<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratKeputusan;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratSekolah;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Sekolah — Daftar Surat (buku agenda surat keluar), detail, keputusan (ACC / kembalikan / batalkan), dan unduhan.
 * Pembuatan tiap jenis ada di controller jenis masing-masing (langkah berikutnya, docs/DESAIN-SURAT-SEKOLAH.md bagian 7).
 *
 * Alamat: admin/surat (daftar; saringan ?status=&jenis=&tahun=&q=&page=), admin/surat/{id} (detail), aksi POST di bawahnya.
 * Aturan keputusan: Libraries\SuratKeputusan; perakitan berkas: Libraries\SuratBerkas. Hak per alamat: PklHak::hakUntukAlamat.
 */
class Surat extends SuratDasar
{
    // =================================================================
    // Daftar & detail
    // =================================================================

    /** GET admin/surat */
    public function index()
    {
        $f = [
            'status' => (string) $this->request->getGet('status'),
            'jenis'  => (string) $this->request->getGet('jenis'),
            'tahun'  => (int) $this->request->getGet('tahun'),
            'q'      => mb_substr(IsianBantu::rapikan((string) $this->request->getGet('q')), 0, 80),
        ];
        if (! in_array($f['status'], SuratSekolahModel::STATUS, true)) {
            $f['status'] = '';
        }
        if (! SuratJenis::ada($f['jenis'])) {
            $f['jenis'] = '';
        }
        if ($f['tahun'] < 2000 || $f['tahun'] > 2100) {
            $f['tahun'] = 0;
        }

        $hasil = $this->surat->daftar($f, max(1, (int) $this->request->getGet('page')), SuratSekolah::PER_HALAMAN);
        $judul = $f['status'] !== '' ? SuratSekolahModel::TAMPIL_STATUS[$f['status']][0] : 'Daftar Surat';
        $peran = $this->peranSaya();

        return view('admin/surat/daftar', $this->dasarSurat($judul, 'daftar') + [
            'f'          => $f,
            'rows'       => $hasil['rows'],
            'total'      => $hasil['total'],
            'page'       => $hasil['page'],
            'jmlHal'     => $hasil['jml_hal'],
            'tahun'      => $this->surat->tahunTersedia(),
            'perluUlang' => $this->surat->perluUlangBanyak($hasil['rows'], $this->p),
            // Unduhan massal: hanya bagi yang berhak, pada tab "Siap unduh" yang sudah disaring ke SATU jenis yang templatenya ada.
            'bisaUnduhMassal' => $f['status'] === 'disetujui' && $f['jenis'] !== '' && HakAkses::bolehPkl($peran, 'surat_sekolah') && SuratJenis::templateLengkap($f['jenis']),
        ]);
    }

    /** GET admin/surat/(:num) */
    public function detail($id)
    {
        $m = $this->surat->muat((int) $id);
        if ($m === null) {
            return $this->ke('admin/surat', 'error', 'Surat tidak ditemukan (mungkin sudah dihapus).');
        }

        $surat  = $m['surat'];
        $jenis  = (string) $surat['jenis'];
        $peran  = $this->peranSaya();
        $status = (string) $surat['status'];

        return view('admin/surat/detail', $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + $m + [
            'kode'       => SuratSekolahModel::kode((int) $surat['id']),
            'perluUlang' => $status === 'disetujui' && SuratSekolah::perluCetakUlang($surat, $m['siswa'], $this->p),
            'ringkasIsi' => SuratJenis::ringkasIsi($surat['isi_arr']),
            'periksa'    => in_array($status, ['menunggu', 'disetujui'], true) ? SuratPeriksa::untuk($surat, $m['siswa'], $this->p) : [],
            'templateAda'    => is_file(SuratJenis::pathTemplate($jenis, SuratJenis::varian($jenis, $surat['isi_arr']))),
            'bolehPengaturan' => HakAkses::boleh($peran, 'admin/pkl/pengaturan'),
            'namaSaya'   => (string) (session('admin')['full_name'] ?? 'Anda'),
            // "Ubah data": hanya jenis yang formulirnya sudah dibangun, selama belum disetujui & belum bernomor.
            'alamatUbah' => SuratJenis::siap($jenis) && in_array($status, ['menunggu', 'dikembalikan'], true) && (string) ($surat['nomor'] ?? '') === ''
                ? site_url(SuratJenis::alamat($jenis) . '/' . (int) $surat['id'] . '/ubah') : '',
            // Persetujuan baru bisa dibatalkan selama surat belum bernomor dan belum pernah diunduh.
            'bisaBatalAcc' => $status === 'disetujui' && (int) $surat['perlu_acc'] === 1 && (string) ($surat['nomor'] ?? '') === '' && (int) $surat['cetak_ke'] === 0,
        ]);
    }

