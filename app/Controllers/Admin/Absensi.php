<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AbsensiHarian;
use App\Libraries\AbsensiLaporan;
use App\Libraries\AbsensiRekap;
use App\Libraries\AbsensiWa;
use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AuditModel;
use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use App\Models\JabatanModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Absensi manual guru per sesi mengajar (berbasis jadwal KBM).
 * Pilih tanggal → sistem tampilkan sesi hari itu (default HADIR) → admin
 * tandai yang telat/izin/sakit/alpa → simpan. Hanya pengecualian disimpan.
 * Rekap merangkum kehadiran per guru pada rentang tanggal (untuk gaji).
 */
class Absensi extends BaseController
{
    /**
     * Ringkasan absensi guru satu tanggal — dipakai highlight dashboard web
     * & API. Status harian per ORANG = terburuk dari sesi + kehadiran kerja;
     * guru ganda digabung & yang tidak ikut absensi dibuang (lihat muat()).
     */
    public static function ringkasHarian(string $tanggal): array
    {
        $d      = AbsensiHarian::muat($tanggal);
        $harian = [];
        foreach ($d['grup'] as $g) {
            foreach ($g['sesi'] as $s) {
                $harian[$g['guru_id']] = AbsensiGuruModel::worst($harian[$g['guru_id']] ?? 'hadir', $s['status']);
            }
        }
        // Kehadiran kerja (di luar jadwal) ikut jadi status harian guru.
        foreach ($d['kerja'] as $k) {
            $gid          = (int) $k['guru_id'];
            $harian[$gid] = AbsensiGuruModel::worst($harian[$gid] ?? 'hadir', $k['status']);
        }

        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        if ($d['recorded']) {
            foreach ($harian as $st) {
                $ringkas[$st] = ($ringkas[$st] ?? 0) + 1;
            }
        }

        return [
            'tanggal'    => $tanggal,
            'hari_nama'  => $d['namaHari'],
            'hari_aktif' => $d['hariAktif'],
            'recorded'   => $d['recorded'],
            'total_sesi' => $d['total'],
            'total_guru' => count($harian),
            'ringkas'    => $ringkas,
        ];
    }

    // ===================== INPUT HARIAN =====================
    public function index()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $d       = AbsensiHarian::muat($tanggal);
        $setting = (new SettingModel())->get();

