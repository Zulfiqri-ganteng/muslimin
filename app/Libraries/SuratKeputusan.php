<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;
use Config\Peran;

/**
 * Keputusan atas surat sekolah — SATU sumber aturan (web sekarang, API Android kelak), pola sama dengan PklKeputusan.
 * Isinya: siapa yang berhak, status asal yang sah, alasan wajib, pemeriksaan sebelum ACC, pencatatan (riwayat,
 * catatan ACC + kode verifikasi, Audit Log). Rancangan: docs/DESAIN-SURAT-SEKOLAH.md.
 *
 * ATURAN: ACC / kembalikan / batalkan-ACC = hak 'acc' (bawaan Waka Hubin; Admin cadangan, WAJIB menyatakan
 * "mewakili Waka Hubin" saat ACC). Ajukan ulang / batalkan surat = hak 'surat_sekolah' (bawaan Operator).
 *
 * Alur status:  menunggu ──ACC──▶ disetujui ──(unduh pertama: nomor terbit)
 *               menunggu ──kembalikan──▶ dikembalikan ──ajukan ulang──▶ menunggu
 *               disetujui ──batal ACC (hanya bila belum bernomor / belum diunduh)──▶ dikembalikan
 *               menunggu | dikembalikan | disetujui ──batalkan surat──▶ dibatalkan (nomor yang sudah terbit tetap tercatat)
 *
 * $konteks = ['oleh', 'admin_id', 'peran', 'ip', 'saluran' => 'web'|'aplikasi'].
 *
 * Kembalian putuskan(): ['ok' => bool, 'kode' => string, 'pesan' => string, 'http' => int]
 *   kode: ok | tidak_ada | dilarang | wakil | status | terbit | catatan | bahaya | gagal
 */
final class SuratKeputusan
{
    /** aksi => [status baru, status asal yang boleh, alasan wajib?, kata kerja untuk pesan, hak yang dituntut] */
    public const ATURAN = [
        'acc'          => ['disetujui', ['menunggu'], false, 'disetujui', 'acc'],
        'kembalikan'   => ['dikembalikan', ['menunggu'], true, 'dikembalikan untuk diperbaiki', 'acc'],
        'batal_acc'    => ['dikembalikan', ['disetujui'], true, 'persetujuannya dibatalkan (dikembalikan untuk diperbaiki)', 'acc'],
        'ajukan_ulang' => ['menunggu', ['dikembalikan'], false, 'diajukan ulang untuk ACC', 'surat_sekolah'],
        'batal'        => ['dibatalkan', ['menunggu', 'dikembalikan', 'disetujui'], true, 'dibatalkan', 'surat_sekolah'],
    ];

    /** Batas satu kali ACC massal. */
    public const MAKS_ACC_MASSAL = 200;

    private BaseConnection $db;
    private SuratSekolah $svc;
    private AuditModel $audit;

    public function __construct(?SuratSekolah $svc = null, ?BaseConnection $db = null)
    {
        $this->db    = $db ?? db_connect();
        $this->svc   = $svc ?? new SuratSekolah($this->db);
        $this->audit = new AuditModel();
    }

    /**
     * Kode verifikasi ACC, mis. SRT-00012-8F3A9C: kode surat + 6 huruf-angka tanda tangan HMAC dari (surat, jam ACC,
     * akun penyetuju). Tak bisa ditebak tanpa kunci aplikasi; staf mencocokkannya dengan yang tertera di halaman detail.
     */
    public static function kodeVerifikasi(int $id, string $accAt, ?int $adminId): string
    {
        $kunci = (string) (env('encryption.key') ?: 'pkl-bina-nusa');

        return SuratSekolahModel::kode($id) . '-' . strtoupper(substr(hash_hmac('sha256', 'surat|' . $id . '|' . $accAt . '|' . (int) $adminId, $kunci), 0, 6));
    }

