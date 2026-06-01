<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * NIP/NUPTK jadi opsional (boleh kosong) — sebagian guru belum punya NUPTK/NIS.
 * Kolom dibuat NULL agar indeks UNIQUE tetap mengizinkan banyak baris tanpa NIP
 * (MySQL: UNIQUE membolehkan banyak NULL, tetapi tidak banyak string kosong).
 */
class MakeNipNullable extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('submissions', [
            'nip_nuptk' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('submissions', [
            'nip_nuptk' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => false],
        ]);
    }
}
