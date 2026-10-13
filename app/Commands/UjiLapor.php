<?php

namespace App\Commands;

use App\Controllers\Admin\Pkl as AdminPkl;
use App\Controllers\Admin\PklBerkas;
use App\Controllers\Admin\SuratLapor;
use App\Controllers\Admin\SuratPkl as AdminSuratPkl;
use App\Controllers\LaporPkl;
use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\HakPeran;
use App\Libraries\LoginThrottle;
use App\Libraries\MenuAdmin;
use App\Libraries\PklAjuan;
use App\Libraries\PklDocx;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\PklLapor;
use App\Libraries\PklSurat;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPkl;
use App\Libraries\SuratSekolah;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Uji LAPOR DITERIMA PKL (rancangan: docs/DESAIN-HAK-AKSES.md bagian 4):
 *   1. sisi siswa (PklLapor): daftar kelas/siswa + status, bukti nomor HP, kirim & kirim ulang, validasi;
 *   2. hak staf: siapa melihat kotak / "Setujui & buat" / "Buat" menurut hak Buat/ACC Surat Balasan yang diatur Admin;
 *   3. alur: kembalikan → kirim ulang → proses (klaim atomik, surat langsung disetujui), Surat Balasan yang dibuat lewat jalur lain
 *      menandai laporan selesai, Balasan lama, batal klaim saat gagal, jenis tanpa ACC;
 *   4. halaman nyata: kotak laporan, formulir "Periksa & ubah dulu", Beranda & detail PKL, form publik;
 *   5. API aplikasi (api/v1/surat/balasan/laporan…): daftar, kembalikan, setujui, tolak ganda, gerbang hak;
 *   6. tanda tangan + stempel Kepala Sekolah: unggah, penggantian gambar bawaan template (ukuran & posisi), sidik, hak.
 *
 * Data uji bertanda "ZZUJI LP" (nama), NIS "ZZUJILP…", IP "10.9.7.8" — dibuat & dihapus sendiri; Pengaturan PKL dan tabel
 * peran dipulihkan ke isi semula di akhir (juga bila uji berhenti di tengah, pada jalan berikutnya).
 *
 * Jalankan:  php spark dev:uji-lapor
 */
class UjiLapor extends BaseCommand
{
    use UjiPeranAman;

    protected $group       = 'dev';
    protected $name        = 'dev:uji-lapor';
    protected $description = 'Uji Lapor Diterima PKL (form siswa, kotak staf, Balasan dari laporan, hak per peran) + TTD & stempel Kepala Sekolah.';

