<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Absensi manual guru (per sesi mengajar / per jam pelajaran).
 *
 * Pola "hanya simpan pengecualian": default kehadiran = HADIR (tidak disimpan).
 * Baris hanya dibuat saat status != hadir (telat/izin/sakit/alpa). Kolom guru/
 * kelas/mapel di-DENORMALISASI (disalin) agar data absensi tetap valid untuk
 * penggajian walau jadwal berubah/dihapus di kemudian hari.
 *
 * Kunci unik natural sesi = (tanggal, kelas_id, jam_id): satu kelas pada satu
 * jam di satu tanggal hanya punya satu sesi (satu guru).
 */
class CreateAbsensiGuru extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'jadwal_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kelas_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'mapel_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'hari_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jam_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['hadir', 'telat', 'izin', 'sakit', 'alpa'], 'default' => 'hadir'],
            'jam_masuk'  => ['type' => 'TIME', 'null' => true],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Satu status per sesi (kelas+jam) per tanggal.
        $this->forge->addUniqueKey(['tanggal', 'kelas_id', 'jam_id']);
        // Rekap per guru & per tanggal cepat.
        $this->forge->addKey(['guru_id', 'tanggal']);
        $this->forge->addKey('tanggal');

        // FK: jadwal/mapel/admin boleh hilang (SET NULL) — data absensi tetap hidup.
        // guru/kelas terhubung CASCADE; hari/jam RESTRICT (master jarang dihapus).
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('jam_id', 'jam_pelajaran', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('jadwal_id', 'jadwal', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');

        $this->forge->createTable('absensi_guru', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('absensi_guru', true);
    }
}
