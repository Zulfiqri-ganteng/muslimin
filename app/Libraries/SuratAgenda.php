<?php

namespace App\Libraries;

use App\Models\SuratSekolahModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel "Buku Agenda Surat Keluar" dari halaman Daftar Surat (Surat Sekolah L7).
 * Baris = hasil SuratSekolah::daftar() dengan saringan yang sedang tampil (tanpa halaman).
 * Semua isian ditulis sebagai TEKS kecuali No & jumlah siswa (nomor surat tidak boleh berubah jadi angka/tanggal).
 */
final class SuratAgenda
{
    private const JUDUL = [
        'No', 'Nomor Surat', 'Tanggal Surat', 'Jenis Surat', 'Perihal', 'Tujuan / Perusahaan', 'Jml Siswa',
        'Status', 'Disetujui oleh', 'Waktu ACC', 'Kode ACC', 'Dibuat oleh', 'Dibuat pada', 'Diunduh (kali)',
    ];

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $setting SettingModel::get()
     *
     * @return string biner .xlsx
     */
    public function excel(array $rows, string $teksSaringan, array $setting): string
    {
        helper('kop');
        $x = new Spreadsheet();
        $x->getProperties()->setTitle('Buku Agenda Surat Keluar')->setCreator('Sistem Informasi Akademik Sekolah');
        $ws    = $x->getActiveSheet()->setTitle('Agenda Surat');
        $akhir = 'N';

        $ws->setCellValue('A1', 'BUKU AGENDA SURAT KELUAR — ' . mb_strtoupper((string) ($setting['school_name'] ?? '')));
        $ws->setCellValue('A2', 'Dicetak ' . date('d-m-Y H:i') . ' · ' . count($rows) . ' surat' . ($teksSaringan !== '' ? ' · ' . $teksSaringan : ''));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $ws->fromArray(self::JUDUL, null, 'A4');
        $ws->getStyle('A4:' . $akhir . '4')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A6B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $b = 5;
        foreach ($rows as $i => $r) {
            $acc = (string) ($r['acc_nama'] ?? '');
            if ($acc !== '' && (string) ($r['acc_peran'] ?? '') !== '') {
                $acc .= ' (' . HakAkses::label((string) $r['acc_peran']) . ')';
            }
            $ws->setCellValue('A' . $b, $i + 1);
            $isi = [
                'B' => (string) ($r['nomor'] ?? '') ?: '-',
                'C' => self::tgl($r['tanggal_surat'] ?? null),
                'D' => SuratJenis::label((string) $r['jenis']),
                'E' => (string) ($r['judul'] ?? ''),
                'F' => (string) ($r['perusahaan_nama'] ?? ''),
                'H' => SuratSekolahModel::TAMPIL_STATUS[$r['status']][0] ?? (string) $r['status'],
                'I' => $acc,
                'J' => self::tgl($r['acc_at'] ?? null, true),
                'K' => (string) ($r['acc_kode'] ?? ''),
                'L' => (string) ($r['dibuat_nama'] ?? ''),
                'M' => self::tgl($r['created_at'] ?? null, true),
            ];
            foreach ($isi as $kol => $v) {
                if ($v !== '') {
                    $ws->setCellValueExplicit($kol . $b, $v, DataType::TYPE_STRING);
                }
            }
            $ws->setCellValue('G' . $b, (int) ($r['jml_siswa'] ?? 0));
            $ws->setCellValue('N' . $b, (int) ($r['cetak_ke'] ?? 0));
            $b++;
        }

        $ujung = max(5, $b - 1);
        $ws->getStyle('A4:' . $akhir . $ujung)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('A5:' . $akhir . $ujung)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $ws->getStyle('A5:A' . $ujung)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('G5:G' . $ujung)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('N5:N' . $ujung)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 5, 'B' => 24, 'C' => 12, 'D' => 22, 'E' => 40, 'F' => 30, 'G' => 8, 'H' => 14, 'I' => 24, 'J' => 16, 'K' => 20, 'L' => 20, 'M' => 16, 'N' => 9] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        $ws->freezePane('C5');
        $ws->setAutoFilter('A4:' . $akhir . '4');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        kop_excel_prepend($ws, $akhir);

        ob_start();
        (new Xlsx($x))->save('php://output');

        return (string) ob_get_clean();
    }

    /** "2026-10-12" / "2026-10-12 08:30:00" → "12-10-2026" (+ " 08:30"). Kosong → "". */
    private static function tgl(?string $v, bool $jam = false): string
    {
        if ($v === null || $v === '' || str_starts_with($v, '0000')) {
            return '';
        }
        $t = strtotime($v);

        return $t ? date($jam ? 'd-m-Y H:i' : 'd-m-Y', $t) : '';
    }
}
