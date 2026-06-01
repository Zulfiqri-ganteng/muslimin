<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SubmissionModel;
use App\Models\SettingModel;

class Submissions extends BaseController
{
    protected SubmissionModel $model;

    public function __construct()
    {
        $this->model = new SubmissionModel();
    }

    /** Daftar kesediaan dengan pencarian, filter, & pagination. */
    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $builder = $this->model;
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama_lengkap', $q)
                ->orLike('nip_nuptk', $q)
                ->orLike('guru_mapel', $q)
                ->groupEnd();
        }
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $builder = $builder->where('status_kepegawaian', $status);
        }

        $rows  = $builder->orderBy('created_at', 'DESC')->paginate(15);
        $pager = $this->model->pager;

        return view('admin/submissions/index', [
            'title'  => 'Data Kesediaan Guru',
            'rows'   => $rows,
            'pager'  => $pager,
            'q'      => $q,
            'status' => $status,
            'total'  => $pager ? $pager->getTotal() : count($rows),
        ]);
    }

    /** Detail satu kesediaan. */
    public function view($id)
    {
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/submissions'))->with('error', 'Data tidak ditemukan.');
        }

        return view('admin/submissions/view', [
            'title'   => 'Detail Kesediaan',
            'row'     => SubmissionModel::decode($row),
            'setting' => (new SettingModel())->get(),
        ]);
    }

    /** Ubah status verifikasi + catatan admin. */
    public function updateStatus($id)
    {
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/submissions'))->with('error', 'Data tidak ditemukan.');
        }

        $status = $this->request->getPost('status') ?: 'baru';
        $update = [
            'status'        => $status,
            'catatan_admin' => trim((string) $this->request->getPost('catatan_admin')),
        ];

        // "Perlu Revisi" (ditolak): buatkan token agar guru bisa membuka tautan revisi.
        // Status lain: hapus token supaya tautan lama tidak bisa dipakai lagi.
        if ($status === 'ditolak') {
            if (empty($row['edit_token'])) {
                $update['edit_token'] = bin2hex(random_bytes(20));
            }
        } else {
            $update['edit_token'] = null;
        }

        $this->model->update($id, $update);

        return redirect()->to(site_url('admin/submissions/view/' . $id))->with('success', 'Status kesediaan berhasil diperbarui.');
    }

    /** Hapus kesediaan. */
    public function delete($id)
    {
        if (! $this->model->find($id)) {
            return redirect()->to(site_url('admin/submissions'))->with('error', 'Data tidak ditemukan.');
        }
        $this->model->delete($id);
        return redirect()->to(site_url('admin/submissions'))->with('success', 'Data kesediaan berhasil dihapus.');
    }
}
