<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;

class AuditLog extends BaseController
{
    protected AuditModel $model;

    public function __construct()
    {
        $this->model = new AuditModel();
    }

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $aksi  = trim((string) $this->request->getGet('aksi'));
        $tabel = trim((string) $this->request->getGet('tabel'));

        $rows  = $this->model->filtered($q, $aksi, $tabel)->paginate(25);
        $pager = $this->model->pager;

        return view('admin/audit/index', [
            'title'     => 'Audit Log',
            'rows'      => $rows,
            'pager'     => $pager,
            'q'         => $q,
            'aksi'      => $aksi,
            'tabel'     => $tabel,
            'total'     => $pager ? $pager->getTotal() : count($rows),
            'tabelList' => $this->model->tabelList(),
        ]);
    }

    /** Hapus log lama (>90 hari). */
    public function purge()
    {
        $n = $this->model->purgeOlderThan(90);
        return redirect()->to(site_url('admin/audit'))->with('success', "{$n} log lama (>90 hari) dihapus.");
    }
}
