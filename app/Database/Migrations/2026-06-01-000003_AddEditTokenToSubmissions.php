<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menambah kolom edit_token untuk fitur "Link Revisi".
 * Saat admin menandai data sebagai "Perlu Revisi", sistem membuat token unik
 * sehingga guru bisa membuka link khusus (form ter-isi data lama) untuk memperbaiki.
 */
class AddEditTokenToSubmissions extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('edit_token', 'submissions')) {
            $this->forge->addColumn('submissions', [
                'edit_token' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'catatan_admin',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('edit_token', 'submissions')) {
            $this->forge->dropColumn('submissions', 'edit_token');
        }
    }
}
