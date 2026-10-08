<?php

namespace App\Database\Migrations;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Migration;

/**
 * PKL — BERSIHKAN DATA UJI COBA (mulai dari kosong, sekali jalan).
 *
 * Permintaan sekolah (2026-10-09): ajuan PKL yang masuk selama uji coba dibuang sebelum sistem dipakai sungguhan,
 * termasuk urutan nomor surat dan catatan biaya/beasiswa/keringanan hasil uji.
 *
 * DIHAPUS PERMANEN (tidak bisa dikembalikan lewat down()):
 *   - pkl_pembayaran, pkl_beasiswa, pkl_keringanan   catatan biaya hasil uji
 *   - pkl_riwayat, pkl_anggota                       jejak & siswa pada tiap ajuan (siswa kembali "belum mengisi")
 *   - pkl_surat                                      nomor surat terbit (penomoran kembali ke 001 / "nomor awal")
 *   - pkl_pengajuan                                  ajuan itu sendiri (nomor bukti PKL-xxxxx mulai lagi dari 1)
 *   - pkl_perusahaan                                 master perusahaan hasil ACC ajuan-ajuan itu
 *   - berkas pratinjau impor sementara di writable/pkl/impor_*.json
 *   Penghitung nomor (pkl_pengaturan.nomor_awal / nomor_awal_tahun) dikembalikan ke 1 / kosong.
 *
 * TIDAK disentuh: data siswa/kelas/guru, akun (admins), Pengaturan PKL lainnya (nama Waka Hubin, Kepala Sekolah,
 * format nomor, template & tanda tangan, saklar form, hak akses, pesan WhatsApp), jenis & nominal biaya (pkl_biaya),
 * serta Audit Log (ditambah satu catatan tentang pembersihan ini).
 *
 * PENGAMAN: bila ada ajuan yang dibuat pada/ sesudah BATAS_UJI, migrasi menganggap sistem SUDAH DIPAKAI SUNGGUHAN dan
 * TIDAK menghapus apa pun (hanya mencatat di Audit Log). Jadi migrasi ini tak mungkin menghapus data asli bila
 * terjalankan terlambat. Ubah BATAS_UJI hanya bila memang disengaja. Berjalan SEKALI (dicatat tabel migrations).
 *
 * WAJIB cadangkan database dulu (cPanel → Backup / phpMyAdmin → Export) sebelum `phpm spark migrate`.
 */
class PklBersihkanDataUji extends Migration
{
    /** Ajuan yang dibuat sebelum waktu ini dianggap data uji. */
    private const BATAS_UJI = '2026-10-13 00:00:00';

    /** Urutan aman terhadap kunci asing (anak dulu, induk belakangan). */
    private const TABEL = ['pkl_pembayaran', 'pkl_beasiswa', 'pkl_keringanan', 'pkl_riwayat', 'pkl_anggota', 'pkl_surat', 'pkl_pengajuan', 'pkl_perusahaan'];

    public function up()
    {
        $ada = array_values(array_filter(self::TABEL, fn (string $t): bool => $this->db->tableExists($t)));
        if (! in_array('pkl_pengajuan', $ada, true)) {
            return; // modul PKL belum terpasang di database ini
        }

        // Pengaman: ada ajuan SESUDAH batas → bukan lagi data uji.
        $baru = (int) $this->db->table('pkl_pengajuan')->where('created_at >=', self::BATAS_UJI)->countAllResults();
        if ($baru > 0) {
            $this->catatAudit('Pembersihan data uji PKL DILEWATI: ada ' . $baru . ' ajuan dibuat sejak ' . self::BATAS_UJI . ' (dianggap data sungguhan). Tidak ada yang dihapus.');
            $this->tulis('PKL: pembersihan data uji DILEWATI (ada ' . $baru . ' ajuan sejak ' . self::BATAS_UJI . ', dianggap data sungguhan).');

            return;
        }

        $jumlah = [];
        foreach ($ada as $tabel) {
            $jumlah[$tabel] = (int) $this->db->table($tabel)->countAllResults();
        }

        // Satu transaksi: semua terhapus, atau tidak ada yang terhapus.
        $this->db->transStart();
        foreach ($ada as $tabel) {
            $this->db->table($tabel)->emptyTable();
        }
        if ($this->db->tableExists('pkl_pengaturan')) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update(['nomor_awal' => 1, 'nomor_awal_tahun' => null]);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Pembersihan data uji PKL gagal; tidak ada data yang dihapus.');
        }

        // Penomoran kembali ke 1 (DDL, jadi di luar transaksi).
        foreach ($ada as $tabel) {
            $this->db->query('ALTER TABLE `' . $tabel . '` AUTO_INCREMENT = 1');
        }
        foreach (glob(WRITEPATH . 'pkl/impor_*.json') ?: [] as $berkas) {
            @unlink($berkas);
        }

        $ringkas = 'Data uji PKL dibersihkan lewat migrasi: ' . ($jumlah['pkl_pengajuan'] ?? 0) . ' ajuan, ' . ($jumlah['pkl_anggota'] ?? 0) . ' anggota, '
            . ($jumlah['pkl_surat'] ?? 0) . ' surat, ' . ($jumlah['pkl_pembayaran'] ?? 0) . ' catatan biaya, ' . ($jumlah['pkl_perusahaan'] ?? 0) . ' perusahaan master';
        if (array_sum($jumlah) > 0) {
            $this->catatAudit($ringkas);
        }
        $this->tulis('PKL: ' . $ringkas . '. Nomor surat mulai lagi dari 001.');
    }

    public function down()
    {
        // Data yang sudah dihapus tidak bisa dikembalikan. Pulihkan dari cadangan database bila diperlukan.
    }

    private function catatAudit(string $deskripsi): void
    {
        if ($this->db->tableExists('audit_log')) {
            $this->db->table('audit_log')->insert([
                'admin_id' => null, 'aksi' => 'delete', 'tabel' => 'pkl_pengajuan', 'record_id' => null,
                'deskripsi' => mb_substr($deskripsi, 0, 255), 'ip_address' => null, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function tulis(string $teks): void
    {
        if (is_cli()) {
            CLI::write("\t" . $teks, 'yellow');
        }
    }
}
