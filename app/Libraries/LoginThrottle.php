<?php

namespace App\Libraries;

/**
 * Proteksi brute-force login (web & API) berbasis sliding window.
 *
 * Kebijakan (window 15 menit):
 *   - Maks 5 percobaan GAGAL per (login + IP)  → kunci sementara akun yang dituju.
 *   - Maks 30 percobaan GAGAL per IP            → kunci IP (mencegah spraying banyak akun).
 *
 * Alur pemakaian di controller Auth:
 *   $lock = (new LoginThrottle())->retryAfter($login, $ip);
 *   if ($lock > 0) { tolak, tampilkan sisa menit }
 *   ... verifikasi password ...
 *   gagal  → $throttle->hit($login, $ip);
 *   sukses → $throttle->clear($login, $ip);
 *
 * Kunci otomatis terbuka begitu percobaan lama keluar dari window (tak perlu cron).
 */
final class LoginThrottle
{
    /** Panjang window pengamatan (detik). */
    public const WINDOW = 900; // 15 menit

    /** Batas percobaan gagal per (login + IP) dalam window. */
    public const MAX_PER_LOGIN = 5;

    /** Batas percobaan gagal per IP dalam window. */
    public const MAX_PER_IP = 30;

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Berapa detik lagi login untuk kombinasi ini masih terkunci.
     *
     * @return int 0 jika diizinkan; >0 = sisa detik lockout.
     */
    public function retryAfter(string $login, string $ip): int
    {
        $login = $this->norm($login);
        $since = date('Y-m-d H:i:s', time() - self::WINDOW);

        // Terkunci karena terlalu banyak gagal pada akun yang dituju dari IP ini.
        $perLogin = $this->rows([
            'login'          => $login,
            'ip_address'     => $ip,
            'attempted_at >=' => $since,
        ]);
        if (count($perLogin) >= self::MAX_PER_LOGIN) {
            return $this->remaining($perLogin);
        }

        // Terkunci karena IP ini menembak banyak akun.
        $perIp = $this->rows([
            'ip_address'     => $ip,
            'attempted_at >=' => $since,
        ]);
        if (count($perIp) >= self::MAX_PER_IP) {
            return $this->remaining($perIp);
        }

        return 0;
    }

    /** Catat satu percobaan GAGAL. */
    public function hit(string $login, string $ip, string $scope = 'web'): void
    {
        $this->db->table('login_attempts')->insert([
            'login'        => $this->norm($login),
            'ip_address'   => $ip,
            'scope'        => $scope,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);

        // Bersih-bersih ringan: buang jejak yang jauh lebih tua dari window.
        $this->db->table('login_attempts')
            ->where('attempted_at <', date('Y-m-d H:i:s', time() - (self::WINDOW * 8)))
            ->delete();
    }

    /** Hapus jejak gagal setelah login berhasil (reset hitungan). */
    public function clear(string $login, string $ip): void
    {
        $this->db->table('login_attempts')
            ->where('login', $this->norm($login))
            ->where('ip_address', $ip)
            ->delete();
    }

    // ---------------- internal ----------------

    private function rows(array $conditions): array
    {
        return $this->db->table('login_attempts')
            ->select('attempted_at')
            ->where($conditions)
            ->orderBy('attempted_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Sisa lockout = window dikurangi umur percobaan terbaru dalam kumpulan. */
    private function remaining(array $rows): int
    {
        $newest = strtotime(end($rows)['attempted_at']);
        $left   = self::WINDOW - (time() - $newest);
        return max($left, 1);
    }

    private function norm(string $login): string
    {
        return mb_strtolower(trim($login));
    }
}
