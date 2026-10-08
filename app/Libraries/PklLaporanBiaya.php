<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan Pembayaran PKL — siapa (nama, NIS), kelas, jurusan, sudah bayar berapa. Dipakai halaman web
 * (Admin\PklLaporan), unduhan Excel, dan API Android.
 *
 * Baris = setiap siswa yang punya ajuan PKL DISETUJUI atau punya catatan biaya/keringanan. Kelas = kelas saat
 * mengajukan (snapshot pkl_anggota), jurusan disatukan lewat SiswaResmi8355::jurusanDb (TKJ/TJKT/TKJT → TKJ, dst.).
 *
 * Status per siswa (selalu dari SEMUA catatannya, bukan hanya rentang tanggal):
 *   Lunas    = semua biaya yang harus ada untuk surat ini tercatat: Biaya PKL (tahun ajaran ajuan) + tiap biaya
 *              bulanan untuk BULAN SURAT (SPP dibebaskan bila beasiswa berlaku);
 *   Sebagian = ada yang tercatat tetapi belum semuanya;
 *   Belum    = tidak ada satu pun biaya tercatat.
 * Kekurangan = jumlah nominal biaya yang harus ada tetapi belum tercatat. Beasiswa & keringanan ditampilkan tersendiri.
 * Filter tanggal memilih siswa yang punya catatan pada rentang itu dan menghitung "uang masuk" pada rentang itu.
 */
