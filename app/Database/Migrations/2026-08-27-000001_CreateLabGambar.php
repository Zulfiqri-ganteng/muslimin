<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Galeri gambar SIMLAB — polimorfik (banyak foto per entitas).
 * Dipakai oleh: aset, kerusakan, perbaikan, lab, sparepart, peminjaman.
 * Semua gambar disimpan sebagai .webp di public/uploads/lab/ (lihat labimage_helper).
 */
class CreateLabGambar extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entitas'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'entitas_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'file'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'urutan'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entitas', 'entitas_id']);
        $this->forge->createTable('lab_gambar', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('lab_gambar', true);
    }
}
