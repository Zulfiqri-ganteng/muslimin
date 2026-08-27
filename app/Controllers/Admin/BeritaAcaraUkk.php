<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\BeritaAcaraUkkModel;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\PesertaUkkModel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Berita Acara pelaksanaan UKK per jadwal (workflow, bukan master data).
 * Nomor otomatis "BA-UKK-{tahun}-001"; PDF berisi daftar peserta + penguji
 * + kolom tanda tangan (pola cetak sama seperti `Admin\Cetak`/`LaporanLab`).
 */
class BeritaAcaraUkk extends BaseController
{
    protected BeritaAcaraUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new BeritaAcaraUkkModel();
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
                ->like('berita_acara_ukk.nomor_ba', $q)->orLike('paket_soal_ukk.nama', $q)
                ->groupEnd();
        }
        $rows  = $builder->orderBy('berita_acara_ukk.tanggal', 'DESC')->orderBy('berita_acara_ukk.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        return view('admin/berita_acara_ukk/index', [
            'title'     => 'Berita Acara UKK',
            'rows'      => $rows,
            'pager'     => $pager,
            'q'         => $q,
            'per'       => $per,
            'jadwalOpts' => (new JadwalUkkModel())->options(),
        ]);
    }

    public function store()
    {
        $jadwalId = (int) $this->request->getPost('jadwal_ukk_id');
        if (! $jadwalId || ! (new JadwalUkkModel())->find($jadwalId)) {
            return redirect()->back()->withInput()->with('error', 'Jadwal UKK wajib dipilih.');
        }

        $data = [
            'jadwal_ukk_id' => $jadwalId,
            'nomor_ba'      => $this->model->nomorBerikutnya(),
            'tanggal'       => trim((string) $this->request->getPost('tanggal')) ?: date('Y-m-d'),
            'catatan'       => trim((string) $this->request->getPost('catatan')) ?: null,
            'keterangan'    => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'berita_acara_ukk', $this->model->getInsertID(), 'Buat berita acara UKK ' . $data['nomor_ba']);

        return redirect()->to(site_url('admin/berita-acara-ukk'))->with('success', 'Berita acara ' . $data['nomor_ba'] . ' dibuat.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/berita-acara-ukk'))->with('error', 'Berita acara tidak ditemukan.');
        }

        $data = [
            'id'         => $id,
            'tanggal'    => trim((string) $this->request->getPost('tanggal')) ?: date('Y-m-d'),
            'catatan'    => trim((string) $this->request->getPost('catatan')) ?: null,
            'keterangan' => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'berita_acara_ukk', $id, 'Ubah berita acara UKK');

        return redirect()->to(site_url('admin/berita-acara-ukk'))->with('success', 'Berita acara diperbarui.');
    }

    /** @param int|string $id */
    public function delete($id)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/berita-acara-ukk'))->with('error', 'Berita acara tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'berita_acara_ukk', $id, 'Hapus berita acara UKK');

        return redirect()->to(site_url('admin/berita-acara-ukk'))->with('success', 'Berita acara dihapus.');
    }

    /** @param int|string $id */
    public function pdf($id)
    {
        $id = (int) $id;
        $ba = $this->model->withRelations()->where('berita_acara_ukk.id', $id)->first();
        if (! $ba) {
            return redirect()->to(site_url('admin/berita-acara-ukk'))->with('error', 'Berita acara tidak ditemukan.');
        }

        $peserta = (new PesertaUkkModel())->withRelations()
            ->where('peserta_ukk.jadwal_ukk_id', $ba['jadwal_ukk_id'])->orderBy('siswa.nama', 'ASC')->findAll();
        $penguji = (new JadwalUkkPengujiModel())->forJadwal((int) $ba['jadwal_ukk_id']);

        $html = view('pdf/berita_acara_ukk', [
            'ba'      => $ba,
            'peserta' => $peserta,
            'penguji' => $penguji,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Berita-Acara-' . $ba['nomor_ba'] . '.pdf', ['Attachment' => false]);
        exit;
    }
}