        return view('admin/absensi/index', [
            'title'       => 'Absensi Guru',
            'tanggal'     => $tanggal,
            // Shift laporan (pagi/siang) — default menurut jam sekarang.
            'shift'       => AbsensiWa::normalShift($this->request->getGet('shift')),
            'belum'       => $d['belum'],
            'piket'       => $d['piket'],
            'waTemplate'  => AbsensiWa::template($setting),
            'waCustom'    => trim((string) ($setting['wa_template_absensi'] ?? '')) !== '',
            'namaHari'    => $d['namaHari'],
            'hariAktif'   => $d['hariAktif'],
            'grup'        => $d['grup'],
            'total'       => $d['total'],
            'sekolah'     => $setting['school_name'] ?? '',
            'recorded'    => $d['recorded'],
            'kerja'       => $d['kerja'],
            'guruOptions' => $d['guruOptions'],
            'saranKerja'  => $d['saran'],
            'jabatanMap'  => $d['jabatanMap'],
        ]);
    }

    /**
     * Simpan absensi satu tanggal: sesi mengajar + kehadiran kerja + daftar
     * belum hadir (shift terpilih) sekaligus. Dipanggil lewat form biasa
     * (redirect) atau AJAX tombol "Kirim WA" (JSON berisi teks pesan).
     */
    public function save()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));
        $shift   = AbsensiWa::normalShift($this->request->getPost('shift'));
        $adminId = session('admin')['id'] ?? null;

        // Halaman mengirim sesi & kerja sebagai JSON (1 isian) agar tidak terpotong
        // batas max_input_vars PHP; bentuk array lama tetap diterima.
        $rows  = $this->jsonPost('rows_json') ?? $this->request->getPost('rows');
        $kerja = $this->jsonPost('kerja_json') ?? $this->request->getPost('kerja');
        $belum = $this->request->getPost('belum');
        AbsensiHarian::simpan($tanggal, [
            'rows'  => is_array($rows) ? $rows : [],
            // Penanda *_sync membedakan "daftar kosong" dari "bagian tidak dikirim".
            'kerja' => $this->request->getPost('kerja_sync') ? (is_array($kerja) ? $kerja : []) : null,
            'shift' => $shift,
            'belum' => $this->request->getPost('belum_sync') ? (is_array($belum) ? $belum : []) : null,
        ], $adminId);
        (new AuditModel())->record('update', 'absensi_guru', null, 'Simpan absensi ' . $tanggal . ' (' . $shift . ')');

        $pesan = 'Absensi tanggal ' . $tanggal . ' berhasil disimpan.';
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'ok'       => true,
                'message'  => $pesan,
                'pesan_wa' => AbsensiWa::pesan($tanggal, $shift),
            ]);
        }

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal . '&shift=' . $shift)
            ->with('success', $pesan);
    }

    /**
     * Simpan KEHADIRAN KERJA (di luar jadwal) satu tanggal. Sinkron ke daftar
     * yang dikirim (guru yang dihapus dari daftar → catatannya dibuang). Tanggal
     * ikut ditandai tercatat agar masuk rekap.
     */
    public function saveKerja()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));
        $rows    = $this->request->getPost('kerja');
        $rows    = is_array($rows) ? $rows : [];
        $adminId = session('admin')['id'] ?? null;

        (new AbsensiKerjaModel())->syncDate($tanggal, $rows, $adminId);
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_kerja', null, 'Simpan kehadiran kerja ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal)
            ->with('success', 'Kehadiran kerja tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /** Batalkan pencatatan satu hari: hapus registry + semua tandaan hari itu. */
    public function unrecord()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));

        AbsensiHarian::batalkan($tanggal);
        (new AuditModel())->record('delete', 'absensi_hari', null, 'Batal catat absensi ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal . '&shift=' . AbsensiWa::normalShift($this->request->getPost('shift')))
            ->with('success', 'Pencatatan absensi tanggal ' . $tanggal . ' dibatalkan. Hari ini kembali "belum diabsen".');
    }

    /**
     * Simpan template pesan WhatsApp (AJAX). Kosong / "reset" = kembali ke
     * template bawaan.
     */
    public function templateWa()
    {
        $tpl = trim(str_replace("\r\n", "\n", (string) $this->request->getPost('template')));
        if ($this->request->getPost('reset') || $tpl === '') {
            $tpl = null;
        } elseif (mb_strlen($tpl) > 5000) {
            return $this->response->setJSON(['ok' => false, 'message' => 'Template terlalu panjang (maks 5000 karakter).']);
        }
        (new SettingModel())->store(['wa_template_absensi' => $tpl]);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah template WA absensi');

        return $this->response->setJSON([
            'ok'       => true,
            'message'  => $tpl === null ? 'Template dikembalikan ke bawaan.' : 'Template pesan disimpan.',
            'template' => $tpl ?? AbsensiWa::DEFAULT_TEMPLATE,
            'custom'   => $tpl !== null,
        ]);
    }

    /** Pratinjau teks pesan WA dari data TERSIMPAN (AJAX). */
    public function pesanWa()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $shift   = AbsensiWa::normalShift($this->request->getGet('shift'));

        return $this->response->setJSON(['ok' => true, 'pesan_wa' => AbsensiWa::pesan($tanggal, $shift)]);
    }

    // ===================== REKAP =====================
    public function rekap($format = 'html')
    {
        [$dari, $sampai] = $this->rentang();
        // Filter jabatan (mis. hanya wakil kepala) — berlaku juga untuk export
        // agar berkas unduhan persis seperti yang tampil di layar.
        $jabatanId = (int) $this->request->getGet('jabatan_id');
        $rows      = AbsensiRekap::rekapData($dari, $sampai, $jabatanId);
        $setting   = (new SettingModel())->get();

        if ($format === 'pdf') {
            $html = AbsensiRekap::pdfHtml($rows, $dari, $sampai, $setting, AbsensiRekap::labelJabatan($jabatanId));
            $this->streamPdf($html, AbsensiRekap::namaBerkas($dari, $sampai));
            return null;
        }
        if ($format === 'excel') {
            $ss = AbsensiRekap::excel($rows, $dari, $sampai, $setting, AbsensiRekap::labelJabatan($jabatanId));
            $this->streamXlsx($ss, AbsensiRekap::namaBerkas($dari, $sampai));
            return null;
        }

        return view('admin/absensi/rekap', [
            'title'       => 'Rekap Absensi Guru',
            'rows'        => $rows, 'dari' => $dari, 'sampai' => $sampai, 'sum' => AbsensiRekap::sum($rows),
            'hariTercatat' => count((new AbsensiHariModel())->datesInRange($dari, $sampai)),
            'jabatanId'   => $jabatanId,
            'jabatanOpts' => (new JabatanModel())->options(),
            // Laporan bulanan format sekolah: bulan default = bulan tanggal "dari".
            'bulanLaporan' => substr($dari, 0, 7),
            'tarifJp'      => (int) ($setting['absensi_potongan_jp'] ?? 5000),
            'tarifTrans'   => (int) ($setting['absensi_transport'] ?? 0),
        ]);
    }

    /** Rincian ketidakhadiran satu guru pada rentang. */
    public function rekapGuru($id = 0)
    {
        [$dari, $sampai] = $this->rentang();
        $guru            = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return redirect()->to(site_url('admin/absensi/rekap'))->with('error', 'Guru tidak ditemukan.');
        }

        // Rincian: sesi mengajar (pengecualian) + kehadiran kerja (di luar jadwal).
        $detail = (new AbsensiGuruModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai);
        foreach ((new AbsensiKerjaModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai) as $k) {
            $detail[] = [
                'tanggal'         => $k['tanggal'],
                'status'          => $k['status'],
                'jam_masuk'       => $k['jam_masuk'],
                'keterangan'      => $k['keterangan'],
                'nama_kelas'      => null,
                'nama_mapel'      => null,
                'jam_ke'          => null,
                'waktu_mulai'     => null,
                'waktu_selesai'   => null,
                'kehadiran_kerja' => true,
            ];
        }
        // Belum hadir yang tidak diselesaikan (dihitung tidak hadir).
        foreach ((new AbsensiBelumModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai) as $b) {
            $detail[] = $b + [
                'nama_kelas' => null, 'nama_mapel' => null, 'jam_ke' => null,
                'waktu_mulai' => null, 'waktu_selesai' => null, 'kehadiran_kerja' => false,
            ];
        }
        usort($detail, static fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        // Ringkas per HARI dari status harian gabungan (mengajar + kerja).
        $perTgl = AbsensiRekap::dailyStatus($dari, $sampai)[(int) $id] ?? [];
        $total  = count($perTgl);
        $cnt    = ['telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($perTgl as $st) {
            if (isset($cnt[$st])) {
                $cnt[$st]++;
            }
        }
        $ringkas = [
            'total' => $total,
            'hadir' => max(0, $total - array_sum($cnt)),
        ] + $cnt;

        return view('admin/absensi/rekap_guru', [
            'title'   => 'Rincian Absensi — ' . $guru['nama'],
            'guru'    => $guru, 'detail' => $detail, 'ringkas' => $ringkas,
            'dari'    => $dari, 'sampai' => $sampai,
            'jabatan' => (new GuruJabatanModel())->forGuru((int) $id),
        ]);
    }

    // ===================== LAPORAN BULANAN (format sekolah) =====================
    /** GET admin/absensi/laporan/{excel|pdf}?bulan=YYYY-MM */
    public function laporan($format = 'pdf')
    {
        [$dari, $sampai, $bulan] = AbsensiLaporan::rentangBulan($this->request->getGet('bulan'));
        $m       = AbsensiLaporan::matriks($dari, $sampai);
        $setting = (new SettingModel())->get();

        if ($format === 'excel') {
            $this->streamXlsx(AbsensiLaporan::excel($m, $bulan, $setting), AbsensiLaporan::namaBerkas($bulan));
            return null;
        }
        $this->streamPdf(AbsensiLaporan::pdfHtml($m, $bulan, $setting), AbsensiLaporan::namaBerkas($bulan), AbsensiLaporan::KERTAS_F4);
        return null;
    }

    /** POST admin/absensi/tarif — potongan per JP & uang transport per hari. */
    public function tarif()
    {
        (new SettingModel())->store([
            'absensi_potongan_jp' => max(0, (int) $this->request->getPost('absensi_potongan_jp')),
            'absensi_transport'   => max(0, (int) $this->request->getPost('absensi_transport')),
        ]);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah tarif potongan/transport absensi');

        return redirect()->back()->with('success', 'Tarif potongan & transport disimpan.');
    }

    // ===================== HELPER =====================
    /** Ambil rentang tanggal dari GET; default bulan berjalan; tukar bila terbalik. */
    private function rentang(): array
    {
        $dari   = $this->normalTanggal($this->request->getGet('dari') ?: date('Y-m-01'));
        $sampai = $this->normalTanggal($this->request->getGet('sampai') ?: date('Y-m-t'));
        if (strtotime($sampai) < strtotime($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        return [$dari, $sampai];
    }

    /** Isian POST berisi JSON array → array; null bila kosong/tidak valid. */
    private function jsonPost(string $name): ?array
    {
        $raw = (string) $this->request->getPost($name);
        if ($raw === '') {
            return null;
        }
        $val = json_decode($raw, true);

        return is_array($val) ? $val : null;
    }

    /** Validasi/normalisasi tanggal → Y-m-d; fallback hari ini. */
    private function normalTanggal(?string $raw): string
    {
        $raw = trim((string) $raw);
        $ts  = $raw !== '' ? strtotime($raw) : false;
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function streamPdf(string $html, string $filename, string|array $kertas = 'A4'): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($kertas, 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => false]);
        exit;
    }

    private function streamXlsx(Spreadsheet $ss, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
