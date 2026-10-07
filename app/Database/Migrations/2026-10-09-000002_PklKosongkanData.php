<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PKL — KOSONGKAN SEMUA DATA AJUAN (mulai dari nol, sekali jalan).
 *
 * Permintaan sekolah (2026-10-07): data uji coba ajuan PKL dibuang sebelum dipakai sungguhan.
 * DIHAPUS PERMANEN — tidak bisa dikembalikan lewat down():
 *   - pkl_riwayat    jejak kejadian tiap ajuan
 *   - pkl_anggota    siswa pada tiap ajuan (siswa jadi "belum mengisi" lagi dan bebas mengajukan)
 *   - pkl_surat      nomor surat yang sudah terbit (penomoran kembali ke "nomor_awal" di Pengaturan PKL)
 *   - pkl_pengajuan  ajuan itu sendiri (nomor bukti PKL-xxxxx mulai lagi dari PKL-00001)
 *   - pkl_perusahaan daftar perusahaan master yang terbentuk dari ACC ajuan-ajuan itu
 *
 * TIDAK disentuh: Master Siswa/Kelas/Guru (data siswa tetap utuh), akun (admins), Pengaturan PKL
 * (nama Waka Hubin, Kepala Sekolah, pola nomor, gambar tanda tangan, saklar form), Audit Log
 * (ditambah satu catatan tentang pengosongan ini).
 *
 * Berjalan SEKALI saja (dicatat tabel migrations). Di database yang sudah kosong tidak berbuat apa-apa.
 * Disarankan mencadangkan database dulu (cPanel → Backup / phpMyAdmin → Export). Lihat docs/DESAIN-PKL.md.
 */
class PklKosongkanData extends Migration
{
    /** Urutan aman terhadap kunci asing (anak dulu, induk belakangan). */
    private const TABEL = ['pkl_riwayat', 'pkl_anggota', 'pkl_surat', 'pkl_pengajuan', 'pkl_perusahaan'];

    public function up()
    {
        $ada    = array_values(array_filter(self::TABEL, fn (string $t): bool => $this->db->tableExists($t)));
        $jumlah = [];
        foreach ($ada as $tabel) {
            $jumlah[$tabel] = (int) $this->db->table($tabel)->countAllResults();
        }

        // Satu transaksi: semua terhapus, atau tidak ada yang terhapus.
        $this->db->transStart();
        foreach ($ada as $tabel) {
            $this->db->table($tabel)->emptyTable();
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Pengosongan data PKL gagal; tidak ada data yang dihapus.');
        }

        // Penomoran kembali ke 1 (DDL, jadi di luar transaksi).
        foreach ($ada as $tabel) {
            $this->db->query('ALTER TABLE `' . $tabel . '` AUTO_INCREMENT = 1');
        }

        if ($this->db->tableExists('audit_log') && array_sum($jumlah) > 0) {
            $this->db->table('audit_log')->insert([
                'admin_id'   => null,
                'aksi'       => 'delete',
                'tabel'      => 'pkl_pengajuan',
                'record_id'  => null,
                'deskripsi'  => mb_substr('Data PKL dikosongkan lewat migrasi: ' . ($jumlah['pkl_pengajuan'] ?? 0) . ' ajuan, ' . ($jumlah['pkl_anggota'] ?? 0)
                    . ' anggota, ' . ($jumlah['pkl_surat'] ?? 0) . ' surat, ' . ($jumlah['pkl_perusahaan'] ?? 0) . ' perusahaan master', 0, 255),
                'ip_address' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        // Data yang sudah dihapus tidak bisa dikembalikan. Pulihkan dari cadangan database bila diperlukan.
    }
}
