<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\HonorDokumen;
use App\Libraries\HonorHitung;
use App\Libraries\HonorPembuatSoal;
use App\Libraries\HonorPengaturan;
use App\Libraries\HonorPeriksa;
use App\Models\GuruModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Honor Ujian (API Android) — KHUSUS ADMIN. Cermin tab "Honor" di web (Admin\UjianHonor); seluruh aturan hitung dan
 * validasi ada di Libraries\HonorDokumen / HonorHitung / HonorPembuatSoal, jadi angka dan pesan SAMA dengan web.
 * Kontrak lengkap + contoh: docs/API-HONOR.md. Impor Excel lama TIDAK tersedia di API (hanya web).
 *
 * Semua rute di bawah `admin/ujian/{slug}` ({slug} = asts1|asas|asts2|asat) dan menerima/mengirim `tp`:
 *   GET    …/honor                       honor lengkap (atau pratinjau bila belum dibuat)
 *   GET    …/honor/calon                 calon penerima (guru yang belum masuk)
 *   POST   …/honor                       buat honor            {salin_dari?}
 *   DELETE …/honor                       hapus honor
 *   POST   …/honor/dokumen               data surat            {judul,tempat,tanggal,ketua_nama,bendahara_nama,kepsek_nama}
 *   POST   …/honor/penerima              tambah penerima       {guru_ids:[…]}
 *   POST   …/honor/penerima/semua        tambah semua guru yang belum ada
 *   DELETE …/honor/baris/{id}            hapus penerima
 *   POST   …/honor/baris/{id}/jabatan    ubah label jabatan    {jabatan}
 *   POST   …/honor/baris/{id}/pindah     pindah nomor urut     {posisi}
 *   POST   …/honor/nilai                 simpan satu isian     {baris,komponen,nilai}
 *   POST   …/honor/sinkron               perbarui tarif dari pengaturan
 *   POST   …/honor/hitung                hitung otomatis       {sumber:[koreksi|rapot|soal], timpa?}
 *   POST   …/honor/status                draf|final|dikunci    {ke, alasan?}  (buka kunci wajib alasan)
 *   GET    …/pembuat-soal                jadwal + pembuat soalnya
 *   POST   …/pembuat-soal                tugaskan              {jadwal_id,guru_id}
 *   DELETE …/pembuat-soal/{id}           cabut penugasan
 */
class Honor extends HonorBase
{
    private HonorDokumen $lib;

    public function __construct()
    {
        $this->lib = new HonorDokumen();
    }

    /** Honor lengkap + pemeriksaan + bahan layar. Belum dibuat → ada=false beserta pratinjau komponen & honor lain. */
    public function show(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $p      = $this->periode;
        $header = [
            'periode' => ['id' => (int) $p['id'], 'jenis' => $p['jenis'], 'slug' => UjianPeriodeModel::keSlug($p['jenis']), 'tahun_ajaran' => $p['tahun_ajaran'], 'label' => (new UjianPeriodeModel())->label($p)],
        ];
        $dok = $this->dokumenAda();
        if ($dok === null) {
            $pratinjau = array_map(static fn (array $k): array => [
                'kode' => $k['kode'], 'nama' => $k['nama'], 'judul_kolom' => \App\Libraries\HonorCetak::judulKolom($k), 'tipe' => $k['tipe'], 'tarif' => (int) $k['tarif'], 'satuan' => $k['satuan'],
            ], (new HonorPengaturan())->komponen(true, (string) $p['jenis']));
            $lain = array_map(static fn (array $d): array => [
                'id' => (int) $d['id'], 'label' => (UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran'], 'jumlah_penerima' => (int) $d['jml'],
            ], $this->lib->dokumenLain((int) $p['id']));

            return $this->ok($header + ['ada' => false, 'honor' => null, 'pratinjau_komponen' => $pratinjau, 'honor_lain' => $lain], 'Honor belum dibuat.');
        }
        $m     = $this->lib->muat((int) $dok['id']);
        $hitung = new HonorHitung();

        return $this->ok($header + [
            'ada'               => true,
            'honor'             => $this->muatJson($m),
            'pemeriksaan'       => (new HonorPeriksa())->periksa($m, $p),
            'gambaran_hitung'   => $hitung->gambaran((int) $p['id']),
            'petunjuk_pengawas' => (object) array_map('intval', $hitung->pengawasPetunjuk((int) $p['id'])),
        ], 'Honor ' . (new UjianPeriodeModel())->label($p));
    }

    public function calon(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        if ($dok === null) {
            return $this->belumAda();
        }

        return $this->ok(array_map(static fn (array $g): array => [
            'id' => (int) $g['id'], 'kode_guru' => $g['kode_guru'], 'nama' => $g['nama'], 'jabatan' => $g['jabatan'], 'bukan_pengajar' => (int) $g['bukan_pengajar'] === 1,
        ], $this->lib->calonPenerima((int) $dok['id'])), 'Calon penerima.');
    }

    public function store(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $h = $this->lib->buat($this->periode, (int) ($this->in['salin_dari'] ?? 0));

        return $this->hasil($h, ($h['ok'] ?? false) ? ['id' => (int) $h['id']] + $this->ringkas((int) $h['id']) : [], 201);
    }

    public function destroy(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();

        return $dok === null ? $this->belumAda() : $this->hasil($this->lib->hapusDokumen((int) $dok['id']));
    }

    public function dokumen(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->simpanDokumen($id, $this->in));
    }

