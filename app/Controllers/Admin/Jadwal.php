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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
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

    /** Unduh template Excel untuk impor jadwal (format baris/list). */
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Jadwal');

        $headers = ['Kelas', 'Hari', 'Jam ke', 'Mapel (kode/nama)', 'Guru (kode/nama)'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // contoh: satu mapel 2 JP berurutan
        $contoh = [
            ['X TKJ 1', 'Senin', 1, 'MTK', 'Budi'],
            ['X TKJ 1', 'Senin', 2, 'MTK', 'Budi'],
            ['X TKJ 1', 'Senin', 3, 'BIND', 'Ani'],
        ];
        $sheet->fromArray($contoh, null, 'A2', true);
        foreach (['A' => 18, 'B' => 14, 'C' => 10, 'D' => 26, 'E' => 26] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Jadwal');
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

    /** Baca file Excel → baris assoc sesuai urutan kolom template. Null bila gagal (flash diset). */
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
            $data  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Gagal membaca file: ' . $e->getMessage());
            return null;
        }

        $keys = array_column($this->importCols(), 'key');
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

    /** Pratinjau impor (dapat diedit sebelum disimpan). */
    public function importPreview()
    {
        $rows = $this->readUpload();
        if ($rows === null) {
            return redirect()->to(site_url('admin/jadwal'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/jadwal'))->with('error', 'File tidak berisi data.');
        }

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

        // --- peta lookup ---
        $kelasMap = []; // NAMA => ['id','shift']
        foreach ((new KelasModel())->select('id, nama_kelas, shift')->findAll() as $k) {
            $kelasMap[strtoupper($k['nama_kelas'])] = ['id' => (int) $k['id'], 'shift' => $k['shift']];
        }
        $hariMap = []; // NAMA => id
        foreach ((new HariModel())->findAll() as $h) {
            $hariMap[strtoupper($h['nama'])] = (int) $h['id'];
        }
        $jamMap = []; // "shift-jamKe" => id (tanpa istirahat)
        foreach ((new JamPelajaranModel())->where('is_istirahat', 0)->findAll() as $j) {
            $jamMap[$j['shift'] . '-' . (int) $j['jam_ke']] = (int) $j['id'];
        }
        $mapelMap = []; // KODE/NAMA => id
        foreach ((new MataPelajaranModel())->select('id, kode_mapel, nama_mapel')->findAll() as $m) {
            $mapelMap[strtoupper($m['kode_mapel'])] = (int) $m['id'];
            $mapelMap[strtoupper($m['nama_mapel'])] = (int) $m['id'];
        }
        $guruMap = []; // KODE/NAMA => id
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $g) {
            $guruMap[strtoupper($g['kode_guru'])] = (int) $g['id'];
            $guruMap[strtoupper($g['nama'])]      = (int) $g['id'];
        }

        // --- hitung JP per (kelas,mapel,guru) di file → untuk jp pengampu baru ---
        $jpFile = [];
        foreach ($rows as $row) {
            $kk = strtoupper(trim((string) ($row['kelas'] ?? '')));
            $mm = strtoupper(trim((string) ($row['mapel'] ?? '')));
            $gg = strtoupper(trim((string) ($row['guru'] ?? '')));
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
            $kelasKey = strtoupper(trim((string) ($row['kelas'] ?? '')));
            $hariKey  = strtoupper(trim((string) ($row['hari'] ?? '')));
            $jamKe    = (int) ($row['jam'] ?? 0);
            $mapelKey = strtoupper(trim((string) ($row['mapel'] ?? '')));
            $guruKey  = strtoupper(trim((string) ($row['guru'] ?? '')));

            if ($kelasKey === '' || $hariKey === '' || $jamKe <= 0 || $mapelKey === '' || $guruKey === '') {
                $skip++;
                continue;
            }
            if (! isset($kelasMap[$kelasKey])) { $errors[] = "Baris {$ln}: kelas \"{$row['kelas']}\" tidak dikenal."; $skip++; continue; }
            if (! isset($hariMap[$hariKey]))   { $errors[] = "Baris {$ln}: hari \"{$row['hari']}\" tidak dikenal.";   $skip++; continue; }
            if (! isset($mapelMap[$mapelKey])) { $errors[] = "Baris {$ln}: mapel \"{$row['mapel']}\" tidak dikenal."; $skip++; continue; }
            if (! isset($guruMap[$guruKey]))   { $errors[] = "Baris {$ln}: guru \"{$row['guru']}\" tidak dikenal.";   $skip++; continue; }

            $kelas   = $kelasMap[$kelasKey];
            $kelasId = $kelas['id'];
            $hariId  = $hariMap[$hariKey];
            $jamId   = $jamMap[$kelas['shift'] . '-' . $jamKe] ?? null;
            if ($jamId === null) {
                $errors[] = "Baris {$ln}: jam ke-{$jamKe} tidak ada untuk shift {$kelas['shift']} (kelas {$row['kelas']}).";
                $skip++;
                continue;
            }
            $mapelId = $mapelMap[$mapelKey];
            $guruId  = $guruMap[$guruKey];

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
