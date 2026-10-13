<?php

namespace App\Database\Migrations;

use App\Libraries\PklSurat;
use CodeIgniter\Database\Migration;

/**
 * Rapikan gambar tanda tangan yang SUDAH diunggah sebelum ada perapi otomatis (Libraries\GambarTtd, 2026-10-13): tanda tangan
 * Waka Hubin & tanda tangan + stempel Kepala Sekolah di writable/pkl dipotong tepi kosongnya dan latarnya dibuat transparan,
 * supaya ukurannya di surat Word seragam. Gambar aslinya disimpan sebagai {kolom}_asli.*; waktu ubah berkas dipertahankan
 * (tanda "perlu cetak ulang" surat lama tidak berubah). Gambar yang diunggah sesudah perapi ada → dilewati. Tidak ada data
 * database yang diubah, kecuali nama berkas JPG lama yang kini menjadi PNG. Tanpa gambar → tidak melakukan apa pun.
 */
class RapikanTtd extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('pkl_pengaturan') || ! method_exists(PklSurat::class, 'rapikanTersimpan')) {
            return;
        }
        foreach (['ttd_hubin', 'ttd_kepsek'] as $kolom) {
            if (! $this->db->fieldExists($kolom, 'pkl_pengaturan')) {
                continue;
            }
            try {
                PklSurat::rapikanTersimpan($kolom);
            } catch (\Throwable $e) {
                log_message('error', '[Migrasi RapikanTtd] ' . $kolom . ': ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // Tidak ada yang dibalik: gambar asli tetap tersimpan sebagai {kolom}_asli.* (unggah ulang bila perlu).
    }
}
