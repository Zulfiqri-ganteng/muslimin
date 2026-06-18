<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\KetersediaanGuruModel;
use App\Models\PengampuModel;

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
            ->orderBy('jam_ke', 'ASC')->findAll(); // termasuk istirahat (utk baris pemisah)

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
