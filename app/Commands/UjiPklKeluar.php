<?php

namespace App\Commands;

use App\Controllers\Admin\Pkl as AdminPkl;
use App\Controllers\Admin\PklAlasan as AdminPklAlasan;
use App\Controllers\Admin\SuratLapor;
use App\Controllers\Admin\SuratPkl as AdminSuratPkl;
use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\HakPeran;
use App\Libraries\LoginThrottle;
use App\Libraries\PklAjuan;
use App\Libraries\PklAlasan;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\PklKeluar;
use App\Libraries\PklLapor;
use App\Libraries\PklPeringatan;
use App\Libraries\PklPrakerin;
use App\Libraries\PklSurat;
use App\Libraries\PklWaKeputusan;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPkl;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Uji REVISI KLIEN PKL 2026-10-20 (docs/DESAIN-PKL.md bagian "Siswa dikeluarkan, ditolak perusahaan, template alasan"):
 *   1. Template alasan (PklAlasan): isi awal dari sekolah, hak kelola, tambah/ubah/hapus/urut, validasi, jendela;
 *   2. Keluarkan siswa (PklKeluar): hak per status, validasi, jejak, kunci lepas, pengaju pindah, Surat Izin (ditolak perusahaan
 *      tetap arsip vs jenis lain perlu cetak ulang), riwayat & peringatan saat mengajukan lagi;
 *   3. Ditolak perusahaan: hak ACC, satu langkah dari Disetujui, label, siswa bebas, ACC ulang mengosongkan sumber;
 *   4. Status Siswa / daftar nama / Data Prakerin / Excel: siswa yang dikeluarkan terhitung "pernah ditolak";
 *   5. Lapor Diterima: semua / sebagian / semua ditolak — validasi, proses staf (keluarkan dulu → Balasan hanya yang diterima),
 *      konfirmasi ditolak, penjaga Balasan manual & formulir "Periksa & ubah dulu";
 *   6. Kabar WhatsApp siswa yang dikeluarkan + perbaikan galat JSON tandai kabar keputusan;
 *   7. Halaman web (detail, Template Alasan, Riwayat, Status Siswa) + gerbang alamat;
 *   8. API aplikasi (api/v1/pkl/… & api/v1/surat/balasan/laporan).
 *
 * Data uji bertanda "ZZUJI KL" (nama), NIS "ZZUJIKL…", IP "10.9.7.9" — dibuat & dihapus sendiri; Pengaturan PKL, tabel peran, dan
 * template alasan dipulihkan ke isi semula di akhir (juga bila uji berhenti di tengah, pada jalan berikutnya).
 *
 * Jalankan:  php spark dev:uji-pkl-keluar
 */
class UjiPklKeluar extends BaseCommand
{
    use UjiPeranAman;

    protected $group       = 'dev';
    protected $name        = 'dev:uji-pkl-keluar';
    protected $description = 'Uji revisi PKL 2026-10-20: keluarkan siswa, ditolak perusahaan, lapor sebagian/ditolak, template alasan, riwayat siswa, API.';

    private const NIS  = 'ZZUJIKL';
    private const IP   = '10.9.7.9';
    private const OLEH = 'ZZUJI KL';
    private const PT   = 'ZZUJIKL Perusahaan ';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    /** @var list<array<string, mixed>>|null isi tabel pkl_alasan sebelum uji */
    private ?array $alasanAsli = null;
    private int $kelasId = 0;
    /** @var array<int, int> nomor => id siswa uji */
    private array $s = [];
    /** @var array<string, int> huruf => id ajuan uji */
    private array $aj = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
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
        if (! PklKeluar::tabelAda() || ! PklAlasan::tabelAda() || ! PklLapor::kolomHasil()) {
            CLI::write('Tabel/kolom revisi belum ada. Jalankan "php spark migrate" dulu.', 'red');

            return EXIT_ERROR;
        }
        $this->bersihkan();
        $this->simpanAsli();
        $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('ada kelas sebagai bahan uji', false, 'tabel kelas kosong');

