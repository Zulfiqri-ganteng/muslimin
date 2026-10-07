<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengaturan PKL — baris tunggal (id = 1) di tabel pkl_pengaturan.
 *
 * Sengaja TANPA cache: form publik hanya membaca satu baris berkunci utama,
 * dan saklar buka/tutup harus langsung berlaku begitu staf menekannya.
 */
class PklPengaturanModel extends Model
{
    protected $table            = 'pkl_pengaturan';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'form_buka', 'form_tutup', 'tingkat', 'mulai_paling_awal', 'selesai_paling_akhir',
        'durasi_min_hari', 'durasi_maks_hari', 'maks_anggota',
        'waka_hubin_nama', 'waka_hubin_nip', 'waka_hubin_jabatan', 'format_nomor', 'nomor_awal', 'nomor_awal_tahun', 'template_surat',
        'kepsek_nama', 'kontak_surat_nama', 'kontak_surat_hp', 'ttd_hubin', 'format_nama_berkas', 'batas_keputusan_hari',
    ];
    protected $useTimestamps = true;
    protected $createdField  = '';
    protected $updatedField  = 'updated_at';

    /** Tingkat yang dikenal (sama dengan enum kelas.tingkat). */
    public const TINGKAT = ['X', 'XI', 'XII'];

    /** Baris pengaturan (dibuat bila hilang, mis. tabel dikosongkan manual). */
    public function ambil(): array
    {
        $row = $this->find(1);
        if ($row === null) {
            $this->db->table($this->table)->insert(['id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $row = $this->find(1);
        }

        return $row ?? [];
    }

    /**
     * Tingkat yang boleh mengajukan, urut X → XI → XII, hanya nilai yang sah.
     *
     * @return list<string>
     */
    public static function tingkatBoleh(array $p): array
    {
        $dipilih = array_map('trim', explode(',', (string) ($p['tingkat'] ?? '')));

        return array_values(array_intersect(self::TINGKAT, $dipilih));
    }

    /** Batas hari keputusan Waka Hubin (bawaan 5). */
    public static function batasHari(array $p): int
    {
        return max(1, min(30, (int) ($p['batas_keputusan_hari'] ?? 5) ?: 5));
    }

    /** Maksimal siswa per ajuan: pengaturan sekolah, tak pernah lebih dari batas keras 5. */
    public static function maksSiswa(array $p): int
    {
        return max(1, min(\App\Libraries\PklForm::MAKS_SISWA, (int) ($p['maks_anggota'] ?? \App\Libraries\PklForm::MAKS_SISWA)));
    }

    /**
     * Mengapa form belum bisa diisi, atau null bila terbuka. Form terbuka bila:
     * saklar menyala, belum lewat batas waktu, dan ada tingkat yang diizinkan.
     * (Pagar tanggal PKL tidak lagi dipakai: siswa tidak mengisi tanggal.)
     *
     * @return 'belum_dibuka'|'sudah_ditutup'|'belum_siap'|null
     */
    public static function alasanTutup(array $p): ?string
    {
        if (empty($p['form_buka'])) {
            return 'belum_dibuka';
        }

        $tutup = $p['form_tutup'] ?? null;
        if ($tutup !== null && $tutup !== '' && strtotime((string) $tutup) <= time()) {
            return 'sudah_ditutup';
        }

        if (self::tingkatBoleh($p) === []) {
            return 'belum_siap';
        }

        return null;
    }

    public static function formTerbuka(array $p): bool
    {
        return self::alasanTutup($p) === null;
    }
}
