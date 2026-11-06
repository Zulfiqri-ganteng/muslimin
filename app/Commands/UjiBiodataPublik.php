<?php

namespace App\Commands;

use App\Libraries\LayananToken;
use App\Libraries\LoginThrottle;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Config\Services;

/**
 * Uji FORM ISIAN BIODATA publik — halaman web `biodata` (Controllers\Biodata: siswa, buka, kirim) dan API aplikasi Android
 * `publik/biodata/*` (Api\BiodataPublik, layanan LY6). Keduanya memakai logika yang SAMA, jadi skenario & hasilnya sama:
 *   daftar nama + status, galat per kolom, kirim baru, kirim lagi ditolak (terkunci), dikembalikan admin → buka dengan NISN /
 *   tanggal lahir (salah/benar) → kirim perbaikan; tanpa izin buka → ditolak.
 * Jalur API: izin buka = token `X-Izin-Token` (bukan sesi). `--fixture <folder>` menyimpan respons API untuk tes aplikasi.
 *
 * Data uji "ZZUJI BD …" (nama), NIS "ZZUJIBD…", NISN "99123456xx", IP "10.9.7.10" — dibuat & dihapus sendiri; saklar form biodata
 * di Pengaturan Sekolah dipulihkan ke isi semula di akhir.
 *
 * Jalankan:  php spark dev:uji-biodata-publik
 */
