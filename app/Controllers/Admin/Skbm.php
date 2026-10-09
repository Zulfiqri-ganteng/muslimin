<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Skbm as DataSkbm;
use App\Libraries\SkbmCetak;
use App\Libraries\SkbmImpor;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SKBM — SK Pembagian Tugas Mengajar per tahun ajaran: halaman matriks (guru × mapel × kelas, isi = JP), impor Excel sekolah,
 * salin antar tahun, dan perbandingan dengan Penugasan (pengampu). KHUSUS ADMIN: dipakai sebagai sumber ceklis Koreksi honor
 * (data gaji), jadi rute ini sengaja tidak masuk daftar hak Operator/Waka Hubin; tiap aksi memeriksa ulang peran Admin.
 * Aturan & hitungan: Libraries\Skbm dan Libraries\SkbmImpor. Perubahan tercatat di Audit Log.
 *
 * Aksi yang sering (nyalakan sel, isi JP, ganti nama mapel) berupa POST JSON; aksi struktural (tambah/hapus baris, impor,
 * salin, kosongkan) berupa form biasa lalu kembali ke halaman.
 */
class Skbm extends BaseController
{
    private const ALAMAT = 'admin/skbm';

    // ----------------------------------------------------------------- pagar

    /** Admin + tabel SKBM sudah ada (kode baru bisa terpasang lebih dulu daripada migrasinya). */
    private function pagar(bool $json = false): RedirectResponse|ResponseInterface|null
    {
        if ((string) (session('admin')['role'] ?? '') !== 'admin') {
            return $json
                ? $this->json(['ok' => false, 'pesan' => 'SKBM hanya bisa dikelola Admin.'], 403)
                : redirect()->to(site_url('admin/dashboard'))->with('error', 'SKBM hanya bisa dikelola Admin.');
        }
        $db = db_connect();
        if (! $db->tableExists('skbm_mapel') || ! $db->tableExists('skbm_sel')) {
            $pesan = 'Fitur SKBM belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).';

            return $json ? $this->json(['ok' => false, 'pesan' => $pesan], 503) : redirect()->to(site_url('admin/dashboard'))->with('error', $pesan);
        }

        return null;
    }

    /** Tahun ajaran dari POST/GET (hanya yang sah), bila tidak ada → tahun bawaan. */
    private function tahun(): string
    {
        $t = trim((string) ($this->request->getPost('tahun') ?? $this->request->getGet('tahun') ?? ''));

        return DataSkbm::tahunValid($t) ? $t : (new DataSkbm())->tahunBawaan();
    }

    private function url(string $tahun, string $akhiran = '', string $jangkar = ''): string
    {
        return site_url(self::ALAMAT . $akhiran) . '?tahun=' . rawurlencode($tahun) . $jangkar;
    }

    private function kembali(string $tahun, array $hasil, string $jangkar = ''): RedirectResponse
    {
        return redirect()->to($this->url($tahun, '', $jangkar))->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /** Balasan JSON tanpa cache; token CSRF terkini disertakan. */
    private function json(array $isi, int $kode = 200): ResponseInterface
    {
        $isi['csrf'] = csrf_hash();

        return $this->response->setStatusCode($kode)->setHeader('Cache-Control', 'no-store')->setJSON($isi);
    }

    // ----------------------------------------------------------------- halaman

    /** GET admin/skbm?tahun= — matriks SKBM satu tahun ajaran. */
    public function index()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $s     = new DataSkbm();
        $tahun = $this->tahun();
        $m     = $s->muat($tahun);
        $db    = db_connect();

        $daftar = $s->daftarTahun($tahun);
        $opsi   = array_column($daftar, 'tahun');
        $berisi = [];
        foreach ($daftar as $d) {
            if ($d['guru'] > 0) {
                $berisi[$d['tahun']] = ['guru' => $d['guru'], 'mapel' => $d['mapel']];
            }
        }
        $selisih = null;
        if ($m['jumlah_guru'] > 0) {
            $b       = $s->bandingkanDenganPengampu($tahun);
            $selisih = count($b['hanya_skbm']) + count($b['hanya_pengampu']) + count($b['jp_beda']);
        }

        // Honor ujian tahun ajaran ini → tautan langsung ke halaman Koreksi-nya ("Isi dari SKBM" ada di sana).
        $honorTahun = [];
        if ($db->tableExists('honor_dokumen')) {
            foreach ($db->query("SELECT p.jenis, p.tahun_ajaran FROM honor_dokumen d JOIN ujian_periode p ON p.id = d.periode_id
                                 WHERE p.tahun_ajaran = ? AND p.deleted_at IS NULL ORDER BY FIELD(p.jenis, 'ASTS1', 'ASAS', 'ASTS2', 'ASAT')", [$tahun])->getResultArray() as $r) {
                $honorTahun[] = [
                    'label' => UjianPeriodeModel::JENIS_LABEL[$r['jenis']] ?? $r['jenis'],
                    'url'   => site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $r['jenis']) . '/honor/koreksi') . '?tp=' . rawurlencode((string) $r['tahun_ajaran']),
                ];
            }
        }

