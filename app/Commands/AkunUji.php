<?php

namespace App\Commands;

use App\Libraries\HakPeran;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Akun uji SEMENTARA untuk alat uji peramban (tools/uji-peramban.mjs): satu akun per peran (zzuji_<kode peran>), sandi acak
 * baru setiap kali dibuat. Hanya mau jalan bila app.baseURL menunjuk ke laptop (localhost / 127.0.0.1), jadi tidak mungkin
 * membuat akun di hosting.
 *
 *   php spark dev:akun-uji buat    → cetak satu baris JSON {"sandi": "...", "akun": {"admin": "zzuji_admin", …}}
 *   php spark dev:akun-uji hapus   → buang semua akun zzuji_*
 */
class AkunUji extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:akun-uji';
    protected $description = 'Buat/hapus akun uji sementara (satu per peran, sandi acak) untuk uji peramban — hanya di laptop.';
    protected $usage       = 'dev:akun-uji buat|hapus';

    public function run(array $params)
    {
        $aksi = $params[0] ?? '';
        if (! in_array($aksi, ['buat', 'hapus'], true)) {
            CLI::error('Pakai: php spark dev:akun-uji buat|hapus');

            return EXIT_USER_INPUT;
        }
        $host = (string) parse_url((string) config('App')->baseURL, PHP_URL_HOST);
        if (! in_array($host, ['localhost', '127.0.0.1'], true)) {
            CLI::error('Ditolak: app.baseURL bukan laptop (' . $host . '). Akun uji hanya untuk uji lokal.');

            return EXIT_ERROR;
        }

        $db = db_connect();
        $db->table('admins')->like('username', 'zzuji_', 'after')->delete();
        if ($aksi === 'hapus') {
            CLI::write('Akun uji dihapus.');

            return EXIT_SUCCESS;
        }

        $peran = $db->tableExists('peran')
            ? array_column($db->table('peran')->select('kode')->orderBy('urut')->get()->getResultArray(), 'kode')
            : array_keys(HakPeran::bawaan());
        $sandi = 'Uji-' . bin2hex(random_bytes(8));
        $hash  = password_hash($sandi, PASSWORD_DEFAULT);
        $now   = date('Y-m-d H:i:s');
        $akun  = [];
        foreach ($peran as $kode) {
            $user = 'zzuji_' . $kode;
            $db->table('admins')->insert([
                'full_name' => 'Uji ' . $kode, 'email' => $user . '@example.test', 'username' => $user, 'password' => $hash,
                'role'      => $kode, 'aktif' => 1, 'wajib_ganti_sandi' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $akun[$kode] = $user;
        }
        CLI::write(json_encode(['sandi' => $sandi, 'akun' => $akun]));

        return EXIT_SUCCESS;
    }
}
