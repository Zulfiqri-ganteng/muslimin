<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\LogGalat;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji Sistem → Log Error Hosting (docs/DESAIN-ALAT-SISTEM.md F1):
 *   pustaka LogGalat dengan berkas contoh di folder sementara (log aplikasi CodeIgniter + error_log PHP cPanel): daftar berkas,
 *   kunci asing ditolak, urai baris jejak, kelompok galat sama, penjelasan awam, saringan, berkas besar, UTF-8 rusak, bersihkan,
 *   HAPUS satu & HAPUS SEMUA (2026-11-07, hanya di folder sementara); hak (khusus Admin); halaman & unduh dirender lewat controller
 *   memakai log asli laptop (writable/logs — dibaca; membuka halaman ikut membersihkan log laptop > 30 hari, sama dengan di hosting).
 *
 *   php spark dev:uji-log-error
 */
class UjiLogError extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-log-error';
    protected $description = 'Uji Log Error Hosting (pustaka LogGalat, hak Admin, halaman).';

    private int $lulus = 0;
    private int $gagal = 0;
    private string $dir = '';

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
        $this->dir = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'uji_log_error_' . getmypid() . DIRECTORY_SEPARATOR;
        $auditAwal = (int) (db_connect()->table('audit_log')->selectMax('id', 'm')->get()->getRow()->m ?? 0);
        try {
            $this->siapkan();
            $this->ujiPustaka();
            $this->ujiHak();
            $this->ujiHalaman();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            foreach (glob($this->dir . '{,.}*', GLOB_BRACE) ?: [] as $f) {
                is_file($f) && unlink($f);
            }
            is_dir($this->dir) && rmdir($this->dir);
            db_connect()->table('audit_log')->where('id >', $auditAwal)->where('tabel', 'log_error')->delete(); // jejak unduh dari uji
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function siapkan(): void
    {
        mkdir($this->dir, 0777, true);
        $jejakSpark = " 1 SYSTEMPATH\\Database\\BaseConnection.php(908): x()\n 2 SYSTEMPATH\\CLI\\Console.php(47): CodeIgniter\\CLI\\Commands->run('notif:kirim', [])\n 3 SYSTEMPATH\\Boot.php(166): CodeIgniter\\Boot::bootSpark(Object(Config\\Paths))";
        file_put_contents($this->dir . 'log-2026-10-29.log', implode("\n", [
            "ERROR - 2026-10-29 08:16:15 --> Duplicate entry '2033-7' for key 'surat_sekolah_nomor_unik'",
            'in SYSTEMPATH\\Database\\MySQLi\\Connection.php on line 300.',
            " 1 APPPATH\\Libraries\\SuratSekolah.php(164): insert()",
            "ERROR - 2026-10-29 09:00:01 --> Duplicate entry '2034-81' for key 'surat_sekolah_nomor_unik'",
            'CRITICAL - 2026-10-29 05:42:10 --> No such file or directory',
            " 1 SYSTEMPATH\\Database\\MySQLi\\Connection.php(186): mysqli->real_connect('localhost', 'kank', Object(SensitiveParameterValue))",
            $jejakSpark,
            "ERROR - 2026-10-29 13:10:11 --> Halaman \xff\xfe rusak UTF-8",
            '',
        ]));
        file_put_contents($this->dir . 'log-2026-10-20.log', "ERROR - 2026-10-20 10:00:00 --> Too many connections\n");
        file_put_contents($this->dir . 'log-2026-09-01.log', "ERROR - 2026-09-01 10:00:00 --> lama\n");
        file_put_contents($this->dir . 'log-evil.log', "ERROR - 2026-10-29 10:00:00 --> tidak boleh terbaca\n");
        file_put_contents($this->dir . 'error_log', implode("\n", [
            '[29-Oct-2026 06:10:11 UTC] PHP Fatal error:  Uncaught TypeError: hitung(): Argument #1 ($x) must be of type int in /home/k/app.php:12',
            '[29-Oct-2026 06:10:11 UTC] PHP Stack trace:',
            '[29-Oct-2026 06:10:11 UTC] PHP   1. {main}() /home/k/index.php:0',
            '[29-Oct-2026 07:00:00 UTC] PHP Warning:  Undefined variable $x in /home/k/a.php on line 3',
            '',
        ]));
        // Berkas besar: 2,5 MB isian lalu satu galat penanda di ujung.
        $isi = str_repeat("ERROR - 2026-10-28 01:00:00 --> isian panjang " . str_repeat('x', 60) . "\n", 30000);
        file_put_contents($this->dir . 'log-2026-10-28.log', $isi . "CRITICAL - 2026-10-28 23:59:59 --> penanda ujung\n");
    }

    private function lib(): LogGalat
    {
        return new LogGalat($this->dir, ['php-uji' => ['error_log PHP (uji)', $this->dir . 'error_log'], 'php-hilang' => ['tidak ada', $this->dir . 'tidak_ada']]);
    }

    private function ujiPustaka(): void
    {
        CLI::write('== Pustaka LogGalat ==', 'yellow');
        $lib = $this->lib();
        $b   = $lib->berkas();
        $this->cek('daftar: error_log PHP dulu, lalu log aplikasi terbaru di atas; nama asing & berkas tak ada diabaikan',
            array_keys($b) === ['php-uji', 'log-2026-10-29.log', 'log-2026-10-28.log', 'log-2026-10-20.log', 'log-2026-09-01.log'], implode(',', array_keys($b)));
        $this->cek('label log aplikasi berbahasa Indonesia', ($b['log-2026-10-29.log']['label'] ?? '') === 'Log aplikasi 29 Oktober 2026', $b['log-2026-10-29.log']['label'] ?? '');
        $this->cek('kunci asing ditolak (path, nama di luar pola)', $lib->baca('log-evil.log') === null && $lib->baca('../../.env') === null
            && $lib->path('..\\..\\.env') === null && $lib->path('php-hilang') === null && $lib->path('log-2026-10-29.log') !== null);

        $e = $lib->baca('log-2026-10-29.log')['entri'] ?? [];
        $this->cek('log aplikasi: 4 kejadian, tingkat ERROR=galat, CRITICAL=berat', count($e) === 4
            && array_column($e, 'tingkat') === ['galat', 'galat', 'berat', 'galat'], json_encode(array_column($e, 'tingkat')));
        $this->cek('baris jejak ikut kejadiannya (bukan kejadian baru)', str_contains($e[0]['jejak'] ?? '', 'SuratSekolah.php(164)') && ($e[1]['jejak'] ?? 'x') === '');
        $this->cek('asal: jejak lewat spark = perintah terjadwal, lainnya = web', ($e[2]['asal'] ?? '') === 'perintah' && ($e[0]['asal'] ?? '') === 'web');
        $this->cek('UTF-8 rusak dibersihkan (aman di-esc)', mb_check_encoding($e[3]['pesan'] ?? '', 'UTF-8') && str_contains($e[3]['pesan'] ?? '', 'rusak UTF-8'));

        $g = LogGalat::kelompok($e);
        $dup = array_values(array_filter($g, static fn (array $x): bool => str_starts_with($x['pesan'], 'Duplicate entry')))[0] ?? null;
        $this->cek('galat sama beda angka digabung: 2× + jam pertama/terakhir + pesan terakhir', $dup !== null && $dup['jumlah'] === 2
            && $dup['pertama'] === '2026-10-29 08:16:15' && $dup['terakhir'] === '2026-10-29 09:00:01' && str_contains($dup['pesan'], '2034-81'), json_encode($dup));
        $this->cek('kelompok urut kejadian terakhir terbaru di atas', array_column($g, 'terakhir') === ['2026-10-29 13:10:11', '2026-10-29 09:00:01', '2026-10-29 05:42:10'], json_encode(array_column($g, 'terakhir')));
        $db = array_values(array_filter($g, static fn (array $x): bool => $x['tingkat'] === 'berat'))[0] ?? [];
        $this->cek('penjelasan awam: "No such file or directory" + real_connect = database hosting sesaat mati', str_contains((string) ($db['penjelasan'] ?? ''), 'Database hosting sesaat mati'));
        $this->cek('penjelasan: Duplicate entry & Too many connections dikenal, galat asing = null',
            str_contains((string) ($dup['penjelasan'] ?? ''), 'Data kembar') && str_contains((string) LogGalat::penjelasan('Too many connections'), 'Batas koneksi')
            && LogGalat::penjelasan('Halaman rusak UTF-8') === null);

        $this->cek('saring tingkat & kata', count(LogGalat::saring($e, 'berat', '')) === 1 && count(LogGalat::saring($e, '', 'DUPLICATE')) === 2
            && count(LogGalat::saring($e, '', 'real_connect')) === 1 && LogGalat::saring($e, 'peringatan', '') === []);
        $this->cek('hitung per tingkat', $lib->hitung($b['log-2026-10-29.log']) === ['berat' => 1, 'galat' => 3, 'peringatan' => 0, 'info' => 0], json_encode($lib->hitung($b['log-2026-10-29.log'])));

        $p = $lib->baca('php-uji')['entri'] ?? [];
        $this->cek('error_log PHP: "PHP Stack trace" = jejak, 2 kejadian (Fatal=berat, Warning=peringatan)', count($p) === 2
            && array_column($p, 'tingkat') === ['berat', 'peringatan'] && str_contains($p[0]['jejak'] ?? '', '1. {main}()'), json_encode($p));
        $this->cek('waktu error_log UTC → WIB', ($p[0]['waktu'] ?? '') === '2026-10-29 13:10:11', $p[0]['waktu'] ?? '');
        $this->cek('penjelasan TypeError = kesalahan kode', str_contains((string) LogGalat::penjelasan($p[0]['pesan'] ?? ''), 'kode aplikasi'));

        $besar = $lib->baca('log-2026-10-28.log');
        $ujung = end($besar['entri']);
        $this->cek('berkas besar: hanya ujung dibaca, ditandai, baris pertama utuh', $besar['terpotong'] === true && ($ujung['pesan'] ?? '') === 'penanda ujung'
            && count($besar['entri']) < 30000 && str_starts_with($besar['entri'][0]['pesan'] ?? '', 'isian panjang'));
        $this->cek('terbaru(): berkas paling baru diubah', LogGalat::terbaru(['a' => ['diubah' => 5], 'b' => ['diubah' => 9]]) === 'b' && LogGalat::terbaru([]) === null);

        $n = $lib->bersihkan(30, '2026-10-29');
        $this->cek('bersihkan >30 hari: hanya log 1 Sep terhapus; log baru & error_log PHP tetap', $n === 1 && ! is_file($this->dir . 'log-2026-09-01.log')
            && is_file($this->dir . 'log-2026-10-20.log') && is_file($this->dir . 'error_log') && is_file($this->dir . 'log-evil.log'), (string) $n);

        // 2026-11-07: penjelasan pesan notif:kirim + hapus satu / hapus semua.
        $this->cek('penjelasan: pesan lama notif:kirim "database sedang tidak tersedia" = restart rutin, boleh diabaikan',
            str_contains((string) LogGalat::penjelasan('notif:kirim dilewati: database sedang tidak tersedia; notifikasi menyusul'), 'Boleh diabaikan'));
        $this->cek('penjelasan: pesan baru "tidak tersedia 3 menit berturut-turut" = mati beberapa menit',
            str_contains((string) LogGalat::penjelasan('notif:kirim: database hosting tidak tersedia 3 menit berturut-turut'), 'beberapa menit berturut-turut'));
        $this->cek('hapus kunci asing (../../.env, log-evil.log) → false, tak ada yang terhapus', ! $lib->hapus('../../.env') && ! $lib->hapus('log-evil.log')
            && is_file($this->dir . 'log-evil.log'));
        $this->cek('hapus satu berkas (log 20 Okt) → true, berkas hilang, lainnya tetap', $lib->hapus('log-2026-10-20.log') && ! is_file($this->dir . 'log-2026-10-20.log')
            && is_file($this->dir . 'error_log') && isset($lib->berkas()['log-2026-10-29.log']));
        $h = $lib->hapusSemua();
        $this->cek('hapus semua → semua berkas daftar (termasuk error_log PHP) hilang, gagal kosong; berkas di luar daftar tetap', $h['terhapus'] >= 2 && $h['gagal'] === []
            && $lib->berkas() === [] && ! is_file($this->dir . 'error_log') && is_file($this->dir . 'log-evil.log'), json_encode($h));
    }

    private function ujiHak(): void
    {
        CLI::write('== Hak (khusus Admin) ==', 'yellow');
        $this->cek('Admin boleh buka, unduh, bersihkan, hapus, hapus semua', HakAkses::boleh('admin', 'admin/sistem/log-error') && HakAkses::boleh('admin', 'admin/sistem/log-error/unduh')
            && HakAkses::boleh('admin', 'admin/sistem/log-error/bersihkan', 'POST') && HakAkses::boleh('admin', 'admin/sistem/log-error/hapus', 'POST')
            && HakAkses::boleh('admin', 'admin/sistem/log-error/hapus-semua', 'POST'));
        $tolak = true;
        foreach (['operator', 'hubin', 'guru', ''] as $p) {
            $tolak = $tolak && ! HakAkses::boleh($p, 'admin/sistem/log-error') && ! HakAkses::boleh($p, 'admin/sistem/log-error/unduh')
                && ! HakAkses::boleh($p, 'admin/sistem/log-error/bersihkan', 'POST') && ! HakAkses::boleh($p, 'admin/sistem/log-error/hapus', 'POST')
                && ! HakAkses::boleh($p, 'admin/sistem/log-error/hapus-semua', 'POST');
        }
        $this->cek('Operator, Waka Hubin, Guru, tanpa peran ditolak', $tolak);
        $item = array_values(array_filter(array_merge(...array_column(\App\Libraries\MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => $it[0] === 'admin/sistem/log-error'))[0] ?? null;
        $this->cek('menu "Log Error Hosting" aktif (tidak lagi bertanda Segera)', $item !== null && empty($item[4]));
    }

    private function ujiHalaman(): void
    {
        CLI::write('== Halaman (log asli laptop, hanya dibaca) ==', 'yellow');
        $asli = (new LogGalat())->berkas();
        $html = $this->panggil('index', []);
        $asli = (new LogGalat())->berkas(); // sesudah bersih otomatis > 30 hari
        $this->cek('halaman tampil: judul + daftar berkas + tombol Hapus semua log, Hapus & ikon hapus per baris',
            is_string($html) && str_contains($html, 'Log Error Hosting') && ($asli === [] || (str_contains($html, 'Semua berkas log') && str_contains($html, 'Hapus semua log')
            && str_contains($html, 'admin/sistem/log-error/hapus"') && str_contains($html, 'Lihat'))) && ! str_contains($html, 'Bersihkan &gt;30 hari'),
            is_string($html) ? '' : get_debug_type($html));
        if ($asli !== []) {
            $k    = (string) array_key_last($asli);
            $html = $this->panggil('index', ['berkas' => $k, 'tingkat' => 'berat', 'q' => 'zz-tak-ada-zz']);
            $this->cek('saringan tanpa hasil → "Tidak ada galat yang cocok" + Reset', is_string($html) && str_contains($html, 'Tidak ada galat yang cocok') && str_contains($html, 'Reset'));
            $r = $this->panggil('unduh', ['berkas' => $k]);
            $this->cek('unduh berkas daftar → DownloadResponse', $r instanceof \CodeIgniter\HTTP\DownloadResponse, get_debug_type($r));
        }
        $r = $this->panggil('unduh', ['berkas' => '../../.env']);
        $this->cek('unduh kunci asing → dialihkan, bukan berkas', $r instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($r->getHeaderLine('Location'), 'admin/sistem/log-error'));
        $html = $this->panggil('index', ['berkas' => '../../.env']);
        $this->cek('kunci asing di halaman → pakai berkas terbaru, tanpa galat', is_string($html) && ! str_contains($html, 'encryption.key'));
        $jml = count((new LogGalat())->berkas());
        $r   = $this->panggil('hapus', [], ['berkas' => '../../.env']);
        $this->cek('POST hapus kunci asing → dialihkan + pesan galat, tak ada berkas terhapus', $r instanceof \CodeIgniter\HTTP\RedirectResponse
            && str_contains((string) session('error'), 'tidak ditemukan') && count((new LogGalat())->berkas()) === $jml && is_file(ROOTPATH . '.env'));
    }

    private function panggil(string $metode, array $get, array $post = []): mixed
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/sistem/log-error'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => 1, 'full_name' => 'ZZUJI Log', 'username' => 'zzujilog', 'role' => 'admin']);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Admin\LogError();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}();
    }
}
