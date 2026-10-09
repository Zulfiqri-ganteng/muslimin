<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Skbm as DataSkbm;
use App\Libraries\SkbmCetak;
use App\Models\AuditModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SKBM — SK Pembagian Tugas Mengajar per tahun ajaran (API Android) — KHUSUS ADMIN (sumber ceklis Koreksi honor = data gaji).
 * Cermin menu Guru → SKBM di web (Admin\Skbm); seluruh aturan & angka ada di Libraries\Skbm yang SAMA dengan web.
 * Kontrak lengkap + contoh: docs/API-SKBM-KOREKSI.md. Impor Excel TIDAK tersedia di API (hanya web).
 *
 * Semua di bawah `/api/v1/admin/skbm`. `tahun` = tahun ajaran "2026/2027": di query (GET/DELETE) atau body (POST).
 *   GET    skbm?tahun=              matriks lengkap satu tahun (kosong = tahun bawaan) + ringkasan + pilihan tahun
 *   GET    skbm/opsi                guru (Master Guru) & nama mapel untuk dialog tambah
 *   GET    skbm/bandingkan?tahun=   selisih dengan Penugasan (pengampu)
 *   GET    skbm/xlsx?tahun=         Excel SKBM (BERKAS BINER; template kosong bila tahun itu belum diisi; ?unduh=1 = attachment)
 *   POST   skbm/sel                 {tahun,mapel,kelas,aktif,jp?}   nyalakan/matikan sel & isi JP
 *   POST   skbm/mapel               {tahun,guru,nama}               tambah baris mapel (201)
 *   POST   skbm/mapel/{id}          {nama}                          ganti nama mapel
 *   DELETE skbm/mapel/{id}                                          hapus baris mapel
 *   DELETE skbm/guru/{guruId}?tahun=                                keluarkan guru dari SKBM tahun itu
 *   POST   skbm/salin               {dari,ke,ganti?}                salin satu tahun ke tahun lain
 *   POST   skbm/kosongkan           {tahun}                         buang seluruh SKBM satu tahun
 */
class Skbm extends BaseApiController
{
    private DataSkbm $lib;
    /** @var array<string,mixed> */
    private array $in = [];

    public function __construct()
    {
        $this->lib = new DataSkbm();
    }

    // ----------------------------------------------------------------- pagar & pembantu

    /** Pagar Admin + tabel SKBM sudah ada (kode bisa terpasang sebelum migrasinya). Mengisi $in. */
    private function siap(): ?ResponseInterface
    {
        if ((string) ($this->admin()['role'] ?? '') !== 'admin') {
            return $this->forbidden('SKBM hanya bisa dikelola Admin.');
        }
        $db = db_connect();
        if (! $db->tableExists('skbm_mapel') || ! $db->tableExists('skbm_sel')) {
            return $this->failure('Fitur SKBM belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).', 503);
        }
        $this->in = in_array(strtolower($this->request->getMethod()), ['get', 'delete'], true) ? [] : $this->body();

        return null;
    }

    /** Nilai `$kunci` dari query atau body sebagai teks rapi; null bila tidak dikirim. */
    private function teks(string $kunci): ?string
    {
        $v = $this->request->getGet($kunci) ?? ($this->in[$kunci] ?? null);

        return is_scalar($v) ? trim((string) $v) : null;
    }

    /** Tahun ajaran WAJIB sah (aksi pengubah). Mengembalikan teksnya, atau respons galat. */
    private function tahunWajib(string $kunci = 'tahun'): string|ResponseInterface
    {
        $t = $this->teks($kunci) ?? '';

        return DataSkbm::tahunValid($t) ? $t : $this->failure('Tahun ajaran tidak sah (contoh: 2026/2027).', 422);
    }

    /** Peta berkunci id (int) → OBJEK JSON (kosong pun tetap objek, bukan list). */
    private function peta(array $a): object
    {
        return (object) array_combine(array_map('strval', array_keys($a)), array_values($a));
    }

    /**
     * Hasil pustaka → data respons: buang ok/pesan, ganti nama `per_baris` menjadi `per_guru`, dan jadikan semua peta berkunci id sebagai OBJEK.
     *
     * @param array<string,mixed> $h
     *
     * @return array<string,mixed>
     */
    private function rapikan(array $h): array
    {
        $h = array_diff_key($h, ['ok' => 1, 'pesan' => 1]);
        if (isset($h['per_baris'])) {
            $h['per_guru'] = $h['per_baris'];
            unset($h['per_baris']);
        }
        foreach (['per_mapel', 'per_guru', 'per_guru_jml', 'per_kelas'] as $k) {
            if (isset($h[$k]) && is_array($h[$k])) {
                $h[$k] = $this->peta($h[$k]);
            }
        }

        return $h;
    }

