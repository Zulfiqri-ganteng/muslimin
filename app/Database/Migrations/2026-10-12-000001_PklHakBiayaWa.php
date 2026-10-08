<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PKL — hak akses yang diatur Admin, pencatatan biaya saat surat diunduh, dan kabar WhatsApp manual.
 *
 *   1. pkl_pengaturan  + hak_peran (JSON hak per peran; kosong = hak bawaan di Libraries\PklHak)
 *                      + wa_pesan  (templat pesan WhatsApp; kosong = templat bawaan di Libraries\PklWa)
 *   2. pkl_anggota     + dikabari_at / dikabari_oleh (siswa sudah dikabari lewat WhatsApp)
 *   3. pkl_biaya       jenis biaya (4 baris awal: Biaya PKL, SPP, Tabungan Wajib, Iuran OSIS) — nominal bisa diubah Admin
 *      pkl_pembayaran  catatan pembayaran per siswa (UNIQUE siswa+biaya+periode, nominal disalin saat dicatat)
 *      pkl_beasiswa    beasiswa 3 tahun per siswa (membebaskan SPP saja)
 *      pkl_keringanan  keringanan/penundaan beserta alasannya (per kali unduh)
 *   4. Perapian nama Kepala Sekolah di data lama: "S.T" → "S.T." (titik penutup gelar).
 *
 * Idempoten: aman dijalankan ulang. Rancangan & alasan: docs/DESAIN-PKL.md (bagian "Hak akses, biaya, WhatsApp").
 */
class PklHakBiayaWa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        // ---------- 1. pkl_pengaturan ----------
        foreach (['hak_peran', 'wa_pesan'] as $kolom) {
            if (! $this->db->fieldExists($kolom, 'pkl_pengaturan')) {
                $this->forge->addColumn('pkl_pengaturan', [$kolom => ['type' => 'TEXT', 'null' => true]]);
            }
        }

        // ---------- 2. pkl_anggota ----------
        if (! $this->db->fieldExists('dikabari_at', 'pkl_anggota')) {
            $this->forge->addColumn('pkl_anggota', [
                'dikabari_at'    => ['type' => 'DATETIME', 'null' => true],
                'dikabari_oleh'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ]);
        }

        // ---------- 3. tabel biaya ----------
        if (! $this->db->tableExists('pkl_biaya')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'nominal'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'siklus'     => ['type' => 'ENUM', 'constraint' => ['bulanan', 'kegiatan'], 'default' => 'bulanan'],
                'aktif'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'urut'       => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('kode', 'biaya_kode');
            $this->forge->createTable('pkl_biaya', true, $this->attr);
        }
        if ((int) $this->db->table('pkl_biaya')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('pkl_biaya')->insertBatch([
                ['kode' => 'pkl',       'nama' => 'Biaya PKL',       'nominal' => 300000, 'siklus' => 'kegiatan', 'aktif' => 1, 'urut' => 1, 'updated_at' => $now],
                ['kode' => 'spp',       'nama' => 'Biaya SPP',       'nominal' => 150000, 'siklus' => 'bulanan',  'aktif' => 1, 'urut' => 2, 'updated_at' => $now],
                ['kode' => 'tabungan',  'nama' => 'Tabungan Wajib',  'nominal' => 50000,  'siklus' => 'bulanan',  'aktif' => 1, 'urut' => 3, 'updated_at' => $now],
                ['kode' => 'osis',      'nama' => 'Iuran OSIS',      'nominal' => 10000,  'siklus' => 'bulanan',  'aktif' => 1, 'urut' => 4, 'updated_at' => $now],
            ]);
        }

        if (! $this->db->tableExists('pkl_pembayaran')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'siswa_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'biaya_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                // bulanan: "2026-10"; kegiatan: tahun ajaran ajuan, mis. "2026/2027"
                'periode'          => ['type' => 'VARCHAR', 'constraint' => 20],
                // nominal disalin saat dicatat, jadi catatan lama tidak berubah bila Admin mengubah harga
                'nominal'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'pengajuan_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dicatat_oleh_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dicatat_oleh'     => ['type' => 'VARCHAR', 'constraint' => 150],
                'dicatat_peran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['siswa_id', 'biaya_id', 'periode'], 'bayar_unik');
            $this->forge->addKey('pengajuan_id');
            $this->forge->addKey('created_at');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('biaya_id', 'pkl_biaya', 'id', '', 'RESTRICT');
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'SET NULL');
            $this->forge->createTable('pkl_pembayaran', true, $this->attr);
        }

        if (! $this->db->tableExists('pkl_beasiswa')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'siswa_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'sumber'          => ['type' => 'ENUM', 'constraint' => ['sktm', 'yayasan', 'lainnya'], 'default' => 'sktm'],
                'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                // Beasiswa 3 tahun: berakhir akhir Juni (tahun masuk + 3); NULL = tanpa batas.
                'berakhir_at'     => ['type' => 'DATE', 'null' => true],
                'dicatat_oleh_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dicatat_oleh'    => ['type' => 'VARCHAR', 'constraint' => 150],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('siswa_id', 'beasiswa_satu_per_siswa');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
            $this->forge->createTable('pkl_beasiswa', true, $this->attr);
        }

        if (! $this->db->tableExists('pkl_keringanan')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'siswa_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'pengajuan_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'alasan'          => ['type' => 'VARCHAR', 'constraint' => 255],
                'dicatat_oleh_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dicatat_oleh'    => ['type' => 'VARCHAR', 'constraint' => 150],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['siswa_id', 'pengajuan_id']);
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'SET NULL');
            $this->forge->createTable('pkl_keringanan', true, $this->attr);
        }

        // ---------- 4. perapian data lama (hanya nilai persis yang dikenal) ----------
        // (a) titik penutup gelar Kepala Sekolah.
        $this->db->query("UPDATE pkl_pengaturan SET kepsek_nama = 'Napis Kuturupi, S.T.' WHERE kepsek_nama = 'Napis Kuturupi, S.T'");
        // (b) format nomor "{urut}00/…" (salah ketik: hasilnya 100, 200, 600, bukan 001, 002) → tiga angka: 001/…
        $this->db->query("UPDATE pkl_pengaturan SET format_nomor = '{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}' WHERE format_nomor = '{urut}00/SMK-BN/PKL/{bln_romawi}/{thn}'");
        if ($this->db->tableExists('settings') && $this->db->fieldExists('headmaster_name', 'settings')) {
            $this->db->query("UPDATE settings SET headmaster_name = 'Napis Kuturupi, S.T.' WHERE headmaster_name = 'Napis Kuturupi, S.T'");
        }
    }

    public function down()
    {
        foreach (['pkl_keringanan', 'pkl_beasiswa', 'pkl_pembayaran', 'pkl_biaya'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
        foreach (['dikabari_at', 'dikabari_oleh'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'pkl_anggota')) {
                $this->forge->dropColumn('pkl_anggota', $kolom);
            }
        }
        foreach (['hak_peran', 'wa_pesan'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $kolom);
            }
        }
    }
}
