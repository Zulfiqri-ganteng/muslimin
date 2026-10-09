<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\PklSurat;
use App\Libraries\SuratAcara;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratNomor;
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
            'admin/surat/asts' => 'surat_sekolah', 'admin/surat/tka' => 'surat_sekolah', 'admin/surat/pernyataan-ortu' => 'surat_sekolah',
            'admin/surat/balasan' => 'surat_sekolah', 'admin/surat/penarikan' => 'surat_sekolah', 'admin/surat/12/unduh' => 'surat_sekolah',
            'admin/surat/12/ubah' => 'surat_sekolah', 'admin/surat/12/batal' => 'surat_sekolah', 'admin/surat/asts/simpan' => 'surat_sekolah',
            'admin/surat/12/acc' => 'acc', 'admin/surat/12/kembalikan' => 'acc', 'admin/surat/12/batal-acc' => 'acc', 'admin/surat/acc-massal' => 'acc',
            'admin/pkl' => null, 'admin/pkl/12/surat' => 'surat', 'admin/surat-lain' => null, 'admin/master/siswa' => null,
        ];
        $salah = [];
        foreach ($peta as $a => $harap) {
            if ($H::hakUntukAlamat($a) !== $harap) {
                $salah[] = $a . ' → ' . json_encode($H::hakUntukAlamat($a));
            }
        }
        $this->cek('alamat → hak yang dituntut (' . count($peta) . ' alamat)', $salah === [], implode(' | ', $salah));
        $this->cek('huruf besar / garis ganda / query tetap terpetakan', $H::hakUntukAlamat('ADMIN/Surat/Asts') === 'surat_sekolah' && $H::hakUntukAlamat('admin//surat//12//acc') === 'acc' && $H::hakUntukAlamat('/admin/surat/') === null);
    }

    // =================================================================
    // 2. Hak akses
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Hak akses: Operator, Waka Hubin, Admin, peran asing');
        $this->simpanPengaturanAsli();
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => null]);
        PklHak::lupakan();
        $H = PklHak::class;

        $this->cek('hak baru terdaftar, bawaan Operator memegangnya, Hubin tidak', isset($H::HAK['surat_sekolah']) && in_array('surat_sekolah', $H::BAWAAN['operator'], true) && ! in_array('surat_sekolah', $H::BAWAAN['hubin'], true));
        $this->cek('PklHak::boleh: Operator & Admin ya; Hubin & peran asing tidak', $H::boleh('operator', 'surat_sekolah') && $H::boleh('admin', 'surat_sekolah') && ! $H::boleh('hubin', 'surat_sekolah') && ! $H::boleh('guru', 'surat_sekolah') && ! $H::boleh('', 'surat_sekolah'));

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

        // Admin memberi Hubin hak membuat surat: menu terbuka, peringatan pemisahan tugas muncul.
        $adm  = ['oleh' => self::OLEH . ' Admin', 'admin_id' => null, 'peran' => 'admin', 'ip' => self::IP];
        $baru = $H::BAWAAN;
        $baru['hubin'][] = 'surat_sekolah';
        $r = $H::simpan($baru, $adm);
        $this->cek('Admin menyimpan hak Hubin + surat_sekolah → tersimpan', $r['ok'] === true);
        $this->cek('Hubin kini boleh membuka halaman jenis surat, Operator tetap', HakAkses::boleh('hubin', 'admin/surat/asts') && HakAkses::boleh('operator', 'admin/surat/asts'));
        $adaPeringatan = false;
        foreach ($r['peringatan'] ?? [] as $w) {
            $adaPeringatan = $adaPeringatan || str_contains($w, 'Waka Hubin memegang ACC sekaligus unduh surat');
        }
        $this->cek('peringatan pemisahan tugas muncul (ACC + surat_sekolah satu peran)', $adaPeringatan, json_encode($r['peringatan'] ?? []));

        // Hak ini boleh dikosongkan (hanya Admin yang membuat) — tidak ikut daftar wajib ACC/Unduh surat PKL.
        $kosong = $H::BAWAAN;
        $kosong['operator'] = array_values(array_diff($kosong['operator'], ['surat_sekolah']));
        $r2 = $H::simpan($kosong, $adm);
        $this->cek('hak surat_sekolah boleh tak dipegang siapa pun (Admin tetap bisa)', $r2['ok'] === true && ! HakAkses::boleh('operator', 'admin/surat/asts') && HakAkses::boleh('admin', 'admin/surat/asts'));

        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => null]);
        PklHak::lupakan();
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
        $r  = $kep->putuskan($a2, 'acc', [], $ad, $p);
        $this->cek('Admin ACC tanpa menyatakan "mewakili Waka Hubin" → wakil 422, status tetap', $r['kode'] === 'wakil' && $r['http'] === 422 && $this->surat($a2)['status'] === 'menunggu');
        $r = $kep->putuskan($a2, 'acc', ['wakil' => '1', 'catatan' => 'Hubin sedang dinas luar'], $ad, $p);
        $this->cek('Admin ACC dengan pernyataan → disetujui, acc_peran=admin, riwayat "Mewakili Waka Hubin — …"', $r['ok'] === true && $this->surat($a2)['acc_peran'] === 'admin' && $this->riwayatCatatan($a2, 'acc') === 'Mewakili Waka Hubin — Hubin sedang dinas luar');

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
        $this->cek('ACC massal: Operator → dilarang 403; Admin tanpa pernyataan → wakil; pilihan kosong → catatan', $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m1]], $op, $p)['http'] === 403
            && $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m1]], $ad, $p)['kode'] === 'wakil' && $kep->accMassal(['mode' => 'terpilih', 'ids' => []], $hu, $p)['kode'] === 'catatan');
        $r = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m1, $m2, $m3]], $hu, $p);
        $this->cek('ACC massal terpilih [M1, M2, M3]: 2 disetujui, 1 dilewati (M3 tanpa siswa) dengan alasannya', $r['ok'] === true && $r['disetujui'] === 2 && count($r['dilewati']) === 1 && str_contains($r['dilewati'][0], \App\Models\SuratSekolahModel::kode($m3))
            && $this->surat($m1)['status'] === 'disetujui' && $this->surat($m2)['status'] === 'disetujui' && $this->surat($m3)['status'] === 'menunggu', json_encode($r));
        $this->cek('riwayat massal bercatatan "ACC massal"; Audit Log mencatat ringkasan + nama pelaku', $this->riwayatCatatan($m1, 'acc') === 'ACC massal' && $this->auditAda('ACC massal surat sekolah oleh ' . self::OLEH . ' hubin: 2 disetujui, 1 dilewati'));
        $m4 = $this->baru('izin_asts', 'Kep Massal M4 admin');
        $r  = $kep->accMassal(['mode' => 'terpilih', 'ids' => [$m4], 'wakil' => '1'], $ad, $p);
        $this->cek('Admin ACC massal dengan pernyataan → disetujui; acc_peran=admin; riwayat "ACC massal — mewakili Waka Hubin"', $r['disetujui'] === 1 && $this->surat($m4)['acc_peran'] === 'admin' && $this->riwayatCatatan($m4, 'acc') === 'ACC massal — mewakili Waka Hubin');
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
        $this->cek('kaki surat ACC oleh Admin menyebut "mewakili Waka Hubin"', str_contains($B::kakiSurat(['acc_peran' => 'admin'] + $dasar, $p, $ekstra), 'Admin Sistem, mewakili Waka Hubin'));
        $tanpa = $B::kakiSurat(['perlu_acc' => 0] + $dasar, $p, $ekstra);
        $this->cek('kaki surat tanpa ACC: hanya keterangan cetak (tidak mengaku "disetujui")', str_contains($tanpa, 'Dicetak melalui Sistem Informasi Akademik Sekolah (BINUS) oleh Siti Operator') && ! str_contains($tanpa, 'disetujui') && $B::kakiSurat(['perlu_acc' => 0] + $dasar, $p, []) === '');

        // --- Penanda umum
        $ttd = ['path' => '/x.png', 'ext' => 'png', 'cx' => 1512000, 'cy' => 648000];
        $surat = ['jenis' => 'balasan_pkl', 'perlu_acc' => 1, 'nomor' => '12/SMK-BN/PKL/III/2031', 'urut' => 12, 'tanggal_surat' => '2031-03-05', 'perusahaan_nama' => 'PT Contoh', 'isi' => '{}'] + $dasar;
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
        $this->cek('tanda tangan digital Waka Hubin dipasang HANYA bila yang ACC akun Hubin (dan gambar ada)', str_starts_with($v['ttd_hubin'], \App\Libraries\PklDocx::RAW) && str_contains($v['ttd_hubin'], PklSurat::REL_TTD)
            && $B::isi(['acc_peran' => 'admin'] + $surat, $siswa, null, [], $p, ['ttd' => $ttd])['v']['ttd_hubin'] === '' && $B::isi($surat, $siswa, null, [], $p, [])['v']['ttd_hubin'] === ''
            && $B::isi(['perlu_acc' => 0] + $surat, $siswa, null, [], $p, ['ttd' => $ttd])['v']['ttd_hubin'] === '');
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
            $this->cek('detail menunggu — Operator: TIDAK ada tombol ACC; ada keterangan menunggu ACC dan tombol Batalkan surat', ! str_contains($dOp, 'ACC surat ini') && str_contains($dOp, 'menunggu <b>ACC</b> dari peran yang berhak') && str_contains($dOp, "dlg = 'batal'"));
            $this->cek('detail menunggu — Hubin: tidak ada tombol Batalkan surat (bukan haknya); Admin: ada kotak pernyataan "mewakili Waka Hubin"', ! str_contains($dHu, "dlg = 'batal'") && str_contains($dAd, 'name="wakil"') && str_contains($dAd, 'Admin yang mewakili Waka Hubin'));
            $dBahaya = (string) $this->panggil($S, 'hubin', 'detail', [], [], $v5);
            $this->cek('surat tanpa siswa: peringatan BAHAYA tampil dan dialog ACC meminta centang "sudah saya periksa"', str_contains($dBahaya, 'BAHAYA: Surat ini belum memuat satu pun siswa') && str_contains($dBahaya, 'name="paham"'));
            $this->atur(['kontak_surat_nama' => '']);
            $this->cek('kontak NB kosong: peringatan awas tampil (tanpa memblokir ACC)', str_contains((string) $this->panggil($S, 'hubin', 'detail', [], [], $v1), 'Kontak &quot;NB&quot; di bawah surat belum diisi'));
            $this->atur(['kontak_surat_nama' => 'Kontak Uji']);

            // --- Detail surat siap unduh / dikembalikan / dibatalkan
            \App\Libraries\SuratJenis::$templateTimpa = ['izin_tka' => $templateUji];
            $sOp = (string) $this->panggil($S, 'operator', 'detail', [], [], $v2);
            $sHu = (string) $this->panggil($S, 'hubin', 'detail', [], [], $v2);
            $this->cek('detail siap unduh — Operator (template ada): formulir unduh berpenanda data-unduh ke alamat yang benar + tombol "Unduh surat (Word)"', str_contains($sOp, '/admin/surat/' . $v2 . '/unduh"') && str_contains($sOp, 'data-unduh') && str_contains($sOp, 'Unduh surat (Word)'));
            $this->cek('detail siap unduh — Hubin: pesan "diunduh oleh peran yang diberi hak", TANPA formulir unduh; tombol Batalkan persetujuan tampil', str_contains($sHu, 'Surat diunduh oleh peran yang diberi hak') && ! str_contains($sHu, '/unduh"') && str_contains($sHu, 'Batalkan persetujuan'));
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
            $this->cek('tab Menunggu — Hubin: formulir ACC massal + kotak centang; Operator: keterangan terkunci, tanpa formulir; Admin: ada pernyataan "mewakili"', str_contains($lHu, 'id="formAcc"') && str_contains($lHu, 'name="ids[]"') && ! str_contains($lOp, 'id="formAcc"') && str_contains($lOp, 'ACC dilakukan peran yang diberi hak ACC')
                && str_contains($lAd, 'id="formAcc"') && str_contains($lAd, 'name="wakil"'));
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
            $this->cek('daftar menandai "⚠ perlu cetak ulang" untuk surat yang datanya berubah sesudah diunduh', str_contains($uUlang, '⚠ perlu cetak ulang') && str_contains($uUlang, 'ACC: ' . self::OLEH . ' hubin'));

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

            // --- Halaman Hak Akses: aturan ACC per jenis
            $H   = \App\Controllers\Admin\PklHakAkses::class;
            $hak = (string) $this->panggil($H, 'admin', 'index');
            $this->cek('Hak Akses (Admin): bagian "Surat Sekolah: jenis yang wajib ACC" dengan 4 kotak centang jenis bernomor', str_contains($hak, 'Surat Sekolah: jenis yang wajib ACC') && substr_count($hak, 'name="acc[]"') === 4 && str_contains($hak, 'Pernyataan Orang Tua PKL tidak bernomor'));
            $res = $this->panggil($H, 'hubin', 'simpanAccSurat', [], ['acc' => ['izin_asts']]);
            $this->cek('simpan aturan ACC oleh Hubin → ditolak (khusus Admin); pengaturan tidak berubah', $res instanceof \CodeIgniter\HTTP\RedirectResponse && (string) (new PklPengaturanModel())->ambil()['surat_perlu_acc'] === '');
            $res = $this->panggil($H, 'admin', 'simpanAccSurat', [], ['acc' => ['izin_asts', 'balasan_pkl']]);
            $peta = SuratJenis::petaAcc((new PklPengaturanModel())->ambil());
            $this->cek('simpan aturan ACC oleh Admin → tersimpan (ASTS & Balasan wajib, TKA & Penarikan tidak)', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $peta['izin_asts'] && ! $peta['izin_tka'] && $peta['balasan_pkl'] && ! $peta['penarikan_pkl']);
            $this->atur(['surat_perlu_acc' => null]);
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
            $this->cek('formulir ASTS (Operator): judul, pilihan periode Ujian, semester, tanggal, cakupan umum/per perusahaan, tombol "ajukan ke Waka Hubin"', str_contains($fOp, 'Buat Surat Izin ASTS') && str_contains($fOp, 'Isi otomatis dari menu Ujian') && str_contains($fOp, 'name="semester"') && str_contains($fOp, 'name="tgl_mulai"')
                && str_contains($fOp, 'value="perusahaan"') && str_contains($fOp, 'x-data="suratIzinForm"') && str_contains($fOp, 'Buat surat &amp; ajukan ke Waka Hubin') && str_contains(html_entity_decode($fOp), 'ZZUJISS Acara Satu'));
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

            // simpan per perusahaan (TKA)
            $postP = ['tanggal_surat' => "$y-05-02", 'mode' => 'perusahaan', 'sesi' => 'Gelombang 1', 'tgl_mulai' => "$y-10-05", 'tgl_selesai' => "$y-10-08", 'tempat' => '', 'perusahaan' => [$kunciG]];
            $res   = $this->panggil($I, 'operator', 'simpanTka', [], $postP);
            $rowt  = $this->db->table('surat_sekolah')->where('jenis', 'izin_tka')->where('perusahaan_nama', 'ZZUJISS Acara Satu')->orderBy('id', 'DESC')->get(1)->getRowArray();
            $this->cek('POST simpan TKA per perusahaan → 1 surat untuk perusahaan itu, dialihkan ke detailnya', $res instanceof \CodeIgniter\HTTP\RedirectResponse && $rowt !== null && str_contains($res->getHeaderLine('Location'), '/admin/surat/' . $rowt['id']) && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $rowt['id']]) === 2);
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
        $this->cek('templateLengkap: ASTS & TKA (utama + varian perusahaan) ada; Balasan belum punya template', SuratJenis::templateLengkap(SuratJenis::ASTS) && SuratJenis::templateLengkap(SuratJenis::TKA) && ! SuratJenis::templateLengkap(SuratJenis::BALASAN));
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
    // Pemulihan
    // =================================================================

    private function simpanPengaturanAsli(): void
    {
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
        PklHak::lupakan();
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        SuratJenis::$templateTimpa = [];
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete(); // CASCADE: siswa & riwayat (juga yang dibuat lewat controller: "ZZUJI SS operator")
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

        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        PklHak::lupakan();
    }
}
