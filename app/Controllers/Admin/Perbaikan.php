<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\KerusakanModel;
use App\Models\PerbaikanModel;
use App\Models\SparepartModel;
use App\Models\SparepartMutasiModel;
use App\Models\TeknisiModel;

/**
 * Perbaikan / Maintenance / Penggantian komponen (alur/workflow).
 *
 * Bila mengganti komponen (pilih sparepart + jumlah), stok sparepart otomatis
 * berkurang lewat mutasi keluar (dan dipulihkan bila catatan perbaikan dihapus).
 * Bila perbaikan berstatus 'selesai' dan dikaitkan ke sebuah kerusakan,
 * kerusakan itu ditandai selesai & asetnya dikembalikan 'tersedia'.
 */
class Perbaikan extends BaseController
{
    protected PerbaikanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PerbaikanModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $jenis = trim((string) $this->request->getGet('jenis'));
        if (! in_array($jenis, PerbaikanModel::JENIS, true)) {
            $jenis = '';
        }
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        // Query khusus: ikut membawa komponen yang diganti (bila ada).
        $builder = $this->model
            ->select('perbaikan.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS teknisi_nama, sp.nama AS komponen_nama, sm.jumlah AS komponen_jumlah, sp.satuan AS komponen_satuan')
            ->join('aset', 'aset.id = perbaikan.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = perbaikan.teknisi_id', 'left')
            ->join('sparepart_mutasi sm', 'sm.perbaikan_id = perbaikan.id', 'left')
            ->join('sparepart sp', 'sp.id = sm.sparepart_id', 'left');
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('perbaikan.tindakan', $q)
                ->groupEnd();
        }
        if ($jenis !== '') {
            $builder = $builder->where('perbaikan.jenis', $jenis);
        }
        $rows  = $builder->orderBy('perbaikan.tanggal', 'DESC')->orderBy('perbaikan.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        return view('admin/perbaikan/index', [
            'title'         => 'Perbaikan & Maintenance',
            'rows'          => $rows,
            'pager'         => $pager,
            'q'             => $q,
            'jenis'         => $jenis,
            'per'           => $per,
            'asetOpts'      => (new AsetModel())->options(),
            'teknisiOpts'   => (new TeknisiModel())->options(),
            'sparepartOpts' => (new SparepartModel())->options(),
            'kerusakanOpts' => (new KerusakanModel())->optionsTerbuka(),
            'jenisList'     => PerbaikanModel::JENIS,
            'hasilList'     => PerbaikanModel::HASIL,
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

        // Komponen diganti (opsional): validasi stok SEBELUM transaksi.
        $sparepartModel = new SparepartModel();
        $sparepartId    = (int) $this->request->getPost('sparepart_id');
        $jumlah         = (int) $this->request->getPost('jumlah_komponen');
        $sparepart      = null;
        if ($sparepartId > 0 && $jumlah > 0) {
            $sparepart = $sparepartModel->find($sparepartId);
            if (! $sparepart) {
                return redirect()->back()->withInput()->with('error', 'Sparepart tidak ditemukan.');
            }
            if ((int) $sparepart['stok'] < $jumlah) {
                return redirect()->back()->withInput()->with('error', 'Stok sparepart tidak cukup (tersisa ' . (int) $sparepart['stok'] . ').');
            }
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        $this->model->insert($data);
        $perbaikanId = $this->model->getInsertID();

        // Penggantian komponen → mutasi keluar + kurangi stok.
        if ($sparepart) {
            (new SparepartMutasiModel())->insert([
                'sparepart_id' => $sparepartId,
                'tanggal'      => $data['tanggal'],
                'tipe'         => 'keluar',
                'jumlah'       => $jumlah,
                'perbaikan_id' => $perbaikanId,
                'keterangan'   => 'Penggantian komponen (perbaikan aset ' . $aset['nomor_aset'] . ')',
                'petugas'      => null,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $sparepartModel->update($sparepartId, ['stok' => (int) $sparepart['stok'] - $jumlah]);
        }

        // Perbaikan tuntas → tuntaskan kerusakan terkait & bebaskan aset.
        if ($data['status'] === 'selesai') {
            $kerusakanId = (int) ($data['kerusakan_id'] ?? 0);
            if ($kerusakanId > 0) {
                (new KerusakanModel())->update($kerusakanId, ['id' => $kerusakanId, 'status' => 'selesai']);
            }
            $sisaTerbuka = (new KerusakanModel())->terbukaCount($asetId, $kerusakanId ?: null);
            if ($aset['status'] === 'perbaikan' && $sisaTerbuka === 0) {
                $asetUpd = ['status' => 'tersedia'];
                if ($data['hasil'] === 'berhasil') {
                    $asetUpd['kondisi'] = 'baik';
                }
                $asetModel->update($asetId, $asetUpd);
            }
        }

        $db->transComplete();

        master_data_changed('aset', 'sparepart');
        $this->audit->record('create', 'perbaikan', $perbaikanId, ucfirst($data['jenis']) . ' aset ' . $aset['nomor_aset']);

        return redirect()->to(site_url('admin/perbaikan'))->with('success', 'Perbaikan dicatat.'
            . ($sparepart ? ' Stok ' . $sparepart['nama'] . ' berkurang ' . $jumlah . '.' : ''));
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/perbaikan'))->with('error', 'Data tidak ditemukan.');
        }

        $sparepartModel = new SparepartModel();
        $mutasiModel    = new SparepartMutasiModel();

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        // Pulihkan stok dari komponen yang sempat dikeluarkan.
        foreach ($mutasiModel->where('perbaikan_id', $id)->where('tipe', 'keluar')->findAll() as $m) {
            $sp = $sparepartModel->find($m['sparepart_id']);
            if ($sp) {
                $sparepartModel->update($m['sparepart_id'], ['stok' => (int) $sp['stok'] + (int) $m['jumlah']]);
            }
            $mutasiModel->delete($m['id']);
        }
        $this->model->delete($id);
        $db->transComplete();

        (new \App\Models\LabGambarModel())->hapusUntuk('perbaikan', [$id]);
        master_data_changed('aset', 'sparepart');
        $this->audit->record('delete', 'perbaikan', $id, 'Hapus catatan perbaikan (stok komponen dipulihkan)');

        return redirect()->to(site_url('admin/perbaikan'))->with('success', 'Catatan perbaikan dihapus, stok komponen dipulihkan.');
    }

    private function collect(int $asetId): array
    {
        $post  = fn (string $k) => trim((string) $this->request->getPost($k));
        $jenis = strtolower($post('jenis'));
        $hasil = strtolower($post('hasil'));
        $status = strtolower($post('status'));
        $biaya = $post('biaya');

        return [
            'aset_id'      => $asetId,
            'kerusakan_id' => (int) $this->request->getPost('kerusakan_id') ?: null,
            'jenis'        => in_array($jenis, PerbaikanModel::JENIS, true) ? $jenis : 'perbaikan',
            'tanggal'      => $post('tanggal') ?: date('Y-m-d'),
            'teknisi_id'   => (int) $this->request->getPost('teknisi_id') ?: null,
            'tindakan'     => $post('tindakan'),
            'hasil'        => in_array($hasil, PerbaikanModel::HASIL, true) ? $hasil : 'berhasil',
            'biaya'        => $biaya !== '' ? (float) $biaya : null,
            'status'       => in_array($status, PerbaikanModel::STATUS, true) ? $status : 'selesai',
            'keterangan'   => $post('keterangan') ?: null,
        ];
    }
}
