<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Rekap "DATA SISWA PRAKERIN" per kelas dari data sistem + unduhan Excel persis format sekolah: satu lembar per kelas
 * (nama lembar = nama kelas), judul 3 baris, kolom NO, NAMA, Mitra DI/DU, Praktek Kerja Lapangan (Mulai, Selesai),
 * Lamanya PKL, + kolom G Keterangan. Seluruh huruf Times New Roman. Berkas unduhan bisa dilengkapi lalu diimpor balik
 * lewat PklImpor (format sekolah; kolom Keterangan diabaikan).
 *
 * Mitra = perusahaan ajuan aktif siswa; Keterangan menjelaskan posisinya (sudah diterima, belum lapor, belum ACC, …;
 * lihat baris()). Mulai/Selesai = periode di ajuan (diisi Surat Balasan atau impor); bila ajuan belum punya tanggal,
 * periode yang dilaporkan siswa (Lapor Diterima, selama tidak dikembalikan). Lamanya = bulan dari Mulai–Selesai
 * (SuratPkl::lamaBulan). Urutan kelas & siswa = urutan data di sistem (sama dengan daftar sekolah).
 */
final class PklPrakerin
{
    /** Format tanggal sel Mulai/Selesai di berkas sekolah: "28 Juli 2026". */
    private const FORMAT_TANGGAL = '[$-421]dd\ mmmm\ yyyy;@';

    /** Lebar kolom A–F berkas sekolah + G Keterangan (tambahan: siswa yang belum pasti diterima). */
    private const LEBAR = ['A' => 7.5703125, 'B' => 29.42578125, 'C' => 28.7109375, 'D' => 19.85546875, 'E' => 17.5703125, 'F' => 15, 'G' => 55];

