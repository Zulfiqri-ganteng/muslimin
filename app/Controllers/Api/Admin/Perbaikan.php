<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\KerusakanModel;
use App\Models\PerbaikanModel;
use App\Models\SparepartModel;
use App\Models\SparepartMutasiModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Perbaikan / Maintenance / Penggantian komponen (API).
 * Cermin App\Controllers\Admin\Perbaikan. Rute: /api/v1/admin/perbaikan
 */
class Perbaikan extends BaseApiController
{
    protected PerbaikanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PerbaikanModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q     = trim((string) $this->request->getGet('q'));
        $jenis = trim((string) $this->request->getGet('jenis'));

        $b = $this->model
            ->select('perbaikan.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS teknisi_nama, sp.nama AS komponen_nama, sm.jumlah AS komponen_jumlah, sp.satuan AS komponen_satuan')
            ->join('aset', 'aset.id = perbaikan.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = perbaikan.teknisi_id', 'left')
            ->join('sparepart_mutasi sm', 'sm.perbaikan_id = perbaikan.id', 'left')
            ->join('sparepart sp', 'sp.id = sm.sparepart_id', 'left');
        if ($q !== '') {
            $b = $b->groupStart()->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('perbaikan.tindakan', $q)->groupEnd();
        }
        if (in_array($jenis, PerbaikanModel::JENIS, true)) {
            $b = $b->where('perbaikan.jenis', $jenis);
        }
        $rows  = $b->orderBy('perbaikan.tanggal', 'DESC')->orderBy('perbaikan.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        return $this->collection(
            array_map([$this, 'transform'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total]
        );
    }

