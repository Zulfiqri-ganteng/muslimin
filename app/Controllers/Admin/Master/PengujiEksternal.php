<?php

namespace App\Controllers\Admin\Master;

use App\Models\PengujiEksternalModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Master Penguji Eksternal UKK — penguji dari DUDI/industri (bukan guru), tabel tersendiri. */
class PengujiEksternal extends BaseMaster
{
    protected string $module     = 'penguji_eksternal';
    protected string $auditTable = 'penguji_eksternal';
    protected string $routeBase  = 'admin/master/penguji-eksternal';
    protected string $entity     = 'penguji eksternal';
    protected string $titleLabel = 'Master Penguji Eksternal';

    protected function makeModel(): Model
    {
        return new PengujiEksternalModel();
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
                    ->like('nama', $q)->orLike('kode', $q)->orLike('instansi', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/penguji_eksternal', [
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
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah penguji eksternal ' . $data['nama']);

        return $this->goIndex('Penguji eksternal ditambahkan.');
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
        $this->audit->record('update', $this->auditTable, $id, 'Ubah penguji eksternal ' . $data['nama']);

        return $this->goIndex('Penguji eksternal diperbarui.');
    }

    private function collect(): array
    {
        $post = fn (string $k) => trim((string) $this->request->getPost($k));

        return [
            'kode'       => strtoupper($post('kode')),
            'nama'       => $post('nama'),
            'instansi'   => $post('instansi') ?: null,
            'jabatan'    => $post('jabatan') ?: null,
            'no_hp'      => $post('no_hp') ?: null,
            'email'      => $post('email') ?: null,
            'keterangan' => $post('keterangan') ?: null,
        ];
    }

    /**
     * Rujukan penguji eksternal ada di penugasan jadwal (jadwal_ukk_penguji, hard
     * delete → hapus baris penugasannya) dan riwayat nilai (nilai_ukk, soft delete
     * → lepas rujukannya saja agar skor historis tetap tersimpan).
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal_ukk_penguji')->whereIn('penguji_eksternal_id', $ids)->delete();
        $db->table('nilai_ukk')->whereIn('penguji_eksternal_id', $ids)->update(['penguji_eksternal_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->orderBy('nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Penguji Eksternal',
            ['No', 'Kode', 'Nama', 'Instansi', 'Jabatan', 'No HP', 'Email', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 26, 'D' => 26, 'E' => 20, 'F' => 16, 'G' => 24, 'H' => 30]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], $d['instansi'], $d['jabatan'],
                "'" . $d['no_hp'], $d['email'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Penguji-Eksternal-' . date('Ymd-His'), 'H');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Penguji Eksternal',
            ['Kode', 'Nama', 'Instansi', 'Jabatan', 'No HP', 'Email', 'Keterangan'],
            ['A' => 14, 'B' => 26, 'C' => 26, 'D' => 20, 'E' => 16, 'F' => 24, 'G' => 30]
        );
        $sheet->fromArray(['PE01', 'Ahmad Fauzi', 'PT Contoh Teknologi', 'Manager IT', '081234567890', 'ahmad@contoh.com', ''], null, 'A2', true);
        $sheet->getStyle('E2:E500')->getNumberFormat()->setFormatCode('@');

        $this->streamXlsx($ss, 'Template-Import-Penguji-Eksternal');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',       'label' => 'Kode',       'type' => 'text', 'required' => true, 'width' => 120],
            ['key' => 'nama',       'label' => 'Nama',       'type' => 'text', 'required' => true, 'width' => 220],
            ['key' => 'instansi',   'label' => 'Instansi',   'type' => 'text', 'width' => 220],
            ['key' => 'jabatan',    'label' => 'Jabatan',    'type' => 'text', 'width' => 160],
            ['key' => 'no_hp',      'label' => 'No HP',      'type' => 'text', 'width' => 120],
            ['key' => 'email',      'label' => 'Email',      'type' => 'text', 'width' => 200],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'text', 'width' => 200],
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

        return [
            'kode'       => $kode,
            'nama'       => $nama,
            'instansi'   => trim((string) ($row['instansi'] ?? '')) ?: null,
            'jabatan'    => trim((string) ($row['jabatan'] ?? '')) ?: null,
            'no_hp'      => trim((string) ($row['no_hp'] ?? '')) ?: null,
            'email'      => trim((string) ($row['email'] ?? '')) ?: null,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }
}
