<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tautan berbagi bertoken — untuk membagikan dokumen/folder ke guru yang
 * TIDAK punya akun (guru di sistem ini data master, bukan pengguna).
 *
 * Satu baris menunjuk ke dokumen_id ATAU folder_id, tidak keduanya.
 */
class DokumenShareModel extends Model
{
    protected $table         = 'dokumen_share';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'dokumen_id', 'folder_id', 'token', 'password_hash', 'expired_at',
        'boleh_unduh', 'maks_unduh', 'jml_akses', 'jml_unduh', 'catatan',
        'aktif', 'created_by',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $validationRules = [
        'id'         => 'permit_empty|is_natural',
        'token'      => 'required|max_length[64]|is_unique[dokumen_share.token,id,{id}]',
        'dokumen_id' => 'permit_empty|is_natural',
        'folder_id'  => 'permit_empty|is_natural',
        'maks_unduh' => 'permit_empty|is_natural',
    ];

    /** Alasan sebuah tautan tidak bisa dipakai — dipakai halaman penerima. */
    public const TOLAK_TIDAK_ADA   = 'tidak_ada';
    public const TOLAK_DICABUT     = 'dicabut';
    public const TOLAK_KEDALUWARSA = 'kedaluwarsa';
    public const TOLAK_HABIS       = 'habis';

    /** Buat token acak yang belum terpakai. */
    public function tokenBaru(): string
    {
        do {
            $token = bin2hex(random_bytes(16));   // 32 karakter heksadesimal
        } while ($this->where('token', $token)->countAllResults() > 0);

        return $token;
    }

    /** Ambil baris tautan berdasarkan token (tanpa memeriksa keabsahan). */
    public function byToken(string $token): ?array
    {
        $token = trim($token);

        return $token === '' ? null : $this->where('token', $token)->first();
    }

    /**
     * Periksa apakah tautan masih boleh dipakai.
     *
     * @return array{ok:bool, alasan:?string, row:?array<string,mixed>}
     */
    public function periksa(string $token): array
    {
        $row = $this->byToken($token);

        if ($row === null) {
            return ['ok' => false, 'alasan' => self::TOLAK_TIDAK_ADA, 'row' => null];
        }
        if ((int) $row['aktif'] !== 1) {
            return ['ok' => false, 'alasan' => self::TOLAK_DICABUT, 'row' => $row];
        }
        if (! empty($row['expired_at']) && strtotime((string) $row['expired_at']) < time()) {
            return ['ok' => false, 'alasan' => self::TOLAK_KEDALUWARSA, 'row' => $row];
        }
        if ($row['maks_unduh'] !== null && (int) $row['jml_unduh'] >= (int) $row['maks_unduh']) {
            return ['ok' => false, 'alasan' => self::TOLAK_HABIS, 'row' => $row];
        }

        return ['ok' => true, 'alasan' => null, 'row' => $row];
    }

    /** Apakah tautan ini dikunci kata sandi? */
    public function pakaiSandi(array $row): bool
    {
        return trim((string) ($row['password_hash'] ?? '')) !== '';
    }

    /** Cocokkan kata sandi tautan. */
    public function sandiCocok(array $row, string $sandi): bool
    {
        return $this->pakaiSandi($row) && password_verify($sandi, (string) $row['password_hash']);
    }

    /** Tambah penghitung akses/unduh tanpa menyentuh updated_at. */
    public function tambahHitung(int $id, string $kolom): void
    {
        if (! in_array($kolom, ['jml_akses', 'jml_unduh'], true)) {
            return;
        }
        $this->db->table($this->table)
            ->where('id', $id)
            ->set($kolom, $kolom . ' + 1', false)
            ->update();
    }

    /** Semua tautan untuk satu dokumen (terbaru dulu). */
    public function untukDokumen(int $dokumenId): array
    {
        return $this->where('dokumen_id', $dokumenId)->orderBy('created_at', 'DESC')->findAll();
    }

    /** Semua tautan untuk satu folder (terbaru dulu). */
    public function untukFolder(int $folderId): array
    {
        return $this->where('folder_id', $folderId)->orderBy('created_at', 'DESC')->findAll();
    }

    /** Daftar tautan aktif + judul sasarannya, untuk halaman kelola berbagi. */
    public function daftarLengkap()
    {
        return $this->select('dokumen_share.*, dokumen.judul AS dokumen_judul, dokumen_folder.nama AS folder_nama, admins.full_name AS pembuat')
            ->join('dokumen', 'dokumen.id = dokumen_share.dokumen_id', 'left')
            ->join('dokumen_folder', 'dokumen_folder.id = dokumen_share.folder_id', 'left')
            ->join('admins', 'admins.id = dokumen_share.created_by', 'left')
            ->orderBy('dokumen_share.created_at', 'DESC');
    }
}
