<?php

namespace App\Controllers\Api\Admin;

use App\Models\AsetKomputerModel;
use App\Models\AsetModel;
use App\Models\LabModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Aset / Inventaris (API). Cermin App\Controllers\Admin\Master\Aset.
 * Rute: /api/v1/admin/master/aset (+ /{id}/komputer untuk detail 1:1).
 * Nomor aset otomatis (KODELAB-KAT-###) bila dikirim kosong.
 */
class Aset extends BaseCrud
{
    protected string $module     = 'aset';
    protected string $auditTable = 'aset';
    protected string $entity     = 'aset';

    private const KAT_PREFIX = [
        'komputer' => 'KOM', 'laptop' => 'LTP', 'printer' => 'PRT', 'proyektor' => 'PRO',
        'jaringan' => 'NET', 'furnitur' => 'FRN', 'lainnya' => 'LNY',
    ];

    protected function makeModel(): Model
    {
        return new AsetModel();
    }

    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q        = trim((string) $this->request->getGet('q'));
        $labId    = (int) $this->request->getGet('lab_id');
        $kategori = strtolower(trim((string) $this->request->getGet('kategori')));
        $kondisi  = strtolower(trim((string) $this->request->getGet('kondisi')));
        $status   = strtolower(trim((string) $this->request->getGet('status')));

        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('aset.merk', $q)->groupEnd();
        }
        if ($labId > 0) {
            $builder = $builder->where('aset.lab_id', $labId);
        }
        if (in_array($kategori, AsetModel::KATEGORI, true)) {
            $builder = $builder->where('aset.kategori', $kategori);
        }
        if (in_array($kondisi, AsetModel::KONDISI, true)) {
            $builder = $builder->where('aset.kondisi', $kondisi);
        }
        if (in_array($status, AsetModel::STATUS, true)) {
            $builder = $builder->where('aset.status', $status);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('aset.nomor_aset', 'ASC');
    }

    protected function collect(array $in): array
    {
        $kategori = strtolower(trim((string) ($in['kategori'] ?? '')));
        $kondisi  = strtolower(trim((string) ($in['kondisi'] ?? '')));
        $status   = strtolower(trim((string) ($in['status'] ?? '')));
        $harga    = trim((string) ($in['harga'] ?? ''));
        $kategori = in_array($kategori, AsetModel::KATEGORI, true) ? $kategori : 'komputer';
        $labId    = (int) ($in['lab_id'] ?? 0) ?: null;

        $nomor = strtoupper(trim((string) ($in['nomor_aset'] ?? '')));
        if ($nomor === '') {
            $nomor = $this->generateNomor($labId, $kategori);
        }

        return [
            'nomor_aset'      => $nomor,
            'nama'            => trim((string) ($in['nama'] ?? '')),
            'kategori'        => $kategori,
            'lab_id'          => $labId,
            'merk'            => trim((string) ($in['merk'] ?? '')) ?: null,
            'spesifikasi'     => trim((string) ($in['spesifikasi'] ?? '')) ?: null,
            'tahun_pengadaan' => (int) ($in['tahun_pengadaan'] ?? 0) ?: null,
            'sumber_dana'     => trim((string) ($in['sumber_dana'] ?? '')) ?: null,
            'harga'           => $harga !== '' ? (float) $harga : null,
            'kondisi'         => in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : 'baik',
            'status'          => in_array($status, AsetModel::STATUS, true) ? $status : 'tersedia',
            'keterangan'      => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'nomor_aset'      => $r['nomor_aset'],
            'nama'            => $r['nama'],
            'kategori'        => $r['kategori'],
            'lab_id'          => ((int) ($r['lab_id'] ?? 0)) ?: null,
            'lab_nama'        => $r['lab_nama'] ?? null,
            'merk'            => $r['merk'] ?? null,
            'spesifikasi'     => $r['spesifikasi'] ?? null,
            'tahun_pengadaan' => ((int) ($r['tahun_pengadaan'] ?? 0)) ?: null,
            'sumber_dana'     => $r['sumber_dana'] ?? null,
            'harga'           => $r['harga'] !== null ? (float) $r['harga'] : null,
            'kondisi'         => $r['kondisi'],
            'status'          => $r['status'],
            'is_komputer'     => in_array($r['kategori'], ['komputer', 'laptop'], true),
            'keterangan'      => $r['keterangan'] ?? null,
        ];
    }

    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('aset_komputer')->whereIn('aset_id', $ids)->delete();
    }

    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('aset.id', $id)->first() ?? [];
    }

    private function generateNomor(?int $labId, string $kategori): string
    {
        $labKode = 'UMUM';
        if ($labId) {
            $lab = (new LabModel())->find($labId);
            if ($lab) {
                $labKode = strtoupper($lab['kode']);
            }
        }
        $prefix = $labKode . '-' . (self::KAT_PREFIX[$kategori] ?? 'LNY') . '-';

        $max = 0;
        foreach ($this->model->withDeleted()->select('nomor_aset')->like('nomor_aset', $prefix, 'after')->findAll() as $r) {
            if (preg_match('/(\d+)$/', (string) $r['nomor_aset'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    // ===================== DETAIL KOMPUTER (1:1) =====================

    /** GET /admin/master/aset/{id}/komputer → detail komputer (atau objek kosong). */
    public function komputerGet($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Aset tidak ditemukan.');
        }

        return $this->ok((new AsetKomputerModel())->forAset($id) ?? []);
    }

    /** POST /admin/master/aset/{id}/komputer → simpan detail komputer (upsert). */
    public function komputerSet($id = null): ResponseInterface
    {
        $id   = (int) $id;
        $aset = $this->model->find($id);
        if (! $aset) {
            return $this->missing('Aset tidak ditemukan.');
        }

        $in   = $this->body();
        $teks = static fn (string $k) => trim((string) ($in[$k] ?? '')) ?: null;
        $data = [
            'aset_id'     => $id,
            'hostname'    => $teks('hostname'),
            'processor'   => $teks('processor'),
            'ram'         => $teks('ram'),
            'storage'     => $teks('storage'),
            'gpu'         => $teks('gpu'),
            'os'          => $teks('os'),
            'mac_address' => $teks('mac_address'),
            'ip_address'  => $teks('ip_address'),
            'monitor'     => $teks('monitor'),
            'keterangan'  => $teks('keterangan'),
        ];

        $km       = new AsetKomputerModel();
        $existing = $km->forAset($id);
        if ($existing) {
            $data['id'] = $existing['id'];
            $ok         = $km->update($existing['id'], $data);
        } else {
            $ok = $km->insert($data);
        }
        if (! $ok) {
            return $this->invalid($km->errors());
        }

        master_data_changed('aset');
        $this->audit->record('update', 'aset_komputer', $id, 'Detail komputer aset ' . $aset['nomor_aset'] . ' (via mobile)');

        return $this->ok($km->forAset($id) ?? [], 'Detail komputer tersimpan.');
    }
}
