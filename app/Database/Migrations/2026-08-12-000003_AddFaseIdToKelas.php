<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kelas.fase_id (FK -> fase, SET NULL) + seed nilai awal berdasar
 * tingkat (X -> Fase E, XI/XII -> Fase F, standar SMK). Kolom ini tetap
 * bisa diedit manual per kelas lewat form Kelas bila ada pengecualian.
 *
 * FK ditambah lewat raw SQL (bukan Forge::addForeignKey) karena constraint
 * ini dipasang ke tabel yang SUDAH ADA (ALTER), bukan saat createTable().
 */
class AddFaseIdToKelas extends Migration
{
    public function up()
    {
        $this->forge->addColumn('kelas', [
            'fase_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'tingkat'],
        ]);

        $this->db->query(
            'ALTER TABLE `kelas` ADD CONSTRAINT `kelas_fase_id_foreign` '
            . 'FOREIGN KEY (`fase_id`) REFERENCES `fase` (`id`) ON DELETE SET NULL'
        );

        $faseE = $this->db->table('fase')->select('id')->where('kode', 'E')->get()->getRowArray();
        $faseF = $this->db->table('fase')->select('id')->where('kode', 'F')->get()->getRowArray();

        if ($faseE) {
            $this->db->table('kelas')->where('tingkat', 'X')->update(['fase_id' => $faseE['id']]);
        }
        if ($faseF) {
            $this->db->table('kelas')->whereIn('tingkat', ['XI', 'XII'])->update(['fase_id' => $faseF['id']]);
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `kelas` DROP FOREIGN KEY `kelas_fase_id_foreign`');
        $this->forge->dropColumn('kelas', 'fase_id');
    }
}
