<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * settings.absensi_publik : toggle apakah halaman Absensi Guru boleh dilihat
 * publik (terpisah dari jadwal_publik agar admin bisa atur mandiri).
 */
class AddAbsensiPublik extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('absensi_publik', 'settings')) {
            $this->forge->addColumn('settings', [
                'absensi_publik' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'jadwal_publik'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('absensi_publik', 'settings')) {
            $this->forge->dropColumn('settings', 'absensi_publik');
        }
    }
}
