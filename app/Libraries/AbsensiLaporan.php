<?php

namespace App\Libraries;

use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AbsensiSnapshotModel;
use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan absensi BULANAN format sekolah — meniru lembar manual sekolah:
 *   1. ABSEN            : matriks orang × tanggal × P/S (kode H, HT, I, S, TH, -)
 *   2. REKAP HADIR      : jumlah hadir pagi/siang, total hari (+ uang transport)
 *   3. REKAP TIDAK HADIR: jumlah tidak hadir pagi/siang, total JP & potongan
 *
 * Kode sel: H hadir · HT hadir terlambat · I izin · S sakit · TH tidak hadir
 * (alpa / tetap "belum hadir") · "-" tidak ada tugas pada shift itu · kosong =
 * tanggal belum/tidak diabsen. JP (jam pelajaran) dihitung per sesi mengajar:
 * telat, izin, sakit, alpa masing-masing 1 JP per sesi → potongan per JP.
 */
class AbsensiLaporan
{
    /** Kertas F4 (215 × 330 mm) dalam poin untuk Dompdf. */
    public const KERTAS_F4 = [0, 0, 609.45, 935.43];

    public const KODE = ['hadir' => 'H', 'telat' => 'HT', 'izin' => 'I', 'sakit' => 'S', 'alpa' => 'TH'];

    /** Kode sel → status (untuk status harian rekap). */
    public const STATUS_KODE = ['H' => 'hadir', 'HT' => 'telat', 'I' => 'izin', 'S' => 'sakit', 'TH' => 'alpa'];

    private const BULAN = [
        1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
        7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
    ];
    private const HARI_SINGKAT = [1 => 'SEN', 2 => 'SEL', 3 => 'RAB', 4 => 'KAM', 5 => 'JUM', 6 => 'SAB', 7 => 'MIN'];

    // Warna senada lembar manual sekolah (ungu), dibuat lebih lembut untuk cetak.
    private const WARNA_JUDUL = '7B2C8F';
    private const WARNA_PITA  = ['F6ECF8', 'EBD7F0'];