    /** Keterangan lebih panjang dari ini dibungkus jadi dua baris. */
    private const KET_SEBARIS = 62;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Kelas aktif + jumlah siswa aktifnya, urut tingkat lalu urutan data.
     *
     * @return list<array{id: int, nama_kelas: string, tingkat: string, jumlah: int}>
     */
    public function kelas(): array
    {
        $rows = $this->db->table('kelas k')->select('k.id, k.nama_kelas, k.tingkat, COUNT(s.id) AS jumlah')
            ->join('siswa s', "s.kelas_id = k.id AND s.status = 'aktif' AND s.deleted_at IS NULL", 'left')
            ->where('k.deleted_at', null)->groupBy('k.id, k.nama_kelas, k.tingkat')
            ->orderBy("FIELD(k.tingkat, 'X', 'XI', 'XII')", '', false)->orderBy('k.id', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama_kelas' => (string) $r['nama_kelas'], 'tingkat' => (string) $r['tingkat'], 'jumlah' => (int) $r['jumlah']], $rows);
    }

    /**
     * Isi rekap untuk kelas terpilih (urutan $kelas dipertahankan). Kelas tanpa siswa aktif tetap tampil (kosong).
     *
     * @param list<array{id: int, nama_kelas: string, tingkat: string}> $kelas
     * @param bool $hanyaDiterima true = hanya siswa yang sudah pasti diterima perusahaan (nomor urut diulang)
     *
     * @return list<array{kelas: array, siswa: list<array{no: int, nama: string, mitra: string, mulai: ?string, selesai: ?string, lama: ?int, tahap: string, catatan: string}>, ringkas: array{siswa: int, diterima: int, tanggal: int, tunggu: int, belum: int}}>
     */
    public function data(array $kelas, bool $hanyaDiterima = false): array
    {
        if ($kelas === []) {
            return [];
        }
        $ids   = array_map(static fn (array $k): int => (int) $k['id'], $kelas);
        $lapor = PklLapor::tabelAda();
        $q     = $this->db->table('siswa s')
            ->select('s.id, s.nama, s.kelas_id, p.status, p.sumber, p.perusahaan_nama, p.tanggal_mulai, p.tanggal_selesai')
            // Tanpa ajuan aktif: ajuan terakhir yang ditolak (bila ada), untuk keterangan.
            ->select("(SELECT p2.perusahaan_nama FROM pkl_anggota a2 JOIN pkl_pengajuan p2 ON p2.id = a2.pengajuan_id WHERE a2.siswa_id = s.id AND p2.status = 'ditolak' ORDER BY p2.id DESC LIMIT 1) AS ditolak_pt", false)
            ->join('pkl_anggota a', 'a.siswa_aktif = s.id', 'left')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id', 'left');
        // Siswa yang dikeluarkan dari kelompok (revisi 2026-10-20): catatan terakhirnya "jenis|waktu|perusahaan", untuk keterangan.
        if (PklKeluar::tabelAda()) {
            $q->select("(SELECT p2.updated_at FROM pkl_anggota a2 JOIN pkl_pengajuan p2 ON p2.id = a2.pengajuan_id WHERE a2.siswa_id = s.id AND p2.status = 'ditolak' ORDER BY p2.id DESC LIMIT 1) AS ditolak_at", false)
                ->select("(SELECT CONCAT(k3.jenis, '|', k3.created_at, '|', p3.perusahaan_nama) FROM pkl_anggota_keluar k3 JOIN pkl_pengajuan p3 ON p3.id = k3.pengajuan_id WHERE k3.siswa_id = s.id ORDER BY k3.id DESC LIMIT 1) AS keluar_info", false);
        }
        if ($lapor) {
            $q->select('l.status AS lapor_status, l.tanggal_mulai AS lapor_mulai, l.tanggal_selesai AS lapor_selesai')
                ->join('pkl_lapor l', 'l.pengajuan_id = p.id', 'left');
        }
        $rows = $q->whereIn('s.kelas_id', $ids)->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->orderBy('s.id', 'ASC')->get()->getResultArray();

        $per = array_fill_keys($ids, []);
        foreach ($rows as $r) {
            $per[(int) $r['kelas_id']][] = self::baris($r);
        }

        $out = [];
        foreach ($kelas as $k) {
            $semua = $per[(int) $k['id']] ?? [];
            $siswa = $hanyaDiterima ? array_values(array_filter($semua, static fn (array $s): bool => $s['tahap'] === 'diterima')) : $semua;
            foreach ($siswa as $i => $s) {
                $siswa[$i]['no'] = $i + 1;
            }
            $out[] = [
                'kelas'   => $k,
                'siswa'   => $siswa,
                'ringkas' => [
                    'siswa'    => count($semua),
                    'diterima' => count(array_filter($semua, static fn (array $s): bool => $s['tahap'] === 'diterima')),
                    'tanggal' => count(array_filter($semua, static fn (array $s): bool => $s['mulai'] !== null)),
                    'tunggu'  => count(array_filter($semua, static fn (array $s): bool => $s['tahap'] === 'proses')),
                    'belum'   => count(array_filter($semua, static fn (array $s): bool => $s['tahap'] === 'belum')),
                ],
            ];
        }

        return $out;
    }

    /**
     * Berkas Excel format sekolah: satu lembar per kelas.
     *
     * @param list<array> $rekap keluaran data()
     */
    public static function excel(array $rekap, string $sekolah, string $tahun): string
    {
        $x = new Spreadsheet();
        $x->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);
        $dipakai = [];
        foreach (array_values($rekap) as $i => $r) {
            $ws = $i === 0 ? $x->getActiveSheet() : $x->createSheet();
            $ws->setTitle(self::judulLembar((string) $r['kelas']['nama_kelas'], $dipakai));
            self::isiLembar($ws, $r['siswa'], $sekolah, $tahun);
        }
        if ($rekap === []) {
            self::isiLembar($x->getActiveSheet()->setTitle('Data Prakerin'), [], $sekolah, $tahun);
        }
        $x->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'pkp');
        (new Xlsx($x))->save($tmp);
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /**
     * Satu siswa → baris rekap. Mitra terisi untuk SEMUA ajuan aktif (menunggu, perbaikan, disetujui); Keterangan
     * selalu menjelaskan posisinya. "Sudah diterima" = ajuan disetujui DAN (Lapor Diterima yang tidak dikembalikan,
     * ATAU periode tercatat di ajuan — dari Surat Balasan, ATAU hasil impor Excel sekolah); ACC Hubin saja belum.
     * Tanggal hanya bila sudah diketahui. 'tahap' = diterima | proses (sudah mengajukan, belum diterima) | belum.
     */
    private static function baris(array $r): array
    {
        $status = (string) ($r['status'] ?? '');
        $aktif  = in_array($status, ['menunggu', 'perbaikan', 'disetujui'], true);
        $lst    = (string) ($r['lapor_status'] ?? '');
        $lapor  = $lst !== '' && $lst !== 'dikembalikan' && ! empty($r['lapor_mulai']);
        $tglAj  = ! empty($r['tanggal_mulai']) || ! empty($r['tanggal_selesai']);
        $pasti  = $status === 'disetujui' && ($lapor || $tglAj || ($r['sumber'] ?? '') === 'impor');
        $mulai  = ($r['tanggal_mulai'] ?? '') ?: null;
        $akhir  = ($r['tanggal_selesai'] ?? '') ?: null;
        if ($mulai === null && $akhir === null && $lapor) {
            $mulai = (string) $r['lapor_mulai'];
            $akhir = ($r['lapor_selesai'] ?? '') ?: null;
        }
        if (! $aktif) {
            $mulai = $akhir = null;
        }
        $lama = $mulai !== null && $akhir !== null ? SuratPkl::lamaBulan($mulai, $akhir) : 0;
        // Tanpa ajuan aktif: dikeluarkan dari kelompok (bila lebih baru dari ajuan terakhir yang ditolak) → keterangannya.
        $keluar = null;
        if (! $aktif && ! empty($r['keluar_info'])) {
            [$kJenis, $kWaktu, $kPt] = array_pad(explode('|', (string) $r['keluar_info'], 3), 3, '');
            if (empty($r['ditolak_pt']) || $kWaktu >= (string) ($r['ditolak_at'] ?? '')) {
                $keluar = 'Dikeluarkan dari ajuan ke ' . IsianBantu::rapikan($kPt) . ' (' . mb_strtolower(PklKeluar::labelJenis($kJenis)) . '), belum mengajukan ulang';
            }
        }

        return [
            'no' => 0, 'nama' => (string) $r['nama'], 'mitra' => $aktif ? IsianBantu::rapikan((string) $r['perusahaan_nama']) : '',
            'mulai' => $mulai, 'selesai' => $akhir, 'lama' => $lama > 0 ? $lama : null,
            'tahap'   => $pasti ? 'diterima' : ($aktif ? 'proses' : 'belum'),
            'catatan' => match (true) {
                $pasti && $mulai === null                 => 'Sudah diterima (tanggal belum diisi)',
                $pasti && $lst === 'baru' && ! $tglAj     => 'Sudah lapor diterima (Surat Balasan belum dibuat)',
                $pasti                                    => 'Sudah diterima',
                $status === 'disetujui' && $lst === 'dikembalikan' => 'Lapor Diterima dikembalikan petugas, perlu lapor ulang',
                $status === 'disetujui'                   => 'Belum diterima perusahaan (belum isi Lapor Diterima)',
                $status === 'menunggu'                    => 'Belum di-ACC Hubin (ajuan menunggu)',
                $status === 'perbaikan'                   => 'Ajuan dikembalikan Hubin untuk diperbaiki',
                $keluar !== null                          => $keluar,
                ! empty($r['ditolak_pt'])                 => 'Ajuan ke ' . IsianBantu::rapikan((string) $r['ditolak_pt']) . ' ditolak, belum mengajukan ulang',
                default                                   => 'Belum mengajukan PKL',
            },
        ];
    }

