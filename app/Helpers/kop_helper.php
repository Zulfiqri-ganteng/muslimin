<?php

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * =====================================================================
 * KOP Helper — kepala surat (letterhead) resmi sekolah untuk SEMUA
 * keluaran PDF & Excel: logo + nama sekolah + alamat + telp/email/web.
 * Data diambil dari Pengaturan Sekolah (SettingModel, sudah dicache).
 * =====================================================================
 * Dimuat global lewat app/Config/Autoload.php ($helpers = ['cache','kop']).
 *
 * Pakai:
 *  - PDF   : <?= kop_pdf() ?> di bagian atas view (sebelum judul dokumen)
 *  - Excel : kop_excel_prepend($sheet, 'H');  ← panggil SETELAH seluruh isi
 *            sheet selesai ditulis, tepat sebelum streamXlsx. Menyisipkan
 *            5 baris KOP di atas; referensi formula ikut bergeser otomatis.
 */

if (! function_exists('kop_setting')) {
    /** Pengaturan sekolah (cache app_setting via SettingModel::get). */
    function kop_setting(): array
    {
        return (new \App\Models\SettingModel())->get();
    }
}

if (! function_exists('kop_lokasi')) {
    /** Gabungan alamat + kota, hindari duplikat kota ("BEKASI, Bekasi"). */
    function kop_lokasi(array $setting): string
    {
        $alamat = trim((string) ($setting['address'] ?? ''));
        $kota   = trim((string) ($setting['city'] ?? ''));

        if ($kota !== '' && stripos($alamat, $kota) === false) {
            return $alamat !== '' ? $alamat . ', ' . $kota : $kota;
        }

        return $alamat;
    }
}

if (! function_exists('kop_kontak')) {
    /** Baris kontak "Telp: … | Email: … | Web: …" (hanya yang terisi). */
    function kop_kontak(array $setting): string
    {
        $kontak = [];
        if (! empty($setting['phone'])) {
            $kontak[] = 'Telp: ' . $setting['phone'];
        }
        if (! empty($setting['email'])) {
            $kontak[] = 'Email: ' . $setting['email'];
        }
        if (! empty($setting['website'])) {
            $kontak[] = 'Web: ' . $setting['website'];
        }

        return implode('  |  ', $kontak);
    }
}

if (! function_exists('kop_logo_base64')) {
    /** Logo sekolah sebagai data URI (agar pasti tampil di Dompdf). */
    function kop_logo_base64(array $setting): ?string
    {
        if (empty($setting['logo'])) {
            return null;
        }
        $path = FCPATH . 'uploads/' . $setting['logo'];
        if (! is_file($path)) {
            return null;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) @file_get_contents($path));
    }
}

if (! function_exists('kop_pdf')) {
    /** HTML kepala surat untuk PDF (Dompdf-safe, style inline). */
    function kop_pdf(): string
    {
        return view('pdf/partials/kop', ['setting' => kop_setting()]);
    }
}

if (! function_exists('kop_excel_prepend')) {
    /**
     * Sisipkan KOP di baris teratas worksheet (nama sekolah, alamat,
     * kontak, garis pemisah dobel, spasi) + logo. Panggil setelah isi
     * sheet lengkap — formula/merge di bawahnya bergeser otomatis.
     *
     * @param Worksheet $sheet   Sheet tujuan
     * @param string    $lastCol Kolom terakhir isi sheet, mis. 'H'
     */
    function kop_excel_prepend(Worksheet $sheet, string $lastCol): void
    {
        $setting = kop_setting();

        $sheet->insertNewRowBefore(1, 5);

        $sheet->mergeCells("A1:{$lastCol}1")->setCellValue('A1', strtoupper((string) ($setting['school_name'] ?? '')));
        $sheet->mergeCells("A2:{$lastCol}2")->setCellValue('A2', kop_lokasi($setting));
        $sheet->mergeCells("A3:{$lastCol}3")->setCellValue('A3', kop_kontak($setting));
        $sheet->mergeCells("A4:{$lastCol}4"); // garis pemisah
        $sheet->mergeCells("A5:{$lastCol}5"); // spasi sebelum isi

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1A3A6B');
        $sheet->getStyle('A2:A3')->getFont()->setSize(9);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A4:{$lastCol}4")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB('1A3A6B');

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(13);
        $sheet->getRowDimension(3)->setRowHeight(13);
        $sheet->getRowDimension(4)->setRowHeight(4);
        $sheet->getRowDimension(5)->setRowHeight(8);

        // Logo di pojok kiri KOP
        if (! empty($setting['logo']) && is_file(FCPATH . 'uploads/' . $setting['logo'])) {
            $logo = new Drawing();
            $logo->setName('Logo Sekolah');
            $logo->setPath(FCPATH . 'uploads/' . $setting['logo']);
            $logo->setHeight(50);
            $logo->setCoordinates('A1');
            $logo->setOffsetX(8);
            $logo->setOffsetY(4);
            $logo->setWorksheet($sheet);
        }
    }
}
