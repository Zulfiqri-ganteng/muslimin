<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JamPelajaranModel;
use App\Models\KetersediaanGuruModel;

class Ketersediaan extends BaseController
{
    protected KetersediaanGuruModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new KetersediaanGuruModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $guruOpts = (new GuruModel())->options();

        $guruId = (int) $this->request->getGet('guru_id');
        if (($guruId === 0 || ! isset($guruOpts[$guruId])) && $guruOpts !== []) {
            $guruId = (int) array_key_first($guruOpts);
        }
        $shift = in_array($this->request->getGet('shift'), ['pagi', 'siang'], true)
            ? $this->request->getGet('shift') : 'pagi';

        // daftar hari aktif & jam KBM per shift jarang berubah → cache per modul
        $hari = master_cache('hari', 'aktif_urut', 3600, static fn () => (new HariModel())->aktifUrut());
        $jam  = master_cache('jam', 'kbm_' . $shift, 3600, static fn () => (new JamPelajaranModel())
            ->where('shift', $shift)->where('is_istirahat', 0)
            ->orderBy('jam_ke', 'ASC')->findAll());

        $unavailable = $guruId
            ? master_cache('ketersediaan', 'guru_' . $guruId, 3600, fn () => $this->model->unavailableSet($guruId))
            : [];

        return view('admin/master/ketersediaan', [
            'title'       => 'Ketersediaan Guru',
            'guruOpts'    => $guruOpts,
            'guruId'      => $guruId,
            'shift'       => $shift,
            'hari'        => $hari,
            'jam'         => $jam,
            'unavailable' => $unavailable,
        ]);
    }

    public function save()
    {
        $guruId = (int) $this->request->getPost('guru_id');
        $shift  = in_array($this->request->getPost('shift'), ['pagi', 'siang'], true)
            ? $this->request->getPost('shift') : 'pagi';
        $unavailable = (array) $this->request->getPost('unavailable');

        if (! $guruId) {
            return redirect()->back()->with('error', 'Guru belum dipilih.');
        }

        // jam_id valid milik shift ini (cegah manipulasi & batasi cakupan hapus)
        $shiftJamIds = array_map('intval', array_column(
            (new JamPelajaranModel())->select('id')->where('shift', $shift)->findAll(),
            'id'
        ));

        $this->model->syncShift($guruId, $shiftJamIds, $unavailable);
        master_data_changed('ketersediaan');
        $this->audit->record('update', 'ketersediaan_guru', $guruId, "Atur ketersediaan ({$shift}): " . count($unavailable) . ' slot tidak tersedia');

        return redirect()->to(site_url('admin/master/ketersediaan?guru_id=' . $guruId . '&shift=' . $shift))
            ->with('success', 'Ketersediaan guru disimpan.');
    }
}
