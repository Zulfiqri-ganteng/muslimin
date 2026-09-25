<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\AbsensiHarian;
use App\Libraries\AbsensiLaporan;
use App\Libraries\AbsensiRekap;
use App\Libraries\AbsensiWa;
use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Absensi guru (API). Cermin App\Controllers\Admin\Absensi; logika rekap &
 * export dipakai bersama lewat App\Libraries\AbsensiRekap.
 * Prinsip "hanya simpan pengecualian": tak ada baris = HADIR.
 *
 *   GET  /api/v1/admin/absensi?tanggal=            → sesi hari itu (untuk input)
 *   POST /api/v1/admin/absensi/save               → simpan absensi 1 tanggal
 *   POST /api/v1/admin/absensi/save-kerja         → simpan kehadiran kerja 1 tanggal
 *   POST /api/v1/admin/absensi/unrecord           → batalkan pencatatan 1 tanggal
 *   GET  /api/v1/admin/absensi/rekap?dari=&sampai=&jabatan_id= → rekap per guru
 *   GET  /api/v1/admin/absensi/rekap/{guruId}?dari=&sampai= → rincian 1 guru
 *   GET  /api/v1/admin/absensi/rekap/export/{pdf|excel}?dari=&sampai=&jabatan_id=
 *   GET  /api/v1/admin/absensi/pesan-wa?tanggal=&shift= → teks WhatsApp
 *   GET  /api/v1/admin/absensi/laporan/{excel|pdf}?bulan=YYYY-MM → laporan format sekolah
 *   GET|POST /api/v1/admin/absensi/tarif           → potongan per JP & transport per hari
 *   GET|POST /api/v1/admin/absensi/template-wa     → template pesan WhatsApp
 */
class Absensi extends BaseApiController
{
    // ===================== INPUT HARIAN =====================
    /**
     * GET /api/v1/admin/absensi?tanggal= — data input satu tanggal. Sumber sama
     * dengan web (AbsensiHarian::muat): sesi dikelompokkan per ORANG (guru ganda
     * digabung), `sesi[].guru_id` = guru asli pemilik jadwal (dipakai saat save).
     */
    public function index()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $d       = AbsensiHarian::muat($tanggal);

        $grup    = [];
        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($d['grup'] as $g) {
            $sesi   = [];
            $harian = 'hadir';
            foreach ($g['sesi'] as $s) {
                $harian = AbsensiGuruModel::worst($harian, $s['status']);
                $sesi[] = [
                    // identitas sesi (dikirim balik saat save)
                    'jadwal_id'     => (int) ($s['jadwal_id'] ?? 0),
                    'kelas_id'      => (int) $s['kelas_id'],
                    'jam_id'        => (int) $s['jam_id'],
                    'guru_id'       => (int) $s['guru_id'],
                    'hari_id'       => (int) $s['hari_id'],
                    'mapel_id'      => (int) ($s['mapel_id'] ?? 0),
                    // tampilan
                    'kelas'         => $s['nama_kelas'],
                    'mapel'         => $s['nama_mapel'],
                    'jam_ke'        => (int) $s['jam_ke'],
                    'shift'         => $s['jam_shift'],
                    'waktu_mulai'   => substr((string) $s['waktu_mulai'], 0, 5),
                    'waktu_selesai' => substr((string) $s['waktu_selesai'], 0, 5),
                    // status saat ini
                    'status'        => $s['status'],
                    'jam_masuk'     => $s['jam_masuk'] !== '' ? $s['jam_masuk'] : null,
                    'keterangan'    => $s['keterangan'] !== '' ? $s['keterangan'] : null,
                ];
            }
            if ($d['recorded']) {
                $ringkas[$harian]++;
            }
            $grup[] = [
                'guru_id'       => (int) $g['guru_id'],
                'nama'          => $g['nama'],
                'kode_guru'     => $g['kode'] ?? null,
                'status_harian' => $harian,
                'sesi'          => $sesi,
            ];
        }

        // Jabatan ditempel ke tiap baris kerja (web menampilkannya di bawah nama).
        $kerja = $d['kerja'];
        foreach ($kerja as &$k) {
            $k['jabatan'] = implode(', ', array_column($d['jabatanMap'][$k['guru_id']] ?? [], 'nama'));
        }
        unset($k);

