<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\KetersediaanGuruModel;
use App\Models\MataPelajaranModel;
use App\Models\PengampuModel;
use App\Models\SettingModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Jadwal extends BaseController
{
    protected JadwalModel $model;
    protected KetersediaanGuruModel $ket;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JadwalModel();
        $this->ket   = new KetersediaanGuruModel();
        $this->audit = new AuditModel();
    }

    // ===================== HALAMAN GRID =====================
    public function index()
    {
        $kelasModel = new KelasModel();
        $kelasOpts  = $kelasModel->options();

        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId === 0 && ! empty($kelasOpts)) {
            $kelasId = (int) array_key_first($kelasOpts);
        }

        $kelas = $kelasId ? $kelasModel->find($kelasId) : null;
        $shift = $kelas['shift'] ?? 'pagi';

        $hari = (new HariModel())->aktifUrut();
        $jam  = (new JamPelajaranModel())->where('shift', $shift)
            ->orderBy('waktu_mulai', 'ASC')->findAll(); // termasuk istirahat (urut waktu → pemisah di tengah)

        $grid = $kelasId ? $this->model->gridForKelas($kelasId) : [];

        // palet penugasan + sisa JP (R4)
        $palet = [];
        if ($kelasId) {
            $pengampu = (new PengampuModel())->forKelas($kelasId);
            $placed   = $this->model->placedCountByKelas($kelasId);
            foreach ($pengampu as $p) {
                $sudah = $placed[(int) $p['id']] ?? 0;
                $palet[] = [
                    'id'         => (int) $p['id'],
                    'guru_id'    => (int) $p['guru_id'],
                    'kode_mapel' => $p['kode_mapel'],
                    'nama_mapel' => $p['nama_mapel'],
                    'kode_guru'  => $p['kode_guru'],
                    'guru_nama'  => $p['guru_nama'],
                    'jp'         => (int) $p['jp'],
                    'sisa'       => (int) $p['jp'] - $sudah,
                ];
            }
        }

        return view('admin/jadwal/index', [
            'title'     => 'Jadwal KBM',
            'kelasOpts' => $kelasOpts,
            'kelasId'   => $kelasId,
            'kelas'     => $kelas,
            'shift'     => $shift,
            'hari'      => $hari,
            'jam'       => $jam,
            'grid'      => $grid,
            'palet'     => $palet,
        ]);
    }

    // ===================== GENERATE OTOMATIS =====================
    public function generate()
    {
        $kelasId = (int) $this->request->getPost('kelas_id');
        $reset   = (bool) $this->request->getPost('reset');
        if (! $kelasId) {
            return redirect()->back()->with('error', 'Kelas belum dipilih.');
        }

        $hasil = (new \App\Libraries\JadwalGenerator())->generate($kelasId, $reset);

        if (! $hasil['ok']) {
            return redirect()->to(site_url('admin/jadwal?kelas_id=' . $kelasId))->with('error', $hasil['message']);
        }

        $this->audit->record('create', 'jadwal', $kelasId, 'Generate otomatis: ' . $hasil['placed'] . ' JP');

        if (! empty($hasil['failed'])) {
            $det = array_map(static fn ($f) => $f['kode_mapel'] . ' (kurang ' . $f['kurang'] . ' JP)', $hasil['failed']);
            session()->setFlashdata('error', $hasil['message'] . ' Belum tertempatkan: ' . implode(', ', $det));
        }
        return redirect()->to(site_url('admin/jadwal?kelas_id=' . $kelasId))->with('success', $hasil['message']);
    }

    // ===================== AJAX: TEMPATKAN (palet -> sel kosong) =====================
    public function place()
    {
        $kelasId    = (int) $this->request->getPost('kelas_id');
        $hariId     = (int) $this->request->getPost('hari_id');
        $jamId      = (int) $this->request->getPost('jam_id');
        $pengampuId = (int) $this->request->getPost('pengampu_id');

        $p = $this->pengampuDisplay($pengampuId);
        if (! $p || (int) $p['kelas_id'] !== $kelasId) {
            return $this->fail('Penugasan tidak valid untuk kelas ini.');
        }
        $guruId = (int) $p['guru_id'];

        // R2 — sel sudah terisi?
        if ($this->model->cellOccupied($kelasId, $hariId, $jamId)) {
            return $this->fail('Sel sudah terisi. Hapus dulu isinya.');
        }
        // R4 — kuota JP penuh?
        if ($this->model->placedCount($pengampuId) >= (int) $p['jp']) {
            return $this->fail("Kuota JP {$p['kode_mapel']} sudah penuh ({$p['jp']} JP).");
        }
        // R3 & R1
        if ($err = $this->ruleError($guruId, $hariId, $jamId)) {
            return $this->fail($err);
        }

        try {
            $id = $this->model->insert([
                'tahun_ajaran_id' => $this->activeTaId(),
                'kelas_id'        => $kelasId,
                'hari_id'         => $hariId,
                'jam_id'          => $jamId,
                'pengampu_id'     => $pengampuId,
                'guru_id'         => $guruId,
                'created_by'      => session('admin')['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Gagal menyimpan (kemungkinan bentrok). ' . $e->getMessage());
        }

        $this->afterChange();
        $this->audit->record('create', 'jadwal', (int) $id, "Tempatkan {$p['kode_mapel']} kelas#{$kelasId}");

        return $this->ok([
            'cell' => $this->cellPayload((int) $id, $hariId, $jamId, $p),
            'sisa' => ['pengampu_id' => $pengampuId, 'sisa' => (int) $p['jp'] - $this->model->placedCount($pengampuId)],
        ]);
    }

    // ===================== AJAX: HAPUS (sel -> kosong) =====================
    public function remove()
    {
        $id  = (int) $this->request->getPost('id');
        $row = $this->model->find($id);
        if (! $row) {
            return $this->fail('Data jadwal tidak ditemukan.');
        }
        $pengampuId = (int) $row['pengampu_id'];

        $this->model->delete($id);
        $this->afterChange();
        $this->audit->record('delete', 'jadwal', $id, 'Hapus sel jadwal');

        $p = $this->pengampuDisplay($pengampuId);
        return $this->ok([
            'sisa' => ['pengampu_id' => $pengampuId, 'sisa' => $p ? (int) $p['jp'] - $this->model->placedCount($pengampuId) : 0],
        ]);
    }

    // ===================== AJAX: HAPUS MASSAL =====================
    public function bulkRemove()
    {
        $kelasId = (int) $this->request->getPost('kelas_id');
        $mode    = (string) $this->request->getPost('mode'); // 'selected' | 'all'

        if ($mode === 'all') {
            if (! $kelasId) {
                return $this->fail('Kelas tidak valid.');
            }
            $ids = array_map(static fn ($r) => (int) $r['id'], $this->model->select('id')->where('kelas_id', $kelasId)->findAll());
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return $this->fail('Tidak ada sel yang dipilih.');
        }

        // Ambil baris valid (opsional dibatasi ke kelas terpilih) untuk keamanan + kunci sel.
        $rows    = $this->model->whereIn('id', $ids)->findAll();
        $removed = [];
        foreach ($rows as $r) {
            if ($kelasId && (int) $r['kelas_id'] !== $kelasId) {
                continue;
            }
            $removed[] = ['id' => (int) $r['id'], 'key' => $r['hari_id'] . '-' . $r['jam_id']];
        }
        $delIds = array_column($removed, 'id');
        if (empty($delIds)) {
            return $this->fail('Tidak ada sel yang cocok untuk dihapus.');
        }

        $this->model->delete($delIds);
        $this->afterChange();
        $this->audit->record('delete', 'jadwal', null, 'Hapus massal ' . count($delIds) . ' sel jadwal kelas#' . $kelasId);

        return $this->ok([
            'removedKeys' => array_column($removed, 'key'),
            'sisaAll'     => $this->sisaPengampu($kelasId),
            'count'       => count($delIds),
        ]);
    }

    /** Sisa JP tiap pengampu satu kelas (untuk refresh palet setelah hapus massal). */
    private function sisaPengampu(int $kelasId): array
    {
        if (! $kelasId) {
            return [];
        }
        $placed = $this->model->placedCountByKelas($kelasId);
        $out    = [];
        foreach ((new PengampuModel())->forKelas($kelasId) as $p) {
            $pid   = (int) $p['id'];
            $out[] = ['pengampu_id' => $pid, 'sisa' => (int) $p['jp'] - ($placed[$pid] ?? 0)];
        }
        return $out;
    }

    // ===================== AJAX: PINDAH / TUKAR =====================
    public function move()
    {
        $fromId = (int) $this->request->getPost('id');
        $toHari = (int) $this->request->getPost('to_hari_id');
        $toJam  = (int) $this->request->getPost('to_jam_id');

        $from = $this->model->find($fromId);
        if (! $from) {
            return $this->fail('Sel asal tidak ditemukan.');
        }
        $kelasId = (int) $from['kelas_id'];

        // tak berubah posisi
        if ((int) $from['hari_id'] === $toHari && (int) $from['jam_id'] === $toJam) {
            return $this->ok([]);
        }

        $target = $this->model->cellOccupied($kelasId, $toHari, $toJam);

        // ---- PINDAH ke sel kosong ----
        if (! $target) {
            if ($err = $this->ruleError((int) $from['guru_id'], $toHari, $toJam, $fromId)) {
                return $this->fail($err);
            }
            try {
                $this->model->update($fromId, ['hari_id' => $toHari, 'jam_id' => $toJam]);
            } catch (\Throwable $e) {
                return $this->fail('Gagal memindahkan. ' . $e->getMessage());
            }
            $this->afterChange();
            $this->audit->record('update', 'jadwal', $fromId, 'Pindah sel jadwal');

            $pf = $this->pengampuDisplay((int) $from['pengampu_id']);
            return $this->ok([
                'move' => [
                    'from'  => ['hari_id' => (int) $from['hari_id'], 'jam_id' => (int) $from['jam_id']],
                    'cell'  => $this->cellPayload($fromId, $toHari, $toJam, $pf),
                ],
            ]);
        }

        // ---- TUKAR dua sel (delete kedua dulu agar tak bentrok UNIQUE, lalu validasi & insert) ----
        $targetId = (int) $target['id'];
        $db = db_connect();
        $db->transBegin();

        $this->model->delete($fromId);
        $this->model->delete($targetId);

        // validasi posisi baru (baris lama sudah terhapus → tak jadi false-positive)
        $errA = $this->ruleError((int) $from['guru_id'], $toHari, $toJam);
        $errB = $this->ruleError((int) $target['guru_id'], (int) $from['hari_id'], (int) $from['jam_id']);
        if ($errA || $errB) {
            $db->transRollback();
            return $this->fail($errA ?: $errB);
        }

        $newFrom = $this->reinsert($from, $toHari, $toJam);
        $newTgt  = $this->reinsert($target, (int) $from['hari_id'], (int) $from['jam_id']);

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->fail('Gagal menukar jadwal.');
        }
        $db->transCommit();
        $this->afterChange();
        $this->audit->record('update', 'jadwal', $fromId, 'Tukar sel jadwal');

        $pf = $this->pengampuDisplay((int) $from['pengampu_id']);
        $pt = $this->pengampuDisplay((int) $target['pengampu_id']);
        return $this->ok([
            'swap' => [
                ['cell' => $this->cellPayload($newFrom, $toHari, $toJam, $pf)],
                ['cell' => $this->cellPayload($newTgt, (int) $from['hari_id'], (int) $from['jam_id'], $pt)],
            ],
        ]);
    }

    // ===================== IMPORT DARI EXCEL =====================

    /**
     * Unduh template Excel untuk impor jadwal — format GRID (Jam × Hari) mengikuti
     * tampilan cetak/export, per kelas. Isi tiap sel dengan mapel (guru diambil
     * otomatis dari Penugasan) atau "Mapel / Guru" untuk menentukan guru langsung.
     */
    public function template()
    {
        $kelasModel = new KelasModel();
        $kelasId    = (int) $this->request->getGet('kelas_id');
        $kelas      = $kelasId ? $kelasModel->find($kelasId) : null;
        if (! $kelas) {
            $kelas = $kelasModel->orderBy('nama_kelas', 'ASC')->first();
        }
        $shift     = $kelas['shift'] ?? 'pagi';
        $namaKelas = $kelas['nama_kelas'] ?? '(NAMA KELAS)';

        $hari    = (new HariModel())->aktifUrut();
        $jam     = (new JamPelajaranModel())->where('shift', $shift)->orderBy('waktu_mulai', 'ASC')->findAll();
        $setting = (new SettingModel())->get() ?: ['school_name' => 'SEKOLAH', 'academic_year' => ''];

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Jadwal');

        // Layout sama seperti hasil cetak/export: kolom A = nama kelas (vertikal),
        // kolom B = "Jam", kolom C.. = hari. Importer membaca kelas dari kolom A ini.
        $jamCol   = 2;                          // "Jam" di kolom B
        $dayStart = 3;                          // hari mulai kolom C
        $colCount = count($hari) + 2;
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells("A1:{$lastCol}1")->setCellValue('A1', 'JADWAL PELAJARAN');
        $sheet->mergeCells("A2:{$lastCol}2")->setCellValue('A2', strtoupper($setting['school_name']) . ' — T.P. ' . $setting['academic_year']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header (baris 4): B = Jam, C.. = hari
        $r = 4;
        $sheet->setCellValue('B' . $r, 'Jam');
        $c = $dayStart;
        foreach ($hari as $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $r, $h['nama']);
        }
        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Body (baris jam + baris istirahat sebagai pemisah) — sel dibiarkan KOSONG untuk diisi
        $r = 5;
        foreach ($jam as $j) {
            if (! empty($j['is_istirahat'])) {
                $sheet->mergeCells("B{$r}:{$lastCol}{$r}")
                    ->setCellValue('B' . $r, 'ISTIRAHAT (' . substr($j['waktu_mulai'], 0, 5) . '-' . substr($j['waktu_selesai'], 0, 5) . ')');
                $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
                $r++;
                continue;
            }
            $sheet->setCellValue('B' . $r, 'Jam ' . $j['jam_ke'] . ' (' . substr($j['waktu_mulai'], 0, 5) . '-' . substr($j['waktu_selesai'], 0, 5) . ')');
            $r++;
        }

        $lastRow = $r - 1;

        // Kolom kelas vertikal (A4:A{lastRow}) — importer membaca nama kelas dari sini.
        $sheet->mergeCells("A4:A{$lastRow}")->setCellValue('A4', $namaKelas);
        $sheet->getStyle("A4:A{$lastRow}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1A3A6B');
        $sheet->getStyle("A4:A{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $sheet->getStyle("A4:A{$lastRow}")->getAlignment()
            ->setTextRotation(90)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(5);

        $sheet->getStyle("A4:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B4:{$lastCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $dayStartL = Coordinate::stringFromColumnIndex($dayStart);
        $sheet->getStyle("{$dayStartL}5:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('B')->setWidth(24);
        for ($i = $dayStart; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(22);
        }

        // Petunjuk pengisian (di bawah tabel; diabaikan saat impor)
        $noteRow = $lastRow + 2;
        $sheet->setCellValue('A' . $noteRow, 'CARA ISI: tulis MAPEL di tiap sel (guru otomatis dari Penugasan). Untuk tentukan guru langsung, tulis: Mapel / Guru. Kosongkan sel jika tidak ada pelajaran. Jangan ubah judul, nama kelas (kolom kiri), & kolom Jam.');
        $sheet->getStyle('A' . $noteRow)->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $this->streamXlsx($ss, 'Template-Jadwal-' . preg_replace('/[^a-z0-9]+/i', '-', $namaKelas));
    }

    /** Konfigurasi kolom impor (dipakai template, pembaca file, & pratinjau). */
    private function importCols(): array
    {
        $kelasNames = array_column((new KelasModel())->select('nama_kelas')->orderBy('nama_kelas', 'ASC')->findAll(), 'nama_kelas');
        $hariNames  = array_column((new HariModel())->orderBy('urutan', 'ASC')->findAll(), 'nama');
        $mapelList  = [];
        foreach ((new MataPelajaranModel())->select('kode_mapel, nama_mapel')->orderBy('nama_mapel', 'ASC')->findAll() as $m) {
            $mapelList[] = $m['kode_mapel'];
            $mapelList[] = $m['nama_mapel'];
        }
        $guruList = [];
        foreach ((new GuruModel())->select('kode_guru, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $guruList[] = $g['kode_guru'];
            $guruList[] = $g['nama'];
        }

        return [
            ['key' => 'kelas', 'label' => 'Kelas',             'type' => 'datalist', 'options' => $kelasNames, 'required' => true, 'width' => 160],
            ['key' => 'hari',  'label' => 'Hari',              'type' => 'select',   'options' => $hariNames,  'required' => true, 'width' => 120],
            ['key' => 'jam',   'label' => 'Jam ke',            'type' => 'number',   'required' => true, 'width' => 90],
            ['key' => 'mapel', 'label' => 'Mapel (kode/nama)', 'type' => 'datalist', 'options' => $mapelList,  'required' => true, 'width' => 200],
            ['key' => 'guru',  'label' => 'Guru (kode/nama)',  'type' => 'datalist', 'options' => $guruList,   'required' => true, 'width' => 200],
        ];
    }

    /** Baca file Excel → baris assoc [kelas,hari,jam,mapel,guru]. Null bila gagal (flash diset). */
    private function readUpload(): ?array
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            session()->setFlashdata('error', 'File tidak valid.');
            return null;
        }
        if (! in_array($file->getExtension(), ['xlsx', 'xls'], true)) {
            session()->setFlashdata('error', 'File harus berformat Excel (.xlsx / .xls).');
            return null;
        }
        try {
            $sheet = IOFactory::load($file->getTempName())->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false); // kolom 0-index
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Gagal membaca file: ' . $e->getMessage());
            return null;
        }

        // Utamakan format GRID (Jam × Hari) sesuai template; fallback ke format baris lama.
        $rows = $this->parseGrid($data);
        if ($rows === null) {
            $rows = $this->parseList($data);
        }
        return $rows;
    }

    /**
     * Parse GRID (Jam baris × Hari kolom). Kembalikan baris [kelas,hari,jam,mapel,guru]
     * atau null bila bukan format grid (agar bisa fallback ke parser baris).
     */
    private function parseGrid(array $data): ?array
    {
        // Cari baris header + kolom "Jam" (bisa di kolom mana saja — layout baru
        // menaruh kolom kelas vertikal di kiri sehingga "Jam" ada di kolom B).
        $headerIdx = null;
        $jamCol    = null;
        foreach ($data as $i => $row) {
            foreach ($row as $c => $val) {
                if (strtolower(trim((string) $val)) === 'jam') {
                    $headerIdx = $i;
                    $jamCol    = (int) $c;
                    break 2;
                }
            }
        }
        if ($headerIdx === null) {
            return null;
        }

        // Nama kelas — utamakan kolom kelas vertikal (kolom sebelum "Jam"),
        // lalu fallback ke judul ("JADWAL … — X TKJ 1") pada layout lama.
        $kelasName = '';
        if ($jamCol > 0) {
            for ($i = $headerIdx, $n = count($data); $i < $n; $i++) {
                $t = trim((string) ($data[$i][$jamCol - 1] ?? ''));
                if ($t !== '') {
                    $kelasName = $t;
                    break;
                }
            }
        }
        if ($kelasName === '') {
            for ($i = 0; $i < $headerIdx; $i++) {
                $t = trim((string) ($data[$i][0] ?? ''));
                if ($t !== '' && stripos($t, 'JADWAL') !== false) {
                    if (($p = mb_strpos($t, '—')) !== false) {
                        $kelasName = trim(mb_substr($t, $p + 1));
                    } elseif (($p = strrpos($t, '-')) !== false) {
                        $kelasName = trim(substr($t, $p + 1));
                    }
                    break;
                }
            }
        }

        // Kolom hari dari baris header (setelah kolom "Jam").
        $header   = $data[$headerIdx];
        $hariCols = []; // colIndex => nama hari
        for ($c = $jamCol + 1, $n = count($header); $c < $n; $c++) {
            $h = trim((string) ($header[$c] ?? ''));
            if ($h !== '') {
                $hariCols[$c] = $h;
            }
        }
        if (empty($hariCols)) {
            return null;
        }

        // Baris jam → sel per hari.
        $out = [];
        for ($i = $headerIdx + 1, $n = count($data); $i < $n; $i++) {
            $row   = $data[$i];
            $label = trim((string) ($row[$jamCol] ?? ''));
            if ($label === '' || stripos($label, 'istirahat') !== false) {
                continue;
            }
            if (! preg_match('/(\d+)/', $label, $m)) {
                continue; // baris catatan/lain
            }
            $jamKe = (int) $m[1];
            foreach ($hariCols as $c => $hariName) {
                $val = trim((string) ($row[$c] ?? ''));
                if ($val === '') {
                    continue;
                }
                // Sel bisa "Mapel", "Mapel / Guru", atau dua baris "Mapel\nGuru".
                $parts = preg_split('/\s*[\/\r\n]+\s*/', $val, 2);
                $mapel = $this->tidy((string) ($parts[0] ?? ''));
                $guru  = $this->tidy((string) ($parts[1] ?? ''));
                if ($mapel === '') {
                    continue;
                }
                $out[] = ['kelas' => $this->tidy($kelasName), 'hari' => $this->tidy($hariName), 'jam' => $jamKe, 'mapel' => $mapel, 'guru' => $guru];
            }
        }
        return $out;
    }

    /** Rapikan teks sel: buang spasi ganda / newline / spasi tepi jadi satu spasi. */
    private function tidy(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** Kunci ternormalisasi: HURUF BESAR + spasi tunggal (toleran beda spasi/newline). */
    private function nk(string $s): string
    {
        return strtoupper($this->tidy($s));
    }

    /** Kunci fuzzy: hanya huruf/angka (abaikan spasi, koma, titik, gelar dsb.). */
    private function fk(string $s): string
    {
        return strtoupper((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $s));
    }

    /** Daftarkan nilai ke peta dengan kunci normal + fuzzy (normal menang bila bentrok). */
    private function addKeys(array &$map, string $name, $value): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $map[$this->nk($name)] = $value;
        $fk = $this->fk($name);
        if ($fk !== '' && ! isset($map[$fk])) {
            $map[$fk] = $value; // jangan menimpa kunci fuzzy yang sudah ada
        }
    }

    /** Cari nilai di peta: cocok persis (ternormalisasi) dulu, lalu fuzzy. Null bila tak ada. */
    private function lookup(array $map, string $val)
    {
        $nk = $this->nk($val);
        if ($nk !== '' && isset($map[$nk])) {
            return $map[$nk];
        }
        $fk = $this->fk($val);
        if ($fk !== '' && isset($map[$fk])) {
            return $map[$fk];
        }
        return null;
    }

    /** Parser format baris lama (Kelas,Hari,Jam ke,Mapel,Guru) sebagai fallback. */
    private function parseList(array $data): array
    {
        $keys = ['kelas', 'hari', 'jam', 'mapel', 'guru'];
        $rows = [];
        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue; // header
            }
            $assoc = [];
            foreach ($keys as $c => $k) {
                $assoc[$k] = trim((string) ($row[$c] ?? ''));
            }
            if (implode('', $assoc) === '') {
                continue;
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    /** Peta guru dari Penugasan: "KELAS|MAPEL(kode/nama)" => nama guru. Untuk auto-isi kolom guru. */
    private function pengampuGuruMap(): array
    {
        $map  = [];
        $rows = db_connect()->table('pengampu p')
            ->select('k.nama_kelas, m.kode_mapel, m.nama_mapel, g.nama AS guru_nama')
            ->join('kelas k', 'k.id = p.kelas_id')
            ->join('mata_pelajaran m', 'm.id = p.mapel_id')
            ->join('guru g', 'g.id = p.guru_id')
            ->where('p.deleted_at', null)
            ->get()->getResultArray();
        foreach ($rows as $r) {
            foreach ([$this->nk((string) $r['nama_kelas']), $this->fk((string) $r['nama_kelas'])] as $kk) {
                if ($kk === '') {
                    continue;
                }
                foreach ([$r['kode_mapel'], $r['nama_mapel']] as $mp) {
                    foreach ([$this->nk((string) $mp), $this->fk((string) $mp)] as $mm) {
                        if ($mm !== '' && ! isset($map[$kk . '|' . $mm])) {
                            $map[$kk . '|' . $mm] = $r['guru_nama'];
                        }
                    }
                }
            }
        }
        return $map;
    }

    /** Cari guru dari peta Penugasan untuk (kelas, mapel) dengan toleransi spasi/tanda baca. */
    private function guruForCell(array $map, string $kelas, string $mapel): string
    {
        foreach ([$this->nk($kelas), $this->fk($kelas)] as $kk) {
            foreach ([$this->nk($mapel), $this->fk($mapel)] as $mm) {
                if ($kk !== '' && $mm !== '' && isset($map[$kk . '|' . $mm])) {
                    return (string) $map[$kk . '|' . $mm];
                }
            }
        }
        return '';
    }

    /** Pratinjau impor (dapat diedit sebelum disimpan). */
    public function importPreview()
    {
        $rows = $this->readUpload();
        if ($rows === null) {
            return redirect()->to(site_url('admin/jadwal'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/jadwal'))->with('error', 'File belum diisi (semua sel kosong).');
        }

        // Kelas cadangan bila judul tak terbaca: pakai kelas yang sedang dipilih.
        $fallbackKelas = '';
        if ($kid = (int) $this->request->getPost('kelas_id')) {
            $fallbackKelas = (string) ((new KelasModel())->find($kid)['nama_kelas'] ?? '');
        }
        // Auto-isi guru dari Penugasan bila sel hanya berisi mapel.
        $guruMap = $this->pengampuGuruMap();
        foreach ($rows as &$r) {
            if (($r['kelas'] ?? '') === '' && $fallbackKelas !== '') {
                $r['kelas'] = $fallbackKelas;
            }
            if (($r['guru'] ?? '') === '') {
                $r['guru'] = $this->guruForCell($guruMap, (string) ($r['kelas'] ?? ''), (string) ($r['mapel'] ?? ''));
            }
        }
        unset($r);

        // Tandai baris yang akan MENGGANTI slot terisi (kelas+hari+jam) vs baris baru.
        $occupied = [];
        foreach ($this->model
            ->select('kelas.nama_kelas, hari.nama AS hari_nama, jam_pelajaran.jam_ke')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->findAll() as $o) {
            $occupied[$this->slotKey($o['nama_kelas'], $o['hari_nama'], (string) $o['jam_ke'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($occupied[$this->slotKey($r['kelas'] ?? '', $r['hari'] ?? '', $r['jam'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Jadwal',
            'subtitle'  => 'Jadwal KBM',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/jadwal/import-commit'),
            'backUrl'   => site_url('admin/jadwal'),
        ]);
    }

    /** Simpan hasil impor (upsert per slot kelas+hari+jam). */
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/jadwal'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        // --- peta lookup (kunci ganda: ternormalisasi + fuzzy tanpa tanda baca,
        //     supaya beda spasi/koma/titik tetap cocok) ---
        $kelasMap = []; // NAMA => ['id','shift']
        foreach ((new KelasModel())->select('id, nama_kelas, shift')->findAll() as $k) {
            $this->addKeys($kelasMap, $k['nama_kelas'], ['id' => (int) $k['id'], 'shift' => $k['shift']]);
        }
        $hariMap = []; // NAMA => id
        foreach ((new HariModel())->findAll() as $h) {
            $this->addKeys($hariMap, $h['nama'], (int) $h['id']);
        }
        $jamMap = []; // "shift-jamKe" => id (tanpa istirahat)
        foreach ((new JamPelajaranModel())->where('is_istirahat', 0)->findAll() as $j) {
            $jamMap[$j['shift'] . '-' . (int) $j['jam_ke']] = (int) $j['id'];
        }
        $mapelMap = []; // KODE/NAMA => id
        foreach ((new MataPelajaranModel())->select('id, kode_mapel, nama_mapel')->findAll() as $m) {
            $this->addKeys($mapelMap, $m['kode_mapel'], (int) $m['id']);
            $this->addKeys($mapelMap, $m['nama_mapel'], (int) $m['id']);
        }
        $guruMap = []; // KODE/NAMA => id
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $g) {
            $this->addKeys($guruMap, $g['kode_guru'], (int) $g['id']);
            $this->addKeys($guruMap, $g['nama'], (int) $g['id']);
        }

        // --- hitung JP per (kelas,mapel,guru) di file → untuk jp pengampu baru ---
        $jpFile = [];
        foreach ($rows as $row) {
            $kk = $this->nk((string) ($row['kelas'] ?? ''));
            $mm = $this->nk((string) ($row['mapel'] ?? ''));
            $gg = $this->nk((string) ($row['guru'] ?? ''));
            if ($kk === '' || $mm === '' || $gg === '') {
                continue;
            }
            $jpFile["{$kk}|{$mm}|{$gg}"] = ($jpFile["{$kk}|{$mm}|{$gg}"] ?? 0) + 1;
        }

        $pengampuModel = new PengampuModel();
        $taId = $this->activeTaId();
        $ins = 0; $upd = 0; $skip = 0; $errors = [];

        $db = db_connect();
        $db->transStart();

        foreach ($rows as $i => $row) {
            $ln       = $i + 1;
            $kelasKey = $this->nk((string) ($row['kelas'] ?? ''));
            $mapelKey = $this->nk((string) ($row['mapel'] ?? ''));
            $guruKey  = $this->nk((string) ($row['guru'] ?? ''));
            $jamKe    = (int) ($row['jam'] ?? 0);

            if ($kelasKey === '' || $this->nk((string) ($row['hari'] ?? '')) === '' || $jamKe <= 0 || $mapelKey === '' || $guruKey === '') {
                $skip++;
                continue;
            }
            $kelas  = $this->lookup($kelasMap, (string) ($row['kelas'] ?? ''));
            $hariId = $this->lookup($hariMap, (string) ($row['hari'] ?? ''));
            $mapelId = $this->lookup($mapelMap, (string) ($row['mapel'] ?? ''));
            $guruId  = $this->lookup($guruMap, (string) ($row['guru'] ?? ''));
            if ($kelas === null)  { $errors[] = "Baris {$ln}: kelas \"{$row['kelas']}\" tidak dikenal."; $skip++; continue; }
            if ($hariId === null) { $errors[] = "Baris {$ln}: hari \"{$row['hari']}\" tidak dikenal.";   $skip++; continue; }
            if ($mapelId === null){ $errors[] = "Baris {$ln}: mapel \"{$row['mapel']}\" tidak dikenal."; $skip++; continue; }
            if ($guruId === null) { $errors[] = "Baris {$ln}: guru \"{$row['guru']}\" tidak dikenal.";   $skip++; continue; }

            $kelasId = $kelas['id'];
            $jamId   = $jamMap[$kelas['shift'] . '-' . $jamKe] ?? null;
            if ($jamId === null) {
                $errors[] = "Baris {$ln}: jam ke-{$jamKe} tidak ada untuk shift {$kelas['shift']} (kelas {$row['kelas']}).";
                $skip++;
                continue;
            }

            // pengampu (kelas+mapel+guru): pakai yang ada / buat otomatis / pulihkan yg terhapus
            $pengampuId = $this->resolvePengampu($pengampuModel, $kelasId, $mapelId, $guruId, (int) ($jpFile["{$kelasKey}|{$mapelKey}|{$guruKey}"] ?? 1));

            $existing = $this->model->cellOccupied($kelasId, $hariId, $jamId);

            // R1: guru sudah mengajar kelas lain pada slot yang sama?
            if ($c = $this->model->guruConflict($guruId, $hariId, $jamId, $existing['id'] ?? null)) {
                $errors[] = "Baris {$ln}: guru bentrok — sudah mengajar kelas {$c['nama_kelas']} pada {$row['hari']} jam ke-{$jamKe}.";
                $skip++;
                continue;
            }

            $payload = [
                'tahun_ajaran_id' => $taId,
                'kelas_id'        => $kelasId,
                'hari_id'         => $hariId,
                'jam_id'          => $jamId,
                'pengampu_id'     => $pengampuId,
                'guru_id'         => $guruId,
                'created_by'      => session('admin')['id'] ?? null,
            ];

            try {
                if ($existing) {
                    $this->model->update((int) $existing['id'], $payload);
                    $upd++;
                } else {
                    $this->model->insert($payload);
                    $ins++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Baris {$ln}: gagal disimpan (" . $e->getMessage() . ').';
                $skip++;
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return redirect()->to(site_url('admin/jadwal'))->with('error', 'Impor dibatalkan karena kesalahan database.');
        }

        $this->afterChange();
        cache()->delete('opt_kelas');
        $this->audit->record('import', 'jadwal', null, "Import jadwal: +{$ins} baru, {$upd} diganti, {$skip} dilewati");

        $msg = "Impor selesai: {$ins} slot baru, {$upd} diganti, {$skip} dilewati.";
        if ($errors) {
            $msg .= ' Sebagian dilewati: ' . implode(' ', array_slice($errors, 0, 8));
            if (count($errors) > 8) {
                $msg .= ' …(' . (count($errors) - 8) . ' lainnya).';
            }
            return redirect()->to(site_url('admin/jadwal'))->with('error', $msg);
        }
        return redirect()->to(site_url('admin/jadwal'))->with('success', $msg);
    }

    /** Cari pengampu (kelas+mapel+guru); buat/pulihkan bila belum ada. Kembalikan id. */
    private function resolvePengampu(PengampuModel $model, int $kelasId, int $mapelId, int $guruId, int $jpBaru): int
    {
        $existing = $model->withDeleted()
            ->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->where('guru_id', $guruId)->first();
        if ($existing) {
            if ($existing['deleted_at'] !== null) {
                $model->protect(false)->update($existing['id'], ['deleted_at' => null, 'id' => $existing['id']]);
                $model->protect(true);
            }
            return (int) $existing['id'];
        }
        // cegah bentrok UNIQUE(kelas,mapel): guru berbeda untuk mapel yang sama di kelas ini →
        // perbarui guru pada penugasan yang ada agar impor tetap jalan.
        $sameKelasMapel = $model->withDeleted()->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->first();
        if ($sameKelasMapel) {
            $model->protect(false)->update($sameKelasMapel['id'], ['guru_id' => $guruId, 'deleted_at' => null, 'id' => $sameKelasMapel['id']]);
            $model->protect(true);
            return (int) $sameKelasMapel['id'];
        }
        $model->insert(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'guru_id' => $guruId, 'jp' => max(1, $jpBaru)]);
        return (int) $model->getInsertID();
    }

    /** Kunci slot unik dari nama (dinormalisasi) untuk penanda status pratinjau. */
    private function slotKey(string $kelas, string $hari, string $jam): string
    {
        return strtoupper(trim($kelas)) . '|' . strtoupper(trim($hari)) . '|' . (int) $jam;
    }

    private function streamXlsx(Spreadsheet $ss, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    // ===================== HELPER =====================

    /** Kembalikan pesan error R3/R1 atau null bila lolos. */
    private function ruleError(int $guruId, int $hariId, int $jamId, ?int $exceptId = null): ?string
    {
        if ($this->ket->isUnavailable($guruId, $hariId, $jamId)) {
            return 'Guru tidak tersedia pada slot ini (lihat Ketersediaan Guru).';
        }
        if ($c = $this->model->guruConflict($guruId, $hariId, $jamId, $exceptId)) {
            return 'Bentrok: guru sudah mengajar di kelas ' . $c['nama_kelas'] . ' pada jam yang sama.';
        }
        return null;
    }

    /** Insert ulang baris jadwal (untuk swap) pada slot baru, kembalikan id baru. */
    private function reinsert(array $row, int $hariId, int $jamId): int
    {
        return (int) $this->model->insert([
            'tahun_ajaran_id' => $row['tahun_ajaran_id'] ?? $this->activeTaId(),
            'kelas_id'        => (int) $row['kelas_id'],
            'hari_id'         => $hariId,
            'jam_id'          => $jamId,
            'pengampu_id'     => (int) $row['pengampu_id'],
            'guru_id'         => (int) $row['guru_id'],
            'created_by'      => session('admin')['id'] ?? null,
        ]);
    }

    /** Data pengampu + nama mapel/guru untuk satu pengampu. */
    private function pengampuDisplay(int $pengampuId): ?array
    {
        return db_connect()->table('pengampu p')
            ->select('p.id, p.kelas_id, p.guru_id, p.jp, m.kode_mapel, m.nama_mapel, g.kode_guru, g.nama AS guru_nama')
            ->join('mata_pelajaran m', 'm.id = p.mapel_id')
            ->join('guru g', 'g.id = p.guru_id')
            ->where('p.id', $pengampuId)
            ->get()->getRowArray();
    }

    /** Payload sel untuk render di JS. */
    private function cellPayload(int $id, int $hariId, int $jamId, array $p): array
    {
        return [
            'id'          => $id,
            'hari_id'     => $hariId,
            'jam_id'      => $jamId,
            'pengampu_id' => (int) $p['id'],
            'kode_mapel'  => $p['kode_mapel'],
            'nama_mapel'  => $p['nama_mapel'],
            'kode_guru'   => $p['kode_guru'],
            'guru_nama'   => $p['guru_nama'],
        ];
    }

    private function activeTaId(): ?int
    {
        $ta = db_connect()->table('tahun_ajaran')->where('is_aktif', 1)->get()->getRowArray();
        return $ta ? (int) $ta['id'] : null;
    }

    /** Invalidasi cache yang bergantung pada jadwal. */
    private function afterChange(): void
    {
        cache()->delete('rekap_beban');
        cache()->delete('dash_kurikulum');
    }

    private function ok(array $extra = [])
    {
        return $this->response->setJSON(['ok' => true] + $extra);
    }

    private function fail(string $msg)
    {
        return $this->response->setStatusCode(200)->setJSON(['ok' => false, 'msg' => $msg]);
    }
}
