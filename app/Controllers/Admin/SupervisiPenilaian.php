<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiPenilaian as Penilaian;

/**
 * Penilaian Supervisi (menu KURIKULUM → Supervisi Guru): daftar kunjungan per semester → lembar penilaian satu kunjungan (skor
 * 1–4 / tidak dinilai per butir, temuan, rekomendasi, tindak lanjut; Simpan draf / Simpan & selesai). Logika: Libraries\SupervisiPenilaian.
 */
class SupervisiPenilaian extends BaseController
{
    public function index()
    {
        $sup    = new Supervisi();
        $saring = Supervisi::saringan($this->request->getGet() ?? []);
        $rows   = $sup->daftarJadwal($saring);

        return view('admin/supervisi/penilaian', [
            'title'     => 'Penilaian Supervisi',
            'saring'    => $saring,
            'rows'      => $rows,
            'ringkas'   => $sup->ringkasan($saring['ta'], $saring['semester']),
            'rataRata'  => Penilaian::rataRata(array_map(static fn (array $r): ?float => $r['status'] === 'selesai' && $r['nilai_akhir'] !== null ? (float) $r['nilai_akhir'] : null, $rows)),
            'pilihanTa' => $sup->pilihanTahunAjaran(),
            'guru'      => $sup->pilihanGuru(),
        ]);
    }

    /** GET {id} — lembar penilaian satu kunjungan. */
    public function lembar($id)
    {
        $lembar = (new Penilaian())->lembar((int) $id);
        if ($lembar === null) {
            return redirect()->to(site_url('admin/supervisi/penilaian'))->with('error', 'Jadwal supervisi tidak ditemukan.');
        }

        return view('admin/supervisi/lembar', [
            'title'   => 'Lembar Penilaian Supervisi',
            'lembar'  => $lembar,
            'kelola'  => HakAkses::boleh((string) (session('admin')['role'] ?? ''), 'admin/supervisi/penilaian/' . (int) $id, 'POST'),
            'kembali' => (string) $this->request->getGet('kembali'),
        ]);
    }

    /** POST {id} — nilai[{instrumen_id}][skor|catatan][{kunci}], nilai[…][kekuatan|kelemahan|rekomendasi], nilai[…][tindak_lanjut][]; aksi=draf|selesai. */
    public function simpan($id)
    {
        $a       = session('admin') ?? [];
        $selesai = $this->request->getPost('aksi') === 'selesai';
        $nilai   = $this->request->getPost('nilai');
        $r       = (new Penilaian())->simpan((int) $id, is_array($nilai) ? $nilai : [], $selesai,
            ['id' => isset($a['id']) ? (int) $a['id'] : null, 'nama' => (string) ($a['full_name'] ?? '')]);
        $kembali = (string) $this->request->getPost('kembali');
        $qs      = $kembali !== '' ? '?kembali=' . rawurlencode($kembali) : '';

        if (! $r['ok']) {
            // Isian dikembalikan agar lembar tidak kosong lagi; tab instrumen yang bermasalah dibuka.
            return redirect()->to(site_url('admin/supervisi/penilaian/' . (int) $id) . $qs . (isset($r['instrumen_id']) ? '#ins-' . $r['instrumen_id'] : ''))
                ->withInput()->with('error', $r['pesan']);
        }
        // Selesai → kembali ke daftar penilaian (saringan semula); draf → tetap di lembar.
        $ke = $selesai ? site_url('admin/supervisi/penilaian') . ($kembali !== '' ? '?' . ltrim($kembali, '?') : '') : site_url('admin/supervisi/penilaian/' . (int) $id) . $qs;

        return redirect()->to($ke)->with('success', $r['pesan']);
    }
}
