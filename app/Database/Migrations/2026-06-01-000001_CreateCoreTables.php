<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCoreTables extends Migration
{
    public function up()
    {
        // ============================================================
        // TABEL: admins (akun pengelola / panel admin)
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'full_name'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'photo'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'admin'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('admins', true);

        // ============================================================
        // TABEL: settings (identitas sekolah - 1 baris, id=1)
        // ============================================================
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'school_name'     => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => 'NAMA SEKOLAH'],
            'school_level'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'logo'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'headmaster_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'headmaster_nip'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'city'            => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Bekasi'],
            'academic_year'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '2026/2027'],
            'address'         => ['type' => 'TEXT', 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'website'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'form_open'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'form_intro'      => ['type' => 'TEXT', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('settings', true);

        // ============================================================
        // TABEL: submissions (data kesediaan guru)
        // ============================================================
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // Identitas Guru
            'nama_lengkap'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'nip_nuptk'           => ['type' => 'VARCHAR', 'constraint' => 60],
            'tempat_lahir'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tanggal_lahir'       => ['type' => 'DATE', 'null' => true],
            'pendidikan_terakhir' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'guru_mapel'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'status_kepegawaian'  => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'nomor_hp'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            // Mata pelajaran yang diampu (JSON)
            'mapel_diampu'        => ['type' => 'LONGTEXT', 'null' => true],
            'total_jam'           => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            // Tugas tambahan (JSON array) + lainnya
            'tugas_tambahan'      => ['type' => 'LONGTEXT', 'null' => true],
            'tugas_lainnya'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Kesediaan jam mengajar (JSON array)
            'kesediaan_jam'       => ['type' => 'LONGTEXT', 'null' => true],
            // Lampiran A: preferensi mapel (JSON), B: ketersediaan hari (JSON), C: keterangan
            'preferensi'          => ['type' => 'LONGTEXT', 'null' => true],
            'ketersediaan_hari'   => ['type' => 'LONGTEXT', 'null' => true],
            'keterangan_tambahan' => ['type' => 'TEXT', 'null' => true],
            // Pernyataan
            'bersedia_mengajar'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'komitmen_setuju'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            // Meta / workflow admin
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'baru'],
            'catatan_admin'       => ['type' => 'TEXT', 'null' => true],
            'ip_address'          => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nip_nuptk');
        $this->forge->createTable('submissions', true);
    }

    public function down()
    {
        $this->forge->dropTable('submissions', true);
        $this->forge->dropTable('settings', true);
        $this->forge->dropTable('admins', true);
    }
}
