<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HonorDokumen;
use App\Libraries\HonorHitung;
use App\Libraries\HonorImpor;
use App\Libraries\HonorPengaturan;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Honor Ujian — aksi pada tab "Honor" di halaman Ujian (buat honor, penerima, isian, data surat).
 * KHUSUS ADMIN (data gaji): rute tidak ada di hak Operator/Waka Hubin, dan tiap aksi memeriksa ulang peran Admin.
 * Aturan & hitungan: Libraries\HonorDokumen. Tampilan tab: Admin\Ujian::jenis → admin/ujian/tab_honor.
 *
 * Periode ditentukan dari `periode_id` kiriman form dan DIPERIKSA cocok dengan jenis ujian di alamat (slug),
 * jadi alamat asts1 tidak bisa dipakai mengubah honor periode lain.
 */
class UjianHonor extends BaseController
{
    private ?array $periode = null;

    /** POST ujian/(:segment)/honor/buat */
    public function buat(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $hasil = (new HonorDokumen())->buat($this->periode, (int) $this->request->getPost('salin_dari'));

        return $this->balas($slug, $hasil);
    }

    /** POST ujian/(:segment)/honor/dokumen — judul, tempat, tanggal, nama penanda tangan. */
    public function simpanDokumen(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null ? ['ok' => false, 'pesan' => 'Buat honor dulu.'] : (new HonorDokumen())->simpanDokumen((int) $dok['id'], (array) $this->request->getPost());

        return $this->balas($slug, $hasil, ! $hasil['ok']);
    }

    /** POST ujian/(:segment)/honor/penerima — tambah penerima terpilih. */
    public function tambahPenerima(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null ? ['ok' => false, 'pesan' => 'Buat honor dulu.'] : (new HonorDokumen())->tambahPenerima((int) $dok['id'], (array) $this->request->getPost('guru_ids'));

        return $this->balas($slug, $hasil, false, '#penerima');
    }

    /** POST ujian/(:segment)/honor/penerima-semua — tambah semua guru yang belum ada. */
    public function tambahSemua(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->balas($slug, ['ok' => false, 'pesan' => 'Buat honor dulu.']);
        }
        $h   = new HonorDokumen();
        $ids = array_map(static fn (array $g): int => (int) $g['id'], $h->calonPenerima((int) $dok['id']));

