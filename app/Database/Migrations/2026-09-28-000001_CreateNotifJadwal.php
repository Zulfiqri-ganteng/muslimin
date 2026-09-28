<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Notifikasi HP jadwal guru masuk kelas (Firebase Cloud Messaging).
 * Rancangan: docs/DESAIN-ABSENSI-KELAS-NOTIF.md (Bagian B).
 *
 *   notif_perangkat  — HP penerima per admin (token FCM). Satu baris per HP.
 *   notif_aturan     — aturan klien: hari × guru × jurusan + menit sebelum.
 *   notif_pengaturan — saklar per admin: aktif, jeda sampai, diam saat ujian.
 *   notif_log        — riwayat notif terkirim + kunci ANTI DOBEL (UNIQUE)
 *                      agar cron yang tumpang tindih tidak mengirim dua kali.
 *
 * Kolom JSON disimpan sebagai TEXT (aman di MySQL/MariaDB versi apa pun).
 * Semua perubahan MENAMBAH tabel baru — tidak ada tabel lama yang diubah.
 */
class CreateNotifJadwal extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'token'          => ['type' => 'TEXT'],
            'token_hash'     => ['type' => 'CHAR', 'constraint' => 64],
            'nama_perangkat' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'terima'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_seen_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['admin_id', 'device_id']);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'CASCADE');
        $this->forge->createTable('notif_perangkat', true, $this->attr);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'hari'          => ['type' => 'TEXT'],                  // JSON id hari
            'guru'          => ['type' => 'TEXT', 'null' => true],  // JSON id guru (orang); kosong = semua
            'jurusan'       => ['type' => 'TEXT', 'null' => true],  // JSON id jurusan; kosong = semua
            'menit_sebelum' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 5],
            'aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('admin_id');
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'CASCADE');
        $this->forge->createTable('notif_aturan', true, $this->attr);

        $this->forge->addField([
            'admin_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'aktif'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'jeda_sampai'     => ['type' => 'DATE', 'null' => true],
            'diam_saat_ujian' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('admin_id', true);
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'CASCADE');
        $this->forge->createTable('notif_pengaturan', true, $this->attr);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jenis'         => ['type' => 'VARCHAR', 'constraint' => 20],   // jadwal / uji
            'kunci'         => ['type' => 'VARCHAR', 'constraint' => 100],  // anti dobel
            'tanggal'       => ['type' => 'DATE'],
            'slot'          => ['type' => 'TIME', 'null' => true],
            'judul'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'isi'           => ['type' => 'TEXT'],
            'data'          => ['type' => 'TEXT', 'null' => true],          // JSON
            'jml_perangkat' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'berhasil'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'gagal'         => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'galat'         => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kunci');
        $this->forge->addKey(['admin_id', 'created_at']);
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'CASCADE');
        $this->forge->createTable('notif_log', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('notif_log', true);
        $this->forge->dropTable('notif_pengaturan', true);
        $this->forge->dropTable('notif_aturan', true);
        $this->forge->dropTable('notif_perangkat', true);
    }
}
