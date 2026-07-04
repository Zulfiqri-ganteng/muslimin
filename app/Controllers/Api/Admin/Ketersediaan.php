<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\HariModel;
use App\Models\JamPelajaranModel;
use App\Models\KetersediaanGuruModel;

/**
 * Ketersediaan Guru (API). Cermin App\Controllers\Admin\Master\Ketersediaan.
 * Konvensi: hanya slot "tidak tersedia" yang disimpan (lihat KetersediaanGuruModel).
 *
 *   GET  /api/v1/admin/master/ketersediaan?guru_id=&shift=  → grid + slot tak tersedia
 *   POST /api/v1/admin/master/ketersediaan  { guru_id, shift, unavailable:["hariId-jamId"] }
 */
class Ketersediaan extends BaseApiController
{
    protected KetersediaanGuruModel $model;

    public function __construct()
    {
        $this->model = new KetersediaanGuruModel();
    }

    public function index()
    {
        $guruId = (int) $this->request->getGet('guru_id');
        if ($guruId === 0) {
            return $this->invalid(['guru_id' => 'Guru wajib dipilih.']);
        }
        $shift = in_array($this->request->getGet('shift'), ['pagi', 'siang'], true)
            ? $this->request->getGet('shift') : 'pagi';

        $hari = master_cache('hari', 'aktif_urut', 3600, static fn () => (new HariModel())->aktifUrut());
        $jam  = master_cache('jam', 'kbm_' . $shift, 3600, static fn () => (new JamPelajaranModel())
            ->where('shift', $shift)->where('is_istirahat', 0)
            ->orderBy('jam_ke', 'ASC')->findAll());

        $unavailable = master_cache('ketersediaan', 'guru_' . $guruId, 3600, fn () => $this->model->unavailableSet($guruId));

        return $this->ok([
            'guru_id'     => $guruId,
            'shift'       => $shift,
            'hari'        => array_map(static fn ($h) => [
                'id' => (int) $h['id'], 'nama' => $h['nama'], 'urutan' => (int) ($h['urutan'] ?? 0),
            ], $hari),
            'jam'         => array_map(static fn ($j) => [
                'id'            => (int) $j['id'],
                'jam_ke'        => (int) $j['jam_ke'],
                'waktu_mulai'   => substr((string) $j['waktu_mulai'], 0, 5),
                'waktu_selesai' => substr((string) $j['waktu_selesai'], 0, 5),
            ], $jam),
            // daftar "hariId-jamId" yang ditandai TIDAK tersedia
            'unavailable' => array_keys($unavailable),
        ]);
    }

    public function save()
    {
        $in     = $this->body();
        $guruId = (int) ($in['guru_id'] ?? 0);
        if ($guruId === 0) {
            return $this->invalid(['guru_id' => 'Guru belum dipilih.']);
        }
        $shift = in_array($in['shift'] ?? null, ['pagi', 'siang'], true) ? $in['shift'] : 'pagi';
        $unavailable = array_values(array_filter(array_map('strval', (array) ($in['unavailable'] ?? []))));

        // jam_id valid milik shift ini (batasi cakupan hapus & cegah manipulasi)
        $shiftJamIds = array_map('intval', array_column(
            (new JamPelajaranModel())->select('id')->where('shift', $shift)->findAll(),
            'id'
        ));

        $this->model->syncShift($guruId, $shiftJamIds, $unavailable);
        master_data_changed('ketersediaan');
        (new AuditModel())->record('update', 'ketersediaan_guru', $guruId, "Atur ketersediaan ({$shift}): " . count($unavailable) . ' slot tidak tersedia (via mobile)');

        return $this->ok(['guru_id' => $guruId, 'shift' => $shift, 'unavailable' => $unavailable], 'Ketersediaan guru berhasil disimpan.');
    }
}
