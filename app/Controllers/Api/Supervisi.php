<?php

namespace App\Controllers\Api;

use App\Controllers\Admin\SupervisiCetak as WebCetak;
use App\Libraries\HakAkses;
use App\Libraries\HonorCetak;
use App\Libraries\Keterlambatan;
use App\Libraries\Supervisi as Sup;
use App\Libraries\SupervisiCetak as Cetak;
use App\Libraries\SupervisiPenilaian as Penilaian;
use App\Models\AuditModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API Supervisi Guru untuk aplikasi Android — cermin halaman web admin/supervisi/jadwal, …/penilaian, …/rekap (S4,
 * docs/DESAIN-SUPERVISI.md §7). Kontrak lengkap: docs/API-SUPERVISI.md. Guna utama: supervisor mengisi lembar penilaian DI KELAS.
 *
 * Controller tipis: SEMUA aturan dari library yang sama dengan web (Libraries\Supervisi, SupervisiPenilaian, SupervisiCetak) → hasil
 * web = hasil aplikasi. Audit Log diberi "(via aplikasi)". Kelola instrumen & butir TIDAK di aplikasi (web saja).
 * Gerbang: ApiAuthFilter (awalan 'supervisi' dari katalog menu, peran harus "boleh aplikasi") + hak per aksi di sini dengan aturan
 * web yang SAMA — HakAkses::boleh() pada alamat web padanannya: melihat & mengunduh = GET admin/supervisi/…, tambah/ubah/hapus jadwal
 * & isi penilaian = POST (level Kelola).
 */
class Supervisi extends BaseApiController
{
    private const WEB = 'admin/supervisi';

    // ================================================================= meta & jadwal

    /** GET supervisi/meta — bahan layar: periode sekarang, pilihan, instrumen aktif + butirnya, skor, predikat, tindak lanjut, batas, hak. */
    public function meta(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal', 'GET', 'membuka Supervisi Guru')) !== null) {
            return $t;
        }
        $sup  = new Sup('aplikasi');
        $kini = Sup::periodeSekarang();
        $spv  = $sup->pilihanSupervisor();

