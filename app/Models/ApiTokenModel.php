<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengelola token API (Bearer) untuk aplikasi mobile.
 *
 * Alur:
 *  - issue()  : dipanggil saat login → menghasilkan token acak, menyimpan hash-nya,
 *               mengembalikan token asli (hanya sekali ini token mentah terlihat).
 *  - findValid(): dipakai filter untuk memvalidasi header Authorization: Bearer <token>.
 */
class ApiTokenModel extends Model
{
    protected $table            = 'api_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'admin_id', 'token_hash', 'device_name', 'last_used_at', 'expires_at', 'created_at',
    ];

    protected $useTimestamps = false;

    /** Berapa lama token berlaku (hari). Null = tidak kadaluarsa. */
    public const TTL_DAYS = 60;

    /**
     * Terbitkan token baru untuk seorang admin.
     *
     * @return string Token mentah (kirim ke klien, jangan disimpan di server).
     */
    public function issue(int $adminId, ?string $deviceName = null): string
    {
        $raw = bin2hex(random_bytes(32)); // 64 hex chars

        $this->insert([
            'admin_id'     => $adminId,
            'token_hash'   => hash('sha256', $raw),
            'device_name'  => $deviceName ? mb_substr($deviceName, 0, 150) : null,
            'last_used_at' => date('Y-m-d H:i:s'),
            'expires_at'   => self::TTL_DAYS ? date('Y-m-d H:i:s', strtotime('+' . self::TTL_DAYS . ' days')) : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $raw;
    }

    /**
     * Cari baris token yang valid (belum kadaluarsa) dari token mentah.
     *
     * @return array|null Baris api_tokens, atau null bila tidak valid.
     */
    public function findValid(string $rawToken): ?array
    {
        $row = $this->where('token_hash', hash('sha256', $rawToken))->first();
        if (! $row) {
            return null;
        }
        if (! empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            $this->delete($row['id']); // bersihkan token kadaluarsa
            return null;
        }
        return $row;
    }

    /** Perbarui stempel pemakaian terakhir (dipanggil setelah request tervalidasi). */
    public function touch(int $tokenId): void
    {
        $this->update($tokenId, ['last_used_at' => date('Y-m-d H:i:s')]);
    }

    /** Cabut satu token (logout dari satu perangkat). */
    public function revoke(string $rawToken): void
    {
        $this->where('token_hash', hash('sha256', $rawToken))->delete();
    }

    /** Cabut semua token milik admin (logout dari semua perangkat). */
    public function revokeAllFor(int $adminId): void
    {
        $this->where('admin_id', $adminId)->delete();
    }
}