final class PklLaporanBiaya
{
    public const STATUS = ['lunas' => 'Lunas', 'sebagian' => 'Sebagian', 'belum' => 'Belum'];
    public const JURUSAN = ['TKJ' => 'TKJ', 'AKL' => 'Akuntansi (AKL)', 'MP' => 'Manajemen Perkantoran', '' => 'Lainnya / belum ada kelas'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array{kelas_id?: int, jurusan?: string, status?: string, q?: string, dari?: string, sampai?: string, beasiswa?: bool} $f
     *
     * @return array{baris: list<array<string,mixed>>, ringkas: array<string,mixed>, jenis: list<array<string,mixed>>}
     */
    public function data(array $f = []): array
    {
        $biaya = new PklBiaya($this->db);
        $jenis = $biaya->jenis();
        $jenisSemua = [];
        foreach ($biaya->jenis(true) as $j) {
            $jenisSemua[$j['id']] = $j;
        }

        // ---------- siswa kandidat ----------
        $ids = [];
        foreach ($this->db->query("SELECT DISTINCT a.siswa_id AS id FROM pkl_anggota a JOIN pkl_pengajuan p ON p.id = a.pengajuan_id WHERE p.status = 'disetujui'"
            . ' UNION SELECT DISTINCT siswa_id FROM pkl_pembayaran UNION SELECT DISTINCT siswa_id FROM pkl_keringanan')->getResultArray() as $r) {
            $ids[] = (int) $r['id'];
        }
        $out = ['baris' => [], 'ringkas' => [], 'jenis' => $jenis];
        $kosongRingkas = ['siswa' => 0, 'lunas' => 0, 'sebagian' => 0, 'belum' => 0, 'beasiswa' => 0, 'uang_masuk' => 0, 'total_kekurangan' => 0, 'per_jenis' => array_fill_keys(array_column($jenis, 'kode'), 0)];
        if ($ids === []) {
            $out['ringkas'] = $kosongRingkas;

            return $out;
        }

        // ---------- data pendukung (satu kali ambil) ----------
        $siswa = [];
        foreach ($this->db->table('siswa')->select('id, nama, nis, tahun_masuk')->whereIn('id', $ids)->get()->getResultArray() as $r) {
            $siswa[(int) $r['id']] = $r;
        }
        // Ajuan disetujui terbaru tiap siswa + kelas/jurusan saat mengajukan + surat.
        $ajuan = [];
        $q = $this->db->query(
            "SELECT a.siswa_id, p.id AS ajuan_id, p.perusahaan_nama, p.tahun_ajaran, p.acc_at, k.id AS kelas_id, k.nama_kelas, j.kode AS jur_kode, j.nama AS jur_nama,"
            . ' sr.nomor, sr.tanggal_surat'
            . ' FROM pkl_anggota a JOIN pkl_pengajuan p ON p.id = a.pengajuan_id'
            . ' LEFT JOIN kelas k ON k.id = a.kelas_id LEFT JOIN jurusan j ON j.id = k.jurusan_id LEFT JOIN pkl_surat sr ON sr.pengajuan_id = p.id'
            . " WHERE p.status = 'disetujui' ORDER BY p.id ASC"
        )->getResultArray();
        foreach ($q as $r) { // urut naik → yang terakhir menimpa = terbaru
            $ajuan[(int) $r['siswa_id']] = $r;
        }
        $kelasSekarang = [];
        foreach ($this->db->query('SELECT s.id, k.id AS kelas_id, k.nama_kelas, j.kode AS jur_kode, j.nama AS jur_nama FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id LEFT JOIN jurusan j ON j.id = k.jurusan_id WHERE s.id IN (' . implode(',', $ids) . ')')->getResultArray() as $r) {
            $kelasSekarang[(int) $r['id']] = $r;
        }

        $bayar = [];
        foreach ($this->db->table('pkl_pembayaran')->whereIn('siswa_id', $ids)->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            $bayar[(int) $r['siswa_id']][] = $r;
        }
        $beasiswa = $biaya->beasiswaAktif($ids);
        $ringan = [];
        foreach ($this->db->table('pkl_keringanan')->whereIn('siswa_id', $ids)->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            $ringan[(int) $r['siswa_id']] = $r; // terakhir menimpa
        }
        $akt = $jenis; // jenis aktif

        $dari = $this->tanggal($f['dari'] ?? '');
        $sampai = $this->tanggal($f['sampai'] ?? '');
        $cari = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $hari = date('Y-m-d');

        $ringkas = $kosongRingkas;
        $baris = [];
        foreach ($ids as $sid) {
            $s = $siswa[$sid] ?? null;
            if ($s === null) {
                continue;
            }
            $aj = $ajuan[$sid] ?? null;
            $kls = $aj !== null && $aj['kelas_id'] !== null ? $aj : ($kelasSekarang[$sid] ?? []);
            $jur = SiswaResmi8355::jurusanDb($kls['jur_kode'] ?? null, $kls['jur_nama'] ?? null);
            $rows = $bayar[$sid] ?? [];

            // Rentang tanggal: siswa tampil hanya bila punya catatan di rentang itu.
            $masukRentang = 0;
            $adaRentang = ($dari === null && $sampai === null);
            foreach ($rows as $r) {
                $tgl = substr((string) $r['created_at'], 0, 10);
                if (($dari === null || $tgl >= $dari) && ($sampai === null || $tgl <= $sampai)) {
                    $masukRentang += (int) $r['nominal'];
                    $adaRentang = true;
                }
            }
            if (isset($ringan[$sid])) {
                $tgl = substr((string) $ringan[$sid]['created_at'], 0, 10);
                if (($dari === null || $tgl >= $dari) && ($sampai === null || $tgl <= $sampai)) {
                    $adaRentang = true;
                }
            }
            if (! $adaRentang) {
                continue;
            }

            // Rincian per jenis (semua catatan).
            $per = [];
            $total = 0;
            foreach ($akt as $j) {
                $per[$j['kode']] = ['total' => 0, 'periode' => []];
            }
            $terakhir = null;
            $oleh = null;
            foreach ($rows as $r) {
                $j = $jenisSemua[(int) $r['biaya_id']] ?? null;
                if ($j === null) {
                    continue;
                }
                $per[$j['kode']]['total'] = ($per[$j['kode']]['total'] ?? 0) + (int) $r['nominal'];
                $per[$j['kode']]['periode'][] = PklBiaya::labelPeriode($j['siklus'], (string) $r['periode']);
                $per[$j['kode']]['_raw'][] = (string) $r['periode'];
                $total += (int) $r['nominal'];
                $terakhir = (string) $r['created_at'];
                $oleh = (string) $r['dicatat_oleh'];
            }

            // Yang harus ada untuk surat ini.
            $bebasSpp = isset($beasiswa[$sid]);
            $acuan = $this->bulanAcuan($aj, $terakhir);
            $periodeKegiatan = $aj !== null ? PklBiaya::periodeKegiatan($aj) : PklBiaya::periodeKegiatan([]);
            $perlu = 0;
            $kurang = 0;
            $adaTercatat = 0;
            foreach ($akt as $j) {
                if ($j['kode'] === 'spp' && $bebasSpp) {
                    continue;
                }
                $periodeButuh = $j['siklus'] === 'bulanan' ? $acuan : $periodeKegiatan;
                $perlu++;
                if (in_array($periodeButuh, $per[$j['kode']]['_raw'] ?? [], true)) {
                    $adaTercatat++;
                } else {
                    $kurang += $j['nominal'];
                }
            }
            $status = $total === 0 ? 'belum' : ($perlu > 0 && $adaTercatat >= $perlu ? 'lunas' : 'sebagian');
            if ($perlu === 0 && $total > 0) {
                $status = 'lunas';
            }

            $row = [
                'siswa_id' => $sid, 'nama' => (string) $s['nama'], 'nis' => (string) $s['nis'],
                'kelas_id' => isset($kls['kelas_id']) ? (int) $kls['kelas_id'] : 0, 'kelas' => (string) ($kls['nama_kelas'] ?? ''),
                'jurusan_kode' => $jur, 'jurusan' => self::JURUSAN[$jur] ?? 'Lainnya',
                'ajuan_id' => $aj !== null ? (int) $aj['ajuan_id'] : null, 'kode_ajuan' => $aj !== null ? \App\Models\PklPengajuanModel::kode((int) $aj['ajuan_id']) : null,
                'perusahaan' => $aj['perusahaan_nama'] ?? null, 'nomor_surat' => $aj['nomor'] ?? null, 'tanggal_surat' => $aj['tanggal_surat'] ?? null,
                'bayar' => array_map(static fn (array $x) => ['total' => (int) $x['total'], 'periode' => $x['periode']], $per),
                'total_bayar' => $total, 'kekurangan' => $kurang, 'status' => $status, 'status_label' => self::STATUS[$status],
                'beasiswa' => $bebasSpp ? (PklBiaya::SUMBER_BEASISWA[$beasiswa[$sid]['sumber']] ?? 'Beasiswa') : null,
                'keringanan' => $ringan[$sid]['alasan'] ?? null,
                'terakhir_bayar' => $terakhir, 'dicatat_oleh' => $oleh, 'uang_rentang' => $masukRentang, 'bulan_acuan' => $acuan,
            ];

            // Filter lain.
            if ((int) ($f['kelas_id'] ?? 0) > 0 && $row['kelas_id'] !== (int) $f['kelas_id']) {
                continue;
            }
            if (($f['jurusan'] ?? '') !== '' && $row['jurusan_kode'] !== $f['jurusan']) {
                continue;
            }
            if (($f['status'] ?? '') !== '' && $row['status'] !== $f['status']) {
                continue;
            }
            if (! empty($f['beasiswa']) && $row['beasiswa'] === null) {
                continue;
            }
            if ($cari !== '' && ! str_contains(mb_strtolower($row['nama'] . ' ' . $row['nis'] . ' ' . ($row['perusahaan'] ?? '') . ' ' . ($row['nomor_surat'] ?? '')), $cari)) {
                continue;
            }

            $baris[] = $row;
            $ringkas['siswa']++;
            $ringkas[$status]++;
            $ringkas['beasiswa'] += $row['beasiswa'] !== null ? 1 : 0;
            $ringkas['total_kekurangan'] += $kurang;
            $ringkas['uang_masuk'] += ($dari !== null || $sampai !== null) ? $masukRentang : $total;
            foreach ($row['bayar'] as $kode => $x) {
                $ringkas['per_jenis'][$kode] = ($ringkas['per_jenis'][$kode] ?? 0) + $x['total'];
            }
        }

        usort($baris, static fn (array $a, array $b) => [$a['jurusan_kode'], $a['kelas'], mb_strtolower($a['nama'])] <=> [$b['jurusan_kode'], $b['kelas'], mb_strtolower($b['nama'])]);
        $out['baris'] = $baris;
        $out['ringkas'] = $ringkas;

        return $out;
    }

    /** Bulan acuan tagihan bulanan = bulan tanggal surat; cadangan: bulan ACC, bulan catatan terakhir, bulan ini. */
    private function bulanAcuan(?array $aj, ?string $terakhir): string
    {
        foreach ([$aj['tanggal_surat'] ?? null, $aj['acc_at'] ?? null, $terakhir] as $t) {
            if (! empty($t) && preg_match('/^(\d{4}-\d{2})/', (string) $t, $m)) {
                return $m[1];
            }
        }

        return PklBiaya::bulanIni();
    }

    private function tanggal(string $s): ?string
    {
        $s = trim($s);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4)) ? $s : null;
    }

    // =================================================================
    // Excel
    // =================================================================

    /**
     * Berkas Excel: lembar "Rincian", "Rekap Kelas", "Rekap Jurusan".
     *
     * @param array{baris: list<array<string,mixed>>, ringkas: array<string,mixed>, jenis: list<array<string,mixed>>} $hasil
     * @param array<string, mixed> $saringan label filter yang dipakai (untuk judul)
     * @param array<string, mixed> $setting  baris settings sekolah
     */
    public function excel(array $hasil, array $saringan, array $setting): string
    {
        $jenis = $hasil['jenis'];
        $x = new Spreadsheet();
        $x->getProperties()->setTitle('Laporan Pembayaran PKL')->setCreator('Sistem Informasi Akademik Sekolah');

        // ---------- Rincian ----------
        $ws = $x->getActiveSheet()->setTitle('Rincian');
        $nKol = 8 + count($jenis) + 5; // No..Surat (8) + jenis + total/kurang/status/beasiswa/catatan
        $akhir = $this->kolom($nKol);
        $ws->setCellValue('A1', 'LAPORAN PEMBAYARAN PKL — ' . mb_strtoupper((string) ($setting['school_name'] ?? '')));
        $ws->setCellValue('A2', 'Tahun Pelajaran ' . ($setting['academic_year'] ?? '') . ' · dicetak ' . date('d-m-Y H:i') . ($saringan['teks'] !== '' ? ' · ' . $saringan['teks'] : ''));
        $r = $hasil['ringkas'];
        $ws->setCellValue('A3', sprintf('Siswa: %d · Lunas: %d · Sebagian: %d · Belum: %d · Beasiswa: %d · Uang tercatat: %s · Kekurangan: %s',
            $r['siswa'], $r['lunas'], $r['sebagian'], $r['belum'], $r['beasiswa'], PklBiaya::rupiah((int) $r['uang_masuk']), PklBiaya::rupiah((int) $r['total_kekurangan'])));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A3')->getFont()->setBold(true);

        $judul = ['No', 'Nama', 'NIS', 'Kelas', 'Jurusan', 'Perusahaan', 'No. Surat', 'Tgl Surat'];
        foreach ($jenis as $j) {
            $judul[] = $j['nama'] . ($j['siklus'] === 'bulanan' ? ' (bulanan)' : '');
        }
        array_push($judul, 'Total Dibayar', 'Kekurangan', 'Status', 'Beasiswa', 'Catatan / Keringanan');
        $ws->fromArray($judul, null, 'A5');
        $ws->getStyle('A5:' . $akhir . '5')->applyFromArray([
            'font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $baris = 6;
        foreach ($hasil['baris'] as $i => $b) {
            $ws->setCellValue('A' . $baris, $i + 1);
            $this->teks($ws, 'B' . $baris, $b['nama']);
            $this->teks($ws, 'C' . $baris, $b['nis']);
            $this->teks($ws, 'D' . $baris, $b['kelas']);
            $this->teks($ws, 'E' . $baris, $b['jurusan']);
            $this->teks($ws, 'F' . $baris, (string) ($b['perusahaan'] ?? ''));
            $this->teks($ws, 'G' . $baris, (string) ($b['nomor_surat'] ?? ''));
            $this->teks($ws, 'H' . $baris, $b['tanggal_surat'] ? date('d-m-Y', strtotime((string) $b['tanggal_surat'])) : '');
            $c = 9;
            foreach ($jenis as $j) {
                $v = $b['bayar'][$j['kode']] ?? ['total' => 0, 'periode' => []];
                $ws->setCellValue($this->kolom($c) . $baris, $v['total'] > 0 ? $v['total'] : null);
                if ($j['siklus'] === 'bulanan' && $v['periode'] !== []) {
                    $ws->getComment($this->kolom($c) . $baris)->getText()->createTextRun(implode(', ', $v['periode']));
                }
                $c++;
            }
            $ws->setCellValue($this->kolom($c++) . $baris, $b['total_bayar']);
            $ws->setCellValue($this->kolom($c++) . $baris, $b['kekurangan'] > 0 ? $b['kekurangan'] : null);
            $this->teks($ws, $this->kolom($c++) . $baris, $b['status_label']);
            $this->teks($ws, $this->kolom($c++) . $baris, (string) ($b['beasiswa'] ?? ''));
            $this->teks($ws, $this->kolom($c) . $baris, (string) ($b['keringanan'] ?? ''));
            $baris++;
        }
        if ($hasil['baris'] !== []) {
            $ws->setCellValue('B' . $baris, 'JUMLAH');
            $c = 9;
            foreach ($jenis as $j) {
                $ws->setCellValue($this->kolom($c) . $baris, '=SUM(' . $this->kolom($c) . '6:' . $this->kolom($c) . ($baris - 1) . ')');
                $c++;
            }
            $ws->setCellValue($this->kolom($c) . $baris, '=SUM(' . $this->kolom($c) . '6:' . $this->kolom($c) . ($baris - 1) . ')');
            $ws->setCellValue($this->kolom($c + 1) . $baris, '=SUM(' . $this->kolom($c + 1) . '6:' . $this->kolom($c + 1) . ($baris - 1) . ')');
            $ws->getStyle('A' . $baris . ':' . $akhir . $baris)->getFont()->setBold(true);
            $ws->getStyle('A' . $baris . ':' . $akhir . $baris)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        }
        $ws->getStyle('A6:' . $akhir . max(6, $baris))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $ws->getStyle($this->kolom(9) . '6:' . $this->kolom(9 + count($jenis) + 1) . max(6, $baris))->getNumberFormat()->setFormatCode('#,##0');
        foreach (range(1, $nKol) as $c) {
            $ws->getColumnDimension($this->kolom($c))->setAutoSize(true);
        }
        $ws->getColumnDimension('F')->setAutoSize(false)->setWidth(34);
        $ws->freezePane('C6');
        $ws->setAutoFilter('A5:' . $akhir . '5');
        $ws->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);

        // ---------- Rekap Kelas & Jurusan ----------
        $this->lembarRekap($x->createSheet(), 'Rekap Kelas', 'Kelas', $hasil, $jenis, static fn (array $b) => $b['kelas'] !== '' ? $b['kelas'] : '(belum ada kelas)');
        $this->lembarRekap($x->createSheet(), 'Rekap Jurusan', 'Jurusan', $hasil, $jenis, static fn (array $b) => $b['jurusan']);
        $x->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'pkb');
        (new Xlsx($x))->save($tmp);
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }

    /** @param callable(array<string,mixed>): string $kunci */
    private function lembarRekap($ws, string $nama, string $judulKolom, array $hasil, array $jenis, callable $kunci): void
    {
        $ws->setTitle($nama);
        $ws->setCellValue('A1', 'REKAP PEMBAYARAN PKL PER ' . mb_strtoupper($judulKolom));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $head = [$judulKolom, 'Jumlah Siswa', 'Lunas', 'Sebagian', 'Belum', 'Beasiswa'];
        foreach ($jenis as $j) {
            $head[] = $j['nama'] . ' (Rp)';
        }
        array_push($head, 'Total Uang (Rp)', 'Kekurangan (Rp)');
        $ws->fromArray($head, null, 'A3');
        $akhir = $this->kolom(count($head));
        $ws->getStyle('A3:' . $akhir . '3')->applyFromArray([
            'font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $grup = [];
        foreach ($hasil['baris'] as $b) {
            $k = $kunci($b);
            $g = &$grup[$k];
            $g ??= ['siswa' => 0, 'lunas' => 0, 'sebagian' => 0, 'belum' => 0, 'beasiswa' => 0, 'total' => 0, 'kurang' => 0, 'jenis' => []];
            $g['siswa']++;
            $g[$b['status']]++;
            $g['beasiswa'] += $b['beasiswa'] !== null ? 1 : 0;
            $g['total'] += $b['total_bayar'];
            $g['kurang'] += $b['kekurangan'];
            foreach ($b['bayar'] as $kode => $v) {
                $g['jenis'][$kode] = ($g['jenis'][$kode] ?? 0) + $v['total'];
            }
            unset($g);
        }
        ksort($grup, SORT_NATURAL | SORT_FLAG_CASE);

        $r = 4;
        foreach ($grup as $k => $g) {
            $this->teks($ws, 'A' . $r, (string) $k);
            $ws->setCellValue('B' . $r, $g['siswa']);
            $ws->setCellValue('C' . $r, $g['lunas']);
            $ws->setCellValue('D' . $r, $g['sebagian']);
            $ws->setCellValue('E' . $r, $g['belum']);
            $ws->setCellValue('F' . $r, $g['beasiswa']);
            $c = 7;
            foreach ($jenis as $j) {
                $ws->setCellValue($this->kolom($c++) . $r, $g['jenis'][$j['kode']] ?? 0);
            }
            $ws->setCellValue($this->kolom($c++) . $r, $g['total']);
            $ws->setCellValue($this->kolom($c) . $r, $g['kurang']);
            $r++;
        }
        if ($grup !== []) {
            $ws->setCellValue('A' . $r, 'JUMLAH');
            for ($c = 2; $c <= count($head); $c++) {
                $ws->setCellValue($this->kolom($c) . $r, '=SUM(' . $this->kolom($c) . '4:' . $this->kolom($c) . ($r - 1) . ')');
            }
            $ws->getStyle('A' . $r . ':' . $akhir . $r)->getFont()->setBold(true);
        } else {
            $ws->setCellValue('A4', 'Belum ada data.');
        }
        $ws->getStyle('G4:' . $akhir . max(4, $r))->getNumberFormat()->setFormatCode('#,##0');
        $ws->getStyle('A4:' . $akhir . max(4, $r))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (range(1, count($head)) as $c) {
            $ws->getColumnDimension($this->kolom($c))->setAutoSize(true);
        }
    }

    /** Isi sel sebagai TEKS murni (NIS berangka nol di depan aman; tanda = tak jadi rumus). */
    private function teks($ws, string $sel, string $nilai): void
    {
        $ws->setCellValueExplicit($sel, $nilai, DataType::TYPE_STRING);
    }

    /** 1 → A, 27 → AA. */
    private function kolom(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }
}
