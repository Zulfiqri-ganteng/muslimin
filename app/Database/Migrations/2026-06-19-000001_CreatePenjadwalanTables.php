<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * F0 — Tabel inti modul Penjadwalan KBM.
 * 12 tabel: tahun_ajaran, jurusan, guru, mata_pelajaran, kelas, hari,
 * jam_pelajaran, guru_mapel, ketersediaan_guru, pengampu, jadwal, audit_log.
 *
 * Semua InnoDB + utf8mb4, FK lengkap, soft delete (deleted_at) untuk master data.
 */
class CreatePenjadwalanTables extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $ts = static fn () => [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        // ============================================================
        // 1. tahun_ajaran
        // ============================================================
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tahun'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'semester' => ['type' => 'ENUM', 'constraint' => ['Ganjil', 'Genap']],
            'is_aktif' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tahun', 'semester']);
        $this->forge->createTable('tahun_ajaran', true, $this->attr);

        // ============================================================
        // 2. jurusan
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('jurusan', true, $this->attr);

        // ============================================================
        // 3. guru
        // ============================================================
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nip'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'kode_guru'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'jenis_kelamin' => ['type' => 'ENUM', 'constraint' => ['L', 'P'], 'null' => true],
            'status_guru'   => ['type' => 'ENUM', 'constraint' => ['PNS', 'PPPK', 'GTY', 'GTT'], 'null' => true],
            'max_beban'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 24],
            'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode_guru');
        $this->forge->addKey('nama');
        $this->forge->createTable('guru', true, $this->attr);

        // ============================================================
        // 4. mata_pelajaran
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_mapel' => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama_mapel' => ['type' => 'VARCHAR', 'constraint' => 150],
            'kelompok'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'jp_default' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 2],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode_mapel');
        $this->forge->createTable('mata_pelajaran', true, $this->attr);

        // ============================================================
        // 5. hari
        // ============================================================
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama'   => ['type' => 'VARCHAR', 'constraint' => 15],
            'urutan' => ['type' => 'TINYINT', 'unsigned' => true],
            'aktif'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nama');
        $this->forge->createTable('hari', true, $this->attr);

        // ============================================================
        // 6. jam_pelajaran
        // ============================================================
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'shift'         => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
            'jam_ke'        => ['type' => 'TINYINT', 'unsigned' => true],
            'waktu_mulai'   => ['type' => 'TIME'],
            'waktu_selesai' => ['type' => 'TIME'],
            'durasi'        => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 35],
            'is_istirahat'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['shift', 'jam_ke']);
        $this->forge->createTable('jam_pelajaran', true, $this->attr);

        // ============================================================
        // 7. kelas  (FK -> jurusan, guru)
        // ============================================================
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_kelas'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'tingkat'       => ['type' => 'ENUM', 'constraint' => ['X', 'XI', 'XII']],
            'jurusan_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'wali_kelas_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'shift'         => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang'], 'default' => 'pagi'],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nama_kelas');
        $this->forge->addKey('shift');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('wali_kelas_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->createTable('kelas', true, $this->attr);

        // ============================================================
        // 8. guru_mapel  (pivot kompetensi)
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'mapel_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'mapel_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'CASCADE');
        $this->forge->createTable('guru_mapel', true, $this->attr);

        // ============================================================
        // 9. ketersediaan_guru
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'hari_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jam_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['tersedia', 'tidak'], 'default' => 'tersedia'],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'hari_id', 'jam_id']);
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('jam_id', 'jam_pelajaran', 'id', '', 'CASCADE');
        $this->forge->createTable('ketersediaan_guru', true, $this->attr);

        // ============================================================
        // 10. pengampu  (penugasan guru-mapel-kelas + JP)
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kelas_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'mapel_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jp'         => ['type' => 'SMALLINT', 'unsigned' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['kelas_id', 'mapel_id']);
        $this->forge->addKey('guru_id');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'RESTRICT');
        $this->forge->createTable('pengampu', true, $this->attr);

        // ============================================================
        // 11. jadwal  (isi sel grid)
        // ============================================================
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tahun_ajaran_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'kelas_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'hari_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jam_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'pengampu_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'guru_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        // R2 Bentrok Kelas: 1 sel (kelas+hari+jam) hanya boleh 1 isi
        $this->forge->addUniqueKey(['kelas_id', 'hari_id', 'jam_id']);
        // R1 Bentrok Guru: 1 guru tak boleh 2 kelas di slot sama
        $this->forge->addUniqueKey(['guru_id', 'hari_id', 'jam_id']);
        $this->forge->addKey('tahun_ajaran_id');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('jam_id', 'jam_pelajaran', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('pengampu_id', 'pengampu', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', '', 'SET NULL');
        $this->forge->createTable('jadwal', true, $this->attr);

        // ============================================================
        // 12. audit_log
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'aksi'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'tabel'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'record_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deskripsi'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tabel', 'record_id']);
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('audit_log', true, $this->attr);
    }

    public function down()
    {
        // urutan terbalik (anak dulu) agar FK aman
        foreach ([
            'audit_log', 'jadwal', 'pengampu', 'ketersediaan_guru', 'guru_mapel',
            'kelas', 'jam_pelajaran', 'hari', 'mata_pelajaran', 'guru', 'jurusan', 'tahun_ajaran',
        ] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
