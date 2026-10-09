<?php

namespace App\Controllers\Admin;

use App\Libraries\SuratAcara as Acara;
use App\Libraries\SuratJenis;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Izin ASTS dan Surat Izin TKA — formulir membuat dan mengubah (hak 'surat_sekolah'). Logika isian, validasi, format
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
        if (($tolak = $this->tolakHalaman('surat_sekolah')) !== null) {
            return $tolak;
        }
        $acara   = new Acara();
        $periode = $jenis === SuratJenis::ASTS ? $acara->periodeUjian() : [];

        // Isian awal: ASTS mengambil periode Ujian terdekat yang belum lewat (atau yang terbaru); TKA mulai kosong.
        $awal = ['tanggal_surat' => date('Y-m-d'), 'mode' => 'umum', 'tempat' => Acara::TEMPAT_BAWAAN, 'tgl_mulai' => '', 'tgl_selesai' => '',
            'semester' => 'Ganjil', 'tahun_pelajaran' => (string) ((new \App\Models\SettingModel())->get()['academic_year'] ?? ''), 'sesi' => $jenis === SuratJenis::TKA ? 'Gelombang 1' : ''];
        if ($periode !== []) {
            $pilih = null;
            foreach (array_reverse($periode) as $x) { // dari yang tertua: ambil yang pertama belum lewat
                if ($x['selesai'] !== null && $x['selesai'] >= date('Y-m-d')) {
                    $pilih = $x;
                    break;
                }
            }
            // Tak ada periode yang akan datang: semester & tahun mengikuti yang terbaru, tanggal dibiarkan kosong.
            $awal = $pilih !== null
                ? ['semester' => $pilih['semester'], 'tahun_pelajaran' => $pilih['tahun'], 'tgl_mulai' => (string) $pilih['mulai'], 'tgl_selesai' => (string) $pilih['selesai']] + $awal
                : ['semester' => $periode[0]['semester'], 'tahun_pelajaran' => $periode[0]['tahun']] + $awal;
        }

        return view('admin/surat/izin_form', $this->dasarForm($jenis) + [
            'ubah'       => false,
            'nilai'      => $this->nilaiForm($awal),
            'periode'    => $periode,
            'perusahaan' => $acara->perusahaan(),
        ]);
    }

    private function simpan(string $jenis): RedirectResponse
    {
        if (($tolak = $this->tolakHalaman('surat_sekolah')) !== null) {
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

        $perluAcc = SuratJenis::perluAcc($jenis, $this->p);
        $akhir    = $perluAcc ? ' Surat menunggu ACC Waka Hubin.' : ' Surat langsung siap diunduh.';
        if (count($hasil['dibuat']) === 1 && $catatan === []) {
            return $this->ke('admin/surat/' . $hasil['dibuat'][0], 'success', 'Surat dibuat.' . $akhir);
        }
        $redir = redirect()->to(site_url('admin/surat') . '?' . http_build_query(['jenis' => $jenis]))->with('success', $hasil['pesan'] . $akhir);

        return $catatan !== [] ? $redir->with('errors', $catatan) : $redir;
    }

    // ----- ubah -------------------------------------------------------
    /** @return array{0: array<string, mixed>|null, 1: RedirectResponse|null} surat termuat, atau pengalihan galat */
    private function muatBisaDiubah(string $jenis, int $id): array
    {
        if (($tolak = $this->tolakHalaman('surat_sekolah', 'admin/surat/' . $id)) !== null) {
            return [null, $tolak];
        }
        $m = $this->surat->muat($id);
        if ($m === null || $m['surat']['jenis'] !== $jenis) {
            return [null, $this->ke('admin/surat', 'error', 'Surat tidak ditemukan.')];
        }
        if ((string) ($m['surat']['nomor'] ?? '') !== '') {
            return [null, $this->ke('admin/surat/' . $id, 'error', 'Surat ini sudah bernomor, jadi datanya tidak bisa diubah. Bila salah, batalkan surat lalu buat yang baru.')];
        }
        if (! in_array($m['surat']['status'], ['menunggu', 'dikembalikan'], true)) {
            return [null, $this->ke('admin/surat/' . $id, 'error', 'Surat berstatus "' . (SuratSekolahModel::TAMPIL_STATUS[$m['surat']['status']][0] ?? $m['surat']['status']) . '" tidak bisa diubah. Surat yang sudah disetujui harus dibatalkan persetujuannya dulu.')];
        }

        return [$m, null];
    }

    private function ubah(string $jenis, int $id)
    {
        [$m, $alih] = $this->muatBisaDiubah($jenis, $id);
        if ($alih !== null) {
            return $alih;
        }
        $isi   = $m['surat']['isi_arr'];
        $awal  = ['tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'mode' => (string) ($isi['mode'] ?? 'umum'), 'tempat' => (string) ($isi['tempat'] ?? Acara::TEMPAT_BAWAAN),
            'tgl_mulai' => (string) ($isi['tgl_mulai'] ?? ''), 'tgl_selesai' => (string) ($isi['tgl_selesai'] ?? ''), 'semester' => (string) ($isi['semester'] ?? 'Ganjil'),
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
        $lama  = $m['surat']['isi_arr'];
        $h     = Acara::periksa($jenis, (array) $this->request->getPost(), (string) ($lama['mode'] ?? 'umum'));
        if (! $h['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', $h['galat']);
        }

        $pkey  = (string) ($lama['pkey'] ?? '');
        $isi   = $h['isi'] + ($pkey !== '' ? ['pkey' => $pkey] : []);
        $isi  += ['kunci' => Acara::kunci($jenis, $h['isi'], $pkey)];
        $acara = new Acara();
        if (($sama = $acara->adaSama($isi['kunci'], $id)) !== null) {
            return redirect()->to($balik)->withInput()->with('error', 'Surat yang sama sudah ada (' . SuratSekolahModel::kode($sama['id']) . ($sama['nomor'] ? ', nomor ' . $sama['nomor'] : '') . '). Tidak dibuat dobel.');
        }

        $hasil = $this->surat->ubah($id, [
            'judul' => Acara::judul($jenis, $isi, (string) $m['surat']['perusahaan_nama'], count($m['siswa'])), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
        ], $this->konteks(['saluran' => 'web']));
        if (! $hasil['ok']) {
            return $this->ke('admin/surat/' . $id, 'error', (string) ($hasil['pesan'] ?? 'Perubahan gagal disimpan.'));
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
        return $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + [
            'jenis'    => $jenis,
            'label'    => SuratJenis::label($jenis),
            'perluAcc' => SuratJenis::perluAcc($jenis, $this->p),
            'sesiSaran' => Acara::SESI_SARAN,
        ];
    }
}
