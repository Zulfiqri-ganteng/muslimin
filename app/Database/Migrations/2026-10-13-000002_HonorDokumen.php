<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — DOKUMEN HONOR per periode ujian (Fase 2).
 *
 *   honor_dokumen        satu per periode ujian (ujian_periode): judul, tempat/tanggal, nama penanda tangan, status.
 *   honor_dok_komponen   SNAPSHOT komponen & tarif yang berlaku untuk dokumen itu (disalin dari honor_komponen saat
 *                        dokumen dibuat). Mengubah tarif di Pengaturan Honor TIDAK mengubah honor yang sudah dibuat.
 *                        FK ke honor_komponen RESTRICT → komponen yang sudah dipakai sebuah honor tak bisa dihapus.
 *   honor_baris          satu baris per penerima (nama disalin supaya rekap tetap terbaca bila guru diganti/dihapus).
 *   honor_nilai          isian: `nilai` = jumlah (komponen satuan) atau nominal rupiah (komponen tetap);
 *                        `otomatis_nilai` = angka hasil hitung otomatis terakhir (NULL = tidak pernah dihitung otomatis).
 *                        Isian dianggap "diubah manual" bila nilai ≠ otomatis_nilai.
 *
 * TOTAL tidak disimpan: selalu dihitung ulang oleh satu fungsi (Libraries\HonorDokumen::hitung) — tak mungkin
 * layar, Excel, dan PDF memberi angka berbeda. Idempoten. Rancangan: docs/DESAIN-HONOR.md.
 */
class HonorDokumen extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        // ---------- honor_dokumen ----------
        if (! $this->db->tableExists('honor_dokumen')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'periode_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'status'      => ['type' => 'ENUM', 'constraint' => ['draf', 'final', 'dikunci'], 'default' => 'draf'],
                'judul'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'tempat'      => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'tanggal'     => ['type' => 'DATE', 'null' => true],
                'ketua_nama'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'bendahara_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'kepsek_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'dikunci_at'  => ['type' => 'DATETIME', 'null' => true],
                'dikunci_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'dibuat_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('periode_id', 'honor_dokumen_satu_per_periode');
            $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_dokumen', true, $this->attr);
        }

        // ---------- honor_dok_komponen (snapshot) ----------
        if (! $this->db->tableExists('honor_dok_komponen')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'dokumen_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'komponen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'kode'        => ['type' => 'VARCHAR', 'constraint' => 30],
                'nama'        => ['type' => 'VARCHAR', 'constraint' => 80],
                'tipe'        => ['type' => 'ENUM', 'constraint' => ['tetap', 'satuan'], 'default' => 'satuan'],
                'tarif'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'satuan'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'sumber'      => ['type' => 'ENUM', 'constraint' => ['manual', 'koreksi', 'rapot', 'soal'], 'default' => 'manual'],
                'urut'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 100],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['dokumen_id', 'komponen_id'], 'honor_dok_komponen_unik');
            $this->forge->addForeignKey('dokumen_id', 'honor_dokumen', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('komponen_id', 'honor_komponen', 'id', '', 'RESTRICT');
            $this->forge->createTable('honor_dok_komponen', true, $this->attr);
        }

        // ---------- honor_baris ----------
        if (! $this->db->tableExists('honor_baris')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'dokumen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'jabatan'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'catatan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'urut'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['dokumen_id', 'guru_id'], 'honor_baris_satu_per_orang');
            $this->forge->addKey(['dokumen_id', 'urut']);
            $this->forge->addForeignKey('dokumen_id', 'honor_dokumen', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
            $this->forge->createTable('honor_baris', true, $this->attr);
        }

        // ---------- honor_nilai ----------
        if (! $this->db->tableExists('honor_nilai')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'baris_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'dok_komponen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'nilai'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'otomatis_nilai' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['baris_id', 'dok_komponen_id'], 'honor_nilai_unik');
            $this->forge->addForeignKey('baris_id', 'honor_baris', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('dok_komponen_id', 'honor_dok_komponen', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_nilai', true, $this->attr);
        }
    }

    public function down()
    {
        foreach (['honor_nilai', 'honor_baris', 'honor_dok_komponen', 'honor_dokumen'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
    }
}
