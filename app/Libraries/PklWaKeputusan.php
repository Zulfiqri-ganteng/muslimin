<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Kabar WhatsApp MANUAL ke siswa saat ajuan PKL DIKEMBALIKAN untuk diperbaiki (termasuk persetujuan yang dibatalkan, statusnya
 * juga "perbaikan") atau DITOLAK — pola sama dengan PklWa: sistem menyiapkan nomor + pesan + tautan wa.me, staf menekan Kirim
 * dari WhatsApp-nya sendiri (tanpa gateway, tanpa biaya).
 *
 * Penerima = SEMUA anggota kelompok; pengaju ditandai "yang memperbaiki" (hanya pengaju yang bisa membuka ajuan perbaikan di
 * form PKL dengan nomor HP-nya). Nomor = HP yang diisi siswa di form PKL (pkl_anggota.hp), cadangan no_hp Master Siswa.
 * Templat diubah di Pengaturan PKL (pkl_pengaturan.wa_pesan_perbaikan / wa_pesan_tolak; kosong = pesan bawaan).
 * "Sudah dikabari" = pkl_anggota.kabar_keputusan_at TIDAK lebih awal dari keputusan terakhir (pkl_pengajuan.diputuskan_at),
 * jadi tandanya kosong lagi bila ajuan dikembalikan/ditolak ulang.
 * Hak: pemegang hak PKL 'acc', 'ubah', ATAU 'surat' (diatur Admin di Sistem → Hak Akses) — bawaan Waka Hubin & Operator.
 * Kolom dari migrasi 2026-10-21-000001_PklWaKeputusan; sebelum migrasi dijalankan fitur ini disembunyikan (kolomAda()).
 */
final class PklWaKeputusan
{
    public const HAK    = 'acc|ubah|surat';
    public const STATUS = ['perbaikan', 'ditolak'];

    public const PESAN_PERBAIKAN = 'Halo {nama}, ajuan PKL kamu ke {perusahaan} ({kode}) DIKEMBALIKAN sekolah untuk diperbaiki. Catatan: {alasan}. Pengaju silakan buka form PKL {tautan}, pilih namanya, masukkan nomor HP pengaju, perbaiki, lalu kirim ulang. Terima kasih. {sekolah}';
    public const PESAN_TOLAK     = 'Halo {nama}, mohon maaf, ajuan PKL kamu ke {perusahaan} ({kode}) DITOLAK sekolah. Alasan: {alasan}. Kamu boleh mengajukan tempat PKL lain lewat form PKL {tautan}. Terima kasih. {sekolah}';
    /** Siswa dikeluarkan dari ajuan / ajuan ditolak perusahaan (revisi 2026-10-20; pkl_pengaturan.wa_pesan_keluar). */
    public const PESAN_KELUAR    = 'Halo {nama}, kamu tidak lagi tercatat di ajuan PKL {kode} ke {perusahaan}. Alasan: {alasan}. Kamu boleh mengajukan tempat PKL lain lewat form PKL {tautan}. Terima kasih. {sekolah}';
    public const TOKEN           = ['{nama}', '{kelas}', '{perusahaan}', '{kode}', '{alasan}', '{tautan}', '{sekolah}'];

    private static ?bool $kolomAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Kolom migrasi PklWaKeputusan sudah ada? (server yang belum `spark migrate` tidak menampilkan fiturnya.) */
    public static function kolomAda(): bool
    {
        if (self::$kolomAda === null) {
            try {
                $db             = db_connect();
                self::$kolomAda = $db->tableExists('pkl_anggota') && $db->fieldExists('kabar_keputusan_at', 'pkl_anggota')
                    && $db->fieldExists('wa_pesan_tolak', 'pkl_pengaturan');
            } catch (\Throwable $e) {
                self::$kolomAda = false;
            }
        }

        return self::$kolomAda;
    }

    public static function boleh(?string $peran): bool
    {
        return self::kolomAda() && HakAkses::bolehPkl($peran, self::HAK);
    }

    /**
     * 'perbaikan' | 'ditolak' | 'keluar' → templat aktif (dari Pengaturan PKL atau bawaan). Ajuan yang ditolak PERUSAHAAN
     * ($sumber 'perusahaan') memakai templat 'keluar' (bukan "DITOLAK sekolah").
     */
    public static function templat(string $status, array $p, ?string $sumber = null): string
    {
        if ($status === 'keluar' || ($status === 'ditolak' && $sumber === 'perusahaan')) {
            return trim((string) ($p['wa_pesan_keluar'] ?? '')) !== '' ? (string) $p['wa_pesan_keluar'] : self::PESAN_KELUAR;
        }
        $kolom  = $status === 'ditolak' ? 'wa_pesan_tolak' : 'wa_pesan_perbaikan';
        $bawaan = $status === 'ditolak' ? self::PESAN_TOLAK : self::PESAN_PERBAIKAN;

        return trim((string) ($p[$kolom] ?? '')) !== '' ? (string) $p[$kolom] : $bawaan;
    }