        return $this->ok([
            'tanggal'         => $tanggal,
            'hari_id'         => $d['hariId'],
            'hari_nama'       => $d['namaHari'],
            'hari_aktif'      => $d['hariAktif'],
            'recorded'        => $d['recorded'],
            'sekolah'         => (new SettingModel())->get()['school_name'] ?? '',
            // Shift laporan menurut jam server + guru belum hadir & piket per shift.
            'shift_default'   => AbsensiWa::shiftSekarang(),
            'belum'           => $d['belum'],
            'piket'           => $d['piket'],
            'total'           => $d['total'],
            'total_guru'      => count($grup),
            'ringkas'         => $ringkas,
            'guru'            => $grup,
            'kehadiran_kerja' => $kerja,
            'guru_options'    => $d['guruOptions'],
            'saran_kerja'     => $d['saran'],
        ]);
    }

    /**
     * POST /api/v1/admin/absensi/save
     * Body: {tanggal, rows[], kerja?[], shift?, belum?[]}. `kerja` & `belum`
     * hanya disinkronkan bila key-nya dikirim (aplikasi lama tetap aman).
     * Bila `shift` dikirim, respons memuat `pesan_wa` siap dibagikan.
     */
    public function save()
    {
        $in      = $this->body();
        $tanggal = $this->normalTanggal($in['tanggal'] ?? null);
        $adminId = $this->adminId();
        $shift   = isset($in['shift']) ? AbsensiWa::normalShift((string) $in['shift']) : null;

        AbsensiHarian::simpan($tanggal, [
            'rows'  => is_array($in['rows'] ?? null) ? $in['rows'] : [],
            'kerja' => array_key_exists('kerja', $in) ? (is_array($in['kerja']) ? $in['kerja'] : []) : null,
            'shift' => $shift,
            'belum' => array_key_exists('belum', $in) ? (is_array($in['belum']) ? $in['belum'] : []) : null,
        ], $adminId);
        (new AuditModel())->record('update', 'absensi_guru', null, 'Simpan absensi ' . $tanggal . ($shift ? ' (' . $shift . ')' : '') . ' (via mobile)');

        return $this->ok([
            'tanggal'  => $tanggal,
            'pesan_wa' => $shift ? AbsensiWa::pesan($tanggal, $shift) : null,
        ], 'Absensi tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /**
     * POST /api/v1/admin/absensi/save-kerja
     * Simpan kehadiran kerja (di luar jadwal) satu tanggal. Body: {tanggal, rows:[
     * {guru_id, status, jam_masuk, keterangan}]}. Sinkron ke daftar yang dikirim.
     */
    public function saveKerja()
    {
        $in      = $this->body();
        $tanggal = $this->normalTanggal($in['tanggal'] ?? null);
        $rows    = is_array($in['rows'] ?? null) ? $in['rows'] : [];
        $adminId = $this->adminId();

        (new AbsensiKerjaModel())->syncDate($tanggal, $rows, $adminId);
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_kerja', null, 'Simpan kehadiran kerja ' . $tanggal . ' (via mobile)');

        return $this->ok(['tanggal' => $tanggal], 'Kehadiran kerja tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    public function unrecord()
    {
        $tanggal = $this->normalTanggal($this->body()['tanggal'] ?? null);

        AbsensiHarian::batalkan($tanggal);
        (new AuditModel())->record('delete', 'absensi_hari', null, 'Batal catat absensi ' . $tanggal . ' (via mobile)');

        return $this->ok(['tanggal' => $tanggal], 'Pencatatan absensi tanggal ' . $tanggal . ' dibatalkan.');
    }

    /**
     * GET /api/v1/admin/absensi/template-wa → template aktif, template bawaan,
     * dan daftar isian otomatis (untuk editor di aplikasi).
     */
    public function templateWa()
    {
        $setting = (new SettingModel())->get();

        return $this->ok([
            'template'     => AbsensiWa::template($setting),
            'bawaan'       => AbsensiWa::DEFAULT_TEMPLATE,
            'custom'       => trim((string) ($setting['wa_template_absensi'] ?? '')) !== '',
            'placeholders' => array_map(
                static fn ($k, $v) => ['kode' => $k, 'arti' => $v],
                array_keys(AbsensiWa::PLACEHOLDERS),
                AbsensiWa::PLACEHOLDERS
            ),
        ]);
    }

    /** POST /api/v1/admin/absensi/template-wa  Body: {template} atau {reset:true}. */
    public function templateWaSave()
    {
        $in  = $this->body();
        $tpl = trim(str_replace("\r\n", "\n", (string) ($in['template'] ?? '')));
        if (! empty($in['reset']) || $tpl === '') {
            $tpl = null;
        } elseif (mb_strlen($tpl) > 5000) {
            return $this->invalid(['template' => 'Template terlalu panjang (maks 5000 karakter).']);
        }
        (new SettingModel())->store(['wa_template_absensi' => $tpl]);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah template WA absensi (via mobile)');

        return $this->ok([
            'template' => $tpl ?? AbsensiWa::DEFAULT_TEMPLATE,
            'custom'   => $tpl !== null,
        ], $tpl === null ? 'Template dikembalikan ke bawaan.' : 'Template pesan disimpan.');
    }

    /** GET /api/v1/admin/absensi/pesan-wa?tanggal=&shift= → teks dari data tersimpan. */
    public function pesanWa()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $shift   = AbsensiWa::normalShift($this->request->getGet('shift'));

        return $this->ok(['tanggal' => $tanggal, 'shift' => $shift, 'pesan_wa' => AbsensiWa::pesan($tanggal, $shift)]);
    }

    // ===================== REKAP =====================
    public function rekap()
    {
        [$dari, $sampai] = $this->rentang();
        // Filter jabatan (mis. hanya wakil kepala). Jumlah dihitung SETELAH
        // difilter agar total selalu cocok dengan daftar yang dikirim.
        $jabatanId = (int) $this->request->getGet('jabatan_id');
        $rows      = AbsensiRekap::rekapData($dari, $sampai, $jabatanId);

        return $this->ok([
            'dari'          => $dari,
            'sampai'        => $sampai,
            'hari_tercatat' => count((new AbsensiHariModel())->datesInRange($dari, $sampai)),
            'jabatan_id'    => $jabatanId ?: null,
            'jabatan_nama'  => AbsensiRekap::labelJabatan($jabatanId) ?: null,
            'sum'           => AbsensiRekap::sum($rows),
            'items'         => $rows,
        ]);
    }

    /** Rincian ketidakhadiran satu guru pada rentang. */
    public function rekapGuru($id = 0)
    {
        [$dari, $sampai] = $this->rentang();
        $guru            = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return $this->missing('Guru tidak ditemukan.');
        }

        // Rincian: sesi mengajar (pengecualian) + kehadiran kerja (di luar jadwal).
        $detail = array_map(static fn ($d) => [
            'tanggal'         => $d['tanggal'],
            'status'          => $d['status'],
            'jam_masuk'       => $d['jam_masuk'] ? substr($d['jam_masuk'], 0, 5) : null,
            'keterangan'      => $d['keterangan'] ?? null,
            'kelas'           => $d['nama_kelas'] ?? null,
            'mapel'           => $d['nama_mapel'] ?? null,
            'jam_ke'          => isset($d['jam_ke']) ? (int) $d['jam_ke'] : null,
            'waktu_mulai'     => $d['waktu_mulai'] ? substr((string) $d['waktu_mulai'], 0, 5) : null,
            'waktu_selesai'   => $d['waktu_selesai'] ? substr((string) $d['waktu_selesai'], 0, 5) : null,
            'kehadiran_kerja' => false,
        ], (new AbsensiGuruModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai));
        foreach ((new AbsensiKerjaModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai) as $k) {
            $detail[] = [
                'tanggal'         => $k['tanggal'],
                'status'          => $k['status'],
                'jam_masuk'       => $k['jam_masuk'] ? substr($k['jam_masuk'], 0, 5) : null,
                'keterangan'      => $k['keterangan'],
                'kelas'           => null,
                'mapel'           => null,
                'jam_ke'          => null,
                'waktu_mulai'     => null,
                'waktu_selesai'   => null,
                'kehadiran_kerja' => true,
            ];
        }
        // Belum hadir yang tidak diselesaikan (dihitung tidak hadir).
        foreach ((new AbsensiBelumModel())->detailForGuru(GuruModel::idsOrang((int) $id), $dari, $sampai) as $b) {
            $detail[] = $b + [
                'kelas' => null, 'mapel' => null, 'jam_ke' => null,
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
        $ringkas = ['total' => $total, 'hadir' => max(0, $total - array_sum($cnt))] + $cnt;

        return $this->ok([
            'guru'    => ['id' => (int) $guru['id'], 'kode_guru' => $guru['kode_guru'] ?? null, 'nama' => $guru['nama']],
            'dari'    => $dari,
            'sampai'  => $sampai,
            'ringkas' => $ringkas,
            'detail'  => $detail,
        ]);
    }

    /**
     * GET /api/v1/admin/absensi/rekap/export/{pdf|excel}?dari=&sampai=&jabatan_id=
     * Unduh laporan rekap (dipakai tombol download di aplikasi Android).
     * Isi & filter identik dengan export web (sumber: AbsensiRekap).
     */
    public function rekapExport($format = 'pdf')
    {
        [$dari, $sampai] = $this->rentang();
        $jabatanId       = (int) $this->request->getGet('jabatan_id');
        $rows            = AbsensiRekap::rekapData($dari, $sampai, $jabatanId);
        $setting         = (new SettingModel())->get();
        $label           = AbsensiRekap::labelJabatan($jabatanId);
        $berkas          = AbsensiRekap::namaBerkas($dari, $sampai);

        if ($format === 'excel') {
            $ss = AbsensiRekap::excel($rows, $dari, $sampai, $setting, $label);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $berkas . '.xlsx"');
            header('Cache-Control: max-age=0');
            (new Xlsx($ss))->save('php://output');
            exit;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(AbsensiRekap::pdfHtml($rows, $dari, $sampai, $setting, $label));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($berkas . '.pdf', ['Attachment' => true]);
        exit;
    }

    // ===================== LAPORAN BULANAN (format sekolah) =====================
    /**
     * GET /api/v1/admin/absensi/laporan/{excel|pdf}?bulan=YYYY-MM — isi & format
     * identik dengan web (AbsensiLaporan).
     */
    public function laporan($format = 'pdf')
    {
        [$dari, $sampai, $bulan] = AbsensiLaporan::rentangBulan($this->request->getGet('bulan'));
        $m       = AbsensiLaporan::matriks($dari, $sampai);
        $setting = (new SettingModel())->get();
        $berkas  = AbsensiLaporan::namaBerkas($bulan);

        if ($format === 'excel') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $berkas . '.xlsx"');
            header('Cache-Control: max-age=0');
            (new Xlsx(AbsensiLaporan::excel($m, $bulan, $setting)))->save('php://output');
            exit;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(AbsensiLaporan::pdfHtml($m, $bulan, $setting));
        $dompdf->setPaper(AbsensiLaporan::KERTAS_F4, 'landscape');
        $dompdf->render();
        $dompdf->stream($berkas . '.pdf', ['Attachment' => true]);
        exit;
    }

    /** GET /api/v1/admin/absensi/tarif → {potongan_jp, transport}. */
    public function tarif()
    {
        $s = (new SettingModel())->get();

        return $this->ok([
            'potongan_jp' => (int) ($s['absensi_potongan_jp'] ?? 5000),
            'transport'   => (int) ($s['absensi_transport'] ?? 0),
        ]);
    }

    /** POST /api/v1/admin/absensi/tarif  Body: {potongan_jp, transport}. */
    public function tarifSave()
    {
        $in = $this->body();
        (new SettingModel())->store([
            'absensi_potongan_jp' => max(0, (int) ($in['potongan_jp'] ?? 5000)),
            'absensi_transport'   => max(0, (int) ($in['transport'] ?? 0)),
        ]);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah tarif potongan/transport absensi (via mobile)');

        return $this->tarif();
    }

    // ===================== HELPER =====================
    private function rentang(): array
    {
        $dari   = $this->normalTanggal($this->request->getGet('dari') ?: date('Y-m-01'));
        $sampai = $this->normalTanggal($this->request->getGet('sampai') ?: date('Y-m-t'));
        if (strtotime($sampai) < strtotime($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        return [$dari, $sampai];
    }

    private function normalTanggal(?string $raw): string
    {
        $raw = trim((string) $raw);
        $ts  = $raw !== '' ? strtotime($raw) : false;
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