    /**
     * Satu keputusan. $in: catatan (alasan), wakil ('1' = Admin menyatakan mewakili Waka Hubin), paham ('1' = sudah
     * memeriksa peringatan BAHAYA).
     *
     * @param array<string, mixed>      $in
     * @param array<string, mixed>      $konteks
     * @param array<string, mixed>|null $p       baris pkl_pengaturan (dibaca bila null)
     *
     * @return array{ok: bool, kode: string, pesan: string, http: int}
     */
    public function putuskan(int $id, string $aksi, array $in, array $konteks, ?array $p = null): array
    {
        if (! isset(self::ATURAN[$aksi])) {
            return $this->gagal('gagal', 'Aksi tidak dikenal.', 422);
        }
        $m = $this->svc->muat($id);
        if ($m === null) {
            return $this->gagal('tidak_ada', 'Surat tidak ditemukan (mungkin sudah dihapus).', 404);
        }
        $s = $m['surat'];
        [, $asal, $wajibCatatan, $kata, $hak] = self::ATURAN[$aksi];
        $kode  = SuratSekolahModel::kode($id);
        $peran = (string) ($konteks['peran'] ?? '');

        // Hak keputusan.
        if (! HakAkses::bolehPkl($peran, $hak)) {
            $this->audit->record('update', 'surat_sekolah', $id, 'DITOLAK: ' . HakAkses::label($peran) . ' ' . $this->pelaku($konteks) . ' mencoba "' . $aksi . '" pada ' . $kode . $this->saluran($konteks));

            return $this->gagal('dilarang', $hak === 'acc'
                ? 'Keputusan ini hanya boleh dilakukan peran yang diberi hak ACC (bawaan: Waka Hubin). Akun ' . HakAkses::label($peran) . ' tidak punya hak ACC; Admin bisa mengaturnya di PKL → Hak Akses.'
                : 'Akun ' . HakAkses::label($peran) . ' tidak punya hak mengelola surat sekolah. Admin bisa mengaturnya di PKL → Hak Akses.', 403);
        }
        $mewakili = $aksi === 'acc' && $peran === Peran::ADMIN;
        if ($mewakili && ! self::benar($in['wakil'] ?? null)) {
            return $this->gagal('wakil', 'ACC oleh Admin harus menyatakan bahwa Anda mewakili Waka Hubin. Centang pernyataannya, atau minta Waka Hubin yang meng-ACC.', 422);
        }

        if (! in_array($s['status'], $asal, true)) {
            return $this->gagal('status', 'Aksi ini tidak bisa dilakukan: status surat sudah "' . (SuratSekolahModel::TAMPIL_STATUS[$s['status']][0] ?? $s['status']) . '". Muat ulang halaman.', 409);
        }
        if ($aksi === 'batal_acc') {
            if ((int) $s['perlu_acc'] !== 1) {
                return $this->gagal('status', 'Jenis surat ini tidak memakai ACC.', 409);
            }
            if ((string) ($s['nomor'] ?? '') !== '' || (int) $s['cetak_ke'] > 0) {
                return $this->gagal('terbit', 'Surat ini sudah bernomor atau pernah diunduh, jadi persetujuannya tidak bisa dibatalkan. Bila surat tidak jadi dipakai, pakai "Batalkan surat" (nomornya tetap tercatat).', 409);
            }
        }

        $catatan = IsianBantu::rapikan((string) ($in['catatan'] ?? ''));
        if ($wajibCatatan && mb_strlen($catatan) < 5) {
            return $this->gagal('catatan', 'Alasan wajib diisi (minimal 5 huruf).', 422);
        }
        if (mb_strlen($catatan) > 255) {
            return $this->gagal('catatan', 'Alasan terlalu panjang (maksimal 255 huruf).', 422);
        }

        if ($aksi === 'acc') {
            $p        ??= (new PklPengaturanModel())->ambil();
            $peringatan = SuratPeriksa::untuk($s, $m['siswa'], $p);
            if (SuratPeriksa::adaBahaya($peringatan) && ! self::benar($in['paham'] ?? null)) {
                return $this->gagal('bahaya', 'Ada peringatan BAHAYA pada surat ini. Bereskan dulu, atau centang "sudah saya periksa" bila Anda yakin.', 422);
            }
        }

        $ket   = $mewakili ? mb_substr('Mewakili Waka Hubin' . ($catatan !== '' ? ' — ' . $catatan : ''), 0, 255) : null;
        $hasil = $this->terapkan($id, $aksi, $catatan, $konteks, $ket);
        if (! $hasil['ok']) {
            return $this->gagal($hasil['kode'], $hasil['kode'] === 'status' ? 'Surat sudah berubah (mungkin diputuskan staf lain). Muat ulang halaman.' : 'Penyimpanan gagal. Coba lagi sebentar.', $hasil['kode'] === 'status' ? 409 : 500);
        }

        $this->audit->record('update', 'surat_sekolah', $id, $kode . ' (' . SuratJenis::label((string) $s['jenis']) . ') ' . $kata . ($catatan !== '' ? ' (' . mb_substr($catatan, 0, 80) . ')' : '') . ' — oleh ' . $this->pelaku($konteks) . $this->saluran($konteks));

        $tambah = '';
        if ($aksi === 'batal' && (string) ($s['nomor'] ?? '') !== '') {
            $tambah = ' Nomor ' . $s['nomor'] . ' tetap tercatat dan tidak dipakai lagi.';
        }

        return ['ok' => true, 'kode' => 'ok', 'pesan' => 'Surat ' . $kode . ' ' . $kata . '.' . $tambah, 'http' => 200];
    }

