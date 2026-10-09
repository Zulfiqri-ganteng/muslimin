<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\HonorHitung;
use App\Libraries\HonorKoreksi;
use App\Libraries\HonorKoreksiCetak;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Ceklis KOREKSI honor ("KOREKSI NILAI": pembagian lembar jawaban ke guru per rombel) — API Android, KHUSUS ADMIN.
 * Cermin halaman Honor → Koreksi di web (Admin\UjianKoreksi); seluruh aturan & angka ada di Libraries\HonorKoreksi yang SAMA dengan web.
 * Kontrak lengkap + contoh: docs/API-SKBM-KOREKSI.md. Impor Excel KOREKSI TIDAK tersedia di API (hanya web).
 *
 * Semua di bawah `/api/v1/admin/ujian/{slug}/honor/koreksi` ({slug} = asts1|asas|asts2|asat) dan menerima/mengirim `tp` (lihat HonorBase):
 *   GET    …/koreksi                        ceklis lengkap + beda dengan honor + info SKBM + pilihan
 *   POST   …/koreksi/sel                    {mapel,kelas,aktif,jumlah?}    nyalakan/matikan sel (+ angka lembar khusus)
 *   POST   …/koreksi/peserta                {kelas,nilai}                  jumlah peserta ujian satu kelas ("" = ikut siswa aktif)
 *   POST   …/koreksi/mapel                  {baris,nama}                   tambah baris mapel untuk penerima (201)
 *   POST   …/koreksi/mapel/{id}             {nama}                         ganti nama mapel
 *   DELETE …/koreksi/mapel/{id}                                            hapus baris mapel
 *   DELETE …/koreksi/guru/{barisId}                                        keluarkan penerima dari ceklis
 *   POST   …/koreksi/isi-skbm               {ganti?}                       isi ceklis dari SKBM tahun ajaran honor ini
 *   POST   …/koreksi/isi-pengampu           {ganti?}                       isi ceklis dari data Penugasan
 *   POST   …/koreksi/salin                  {sumber,peserta?,ganti?}       salin ceklis dari honor lain
 *   POST   …/koreksi/kosongkan              {peserta?}                     buang seluruh ceklis
 *   POST   …/koreksi/segarkan-peserta       {semua?}                       samakan peserta dengan siswa aktif
 *   POST   …/koreksi/terapkan               {timpa?}                       tulis total ceklis ke kolom Koreksi honor
 *   GET    …/koreksi/xlsx                   Excel "KOREKSI NILAI" (BERKAS BINER; ?unduh=1 = attachment)
 */
