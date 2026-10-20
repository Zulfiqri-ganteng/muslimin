<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * SISWA DIKELUARKAN, DITOLAK PERUSAHAAN, dan RIWAYAT PENOLAKAN siswa — revisi klien 2026-10-20 (docs/DESAIN-PKL.md bagian
 * "Siswa dikeluarkan, ditolak perusahaan, template alasan"). SATU sumber aturan untuk web (Admin\Pkl), API (Api\Pkl), dan kotak
 * Lapor Diterima (PklLapor).
 *
 * ATURAN:
 *   - Keluarkan siswa (satu/lebih anggota) dari ajuan menunggu/perbaikan → hak "Periksa, kembalikan, ubah" (ubah); dari ajuan yang
 *     sudah DISETUJUI → hak "ACC ajuan PKL" (sama dengan mengubah ajuan disetujui). Wajib memilih jenis + alasan (min 5 huruf).
 *     Minimal satu siswa tersisa; pengaju yang dikeluarkan digantikan anggota tersisa yang paling awal.
 *   - Siswa yang dikeluarkan langsung BEBAS mengajukan baru, dan TETAP TERCATAT (pkl_anggota_keluar) → riwayat siswa.
 *   - Jenis "ditolak perusahaan": Surat Izin PKL yang sudah terbit tetap dianggap arsip sah (sudah dibawa ke perusahaan) — tidak
 *     ditandai "perlu cetak ulang". Jenis lain: surat ditandai perlu cetak ulang seperti biasa (nomor tetap).
 *   - Ditolak perusahaan (SEMUA siswa) → hak ACC; boleh dari menunggu/perbaikan/DISETUJUI dalam satu langkah. Status ajuan tetap
 *     'ditolak' dengan pkl_pengajuan.tolak_sumber = 'perusahaan' (tampil "Ditolak perusahaan"); siswa bebas mengajukan baru.
 *   - Riwayat penolakan siswa = ajuan yang ditolak (sekolah/perusahaan) + catatan dikeluarkan (kecuali jenis "pindah kelompok").
 *     Ditampilkan di detail ajuan, Status Siswa, Excel, halaman riwayat siswa, dan API; peringatan "periksa" bila siswa dulu
 *     DITOLAK PERUSAHAAN YANG SAMA (PklPeringatan).
 */
final class PklKeluar
{
    /** jenis => [label, penjelasan] */
    public const JENIS = [
        'ditolak_perusahaan' => ['Ditolak perusahaan', 'Perusahaan tidak menerima siswa ini (mis. kuota penuh). Surat Izin yang sudah terbit tetap dianggap arsip sah.'],
        'administrasi'       => ['Administrasi belum lunas', 'Kewajiban administrasi sekolah belum diselesaikan; kelompoknya tetap bisa lanjut.'],
        'pindah'             => ['Pindah ke kelompok lain', 'Digabung ke ajuan lain di tempat yang sama (tidak dihitung sebagai penolakan).'],
        'mundur'             => ['Mengundurkan diri', 'Siswa membatalkan sendiri tempat PKL ini.'],
        'lainnya'            => ['Lainnya', 'Alasan lain — tuliskan di kotak alasan.'],
    ];

    /** Jenis yang TIDAK dihitung sebagai penolakan di riwayat siswa. */
    public const BUKAN_TOLAK = ['pindah'];

    public const MIN_ALASAN = 5;

    private static ?bool $tabelAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Tabel pkl_anggota_keluar (migrasi 2026-10-25-000001) sudah ada? */
    public static function tabelAda(): bool
    {
        if (self::$tabelAda === null) {
            try {
                self::$tabelAda = db_connect()->tableExists('pkl_anggota_keluar');
            } catch (\Throwable $e) {
                self::$tabelAda = false;
            }
        }

        return self::$tabelAda;
    }

    public static function labelJenis(string $jenis): string
    {
        return self::JENIS[$jenis][0] ?? $jenis;
    }

    /** Peran ini boleh mengeluarkan siswa dari ajuan berstatus $status? */
    public static function bolehKeluarkan(?string $peran, string $status): bool
    {
        if (! self::tabelAda() || ! PklPengajuanModel::aktif($status)) {
            return false;
        }

        return $status === 'disetujui' ? HakAkses::bolehAcc($peran) : HakAkses::bolehPkl($peran, 'ubah');
    }

