<?php

namespace App\Libraries;

/**
 * Sesi Aktif (Sistem → Sesi Aktif; docs/DESAIN-ALAT-SISTEM.md F5). Sesi WEB dicatat di tabel sesi_admin (sidik sha256 id sesi):
 * dibuat saat login, aktivitasnya diperbarui AuthFilter paling sering tiap SENTUH detik, ditandai keluar saat logout / habis waktu.
 * "Keluarkan" = tandai dicabut → permintaan berikutnya dari sesi itu langsung keluar (AuthFilter). Sesi ANDROID = api_tokens;
 * "keluarkan" = hapus token → aplikasi mendapat 401 lalu keluar sendiri.
 */
class SesiAktif
{
    /** Sesi web dianggap aktif bila ada aktivitas dalam batas ini (sama dengan keluar otomatis AuthFilter). */
    public const BATAS_AKTIF = 3600;

    /** Jeda minimal antar-pembaruan aktif_at (detik) — supaya tidak menulis ke database tiap klik. */
    public const SENTUH = 300;

    public const SIMPAN_HARI = 30;

    public const PESAN_DIKELUARKAN = 'Sesi Anda dihentikan oleh Admin. Silakan login kembali.';

    public static function sidik(?string $sid = null): string
    {
        return hash('sha256', (string) ($sid ?? self::sid()));
    }

    private static ?string $sidUji = null;

    /** Uji (CLI tidak punya sesi PHP): pakai id sesi tiruan. null = kembali normal. */
    public static function pakaiSid(?string $sid): void
    {
        self::$sidUji = $sid;
    }

    /** Id sesi PHP saat ini (sesi dibuka dulu bila belum — mis. di halaman logout yang tanpa penyaring auth). '' di CLI. */
    private static function sid(): string
    {
        if (self::$sidUji !== null) {
            return self::$sidUji;
        }
        if (is_cli() && session_id() === '') {
            return '';
        }
        session();

        return (string) session_id();
    }

    private static function ada(): bool
    {
        static $ada = null;

        return $ada ??= db_connect()->tableExists('sesi_admin');
    }

    /** Dipanggil sesudah login web berhasil (sesudah regenerate). */
    public static function catatMasuk(int $adminId, string $ip, string $userAgent): void
    {
        if (! self::ada() || self::sid() === '') {
            return;
        }
        $db = db_connect();
        $db->table('sesi_admin')->where('aktif_at <', date('Y-m-d H:i:s', time() - self::SIMPAN_HARI * 86400))->delete();
        $sekarang = date('Y-m-d H:i:s');
        $db->table('sesi_admin')->ignore(true)->insert(['admin_id' => $adminId, 'sesi_hash' => self::sidik(), 'ip' => mb_substr($ip, 0, 45),
            'perangkat' => self::perangkat($userAgent), 'masuk_at' => $sekarang, 'aktif_at' => $sekarang]);
    }

    /**
     * Dipanggil AuthFilter tiap permintaan: null = boleh lanjut; string = pesan (sesi sudah dikeluarkan Admin).
     * Sesi lama tanpa catatan (login sebelum fitur ini) dicatat saat itu juga.
     */
    public static function periksa(int $adminId, string $ip, string $userAgent): ?string
    {
        if (! self::ada() || self::sid() === '') {
            return null;
        }
        $db  = db_connect();
        $row = $db->table('sesi_admin')->where('sesi_hash', self::sidik())->get()->getRowArray();
        if ($row === null) {
            self::catatMasuk($adminId, $ip, $userAgent);

            return null;
        }
        if ((int) $row['admin_id'] !== $adminId) {
            return self::PESAN_DIKELUARKAN; // sidik sesi milik akun lain — tidak wajar, paksa masuk ulang
        }
        if ($row['keluar_at'] !== null) {
            return $row['keluar_alasan'] === 'dicabut' ? self::PESAN_DIKELUARKAN : 'Sesi Anda sudah berakhir. Silakan login kembali.';
        }
        if (strtotime((string) $row['aktif_at']) < time() - self::SENTUH) {
            $db->table('sesi_admin')->where('id', (int) $row['id'])->update(['aktif_at' => date('Y-m-d H:i:s'), 'ip' => mb_substr($ip, 0, 45)]);
        }

        return null;
    }

    /** Logout / keluar otomatis: tandai sesi ini berakhir. alasan: keluar | habis */
    public static function catatKeluar(string $alasan = 'keluar'): void
    {
        if (! self::ada() || self::sid() === '') {
            return;
        }
        db_connect()->table('sesi_admin')->where('sesi_hash', self::sidik())->where('keluar_at', null)
            ->update(['keluar_at' => date('Y-m-d H:i:s'), 'keluar_alasan' => $alasan]);
    }

