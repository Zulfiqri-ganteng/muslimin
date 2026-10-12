<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UkkAlur;
use App\Models\AuditModel;
use App\Models\PesertaUkkModel;
use App\Models\SertifikatUkkModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sertifikat UKK (API, fase UKK A2) — kelola DATA saja; cetak PDF tetap di web ('pdf_web', perlu login web).
 * Cermin App\Controllers\Admin\SertifikatUkk; aturan terbit bersama web di App\Libraries\UkkAlur::terbitkanSertifikat
 * (hanya peserta lulus, 1 peserta = 1 sertifikat, nomor otomatis "SERT-UKK-{tahun}-001", yang dulu dihapus dipulihkan).
 * Rute /api/v1/admin/sertifikat-ukk:
 *   GET ?q=&per=&page= · GET calon (peserta lulus yang belum bersertifikat)
 *   POST {peserta_ukk_id, tanggal_terbit?, keterangan?} · POST {id} {tanggal_terbit?, keterangan?} · DELETE {id}
 */
class SertifikatUkk extends BaseApiController
{
    protected SertifikatUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new SertifikatUkkModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q = trim((string) $this->request->getGet('q'));

        $b = $this->model->withRelations();
        if ($q !== '') {
            $b = $b->groupStart()
                ->like('sertifikat_ukk.nomor_sertifikat', $q)->orLike('siswa.nama', $q)->orLike('siswa.nis', $q)->groupEnd();
        }
        $rows  = $b->orderBy('sertifikat_ukk.tanggal_terbit', 'DESC')->orderBy('sertifikat_ukk.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        return $this->collection(array_map([self::class, 'data'], $rows), ['page' => $page, 'perPage' => $per, 'total' => $total]);
    }

    /** GET peserta berstatus lulus yang BELUM punya sertifikat → [{id, label}] (dropdown "Terbitkan"). */
    public function calon(): ResponseInterface
    {
        $list = [];
        foreach ((new PesertaUkkModel())->optionsLulusBelumSertifikat() as $id => $label) {
            $list[] = ['id' => (int) $id, 'label' => $label];
        }

        return $this->ok($list);
    }

    public function store(): ResponseInterface
    {
        $in    = $this->body();
        $hasil = UkkAlur::terbitkanSertifikat(
            (int) ($in['peserta_ukk_id'] ?? 0),
            trim((string) ($in['tanggal_terbit'] ?? '')),
            trim((string) ($in['keterangan'] ?? ''))
        );
        if (! $hasil['ok']) {
            return $this->tolakHasil($hasil);
        }
        $this->audit->record('create', 'sertifikat_ukk', $hasil['id'], 'Terbitkan sertifikat UKK ' . $hasil['nomor'] . ($hasil['dipulihkan'] ? ' (dipulihkan)' : '') . ' (via mobile)');

        return $this->created(self::data($this->segar($hasil['id'])), 'Sertifikat ' . $hasil['nomor'] . ' diterbitkan.');
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Sertifikat tidak ditemukan.');
        }
        $in         = $this->body();
        $tanggal    = trim((string) ($in['tanggal_terbit'] ?? '')) ?: date('Y-m-d');
        $keterangan = trim((string) ($in['keterangan'] ?? '')) ?: null;
        $err        = [];
        if (! UkkAlur::tanggalSah($tanggal)) {
            $err['tanggal_terbit'] = 'Format tanggal harus TTTT-BB-HH.';
        }
        if ($keterangan !== null && mb_strlen($keterangan) > 255) {
            $err['keterangan'] = 'Keterangan maksimal 255 karakter.';
        }
        if ($err !== []) {
            return $this->invalid($err);
        }
        if (! $this->model->update($id, ['id' => $id, 'tanggal_terbit' => $tanggal, 'keterangan' => $keterangan])) {
            return $this->invalid($this->model->errors());
        }
        $this->audit->record('update', 'sertifikat_ukk', $id, 'Ubah sertifikat UKK (via mobile)');

        return $this->ok(self::data($this->segar($id)), 'Sertifikat diperbarui.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Sertifikat tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'sertifikat_ukk', $id, 'Hapus sertifikat UKK (via mobile)');

        return $this->ok(null, 'Sertifikat dihapus.');
    }

    public static function data(array $r): array
    {
        return [
            'id'               => (int) $r['id'],
            'nomor_sertifikat' => $r['nomor_sertifikat'],
            'peserta_ukk_id'   => (int) $r['peserta_ukk_id'],
            'siswa_nama'       => $r['siswa_nama'] ?? null,
            'nis'              => $r['nis'] ?? null,
            'paket_nama'       => $r['paket_nama'] ?? null,
            'nilai_akhir'      => isset($r['nilai_akhir']) ? (float) $r['nilai_akhir'] : null,
            'predikat'         => $r['predikat'] ?? null,
            'tanggal_terbit'   => $r['tanggal_terbit'],
            'keterangan'       => $r['keterangan'] ?? null,
            'pdf_web'          => site_url('admin/sertifikat-ukk/pdf/' . (int) $r['id']),
        ];
    }

    private function segar(int $id): array
    {
        return $this->model->withRelations()->where('sertifikat_ukk.id', $id)->first() ?? [];
    }
}
