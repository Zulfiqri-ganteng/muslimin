<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kabar WhatsApp saat ajuan PKL DIKEMBALIKAN untuk diperbaiki atau DITOLAK (Libraries\PklWaKeputusan, 2026-10-18):
 *   - pkl_pengaturan.wa_pesan_perbaikan / wa_pesan_tolak — templat pesan (kosong = pesan bawaan), diatur di Pengaturan PKL;
 *   - pkl_anggota.kabar_keputusan_at / kabar_keputusan_oleh — siswa sudah dikabari keputusan terakhir lewat WhatsApp
 *     (dibandingkan dengan pkl_pengajuan.diputuskan_at, jadi kosong lagi bila ajuan diputuskan ulang).
 * Idempoten.
 */
class PklWaKeputusan extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('pkl_pengaturan')) {
            foreach (['wa_pesan_perbaikan', 'wa_pesan_tolak'] as $k) {
                if (! $this->db->fieldExists($k, 'pkl_pengaturan')) {
                    $this->forge->addColumn('pkl_pengaturan', [$k => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true]]);
                }
            }
        }
        if ($this->db->tableExists('pkl_anggota') && ! $this->db->fieldExists('kabar_keputusan_at', 'pkl_anggota')) {
            $this->forge->addColumn('pkl_anggota', [
                'kabar_keputusan_at'   => ['type' => 'DATETIME', 'null' => true],
                'kabar_keputusan_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ]);
        }
    }

    public function down()
    {
        foreach (['wa_pesan_perbaikan', 'wa_pesan_tolak'] as $k) {
            if ($this->db->tableExists('pkl_pengaturan') && $this->db->fieldExists($k, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $k);
            }
        }
        foreach (['kabar_keputusan_at', 'kabar_keputusan_oleh'] as $k) {
            if ($this->db->tableExists('pkl_anggota') && $this->db->fieldExists($k, 'pkl_anggota')) {
                $this->forge->dropColumn('pkl_anggota', $k);
            }
        }
    }
}
