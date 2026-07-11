<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Absensi KEHADIRAN KERJA guru (per orang per hari, DI LUAR jadwal KBM).
 *
 * Berbeda dari `absensi_guru` yang per SESI mengajar & memakai pola "hanya
 * simpan pengecualian": tabel ini menyimpan SEMUA status termasuk 'hadir',
 * karena tanpa jadwal tidak ada acuan "siapa yang seharusnya hadir". Satu baris
 * = "orang ini masuk kerja pada tanggal ini" (mis. wakil kepala yang hadir di
 * hari Sabtu/Minggu meski tidak mengajar).
 *
 * Rekap absensi menggabungkan hari-mengajar (absensi_guru + jadwal) dengan
 * hari-masuk-kerja (tabel ini) → total hari per guru untuk penggajian.
 */
class CreateAbsensiKerja extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['hadir', 'telat', 'izin', 'sakit', 'alpa'], 'default' => 'hadir'],
            'jam_masuk'  => ['type' => 'TIME', 'null' => true],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Satu catatan kehadiran kerja per guru per tanggal.
        $this->forge->addUniqueKey(['tanggal', 'guru_id']);
        // Rekap per guru & per tanggal cepat.
        $this->forge->addKey(['guru_id', 'tanggal']);
        $this->forge->addKey('tanggal');

        // guru dihapus → catatan ikut hilang; admin boleh hilang (SET NULL).
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');

        $this->forge->createTable('absensi_kerja', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('absensi_kerja', true);
    }
}
