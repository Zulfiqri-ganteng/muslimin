<?php

namespace App\Controllers\Admin;

use App\Libraries\SuratJenis;
use App\Libraries\SuratPkl as Lib;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Balasan PKL dan Surat Penarikan Izin PKL — formulir membuat dan mengubah (hak 'surat_sekolah'). Logika isian,
 * periode, alasan, pencegah ganda, dan jejak di ajuan PKL ada di Libraries\SuratPkl; ACC / unduh di Admin\Surat.
 *
 * Alamat: admin/surat/{balasan|penarikan} (GET formulir — ?ajuan=ID langsung memilih ajuan itu; POST simpan) dan
 * admin/surat/{balasan|penarikan}/{id}/ubah (GET, POST).
 */
class SuratPkl extends SuratDasar
{
    // ----- rute -----------------------------------------------------
    public function balasan()
    {
        return $this->form(SuratJenis::BALASAN);
    }

    public function penarikan()
    {
        return $this->form(SuratJenis::PENARIKAN);
    }

    public function simpanBalasan(): RedirectResponse
    {
        return $this->simpan(SuratJenis::BALASAN);
    }

    public function simpanPenarikan(): RedirectResponse
    {
        return $this->simpan(SuratJenis::PENARIKAN);
    }

    /** @param int|string $id */
    public function ubahBalasan($id)
    {
        return $this->ubah(SuratJenis::BALASAN, (int) $id);
    }

    /** @param int|string $id */
    public function ubahPenarikan($id)
    {
        return $this->ubah(SuratJenis::PENARIKAN, (int) $id);
    }

    /** @param int|string $id */
    public function simpanUbahBalasan($id): RedirectResponse
    {
        return $this->simpanUbah(SuratJenis::BALASAN, (int) $id);
    }

    /** @param int|string $id */
    public function simpanUbahPenarikan($id): RedirectResponse
    {
        return $this->simpanUbah(SuratJenis::PENARIKAN, (int) $id);
    }

    // ----- formulir baru ----------------------------------------------
    private function form(string $jenis)
    {
        if (($tolak = $this->tolakHalaman('surat_sekolah')) !== null) {
            return $tolak;
        }
        $ajuan = (new Lib())->ajuan();
        $pilih = (int) $this->request->getGet('ajuan');
        $awal  = ['tanggal_surat' => date('Y-m-d'), 'sapaan' => Lib::SAPAAN_BAWAAN, 'periode_mulai' => '', 'periode_selesai' => '', 'tgl_efektif' => date('Y-m-d'), 'alasan_pilih' => '0', 'alasan_lain' => ''];
        foreach ($ajuan as $a) { // periode yang sudah tercatat di ajuan pilihan mengisi formulir Balasan
            if ($a['id'] === $pilih && $a['mulai'] !== null && $a['selesai'] !== null) {
                $awal['periode_mulai']   = $a['mulai'];
                $awal['periode_selesai'] = $a['selesai'];
            }
        }

        return view('admin/surat/pkl_form', $this->dasarForm($jenis) + [
            'ubah'  => false,
            'nilai' => $this->nilaiAwal($awal) + ['ajuan' => $this->idLama('ajuan', $pilih > 0 ? [$pilih] : []), 'siswa' => $this->idLama('siswa')],
            'ajuan' => $ajuan,
        ]);
    }

    private function simpan(string $jenis): RedirectResponse
    {
        if (($tolak = $this->tolakHalaman('surat_sekolah')) !== null) {
            return $tolak;
        }
        $post  = (array) $this->request->getPost();
        $balik = site_url(SuratJenis::alamat($jenis));
        $h     = Lib::periksa($jenis, $post);
        if (! $h['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', $h['galat']);
        }
        $hasil   = (new Lib())->buat($jenis, $h, (array) ($post['ajuan'] ?? []), (array) ($post['siswa'] ?? []), $this->konteks(['saluran' => 'web']), $this->p);
        $catatan = array_merge($hasil['sama'], $hasil['gagal']);
        if (! $hasil['ok']) {
            return redirect()->to($balik)->withInput()->with('error', 'Tidak ada surat yang dibuat. ' . implode(' ', $catatan));
        }

        $akhir = SuratJenis::perluAcc($jenis, $this->p) ? ' Surat menunggu ACC Waka Hubin.' : ' Surat langsung siap diunduh.';
        if (count($hasil['dibuat']) === 1 && $catatan === []) {
            return $this->ke('admin/surat/' . $hasil['dibuat'][0], 'success', 'Surat dibuat.' . $akhir);
        }
        $redir = redirect()->to(site_url('admin/surat') . '?' . http_build_query(['jenis' => $jenis]))->with('success', $hasil['pesan'] . $akhir);

        return $catatan !== [] ? $redir->with('errors', $catatan) : $redir;
    }

