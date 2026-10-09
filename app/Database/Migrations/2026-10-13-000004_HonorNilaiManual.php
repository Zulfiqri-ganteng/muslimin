<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — penanda eksplisit "diisi manual" per sel (honor_nilai.manual).
 *
 * Sel yang diisi lewat ketikan atau impor Excel bertanda manual = 1 dan TIDAK ditimpa oleh "Hitung otomatis"
 * (kecuali Admin mencentang "timpa"). Ini juga melindungi angka 0 yang sengaja diisi (mis. guru yang memang tidak
 * dibayar koreksi), yang dulu tak bisa dibedakan dari sel yang belum pernah disentuh. Idempoten.
 *
 * Isi lama (bila ada) diisi ulang: sel berangka ≠ 0 yang belum pernah dihitung otomatis atau sudah beda dari hitungan
 * otomatisnya dianggap manual.
 */
class HonorNilaiManual extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('manual', 'honor_nilai')) {
            $this->forge->addColumn('honor_nilai', [
                'manual' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0, 'after' => 'otomatis_nilai'],
            ]);
            $this->db->query('UPDATE honor_nilai SET manual = 1 WHERE nilai <> 0 AND (otomatis_nilai IS NULL OR nilai <> otomatis_nilai)');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('manual', 'honor_nilai')) {
            $this->forge->dropColumn('honor_nilai', 'manual');
        }
    }
}
