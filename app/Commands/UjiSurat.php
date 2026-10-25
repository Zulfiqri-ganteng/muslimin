<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\PklAjuan;
use App\Libraries\PklDocx;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\PklKeluar;
use App\Libraries\PklSurat;
use App\Libraries\SuratAcara;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratNomor;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratPernyataan;
use App\Libraries\SuratPkl;
use App\Libraries\SuratSekolah;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji regresi Surat Sekolah (rancangan: docs/DESAIN-SURAT-SEKOLAH.md): jenis & aturan ACC, hak per alamat, register
 * (buat/muat/daftar), penomoran SATU URUTAN dengan Surat Izin PKL, sidik data, kunci asing, dan tampilan nyata per peran.
 *
 * Data uji diberi tanda "ZZUJI SS" (nama pembuat), NIS "ZZUJISS…" dan alamat IP "10.9.7.7", dibuat dan dihapus sendiri;
 * Pengaturan PKL dipulihkan ke nilai semula di akhir (juga bila uji berhenti di tengah, pada jalan berikutnya).
 * Nomor diuji di tahun 2031–2033 supaya tidak menyentuh nomor surat sungguhan.
 *
 * Jalankan:  php spark dev:uji-surat
 */
class UjiSurat extends BaseCommand
{
    use UjiPeranAman;

    protected $group       = 'dev';
    protected $name        = 'dev:uji-surat';
    protected $description = 'Uji Surat Sekolah: jenis, hak per alamat, register, nomor bersama dengan Surat Izin PKL, sidik, tampilan.';

    private const NIS   = 'ZZUJISS';
    private const IP    = '10.9.7.7';
    private const OLEH  = 'ZZUJI SS';
    private const BAKU  = '{urut}/SMK-BN/PKL/{bln_romawi}/{thn}';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    private int $kelasId = 0;

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
        $this->bersihkan();
        $this->simpanPengaturanAsli();

        $this->ujiMurni();
        $this->ujiHak();

