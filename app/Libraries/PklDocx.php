<?php

namespace App\Libraries;

/**
 * Pembuat berkas Word (.docx) TANPA pustaka luar — hanya ekstensi zip yang sudah dipakai
 * PhpSpreadsheet. Folder vendor ikut di git dan hosting hanya `git pull`, jadi menambah PhpWord
 * (pustaka besar + dependensinya) sengaja dihindari.
 *
 * Dua jalur:
 *   1. suratBawaan()   — surat permohonan PKL bawaan (kop sekolah, tabel siswa, ruang TTD + stempel).
 *   2. dariTemplate()  — mengisi TEMPLATE Word milik sekolah. Penanda ${nama} diganti nilainya
 *                        (penanda yang terpecah antar-bagian oleh Word dirapikan dulu), dan baris
 *                        tabel yang memuat ${no} / ${siswa_*} digandakan sebanyak siswa.
 * Surat banyak (massal) = badan tiap surat digabung dalam SATU dokumen, dipisah pindah halaman.
 *
 * Urutan elemen XML mengikuti skema WordprocessingML (Word menolak urutan yang salah).
 */
final class PklDocx
{
    private const LEBAR_TEKS = 9355; // A4 11906 − kiri 1417 − kanan 1134 (twip)

    private static int $idGambar = 0;

    // =================================================================
    // Elemen dasar
    // =================================================================

    public static function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param array<string, mixed> $f format: b, i, u, sz (setengah poin) */
    private static function run(string $t, array $f = []): string
    {
        $rpr = '';
        if (! empty($f['b'])) {
            $rpr .= '<w:b/>';
        }
        if (! empty($f['i'])) {
            $rpr .= '<w:i/>';
        }
        if (! empty($f['sz'])) {
            $rpr .= '<w:sz w:val="' . (int) $f['sz'] . '"/><w:szCs w:val="' . (int) $f['sz'] . '"/>';
        }
        if (! empty($f['u'])) {
            $rpr .= '<w:u w:val="single"/>';
        }
        $rpr = $rpr !== '' ? '<w:rPr>' . $rpr . '</w:rPr>' : '';

        $out = '';
        foreach (preg_split('/(\t|\n)/', $t, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $bagian) {
            if ($bagian === "\t") {
                $out .= '<w:r>' . $rpr . '<w:tab/></w:r>';
            } elseif ($bagian === "\n") {
                $out .= '<w:r>' . $rpr . '<w:br/></w:r>';
            } elseif ($bagian !== '') {
                $out .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . self::esc($bagian) . '</w:t></w:r>';
            }
        }

        return $out;
    }

    /**
     * Paragraf. $isi = teks, atau daftar [teks, format].
     * Opsi: jc (left|center|right|both), before/after/line, left/hang/first (indentasi twip),
     *       tabs [[left|right, posisi], …], bd (garis bawah), keep (satu halaman dengan berikutnya).
     *
     * @param string|list<array{0:string, 1?:array}> $isi
     */
    public static function p($isi = '', array $o = []): string
    {
        $ppr = '';
        if (! empty($o['keep'])) {
            $ppr .= '<w:keepNext/>';
        }
        if (! empty($o['bd'])) {
            $ppr .= '<w:pBdr><w:bottom w:val="single" w:sz="12" w:space="1" w:color="000000"/></w:pBdr>';
        }
        if (! empty($o['tabs'])) {
            $ppr .= '<w:tabs>';
            foreach ($o['tabs'] as [$jenis, $pos]) {
                $ppr .= '<w:tab w:val="' . $jenis . '" w:pos="' . (int) $pos . '"/>';
            }
            $ppr .= '</w:tabs>';
        }
        $ppr .= '<w:spacing w:before="' . (int) ($o['before'] ?? 0) . '" w:after="' . (int) ($o['after'] ?? 0)
            . '" w:line="' . (int) ($o['line'] ?? 276) . '" w:lineRule="auto"/>';
        if (isset($o['left']) || isset($o['first'])) {
            $ppr .= '<w:ind w:left="' . (int) ($o['left'] ?? 0) . '"'
                . (isset($o['hang']) ? ' w:hanging="' . (int) $o['hang'] . '"' : '')
                . (isset($o['first']) ? ' w:firstLine="' . (int) $o['first'] . '"' : '') . '/>';
        }
        if (! empty($o['jc'])) {
            $ppr .= '<w:jc w:val="' . $o['jc'] . '"/>';
        }

        if (is_string($isi)) {
            $isi = [[$isi, []]];
        }
        $runs = '';
        foreach ($isi as $bagian) {
            $runs .= is_string($bagian) ? self::run($bagian) : self::run((string) $bagian[0], $bagian[1] ?? []);
        }

        return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $runs . '</w:p>';
    }

    /**
     * Tabel. $baris = daftar baris berisi teks sel. Baris pertama = judul bila $o['judul'].
     * Opsi: jc (rata tiap kolom), tanpaGaris.
     *
     * @param list<list<string>> $baris
     * @param list<int>          $lebar
     */
    public static function tabel(array $baris, array $lebar, array $o = []): string
    {
        $garis = '';
        if (empty($o['tanpaGaris'])) {
            $garis = '<w:tblBorders>';
            foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $sisi) {
                $garis .= '<w:' . $sisi . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
            }
            $garis .= '</w:tblBorders>';
        }
        $x = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($lebar) . '" w:type="dxa"/>' . $garis
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="40" w:type="dxa"/><w:left w:w="80" w:type="dxa"/>'
            . '<w:bottom w:w="40" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($lebar as $w) {
            $x .= '<w:gridCol w:w="' . (int) $w . '"/>';
        }
        $x .= '</w:tblGrid>';

