<?php

namespace App\Controllers\Admin;

use App\Libraries\SuratAcara as Acara;
use App\Libraries\SuratJenis;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Izin ASTS dan Surat Izin TKA — formulir membuat dan mengubah (hak Buat jenis itu). Logika isian, validasi, format
 * tanggal, pencegah ganda, dan penanda template ada di Libraries\SuratAcara; ACC / unduh di Admin\Surat.
 *
 * Alamat: admin/surat/asts · admin/surat/tka (GET formulir, POST simpan) dan admin/surat/{asts|tka}/{id}/ubah (GET, POST).
 */
class SuratIzin extends SuratDasar
{
    // ----- rute -----------------------------------------------------
    public function asts()
    {
        return $this->form(SuratJenis::ASTS);
    }

    public function tka()
    {
        return $this->form(SuratJenis::TKA);
    }

    public function simpanAsts(): RedirectResponse
    {
        return $this->simpan(SuratJenis::ASTS);
    }

    public function simpanTka(): RedirectResponse
    {
        return $this->simpan(SuratJenis::TKA);
    }

    public function ubahAsts($id)
    {
        return $this->ubah(SuratJenis::ASTS, (int) $id);
    }

    public function ubahTka($id)
    {
        return $this->ubah(SuratJenis::TKA, (int) $id);
    }

    public function simpanUbahAsts($id): RedirectResponse
    {
        return $this->simpanUbah(SuratJenis::ASTS, (int) $id);
    }

    public function simpanUbahTka($id): RedirectResponse
    {
        return $this->simpanUbah(SuratJenis::TKA, (int) $id);
    }

    // ----- formulir baru ----------------------------------------------
    private function form(string $jenis)
    {
        if (($tolak = $this->tolakJenis('buat', $jenis)) !== null) {
            return $tolak;
        }
        $acara   = new Acara();
        $periode = $jenis === SuratJenis::ASTS ? $acara->periodeUjian() : [];

        // Isian awal (sama dengan API): ASTS dari periode Ujian terdekat yang belum lewat; TKA mulai kosong.
        $awal = Acara::nilaiAwal($jenis, $periode, (string) ((new \App\Models\SettingModel())->get()['academic_year'] ?? ''));

        return view('admin/surat/izin_form', $this->dasarForm($jenis) + [
            'ubah'       => false,
            'nilai'      => $this->nilaiForm($awal),
            'periode'    => $periode,
            // TKA: hanya siswa kelas XII + penempatan gelombang tiap siswa (siswa yang sudah punya surat TKA tidak bisa dicentang).
            'perusahaan' => $jenis === SuratJenis::TKA ? $acara->perusahaanTka() : $acara->perusahaan(null, null, $jenis),
        ]);
    }

    private function simpan(string $jenis): RedirectResponse
    {
        if (($tolak = $this->tolakJenis('buat', $jenis)) !== null) {
            return $tolak;
        }
        $h = Acara::periksa($jenis, (array) $this->request->getPost());
        if (! $h['ok']) {
            return redirect()->to(site_url(SuratJenis::alamat($jenis)))->withInput()->with('errors', $h['galat']);
        }
        $hasil = (new Acara())->buat($jenis, $h, $this->konteks(['saluran' => 'web']), $this->p);
        $catatan = array_merge($hasil['sama'], $hasil['gagal']);
        if (! $hasil['ok']) {
            return redirect()->to(site_url(SuratJenis::alamat($jenis)))->withInput()->with('error', 'Tidak ada surat yang dibuat. ' . ($catatan !== [] ? implode(' ', $catatan) : ''));
        }

        $acc     = $this->langsungAcc($hasil['dibuat'], $jenis);
        $catatan = array_merge($catatan, $acc['catatan']);
        $akhir   = $this->akhirBuat($jenis, count($hasil['dibuat']), $acc);
        if (count($hasil['dibuat']) === 1 && $catatan === []) {
            return $this->ke('admin/surat/' . $hasil['dibuat'][0], 'success', 'Surat dibuat.' . $akhir);
        }
        $redir = redirect()->to(site_url('admin/surat') . '?' . http_build_query(['jenis' => $jenis]))->with('success', $hasil['pesan'] . $akhir);

        return $catatan !== [] ? $redir->with('errors', $catatan) : $redir;
    }

    // ----- ubah (muatBisaDiubah: SuratDasar) -----------------------
    private function ubah(string $jenis, int $id)
    {
        [$m, $alih] = $this->muatBisaDiubah($jenis, $id);
        if ($alih !== null) {
            return $alih;
        }
        $isi   = $m['surat']['isi_arr'];
        $awal  = ['tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'mode' => (string) ($isi['mode'] ?? 'umum'), 'tempat' => (string) ($isi['tempat'] ?? Acara::TEMPAT_BAWAAN),
            'tgl_mulai' => (string) ($isi['tgl_mulai'] ?? ''), 'tgl_selesai' => (string) ($isi['tgl_selesai'] ?? ''), 'semester' => (string) ($isi['semester'] ?? 'Ganjil'),
            'kegiatan' => $jenis === SuratJenis::ASTS ? Acara::kodeUjian($isi['kegiatan'] ?? '') : 'TKA',
            'tahun_pelajaran' => (string) ($isi['tahun_pelajaran'] ?? ''), 'sesi' => (string) ($isi['sesi'] ?? '')];

        return view('admin/surat/izin_form', $this->dasarForm($jenis) + [
            'ubah'       => true,
            'surat'      => $m['surat'],
            'kode'       => SuratSekolahModel::kode($id),
            'nilai'      => $this->nilaiForm($awal),
            'periode'    => $jenis === SuratJenis::ASTS ? (new Acara())->periodeUjian() : [],
            'perusahaan' => [],
            'jmlSiswa'   => count($m['siswa']),
        ]);
    }

    private function simpanUbah(string $jenis, int $id): RedirectResponse
    {
        [$m, $alih] = $this->muatBisaDiubah($jenis, $id);
        if ($alih !== null) {
            return $alih;
        }
        $balik = site_url(SuratJenis::alamat($jenis) . '/' . $id . '/ubah');
        $hasil = (new Acara())->ubahSurat($m, (array) $this->request->getPost(), $this->konteks(['saluran' => 'web']));
        if ($hasil['kode'] === 'galat') {
            return redirect()->to($balik)->withInput()->with('errors', $hasil['galat']);
        }
        if ($hasil['kode'] === 'sama') {
            return redirect()->to($balik)->withInput()->with('error', $hasil['pesan']);
        }
        if (! $hasil['ok']) {
            return $this->ke('admin/surat/' . $id, 'error', $hasil['pesan']);
        }

        return $this->ke('admin/surat/' . $id, 'success', 'Data surat diperbarui.' . ($m['surat']['status'] === 'dikembalikan' ? ' Surat masih berstatus Dikembalikan: tekan "Ajukan ulang untuk ACC" bila sudah siap.' : ''));
    }

    // ----- pembantu ---------------------------------------------------
    /** @param array<string, string> $awal */
    private function nilaiForm(array $awal): array
    {
        $nilai = [];
        foreach ($awal as $k => $v) {
            $nilai[$k] = (string) old($k, $v);
        }
        $nilai['perusahaan'] = array_map('strval', (array) (old('perusahaan') ?? []));

        return $nilai;
    }

    /** @return array<string, mixed> */
    private function dasarForm(string $jenis): array
    {
        return $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + $this->dasarJenis($jenis) + [
            'jenis'    => $jenis,
            'label'    => SuratJenis::label($jenis),
            'sesiSaran' => Acara::SESI_SARAN,
        ];
    }
}
