<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\PklBiaya as Biaya;
use App\Libraries\PklLaporanBiaya;
use App\Libraries\PklNomorSurat;
use App\Libraries\PklSurat;
use App\Libraries\PklWa;
use App\Models\AuditModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PKL — biaya yang dicatat saat unduh surat, kabar WhatsApp manual, koreksi catatan, dan Laporan Pembayaran.
 * Aturan ada di Libraries (PklBiaya, PklWa, PklLaporanBiaya, PklUnduh); controller ini hanya jembatan web.
 * Hak: 'surat' (siap, WA, koreksi), 'laporan' (laporan), 'pengaturan' (nominal biaya). Dijaga filter rute
 * (HakAkses::boleh → PklHak) DAN diperiksa lagi di tiap aksi.
 */
class PklBiaya extends PklDasar
{
    private const PER = 40;

    // =================================================================
    // Dialog unduh: keadaan siswa + WhatsApp
    // =================================================================

    /**
     * GET admin/pkl/surat/siap?mode=terpilih|belum|semua&ids[]= — apa yang akan diunduh dan keadaan biaya tiap siswa
     * (yang sudah tercatat, beasiswa). Hanya baca; dipakai kotak dialog sebelum unduhan.
     */
    public function siap(): ResponseInterface
    {
        if (($tolak = $this->wajibHak('surat')) !== null) {
            return $tolak;
        }
        $pilih = (new PklSurat())->pilihUntukUnduh((string) $this->request->getGet('mode'), (array) $this->request->getGet('ids'), 300);
        if ($pilih['ok'] === false) {
            return $this->json(['ok' => false, 'message' => $pilih['pesan']], 422);
        }
        if ($pilih['ids'] === []) {
            return $this->json(['ok' => true, 'data' => ['kosong' => true, 'pesan' => $pilih['pesan'], 'surat' => [], 'siswa' => []]]);
        }
        $data = (new Biaya())->siap($pilih['ids']);
        $data['kosong'] = false;
        $data['siswa'] = array_values($data['siswa']);
        $data['ringkas'] = ['surat' => count($data['surat']), 'siswa' => count($data['siswa'])];

        return $this->json(['ok' => true, 'data' => $data]);
    }

    /** GET admin/pkl/surat/hasil/{token} — siswa pada unduhan yang barusan selesai (untuk tombol WhatsApp). */
    public function hasil($token): ResponseInterface
    {
        if (($tolak = $this->wajibHak('surat')) !== null) {
            return $tolak;
        }
        $token = (string) $token;
        $isi   = preg_match('/^[0-9]{1,40}$/', $token) === 1 ? cache('pkl_unduh_' . $token) : null;
        if (! is_array($isi) || (int) ($isi['admin'] ?? 0) !== (int) session('admin.id')) {
            return $this->json(['ok' => true, 'data' => []]);
        }

        return $this->json(['ok' => true, 'data' => (new PklWa())->daftar((array) $isi['ids'], $this->p, (new SettingModel())->get())]);
    }

