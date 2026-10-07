<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use Config\Peran;

/**
 * Keputusan staf atas ajuan PKL — SATU sumber aturan untuk web (Admin\Pkl) dan API Android (Api\Pkl),
 * supaya hasilnya selalu sama. Isinya: siapa yang berhak, status asal yang sah, alasan wajib, pemeriksaan
 * peringatan "bahaya", dan pencatatan (riwayat, catatan ACC, Audit Log).
 *
 * ATURAN SEKOLAH: ACC, tolak, dan batalkan persetujuan hanya Waka Hubin (HakAkses::bolehAcc). Admin sebagai
 * cadangan wajib menyatakan "mewakili Waka Hubin" ($in['wakil'] = '1') saat ACC. Operator hanya boleh
 * mengembalikan ajuan untuk diperbaiki.
 *
 * $konteks = ['oleh', 'admin_id', 'peran', 'ip', 'saluran' => 'web'|'aplikasi'].
 *
 * Kembalian putuskan(): ['ok' => bool, 'kode' => string, 'pesan' => string, 'http' => int]
 *   kode: ok | tidak_ada | dilarang | wakil | status | catatan | bahaya | gagal
 */
final class PklKeputusan
{
    /** aksi => [status baru, status asal yang boleh, alasan wajib?, kata kerja untuk pesan] */
    public const ATURAN = [
        'acc'        => ['disetujui', ['menunggu', 'perbaikan', 'ditolak'], false, 'disetujui'],
        'kembalikan' => ['perbaikan', ['menunggu'], true, 'dikembalikan untuk diperbaiki'],
        'tolak'      => ['ditolak', ['menunggu', 'perbaikan'], true, 'ditolak'],
        'batal_acc'  => ['perbaikan', ['disetujui'], true, 'persetujuannya dibatalkan (dikembalikan untuk diperbaiki)'],
    ];

    /** Aksi yang hanya boleh dilakukan Waka Hubin (Admin cadangan). */
    public const KHUSUS_HUBIN = ['acc', 'tolak', 'batal_acc'];

    /** Batas satu kali ACC massal. */
    public const MAKS_ACC_MASSAL = 200;

    private PklPengajuanModel $model;
    private AuditModel $audit;
    private PklAjuan $svc;

    public function __construct(?PklAjuan $svc = null)
    {
        $this->model = new PklPengajuanModel();
        $this->audit = new AuditModel();
        $this->svc   = $svc ?? new PklAjuan();
    }

