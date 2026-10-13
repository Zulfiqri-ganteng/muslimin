<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kabar WhatsApp "Surat Balasan siap diambil" (Libraries\SuratWa, 2026-10-13):
 *   - surat_sekolah_siswa.dikabari_at / dikabari_oleh — siswa sudah dikabari lewat WhatsApp (per surat);
 *   - pkl_pengaturan.wa_pesan_balasan — templat pesan (kosong = pesan bawaan), diatur di Pengaturan PKL.
 * Idempoten.
 */
class SuratWa extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('surat_sekolah_siswa') && ! $this->db->fieldExists('dikabari_at', 'surat_sekolah_siswa')) {
            $this->forge->addColumn('surat_sekolah_siswa', [
                'dikabari_at'   => ['type' => 'DATETIME', 'null' => true],
                'dikabari_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ]);
        }
        if ($this->db->tableExists('pkl_pengaturan') && ! $this->db->fieldExists('wa_pesan_balasan', 'pkl_pengaturan')) {
            $this->forge->addColumn('pkl_pengaturan', [
                'wa_pesan_balasan' => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true],
            ]);
        }
    }

    public function down()
    {
        foreach (['dikabari_at', 'dikabari_oleh'] as $k) {
            if ($this->db->tableExists('surat_sekolah_siswa') && $this->db->fieldExists($k, 'surat_sekolah_siswa')) {
                $this->forge->dropColumn('surat_sekolah_siswa', $k);
            }
        }
        if ($this->db->tableExists('pkl_pengaturan') && $this->db->fieldExists('wa_pesan_balasan', 'pkl_pengaturan')) {
            $this->forge->dropColumn('pkl_pengaturan', 'wa_pesan_balasan');
        }
    }
}
