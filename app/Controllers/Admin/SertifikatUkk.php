<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\PesertaUkkModel;
use App\Models\SertifikatUkkModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Sertifikat kelulusan UKK (workflow, bukan master data). Hanya peserta
 * berstatus 'lulus' yang bisa diterbitkan sertifikatnya (1 peserta = 1
 * sertifikat, dijaga UNIQUE di `sertifikat_ukk`). Nomor otomatis
 * "SERT-UKK-{tahun}-001".
 */
class SertifikatUkk extends BaseController
{
    protected SertifikatUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new SertifikatUkkModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('sertifikat_ukk.nomor_sertifikat', $q)->orLike('siswa.nama', $q)->orLike('siswa.nis', $q)
                ->groupEnd();
        }
        $rows  = $builder->orderBy('sertifikat_ukk.tanggal_terbit', 'DESC')->orderBy('sertifikat_ukk.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        return view('admin/sertifikat_ukk/index', [
            'title'      => 'Sertifikat UKK',
            'rows'       => $rows,
            'pager'      => $pager,
            'q'          => $q,
            'per'        => $per,
            'pesertaOpts' => (new PesertaUkkModel())->optionsLulusBelumSertifikat(),
        ]);
    }

    public function store()
    {
        $pesertaId = (int) $this->request->getPost('peserta_ukk_id');
        $peserta   = $pesertaId ? (new PesertaUkkModel())->find($pesertaId) : null;
        if (! $peserta) {
            return redirect()->back()->with('error', 'Peserta wajib dipilih.');
        }
        if ($peserta['status'] !== 'lulus') {
            return redirect()->back()->with('error', 'Sertifikat hanya bisa diterbitkan untuk peserta berstatus lulus.');
        }

        $data = [
            'peserta_ukk_id'   => $pesertaId,
            'nomor_sertifikat' => $this->model->nomorBerikutnya(),
            'tanggal_terbit'   => trim((string) $this->request->getPost('tanggal_terbit')) ?: date('Y-m-d'),
            'keterangan'       => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'sertifikat_ukk', $this->model->getInsertID(), 'Terbitkan sertifikat UKK ' . $data['nomor_sertifikat']);

        return redirect()->to(site_url('admin/sertifikat-ukk'))->with('success', 'Sertifikat ' . $data['nomor_sertifikat'] . ' diterbitkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/sertifikat-ukk'))->with('error', 'Sertifikat tidak ditemukan.');
        }

        $data = [
            'id'             => $id,
            'tanggal_terbit' => trim((string) $this->request->getPost('tanggal_terbit')) ?: date('Y-m-d'),
            'keterangan'     => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'sertifikat_ukk', $id, 'Ubah sertifikat UKK');

        return redirect()->to(site_url('admin/sertifikat-ukk'))->with('success', 'Sertifikat diperbarui.');
    }

    /** @param int|string $id */
    public function delete($id)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/sertifikat-ukk'))->with('error', 'Sertifikat tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'sertifikat_ukk', $id, 'Hapus sertifikat UKK');

        return redirect()->to(site_url('admin/sertifikat-ukk'))->with('success', 'Sertifikat dihapus.');
    }

    /** @param int|string $id */
    public function pdf($id)
    {
        $id  = (int) $id;
        $srt = $this->model->withRelations()->where('sertifikat_ukk.id', $id)->first();
        if (! $srt) {
            return redirect()->to(site_url('admin/sertifikat-ukk'))->with('error', 'Sertifikat tidak ditemukan.');
        }

        $html = view('pdf/sertifikat_ukk', [
            'srt'     => $srt,
            'setting' => (new SettingModel())->get(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('Sertifikat-' . $srt['nomor_sertifikat'] . '.pdf', ['Attachment' => false]);
        exit;
    }
}
