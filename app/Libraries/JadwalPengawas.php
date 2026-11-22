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
     * Semua guru aktif untuk daftar centang, diurutkan kode (angka alami; tanpa kode di belakang).
     *
     * @return list<array{id: int, nama: string, kode: string, ganda: bool}>
     */
    public static function semuaGuru(): array
    {
        $rows = db_connect()->table('guru')->select('id, nama, kode_guru')->where('deleted_at', null)->get()->getResultArray();
        $out  = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int) $r['id'], 'nama' => (string) $r['nama'], 'kode' => self::kodeBersih($r['kode_guru'])];
        }
        $hitung = array_count_values(array_filter(array_column($out, 'kode'), static fn (string $k): bool => $k !== ''));
        foreach ($out as &$g) {
            $g['ganda'] = $g['kode'] !== '' && ($hitung[$g['kode']] ?? 0) > 1;
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
     * Pengawas terpilih yang punya kode → [KODE => ['id' => .., 'nama' => ..]].
     *
     * @return array<string, array{id: int, nama: string}>
     */
    public static function petaKode(int $periodeId): array
    {
        $pilih = array_flip(self::guruTerpilih($periodeId));
        $peta  = [];
        foreach (self::semuaGuru() as $g) {
            if (isset($pilih[$g['id']]) && $g['kode'] !== '' && ! isset($peta[$g['kode']])) {
                $peta[$g['kode']] = ['id' => $g['id'], 'nama' => $g['nama']];
            }
        }

        return $peta;
    }

    /**
     * Simpan daftar guru pengawas. Hanya guru aktif berkode; kode ganda di antara yang dicentang ditolak (kode harus menunjuk satu
     * orang). Penugasan milik guru yang dikeluarkan dari daftar ikut dikosongkan.
     *
     * @param list<int|string> $guruIds
     *
     * @return array{ok: bool, pesan: string, jumlah?: int, dikosongkan?: int}
     */
    public static function simpanGuru(array $periode, array $guruIds): array
    {
        $ids   = array_flip(array_map('intval', $guruIds));
        $pilih = array_values(array_filter(self::semuaGuru(), static fn (array $g): bool => isset($ids[$g['id']]) && $g['kode'] !== ''));

        $perKode = [];
        foreach ($pilih as $g) {
            $perKode[$g['kode']][] = $g['nama'];
        }
        $ganda = array_filter($perKode, static fn (array $n): bool => count($n) > 1);
        if ($ganda !== []) {
            $teks = [];
            foreach ($ganda as $k => $nama) {
                $teks[] = 'kode ' . $k . ' (' . implode(', ', $nama) . ')';
            }

            return ['ok' => false, 'pesan' => 'Satu kode hanya boleh untuk satu pengawas: ' . implode('; ', $teks) . '. Bedakan kodenya di Master Guru atau centang salah satu saja.'];
        }

        // Orang yang sama tercatat dua kali di Master Guru (data hosting: Irvan Hidayat 23 & 23A, Debi Saputra 60 & 41) → bila
        // keduanya dicentang, Generate bisa memberi satu orang dua ruang pada jam yang sama. Nama dibandingkan tanpa gelar.
        $perNama = [];
        foreach ($pilih as $g) {
            $kunci = preg_replace('/[^a-z]/', '', mb_strtolower(explode(',', $g['nama'])[0]));
            $perNama[$kunci][] = $g['nama'] . ' (kode ' . $g['kode'] . ')';
        }
        $kembar = array_filter($perNama, static fn (array $n): bool => count($n) > 1);
        if ($kembar !== []) {
            return ['ok' => false, 'pesan' => 'Guru yang sama tercentang dua kali: ' . implode('; ', array_map(static fn (array $n): string => implode(' & ', $n), $kembar))
                . '. Centang salah satu kodenya saja (data gandanya bisa dirapikan di Master Guru).'];
        }

        $pid     = (int) $periode['id'];
        $baru    = array_column($pilih, 'id');
        $db      = db_connect();
        $db->transException(true);
        $db->transStart();
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

        return ['ok' => true, 'pesan' => count($baru) . ' guru pengawas disimpan.' . ($dikosongkan > 0 ? ' ' . $dikosongkan . ' sel jadwal pengawas milik guru yang dikeluarkan dari daftar ikut dikosongkan.' : ''),
            'jumlah' => count($baru), 'dikosongkan' => $dikosongkan];
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
     *
     * @return array{ok: bool, pesan: string, galat?: list<string>, terisi?: int}
     */
    public static function simpanGrid(array $periode, string $shift, array $isian): array
    {
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            return ['ok' => false, 'pesan' => 'Shift tidak dikenal.'];
        }
        $pid     = (int) $periode['id'];
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

        // Bentrok dengan tugas di shift LAIN pada jam yang beririsan.
        $kodeDari = [];
        foreach ($kodeKe as $k => $g) {
            $kodeDari[$g['id']] = $k;
        }
        $lain = db_connect()->table('ujian_pengawas_ruang')->select('tanggal, jam_mulai, jam_selesai, ruang, guru_id, shift')
            ->where('periode_id', $pid)->where('shift !=', $shift)->get()->getResultArray();
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
        $db->table('ujian_pengawas_ruang')->where('periode_id', $pid)->where('shift', $shift)->delete();
        if ($baris !== []) {
            $now = date('Y-m-d H:i:s');
            $db->table('ujian_pengawas_ruang')->insertBatch(array_map(static fn (array $b): array => $b + ['created_at' => $now, 'updated_at' => $now], $baris));
        }
        $db->transComplete();

        return ['ok' => true, 'pesan' => 'Jadwal pengawas ' . $shift . ' disimpan (' . count($baris) . ' sel terisi).', 'terisi' => count($baris)];
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
        $idKeKode = [];
        foreach (self::semuaGuru() as $g) {
            $idKeKode[$g['id']] = $g['kode'];
        }
        $isi = [];
        foreach (self::penugasan($pid, $shift) as $kunci => $perRuang) {
            foreach ($perRuang as $ruang => $gid) {
                $isi[$kunci][$ruang] = $idKeKode[$gid] ?? '';
            }
        }
        $sesi  = self::sesi($periode, $shift);
        $nSesi = 0;
        foreach ($sesi as $h) {
            foreach ($h['baris'] as $b) {
                $nSesi += $b['jenis'] === 'sesi' ? 1 : 0;
            }
        }
        $nRuang = UjianPeriodeModel::jumlahRuang($periode, $shift);

        return [
            'pgShifts'   => $shifts,
            'pgShift'    => $shift,
            'pgSesi'     => $sesi,
            'pgRuang'    => $nRuang,
            'pgIsi'      => $isi,
            'pgTerisi'   => array_sum(array_map('count', $isi)),
            'pgTotalSel' => $nSesi * $nRuang,
            'pgGuru'     => self::semuaGuru(),
            'pgTerpilih' => $terpilih,
            'pgSaran'    => $terpilih === [] ? self::saran($periode) : null,
            'pgKode'     => self::petaKode($pid),
            'pgBeban'    => self::beban($pid), // F4: jumlah sesi mengawasi per guru (semua shift) — cek kemerataan
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
        $pool = array_values(array_map(static fn (array $g): int => $g['id'], self::petaKode($pid)));
        if ($pool === []) {
            return ['ok' => false, 'pesan' => 'Pilih dan simpan daftar guru pengawas dulu (kartu nomor 1), baru Generate.'];
        }

        // Tugas yang sudah ada di shift yang TIDAK di-generate: ikut dihitung beban & dicek bentroknya.
        $db       = db_connect();
        $beban    = array_fill_keys($pool, 0);
        $tugas    = []; // guru_id → list [tanggal, mulai, selesai]
        $tetap    = $db->table('ujian_pengawas_ruang')->select('tanggal, jam_mulai, jam_selesai, guru_id')->where('periode_id', $pid);
        $tetap->whereNotIn('shift', $shifts);
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
        foreach (db_connect()->table('ujian_pengawas_ruang')->select('guru_id, COUNT(*) AS n')->where('periode_id', $periodeId)->groupBy('guru_id')->get()->getResultArray() as $r) {
            $out[(int) $r['guru_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** Jumlah sel jadwal pengawas terisi (untuk lencana tab). */
    public static function jumlahTerisi(int $periodeId): int
    {
        return (int) db_connect()->table('ujian_pengawas_ruang')->where('periode_id', $periodeId)->countAllResults();
    }
}