        return view('admin/skbm/index', [
            'title'     => 'SKBM — Pembagian Tugas Mengajar',
            'honorTahun' => $honorTahun,
            'nomorSk'   => $s->nomorSk($tahun),
            'tahun'     => $tahun,
            'tahunOpsi' => $opsi,
            'berisi'    => $berisi,
            'm'         => $m,
            'selisih'   => $selisih,
            'tanpaJp'   => $m['tanpa_jp'],
            'guruOpsi'  => $db->table('guru')->select('id, nama')->where('deleted_at', null)->where('induk_id', null)->orderBy('nama', 'ASC')->get()->getResultArray(),
            'mapelOpsi' => array_column($db->table('mata_pelajaran')->select('nama_mapel')->where('deleted_at', null)->distinct()->orderBy('nama_mapel', 'ASC')->get()->getResultArray(), 'nama_mapel'),
            'base'      => site_url(self::ALAMAT),
        ]);
    }

    /** GET admin/skbm/bandingkan?tahun= — selisih SKBM dengan data Penugasan (pengampu). */
    public function bandingkan()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();

        return view('admin/skbm/bandingkan', [
            'title' => 'SKBM vs Penugasan',
            'tahun' => $tahun,
            'b'     => (new DataSkbm())->bandingkanDenganPengampu($tahun),
            'back'  => $this->url($tahun),
        ]);
    }

    /** GET admin/skbm/xlsx?tahun= — Excel SKBM tahun itu (atau TEMPLATE kosong bila belum diisi); bisa diimpor kembali. */
    public function xlsx()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun  = $this->tahun();
        $m      = (new DataSkbm())->muat($tahun);
        $kosong = $m['jumlah_guru'] === 0;
        $isi    = SkbmCetak::xlsx(SkbmCetak::spreadsheet($m, SkbmCetak::info($tahun)));
        (new AuditModel())->record('export', 'skbm_mapel', null, 'Unduh Excel ' . ($kosong ? 'template ' : '') . 'SKBM ' . $tahun . ($kosong ? '' : ' (' . $m['jumlah_guru'] . ' guru, ' . $m['jumlah_mapel'] . ' baris mapel)'));

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . SkbmCetak::namaBerkas($tahun, $kosong) . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($isi);
    }

    // ----------------------------------------------------------------- aksi JSON

    /** POST admin/skbm/sel — nyalakan / matikan satu kelas pada satu baris mapel (+ JP bila dikirim). */
    public function sel()
    {
        if (($tolak = $this->pagar(true)) !== null) {
            return $tolak;
        }
        $h = (new DataSkbm())->setSel(
            $this->tahun(),
            (int) $this->request->getPost('mapel'),
            (int) $this->request->getPost('kelas'),
            (string) $this->request->getPost('aktif') === '1',
            $this->request->getPost('jp')
        );

        return $this->json($h, $h['ok'] ? 200 : 422);
    }

    /** POST admin/skbm/mapel/(:num)/ubah — ganti nama mapel satu baris. */
    public function ubahMapel($id = 0)
    {
        if (($tolak = $this->pagar(true)) !== null) {
            return $tolak;
        }
        $h = (new DataSkbm())->ubahMapel((int) $id, (string) $this->request->getPost('nama'));

        return $this->json($h, $h['ok'] ? 200 : 422);
    }

    // ----------------------------------------------------------------- aksi form (kembali ke halaman)

    /** POST admin/skbm/mapel — tambah baris mapel untuk seorang guru. */
    public function tambahMapel()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();
        $h     = (new DataSkbm())->tambahMapel($tahun, (int) $this->request->getPost('guru'), (string) $this->request->getPost('nama'));

        return $this->kembali($tahun, $h);
    }

    /** POST admin/skbm/mapel/(:num)/hapus */
    public function hapusMapel($id = 0)
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $h = (new DataSkbm())->hapusMapel((int) $id);

        return $this->kembali($h['tahun'] ?? $this->tahun(), $h);
    }

    /** POST admin/skbm/guru/(:num)/hapus — keluarkan seorang guru dari SKBM tahun itu. */
    public function hapusGuru($guruId = 0)
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();

        return $this->kembali($tahun, (new DataSkbm())->hapusGuru($tahun, (int) $guruId));
    }

    /** POST admin/skbm/nomor — simpan nomor SK tahun ajaran (tercetak di baris "Nomor :" pada Excel SKBM); kosong = hapus. */
    public function nomor()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();

        return $this->kembali($tahun, (new DataSkbm())->simpanNomorSk($tahun, (string) $this->request->getPost('nomor')));
    }

    /** POST admin/skbm/salin — salin SKBM tahun lain ke tahun ini. */
    public function salin()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();
        $h     = (new DataSkbm())->salinTahun((string) $this->request->getPost('dari'), $tahun, (bool) $this->request->getPost('ganti'));

        return $this->kembali($tahun, $h);
    }

    /** POST admin/skbm/kosongkan — buang seluruh SKBM satu tahun ajaran. */
    public function kosongkan()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun = $this->tahun();

        return $this->kembali($tahun, (new DataSkbm())->kosongkan($tahun));
    }

    // ----------------------------------------------------------------- impor Excel sekolah

    /** POST admin/skbm/impor/unggah — baca berkas, simpan hasil bacaan di sesi, tampilkan pratinjau. */
    public function imporUnggah()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $tahun  = $this->tahun();
        $berkas = $this->request->getFile('berkas');
        if ($berkas === null || $berkas->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->kembali($tahun, ['ok' => false, 'pesan' => 'Pilih berkas Excel (.xlsx) dulu.'], '#impor');
        }
        if (in_array($berkas->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return $this->kembali($tahun, ['ok' => false, 'pesan' => 'Berkas lebih besar dari batas unggah server. Salin lembar SKBM saja ke berkas Excel baru (File → Baru, tempel lembarnya), lalu unggah yang kecil itu.'], '#impor');
        }
        if (! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->kembali($tahun, ['ok' => false, 'pesan' => 'Berkas gagal diunggah. Coba lagi.'], '#impor');
        }
        if (strtolower((string) $berkas->getClientExtension()) !== 'xlsx' || $berkas->getSize() > SkbmImpor::MAKS_BYTE) {
            return $this->kembali($tahun, ['ok' => false, 'pesan' => 'Berkas harus berformat .xlsx dan maksimal 8 MB.'], '#impor');
        }
        try {
            $payload = (new SkbmImpor())->baca($berkas->getTempName());
        } catch (\RuntimeException $e) {
            return $this->kembali($tahun, ['ok' => false, 'pesan' => $e->getMessage()], '#impor');
        }
        session()->set('skbm_impor', [
            'token' => bin2hex(random_bytes(8)), 'payload' => $payload, 'berkas' => mb_substr((string) $berkas->getClientName(), 0, 120),
            'tahun' => $tahun, 'at' => time(),
        ]);

        return redirect()->to(site_url(self::ALAMAT . '/impor'));
    }

    /** GET admin/skbm/impor?blok= — pratinjau: pilihan blok kelas, pencocokan kelas & nama guru, keputusan tiap guru. */
    public function impor()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $sesi = $this->sesiImpor();
        if ($sesi === null) {
            return redirect()->to(site_url(self::ALAMAT) . '#impor')->with('error', 'Data impor sudah kedaluwarsa atau belum ada. Unggah berkas Excel lagi.');
        }
        $b       = $this->request->getGet('blok');
        $payload = (new SkbmImpor())->cocokkan($sesi['payload'], ctype_digit((string) $b) ? (int) $b : null);
        $s       = new DataSkbm();
        $db      = db_connect();
        $tujuan  = $payload['tahun'] !== null && DataSkbm::tahunValid((string) $payload['tahun']) ? (string) $payload['tahun'] : (string) $sesi['tahun'];
        $berisi  = [];
        foreach ($db->query('SELECT tahun_ajaran, COUNT(DISTINCT guru_id) AS guru FROM skbm_mapel GROUP BY tahun_ajaran')->getResultArray() as $r) {
            $berisi[$r['tahun_ajaran']] = (int) $r['guru'];
        }

        return view('admin/skbm/impor', [
            'title'    => 'Impor SKBM dari Excel',
            'token'    => $sesi['token'],
            'berkas'   => $sesi['berkas'],
            'payload'  => $payload,
            'tujuan'   => $tujuan,
            'berisi'   => $berisi,
            'guruOpsi' => $db->table('guru')->select('id, nama')->where('deleted_at', null)->where('induk_id', null)->orderBy('nama', 'ASC')->get()->getResultArray(),
            'back'     => $this->url((string) $sesi['tahun']),
            'tahunBawaan' => $s->tahunBawaan(),
        ]);
    }

    /** POST admin/skbm/impor/terapkan — pencocokan dihitung ulang di server; hanya pilihan guru & blok yang diambil dari form. */
    public function imporTerapkan()
    {
        if (($tolak = $this->pagar()) !== null) {
            return $tolak;
        }
        $sesi = $this->sesiImpor();
        if ($sesi === null || ! hash_equals((string) $sesi['token'], (string) $this->request->getPost('token'))) {
            return redirect()->to(site_url(self::ALAMAT) . '#impor')->with('error', 'Data impor sudah kedaluwarsa. Unggah berkas Excel lagi.');
        }
        $tujuan = trim((string) $this->request->getPost('tujuan'));
        $blok   = (string) $this->request->getPost('blok');
        if (! DataSkbm::tahunValid($tujuan)) {
            return redirect()->to(site_url(self::ALAMAT . '/impor') . '?blok=' . (ctype_digit($blok) ? $blok : '0'))->with('error', 'Tahun ajaran tujuan tidak sah. Tulis seperti 2026/2027.');
        }
        $keputusan = [];
        foreach ((array) $this->request->getPost('aksi') as $i => $v) {
            $keputusan[(int) $i] = (string) $v;
        }
        $imp   = new SkbmImpor();
        $hasil = $imp->terapkan($tujuan, $imp->cocokkan($sesi['payload'], ctype_digit($blok) ? (int) $blok : null), $keputusan, (bool) $this->request->getPost('ganti'));
        if (! $hasil['ok']) {
            return redirect()->to(site_url(self::ALAMAT . '/impor') . '?blok=' . (ctype_digit($blok) ? $blok : '0'))->with('error', $hasil['pesan']);
        }
        session()->remove('skbm_impor');
        $r = redirect()->to($this->url($tujuan))->with('success', $hasil['pesan']);
        $p = array_merge($sesi['payload']['peringatan'] ?? [], $hasil['ringkas']['peringatan'] ?? []);

        return $p !== [] ? $r->with('skbm_peringatan', array_slice($p, 0, 12)) : $r;
    }

    /** Data impor di sesi yang masih berlaku (umur ≤ 30 menit), atau null. */
    private function sesiImpor(): ?array
    {
        $s = session('skbm_impor');
        if (! is_array($s) || time() - (int) ($s['at'] ?? 0) > 1800) {
            return null;
        }

        return $s;
    }
}