    /** Peran ini boleh menandai ajuan berstatus $status "Ditolak perusahaan"? */
    public static function bolehDitolakPerusahaan(?string $peran, string $status): bool
    {
        return self::tabelAda() && PklPengajuanModel::aktif($status) && HakAkses::bolehAcc($peran);
    }

    // =================================================================
    // Aksi
    // =================================================================

    /**
     * Keluarkan siswa. $in: siswa_ids (list) atau siswa_id, jenis, catatan (alasan, dibaca siswa).
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip', 'saluran']
     *
     * @return array{ok: bool, http: int, kode: string, pesan: string, keluar?: list<array{id: int, siswa_id: int, nama: string}>}
     */
    public function keluarkan(int $id, array $in, array $konteks, ?array $p = null, bool $periksaHak = true): array
    {
        if (! self::tabelAda()) {
            return $this->gagal(503, 'belum_migrasi', 'Fitur keluarkan siswa belum aktif di server ini: jalankan "php spark migrate".');
        }
        $model = new PklPengajuanModel();
        $ajuan = $model->detail($id);
        if ($ajuan === null) {
            return $this->gagal(404, 'tidak_ada', 'Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }
        $kode  = PklPengajuanModel::kode($id);
        $peran = (string) ($konteks['peran'] ?? '');
        if (! PklPengajuanModel::aktif((string) $ajuan['status'])) {
            return $this->gagal(409, 'status', 'Siswa hanya bisa dikeluarkan dari ajuan yang masih aktif (menunggu, perlu perbaikan, atau disetujui). Ajuan ' . $kode . ' berstatus "' . $ajuan['status'] . '".');
        }
        if ($periksaHak && ! self::bolehKeluarkan($peran, (string) $ajuan['status'])) {
            (new AuditModel())->record('update', 'pkl_pengajuan', $id, 'DITOLAK: ' . HakAkses::label($peran) . ' mencoba mengeluarkan siswa dari PKL ' . $kode . $this->saluran($konteks));

            return $this->gagal(403, 'dilarang', $ajuan['status'] === 'disetujui'
                ? 'Mengeluarkan siswa dari ajuan yang sudah DISETUJUI hanya boleh dilakukan pemegang hak ACC ajuan PKL (' . PklHak::teksPemegang('acc') . ').'
                : 'Akun ' . HakAkses::label($peran) . ' tidak punya hak mengubah ajuan. Admin bisa mengaturnya di Sistem → Hak Akses.');
        }

        $ids = array_key_exists('siswa_ids', $in) ? (array) $in['siswa_ids'] : (isset($in['siswa_id']) ? [$in['siswa_id']] : []);
        $ids = array_values(array_unique(array_filter(array_map('intval', array_filter($ids, 'is_scalar')), static fn (int $x): bool => $x > 0)));
        $jenis  = trim((string) ($in['jenis'] ?? ''));
        $alasan = IsianBantu::rapikan((string) ($in['catatan'] ?? ($in['alasan'] ?? '')));
        $galat  = [];
        if ($ids === []) {
            $galat['siswa_ids'] = 'Pilih siswa yang dikeluarkan.';
        }
        if (! isset(self::JENIS[$jenis])) {
            $galat['jenis'] = 'Pilih jenisnya: ' . implode(', ', array_map(static fn (array $j): string => $j[0], self::JENIS)) . '.';
        }
        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            $galat['catatan'] = 'Alasan wajib diisi (minimal ' . self::MIN_ALASAN . ' huruf) — siswa akan membacanya.';
        } elseif (mb_strlen($alasan) > PklAjuan::MAKS_CATATAN) {
            $galat['catatan'] = 'Alasan terlalu panjang (maksimal ' . PklAjuan::MAKS_CATATAN . ' huruf).';
        }
        if ($galat !== []) {
            return $this->gagal(422, array_key_first($galat), (string) reset($galat)) + ['galat' => $galat];
        }

        $p ??= (new PklPengaturanModel())->ambil();
        $surat     = $jenis === 'ditolak_perusahaan' ? (new PklSurat($this->db))->surat($id) : null;
        $sidikLama = $surat !== null ? PklSurat::sidik($ajuan, $model->anggotaDetail($id), $p) : null;

        $hasil = (new PklAjuan($this->db))->keluarkan($id, $ids, $jenis, $alasan, $konteks + ['aksi' => 'keluarkan', 'sumber' => $konteks['sumber'] ?? 'staf']);
        if (! $hasil['ok']) {
            return match ($hasil['kode'] ?? 'galat') {
                'semua'         => $this->gagal(422, 'semua', 'Tidak bisa mengeluarkan SEMUA siswa — minimal satu harus tersisa. Bila semua ditolak perusahaan, pakai tombol "Ditolak perusahaan"; bila ditolak sekolah, pakai "Tolak".'),
                'bukan_anggota' => $this->gagal(409, 'bukan_anggota', 'Ada siswa pilihan yang bukan (lagi) anggota ajuan ' . $kode . '. Muat ulang halaman lalu pilih lagi.'),
                'status'        => $this->gagal(409, 'status', 'Status ajuan ' . $kode . ' baru saja berubah. Muat ulang halaman.'),
                'tidak_ada'     => $this->gagal(404, 'tidak_ada', 'Ajuan tidak ditemukan (mungkin sudah dihapus).'),
                default         => $this->gagal(500, 'galat', 'Penyimpanan gagal. Coba lagi sebentar.'),
            };
        }

        // Ditolak perusahaan: Surat Izin yang sudah terbit (dan masih sesuai data) tetap dianggap arsip sah.
        if ($surat !== null && $sidikLama === (string) $surat['sidik']) {
            $baru = $model->detail($id);
            if ($baru !== null) {
                $this->db->table('pkl_surat')->where('pengajuan_id', $id)->update(['sidik' => PklSurat::sidik($baru, $model->anggotaDetail($id), $p)]);
            }
        }

        $nama = array_column($hasil['keluar'], 'nama');
        (new AuditModel())->record('update', 'pkl_pengajuan', $id, mb_substr('PKL ' . $kode . ': dikeluarkan (' . self::labelJenis($jenis) . ') ' . implode(', ', $nama)
            . ($hasil['pengaju_baru'] !== null ? '; pengaju kini ' . $hasil['pengaju_baru'] : '') . ' — ' . $alasan . $this->saluran($konteks), 0, 255));

        return [
            'ok' => true, 'http' => 200, 'kode' => 'ok', 'keluar' => $hasil['keluar'],
            'pesan' => implode(', ', $nama) . ' dikeluarkan dari ' . $kode . ' (' . self::labelJenis($jenis) . ') dan sekarang bebas mengajukan tempat PKL lain.'
                . ($hasil['pengaju_baru'] !== null ? ' Pengaju kini ' . $hasil['pengaju_baru'] . '.' : ''),
        ];
    }

    /**
     * SEMUA siswa ditolak perusahaan → ajuan 'ditolak' (tolak_sumber 'perusahaan') dalam satu langkah, juga dari DISETUJUI.
     * $in: catatan (alasan, wajib). $konteks['aksi'] boleh diisi pemanggil (bawaan 'tolak_perusahaan').
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, http: int, kode: string, pesan: string}
     */
    public function ditolakPerusahaan(int $id, array $in, array $konteks, bool $periksaHak = true): array
    {
        if (! self::tabelAda()) {
            return $this->gagal(503, 'belum_migrasi', 'Fitur ditolak perusahaan belum aktif di server ini: jalankan "php spark migrate".');
        }
        $ajuan = (new PklPengajuanModel())->find($id);
        if ($ajuan === null) {
            return $this->gagal(404, 'tidak_ada', 'Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }
        $kode  = PklPengajuanModel::kode($id);
        $peran = (string) ($konteks['peran'] ?? '');
        if (! PklPengajuanModel::aktif((string) $ajuan['status'])) {
            return $this->gagal(409, 'status', 'Ajuan ' . $kode . ' sudah berstatus "' . $ajuan['status'] . '". Muat ulang halaman.');
        }
        if ($periksaHak && ! self::bolehDitolakPerusahaan($peran, (string) $ajuan['status'])) {
            (new AuditModel())->record('update', 'pkl_pengajuan', $id, 'DITOLAK: ' . HakAkses::label($peran) . ' mencoba menandai PKL ' . $kode . ' ditolak perusahaan' . $this->saluran($konteks));

            return $this->gagal(403, 'dilarang', 'Menandai ajuan "Ditolak perusahaan" hanya boleh dilakukan pemegang hak ACC ajuan PKL (sekarang: ' . PklHak::teksPemegang('acc') . ').');
        }
        $alasan = IsianBantu::rapikan((string) ($in['catatan'] ?? ($in['alasan'] ?? '')));
        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            return $this->gagal(422, 'catatan', 'Alasan wajib diisi (minimal ' . self::MIN_ALASAN . ' huruf) — siswa akan membacanya.');
        }
        if (mb_strlen($alasan) > PklAjuan::MAKS_CATATAN) {
            return $this->gagal(422, 'catatan', 'Alasan terlalu panjang (maksimal ' . PklAjuan::MAKS_CATATAN . ' huruf).');
        }
        $hasil = (new PklAjuan($this->db))->ubahStatus($id, 'ditolak', $konteks + ['aksi' => 'tolak_perusahaan'], $alasan, ['sumber' => 'perusahaan']);
        if (! $hasil['ok']) {
            return $this->gagal(500, 'galat', 'Penyimpanan gagal. Coba lagi sebentar.');
        }
        (new AuditModel())->record('update', 'pkl_pengajuan', $id, mb_substr('PKL ' . $kode . ' ditolak PERUSAHAAN (dari ' . $ajuan['status'] . ') — ' . $ajuan['perusahaan_nama'] . ' (' . $alasan . ')' . $this->saluran($konteks), 0, 255));

        return ['ok' => true, 'http' => 200, 'kode' => 'ok', 'pesan' => 'Ajuan ' . $kode . ' ditandai DITOLAK PERUSAHAAN. Seluruh siswanya bebas mengajukan tempat PKL lain.'];
    }

    // =================================================================
    // Bacaan
    // =================================================================

    /**
     * Siswa yang pernah dikeluarkan dari SATU ajuan (terbaru dulu).
     *
     * @return list<array<string, mixed>>
     */
    public function daftarAjuan(int $ajuanId): array
    {
        if (! self::tabelAda()) {
            return [];
        }
        $rows = $this->db->table('pkl_anggota_keluar k')
            ->select('k.*, s.nama, s.no_hp AS hp_master, kl.nama_kelas')
            ->join('siswa s', 's.id = k.siswa_id')
            ->join('kelas kl', 'kl.id = k.kelas_id', 'left')
            ->where('k.pengajuan_id', $ajuanId)
            ->orderBy('k.id', 'DESC')->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'siswa_id' => (int) $r['siswa_id'], 'nama' => (string) $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''),
            'peran' => (string) $r['peran'], 'hp' => trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : (string) ($r['hp_master'] ?? ''),
            'jenis' => (string) $r['jenis'], 'jenis_label' => self::labelJenis((string) $r['jenis']), 'alasan' => (string) $r['alasan'],
            'status_ajuan' => $r['status_ajuan'], 'sumber' => (string) $r['sumber'], 'oleh' => $r['oleh'], 'peran_staf' => $r['peran_staf'],
            'waktu' => (string) $r['created_at'], 'kabar_at' => $r['kabar_at'], 'kabar_oleh' => $r['kabar_oleh'],
        ], $rows);
    }

    /**
     * Riwayat PENOLAKAN banyak siswa sekaligus: ajuan yang ditolak (sekolah/perusahaan) + catatan dikeluarkan (kecuali jenis
     * "pindah"). $kecualiAjuan = ajuan yang sedang dibuka (tidak dihitung sebagai riwayat dirinya sendiri). Terbaru dulu.
     *
     * @param list<int> $siswaIds
     *
     * @return array<int, list<array{ajuan_id: int, kode: string, perusahaan: string, perusahaan_norm: string, sumber: string, jenis: string, keterangan: string, alasan: string, waktu: string}>>
     */
    public function tolakSiswa(array $siswaIds, int $kecualiAjuan = 0): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds))));
        $out      = [];
        if ($siswaIds === []) {
            return $out;
        }
        $sumberAda = PklAjuan::kolomTolakSumber($this->db);
        $b = $this->db->table('pkl_anggota a')
            ->select('a.siswa_id, p.id, p.perusahaan_nama, p.perusahaan_norm, p.catatan_staf, p.diputuskan_at, p.updated_at')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->whereIn('a.siswa_id', $siswaIds)->where('p.status', 'ditolak')->where('p.id !=', $kecualiAjuan);
        if ($sumberAda) {
            $b->select('p.tolak_sumber');
        }
        foreach ($b->get()->getResultArray() as $r) {
            $perusahaan = (($r['tolak_sumber'] ?? null) === 'perusahaan');
            $out[(int) $r['siswa_id']][] = [
                'ajuan_id' => (int) $r['id'], 'kode' => PklPengajuanModel::kode((int) $r['id']), 'perusahaan' => (string) $r['perusahaan_nama'],
                'perusahaan_norm' => (string) $r['perusahaan_norm'], 'sumber' => $perusahaan ? 'perusahaan' : 'sekolah',
                'jenis' => $perusahaan ? 'ditolak_perusahaan' : 'ditolak', 'keterangan' => $perusahaan ? 'Ditolak perusahaan' : 'Ajuan ditolak sekolah',
                'alasan' => (string) ($r['catatan_staf'] ?? ''), 'waktu' => (string) ($r['diputuskan_at'] ?? $r['updated_at']),
            ];
        }
        if (self::tabelAda()) {
            $rows = $this->db->table('pkl_anggota_keluar k')
                ->select('k.siswa_id, k.jenis, k.alasan, k.created_at, p.id, p.perusahaan_nama, p.perusahaan_norm')
                ->join('pkl_pengajuan p', 'p.id = k.pengajuan_id')
                ->whereIn('k.siswa_id', $siswaIds)->whereNotIn('k.jenis', self::BUKAN_TOLAK)->where('p.id !=', $kecualiAjuan)
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $out[(int) $r['siswa_id']][] = [
                    'ajuan_id' => (int) $r['id'], 'kode' => PklPengajuanModel::kode((int) $r['id']), 'perusahaan' => (string) $r['perusahaan_nama'],
                    'perusahaan_norm' => (string) $r['perusahaan_norm'], 'sumber' => $r['jenis'] === 'ditolak_perusahaan' ? 'perusahaan' : 'sekolah',
                    'jenis' => (string) $r['jenis'], 'keterangan' => 'Dikeluarkan: ' . self::labelJenis((string) $r['jenis']),
                    'alasan' => (string) $r['alasan'], 'waktu' => (string) $r['created_at'],
                ];
            }
        }
        foreach ($out as &$daftar) {
            usort($daftar, static fn (array $x, array $y): int => strcmp($y['waktu'], $x['waktu']));
        }
        unset($daftar);

        return $out;
    }

    /**
     * Riwayat PKL LENGKAP satu siswa (halaman riwayat & API): semua ajuan tempat ia masih tercatat (status apa pun) + catatan
     * dikeluarkan (semua jenis). Terbaru dulu. null bila siswa tidak ada.
     *
     * @return array{siswa: array<string, mixed>, aktif: ?array<string, mixed>, jumlah_ditolak: int, riwayat: list<array<string, mixed>>}|null
     */
    public function riwayatSiswa(int $siswaId): ?array
    {
        $s = $this->db->table('siswa s')->select('s.id, s.nama, s.nis, s.nisn, s.status, s.no_hp, k.nama_kelas')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->where('s.id', $siswaId)->where('s.deleted_at', null)->get()->getRowArray();
        if ($s === null) {
            return null;
        }
        $sumberAda = PklAjuan::kolomTolakSumber($this->db);
        $b = $this->db->table('pkl_anggota a')
            ->select('a.peran, a.siswa_aktif, p.id, p.status, p.perusahaan_nama, p.catatan_staf, p.created_at, p.diputuskan_at, p.acc_nama, p.acc_at')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')->where('a.siswa_id', $siswaId);
        if ($sumberAda) {
            $b->select('p.tolak_sumber');
        }
        $riwayat = [];
        $aktif   = null;
        foreach ($b->get()->getResultArray() as $r) {
            $perusahaan = $r['status'] === 'ditolak' && ($r['tolak_sumber'] ?? null) === 'perusahaan';
            $baris = [
                'jenis' => 'ajuan', 'ajuan_id' => (int) $r['id'], 'kode' => PklPengajuanModel::kode((int) $r['id']), 'perusahaan' => (string) $r['perusahaan_nama'],
                'status' => (string) $r['status'], 'tolak_sumber' => $perusahaan ? 'perusahaan' : ($r['status'] === 'ditolak' ? 'sekolah' : null),
                'keterangan' => match ($r['status']) {
                    'ditolak'   => $perusahaan ? 'Ditolak perusahaan' : 'Ditolak sekolah',
                    'disetujui' => 'Disetujui' . ($r['acc_nama'] ? ' oleh ' . $r['acc_nama'] : ''),
                    'perbaikan' => 'Dikembalikan untuk diperbaiki',
                    default     => 'Menunggu keputusan ACC',
                },
                'peran' => (string) $r['peran'], 'alasan' => in_array($r['status'], ['ditolak', 'perbaikan'], true) ? (string) ($r['catatan_staf'] ?? '') : '',
                'waktu' => (string) ($r['diputuskan_at'] ?? $r['created_at']), 'diajukan' => (string) $r['created_at'],
            ];
            $riwayat[] = $baris;
            if ($r['siswa_aktif'] !== null) {
                $aktif = $baris;
            }
        }
        if (self::tabelAda()) {
            $rows = $this->db->table('pkl_anggota_keluar k')
                ->select('k.jenis, k.alasan, k.peran, k.oleh, k.created_at, p.id, p.perusahaan_nama, p.created_at AS diajukan')
                ->join('pkl_pengajuan p', 'p.id = k.pengajuan_id')->where('k.siswa_id', $siswaId)->get()->getResultArray();
            foreach ($rows as $r) {
                $riwayat[] = [
                    'jenis' => 'keluar', 'ajuan_id' => (int) $r['id'], 'kode' => PklPengajuanModel::kode((int) $r['id']), 'perusahaan' => (string) $r['perusahaan_nama'],
                    'status' => 'dikeluarkan', 'tolak_sumber' => $r['jenis'] === 'ditolak_perusahaan' ? 'perusahaan' : null,
                    'keterangan' => 'Dikeluarkan dari kelompok: ' . self::labelJenis((string) $r['jenis']) . ($r['oleh'] ? ' (oleh ' . $r['oleh'] . ')' : ''),
                    'jenis_keluar' => (string) $r['jenis'], 'peran' => (string) $r['peran'], 'alasan' => (string) $r['alasan'],
                    'waktu' => (string) $r['created_at'], 'diajukan' => (string) $r['diajukan'],
                ];
            }
        }
        usort($riwayat, static fn (array $x, array $y): int => strcmp($y['waktu'], $x['waktu']) ?: ($y['ajuan_id'] <=> $x['ajuan_id']));
        $tolak = count(array_filter($riwayat, static fn (array $r): bool => $r['status'] === 'ditolak'
            || ($r['jenis'] === 'keluar' && ! in_array($r['jenis_keluar'] ?? '', self::BUKAN_TOLAK, true))));

        return [
            'siswa' => ['id' => (int) $s['id'], 'nama' => (string) $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? ''), 'nis' => $s['nis'], 'nisn' => $s['nisn'], 'status' => $s['status']],
            'aktif' => $aktif, 'jumlah_ditolak' => $tolak, 'riwayat' => $riwayat,
        ];
    }

    /**
     * Nama perusahaan "sama" untuk peringatan: nama pembanding sama persis, atau mirip ≥ 80%.
     */
    public static function perusahaanSama(string $normA, string $normB): bool
    {
        if ($normA === '' || $normB === '') {
            return false;
        }
        if ($normA === $normB) {
            return true;
        }
        similar_text($normA, $normB, $persen);

        return $persen >= 80.0;
    }

    /** Ringkasan satu baris riwayat penolakan, mis. "PT X (PKL-00012, 14-10-2026, ditolak perusahaan)". */
    public static function teksTolak(array $t): string
    {
        return $t['perusahaan'] . ' (' . $t['kode'] . ', ' . ($t['waktu'] !== '' ? date('d-m-Y', strtotime($t['waktu'])) : '?') . ', ' . mb_strtolower($t['keterangan']) . ')';
    }

    /** @return array{ok: false, http: int, kode: string, pesan: string} */
    private function gagal(int $http, string $kode, string $pesan): array
    {
        return ['ok' => false, 'http' => $http, 'kode' => $kode, 'pesan' => $pesan];
    }

    private function saluran(array $konteks): string
    {
        return ($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : '';
    }
}