    /** GET admin/pkl/wa?ids[]= — daftar WhatsApp untuk ajuan tertentu (tombol "Kabari" di daftar & detail). */
    public function wa(): ResponseInterface
    {
        if (($tolak = $this->wajibHak('surat')) !== null) {
            return $tolak;
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getGet('ids'))))), 0, 300);

        return $this->json(['ok' => true, 'data' => (new PklWa())->daftar($ids, $this->p, (new SettingModel())->get())]);
    }

    /** POST admin/pkl/{ajuan}/wa/{siswa}/tandai — staf menekan tombol WhatsApp: catat "sudah dikabari". */
    public function tandaiWa($ajuanId, $siswaId): ResponseInterface
    {
        if (($tolak = $this->wajibHak('surat')) !== null) {
            return $tolak;
        }
        $ok = (new PklWa())->tandai((int) $ajuanId, (int) $siswaId, $this->konteks());

        return $this->json($ok
            ? ['ok' => true, 'waktu' => date('Y-m-d H:i:s'), 'oleh' => $this->konteks()['oleh']]
            : ['ok' => false, 'message' => 'Siswa tidak ditemukan pada ajuan yang disetujui.'], $ok ? 200 : 404, true);
    }

    // =================================================================
    // Koreksi catatan biaya (dari halaman detail ajuan)
    // =================================================================

    public function hapusPembayaran($ajuanId): RedirectResponse
    {
        $ajuanId = (int) $ajuanId;
        $balik   = 'admin/pkl/' . $ajuanId;
        if (! HakAkses::bolehPkl($this->peranSaya(), 'surat')) {
            return $this->ke($balik, 'error', 'Akun ini tidak punya hak mengoreksi catatan biaya.');
        }
        $hasil = (new Biaya())->hapusPembayaran((int) $this->request->getPost('pembayaran_id'), $ajuanId, (string) $this->request->getPost('alasan'), $this->konteks());

        return $this->ke($balik, $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    public function cabutBeasiswa($ajuanId): RedirectResponse
    {
        $ajuanId = (int) $ajuanId;
        $balik   = 'admin/pkl/' . $ajuanId;
        if (! HakAkses::bolehPkl($this->peranSaya(), 'surat')) {
            return $this->ke($balik, 'error', 'Akun ini tidak punya hak mengoreksi catatan biaya.');
        }
        $hasil = (new Biaya())->cabutBeasiswa((int) $this->request->getPost('siswa_id'), $ajuanId, (string) $this->request->getPost('alasan'), $this->konteks());

        return $this->ke($balik, $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    // =================================================================
    // Pengaturan biaya & pesan WhatsApp (bagian dari Pengaturan PKL)
    // =================================================================

    /** POST admin/pkl/pengaturan/biaya — nama, nominal, aktif tiap jenis biaya + templat pesan WhatsApp. */
    public function simpanBiaya(): RedirectResponse
    {
        $balik = 'admin/pkl/pengaturan#biaya';
        if (! HakAkses::boleh($this->peranSaya(), 'admin/pkl/pengaturan')) {
            return $this->ke($balik, 'error', 'Akun ini tidak boleh mengubah pengaturan PKL.');
        }
        $hasil = (new Biaya())->simpanJenis((array) $this->request->getPost('biaya'), $this->konteks());

        // Pesan WhatsApp (kosong = kembali ke templat bawaan).
        $pesan = trim((string) $this->request->getPost('wa_pesan'));
        $galatWa = null;
        if ($pesan !== '' && $pesan !== PklWa::PESAN_BAWAAN) {
            $galatWa = PklWa::periksaPesan($pesan);
        }
        if ($hasil['ok'] && $galatWa === null) {
            db_connect()->table('pkl_pengaturan')->where('id', 1)->update([
                'wa_pesan' => ($pesan === '' || $pesan === PklWa::PESAN_BAWAAN) ? null : $pesan, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new AuditModel())->record('update', 'pkl_pengaturan', 1, 'Pengaturan biaya / pesan WhatsApp PKL disimpan oleh ' . $this->konteks()['oleh']);

            return $this->ke($balik, 'success', $hasil['pesan'] . ' Pesan WhatsApp disimpan.');
        }

        return redirect()->to(site_url($balik))->withInput()->with('error', $galatWa ?? $hasil['pesan'])->with('galat_biaya', $hasil['galat']);
    }

    // =================================================================
    // Laporan Pembayaran
    // =================================================================

    public function laporan()
    {
        if (! HakAkses::bolehPkl($this->peranSaya(), 'laporan')) {
            return $this->ke('admin/pkl', 'error', 'Akun ini tidak punya hak melihat Laporan Pembayaran.');
        }
        $f      = $this->saringan();
        $hasil  = (new PklLaporanBiaya())->data($f);
        $page   = max(1, (int) $this->request->getGet('page'));
        $total  = count($hasil['baris']);
        $jmlHal = max(1, (int) ceil($total / self::PER));
        $page   = min($page, $jmlHal);

        return view('admin/pkl/laporan', $this->dasar('Laporan Pembayaran', 'laporan') + [
            'f'       => $f,
            'ringkas' => $hasil['ringkas'],
            'jenis'   => $hasil['jenis'],
            'baris'   => array_slice($hasil['baris'], ($page - 1) * self::PER, self::PER),
            'total'   => $total,
            'page'    => $page,
            'jmlHal'  => $jmlHal,
            'kelas'   => $this->model->kelasBersiswa(),
            'queryUnduh' => http_build_query(array_filter($f, static fn ($v) => $v !== '' && $v !== 0 && $v !== false && $v !== null)),
        ]);
    }

    public function laporanExcel()
    {
        if (! HakAkses::bolehPkl($this->peranSaya(), 'laporan')) {
            return $this->ke('admin/pkl', 'error', 'Akun ini tidak punya hak mengunduh Laporan Pembayaran.');
        }
        $f      = $this->saringan();
        $svc    = new PklLaporanBiaya();
        $hasil  = $svc->data($f);
        $biner  = $svc->excel($hasil, ['teks' => $this->teksSaringan($f)], (new SettingModel())->get());
        $nama   = 'Laporan Pembayaran PKL ' . date('Y-m-d') . '.xlsx';
        (new AuditModel())->record('update', 'pkl_pembayaran', null, 'Laporan Pembayaran PKL diunduh (' . count($hasil['baris']) . ' siswa)' . ($this->teksSaringan($f) !== '' ? ' — ' . $this->teksSaringan($f) : ''));

        return $this->response->download($nama, $biner)->setFileName($nama);
    }

    /** @return array{kelas_id: int, jurusan: string, status: string, q: string, dari: string, sampai: string, beasiswa: bool} */
    private function saringan(): array
    {
        $jur = strtoupper(trim((string) $this->request->getGet('jurusan')));
        $st  = strtolower(trim((string) $this->request->getGet('status')));

        return [
            'kelas_id' => (int) $this->request->getGet('kelas_id'),
            'jurusan'  => in_array($jur, ['TKJ', 'AKL', 'MP'], true) ? $jur : '',
            'status'   => isset(PklLaporanBiaya::STATUS[$st]) ? $st : '',
            'q'        => \App\Libraries\IsianBantu::rapikan((string) $this->request->getGet('q')),
            'dari'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('dari')) ? (string) $this->request->getGet('dari') : '',
            'sampai'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('sampai')) ? (string) $this->request->getGet('sampai') : '',
            'beasiswa' => $this->request->getGet('beasiswa') === '1',
        ];
    }

    private function teksSaringan(array $f): string
    {
        $t = [];
        if ($f['kelas_id'] > 0) {
            foreach ($this->model->kelasBersiswa() as $k) {
                if ((int) $k['id'] === $f['kelas_id']) {
                    $t[] = 'kelas ' . $k['nama_kelas'];
                }
            }
        }
        if ($f['jurusan'] !== '') {
            $t[] = 'jurusan ' . (PklLaporanBiaya::JURUSAN[$f['jurusan']] ?? $f['jurusan']);
        }
        if ($f['status'] !== '') {
            $t[] = 'status ' . PklLaporanBiaya::STATUS[$f['status']];
        }
        if ($f['beasiswa']) {
            $t[] = 'penerima beasiswa';
        }
        if ($f['dari'] !== '' || $f['sampai'] !== '') {
            $t[] = 'tanggal ' . ($f['dari'] ?: '…') . ' s/d ' . ($f['sampai'] ?: '…');
        }
        if ($f['q'] !== '') {
            $t[] = 'cari "' . $f['q'] . '"';
        }

        return implode(', ', $t);
    }
}
