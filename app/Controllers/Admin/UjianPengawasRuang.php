<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\JadwalPengawas;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;

/**
 * Aksi tab "Jadwal Pengawas" (F3 cetak jadwal, 2026-11-22): simpan daftar guru pengawas & grid pengawas per ruang. Tampilan tab
 * disajikan Admin\Ujian::jenis() (tab 'jadwal-pengawas'); aturan bisnis di App\Libraries\JadwalPengawas. Controller terpisah supaya
 * Admin\Ujian (±1.150 baris) tidak makin gemuk — pola LaporanUjian/UjianBerkas.
 */
class UjianPengawasRuang extends BaseController
{
    public function simpanGuru(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$periode, $kembali] = $ctx;

        $r = JadwalPengawas::simpanGuru($periode, (array) $this->request->getPost('guru_ids'));
        if (! $r['ok']) {
            return redirect()->to($kembali . '#guru-pengawas')->with('error', $r['pesan']);
        }
        (new AuditModel())->record('update', 'ujian_pengawas_guru', (int) $periode['id'], 'Daftar guru pengawas ' . (new UjianPeriodeModel())->label($periode) . ': ' . $r['jumlah'] . ' guru');

        return redirect()->to($kembali . '#guru-pengawas')->with('success', $r['pesan']);
    }

    public function simpanGrid(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$periode, $kembali] = $ctx;
        $shift = (string) $this->request->getPost('shift');

        $r = JadwalPengawas::simpanGrid($periode, $shift, (array) $this->request->getPost('sel'));
        if (! $r['ok']) {
            return redirect()->to($kembali . '#grid-pengawas')->withInput()->with('errors', array_merge([$r['pesan']], $r['galat'] ?? []));
        }
        (new AuditModel())->record('update', 'ujian_pengawas_ruang', (int) $periode['id'], 'Jadwal pengawas ' . $shift . ' ' . (new UjianPeriodeModel())->label($periode) . ': ' . $r['terisi'] . ' sel');

        return redirect()->to($kembali . '#grid-pengawas')->with('success', $r['pesan']);
    }

    /** F4: isi otomatis (cakupan: semua | pagi | siang). Isi lama shift yang dicakup diganti; shift lain dibiarkan. */
    public function generate(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$periode, $kembali] = $ctx;
        $cakupan = (string) $this->request->getPost('cakupan');
        $shifts  = in_array($cakupan, ['pagi', 'siang'], true) ? [$cakupan] : ['pagi', 'siang'];

        $r = JadwalPengawas::generate($periode, $shifts);
        if (! $r['ok']) {
            return redirect()->to($kembali . '#grid-pengawas')->with('error', $r['pesan']);
        }
        (new AuditModel())->record('update', 'ujian_pengawas_ruang', (int) $periode['id'], 'Generate jadwal pengawas (' . implode('+', $shifts) . ') ' . (new UjianPeriodeModel())->label($periode) . ': ' . json_encode($r['terisi']));

        return redirect()->to($kembali . '#grid-pengawas')->with(($r['kosong'] ?? 0) > 0 ? 'error' : 'success', $r['pesan']);
    }

    /**
     * Jenis + periode (tahun dari isian tersembunyi tp) + alamat kembali ke tab (shift ikut).
     *
     * @return array{0: array, 1: string}|\CodeIgniter\HTTP\RedirectResponse
     */
    private function konteks(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug    = UjianPeriodeModel::keSlug($jenis);
        $periode = (new UjianPeriodeModel())->untukTahun($jenis, (string) $this->request->getPost('tp'));
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $shift   = (string) $this->request->getPost('shift');
        $kembali = site_url('admin/ujian/' . $slug . '/jadwal-pengawas') . '?tp=' . rawurlencode((string) $periode['tahun_ajaran'])
            . (in_array($shift, ['pagi', 'siang'], true) ? '&shift=' . $shift : '');

        return [$periode, $kembali];
    }
}
