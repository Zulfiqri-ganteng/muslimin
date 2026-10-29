<?php

namespace App\Commands;

use App\Libraries\ApiAuth;
use App\Libraries\BackupSistem;
use App\Libraries\HakAkses;
use App\Libraries\ModeMaintenance;
use App\Libraries\PulihkanBackup;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji Sistem → Backup & Restore (docs/DESAIN-ALAT-SISTEM.md F3 & F4). Database asli laptop HANYA DIBACA (dibackup); semua restore
 * masuk ke database percobaan `muslimin_uji_pulih` (dibuat & dihapus uji ini). Folder backup & status maintenance = sementara.
 *   pemecah SQL · periksa berkas · backup → restore → CHECKSUM semua tabel sama persis · restore bertahap (langkah kecil) ·
 *   retensi · halaman & syarat restore web (maintenance, sandi, PULIHKAN) · langkah JSON · API · hak.
 *
 *   php spark dev:uji-backup
 *   UJI_FIXTURE=C:\flutter-muslimin\test\fixtures\sistem php spark dev:uji-backup
 */
class UjiBackup extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-backup';
    protected $description = 'Uji Backup & Restore (ke database percobaan muslimin_uji_pulih).';

    private const DB_UJI = 'muslimin_uji_pulih';
    private const SANDI  = 'ZzUji-Backup-2026';

    private int $lulus = 0;
    private int $gagal = 0;
    private array $fx = [];
    private string $dir = '';
    private int $adminUji = 0;
    private ?BaseConnection $uji = null;

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

    public function run(array $params)
    {
        $db              = db_connect();
        // Di LUAR writable/cache: restore selesai menjalankan cache()->clean() yang menyapu isi folder cache (termasuk subfolder).
        $this->dir       = WRITEPATH . 'uji_backup_' . getmypid() . DIRECTORY_SEPARATOR;
        $auditAwal       = (int) ($db->table('audit_log')->selectMax('id', 'm')->get()->getRow()->m ?? 0);
        $berkasMm        = $this->dir . 'uji_maintenance.json';
        try {
            $db->query('DROP DATABASE IF EXISTS `' . self::DB_UJI . '`');
            $db->query('CREATE DATABASE `' . self::DB_UJI . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            $this->uji = \Config\Database::connect(['database' => self::DB_UJI] + (array) config('Database')->default, false);
            BackupSistem::pakaiFolder($this->dir);
            ModeMaintenance::pakaiBerkas($berkasMm);
            $db->table('admins')->insert(['username' => 'zzujibackup', 'email' => 'zzujibackup@contoh.test', 'full_name' => 'ZZUJI Backup',
                'password' => password_hash(self::SANDI, PASSWORD_DEFAULT), 'role' => 'admin', 'aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $this->adminUji = (int) $db->insertID();

            $this->ujiPecahPeriksa();
            $this->ujiBackupRestore();
            $this->ujiRetensi();
            $this->ujiWeb();
            $this->ujiApi();
            $this->ujiHak();
            $this->tulisFixture();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            BackupSistem::pakaiKoneksi(null);
            BackupSistem::pakaiFolder(null);
            ModeMaintenance::pakaiBerkas(null);
            @unlink($berkasMm);
            ApiAuth::clear();
            session()->remove(['isLoggedIn', 'admin']);
            $this->uji?->close();
            $db->query('DROP DATABASE IF EXISTS `' . self::DB_UJI . '`');
            if ($this->adminUji > 0) {
                $db->table('admins')->where('id', $this->adminUji)->delete();
            }
            $db->table('audit_log')->where('id >', $auditAwal)->whereIn('tabel', ['backup', 'maintenance'])->delete();
            foreach (glob($this->dir . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================

    private function ujiPecahPeriksa(): void
    {
        CLI::write('== Pemecah SQL & pemeriksa berkas ==', 'yellow');
        $sql = "-- komentar; ada titik koma\nSET a=1;\n/*!40101 SET NAMES utf8mb4 */;\nINSERT INTO `t` VALUES ('a;b','it\\'s; ok',\"x;y\"),('-- bukan komentar','/* bukan */');\n# hash ; komentar\n/* blok ; */ CREATE TABLE `x;y` (id int);\nSELECT 1";
        [$p, $pakai, $ujung] = PulihkanBackup::pecah($sql, true);
        $this->cek('pecah: ; di dalam kutip/komentar diabaikan, komentar bersyarat /*! dipertahankan, perintah terakhir tanpa ;', count($p) === 5
            && $p[1] === '/*!40101 SET NAMES utf8mb4 */' && str_contains($p[2], "'it\\'s; ok'") && $p[3] === 'CREATE TABLE `x;y` (id int)' && $p[4] === 'SELECT 1'
            && $pakai === strlen($sql) && count($ujung) === 5, json_encode($p));
        [$p, $pakai] = PulihkanBackup::pecah("SELECT 1; SELECT 'belum", true);
        $this->cek('pecah: kutip tidak tertutup di akhir berkas TIDAK dianggap terpakai', $p === ['SELECT 1'] && $pakai === 9);
        [$p, $pakai] = PulihkanBackup::pecah("SELECT 1; SELECT 2", false);
        $this->cek('pecah: blok belum akhir → sisa tanpa ; menunggu blok berikut', $p === ['SELECT 1'] && $pakai === 9);

        @mkdir($this->dir, 0755, true);
        $tulis = function (string $nama, string $isi): string {
            file_put_contents($this->dir . $nama, $isi);

            return $this->dir . $nama;
        };
        $ok = PulihkanBackup::periksa($tulis('a.sql', "CREATE TABLE `admins` (id int);\nCREATE TABLE `migrations` (id int);\n"));
        $this->cek('periksa: berisi admins + migrations → sah', $ok['ok'] && $ok['tabel'] === 2, json_encode($ok));
        $this->cek('periksa: database lain (tanpa admins) ditolak', ! PulihkanBackup::periksa($tulis('b.sql', "CREATE TABLE `lain` (id int);"))['ok']);
        $this->cek('periksa: DELIMITER ditolak', ! PulihkanBackup::periksa($tulis('c.sql', "CREATE TABLE `admins` (id int);CREATE TABLE `migrations` (id int);\nDELIMITER //\n"))['ok']);
        $this->cek('periksa: backup buatan sistem tanpa penanda selesai (terpotong) ditolak',
            ! PulihkanBackup::periksa($tulis('d.sql', BackupSistem::PENANDA_AWAL . "\nCREATE TABLE `admins` (id int);CREATE TABLE `migrations` (id int);"))['ok']);
        foreach (['a.sql', 'b.sql', 'c.sql', 'd.sql'] as $f) {
            @unlink($this->dir . $f);
        }
    }

    private function ujiBackupRestore(): void
    {
        CLI::write('== Backup database laptop → restore ke ' . self::DB_UJI . ' → bandingkan ==', 'yellow');
        $b = BackupSistem::buatDb('manual', 'ZZUJI Backup');
        $this->cek('backup database: berkas db-…-manual.sql.gz, jumlah tabel & oleh terbaca, tanpa berkas .tmp', str_starts_with($b['kunci'], 'db-') && $b['tabel'] > 50
            && $b['oleh'] === 'ZZUJI Backup' && glob($this->dir . '*.tmp') === [], json_encode($b));
        $isi = (string) gzdecode((string) file_get_contents(BackupSistem::path($b['kunci'])));
        $this->cek('isi backup: kepala, DROP+CREATE, penanda selesai', str_starts_with($isi, BackupSistem::PENANDA_AWAL) && str_contains($isi, 'DROP TABLE IF EXISTS `admins`')
            && str_contains($isi, BackupSistem::PENANDA_SELESAI));
        $this->cek('kunci asing ditolak (path(), hapus())', BackupSistem::path('../../.env') === null && BackupSistem::path('db-x.sql.gz') === null && ! BackupSistem::hapus('../index.php'));

        BackupSistem::pakaiKoneksi($this->uji);
        $this->uji->query('CREATE TABLE `sisa_lama` (id int)'); // tabel yang tidak ada di backup harus hilang
        $t = PulihkanBackup::mulai(BackupSistem::path($b['kunci']), $b['kunci'], 'ZZUJI Backup');
        $this->cek('mulai(): tugas siap, berkas kerja .sql terurai', ($t['tahap'] ?? '') === 'siap' && is_file($this->dir . 'pulihkan-' . $t['id'] . '.sql'), json_encode($t));
        $t2 = PulihkanBackup::mulai(BackupSistem::path($b['kunci']), $b['kunci'], 'ZZUJI Backup');
        $this->cek('restore kedua ditolak selama yang pertama belum selesai', isset($t2['galat']));
        $langkah = 0;
        do {
            $t = PulihkanBackup::langkah($t['id'], 0.2);
            $langkah++;
        } while (in_array($t['tahap'], ['siap', 'jalan'], true) && $langkah < 500);
        $this->cek('restore bertahap (langkah 0,2 dtk) selesai dalam beberapa langkah', $t['tahap'] === 'selesai' && $langkah > 1, $t['tahap'] . ' ' . $langkah . ' langkah ' . ($t['galat'] ?? ''));
        $this->cek('backup "sebelum-pulihkan" dibuat, berkas kerja dihapus', is_string($t['cadangan']) && str_contains($t['cadangan'], 'sebelum-pulihkan')
            && ! is_file($this->dir . 'pulihkan-' . $t['id'] . '.sql'));
        $beda = $this->bandingkan(db_connect(), $this->uji);
        $this->cek('CHECKSUM semua tabel database percobaan = database asli (isi sama persis)', $beda === [], implode(', ', array_slice($beda, 0, 8)));
        $this->cek('tabel yang tidak ada di backup ikut terhapus', $this->uji->query("SHOW TABLES LIKE 'sisa_lama'")->getNumRows() === 0);
        $this->cek('migrasi belum = 0 (backup dari kode yang sama)', PulihkanBackup::migrasiBelum() === 0, (string) PulihkanBackup::migrasiBelum());

        // Restore bertahap dari berkas ekspor phpMyAdmin hosting (bila ada di laptop): perintah transaksi dilewati, hasil = isi berkas.
        $dump = glob(ROOTPATH . 'database/kank9494_muslimin*.sql') ?: [];
        if ($dump !== []) {
            rsort($dump);
            $t = PulihkanBackup::mulai($dump[0], basename($dump[0]), 'ZZUJI Backup');
            $n = 0;
            do {
                $t = PulihkanBackup::langkah($t['id'], 1.0);
                $n++;
            } while (in_array($t['tahap'] ?? '', ['siap', 'jalan'], true) && $n < 500);
            $siswa = (int) ($this->uji->query('SELECT COUNT(*) AS n FROM siswa')->getRow()->n ?? 0);
            $tabel = $this->uji->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getNumRows();
            $this->cek('restore ekspor phpMyAdmin (' . basename($dump[0]) . ') bertahap: selesai, ' . $tabel . ' tabel, ' . $siswa . ' siswa', ($t['tahap'] ?? '') === 'selesai'
                && $tabel === ($t['tabel_berkas'] ?? -1) && $siswa > 1000, ($t['tahap'] ?? '') . ' ' . ($t['galat'] ?? '') . " $n langkah");
        }
        BackupSistem::pakaiKoneksi(null);
    }

    private function ujiRetensi(): void
    {
        CLI::write('== Retensi ==', 'yellow');
        foreach (range(1, 12) as $i) {
            $gz = gzopen($this->dir . sprintf('db-202601%02d-010000-manual.sql.gz', $i), 'wb');
            gzwrite($gz, BackupSistem::PENANDA_AWAL . "\n");
            gzclose($gz);
        }
        file_put_contents($this->dir . 'db-20260101-010000-otomatis.sql.gz.tmp', 'x');
        BackupSistem::rapikan();
        $manual = array_filter(BackupSistem::daftar(), static fn (array $b): bool => $b['isi'] === 'db' && $b['jenis'] === 'manual');
        $this->cek('manual disimpan 10 terbaru (yang lama terhapus); berkas .tmp tidak masuk daftar', count($manual) === BackupSistem::JENIS['manual'][1]
            && ! is_file($this->dir . 'db-20260101-010000-manual.sql.gz') && is_file($this->dir . 'db-20260112-010000-manual.sql.gz'), (string) count($manual));
        $daftar = BackupSistem::daftar();
        $this->cek('daftar urut terbaru di atas', $daftar !== [] && $daftar[0]['waktu'] >= end($daftar)['waktu']);
        $f = BackupSistem::buatBerkas('manual', 'ZZUJI Backup');
        $zip = new \ZipArchive();
        $this->cek('backup berkas unggahan: .zip terbuka, berisi folder unggahan', str_starts_with($f['kunci'], 'berkas-') && $zip->open(BackupSistem::path($f['kunci'])) === true
            && $zip->numFiles > 0 && str_contains((string) $zip->getArchiveComment(), 'ZZUJI Backup'));
        $zip->close();
    }

    private function ujiWeb(): void
    {
        CLI::write('== Halaman web & syarat restore ==', 'yellow');
        BackupSistem::pakaiKoneksi($this->uji);
        $db   = BackupSistem::buatDb('manual', 'ZZUJI Backup'); // backup asli database percobaan (berkas retensi di atas = tiruan)
        $html = $this->web('index');
        $this->cek('halaman: tombol backup, daftar, Unduh/Pulihkan, peringatan cron & maintenance', is_string($html) && str_contains($html, 'Backup database')
            && str_contains($html, 'Daftar backup') && str_contains($html, 'Pulihkan') && str_contains($html, 'Backup database terakhir') && ! str_contains($html, 'cron') &&str_contains($html, 'Aktifkan Mode Maintenance dulu'));
        $r = $this->web('unduhForm', [], ['kunci' => $db['kunci'] ?? '']);
        $this->cek('halaman unduh meminta sandi', is_string($r) && str_contains($r, 'name="sandi"'));
        $r = $this->web('unduh', ['kunci' => $db['kunci'] ?? '', 'sandi' => 'salah'], [], 'POST');
        $this->cek('unduh sandi salah → kembali + "Sandi salah"', $r instanceof \CodeIgniter\HTTP\RedirectResponse && session('error') === 'Sandi salah.');
        $r = $this->web('unduh', ['kunci' => $db['kunci'] ?? '', 'sandi' => self::SANDI], [], 'POST');
        $this->cek('unduh sandi benar → berkas', $r instanceof \CodeIgniter\HTTP\DownloadResponse, get_debug_type($r));

        $isi = ['kunci' => $db['kunci'] ?? '', 'sandi' => self::SANDI, 'kata' => 'PULIHKAN'];
        $r   = $this->web('pulihkan', $isi, [], 'POST');
        $this->cek('restore saat maintenance MATI → ditolak', $r instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains((string) session('error'), 'Mode Maintenance'));
        ModeMaintenance::simpan(true, '', '', 'ZZUJI Backup');
        $r = $this->web('pulihkan', ['sandi' => 'salah'] + $isi, [], 'POST');
        $this->cek('restore sandi salah → ditolak', str_contains((string) session('error'), 'Sandi salah'));
        $r = $this->web('pulihkan', ['kata' => 'pulih'] + $isi, [], 'POST');
        $this->cek('restore tanpa kata PULIHKAN → ditolak', str_contains((string) session('error'), 'PULIHKAN'));
        $r = $this->web('pulihkan', $isi, [], 'POST');
        $loc = $r instanceof \CodeIgniter\HTTP\RedirectResponse ? $r->getHeaderLine('Location') : '';
        $id  = preg_match('~pulihkan/([a-f0-9]{16})$~', $loc, $m) ? $m[1] : '';
        $this->cek('restore sah → dialihkan ke halaman kemajuan', $id !== '', $loc . ' ' . session('error'));
        $html = html_entity_decode((string) $this->web('kemajuan', [], [], 'GET', $id), ENT_QUOTES | ENT_HTML5, 'UTF-8'); // esc(attr) menulis "/" sebagai &#x2F;
        $this->cek('halaman kemajuan: <progress> + pulihkan.js + data-url langkah', str_contains($html, '<progress') && str_contains($html, 'pulihkan.js')
            && str_contains($html, $id . '/langkah'));
        $j = [];
        for ($i = 0; $i < 200; $i++) {
            $r = $this->web('langkah', [], [], 'POST', $id);
            $j = json_decode((string) $r->getBody(), true) ?: [];
            if (! in_array($j['tahap'] ?? '', ['siap', 'jalan'], true)) {
                break;
            }
        }
        $this->cek('POST langkah berulang → JSON tahap selesai, persen 100, alamat', ($j['tahap'] ?? '') === 'selesai' && ($j['persen'] ?? 0) === 100 && str_contains((string) ($j['alamat'] ?? ''), $id), json_encode($j));
        $this->fx['web_pulihkan_langkah_selesai'] = $j;
        $html = $this->web('kemajuan', [], [], 'GET', $id);
        $this->cek('halaman hasil: "Restore selesai" + ajakan matikan maintenance, tanpa skrip', is_string($html) && str_contains($html, 'Restore selesai')
            && str_contains($html, 'Ke Mode Maintenance') && ! str_contains($html, 'pulihkan.js'));
        $this->cek('Audit Log: restore SELESAI tercatat', db_connect()->table('audit_log')->where('tabel', 'backup')->like('deskripsi', 'Restore SELESAI')->countAllResults() >= 1);
        ModeMaintenance::simpan(false, '', '', 'ZZUJI Backup');
        BackupSistem::pakaiKoneksi(null);
    }

    private function ujiApi(): void
    {
        CLI::write('== API ==', 'yellow');
        $S = \App\Controllers\Api\Admin\Sistem::class;
        $this->cek('GET/POST sistem/backup oleh Operator → 403', $this->api($S, 'backup', 'operator')[0] === 403 && $this->api($S, 'buatBackup', 'operator', ['isi' => 'db'])[0] === 403);
        [$k, $j] = $this->api($S, 'backup', 'admin');
        $this->cek('GET sistem/backup → backup[] + total_ukuran + disimpan + maintenance_aktif', $k === 200 && is_array($j['data']['backup'] ?? null) && isset($j['data']['disimpan']['otomatis'])
            && array_key_exists('otomatis_terakhir', $j['data']) && ($j['data']['terakhir'] ?? null) !== null && ($j['data']['hari_sejak_terakhir'] ?? null) === 0
            && ($j['data']['ingatkan_hari'] ?? 0) === BackupSistem::INGATKAN_HARI);
        $this->fx['sistem_backup'] = $this->potong($j);
        [$k] = $this->api($S, 'buatBackup', 'admin', ['isi' => 'apa']);
        $this->cek('POST isi tidak dikenal → 422', $k === 422);
        BackupSistem::pakaiKoneksi($this->uji);
        [$k, $j] = $this->api($S, 'buatBackup', 'admin', ['isi' => 'db']);
        BackupSistem::pakaiKoneksi(null);
        $this->cek('POST {isi:"db"} → dibuat + daftar baru', $k === 200 && str_starts_with((string) ($j['data']['dibuat']['kunci'] ?? ''), 'db-'));
        $this->fx['sistem_backup_post'] = $this->potong($j);
        [$k, $j] = $this->api($S, 'hapusBackup', 'admin', ['kunci' => $j['data']['dibuat']['kunci'] ?? '']);
        $this->cek('POST sistem/backup/hapus → 200; kunci asing → 404', $k === 200 && $this->api($S, 'hapusBackup', 'admin', ['kunci' => '../x'])[0] === 404);
    }

    private function ujiHak(): void
    {
        CLI::write('== Hak (khusus Admin) ==', 'yellow');
        $ok = HakAkses::boleh('admin', 'admin/sistem/backup') && HakAkses::boleh('admin', 'admin/sistem/backup/pulihkan', 'POST');
        foreach (['operator', 'hubin', 'guru', ''] as $p) {
            foreach (['admin/sistem/backup', 'admin/sistem/backup/unduh', 'admin/sistem/backup/pulihkan/0123456789abcdef'] as $a) {
                $ok = $ok && ! HakAkses::boleh($p, $a) && ! HakAkses::boleh($p, $a, 'POST');
            }
        }
        $this->cek('web: hanya Admin', $ok);
        $this->cek('API sistem/backup hanya Admin', HakAkses::bolehApiAlamat('admin', 'sistem/backup') && ! HakAkses::bolehApiAlamat('operator', 'sistem/backup'));
        $item = array_values(array_filter(array_merge(...array_column(\App\Libraries\MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => $it[0] === 'admin/sistem/backup'))[0] ?? null;
        $this->cek('menu "Backup & Restore" aktif (tidak lagi Segera)', $item !== null && empty($item[4]));
    }

    // =================================================================

    /** @return list<string> nama tabel yang isinya beda (CHECKSUM) atau hanya ada di salah satu */
    private function bandingkan(BaseConnection $a, BaseConnection $b): array
    {
        $tabel = static fn (BaseConnection $c): array => array_map(static fn (array $r): string => (string) array_values($r)[0],
            $c->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray());
        $ta   = $tabel($a);
        $tb   = $tabel($b);
        $beda = array_values(array_merge(array_diff($ta, $tb), array_diff($tb, $ta)));
        foreach (array_intersect($ta, $tb) as $t) {
            $ca = $a->query('CHECKSUM TABLE `' . $t . '`')->getRowArray()['Checksum'] ?? null;
            $cb = $b->query('CHECKSUM TABLE `' . $t . '`')->getRowArray()['Checksum'] ?? null;
            if ($ca !== $cb) {
                $beda[] = $t;
            }
        }

        return $beda;
    }

    private function potong(array $j): array
    {
        if (isset($j['data']['backup'])) {
            $j['data']['backup'] = array_slice($j['data']['backup'], 0, 3);
        }

        return $j;
    }

    private function tulisFixture(): void
    {
        $dir = (string) getenv('UJI_FIXTURE');
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }
        foreach ($this->fx as $nama => $isi) {
            file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
    }

    private function web(string $metode, array $post = [], array $get = [], string $http = 'POST', string ...$arg): mixed
    {
        $http    = $post === [] && $metode !== 'langkah' && $metode !== 'pulihkan' ? 'GET' : $http;
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/sistem/backup'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        \Config\Services::injectMock('request', $request);
        session()->set(['isLoggedIn' => true, 'admin' => ['id' => $this->adminUji, 'full_name' => 'ZZUJI Backup', 'username' => 'zzujibackup', 'role' => 'admin']]);
        session()->remove(['success', 'error']);
        $request->setGlobal('post', $post);
        $request->setGlobal('get', $get);
        $c = new \App\Controllers\Admin\Backup();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $kelas, string $metode, string $peran, ?array $json = null): array
    {
        ApiAuth::set(['id' => $peran === 'admin' ? $this->adminUji : 3, 'full_name' => 'ZZUJI ' . $peran, 'username' => 'zzujibackup', 'role' => $peran]);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/sistem/backup'), null, new \CodeIgniter\HTTP\UserAgent()))
            ->withMethod($json !== null ? 'POST' : 'GET');
        if ($json !== null) {
            $request->setHeader('Content-Type', 'application/json');
            $request->setBody(json_encode($json));
        }
        \Config\Services::injectMock('request', $request);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}();
        ApiAuth::clear();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }
}