        return $this->balas($slug, $ids === [] ? ['ok' => true, 'pesan' => 'Semua guru sudah ada di daftar.'] : $h->tambahPenerima((int) $dok['id'], $ids));
    }

    /** POST ujian/(:segment)/honor/baris/(:num)/hapus */
    public function hapusBaris(string $slug = '', $barisId = 0)
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null ? ['ok' => false, 'pesan' => 'Buat honor dulu.'] : (new HonorDokumen())->hapusBaris((int) $dok['id'], (int) $barisId);

        return $this->balas($slug, $hasil);
    }

    /** POST ujian/(:segment)/honor/baris/(:num)/jabatan — JSON; ubah label jabatan penerima. */
    public function ubahJabatan(string $slug = '', $barisId = 0)
    {
        if (($salah = $this->siapkan($slug, true)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->json(['ok' => false, 'pesan' => 'Buat honor dulu.'], 422);
        }
        $hasil = (new HonorDokumen())->ubahBaris((int) $dok['id'], (int) $barisId, (string) $this->request->getPost('jabatan'));

        return $this->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** POST ujian/(:segment)/honor/baris/(:num)/pindah — JSON; pindahkan penerima ke nomor urut tertentu. */
    public function pindah(string $slug = '', $barisId = 0)
    {
        if (($salah = $this->siapkan($slug, true)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->json(['ok' => false, 'pesan' => 'Buat honor dulu.'], 422);
        }
        $hasil = (new HonorDokumen())->pindahKe((int) $dok['id'], (int) $barisId, (int) $this->request->getPost('posisi'));

        return $this->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** POST ujian/(:segment)/honor/nilai — JSON; simpan satu isian. */
    public function simpanNilai(string $slug = '')
    {
        if (($salah = $this->siapkan($slug, true)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->json(['ok' => false, 'pesan' => 'Buat honor dulu.'], 422);
        }
        $hasil = (new HonorDokumen())->simpanNilai(
            (int) $dok['id'],
            (int) $this->request->getPost('baris'),
            (int) $this->request->getPost('komponen'),
            $this->request->getPost('nilai')
        );

        return $this->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    /** POST ujian/(:segment)/honor/sinkron — perbarui komponen dokumen dari Pengaturan Honor. */
    public function sinkron(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null ? ['ok' => false, 'pesan' => 'Buat honor dulu.'] : (new HonorDokumen())->sinkronKomponen((int) $dok['id']);

        return $this->balas($slug, $hasil);
    }

    /** POST ujian/(:segment)/honor/hapus — hapus seluruh honor periode ini. */
    public function hapusDokumen(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null ? ['ok' => false, 'pesan' => 'Honor tidak ditemukan.'] : (new HonorDokumen())->hapusDokumen((int) $dok['id']);

        return $this->balas($slug, $hasil);
    }

    /** POST ujian/(:segment)/honor/status — draf / final / dikunci (membuka kunci wajib beralasan). */
    public function status(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok   = $this->dokumen();
        $hasil = $dok === null
            ? ['ok' => false, 'pesan' => 'Buat honor dulu.']
            : (new HonorDokumen())->ubahStatus((int) $dok['id'], (string) $this->request->getPost('ke'), (string) $this->request->getPost('alasan'));

        return $this->balas($slug, $hasil);
    }

    /** POST ujian/(:segment)/honor/hitung — isi otomatis Koreksi / Rapot / Pembuatan Soal. */
    public function hitung(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $dok = $this->dokumen();
        if ($dok === null) {
            return $this->balas($slug, ['ok' => false, 'pesan' => 'Buat honor dulu.']);
        }
        $hasil = (new HonorHitung())->terapkan((int) $dok['id'], array_map('strval', (array) $this->request->getPost('sumber')), (bool) $this->request->getPost('timpa'));

        return $this->balas($slug, $hasil);
    }

    // ----------------------------------------------------------------- impor Excel

    /** POST ujian/(:segment)/honor/impor/unggah — baca berkas, simpan hasil bacaan di sesi, tampilkan pratinjau. */
    public function imporUnggah(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $berkas = $this->request->getFile('berkas');
        if ($berkas === null || ! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->balas($slug, ['ok' => false, 'pesan' => 'Pilih berkas Excel (.xlsx) dulu.']);
        }
        if (strtolower((string) $berkas->getClientExtension()) !== 'xlsx' || $berkas->getSize() > HonorImpor::MAKS_BYTE) {
            return $this->balas($slug, ['ok' => false, 'pesan' => 'Berkas harus berformat .xlsx dan maksimal 2 MB.']);
        }
        try {
            $payload = (new HonorImpor())->baca($berkas->getTempName());
        } catch (\RuntimeException $e) {
            return $this->balas($slug, ['ok' => false, 'pesan' => $e->getMessage()]);
        }
        $token = bin2hex(random_bytes(8));
        session()->set('honor_impor', ['token' => $token, 'periode_id' => (int) $this->periode['id'], 'payload' => $payload, 'berkas' => mb_substr((string) $berkas->getClientName(), 0, 120), 'at' => time()]);

        return redirect()->to($this->urlHonor('/impor'));
    }

    /** GET ujian/(:segment)/honor/impor — pratinjau + pilihan tiap baris. */
    public function impor(string $slug = '')
    {
        if (($salah = $this->siapkanGet($slug)) !== null) {
            return $salah;
        }
        $sesi = $this->sesiImpor();
        if ($sesi === null) {
            return redirect()->to($this->urlHonor())->with('error', 'Data impor sudah kedaluwarsa atau belum ada. Unggah berkas Excel lagi.');
        }
        $lib     = new HonorImpor();
        $dokLib  = new HonorDokumen();
        $dok     = $dokLib->dokumenPeriode((int) $this->periode['id']);
        $komp    = $dok !== null
            ? (new HonorDokumen())->muat((int) $dok['id'])['komponen']
            : (new HonorPengaturan())->komponen(true, (string) $this->periode['jenis']);
        $payload = $sesi['payload'];
        $baris   = $lib->cocokkan($payload['baris']);

        return view('admin/ujian/honor_impor', [
            'title'    => 'Impor Honor dari Excel',
            'slug'     => UjianPeriodeModel::keSlug((string) $this->periode['jenis']),
            'periode'  => $this->periode,
            'label'    => (new UjianPeriodeModel())->label($this->periode),
            'token'    => $sesi['token'],
            'berkas'   => $sesi['berkas'],
            'payload'  => $payload,
            'baris'    => $baris,
            'analisis' => $lib->analisis($payload, $komp),
            'adaDok'   => $dok !== null,
            'jmlLama'  => $dok !== null ? (int) db_connect()->table('honor_baris')->where('dokumen_id', $dok['id'])->countAllResults() : 0,
            'kembali'  => $this->urlHonor(),
        ]);
    }

    /** POST ujian/(:segment)/honor/impor/terapkan */
    public function imporTerapkan(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $sesi = $this->sesiImpor();
        if ($sesi === null || ! hash_equals((string) $sesi['token'], (string) $this->request->getPost('token'))) {
            return redirect()->to($this->urlHonor())->with('error', 'Data impor sudah kedaluwarsa. Unggah berkas Excel lagi.');
        }
        $keputusan = [];
        foreach ((array) $this->request->getPost('aksi') as $i => $v) {
            $keputusan[(int) $i] = (string) $v;
        }
        $hasil = (new HonorImpor())->terapkan($this->periode, $sesi['payload'], $keputusan, (bool) $this->request->getPost('bersihkan'));
        if (! $hasil['ok']) {
            return redirect()->to($this->urlHonor('/impor'))->with('error', $hasil['pesan']);
        }
        session()->remove('honor_impor');
        $r = redirect()->to($this->urlHonor())->with('success', $hasil['pesan']);
        $p = array_merge($sesi['payload']['peringatan'] ?? [], $hasil['ringkas']['peringatan'] ?? []);

        return $p !== [] ? $r->with('honor_peringatan', array_slice($p, 0, 12)) : $r;
    }

    // ----------------------------------------------------------------- pembuat soal

    /** GET ujian/(:segment)/pembuat-soal — tugaskan pembuat soal per jadwal ujian. */
    public function pembuatSoal(string $slug = '')
    {
        if (($salah = $this->siapkanGet($slug)) !== null) {
            return $salah;
        }
        $jadwal = (new UjianJadwalModel())->untukPeriode((int) $this->periode['id'])->findAll();
        $db     = db_connect();
        $tugas  = [];
        if ($jadwal !== []) {
            foreach ($db->table('ujian_pembuat_soal s')->select('s.id, s.jadwal_id, g.nama')->join('guru g', 'g.id = s.guru_id AND g.deleted_at IS NULL')
                ->whereIn('s.jadwal_id', array_map('intval', array_column($jadwal, 'id')))->orderBy('g.nama')->get()->getResultArray() as $t) {
                $tugas[(int) $t['jadwal_id']][] = $t;
            }
        }

        return view('admin/ujian/pembuat_soal', [
            'title'    => 'Pembuat Soal Ujian',
            'slug'     => UjianPeriodeModel::keSlug((string) $this->periode['jenis']),
            'periode'  => $this->periode,
            'label'    => (new UjianPeriodeModel())->label($this->periode),
            'jadwal'   => $jadwal,
            'tugas'    => $tugas,
            'guruOpts' => (new GuruModel())->options(),
            'kembali'  => $this->urlHonor(),
        ]);
    }

    /** POST ujian/(:segment)/pembuat-soal — tambah satu pembuat soal pada satu jadwal. */
    public function simpanPembuatSoal(string $slug = '')
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $db       = db_connect();
        $jadwalId = (int) $this->request->getPost('jadwal_id');
        $guruId   = (int) $this->request->getPost('guru_id');
        $jadwal   = $jadwalId > 0 ? (new UjianJadwalModel())->find($jadwalId) : null;
        $guru     = $guruId > 0 ? $db->table('guru')->select('id, nama')->where('id', $guruId)->where('deleted_at', null)->get()->getRowArray() : null;
        $url      = $this->urlPembuatSoal();
        if ($jadwal === null || (int) $jadwal['periode_id'] !== (int) $this->periode['id']) {
            return redirect()->to($url)->with('error', 'Jadwal ujian tidak ditemukan.');
        }
        if ($guru === null) {
            return redirect()->to($url)->with('error', 'Pilih guru dulu.');
        }
        if ($db->table('ujian_pembuat_soal')->where('jadwal_id', $jadwalId)->where('guru_id', $guruId)->countAllResults() > 0) {
            return redirect()->to($url)->with('error', $guru['nama'] . ' sudah menjadi pembuat soal untuk jadwal ini.');
        }
        $db->table('ujian_pembuat_soal')->insert(['jadwal_id' => $jadwalId, 'guru_id' => $guruId, 'created_at' => date('Y-m-d H:i:s')]);
        (new AuditModel())->record('create', 'ujian_pembuat_soal', (int) $db->insertID(), 'Pembuat soal ' . $guru['nama'] . ' — jadwal ujian #' . $jadwalId);

        return redirect()->to($url)->with('success', $guru['nama'] . ' ditugaskan sebagai pembuat soal.');
    }

    /** POST ujian/(:segment)/pembuat-soal/(:num)/hapus */
    public function hapusPembuatSoal(string $slug = '', $id = 0)
    {
        if (($salah = $this->siapkan($slug)) !== null) {
            return $salah;
        }
        $db  = db_connect();
        $row = $db->table('ujian_pembuat_soal s')->select('s.id, g.nama')->join('ujian_jadwal j', 'j.id = s.jadwal_id')->join('guru g', 'g.id = s.guru_id', 'left')
            ->where('s.id', (int) $id)->where('j.periode_id', (int) $this->periode['id'])->get()->getRowArray();
        if ($row === null) {
            return redirect()->to($this->urlPembuatSoal())->with('error', 'Penugasan tidak ditemukan.');
        }
        $db->table('ujian_pembuat_soal')->where('id', (int) $id)->delete();
        (new AuditModel())->record('delete', 'ujian_pembuat_soal', (int) $id, 'Cabut pembuat soal ' . ($row['nama'] ?? '?'));

        return redirect()->to($this->urlPembuatSoal())->with('success', 'Penugasan dicabut.');
    }

    // -----------------------------------------------------------------

    private function urlHonor(string $akhiran = ''): string
    {
        return site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/honor' . $akhiran) . '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']);
    }

    private function urlPembuatSoal(): string
    {
        return site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/pembuat-soal') . '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']);
    }

    /** Data impor di sesi yang masih berlaku (periode cocok, umur ≤ 30 menit), atau null. */
    private function sesiImpor(): ?array
    {
        $s = session('honor_impor');
        if (! is_array($s) || (int) ($s['periode_id'] ?? 0) !== (int) $this->periode['id'] || time() - (int) ($s['at'] ?? 0) > 1800) {
            return null;
        }

        return $s;
    }

    /** Pagar untuk halaman GET: Admin, jenis dikenal, periode dari ?tp= (bawaan tahun berjalan). */
    protected function siapkanGet(string $slug): RedirectResponse|ResponseInterface|null
    {
        if ((string) (session('admin')['role'] ?? '') !== 'admin') {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Honor hanya bisa dikelola Admin.');
        }
        $jenis = UjianPeriodeModel::dariSlug($slug);
        $p     = $jenis !== null ? (new UjianPeriodeModel())->untukTahun($jenis, $this->request->getGet('tp')) : null;
        if ($p === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $this->periode = $p;

        return null;
    }

    /**
     * Pagar: harus Admin, jenis ujian dikenal, periode ada dan cocok dengan alamat. Mengisi $this->periode.
     * Mengembalikan balasan penolakan (JSON bila $json), atau null bila lolos.
     */
    protected function siapkan(string $slug, bool $json = false): RedirectResponse|ResponseInterface|null
    {
        if ((string) (session('admin')['role'] ?? '') !== 'admin') {
            return $json
                ? $this->json(['ok' => false, 'pesan' => 'Honor hanya bisa dikelola Admin.'], 403)
                : redirect()->to(site_url('admin/dashboard'))->with('error', 'Honor hanya bisa dikelola Admin.');
        }
        $jenis = UjianPeriodeModel::dariSlug($slug);
        $p     = (int) $this->request->getPost('periode_id') > 0 ? (new UjianPeriodeModel())->find((int) $this->request->getPost('periode_id')) : null;
        if ($jenis === null || $p === null || $p['jenis'] !== $jenis) {
            return $json
                ? $this->json(['ok' => false, 'pesan' => 'Periode ujian tidak ditemukan. Muat ulang halaman.'], 422)
                : redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $this->periode = $p;

        return null;
    }

    private function dokumen(): ?array
    {
        return (new HonorDokumen())->dokumenPeriode((int) $this->periode['id']);
    }

    private function balas(string $slug, array $hasil, bool $simpanIsian = false, string $jangkar = ''): RedirectResponse
    {
        $url = site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/honor') . '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']) . $jangkar;
        $r   = redirect()->to($url)->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);

        return $simpanIsian ? $r->withInput() : $r;
    }

    /** Balasan JSON tanpa cache; token CSRF baru disertakan (token berganti tiap POST). */
    private function json(array $isi, int $kode = 200): ResponseInterface
    {
        $isi['csrf'] = csrf_hash();

        return $this->response->setStatusCode($kode)->setHeader('Cache-Control', 'no-store')->setJSON($isi);
    }
}
