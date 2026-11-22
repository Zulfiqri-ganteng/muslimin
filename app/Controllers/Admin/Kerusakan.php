<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\KerusakanModel;
use App\Models\TeknisiModel;

/**
 * Laporan Kerusakan aset (alur/workflow).
 *
 * Lapor  → catat + set aset.status = 'perbaikan'.
 * Status → dilaporkan → diproses → selesai / tak_teratasi.
 * Saat kerusakan selesai/tak_teratasi (dan tak ada kerusakan lain terbuka),
 * aset dikembalikan ke 'tersedia'. Tindakan detail dicatat di menu Perbaikan.
 */
class Kerusakan extends BaseController
{
    use HapusMassal;

    protected KerusakanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new KerusakanModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    /** Saringan daftar dari GET (halaman) atau POST (hapus massal). @return array{0: string, 1: string} [q, status] */
    private function saringan(string $sumber = 'get'): array
    {
        $ambil  = fn (string $k) => $sumber === 'post' ? $this->request->getPost($k) : $this->request->getGet($k);
        $q      = trim((string) $ambil('q'));
        $status = trim((string) $ambil('status'));

        return [$q, in_array($status, KerusakanModel::STATUS, true) ? $status : ''];
    }

    /** Daftar tersaring (dipakai halaman & "Hapus semua" supaya isinya sama persis). */
    private function daftarTersaring(string $q, string $status)
    {
        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('kerusakan.deskripsi', $q)
                ->groupEnd();
        }
        if ($status !== '') {
            $builder = $builder->where('kerusakan.status', $status);
        }

        return $builder;
    }

    public function index()
    {
        [$q, $status] = $this->saringan();
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $rows  = $this->daftarTersaring($q, $status)->orderBy('kerusakan.tanggal_lapor', 'DESC')->orderBy('kerusakan.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        $terbuka = $this->model->whereIn('status', ['dilaporkan', 'diproses'])->countAllResults();

        return view('admin/kerusakan/index', [
            'title'       => 'Kerusakan Aset',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'status'      => $status,
            'per'         => $per,
            'terbuka'     => $terbuka,
            'asetOpts'    => (new AsetModel())->options(),
            'teknisiOpts' => (new TeknisiModel())->options(),
            'tingkatList' => KerusakanModel::TINGKAT,
            'statusList'  => KerusakanModel::STATUS,
        ]);
    }

    public function store()
    {
        $asetModel = new AsetModel();
        $asetId    = (int) $this->request->getPost('aset_id');
        $aset      = $asetId ? $asetModel->find($asetId) : null;
        if (! $aset) {
            return redirect()->back()->withInput()->with('error', 'Aset tidak ditemukan.');
        }

        $data = $this->collect($asetId);
        $data['status'] = 'dilaporkan';

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->insert($data);
        $newId = $this->model->getInsertID();
        // Aset yang dilaporkan rusak → tandai sedang perbaikan.
        if ($aset['status'] !== 'dihapus') {
            $asetModel->update($asetId, ['status' => 'perbaikan']);
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('create', 'kerusakan', $newId, 'Lapor kerusakan aset ' . $aset['nomor_aset']);

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Kerusakan dicatat. Aset ditandai sedang perbaikan.');
    }

    /** Ubah status kerusakan (diproses/selesai/tak_teratasi/dilaporkan). */
    public function status($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Data tidak ditemukan.');
        }
        $status = strtolower(trim((string) $this->request->getPost('status')));
        if (! in_array($status, KerusakanModel::STATUS, true)) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Status tidak valid.');
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->update($id, ['id' => $id, 'status' => $status]);
        // Kerusakan tuntas → bebaskan aset bila tak ada kerusakan lain yang terbuka.
        if (in_array($status, ['selesai', 'tak_teratasi'], true) && $row['aset_id']) {
            $this->bebaskanAset((int) $row['aset_id'], $id);
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('update', 'kerusakan', $id, 'Status kerusakan → ' . $status);

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Status kerusakan diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Data tidak ditemukan.');
        }

        $this->hapusBanyak([$id]);
        $this->audit->record('delete', 'kerusakan', $id, 'Hapus laporan kerusakan');

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Laporan kerusakan dihapus.');
    }

    /** Hapus massal: "Hapus terpilih" (ids[]) atau "Hapus semua" yang cocok saringan — aturan sama dengan Hapus satuan. */
    public function bulkDelete()
    {
        [$q, $status] = $this->saringan('post');
        [$mode, $ids] = $this->idHapusMassal(
            fn () => array_column($this->daftarTersaring($q, $status)->select('kerusakan.id AS id_hapus')->findAll(), 'id_hapus'),
            fn (array $ids) => array_column($this->model->select('id')->whereIn('id', $ids)->findAll(), 'id')
        );
        $kembali = $this->alamatDaftarMassal(site_url('admin/kerusakan'), ['q', 'status']);
        if ($ids === []) {
            return redirect()->to($kembali)->with('error', $this->pesanTakAdaDipilih('laporan kerusakan'));
        }

        $this->hapusBanyak($ids);
        $this->audit->record('delete', 'kerusakan', null, 'Hapus massal ' . count($ids) . ' laporan kerusakan (' . $mode . ')');

        return redirect()->to($kembali)->with('success', count($ids) . ' laporan kerusakan dihapus.');
    }

    /**
     * Hapus laporan kerusakan (satu atau banyak) dalam satu transaksi, lalu aset yang tak lagi punya kerusakan terbuka
     * dibebaskan dari status 'perbaikan'. Foto lampirannya ikut dihapus.
     *
     * @param list<int> $ids
     */
    private function hapusBanyak(array $ids): void
    {
        $asetIds = array_values(array_unique(array_filter(array_map('intval', array_column(
            $this->model->select('aset_id')->whereIn('id', $ids)->findAll(),
            'aset_id'
        )))));

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->delete($ids);
        foreach ($asetIds as $asetId) {
            $this->bebaskanAset($asetId, 0); // laporan yang dihapus sudah tak terhitung (hapus lunak)
        }
        $db->transComplete();

        (new \App\Models\LabGambarModel())->hapusUntuk('kerusakan', $ids);
        master_data_changed('aset');
    }

    /** Set aset 'tersedia' bila statusnya 'perbaikan' dan tak ada kerusakan lain terbuka. */
    private function bebaskanAset(int $asetId, int $exceptKerusakanId): void
    {
        if ($this->model->terbukaCount($asetId, $exceptKerusakanId) > 0) {
            return;
        }
        $asetModel = new AsetModel();
        $aset      = $asetModel->find($asetId);
        if ($aset && $aset['status'] === 'perbaikan') {
            $asetModel->update($asetId, ['status' => 'tersedia']);
        }
    }

    private function collect(int $asetId): array
    {
        $post    = fn (string $k) => trim((string) $this->request->getPost($k));
        $tingkat = strtolower($post('tingkat'));

        return [
            'aset_id'       => $asetId,
            'tanggal_lapor' => $post('tanggal_lapor') ?: date('Y-m-d'),
            'pelapor'       => $post('pelapor') ?: null,
            'deskripsi'     => $post('deskripsi'),
            'tingkat'       => in_array($tingkat, KerusakanModel::TINGKAT, true) ? $tingkat : 'ringan',
            'teknisi_id'    => (int) $this->request->getPost('teknisi_id') ?: null,
            'keterangan'    => $post('keterangan') ?: null,
        ];
    }
}