        foreach ($baris as $i => $b) {
            $judul = ! empty($o['judul']) && $i === 0;
            $x    .= '<w:tr>' . ($judul ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            foreach ($b as $k => $isi) {
                $x .= '<w:tc><w:tcPr><w:tcW w:w="' . (int) $lebar[$k] . '" w:type="dxa"/>'
                    . ($judul ? '<w:shd w:val="clear" w:color="auto" w:fill="E7E6E6"/>' : '') . '<w:vAlign w:val="center"/></w:tcPr>'
                    . (is_array($isi) ? $isi[0] : self::p($isi === '' ? '' : [[$isi, $judul ? ['b' => true] : []]], ['line' => 240, 'jc' => $judul ? 'center' : ($o['jc'][$k] ?? 'left')]))
                    . '</w:tc>';
            }
            $x .= '</w:tr>';
        }

        return $x . '</w:tbl>';
    }

    private static function gambar(int $cx, int $cy): string
    {
        $id = ++self::$idGambar;

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $id . '" name="Logo ' . $id . '"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="logo"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    // =================================================================
    // Surat bawaan
    // =================================================================

    /** Info logo dari berkas gambar (png/jpg) atau null. @return array{path:string, ext:string, w:int, h:int}|null */
    public static function infoLogo(?string $path): ?array
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $uk  = @getimagesize($path);
        if (! in_array($ext, ['png', 'jpg'], true) || ! $uk || $uk[0] < 1 || $uk[1] < 1) {
            return null;
        }

        return ['path' => $path, 'ext' => $ext, 'w' => (int) $uk[0], 'h' => (int) $uk[1]];
    }

    private static function kop(array $v, ?array $logo): string
    {
        $kontak = [];
        if ($v['sekolah_telepon'] !== '') {
            $kontak[] = 'Telp. ' . $v['sekolah_telepon'];
        }
        if ($v['sekolah_email'] !== '') {
            $kontak[] = 'Email: ' . $v['sekolah_email'];
        }

        $teks = self::p([[mb_strtoupper((string) $v['sekolah']), ['b' => true, 'sz' => 28]]], ['jc' => 'center', 'line' => 240]);
        foreach (array_filter([(string) $v['sekolah_alamat'], implode('  |  ', $kontak)]) as $baris) {
            $teks .= self::p([[$baris, ['sz' => 20]]], ['jc' => 'center', 'line' => 240]);
        }

        if ($logo !== null) {
            $cy   = 720000; // tinggi 2 cm
            $cx   = (int) round($cy * $logo['w'] / $logo['h']);
            $kiri = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>' . self::gambar($cx, $cy) . '</w:p>';
            $kop  = self::tabel([[[$kiri], [$teks]]], [1500, self::LEBAR_TEKS - 1500], ['tanpaGaris' => true]);
        } else {
            $kop = $teks;
        }

        return $kop . self::p('', ['bd' => true, 'line' => 100, 'after' => 160]);
    }

