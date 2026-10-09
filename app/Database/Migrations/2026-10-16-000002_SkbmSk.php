<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SKBM — nomor Surat Keputusan (SK) per TAHUN AJARAN, mis. "123/SMK-BN/SKBM/VII/2026". Dicetak di baris "Nomor :" pada Excel SKBM
 * (tata letak sama dengan lembar SKBM sekolah). Satu baris per tahun ajaran; kosong = baris itu tidak ada (tercetak titik-titik).
 * Tidak ada data pribadi di migrasi ini. Idempoten. Rancangan: docs/DESAIN-SKBM.md.
 */
class SkbmSk extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('skbm_sk')) {
            $this->forge->addField([
                'tahun_ajaran' => ['type' => 'VARCHAR', 'constraint' => 9],
                'nomor'        => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('tahun_ajaran', true);
            $this->forge->createTable('skbm_sk', true, $this->attr);
        }
    }

    public function down()
    {
        $this->forge->dropTable('skbm_sk', true);
    }
}
