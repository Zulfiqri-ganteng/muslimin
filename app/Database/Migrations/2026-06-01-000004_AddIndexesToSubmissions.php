<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menambah indeks untuk optimasi query daftar & statistik
 * (filter status_kepegawaian, urut/rentang created_at).
 * Nama tabel & indeks hardcoded (tanpa input pengguna) — aman.
 */
class AddIndexesToSubmissions extends Migration
{
    public function up()
    {
        foreach ([
            'idx_status_kepegawaian' => 'status_kepegawaian',
            'idx_created_at'         => 'created_at',
        ] as $name => $col) {
            if (! $this->indexExists($name)) {
                $this->db->query("ALTER TABLE `submissions` ADD INDEX `{$name}` (`{$col}`)");
            }
        }
    }

    public function down()
    {
        foreach (['idx_status_kepegawaian', 'idx_created_at'] as $name) {
            if ($this->indexExists($name)) {
                $this->db->query("ALTER TABLE `submissions` DROP INDEX `{$name}`");
            }
        }
    }

    private function indexExists(string $name): bool
    {
        return (bool) $this->db->query(
            "SHOW INDEX FROM `submissions` WHERE Key_name = " . $this->db->escape($name)
        )->getRow();
    }
}
