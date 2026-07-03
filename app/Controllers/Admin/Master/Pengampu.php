<?php

namespace App\Controllers\Admin\Master;

use App\Models\GuruMapelModel;
use App\Models\GuruModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengampuModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Pengampu extends BaseMaster
{
    protected string $module     = 'pengampu';
    protected string $auditTable = 'pengampu';
    protected string $routeBase  = 'admin/master/pengampu';
    protected string $entity     = 'penugasan';
    protected string $titleLabel = 'Penugasan Mengajar (Pengampu)';

    /** Kelas tujuan redirect setelah aksi (agar kembali ke kelas yang sama). */
    private ?int $redirectKelasId = null;

    protected function makeModel(): Model
    {
        return new PengampuModel();
    }

    protected function indexUrl(): string
    {
        $url = site_url($this->routeBase);

        return $this->redirectKelasId ? $url . '?kelas_id=' . $this->redirectKelasId : $url;
    }

    public function index()
    {
        $kelasOpts = (new KelasModel())->options();

        // kelas terpilih — default KOSONG; user memilih sendiri dari dropdown
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId !== 0 && ! isset($kelasOpts[$kelasId])) {
            $kelasId = 0;
        }

        // Seluruh data pendukung dicache; versi cache modul pengampu ikut
        // naik bila guru/mapel/kelas berubah (lihat ripple di cache_helper).
        $data = $this->cachedList('view|k=' . $kelasId, function () use ($kelasId) {
            $mapelModel = new MataPelajaranModel();

            $mapelJp = [];
            $mapelIds = [];
            foreach ($mapelModel->select('id, jp_default')->findAll() as $m) {
                $mapelJp[(int) $m['id']] = (int) $m['jp_default'];
                $mapelIds[]              = (int) $m['id'];
            }

            return [
                'rows'    => $kelasId ? $this->model->forKelas($kelasId) : [],
                'totalJp' => $kelasId ? $this->model->totalJpKelas($kelasId) : 0,
                'mapelJp' => $mapelJp,
                'kompMap' => (new GuruMapelModel())->mapForMapelIds($mapelIds),
            ];
        });

        return view('admin/master/pengampu', [
            'title'     => $this->titleLabel,
            'kelasOpts' => $kelasOpts,
            'kelasId'   => $kelasId,
            'rows'      => $data['rows'],
            'totalJp'   => $data['totalJp'],
            'mapelOpts' => (new MataPelajaranModel())->options(),
            'mapelJp'   => $data['mapelJp'],
            'guruOpts'  => (new GuruModel())->options(),
            'kompMap'   => $data['kompMap'],
        ]);
    }

    public function store()
    {
        $kelasId = (int) $this->request->getPost('kelas_id');
        $mapelId = (int) $this->request->getPost('mapel_id');
        $data    = [
            'kelas_id' => $kelasId,
            'mapel_id' => $mapelId,
            'guru_id'  => (int) $this->request->getPost('guru_id'),
            'jp'       => (int) $this->request->getPost('jp'),
        ];
        $this->redirectKelasId = $kelasId;

        // cegah duplikat kelas+mapel (termasuk yang soft-deleted karena UNIQUE di DB)
        $existing = $this->model->withDeleted()->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->first();
        if ($existing) {
            if ($existing['deleted_at'] === null) {
                return redirect()->back()->with('error', 'Mapel itu sudah ditugaskan di kelas ini. Edit yang ada.');
            }
            // pulihkan baris soft-deleted (protect(false) agar deleted_at boleh di-set null)
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            $id = (int) $existing['id'];
        } else {
            if (! $this->model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }
            $id = (int) $this->model->getInsertID();
        }

        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $id, 'Tambah penugasan kelas#' . $kelasId);

        return $this->goIndex('Penugasan disimpan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Data tidak ditemukan.');
        }
        $this->redirectKelasId = (int) $row['kelas_id'];

        $data = [
            'guru_id' => (int) $this->request->getPost('guru_id'),
            'jp'      => (int) $this->request->getPost('jp'),
        ];
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah penugasan');

        return $this->goIndex('Penugasan diperbarui.');
    }

    /** @param int|string $id */
    public function delete($id): RedirectResponse
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $this->redirectKelasId = $row ? (int) $row['kelas_id'] : null;

        $this->removeWithRelations([$id]);
        master_data_changed($this->module);
        $this->audit->record('delete', $this->auditTable, $id, 'Hapus penugasan');

        return $this->goIndex('Penugasan dihapus.');
    }

    /**
     * Hapus massal penugasan. Mode 'all' = seluruh penugasan pada kelas
     * yang sedang dipilih (bukan semua kelas), 'selected' = ids tercentang.
     */
    public function bulkDelete(): RedirectResponse
    {
        $mode    = (string) $this->request->getPost('mode');
        $kelasId = (int) $this->request->getPost('kelas_id');
        $this->redirectKelasId = $kelasId ?: null;

        if ($mode === 'all') {
            if ($kelasId === 0) {
                return $this->goIndex(null, 'Kelas belum dipilih.');
            }
            $ids = array_map('intval', array_column(
                $this->model->select('id')->where('kelas_id', $kelasId)->findAll(),
                'id'
            ));
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if ($ids === []) {
            return $this->goIndex(null, 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->removeWithRelations($ids);
        master_data_changed($this->module);
        $this->audit->record('delete', $this->auditTable, null, 'Hapus massal ' . count($ids) . ' penugasan (' . $mode . ')');

        return $this->goIndex(count($ids) . ' penugasan dihapus.');
    }

    /** Anti-orphan: jadwal yang memakai penugasan ini ikut terhapus. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('pengampu_id', $ids)->delete();
    }

    // ===================== EXPORT =====================

    /** Ekspor seluruh penugasan (semua kelas) ke Excel. */
    public function export()
    {
        $rows = $this->model
            ->select('kelas.nama_kelas, mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, guru.kode_guru, guru.nama AS guru_nama, pengampu.jp')
            ->join('kelas', 'kelas.id = pengampu.kelas_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('guru', 'guru.id = pengampu.guru_id')
            ->where('kelas.deleted_at', null)
            ->orderBy('kelas.nama_kelas', 'ASC')->orderBy('mata_pelajaran.nama_mapel', 'ASC')
            ->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Penugasan Mengajar',
            ['No', 'Kelas', 'Kode Mapel', 'Mata Pelajaran', 'Kode Guru', 'Guru Pengampu', 'JP/Minggu'],
            ['A' => 5, 'B' => 16, 'C' => 12, 'D' => 34, 'E' => 12, 'F' => 30, 'G' => 12]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['nama_kelas'], $d['kode_mapel'], $d['nama_mapel'],
                $d['kode_guru'], $d['guru_nama'], $d['jp'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Penugasan-Mengajar-' . date('Ymd-His'));
    }
}
