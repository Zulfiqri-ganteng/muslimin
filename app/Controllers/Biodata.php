<?php

namespace App\Controllers;

use App\Libraries\BiodataForm;
use App\Libraries\LoginThrottle;
use App\Models\BiodataIsianModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Form isian biodata siswa — PUBLIK, tanpa login.
 *
 * Alur siswa: pilih kelas → pilih nama → isi biodata → kirim ke kotak masuk
 * admin (tabel biodata_isian). Data di Master Siswa BARU berubah setelah
 * admin menyetujui isian.
 *
 * Pengaman:
 *   - satu siswa satu isian (UNIQUE siswa_id) + isian terkunci setelah kirim;
 *   - NISN tidak boleh dipakai dua siswa (dicek di sini + UNIQUE di tabel);
 *   - isian yang dikembalikan admin ("perbaikan") hanya bisa dibuka ulang
 *     dengan NISN/tanggal lahir yang dulu diisi, dibatasi LoginThrottle,
 *     supaya orang lain tidak bisa mengintip data teman;
 *   - data pribadi siswa TIDAK pernah dikirim ke peramban kecuali lewat
 *     jalur buka-ulang tersebut (daftar nama hanya berisi nama & JK);
 *   - jebakan bot (kolom tersembunyi `website`).
 */
class Biodata extends BaseController
{
    /** Kunci sesi: [siswa_id => waktu] isian perbaikan yang sudah dibuka. */
    private const SESI_BUKA = 'biodata_buka';

    /** Berapa lama izin buka-ulang berlaku (detik). */
    private const BUKA_TTL = 3600;

    private array $setting;

    public function __construct()
    {
        $this->setting = (new SettingModel())->get();
    }

    /** Halaman form (beranda subdomain & /biodata). */
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store');

        if (! BiodataIsianModel::formTerbuka($this->setting)) {
            return view('biodata/tutup', ['setting' => $this->setting]);
        }

