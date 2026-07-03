<?php

namespace App\Controllers\Admin\Master;

use App\Models\JamPelajaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class JamPelajaran extends BaseMaster
{
    protected string $module     = 'jam';
    protected string $auditTable = 'jam_pelajaran';
    protected string $routeBase  = 'admin/master/jam';
    protected string $entity     = 'jam pelajaran';
    protected string $titleLabel = 'Master Jam Pelajaran';

    /** Shift tujuan redirect setelah aksi. */
    private string $redirectShift = 'pagi';

    protected function makeModel(): Model
    {
        return new JamPelajaranModel();
    }

    protected function indexUrl(): string
    {
        return site_url($this->routeBase) . '?shift=' . $this->redirectShift;
    }

    private function shiftParam($value): string
    {
        return in_array($value, ['pagi', 'siang'], true) ? $value : 'pagi';
    }

    public function index()
    {
        $shift = $this->shiftParam($this->request->getGet('shift'));

        $rows = $this->cachedList('list|sh=' . $shift, fn () => $this->model->urutShift($shift));

        return view('admin/master/jam', [
            'title' => $this->titleLabel,
            'shift' => $shift,
            'rows'  => $rows,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        $this->redirectShift = $data['shift'];

        // cegah duplikat shift+jam_ke (termasuk baris yang pernah dihapus)
        $existing = $this->model->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])->first();
        if ($existing) {
            if (($existing['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada. Gunakan nomor jam lain atau edit yang ada.");
            }
            // pulihkan slot yang sebelumnya dihapus
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            master_data_changed($this->module);
            $this->audit->record('create', $this->auditTable, (int) $existing['id'], "Pulihkan jam {$data['shift']} ke-{$data['jam_ke']}");

            return $this->goIndex('Jam pelajaran ditambahkan.');
        }

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), "Tambah jam {$data['shift']} ke-{$data['jam_ke']}");

        return $this->goIndex('Jam pelajaran ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();
        $this->redirectShift = $data['shift'];

        // bentrok shift+jam_ke dengan baris LAIN?
        $dup = $this->model->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])
            ->where('id !=', $id)->first();
        if ($dup) {
            if (($dup['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada.");
            }
            // baris bentrok sudah terhapus (soft) → bersihkan lalu hapus permanen agar slot unik bebas
            $this->purgeHard((int) $dup['id']);
        }

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, "Ubah jam {$data['shift']} ke-{$data['jam_ke']}");

        return $this->goIndex('Jam pelajaran diperbarui.');
    }

    /** @param int|string $id */
    public function delete($id): RedirectResponse
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $this->redirectShift = $this->shiftParam($row['shift'] ?? null);

        $this->removeWithRelations([$id]);
        master_data_changed($this->module);
        $this->audit->record('delete', $this->auditTable, $id, 'Hapus jam pelajaran');

        return $this->goIndex('Jam pelajaran dihapus.');
    }

    /**
     * Hapus massal jam. Mode 'all' = seluruh jam pada shift aktif (bukan
     * kedua shift), 'selected' = ids tercentang.
     */
    public function bulkDelete(): RedirectResponse
    {
        $mode  = (string) $this->request->getPost('mode');
        $shift = $this->shiftParam($this->request->getPost('shift'));
        $this->redirectShift = $shift;

        if ($mode === 'all') {
            $ids = array_map('intval', array_column(
                $this->model->select('id')->where('shift', $shift)->findAll(),
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
        $this->audit->record('delete', $this->auditTable, null, 'Hapus massal ' . count($ids) . ' jam (' . $mode . ', ' . $shift . ')');

        return $this->goIndex(count($ids) . ' jam pelajaran dihapus.');
    }

    /** Ambil & normalisasi input; durasi dihitung dari selisih waktu. */
    private function collect(): array
    {
        $mulai   = (string) $this->request->getPost('waktu_mulai');
        $selesai = (string) $this->request->getPost('waktu_selesai');

        return [
            'shift'         => $this->shiftParam($this->request->getPost('shift')),
            'jam_ke'        => (int) $this->request->getPost('jam_ke'),
            'waktu_mulai'   => $mulai,
            'waktu_selesai' => $selesai,
            'durasi'        => $this->durasi($mulai, $selesai),
            'is_istirahat'  => $this->request->getPost('is_istirahat') ? 1 : 0,
        ];
    }

    private function durasi(string $mulai, string $selesai): int
    {
        if ($mulai === '' || $selesai === '') {
            return 0;
        }

        return (int) max(0, (strtotime($selesai) - strtotime($mulai)) / 60);
    }

    /**
     * Anti-orphan: jadwal, ketersediaan, dan absensi pada slot jam yang
     * dihapus ikut dibersihkan agar tidak ada data basi.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('jam_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('jam_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('jam_id', $ids)->delete();
    }

    /** Bersihkan relasi lalu hapus permanen satu baris (untuk slot soft-deleted yang bentrok). */
    private function purgeHard(int $id): void
    {
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->cleanupRelations($db, [$id]);
        $this->model->delete($id, true);
        $db->transComplete();
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('shift', 'ASC')->orderBy('jam_ke', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Jam',
            ['No', 'Shift', 'Jam Ke', 'Mulai', 'Selesai', 'Durasi (menit)', 'Istirahat'],
            ['A' => 5, 'B' => 10, 'C' => 10, 'D' => 10, 'E' => 10, 'F' => 16, 'G' => 12]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, ucfirst($d['shift']), $d['jam_ke'],
                substr($d['waktu_mulai'], 0, 5), substr($d['waktu_selesai'], 0, 5),
                $d['durasi'], $d['is_istirahat'] ? 'Ya' : 'Tidak',
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Jam-' . date('Ymd-His'));
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Jam',
            ['Shift (pagi/siang)', 'Jam Ke', 'Mulai (JJ:MM)', 'Selesai (JJ:MM)', 'Istirahat (Ya/Tidak)'],
            ['A' => 18, 'B' => 10, 'C' => 15, 'D' => 15, 'E' => 20]
        );
        $sheet->fromArray(['pagi', 1, '07:00', '07:45', 'Tidak'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Jam');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'shift',         'label' => 'Shift',     'type' => 'select', 'options' => ['pagi', 'siang'], 'required' => true, 'width' => 110],
            ['key' => 'jam_ke',        'label' => 'Jam Ke',    'type' => 'number', 'required' => true, 'width' => 90],
            ['key' => 'waktu_mulai',   'label' => 'Mulai',     'type' => 'text',   'required' => true, 'width' => 100],
            ['key' => 'waktu_selesai', 'label' => 'Selesai',   'type' => 'text',   'required' => true, 'width' => 100],
            ['key' => 'is_istirahat',  'label' => 'Istirahat', 'type' => 'select', 'options' => ['Ya', 'Tidak'], 'width' => 110],
        ];
    }

    protected function previewKey(array $row): string
    {
        return strtolower(trim((string) ($row['shift'] ?? 'pagi'))) . '|' . (int) ($row['jam_ke'] ?? 0);
    }

    protected function existingKeys(): array
    {
        $keys = [];
        foreach ($this->model->withDeleted()->select('shift, jam_ke')->findAll() as $r) {
            $keys[$r['shift'] . '|' . (int) $r['jam_ke']] = true;
        }

        return $keys;
    }

    protected function findExisting(array $payload): ?array
    {
        return $this->model->withDeleted()
            ->where('shift', $payload['shift'])
            ->where('jam_ke', $payload['jam_ke'])
            ->first();
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $shift = strtolower(trim((string) ($row['shift'] ?? '')));
        $jamKe = (int) ($row['jam_ke'] ?? 0);
        if ($shift === '' && $jamKe === 0) {
            return null;
        }
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            $error = 'Baris ' . $line . ': shift harus pagi/siang.';

            return null;
        }
        if ($jamKe < 1) {
            $error = 'Baris ' . $line . ': jam ke tidak valid.';

            return null;
        }

        $mulai   = $this->normalizeTime((string) ($row['waktu_mulai'] ?? ''));
        $selesai = $this->normalizeTime((string) ($row['waktu_selesai'] ?? ''));
        if ($mulai === null || $selesai === null) {
            $error = 'Baris ' . $line . ': format waktu harus JJ:MM (mis. 07:00).';

            return null;
        }

        $istirahatRaw = strtolower(trim((string) ($row['is_istirahat'] ?? 'tidak')));

        return [
            'shift'         => $shift,
            'jam_ke'        => $jamKe,
            'waktu_mulai'   => $mulai,
            'waktu_selesai' => $selesai,
            'durasi'        => $this->durasi($mulai, $selesai),
            'is_istirahat'  => in_array($istirahatRaw, ['ya', 'yes', '1', 'istirahat'], true) ? 1 : 0,
        ];
    }

    /** Terima "7:00", "07.00", "07:00:00" → "07:00"; null bila tidak valid. */
    private function normalizeTime(string $value): ?string
    {
        $value = str_replace('.', ':', trim($value));
        if (! preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }
}
