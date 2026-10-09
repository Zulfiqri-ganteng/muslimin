<?php

namespace App\Controllers\Admin;

use App\Libraries\HonorHitung;
use App\Libraries\HonorKoreksi;
use App\Libraries\HonorKoreksiCetak;
use App\Libraries\HonorKoreksiImpor;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Ceklis KOREKSI honor ("KOREKSI NILAI": pembagian lembar jawaban ke guru per rombel) — halaman, aksi, impor Excel
 * sekolah, dan unduhan Excel. KHUSUS ADMIN (bagian dari data gaji): memakai pagar yang sama dengan UjianHonor, rute tidak
 * ada di hak Operator/Waka Hubin. Aturan & hitungan: Libraries\HonorKoreksi; Excel: Libraries\HonorKoreksiCetak.
 *
 * Halaman: GET ujian/{jenis}/honor/koreksi?tp=… Aksi sering (nyalakan sel, ubah peserta) berupa POST JSON; aksi
 * struktural (tambah/hapus baris, isi dari pengampu, salin, terapkan) berupa form biasa lalu kembali ke halaman.
 */
class UjianKoreksi extends UjianHonor
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    // ----------------------------------------------------------------- pagar

    /** Pagar halaman GET: Admin + periode dikenal (UjianHonor) + tabel Koreksi sudah ada (migrasi sudah dijalankan). */
    protected function siapkanGet(string $slug): RedirectResponse|ResponseInterface|null
    {
        if (($salah = parent::siapkanGet($slug)) !== null) {
            return $salah;
        }

        return $this->cekMigrasi(false);
    }

    /** Pagar aksi POST: Admin + periode cocok (UjianHonor) + tabel Koreksi sudah ada. */
    protected function siapkan(string $slug, bool $json = false): RedirectResponse|ResponseInterface|null
    {
        if (($salah = parent::siapkan($slug, $json)) !== null) {
            return $salah;
        }

        return $this->cekMigrasi($json);
    }

    /** Kode baru bisa terpasang lebih dulu daripada migrasinya: beri pesan jelas, jangan galat server. */
    private function cekMigrasi(bool $json): RedirectResponse|ResponseInterface|null
    {
        if (db_connect()->tableExists('honor_koreksi_mapel') && db_connect()->tableExists('honor_koreksi_kelas') && db_connect()->tableExists('honor_koreksi_sel')) {
            return null;
        }
        $pesan = 'Fitur Koreksi belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).';

        return $json ? $this->json(['ok' => false, 'pesan' => $pesan], 503) : redirect()->to($this->urlHonor())->with('error', $pesan);
    }

    // ----------------------------------------------------------------- halaman

    /** GET ujian/(:segment)/honor/koreksi */
    public function index(string $slug = '')
    {
        if (($salah = $this->siapkanGet($slug)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return redirect()->to($this->urlHonor())->with('error', 'Buat honor dulu, baru ceklis Koreksi bisa diisi.');
        }
        $k  = new HonorKoreksi();
        $db = db_connect();
        $m  = $k->muat((int) $dok['id']);

        return view('admin/ujian/koreksi', [
            'title'     => 'Koreksi Honor',
            'slug'      => UjianPeriodeModel::keSlug((string) $this->periode['jenis']),
            'periode'   => $this->periode,
            'label'     => (new UjianPeriodeModel())->label($this->periode),
            'm'         => $m,
            'banding'   => $k->bandingkanDenganHonor((int) $dok['id']),
            'status'    => (string) $dok['status'],
            'penerima'  => $db->table('honor_baris')->select('id, nama, jabatan')->where('dokumen_id', $dok['id'])->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(),
            'mapelOpsi' => array_column($db->table('mata_pelajaran')->select('nama_mapel')->where('deleted_at', null)->distinct()->orderBy('nama_mapel', 'ASC')->get()->getResultArray(), 'nama_mapel'),
            'lain'      => $db->query('SELECT d.id, p.jenis, p.tahun_ajaran, (SELECT COUNT(DISTINCT km.baris_id) FROM honor_koreksi_mapel km WHERE km.dokumen_id = d.id) AS guru
                                       FROM honor_dokumen d JOIN ujian_periode p ON p.id = d.periode_id WHERE d.id != ? HAVING guru > 0 ORDER BY p.tahun_ajaran DESC, p.jenis ASC', [(int) $dok['id']])->getResultArray(),
            'kembali'   => $this->urlHonor(),
            'base'      => site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/honor/koreksi'),
            'qtp'       => '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']),
        ]);
    }

    // ----------------------------------------------------------------- aksi JSON

    /** POST …/koreksi/sel — nyalakan / matikan satu kelas pada satu baris mapel (+ angka khusus bila dikirim). */
    public function sel(string $slug = '')
    {
        return $this->aksiJson($slug, function (HonorKoreksi $k, int $dokId): array {
            $aktif = (string) $this->request->getPost('aktif') === '1';
            $mapel = (int) $this->request->getPost('mapel');
            $kelas = (int) $this->request->getPost('kelas');
            $h     = $k->setSel($dokId, $mapel, $kelas, $aktif, $this->request->getPost('jumlah'));
            if ($h['ok']) {
                $h['nilai'] = $aktif ? $k->nilaiSel($dokId, $mapel, $kelas) : null;
            }

            return $h;
        });
    }

    /** POST …/koreksi/peserta — ubah jumlah peserta satu kelas ("" = ikut siswa aktif). */
    public function peserta(string $slug = '')
    {
        return $this->aksiJson($slug, fn (HonorKoreksi $k, int $dokId): array => $k->simpanPeserta($dokId, [(int) $this->request->getPost('kelas') => (string) $this->request->getPost('nilai')]));
    }

    /** POST …/koreksi/mapel/(:num)/ubah — ganti nama mapel satu baris. */
    public function ubahMapel(string $slug = '', $mapelRowId = 0)
    {
        return $this->aksiJson($slug, fn (HonorKoreksi $k, int $dokId): array => $k->ubahMapel($dokId, (int) $mapelRowId, (string) $this->request->getPost('nama')));
    }

    // ----------------------------------------------------------------- aksi form (kembali ke halaman)

    /** POST …/koreksi/mapel — tambah baris mapel untuk seorang penerima. */
    public function tambahMapel(string $slug = '')
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->tambahMapel($dokId, (int) $this->request->getPost('baris'), (string) $this->request->getPost('nama')));
    }

    /** POST …/koreksi/mapel/(:num)/hapus */
    public function hapusMapel(string $slug = '', $mapelRowId = 0)
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->hapusMapel($dokId, (int) $mapelRowId));
    }

    /** POST …/koreksi/guru/(:num)/hapus — keluarkan seorang penerima dari ceklis. */
    public function hapusGuru(string $slug = '', $barisId = 0)
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->hapusGuru($dokId, (int) $barisId));
    }

    /** POST …/koreksi/isi-pengampu */
    public function isiPengampu(string $slug = '')
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->isiDariPengampu($dokId, (bool) $this->request->getPost('ganti')));
    }

    /** POST …/koreksi/salin */
    public function salin(string $slug = '')
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->salinDari($dokId, (int) $this->request->getPost('sumber'), (bool) $this->request->getPost('peserta'), (bool) $this->request->getPost('ganti')));
    }

    /** POST …/koreksi/kosongkan */
    public function kosongkan(string $slug = '')
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->kosongkan($dokId, (bool) $this->request->getPost('peserta')));
    }

    /** POST …/koreksi/segarkan-peserta */
    public function segarkanPeserta(string $slug = '')
    {
        return $this->aksiForm($slug, fn (HonorKoreksi $k, int $dokId): array => $k->segarkanPeserta($dokId, (bool) $this->request->getPost('semua')));
    }

    /** POST …/koreksi/terapkan — isi kolom "Koreksi" di honor dari total ceklis (lewat Hitung otomatis yang sama). */
    public function terapkan(string $slug = '')
    {
        return $this->aksiForm($slug, function (HonorKoreksi $k, int $dokId): array {
            if (! $k->ada($dokId)) {
                return ['ok' => false, 'pesan' => 'Ceklis masih kosong. Isi ceklis dulu.'];
            }

            return (new HonorHitung())->terapkan($dokId, ['koreksi'], (bool) $this->request->getPost('timpa'));
        });
    }

    // ----------------------------------------------------------------- impor Excel sekolah

    /** POST …/koreksi/impor/unggah — baca berkas, simpan hasil bacaan di sesi, tampilkan pratinjau. */
    public function imporUnggah(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        if ($this->dokumen() === null) {
            return redirect()->to($this->urlHonor())->with('error', 'Buat honor dulu.');
        }
        $berkas = $this->request->getFile('berkas');
        if ($berkas === null || ! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->kembaliKoreksi(['ok' => false, 'pesan' => 'Pilih berkas Excel (.xlsx) dulu.']);
        }
        if (strtolower((string) $berkas->getClientExtension()) !== 'xlsx' || $berkas->getSize() > HonorKoreksiImpor::MAKS_BYTE) {
            return $this->kembaliKoreksi(['ok' => false, 'pesan' => 'Berkas harus berformat .xlsx dan maksimal 2 MB.']);
        }
        try {
            $payload = (new HonorKoreksiImpor())->baca($berkas->getTempName());
        } catch (\RuntimeException $e) {
            return $this->kembaliKoreksi(['ok' => false, 'pesan' => $e->getMessage()]);
        }
        $token = bin2hex(random_bytes(8));
        session()->set('koreksi_impor', ['token' => $token, 'periode_id' => (int) $this->periode['id'], 'payload' => $payload, 'berkas' => mb_substr((string) $berkas->getClientName(), 0, 120), 'at' => time()]);

        return redirect()->to($this->urlHonor('/koreksi/impor'));
    }

    /** GET …/koreksi/impor — pratinjau: pencocokan kelas & nama guru, pilihan tiap guru. */
    public function impor(string $slug = '')
    {
        if (($salah = $this->siapkanGet($slug)) !== null) {
            return $salah;
        }
        $dok  = $this->dokumen();
        $sesi = $this->sesiImpor();
        if ($dok === null || $sesi === null) {
            return redirect()->to($this->urlHonor('/koreksi'))->with('error', 'Data impor sudah kedaluwarsa atau belum ada. Unggah berkas Excel lagi.');
        }
        $payload = (new HonorKoreksiImpor())->cocokkan($sesi['payload'], (int) $dok['id']);
        $db      = db_connect();

        return view('admin/ujian/koreksi_impor', [
            'title'    => 'Impor Ceklis Koreksi dari Excel',
            'slug'     => UjianPeriodeModel::keSlug((string) $this->periode['jenis']),
            'periode'  => $this->periode,
            'label'    => (new UjianPeriodeModel())->label($this->periode),
            'token'    => $sesi['token'],
            'berkas'   => $sesi['berkas'],
            'payload'  => $payload,
            'penerima' => $db->table('honor_baris')->select('id, nama')->where('dokumen_id', $dok['id'])->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(),
            'adaCeklis' => (new HonorKoreksi())->ada((int) $dok['id']),
            'terkunci' => (string) $dok['status'] === 'dikunci',
            'kembali'  => $this->urlHonor('/koreksi'),
        ]);
    }

    /** POST …/koreksi/impor/terapkan — pencocokan dihitung ulang di server; hanya pilihan guru yang diambil dari form. */
    public function imporTerapkan(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok  = $this->dokumen();
        $sesi = $this->sesiImpor();
        if ($dok === null || $sesi === null || ! hash_equals((string) $sesi['token'], (string) $this->request->getPost('token'))) {
            return redirect()->to($this->urlHonor('/koreksi'))->with('error', 'Data impor sudah kedaluwarsa. Unggah berkas Excel lagi.');
        }
        $keputusan = [];
        foreach ((array) $this->request->getPost('aksi') as $i => $v) {
            $keputusan[(int) $i] = (string) $v;
        }
        $imp   = new HonorKoreksiImpor();
        $hasil = $imp->terapkan((int) $dok['id'], $imp->cocokkan($sesi['payload'], (int) $dok['id']), $keputusan, (bool) $this->request->getPost('ganti'));
        if (! $hasil['ok']) {
            return redirect()->to($this->urlHonor('/koreksi/impor'))->with('error', $hasil['pesan']);
        }
        session()->remove('koreksi_impor');
        $r = redirect()->to($this->urlHonor('/koreksi'))->with('success', $hasil['pesan']);
        $p = array_merge($sesi['payload']['peringatan'] ?? [], $hasil['ringkas']['peringatan'] ?? []);

        return $p !== [] ? $r->with('koreksi_peringatan', array_slice($p, 0, 12)) : $r;
    }

    // ----------------------------------------------------------------- unduh

    /** GET …/koreksi/xlsx — Excel "KOREKSI NILAI" (tata letak sama dengan berkas sekolah). */
    public function xlsx(string $slug = '')
    {
        if (($salah = $this->siapkanGet($slug)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        $m   = $dok !== null ? (new HonorKoreksi())->muat((int) $dok['id']) : null;
        if ($m === null || $m['guru'] === []) {
            return redirect()->to($this->urlHonor('/koreksi'))->with('error', 'Ceklis masih kosong — belum ada yang bisa diunduh.');
        }
        $isi = HonorKoreksiCetak::xlsx(HonorKoreksiCetak::spreadsheet($m, $this->periode));
        (new AuditModel())->record('export', 'honor_koreksi_mapel', (int) $dok['id'], 'Unduh Excel KOREKSI NILAI ' . (new UjianPeriodeModel())->label($this->periode) . ' (' . $m['jumlah_guru'] . ' guru, ' . $m['total'] . ' lembar)');

        return $this->response
            ->setHeader('Content-Type', self::XLSX)
            ->setHeader('Content-Disposition', 'attachment; filename="' . HonorKoreksiCetak::namaBerkas($this->periode) . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($isi);
    }

    // -----------------------------------------------------------------

    /** Data impor di sesi yang masih berlaku (periode cocok, umur ≤ 30 menit), atau null. */
    private function sesiImpor(): ?array
    {
        $s = session('koreksi_impor');
        if (! is_array($s) || (int) ($s['periode_id'] ?? 0) !== (int) $this->periode['id'] || time() - (int) ($s['at'] ?? 0) > 1800) {
            return null;
        }

        return $s;
    }

    private function kembaliKoreksi(array $hasil): RedirectResponse
    {
        return redirect()->to($this->urlHonor('/koreksi'))->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /** @param callable(HonorKoreksi, int): array $kerja */
    private function aksiJson(string $slug, callable $kerja): ResponseInterface
    {
        if (($salah = $this->siapkan($slug, true)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->json(['ok' => false, 'pesan' => 'Buat honor dulu.'], 422);
        }
        $hasil = $kerja(new HonorKoreksi(), (int) $dok['id']);

        return $this->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** @param callable(HonorKoreksi, int): array $kerja */
    private function aksiForm(string $slug, callable $kerja): RedirectResponse|ResponseInterface
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return redirect()->to($this->urlHonor())->with('error', 'Buat honor dulu.');
        }

        return $this->kembaliKoreksi($kerja(new HonorKoreksi(), (int) $dok['id']));
    }
}