    /**
     * Badan satu surat permohonan PKL (tanpa pembuka/penutup dokumen).
     *
     * @param array<string, string>       $v     penanda skalar (lihat PklSurat::TOKEN)
     * @param list<array<string, string>> $siswa baris: no, nama, nisn, kelas, jurusan
     */
    public static function suratBawaan(array $v, array $siswa, ?array $logo): string
    {
        $tabs = [['left', 1500], ['right', self::LEBAR_TEKS]];
        $x    = self::kop($v, $logo);
        $x   .= self::p("Nomor\t: " . $v['nomor'] . "\t" . $v['kota_surat'] . ', ' . $v['tanggal'], ['tabs' => $tabs]);
        $x   .= self::p("Lampiran\t: -", ['tabs' => $tabs]);
        $x   .= self::p([["Perihal\t: ", []], ['Permohonan Praktik Kerja Lapangan (PKL)', ['b' => true]]], ['tabs' => $tabs, 'after' => 240]);

        $x .= self::p('Yth. ' . $v['penerima'], ['line' => 240]);
        $x .= self::p([[$v['perusahaan'], ['b' => true]]], ['line' => 240]);
        if ($v['alamat_lengkap'] !== '') {
            $x .= self::p($v['alamat_lengkap'], ['line' => 240]);
        }
        $x .= self::p('', ['after' => 120]);

        $x .= self::p('Dengan hormat,', ['after' => 120]);
        $x .= self::p('Sehubungan dengan program Praktik Kerja Lapangan (PKL) bagi peserta didik ' . $v['sekolah']
            . ($v['tahun_ajaran'] !== '' ? ' Tahun Pelajaran ' . $v['tahun_ajaran'] : '')
            . ', dengan ini kami mengajukan permohonan agar peserta didik berikut dapat melaksanakan PKL di perusahaan/instansi yang Bapak/Ibu pimpin:',
            ['jc' => 'both', 'first' => 720, 'after' => 120]);

        $baris = [['No', 'Nama', 'NISN', 'Kelas', 'Kompetensi Keahlian']];
        foreach ($siswa as $s) {
            $baris[] = [$s['no'], $s['nama'], $s['nisn'], $s['kelas'], $s['jurusan']];
        }
        $x .= self::tabel($baris, [600, 3300, 1500, 1455, 2500], ['judul' => true, 'jc' => ['center', 'left', 'center', 'center', 'left']]);

        $x .= self::p('Pelaksanaan PKL direncanakan mulai tanggal ' . $v['mulai'] . ' sampai dengan ' . $v['selesai'] . ' (' . $v['lama'] . ').',
            ['jc' => 'both', 'first' => 720, 'before' => 160, 'after' => 120]);
        $x .= self::p('Demikian permohonan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.',
            ['jc' => 'both', 'first' => 720, 'after' => 240]);

        // Tanda tangan: ruang KOSONG untuk tanda tangan + stempel basah (tanpa gambar).
        $kiri = 5400;
        $x .= self::p('Hormat kami,', ['left' => $kiri, 'line' => 240, 'keep' => true]);
        $x .= self::p($v['waka_jabatan'], ['left' => $kiri, 'line' => 240, 'keep' => true]);
        for ($i = 0; $i < 4; $i++) {
            $x .= self::p('', ['left' => $kiri, 'keep' => true]);
        }
        $x .= self::p([[$v['waka_nama'] !== '' ? $v['waka_nama'] : '........................................', ['b' => true, 'u' => true]]], ['left' => $kiri, 'line' => 240, 'keep' => true]);
        $x .= self::p($v['waka_nip'] !== '' ? 'NIP. ' . $v['waka_nip'] : '', ['left' => $kiri, 'line' => 240]);

        return $x;
    }

    /** Gabungkan badan surat (satu atau banyak) menjadi dokumen baru. @param list<string> $badan */
    public static function dokumen(array $badan, ?array $logo): string
    {
        $isi = implode('<w:p><w:r><w:br w:type="page"/></w:r></w:p>', $badan);
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . self::NS . '><w:body>' . $isi
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';

        $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . ($logo ? '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.' . $logo['ext'] . '"/>' : '')
            . '</Relationships>';

        $tipe = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/><Default Extension="jpg" ContentType="image/jpeg"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>';

        $bagian = [
            '[Content_Types].xml' => $tipe,
            '_rels/.rels'         => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml'   => $doc,
            'word/_rels/document.xml.rels' => $rel,
            'word/styles.xml'     => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="' . self::W . '"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>',
        ];
        if ($logo) {
            $bagian['word/media/logo.' . $logo['ext']] = (string) file_get_contents($logo['path']);
        }

        return self::zip($bagian);
    }

    private const W  = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    // =================================================================
    // Template Word milik sekolah
    // =================================================================

