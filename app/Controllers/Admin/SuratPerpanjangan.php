<?php

namespace App\Controllers\Admin;

use App\Libraries\IsianBantu;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPerpanjangan as Lib;
use App\Libraries\SuratPkl;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Surat Permohonan Perpanjangan PKL — halaman staf (hak Buat jenis ini). Aturan, pencegah ganda, dan jejak ada di
 * Libraries\SuratPerpanjangan (rancangan: docs/DESAIN-PERPANJANGAN-PKL.md bagian 6); ACC / unduh / WA di Admin\Surat.
 *
 * Alur halaman (pola tampilan baru: cari lalu pilih, satu tombol utama):
 *   GET  admin/surat/perpanjangan            daftar "PKL berakhir ≤ 7 hari" + kotak cari
 *   GET  admin/surat/perpanjangan?q=…        hasil cari ajuan disetujui (perusahaan / kode / nama siswa), satu baris per perusahaan
 *   GET  admin/surat/perpanjangan?ajuan=ID   formulir untuk ajuan itu + semua siswa lain yang PKL di perusahaan yang sama (2026-10-28)
 *   POST admin/surat/perpanjangan            simpan (tombol "Simpan & setujui" bila memegang hak ACC)
 *   GET|POST admin/surat/perpanjangan/{id}/ubah
 */
class SuratPerpanjangan extends SuratDasar
{
    public function index()
    {
        if (($tolak = $this->tolakJenis('buat', SuratJenis::PERPANJANGAN)) !== null) {
            return $tolak;
        }
        $lib   = new Lib();
        $aid   = (int) $this->request->getGet('ajuan');
        $q     = trim((string) $this->request->getGet('q'));
        $ajuan = null;
        if ($aid > 0) {
            $ajuan = $lib->ajuanForm($aid);
            if ($ajuan === null) {
                return $this->ke(SuratJenis::alamat(SuratJenis::PERPANJANGAN), 'error', 'Ajuan ' . \App\Models\PklPengajuanModel::kode($aid) . ' tidak ditemukan, belum disetujui, atau siswanya tidak aktif lagi.');
            }
        }
        // Bawaan tercentang: siswa ajuan yang dipilih saja (siswa ajuan lain di perusahaan yang sama tampil, tidak tercentang).
        $pilihAwal = array_values(array_filter(array_map(static fn (array $s) => $s['bisa'] && $s['ajuan_ini'] ? $s['siswa_id'] : 0, $ajuan['siswa'] ?? [])));
        $sidGet    = (int) $this->request->getGet('siswa');

        return view('admin/surat/perpanjangan_form', $this->dasarForm() + [
            'ubah'     => false,
            'q'        => $q,
            'hasil'    => $ajuan === null && $q !== '' ? $lib->cari($q) : [],
            'berakhir' => $ajuan === null && $q === '' ? $lib->berakhir(7) : [],
            'ajuan'    => $ajuan,
            'nilai'    => $this->nilaiAwal([
                'tanggal_surat' => date('Y-m-d'), 'sapaan' => SuratPkl::SAPAAN_BAWAAN, 'selesai_lama' => '', 'selesai_baru' => '',
                'alasan_pilih' => '0', 'alasan_lain' => '',
            ]) + ['siswa' => $this->idLama('siswa', $sidGet > 0 && in_array($sidGet, $pilihAwal, true) ? [$sidGet] : $pilihAwal)],
        ]);
    }

    public function simpan(): RedirectResponse
    {
        if (($tolak = $this->tolakJenis('buat', SuratJenis::PERPANJANGAN)) !== null) {
            return $tolak;
        }
        $post  = (array) $this->request->getPost();
        $aid   = (int) ($post['ajuan_id'] ?? 0);
        $balik = site_url(SuratJenis::alamat(SuratJenis::PERPANJANGAN)) . ($aid > 0 ? '?ajuan=' . $aid : '');
        $r     = (new Lib())->buat($post, $this->konteks(['saluran' => 'web']), $this->p, ['sumber' => 'staf']);
        if (! $r['ok']) {
            return redirect()->to($balik)->withInput()->with('errors', array_values($r['galat'] ?? [$r['pesan']]));
        }
        $acc   = $this->langsungAcc([(int) $r['surat_id']], SuratJenis::PERPANJANGAN);
        $pesan = $r['pesan'] . $this->akhirBuat(SuratJenis::PERPANJANGAN, 1, $acc);
        $redir = redirect()->to(site_url('admin/surat/' . (int) $r['surat_id']))->with('success', $pesan);

        return $acc['catatan'] !== [] ? $redir->with('errors', $acc['catatan']) : $redir;
    }

