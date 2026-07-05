<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kredensial login sidik jari (biometrik) per-perangkat.
 *
 * Server menyimpan hanya HASH sebuah "device secret" acak. Secret asli
 * diberikan ke aplikasi sekali saat register, lalu disimpan aplikasi di
 * secure storage yang dibuka sidik jari. Login = tukar secret → token API.
 */
class BiometricCredentialModel extends Model
{
    protected $table            = 'biometric_credentials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'admin_id', 'device_id', 'secret_hash', 'device_name', 'last_used_at', 'created_at', 'updated_at',
    ];

    protected $useTimestamps = false;

    /**
     * Daftarkan / perbarui kredensial biometrik untuk sebuah perangkat.
     * Satu device_id hanya boleh terikat ke satu admin (register ulang = timpa).
     *
     * @return string Secret mentah (kirim ke aplikasi sekali, jangan disimpan di server).
     */
    public function register(int $adminId, string $deviceId, ?string $deviceName = null): string
    {
        $raw  = bin2hex(random_bytes(32)); // 64 hex chars, entropi tinggi
        $now  = date('Y-m-d H:i:s');
        $hash = hash('sha256', $raw);

        $existing = $this->where('device_id', $deviceId)->first();
        if ($existing) {
            $this->update($existing['id'], [
                'admin_id'     => $adminId,
                'secret_hash'  => $hash,
                'device_name'  => $deviceName ? mb_substr($deviceName, 0, 150) : $existing['device_name'],
                'last_used_at' => null,
                'updated_at'   => $now,
            ]);
        } else {
            $this->insert([
                'admin_id'     => $adminId,
                'device_id'    => mb_substr($deviceId, 0, 190),
                'secret_hash'  => $hash,
                'device_name'  => $deviceName ? mb_substr($deviceName, 0, 150) : null,
                'last_used_at' => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        return $raw;
    }

    /**
     * Verifikasi secret untuk sebuah perangkat. Aman-timing via hash_equals.
     *
     * @return array|null Baris kredensial bila cocok, atau null.
     */
    public function verify(string $deviceId, string $rawSecret): ?array
    {
        $row = $this->where('device_id', $deviceId)->first();
        if (! $row) {
            return null;
        }
        if (! hash_equals($row['secret_hash'], hash('sha256', $rawSecret))) {
            return null;
        }
        $this->update($row['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
        return $row;
    }

    /** Apakah admin ini punya biometrik aktif di perangkat tsb. */
    public function isEnabled(int $adminId, string $deviceId): bool
    {
        return (bool) $this->where('device_id', $deviceId)
            ->where('admin_id', $adminId)
            ->first();
    }

    /** Matikan biometrik untuk satu perangkat milik admin. */
    public function disableForDevice(int $adminId, string $deviceId): void
    {
        $this->where('admin_id', $adminId)->where('device_id', $deviceId)->delete();
    }

    /** Matikan biometrik untuk semua perangkat milik admin. */
    public function disableAllFor(int $adminId): void
    {
        $this->where('admin_id', $adminId)->delete();
    }
}
