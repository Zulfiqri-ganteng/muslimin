<?php

namespace App\Commands;

use App\Libraries\IsianBantu;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\PklPerusahaanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji regresi modul PKL (Tahap 2): aturan form, pengaturan, saran perusahaan,
 * dan — yang terpenting — aturan anti-ganda: transaksi atomik, UNIQUE
 * siswa_aktif, sinkron status ↔ kunci, kirim ulang, bentrok.
 *
 * Disimpan permanen (pola dev:uji-dokumen) supaya tiap kali modul ini disentuh
 * lagi (Tahap 3–5) dapat dicek ulang dalam sekali jalan. Data uji diberi tanda
 * (NIS "ZZUJIPKL…", alamat perusahaan "ZZUJI-PKL"), dibuat dan dihapus sendiri;
 * Pengaturan PKL dipulihkan ke nilai semula di akhir.
 *
 * Jalankan:  php spark dev:uji-pkl
 */
class UjiPkl extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-pkl';
    protected $description = 'Uji aturan form, anti-ganda, dan transaksi modul PKL (Tahap 2).';

    private const NIS = 'ZZUJIPKL';
    private const TANDA_PERUSAHAAN = 'ZZUJI-PKL';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;

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
        $this->bersihkan(); // sisa uji sebelumnya yang terhenti di tengah

        $this->ujiBantu();
        $this->ujiForm();
        $this->ujiPengaturan();
        $this->ujiDatabase();
        $this->ujiSaran();
        $this->ujiStaf();
        $this->ujiSurat();
        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // 1. Pembantu murni
    // =================================================================

    private function ujiBantu(): void
    {
        $this->bagian('Pembantu isian (tanpa DB)');

        $this->cek('telepon +62 → 0', IsianBantu::telepon('+62 812-3456-7890') === '081234567890');
        $this->cek('telepon 62 → 0', IsianBantu::telepon('6281234567890') === '081234567890');
        $this->cek('telepon spasi/strip dibuang', IsianBantu::telepon('0812 3456-7890') === '081234567890');
        $this->cek('hpSah: 081234567890 sah', IsianBantu::hpSah('081234567890'));
        $this->cek('hpSah: telepon rumah ditolak', ! IsianBantu::hpSah('02188776655'));
        $this->cek('hpSah: 0888888888 (angka sama) ditolak', ! IsianBantu::hpSah('0888888888'));
        $this->cek('hpSah: terlalu pendek ditolak', ! IsianBantu::hpSah('0812345'));
        $this->cek('teleponSah: 02188776655 sah', IsianBantu::teleponSah('02188776655'));
        $this->cek('teleponSah: 0000000000 ditolak', ! IsianBantu::teleponSah('0000000000'));
        $this->cek('teleponSah: nomor cantik 087777777777 lolos', IsianBantu::teleponSah('087777777777'));
        $this->cek('teleponSah: tanpa awalan 0 ditolak', ! IsianBantu::teleponSah('2188776655'));
        $this->cek('tanggal: 30 Feb ditolak', IsianBantu::tanggal('2027-02-30', 2000, 2100) === null);
        $this->cek('tanggal: 28 Feb sah', IsianBantu::tanggal('2027-02-28', 2000, 2100) === '2027-02-28');
        $this->cek('tanggal: di luar rentang tahun ditolak', IsianBantu::tanggal('1999-01-01', 2000, 2100) === null);
        $this->cek('tanggal: bukan format ditolak', IsianBantu::tanggal('06/01/2027', 2000, 2100) === null);
        $this->cek('tanggalIndo', IsianBantu::tanggalIndo('2027-01-06') === '6 Januari 2027');
        $this->cek('hariInklusif: 6 Jan s/d 6 Jan = 1', IsianBantu::hariInklusif('2027-01-06', '2027-01-06') === 1);
        $this->cek('hariInklusif: Jan 11 s/d Apr 11 = 91', IsianBantu::hariInklusif('2027-01-11', '2027-04-11') === 91);
        $this->cek('teleponMurni: angka + pemisah lazim lolos', IsianBantu::teleponMurni('+62 (812) 3456-7890') && IsianBantu::teleponMurni('021.8877.6655'));
        $this->cek('teleponMurni: huruf "O" tertukar angka 0 DITOLAK', ! IsianBantu::teleponMurni('0812345678OO') && ! IsianBantu::teleponMurni('0224-52 1234 99x'));
        $this->cek('teleponMurni: "+" hanya boleh di awal', ! IsianBantu::teleponMurni('0812+3456') && IsianBantu::teleponMurni('+628123456789'));
        $this->cek('namaOrangSah: gelar & titik', IsianBantu::namaOrangSah('Hj. Siti Aminah, S.E.'));
        $this->cek('namaOrangSah: angka ditolak', ! IsianBantu::namaOrangSah('Andi123'));
        $this->cek('rapikan: spasi ganda & kendali', IsianBantu::rapikan("  PT   Maju\t\nJaya  ") === 'PT Maju Jaya');

        $this->cek('normPerusahaan: PT. Telkom Indonesia, Tbk', PklForm::normPerusahaan('PT. Telkom Indonesia, Tbk') === 'telkom indonesia');
        $this->cek('normPerusahaan: huruf kecil sama', PklForm::normPerusahaan('telkom indonesia') === 'telkom indonesia');
        $this->cek('normPerusahaan: & → dan', PklForm::normPerusahaan('CV. Maju & Jaya') === 'maju dan jaya');
        $this->cek('normPerusahaan: hanya "PT" tidak kosong', PklForm::normPerusahaan('PT') === 'pt');
        $this->cek('normPerusahaan: spasi ganda', PklForm::normPerusahaan('  Bank   Mandiri ') === 'bank mandiri');
    }

    // =================================================================
    // 2. Aturan form (PklForm::proses)
    // =================================================================

    /** Isian sah, lalu tiap uji mengubah SATU hal. */
    private function postSah(int $y): array
    {
        return [
            'perusahaan_nama'    => 'pt maju jaya sentosa',
            'perusahaan_alamat'  => 'Jl. Raya Industri No. 12, Cikarang',
            'perusahaan_kota'    => 'BEKASI',
            'perusahaan_telepon' => '+62 21 8877 6655',
            'kontak_nama'        => 'bapak andi wijaya',
            'kontak_jabatan'     => 'Manajer HRD',
            'tanggal_mulai'      => "$y-01-11",
            'tanggal_selesai'    => "$y-04-11",
            'hp'                 => '0812-3456-7890',
            'tanggal_lahir'      => ((int) date('Y') - 16) . '-05-17',
            'teman'              => ['5', '7', '7', 'abc', '-3', '0', ''],
            'pernyataan'         => '1',
        ];
    }

    private function pagar(int $y): array
    {
        return [
            'mulai_paling_awal' => "$y-01-04", 'selesai_paling_akhir' => "$y-06-30",
            'durasi_min_hari' => 30, 'durasi_maks_hari' => 270, 'maks_anggota' => 5,
        ];
    }

    private function ujiForm(): void
    {
        $this->bagian('Aturan form (PklForm::proses)');
        $y = (int) date('Y') + 1;
        $p = $this->pagar($y);

        [$d, $e] = PklForm::proses($this->postSah($y), $p);
        $this->cek('isian sah → tanpa galat', $e === [], json_encode($e));
        $this->cek('nama huruf kecil → "PT Maju Jaya Sentosa"', $d['perusahaan_nama'] === 'PT Maju Jaya Sentosa', (string) $d['perusahaan_nama']);
        $this->cek('kota BESAR → "Bekasi"', $d['perusahaan_kota'] === 'Bekasi');
        $this->cek('telepon perusahaan dinormalkan', $d['perusahaan_telepon'] === '02188776655');
        $this->cek('kontak → "Bapak Andi Wijaya"', $d['kontak_nama'] === 'Bapak Andi Wijaya');
        $this->cek('HP dinormalkan', $d['hp'] === '081234567890');
        $this->cek('teman: duplikat & sampah dibuang → [5,7]', $d['teman'] === [5, 7], json_encode($d['teman']));
        $this->cek('perusahaan_norm terisi', $d['perusahaan_norm'] === 'maju jaya sentosa');

        [$d, $e] = PklForm::proses(array_merge($this->postSah($y), ['perusahaan_nama' => 'PT MAJU JAYA', 'teman' => '3,4']), $p);
        $this->cek('nama BESAR dibiarkan', $d['perusahaan_nama'] === 'PT MAJU JAYA');
        $this->cek('teman dari teks "3,4"', $d['teman'] === [3, 4]);

        [$d, $e] = PklForm::proses(array_merge($this->postSah($y), ['perusahaan_nama' => 'cv. abadi sentosa tbk']), $p);
        $this->cek('"cv. abadi sentosa tbk" → CV. Abadi Sentosa Tbk', $d['perusahaan_nama'] === 'CV. Abadi Sentosa Tbk', (string) $d['perusahaan_nama']);

        [$d] = PklForm::proses(array_merge($this->postSah($y), ['perusahaan_telepon' => '', 'kontak_nama' => '', 'kontak_jabatan' => '']), $p);
        $this->cek('kolom opsional kosong → NULL', $d['perusahaan_telepon'] === null && $d['kontak_nama'] === null && $d['kontak_jabatan'] === null);

        // [judul, perubahan, kunci galat yang diharapkan]
        $kasus = [
            ['nama perusahaan kosong', ['perusahaan_nama' => ''], 'perusahaan_nama'],
            ['nama perusahaan 2 huruf', ['perusahaan_nama' => 'ab'], 'perusahaan_nama'],
            ['nama perusahaan hanya angka', ['perusahaan_nama' => '1234567'], 'perusahaan_nama'],
            ['alamat kosong', ['perusahaan_alamat' => ''], 'perusahaan_alamat'],
            ['alamat terlalu singkat', ['perusahaan_alamat' => 'Jl A'], 'perusahaan_alamat'],
            ['kota kosong', ['perusahaan_kota' => ''], 'perusahaan_kota'],
            ['telepon perusahaan terlalu pendek', ['perusahaan_telepon' => '123'], 'perusahaan_telepon'],
            ['telepon perusahaan 0000000000', ['perusahaan_telepon' => '0000000000'], 'perusahaan_telepon'],
            ['kontak mengandung angka', ['kontak_nama' => 'Andi123'], 'kontak_nama'],
            ['tanggal mulai kosong', ['tanggal_mulai' => ''], 'tanggal_mulai'],
            ['tanggal mulai 30 Februari', ['tanggal_mulai' => "$y-02-30"], 'tanggal_mulai'],
            ['tanggal mulai sebelum pagar', ['tanggal_mulai' => "$y-01-03"], 'tanggal_mulai'],
            ['tanggal mulai setelah pagar akhir', ['tanggal_mulai' => "$y-07-15", 'tanggal_selesai' => "$y-08-20"], 'tanggal_mulai'],
            ['tanggal selesai melewati pagar', ['tanggal_selesai' => "$y-07-01"], 'tanggal_selesai'],
            ['tanggal selesai sama dengan mulai', ['tanggal_selesai' => "$y-01-11"], 'tanggal_selesai'],
            ['tanggal selesai sebelum mulai', ['tanggal_selesai' => "$y-01-05"], 'tanggal_selesai'],
            ['tahun salah ketik (jauh)', ['tanggal_selesai' => ($y + 5) . '-04-11'], 'tanggal_selesai'],
            ['lama PKL kurang dari minimal', ['tanggal_selesai' => "$y-01-20"], 'tanggal_selesai'],
            ['HP berisi huruf O (bukan angka 0)', ['hp' => '0812345678OO'], 'hp'],
            ['telepon perusahaan berisi huruf', ['perusahaan_telepon' => '0224-52 1234 99x'], 'perusahaan_telepon'],
            ['HP kosong', ['hp' => ''], 'hp'],
            ['HP telepon rumah', ['hp' => '02188776655'], 'hp'],
            ['HP terlalu pendek', ['hp' => '0812345'], 'hp'],
            ['tanggal lahir kosong', ['tanggal_lahir' => ''], 'tanggal_lahir'],
            ['tanggal lahir terlalu muda', ['tanggal_lahir' => ((int) date('Y') - 3) . '-05-17'], 'tanggal_lahir'],
            ['tanggal lahir terlalu tua', ['tanggal_lahir' => ((int) date('Y') - 60) . '-05-17'], 'tanggal_lahir'],
            ['pernyataan tidak dicentang', ['pernyataan' => ''], 'pernyataan'],
            ['teman melebihi batas (5 teman, maks 4)', ['teman' => [1, 2, 3, 4, 5]], 'teman'],
        ];
        foreach ($kasus as [$judul, $ubah, $kunci]) {
            [, $e] = PklForm::proses(array_merge($this->postSah($y), $ubah), $p);
            $this->cek('galat: ' . $judul, isset($e[$kunci]), json_encode($e));
        }

        // Lama PKL lebih dari maksimal (pagar longgar agar tidak tertutup galat pagar).
        $longgar = ['mulai_paling_awal' => "$y-01-01", 'selesai_paling_akhir' => "$y-12-31", 'durasi_min_hari' => 30, 'durasi_maks_hari' => 60, 'maks_anggota' => 5];
        [, $e] = PklForm::proses($this->postSah($y), $longgar);
        $this->cek('galat: lama PKL melebihi maksimal (91 > 60 hari)', isset($e['tanggal_selesai']) && str_contains($e['tanggal_selesai'], 'maksimal'), json_encode($e));

        // Tepat di batas pagar harus lolos.
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['tanggal_mulai' => "$y-01-04", 'tanggal_selesai' => "$y-06-30"]), $p);
        $this->cek('tepat di batas pagar (4 Jan – 30 Jun) lolos', $e === [], json_encode($e));
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['tanggal_mulai' => "$y-01-04", 'tanggal_selesai' => "$y-02-02"]), $p);
        $this->cek('tepat 30 hari (minimal) lolos', $e === [], json_encode($e));
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['tanggal_mulai' => "$y-01-04", 'tanggal_selesai' => "$y-02-01"]), $p);
        $this->cek('29 hari (kurang 1 dari minimal) ditolak', isset($e['tanggal_selesai']));

        // Satu siswa saja per ajuan.
        [, $e] = PklForm::proses($this->postSah($y), array_merge($p, ['maks_anggota' => 1]));
        $this->cek('maks_anggota=1: teman ditolak', isset($e['teman']) && str_contains($e['teman'], 'satu siswa'), json_encode($e));
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['teman' => []]), array_merge($p, ['maks_anggota' => 1]));
        $this->cek('maks_anggota=1: tanpa teman lolos', $e === [], json_encode($e));

        // Opsi staf.
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['pernyataan' => '']), $p, ['pernyataan' => false]);
        $this->cek('opsi pernyataan=false: tanpa centang lolos', ! isset($e['pernyataan']));
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['tanggal_mulai' => "$y-01-03"]), $p, ['batas' => false]);
        $this->cek('opsi batas=false: di luar pagar lolos', ! isset($e['tanggal_mulai']), json_encode($e));
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['tanggal_mulai' => "$y-02-30"]), $p, ['batas' => false]);
        $this->cek('opsi batas=false: tanggal tak sah TETAP ditolak', isset($e['tanggal_mulai']));

        // Form kosong total: semua kolom wajib terdeteksi, tidak ada exception.
        [, $e] = PklForm::proses([], $p);
        $wajib = ['perusahaan_nama', 'perusahaan_alamat', 'perusahaan_kota', 'tanggal_mulai', 'tanggal_selesai', 'hp', 'tanggal_lahir', 'pernyataan'];
        $this->cek('form kosong: semua kolom wajib bergalat', array_diff($wajib, array_keys($e)) === [], json_encode(array_keys($e)));

        // Panjang berlebih.
        [, $e] = PklForm::proses(array_merge($this->postSah($y), ['perusahaan_nama' => str_repeat('Abc ', 50)]), $p);
        $this->cek('nama perusahaan > 150 huruf ditolak', isset($e['perusahaan_nama']));
    }

    // =================================================================
    // 3. Pengaturan
    // =================================================================

    private function ujiPengaturan(): void
    {
        $this->bagian('Pengaturan PKL (buka/tutup & tingkat)');
        $depan = date('Y-m-d H:i:s', time() + 3600);
        $lalu  = date('Y-m-d H:i:s', time() - 3600);
        $siap  = ['form_buka' => 1, 'form_tutup' => null, 'tingkat' => 'XI', 'mulai_paling_awal' => '2027-01-04', 'selesai_paling_akhir' => '2027-06-30'];

        $this->cek('default (form_buka=0) → belum_dibuka', PklPengaturanModel::alasanTutup(['form_buka' => 0] + $siap) === 'belum_dibuka');
        $this->cek('siap & terbuka → null', PklPengaturanModel::alasanTutup($siap) === null);
        $this->cek('formTerbuka() true saat siap', PklPengaturanModel::formTerbuka($siap));
        $this->cek('batas waktu lewat → sudah_ditutup', PklPengaturanModel::alasanTutup(['form_tutup' => $lalu] + $siap) === 'sudah_ditutup');
        $this->cek('batas waktu masih depan → terbuka', PklPengaturanModel::alasanTutup(['form_tutup' => $depan] + $siap) === null);
        $this->cek('tanpa tingkat → belum_siap', PklPengaturanModel::alasanTutup(['tingkat' => ''] + $siap) === 'belum_siap');
        $this->cek('tingkat sampah → belum_siap', PklPengaturanModel::alasanTutup(['tingkat' => 'Z,Q'] + $siap) === 'belum_siap');
        $this->cek('tanpa tanggal awal → belum_siap', PklPengaturanModel::alasanTutup(['mulai_paling_awal' => null] + $siap) === 'belum_siap');
        $this->cek('tanpa tanggal akhir → belum_siap', PklPengaturanModel::alasanTutup(['selesai_paling_akhir' => ''] + $siap) === 'belum_siap');
        $this->cek('awal > akhir → belum_siap', PklPengaturanModel::alasanTutup(['mulai_paling_awal' => '2027-07-01'] + $siap) === 'belum_siap');
        $this->cek('tingkatBoleh("XII, XI ,Z") → [XI, XII]', PklPengaturanModel::tingkatBoleh(['tingkat' => 'XII, XI ,Z']) === ['XI', 'XII']);

        $baris = (new PklPengaturanModel())->ambil();
        $this->cek('baris pengaturan ada, form default TUTUP di instalasi baru', isset($baris['id']) && (int) $baris['id'] === 1);
    }

    // =================================================================
    // 4. Database: anti-ganda, transaksi, status
    // =================================================================

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>} [data form, konteks] */
    private function dataAjuan(string $nama = 'PT Uji Satu', string $bulanHariMulai = '01-11'): array
    {
        $y = (int) date('Y') + 1;
        [$d, $e] = PklForm::proses([
            'perusahaan_nama' => $nama, 'perusahaan_alamat' => 'Jl. Uji Coba No. 1, Bekasi', 'perusahaan_kota' => 'Bekasi',
            'tanggal_mulai' => "$y-$bulanHariMulai", 'tanggal_selesai' => "$y-04-11", 'hp' => '081234567890',
            'tanggal_lahir' => ((int) date('Y') - 16) . '-05-17', 'pernyataan' => '1',
        ], $this->pagar($y));
        if ($e !== []) {
            throw new \RuntimeException('dataAjuan tidak sah: ' . json_encode($e));
        }

        return [$d, ['oleh' => 'Siswa: Uji', 'ip' => '10.9.9.9', 'sumber' => 'siswa', 'tahun_ajaran' => '2026/2027']];
    }

    private function buatSiswa(int $n, int $kelasId, string $status = 'aktif'): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('siswa')->insert([
            'nis' => self::NIS . $n, 'nama' => 'ZZUJI PKL ' . $n, 'jenis_kelamin' => 'L', 'kelas_id' => $kelasId,
            'status' => $status, 'tanggal_lahir' => '2010-05-17', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** Kunci ADA dan nilainya NULL (null ?? 'x' tidak bisa dipakai untuk ini). */
    private function nul(?array $baris, string $kunci): bool
    {
        return $baris !== null && array_key_exists($kunci, $baris) && $baris[$kunci] === null;
    }

    private function hitung(): array
    {
        return [
            'pengajuan' => $this->db->table('pkl_pengajuan')->countAllResults(),
            'anggota'   => $this->db->table('pkl_anggota')->countAllResults(),
            'riwayat'   => $this->db->table('pkl_riwayat')->countAllResults(),
        ];
    }

    /** Peta siswa_id => siswa_aktif untuk satu ajuan. */
    private function kunci(int $ajuanId): array
    {
        $out = [];
        foreach ($this->db->table('pkl_anggota')->where('pengajuan_id', $ajuanId)->get()->getResultArray() as $r) {
            $out[(int) $r['siswa_id']] = $r['siswa_aktif'] === null ? null : (int) $r['siswa_aktif'];
        }
        ksort($out);

        return $out;
    }

    private function ujiDatabase(): void
    {
        $this->bagian('Database: kirim, anti-ganda, atomik, status');

        // ---- bahan: kelas XI / XII / X dan 8 siswa uji ----
        $kelas = [];
        foreach (['XI', 'XII', 'X'] as $t) {
            $k = $this->db->table('kelas')->select('id')->where('tingkat', $t)->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
            $kelas[$t] = $k ? (int) $k['id'] : 0;
        }
        if (in_array(0, $kelas, true)) {
            $this->cek('bahan uji: kelas X, XI, dan XII tersedia', false, json_encode($kelas));

            return;
        }
        $s = [];
        foreach ([1, 2, 3, 4, 5] as $n) {
            $s[$n] = $this->buatSiswa($n, $kelas['XI']);
        }
        $s[6] = $this->buatSiswa(6, $kelas['XII']);
        $s[7] = $this->buatSiswa(7, $kelas['X']);
        $s[8] = $this->buatSiswa(8, $kelas['XI'], 'pindah');

        // Pengaturan dipakai penjaga lain; simpan aslinya lalu buka form untuk uji.
        $this->pengaturanAsli = (new PklPengaturanModel())->ambil();
        unset($this->pengaturanAsli['id']);

        $ajuan = new PklAjuan();
        $model = new PklPengajuanModel();
        $admin = ['oleh' => 'Staf Uji', 'admin_id' => 1, 'peran' => 'operator', 'ip' => '10.1.1.1'];
        [$data, $konteks] = $this->dataAjuan();
        $anggota1 = [
            ['siswa_id' => $s[1], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju'],
            ['siswa_id' => $s[2], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'],
            ['siswa_id' => $s[3], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'],
        ];

        // ---- 1. Ajuan baru ----
        $r1 = $ajuan->kirimBaru($data, $anggota1, $konteks);
        $this->cek('kirimBaru: berhasil', $r1['ok'] === true, json_encode($r1));
        $a1 = (int) ($r1['id'] ?? 0);
        $row = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray() ?? [];
        $this->cek('ajuan: status menunggu, kirim_ke 1, sumber siswa', ($row['status'] ?? '') === 'menunggu' && (int) ($row['kirim_ke'] ?? 0) === 1 && ($row['sumber'] ?? '') === 'siswa');
        $this->cek('ajuan: tahun ajaran & IP tercatat', ($row['tahun_ajaran'] ?? '') === '2026/2027' && ($row['ip_address'] ?? '') === '10.9.9.9');
        $this->cek('ajuan: nama pembanding terisi', ($row['perusahaan_norm'] ?? '') === 'uji satu');
        $this->cek('kunci siswa_aktif: ketiganya terisi', $this->kunci($a1) === [$s[1] => $s[1], $s[2] => $s[2], $s[3] => $s[3]], json_encode($this->kunci($a1)));
        $ang = $model->anggotaDetail($a1);
        $this->cek('anggota: pengaju di baris pertama', ($ang[0]['siswa_id'] ?? 0) == $s[1] && ($ang[0]['peran'] ?? '') === 'pengaju');
        $this->cek('anggota: HP & tgl lahir hanya milik pengaju', ($ang[0]['hp'] ?? '') === '081234567890' && $this->nul($ang[1] ?? null, 'hp') && $this->nul($ang[1] ?? null, 'tanggal_lahir'));
        $this->cek('anggota: kelas dicatat saat mengajukan (snapshot)', (int) ($ang[1]['kelas_id'] ?? 0) === $kelas['XI']);
        $rw = $this->db->table('pkl_riwayat')->where('pengajuan_id', $a1)->get()->getResultArray();
        $this->cek('riwayat: 1 baris "kirim" oleh siswa', count($rw) === 1 && $rw[0]['aksi'] === 'kirim' && $rw[0]['oleh'] === 'Siswa: Uji');
        $this->cek('PklPengajuanModel::kode()', PklPengajuanModel::kode($a1) === 'PKL-' . str_pad((string) $a1, 5, '0', STR_PAD_LEFT));

        // ---- 2. Pembacaan ----
        $m2 = $model->aktifMilik($s[2]);
        $this->cek('aktifMilik(teman) → ajuan itu, peran teman', ($m2['id'] ?? 0) == $a1 && ($m2['peran'] ?? '') === 'teman');
        $this->cek('aktifMilik(siswa bebas) → null', $model->aktifMilik($s[4]) === null);
        $daftar = [];
        foreach ($model->daftarSiswaKelas($kelas['XI']) as $r) {
            $daftar[(int) $r['id']] = $r;
        }
        $this->cek('daftar nama: pengaju & teman berstatus menunggu', ($daftar[$s[1]]['aktif'] ?? '') === 'menunggu' && ($daftar[$s[2]]['aktif'] ?? '') === 'menunggu' && ($daftar[$s[2]]['peran'] ?? '') === 'teman');
        $this->cek('daftar nama: siswa bebas tanpa status, belum pernah ditolak', $this->nul($daftar[$s[4]] ?? null, 'aktif') && (int) ($daftar[$s[4]]['pernah_ditolak'] ?? 1) === 0);
        $this->cek('daftar nama: siswa non-aktif (pindah) tidak tampil', ! isset($daftar[$s[8]]));
        $pilih = $model->siswaUntukDipilih([$s[2], $s[4], $s[8], 999999999]);
        $this->cek('siswaUntukDipilih: aktif_di terisi untuk yang terkunci', ($pilih[$s[2]]['aktif_di'] ?? 0) == $a1 && $this->nul($pilih[$s[4]] ?? null, 'aktif_di'));
        $this->cek('siswaUntukDipilih: id tak ada tidak muncul, non-aktif muncul berstatus pindah', ! isset($pilih[999999999]) && ($pilih[$s[8]]['status'] ?? '') === 'pindah');

        // ---- 3. Anti-ganda + atomik ----
        $sebelum = $this->hitung();
        $r = $ajuan->kirimBaru($data, [['siswa_id' => $s[2], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju']], $konteks);
        $this->cek('DB: siswa yang sudah aktif ditolak (bentrok)', $r['ok'] === false && ($r['kode'] ?? '') === 'bentrok' && ($r['siswa_ids'] ?? []) === [$s[2]], json_encode($r));
        $this->cek('atomik: tak ada sisa baris setelah bentrok', $this->hitung() === $sebelum, json_encode([$sebelum, $this->hitung()]));

        $r = $ajuan->kirimBaru($data, [
            ['siswa_id' => $s[4], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju'],
            ['siswa_id' => $s[3], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'], // s3 sudah terkunci
        ], $konteks);
        $this->cek('DB: teman yang sudah aktif menolak SELURUH ajuan', $r['ok'] === false && ($r['siswa_ids'] ?? []) === [$s[3]], json_encode($r));
        $this->cek('atomik: pengaju yang baru tersimpan ikut dibatalkan', $this->hitung() === $sebelum && $model->aktifMilik($s[4]) === null);

        $r = $ajuan->kirimBaru($data, [
            ['siswa_id' => $s[4], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju'],
            ['siswa_id' => $s[4], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'],
        ], $konteks);
        $this->cek('siswa yang sama dua kali dalam satu ajuan ditolak & dibatalkan', $r['ok'] === false && $this->hitung() === $sebelum, json_encode($r));

        // ---- 4. Perbaikan: kembalikan → kirim ulang ----
        $r = $ajuan->ubahStatus($a1, 'perbaikan', $admin, 'Alamat kurang lengkap');
        $this->cek('ubahStatus → perbaikan', $r['ok'] === true && ($r['status_lama'] ?? '') === 'menunggu', json_encode($r));
        $row = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $this->cek('perbaikan: catatan staf & penetap tersimpan', $row['catatan_staf'] === 'Alamat kurang lengkap' && (int) $row['diputuskan_oleh'] === 1 && $row['diputuskan_at'] !== null);
        $this->cek('perbaikan: siswa TETAP terkunci (ajuan masih aktif)', $this->kunci($a1) === [$s[1] => $s[1], $s[2] => $s[2], $s[3] => $s[3]]);

        // Ubah: s3 dikeluarkan, s4 masuk; data perusahaan diganti.
        [$data2] = $this->dataAjuan('PT Uji Dua Baru', '02-01');
        $anggota2 = [
            ['siswa_id' => $s[1], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju'],
            ['siswa_id' => $s[2], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'],
            ['siswa_id' => $s[4], 'kelas_id' => $kelas['XI'], 'peran' => 'teman'],
        ];

        $sebelum = $this->hitung();
        $r = $ajuan->kirimUlang($a1, $data2, [['siswa_id' => $s[2], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju']], $konteks);
        $this->cek('kirimUlang oleh BUKAN pengaju asli ditolak', $r['ok'] === false && ($r['kode'] ?? '') === 'pengaju_beda', json_encode($r));

        $r = $ajuan->kirimUlang($a1, $data2, $anggota2, $konteks);
        $this->cek('kirimUlang: berhasil', $r['ok'] === true, json_encode($r));
        $row = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $this->cek('kirimUlang: status menunggu, kirim_ke 2', $row['status'] === 'menunggu' && (int) $row['kirim_ke'] === 2);
        $this->cek('kirimUlang: catatan staf & penetap dikosongkan', $row['catatan_staf'] === null && $row['diputuskan_at'] === null && $row['diputuskan_oleh'] === null);
        $this->cek('kirimUlang: data perusahaan & tanggal diganti', $row['perusahaan_nama'] === 'PT Uji Dua Baru' && $row['perusahaan_norm'] === 'uji dua baru' && $row['tanggal_mulai'] === ((int) date('Y') + 1) . '-02-01');
        $this->cek('kirimUlang: s3 dikeluarkan, s4 masuk & terkunci', $this->kunci($a1) === [$s[1] => $s[1], $s[2] => $s[2], $s[4] => $s[4]], json_encode($this->kunci($a1)));
        $this->cek('kirimUlang: s3 BEBAS lagi (boleh mengajukan sendiri)', $model->aktifMilik($s[3]) === null);
        $this->cek('kirimUlang: pengaju tetap satu orang', $this->db->table('pkl_anggota')->where('pengajuan_id', $a1)->where('peran', 'pengaju')->countAllResults() === 1);
        $aksi = array_column($this->db->table('pkl_riwayat')->where('pengajuan_id', $a1)->orderBy('id')->get()->getResultArray(), 'aksi');
        $this->cek('riwayat berurutan: kirim, kembalikan, kirim_ulang', $aksi === ['kirim', 'kembalikan', 'kirim_ulang'], json_encode($aksi));

        $sebelum = $this->hitung();
        $r = $ajuan->kirimUlang($a1, $data2, $anggota2, $konteks);
        $this->cek('kirimUlang saat status bukan perbaikan ditolak', $r['ok'] === false && ($r['kode'] ?? '') === 'status' && ($r['status'] ?? '') === 'menunggu', json_encode($r));
        $this->cek('… dan tak mengubah apa pun', $this->hitung() === $sebelum);

        // ---- 5. Kirim ulang yang bentrok harus membatalkan SEMUANYA ----
        $r5 = $ajuan->kirimBaru($data, [['siswa_id' => $s[5], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju']], $konteks);
        $a5 = (int) ($r5['id'] ?? 0);
        $ajuan->ubahStatus($a1, 'perbaikan', $admin, 'Coba lagi');
        $sebelum    = $this->hitung();
        $kunciAwal  = $this->kunci($a1);
        $barisAwal  = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $anggotaBtr = array_merge($anggota2, [['siswa_id' => $s[5], 'kelas_id' => $kelas['XI'], 'peran' => 'teman']]);
        [$data3] = $this->dataAjuan('PT Tidak Boleh Tersimpan');
        $r = $ajuan->kirimUlang($a1, $data3, $anggotaBtr, $konteks);
        $this->cek('kirimUlang menambah teman yang aktif di ajuan lain → bentrok', $r['ok'] === false && ($r['siswa_ids'] ?? []) === [$s[5]], json_encode($r));
        $barisSesudah = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $this->cek('atomik: ajuan, anggota, riwayat tak berubah sedikit pun', $this->hitung() === $sebelum && $this->kunci($a1) === $kunciAwal && $barisSesudah === $barisAwal);

        // ---- 6. ACC / tolak / batal ----
        $r = $ajuan->ubahStatus($a1, 'disetujui', $admin, null);
        $this->cek('ubahStatus → disetujui', $r['ok'] === true && $this->kunci($a1) === [$s[1] => $s[1], $s[2] => $s[2], $s[4] => $s[4]]);
        $daftar = [];
        foreach ($model->daftarSiswaKelas($kelas['XI']) as $rr) {
            $daftar[(int) $rr['id']] = $rr;
        }
        $this->cek('daftar nama: status disetujui tampil', ($daftar[$s[1]]['aktif'] ?? '') === 'disetujui');

        $r = $ajuan->ubahStatus($a1, 'ditolak', $admin, 'Perusahaan tidak menerima');
        $this->cek('ubahStatus → ditolak', $r['ok'] === true);
        $this->cek('ditolak: kunci semua anggota DILEPAS', $this->kunci($a1) === [$s[1] => null, $s[2] => null, $s[4] => null], json_encode($this->kunci($a1)));
        $this->cek('ditolak: siswa boleh mengajukan lagi (aktifMilik null)', $model->aktifMilik($s[1]) === null);
        $daftar = [];
        foreach ($model->daftarSiswaKelas($kelas['XI']) as $rr) {
            $daftar[(int) $rr['id']] = $rr;
        }
        $this->cek('daftar nama: ditolak → tanpa status aktif, pernah_ditolak=1', $this->nul($daftar[$s[1]] ?? null, 'aktif') && (int) ($daftar[$s[1]]['pernah_ditolak'] ?? 0) === 1);

        // s1 mengajukan lagi (ajuan baru) — dan ajuan lama (ditolak) tak mengganggu.
        $r3 = $ajuan->kirimBaru($data, [['siswa_id' => $s[1], 'kelas_id' => $kelas['XI'], 'peran' => 'pengaju']], $konteks);
        $a3 = (int) ($r3['id'] ?? 0);
        $this->cek('s1 mengajukan ulang setelah ditolak → berhasil', $r3['ok'] === true);

        // Menghidupkan ajuan lama padahal s1 sudah aktif di ajuan baru → harus ditolak.
        $sebelum = $this->hitung();
        $r = $ajuan->ubahStatus($a1, 'menunggu', $admin, null);
        $this->cek('menghidupkan ajuan lama saat anggotanya aktif di tempat lain → bentrok', $r['ok'] === false && ($r['kode'] ?? '') === 'bentrok' && ($r['siswa_ids'] ?? []) === [$s[1]], json_encode($r));
        $row = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $this->cek('atomik: ajuan lama tetap ditolak & kunci tetap lepas', $row['status'] === 'ditolak' && $this->kunci($a1) === [$s[1] => null, $s[2] => null, $s[4] => null] && $this->hitung() === $sebelum);

        $ajuan->ubahStatus($a3, 'ditolak', $admin, 'Uji');
        $r = $ajuan->ubahStatus($a1, 'menunggu', $admin, null);
        $this->cek('setelah ajuan baru ditolak, ajuan lama bisa dihidupkan lagi', $r['ok'] === true && $this->kunci($a1) === [$s[1] => $s[1], $s[2] => $s[2], $s[4] => $s[4]]);
        $this->cek('menunggu: penetap dikosongkan', $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray()['diputuskan_at'] === null);

        // ---- 7. Masukan tak sah ----
        $r = $ajuan->ubahStatus($a1, 'ngawur', $admin);
        $this->cek('ubahStatus dengan status tak dikenal ditolak', $r['ok'] === false);
        $r = $ajuan->ubahStatus(999999999, 'disetujui', $admin);
        $this->cek('ubahStatus untuk id tak ada → tidak_ada', $r['ok'] === false && ($r['kode'] ?? '') === 'tidak_ada');
        $r = $ajuan->kirimUlang(999999999, $data, $anggota1, $konteks);
        $this->cek('kirimUlang untuk id tak ada → tidak_ada', $r['ok'] === false && ($r['kode'] ?? '') === 'tidak_ada');

        // ---- 8. Konsistensi & kunci asing ----
        $salah = array_filter($ajuan->periksaKonsistensi(), fn ($x) => in_array((int) $x['siswa_id'], $s, true));
        $this->cek('periksaKonsistensi(): kunci selalu sesuai status (data uji)', $salah === [], json_encode(array_values($salah)));

        // Hapus siswa uji s6 sambil punya ajuan → CASCADE bersih tanpa error.
        $r6 = $ajuan->kirimBaru($data, [['siswa_id' => $s[6], 'kelas_id' => $kelas['XII'], 'peran' => 'pengaju']], $konteks);
        $this->db->table('siswa')->where('id', $s[6])->delete();
        $this->cek('hapus siswa → baris anggotanya ikut terhapus (CASCADE)', $this->db->table('pkl_anggota')->where('siswa_id', $s[6])->countAllResults() === 0 && $r6['ok'] === true);

        // Hapus ajuan → anggota & riwayat ikut terhapus; siswa bebas lagi.
        $this->db->table('pkl_pengajuan')->where('id', $a5)->delete();
        $this->cek('hapus ajuan → anggota & riwayat ikut terhapus', $this->db->table('pkl_anggota')->where('pengajuan_id', $a5)->countAllResults() === 0 && $this->db->table('pkl_riwayat')->where('pengajuan_id', $a5)->countAllResults() === 0);
        $this->cek('… dan siswanya bebas lagi', $model->aktifMilik($s[5]) === null);

        // Riwayat mencatat pelaku staf lengkap.
        $rw = $this->db->table('pkl_riwayat')->where('pengajuan_id', $a1)->where('aksi', 'tolak')->get()->getRowArray();
        $this->cek('riwayat staf: pelaku, peran, catatan tercatat', ($rw['oleh'] ?? '') === 'Staf Uji' && ($rw['peran'] ?? '') === 'operator' && ($rw['catatan'] ?? '') === 'Perusahaan tidak menerima', json_encode($rw));

        // Penjaga banjir kiriman per IP.
        $this->cek('kirimanDariIp: hitung ajuan IP uji 10 menit terakhir (≥ 3)', $model->kirimanDariIp('10.9.9.9', 600) >= 3);
        $this->cek('kirimanDariIp: IP lain = 0', $model->kirimanDariIp('10.250.250.250', 600) === 0);
    }

    // =================================================================
    // 5. Saran perusahaan
    // =================================================================

    private function ujiSaran(): void
    {
        $this->bagian('Saran nama perusahaan');
        $now = date('Y-m-d H:i:s');
        foreach (['PT. Telkom Indonesia, Tbk', 'CV Mitra Telkom Karya', 'Bank Mandiri KCP Cikarang'] as $nama) {
            $this->db->table('pkl_perusahaan')->insert([
                'nama' => $nama, 'nama_norm' => PklForm::normPerusahaan($nama), 'alamat' => self::TANDA_PERUSAHAAN,
                'kota' => 'Bekasi', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        for ($i = 1; $i <= 9; $i++) {
            $nama = 'Alfa Uji ' . $i;
            $this->db->table('pkl_perusahaan')->insert([
                'nama' => $nama, 'nama_norm' => PklForm::normPerusahaan($nama), 'alamat' => self::TANDA_PERUSAHAAN, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $model = new PklPerusahaanModel();
        $hanyaUji = static fn (array $rows) => array_values(array_filter($rows, static fn ($r) => $r['alamat'] === self::TANDA_PERUSAHAAN));

        $r = $hanyaUji($model->saran('telkom'));
        $nama = array_column($r, 'nama');
        $this->cek('"telkom" menemukan keduanya', in_array('PT. Telkom Indonesia, Tbk', $nama, true) && in_array('CV Mitra Telkom Karya', $nama, true), json_encode($nama));
        $this->cek('yang namanya DIAWALI "telkom" didahulukan', ($nama[0] ?? '') === 'PT. Telkom Indonesia, Tbk', json_encode($nama));
        $this->cek('"PT Telkom" (dengan badan usaha) sama hasilnya', array_column($hanyaUji($model->saran('PT Telkom')), 'nama') === $nama);
        $this->cek('huruf besar/kecil tak berpengaruh', array_column($hanyaUji($model->saran('TELKOM INDONESIA')), 'nama') === ['PT. Telkom Indonesia, Tbk']);
        $this->cek('1 huruf → kosong', $model->saran('t') === []);
        $this->cek('kosong → kosong', $model->saran('') === []);
        $this->cek('tak ada yang cocok → kosong', $hanyaUji($model->saran('zzzqqq')) === []);
        $this->cek('batas hasil dihormati (maks 6)', count($model->saran('alfa uji')) === 6);
        $this->cek('batas hasil bisa diatur (3)', count($model->saran('alfa uji', 3)) === 3);

        $aman = true;
        try {
            foreach (["'; DROP TABLE pkl_perusahaan; --", '%', '_', '\\', "alfa' OR '1'='1"] as $jahat) {
                $model->saran($jahat);
            }
            $aman = $this->db->tableExists('pkl_perusahaan');
        } catch (\Throwable) {
            $aman = false;
        }
        $this->cek('masukan jahat (kutip, %, _, \\) tidak merusak & tidak error', $aman);
        $this->cek('"%" tidak dianggap wildcard (hasil kosong)', $hanyaUji($model->saran('%%')) === []);
    }

    // =================================================================
    // 6. Tahap 3: sisi staf (ACC → master, ubah langsung, status siswa, peringatan, hak akses)
    // =================================================================

    /** Data ajuan langsung (tanpa PklForm) supaya tanggal bebas — termasuk yang sudah lewat/akan datang. */
    private function dataLangsung(string $nama, string $mulai, string $selesai, array $ubah = []): array
    {
        return $ubah + [
            'perusahaan_nama' => $nama, 'perusahaan_norm' => PklForm::normPerusahaan($nama),
            'perusahaan_alamat' => 'Jl. Uji Staf No. 9, Bekasi', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            'hp' => '081234567890', 'tanggal_lahir' => '2010-05-17', 'teman' => [],
        ];
    }

    private function ujiStaf(): void
    {
        $this->bagian('Tahap 3: staf — ACC ke master, ubah langsung, status siswa, peringatan, hak akses');

        $k = $this->db->table('kelas')->select('id')->where('tingkat', 'XI')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('bahan uji: kelas XI tersedia', false);

            return;
        }
        $kelasXI = (int) $k['id'];
        $s = [];
        foreach (range(20, 31) as $n) {
            $s[$n] = $this->buatSiswa($n, $kelasXI);
        }
        $s[32] = $this->buatSiswa(32, $kelasXI, 'pindah');

        $ajuan = new PklAjuan();
        $model = new PklPengajuanModel();
        $admin = ['oleh' => 'Operator Uji', 'admin_id' => 1, 'peran' => 'operator', 'ip' => '10.9.9.9'];
        $ctx   = ['oleh' => 'Siswa: Uji', 'ip' => '10.9.9.9', 'sumber' => 'siswa', 'tahun_ajaran' => '2026/2027'];
        $pj    = static fn (int $id): array => ['siswa_id' => $id, 'kelas_id' => $kelasXI, 'peran' => 'pengaju'];
        $tm    = static fn (int $id): array => ['siswa_id' => $id, 'kelas_id' => $kelasXI, 'peran' => 'teman'];
        $hariIni = date('Y-m-d');
        $tgl     = static fn (int $selisih): string => date('Y-m-d', strtotime(($selisih >= 0 ? '+' : '') . $selisih . ' days'));
        $masterJml = fn (string $norm): int => $this->db->table('pkl_perusahaan')->where('nama_norm', $norm)->countAllResults();

        // ---------- 6a. ACC menautkan perusahaan ke master ----------
        $a1 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Alfa', $tgl(10), $tgl(100)), [$pj($s[20])], $ctx)['id'];
        $this->cek('ACC: master belum ada sebelum ACC', $masterJml('zzuji alfa') === 0);
        $r = $ajuan->ubahStatus($a1, 'disetujui', $admin);
        $row = $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray();
        $m1  = (int) ($row['perusahaan_id'] ?? 0);
        $this->cek('ACC: berhasil + perusahaan didaftarkan ke master (1 baris) & ditautkan', $r['ok'] === true && $m1 > 0 && $masterJml('zzuji alfa') === 1, json_encode($r));

        $a2 = (int) $ajuan->kirimBaru($this->dataLangsung('pt. zzuji alfa', $tgl(10), $tgl(100), ['perusahaan_telepon' => '02188776655', 'kontak_nama' => 'Bapak Uji']), [$pj($s[21])], $ctx)['id'];
        $ajuan->ubahStatus($a2, 'disetujui', $admin);
        $m2 = (int) $this->db->table('pkl_pengajuan')->where('id', $a2)->get()->getRowArray()['perusahaan_id'];
        $this->cek('ACC: nama sama (beda tulisan) → ditautkan ke master YANG SAMA, tanpa data ganda', $m2 === $m1 && $masterJml('zzuji alfa') === 1);
        $master = $this->db->table('pkl_perusahaan')->where('id', $m1)->get()->getRowArray();
        $this->cek('ACC: kolom master yang kosong dilengkapi dari ajuan berikutnya (telepon & kontak)', $master['telepon'] === '02188776655' && $master['kontak_nama'] === 'Bapak Uji');

        $a3 = (int) $ajuan->kirimBaru($this->dataLangsung('ZZUJI Alfa Teknik', $tgl(10), $tgl(100)), [$pj($s[22])], $ctx)['id'];
        $ajuan->ubahStatus($a3, 'disetujui', $admin, null, ['perusahaan_id' => $m1]);
        $this->cek('ACC dengan pilihan staf "sama dengan master X" → tertaut ke X, tak membuat master baru', (int) $this->db->table('pkl_pengajuan')->where('id', $a3)->get()->getRowArray()['perusahaan_id'] === $m1 && $masterJml('zzuji alfa teknik') === 0);
        $a3b = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Bukan Master', $tgl(10), $tgl(100)), [$pj($s[23])], $ctx)['id'];
        $r = $ajuan->ubahStatus($a3b, 'disetujui', $admin, null, ['perusahaan_id' => 999999999]);
        $this->cek('ACC dengan master pilihan yang tak ada → ditolak, status tak berubah', $r['ok'] === false && $this->db->table('pkl_pengajuan')->where('id', $a3b)->get()->getRowArray()['status'] === 'menunggu');
        $ajuan->ubahStatus($a3b, 'ditolak', $admin, 'bersihkan uji');

        // ---------- 6b. isi atas nama langsung disetujui ----------
        $r = $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Data Lama', $tgl(-300), $tgl(-200)), [$pj($s[24]), $tm($s[25])], $admin + ['sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui']);
        $a4 = (int) ($r['id'] ?? 0);
        $row = $this->db->table('pkl_pengajuan')->where('id', $a4)->get()->getRowArray() ?? [];
        $this->cek('isi atas nama langsung disetujui: status, sumber staf, penetap & master', ($row['status'] ?? '') === 'disetujui' && ($row['sumber'] ?? '') === 'staf' && (int) ($row['diputuskan_oleh'] ?? 0) === 1 && (int) ($row['perusahaan_id'] ?? 0) > 0, json_encode($r));
        $this->cek('… kedua siswanya terkunci', $this->kunci($a4) === [$s[24] => $s[24], $s[25] => $s[25]]);
        $this->cek('… riwayat tercatat sebagai isi_atas_nama oleh staf', $this->db->table('pkl_riwayat')->where('pengajuan_id', $a4)->get()->getRowArray()['aksi'] === 'isi_atas_nama');

        // ---------- 6c. ubah langsung ----------
        $a5 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Ubah', $tgl(10), $tgl(100)), [$pj($s[26]), $tm($s[27])], $ctx)['id'];
        $baru = $this->dataLangsung('PT ZZUJI Ubah Baru', $tgl(20), $tgl(120), ['hp' => '085211112222']);
        $sebelum = $this->db->table('pkl_pengajuan')->where('id', $a5)->get()->getRowArray();
        $r = $ajuan->ubahIsi($a5, $baru, [$pj($s[26]), $tm($s[28])], $admin + ['aksi' => 'ubah', 'catatan' => 'uji']);
        $row = $this->db->table('pkl_pengajuan')->where('id', $a5)->get()->getRowArray();
        $this->cek('ubahIsi: data & tanggal berubah, STATUS tetap menunggu', $r['ok'] === true && $row['perusahaan_nama'] === 'PT ZZUJI Ubah Baru' && $row['status'] === 'menunggu' && $row['tanggal_mulai'] === $tgl(20) && (int) $row['kirim_ke'] === (int) $sebelum['kirim_ke']);
        $this->cek('ubahIsi: teman diganti (27 keluar, 28 masuk & terkunci), 27 bebas', $this->kunci($a5) === [$s[26] => $s[26], $s[28] => $s[28]] && $model->aktifMilik($s[27]) === null);
        $this->cek('ubahIsi: HP pengaju diperbarui, riwayat "ubah" bercatatan', $this->db->table('pkl_anggota')->where('pengajuan_id', $a5)->where('peran', 'pengaju')->get()->getRowArray()['hp'] === '085211112222' && $this->db->table('pkl_riwayat')->where('pengajuan_id', $a5)->where('aksi', 'ubah')->get()->getRowArray()['catatan'] === 'uji');
        $r = $ajuan->ubahIsi($a5, $baru, [$pj($s[28])], $admin);
        $this->cek('ubahIsi: mengganti PENGAJU ditolak', $r['ok'] === false && ($r['kode'] ?? '') === 'pengaju_beda');
        $sebelumHitung = $this->hitung();
        $r = $ajuan->ubahIsi($a5, $baru, [$pj($s[26]), $tm($s[20])], $admin); // s20 sudah aktif (disetujui, a1)
        $this->cek('ubahIsi: menambah siswa yang terkunci di ajuan lain → bentrok & TIDAK menyimpan apa pun', $r['ok'] === false && ($r['siswa_ids'] ?? []) === [$s[20]] && $this->hitung() === $sebelumHitung && $this->kunci($a5) === [$s[26] => $s[26], $s[28] => $s[28]]);

        // ubah ajuan DITOLAK: anggota baru tak boleh terkunci
        $ajuan->ubahStatus($a5, 'ditolak', $admin, 'uji ditolak');
        $r = $ajuan->ubahIsi($a5, $baru, [$pj($s[26]), $tm($s[28]), $tm($s[29])], $admin);
        $this->cek('ubahIsi pada ajuan ditolak: anggota baru TIDAK terkunci (siswa_aktif NULL)', $r['ok'] === true && $this->kunci($a5) === [$s[26] => null, $s[28] => null, $s[29] => null] && $model->aktifMilik($s[29]) === null);
        $this->cek('… konsistensi tetap terjaga', array_filter($ajuan->periksaKonsistensi(), fn ($x) => in_array((int) $x['siswa_id'], $s, true)) === []);

        // ubah ajuan DISETUJUI: tautan master mengikuti nama baru
        $ajuan->ubahIsi($a1, $this->dataLangsung('PT ZZUJI Alfa Pindah', $tgl(10), $tgl(100)), [$pj($s[20])], $admin);
        $this->cek('ubahIsi pada ajuan disetujui + nama baru → tertaut ke master baru', $masterJml('zzuji alfa pindah') === 1 && (int) $this->db->table('pkl_pengajuan')->where('id', $a1)->get()->getRowArray()['perusahaan_id'] !== $m1);

        // ---------- 6d. status turunan siswa ----------
        $r0 = $model->ringkasanSiswa(['XI']);
        $tingkatBahan = ['XI'];
        // s30 menunggu, s31 perbaikan, s24 (a4): selesai? a4 tanggal lampau → selesai
        $a6 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Fase A', $tgl(10), $tgl(100)), [$pj($s[30])], $ctx)['id'];
        $a7 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Fase B', $tgl(10), $tgl(100)), [$pj($s[31])], $ctx)['id'];
        $ajuan->ubahStatus($a7, 'perbaikan', $admin, 'uji perbaikan');
        $a8 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Fase C', $tgl(-5), $tgl(60)), [$pj($s[23])], $ctx + ['status_awal' => 'disetujui'])['id']; // sedang
        $faseDari = static function (array $rows): array {
            $o = [];
            foreach ($rows as $r) {
                $o[$r['nama']] = $r['fase'];
            }

            return $o;
        };
        [$rows] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', '', 100, 1);
        $f = $faseDari($rows);
        $this->cek('fase: menunggu / perbaikan', ($f['ZZUJI PKL 30'] ?? '') === 'menunggu' && ($f['ZZUJI PKL 31'] ?? '') === 'perbaikan', json_encode($f));
        $this->cek('fase: disetujui belum mulai (a1 mulai +10 hari) & sedang (mulai -5 hari)', ($f['ZZUJI PKL 20'] ?? '') === 'belum_mulai' && ($f['ZZUJI PKL 23'] ?? '') === 'sedang');
        $this->cek('fase: disetujui tanggal lampau = selesai (teman di a4 ikut)', ($f['ZZUJI PKL 24'] ?? '') === 'selesai' && ($f['ZZUJI PKL 25'] ?? '') === 'selesai');
        $this->cek('fase: ditolak (s26/28/29 di a5 ditolak) & belum (s27 bebas)', ($f['ZZUJI PKL 26'] ?? '') === 'ditolak' && ($f['ZZUJI PKL 27'] ?? '') === 'belum');
        $this->cek('fase: siswa pindah tidak dihitung', ! isset($f['ZZUJI PKL 32']));

        $r1 = $model->ringkasanSiswa(['XI']);
        $this->cek('ringkasan: total TIDAK berubah (hanya status bergeser)', $r1['total'] === $r0['total']);
        $this->cek('ringkasan: sudah_isi + belum_isi = total, sudah_pkl + belum_pkl = total', $r1['sudah_isi'] + $r1['belum_isi'] === $r1['total'] && $r1['sudah_pkl'] + $r1['belum_pkl'] === $r1['total']);
        $this->cek('ringkasan: sudah_pkl = belum_mulai + sedang + selesai + disetujui', $r1['sudah_pkl'] === $r1['belum_mulai'] + $r1['sedang'] + $r1['selesai'] + $r1['disetujui']);
        $this->cek('ringkasan: menunggu +1 dan perbaikan +1 dibanding sebelum', $r1['menunggu'] - $r0['menunggu'] === 1 && $r1['perbaikan'] - $r0['perbaikan'] === 1, json_encode([$r0['menunggu'], $r1['menunggu']]));
        [, $nSudah] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', 'sudah_mengisi', 100, 1);
        [, $nBelum] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', 'belum_mengisi', 100, 1);
        [, $nPkl]   = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', 'sudah_pkl', 100, 1);
        [, $nSemua] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', '', 100, 1);
        $this->cek('saringan sudah_mengisi + belum_mengisi = semua; sudah_pkl ⊂ sudah_mengisi', $nSudah + $nBelum === $nSemua && $nPkl <= $nSudah, json_encode([$nSudah, $nBelum, $nSemua, $nPkl]));
        [, $nSedang] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', 'sedang', 100, 1);
        $this->cek('saringan satu fase (sedang) cocok jumlahnya', $nSedang === 1);
        [$hal1, $tot] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', '', 5, 1);
        [$hal2] = $model->statusSiswa($tingkatBahan, 0, 'ZZUJI PKL', '', 5, 2);
        $this->cek('paginasi: halaman 1 = 5 baris, halaman 2 tak tumpang tindih, total konsisten', count($hal1) === 5 && $tot === $nSemua && array_intersect(array_column($hal1, 'id'), array_column($hal2, 'id')) === []);
        [$rowsKosong, $totKosong] = $model->statusSiswa([], 0, '', '', 20, 1);
        $this->cek('tanpa tingkat → kosong, tak error', $rowsKosong === [] && $totKosong === 0);
        [, $totAneh] = $model->statusSiswa($tingkatBahan, 0, "'; DROP TABLE siswa; -- %_", '', 20, 1);
        $this->cek('pencarian jahat tak merusak', $totAneh === 0 && $this->db->tableExists('siswa'));

        // ---------- 6e. kotak masuk (daftar) ----------
        [$rowsD, $totD] = $model->daftar('menunggu', 'ZZUJI Fase A', 0, 20, 1);
        $this->cek('daftar: cari nama perusahaan', $totD === 1 && (int) $rowsD[0]['id'] === $a6 && (int) $rowsD[0]['jumlah'] === 1);
        [, $totN] = $model->daftar('menunggu', 'ZZUJI PKL 30', 0, 20, 1);
        $this->cek('daftar: cari nama SISWA (termasuk teman) menemukan ajuannya', $totN === 1);
        [, $totK] = $model->daftar('menunggu', 'ZZUJI Fase A', $kelasXI, 20, 1);
        [, $totK2] = $model->daftar('menunggu', 'ZZUJI Fase A', 999999999, 20, 1);
        $this->cek('daftar: saringan kelas (ada / tak ada)', $totK === 1 && $totK2 === 0);
        [, $totJahat] = $model->daftar('menunggu', "' OR 1=1 -- %", 0, 20, 1);
        $this->cek('daftar: pencarian jahat tak membocorkan baris', $totJahat === 0);
        $h = $model->hitungStatus();
        $this->cek('hitungStatus: jumlah per status & total konsisten', $h['total'] === $h['menunggu'] + $h['perbaikan'] + $h['disetujui'] + $h['ditolak']);
        $det = $model->detail($a1);
        $this->cek('detail: memuat nama master yang tertaut', ($det['master_nama'] ?? '') === 'PT ZZUJI Alfa Pindah');

        // ---------- 6f. peringatan otomatis ----------
        $pg = $this->pagar((int) date('Y') + 1);
        $w  = static fn (array $ajuanRow, array $anggota, array $p) => \App\Libraries\PklPeringatan::untuk($ajuanRow, $anggota, $p);
        $ada = static fn (array $daftar, string $tingkat, string $potong): bool => (bool) array_filter($daftar, static fn ($x) => $x['tingkat'] === $tingkat && str_contains($x['teks'], $potong));

        $dw = $w($model->detail($a6), $model->anggotaDetail($a6), $pg);
        $this->cek('peringatan: tanggal di luar pagar sekolah → awas', $ada($dw, 'awas', 'Tanggal di luar aturan'), json_encode($dw));
        $this->cek('peringatan: telepon perusahaan kosong → info', $ada($dw, 'info', 'Telepon perusahaan kosong'));

        $this->db->table('pkl_anggota')->where('pengajuan_id', $a6)->update(['hp' => null, 'tanggal_lahir' => '2011-01-01']);
        $dw = $w($model->detail($a6), $model->anggotaDetail($a6), $pg);
        $this->cek('peringatan: HP pengaju kosong → awas', $ada($dw, 'awas', 'No. HP pengaju'));
        $this->cek('peringatan: tanggal lahir TIDAK sama dengan Master Siswa → awas', $ada($dw, 'awas', 'TIDAK sama dengan Master Siswa'));

        $a9 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Alfa Pind', $tgl(10), $tgl(100)), [$pj($s[29])], $ctx)['id'];
        $dw = $w($model->detail($a9), $model->anggotaDetail($a9), $pg);
        $this->cek('peringatan: nama mirip master (alfa pind ≈ alfa pindah) → awas', $ada($dw, 'awas', 'mirip dengan perusahaan terdaftar'), json_encode($dw));
        $a10 = (int) $ajuan->kirimBaru($this->dataLangsung('PT. ZZUJI Alfa Pindah', $tgl(10), $tgl(100)), [$pj($s[27])], $ctx)['id'];
        $dw = $w($model->detail($a10), $model->anggotaDetail($a10), $pg);
        $this->cek('peringatan: nama sama persis dengan master → info (akan ditautkan)', $ada($dw, 'info', 'sudah terdaftar di master'));
        $this->cek('peringatan: ajuan lain dengan perusahaan sama → info', $ada($dw, 'info', 'juga diajukan di'));

        $this->db->table('siswa')->where('id', $s[27])->update(['status' => 'pindah']);
        $dw = $w($model->detail($a10), $model->anggotaDetail($a10), $pg);
        $this->cek('peringatan: anggota yang sudah bukan siswa aktif → BAHAYA & terurut paling atas', $ada($dw, 'bahaya', 'tidak berstatus siswa aktif') && $dw[0]['tingkat'] === 'bahaya');
        $this->cek('kandidatMaster: persis & mirip terdeteksi', \App\Libraries\PklPeringatan::kandidatMaster('zzuji alfa pindah')['persis'] !== null && count(\App\Libraries\PklPeringatan::kandidatMaster('zzuji alfa pind')['mirip']) >= 1);

        // ---------- 6g. hak akses ----------
        $this->cek('hak akses: Hubin boleh admin/pkl & turunannya', \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl') && \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/12') && \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/12/acc') && \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/baru'));
        $this->cek('hak akses: Hubin DITOLAK di pengaturan & hapus (termasuk tipuan huruf/garis)', ! \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/pengaturan') && ! \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/hapus/3') && ! \App\Libraries\HakAkses::boleh('hubin', 'ADMIN/PKL/Pengaturan') && ! \App\Libraries\HakAkses::boleh('hubin', 'admin//pkl/pengaturan/') && ! \App\Libraries\HakAkses::boleh('hubin', 'admin/pkl/pengaturan?x=1'));
        $this->cek('hak akses: Operator & Admin boleh pengaturan dan hapus', \App\Libraries\HakAkses::boleh('operator', 'admin/pkl/pengaturan') && \App\Libraries\HakAkses::boleh('operator', 'admin/pkl/hapus/3') && \App\Libraries\HakAkses::boleh('admin', 'admin/pkl/pengaturan'));
        $this->cek('hak akses: Hubin tetap tak boleh area lain; peran tak dikenal tak boleh apa pun', ! \App\Libraries\HakAkses::boleh('hubin', 'admin/master/siswa') && ! \App\Libraries\HakAkses::boleh('hubin', 'admin/akun') && ! \App\Libraries\HakAkses::boleh('xyz', 'admin/pkl'));
        $terlarang = ['admin/biodata', 'admin/biodata/12', 'admin/biodata/laporan', 'admin/master/siswa', 'admin/master/kelas', 'admin/master/guru', 'admin/master/jurusan', 'admin/master/mapel', 'admin/dokumen', 'admin/settings', 'admin/dashboard'];
        $bocor = array_values(array_filter($terlarang, static fn (string $a) => \App\Libraries\HakAkses::boleh('hubin', $a)));
        $this->cek('KEPUTUSAN: Hubin TIDAK boleh Isian Biodata Siswa & Master Data (dan area non-PKL lain)', $bocor === [], json_encode($bocor));
        $this->cek('Operator tetap boleh Isian Biodata & Master Siswa/Kelas (tak ikut terkunci)', \App\Libraries\HakAkses::boleh('operator', 'admin/biodata') && \App\Libraries\HakAkses::boleh('operator', 'admin/master/siswa') && \App\Libraries\HakAkses::boleh('operator', 'admin/master/kelas'));

        // ---------- 6h. konsistensi akhir & hapus ----------
        $this->cek('periksaKonsistensi(): seluruh data uji tahap 3 konsisten', array_filter($ajuan->periksaKonsistensi(), fn ($x) => in_array((int) $x['siswa_id'], $s, true)) === []);
        $this->db->table('pkl_pengajuan')->where('id', $a4)->delete();
        $this->cek('hapus ajuan disetujui: siswanya bebas lagi, master TETAP ada', $model->aktifMilik($s[24]) === null && $model->aktifMilik($s[25]) === null && $masterJml('zzuji data lama') === 1);
    }

    // =================================================================
    // 7. Tahap 4: nomor surat, surat Word, template, impor
    // =================================================================

    private function xmlDocx(string $biner): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ujd');
        file_put_contents($tmp, $biner);
        $z   = new \ZipArchive();
        $xml = $z->open($tmp) === true ? (string) $z->getFromName('word/document.xml') : '';
        $z->close();
        @unlink($tmp);

        return $xml;
    }

    private function xmlSah(string $xml): bool
    {
        return $xml !== '' && @(new \DOMDocument())->loadXML($xml) !== false;
    }

    private function ujiSurat(): void
    {
        $this->bagian('Tahap 4: nomor surat, surat Word, template, impor');

        // ---------- pembantu murni ----------
        $t = new \DateTimeImmutable('2027-03-06');
        $this->cek('format nomor bawaan', \App\Libraries\PklNomorSurat::format('{urut}/PKL/{tgl}-{bln}-{thn}', 7, $t) === '7/PKL/06-03-2027');
        $this->cek('format nomor romawi + urut 3 angka', \App\Libraries\PklNomorSurat::format('{urut3}/PKL/{bln_romawi}/{thn}', 7, $t) === '007/PKL/III/2027');
        $periksa = static fn (string $pola) => \App\Libraries\PklNomorSurat::periksa($pola);
        $this->cek('pola sah lolos', $periksa('{urut}/PKL/{tgl}-{bln}-{thn}') === null && $periksa('421.5/{urut4}/SMK/{bln_romawi}/{thn}') === null);
        $this->cek('pola tanpa {urut} ditolak', $periksa('PKL/{tgl}') !== null);
        $this->cek('pola dengan penanda tak dikenal ditolak', $periksa('{urut}/{ngawur}') !== null);
        $this->cek('pola berkarakter berbahaya ditolak', $periksa('{urut}<script>') !== null && $periksa('{urut}/{') !== null);
        $this->cek('pola kosong / terlalu panjang ditolak', $periksa('') !== null && $periksa('{urut}' . str_repeat('a', 100)) !== null);

        // ---------- bahan ----------
        $k = $this->db->table('kelas')->select('id, nama_kelas')->where('tingkat', 'XI')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('bahan uji: kelas XI tersedia', false);

            return;
        }
        $kelasXI = (int) $k['id'];
        $s = [];
        foreach (range(40, 54) as $n) {
            $s[$n] = $this->buatSiswa($n, $kelasXI);
        }
        $this->pengaturanAsli ??= (function () {
            $r = (new PklPengaturanModel())->ambil();
            unset($r['id']);

            return $r;
        })();
        $setPeng = fn (array $data) => $this->db->table('pkl_pengaturan')->where('id', 1)->update($data);
        $setPeng(['format_nomor' => '{urut}/PKL/{tgl}-{bln}-{thn}', 'nomor_awal' => 1, 'nomor_awal_tahun' => null, 'waka_hubin_nama' => 'Budi Santoso, S.Pd.', 'waka_hubin_nip' => '198001012005011001', 'template_surat' => null]);

        $ajuan = new PklAjuan();
        $svc   = new \App\Libraries\PklSurat();
        $model = new PklPengajuanModel();
        $admin = ['oleh' => 'Operator Uji', 'admin_id' => 1, 'peran' => 'operator', 'ip' => '10.9.9.9'];
        $pj    = static fn (int $id): array => ['siswa_id' => $id, 'kelas_id' => $kelasXI, 'peran' => 'pengaju'];
        $tm    = static fn (int $id): array => ['siswa_id' => $id, 'kelas_id' => $kelasXI, 'peran' => 'teman'];
        $tgl   = static fn (int $selisih): string => date('Y-m-d', strtotime(($selisih >= 0 ? '+' : '') . $selisih . ' days'));
        $setuju = fn (string $nama, array $anggota) => (int) $ajuan->kirimBaru($this->dataLangsung($nama, $tgl(10), $tgl(100)), $anggota, $admin + ['sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui'])['id'];
        $thn   = (int) date('Y');
        $maksTahun = fn (int $y): int => (int) $this->db->query('SELECT COALESCE(MAX(urut), 0) m FROM pkl_surat WHERE tahun = ?', [$y])->getRowArray()['m'];

        $a1 = $setuju('PT ZZUJI Surat A', [$pj($s[40]), $tm($s[41])]);
        $a2 = $setuju('PT ZZUJI Surat B', [$pj($s[42])]);
        $a3 = (int) $ajuan->kirimBaru($this->dataLangsung('PT ZZUJI Belum ACC', $tgl(10), $tgl(100)), [$pj($s[43])], $admin)['id']; // menunggu

        // ---------- penomoran ----------
        $dasar = $maksTahun($thn);
        $r1    = $svc->terbitkan($a1, "$thn-03-06", $admin);
        $this->cek('terbitkan: nomor pertama = urutan berikutnya, format bawaan', $r1['ok'] && $r1['baru'] === true && (int) $r1['surat']['urut'] === $dasar + 1 && $r1['surat']['nomor'] === ($dasar + 1) . "/PKL/06-03-$thn", json_encode($r1));
        $r1b = $svc->terbitkan($a1, "$thn-09-09", $admin);
        $this->cek('terbitkan ulang ajuan yang sama → nomor SAMA, tanggal lama dipertahankan', $r1b['ok'] && $r1b['baru'] === false && $r1b['surat']['nomor'] === $r1['surat']['nomor'] && $r1b['surat']['tanggal_surat'] === "$thn-03-06");
        $r2 = $svc->terbitkan($a2, "$thn-03-06", $admin);
        $this->cek('ajuan kedua mendapat nomor berikutnya (tanpa loncat)', (int) $r2['surat']['urut'] === $dasar + 2);
        $r3 = $svc->terbitkan($a3, "$thn-03-06", $admin);
        $this->cek('ajuan yang belum disetujui TIDAK bisa diberi nomor', $r3['ok'] === false && ($r3['kode'] ?? '') === 'status' && $svc->surat($a3) === null);
        $this->cek('tanggal tak sah ditolak', ($svc->terbitkan($a2, '2027-02-30', $admin)['kode'] ?? '') === 'tanggal' && ($svc->terbitkan($a2, 'bukan', $admin)['kode'] ?? '') === 'tanggal');
        $dup = true;
        try {
            $dup = (bool) $this->db->table('pkl_surat')->insert(['pengajuan_id' => $a3, 'tahun' => $thn, 'urut' => $dasar + 1, 'nomor' => 'X', 'tanggal_surat' => "$thn-03-06", 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            $dup = false;
        }
        $this->cek('DB: nomor ganda (tahun, urut) ditolak UNIQUE', $dup === false && $this->db->table('pkl_surat')->where('pengajuan_id', $a3)->countAllResults() === 0);

        $setPeng(['nomor_awal' => $dasar + 20, 'nomor_awal_tahun' => $thn]);
        $a4 = $setuju('PT ZZUJI Surat C', [$pj($s[44])]);
        $a5 = $setuju('PT ZZUJI Surat D', [$pj($s[45])]);
        $r4 = $svc->terbitkan($a4, "$thn-03-06", $admin);
        $r5 = $svc->terbitkan($a5, "$thn-03-06", $admin);
        $this->cek('lantai "nomor berikutnya" dihormati lalu lanjut berurutan', (int) $r4['surat']['urut'] === $dasar + 20 && (int) $r5['surat']['urut'] === $dasar + 21);
        $dasarDepan = $maksTahun($thn + 1);
        $a6 = $setuju('PT ZZUJI Surat E', [$pj($s[46])]);
        $r6 = $svc->terbitkan($a6, ($thn + 1) . '-01-10', $admin);
        $this->cek('tahun berbeda → urutan sendiri (lantai tahun lain tak berlaku)', (int) $r6['surat']['urut'] === $dasarDepan + 1 && (int) $r6['surat']['tahun'] === $thn + 1, json_encode($r6['surat'] ?? null));
        $setPeng(['nomor_awal' => 1, 'nomor_awal_tahun' => null, 'format_nomor' => '{urut3}/PKL/{bln_romawi}/{thn}']);
        $a7 = $setuju('PT ZZUJI Surat F', [$pj($s[47])]);
        $r7 = $svc->terbitkan($a7, "$thn-03-06", $admin);
        $this->cek('format nomor kustom dipakai', (bool) preg_match('#^\d{3,}/PKL/III/' . $thn . '$#', $r7['surat']['nomor']), (string) ($r7['surat']['nomor'] ?? ''));
        $setPeng(['format_nomor' => '{urut}/PKL/{tgl}-{bln}-{thn}']);

        // ---------- perlu cetak ulang ----------
        $st = $svc->statusBanyak([$a1, $a2]);
        $this->cek('baru diterbitkan: tidak perlu cetak ulang', $st[$a1]['perlu_ulang'] === false && $st[$a2]['perlu_ulang'] === false && $st[$a1]['nomor'] === $r1['surat']['nomor']);
        $ajuan->ubahIsi($a1, $this->dataLangsung('PT ZZUJI Surat A Ganti', $tgl(10), $tgl(100)), [$pj($s[40]), $tm($s[41])], $admin);
        $st = $svc->statusBanyak([$a1, $a2]);
        $this->cek('data ajuan berubah → a1 PERLU CETAK ULANG, a2 tidak', $st[$a1]['perlu_ulang'] === true && $st[$a2]['perlu_ulang'] === false);
        $m = $svc->muat($a1);
        $this->cek('sidik langsung (muat) konsisten dengan statusBanyak', \App\Libraries\PklSurat::sidik($m['ajuan'], $m['anggota']) !== (string) $svc->surat($a1)['sidik']);

        // ---------- berkas Word bawaan ----------
        $b = $svc->bangun([$a1, $a2], $admin);
        $xml = $b['ok'] ? $this->xmlDocx($b['biner']) : '';
        $this->cek('bangun 2 surat: berhasil, berkas .docx', $b['ok'] && ($b['jumlah'] ?? 0) === 2 && str_ends_with((string) $b['nama'], '.docx') && str_starts_with((string) $b['biner'], 'PK'), json_encode($b['pesan'] ?? $b['nama'] ?? ''));
        $this->cek('isi dokumen = XML Word sah', $this->xmlSah($xml));
        $this->cek('isi memuat nomor, perusahaan, siswa, penanda tangan', str_contains($xml, htmlspecialchars($r1['surat']['nomor'])) && str_contains($xml, 'PT ZZUJI Surat A Ganti') && str_contains($xml, 'ZZUJI PKL 41') && str_contains($xml, 'Budi Santoso, S.Pd.') && str_contains($xml, 'NIP. 198001012005011001'));
        $this->cek('dua surat dipisah SATU pindah halaman', substr_count($xml, '<w:br w:type="page"/>') === 1);
        $this->cek('tabel siswa memuat kolom judul & kompetensi keahlian', str_contains($xml, 'Kompetensi Keahlian') && str_contains($xml, 'NISN'));
        $st = $svc->statusBanyak([$a1, $a2]);
        $this->cek('setelah diunduh: tak lagi perlu cetak ulang, cetak_ke naik, riwayat tercatat', $st[$a1]['perlu_ulang'] === false && (int) $svc->surat($a1)['cetak_ke'] === 1 && $this->db->table('pkl_riwayat')->where('pengajuan_id', $a1)->where('aksi', 'cetak')->countAllResults() === 1);
        $b2 = $svc->bangun([$a1], $admin);
        $this->cek('unduh lagi: nomor tetap, cetak_ke 2', $b2['ok'] && (int) $svc->surat($a1)['cetak_ke'] === 2 && $svc->surat($a1)['nomor'] === $r1['surat']['nomor'] && str_contains((string) $b2['nama'], self::amanNomor($r1['surat']['nomor'])), (string) ($b2['nama'] ?? ''));
        $tak = $svc->bangun([$a3], $admin);
        $this->cek('bangun untuk ajuan belum disetujui ditolak', $tak['ok'] === false);

        $a8 = $setuju('PT ZZUJI & <Mitra> "Q"', [$pj($s[48])]);
        $b8 = $svc->bangun([$a8], $admin);
        $x8 = $b8['ok'] ? $this->xmlDocx($b8['biner']) : '';
        $this->cek('karakter khusus (& < > ") di-escape → XML tetap sah & teks utuh', $this->xmlSah($x8) && str_contains($x8, 'PT ZZUJI &amp; &lt;Mitra&gt; &quot;Q&quot;'));
        $this->cek('nama berkas aman dari karakter terlarang', ! preg_match('#[\\\\/:*?"<>|]#', (string) $b8['nama']), (string) ($b8['nama'] ?? ''));

        // ---------- template Word sekolah ----------
        $badan = '<w:p><w:r><w:t>Nomor ${nom</w:t></w:r><w:r><w:t>or} untuk ${perusahaan}</w:t></w:r></w:p>'
            . \App\Libraries\PklDocx::tabel([['${no}', '${siswa_nama}']], [600, 4000])
            . '<w:p><w:r><w:t>${siswa_daftar} | ${tidak_ada}</w:t></w:r></w:p>';
        $tmpl = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ujitmpl_' . uniqid() . '.docx';
        file_put_contents($tmpl, \App\Libraries\PklDocx::dokumen([$badan], null));
        $pen = \App\Libraries\PklDocx::penandaDi($tmpl);
        $this->cek('penandaDi: penanda yang terpecah antar-bagian ditemukan utuh', in_array('nomor', $pen, true) && in_array('siswa_nama', $pen, true) && in_array('tidak_ada', $pen, true) && ! in_array('nom', $pen, true), json_encode($pen));
        $baris = static fn (string $no, string $nama): array => ['no' => $no, 'nama' => $nama, 'nis' => '', 'nisn' => '1', 'kelas' => 'XI', 'jurusan' => 'TKJ'];
        $isi = \App\Libraries\PklDocx::dariTemplate($tmpl, [
            ['v' => ['nomor' => 'A/1', 'perusahaan' => 'PT & Co', 'siswa_daftar' => "1. Satu\n2. Dua"], 'siswa' => [$baris('1', 'Siswa Satu'), $baris('2', 'Siswa Dua')]],
            ['v' => ['nomor' => 'B/2', 'perusahaan' => 'CV Lain', 'siswa_daftar' => '1. Tiga'], 'siswa' => [$baris('1', 'Siswa Tiga')]],
        ]);
        $xt = $this->xmlDocx($isi);
        $this->cek('template: hasil XML sah', $this->xmlSah($xt));
        $this->cek('template: penanda terpecah terisi, nilai ber-& di-escape', str_contains($xt, 'Nomor A/1 untuk PT &amp; Co') && str_contains($xt, 'Nomor B/2 untuk CV Lain'));
        $this->cek('template: baris tabel digandakan per siswa (2 + 1 = 3 baris)', substr_count($xt, '<w:tr>') === 3 && str_contains($xt, 'Siswa Satu') && str_contains($xt, 'Siswa Dua') && str_contains($xt, 'Siswa Tiga') && ! str_contains($xt, '${no}'));
        $this->cek('template: penanda tak dikenal dibiarkan terlihat; baris baru jadi <w:br/>; surat dipisah halaman', str_contains($xt, '${tidak_ada}') && str_contains($xt, '<w:br/>') && substr_count($xt, '<w:br w:type="page"/>') === 1);
        $this->cek('template: berkas pendukung (styles, rels) ikut tersalin', (function () use ($isi) {
            $f = tempnam(sys_get_temp_dir(), 'ujz');
            file_put_contents($f, $isi);
            $z = new \ZipArchive();
            $z->open($f);
            $ada = $z->locateName('word/styles.xml') !== false && $z->locateName('[Content_Types].xml') !== false;
            $z->close();
            @unlink($f);

            return $ada;
        })());

        $tujuan = \App\Libraries\PklSurat::dirBerkas() . \App\Libraries\PklSurat::BERKAS_TEMPLATE;
        copy($tmpl, $tujuan);
        $setPeng(['template_surat' => \App\Libraries\PklSurat::BERKAS_TEMPLATE]);
        $bt = $svc->bangun([$a2], $admin);
        $xb = $bt['ok'] ? $this->xmlDocx($bt['biner']) : '';
        $this->cek('bangun memakai template unggahan bila ada (bukan surat bawaan)', $bt['ok'] && str_contains($xb, 'untuk PT ZZUJI Surat B') && ! str_contains($xb, 'Kompetensi Keahlian'), json_encode($bt['pesan'] ?? ''));
        @unlink($tujuan);
        $setPeng(['template_surat' => null]);
        $bx = $svc->bangun([$a2], $admin);
        $this->cek('template dihapus → kembali ke surat bawaan', $bx['ok'] && str_contains($this->xmlDocx($bx['biner']), 'Kompetensi Keahlian'));
        $setPeng(['template_surat' => 'tidak_ada.docx']);
        $by = $svc->bangun([$a2], $admin);
        $this->cek('berkas template hilang dari disk → otomatis surat bawaan (tak error)', $by['ok'] && str_contains($this->xmlDocx($by['biner']), 'Kompetensi Keahlian'));
        $setPeng(['template_surat' => null]);
        @unlink($tmpl);
        $contoh = $svc->contohTemplate();
        $xc = $this->xmlDocx($contoh);
        $this->cek('contoh template memuat semua penanda & sah', $this->xmlSah($xc) && str_contains($xc, '${nomor}') && str_contains($xc, '${siswa_nama}') && str_contains($xc, '${waka_nama}'));

        // ---------- impor riwayat lama ----------
        $x  = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $x->getActiveSheet();
        $serial = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime('2026-01-05'));
        $ws->fromArray([
            ['NIS', 'Nama', 'Kelas', 'Perusahaan', 'Alamat', 'Kota', 'Telepon', 'Kontak', 'Jabatan', 'Mulai', 'Selesai'],
            ['ZZUJIPKL50', '', '', 'PT ZZUJI Impor', 'Jl. Impor 1, Cikarang', 'bekasi', '021-888 777', 'bapak impor', 'HRD', '2026-01-05', '2026-04-05'],
            ['ZZUJIPKL51', '', '', 'PT ZZUJI Impor', '', '', '', '', '', $serial, '05/04/2026'],
            ['', 'zzuji pkl 52', $k['nama_kelas'], 'PT ZZUJI Impor', '', '', '', '', '', '5-1-2026', '2026-04-05'],
            ['TIDAKADA', '', '', 'PT ZZUJI Impor', '', '', '', '', '', '2026-01-05', '2026-04-05'],
            ['ZZUJIPKL50', '', '', 'PT ZZUJI Impor', '', '', '', '', '', '2026-01-05', '2026-04-05'],
            ['ZZUJIPKL53', '', '', 'PT ZZUJI Impor', '', '', '', '', '', 'bukan tanggal', '2026-04-05'],
            ['ZZUJIPKL40', '', '', 'PT ZZUJI Impor', '', '', '', '', '', '2026-01-05', '2026-04-05'],
            ['ZZUJIPKL54', '', '', 'PT ZZUJI Impor B', '', 'Jakarta', '', '', '', '2026-02-01', '2026-05-01'],
            ['', '', '', '', '', '', '', '', '', '', ''],
        ], null, 'A1');
        $xlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ujiimpor_' . uniqid() . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($x))->save($xlsx);

        $imp = new \App\Libraries\PklImpor();
        $pra = $imp->baca($xlsx);
        $this->cek('impor pratinjau: 2 kelompok, 4 siswa, 4 baris bermasalah, baris kosong diabaikan', count($pra['kelompok']) === 2 && $pra['ringkas']['siswa'] === 4 && $pra['ringkas']['galat'] === 4, json_encode($pra['ringkas']));
        $pesan = implode(' | ', array_column($pra['galat'], 'pesan'));
        $this->cek('impor: galat jelas (tak ditemukan, dobel, tanggal, sudah aktif)', str_contains($pesan, 'tidak ditemukan') && str_contains($pesan, 'dua kali') && str_contains($pesan, 'Tanggal mulai/selesai') && str_contains($pesan, 'sudah punya ajuan PKL aktif'), $pesan);
        $kel = $pra['kelompok'][0];
        $this->cek('impor: tiga format tanggal (teks, serial Excel, d/m/Y & j-n-Y) → satu kelompok; kolom opsional dilengkapi', count($kel['siswa']) === 3 && $kel['mulai'] === '2026-01-05' && $kel['selesai'] === '2026-04-05' && $kel['kota'] === 'Bekasi' && $kel['telepon'] === '021888777' && $kel['kontak_nama'] === 'Bapak Impor');
        $this->cek('impor: siswa dicocokkan lewat NIS maupun Nama+Kelas (tak peka huruf besar)', array_column($kel['siswa'], 'siswa_id') === [$s[50], $s[51], $s[52]]);
        $sebelum = $this->db->table('pkl_pengajuan')->countAllResults();
        $this->cek('impor: pratinjau TIDAK menyimpan apa pun', $this->db->table('pkl_pengajuan')->countAllResults() === $sebelum);

        $sim = $imp->simpan($pra, $admin);
        $this->cek('impor simpan: 2 ajuan, 4 siswa', $sim['dibuat'] === 2 && $sim['siswa'] === 4 && $sim['gagal'] === [], json_encode($sim));
        $row = $this->db->query("SELECT p.* FROM pkl_pengajuan p JOIN pkl_anggota a ON a.pengajuan_id = p.id WHERE a.siswa_id = ? AND a.peran = 'pengaju'", [$s[50]])->getRowArray() ?? [];
        $this->cek('impor: ajuan Disetujui, sumber impor, tertaut master', ($row['status'] ?? '') === 'disetujui' && ($row['sumber'] ?? '') === 'impor' && (int) ($row['perusahaan_id'] ?? 0) > 0 && ($row['perusahaan_nama'] ?? '') === 'PT ZZUJI Impor');
        $idImp = (int) ($row['id'] ?? 0);
        $this->cek('impor: ketiga siswa terkunci; pengaju & teman benar', $this->kunci($idImp) === [$s[50] => $s[50], $s[51] => $s[51], $s[52] => $s[52]] && $this->db->table('pkl_anggota')->where('pengajuan_id', $idImp)->where('peran', 'teman')->countAllResults() === 2);
        $this->cek('impor: riwayat "impor" tercatat, Status Siswa membaca mereka sebagai sudah PKL', $this->db->table('pkl_riwayat')->where('pengajuan_id', $idImp)->where('aksi', 'impor')->countAllResults() === 1 && count(array_filter($model->statusSiswa(['XI'], 0, 'ZZUJI PKL 5', 'sudah_pkl', 50, 1)[0], static fn ($r) => (int) $r['ajuan_id'] === $idImp)) === 3);
        $jml = $this->db->table('pkl_pengajuan')->countAllResults();
        $sim2 = $imp->simpan($pra, $admin);
        $this->cek('impor disimpan DUA KALI: tak ada ajuan ganda (siswa sudah terkunci), gagal dilaporkan', $sim2['dibuat'] === 0 && count($sim2['gagal']) === 2 && $this->db->table('pkl_pengajuan')->countAllResults() === $jml, json_encode($sim2));
        @unlink($xlsx);
        $bad = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ujirusak_' . uniqid() . '.xlsx';
        file_put_contents($bad, 'bukan excel');
        $galatBaca = '';
        try {
            $imp->baca($bad);
        } catch (\RuntimeException $e) {
            $galatBaca = $e->getMessage();
        }
        @unlink($bad);
        $this->cek('berkas rusak/kosong → pesan ramah berbahasa Indonesia, bukan galat sistem', $galatBaca !== '' && ! str_contains($galatBaca, 'PhpOffice') && ! str_contains($galatBaca, '.php'), $galatBaca);
        $this->cek('contoh berkas impor sah (zip xlsx)', str_starts_with(\App\Libraries\PklImpor::contoh(), 'PK'));
        $this->cek('periksaKonsistensi(): data uji tahap 4 konsisten', array_filter($ajuan->periksaKonsistensi(), fn ($z) => in_array((int) $z['siswa_id'], $s, true)) === []);
    }

    /** Padanan PklSurat::amanNama untuk nomor (garis miring → strip) — dipakai mencocokkan nama berkas unduhan. */
    private static function amanNomor(string $nomor): string
    {
        return \App\Libraries\PklSurat::amanNama($nomor);
    }

    // =================================================================
    // Bersih-bersih
    // =================================================================

    private function bersihkan(): void
    {
        $ids = array_map('intval', array_column(
            $this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(),
            'id'
        ));

        if ($ids !== []) {
            $aj = array_map('intval', array_column(
                $this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(),
                'pengajuan_id'
            ));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete(); // CASCADE → anggota & riwayat
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }

        // Ajuan uji yang anggotanya sudah terhapus (mis. s6) — cari lewat IP uji.
        $this->db->table('pkl_pengajuan')->whereIn('ip_address', ['10.9.9.9'])->delete();
        $this->db->table('pkl_perusahaan')->where('alamat', self::TANDA_PERUSAHAAN)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzuji', 'after')->delete(); // master hasil ACC di uji tahap 3

        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
    }
}
