<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan KELENGKAPAN BIODATA SISWA — Excel siap cetak.
 *
 *   Lembar "REKAP"  : satu baris per kelas (jumlah siswa, data lengkap,
 *                     belum lengkap, belum mengisi, menunggu, perlu perbaikan)
 *                     + tanda tangan Kepala Sekolah.
 *   Lembar per kelas: daftar nama siswa + status isian + kelengkapan +
 *                     keterangan (kolom apa yang masih kurang)
 *                     + tanda tangan Kepala Sekolah & Wali Kelas.
 *
 * "Lengkap" diukur dari DATA MASTER SISWA (data resmi), bukan dari isian:
 * seluruh kolom BiodataForm::WAJIB sudah terisi. Jadi siswa yang biodatanya
 * masuk lewat impor Excel pun ikut dinilai, dan isian yang belum disetujui
 * belum dihitung lengkap.
 */
final class BiodataLaporan
{
    private const WARNA_JUDUL   = '1A3A6B'; // biru brand, sama dengan ekspor Master Data
    private const WARNA_TOTAL   = 'DBEAFE';
    private const WARNA_LENGKAP = '15803D';
    private const WARNA_BELUM   = 'B45309';

    private const STATUS_ISIAN = [
        'menunggu'  => 'Menunggu verifikasi',
        'perbaikan' => 'Perlu perbaikan',
        'disetujui' => 'Disetujui',
    ];

    /**
     * Data siswa aktif dikelompokkan per kelas (urut tingkat lalu nama kelas
     * secara alami), lengkap dengan hitungan per kelas.
     *
     * @return list<array<string, mixed>>
     */
    public static function data(int $kelasId = 0): array
    {
        $b = db_connect()->table('siswa s')
            ->select('s.*, k.nama_kelas, k.tingkat, g.nama AS wali, b.status AS isian_status, b.catatan_admin')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->join('guru g', 'g.id = k.wali_kelas_id', 'left')
            ->join('biodata_isian b', 'b.siswa_id = s.id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif');
        if ($kelasId > 0) {
            $b->where('s.kelas_id', $kelasId);
        }
        $rows = $b->orderBy('s.nama', 'ASC')->get()->getResultArray();

        $kelas = [];
        foreach ($rows as $r) {
            $kid = (int) ($r['kelas_id'] ?? 0);
            $kelas[$kid] ??= [
                'id'      => $kid,
                'nama'    => $r['nama_kelas'] ?? 'Tanpa Kelas',
                'tingkat' => $r['tingkat'] ?? '',
                'wali'    => (string) ($r['wali'] ?? ''),
                'siswa'   => [],
            ];
            $kelas[$kid]['siswa'][] = self::nilaiSiswa($r);
        }

        foreach ($kelas as &$k) {
            $s               = $k['siswa'];
            $k['total']      = count($s);
            $k['lengkap']    = count(array_filter($s, static fn ($x) => $x['lengkap']));
            $k['belum']      = $k['total'] - $k['lengkap'];
            $k['tanpa_isian'] = count(array_filter($s, static fn ($x) => $x['isian'] === null));
            foreach (array_keys(self::STATUS_ISIAN) as $st) {
                $k[$st] = count(array_filter($s, static fn ($x) => $x['isian'] === $st));
            }
        }
        unset($k);

        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($kelas, static fn ($a, $b) => (($a['id'] === 0) <=> ($b['id'] === 0))
            ?: (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9))
            ?: strnatcasecmp($a['nama'], $b['nama']));