    /** Kabar untuk siswa yang dikeluarkan bisa dipakai? (hak sama + tabel pkl_anggota_keluar sudah ada) */
    public static function bolehKeluar(?string $peran): bool
    {
        return self::boleh($peran) && PklKeluar::tabelAda();
    }

    /**
     * Penerima kabar "kamu dikeluarkan dari ajuan" untuk satu ajuan (semua siswa yang pernah dikeluarkan, terbaru dulu).
     *
     * @param array<string, mixed> $p
     * @param array<string, mixed> $setting
     *
     * @return list<array<string, mixed>>
     */
    public function daftarKeluar(int $ajuanId, array $p, array $setting): array
    {
        $a = $this->db->table('pkl_pengajuan')->select('id, perusahaan_nama')->where('id', $ajuanId)->get()->getRowArray();
        if ($a === null) {
            return [];
        }
        $templat = self::templat('keluar', $p);
        $out     = [];
        foreach ((new PklKeluar($this->db))->daftarAjuan($ajuanId) as $k) {
            $hp    = (string) $k['hp'];
            $wa    = $hp !== '' ? PklWa::nomorWa($hp) : null;
            $pesan = PklWa::isi($templat, [
                'nama' => IsianBantu::judul($k['nama']), 'kelas' => $k['kelas'], 'perusahaan' => (string) $a['perusahaan_nama'],
                'kode' => PklPengajuanModel::kode($ajuanId), 'alasan' => $k['alasan'] !== '' ? $k['alasan'] : '-',
                'tautan' => config('Pkl')->tautan(), 'sekolah' => (string) ($setting['school_name'] ?? ''),
            ]);
            $out[] = $k + [
                'url'          => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
                'wa'           => $wa,
                'alasan_tidak' => $wa === null ? ($hp === '' ? 'Nomor HP kosong' : 'Nomor HP tidak valid') : null,
                'pesan'        => $pesan,
            ];
        }

        return $out;
    }

    /**
     * Tandai siswa yang dikeluarkan sudah dikabari (setelah staf menekan tombol WhatsApp).
     *
     * @param array<string, mixed> $konteks
     */
    public function tandaiKeluar(int $ajuanId, int $keluarId, array $konteks): bool
    {
        if (! PklKeluar::tabelAda()) {
            return false;
        }
        $k = $this->db->table('pkl_anggota_keluar k')->select('k.id, s.nama')->join('siswa s', 's.id = k.siswa_id')
            ->where('k.id', $keluarId)->where('k.pengajuan_id', $ajuanId)->get()->getRowArray();
        if ($k === null) {
            return false;
        }
        $oleh = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        $this->db->table('pkl_anggota_keluar')->where('id', $keluarId)->update(['kabar_at' => date('Y-m-d H:i:s'), 'kabar_oleh' => $oleh]);
        (new PklAjuan($this->db))->catat($ajuanId, 'kabari_keluar', $konteks, 'Dikabari lewat WhatsApp (dikeluarkan dari ajuan): ' . $k['nama']);
        (new AuditModel())->record('update', 'pkl_anggota_keluar', $keluarId, mb_substr('Siswa yang dikeluarkan dari PKL ' . PklPengajuanModel::kode($ajuanId) . ' dikabari lewat WhatsApp: ' . $k['nama'], 0, 255));

        return true;
    }

    /** Pesan galat untuk templat, atau null bila sah. */
    public static function periksaPesan(string $pesan): ?string
    {
        $pesan = trim($pesan);
        if ($pesan === '' || mb_strlen($pesan) > PklWa::MAKS_PESAN) {
            return 'Pesan WhatsApp wajib diisi (maksimal ' . PklWa::MAKS_PESAN . ' huruf).';
        }
        preg_match_all('/\{[^}]*\}/', $pesan, $m);
        foreach ($m[0] as $t) {
            if (! in_array($t, self::TOKEN, true)) {
                return 'Penanda ' . $t . ' tidak dikenal. Yang tersedia: ' . implode(' ', self::TOKEN) . '.';
            }
        }

        return null;
    }

