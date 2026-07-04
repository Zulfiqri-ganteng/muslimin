<?php

namespace App\Libraries;

/**
 * Penampung sederhana untuk admin yang sedang terautentikasi via token API.
 *
 * Karena API bersifat stateless (tanpa session), filter ApiAuth menaruh data
 * admin di sini setelah token tervalidasi, lalu controller membacanya melalui
 * BaseApiController::admin(). Bersifat statik agar tak perlu registrasi service.
 */
final class ApiAuth
{
    private static ?array $admin = null;
    private static ?int $tokenId = null;

    public static function set(array $admin, ?int $tokenId = null): void
    {
        self::$admin   = $admin;
        self::$tokenId = $tokenId;
    }

    public static function admin(): ?array
    {
        return self::$admin;
    }

    public static function id(): ?int
    {
        return self::$admin['id'] ?? null;
    }

    public static function tokenId(): ?int
    {
        return self::$tokenId;
    }

    public static function clear(): void
    {
        self::$admin   = null;
        self::$tokenId = null;
    }
}
