<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Foto sekolah yang diunggah Admin (menu Arsip & Info → Foto Sekolah) dan tampil di halaman publik: banner beranda (bila
 * "Tampilkan di banner"), pita "Tentang sekolah", galeri halaman Profil. Berkas WEBP di public/uploads/foto-sekolah/.
 * Rancangan: docs/RENCANA-TAMPILAN-PUBLIK.md (P2a).
 */
class FotoSekolahModel extends Model
{
    public const FOLDER = 'foto-sekolah';
    public const CACHE  = 'foto_sekolah_publik';

    protected $table         = 'foto_sekolah';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['judul', 'keterangan', 'berkas', 'tautan', 'tampil_banner', 'urutan', 'is_aktif'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'judul'      => 'required|max_length[150]',
        'keterangan' => 'permit_empty|max_length[255]',
        'tautan'     => 'permit_empty|max_length[255]|valid_url_strict[https,http]',
        'urutan'     => 'permit_empty|integer',
    ];
    protected $validationMessages = [
        'judul'  => ['required' => 'Judul foto wajib diisi.', 'max_length' => 'Judul foto paling banyak 150 huruf.'],
        'tautan' => ['valid_url_strict' => 'Tautan harus alamat lengkap, mis. https://contoh.sch.id/kegiatan.'],
    ];

    /** Urutan tampil: angka urutan kecil dulu, lalu yang terbaru. */
    public function terurut(): self
    {
        return $this->orderBy('urutan', 'ASC')->orderBy('id', 'DESC');
    }

    /** Foto aktif untuk halaman publik (di-cache 10 menit; dibuang tiap kali Admin mengubah foto). */
    public function untukPublik(): array
    {
        return cache()->remember(self::CACHE, 600, fn () => $this->where('is_aktif', 1)->terurut()->findAll(24));
    }

    /**
     * Foto utama untuk "Tentang sekolah" & pembuka Profil: foto pertama yang TIDAK dipakai banner (agar tak dobel dengan banner);
     * bila semua foto masuk banner, foto pertama.
     */
    public static function utama(array $foto): ?array
    {
        foreach ($foto as $f) {
            if ((int) $f['tampil_banner'] === 0) {
                return $f;
            }
        }

        return $foto[0] ?? null;
    }
}