    public function penerima(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->tambahPenerima($id, array_map('intval', (array) ($this->in['guru_ids'] ?? []))), true);
    }

    public function penerimaSemua(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, function (int $id): array {
            $ids = array_map(static fn (array $g): int => (int) $g['id'], $this->lib->calonPenerima($id));

            return $ids === [] ? ['ok' => true, 'pesan' => 'Semua guru sudah ada di daftar.', 'jumlah' => 0] : $this->lib->tambahPenerima($id, $ids);
        }, true);
    }

    public function hapusBaris(string $slug = '', $barisId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->hapusBaris($id, (int) $barisId), true);
    }

    public function jabatan(string $slug = '', $barisId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->ubahBaris($id, (int) $barisId, (string) ($this->in['jabatan'] ?? '')));
    }

    public function pindah(string $slug = '', $barisId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->pindahKe($id, (int) $barisId, (int) ($this->in['posisi'] ?? 0)), true);
    }

    /** Simpan satu isian; membalas angka resmi dari server (rupiah sel, total baris, total kolom, total semua). */
    public function nilai(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->simpanNilai($id, (int) ($this->in['baris'] ?? 0), (int) ($this->in['komponen'] ?? 0), $this->in['nilai'] ?? ''));
    }

    public function sinkron(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->sinkronKomponen($id), true);
    }

    public function hitung(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => (new HonorHitung())->terapkan($id, array_map('strval', (array) ($this->in['sumber'] ?? [])), filter_var($this->in['timpa'] ?? false, FILTER_VALIDATE_BOOLEAN)), true);
    }

    public function status(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (int $id): array => $this->lib->ubahStatus($id, (string) ($this->in['ke'] ?? ''), isset($this->in['alasan']) ? (string) $this->in['alasan'] : null), true);
    }

    // ----------------------------------------------------------------- pembuat soal

    public function pembuatSoal(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $d     = (new HonorPembuatSoal())->daftar((int) $this->periode['id']);
        $jadwal = [];
        foreach ($d['jadwal'] as $j) {
            $jadwal[] = [
                'id' => (int) $j['id'], 'tanggal' => $j['tanggal'], 'jam_mulai' => $j['jam_mulai'] ? substr((string) $j['jam_mulai'], 0, 5) : null,
                'jam_selesai' => $j['jam_selesai'] ? substr((string) $j['jam_selesai'], 0, 5) : null, 'tingkat' => $j['tingkat'], 'jurusan' => $j['jurusan_kode'],
                'shift' => $j['shift'], 'ruang' => $j['ruang'], 'mapel' => $j['nama_mapel'],
                'pembuat' => array_map(static fn (array $t): array => ['id' => (int) $t['id'], 'guru_id' => (int) $t['guru_id'], 'nama' => $t['nama']], $d['tugas'][(int) $j['id']] ?? []),
            ];
        }
        $guru = [];
        foreach ((new GuruModel())->options() as $id => $label) {
            $guru[] = ['id' => (int) $id, 'label' => $label];
        }

        return $this->ok(['periode_id' => (int) $this->periode['id'], 'jadwal' => $jadwal, 'guru' => $guru], 'Pembuat soal per jadwal.');
    }

    public function pembuatSoalTambah(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $h = (new HonorPembuatSoal())->tambah($this->periode, (int) ($this->in['jadwal_id'] ?? 0), (int) ($this->in['guru_id'] ?? 0));

        return $this->hasil($h, ($h['ok'] ?? false) ? ['id' => (int) $h['id']] : [], 201);
    }

    public function pembuatSoalCabut(string $slug = '', $id = 0): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }

        return $this->hasil((new HonorPembuatSoal())->cabut($this->periode, (int) $id));
    }

    // -----------------------------------------------------------------

    /**
     * Pola umum aksi pada honor yang sudah ada: pagar, ambil dokumen, jalankan, balas pesan + (opsional) ringkasan baru.
     *
     * @param callable(int):array $jalankan menerima id dokumen, mengembalikan hasil pustaka
     */
    private function aksi(string $slug, callable $jalankan, bool $denganRingkas = false): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        if ($dok === null) {
            return $this->belumAda();
        }
        $h = $jalankan((int) $dok['id']);
        if (! ($h['ok'] ?? false)) {
            return $this->failure((string) ($h['pesan'] ?? 'Gagal.'), 422);
        }
        $data = array_diff_key($h, ['ok' => 1, 'pesan' => 1]);
        if ($denganRingkas) {
            $data += ['honor' => $this->ringkas((int) $dok['id'])];
        }

        return $this->ok($data, (string) $h['pesan']);
    }
}
