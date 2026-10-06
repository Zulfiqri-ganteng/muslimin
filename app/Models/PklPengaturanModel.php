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

    /**
     * Mengapa form belum bisa diisi, atau null bila terbuka. Form terbuka bila:
     * saklar menyala, belum lewat batas waktu, ada tingkat yang diizinkan, DAN
     * pagar tanggal dari sekolah sudah diatur (tanpa pagar, salah ketik tahun
     * tak tertangkap).
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

        $awal  = (string) ($p['mulai_paling_awal'] ?? '');
        $akhir = (string) ($p['selesai_paling_akhir'] ?? '');
        if (self::tingkatBoleh($p) === [] || $awal === '' || $akhir === '' || $awal > $akhir) {
            return 'belum_siap';
        }

        return null;
    }

    public static function formTerbuka(array $p): bool
    {
        return self::alasanTutup($p) === null;
    }
}
