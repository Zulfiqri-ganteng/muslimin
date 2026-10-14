<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tema tampilan panel admin & halaman masuk (rombak tampilan F3, docs/RENCANA-UPGRADE-TAMPILAN.md §3), diatur Admin di
 * Sistem → Pengaturan Sekolah → Tampilan:
 *   - settings.tema_warna   : warna utama — 'biru' (bawaan) | 'lilac';
 *   - settings.tema_sidebar : gaya menu samping — 'terang' (bawaan) | 'gelap' | 'warna'.
 * Pasangan keduanya = tiga mode (Libraries\Tampilan::MODE): Terang (biru+terang, bawaan), Gelap (biru+gelap), Warna Sekolah (lilac+warna).
 * Tanpa migrasi ini aplikasi tetap jalan dengan tema bawaan (Libraries\Tampilan::tema). Idempoten.
 */
class TemaTampilan extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('settings')) {
            return;
        }
        $kolom = [];
        if (! $this->db->fieldExists('tema_warna', 'settings')) {
            $kolom['tema_warna'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'biru'];
        }
        if (! $this->db->fieldExists('tema_sidebar', 'settings')) {
            $kolom['tema_sidebar'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'terang'];
        }
        if ($kolom !== []) {
            $this->forge->addColumn('settings', $kolom);
        }
        cache()->delete('app_setting'); // baris pengaturan ter-cache 6 jam (SettingModel::get)
    }

    public function down()
    {
        foreach (['tema_warna', 'tema_sidebar'] as $k) {
            if ($this->db->tableExists('settings') && $this->db->fieldExists($k, 'settings')) {
                $this->forge->dropColumn('settings', $k);
            }
        }
        cache()->delete('app_setting');
    }
}
