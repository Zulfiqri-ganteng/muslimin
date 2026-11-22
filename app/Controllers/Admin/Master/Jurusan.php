<?php

namespace App\Controllers\Admin\Master;

use App\Models\JurusanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Jurusan extends BaseMaster
{
    protected string $module     = 'jurusan';
    protected string $auditTable = 'jurusan';
    protected string $routeBase  = 'admin/master/jurusan';
    protected string $entity     = 'jurusan';
    protected string $titleLabel = 'Master Jurusan';

    protected function makeModel(): Model
    {
        return new JurusanModel();
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|per={$per}|p={$page}", function () use ($q, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('nama', $q)->orLike('kode', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('kode', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/jurusan', [
            'title' => $this->titleLabel,
            'rows'  => $data['rows'],
            'pager' => $this->storePager($page, $per, $data['total']),
            'q'     => $q,
            'per'   => $per,
            'total' => $data['total'],
        ]);
    }

    public function store()
    {
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
            'label_ujian' => strtoupper(trim((string) $this->request->getPost('label_ujian'))) ?: null,
            'urutan_ujian' => ctype_digit(trim((string) $this->request->getPost('urutan_ujian'))) ? (int) $this->request->getPost('urutan_ujian') : null,
        ];

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah jurusan ' . $data['kode']);

        return $this->goIndex('Jurusan berhasil ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
            'label_ujian' => strtoupper(trim((string) $this->request->getPost('label_ujian'))) ?: null,
            'urutan_ujian' => ctype_digit(trim((string) $this->request->getPost('urutan_ujian'))) ? (int) $this->request->getPost('urutan_ujian') : null,
            'id'   => $id, // isi placeholder {id} pada rule is_unique saat edit
        ];

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah jurusan ' . $data['kode']);

        return $this->goIndex('Jurusan berhasil diperbarui.');
    }

    /** Anti-orphan: kelas yang memakai jurusan ini dilepas (jurusan_id = NULL). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('kelas')->whereIn('jurusan_id', $ids)->update(['jurusan_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('kode', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Jurusan',
            ['No', 'Kode', 'Nama Jurusan', 'Label di Jadwal Ujian', 'Urutan di Jadwal Ujian'],
            ['A' => 5, 'B' => 14, 'C' => 40, 'D' => 22, 'E' => 22]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['kode'], $d['nama'], $d['label_ujian'], $d['urutan_ujian']], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Jurusan-' . date('Ymd-His'), 'C');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Jurusan',
            ['Kode', 'Nama Jurusan', 'Label di Jadwal Ujian', 'Urutan di Jadwal Ujian'],
            ['A' => 14, 'B' => 40, 'C' => 22, 'D' => 22]
        );
        $sheet->fromArray(['TKJT', 'Teknik Komputer Jaringan & Telekomunikasi', 'TKJ', 3], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Jurusan');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode', 'label' => 'Kode',         'type' => 'text', 'required' => true, 'width' => 130],
            ['key' => 'nama', 'label' => 'Nama Jurusan', 'type' => 'text', 'required' => true, 'width' => 320],
            // (2026-11-22) kolom TERAKHIR agar template lama (2 kolom) tetap terbaca.
            ['key' => 'label_ujian', 'label' => 'Label di Jadwal Ujian', 'type' => 'text', 'width' => 160],
            ['key' => 'urutan_ujian', 'label' => 'Urutan di Jadwal Ujian', 'type' => 'number', 'width' => 120],
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

        // Sel label kosong (atau template lama) TIDAK menghapus label yang sudah diisi.
        $label = strtoupper(trim((string) ($row['label_ujian'] ?? '')));

        $urut  = trim((string) ($row['urutan_ujian'] ?? ''));

        return ['kode' => $kode, 'nama' => $nama] + ($label !== '' ? ['label_ujian' => $label] : []) + (ctype_digit($urut) ? ['urutan_ujian' => (int) $urut] : []);
    }
}
