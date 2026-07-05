<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kredensial login sidik jari (biometrik) untuk aplikasi mobile.
 *
 * Server TIDAK pernah menyimpan/melihat sidik jari. Yang disimpan hanyalah
 * hash sebuah "device secret" acak berentropi tinggi. Aplikasi menyimpan
 * secret asli di secure storage HP yang dibuka oleh sidik jari. Saat login,
 * HP memverifikasi sidik jari secara lokal, mengambil secret, lalu menukarnya
 * ke server untuk mendapat token API biasa.
 */
class CreateBiometricCredentials extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id'    => ['type' => 'VARCHAR', 'constraint' => 190],
            'secret_hash'  => ['type' => 'VARCHAR', 'constraint' => 64],
            'device_name'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('device_id');
        $this->forge->addKey('admin_id');
        $this->forge->addForeignKey('admin_id', 'admins', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('biometric_credentials', true);
    }

    public function down()
    {
        $this->forge->dropTable('biometric_credentials', true);
    }
}
