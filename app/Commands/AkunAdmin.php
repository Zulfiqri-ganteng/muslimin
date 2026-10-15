<?php

namespace App\Commands;

use App\Libraries\HakPeran;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Buat akun Admin baru, atau atur ulang sandi akun yang sudah ada, lewat Terminal server (mis. lupa sandi Admin).
 * Username & sandi HANYA diketik di Terminal — tidak pernah ditulis ke kode/repo (repo publik). Yang tersimpan di DB hanya hash.
 *
 *   php spark akun:admin <username> <sandi>      (di hosting: phpm spark akun:admin …)
 *
 * - Username belum ada → akun baru peran Admin, aktif.
 * - Username/email sudah ada → sandinya diganti, akun diaktifkan, perannya dijadikan Admin.
 * - Kunci "terlalu banyak percobaan login" untuk username itu dibuka; tercatat di Audit Log.
 */
class AkunAdmin extends BaseCommand
{
    protected $group       = 'akun';
    protected $name        = 'akun:admin';
    protected $description = 'Buat akun Admin atau atur ulang sandinya (untuk lupa sandi Admin).';
    protected $usage       = 'akun:admin <username> <sandi>';

    public function run(array $params)
    {
        $user  = trim((string) ($params[0] ?? ''));
        $sandi = (string) ($params[1] ?? '');
        if ($user === '' || $sandi === '') {
            CLI::error('Pakai: php spark akun:admin <username> <sandi>');

            return EXIT_USER_INPUT;
        }
        if (! preg_match('/^[A-Za-z0-9._-]{3,100}$/', $user)) {
            CLI::error('Username hanya boleh huruf, angka, titik, minus, garis bawah (3–100 karakter).');

            return EXIT_USER_INPUT;
        }
        if (mb_strlen($sandi) < 6) {
            CLI::error('Sandi minimal 6 karakter.');

            return EXIT_USER_INPUT;
        }

        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $hash = password_hash($sandi, PASSWORD_DEFAULT);
        $ada  = $db->table('admins')->groupStart()->where('username', $user)->orWhere('email', $user)->groupEnd()->get()->getRowArray();

        if ($ada) {
            $db->table('admins')->where('id', (int) $ada['id'])->update([
                'password' => $hash, 'role' => HakPeran::ADMIN, 'aktif' => 1, 'wajib_ganti_sandi' => 0, 'updated_at' => $now,
            ]);
            $id    = (int) $ada['id'];
            $pesan = 'Sandi akun "' . $ada['username'] . '" diganti' . ($ada['role'] !== HakPeran::ADMIN ? ' dan perannya dijadikan Admin' : '') . '.';
        } else {
            $email = $user . '@admin.local';
            if ($db->table('admins')->where('email', $email)->countAllResults() > 0) {
                $email = $user . '.' . bin2hex(random_bytes(3)) . '@admin.local';
            }
            $db->table('admins')->insert([
                'full_name' => $user, 'email' => $email, 'username' => $user, 'password' => $hash, 'role' => HakPeran::ADMIN,
                'aktif'     => 1, 'wajib_ganti_sandi' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id    = (int) $db->insertID();
            $pesan = 'Akun Admin "' . $user . '" dibuat (email sementara ' . $email . ' — bisa diganti di Profil).';
        }

        // Buka kunci percobaan login gagal untuk username ini (semua alamat IP).
        $db->table('login_attempts')->where('login', mb_strtolower($user))->delete();
        $db->table('audit_log')->insert([
            'admin_id' => null, 'aksi' => 'ubah', 'tabel' => 'admins', 'record_id' => $id,
            'deskripsi' => 'Lewat Terminal (php spark akun:admin): ' . $pesan, 'ip_address' => 'CLI', 'created_at' => $now,
        ]);

        CLI::write($pesan, 'green');
        CLI::write('Silakan masuk di halaman login admin. Sesudah masuk, ganti sandi lewat Profil saya.');

        return EXIT_SUCCESS;
    }
}
