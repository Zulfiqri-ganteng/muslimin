<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PKL — format surat resmi sekolah + aturan ACC khusus Waka Hubin.
 *
 *   1. pkl_pengaturan — nama Kepala Sekolah, kontak "NB" di surat, gambar tanda tangan Waka Hubin,
 *                       pola nama berkas surat, batas hari keputusan Hubin; pola nomor surat bawaan
 *                       disesuaikan dengan surat sekolah (295/SMK-BN/PKL/VII/2026); maks siswa ≤ 5.
 *   2. pkl_pengajuan  — `diajukan_at` (jam siswa menekan Kirim; dasar batas keputusan Hubin) dan
 *                       "catatan ACC" yang TIDAK ikut berubah bila akun diganti namanya:
 *                       acc_nama, acc_peran (hubin|admin|operator|impor), acc_admin_id, acc_at,
 *                       acc_ip, acc_kode. Dicetak di kaki surat.
 *
 * Idempoten: aman dijalankan ulang. Lihat docs/DESAIN-PKL.md.
 */
class PklSuratSekolah extends Migration
{
    private const POLA_LAMA = '{urut}/PKL/{tgl}-{bln}-{thn}';
    private const POLA_BARU = '{urut}/SMK-BN/PKL/{bln_romawi}/{thn}';

    /** @return array<string, array<string, mixed>> */
    private function kolomPengaturan(): array
    {
        return [
            'kepsek_nama'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'kontak_surat_nama'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'kontak_surat_hp'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            // Nama berkas gambar tanda tangan Waka Hubin di writable/pkl/ (kosong = ruang dikosongkan untuk tanda tangan basah).
            'ttd_hubin'            => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'format_nama_berkas'   => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => '{urut} Surat Izin PKL BINUS - {nama_depan} {all} {kelas}'],
            // Waka Hubin memutuskan paling lambat sekian hari sejak siswa mengirim.
            'batas_keputusan_hari' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 5],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function kolomAjuan(): array
    {
        return [
            'diajukan_at'  => ['type' => 'DATETIME', 'null' => true],
            'acc_admin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'acc_nama'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'acc_peran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'acc_at'       => ['type' => 'DATETIME', 'null' => true],
            'acc_ip'       => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'acc_kode'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
        ];
    }

    public function up()
    {
        foreach ($this->kolomPengaturan() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'pkl_pengaturan')) {
                $this->forge->addColumn('pkl_pengaturan', [$nama => $definisi]);
            }
        }
        foreach ($this->kolomAjuan() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'pkl_pengajuan')) {
                $this->forge->addColumn('pkl_pengajuan', [$nama => $definisi]);
            }
        }

        // Nilai awal sesuai surat sekolah (hanya bila belum pernah diisi).
        $this->db->query("UPDATE pkl_pengaturan SET kepsek_nama = 'Napis Kuturupi, S.T' WHERE kepsek_nama IS NULL");
        $this->db->query("UPDATE pkl_pengaturan SET kontak_surat_nama = 'Puguh Wira Sakti, S.Pd.' WHERE kontak_surat_nama IS NULL");
        $this->db->query("UPDATE pkl_pengaturan SET kontak_surat_hp = '0812 8584 526' WHERE kontak_surat_hp IS NULL");

        // Pola nomor: ganti HANYA bila masih pola bawaan lama (pilihan yang sudah diubah staf dihormati).
        $this->db->query("UPDATE pkl_pengaturan SET format_nomor = ? WHERE format_nomor = ?", [self::POLA_BARU, self::POLA_LAMA]);
        $this->db->query("ALTER TABLE pkl_pengaturan ALTER COLUMN format_nomor SET DEFAULT '" . self::POLA_BARU . "'");

        // Batas maksimal siswa per ajuan = 5 (aturan sekolah).
        $this->db->query('UPDATE pkl_pengaturan SET maks_anggota = 5 WHERE maks_anggota > 5');

        // Ajuan yang sudah ada: jam kirim = jam dibuat; catatan ACC dipulihkan dari data keputusan lama.
        $this->db->query('UPDATE pkl_pengajuan SET diajukan_at = COALESCE(updated_at, created_at) WHERE diajukan_at IS NULL');
        $this->db->query(
            "UPDATE pkl_pengajuan p LEFT JOIN admins a ON a.id = p.diputuskan_oleh"
            . " SET p.acc_admin_id = p.diputuskan_oleh, p.acc_at = p.diputuskan_at,"
            . " p.acc_nama = COALESCE(a.full_name, 'Staf'),"
            . " p.acc_peran = CASE WHEN p.sumber = 'impor' THEN 'impor' ELSE COALESCE(a.role, 'admin') END"
            . " WHERE p.status = 'disetujui' AND p.acc_at IS NULL"
        );
    }

    public function down()
    {
        foreach (array_keys($this->kolomAjuan()) as $nama) {
            if ($this->db->fieldExists($nama, 'pkl_pengajuan')) {
                $this->forge->dropColumn('pkl_pengajuan', $nama);
            }
        }
        foreach (array_keys($this->kolomPengaturan()) as $nama) {
            if ($this->db->fieldExists($nama, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $nama);
            }
        }
    }
}
