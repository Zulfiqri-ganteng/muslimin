<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengaturan modul Manajemen Dokumen, menempel di tabel settings (1 baris).
 *
 *   dok_maks_mb       — batas ukuran satu berkas (MB). Angka pastinya
 *                       dikunci setelah D0 (diagnostik hosting) dijalankan.
 *   dok_izinkan_video — saklar unggah berkas video. DEFAULT MATI sesuai
 *                       keputusan user: video ditempel sebagai TAUTAN
 *                       YouTube/Drive supaya disk hosting tidak jebol.
 *                       Tinggal dinyalakan bila kelak pindah hosting.
 *   dok_kuota_mb      — pagu total penyimpanan modul, untuk peringatan dini.
 *   dokumen_publik    — toggle halaman Dokumen publik, pola sama dengan
 *                       jadwal_publik / absensi_publik.
 */
class AddDokumenSettings extends Migration
{
    /** @var array<string, array<string, mixed>> */
    private array $kolom = [
        'dok_maks_mb' => [
            'type' => 'SMALLINT', 'unsigned' => true, 'default' => 25, 'after' => 'absensi_publik',
        ],
        'dok_izinkan_video' => [
            'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'dok_maks_mb',
        ],
        'dok_kuota_mb' => [
            'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 2048, 'after' => 'dok_izinkan_video',
        ],
        'dokumen_publik' => [
            'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'dok_kuota_mb',
        ],
    ];

    public function up()
    {
        foreach ($this->kolom as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'settings')) {
                $this->forge->addColumn('settings', [$nama => $definisi]);
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(array_keys($this->kolom)) as $nama) {
            if ($this->db->fieldExists($nama, 'settings')) {
                $this->forge->dropColumn('settings', $nama);
            }
        }
    }
}
