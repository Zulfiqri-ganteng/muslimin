<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel jejak percobaan login (untuk proteksi brute-force).
 * Menyimpan tiap percobaan GAGAL beserta IP & identitas login yang dicoba,
 * lalu dipakai App\Libraries\LoginThrottle untuk mengunci sementara.
 */
class CreateLoginAttempts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'login'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'ip_address'   => ['type' => 'VARCHAR', 'constraint' => 45],
            'scope'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'web'], // web | api | biometric
            'attempted_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['ip_address', 'attempted_at']);
        $this->forge->addKey(['login', 'ip_address', 'attempted_at']);
        $this->forge->createTable('login_attempts', true);
    }

    public function down()
    {
        $this->forge->dropTable('login_attempts', true);
    }
}