class Koreksi extends HonorBase
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private HonorKoreksi $lib;

    public function __construct()
    {
        $this->lib = new HonorKoreksi();
    }

    /** Pagar Admin + periode (HonorBase) + tabel Koreksi sudah ada (kode bisa terpasang sebelum migrasinya). */
    private function siapKoreksi(string $slug): ?ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $db = db_connect();
        foreach (['honor_koreksi_mapel', 'honor_koreksi_kelas', 'honor_koreksi_sel'] as $tabel) {
            if (! $db->tableExists($tabel)) {
                return $this->failure('Fitur Koreksi belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).', 503);
            }
        }

        return null;
    }

    /** Peta berkunci id (int) → OBJEK JSON (kosong pun tetap objek, bukan list). */
    private function peta(array $a): object
    {
        return (object) array_combine(array_map('strval', array_keys($a)), array_values($a));
    }

    /**
     * Hasil pustaka → data respons: buang ok/pesan dan jadikan peta berkunci id sebagai OBJEK JSON.
     *
     * @param array<string,mixed> $h
     *
     * @return array<string,mixed>
     */
    private function rapikan(array $h): array
    {
        $h = array_diff_key($h, ['ok' => 1, 'pesan' => 1]);
        foreach (['per_baris', 'per_mapel', 'per_kelas'] as $k) {
            if (isset($h[$k]) && is_array($h[$k])) {
                $h[$k] = $this->peta($h[$k]);
            }
        }

        return $h;
    }

    /**
     * Pola umum aksi pada ceklis: pagar, ambil honor, jalankan, balas pesan + angka resmi (total/per baris/per mapel/per kelas).
     *
     * @param callable(HonorKoreksi,int):array $jalankan menerima pustaka & id dokumen, mengembalikan hasil pustaka
     */
    private function aksi(string $slug, callable $jalankan, int $kode = 200): ResponseInterface
    {
        if (($e = $this->siapKoreksi($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        if ($dok === null) {
            return $this->belumAda();
        }
        $h = $jalankan($this->lib, (int) $dok['id']);
        if (! ($h['ok'] ?? false)) {
            return $this->failure((string) ($h['pesan'] ?? 'Gagal.'), 422);
        }
        $data = $this->rapikan($h);

        return $kode === 201 ? $this->created($data, (string) $h['pesan']) : $this->ok($data, (string) $h['pesan']);
    }

    // ----------------------------------------------------------------- baca

    /** Ceklis lengkap + bahan layar. */
    public function show(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siapKoreksi($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        if ($dok === null) {
            return $this->belumAda();
        }
        $id = (int) $dok['id'];
        $m  = $this->lib->muat($id);
        $db = db_connect();
        $p  = $this->periode;

        $kelas = array_map(static fn (array $k): array => [
            'id' => (int) $k['id'], 'nama' => $k['nama'], 'label' => $k['label'], 'tingkat' => $k['tingkat'], 'shift' => $k['shift'], 'grup' => $k['grup'],
            'siswa' => (int) $k['siswa'], 'peserta' => (int) $k['peserta'], 'manual' => (bool) $k['manual'], 'jml' => (int) $k['jml'], 'lembar' => (int) $k['lembar'],
        ], $m['kelas']);
        $guru = [];
        foreach ($m['guru'] as $g) {
            $mapel = [];
            foreach ($g['mapel'] as $x) {
                $mapel[] = [
                    'id' => $x['id'], 'nama' => $x['nama'], 'mapel_id' => $x['mapel_id'], 'kode' => $x['kode'], 'total' => $x['total'],
                    'sel' => $this->peta($x['sel']), 'khusus' => array_map('intval', array_keys($x['ubah'])),
                ];
            }
            $guru[] = ['baris_id' => $g['baris_id'], 'no' => $g['no'], 'guru_id' => $g['guru_id'], 'nama' => $g['nama'], 'jabatan' => $g['jabatan'], 'total' => $g['total'], 'mapel' => $mapel];
        }
        $skbm = ['aktif' => false, 'tahun' => (string) $p['tahun_ajaran'], 'guru' => 0, 'mapel' => 0, 'sel' => 0];
        if ($db->tableExists('skbm_mapel') && $db->tableExists('skbm_sel')) {
            $r = $db->query('SELECT COUNT(DISTINCT m.guru_id) AS guru, COUNT(DISTINCT m.id) AS mapel, COUNT(s.id) AS sel FROM skbm_mapel m LEFT JOIN skbm_sel s ON s.skbm_mapel_id = m.id WHERE m.tahun_ajaran = ?', [$skbm['tahun']])->getRowArray();
            $skbm = ['aktif' => true, 'tahun' => $skbm['tahun'], 'guru' => (int) ($r['guru'] ?? 0), 'mapel' => (int) ($r['mapel'] ?? 0), 'sel' => (int) ($r['sel'] ?? 0)];
        }
        $lain = $db->query('SELECT d.id, p.jenis, p.tahun_ajaran, (SELECT COUNT(DISTINCT km.baris_id) FROM honor_koreksi_mapel km WHERE km.dokumen_id = d.id) AS guru
                            FROM honor_dokumen d JOIN ujian_periode p ON p.id = d.periode_id WHERE d.id != ? HAVING guru > 0 ORDER BY p.tahun_ajaran DESC, p.jenis ASC', [$id])->getResultArray();

        return $this->ok([
            'periode'   => ['id' => (int) $p['id'], 'jenis' => $p['jenis'], 'slug' => UjianPeriodeModel::keSlug($p['jenis']), 'tahun_ajaran' => $p['tahun_ajaran'], 'label' => (new UjianPeriodeModel())->label($p)],
            'status'    => (string) $dok['status'],
            'boleh_ubah' => (string) $dok['status'] !== 'dikunci',
            'ada_ceklis' => $m['guru'] !== [],
            'kelas'     => $kelas,
            'grup'      => $m['grup'],
            'guru'      => $guru,
            'ringkas'   => ['jumlah_guru' => $m['jumlah_guru'], 'jumlah_mapel' => $m['jumlah_mapel'], 'total' => $m['total']],
            'banding'   => $this->lib->bandingkanDenganHonor($id),
            'skbm'      => $skbm,
            'penerima'  => array_map(static fn (array $b): array => ['id' => (int) $b['id'], 'nama' => $b['nama'], 'jabatan' => $b['jabatan']], $db->table('honor_baris')->select('id, nama, jabatan')->where('dokumen_id', $id)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray()),
            'mapel_opsi' => array_column($db->table('mata_pelajaran')->select('nama_mapel')->where('deleted_at', null)->distinct()->orderBy('nama_mapel', 'ASC')->get()->getResultArray(), 'nama_mapel'),
            'honor_lain' => array_map(static fn (array $d): array => ['id' => (int) $d['id'], 'label' => (UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran'], 'guru' => (int) $d['guru']], $lain),
        ], 'Ceklis Koreksi ' . (new UjianPeriodeModel())->label($p));
    }

    // ----------------------------------------------------------------- tulis (angka resmi dikembalikan)

    /** Nyalakan / matikan satu kelas pada satu baris mapel; `nilai` = lembar efektif sel itu (null bila dimatikan). */
    public function sel(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, function (HonorKoreksi $k, int $dokId): array {
            $aktif = filter_var($this->in['aktif'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $mapel = (int) ($this->in['mapel'] ?? 0);
            $kelas = (int) ($this->in['kelas'] ?? 0);
            $jumlah = array_key_exists('jumlah', $this->in) ? ($this->in['jumlah'] === null ? '' : (is_scalar($this->in['jumlah']) ? (string) $this->in['jumlah'] : 'x')) : null;
            $h = $k->setSel($dokId, $mapel, $kelas, $aktif, $jumlah);
            if ($h['ok']) {
                $h['nilai'] = $aktif ? $k->nilaiSel($dokId, $mapel, $kelas) : null;
            }

            return $h;
        });
    }

    public function peserta(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->simpanPeserta($dokId, [(int) ($this->in['kelas'] ?? 0) => (string) ($this->in['nilai'] ?? '')]));
    }

    public function tambahMapel(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->tambahMapel($dokId, (int) ($this->in['baris'] ?? 0), (string) ($this->in['nama'] ?? '')), 201);
    }

    public function ubahMapel(string $slug = '', $mapelRowId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->ubahMapel($dokId, (int) $mapelRowId, (string) ($this->in['nama'] ?? '')));
    }

    public function hapusMapel(string $slug = '', $mapelRowId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->hapusMapel($dokId, (int) $mapelRowId));
    }

    public function hapusGuru(string $slug = '', $barisId = 0): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->hapusGuru($dokId, (int) $barisId));
    }

    public function isiSkbm(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->isiDariSkbm($dokId, filter_var($this->in['ganti'] ?? false, FILTER_VALIDATE_BOOLEAN)));
    }

    public function isiPengampu(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->isiDariPengampu($dokId, filter_var($this->in['ganti'] ?? false, FILTER_VALIDATE_BOOLEAN)));
    }

    public function salin(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->salinDari(
            $dokId,
            (int) ($this->in['sumber'] ?? 0),
            filter_var($this->in['peserta'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($this->in['ganti'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ));
    }

    public function kosongkan(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->kosongkan($dokId, filter_var($this->in['peserta'] ?? false, FILTER_VALIDATE_BOOLEAN)));
    }

    public function segarkanPeserta(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, fn (HonorKoreksi $k, int $dokId): array => $k->segarkanPeserta($dokId, filter_var($this->in['semua'] ?? false, FILTER_VALIDATE_BOOLEAN)));
    }

    /** Tulis total ceklis ke kolom Koreksi honor (lewat Hitung otomatis yang sama dengan web). */
    public function terapkan(string $slug = ''): ResponseInterface
    {
        return $this->aksi($slug, function (HonorKoreksi $k, int $dokId): array {
            if (! $k->ada($dokId)) {
                return ['ok' => false, 'pesan' => 'Ceklis masih kosong. Isi ceklis dulu.'];
            }
            $h = (new HonorHitung())->terapkan($dokId, ['koreksi'], filter_var($this->in['timpa'] ?? false, FILTER_VALIDATE_BOOLEAN));

            return $h + ['honor' => $this->ringkas($dokId)];
        });
    }

    // ----------------------------------------------------------------- unduh

    /** Excel "KOREKSI NILAI" — berkas biner; galat tetap JSON beramplop (klien periksa Content-Type). */
    public function xlsx(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siapKoreksi($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        if ($dok === null) {
            return $this->belumAda();
        }
        $m = $this->lib->muat((int) $dok['id']);
        if ($m === null || $m['guru'] === []) {
            return $this->failure('Ceklis masih kosong — belum ada yang bisa diunduh.', 422);
        }
        $isi = HonorKoreksiCetak::xlsx(HonorKoreksiCetak::spreadsheet($m, $this->periode));
        (new AuditModel())->record('export', 'honor_koreksi_mapel', (int) $dok['id'], 'Unduh Excel KOREKSI NILAI ' . (new UjianPeriodeModel())->label($this->periode) . ' (' . $m['jumlah_guru'] . ' guru, ' . $m['total'] . ' lembar) (mobile)');
        $unduh = in_array((string) $this->request->getGet('unduh'), ['1', 'true', 'ya'], true);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', self::MIME_XLSX)
            ->setHeader('Content-Disposition', ($unduh ? 'attachment' : 'inline') . '; filename="' . HonorKoreksiCetak::namaBerkas($this->periode) . '"')
            ->setHeader('Content-Length', (string) strlen($isi))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setBody($isi);
    }
}
