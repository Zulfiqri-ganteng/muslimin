<?php

namespace App\Controllers\Admin;

use App\Libraries\IsianBantu;
use App\Libraries\PklLapor;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPkl as Lib;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Balasan PKL dan Surat Penarikan Izin PKL — formulir membuat dan mengubah (hak Buat jenis itu). Logika isian,
 * periode, alasan, pencegah ganda, dan jejak di ajuan PKL ada di Libraries\SuratPkl; ACC / unduh di Admin\Surat.
 *
 * Alamat: admin/surat/{balasan|penarikan} (GET formulir — ?ajuan=ID langsung memilih ajuan itu; Balasan ?lapor=ID = terisi dari
 * laporan siswa "Lapor Diterima PKL", laporan ditandai selesai setelah surat dibuat; POST simpan) dan
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
        if (($tolak = $this->tolakJenis('buat', $jenis)) !== null) {
            return $tolak;
        }
        $ajuan = (new Lib())->ajuan();
        $pilih = (int) $this->request->getGet('ajuan');
        $awal  = ['tanggal_surat' => date('Y-m-d'), 'sapaan' => Lib::SAPAAN_BAWAAN, 'periode_mulai' => '', 'periode_selesai' => '', 'tgl_efektif' => date('Y-m-d'), 'alasan_pilih' => '0', 'alasan_lain' => ''];

        // Dari kotak "Lapor Diterima PKL" ("Periksa & ubah dulu", ?lapor=ID): hanya ajuan laporan itu; periode & alamat dari laporan.
        $lapor = null;
        if ($jenis === SuratJenis::BALASAN && (int) $this->request->getGet('lapor') > 0) {
            [$lapor, $alih] = $this->laporSiap((int) $this->request->getGet('lapor'), $ajuan);
            if ($alih !== null) {
                return $alih;
            }
            $ajuan = array_values(array_filter($ajuan, static fn (array $a): bool => $a['id'] === (int) $lapor['pengajuan_id']));
            $pilih = (int) $lapor['pengajuan_id'];
            $awal  = ['periode_mulai' => (string) $lapor['tanggal_mulai'], 'periode_selesai' => (string) $lapor['tanggal_selesai'],
                'alamat' => (string) ($lapor['alamat'] ?? '') !== '' ? (string) $lapor['alamat'] : (string) $ajuan[0]['alamat']] + $awal;
        } else {
            foreach ($ajuan as $a) { // periode yang sudah tercatat di ajuan pilihan mengisi formulir Balasan
                if ($a['id'] === $pilih && $a['mulai'] !== null && $a['selesai'] !== null) {
                    $awal['periode_mulai']   = $a['mulai'];
                    $awal['periode_selesai'] = $a['selesai'];
                }
            }
        }
        $laporMenunggu = $jenis === SuratJenis::BALASAN && $lapor === null ? (new PklLapor())->hitung()['baru'] : 0;

        return view('admin/surat/pkl_form', $this->dasarForm($jenis) + [
            'ubah'          => false,
            'nilai'         => $this->nilaiAwal($awal) + ['ajuan' => $lapor !== null ? [$pilih] : $this->idLama('ajuan', $pilih > 0 ? [$pilih] : []), 'siswa' => $this->idLama('siswa')],
            'ajuan'         => $ajuan,
            'lapor'         => $lapor,
            'laporMenunggu' => $laporMenunggu,
        ]);
    }

    /**
     * Laporan siswa yang boleh dibuatkan surat lewat formulir: masih menunggu, ajuannya masih disetujui, dan ajuan itu belum
     * punya Surat Balasan. $ajuan = hasil SuratPkl::ajuan() (ajuan disetujui).
     *
     * @param list<array<string, mixed>> $ajuan
     *
     * @return array{0: array<string, mixed>|null, 1: RedirectResponse|null}
     */
    private function laporSiap(int $id, array $ajuan): array
    {
        $kotak = 'admin/surat/balasan/laporan';
        $l     = (new PklLapor())->muat($id);
        if ($l === null) {
            return [null, $this->ke($kotak, 'error', 'Laporan tidak ditemukan (mungkin ajuannya sudah dihapus).')];
        }
        if ($l['status'] !== 'baru') {
            return [null, $this->ke($kotak, 'error', 'Laporan ' . \App\Models\PklPengajuanModel::kode((int) $l['pengajuan_id']) . ' ' . PklLapor::kalimatStatus((string) $l['status']) . ' — surat tidak dibuat dua kali. Muat ulang kotak laporan.')];
        }
        if ((string) ($l['hasil'] ?? 'semua') !== 'semua') {
            return [null, $this->ke($kotak, 'error', PklLapor::pesanKhusus($l))];
        }
        foreach ($ajuan as $a) {
            if ($a['id'] === (int) $l['pengajuan_id']) {
                if ($a['balasan'] !== null) {
                    return [null, $this->ke($kotak, 'error', 'Ajuan ' . $a['kode'] . ' sudah punya Surat Balasan (' . SuratSekolahModel::kode($a['balasan']['id']) . '). Tekan "Tandai selesai" pada laporannya.')];
                }

                return [$l, null];
            }
        }

        return [null, $this->ke($kotak, 'error', 'Ajuan laporan ini tidak lagi berstatus disetujui (atau siswanya tidak aktif), jadi Surat Balasan tidak bisa dibuat. Kembalikan laporan ke siswa.')];
    }

    private function simpan(string $jenis): RedirectResponse
    {
        if (($tolak = $this->tolakJenis('buat', $jenis)) !== null) {
            return $tolak;
        }
        $post  = (array) $this->request->getPost();
        $balik = site_url(SuratJenis::alamat($jenis));
        // Dari laporan siswa (lapor_id): surat HANYA untuk ajuan laporan itu; alamat boleh dikoreksi (kosong = alamat ajuan).
        // Tanpa laporan, alamat diambil dari ajuan (kolom alamat hanya ada di formulir ubah / dari laporan).
        $idLapor = $jenis === SuratJenis::BALASAN ? (int) ($post['lapor_id'] ?? 0) : 0;
        if ($idLapor > 0) {
            [$lapor, $alih] = $this->laporSiap($idLapor, (new Lib())->ajuan());
            if ($alih !== null) {
                return $alih;
            }
            $balik .= '?lapor=' . $idLapor;
            $post['ajuan'] = [(int) $lapor['pengajuan_id']];
            if (IsianBantu::rapikan((string) ($post['alamat'] ?? '')) === '') {
                unset($post['alamat']);
            }
        } else {
            unset($post['alamat']);
        }
        $h = Lib::periksa($jenis, $post);
        if (! $h['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', $h['galat']);
        }
        $hasil   = (new Lib())->buat($jenis, $h, (array) ($post['ajuan'] ?? []), (array) ($post['siswa'] ?? []), $this->konteks(['saluran' => 'web']), $this->p, self::opsiTerima($jenis, $post));
        $catatan = array_merge($hasil['sama'], $hasil['gagal']);
        $tautan  = $hasil['dilepas'] !== [] ? ' ' . Lib::teksDilepas($hasil['dilepas']) : '';
        if (! $hasil['ok']) {
            return redirect()->to($balik)->withInput()->with('error', 'Tidak ada surat yang dibuat. ' . implode(' ', $catatan));
        }
        // Laporan siswa ajuan itu ditandai selesai oleh SuratPkl::buat (jalur mana pun surat dibuat).
        $n       = (int) ($hasil['lapor'] ?? 0);
        $tautan .= $n > 0 ? ($n === 1 ? ' Laporan siswa ditandai selesai.' : ' ' . $n . ' laporan siswa ditandai selesai.') : '';

        $acc     = $this->langsungAcc($hasil['dibuat'], $jenis);
        $catatan = array_merge($catatan, $acc['catatan']);
        $akhir   = $this->akhirBuat($jenis, count($hasil['dibuat']), $acc) . $tautan;
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
        $aid = (int) ($m['surat']['pengajuan_id'] ?? 0);
        $r   = (new Lib())->ubahSurat($m, $h, (array) ($post['siswa'] ?? []), $this->konteks(['saluran' => 'web']), [
            'terima'       => self::opsiTerima($jenis, $post)['terima'][$aid] ?? null,
            'alasan_tolak' => (string) ($post['alasan_tolak'] ?? ''),
        ]);
        if (! $r['ok']) {
            return redirect()->to($balik)->withInput()->with('error', (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.'));
        }

        return $this->ke('admin/surat/' . $id, 'success', 'Data surat diperbarui.' . (($r['dilepas'] ?? []) !== [] ? ' ' . Lib::teksDilepas($r['dilepas']) : '')
            . ($m['surat']['status'] === 'dikembalikan' ? ' Surat masih berstatus Dikembalikan: tekan "Ajukan ulang untuk ACC" bila sudah siap.' : ''));
    }

    /**
     * Balasan: centang "siswa yang diterima perusahaan" dari formulir — atur_terima[{ajuan}] = 1 menandai daftar ajuan itu diatur,
     * terima[{ajuan}][] = siswa yang dicentang (kosong = tidak ada yang dicentang → ditolak dengan pesan). Ajuan tanpa atur_terima =
     * semua siswa diterima (perilaku lama).
     *
     * @param array<string, mixed> $post
     *
     * @return array{terima: array<int, list<int>>, alasan_tolak: string}
     */
    private static function opsiTerima(string $jenis, array $post): array
    {
        $terima = [];
        if ($jenis === SuratJenis::BALASAN) {
            foreach (array_keys((array) ($post['atur_terima'] ?? [])) as $aid) {
                $terima[(int) $aid] = array_map('intval', array_filter((array) ($post['terima'][$aid] ?? []), 'is_scalar'));
            }
        }

        return ['terima' => $terima, 'alasan_tolak' => (string) ($post['alasan_tolak'] ?? '')];
    }

    // ----- pembantu ---------------------------------------------------
    /** @return array<string, mixed> */
    private function dasarForm(string $jenis): array
    {
        return $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + $this->dasarJenis($jenis) + [
            'jenis'       => $jenis,
            'label'       => SuratJenis::label($jenis),
            'sapaanSaran' => Lib::SAPAAN_SARAN,
            'alasanSaran' => Lib::ALASAN_SARAN,
            'alasanTolak' => $jenis === SuratJenis::BALASAN ? (new Lib())->alasanTolak() : ['bawaan' => '', 'saran' => []],
            'batas'       => [
                'awal' => (string) ($this->p['mulai_paling_awal'] ?? ''), 'akhir' => (string) ($this->p['selesai_paling_akhir'] ?? ''),
                'min' => (int) ($this->p['durasi_min_hari'] ?? 0), 'maks' => (int) ($this->p['durasi_maks_hari'] ?? 0),
            ],
        ];
    }
}