    /**
     * Matriks kehadiran pada rentang tanggal.
     *
     * @return array{dari:string,sampai:string,tanggal:list<string>,tercatat:array<string,true>,orang:list<array>}
     */
    public static function matriks(string $dari, string $sampai): array
    {
        $tanggal = [];
        for ($ts = strtotime($dari); $ts <= strtotime($sampai); $ts = strtotime('+1 day', $ts)) {
            $tanggal[] = date('Y-m-d', $ts);
        }
        $tercatat = array_flip((new AbsensiHariModel())->datesInRange($dari, $sampai));
        $peta     = GuruModel::petaOrang();
        $keluar   = GuruModel::tidakIkutAbsensi();
        $orang    = static fn (int $gid): int => $peta[$gid] ?? $gid;

        $snapshot = (new AbsensiSnapshotModel())->rentang($dari, $sampai);
        $jadwalHari = [];
        $sesiLive   = static function (string $tgl) use (&$jadwalHari): array {
            $n = (int) date('N', strtotime($tgl));
            if (! array_key_exists($n, $jadwalHari)) {
                $hari            = (new HariModel())->byWeekday($n);
                $jadwalHari[$n]  = $hari && (int) $hari['aktif'] === 1
                    ? array_map(static fn ($s) => [
                        'guru_id'  => (int) $s['guru_id'],
                        'kelas_id' => (int) $s['kelas_id'],
                        'jam_id'   => (int) $s['jam_id'],
                        'shift'    => $s['jam_shift'] ?? 'pagi',
                    ], (new JadwalModel())->sessionsForHari((int) $hari['id']))
                    : [];
            }

            return $jadwalHari[$n];
        };

        // Pengecualian sesi: [tgl][kelas-jam] => status.
        $exc = [];
        foreach ((new AbsensiGuruModel())->select('tanggal, kelas_id, jam_id, status')
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai)->findAll() as $r) {
            $exc[$r['tanggal']][$r['kelas_id'] . '-' . $r['jam_id']] = $r['status'];
        }
        // Kehadiran kerja: [tgl][orang] => {status, shift}.
        $kerja = [];
        foreach ((new AbsensiKerjaModel())->select('tanggal, guru_id, status, shift')
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai)->findAll() as $r) {
            $kerja[$r['tanggal']][$orang((int) $r['guru_id'])] = ['status' => $r['status'], 'shift' => $r['shift'] ?? 'penuh'];
        }
        // Belum hadir tak diselesaikan: [tgl][shift][orang] => true.
        $belum = [];
        foreach ((new AbsensiBelumModel())->mapRange($dari, $sampai) as $gid => $perTgl) {
            foreach ($perTgl as $tgl => $perShift) {
                foreach (array_keys($perShift) as $sh) {
                    $belum[$tgl][$sh][$orang((int) $gid)] = true;
                }
            }
        }

        $sel = [];   // [orang][tgl][pagi|siang] => kode
        $jp  = [];   // [orang] => ['telat' => n, 'absen' => n]
        foreach ($tanggal as $tgl) {
            if (! isset($tercatat[$tgl])) {
                continue;
            }
            // 1) Sesi mengajar (salinan saat disimpan; data lama → jadwal aktif).
            $perShift = [];
            foreach ($snapshot[$tgl] ?? $sesiLive($tgl) as $s) {
                $oid = $orang($s['guru_id']);
                if (isset($keluar[$oid]) || isset($keluar[$s['guru_id']])) {
                    continue;
                }
                $perShift[$oid][$s['shift']][] = $exc[$tgl][$s['kelas_id'] . '-' . $s['jam_id']] ?? 'hadir';
            }
            foreach ($perShift as $oid => $shifts) {
                foreach ($shifts as $sh => $statuses) {
                    if (isset($belum[$tgl][$sh][$oid])) {
                        $sel[$oid][$tgl][$sh] = 'TH';
                        $jp[$oid]['absen']    = ($jp[$oid]['absen'] ?? 0) + count($statuses);
                        continue;
                    }
                    $worst = 'hadir';
                    foreach ($statuses as $st) {
                        $worst = AbsensiGuruModel::worst($worst, $st);
                        if ($st === 'telat') {
                            $jp[$oid]['telat'] = ($jp[$oid]['telat'] ?? 0) + 1;
                        } elseif ($st !== 'hadir') {
                            $jp[$oid]['absen'] = ($jp[$oid]['absen'] ?? 0) + 1;
                        }
                    }
                    $sel[$oid][$tgl][$sh] = self::KODE[$worst] ?? 'H';
                }
            }
            // 2) Kehadiran kerja pada shift yang tidak ia ajar.
            foreach ($kerja[$tgl] ?? [] as $oid => $k) {
                if (isset($keluar[$oid])) {
                    continue;
                }
                foreach ($k['shift'] === 'penuh' ? ['pagi', 'siang'] : [$k['shift']] as $sh) {
                    if (! isset($sel[$oid][$tgl][$sh])) {
                        $sel[$oid][$tgl][$sh] = isset($belum[$tgl][$sh][$oid]) ? 'TH' : (self::KODE[$k['status']] ?? 'H');
                    }
                }
            }
            // 3) Ditandai belum hadir tanpa tugas lain tercatat → tetap tidak hadir.
            foreach ($belum[$tgl] ?? [] as $sh => $ids) {
                foreach (array_keys($ids) as $oid) {
                    if (! isset($keluar[$oid]) && ! isset($sel[$oid][$tgl][$sh])) {
                        $sel[$oid][$tgl][$sh] = 'TH';
                    }
                }
            }
        }

        // Daftar orang: seluruh guru aktif (data utama, ikut absensi) urut kode
        // guru seperti lembar manual, ditambah orang lain yang punya catatan.
        $guru = [];
        foreach ((new GuruModel())->withDeleted()->select('id, kode_guru, nama, deleted_at')->findAll() as $g) {
            $guru[(int) $g['id']] = $g;
        }
        $ids = [];
        foreach ($guru as $id => $g) {
            if ($g['deleted_at'] === null && ! isset($peta[$id]) && ! isset($keluar[$id])) {
                $ids[$id] = true;
            }
        }
        foreach (array_keys($sel) as $oid) {
            $ids[$oid] = true;
        }
        $ids = array_keys($ids);
        usort($ids, static function ($a, $b) use ($guru) {
            return strnatcasecmp((string) ($guru[$a]['kode_guru'] ?? ''), (string) ($guru[$b]['kode_guru'] ?? ''))
                ?: strcasecmp((string) ($guru[$a]['nama'] ?? ''), (string) ($guru[$b]['nama'] ?? ''));
        });

        $jabatanMap = (new GuruJabatanModel())->mapByGuru();
        $rows       = [];
        foreach ($ids as $oid) {
            $r = [
                'id'      => $oid,
                'kode'    => (string) ($guru[$oid]['kode_guru'] ?? ''),
                'nama'    => (string) ($guru[$oid]['nama'] ?? ('Guru #' . $oid)),
                'jabatan' => implode(', ', array_column($jabatanMap[$oid] ?? [], 'nama')),
                'sel'     => [],
                'hadir'   => ['pagi' => 0, 'siang' => 0],
                'tidak'   => ['pagi' => 0, 'siang' => 0],
                'kode_n'  => ['H' => 0, 'HT' => 0, 'I' => 0, 'S' => 0, 'TH' => 0],
                'hari_hadir' => 0,
                'jp_telat'   => (int) ($jp[$oid]['telat'] ?? 0),
                'jp_absen'   => (int) ($jp[$oid]['absen'] ?? 0),
            ];
            foreach ($tanggal as $tgl) {
                $hadirHari = false;
                foreach (['pagi', 'siang'] as $sh) {
                    $kode = isset($tercatat[$tgl]) ? ($sel[$oid][$tgl][$sh] ?? '-') : '';
                    $r['sel'][$tgl][$sh] = $kode;
                    if ($kode === 'H' || $kode === 'HT') {
                        $r['hadir'][$sh]++;
                        $hadirHari = true;
                    } elseif (in_array($kode, ['I', 'S', 'TH'], true)) {
                        $r['tidak'][$sh]++;
                    }
                    if (isset($r['kode_n'][$kode])) {
                        $r['kode_n'][$kode]++;
                    }
                }
                $r['hari_hadir'] += $hadirHari ? 1 : 0;
            }
            $r['jp_total'] = $r['jp_telat'] + $r['jp_absen'];
            $rows[]        = $r;
        }

        return ['dari' => $dari, 'sampai' => $sampai, 'tanggal' => $tanggal, 'tercatat' => $tercatat, 'orang' => $rows];
    }

    /**
     * Status HARIAN per orang dari matriks: [orang][tanggal] => status terburuk
     * (hanya tanggal tercatat & orang yang punya tugas hari itu). Fondasi
     * AbsensiRekap agar rekap & laporan bulanan selalu sama.
     */
    public static function statusHarian(array $m): array
    {
        $out = [];
        foreach ($m['orang'] as $r) {
            foreach ($r['sel'] as $tgl => $perShift) {
                $st = null;
                foreach ($perShift as $kode) {
                    if (isset(self::STATUS_KODE[$kode])) {
                        $st = AbsensiGuruModel::worst($st ?? 'hadir', self::STATUS_KODE[$kode]);
                    }
                }
                if ($st !== null) {
                    $out[$r['id']][$tgl] = $st;
                }
            }
        }

        return $out;
    }

    /** Rentang satu bulan dari "YYYY-MM" (tidak valid → bulan ini). */
    public static function rentangBulan(?string $bulan): array
    {
        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $bulan) ? (string) $bulan : date('Y-m');
        $awal  = $bulan . '-01';

        return [$awal, date('Y-m-t', strtotime($awal)), $bulan];
    }

    public static function namaBulan(string $bulan): string
    {
        $ts = strtotime($bulan . '-01');

        return self::BULAN[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    public static function namaBerkas(string $bulan): string
    {
        return 'Laporan-Absensi-' . $bulan;
    }

    // ===================== EXCEL =====================

    public static function excel(array $m, string $bulan, array $setting): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

        $absen = $ss->getActiveSheet();
        $absen->setTitle('ABSEN');
        $kolom = self::sheetAbsen($absen, $m, $bulan, $setting);

        self::sheetRekapHadir($ss->createSheet()->setTitle('REKAP HADIR'), $m, $bulan, $setting, $kolom);
        self::sheetRekapTidak($ss->createSheet()->setTitle('REKAP TIDAK HADIR'), $m, $bulan, $setting, $kolom);
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /**
     * Lembar ABSEN (matriks). Mengembalikan letak kolom ringkasan & baris data
     * untuk dirujuk lembar rekap.
     *
     * @return array{baris1:int,barisN:int,hadirP:string,hadirS:string,tidakP:string,tidakS:string}
     */
    private static function sheetAbsen(Worksheet $sh, array $m, string $bulan, array $setting): array
    {
        $n       = count($m['tanggal']);
        $cDay0   = 3;                          // kolom C
        $cAkhir  = $cDay0 + $n * 2 - 1;        // kolom sel terakhir
        $col     = static fn (int $i): string => Coordinate::stringFromColumnIndex($i);
        $hadirP  = $col($cAkhir + 1);
        $hadirS  = $col($cAkhir + 2);
        $tidakP  = $col($cAkhir + 3);
        $tidakS  = $col($cAkhir + 4);
        $total   = $col($cAkhir + 5);
        $pLabel  = $col($cAkhir + 7);
        $pNilai  = $col($cAkhir + 8);
        $dayA    = $col($cDay0);
        $dayZ    = $col($cAkhir);

        // Judul (seperti lembar manual).
        foreach ([
            1 => 'ABSEN GURU DAN STAF TATA USAHA',
            2 => strtoupper((string) ($setting['school_name'] ?? '')),
            3 => 'TAHUN PELAJARAN ' . ($setting['academic_year'] ?? ''),
        ] as $r => $teks) {
            $sh->mergeCells("A{$r}:{$total}{$r}")->setCellValue("A{$r}", $teks);
        }
        $sh->getStyle('A1:A3')->getFont()->setBold(true)->setSize(12);
        $sh->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->setCellValue('C5', 'KETERANGAN:');
        $sh->setCellValue('C6', 'P = PAGI · S = SIANG · H = HADIR · HT = HADIR TERLAMBAT · I = IZIN · S = SAKIT · TH = TIDAK HADIR · - = TIDAK ADA TUGAS');
        $sh->getStyle('C5:C6')->getFont()->setBold(true)->setSize(8);

        // Kepala tabel baris 7–10.
        $sh->mergeCells('A7:A10')->setCellValue('A7', 'NO');
        $sh->mergeCells('B7:B10')->setCellValue('B7', 'NAMA');
        $sh->mergeCells("{$dayA}7:{$dayZ}7")->setCellValue("{$dayA}7", 'BULAN ' . self::namaBulan($bulan));
        foreach ($m['tanggal'] as $i => $tgl) {
            $a  = $col($cDay0 + $i * 2);
            $b  = $col($cDay0 + $i * 2 + 1);
            $ts = strtotime($tgl);
            $sh->mergeCells("{$a}8:{$b}8")->setCellValue("{$a}8", (int) date('j', $ts));
            $sh->mergeCells("{$a}9:{$b}9")->setCellValue("{$a}9", self::HARI_SINGKAT[(int) date('N', $ts)]);
            $sh->setCellValue("{$a}10", 'P');
            $sh->setCellValue("{$b}10", 'S');
            $pita = self::WARNA_PITA[$i % 2];
            $sh->getStyle("{$a}8:{$b}10")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($pita);
            $sh->getColumnDimension($a)->setWidth(3.2);
            $sh->getColumnDimension($b)->setWidth(3.2);
        }
        $sh->mergeCells("{$hadirP}7:{$hadirS}9")->setCellValue("{$hadirP}7", 'HADIR');
        $sh->mergeCells("{$tidakP}7:{$tidakS}9")->setCellValue("{$tidakP}7", 'IZIN/SAKIT/TH');
        $sh->mergeCells("{$total}7:{$total}10")->setCellValue("{$total}7", 'TOTAL HADIR');
        foreach ([$hadirP => 'P', $hadirS => 'S', $tidakP => 'P', $tidakS => 'S'] as $c => $v) {
            $sh->setCellValue("{$c}10", $v);
        }
        $sh->getStyle("A7:{$total}10")->getFont()->setBold(true);
        $sh->getStyle("A7:{$total}10")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach (['A7:B10', "{$dayA}7:{$dayZ}7", "{$hadirP}7:{$total}10"] as $rng) {
            $sh->getStyle($rng)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_JUDUL);
            $sh->getStyle($rng)->getFont()->getColor()->setRGB('FFFFFF');
        }

        // Isi.
        $r0 = 11;
        $r  = $r0;
        foreach ($m['orang'] as $i => $o) {
            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $o['nama']);
            foreach ($m['tanggal'] as $j => $tgl) {
                $sh->setCellValue($col($cDay0 + $j * 2) . $r, $o['sel'][$tgl]['pagi']);
                $sh->setCellValue($col($cDay0 + $j * 2 + 1) . $r, $o['sel'][$tgl]['siang']);
            }
            // Rumus hidup (COUNTIFS) — ikut berubah bila sel dikoreksi manual.
            $hdr = "\${$dayA}\$10:\${$dayZ}\$10";
            $isi = "{$dayA}{$r}:{$dayZ}{$r}";
            $sh->setCellValue("{$hadirP}{$r}", "=COUNTIFS({$hdr},\"P\",{$isi},\"H\")+COUNTIFS({$hdr},\"P\",{$isi},\"HT\")");
            $sh->setCellValue("{$hadirS}{$r}", "=COUNTIFS({$hdr},\"S\",{$isi},\"H\")+COUNTIFS({$hdr},\"S\",{$isi},\"HT\")");
            $sh->setCellValue("{$tidakP}{$r}", "=COUNTIFS({$hdr},\"P\",{$isi},\"I\")+COUNTIFS({$hdr},\"P\",{$isi},\"S\")+COUNTIFS({$hdr},\"P\",{$isi},\"TH\")");
            $sh->setCellValue("{$tidakS}{$r}", "=COUNTIFS({$hdr},\"S\",{$isi},\"I\")+COUNTIFS({$hdr},\"S\",{$isi},\"S\")+COUNTIFS({$hdr},\"S\",{$isi},\"TH\")");
            $sh->setCellValue("{$total}{$r}", "=SUM({$hadirP}{$r}:{$hadirS}{$r})");
            $r++;
        }
        $rN = max($r0, $r - 1);

        // Warna pita per tanggal di area isi + sel tidak hadir ditebalkan.
        foreach ($m['tanggal'] as $j => $tgl) {
            $a = $col($cDay0 + $j * 2);
            $b = $col($cDay0 + $j * 2 + 1);
            $sh->getStyle("{$a}{$r0}:{$b}{$rN}")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::WARNA_PITA[$j % 2]);
        }
        $sh->getStyle("A7:{$total}{$rN}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sh->getStyle("A{$r0}:A{$rN}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("{$dayA}{$r0}:{$total}{$rN}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("{$hadirP}{$r0}:{$total}{$rN}")->getFont()->setBold(true);
        $sh->getColumnDimension('A')->setWidth(4.5);
        $sh->getColumnDimension('B')->setWidth(30);
        foreach ([$hadirP, $hadirS, $tidakP, $tidakS] as $c) {
            $sh->getColumnDimension($c)->setWidth(4.2);
        }
        $sh->getColumnDimension($total)->setWidth(8);

        // Persentase keseluruhan (seperti lembar manual).
        $semua = "{$dayA}{$r0}:{$dayZ}{$rN}";
        $hadirF = "(COUNTIF({$semua},\"H\")+COUNTIF({$semua},\"HT\"))";
        $tidakF = "(COUNTIF({$semua},\"I\")+COUNTIF({$semua},\"S\")+COUNTIF({$semua},\"TH\"))";
        $sh->mergeCells("{$pLabel}7:{$pNilai}10")->setCellValue("{$pLabel}7", 'PERSENTASE');
        $sh->setCellValue("{$pLabel}{$r0}", 'HADIR');
        $sh->setCellValue("{$pNilai}{$r0}", "=IFERROR({$hadirF}/({$hadirF}+{$tidakF}),0)");
        $sh->setCellValue("{$pLabel}" . ($r0 + 1), 'TIDAK HADIR');
        $sh->setCellValue("{$pNilai}" . ($r0 + 1), "=IFERROR({$tidakF}/({$hadirF}+{$tidakF}),0)");
        $sh->getStyle("{$pNilai}{$r0}:{$pNilai}" . ($r0 + 1))->getNumberFormat()->setFormatCode('0.0%');
        $sh->getStyle("{$pLabel}7:{$pNilai}10")->getFont()->setBold(true);
        $sh->getStyle("{$pLabel}7:{$pNilai}10")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("{$pLabel}7:{$pNilai}" . ($r0 + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sh->getColumnDimension($pLabel)->setWidth(13);
        $sh->getColumnDimension($pNilai)->setWidth(9);

        self::tandaTangan($sh, $rN + 2, $col($cAkhir - 12 > 3 ? $cAkhir - 12 : 3), $total, $setting);

        $sh->freezePane("{$dayA}11");
        $sh->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setFitToWidth(1)->setFitToHeight(0);
        $sh->getPageMargins()->setLeft(0.3)->setRight(0.3)->setTop(0.4)->setBottom(0.4);

        return ['baris1' => $r0, 'barisN' => $rN, 'hadirP' => $hadirP, 'hadirS' => $hadirS, 'tidakP' => $tidakP, 'tidakS' => $tidakS];
    }

    /** REKAP HADIR: jumlah hadir pagi/siang + total hari (+ uang transport bila diatur). */
    private static function sheetRekapHadir(Worksheet $sh, array $m, string $bulan, array $setting, array $k): void
    {
        $tarif  = (int) ($setting['absensi_transport'] ?? 0);
        $pakai  = $tarif > 0;
        $akhir  = $pakai ? 'H' : 'G';
        self::judulRekap($sh, 'REKAP KEHADIRAN GURU DAN STAF TATA USAHA', $bulan, $setting, $akhir);
        if ($pakai) {
            $sh->setCellValue('J3', 'Uang transport / hari');
            $sh->setCellValue('K3', $tarif)->getStyle('K3')->getNumberFormat()->setFormatCode('#,##0');
        }

        $head = ['NO', 'NAMA', 'PAGI', 'SIANG', 'TOTAL', 'KET', 'TOTAL HARI'];
        if ($pakai) {
            $head[] = 'UANG TRANSPORT';
        }
        $sh->fromArray($head, null, 'A5', true);

        $r = 6;
        foreach ($m['orang'] as $i => $o) {
            $src = $k['baris1'] + $i;
            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $o['nama']);
            $sh->setCellValue("C{$r}", "='ABSEN'!{$k['hadirP']}{$src}");
            $sh->setCellValue("D{$r}", "='ABSEN'!{$k['hadirS']}{$src}");
            $sh->setCellValue("E{$r}", "=SUM(C{$r}:D{$r})");
            $sh->setCellValue("F{$r}", $o['kode_n']['HT'] > 0 ? 'Terlambat ' . $o['kode_n']['HT'] . 'x' : '');
            $sh->setCellValue("G{$r}", $o['hari_hadir']);
            if ($pakai) {
                $sh->setCellValue("H{$r}", "=G{$r}*\$K\$3");
            }
            $r++;
        }
        self::barisTotal($sh, $r, ['C', 'D', 'E', 'G'] + ($pakai ? [4 => 'H'] : []), $akhir);
        self::gayaRekap($sh, $r, $akhir, $pakai ? ['H'] : []);
        self::tandaTangan($sh, $r + 2, 'E', $akhir, $setting);
    }

    /** REKAP TIDAK HADIR: jumlah tidak hadir pagi/siang, total JP & potongan. */
    private static function sheetRekapTidak(Worksheet $sh, array $m, string $bulan, array $setting, array $k): void
    {
        $tarif = (int) ($setting['absensi_potongan_jp'] ?? 5000);
        self::judulRekap($sh, 'REKAP TIDAK HADIR GURU DAN STAF TATA USAHA', $bulan, $setting, 'H');
        $sh->setCellValue('J3', 'Potongan / JP');
        $sh->setCellValue('K3', $tarif)->getStyle('K3')->getNumberFormat()->setFormatCode('#,##0');

        $sh->fromArray(['NO', 'NAMA', 'PAGI', 'SIANG', 'TOTAL', 'KET', 'TOTAL JAM', 'TOTAL POTONGAN'], null, 'A5', true);
        $r = 6;
        foreach ($m['orang'] as $i => $o) {
            $src = $k['baris1'] + $i;
            $ket = [];
            foreach (['I' => 'Izin', 'S' => 'Sakit', 'TH' => 'Tidak hadir'] as $kode => $lbl) {
                if ($o['kode_n'][$kode] > 0) {
                    $ket[] = $lbl . ' ' . $o['kode_n'][$kode];
                }
            }
            if ($o['jp_telat'] > 0) {
                $ket[] = 'Terlambat ' . $o['jp_telat'] . ' JP';
            }
            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $o['nama']);
            $sh->setCellValue("C{$r}", "='ABSEN'!{$k['tidakP']}{$src}");
            $sh->setCellValue("D{$r}", "='ABSEN'!{$k['tidakS']}{$src}");
            $sh->setCellValue("E{$r}", "=SUM(C{$r}:D{$r})");
            $sh->setCellValue("F{$r}", implode(', ', $ket));
            $sh->setCellValue("G{$r}", $o['jp_total']);
            $sh->setCellValue("H{$r}", "=G{$r}*\$K\$3");
            $r++;
        }
        self::barisTotal($sh, $r, ['C', 'D', 'E', 'G', 'H'], 'H');
        self::gayaRekap($sh, $r, 'H', ['H']);
        $sh->setCellValue('A' . ($r + 1), 'Total jam = JP terlambat + JP izin/sakit/tidak hadir (per sesi mengajar). Potongan = total jam × potongan per JP.');
        $sh->getStyle('A' . ($r + 1))->getFont()->setItalic(true)->setSize(8);
        self::tandaTangan($sh, $r + 3, 'E', 'H', $setting);
    }

    private static function judulRekap(Worksheet $sh, string $judul, string $bulan, array $setting, string $akhir): void
    {
        $sh->mergeCells("A1:{$akhir}1")->setCellValue('A1', $judul);
        $sh->mergeCells("A2:{$akhir}2")->setCellValue('A2', strtoupper((string) ($setting['school_name'] ?? '')));
        $sh->mergeCells("A3:{$akhir}3")->setCellValue('A3', 'PERIODE BULAN ' . self::namaBulan($bulan));
        $sh->getStyle('A1:A3')->getFont()->setBold(true)->setSize(12);
        $sh->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('J3:K3')->getFont()->setBold(true);
    }

    private static function barisTotal(Worksheet $sh, int $r, array $kolom, string $akhir): void
    {
        $sh->setCellValue("B{$r}", 'TOTAL');
        foreach ($kolom as $c) {
            $sh->setCellValue("{$c}{$r}", "=SUM({$c}6:{$c}" . ($r - 1) . ')');
        }
        $sh->getStyle("A{$r}:{$akhir}{$r}")->getFont()->setBold(true);
        $sh->getStyle("A{$r}:{$akhir}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_PITA[1]);
    }

    private static function gayaRekap(Worksheet $sh, int $rTotal, string $akhir, array $kolomUang): void
    {
        $sh->getStyle("A5:{$akhir}5")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle("A5:{$akhir}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_JUDUL);
        $sh->getStyle("A5:{$akhir}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sh->getStyle("A5:{$akhir}{$rTotal}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sh->getStyle("A6:A{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("C6:E{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("G6:G{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach ($kolomUang as $c) {
            $sh->getStyle("{$c}6:{$c}{$rTotal}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        }
        foreach (['A' => 5, 'B' => 32, 'C' => 8, 'D' => 8, 'E' => 8, 'F' => 30, 'G' => 11, 'H' => 17, 'J' => 20, 'K' => 10] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }
        $sh->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    }

    /** Blok tanda tangan: "{Kota}, {tanggal}" / Mengetahui, / Kepala Sekolah / nama. */
    private static function tandaTangan(Worksheet $sh, int $r, string $dari, string $sampai, array $setting): void
    {
        $baris = [
            $r     => ($setting['city'] ?? '') . ', ' . self::tanggalCetak(),
            $r + 1 => 'Mengetahui,',
            $r + 2 => 'Kepala Sekolah',
            $r + 6 => (string) ($setting['headmaster_name'] ?? ''),
        ];
        foreach ($baris as $i => $teks) {
            $sh->mergeCells("{$dari}{$i}:{$sampai}{$i}")->setCellValue("{$dari}{$i}", $teks);
            $sh->getStyle("{$dari}{$i}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sh->getStyle("{$dari}" . ($r + 6))->getFont()->setBold(true)->setUnderline(true);
    }

    public static function tanggalCetak(): string
    {
        $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return (int) date('j') . ' ' . $bulan[(int) date('n')] . ' ' . date('Y');
    }

    // ===================== PDF =====================

    /** HTML laporan (3 bagian) untuk Dompdf — kertas F4/Legal landscape. */
    public static function pdfHtml(array $m, string $bulan, array $setting): string
    {
        return view('pdf/laporan_absensi', [
            'm'        => $m,
            'bulan'    => $bulan,
            'judulBln' => self::namaBulan($bulan),
            'setting'  => $setting,
            'hari'     => self::HARI_SINGKAT,
            'tanggal'  => self::tanggalCetak(),
        ]);
    }
}
