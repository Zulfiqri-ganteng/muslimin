<?php

namespace App\Libraries;

use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;

/**
 * Jadwal Pengawas per RUANG (F3 cetak jadwal, permintaan user 2026-11-22; contoh hasil: "jadwal pengawas.xlsx").
 *
 * Satu pengawas per ruang (1..N) per sesi. Sesi = slot jam Jadwal Ujian satu shift (diambil dari JadwalUjianCetak::matriks →
 * sesi pengawas PASTI sama dengan cetakan Jadwal Ujian; "JAM KE-" = urutan sesi dalam sehari, istirahat tidak dihitung).
 * Pengawas diambil dari daftar guru yang dicentang per periode (ujian_pengawas_guru); ditampilkan & diketik memakai KODE GURU
 * (Master Guru) seperti contoh sekolah. Aturan simpan: kode wajib ada di daftar; satu guru tidak boleh di dua ruang pada sesi yang
 * sama; tidak boleh bentrok jam dengan tugasnya di shift lain.
 */
class JadwalPengawas
{
    /** Kunci satu sesi: "2026-11-30|07:00:00|08:30:00". */
    public static function kunci(string $tanggal, ?string $mulai, ?string $selesai): string
    {
        return $tanggal . '|' . ($mulai ?? '') . '|' . ($selesai ?? '');
    }

    /**
     * Sesi per hari satu shift.
     *
     * @return list<array{tanggal: string, baris: list<array{jenis: string, mulai: ?string, selesai: ?string, waktu: string, jamKe: ?int, kunci: ?string}>}>
     */
    public static function sesi(array $periode, string $shift): array
    {
        $hari = [];
        foreach (JadwalUjianCetak::matriks($periode, $shift)['hari'] as $h) {
            $no    = 0;
            $baris = [];
            foreach ($h['baris'] as $b) {
                $adalahSesi = $b['jenis'] === 'sesi';
                $baris[] = [
                    'jenis'   => $b['jenis'],
                    'mulai'   => $b['mulai'] !== '' ? $b['mulai'] : null,
                    'selesai' => $b['selesai'] !== '' ? $b['selesai'] : null,
                    'waktu'   => $b['waktu'],
                    'jamKe'   => $adalahSesi ? ++$no : null,
                    'kunci'   => $adalahSesi ? self::kunci($h['tanggal'], $b['mulai'], $b['selesai']) : null,
                ];
            }
            $hari[] = ['tanggal' => $h['tanggal'], 'baris' => $baris];
        }

        return $hari;
    }

    /** Kode guru dirapikan (spasi dibuang, huruf besar). */
    public static function kodeBersih(?string $kode): string
    {
        return mb_strtoupper(trim((string) $kode));
    }

    /**
     * Angka pengawas OTOMATIS = angka di depan Kode Guru KBM, huruf dibuang (klien 2026-11-23: cetakan pengawas hanya angka):
     * "13a" → "13", "20A" → "20", "36(1)" → "36", "07" → "7". Tanpa angka di depan (mis. "AB") → '' (admin mengisi angka pengganti).
     */
    public static function angkaDepan(string $kode): string
    {
        if (! preg_match('/^\s*(\d+)/', $kode, $m)) {
            return '';
        }
        $n = (int) $m[1];

        return $n > 0 && $n <= 9999 ? (string) $n : '';
    }

    /** Kunci nama tanpa gelar — mengenali orang yang sama tercatat dua kali di Master Guru (mis. Irvan 23 & 23A). */
    private static function kunciNama(string $nama): string
    {
        return (string) preg_replace('/[^a-z]/', '', mb_strtolower(explode(',', $nama)[0]));
    }

    /**
     * Dua baris guru = ORANG YANG SAMA? Nama (tanpa gelar) persis sama; ATAU berangka pengawas sama DAN nama pendek = awal nama panjang
     * per kata — data hosting mencatat orang yang sama dengan nama dipendekkan: "Indri, S.Pd" (36(1)) & "Indri Rachmawati Chasanah, S.Pd"
     * (36), juga 9(1)/9, 31(1)/31, 34A/34. Syarat angka sama mencegah "Siti" & "Siti Hajar" berangka beda dianggap satu orang.
     */
    private static function samaOrang(array $a, array $b): bool
    {
        if (self::kunciNama($a['nama']) === self::kunciNama($b['nama'])) {
            return true;
        }
        if (($a['nomor'] ?? '') === '' || $a['nomor'] !== ($b['nomor'] ?? '')) {
            return false;
        }
        $kata = static fn (string $n): array => preg_split('/\s+/', trim((string) preg_replace('/[^a-z\s]/', '', mb_strtolower(explode(',', $n)[0]))), -1, PREG_SPLIT_NO_EMPTY);
        $ka   = $kata($a['nama']);
        $kb   = $kata($b['nama']);
        $n    = min(count($ka), count($kb));

        return $n > 0 && array_slice($ka, 0, $n) === array_slice($kb, 0, $n);
    }

    /**
     * Semua guru aktif untuk daftar centang, diurutkan kode (angka alami; tanpa kode di belakang).
     * 'nomor' = angka pengawas yang DIPAKAI (pengganti dari admin bila ada, selain itu angka depan Kode Guru);
     * 'masalah' = '' | 'tanpa-angka' (kode tak berangka & belum ada pengganti) | 'bentrok' (angka sama dengan guru lain yang BEDA orang).
     *
     * @return list<array{id: int, nama: string, kode: string, otomatis: string, pengganti: string, nomor: string, masalah: string, ganda: bool}>
     */
    public static function semuaGuru(): array
    {
        $rows = db_connect()->table('guru')->select('id, nama, kode_guru, kode_pengawas')->where('deleted_at', null)->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $kode      = self::kodeBersih($r['kode_guru']);
            $otomatis  = self::angkaDepan($kode);
            $pengganti = $r['kode_pengawas'] !== null && (int) $r['kode_pengawas'] > 0 ? (string) (int) $r['kode_pengawas'] : '';
            $out[]     = ['id' => (int) $r['id'], 'nama' => (string) $r['nama'], 'kode' => $kode, 'otomatis' => $otomatis,
                'pengganti' => $pengganti, 'nomor' => $pengganti !== '' ? $pengganti : $otomatis];
        }

