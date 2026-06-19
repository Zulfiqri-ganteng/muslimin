<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dukungan frontend publik:
 *  - settings.jadwal_publik : toggle apakah jadwal boleh dilihat umum
 *  - tabel pengumuman       : pengumuman yang tampil di beranda publik
 */
class FrontendPublik extends Migration
{
    public function up()
    {
        // toggle privasi jadwal (default tampil)
        if (! $this->db->fieldExists('jadwal_publik', 'settings')) {
            $this->forge->addColumn('settings', [
                'jadwal_publik' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'form_open'],
            ]);
        }

        // tabel pengumuman
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'judul'      => ['type' => 'VARCHAR', 'constraint' => 200],
            'isi'        => ['type' => 'TEXT'],
            'is_aktif'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_aktif', 'deleted_at']);
        $this->forge->createTable('pengumuman', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('pengumuman', true);
        if ($this->db->fieldExists('jadwal_publik', 'settings')) {
            $this->forge->dropColumn('settings', 'jadwal_publik');
        }
    }
}