    /**
     * Daftar penerima kabar keputusan untuk satu ajuan berstatus perbaikan/ditolak; null bila status lain / tak ada.
     *
     * @param array<string, mixed> $p       pkl_pengaturan
     * @param array<string, mixed> $setting settings sekolah
     *
     * @return array{status: string, alasan: string, diputuskan_at: ?string, siswa: list<array<string, mixed>>}|null
     */
    public function daftar(int $ajuanId, array $p, array $setting): ?array
    {
        $a = $this->db->table('pkl_pengajuan')->where('id', $ajuanId)->get()->getRowArray();
        if ($a === null || ! in_array($a['status'], self::STATUS, true)) {
            return null;
        }
        $sumber  = (string) ($a['tolak_sumber'] ?? '');
        $templat = self::templat((string) $a['status'], $p, $sumber !== '' ? $sumber : null);
        $putus   = (string) ($a['diputuskan_at'] ?? '');
        $kabar   = [];
        foreach ($this->db->table('pkl_anggota')->select('id, kabar_keputusan_at, kabar_keputusan_oleh')->where('pengajuan_id', $ajuanId)->get()->getResultArray() as $r) {
            $kabar[(int) $r['id']] = $r;
        }
        $alasan = trim((string) ($a['catatan_staf'] ?? ''));
        $siswa  = [];
        foreach ((new PklPengajuanModel())->anggotaDetail($ajuanId) as $s) {
            $hp    = trim((string) ($s['hp'] ?? '')) !== '' ? (string) $s['hp'] : (string) ($s['hp_master'] ?? '');
            $wa    = $hp !== '' ? PklWa::nomorWa($hp) : null;
            $pesan = PklWa::isi($templat, [
                'nama'       => IsianBantu::judul((string) $s['nama']),
                'kelas'      => (string) ($s['nama_kelas'] ?? ''),
                'perusahaan' => (string) $a['perusahaan_nama'],
                'kode'       => PklPengajuanModel::kode($ajuanId),
                'alasan'     => $alasan !== '' ? $alasan : '-',
                'tautan'     => config('Pkl')->tautan(),
                'sekolah'    => (string) ($setting['school_name'] ?? ''),
            ]);
            $k        = $kabar[(int) $s['id']] ?? [];
            $waktu    = (string) ($k['kabar_keputusan_at'] ?? '');
            $sudah    = $waktu !== '' && ($putus === '' || $waktu >= $putus);
            $siswa[]  = [
                'ajuan_id' => $ajuanId, 'siswa_id' => (int) $s['siswa_id'], 'nama' => (string) $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? ''),
                'pengaju'  => $s['peran'] === 'pengaju', 'hp' => $hp, 'wa' => $wa,
                'url'      => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
                'alasan_tidak' => $wa === null ? ($hp === '' ? 'Nomor HP kosong' : 'Nomor HP tidak valid') : null,
                'pesan'    => $pesan,
                'dikabari_at'   => $sudah ? $waktu : null,
                'dikabari_oleh' => $sudah ? ($k['kabar_keputusan_oleh'] ?? null) : null,
            ];
        }

        return ['status' => (string) $a['status'], 'sumber' => $sumber !== '' ? $sumber : null, 'alasan' => $alasan, 'diputuskan_at' => $putus !== '' ? $putus : null, 'siswa' => $siswa];
    }

    /**
     * Tandai siswa sudah dikabari keputusan (dipanggil setelah staf menekan tombol WhatsApp).
     *
     * @param array<string, mixed> $konteks
     */
    public function tandai(int $ajuanId, int $siswaId, array $konteks): bool
    {
        $a = $this->db->table('pkl_anggota a')->select('a.id, s.nama, p.status')->join('siswa s', 's.id = a.siswa_id')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->where('a.pengajuan_id', $ajuanId)->where('a.siswa_id', $siswaId)->whereIn('p.status', self::STATUS)->get()->getRowArray();
        if ($a === null) {
            return false;
        }
        $oleh = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        $kata = $a['status'] === 'ditolak' ? 'ditolak' : 'perlu diperbaiki';
        $this->db->table('pkl_anggota')->where('id', (int) $a['id'])->update(['kabar_keputusan_at' => date('Y-m-d H:i:s'), 'kabar_keputusan_oleh' => $oleh]);
        (new PklAjuan($this->db))->catat($ajuanId, 'kabari_keputusan', $konteks, 'Dikabari lewat WhatsApp (' . $kata . '): ' . $a['nama']);
        (new AuditModel())->record('update', 'pkl_anggota', (int) $a['id'], mb_substr('Siswa dikabari lewat WhatsApp bahwa ajuan PKL ' . PklPengajuanModel::kode($ajuanId) . ' ' . $kata . ': ' . $a['nama'], 0, 255));

        return true;
    }
}