    /** Tata letak & gaya persis berkas sekolah (huruf, sel gabungan, lebar, garis, format tanggal). */
    private static function isiLembar(Worksheet $ws, array $siswa, string $sekolah, string $tahun): void
    {
        foreach (self::LEBAR as $kol => $lebar) {
            $ws->getColumnDimension($kol)->setWidth($lebar);
        }
        $ws->setCellValue('A1', 'DATA SISWA PRAKERIN');
        $ws->setCellValue('A2', mb_strtoupper($sekolah));
        $ws->setCellValue('A3', 'TAHUN PELAJARAN ' . $tahun);
        foreach (['A1:G1', 'A2:G2', 'A3:G3', 'A6:A7', 'B6:B7', 'C6:C7', 'D6:E6', 'F6:F7', 'G6:G7'] as $gabung) {
            $ws->mergeCells($gabung);
        }
        $ws->getStyle('A1:G3')->getFont()->setName('Times New Roman')->setSize(12)->setBold(true);
        $ws->getStyle('A1:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ws->setCellValue('A6', 'NO');
        $ws->setCellValue('B6', 'NAMA');
        $ws->setCellValue('C6', 'Mitra DI/DU');
        $ws->setCellValue('D6', 'Praktek Kerja Lapangan');
        $ws->setCellValue('F6', 'Lamanya PKL');
        $ws->setCellValue('G6', 'Keterangan');
        $ws->setCellValue('D7', 'Mulai ');
        $ws->setCellValue('E7', 'Selesai');
        $ws->getStyle('A6:G7')->getFont()->setName('Times New Roman')->setSize(12)->setBold(true);
        $ws->getStyle('A6:G6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A6:G7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $baris = 8;
        $tinggi = [];
        foreach ($siswa as $s) {
            if ($s['catatan'] !== null) {
                $ws->setCellValueExplicit('G' . $baris, (string) $s['catatan'], DataType::TYPE_STRING);
                if (mb_strlen((string) $s['catatan']) > self::KET_SEBARIS) {
                    $tinggi[$baris] = 31.5; // dua baris
                }
            }
            $ws->setCellValue('A' . $baris, (int) $s['no']);
            $ws->setCellValueExplicit('B' . $baris, (string) $s['nama'], DataType::TYPE_STRING);
            if ($s['mitra'] !== '') {
                $ws->setCellValueExplicit('C' . $baris, (string) $s['mitra'], DataType::TYPE_STRING);
            }
            if ($s['mulai'] !== null) {
                $ws->setCellValue('D' . $baris, TanggalExcel::PHPToExcel(new \DateTimeImmutable($s['mulai'] . ' 00:00:00')));
            }
            if ($s['selesai'] !== null) {
                $ws->setCellValue('E' . $baris, TanggalExcel::PHPToExcel(new \DateTimeImmutable($s['selesai'] . ' 00:00:00')));
            }
            if ($s['lama'] !== null) {
                $ws->setCellValue('F' . $baris, (int) $s['lama']);
            }
            $baris++;
        }

        $akhir = max(8, $baris - 1);
        $ws->getStyle('A8:A' . $akhir)->getFont()->setName('Times New Roman')->setSize(11);
        $ws->getStyle('A8:A' . $akhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('B8:B' . $akhir)->getFont()->setName('Times New Roman')->setSize(12);
        $ws->getStyle('B8:B' . $akhir)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('A8:B' . $akhir)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $ws->getStyle('D8:E' . $akhir)->getNumberFormat()->setFormatCode(self::FORMAT_TANGGAL);
        $ws->getStyle('G8:G' . $akhir)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('A8:G' . $akhir)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        for ($r = 1; $r <= $akhir; $r++) {
            $ws->getRowDimension($r)->setRowHeight($tinggi[$r] ?? 15.75);
        }
        // Kolom Keterangan menambah lebar → cetak mendatar, muat satu halaman lebar.
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $ws->setSelectedCell('A1');
    }

    /** Nama lembar sah untuk Excel (≤ 31 huruf, tanpa []:*?/\) dan tidak kembar. */
    private static function judulLembar(string $nama, array &$dipakai): string
    {
        $dasar = trim(mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $nama), 0, 31)) ?: 'Kelas';
        $judul = $dasar;
        for ($i = 2; isset($dipakai[mb_strtolower($judul)]); $i++) {
            $judul = mb_substr($dasar, 0, 31 - strlen(' (' . $i . ')')) . ' (' . $i . ')';
        }
        $dipakai[mb_strtolower($judul)] = true;

        return $judul;
    }
}