    /**
     * Satu keputusan. $in: catatan, paham ('1' = sudah periksa peringatan bahaya), perusahaan_id, wakil ('1').
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $konteks
     * @param array<string, mixed> $p       baris pkl_pengaturan
     *
     * @return array{ok: bool, kode: string, pesan: string, http: int}
     */
    public function putuskan(int $id, string $aksi, array $in, array $konteks, array $p): array
    {
        if (! isset(self::ATURAN[$aksi])) {
            return $this->gagal('gagal', 'Aksi tidak dikenal.', 422);
        }
        $ajuan = $this->model->find($id);
        if ($ajuan === null) {
            return $this->gagal('tidak_ada', 'Ajuan tidak ditemukan (mungkin sudah dihapus).', 404);
        }
        [$baru, $asal, $wajibCatatan, $kata] = self::ATURAN[$aksi];
        $kode  = PklPengajuanModel::kode($id);
        $peran = (string) ($konteks['peran'] ?? '');

        // Hak keputusan.
        if (in_array($aksi, self::KHUSUS_HUBIN, true) && ! HakAkses::bolehAcc($peran)) {
            $this->audit->record('update', 'pkl_pengajuan', $id, 'DITOLAK: ' . HakAkses::label($peran) . ' mencoba "' . $aksi . '" pada PKL ' . $kode . $this->saluran($konteks));

            return $this->gagal('dilarang', 'Keputusan ini hanya boleh dilakukan Waka Hubin. Akun ' . HakAkses::label($peran) . ' bisa memeriksa dan mengembalikan ajuan untuk diperbaiki, tetapi tidak bisa menyetujui atau menolaknya.', 403);
        }
        $mewakili = $peran === Peran::ADMIN && in_array($aksi, self::KHUSUS_HUBIN, true);
        if ($aksi === 'acc' && $mewakili && ($in['wakil'] ?? '') !== '1' && ($in['wakil'] ?? false) !== true) {
            return $this->gagal('wakil', 'ACC oleh Admin harus menyatakan bahwa Anda mewakili Waka Hubin. Centang pernyataannya, atau minta Waka Hubin yang meng-ACC.', 422);
        }

        if (! in_array($ajuan['status'], $asal, true)) {
            return $this->gagal('status', 'Aksi ini tidak bisa dilakukan: status ajuan sudah "' . $ajuan['status'] . '". Muat ulang.', 409);
        }

        $catatan = IsianBantu::rapikan((string) ($in['catatan'] ?? ''));
        if ($wajibCatatan && mb_strlen($catatan) < 5) {
            return $this->gagal('catatan', 'Alasan wajib diisi (minimal 5 huruf) — siswa akan membacanya.', 422);
        }
        if (mb_strlen($catatan) > 255) {
            return $this->gagal('catatan', 'Alasan terlalu panjang (maksimal 255 huruf).', 422);
        }
        if ($aksi === 'acc' && $mewakili) {
            $catatan = mb_substr('Mewakili Waka Hubin' . ($catatan !== '' ? ' — ' . $catatan : ''), 0, 255);
        }

        $opsi = [];
        if ($aksi === 'acc') {
            $anggota = $this->model->anggotaDetail($id);
            $detail  = $this->model->detail($id);
            $bahaya  = in_array('bahaya', array_column(PklPeringatan::untuk($detail, $anggota, $p), 'tingkat'), true);
            if ($bahaya && ($in['paham'] ?? '') !== '1' && ($in['paham'] ?? false) !== true) {
                return $this->gagal('bahaya', 'Ada peringatan BAHAYA pada ajuan ini. Bereskan dulu, atau centang "sudah saya periksa" bila Anda yakin.', 422);
            }
            $pilih = (int) ($in['perusahaan_id'] ?? 0);
            if ($pilih > 0) {
                $opsi['perusahaan_id'] = $pilih;
            }
        }

        $hasil = $this->svc->ubahStatus($id, $baru, $konteks + ['aksi' => $aksi], $catatan !== '' ? $catatan : null, $opsi);
        if (! $hasil['ok']) {
            return $this->gagal('gagal', $this->pesanGagal($hasil), ($hasil['kode'] ?? '') === 'bentrok' ? 409 : 500);
        }

        $this->audit->record('update', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' ' . $kata . ' — ' . $ajuan['perusahaan_nama'] . ($catatan !== '' ? ' (' . mb_substr($catatan, 0, 80) . ')' : '') . $this->saluran($konteks));

        return ['ok' => true, 'kode' => 'ok', 'pesan' => 'Ajuan ' . $kode . ' ' . $kata . '.', 'http' => 200];
    }

