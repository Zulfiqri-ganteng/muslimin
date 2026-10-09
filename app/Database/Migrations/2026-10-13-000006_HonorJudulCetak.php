<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — penyesuaian agar cetakan SAMA dengan rekap Excel sekolah.
 *
 *   1. judul_cetak (honor_komponen & honor_dok_komponen): tulisan judul kolom di cetakan bila ingin berbeda dari
 *      NAMA KOMPONEN HURUF BESAR (mis. judul "Rapot" di rekap sekolah ditulis "Rapot", bukan "RAPOT").
 *   2. Komponen Rapot: di rekap ASTS sekolah kolom Rapot TETAP ADA (berisi 0), jadi berlaku di semua jenis ujian
 *      (sebelumnya hanya ASAS & ASAT) dan judulnya "Rapot".
 *
 * Idempoten. Rancangan: docs/DESAIN-HONOR.md.
 */
class HonorJudulCetak extends Migration
{
    public function up()
    {
        foreach (['honor_komponen', 'honor_dok_komponen'] as $tabel) {
            if (! $this->db->fieldExists('judul_cetak', $tabel)) {
                $this->forge->addColumn($tabel, ['judul_cetak' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'nama']]);
            }
            $this->db->query("UPDATE {$tabel} SET judul_cetak = 'Rapot' WHERE kode = 'rapot' AND judul_cetak IS NULL");
        }
        $this->db->query("UPDATE honor_komponen SET berlaku_di = NULL WHERE kode = 'rapot' AND berlaku_di = 'ASAS,ASAT'");
    }

    public function down()
    {
        foreach (['honor_komponen', 'honor_dok_komponen'] as $tabel) {
            if ($this->db->fieldExists('judul_cetak', $tabel)) {
                $this->forge->dropColumn($tabel, 'judul_cetak');
            }
        }
    }
}