    // =================================================================
    // Keputusan
    // =================================================================

    /** POST admin/surat/(:num)/acc */
    public function acc($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'acc');
    }

    /** POST admin/surat/(:num)/kembalikan */
    public function kembalikan($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'kembalikan');
    }

    /** POST admin/surat/(:num)/batal-acc */
    public function batalAcc($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'batal_acc');
    }

    /** POST admin/surat/(:num)/ajukan-ulang */
    public function ajukanUlang($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'ajukan_ulang');
    }

    /** POST admin/surat/(:num)/batal */
    public function batal($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'batal');
    }

    /**
     * Satu pintu untuk semua keputusan. Seluruh aturan (hak, Admin "mewakili", status asal, alasan wajib, peringatan
     * bahaya, pencatatan) ada di Libraries\SuratKeputusan.
     */
    private function putuskan(int $id, string $aksi): RedirectResponse
    {
        $hasil = (new SuratKeputusan($this->surat))->putuskan($id, $aksi, (array) $this->request->getPost(), $this->konteks(['saluran' => 'web']), $this->p);
        if ($hasil['kode'] === 'tidak_ada') {
            return $this->ke('admin/surat', 'error', $hasil['pesan']);
        }

        return $this->ke('admin/surat/' . $id, $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /**
     * POST admin/surat/acc-massal — mode "terpilih" (ids[]) atau "aman" (semua yang menunggu). Hanya yang berhak ACC
     * (Admin cadangan, wajib "mewakili"); surat dengan peringatan dilewati dan dilaporkan.
     */
    public function accMassal(): RedirectResponse
    {
        $hasil = (new SuratKeputusan($this->surat))->accMassal((array) $this->request->getPost(), $this->konteks(['saluran' => 'web']), $this->p);
        $redir = redirect()->to(site_url('admin/surat') . '?status=menunggu')->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);

        return ($hasil['dilewati'] ?? []) !== [] ? $redir->with('errors', $hasil['dilewati']) : $redir;
    }

    // =================================================================
    // Unduhan
    // =================================================================

    /** POST admin/surat/(:num)/unduh — terbitkan nomor (bila belum) + unduh berkas Word satu surat. */
    public function unduh($id)
    {
        $id = (int) $id;
        if (($tolak = $this->tolakHalaman('surat_sekolah', 'admin/surat/' . $id)) !== null) {
            return $tolak;
        }
        $hasil = (new SuratBerkas($this->surat))->bangun([$id], $this->konteks());
        if (! $hasil['ok']) {
            return $this->ke('admin/surat/' . $id, 'error', $hasil['pesan']);
        }

        return $this->kirimBerkas($hasil['nama'], $hasil['biner']);
    }

    /**
     * POST admin/surat/unduh-massal — mode "terpilih" (ids[]), "belum" (belum pernah diunduh / perlu cetak ulang) atau
     * "semua", untuk SATU jenis (jenis=). Lebih dari 60 surat dipecah jadi ZIP.
     */
    public function unduhMassal()
    {
        $jenis = (string) $this->request->getPost('jenis');
        $balik = site_url('admin/surat') . '?' . http_build_query(array_filter(['status' => 'disetujui', 'jenis' => SuratJenis::ada($jenis) ? $jenis : null]));
        if (($tolak = $this->tolakHalaman('surat_sekolah', 'admin/surat')) !== null) {
            return $tolak;
        }

        $pilih = $this->surat->pilihUntukUnduh((string) $this->request->getPost('mode'), (array) $this->request->getPost('ids'), $jenis, SuratBerkas::MAKS_UNDUH, $this->p);
        if (! $pilih['ok']) {
            return redirect()->to($balik)->with('error', $pilih['pesan']);
        }
        if ($pilih['ids'] === []) {
            return redirect()->to($balik)->with('success', $pilih['pesan']);
        }
        $hasil = (new SuratBerkas($this->surat))->bangun($pilih['ids'], $this->konteks());
        if (! $hasil['ok']) {
            return redirect()->to($balik)->with('error', $hasil['pesan']);
        }

        return $this->kirimBerkas($hasil['nama'], $hasil['biner']);
    }
}