    /** Hasil pustaka → respons: ditolak aturan = 422 dengan pesan siap tampil. $tahun ≠ null → ringkasan angka tahun itu ikut dikirim. */
    private function hasil(array $h, ?string $tahun = null, int $kode = 200): ResponseInterface
    {
        if (! ($h['ok'] ?? false)) {
            return $this->failure((string) ($h['pesan'] ?? 'Gagal.'), 422);
        }
        if ($tahun !== null) {
            $h += $this->lib->ringkas($tahun);
        }
        $data = $this->rapikan($h);

        return $kode === 201 ? $this->created($data, (string) $h['pesan']) : $this->ok($data, (string) $h['pesan']);
    }

    // ----------------------------------------------------------------- baca

    /** Matriks SKBM lengkap satu tahun ajaran. */
    public function show(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->teks('tahun');
        if ($t === null || $t === '') {
            $t = $this->lib->tahunBawaan();
        } elseif (! DataSkbm::tahunValid($t)) {
            return $this->failure('Tahun ajaran tidak sah (contoh: 2026/2027).', 422);
        }
        $m = $this->lib->muat($t);

        $kelas = array_map(static fn (array $k): array => [
            'id' => (int) $k['id'], 'nama' => $k['nama'], 'label' => $k['label'], 'tingkat' => $k['tingkat'], 'shift' => $k['shift'],
            'grup' => $k['grup'], 'jml' => (int) $k['jml'], 'jp' => (int) $k['jp'],
        ], $m['kelas']);
        $guru = [];
        foreach ($m['guru'] as $g) {
            $mapel = [];
            foreach ($g['mapel'] as $x) {
                $mapel[] = ['id' => $x['id'], 'nama' => $x['nama'], 'mapel_id' => $x['mapel_id'], 'kode' => $x['kode'], 'total_jp' => $x['total_jp'], 'jml' => $x['jml'], 'sel' => $this->peta($x['sel'])];
            }
            $guru[] = ['guru_id' => $g['guru_id'], 'no' => $g['no'], 'nama' => $g['nama'], 'total_jp' => $g['total_jp'], 'jml' => $g['jml'], 'mapel' => $mapel];
        }
        $selisih = null;
        if ($m['jumlah_guru'] > 0) {
            $b       = $this->lib->bandingkanDenganPengampu($t);
            $selisih = count($b['hanya_skbm']) + count($b['hanya_pengampu']) + count($b['jp_beda']);
        }

        return $this->ok([
            'tahun'       => $t,
            'ada'         => $m['jumlah_guru'] > 0,
            'tahun_opsi'  => $this->lib->daftarTahun($t),
            'sk'          => ['nomor' => $this->lib->nomorSk($t)],
            'kelas'       => $kelas,
            'grup'        => $m['grup'],
            'guru'        => $guru,
            'ringkas'     => [
                'jumlah_guru' => $m['jumlah_guru'], 'jumlah_mapel' => $m['jumlah_mapel'], 'jumlah_sel' => $m['jumlah_sel'], 'total_jp' => $m['total_jp'],
                'tanpa_jp' => $m['tanpa_jp'], 'beda_penugasan' => $selisih,
            ],
        ], $m['jumlah_guru'] > 0 ? 'SKBM ' . $t : 'SKBM ' . $t . ' belum diisi.');
    }

    /** Pilihan untuk dialog "Tambah guru / mapel": guru di Master Guru dan nama mapel yang sudah dikenal. */
    public function opsi(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $db = db_connect();

        return $this->ok([
            'guru'  => array_map(static fn (array $g): array => ['id' => (int) $g['id'], 'nama' => $g['nama']], $db->table('guru')->select('id, nama')->where('deleted_at', null)->where('induk_id', null)->orderBy('nama', 'ASC')->get()->getResultArray()),
            'mapel' => array_column($db->table('mata_pelajaran')->select('nama_mapel')->where('deleted_at', null)->distinct()->orderBy('nama_mapel', 'ASC')->get()->getResultArray(), 'nama_mapel'),
        ], 'Pilihan guru dan mapel.');
    }