        return $kelas;
    }

    /** Status isian, kelengkapan, dan keterangan satu siswa. */
    private static function nilaiSiswa(array $r): array
    {
        $kosong  = BiodataForm::kolomKosong($r);
        $lengkap = $kosong === [];
        $isian   = $r['isian_status'] ?? null;

        if ($lengkap) {
            $ket = ! empty($r['biodata_at']) ? 'Disahkan ' . date('d/m/Y', strtotime($r['biodata_at'])) : '';
        } elseif ($isian === 'menunggu') {
            $ket = 'Isian sudah dikirim, menunggu disetujui admin';
        } elseif ($isian === 'perbaikan') {
            $ket = 'Dikembalikan untuk diperbaiki' . (! empty($r['catatan_admin']) ? ': ' . $r['catatan_admin'] : '');
        } elseif (count($kosong) >= count(BiodataForm::WAJIB) - 2) {
            // Hanya nama & jenis kelamin (data awal sekolah) yang ada.
            $ket = 'Belum mengisi form biodata';
        } else {
            $ket = 'Kurang: ' . implode(', ', $kosong);
        }

        return [
            'nis'     => (string) $r['nis'],
            'nisn'    => (string) ($r['nisn'] ?? ''),
            'nama'    => (string) $r['nama'],
            'jk'      => (string) ($r['jenis_kelamin'] ?? ''),
            'isian'   => $isian,
            'lengkap' => $lengkap,
            'ket'     => $ket,
        ];
    }

    // ===================== EXCEL =====================

    /** @param list<array<string, mixed>> $kelas hasil data() */
    public static function excel(array $kelas, array $setting): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $ss->getProperties()->setTitle('Laporan Kelengkapan Biodata Siswa')
            ->setCreator((string) ($setting['school_name'] ?? ''));

        self::lembarRekap($ss->getActiveSheet()->setTitle('REKAP'), $kelas, $setting);
        foreach ($kelas as $k) {
            self::lembarKelas($ss->createSheet()->setTitle(self::judulLembar($k['nama'])), $k, $setting);
        }
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /** Nama berkas unduhan: satu kelas → nama kelasnya, selain itu "Semua-Kelas". */
    public static function namaBerkas(array $kelas, int $kelasId): string
    {
        $bagian = $kelasId > 0 && count($kelas) === 1
            ? preg_replace('/[^A-Za-z0-9]+/', '-', $kelas[0]['nama'])
            : 'Semua-Kelas';

        return 'Laporan-Biodata-Siswa-' . trim($bagian, '-') . '-' . date('Ymd');
    }

    private static function lembarRekap(Worksheet $sh, array $kelas, array $setting): void
    {
        $akhir = 'J';
        self::judul($sh, $akhir, [
            'LAPORAN KELENGKAPAN BIODATA SISWA',
            'TAHUN PELAJARAN ' . strtoupper((string) ($setting['academic_year'] ?? '')),
            'Keadaan per ' . AbsensiLaporan::tanggalCetak() . ' pukul ' . date('H.i'),
        ]);

        $h = 5;
        $sh->fromArray([
            'No', 'Kelas', 'Wali Kelas', 'Jumlah Siswa', 'Data Lengkap', 'Belum Lengkap', '% Lengkap',
            'Belum Mengisi Form', 'Menunggu Verifikasi', 'Perlu Perbaikan',
        ], null, "A{$h}");

        $r   = $h + 1;
        $tot = ['total' => 0, 'lengkap' => 0, 'belum' => 0, 'tanpa_isian' => 0, 'menunggu' => 0, 'perbaikan' => 0];
        foreach ($kelas as $i => $k) {
            $sh->fromArray([
                $i + 1, $k['nama'], $k['wali'] !== '' ? $k['wali'] : '—', $k['total'], $k['lengkap'], $k['belum'],
                self::rasio($k['lengkap'], $k['total']), $k['tanpa_isian'], $k['menunggu'], $k['perbaikan'],
            ], null, "A{$r}", true);
            foreach ($tot as $kunci => $n) {
                $tot[$kunci] = $n + $k[$kunci];
            }
            $r++;
        }
        if ($kelas === []) {
            $sh->mergeCells("A{$r}:{$akhir}{$r}")->setCellValue("A{$r}", 'Tidak ada siswa aktif.');
            $r++;
        }

        $sh->mergeCells("A{$r}:C{$r}")->setCellValue("A{$r}", 'JUMLAH');
        $sh->fromArray([
            $tot['total'], $tot['lengkap'], $tot['belum'], self::rasio($tot['lengkap'], $tot['total']),
            $tot['tanpa_isian'], $tot['menunggu'], $tot['perbaikan'],
        ], null, "D{$r}", true);
        $rTotal = $r;

        self::gayaTabel($sh, $h, $rTotal, $akhir);
        $sh->getStyle("A{$rTotal}:{$akhir}{$rTotal}")->getFont()->setBold(true);
        $sh->getStyle("A{$rTotal}:{$akhir}{$rTotal}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_TOTAL);
        $sh->getStyle("A{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('A' . ($h + 1) . ":A{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('D' . ($h + 1) . ":{$akhir}{$rTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('G' . ($h + 1) . ":G{$rTotal}")->getNumberFormat()->setFormatCode('0%');
        foreach (['A' => 5, 'B' => 14, 'C' => 30, 'D' => 10, 'E' => 10, 'F' => 10, 'G' => 10, 'H' => 12, 'I' => 12, 'J' => 11] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        $n = $rTotal + 2;
        $sh->mergeCells("A{$n}:{$akhir}{$n}")->setCellValue(
            "A{$n}",
            'Data lengkap = seluruh ' . count(BiodataForm::WAJIB) . ' kolom wajib biodata (sesuai form isian siswa) sudah tercatat di Master Siswa.'
        );
        $sh->getStyle("A{$n}")->getFont()->setItalic(true)->setSize(8);

        self::tandaTangan($sh, $n + 2, [['G', 'J', 'Mengetahui,', 'Kepala Sekolah', (string) ($setting['headmaster_name'] ?? ''), (string) ($setting['headmaster_nip'] ?? '')]], $setting);
        self::selesai($sh, $akhir, $h);
    }

    private static function lembarKelas(Worksheet $sh, array $k, array $setting): void
    {
        $akhir = 'H';
        self::judul($sh, $akhir, [
            'DAFTAR KELENGKAPAN BIODATA SISWA',
            // Nama wali TIDAK dibesarkan: gelar seperti "S.Pd" rusak jadi "S.PD".
            'KELAS ' . strtoupper($k['nama']) . ($k['wali'] !== '' ? ' — Wali Kelas: ' . $k['wali'] : ''),
            'Tahun Pelajaran ' . ($setting['academic_year'] ?? '') . ' · keadaan per ' . AbsensiLaporan::tanggalCetak()
                . ' · Jumlah siswa ' . $k['total'] . ', data lengkap ' . $k['lengkap'] . ', belum lengkap ' . $k['belum'],
        ]);

        $h = 5;
        $sh->fromArray(['No', 'NIS', 'NISN', 'Nama Siswa', 'L/P', 'Status Isian', 'Kelengkapan', 'Keterangan'], null, "A{$h}");

        $r = $h + 1;
        foreach ($k['siswa'] as $i => $s) {
            $sh->setCellValue("A{$r}", $i + 1);
            // NIS/NISN sebagai teks: nol di depan NISN tidak boleh hilang.
            $sh->setCellValueExplicit("B{$r}", $s['nis'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if ($s['nisn'] !== '') {
                $sh->setCellValueExplicit("C{$r}", $s['nisn'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sh->setCellValue("D{$r}", $s['nama']);
            $sh->setCellValue("E{$r}", $s['jk']);
            $sh->setCellValue("F{$r}", self::STATUS_ISIAN[$s['isian']] ?? 'Belum mengisi');
            $sh->setCellValue("G{$r}", $s['lengkap'] ? 'Lengkap' : 'Belum lengkap');
            $sh->getStyle("G{$r}")->getFont()->setBold(true)->getColor()->setRGB($s['lengkap'] ? self::WARNA_LENGKAP : self::WARNA_BELUM);
            $sh->setCellValue("H{$r}", $s['ket']);
            $r++;
        }
        $rAkhir = $r - 1;

        self::gayaTabel($sh, $h, max($rAkhir, $h), $akhir);
        $sh->getStyle('A' . ($h + 1) . ":A{$rAkhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('E' . ($h + 1) . ":E{$rAkhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('G' . ($h + 1) . ":G{$rAkhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('H' . ($h + 1) . ":H{$rAkhir}")->getAlignment()->setWrapText(true);
        $sh->getStyle('A' . ($h + 1) . ":{$akhir}{$rAkhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        foreach (['A' => 5, 'B' => 12, 'C' => 13, 'D' => 32, 'E' => 5, 'F' => 19, 'G' => 14, 'H' => 52] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        self::tandaTangan($sh, $rAkhir + 2, [
            ['B', 'D', 'Mengetahui,', 'Kepala Sekolah', (string) ($setting['headmaster_name'] ?? ''), (string) ($setting['headmaster_nip'] ?? '')],
            ['F', 'H', '', 'Wali Kelas', $k['wali'], ''],
        ], $setting);
        self::selesai($sh, $akhir, $h);
    }

    // ===================== Pembantu tata letak =====================

    /** Tiga baris judul (baris 1–3), baris 4 kosong. KOP disisipkan belakangan. */
    private static function judul(Worksheet $sh, string $akhir, array $baris): void
    {
        foreach ($baris as $i => $teks) {
            $n = $i + 1;
            $sh->mergeCells("A{$n}:{$akhir}{$n}")->setCellValue("A{$n}", $teks);
            $sh->getStyle("A{$n}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        }
        $sh->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
        $sh->getStyle('A3')->getFont()->setSize(9);
    }

    private static function gayaTabel(Worksheet $sh, int $h, int $rAkhir, string $akhir): void
    {
        $kepala = $sh->getStyle("A{$h}:{$akhir}{$h}");
        $kepala->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $kepala->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_JUDUL);
        $kepala->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sh->getRowDimension($h)->setRowHeight(28);
        $sh->getStyle("A{$h}:{$akhir}{$rAkhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Blok tanda tangan. Tiap blok: [kolomDari, kolomSampai, baris1, jabatan, nama, nip].
     * Baris tempat & tanggal ditaruh di atas blok paling kanan.
     */
    private static function tandaTangan(Worksheet $sh, int $r, array $blok, array $setting): void
    {
        $kanan = end($blok);
        $sh->mergeCells("{$kanan[0]}{$r}:{$kanan[1]}{$r}")
            ->setCellValue("{$kanan[0]}{$r}", ($setting['city'] ?? '') . ', ' . AbsensiLaporan::tanggalCetak());
        $sh->getStyle("{$kanan[0]}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($blok as [$dari, $sampai, $baris1, $jabatan, $nama, $nip]) {
            // Pengaturan sekolah kadang mengisi NIP dengan "-" → anggap kosong.
            $nip = trim($nip, " -\t");
            $isi = [
                $r + 1 => $baris1,
                $r + 2 => $jabatan,
                $r + 6 => $nama !== '' ? $nama : '(..................................)',
                $r + 7 => $nip !== '' ? 'NIP. ' . $nip : '',
            ];
            foreach ($isi as $i => $teks) {
                $sh->mergeCells("{$dari}{$i}:{$sampai}{$i}")->setCellValue("{$dari}{$i}", $teks);
                $sh->getStyle("{$dari}{$i}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if ($nama !== '') {
                $sh->getStyle("{$dari}" . ($r + 6))->getFont()->setBold(true)->setUnderline(true);
            }
        }
    }

    /** KOP sekolah, pengaturan cetak A4 mendatar, judul kolom berulang tiap halaman. */
    private static function selesai(Worksheet $sh, string $akhir, int $h): void
    {
        kop_excel_prepend($sh, $akhir); // menyisipkan 5 baris di atas
        $hBaru = $h + 5;

        $sh->freezePane('A' . ($hBaru + 1));
        $ps = $sh->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setHorizontalCentered(true);
        $ps->setRowsToRepeatAtTopByStartAndEnd($hBaru, $hBaru);
        $sh->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sh->getHeaderFooter()->setOddFooter('&L&8' . $sh->getTitle() . '&R&8Halaman &P dari &N');
    }

    /** Nama lembar Excel yang sah (≤31 huruf, tanpa \ / ? * [ ] :). */
    private static function judulLembar(string $nama): string
    {
        $bersih = trim(preg_replace('/[\\\\\/?*\[\]:]+/', '-', $nama) ?? '');

        return mb_substr($bersih !== '' ? $bersih : 'Kelas', 0, 31);
    }

    /**
     * Rasio 0–1 dibulatkan ke BAWAH ke 1% (99,9% tidak pernah tercetak 100%).
     * Sudah ada yang lengkap tapi belum 1% → teks "<1%", bukan "0%" yang
     * menyesatkan (sama dengan tampilan di halaman admin).
     */
    private static function rasio(int $bagian, int $total): float|string
    {
        if ($total <= 0) {
            return 0.0;
        }
        $rasio = floor($bagian * 100 / $total) / 100;

        return ($rasio === 0.0 && $bagian > 0) ? '<1%' : $rasio;
    }
}
