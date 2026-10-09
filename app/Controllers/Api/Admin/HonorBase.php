<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\HonorDokumen;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Dasar API Honor Ujian (Android): pagar Admin + penentuan periode + pembantu respons.
 *
 * KHUSUS ADMIN (data gaji). Penyaring rute `apiauth` sudah menolak peran lain (Operator/Waka Hubin hanya boleh
 * awalan `pkl`); `siap()` memeriksa ulang sebagai pagar kedua.
 *
 * Periode ditentukan oleh `{slug}` + `tp` (tahun pelajaran, "2026/2027"; kosong = tahun berjalan) — di query
 * (GET/DELETE) atau di body (POST). Klien SEBAIKNYA selalu mengirim `tp` yang didapat dari GET honor, supaya
 * perubahan tidak jatuh ke periode lain bila tahun berjalan bergeser. Bila body memuat `periode_id`, nilainya
 * harus cocok dengan periode hasil penentuan itu (409 bila tidak).
 */
abstract class HonorBase extends BaseApiController
{
    /** @var array<string,mixed>|null */
    protected ?array $periode = null;
    /** @var array<string,mixed> */
    protected array $in = [];

    /** Pagar Admin + jenis + periode. Mengembalikan respons galat, atau null bila lolos (mengisi $periode & $in). */
    protected function siap(string $slug): ?ResponseInterface
    {
        if ((string) ($this->admin()['role'] ?? '') !== 'admin') {
            return $this->forbidden('Honor hanya bisa dikelola Admin.');
        }
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }
        $this->in = strtolower($this->request->getMethod()) === 'get' ? [] : $this->body();
        $tp = $this->request->getGet('tp') ?? ($this->in['tp'] ?? null);
        $p  = (new UjianPeriodeModel())->untukTahun($jenis, is_string($tp) && trim($tp) !== '' ? trim($tp) : null);
        if ($p === null) {
            return $this->missing('Periode tahun pelajaran itu belum pernah dibuat.');
        }
        if (isset($this->in['periode_id']) && (int) $this->in['periode_id'] !== (int) $p['id']) {
            return $this->failure('Periode yang dikirim tidak cocok dengan jenis ujian / tahun pelajaran di alamat.', 409);
        }
        $this->periode = $p;

        return null;
    }

    /** Pagar Admin saja (pengaturan tidak terikat periode). */
    protected function sebagaiAdmin(): ?ResponseInterface
    {
        return (string) ($this->admin()['role'] ?? '') === 'admin' ? null : $this->forbidden('Honor hanya bisa dikelola Admin.');
    }

    protected function dokumenAda(): ?array
    {
        return (new HonorDokumen())->dokumenPeriode((int) $this->periode['id']);
    }

    protected function belumAda(): ResponseInterface
    {
        return $this->missing('Honor belum dibuat untuk ujian ini. Buat dulu (POST …/honor).');
    }

    /** Hasil pustaka ['ok','pesan',…] → respons: ditolak aturan = 422 dengan pesan siap tampil. */
    protected function hasil(array $h, array $data = [], int $kode = 200): ResponseInterface
    {
        if (! ($h['ok'] ?? false)) {
            return $this->failure((string) ($h['pesan'] ?? 'Gagal.'), 422);
        }

        return $kode === 201 ? $this->created($data, (string) $h['pesan']) : $this->ok($data, (string) $h['pesan']);
    }

    /** Ringkasan kecil setelah perubahan, supaya klien tak perlu mengambil ulang seluruh honor. */
    protected function ringkas(int $dokumenId): array
    {
        $m = (new HonorDokumen())->muat($dokumenId);
        if ($m === null) {
            return [];
        }

        return ['status' => $m['dokumen']['status'], 'jumlah_penerima' => count($m['baris']), 'total' => (int) $m['total']];
    }

    /**
     * Honor lengkap → bentuk JSON API. Peta berkunci id komponen (string) dijadikan OBJEK JSON.
     *
     * @param array<string,mixed> $m hasil HonorDokumen::muat()
     */
    protected function muatJson(array $m): array
    {
        $dok = $m['dokumen'];
        $komponen = [];
        foreach ($m['komponen'] as $k) {
            $komponen[] = [
                'id' => (int) $k['id'], 'komponen_id' => (int) $k['komponen_id'], 'kode' => $k['kode'], 'nama' => $k['nama'],
                'judul_kolom' => \App\Libraries\HonorCetak::judulKolom($k), 'tipe' => $k['tipe'], 'tarif' => (int) $k['tarif'],
                'satuan' => $k['satuan'], 'sumber' => $k['sumber'], 'urut' => (int) $k['urut'],
            ];
        }
        $baris = [];
        foreach ($m['baris'] as $i => $b) {
            $nilai = [];
            $rupiah = [];
            foreach ($m['komponen'] as $k) {
                $kid = (int) $k['id'];
                $s = $b['nilai'][$kid] ?? ['nilai' => 0, 'otomatis' => null, 'manual' => false];
                $nilai[(string) $kid] = ['nilai' => (int) $s['nilai'], 'otomatis' => $s['otomatis'], 'manual' => (bool) $s['manual']];
                $rupiah[(string) $kid] = (int) ($b['per'][$kid] ?? 0);
            }
            $baris[] = [
                'id' => (int) $b['id'], 'no' => $i + 1, 'guru_id' => $b['guru_id'] !== null ? (int) $b['guru_id'] : null, 'nama' => $b['nama'],
                'jabatan' => $b['jabatan'], 'catatan' => $b['catatan'], 'nilai' => (object) $nilai, 'rupiah' => (object) $rupiah, 'total' => (int) $b['total'],
            ];
        }
        $peta = static fn (array $a): object => (object) array_combine(array_map('strval', array_keys($a)), array_map('intval', array_values($a)));

        return [
            'dokumen' => [
                'id' => (int) $dok['id'], 'status' => $dok['status'], 'status_label' => HonorDokumen::STATUS[$dok['status']] ?? $dok['status'],
                'judul' => $dok['judul'], 'tempat' => $dok['tempat'], 'tanggal' => $dok['tanggal'], 'ketua_nama' => $dok['ketua_nama'],
                'bendahara_nama' => $dok['bendahara_nama'], 'kepsek_nama' => $dok['kepsek_nama'], 'dibuat_oleh' => $dok['dibuat_oleh'],
                'dikunci_at' => $dok['dikunci_at'], 'dikunci_oleh' => $dok['dikunci_oleh'],
                'boleh_ubah' => $dok['status'] !== 'dikunci',
            ],
            'komponen'       => $komponen,
            'baris'          => $baris,
            'total_komponen' => $peta($m['total_komponen']),
            'total_jumlah'   => $peta($m['total_jumlah']),
            'total'          => (int) $m['total'],
            'utuh'           => HonorDokumen::utuh($m),
        ];
    }
}
