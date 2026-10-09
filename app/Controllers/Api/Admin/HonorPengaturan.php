<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\HonorPengaturan as Aturan;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pengaturan Honor Ujian (API Android) — KHUSUS ADMIN. Cermin Admin\HonorPengaturan; aturan & validasi di
 * Libraries\HonorPengaturan (pesan galat sama dengan web). Kontrak: docs/API-HONOR.md.
 *
 *   GET    /api/v1/admin/honor/pengaturan                      komponen, tunjangan panitia per jabatan, tanda tangan
 *   POST   /api/v1/admin/honor/pengaturan/komponen             simpan semua komponen   {komponen:{id:{…}}}
 *   POST   /api/v1/admin/honor/pengaturan/komponen/tambah      {nama,tipe,tarif,satuan}
 *   DELETE /api/v1/admin/honor/pengaturan/komponen/{id}
 *   POST   /api/v1/admin/honor/pengaturan/panitia              {nominal:{jabatan_id:"1.500.000"}}
 *   POST   /api/v1/admin/honor/pengaturan/tanda-tangan         {ketua_nama,bendahara_nama}
 */
class HonorPengaturan extends HonorBase
{
    public function show(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $a = new Aturan();
        $jenis = [];
        foreach (UjianPeriodeModel::JENIS as $j) {
            $jenis[] = ['kode' => $j, 'slug' => UjianPeriodeModel::keSlug($j), 'label' => UjianPeriodeModel::JENIS_LABEL[$j]];
        }

        return $this->ok([
            'komponen' => array_map(static fn (array $k): array => [
                'id' => (int) $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'judul_cetak' => $k['judul_cetak'], 'tipe' => $k['tipe'], 'tarif' => (int) $k['tarif'],
                'satuan' => $k['satuan'], 'sumber' => $k['sumber'], 'berlaku_di' => Aturan::jenisBerlaku($k['berlaku_di']), 'aktif' => (int) $k['aktif'] === 1,
                'urut' => (int) $k['urut'], 'bawaan' => (int) $k['bawaan'] === 1,
            ], $a->komponen()),
            'panitia' => array_map(static fn (array $j): array => ['jabatan_id' => (int) $j['id'], 'kode' => $j['kode'], 'nama' => $j['nama'], 'nominal' => (int) $j['nominal']], $a->panitia()),
            'tanda_tangan' => $a->tandaTangan(),
            'jenis_ujian'  => $jenis,
            'batas'        => ['maks_komponen' => Aturan::MAKS_KOMPONEN, 'maks_rupiah' => Aturan::MAKS_RUPIAH],
        ], 'Pengaturan honor.');
    }

    public function komponen(): ResponseInterface
    {
        return $this->jalankan(fn (Aturan $a, array $in): array => $a->simpanKomponen((array) ($in['komponen'] ?? [])));
    }

    public function tambah(): ResponseInterface
    {
        return $this->jalankan(fn (Aturan $a, array $in): array => $a->tambahKomponen($in), 201);
    }

    public function hapus($id = 0): ResponseInterface
    {
        return $this->jalankan(fn (Aturan $a): array => $a->hapusKomponen((int) $id));
    }

    public function panitia(): ResponseInterface
    {
        return $this->jalankan(fn (Aturan $a, array $in): array => $a->simpanPanitia((array) ($in['nominal'] ?? [])));
    }

    public function tandaTangan(): ResponseInterface
    {
        return $this->jalankan(fn (Aturan $a, array $in): array => $a->simpanTandaTangan((string) ($in['ketua_nama'] ?? ''), (string) ($in['bendahara_nama'] ?? '')));
    }

    /** @param callable(Aturan,array):array $f */
    private function jalankan(callable $f, int $kode = 200): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $in = strtolower($this->request->getMethod()) === 'delete' ? [] : $this->body();

        return $this->hasil($f(new Aturan(), $in), [], $kode);
    }
}
