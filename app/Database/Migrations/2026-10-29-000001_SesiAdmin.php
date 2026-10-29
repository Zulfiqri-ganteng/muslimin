<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sistem → Sesi Aktif (permintaan user 2026-10-29; rancangan docs/DESAIN-ALAT-SISTEM.md F5). Jejak login WEB per sesi supaya Admin
 * bisa melihat siapa sedang masuk dan mengeluarkannya. Sesi disimpan di berkas (FileHandler), jadi di sini hanya SIDIK sesi
 * (sha256 id sesi, bukan id sesi mentah). Sesi Android = tabel api_tokens yang sudah ada.
 *
 * Idempoten, tanpa data pribadi selain IP & nama peramban. Baris > 30 hari dihapus otomatis oleh aplikasi.
 */
class SesiAdmin extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('sesi_admin')) {
            return;
        }
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sesi_hash'     => ['type' => 'CHAR', 'constraint' => 64],
            'ip'            => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'perangkat'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'masuk_at'      => ['type' => 'DATETIME'],
            'aktif_at'      => ['type' => 'DATETIME'],
            'keluar_at'     => ['type' => 'DATETIME', 'null' => true],
            'keluar_alasan' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'dikeluarkan_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sesi_hash');
        $this->forge->addKey('admin_id');
        $this->forge->addKey('aktif_at');
        $this->forge->createTable('sesi_admin', true);
    }

    public function down()
    {
        $this->forge->dropTable('sesi_admin', true);
    }
}
