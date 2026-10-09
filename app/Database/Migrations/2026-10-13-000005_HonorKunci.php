<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — kunci dokumen (Fase 5): sidik jari isi honor saat DIKUNCI (kunci_hash, kunci_total).
 *
 * Saat honor dikunci, sistem menyimpan sidik jari (HMAC) dari seluruh isi honor. Bila nanti isi berubah lewat
 * jalan lain (mis. langsung di database), tab Honor menampilkan peringatan "berubah sejak dikunci". Idempoten.
 */
class HonorKunci extends Migration
{
    public function up()
    {
        $tambah = [];
        if (! $this->db->fieldExists('kunci_hash', 'honor_dokumen')) {
            $tambah['kunci_hash'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'dikunci_oleh'];
        }
        if (! $this->db->fieldExists('kunci_total', 'honor_dokumen')) {
            $tambah['kunci_total'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'dikunci_oleh'];
        }
        if ($tambah !== []) {
            $this->forge->addColumn('honor_dokumen', $tambah);
        }
    }

    public function down()
    {
        foreach (['kunci_hash', 'kunci_total'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'honor_dokumen')) {
                $this->forge->dropColumn('honor_dokumen', $kolom);
            }
        }
    }
}
