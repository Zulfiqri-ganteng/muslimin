<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SKBM — Surat Keputusan Pembagian Tugas Mengajar (Lampiran 3), per TAHUN AJARAN.
 *
 *   1. skbm_mapel  satu baris = satu guru + satu mata pelajaran yang ia ampu pada tahun ajaran itu ("-" = guru terdaftar
 *                  tanpa mapel). Mapel ditulis sebagai teks supaya bisa datang dari Excel sekolah; mapel_id hanya
 *                  tautan bila namanya dikenal. kode = kode guru di SK (mis. "3A"), urut = urutan baris di SK.
 *   2. skbm_sel    kelas (rombel) yang diajar pada baris itu + JP (jam pelajaran per minggu; NULL = belum diisi).
 *
 * Sengaja TERPISAH dari tabel pengampu (dipakai Jadwal KBM, Rekap Beban, Cetak, API): SKBM adalah salinan SK resmi yang
 * dipegang per tahun ajaran dan menjadi sumber ceklis Koreksi honor; menyelaraskannya ke pengampu adalah keputusan
 * tersendiri. Tidak ada nama orang atau data pribadi di migrasi ini (repo publik). Idempoten.
 * Rancangan: docs/DESAIN-SKBM.md.
 */
class Skbm extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('skbm_mapel')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'tahun_ajaran' => ['type' => 'VARCHAR', 'constraint' => 9],
                'guru_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'mapel_nama'   => ['type' => 'VARCHAR', 'constraint' => 150],
                'mapel_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'kode'         => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
                'urut'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tahun_ajaran', 'guru_id'], false, false, 'skbm_mapel_tahun_guru');
            $this->forge->addKey('mapel_id');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
            $this->forge->createTable('skbm_mapel', true, $this->attr);
        }

        if (! $this->db->tableExists('skbm_sel')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'skbm_mapel_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'kelas_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'jp'            => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['skbm_mapel_id', 'kelas_id'], 'skbm_sel_unik');
            $this->forge->addKey('kelas_id');
            $this->forge->addForeignKey('skbm_mapel_id', 'skbm_mapel', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
            $this->forge->createTable('skbm_sel', true, $this->attr);
        }
    }

    public function down()
    {
        foreach (['skbm_sel', 'skbm_mapel'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
    }
}
