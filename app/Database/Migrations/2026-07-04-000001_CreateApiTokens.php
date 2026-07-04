<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel token autentikasi untuk API mobile (Flutter).
 * Token disimpan sebagai hash (SHA-256); nilai asli hanya dikirim sekali ke klien.
 */
class CreateApiTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token_hash'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'device_name'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('admin_id');
        $this->forge->addForeignKey('admin_id', 'admins', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_tokens', true);
    }

    public function down()
    {
        $this->forge->dropTable('api_tokens', true);
    }
}
