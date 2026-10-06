<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PKL Tahap 4 — surat permohonan PKL.
 *
 *   1. pkl_pengaturan — kolom penanda tangan (Waka Hubin), format nomor surat, lantai nomor
 *                       ("nomor berikutnya"), dan nama berkas template Word milik sekolah.
 *   2. pkl_surat      — satu baris per ajuan yang suratnya sudah DITERBITKAN. Nomor ditetapkan
 *                       SEKALI (cetak ulang memakai nomor yang sama); UNIQUE(tahun, urut) mencegah
 *                       nomor ganda; `sidik` = sidik jari data saat terakhir dicetak, untuk
 *                       penanda "perlu cetak ulang" bila data ajuan berubah sesudahnya.
 *
 * Idempoten. Lihat docs/DESAIN-PKL.md.
 */
class PklSurat extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    /** @return array<string, array<string, mixed>> */
    private function kolomPengaturan(): array
    {
        return [
            'waka_hubin_nama'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'waka_hubin_nip'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'waka_hubin_jabatan' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => 'Wakil Kepala Sekolah Bidang Hubungan Industri'],
            'format_nomor'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => '{urut}/PKL/{tgl}-{bln}-{thn}'],
            // Lantai nomor: urut berikutnya = max(urut terakhir + 1, nomor_awal) — HANYA di tahun nomor_awal_tahun.
            'nomor_awal'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'nomor_awal_tahun'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            // Nama berkas template Word di writable/pkl/ (kosong = pakai surat bawaan).
            'template_surat'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
        ];
    }

    public function up()
    {
        foreach ($this->kolomPengaturan() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'pkl_pengaturan')) {
                $this->forge->addColumn('pkl_pengaturan', [$nama => $definisi]);
            }
        }

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pengajuan_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tahun'         => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'urut'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nomor'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'tanggal_surat' => ['type' => 'DATE'],
            'cetak_ke'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
            'sidik'         => ['type' => 'CHAR', 'constraint' => 40, 'null' => true],
            'terakhir_cetak_at' => ['type' => 'DATETIME', 'null' => true],
            'dibuat_oleh'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('pengajuan_id', 'surat_satu_per_ajuan');
        $this->forge->addUniqueKey(['tahun', 'urut'], 'surat_nomor_unik');
        $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
        $this->forge->createTable('pkl_surat', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('pkl_surat', true);
        foreach (array_keys($this->kolomPengaturan()) as $nama) {
            if ($this->db->fieldExists($nama, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $nama);
            }
        }
    }
}