        return self::tandaiMasalah($out);
    }

    /**
     * Isi 'ganda' (Kode Guru dipakai >1 guru) & 'masalah' (lihat semuaGuru) — dipisah supaya simpanGuru() bisa menilai ulang setelah
     * angka pengganti baru diterapkan di memori, sebelum disimpan.
     *
     * @param list<array<string, mixed>> $out
     *
     * @return list<array<string, mixed>>
     */
    private static function tandaiMasalah(array $out): array
    {
        $hitung   = array_count_values(array_filter(array_column($out, 'kode'), static fn (string $k): bool => $k !== ''));
        $perNomor = []; // angka → indeks guru pemakainya
        foreach ($out as $i => $g) {
            if ($g['nomor'] !== '' && $g['kode'] !== '') {
                $perNomor[$g['nomor']][] = $i;
            }
        }
        $bentrok = [];
        foreach ($perNomor as $idx) {
            foreach ($idx as $i) {
                foreach ($idx as $j) {
                    if ($i !== $j && ! self::samaOrang($out[$i], $out[$j])) {
                        $bentrok[$i] = true; // angka sama dengan guru lain yang BEDA orang
                    }
                }
            }
        }
        foreach ($out as $i => &$g) {
            $g['ganda']   = $g['kode'] !== '' && ($hitung[$g['kode']] ?? 0) > 1;
            $g['masalah'] = '';
            if ($g['kode'] === '') {
                continue;
            }
            if ($g['nomor'] === '') {
                $g['masalah'] = 'tanpa-angka';
            } elseif (isset($bentrok[$i])) {
                $g['masalah'] = 'bentrok';
            }
        }
        unset($g);
        usort($out, static function (array $a, array $b): int {
            if (($a['kode'] === '') !== ($b['kode'] === '')) {
                return $a['kode'] === '' ? 1 : -1;
            }

            return strnatcasecmp($a['kode'], $b['kode']) ?: strcasecmp($a['nama'], $b['nama']);
        });

        return $out;
    }

    /** Id guru pengawas yang dicentang untuk periode ini. @return list<int> */
    public static function guruTerpilih(int $periodeId): array
    {
        return array_map('intval', array_column(
            db_connect()->table('ujian_pengawas_guru')->select('guru_id')->where('periode_id', $periodeId)->get()->getResultArray(),
            'guru_id'
        ));
    }

    /**
     * Saran daftar guru dari periode lain yang paling baru disimpan (dipakai saat periode ini belum punya daftar).
     *
     * @return array{label: string, ids: list<int>}|null
     */
    public static function saran(array $periode): ?array
    {
        $db  = db_connect();
        $row = $db->table('ujian_pengawas_guru')->select('periode_id, MAX(id) AS terakhir')
            ->where('periode_id !=', (int) $periode['id'])->groupBy('periode_id')->orderBy('terakhir', 'DESC')->limit(1)
            ->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        $lain = (new UjianPeriodeModel())->find((int) $row['periode_id']);
        if ($lain === null) {
            return null;
        }

        return ['label' => (new UjianPeriodeModel())->label($lain), 'ids' => self::guruTerpilih((int) $lain['id'])];
    }

    /**
     * Pengawas terpilih yang punya ANGKA pengawas → [ANGKA => ['id' => .., 'nama' => ..]] (dipakai isian grid, Generate, datalist).
     *
     * @return array<string, array{id: int, nama: string}>
     */
    public static function petaKode(int $periodeId): array
    {
        $pilih = array_flip(self::guruTerpilih($periodeId));
        $peta  = [];
        foreach (self::semuaGuru() as $g) {
            if (isset($pilih[$g['id']]) && $g['nomor'] !== '' && ! isset($peta[$g['nomor']])) {
                $peta[$g['nomor']] = ['id' => $g['id'], 'nama' => $g['nama']];
            }
        }

        return $peta;
    }

    /**
     * Masalah angka pengawas pada daftar guru TERPILIH periode ini (kosong = aman). Bisa muncul belakangan bila Kode Guru diubah di
     * Master Guru sesudah daftar disimpan — Generate, simpan grid, dan cetak menolak sampai dibereskan (angka harus menunjuk satu orang).
     *
     * @return list<string>
     */
    public static function masalahNomor(int $periodeId): array
    {
        $pilih = array_flip(self::guruTerpilih($periodeId));
        $per   = [];
        $out   = [];
        foreach (self::semuaGuru() as $g) {
            if (! isset($pilih[$g['id']])) {
                continue;
            }
            if ($g['nomor'] === '') {
                $out[] = $g['nama'] . ' (kode ' . $g['kode'] . ') belum punya angka pengawas.';

                continue;
            }
            $per[$g['nomor']][] = $g['nama'] . ' (kode ' . $g['kode'] . ')';
        }
        foreach ($per as $n => $nama) {
            if (count($nama) > 1) {
                $out[] = 'Angka ' . $n . ' dipakai ' . count($nama) . ' guru: ' . implode(' & ', $nama) . '.';
            }
        }

        return $out;
    }

    /**
     * Simpan daftar guru pengawas + ANGKA PENGGANTI (klien 2026-11-23: cetakan pengawas hanya angka). Hanya guru aktif berkode.
     * Angka pengganti diisi admin untuk guru yang Kode Guru-nya tak berangka atau angkanya bentrok dengan guru lain (beda orang);
     * kosong = kembali ke angka otomatis. Ditolak bila: pengganti bukan angka 1–9999 / sama dengan angka guru lain; guru terpilih tanpa
     * angka; dua guru terpilih berangka sama; orang yang sama dicentang dua kali. Semua dicek dulu, baru disimpan dalam satu transaksi.
     * Penugasan milik guru yang dikeluarkan dari daftar ikut dikosongkan.
     *
     * @param list<int|string>          $guruIds
     * @param array<int|string, string> $pengganti [guru_id => angka|'' ] dari kotak angka pengganti
     *
     * @return array{ok: bool, pesan: string, jumlah?: int, dikosongkan?: int}
     */
    public static function simpanGuru(array $periode, array $guruIds, array $pengganti = []): array
    {
        $semua = self::semuaGuru();
        $byId  = array_column($semua, null, 'id');

        // 1) Angka pengganti dari kotak (hanya guru yang kotaknya tampil).
        $ganti = [];
        foreach ($pengganti as $gid => $v) {
            $gid = (int) $gid;
            if (! isset($byId[$gid]) || is_array($v)) {
                continue;
            }
            $v = trim((string) $v);
            if ($v === '') {
                $ganti[$gid] = null;

                continue;
            }
            if (! ctype_digit($v) || (int) $v < 1 || (int) $v > 9999) {
                return ['ok' => false, 'pesan' => 'Angka pengawas ' . $byId[$gid]['nama'] . ' harus berupa angka 1–9999 tanpa huruf (diisi: "' . $v . '").'];
            }
            $ganti[$gid] = (int) $v;
        }
        foreach ($semua as &$g) {
            if (array_key_exists($g['id'], $ganti)) {
                $g['pengganti'] = $ganti[$g['id']] === null ? '' : (string) $ganti[$g['id']];
                $g['nomor']     = $g['pengganti'] !== '' ? $g['pengganti'] : $g['otomatis'];
            }
        }
        unset($g);
        $semua = self::tandaiMasalah($semua);

        // 2) Angka pengganti tak boleh sama dengan angka guru lain (orang berbeda) — angka tetap unik di semua ujian.
        foreach ($ganti as $gid => $n) {
            if ($n === null) {
                continue;
            }
            $diri = ['nama' => $byId[$gid]['nama'], 'nomor' => (string) $n];
            foreach ($semua as $h) {
                if ($h['id'] !== $gid && $h['kode'] !== '' && $h['nomor'] === (string) $n && ! self::samaOrang($diri, $h)) {
                    return ['ok' => false, 'pesan' => 'Angka ' . $n . ' untuk ' . $byId[$gid]['nama'] . ' sudah dipakai ' . $h['nama'] . ' (kode ' . $h['kode'] . '). Pilih angka lain yang belum dipakai.'];
                }
            }
        }

        $ids   = array_flip(array_map('intval', $guruIds));
        $pilih = array_values(array_filter($semua, static fn (array $g): bool => isset($ids[$g['id']]) && $g['kode'] !== ''));

        // 3) Orang yang sama tercatat dua kali di Master Guru (data hosting: Irvan Hidayat 23 & 23A, Debi Saputra 60 & 41) → bila
        // keduanya dicentang, Generate bisa memberi satu orang dua ruang pada jam yang sama. Nama dibandingkan tanpa gelar.
        $kembar = [];
        foreach ($pilih as $i => $a) {
            foreach ($pilih as $j => $b) {
                if ($j > $i && self::samaOrang($a, $b)) {
                    $kembar[] = $a['nama'] . ' (kode ' . $a['kode'] . ') & ' . $b['nama'] . ' (kode ' . $b['kode'] . ')';
                }
            }
        }
        if ($kembar !== []) {
            return ['ok' => false, 'pesan' => 'Guru yang sama tercentang dua kali: ' . implode('; ', $kembar)
                . '. Centang salah satu kodenya saja (data gandanya bisa dirapikan di Master Guru).'];
        }

        // 4) Tiap guru terpilih wajib berangka, dan satu angka hanya untuk satu guru terpilih.
        $tanpa   = [];
        $perNomor = [];
        foreach ($pilih as $g) {
            if ($g['nomor'] === '') {
                $tanpa[] = $g['nama'] . ' (kode ' . $g['kode'] . ')';
            } else {
                $perNomor[$g['nomor']][] = $g['nama'] . ' (kode ' . $g['kode'] . ')';
            }
        }
        if ($tanpa !== []) {
            return ['ok' => false, 'pesan' => 'Kode guru berikut tidak punya angka: ' . implode('; ', $tanpa) . '. Isi angka pengawasnya di kotak samping nama, lalu Simpan lagi.'];
        }
        $bentrok = array_filter($perNomor, static fn (array $n): bool => count($n) > 1);
        if ($bentrok !== []) {
            $teks = [];
            foreach ($bentrok as $n => $nama) {
                $teks[] = 'angka ' . $n . ' (' . implode(' & ', $nama) . ')';
            }

            return ['ok' => false, 'pesan' => 'Satu angka hanya boleh untuk satu pengawas: ' . implode('; ', $teks) . '. Isi angka pengganti di kotak samping nama salah satunya, atau centang salah satu saja.'];
        }

        $pid     = (int) $periode['id'];
        $baru    = array_column($pilih, 'id');
        $db      = db_connect();
        $db->transException(true);
        $db->transStart();
        foreach ($ganti as $gid => $n) {
            $db->table('guru')->where('id', $gid)->update(['kode_pengawas' => $n]);
        }
        $db->table('ujian_pengawas_guru')->where('periode_id', $pid)->delete();
        if ($baru !== []) {
            $now = date('Y-m-d H:i:s');
            $db->table('ujian_pengawas_guru')->insertBatch(array_map(static fn (int $id): array => ['periode_id' => $pid, 'guru_id' => $id, 'created_at' => $now], $baru));
        }
        $kosong = $db->table('ujian_pengawas_ruang')->where('periode_id', $pid);
        if ($baru !== []) {
            $kosong->whereNotIn('guru_id', $baru);
        }
        $kosong->delete();
        $dikosongkan = $db->affectedRows();
        $db->transComplete();

        $nGanti = count(array_filter($ganti, static fn (?int $n): bool => $n !== null));

        return ['ok' => true, 'pesan' => count($baru) . ' guru pengawas disimpan.' . ($nGanti > 0 ? ' ' . $nGanti . ' angka pengganti disimpan.' : '')
            . ($dikosongkan > 0 ? ' ' . $dikosongkan . ' sel jadwal pengawas milik guru yang dikeluarkan dari daftar ikut dikosongkan.' : ''),
            'jumlah' => count($baru), 'dikosongkan' => $dikosongkan];
    }

    /**
     * Ubah jumlah ruang satu shift dari tab Jadwal Pengawas (klien 2026-11-23: "bisa kurang bisa nambah", mis. 21 → 22). Sama dengan
     * isian "Jumlah ruang ujian" di tab Periode. Dikurangi → isi ruang yang hilang ikut dihapus; ditambah → ruang baru kosong.
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function ubahJumlahRuang(array $periode, string $shift, int $n): array
    {
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            return ['ok' => false, 'pesan' => 'Shift tidak dikenal.'];
        }
        if ($n < 1 || $n > 200) {
            return ['ok' => false, 'pesan' => 'Jumlah ruang harus 1 sampai 200.'];
        }
        $lama = UjianPeriodeModel::jumlahRuang($periode, $shift);
        $db   = db_connect();
        $db->table('ujian_periode')->where('id', (int) $periode['id'])->update(['ruang_' . $shift => $n]);
        $hapus = self::rapikanRuang($periode, [$shift => $n]);

        $pesan = 'Jumlah ruang ' . $shift . ' sekarang ' . $n . ' (sebelumnya ' . $lama . ').';
        if ($n > $lama) {
            $pesan .= ' Ruang ' . ($lama + 1) . ($n > $lama + 1 ? '–' . $n : '') . ' masih kosong — tekan Generate otomatis atau isi manual.';
        }
        if ($hapus > 0) {
            $pesan .= ' ' . $hapus . ' sel pengawas di ruang yang dihapus ikut dikosongkan.';
        }

        return ['ok' => true, 'pesan' => $pesan];
    }

    /**
     * Hapus penugasan di ruang yang melebihi jumlah ruang shift-nya (sesudah jumlah ruang dikurangi, di tab ini atau tab Periode),
     * supaya tak ada sisa tersembunyi yang ikut terhitung beban/bentrok saat Generate.
     *
     * @param array<string, int> $jumlah paksa jumlah per shift (kosong = dihitung dari periode)
     *
     * @return int jumlah sel yang dihapus
     */
    public static function rapikanRuang(array $periode, array $jumlah = []): int
    {
        $db    = db_connect();
        $hapus = 0;
        foreach (['pagi', 'siang'] as $s) {
            $n = $jumlah[$s] ?? UjianPeriodeModel::jumlahRuang($periode, $s);
            if ($n < 1) {
                continue;
            }
            $db->table('ujian_pengawas_ruang')->where('periode_id', (int) $periode['id'])->where('shift', $s)->where('ruang >', $n)->delete();
            $hapus += $db->affectedRows();
        }

        return $hapus;
    }

    /** Penugasan satu shift: [kunci sesi => [ruang => guru_id]]. @return array<string, array<int, int>> */
    public static function penugasan(int $periodeId, string $shift): array
    {
        $peta = [];
        foreach (db_connect()->table('ujian_pengawas_ruang')->select('tanggal, jam_mulai, jam_selesai, ruang, guru_id')
            ->where('periode_id', $periodeId)->where('shift', $shift)->get()->getResultArray() as $r) {
            $peta[self::kunci((string) $r['tanggal'], $r['jam_mulai'], $r['jam_selesai'])][(int) $r['ruang']] = (int) $r['guru_id'];
        }

        return $peta;
    }

    /**
     * Simpan grid satu shift (seluruh isi shift itu diganti). Isian: [kunci sesi => [ruang => KODE]]; sel kosong = belum diisi.
     * $hanyaKunci (2026-11-24, tombol Ubah per sesi) = hanya sesi itu yang diganti; sesi lain tidak disentuh, aturan ceknya sama.
     *
     * @return array{ok: bool, pesan: string, galat?: list<string>, terisi?: int}
     */
    public static function simpanGrid(array $periode, string $shift, array $isian, ?string $hanyaKunci = null): array
    {
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            return ['ok' => false, 'pesan' => 'Shift tidak dikenal.'];
        }
        $pid     = (int) $periode['id'];
        if (($masalah = self::masalahNomor($pid)) !== []) {
            return ['ok' => false, 'pesan' => 'Angka pengawas belum beres — betulkan dulu di kartu nomor 1:', 'galat' => $masalah];
        }
        $nRuang  = UjianPeriodeModel::jumlahRuang($periode, $shift);
        $kodeKe  = self::petaKode($pid);
        $sesiAda = [];
        foreach (self::sesi($periode, $shift) as $h) {
            foreach ($h['baris'] as $b) {
                if ($b['jenis'] === 'sesi') {
                    $sesiAda[$b['kunci']] = ['tanggal' => $h['tanggal'], 'mulai' => $b['mulai'], 'selesai' => $b['selesai'], 'jamKe' => $b['jamKe']];
                }
            }
        }
        $label = static fn (array $s, int $ruang): string => JadwalUjianCetak::tanggalTeks($s['tanggal']) . ' jam ke-' . $s['jamKe'] . ' ruang ' . $ruang;
        if ($hanyaKunci !== null) {
            if (! isset($sesiAda[$hanyaKunci])) {
                return ['ok' => false, 'pesan' => 'Sesi ini sudah tidak ada di jadwal ujian (jamnya mungkin baru diubah). Muat ulang halaman, lalu coba lagi.'];
            }
            $isian = [$hanyaKunci => (array) ($isian[$hanyaKunci] ?? [])];
        }

        $galat = [];
        $baris = [];
        $dipakai = []; // kunci sesi → guru_id → ruang pertama
        foreach ($isian as $kunci => $perRuang) {
            if (! isset($sesiAda[$kunci]) || ! is_array($perRuang)) {
                continue; // sesi yang tak (lagi) ada di jadwal diabaikan
            }
            $s = $sesiAda[$kunci];
            foreach ($perRuang as $ruang => $kode) {
                $ruang = (int) $ruang;
                $kode  = self::kodeBersih(is_string($kode) ? $kode : '');
                if ($kode === '' || $ruang < 1 || $ruang > $nRuang) {
                    continue;
                }
                if (! isset($kodeKe[$kode])) {
                    $galat[] = 'Kode ' . $kode . ' (' . $label($s, $ruang) . ') tidak ada di daftar guru pengawas.';

                    continue;
                }
                $gid = $kodeKe[$kode]['id'];
                if (isset($dipakai[$kunci][$gid])) {
                    $galat[] = 'Kode ' . $kode . ' (' . $kodeKe[$kode]['nama'] . ') dipakai di dua ruang sekaligus: ruang ' . $dipakai[$kunci][$gid] . ' dan ' . $ruang . ' (' . JadwalUjianCetak::tanggalTeks($s['tanggal']) . ' jam ke-' . $s['jamKe'] . ').';

                    continue;
                }
                $dipakai[$kunci][$gid] = $ruang;
                $baris[] = ['periode_id' => $pid, 'shift' => $shift, 'tanggal' => $s['tanggal'], 'jam_mulai' => $s['mulai'], 'jam_selesai' => $s['selesai'], 'ruang' => $ruang, 'guru_id' => $gid];
            }
        }

        $kodeDari = [];
        foreach ($kodeKe as $k => $g) {
            $kodeDari[$g['id']] = $k;
        }
        // Sesi LAIN di shift ini yang jamnya beririsan (mis. 07.00–08.30 dan 07.00–09.10): orang yang sama hanya boleh di ruang yang sama.
        $perGuru = []; // guru_id|tanggal → daftar tugas (baru = dari isian ini)
        foreach ($baris as $b) {
            $perGuru[$b['guru_id'] . '|' . $b['tanggal']][] = $b + ['baru' => true];
        }
        if ($hanyaKunci !== null) {
            foreach (self::penugasan($pid, $shift) as $k => $perRuang) {
                if ($k === $hanyaKunci || ! isset($sesiAda[$k])) {
                    continue;
                }
                foreach ($perRuang as $ru => $gid) {
                    $perGuru[$gid . '|' . $sesiAda[$k]['tanggal']][] = ['jam_mulai' => $sesiAda[$k]['mulai'], 'jam_selesai' => $sesiAda[$k]['selesai'], 'tanggal' => $sesiAda[$k]['tanggal'],
                        'ruang' => $ru, 'guru_id' => $gid, 'baru' => false];
                }
            }
        }
        foreach ($perGuru as $tugas) {
            foreach ($tugas as $i => $a) {
                foreach (array_slice($tugas, $i + 1) as $c) {
                    if (($a['baru'] || $c['baru']) && $a['ruang'] !== $c['ruang'] && $a['jam_mulai'] !== null && $c['jam_mulai'] !== null
                        && [$a['jam_mulai'], $a['jam_selesai']] !== [$c['jam_mulai'], $c['jam_selesai']]
                        && UjianJadwalModel::jamBerimpit($a['jam_mulai'], $a['jam_selesai'], $c['jam_mulai'], $c['jam_selesai'])) {
                        $galat[] = 'Kode ' . ($kodeDari[$a['guru_id']] ?? '?') . ' dipakai di dua ruang pada jam yang beririsan: ruang ' . $a['ruang'] . ' (' . JadwalUjianCetak::waktu($a['jam_mulai'], $a['jam_selesai'])
                            . ') dan ruang ' . $c['ruang'] . ' (' . JadwalUjianCetak::waktu($c['jam_mulai'], $c['jam_selesai']) . '), ' . JadwalUjianCetak::tanggalTeks($a['tanggal']) . '.';
                    }
                }
            }
        }

        // Bentrok dengan tugas di shift LAIN pada jam yang beririsan.
        $lain = self::tabelSah($pid)->select('p.tanggal, p.jam_mulai, p.jam_selesai, p.ruang, p.guru_id, p.shift')
            ->where('p.shift !=', $shift)->get()->getResultArray();
        foreach ($baris as $b) {
            foreach ($lain as $l) {
                if ((int) $l['guru_id'] === $b['guru_id'] && $l['tanggal'] === $b['tanggal'] && UjianJadwalModel::jamBerimpit($b['jam_mulai'], $b['jam_selesai'], $l['jam_mulai'], $l['jam_selesai'])) {
                    $galat[] = 'Kode ' . ($kodeDari[$b['guru_id']] ?? '?') . ' bentrok dengan tugasnya di shift ' . $l['shift'] . ' ruang ' . (int) $l['ruang']
                        . ' (' . JadwalUjianCetak::tanggalTeks($b['tanggal']) . ', jam beririsan).';
                    break;
                }
            }
        }

        if ($galat !== []) {
            return ['ok' => false, 'pesan' => 'Jadwal pengawas ' . $shift . ' BELUM disimpan — betulkan dulu:', 'galat' => array_slice(array_values(array_unique($galat)), 0, 15)];
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $lama = $db->table('ujian_pengawas_ruang')->where('periode_id', $pid)->where('shift', $shift);
        if ($hanyaKunci !== null) {
            $s = $sesiAda[$hanyaKunci];
            $lama->where('tanggal', $s['tanggal'])->where('jam_mulai', $s['mulai'])->where('jam_selesai', $s['selesai']);
        }
        $lama->delete();
        if ($baris !== []) {
            $now = date('Y-m-d H:i:s');
            $db->table('ujian_pengawas_ruang')->insertBatch(array_map(static fn (array $b): array => $b + ['created_at' => $now, 'updated_at' => $now], $baris));
        }
        $db->transComplete();
        if ($hanyaKunci !== null) {
            $s = $sesiAda[$hanyaKunci];

            return ['ok' => true, 'pesan' => 'Pengawas ' . $shift . ' ' . self::labelSesi($s['tanggal'], $s['mulai'], $s['selesai'], $s['jamKe']) . ' disimpan (' . count($baris) . ' dari ' . $nRuang . ' ruang terisi).', 'terisi' => count($baris)];
        }

        return ['ok' => true, 'pesan' => 'Jadwal pengawas ' . $shift . ' disimpan (' . count($baris) . ' sel terisi).', 'terisi' => count($baris)];
    }

    /** "SENIN 30-Nov-26 jam ke-1 (07.00 - 08.30)". */
    public static function labelSesi(string $tanggal, ?string $mulai, ?string $selesai, ?int $jamKe): string
    {
        return JadwalUjianCetak::namaHari($tanggal) . ' ' . JadwalUjianCetak::tanggalTeks($tanggal) . ($jamKe !== null ? ' jam ke-' . $jamKe : '') . ' (' . JadwalUjianCetak::waktu($mulai, $selesai) . ')';
    }

    /**
     * Hapus penugasan pengawas (tombol Hapus, klien 2026-11-24). Jadwal ujian & daftar guru pengawas tidak disentuh. Selalu boleh,
     * juga saat angka pengawas belum beres. Hapus hari/shift ikut membersihkan sisa sesi yang jamnya sudah tak ada di jadwal.
     *
     * @param string $cakupan "sesi|TTTT-BB-HH|mulai|selesai" (kunci sesi), "hari|TTTT-BB-HH", atau "shift"
     *
     * @return array{ok: bool, pesan: string, hapus: int}
     */
    public static function kosongkan(array $periode, string $shift, string $cakupan): array
    {
        $tolak = static fn (string $p): array => ['ok' => false, 'pesan' => $p, 'hapus' => 0];
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            return $tolak('Shift tidak dikenal.');
        }
        $bagian  = explode('|', $cakupan);
        $tanggal = $bagian[1] ?? '';
        $jumlah  = ['sesi' => 4, 'hari' => 2, 'shift' => 1][$bagian[0]] ?? 0;
        if ($jumlah !== count($bagian)) {
            return $tolak('Pilihan hapus tidak dikenal. Muat ulang halaman, lalu coba lagi.');
        }
        $tgl = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        if ($bagian[0] !== 'shift' && (! $tgl || $tgl->format('Y-m-d') !== $tanggal)) {
            return $tolak('Tanggal tidak dikenal. Muat ulang halaman, lalu coba lagi.');
        }
        $db = db_connect();
        $b  = $db->table('ujian_pengawas_ruang')->where('periode_id', (int) $periode['id'])->where('shift', $shift);
        if ($bagian[0] === 'sesi') {
            foreach ([2, 3] as $i) {
                if ($bagian[$i] !== '' && preg_match('/^\d{2}:\d{2}:\d{2}$/', $bagian[$i]) !== 1) {
                    return $tolak('Jam sesi tidak dikenal. Muat ulang halaman, lalu coba lagi.');
                }
            }
            $b->where('tanggal', $tanggal)->where('jam_mulai', $bagian[2] !== '' ? $bagian[2] : null)->where('jam_selesai', $bagian[3] !== '' ? $bagian[3] : null);
            $apa = self::labelSesi($tanggal, $bagian[2], $bagian[3], null);
        } elseif ($bagian[0] === 'hari') {
            $b->where('tanggal', $tanggal);
            $apa = JadwalUjianCetak::namaHari($tanggal) . ' ' . JadwalUjianCetak::tanggalTeks($tanggal);
        } else {
            $apa = 'semua hari';
        }
        $b->delete();
        $n = (int) $db->affectedRows();

        return ['ok' => true, 'hapus' => $n, 'pesan' => $n > 0
            ? 'Pengawas ' . $shift . ' ' . $apa . ' dihapus (' . $n . ' sel dikosongkan). Jadwal ujian tidak berubah.'
            : 'Pengawas ' . $shift . ' ' . $apa . ' memang sudah kosong.'];
    }

    /**
     * Data tab "Jadwal Pengawas".
     *
     * @return array<string, mixed>
     */
    public static function dataTab(array $periode, string $shiftDiminta): array
    {
        $pid     = (int) $periode['id'];
        $shifts  = JadwalUjianCetak::shiftAda();
        $shift   = in_array($shiftDiminta, $shifts, true) ? $shiftDiminta : ($shifts[0] ?? 'pagi');
        $terpilih = self::guruTerpilih($pid);
        $semua    = self::semuaGuru();
        $idKeKode = [];
        foreach ($semua as $g) {
            $idKeKode[$g['id']] = $g['nomor']; // grid menampilkan ANGKA pengawas (2026-11-23)
        }
        $sesi   = self::sesi($periode, $shift);
        $nSesi  = 0;
        $tampil = []; // kunci sesi yang tampil di grid
        foreach ($sesi as $h) {
            foreach ($h['baris'] as $b) {
                if ($b['jenis'] === 'sesi') {
                    $nSesi++;
                    $tampil[$b['kunci']] = true;
                }
            }
        }
        $nRuang = UjianPeriodeModel::jumlahRuang($periode, $shift);
        // Angka "Terisi" = persis kotak yang terlihat berisi (2026-11-24): sesi & ruang yang tampil, guru yang masih ada.
        $isi = [];
        foreach (self::penugasan($pid, $shift) as $kunci => $perRuang) {
            foreach ($perRuang as $ruang => $gid) {
                if (isset($tampil[$kunci]) && $ruang >= 1 && $ruang <= $nRuang && ($idKeKode[$gid] ?? '') !== '') {
                    $isi[$kunci][$ruang] = $idKeKode[$gid];
                }
            }
        }

        return [
            'pgShifts'   => $shifts,
            'pgShift'    => $shift,
            'pgSesi'     => $sesi,
            'pgRuang'    => $nRuang,
            'pgIsi'      => $isi,
            'pgTerisi'   => array_sum(array_map('count', $isi)),
            'pgTotalSel' => $nSesi * $nRuang,
            'pgGuru'     => $semua,
            'pgTerpilih' => $terpilih,
            'pgSaran'    => $terpilih === [] ? self::saran($periode) : null,
            'pgKode'     => self::petaKode($pid),
            'pgBeban'    => self::beban($pid), // F4: jumlah sesi mengawasi per guru (semua shift) — cek kemerataan
            'pgMasalah'  => self::masalahNomor($pid), // angka pengawas terpilih yang kosong/kembar (2026-11-23)
            'pgKelasShift' => UjianPeriodeModel::jumlahKelasShift($shift), // bawaan jumlah ruang bila Periode kosong
        ];
    }

    // =================================================================
    //  GENERATE otomatis (F4, 2026-11-22) — meniru pola berkas contoh sekolah
    // =================================================================

    /**
     * Isi otomatis jadwal pengawas untuk shift-shift yang diminta (isi lama shift itu DIGANTI; shift lain dibiarkan & ikut
     * diperhitungkan). Aturan:
     *  1. tiap ruang tiap sesi diisi satu guru dari daftar pengawas; guru tak pernah di dua tempat pada jam beririsan (lintas shift);
     *  2. sesi beruntun tanpa istirahat = satu BLOK → pengawas ruang itu sama (di cetakan selnya digabung, seperti contoh);
     *  3. sesudah istirahat pengawas BERPINDAH ruang (blok ke-2: pasangan ruang bertukar 1↔2, 3↔4, … sisa ganjil diputar tiga —
     *     pola lembar pagi contoh; blok ke-3 dst.: digeser), sehingga satu kelas tidak diawasi orang yang sama dua kali sehari;
     *  4. MERATA: tiap hari dipilih guru yang beban (jumlah sesi) paling sedikit; urutan seri diacak supaya adil dan "Generate
     *     ulang" menghasilkan susunan baru.
     * Guru kurang dari jumlah ruang → ruang sisanya dibiarkan kosong & dilaporkan.
     *
     * @param list<string> $shifts
     *
     * @return array{ok: bool, pesan: string, terisi?: array<string, int>, kosong?: int, beban?: array{min: int, max: int}}
     */
    public static function generate(array $periode, array $shifts): array
    {
        $pid    = (int) $periode['id'];
        $shifts = array_values(array_intersect(JadwalUjianCetak::shiftAda(), $shifts));
        if ($shifts === []) {
            return ['ok' => false, 'pesan' => 'Tidak ada shift yang bisa di-generate (belum ada kelas/jadwal).'];
        }
        if (($masalah = self::masalahNomor($pid)) !== []) {
            return ['ok' => false, 'pesan' => 'Angka pengawas belum beres — ' . implode(' ', $masalah) . ' Betulkan di kartu nomor 1, lalu Generate lagi.'];
        }
        $pool = array_values(array_map(static fn (array $g): int => $g['id'], self::petaKode($pid)));
        if ($pool === []) {
            return ['ok' => false, 'pesan' => 'Pilih dan simpan daftar guru pengawas dulu (kartu nomor 1), baru Generate.'];
        }

        // Tugas yang sudah ada di shift yang TIDAK di-generate: ikut dihitung beban & dicek bentroknya.
        $db       = db_connect();
        $beban    = array_fill_keys($pool, 0);
        $tugas    = []; // guru_id → list [tanggal, mulai, selesai]
        $tetap    = self::tabelSah($pid)->select('p.tanggal, p.jam_mulai, p.jam_selesai, p.guru_id');
        $tetap->whereNotIn('p.shift', $shifts);
        foreach ($tetap->get()->getResultArray() as $t) {
            $g = (int) $t['guru_id'];
            $beban[$g] = ($beban[$g] ?? 0) + 1;
            $tugas[$g][] = [$t['tanggal'], $t['jam_mulai'], $t['jam_selesai']];
        }

        // Satuan kerja = (shift, hari). Dibagi mulai dari yang sesinya TERBANYAK (pola "tugas besar dulu"): hari 3 sesi dibagikan
        // lebih dulu, hari 2 sesi menutup selisihnya → beban akhir lebih rata (uji F6: selisih turun dari 4 ke ≤ 3).
        $satuan = [];
        foreach ($shifts as $shift) {
            $nRuang = UjianPeriodeModel::jumlahRuang($periode, $shift);
            foreach (self::sesi($periode, $shift) as $h) {
                $blok = self::blokHari($h['baris']);
                if ($blok !== [] && $nRuang >= 1) {
                    $satuan[] = ['shift' => $shift, 'nRuang' => $nRuang, 'h' => $h, 'blok' => $blok,
                        'jml' => array_sum(array_map(static fn (array $b): int => count($b['sesi']), $blok))];
                }
            }
        }
        usort($satuan, static fn (array $a, array $b): int => $b['jml'] <=> $a['jml']); // stabil: urutan tanggal dipertahankan bila seri

        $baris   = [];
        $terisi  = array_fill_keys($shifts, 0);
        $kosong  = 0;
        foreach ($satuan as ['shift' => $shift, 'nRuang' => $nRuang, 'h' => $h, 'blok' => $blok, 'jml' => $jml]) {
            // Calon: guru pengawas yang bebas di SEMUA blok hari itu (tidak beririsan dengan tugas lain).
            $calon = [];
            foreach ($pool as $g) {
                $bebas = true;
                foreach ($tugas[$g] ?? [] as [$tgl, $m, $s]) {
                    if ($tgl !== $h['tanggal']) {
                        continue;
                    }
                    foreach ($blok as $b) {
                        if (UjianJadwalModel::jamBerimpit($b['mulai'], $b['selesai'], $m, $s)) {
                            $bebas = false;
                            break 2;
                        }
                    }
                }
                if ($bebas) {
                    $calon[] = $g;
                }
            }
            shuffle($calon);
            usort($calon, static fn (int $a, int $b): int => ($beban[$a] ?? 0) <=> ($beban[$b] ?? 0)); // stabil (PHP 8): seri tetap acak
            $hari = array_slice($calon, 0, $nRuang);
            $kosong += ($nRuang - count($hari)) * $jml;

            foreach ($blok as $ke => $b) {
                $susunan = self::susunBlok($hari, $ke);
                foreach ($b['sesi'] as $s) {
                    foreach ($susunan as $i => $g) {
                        $baris[] = ['periode_id' => $pid, 'shift' => $shift, 'tanggal' => $h['tanggal'], 'jam_mulai' => $s['mulai'], 'jam_selesai' => $s['selesai'], 'ruang' => $i + 1, 'guru_id' => $g];
                        $beban[$g] = ($beban[$g] ?? 0) + 1;
                        $tugas[$g][] = [$h['tanggal'], $s['mulai'], $s['selesai']];
                        $terisi[$shift]++;
                    }
                }
            }
        }

        $db->transException(true);
        $db->transStart();
        $db->table('ujian_pengawas_ruang')->where('periode_id', $pid)->whereIn('shift', $shifts)->delete();
        if ($baris !== []) {
            $now = date('Y-m-d H:i:s');
            foreach (array_chunk($baris, 500) as $potong) {
                $db->table('ujian_pengawas_ruang')->insertBatch(array_map(static fn (array $b): array => $b + ['created_at' => $now, 'updated_at' => $now], $potong));
            }
        }
        $db->transComplete();

        $angka = array_intersect_key($beban, array_flip($pool));
        $min   = $angka !== [] ? min($angka) : 0;
        $max   = $angka !== [] ? max($angka) : 0;
        $bagian = [];
        foreach ($terisi as $s => $n) {
            $bagian[] = ucfirst($s) . ' ' . $n . ' sel';
        }
        $pesan = 'Generate selesai: ' . implode(', ', $bagian) . '. Beban tiap guru ' . ($min === $max ? $min : $min . '–' . $max) . ' sesi (' . count($pool) . ' guru).';
        if ($kosong > 0) {
            $pesan .= ' PERHATIAN: ' . $kosong . ' sel tetap kosong karena guru pengawas kurang dari jumlah ruang — tambah guru di daftar lalu Generate ulang, atau isi manual.';
        }

        return ['ok' => true, 'pesan' => $pesan . ' Silakan periksa/ubah manual bila perlu.', 'terisi' => $terisi, 'kosong' => $kosong, 'beban' => ['min' => $min, 'max' => $max]];
    }

    /**
     * Kelompokkan sesi satu hari menjadi BLOK: sesi beruntun yang tidak dipisah istirahat.
     *
     * @return list<array{mulai: ?string, selesai: ?string, sesi: list<array{mulai: ?string, selesai: ?string}>}>
     */
    private static function blokHari(array $barisHari): array
    {
        $blok = [];
        $kini = null;
        foreach ($barisHari as $b) {
            if ($b['jenis'] !== 'sesi') {
                if ($kini !== null) {
                    $blok[] = $kini;
                    $kini = null;
                }
                continue;
            }
            if ($kini === null) {
                $kini = ['mulai' => $b['mulai'], 'selesai' => $b['selesai'], 'sesi' => []];
            }
            $kini['sesi'][] = ['mulai' => $b['mulai'], 'selesai' => $b['selesai']];
            if ($b['selesai'] !== null && ($kini['selesai'] === null || $b['selesai'] > $kini['selesai'])) {
                $kini['selesai'] = $b['selesai'];
            }
        }
        if ($kini !== null) {
            $blok[] = $kini;
        }

        return $blok;
    }

    /**
     * Susunan ruang untuk blok ke-$ke dari susunan awal $hari (indeks = ruang−1). Blok 0 = apa adanya; blok 1 = pasangan ruang
     * bertukar (1↔2, 3↔4, …; bila ganjil tiga ruang terakhir diputar); blok 2+ = digeser $ke ruang. Tak ada guru yang kembali ke
     * ruang yang sama di hari itu selama ruang ≥ 4.
     *
     * @param list<int> $hari
     *
     * @return list<int>
     */
    private static function susunBlok(array $hari, int $ke): array
    {
        $k = count($hari);
        if ($ke === 0 || $k < 2) {
            return $hari;
        }
        if ($ke === 1) {
            $out   = $hari;
            $batas = $k % 2 === 1 && $k >= 3 ? $k - 3 : $k;
            for ($i = 0; $i + 1 < $batas; $i += 2) {
                [$out[$i], $out[$i + 1]] = [$hari[$i + 1], $hari[$i]];
            }
            if ($batas !== $k) {
                [$out[$k - 3], $out[$k - 2], $out[$k - 1]] = [$hari[$k - 1], $hari[$k - 3], $hari[$k - 2]];
            }

            return $out;
        }
        $out = [];
        for ($i = 0; $i < $k; $i++) {
            $out[$i] = $hari[($i + $ke) % $k];
        }

        return $out;
    }

    /** Beban mengawasi per guru di periode ini: [guru_id => jumlah sesi]. @return array<int, int> */
    public static function beban(int $periodeId): array
    {
        $out = [];
        foreach (self::tabelSah($periodeId)->select('p.guru_id, COUNT(*) AS n')->groupBy('p.guru_id')->get()->getResultArray() as $r) {
            $out[(int) $r['guru_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** Jumlah sel jadwal pengawas terisi (untuk lencana tab). */
    public static function jumlahTerisi(int $periodeId): int
    {
        return (int) self::tabelSah($periodeId)->countAllResults();
    }

    /**
     * Penugasan periode ini yang sesinya (shift + tanggal + jam) MASIH ADA di jadwal ujian. Sisa sesi yang jamnya sudah diubah/dihapus
     * tidak boleh ikut terhitung terisi, beban, atau bentrok (2026-11-24). Alias tabel: p.
     */
    private static function tabelSah(int $periodeId): \CodeIgniter\Database\BaseBuilder
    {
        $db = db_connect();
        $j  = $db->prefixTable('ujian_jadwal');

        return $db->table('ujian_pengawas_ruang p')->where('p.periode_id', $periodeId)
            ->where("EXISTS (SELECT 1 FROM {$j} j WHERE j.periode_id = p.periode_id AND j.deleted_at IS NULL AND j.tanggal = p.tanggal"
                . " AND (j.shift = p.shift OR j.shift = 'semua') AND j.jam_mulai <=> p.jam_mulai AND j.jam_selesai <=> p.jam_selesai)", null, false);
    }

    // =================================================================
    //  Pengawas IKUT jadwal ujian (tahap 3, 2026-11-24)
    // =================================================================

    /**
     * Sesi menurut jadwal ujian apa adanya (tanpa melihat kecocokan kelas): [shift][kunci sesi] => daftar id jadwal. Diambil SEBELUM
     * jadwal diubah/diimpor/dihapus lalu diberikan ke selaraskan() supaya diketahui jadwal sesi lama pindah ke mana.
     *
     * @return array<string, array<string, list<int>>>
     */
    public static function potretSesi(int $periodeId): array
    {
        $out = ['pagi' => [], 'siang' => []];
        foreach (db_connect()->table('ujian_jadwal')->select('id, shift, tanggal, jam_mulai, jam_selesai')->where('periode_id', $periodeId)
            ->where('deleted_at', null)->where('tanggal IS NOT NULL', null, false)->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            foreach ($r['shift'] === 'semua' ? ['pagi', 'siang'] : [(string) $r['shift']] as $s) {
                if (isset($out[$s])) {
                    $out[$s][self::kunci((string) $r['tanggal'], $r['jam_mulai'], $r['jam_selesai'])][] = (int) $r['id'];
                }
            }
        }

        return $out;
    }

    /**
     * Selaraskan penugasan pengawas dengan jadwal ujian — dipanggil sesudah jadwal ditambah/diubah/dihapus/diimpor (web & API).
     * Penugasan melekat pada JAM (shift + tanggal + jam), bukan mapel, jadi sesi yang jamnya masih dipakai jadwal lain tidak disentuh.
     * Sesi yang jamnya sudah TIDAK ADA lagi di jadwal:
     *   - pengawasnya PINDAH ke sesi pengganti yang masih kosong di shift yang sama: sesi tujuan jadwalnya (menurut $sebelum), atau sesi
     *     di hari yang sama yang jamnya beririsan;
     *   - guru yang jadi bentrok di jam baru dikosongkan (tidak pernah dipaksakan);
     *   - tak ada sesi pengganti → penugasannya dihapus.
     *
     * @param array<string, array<string, list<int>>>|null $sebelum potretSesi() sebelum perubahan
     *
     * @return array{pindah: int, bentrok: int, hapus: int, rinci: list<string>}
     */
    public static function selaraskan(int $periodeId, ?array $sebelum = null): array
    {
        $hasil = ['pindah' => 0, 'bentrok' => 0, 'hapus' => 0, 'rinci' => []];
        $db    = db_connect();
        $grup  = ['pagi' => [], 'siang' => []]; // shift → kunci → baris penugasan
        foreach ($db->table('ujian_pengawas_ruang')->select('id, shift, tanggal, jam_mulai, jam_selesai, ruang, guru_id')->where('periode_id', $periodeId)
            ->orderBy('tanggal', 'ASC')->orderBy('jam_mulai', 'ASC')->orderBy('ruang', 'ASC')->get()->getResultArray() as $r) {
            $grup[$r['shift']][self::kunci((string) $r['tanggal'], $r['jam_mulai'], $r['jam_selesai'])][] = $r;
        }
        $kini  = self::potretSesi($periodeId);
        $lepas = []; // [shift, kunci lama, kunci tujuan|null]
        $tugas = []; // guru_id → tugas yang tetap berlaku (untuk cek bentrok)
        foreach ($grup as $shift => $perKunci) {
            $hilang = [];
            foreach ($perKunci as $kunci => $baris) {
                if (! isset($kini[$shift][$kunci])) {
                    $hilang[] = (string) $kunci;

                    continue;
                }
                foreach ($baris as $b) {
                    $tugas[(int) $b['guru_id']][] = [$shift, (string) $b['tanggal'], $b['jam_mulai'], $b['jam_selesai'], (int) $b['ruang']];
                }
            }
            if ($hilang === []) {
                continue;
            }
            // Sesi tujuan yang mungkin: ada di jadwal dan belum punya pengawas.
            $kosong = array_diff_key($kini[$shift], $perKunci);
            $posisi = []; // id jadwal → kunci sekarang
            foreach ($kini[$shift] as $k => $ids) {
                foreach ($ids as $id) {
                    $posisi[$id] = (string) $k;
                }
            }
            foreach ($hilang as $kunci) {
                $tuju = self::sesiPengganti($kunci, $kosong, $posisi, $sebelum[$shift][$kunci] ?? []);
                if ($tuju !== null) {
                    unset($kosong[$tuju]);
                }
                $lepas[] = [$shift, $kunci, $tuju];
            }
        }
        if ($lepas === []) {
            return $hasil;
        }

        $buang = [];
        $baru  = [];
        $now   = date('Y-m-d H:i:s');
        foreach ($lepas as [$shift, $kunci, $tuju]) {
            $pindah = 0;
            foreach ($grup[$shift][$kunci] as $b) {
                $buang[] = (int) $b['id'];
                if ($tuju === null) {
                    $hasil['hapus']++;

                    continue;
                }
                [$tgl, $m, $s] = explode('|', $tuju);
                $m   = $m !== '' ? $m : null;
                $s   = $s !== '' ? $s : null;
                $gid = (int) $b['guru_id'];
                if (self::bentrokTugas($tugas[$gid] ?? [], $shift, $tgl, $m, $s, (int) $b['ruang'])) {
                    $hasil['bentrok']++;

                    continue;
                }
                $tugas[$gid][] = [$shift, $tgl, $m, $s, (int) $b['ruang']];
                $baru[]        = ['periode_id' => $periodeId, 'shift' => $shift, 'tanggal' => $tgl, 'jam_mulai' => $m, 'jam_selesai' => $s, 'ruang' => (int) $b['ruang'], 'guru_id' => $gid, 'created_at' => $now, 'updated_at' => $now];
                $pindah++;
            }
            if ($pindah > 0) {
                [$t0, $m0, $s0] = explode('|', $kunci);
                [$t1, $m1, $s1] = explode('|', $tuju);
                $hasil['pindah'] += $pindah;
                $hasil['rinci'][] = $shift . ' ' . self::labelSesi($t0, $m0, $s0, null) . ' → ' . ($t1 !== $t0 ? JadwalUjianCetak::namaHari($t1) . ' ' . JadwalUjianCetak::tanggalTeks($t1) . ' ' : '')
                    . JadwalUjianCetak::waktu($m1, $s1) . ' (' . $pindah . ' ruang)';
            }
        }

        $db->transException(true);
        $db->transStart();
        foreach (array_chunk($buang, 500) as $potong) {
            $db->table('ujian_pengawas_ruang')->whereIn('id', $potong)->delete();
        }
        foreach (array_chunk($baru, 500) as $potong) {
            $db->table('ujian_pengawas_ruang')->insertBatch($potong);
        }
        $db->transComplete();

        return $hasil;
    }

    /** Kalimat hasil selaraskan() untuk pesan sesudah aksi; '' bila tak ada yang berubah. */
    public static function kalimatSelaras(array $r): string
    {
        $k = '';
        if ($r['pindah'] > 0) {
            $tampil = array_slice($r['rinci'], 0, 3);
            $k .= ' Pengawas ikut pindah: ' . implode('; ', $tampil) . (count($r['rinci']) > 3 ? '; dan ' . (count($r['rinci']) - 3) . ' sesi lain' : '') . '.';
        }
        if ($r['bentrok'] > 0) {
            $k .= ' ' . $r['bentrok'] . ' ruang dikosongkan karena gurunya bentrok di jam baru — isi lagi di Jadwal Pengawas.';
        }
        if ($r['hapus'] > 0) {
            $k .= ' ' . $r['hapus'] . ' isian pengawas dihapus karena jam ujiannya sudah tidak ada.';
        }

        return $k;
    }

    /**
     * Catatan bila jam satu jadwal diubah tetapi jadwal lain masih memakai jam lama: pengawas belum ikut pindah. '' bila tak perlu.
     *
     * @param array<string, mixed> $lama baris jadwal sebelum diubah
     * @param array<string, mixed> $baru isian baru
     */
    public static function catatanSisa(int $periodeId, array $lama, array $baru): string
    {
        $k0 = self::kunci((string) $lama['tanggal'], $lama['jam_mulai'], $lama['jam_selesai']);
        $k1 = self::kunci((string) $baru['tanggal'], $baru['jam_mulai'], $baru['jam_selesai']);
        if ($lama['shift'] !== $baru['shift'] || $k0 === $k1 || ! in_array($lama['shift'], ['pagi', 'siang'], true)) {
            return '';
        }
        $sisa = count(self::potretSesi($periodeId)[$lama['shift']][$k0] ?? []);
        if ($sisa === 0 || ! isset(self::penugasan($periodeId, (string) $lama['shift'])[$k0])) {
            return '';
        }

        return ' Masih ada ' . $sisa . ' jadwal di jam lama (' . JadwalUjianCetak::waktu($lama['jam_mulai'], $lama['jam_selesai']) . '); pengawasnya ikut pindah setelah semuanya diubah.';
    }

    /**
     * Sesi pengganti untuk sesi yang hilang: (1) sesi tujuan jadwalnya bila di hari yang sama atau dituju ≥ 2 jadwal, (2) sesi kosong
     * di hari yang sama yang jamnya paling beririsan, (3) sesi tujuan jadwalnya di hari lain, (4) jadwalnya pindah tetapi sesi tujuannya
     * sudah berpengawas (mis. semua jam digeser satu sesi) → sesi kosong terdekat di hari tujuan itu. null = tak ada.
     *
     * @param array<string, list<int>> $kosong sesi tanpa pengawas
     * @param array<int, string>       $posisi id jadwal → kunci sesi sekarang
     * @param list<int>                $ids    id jadwal yang dulu ada di sesi ini
     */
    private static function sesiPengganti(string $kunci, array $kosong, array $posisi, array $ids): ?string
    {
        [$tgl, $mulai, $selesai] = explode('|', $kunci);
        $hitung = [];
        foreach ($ids as $id) {
            if (isset($posisi[$id])) {
                $hitung[$posisi[$id]] = ($hitung[$posisi[$id]] ?? 0) + 1;
            }
        }
        arsort($hitung);
        $tujuan = $hitung !== [] ? (string) array_key_first($hitung) : null;
        $jarak  = static fn (string $k): int => abs(strtotime('1970-01-01 ' . (explode('|', $k)[1] ?: '00:00:00')) - strtotime('1970-01-01 ' . ($mulai ?: '00:00:00')))
            + abs(strtotime('1970-01-01 ' . (explode('|', $k)[2] ?: '00:00:00')) - strtotime('1970-01-01 ' . ($selesai ?: '00:00:00')));
        $terdekat = static function (array $calon) use ($jarak): ?string {
            usort($calon, static fn (string $a, string $b): int => [$jarak($a), $a] <=> [$jarak($b), $b]);

            return $calon[0] ?? null;
        };
        $calonHari = static fn (string $t): array => array_values(array_filter(array_map('strval', array_keys($kosong)), static fn (string $k): bool => str_starts_with($k, $t . '|')));

        if ($tujuan !== null && isset($kosong[$tujuan]) && (str_starts_with($tujuan, $tgl . '|') || $hitung[$tujuan] >= 2)) {
            return $tujuan;
        }
        $beririsan = $mulai === '' ? null : $terdekat(array_values(array_filter($calonHari($tgl), static function (string $k) use ($mulai, $selesai): bool {
            [, $m, $s] = explode('|', $k);

            return $m !== '' && UjianJadwalModel::jamBerimpit($mulai, $selesai, $m, $s);
        })));
        if ($beririsan !== null) {
            return $beririsan;
        }
        if ($tujuan === null) {
            return null;
        }

        return isset($kosong[$tujuan]) ? $tujuan : $terdekat($calonHari(explode('|', $tujuan)[0]));
    }

    /**
     * Tugas baru ini bentrok dengan tugas guru yang sudah ada? (aturan sama dengan simpanGrid: beririsan di shift lain, atau di shift
     * yang sama tetapi ruangnya beda)
     *
     * @param list<array{0: string, 1: string, 2: ?string, 3: ?string, 4: int}> $ada
     */
    private static function bentrokTugas(array $ada, string $shift, string $tanggal, ?string $mulai, ?string $selesai, int $ruang): bool
    {
        foreach ($ada as [$s2, $t2, $m2, $e2, $r2]) {
            if ($t2 !== $tanggal) {
                continue;
            }
            if ($s2 === $shift && ($r2 === $ruang || $mulai === null || $m2 === null)) {
                continue;
            }
            if (UjianJadwalModel::jamBerimpit($mulai, $selesai, $m2, $e2)) {
                return true;
            }
        }

        return false;
    }
}