class UjiBiodataPublik extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-biodata-publik';
    protected $description = 'Uji form isian biodata publik: halaman web & API aplikasi (LY6).';

    private const NIS  = 'ZZUJIBD';
    private const IP   = '10.9.7.10';
    private const OLEH = 'ZZUJI BD';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $asli = null;
    private int $kelasId = 0;

    /** @var array<int, int> nomor => id siswa uji */
    private array $s = [];

    public function run(array $params)
    {
        try {
            return $this->jalan();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function jalan(): int
    {
        $this->db = db_connect();
        $this->bersihkan();
        $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('ada kelas sebagai bahan uji', false, 'tabel kelas kosong');

            return EXIT_ERROR;
        }
        $this->kelasId = (int) $k['id'];
        $row           = $this->db->table('settings')->select('id, biodata_open, biodata_tutup')->orderBy('id')->get()->getRowArray();
        $this->asli    = $row;
        $this->db->table('settings')->where('id', (int) $row['id'])->update(['biodata_open' => 1, 'biodata_tutup' => null]);
        cache()->delete('app_setting');
        $now = date('Y-m-d H:i:s');
        for ($n = 1; $n <= 4; $n++) {
            $this->db->table('siswa')->insert([
                'nis' => self::NIS . $n, 'nama' => $this->nama($n), 'jenis_kelamin' => 'P', 'kelas_id' => $this->kelasId,
                'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->s[$n] = (int) $this->db->insertID();
        }

        $this->ujiWeb();
        if (class_exists(\App\Controllers\Api\BiodataPublik::class)) {
            $this->ujiApi();
        } else {
            CLI::write('(Api\BiodataPublik belum ada — bagian API dilewati)', 'yellow');
        }

        $this->bersihkan();
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . $detail : ''), $ok ? 'green' : 'red');
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    /** Nama siswa uji: hanya huruf (aturan nama web menolak angka). */
    private function nama(int $n): string
    {
        return self::OLEH . ' ' . ['', 'Satu', 'Dua', 'Tiga', 'Empat'][$n];
    }

    private function nisn(int $n): string
    {
        return '99123456' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /** Isian biodata yang sah (format kiriman web). @return array<string, string> */
    private function isian(int $n): array
    {
        return [
            'siswa_id' => (string) $this->s[$n], 'nama' => $this->nama($n), 'nisn' => $this->nisn($n), 'nis' => '',
            'tempat_lahir' => 'Bekasi', 'tanggal_lahir' => '2009-04-12', 'jenis_kelamin' => 'P', 'agama' => 'Islam',
            'status_keluarga' => 'Anak Kandung', 'anak_ke' => '2', 'alamat' => 'Jl. Uji Biodata Blok A No. 7', 'rt' => '018', 'rw' => '22',
            'kelurahan' => 'Kebalen', 'kecamatan' => 'Babelan', 'kota' => 'Bekasi', 'no_hp' => '', 'sekolah_asal' => 'smp negeri 6 babelan',
            'diterima_kelas' => 'X TKJ 1', 'diterima_tanggal' => '', 'nama_ayah' => 'Budi Santoso', 'pekerjaan_ayah' => 'Wiraswasta',
            'nama_ibu' => 'Sri Wahyuni', 'pekerjaan_ibu' => 'Mengurus Rumah Tangga', 'ortu_sama' => '1', 'ortu_telepon1' => '081277770001',
            'ortu_telepon2' => '', 'punya_wali' => 'tidak', 'pernyataan' => '1',
        ];
    }

    private function permintaan(string $uri, array $get, array $post, ?string $json = null): IncomingRequest
    {
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), $uri), $json, new UserAgent());
        Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('server', ['REMOTE_ADDR' => self::IP]);

        return $request;
    }

    /** Controller web publik (sesi ikut, seperti peramban). @return array{0: int, 1: array<string, mixed>} */
    private function web(string $metode, array $get = [], array $post = []): array
    {
        $request = $this->permintaan('biodata', $get, $post);
        $c       = new \App\Controllers\Biodata();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r = $c->{$metode}();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    /** API aplikasi (isi JSON, tanpa sesi). @return array{0: int, 1: array<string, mixed>} */
    private function api(string $metode, array $get = [], array $json = [], string $token = '', string $fixture = ''): array
    {
        session()->remove(['biodata_buka', 'biodata_selesai']); // API tidak boleh menumpang sesi web
        $request = $this->permintaan('api/v1/publik/biodata', $get, [], $json === [] ? null : json_encode($json));
        if ($json !== []) {
            $request->setHeader('Content-Type', 'application/json');
        }
        if ($token !== '') {
            $request->setHeader('X-Izin-Token', $token);
        }
        $c = new \App\Controllers\Api\BiodataPublik();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r   = $c->{$metode}();
        $isi = json_decode((string) $r->getBody(), true) ?: [];
        $dir = (string) (CLI::getOption('fixture') ?? '');
        if ($fixture !== '' && $dir !== '') {
            $simpan = $isi;
            if ($fixture === 'biodata_siswa') { // daftar nama sekelas: hanya siswa uji — nama siswa asli tidak boleh masuk fixture
                $simpan['data'] = array_values(array_filter($isi['data'] ?? [], static fn (array $s): bool => str_starts_with((string) $s['nama'], self::OLEH)));
            }
            @mkdir($dir, 0775, true);
            file_put_contents(rtrim($dir, '/\\') . '/' . $fixture . '.json', json_encode($simpan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return [$r->getStatusCode(), $isi];
    }

    /** @param array<int, array<string, mixed>> $daftar */
    private function status(array $daftar, int $siswaId): string
    {
        foreach ($daftar as $s) {
            if ((int) $s['id'] === $siswaId) {
                return (string) $s['status'];
            }
        }

        return '-';
    }

    /** Admin mengembalikan isian siswa [$n] untuk diperbaiki. */
    private function kembalikan(int $n): void
    {
        $this->db->table('biodata_isian')->where('siswa_id', $this->s[$n])->update(['status' => 'perbaikan', 'catatan_admin' => 'Nama ibu belum sesuai KK']);
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $this->db->table('biodata_isian')->whereIn('siswa_id', $ids)->delete();
            $throttle = new LoginThrottle();
            foreach ($ids as $id) {
                $throttle->clear('biodata:' . $id, self::IP);
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('biodata_isian')->where('ip_address', self::IP)->delete();
        session()->remove(['biodata_buka', 'biodata_selesai']);
        if ($this->asli !== null) {
            $this->db->table('settings')->where('id', (int) $this->asli['id'])->update(['biodata_open' => $this->asli['biodata_open'], 'biodata_tutup' => $this->asli['biodata_tutup']]);
            cache()->delete('app_setting');
            $this->asli = null;
        }
    }

    // =================================================================
    // Halaman web (patokan perilaku)
    // =================================================================

    private function ujiWeb(): void
    {
        $this->bagian('Halaman web biodata: siswa, kirim, buka, kirim perbaikan');
        [$k, $j] = $this->web('siswa', ['kelas_id' => $this->kelasId]);
        $this->cek('siswa: 200, siswa uji berstatus belum', $k === 200 && ($j['ok'] ?? false) && $this->status($j['data'] ?? [], $this->s[1]) === 'belum');
        [$k] = $this->web('siswa');
        $this->cek('siswa tanpa kelas → 422', $k === 422);

        [$k, $j] = $this->web('kirim', [], ['nisn' => '12'] + $this->isian(1));
        $this->cek('kirim isian salah → 422 errors.nisn', $k === 422 && isset($j['errors']['nisn']), json_encode($j));
        [$k, $j] = $this->web('kirim', [], $this->isian(1));
        $info    = (array) session('biodata_selesai');
        $this->cek('kirim baru → ok + ke halaman selesai, ringkasan nama/no', $k === 200 && ($j['ok'] ?? false) && str_ends_with((string) ($j['redirect'] ?? ''), 'biodata/selesai')
            && (int) ($info['no'] ?? 0) > 0 && ($info['revisi'] ?? true) === false, json_encode($j));
        [$k, $j] = $this->web('kirim', [], $this->isian(1));
        $this->cek('kirim lagi → 409 pesan terkunci', $k === 409 && str_contains((string) ($j['message'] ?? ''), 'sudah mengirim biodata'), (string) ($j['message'] ?? ''));
        [$k, $j] = $this->web('kirim', [], ['nisn' => $this->nisn(1)] + $this->isian(2));
        $this->cek('NISN milik isian lain → 422 errors.nisn', $k === 422 && isset($j['errors']['nisn']));

        $this->kembalikan(1);
        session()->remove('biodata_buka');
        [$k, $j] = $this->web('kirim', [], $this->isian(1));
        $this->cek('kirim perbaikan tanpa buka → 403', $k === 403 && str_contains((string) ($j['message'] ?? ''), 'Buka isianmu dulu'));
        [$k, $j] = $this->web('buka', [], ['siswa_id' => (string) $this->s[1], 'nisn' => '9999999999']);
        $this->cek('buka NISN salah → 422', $k === 422 && str_contains((string) ($j['message'] ?? ''), 'tidak cocok'));
        [$k, $j] = $this->web('buka', [], ['siswa_id' => (string) $this->s[1], 'tanggal_lahir' => '2009-04-12']);
        $this->cek('buka tanggal lahir benar → 200 isian lama + catatan admin', $k === 200 && ($j['data']['nisn'] ?? '') === $this->nisn(1) && ($j['catatan'] ?? '') === 'Nama ibu belum sesuai KK', json_encode($j));
        [$k, $j] = $this->web('kirim', [], ['nama_ibu' => 'Sri Wahyuningsih'] + $this->isian(1));
        $info    = (array) session('biodata_selesai');
        $this->cek('kirim perbaikan sesudah buka → ok (revisi), isian kembali menunggu', $k === 200 && ($j['ok'] ?? false) && ($info['revisi'] ?? false) === true
            && ($this->db->table('biodata_isian')->select('status')->where('siswa_id', $this->s[1])->get()->getRowArray()['status'] ?? '') === 'menunggu', json_encode($j));
    }

    // =================================================================
    // API aplikasi (LY6)
    // =================================================================

    private function ujiApi(): void
    {
        $this->bagian('API publik aplikasi: publik/biodata (LY6)');
        [$k, $j] = $this->api('index', [], [], '', 'biodata_form');
        $d       = $j['data'] ?? [];
        $ada     = false;
        foreach ($d['kelas'] ?? [] as $g) {
            $ada = $ada || in_array($this->kelasId, array_map('intval', array_column($g['kelas'] ?? [], 'id')), true);
        }
        $this->cek('GET publik/biodata: terbuka, kelas, label/bagian/wajib (BiodataForm), pilihan agama/status/pekerjaan', $k === 200 && ($d['terbuka'] ?? false) === true && $ada
            && ($d['label']['nisn'] ?? '') === 'NISN' && isset($d['bagian']['Data Diri']) && in_array('nama', $d['wajib'] ?? [], true)
            && in_array('Islam', $d['agama'] ?? [], true) && in_array('Anak Kandung', $d['status_keluarga'] ?? [], true) && ($d['pekerjaan'] ?? []) !== [], json_encode($d));
        [$k, $j] = $this->api('siswa', ['kelas_id' => $this->kelasId], [], '', 'biodata_siswa');
        $this->cek('GET siswa: amplop API, status sama dengan web', $k === 200 && ($j['status'] ?? '') === 'success' && $this->status($j['data'] ?? [], $this->s[1]) === 'menunggu'
            && $this->status($j['data'] ?? [], $this->s[3]) === 'belum');

        $isi              = $this->isian(3);
        $isi['siswa_id']  = $this->s[3];
        $isi['ortu_sama'] = true;
        $isi['pernyataan'] = true;
        [$k, $j] = $this->api('kirim', [], ['nisn' => '12', 'agama' => '', 'ortu_telepon1' => '', 'pernyataan' => false] + $isi, '', 'biodata_kirim_galat');
        $this->cek('POST kirim isian salah → 422 data.errors per kolom (kalimat web)', $k === 422 && isset($j['data']['errors']['nisn'], $j['data']['errors']['agama'], $j['data']['errors']['ortu_telepon'], $j['data']['errors']['pernyataan']), json_encode($j));
        [$k, $j] = $this->api('kirim', [], $isi, '', 'biodata_kirim');
        $this->cek('POST kirim baru (boolean JSON = "1") → 200 ringkasan', $k === 200 && (int) ($j['data']['no'] ?? 0) > 0 && ($j['data']['revisi'] ?? true) === false && ($j['data']['nama'] ?? '') === $this->nama(3), json_encode($j));
        [$k, $j] = $this->api('kirim', [], $isi, '', 'biodata_kirim_terkunci');
        $this->cek('POST kirim lagi → 409 pesan terkunci', $k === 409 && str_contains((string) ($j['message'] ?? ''), 'sudah mengirim biodata'));

        $this->kembalikan(3);
        [$k, $j] = $this->api('kirim', [], $isi, '', 'biodata_kirim_belum_buka');
        $this->cek('POST kirim perbaikan tanpa token → 403', $k === 403 && str_contains((string) ($j['message'] ?? ''), 'Buka isianmu dulu'));
        [$k, $j] = $this->api('buka', [], ['siswa_id' => $this->s[3], 'nisn' => '9999999999'], '', 'biodata_buka_salah');
        $this->cek('POST buka NISN salah → 422', $k === 422);
        [$k, $j] = $this->api('buka', [], ['siswa_id' => $this->s[3], 'nisn' => $this->nisn(3)], '', 'biodata_buka');
        $token   = (string) ($j['data']['token'] ?? '');
        $this->cek('POST buka NISN benar → 200 isian lama + catatan + token', $k === 200 && $token !== '' && ($j['data']['nisn'] ?? '') === $this->nisn(3)
            && ($j['data']['catatan'] ?? '') === 'Nama ibu belum sesuai KK', json_encode($j));
        $this->cek('token izin biodata ≠ token PKL / Layanan Siswa', LayananToken::bacaIzin('pkl-ajukan', $token) === null && LayananToken::baca($token) === null);
        [$k] = $this->api('kirim', [], ['siswa_id' => $this->s[1]] + $isi, $token);
        $this->cek('POST kirim isian siswa lain dengan token ini → ditolak', in_array($k, [403, 409, 422], true));
        [$k, $j] = $this->api('kirim', [], ['nama_ibu' => 'Sri Wahyuningsih'] + $isi, $token, 'biodata_kirim_perbaikan');
        $this->cek('POST kirim perbaikan dengan token → 200 revisi, isian kembali menunggu', $k === 200 && ($j['data']['revisi'] ?? false) === true
            && ($this->db->table('biodata_isian')->select('status')->where('siswa_id', $this->s[3])->get()->getRowArray()['status'] ?? '') === 'menunggu', json_encode($j));
        $this->cek('API tidak menulis sesi web (izin buka & halaman selesai)', session('biodata_buka') === null && session('biodata_selesai') === null);
    }
}