    private const NIS  = 'ZZUJILP';
    private const IP   = '10.9.7.8';
    private const OLEH = 'ZZUJI LP';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    /** @var array<string, string>|null isi berkas gambar tanda tangan di writable/pkl sebelum uji (dipulihkan di akhir) */
    private ?array $kepsekAsli = null;
    private int $kelasId = 0;
    private int $th = 0;
    /** @var array<int, int> nomor => id siswa uji */
    private array $s = [];
    /** @var array<string, int> huruf => id ajuan uji */
    private array $aj = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        try {
            return $this->doRun();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function doRun(): int
    {
        $this->db = db_connect();
        if (! PklLapor::tabelAda()) {
            CLI::write('Tabel pkl_lapor belum ada. Jalankan "php spark migrate" dulu.', 'red');

            return EXIT_ERROR;
        }
        $this->bersihkan();
        $this->simpanAsli();
        $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('ada kelas sebagai bahan uji', false, 'tabel kelas kosong');
            $this->bersihkan();

            return EXIT_ERROR;
        }
        $this->kelasId = (int) $k['id'];
        $this->th      = (int) date('Y') + 1;
        $this->netralkan();
        $this->siapkanData();

        $this->ujiSiswa();
        $this->ujiHak();
        $this->ujiAlur();
        $this->ujiHalaman();
        $this->ujiPublik();
        $this->ujiApi();
        $this->ujiTtdKepsek();

        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // Bahan uji
    // =================================================================

    private function siapkanData(): void
    {
        $now = date('Y-m-d H:i:s');
        for ($n = 1; $n <= 10; $n++) {
            $this->db->table('siswa')->insert([
                'nis' => self::NIS . $n, 'nama' => self::OLEH . ' ' . $n, 'jenis_kelamin' => 'L', 'kelas_id' => $this->kelasId,
                'no_hp' => '0812111100' . str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'status' => 'aktif', 'tanggal_lahir' => '2010-05-17',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->s[$n] = (int) $this->db->insertID();
        }
        // A: siswa 1 (pengaju) + 2 (teman) · B: 3 · C: 5 · D: 6 · E: 7 · F: 8 · G: 9 · siswa 4 & 10 tanpa ajuan.
        $this->aj['A'] = $this->ajuan($this->s[1], 'ZZUJILP Perusahaan A', [$this->s[2]]);
        $this->aj['B'] = $this->ajuan($this->s[3], 'ZZUJILP Perusahaan B');
        $this->aj['C'] = $this->ajuan($this->s[5], 'ZZUJILP Perusahaan C');
        $this->aj['D'] = $this->ajuan($this->s[6], 'ZZUJILP Perusahaan D');
        $this->aj['E'] = $this->ajuan($this->s[7], 'ZZUJILP Perusahaan E');
        $this->aj['F'] = $this->ajuan($this->s[8], 'ZZUJILP Perusahaan F');
        $this->aj['G'] = $this->ajuan($this->s[9], 'ZZUJILP Perusahaan G');
        $this->cek('bahan uji: 7 ajuan disetujui dibuat', count(array_filter($this->aj)) === 7, json_encode($this->aj));
    }

    /** @param list<int> $teman */
    private function ajuan(int $pengaju, string $perusahaan, array $teman = []): int
    {
        $data = [
            'perusahaan_nama' => $perusahaan, 'perusahaan_norm' => PklForm::normPerusahaan($perusahaan),
            'perusahaan_alamat' => 'Jl. Uji Lapor No. 1', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null, 'hp' => '081299990000', 'tanggal_lahir' => null, 'teman' => [],
        ];
        $anggota = [['siswa_id' => $pengaju, 'kelas_id' => $this->kelasId, 'peran' => 'pengaju', 'hp' => '081299990000']];
        foreach ($teman as $t) {
            $anggota[] = ['siswa_id' => $t, 'kelas_id' => $this->kelasId, 'peran' => 'teman', 'hp' => null];
        }
        $r = (new PklAjuan())->kirimBaru($data, $anggota,
            ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP, 'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui', 'tahun_ajaran' => '2031/2032']);

        return (int) ($r['id'] ?? 0);
    }

    private function hp(int $n): string
    {
        return '0812111100' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    private function tgl(string $bulanHari): string
    {
        return $this->th . '-' . $bulanHari;
    }

    /** @return array<string, mixed> */
    private function ctx(string $peran): array
    {
        return ['oleh' => self::OLEH . ' ' . $peran, 'admin_id' => null, 'peran' => $peran, 'ip' => self::IP, 'saluran' => 'web'];
    }

    private function lapor(string $huruf): ?array
    {
        return (new PklLapor())->untukAjuan($this->aj[$huruf]);
    }

    /** Siswa melapor: bukti HP lalu kirim. @return array<string, mixed> hasil kirim */
    private function laporkan(int $nSiswa, string $huruf, array $isi = []): array
    {
        $lib = new PklLapor();
        $c   = $lib->cek($this->s[$nSiswa], $this->hp($nSiswa), self::IP);
        if (! $c['ok']) {
            return $c;
        }

        return $lib->kirim($isi + ['ajuan_id' => $this->aj[$huruf], 'tanggal_mulai' => $this->tgl('07-01'), 'tanggal_selesai' => $this->tgl('09-30')], self::IP);
    }

    private function riwayat(int $ajuan, string $aksi): int
    {
        return (int) $this->db->table('pkl_riwayat')->where('pengajuan_id', $ajuan)->where('aksi', $aksi)->countAllResults();
    }

    private function statusSiswa(int $nSiswa): string
    {
        foreach ((new PklLapor())->siswaKelas($this->kelasId) as $r) {
            if ($r['id'] === $this->s[$nSiswa]) {
                return $r['status'];
            }
        }

        return '(tidak ada)';
    }

    /** Hak Surat Balasan: $peta peran => ['buat'?, 'acc'?]; hak lain tetap bawaan. */
    private function aturBalasan(array $peta): void
    {
        $this->setelPeranBawaan();
        $baru = [];
        foreach ($peta as $peran => $aksi) {
            $hak = array_values(array_filter(HakPeran::hakDaftar($peran), static fn (string $k): bool => ! str_ends_with($k, ':' . SuratJenis::BALASAN)));
            foreach ($aksi as $a) {
                $hak[] = PklHak::kunciSurat($a, SuratJenis::BALASAN);
            }
            $baru[$peran] = $hak;
        }
        HakPeran::simpanHak($baru);
        PklHak::lupakan();
    }

    // =================================================================
    // 1. Sisi siswa
    // =================================================================

    private function ujiSiswa(): void
    {
        $this->bagian('Sisi siswa: kelas, status, bukti HP, kirim');
        $lib = new PklLapor();

        $kelas = $lib->kelas();
        $ada   = false;
        foreach ($kelas as $daftar) {
            foreach ($daftar as $k) {
                $ada = $ada || $k['id'] === $this->kelasId;
            }
        }
        $this->cek('kelas(): kelas uji tampil (punya siswa dengan ajuan disetujui)', $ada);
        $this->cek('siswaKelas(): siswa beranjuan disetujui = boleh, tanpa ajuan = belum_ajuan', $this->statusSiswa(1) === 'boleh' && $this->statusSiswa(4) === 'belum_ajuan', $this->statusSiswa(1) . ' / ' . $this->statusSiswa(4));

        $r = $lib->cek(999999999, $this->hp(1), self::IP);
        $this->cek('cek(): siswa tak dikenal → 404', ! $r['ok'] && $r['http'] === 404, $r['pesan']);
        $r = $lib->cek($this->s[4], $this->hp(4), self::IP);
        $this->cek('cek(): siswa tanpa ajuan → 409', ! $r['ok'] && $r['http'] === 409, $r['pesan']);
        $r = $lib->cek($this->s[1], 'abc', self::IP);
        $this->cek('cek(): HP bukan angka → 422', ! $r['ok'] && $r['http'] === 422);
        $r = $lib->cek($this->s[1], '081277776666', self::IP);
        $this->cek('cek(): HP tidak cocok → 422 (tercatat di pembatas percobaan)', ! $r['ok'] && $r['http'] === 422, $r['pesan']);

        // Teman (bukan pengaju) membuktikan diri dengan HP di data siswa.
        $r = $lib->cek($this->s[2], $this->hp(2), self::IP);
        $d = $r['data'] ?? [];
        $this->cek('cek(): anggota teman + HP data siswa → cocok, ringkasan ajuan', $r['ok'] && ($d['kode'] ?? '') === PklPengajuanModel::kode($this->aj['A']) && count($d['anggota'] ?? []) === 2 && ($d['balasan'] ?? true) === false && array_key_exists('lapor', $d) && $d['lapor'] === null, json_encode($d));
        $r = $lib->cek($this->s[1], '081299990000', self::IP);
        $this->cek('cek(): pengaju + HP yang diisi di ajuan → cocok', $r['ok']);

        $r = $lib->kirim(['ajuan_id' => $this->aj['B'], 'tanggal_mulai' => $this->tgl('07-01'), 'tanggal_selesai' => $this->tgl('09-30')], self::IP);
        $this->cek('kirim(): ajuan yang belum dibuktikan dengan HP → 403', ! $r['ok'] && $r['http'] === 403);
        $r = $lib->kirim(['ajuan_id' => $this->aj['A'], 'tanggal_mulai' => $this->tgl('09-30'), 'tanggal_selesai' => $this->tgl('07-01')], self::IP);
        $this->cek('kirim(): selesai sebelum mulai → 422 di kolom tanggal_selesai', ! $r['ok'] && $r['http'] === 422 && isset($r['galat']['tanggal_selesai']));
        $r = $lib->kirim(['ajuan_id' => $this->aj['A'], 'tanggal_mulai' => '', 'tanggal_selesai' => $this->tgl('09-30'), 'alamat' => str_repeat('x', 301)], self::IP);
        $this->cek('kirim(): tanggal mulai kosong + alamat > 300 huruf → galat per kolom', ! $r['ok'] && isset($r['galat']['tanggal_mulai'], $r['galat']['alamat']));
        $this->cek('kirim() yang gagal tidak menyimpan apa pun', $this->lapor('A') === null);

        $r = $this->laporkan(2, 'A');
        $l = $this->lapor('A');
        $this->cek('kirim(): laporan tersimpan (baru, kiriman ke-1, pelapor = teman)', $r['ok'] && $l !== null && $l['status'] === 'baru' && (int) $l['kirim_ke'] === 1 && (int) $l['siswa_id'] === $this->s[2] && $l['alamat'] === null, json_encode($r['data'] ?? $r));
        $this->cek('kirim(): tercatat di riwayat ajuan (lapor_diterima) tanpa label peran staf', $this->riwayat($this->aj['A'], 'lapor_diterima') === 1
            && $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['A'])->where('aksi', 'lapor_diterima')->where('peran', null)->countAllResults() === 1);
        $this->cek('kirim(): tercatat di Audit Log dengan id laporan', $this->db->table('audit_log')->where('tabel', 'pkl_lapor')->where('record_id', (int) $l['id'])->countAllResults() === 1);
        $this->cek('status siswa sekelompok → sudah_lapor', $this->statusSiswa(1) === 'sudah_lapor' && $this->statusSiswa(2) === 'sudah_lapor');

        $r = $this->laporkan(1, 'A', ['alamat' => 'JL. UJI LAPOR NO. 1, BEKASI', 'catatan' => 'Bagian IT']);
        $l = $this->lapor('A');
        $this->cek('kirim ulang: baris yang sama diperbarui (kiriman ke-2), alamat sama dengan ajuan tidak disimpan', $r['ok'] && (int) $l['kirim_ke'] === 2 && $l['alamat'] === null && $l['catatan'] === 'Bagian IT' && (int) $l['siswa_id'] === $this->s[1]);
        $r = $this->laporkan(1, 'A', ['alamat' => 'Jl. Baru No. 2, Bekasi']);
        $l = $this->lapor('A');
        $this->cek('kirim ulang: alamat baru dari siswa disimpan', $r['ok'] && (int) $l['kirim_ke'] === 3 && $l['alamat'] === 'Jl. Baru No. 2, Bekasi' && $this->db->table('pkl_lapor')->where('pengajuan_id', $this->aj['A'])->countAllResults() === 1);
        $this->cek('ringkasan(): siswa melihat status laporannya', ($lib->ringkasan($this->aj['A'])['lapor']['status'] ?? '') === 'baru');
    }

    // =================================================================
    // 2. Hak staf
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Hak staf (diatur Admin: Buat / ACC Surat Balasan)');
        $this->setelPeranBawaan();
        $op = PklLapor::hak('operator');
        $hb = PklLapor::hak('hubin');
        $ad = PklLapor::hak('admin');
        $this->cek('bawaan: Operator (Buat) → lihat + "Buat" (antrean ACC), tanpa "Setujui & buat"', $op['lihat'] && $op['buat'] && ! $op['setujui'] && $op['perlu_acc'], json_encode($op));
        $this->cek('bawaan: Waka Hubin (ACC) → lihat + "Setujui & buat", tanpa "Buat"', $hb['lihat'] && $hb['setujui'] && ! $hb['buat'], json_encode($hb));
        $this->cek('Admin → semua tombol', $ad['lihat'] && $ad['setujui'] && $ad['buat'] && $ad['kembalikan']);
        $this->cek('penjaga rute: kotak laporan terbuka bagi Operator & Waka Hubin', HakAkses::boleh('operator', 'admin/surat/balasan/laporan') && HakAkses::boleh('hubin', 'admin/surat/balasan/laporan'));
        $this->cek('penjaga rute: POST aksi laporan lolos rute (hak persis diperiksa controller)', HakAkses::boleh('operator', 'admin/surat/balasan/laporan/5/setujui', 'POST') && HakAkses::boleh('hubin', 'admin/surat/balasan/laporan/5/kembalikan', 'POST'));

        // Admin memutuskan: Surat Balasan HANYA Operator (buat + ACC); Waka Hubin tidak ikut.
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
        $op = PklLapor::hak('operator');
        $hb = PklLapor::hak('hubin');
        $this->cek('Admin atur "ACC Balasan = Operator saja" → Operator dapat "Setujui & buat"', $op['setujui'] && $op['buat']);
        $this->cek('… Waka Hubin tidak lagi melihat kotak (rute & tombol)', ! $hb['lihat'] && ! HakAkses::boleh('hubin', 'admin/surat/balasan/laporan'));
        $this->cek('… teks pemegang ACC = Operator Sekolah', PklHak::teksPemegang(PklHak::kunciSurat('acc', SuratJenis::BALASAN)) === HakPeran::label('operator'), PklHak::teksPemegang(PklHak::kunciSurat('acc', SuratJenis::BALASAN)));

        // Keduanya ACC: dua-duanya boleh "Setujui & buat"; yang menekan lebih dulu menang (diuji di alur).
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => ['acc']]);
        $this->cek('Admin atur "keduanya ACC" → Operator & Waka Hubin sama-sama "Setujui & buat"', PklLapor::hak('operator')['setujui'] && PklLapor::hak('hubin')['setujui']);