    public function store(): ResponseInterface
    {
        $in        = $this->body();
        $asetModel = new AsetModel();
        $asetId    = (int) ($in['aset_id'] ?? 0);
        $aset      = $asetId ? $asetModel->find($asetId) : null;
        if (! $aset) {
            return $this->missing('Aset tidak ditemukan.');
        }

        $jenis  = strtolower((string) ($in['jenis'] ?? 'perbaikan'));
        $hasil  = strtolower((string) ($in['hasil'] ?? 'berhasil'));
        $status = strtolower((string) ($in['status'] ?? 'selesai'));
        $biaya  = trim((string) ($in['biaya'] ?? ''));
        $data = [
            'aset_id'      => $asetId,
            'kerusakan_id' => (int) ($in['kerusakan_id'] ?? 0) ?: null,
            'jenis'        => in_array($jenis, PerbaikanModel::JENIS, true) ? $jenis : 'perbaikan',
            'tanggal'      => trim((string) ($in['tanggal'] ?? '')) ?: date('Y-m-d'),
            'teknisi_id'   => (int) ($in['teknisi_id'] ?? 0) ?: null,
            'tindakan'     => trim((string) ($in['tindakan'] ?? '')),
            'hasil'        => in_array($hasil, PerbaikanModel::HASIL, true) ? $hasil : 'berhasil',
            'biaya'        => $biaya !== '' ? (float) $biaya : null,
            'status'       => in_array($status, PerbaikanModel::STATUS, true) ? $status : 'selesai',
            'keterangan'   => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];

        // Komponen opsional (validasi stok sebelum transaksi).
        $sparepartModel = new SparepartModel();
        $sparepartId    = (int) ($in['sparepart_id'] ?? 0);
        $jumlah         = (int) ($in['jumlah_komponen'] ?? 0);
        $sparepart      = null;
        if ($sparepartId > 0 && $jumlah > 0) {
            $sparepart = $sparepartModel->find($sparepartId);
            if (! $sparepart) {
                return $this->missing('Sparepart tidak ditemukan.');
            }
            if ((int) $sparepart['stok'] < $jumlah) {
                return $this->failure('Stok sparepart tidak cukup (tersisa ' . (int) $sparepart['stok'] . ').', 409);
            }
        }

        if (! $this->model->insert($data)) {
            return $this->invalid($this->model->errors());
        }
        $perbaikanId = (int) $this->model->getInsertID();

        if ($sparepart) {
            (new SparepartMutasiModel())->insert([
                'sparepart_id' => $sparepartId,
                'tanggal'      => $data['tanggal'],
                'tipe'         => 'keluar',
                'jumlah'       => $jumlah,
                'perbaikan_id' => $perbaikanId,
                'keterangan'   => 'Penggantian komponen (via mobile)',
                'petugas'      => null,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $sparepartModel->update($sparepartId, ['stok' => (int) $sparepart['stok'] - $jumlah]);
        }

        if ($data['status'] === 'selesai') {
            $kerusakanId = (int) ($data['kerusakan_id'] ?? 0);
            if ($kerusakanId > 0) {
                (new KerusakanModel())->update($kerusakanId, ['id' => $kerusakanId, 'status' => 'selesai']);
            }
            $sisa = (new KerusakanModel())->terbukaCount($asetId, $kerusakanId ?: null);
            if ($aset['status'] === 'perbaikan' && $sisa === 0) {
                $upd = ['status' => 'tersedia'];
                if ($data['hasil'] === 'berhasil') {
                    $upd['kondisi'] = 'baik';
                }
                $asetModel->update($asetId, $upd);
            }
        }

        master_data_changed('aset', 'sparepart');
        $this->audit->record('create', 'perbaikan', $perbaikanId, ucfirst($data['jenis']) . ' aset ' . $aset['nomor_aset'] . ' (via mobile)');

        return $this->created($this->transform($this->fresh($perbaikanId)), 'Perbaikan dicatat.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->missing('Data tidak ditemukan.');
        }

        $sparepartModel = new SparepartModel();
        $mutasiModel    = new SparepartMutasiModel();
        foreach ($mutasiModel->where('perbaikan_id', $id)->where('tipe', 'keluar')->findAll() as $m) {
            $sp = $sparepartModel->find($m['sparepart_id']);
            if ($sp) {
                $sparepartModel->update($m['sparepart_id'], ['stok' => (int) $sp['stok'] + (int) $m['jumlah']]);
            }
            $mutasiModel->delete($m['id']);
        }
        $this->model->delete($id);

        master_data_changed('aset', 'sparepart');
        $this->audit->record('delete', 'perbaikan', $id, 'Hapus perbaikan, stok dipulihkan (via mobile)');

        return $this->ok(null, 'Catatan perbaikan dihapus, stok komponen dipulihkan.');
    }

    private function fresh(int $id): array
    {
        return $this->model
            ->select('perbaikan.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS teknisi_nama, sp.nama AS komponen_nama, sm.jumlah AS komponen_jumlah, sp.satuan AS komponen_satuan')
            ->join('aset', 'aset.id = perbaikan.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = perbaikan.teknisi_id', 'left')
            ->join('sparepart_mutasi sm', 'sm.perbaikan_id = perbaikan.id', 'left')
            ->join('sparepart sp', 'sp.id = sm.sparepart_id', 'left')
            ->where('perbaikan.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'aset_id'         => ((int) ($r['aset_id'] ?? 0)) ?: null,
            'aset_nama'       => $r['aset_nama'] ?? null,
            'nomor_aset'      => $r['nomor_aset'] ?? null,
            'kerusakan_id'    => ((int) ($r['kerusakan_id'] ?? 0)) ?: null,
            'jenis'           => $r['jenis'],
            'tanggal'         => $r['tanggal'] ?? null,
            'teknisi_nama'    => $r['teknisi_nama'] ?? null,
            'tindakan'        => $r['tindakan'],
            'hasil'           => $r['hasil'],
            'biaya'           => $r['biaya'] !== null ? (float) $r['biaya'] : null,
            'status'          => $r['status'],
            'komponen_nama'   => $r['komponen_nama'] ?? null,
            'komponen_jumlah' => isset($r['komponen_jumlah']) ? (int) $r['komponen_jumlah'] : null,
            'komponen_satuan' => $r['komponen_satuan'] ?? null,
            'keterangan'      => $r['keterangan'] ?? null,
        ];
    }
}