    /**
     * Daftar sesi web aktif (aktivitas < BATAS_AKTIF) + sesi Android (token belum kedaluwarsa), terbaru dulu.
     *
     * @return array{web: list<array>, android: list<array>}
     */
    public static function daftar(?string $sidikSaya = null, ?int $tokenSaya = null): array
    {
        $db  = db_connect();
        $web = self::ada() ? $db->table('sesi_admin s')->select('s.id, s.admin_id, s.ip, s.perangkat, s.masuk_at, s.aktif_at, s.sesi_hash, a.full_name, a.username, a.role')
            ->join('admins a', 'a.id = s.admin_id')->where('s.keluar_at', null)
            ->where('s.aktif_at >=', date('Y-m-d H:i:s', time() - self::BATAS_AKTIF))->orderBy('s.aktif_at', 'DESC')->get()->getResultArray() : [];
        $android = $db->table('api_tokens t')->select('t.id, t.admin_id, t.device_name, t.created_at, t.last_used_at, t.expires_at, a.full_name, a.username, a.role')
            ->join('admins a', 'a.id = t.admin_id')->groupStart()->where('t.expires_at', null)->orWhere('t.expires_at >', date('Y-m-d H:i:s'))->groupEnd()
            ->orderBy('t.last_used_at', 'DESC')->get()->getResultArray();

        return [
            'web' => array_map(static fn (array $r): array => ['jenis' => 'web', 'id' => (int) $r['id'], 'admin_id' => (int) $r['admin_id'], 'nama' => (string) $r['full_name'],
                'username' => (string) $r['username'], 'peran' => (string) $r['role'], 'peran_label' => HakAkses::label($r['role']), 'perangkat' => (string) $r['perangkat'],
                'ip' => (string) $r['ip'], 'masuk' => $r['masuk_at'], 'aktif' => $r['aktif_at'], 'ini' => $sidikSaya !== null && hash_equals($r['sesi_hash'], $sidikSaya)], $web),
            'android' => array_map(static fn (array $r): array => ['jenis' => 'android', 'id' => (int) $r['id'], 'admin_id' => (int) $r['admin_id'], 'nama' => (string) $r['full_name'],
                'username' => (string) $r['username'], 'peran' => (string) $r['role'], 'peran_label' => HakAkses::label($r['role']), 'perangkat' => (string) ($r['device_name'] ?: 'Perangkat Android'),
                'ip' => '', 'masuk' => $r['created_at'], 'aktif' => $r['last_used_at'], 'kedaluwarsa' => $r['expires_at'], 'ini' => $tokenSaya !== null && (int) $r['id'] === $tokenSaya], $android),
        ];
    }

    /** Keluarkan satu sesi. @return bool berhasil (false = tidak ditemukan / sudah keluar) */
    public static function keluarkan(string $jenis, int $id, string $oleh): bool
    {
        $db = db_connect();
        if ($jenis === 'android') {
            $db->table('api_tokens')->where('id', $id)->delete();

            return $db->affectedRows() > 0;
        }
        if (! self::ada()) {
            return false;
        }
        $db->table('sesi_admin')->where('id', $id)->where('keluar_at', null)
            ->update(['keluar_at' => date('Y-m-d H:i:s'), 'keluar_alasan' => 'dicabut', 'dikeluarkan_oleh' => mb_substr($oleh, 0, 150)]);

        return $db->affectedRows() > 0;
    }

    /**
     * Keluarkan semua sesi (web + Android) KECUALI milik akun $kecualiAdmin (diri sendiri), atau hanya akun $akun bila diisi.
     *
     * @return array{web: int, android: int}
     */
    public static function keluarkanSemua(string $oleh, int $kecualiAdmin, ?int $akun = null): array
    {
        $db = db_connect();
        $n  = ['web' => 0, 'android' => 0];
        if (self::ada()) {
            $q = $db->table('sesi_admin')->where('keluar_at', null)->where('admin_id !=', $kecualiAdmin);
            if ($akun !== null) {
                $q->where('admin_id', $akun);
            }
            $q->update(['keluar_at' => date('Y-m-d H:i:s'), 'keluar_alasan' => 'dicabut', 'dikeluarkan_oleh' => mb_substr($oleh, 0, 150)]);
            $n['web'] = $db->affectedRows();
        }
        $q = $db->table('api_tokens')->where('admin_id !=', $kecualiAdmin);
        if ($akun !== null) {
            $q->where('admin_id', $akun);
        }
        $q->delete();
        $n['android'] = $db->affectedRows();

        return $n;
    }

    /** "Chrome 130 · Windows 10" / "Safari · iOS (HP)" dari teks User-Agent. */
    public static function perangkat(string $ua): string
    {
        $a = new \CodeIgniter\HTTP\UserAgent();
        $a->parse($ua);
        $peramban = $a->getBrowser() !== '' ? $a->getBrowser() . ($a->getVersion() !== '' ? ' ' . explode('.', $a->getVersion())[0] : '') : 'Peramban';
        $sistem   = $a->getPlatform() !== '' && $a->getPlatform() !== 'Unknown Platform' ? $a->getPlatform() : '';

        return mb_substr($peramban . ($sistem !== '' ? ' · ' . $sistem : '') . ($a->isMobile() ? ' (HP)' : ''), 0, 150);
    }
}