        $this->aturBalasan(['operator' => []]);
        $this->cek('tanpa hak Buat & ACC Balasan → kotak tertutup', ! PklLapor::hak('operator')['lihat'] && ! HakAkses::boleh('operator', 'admin/surat/balasan/laporan'));

        $item = [];
        foreach (MenuAdmin::sidebar() as $g) {
            if ($g['title'] === 'SURAT SEKOLAH') {
                $item = array_column($g['items'], 0);
            }
        }
        $i = array_search(SuratJenis::alamat(SuratJenis::BALASAN), $item, true);
        $this->cek('menu samping: "Lapor Diterima PKL" tepat setelah Surat Balasan', $i !== false && ($item[$i + 1] ?? '') === 'admin/surat/balasan/laporan', json_encode($item));
        $this->setelPeranBawaan();
    }

    // =================================================================
    // 3. Alur
    // =================================================================

    private function ujiAlur(): void
    {
        $this->bagian('Alur: kembalikan, kirim ulang, proses, tautan otomatis');
        $lib = new PklLapor();
        $idA = (int) $this->lapor('A')['id'];

        $r = $lib->kembalikan($idA, 'abc', $this->ctx('operator'));
        $this->cek('kembalikan: alasan < 5 huruf → 422', ! $r['ok'] && $r['http'] === 422);
        $r = $lib->kembalikan($idA, 'Tanggal selesai keliru, cek surat perusahaan', $this->ctx('operator'));
        $l = $this->lapor('A');
        $this->cek('kembalikan: status dikembalikan + alasan + pelaku', $r['ok'] && $l['status'] === 'dikembalikan' && $l['alasan'] === 'Tanggal selesai keliru, cek surat perusahaan' && $l['diproses_peran'] === 'operator');
        $this->cek('kembalikan: riwayat lapor_kembali', $this->riwayat($this->aj['A'], 'lapor_kembali') === 1);
        $r = $lib->kembalikan($idA, 'Sekali lagi dikembalikan', $this->ctx('operator'));
        $this->cek('kembalikan dua kali → 409', ! $r['ok'] && $r['http'] === 409);
        $this->cek('siswa melihat "dikembalikan" + alasannya', $this->statusSiswa(1) === 'dikembalikan' && ($lib->ringkasan($this->aj['A'])['lapor']['alasan'] ?? '') !== '');
        $r = $this->laporkan(1, 'A', ['alamat' => 'Jl. Baru No. 2, Bekasi', 'tanggal_selesai' => $this->tgl('10-31')]);
        $l = $this->lapor('A');
        $this->cek('kirim ulang setelah dikembalikan → baru lagi, alasan & pelaku dikosongkan', $r['ok'] && $l['status'] === 'baru' && $l['alasan'] === null && $l['diproses_oleh'] === null && (int) $l['kirim_ke'] === 4);

        // Bawaan: Waka Hubin hanya ACC, Operator hanya Buat.
        $this->setelPeranBawaan();
        $r = $lib->proses($idA, false, $this->ctx('hubin'));
        $this->cek('proses "Buat" oleh peran tanpa hak Buat → 403', ! $r['ok'] && $r['http'] === 403);
        $r = $lib->proses($idA, true, $this->ctx('operator'));
        $this->cek('proses "Setujui & buat" oleh peran tanpa hak ACC → 403, menyebut pemegang ACC', ! $r['ok'] && $r['http'] === 403 && str_contains($r['pesan'], 'Hak Akses'), $r['pesan']);
        $this->cek('penolakan tidak mengubah laporan', $this->lapor('A')['status'] === 'baru');

        // Admin: ACC Balasan = Operator saja.
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
        $r = $lib->proses($idA, true, $this->ctx('operator'));
        $l = $this->lapor('A');
        $s = $this->db->table('surat_sekolah')->where('id', (int) ($r['surat_id'] ?? 0))->get()->getRowArray();
        $isi = json_decode((string) ($s['isi'] ?? '{}'), true) ?: [];
        $this->cek('"Setujui & buat" oleh Operator → Surat Balasan langsung DISETUJUI atas namanya', $r['ok'] && $s !== null && $s['status'] === 'disetujui' && $s['acc_peran'] === 'operator' && $s['acc_nama'] === self::OLEH . ' operator', $r['pesan']);
        $this->cek('isi surat dari laporan: periode & alamat baru, satu surat per ajuan', $s !== null && ($isi['periode_mulai'] ?? '') === $this->tgl('07-01') && ($isi['periode_selesai'] ?? '') === $this->tgl('10-31') && ($isi['alamat'] ?? '') === 'Jl. Baru No. 2, Bekasi' && (int) $s['pengajuan_id'] === $this->aj['A']);
        $this->cek('laporan → diproses, tertaut ke surat, riwayat lapor_proses', $l['status'] === 'diproses' && (int) $l['surat_id'] === (int) ($r['surat_id'] ?? -1) && $this->riwayat($this->aj['A'], 'lapor_proses') === 1);
        $p = $this->db->table('pkl_pengajuan')->select('tanggal_mulai, tanggal_selesai')->where('id', $this->aj['A'])->get()->getRowArray();
        $this->cek('periode ikut tersimpan di ajuan PKL', $p['tanggal_mulai'] === $this->tgl('07-01') && $p['tanggal_selesai'] === $this->tgl('10-31'));
        $r2 = $lib->proses($idA, true, $this->ctx('operator'));
        $this->cek('proses kedua kali (mis. dua staf menekan bersamaan) → 409, tidak ada surat ganda', ! $r2['ok'] && $r2['http'] === 409
            && $this->db->table('surat_sekolah')->where('pengajuan_id', $this->aj['A'])->where('jenis', SuratJenis::BALASAN)->countAllResults() === 1);
        $r = $this->laporkan(2, 'A');
        $this->cek('siswa tidak bisa melapor lagi setelah Balasan dibuat (409), status selesai', ! $r['ok'] && $r['http'] === 409 && $this->statusSiswa(2) === 'selesai', $r['pesan'] ?? '');

        // B: Balasan dibuat lewat formulir biasa / pilih banyak → laporan menunggu ikut ditandai selesai.
        $this->laporkan(3, 'B');
        $h   = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => '', 'periode_mulai' => $this->tgl('07-01'), 'periode_selesai' => $this->tgl('09-30')]);
        $b   = (new SuratPkl())->buat(SuratJenis::BALASAN, $h, [$this->aj['B']], [], $this->ctx('operator'));
        $lB  = $this->lapor('B');
        $this->cek('Balasan dibuat lewat formulir → laporan menunggu otomatis selesai & tertaut', $b['ok'] && ($b['lapor'] ?? 0) === 1 && $lB['status'] === 'diproses' && (int) $lB['surat_id'] === (int) $b['dibuat'][0]);

        // C: Balasan sudah ada sebelum laporan diproses (data lama) → proses hanya menandai selesai.
        $this->laporkan(5, 'C');
        $b  = (new SuratPkl())->buat(SuratJenis::BALASAN, $h, [$this->aj['C']], [], $this->ctx('operator'));
        $lC = $this->lapor('C');
        $this->db->table('pkl_lapor')->where('id', (int) $lC['id'])->update(['status' => 'baru', 'surat_id' => null, 'diproses_oleh' => null, 'diproses_peran' => null, 'diproses_at' => null]);
        $r = $lib->proses((int) $lC['id'], true, $this->ctx('operator'));
        $lC = $this->lapor('C');
        $this->cek('Balasan sudah ada → laporan ditandai selesai, tertaut ke surat lama, tanpa surat baru', $r['ok'] && str_contains($r['pesan'], 'sudah ada') && $lC['status'] === 'diproses' && (int) $lC['surat_id'] === (int) $b['dibuat'][0]
            && $this->db->table('surat_sekolah')->where('pengajuan_id', $this->aj['C'])->where('jenis', SuratJenis::BALASAN)->countAllResults() === 1, $r['pesan']);

        // D: kegagalan setelah klaim → klaim dibatalkan (laporan kembali "baru").
        $this->laporkan(6, 'D');
        $lD = $this->lapor('D');
        $this->db->table('pkl_pengajuan')->where('id', $this->aj['D'])->update(['status' => 'perbaikan']);
        $r = $lib->proses((int) $lD['id'], true, $this->ctx('operator'));
        $this->cek('ajuan tidak lagi disetujui → gagal 409, klaim dibatalkan (laporan tetap menunggu)', ! $r['ok'] && $r['http'] === 409 && $this->lapor('D')['status'] === 'baru' && $this->lapor('D')['diproses_oleh'] === null, $r['pesan']);
        $this->db->table('pkl_pengajuan')->where('id', $this->aj['D'])->update(['status' => 'disetujui']);
        $this->db->table('pkl_lapor')->where('id', (int) $lD['id'])->update(['tanggal_selesai' => $this->tgl('06-01')]);
        $r = $lib->proses((int) $lD['id'], true, $this->ctx('operator'));
        $this->cek('isian laporan tak sah untuk surat → 422, klaim dibatalkan', ! $r['ok'] && $r['http'] === 422 && $this->lapor('D')['status'] === 'baru', $r['pesan']);
        $this->db->table('pkl_lapor')->where('id', (int) $lD['id'])->update(['tanggal_selesai' => $this->tgl('09-30')]);

        // Jenis Balasan dibuat TANPA ACC (Pengaturan surat): hak Buat cukup, surat langsung siap unduh.
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['surat_perlu_acc' => json_encode([SuratJenis::BALASAN => 0])]);
        $this->aturBalasan(['operator' => ['buat', 'unduh']]);
        $op = PklLapor::hak('operator');
        $this->cek('Balasan tanpa ACC: hak Buat → "Buat surat" (langsung siap unduh)', $op['setujui'] && ! $op['buat'] && ! $op['perlu_acc'], json_encode($op));
        $r = $lib->proses((int) $lD['id'], true, $this->ctx('operator'));
        $sD = $this->db->table('surat_sekolah')->select('status')->where('id', (int) ($r['surat_id'] ?? 0))->get()->getRowArray();
        $this->cek('… surat langsung berstatus siap unduh', $r['ok'] && ($sD['status'] ?? '') === 'disetujui' && str_contains($r['pesan'], 'tanpa ACC'), $r['pesan']);
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['surat_perlu_acc' => null]);
        $this->setelPeranBawaan();
    }

    // =================================================================
    // 4. Halaman nyata (controller web)
    // =================================================================

    /**
     * Panggil metode controller dengan peran + masukan tertentu (sesi & request ditiru). Akun sesi memakai id admin sungguhan
     * (1 admin, 2 hubin, 3 operator) karena Audit Log punya kunci asing ke tabel admins.
     */
    private function panggil(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/surat'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'username' => 'zzujilp', 'role' => $peran]);
        session()->remove(['_ci_old_input', 'error', 'success', 'errors']);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** Panggil controller halaman → HTML dengan entitas didekode (esc(..., 'attr') menyandikan spasi dan titik dua). */
    private function halaman(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): string
    {
        $r = $this->panggil($kelas, $peran, $metode, $get, $post, ...$arg);

        return html_entity_decode(is_string($r) ? $r : '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function lokasi(mixed $r): string
    {
        return $r instanceof RedirectResponse ? $r->getHeaderLine('Location') : '';
    }

    private function ujiHalaman(): void
    {
        $this->bagian('Halaman: kotak laporan, "Periksa & ubah dulu", Beranda & detail PKL');
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
        $this->laporkan(7, 'E', ['alamat' => 'Jl. Kotak No. 7', 'catatan' => 'Masuk jam 8']);
        $this->laporkan(8, 'F');
        $idE = (int) $this->lapor('E')['id'];
        $idF = (int) $this->lapor('F')['id'];

        $html = $this->halaman(SuratLapor::class, 'operator', 'index');
        $this->cek('kotak laporan (Operator): laporan E & F tampil dengan tombol Setujui, Buat, Periksa, Kembalikan',
            str_contains($html, PklPengajuanModel::kode($this->aj['E'])) && str_contains($html, 'admin/surat/balasan/laporan/' . $idE . '/setujui') && str_contains($html, 'admin/surat/balasan/laporan/' . $idE . '/buat')
            && str_contains($html, 'admin/surat/balasan?lapor=' . $idE) && str_contains($html, 'admin/surat/balasan/laporan/' . $idE . '/kembalikan') && str_contains($html, 'Jl. Kotak No. 7') && str_contains($html, 'Masuk jam 8'));
        $r = $this->panggil(SuratLapor::class, 'hubin', 'index');
        $this->cek('kotak laporan (Waka Hubin tanpa hak) → dialihkan dengan pesan', $r instanceof RedirectResponse && str_contains((string) session('error'), 'Hak Akses'), (string) session('error'));
        $html = $this->halaman(SuratLapor::class, 'operator', 'index', ['status' => 'diproses']);
        $this->cek('tab "Sudah dibuat suratnya" menautkan ke Surat Balasan', str_contains($html, 'Buka Surat Balasan'));

        // "Periksa & ubah dulu": formulir terisi dari laporan; surat hanya untuk ajuan laporan itu.
        $html = $this->halaman(AdminSuratPkl::class, 'operator', 'balasan', ['lapor' => $idE]);
        $this->cek('formulir dari laporan: lapor_id tersembunyi, periode & alamat dari laporan, hanya ajuan itu',
            str_contains($html, 'name="lapor_id" value="' . $idE . '"') && str_contains($html, 'value="Jl. Kotak No. 7"') && str_contains($html, 'Dari laporan siswa')
            && str_contains($html, PklPengajuanModel::kode($this->aj['E'])) && ! str_contains($html, PklPengajuanModel::kode($this->aj['G'])));
        $r = $this->panggil(AdminSuratPkl::class, 'operator', 'simpanBalasan', [], [
            'lapor_id' => (string) $idE, 'ajuan' => [(string) $this->aj['G']], 'tanggal_surat' => date('Y-m-d'), 'sapaan' => 'Pimpinan',
            'periode_mulai' => $this->tgl('08-01'), 'periode_selesai' => $this->tgl('10-31'), 'alamat' => '', 'langsung_acc' => '0',
        ]);
        $lE = $this->lapor('E');
        $sE = $this->db->table('surat_sekolah')->where('id', (int) $lE['surat_id'])->get()->getRowArray();
        $isiE = json_decode((string) ($sE['isi'] ?? '{}'), true) ?: [];
        $this->cek('simpan dari laporan → surat HANYA untuk ajuan laporan (kiriman ajuan lain diabaikan), laporan selesai',
            str_contains($this->lokasi($r), 'admin/surat/') && $lE['status'] === 'diproses' && $sE !== null && (int) $sE['pengajuan_id'] === $this->aj['E']
            && $this->db->table('surat_sekolah')->where('pengajuan_id', $this->aj['G'])->countAllResults() === 0 && str_contains((string) session('success'), 'Laporan siswa ditandai selesai'), (string) session('success') . (string) session('error'));
        $this->cek('… periode hasil koreksi staf, alamat kosong = alamat ajuan, surat menunggu ACC (tombol biasa)',
            ($isiE['periode_mulai'] ?? '') === $this->tgl('08-01') && ($isiE['alamat'] ?? '') === 'Jl. Uji Lapor No. 1, Bekasi' && ($sE['status'] ?? '') === 'menunggu', json_encode($isiE));
        $r = $this->panggil(AdminSuratPkl::class, 'operator', 'balasan', ['lapor' => $idE]);
        $this->cek('membuka formulir dari laporan yang sudah diproses → kembali ke kotak dengan pesan', str_contains($this->lokasi($r), 'admin/surat/balasan/laporan') && session('error') !== null);

        // Kembalikan & "Setujui & buat" lewat controller.
        $r = $this->panggil(SuratLapor::class, 'operator', 'kembalikan', [], ['alasan' => 'Periode belum sesuai surat perusahaan'], $idF);
        $this->cek('controller kembalikan → laporan dikembalikan', str_contains($this->lokasi($r), 'admin/surat/balasan/laporan') && $this->lapor('F')['status'] === 'dikembalikan');
        $this->laporkan(8, 'F');
        $r = $this->panggil(SuratLapor::class, 'operator', 'setujui', [], [], $idF);
        $lF = $this->lapor('F');
        $this->cek('controller "Setujui & buat" → ke halaman surat, surat disetujui', $lF['status'] === 'diproses' && str_contains($this->lokasi($r), 'admin/surat/' . (int) $lF['surat_id'])
            && $this->db->table('surat_sekolah')->where('id', (int) $lF['surat_id'])->where('status', 'disetujui')->countAllResults() === 1, $this->lokasi($r));
        $r = $this->panggil(SuratLapor::class, 'operator', 'buat', [], [], $idF);
        $this->cek('controller proses laporan yang sudah diproses → kembali ke kotak dengan pesan', str_contains($this->lokasi($r), 'admin/surat/balasan/laporan') && session('error') !== null);

        // Beranda & detail PKL: laporan G menunggu.
        $this->laporkan(9, 'G');
        $html = $this->halaman(AdminPkl::class, 'operator', 'index');
        $this->cek('Beranda PKL: pita jumlah laporan menunggu', str_contains($html, 'laporan siswa "sudah diterima perusahaan"') || str_contains($html, 'laporan siswa &quot;sudah diterima perusahaan&quot;'));
        $html = $this->halaman(AdminPkl::class, 'operator', 'detail', [], [], $this->aj['G']);
        $idG  = (int) $this->lapor('G')['id'];
        $this->cek('detail ajuan: status laporan + tombol Balasan memakai laporan (?lapor=)', str_contains($html, 'Lapor diterima dari siswa') && str_contains($html, 'admin/surat/balasan?lapor=' . $idG));
        $this->setelPeranBawaan();
    }

    // =================================================================
    // 5. Form publik
    // =================================================================

    private function ujiPublik(): void
    {
        $this->bagian('Form publik pkl/lapor');
        $html = $this->halaman(LaporPkl::class, 'operator', 'index');
        $this->cek('form lapor terbuka (bawaan)', str_contains($html, 'pklLapor') || str_contains($html, 'pkl-lapor.js'));
        /** @var ResponseInterface $r */
        $r = $this->panggil(LaporPkl::class, 'operator', 'siswa', ['kelas_id' => (string) $this->kelasId]);
        $j = json_decode((string) $r->getBody(), true) ?: [];
        $nama = array_column($j['data'] ?? [], 'status', 'nama');
        $this->cek('GET siswa: daftar nama + status saja (tanpa data perusahaan)', ($j['ok'] ?? false) && ($nama[self::OLEH . ' 9'] ?? '') === 'sudah_lapor' && ! str_contains((string) $r->getBody(), 'ZZUJILP Perusahaan'));
        $r = $this->panggil(LaporPkl::class, 'operator', 'cek', [], ['siswa_id' => (string) $this->s[10], 'hp' => $this->hp(10)]);
        $this->cek('POST cek: siswa tanpa ajuan → 409 JSON', $r->getStatusCode() === 409);
        $r = $this->panggil(LaporPkl::class, 'operator', 'kirim', [], ['website' => 'http://spam.example', 'ajuan_id' => (string) $this->aj['G']]);
        $j = json_decode((string) $r->getBody(), true) ?: [];
        $this->cek('jebakan bot: pura-pura berhasil, tidak menyimpan apa pun', ($j['ok'] ?? false) === true && (int) $this->lapor('G')['kirim_ke'] === 1);

        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['lapor_buka' => 0]);
        $html = $this->halaman(LaporPkl::class, 'operator', 'index');
        $r    = $this->panggil(LaporPkl::class, 'operator', 'cek', [], ['siswa_id' => (string) $this->s[9], 'hp' => $this->hp(9)]);
        $this->cek('saklar Pengaturan PKL "tutup form lapor" → halaman tutup, cek ditolak 403', ! str_contains($html, 'pkl-lapor.js') && $r->getStatusCode() === 403);
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['lapor_buka' => 1]);
    }

    // =================================================================
    // 5b. API aplikasi Android
    // =================================================================

    /** Panggil Api\Surat sebagai $peran (token ditiru lewat ApiAuth). @return array{0: int, 1: array<string, mixed>} [kode HTTP, isi JSON] */
    private function api(string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): array
    {
        ApiAuth::set(['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'role' => $peran]);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/surat'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Api\Surat();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r = $c->{$metode}(...$arg);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    private function ujiApi(): void
    {
        $this->bagian('API aplikasi: api/v1/surat/balasan/laporan');
        try {
            $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
            $idG = (int) $this->lapor('G')['id'];
            [$k, $j] = $this->api('operator', 'laporan');
            $ids     = array_column($j['data'] ?? [], 'id');
            $baris   = $j['data'][array_search($idG, $ids, true)] ?? [];
            $this->cek('GET laporan (Operator): daftar + hitungan + hak + tautan form', $k === 200 && in_array($idG, $ids, true) && ($j['meta']['hak']['setujui'] ?? false) === true
                && (int) ($j['meta']['hitung']['baru'] ?? 0) >= 1 && str_ends_with((string) ($j['meta']['form']['tautan'] ?? ''), 'pkl/lapor')
                && ($baris['kode_ajuan'] ?? '') === PklPengajuanModel::kode($this->aj['G']) && count($baris['anggota'] ?? []) === 1 && ($baris['ajuan_sah'] ?? false) === true, json_encode($j['meta'] ?? $j));
            [$k] = $this->api('operator', 'laporan', ['status' => 'ngawur']);
            $this->cek('GET laporan status tak dikenal → 422', $k === 422);
            [$k, $j] = $this->api('hubin', 'laporan');
            $this->cek('GET laporan oleh peran tanpa hak Balasan → 403', $k === 403 && str_contains((string) ($j['message'] ?? ''), 'Hak Akses'));
            $this->cek('gerbang API: modul Surat dipegang → alamat laporan lolos apiauth', HakAkses::bolehApiAlamat('operator', 'surat/balasan/laporan/5/setujui'));

            [$k, $j] = $this->api('operator', 'laporanKembalikan', [], ['alasan' => 'abc'], $idG);
            $this->cek('POST kembalikan alasan pendek → 422', $k === 422);
            [$k, $j] = $this->api('operator', 'laporanKembalikan', [], ['alasan' => 'Tanggal mulai belum sesuai surat perusahaan'], $idG);
            $this->cek('POST kembalikan → 200, laporan dikembalikan', $k === 200 && ($j['data']['status'] ?? '') === 'dikembalikan' && $this->lapor('G')['status'] === 'dikembalikan');
            $this->laporkan(9, 'G');
            [$k, $j] = $this->api('operator', 'laporanSetujui', [], [], $idG);
            $sid     = (int) ($j['data']['surat_id'] ?? 0);
            $this->cek('POST setujui (Operator pemegang ACC) → Surat Balasan dibuat & disetujui', $k === 200 && $sid > 0 && ($j['data']['status_surat'] ?? '') === 'disetujui' && (int) $this->lapor('G')['surat_id'] === $sid, (string) ($j['message'] ?? ''));
            $this->cek('… tercatat di Audit Log sebagai via aplikasi', $this->db->table('audit_log')->where('tabel', 'pkl_lapor')->where('record_id', $idG)->like('deskripsi', 'via aplikasi')->countAllResults() === 1);
            [$k, $j] = $this->api('operator', 'laporanBuat', [], [], $idG);
            $this->cek('POST buat untuk laporan yang sudah diproses → 409', $k === 409, (string) ($j['message'] ?? ''));
            [$k] = $this->api('operator', 'laporanSetujui', [], [], 999999999);
            $this->cek('POST setujui laporan tak ada → 404', $k === 404);
        } finally {
            ApiAuth::clear();
            $this->setelPeranBawaan();
        }
    }

    // =================================================================
    // 6. Tanda tangan + stempel Kepala Sekolah
    // =================================================================

    /** Gambar PNG uji (latar putih, coretan biru) → path berkas sementara. */
    private function gambarUji(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 30, 60, 200));
        $path = (string) tempnam(sys_get_temp_dir(), 'zzujilp');
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /** @return array<string, string> isi paket Word (nama bagian => isi) */
    private function bukaDocx(string $biner): array
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'zzujilpdocx');
        file_put_contents($tmp, $biner);
        $z   = new \ZipArchive();
        $out = [];
        if ($z->open($tmp) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                $out[(string) $z->getNameIndex($i)] = (string) $z->getFromIndex($i);
            }
            $z->close();
        }
        @unlink($tmp);

        return $out;
    }

    private function ujiTtdKepsek(): void
    {
        $this->bagian('Tanda tangan + stempel Kepala Sekolah');
        $p0 = (new PklPengaturanModel())->ambil();
        $this->cek('belum ada unggahan → template dipakai apa adanya', PklSurat::infoTtdKepsek($p0) === null && PklSurat::gantiGambar($p0) === []);

        $teks = (string) tempnam(sys_get_temp_dir(), 'zzujilp');
        file_put_contents($teks, 'bukan gambar');
        $kecil = $this->gambarUji(60, 60);
        $baik  = $this->gambarUji(311, 281);
        $r1 = PklSurat::simpanTtdKepsek($teks, 12);
        $r2 = PklSurat::simpanTtdKepsek($kecil, (int) filesize($kecil));
        $r3 = PklSurat::simpanTtdKepsek($baik, 6 * 1048576);
        $this->cek('unggah ditolak: bukan gambar / terlalu kecil / lebih dari 5 MB', ! $r1['ok'] && ! $r2['ok'] && ! $r3['ok'] && str_contains($r3['pesan'], '5 MB'), $r1['pesan'] . ' | ' . $r2['pesan']);
        $r = PklSurat::simpanTtdKepsek($baik, (int) filesize($baik));
        $p = (new PklPengaturanModel())->ambil();
        $k = PklSurat::infoTtdKepsek($p);
        $this->cek('unggah PNG 311×281 → tersimpan di writable/pkl, dimuat dalam kotak 4,0 × 3,6 cm', $r['ok'] && $k !== null && ($p['ttd_kepsek'] ?? '') === 'ttd_kepsek.png'
            && is_file(PklSurat::dirBerkas() . 'ttd_kepsek.png') && $k['cx'] <= 1440000 && $k['cy'] <= 1296000 && ($k['cx'] === 1440000 || $k['cy'] === 1296000), json_encode($k));
        $uk  = @getimagesize(PklSurat::dirBerkas() . 'ttd_kepsek.png') ?: [0, 0];
        $img = @imagecreatefrompng(PklSurat::dirBerkas() . 'ttd_kepsek.png');
        $sudut = $img ? (imagecolorat($img, 0, 0) >> 24 & 0x7F) : -1;
        $tengah = $img ? (imagecolorat($img, intdiv($uk[0], 2), intdiv($uk[1], 2)) >> 24 & 0x7F) : -1;
        $this->cek('gambar dirapikan otomatis: tepi kosong dipotong, sudut transparan, tinta utuh, gambar asli disimpan', $uk[0] > 0 && $uk[0] < 311 && $uk[1] < 281 && $sudut === 127 && $tengah === 0
            && is_file(PklSurat::dirBerkas() . 'ttd_kepsek_asli.png') && str_contains($r['pesan'], 'dirapikan otomatis'), $uk[0] . '×' . $uk[1] . ' sudut α=' . $sudut . ' tengah α=' . $tengah);
        foreach ([$teks, $kecil, $baik] as $t) {
            @unlink($t);
        }

        // Perakitan: Surat Balasan — gambar Kepala Sekolah bawaan (rId9) diganti, ukuran & posisi rapian sekolah.
        $g   = PklSurat::gantiGambar($p);
        $tpl = SuratJenis::pathTemplate(SuratJenis::BALASAN, '');
        $d   = $this->bukaDocx(PklDocx::dariTemplate($tpl, [['v' => [], 'siswa' => []], ['v' => [], 'siswa' => []]], [], $g));
        $doc = $d['word/document.xml'] ?? '';
        $this->cek('Balasan: gambar bawaan diganti di SEMUA salinan surat, relasi + berkas + tipe konten ditambahkan',
            substr_count($doc, 'r:embed="rIdTtdKepsek"') === 2 && ! str_contains($doc, 'r:embed="rId9"')
            && str_contains($d['word/_rels/document.xml.rels'] ?? '', 'Id="rIdTtdKepsek"') && ($d['word/media/rIdTtdKepsek.png'] ?? '') === file_get_contents($k['path'])
            && (bool) preg_match('/Extension="png"/i', $d['[Content_Types].xml'] ?? ''));
        $x = '';
        if (($a = strpos($doc, 'r:embed="rIdTtdKepsek"')) !== false) {
            $mulai = (int) strrpos(substr($doc, 0, $a), '<w:drawing>');
            $x     = substr($doc, $mulai, (int) strpos($doc, '</w:drawing>', $a) - $mulai);
        }
        preg_match('/<wp:positionH\b[^>]*>\s*<wp:posOffset>(-?\d+)/', $x, $h);
        preg_match('/<wp:positionV\b[^>]*>\s*<wp:posOffset>(-?\d+)/', $x, $v);
        $hHarus = 3657600 + 755650 - 108000 - $k['cx'];
        $vHarus = 78740 + intdiv(1175456, 2) - 29268 - intdiv($k['cy'], 2);
        $this->cek('Balasan: ukuran & posisi = rapian sekolah (tepi kanan 0,3 cm ke kiri, tengah 0,08 cm ke atas), di belakang teks',
            str_contains($x, '<wp:extent cx="' . $k['cx'] . '" cy="' . $k['cy'] . '"/>') && str_contains($x, '<a:ext cx="' . $k['cx'] . '" cy="' . $k['cy'] . '"/>')
            && (int) ($h[1] ?? -1) === $hHarus && (int) ($v[1] ?? -1) === $vHarus && str_contains($x, 'behindDoc="1"') && ! str_contains($x, 'SCAN BERKAS'),
            'H ' . ($h[1] ?? '?') . ' (harus ' . $hHarus . '), V ' . ($v[1] ?? '?') . ' (harus ' . $vHarus . ')');

        $izin = $this->bukaDocx(PklDocx::dariTemplate(APPPATH . PklSurat::TEMPLATE_BAWAAN, [['v' => [], 'siswa' => []]], [], $g));
        $asts = $this->bukaDocx(PklDocx::dariTemplate(SuratJenis::pathTemplate(SuratJenis::ASTS, ''), [['v' => [], 'siswa' => []]], [], $g));
        $this->cek('Surat Izin PKL & Izin ASTS (Id relasi beda: rId9 / rId6) ikut diganti', str_contains($izin['word/document.xml'] ?? '', 'r:embed="rIdTtdKepsek"') && str_contains($asts['word/document.xml'] ?? '', 'r:embed="rIdTtdKepsek"') && ! str_contains($asts['word/document.xml'] ?? '', 'r:embed="rId6"'));
        $tplP = SuratJenis::pathTemplate(SuratJenis::PERNYATAAN, '');
        $pern = $this->bukaDocx(PklDocx::dariTemplate($tplP, [['v' => [], 'siswa' => []]], [], $g));
        $polos = $this->bukaDocx(PklDocx::dariTemplate($tplP, [['v' => [], 'siswa' => []]]));
        $this->cek('Pernyataan Orang Tua (tanpa tanda tangan Kepala Sekolah) tidak berubah', ! isset($pern['word/media/rIdTtdKepsek.png']) && ($pern['word/document.xml'] ?? 'a') === ($polos['word/document.xml'] ?? 'b'));

        // Sidik: surat bernomor → "perlu cetak ulang" setelah gambar diunggah; Pernyataan tidak.
        $bal  = ['jenis' => SuratJenis::BALASAN, 'tanggal_surat' => '2027-01-04', 'perusahaan_nama' => 'X', 'isi' => '{}'];
        $prn  = ['jenis' => SuratJenis::PERNYATAAN] + $bal;
        $ajn  = ['perusahaan_nama' => 'X'];
        $this->cek('sidik surat bernomor & Surat Izin PKL berubah setelah unggah; Pernyataan tetap',
            SuratSekolah::sidik($bal, [], $p0) !== SuratSekolah::sidik($bal, [], $p) && SuratSekolah::sidik($prn, [], $p0) === SuratSekolah::sidik($prn, [], $p)
            && PklSurat::sidik($ajn, [], $p0) !== PklSurat::sidik($ajn, [], $p));

        // --- Perapi gambar (GambarTtd): foto berlatar abu-abu, gambar gelap, PNG transparan, gambar besar ---
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $foto = imagecreatetruecolor(1600, 1200);
        imagefill($foto, 0, 0, imagecolorallocate($foto, 205, 203, 198)); // kertas difoto: abu-abu kekuningan
        imagesetthickness($foto, 14);
        imageline($foto, 500, 700, 1100, 500, imagecolorallocate($foto, 30, 40, 110));
        imagejpeg($foto, $tmp . 'zzujilp_foto.jpg', 90);
        $h1 = \App\Libraries\GambarTtd::rapikan($tmp . 'zzujilp_foto.jpg', $tmp . 'zzujilp_foto.png');
        $u1 = @getimagesize($tmp . 'zzujilp_foto.png') ?: [0, 0];
        $g1 = @imagecreatefrompng($tmp . 'zzujilp_foto.png');
        $this->cek('perapi: foto 1600×1200 berlatar abu-abu → dipotong ke goresan, latar abu-abu jadi transparan', $h1['ok'] && ! empty($h1['dipotong']) && $u1[0] > 300 && $u1[0] < 600 && $u1[1] < 250
            && $g1 && (imagecolorat($g1, 2, 2) >> 24 & 0x7F) === 127 && (imagecolorat($g1, intdiv($u1[0], 2), intdiv($u1[1], 2)) >> 24 & 0x7F) < 40, json_encode($h1) . ' ' . $u1[0] . '×' . $u1[1]);
        $gelap = imagecreatetruecolor(300, 200);
        imagefill($gelap, 0, 0, imagecolorallocate($gelap, 20, 20, 20));
        imagepng($gelap, $tmp . 'zzujilp_gelap.png');
        $h2 = \App\Libraries\GambarTtd::rapikan($tmp . 'zzujilp_gelap.png', $tmp . 'zzujilp_gelap2.png');
        $this->cek('perapi: gambar berlatar gelap (bukan kertas) → tidak dipotong, ukuran tetap', $h2['ok'] && empty($h2['dipotong']) && ($h2['lebar'] ?? 0) === 300 && ($h2['tinggi'] ?? 0) === 200);
        $bening = imagecreatetruecolor(800, 400);
        imagealphablending($bening, false);
        imagesavealpha($bening, true);
        imagefill($bening, 0, 0, imagecolorallocatealpha($bening, 0, 0, 0, 127));
        imagesetthickness($bening, 6);
        imageline($bening, 300, 250, 500, 150, imagecolorallocatealpha($bening, 10, 10, 10, 0));
        imagepng($bening, $tmp . 'zzujilp_bening.png');
        $h3 = \App\Libraries\GambarTtd::rapikan($tmp . 'zzujilp_bening.png', $tmp . 'zzujilp_bening2.png');
        $this->cek('perapi: PNG tanpa latar (transparan) → tetap dipotong ke goresannya', $h3['ok'] && ! empty($h3['dipotong']) && ($h3['lebar'] ?? 999) < 260 && ($h3['tinggi'] ?? 999) < 160, json_encode($h3));
        $besar = imagecreatetruecolor(3000, 1000);
        imagefill($besar, 0, 0, imagecolorallocate($besar, 255, 255, 255));
        imagesetthickness($besar, 20);
        imageline($besar, 50, 900, 2950, 100, imagecolorallocate($besar, 0, 0, 0));
        imagepng($besar, $tmp . 'zzujilp_besar.png');
        $h4 = \App\Libraries\GambarTtd::rapikan($tmp . 'zzujilp_besar.png', $tmp . 'zzujilp_besar2.png');
        $this->cek('perapi: gambar 3000 px → diperkecil (sisi terpanjang ≤ 1200 px)', $h4['ok'] && ($h4['lebar'] ?? 9999) <= 1200 && ($h4['tinggi'] ?? 9999) <= 1200, json_encode($h4));
        foreach (['zzujilp_foto.jpg', 'zzujilp_foto.png', 'zzujilp_gelap.png', 'zzujilp_gelap2.png', 'zzujilp_bening.png', 'zzujilp_bening2.png', 'zzujilp_besar.png', 'zzujilp_besar2.png'] as $n) {
            @unlink($tmp . $n);
        }

        // --- Tanda tangan Waka Hubin: kotak 5,0 × 2,2 cm, di tengah ruang tanda tangan ---
        $lebar = imagecreatetruecolor(900, 300);
        imagefill($lebar, 0, 0, imagecolorallocate($lebar, 255, 255, 255));
        imagesetthickness($lebar, 8);
        imageline($lebar, 60, 220, 840, 90, imagecolorallocate($lebar, 15, 25, 120));
        imagepng($lebar, $tmp . 'zzujilp_hubin.png');
        $rh = PklSurat::simpanTtd($tmp . 'zzujilp_hubin.png', (int) filesize($tmp . 'zzujilp_hubin.png'));
        @unlink($tmp . 'zzujilp_hubin.png');
        $ih = PklSurat::infoTtd((new PklPengaturanModel())->ambil());
        $xml = $ih !== null ? PklDocx::gambarTtd(PklSurat::REL_TTD, $ih['cx'], $ih['cy'], offsetAtas: $ih['atas']) : '';
        $this->cek('TTD Waka Hubin: dirapikan, dimuat dalam kotak 5,0 × 2,2 cm, posisi di tengah ruang 4 baris', $rh['ok'] && $ih !== null && $ih['cx'] <= 1800000 && $ih['cy'] <= 792000
            && ($ih['cx'] === 1800000 || $ih['cy'] === 792000) && $ih['atas'] === intdiv(702000 - $ih['cy'], 2)
            && str_contains($xml, '<wp:positionV relativeFrom="paragraph"><wp:posOffset>' . $ih['atas'] . '</wp:posOffset>'), json_encode($ih));

        // --- Gambar LAMA (diunggah sebelum ada perapi): dirapikan oleh migrasi, waktu ubah dipertahankan ---
        foreach (['ttd_hubin.png', 'ttd_hubin.jpg', 'ttd_hubin_asli.png', 'ttd_hubin_asli.jpg'] as $n) {
            @unlink(PklSurat::dirBerkas() . $n);
        }
        $lama = imagecreatetruecolor(800, 600);
        imagefill($lama, 0, 0, imagecolorallocate($lama, 255, 255, 255));
        imagesetthickness($lama, 6);
        imageline($lama, 350, 320, 450, 280, imagecolorallocate($lama, 0, 0, 90));
        imagepng($lama, PklSurat::dirBerkas() . 'ttd_hubin.png');
        $waktu = strtotime('2026-10-01 08:00:00');
        touch(PklSurat::dirBerkas() . 'ttd_hubin.png', $waktu);
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['ttd_hubin' => 'ttd_hubin.png']);
        $sidikSebelum = PklSurat::sidik(['perusahaan_nama' => 'X'], [], (new PklPengaturanModel())->ambil());
        $ok1 = PklSurat::rapikanTersimpan('ttd_hubin');
        clearstatcache();
        $ul  = @getimagesize(PklSurat::dirBerkas() . 'ttd_hubin.png') ?: [0, 0];
        $ok2 = PklSurat::rapikanTersimpan('ttd_hubin');
        $this->cek('gambar lama dirapikan (migrasi): dipotong, asli disimpan, waktu ubah & sidik "perlu cetak ulang" tetap, tidak dua kali', $ok1 && ! $ok2 && $ul[0] > 0 && $ul[0] < 200
            && is_file(PklSurat::dirBerkas() . 'ttd_hubin_asli.png') && filemtime(PklSurat::dirBerkas() . 'ttd_hubin.png') === $waktu
            && PklSurat::sidik(['perusahaan_nama' => 'X'], [], (new PklPengaturanModel())->ambil()) === $sidikSebelum, $ul[0] . '×' . $ul[1]);
        $this->cek('pesan unggah gagal: tanpa berkas → pesan bawaan', PklSurat::pesanUnggahGagal(null, 'Pilih gambar dulu.') === 'Pilih gambar dulu.');

        // Hak (bawaan: hanya Admin) + halaman.
        $this->setelPeranBawaan();
        $this->cek('hak bawaan: hanya Admin; Waka Hubin tetap membuka halaman Tanda Tangan & pratinjau, tapi tidak bisa mengunggah',
            PklHak::boleh('admin', 'ttd_kepsek') && ! PklHak::boleh('hubin', 'ttd_kepsek') && ! PklHak::boleh('operator', 'ttd_kepsek')
            && HakAkses::boleh('hubin', 'admin/pkl/ttd') && HakAkses::boleh('hubin', 'admin/pkl/ttd-kepsek/gambar') && ! HakAkses::boleh('hubin', 'admin/pkl/ttd-kepsek', 'POST'));
        $html = $this->halaman(PklBerkas::class, 'admin', 'ttd');
        $this->cek('halaman Tanda Tangan (Admin): dua bagian + formulir unggah Kepala Sekolah', str_contains($html, 'Tanda tangan + stempel Kepala Sekolah') && str_contains($html, 'admin/pkl/ttd-kepsek') && str_contains($html, 'name="ttd_kepsek"'));
        $html = $this->halaman(PklBerkas::class, 'hubin', 'ttd');
        $this->cek('halaman Tanda Tangan (Waka Hubin): bagian Kepala Sekolah terkunci', ! str_contains($html, 'name="ttd_kepsek"') && str_contains($html, 'name="ttd"') && str_contains($html, 'Diubah oleh Admin'));
        $r = $this->panggil(PklBerkas::class, 'hubin', 'hapusTtdKepsek');
        $this->cek('hapus oleh peran tanpa hak → ditolak, gambar tetap ada', $r instanceof RedirectResponse && str_contains((string) session('error'), 'Hak Akses') && PklSurat::infoTtdKepsek((new PklPengaturanModel())->ambil()) !== null);
        $r = $this->panggil(PklBerkas::class, 'admin', 'hapusTtdKepsek');
        $p = (new PklPengaturanModel())->ambil();
        $this->cek('hapus oleh Admin → berkas & kolom kosong, surat kembali memakai tanda tangan bawaan', ! is_file(PklSurat::dirBerkas() . 'ttd_kepsek.png') && ($p['ttd_kepsek'] ?? null) === null && PklSurat::gantiGambar($p) === []);
    }

    // =================================================================
    // Pemulihan
    // =================================================================

    private function simpanAsli(): void
    {
        $this->simpanPeranAsli();
        if ($this->kepsekAsli === null) {
            $this->kepsekAsli = [];
            foreach (['ttd_kepsek.png', 'ttd_kepsek.jpg', 'ttd_kepsek_asli.png', 'ttd_kepsek_asli.jpg', 'ttd_hubin.png', 'ttd_hubin.jpg', 'ttd_hubin_asli.png', 'ttd_hubin_asli.jpg'] as $n) {
                if (is_file(PklSurat::dirBerkas() . $n)) {
                    $this->kepsekAsli[$n] = (string) file_get_contents(PklSurat::dirBerkas() . $n);
                }
            }
        }
        $this->pengaturanAsli ??= (function () {
            $r = (new PklPengaturanModel())->ambil();
            unset($r['id']);

            return $r;
        })();
    }

    /** Pengaturan netral: aturan ACC bawaan, form lapor terbuka, tanpa batas periode (tanpa peringatan). */
    private function netralkan(): void
    {
        $this->db->table('pkl_pengaturan')->where('id', 1)->update([
            'hak_peran' => null, 'surat_perlu_acc' => null, 'lapor_buka' => 1,
            'mulai_paling_awal' => null, 'selesai_paling_akhir' => null, 'durasi_min_hari' => 0, 'durasi_maks_hari' => 0,
        ]);
        $this->setelPeranBawaan();
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete(); // CASCADE: siswa surat & riwayat; pkl_lapor.surat_id → NULL
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_map('intval', array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete(); // CASCADE: anggota, riwayat, laporan
            }
            $throttle = new LoginThrottle();
            foreach ($ids as $id) {
                $throttle->clear('pkllapor:' . $id, self::IP);
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujilp', 'after')->delete();
        $this->db->table('audit_log')->like('deskripsi', self::OLEH)->delete();
        session()->remove(['pkl_lapor_izin', 'admin', 'error', 'success', 'errors', 'pkl_lapor_selesai']);

        if ($this->kepsekAsli !== null) { // berkas gambar tanda tangan dikembalikan seperti sebelum uji
            foreach (['ttd_kepsek.png', 'ttd_kepsek.jpg', 'ttd_kepsek_asli.png', 'ttd_kepsek_asli.jpg', 'ttd_hubin.png', 'ttd_hubin.jpg', 'ttd_hubin_asli.png', 'ttd_hubin_asli.jpg'] as $n) {
                @unlink(PklSurat::dirBerkas() . $n);
            }
            foreach ($this->kepsekAsli as $n => $isi) {
                file_put_contents(PklSurat::dirBerkas() . $n, $isi);
            }
            $this->kepsekAsli = null;
        }
        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        $this->pulihkanPeran();
        PklHak::lupakan();
    }
}
