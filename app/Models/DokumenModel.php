<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Dokumen — satu baris = satu BERKAS (tersimpan di writable/uploads/dokumen)
 * atau satu TAUTAN eksternal (YouTube/Drive/URL apa pun).
 *
 * Soft delete dipakai sebagai TEMPAT SAMPAH: baris terhapus masih bisa
 * dipulihkan, berkas fisiknya baru benar-benar dibuang saat hapus permanen.
 */
class DokumenModel extends Model
{
    protected $table         = 'dokumen';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'folder_id', 'judul', 'deskripsi', 'tipe',
        'nama_asli', 'nama_file', 'path_rel', 'ekstensi', 'mime', 'ukuran',
        'hash_sha256', 'thumb', 'url_eksternal', 'penyedia',
        'kategori', 'visibilitas', 'jml_lihat', 'jml_unduh', 'created_by',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'judul'         => 'required|max_length[200]',
        'folder_id'     => 'permit_empty|is_natural',
        'tipe'          => 'permit_empty|in_list[berkas,tautan]',
        'kategori'      => 'permit_empty|in_list[pdf,dokumen,spreadsheet,presentasi,gambar,audio,video,arsip,lainnya]',
        'visibilitas'   => 'permit_empty|in_list[privat,link,publik]',
        'url_eksternal' => 'permit_empty|max_length[500]|valid_url_strict',
    ];
    protected $validationMessages = [
        'judul'         => ['required' => 'Judul dokumen wajib diisi.'],
        'url_eksternal' => ['valid_url_strict' => 'Tautan harus URL lengkap (diawali http:// atau https://).'],
    ];

    /** Kategori yang punya pratinjau bawaan di web. */
    public const KATEGORI_PRATINJAU = ['pdf', 'gambar', 'audio', 'spreadsheet', 'dokumen'];

    /**
     * Daftar isi sebuah folder + nama pengunggah.
     *
     * @param array<string, mixed> $filter q|kategori|visibilitas|urut|arah
     */
    public function daftar(?int $folderId, array $filter = [])
    {
        $b = $this->select('dokumen.*, admins.full_name AS pengunggah')
            ->join('admins', 'admins.id = dokumen.created_by', 'left');

        // folderId null = akar; false = jangan filter folder sama sekali (mode cari global)
        if ($folderId === null) {
            $b->where('dokumen.folder_id IS NULL', null, false);
        } else {
            $b->where('dokumen.folder_id', $folderId);
        }

        return $this->terapkanFilter($b, $filter);
    }

    /** Pencarian lintas folder (tidak dibatasi satu folder). */
    public function cariSemua(array $filter = [])
    {
        $b = $this->select('dokumen.*, admins.full_name AS pengunggah')
            ->join('admins', 'admins.id = dokumen.created_by', 'left');

        return $this->terapkanFilter($b, $filter);
    }

    /** Filter & pengurutan bersama untuk daftar maupun pencarian. */
    private function terapkanFilter($b, array $filter)
    {
        $q = trim((string) ($filter['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()
                ->like('dokumen.judul', $q)
                ->orLike('dokumen.nama_asli', $q)
                ->orLike('dokumen.deskripsi', $q)
                ->groupEnd();
        }

        foreach (['kategori', 'visibilitas', 'tipe'] as $kolom) {
            $nilai = trim((string) ($filter[$kolom] ?? ''));
            if ($nilai !== '') {
                $b->where('dokumen.' . $kolom, $nilai);
            }
        }

        $urutValid = ['judul', 'created_at', 'updated_at', 'ukuran', 'jml_unduh'];
        $urut      = in_array($filter['urut'] ?? '', $urutValid, true) ? $filter['urut'] : 'created_at';
        $arah      = strtoupper((string) ($filter['arah'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        return $b->orderBy('dokumen.' . $urut, $arah);
    }

    /** Satu dokumen + nama pengunggah + nama folder induk. */
    public function detail(int $id, bool $denganTerhapus = false): ?array
    {
        $b = $this->select('dokumen.*, admins.full_name AS pengunggah, dokumen_folder.nama AS folder_nama')
            ->join('admins', 'admins.id = dokumen.created_by', 'left')
            ->join('dokumen_folder', 'dokumen_folder.id = dokumen.folder_id', 'left');

        if ($denganTerhapus) {
            $b->withDeleted();
        }

        return $b->where('dokumen.id', $id)->first();
    }

    /** Isi tempat sampah (yang sudah di-soft-delete). */
    public function sampah()
    {
        return $this->select('dokumen.*, admins.full_name AS pengunggah')
            ->join('admins', 'admins.id = dokumen.created_by', 'left')
            ->onlyDeleted()
            ->orderBy('dokumen.deleted_at', 'DESC');
    }

    /** Tambah penghitung tanpa menyentuh updated_at (bukan perubahan isi). */
    public function tambahHitung(int $id, string $kolom): void
    {
        if (! in_array($kolom, ['jml_lihat', 'jml_unduh'], true)) {
            return;
        }
        $this->db->table($this->table)
            ->where('id', $id)
            ->set($kolom, $kolom . ' + 1', false)
            ->update();
    }

    /**
     * Rekap pemakaian penyimpanan: total byte & jumlah berkas, plus rincian
     * per kategori. Hanya menghitung tipe 'berkas' yang belum dibuang.
     *
     * @return array{total_byte:int, total_berkas:int, per_kategori:list<array<string,mixed>>, sampah_byte:int}
     */
    public function pemakaian(): array
    {
        $baris = $this->db->table($this->table)
            ->select('kategori, COUNT(*) AS jml, COALESCE(SUM(ukuran),0) AS byte_total')
            ->where('tipe', 'berkas')
            ->where('deleted_at IS NULL', null, false)
            ->groupBy('kategori')
            ->orderBy('byte_total', 'DESC')
            ->get()->getResultArray();

        $totalByte   = 0;
        $totalBerkas = 0;

        foreach ($baris as $r) {
            $totalByte += (int) $r['byte_total'];
            $totalBerkas += (int) $r['jml'];
        }

        $sampah = $this->db->table($this->table)
            ->selectSum('ukuran', 'byte_total')
            ->where('tipe', 'berkas')
            ->where('deleted_at IS NOT NULL', null, false)
            ->get()->getRowArray();

        return [
            'total_byte'   => $totalByte,
            'total_berkas' => $totalBerkas,
            'per_kategori' => $baris,
            'sampah_byte'  => (int) ($sampah['byte_total'] ?? 0),
        ];
    }

    /**
     * Cari dokumen dengan sidik jari isi yang sama (deteksi unggah ganda).
     * Mengembalikan baris pertama yang cocok, atau null.
     */
    public function serupa(string $hash, ?int $kecualiId = null): ?array
    {
        if ($hash === '') {
            return null;
        }
        $b = $this->where('hash_sha256', $hash)->where('tipe', 'berkas');
        if ($kecualiId !== null) {
            $b->where('id !=', $kecualiId);
        }

        return $b->first();
    }
}
