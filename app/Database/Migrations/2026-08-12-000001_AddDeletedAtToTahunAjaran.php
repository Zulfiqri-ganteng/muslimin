<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tahun_ajaran belum punya soft delete — selaraskan dengan konvensi mayoritas
 * tabel master (jurusan, guru, mata_pelajaran, dst) agar tahun ajaran yang
 * sudah dipakai histori (kkm, struktur_kurikulum, dst di modul Pembelajaran)
 * tidak pernah hilang permanen secara tidak sengaja.
 */
class AddDeletedAtToTahunAjaran extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tahun_ajaran', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'is_aktif'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tahun_ajaran', 'deleted_at');
    }
}
