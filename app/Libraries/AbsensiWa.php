<?php

namespace App\Libraries;

use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\GuruModel;
use App\Models\SettingModel;

/**
 * Pesan WhatsApp laporan kehadiran guru per SHIFT (pagi/siang).
 *
 * Teks disusun di SERVER dari data yang sudah tersimpan + template yang bisa
 * diubah admin (settings.wa_template_absensi), lalu web & Android cukup
 * membuka WhatsApp dengan teks ini — isi pesan dijamin identik di dua platform.
 *
 * Nama ditulis sebagai tag asli "@62…" bila guru punya No. WA (WhatsApp
 * mengubahnya jadi mention di grup), selain itu nama tanpa gelar.
 */
class AbsensiWa
{
    /** Template bawaan — format yang biasa dikirim sekolah ke grup guru. */
    public const DEFAULT_TEMPLATE = <<<'TXT'
        Assalamu'alaikum Warahmatullahi Wabarakatuh
        Yth:
        Bpk Kepala Sekolah
        Bpk dan Ibu Guru

        Selamat {salam} Bapak dan Ibu, izin mengirimkan daftar Bapak dan Ibu guru yang sudah hadir pada hari {hari}, {tanggal} untuk KBM {shift}.

        Berikut daftar Bapak dan Ibu guru yang sudah hadir:
        {daftar_hadir}

        Yang belum hadir:
        {daftar_belum_hadir}

        Izin / sakit / tidak hadir:
        {daftar_tidak_hadir}

        Guru piket yang hadir:
        {daftar_piket}

        Staf TU & lainnya yang hadir:
        {daftar_staf}

        Terima kasih.
        Wassalamu'alaikum Warahmatullahi Wabarakatuh
        TXT;

    /** Penjelasan isian otomatis — ditampilkan di editor template web & Android. */
    public const PLACEHOLDERS = [
        '{salam}'              => 'pagi / siang (mengikuti shift)',
        '{hari}'               => 'nama hari, mis. Kamis',
        '{tanggal}'            => 'mis. 24 September 2026',
        '{shift}'              => 'pagi / siang',
        '{sekolah}'            => 'nama sekolah',
        '{kepsek}'             => 'nama kepala sekolah',
        '{daftar_hadir}'       => 'guru KBM yang sudah hadir (termasuk terlambat)',
        '{daftar_belum_hadir}' => 'guru yang ditandai belum hadir',
        '{daftar_tidak_hadir}' => 'guru izin / sakit / tidak hadir',
        '{daftar_piket}'       => 'guru piket shift ini yang hadir',
        '{daftar_staf}'        => 'staf TU & lainnya (kehadiran kerja) yang hadir',
        '{jumlah_hadir}'       => 'jumlah guru hadir',
        '{jumlah_belum_hadir}' => 'jumlah guru belum hadir',
        '{jumlah_tidak_hadir}' => 'jumlah guru izin/sakit/tidak hadir',
    ];

    private const LABEL_TIDAK_HADIR = ['izin' => 'izin', 'sakit' => 'sakit', 'alpa' => 'tidak hadir'];

    private const HARI_NAMA = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Template aktif: milik admin bila ada, selain itu bawaan. */
    public static function template(?array $setting = null): string
    {
        $setting ??= (new SettingModel())->get();
        $tpl = trim((string) ($setting['wa_template_absensi'] ?? ''));

        return $tpl !== '' ? $tpl : self::DEFAULT_TEMPLATE;
    }

    /** Shift default menurut jam sekarang: sebelum 12.00 = pagi. */
    public static function shiftSekarang(): string
    {
        return (int) date('G') < 12 ? 'pagi' : 'siang';
    }

