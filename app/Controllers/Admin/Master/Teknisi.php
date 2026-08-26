<?php

namespace App\Controllers\Admin\Master;

use App\Models\GuruModel;
use App\Models\TeknisiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Teknisi / Penanggung Jawab lab.
 * Tabel tersendiri (boleh staf non-guru); opsional ditautkan ke data guru.
 */
class Teknisi extends BaseMaster
{
    protected string $module     = 'teknisi';
    protected string $auditTable = 'teknisi';
    protected string $routeBase  = 'admin/master/teknisi';
    protected string $entity     = 'teknisi';
    protected string $titleLabel = 'Master Teknisi';

    protected function makeModel(): Model
    {
        return new TeknisiModel();
    }

    protected function indexUrl(): string
    {
        $qs = array_filter([
            'q'     => trim((string) $this->request->getGet('q')),
            'peran' => trim((string) $this->request->getGet('peran')),
            'per'   => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $peran = trim((string) $this->request->getGet('peran'));
        if (! in_array($peran, TeknisiModel::PERAN, true)) {
            $peran = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $kunci = "list|q={$q}|peran={$peran}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $peran, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('teknisi.nama', $q)->orLike('teknisi.kode', $q)
                    ->groupEnd();
            }
            if ($peran !== '') {
                $builder = $builder->where('teknisi.peran', $peran);
            }
            $rows = $builder->orderBy('teknisi.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/teknisi', [
            'title'     => $this->titleLabel,
            'rows'      => $data['rows'],
            'pager'     => $this->storePager($page, $per, $data['total']),
            'q'         => $q,
            'peran'     => $peran,
            'per'       => $per,
            'total'     => $data['total'],
            'guruOpts'  => (new GuruModel())->options(),
            'peranList' => TeknisiModel::PERAN,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah teknisi ' . $data['nama']);

        return $this->goIndex('Teknisi ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah teknisi ' . $data['nama']);

        return $this->goIndex('Teknisi diperbarui.');
    }

    private function collect(): array
    {
        $post  = fn (string $k) => trim((string) $this->request->getPost($k));
        $peran = strtolower($post('peran'));

        return [
            'kode'       => strtoupper($post('kode')),
            'nama'       => $post('nama'),
            'peran'      => in_array($peran, TeknisiModel::PERAN, true) ? $peran : 'teknisi',
            'no_hp'      => $post('no_hp') ?: null,
            'guru_id'    => (int) $this->request->getPost('guru_id') ?: null,
            'keterangan' => $post('keterangan') ?: null,
        ];
    }

    /** Lepas rujukan teknisi di lab/peminjaman/kerusakan/perbaikan/jurnal (soft delete → set null manual). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('lab')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('peminjaman')->whereIn('petugas_id', $ids)->update(['petugas_id' => null]);
        $db->table('kerusakan')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('perbaikan')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('jurnal_lab')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->withRelations()->orderBy('teknisi.nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Teknisi',
            ['No', 'Kode', 'Nama', 'Peran', 'No HP', 'Guru Tertaut', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 14, 'E' => 16, 'F' => 26, 'G' => 30]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], ucfirst(str_replace('_', ' ', $d['peran'])),
                "'" . $d['no_hp'], $d['guru_nama'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Teknisi-' . date('Ymd-His'), 'G');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Teknisi',
            ['Kode', 'Nama', 'Peran (teknisi/kepala_lab/laboran/lainnya)', 'No HP', 'Keterangan'],
            ['A' => 14, 'B' => 28, 'C' => 40, 'D' => 16, 'E' => 30]
        );
        $sheet->fromArray(['TKN01', 'Budi Santoso', 'teknisi', '081234567890', ''], null, 'A2', true);
        $sheet->getStyle('D2:D500')->getNumberFormat()->setFormatCode('@');

        $this->streamXlsx($ss, 'Template-Import-Teknisi');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',       'label' => 'Kode',       'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama',       'label' => 'Nama',       'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'peran',      'label' => 'Peran',      'type' => 'select', 'options' => TeknisiModel::PERAN, 'width' => 140],
            ['key' => 'no_hp',      'label' => 'No HP',      'type' => 'text',   'width' => 120],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'text',   'width' => 200],
        ];
    }

    protected function matchField(): string
    {
        return 'kode';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $kode = strtoupper(trim((string) ($row['kode'] ?? '')));
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($kode === '' && $nama === '') {
            return null;
        }
        if ($kode === '' || $nama === '') {
            $error = 'Baris ' . $line . ': kode/nama kosong.';

            return null;
        }
        $peran = strtolower(trim((string) ($row['peran'] ?? '')));

        return [
            'kode'       => $kode,
            'nama'       => $nama,
            'peran'      => in_array($peran, TeknisiModel::PERAN, true) ? $peran : 'teknisi',
            'no_hp'      => trim((string) ($row['no_hp'] ?? '')) ?: null,
            'guru_id'    => null,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }
}
