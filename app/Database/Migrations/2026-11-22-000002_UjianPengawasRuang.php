<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jadwal Pengawas per RUANG (F3 cetak jadwal, permintaan user 2026-11-22; contoh hasil: berkas klien "jadwal pengawas.xlsx").
 * Berbeda dari ujian_pengawas (per mapel/jadwal, Step 5): di sini satu pengawas per ruang per sesi (shift + tanggal + jam),
 * ruang bernomor 1..N (N = Periode ruang_pagi/ruang_siang, bawaan jumlah kelas shift). Hanya menambah tabel; idempoten.
 *
 *   ujian_pengawas_guru  : guru yang ikut diatur/di-generate jadi pengawas pada satu periode (dicentang di tab Jadwal Pengawas).
 *   ujian_pengawas_ruang : penugasan — UNIQUE(periode, shift, tanggal, jam_mulai, jam_selesai, ruang). Hapus permanen (pivot).
 */
class UjianPengawasRuang extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('ujian_pengawas_guru')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'periode_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['periode_id', 'guru_id']);
            $this->forge->addKey('guru_id');
            $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
            $this->forge->createTable('ujian_pengawas_guru', true);
        }

        if (! $this->db->tableExists('ujian_pengawas_ruang')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'periode_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'shift'       => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
                'tanggal'     => ['type' => 'DATE'],
                'jam_mulai'   => ['type' => 'TIME', 'null' => true],
                'jam_selesai' => ['type' => 'TIME', 'null' => true],
                'ruang'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
                'guru_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['periode_id', 'shift', 'tanggal', 'jam_mulai', 'jam_selesai', 'ruang'], 'ujian_pengawas_ruang_sel_unique');
            $this->forge->addKey(['guru_id', 'tanggal']);
            $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
            $this->forge->createTable('ujian_pengawas_ruang', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('ujian_pengawas_ruang', true);
        $this->forge->dropTable('ujian_pengawas_guru', true);
    }
}
