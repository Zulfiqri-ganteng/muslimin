<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklHak;
use App\Libraries\SuratAgenda;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratKeputusan;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratSekolah;
use App\Libraries\SuratWa;
use App\Models\SettingModel;
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

    /** Saringan Daftar Surat dari ?status=&jenis=&tahun=&q= (nilai asing dikosongkan). */
    private function saringan(): array
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

        return $f;
    }

    /** GET admin/surat/excel — Buku Agenda Surat Keluar sesuai saringan yang tampil (semua halaman, maks 5.000). */
    public function excel()
    {
        $f    = $this->saringan();
        $teks = implode(' · ', array_filter([
            $f['status'] !== '' ? 'Status: ' . SuratSekolahModel::TAMPIL_STATUS[$f['status']][0] : '',
            $f['jenis'] !== '' ? 'Jenis: ' . SuratJenis::label($f['jenis']) : '',
            $f['tahun'] > 0 ? 'Tahun: ' . $f['tahun'] : '',
            $f['q'] !== '' ? 'Cari: "' . $f['q'] . '"' : '',
        ]));
        $biner = (new SuratAgenda())->excel($this->surat->daftar($f, 1, 5000)['rows'], $teks, (new SettingModel())->get());
        $nama  = 'Buku Agenda Surat Keluar ' . date('Y-m-d') . '.xlsx';

        return $this->response->download($nama, $biner)->setFileName($nama);
    }

    /** GET admin/surat */
    public function index()
    {
        $f     = $this->saringan();
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
            // Unduhan massal: hanya bagi yang berhak mengunduh jenis itu, pada tab "Siap unduh" yang sudah disaring ke SATU jenis
            // yang templatenya ada.
            'bisaUnduhMassal' => $f['status'] === 'disetujui' && $f['jenis'] !== '' && PklHak::bolehSurat($peran, 'unduh', $f['jenis']) && SuratJenis::templateLengkap($f['jenis']),
            // ACC massal: kotak centang hanya pada surat yang jenisnya boleh di-ACC peran ini.
            'jenisAcc'   => PklHak::jenisBoleh($peran, 'acc'),
            // Kabar WhatsApp massal "siap diambil": tab Siap unduh yang disaring ke jenis berkabar (Surat Balasan), hak Unduh.
            'kabarWaJenis' => $f['status'] === 'disetujui' && SuratWa::berlaku($f['jenis']) && SuratWa::kolomAda() && PklHak::bolehSurat($peran, 'unduh', $f['jenis']),
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

        // Hak per jenis ditaruh DI DEPAN (array + array: kunci kiri menang) supaya menimpa nilai "jenis apa pun" dasarSurat.
        return view('admin/surat/detail', [
            'bolehAcc'   => PklHak::bolehSurat($peran, 'acc', $jenis),
            'bolehBuat'  => PklHak::bolehSurat($peran, 'buat', $jenis),
            'bolehUnduh' => PklHak::bolehSurat($peran, 'unduh', $jenis),
        ] + $this->dasarSurat(SuratJenis::label($jenis), 'daftar') + $m + [
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
            'pemegangAcc'   => PklHak::teksPemegang(PklHak::kunciSurat('acc', $jenis)),
            'pemegangUnduh' => PklHak::teksPemegang(PklHak::kunciSurat('unduh', $jenis)),
            // Kabar WhatsApp "siap diambil" (Surat Balasan): tombol bagi pemegang hak Unduh; aktif setelah surat diunduh.
            'kabarWa'    => SuratWa::berlaku($jenis) && SuratWa::kolomAda() && PklHak::bolehSurat($peran, 'unduh', $jenis) && $status === 'disetujui',
            'hitungWa'   => SuratWa::berlaku($jenis) ? (new SuratWa())->hitung((int) $surat['id']) : ['total' => 0, 'dikabari' => 0],
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
     * Satu pintu untuk semua keputusan. Seluruh aturan (hak per jenis, status asal, alasan wajib, peringatan
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
     * POST admin/surat/acc-massal — mode "terpilih" (ids[]) atau "aman" (semua yang menunggu). Hanya jenis yang boleh di-ACC
     * peran ini; surat dengan peringatan dilewati dan dilaporkan.
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
        $id    = (int) $id;
        $jenis = (string) ($this->surat->muat($id)['surat']['jenis'] ?? '');
        if ($jenis === '') {
            return $this->ke('admin/surat', 'error', 'Surat tidak ditemukan (mungkin sudah dihapus).');
        }
        if (($tolak = $this->tolakJenis('unduh', $jenis, 'admin/surat/' . $id)) !== null) {
            return $tolak;
        }
        $hasil = (new SuratBerkas($this->surat))->bangun([$id], $this->konteks());
        if (! $hasil['ok']) {
            return $this->ke('admin/surat/' . $id, 'error', $hasil['pesan']);
        }

        return $this->kirimBerkas($hasil['nama'], $hasil['biner']);
    }

    // =================================================================
    // Kabar WhatsApp "siap diambil" (Surat Balasan) — Libraries\SuratWa
    // =================================================================

    /** GET admin/surat/(:num)/wa — siswa surat ini + tautan wa.me (hak Unduh jenis itu; surat sudah diunduh). */
    public function wa($id)
    {
        $id    = (int) $id;
        $jenis = (string) ($this->surat->muat($id)['surat']['jenis'] ?? '');
        if (($tolak = $this->tolakWa($jenis)) !== null) {
            return $tolak;
        }

        return $this->json(['ok' => true, 'data' => (new SuratWa())->daftar([$id], $this->p, (new SettingModel())->get())]);
    }

    /** GET admin/surat/wa?jenis= — kabar massal: siswa yang belum dikabari pada semua surat jenis itu yang sudah diunduh. */
    public function waJenis()
    {
        $jenis = (string) $this->request->getGet('jenis');
        if (($tolak = $this->tolakWa($jenis)) !== null) {
            return $tolak;
        }

        return $this->json(['ok' => true, 'data' => (new SuratWa())->belumDikabari($jenis, $this->p, (new SettingModel())->get())]);
    }

    /** POST admin/surat/(:num)/wa/(:num)/tandai — catat "sudah dikabari" setelah tombol WhatsApp ditekan. */
    public function tandaiWa($id, $siswaId)
    {
        $jenis = (string) ($this->surat->muat((int) $id)['surat']['jenis'] ?? '');
        if (($tolak = $this->tolakWa($jenis)) !== null) {
            return $tolak;
        }
        $r = (new SuratWa())->tandai((int) $id, (int) $siswaId, $this->konteks(['saluran' => 'web']));

        return $this->json($r + ['oleh' => $r['oleh'] ?? null], $r['ok'] ? 200 : 409, true);
    }

    /** null bila boleh mengabari jenis ini (jenis berlaku, migrasi ada, hak Unduh); selain itu respons JSON galat. */
    private function tolakWa(string $jenis)
    {
        if ($jenis === '' || ! SuratJenis::ada($jenis)) {
            return $this->json(['ok' => false, 'message' => 'Surat tidak ditemukan.'], 404);
        }
        if (! SuratWa::berlaku($jenis)) {
            return $this->json(['ok' => false, 'message' => 'Kabar WhatsApp belum tersedia untuk ' . SuratJenis::label($jenis) . '.'], 422);
        }
        if (! SuratWa::kolomAda()) {
            return $this->json(['ok' => false, 'message' => 'Fitur kabar WhatsApp belum aktif di server ini: jalankan "php spark migrate".'], 503);
        }
        if (! PklHak::bolehSurat($this->peranSaya(), 'unduh', $jenis)) {
            return $this->json(['ok' => false, 'message' => 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak mengunduh ' . SuratJenis::label($jenis) . ', jadi tidak bisa mengabari siswa. Admin bisa mengaturnya di Sistem → Hak Akses.'], 403);
        }

        return null;
    }

    /**
     * POST admin/surat/unduh-massal — mode "terpilih" (ids[]), "belum" (belum pernah diunduh / perlu cetak ulang) atau
     * "semua", untuk SATU jenis (jenis=). Lebih dari 60 surat dipecah jadi ZIP.
     */
    public function unduhMassal()
    {
        $jenis = (string) $this->request->getPost('jenis');
        $balik = site_url('admin/surat') . '?' . http_build_query(array_filter(['status' => 'disetujui', 'jenis' => SuratJenis::ada($jenis) ? $jenis : null]));
        if (SuratJenis::ada($jenis) && ($tolak = $this->tolakJenis('unduh', $jenis)) !== null) {
            return $tolak;
        }
        if (! SuratJenis::ada($jenis) && ($tolak = $this->tolakHalaman('surat_unduh:*')) !== null) {
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
