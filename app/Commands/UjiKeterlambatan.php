<?php

namespace App\Commands;

use App\Libraries\HakPeran;
use App\Libraries\Keterlambatan;
use App\Libraries\KeterlambatanExcel;
use App\Libraries\KeterlambatanWa;
use App\Libraries\MenuAdmin;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Uji Catat Keterlambatan (menu Guru Piket): katalog & hak peran Guru Piket, pustaka Libraries\Keterlambatan (cari, catat, aturan
 * satu-per-hari, ubah, hapus, guru piket hari itu), dan halaman/controller Admin\Keterlambatan. Memakai siswa & guru nyata di DB
 * lokal, tetapi semua catatan uji (dan baris jadwal piket sementara) dihapus lagi di akhir.
 *
 *   php spark dev:uji-telat
 *   php spark dev:uji-telat --simpan C:/folder/contoh.xlsx   (+ simpan contoh Excel buku piket hari ini untuk dibuka manual)
 */
class UjiKeterlambatan extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-telat';
    protected $description = 'Uji Catat Keterlambatan siswa (Guru Piket): hak, pustaka, halaman.';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    /** @var list<int> */
    private array $idUji = [];
    private ?int $piketUji = null;
    /** @var list<array<string, mixed>> siswa & guru yang dipakai uji pustaka (dipakai lagi oleh uji Excel) */
    private array $siswaUji = [];
    private array $guruUji = [];
    /** @var array<int, array{ortu_telepon: ?string, no_hp_wali: ?string}> nomor HP asli siswa uji (dikembalikan di akhir) */
    private array $hpAsli = [];
    private ?string $templatAsli = null;
    private bool $templatDisimpan = false;
    private string $peranPanggil = 'guru_piket';

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . mb_substr($detail, 0, 400) : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        $this->db = db_connect();
        if (! $this->db->tableExists('keterlambatan_siswa')) {
            CLI::write('Tabel keterlambatan_siswa belum ada. Jalankan "php spark migrate" dulu.', 'red');

            return EXIT_ERROR;
        }
        try {
            $this->ujiHak();
            $this->ujiPustaka();
            $this->ujiExcel();
            $this->ujiWa();
            $this->ujiHalaman();
            $this->ujiApi();
            $this->ujiManual();
            $this->ujiPilihKelas();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            $this->bersihkan();
        }

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function bersihkan(): void
    {
        $ids = array_merge($this->idUji, array_map('intval', array_column($this->db->table('keterlambatan_siswa')->select('id')->like('dicatat_oleh', 'ZZUJI', 'after')->get()->getResultArray(), 'id')));
        if ($ids !== []) {
            $this->db->table('keterlambatan_siswa')->whereIn('id', $ids)->delete();
            $this->db->table('audit_log')->where('tabel', 'keterlambatan_siswa')->whereIn('record_id', $ids)->delete();
        }
        if ($this->piketUji !== null) {
            $this->db->table('jadwal_piket')->where('id', $this->piketUji)->delete();
        }
        foreach ($this->hpAsli as $sid => $hp) {
            $this->db->table('siswa')->where('id', $sid)->update($hp);
        }
        $this->db->table('audit_log')->where('tabel', 'siswa')->whereIn('record_id', array_keys($this->hpAsli) ?: [0])->like('deskripsi', 'diisi dari Catat Keterlambatan')->delete();
        if ($this->templatDisimpan) {
            (new \App\Models\SettingModel())->store(['wa_pesan_terlambat' => $this->templatAsli]);
        }
        session()->remove(['admin', 'keterlambatan_guru', '_ci_old_input', 'error', 'success']);
    }

    // =================================================================
    // 1. Katalog & hak
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Menu & hak akses');
        $e = MenuAdmin::entri('keterlambatan');
        $this->cek('Catat Keterlambatan: menu aktif (bukan "Segera") dan tampil di tabel Hak Akses', $e !== null && ! $e['segera'] && $e['tampil'] && $e['grup'] === 'GURU PIKET');
        $rk = MenuAdmin::entri('keterlambatan_rekap');
        $this->cek('Rekap Keterlambatan: menu aktif & tampil di Hak Akses; alamat rekap/excel/buku miliknya', $rk !== null && ! $rk['segera'] && $rk['tampil']
            && (MenuAdmin::cariAlamat('admin/keterlambatan/rekap')['kunci'] ?? '') === 'keterlambatan_rekap' && (MenuAdmin::cariAlamat('admin/keterlambatan/rekap/excel')['kunci'] ?? '') === 'keterlambatan_rekap'
            && (MenuAdmin::cariAlamat('admin/keterlambatan/rekap/buku')['kunci'] ?? '') === 'keterlambatan_rekap' && (MenuAdmin::cariAlamat('admin/keterlambatan/excel')['kunci'] ?? '') === 'keterlambatan');
        $kunci = static fn (string $a): ?string => MenuAdmin::cariAlamat($a)['kunci'] ?? null;
        $this->cek('alamat catat/cari/ubah/hapus milik entri keterlambatan', $kunci('admin/keterlambatan') === 'keterlambatan' && $kunci('admin/keterlambatan/siswa') === 'keterlambatan'
            && $kunci('admin/keterlambatan/12') === 'keterlambatan' && $kunci('admin/keterlambatan/delete/12') === 'keterlambatan');

        HakPeran::lupakan();
        $p = HakPeran::peran('guru_piket');
        $this->cek('peran Guru Piket ada (bukan peran sistem), halaman awal = Catat Keterlambatan', $p !== null && empty($p['sistem']) && HakPeran::beranda('guru_piket') === 'admin/keterlambatan', json_encode($p));
        $this->cek('Guru Piket: buka, cari, simpan, ubah, hapus keterlambatan', HakPeran::boleh('guru_piket', 'admin/keterlambatan') && HakPeran::boleh('guru_piket', 'admin/keterlambatan/siswa')
            && HakPeran::boleh('guru_piket', 'admin/keterlambatan', 'POST') && HakPeran::boleh('guru_piket', 'admin/keterlambatan/5', 'POST') && HakPeran::boleh('guru_piket', 'admin/keterlambatan/delete/5', 'POST'));
        $this->cek('Guru Piket: Jadwal Piket hanya dilihat (simpan ditolak)', HakPeran::boleh('guru_piket', 'admin/absensi/piket') && ! HakPeran::boleh('guru_piket', 'admin/absensi/piket', 'POST'));
        $this->cek('Guru Piket: unduh Excel harian, buka Rekap, unduh rekap & buku piket rentang', HakPeran::boleh('guru_piket', 'admin/keterlambatan/excel') && HakPeran::boleh('guru_piket', 'admin/keterlambatan/rekap')
            && HakPeran::boleh('guru_piket', 'admin/keterlambatan/rekap/excel') && HakPeran::boleh('guru_piket', 'admin/keterlambatan/rekap/buku'));
        $this->cek('Guru Piket: menu lain tertutup (Dashboard, Data Siswa, Absensi Guru, PKL, Kelola Akun)', ! HakPeran::boleh('guru_piket', 'admin/dashboard') && ! HakPeran::boleh('guru_piket', 'admin/master/siswa')
            && ! HakPeran::boleh('guru_piket', 'admin/absensi') && ! HakPeran::boleh('guru_piket', 'admin/pkl') && ! HakPeran::boleh('guru_piket', 'admin/akun'));
        $this->cek('Operator & Waka Hubin tanpa akses (bawaan); Admin penuh', ! HakPeran::boleh('operator', 'admin/keterlambatan') && ! HakPeran::boleh('hubin', 'admin/keterlambatan')
            && HakPeran::boleh('admin', 'admin/keterlambatan', 'POST'));
    }

    // =================================================================
    // 2. Pustaka
    // =================================================================

    private function ujiPustaka(): void
    {
        $this->bagian('Pustaka Keterlambatan');
        $buku = new Keterlambatan();
        $hari = date('Y-m-d');
        $kmrn = date('Y-m-d', strtotime('-1 day'));

        $this->cek('tanggalSah: hari ini & kemarin sah; besok, 2026-02-30, teks → ditolak', Keterlambatan::tanggalSah($hari) === $hari && Keterlambatan::tanggalSah($kmrn) === $kmrn
            && Keterlambatan::tanggalSah(date('Y-m-d', strtotime('+1 day'))) === null && Keterlambatan::tanggalSah('2026-02-30') === null && Keterlambatan::tanggalSah('kemarin') === null);
        $this->cek('jamSah: "7:05"→07:05:00, "07.30"→07:30:00, "" → kosong, "25:00" → tidak sah', Keterlambatan::jamSah('7:05') === '07:05:00' && Keterlambatan::jamSah('07.30') === '07:30:00'
            && Keterlambatan::jamSah('') === null && Keterlambatan::jamSah('25:00') === false && Keterlambatan::jamSah('ab') === false);
        $this->cek('hariTanggal: "2026-10-19" → "Senin, 19 Oktober 2026"', Keterlambatan::hariTanggal('2026-10-19') === 'Senin, 19 Oktober 2026', Keterlambatan::hariTanggal('2026-10-19'));

        $siswa = $this->db->table('siswa s')->select('s.id, s.nama, s.nis, s.kelas_id, k.nama_kelas, k.shift')->join('kelas k', 'k.id = s.kelas_id')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')->where('s.nis IS NOT NULL')->where('s.nis !=', '')->orderBy('s.id', 'ASC')->limit(3)->get()->getResultArray();
        $guru  = $buku->pilihanGuru();
        if (count($siswa) < 3 || count($guru) < 2) {
            $this->cek('data uji cukup (3 siswa aktif berkelas & ber-NIS, 2 guru)', false, count($siswa) . ' siswa, ' . count($guru) . ' guru');

            return;
        }
        [$s1, $s2, $s3] = $siswa;
        [$g1, $g2]      = $guru;
        $this->siswaUji = $siswa;
        $this->guruUji  = [$g1, $g2];
        $saja = ['id' => null, 'nama' => 'ZZUJI Guru Piket'];
        // Sisa uji yang gagal dibersihkan dulu (catatan hari ini/kemarin untuk siswa uji).
        $this->db->table('keterlambatan_siswa')->whereIn('siswa_id', [$s1['id'], $s2['id'], $s3['id']])->whereIn('tanggal', [$hari, $kmrn])->like('dicatat_oleh', 'ZZUJI', 'after')->delete();
        $sudahAda = $this->db->table('keterlambatan_siswa')->whereIn('siswa_id', [$s1['id'], $s2['id'], $s3['id']])->whereIn('tanggal', [$hari, $kmrn])->countAllResults();
        if ($sudahAda > 0) {
            $this->cek('siswa uji belum punya catatan asli hari ini/kemarin', false, 'lewati agar data asli tidak tersentuh');

            return;
        }

        $kata = mb_substr((string) $s1['nama'], 0, 5);
        $h    = $buku->cariSiswa($kata, $hari);
        $this->cek('cari nama "' . $kata . '" → siswa ditemukan, berkelas', in_array((int) $s1['id'], array_column($h, 'id'), true) && $this->dari($h, $s1['id'])['kelas'] === $s1['nama_kelas'], json_encode(array_slice($h, 0, 3)));
        $h = $buku->cariSiswa((string) $s2['nis'], $hari);
        $this->cek('cari NIS ' . $s2['nis'] . ' → siswa ditemukan', in_array((int) $s2['id'], array_column($h, 'id'), true));
        $this->cek('cari 1 huruf → kosong (minimal 2)', $buku->cariSiswa('a', $hari) === []);
        $keluar = $this->db->table('siswa')->select('nama')->where('status', 'keluar')->where('deleted_at', null)->get()->getRowArray();
        if ($keluar) {
            $this->cek('siswa berstatus keluar tidak muncul di pencarian', ! in_array($keluar['nama'], array_column($buku->cariSiswa((string) $keluar['nama'], $hari), 'nama'), true));
        }

        // --- catat ---
        $r1 = $buku->catat(['siswa_id' => $s1['id'], 'tanggal' => $hari, 'jam' => '07:20', 'guru_id' => $g1['id'], 'sanksi' => '  Membersihkan   halaman ', 'keterangan' => 'Ban bocor'], $saja);
        $this->ingat($r1);
        $row = $r1['ok'] ? $buku->cari($r1['id']) : null;
        $this->cek('catat → tersimpan, nama/kelas/shift/tahun ajaran/guru piket disalin, spasi sanksi dirapikan', $row !== null && $row['nama_siswa'] === $s1['nama'] && $row['nama_kelas'] === $s1['nama_kelas']
            && $row['shift'] === $s1['shift'] && $row['tahun_ajaran'] === Keterlambatan::tahunAjaran() && $row['guru_piket'] === $g1['nama'] && $row['sanksi'] === 'Membersihkan halaman'
            && $row['jam'] === '07:20:00' && $row['dicatat_oleh'] === 'ZZUJI Guru Piket', json_encode($r1) . json_encode($row));
        $r = $buku->catat(['siswa_id' => $s1['id'], 'tanggal' => $hari, 'jam' => '07:40', 'guru_id' => $g2['id']], $saja);
        $this->cek('siswa sama, hari sama → ditolak "sudah tercatat … jam 07:20"', ! $r['ok'] && str_contains($r['pesan'], 'sudah tercatat') && str_contains($r['pesan'], '07:20'), $r['pesan']);
        $this->cek('pencarian menandai siswa yang sudah dicatat hari itu', ($this->dari($buku->cariSiswa((string) $s1['nis'], $hari), $s1['id'])['tercatat'] ?? null) === 'jam 07:20');
        $this->ingat($r = $buku->catat(['siswa_id' => $s1['id'], 'tanggal' => $kmrn, 'guru_id' => $g1['id']], $saja));
        $this->cek('siswa sama, tanggal lain (kemarin, tanpa jam) → boleh', $r['ok'], $r['pesan']);
        $this->ingat($r = $buku->catat(['siswa_id' => $s2['id'], 'tanggal' => $hari, 'jam' => '06:55', 'guru_id' => $g2['id']], $saja));
        $this->ingat($r3 = $buku->catat(['siswa_id' => $s3['id'], 'tanggal' => $hari, 'jam' => '', 'guru_id' => $g2['id']], $saja));
        $this->cek('dua siswa lain tercatat (satu tanpa jam)', $r['ok'] && $r3['ok']);

        $tolak = [
            'tanggal besok'      => ['siswa_id' => $s2['id'], 'tanggal' => date('Y-m-d', strtotime('+1 day')), 'guru_id' => $g1['id']],
            'siswa tidak ada'    => ['siswa_id' => 99999999, 'tanggal' => $hari, 'guru_id' => $g1['id']],
            'tanpa siswa'        => ['tanggal' => $hari, 'guru_id' => $g1['id']],
            'tanpa guru piket'   => ['siswa_id' => $s2['id'], 'tanggal' => $kmrn],
            'jam tidak sah'      => ['siswa_id' => $s2['id'], 'tanggal' => $kmrn, 'jam' => '31:00', 'guru_id' => $g1['id']],
            'sanksi > 150 huruf' => ['siswa_id' => $s2['id'], 'tanggal' => $kmrn, 'guru_id' => $g1['id'], 'sanksi' => str_repeat('x', 151)],
        ];
        $lolos = [];
        foreach ($tolak as $judul => $d) {
            $r = $buku->catat($d, $saja);
            $this->ingat($r);
            if ($r['ok']) {
                $lolos[] = $judul;
            }
        }
        $this->cek('isian tidak sah ditolak: ' . implode(', ', array_keys($tolak)), $lolos === [], 'lolos: ' . implode(', ', $lolos));

        $d = $buku->daftar($hari);
        $urut = array_column(array_values(array_filter($d, fn (array $x): bool => in_array((int) $x['id'], $this->idUji, true))), 'siswa_id');
        $this->cek('daftar hari ini urut jam datang, tanpa jam di akhir; NIS ikut', array_map('intval', $urut) === [(int) $s2['id'], (int) $s1['id'], (int) $s3['id']]
            && (string) $this->dariId($d, $r1['id'])['nis'] === (string) $s1['nis'], json_encode($urut));

        // --- ubah & hapus ---
        $u = $buku->ubah($r1['id'], ['jam' => '07:25', 'guru_id' => $g2['id'], 'sanksi' => 'Menyapu kelas', 'keterangan' => '', 'siswa_id' => $s2['id'], 'tanggal' => $kmrn]);
        $row = $buku->cari($r1['id']);
        $this->cek('ubah: jam, guru piket, sanksi berubah; keterangan dikosongkan; siswa & tanggal TETAP', $u['ok'] && $row['jam'] === '07:25:00' && $row['guru_piket'] === $g2['nama'] && $row['sanksi'] === 'Menyapu kelas'
            && $row['keterangan'] === null && (int) $row['siswa_id'] === (int) $s1['id'] && $row['tanggal'] === $hari, json_encode($row));
        $this->cek('ubah tanpa guru piket → ditolak', ! $buku->ubah($r1['id'], ['jam' => '07:25'])['ok']);
        $this->cek('saran sanksi memuat sanksi yang pernah ditulis', in_array('Menyapu kelas', $buku->saranSanksi(50), true));
        $h = $buku->hapus($r3['id']);
        $this->cek('hapus → catatan hilang; hapus lagi → "tidak ditemukan"', $h['ok'] && $buku->cari($r3['id']) === null && ! $buku->hapus($r3['id'])['ok']);
        $this->cek('jejak Audit Log: catat, ubah, hapus', $this->db->table('audit_log')->where('tabel', 'keterlambatan_siswa')->whereIn('record_id', [$r1['id'], $r3['id']])->whereIn('aksi', ['create', 'update', 'delete'])->countAllResults() >= 4);

        // --- guru piket hari itu (Jadwal Piket) ---
        $hariRow = (new \App\Models\HariModel())->byWeekday((int) date('N'));
        if ($hariRow !== null) {
            $sudah = $this->db->table('jadwal_piket')->where(['hari_id' => $hariRow['id'], 'shift' => 'siang', 'guru_id' => $g2['id']])->countAllResults() > 0;
            if (! $sudah) {
                $this->db->table('jadwal_piket')->insert(['hari_id' => $hariRow['id'], 'shift' => 'siang', 'guru_id' => $g2['id'], 'created_at' => date('Y-m-d H:i:s')]);
                $this->piketUji = (int) $this->db->insertID();
            }
            $pk = $buku->guruPiketHari($hari);
            $this->cek('guru piket hari ini diambil dari Jadwal Piket (shift siang)', in_array((int) $g2['id'], array_column($pk['siang'], 'id'), true), json_encode($pk));
        }
    }

    // =================================================================
    // 3. Excel buku piket (dibandingkan dengan contoh sekolah) & rekap
    // =================================================================

    private function ujiExcel(): void
    {
        $this->bagian('Excel buku piket & rekap');
        if (count($this->siswaUji) < 3 || count($this->guruUji) < 2) {
            $this->cek('data uji pustaka tersedia', false);

            return;
        }
        [$s1, $s2, $s3] = $this->siswaUji;
        [$g1, $g2]      = $this->guruUji;
        $buku = new Keterlambatan();
        $hari = date('Y-m-d');
        $kmrn = date('Y-m-d', strtotime('-1 day'));
        // Keadaan dari uji pustaka: hari ini s2 06:55 (g2), s1 07:25 (g2, "Menyapu kelas"); kemarin s1 (g1). + s3 berketerangan panjang.
        $panjang = trim(str_repeat('Hujan deras dan jalan macet ', 3));
        $this->ingat($buku->catat(['siswa_id' => $s3['id'], 'tanggal' => $hari, 'jam' => '07:50', 'guru_id' => $g1['id'], 'sanksi' => 'Push up', 'keterangan' => $panjang], ['id' => null, 'nama' => 'ZZUJI Guru Piket']));

        $asli = KeterlambatanExcel::bukuPiket($buku->bahanBuku($hari, $hari));
        if (is_string($simpan = CLI::getOption('simpan')) && $simpan !== '') {
            file_put_contents($simpan, \App\Libraries\HonorCetak::xlsx($asli));
            CLI::write('  (contoh Excel hari ini disimpan: ' . $simpan . ')', 'light_gray');
        }
        $ss = $this->baca($asli);
        $ws = $ss->getSheet(0);
        $this->cek('satu hari → satu lembar bernama tanggal', $ss->getSheetCount() === 1 && $ws->getTitle() === date('d-m-Y'), $ws->getTitle());
        $this->cek('judul: BUKU PIKET… / nama sekolah + kabupaten (tanpa kurung) / TAHUN PELAJARAN 2026-2027', $ws->getCell('A1')->getValue() === 'BUKU PIKET KETERLAMBATAN SISWA'
            && $ws->getCell('A2')->getValue() === KeterlambatanExcel::namaSekolah() && ! str_contains((string) $ws->getCell('A2')->getValue(), '(')
            && $ws->getCell('A3')->getValue() === 'TAHUN PELAJARAN ' . str_replace('/', '-', Keterlambatan::tahunAjaran()), $ws->getCell('A2')->getValue() . ' | ' . $ws->getCell('A3')->getValue());
        $this->cek('HARI / TANGGAL & GURU PIKET terisi (guru = yang mencatat, urut pertama mencatat)', $ws->getCell('C5')->getValue() === ': ' . Keterlambatan::hariTanggal($hari)
            && $ws->getCell('C6')->getValue() === ': ' . $g2['nama'] . ', ' . $g1['nama'], $ws->getCell('C5')->getValue() . ' | ' . $ws->getCell('C6')->getValue());
        $data = $ws->rangeToArray('A9:F11', null, false, false);
        $tgl  = Keterlambatan::HARI[(int) date('N')] . ', ' . date('d-m-Y');
        $this->cek('baris data urut jam: NO, HARI/TANGGAL, NAMA, KELAS, SANKSI, KETERANGAN', $data[0][0] == 1 && $data[0][1] === $tgl && $data[0][2] === $s2['nama'] && $data[0][3] === $s2['nama_kelas']
            && $data[1][2] === $s1['nama'] && $data[1][4] === 'Menyapu kelas' && $data[2][2] === $s3['nama'] && $data[2][4] === 'Push up' && $data[2][5] === $panjang, json_encode($data));
        $this->cek('50 baris bernomor bergaris (sisa kosong), baris 59 tanpa garis', $ws->getCell('A58')->getValue() == 50 && $ws->getCell('A59')->getValue() === null && $ws->getCell('C12')->getValue() === null
            && $ws->getStyle('F58')->getBorders()->getBottom()->getBorderStyle() === 'thin' && $ws->getStyle('A59')->getBorders()->getTop()->getBorderStyle() === 'none'
            && $ws->getStyle('F58')->getFont()->getName() === 'Times New Roman');
        $this->cek('keterangan panjang dilipat, baris ikut tinggi', $ws->getStyle('F11')->getAlignment()->getWrapText() && $ws->getRowDimension(11)->getRowHeight() > 14 && $ws->getRowDimension(10)->getRowHeight() == 14);

        // --- banding dengan berkas contoh sekolah (hanya di laptop yang punya formatdatasekolah) ---
        $contoh = ROOTPATH . 'formatdatasekolah/KETERLAMBATAN SISWA DI GURU PIKET.xlsx';
        if (is_file($contoh)) {
            $t    = \PhpOffice\PhpSpreadsheet\IOFactory::load($contoh)->getActiveSheet();
            $beda = [];
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $k) {
                if (abs($t->getColumnDimension($k)->getWidth() - $ws->getColumnDimension($k)->getWidth()) > 0.01) {
                    $beda[] = 'lebar ' . $k;
                }
            }
            foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $b) {
                if ($t->getRowDimension($b)->getRowHeight() != $ws->getRowDimension($b)->getRowHeight()) {
                    $beda[] = 'tinggi baris ' . $b;
                }
            }
            foreach (['A1', 'A2', 'A3', 'A5', 'C5', 'A6', 'C6', 'A8', 'B8', 'C8', 'D8', 'E8', 'F8', 'A9', 'B9', 'C9', 'D9', 'E9', 'F9', 'A20', 'F20'] as $sel) {
                $a = $t->getStyle($sel);
                $o = $ws->getStyle($sel);
                $ga = [$a->getFont()->getName(), $a->getFont()->getSize(), $a->getFont()->getBold(), $a->getAlignment()->getHorizontal(), $a->getAlignment()->getVertical()];
                $go = [$o->getFont()->getName(), $o->getFont()->getSize(), $o->getFont()->getBold(), $o->getAlignment()->getHorizontal(), $o->getAlignment()->getVertical()];
                foreach (['Top', 'Bottom', 'Left', 'Right'] as $g) {
                    $ga[] = $a->getBorders()->{'get' . $g}()->getBorderStyle();
                    $go[] = $o->getBorders()->{'get' . $g}()->getBorderStyle();
                }
                if ($ga != $go) {
                    $beda[] = $sel . ' ' . json_encode($ga) . '≠' . json_encode($go);
                }
            }
            foreach (['A1', 'A5', 'A6', 'A8', 'B8', 'C8', 'D8', 'E8', 'F8', 'A9', 'A58'] as $sel) {
                if ($t->getCell($sel)->getValue() != $ws->getCell($sel)->getValue()) {
                    $beda[] = 'isi ' . $sel;
                }
            }
            $ma = array_keys($t->getMergeCells());
            $mo = array_keys($ws->getMergeCells());
            sort($ma);
            sort($mo);
            if ($ma !== $mo) {
                $beda[] = 'sel gabung ' . implode(' ', $mo);
            }
            $pa = $t->getPageSetup();
            $po = $ws->getPageSetup();
            $ka = $t->getPageMargins();
            $ko = $ws->getPageMargins();
            if ([$pa->getOrientation(), $pa->getPaperSize(), $ka->getTop(), $ka->getBottom(), $ka->getLeft(), $ka->getRight(), $ka->getHeader(), $ka->getFooter(), $t->getPrintGridlines()]
                != [$po->getOrientation(), $po->getPaperSize(), $ko->getTop(), $ko->getBottom(), $ko->getLeft(), $ko->getRight(), $ko->getHeader(), $ko->getFooter(), $ws->getPrintGridlines()]) {
                $beda[] = 'pengaturan cetak';
            }
            $this->cek('SAMA dengan contoh sekolah: lebar kolom, tinggi baris 1–10, huruf/tebal/perataan/garis, isi tetap, sel gabung, kertas & margin', $beda === [], implode(' | ', $beda));
        } else {
            CLI::write('  (banding contoh dilewati: formatdatasekolah/KETERLAMBATAN SISWA DI GURU PIKET.xlsx tidak ada)', 'light_gray');
        }

        // --- hari kosong, > 50 baris, rentang ---
        $kosong = $buku->bahanBuku('2020-01-01', '2020-01-01');
        $wk     = $this->baca(KeterlambatanExcel::bukuPiket($kosong))->getSheet(0);
        $jadwal = $buku->guruLembar('2020-01-01', []);
        $this->cek('hari tanpa catatan → lembar kosong 50 baris; guru piket dari jadwal atau garis isian', count($kosong) === 1 && $kosong[0]['rows'] === [] && $wk->getCell('A58')->getValue() == 50
            && $wk->getCell('C9')->getValue() === null && $wk->getCell('C6')->getValue() === ($jadwal !== '' ? ': ' . $jadwal : ': _____________________________'), (string) $wk->getCell('C6')->getValue());
        $banyak = array_map(static fn (int $i): array => ['tanggal' => $hari, 'nama_siswa' => 'Siswa ' . $i, 'nama_kelas' => 'X', 'sanksi' => '', 'keterangan' => '', 'tahun_ajaran' => '2026/2027'], range(1, 55));
        $wb = $this->baca(KeterlambatanExcel::bukuPiket([['tanggal' => $hari, 'rows' => $banyak, 'guru' => 'Uji']]))->getSheet(0);
        $this->cek('55 siswa → tabel memanjang sampai nomor 55', $wb->getCell('A63')->getValue() == 55 && $wb->getCell('C63')->getValue() === 'Siswa 55' && $wb->getCell('A64')->getValue() === null
            && $wb->getStyle('F63')->getBorders()->getBottom()->getBorderStyle() === 'thin');
        $rentang = $buku->bahanBuku($kmrn, $hari);
        $sr = $this->baca(KeterlambatanExcel::bukuPiket($rentang));
        $this->cek('rentang kemarin–hari ini → 2 lembar (hanya hari yang ada catatan), lembar kemarin berisi siswanya', $sr->getSheetCount() === 2 && $sr->getSheet(0)->getTitle() === date('d-m-Y', strtotime($kmrn))
            && $sr->getSheet(0)->getCell('C9')->getValue() === $s1['nama'] && $sr->getSheet(0)->getCell('C6')->getValue() === ': ' . $g1['nama'] && $sr->getSheet(1)->getTitle() === date('d-m-Y'));
        $this->cek('rentang tanpa catatan → tidak ada lembar', $buku->bahanBuku('2020-01-01', '2020-01-31') === []);

        // --- rentang sah & rekap ---
        $r1 = Keterlambatan::rentangSah('', '');
        $r2 = Keterlambatan::rentangSah($hari, $kmrn);
        $r3 = Keterlambatan::rentangSah('2024-01-01', $hari);
        $r4 = Keterlambatan::rentangSah(date('Y-m-d', strtotime('+2 day')), $hari);
        $this->cek('rentangSah: kosong = awal bulan–hari ini; terbalik ditukar; > 366 hari dipotong + pesan; tanggal depan → bawaan + pesan', $r1['mulai'] === date('Y-m-01') && $r1['selesai'] === $hari && $r1['pesan'] === null
            && $r2['mulai'] === $kmrn && $r2['selesai'] === $hari && $r3['selesai'] === $hari && \App\Libraries\IsianBantu::hariInklusif($r3['mulai'], $r3['selesai']) === 366 && $r3['pesan'] !== null
            && $r4['mulai'] === date('Y-m-01') && $r4['pesan'] !== null, json_encode([$r2, $r3, $r4]));
        $rk = $buku->rekap($kmrn, $hari);
        $satu = array_values(array_filter($rk['siswa'], static fn (array $s): bool => $s['nama'] === $s1['nama']))[0] ?? [];
        $this->cek('rekap per siswa: ' . $s1['nama'] . ' 2× (kemarin & hari ini), paling atas', ($satu['jumlah'] ?? 0) === 2 && $satu['tanggal'] === [$kmrn, $hari] && $rk['siswa'][0]['jumlah'] >= 2
            && $rk['total'] >= 4 && $rk['jumlah_hari'] === 2, json_encode(array_slice($rk['siswa'], 0, 3)));
        $kls = array_values(array_filter($rk['kelas'], static fn (array $k): bool => $k['kelas'] === $s1['nama_kelas']))[0] ?? [];
        $this->cek('rekap per kelas: kelas ' . $s1['nama_kelas'] . ' terhitung', ($kls['jumlah'] ?? 0) >= 2 && ($kls['siswa'] ?? 0) >= 1, json_encode($rk['kelas']));
        $rkK = $buku->rekap($kmrn, $hari, (int) $s2['kelas_id']);
        $this->cek('saring satu kelas → hanya siswa kelas itu', $rkK['total'] >= 1 && array_unique(array_column($rkK['siswa'], 'kelas')) === [$s2['nama_kelas']], json_encode($rkK['kelas']));
        $sx = $this->baca(KeterlambatanExcel::rekap($rk, $kmrn, $hari, ''));
        $this->cek('Excel rekap: lembar Per siswa & Per kelas, baris pertama = siswa terbanyak', $sx->getSheetCount() === 2 && $sx->getSheet(0)->getTitle() === 'Per siswa' && $sx->getSheet(1)->getTitle() === 'Per kelas'
            && $sx->getSheet(0)->getCell('A1')->getValue() === 'REKAP KETERLAMBATAN SISWA' && $sx->getSheet(0)->getCell('B6')->getValue() === $rk['siswa'][0]['nama'] && $sx->getSheet(0)->getCell('E6')->getValue() == $rk['siswa'][0]['jumlah']);

        // --- controller: unduhan & halaman rekap ---
        $res = $this->panggil('admin/keterlambatan/excel', 'GET', 'excel', [], ['tanggal' => $hari]);
        $this->cek('unduh Excel harian (controller): berkas .xlsx bernama tanggal', $res instanceof \CodeIgniter\HTTP\ResponseInterface && str_contains($res->getHeaderLine('Content-Type'), 'spreadsheetml')
            && str_contains($res->getHeaderLine('Content-Disposition'), KeterlambatanExcel::namaBerkasHari($hari)) && str_starts_with((string) $res->getBody(), 'PK'));
        $html = (string) $this->panggil('admin/keterlambatan/rekap', 'GET', 'rekap', [], ['mulai' => $kmrn, 'selesai' => $hari]);
        $this->cek('halaman Rekap: tab Per siswa memuat siswa & tombol unduh', str_contains($html, 'Rekap per siswa') && str_contains($html, esc((string) $s1['nama'])) && str_contains($html, '/rekap/buku?') && str_contains($html, '/rekap/excel?'));
        $html = (string) $this->panggil('admin/keterlambatan/rekap', 'GET', 'rekap', [], ['mulai' => $kmrn, 'selesai' => $hari, 'tampil' => 'kelas']);
        $this->cek('halaman Rekap: tab Per kelas', str_contains($html, 'Rekap per kelas') && str_contains($html, esc((string) $s1['nama_kelas'])));
        $res = $this->panggil('admin/keterlambatan/rekap/buku', 'GET', 'rekapBuku', [], ['mulai' => $kmrn, 'selesai' => $hari]);
        $res2 = $this->panggil('admin/keterlambatan/rekap/excel', 'GET', 'rekapExcel', [], ['mulai' => $kmrn, 'selesai' => $hari]);
        $this->cek('unduh buku piket rentang & rekap (controller) → .xlsx', ! $res instanceof RedirectResponse && str_starts_with((string) $res->getBody(), 'PK') && str_starts_with((string) $res2->getBody(), 'PK')
            && str_contains($res->getHeaderLine('Content-Disposition'), ' sd '));
        $res = $this->panggil('admin/keterlambatan/rekap/buku', 'GET', 'rekapBuku', [], ['mulai' => '2020-01-01', 'selesai' => '2020-01-31']);
        $this->cek('unduh buku piket rentang kosong → kembali ke Rekap dengan pesan', $res instanceof RedirectResponse && str_contains((string) session('error'), 'Tidak ada catatan'));
    }

    // =================================================================
    // 4. Kabar WhatsApp ke orang tua
    // =================================================================

    private function ujiWa(): void
    {
        $this->bagian('Kabar WhatsApp ke orang tua');
        if (count($this->siswaUji) < 3) {
            $this->cek('data uji pustaka tersedia', false);

            return;
        }
        [$s1, $s2, $s3] = $this->siswaUji;
        $buku = new Keterlambatan();
        $hari = date('Y-m-d');
        $W    = KeterlambatanWa::class;
        if (! KeterlambatanWa::kolomAda()) {
            $this->cek('kolom settings.wa_pesan_terlambat ada (php spark migrate)', false);

            return;
        }

        $this->cek('hpPertama: dua nomor "0815… / 0881…" → nomor pertama; +62 → 08…; telepon rumah ditolak; "rumah atau HP" → HP', $W::hpPertama('081585367649 / 0881011083904') === '081585367649'
            && $W::hpPertama('+62 812-3456-7890') === '081234567890' && $W::hpPertama('0215551234') === null && $W::hpPertama('') === null
            && $W::hpPertama('0215551234 atau 0857 1111 2222') === '085711112222');
        $this->cek('nomorOrtu: telepon orang tua dulu, cadangan HP wali; HP siswa tidak dipakai', $W::nomorOrtu(['ortu_telepon' => '0812 1111 2222', 'no_hp_wali' => '085733334444'])['sumber'] === 'orang tua'
            && $W::nomorOrtu(['ortu_telepon' => '', 'no_hp_wali' => '085733334444']) === ['hp' => '085733334444', 'sumber' => 'wali', 'ada_isian' => true]
            && $W::nomorOrtu(['ortu_telepon' => null, 'no_hp_wali' => null, 'no_hp' => '081299998888'])['hp'] === null
            && $W::nomorOrtu(['ortu_telepon' => '0215551234', 'no_hp_wali' => ''])['ada_isian'] === true);

        $baris = ['siswa_id' => 1, 'nama_siswa' => 'ADE FIRMANSYAH', 'nama_kelas' => 'X TKJ 1', 'tanggal' => '2026-10-19', 'jam' => '07:25:00', 'sanksi' => 'Menyapu', 'keterangan' => '',
            'guru_piket' => 'Bu Iin', 'ortu_telepon' => '0812-3456-7890', 'no_hp_wali' => null];
        $k = $W::kabar($baris, $W::PESAN_BAWAAN, 'SMK BINA NUSA');
        $this->cek('kabar: tautan wa.me/62…, pesan bawaan berisi nama (huruf judul), kelas, "Senin, 19 Oktober 2026 pukul 07.25", sekolah', $k['url'] !== null && str_starts_with($k['url'], 'https://wa.me/6281234567890?text=')
            && str_contains($k['pesan'], 'Ade Firmansyah (X TKJ 1)') && str_contains($k['pesan'], 'Senin, 19 Oktober 2026 pukul 07.25') && str_contains($k['pesan'], 'SMK BINA NUSA') && ! str_contains($k['pesan'], '{'), $k['pesan']);
        $k2 = $W::kabar(['jam' => null, 'ortu_telepon' => ''] + $baris, '{nama} terlambat {waktu}. Sanksi: {sanksi}. Piket: {guru_piket}', 'X');
        $this->cek('kabar tanpa jam → tanpa "pukul"; penanda lain terisi; tanpa nomor → alasan "belum ada"', $k2['pesan'] === 'Ade Firmansyah terlambat Senin, 19 Oktober 2026. Sanksi: Menyapu. Piket: Bu Iin'
            && $k2['url'] === null && $k2['alasan_tidak'] === 'Nomor HP orang tua belum ada', json_encode($k2));

        // --- templat (dikembalikan di akhir) ---
        $this->templatAsli     = ((new \App\Models\SettingModel())->get()['wa_pesan_terlambat'] ?? null) ?: null;
        $this->templatDisimpan = true;
        $g1 = $W::simpanTemplat('Halo ortu {nama}, terlambat {waktu}.');
        $g2 = $W::simpanTemplat('Halo {perusahaan}');
        $g3 = $W::simpanTemplat(str_repeat('x', 601));
        $ok = $g1 === null && $W::templat() === 'Halo ortu {nama}, terlambat {waktu}.' && $g2 !== null && str_contains($g2, '{perusahaan}') && $g3 !== null;
        $W::simpanTemplat('');
        $this->cek('templat: simpan dengan penanda sah; penanda asing & > 600 huruf ditolak; kosong → kembali ke bawaan', $ok && $W::templat() === $W::PESAN_BAWAAN, json_encode([$g1, $g2, $g3]));

        // --- tandai & isi nomor (nomor asli siswa uji disimpan, dikembalikan di akhir) ---
        foreach ([$s1, $s3] as $s) {
            $this->hpAsli[(int) $s['id']] ??= $this->db->table('siswa')->select('ortu_telepon, no_hp_wali')->where('id', $s['id'])->get()->getRowArray();
        }
        $this->db->table('siswa')->where('id', $s1['id'])->update(['ortu_telepon' => '0812 1111 2222 / 0857 0000 1111', 'no_hp_wali' => null]);
        $this->db->table('siswa')->where('id', $s3['id'])->update(['ortu_telepon' => null, 'no_hp_wali' => null]);
        $rows = $buku->daftar($hari);
        $r1   = $this->baris($rows, $s1['id']);
        $r3   = $this->baris($rows, $s3['id']);
        $k1   = $W::kabar($r1, $W::templat(), 'X');
        $this->cek('daftar memuat nomor orang tua → tombol WA ke nomor pertama', ($k1['hp'] ?? '') === '081211112222' && str_contains((string) $k1['url'], 'wa.me/6281211112222'), json_encode($k1));
        $t = (new KeterlambatanWa())->tandai((int) $r1['id'], 'ZZUJI Guru Piket');
        $row = $buku->cari((int) $r1['id']);
        $this->cek('tandai sudah dikabari → waktu & nama tercatat; id tak ada → ditolak', $t['ok'] && $row['dikabari_at'] !== null && $row['dikabari_oleh'] === 'ZZUJI Guru Piket' && ! (new KeterlambatanWa())->tandai(99999999, 'x')['ok']);

        $wa = new KeterlambatanWa();
        $a  = $wa->isiHp((int) $r3['id'], '0812ABC');
        $b  = $wa->isiHp((int) $r3['id'], '021 555 1234');
        $c  = $wa->isiHp((int) $r3['id'], '0812-7777-8888');
        $sx = $this->db->table('siswa')->select('ortu_telepon, no_hp_wali')->where('id', $s3['id'])->get()->getRowArray();
        $d  = $wa->isiHp((int) $r3['id'], '085799990000');
        $this->cek('isi nomor: huruf & telepon rumah ditolak; HP sah → Telepon orang tua di Data Siswa; isian kedua ditolak (tidak menimpa)', ! $a['ok'] && ! $b['ok'] && $c['ok']
            && $sx['ortu_telepon'] === '081277778888' && $sx['no_hp_wali'] === null && ! $d['ok'] && str_contains($d['pesan'], 'sudah berisi'), json_encode([$a, $b, $c, $d, $sx]));
        $this->db->table('siswa')->where('id', $s3['id'])->update(['ortu_telepon' => '021 555 1234', 'no_hp_wali' => null]);
        $e  = $wa->isiHp((int) $r3['id'], '0812 6666 5555');
        $sx = $this->db->table('siswa')->select('ortu_telepon, no_hp_wali')->where('id', $s3['id'])->get()->getRowArray();
        $this->cek('telepon orang tua berisi nomor rumah → HP disimpan ke HP wali, nomor rumah tetap', $e['ok'] && $sx['ortu_telepon'] === '021 555 1234' && $sx['no_hp_wali'] === '081266665555', json_encode($sx));

        // --- hak & halaman ---
        $this->cek('hak: Guru Piket boleh kabari & isi nomor; ubah templat KHUSUS Admin (tak ada di tabel Hak Akses)', HakPeran::boleh('guru_piket', 'admin/keterlambatan/5/kabari', 'POST')
            && HakPeran::boleh('guru_piket', 'admin/keterlambatan/5/hp', 'POST') && ! HakPeran::boleh('guru_piket', 'admin/keterlambatan/pesan', 'POST')
            && HakPeran::boleh('admin', 'admin/keterlambatan/pesan', 'POST') && (MenuAdmin::entri('keterlambatan_pesan')['jenis'] ?? '') === MenuAdmin::ADMIN);
        $this->db->table('siswa')->where('id', $s3['id'])->update(['ortu_telepon' => null, 'no_hp_wali' => null]);
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index');
        $cekHal = [
            'tombol WA'       => str_contains($html, 'data-kabar-tandai="' . esc(site_url('admin/keterlambatan/' . $r1['id'] . '/kabari'), 'attr') . '"'),
            'Kirim ulang'     => str_contains($html, 'Kirim ulang'),
            'Isi no. ortu'    => str_contains($html, 'data-isi-hp="' . esc(site_url('admin/keterlambatan/' . $r3['id'] . '/hp'), 'attr') . '"'),
            'status dikabari' => str_contains($html, 'Sudah dikabari'),
            'tanpa templat'   => ! str_contains($html, 'tl-pesan-wa'),
            'pkl-kabar.js'    => str_contains($html, 'pkl-kabar.js'),
        ];
        $this->cek('halaman (Guru Piket): tombol "Kirim ulang" WA untuk siswa bernomor, "Isi no. ortu" untuk yang belum, tanpa dialog templat', ! in_array(false, $cekHal, true), json_encode($cekHal));
        $this->peranPanggil = 'admin';
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index');
        $this->cek('halaman (Admin): ada tombol & dialog "Pesan WA ke ortu"', str_contains($html, 'data-buka-dialog="tl-pesan-wa"') && str_contains($html, 'name="wa_pesan"'));
        $this->panggil('admin/keterlambatan/pesan', 'POST', 'pesan', ['wa_pesan' => 'Uji {nama} {waktu}', 'tanggal' => $hari]);
        $this->cek('simpan templat lewat halaman (Admin) → tersimpan', str_contains((string) session('success'), 'disimpan') && $W::templat() === 'Uji {nama} {waktu}');
        $W::simpanTemplat('');
        $this->peranPanggil = 'guru_piket';
        $res = $this->panggil('admin/keterlambatan/' . $r1['id'] . '/kabari', 'POST', 'kabari', [], [], true, (int) $r1['id']);
        $j   = json_decode((string) $res->getBody(), true);
        $this->cek('POST kabari (controller) → JSON ok + waktu + oleh + csrf', ($j['ok'] ?? false) && ! empty($j['waktu']) && ($j['oleh'] ?? '') === 'ZZUJI Guru Piket' && ! empty($j['csrf']), (string) $res->getBody());
        $this->panggil('admin/keterlambatan/' . $r3['id'] . '/hp', 'POST', 'isiHp', ['hp' => '0813 2222 3333'], [], true, (int) $r3['id']);
        $this->cek('POST isi nomor (controller) → pesan sukses, nomor tersimpan', str_contains((string) session('success'), 'disimpan ke Data Siswa')
            && $this->db->table('siswa')->select('ortu_telepon')->where('id', $s3['id'])->get()->getRowArray()['ortu_telepon'] === '081322223333');
    }

    private function baris(array $rows, int|string $siswaId): array
    {
        foreach ($rows as $r) {
            if ((int) $r['siswa_id'] === (int) $siswaId) {
                return $r;
            }
        }

        return [];
    }

    /** Tulis buku kerja ke .xlsx lalu baca lagi (menguji berkas sungguhan, bukan objek di memori). */
    private function baca(\PhpOffice\PhpSpreadsheet\Spreadsheet $ss): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $f = WRITEPATH . 'cache/uji-telat-' . uniqid() . '.xlsx';
        file_put_contents($f, \App\Libraries\HonorCetak::xlsx($ss));
        try {
            return \PhpOffice\PhpSpreadsheet\IOFactory::load($f);
        } finally {
            @unlink($f);
        }
    }

    // =================================================================
    // 4. Halaman (controller)
    // =================================================================

    private function ujiHalaman(): void
    {
        $this->bagian('Halaman Catat Keterlambatan');
        $buku  = new Keterlambatan();
        $hari  = date('Y-m-d');
        $guru  = $buku->pilihanGuru();
        $siswa = $this->db->table('siswa s')->select('s.id, s.nama, s.nis')->join('kelas k', 'k.id = s.kelas_id')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')->orderBy('s.id', 'DESC')->limit(1)->get()->getRowArray();
        if (! $siswa || $guru === [] || $this->db->table('keterlambatan_siswa')->where(['siswa_id' => $siswa['id'], 'tanggal' => $hari])->countAllResults() > 0) {
            $this->cek('data uji halaman tersedia', false);

            return;
        }

        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index');
        $this->cek('halaman terbuka: formulir catat, pilihan guru piket, daftar, panduan', str_contains($html, 'x-data="keterlambatanPage"') && str_contains($html, 'name="guru_id"')
            && str_contains($html, 'Daftar terlambat') && str_contains($html, 'keterlambatan.js') && str_contains($html, Keterlambatan::hariTanggal($hari)));
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index', [], ['tanggal' => date('Y-m-d', strtotime('+3 day'))]);
        $this->cek('tanggal masa depan di alamat → kembali ke hari ini', str_contains($html, Keterlambatan::hariTanggal($hari)));

        $j = json_decode((string) $this->panggil('admin/keterlambatan/siswa', 'GET', 'siswa', [], ['q' => mb_substr((string) $siswa['nama'], 0, 6), 'tanggal' => $hari])->getBody(), true);
        $this->cek('cari siswa (JSON) → ok + hasil memuat siswa', ($j['ok'] ?? false) && in_array((int) $siswa['id'], array_column($j['hasil'] ?? [], 'id'), true), json_encode($j));

        $r = $this->panggil('admin/keterlambatan', 'POST', 'store', ['siswa_id' => $siswa['id'], 'tanggal' => $hari, 'jam' => '07:31', 'guru_id' => $guru[0]['id'], 'sanksi' => 'Uji', 'keterangan' => '']);
        $id = (int) ($this->db->table('keterlambatan_siswa')->select('id')->where(['siswa_id' => $siswa['id'], 'tanggal' => $hari])->get()->getRowArray()['id'] ?? 0);
        if ($id > 0) {
            $this->idUji[] = $id;
        }
        $this->cek('simpan → dialihkan ke daftar + pesan sukses; guru piket diingat hari ini', $r instanceof RedirectResponse && $id > 0 && str_contains((string) session('success'), 'dicatat terlambat')
            && (session('keterlambatan_guru')['id'] ?? 0) === (int) $guru[0]['id'], (string) session('error'));
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index', [], [], false);
        $this->cek('… daftar memuat siswa itu & guru piket terpilih otomatis di formulir', str_contains($html, esc((string) $siswa['nama'])) && str_contains($html, '<option value="' . $guru[0]['id'] . '" selected>'));

        $r = $this->panggil('admin/keterlambatan', 'POST', 'store', ['siswa_id' => $siswa['id'], 'tanggal' => $hari, 'guru_id' => $guru[0]['id']]);
        $this->cek('simpan ganda → pesan galat "sudah tercatat"', $r instanceof RedirectResponse && str_contains((string) session('error'), 'sudah tercatat'));
        $r = $this->panggil('admin/keterlambatan/' . $id, 'POST', 'update', ['jam' => '07:45', 'guru_id' => $guru[0]['id'], 'sanksi' => 'Uji ubah', 'keterangan' => 'x'], [], true, $id);
        $this->cek('ubah lewat dialog → tersimpan', str_contains((string) session('success'), 'diperbarui') && ($buku->cari($id)['jam'] ?? '') === '07:45:00');
        $this->panggil('admin/keterlambatan/delete/' . $id, 'POST', 'delete', [], [], true, $id);
        $this->cek('hapus → catatan hilang', $buku->cari($id) === null && str_contains((string) session('success'), 'dihapus'));
    }

    // =================================================================
    // 5. API aplikasi Android (api/v1/keterlambatan)
    // =================================================================

    private function ujiApi(): void
    {
        $this->bagian('API aplikasi: api/v1/keterlambatan');
        $this->cek('gerbang: Guru Piket boleh aplikasi, hanya awalan keterlambatan (+ rekap); Operator tidak', HakPeran::bolehApi('guru_piket')
            && HakPeran::bolehApiAlamat('guru_piket', 'keterlambatan/5/kabari') && HakPeran::bolehApiAlamat('guru_piket', 'keterlambatan/rekap/buku')
            && ! HakPeran::bolehApiAlamat('guru_piket', 'surat') && ! HakPeran::bolehApiAlamat('guru_piket', 'admin/master/siswa') && ! HakPeran::bolehApiAlamat('operator', 'keterlambatan'));

        $hari  = date('Y-m-d');
        $siswa = $this->db->table('siswa s')->select('s.id, s.nama, s.ortu_telepon, s.no_hp_wali, k.nama_kelas')->join('kelas k', 'k.id = s.kelas_id')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')->orderBy('s.id', 'DESC')->limit(1, 1)->get()->getRowArray();
        $guru  = (new Keterlambatan())->pilihanGuru();
        if (! $siswa || $guru === [] || $this->db->table('keterlambatan_siswa')->where(['siswa_id' => $siswa['id'], 'tanggal' => $hari])->countAllResults() > 0) {
            $this->cek('data uji API tersedia', false);

            return;
        }
        $this->hpAsli[(int) $siswa['id']] ??= ['ortu_telepon' => $siswa['ortu_telepon'], 'no_hp_wali' => $siswa['no_hp_wali']];
        $this->db->table('siswa')->where('id', $siswa['id'])->update(['ortu_telepon' => null, 'no_hp_wali' => null]);

        [$k, $j] = $this->api('guru_piket', 'meta');
        $this->cek('GET meta (Guru Piket): hak catat/kabari/rekap ya, ubah pesan tidak; guru, kelas, penanda WA', $k === 200 && $j['data']['hak']['catat'] && $j['data']['hak']['kabari'] && $j['data']['hak']['rekap']
            && ! $j['data']['hak']['ubah_pesan'] && $j['data']['guru'] !== [] && $j['data']['kelas'] !== [] && in_array('{waktu}', $j['data']['wa']['penanda'], true) && $j['data']['tanggal'] === $hari, json_encode($j['data']['hak'] ?? $j));
        [, $j] = $this->api('admin', 'meta');
        $this->cek('GET meta (Admin): boleh ubah pesan', $j['data']['hak']['ubah_pesan'] ?? false);
        [$k] = $this->api('operator', 'daftar');
        $this->cek('Operator memanggil daftar → 403', $k === 403);

        [$k, $j] = $this->api('guru_piket', 'siswa', ['q' => mb_substr((string) $siswa['nama'], 0, 6), 'tanggal' => $hari]);
        $this->cek('GET siswa?q= → siswa ditemukan', $k === 200 && in_array((int) $siswa['id'], array_column($j['data'], 'id'), true));

        [$k, $j] = $this->api('guru_piket', 'catat', [], ['siswa_id' => $siswa['id'], 'guru_id' => $guru[0]['id'], 'jam' => '07:12', 'sanksi' => 'Uji API']);
        $id = (int) ($j['data']['id'] ?? 0);
        if ($id > 0) {
            $this->idUji[] = $id;
        }
        $b = $j['data'] ?? [];
        $this->cek('POST catat (tanggal kosong = hari ini) → 201 + baris lengkap (kelas, guru piket, wa: belum ada nomor, bisa isi)', $k === 201 && $id > 0 && $b['tanggal'] === $hari && $b['jam'] === '07:12'
            && $b['nama_kelas'] === $siswa['nama_kelas'] && $b['guru_piket'] === $guru[0]['nama'] && $b['wa']['url'] === null && $b['wa']['bisa_isi_hp'] === true, json_encode($j));
        [$k] = $this->api('guru_piket', 'catat', [], ['siswa_id' => $siswa['id'], 'guru_id' => $guru[0]['id']]);
        [$k2] = $this->api('guru_piket', 'catat', [], ['siswa_id' => $siswa['id'], 'tanggal' => date('Y-m-d', strtotime('-1 day'))]);
        [$k3] = $this->api('guru_piket', 'catat', [], ['siswa_id' => $siswa['id'], 'guru_id' => $guru[0]['id'], 'tanggal' => date('Y-m-d', strtotime('+1 day'))]);
        $this->cek('catat ganda → 409; tanpa guru piket → 422; tanggal besok → 422', $k === 409 && $k2 === 422 && $k3 === 422, json_encode([$k, $k2, $k3]));

        [$k, $j] = $this->api('guru_piket', 'daftar', ['tanggal' => $hari]);
        $this->cek('GET daftar → baris memuat catatan itu + jumlah', $k === 200 && in_array($id, array_column($j['data']['baris'], 'id'), true) && $j['data']['jumlah'] === count($j['data']['baris']));
        [$k, $j] = $this->api('guru_piket', 'ubah', [], ['guru_id' => $guru[0]['id'], 'jam' => '07:40', 'sanksi' => 'Uji ubah API'], $id);
        $this->cek('POST {id}/ubah → 200, jam & sanksi baru', $k === 200 && $j['data']['jam'] === '07:40' && $j['data']['sanksi'] === 'Uji ubah API');
        [$k] = $this->api('guru_piket', 'ubah', [], ['jam' => '07:40'], $id);
        [$k2] = $this->api('guru_piket', 'detail', [], [], 99999999);
        $this->cek('ubah tanpa guru → 422; detail id tak ada → 404', $k === 422 && $k2 === 404);

        [$k, $j] = $this->api('guru_piket', 'isiHp', [], ['hp' => '0812 abc'], $id);
        [$k2, $j2] = $this->api('guru_piket', 'isiHp', [], ['hp' => '0813-4444-1212'], $id);
        $this->cek('POST {id}/hp: nomor salah → 422; HP sah → 200 + baris.wa.url wa.me/62…', $k === 422 && $k2 === 200 && str_starts_with((string) ($j2['data']['wa']['url'] ?? ''), 'https://wa.me/6281344441212?text='), json_encode($j2['data']['wa'] ?? $j2));
        [$k, $j] = $this->api('guru_piket', 'kabari', [], [], $id);
        [, $jd] = $this->api('guru_piket', 'detail', [], [], $id);
        $this->cek('POST {id}/kabari → 200; detail menunjukkan dikabari_at & oleh', $k === 200 && ! empty($j['data']['dikabari_at']) && ($jd['data']['dikabari_oleh'] ?? '') === 'ZZUJI Aplikasi guru_piket');

        [$k] = $this->api('guru_piket', 'pesan', [], ['wa_pesan' => 'X {nama}']);
        $this->templatAsli     = $this->templatDisimpan ? $this->templatAsli : (((new \App\Models\SettingModel())->get()['wa_pesan_terlambat'] ?? null) ?: null);
        $this->templatDisimpan = true;
        [$k2, $j2] = $this->api('admin', 'pesan', [], ['wa_pesan' => 'Uji API {nama} {waktu}']);
        [$k3] = $this->api('admin', 'pesan', [], ['wa_pesan' => 'Salah {abc}']);
        $this->cek('POST pesan: Guru Piket → 403; Admin → 200 tersimpan; penanda asing → 422', $k === 403 && $k2 === 200 && ($j2['data']['templat'] ?? '') === 'Uji API {nama} {waktu}' && $k3 === 422);
        KeterlambatanWa::simpanTemplat('');

        $res = $this->api('guru_piket', 'excel', ['tanggal' => $hari], [], false);
        $this->cek('GET excel → berkas .xlsx buku piket', $res instanceof \CodeIgniter\HTTP\DownloadResponse && str_starts_with($this->isiUnduhan($res), 'PK'));
        [$k, $j] = $this->api('guru_piket', 'rekap', ['mulai' => $hari, 'selesai' => $hari]);
        $this->cek('GET rekap → ringkas & per siswa memuat siswa itu', $k === 200 && $j['data']['ringkas']['total'] >= 1 && in_array($siswa['nama'], array_column($j['data']['siswa'], 'nama'), true));
        $res = $this->api('guru_piket', 'rekapExcel', ['mulai' => $hari, 'selesai' => $hari], [], false);
        $res2 = $this->api('guru_piket', 'rekapBuku', ['mulai' => $hari, 'selesai' => $hari], [], false);
        [$k] = $this->api('guru_piket', 'rekapBuku', ['mulai' => '2020-01-01', 'selesai' => '2020-01-31']);
        $this->cek('GET rekap/excel & rekap/buku → .xlsx; rentang kosong → 404', str_starts_with($this->isiUnduhan($res), 'PK') && str_starts_with($this->isiUnduhan($res2), 'PK') && $k === 404);

        [$k] = $this->api('guru_piket', 'hapus', [], [], $id);
        [$k2] = $this->api('guru_piket', 'detail', [], [], $id);
        $this->cek('DELETE {id} → 200, lalu detail → 404', $k === 200 && $k2 === 404);
        $this->cek('Audit Log mencatat "(via aplikasi)"', $this->db->table('audit_log')->where('tabel', 'keterlambatan_siswa')->where('record_id', $id)->like('deskripsi', '(via aplikasi)')->countAllResults() >= 3);
    }

    // =================================================================
    // 6. Siswa BELUM TERDAFTAR (dicatat manual) + tautkan ke Data Siswa — pustaka, WA, halaman, API
    // =================================================================

    private function ujiManual(): void
    {
        $this->bagian('Siswa belum terdaftar (manual) & tautkan');
        if (! $this->db->fieldExists('manual', 'keterlambatan_siswa')) {
            $this->cek('kolom manual & hp_ortu ada (php spark migrate)', false);

            return;
        }
        $buku  = new Keterlambatan();
        $hari  = date('Y-m-d');
        $kmrn  = date('Y-m-d', strtotime('-1 day'));
        $lusa  = date('Y-m-d', strtotime('-2 day'));
        $saja  = ['id' => null, 'nama' => 'ZZUJI Guru Piket'];
        $guru  = $buku->pilihanGuru();
        $kelas = $buku->pilihanKelas();
        // Siswa sasaran Tautkan: siswa aktif berkelas tanpa catatan apa pun pada 3 hari uji (agar data asli tak tersentuh).
        $s = null;
        foreach ($this->db->table('siswa s')->select('s.id, s.nama, s.nis, s.kelas_id, s.ortu_telepon, s.no_hp_wali, k.nama_kelas')->join('kelas k', 'k.id = s.kelas_id')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')->orderBy('s.id', 'ASC')->limit(10, 20)->get()->getResultArray() as $c) {
            if ($this->db->table('keterlambatan_siswa')->where('siswa_id', $c['id'])->whereIn('tanggal', [$hari, $kmrn, $lusa])->countAllResults() === 0) {
                $s = $c;
                break;
            }
        }
        if ($s === null || count($guru) < 1 || count($kelas) < 2) {
            $this->cek('data uji manual tersedia (siswa sasaran, guru, 2 kelas)', false);

            return;
        }
        $g      = $guru[0];
        $kA     = (int) $s['kelas_id'];
        $kB     = (int) (array_values(array_filter($kelas, static fn (array $k): bool => $k['id'] !== $kA))[0]['id']);
        $this->hpAsli[(int) $s['id']] ??= ['ortu_telepon' => $s['ortu_telepon'], 'no_hp_wali' => $s['no_hp_wali']];
        $this->db->table('siswa')->where('id', $s['id'])->update(['ortu_telepon' => null, 'no_hp_wali' => null]);
        $dasar = ['tanggal' => $hari, 'guru_id' => $g['id'], 'manual' => '1', 'kelas_id' => $kA];

        // --- catat manual ---
        $this->ingat($r1 = $buku->catat(['nama_siswa' => '  zzuji   budi santoso ', 'jam' => '07:10', 'hp_ortu' => '0812-1111-0000', 'siswa_id' => $s['id']] + $dasar, $saja));
        $row = $r1['ok'] ? $buku->cari($r1['id']) : null;
        $this->cek('catat manual → HURUF BESAR & spasi rapi, manual=1, siswa_id kosong (siswa_id kiriman diabaikan), kelas & shift dari daftar kelas, HP 08…', $row !== null
            && $row['nama_siswa'] === 'ZZUJI BUDI SANTOSO' && (int) $row['manual'] === 1 && $row['siswa_id'] === null && (int) $row['kelas_id'] === $kA
            && $row['nama_kelas'] === $s['nama_kelas'] && $row['hp_ortu'] === '081211110000' && $row['tahun_ajaran'] === Keterlambatan::tahunAjaran() && str_contains($r1['pesan'], 'belum terdaftar'), json_encode([$r1, $row]));
        $tolak = [
            'nama 2 huruf'       => [['nama_siswa' => 'ZZ'] + $dasar, 'nama_siswa'],
            'nama berangka'      => [['nama_siswa' => 'ZZUJI 123'] + $dasar, 'nama_siswa'],
            'tanpa kelas'        => [['nama_siswa' => 'ZZUJI ANI', 'kelas_id' => 0] + $dasar, 'kelas_id'],
            'kelas tak ada'      => [['nama_siswa' => 'ZZUJI ANI', 'kelas_id' => 99999999] + $dasar, 'kelas_id'],
            'HP berhuruf'        => [['nama_siswa' => 'ZZUJI ANI', 'hp_ortu' => '0812abc'] + $dasar, 'hp_ortu'],
            'HP telepon rumah'   => [['nama_siswa' => 'ZZUJI ANI', 'hp_ortu' => '021 555 1234'] + $dasar, 'hp_ortu'],
        ];
        $salah = [];
        foreach ($tolak as $judul => [$d, $kolom]) {
            $r = $buku->catat($d, $saja);
            $this->ingat($r);
            if ($r['ok'] || ($r['kode'] ?? 0) !== 422 || ! isset($r['errors'][$kolom])) {
                $salah[] = $judul;
            }
        }
        $r = $buku->catat(['nama_siswa' => 'ZZUJI ANI', 'guru_id' => 0] + $dasar, $saja);
        $this->ingat($r);
        $this->cek('isian manual tidak sah → 422 + kolomnya: ' . implode(', ', array_keys($tolak)) . '; tanpa guru piket juga ditolak', $salah === [] && ! $r['ok'], 'lolos/salah: ' . implode(', ', $salah));
        $r  = $buku->catat(['nama_siswa' => 'Zzuji Budi Santoso', 'jam' => '07:30'] + $dasar, $saja);
        $this->ingat($r);
        $this->ingat($r2 = $buku->catat(['nama_siswa' => 'ZZUJI BUDI SANTOSO', 'kelas_id' => $kB] + $dasar, $saja));
        $this->cek('nama + kelas sama, hari sama (beda huruf besar/kecil) → 409 "sudah tercatat"; nama sama di kelas lain → boleh', ! $r['ok'] && $r['kode'] === 409
            && str_contains($r['pesan'], 'sudah tercatat') && $r2['ok'], json_encode([$r, $r2]));
        $r = $buku->catat(['nama_siswa' => mb_strtolower((string) $s['nama'])] + $dasar, $saja);
        $this->ingat($r);
        $this->cek('nama = siswa aktif TERDAFTAR di kelas yang sama → 422 + siswa_terdaftar (harus dipilih dari pencarian)', ! $r['ok'] && $r['kode'] === 422
            && (int) ($r['siswa_terdaftar']['id'] ?? 0) === (int) $s['id'] && str_contains($r['pesan'], 'sudah terdaftar'), json_encode($r));

        // --- cari ---
        $h  = $buku->cariSiswa('zzuji budi', $hari, 15, true);
        $m  = array_values(array_filter($h, static fn (array $x): bool => $x['manual'] && (int) $x['kelas_id'] === $kA))[0] ?? [];
        $h0 = $buku->cariSiswa('zzuji budi', $hari);
        $hs = $buku->cariSiswa((string) $s['nis'], $hari, 15, true);
        $this->cek('cari + manual: saran siswa belum terdaftar (id null, manual, kelas, jumlah, HP terakhir, tercatat hari ini); tanpa manual → tidak ada; siswa terdaftar membawa manual=false & kelas_id', $m !== []
            && $m['id'] === null && $m['nama'] === 'ZZUJI BUDI SANTOSO' && $m['kelas'] === $s['nama_kelas'] && $m['jumlah'] === 1 && $m['hp_ortu'] === '081211110000'
            && $m['tercatat'] === 'jam 07:10' && $h0 === [] && ($hs[0]['manual'] ?? null) === false && ($hs[0]['kelas_id'] ?? 0) === $kA, json_encode([$h, $hs[0] ?? null]));

        // --- WA & isi nomor ---
        $W  = KeterlambatanWa::class;
        $k1 = $W::kabar($buku->cariLengkap($r1['id']), $W::PESAN_BAWAAN, 'X');
        $k2 = $W::kabar($buku->cariLengkap($r2['id']), $W::PESAN_BAWAAN, 'X');
        $this->cek('WA siswa manual: nomor dari catatan (sumber "isian guru piket"); tanpa nomor → "belum ada" & bisa isi', str_contains((string) $k1['url'], 'wa.me/6281211110000')
            && $k1['sumber'] === $W::SUMBER_ISIAN && str_contains($k1['pesan'], 'Zzuji Budi Santoso') && $k2['url'] === null && $k2['alasan_tidak'] === 'Nomor HP orang tua belum ada'
            && $W::bisaIsiHp($buku->cari($r2['id'])), json_encode([$k1, $k2]));
        $wa = new KeterlambatanWa();
        $a  = $wa->isiHp($r2['id'], '0812 xx');
        $b  = $wa->isiHp($r2['id'], '0857-2222-3333');
        $c  = $wa->isiHp($r2['id'], '0857 4444 5555');
        $this->cek('isi nomor siswa manual: salah → 422; sah → disimpan di CATATAN (bukan Data Siswa); isian kedua → 409 (ganti lewat Ubah)', ! $a['ok'] && $b['ok']
            && $buku->cari($r2['id'])['hp_ortu'] === '085722223333' && str_contains($b['pesan'], 'belum terdaftar') && ! $c['ok'] && $c['kode'] === 409, json_encode([$a, $b, $c]));

        // --- ubah ---
        $u   = $buku->ubah($r2['id'], ['guru_id' => $g['id'], 'jam' => '07:45', 'nama_siswa' => 'zzuji budi santosa', 'kelas_id' => $kA, 'hp_ortu' => '']);
        $row = $buku->cari($r2['id']);
        $this->cek('ubah manual: nama dibetulkan (huruf besar), kelas pindah, HP dikosongkan, jam berubah', $u['ok'] && $row['nama_siswa'] === 'ZZUJI BUDI SANTOSA'
            && (int) $row['kelas_id'] === $kA && $row['nama_kelas'] === $s['nama_kelas'] && $row['hp_ortu'] === null && $row['jam'] === '07:45:00', json_encode([$u, $row]));
        $u1 = $buku->ubah($r2['id'], ['guru_id' => $g['id'], 'nama_siswa' => 'ZZUJI BUDI SANTOSO']);
        $u2 = $buku->ubah($r2['id'], ['guru_id' => $g['id'], 'nama_siswa' => (string) $s['nama']]);
        $u3 = $buku->ubah($r2['id'], ['guru_id' => $g['id'], 'sanksi' => 'Push up']);
        $this->cek('ubah manual: jadi kembar catatan lain hari itu → 409; jadi nama siswa terdaftar → 422 "Tautkan"; tanpa kunci nama/kelas → nama & kelas tetap', ! $u1['ok'] && $u1['kode'] === 409
            && ! $u2['ok'] && $u2['kode'] === 422 && str_contains($u2['pesan'], 'Tautkan') && $u3['ok'] && $buku->cari($r2['id'])['nama_siswa'] === 'ZZUJI BUDI SANTOSA', json_encode([$u1, $u2, $u3]));
        $biasa = $this->siswaUji[0] ?? null;
        $idB   = $biasa ? (int) ($this->db->table('keterlambatan_siswa')->select('id')->where(['siswa_id' => $biasa['id'], 'tanggal' => $hari])->get()->getRowArray()['id'] ?? 0) : 0;
        if ($idB > 0) {
            $buku->ubah($idB, ['guru_id' => $g['id'], 'nama_siswa' => 'ZZUJI PALSU', 'kelas_id' => $kB, 'hp_ortu' => '081200000001']);
            $rb = $buku->cari($idB);
            $this->cek('ubah catatan siswa TERDAFTAR: nama/kelas/HP kiriman diabaikan', $rb['nama_siswa'] === $biasa['nama'] && $rb['hp_ortu'] === null && (int) $rb['manual'] === 0);
        }

        // --- rekap ---
        $rk = $buku->rekap($hari, $hari);
        $mn = array_values(array_filter($rk['siswa'], static fn (array $x): bool => str_starts_with($x['nama'], 'ZZUJI BUDI')));
        $this->cek('rekap: siswa manual bertanda manual=true, dipisah per nama + kelas', count($mn) === 2 && $mn[0]['manual'] && $mn[1]['manual'] && $mn[0]['nis'] === '', json_encode($mn));
        $sx = $this->baca(KeterlambatanExcel::rekap($rk, $hari, $hari, ''));
        $col = array_column($sx->getSheet(0)->rangeToArray('B6:C' . (5 + count($rk['siswa'])), null, false, false), 1, 0);
        $this->cek('Excel rekap: kolom NIS siswa manual = "belum terdaftar"', ($col['ZZUJI BUDI SANTOSO'] ?? '') === 'belum terdaftar', json_encode($col));

        // --- tautkan ---
        $this->ingat($t1 = $buku->catat(['nama_siswa' => 'ZZUJI CALON SISWA', 'tanggal' => $lusa, 'hp_ortu' => '081299990001'] + $dasar, $saja));
        $this->ingat($t2 = $buku->catat(['nama_siswa' => 'ZZUJI CALON SISWA', 'tanggal' => $kmrn] + $dasar, $saja));
        $this->ingat($t3 = $buku->catat(['nama_siswa' => 'ZZUJI CALON SISWA', 'tanggal' => $hari] + $dasar, $saja));
        $this->ingat($tB = $buku->catat(['nama_siswa' => 'ZZUJI CALON SISWA', 'tanggal' => $kmrn, 'kelas_id' => $kB] + $dasar, $saja));
        $this->ingat($sr = $buku->catat(['siswa_id' => $s['id'], 'tanggal' => $hari, 'guru_id' => $g['id']], $saja)); // siswa asli sudah tercatat hari ini
        $x1 = $buku->tautkan($t1['id'], 0);
        $x2 = $buku->tautkan($sr['id'], (int) $s['id']);
        $x3 = $buku->tautkan($t3['id'], (int) $s['id'], false);
        $this->cek('tautkan: siswa kosong → 422; catatan siswa terdaftar → 422; satu-satunya tanggal bentrok → 409', ! $x1['ok'] && $x1['kode'] === 422 && ! $x2['ok'] && $x2['kode'] === 422
            && ! $x3['ok'] && $x3['kode'] === 409, json_encode([$x1, $x2, $x3]));
        $y   = $buku->tautkan($t1['id'], (int) $s['id']);
        $a1  = $buku->cari($t1['id']);
        $a3  = $buku->cari($t3['id']);
        $aB  = $buku->cari($tB['id']);
        $hpS = $this->db->table('siswa')->select('ortu_telepon')->where('id', $s['id'])->get()->getRowArray()['ortu_telepon'];
        $this->cek('tautkan semua: 2 catatan (lusa, kemarin) jadi milik siswa (nama & kelas Data Siswa, manual=0); hari ini dilewati (bentrok); kelas lain tak ikut; HP catatan → Data Siswa yang kosong', $y['ok']
            && $y['jumlah'] === 2 && $y['dilewati'] === [$hari] && (int) $a1['siswa_id'] === (int) $s['id'] && (int) $a1['manual'] === 0 && $a1['nama_siswa'] === $s['nama']
            && (int) $buku->cari($t2['id'])['siswa_id'] === (int) $s['id'] && $a3['siswa_id'] === null && (int) $a3['manual'] === 1 && $aB['siswa_id'] === null
            && $hpS === '081299990001' && str_contains($y['pesan'], 'disimpan ke Data Siswa'), json_encode([$y, $a1, $hpS]));
        $this->cek('setelah ditautkan: rekap siswa itu menyatu (lusa + kemarin + hari ini = 3×), WA memakai Data Siswa', (array_values(array_filter($buku->rekap($lusa, $hari)['siswa'],
            static fn (array $x): bool => $x['nama'] === $s['nama']))[0]['jumlah'] ?? 0) === 3 && ($W::kabar($buku->cariLengkap($t1['id']), $W::PESAN_BAWAAN, 'X')['sumber'] ?? '') === 'orang tua');
        $this->cek('Audit Log: catat manual & tautkan tercatat', $this->db->table('audit_log')->where('tabel', 'keterlambatan_siswa')->whereIn('record_id', [$r1['id'], $t1['id']])
            ->groupStart()->like('deskripsi', 'belum terdaftar')->orLike('deskripsi', 'Tautkan')->groupEnd()->countAllResults() >= 3);

        // --- hak & halaman web ---
        $this->cek('hak: Guru Piket boleh tautkan (Kelola); Operator tidak; alamat milik menu Catat Keterlambatan', HakPeran::boleh('guru_piket', 'admin/keterlambatan/5/tautkan', 'POST')
            && ! HakPeran::boleh('operator', 'admin/keterlambatan/5/tautkan', 'POST') && (MenuAdmin::cariAlamat('admin/keterlambatan/5/tautkan')['kunci'] ?? '') === 'keterlambatan');
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index');
        $cekHal = [
            'tombol tulis manual' => str_contains($html, 'Tulis manual') && str_contains($html, 'id="tl-m-nama"') && str_contains($html, 'name="manual"'),
            'pilihan kelas'       => str_contains($html, 'id="tl-m-kelas"') && str_contains($html, '<option value="' . $kB . '">'),
            'lencana'             => str_contains($html, 'Belum terdaftar'),
            'tombol Tautkan'      => str_contains($html, 'data-tautkan="' . esc(site_url('admin/keterlambatan/' . $r1['id'] . '/tautkan'), 'attr') . '"'),
            'dialog Tautkan'      => str_contains($html, 'x-data="tautkanSiswa"'),
            'WA siswa manual'     => str_contains($html, esc('wa.me/6281211110000', 'attr')),
            'isi no. manual'      => str_contains($html, 'data-isi-manual="1"') || $buku->cari($r2['id'])['hp_ortu'] !== null,
        ];
        $this->cek('halaman: tombol & isian Tulis manual, lencana Belum terdaftar, tombol + dialog Tautkan, WA dari nomor catatan', ! in_array(false, $cekHal, true), json_encode($cekHal));
        $j  = json_decode((string) $this->panggil('admin/keterlambatan/siswa', 'GET', 'siswa', [], ['q' => 'ZZUJI BUDI', 'tanggal' => $hari])->getBody(), true);
        $j0 = json_decode((string) $this->panggil('admin/keterlambatan/siswa', 'GET', 'siswa', [], ['q' => 'ZZUJI BUDI', 'tanggal' => $hari, 'manual' => '0'])->getBody(), true);
        $this->cek('cari (JSON halaman): bawaan memuat saran manual; manual=0 (dialog Tautkan) tidak', in_array(true, array_column($j['hasil'] ?? [], 'manual'), true) && ($j0['hasil'] ?? null) === []);
        $this->panggil('admin/keterlambatan', 'POST', 'store', ['manual' => '1', 'nama_siswa' => 'zzuji cici', 'kelas_id' => $kB, 'hp_ortu' => '', 'tanggal' => $hari, 'jam' => '07:05', 'guru_id' => $g['id'], 'siswa_id' => '', 'sanksi' => '', 'keterangan' => '']);
        $idC = (int) ($this->db->table('keterlambatan_siswa')->select('id')->where(['nama_siswa' => 'ZZUJI CICI', 'tanggal' => $hari])->get()->getRowArray()['id'] ?? 0);
        $this->idUji[] = $idC;
        $this->cek('simpan manual lewat halaman → pesan sukses "belum terdaftar"', $idC > 0 && str_contains((string) session('success'), 'belum terdaftar'), (string) session('error'));
        $this->panggil('admin/keterlambatan/' . $idC, 'POST', 'update', ['jam' => '07:06', 'guru_id' => $g['id'], 'sanksi' => '', 'keterangan' => '', 'nama_siswa' => 'ZZUJI CICI AMELIA', 'kelas_id' => $kB, 'hp_ortu' => '0813 5555 6666'], [], true, $idC);
        $rc = $buku->cari($idC);
        $this->cek('ubah manual lewat dialog halaman → nama & HP tersimpan', $rc['nama_siswa'] === 'ZZUJI CICI AMELIA' && $rc['hp_ortu'] === '081355556666', json_encode($rc));
        $this->panggil('admin/keterlambatan/' . $idC . '/tautkan', 'POST', 'tautkan', ['siswa_id' => $s['id'], 'semua' => '1'], [], true, $idC);
        $this->cek('tautkan lewat halaman: siswa sudah tercatat hari ini → pesan galat 409', str_contains((string) session('error'), 'sudah punya catatan'), (string) session('error') . (string) session('success'));

        // --- API ---
        [$k, $j] = $this->api('guru_piket', 'meta');
        $this->cek('API meta: hak.tautkan & batas nama', $k === 200 && ($j['data']['hak']['tautkan'] ?? false) && ($j['data']['batas']['nama'] ?? 0) === Keterlambatan::MAKS_NAMA && ($j['data']['batas']['nama_min'] ?? 0) === Keterlambatan::MIN_NAMA);
        [$k, $j]   = $this->api('guru_piket', 'siswa', ['q' => 'ZZUJI CICI', 'tanggal' => $hari, 'manual' => '1']);
        [$k2, $j2] = $this->api('guru_piket', 'siswa', ['q' => 'ZZUJI CICI', 'tanggal' => $hari]);
        $this->cek('API cari: manual=1 → saran (id null, manual true, tercatat); tanpa manual → kosong (aplikasi lama aman)', $k === 200 && array_key_exists('id', $j['data'][0] ?? []) && $j['data'][0]['id'] === null
            && ($j['data'][0]['manual'] ?? false) === true && ($j['data'][0]['tercatat'] ?? '') === 'jam 07:06' && $k2 === 200 && $j2['data'] === [], json_encode([$j['data'] ?? $j, $j2['data'] ?? null]));
        [$k, $j] = $this->api('guru_piket', 'catat', [], ['manual' => true, 'nama_siswa' => 'zzuji dodi', 'kelas_id' => $kB, 'hp_ortu' => '0812 7777 1111', 'guru_id' => $g['id'], 'jam' => '07:20']);
        $idD = (int) ($j['data']['id'] ?? 0);
        $this->idUji[] = $idD;
        $b = $j['data'] ?? [];
        $this->cek('API catat manual → 201: manual true, siswa_id & nis null, hp_ortu, wa.sumber "isian guru piket", bisa_isi_hp false', $k === 201 && $idD > 0 && $b['manual'] === true
            && $b['siswa_id'] === null && $b['nis'] === null && $b['nama_siswa'] === 'ZZUJI DODI' && $b['hp_ortu'] === '081277771111' && $b['wa']['sumber'] === KeterlambatanWa::SUMBER_ISIAN
            && $b['wa']['bisa_isi_hp'] === false, json_encode($j));
        [$k, $j]   = $this->api('guru_piket', 'catat', [], ['manual' => '1', 'nama_siswa' => (string) $s['nama'], 'kelas_id' => $kA, 'guru_id' => $g['id'], 'tanggal' => $kmrn]);
        [$k2, $j2] = $this->api('guru_piket', 'catat', [], ['manual' => '1', 'nama_siswa' => 'ZZUJI DODI', 'kelas_id' => $kB, 'guru_id' => $g['id']]);
        [$k3, $j3] = $this->api('guru_piket', 'catat', [], ['manual' => '1', 'nama_siswa' => 'ZZUJI EKA', 'guru_id' => $g['id']]);
        $this->cek('API catat manual: nama siswa terdaftar → 422 + data.siswa_terdaftar; kembar → 409; tanpa kelas → 422 errors.kelas_id', $k === 422 && (int) ($j['data']['siswa_terdaftar']['id'] ?? 0) === (int) $s['id']
            && $k2 === 409 && $k3 === 422 && isset($j3['data']['errors']['kelas_id']), json_encode([$j, $k2, $j3]));
        [$k, $j]   = $this->api('guru_piket', 'ubah', [], ['guru_id' => $g['id'], 'jam' => '07:21', 'nama_siswa' => 'Zzuji Dodi Pratama'], $idD);
        [$k2, $j2] = $this->api('guru_piket', 'ubah', [], ['guru_id' => $g['id'], 'jam' => '07:22'], $idD);
        $this->cek('API ubah manual: nama_siswa dikirim → berubah (HP tetap); tidak dikirim → tetap', $k === 200 && $j['data']['nama_siswa'] === 'ZZUJI DODI PRATAMA' && $j['data']['hp_ortu'] === '081277771111'
            && $k2 === 200 && $j2['data']['nama_siswa'] === 'ZZUJI DODI PRATAMA' && $j2['data']['jam'] === '07:22', json_encode([$j, $j2]));
        [$k, $j]   = $this->api('guru_piket', 'tautkan', [], ['siswa_id' => $s['id'], 'semua' => '0'], $idD);
        [$k2, $j2] = $this->api('guru_piket', 'tautkan', [], ['siswa_id' => 0], $t3['id']);
        $this->ingat($e1 = $buku->catat(['nama_siswa' => 'ZZUJI CALON SISWA', 'tanggal' => date('Y-m-d', strtotime('-3 day'))] + $dasar, $saja));
        [$k3, $j3] = $this->api('guru_piket', 'tautkan', [], ['siswa_id' => $s['id']], $e1['id']);
        [$k4]      = $this->api('operator', 'tautkan', [], ['siswa_id' => $s['id']], $e1['id']);
        $this->cek('API tautkan: bentrok → 409; siswa kosong → 422; sah → 200 {jumlah 1, dilewati [hari ini], baris.manual false, siswa_id}; Operator → 403', $k === 409 && $k2 === 422
            && $k3 === 200 && $j3['data']['jumlah'] === 1 && $j3['data']['dilewati'] === [$hari] && $j3['data']['baris']['manual'] === false
            && $j3['data']['baris']['siswa_id'] === (int) $s['id'] && $j3['data']['baris']['nama_siswa'] === $s['nama'] && $k4 === 403, json_encode([$j, $j2, $j3]));
    }

    // =================================================================
    // 7. "Pilih dari kelas" (tekan kelas → semua siswanya), ubah API tanpa kunci, Excel hari Selasa
    // =================================================================

    private function ujiPilihKelas(): void
    {
        $this->bagian('Pilih dari kelas, ubah lewat aplikasi, Excel hari Selasa');
        $buku = new Keterlambatan();
        $hari = date('Y-m-d');
        $guru = $buku->pilihanGuru();
        $s    = $this->db->query("SELECT s.id, s.nama, s.kelas_id, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.kelas_id AND k.deleted_at IS NULL
            WHERE s.deleted_at IS NULL AND s.status = 'aktif' AND NOT EXISTS (SELECT 1 FROM keterlambatan_siswa t WHERE t.siswa_id = s.id AND t.tanggal = ?)
            ORDER BY s.id DESC LIMIT 1 OFFSET 5", [$hari])->getRowArray();
        if (! $s || $guru === []) {
            $this->cek('data uji pilih kelas tersedia', false);

            return;
        }
        $kelasId = (int) $s['kelas_id'];
        $jml     = $this->db->table('siswa')->where('kelas_id', $kelasId)->where('deleted_at', null)->where('status', 'aktif')->countAllResults();

        $kelas = $buku->pilihanKelas();
        $urut  = true;
        for ($i = 1; $i < count($kelas); $i++) {
            $urut = $urut && (strcmp($kelas[$i - 1]['tingkat'], $kelas[$i]['tingkat']) ?: strnatcasecmp($kelas[$i - 1]['nama'], $kelas[$i]['nama'])) < 0;
        }
        $kk = $this->dari($kelas, $kelasId);
        $this->cek('pilihanKelas: urut tingkat lalu nama alami (X TKJ 2 sebelum X TKJ 10) + tingkat & jumlah siswa aktif', $urut && ($kk['jumlah_siswa'] ?? -1) === $jml && ($kk['tingkat'] ?? null) !== null,
            json_encode(array_slice(array_column($kelas, 'nama'), 0, 12)));

        $r  = $buku->siswaKelas($kelasId, $hari);
        $ds = $this->dari($r['hasil'] ?? [], $s['id']);
        $this->cek('siswaKelas: SEMUA siswa aktif kelas ' . $s['nama_kelas'] . ' (' . $jml . '), berkelas sama, bentuk sama dengan cari', $r !== null && count($r['hasil']) === $jml
            && $r['kelas']['nama'] === $s['nama_kelas'] && array_unique(array_column($r['hasil'], 'kelas_id')) === [$kelasId] && $ds !== [] && $ds['tercatat'] === null
            && array_keys($ds) === ['id', 'manual', 'nama', 'nis', 'kelas', 'kelas_id', 'shift', 'tercatat'], json_encode($ds));
        $this->cek('siswaKelas: kelas tidak ada → null', $buku->siswaKelas(99999999, $hari) === null && $buku->siswaKelas(0, $hari) === null);

        $saja = ['id' => null, 'nama' => 'ZZUJI Guru Piket'];
        $this->ingat($c1 = $buku->catat(['siswa_id' => $s['id'], 'tanggal' => $hari, 'jam' => '07:05', 'guru_id' => $guru[0]['id'], 'sanksi' => 'Uji kelas', 'keterangan' => 'Ket kelas'], $saja));
        $this->ingat($c2 = $buku->catat(['manual' => 1, 'nama_siswa' => 'zzuji kelas pilih', 'kelas_id' => $kelasId, 'tanggal' => $hari, 'guru_id' => $guru[0]['id']], $saja));
        // Hitung terpisah: siswa terdaftar (= $jml) & catatan manual (DB bisa punya catatan manual lain di kelas ini).
        $terdaftar = static fn (array $h): int => count(array_filter($h, static fn (array $x): bool => ! $x['manual']));
        $manualUji = static fn (array $h): array => array_values(array_filter($h, static fn (array $x): bool => $x['manual'] && $x['nama'] === 'ZZUJI KELAS PILIH'))[0] ?? [];
        $r  = $buku->siswaKelas($kelasId, $hari, true);
        $r0 = $buku->siswaKelas($kelasId, $hari);
        $m  = $manualUji($r['hasil']);
        $this->cek('siswaKelas: siswa yang sudah dicatat → tercatat "jam 07:05"; + siswa belum terdaftar kelas itu di akhir (hanya bila diminta)', $c1['ok'] && $c2['ok']
            && ($this->dari($r['hasil'], $s['id'])['tercatat'] ?? null) === 'jam 07:05' && ($m['tercatat'] ?? null) === 'sudah dicatat' && $terdaftar($r['hasil']) === $jml
            && end($r['hasil'])['manual'] === true && count($r0['hasil']) === $jml && ! in_array(true, array_column($r0['hasil'], 'manual'), true), json_encode([$c1['pesan'], $c2['pesan'], $m]));

        // --- halaman & JSON web ---
        $html = (string) $this->panggil('admin/keterlambatan', 'GET', 'index');
        $this->cek('halaman catat: tombol kelas di bawah kotak cari', str_contains($html, 'Atau pilih dari kelas') && str_contains($html, 'data-kelas-id="' . $kelasId . '"')
            && strpos($html, 'id="tl-cari"') < strpos($html, 'data-kelas-id="'));
        $res = $this->panggil('admin/keterlambatan/siswa', 'GET', 'siswa', [], ['kelas_id' => $kelasId, 'tanggal' => $hari]);
        $j   = json_decode((string) $res->getBody(), true);
        $res2 = $this->panggil('admin/keterlambatan/siswa', 'GET', 'siswa', [], ['kelas_id' => 99999999, 'tanggal' => $hari]);
        $this->cek('JSON web ?kelas_id= → ok + kelas + semua siswa (+ belum terdaftar); kelas tak ada → 404', ($j['ok'] ?? false) && ($j['kelas']['id'] ?? 0) === $kelasId
            && $terdaftar($j['hasil']) === $jml && $manualUji($j['hasil']) !== [] && $res2->getStatusCode() === 404, json_encode($j['kelas'] ?? $j));

        // --- API ---
        [$k, $j]   = $this->api('guru_piket', 'siswa', ['kelas_id' => $kelasId, 'tanggal' => $hari, 'manual' => '1']);
        [$k2, $j2] = $this->api('guru_piket', 'siswa', ['kelas_id' => $kelasId, 'tanggal' => $hari]);
        [$k3]      = $this->api('guru_piket', 'siswa', ['kelas_id' => 99999999]);
        [$k4]      = $this->api('operator', 'siswa', ['kelas_id' => $kelasId]);
        $this->cek('API GET siswa?kelas_id= → 200 daftar kelas (manual=1 + belum terdaftar), pesan "N siswa aktif di …"; kelas tak ada → 404; Operator → 403', $k === 200
            && $terdaftar($j['data']) === $jml && $manualUji($j['data']) !== [] && ($this->dari($j['data'], $s['id'])['tercatat'] ?? null) === 'jam 07:05' && $j['message'] === $jml . ' siswa aktif di ' . $s['nama_kelas'] . '.'
            && $k2 === 200 && count($j2['data']) === $jml && $k3 === 404 && $k4 === 403, json_encode([$k, $j['message'] ?? null, $k2, $k3, $k4]));
        [$k, $j] = $this->api('guru_piket', 'meta');
        $mk      = $this->dari($j['data']['kelas'] ?? [], $kelasId);
        $this->cek('API meta.kelas[] membawa tingkat & jumlah_siswa', $k === 200 && ($mk['jumlah_siswa'] ?? -1) === $jml && isset($mk['tingkat']), json_encode($mk));

        [$k, $j]   = $this->api('guru_piket', 'ubah', [], ['guru_id' => $guru[0]['id']], (int) $c1['id']);
        [$k2, $j2] = $this->api('guru_piket', 'ubah', [], ['guru_id' => $guru[0]['id'], 'sanksi' => '', 'jam' => '07:09'], (int) $c1['id']);
        $this->cek('API ubah: kunci jam/sanksi/keterangan yang TIDAK dikirim → tetap; "" → dikosongkan', $k === 200 && $j['data']['jam'] === '07:05' && $j['data']['sanksi'] === 'Uji kelas'
            && $j['data']['keterangan'] === 'Ket kelas' && $k2 === 200 && $j2['data']['sanksi'] === null && $j2['data']['jam'] === '07:09' && $j2['data']['keterangan'] === 'Ket kelas', json_encode([$j['data'] ?? $j, $j2['data'] ?? $j2]));

        // --- Excel: hari Selasa ("Selasa, 20-10-2026" 18 huruf) tidak boleh membuat baris dua kali tinggi ---
        $ss = $this->baca(KeterlambatanExcel::bukuPiket([['tanggal' => '2026-10-20', 'guru' => '', 'rows' => [
            ['tanggal' => '2026-10-20', 'nama_siswa' => 'ZZUJI SELASA', 'nama_kelas' => 'X TKJ 1', 'sanksi' => 'Uji', 'keterangan' => '', 'tahun_ajaran' => null],
            ['tanggal' => '2026-10-20', 'nama_siswa' => 'ZZUJI SELASA DUA', 'nama_kelas' => 'X TKJ 1', 'sanksi' => '', 'keterangan' => str_repeat('Hujan deras dan macet ', 3), 'tahun_ajaran' => null],
        ]]]));
        $ws = $ss->getSheet(0);
        $this->cek('Excel hari Selasa: baris setinggi contoh (14), tanggal "perkecil agar muat"; keterangan panjang tetap dilipat', $ws->getCell('B9')->getValue() === 'Selasa, 20-10-2026'
            && $ws->getRowDimension(9)->getRowHeight() == 14 && $ws->getStyle('B9')->getAlignment()->getShrinkToFit() && ! $ws->getStyle('B9')->getAlignment()->getWrapText()
            && $ws->getRowDimension(10)->getRowHeight() > 14 && $ws->getStyle('F10')->getAlignment()->getWrapText(), (string) $ws->getRowDimension(9)->getRowHeight());
    }

    /**
     * Panggil Api\Keterlambatan sebagai peran tertentu (token sudah lolos ApiAuthFilter).
     *
     * @return array{0: int, 1: array}|\CodeIgniter\HTTP\ResponseInterface [kode, JSON] atau respons mentah bila $json = false
     */
    private function api(string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $json = true;
        if ($arg !== [] && $arg[0] === false) {
            $json = false;
            array_shift($arg);
        }
        \App\Libraries\ApiAuth::set(['id' => null, 'full_name' => 'ZZUJI Aplikasi ' . $peran, 'role' => $peran]);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/keterlambatan'), null, new \CodeIgniter\HTTP\UserAgent()))
            ->withMethod($post === [] ? 'GET' : 'POST');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Api\Keterlambatan();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}(...$arg);

        return $json ? [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []] : $r;
    }

    private function isiUnduhan(mixed $res): string
    {
        if (! $res instanceof \CodeIgniter\HTTP\DownloadResponse) {
            return '';
        }
        ob_start();
        $res->sendBody();

        return (string) ob_get_clean();
    }

    /** Panggil Admin\Keterlambatan seperti permintaan sungguhan (peran Guru Piket). $bersih=false: sesi (guru diingat, pesan) tidak dikosongkan. */
    private function panggil(string $alamat, string $http, string $metode, array $post = [], array $get = [], bool $bersih = true, mixed ...$arg): mixed
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), $alamat), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => null, 'full_name' => 'ZZUJI Guru Piket', 'username' => 'zzujitelat', 'role' => $this->peranPanggil]);
        if ($bersih) {
            session()->remove(['_ci_old_input', 'error', 'success']);
        }
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Admin\Keterlambatan();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** @param array{ok: bool, id?: int} $r */
    private function ingat(array $r): void
    {
        if (! empty($r['ok']) && ! empty($r['id'])) {
            $this->idUji[] = (int) $r['id'];
        }
    }

    private function dari(array $hasil, int|string $siswaId): array
    {
        foreach ($hasil as $h) {
            if ((int) $h['id'] === (int) $siswaId) {
                return $h;
            }
        }

        return [];
    }

    private function dariId(array $rows, int $id): array
    {
        foreach ($rows as $r) {
            if ((int) $r['id'] === $id) {
                return $r;
            }
        }

        return [];
    }
}