    /**
     * ACC massal: mode "terpilih" ($in['ids']) atau "aman" (semua yang menunggu). Hanya ajuan tanpa peringatan
     * bahaya/periksa yang di-ACC; sisanya dilewati dan dilaporkan.
     *
     * @return array{ok: bool, kode: string, pesan: string, http: int, disetujui?: int, dilewati?: list<string>}
     */
    public function accMassal(array $in, array $konteks, array $p): array
    {
        $peran = (string) ($konteks['peran'] ?? '');
        if (! HakAkses::bolehAcc($peran)) {
            $this->audit->record('update', 'pkl_pengajuan', null, 'DITOLAK: ' . HakAkses::label($peran) . ' mencoba ACC massal PKL' . $this->saluran($konteks));

            return $this->gagal('dilarang', 'ACC hanya boleh dilakukan Waka Hubin. Akun ' . HakAkses::label($peran) . ' tidak bisa meng-ACC.', 403);
        }
        $mewakili = $peran === Peran::ADMIN;
        if ($mewakili && ($in['wakil'] ?? '') !== '1' && ($in['wakil'] ?? false) !== true) {
            return $this->gagal('wakil', 'ACC oleh Admin harus menyatakan bahwa Anda mewakili Waka Hubin. Centang pernyataannya, atau minta Waka Hubin yang meng-ACC.', 422);
        }

        if ((string) ($in['mode'] ?? '') === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['ids'] ?? [])))));
            if ($ids === []) {
                return $this->gagal('catatan', 'Pilih dulu ajuan yang mau di-ACC.', 422);
            }
        } else {
            $ids = null;
        }

        $q = db_connect()->table('pkl_pengajuan')->select('id')->where('status', 'menunggu')->orderBy('COALESCE(diajukan_at, updated_at)', 'ASC', false)->orderBy('id', 'ASC');
        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }
        $urut = array_map('intval', array_column($q->limit(self::MAKS_ACC_MASSAL + 1)->get()->getResultArray(), 'id'));
        if ($urut === []) {
            return ['ok' => true, 'kode' => 'ok', 'pesan' => 'Tidak ada ajuan yang menunggu.', 'http' => 200, 'disetujui' => 0, 'dilewati' => []];
        }
        if (count($urut) > self::MAKS_ACC_MASSAL) {
            return $this->gagal('catatan', 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_ACC_MASSAL . ' ajuan per aksi). Pilih sebagian.', 422);
        }

        $ok    = 0;
        $lewat = [];
        foreach ($urut as $id) {
            $ajuan = $this->model->detail($id);
            if ($ajuan === null || $ajuan['status'] !== 'menunggu') {
                continue;
            }
            $label = PklPengajuanModel::kode($id) . ' ' . $ajuan['perusahaan_nama'];
            $berat = array_values(array_filter(
                PklPeringatan::untuk($ajuan, $this->model->anggotaDetail($id), $p),
                static fn (array $w) => in_array($w['tingkat'], ['bahaya', 'awas'], true)
            ));
            if ($berat !== []) {
                $lewat[] = $label . ' — dilewati: ' . mb_substr($berat[0]['teks'], 0, 110) . (count($berat) > 1 ? ' (+' . (count($berat) - 1) . ' peringatan lain)' : '');
                continue;
            }

            $hasil = $this->svc->ubahStatus($id, 'disetujui', $konteks + ['aksi' => 'acc'], $mewakili ? 'ACC massal — mewakili Waka Hubin' : 'ACC massal');
            if ($hasil['ok']) {
                $ok++;
            } else {
                $lewat[] = $label . ' — gagal: ' . $this->pesanGagal($hasil);
            }
        }

        $this->audit->record('update', 'pkl_pengajuan', null, 'ACC massal PKL: ' . $ok . ' disetujui, ' . count($lewat) . ' dilewati' . $this->saluran($konteks));

        return [
            'ok'        => $ok > 0 || $lewat === [],
            'kode'      => 'ok',
            'pesan'     => 'ACC massal: ' . $ok . ' ajuan disetujui' . ($lewat !== [] ? ', ' . count($lewat) . ' dilewati (perlu diperiksa satu per satu).' : '.'),
            'http'      => 200,
            'disetujui' => $ok,
            'dilewati'  => array_slice($lewat, 0, 40),
        ];
    }

    /** Pesan galat ramah dari hasil PklAjuan. */
    public function pesanGagal(array $hasil): string
    {
        switch ($hasil['kode'] ?? 'galat') {
            case 'bentrok':
                $nama = [];
                foreach ($hasil['siswa_ids'] ?? [] as $sid) {
                    $s      = db_connect()->table('siswa')->select('nama')->where('id', (int) $sid)->get()->getRowArray();
                    $nama[] = $s['nama'] ?? ('#' . $sid);
                }

                return 'Gagal: ' . implode(', ', $nama) . ' sudah punya ajuan PKL aktif lain. Selesaikan/tolak ajuan itu dulu.';
            case 'status':
            case 'tidak_ada':
                return 'Ajuan sudah berubah (mungkin diubah staf lain). Muat ulang.';
            case 'pengaju_beda':
                return 'Pengaju tidak boleh diganti. Hapus ajuan lalu isi ulang bila pengajunya salah.';
            default:
                return 'Penyimpanan gagal. Coba lagi sebentar.';
        }
    }

    /** @return array{ok: false, kode: string, pesan: string, http: int} */
    private function gagal(string $kode, string $pesan, int $http): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan, 'http' => $http];
    }

    private function saluran(array $konteks): string
    {
        return ($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : '';
    }
}
