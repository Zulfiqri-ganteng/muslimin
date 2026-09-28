<?php

namespace App\Libraries;

use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\NotifAturanModel;
use App\Models\NotifLogModel;
use App\Models\NotifPengaturanModel;
use App\Models\NotifPerangkatModel;
use Closure;

/**
 * Mesin notifikasi "guru masuk kelas" — dijalankan cron tiap menit
 * (`spark notif:kirim`) dan dipakai pratinjau di aplikasi.
 *
 * 1. Diam bila: notif dimatikan, dijeda, bukan hari KBM, atau hari ujian.
 * 2. Sesi jadwal hari itu → BLOK MASUK: sesi berurutan (jam ke +1, shift sama)
 *    milik guru (orang) & kelas yang sama digabung; notif hanya di awal blok.
 * 3. Blok dicocokkan ke aturan admin (hari × shift × guru × jurusan). Cocok >1 aturan →
 *    dipakai menit_sebelum terbesar. Waktu kirim = jam masuk − menit.
 * 4. Blok dengan waktu kirim sama digabung jadi SATU notif.
 * 5. Anti dobel: slot diklaim lewat notif_log.kunci (UNIQUE) sebelum dikirim.
 * Isi notif tanpa mapel: nama guru (tanpa gelar), kelas, jam ke. Guru yang
 * sudah tercatat izin/sakit/alpa atau belum hadir diberi tanda ⚠ (kelas kosong).
 */
class NotifJadwal
{
    /** Cron telat / terlewat sampai sekian menit masih dikirim. */
    public const TOLERANSI_MENIT = 10;

    /** Kunci cache "detak" cron: timestamp terakhir `notif:kirim` dijalankan cron. */
    public const CACHE_DETAK = 'notif_cron_detak';

    /** Detik sejak cron terakhir jalan, atau null bila belum pernah tercatat. */
    public static function detikSejakCron(): ?int
    {
        $t = cache()->get(self::CACHE_DETAK);

        return is_int($t) ? max(0, time() - $t) : null;
    }

    private const LABEL_KOSONG = ['izin' => 'izin', 'sakit' => 'sakit', 'alpa' => 'tidak hadir'];

    /** Pengganti Fcm::kirim untuk uji otomatis (null = kirim sungguhan). */
    public static ?Closure $pengirim = null;

    /** @var array<int,list<array>> memo blok per hari_id dalam satu proses */
    private static array $memoBlok = [];

    /** Alasan notif DIAM pada tanggal itu (null = boleh kirim). */
    public static function alasanDiam(array $pengaturan, string $tanggal, ?array $hari): ?string
    {
        if (! $pengaturan['aktif']) {
            return 'Notifikasi sedang dimatikan.';
        }
        if ($pengaturan['jeda_sampai'] !== null && $tanggal <= $pengaturan['jeda_sampai']) {
            return 'Dijeda sampai ' . AbsensiWa::tanggalIndo($pengaturan['jeda_sampai']) . '.';
        }
        if (! $hari || (int) $hari['aktif'] !== 1) {
            return 'Bukan hari KBM (libur).';
        }
        if ($pengaturan['diam_saat_ujian'] && self::hariUjian($tanggal)) {
            return 'Hari ujian — KBM biasa tidak berjalan.';
        }

        return null;
    }