    /**
     * Isi template untuk banyak surat → biner .docx (satu dokumen, tiap surat di halaman baru).
     *
     * @param list<array{v: array<string,string>, siswa: list<array<string,string>>}> $daftar
     */
    public static function dariTemplate(string $pathTemplate, array $daftar): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($pathTemplate) !== true) {
            throw new \RuntimeException('Template Word tidak bisa dibuka.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new \RuntimeException('Berkas bukan dokumen Word yang sah (word/document.xml tidak ada).');
        }

        if (preg_match('/^(.*?<w:body>)(.*)(<w:sectPr(?:(?!<w:sectPr).)*<\/w:sectPr>\s*)(<\/w:body>.*)$/s', $xml, $m)) {
            [, $awal, $badan, $sect, $akhir] = $m;
        } elseif (preg_match('/^(.*?<w:body>)(.*)(<\/w:body>.*)$/s', $xml, $m)) {
            [, $awal, $badan, $akhir] = $m;
            $sect = '';
        } else {
            throw new \RuntimeException('Struktur dokumen Word tidak dikenali.');
        }

        $badan = self::rapikanPenanda($badan);
        $hasil = [];
        foreach ($daftar as $surat) {
            $hasil[] = self::isiPenanda(self::gandakanBaris($badan, $surat['siswa']), $surat['v']);
        }
        $baru = $awal . implode('<w:p><w:r><w:br w:type="page"/></w:r></w:p>', $hasil) . $sect . $akhir;

        // Salin seluruh isi template, ganti document.xml saja.
        $sumber = new \ZipArchive();
        $sumber->open($pathTemplate);
        $bagian = [];
        for ($i = 0; $i < $sumber->numFiles; $i++) {
            $nama = (string) $sumber->getNameIndex($i);
            if (! str_ends_with($nama, '/')) {
                $bagian[$nama] = (string) $sumber->getFromIndex($i);
            }
        }
        $sumber->close();
        $bagian['word/document.xml'] = $baru;

        return self::zip($bagian);
    }

    /** Nama-nama penanda ${...} yang ada di template (setelah dirapikan). @return list<string> */
    public static function penandaDi(string $pathTemplate): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($pathTemplate) !== true) {
            return [];
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        preg_match_all('/\$\{([a-z0-9_]+)\}/i', self::rapikanPenanda($xml), $m);

        return array_values(array_unique($m[1]));
    }

    /** Word sering memecah "${nomor}" ke beberapa potongan teks — satukan kembali. */
    private static function rapikanPenanda(string $xml): string
    {
        return preg_replace_callback('/\$(?:\{|[^{$]*>\{)[^}$]*\}/U', static fn (array $m) => strip_tags($m[0]), $xml) ?? $xml;
    }

    /** Gandakan baris tabel yang memuat ${no} atau ${siswa_*} sebanyak siswa. */
    private static function gandakanBaris(string $badan, array $siswa): string
    {
        return preg_replace_callback('/<w:tr[ >](?:(?!<w:tr[ >]).)*<\/w:tr>/s', static function (array $m) use ($siswa): string {
            if (! preg_match('/\$\{(no|siswa_[a-z]+)\}/i', $m[0])) {
                return $m[0];
            }
            $hasil = '';
            foreach ($siswa as $s) {
                $hasil .= self::isiPenanda($m[0], [
                    'no' => $s['no'], 'siswa_nama' => $s['nama'], 'siswa_nis' => $s['nis'] ?? '', 'siswa_nisn' => $s['nisn'],
                    'siswa_kelas' => $s['kelas'], 'siswa_jurusan' => $s['jurusan'],
                ]);
            }

            return $hasil;
        }, $badan) ?? $badan;
    }

    private static function isiPenanda(string $xml, array $v): string
    {
        return preg_replace_callback('/\$\{([a-z0-9_]+)\}/i', static function (array $m) use ($v): string {
            if (! array_key_exists($m[1], $v)) {
                return $m[0]; // penanda tak dikenal dibiarkan terlihat agar ketahuan salah ketiknya
            }

            return str_replace("\n", '</w:t><w:br/><w:t xml:space="preserve">', self::esc((string) $v[$m[1]]));
        }, $xml) ?? $xml;
    }

    // =================================================================
    // Zip
    // =================================================================

    /** @param array<string, string> $bagian nama berkas → isi */
    private static function zip(array $bagian): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pkl');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat berkas Word.');
        }
        // [Content_Types].xml harus di urutan pertama agar mudah dikenali Word.
        uksort($bagian, static fn ($a, $b) => ($a === '[Content_Types].xml' ? -1 : ($b === '[Content_Types].xml' ? 1 : 0)));
        foreach ($bagian as $nama => $isi) {
            $zip->addFromString($nama, $isi);
        }
        $zip->close();
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }
}
