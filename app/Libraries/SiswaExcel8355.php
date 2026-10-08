<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Daftar Nama Siswa Kelas X, XI, XII — berkas Excel dengan bentuk PERSIS berkas resmi sekolah (Format 8355).
 * Bentuk diambil dari formatdatasekolah/Data Siswa.xlsx (rancangan: docs/RENCANA-DATA-SISWA-8355.md, F5):
 *
 *   satu lembar "8355", tiga BLOK (satu per jurusan: TKJ, AKL, Manajemen Perkantoran), nomor urut berlanjut antarblok;
 *   tiap blok: judul 2 baris · info sekolah (Nama, Alamat, Telpon, Kode Pos, Akreditasi, Konsentrasi Keahlian) ·
 *   header tabel 2 baris (13 kolom A–M) · baris bagian "Kelas X/XI/XII <konsentrasi>" · siswa urut nama ·
 *   tanda tangan "Mengetahui, Pengawas Pembina" (kiri) & "Kepala <sekolah>" (kanan);
 *   tabel ringkasan L/P/Total per kelas di kolom Q–U (rumus COUNTIF); cetak landscape skala 78 %, baris 11–12 diulang.
 *
 * Hanya siswa berstatus AKTIF. Kolom diambil dari Master Siswa; siswa yang belum punya kelas ditempatkan menurut
 * tingkat+jurusan resmi yang dicatat F2 di kolom keterangan; bila itu pun tak ada → blok "Belum ada kelas/jurusan" di akhir.
 */
final class SiswaExcel8355
{
    /** Urutan blok + nama konsentrasi keahlian seperti di berkas sekolah. */
    public const KONSENTRASI = [
        'TKJ' => 'TEKNIK KOMPUTER DAN JARINGAN',
        'AKL' => 'AKUNTANSI',
        'MP'  => 'Manajemen Perkantoran',
    ];

    /** Singkatan di tabel ringkasan. */
    private const RINGKAS = ['TKJ' => 'TKJ', 'AKL' => 'AKL', 'MP' => 'MPLB'];

    private const TINGKAT = ['X', 'XI', 'XII'];

    /** Awalan catatan otomatis F2 pada siswa baru tanpa kelas (bukan keterangan resmi → tidak dicetak). */
    private const CATATAN_OTOMATIS = 'Perlu penempatan kelas';

    public const SQL = 'SELECT s.id, s.nis, s.nisn, s.nama, s.jenis_kelamin, s.tempat_lahir, s.tanggal_lahir, s.agama, s.nama_orang_tua, s.ortu_alamat,'
        . ' s.sttb_nomor, s.sttb_tahun, s.keterangan, s.kelas_id, k.nama_kelas, k.tingkat, j.kode AS jur_kode, j.nama AS jur_nama'
        . ' FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id LEFT JOIN jurusan j ON j.id = k.jurusan_id'
        . " WHERE s.deleted_at IS NULL AND s.status = 'aktif' ORDER BY s.id";

    /** @return list<array<string, mixed>> siswa aktif beserta kelas/jurusan */
    public static function ambil(BaseConnection $db): array
    {
        return $db->query(self::SQL)->getResultArray();
    }

    /**
     * Kelompokkan siswa → [kunci jurusan => [tingkat => baris[]]] (kunci jurusan: TKJ|AKL|MP|LAIN; tingkat: X|XI|XII|?).
     *
     * @param list<array<string, mixed>> $siswa
     *
     * @return array{blok: array<string, array<string, list<array<string, mixed>>>>, rombel: array<string, int>}
     */
    public static function kelompokkan(array $siswa): array
    {
        $blok   = [];
        $rombel = [];
        foreach ($siswa as $d) {
            [$tingkat, $jur] = self::tempat($d);
            $d['n']                  = SiswaResmi8355::kunciNama((string) $d['nama']);
            $blok[$jur][$tingkat][]  = $d;
            if ($d['kelas_id'] !== null) {
                $rombel[$jur . '|' . $tingkat][(int) $d['kelas_id']] = true;
            }
        }
        foreach ($blok as &$perTingkat) {
            foreach ($perTingkat as &$daftar) {
                usort($daftar, static fn (array $a, array $b) => [$a['n'], (string) $a['nis']] <=> [$b['n'], (string) $b['nis']]);
            }
        }
        unset($perTingkat, $daftar);

        // Urutan blok: TKJ, AKL, MP, lalu sisanya (LAIN).
        $urut = [];
        foreach ([...array_keys(self::KONSENTRASI), 'LAIN'] as $k) {
            if (isset($blok[$k])) {
                $urut[$k] = $blok[$k];
            }
        }

        return ['blok' => $urut, 'rombel' => array_map('count', $rombel)];
    }

