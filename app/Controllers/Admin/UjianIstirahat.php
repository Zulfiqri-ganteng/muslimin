<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\IstirahatUjian;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;

/**
 * Simpan pengaturan istirahat ujian (tab Jadwal Ujian → bagian Istirahat; keluhan klien 2026-11-24, tahap 1). Satu alamat untuk
 * Ubah (ist_mode = atur), Hapus (tanpa), dan Kembalikan otomatis (otomatis). Aturan & pengecekan di App\Libraries\IstirahatUjian.
 * Isian: tp, ist_hari = "pagi|2026-11-30" atau "pagi|semua", ist_mode, ist_mulai[], ist_selesai[] (awalan ist_ supaya old() tidak
 * tercampur dengan formulir lain di tab itu).
 */
class UjianIstirahat extends BaseController
{
    public function simpan(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug    = UjianPeriodeModel::keSlug($jenis);
        $model   = new UjianPeriodeModel();
        $periode = $model->untukTahun($jenis, (string) $this->request->getPost('tp'));
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $kembali = site_url('admin/ujian/' . $slug . '/jadwal') . '?tp=' . rawurlencode((string) $periode['tahun_ajaran']) . '#istirahat';

        [$shift, $tanggal] = array_pad(explode('|', (string) $this->request->getPost('ist_hari'), 2), 2, '');
        $mode    = (string) $this->request->getPost('ist_mode');
        $mulai   = array_values((array) $this->request->getPost('ist_mulai'));
        $selesai = array_values((array) $this->request->getPost('ist_selesai'));
        $jam     = [];
        for ($i = 0; $i < IstirahatUjian::MAKS; $i++) {
            $jam[] = [is_string($mulai[$i] ?? null) ? $mulai[$i] : '', is_string($selesai[$i] ?? null) ? $selesai[$i] : ''];
        }

        $r = IstirahatUjian::simpan($periode, $shift, $tanggal, $mode, $jam);
        if (! $r['ok']) {
            return redirect()->to($kembali)->withInput()->with('errors', $r['galat'])->with('bukaIstirahat', 1);
        }

        master_data_changed('ujian_jadwal');
        (new AuditModel())->record('update', 'ujian_istirahat', (int) $periode['id'], 'Istirahat ' . $model->label($periode) . ': ' . $r['catatan']);

        $balik = redirect()->to($kembali)->with('success', $r['pesan'])->with('bukaIstirahat', 1);

        return $r['galat'] === [] ? $balik : $balik->with('errors', $r['galat']); // "semua hari": hari yang dilewati beserta sebabnya
    }
}
