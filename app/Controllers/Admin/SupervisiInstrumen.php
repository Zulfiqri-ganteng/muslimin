<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\Supervisi;

/**
 * Instrumen Supervisi (menu KURIKULUM → Supervisi Guru): daftar lembar penilaian + butirnya (tambah, ubah, hapus, urutkan).
 * Instrumen yang sudah dipakai jadwal/hasil hanya bisa dinonaktifkan. Logika: Libraries\Supervisi.
 */
class SupervisiInstrumen extends BaseController
{
    public function index()
    {
        return view('admin/supervisi/instrumen', [
            'title'  => 'Instrumen Supervisi',
            'rows'   => (new Supervisi())->daftarInstrumen(),
            'kelola' => $this->bolehKelola(),
        ]);
    }

    public function store()
    {
        return $this->kembali((new Supervisi())->simpanInstrumen($this->isian()));
    }

    public function update($id)
    {
        return $this->kembali((new Supervisi())->simpanInstrumen($this->isian(), (int) $id));
    }

    public function delete($id)
    {
        return $this->kembali((new Supervisi())->hapusInstrumen((int) $id));
    }

    /** POST bawaan — pasang instrumen bawaan yang belum ada. */
    public function bawaan()
    {
        return $this->kembali((new Supervisi())->pasangBawaan());
    }

    /** GET {id}/butir — butir satu instrumen. */
    public function butir($id)
    {
        $sup = new Supervisi();
        $ins = $sup->cariInstrumen((int) $id);
        if ($ins === null) {
            return redirect()->to(site_url('admin/supervisi/instrumen'))->with('error', 'Instrumen tidak ditemukan.');
        }

        return view('admin/supervisi/butir', [
            'title'     => 'Butir: ' . $ins['nama'],
            'instrumen' => $ins,
            'rows'      => $sup->daftarButir((int) $id),
            'aspek'     => $sup->daftarAspek((int) $id),
            'kelola'    => $this->bolehKelola(),
        ]);
    }

    /** POST {id}/butir — tambah butir. */
    public function butirStore($id)
    {
        return $this->keButir((new Supervisi())->simpanButir((int) $id, $this->isianButir()), (int) $id);
    }

    /** POST {instrumen}/butir/{id} — ubah butir (alamat dari masterList: data-base + '/' + id). */
    public function butirUpdate($id)
    {
        return $this->keButir((new Supervisi())->simpanButir(0, $this->isianButir(), (int) $id));
    }

    /** POST butir/delete/{id} */
    public function butirDelete($id)
    {
        return $this->keButir((new Supervisi())->hapusButir((int) $id));
    }

    /** POST butir/{id}/geser — arah=naik|turun. */
    public function butirGeser($id)
    {
        return $this->keButir((new Supervisi())->geserButir((int) $id, $this->request->getPost('arah') === 'naik' ? -1 : 1), 0, false);
    }

    private function isian(): array
    {
        return $this->request->getPost(['kode', 'nama', 'jenis', 'keterangan', 'urut', 'aktif']);
    }

    private function isianButir(): array
    {
        return $this->request->getPost(['aspek', 'uraian', 'aktif']);
    }

    private function bolehKelola(): bool
    {
        return HakAkses::boleh((string) (session('admin')['role'] ?? ''), 'admin/supervisi/instrumen', 'POST');
    }

    private function kembali(array $r)
    {
        return redirect()->to(site_url('admin/supervisi/instrumen'))->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** Kembali ke halaman butir instrumennya ($pesanSukses = false: geser urutan tanpa toast). */
    private function keButir(array $r, int $instrumenId = 0, bool $pesanSukses = true)
    {
        $id = (int) ($r['instrumen_id'] ?? $instrumenId);
        $ke = redirect()->to(site_url($id > 0 ? 'admin/supervisi/instrumen/' . $id . '/butir' : 'admin/supervisi/instrumen'));

        return $r['ok'] && ! $pesanSukses ? $ke : $ke->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }
}
