<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ---- Akun admin default ----
        // Username : admin
        // Password : admin123  (WAJIB diganti setelah login pertama)
        $this->db->table('admins')->ignore(true)->insert([
            'full_name'  => 'Administrator',
            'email'      => 'admin@sekolah.sch.id',
            'username'   => 'admin',
            'password'   => password_hash('admin123', PASSWORD_DEFAULT),
            'phone'      => null,
            'photo'      => null,
            'role'       => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ---- Pengaturan / identitas sekolah default (1 baris) ----
        $existing = $this->db->table('settings')->where('id', 1)->get()->getRow();
        if (! $existing) {
            $this->db->table('settings')->insert([
                'id'              => 1,
                'school_name'     => 'NAMA SEKOLAH ANDA',
                'school_level'    => 'SMK',
                'logo'            => null,
                'headmaster_name' => '',
                'headmaster_nip'  => '',
                'city'            => 'Bekasi',
                'academic_year'   => '2026/2027',
                'address'         => '',
                'phone'           => '',
                'email'           => '',
                'website'         => 'zulfiqri.it.com',
                'form_open'       => 1,
                'form_intro'      => 'Surat pernyataan kesediaan guru untuk melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.',
                'updated_at'      => $now,
            ]);
        }
    }
}
