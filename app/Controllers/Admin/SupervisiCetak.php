<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HonorCetak;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiCetak as Cetak;
use App\Libraries\SupervisiPenilaian as Penilaian;
use App\Models\AuditModel;

/**
 * Unduhan Supervisi Guru (menu KURIKULUM → Supervisi Guru): Excel Program Supervisi (halaman Jadwal), PDF Lembar Hasil satu kunjungan
 * (lembar penilaian & daftar Penilaian) dan PDF gabungan semua kunjungan selesai satu periode (Penilaian & Rekap). Isi berkas:
 * Libraries\SupervisiCetak. Hak = menu Supervisi Guru level Lihat (GET).
 */
class SupervisiCetak extends BaseController
{
    /** GET program?ta=&semester=&status=&guru_id= — saringan sama dengan halaman Jadwal; tanpa saringan status, jadwal batal tidak ikut. */
    public function program()
    {
        $s    = Supervisi::saringan($this->request->getGet() ?? []);
        $rows = self::rowsProgram((new Supervisi())->daftarJadwal($s), $s['status']);
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh Program Supervisi ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($rows) . ' kunjungan)');

        return $this->xlsx(Cetak::program($rows, $s['ta'], $s['semester']), Cetak::namaBerkasProgram($s['ta'], $s['semester']));
    }

    /** GET penilaian/{id}/pdf — lembar hasil satu kunjungan (belum selesai → bertanda DRAF / lembar kosong untuk diisi tangan). */
    public function lembar($id)
    {
        $l = (new Penilaian())->lembar((int) $id);
        if ($l === null) {
            return redirect()->to(site_url('admin/supervisi/penilaian'))->with('error', 'Jadwal supervisi tidak ditemukan.');
        }
        if ($l['jadwal']['status'] === 'batal') {
            return redirect()->to(site_url('admin/supervisi/penilaian/' . (int) $id))->with('error', 'Kunjungan ini dibatalkan — tidak ada lembar hasil.');
        }
        (new AuditModel())->record('export', 'supervisi_jadwal', (int) $id, 'Unduh lembar hasil supervisi: ' . $l['jadwal']['nama_guru'] . ', ' . $l['jadwal']['tanggal']);

        return $this->pdf(Cetak::pdf(Cetak::lembarHtml([$l])), Cetak::namaBerkasLembar($l['jadwal']));
    }

    /** GET lembar?ta=&semester=&guru_id= — PDF gabungan semua kunjungan SELESAI periode itu (urut tanggal). */
    public function gabungan()
    {
        $s      = Supervisi::saringan($this->request->getGet() ?? []);
        $lembar = (new Penilaian())->lembarPeriode($s['ta'], $s['semester'], $s['guru_id']);
        $balik  = site_url((string) $this->request->getGet('dari') === 'rekap' ? 'admin/supervisi/rekap' : 'admin/supervisi/penilaian')
            . '?' . http_build_query(array_filter(['ta' => $s['ta'], 'semester' => $s['semester'], 'guru_id' => $s['guru_id'] ?: null]));
        if ($lembar === []) {
            return redirect()->to($balik)->with('error', 'Belum ada kunjungan yang selesai dinilai pada ' . Supervisi::labelPeriode($s['ta'], $s['semester']) . ' — tidak ada yang diunduh.');
        }
        if (count($lembar) > Cetak::MAKS_GABUNGAN) {
            return redirect()->to($balik)->with('error', count($lembar) . ' kunjungan terlalu banyak untuk satu PDF (maks ' . Cetak::MAKS_GABUNGAN . '). Saring per guru dulu.');
        }
        set_time_limit(300);
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh lembar hasil supervisi gabungan ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($lembar) . ' kunjungan)');

        return $this->pdf(Cetak::pdf(Cetak::lembarHtml($lembar)), Cetak::namaBerkasGabungan($s['ta'], $s['semester']));
    }

    /**
     * Baris Program Supervisi: tanpa saringan status → jadwal batal disembunyikan (program = rencana yang berjalan).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsProgram(array $rows, string $status): array
    {
        return $status !== '' ? $rows : array_values(array_filter($rows, static fn (array $r): bool => $r['status'] !== 'batal'));
    }

    private function xlsx(\PhpOffice\PhpSpreadsheet\Spreadsheet $ss, string $nama)
    {
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nama . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(HonorCetak::xlsx($ss));
    }

    /** PDF dibuka di tab baru (inline), nama berkas tetap ikut bila disimpan. */
    private function pdf(string $isi, string $nama)
    {
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $nama . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($isi);
    }
}
