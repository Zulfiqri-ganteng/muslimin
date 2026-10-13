<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aturan hak akses SEKOLAH (keputusan klien 2026-10-13) dipasang lewat migrasi, supaya tidak perlu diklik manual di
 * Sistem → Hak Akses (docs/DESAIN-HAK-AKSES.md):
 *   - Surat Balasan PKL di-ACC OPERATOR saja: Operator Sekolah + `surat_acc:balasan_pkl`, Waka Hubin − `surat_acc:balasan_pkl`.
 *     Hak lain TIDAK disentuh (Waka Hubin tetap ACC ajuan PKL, Izin ASTS/TKA, Penarikan; Operator tetap Buat + Unduh semua jenis).
 *   - Keterangan peran (kolom `ringkas`) Operator & Waka Hubin disesuaikan — hanya bila masih teks bawaan (yang Operator terpotong
 *     300 huruf saat migrasi HakAksesPeran); teks yang sudah diubah Admin dibiarkan.
 *
 * Idempoten; peran/tabel yang tidak ada atau isian hak yang rusak dilewati. Migrasi hanya jalan sekali: setelah ini Admin tetap
 * bebas mengubahnya di Sistem → Hak Akses. down() = ACC Surat Balasan kembali ke Waka Hubin (aturan awal sistem). Tercatat di Audit Log.
 */
class AturanHakSekolah extends Migration
{
    private const KUNCI = 'surat_acc:balasan_pkl';

    /** kode peran => [awal teks bawaan lama (penanda belum diubah Admin), teks baru] */
    private const RINGKAS = [
        'operator' => [
            'Kerja harian: PKL (periksa, kembalikan, ubah, isi atas nama',
            'Kerja harian: PKL (periksa, kembalikan, ubah, isi atas nama, unduh surat + catat biaya, laporan pembayaran, pengaturan), Surat Sekolah (buat & unduh semua jenis + ACC Surat Balasan), Isian Biodata, Master Siswa & Kelas, Arsip Dokumen. Hak persisnya diatur Admin di Sistem → Hak Akses.',
        ],
        'hubin' => [
            'Hanya menu PKL dan Surat Sekolah (Daftar Surat untuk ACC)',
            'Menu PKL dan Surat Sekolah: ACC ajuan PKL, ACC Izin ASTS, Izin TKA & Penarikan PKL, ubah data, tanda tangan digital (Surat Balasan di-ACC Operator). Tidak mengunduh surat, tidak menghapus, tidak mengatur form. Hak persisnya diatur Admin di Sistem → Hak Akses.',
        ],
    ];

    public function up()
    {
        if (! $this->db->tableExists('peran')) {
            return;
        }
        $catat = [];
        if ($this->aturHak('operator', true)) {
            $catat[] = 'Operator Sekolah + ACC Surat Balasan';
        }
        if ($this->aturHak('hubin', false)) {
            $catat[] = 'Waka Hubin − ACC Surat Balasan';
        }
        foreach (self::RINGKAS as $kode => [$awalLama, $baru]) {
            $r = $this->db->table('peran')->select('ringkas')->where('kode', $kode)->get()->getRowArray();
            if ($r !== null && str_starts_with((string) $r['ringkas'], $awalLama)) {
                $this->db->table('peran')->where('kode', $kode)->update(['ringkas' => mb_substr($baru, 0, 300), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->audit('Aturan sekolah dipasang lewat migrasi (Surat Balasan di-ACC Operator saja)', $catat);
    }

    public function down()
    {
        if (! $this->db->tableExists('peran')) {
            return;
        }
        $catat = [];
        if ($this->aturHak('hubin', true)) {
            $catat[] = 'Waka Hubin + ACC Surat Balasan';
        }
        if ($this->aturHak('operator', false)) {
            $catat[] = 'Operator Sekolah − ACC Surat Balasan';
        }
        // Keterangan peran kembali ke teks bawaan (Config\Peran) — hanya bila masih teks dari migrasi ini.
        foreach (self::RINGKAS as $kode => [, $baru]) {
            $r    = $this->db->table('peran')->select('ringkas')->where('kode', $kode)->get()->getRowArray();
            $awal = (string) (config(\Config\Peran::class)->peran[$kode]['ringkas'] ?? '');
            if ($r !== null && $awal !== '' && (string) $r['ringkas'] === mb_substr($baru, 0, 300)) {
                $this->db->table('peran')->where('kode', $kode)->update(['ringkas' => mb_substr($awal, 0, 300), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->audit('Aturan sekolah dibatalkan lewat migrasi (ACC Surat Balasan kembali ke Waka Hubin)', $catat);
    }

    /** Beri ($beri = true) atau cabut hak ACC Surat Balasan dari satu peran. true bila ada yang berubah. */
    private function aturHak(string $kode, bool $beri): bool
    {
        $r = $this->db->table('peran')->select('hak')->where('kode', $kode)->get()->getRowArray();
        if ($r === null) {
            return false;
        }
        $hak = json_decode((string) $r['hak'], true);
        if (! is_array($hak)) {
            return false; // isian rusak: dibiarkan (sistem memakai aturan bawaan untuk peran ini)
        }
        $hak = array_values(array_map('strval', $hak));
        if (in_array(self::KUNCI, $hak, true) === $beri) {
            return false;
        }
        $hak = $beri ? array_merge($hak, [self::KUNCI]) : array_values(array_diff($hak, [self::KUNCI]));
        $this->db->table('peran')->where('kode', $kode)->update(['hak' => json_encode($hak), 'updated_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /** @param list<string> $catat */
    private function audit(string $judul, array $catat): void
    {
        if ($catat === [] || ! $this->db->tableExists('audit_log')) {
            return;
        }
        $this->db->table('audit_log')->insert([
            'admin_id' => null, 'aksi' => 'update', 'tabel' => 'peran', 'record_id' => null,
            'deskripsi' => mb_substr($judul . ': ' . implode('; ', $catat), 0, 255), 'ip_address' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