    /** @param array<string, mixed> $d @return array{string, string} [tingkat, kunci jurusan] */
    private static function tempat(array $d): array
    {
        $tingkat = self::tingkat((string) ($d['tingkat'] ?? ''));
        $teks    = strtoupper(trim(($d['jur_kode'] ?? '') . ' ' . ($d['jur_nama'] ?? '')));
        $jur     = str_contains($teks, 'TJKT') ? 'TKJ' : SiswaResmi8355::jurusanDb($d['jur_kode'] ?? null, $d['jur_nama'] ?? null);

        // Belum punya kelas → pakai catatan resmi F2: "Perlu penempatan kelas (data resmi sekolah: X TKJ)".
        if (($tingkat === '?' || $jur === '') && preg_match('/data resmi sekolah:\s*(XII|XI|X)\s+(TKJ|AKL|MP)/i', (string) ($d['keterangan'] ?? ''), $m)) {
            $tingkat = $tingkat === '?' ? strtoupper($m[1]) : $tingkat;
            $jur     = $jur === '' ? strtoupper($m[2]) : $jur;
        }

        return [$tingkat, isset(self::KONSENTRASI[$jur]) ? $jur : 'LAIN'];
    }

    private static function tingkat(string $t): string
    {
        $t = strtoupper(trim($t));

        return match ($t) {
            '10', 'X' => 'X', '11', 'XI' => 'XI', '12', 'XII' => 'XII', default => '?',
        };
    }

    /**
     * @param array{blok: array<string, array<string, list<array<string, mixed>>>>, rombel: array<string, int>} $kel
     * @param array<string, mixed>                                                                              $setting
     */
    public static function buat(array $kel, array $setting): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('8355');
        foreach (['A' => 6.18, 'B' => 10.82, 'C' => 11.82, 'D' => 25.82, 'E' => 3.82, 'F' => 11.82, 'G' => 15.54, 'H' => 7.82, 'I' => 24.82, 'J' => 31.82, 'K' => 28.54, 'L' => 6.82, 'M' => 12.45, 'Q' => 12, 'R' => 10, 'S' => 6, 'T' => 6, 'U' => 9] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }

        $tahunAjaran = trim((string) ($setting['academic_year'] ?? ''));
        $judul       = 'DAFTAR NAMA SISWA KELAS X, XI, XII TAHUN PELAJARAN ' . ($tahunAjaran !== '' ? $tahunAjaran : '....');
        $kota        = trim((string) ($setting['city'] ?? '')) ?: 'Bekasi';
        $sekolah     = trim((string) ($setting['school_name'] ?? ''));
        $tgl         = $kota . ', ' . SiswaDetail::tanggal(date('Y-m-d'));
        $info        = [
            ['Nama Sekolah', $sekolah],
            ['Alamat', trim((string) ($setting['address'] ?? ''))],
            ['No. Telpon', trim((string) ($setting['phone'] ?? ''))],
            ['Kode Pos', trim((string) ($setting['school_postal_code'] ?? ''))],
            ['Status Akreditasi', trim((string) ($setting['school_accreditation'] ?? ''))],
        ];
        $ttd = [
            'kiri'  => [trim((string) ($setting['supervisor_name'] ?? '')), trim((string) ($setting['supervisor_nip'] ?? ''))],
            'kanan' => [IsianBantu::rapikanGelar((string) ($setting['headmaster_name'] ?? '')), trim((string) ($setting['headmaster_nip'] ?? ''))],
            'jabatan' => 'Kepala ' . self::judulSekolah($sekolah),
        ];

