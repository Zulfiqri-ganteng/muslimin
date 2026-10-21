<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HonorCetak;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiCetak as Cetak;
use App\Libraries\SupervisiPenilaian as Penilaian;
use App\Models\AuditModel;

/**
 * Rekap Supervisi (menu KURIKULUM → Supervisi Guru): hasil satu semester — satu baris per guru (nilai per instrumen, rata-rata,
 * predikat, tindak lanjut), ringkasan per predikat, guru yang belum disupervisi; Unduh Rekap (Excel) & Lembar Hasil gabungan (PDF).
 * Logika: Libraries\SupervisiPenilaian::rekap(); berkas: Libraries\SupervisiCetak.
 */
class SupervisiRekap extends BaseController
{
    /** GET ?ta=&semester=&predikat= */
    public function index()
    {
        $s = $this->saringan();

        return view('admin/supervisi/rekap', [
            'title'     => 'Rekap Supervisi',
            'saring'    => $s,
            'rekap'     => (new Penilaian())->rekap($s['ta'], $s['semester'], $s['predikat']),
            'pilihanTa' => (new Supervisi())->pilihanTahunAjaran(),
        ]);
    }

    /** GET excel?ta=&semester=&predikat= */
    public function excel()
    {
        $s     = $this->saringan();
        $rekap = (new Penilaian())->rekap($s['ta'], $s['semester'], $s['predikat']);
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh rekap supervisi ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($rekap['baris']) . ' guru)');
        $nama = Cetak::namaBerkasRekap($s['ta'], $s['semester']);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nama . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(HonorCetak::xlsx(Cetak::rekap($rekap)));
    }

    /** @return array{ta: string, semester: string, predikat: string} */
    private function saringan(): array
    {
        $get = $this->request->getGet() ?? [];
        $s   = Supervisi::saringan($get);

        return ['ta' => $s['ta'], 'semester' => $s['semester'], 'predikat' => Penilaian::predikatSah((string) ($get['predikat'] ?? ''))];
    }
}
