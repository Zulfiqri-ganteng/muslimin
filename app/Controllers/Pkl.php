<?php

namespace App\Controllers;

use App\Libraries\IsianBantu;
use App\Libraries\LoginThrottle;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\PklPerusahaanModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Form pengajuan PKL / Prakerin siswa — PUBLIK, tanpa login. Rancangan: docs/DESAIN-PKL.md.
 *
 * Alur siswa: pilih kelas → pilih nama → data perusahaan → teman satu tempat
 * (opsional, maks 5 siswa termasuk pengaju; HP tiap siswa wajib) → HP pengaju →
 * periksa & kirim. Tanggal PKL & tanggal lahir TIDAK ditanyakan. Ajuan masuk ke
 * kotak masuk staf (pkl_pengajuan, status `menunggu`) dan diputuskan Waka Hubin
 * paling lambat N hari (Pengaturan, bawaan 5) sejak dikirim.
 *
 * Pengaman:
 *   - satu siswa satu ajuan AKTIF — dijaga 3 lapis: daftar nama menandai & mengunci
 *     siswa yang sudah aktif, validasi di sini, dan UNIQUE pkl_anggota.siswa_aktif.
 *     Selama ajuan menunggu keputusan Hubin siswa TIDAK boleh mengajukan ulang (pesan
 *     menyebut nomor bukti, jam kirim, dan batas keputusan);
 *   - ajuan terkunci setelah kirim; hanya yang dikembalikan staf (`perbaikan`) bisa
 *     dibuka lagi, oleh PENGAJU, dengan nomor HP yang ia isi (dibatasi LoginThrottle);
 *   - daftar nama hanya memuat status — tak pernah data perusahaan atau ajuan orang lain;
 *   - form default TUTUP; buka/tutup & batas waktu diatur di Pengaturan PKL;
 *   - jebakan bot (kolom tersembunyi `website`) + batas kiriman per IP.
 */
class Pkl extends BaseController
{
    /** Kunci sesi: [pengajuan_id => waktu] ajuan perbaikan yang sudah dibuka. */
    private const SESI_BUKA = 'pkl_buka';

    /** Berapa lama izin buka-ulang berlaku (detik). */
    private const BUKA_TTL = 3600;

    /** Banjir kiriman: maksimum ajuan dari satu IP dalam JENDELA_IP detik. Longgar — satu kelas bisa memakai satu WiFi. */
    private const BATAS_IP   = 100;
    private const JENDELA_IP = 600;

    private array $setting;
    private array $p;

    public function __construct()
    {
        $this->setting = (new SettingModel())->get();
        $this->p       = (new PklPengaturanModel())->ambil();
    }

    /** Halaman form (beranda subdomain & /pkl). */
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store');

        $alasan = PklPengaturanModel::alasanTutup($this->p);
        if ($alasan !== null) {
            return view('pkl/tutup', ['setting' => $this->setting, 'alasan' => $alasan]);
        }