    /**
     * ACC massal: mode "terpilih" ($in['ids']) atau "aman" (semua yang menunggu). Hanya surat TANPA peringatan
     * (bahaya maupun awas) yang di-ACC; sisanya dilewati dan dilaporkan supaya diperiksa satu per satu.
     *
     * @param array<string, mixed>      $in
     * @param array<string, mixed>      $konteks
     * @param array<string, mixed>|null $p
     *
     * @return array{ok: bool, kode: string, pesan: string, http: int, disetujui?: int, dilewati?: list<string>}
     */
    public function accMassal(array $in, array $konteks, ?array $p = null): array
    {
        $peran = (string) ($konteks['peran'] ?? '');
        if (! HakAkses::bolehAcc($peran)) {
            $this->audit->record('update', 'surat_sekolah', null, 'DITOLAK: ' . HakAkses::label($peran) . ' ' . $this->pelaku($konteks) . ' mencoba ACC massal surat sekolah' . $this->saluran($konteks));

            return $this->gagal('dilarang', 'ACC hanya boleh dilakukan peran yang diberi hak ACC (bawaan: Waka Hubin). Akun ' . HakAkses::label($peran) . ' tidak bisa meng-ACC.', 403);
        }
        $mewakili = $peran === Peran::ADMIN;
        if ($mewakili && ! self::benar($in['wakil'] ?? null)) {
            return $this->gagal('wakil', 'ACC oleh Admin harus menyatakan bahwa Anda mewakili Waka Hubin. Centang pernyataannya, atau minta Waka Hubin yang meng-ACC.', 422);
        }

        $ids = null;
        if ((string) ($in['mode'] ?? '') === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['ids'] ?? [])))));
            if ($ids === []) {
                return $this->gagal('catatan', 'Pilih dulu surat yang mau di-ACC.', 422);
            }
        }

        $q = $this->db->table('surat_sekolah')->select('id')->where('status', 'menunggu')->orderBy('created_at', 'ASC')->orderBy('id', 'ASC');
        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }
        $urut = array_map('intval', array_column($q->limit(self::MAKS_ACC_MASSAL + 1)->get()->getResultArray(), 'id'));
        if ($urut === []) {
            return ['ok' => true, 'kode' => 'ok', 'pesan' => 'Tidak ada surat yang menunggu.', 'http' => 200, 'disetujui' => 0, 'dilewati' => []];
        }
        if (count($urut) > self::MAKS_ACC_MASSAL) {
            return $this->gagal('catatan', 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_ACC_MASSAL . ' surat per aksi). Pilih sebagian.', 422);
        }

        $p ??= (new PklPengaturanModel())->ambil();
        $ok    = 0;
        $lewat = [];
        foreach ($urut as $id) {
            $m = $this->svc->muat($id);
            if ($m === null || $m['surat']['status'] !== 'menunggu') {
                continue;
            }
            $label  = SuratSekolahModel::kode($id) . ' ' . $m['surat']['judul'];
            $berat  = SuratPeriksa::untuk($m['surat'], $m['siswa'], $p);
            if ($berat !== []) {
                $lewat[] = $label . ' — dilewati: ' . mb_substr($berat[0]['teks'], 0, 110) . (count($berat) > 1 ? ' (+' . (count($berat) - 1) . ' peringatan lain)' : '');
                continue;
            }

            $hasil = $this->terapkan($id, 'acc', '', $konteks, $mewakili ? 'ACC massal — mewakili Waka Hubin' : 'ACC massal');
            if ($hasil['ok']) {
                $ok++;
            } else {
                $lewat[] = $label . ' — gagal: ' . ($hasil['kode'] === 'status' ? 'sudah diputuskan staf lain' : 'penyimpanan gagal');
            }
        }

        $this->audit->record('update', 'surat_sekolah', null, 'ACC massal surat sekolah oleh ' . $this->pelaku($konteks) . ': ' . $ok . ' disetujui, ' . count($lewat) . ' dilewati' . $this->saluran($konteks));

        return [
            'ok'        => $ok > 0 || $lewat === [],
            'kode'      => 'ok',
            'pesan'     => 'ACC massal: ' . $ok . ' surat disetujui' . ($lewat !== [] ? ', ' . count($lewat) . ' dilewati (perlu diperiksa satu per satu).' : '.'),
            'http'      => 200,
            'disetujui' => $ok,
            'dilewati'  => array_slice($lewat, 0, 40),
        ];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /**
     * Terapkan perubahan status (+ kolom ACC) dalam satu transaksi, dijaga status asal (dua staf yang memutuskan bersamaan
     * tidak saling menimpa), lalu catat riwayat.
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, kode: string}
     */
    private function terapkan(int $id, string $aksi, string $catatan, array $konteks, ?string $catatanRiwayat = null): array
    {
        [$baru, $asal] = self::ATURAN[$aksi];
        $now = date('Y-m-d H:i:s');
        $set = ['status' => $baru, 'updated_at' => $now];

        if ($catatanRiwayat === null) {
            $catatanRiwayat = $catatan !== '' ? $catatan : null;
        }
        switch ($aksi) {
            case 'acc':
                $adminId = isset($konteks['admin_id']) ? (((int) $konteks['admin_id']) ?: null) : null;
                $peranAcc = (string) ($konteks['peran'] ?? '');
                $set += [
                    'acc_admin_id' => $adminId,
                    'acc_nama'     => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150),
                    'acc_peran'    => $peranAcc !== '' ? mb_substr($peranAcc, 0, 20) : null,
                    'acc_at'       => $now,
                    'acc_ip'       => $konteks['ip'] ?? null,
                    'acc_kode'     => self::kodeVerifikasi($id, $now, $adminId),
                    'catatan_staf' => null,
                ];
                break;
            case 'kembalikan':
            case 'batal':
                $set += ['catatan_staf' => $catatan];
                break;
            case 'batal_acc':
                $set += ['catatan_staf' => $catatan, 'acc_admin_id' => null, 'acc_nama' => null, 'acc_peran' => null, 'acc_at' => null, 'acc_ip' => null, 'acc_kode' => null];
                break;
            case 'ajukan_ulang':
                $set += ['diajukan_at' => $now, 'catatan_staf' => null];
                break;
        }

        $this->db->transBegin();
        try {
            $this->db->table('surat_sekolah')->where('id', $id)->whereIn('status', $asal)->update($set);
            if ($this->db->affectedRows() < 1) {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'status'];
            }
            $this->svc->catat($id, $aksi, $konteks, $catatanRiwayat);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Surat] keputusan "' . $aksi . '" gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'gagal'];
        }

        return ['ok' => true, 'kode' => 'ok'];
    }

    /** Nilai kotak centang / JSON: '1', 1, true, 'true', 'on' → true. */
    private static function benar(mixed $v): bool
    {
        return $v !== null && filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{ok: false, kode: string, pesan: string, http: int} */
    private function gagal(string $kode, string $pesan, int $http): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan, 'http' => $http];
    }

    /** Nama pelaku untuk Audit Log (akun yang menekan tombol). */
    private function pelaku(array $konteks): string
    {
        return mb_substr(trim((string) ($konteks['oleh'] ?? '')) !== '' ? (string) $konteks['oleh'] : 'tak dikenal', 0, 60);
    }

    private function saluran(array $konteks): string
    {
        return ($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : '';
    }
}
