<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UjianReport;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Menu Ujian (API) — periode & rekap.
 * Cermin App\Controllers\Admin\Ujian (tab Periode & Rekap) + LaporanUjian.
 *
 * Rute:
 *   GET  /api/v1/admin/ujian                     ringkasan 4 gelombang (read-only)
 *   GET  /api/v1/admin/ujian/{slug}?tp=          detail satu jenis + ringkasan
 *   POST /api/v1/admin/ujian/{slug}/periode      simpan pengaturan periode
 *   GET  /api/v1/admin/ujian/{slug}/rekap?tp=    agregat lengkap satu periode
 *
 * {slug} = asts1 | asas | asts2 | asat
 */
class Ujian extends BaseApiController
{
    protected UjianPeriodeModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new UjianPeriodeModel();
        $this->audit = new AuditModel();
    }

    /** Ringkasan keempat gelombang pada tahun pelajaran berjalan. */
    public function index(): ResponseInterface
    {
        $tahun = $this->model->tahunBerjalan();

        return $this->ok(UjianReport::dashboard($tahun), 'Ringkasan ujian.');
    }

    /** Detail satu jenis ujian: periode, ringkasan angka, dan riwayat tahun. */
    public function show(string $slug = ''): ResponseInterface
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $periode = $this->model->untukTahun($jenis, $this->request->getGet('tp'));
        if ($periode === null) {
            return $this->missing('Periode tahun pelajaran itu belum pernah dibuat.');
        }

        $agregat = UjianReport::hitung((int) $periode['id']);

        return $this->ok([
            'periode'  => $this->transform($periode),
            'ringkas'  => [
                'jadwal'     => $agregat['jadwalTotal'],
                'pengawas'   => $agregat['pengawasTotal'],
                'tidak_hadir'=> $agregat['takHadirTotal'],
                'status'     => $agregat['perStatus'],
                'alasan'     => $agregat['perAlasan'],
            ],
            'riwayat'  => array_map(fn ($r) => [
                'tahun_ajaran' => $r['tahun_ajaran'],
                'label'        => $this->model->label($r),
                'berjalan'     => $r['tahun_ajaran'] === $this->model->tahunBerjalan(),
            ], $this->model->riwayat($jenis)),
            'berjalan' => $this->model->tahunBerjalan(),
        ]);
    }

    /** Simpan tanggal pelaksanaan, tanggal susulan, dan status periode. */
    public function simpanPeriode(string $slug = ''): ResponseInterface
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $in      = $this->body();
        $id      = (int) ($in['id'] ?? 0);
        $periode = $id > 0 ? $this->model->find($id) : null;

        // Jangan percaya id dari klien: pastikan miliknya jenis ini.
        if (! $periode || $periode['jenis'] !== $jenis) {
            return $this->missing('Periode ujian tidak ditemukan.');
        }

        $status = (string) ($in['status'] ?? '');
        $data   = [
            'nama'            => $this->teks($in['nama'] ?? null, 100),
            'tanggal_mulai'   => $this->tanggal($in['tanggal_mulai'] ?? null),
            'tanggal_selesai' => $this->tanggal($in['tanggal_selesai'] ?? null),
            'susulan_mulai'   => $this->tanggal($in['susulan_mulai'] ?? null),
            'susulan_selesai' => $this->tanggal($in['susulan_selesai'] ?? null),
            'status'          => in_array($status, UjianPeriodeModel::STATUS, true) ? $status : $periode['status'],
            'keterangan'      => $this->teks($in['keterangan'] ?? null, 255),
        ];

        $salah = [];
        if ($data['tanggal_mulai'] && $data['tanggal_selesai'] && $data['tanggal_selesai'] < $data['tanggal_mulai']) {
            $salah['tanggal_selesai'] = 'Tanggal selesai ujian tidak boleh lebih awal dari tanggal mulai.';
        }
        if ($data['susulan_mulai'] && $data['susulan_selesai'] && $data['susulan_selesai'] < $data['susulan_mulai']) {
            $salah['susulan_selesai'] = 'Tanggal selesai susulan tidak boleh lebih awal dari tanggal mulai susulan.';
        }
        if ($salah !== []) {
            return $this->invalid($salah);
        }

        if (! $this->model->update($periode['id'], $data)) {
            return $this->invalid($this->model->errors() ?: ['periode' => 'Periode gagal disimpan.']);
        }

        master_data_changed('ujian_periode');
        $this->audit->record('update', 'ujian_periode', (int) $periode['id'], 'Ubah pengaturan periode ujian (via mobile)');

        return $this->ok($this->transform($this->model->find($periode['id'])), 'Pengaturan periode disimpan.');
    }

    /** Agregat lengkap satu periode (sumber angka yang sama dengan web). */
    public function rekap(string $slug = ''): ResponseInterface
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $periode = $this->model->untukTahun($jenis, $this->request->getGet('tp'));
        if ($periode === null) {
            return $this->missing('Periode ujian tidak ditemukan.');
        }

        return $this->ok([
            'periode' => $this->transform($periode),
        ] + UjianReport::hitung((int) $periode['id']));
    }

    // ================= Util =================

    private function transform(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'jenis'           => $r['jenis'],
            'slug'            => UjianPeriodeModel::keSlug($r['jenis']),
            'label'           => $this->model->label($r),
            'nama_panjang'    => UjianPeriodeModel::JENIS_PANJANG[$r['jenis']] ?? null,
            'tahun_ajaran'    => $r['tahun_ajaran'],
            'semester'        => $r['semester'],
            'tanggal_mulai'   => $r['tanggal_mulai'],
            'tanggal_selesai' => $r['tanggal_selesai'],
            'susulan_mulai'   => $r['susulan_mulai'],
            'susulan_selesai' => $r['susulan_selesai'],
            'status'          => $r['status'],
            'keterangan'      => $r['keterangan'],
        ];
    }

    /** Teks dipangkas; kosong jadi null. */
    private function teks($v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /** Tanggal Y-m-d; kosong atau tidak valid jadi null. */
    private function tanggal($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);

        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