        return view('pkl/form', [
            'setting'    => $this->setting,
            'p'          => $this->p,
            'kelas'      => $this->daftarKelas(),
            'batasHari'  => PklPengaturanModel::batasHari($this->p),
            'maksSiswa'  => PklPengaturanModel::maksSiswa($this->p),
        ]);
    }

    /** GET pkl/siswa?kelas_id= — daftar nama satu kelas + status PKL-nya. */
    public function siswa(): ResponseInterface
    {
        if (! PklPengaturanModel::formTerbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Pengisian PKL sedang ditutup.'], 403);
        }

        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->json(['ok' => false, 'message' => 'Pilih kelas terlebih dahulu.'], 422);
        }
        if ($this->kelasBoleh($kelasId) === null) {
            return $this->json(['ok' => false, 'message' => 'Kelas ini belum termasuk yang boleh mengajukan PKL.'], 422);
        }

        $hari = PklPengaturanModel::batasHari($this->p);
        $data = array_map(static fn (array $r) => [
            'id'     => (int) $r['id'],
            'nama'   => $r['nama'],
            'jk'     => $r['jenis_kelamin'],
            'status' => $r['aktif'] ?? ((int) $r['pernah_ditolak'] === 1 ? 'ditolak' : 'belum'),
            'peran'  => $r['aktif'] !== null ? $r['peran'] : null,
            // Hanya tanggal (bukan data ajuan): kapan keputusan Waka Hubin paling lambat keluar.
            'batas'  => $r['aktif'] === 'menunggu' ? substr((string) PklPengajuanModel::batasKeputusan($r['diajukan_at'] ?? null, $hari), 0, 10) : null,
        ], (new PklPengajuanModel())->daftarSiswaKelas($kelasId));

        return $this->json(['ok' => true, 'data' => $data]);
    }

    /** GET pkl/perusahaan?q= — saran nama perusahaan yang pernah disetujui. */
    public function perusahaan(): ResponseInterface
    {
        if (! PklPengaturanModel::formTerbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Pengisian PKL sedang ditutup.'], 403);
        }

        $q    = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $data = mb_strlen($q) >= 2 ? (new PklPerusahaanModel())->saran($q) : [];

        return $this->json(['ok' => true, 'data' => array_map(static fn (array $r) => [
            'id'             => (int) $r['id'],
            'nama'           => $r['nama'],
            'alamat'         => $r['alamat'],
            'kota'           => $r['kota'],
            'telepon'        => $r['telepon'],
            'kontak_nama'    => $r['kontak_nama'],
            'kontak_jabatan' => $r['kontak_jabatan'],
        ], $data)]);
    }

    /**
     * POST pkl/buka — buka kembali ajuan yang dikembalikan staf. Hanya PENGAJU,
     * dengan nomor HP yang dulu ia isi di ajuan ATAU nomor HP di Master Siswa
     * (jaga-jaga salah ketik waktu pertama mengisi).
     */
    public function buka(): ResponseInterface
    {
        if (! PklPengaturanModel::formTerbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Pengisian PKL sedang ditutup.'], 403);
        }

        $id    = (int) $this->request->getPost('siswa_id');
        $hpIn  = trim((string) $this->request->getPost('hp'));
        $siswa = $this->siswaBoleh($id);
        if ($siswa === null) {
            return $this->json(['ok' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $model = new PklPengajuanModel();
        $milik = $model->aktifMilik($id);
        if ($milik === null || $milik['status'] !== 'perbaikan') {
            return $this->json(['ok' => false, 'message' => 'Ajuan PKL-mu tidak sedang dibuka untuk perbaikan.'], 409);
        }
        if ($milik['peran'] !== 'pengaju') {
            return $this->json(['ok' => false, 'message' => 'Ajuan ini diajukan oleh temanmu. Hanya yang mengajukan yang bisa memperbaikinya — minta dia membukanya, atau hubungi operator sekolah.'], 409);
        }
        if ($hpIn === '' || ! IsianBantu::teleponMurni($hpIn) || ! IsianBantu::hpSah(IsianBantu::telepon($hpIn))) {
            return $this->json(['ok' => false, 'message' => 'Isi nomor HP-mu dengan benar (contoh: 081234567890).'], 422);
        }

        $throttle = new LoginThrottle();
        $kunci    = 'pkl:' . $id;
        $ip       = $this->request->getIPAddress();
        $sisa     = $throttle->retryAfter($kunci, $ip);
        if ($sisa > 0) {
            return $this->json([
                'ok'      => false,
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($sisa / 60) . ' menit.',
            ], 429);
        }

        $hp    = IsianBantu::telepon($hpIn);
        $cocok = ($hp !== '' && $hp === IsianBantu::telepon((string) ($milik['hp_anggota'] ?? '')))
            || ($hp !== '' && $hp === IsianBantu::telepon((string) ($siswa['no_hp'] ?? '')));
        if (! $cocok) {
            $throttle->hit($kunci, $ip, 'pkl');

            return $this->json(['ok' => false, 'message' => 'Nomor HP tidak cocok dengan yang kamu isi sebelumnya.'], 422);
        }

        $throttle->clear($kunci, $ip);
        $buka               = (array) session(self::SESI_BUKA);
        $buka[$milik['id']] = time();
        session()->set(self::SESI_BUKA, $buka);

        $anggota = $model->anggotaDetail((int) $milik['id']);
        $pengaju = $anggota[0] ?? [];

        return $this->json([
            'ok'      => true,
            'catatan' => $milik['catatan_staf'],
            'data'    => [
                'ajuan_id'           => (int) $milik['id'],
                'perusahaan_nama'    => $milik['perusahaan_nama'],
                'perusahaan_alamat'  => $milik['perusahaan_alamat'],
                'perusahaan_kota'    => $milik['perusahaan_kota'],
                'perusahaan_telepon' => $milik['perusahaan_telepon'],
                'kontak_nama'        => $milik['kontak_nama'],
                'kontak_jabatan'     => $milik['kontak_jabatan'],
                'hp'                 => $pengaju['hp'] ?? null,
                'teman'              => array_values(array_map(static fn (array $a) => [
                    'id'    => (int) $a['siswa_id'],
                    'nama'  => $a['nama'],
                    'kelas' => $a['nama_kelas'],
                    'hp'    => $a['hp'] ?? null,
                ], array_filter($anggota, static fn (array $a) => $a['peran'] === 'teman'))),
            ],
        ]);
    }

    /** POST pkl/kirim — simpan ajuan baru, atau kirim ulang ajuan perbaikan (ajuan_id terisi). */
    public function kirim(): ResponseInterface
    {
        if (! PklPengaturanModel::formTerbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Maaf, pengisian PKL sudah ditutup.'], 403);
        }

        // Jebakan bot: manusia tidak melihat kolom ini. Pura-pura berhasil.
        if (trim((string) $this->request->getPost('website')) !== '') {
            return $this->json(['ok' => true, 'redirect' => site_url('pkl/selesai')]);
        }

        $model = new PklPengajuanModel();
        $ip    = $this->request->getIPAddress();
        if ($model->kirimanDariIp($ip, self::JENDELA_IP) >= self::BATAS_IP) {
            return $this->json(['ok' => false, 'message' => 'Terlalu banyak pengiriman dari jaringan ini. Tunggu beberapa menit lalu coba lagi — isianmu tidak hilang.'], 429);
        }

        $id    = (int) $this->request->getPost('siswa_id');
        $siswa = $this->siswaBoleh($id);
        if ($siswa === null) {
            return $this->json(['ok' => false, 'message' => 'Data siswa tidak ditemukan atau kelasmu belum boleh mengajukan PKL. Muat ulang halaman lalu pilih namamu lagi.'], 404);
        }

        $ajuanId = (int) $this->request->getPost('ajuan_id');
        $milik   = $model->aktifMilik($id);
        if ($ajuanId > 0) {
            if ($milik === null || (int) $milik['id'] !== $ajuanId || $milik['status'] !== 'perbaikan' || $milik['peran'] !== 'pengaju') {
                return $this->json(['ok' => false, 'message' => 'Ajuan ini tidak sedang dibuka untuk perbaikan.'], 409);
            }
            if (! $this->sudahDibuka($ajuanId)) {
                return $this->json(['ok' => false, 'message' => 'Buka ajuanmu dulu dengan nomor HP.'], 403);
            }
        } elseif ($milik !== null) {
            return $this->json(['ok' => false, 'message' => $this->pesanTerkunci($milik)], 409);
        }

        [$data, $galat] = PklForm::proses($this->request->getPost(), $this->p);
        [$teman, $galatTeman] = $this->periksaTeman($data['teman'], $id, $ajuanId > 0 ? $ajuanId : null, $data['teman_hp'] ?? []);
        $galat += $galatTeman;
        if ($galat !== []) {
            return $this->json(['ok' => false, 'message' => 'Masih ada isian yang perlu diperbaiki.', 'errors' => $galat], 422);
        }

        $anggota = array_merge([[
            'siswa_id' => $id,
            'kelas_id' => (int) $siswa['kelas_id'] ?: null,
            'peran'    => 'pengaju',
        ]], $teman);
        $konteks = [
            'oleh'         => 'Siswa: ' . $siswa['nama'],
            'ip'           => $ip,
            'sumber'       => 'siswa',
            'tahun_ajaran' => $this->setting['academic_year'] ?? null,
        ];

        $ajuan = new PklAjuan();
        $hasil = $ajuanId > 0
            ? $ajuan->kirimUlang($ajuanId, $data, $anggota, $konteks)
            : $ajuan->kirimBaru($data, $anggota, $konteks);

        if (! $hasil['ok']) {
            return $this->tolakHasil($hasil, $id, $model);
        }

        $buka = (array) session(self::SESI_BUKA);
        unset($buka[$hasil['id']]);
        session()->set(self::SESI_BUKA, $buka);
        session()->setFlashdata('pkl_selesai', [
            'nama'       => $siswa['nama'],
            'kelas'      => $siswa['nama_kelas'],
            'no'         => PklPengajuanModel::kode((int) $hasil['id']),
            'revisi'     => $ajuanId > 0,
            'perusahaan' => $data['perusahaan_nama'],
            'jumlah'     => count($anggota),
            'batas'      => substr((string) PklPengajuanModel::batasKeputusan(date('Y-m-d H:i:s'), PklPengaturanModel::batasHari($this->p)), 0, 10),
            'hari'       => PklPengaturanModel::batasHari($this->p),
        ]);

        return $this->json(['ok' => true, 'redirect' => site_url('pkl/selesai')]);
    }

    /** Halaman terima kasih setelah kirim. */
    public function selesai()
    {
        $info = session('pkl_selesai');
        if (! is_array($info)) {
            return redirect()->to($this->urlForm());
        }

        return view('pkl/selesai', [
            'setting' => $this->setting,
            'info'    => $info,
            'urlForm' => $this->urlForm(),
        ]);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /**
     * Kelas yang boleh mengajukan PKL (menurut Pengaturan) dan punya siswa aktif,
     * dikelompokkan per tingkat dan diurutkan alami ("XI TKJT 2" sebelum "XI TKJT 10").
     *
     * @return array<string, list<array{id:int, nama:string}>>
     */
    private function daftarKelas(): array
    {
        $boleh = PklPengaturanModel::tingkatBoleh($this->p);
        if ($boleh === []) {
            return [];
        }

        return master_cache('siswa', 'pkl_kelas|v1|' . implode(',', $boleh), 1800, static function () use ($boleh) {
            $rows = db_connect()->table('kelas')
                ->select('kelas.id, kelas.nama_kelas, kelas.tingkat')
                ->join('siswa', 'siswa.kelas_id = kelas.id')
                ->where('kelas.deleted_at', null)
                ->where('siswa.deleted_at', null)
                ->where('siswa.status', 'aktif')
                ->whereIn('kelas.tingkat', $boleh)
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

    /** Kelas bila tingkatnya termasuk yang boleh mengajukan, selain itu null. */
    private function kelasBoleh(int $kelasId): ?array
    {
        $k = db_connect()->table('kelas')->select('id, nama_kelas, tingkat')
            ->where('id', $kelasId)->where('deleted_at', null)->get()->getRowArray();

        return ($k !== null && in_array($k['tingkat'], PklPengaturanModel::tingkatBoleh($this->p), true)) ? $k : null;
    }

    /** Siswa aktif (belum dihapus) di kelas yang boleh mengajukan, lengkap dengan nama kelasnya, atau null. */
    private function siswaBoleh(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $s = (new SiswaModel())->withRelations()
            ->where('siswa.id', $id)
            ->where('siswa.status', 'aktif')
            ->first();

        return ($s !== null && in_array($s['tingkat'] ?? '', PklPengaturanModel::tingkatBoleh($this->p), true)) ? $s : null;
    }

    /**
     * Periksa pilihan teman: ada, aktif, kelasnya boleh PKL, dan belum punya ajuan
     * aktif lain (ajuan yang sedang diperbaiki ini sendiri tentu tidak dihitung).
     * Pesan menyebut NAMA yang dipilih sendiri oleh pengaju — tak membuka data lain.
     *
     * @param list<int>          $ids
     * @param array<int, string> $hpTeman id siswa → HP yang sudah dirapikan PklForm
     *
     * @return array{0: list<array{siswa_id:int, kelas_id:?int, peran:string, hp:?string}>, 1: array<string, string>}
     */
    private function periksaTeman(array $ids, int $pengajuId, ?int $ajuanSendiri, array $hpTeman = []): array
    {
        if ($ids === []) {
            return [[], []];
        }

        $info    = (new PklPengajuanModel())->siswaUntukDipilih($ids);
        $boleh   = PklPengaturanModel::tingkatBoleh($this->p);
        $baris   = [];
        $masalah = [];
        foreach ($ids as $tid) {
            $s = $info[$tid] ?? null;
            if ($tid === $pengajuId) {
                $masalah[] = 'kamu sendiri tidak perlu dipilih sebagai teman';
            } elseif ($s === null || $s['status'] !== 'aktif') {
                $masalah[] = 'ada siswa yang tidak ditemukan (muat ulang halaman)';
            } elseif (! in_array($s['tingkat'] ?? '', $boleh, true)) {
                $masalah[] = $s['nama'] . ' (kelasnya belum boleh mengajukan PKL)';
            } elseif ($s['aktif_di'] !== null && (int) $s['aktif_di'] !== (int) $ajuanSendiri) {
                $masalah[] = $s['nama'] . ' (sudah punya ajuan PKL)';
            } else {
                $baris[] = ['siswa_id' => $tid, 'kelas_id' => (int) $s['kelas_id'] ?: null, 'peran' => 'teman', 'hp' => $hpTeman[$tid] ?? null];
            }
        }

        return $masalah === []
            ? [$baris, []]
            : [[], ['teman' => 'Teman berikut tidak bisa ditambahkan: ' . implode('; ', $masalah) . '.']];
    }

    /** Ubah hasil gagal PklAjuan menjadi balasan JSON yang ramah. */
    private function tolakHasil(array $hasil, int $pengajuId, PklPengajuanModel $model): ResponseInterface
    {
        switch ($hasil['kode'] ?? 'galat') {
            case 'bentrok':
                $ids = $hasil['siswa_ids'] ?? [];
                // Pengaju sendiri sudah aktif = ia (atau HP-nya yang lain) baru saja mengirim.
                if (in_array($pengajuId, $ids, true)) {
                    $milik = $model->aktifMilik($pengajuId);

                    return $this->json(['ok' => false, 'message' => $this->pesanTerkunci($milik ?? ['status' => 'menunggu'])], 409);
                }

                return $this->json([
                    'ok'      => false,
                    'message' => 'Masih ada isian yang perlu diperbaiki.',
                    'errors'  => ['teman' => 'Ada teman yang baru saja dipakai ajuan lain. Muat ulang daftar nama lalu pilih ulang temanmu.'],
                ], 409);

            case 'status':
            case 'pengaju_beda':
            case 'tidak_ada':
                return $this->json(['ok' => false, 'message' => 'Ajuan ini sudah tidak dalam status perbaikan (mungkin sedang diperiksa sekolah). Muat ulang halaman.'], 409);

            default:
                return $this->json(['ok' => false, 'message' => 'Ajuan gagal disimpan. Coba tekan Kirim sekali lagi — isianmu tidak hilang.'], 500);
        }
    }

    private function sudahDibuka(int $ajuanId): bool
    {
        $waktu = (int) (((array) session(self::SESI_BUKA))[$ajuanId] ?? 0);

        return $waktu > 0 && (time() - $waktu) <= self::BUKA_TTL;
    }

    /**
     * Pesan saat siswa mencoba mengajukan padahal ajuannya masih aktif. Menyebut nomor bukti, jam kirim,
     * dan batas keputusan Waka Hubin supaya siswa tahu harus menunggu — bukan mengajukan ulang.
     *
     * @param array<string, mixed> $milik baris ajuan aktif (PklPengajuanModel::aktifMilik)
     */
    private function pesanTerkunci(array $milik): string
    {
        $kode = isset($milik['id']) ? PklPengajuanModel::kode((int) $milik['id']) : 'ajuanmu';
        if (($milik['status'] ?? '') === 'disetujui') {
            return 'Ajuan PKL-mu (' . $kode . ') sudah DISETUJUI Waka Hubin. Jika ada perubahan, hubungi operator sekolah atau Waka Hubin.';
        }
        if (($milik['status'] ?? '') === 'perbaikan') {
            return 'Ajuan PKL-mu (' . $kode . ') sedang dikembalikan untuk diperbaiki. Pilih namamu di langkah 1 lalu buka ajuanmu — jangan membuat ajuan baru.';
        }

        $hari  = PklPengaturanModel::batasHari($this->p);
        $kirim = $milik['diajukan_at'] ?? ($milik['created_at'] ?? null);
        $batas = PklPengajuanModel::batasKeputusan($kirim, $hari);

        return 'Kamu sudah mengajukan PKL (' . $kode . ')'
            . ($kirim ? ' pada ' . IsianBantu::tanggalIndo(substr((string) $kirim, 0, 10)) : '')
            . ' dan ajuanmu sedang menunggu keputusan Waka Hubin'
            . ($batas ? ' (paling lambat ' . IsianBantu::tanggalIndo(substr($batas, 0, 10)) . ')' : '')
            . '. Jangan mengajukan ulang — tunggu keputusannya. Bila lewat batas belum ada kabar, hubungi operator sekolah atau Waka Hubin.';
    }

    /** Alamat form: beranda bila dibuka lewat subdomain, /pkl bila lewat domain utama. */
    private function urlForm(): string
    {
        $host = strtolower(explode(':', (string) $this->request->getServer('HTTP_HOST'))[0]);

        return site_url($host === strtolower(config('Pkl')->host) ? '/' : 'pkl');
    }

    private function json(array $body, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($body);
    }
}
