<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Master Siswa — kolom tambahan dari Daftar Nama Siswa resmi sekolah (Format 8355).
 *
 *   nama_orang_tua  "Nama Orang Tua" persis seperti di berkas sekolah (satu kolom; bisa ayah atau ibu,
 *                   sehingga TIDAK ditebak masuk nama_ayah/nama_ibu milik biodata)
 *   sttb_nomor      Nomor STTB/ijazah setingkat lebih rendah (SMP/MTs)
 *   sttb_tahun      Tahun STTB/ijazah setingkat lebih rendah
 *
 * Murni TAMBAHAN: tidak mengubah/menghapus kolom lama, semua boleh kosong. Idempoten (aman diulang).
 * Lihat docs/RENCANA-DATA-SISWA-8355.md.
 */
class SiswaDataSekolah extends Migration
{
    /** @return array<string, array<string, mixed>> */
    private function kolom(): array
    {
        return [
            'nama_orang_tua' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'pekerjaan_wali'],
            'sttb_nomor'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'nama_orang_tua'],
            'sttb_tahun'     => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'sttb_nomor'],
        ];
    }

    public function up()
    {
        foreach ($this->kolom() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'siswa')) {
                $this->forge->addColumn('siswa', [$nama => $definisi]);
            }
        }
    }

    public function down()
    {
        foreach (array_keys($this->kolom()) as $nama) {
            if ($this->db->fieldExists($nama, 'siswa')) {
                $this->forge->dropColumn('siswa', $nama);
            }
        }
    }
}