        $r      = 1;
        $no     = 0;
        $rentang = []; // 'JUR|TINGKAT' => [baris awal, baris akhir] (kolom E, untuk rumus L/P)
        $blokKe = 0;
        foreach ($kel['blok'] as $jur => $perTingkat) {
            if ($blokKe > 0) {
                $ws->setBreak('A' . ($r - 1), Worksheet::BREAK_ROW); // tiap jurusan mulai di halaman baru
            }
            $konsentrasi = self::KONSENTRASI[$jur] ?? 'Belum ada kelas / jurusan';

            // --- judul
            foreach ([[$r, $judul], [$r + 1, '(Format 8355)']] as [$baris, $teks]) {
                $ws->setCellValue('A' . $baris, $teks);
                $ws->mergeCells("A{$baris}:M{$baris}");
                $ws->getRowDimension($baris)->setRowHeight(15.5);
            }
            $ws->getStyle("A{$r}:M" . ($r + 1))->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);

            // --- info sekolah (baris r+3 … r+8)
            $baris = $r + 3;
            foreach ([...$info, ['Konsentrasi Keahlian', $konsentrasi]] as [$label, $nilai]) {
                $ws->setCellValue('A' . $baris, $label);
                $ws->setCellValue('D' . $baris, ': ' . $nilai);
                $ws->getRowDimension($baris)->setRowHeight(15.5);
                $baris++;
            }
            $ws->getStyle('A' . ($r + 3) . ':M' . ($r + 8))->applyFromArray(['font' => ['size' => 12], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);

            // --- header tabel (2 baris)
            $h1 = $r + 10;
            $h2 = $h1 + 1;
            foreach (['No.  ', 'NIS', 'NISN', 'Nama Siswa', 'L/P', 'Tempat Lahir', 'Tanggal/Tahun Lahir', 'Agama', 'Nama Orang Tua', 'Alamat Orang Tua / Wali', 'STTB Setingkat lebih rendah'] as $i => $teks) {
                $kol = chr(ord('A') + $i);
                $ws->setCellValue($kol . $h1, $teks);
                if ($kol !== 'K') {
                    $ws->mergeCells("{$kol}{$h1}:{$kol}{$h2}");
                }
            }
            $ws->mergeCells("K{$h1}:L{$h1}");
            $ws->setCellValue('K' . $h2, 'Nomor');
            $ws->setCellValue('L' . $h2, 'Tahun');
            $ws->setCellValue('M' . $h1, 'Keterangan');
            $ws->mergeCells("M{$h1}:M{$h2}");
            $ws->getRowDimension($h1)->setRowHeight(15);
            $ws->getStyle("A{$h1}:M{$h2}")->applyFromArray(self::garis() + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);

            // --- bagian per tingkat + siswa
            $baris = $h2 + 1;
            $urutTingkat = [...self::TINGKAT, '?'];
            foreach ($urutTingkat as $tingkat) {
                $daftar = $perTingkat[$tingkat] ?? [];
                if ($daftar === []) {
                    continue;
                }
                $ws->setCellValue('A' . $baris, match (true) {
                    $jur === 'LAIN' && $tingkat === '?' => 'Belum ada kelas / jurusan',
                    $jur === 'LAIN'                     => 'Kelas ' . $tingkat . ' (jurusan belum ditentukan)',
                    $tingkat === '?'                    => 'Tingkat belum ditentukan ' . $konsentrasi,
                    default                             => 'Kelas ' . $tingkat . ' ' . $konsentrasi,
                });
                $ws->mergeCells("A{$baris}:M{$baris}");
                $ws->getStyle("A{$baris}:M{$baris}")->applyFromArray(self::garis() + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);
                $ws->getRowDimension($baris)->setRowHeight(30);
                $baris++;

                $awal = $baris;
                foreach ($daftar as $d) {
                    $no++;
                    self::tulisSiswa($ws, $baris, $no, $d);
                    $ws->getRowDimension($baris)->setRowHeight(30);
                    $baris++;
                }
                $akhir = $baris - 1;
                $ws->getStyle("A{$awal}:M{$akhir}")->applyFromArray(self::garis() + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);
                foreach (['A', 'E', 'L'] as $tengah) {
                    $ws->getStyle("{$tengah}{$awal}:{$tengah}{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $rentang[$jur . '|' . $tingkat] = [$awal, $akhir];
            }

            // --- tanda tangan (dimulai 1 baris kosong setelah siswa terakhir)
            $t = $baris + 1;
            $ws->setCellValue('C' . $t, 'Mengetahui,');
            $ws->setCellValue('K' . $t, $tgl);
            $ws->setCellValue('C' . ($t + 1), 'Pengawas Pembina');
            $ws->setCellValue('K' . ($t + 1), $ttd['jabatan']);
            $ws->setCellValue('C' . ($t + 5), $ttd['kiri'][0] !== '' ? $ttd['kiri'][0] : '...............................');
            $ws->setCellValue('K' . ($t + 5), $ttd['kanan'][0] !== '' ? $ttd['kanan'][0] : '...............................');
            $ws->setCellValue('C' . ($t + 6), 'NIP. ' . ($ttd['kiri'][1] !== '' ? $ttd['kiri'][1] : '-'));
            $ws->setCellValue('K' . ($t + 6), 'NIP. ' . ($ttd['kanan'][1] !== '' ? $ttd['kanan'][1] : '-'));
            for ($i = $t; $i <= $t + 6; $i++) {
                $ws->getRowDimension($i)->setRowHeight(15.5);
            }
            $ws->getStyle("A{$t}:M" . ($t + 6))->getFont()->setSize(12);
            $ws->getStyle("C{$t}:C" . ($t + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $r = $t + 6 + 5; // 4 baris kosong lalu judul blok berikutnya
            $blokKe++;
        }

        self::ringkasan($ws, $kel['rombel'], $rentang);

        // --- cetak: landscape, F4/Folio, skala 78 %, baris 11–12 diulang, tengah halaman
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setScale(78);
        $ps->setRowsToRepeatAtTopByStartAndEnd(11, 12);
        $ps->setHorizontalCentered(true);
        $ps->setPrintArea('A1:M' . max(1, $r - 5)); // sampai baris NIP tanda tangan terakhir (tabel ringkasan Q–U tidak ikut tercetak)
        $ws->getPageMargins()->setTop(0.98425196850394)->setBottom(0.59055118110236)->setLeft(0.39370078740157)->setRight(0.39370078740157)->setHeader(0.51181102362205)->setFooter(0.51181102362205);
        $ws->getSheetView()->setZoomScale(85);
        $ss->getProperties()->setTitle('Daftar Nama Siswa (Format 8355)')->setCreator($sekolah !== '' ? $sekolah : 'Sekolah');

        return $ss;
    }

    /** Baris siswa: A No · B NIS · C NISN · D Nama · E L/P · F Tempat · G Tanggal · H Agama · I Ortu · J Alamat ortu · K STTB nomor · L STTB tahun · M Keterangan. */
    private static function tulisSiswa(Worksheet $ws, int $r, int $no, array $d): void
    {
        $teks = static function (string $kol, $v) use ($ws, $r): void {
            $v = trim((string) $v);
            if ($v !== '') {
                $ws->setCellValueExplicit($kol . $r, $v, DataType::TYPE_STRING);
            }
        };
        $ws->setCellValue('A' . $r, $no);
        $nis = trim((string) ($d['nis'] ?? ''));
        if ($nis !== '' && ctype_digit($nis) && strlen($nis) <= 12) {
            $ws->setCellValueExplicit('B' . $r, (int) $nis, DataType::TYPE_NUMERIC);
        } else {
            $teks('B', $nis);
        }
        $teks('C', $d['nisn'] ?? '');
        $teks('D', $d['nama'] ?? '');
        $teks('E', $d['jenis_kelamin'] ?? '');
        $teks('F', $d['tempat_lahir'] ?? '');
        $teks('G', $d['tanggal_lahir'] ?? '');
        $teks('H', $d['agama'] ?? '');
        $teks('I', $d['nama_orang_tua'] ?? '');
        $teks('J', $d['ortu_alamat'] ?? '');
        $teks('K', $d['sttb_nomor'] ?? '');
        if (! empty($d['sttb_tahun'])) {
            $ws->setCellValueExplicit('L' . $r, (int) $d['sttb_tahun'], DataType::TYPE_NUMERIC);
        }
        $ket = trim((string) ($d['keterangan'] ?? ''));
        if ($ket !== '' && ! str_starts_with($ket, self::CATATAN_OTOMATIS)) {
            $teks('M', $ket);
        }
    }

    /**
     * Tabel ringkasan di kolom Q–U (mulai baris 12): KELAS · ROMBEL · L · P · TOTAL — rumus COUNTIF atas kolom E tiap bagian,
     * lalu jumlah per tingkat. Rombel dihitung dari sistem (jumlah kelas yang punya siswa aktif).
     *
     * @param array<string, int>                $rombel
     * @param array<string, array{int, int}>    $rentang
     */
    private static function ringkasan(Worksheet $ws, array $rombel, array $rentang): void
    {
        foreach (['KELAS', 'ROMBEL', 'L', 'P', 'TOTAL'] as $i => $teks) {
            $ws->setCellValue(chr(ord('Q') + $i) . '12', $teks);
        }
        $r      = 14;
        $baris  = []; // [tingkat][jur] => nomor baris
        foreach (array_keys(self::KONSENTRASI) as $jur) {
            foreach (self::TINGKAT as $tingkat) {
                $ws->setCellValue('Q' . $r, $tingkat . ' ' . self::RINGKAS[$jur]);
                $ws->setCellValue('R' . $r, $rombel[$jur . '|' . $tingkat] ?? 0);
                [$a, $b] = $rentang[$jur . '|' . $tingkat] ?? [0, 0];
                foreach (['S' => 'L', 'T' => 'P'] as $kol => $jk) {
                    $ws->setCellValue($kol . $r, $a > 0 ? '=COUNTIF($E$' . $a . ':$E$' . $b . ',"' . $jk . '")' : 0);
                }
                $ws->setCellValue('U' . $r, '=SUM(S' . $r . ':T' . $r . ')');
                $baris[$tingkat][] = $r;
                $r++;
            }
        }
        // Baris 24–26: jumlah siswa per tingkat (R = X/XI/XII dari semua jurusan) — seperti berkas sekolah.
        $r = 24;
        foreach (self::TINGKAT as $tingkat) {
            $ws->setCellValue('Q' . $r, $tingkat);
            $ws->setCellValue('R' . $r, '=SUM(' . implode(',', array_map(static fn (int $b) => 'U' . $b, $baris[$tingkat])) . ')');
            $r++;
        }
        $ws->getStyle('Q12:U12')->applyFromArray(['font' => ['bold' => true]]);
        $ws->getStyle('Q12:U22')->applyFromArray(self::garis() + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle('Q24:R26')->applyFromArray(self::garis() + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle('Q13:U13')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
        $ws->getStyle('Q12:U12')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /** @return array<string, mixed> */
    private static function garis(): array
    {
        return ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
    }

    /** "SMK BINA NUSA" → "SMK Bina Nusa" (singkatan jenjang tetap kapital). */
    private static function judulSekolah(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return 'Sekolah';
        }

        return (string) preg_replace_callback('/\p{L}+/u', static function (array $m): string {
            $k = mb_strtoupper($m[0]);

            return in_array($k, ['SMK', 'SMA', 'SMP', 'MA', 'MTS', 'SD', 'MI'], true) ? $k : mb_convert_case($m[0], MB_CASE_TITLE);
        }, $s);
    }
}