    /** Validasi nilai shift dari request; tidak dikenal → shift sekarang. */
    public static function normalShift(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));

        return in_array($raw, AbsensiBelumModel::SHIFTS, true) ? $raw : self::shiftSekarang();
    }

    /** Pesan WhatsApp final untuk satu tanggal + shift (dari data tersimpan). */
    public static function pesan(string $tanggal, string $shift): string
    {
        $setting = (new SettingModel())->get();
        $d       = self::data($tanggal, $shift);

        $vars = [
            '{salam}'              => $shift,
            '{hari}'               => $d['hari'],
            '{tanggal}'            => self::tanggalIndo($tanggal),
            '{shift}'              => $shift,
            '{sekolah}'            => (string) ($setting['school_name'] ?? ''),
            '{kepsek}'             => (string) ($setting['headmaster_name'] ?? ''),
            '{jumlah_hadir}'       => (string) count($d['hadir']),
            '{jumlah_belum_hadir}' => (string) count($d['belum']),
            '{jumlah_tidak_hadir}' => (string) count($d['tidak_hadir']),
        ];
        $lists = [
            '{daftar_hadir}'       => self::baris($d['hadir'], static fn ($o) => ' - ' . self::labelHadir($o)),
            '{daftar_belum_hadir}' => self::baris($d['belum'], static fn ($o) => ''),
            '{daftar_tidak_hadir}' => self::baris($d['tidak_hadir'], static fn ($o) => ' - ' . self::LABEL_TIDAK_HADIR[$o['status']]
                . ($o['keterangan'] !== '' ? ' (' . $o['keterangan'] . ')' : '')),
            '{daftar_piket}'       => self::baris($d['piket'], static fn ($o) => ' - ' . self::labelHadir($o)),
            '{daftar_staf}'        => self::baris($d['staf'], static fn ($o) => ' - ' . self::labelHadir($o)),
        ];

        return self::render(self::template($setting), $vars, $lists);
    }

    /**
     * Kelompokkan orang pada satu tanggal + shift ke daftar-daftar pesan.
     * Sumber data sama dengan halaman input (AbsensiHarian::muat): guru ganda
     * sudah digabung ke data utamanya & yang tidak ikut absensi dibuang.
     * Tiap orang: {nama, tag, status, jam_masuk, keterangan}.
     *
     * Urutan penggolongan: guru piket shift ini → guru mengajar → kehadiran
     * kerja (staf, hanya yang berlaku di shift ini) → sisa belum hadir. Tiap
     * orang hanya muncul sekali; izin/sakit/alpa selalu ke "tidak hadir" dan
     * yang ditandai belum hadir ke "belum hadir".
     *
     * @return array{hari:string,hadir:list<array>,belum:list<array>,tidak_hadir:list<array>,piket:list<array>,staf:list<array>}
     */
    public static function data(string $tanggal, string $shift): array
    {
        $d        = AbsensiHarian::muat($tanggal);
        $namaHari = $d['namaHari'] !== ''
            ? ucwords(strtolower($d['namaHari']))
            : (self::HARI_NAMA[(int) date('N', strtotime($tanggal))] ?? '');
        $peta   = GuruModel::petaOrang();
        $keluar = GuruModel::tidakIkutAbsensi();
        $belum  = array_flip($d['belum'][$shift] ?? []);

        // Data guru (termasuk yang terhapus agar nama di catatan lama tetap ada).
        $guru = [];
        foreach ((new GuruModel())->withDeleted()->select('id, nama, no_wa')->findAll() as $g) {
            $guru[(int) $g['id']] = $g;
        }
        $urutNama = static function (array $ids) use ($guru): array {
            usort($ids, static fn ($a, $b) => strcasecmp($guru[$a]['nama'] ?? '', $guru[$b]['nama'] ?? ''));

            return $ids;
        };

        // Status mengajar per orang pada shift ini (terburuk dari sesinya).
        $ajar = [];
        foreach ($d['grup'] as $g) {
            foreach ($g['sesi'] as $s) {
                if ($s['jam_shift'] !== $shift) {
                    continue;
                }
                $oid = (int) $g['guru_id'];
                $o   = $ajar[$oid] ?? ['status' => 'hadir', 'jam_masuk' => '', 'keterangan' => ''];
                $o['status'] = AbsensiGuruModel::worst($o['status'], $s['status']);
                if ($s['jam_masuk'] !== '' && ($o['jam_masuk'] === '' || $s['jam_masuk'] < $o['jam_masuk'])) {
                    $o['jam_masuk'] = $s['jam_masuk'];
                }
                if ($o['keterangan'] === '' && trim((string) $s['keterangan']) !== '') {
                    $o['keterangan'] = trim((string) $s['keterangan']);
                }
                $ajar[$oid] = $o;
            }
        }

        // Kehadiran kerja yang berlaku di shift ini (penuh / shift yang sama).
        $kerja = [];
        foreach ($d['kerja'] as $k) {
            $oid = $peta[(int) $k['guru_id']] ?? (int) $k['guru_id'];
            if (isset($keluar[$oid]) || ! in_array($k['shift'] ?? 'penuh', ['penuh', $shift], true)) {
                continue;
            }
            $kerja[$oid] = [
                'status'     => AbsensiGuruModel::worst($kerja[$oid]['status'] ?? 'hadir', $k['status']),
                'jam_masuk'  => (string) ($k['jam_masuk'] ?? ''),
                'keterangan' => trim((string) ($k['keterangan'] ?? '')),
            ];
        }

        $out  = ['hari' => $namaHari, 'hadir' => [], 'belum' => [], 'tidak_hadir' => [], 'piket' => [], 'staf' => []];
        $seen = [];
        $masuk = static function (string $daftar, int $oid, array $o) use (&$out, &$seen, $guru): void {
            if (isset($seen[$oid])) {
                return; // satu orang cukup muncul sekali
            }
            $seen[$oid]     = true;
            $g              = $guru[$oid] ?? ['nama' => '-', 'no_wa' => null];
            $out[$daftar][] = [
                'nama'       => (string) $g['nama'],
                'tag'        => self::tag($g),
                'status'     => $o['status'] ?? 'hadir',
                'jam_masuk'  => (string) ($o['jam_masuk'] ?? ''),
                'keterangan' => (string) ($o['keterangan'] ?? ''),
            ];
        };
        $golongkan = static function (int $oid, array $o, string $daftarHadir) use ($belum, $masuk): void {
            if (isset(self::LABEL_TIDAK_HADIR[$o['status']])) {
                $masuk('tidak_hadir', $oid, $o);
            } elseif (isset($belum[$oid])) {
                $masuk('belum', $oid, $o);
            } else {
                $masuk($daftarHadir, $oid, $o);
            }
        };

        // 1) Guru piket shift ini (status dari mengajar / kehadiran kerja; default hadir).
        foreach ($urutNama($d['piket'][$shift] ?? []) as $oid) {
            $golongkan($oid, $ajar[$oid] ?? $kerja[$oid] ?? ['status' => 'hadir'], 'piket');
        }
        // 2) Guru mengajar shift ini.
        foreach ($urutNama(array_keys($ajar)) as $oid) {
            $golongkan($oid, $ajar[$oid], 'hadir');
        }
        // 3) Kehadiran kerja (staf TU, pimpinan, dsb.).
        foreach ($urutNama(array_keys($kerja)) as $oid) {
            $golongkan($oid, $kerja[$oid], 'staf');
        }
        // 4) Belum hadir yang tidak mengajar & tidak tercatat kerja.
        foreach ($urutNama(array_keys($belum)) as $oid) {
            if (! isset($keluar[$oid])) {
                $masuk('belum', (int) $oid, ['status' => 'hadir']);
            }
        }

        return $out;
    }

    /**
     * Isi template: ganti isian tunggal, dan baris yang berisi placeholder
     * daftar diganti daftar bernomor. Bila daftarnya KOSONG, baris placeholder
     * beserta satu baris judul tepat di atasnya ikut dihilangkan.
     */
    public static function render(string $template, array $vars, array $lists): string
    {
        // Placeholder daftar di tengah kalimat → gabung dengan baris baru.
        $inline = array_map(static fn ($l) => implode("\n", $l), $lists);

        $out = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $template)) as $line) {
            $trim = trim($line);
            if (isset($lists[$trim])) {
                if ($lists[$trim] === []) {
                    $prev = end($out);
                    if ($prev !== false && trim($prev) !== '' && ! str_contains($prev, '{')) {
                        array_pop($out);
                    }

                    continue;
                }
                array_push($out, ...$lists[$trim]);

                continue;
            }
            $out[] = strtr($line, $vars + $inline);
        }

        $text = implode("\n", $out);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    /** Daftar bernomor "1. @62… - hadir". */
    private static function baris(array $orang, callable $akhiran): array
    {
        $rows = [];
        foreach (array_values($orang) as $i => $o) {
            $rows[] = ($i + 1) . '. ' . $o['tag'] . $akhiran($o);
        }

        return $rows;
    }

    private static function labelHadir(array $o): string
    {
        if (($o['status'] ?? 'hadir') !== 'telat') {
            return 'hadir';
        }

        return 'hadir (terlambat' . ($o['jam_masuk'] !== '' ? ', masuk ' . $o['jam_masuk'] : '') . ')';
    }

    /** "@62812…" bila ada No. WA (jadi mention di grup), selain itu nama tanpa gelar. */
    public static function tag(array $guru): string
    {
        $wa = trim((string) ($guru['no_wa'] ?? ''));

        return $wa !== '' ? '@' . $wa : self::namaTanpaGelar((string) ($guru['nama'] ?? ''));
    }

    /** "Elvira Safitri, S.Pd" → "Elvira Safitri" (gelar di belakang koma dibuang). */
    public static function namaTanpaGelar(string $nama): string
    {
        $pos = strpos($nama, ',');

        return trim($pos === false ? $nama : substr($nama, 0, $pos));
    }

    public static function tanggalIndo(string $tanggal): string
    {
        $ts = strtotime($tanggal);

        return (int) date('j', $ts) . ' ' . self::BULAN[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}