        return view('biodata/form', [
            'setting' => $this->setting,
            'kelas'   => $this->daftarKelas(),
        ]);
    }

    /** GET biodata/siswa?kelas_id= — daftar nama satu kelas + status isiannya. */
    public function siswa(): ResponseInterface
    {
        if (! BiodataIsianModel::formTerbuka($this->setting)) {
            return $this->json(['ok' => false, 'message' => 'Pengisian biodata sedang ditutup.'], 403);
        }

        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->json(['ok' => false, 'message' => 'Pilih kelas terlebih dahulu.'], 422);
        }

        $rows = (new SiswaModel())
            ->select('siswa.id, siswa.nama, siswa.jenis_kelamin, b.status AS isian')
            ->join('biodata_isian b', 'b.siswa_id = siswa.id', 'left')
            ->where('siswa.kelas_id', $kelasId)
            ->where('siswa.status', 'aktif')
            ->orderBy('siswa.nama', 'ASC')
            ->findAll();

        $data = array_map(static fn (array $r) => [
            'id'     => (int) $r['id'],
            'nama'   => $r['nama'],
            'jk'     => $r['jenis_kelamin'],
            'status' => $r['isian'] ?? 'belum',
        ], $rows);

        return $this->json(['ok' => true, 'data' => $data]);
    }

    /**
     * POST biodata/buka — buka kembali isian yang dikembalikan admin.
     * Cukup SALAH SATU cocok: NISN atau tanggal lahir yang dulu diisi
     * (salah satunya bisa jadi justru yang diminta diperbaiki admin).
     */
    public function buka(): ResponseInterface
    {
        if (! BiodataIsianModel::formTerbuka($this->setting)) {
            return $this->json(['ok' => false, 'message' => 'Pengisian biodata sedang ditutup.'], 403);
        }

        $id    = (int) $this->request->getPost('siswa_id');
        $nisn  = preg_replace('/\D/', '', (string) $this->request->getPost('nisn'));
        $tgl   = trim((string) $this->request->getPost('tanggal_lahir'));
        $siswa = $this->siswaAktif($id);
        if ($siswa === null) {
            return $this->json(['ok' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $isian = (new BiodataIsianModel())->milikSiswa($id);
        if ($isian === null || $isian['status'] !== 'perbaikan') {
            return $this->json(['ok' => false, 'message' => 'Isian ini tidak sedang dibuka untuk perbaikan.'], 409);
        }
        if ($nisn === '' && $tgl === '') {
            return $this->json(['ok' => false, 'message' => 'Isi NISN atau tanggal lahirmu.'], 422);
        }

        $throttle = new LoginThrottle();
        $kunci    = 'biodata:' . $id;
        $ip       = $this->request->getIPAddress();
        $sisa     = $throttle->retryAfter($kunci, $ip);
        if ($sisa > 0) {
            return $this->json([
                'ok'      => false,
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($sisa / 60) . ' menit.',
            ], 429);
        }

        $lama  = BiodataIsianModel::decode($isian);
        $cocok = ($nisn !== '' && $nisn === (string) ($lama['nisn'] ?? ''))
            || ($tgl !== '' && $tgl === (string) ($lama['tanggal_lahir'] ?? ''));
        if (! $cocok) {
            $throttle->hit($kunci, $ip, 'biodata');

            return $this->json(['ok' => false, 'message' => 'NISN atau tanggal lahir tidak cocok dengan isianmu sebelumnya.'], 422);
        }

        $throttle->clear($kunci, $ip);
        $buka      = (array) session(self::SESI_BUKA);
        $buka[$id] = time();
        session()->set(self::SESI_BUKA, $buka);

        return $this->json([
            'ok'      => true,
            'data'    => $lama,
            'catatan' => $isian['catatan_admin'],
        ]);
    }

    /** POST biodata/kirim — simpan isian ke kotak masuk. */
    public function kirim(): ResponseInterface
    {
        if (! BiodataIsianModel::formTerbuka($this->setting)) {
            return $this->json(['ok' => false, 'message' => 'Maaf, pengisian biodata sudah ditutup.'], 403);
        }

        // Jebakan bot: manusia tidak melihat kolom ini. Pura-pura berhasil.
        if (trim((string) $this->request->getPost('website')) !== '') {
            return $this->json(['ok' => true, 'redirect' => site_url('biodata/selesai')]);
        }

        $id    = (int) $this->request->getPost('siswa_id');
        $siswa = $this->siswaAktif($id);
        if ($siswa === null) {
            return $this->json(['ok' => false, 'message' => 'Data siswa tidak ditemukan. Muat ulang halaman lalu pilih namamu lagi.'], 404);
        }

        $model = new BiodataIsianModel();
        $isian = $model->milikSiswa($id);
        if ($isian !== null && $isian['status'] !== 'perbaikan') {
            return $this->json(['ok' => false, 'message' => $this->pesanTerkunci($isian['status'])], 409);
        }
        if ($isian !== null && ! $this->sudahDibuka($id)) {
            return $this->json(['ok' => false, 'message' => 'Buka isianmu dulu dengan NISN atau tanggal lahir.'], 403);
        }

        [$data, $galat] = BiodataForm::proses($this->request->getPost());
        $galat += $this->cekKeunikan($data, $id);
        if ($galat !== []) {
            return $this->json(['ok' => false, 'message' => 'Masih ada isian yang perlu diperbaiki.', 'errors' => $galat], 422);
        }

        $baris = [
            'nisn'       => $data['nisn'],
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'     => 'menunggu',
            'ip_address' => $this->request->getIPAddress(),
        ];

        if ($isian === null) {
            $ok = $model->insert($baris + ['siswa_id' => $id]) !== false;
            $no = (int) $model->getInsertID();
        } else {
            $ok = $model->update($isian['id'], $baris + ['kirim_ke' => (int) $isian['kirim_ke'] + 1]);
            $no = (int) $isian['id'];
        }

        if (! $ok) {
            // Hampir pasti tabrakan UNIQUE: isian ganda dari dua HP sekaligus,
            // atau NISN yang sama dikirim siswa lain di detik yang sama.
            if ($isian === null && $model->milikSiswa($id) !== null) {
                return $this->json(['ok' => false, 'message' => $this->pesanTerkunci('menunggu')], 409);
            }

            return $this->json([
                'ok'      => false,
                'message' => 'Isian gagal disimpan. Periksa NISN lalu coba kirim lagi.',
                'errors'  => ['nisn' => 'NISN ini sudah dipakai di isian lain. Periksa kembali NISN-mu.'],
            ], 422);
        }

        $buka = (array) session(self::SESI_BUKA);
        unset($buka[$id]);
        session()->set(self::SESI_BUKA, $buka);
        session()->setFlashdata('biodata_selesai', [
            'nama'   => $data['nama'],
            'kelas'  => $siswa['nama_kelas'],
            'no'     => $no,
            'revisi' => $isian !== null,
        ]);

        return $this->json(['ok' => true, 'redirect' => site_url('biodata/selesai')]);
    }

    /** Halaman terima kasih setelah kirim. */
    public function selesai()
    {
        $info = session('biodata_selesai');
        if (! is_array($info)) {
            return redirect()->to($this->urlForm());
        }

        return view('biodata/selesai', [
            'setting' => $this->setting,
            'info'    => $info,
            'urlForm' => $this->urlForm(),
        ]);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /**
     * Kelas yang punya siswa aktif, dikelompokkan per tingkat dan diurutkan
     * alami ("X TKJ 2" sebelum "X TKJ 10").
     *
     * @return array<string, list<array{id:int, nama:string}>>
     */
    private function daftarKelas(): array
    {
        return master_cache('siswa', 'biodata_kelas|v1', 1800, static function () {
            $rows = db_connect()->table('kelas')
                ->select('kelas.id, kelas.nama_kelas, kelas.tingkat')
                ->join('siswa', 'siswa.kelas_id = kelas.id')
                ->where('kelas.deleted_at', null)
                ->where('siswa.deleted_at', null)
                ->where('siswa.status', 'aktif')
                ->groupBy('kelas.id')
                ->get()->getResultArray();

            $urutTingkat = ['X' => 0, 'XI' => 1, 'XII' => 2];
            usort($rows, static fn ($a, $b) => (($urutTingkat[$a['tingkat']] ?? 9) <=> ($urutTingkat[$b['tingkat']] ?? 9))
                ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));

            $out = [];
            foreach ($rows as $r) {
                $out[$r['tingkat']][] = ['id' => (int) $r['id'], 'nama' => $r['nama_kelas']];
            }

            return $out;
        });
    }

    /** Siswa aktif (belum dihapus) beserta nama kelasnya, atau null. */
    private function siswaAktif(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return (new SiswaModel())->withRelations()
            ->where('siswa.id', $id)
            ->where('siswa.status', 'aktif')
            ->first();
    }

    /**
     * NISN/NIS tidak boleh milik siswa lain — baik yang sudah tercatat di
     * Master Siswa maupun yang masih menunggu di kotak masuk. Pesan sengaja
     * TIDAK menyebut nama pemiliknya.
     *
     * @return array<string, string>
     */
    private function cekKeunikan(array $data, int $siswaId): array
    {
        $galat = [];
        $db    = db_connect();

        if (! empty($data['nisn'])) {
            $diSiswa = $db->table('siswa')->where('nisn', $data['nisn'])
                ->where('id !=', $siswaId)->where('deleted_at', null)->countAllResults();
            $diIsian = $db->table('biodata_isian')->where('nisn', $data['nisn'])
                ->where('siswa_id !=', $siswaId)->countAllResults();
            if ($diSiswa + $diIsian > 0) {
                $galat['nisn'] = 'NISN ini sudah terdaftar untuk siswa lain. Periksa kembali NISN-mu.';
            }
        }

        if (! empty($data['nis'])) {
            $diSiswa = $db->table('siswa')->where('nis', $data['nis'])
                ->where('id !=', $siswaId)->where('deleted_at', null)->countAllResults();
            if ($diSiswa > 0) {
                $galat['nis'] = 'NIS ini sudah terdaftar untuk siswa lain. Periksa kembali NIS-mu.';
            }
        }

        return $galat;
    }

    private function sudahDibuka(int $id): bool
    {
        $waktu = (int) (((array) session(self::SESI_BUKA))[$id] ?? 0);

        return $waktu > 0 && (time() - $waktu) <= self::BUKA_TTL;
    }

    private function pesanTerkunci(string $status): string
    {
        return $status === 'disetujui'
            ? 'Biodatamu sudah diverifikasi sekolah. Jika ada perubahan, hubungi wali kelas atau operator sekolah.'
            : 'Kamu sudah mengirim biodata dan sedang diperiksa sekolah. Jika ada yang salah, hubungi wali kelas atau operator sekolah.';
    }

    /** Alamat form: beranda bila dibuka lewat subdomain, /biodata bila lewat domain utama. */
    private function urlForm(): string
    {
        $host = strtolower(explode(':', (string) $this->request->getServer('HTTP_HOST'))[0]);

        return site_url($host === strtolower(config('Biodata')->host) ? '/' : 'biodata');
    }

    private function json(array $body, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($body);
    }
}
