<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Istirahat ujian yang DIATUR admin (keluhan klien 2026-11-24: "di jadwal ujian istirahat tidak muncul / tidak bisa di-set"; tahap 1).
 * Sebelumnya istirahat hanya dihitung otomatis dari jeda jam ujian. Tabel ini menyimpan pengaturan per periode + shift + TANGGAL:
 *   - hari tanpa baris di sini           = OTOMATIS (perilaku lama, tak berubah);
 *   - baris berjam (boleh lebih dari 1)  = istirahat hari itu diatur sendiri;
 *   - satu baris jam_mulai/selesai NULL  = istirahat hari itu DITIADAKAN.
 * Aturan & pengecekan di App\Libraries\IstirahatUjian. HANYA MENAMBAH tabel, idempoten. Hapus permanen (pengaturan, bukan riwayat).
 */
class UjianIstirahat extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('ujian_istirahat')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'periode_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'shift'       => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
            'tanggal'     => ['type' => 'DATE'],
            'jam_mulai'   => ['type' => 'TIME', 'null' => true],
            'jam_selesai' => ['type' => 'TIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['periode_id', 'shift', 'tanggal', 'jam_mulai'], 'ujian_istirahat_unique');
        $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
        $this->forge->createTable('ujian_istirahat', true);
    }

    public function down()
    {
        $this->forge->dropTable('ujian_istirahat', true);
    }
}
