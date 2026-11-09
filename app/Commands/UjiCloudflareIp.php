<?php

namespace App\Commands;

use App\Libraries\LoginThrottle;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

/**
 * Uji IP ASLI pengunjung di balik proxy Cloudflare (Config\App::$proxyIPs, 2026-11-09).
 *
 * Mensimulasikan permintaan (REMOTE_ADDR + header) tanpa server sungguhan:
 *   - lewat Cloudflare (IPv4/IPv6) → IP dari CF-Connecting-IP;
 *   - permintaan LANGSUNG ke IP hosting dengan header palsu → header diabaikan;
 *   - X-Forwarded-For palsu tidak dipercaya; IP di luar rentang tidak dipercaya;
 *   - server (LiteSpeed) yang sudah menukar REMOTE_ADDR tetap benar;
 *   - kunci login (LoginThrottle) per IP ASLI: dibandingkan keadaan "sebelum" (tanpa daftar) — DALAM TRANSAKSI yang dibatalkan.
 *
 *   php spark dev:uji-cloudflare-ip
 */
class UjiCloudflareIp extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-cloudflare-ip';
    protected $description = 'Uji IP asli pengunjung di balik Cloudflare (proxyIPs + kunci login per IP).';

    /** IP server Cloudflare contoh (dalam 172.64.0.0/13) & pengunjung contoh. */
    private const CF    = '172.70.10.5';
    private const CF6   = '2400:cb00:2049:1::a29f:1804';
    private const ASLI  = '114.12.1.92';
    private const ASLI2 = '36.68.1.2';

    /** Daftar resmi https://www.cloudflare.com/ips-v4 & ips-v6 (dicek 2026-11-09). */
    private const RESMI = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . mb_substr($detail, 0, 300) : ''), $ok ? 'green' : 'red');
    }

    public function run(array $params)
    {
        $sg   = service('superglobals');
        $asli = $sg->getServerArray();
        $db   = db_connect();

        try {
            CLI::write('== Daftar IP Cloudflare di Config\App ==', 'yellow');
            $daftar = config(App::class)->proxyIPs;
            $this->cek('22 rentang (15 IPv4 + 7 IPv6) = daftar resmi Cloudflare', array_keys($daftar) === self::RESMI, json_encode(array_keys($daftar)));
            $this->cek('semua memakai header CF-Connecting-IP (bukan X-Forwarded-For)', array_unique(array_values($daftar)) === ['CF-Connecting-IP'], json_encode(array_unique(array_values($daftar))));

            CLI::write('== IP yang terbaca ==', 'yellow');
            $this->ip('tanpa Cloudflare (laptop/lokal) → REMOTE_ADDR apa adanya', ['REMOTE_ADDR' => '127.0.0.1'], '127.0.0.1');
            $this->ip('lewat Cloudflare IPv4 → IP asli dari CF-Connecting-IP', ['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => self::ASLI], self::ASLI);
            $this->ip('lewat Cloudflare, pengunjung ber-IPv6 → IPv6 asli', ['REMOTE_ADDR' => '162.158.1.1', 'HTTP_CF_CONNECTING_IP' => '2001:448a:1020::1'], '2001:448a:1020::1');
            $this->ip('server Cloudflare ber-IPv6 (2400:cb00::/32) → IP asli', ['REMOTE_ADDR' => self::CF6, 'HTTP_CF_CONNECTING_IP' => self::ASLI2], self::ASLI2);
            $this->ip('langsung ke IP hosting + CF-Connecting-IP PALSU → header diabaikan', ['REMOTE_ADDR' => '36.80.1.1', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4'], '36.80.1.1');
            $this->ip('X-Forwarded-For palsu "6.6.6.6, …" lewat Cloudflare → tetap IP asli', ['REMOTE_ADDR' => self::CF, 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, ' . self::ASLI, 'HTTP_CF_CONNECTING_IP' => self::ASLI], self::ASLI);
            $this->ip('langsung ke hosting + X-Forwarded-For palsu → diabaikan', ['REMOTE_ADDR' => '36.80.1.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], '36.80.1.1');
            $this->ip('isi CF-Connecting-IP rusak → kembali ke REMOTE_ADDR (tidak galat)', ['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => 'bukan-ip'], self::CF);
            $this->ip('LiteSpeed sudah menukar REMOTE_ADDR ke IP asli → tetap benar', ['REMOTE_ADDR' => self::ASLI, 'HTTP_CF_CONNECTING_IP' => self::ASLI], self::ASLI);
            $this->ip('batas rentang: 104.28.0.1 (di luar 104.24.0.0/14) → header tidak dipercaya', ['REMOTE_ADDR' => '104.28.0.1', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4'], '104.28.0.1');
            $this->ip('batas rentang: 104.27.255.254 (ujung 104.24.0.0/14) → dipercaya', ['REMOTE_ADDR' => '104.27.255.254', 'HTTP_CF_CONNECTING_IP' => self::ASLI], self::ASLI);

            CLI::write('== Kunci login per IP (transaksi dibatalkan) ==', 'yellow');
            $db->transBegin();
            $t   = new LoginThrottle();
            $ipA = $this->baca(['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => self::ASLI]);
            $ipB = $this->baca(['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => self::ASLI2]);
            for ($i = 1; $i <= LoginThrottle::MAX_PER_IP; $i++) {
                $t->hit('zzuji-cf-' . $i, $ipA, 'web');
            }
            $this->cek('pengunjung A salah sandi 30× → A terkunci', $t->retryAfter('zzuji-cf-baru', $ipA) > 0);
            $this->cek('pengunjung B lewat server Cloudflare yang SAMA → TIDAK ikut terkunci', $t->retryAfter('zzuji-cf-baru', $ipB) === 0, $ipA . ' / ' . $ipB);

            $tanpa          = clone config(App::class);
            $tanpa->proxyIPs = [];
            $ipB0           = $this->baca(['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => self::ASLI2], $tanpa);
            $this->cek('pembanding SEBELUM perbaikan: tanpa daftar, B terbaca IP Cloudflare', $ipB0 === self::CF, $ipB0);
            $db->transRollback();
            $db->transBegin();
            for ($i = 1; $i <= LoginThrottle::MAX_PER_IP; $i++) {
                $t->hit('zzuji-cf-' . $i, $this->baca(['REMOTE_ADDR' => self::CF, 'HTTP_CF_CONNECTING_IP' => self::ASLI], $tanpa), 'web');
            }
            $this->cek('… dan B ikut terkunci (masalah yang dicegah perbaikan ini)', $t->retryAfter('zzuji-cf-baru', $ipB0) > 0);
            $db->transRollback();
            $sisa = $db->table('login_attempts')->like('login', 'zzuji-cf-', 'after')->countAllResults();
            $this->cek('tidak ada jejak uji tertinggal di login_attempts', $sisa === 0, (string) $sisa);
        } catch (\Throwable $e) {
            $db->transRollback(); // aman bila tidak ada transaksi aktif
            $this->cek('tanpa galat', false, $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            $sg->setServerArray($asli);
        }

        CLI::newLine();
        CLI::write("Hasil: {$this->lulus} lulus, {$this->gagal} gagal", $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function ip(string $judul, array $server, string $harap): void
    {
        $dapat = $this->baca($server);
        $this->cek($judul, $dapat === $harap, 'dapat ' . $dapat . ', harap ' . $harap);
    }

    /** IP terbaca untuk permintaan baru dengan $_SERVER = $server (header HTTP_* dibaca konstruktor seperti di server sungguhan). */
    private function baca(array $server, ?App $config = null): string
    {
        $config ??= config(App::class);
        service('superglobals')->setServerArray($server);
        $req = new IncomingRequest($config, new SiteURI($config, ''), null, new UserAgent());

        return $req->getIPAddress();
    }
}
