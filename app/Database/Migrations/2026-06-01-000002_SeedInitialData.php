<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mengisi data awal (admin default + pengaturan sekolah) langsung lewat migration,
 * sehingga deploy via "php spark migrate" di hosting otomatis punya akun login &
 * baris settings tanpa perlu import SQL manual. Bersifat idempotent (aman dijalankan
 * ulang — tidak menimpa bila data sudah ada).
 */
class SeedInitialData extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // --- Admin default ---
        $adminExists = $this->db->table('admins')->where('username', 'admin')->countAllResults() > 0;
        if (! $adminExists) {
            $this->db->table('admins')->insert([
                'full_name'  => 'Administrator',
                'email'      => 'admin@sekolah.sch.id',
                'username'   => 'admin',
                'password'   => password_hash('admin123', PASSWORD_DEFAULT),
                'role'       => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Pengaturan sekolah default (1 baris, id=1) ---
        $settingExists = $this->db->table('settings')->where('id', 1)->countAllResults() > 0;
        if (! $settingExists) {
            $this->db->table('settings')->insert([
                'id'            => 1,
                'school_name'   => 'NAMA SEKOLAH ANDA',
                'school_level'  => 'SMK',
                'city'          => 'Bekasi',
                'academic_year' => '2026/2027',
                'website'       => 'zulfiqri.site',
                'form_open'     => 1,
                'form_intro'    => 'Surat pernyataan kesediaan guru untuk melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.',
                'updated_at'    => $now,
            ]);
        }
    }

    public function down()
    {
        $this->db->table('admins')->where('username', 'admin')->delete();
        $this->db->table('settings')->where('id', 1)->delete();
    }
}
