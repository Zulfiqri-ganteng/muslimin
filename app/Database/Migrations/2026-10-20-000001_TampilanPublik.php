<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rombak tampilan halaman publik (docs/RENCANA-TAMPILAN-PUBLIK.md, P2):
 *   - tabel foto_sekolah  : foto yang diunggah sekolah → banner beranda, pita "Tentang sekolah", galeri halaman Profil;
 *   - pengumuman          : + kategori (Pengumuman/Berita/Agenda/Prestasi), + gambar (opsional, kartu "Informasi untuk Anda");
 *   - settings            : + profil_singkat, + wa_bantuan (nomor WhatsApp bantuan), + tautan_aplikasi (unduh aplikasi Android).
 * Aman dijalankan ulang (cek kolom/tabel dulu).
 */
class TampilanPublik extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('foto_sekolah')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'judul'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'berkas'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'tautan'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'tampil_banner' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'urutan'        => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_aktif'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['is_aktif', 'urutan']);
            $this->forge->createTable('foto_sekolah', true, ['ENGINE' => 'InnoDB']);
        }

        $kolomPengumuman = [];
        if (! $this->db->fieldExists('kategori', 'pengumuman')) {
            $kolomPengumuman['kategori'] = ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pengumuman', 'after' => 'isi'];
        }
        if (! $this->db->fieldExists('gambar', 'pengumuman')) {
            $kolomPengumuman['gambar'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'isi'];
        }
        if ($kolomPengumuman !== []) {
            $this->forge->addColumn('pengumuman', $kolomPengumuman);
        }

        $kolomSetting = [];
        if (! $this->db->fieldExists('profil_singkat', 'settings')) {
            $kolomSetting['profil_singkat'] = ['type' => 'TEXT', 'null' => true];
        }
        if (! $this->db->fieldExists('wa_bantuan', 'settings')) {
            $kolomSetting['wa_bantuan'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true];
        }
        if (! $this->db->fieldExists('tautan_aplikasi', 'settings')) {
            $kolomSetting['tautan_aplikasi'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if ($kolomSetting !== []) {
            $this->forge->addColumn('settings', $kolomSetting);
        }

        // Pengaturan di-cache (SettingModel::get) — buang supaya kolom baru langsung terbaca.
        cache()->delete('app_setting');
        cache()->delete('pengumuman_aktif');
    }

    public function down()
    {
        $this->forge->dropTable('foto_sekolah', true);
        foreach (['gambar', 'kategori'] as $k) {
            if ($this->db->fieldExists($k, 'pengumuman')) {
                $this->forge->dropColumn('pengumuman', $k);
            }
        }
        foreach (['profil_singkat', 'wa_bantuan', 'tautan_aplikasi'] as $k) {
            if ($this->db->fieldExists($k, 'settings')) {
                $this->forge->dropColumn('settings', $k);
            }
        }
        cache()->delete('app_setting');
    }
}