        return $this->ok([
            'hari_ini'      => date('Y-m-d'),
            'periode'       => ['tahun_ajaran' => $kini['tahun_ajaran'], 'semester' => $kini['semester'], 'label' => Sup::labelPeriode($kini['tahun_ajaran'], $kini['semester'])],
            'tahun_ajaran'  => $sup->pilihanTahunAjaran(),
            'semester'      => self::pilihan(Sup::SEMESTER),
            'status'        => self::pilihan(Sup::STATUS),
            'instrumen'     => array_map(fn (array $i): array => [
                'id' => (int) $i['id'], 'kode' => (string) $i['kode'], 'nama' => (string) $i['nama'], 'nama_pendek' => Cetak::namaPendek((string) $i['nama']),
                'jenis' => (string) $i['jenis'], 'keterangan' => $i['keterangan'] ?? null, 'jumlah_butir' => (int) $i['butir_aktif'],
                'butir' => array_map(static fn (array $b): array => ['id' => (int) $b['id'], 'aspek' => (string) $b['aspek'], 'uraian' => (string) $b['uraian']], $sup->daftarButir((int) $i['id'], true)),
            ], $sup->daftarInstrumen(true)),
            'guru'          => $sup->pilihanGuru(),
            'supervisor'    => ['saran' => $spv['saran'], 'lain' => $spv['lain']],
            'mapel'         => $sup->pilihanMapel(),
            'kelas'         => $sup->pilihanKelas(),
            'skor'          => array_merge(array_map(static fn (int $k, string $v): array => ['nilai' => $k, 'label' => $v], array_keys(Penilaian::SKOR), Penilaian::SKOR),
                [['nilai' => 0, 'label' => 'Tidak dinilai (TD)']]),
            'predikat'      => array_map(static fn (int $b, string $p): array => ['predikat' => $p, 'min' => $b, 'rentang' => Cetak::rentangPredikat($b)], array_keys(Penilaian::PREDIKAT), Penilaian::PREDIKAT),
            'tindak_lanjut' => self::pilihan(Penilaian::TINDAK_LANJUT),
            'batas'         => ['catatan_jadwal' => Sup::MAKS_CATATAN, 'catatan_butir' => Penilaian::MAKS_CATATAN, 'teks' => Penilaian::MAKS_TEKS,
                'massal_per_hari' => Sup::MASSAL_PER_HARI, 'massal_maks_per_hari' => Sup::MASSAL_MAKS_PER_HARI, 'massal_maks_hari' => Sup::MASSAL_MAKS_HARI,
                'pdf_gabungan' => Cetak::MAKS_GABUNGAN],
            'hak'           => $this->hak(),
        ], 'Bahan layar supervisi.');
    }

    /** GET supervisi/jadwal?ta=&semester=&status=&guru_id= — kunjungan satu periode (bawaan periode sekarang) + ringkasan. */
    public function daftar(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal', 'GET', 'melihat jadwal supervisi')) !== null) {
            return $t;
        }
        $sup = new Sup('aplikasi');
        $s   = Sup::saringan($this->request->getGet() ?? []);
        $rk  = $sup->ringkasan($s['ta'], $s['semester']);
        $rows = array_map([self::class, 'baris'], $sup->daftarJadwal($s));
        $selesaiNilai = array_values(array_filter(array_map(static fn (array $r): ?float => $r['status'] === 'selesai' ? $r['nilai_akhir'] : null, $rows), static fn ($n): bool => $n !== null));

        return $this->ok([
            'saringan' => $s + ['label' => Sup::labelPeriode($s['ta'], $s['semester'])],
            'ringkas'  => [
                'dijadwalkan' => $rk['dijadwalkan'], 'selesai' => $rk['selesai'], 'batal' => $rk['batal'], 'total' => $rk['total'],
                'rata_rata'   => Penilaian::rataRata($selesaiNilai),
                'guru_belum'  => $rk['guru_belum'],
            ],
            'jumlah'   => count($rows),
            'baris'    => $rows,
        ], count($rows) . ' kunjungan supervisi ' . Sup::labelPeriode($s['ta'], $s['semester']) . '.');
    }

    /** GET supervisi/jadwal/saran?guru_id= — mapel & kelas yang diampu guru itu (Penugasan), JP terbanyak dulu. */
    public function saran(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal', 'GET', 'melihat jadwal supervisi')) !== null) {
            return $t;
        }

        return $this->ok((new Sup('aplikasi'))->saranPengampu((int) $this->request->getGet('guru_id')));
    }

    /** GET supervisi/jadwal/{id} — satu kunjungan + lembar penilaiannya (butir dengan 'kunci' untuk dikirim balik saat menilai). */
    public function detail($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/penilaian/' . (int) $id, 'GET', 'melihat lembar supervisi')) !== null) {
            return $t;
        }
        $d = $this->bentukDetail((int) $id);

        return $d === null ? $this->missing('Jadwal supervisi tidak ditemukan (mungkin sudah dihapus).') : $this->ok($d);
    }

    /** POST supervisi/jadwal — {guru_id*, supervisor_id*, tanggal*, jam?, mapel_id?, kelas_id?, instrumen*: [id], catatan?} → 201 detail. */
    public function tambah(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal', 'POST', 'menambah jadwal supervisi')) !== null) {
            return $t;
        }
        $r = (new Sup('aplikasi'))->simpanJadwal($this->isianJadwal($this->body()), $this->pencatat());
        if (! $r['ok']) {
            return $this->tolakHasil($r);
        }

        return $this->created($this->bentukDetail((int) $r['id']), $r['pesan']);
    }

    /**
     * POST supervisi/jadwal/{id}/ubah — isian sama dengan tambah + batal?: bool (true = dibatalkan; false/tak dikirim = aktif lagi).
     * Kunci yang TIDAK dikirim = tetap seperti sekarang (aplikasi boleh mengirim sebagian, mis. hanya {batal: true}).
     */
    public function ubah($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal/' . (int) $id, 'POST', 'mengubah jadwal supervisi')) !== null) {
            return $t;
        }
        $sup  = new Sup('aplikasi');
        $lama = $sup->cariJadwal((int) $id);
        if ($lama === null) {
            return $this->missing('Jadwal supervisi tidak ditemukan (mungkin sudah dihapus).');
        }
        $in    = $this->body();
        $isian = [
            'guru_id' => $lama['guru_id'], 'supervisor_id' => $lama['supervisor_id'], 'tanggal' => $lama['tanggal'],
            'jam' => $lama['jam'] !== null ? substr((string) $lama['jam'], 0, 5) : '', 'mapel_id' => $lama['mapel_id'], 'kelas_id' => $lama['kelas_id'],
            'instrumen' => $lama['instrumen_id'], 'catatan' => (string) ($lama['catatan'] ?? ''), 'batal' => $lama['status'] === 'batal',
        ];
        foreach (array_keys($isian) as $k) {
            if (array_key_exists($k, $in)) {
                $isian[$k] = $in[$k] ?? '';
            }
        }
        $r = $sup->simpanJadwal($this->isianJadwal($isian), $this->pencatat(), (int) $id);
        if (! $r['ok']) {
            return $this->tolakHasil($r);
        }

        return $this->ok($this->bentukDetail((int) $id), $r['pesan']);
    }

    /** POST supervisi/jadwal/massal — {guru_ids*: [id], supervisor_id*, mulai*, selesai*, per_hari? (3), jam?, instrumen*: [id]}. */
    public function massal(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal/massal', 'POST', 'membuat jadwal supervisi')) !== null) {
            return $t;
        }
        $in = $this->body();
        $r  = (new Sup('aplikasi'))->buatMassal([
            'guru_ids' => self::daftarId($in['guru_ids'] ?? []), 'supervisor_id' => $in['supervisor_id'] ?? 0, 'mulai' => $in['mulai'] ?? '',
            'selesai' => $in['selesai'] ?? '', 'per_hari' => $in['per_hari'] ?? Sup::MASSAL_PER_HARI, 'jam' => $in['jam'] ?? '', 'instrumen' => self::daftarId($in['instrumen'] ?? []),
        ], $this->pencatat());
        if (! $r['ok']) {
            return $this->tolakHasil($r);
        }

        return $this->created(['jumlah' => $r['jumlah'], 'ids' => $r['ids'], 'dilewati' => $r['dilewati']], $r['pesan']);
    }

    /** DELETE supervisi/jadwal/{id} — hanya jadwal yang belum ada penilaiannya (yang sudah dinilai: batalkan lewat ubah). */
    public function hapus($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/jadwal/delete/' . (int) $id, 'POST', 'menghapus jadwal supervisi')) !== null) {
            return $t;
        }
        $r = (new Sup('aplikasi'))->hapusJadwal((int) $id);

        return $r['ok'] ? $this->ok(['id' => (int) $id], $r['pesan']) : $this->tolakHasil($r);
    }

    // ================================================================= penilaian

    /**
     * POST supervisi/jadwal/{id}/nilai — lembar penilaian UTUH:
     * {selesai: bool, nilai: {"<instrumen_id>": {skor: {"<kunci>": 1–4 | 0 (TD) | null}, catatan: {"<kunci>": "…"}, kekuatan, kelemahan,
     * rekomendasi, tindak_lanjut: ["individual", …]}}} — 'kunci' dari GET supervisi/jadwal/{id}. → detail terbaru.
     * 422 = isian kurang/tak sah (data.instrumen_id = tab yang bermasalah), 409 = jadwal batal / sudah selesai lalu dikirim sebagai draf.
     */
    public function nilai($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/penilaian/' . (int) $id, 'POST', 'mengisi penilaian supervisi')) !== null) {
            return $t;
        }
        $in    = $this->body();
        $nilai = is_array($in['nilai'] ?? null) ? $in['nilai'] : [];
        $r     = (new Penilaian('aplikasi'))->simpan((int) $id, $nilai, Sup::ya($in['selesai'] ?? false), $this->pencatat());
        if (! $r['ok']) {
            return ($r['kode'] ?? 0) === 422
                ? $this->failure($r['pesan'], 422, ['errors' => [], 'instrumen_id' => $r['instrumen_id'] ?? null])
                : $this->tolakHasil($r);
        }

        return $this->ok($this->bentukDetail((int) $id), $r['pesan']);
    }

    // ================================================================= rekap & unduhan

    /** GET supervisi/rekap?ta=&semester=&predikat= — satu baris per guru (kunjungan selesai), ringkasan, guru belum disupervisi. */
    public function rekap(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/rekap', 'GET', 'melihat rekap supervisi')) !== null) {
            return $t;
        }
        $s = Sup::saringan($this->request->getGet() ?? []);
        $r = (new Penilaian('aplikasi'))->rekap($s['ta'], $s['semester'], (string) $this->request->getGet('predikat'));
        $r['baris'] = array_map(static function (array $b): array {
            $b['tindak_lanjut_label'] = Penilaian::labelTindakLanjut($b['tindak_lanjut']);

            return $b;
        }, $r['baris']);

        return $this->ok(['label' => Sup::labelPeriode($s['ta'], $s['semester'])] + $r,
            $r['ringkas']['guru_dinilai'] . ' guru sudah dinilai, ' . count($r['belum']) . ' belum — ' . Sup::labelPeriode($s['ta'], $s['semester']) . '.');
    }

    /** GET supervisi/program?ta=&semester=&status=&guru_id= — Excel Program Supervisi (tanpa saringan status: tanpa jadwal batal). */
    public function program()
    {
        if (($t = $this->tolak(self::WEB . '/program', 'GET', 'mengunduh program supervisi')) !== null) {
            return $t;
        }
        $s    = Sup::saringan($this->request->getGet() ?? []);
        $rows = WebCetak::rowsProgram((new Sup('aplikasi'))->daftarJadwal($s), $s['status']);
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh Program Supervisi ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($rows) . ' kunjungan) (via aplikasi)');

        return $this->berkas(HonorCetak::xlsx(Cetak::program($rows, $s['ta'], $s['semester'])), Cetak::namaBerkasProgram($s['ta'], $s['semester']));
    }

    /** GET supervisi/jadwal/{id}/pdf — PDF lembar hasil satu kunjungan (belum selesai → bertanda DRAF). Batal → 409, tidak ada → 404. */
    public function pdf($id)
    {
        if (($t = $this->tolak(self::WEB . '/penilaian/' . (int) $id . '/pdf', 'GET', 'mengunduh lembar supervisi')) !== null) {
            return $t;
        }
        $l = (new Penilaian('aplikasi'))->lembar((int) $id);
        if ($l === null) {
            return $this->missing('Jadwal supervisi tidak ditemukan (mungkin sudah dihapus).');
        }
        if ($l['jadwal']['status'] === 'batal') {
            return $this->failure('Kunjungan ini dibatalkan — tidak ada lembar hasil.', 409);
        }
        (new AuditModel())->record('export', 'supervisi_jadwal', (int) $id, 'Unduh lembar hasil supervisi: ' . $l['jadwal']['nama_guru'] . ', ' . $l['jadwal']['tanggal'] . ' (via aplikasi)');

        return $this->berkas(Cetak::pdf(Cetak::lembarHtml([$l])), Cetak::namaBerkasLembar($l['jadwal']));
    }

    /** GET supervisi/lembar?ta=&semester=&guru_id= — PDF gabungan kunjungan SELESAI periode itu. Kosong → 404, terlalu banyak → 422. */
    public function gabungan()
    {
        if (($t = $this->tolak(self::WEB . '/lembar', 'GET', 'mengunduh lembar supervisi')) !== null) {
            return $t;
        }
        $s      = Sup::saringan($this->request->getGet() ?? []);
        $lembar = (new Penilaian('aplikasi'))->lembarPeriode($s['ta'], $s['semester'], $s['guru_id']);
        if ($lembar === []) {
            return $this->missing('Belum ada kunjungan yang selesai dinilai pada ' . Sup::labelPeriode($s['ta'], $s['semester']) . ' — tidak ada yang diunduh.');
        }
        if (count($lembar) > Cetak::MAKS_GABUNGAN) {
            return $this->failure(count($lembar) . ' kunjungan terlalu banyak untuk satu PDF (maks ' . Cetak::MAKS_GABUNGAN . '). Saring per guru dulu.', 422, ['errors' => []]);
        }
        set_time_limit(300);
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh lembar hasil supervisi gabungan ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($lembar) . ' kunjungan) (via aplikasi)');

        return $this->berkas(Cetak::pdf(Cetak::lembarHtml($lembar)), Cetak::namaBerkasGabungan($s['ta'], $s['semester']));
    }

    /** GET supervisi/rekap/excel?ta=&semester=&predikat= — Excel Rekap (lembar Rekap & Ringkasan). */
    public function rekapExcel()
    {
        if (($t = $this->tolak(self::WEB . '/rekap/excel', 'GET', 'mengunduh rekap supervisi')) !== null) {
            return $t;
        }
        $s = Sup::saringan($this->request->getGet() ?? []);
        $r = (new Penilaian('aplikasi'))->rekap($s['ta'], $s['semester'], (string) $this->request->getGet('predikat'));
        (new AuditModel())->record('export', 'supervisi_jadwal', null, 'Unduh rekap supervisi ' . $s['ta'] . ' ' . $s['semester'] . ' (' . count($r['baris']) . ' guru) (via aplikasi)');

        return $this->berkas(HonorCetak::xlsx(Cetak::rekap($r)), Cetak::namaBerkasRekap($s['ta'], $s['semester']));
    }

    // ================================================================= bentuk respons

    /** Satu baris jadwal untuk aplikasi. */
    public static function baris(array $r): array
    {
        $dinilai = array_map('intval', $r['dinilai'] ?? []);
        $kemajuan = match (true) {
            $r['status'] === 'batal'   => 'batal',
            $r['status'] === 'selesai' => 'selesai',
            (bool) $r['ada_nilai']     => 'draf',
            default                    => 'belum',
        };

        return [
            'id'                 => (int) $r['id'],
            'tahun_ajaran'       => (string) $r['tahun_ajaran'],
            'semester'           => (string) $r['semester'],
            'tanggal'            => (string) $r['tanggal'],
            'hari_tanggal'       => Keterlambatan::hariTanggal((string) $r['tanggal']),
            'jam'                => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : null,
            'guru_id'            => $r['guru_id'] !== null ? (int) $r['guru_id'] : null,
            'nama_guru'          => (string) $r['nama_guru'],
            'nip_guru'           => ($r['nip_guru'] ?? '') !== '' ? (string) $r['nip_guru'] : null,
            'mapel_id'           => $r['mapel_id'] !== null ? (int) $r['mapel_id'] : null,
            'nama_mapel'         => $r['nama_mapel'] ?? null,
            'kelas_id'           => $r['kelas_id'] !== null ? (int) $r['kelas_id'] : null,
            'nama_kelas'         => $r['nama_kelas'] ?? null,
            'supervisor_id'      => $r['supervisor_id'] !== null ? (int) $r['supervisor_id'] : null,
            'nama_supervisor'    => (string) $r['nama_supervisor'],
            'jabatan_supervisor' => $r['jabatan_supervisor'] ?? null,
            'instrumen'          => array_map(static fn (int $id, string $nama): array => ['id' => $id, 'nama' => $nama, 'nama_pendek' => Cetak::namaPendek($nama), 'dinilai' => in_array($id, $dinilai, true)],
                $r['instrumen_id'], $r['instrumen_nama']),
            'status'             => (string) $r['status'],
            'status_label'       => Sup::STATUS[$r['status']] ?? (string) $r['status'],
            'kemajuan'           => $kemajuan,
            'nilai_akhir'        => $r['nilai_akhir'] !== null ? (float) $r['nilai_akhir'] : null,
            'predikat'           => $r['predikat'] ?? null,
            'catatan'            => ($r['catatan'] ?? '') !== '' ? (string) $r['catatan'] : null,
            'ada_nilai'          => (bool) $r['ada_nilai'],
            'bisa_hapus'         => ! $r['ada_nilai'] && $r['status'] !== 'selesai',
            'dicatat_oleh'       => $r['dicatat_oleh'] ?? null,
            'dibuat_at'          => $r['created_at'] ?? null,
            'diubah_at'          => $r['updated_at'] ?? null,
        ];
    }

    /** Detail = baris + 'lembar' (instrumen → butir dengan kunci & skor, ringkasan). null bila jadwal tidak ada. */
    private function bentukDetail(int $id): ?array
    {
        $l = (new Penilaian('aplikasi'))->lembar($id);
        if ($l === null) {
            return null;
        }

        return self::baris($l['jadwal']) + ['lembar' => [
            'instrumen' => array_map(static fn (array $i): array => [
                'id'            => (int) $i['id'],
                'nama'          => (string) $i['nama'],
                'nama_pendek'   => Cetak::namaPendek((string) $i['nama']),
                'jenis'         => (string) $i['jenis'],
                'tersimpan'     => (bool) $i['tersimpan'],
                'butir'         => array_map(static fn (array $b): array => ['kunci' => (string) $b['kunci'], 'butir_id' => $b['butir_id'], 'aspek' => (string) $b['aspek'],
                    'uraian' => (string) $b['uraian'], 'skor' => $b['skor'], 'catatan' => (string) $b['catatan']], $i['butir']),
                'ringkas'       => $i['ringkas'],
                'kekuatan'      => (string) $i['kekuatan'],
                'kelemahan'     => (string) $i['kelemahan'],
                'rekomendasi'   => (string) $i['rekomendasi'],
                'tindak_lanjut' => $i['tindak_lanjut'],
                'dinilai_at'    => $i['dinilai_at'],
                'dinilai_oleh'  => $i['dinilai_oleh'],
            ], $l['instrumen']),
            'ringkas'   => $l['ringkas'],
        ]];
    }

    // ================================================================= bantu

    /** Isian jadwal dari body aplikasi (instrumen boleh larik angka/teks). */
    private function isianJadwal(array $in): array
    {
        return [
            'guru_id'       => $in['guru_id'] ?? 0,
            'supervisor_id' => $in['supervisor_id'] ?? 0,
            'tanggal'       => (string) ($in['tanggal'] ?? ''),
            'jam'           => (string) ($in['jam'] ?? ''),
            'mapel_id'      => $in['mapel_id'] ?? 0,
            'kelas_id'      => $in['kelas_id'] ?? 0,
            'instrumen'     => self::daftarId($in['instrumen'] ?? []),
            'catatan'       => (string) ($in['catatan'] ?? ''),
            'batal'         => Sup::ya($in['batal'] ?? false) ? '1' : '',
        ];
    }

    /** @return list<string> */
    private static function daftarId(mixed $v): array
    {
        return array_map('strval', Sup::idDariJson(is_array($v) ? $v : explode(',', (string) $v)));
    }

    /** @param array<string, string> $peta @return list<array{kode: string, label: string}> */
    private static function pilihan(array $peta): array
    {
        return array_map(static fn (string $k, string $v): array => ['kode' => $k, 'label' => $v], array_keys($peta), $peta);
    }

    private function berkas(string $isi, string $nama)
    {
        return $this->response->download($nama, $isi)->setFileName($nama);
    }

    /** @return array<string, bool> hak peran ini (aplikasi menyembunyikan tombol yang tidak boleh) */
    private function hak(): array
    {
        return [
            'lihat'       => $this->boleh(self::WEB . '/jadwal'),
            'tambah'      => $this->boleh(self::WEB . '/jadwal', 'POST'),
            'ubah'        => $this->boleh(self::WEB . '/jadwal/0', 'POST'),
            'massal'      => $this->boleh(self::WEB . '/jadwal/massal', 'POST'),
            'hapus'       => $this->boleh(self::WEB . '/jadwal/delete/0', 'POST'),
            'nilai'       => $this->boleh(self::WEB . '/penilaian/0', 'POST'),
            'rekap'       => $this->boleh(self::WEB . '/rekap'),
            'unduh'       => $this->boleh(self::WEB . '/program'),
        ];
    }

    private function boleh(string $alamatWeb, string $metode = 'GET'): bool
    {
        return HakAkses::boleh((string) ($this->admin()['role'] ?? ''), $alamatWeb, $metode);
    }

    private function tolak(string $alamatWeb, string $metode, string $aksi): ?ResponseInterface
    {
        return $this->boleh($alamatWeb, $metode) ? null : $this->forbidden('Akun ' . HakAkses::label((string) ($this->admin()['role'] ?? ''))
            . ' tidak punya hak ' . $aksi . '. Admin bisa mengaturnya di Sistem → Hak Akses.');
    }

    /** @return array{id: ?int, nama: string} */
    private function pencatat(): array
    {
        $a = (array) $this->admin();

        return ['id' => ((int) ($a['id'] ?? 0)) ?: null, 'nama' => (string) ($a['full_name'] ?? '')];
    }
}
