<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Surat Perpanjangan PKL LINTAS AJUAN (permintaan user 2026-10-28: "ajak teman seperti konsep ajuan PKL"; rancangan:
 * docs/DESAIN-PERPANJANGAN-PKL.md bagian 13). Teman yang dicentang kini = semua siswa PKL di PERUSAHAAN yang sama, walaupun
 * dulu mengajukan PKL lewat ajuan berbeda, jadi satu surat bisa memuat siswa dari beberapa ajuan.
 *
 *   - surat_sekolah_siswa.pengajuan_id (NULL untuk jenis surat lain): ajuan PKL asal siswa itu di surat perpanjangan, supaya
 *     tanggal selesai efektif, lencana "Diperpanjang s.d.", dan "maksimal satu kali" tetap dihitung per ajuan siswanya sendiri.
 *   - Isi data lama: surat perpanjangan yang sudah ada dibuat dengan aturan lama (semua siswanya satu ajuan), jadi kolom baru diisi
 *     dari pkl_perpanjangan.pengajuan_id — pasti benar, tanpa perlu data dari hosting.
 *
 * Idempoten. Tanpa data pribadi. down() menolak berjalan bila sudah ada surat yang memuat siswa dari ajuan lain (data tidak
 * dihapus diam-diam).
 */
class PerpanjanganLintasAjuan extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('surat_sekolah_siswa')) {
            return;
        }
        if (! $this->db->fieldExists('pengajuan_id', 'surat_sekolah_siswa')) {
            $this->forge->addColumn('surat_sekolah_siswa', [
                'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'siswa_id'],
            ]);
            $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('surat_sekolah_siswa')) . ' ADD INDEX `surat_sekolah_siswa_pengajuan_id` (`pengajuan_id`)');
        }
        if ($this->db->tableExists('pkl_perpanjangan')) {
            $this->db->query('UPDATE ' . $this->db->prefixTable('surat_sekolah_siswa') . ' ss JOIN ' . $this->db->prefixTable('pkl_perpanjangan')
                . ' pp ON pp.surat_id = ss.surat_id SET ss.pengajuan_id = pp.pengajuan_id WHERE ss.pengajuan_id IS NULL');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('surat_sekolah_siswa') || ! $this->db->fieldExists('pengajuan_id', 'surat_sekolah_siswa')) {
            return;
        }
        if ($this->db->tableExists('pkl_perpanjangan')) {
            $lintas = (int) $this->db->query('SELECT COUNT(*) AS n FROM ' . $this->db->prefixTable('surat_sekolah_siswa') . ' ss JOIN ' . $this->db->prefixTable('pkl_perpanjangan')
                . ' pp ON pp.surat_id = ss.surat_id WHERE ss.pengajuan_id IS NOT NULL AND ss.pengajuan_id <> pp.pengajuan_id')->getRow('n');
            if ($lintas > 0) {
                throw new \RuntimeException('Sudah ada ' . $lintas . ' siswa di Surat Perpanjangan PKL yang berasal dari ajuan lain. Batalkan/hapus surat itu dulu bila migrasi ini memang mau dibatalkan.');
            }
        }
        $this->forge->dropColumn('surat_sekolah_siswa', 'pengajuan_id');
    }
}
