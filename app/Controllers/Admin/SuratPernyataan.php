<?php

namespace App\Controllers\Admin;

use App\Libraries\SuratJenis;
use App\Libraries\SuratPernyataan as Pernyataan;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pernyataan Orang Tua PKL — formulir membuat (hak Buat jenis ini). Tanpa nomor dan tanpa ACC: surat yang dibuat langsung
 * siap diunduh dari halaman detailnya (Admin\Surat::unduh). Logika cakupan, data orang tua, dan halaman di
 * Libraries\SuratPernyataan.
 *
 * Alamat: admin/surat/pernyataan-ortu (GET formulir — ?ajuan=ID langsung memilih ajuan itu; POST simpan).
 */
class SuratPernyataan extends SuratDasar
{
    public function index()
    {
        if (($tolak = $this->tolakJenis('buat', SuratJenis::PERNYATAAN)) !== null) {
            return $tolak;
        }
        $lib   = new Pernyataan();
        $kelas = $lib->kelas($this->p);
        $ajuan = (int) $this->request->getGet('ajuan');

        return view('admin/surat/pernyataan_form', $this->dasarSurat(SuratJenis::label(SuratJenis::PERNYATAAN), 'daftar') + [
            'label' => SuratJenis::label(SuratJenis::PERNYATAAN),
            'nilai' => $this->nilaiAwal(['tanggal_surat' => date('Y-m-d'), 'cakupan' => $ajuan > 0 ? 'ajuan' : 'kelas', 'isi_ortu' => '1']) + [
                'kelas' => $this->idLama('kelas'),
                'ajuan' => $this->idLama('ajuan', $ajuan > 0 ? [$ajuan] : []),
                'siswa' => $this->idLama('siswa'),
            ],
            'kelas' => $kelas,
            'ajuan' => $lib->ajuan(),
            'siswa' => $lib->siswaKelas(array_column($kelas, 'id')),
        ]);
    }

    public function simpan(): RedirectResponse
    {
        if (($tolak = $this->tolakJenis('buat', SuratJenis::PERNYATAAN)) !== null) {
            return $tolak;
        }
        $balik = site_url(SuratJenis::alamat(SuratJenis::PERNYATAAN));
        $h     = Pernyataan::periksa((array) $this->request->getPost());
        if (! $h['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', $h['galat']);
        }
        $hasil   = (new Pernyataan())->buat($h, $this->konteks(['saluran' => 'web']), $this->p);
        $catatan = array_merge($hasil['sama'], $hasil['gagal']);
        if (! $hasil['ok']) {
            return redirect()->to($balik)->withInput()->with('error', 'Tidak ada surat yang dibuat. ' . implode(' ', $catatan));
        }
        if (count($hasil['dibuat']) === 1 && $catatan === []) {
            return $this->ke('admin/surat/' . $hasil['dibuat'][0], 'success', 'Surat dibuat dan langsung siap diunduh (tanpa nomor, tanpa ACC).');
        }
        $redir = redirect()->to(site_url('admin/surat') . '?' . http_build_query(['jenis' => SuratJenis::PERNYATAAN]))->with('success', $hasil['pesan'] . ' Surat langsung siap diunduh.');

        return $catatan !== [] ? $redir->with('errors', $catatan) : $redir;
    }
}
