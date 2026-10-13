<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lapor diterima PKL + tanda tangan & stempel Kepala Sekolah (docs/DESAIN-HAK-AKSES.md bagian 4–5).
 *
 *   - Tabel `pkl_lapor`: SATU laporan per ajuan PKL (UNIQUE pengajuan_id; kirim ulang memperbarui baris yang sama). Siswa melapor
 *     "sudah diterima perusahaan" + periode PKL lewat form publik (pkl/lapor); staf memprosesnya menjadi Surat Balasan, atau
 *     mengembalikannya dengan alasan. Status: baru | diproses | dikembalikan.
 *   - pkl_pengaturan.lapor_buka (bawaan 1 = terbuka): saklar form lapor, terpisah dari saklar form pengajuan PKL.
 *   - pkl_pengaturan.ttd_kepsek: nama berkas gambar tanda tangan + stempel Kepala Sekolah di writable/pkl (TIDAK masuk repo).
 * Tidak ada data pribadi di migrasi ini. Idempoten.
 */
class PklLapor extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('pkl_lapor')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'pengajuan_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'siswa_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // pelapor terakhir
                'pelapor_nama'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'hp'                => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'tanggal_mulai'     => ['type' => 'DATE'],
                'tanggal_selesai'   => ['type' => 'DATE'],
                'alamat'            => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'catatan'           => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'status'            => ['type' => 'ENUM', 'constraint' => ['baru', 'diproses', 'dikembalikan'], 'default' => 'baru'],
                'alasan'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'surat_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'diproses_oleh'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'diproses_peran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'diproses_admin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'diproses_at'       => ['type' => 'DATETIME', 'null' => true],
                'kirim_ke'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
                'ip_address'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('pengajuan_id');
            $this->forge->addKey(['status', 'updated_at']);
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('surat_id', 'surat_sekolah', 'id', '', 'SET NULL');
            $this->forge->createTable('pkl_lapor', true, $this->attr);
        }

        $kolom = [];
        if (! $this->db->fieldExists('lapor_buka', 'pkl_pengaturan')) {
            $kolom['lapor_buka'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (! $this->db->fieldExists('ttd_kepsek', 'pkl_pengaturan')) {
            $kolom['ttd_kepsek'] = ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true];
        }
        if ($kolom !== []) {
            $this->forge->addColumn('pkl_pengaturan', $kolom);
        }
    }

    public function down()
    {
        $this->forge->dropTable('pkl_lapor', true);
        foreach (['lapor_buka', 'ttd_kepsek'] as $k) {
            if ($this->db->fieldExists($k, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $k);
            }
        }
    }
}