    /** Selisih SKBM dengan Penugasan (hanya membandingkan; tak mengubah apa pun). */
    public function bandingkan(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->teks('tahun');
        if ($t === null || $t === '') {
            $t = $this->lib->tahunBawaan();
        } elseif (! DataSkbm::tahunValid($t)) {
            return $this->failure('Tahun ajaran tidak sah (contoh: 2026/2027).', 422);
        }

        return $this->ok(['tahun' => $t] + $this->lib->bandingkanDenganPengampu($t), 'SKBM ' . $t . ' dibandingkan dengan Penugasan.');
    }

    /** Excel SKBM — berkas biner; galat tetap JSON beramplop (klien periksa Content-Type). Tahun yang belum diisi → TEMPLATE kosong. */
    public function xlsx(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->teks('tahun');
        if ($t === null || $t === '') {
            $t = $this->lib->tahunBawaan();
        } elseif (! DataSkbm::tahunValid($t)) {
            return $this->failure('Tahun ajaran tidak sah (contoh: 2026/2027).', 422);
        }
        $m      = $this->lib->muat($t);
        $kosong = $m['jumlah_guru'] === 0;
        $isi    = SkbmCetak::xlsx(SkbmCetak::spreadsheet($m, SkbmCetak::info($t)));
        (new AuditModel())->record('export', 'skbm_mapel', null, 'Unduh Excel ' . ($kosong ? 'template ' : '') . 'SKBM ' . $t . ($kosong ? '' : ' (' . $m['jumlah_guru'] . ' guru, ' . $m['jumlah_mapel'] . ' baris mapel)') . ' (mobile)');
        $unduh = in_array((string) $this->request->getGet('unduh'), ['1', 'true', 'ya'], true);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', ($unduh ? 'attachment' : 'inline') . '; filename="' . SkbmCetak::namaBerkas($t, $kosong) . '"')
            ->setHeader('Content-Length', (string) strlen($isi))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setBody($isi);
    }

    // ----------------------------------------------------------------- tulis

    /** Nyalakan / matikan satu kelas pada satu baris mapel (+ JP bila dikirim: "" = kosongkan, 1–40 = isi). Membalas angka resmi dari server. */
    public function sel(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->tahunWajib();
        if ($t instanceof ResponseInterface) {
            return $t;
        }
        $aktif = filter_var($this->in['aktif'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $jp    = array_key_exists('jp', $this->in) ? ($this->in['jp'] === null ? '' : (is_scalar($this->in['jp']) ? (string) $this->in['jp'] : 'x')) : null;

        return $this->hasil($this->lib->setSel($t, (int) ($this->in['mapel'] ?? 0), (int) ($this->in['kelas'] ?? 0), $aktif, $jp));
    }

    public function tambahMapel(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->tahunWajib();
        if ($t instanceof ResponseInterface) {
            return $t;
        }

        return $this->hasil($this->lib->tambahMapel($t, (int) ($this->in['guru'] ?? 0), (string) ($this->in['nama'] ?? '')), $t, 201);
    }

    public function ubahMapel($id = 0): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }

        return $this->hasil($this->lib->ubahMapel((int) $id, (string) ($this->in['nama'] ?? '')));
    }

    public function hapusMapel($id = 0): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $h = $this->lib->hapusMapel((int) $id);

        return $this->hasil($h, ($h['ok'] ?? false) ? (string) $h['tahun'] : null);
    }

    public function hapusGuru($guruId = 0): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->tahunWajib();
        if ($t instanceof ResponseInterface) {
            return $t;
        }

        return $this->hasil($this->lib->hapusGuru($t, (int) $guruId), $t);
    }

    /** Nomor SK tahun ajaran (tercetak di baris "Nomor :" pada Excel SKBM); `nomor` kosong = hapus. */
    public function nomor(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->tahunWajib();
        if ($t instanceof ResponseInterface) {
            return $t;
        }

        return $this->hasil($this->lib->simpanNomorSk($t, (string) ($this->in['nomor'] ?? '')));
    }

    public function salin(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $dari = $this->tahunWajib('dari');
        if ($dari instanceof ResponseInterface) {
            return $dari;
        }
        $ke = $this->tahunWajib('ke');
        if ($ke instanceof ResponseInterface) {
            return $ke;
        }

        return $this->hasil($this->lib->salinTahun($dari, $ke, filter_var($this->in['ganti'] ?? false, FILTER_VALIDATE_BOOLEAN)), $ke);
    }

    public function kosongkan(): ResponseInterface
    {
        if (($e = $this->siap()) !== null) {
            return $e;
        }
        $t = $this->tahunWajib();
        if ($t instanceof ResponseInterface) {
            return $t;
        }

        return $this->hasil($this->lib->kosongkan($t), $t);
    }
}