    /** Tanggal masuk rentang periode ujian, atau ada jadwal ujian pada tanggal itu. */
    public static function hariUjian(string $tanggal): bool
    {
        $db = db_connect();

        $periode = $db->table('ujian_periode')
            ->where('deleted_at', null)
            ->where('tanggal_mulai IS NOT NULL')
            ->where('tanggal_selesai IS NOT NULL')
            ->where('tanggal_mulai <=', $tanggal)
            ->where('tanggal_selesai >=', $tanggal)
            ->countAllResults();
        if ($periode > 0) {
            return true;
        }

        return $db->table('ujian_jadwal j')
            ->join('ujian_periode p', 'p.id = j.periode_id')
            ->where('j.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('j.tanggal', $tanggal)
            ->countAllResults() > 0;
    }

    /**
     * Blok masuk kelas satu hari.
     *
     * @return list<array{oid:int,nama:string,kelas_id:int,kelas:string,urut:int,jurusan_id:int,shift:string,awal:int,akhir:int,mulai:string,jam_ids:list<int>}>
     */
    public static function blokHari(int $hariId): array
    {
        if (isset(self::$memoBlok[$hariId])) {
            return self::$memoBlok[$hariId];
        }

        $sesi = (new JadwalModel())->sessionsForHari($hariId);
        $peta = GuruModel::petaOrang();
        $nama = [];
        foreach ((new GuruModel())->withDeleted()->select('id, nama')->findAll() as $g) {
            $nama[(int) $g['id']] = (string) $g['nama'];
        }
        $urut = KelasModel::urutNatural(array_column($sesi, 'nama_kelas', 'kelas_id'));

        foreach ($sesi as &$s) {
            $s['oid'] = $peta[(int) $s['guru_id']] ?? (int) $s['guru_id'];
        }
        unset($s);
        usort($sesi, static fn ($a, $b) => [$a['oid'], (int) $a['kelas_id'], $a['jam_shift'], (string) $a['waktu_mulai']]
            <=> [$b['oid'], (int) $b['kelas_id'], $b['jam_shift'], (string) $b['waktu_mulai']]);

        $blok = [];
        $cur  = null;
        foreach ($sesi as $s) {
            $jamKe = (int) $s['jam_ke'];
            if ($cur !== null && $cur['oid'] === $s['oid'] && $cur['kelas_id'] === (int) $s['kelas_id']
                && $cur['shift'] === $s['jam_shift'] && $cur['akhir'] + 1 === $jamKe) {
                $cur['akhir']     = $jamKe;
                $cur['jam_ids'][] = (int) $s['jam_id'];

                continue;
            }
            if ($cur !== null) {
                $blok[] = $cur;
            }
            $cur = [
                'oid'        => $s['oid'],
                'nama'       => AbsensiWa::namaTanpaGelar($nama[$s['oid']] ?? (string) $s['guru_nama']),
                'kelas_id'   => (int) $s['kelas_id'],
                'kelas'      => (string) $s['nama_kelas'],
                'urut'       => $urut[(int) $s['kelas_id']] ?? 0,
                'jurusan_id' => (int) ($s['jurusan_id'] ?? 0),
                'shift'      => (string) $s['jam_shift'],
                'awal'       => $jamKe,
                'akhir'      => $jamKe,
                'mulai'      => substr((string) $s['waktu_mulai'], 0, 5),
                'jam_ids'    => [(int) $s['jam_id']],
            ];
        }
        if ($cur !== null) {
            $blok[] = $cur;
        }

        return self::$memoBlok[$hariId] = $blok;
    }

    /** Kosongkan memo (dipakai uji & proses panjang). */
    public static function lupakan(): void
    {
        self::$memoBlok = [];
    }

    /**
     * Rencana notif satu admin pada satu tanggal, dikelompokkan per waktu kirim.
     *
     * @return array{tanggal:string,diam:?string,grup:list<array{slot:string,kirim_ts:int,item:list<array>}>}
     */
    public static function rencana(int $adminId, string $tanggal): array
    {
        $hari   = (new HariModel())->byWeekday((int) date('N', strtotime($tanggal)));
        $diam   = self::alasanDiam((new NotifPengaturanModel())->ambil($adminId), $tanggal, $hari);
        $aturan = (new NotifAturanModel())->milik($adminId, true);
        if ($diam === null && $aturan === []) {
            $diam = 'Belum ada aturan notifikasi yang aktif.';
        }
        if ($diam !== null) {
            return ['tanggal' => $tanggal, 'diam' => $diam, 'grup' => []];
        }

        $hariId = (int) $hari['id'];
        $grup   = [];
        foreach (self::blokHari($hariId) as $b) {
            $menit = null;
            foreach ($aturan as $a) {
                if (! in_array($hariId, $a['hari'], true)
                    || ($a['shift'] !== 'semua' && $a['shift'] !== $b['shift'])
                    || ($a['guru'] !== [] && ! in_array($b['oid'], $a['guru'], true))
                    || ($a['jurusan'] !== [] && ! in_array($b['jurusan_id'], $a['jurusan'], true))) {
                    continue;
                }
                $menit = max($menit ?? 0, $a['menit_sebelum']);
            }
            if ($menit === null) {
                continue;
            }
            $ts   = strtotime($tanggal . ' ' . $b['mulai'] . ':00') - $menit * 60;
            $slot = date('H:i', $ts);
            $grup[$slot] ??= ['slot' => $slot, 'kirim_ts' => $ts, 'item' => []];
            $grup[$slot]['item'][] = $b + ['menit' => $menit];
        }
        ksort($grup);
        foreach ($grup as &$g) {
            usort($g['item'], static fn ($x, $y) => [$x['urut'], $x['awal']] <=> [$y['urut'], $y['awal']]);
        }
        unset($g);

        return ['tanggal' => $tanggal, 'diam' => null, 'grup' => array_values($grup)];
    }

    /**
     * Status kehadiran tercatat per blok: 'izin' | 'sakit' | 'tidak hadir' |
     * 'belum hadir' | null (hadir / belum dicatat).
     */
    public static function statusKosong(array $b, array $absen, array $belum): ?string
    {
        foreach ($b['jam_ids'] as $jamId) {
            $st = $absen[$b['kelas_id'] . '-' . $jamId]['status'] ?? null;
            if ($st !== null && isset(self::LABEL_KOSONG[$st])) {
                return self::LABEL_KOSONG[$st];
            }
        }

        return in_array($b['oid'], $belum[$b['shift']] ?? [], true) ? 'belum hadir' : null;
    }

    /**
     * Judul & isi satu notif (satu kelompok waktu kirim).
     *
     * @return array{judul:string,isi:string,kosong:int}
     */
    public static function susunPesan(array $item, array $absen = [], array $belum = []): array
    {
        $jam = static fn ($b) => $b['awal'] === $b['akhir'] ? (string) $b['awal'] : $b['awal'] . '-' . $b['akhir'];

        $kosong = 0;
        $status = [];
        foreach ($item as $i => $b) {
            $status[$i] = self::statusKosong($b, $absen, $belum);
            $kosong += $status[$i] !== null ? 1 : 0;
        }
        $tanda = $kosong > 0 ? '⚠ ' : '';

        if (count($item) === 1) {
            $b  = $item[0];
            $st = $status[0];

            return [
                'judul'  => $tanda . $b['nama'] . ' masuk ' . $b['kelas'],
                'isi'    => 'Jam ke ' . $jam($b) . ' · ' . $b['mulai'] . ' · KBM ' . $b['shift'] . ($st !== null ? ' · ' . strtoupper($st) : ''),
                'kosong' => $kosong,
            ];
        }

        $mulai  = array_unique(array_column($item, 'mulai'));
        $shift  = array_unique(array_column($item, 'shift'));
        sort($mulai);
        $banyakMulai = count($mulai) > 1;
        $baris = [];
        foreach ($item as $i => $b) {
            $baris[] = ($status[$i] !== null ? '⚠ ' : '') . $b['nama'] . ' → ' . $b['kelas'] . ' · jam ke ' . $jam($b)
                . ($banyakMulai ? ' · ' . $b['mulai'] : '')
                . ($status[$i] !== null ? ' (' . $status[$i] . ')' : '');
        }

        return [
            'judul'  => $tanda . 'Jadwal masuk ' . $mulai[0] . (count($shift) === 1 ? ' · KBM ' . $shift[0] : '') . ' · ' . count($item) . ' guru',
            'isi'    => implode("\n", $baris),
            'kosong' => $kosong,
        ];
    }

    /**
     * Kirim satu notif ke daftar perangkat; token yang ditolak Firebase dibuang.
     *
     * @return array{berhasil:int,gagal:int,galat:list<string>}
     */
    public static function kirimKePerangkat(array $perangkat, string $judul, string $isi, array $data): array
    {
        $kirim = self::$pengirim ?? static fn (string $t, string $j, string $i, array $d) => Fcm::kirim($t, $j, $i, $d);
        $model = new NotifPerangkatModel();
        $out   = ['berhasil' => 0, 'gagal' => 0, 'galat' => []];

        foreach ($perangkat as $p) {
            $r = $kirim((string) $p['token'], $judul, $isi, $data);
            if ($r['ok']) {
                $out['berhasil']++;

                continue;
            }
            $out['gagal']++;
            $out['galat'][] = ($p['nama_perangkat'] ?: $p['device_id']) . ': ' . $r['kode'] . ' ' . $r['pesan'];
            if ($r['token_mati']) {
                $model->buangToken((string) $p['token']);
            }
        }

        return $out;
    }

    /**
     * Satu putaran cron: kirim notif yang waktu kirimnya jatuh di
     * (sekarang − toleransi, sekarang]. $kering = hanya laporkan, tanpa kirim
     * & tanpa klaim.
     *
     * @return array{tanggal:string,jam:string,kelompok:int,berhasil:int,gagal:int,catatan:list<string>}
     */
    public static function jalankan(?int $sekarang = null, bool $kering = false): array
    {
        $now     = $sekarang ?? time();
        $menit   = $now - ($now % 60);
        $tanggal = date('Y-m-d', $now);
        $hasil   = ['tanggal' => $tanggal, 'jam' => date('H:i', $now), 'kelompok' => 0, 'berhasil' => 0, 'gagal' => 0, 'catatan' => []];

        if (! $kering && self::$pengirim === null && ! Fcm::siap()) {
            $hasil['catatan'][] = 'Firebase belum dipasang (FCM_KEY_PATH) — tidak ada yang dikirim.';

            return $hasil;
        }

        $penerima = (new NotifPerangkatModel())->penerimaPerAdmin();
        $absen    = null;
        $belum    = null;
        $log      = new NotifLogModel();

        foreach ($penerima as $adminId => $perangkat) {
            $r = self::rencana($adminId, $tanggal);
            if ($r['diam'] !== null) {
                continue;
            }
            foreach ($r['grup'] as $g) {
                if ($g['kirim_ts'] > $menit || $g['kirim_ts'] <= $menit - self::TOLERANSI_MENIT * 60) {
                    continue;
                }
                // Data absensi dibaca sekali per putaran, hanya bila ada yang dikirim.
                $absen ??= (new AbsensiGuruModel())->forDate($tanggal);
                $belum ??= (new AbsensiBelumModel())->forDate($tanggal);
                $pesan = self::susunPesan($g['item'], $absen, $belum);
                $data  = ['jenis' => 'jadwal', 'tanggal' => $tanggal, 'shift' => $g['item'][0]['shift'], 'slot' => $g['slot']];

                if ($kering) {
                    $hasil['kelompok']++;
                    $hasil['catatan'][] = "[admin {$adminId}] {$g['slot']} — {$pesan['judul']}\n" . $pesan['isi'];

                    continue;
                }

                $kunci = 'jadwal:' . $adminId . ':' . $tanggal . ':' . $g['slot'];
                $logId = $log->klaim([
                    'admin_id'      => $adminId,
                    'jenis'         => 'jadwal',
                    'kunci'         => $kunci,
                    'tanggal'       => $tanggal,
                    'slot'          => $g['slot'] . ':00',
                    'judul'         => mb_substr($pesan['judul'], 0, 255),
                    'isi'           => $pesan['isi'],
                    'data'          => $data,
                    'jml_perangkat' => count($perangkat),
                ]);
                if ($logId === null) {
                    continue; // sudah dikirim putaran / proses lain
                }

                $k    = self::kirimKePerangkat($perangkat, $pesan['judul'], $pesan['isi'], $data);
                $ubah = [
                    'berhasil' => $k['berhasil'],
                    'gagal'    => $k['gagal'],
                    'galat'    => $k['galat'] !== [] ? mb_substr(implode("\n", $k['galat']), 0, 2000) : null,
                ];
                // SEMUA HP gagal (mis. koneksi ke Google putus sesaat) → lepas kunci agar
                // putaran berikutnya (masih dalam toleransi) mencoba lagi. Baris tetap
                // tersimpan sebagai riwayat percobaan gagal.
                if ($k['berhasil'] === 0) {
                    $ubah['kunci'] = mb_substr('gagal:' . $kunci . ':' . $now, 0, 100);
                }
                $log->update($logId, $ubah);
                $hasil['kelompok']++;
                $hasil['berhasil'] += $k['berhasil'];
                $hasil['gagal'] += $k['gagal'];
                $hasil['catatan'][] = "[admin {$adminId}] {$g['slot']} {$pesan['judul']} → {$k['berhasil']} ok, {$k['gagal']} gagal";
            }
        }

        return $hasil;
    }
}