    // ----- ubah -------------------------------------------------------
    private function ubah(string $jenis, int $id)
    {
        [$m, $alih] = $this->muatBisaDiubah($jenis, $id);
        if ($alih !== null) {
            return $alih;
        }
        $isi     = $m['surat']['isi_arr'];
        $alasan  = (string) ($isi['alasan'] ?? '');
        $iAlasan = array_search($alasan, Lib::ALASAN_SARAN, true);
        $aid     = (int) ($m['surat']['pengajuan_id'] ?? 0);
        $awal    = [
            'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'sapaan' => (string) ($isi['sapaan'] ?? Lib::SAPAAN_BAWAAN),
            'periode_mulai' => (string) ($isi['periode_mulai'] ?? ''), 'periode_selesai' => (string) ($isi['periode_selesai'] ?? ''),
            'alamat' => (string) ($isi['alamat'] ?? ''), 'tgl_efektif' => (string) ($isi['tgl_efektif'] ?? ''),
            'alasan_pilih' => $iAlasan !== false ? (string) $iAlasan : 'lain', 'alasan_lain' => $iAlasan !== false ? '' : $alasan,
        ];

        return view('admin/surat/pkl_form', $this->dasarForm($jenis) + [
            'ubah'       => true,
            'surat'      => $m['surat'],
            'kode'       => SuratSekolahModel::kode($id),
            'nilai'      => $this->nilaiAwal($awal) + ['ajuan' => $aid > 0 ? [$aid] : [], 'siswa' => $this->idLama('siswa', array_map('intval', array_column($m['siswa'], 'siswa_id')))],
            'ajuan'      => $aid > 0 ? (new Lib())->ajuan([$aid]) : [],
            'siswaSurat' => $m['siswa'],
        ]);
    }

    private function simpanUbah(string $jenis, int $id): RedirectResponse
    {
        [$m, $alih] = $this->muatBisaDiubah($jenis, $id);
        if ($alih !== null) {
            return $alih;
        }
        $post  = (array) $this->request->getPost();
        $balik = site_url(SuratJenis::alamat($jenis) . '/' . $id . '/ubah');
        $h     = Lib::periksa($jenis, $post);
        if (! $h['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', $h['galat']);
        }
        $r = (new Lib())->ubahSurat($m, $h, (array) ($post['siswa'] ?? []), $this->konteks(['saluran' => 'web']));
        if (! $r['ok']) {
            return redirect()->to($balik)->withInput()->with('error', (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.'));
        }

        return $this->ke('admin/surat/' . $id, 'success', 'Data surat diperbarui.' . ($m['surat']['status'] === 'dikembalikan' ? ' Surat masih berstatus Dikembalikan: tekan "Ajukan ulang untuk ACC" bila sudah siap.' : ''));
    }

    // ----- pembantu ---------------------------------------------------
    /** @return array<string, mixed> */
    private function dasarForm(string $jenis): array
    {
        return $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + [
            'jenis'       => $jenis,
            'label'       => SuratJenis::label($jenis),
            'perluAcc'    => SuratJenis::perluAcc($jenis, $this->p),
            'sapaanSaran' => Lib::SAPAAN_SARAN,
            'alasanSaran' => Lib::ALASAN_SARAN,
            'batas'       => [
                'awal' => (string) ($this->p['mulai_paling_awal'] ?? ''), 'akhir' => (string) ($this->p['selesai_paling_akhir'] ?? ''),
                'min' => (int) ($this->p['durasi_min_hari'] ?? 0), 'maks' => (int) ($this->p['durasi_maks_hari'] ?? 0),
            ],
        ];
    }
}