    /** @param int|string $id */
    public function ubah($id)
    {
        [$m, $alih] = $this->muatBisaDiubah(SuratJenis::PERPANJANGAN, (int) $id);
        if ($alih !== null) {
            return $alih;
        }
        $aid   = (int) ($m['surat']['pengajuan_id'] ?? 0);
        $ajuan = $aid > 0 ? (new Lib())->ajuanForm($aid) : null;
        if ($ajuan === null) {
            return $this->ke('admin/surat/' . (int) $id, 'error', 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi surat tidak bisa diubah. Batalkan surat ini bila perlu.');
        }
        // Siswa surat ini sendiri tetap bisa dipilih (perpanjangannya = surat ini).
        $diSurat = array_map('intval', array_column($m['siswa'], 'siswa_id'));
        foreach ($ajuan['siswa'] as &$s) {
            if (($s['pj']['surat_id'] ?? 0) === (int) $id) {
                $s['bisa'] = $s['ditarik'] === null;
                $s['pj']   = null;
            }
        }
        unset($s);
        $ajuan['bisa'] = count(array_filter(array_column($ajuan['siswa'], 'bisa')));
        $isi = $m['surat']['isi_arr'];
        if (($isi['sumber_lama'] ?? 'sekolah') !== 'sekolah' && $ajuan['selesai'] === null) {
            [$ajuan['selesai_lama'], $ajuan['sumber_lama']] = [(string) ($isi['selesai_lama'] ?? ''), (string) $isi['sumber_lama']];
        }
        $alasan = (string) ($isi['alasan_perpanjangan'] ?? '');
        $i      = Lib::indeksAlasan($alasan);

        return view('admin/surat/perpanjangan_form', $this->dasarForm() + [
            'ubah'  => true,
            'surat' => $m['surat'],
            'kode'  => SuratSekolahModel::kode((int) $id),
            'ajuan' => $ajuan,
            'q' => '', 'hasil' => [], 'berakhir' => [],
            'nilai' => $this->nilaiAwal([
                'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'sapaan' => (string) ($isi['sapaan'] ?? SuratPkl::SAPAAN_BAWAAN),
                'selesai_lama' => (string) ($isi['selesai_lama'] ?? ''), 'selesai_baru' => (string) ($isi['selesai_baru'] ?? ''),
                'alasan_pilih' => $i, 'alasan_lain' => $i === 'lain' ? $alasan : '',
            ]) + ['siswa' => $this->idLama('siswa', $diSurat)],
        ]);
    }

    /** @param int|string $id */
    public function simpanUbah($id): RedirectResponse
    {
        [$m, $alih] = $this->muatBisaDiubah(SuratJenis::PERPANJANGAN, (int) $id);
        if ($alih !== null) {
            return $alih;
        }
        $r = (new Lib())->ubahSurat($m, (array) $this->request->getPost(), $this->konteks(['saluran' => 'web']), $this->p);
        if (! $r['ok']) {
            return redirect()->to(site_url(SuratJenis::alamat(SuratJenis::PERPANJANGAN) . '/' . (int) $id . '/ubah'))->withInput()
                ->with('errors', array_values($r['galat'] ?? [$r['pesan']]) ?: [$r['pesan']]);
        }

        return $this->ke('admin/surat/' . (int) $id, 'success', 'Data surat diperbarui.'
            . ($m['surat']['status'] === 'dikembalikan' ? ' Surat masih berstatus Dikembalikan: tekan "Ajukan ulang untuk ACC" bila sudah siap.' : ''));
    }

    /** @return array<string, mixed> */
    private function dasarForm(): array
    {
        $j = SuratJenis::PERPANJANGAN;

        return $this->dasarSurat(SuratJenis::label($j), 'daftar') + $this->dasarJenis($j) + [
            'jenis'       => $j,
            'label'       => SuratJenis::label($j),
            'sapaanSaran' => SuratPkl::SAPAAN_SARAN,
            'alasanSaran' => Lib::ALASAN,
            'setelan'     => Lib::setelan($this->p),
            'batas'       => ['akhir' => (string) ($this->p['selesai_paling_akhir'] ?? ''), 'maks' => (int) ($this->p['durasi_maks_hari'] ?? 0), 'maksHari' => Lib::MAKS_HARI],
            'hariIni'     => date('Y-m-d'),
            'tglIndo'     => static fn (?string $t): string => IsianBantu::tanggalIndo($t),
        ];
    }
}
