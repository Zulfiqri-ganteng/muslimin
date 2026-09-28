<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aturan notifikasi jadwal guru bisa dibatasi per SHIFT KBM:
 *   notif_aturan.shift — 'semua' (pagi + siang, bawaan), 'pagi', atau 'siang'.
 *
 * Aturan yang sudah ada otomatis 'semua' → perilakunya tidak berubah.
 */
class NotifAturanShift extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('shift', 'notif_aturan')) {
            $this->forge->addColumn('notif_aturan', [
                'shift' => [
                    'type'       => 'ENUM',
                    'constraint' => ['semua', 'pagi', 'siang'],
                    'default'    => 'semua',
                    'after'      => 'jurusan',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('shift', 'notif_aturan')) {
            $this->forge->dropColumn('notif_aturan', 'shift');
        }
    }
}