            return EXIT_ERROR;
        }
        $this->kelasId = (int) $k['id'];
        $this->netralkan();
        $this->siapkanData();

        $this->ujiAlasan();
        $this->ujiKeluarkan();
        $this->ujiDitolakPerusahaan();
        $this->ujiStatus();
        $this->ujiLapor();
        $this->ujiWa();
        $this->ujiGabung();
        $this->ujiHalaman();
        $this->ujiApi();

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
        for ($n = 1; $n <= 22; $n++) {
            $this->db->table('siswa')->insert([
                'nis' => self::NIS . $n, 'nama' => self::OLEH . ' ' . $n, 'jenis_kelamin' => 'P', 'kelas_id' => $this->kelasId,
                'no_hp' => $this->hp($n), 'status' => 'aktif', 'tanggal_lahir' => '2010-05-17', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->s[$n] = (int) $this->db->insertID();
        }
        // A menunggu: 1 (pengaju) + 2 + 3 · B disetujui: 4 (pengaju) + 5 + 6 + 7 · C disetujui: 8 + 9 · D disetujui: 10 + 11 + 12 ·
        // E disetujui: 13 · H disetujui: 14 + 15 (untuk API). Siswa 16 tanpa ajuan.
        $this->aj['A'] = $this->ajuan($this->s[1], 'A', [$this->s[2], $this->s[3]], 'menunggu');
        $this->aj['B'] = $this->ajuan($this->s[4], 'B', [$this->s[5], $this->s[6], $this->s[7]]);
        $this->aj['C'] = $this->ajuan($this->s[8], 'C', [$this->s[9]]);
        $this->aj['D'] = $this->ajuan($this->s[10], 'D', [$this->s[11], $this->s[12]]);
        $this->aj['E'] = $this->ajuan($this->s[13], 'E');
        $this->aj['H'] = $this->ajuan($this->s[14], 'H', [$this->s[15]]);
        $this->aj['J'] = $this->ajuan($this->s[17], 'J', [$this->s[18]]);
        $this->cek('bahan uji: 7 ajuan dibuat', count(array_filter($this->aj)) === 7, json_encode($this->aj));
    }

    /** @param list<int> $teman */
    private function ajuan(int $pengaju, string $huruf, array $teman = [], string $status = 'disetujui', ?string $namaPt = null): int
    {
        $nama = $namaPt ?? self::PT . $huruf;
        $data = [
            'perusahaan_nama' => $nama, 'perusahaan_norm' => PklForm::normPerusahaan($nama), 'perusahaan_alamat' => 'Jl. Uji Keluar No. 2',
            'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => '0218888888', 'kontak_nama' => null, 'kontak_jabatan' => null,
            'tanggal_mulai' => null, 'tanggal_selesai' => null, 'hp' => $this->hpPengaju($pengaju), 'tanggal_lahir' => null, 'teman' => [],
        ];
        $anggota = [['siswa_id' => $pengaju, 'kelas_id' => $this->kelasId, 'peran' => 'pengaju', 'hp' => $this->hpPengaju($pengaju)]];
        foreach ($teman as $t) {
            $anggota[] = ['siswa_id' => $t, 'kelas_id' => $this->kelasId, 'peran' => 'teman', 'hp' => '0857' . str_pad((string) $t, 8, '0', STR_PAD_LEFT)];
        }
        $r = (new PklAjuan())->kirimBaru($data, $anggota, ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP,
            'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => $status, 'tahun_ajaran' => '2031/2032']);

        return (int) ($r['id'] ?? 0);
    }

    private function hp(int $n): string
    {
        return '0812333300' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    private function hpPengaju(int $siswaId): string
    {
        return '0813' . str_pad((string) $siswaId, 8, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function ctx(string $peran, string $saluran = 'web'): array
    {
        return ['oleh' => self::OLEH . ' ' . $peran, 'admin_id' => null, 'peran' => $peran, 'ip' => self::IP, 'saluran' => $saluran];
    }

    private function aktif(int $nSiswa): ?array
    {
        return (new PklPengajuanModel())->aktifMilik($this->s[$nSiswa]);
    }

    private function peran(string $huruf, int $nSiswa): ?string
    {
        $r = $this->db->table('pkl_anggota')->select('peran')->where('pengajuan_id', $this->aj[$huruf])->where('siswa_id', $this->s[$nSiswa])->get()->getRowArray();

        return $r['peran'] ?? null;
    }

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
    // 1. Template alasan
    // =================================================================

    private function ujiAlasan(): void
    {
        $this->bagian('Template alasan: isi awal, hak, tambah/ubah/hapus/urut');
        $lib = new PklAlasan();
        // Isi awal dari "Bank Kalimat Alasan" sekolah (dipulihkan di akhir; di sini dicek dari salinan sebelum uji).
        $asli  = array_column($this->alasanAsli ?? [], 'judul');
        $this->cek('isi awal memuat kalimat sekolah (mis. "Administrasi belum lunas", "Nama perusahaan salah")', in_array('Administrasi belum lunas', $asli, true) && in_array('Nama perusahaan salah', $asli, true), json_encode(array_slice($asli, 0, 5)));
        $this->cek('hak kelola: Hubin & Admin boleh, Operator tidak (bawaan)', PklAlasan::bolehKelola('hubin') && PklAlasan::bolehKelola('admin') && ! PklAlasan::bolehKelola('operator'));
        $this->cek('gerbang alamat: admin/pkl/alasan menuntut hak "alasan"', PklHak::hakUntukAlamat('admin/pkl/alasan') === 'alasan' && ! HakPeran::boleh('operator', 'admin/pkl/alasan') && HakPeran::boleh('hubin', 'admin/pkl/alasan', 'POST'));

        $r = $lib->simpan(['jenis' => 'tolak', 'judul' => 'ZZ uji', 'isi' => 'Alasan uji yang cukup panjang.'], null, $this->ctx('operator'));
        $this->cek('Operator menambah template → 403', ! $r['ok'] && $r['http'] === 403, $r['pesan']);
        $r = $lib->simpan(['jenis' => 'ngawur', 'judul' => 'Z', 'isi' => 'abc'], null, $this->ctx('hubin'));
        $this->cek('validasi: jenis, judul, isi', ! $r['ok'] && $r['http'] === 422 && isset($r['galat']['jenis'], $r['galat']['judul'], $r['galat']['isi']), json_encode($r['galat'] ?? []));
        $r = $lib->simpan(['jenis' => 'tolak', 'judul' => 'Administrasi belum lunas', 'isi' => 'Kalimat ganda untuk uji.'], null, $this->ctx('hubin'));
        $this->cek('judul kembar di kelompok sama ditolak', ! $r['ok'] && isset($r['galat']['judul']));
        $r  = $lib->simpan(['jenis' => 'tolak', 'judul' => 'ZZ Uji Satu', 'isi' => 'Kalimat uji satu yang dibaca siswa.'], null, $this->ctx('hubin'));
        $id = (int) ($r['id'] ?? 0);
        $daftar = array_column($lib->daftar('tolak'), 'judul');
        $this->cek('Hubin menambah template → paling bawah kelompoknya', $r['ok'] && end($daftar) === 'ZZ Uji Satu', json_encode($daftar));
        $r = $lib->simpan(['jenis' => 'keluar', 'judul' => 'ZZ Uji Dua', 'isi' => 'Kalimat uji dua (diubah).'], $id, $this->ctx('admin'));
        $baris = $this->db->table('pkl_alasan')->where('id', $id)->get()->getRowArray();
        $this->cek('ubah (Admin) + pindah kelompok', $r['ok'] && $baris['jenis'] === 'keluar' && $baris['judul'] === 'ZZ Uji Dua' && $baris['isi'] === 'Kalimat uji dua (diubah).');
        $ids = array_column($lib->daftar('keluar'), 'id');
        $lib->geser($id, -1, $this->ctx('hubin'));
        $ids2 = array_column($lib->daftar('keluar'), 'id');
        $this->cek('geser naik satu langkah', array_search($id, $ids2, true) === array_search($id, $ids, true) - 1, json_encode([$ids, $ids2]));
        $r = $lib->urutkan('keluar', array_slice($ids2, 1), $this->ctx('hubin'));
        $this->cek('urutkan dengan daftar tidak lengkap → 409', ! $r['ok'] && $r['http'] === 409);
        $r = $lib->urutkan('keluar', array_reverse($ids2), $this->ctx('hubin'));
        $this->cek('urutkan lengkap → tersimpan', $r['ok'] && array_column($lib->daftar('keluar'), 'id') === array_reverse($ids2));
        $grup = $lib->untukDialog('keluarkan');
        $this->cek('jendela "keluarkan" = kelompok keluar + tolak', array_column($grup, 'jenis') === ['keluar', 'tolak'] && $grup[0]['item'] !== []);
        $this->cek('jendela "batal_acc" = kembalikan + tolak; "lapor" = lapor', array_column($lib->untukDialog('batal_acc'), 'jenis') === ['kembalikan', 'tolak'] && array_column($lib->untukDialog('lapor'), 'jenis') === ['lapor']);
        $r = $lib->hapus($id, $this->ctx('hubin'));
        $this->cek('hapus template', $r['ok'] && $this->db->table('pkl_alasan')->where('id', $id)->countAllResults() === 0);
        $this->cek('Audit Log mencatat template', $this->db->table('audit_log')->where('tabel', 'pkl_alasan')->like('deskripsi', 'ZZ Uji')->countAllResults() >= 3);
    }

    // =================================================================
    // 2. Keluarkan siswa
    // =================================================================

    private function ujiKeluarkan(): void
    {
        $this->bagian('Keluarkan siswa: hak, validasi, jejak, pengaju pindah, Surat Izin, riwayat');
        $lib = new PklKeluar();
        $p   = (new PklPengaturanModel())->ambil();

        $this->cek('hak: menunggu → Operator & Hubin (ubah); disetujui → hanya pemegang ACC',
            PklKeluar::bolehKeluarkan('operator', 'menunggu') && PklKeluar::bolehKeluarkan('hubin', 'menunggu') && ! PklKeluar::bolehKeluarkan('operator', 'disetujui')
            && PklKeluar::bolehKeluarkan('hubin', 'disetujui') && ! PklKeluar::bolehKeluarkan('hubin', 'ditolak'));
        $r = $lib->keluarkan($this->aj['B'], ['siswa_ids' => [$this->s[6]], 'jenis' => 'administrasi', 'catatan' => 'Administrasi belum lunas.'], $this->ctx('operator'), $p);
        $this->cek('Operator mengeluarkan dari ajuan DISETUJUI → 403 + Audit', ! $r['ok'] && $r['http'] === 403 && $this->aktif(6) !== null, $r['pesan']);
        $r = $lib->keluarkan($this->aj['A'], ['siswa_ids' => [], 'jenis' => 'x', 'catatan' => 'abc'], $this->ctx('operator'), $p);
        $this->cek('validasi: siswa, jenis, alasan', ! $r['ok'] && $r['http'] === 422 && isset($r['galat']['siswa_ids'], $r['galat']['jenis'], $r['galat']['catatan']), json_encode($r['galat'] ?? []));
        $r = $lib->keluarkan($this->aj['A'], ['siswa_ids' => [$this->s[1], $this->s[2], $this->s[3]], 'jenis' => 'administrasi', 'catatan' => 'Semua belum lunas administrasi.'], $this->ctx('operator'), $p);
        $this->cek('mengeluarkan SEMUA siswa ditolak (minimal satu tersisa)', ! $r['ok'] && $r['kode'] === 'semua' && $this->aktif(1) !== null);
        $r = $lib->keluarkan($this->aj['A'], ['siswa_ids' => [$this->s[16]], 'jenis' => 'lainnya', 'catatan' => 'Bukan anggota kelompok.'], $this->ctx('operator'), $p);
        $this->cek('siswa bukan anggota ditolak', ! $r['ok'] && $r['kode'] === 'bukan_anggota');

        // A (menunggu): Operator mengeluarkan siswa 2 (administrasi).
        $r = $lib->keluarkan($this->aj['A'], ['siswa_ids' => [$this->s[2]], 'jenis' => 'administrasi', 'catatan' => 'Administrasi siswa ini belum lunas.'], $this->ctx('operator'), $p);
        $k = $this->db->table('pkl_anggota_keluar')->where('pengajuan_id', $this->aj['A'])->where('siswa_id', $this->s[2])->get()->getRowArray();
        $this->cek('Operator mengeluarkan siswa 2 dari ajuan menunggu → bebas, jejak tersimpan (peran, kelas, HP, jenis, alasan, status)', $r['ok'] && $this->aktif(2) === null && $k !== null
            && $k['peran'] === 'teman' && (int) $k['kelas_id'] === $this->kelasId && $k['jenis'] === 'administrasi' && $k['status_ajuan'] === 'menunggu' && $k['hp'] !== null && $k['sumber'] === 'staf', $r['pesan'] . json_encode($k));
        $this->cek('… riwayat ajuan "keluarkan" + Audit Log + anggota tersisa 2', $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['A'])->where('aksi', 'keluarkan')->countAllResults() === 1
            && $this->db->table('audit_log')->where('record_id', $this->aj['A'])->like('deskripsi', 'dikeluarkan (Administrasi belum lunas)')->countAllResults() === 1
            && $this->db->table('pkl_anggota')->where('pengajuan_id', $this->aj['A'])->countAllResults() === 2);
        $this->cek('periksaKonsistensi tetap bersih', (new PklAjuan())->periksaKonsistensi() === []);

        // B (disetujui) + Surat Izin terbit: Hubin mengeluarkan PENGAJU 4 + 6 (ditolak perusahaan) → pengaju pindah ke 5, surat tetap arsip.
        $model = new PklPengajuanModel();
        $this->db->table('pkl_surat')->insert(['pengajuan_id' => $this->aj['B'], 'tahun' => 2098, 'urut' => 980001, 'nomor' => 'ZZUJIKL/B', 'tanggal_surat' => date('Y-m-d'),
            'cetak_ke' => 1, 'sidik' => PklSurat::sidik($model->detail($this->aj['B']), $model->anggotaDetail($this->aj['B']), $p), 'created_at' => date('Y-m-d H:i:s')]);
        $r = $lib->keluarkan($this->aj['B'], ['siswa_ids' => [$this->s[4], $this->s[6]], 'jenis' => 'ditolak_perusahaan', 'catatan' => 'Perusahaan hanya menerima 2 siswa.'], $this->ctx('hubin'), $p);
        $suratB = (new PklSurat())->surat($this->aj['B']);
        $this->cek('Hubin mengeluarkan pengaju 4 + 6 (ditolak perusahaan) → keduanya bebas, pengaju pindah ke siswa 5', $r['ok'] && $this->aktif(4) === null && $this->aktif(6) === null
            && $this->peran('B', 5) === 'pengaju' && $this->peran('B', 7) === 'teman' && str_contains($r['pesan'], 'Pengaju kini'), $r['pesan']);
        $this->cek('… Surat Izin yang sudah terbit TIDAK ditandai perlu cetak ulang (arsip sah)', $suratB !== null
            && PklSurat::sidik($model->detail($this->aj['B']), $model->anggotaDetail($this->aj['B']), $p) === (string) $suratB['sidik']);
        $this->cek('… status ajuan B tetap disetujui, catatan ACC tetap', $model->find($this->aj['B'])['status'] === 'disetujui' && ! empty($model->find($this->aj['B'])['acc_kode']));
        // Jenis lain (administrasi) pada ajuan bersurat → perlu cetak ulang.
        $r = $lib->keluarkan($this->aj['B'], ['siswa_ids' => [$this->s[7]], 'jenis' => 'administrasi', 'catatan' => 'Belum lunas administrasi.'], $this->ctx('hubin'), $p);
        $suratB = (new PklSurat())->surat($this->aj['B']);
        $this->cek('jenis administrasi pada ajuan bersurat → surat ditandai perlu cetak ulang (nomor sama)', $r['ok'] && $suratB['nomor'] === 'ZZUJIKL/B'
            && PklSurat::sidik($model->detail($this->aj['B']), $model->anggotaDetail($this->aj['B']), $p) !== (string) $suratB['sidik']);

        // Siswa yang dikeluarkan mengajukan lagi → riwayat + peringatan.
        $this->aj['F'] = $this->ajuan($this->s[4], 'B', [], 'menunggu'); // ke perusahaan YANG SAMA dengan B
        $this->aj['G'] = $this->ajuan($this->s[2], 'G', [], 'menunggu'); // ke perusahaan lain
        $this->cek('siswa yang dikeluarkan bisa langsung mengajukan ajuan baru', $this->aj['F'] > 0 && $this->aj['G'] > 0);
        $w = static fn (int $id, string $tingkat): array => array_values(array_filter(PklPeringatan::untuk($model->detail($id), $model->anggotaDetail($id), (new PklPengaturanModel())->ambil()),
            static fn (array $x): bool => $x['tingkat'] === $tingkat && str_contains($x['teks'], 'pernah')));
        $wF = $w($this->aj['F'], 'awas');
        $this->cek('peringatan PERIKSA: siswa 4 dulu ditolak perusahaan YANG SAMA', count($wF) === 1 && str_contains($wF[0]['teks'], 'DITOLAK perusahaan ini') && str_contains($wF[0]['teks'], PklPengajuanModel::kode($this->aj['B'])), json_encode($wF));
        $wG = $w($this->aj['G'], 'info');
        $this->cek('peringatan INFO: siswa 2 pernah dikeluarkan (administrasi) di tempat lain', count($wG) === 1 && str_contains($wG[0]['teks'], '1×') && $w($this->aj['G'], 'awas') === [], json_encode($wG));
        $t = $lib->tolakSiswa([$this->s[4], $this->s[2], $this->s[16]], $this->aj['F']);
        $this->cek('tolakSiswa: siswa 4 → ditolak perusahaan (B), siswa 2 → dikeluarkan administrasi (A), siswa 16 kosong',
            ($t[$this->s[4]][0]['sumber'] ?? '') === 'perusahaan' && ($t[$this->s[4]][0]['ajuan_id'] ?? 0) === $this->aj['B']
            && ($t[$this->s[2]][0]['jenis'] ?? '') === 'administrasi' && ! isset($t[$this->s[16]]), json_encode($t));
        $rw = $lib->riwayatSiswa($this->s[4]);
        $this->cek('riwayatSiswa(4): ajuan aktif F + dikeluarkan dari B, jumlah ditolak 1', $rw !== null && $rw['aktif'] !== null && $rw['aktif']['ajuan_id'] === $this->aj['F']
            && $rw['jumlah_ditolak'] === 1 && count($rw['riwayat']) === 2 && in_array('keluar', array_column($rw['riwayat'], 'jenis'), true), json_encode($rw));
        // "Pindah kelompok" bukan penolakan.
        $r = $lib->keluarkan($this->aj['A'], ['siswa_ids' => [$this->s[3]], 'jenis' => 'pindah', 'catatan' => 'Digabung ke kelompok lain di tempat sama.'], $this->ctx('operator'), $p);
        $this->cek('jenis "pindah kelompok" tercatat tetapi tidak dihitung sebagai penolakan', $r['ok'] && ! isset($lib->tolakSiswa([$this->s[3]])[$this->s[3]])
            && count($lib->riwayatSiswa($this->s[3])['riwayat']) === 1 && $lib->riwayatSiswa($this->s[3])['jumlah_ditolak'] === 0);
    }

    // =================================================================
    // 3. Ditolak perusahaan (semua siswa)
    // =================================================================

    private function ujiDitolakPerusahaan(): void
    {
        $this->bagian('Ditolak perusahaan: hak ACC, satu langkah dari Disetujui, ACC ulang');
        $lib   = new PklKeluar();
        $model = new PklPengajuanModel();
        $r = $lib->ditolakPerusahaan($this->aj['C'], ['catatan' => 'Kuota penuh.'], $this->ctx('operator'));
        $this->cek('Operator (tanpa hak ACC) → 403', ! $r['ok'] && $r['http'] === 403 && $model->find($this->aj['C'])['status'] === 'disetujui');
        $r = $lib->ditolakPerusahaan($this->aj['C'], ['catatan' => 'abc'], $this->ctx('hubin'));
        $this->cek('alasan wajib (minimal 5 huruf)', ! $r['ok'] && $r['http'] === 422);
        $r = $lib->ditolakPerusahaan($this->aj['C'], ['catatan' => 'Perusahaan tidak membuka PKL periode ini.'], $this->ctx('hubin'));
        $c = $model->find($this->aj['C']);
        $this->cek('Hubin: Disetujui → Ditolak perusahaan dalam SATU langkah, siswa 8 & 9 bebas, catatan ACC dikosongkan', $r['ok'] && $c['status'] === 'ditolak' && $c['tolak_sumber'] === 'perusahaan'
            && $this->aktif(8) === null && $this->aktif(9) === null && $c['acc_kode'] === null && $c['catatan_staf'] === 'Perusahaan tidak membuka PKL periode ini.', json_encode($c));
        $this->cek('… riwayat "tolak_perusahaan" + daftar Ditolak memuat tolak_sumber', $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['C'])->where('aksi', 'tolak_perusahaan')->countAllResults() === 1
            && in_array('perusahaan', array_column($model->daftar('ditolak', self::PT . 'C', 0, 50, 1)[0], 'tolak_sumber'), true));
        $r = $lib->ditolakPerusahaan($this->aj['C'], ['catatan' => 'Ulang lagi.'], $this->ctx('hubin'));
        $this->cek('ajuan yang sudah ditolak tidak bisa ditolak lagi (409)', ! $r['ok'] && $r['http'] === 409);
        $t = $lib->tolakSiswa([$this->s[8]]);
        $this->cek('riwayat siswa 8: ditolak perusahaan', ($t[$this->s[8]][0]['sumber'] ?? '') === 'perusahaan' && ($t[$this->s[8]][0]['keterangan'] ?? '') === 'Ditolak perusahaan');
        // ACC ulang (hidupkan) → tolak_sumber kosong lagi.
        $h = (new \App\Libraries\PklKeputusan())->putuskan($this->aj['C'], 'acc', ['paham' => '1'], $this->ctx('hubin'), (new PklPengaturanModel())->ambil());
        $this->cek('ACC ulang ajuan ditolak perusahaan → tolak_sumber dikosongkan', $h['ok'] && $model->find($this->aj['C'])['tolak_sumber'] === null, $h['pesan']);
        $lib->ditolakPerusahaan($this->aj['C'], ['catatan' => 'Perusahaan tidak membuka PKL periode ini.'], $this->ctx('admin'));
        $this->cek('Admin selalu boleh menandai ditolak perusahaan', $model->find($this->aj['C'])['tolak_sumber'] === 'perusahaan');
        // Tolak biasa → sumber sekolah (null).
        $this->cek('Tolak biasa tetap "ditolak sekolah" (tolak_sumber kosong)', $model->find($this->aj['G'])['tolak_sumber'] === null);
    }

    // =================================================================
    // 4. Status siswa, daftar nama, Data Prakerin, Excel
    // =================================================================

    private function ujiStatus(): void
    {
        $this->bagian('Status Siswa, daftar nama form, Data Prakerin');
        $model   = new PklPengajuanModel();
        $tingkat = array_values(array_unique(array_merge(PklPengaturanModel::tingkatBoleh((new PklPengaturanModel())->ambil()), ['X', 'XI', 'XII'])));
        [$rows]  = $model->statusSiswa($tingkat, $this->kelasId, self::OLEH, '', 100, 1);
        $peta    = array_column($rows, null, 'id');
        $this->cek('siswa 6 (dikeluarkan, belum mengajukan lagi) → fase "ditolak", jml_tolak 1', ($peta[$this->s[6]]['fase'] ?? '') === 'ditolak' && (int) ($peta[$this->s[6]]['jml_tolak'] ?? 0) === 1, json_encode($peta[$this->s[6]] ?? null));
        $this->cek('siswa 4 (dikeluarkan lalu mengajukan lagi) → fase menunggu tetapi jml_tolak 1', ($peta[$this->s[4]]['fase'] ?? '') === 'menunggu' && (int) ($peta[$this->s[4]]['jml_tolak'] ?? 0) === 1);
        $this->cek('siswa 3 (pindah kelompok) → tidak dihitung ditolak', (int) ($peta[$this->s[3]]['jml_tolak'] ?? -1) === 0 && ($peta[$this->s[3]]['fase'] ?? '') === 'belum');
        [$rowsT] = $model->statusSiswa($tingkat, $this->kelasId, self::OLEH, 'pernah_ditolak', 100, 1);
        $idsT    = array_map('intval', array_column($rowsT, 'id'));
        $this->cek('saringan "pernah_ditolak": 2, 4, 6, 7, 8, 9 (apa pun status sekarang)', ! array_diff([$this->s[2], $this->s[4], $this->s[6], $this->s[7], $this->s[8], $this->s[9]], $idsT) && ! in_array($this->s[3], $idsT, true), json_encode($idsT));
        $this->cek('ringkasanSiswa memuat jumlah pernah_ditolak', (int) ($model->ringkasanSiswa($tingkat, $this->kelasId)['pernah_ditolak'] ?? 0) >= 6);
        $daftar = array_column($model->daftarSiswaKelas($this->kelasId), 'pernah_ditolak', 'id');
        $this->cek('daftar nama form PKL: siswa dikeluarkan = "pernah ditolak" (boleh mengajukan)', (int) ($daftar[$this->s[6]] ?? 0) === 1 && (int) ($daftar[$this->s[3]] ?? 1) === 0);
        $this->cek('daftar nama form Lapor: siswa 6 = "ajukan_lagi"', $this->statusLapor(6) === 'ajukan_lagi' && $this->statusLapor(16) === 'belum_ajuan');
        $prak = (new PklPrakerin())->data([['id' => $this->kelasId, 'nama' => 'uji', 'nama_kelas' => 'uji', 'tingkat' => 'XI']]);
        $ket  = '';
        foreach ($prak as $kelas) {
            foreach ($kelas['siswa'] ?? [] as $x) {
                if ($x['nama'] === self::OLEH . ' 6') {
                    $ket = (string) $x['catatan'];
                }
            }
        }
        $this->cek('Data Prakerin: keterangan siswa 6 = dikeluarkan dari ajuan ke B', str_contains($ket, 'Dikeluarkan dari ajuan ke ' . self::PT . 'B'), $ket);
    }

    private function statusLapor(int $n): string
    {
        foreach ((new PklLapor())->siswaKelas($this->kelasId) as $r) {
            if ($r['id'] === $this->s[$n]) {
                return $r['status'];
            }
        }

        return '';
    }

    // =================================================================
    // 5. Lapor diterima: semua / sebagian / ditolak
    // =================================================================

    private function laporkan(int $nSiswa, string $huruf, array $isi): array
    {
        $lib = new PklLapor();
        (new LoginThrottle())->clear('pkllapor:' . $this->s[$nSiswa], self::IP);
        $c = $lib->cek($this->s[$nSiswa], $this->hp($nSiswa), self::IP);
        if (! $c['ok']) {
            return $c;
        }
        $th = (int) date('Y') + 1;

        return $lib->kirim($isi + ['ajuan_id' => $this->aj[$huruf], 'tanggal_mulai' => $th . '-07-01', 'tanggal_selesai' => $th . '-09-30'], self::IP);
    }

    private function ujiLapor(): void
    {
        $this->bagian('Lapor Diterima: semua / sebagian / semua ditolak');
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
        $lib = new PklLapor();
        $c   = $lib->cek($this->s[10], $this->hp(10), self::IP);
        $this->cek('ringkasan untuk siswa: anggota ber-siswa_id + hasil_ada', $c['ok'] && isset($c['data']['anggota'][0]['siswa_id']) && $c['data']['hasil_ada'] === true);

        $r = $this->laporkan(13, 'E', ['hasil' => 'sebagian', 'diterima' => [$this->s[13]]]);
        $this->cek('kelompok 1 siswa tidak boleh "sebagian"', ! $r['ok'] && isset($r['galat']['hasil']));
        $r = $this->laporkan(10, 'D', ['hasil' => 'sebagian']);
        $this->cek('"sebagian" tanpa centang ditolak', ! $r['ok'] && isset($r['galat']['diterima']));
        $r = $this->laporkan(10, 'D', ['hasil' => 'sebagian', 'diterima' => [$this->s[10], $this->s[11], $this->s[12]]]);
        $this->cek('"sebagian" dengan semua tercentang ditolak', ! $r['ok'] && isset($r['galat']['diterima']));
        $r = $this->laporkan(10, 'D', ['hasil' => 'sebagian', 'diterima' => [$this->s[10], $this->s[16]]]);
        $this->cek('"sebagian" dengan siswa bukan anggota ditolak', ! $r['ok'] && isset($r['galat']['diterima']));
        $r = $this->laporkan(10, 'D', ['hasil' => 'ngawur']);
        $this->cek('hasil tak dikenal ditolak', ! $r['ok'] && isset($r['galat']['hasil']));
        $r = $this->laporkan(10, 'D', ['hasil' => 'sebagian', 'diterima' => [$this->s[11], $this->s[10]]]);
        $l = $lib->untukAjuan($this->aj['D']);
        $this->cek('lapor "sebagian" (10 & 11 diterima, 12 tidak) tersimpan', $r['ok'] && $l['hasil'] === 'sebagian' && PklLapor::idDiterima($l) === [$this->s[10], $this->s[11]]
            && $r['data']['ditolak'] === [self::OLEH . ' 12'], json_encode($r));
        $this->cek('siswa TIDAK melepas temannya sendiri: siswa 12 masih terkunci sampai staf memproses', $this->aktif(12) !== null);

        // Jalur lain ditolak untuk laporan sebagian.
        $sp  = new SuratPkl();
        $per = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => SuratPkl::SAPAAN_BAWAAN, 'periode_mulai' => ((int) date('Y') + 1) . '-07-01', 'periode_selesai' => ((int) date('Y') + 1) . '-09-30']);
        $b   = $sp->buat(SuratJenis::BALASAN, $per, [$this->aj['D']], [], $this->ctx('operator'), (new PklPengaturanModel())->ambil());
        $this->cek('Balasan MANUAL untuk ajuan berlaporan "sebagian" ditolak (harus lewat kotak laporan)', ! $b['ok'] && str_contains(implode(' ', $b['gagal']), 'kotak Lapor Diterima'), json_encode($b));
        $rr = $this->panggil(AdminSuratPkl::class, 'operator', 'balasan', ['lapor' => (string) $l['id']]);
        $this->cek('formulir "Periksa & ubah dulu" menolak laporan "sebagian"', $rr instanceof RedirectResponse && str_contains((string) session('error'), 'kotak Lapor Diterima'));
        $html = $this->halaman(SuratLapor::class, 'operator', 'index');
        $this->cek('kotak laporan: label "Sebagian diterima (2 dari 3)", siswa 12 dicoret, tanpa tombol "Periksa & ubah dulu"', str_contains($html, 'Sebagian diterima (2 dari 3)')
            && str_contains($html, 'dikeluarkan saat diproses') && ! str_contains($html, 'admin/surat/balasan?lapor=' . $l['id']));

        $r = $lib->proses((int) $l['id'], true, $this->ctx('operator'), (new PklPengaturanModel())->ambil());
        $surat = (int) ($r['surat_id'] ?? 0);
        $siswaSurat = array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->select('siswa_id')->where('surat_id', $surat)->orderBy('urut')->get()->getResultArray(), 'siswa_id'));
        $k12 = $this->db->table('pkl_anggota_keluar')->where('pengajuan_id', $this->aj['D'])->where('siswa_id', $this->s[12])->get()->getRowArray();
        $this->cek('proses "sebagian": siswa 12 DIKELUARKAN (ditolak perusahaan, dari laporan) lalu Balasan hanya untuk 10 & 11', $r['ok'] && $surat > 0
            && $siswaSurat === [$this->s[10], $this->s[11]] && $k12 !== null && $k12['jenis'] === 'ditolak_perusahaan' && $k12['sumber'] === 'lapor' && $this->aktif(12) === null, json_encode([$r, $siswaSurat]));
        $this->cek('… pesan menyebut siswa yang dikeluarkan, laporan diproses', str_contains($r['pesan'], self::OLEH . ' 12 dikeluarkan') && $lib->untukAjuan($this->aj['D'])['status'] === 'diproses');

        // Semua ditolak.
        $r = $this->laporkan(13, 'E', ['hasil' => 'ditolak', 'tanggal_mulai' => '', 'tanggal_selesai' => '', 'catatan' => 'Perusahaan sedang renovasi.']);
        $l = $lib->untukAjuan($this->aj['E']);
        $this->cek('lapor "semua ditolak" tanpa tanggal tersimpan', $r['ok'] && $l['hasil'] === 'ditolak' && $l['tanggal_mulai'] === null, json_encode($r));
        $this->aturBalasan(['operator' => ['buat', 'unduh'], 'hubin' => []]);
        $r = $lib->proses((int) $l['id'], true, $this->ctx('operator'), (new PklPengaturanModel())->ambil());
        $e = (new PklPengajuanModel())->find($this->aj['E']);
        $this->cek('konfirmasi "semua ditolak" cukup hak memproses kotak (Buat) → ajuan Ditolak perusahaan, tanpa surat', $r['ok'] && $e['status'] === 'ditolak' && $e['tolak_sumber'] === 'perusahaan'
            && $this->aktif(13) === null && $lib->untukAjuan($this->aj['E'])['status'] === 'diproses' && empty($lib->untukAjuan($this->aj['E'])['surat_id'])
            && $this->db->table('surat_sekolah')->where('pengajuan_id', $this->aj['E'])->countAllResults() === 0, $r['pesan']);
        $this->cek('… catatan ajuan memuat catatan siswa', str_contains((string) $e['catatan_staf'], 'renovasi'));
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);

        // Laporan "semua ditolak" pada ajuan yang LEBIH DULU ditandai ditolak perusahaan oleh Hubin → cukup ditandai selesai.
        $r  = $this->laporkan(17, 'J', ['hasil' => 'ditolak']);
        (new PklKeluar())->ditolakPerusahaan($this->aj['J'], ['catatan' => 'Perusahaan menolak (kabar telepon).'], $this->ctx('hubin'));
        $lJ = $lib->untukAjuan($this->aj['J']);
        $html = $this->halaman(SuratLapor::class, 'operator', 'index');
        $this->cek('kotak: laporan "semua ditolak" pada ajuan yang sudah ditolak tetap punya tombol Konfirmasi', $r['ok'] && str_contains($html, 'admin/surat/balasan/laporan/' . $lJ['id'] . '/setujui'));
        $r  = $lib->proses((int) $lJ['id'], true, $this->ctx('operator'), (new PklPengaturanModel())->ambil());
        $this->cek('… diproses → laporan selesai tanpa galat (ajuan sudah ditolak)', $r['ok'] && str_contains($r['pesan'], 'sudah berstatus ditolak') && $lib->untukAjuan($this->aj['J'])['status'] === 'diproses', $r['pesan']);

        // Laporan "semua" tetap seperti dulu + tautkanAjuan hanya untuk "semua".
        $r = $this->laporkan(14, 'H', ['hasil' => 'semua']);
        $this->cek('lapor "semua diterima" tetap berjalan seperti dulu', $r['ok'] && $lib->untukAjuan($this->aj['H'])['hasil'] === 'semua');
        $this->setelPeranBawaan();
    }

    // =================================================================
    // 6. Kabar WhatsApp
    // =================================================================

    private function ujiWa(): void
    {
        $this->bagian('Kabar WhatsApp siswa yang dikeluarkan');
        $wa  = new PklWaKeputusan();
        $p   = (new PklPengaturanModel())->ambil();
        $set = (new \App\Models\SettingModel())->get();
        $d   = $wa->daftarKeluar($this->aj['B'], $p, $set);
        $this->cek('daftarKeluar(B): 3 siswa, tautan wa.me + pesan berisi alasan', count($d) === 3 && $d[0]['url'] !== null && str_contains($d[0]['pesan'], PklPengajuanModel::kode($this->aj['B'])), json_encode(array_column($d, 'pesan')));
        $this->cek('templat keluar dipakai juga untuk ajuan ditolak PERUSAHAAN', PklWaKeputusan::templat('ditolak', $p, 'perusahaan') === PklWaKeputusan::templat('keluar', $p)
            && PklWaKeputusan::templat('ditolak', $p) !== PklWaKeputusan::templat('keluar', $p));
        $kab = $wa->daftar($this->aj['C'], $p, $set);
        $this->cek('kabar keputusan ajuan C (ditolak perusahaan): sumber + pesan "tidak lagi tercatat"', $kab !== null && $kab['sumber'] === 'perusahaan' && str_contains($kab['siswa'][0]['pesan'], 'tidak lagi tercatat'));
        $ok = $wa->tandaiKeluar($this->aj['B'], (int) $d[0]['id'], $this->ctx('operator'));
        $this->cek('tandaiKeluar: catat sudah dikabari + riwayat', $ok && $this->db->table('pkl_anggota_keluar')->where('id', (int) $d[0]['id'])->where('kabar_at IS NOT NULL', null, false)->countAllResults() === 1
            && $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['B'])->where('aksi', 'kabari_keluar')->countAllResults() === 1);
        $this->cek('tandaiKeluar menolak id dari ajuan lain', ! $wa->tandaiKeluar($this->aj['A'], (int) $d[0]['id'], $this->ctx('operator')));

        // Perbaikan galat: POST kabar-keputusan (ajuan ditolak) kini membalas JSON, bukan galat 500.
        $r = $this->panggil(AdminPkl::class, 'operator', 'tandaiKabar', [], [], $this->aj['C'], $this->s[8]);
        $j = $r instanceof ResponseInterface ? json_decode((string) $r->getBody(), true) : null;
        $this->cek('tandaiKabar (web) membalas JSON ok + token CSRF baru', is_array($j) && ($j['ok'] ?? false) === true && isset($j['csrf']), is_object($r) ? get_class($r) : gettype($r));
        $r = $this->panggil(AdminPkl::class, 'operator', 'tandaiKabarKeluar', [], [], $this->aj['B'], (int) $d[1]['id']);
        $j = $r instanceof ResponseInterface ? json_decode((string) $r->getBody(), true) : null;
        $this->cek('tandaiKabarKeluar (web) membalas JSON ok', is_array($j) && ($j['ok'] ?? false) === true);
    }

    // =================================================================
    // 6b. Gabungkan ajuan (perusahaan sama)
    // =================================================================

    private function ujiGabung(): void
    {
        $this->bagian('Gabungkan ajuan: kandidat, hak, perusahaan sama, batas, hasil');
        $this->aj['K'] = $this->ajuan($this->s[19], 'KL', [$this->s[20]], 'menunggu');
        $this->aj['L'] = $this->ajuan($this->s[21], 'KL');
        $this->aj['M'] = $this->ajuan($this->s[22], 'M', [], 'menunggu', 'ZZUJIKL Toko Sentosa Abadi');
        $lib   = new PklKeluar();
        $model = new PklPengajuanModel();
        $kand  = array_column($lib->kandidatGabung($model->detail($this->aj['K'])), 'id');
        $this->cek('kandidat gabung K = ajuan aktif perusahaan sama (L), bukan M', in_array($this->aj['L'], $kand, true) && ! in_array($this->aj['M'], $kand, true), json_encode($kand));
        $p = (new PklPengaturanModel())->ambil();
        $r = $lib->gabungkan($this->aj['K'], ['tujuan_id' => $this->aj['L']], $this->ctx('operator'), $p);
        $this->cek('Operator menggabungkan ke ajuan DISETUJUI → 403', ! $r['ok'] && $r['http'] === 403);
        $r = $lib->gabungkan($this->aj['K'], ['tujuan_id' => $this->aj['M']], $this->ctx('hubin'), $p);
        $this->cek('perusahaan berbeda → 422', ! $r['ok'] && $r['http'] === 422 && str_contains($r['pesan'], 'berbeda'));
        $r = $lib->gabungkan($this->aj['K'], ['tujuan_id' => $this->aj['K']], $this->ctx('hubin'), $p);
        $this->cek('tujuan = dirinya sendiri → 422', ! $r['ok'] && $r['http'] === 422);
        $r = $lib->gabungkan($this->aj['K'], ['tujuan_id' => $this->aj['L']], $this->ctx('hubin'), ['maks_anggota' => 2] + $p);
        $this->cek('melebihi batas siswa per ajuan → 422', ! $r['ok'] && str_contains($r['pesan'], 'melebihi batas'), $r['pesan']);

        $rr = $this->panggil(AdminPkl::class, 'hubin', 'gabungkan', [], ['tujuan_id' => (string) $this->aj['L'], 'catatan' => 'Satu kelompok di tempat sama.'], $this->aj['K']);
        $k = $model->find($this->aj['K']);
        $angL = array_column($model->anggotaDetail($this->aj['L']), 'peran', 'siswa_id');
        $this->cek('POST gabungkan (Hubin) → ke halaman L; K ditutup (ditolak, sumber gabung, tanpa anggota)', str_contains($this->lokasi($rr), 'admin/pkl/' . $this->aj['L'])
            && $k['status'] === 'ditolak' && $k['tolak_sumber'] === 'gabung' && $this->db->table('pkl_anggota')->where('pengajuan_id', $this->aj['K'])->countAllResults() === 0, (string) session('error'));
        $this->cek('… L kini 3 siswa: 21 tetap pengaju, 19 & 20 teman dan terkunci di L', ($angL[$this->s[21]] ?? '') === 'pengaju' && ($angL[$this->s[19]] ?? '') === 'teman'
            && ($angL[$this->s[20]] ?? '') === 'teman' && (int) ($this->aktif(19)['id'] ?? 0) === $this->aj['L'] && (int) ($this->aktif(20)['id'] ?? 0) === $this->aj['L']);
        $this->cek('… jejak: keluar jenis "pindah" + riwayat gabung/gabung_masuk; BUKAN dihitung penolakan', $this->db->table('pkl_anggota_keluar')->where('pengajuan_id', $this->aj['K'])->where('jenis', 'pindah')->countAllResults() === 2
            && $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['K'])->where('aksi', 'gabung')->countAllResults() === 1
            && $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->aj['L'])->where('aksi', 'gabung_masuk')->countAllResults() === 1
            && ! isset($lib->tolakSiswa([$this->s[19]])[$this->s[19]]) && (new PklAjuan())->periksaKonsistensi() === []);
        $html = $this->halaman(AdminPkl::class, 'hubin', 'detail', [], [], $this->aj['K']);
        $this->cek('detail K: lencana "digabung", tanpa tombol kabar keputusan kosong', str_contains($html, 'digabung ke ajuan lain') && ! str_contains($html, 'data-buka-dialog="pkl-kabar"'));
        $html = $this->halaman(AdminPkl::class, 'operator', 'detail', [], [], $this->aj['M']);
        $this->cek('detail M (tanpa ajuan sejenis): tanpa tombol Gabungkan', ! str_contains($html, 'data-buka-dialog="pkl-gabung"'));
        [$k2, $j] = $this->api(\App\Controllers\Api\Pkl::class, 'operator', 'gabungkan', [], ['tujuan_id' => $this->aj['L']], $this->aj['M']);
        $this->cek('API gabungkan ke ajuan disetujui oleh Operator → 403', $k2 === 403, json_encode($j));
        [$k2, $j] = $this->api(\App\Controllers\Api\Pkl::class, 'hubin', 'gabungkan', [], ['tujuan_id' => $this->aj['L']], $this->aj['M']);
        $this->cek('API gabungkan perusahaan berbeda → 422 + galat tujuan_id', $k2 === 422 && isset($j['data']['errors']['tujuan_id']), json_encode($j));
        [$k2, $j] = $this->api(\App\Controllers\Api\Pkl::class, 'hubin', 'daftar', ['status' => 'ditolak', 'q' => self::PT . 'KL']);
        $this->cek('API daftar: tolak_sumber "gabung" + label "Digabung ke ajuan lain"', ($j['data'][0]['tolak_sumber'] ?? '') === 'gabung' && ($j['data'][0]['status_label'] ?? '') === 'Digabung ke ajuan lain', json_encode($j['data'] ?? $j));
    }

    // =================================================================
    // 7. Halaman web
    // =================================================================

    private function panggil(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/pkl'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        \Config\Services::injectMock('response', new \CodeIgniter\HTTP\Response(config('App')));
        session()->set('admin', ['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'username' => 'zzujikl', 'role' => $peran]);
        session()->remove(['_ci_old_input', 'error', 'success', 'errors', 'pkl_kabar', 'pkl_kabar_keluar', 'galat_alasan', 'alasan_ubah']);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

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
        $this->bagian('Halaman web: detail, Template Alasan, Riwayat, Status Siswa, gerbang alamat');
        $html = $this->halaman(AdminPkl::class, 'hubin', 'detail', [], [], $this->aj['B']);
        $this->cek('detail B (Hubin, tinggal 1 siswa): Ditolak perusahaan ada, Keluarkan TIDAK (minimal 1 tersisa), daftar dikeluarkan, alasan cepat', str_contains($html, 'data-buka-dialog="pkl-ditolakpt"')
            && ! str_contains($html, 'data-buka-dialog="pkl-keluarkan"') && str_contains($html, 'Dikeluarkan dari ajuan ini (3)') && str_contains($html, 'data-alasan-isi=') && str_contains($html, 'pkl-alasan.js'));
        $this->cek('… kotak kabar siswa yang dikeluarkan + batas alasan 1000', str_contains($html, 'data-buka-dialog="pkl-kabar-keluar"') && str_contains($html, 'maxlength="1000"'));
        $html = $this->halaman(AdminPkl::class, 'hubin', 'detail', [], [], $this->aj['D']);
        $this->cek('detail D (Hubin, 2 siswa, disetujui): jendela Keluarkan dengan pilihan siswa + jenis', str_contains($html, 'data-buka-dialog="pkl-keluarkan"')
            && str_contains($html, 'name="siswa_ids[]"') && str_contains($html, 'value="ditolak_perusahaan"') && str_contains($html, 'data-keluar-form'));
        $html = $this->halaman(AdminPkl::class, 'operator', 'detail', [], [], $this->aj['B']);
        $this->cek('detail B (Operator, disetujui): TANPA tombol Ditolak perusahaan & Keluarkan', ! str_contains($html, 'data-buka-dialog="pkl-ditolakpt"') && ! str_contains($html, 'data-buka-dialog="pkl-keluarkan"'));
        $html = $this->halaman(AdminPkl::class, 'operator', 'detail', [], [], $this->aj['F']);
        $this->cek('detail F: lencana "Pernah ditolak 1×" + tautan Riwayat + peringatan', str_contains($html, 'Pernah ditolak 1×') && str_contains($html, 'admin/pkl/riwayat/' . $this->s[4]) && str_contains($html, 'DITOLAK perusahaan ini'));
        $html = $this->halaman(AdminPkl::class, 'operator', 'detail', [], [], $this->aj['C']);
        $this->cek('detail C: lencana "oleh perusahaan"', str_contains($html, 'oleh perusahaan'));

        $r = $this->panggil(AdminPkl::class, 'operator', 'keluarkan', [], ['siswa_ids' => [(string) $this->s[5]], 'jenis' => 'mundur', 'catatan' => 'Mengundurkan diri.'], $this->aj['B']);
        $this->cek('POST keluarkan (Operator, ajuan disetujui) → kembali dengan galat', str_contains($this->lokasi($r), 'admin/pkl/' . $this->aj['B']) && session('error') !== null);
        $this->aj['I'] = $this->ajuan($this->s[16], 'I', [$this->s[3]], 'menunggu');
        $r = $this->panggil(AdminPkl::class, 'operator', 'keluarkan', [], ['siswa_ids' => [(string) $this->s[3]], 'jenis' => 'mundur', 'catatan' => 'Mengundurkan diri dari tempat ini.'], $this->aj['I']);
        $this->cek('POST keluarkan (Operator, ajuan menunggu) → sukses + kotak kabar terbuka', str_contains((string) session('success'), 'dikeluarkan') && (int) session('pkl_kabar_keluar') === $this->aj['I'] && $this->aktif(3) === null);
        $r = $this->panggil(AdminPkl::class, 'hubin', 'ditolakPerusahaan', [], ['catatan' => 'Perusahaan tutup sementara.'], $this->aj['I']);
        $this->cek('POST ditolak-perusahaan (Hubin) → sukses', str_contains((string) session('success'), 'DITOLAK PERUSAHAAN') && (new PklPengajuanModel())->find($this->aj['I'])['tolak_sumber'] === 'perusahaan');

        $html = $this->halaman(AdminPkl::class, 'operator', 'riwayat', ['dari' => (string) $this->aj['F']], [], $this->s[4]);
        $this->cek('halaman Riwayat siswa 4: 2 catatan, "Dikeluarkan dari kelompok", tombol kembali ke F', str_contains($html, 'Dikeluarkan dari kelompok') && str_contains($html, '2 catatan')
            && str_contains($html, 'Kembali ke ' . PklPengajuanModel::kode($this->aj['F'])));
        $html = $this->halaman(AdminPkl::class, 'operator', 'siswa', ['fase' => 'pernah_ditolak', 'q' => self::OLEH]);
        $this->cek('Status Siswa: saringan "pernah ditolak" + lencana + nama bertaut riwayat', str_contains($html, 'Pernah ditolak 1×') && str_contains($html, 'admin/pkl/riwayat/' . $this->s[6]));

        $html = $this->halaman(AdminPklAlasan::class, 'hubin', 'index');
        $this->cek('halaman Template Alasan (Hubin): 4 kelompok + kalimat sekolah', str_contains($html, 'Dikembalikan untuk perbaikan') && str_contains($html, 'Laporan diterima dikembalikan') && str_contains($html, 'Tunggakan pembayaran'));
        $r = $this->panggil(AdminPklAlasan::class, 'operator', 'index');
        $this->cek('halaman Template Alasan (Operator) → dialihkan', $r instanceof RedirectResponse && session('error') !== null);
        $r = $this->panggil(AdminPklAlasan::class, 'hubin', 'tambah', [], ['jenis' => 'kembalikan', 'judul' => 'ZZ Uji Web', 'isi' => 'Kalimat uji lewat halaman web.']);
        $id = (int) ($this->db->table('pkl_alasan')->where('judul', 'ZZ Uji Web')->get()->getRowArray()['id'] ?? 0);
        $this->cek('POST tambah template (Hubin) → tersimpan', $id > 0 && str_contains($this->lokasi($r), 'admin/pkl/alasan'));
        $this->panggil(AdminPklAlasan::class, 'hubin', 'ubah', [], ['jenis' => 'kembalikan', 'judul' => 'ZZ Uji Web 2', 'isi' => 'Kalimat uji lewat halaman web (ubah).'], $id);
        $this->panggil(AdminPklAlasan::class, 'hubin', 'naik', [], [], $id);
        $this->panggil(AdminPklAlasan::class, 'hubin', 'hapus', [], [], $id);
        $this->cek('POST ubah / geser / hapus template', $this->db->table('pkl_alasan')->where('id', $id)->countAllResults() === 0 && $this->db->table('audit_log')->like('deskripsi', 'ZZ Uji Web 2')->countAllResults() >= 1);
        $r = $this->panggil(AdminPklAlasan::class, 'hubin', 'tambah', [], ['jenis' => 'kembalikan', 'judul' => 'Z', 'isi' => 'x']);
        $this->cek('POST tambah template tidak sah → kembali dengan galat isian', session('galat_alasan') !== null || session('error') !== null);

        $this->cek('gerbang: admin/pkl/{id}/keluarkan = ubah|acc, ditolak-perusahaan = acc, riwayat terbuka', PklHak::hakUntukAlamat('admin/pkl/5/keluarkan', 'POST') === 'ubah|acc'
            && PklHak::hakUntukAlamat('admin/pkl/5/ditolak-perusahaan', 'POST') === 'acc' && PklHak::hakUntukAlamat('admin/pkl/riwayat/5') === null
            && HakPeran::boleh('operator', 'admin/pkl/5/keluarkan', 'POST') && ! HakPeran::boleh('operator', 'admin/pkl/5/ditolak-perusahaan', 'POST'));
        $this->cek('menu samping: Template Alasan tampil untuk Hubin, tidak untuk Operator', HakAkses::boleh('hubin', 'admin/pkl/alasan') && ! HakAkses::boleh('operator', 'admin/pkl/alasan'));
    }

    // =================================================================
    // 8. API aplikasi
    // =================================================================

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): array
    {
        ApiAuth::set(['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'role' => $peran]);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/pkl'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        \Config\Services::injectMock('response', new \CodeIgniter\HTTP\Response(config('App')));
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r = $c->{$metode}(...$arg);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    private function ujiApi(): void
    {
        $this->bagian('API aplikasi: api/v1/pkl/… & surat/balasan/laporan');
        $P = \App\Controllers\Api\Pkl::class;
        [$k, $j] = $this->api($P, 'hubin', 'meta');
        $this->cek('GET pkl/meta: jenis_keluar + template alasan per jendela + batas 1000 + hak kelola_alasan', $k === 200 && isset($j['data']['jenis_keluar']['ditolak_perusahaan'])
            && isset($j['data']['alasan']['dialog']['keluarkan']) && ($j['data']['aturan']['alasan_maks_huruf'] ?? 0) === 1000 && ($j['data']['hak']['kelola_alasan'] ?? false) === true
            && isset($j['data']['hak']['hak_pkl']['alasan']));
        [$k, $j] = $this->api($P, 'hubin', 'detail', [], [], $this->aj['F']);
        $ang = $j['data']['anggota'][0] ?? [];
        $this->cek('GET pkl/ajuan/{F}: anggota[].riwayat_tolak + keluar + hak.keluarkan/ditolak_perusahaan', $k === 200 && count($ang['riwayat_tolak'] ?? []) === 1
            && ($ang['riwayat_tolak'][0]['sumber'] ?? '') === 'perusahaan' && array_key_exists('keluar', $j['data']) && array_key_exists('ditolak_perusahaan', $j['data']['hak'] ?? []));
        [$k, $j] = $this->api($P, 'hubin', 'detail', [], [], $this->aj['B']);
        $this->cek('GET pkl/ajuan/{B}: keluar (3) + jumlah_keluar', $k === 200 && count($j['data']['keluar'] ?? []) === 3 && ($j['data']['jumlah_keluar'] ?? 0) === 3);
        [$k, $j] = $this->api($P, 'hubin', 'daftar', ['status' => 'ditolak', 'q' => self::PT . 'C']);
        $this->cek('GET pkl/ajuan?status=ditolak: status_label "Ditolak perusahaan" + tolak_sumber', $k === 200 && ($j['data'][0]['tolak_sumber'] ?? '') === 'perusahaan' && ($j['data'][0]['status_label'] ?? '') === 'Ditolak perusahaan');
        [$k, $j] = $this->api($P, 'operator', 'siswa', ['fase' => 'pernah_ditolak', 'q' => self::OLEH, 'per' => '50']);
        $this->cek('GET pkl/siswa?fase=pernah_ditolak + jumlah_ditolak', $k === 200 && in_array($this->s[6], array_column($j['data'] ?? [], 'siswa_id'), true) && max(array_column($j['data'] ?? [['jumlah_ditolak' => 0]], 'jumlah_ditolak')) >= 1);
        [$k, $j] = $this->api($P, 'operator', 'riwayatSiswa', [], [], $this->s[4]);
        $this->cek('GET pkl/siswa/{id}/riwayat', $k === 200 && ($j['data']['jumlah_ditolak'] ?? 0) === 1 && count($j['data']['riwayat'] ?? []) === 2);
        [$k] = $this->api($P, 'operator', 'riwayatSiswa', [], [], 999999999);
        $this->cek('GET riwayat siswa tak ada → 404', $k === 404);

        // Keluarkan & ditolak perusahaan lewat aplikasi.
        [$k, $j] = $this->api($P, 'operator', 'keluarkan', [], ['siswa_ids' => [$this->s[15]], 'jenis' => 'mundur', 'catatan' => 'Mengundurkan diri.'], $this->aj['H']);
        $this->cek('POST keluarkan (Operator, disetujui) → 403', $k === 403);
        [$k, $j] = $this->api($P, 'hubin', 'keluarkan', [], ['siswa_ids' => [$this->s[15]], 'jenis' => 'ngawur', 'catatan' => 'x'], $this->aj['H']);
        $this->cek('POST keluarkan tidak sah → 422 + galat per kolom', $k === 422 && isset($j['data']['errors']['jenis'], $j['data']['errors']['catatan']), json_encode($j));
        [$k, $j] = $this->api($P, 'hubin', 'keluarkan', [], ['siswa_ids' => [$this->s[15]], 'jenis' => 'ditolak_perusahaan', 'catatan' => 'Kuota penuh untuk satu siswa.'], $this->aj['H']);
        $this->cek('POST keluarkan (Hubin) → 200 + daftar keluar, siswa 15 bebas', $k === 200 && count($j['data']['keluar'] ?? []) === 1 && $this->aktif(15) === null, json_encode($j));
        [$k, $j] = $this->api($P, 'hubin', 'kabarKeluar', [], [], $this->aj['H']);
        $this->cek('GET kabar-keluar (H): 1 siswa + url', $k === 200 && count($j['data'] ?? []) === 1 && ! empty($j['data'][0]['url']));
        [$k] = $this->api($P, 'hubin', 'kabarKeluarTandai', [], [], $this->aj['H'], (int) ($j['data'][0]['id'] ?? 0));
        $this->cek('POST kabar-keluar/{id}/tandai → 200', $k === 200);
        [$k] = $this->api($P, 'operator', 'ditolakPerusahaan', [], ['catatan' => 'Perusahaan tutup.'], $this->aj['H']);
        $this->cek('POST ditolak-perusahaan (Operator) → 403', $k === 403);
        [$k, $j] = $this->api($P, 'hubin', 'ditolakPerusahaan', [], ['catatan' => 'Perusahaan tutup sementara.'], $this->aj['H']);
        $this->cek('POST ditolak-perusahaan (Hubin) → 200, status ditolak, sumber perusahaan', $k === 200 && ($j['data']['tolak_sumber'] ?? '') === 'perusahaan' && $this->aktif(14) === null);

        // Template alasan lewat aplikasi.
        [$k, $j] = $this->api($P, 'operator', 'alasan', ['dialog' => 'tolak']);
        $this->cek('GET pkl/alasan?dialog=tolak (siapa pun) + boleh_kelola false untuk Operator', $k === 200 && ($j['data'][0]['jenis'] ?? '') === 'tolak' && ($j['meta']['boleh_kelola'] ?? true) === false);
        [$k] = $this->api($P, 'operator', 'alasan', ['jenis' => 'ngawur']);
        $this->cek('GET pkl/alasan?jenis tak dikenal → 422', $k === 422);
        [$k] = $this->api($P, 'operator', 'alasanTambah', [], ['jenis' => 'tolak', 'judul' => 'ZZ Uji Api', 'isi' => 'Kalimat dari aplikasi.']);
        $this->cek('POST pkl/alasan (Operator) → 403', $k === 403);
        [$k, $j] = $this->api($P, 'hubin', 'alasanTambah', [], ['jenis' => 'tolak', 'judul' => 'ZZ Uji Api', 'isi' => 'Kalimat dari aplikasi.']);
        $idA = (int) ($j['data']['id'] ?? 0);
        $this->cek('POST pkl/alasan (Hubin) → 201', $k === 201 && $idA > 0);
        [$k] = $this->api($P, 'hubin', 'alasanUbah', [], ['judul' => 'ZZ Uji Api 2', 'isi' => 'Kalimat dari aplikasi (ubah).'], $idA);
        $this->cek('POST pkl/alasan/{id} → 200', $k === 200 && $this->db->table('pkl_alasan')->where('id', $idA)->where('judul', 'ZZ Uji Api 2')->countAllResults() === 1);
        $ids = array_column((new PklAlasan())->daftar('tolak'), 'id');
        [$k] = $this->api($P, 'hubin', 'alasanUrut', [], ['jenis' => 'tolak', 'ids' => array_reverse($ids)]);
        $this->cek('POST pkl/alasan/urut → 200', $k === 200 && array_column((new PklAlasan())->daftar('tolak'), 'id') === array_reverse($ids));
        [$k] = $this->api($P, 'hubin', 'alasanHapus', [], [], $idA);
        [$k2] = $this->api($P, 'hubin', 'alasanHapus', [], [], $idA);
        $this->cek('DELETE pkl/alasan/{id} → 200, kedua kali 404', $k === 200 && $k2 === 404);

        // Kotak laporan API: hasil perusahaan.
        $this->aturBalasan(['operator' => ['buat', 'unduh', 'acc'], 'hubin' => []]);
        [$k, $j] = $this->api(\App\Controllers\Api\Surat::class, 'operator', 'laporan', ['status' => 'diproses']);
        $baris = [];
        foreach ($j['data'] ?? [] as $x) {
            if ($x['pengajuan_id'] === $this->aj['D']) {
                $baris = $x;
            }
        }
        $this->cek('GET surat/balasan/laporan: hasil + siswa_diterima/siswa_ditolak', $k === 200 && ($baris['hasil'] ?? '') === 'sebagian' && array_key_exists('siswa_diterima', $baris) && array_key_exists('siswa_ditolak', $baris), json_encode($baris));
        $this->setelPeranBawaan();
    }

    // =================================================================
    // Pemulihan
    // =================================================================

    private function simpanAsli(): void
    {
        $this->simpanPeranAsli();
        $this->pengaturanAsli ??= (function () {
            $r = (new PklPengaturanModel())->ambil();
            unset($r['id']);

            return $r;
        })();
        $this->alasanAsli ??= $this->db->table('pkl_alasan')->orderBy('id')->get()->getResultArray();
    }

    private function netralkan(): void
    {
        $this->db->table('pkl_pengaturan')->where('id', 1)->update([
            'hak_peran' => null, 'surat_perlu_acc' => null, 'lapor_buka' => 1, 'wa_pesan_keluar' => null, 'wa_pesan_tolak' => null, 'tingkat' => 'X,XI,XII',
            'mulai_paling_awal' => null, 'selesai_paling_akhir' => null, 'durasi_min_hari' => 0, 'durasi_maks_hari' => 0,
        ]);
        $this->setelPeranBawaan();
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete();
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_merge(
                array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'),
                PklKeluar::tabelAda() ? array_column($this->db->table('pkl_anggota_keluar')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id') : [],
            );
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique(array_map('intval', $aj))))->delete();
            }
            $throttle = new LoginThrottle();
            foreach ($ids as $id) {
                $throttle->clear('pkllapor:' . $id, self::IP);
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujikl', 'after')->delete();
        $this->db->table('audit_log')->groupStart()->like('deskripsi', self::OLEH)->orLike('deskripsi', 'ZZ Uji')->orLike('deskripsi', 'ZZUJIKL')->groupEnd()->delete();
        session()->remove(['pkl_lapor_izin', 'admin', 'error', 'success', 'errors', 'pkl_lapor_selesai', 'pkl_kabar', 'pkl_kabar_keluar', 'galat_alasan', 'alasan_ubah']);
        if ($this->alasanAsli !== null && PklAlasan::tabelAda()) {
            $this->db->table('pkl_alasan')->emptyTable();
            if ($this->alasanAsli !== []) {
                $this->db->table('pkl_alasan')->insertBatch($this->alasanAsli);
            }
            $this->alasanAsli = null;
        }
        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        $this->pulihkanPeran();
        PklHak::lupakan();
    }
}
