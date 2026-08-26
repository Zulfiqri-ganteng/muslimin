<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\PeminjamanModel;
use App\Models\TeknisiModel;

/**
 * Peminjaman & Pengembalian aset lab (alur/workflow).
 *
 * Pinjam  → catat + set aset.status = 'dipinjam'.
 * Kembali → isi tanggal/kondisi kembali + set aset.status kembali 'tersedia'
 *           (atau 'dihapus' bila barang dinyatakan hilang). Pengembalian
 *           MEMPERBARUI baris peminjaman yang sama, tidak menambah baris baru.
 */
class Peminjaman extends BaseController
{
    protected PeminjamanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PeminjamanModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, PeminjamanModel::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('peminjaman.peminjam_nama', $q)
                ->groupEnd();
        }
        if ($status !== '') {
            $builder = $builder->where('peminjaman.status', $status);
        }
        $rows  = $builder->orderBy('peminjaman.tanggal_pinjam', 'DESC')->orderBy('peminjaman.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        $today      = date('Y-m-d');
        $sedang     = $this->model->where('status', 'dipinjam')->countAllResults();
        $terlambat  = $this->model->where('status', 'dipinjam')
            ->where('tanggal_kembali_rencana IS NOT NULL')
            ->where('tanggal_kembali_rencana <', $today)->countAllResults();

        return view('admin/peminjaman/index', [
            'title'       => 'Peminjaman Barang',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'status'      => $status,
            'per'         => $per,
            'today'       => $today,
            'sedang'      => $sedang,
            'terlambat'   => $terlambat,
            'asetOpts'    => (new AsetModel())->optionsTersedia(),
            'petugasOpts' => (new TeknisiModel())->options(),
            'statusList'  => PeminjamanModel::STATUS,
            'kondisiList' => AsetModel::KONDISI,
        ]);
    }

    /** Pinjamkan barang: catat peminjaman + tandai aset 'dipinjam'. */
    public function store()
    {
        $asetModel = new AsetModel();
        $asetId    = (int) $this->request->getPost('aset_id');
        $aset      = $asetId ? $asetModel->find($asetId) : null;
        if (! $aset) {
            return redirect()->back()->withInput()->with('error', 'Aset tidak ditemukan.');
        }
        if ($aset['status'] !== 'tersedia') {
            return redirect()->back()->withInput()->with('error', 'Aset tidak tersedia (sedang ' . $aset['status'] . ').');
        }

        $data = $this->collectPinjam($asetId);
        $data['status'] = 'dipinjam';

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->insert($data);
        $newId = $this->model->getInsertID();
        $asetModel->update($asetId, ['status' => 'dipinjam']);
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('create', 'peminjaman', $newId, 'Pinjam aset ' . $aset['nomor_aset'] . ' oleh ' . $data['peminjam_nama']);

        return redirect()->to(site_url('admin/peminjaman'))->with('success', 'Peminjaman dicatat. Aset kini berstatus dipinjam.');
    }

    /** Proses pengembalian (atau tandai hilang). */
    public function kembalikan($id)
    {
        $id  = (int) $id;
        $pinjam = $this->model->find($id);
        if (! $pinjam) {
            return redirect()->to(site_url('admin/peminjaman'))->with('error', 'Data peminjaman tidak ditemukan.');
        }
        if ($pinjam['status'] !== 'dipinjam') {
            return redirect()->to(site_url('admin/peminjaman'))->with('error', 'Peminjaman ini sudah selesai.');
        }

        $status = strtolower(trim((string) $this->request->getPost('status')));
        $status = in_array($status, ['dikembalikan', 'hilang'], true) ? $status : 'dikembalikan';

        $tglKembali = trim((string) $this->request->getPost('tanggal_kembali_aktual')) ?: date('Y-m-d');
        $kondisi    = strtolower(trim((string) $this->request->getPost('kondisi_kembali')));
        $kondisi    = in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : null;

        $upd = [
            'id'                     => $id,
            'tanggal_kembali_aktual' => $tglKembali,
            'kondisi_kembali'        => $kondisi,
            'status'                 => $status,
            'keterangan'             => trim((string) $this->request->getPost('keterangan')) ?: $pinjam['keterangan'],
        ];

        $asetModel = new AsetModel();
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->update($id, $upd);
        if ($pinjam['aset_id']) {
            if ($status === 'hilang') {
                // Barang hilang → tak tersedia lagi (bisa dipulihkan lewat Master Aset).
                $asetModel->update($pinjam['aset_id'], ['status' => 'dihapus']);
            } else {
                $asetUpd = ['status' => 'tersedia'];
                if ($kondisi !== null) {
                    $asetUpd['kondisi'] = $kondisi; // kondisi aset mengikuti saat kembali
                }
                $asetModel->update($pinjam['aset_id'], $asetUpd);
            }
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('update', 'peminjaman', $id, 'Pengembalian (' . $status . ')');

        $pesan = $status === 'hilang' ? 'Barang ditandai hilang.' : 'Pengembalian dicatat. Aset kembali tersedia.';

        return redirect()->to(site_url('admin/peminjaman'))->with('success', $pesan);
    }

    public function delete($id)
    {
        $id     = (int) $id;
        $pinjam = $this->model->find($id);
        if (! $pinjam) {
            return redirect()->to(site_url('admin/peminjaman'))->with('error', 'Data tidak ditemukan.');
        }

        $asetModel = new AsetModel();
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        // Bila masih dipinjam, bebaskan asetnya agar tak "nyangkut".
        if ($pinjam['status'] === 'dipinjam' && $pinjam['aset_id']) {
            $asetModel->update($pinjam['aset_id'], ['status' => 'tersedia']);
        }
        $this->model->delete($id);
        $db->transComplete();

        (new \App\Models\LabGambarModel())->hapusUntuk('peminjaman', [$id]);
        master_data_changed('aset');
        $this->audit->record('delete', 'peminjaman', $id, 'Hapus catatan peminjaman');

        return redirect()->to(site_url('admin/peminjaman'))->with('success', 'Catatan peminjaman dihapus.');
    }

    private function collectPinjam(int $asetId): array
    {
        $post   = fn (string $k) => trim((string) $this->request->getPost($k));
        $tipe   = strtolower($post('peminjam_tipe'));
        $kondisi = strtolower($post('kondisi_pinjam'));

        return [
            'aset_id'                 => $asetId,
            'peminjam_nama'           => $post('peminjam_nama'),
            'peminjam_tipe'           => in_array($tipe, PeminjamanModel::TIPE, true) ? $tipe : 'umum',
            'peminjam_ref'            => (int) $this->request->getPost('peminjam_ref') ?: null,
            'tujuan'                  => $post('tujuan') ?: null,
            'tanggal_pinjam'          => $post('tanggal_pinjam') ?: date('Y-m-d'),
            'tanggal_kembali_rencana' => $post('tanggal_kembali_rencana') ?: null,
            'kondisi_pinjam'          => in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : 'baik',
            'petugas_id'              => (int) $this->request->getPost('petugas_id') ?: null,
            'keterangan'              => $post('keterangan') ?: null,
        ];
    }
}