        $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('ada kelas sebagai bahan uji DB', false, 'tabel kelas kosong — bagian DB dilewati');
        } else {
            $this->kelasId = (int) $k['id'];
            $this->netralkanPengaturan();
            $this->ujiRegister();
            $this->ujiNomor();
            $this->ujiTampilan();
            $this->ujiKeputusan();
            $this->ujiBerkas();
            $this->ujiTampilanKeputusan();
            $this->ujiAcara();
            $this->ujiSuratPkl();
            $this->ujiBalasanTerima();
            $this->ujiJadwal();
            $this->ujiLayanan();
            $this->ujiSidikDanTabel(); // paling akhir: menghapus data uji (CASCADE / SET NULL)
        }

        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // 1. Murni (tanpa DB)
    // =================================================================

    private function ujiMurni(): void
    {
        $this->bagian('Jenis surat, aturan ACC, pembantu murni');
        $J = SuratJenis::class;

        $this->cek('5 jenis, urutan menu: ASTS, TKA, Pernyataan, Balasan, Penarikan', $J::kode() === ['izin_asts', 'izin_tka', 'pernyataan_ortu', 'balasan_pkl', 'penarikan_pkl']);
        $alamat = array_map(static fn (string $k) => $J::alamat($k), $J::kode());
        $label  = array_map(static fn (string $k) => $J::label($k), $J::kode());
        $this->cek('alamat & label unik, alamat di bawah admin/surat/', count(array_unique($alamat)) === 5 && count(array_unique($label)) === 5
            && count(array_filter($alamat, static fn (string $a) => str_starts_with($a, 'admin/surat/'))) === 5);
        $this->cek('hanya Pernyataan Orang Tua yang tanpa nomor', ! $J::bernomor('pernyataan_ortu') && $J::bernomor('izin_asts') && $J::bernomor('izin_tka') && $J::bernomor('balasan_pkl') && $J::bernomor('penarikan_pkl'));
        $this->cek('jenis tak dikenal: ada() false, label apa adanya', ! $J::ada('ngawur') && ! $J::ada(null) && ! $J::ada('') && $J::label('ngawur') === 'ngawur' && ! $J::bernomor('ngawur'));

        $semuaSah = true;
        foreach ($J::SIAP as $k) {
            $semuaSah = $semuaSah && $J::ada($k);
        }
        $konsisten = true;
        foreach ($J::kode() as $k) {
            $konsisten = $konsisten && $J::siap($k) === in_array($k, $J::SIAP, true);
        }
        $this->cek('daftar SIAP hanya berisi jenis yang dikenal dan siap() konsisten', $semuaSah && $konsisten, 'siap: ' . ($J::SIAP === [] ? '(belum ada)' : implode(', ', $J::SIAP)));

        $this->cek('ACC bawaan: 4 jenis bernomor wajib, Pernyataan tidak', $J::perluAcc('izin_asts', []) && $J::perluAcc('izin_tka', []) && $J::perluAcc('balasan_pkl', []) && $J::perluAcc('penarikan_pkl', []) && ! $J::perluAcc('pernyataan_ortu', []));
        $p = ['surat_perlu_acc' => '{"izin_tka":0,"izin_asts":1,"pernyataan_ortu":1}'];
        $this->cek('ACC diatur Admin: TKA dimatikan; ASTS tetap; Balasan ikut bawaan; Pernyataan TIDAK PERNAH (tanpa nomor)', ! $J::perluAcc('izin_tka', $p) && $J::perluAcc('izin_asts', $p) && $J::perluAcc('balasan_pkl', $p) && ! $J::perluAcc('pernyataan_ortu', $p));
        $this->cek('nilai "0"/"false"/false mematikan, "1"/true menyalakan', ! $J::perluAcc('izin_tka', ['surat_perlu_acc' => '{"izin_tka":"0"}']) && ! $J::perluAcc('izin_tka', ['surat_perlu_acc' => '{"izin_tka":false}']) && ! $J::perluAcc('izin_tka', ['surat_perlu_acc' => '{"izin_tka":"false"}']) && $J::perluAcc('izin_tka', ['surat_perlu_acc' => '{"izin_tka":true}']));
        $this->cek('JSON rusak / bukan peta / kosong → bawaan (aman: tetap wajib ACC)', $J::perluAcc('izin_tka', ['surat_perlu_acc' => '{rusak']) && $J::perluAcc('izin_tka', ['surat_perlu_acc' => '[1,2]']) && $J::perluAcc('izin_tka', ['surat_perlu_acc' => 'null']) && $J::perluAcc('izin_tka', ['surat_perlu_acc' => '']));
        $peta = $J::petaAcc($p);
        $this->cek('petaAcc: 5 kunci, sesuai aturan', array_keys($peta) === $J::kode() && $peta['izin_tka'] === false && $peta['balasan_pkl'] === true);

        $ringkas = $J::ringkasIsi(['tgl_mulai' => '2026-09-14', 'kegiatan' => 'ASTS', 'mode' => ['umum', 'x'], 'kosong' => '', 'y' => true, 'z' => null, 'sesi' => 'Gelombang 1', 'obj' => new \stdClass()]);
        $this->cek('ringkasIsi: tanggal Indonesia, daftar digabung, boolean Ya, kosong/null/objek dilewati', $ringkas === [
            ['Mulai', '14 September 2026'], ['Kegiatan', 'ASTS'], ['Cakupan', 'umum, x'], ['Y', 'Ya'], ['Sesi', 'Gelombang 1'],
        ], json_encode($ringkas));

        $this->cek('kode tampilan SRT-00012 / SRT-123456', SuratSekolahModel::kode(12) === 'SRT-00012' && SuratSekolahModel::kode(123456) === 'SRT-123456');
        $this->cek('tanggalSah: Y-m-d sah, 30 Feb, format lain, tahun di luar 2000–2100 ditolak', SuratSekolah::tanggalSah('2031-03-10') && ! SuratSekolah::tanggalSah('2031-02-30') && ! SuratSekolah::tanggalSah('10-03-2031') && ! SuratSekolah::tanggalSah('1999-12-31') && ! SuratSekolah::tanggalSah('2101-01-01') && ! SuratSekolah::tanggalSah(''));
        $this->cek('dekodeIsi: JSON peta ok; kosong, bukan JSON, teks JSON → []', SuratSekolah::dekodeIsi('{"a":1}') === ['a' => 1] && SuratSekolah::dekodeIsi('') === [] && SuratSekolah::dekodeIsi('bukan json') === [] && SuratSekolah::dekodeIsi('"teks"') === []);
        $this->cek('pola nomor: kosong/rusak ("{urut}00/X") → bawaan; pola sah dipakai', SuratNomor::pola([]) === self::BAKU && SuratNomor::pola(['format_nomor' => '{urut}00/X']) === self::BAKU && SuratNomor::pola(['format_nomor' => '{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}']) === '{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}');

        $H = PklHak::class;
        $peta = [
            'admin/surat' => null, 'admin/surat/12' => null, 'admin/surat?status=menunggu' => null,
            'admin/surat/asts' => 'surat_buat:izin_asts', 'admin/surat/tka' => 'surat_buat:izin_tka', 'admin/surat/pernyataan-ortu' => 'surat_buat:pernyataan_ortu',
            'admin/surat/balasan' => 'surat_buat:balasan_pkl', 'admin/surat/penarikan' => 'surat_buat:penarikan_pkl', 'admin/surat/12/unduh' => 'surat_unduh:*',
            'admin/surat/12/ubah' => 'surat_buat:*', 'admin/surat/12/batal' => 'surat_buat:*', 'admin/surat/asts/simpan' => 'surat_buat:izin_asts',
            'admin/surat/12/acc' => 'surat_acc:*', 'admin/surat/12/kembalikan' => 'surat_acc:*', 'admin/surat/12/batal-acc' => 'surat_acc:*', 'admin/surat/acc-massal' => 'surat_acc:*',
            'admin/pkl' => null, 'admin/pkl/12/surat' => 'surat', 'admin/surat-lain' => null, 'admin/master/siswa' => null,
        ];
        $salah = [];
        foreach ($peta as $a => $harap) {
            if ($H::hakUntukAlamat($a) !== $harap) {
                $salah[] = $a . ' → ' . json_encode($H::hakUntukAlamat($a));
            }
        }
        $this->cek('alamat → hak yang dituntut (' . count($peta) . ' alamat)', $salah === [], implode(' | ', $salah));
        $this->cek('huruf besar / garis ganda / query tetap terpetakan', $H::hakUntukAlamat('ADMIN/Surat/Asts') === 'surat_buat:izin_asts' && $H::hakUntukAlamat('admin//surat//12//acc') === 'surat_acc:*' && $H::hakUntukAlamat('/admin/surat/') === null);
    }

    // =================================================================
    // 2. Hak akses
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Hak akses: Operator, Waka Hubin, Admin, peran asing (hak per jenis surat, diatur Admin)');
        $this->simpanPengaturanAsli();
        $this->setelPeranBawaan();
        $H = PklHak::class;

        $this->cek('bawaan: Operator membuat & mengunduh kelima jenis, tanpa ACC; Hubin ACC 4 jenis bernomor, tanpa buat/unduh', $H::jenisBoleh('operator', 'buat') === SuratJenis::kode()
            && $H::jenisBoleh('operator', 'unduh') === SuratJenis::kode() && $H::jenisBoleh('operator', 'acc') === []
            && $H::jenisBoleh('hubin', 'buat') === [] && $H::jenisBoleh('hubin', 'unduh') === [] && $H::jenisBoleh('hubin', 'acc') === ['izin_asts', 'izin_tka', 'balasan_pkl', 'penarikan_pkl']);
        $this->cek('kunci lama surat_sekolah (aplikasi lama): Operator & Admin ya; Hubin & peran asing tidak', $H::boleh('operator', 'surat_sekolah') && $H::boleh('admin', 'surat_sekolah') && ! $H::boleh('hubin', 'surat_sekolah') && ! $H::boleh('guru', 'surat_sekolah') && ! $H::boleh('', 'surat_sekolah'));

        $uji = [
            // alamat => [operator, hubin, admin]
            'admin/surat'                => [true, true, true],
            'admin/surat/12'             => [true, true, true],
            'admin/surat/asts'           => [true, false, true],
            'admin/surat/pernyataan-ortu' => [true, false, true],
            'admin/surat/12/unduh'       => [true, false, true],
            'admin/surat/12/acc'         => [false, true, true],
            'admin/surat/12/kembalikan'  => [false, true, true],
            'admin/surat/acc-massal'     => [false, true, true],
            'admin/surat-lain'           => [false, false, true],
            'admin/surat/../master/guru' => [false, false, false],
            'admin/master/guru'          => [false, false, true],
            'admin/pkl'                  => [true, true, true],
        ];
        $salah = [];
        foreach ($uji as $a => [$op, $hu, $ad]) {
            foreach (['operator' => $op, 'hubin' => $hu, 'admin' => $ad, 'guru' => false, '' => false] as $peran => $harap) {
                if (HakAkses::boleh($peran, $a) !== $harap) {
                    $salah[] = ($peran !== '' ? $peran : '(kosong)') . ' ' . $a . ' → ' . json_encode(HakAkses::boleh($peran, $a));
                }
            }
        }
        $this->cek('HakAkses::boleh: ' . count($uji) . ' alamat × 5 peran sesuai aturan', $salah === [], implode(' | ', array_slice($salah, 0, 6)));

        $menuOp = true;
        $menuHu = false;
        foreach (SuratJenis::kode() as $k) {
            $menuOp = $menuOp && HakAkses::boleh('operator', SuratJenis::alamat($k));
            $menuHu = $menuHu || HakAkses::boleh('hubin', SuratJenis::alamat($k));
        }
        $this->cek('menu jenis surat: terlihat oleh Operator (semua), tersembunyi bagi Hubin bawaan', $menuOp && ! $menuHu);

        // Admin memberi Hubin hak membuat & mengunduh semua jenis: menu terbuka, peringatan pemisahan tugas muncul (tidak memblokir).
        $adm  = ['oleh' => self::OLEH . ' Admin', 'admin_id' => null, 'peran' => 'admin', 'ip' => self::IP];
        $baru = $H::bawaanLengkap();
        $baru['hubin'] = array_merge($baru['hubin'], $H::perluasLama(['surat_sekolah']));
        $r = $H::simpan($baru, $adm, true);
        $this->cek('Admin menyimpan hak Hubin + buat/unduh semua jenis → tersimpan', $r['ok'] === true, $r['pesan']);
        $this->cek('Hubin kini boleh membuka halaman jenis surat, Operator tetap', HakAkses::boleh('hubin', 'admin/surat/asts') && HakAkses::boleh('operator', 'admin/surat/asts'));
        $adaPeringatan = false;
        foreach ($r['peringatan'] ?? [] as $w) {
            $adaPeringatan = $adaPeringatan || str_contains($w, 'Waka Hubin memegang ACC sekaligus unduh surat');
        }
        $this->cek('peringatan pemisahan tugas muncul (ACC + unduh pada satu peran)', $adaPeringatan, json_encode($r['peringatan'] ?? []));

        // Hak Buat satu jenis boleh tak dipegang siapa pun (Admin tetap bisa) — jenis lain tidak ikut berubah.
        $kosong = $H::bawaanLengkap();
        $kosong['operator'] = array_values(array_diff($kosong['operator'], ['surat_buat:izin_asts']));
        $r2 = $H::simpan($kosong, $adm, true);
        $this->cek('hak Buat Izin ASTS boleh tak dipegang siapa pun (Admin tetap bisa); jenis lain tetap', $r2['ok'] === true && ! HakAkses::boleh('operator', 'admin/surat/asts') && HakAkses::boleh('admin', 'admin/surat/asts') && HakAkses::boleh('operator', 'admin/surat/tka'));

        $this->setelPeranBawaan();
    }

    // =================================================================
    // 3. Register (buat / muat / daftar)
    // =================================================================

    private function buatSiswa(int $n): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('siswa')->insert([
            'nis' => self::NIS . $n, 'nama' => 'ZZUJI SS ' . $n, 'jenis_kelamin' => 'L', 'kelas_id' => $this->kelasId, 'no_hp' => '08120000000' . $n,
            'status' => 'aktif', 'tanggal_lahir' => '2010-05-17', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** @return array<string, mixed> */
    private function konteks(): array
    {
        return ['oleh' => self::OLEH, 'admin_id' => null, 'peran' => 'operator', 'ip' => self::IP];
    }

    /** @return array<string, mixed> */
    private function data(string $judul, string $tanggal, array $isi = [], array $siswa = [], ?int $ajuan = null, string $perusahaan = ''): array
    {
        return ['judul' => self::OLEH . ' ' . $judul, 'tanggal_surat' => $tanggal, 'isi' => $isi, 'siswa' => $siswa, 'pengajuan_id' => $ajuan, 'perusahaan_nama' => $perusahaan];
    }

    private function surat(int $id): array
    {
        return $this->db->table('surat_sekolah')->where('id', $id)->get()->getRowArray() ?? [];
    }

    private function jumlah(string $tabel, array $where): int
    {
        return (int) $this->db->table($tabel)->where($where)->countAllResults();
    }

    private int $sA = 0;
    private int $sB = 0;
    private int $sC = 0;
    private array $siswaUji = [];

    private function ujiRegister(): void
    {
        $this->bagian('Register: buat, muat, daftar, hitung');
        $svc = new SuratSekolah();
        $ctx = $this->konteks();
        $this->siswaUji = [$this->buatSiswa(1), $this->buatSiswa(2), $this->buatSiswa(3)];
        [$s1, $s2, $s3] = $this->siswaUji;

        // Surat wajib ACC
        $a = $svc->buat('izin_asts', $this->data('ASTS Ganjil 2031/2032', '2031-03-10', ['kegiatan' => 'ASTS', 'semester' => 'Ganjil', 'tgl_mulai' => '2031-09-14', 'tgl_selesai' => '2031-09-18', 'mode' => 'umum']), $ctx);
        $this->cek('buat izin_asts → ok, status menunggu (wajib ACC)', $a['ok'] === true && $a['status'] === 'menunggu' && str_starts_with((string) $a['kode'], 'SRT-'), json_encode($a));
        $this->sA = (int) ($a['id'] ?? 0);
        $r = $this->surat($this->sA);
        $this->cek('baris: perlu_acc=1, diajukan_at terisi, belum bernomor (nomor/tahun/urut NULL), pembuat tercatat',
            (int) ($r['perlu_acc'] ?? 0) === 1 && ! empty($r['diajukan_at']) && array_key_exists('nomor', $r) && $r['nomor'] === null && $r['tahun'] === null && $r['urut'] === null && $r['dibuat_nama'] === self::OLEH);
        $this->cek('riwayat "buat" tercatat sekali, ber-IP dan peran', $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $this->sA, 'aksi' => 'buat', 'peran' => 'operator', 'ip_address' => self::IP]) === 1);
        $this->cek('Audit Log mencatat pembuatan surat', (int) $this->db->table('audit_log')->where('tabel', 'surat_sekolah')->where('record_id', $this->sA)->like('deskripsi', 'ZZUJI SS')->countAllResults() === 1);

        // Surat tanpa ACC + siswa
        $b = $svc->buat('pernyataan_ortu', $this->data('Pernyataan XI TKJ (2 siswa)', '2031-03-10', ['isi_ortu' => true], [
            ['siswa_id' => $s1, 'kelas_id' => $this->kelasId, 'hp' => '081200001111'], ['siswa_id' => $s2, 'kelas_id' => $this->kelasId, 'hp' => null],
        ]), $ctx);
        $this->sB = (int) ($b['id'] ?? 0);
        $this->cek('buat pernyataan_ortu → status disetujui (tanpa ACC), perlu_acc=0, tanpa jam diajukan', $b['ok'] === true && $b['status'] === 'disetujui' && (int) $this->surat($this->sB)['perlu_acc'] === 0 && $this->surat($this->sB)['diajukan_at'] === null);
        $baris = $this->db->table('surat_sekolah_siswa')->where('surat_id', $this->sB)->orderBy('urut')->get()->getResultArray();
        $this->cek('2 siswa tersimpan berurutan 1,2; HP & kelas terbawa', count($baris) === 2 && (int) $baris[0]['urut'] === 1 && (int) $baris[1]['urut'] === 2 && (int) $baris[0]['siswa_id'] === $s1 && $baris[0]['hp'] === '081200001111' && $baris[1]['hp'] === null && (int) $baris[0]['kelas_id'] === $this->kelasId);

        // Duplikat & id sampah dibuang
        $c = $svc->buat('balasan_pkl', $this->data('Balasan PT Uji Surat', '2031-04-01', ['periode_mulai' => '2031-05-04'], [$s1, ['siswa_id' => $s1], ['siswa_id' => 0], ['siswa_id' => $s3], 'x', ['siswa_id' => $s2]], null, 'PT Uji Surat'), $ctx);
        $this->sC = (int) ($c['id'] ?? 0);
        $this->cek('siswa ganda & id 0/teks dibuang: 3 siswa unik (s1,s3,s2) berurutan', $c['ok'] === true && array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->where('surat_id', $this->sC)->orderBy('urut')->get()->getResultArray(), 'siswa_id')) === [$s1, $s3, $s2]);
        $this->cek('perusahaan_nama tersimpan', $this->surat($this->sC)['perusahaan_nama'] === 'PT Uji Surat');
        $kelasTersimpan = array_values(array_unique(array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->where('surat_id', $this->sC)->get()->getResultArray(), 'kelas_id'))));
        $this->cek('kelas yang tidak disebut diisi kelas siswa SAAT INI (kolom kelas surat tak tercetak kosong)', $kelasTersimpan === [$this->kelasId], json_encode($kelasTersimpan));

        // Aturan Admin: TKA dimatikan ACC-nya
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['surat_perlu_acc' => '{"izin_tka":0}']);
        $t = $svc->buat('izin_tka', $this->data('TKA tanpa ACC (aturan Admin)', '2031-03-10', ['sesi' => 'Gelombang 1']), $ctx);
        $this->cek('aturan Admin "TKA tanpa ACC" → surat TKA langsung disetujui, perlu_acc=0', $t['ok'] === true && $t['status'] === 'disetujui' && (int) $this->surat((int) $t['id'])['perlu_acc'] === 0);
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['surat_perlu_acc' => null]);

        // Masukan tidak sah
        $sebelum = $this->jumlah('surat_sekolah', ['dibuat_nama' => self::OLEH]);
        $gagal = [
            'jenis ngawur'     => $svc->buat('ngawur', $this->data('x', '2031-03-10'), $ctx),
            'judul kosong'     => $svc->buat('izin_tka', ['judul' => '   ', 'tanggal_surat' => '2031-03-10'] + $this->data('x', '2031-03-10'), $ctx),
            'judul 191 huruf'  => $svc->buat('izin_tka', ['judul' => str_repeat('a', 191)] + $this->data('x', '2031-03-10'), $ctx),
            'tanggal 30 Feb'   => $svc->buat('izin_tka', $this->data('x', '2031-02-30'), $ctx),
            'tanggal kosong'   => $svc->buat('izin_tka', $this->data('x', ''), $ctx),
            'isi bukan array'  => $svc->buat('izin_tka', ['isi' => 'teks'] + $this->data('x', '2031-03-10'), $ctx),
            'isi >60 KB'       => $svc->buat('izin_tka', ['isi' => ['besar' => str_repeat('a', 61000)]] + $this->data('x', '2031-03-10'), $ctx),
            'siswa tak ada'    => $svc->buat('balasan_pkl', $this->data('ZZUJI SS GAGAL', '2031-03-10', [], [$s1, 2000000000]), $ctx),
        ];
        $kodeHarap = ['jenis ngawur' => 'jenis', 'judul kosong' => 'judul', 'judul 191 huruf' => 'judul', 'tanggal 30 Feb' => 'tanggal', 'tanggal kosong' => 'tanggal', 'isi bukan array' => 'isi', 'isi >60 KB' => 'isi', 'siswa tak ada' => 'galat'];
        $salah = [];
        foreach ($gagal as $nama => $h) {
            if (($h['ok'] ?? true) !== false || ($h['kode'] ?? '') !== $kodeHarap[$nama]) {
                $salah[] = $nama . ' → ' . json_encode($h);
            }
        }
        $this->cek('8 masukan tidak sah ditolak dengan kode yang benar', $salah === [], implode(' | ', $salah));
        $this->cek('penolakan tidak meninggalkan baris (siswa tak ada di tengah: transaksi dibatalkan semua)', $this->jumlah('surat_sekolah', ['dibuat_nama' => self::OLEH]) === $sebelum && $this->jumlah('surat_sekolah', ['judul' => self::OLEH . ' ZZUJI SS GAGAL']) === 0);

        // Muat
        $m = $svc->muat($this->sC);
        $this->cek('muat: isi_arr terdekode, 3 siswa berurutan dengan nama/NIS/kelas, riwayat ada', $m !== null && ($m['surat']['isi_arr']['periode_mulai'] ?? '') === '2031-05-04' && count($m['siswa']) === 3
            && $m['siswa'][0]['nama'] === 'ZZUJI SS 1' && $m['siswa'][0]['nis'] === self::NIS . '1' && (string) $m['siswa'][0]['nama_kelas'] !== '' && count($m['riwayat']) === 1);
        $this->cek('muat id tak ada → null', $svc->muat(2000000000) === null);

        // Daftar
        $f = ['q' => 'ZZUJI SS'];
        $d = $svc->daftar($f, 1, 25);
        $this->cek('daftar q="ZZUJI SS" → 4 surat (ASTS, Pernyataan, Balasan, TKA)', $d['total'] === 4 && count($d['rows']) === 4, 'total ' . $d['total']);
        $this->cek('saringan status=menunggu → 2 (ASTS & Balasan)', $svc->daftar($f + ['status' => 'menunggu'], 1)['total'] === 2);
        $this->cek('saringan jenis=pernyataan_ortu → 1; jenis asing diabaikan (4)', $svc->daftar($f + ['jenis' => 'pernyataan_ortu'], 1)['total'] === 1 && $svc->daftar($f + ['jenis' => 'ngawur'], 1)['total'] === 4);
        $this->cek('saringan tahun 2031 → 4; 2030 → 0; tahun absurd diabaikan', $svc->daftar($f + ['tahun' => 2031], 1)['total'] === 4 && $svc->daftar($f + ['tahun' => 2030], 1)['total'] === 0 && $svc->daftar($f + ['tahun' => 9999], 1)['total'] === 4);
        $this->cek('cari perusahaan "Uji Surat" → 1; cari kata tak ada → 0', $svc->daftar(['q' => 'PT Uji Surat'], 1)['total'] === 1 && $svc->daftar(['q' => 'zzz-tidak-ada-zzz'], 1)['total'] === 0);
        $dh = $svc->daftar($f, 99, 2);
        $this->cek('halaman dijepit: per=2, 4 surat → 2 halaman, minta hal 99 → hal 2 dengan 2 baris; hal 0 → hal 1', $dh['jml_hal'] === 2 && $dh['page'] === 2 && count($dh['rows']) === 2 && $svc->daftar($f, 0, 2)['page'] === 1);
        $baris = $svc->daftar(['q' => 'Balasan PT Uji Surat'], 1)['rows'][0] ?? [];
        $this->cek('jml_siswa dihitung (Balasan = 3, Pernyataan = 2, ASTS = 0)', (int) ($baris['jml_siswa'] ?? -1) === 3
            && (int) $svc->daftar(['q' => 'Pernyataan XI TKJ'], 1)['rows'][0]['jml_siswa'] === 2 && (int) $svc->daftar(['q' => 'ASTS Ganjil 2031'], 1)['rows'][0]['jml_siswa'] === 0);
        $menunggu = $svc->daftar($f + ['status' => 'menunggu'], 1)['rows'];
        $this->cek('tab Menunggu: yang paling lama menunggu di atas (ASTS dulu, Balasan kemudian)', count($menunggu) === 2 && (int) $menunggu[0]['id'] === $this->sA && (int) $menunggu[1]['id'] === $this->sC);

        $h = $svc->hitungStatus();
        $this->cek('hitungStatus: kunci lengkap, semua = jumlah per status, menunggu ≥ 2, disetujui ≥ 2', array_keys($h) === ['semua', 'menunggu', 'dikembalikan', 'disetujui', 'dibatalkan']
            && $h['semua'] === $h['menunggu'] + $h['dikembalikan'] + $h['disetujui'] + $h['dibatalkan'] && $h['menunggu'] >= 2 && $h['disetujui'] >= 2);
        $tahun  = $svc->tahunTersedia();
        $urutan = $tahun;
        rsort($urutan);
        $this->cek('tahunTersedia memuat 2031 dan berurutan terbaru dulu', in_array(2031, $tahun, true) && $tahun === $urutan);
    }

    // =================================================================
    // 4. Nomor: satu urutan dengan Surat Izin PKL
    // =================================================================

    /** @param array<string, mixed> $set */
    private function atur(array $set): void
    {
        $this->db->table('pkl_pengaturan')->where('id', 1)->update($set);
    }

    private function setujui(int $id): void
    {
        $this->db->table('surat_sekolah')->where('id', $id)->update(['status' => 'disetujui']);
    }

    /** @param list<int> $teman siswa lain dalam ajuan yang sama (peran "teman") */
    private function buatAjuanDisetujui(int $siswaId, string $perusahaan, array $teman = []): int
    {
        $data = [
            'perusahaan_nama' => $perusahaan, 'perusahaan_norm' => PklForm::normPerusahaan($perusahaan),
            'perusahaan_alamat' => 'Jl. Uji Surat No. 1', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null, 'hp' => '081200009999', 'tanggal_lahir' => null, 'teman' => [],
        ];
        $anggota = [['siswa_id' => $siswaId, 'kelas_id' => $this->kelasId, 'peran' => 'pengaju', 'hp' => '081200009999']];
        foreach ($teman as $t) {
            $anggota[] = ['siswa_id' => $t, 'kelas_id' => $this->kelasId, 'peran' => 'teman', 'hp' => null];
        }
        $r = (new PklAjuan())->kirimBaru($data, $anggota,
            ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP, 'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui', 'tahun_ajaran' => '2031/2032']);

        return (int) ($r['id'] ?? 0);
    }

    private function ujiNomor(): void
    {
        $this->bagian('Nomor: satu urutan dengan Surat Izin PKL (tahun uji 2031–2033)');
        $svc = new SuratSekolah();
        $ctx = $this->konteks();
        $this->atur(['format_nomor' => self::BAKU, 'nomor_awal' => 1, 'nomor_awal_tahun' => null, 'surat_perlu_acc' => null]);
        $p = (new PklPengaturanModel())->ambil();

        $this->cek('tahun kosong: maksTahun 0, urutBerikutnya 1', SuratNomor::maksTahun($this->db, 2031) === 0 && SuratNomor::urutBerikutnya($this->db, 2031, $p) === 1);
        $this->cek('lantai "nomor berikutnya" 50 berlaku HANYA di tahunnya (2031 → 50; 2032 → 1)',
            SuratNomor::urutBerikutnya($this->db, 2031, ['nomor_awal' => 50, 'nomor_awal_tahun' => 2031]) === 50 && SuratNomor::urutBerikutnya($this->db, 2032, ['nomor_awal' => 50, 'nomor_awal_tahun' => 2031]) === 1);

        // A: TKA (wajib ACC)
        $a = (int) $svc->buat('izin_tka', $this->data('TKA Gelombang 1 (nomor)', '2031-03-10', ['sesi' => 'Gelombang 1']), $ctx)['id'];
        $r = $svc->terbitkan($a, $ctx);
        $this->cek('terbitkan ditolak selama belum disetujui (kode status), nomor tetap kosong', $r['ok'] === false && $r['kode'] === 'status' && $this->surat($a)['nomor'] === null);
        $this->setujui($a);
        $r = $svc->terbitkan($a, $ctx);
        $this->cek('A disetujui → nomor 1/SMK-BN/PKL/III/2031 (baru)', $r['ok'] === true && $r['baru'] === true && $r['surat']['nomor'] === '1/SMK-BN/PKL/III/2031' && (int) $r['surat']['urut'] === 1, json_encode($r['surat']['nomor'] ?? $r));
        $r2 = $svc->terbitkan($a, $ctx);
        $row = $this->surat($a);
        $this->cek('terbitkan ulang → nomor SAMA (baru=false), baris tidak berubah', $r2['ok'] === true && $r2['baru'] === false && $row['nomor'] === '1/SMK-BN/PKL/III/2031' && (int) $row['urut'] === 1 && (int) $row['tahun'] === 2031);
        $this->cek('riwayat "nomor" tercatat tepat sekali', $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $a, 'aksi' => 'nomor']) === 1);

        // B: Balasan
        $b = (int) $svc->buat('balasan_pkl', $this->data('Balasan (nomor)', '2031-03-11', [], [], null, 'PT Uji Nomor'), $ctx)['id'];
        $this->setujui($b);
        $rb = $svc->terbitkan($b, $ctx);
        $this->cek('B (Balasan) → nomor 2/SMK-BN/PKL/III/2031', $rb['ok'] === true && $rb['surat']['nomor'] === '2/SMK-BN/PKL/III/2031');

        // Jenis tanpa nomor
        $rc = $svc->terbitkan($this->sB, $ctx);
        $this->cek('Pernyataan (tanpa nomor): terbitkan ok tetapi tetap tanpa nomor', $rc['ok'] === true && $rc['baru'] === false && $this->surat($this->sB)['nomor'] === null && $this->surat($this->sB)['urut'] === null);
        $this->cek('id tak ada → kode tidak_ada', $svc->terbitkan(2000000000, $ctx) === ['ok' => false, 'kode' => 'tidak_ada']);

        // Surat Izin PKL ikut urutan yang sama
        [$s1] = $this->siswaUji;
        $aj = $this->buatAjuanDisetujui($s1, 'ZZUJISS Nomor Satu');
        $pk = (new PklSurat())->terbitkan($aj, '2031-03-12', $ctx);
        $this->cek('Surat Izin PKL terbit berikutnya → nomor 3/SMK-BN/PKL/III/2031 (urutan dibagi dengan surat sekolah)', ($pk['ok'] ?? false) === true && ($pk['surat']['nomor'] ?? '') === '3/SMK-BN/PKL/III/2031' && (int) ($pk['surat']['urut'] ?? 0) === 3, json_encode($pk['surat']['nomor'] ?? $pk));
        $d = (int) $svc->buat('izin_asts', $this->data('ASTS (nomor)', '2031-03-13'), $ctx)['id'];
        $this->setujui($d);
        $rd = $svc->terbitkan($d, $ctx);
        $this->cek('surat sekolah sesudah Surat Izin PKL → nomor 4', $rd['ok'] === true && $rd['surat']['nomor'] === '4/SMK-BN/PKL/III/2031');
        $semua = array_map('intval', array_column($this->db->query('SELECT urut FROM pkl_surat WHERE tahun = 2031 UNION ALL SELECT urut FROM surat_sekolah WHERE tahun = 2031')->getResultArray(), 'urut'));
        sort($semua);
        $this->cek('gabungan kedua tabel tahun 2031 = 1,2,3,4 tanpa nomor ganda', $semua === [1, 2, 3, 4], implode(',', $semua));

        // Lantai
        $this->atur(['nomor_awal' => 50, 'nomor_awal_tahun' => 2031]);
        $e = (int) $svc->buat('izin_tka', $this->data('TKA (lantai)', '2031-03-14'), $ctx)['id'];
        $this->setujui($e);
        $f = (int) $svc->buat('penarikan_pkl', $this->data('Penarikan (lantai)', '2031-03-15', [], [], null, 'PT Uji Nomor'), $ctx)['id'];
        $this->setujui($f);
        $re = $svc->terbitkan($e, $ctx);
        $rf = $svc->terbitkan($f, $ctx);
        $this->cek('lantai 50 di 2031: surat berikutnya 50, lalu 51', ($re['surat']['nomor'] ?? '') === '50/SMK-BN/PKL/III/2031' && ($rf['surat']['nomor'] ?? '') === '51/SMK-BN/PKL/III/2031', ($re['surat']['nomor'] ?? '?') . ' & ' . ($rf['surat']['nomor'] ?? '?'));
        $this->atur(['nomor_awal' => 1, 'nomor_awal_tahun' => null]);

        // Tahun lain, format lain, format rusak
        $g = (int) $svc->buat('izin_asts', $this->data('ASTS (2032)', '2032-01-05'), $ctx)['id'];
        $this->setujui($g);
        $rg = $svc->terbitkan($g, $ctx);
        $this->cek('urutan PER TAHUN: tahun 2032 mulai dari 1 → 1/SMK-BN/PKL/I/2032', ($rg['surat']['nomor'] ?? '') === '1/SMK-BN/PKL/I/2032');
        $this->atur(['format_nomor' => '{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}']);
        $h = (int) $svc->buat('izin_tka', $this->data('TKA (3 angka)', '2032-01-06'), $ctx)['id'];
        $this->setujui($h);
        $this->cek('format {urut3} → 002/SMK-BN/PKL/I/2032', ($svc->terbitkan($h, $ctx)['surat']['nomor'] ?? '') === '002/SMK-BN/PKL/I/2032');
        $this->atur(['format_nomor' => '{urut}00/X']);
        $i = (int) $svc->buat('izin_tka', $this->data('TKA (format rusak)', '2032-01-07'), $ctx)['id'];
        $this->setujui($i);
        $this->cek('format tersimpan RUSAK "{urut}00/X" → pakai format bawaan, nomor tidak ngawur: 3/SMK-BN/PKL/I/2032', ($svc->terbitkan($i, $ctx)['surat']['nomor'] ?? '') === '3/SMK-BN/PKL/I/2032');
        $this->atur(['format_nomor' => self::BAKU]);

        // Surat Izin PKL sesudah banyak surat sekolah: tetap tak bentrok
        $aj2 = $this->buatAjuanDisetujui($this->siswaUji[1], 'ZZUJISS Nomor Dua');
        $pk2 = (new PklSurat())->terbitkan($aj2, '2032-01-08', $ctx);
        $this->cek('Surat Izin PKL tahun 2032 sesudah 3 surat sekolah → nomor 4/SMK-BN/PKL/I/2032', ($pk2['surat']['nomor'] ?? '') === '4/SMK-BN/PKL/I/2032', (string) ($pk2['surat']['nomor'] ?? json_encode($pk2)));
        $ganda = (int) $this->db->query('SELECT COUNT(*) n FROM (SELECT tahun, urut FROM pkl_surat WHERE tahun IN (2031, 2032) UNION ALL SELECT tahun, urut FROM surat_sekolah WHERE tahun IN (2031, 2032)) x GROUP BY tahun, urut HAVING COUNT(*) > 1')->getNumRows();
        $this->cek('tidak ada pasangan (tahun, urut) kembar di gabungan kedua tabel', $ganda === 0);
    }

    // =================================================================
    // 5. Sidik & tabel
    // =================================================================

    private function ujiSidikDanTabel(): void
    {
        $this->bagian('Sidik data ("perlu cetak ulang") dan kunci asing');
        $svc = new SuratSekolah();
        $p   = (new PklPengaturanModel())->ambil();
        $m   = $svc->muat($this->sB);
        $s0  = SuratSekolah::sidik($m['surat'], $m['siswa'], $p);
        $this->cek('sidik: 40 heksadesimal, stabil di pemanggilan berulang', preg_match('/^[0-9a-f]{40}$/', $s0) === 1 && $s0 === SuratSekolah::sidik($m['surat'], $m['siswa'], $p));

        $beda = $m['surat'];
        $beda['isi'] = '{"x":1}';
        $this->cek('sidik berubah bila isi surat berubah', SuratSekolah::sidik($beda, $m['siswa'], $p) !== $s0);
        $hp = $m['siswa'];
        $hp[0]['hp'] = '089999999999';
        $this->cek('sidik berubah bila HP siswa berubah', SuratSekolah::sidik($m['surat'], $hp, $p) !== $s0);
        $nama = $m['siswa'];
        $nama[1]['nama'] = 'Nama Lain';
        $this->cek('sidik berubah bila nama siswa (data induk) berubah', SuratSekolah::sidik($m['surat'], $nama, $p) !== $s0);
        $this->cek('sidik berubah bila urutan siswa di surat berubah', SuratSekolah::sidik($m['surat'], array_reverse($m['siswa']), $p) !== $s0);
        $this->cek('sidik berubah bila Kepala Sekolah / Waka Hubin / kontak NB berubah', SuratSekolah::sidik($m['surat'], $m['siswa'], ['kepsek_nama' => 'Orang Lain'] + $p) !== $s0
            && SuratSekolah::sidik($m['surat'], $m['siswa'], ['waka_hubin_nama' => 'Hubin Baru'] + $p) !== $s0 && SuratSekolah::sidik($m['surat'], $m['siswa'], ['kontak_surat_hp' => '0800'] + $p) !== $s0);
        $acc = $m['surat'];
        $acc['acc_nama'] = 'Seseorang';
        $acc['acc_at'] = '2031-03-10 10:00:00';
        $this->cek('sidik berubah bila catatan ACC berubah', SuratSekolah::sidik($acc, $m['siswa'], $p) !== $s0);
        $sama = $m['surat'];
        $sama['judul'] = 'Judul lain';
        $sama['status'] = 'dibatalkan';
        $sama['cetak_ke'] = 9;
        $sama['nomor'] = '9/X';
        $this->cek('sidik TIDAK berubah oleh judul / status / jumlah unduhan / nomor', SuratSekolah::sidik($sama, $m['siswa'], $p) === $s0);

        // Mesin penyimpan
        $ada = [];
        foreach (['surat_sekolah', 'surat_sekolah_siswa', 'surat_sekolah_riwayat'] as $t) {
            $e = $this->db->query('SELECT ENGINE e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t])->getRowArray();
            $ada[$t] = $e['e'] ?? null;
        }
        $this->cek('3 tabel ada dan berjenis InnoDB (transaksi & kunci asing)', array_values($ada) === ['InnoDB', 'InnoDB', 'InnoDB'], json_encode($ada));
        $this->cek('kolom pkl_pengaturan.surat_perlu_acc ada', $this->db->fieldExists('surat_perlu_acc', 'pkl_pengaturan'));

        // UNIQUE(tahun, urut)
        $x = (int) $svc->buat('izin_tka', $this->data('Kembar 1', '2033-02-01'), $this->konteks())['id'];
        $y = (int) $svc->buat('izin_tka', $this->data('Kembar 2', '2033-02-01'), $this->konteks())['id'];
        $this->db->table('surat_sekolah')->where('id', $x)->update(['tahun' => 2033, 'urut' => 7]);
        $ditolak = false;
        try {
            $ok = $this->db->table('surat_sekolah')->where('id', $y)->update(['tahun' => 2033, 'urut' => 7]);
            $ditolak = ($ok === false);
        } catch (\Throwable $e) {
            $ditolak = true;
        }
        $this->cek('UNIQUE(tahun, urut): dua surat sekolah tak bisa memegang nomor yang sama', $ditolak && $this->surat($y)['urut'] === null && (int) $this->surat($x)['urut'] === 7);

        // CASCADE: hapus surat → siswa & riwayat ikut hilang
        $this->db->table('surat_sekolah')->where('id', $this->sC)->delete();
        $this->cek('hapus surat → baris siswa & riwayat ikut terhapus (CASCADE)', $this->jumlah('surat_sekolah_siswa', ['surat_id' => $this->sC]) === 0 && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $this->sC]) === 0);

        // SET NULL: hapus ajuan → surat tetap ada, tautan putus, nama perusahaan tetap
        $aj = $this->buatAjuanDisetujui($this->siswaUji[2], 'ZZUJISS Tautan');
        $z  = (int) $svc->buat('balasan_pkl', $this->data('Balasan tertaut', '2033-03-01', [], [], $aj, 'ZZUJISS Tautan'), $this->konteks())['id'];
        $this->cek('surat tertaut ke ajuan (pengajuan_id terisi)', (int) $this->surat($z)['pengajuan_id'] === $aj && $aj > 0);
        $this->db->table('pkl_pengajuan')->where('id', $aj)->delete();
        $s = $this->surat($z);
        $this->cek('hapus ajuan → surat tetap ada, pengajuan_id jadi NULL, nama perusahaan tetap tercatat', $s !== [] && $s['pengajuan_id'] === null && $s['perusahaan_nama'] === 'ZZUJISS Tautan');

        // CASCADE dari siswa
        $hapusSiswa = $this->siswaUji[0];
        $this->db->table('siswa')->where('id', $hapusSiswa)->delete();
        $this->cek('hapus siswa → barisnya di surat_sekolah_siswa hilang, suratnya tetap', $this->jumlah('surat_sekolah_siswa', ['siswa_id' => $hapusSiswa]) === 0 && $this->surat($this->sB) !== []);
    }

    // =================================================================
    // 6. Tampilan nyata
    // =================================================================

    private function ujiTampilan(): void
    {
        $this->bagian('Tampilan: Daftar Surat & detail dirender nyata per peran (menu, tab, kartu, penjagaan)');
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/surat'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        set_error_handler(static function (int $severity, string $message, string $file, int $line) {
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $render = function (string $peran, string $metode, array $get = [], ...$arg) use ($request): mixed {
                session()->set('admin', ['id' => 2000000001, 'full_name' => self::OLEH . ' ' . $peran, 'username' => 'zzujiss', 'role' => $peran]);
                $request->setGlobal('get', $get);
                $c = new \App\Controllers\Admin\Surat();
                $c->initController($request, service('response'), service('logger'));

                return $c->{$metode}(...$arg);
            };

            $html = [];
            foreach (['operator', 'hubin', 'admin'] as $peran) {
                $html[$peran] = $render($peran, 'index');
            }
            $semuaStr = is_string($html['operator']) && is_string($html['hubin']) && is_string($html['admin']);
            $this->cek('Daftar Surat dirender (string HTML) untuk Operator, Hubin, Admin tanpa galat', $semuaStr);
            if (! $semuaStr) {
                return;
            }

            $this->cek('judul halaman & tab: Daftar Surat, Semua, Menunggu ACC, Siap unduh, Dikembalikan, Dibatalkan',
                str_contains($html['operator'], 'Daftar Surat') && str_contains($html['operator'], 'Menunggu ACC') && str_contains($html['operator'], 'Siap unduh')
                && str_contains($html['operator'], 'Dikembalikan') && str_contains($html['operator'], 'Dibatalkan'));
            $this->cek('daftar memuat surat uji (judul, jenis, "belum bernomor", nomor yang sudah terbit)', str_contains($html['operator'], 'ZZUJI SS') && str_contains($html['operator'], 'belum bernomor') && str_contains($html['operator'], 'SMK-BN/PKL'));
            // Menu samping: tiap jenis = tautan (halaman sudah dibangun) atau item redup "— segera hadir" (belum).
            $menuJenis = static function (string $h, string $k): ?string {
                if (SuratJenis::siap($k)) {
                    return preg_match('~href="[^"]*/' . preg_quote(SuratJenis::alamat($k), '~') . '"~', $h) === 1 ? 'tautan' : null;
                }

                return str_contains($h, 'title="' . SuratJenis::label($k) . ' — segera hadir"') ? 'segera' : null;
            };
            $this->cek('menu samping Operator: "Daftar Surat" berupa tautan aktif', (bool) preg_match('~href="[^"]*/admin/surat"~', $html['operator']));
            $kurangOp = array_filter(SuratJenis::kode(), static fn (string $k) => $menuJenis($html['operator'], $k) === null);
            $kurangAd = array_filter(SuratJenis::kode(), static fn (string $k) => $menuJenis($html['admin'], $k) === null);
            $this->cek('menu samping Operator: kelima jenis surat ada (aktif bila SIAP, redup "segera hadir" bila belum)', $kurangOp === [], implode(', ', $kurangOp));
            $this->cek('menu samping Admin: kelima jenis surat ada', $kurangAd === [], implode(', ', $kurangAd));
            $bocorHubin = array_filter(SuratJenis::kode(), static fn (string $k) => $menuJenis($html['hubin'], $k) !== null);
            $this->cek('menu samping Waka Hubin: "Daftar Surat" ada; menu jenis surat TIDAK tampil (belum diberi hak)',
                (bool) preg_match('~href="[^"]*/admin/surat"~', $html['hubin']) && $bocorHubin === [] && ! str_contains($html['hubin'], 'segera hadir'), implode(', ', $bocorHubin));
            $this->cek('Hubin tetap tidak melihat Data Siswa / Isian Biodata (larangan lama utuh)', ! str_contains($html['hubin'], 'Isian Biodata Siswa') && ! str_contains($html['hubin'], '>Data Siswa<'));

            // Saringan
            $f = $render('operator', 'index', ['status' => 'menunggu', 'q' => 'Balasan PT Uji Surat']);
            $this->cek('saringan status+kata kunci: hanya Balasan yang menunggu tampil (Pernyataan & ASTS tidak)', is_string($f) && str_contains($f, 'Balasan PT Uji Surat') && ! str_contains($f, 'Pernyataan XI TKJ') && ! str_contains($f, 'ASTS Ganjil 2031'));
            $f2 = $render('operator', 'index', ['status' => 'ngawur', 'jenis' => 'ngawur', 'tahun' => '99999', 'q' => str_repeat('x', 300), 'page' => '-5']);
            $this->cek('parameter ngawur (status, jenis, tahun, q 300 huruf, page negatif) tidak membuat galat', is_string($f2));
            $f3 = $render('operator', 'index', ['jenis' => 'pernyataan_ortu', 'q' => 'ZZUJI SS']);
            $this->cek('saringan jenis: hanya Pernyataan yang tampil (judul Balasan tidak ada)', is_string($f3) && str_contains($f3, 'Pernyataan XI TKJ') && ! str_contains($f3, 'Balasan PT Uji Surat'));
            $f4 = $render('operator', 'index', ['q' => 'zzz-tidak-ada-zzz']);
            $this->cek('pencarian tanpa hasil → pesan "Tidak ada surat yang cocok"', is_string($f4) && str_contains($f4, 'Tidak ada surat yang cocok'));

            // Detail
            $det = $render('operator', 'detail', [], $this->sB);
            $this->cek('detail Pernyataan: judul, kode SRT-, "tidak bernomor", 2 siswa, riwayat "Surat dibuat", aturan "Tanpa ACC"',
                is_string($det) && str_contains($det, 'Pernyataan XI TKJ') && str_contains($det, \App\Models\SuratSekolahModel::kode($this->sB)) && str_contains($det, 'jenis ini tidak bernomor')
                && str_contains($det, 'ZZUJI SS 2') && str_contains($det, 'Surat dibuat') && str_contains($det, 'Tanpa ACC'));
            $det2 = $render('hubin', 'detail', [], $this->sA);
            $this->cek('detail ASTS (menunggu): "Menunggu persetujuan", "Wajib ACC", isian ringkas (Kegiatan ASTS, tanggal Indonesia), terbuka untuk Hubin',
                is_string($det2) && str_contains($det2, 'Menunggu persetujuan') && str_contains($det2, 'Wajib ACC') && str_contains($det2, '14 September 2031') && str_contains($det2, 'Kegiatan'));
            $det3 = $render('operator', 'detail', [], 2000000000);
            $this->cek('detail id tak ada → dialihkan ke Daftar Surat dengan pesan', $det3 instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($det3->getHeaderLine('Location'), 'admin/surat'));
        } finally {
            restore_error_handler();
            session()->remove('admin');
        }
    }

    // =================================================================
    // 7. Keputusan: ACC, kembalikan, ajukan ulang, batal, ACC massal, aturan ACC per jenis
    // =================================================================

    /** @return array<string, mixed> konteks pelaku seperti yang dibentuk controller */
    private function ctx(string $peran): array
    {
        return ['oleh' => self::OLEH . ' ' . $peran, 'admin_id' => ['hubin' => 7, 'admin' => 8, 'operator' => 9][$peran] ?? null, 'peran' => $peran, 'ip' => self::IP, 'saluran' => 'web'];
    }

    /** Surat uji baru; status awal mengikuti aturan jenisnya. */
    private function baru(string $jenis, string $judul, string $tanggal = '2032-05-10', array $siswa = [], string $perusahaan = ''): int
    {
        $r = (new SuratSekolah())->buat($jenis, $this->data($judul, $tanggal, [], $siswa, null, $perusahaan), $this->konteks());

        return (int) ($r['id'] ?? 0);
    }

    private function auditAda(string $potongan): bool
    {
        return (int) $this->db->table('audit_log')->like('deskripsi', $potongan)->countAllResults() > 0;
    }

    private function riwayatCatatan(int $id, string $aksi): ?string
    {
        $r = $this->db->table('surat_sekolah_riwayat')->select('catatan')->where('surat_id', $id)->where('aksi', $aksi)->orderBy('id', 'DESC')->get()->getRowArray();

        return $r['catatan'] ?? null;
    }

    private function ujiKeputusan(): void
    {
        $this->bagian('Keputusan: ACC, kembalikan, ajukan ulang, batal, ACC massal, aturan ACC per jenis');
        $this->netralkanPengaturan();
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
        $svc = new SuratSekolah();
        $kep = new \App\Libraries\SuratKeputusan();
        $p   = (new PklPengaturanModel())->ambil();
        [$op, $hu, $ad] = [$this->ctx('operator'), $this->ctx('hubin'), $this->ctx('admin')];
        [$s1, $s2] = $this->siswaUji;
        // Surat uji dari bagian lain tidak boleh ikut tersentuh "ACC semua yang aman".
        $this->db->table('surat_sekolah')->where('dibuat_nama', self::OLEH)->where('status', 'menunggu')->update(['status' => 'dibatalkan']);
        $nyata = (int) $this->db->table('surat_sekolah')->where('status', 'menunggu')->where('dibuat_nama !=', self::OLEH)->countAllResults();

        // --- ACC oleh yang berhak / tidak berhak
        $a1 = $this->baru('izin_asts', 'Kep ASTS A1');
        $r  = $kep->putuskan($a1, 'acc', [], $op, $p);
        $this->cek('Operator tidak boleh ACC → dilarang 403, status tetap menunggu', $r['ok'] === false && $r['kode'] === 'dilarang' && $r['http'] === 403 && $this->surat($a1)['status'] === 'menunggu');
        $this->cek('penolakan tercatat di Audit Log (DITOLAK + nama pelaku)', $this->auditAda('DITOLAK: Operator Sekolah ' . self::OLEH . ' operator mencoba "acc"'));
        $this->cek('peran asing (guru) juga ditolak', $kep->putuskan($a1, 'acc', [], $this->ctx('guru'), $p)['kode'] === 'dilarang');
        $this->cek('aksi ngawur → gagal 422; id tak ada → tidak_ada 404', $kep->putuskan($a1, 'hapus', [], $hu, $p)['kode'] === 'gagal' && ($x = $kep->putuskan(2000000000, 'acc', [], $hu, $p))['kode'] === 'tidak_ada' && $x['http'] === 404);
        $r   = $kep->putuskan($a1, 'acc', [], $hu, $p);
        $row = $this->surat($a1);
        $this->cek('Hubin ACC → disetujui; tercatat nama, peran, akun, jam, IP; catatan kosong', $r['ok'] === true && $row['status'] === 'disetujui' && $row['acc_nama'] === self::OLEH . ' hubin' && $row['acc_peran'] === 'hubin'
            && (int) $row['acc_admin_id'] === 7 && ! empty($row['acc_at']) && $row['acc_ip'] === self::IP && $row['catatan_staf'] === null, json_encode($r));
        $this->cek('kode verifikasi SRT-00012-8F3A9C: bentuk benar, cocok dihitung ulang, berubah bila akunnya beda',
            preg_match('/^SRT-\d{5}-[0-9A-F]{6}$/', (string) $row['acc_kode']) === 1 && $row['acc_kode'] === \App\Libraries\SuratKeputusan::kodeVerifikasi($a1, $row['acc_at'], 7)
            && $row['acc_kode'] !== \App\Libraries\SuratKeputusan::kodeVerifikasi($a1, $row['acc_at'], 8));
        $this->cek('riwayat "acc" oleh Hubin tercatat; Audit Log mencatat siapa yang menyetujui', $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $a1, 'aksi' => 'acc', 'peran' => 'hubin']) === 1 && $this->auditAda('disetujui — oleh ' . self::OLEH . ' hubin'));
        $r = $kep->putuskan($a1, 'acc', [], $hu, $p);
        $this->cek('ACC kedua kali → status 409 (sudah disetujui)', $r['kode'] === 'status' && $r['http'] === 409);

        // --- Admin sebagai cadangan
        $a2 = $this->baru('izin_tka', 'Kep TKA A2');
        $r  = $kep->putuskan($a2, 'acc', ['catatan' => 'Hubin sedang dinas luar'], $ad, $p);
        $this->cek('Admin ACC tanpa pernyataan "mewakili" (sudah dihapus) → disetujui, acc_peran=admin, catatan riwayat apa adanya', $r['ok'] === true && $this->surat($a2)['acc_peran'] === 'admin' && $this->riwayatCatatan($a2, 'acc') === 'Hubin sedang dinas luar', json_encode($r));

        // --- Kembalikan & ajukan ulang
        $a3 = $this->baru('izin_tka', 'Kep TKA A3');
        $this->cek('Operator tidak boleh mengembalikan surat (hak ACC) → dilarang', $kep->putuskan($a3, 'kembalikan', ['catatan' => 'Tanggal ujian salah'], $op, $p)['kode'] === 'dilarang');
        $this->cek('kembalikan wajib alasan ≥ 5 huruf ("abc" ditolak), ≤ 255; status tetap', $kep->putuskan($a3, 'kembalikan', ['catatan' => 'abc'], $hu, $p)['kode'] === 'catatan'
            && $kep->putuskan($a3, 'kembalikan', ['catatan' => str_repeat('x', 256)], $hu, $p)['kode'] === 'catatan' && $this->surat($a3)['status'] === 'menunggu');
        $r   = $kep->putuskan($a3, 'kembalikan', ['catatan' => '  Tanggal   ujian salah,  cek kalender  '], $hu, $p);
        $row = $this->surat($a3);
        $this->cek('Hubin mengembalikan dengan alasan → dikembalikan; alasan dirapikan & tersimpan; kolom ACC kosong', $r['ok'] === true && $row['status'] === 'dikembalikan' && $row['catatan_staf'] === 'Tanggal ujian salah, cek kalender' && $row['acc_at'] === null);
        $this->cek('dari "dikembalikan" tidak bisa langsung di-ACC (harus diajukan ulang) → status 409', $kep->putuskan($a3, 'acc', [], $hu, $p)['kode'] === 'status');
        $this->cek('Hubin (tanpa hak surat_sekolah) tidak boleh mengajukan ulang → dilarang', $kep->putuskan($a3, 'ajukan_ulang', [], $hu, $p)['kode'] === 'dilarang');
        $this->db->table('surat_sekolah')->where('id', $a3)->update(['diajukan_at' => '2000-01-01 00:00:00']);
        $r   = $kep->putuskan($a3, 'ajukan_ulang', [], $op, $p);
        $row = $this->surat($a3);
        $this->cek('Operator mengajukan ulang → menunggu lagi; jam diajukan baru; catatan kosong; riwayat "ajukan_ulang"', $r['ok'] === true && $row['status'] === 'menunggu' && substr((string) $row['diajukan_at'], 0, 4) !== '2000'
            && $row['catatan_staf'] === null && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $a3, 'aksi' => 'ajukan_ulang']) === 1);
        $this->cek('ajukan ulang untuk surat yang tidak dikembalikan → status 409', $kep->putuskan($a3, 'ajukan_ulang', [], $op, $p)['kode'] === 'status');

        // --- Batal ACC & nomor
        $a4 = $this->baru('balasan_pkl', 'Kep Balasan A4', '2032-05-10', [$s1, $s2], 'ZZUJISS Perusahaan A4');
        $kep->putuskan($a4, 'acc', [], $hu, $p);
        $this->cek('Operator tidak boleh membatalkan persetujuan → dilarang; tanpa alasan → catatan', $kep->putuskan($a4, 'batal_acc', ['catatan' => 'Ada siswa salah masuk'], $op, $p)['kode'] === 'dilarang' && $kep->putuskan($a4, 'batal_acc', [], $hu, $p)['kode'] === 'catatan');
        $r   = $kep->putuskan($a4, 'batal_acc', ['catatan' => 'Ada siswa yang salah masuk daftar'], $hu, $p);
        $row = $this->surat($a4);
        $this->cek('Hubin membatalkan persetujuan (belum bernomor) → dikembalikan; SEMUA kolom ACC kosong; alasan tersimpan', $r['ok'] === true && $row['status'] === 'dikembalikan' && $row['acc_at'] === null && $row['acc_nama'] === null && $row['acc_peran'] === null
            && $row['acc_kode'] === null && $row['acc_admin_id'] === null && $row['catatan_staf'] === 'Ada siswa yang salah masuk daftar');
        $kep->putuskan($a4, 'ajukan_ulang', [], $op, $p);
        $kep->putuskan($a4, 'acc', [], $hu, $p);
        $t = $svc->terbitkan($a4, $op);
        $r = $kep->putuskan($a4, 'batal_acc', ['catatan' => 'Mau dibatalkan lagi'], $hu, $p);
        $this->cek('setelah bernomor, persetujuan TIDAK bisa dibatalkan (kode terbit 409); status tetap disetujui', $t['ok'] === true && $r['kode'] === 'terbit' && $r['http'] === 409 && $this->surat($a4)['status'] === 'disetujui');

        // --- Batal surat (nomor tetap tercatat dan tidak dipakai lagi)
        $nomorA4 = (string) $this->surat($a4)['nomor'];
        $urutA4  = (int) $this->surat($a4)['urut'];
        $r       = $kep->putuskan($a4, 'batal', ['catatan' => 'Perusahaan menarik diri'], $op, $p);
        $row     = $this->surat($a4);
        $this->cek('Operator membatalkan surat yang sudah bernomor → dibatalkan; nomor TETAP tercatat; pesan menyebut nomornya', $r['ok'] === true && $row['status'] === 'dibatalkan' && $row['nomor'] === $nomorA4 && $nomorA4 !== '' && str_contains($r['pesan'], $nomorA4) && $row['catatan_staf'] === 'Perusahaan menarik diri');
        $a8 = $this->baru('izin_tka', 'Kep TKA A8 nomor', '2032-05-10');
        $kep->putuskan($a8, 'acc', [], $hu, $p);
        $t8 = $svc->terbitkan($a8, $op);
        $this->cek('nomor surat yang dibatalkan TIDAK dipakai lagi: surat berikutnya mendapat urut lebih besar', $t8['ok'] === true && (int) $t8['surat']['urut'] > $urutA4, 'urut ' . $urutA4 . ' → ' . ($t8['surat']['urut'] ?? '?'));
        $a7 = $this->baru('izin_asts', 'Kep ASTS A7 batal');
        $this->cek('Hubin (tanpa hak surat_sekolah) tidak boleh membatalkan surat; tanpa alasan → catatan', $kep->putuskan($a7, 'batal', ['catatan' => 'Salah buat surat'], $hu, $p)['kode'] === 'dilarang' && $kep->putuskan($a7, 'batal', [], $op, $p)['kode'] === 'catatan');
        $r = $kep->putuskan($a7, 'batal', ['catatan' => 'Salah buat surat'], $op, $p);
        $this->cek('Operator membatalkan surat yang menunggu → dibatalkan; sesudahnya ACC / batal lagi / ajukan ulang → status 409', $r['ok'] === true && $this->surat($a7)['status'] === 'dibatalkan' && $kep->putuskan($a7, 'acc', [], $hu, $p)['kode'] === 'status'
            && $kep->putuskan($a7, 'batal', ['catatan' => 'sekali lagi ya'], $op, $p)['kode'] === 'status' && $kep->putuskan($a7, 'ajukan_ulang', [], $op, $p)['kode'] === 'status');

        // --- Pemeriksaan sebelum ACC & jenis tanpa ACC
        $a5 = $this->baru('penarikan_pkl', 'Kep Penarikan A5 tanpa siswa', '2032-05-10', [], 'ZZUJISS Perusahaan A5');
        $r  = $kep->putuskan($a5, 'acc', [], $hu, $p);
        $this->cek('surat tanpa siswa (jenis butuh siswa) → ACC ditolak: bahaya 422, status tetap', $r['kode'] === 'bahaya' && $r['http'] === 422 && $this->surat($a5)['status'] === 'menunggu');
        $this->cek('dengan centang "sudah saya periksa" → disetujui', $kep->putuskan($a5, 'acc', ['paham' => '1'], $hu, $p)['ok'] === true);
        $a6 = $this->baru('pernyataan_ortu', 'Kep Pernyataan A6', '2032-05-10', [$s1]);
        $this->cek('Pernyataan (tanpa ACC) langsung disetujui; ACC / batal ACC padanya → status 409', $this->surat($a6)['status'] === 'disetujui' && $kep->putuskan($a6, 'acc', [], $hu, $p)['kode'] === 'status' && $kep->putuskan($a6, 'batal_acc', ['catatan' => 'coba batalkan'], $hu, $p)['kode'] === 'status');

        // --- ACC massal
        $m1 = $this->baru('izin_asts', 'Kep Massal M1');
        $m2 = $this->baru('izin_asts', 'Kep Massal M2');
        $m3 = $this->baru('balasan_pkl', 'Kep Massal M3 tanpa siswa', '2032-05-10', [], 'ZZUJISS Perusahaan M3');
        $this->cek('ACC massal: Operator (tanpa hak ACC jenis apa pun) → dilarang 403; pilihan kosong → catatan', $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m1]], $op, $p)['http'] === 403
            && $kep->accMassal(['mode' => 'terpilih', 'ids' => []], $hu, $p)['kode'] === 'catatan');
        $r = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m1, $m2, $m3]], $hu, $p);
        $this->cek('ACC massal terpilih [M1, M2, M3]: 2 disetujui, 1 dilewati (M3 tanpa siswa) dengan alasannya', $r['ok'] === true && $r['disetujui'] === 2 && count($r['dilewati']) === 1 && str_contains($r['dilewati'][0], \App\Models\SuratSekolahModel::kode($m3))
            && $this->surat($m1)['status'] === 'disetujui' && $this->surat($m2)['status'] === 'disetujui' && $this->surat($m3)['status'] === 'menunggu', json_encode($r));
        $this->cek('riwayat massal bercatatan "ACC massal"; Audit Log mencatat ringkasan + nama pelaku', $this->riwayatCatatan($m1, 'acc') === 'ACC massal' && $this->auditAda('ACC massal surat sekolah oleh ' . self::OLEH . ' hubin: 2 disetujui, 1 dilewati'));
        $m4 = $this->baru('izin_asts', 'Kep Massal M4 admin');
        $r  = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m4], 'wakil' => '1'], $ad, $p);
        $this->cek('Admin ACC massal (kiriman "wakil" lama diabaikan) → disetujui; acc_peran=admin; riwayat "ACC massal"', $r['disetujui'] === 1 && $this->surat($m4)['acc_peran'] === 'admin' && $this->riwayatCatatan($m4, 'acc') === 'ACC massal');
        $this->atur(['kontak_surat_nama' => '']);
        $p2 = (new PklPengaturanModel())->ambil();
        $m5 = $this->baru('izin_asts', 'Kep Massal M5 awas');
        $r  = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m5]], $hu, $p2);
        $this->cek('peringatan "awas" (kontak NB kosong): ACC massal melewatinya; ACC satuan tetap boleh', $r['disetujui'] === 0 && count($r['dilewati']) === 1 && str_contains($r['dilewati'][0], 'Kontak "NB"') && $kep->putuskan($m5, 'acc', [], $hu, $p2)['ok'] === true);
        $this->atur(['kontak_surat_nama' => 'Kontak Uji']);
        if ($nyata === 0) {
            $this->db->table('surat_sekolah')->where('dibuat_nama', self::OLEH)->where('status', 'menunggu')->update(['status' => 'dibatalkan']);
            $n1 = $this->baru('izin_asts', 'Kep Aman N1');
            $n2 = $this->baru('penarikan_pkl', 'Kep Aman N2 tanpa siswa', '2032-05-10', [], 'ZZUJISS Perusahaan N2');
            $r  = $kep->accMassal(['mode' => 'aman'], $hu, $p);
            $this->cek('ACC massal mode "aman": N1 (tanpa peringatan) di-ACC, N2 (tanpa siswa) dilewati', $r['disetujui'] === 1 && count($r['dilewati']) === 1 && $this->surat($n1)['status'] === 'disetujui' && $this->surat($n2)['status'] === 'menunggu', json_encode($r));
        } else {
            $this->cek('ACC massal mode "aman" dilewati: ada ' . $nyata . ' surat sungguhan yang menunggu di database ini', true);
        }

        // --- Aturan ACC per jenis (Admin)
        $adm = ['oleh' => self::OLEH . ' admin'];
        $r   = SuratJenis::simpanAcc(['izin_asts', 'balasan_pkl', 'ngawur', 'pernyataan_ortu'], $adm);
        $peta = SuratJenis::petaAcc((new PklPengaturanModel())->ambil());
        $this->cek('Admin menyimpan aturan ACC: ASTS & Balasan wajib; TKA & Penarikan tanpa ACC; kode asing & Pernyataan diabaikan',
            $r['ok'] === true && $peta['izin_asts'] === true && $peta['izin_tka'] === false && $peta['balasan_pkl'] === true && $peta['penarikan_pkl'] === false && $peta['pernyataan_ortu'] === false && count($r['ubah']) === 2, json_encode($r['ubah']));
        $simpan = json_decode((string) $this->db->table('pkl_pengaturan')->select('surat_perlu_acc')->where('id', 1)->get()->getRowArray()['surat_perlu_acc'], true);
        $this->cek('tersimpan sebagai JSON hanya untuk 4 jenis bernomor', is_array($simpan) && array_keys($simpan) === ['izin_asts', 'izin_tka', 'balasan_pkl', 'penarikan_pkl']);
        $tka = $this->baru('izin_tka', 'Kep TKA tanpa ACC (aturan baru)');
        $this->cek('surat TKA yang dibuat sesudahnya langsung siap unduh (tanpa ACC), perlu_acc=0', $this->surat($tka)['status'] === 'disetujui' && (int) $this->surat($tka)['perlu_acc'] === 0);
        $r2 = SuratJenis::simpanAcc(['izin_asts', 'balasan_pkl'], $adm);
        $this->cek('menyimpan hal yang sama → tidak ada perubahan; perubahan tercatat di Audit Log', $r2['ubah'] === [] && str_contains($r2['pesan'], 'Tidak ada perubahan') && $this->auditAda('Aturan ACC surat sekolah diubah oleh ' . self::OLEH . ' admin'));
        $this->atur(['surat_perlu_acc' => null]);

        // --- Hak ACC per jenis (diatur Admin): Balasan di-ACC Operator saja; dua peran sama-sama ACC → yang pertama tercatat
        $kirim = PklHak::bawaanLengkap();
        $kirim['operator'][] = 'surat_acc:balasan_pkl';
        $kirim['hubin'] = array_values(array_diff($kirim['hubin'], ['surat_acc:balasan_pkl']));
        PklHak::simpan($kirim, ['oleh' => self::OLEH . ' admin'], true);
        $p3 = (new PklPengaturanModel())->ambil();
        $j1 = $this->baru('balasan_pkl', 'Kep Jenis J1', '2032-05-10', [$s1], 'ZZUJISS Perusahaan J1');
        $j2 = $this->baru('izin_asts', 'Kep Jenis J2');
        $x  = $kep->putuskan($j1, 'acc', [], $hu, $p3);
        $this->cek('Balasan di-ACC Operator saja: Hubin ditolak (pesan menyebut pemegangnya), Operator boleh; Operator tetap TIDAK boleh ACC Izin ASTS',
            $x['kode'] === 'dilarang' && str_contains($x['pesan'], 'Operator Sekolah') && $kep->putuskan($j1, 'acc', [], $op, $p3)['ok'] === true
            && $this->surat($j1)['acc_peran'] === 'operator' && $kep->putuskan($j2, 'acc', [], $op, $p3)['kode'] === 'dilarang', $x['pesan']);
        $j3 = $this->baru('balasan_pkl', 'Kep Jenis J3', '2032-05-10', [$s2], 'ZZUJISS Perusahaan J3');
        $j4 = $this->baru('izin_asts', 'Kep Jenis J4');
        $r  = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$j3, $j4]], $op, $p3);
        $this->cek('ACC massal Operator: hanya Balasan yang di-ACC; Izin ASTS dilewati dengan alasan "tidak punya hak ACC"', $r['disetujui'] === 1 && $this->surat($j3)['status'] === 'disetujui' && $this->surat($j4)['status'] === 'menunggu'
            && count($r['dilewati']) === 1 && str_contains($r['dilewati'][0], 'tidak punya hak ACC Surat Izin ASTS'), json_encode($r));
        $kirim['hubin'][] = 'surat_acc:balasan_pkl'; // dua peran sama-sama memegang ACC Balasan
        PklHak::simpan($kirim, ['oleh' => self::OLEH . ' admin'], true);
        $j5 = $this->baru('balasan_pkl', 'Kep Jenis J5', '2032-05-10', [$s1], 'ZZUJISS Perusahaan J5');
        $r1 = $kep->putuskan($j5, 'acc', [], $op, $p3);
        $r2 = $kep->putuskan($j5, 'acc', [], $hu, $p3);
        $this->cek('Operator & Hubin sama-sama ACC Balasan: yang pertama (Operator) tercatat; yang kedua status 409 — tidak ada ACC ganda', $r1['ok'] === true && $r2['kode'] === 'status'
            && $this->surat($j5)['acc_peran'] === 'operator' && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $j5, 'aksi' => 'acc']) === 1);
        $j6 = $this->baru('balasan_pkl', 'Kep Jenis J6', '2032-05-10', [$s2], 'ZZUJISS Perusahaan J6');
        $sa = $kep->accSetelahBuat([$j6], 'balasan_pkl', $op, $p3);
        $j7 = $this->baru('izin_asts', 'Kep Jenis J7');
        $sb = $kep->accSetelahBuat([$j7], 'izin_asts', $op, $p3);
        $this->cek('"Simpan & setujui": Operator (Buat + ACC Balasan) → langsung disetujui; Izin ASTS (tanpa hak ACC) → tetap menunggu tanpa galat',
            $sa['acc'] === 1 && $this->surat($j6)['status'] === 'disetujui' && $this->surat($j6)['acc_peran'] === 'operator' && $sb['acc'] === 0 && $this->surat($j7)['status'] === 'menunggu');
        $this->setelPeranBawaan();
        $this->atur(['surat_perlu_acc' => null]);
    }

    // =================================================================
    // 8. Berkas Word: kaki surat, tanda tangan, penanda, pembukuan, massal, ZIP
    // =================================================================

    private int $b1 = 0;
    private int $b2 = 0;

    /** Teks polos sebuah berkas .docx (paragraf dipisah baris baru). */
    private function isiDocx(string $biner): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ujisrt');
        file_put_contents($tmp, $biner);
        $zip = new \ZipArchive();
        $xml = '';
        if ($zip->open($tmp) === true) {
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
        }
        @unlink($tmp);

        return html_entity_decode(strip_tags((string) preg_replace('/<\/w:p>/', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function ujiBerkas(): void
    {
        $this->bagian('Berkas Word: kaki surat, tanda tangan, penanda, pembukuan unduhan, massal, ZIP');
        $this->netralkanPengaturan();
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
        $svc = new SuratSekolah();
        $kep = new \App\Libraries\SuratKeputusan();
        $bk  = new \App\Libraries\SuratBerkas();
        $p   = (new PklPengaturanModel())->ambil();
        [$op, $hu, $ad] = [$this->ctx('operator'), $this->ctx('hubin'), $this->ctx('admin')];
        [$s1, $s2] = $this->siswaUji;
        $templateUji = APPPATH . PklSurat::TEMPLATE_BAWAAN;
        // izin_asts sengaja diarahkan ke berkas yang TIDAK ada (template aslinya dibuat di langkah berikutnya) untuk menguji galat "template".
        SuratJenis::$templateTimpa = ['izin_tka' => $templateUji, 'balasan_pkl' => $templateUji, 'izin_asts' => APPPATH . 'Libraries/Surat/__tidak_ada__.docx'];

        // --- Kaki surat
        $B = \App\Libraries\SuratBerkas::class;
        $dasar = ['perlu_acc' => 1, 'acc_nama' => 'Budi Hubin', 'acc_peran' => 'hubin', 'acc_at' => '2031-03-10 09:41:00', 'acc_kode' => 'SRT-00012-ABC123'];
        $ekstra = ['oleh' => 'Siti Operator', 'peran' => 'operator', 'sekarang' => '2031-03-11 10:05:00'];
        $kaki = $B::kakiSurat($dasar, $p, $ekstra);
        $this->cek('kaki surat wajib-ACC (Hubin): "disetujui secara elektronik", penyetuju + jabatan, waktu, kode, pencetak', str_contains($kaki, 'disetujui secara elektronik') && str_contains($kaki, 'Disetujui oleh: Budi Hubin (') && str_contains($kaki, 'Waktu persetujuan:')
            && str_contains($kaki, 'Kode verifikasi: SRT-00012-ABC123') && str_contains($kaki, 'Dicetak oleh Siti Operator (Operator Sekolah) pada') && str_contains($kaki, 'pukul 10.05 WIB'), $kaki);
        $this->cek('kaki surat ACC oleh Admin: "(Admin Sistem)" apa adanya, tanpa "mewakili"', str_contains($B::kakiSurat(['acc_peran' => 'admin'] + $dasar, $p, $ekstra), '(Admin Sistem)') && ! str_contains($B::kakiSurat(['acc_peran' => 'admin'] + $dasar, $p, $ekstra), 'mewakili'));
        $tanpa = $B::kakiSurat(['perlu_acc' => 0] + $dasar, $p, $ekstra);
        $this->cek('kaki surat tanpa ACC: hanya keterangan cetak (tidak mengaku "disetujui")', str_contains($tanpa, 'Dicetak melalui Sistem Informasi Akademik Sekolah oleh Siti Operator') && ! str_contains($tanpa, 'disetujui') && $B::kakiSurat(['perlu_acc' => 0] + $dasar, $p, []) === '');

        // --- Penanda umum
        $ttd = ['path' => '/x.png', 'ext' => 'png', 'cx' => 1512000, 'cy' => 648000];
        // Jenis tiruan tanpa penanda khusus: yang diuji di sini penanda UMUM (tiap jenis bisa menimpanya lewat khusus()).
        $surat = ['jenis' => 'jenis_uji', 'perlu_acc' => 1, 'nomor' => '12/SMK-BN/PKL/III/2031', 'urut' => 12, 'tanggal_surat' => '2031-03-05', 'perusahaan_nama' => 'PT Contoh', 'isi' => '{}'] + $dasar;
        $siswa = [
            ['siswa_id' => 1, 'nama' => 'Ani', 'nis' => '111', 'nisn' => '', 'nama_kelas' => 'XI TKJ 1', 'jurusan_nama' => null, 'hp' => null, 'hp_master' => '0811'],
            ['siswa_id' => 2, 'nama' => 'Budi', 'nis' => '222', 'nisn' => '999', 'nama_kelas' => 'XI TKJ 1', 'jurusan_nama' => 'Teknik Komputer', 'hp' => '0822', 'hp_master' => '0812'],
        ];
        $ajuan = ['perusahaan_nama' => 'PT Ajuan', 'perusahaan_alamat' => 'Jl. Mawar 1', 'perusahaan_kota' => 'Bekasi', 'kontak_nama' => 'Bu Rini', 'kontak_jabatan' => 'HRD', 'perusahaan_telepon' => '021'];
        $isi = $B::isi($surat, $siswa, $ajuan, ['city' => 'Bekasi', 'school_name' => 'SMK Uji', 'academic_year' => '2031/2032'], $p, ['ttd' => $ttd, 'oleh' => 'X', 'peran' => 'operator']);
        $v = $isi['v'];
        $this->cek('penanda umum: nomor, tanggal "05 Maret 2031", Kepala Sekolah bertitik, Waka Hubin, perusahaan (milik surat), alamat lengkap, penerima', $v['nomor'] === '12/SMK-BN/PKL/III/2031' && $v['tanggal_surat'] === '05 Maret 2031' && $v['kepsek_nama'] === 'Kepsek Uji, S.T.' && $v['hubin_nama'] === 'Hubin Uji, S.Pd.'
            && $v['perusahaan'] === 'PT Contoh' && $v['alamat_lengkap'] === 'Jl. Mawar 1, Bekasi' && $v['penerima'] === 'Bu Rini (HRD)' && $v['tahun_ajaran'] === '2031/2032' && $v['jumlah_siswa'] === '2');
        $this->cek('baris siswa: NISN kosong → "-", jurusan kosong → "-", HP surat > HP induk, HP kosong → "-"', $isi['siswa'][0]['nisn'] === '-' && $isi['siswa'][0]['jurusan'] === '-' && $isi['siswa'][0]['hp'] === '0811' && $isi['siswa'][1]['hp'] === '0822' && $isi['siswa'][1]['nisn'] === '999' && $isi['siswa'][1]['no'] === '2');
        $this->cek('tanda tangan digital Waka Hubin dipasang bila gambarnya ada, SIAPA PUN yang ACC (juga Admin & jenis tanpa ACC); tanpa gambar → kosong', str_starts_with($v['ttd_hubin'], \App\Libraries\PklDocx::RAW) && str_contains($v['ttd_hubin'], PklSurat::REL_TTD)
            && $B::isi(['acc_peran' => 'admin'] + $surat, $siswa, null, [], $p, ['ttd' => $ttd])['v']['ttd_hubin'] !== '' && $B::isi($surat, $siswa, null, [], $p, [])['v']['ttd_hubin'] === ''
            && $B::isi(['perlu_acc' => 0] + $surat, $siswa, null, [], $p, ['ttd' => $ttd])['v']['ttd_hubin'] !== '');
        $this->atur(['waka_hubin_nama' => '']);
        $this->cek('nama Waka Hubin kosong → titik-titik (bukan kosong)', str_starts_with($B::isi($surat, [], null, [], (new PklPengaturanModel())->ambil())['v']['hubin_nama'], '......'));
        $this->atur(['waka_hubin_nama' => 'Hubin Uji, S.Pd.']);

        // --- bangun(): satu surat
        $b1 = $this->baru('izin_tka', 'Berkas TKA B1', '2032-06-01', [$s1, $s2], 'ZZUJISS Perusahaan B1');
        $kep->putuskan($b1, 'acc', [], $hu, $p);
        $this->b1 = $b1;
        $h = $bk->bangun([$b1], $op);
        $this->cek('bangun satu surat → ok, 1 berkas Word (bukan ZIP)', $h['ok'] === true && $h['jumlah'] === 1 && $h['zip'] === false && str_ends_with($h['nama'], '.docx') && str_starts_with($h['biner'], 'PK'), $h['ok'] ? $h['nama'] : json_encode($h));
        if (! $h['ok']) {
            SuratJenis::$templateTimpa = [];

            return;
        }
        $row   = $this->surat($b1);
        $teks  = $this->isiDocx($h['biner']);
        $this->cek('nama berkas "{urut} Surat Izin TKA - {judul}"', str_starts_with($h['nama'], $row['urut'] . ' Surat Izin TKA - ' . self::OLEH . ' Berkas TKA B1'), $h['nama']);
        $this->cek('isi berkas: nomor, 2 siswa + NIS, Kepala Sekolah, Waka Hubin, perusahaan, tanggal surat', str_contains($teks, (string) $row['nomor']) && str_contains($teks, 'ZZUJI SS 1') && str_contains($teks, 'ZZUJI SS 2') && str_contains($teks, self::NIS . '1')
            && str_contains($teks, 'Kepsek Uji, S.T.') && str_contains($teks, 'Hubin Uji, S.Pd.') && str_contains($teks, 'ZZUJISS Perusahaan B1') && str_contains($teks, '1 Juni 2032'));
        $this->cek('kaki surat di berkas: disetujui secara elektronik, penyetuju, kode verifikasi, pencetak', str_contains($teks, 'disetujui secara elektronik') && str_contains($teks, 'Disetujui oleh: ' . self::OLEH . ' hubin') && str_contains($teks, (string) $row['acc_kode'])
            && str_contains($teks, 'Dicetak oleh ' . self::OLEH . ' operator (Operator Sekolah)'));
        $this->cek('tidak ada penanda ${…} yang tersisa di berkas', ! str_contains($teks, '${'));
        $m = $svc->muat($b1);
        $this->cek('pembukuan: cetak_ke=1, sidik = sidik data sekarang, jam cetak terisi; riwayat "nomor" 1× dan "cetak" 1×', (int) $row['cetak_ke'] === 1 && $row['sidik'] === SuratSekolah::sidik($m['surat'], $m['siswa'], $p) && ! empty($row['terakhir_cetak_at'])
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $b1, 'aksi' => 'nomor']) === 1 && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $b1, 'aksi' => 'cetak']) === 1);
        $this->cek('Audit Log mencatat unduhan (export)', (int) $this->db->table('audit_log')->where('aksi', 'export')->where('tabel', 'surat_sekolah')->like('deskripsi', 'Surat Izin TKA diunduh: 1 surat')->countAllResults() >= 1);
        $h2 = $bk->bangun([$b1], $op);
        $this->cek('unduh ulang → nomor SAMA, cetak_ke=2, riwayat "cetak" 2×; nomor tidak terbit lagi', $h2['ok'] === true && $this->surat($b1)['nomor'] === $row['nomor'] && (int) $this->surat($b1)['cetak_ke'] === 2
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $b1, 'aksi' => 'cetak']) === 2 && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $b1, 'aksi' => 'nomor']) === 1 && str_contains($this->isiDocx($h2['biner']), (string) $row['nomor']));

        // --- Perlu cetak ulang
        $m = $svc->muat($b1);
        $this->cek('sesudah diunduh dan tidak berubah → TIDAK perlu cetak ulang', ! SuratSekolah::perluCetakUlang($m['surat'], $m['siswa'], $p));
        $this->db->table('siswa')->where('id', $s1)->update(['nama' => 'ZZUJI SS 1 Revisi']);
        $m = $svc->muat($b1);
        $this->cek('nama siswa di data induk berubah → perlu cetak ulang; nomor tidak ikut berubah', SuratSekolah::perluCetakUlang($m['surat'], $m['siswa'], $p) && $this->surat($b1)['nomor'] === $row['nomor']);
        $this->db->table('siswa')->where('id', $s1)->update(['nama' => 'ZZUJI SS 1']);
        $m = $svc->muat($b1);
        $this->cek('dipulihkan → tidak perlu cetak ulang lagi; Kepala Sekolah diganti → perlu cetak ulang', ! SuratSekolah::perluCetakUlang($m['surat'], $m['siswa'], $p) && SuratSekolah::perluCetakUlang($m['surat'], $m['siswa'], ['kepsek_nama' => 'Orang Lain'] + $p));

        // --- Banyak surat dalam satu berkas
        $b2 = $this->baru('izin_tka', 'Berkas TKA B2', '2032-06-02', [$s2], 'ZZUJISS Perusahaan B2');
        $kep->putuskan($b2, 'acc', [], $hu, $p);
        $this->b2 = $b2;
        $h3 = $bk->bangun([$b2, $b1], $op);
        $teks3 = $h3['ok'] ? $this->isiDocx($h3['biner']) : '';
        $n1 = (string) $this->surat($b1)['nomor'];
        $n2 = (string) $this->surat($b2)['nomor'];
        $this->cek('banyak surat → satu berkas "Surat Izin TKA (2 surat) …"; kedua nomor ada; urutan = urutan persetujuan', $h3['ok'] === true && $h3['jumlah'] === 2 && str_contains($h3['nama'], 'Surat Izin TKA (2 surat)') && $n1 !== '' && $n2 !== ''
            && str_contains($teks3, $n1) && str_contains($teks3, $n2) && strpos($teks3, $n1) < strpos($teks3, $n2), $h3['ok'] ? $h3['nama'] : json_encode($h3));

        // --- Kegagalan yang jelas
        $a1 = (int) $this->db->table('surat_sekolah')->select('id')->where('dibuat_nama', self::OLEH)->where('jenis', 'izin_asts')->where('status', 'disetujui')->orderBy('id')->get()->getRowArray()['id'];
        $menunggu = $this->baru('izin_tka', 'Berkas TKA menunggu');
        $this->db->table('surat_sekolah')->where('id', $menunggu)->update(['status' => 'menunggu']);
        $g = [
            'kosong'         => $bk->bangun([], $op),
            'tidak_ada'      => $bk->bangun([2000000000], $op),
            'campur'         => $bk->bangun([$b1, $a1], $op),
            'template'       => $bk->bangun([$a1], $op),
            'belum disetujui' => $bk->bangun([$menunggu], $op),
            'terlalu_banyak' => $bk->bangun(range(1, 301), $op),
        ];
        $harap = ['kosong' => 'kosong', 'tidak_ada' => 'tidak_ada', 'campur' => 'campur', 'template' => 'template', 'belum disetujui' => 'kosong', 'terlalu_banyak' => 'terlalu_banyak'];
        $salah = [];
        foreach ($g as $nama => $x) {
            if (($x['ok'] ?? true) !== false || ($x['kode'] ?? '') !== $harap[$nama]) {
                $salah[] = $nama . ' → ' . json_encode($x);
            }
        }
        $this->cek('6 kegagalan jelas: ids kosong, id tak ada, jenis campur, template belum ada, belum disetujui, >300', $salah === [], implode(' | ', $salah));
        $this->cek('galat template menyebut nama jenisnya', str_contains($g['template']['pesan'] ?? '', 'Surat Izin ASTS'));

        // --- Pilih untuk unduh massal
        $this->cek('pilih: terpilih kosong → gagal; semua tanpa jenis → gagal minta jenis', $svc->pilihUntukUnduh('terpilih', [], '', 300, $p)['ok'] === false && ($x = $svc->pilihUntukUnduh('semua', [], '', 300, $p))['ok'] === false && str_contains($x['pesan'], 'jenis'));
        $semua = $svc->pilihUntukUnduh('semua', [], 'izin_tka', 300, $p);
        $this->cek('pilih semua izin_tka: memuat B1, B2; tidak memuat surat yang menunggu', $semua['ok'] === true && in_array($b1, $semua['ids'], true) && in_array($b2, $semua['ids'], true) && ! in_array($menunggu, $semua['ids'], true));
        $belum = $svc->pilihUntukUnduh('belum', [], 'izin_tka', 300, $p);
        $this->cek('pilih "belum diunduh / perlu cetak ulang": B1 & B2 (sudah diunduh, tak berubah) TIDAK ikut', $belum['ok'] === true && ! in_array($b1, $belum['ids'], true) && ! in_array($b2, $belum['ids'], true));
        $this->db->table('siswa')->where('id', $s1)->update(['nama' => 'ZZUJI SS 1 Revisi']);
        $belum = $svc->pilihUntukUnduh('belum', [], 'izin_tka', 300, $p);
        $this->db->table('siswa')->where('id', $s1)->update(['nama' => 'ZZUJI SS 1']);
        $this->cek('setelah data siswa B1 berubah → B1 ikut ("perlu cetak ulang"), B2 tetap tidak', in_array($b1, $belum['ids'], true) && ! in_array($b2, $belum['ids'], true));
        $this->cek('melebihi batas → gagal "Terlalu banyak"', ($x = $svc->pilihUntukUnduh('semua', [], 'izin_tka', 2, $p))['ok'] === false && str_contains($x['pesan'], 'Terlalu banyak'));

        // --- Lebih dari 60 surat → ZIP
        $ids = [];
        for ($i = 0; $i < 61; $i++) {
            $ids[] = $this->baru('izin_tka', 'Zip TKA ' . $i, '2034-01-15');
        }
        $this->db->table('surat_sekolah')->whereIn('id', $ids)->update(['status' => 'disetujui']);
        $hz  = $bk->bangun($ids, $op);
        $zipOk = false;
        $isiZip = [];
        if ($hz['ok']) {
            $tmp = tempnam(sys_get_temp_dir(), 'ujizip');
            file_put_contents($tmp, $hz['biner']);
            $z = new \ZipArchive();
            if ($z->open($tmp) === true) {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $isiZip[] = (string) $z->getNameIndex($i) . ':' . substr((string) $z->getFromIndex($i), 0, 2);
                }
                $z->close();
                $zipOk = true;
            }
            @unlink($tmp);
        }
        $this->cek('61 surat → ZIP berisi 2 berkas Word (50 + 11), semua bernomor berurutan', $hz['ok'] === true && $hz['zip'] === true && $hz['jumlah'] === 61 && str_ends_with($hz['nama'], '.zip') && $zipOk && count($isiZip) === 2 && str_ends_with($isiZip[0], ':PK')
            && (int) $this->db->table('surat_sekolah')->whereIn('id', $ids)->where('nomor IS NOT NULL', null, false)->countAllResults() === 61, $hz['ok'] ? implode(', ', $isiZip) : json_encode($hz));
        SuratJenis::$templateTimpa = [];
    }

    // =================================================================
    // 9. Tampilan dan controller untuk keputusan & unduhan
    // =================================================================

    /**
     * Panggil metode controller dengan peran + masukan tertentu (sesi & request ditiru). Akun sesi memakai id admin
     * sungguhan (1 admin, 2 hubin, 3 operator) karena Audit Log punya kunci asing ke tabel admins; hanya id-nya yang dipakai.
     */
    private function panggil(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/surat'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'username' => 'zzujiss', 'role' => $peran]);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        // Isian lama (old()) hanya hidup satu permintaan di peramban; di uji ini sesi menetap, jadi dibersihkan kecuali diminta.
        if (! $this->jagaMasukan) {
            session()->remove('_ci_old_input');
        }
        $this->jagaMasukan = false;
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    private bool $jagaMasukan = false;

    /**
     * Layanan Siswa T2 (2026-10-21): daftar nama per tingkat, masuk dengan NISN / tanggal lahir, kelayakan tiap surat (dengan
     * alasan), status permintaan dari status suratnya.
     */
    private function ujiLayanan(): void
    {
        $this->bagian('Layanan Siswa T2: masuk NISN / tanggal lahir, kelayakan surat, status permintaan');
        if (! \App\Libraries\LayananSiswa::tabelAda()) {
            $this->cek('tabel Layanan Siswa ada (php spark migrate)', false, 'migrasi belum dijalankan — bagian dilewati');

            return;
        }
        $L   = \App\Libraries\LayananSiswa::class;
        $lib = new \App\Libraries\LayananSiswa();
        $p   = (new PklPengaturanModel())->ambil();
        $k   = fn (string $t): int => (int) ($this->db->table('kelas')->select('id')->where('tingkat', $t)->where('deleted_at', null)->orderBy('id')->get(1)->getRowArray()['id'] ?? 0);
        [$kXI, $kXII] = [$k('XI'), $k('XII')];
        $s12 = $this->buatSiswa(91);
        $s11 = $this->buatSiswa(92);
        $this->db->table('siswa')->where('id', $s12)->update(['kelas_id' => $kXII, 'nisn' => '0091919191', 'tanggal_lahir' => '2008-03-09']);
        $this->db->table('siswa')->where('id', $s11)->update(['kelas_id' => $kXI]);

        $this->cek('tujuan setelah masuk hanya kunci layanan surat / "status"; tingkat yang dilayani memuat XII', $L::tujuanSah('izin_tka') === 'izin_tka' && $L::tujuanSah('status') === 'status'
            && $L::tujuanSah('../admin') === '' && in_array('XII', $L::tingkat($p), true));
        $kTka = $lib->kelas($p, 'izin_tka');
        $this->cek('langkah masuk Surat Izin TKA: pilihan kelas HANYA kelas XII; tujuan lain tetap memuat XI', array_keys($kTka) === ['XII']
            && in_array($kXII, array_column($kTka['XII'], 'id'), true) && $L::tingkatTujuan('izin_ujian', $p) === $L::tingkat($p)
            && in_array('XI', $L::tingkatTujuan('', $p), true), json_encode(array_keys($kTka)));
        $this->cek('daftar nama: kelas X (tidak dilayani) kosong; kelas XII memuat siswa uji (id + nama saja)', $lib->siswaKelas($this->kelasId, $p) === []
            && in_array($s12, array_column($lib->siswaKelas($kXII, $p), 'id'), true) && array_keys($lib->siswaKelas($kXII, $p)[0] ?? []) === ['id', 'nama']);

        $ip = self::IP;
        $r1 = $lib->masuk($s12, '', '', $ip, $p);
        $r2 = $lib->masuk($s12, '1111111111', '', $ip, $p);
        $r3 = $lib->masuk($this->buatSiswa(93), '0091919191', '', $ip, $p); // siswa kelas X (tidak dilayani)
        $this->cek('masuk: kosong → 422 "salah satu"; NISN salah → 422 tidak cocok; siswa kelas yang tidak dilayani → 404', ! $r1['ok'] && $r1['http'] === 422 && str_contains($r1['pesan'], 'salah satu')
            && ! $r2['ok'] && str_contains($r2['pesan'], 'tidak cocok') && ! $r3['ok'] && $r3['http'] === 404);
        $okN = $lib->masuk($s12, '0091-9191-91', '', $ip, $p);
        $idN = $L::siswaMasuk();
        $L::keluar();
        $okT = $lib->masuk($s12, '', '2008-03-09', $ip, $p);
        $this->cek('masuk dengan NISN (tanda baca diabaikan) ATAU tanggal lahir → berhasil, sesi berisi siswa; Keluar mengosongkan sesi', $okN['ok'] && $idN === $s12 && $okT['ok'] && $L::siswaMasuk() === $s12);
        $L::keluar();
        $this->cek('sesudah keluar tidak ada siswa yang masuk', $L::siswaMasuk() === null);
        $this->db->table('login_attempts')->where('ip_address', $ip)->delete();

        // Kelayakan
        $pr12 = $lib->profil($s12);
        $kl   = $lib->kelayakan($pr12, $p);
        $kl11 = $lib->kelayakan($lib->profil($s11), $p);
        $this->cek('kelayakan tanpa ajuan PKL: izin ujian/TKA/penarikan belum bisa (alasan menyebut ajuan PKL); TKA untuk kelas XI → "Khusus siswa kelas XII"; formulir Izin Ujian/TKA sudah ada (siap), Pernyataan & Penarikan belum',
            ! $kl['izin_ujian']['boleh'] && str_contains($kl['izin_ujian']['alasan'], 'ajuan PKL') && ! $kl['penarikan']['boleh'] && ! $kl['izin_tka']['boleh']
            && $kl11['izin_tka']['alasan'] === 'Khusus siswa kelas XII.' && $kl['izin_ujian']['siap'] && $kl['izin_tka']['siap'] && $kl['pernyataan']['siap'] && $kl['penarikan']['siap'], json_encode(array_column($kl, 'alasan')));
        $s13 = $this->buatSiswa(94);
        $s14 = $this->buatSiswa(95);
        $s15 = $this->buatSiswa(96);
        $this->db->table('siswa')->whereIn('id', [$s13, $s14, $s15])->update(['kelas_id' => $kXII]);
        $aj = $this->buatAjuanDisetujui($s12, 'ZZUJISS Layanan', [$s13, $s14, $s15]);
        $jd = new \App\Libraries\SuratJadwal();
        $this->db->table('surat_jadwal')->like('sesi', self::NIS)->update(['buka' => 0]); // jadwal dari bagian uji sebelumnya
        $kl2 = $lib->kelayakan($lib->profil($s12), $p);
        $this->cek('ajuan disetujui tetapi belum ada jadwal TKA → alasan "Belum ada jadwal TKA"; penarikan boleh', ! $kl2['izin_tka']['boleh'] && str_contains($kl2['izin_tka']['alasan'], 'jadwal TKA') && $kl2['penarikan']['boleh']);
        $rj = $jd->simpan(SuratJenis::TKA, ['sesi' => 'Gelombang ' . self::NIS . ' L', 'tgl_mulai' => date('Y-m-d', strtotime('+5 days')), 'tgl_selesai' => date('Y-m-d', strtotime('+6 days')), 'buka' => '1', 'tempat' => ''], null, $this->konteks());
        $kl3 = $lib->kelayakan($lib->profil($s12), $p);
        $this->cek('jadwal TKA dibuka → siswa XII yang sedang PKL boleh mengajukan Izin TKA (jadwal ikut terbawa)', $rj['ok'] && $kl3['izin_tka']['boleh'] && in_array((int) $rj['id'], array_column($kl3['izin_tka']['jadwal'], 'id'), true));

        // Status permintaan
        $st = static fn (?array $s): string => $L::status($s)['status'];
        $this->cek('status permintaan: menunggu / dikembalikan → diperiksa / disetujui belum dicetak / sudah dicetak → siap diambil / dibatalkan & surat hilang → dibatalkan', $st(['status' => 'menunggu']) === 'menunggu'
            && $st(['status' => 'dikembalikan']) === 'diperiksa' && $st(['status' => 'disetujui', 'cetak_ke' => 0]) === 'disetujui' && $st(['status' => 'disetujui', 'cetak_ke' => 2]) === 'ambil'
            && $st(['status' => 'dibatalkan', 'catatan_staf' => 'Ganda']) === 'batal' && str_contains($L::status(['status' => 'dibatalkan', 'catatan_staf' => 'Ganda'])['status_ket'], 'Ganda') && $st(null) === 'batal');
        // T3 (aturan 2026-10-25, kasus PT Inti Pantja) — Surat Izin TKA dengan gelombang PER SISWA: satu surat per perusahaan per
        // gelombang yang hanya memuat siswa gelombang itu; teman diisikan bila dipilih; pilihan sendiri terkunci; pindah; penyusul.
        $jid  = (int) $rj['id'];
        $rj2  = $jd->simpan(SuratJenis::TKA, ['sesi' => 'Gelombang ' . self::NIS . ' M', 'tgl_mulai' => date('Y-m-d', strtotime('+8 days')), 'tgl_selesai' => date('Y-m-d', strtotime('+9 days')), 'buka' => '1', 'tempat' => ''], null, $this->konteks());
        $jid2 = (int) ($rj2['id'] ?? 0);
        $isiSiswa = fn (int $suratId): array => array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->select('siswa_id')->where('surat_id', $suratId)->orderBy('urut', 'ASC')->get()->getResultArray(), 'siswa_id'));
        $tka      = static fn (int $siswa, int $j, array $teman = []): array => $lib->ajukanTka($siswa, ['jadwal_id' => (string) $j, 'teman' => array_map('strval', $teman)], $ip, $p);
        $a1  = $tka($s12, $jid, [$s13 => '']);
        $sid = (int) ($a1['surat_id'] ?? 0);
        $sr  = $this->surat($sid);
        $isiS = SuratSekolah::dekodeIsi((string) ($sr['isi'] ?? ''));
        $this->cek('TKA permintaan pertama (teman "biar pilih sendiri") → surat BARU per perusahaan per gelombang: menunggu ACC, sumber "siswa", dibuat "Siswa: …", HANYA siswa peminta (teman tidak otomatis ikut), isian = jadwal, 1 permintaan',
            $a1['ok'] && $a1['kode'] === 'baru' && $jid2 > 0 && ($sr['status'] ?? '') === 'menunggu' && ($sr['sumber'] ?? '') === 'siswa' && str_starts_with((string) ($sr['dibuat_nama'] ?? ''), 'Siswa: ')
            && $isiSiswa($sid) === [$s12] && ($isiS['mode'] ?? '') === 'perusahaan' && ($isiS['sesi'] ?? '') === 'Gelombang ' . self::NIS . ' L' && str_contains((string) ($sr['judul'] ?? ''), '(1 siswa)')
            && $this->jumlah('surat_permintaan', ['siswa_id' => $s12]) === 1, json_encode($a1));
        $a2 = $tka($s12, $jid);
        $a3 = $tka($s13, $jid);
        $this->cek('minta lagi gelombang yang sama → "sudah"; teman memilih gelombang yang SAMA → "ikut" surat yang sama (2 siswa, judul dihitung ulang, riwayat "minta")', $a2['kode'] === 'sudah' && $a2['surat_id'] === $sid
            && $a3['ok'] && $a3['kode'] === 'ikut' && $a3['surat_id'] === $sid && $isiSiswa($sid) === [$s12, $s13] && str_contains((string) $this->surat($sid)['judul'], '(2 siswa)')
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $sid, 'aksi' => 'minta']) === 1, json_encode([$a2, $a3]));
        $a4  = $lib->ajukanIzin($s11, 'izin_tka', $jid, $ip, $p);
        $a5  = $tka($s12, 999999999);
        $a5b = $tka($s12, $jid, [$s14 => '999999999']);
        $this->cek('ditolak: kelas XI ("Khusus siswa kelas XII."); gelombang tak dikenal ("Pilih gelombang TKA-mu"); gelombang teman tak dikenal → menyebut nama temannya, tanpa perubahan', ! $a4['ok'] && $a4['pesan'] === 'Khusus siswa kelas XII.'
            && ! $a5['ok'] && str_contains($a5['pesan'], 'Pilih gelombang TKA-mu') && ! $a5b['ok'] && str_contains($a5b['pesan'], 'ZZUJI SS 95') && $isiSiswa($sid) === [$s12, $s13], json_encode([$a4['pesan'], $a5['pesan'], $a5b['pesan']]));

        // Teman beda gelombang: s13 mengisikan s14 ke Gelombang M → surat TERPISAH; s12 (memilih sendiri) tidak bisa diubah s13.
        $a7   = $tka($s13, $jid, [$s14 => (string) $jid2, $s12 => (string) $jid2]);
        $sid2 = (int) (($lib->penempatanTka([$s14])[$s14][0]['surat_id']) ?? 0);
        $pm14 = $lib->permintaan($s14);
        $this->cek('teman beda gelombang: s13 mengisikan s14 ke Gelombang M → surat BARU terpisah berisi s14 saja (surat L tetap 2 siswa); s12 yang memilih sendiri TIDAK dipindah (disebut di pesan); permintaan s14 bercatatan "diisikan oleh temanmu"; staf melihat "diisikan oleh"',
            $a7['ok'] && $a7['kode'] === 'baru' && $sid2 > 0 && $sid2 !== $sid && $isiSiswa($sid2) === [$s14] && $isiSiswa($sid) === [$s12, $s13]
            && (SuratSekolah::dekodeIsi((string) $this->surat($sid2)['isi'])['sesi'] ?? '') === 'Gelombang ' . self::NIS . ' M' && str_contains($a7['pesan'], 'ZZUJI SS 91 sudah memilih sendiri')
            && count($pm14) === 1 && str_contains($pm14[0]['catatan'], 'diisikan oleh temanmu, ZZUJI SS 94') && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $sid2, 'aksi' => 'minta']) === 1
            && ((new SuratSekolah())->peminta($sid2)[0]['diisikan_oleh'] ?? '') === 'ZZUJI SS 94', json_encode([$a7, $pm14]));
        $f14 = $lib->formTka($lib->profil($s14), $lib->kelayakan($lib->profil($s14), $p));
        $tm  = array_column($f14['teman'], null, 'id');
        $this->cek('formulir TKA (s14): gelombangnya sekarang M (belum terkunci); teman s12 & s13 TERKUNCI (memilih sendiri); s15 (belum ada surat) bebas; kedua gelombang dibuka; perusahaan tampil',
            $f14['saya']['jadwal_id'] === $jid2 && ! $f14['saya']['terkunci'] && ($tm[$s12]['terkunci'] ?? false) && ($tm[$s13]['terkunci'] ?? false) && isset($tm[$s15]) && ! $tm[$s15]['terkunci']
            && $tm[$s15]['penempatan'] === [] && count(array_intersect([$jid, $jid2], array_map('intval', array_column($f14['jadwal'], 'id')))) === 2 && ($f14['perusahaan']['nama'] ?? '') === 'ZZUJISS Layanan');

        // Pindah gelombang: s14 memilih sendiri L → surat M yang jadi kosong dibatalkan otomatis; lalu kembali ke M (surat L tetap ada).
        $a8 = $tka($s14, $jid);
        $s2 = $this->surat($sid2);
        $this->cek('pindah: s14 memilih sendiri L → surat M yang jadi KOSONG dibatalkan otomatis (riwayat "pindah" + "batal"), s14 masuk surat L (3 siswa), permintaannya ikut pindah (1 baris, kini pilihan sendiri)',
            $a8['ok'] && $a8['kode'] === 'ikut' && $a8['surat_id'] === $sid && ($s2['status'] ?? '') === 'dibatalkan' && str_contains((string) ($s2['catatan_staf'] ?? ''), 'pindah')
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $sid2, 'aksi' => 'pindah']) === 1 && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $sid2, 'aksi' => 'batal']) === 1
            && $isiSiswa($sid) === [$s12, $s13, $s14] && $this->jumlah('surat_permintaan', ['siswa_id' => $s14]) === 1 && ($lib->permintaan($s14)[0]['catatan'] ?? 'x') === '', json_encode($a8));
        $a9   = $tka($s14, $jid2);
        $sid3 = (int) ($a9['surat_id'] ?? 0);
        $this->cek('pindah lagi ke M: s14 keluar dari surat L yang masih berisi teman (tetap menunggu, 2 siswa, judul dihitung ulang) → surat M BARU (yang lama sudah batal)', $a9['ok'] && $a9['kode'] === 'baru'
            && $sid3 !== $sid2 && $isiSiswa($sid3) === [$s14] && $isiSiswa($sid) === [$s12, $s13] && ($this->surat($sid)['status'] ?? '') === 'menunggu' && str_contains((string) $this->surat($sid)['judul'], '(2 siswa)')
            && $this->jumlah('surat_permintaan', ['siswa_id' => $s14]) === 1, json_encode($a9));

        // Surat M disetujui → s14 terkunci; s13 tidak bisa memindahkannya; s15 menyusul ke M → surat BARU (surat pertama sudah disetujui).
        // Pemegang ACC di lingkungan uji = bawaan (netralkanPengaturan); di sekolah = Operator (migrasi Layanan Siswa).
        $penyetuju = PklHak::boleh('operator', PklHak::kunciSurat('acc', SuratJenis::TKA)) ? ['peran' => 'operator', 'admin_id' => 3] : ['peran' => 'hubin', 'admin_id' => 2];
        $opK = ['oleh' => self::OLEH . ' ' . $penyetuju['peran'], 'ip' => $ip] + $penyetuju;
        $accM = (new \App\Libraries\SuratKeputusan())->putuskan($sid3, 'acc', ['paham' => '1'], $opK, $p);
        $tkaKunci   = $tka($s14, $jid);
        $tkaLewat   = $tka($s13, $jid, [$s14 => (string) $jid]);
        $tkaSusul   = $tka($s15, $jid2);
        $sid4 = (int) ($tkaSusul['surat_id'] ?? 0);
        $k3   = (string) (SuratSekolah::dekodeIsi((string) $this->surat($sid3)['isi'])['kunci'] ?? '');
        $k4   = (string) (SuratSekolah::dekodeIsi((string) ($this->surat($sid4)['isi'] ?? ''))['kunci'] ?? '');
        $this->cek('surat M disetujui: s14 tidak bisa pindah sendiri ("minta operator"); s13 tidak bisa memindahkan s14 (disebut di pesan); s15 menyusul ke M → surat BARU menunggu ACC berisi s15 saja (kunci berbeda, surat lama tetap)',
            $accM['ok'] && ! $tkaKunci['ok'] && str_contains($tkaKunci['pesan'], 'minta operator') && $tkaLewat['kode'] === 'sudah' && str_contains($tkaLewat['pesan'], 'ZZUJI SS 95 sudah')
            && $tkaSusul['ok'] && $tkaSusul['kode'] === 'baru' && $sid4 !== $sid3 && $isiSiswa($sid4) === [$s15] && $isiSiswa($sid3) === [$s14] && ($this->surat($sid4)['status'] ?? '') === 'menunggu'
            && $k3 !== '' && $k4 !== '' && $k3 !== $k4, json_encode([$accM['pesan'] ?? '', $tkaKunci, $tkaLewat, $tkaSusul]));
        $this->db->table('surat_permintaan')->where('siswa_id', $s15)->delete(); // tiru data lama: nama tercantum tanpa permintaan
        $pm15 = $lib->permintaan($s15);
        $this->cek('halaman status: surat yang memuat nama siswa TANPA permintaannya tetap tampil + catatan "dicantumkan … teman PKL-mu"', count($pm15) === 1
            && str_contains($pm15[0]['catatan'], 'dicantumkan') && str_contains($pm15[0]['catatan'], 'teman PKL-mu') && $pm15[0]['status'] === 'menunggu', json_encode($pm15));
        $hasilCari = array_map('intval', array_column((new SuratSekolah())->daftar(['q' => 'ZZUJI SS 95'], 1, 200)['rows'], 'id'));
        $this->cek('Daftar Surat: kotak cari menemukan surat lewat NAMA SISWA (s14 → surat M yang memuat namanya, bukan surat L)', in_array($sid3, $hasilCari, true) && ! in_array($sid, $hasilCari, true), json_encode($hasilCari));

        $pm = $lib->permintaan($s12);
        $this->cek('daftar permintaan siswa: judul jenis + jadwal ("Surat Izin TKA — TKA Gelombang …"), perusahaan, status Menunggu ACC', count($pm) === 1 && str_contains($pm[0]['judul'], 'Surat Izin TKA — TKA Gelombang ' . self::NIS)
            && $pm[0]['perusahaan'] === 'ZZUJISS Layanan' && $pm[0]['status'] === 'menunggu', json_encode($pm));
        (new \App\Libraries\SuratKeputusan())->putuskan($sid, 'batal', ['catatan' => 'Uji batal permintaan'], ['oleh' => self::OLEH . ' operator', 'admin_id' => 3, 'peran' => 'operator', 'ip' => $ip], $p);
        $a6 = $tka($s12, $jid, [$s13 => (string) $jid]);
        $this->cek('surat dibatalkan → status "Dibatalkan" + alasan; siswa boleh meminta lagi (sekaligus mengisikan teman ke gelombang yang sama) → surat BARU berisi keduanya', $lib->permintaan($s12)[1]['status'] === 'batal' && str_contains($lib->permintaan($s12)[1]['status_ket'], 'Uji batal permintaan')
            && $a6['kode'] === 'baru' && $a6['surat_id'] !== $sid && $isiSiswa((int) $a6['surat_id']) === [$s12, $s13], json_encode($a6));
        $acc =(new \App\Libraries\SuratKeputusan())->putuskan((int) $a6['surat_id'], 'acc', ['paham' => '1'], $opK, $p);
        $bk  = (new SuratBerkas())->bangun([(int) $a6['surat_id']], $opK);
        $tks = $bk['ok'] ? preg_replace('/\s+/u', ' ', $this->isiDocx($bk['biner'])) : '';
        $this->cek('surat dari permintaan siswa: Operator meng-ACC, berkas Word per perusahaan terbentuk (nama perusahaan + kedua siswa + "Gelombang …"), status siswa → "Siap diambil"', $acc['ok'] && $bk['ok']
            && str_contains($tks, 'ZZUJISS Layanan') && str_contains($tks, 'ZZUJI SS 91') && str_contains($tks, 'ZZUJI SS 94') && str_contains($tks, 'Gelombang ' . self::NIS . ' L')
            && $lib->permintaan($s12)[0]['status'] === 'ambil', json_encode([$acc, $bk['ok'] ?? null]));

        // T4 — Pernyataan Orang Tua (tanpa nomor & ACC) dan Penarikan PKL (diri sendiri, menunggu ACC).
        if ($lib->kelayakan($lib->profil($s12), $p)['pernyataan']['boleh']) {
            $ortu = ['nama' => 'Bapak ' . self::NIS, 'pekerjaan' => 'Wiraswasta', 'alamat' => 'Jl. Uji No. 1, Bekasi', 'hp' => '0812-1111-2222', 'hp_anak' => '081233334444'];
            $g1 = $lib->ajukanPernyataan($s12, ['nama' => ''] + $ortu, $ip, $p);
            $b1 = $lib->ajukanPernyataan($s12, $ortu, $ip, $p);
            $sP = $this->surat((int) ($b1['surat_id'] ?? 0));
            $iP = SuratSekolah::dekodeIsi((string) ($sP['isi'] ?? ''));
            $this->cek('Pernyataan: nama orang tua kosong → galat kolom; terkirim → surat langsung siap (disetujui, tanpa ACC & nomor), sumber siswa, data orang tua isian siswa tersimpan (HP dirapikan)', ! $g1['ok'] && isset($g1['galat']['nama'])
                && $b1['ok'] && $b1['kode'] === 'baru' && ($sP['status'] ?? '') === 'disetujui' && ($sP['sumber'] ?? '') === 'siswa' && ($iP['ortu_isian'][(string) $s12]['hp'] ?? '') === '081211112222'
                && $lib->permintaan($s12)[0]['status_teks'] === 'Siap dicetak sekolah', json_encode([$g1, $b1]));
            $b2 = $lib->ajukanPernyataan($s12, ['nama' => 'Ibu ' . self::NIS] + $ortu, $ip, $p);
            $iP2 = SuratSekolah::dekodeIsi((string) ($this->surat((int) $b1['surat_id'])['isi'] ?? ''));
            $bkP = (new SuratBerkas())->bangun([(int) $b1['surat_id']], $opK);
            $tkP = $bkP['ok'] ? preg_replace('/\s+/u', ' ', $this->isiDocx($bkP['biner'])) : '';
            $this->cek('Pernyataan: kirim lagi sebelum dicetak → data DIPERBARUI pada surat yang sama (tanpa surat baru); berkas Word mencetak nama & alamat isian siswa', $b2['kode'] === 'ubah' && $b2['surat_id'] === $b1['surat_id']
                && ($iP2['ortu_isian'][(string) $s12]['nama'] ?? '') === 'Ibu ' . self::NIS && $this->jumlah('surat_permintaan', ['siswa_id' => $s12, 'jenis' => SuratJenis::PERNYATAAN]) === 1
                && str_contains($tkP, 'Ibu ' . self::NIS) && str_contains($tkP, 'Jl. Uji No. 1, Bekasi'), mb_substr($tkP, 0, 200));
            $this->db->table('surat_sekolah')->where('id', (int) $b1['surat_id'])->update(['cetak_ke' => 1]);
            $b3 = $lib->ajukanPernyataan($s12, $ortu, $ip, $p);
            $this->cek('Pernyataan sudah dicetak → perubahan ditolak (arahkan ke operator); status "Siap diambil" + pesan tanda tangan di atas materai', ! $b3['ok'] && str_contains($b3['pesan'], 'sudah dicetak')
                && $lib->permintaan($s12)[0]['status'] === 'ambil' && str_contains($lib->permintaan($s12)[0]['status_ket'], 'materai'));
        } else {
            $this->cek('Pernyataan: kelas XII tidak termasuk tingkat PKL di pengaturan lokal — alur dilewati', true);
        }

        $t1 = $lib->ajukanPenarikan($s13, ['alasan_pilih' => 'lain', 'alasan_lain' => '', 'tgl_efektif' => date('Y-m-d')], $ip, $p);
        $t2 = $lib->ajukanPenarikan($s13, ['alasan_pilih' => '4', 'tgl_efektif' => date('Y-m-d')], $ip, $p);
        $sT = $this->surat((int) ($t2['surat_id'] ?? 0));
        $iT = SuratSekolah::dekodeIsi((string) ($sT['isi'] ?? ''));
        $sisT = array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->select('siswa_id')->where('surat_id', (int) ($t2['surat_id'] ?? 0))->get()->getResultArray(), 'siswa_id'));
        $this->cek('Penarikan: "alasan lain" kosong → galat; terkirim → surat menunggu ACC, sumber siswa, HANYA siswa peminta (bukan teman), alasan = kalimat baku "sakit", tanggal efektif', ! $t1['ok'] && isset($t1['galat']['alasan_lain'])
            && $t2['ok'] && ($sT['status'] ?? '') === 'menunggu' && ($sT['sumber'] ?? '') === 'siswa' && $sisT === [$s13] && str_contains((string) ($iT['alasan'] ?? ''), 'sakit') && ($iT['tgl_efektif'] ?? '') === date('Y-m-d'), json_encode([$t1, $t2]));
        $t3 = $lib->ajukanPenarikan($s13, ['alasan_pilih' => '0', 'tgl_efektif' => date('Y-m-d')], $ip, $p);
        $t4 = $lib->ajukanPenarikan($s11, ['alasan_pilih' => '0', 'tgl_efektif' => date('Y-m-d')], $ip, $p);
        $this->cek('Penarikan: minta lagi → "sudah" (surat sama); siswa tanpa ajuan PKL disetujui → ditolak dengan alasan', $t3['kode'] === 'sudah' && $t3['surat_id'] === $t2['surat_id'] && ! $t4['ok'] && str_contains($t4['pesan'], 'sedang PKL'));

        // T5 — layar staf: saringan asal, kotak per jenis, peminta, WhatsApp "siap diambil" semua jenis.
        $svc  = new SuratSekolah();
        $idSw = array_map('intval', array_column($svc->daftar(['asal' => 'siswa', 'jenis' => SuratJenis::TKA], 1, 200)['rows'], 'id'));
        $idSt = array_map('intval', array_column($svc->daftar(['asal' => 'staf', 'jenis' => SuratJenis::TKA], 1, 200)['rows'], 'id'));
        $rowA6 = array_values(array_filter($svc->daftar(['asal' => 'siswa'], 1, 200)['rows'], static fn (array $x): bool => (int) $x['id'] === (int) $a6['surat_id']));
        $this->cek('Daftar Surat saringan asal: surat permintaan siswa ada di "Diajukan siswa", tidak di "Dibuat staf"; baris membawa jml_minta', in_array((int) $a6['surat_id'], $idSw, true)
            && ! in_array((int) $a6['surat_id'], $idSt, true) && (int) ($rowA6[0]['jml_minta'] ?? 0) === 2);
        $rk = $svc->ringkasPermintaan();
        $this->cek('kotak per jenis: Penarikan (diminta siswa) menunggu ACC ≥ 1; jenis tanpa permintaan = 0', $rk[SuratJenis::PENARIKAN]['menunggu'] >= 1 && isset($rk[SuratJenis::BALASAN]) && is_int($rk[SuratJenis::ASTS]['siap']));
        $pm2 = $svc->peminta($sid);
        $this->cek('peminta surat Izin TKA pertama: kedua siswa (urutan permintaan) beserta kelas', array_column($pm2, 'nama') === ['ZZUJI SS 91', 'ZZUJI SS 94'] && $pm2[0]['kelas'] !== '');
        $wa  = (new \App\Libraries\SuratWa())->daftar([(int) $a6['surat_id']], $p, ['school_name' => 'SMK Uji']);
        $this->cek('WhatsApp "siap diambil" kini juga untuk Izin TKA: pesan menyebut "Surat Izin TKA" + perusahaan + nomor surat', \App\Libraries\SuratWa::berlaku(SuratJenis::TKA) && $wa !== []
            && str_contains($wa[0]['pesan'], 'Surat Izin TKA untuk ZZUJISS Layanan') && str_contains($wa[0]['pesan'], 'SMK Uji'), json_encode($wa[0]['pesan'] ?? null));
        if (isset($b1['surat_id'])) {
            $waP = (new \App\Libraries\SuratWa())->daftar([(int) $b1['surat_id']], $p, ['school_name' => 'SMK Uji']);
            $this->cek('WhatsApp Pernyataan Orang Tua: pesan berisi pengingat tanda tangan di atas materai', $waP !== [] && str_contains($waP[0]['pesan'], 'materai'), json_encode($waP[0]['pesan'] ?? null));
        }
        $C   = \App\Controllers\Admin\Surat::class;
        $hD  = html_entity_decode((string) $this->panggil($C, 'operator', 'index', ['asal' => 'siswa']));
        $hDt = html_entity_decode((string) $this->panggil($C, 'operator', 'detail', [], [], $sid));
        $this->cek('halaman Daftar Surat (Operator): kotak "Permintaan dari siswa" per jenis, saringan Asal, lencana "Diajukan … siswa"; detail: peminta + kelas',
            str_contains($hD, 'Permintaan dari siswa') && str_contains($hD, 'name="asal"') && str_contains($hD, 'Diajukan') && str_contains($hD, 'Perlu ACC')
            && str_contains($hDt, 'Diajukan siswa') && str_contains($hDt, 'ZZUJI SS 94'));

        // T6 — API Android: daftar (asal, diajukan_siswa, meta.permintaan_siswa), detail (peminta, ortu_isian), jadwal.
        [$k1, $j1] = $this->apiSurat('operator', 'daftar', ['asal' => 'siswa', 'jenis' => SuratJenis::TKA], []);
        $baris = array_values(array_filter((array) ($j1['data'] ?? []), static fn (array $x): bool => (int) $x['id'] === (int) $a6['surat_id']));
        [$k2] = $this->apiSurat('operator', 'daftar', ['asal' => 'guru'], []);
        $this->cek('API surat?asal=siswa: surat permintaan siswa ada + diajukan_siswa/jml_minta; meta.permintaan_siswa per jenis; asal tak dikenal → 422', $k1 === 200
            && ($baris[0]['diajukan_siswa'] ?? false) === true && (int) ($baris[0]['jml_minta'] ?? 0) === 2 && isset($j1['meta']['permintaan_siswa'][SuratJenis::TKA]['menunggu']) && $k2 === 422, json_encode($j1['meta'] ?? null));
        [$k3, $j3] = $this->apiSurat('operator', 'detail', [], [], $sid);
        $okOrtu = true;
        if (isset($b1['surat_id'])) {
            [, $j4] = $this->apiSurat('operator', 'detail', [], [], (int) $b1['surat_id']);
            $okOrtu = ($j4['data']['ortu_isian'][0]['nama'] ?? '') === 'Ibu ' . self::NIS && ! isset($j4['data']['isi']['ortu_isian']);
        }
        $this->cek('API detail surat: peminta (nama, kelas, waktu), ortu_isian Pernyataan sebagai daftar (tidak bocor di isi mentah)', $k3 === 200
            && array_column((array) ($j3['data']['peminta'] ?? []), 'nama') === ['ZZUJI SS 91', 'ZZUJI SS 94'] && $okOrtu);
        [$k5, $j5] = $this->apiSurat('operator', 'jadwal', [], []);
        [$k6] = $this->apiSurat('hubin', 'jadwal', [], []);
        $this->cek('API GET surat/jadwal (Operator): jenis yang boleh, jadwal + label/keadaan/jml_permintaan, pilihan ujian; Waka Hubin (tanpa hak Buat) → 403', $k5 === 200
            && in_array(SuratJenis::TKA, (array) ($j5['data']['jenis'] ?? []), true) && in_array('TKA Gelombang ' . self::NIS . ' L', array_column((array) ($j5['data']['jadwal'] ?? []), 'label'), true)
            && count((array) ($j5['data']['ujian'] ?? [])) === 3 && $k6 === 403);
        $tglA = date('Y-m-d', strtotime('+40 days'));
        [$k7, $j7] = $this->apiSurat('operator', 'jadwalTambah', [], ['jenis' => SuratJenis::TKA, 'sesi' => 'Gelombang ' . self::NIS . ' API', 'tgl_mulai' => $tglA, 'tgl_selesai' => $tglA, 'buka' => true]);
        $jid7 = (int) ($j7['data']['id'] ?? 0);
        [$k8, $j8] = $this->apiSurat('operator', 'jadwalTutup', [], [], $jid7);
        [$k9] = $this->apiSurat('operator', 'jadwalTambah', [], ['jenis' => SuratJenis::TKA, 'sesi' => '', 'tgl_mulai' => $tglA, 'tgl_selesai' => $tglA]);
        [$k10] = $this->apiSurat('operator', 'jadwalHapus', [], [], $jid7);
        $this->cek('API jadwal: tambah → 201 (buka=true dibaca), tutup → keadaan "tutup", isian salah → 422, hapus → 200', $k7 === 201 && ($j7['data']['buka'] ?? null) === true
            && $k8 === 200 && ($j8['data']['keadaan'] ?? '') === 'tutup' && $k9 === 422 && $k10 === 200 && (new \App\Libraries\SuratJadwal())->muat($jid7) === null, json_encode([$k7, $j7['message'] ?? '', $k8, $k9, $k10]));

        // Izin Ujian (ASTS) TIDAK ikut aturan gelombang TKA: surat per perusahaan tetap memuat semua siswa PKL di perusahaan itu.
        $rjA = $jd->simpan(SuratJenis::ASTS, ['kegiatan' => 'ASTS', 'semester' => 'Ganjil', 'tahun_pelajaran' => '2031/2032', 'tgl_mulai' => date('Y-m-d', strtotime('+50 days')),
            'tgl_selesai' => date('Y-m-d', strtotime('+51 days')), 'buka' => '1', 'tempat' => 'Aula ' . self::NIS], null, $this->konteks());
        $aU = $lib->ajukanIzin($s13, 'izin_ujian', (int) ($rjA['id'] ?? 0), $ip, $p);
        $this->cek('Izin Ujian (ASTS) tidak berubah: satu surat per perusahaan memuat SEMUA siswa PKL di perusahaan itu', $rjA['ok'] && $aU['ok'] && $aU['kode'] === 'baru'
            && count(array_intersect([$s12, $s13, $s14, $s15], $isiSiswa((int) ($aU['surat_id'] ?? 0)))) === 4, json_encode([$rjA['pesan'] ?? '', $aU]));

        // F2 — alat staf "Pindahkan siswa ke gelombang lain" (SuratTkaPindah). Keadaan sekarang: a6 = L [s12, s13] disetujui & sudah
        // diunduh; sid3 = M [s14] disetujui; sid4 = M [s15] menunggu.
        $this->bagian('Surat Izin TKA F2: staf memindahkan siswa ke gelombang lain');
        $L6    = (int) $a6['surat_id'];
        $alat  = new \App\Libraries\SuratTkaPindah();
        $svc   = new SuratSekolah();
        $C     = \App\Controllers\Admin\Surat::class;
        $lain  = $penyetuju['peran'] === 'operator' ? 'hubin' : 'operator';
        $this->cek('hak alamat: POST admin/surat/{id}/pindah-siswa = pemegang ACC surat', PklHak::hakUntukAlamat('admin/surat/12/pindah-siswa', 'POST') === 'surat_acc:*');
        if (! PklHak::bolehSurat($lain, 'acc', SuratJenis::TKA)) {
            $this->panggil($C, $lain, 'pindahSiswa', [], ['tujuan' => (string) $jid2, 'siswa_id' => (string) $s13], $L6);
            $this->cek('peran TANPA hak ACC Izin TKA (' . $lain . ') → ditolak, surat tidak berubah', $isiSiswa($L6) === [$s12, $s13]);
        }
        $this->panggil($C, $penyetuju['peran'], 'pindahSiswa', [], ['tujuan' => '', 'siswa_id' => (string) $s13], $L6);
        $this->cek('tujuan belum dipilih → ditolak (tidak dianggap "keluarkan"), surat tidak berubah', $isiSiswa($L6) === [$s12, $s13]);

        $this->db->table('surat_sekolah_siswa')->where('surat_id', $L6)->where('siswa_id', $s12)->update(['dikabari_at' => date('Y-m-d H:i:s'), 'dikabari_oleh' => 'uji']);
        $pmL6 = $svc->muat($L6);
        $p1   = $alat->pindahkan($L6, $s13, $jid2, 'sesuai kartu peserta', $opK, $p);
        $mL6  = $svc->muat($L6);
        $this->cek('PINDAH dari surat yang sudah disetujui & diunduh: s13 keluar dari surat L (nomor tetap, "perlu cetak ulang"), masuk surat M yang MASIH MENUNGGU (sid4, bukan yang sudah disetujui); tanda WhatsApp s12 tetap; riwayat "pindah" di kedua surat; permintaan s13 ikut pindah',
            $p1['ok'] && $p1['tujuan_id'] === $sid4 && $isiSiswa($L6) === [$s12] && $isiSiswa($sid4) === [$s15, $s13] && ($mL6['surat']['nomor'] ?? '') === ($pmL6['surat']['nomor'] ?? 'x')
            && SuratSekolah::perluCetakUlang($mL6['surat'], $mL6['siswa'], $p) && str_contains($p1['pesan'], 'Unduh ulang') && str_contains((string) $mL6['surat']['judul'], '(1 siswa)')
            && $this->db->table('surat_sekolah_siswa')->where('surat_id', $L6)->where('siswa_id', $s12)->where('dikabari_at IS NOT NULL', null, false)->countAllResults() === 1
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $L6, 'aksi' => 'pindah']) === 1 && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $sid4, 'aksi' => 'pindah']) === 1
            && $this->jumlah('surat_permintaan', ['siswa_id' => $s13, 'surat_id' => $sid4]) === 1 && $this->jumlah('surat_permintaan', ['siswa_id' => $s13, 'surat_id' => $L6]) === 0, json_encode($p1));

        // Data ganda (seperti 15 siswa di hosting): s14 ada di sid3 (M) dan ditambahkan ke L → ditandai, lalu "keluarkan saja".
        $svc->aturSiswa($L6, null, ['siswa_id' => $s14, 'kelas_id' => null, 'hp' => null], 'uji ganda');
        $hDet = html_entity_decode((string) $this->panggil($C, $penyetuju['peran'], 'detail', [], [], $L6));
        $hDt2 = $lain !== '' && ! PklHak::bolehSurat($lain, 'acc', SuratJenis::TKA) ? html_entity_decode((string) $this->panggil($C, $lain, 'detail', [], [], $L6)) : '';
        $this->cek('detail surat: siswa yang juga ada di surat TKA lain ditandai "Juga di … SRT-…"; pemegang ACC melihat tombol + dialog Pindah (gelombang M + "Keluarkan saja"); peran tanpa hak ACC hanya melihat tanda',
            str_contains($hDet, 'Juga di TKA Gelombang ' . self::NIS . ' M') && str_contains($hDet, 'data-buka-dialog="surat-pindah-' . $s14 . '"') && str_contains($hDet, 'Keluarkan saja dari surat ini')
            && ($hDt2 === '' || (str_contains($hDt2, 'Juga di') && ! str_contains($hDt2, 'surat-pindah-'))));
        $p2 = $this->panggil($C, $penyetuju['peran'], 'pindahSiswa', [], ['tujuan' => 'keluar', 'siswa_id' => (string) $s14], $L6);
        $this->cek('KELUARKAN saja (lewat halaman): s14 keluar dari surat L, tetap di surat M (sid3); surat L tetap aktif', $p2 instanceof \CodeIgniter\HTTP\RedirectResponse
            && $isiSiswa($L6) === [$s12] && $isiSiswa($sid3) === [$s14] && ($this->surat($L6)['status'] ?? '') === 'disetujui');

        // Siswa terakhir dipindah → surat dibatalkan (nomor tetap tercatat); gelombang tanpa surat → surat baru langsung di-ACC.
        $p3 = $alat->pindahkan($L6, $s12, $jid2, '', $opK, $p);
        $sL = $this->surat($L6);
        $this->cek('siswa TERAKHIR dipindah: surat L (sudah disetujui) dibatalkan otomatis, nomor tetap tercatat; s12 masuk surat M yang menunggu; permintaannya ikut', $p3['ok'] && ($sL['status'] ?? '') === 'dibatalkan'
            && (string) ($sL['nomor'] ?? '') !== '' && str_contains($p3['pesan'], 'dibatalkan') && $isiSiswa($sid4) === [$s15, $s13, $s12] && $this->jumlah('surat_permintaan', ['siswa_id' => $s12, 'surat_id' => $sid4]) === 1, json_encode($p3));
        $rj3  = $jd->simpan(SuratJenis::TKA, ['sesi' => 'Gelombang ' . self::NIS . ' N', 'tgl_mulai' => date('Y-m-d', strtotime('+12 days')), 'tgl_selesai' => date('Y-m-d', strtotime('+13 days')), 'buka' => '0', 'tempat' => ''], null, $this->konteks());
        $p4   = $alat->pindahkan($sid3, $s14, (int) ($rj3['id'] ?? 0), '', $opK, $p);
        $sN   = $this->surat((int) ($p4['tujuan_id'] ?? 0));
        $iN   = SuratSekolah::dekodeIsi((string) ($sN['isi'] ?? ''));
        $p5   = $alat->pindahkan($sid4, $s15, 999999999, '', $opK, $p);
        $this->cek('gelombang yang BELUM punya surat (jadwal ditutup untuk siswa pun boleh): surat BARU per perusahaan, langsung di-ACC karena surat asal sudah disetujui; surat asal yang kosong dibatalkan; gelombang tak dikenal → ditolak',
            $rj3['ok'] && $p4['ok'] && ($sN['status'] ?? '') === 'disetujui' && ($iN['sesi'] ?? '') === 'Gelombang ' . self::NIS . ' N' && ($iN['pkey'] ?? '') !== '' && $isiSiswa((int) $p4['tujuan_id']) === [$s14]
            && ($this->surat($sid3)['status'] ?? '') === 'dibatalkan' && ! $p5['ok'] && $p5['kode'] === 'galat' && $isiSiswa($sid4) === [$s15, $s13, $s12], json_encode([$p4, $p5]));

        // F3 — formulir staf "Surat Izin TKA per perusahaan": centang siswa per perusahaan (satu siswa = satu gelombang).
        $this->bagian('Surat Izin TKA F3: staf memilih siswa per perusahaan');
        [$s16, $s17, $s18, $s19] = [$this->buatSiswa(97), $this->buatSiswa(98), $this->buatSiswa(99), $this->buatSiswa(89)];
        $this->db->table('siswa')->whereIn('id', [$s16, $s17, $s18, $s19])->update(['kelas_id' => $kXII]);
        $this->buatAjuanDisetujui($s16, 'ZZUJISS Staf', [$s17, $s18]);
        $this->buatAjuanDisetujui($s19, 'ZZUJISS Api', []);
        $acaraF3 = new \App\Libraries\SuratAcara();
        $grupF3  = static fn (string $nama): ?array => array_values(array_filter($acaraF3->perusahaanTka(), static fn (array $g): bool => $g['nama'] === $nama))[0] ?? null;
        $gF3     = $grupF3('ZZUJISS Staf');
        $kF3     = (string) ($gF3['kunci'] ?? '');
        $this->cek('data formulir TKA: perusahaan memuat siswa XII + tka (null = belum punya surat TKA) + jml_bebas', $gF3 !== null && $gF3['jml_bebas'] === 3
            && array_column($gF3['siswa'], 'tka') === [null, null, null], json_encode($gF3));
        $f3s16   = $lib->ajukanTka($s16, ['jadwal_id' => (string) $jid], $ip, $p); // s16 meminta sendiri gelombang L lebih dulu
        $suratL  = (int) ($f3s16['surat_id'] ?? 0);
        $isiL    = SuratSekolah::dekodeIsi((string) ($this->surat($suratL)['isi'] ?? ''));
        $isiM    = SuratSekolah::dekodeIsi((string) ($this->surat($sid4)['isi'] ?? ''));
        $masukan = static fn (string $k, array $isi, ?array $siswa): array => ['tanggal_surat' => date('Y-m-d'), 'tgl_mulai' => (string) ($isi['tgl_mulai'] ?? ''), 'tgl_selesai' => (string) ($isi['tgl_selesai'] ?? ''),
            'sesi' => (string) ($isi['sesi'] ?? ''), 'tempat' => '', 'mode' => 'perusahaan', 'perusahaan' => [$k]] + ($siswa === null ? [] : ['siswa_form' => '1', 'siswa' => [$k => array_map('strval', $siswa)]]);
        $hL = SuratAcara::periksa(SuratJenis::TKA, $masukan($kF3, $isiL, [$s16, $s17]));
        $bL = $acaraF3->buat(SuratJenis::TKA, $hL, $opK, $p);
        $this->cek('staf mencentang s16 + s17 untuk gelombang L: s16 (sudah minta sendiri) disebut "sudah punya surat TKA", s17 DITAMBAHKAN ke surat L yang sudah ada (tanpa surat dobel), s18 yang tidak dicentang tidak ikut',
            $hL['ok'] && $bL['ok'] && $bL['dibuat'] === [] && $bL['ditambah'] === [$suratL] && $isiSiswa($suratL) === [$s16, $s17] && str_contains(implode(' ', $bL['sama']), 'ZZUJI SS 97'), json_encode($bL));
        $hM  = SuratAcara::periksa(SuratJenis::TKA, $masukan($kF3, $isiM, [$s17, $s18]));
        $bM  = $acaraF3->buat(SuratJenis::TKA, $hM, $opK, $p);
        $idM = (int) ($bM['dibuat'][0] ?? 0);
        $this->cek('staf mencentang s17 + s18 untuk gelombang M: s17 (sudah di L) TIDAK dimasukkan dan disebut; surat M BARU hanya berisi s18', $bM['ok'] && count($bM['dibuat']) === 1
            && $isiSiswa($idM) === [$s18] && str_contains(implode(' ', $bM['sama']), 'ZZUJI SS 98') && str_contains((string) ($this->surat($idM)['judul'] ?? ''), '(1 siswa)'), json_encode($bM));
        $hLama   = SuratAcara::periksa(SuratJenis::TKA, $masukan($kF3, $isiL, null));
        $bLama   = $acaraF3->buat(SuratJenis::TKA, $hLama, $opK, $p);
        $bKosong = $acaraF3->buat(SuratJenis::TKA, SuratAcara::periksa(SuratJenis::TKA, $masukan($kF3, $isiL, [])), $opK, $p);
        $this->cek('aplikasi lama (tanpa pilihan siswa) = semua siswa yang belum punya surat — di sini semuanya sudah → tidak ada surat dobel; centang kosong → "tidak ada siswa yang dicentang"',
            $hLama['siswa_pilih'] === null && ! $bLama['ok'] && $bLama['dibuat'] === [] && str_contains(implode(' ', $bLama['sama']), 'sudah punya surat TKA')
            && ! $bKosong['ok'] && str_contains(implode(' ', $bKosong['sama']), 'tidak ada siswa yang dicentang') && $isiSiswa($suratL) === [$s16, $s17], json_encode([$bLama, $bKosong]));
        [$kf, $jf] = $this->apiSurat('admin', 'form', ['tgl_mulai' => (string) $isiL['tgl_mulai'], 'tgl_selesai' => (string) $isiL['tgl_selesai']], [], 'tka');
        $pf = array_values(array_filter((array) ($jf['data']['perusahaan'] ?? []), static fn (array $g): bool => $g['nama'] === 'ZZUJISS Staf'))[0] ?? [];
        $this->cek('API GET surat/form/tka: perusahaan[].jml_bebas + siswa[].tka (surat yang sudah memuat siswa: kode, label, status_teks)', $kf === 200 && ($pf['jml_bebas'] ?? -1) === 0
            && ($pf['siswa'][0]['tka']['kode'] ?? '') === \App\Models\SuratSekolahModel::kode($suratL) && isset($pf['siswa'][2]['tka']['label']), json_encode($pf));
        $kApi = (string) ($grupF3('ZZUJISS Api')['kunci'] ?? '');
        [$kb, $jb] = $this->apiSurat('admin', 'buat', [], $masukan($kApi, $isiM, [$s19]) + ['siswa' => [$kApi => [(string) $s19]]], 'tka');
        [$kb2, $jb2] = $this->apiSurat('admin', 'buat', [], $masukan($kApi, $isiL, null), 'tka');
        $this->cek('API POST surat/tka + siswa {kunci: [id]} → 201 surat hanya siswa itu, data.ditambah ada; kirim lagi tanpa siswa (aplikasi lama) → 409, tidak dobel',
            $kb === 201 && count((array) ($jb['data']['dibuat'] ?? [])) === 1 && array_key_exists('ditambah', (array) ($jb['data'] ?? [])) && $isiSiswa((int) ($jb['data']['dibuat'][0]['id'] ?? 0)) === [$s19]
            && $kb2 === 409, json_encode([$kb, $jb['message'] ?? '', $kb2, $jb2['message'] ?? '']));
        $hF = html_entity_decode((string) $this->panggil(\App\Controllers\Admin\SuratIzin::class, 'admin', 'tka'));
        $this->cek('halaman formulir TKA (web): penanda siswa_form + daftar siswa per perusahaan dengan surat yang sudah memuatnya', str_contains($hF, 'name="siswa_form"')
            && str_contains($hF, '"daftar":[') && str_contains($hF, '"tempat":"TKA Gelombang ' . self::NIS . ' L') && str_contains($hF, 'Siswa yang ikut gelombang ini'));

        // F4 — peringatan data ganda di Daftar Surat (satu siswa di dua surat TKA aktif) + saringan ganda=1.
        $this->bagian('Surat Izin TKA F4–F5: peringatan siswa ganda + API Android');
        $svc->aturSiswa($idM, null, ['siswa_id' => $s16, 'kelas_id' => null, 'hp' => null], 'uji ganda F4'); // s16 kini di L dan M
        $gandaF4 = array_values(array_filter($svc->tkaGanda(), static fn (array $g): bool => $g['siswa_id'] === $s16));
        $idGanda = array_map('intval', array_column($svc->daftar(['ganda' => true], 1, 200)['rows'], 'id'));
        $this->cek('tkaGanda: s16 tercantum di 2 surat TKA aktif (L & M, kode + label + status); saringan ganda=1 hanya surat TKA yang memuat siswa ganda', count($gandaF4) === 1
            && array_column($gandaF4[0]['surat'], 'id') === [$suratL, $idM] && str_contains($gandaF4[0]['surat'][1]['label'], 'Gelombang ' . self::NIS . ' M')
            && in_array($suratL, $idGanda, true) && in_array($idM, $idGanda, true) && ! in_array($sid4, $idGanda, true), json_encode([$gandaF4, $idGanda]));
        $hG  = html_entity_decode((string) $this->panggil($C, $penyetuju['peran'], 'index'));
        $hG1 = html_entity_decode((string) $this->panggil($C, $penyetuju['peran'], 'index', ['ganda' => '1']));
        $this->cek('halaman Daftar Surat: kotak peringatan "siswa tercantum di lebih dari satu Surat Izin TKA" + daftar siswa (tautan surat) + tombol saring; ganda=1 → lencana saringan',
            str_contains($hG, 'tercantum di lebih dari satu Surat Izin TKA') && str_contains($hG, 'ZZUJI SS 97') && str_contains($hG, 'Tampilkan suratnya saja')
            && str_contains($hG1, 'Hanya surat TKA dengan siswa ganda') && str_contains($hG1, 'name="ganda" value="1"'));

        // F5 — API Android.
        [$ka, $ja] = $this->apiSurat($penyetuju['peran'], 'daftar', ['ganda' => '1', 'per' => '100'], []);
        $this->cek('API GET surat?ganda=1: surat TKA bersiswa ganda + meta.tka_ganda (siswa + surat-suratnya)', $ka === 200
            && in_array($idM, array_map('intval', array_column((array) ($ja['data'] ?? []), 'id')), true)
            && in_array($s16, array_column((array) ($ja['meta']['tka_ganda'] ?? []), 'siswa_id'), true), json_encode($ja['meta']['tka_ganda'] ?? null));
        [$kd, $jdt] = $this->apiSurat($penyetuju['peran'], 'detail', [], [], $suratL);
        $s16d = array_values(array_filter((array) ($jdt['data']['siswa'] ?? []), static fn (array $x): bool => $x['siswa_id'] === $s16))[0] ?? [];
        [, $jAsts] = $this->apiSurat($penyetuju['peran'], 'detail', [], [], (int) ($aU['surat_id'] ?? 0));
        $this->cek('API GET surat/{id} (Izin TKA): siswa[].tka_lain (surat lain yang memuatnya), pindah_tka {boleh, tujuan[jadwal_id, label, hari_tanggal, keadaan]} tanpa gelombang surat ini; surat ASTS → pindah_tka null',
            $kd === 200 && ($s16d['tka_lain'][0]['kode'] ?? '') === \App\Models\SuratSekolahModel::kode($idM) && ($jdt['data']['pindah_tka']['boleh'] ?? false) === true
            && in_array($jid2, array_column((array) ($jdt['data']['pindah_tka']['tujuan'] ?? []), 'jadwal_id'), true) && ! in_array($jid, array_column((array) ($jdt['data']['pindah_tka']['tujuan'] ?? []), 'jadwal_id'), true)
            && array_key_exists('pindah_tka', (array) ($jAsts['data'] ?? [])) && $jAsts['data']['pindah_tka'] === null, json_encode($jdt['data']['pindah_tka'] ?? null));
        [$k422, $j422] = $this->apiSurat($penyetuju['peran'], 'pindahSiswa', [], ['siswa_id' => (string) $s16, 'tujuan' => ''], $idM);
        $k403   = 0;
        $jTolak = null;
        if (! PklHak::bolehSurat($lain, 'acc', SuratJenis::TKA)) {
            [$k403, $j403] = $this->apiSurat($lain, 'detail', [], [], $suratL);
            $k403 = ($j403['data']['pindah_tka']['boleh'] ?? true) === false ? 403 : -1;
            [$kTolak, $jTolak] = $this->apiSurat($lain, 'pindahSiswa', [], ['siswa_id' => (string) $s16, 'tujuan' => 'keluar'], $idM);
            $k403 = $k403 === 403 && $kTolak === 403 ? 403 : -1;
        }
        [$kp, $jp] = $this->apiSurat($penyetuju['peran'], 'pindahSiswa', [], ['siswa_id' => (string) $s16, 'tujuan' => 'keluar', 'catatan' => 'uji API'], $idM);
        $this->cek('API POST surat/{id}/pindah-siswa: tujuan kosong → 422; peran tanpa hak ACC → pindah_tka.boleh false & 403; "keluar" → 200, s16 keluar dari surat M, data ganda hilang, riwayat "(via aplikasi)"',
            $k422 === 422 && ($k403 === 403 || PklHak::bolehSurat($lain, 'acc', SuratJenis::TKA)) && $kp === 200 && $isiSiswa($idM) === [$s18]
            && array_filter($svc->tkaGanda(), static fn (array $g): bool => $g['siswa_id'] === $s16) === [] && $this->auditAda('pindah gelombang: ZZUJI SS 97 dikeluarkan dari surat ini') , json_encode([$k422, $k403, $kp, $jp['message'] ?? '']));

        // UJI_FIXTURE=<folder>: simpan respons API di atas (data fiktif ZZUJI) sebagai fixture kontrak Flutter (test/fixtures/surat).
        $dirFx = (string) getenv('UJI_FIXTURE');
        if ($dirFx !== '' && is_dir($dirFx)) {
            $fx = ['form_tka' => $jf, 'buat_tka_perusahaan' => $jb, 'daftar_tka_ganda' => $ja, 'detail_tka_pindah' => $jdt, 'galat_422_pindah' => $j422, 'pindah_siswa' => $jp]
                + ($jTolak !== null ? ['galat_403_pindah' => $jTolak] : []);
            foreach ($fx as $nama => $isiFx) {
                file_put_contents(rtrim($dirFx, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isiFx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
            $this->cek('fixture Flutter ditulis ke ' . $dirFx . ' (' . count($fx) . ' berkas)', true);
        }
    }

    /**
     * Jadwal ASTS & TKA untuk permintaan surat dari siswa (Layanan Siswa T1, 2026-10-21): aturan isian, kembar, buka/tutup/selesai,
     * terkunci setelah dipakai permintaan, hak alamat, dan halaman staf.
     */
    private function ujiJadwal(): void
    {
        $this->bagian('Jadwal ASTS & TKA untuk permintaan siswa (Layanan Siswa T1)');
        if (! \App\Libraries\SuratJadwal::tabelAda()) {
            $this->cek('tabel surat_jadwal ada (php spark migrate)', false, 'migrasi 2026-10-26-000001 belum dijalankan — bagian dilewati');

            return;
        }
        $J   = \App\Libraries\SuratJadwal::class;
        $lib = new \App\Libraries\SuratJadwal();
        $op  = ['oleh' => self::OLEH . ' operator', 'admin_id' => 3, 'peran' => 'operator', 'ip' => self::IP];
        $hr  = static fn (int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
        $tka = ['sesi' => 'Gelombang ' . self::NIS, 'tgl_mulai' => $hr(10), 'tgl_selesai' => $hr(12), 'tempat' => '', 'buka' => '1'];

        $p1 = $lib->periksa(SuratJenis::TKA, ['sesi' => ''] + $tka);
        $p2 = $lib->periksa(SuratJenis::ASTS, ['semester' => 'Ganjil', 'tahun_pelajaran' => '2026/2028', 'tgl_mulai' => $hr(12), 'tgl_selesai' => $hr(10)]);
        $p3 = $lib->periksa('pernyataan_ortu', $tka);
        $this->cek('isian salah ditolak per kolom: TKA tanpa gelombang; ASTS tahun pelajaran 2026/2028 + tanggal terbalik; jenis bukan ASTS/TKA', ! $p1['ok'] && isset($p1['galat']['sesi'])
            && ! $p2['ok'] && isset($p2['galat']['tahun_pelajaran'], $p2['galat']['tgl_selesai']) && ! $p3['ok'], json_encode([$p1['galat'], $p2['galat']]));

        $r  = $lib->simpan(SuratJenis::TKA, $tka, null, $op);
        $id = (int) ($r['id'] ?? 0);
        $j  = $lib->muat($id);
        $this->cek('simpan TKA → tersimpan, tempat kosong = "' . SuratAcara::TEMPAT_BAWAAN . '", label "TKA Gelombang …", dibuka untuk siswa, tampil di terbuka()', $r['ok'] && $j !== null && $j['tempat'] === SuratAcara::TEMPAT_BAWAAN
            && $j['label'] === 'TKA Gelombang ' . self::NIS && $j['keadaan'] === 'buka' && in_array($id, array_column($lib->terbuka(SuratJenis::TKA), 'id'), true), json_encode($r));
        $isi = $J::isiSurat($j ?? []);
        $this->cek('isiSurat: isian surat acara per perusahaan (kegiatan TKA, sesi, tanggal, tempat, mode perusahaan)', $isi['kegiatan'] === 'TKA' && $isi['sesi'] === 'Gelombang ' . self::NIS && $isi['mode'] === 'perusahaan' && $isi['tgl_mulai'] === $hr(10));
        $r2 = $lib->simpan(SuratJenis::TKA, $tka, null, $op);
        $this->cek('jadwal yang sama dua kali → ditolak "sudah ada"', ! $r2['ok'] && str_contains($r2['pesan'], 'sudah ada'));

        $t = $lib->aturBuka($id, false, $op);
        $this->cek('Tutup → keadaan "tutup", hilang dari terbuka(); Buka lagi → kembali tampil', $t['ok'] && $lib->muat($id)['keadaan'] === 'tutup' && ! in_array($id, array_column($lib->terbuka(SuratJenis::TKA), 'id'), true)
            && $lib->aturBuka($id, true, $op)['ok'] && in_array($id, array_column($lib->terbuka(SuratJenis::TKA), 'id'), true));

        $rLewat = $lib->simpan(SuratJenis::ASTS, ['semester' => 'Genap', 'tahun_pelajaran' => '2026/2027', 'tgl_mulai' => $hr(-20), 'tgl_selesai' => $hr(-18), 'tempat' => 'Aula ' . self::NIS, 'buka' => '1'], null, $op);
        $idLewat = (int) ($rLewat['id'] ?? 0);
        $this->cek('jadwal yang kegiatannya sudah lewat → keadaan "selesai", tidak tampil bagi siswa, tidak bisa dibuka lagi', $rLewat['ok'] && $lib->muat($idLewat)['keadaan'] === 'selesai'
            && ! in_array($idLewat, array_column($lib->terbuka(SuratJenis::ASTS), 'id'), true) && ! $lib->aturBuka($idLewat, true, $op)['ok']);

        // Dipakai permintaan siswa → isi terkunci, tidak bisa dihapus (cukup ditutup).
        $s = $this->buatSiswa(81);
        $this->db->table('surat_permintaan')->insert(['jenis' => SuratJenis::TKA, 'siswa_id' => $s, 'kelas_id' => $this->kelasId, 'jadwal_id' => $id, 'created_at' => date('Y-m-d H:i:s')]);
        $u = $lib->simpan(SuratJenis::TKA, ['tempat' => 'Lab ' . self::NIS] + $tka, $id, $op);
        $h = $lib->hapus($id, $op);
        $this->cek('jadwal yang sudah dipakai permintaan siswa → ubah & hapus DITOLAK (pesan menyarankan tutup), jumlah permintaan terbaca', ! $u['ok'] && str_contains($u['pesan'], 'Tutup') && ! $h['ok'] && $lib->muat($id)['jml_permintaan'] === 1);
        $this->cek('jadwal yang belum dipakai → bisa dihapus', $lib->hapus($idLewat, $op)['ok'] && $lib->muat($idLewat) === null);
        $rAsat = $lib->simpan(SuratJenis::ASTS, ['kegiatan' => 'ASAT', 'semester' => 'Ganjil', 'tahun_pelajaran' => '2026/2027', 'tgl_mulai' => $hr(30), 'tgl_selesai' => $hr(34), 'tempat' => 'Aula ' . self::NIS, 'buka' => '1'], null, $op);
        $jAsat = $lib->muat((int) ($rAsat['id'] ?? 0));
        $this->cek('jadwal ujian ASAT: tersimpan sebagai ASAT semester Genap, label "ASAT 2026/2027", isiSurat membawa kegiatan ASAT', $rAsat['ok'] && $jAsat !== null && $jAsat['kegiatan'] === 'ASAT'
            && $jAsat['semester'] === 'Genap' && $jAsat['label'] === 'ASAT 2026/2027' && $J::isiSurat($jAsat)['kegiatan'] === 'ASAT', json_encode($rAsat));

        // Hak alamat: pembuat Izin ASTS/TKA (bawaan Operator) — Waka Hubin dan Guru Piket tidak.
        $this->cek('hak alamat admin/surat/jadwal: Operator boleh (lihat & POST), Waka Hubin tidak', HakAkses::boleh('operator', 'admin/surat/jadwal') && HakAkses::boleh('operator', 'admin/surat/jadwal/' . $id . '/tutup', 'POST')
            && ! HakAkses::boleh('hubin', 'admin/surat/jadwal'));

        // Halaman staf.
        $C    = \App\Controllers\Admin\SuratJadwal::class;
        $hal  = html_entity_decode((string) $this->panggil($C, 'operator', 'index'));
        $hub  = $this->panggil($C, 'hubin', 'index');
        $this->cek('halaman Jadwal (Operator): bagian Ujian (ASTS/ASAS/ASAT) & TKA, pilihan ujian, tombol tambah, jadwal uji + lencana keadaan + jumlah permintaan; Hubin → dialihkan', str_contains($hal, 'Jadwal Ujian (ASTS, ASAS, ASAT)') && str_contains($hal, 'Jadwal TKA')
            && str_contains($hal, 'name="kegiatan"') && str_contains($hal, 'ASAT — Asesmen Sumatif Akhir Tahun')
            && str_contains($hal, 'Tambah jadwal TKA') && str_contains($hal, 'TKA Gelombang ' . self::NIS) && str_contains($hal, 'Dibuka untuk siswa') && str_contains($hal, '1 permintaan siswa')
            && $hub instanceof \CodeIgniter\HTTP\RedirectResponse);
        $res = $this->panggil($C, 'operator', 'tambah', [], ['jenis' => SuratJenis::TKA, 'sesi' => '', 'tgl_mulai' => $hr(10), 'tgl_selesai' => $hr(12)]);
        $this->cek('POST tambah dengan isian salah → kembali ke formulir yang sama (#tambah-izin_tka), tidak tersimpan', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_ends_with($res->getHeaderLine('Location'), '#tambah-izin_tka'));
    }

    /**
     * Surat Balasan: staf mencentang siswa yang DITERIMA perusahaan saat membuat/mengubah surat; yang tidak dicentang dikeluarkan dari
     * ajuan sebagai "ditolak perusahaan" (permintaan klien 2026-10-21 — operator memperbaiki sendiri, bukan lewat lapor ulang siswa).
     */
    private function ujiBalasanTerima(): void
    {
        $this->bagian('Surat Balasan: centang siswa yang diterima perusahaan (yang lain dikeluarkan, ditolak perusahaan)');
        $lib = new SuratPkl();
        $p   = (new PklPengaturanModel())->ambil();
        [$s71, $s72, $s73] = [$this->buatSiswa(71), $this->buatSiswa(72), $this->buatSiswa(73)];
        $aid     = $this->buatAjuanDisetujui($s71, 'ZZUJISS Terima Sebagian', [$s72, $s73]);
        $anggota = fn (): array => array_map('intval', array_column($this->db->table('pkl_anggota')->select('siswa_id')->where('pengajuan_id', $aid)->orderBy('id')->get()->getResultArray(), 'siswa_id'));
        $siswaSurat = fn (int $id): array => array_map('intval', array_column($this->db->table('surat_sekolah_siswa')->select('siswa_id')->where('surat_id', $id)->orderBy('urut')->get()->getResultArray(), 'siswa_id'));
        $h  = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => '', 'periode_mulai' => '2027-08-24', 'periode_selesai' => '2027-11-24']);
        $op = ['oleh' => self::OLEH . ' operator', 'admin_id' => 3, 'peran' => 'operator', 'ip' => self::IP];

        $r = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $op, $p, ['terima' => [$aid => []], 'alasan_tolak' => 'Ditolak semua']);
        $this->cek('buat Balasan tanpa satu pun siswa dicentang → ditolak, tidak ada yang dikeluarkan', ! $r['ok'] && str_contains(implode(' ', $r['gagal']), 'Centang minimal satu') && $anggota() === [$s71, $s72, $s73], json_encode($r['gagal']));
        $r = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $op, $p, ['terima' => [$aid => [$s71, $s72]], 'alasan_tolak' => ' ']);
        $this->cek('ada siswa tidak dicentang tanpa alasan → ditolak ("Tulis alasan"), ajuan utuh', ! $r['ok'] && str_contains(implode(' ', $r['gagal']), 'Tulis alasan') && count($anggota()) === 3);
        $r = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $op, $p, ['terima' => [$aid => [$s71, 999999]], 'alasan_tolak' => 'Ditolak perusahaan.']);
        $this->cek('siswa yang bukan anggota ajuan → ditolak', ! $r['ok'] && str_contains(implode(' ', $r['gagal']), 'bukan anggota aktif'));

        $r   = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $op, $p, ['terima' => [$aid => [$s71, $s72]], 'alasan_tolak' => 'Perusahaan hanya menerima dua siswa.']);
        $id  = (int) ($r['dibuat'][0] ?? 0);
        $kel = $this->db->table('pkl_anggota_keluar')->where(['pengajuan_id' => $aid, 'siswa_id' => $s73])->get()->getRowArray();
        $this->cek('buat (operator, tanpa hak ACC ajuan): surat hanya 2 siswa yang dicentang; siswa 73 dikeluarkan "ditolak perusahaan" + alasan; pesan menyebutnya', $r['ok'] && $siswaSurat($id) === [$s71, $s72]
            && $anggota() === [$s71, $s72] && ($kel['jenis'] ?? '') === 'ditolak_perusahaan' && ($kel['alasan'] ?? '') === 'Perusahaan hanya menerima dua siswa.' && ($kel['peran_staf'] ?? '') === 'operator'
            && $r['dilepas'] === ['ZZUJI SS 73'] && str_contains($r['pesan'], 'ZZUJI SS 73 tidak diterima perusahaan') && str_ends_with((string) $this->surat($id)['judul'], '(2 siswa)'), json_encode($r));
        $this->cek('siswa yang dikeluarkan: riwayat "ditolak 1×", bebas mengajukan lagi (bukan anggota aktif)', ((new PklKeluar())->riwayatSiswa($s73)['jumlah_ditolak'] ?? 0) === 1
            && $this->db->table('pkl_anggota')->where('siswa_aktif', $s73)->countAllResults() === 0);

        // Ubah (surat menunggu ACC / dikembalikan): lepas siswa 72.
        $m  = (new SuratSekolah())->muat($id);
        $ru = $lib->ubahSurat($m, $h, [], $op, ['terima' => [$s71], 'alasan_tolak' => 'Kuota hanya satu siswa.']);
        $this->cek('ubah Balasan: lepas centang siswa 72 → surat 1 siswa, siswa 72 dikeluarkan (ditolak perusahaan), judul "(1 siswa)"', $ru['ok'] && ($ru['dilepas'] ?? []) === ['ZZUJI SS 72']
            && $siswaSurat($id) === [$s71] && $anggota() === [$s71] && str_ends_with((string) $this->surat($id)['judul'], '(1 siswa)'), json_encode($ru));
        $m  = (new SuratSekolah())->muat($id);
        $this->cek('ubah tanpa opsi terima → daftar siswa tetap; terima kosong → ditolak tanpa perubahan', $lib->ubahSurat($m, $h, [], $op)['ok'] && $siswaSurat($id) === [$s71]
            && ! $lib->ubahSurat($m, $h, [], $op, ['terima' => [], 'alasan_tolak' => 'x x x x'])['ok'] && $anggota() === [$s71]);

        // Formulir web (operator): ubah Balasan menampilkan centang siswa + kotak alasan; simpan lewat controller.
        [$s74, $s75] = [$this->buatSiswa(74), $this->buatSiswa(75)];
        $aid2 = $this->buatAjuanDisetujui($s74, 'ZZUJISS Terima Web', [$s75]);
        $id2  = (int) ($lib->buat(SuratJenis::BALASAN, $h, [$aid2], [], $op, $p)['dibuat'][0] ?? 0);
        $C    = \App\Controllers\Admin\SuratPkl::class;
        $html = (string) $this->panggil($C, 'operator', 'ubahBalasan', [], [], $id2);
        $this->cek('formulir Ubah Balasan: blok "Siswa yang diterima perusahaan" (centang per siswa) + kotak alasan + template "Ditolak perusahaan"', str_contains($html, 'Siswa yang diterima perusahaan')
            && str_contains($html, ':name="namaTerima(a)"') && str_contains($html, 'name="alasan_tolak"') && str_contains($html, 'Tempat tidak bisa diganti di sini') && str_contains($html, 'tolakAwal'));
        $res = $this->panggil($C, 'operator', 'simpanUbahBalasan', [], ['tanggal_surat' => date('Y-m-d'), 'sapaan' => 'Pimpinan', 'periode_mulai' => '2027-08-24', 'periode_selesai' => '2027-11-24',
            'atur_terima' => [$aid2 => '1'], 'terima' => [$aid2 => [(string) $s75]], 'alasan_tolak' => 'Perusahaan memilih satu siswa saja.'], $id2);
        $this->cek('POST ubah (operator): siswa 74 tak dicentang → dikeluarkan, surat hanya siswa 75, pesan sukses menyebutnya', $res instanceof \CodeIgniter\HTTP\RedirectResponse
            && $siswaSurat($id2) === [$s75] && str_contains((string) session('success'), 'ZZUJI SS 74 tidak diterima perusahaan')
            && (int) ($this->db->table('pkl_anggota')->where(['pengajuan_id' => $aid2, 'siswa_id' => $s75])->get()->getRowArray()['peran'] === 'pengaju'), (string) session('success') . ' ' . (string) session('error'));

        // API Android: ubah (siswa = diterima) & buat (diterima = {ajuan: [siswa]}).
        [$s76, $s77] = [$this->buatSiswa(76), $this->buatSiswa(77)];
        $aid3 = $this->buatAjuanDisetujui($s76, 'ZZUJISS Terima Api', [$s77]);
        [$k, $j] = $this->apiSurat('operator', 'buat', [], ['tanggal_surat' => date('Y-m-d'), 'periode_mulai' => '2027-08-24', 'periode_selesai' => '2027-11-24', 'ajuan' => [$aid3],
            'diterima' => [(string) $aid3 => [$s76]], 'alasan_tolak' => 'Hanya satu yang diterima.'], 'balasan');
        $id3 = (int) ($j['data']['dibuat'][0]['id'] ?? 0);
        $this->cek('API POST surat/balasan + diterima {ajuan: [siswa]} → 201, data.dilepas, surat hanya siswa yang diterima', $k === 201 && ($j['data']['dilepas'] ?? []) === ['ZZUJI SS 77']
            && $siswaSurat($id3) === [$s76], json_encode($j));
        [$k, $j] = $this->apiSurat('operator', 'ubah', [], ['tanggal_surat' => date('Y-m-d'), 'periode_mulai' => '2027-08-24', 'periode_selesai' => '2027-11-24', 'siswa' => []], $id3);
        $this->cek('API POST surat/{id}/ubah Balasan dengan siswa [] → 422 (minimal satu diterima)', $k === 422 && str_contains((string) ($j['message'] ?? ''), 'Centang minimal satu'), json_encode($j));
    }

    /** @return array{0: int, 1: array} panggil Api\Surat sebagai peran (token sudah lolos ApiAuthFilter) */
    private function apiSurat(string $peran, string $metode, array $get, array $post, mixed ...$arg): array
    {
        \App\Libraries\ApiAuth::set(['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'role' => $peran]);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/surat'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod('POST');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Api\Surat();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}(...$arg);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    private function ujiTampilanKeputusan(): void
    {
        $this->bagian('Tampilan & controller: tombol per peran/status, unduhan, ACC massal, halaman Hak Akses');
        $this->netralkanPengaturan();
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
        $S = \App\Controllers\Admin\Surat::class;
        $kep = new \App\Libraries\SuratKeputusan();
        $p   = (new PklPengaturanModel())->ambil();
        [$op, $hu] = [$this->ctx('operator'), $this->ctx('hubin')];
        [$s1, $s2] = $this->siswaUji;
        $templateUji = APPPATH . PklSurat::TEMPLATE_BAWAAN;

        set_error_handler(static function (int $severity, string $message, string $file, int $line) {
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $v1 = $this->baru('izin_asts', 'Tampil V1 menunggu');
            $v2 = $this->baru('izin_tka', 'Tampil V2 siap', '2032-06-03', [$s1], 'ZZUJISS Perusahaan V2');
            $kep->putuskan($v2, 'acc', [], $hu, $p);
            $v3 = $this->baru('izin_tka', 'Tampil V3 kembali');
            $kep->putuskan($v3, 'kembalikan', ['catatan' => 'Cek ulang tanggal pelaksanaan'], $hu, $p);
            $v4 = $this->baru('izin_asts', 'Tampil V4 batal');
            $kep->putuskan($v4, 'batal', ['catatan' => 'Dobel dengan surat lain'], $op, $p);
            $v5 = $this->baru('penarikan_pkl', 'Tampil V5 tanpa siswa', '2032-06-03', [], 'ZZUJISS Perusahaan V5');

            // --- Detail surat menunggu
            $dHu = (string) $this->panggil($S, 'hubin', 'detail', [], [], $v1);
            $dOp = (string) $this->panggil($S, 'operator', 'detail', [], [], $v1);
            $dAd = (string) $this->panggil($S, 'admin', 'detail', [], [], $v1);
            // Alamat di atribut form dialog ditulis esc(…, 'attr') (entitas heksa) → didekode dulu sebelum dicocokkan.
            $dHuD = html_entity_decode($dHu);
            $this->cek('detail menunggu — Hubin: tombol ACC & Kembalikan + dialog berformulir ke alamat yang benar; tanpa kolom wakil/paham', str_contains($dHu, 'ACC surat ini') && str_contains($dHu, 'Kembalikan untuk diperbaiki') && str_contains($dHuD, '/admin/surat/' . $v1 . '/acc"')
                && str_contains($dHuD, '/admin/surat/' . $v1 . '/kembalikan"') && ! str_contains($dHu, 'name="wakil"') && ! str_contains($dHu, 'name="paham"'));
            $this->cek('detail menunggu — Operator: TIDAK ada tombol ACC; ada keterangan menunggu ACC dan tombol Batalkan surat', ! str_contains($dOp, 'ACC surat ini') && str_contains($dOp, 'menunggu <b>ACC</b> dari Waka Hubin') && str_contains($dOp, 'data-buka-dialog="surat-batal"'));
            $this->cek('detail menunggu — Hubin: tidak ada tombol Batalkan surat (bukan haknya); Admin: tombol ACC tanpa kotak "mewakili" (dihapus)', ! str_contains($dHu, 'data-buka-dialog="surat-batal"') &&! str_contains($dAd, 'name="wakil"') && str_contains($dAd, 'ACC surat ini'));
            $dBahaya = (string) $this->panggil($S, 'hubin', 'detail', [], [], $v5);
            $this->cek('surat tanpa siswa: peringatan BAHAYA tampil dan dialog ACC meminta centang "sudah saya periksa"', str_contains($dBahaya, '<b>Bahaya:</b> Surat ini belum memuat satu pun siswa') &&str_contains($dBahaya, 'name="paham"'));
            $this->atur(['kontak_surat_nama' => '']);
            $this->cek('kontak NB kosong: peringatan awas tampil (tanpa memblokir ACC)', str_contains((string) $this->panggil($S, 'hubin', 'detail', [], [], $v1), 'Kontak &quot;NB&quot; di bawah surat belum diisi'));
            $this->atur(['kontak_surat_nama' => 'Kontak Uji']);

            // --- Detail surat siap unduh / dikembalikan / dibatalkan
            \App\Libraries\SuratJenis::$templateTimpa = ['izin_tka' => $templateUji];
            $sOp = (string) $this->panggil($S, 'operator', 'detail', [], [], $v2);
            $sHu = (string) $this->panggil($S, 'hubin', 'detail', [], [], $v2);
            $this->cek('detail siap unduh — Operator (template ada): formulir unduh berpenanda data-unduh ke alamat yang benar + tombol "Unduh surat (Word)"', str_contains($sOp, '/admin/surat/' . $v2 . '/unduh"') && str_contains($sOp, 'data-unduh') && str_contains($sOp, 'Unduh surat (Word)'));
            $this->cek('detail siap unduh — Hubin: pesan "diunduh oleh peran yang diberi hak", TANPA formulir unduh; tombol Batalkan persetujuan tampil', str_contains($sHu, 'Surat ini diunduh oleh') && ! str_contains($sHu, '/unduh"') && str_contains($sHu, 'Batalkan persetujuan'));
            $this->cek('detail siap unduh — Operator tidak melihat Batalkan persetujuan; ACC tercatat: nama penyetuju + kode verifikasi tampil', ! str_contains($sOp, 'Batalkan persetujuan') && str_contains($sOp, self::OLEH . ' hubin') && str_contains($sOp, 'SRT-'));
            \App\Libraries\SuratJenis::$templateTimpa = ['izin_tka' => APPPATH . 'Libraries/Surat/__tidak_ada__.docx']; // template dianggap belum ada
            $tanpaTemplate = (string) $this->panggil($S, 'operator', 'detail', [], [], $v2);
            $this->cek('template jenis belum ada → pesan "belum tersedia", tidak ada formulir unduh', str_contains($tanpaTemplate, 'belum tersedia') && ! str_contains($tanpaTemplate, '/unduh"'));
            $dKem = (string) $this->panggil($S, 'operator', 'detail', [], [], $v3);
            $this->cek('detail dikembalikan — Operator: catatan Hubin tampil dan tombol "Ajukan ulang untuk ACC"; Hubin: menunggu pembuat memperbaiki', str_contains($dKem, 'Dikembalikan dengan catatan') && str_contains($dKem, 'Cek ulang tanggal pelaksanaan') && str_contains($dKem, 'Ajukan ulang untuk ACC')
                && str_contains((string) $this->panggil($S, 'hubin', 'detail', [], [], $v3), 'Menunggu pembuat surat memperbaiki'));
            $dBat = (string) $this->panggil($S, 'operator', 'detail', [], [], $v4);
            $this->cek('detail dibatalkan: pernyataan "Surat dibatalkan" + alasan; kartu Tindakan tidak ada', str_contains($dBat, 'Surat dibatalkan.') && str_contains($dBat, 'Dobel dengan surat lain') && ! str_contains($dBat, '>Tindakan<'));

            // --- Daftar: ACC massal & unduh massal
            $lHu = (string) $this->panggil($S, 'hubin', 'index', ['status' => 'menunggu']);
            $lOp = (string) $this->panggil($S, 'operator', 'index', ['status' => 'menunggu']);
            $lAd = (string) $this->panggil($S, 'admin', 'index', ['status' => 'menunggu']);
            $this->cek('tab Menunggu — Hubin: formulir ACC massal + kotak centang; Operator: keterangan terkunci, tanpa formulir; Admin: formulir tanpa pernyataan "mewakili"', str_contains($lHu, 'id="formAcc"') && str_contains($lHu, 'name="ids[]"') && ! str_contains($lOp, 'id="formAcc"') && str_contains($lOp, 'ACC dilakukan peran yang diberi hak ACC')
                && str_contains($lAd, 'id="formAcc"') && ! str_contains($lAd, 'name="wakil"'));
            \App\Libraries\SuratJenis::$templateTimpa = ['izin_tka' => $templateUji];
            $uOp = (string) $this->panggil($S, 'operator', 'index', ['status' => 'disetujui', 'jenis' => 'izin_tka']);
            $uHu = (string) $this->panggil($S, 'hubin', 'index', ['status' => 'disetujui', 'jenis' => 'izin_tka']);
            $uSemua = (string) $this->panggil($S, 'operator', 'index', ['status' => 'disetujui']);
            $this->cek('tab Siap unduh + satu jenis — Operator: formulir unduh massal (data-unduh) dengan 3 mode; Hubin: tanpa formulir; tanpa jenis: petunjuk "saring dulu"',
                str_contains($uOp, 'id="formUnduh"') && str_contains($uOp, 'data-unduh') && str_contains($uOp, 'value="terpilih"') && str_contains($uOp, 'value="belum"') && str_contains($uOp, 'value="semua"') && str_contains($uOp, 'name="jenis" value="izin_tka"')
                && ! str_contains($uHu, 'id="formUnduh"') && ! str_contains($uSemua, 'id="formUnduh"') && str_contains($uSemua, 'saring dulu menurut satu jenis surat'));
            $this->db->table('siswa')->where('id', $s2)->update(['nama' => 'ZZUJI SS 2 Revisi']);
            $uUlang = (string) $this->panggil($S, 'operator', 'index', ['status' => 'disetujui', 'jenis' => 'izin_tka', 'q' => 'Berkas TKA B2']);
            $this->db->table('siswa')->where('id', $s2)->update(['nama' => 'ZZUJI SS 2']);
            // (rombak tampilan) tanda = lencana kuning ber-ikon "perlu cetak ulang"; nama yang meng-ACC bisa ditebalkan → bandingkan teks polos.
            $this->cek('daftar menandai "perlu cetak ulang" untuk surat yang datanya berubah sesudah diunduh', str_contains($uUlang, 'perlu cetak ulang</span>')
                && str_contains((string) preg_replace('/\s+/', ' ', strip_tags($uUlang)), 'ACC: ' . self::OLEH . ' hubin'));

            // --- Aksi controller (alur web utuh)
            $cAcc = $this->baru('izin_asts', 'Ctrl ACC');
            $res  = $this->panggil($S, 'hubin', 'acc', [], [], $cAcc);
            $this->cek('POST acc oleh Hubin → alihkan ke detail, status disetujui, tercatat atas nama Hubin', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($res->getHeaderLine('Location'), '/admin/surat/' . $cAcc) && $this->surat($cAcc)['status'] === 'disetujui' && $this->surat($cAcc)['acc_peran'] === 'hubin');
            $cOp = $this->baru('izin_asts', 'Ctrl ACC ditolak');
            $res = $this->panggil($S, 'operator', 'acc', [], [], $cOp);
            $this->cek('POST acc oleh Operator → dialihkan dengan pesan galat; status tetap menunggu', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $this->surat($cOp)['status'] === 'menunggu');
            $res = $this->panggil($S, 'hubin', 'acc', [], [], 2000000000);
            $this->cek('POST acc untuk id tak ada → dialihkan ke Daftar Surat', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($res->getHeaderLine('Location'), '/admin/surat') && ! str_contains($res->getHeaderLine('Location'), '/admin/surat/'));
            $res = $this->panggil($S, 'operator', 'ajukanUlang', [], [], $v3);
            $this->cek('POST ajukan-ulang oleh Operator → surat dikembalikan kembali menunggu', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $this->surat($v3)['status'] === 'menunggu');
            $cm1 = $this->baru('izin_asts', 'Ctrl Massal 1');
            $res = $this->panggil($S, 'hubin', 'accMassal', [], ['mode' => 'terpilih', 'ids' => [$cm1]]);
            $this->cek('POST acc-massal oleh Hubin → alihkan ke tab Menunggu; surat tersetujui', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($res->getHeaderLine('Location'), 'status=menunggu') && $this->surat($cm1)['status'] === 'disetujui');

            \App\Libraries\SuratJenis::$templateTimpa = ['izin_tka' => $templateUji];
            $unduh = $this->panggil($S, 'operator', 'unduh', [], ['unduh_token' => '123456789'], $v2);
            $isiUnduh = '';
            $kepala = '';
            if ($unduh instanceof \CodeIgniter\HTTP\DownloadResponse) {
                $unduh->buildHeaders();
                $kepala = $unduh->getHeaderLine('Content-Disposition') . ' | ' . $unduh->getHeaderLine('Set-Cookie');
                ob_start();
                $unduh->sendBody();
                $isiUnduh = (string) ob_get_clean();
            }
            $this->cek('POST unduh oleh Operator → berkas Word (DownloadResponse) bernama .docx, isinya dokumen Word, cookie penanda selesai terpasang', $unduh instanceof \CodeIgniter\HTTP\DownloadResponse && str_starts_with($isiUnduh, 'PK')
                && str_contains($kepala, '.docx') && str_contains($kepala, 'unduh_selesai=123456789'), $kepala);
            $before = (int) $this->surat($v2)['cetak_ke'];
            $res = $this->panggil($S, 'hubin', 'unduh', [], [], $v2);
            $this->cek('POST unduh oleh Hubin (tanpa hak) → dialihkan dengan galat; jumlah unduhan tidak bertambah', $res instanceof \CodeIgniter\HTTP\RedirectResponse && (int) $this->surat($v2)['cetak_ke'] === $before);
            $res = $this->panggil($S, 'operator', 'unduh', [], [], 2000000000);
            $this->cek('POST unduh untuk id tak ada → dialihkan, tidak melempar galat', $res instanceof \CodeIgniter\HTTP\RedirectResponse);
            $massal = $this->panggil($S, 'operator', 'unduhMassal', [], ['mode' => 'semua', 'jenis' => 'izin_tka']);
            $this->cek('POST unduh-massal (semua izin_tka) oleh Operator → berkas unduhan', $massal instanceof \CodeIgniter\HTTP\DownloadResponse);
            $res = $this->panggil($S, 'operator', 'unduhMassal', [], ['mode' => 'semua', 'jenis' => '']);
            $res2 = $this->panggil($S, 'hubin', 'unduhMassal', [], ['mode' => 'semua', 'jenis' => 'izin_tka']);
            $this->cek('unduh-massal tanpa jenis → dialihkan dengan petunjuk; oleh Hubin (tanpa hak) → dialihkan', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $res2 instanceof \CodeIgniter\HTTP\RedirectResponse);
            \App\Libraries\SuratJenis::$templateTimpa = [];

            // --- Halaman Sistem → Hak Akses (bagian PKL & Surat): hak per jenis + aturan wajib ACC
            $H   = \App\Controllers\Admin\AturHak::class;
            $hak = (string) $this->panggil($H, 'admin', 'index', ['bagian' => 'pkl']);
            $this->cek('Hak Akses (Admin, PKL & Surat): tabel per jenis surat, 4 kotak "Wajib ACC" (jenis bernomor), centang Buat/ACC/Unduh per peran', str_contains($hak, 'Surat Sekolah — per jenis surat') && substr_count($hak, 'name="acc[]"') === 4
                && str_contains(html_entity_decode($hak), 'value="surat_acc:balasan_pkl"') && str_contains($hak, 'Simpan hak PKL &amp; Surat'));
            $res = $this->panggil($H, 'hubin', 'simpanPkl', [], ['acc' => ['izin_asts']]);
            $this->cek('simpan oleh Hubin → ditolak (khusus Admin); pengaturan tidak berubah', $res instanceof \CodeIgniter\HTTP\RedirectResponse && (string) (new PklPengaturanModel())->ambil()['surat_perlu_acc'] === '');
            $res = $this->panggil($H, 'admin', 'simpanPkl', [], ['acc' => ['izin_asts', 'balasan_pkl'], 'hak' => PklHak::bawaanLengkap()]);
            $peta = SuratJenis::petaAcc((new PklPengaturanModel())->ambil());
            $this->cek('simpan oleh Admin → aturan ACC tersimpan (ASTS & Balasan wajib, TKA & Penarikan tidak); hak bawaan utuh', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $peta['izin_asts'] && ! $peta['izin_tka'] && $peta['balasan_pkl'] && ! $peta['penarikan_pkl']
                && PklHak::jenisBoleh('hubin', 'acc') === ['izin_asts', 'izin_tka', 'balasan_pkl', 'penarikan_pkl'] && PklHak::jenisBoleh('operator', 'buat') === SuratJenis::kode());
            $this->atur(['surat_perlu_acc' => null]);
            $this->setelPeranBawaan();
        } finally {
            restore_error_handler();
            session()->remove('admin');
            \App\Libraries\SuratJenis::$templateTimpa = [];
        }
    }

    // =================================================================
    // 10. Surat Izin ASTS & TKA
    // =================================================================

    /** Isi sebuah surat acara untuk uji; bila $kunci diberikan ikut disimpan. */
    private function isiAcara(string $jenis, string $mulai, string $selesai, string $mode = 'umum', string $sesi = 'Gelombang 1'): array
    {
        return $jenis === SuratJenis::ASTS
            ? ['kegiatan' => 'ASTS', 'semester' => 'Ganjil', 'tahun_pelajaran' => '2026/2027', 'tgl_mulai' => $mulai, 'tgl_selesai' => $selesai, 'tempat' => 'SMK Bina Nusa', 'mode' => $mode]
            : ['kegiatan' => 'TKA', 'sesi' => $sesi, 'tgl_mulai' => $mulai, 'tgl_selesai' => $selesai, 'tempat' => 'SMK Bina Nusa', 'mode' => $mode];
    }

    /** Badan surat (dari "Kepada Yth." sampai "terima kasih.") dengan spasi dirapatkan — untuk membandingkan dengan contoh sekolah. */
    private function badanSurat(string $teks): string
    {
        return preg_match('~Kepada\s*Yth\..*?terima kasih\.~su', $teks, $m) === 1 ? trim((string) preg_replace('/\s+/u', ' ', $m[0])) : '';
    }

    private function teksDocxBerkas(string $path): string
    {
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            return '';
        }
        $xml = (string) $z->getFromName('word/document.xml');
        $z->close();

        return html_entity_decode(strip_tags((string) preg_replace('/<\/w:p>/', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function ujiAcara(): void
    {
        $this->bagian('Surat Izin ASTS & TKA: tanggal otomatis, validasi, pencegah ganda, per perusahaan, template asli');

        // Ujian ASAS & ASAT pada jenis izin_asts (2026-10-21).
        $dasarU = ['tanggal_surat' => '2026-11-30', 'tgl_mulai' => '2026-12-07', 'tgl_selesai' => '2026-12-11', 'tahun_pelajaran' => '2026/2027', 'mode' => 'umum', 'tempat' => ''];
        $hAsts  = SuratAcara::periksa(SuratJenis::ASTS, $dasarU + ['semester' => 'Ganjil']);
        $hAsas  = SuratAcara::periksa(SuratJenis::ASTS, $dasarU + ['semester' => 'Ganjil', 'kegiatan' => 'asas']);
        $hAsat  = SuratAcara::periksa(SuratJenis::ASTS, $dasarU + ['semester' => 'Ganjil', 'kegiatan' => 'ASAT']);
        $hSalah = SuratAcara::periksa(SuratJenis::ASTS, $dasarU + ['semester' => 'Ganjil', 'kegiatan' => 'UTS']);
        $this->cek('ujian: tanpa isian = ASTS (formulir lama); "asas" → ASAS; ASAT memaksa semester Genap; ujian tak dikenal → galat', $hAsts['ok'] && $hAsts['isi']['kegiatan'] === 'ASTS'
            && $hAsas['ok'] && $hAsas['isi']['kegiatan'] === 'ASAS' && $hAsat['ok'] && $hAsat['isi']['semester'] === 'Genap' && ! $hSalah['ok'] && str_contains(implode(' ', $hSalah['galat']), 'ASAS'));
        $this->cek('judul: "ASAS Ganjil 2026/2027 - surat umum", "ASAT 2026/2027 - surat umum" (ASAT tanpa semester)', SuratAcara::judul(SuratJenis::ASTS, $hAsas['isi']) === 'ASAS Ganjil 2026/2027 - surat umum'
            && SuratAcara::judul(SuratJenis::ASTS, $hAsat['isi']) === 'ASAT 2026/2027 - surat umum', SuratAcara::judul(SuratJenis::ASTS, $hAsat['isi']));
        $this->cek('sidik pencegah ganda: ASAS ≠ ASTS di tanggal yang sama; sidik ASTS sama dengan surat lama tanpa isian ujian', SuratAcara::kunci(SuratJenis::ASTS, $hAsas['isi']) !== SuratAcara::kunci(SuratJenis::ASTS, $hAsts['isi'])
            && SuratAcara::kunci(SuratJenis::ASTS, $hAsts['isi']) === SuratAcara::kunci(SuratJenis::ASTS, array_diff_key($hAsts['isi'], ['kegiatan' => 1])));
        $this->netralkanPengaturan();
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
        $svc  = new SuratSekolah();
        $kep  = new \App\Libraries\SuratKeputusan();
        $p    = (new PklPengaturanModel())->ambil();
        [$op, $hu] = [$this->ctx('operator'), $this->ctx('hubin')];
        $A    = SuratAcara::class;
        $y    = (int) date('Y') + 1;

        // --- Format tanggal otomatis (nama hari dihitung, bukan diketik)
        $kasus = [
            ['2026-09-14', '2026-09-18', 'Senin s.d. Jumat, 14 - 18 September 2026', '14 sampai dengan 18 September 2026'],
            ['2026-10-05', '2026-10-08', 'Senin s.d. Kamis, 5 - 8 Oktober 2026', '5 sampai dengan 8 Oktober 2026'],
            ['2026-10-12', '2026-10-15', 'Senin s.d. Kamis, 12 - 15 Oktober 2026', '12 sampai dengan 15 Oktober 2026'],
            ['2026-09-28', '2026-10-02', 'Senin s.d. Jumat, 28 September - 2 Oktober 2026', '28 September sampai dengan 2 Oktober 2026'],
            ['2026-12-30', '2027-01-01', 'Rabu s.d. Jumat, 30 Desember 2026 - 1 Januari 2027', '30 Desember 2026 sampai dengan 1 Januari 2027'],
            ['2026-10-05', '2026-10-05', 'Senin, 5 Oktober 2026', '5 Oktober 2026'],
        ];
        $salah = [];
        foreach ($kasus as [$a, $b, $ht, $ts]) {
            if ($A::hariTanggal($a, $b) !== $ht || $A::tanggalSampai($a, $b) !== $ts) {
                $salah[] = "$a..$b → " . $A::hariTanggal($a, $b) . ' | ' . $A::tanggalSampai($a, $b);
            }
        }
        $this->cek('format tanggal: 6 bentuk (satu bulan, lintas bulan, lintas tahun, satu hari) benar; 5–8 Okt 2026 = Senin s.d. KAMIS (salah ketik "Jumat" di contoh tak terulang)', $salah === [], implode(' ; ', $salah));
        $this->cek('tanggal tak sah / terbalik → teks kosong (tidak melempar galat)', $A::hariTanggal('2026-10-08', '2026-10-05') === '' && $A::hariTanggal('x', 'y') === '' && $A::tanggalSampai('2026-02-30', '2026-03-01') === '' && $A::tanggalSampai('', '') === '');

        // --- Validasi masukan
        $in = ['tanggal_surat' => "$y-02-01", 'tgl_mulai' => "$y-03-02", 'tgl_selesai' => "$y-03-06", 'tempat' => '', 'semester' => 'Genap', 'tahun_pelajaran' => ($y - 1) . '/' . $y, 'sesi' => 'Gelombang 2', 'mode' => 'umum'];
        $h = $A::periksa(SuratJenis::ASTS, $in);
        $this->cek('periksa ASTS sah: isian lengkap, tempat kosong → "SMK Bina Nusa", cakupan umum', $h['ok'] === true && $h['isi']['kegiatan'] === 'ASTS' && $h['isi']['semester'] === 'Genap' && $h['isi']['tahun_pelajaran'] === ($y - 1) . '/' . $y && $h['isi']['tempat'] === 'SMK Bina Nusa' && $h['mode'] === 'umum' && $h['tanggal_surat'] === "$y-02-01", json_encode($h['galat']));
        $h = $A::periksa(SuratJenis::TKA, $in);
        $this->cek('periksa TKA sah: sesi tersimpan, tanpa semester', $h['ok'] === true && $h['isi']['sesi'] === 'Gelombang 2' && ! isset($h['isi']['semester']) && $h['isi']['kegiatan'] === 'TKA');
        $buruk = [
            'selesai sebelum mulai'  => [['tgl_selesai' => "$y-03-01"] + $in, SuratJenis::ASTS],
            'surat setelah selesai'  => [['tanggal_surat' => "$y-03-07"] + $in, SuratJenis::ASTS],
            'rentang 40 hari'        => [['tgl_selesai' => "$y-04-11"] + $in, SuratJenis::ASTS],
            'tanggal kosong'         => [['tgl_mulai' => ''] + $in, SuratJenis::ASTS],
            'tanggal 30 Feb'         => [['tgl_mulai' => "$y-02-30"] + $in, SuratJenis::ASTS],
            'tahun terlalu jauh'     => [['tgl_mulai' => '2099-03-02', 'tgl_selesai' => '2099-03-03'] + $in, SuratJenis::ASTS],
            'semester ngawur'        => [['semester' => 'Tengah'] + $in, SuratJenis::ASTS],
            'tahun pelajaran salah'  => [['tahun_pelajaran' => ($y - 1) . '/' . ($y + 1)] + $in, SuratJenis::ASTS],
            'sesi kosong (TKA)'      => [['sesi' => '  '] + $in, SuratJenis::TKA],
            'tempat 101 huruf'       => [['tempat' => str_repeat('a', 101)] + $in, SuratJenis::TKA],
            'cakupan ngawur'         => [['mode' => 'semua'] + $in, SuratJenis::ASTS],
            'perusahaan tanpa pilih' => [['mode' => 'perusahaan', 'perusahaan' => []] + $in, SuratJenis::ASTS],
        ];
        $lolos = [];
        foreach ($buruk as $nama => [$masukan, $jenis]) {
            if ($A::periksa($jenis, $masukan)['ok'] !== false) {
                $lolos[] = $nama;
            }
        }
        $this->cek('12 masukan buruk ditolak dengan pesan', $lolos === [], 'lolos: ' . implode(', ', $lolos));
        $h = $A::periksa(SuratJenis::ASTS, ['mode' => 'perusahaan', 'perusahaan' => ['m:12', 'n:abc def', 'jahat!!;', 'm:12', 'x:1']] + $in);
        $this->cek('kunci perusahaan disaring: hanya m:/n: berkarakter aman, tanpa duplikat', $h['ok'] === true && $h['pilihan'] === ['m:12', 'n:abc def']);
        $h = $A::periksa(SuratJenis::ASTS, ['mode' => 'perusahaan', 'perusahaan' => ['m:1']] + $in, 'umum');
        $this->cek('saat mengubah, cakupan terkunci mengikuti surat (masukan "perusahaan" diabaikan)', $h['ok'] === true && $h['mode'] === 'umum' && $h['pilihan'] === []);

        // --- Judul, kunci, penanda
        $isiA = $this->isiAcara(SuratJenis::ASTS, '2026-09-14', '2026-09-18');
        $this->cek('judul: ASTS umum / TKA per perusahaan', $A::judul(SuratJenis::ASTS, $isiA) === 'ASTS Ganjil 2026/2027 - surat umum'
            && $A::judul(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan'), 'PT Contoh', 3) === 'TKA Gelombang 1 - PT Contoh (3 siswa)');
        $k0 = $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08'));
        $this->cek('kunci pencegah ganda: sama bila isian sama (sesi tak peka huruf besar), beda bila tanggal / sesi / cakupan / perusahaan / jenis beda',
            $k0 === $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'umum', 'GELOMBANG 1'))
            && $k0 !== $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-09'))
            && $k0 !== $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'umum', 'Gelombang 2'))
            && $k0 !== $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan'), 'm:1')
            && $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan'), 'm:1') !== $A::kunci(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan'), 'm:2')
            && $A::kunci(SuratJenis::ASTS, $isiA) !== $A::kunci(SuratJenis::TKA, $isiA));
        $tk = $A::token(SuratJenis::ASTS, ['perusahaan_nama' => ''], $isiA);
        $this->cek('penanda ASTS umum: tujuan "Pimpinan / Pembimbing PKL", perihal & kegiatan lengkap persis contoh', $tk['tujuan_surat'] === 'Bapak/Ibu Pimpinan / Pembimbing PKL'
            && $tk['perihal'] === 'Pemberitahuan Pelaksanaan Asesmen Sumatif Tengah Semester (ASTS) Ganjil' && $tk['kegiatan'] === 'Asesmen Sumatif Tengah Semester (ASTS) Ganjil'
            && $tk['kegiatan_lengkap'] === 'Asesmen Sumatif Tengah Semester (ASTS) Ganjil Tahun Pelajaran 2026/2027' && $tk['hari_tanggal'] === 'Senin s.d. Jumat, 14 - 18 September 2026' && $tk['tempat'] === 'SMK Bina Nusa');
        $isiP = $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan');
        $tk   = $A::token(SuratJenis::TKA, ['perusahaan_nama' => 'PT Contoh'], $isiP);
        $this->cek('penanda TKA per perusahaan: tujuan "Pimpinan PT Contoh" (satu baris), sesi; tanpa perihal ASTS', $tk['tujuan_surat'] === 'Bapak/Ibu Pimpinan PT Contoh' && $tk['sesi'] === 'Gelombang 1' && ! isset($tk['perihal']));
        $this->cek('varian template: per perusahaan → "perusahaan", umum / isian kosong → utama; jenis tanpa varian selalu utama; path menunjuk berkas yang berbeda',
            SuratJenis::varian(SuratJenis::TKA, $isiP) === 'perusahaan' && SuratJenis::varian(SuratJenis::ASTS, $this->isiAcara(SuratJenis::ASTS, '2026-09-14', '2026-09-18', 'perusahaan')) === 'perusahaan'
            && SuratJenis::varian(SuratJenis::TKA, $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08')) === '' && SuratJenis::varian(SuratJenis::TKA, []) === '' && SuratJenis::varian(SuratJenis::BALASAN, ['mode' => 'perusahaan']) === ''
            && basename(SuratJenis::pathTemplate(SuratJenis::TKA)) === 'izin_tka.docx' && basename(SuratJenis::pathTemplate(SuratJenis::TKA, 'perusahaan')) === 'izin_tka_perusahaan.docx'
            && basename(SuratJenis::pathTemplate(SuratJenis::BALASAN, 'perusahaan')) === 'balasan_pkl.docx');
        $tk = $A::token(SuratJenis::ASTS, [], []);
        $this->cek('isian kosong (surat lama / uji) → penanda kosong, bukan galat', $tk['hari_tanggal'] === '' && $tk['tanggal_sampai'] === '' && $tk['tempat'] === 'SMK Bina Nusa' && $tk['kegiatan'] === 'Asesmen Sumatif Tengah Semester (ASTS)');

        // --- Membuat surat umum + pencegah ganda
        $acara = new SuratAcara();
        $h     = $A::periksa(SuratJenis::TKA, ['tanggal_surat' => "$y-02-01", 'tgl_mulai' => "$y-03-02", 'tgl_selesai' => "$y-03-05", 'sesi' => 'Gelombang 7', 'mode' => 'umum', 'tempat' => '']);
        $r1    = $acara->buat(SuratJenis::TKA, $h, $this->konteks(), $p);
        $id1   = (int) ($r1['dibuat'][0] ?? 0);
        $row   = $id1 ? $this->surat($id1) : [];
        $isi1  = SuratSekolah::dekodeIsi((string) ($row['isi'] ?? ''));
        $this->cek('buat TKA umum → 1 surat, menunggu ACC, judul benar, isian (sesi) + kunci 40 heksa tersimpan, tanpa perusahaan', $r1['ok'] === true && count($r1['dibuat']) === 1 && $id1 > 0 && $row['status'] === 'menunggu' && $row['judul'] === 'TKA Gelombang 7 - surat umum' && $isi1['sesi'] === 'Gelombang 7' && preg_match('/^[0-9a-f]{40}$/', (string) ($isi1['kunci'] ?? '')) === 1 && $row['perusahaan_nama'] === null);
        $r2 = $acara->buat(SuratJenis::TKA, $h, $this->konteks(), $p);
        $this->cek('surat yang sama dibuat lagi → DITOLAK sebagai kembar (tidak ada baris baru), pesan menyebut kode suratnya', $r2['ok'] === false && $r2['dibuat'] === [] && count($r2['sama']) === 1 && str_contains($r2['sama'][0], \App\Models\SuratSekolahModel::kode($id1)));
        $kep->putuskan($id1, 'batal', ['catatan' => 'Uji pembatalan kembar'], $op, $p);
        $r3 = $acara->buat(SuratJenis::TKA, $h, $this->konteks(), $p);
        $this->cek('setelah suratnya DIBATALKAN, kegiatan yang sama boleh dibuat lagi', $r3['ok'] === true && count($r3['dibuat']) === 1 && $r3['dibuat'][0] !== $id1);
        $id3 = $r3['dibuat'][0] ?? 0;

        // --- Per perusahaan
        $s4 = $this->buatSiswa(4);
        $s5 = $this->buatSiswa(5);
        $aj = $this->buatAjuanDisetujui($s4, 'ZZUJISS Acara Satu', [$s5]);
        $g  = null;
        foreach ($acara->perusahaan("$y-03-02", "$y-03-05") as $x) {
            if ($x['nama'] === 'ZZUJISS Acara Satu') {
                $g = $x;
            }
        }
        $this->cek('daftar perusahaan: perusahaan fixture muncul dengan 2 siswa (pengaju dulu), kelas, kunci m:/n:, periode belum tercatat → tidak ditandai', $g !== null && count($g['siswa']) === 2 && $g['siswa'][0]['siswa_id'] === $s4 && $g['siswa'][0]['kelas'] !== '' && preg_match('/^[mn]:/', $g['kunci']) === 1
            && $g['ajuan_ids'] === [$aj] && $g['mulai'] === null && $g['luar_periode'] === false);
        $this->db->table('pkl_pengajuan')->where('id', $aj)->update(['tanggal_mulai' => "$y-06-01", 'tanggal_selesai' => "$y-08-31"]);
        $luar = null;
        $dalam = null;
        foreach ($acara->perusahaan("$y-03-02", "$y-03-05") as $x) {
            $luar = $x['nama'] === 'ZZUJISS Acara Satu' ? $x : $luar;
        }
        foreach ($acara->perusahaan("$y-06-10", "$y-06-12") as $x) {
            $dalam = $x['nama'] === 'ZZUJISS Acara Satu' ? $x : $dalam;
        }
        $this->cek('periode PKL tercatat (1 Jun–31 Agu): kegiatan Maret → ditandai di luar periode; kegiatan Juni → tidak', $luar !== null && $luar['luar_periode'] === true && $luar['mulai'] === "$y-06-01" && $dalam !== null && $dalam['luar_periode'] === false);
        $this->db->table('siswa')->where('id', $s5)->update(['status' => 'keluar']);
        $satu = null;
        foreach ($acara->perusahaan() as $x) {
            $satu = $x['nama'] === 'ZZUJISS Acara Satu' ? $x : $satu;
        }
        $this->db->table('siswa')->where('id', $s4)->update(['status' => 'lulus']);
        $hilang = true;
        foreach ($acara->perusahaan() as $x) {
            $hilang = $hilang && $x['nama'] !== 'ZZUJISS Acara Satu';
        }
        $this->db->table('siswa')->whereIn('id', [$s4, $s5])->update(['status' => 'aktif']);
        $this->cek('siswa yang bukan "aktif" tidak ikut daftar; bila semuanya keluar, perusahaan hilang dari daftar', $satu !== null && count($satu['siswa']) === 1 && $hilang);

        $kunciG = $g['kunci'] ?? 'm:0';
        $hp = $A::periksa(SuratJenis::ASTS, ['mode' => 'perusahaan', 'perusahaan' => [$kunciG, 'm:2000000000'], 'tanggal_surat' => "$y-02-01", 'tgl_mulai' => "$y-03-02", 'tgl_selesai' => "$y-03-05", 'semester' => 'Genap', 'tahun_pelajaran' => ($y - 1) . '/' . $y]);
        $rp = $acara->buat(SuratJenis::ASTS, $hp, $this->konteks(), $p);
        $idp = (int) ($rp['dibuat'][0] ?? 0);
        $rowp = $idp ? $this->surat($idp) : [];
        $this->cek('buat per perusahaan [fixture, perusahaan tak ada]: 1 surat dibuat, 1 gagal dilaporkan', $rp['ok'] === true && count($rp['dibuat']) === 1 && count($rp['gagal']) === 1 && str_contains($rp['gagal'][0], 'tidak ditemukan'));
        $this->cek('surat per perusahaan: nama perusahaan, tautan ke ajuan, 2 siswa berkelas, judul "… (2 siswa)", mode & kunci perusahaan tersimpan', $idp > 0 && $rowp['perusahaan_nama'] === 'ZZUJISS Acara Satu' && (int) $rowp['pengajuan_id'] === $aj
            && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idp]) === 2 && str_ends_with($rowp['judul'], '- ZZUJISS Acara Satu (2 siswa)') && (SuratSekolah::dekodeIsi((string) $rowp['isi'])['pkey'] ?? '') === $kunciG && (SuratSekolah::dekodeIsi((string) $rowp['isi'])['mode'] ?? '') === 'perusahaan');
        $rp2 = $acara->buat(SuratJenis::ASTS, $hp, $this->konteks(), $p);
        $this->cek('perusahaan yang sama + kegiatan yang sama → dilewati sebagai kembar', $rp2['ok'] === false && $rp2['dibuat'] === [] && count($rp2['sama']) === 1);

        // --- Mengubah surat (pustaka)
        $isiBaru = SuratSekolah::dekodeIsi((string) $row['isi']);
        $isiBaru['tgl_selesai'] = "$y-03-06";
        $u = $svc->ubah($id3, ['judul' => 'TKA Gelombang 7 - surat umum', 'tanggal_surat' => "$y-02-02", 'isi' => $isiBaru], $op);
        $row3 = $this->surat($id3);
        $this->cek('ubah surat menunggu: tanggal & isian berubah, status tetap, riwayat "ubah" tercatat', $u['ok'] === true && $row3['tanggal_surat'] === "$y-02-02" && (SuratSekolah::dekodeIsi((string) $row3['isi'])['tgl_selesai'] ?? '') === "$y-03-06" && $row3['status'] === 'menunggu' && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $id3, 'aksi' => 'ubah']) === 1);
        $u = $svc->ubah($idp, ['judul' => $rowp['judul'], 'tanggal_surat' => $rowp['tanggal_surat'], 'isi' => SuratSekolah::dekodeIsi((string) $rowp['isi']), 'siswa' => [$s4]], $op);
        $this->cek('ubah dengan daftar siswa baru MENGGANTI seluruh daftar (2 → 1 siswa)', $u['ok'] === true && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idp]) === 1);
        $kep->putuskan($id3, 'kembalikan', ['catatan' => 'Uji ubah sesudah dikembalikan'], $this->ctx('hubin'), $p);
        $this->cek('surat yang DIKEMBALIKAN masih bisa diubah (statusnya tetap dikembalikan)', $svc->ubah($id3, ['judul' => 'TKA Gelombang 7 - surat umum', 'tanggal_surat' => "$y-02-03", 'isi' => $isiBaru], $op)['ok'] === true && $this->surat($id3)['status'] === 'dikembalikan');
        $kep->putuskan($id3, 'ajukan_ulang', [], $op, $p);
        $kep->putuskan($id3, 'acc', [], $hu, $p);
        $u = $svc->ubah($id3, ['judul' => 'x', 'tanggal_surat' => "$y-02-03", 'isi' => $isiBaru], $op);
        $this->cek('surat yang sudah DISETUJUI tidak bisa diubah (kode status; harus batalkan ACC dulu)', $u['ok'] === false && $u['kode'] === 'status');
        $svc->terbitkan($id3, $op);
        $kep->putuskan($id3, 'batal', ['catatan' => 'Uji: sudah bernomor lalu batal'], $op, $p);
        $this->db->table('surat_sekolah')->where('id', $id3)->update(['status' => 'menunggu']);
        $u = $svc->ubah($id3, ['judul' => 'x', 'tanggal_surat' => "$y-02-03", 'isi' => $isiBaru], $op);
        $this->cek('surat yang sudah BERNOMOR tidak bisa diubah walau statusnya menunggu (kode terbit)', $u['ok'] === false && $u['kode'] === 'terbit');
        $this->cek('ubah id tak ada / judul kosong / tanggal tak sah ditolak', $svc->ubah(2000000000, ['judul' => 'x', 'tanggal_surat' => "$y-02-03", 'isi' => []], $op)['kode'] === 'tidak_ada'
            && $svc->ubah($idp, ['judul' => ' ', 'tanggal_surat' => "$y-02-03", 'isi' => []], $op)['kode'] === 'judul' && $svc->ubah($idp, ['judul' => 'x', 'tanggal_surat' => 'salah', 'isi' => []], $op)['kode'] === 'tanggal');

        // --- Controller & formulir
        $I = \App\Controllers\Admin\SuratIzin::class;
        set_error_handler(static function (int $severity, string $message, string $file, int $line) {
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $fOp = $this->panggil($I, 'operator', 'asts');
            $fTk = (string) $this->panggil($I, 'operator', 'tka');
            $fOp = (string) $fOp;
            $this->cek('formulir ASTS (Operator): judul, pilihan periode Ujian, semester, tanggal, cakupan umum/per perusahaan, tombol "ajukan untuk ACC" (Operator tanpa hak ACC: tanpa tombol Simpan & setujui)', str_contains($fOp, 'Buat Surat Izin ASTS') && str_contains($fOp, 'Isi otomatis dari menu Ujian') && str_contains($fOp, 'name="semester"') && str_contains($fOp, 'name="tgl_mulai"')
                && str_contains($fOp, 'value="perusahaan"') && str_contains($fOp, 'x-data="suratIzinForm"') && str_contains($fOp, 'Buat surat &amp; ajukan untuk ACC') && ! str_contains($fOp, 'name="langsung_acc"') && str_contains(html_entity_decode($fOp), 'ZZUJISS Acara Satu'));
            $this->cek('formulir TKA: ada kolom sesi + saran gelombang; tidak ada semester / periode Ujian', str_contains($fTk, 'name="sesi"') && str_contains(html_entity_decode($fTk), 'Gelombang 2') && ! str_contains($fTk, 'name="semester"') && ! str_contains($fTk, 'Isi otomatis dari menu Ujian'));
            $this->cek('Hubin (tanpa hak membuat surat) dialihkan dari formulir; Admin dapat membukanya', $this->panggil($I, 'hubin', 'asts') instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains((string) $this->panggil($I, 'admin', 'asts'), 'Buat Surat Izin ASTS'));

            // simpan umum (sah)
            $post = ['tanggal_surat' => "$y-05-02", 'mode' => 'umum', 'semester' => 'Ganjil', 'tahun_pelajaran' => "$y/" . ($y + 1), 'tgl_mulai' => "$y-09-14", 'tgl_selesai' => "$y-09-18", 'tempat' => ''];
            $res  = $this->panggil($I, 'operator', 'simpanAsts', [], $post);
            $idc  = (int) $this->db->table('surat_sekolah')->select('id')->where('dibuat_nama', self::OLEH . ' operator')->where('jenis', 'izin_asts')->orderBy('id', 'DESC')->get(1)->getRowArray()['id'];
            $rowc = $idc ? $this->surat($idc) : [];
            $this->cek('POST simpan ASTS umum (Operator) → surat dibuat berstatus menunggu, dialihkan ke detailnya', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($res->getHeaderLine('Location'), '/admin/surat/' . $idc) && ($rowc['status'] ?? '') === 'menunggu'
                && ($rowc['judul'] ?? '') === 'ASTS Ganjil ' . "$y/" . ($y + 1) . ' - surat umum');
            $res = $this->panggil($I, 'operator', 'simpanAsts', [], $post);
            $this->cek('POST yang sama sekali lagi → ditolak kembar: kembali ke formulir, tidak ada surat baru', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_ends_with($res->getHeaderLine('Location'), '/admin/surat/asts')
                && (int) $this->db->table('surat_sekolah')->where('dibuat_nama', self::OLEH . ' operator')->where('jenis', 'izin_asts')->like('judul', 'ASTS Ganjil ' . "$y/" . ($y + 1) . ' - surat umum')->countAllResults() === 1);
            $res = $this->panggil($I, 'operator', 'simpanAsts', [], ['tgl_selesai' => "$y-09-10"] + $post);
            $this->cek('POST dengan tanggal terbalik → kembali ke formulir (tidak menyimpan)', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_ends_with($res->getHeaderLine('Location'), '/admin/surat/asts') && (int) $this->db->table('surat_sekolah')->where('dibuat_nama', self::OLEH . ' operator')->where('jenis', 'izin_asts')->countAllResults() === 1);
            $this->jagaMasukan = true; // satu permintaan berikutnya (formulir yang ditampilkan ulang) masih memegang isian tadi
            $ulang = html_entity_decode((string) $this->panggil($I, 'operator', 'asts'));
            $this->cek('formulir ditampilkan ulang memegang isian yang tadi diketik (tanggal selesai terbalik) agar operator tinggal membetulkan', str_contains($ulang, '"selesai":"' . $y . '-09-10"') && str_contains($ulang, '"mulai":"' . $y . '-09-14"'));
            $res = $this->panggil($I, 'hubin', 'simpanAsts', [], ['tgl_mulai' => "$y-10-14", 'tgl_selesai' => "$y-10-18"] + $post);
            $this->cek('POST simpan oleh Hubin (tanpa hak) → dialihkan; tidak ada surat dibuat', $res instanceof \CodeIgniter\HTTP\RedirectResponse && (int) $this->db->table('surat_sekolah')->where('dibuat_nama', self::OLEH . ' operator')->where('jenis', 'izin_asts')->countAllResults() === 1);

            // simpan per perusahaan (TKA) — TKA khusus kelas XII (2026-10-21): siswa fixture dipindah sementara ke kelas XII.
            $kXII     = (int) ($this->db->table('kelas')->select('id')->where('tingkat', 'XII')->where('deleted_at', null)->orderBy('id')->get(1)->getRowArray()['id'] ?? 0);
            $idsAcara = array_map('intval', array_column($this->db->table('siswa')->select('id')->whereIn('nis', [self::NIS . 4, self::NIS . 5])->get()->getResultArray(), 'id'));
            $adaAcara = static fn (array $daftar): bool => in_array('ZZUJISS Acara Satu', array_column($daftar, 'nama'), true);
            $this->cek('TKA khusus kelas XII: perusahaan yang siswanya bukan kelas XII tidak ditawarkan untuk TKA (ASTS tetap)', $kXII > 0
                && ! $adaAcara((new \App\Libraries\SuratAcara())->perusahaan(null, null, SuratJenis::TKA)) && $adaAcara((new \App\Libraries\SuratAcara())->perusahaan(null, null, SuratJenis::ASTS)));
            $this->db->table('siswa')->whereIn('id', $idsAcara)->update(['kelas_id' => $kXII]);
            $postP = ['tanggal_surat' => "$y-05-02", 'mode' => 'perusahaan', 'sesi' => 'Gelombang 1', 'tgl_mulai' => "$y-10-05", 'tgl_selesai' => "$y-10-08", 'tempat' => '', 'perusahaan' => [$kunciG]];
            $res   = $this->panggil($I, 'operator', 'simpanTka', [], $postP);
            $this->db->table('siswa')->whereIn('id', $idsAcara)->update(['kelas_id' => $this->kelasId]);
            $rowt  = $this->db->table('surat_sekolah')->where('jenis', 'izin_tka')->where('perusahaan_nama', 'ZZUJISS Acara Satu')->orderBy('id', 'DESC')->get(1)->getRowArray();
            $this->cek('POST simpan TKA per perusahaan (siswa kelas XII) → 1 surat untuk perusahaan itu, dialihkan ke detailnya', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $rowt !== null && str_contains($res->getHeaderLine('Location'), '/admin/surat/' . $rowt['id']) && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $rowt['id']]) === 2);
            $res = $this->panggil($I, 'operator', 'simpanTka', [], ['perusahaan' => ['m:2000000000']] + $postP);
            $this->cek('perusahaan pilihan tak ada → kembali ke formulir dengan pesan, tidak ada surat baru', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_ends_with($res->getHeaderLine('Location'), '/admin/surat/tka'));

            // ubah
            $dOp = (string) $this->panggil(\App\Controllers\Admin\Surat::class, 'operator', 'detail', [], [], $idc);
            $this->cek('detail surat menunggu: tombol "Ubah data surat" menuju formulir ubah', str_contains(html_entity_decode($dOp), '/admin/surat/asts/' . $idc . '/ubah') && str_contains($dOp, 'Ubah data surat'));
            $ub = (string) $this->panggil($I, 'operator', 'ubahAsts', [], [], $idc);
            $rinci = [str_contains($ub, 'Ubah Surat Izin ASTS'), str_contains($ub, "$y-09-14"), str_contains(html_entity_decode($ub), "$y/" . ($y + 1)), str_contains($ub, 'Cakupan dan daftar siswa tidak bisa diubah'), str_contains($ub, 'Simpan perubahan'), ! str_contains($ub, 'name="mode"')];
            $this->cek('formulir ubah terisi data lama (tanggal, tahun pelajaran), cakupan umum tak bisa diganti, tombol "Simpan perubahan"', ! in_array(false, $rinci, true), json_encode($rinci));
            $res = $this->panggil($I, 'operator', 'simpanUbahAsts', [], ['tgl_mulai' => "$y-09-15", 'tgl_selesai' => "$y-09-19"] + $post, $idc);
            $rowc = $this->surat($idc);
            $isiC = SuratSekolah::dekodeIsi((string) $rowc['isi']);
            $isiCtanpa = array_diff_key($isiC, ['kunci' => 1, 'pkey' => 1]);
            $this->cek('POST ubah → dialihkan ke detail; tanggal baru tersimpan, kunci cocok dengan isian baru, riwayat "ubah" tercatat', $res instanceof \CodeIgniter\HTTP\RedirectResponse && ($isiC['tgl_mulai'] ?? '') === "$y-09-15" && ($isiC['kunci'] ?? '') === $A::kunci(SuratJenis::ASTS, $isiCtanpa) && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $idc, 'aksi' => 'ubah']) === 1);
            // buat surat kedua lalu ubah menjadi kembar dengan yang pertama
            $post2 = ['tgl_mulai' => "$y-11-02", 'tgl_selesai' => "$y-11-06"] + $post;
            $this->panggil($I, 'operator', 'simpanAsts', [], $post2);
            $id2 = (int) $this->db->table('surat_sekolah')->select('id')->where('dibuat_nama', self::OLEH . ' operator')->where('jenis', 'izin_asts')->orderBy('id', 'DESC')->get(1)->getRowArray()['id'];
            $res = $this->panggil($I, 'operator', 'simpanUbahAsts', [], ['tgl_mulai' => "$y-09-15", 'tgl_selesai' => "$y-09-19"] + $post, $id2);
            $this->cek('mengubah surat menjadi kembar surat lain → ditolak, kembali ke formulir ubah; tanggalnya tidak berubah', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_ends_with($res->getHeaderLine('Location'), '/ubah') && (SuratSekolah::dekodeIsi((string) $this->surat($id2)['isi'])['tgl_mulai'] ?? '') === "$y-11-02");
            $kep->putuskan($idc, 'acc', [], $hu, $p);
            $res = $this->panggil($I, 'operator', 'ubahAsts', [], [], $idc);
            $res2 = $this->panggil($I, 'operator', 'simpanUbahAsts', [], $post, $idc);
            $this->cek('surat sudah disetujui: formulir ubah & simpan ubah dialihkan ke detail dengan galat; tombol Ubah hilang dari detail', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $res2 instanceof \CodeIgniter\HTTP\RedirectResponse
                && ! str_contains((string) $this->panggil(\App\Controllers\Admin\Surat::class, 'operator', 'detail', [], [], $idc), 'Ubah data surat'));
            $this->cek('ubah surat jenis lain lewat alamat yang salah (id TKA lewat rute ASTS) → dialihkan', $this->panggil($I, 'operator', 'ubahAsts', [], [], $id3 ?: 1) instanceof \CodeIgniter\HTTP\RedirectResponse);
        } finally {
            restore_error_handler();
            session()->remove('admin');
        }

        // --- Template ASLI (tanpa penimpa): isi, penanda, data pribadi, kesamaan dengan contoh sekolah
        SuratJenis::$templateTimpa = [];
        $this->cek('templateLengkap: ASTS & TKA (utama + varian perusahaan), Pernyataan, Balasan, Penarikan ada', SuratJenis::templateLengkap(SuratJenis::ASTS) && SuratJenis::templateLengkap(SuratJenis::TKA)
            && SuratJenis::templateLengkap(SuratJenis::PERNYATAAN) && SuratJenis::templateLengkap(SuratJenis::BALASAN) && SuratJenis::templateLengkap(SuratJenis::PENARIKAN));
        foreach ([SuratJenis::ASTS, SuratJenis::TKA] as $jenis) {
            foreach (['' => 'umum', 'perusahaan' => 'per perusahaan'] as $varian => $namaVarian) {
                $path = SuratJenis::pathTemplate($jenis, $varian);
                $ada  = is_file($path);
                $nama = $jenis . ' (' . $namaVarian . ')';
                $this->cek('template ' . $nama . ' ada di folder Libraries/Surat', $ada, basename($path));
                if (! $ada) {
                    continue;
                }
                $tokenTpl = \App\Libraries\PklDocx::penandaDi($path);
                $contoh   = SuratBerkas::isi(['jenis' => $jenis, 'perlu_acc' => 1, 'tanggal_surat' => '2026-08-14', 'isi' => json_encode($this->isiAcara($jenis, '2026-09-14', '2026-09-18', $varian === '' ? 'umum' : 'perusahaan')), 'perusahaan_nama' => ''], [], null, [], $p, []);
                $boleh    = array_merge(array_keys($contoh['v']), ['no', 'siswa_nama', 'siswa_nis', 'siswa_nisn', 'siswa_kelas', 'siswa_jurusan', 'siswa_hp']);
                $asing    = array_values(array_diff($tokenTpl, $boleh));
                $this->cek('template ' . $nama . ': semua penanda ${…} dikenal perakit (' . count($tokenTpl) . ' penanda)', $asing === [] && $tokenTpl !== [], 'tak dikenal: ' . implode(', ', $asing));
                $this->cek('template ' . $nama . ($varian === '' ? ': TANPA tabel siswa' : ': memuat tabel siswa (${no}, ${siswa_nama}) di halaman Lampiran'), ($varian === '') === (! in_array('siswa_nama', $tokenTpl, true)));
                $zip = new \ZipArchive();
                $zip->open($path);
                $mentah = strip_tags((string) preg_replace('/<\/w:p>/', "\n", (string) $zip->getFromName('word/document.xml'))) . ' ' . (string) $zip->getFromName('docProps/core.xml');
                $zip->close();
                $bocor = array_values(array_filter(['Napis', 'Kuturupi', 'Puguh', 'Wira Sakti', '0812 8584', 'abilseptian', '220/SMK-BN', '255/SMK-BN'], static fn (string $k) => stripos($mentah, $k) !== false));
                $this->cek('template ' . $nama . ' bersih dari data asli (nama Kepsek / Hubin, HP, nomor contoh, akun penyusun) — repo publik', $bocor === [], implode(', ', $bocor));
            }
        }

        // --- Berkas dari template asli = badan surat contoh sekolah (14–18 Sep 2026 / 5–8 Okt 2026)
        $this->atur(['kepsek_nama' => 'Napis Kuturupi, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Puguh Wira Sakti, S.Pd.', 'kontak_surat_hp' => '0812 8584 526']);
        $p   = (new PklPengaturanModel())->ambil();
        $bk  = new SuratBerkas();
        $keluar = (string) getenv('UJI_SURAT_KELUAR');
        $sampel = [
            [SuratJenis::ASTS, 'SURAT PERMOHONAN IZIN ASTS.docx', '2026-08-14', '2026-09-14', '2026-09-18', '14 Agustus 2026'],
            [SuratJenis::TKA, 'Surat Permohonan Izin TKA Gelombang 1.docx', '2026-10-05', '2026-10-05', '2026-10-08', '5 Oktober 2026'],
        ];
        foreach ($sampel as [$jenis, $berkasContoh, $tglSurat, $mulai, $selesai, $tglTeks]) {
            $isi = $this->isiAcara($jenis, $mulai, $selesai);
            $r   = $svc->buat($jenis, ['judul' => self::OLEH . ' Berkas asli ' . $jenis, 'tanggal_surat' => $tglSurat, 'isi' => $isi], $this->konteks(), $p);
            $sid = (int) ($r['id'] ?? 0);
            $kep->putuskan($sid, 'acc', [], $hu, $p);
            $h = $bk->bangun([$sid], $op);
            if (! $h['ok']) {
                $this->cek('berkas ' . $jenis . ' dari template asli berhasil dibuat', false, json_encode($h));
                continue;
            }
            $teks = $this->isiDocx($h['biner']);
            if ($keluar !== '') {
                file_put_contents(rtrim($keluar, '/\\') . '/hasil_' . $jenis . '_umum.docx', $h['biner']);
            }
            $this->cek('berkas ' . $jenis . ' dari template asli dibuat; tak ada penanda ${…} tersisa; tanggal surat "Bekasi, ' . $tglTeks . '"', ! str_contains($teks, '${') && str_contains($teks, 'Bekasi, ' . $tglTeks));
            $this->cek('berkas ' . $jenis . ': kolom tanda tangan lengkap — Waka Hubin, Kepala Sekolah bertitik, kontak NB, kaki ACC', str_contains($teks, 'Hubin Uji, S.Pd.') && str_contains($teks, 'Napis Kuturupi, S.T.') && str_contains($teks, 'Puguh Wira Sakti, S.Pd.') && str_contains($teks, '0812 8584 526') && str_contains($teks, 'disetujui secara elektronik'));
            $sumber = ROOTPATH . 'formatdatasekolah/' . $berkasContoh;
            if (! is_file($sumber)) {
                $this->cek('berkas ' . $jenis . ': dibandingkan dengan contoh sekolah — dilewati (berkas contoh tak ada di mesin ini)', true);
                continue;
            }
            $badanContoh = $this->badanSurat($this->teksDocxBerkas($sumber));
            $badanHasil  = $this->badanSurat($teks);
            $this->cek('berkas ' . $jenis . ': BADAN SURAT sama persis dengan contoh sekolah (dari "Kepada Yth." sampai "terima kasih.")', $badanContoh !== '' && $badanContoh === $badanHasil, $badanContoh === $badanHasil ? '' : "\n   contoh: " . mb_substr($badanContoh, 0, 400) . "\n   hasil : " . mb_substr($badanHasil, 0, 400));
        }

        // --- Surat izin ASAS & ASAT (template Izin ASTS yang sama, 2026-10-21): nama ujian & singkatan menggantikan "ASTS".
        foreach ([['ASAS', 'Ganjil', 'Asesmen Sumatif Akhir Semester (ASAS) Ganjil'], ['ASAT', 'Ganjil', 'Asesmen Sumatif Akhir Tahun (ASAT)']] as [$kodeU, $smt, $namaU]) {
            $isiU = ['kegiatan' => $kodeU, 'semester' => $smt] + $this->isiAcara(SuratJenis::ASTS, '2026-12-07', '2026-12-11');
            $r    = $svc->buat(SuratJenis::ASTS, ['judul' => self::OLEH . ' Berkas ' . $kodeU, 'tanggal_surat' => '2026-11-30', 'isi' => $isiU], $this->konteks(), $p);
            $sid  = (int) ($r['id'] ?? 0);
            $kep->putuskan($sid, 'acc', [], $hu, $p);
            $h    = $bk->bangun([$sid], $op);
            $teks = $h['ok'] ? preg_replace('/\s+/u', ' ', $this->isiDocx($h['biner'])) : '';
            $this->cek('berkas izin ' . $kodeU . ': perihal & kegiatan "' . $namaU . '", "pelaksanaan ' . $kodeU . ' di sekolah", "rangkaian ' . $kodeU . ' selesai", tanpa kata ASTS, tanpa penanda tersisa', $h['ok']
                && str_contains($teks, 'Pemberitahuan Pelaksanaan ' . $namaU) && str_contains($teks, 'pelaksanaan ' . $kodeU . ' di sekolah') && str_contains($teks, 'rangkaian ' . $kodeU . ' selesai')
                && ! str_contains($teks, 'ASTS') && ! str_contains($teks, '${'), mb_substr($teks, 0, 300));
        }

        // --- Berkas per perusahaan (template yang sama)
        $r   = $svc->buat(SuratJenis::TKA, ['judul' => self::OLEH . ' Berkas per perusahaan', 'tanggal_surat' => '2026-10-05', 'isi' => $this->isiAcara(SuratJenis::TKA, '2026-10-05', '2026-10-08', 'perusahaan'),
            'perusahaan_nama' => 'ZZUJISS Acara Satu', 'siswa' => [$s4, $s5]], $this->konteks(), $p);
        $sid = (int) ($r['id'] ?? 0);
        $kep->putuskan($sid, 'acc', [], $hu, $p);
        $h = $bk->bangun([$sid], $op);
        $teks = $h['ok'] ? $this->isiDocx($h['biner']) : '';
        if ($keluar !== '' && $h['ok']) {
            file_put_contents(rtrim($keluar, '/\\') . '/hasil_izin_tka_perusahaan.docx', $h['biner']);
        }
        $this->cek('berkas TKA per perusahaan: nama perusahaan di alamat tujuan, "Lampiran : 1 (satu) lembar", kalimat "terlampir", tanpa "Pembimbing PKL" umum', $h['ok'] === true && str_contains($teks, 'Bapak/Ibu Pimpinan') && str_contains($teks, 'ZZUJISS Acara Satu')
            && str_contains($teks, '1 (satu) lembar') && str_contains($teks, 'sebagaimana daftar terlampir') && ! str_contains($teks, 'Pembimbing PKL') && ! str_contains($teks, '${'));
        $this->cek('halaman LAMPIRAN: judul "DAFTAR PESERTA DIDIK", nomor surat, perusahaan, kegiatan + sesi, tabel berisi kedua siswa dengan NIS & kelas', str_contains($teks, 'DAFTAR PESERTA DIDIK') && str_contains($teks, 'Lampiran surat nomor ' . $this->surat($sid)['nomor'])
            && str_contains($teks, 'Tes Kemampuan Akademik (TKA) - Gelombang 1') && str_contains($teks, 'ZZUJI SS 4') && str_contains($teks, 'ZZUJI SS 5') && str_contains($teks, self::NIS . '4') && str_contains($teks, self::NIS . '5'));

        // --- Unduhan campuran: satu surat umum + satu surat per perusahaan (jenis sama) → ZIP dua berkas, masing-masing template-nya
        $ru  = $svc->buat(SuratJenis::TKA, ['judul' => self::OLEH . ' Campur umum', 'tanggal_surat' => '2026-10-05', 'isi' => $this->isiAcara(SuratJenis::TKA, '2026-10-12', '2026-10-15', 'umum', 'Gelombang 2')], $this->konteks(), $p);
        $idu = (int) ($ru['id'] ?? 0);
        $kep->putuskan($idu, 'acc', [], $hu, $p);
        $hc  = $bk->bangun([$sid, $idu], $op);
        $namaZip = [];
        $pk = [];
        if ($hc['ok'] && $hc['zip']) {
            $tmp = tempnam(sys_get_temp_dir(), 'ujicmp');
            file_put_contents($tmp, $hc['biner']);
            $z = new \ZipArchive();
            if ($z->open($tmp) === true) {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $namaZip[] = (string) $z->getNameIndex($i);
                    $pk[(string) $z->getNameIndex($i)] = (string) $z->getFromIndex($i);
                }
                $z->close();
            }
            @unlink($tmp);
        }
        sort($namaZip);
        $this->cek('unduhan campuran umum + per perusahaan → ZIP dengan 2 berkas Word (umum / per perusahaan); yang per perusahaan memuat Lampiran, yang umum tidak', $hc['ok'] === true && $hc['zip'] === true && $hc['jumlah'] === 2 && str_ends_with($hc['nama'], '.zip')
            && count($namaZip) === 2 && str_contains($namaZip[0], 'per perusahaan') && str_contains($namaZip[1], 'umum') && str_starts_with($pk[$namaZip[0]], 'PK') && str_starts_with($pk[$namaZip[1]], 'PK')
            && str_contains($this->isiDocx($pk[$namaZip[0]]), 'DAFTAR PESERTA DIDIK') && ! str_contains($this->isiDocx($pk[$namaZip[1]]), 'DAFTAR PESERTA DIDIK'), json_encode($namaZip));
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
    }

    // =================================================================
    // 9. L4–L6: Pernyataan Orang Tua, Surat Balasan PKL, Surat Penarikan Izin PKL
    // =================================================================

    /** document.xml dari biner .docx. */
    private function xmlDocx(string $biner): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ujisrt');
        file_put_contents($tmp, $biner);
        $z   = new \ZipArchive();
        $xml = $z->open($tmp) === true ? (string) $z->getFromName('word/document.xml') : '';
        $z->close();
        @unlink($tmp);

        return $xml;
    }

    private function xmlBerkas(string $path): string
    {
        $z   = new \ZipArchive();
        $xml = $z->open($path) === true ? (string) $z->getFromName('word/document.xml') : '';
        $z->close();

        return $xml;
    }

    private function teksXml(string $xml): string
    {
        return html_entity_decode(strip_tags((string) preg_replace('/<\/w:p>/', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Tabel siswa (judul "NOMOR HANDPHONE"): [xml tabel, daftar baris data → daftar teks sel]. */
    private function tabelSiswa(string $xml): array
    {
        // Tanpa pola runut-balik per huruf (lihat PklDocx::gandakanBaris): cari judul, lalu tabel yang membungkusnya.
        $j = strpos($xml, 'NOMOR HANDPHONE');
        $a = $j === false ? false : strrpos(substr($xml, 0, $j), '<w:tbl>');
        $b = $j === false ? false : strpos($xml, '</w:tbl>', $j);
        if ($a === false || $b === false) {
            return ['', []];
        }
        $tabel = substr($xml, $a, $b + strlen('</w:tbl>') - $a);
        preg_match_all('~<w:tr[ >][^<]*+(?:<(?!/w:tr>)[^<]*+)*+</w:tr>~', $tabel, $tr);
        $baris = [];
        foreach (array_slice($tr[0], 1) as $r) {
            preg_match_all('~<w:tc>[^<]*+(?:<(?!/w:tc>)[^<]*+)*+</w:tc>~', $r, $tc);
            $baris[] = array_map(fn (string $c) => trim((string) preg_replace('/\s+/u', ' ', $this->teksXml($c))), $tc[0]);
        }

        return [$tabel, $baris];
    }

    /** Badan surat ("Kepada Yth." … "terima kasih.") dengan baris data tabel siswa dibuang (judul tabel tetap). */
    private function badanTanpaSiswa(string $xml): string
    {
        [$tbl] = $this->tabelSiswa($xml);
        if ($tbl !== '') {
            preg_match_all('~<w:tr[ >][^<]*+(?:<(?!/w:tr>)[^<]*+)*+</w:tr>~', $tbl, $tr, PREG_OFFSET_CAPTURE);
            $akhir = end($tr[0]);
            $xml   = str_replace($tbl, substr($tbl, 0, $tr[0][1][1]) . substr($tbl, $akhir[1] + strlen($akhir[0])), $xml);
        }

        return $this->badanSurat($this->teksXml($xml));
    }

    /**
     * Data surat contoh sekolah, dibaca saat uji (berkas contoh di luar repo; data aslinya TIDAK ditulis di kode repo publik).
     *
     * @return array{perusahaan: string, sapaan: string, alamat: string, tgl_efektif: string, alasan: string, sel: list<string>}
     */
    private function dataContoh(string $path): array
    {
        $xml  = $this->xmlBerkas($path);
        $teks = trim((string) preg_replace('/\s+/u', ' ', $this->teksXml($xml)));
        $cari = static fn (string $pola): string => preg_match($pola, $teks, $m) === 1 ? trim($m[1]) : '';
        $tgl  = '';
        if (preg_match('~terhitung sejak tanggal (\d{1,2}) (\p{L}+) (\d{4})~u', $teks, $m) === 1 && ($b = array_search($m[2], \App\Libraries\IsianBantu::BULAN, true)) !== false) {
            $tgl = sprintf('%04d-%02d-%02d', (int) $m[3], $b + 1, (int) $m[1]);
        }
        $sel = [];
        foreach ($this->tabelSiswa($xml)[1] as $baris) {
            foreach (array_slice($baris, 1) as $c) {
                if (mb_strlen($c) >= 4) {
                    $sel[] = $c;
                }
            }
        }

        return [
            'perusahaan'  => $cari('~Dengan ini kami menyampaikan bahwa (.+?) bersedia menerima~u') ?: $cari('~peserta didik SMK Bina Nusa Kab\. Bekasi di (.+?), kami menyampaikan~u'),
            'sapaan'      => $cari('~Kepada Yth\. Bapak/Ibu (\S+)~u'),
            'alamat'      => $cari('~Alamat : (.+?) Selama kegiatan~u'),
            'tgl_efektif' => $tgl,
            'alasan'      => $cari('~dilakukan karena (.+?)\. Kami mengucapkan~u'),
            'sel'         => array_values(array_unique($sel)),
        ];
    }

    private function ujiSuratPkl(): void
    {
        $this->bagian('L4–L6: Pernyataan Orang Tua, Surat Balasan, Surat Penarikan — aturan, template asli, buat/ubah, berkas Word');
        $this->netralkanPengaturan();
        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000',
            'durasi_min_hari' => 0, 'durasi_maks_hari' => 0, 'mulai_paling_awal' => null, 'selesai_paling_akhir' => null]);
        $svc = new SuratSekolah();
        $kep = new \App\Libraries\SuratKeputusan();
        $bk  = new SuratBerkas();
        $lib = new SuratPkl();
        $per = new SuratPernyataan();
        $p   = (new PklPengaturanModel())->ambil();
        [$op, $hu] = [$this->ctx('operator'), $this->ctx('hubin')];
        $keluar = (string) getenv('UJI_SURAT_KELUAR'); // folder untuk menyimpan contoh hasil Word (opsional)

        // --- Murni: lama & periode PKL, sapaan, alasan, periksa
        $kasus = [
            ['2026-08-24', '2026-11-24', '3 (tiga) bulan', '24 Agustus s.d. 24 November 2026'],
            ['2026-08-24', '2026-11-23', '3 (tiga) bulan', '24 Agustus s.d. 23 November 2026'],
            ['2026-07-01', '2026-12-31', '6 (enam) bulan', '1 Juli s.d. 31 Desember 2026'],
            ['2026-11-24', '2027-02-24', '3 (tiga) bulan', '24 November 2026 s.d. 24 Februari 2027'],
            ['2026-09-01', '2026-09-30', '1 (satu) bulan', '1 s.d. 30 September 2026'],
            ['2026-09-30', '2026-09-01', '', ''],
        ];
        $salah = [];
        foreach ($kasus as [$a, $b, $lama, $per2]) {
            if (SuratPkl::lamaTeks($a, $b) !== $lama || SuratPkl::periodeTeks($a, $b) !== $per2) {
                $salah[] = "$a..$b → \"" . SuratPkl::lamaTeks($a, $b) . '" / "' . SuratPkl::periodeTeks($a, $b) . '"';
            }
        }
        $this->cek('lama PKL ("3 (tiga) bulan" = hari ÷ 30,4375 dibulatkan) & teks periode (lintas tahun, sebulan, terbalik → kosong): 6 bentuk', $salah === [], implode('; ', $salah));
        $this->cek('sapaan: kosong → Pimpinan; "Bapak/Ibu Rektorat" → Rektorat; "Yth. Bapak Direktur" → Direktur', SuratPkl::sapaan('') === 'Pimpinan' && SuratPkl::sapaan('Bapak/Ibu Rektorat') === 'Rektorat'
            && SuratPkl::sapaan(' Yth.  Bapak Direktur ') === 'Direktur' && SuratPkl::sapaan('HRD') === 'HRD');
        $this->cek('alasan penarikan: saran ke-1 = kalimat contoh sekolah; ketikan dirapikan ("Karena Siswa sakit." → "siswa sakit"; singkatan tetap)', SuratPkl::alasan(['alasan_pilih' => '0']) === SuratPkl::ALASAN_SARAN[0]
            && str_starts_with(SuratPkl::ALASAN_SARAN[0], 'adanya keperluan administrasi sekolah') && SuratPkl::alasan(['alasan_pilih' => 'lain', 'alasan_lain' => 'Karena Siswa sakit keras.']) === 'siswa sakit keras'
            && SuratPkl::alasan(['alasan_pilih' => 'lain', 'alasan_lain' => 'PT ABC tutup']) === 'PT ABC tutup' && SuratPkl::alasan(['alasan_pilih' => '99']) === '');
        $hB = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'periode_mulai' => '2026-11-24', 'periode_selesai' => '2026-08-24']);
        $hP = SuratPkl::periksa(SuratJenis::PENARIKAN, ['tanggal_surat' => date('Y-m-d'), 'tgl_efektif' => date('Y-m-d'), 'alasan_pilih' => 'lain', 'alasan_lain' => ' . ']);
        $this->cek('periksa: Balasan dengan selesai sebelum mulai ditolak; Penarikan tanpa alasan ditolak', ! $hB['ok'] && str_contains(implode(' ', $hB['galat']), 'harus setelah') && ! $hP['ok'] && str_contains(implode(' ', $hP['galat']), 'Alasan'));
        $this->cek('peringatan periode terhadap Pengaturan PKL (minimal 120 hari, batas selesai) — tidak memblokir', SuratPkl::peringatanPeriode('2026-08-24', '2026-11-24', ['durasi_min_hari' => 120, 'selesai_paling_akhir' => '2026-11-01']) !== []
            && count(SuratPkl::peringatanPeriode('2026-08-24', '2026-11-24', ['durasi_min_hari' => 120, 'selesai_paling_akhir' => '2026-11-01'])) === 2 && SuratPkl::peringatanPeriode('2026-08-24', '2026-11-24', ['durasi_min_hari' => 90]) === []);

        $o1 = SuratPernyataan::ortu(['nama_orang_tua' => 'Budi Uji', 'nama_ayah' => 'BUDI UJI', 'pekerjaan_ayah' => 'Wiraswasta', 'nama_ibu' => 'Sri Uji', 'pekerjaan_ibu' => 'Guru',
            'ortu_alamat' => 'Jl. Uji 7', 'ortu_rt' => '3', 'ortu_rw' => '12', 'ortu_kelurahan' => 'Ujikel', 'ortu_kecamatan' => 'Ujikec', 'ortu_kota' => 'Bekasi', 'ortu_telepon' => '', 'no_hp_wali' => '0812 9']);
        $o2 = SuratPernyataan::ortu(['nama_orang_tua' => 'Sri Uji', 'nama_ayah' => 'Budi', 'pekerjaan_ayah' => 'Buruh', 'nama_ibu' => 'Sri Uji', 'pekerjaan_ibu' => 'Sudah Meninggal',
            'ortu_alamat' => 'Kp. Uji rt 001 rw 002 Ujikel', 'ortu_rt' => '1', 'ortu_rw' => '2', 'ortu_kelurahan' => 'Ujikel', 'ortu_kota' => 'Kota Bekasi']);
        $o3 = SuratPernyataan::ortu(['nama_ibu' => 'Ibu Uji', 'pekerjaan_ibu' => 'Pedagang', 'alamat' => 'Jl. Siswa 1', 'kota' => 'Bekasi']);
        $this->cek('data orang tua: pekerjaan milik orang yang tercetak; "Sudah Meninggal" → titik-titik; alamat lengkap tanpa mengulang RT/kelurahan; cadangan ibu & alamat siswa; HP wali',
            $o1 === ['nama' => 'Budi Uji', 'pekerjaan' => 'Wiraswasta', 'alamat' => 'Jl. Uji 7, RT 003/RW 012, Ujikel, Kec. Ujikec, Bekasi', 'hp' => '0812 9']
            && $o2['pekerjaan'] === '' && $o2['alamat'] === 'Kp. Uji rt 001 rw 002 Ujikel, Kota Bekasi'
            && $o3 === ['nama' => 'Ibu Uji', 'pekerjaan' => 'Pedagang', 'alamat' => 'Jl. Siswa 1, Bekasi', 'hp' => ''], json_encode([$o1, $o2, $o3], JSON_UNESCAPED_UNICODE));
        $q1 = SuratPernyataan::periksa(['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'kelas']);
        $q2 = SuratPernyataan::periksa(['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'siswa', 'siswa' => ['5', '5', 'x', '7'], 'isi_ortu' => '0']);
        $this->cek('periksa Pernyataan: tanpa pilihan ditolak; id dirapikan (ganda/sampah dibuang); "kosongkan" dipahami', ! $q1['ok'] && $q2['ok'] && $q2['pilihan'] === [5, 7] && $q2['isi_ortu'] === false);

        // --- Template asli: ada, penanda dikenal perakit, bersih dari data asli (repo publik)
        $contoh = [
            SuratJenis::PERNYATAAN => glob(ROOTPATH . 'formatdatasekolah/SURAT PERNYATAAN ORANG TUA PKL*.docx')[0] ?? null,
            SuratJenis::BALASAN    => glob(ROOTPATH . 'formatdatasekolah/Surat Balasan PKL*.docx')[0] ?? null,
            SuratJenis::PENARIKAN  => glob(ROOTPATH . 'formatdatasekolah/Surat Penarikan Izin PKL*.docx')[0] ?? null,
        ];
        $isiContoh = json_encode(['sapaan' => 'Pimpinan', 'alamat' => 'x', 'periode_mulai' => '2026-08-24', 'periode_selesai' => '2026-11-24', 'tgl_efektif' => '2026-09-28', 'alasan' => 'x']);
        foreach ($contoh as $jenis => $sumber) {
            $path = SuratJenis::pathTemplate($jenis);
            $this->cek('template ' . $jenis . ' ada di folder Libraries/Surat', is_file($path), basename($path));
            if (! is_file($path)) {
                continue;
            }
            $token = PklDocx::penandaDi($path);
            $boleh = $jenis === SuratJenis::PERNYATAAN
                ? array_keys(SuratPernyataan::halaman(['tanggal_surat' => '2026-10-11', 'isi' => '{}'], [['siswa_id' => 1, 'nama' => 'X']])[0]['v'])
                : array_merge(array_keys(SuratBerkas::isi(['jenis' => $jenis, 'perlu_acc' => 1, 'tanggal_surat' => '2026-10-11', 'isi' => $isiContoh, 'perusahaan_nama' => 'x'], [], null, [], $p, [])['v']),
                    ['no', 'siswa_nama', 'siswa_nis', 'siswa_nisn', 'siswa_kelas', 'siswa_jurusan', 'siswa_hp']);
            $asing = array_values(array_diff($token, $boleh));
            $this->cek('template ' . $jenis . ': semua penanda ${…} dikenal perakit (' . count($token) . ' penanda)', $asing === [] && $token !== [], 'tak dikenal: ' . implode(', ', $asing));
            $z = new \ZipArchive();
            $z->open($path);
            $mentah = $this->teksXml((string) $z->getFromName('word/document.xml')) . ' ' . (string) $z->getFromName('docProps/core.xml');
            $z->close();
            $data      = $sumber !== null ? $this->dataContoh($sumber) : ['perusahaan' => '', 'alamat' => '', 'sel' => []];
            $terlarang = array_merge(['Napis', 'Kuturupi', 'Puguh', 'Wira Sakti', '0812 8584', 'abilseptian', 'Lenovo', '255/SMK-BN'], array_filter([$data['perusahaan'], $data['alamat']]), $data['sel']);
            $bocor     = array_values(array_filter($terlarang, static fn (string $k) => $k !== '' && stripos($mentah, $k) !== false));
            $this->cek('template ' . $jenis . ' bersih dari data asli (penanda tangan, kontak, akun penyusun, ' . count($data['sel']) . ' sel tabel siswa & perusahaan contoh)', $bocor === [], count($bocor) . ' kata bocor');
        }

        // --- Pernyataan: template + isian kosong = contoh sekolah, utuh satu halaman
        $pathPern = SuratJenis::pathTemplate(SuratJenis::PERNYATAAN);
        if ($contoh[SuratJenis::PERNYATAAN] !== null && is_file($pathPern)) {
            $kosong = array_fill_keys(['ortu_nama', 'ortu_pekerjaan', 'ortu_alamat', 'ortu_hp', 'anak_nama', 'anak_kelas', 'anak_jurusan', 'anak_hp'], SuratPernyataan::titik())
                + ['ttd_anak' => SuratPernyataan::titikTtd(), 'ttd_ortu' => SuratPernyataan::titikTtd(), 'tahun' => '2026'];
            $rapat  = static fn (string $s): string => trim((string) preg_replace('/\s+/u', ' ', $s));
            $hasil  = $rapat($this->isiDocx(PklDocx::dariTemplate($pathPern, [['v' => $kosong, 'siswa' => []]])));
            $asli   = $rapat($this->teksDocxBerkas($contoh[SuratJenis::PERNYATAAN]));
            $this->cek('Pernyataan: template diisi titik-titik = contoh sekolah SAMA PERSIS (seluruh teks halaman, ' . mb_strlen($asli) . ' huruf)', $asli !== '' && $hasil === $asli, $hasil === $asli ? '' : "\n   contoh: " . mb_substr($asli, 0, 300) . "\n   hasil : " . mb_substr($hasil, 0, 300));
        } else {
            $this->cek('Pernyataan: pembanding contoh sekolah — dilewati (berkas contoh tak ada di mesin ini)', true);
        }

        // --- Data uji: dua siswa dalam satu ajuan disetujui; siswa 61 punya data orang tua, siswa 62 tidak
        $s61 = $this->buatSiswa(61);
        $s62 = $this->buatSiswa(62);
        $s63 = $this->buatSiswa(63);
        $this->db->table('siswa')->where('id', $s61)->update(['nama_orang_tua' => 'ZZUJI ORTU 61', 'nama_ayah' => 'ZZUJI ORTU 61', 'pekerjaan_ayah' => 'Wiraswasta',
            'ortu_alamat' => 'Jl. Uji Ortu 7', 'ortu_rt' => '3', 'ortu_rw' => '4', 'ortu_kelurahan' => 'Ujikel', 'ortu_kecamatan' => 'Ujikec', 'ortu_kota' => 'Bekasi', 'ortu_telepon' => '081299990061']);
        $aid = $this->buatAjuanDisetujui($s61, 'ZZUJISS Balasan Satu', [$s62]);
        $daf = $lib->ajuan([$aid]);
        $this->cek('ajuan disetujui untuk formulir: 2 siswa (pengaju dulu), alamat lengkap, belum ada Balasan', count($daf) === 1 && count($daf[0]['siswa']) === 2 && $daf[0]['siswa'][0]['siswa_id'] === $s61
            && $daf[0]['alamat'] === 'Jl. Uji Surat No. 1, Bekasi' && $daf[0]['balasan'] === null, json_encode($daf[0] ?? null));

        // --- Balasan: buat, periode tersimpan di ajuan, pencegah ganda, peringatan, ubah, ACC, berkas
        $h  = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => '', 'periode_mulai' => '2027-08-24', 'periode_selesai' => '2027-11-24']);
        $rb = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $this->konteks(), $p);
        $idB = (int) ($rb['dibuat'][0] ?? 0);
        $sb  = $this->surat($idB);
        $aj  = $this->db->table('pkl_pengajuan')->where('id', $aid)->get()->getRowArray();
        $this->cek('Balasan dibuat: menunggu ACC, tertaut ke ajuan, 2 siswa, isi sapaan Pimpinan + alamat + periode', $rb['ok'] && ($sb['status'] ?? '') === 'menunggu' && (int) ($sb['pengajuan_id'] ?? 0) === $aid
            && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idB]) === 2 && str_contains((string) ($sb['isi'] ?? ''), '"sapaan":"Pimpinan"') && str_contains((string) ($sb['isi'] ?? ''), '"alamat":"Jl. Uji Surat No. 1, Bekasi"'), json_encode($rb));
        $this->cek('periode PKL ikut tersimpan di ajuan + tercatat di riwayat ajuan', ($aj['tanggal_mulai'] ?? '') === '2027-08-24' && ($aj['tanggal_selesai'] ?? '') === '2027-11-24'
            && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $aid, 'aksi' => 'periode']) === 1);
        $rb2 = $lib->buat(SuratJenis::BALASAN, $h, [$aid], [], $this->konteks(), $p);
        $this->cek('Balasan kedua untuk ajuan yang sama ditolak (satu ajuan = satu Balasan aktif)', ! $rb2['ok'] && count($rb2['sama']) === 1 && ($lib->ajuan([$aid])[0]['balasan']['id'] ?? 0) === $idB);
        $mB = $svc->muat($idB);
        $pMin = ['durasi_min_hari' => 120] + $p;
        $this->cek('periode kurang dari minimal di Pengaturan PKL → peringatan "awas" di detail (ACC massal melewatinya)', in_array('awas', array_column(SuratPeriksa::untuk($mB['surat'], $mB['siswa'], $pMin), 'tingkat'), true)
            && ! in_array('awas', array_column(SuratPeriksa::untuk($mB['surat'], $mB['siswa'], $p), 'tingkat'), true));
        $hU = SuratPkl::periksa(SuratJenis::BALASAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => 'Bapak/Ibu Direktur', 'periode_mulai' => '2027-09-01', 'periode_selesai' => '2027-12-01', 'alamat' => 'Jl. Ubah 9, Bekasi']);
        $ru = $lib->ubahSurat($mB, $hU, [], $this->konteks());
        $sb = $this->surat($idB);
        $aj = $this->db->table('pkl_pengajuan')->where('id', $aid)->get()->getRowArray();
        $this->cek('ubah Balasan: sapaan & alamat & periode berubah; periode ajuan ikut diperbarui', $ru['ok'] && str_contains((string) $sb['isi'], '"sapaan":"Direktur"') && str_contains((string) $sb['isi'], 'Jl. Ubah 9')
            && ($aj['tanggal_mulai'] ?? '') === '2027-09-01' && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $aid, 'aksi' => 'periode']) === 2, json_encode($ru));
        $kep->putuskan($idB, 'acc', [], $hu, $p);
        $hb  = $bk->bangun([$idB], $op);
        $tB  = $hb['ok'] ? trim((string) preg_replace('/\s+/u', ' ', $this->isiDocx($hb['biner']))) : '';
        $hilang = static fn (string $teks, array $harus): array => array_values(array_filter($harus, static fn (string $k) => ! str_contains($teks, $k)));
        $kurang = $hilang($tB, ['Bapak/Ibu Direktur', 'ZZUJISS Balasan Satu Di Tempat', 'ZZUJI SS 61', 'ZZUJI SS 62', '3 (tiga) bulan, bulan 1 September s.d. 1 Desember 2027', 'Jl. Ubah 9, Bekasi', 'Bekasi, ' . \App\Libraries\IsianBantu::tanggalIndo(date('Y-m-d'))]);
        $this->cek('berkas Balasan: nomor terbit, "Bapak/Ibu Direktur", perusahaan "Di Tempat", kedua siswa, "3 (tiga) bulan, bulan 1 September s.d. 1 Desember 2027", alamat, tanggal tanpa nol', $hb['ok'] === true
            && (string) $this->surat($idB)['nomor'] !== '' && $kurang === [] && ! str_contains($tB, '${'), 'tak ditemukan: ' . implode(' | ', $kurang));
        $this->cek('berkas Balasan: tanda tangan Waka Hubin + Kepala Sekolah, NB, kaki "disetujui secara elektronik"', str_contains($tB, 'Hubin Uji, S.Pd.') && str_contains($tB, 'Kepsek Uji, S.T.') && str_contains($tB, 'Kontak Uji 0812000') && str_contains($tB, 'disetujui secara elektronik'));

        // --- Penarikan: siswa sebagian, pencegah ganda, riwayat ajuan, berkas (tanggal tebal)
        $h  = SuratPkl::periksa(SuratJenis::PENARIKAN, ['tanggal_surat' => date('Y-m-d'), 'sapaan' => '', 'tgl_efektif' => '2027-09-28', 'alasan_pilih' => '0']);
        $rx = $lib->buat(SuratJenis::PENARIKAN, $h, [$aid], [$s63], $this->konteks(), $p);
        $this->cek('Penarikan dengan siswa yang bukan anggota ajuan ditolak', ! $rx['ok'] && $rx['gagal'] !== []);
        $rp = $lib->buat(SuratJenis::PENARIKAN, $h, [$aid], [$s62], $this->konteks(), $p);
        $idP = (int) ($rp['dibuat'][0] ?? 0);
        $sp  = $this->surat($idP);
        $this->cek('Penarikan dibuat: hanya siswa 62, menunggu ACC, judul memuat perusahaan & nama, riwayat ajuan tercatat', $rp['ok'] && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idP]) === 1
            && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idP, 'siswa_id' => $s62]) === 1 && ($sp['status'] ?? '') === 'menunggu' && str_contains((string) ($sp['judul'] ?? ''), 'ZZUJISS Balasan Satu - ZZUJI SS 62')
            && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $aid, 'aksi' => 'surat_sekolah']) >= 1, json_encode($rp));
        $this->cek('Penarikan sama persis (ajuan + siswa + tanggal) tidak dibuat dua kali; ajuan berbeda sekaligus ditolak', ! $lib->buat(SuratJenis::PENARIKAN, $h, [$aid], [$s62], $this->konteks(), $p)['ok']
            && ! $lib->buat(SuratJenis::PENARIKAN, $h, [$aid, $aid + 1], [$s62], $this->konteks(), $p)['ok']);
        $this->cek('status PKL siswa TIDAK berubah oleh surat penarikan (tetap terkunci di ajuan disetujui)', (int) ($this->db->table('pkl_anggota')->where('siswa_id', $s62)->get()->getRowArray()['siswa_aktif'] ?? 0) === $s62);
        $kep->putuskan($idP, 'acc', [], $hu, $p);
        $hpk = $bk->bangun([$idP], $op);
        $xP  = $hpk['ok'] ? $this->xmlDocx($hpk['biner']) : '';
        $tP  = trim((string) preg_replace('/\s+/u', ' ', $this->teksXml($xP)));
        $kurang = $hilang($tP, ['terhitung sejak tanggal 28 September 2027', 'karena ' . SuratPkl::ALASAN_SARAN[0] . '.', 'ZZUJI SS 62']);
        $this->cek('berkas Penarikan: "terhitung sejak tanggal 28 September 2027", alasan contoh, perusahaan 4×, hanya siswa 62', $hpk['ok'] === true && $kurang === []
            && substr_count($tP, 'ZZUJISS Balasan Satu') === 4 && ! str_contains($tP, 'ZZUJI SS 61') && ! str_contains($tP, '${'),
            'tak ditemukan: ' . implode(' | ', $kurang) . ' · perusahaan ' . substr_count($tP, 'ZZUJISS Balasan Satu') . '× · ada siswa 61: ' . (str_contains($tP, 'ZZUJI SS 61') ? 'ya' : 'tidak'));
        $this->cek('berkas Penarikan: tanggal "terhitung sejak" tetap TEBAL seperti contoh', preg_match('~<w:r>(<w:rPr>[^<]*+(?:<(?!/w:rPr>)[^<]*+)*+</w:rPr>)<w:t[^>]*>28 September 2027</w:t>~', $xP, $mb) === 1 && str_contains($mb[1], '<w:b/>'));
        // Kaki ACC = paragraf terakhir, SESUDAH "NB :" dan kontak "1. …" (permintaan klien 2026-10-21; dulu di atas NB), dua baris.
        $urutKaki = static fn (string $t): bool => ($nb = strpos($t, 'NB :')) !== false && ($ko = strpos($t, 'Kontak Uji', $nb)) !== false
            && ($ka = strpos($t, 'disetujui secara elektronik')) !== false && $ka > $ko;
        $posKaki  = (int) strpos($xP, 'disetujui secara elektronik');
        $awalKaki = (int) strrpos(substr($xP, 0, $posKaki), '<w:p>');
        $kakiXml  = $posKaki > 0 ? substr($xP, $awalKaki, (int) strpos($xP, '</w:p>', $posKaki) - $awalKaki) : '';
        $this->cek('berkas Balasan & Penarikan: kaki "disetujui secara elektronik" di PALING BAWAH (sesudah NB & kontak), dua baris', $urutKaki($tB) && $urutKaki($tP)
            && substr_count($kakiXml, '<w:br/>') === 1 && str_contains($kakiXml, 'Kode verifikasi') && str_contains($kakiXml, 'Dicetak oleh'), substr($tP, -260));

        // --- Badan surat = contoh sekolah (data contoh dibaca dari berkasnya; baris siswa dibandingkan terpisah)
        $this->atur(['kepsek_nama' => 'Napis Kuturupi, S.T.', 'kontak_surat_nama' => 'Puguh Wira Sakti, S.Pd.', 'kontak_surat_hp' => '0812 8584 526']);
        $p = (new PklPengaturanModel())->ambil();
        foreach ([SuratJenis::BALASAN, SuratJenis::PENARIKAN] as $jenis) {
            $sumber = $contoh[$jenis];
            if ($sumber === null) {
                $this->cek($jenis . ': dibandingkan dengan contoh sekolah — dilewati (berkas contoh tak ada di mesin ini)', true);
                continue;
            }
            $d   = $this->dataContoh($sumber);
            $isi = $jenis === SuratJenis::BALASAN
                ? ['sapaan' => $d['sapaan'], 'alamat' => $d['alamat'], 'periode_mulai' => '2026-08-24', 'periode_selesai' => '2026-11-24']
                : ['sapaan' => $d['sapaan'], 'tgl_efektif' => $d['tgl_efektif'], 'alasan' => $d['alasan']];
            $r   = $svc->buat($jenis, $this->data('Banding ' . $jenis, '2031-08-20', $isi, [['siswa_id' => $s61], ['siswa_id' => $s62]], null, $d['perusahaan']), $this->konteks(), $p);
            $sid = (int) ($r['id'] ?? 0);
            $kep->putuskan($sid, 'acc', [], $hu, $p);
            $h2  = $bk->bangun([$sid], $op);
            $xml = $h2['ok'] ? $this->xmlDocx($h2['biner']) : '';
            if ($keluar !== '' && $h2['ok']) {
                file_put_contents(rtrim($keluar, '/\\') . '/hasil_' . $jenis . '.docx', $h2['biner']);
            }
            $bc  = $this->badanTanpaSiswa($this->xmlBerkas($sumber));
            $bh  = $this->badanTanpaSiswa($xml);
            $this->cek($jenis . ': BADAN SURAT sama persis dengan contoh sekolah ("Kepada Yth." … "terima kasih.", data contoh, baris siswa diganti siswa uji)', $bc !== '' && $bc === $bh && $d['perusahaan'] !== '',
                $bc === $bh ? '' : "\n   contoh: " . mb_substr($bc, 0, 300) . "\n   hasil : " . mb_substr($bh, 0, 300));
            $tbHasil = $this->tabelSiswa($xml)[1];
            $tbAsli  = $this->tabelSiswa($this->xmlBerkas($sumber))[1];
            $this->cek($jenis . ': tabel siswa berisi baris uji urut (No, Nama, Kelas, Konsentrasi, HP) — jumlah kolom sama dengan contoh', ($tbHasil[1][0] ?? '') === '2'
                && ($tbHasil[0][1] ?? '') === 'ZZUJI SS 61' && count($tbHasil[0] ?? []) === count($tbAsli[0] ?? [0]), json_encode([$tbHasil, count($tbAsli[0] ?? [])], JSON_UNESCAPED_UNICODE));
        }

        $this->atur(['kepsek_nama' => 'Kepsek Uji, S.T.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);
        $p = (new PklPengaturanModel())->ambil();

        // --- Pernyataan: buat per ajuan / per siswa / per kelas, langsung siap unduh, satu halaman per siswa
        $h1 = SuratPernyataan::periksa(['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'ajuan', 'ajuan' => [(string) $aid], 'isi_ortu' => '1']);
        $r1 = $per->buat($h1, $this->konteks(), $p);
        $id1 = (int) ($r1['dibuat'][0] ?? 0);
        $sp1 = $this->surat($id1);
        $this->cek('Pernyataan per ajuan: langsung "siap unduh", tanpa ACC & tanpa nomor, tertaut ke ajuan, 2 siswa', $r1['ok'] && ($sp1['status'] ?? '') === 'disetujui' && (int) ($sp1['perlu_acc'] ?? 1) === 0
            && (string) ($sp1['nomor'] ?? '') === '' && (int) ($sp1['pengajuan_id'] ?? 0) === $aid && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $id1]) === 2, json_encode($r1));
        $this->cek('Pernyataan yang sama persis tidak dibuat dua kali (unduh ulang surat lama)', ! $per->buat($h1, $this->konteks(), $p)['ok']);
        $r2 = $per->buat(SuratPernyataan::periksa(['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'siswa', 'siswa' => [(string) $s61], 'isi_ortu' => '0']), $this->konteks(), $p);
        $id2 = (int) ($r2['dibuat'][0] ?? 0);
        $jmlKelas = (int) $this->db->table('siswa')->where('kelas_id', $this->kelasId)->where('status', 'aktif')->where('deleted_at', null)->countAllResults();
        $r3 = $per->buat(SuratPernyataan::periksa(['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'kelas', 'kelas' => [(string) $this->kelasId], 'isi_ortu' => '1']), $this->konteks(), $p);
        $id3 = (int) ($r3['dibuat'][0] ?? 0);
        $this->cek('Pernyataan siswa pilihan (kosongkan data ortu) & per kelas (' . $jmlKelas . ' siswa aktif) dibuat', $r2['ok'] && $r3['ok'] && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $id3]) === $jmlKelas);

        $hp1 = $bk->bangun([$id1], $op);
        $t1  = $hp1['ok'] ? $this->isiDocx($hp1['biner']) : '';
        if ($keluar !== '' && $hp1['ok']) {
            file_put_contents(rtrim($keluar, '/\\') . '/hasil_pernyataan_ortu.docx', $hp1['biner']);
        }
        $hal = explode('SURAT PERNYATAAN DAN PERSETUJUAN ORANG TUA/WALI', $t1);
        $th  = substr(date('Y-m-d'), 0, 4);
        $this->cek('berkas Pernyataan: 2 halaman (satu per siswa), tanpa penanda tersisa, tahun ' . $th, $hp1['ok'] === true && count($hal) === 3 && ! str_contains($t1, '${') && substr_count($t1, 'Bekasi, ................................ ' . $th) === 2);
        $this->cek('halaman siswa 61: data orang tua dari data sekolah (nama, pekerjaan, alamat lengkap, HP) + nama di kurung tanda tangan', str_contains($hal[1] ?? '', 'ZZUJI ORTU 61') && str_contains($hal[1] ?? '', 'Wiraswasta')
            && str_contains($hal[1] ?? '', 'Jl. Uji Ortu 7, RT 003/RW 004, Ujikel, Kec. Ujikec, Bekasi') && str_contains($hal[1] ?? '', '081299990061') && str_contains($hal[1] ?? '', '(ZZUJI SS 61)') && str_contains($hal[1] ?? '', '(ZZUJI ORTU 61)'));
        $this->cek('halaman siswa 62 (data orang tua kosong): titik-titik seperti contoh, kurung tanda tangan orang tua bertitik', str_contains($hal[2] ?? '', 'ZZUJI SS 62') && substr_count($hal[2] ?? '', SuratPernyataan::titik()) >= 4
            && str_contains($hal[2] ?? '', '(' . SuratPernyataan::titikTtd() . ')'));
        $hp2 = $bk->bangun([$id2], $op);
        $t2  = $hp2['ok'] ? $this->isiDocx($hp2['biner']) : '';
        $this->cek('"Kosongkan data orang tua": data orang tua TIDAK tercetak (titik-titik), data siswa tetap', $hp2['ok'] === true && ! str_contains($t2, 'ZZUJI ORTU 61') && str_contains($t2, 'ZZUJI SS 61') && substr_count($t2, SuratPernyataan::titik()) >= 4);
        $hg = $bk->bangun([$id1, $id2], $op);
        $this->cek('unduh 2 surat Pernyataan sekaligus (3 halaman) → satu berkas Word, dihitung 2 surat', $hg['ok'] === true && $hg['zip'] === false && $hg['jumlah'] === 2 && substr_count($this->isiDocx($hg['biner']), 'SURAT PERNYATAAN DAN PERSETUJUAN') === 3);
        $this->db->table('siswa')->where('id', $s61)->update(['pekerjaan_ayah' => 'Pedagang']);
        $p = (new PklPengaturanModel())->ambil();
        $m1 = $svc->muat($id1);
        $m2 = $svc->muat($id2);
        $this->cek('data orang tua berubah setelah diunduh → "perlu cetak ulang" hanya pada surat yang mencetak data orang tua', SuratSekolah::perluCetakUlang($m1['surat'], $m1['siswa'], $p) && ! SuratSekolah::perluCetakUlang($m2['surat'], $m2['siswa'], $p));

        // --- Controller & tampilan per peran
        set_error_handler(static function (int $no, string $pesan, string $berkas, int $baris): bool {
            throw new \ErrorException($pesan, 0, $no, $berkas, $baris);
        });
        try {
            $fOrtu = $this->panggil(\App\Controllers\Admin\SuratPernyataan::class, 'operator', 'index', ['ajuan' => (string) $aid]);
            $fBal  = $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'operator', 'balasan', ['ajuan' => (string) $aid]);
            $fTar  = $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'operator', 'penarikan', ['ajuan' => (string) $aid]);
            $this->cek('formulir Pernyataan / Balasan / Penarikan tampil untuk Operator (memuat ajuan uji, Balasan yang sudah ada ditandai)', is_string($fOrtu) && str_contains($fOrtu, 'suratOrtuForm') && str_contains(html_entity_decode($fOrtu), 'ZZUJISS Balasan Satu')
                && is_string($fBal) && str_contains($fBal, 'suratPklForm') && str_contains(html_entity_decode($fBal), '"balasan":{"id":' . $idB) && is_string($fTar) && str_contains($fTar, 'Ditarik terhitung sejak tanggal'));
            $this->cek('Waka Hubin (tanpa hak "Surat Sekolah") tidak bisa membuka formulir Balasan & Pernyataan → dialihkan', $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'hubin', 'balasan') instanceof \CodeIgniter\HTTP\RedirectResponse
                && $this->panggil(\App\Controllers\Admin\SuratPernyataan::class, 'hubin', 'index') instanceof \CodeIgniter\HTTP\RedirectResponse);
            $res = $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'operator', 'simpanPenarikan', [], ['tanggal_surat' => date('Y-m-d'), 'sapaan' => 'Pimpinan', 'ajuan' => [(string) $aid], 'siswa' => [(string) $s61],
                'tgl_efektif' => '2027-10-05', 'alasan_pilih' => 'lain', 'alasan_lain' => 'Siswa diminta kembali ke sekolah.']);
            $baru = $this->db->table('surat_sekolah')->where('jenis', SuratJenis::PENARIKAN)->where('pengajuan_id', $aid)->orderBy('id', 'DESC')->get()->getRowArray();
            $this->cek('simpan Penarikan lewat controller → dialihkan ke detail surat; alasan ketikan dirapikan', $res instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains((string) ($baru['isi'] ?? ''), '"alasan":"siswa diminta kembali ke sekolah"'));
            $idBaru = (int) ($baru['id'] ?? 0);
            $det    = (string) $this->panggil(\App\Controllers\Admin\Surat::class, 'operator', 'detail', [], [], $idBaru);
            $fUbah  = $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'operator', 'ubahPenarikan', [], [], $idBaru);
            $this->cek('detail Penarikan (menunggu) punya tombol "Ubah data surat"; formulir ubah tampil dengan siswa tercentang', str_contains($det, 'Ubah data surat') && str_contains($det, 'Alasan penarikan')
                && is_string($fUbah) && str_contains($fUbah, 'Simpan perubahan') && str_contains(html_entity_decode($fUbah), '"siswaDipilih":["' . $s61 . '"]'));
            $res = $this->panggil(\App\Controllers\Admin\SuratPkl::class, 'operator', 'simpanUbahPenarikan', [], ['tanggal_surat' => date('Y-m-d'), 'sapaan' => 'Pimpinan', 'siswa' => [(string) $s61, (string) $s62],
                'tgl_efektif' => '2027-10-06', 'alasan_pilih' => '3'], $idBaru);
            $this->cek('ubah Penarikan lewat controller: siswa jadi 2, tanggal & alasan berganti', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $idBaru]) === 2
                && str_contains((string) $this->surat($idBaru)['isi'], '2027-10-06') && str_contains((string) $this->surat($idBaru)['isi'], 'permintaan orang tua'));
            $dPkl = (string) $this->panggil(\App\Controllers\Admin\Pkl::class, 'operator', 'detail', [], [], $aid);
            $this->cek('detail ajuan PKL: kartu "Surat sekolah" memuat surat terkait + tombol Balasan / Penarikan / Pernyataan; periode PKL tampil', str_contains($dPkl, 'Surat sekolah') && str_contains($dPkl, 'Buat Surat Balasan')
                && str_contains($dPkl, 'pernyataan-ortu?ajuan=' . $aid) && str_contains($dPkl, 'Balasan PKL') && str_contains($dPkl, '1 September 2027'));
            $this->cek('menu SURAT SEKOLAH: kelima jenis aktif (tak ada lagi yang "Segera")', array_filter(SuratJenis::kode(), static fn (string $k) => ! SuratJenis::siap($k)) === []);
        } finally {
            restore_error_handler();
            session()->remove('admin');
        }
        $this->atur(['durasi_min_hari' => (int) ($this->pengaturanAsli['durasi_min_hari'] ?? 0), 'durasi_maks_hari' => (int) ($this->pengaturanAsli['durasi_maks_hari'] ?? 0)]);
    }

    // =================================================================
    // Pemulihan
    // =================================================================

    private function simpanPengaturanAsli(): void
    {
        $this->simpanPeranAsli();
        $this->pengaturanAsli ??= (function () {
            $r = (new PklPengaturanModel())->ambil();
            unset($r['id']);

            return $r;
        })();
    }

    /** Pengaturan netral untuk uji: tanpa aturan ACC khusus, format bawaan, lantai 1. */
    private function netralkanPengaturan(): void
    {
        $this->atur(['hak_peran' => null, 'surat_perlu_acc' => null, 'format_nomor' => self::BAKU, 'nomor_awal' => 1, 'nomor_awal_tahun' => null]);
        $this->setelPeranBawaan();
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        SuratJenis::$templateTimpa = [];
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete(); // CASCADE: siswa & riwayat (juga yang dibuat lewat controller: "ZZUJI SS operator")
        $this->db->table('surat_sekolah')->like('dibuat_nama', 'Siswa: ' . self::OLEH, 'after')->delete(); // dibuat dari permintaan siswa uji (Layanan Siswa)
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_map('intval', array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete(); // CASCADE: anggota, riwayat, surat
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujiss', 'after')->delete();
        $this->db->table('audit_log')->like('deskripsi', 'ZZUJI SS')->delete();
        if ($this->db->tableExists('surat_jadwal')) { // jadwal uji: sesi/tempat bertanda ZZUJISS (permintaan ikut terhapus bersama siswanya)
            $this->db->table('surat_jadwal')->groupStart()->like('sesi', self::NIS)->orLike('tempat', self::NIS)->groupEnd()->delete();
            $this->db->table('audit_log')->where('tabel', 'surat_jadwal')->like('deskripsi', self::NIS)->delete();
        }

        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        $this->pulihkanPeran();
        PklHak::lupakan();
    }
}
