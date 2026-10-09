<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — CEKLIS KOREKSI ("KOREKSI NILAI": pembagian lembar jawaban ke guru per rombel).
 *
 *   1. honor_koreksi_kelas  jumlah PESERTA ujian tiap kelas untuk satu honor (default = siswa aktif, boleh diketik).
 *   2. honor_koreksi_mapel  satu baris = satu guru penerima honor + satu mata pelajaran yang ia koreksi
 *                           ("-" = guru terdaftar di ceklis tanpa mapel). Mapel ditulis sebagai teks supaya bisa
 *                           datang dari Excel sekolah; mapel_id hanya tautan bila namanya dikenal.
 *   3. honor_koreksi_sel    kelas yang dikoreksi pada baris mapel itu. jumlah NULL = pakai jumlah peserta kelas;
 *                           terisi = angka khusus sel itu (agar Excel yang diimpor tercetak persis).
 *
 * Jumlah lembar guru = Σ jumlah sel semua baris mapelnya; itulah angka "Koreksi" di honor. Semua tabel ikut terhapus
 * bila honor dihapus (CASCADE). Tidak ada nama orang di migrasi ini (repo publik). Idempoten.
 * Rancangan: docs/DESAIN-HONOR.md bagian 11.
 */
class HonorKoreksi extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('honor_koreksi_kelas')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'dokumen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'kelas_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'peserta'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
                'manual'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['dokumen_id', 'kelas_id'], 'koreksi_kelas_unik');
            $this->forge->addKey('kelas_id');
            $this->forge->addForeignKey('dokumen_id', 'honor_dokumen', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_koreksi_kelas', true, $this->attr);
        }

        if (! $this->db->tableExists('honor_koreksi_mapel')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'dokumen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'baris_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'mapel_nama' => ['type' => 'VARCHAR', 'constraint' => 150],
                'mapel_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'urut'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['dokumen_id', 'baris_id'], false, false, 'koreksi_mapel_baris');
            $this->forge->addKey('mapel_id');
            $this->forge->addForeignKey('dokumen_id', 'honor_dokumen', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('baris_id', 'honor_baris', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
            $this->forge->createTable('honor_koreksi_mapel', true, $this->attr);
        }

        if (! $this->db->tableExists('honor_koreksi_sel')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'mapel_row_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'kelas_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'jumlah'     => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['mapel_row_id', 'kelas_id'], 'koreksi_sel_unik');
            $this->forge->addKey('kelas_id');
            $this->forge->addForeignKey('mapel_row_id', 'honor_koreksi_mapel', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_koreksi_sel', true, $this->attr);
        }
    }

    public function down()
    {
        foreach (['honor_koreksi_sel', 'honor_koreksi_mapel', 'honor_koreksi_kelas'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
    }
}
