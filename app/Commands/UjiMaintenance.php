<?php

namespace App\Commands;

use App\Filters\MaintenanceFilter;
use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\ModeMaintenance;
use App\Models\ApiTokenModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Uji Sistem → Mode Maintenance (docs/DESAIN-ALAT-SISTEM.md F2, docs/API-SISTEM.md): pustaka ModeMaintenance (berkas status
 * sementara — berkas asli writable/maintenance.json TIDAK disentuh), penyaring global (publik/admin/API/token), halaman Admin,
 * spanduk layout, API app/status + bootstrap + sistem/maintenance + sistem/log-error, tolak masuk API, hak.
 *
 *   php spark dev:uji-maintenance
 *   UJI_FIXTURE=C:\flutter-muslimin\test\fixtures\sistem php spark dev:uji-maintenance   (tulis contoh JSON untuk Flutter)
 */
class UjiMaintenance extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-maintenance';
    protected $description = 'Uji Mode Maintenance (pustaka, penyaring, halaman, API) + API Log Error.';

    private int $lulus = 0;
    private int $gagal = 0;

    /** @var array<string, mixed> contoh JSON untuk kontrak Flutter */
    private array $fx = [];

    /** @var list<string> token uji (dicabut di akhir) */
    private array $token = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . mb_substr($detail, 0, 400) : ''), 'red');
        }
    }

    public function run(array $params)
    {
        $berkas    = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'uji_maintenance_' . getmypid() . '.json';
        $auditAwal = (int) (db_connect()->table('audit_log')->selectMax('id', 'm')->get()->getRow()->m ?? 0);
        ModeMaintenance::pakaiBerkas($berkas);
        try {
            $this->ujiPustaka();
            $this->ujiPenyaring();
            $this->ujiHalaman();
            $this->ujiApi();
            $this->ujiHak();
            $this->tulisFixture();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            ModeMaintenance::pakaiBerkas(null);
            @unlink($berkas);
            foreach ($this->token as $t) {
                (new ApiTokenModel())->revoke($t);
            }
            ApiAuth::clear();
            session()->remove(['isLoggedIn', 'admin']);
            db_connect()->table('audit_log')->where('id >', $auditAwal)->whereIn('tabel', ['maintenance', 'log_error'])->delete();
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================

    private function ujiPustaka(): void
    {
        CLI::write('== Pustaka ModeMaintenance ==', 'yellow');
        $s = ModeMaintenance::status();
        $this->cek('awal tanpa berkas: mati, pesan bawaan', $s['aktif'] === false && $s['pesan'] === ModeMaintenance::PESAN_BAWAAN);
        $this->cek('waktuSah: isian datetime-local diterima, tanggal mustahil/teks ditolak', ModeMaintenance::waktuSah('2026-10-30T22:00') === '2026-10-30 22:00'
            && ModeMaintenance::waktuSah('2026-02-30 10:00') === null && ModeMaintenance::waktuSah('besok') === null && ModeMaintenance::waktuSah('') === null);
        $this->cek('labelWaktu berbahasa Indonesia', ModeMaintenance::labelWaktu('2026-10-30 22:00') === 'Jumat, 30 Oktober 2026 pukul 22.00 WIB', ModeMaintenance::labelWaktu('2026-10-30 22:00'));

        $depan = date('Y-m-d H:i', strtotime('+2 hours'));
        $r     = ModeMaintenance::simpan(true, '', $depan, 'ZZUJI Admin');
        $s     = ModeMaintenance::status();
        $api   = ModeMaintenance::untukApi();
        $this->cek('nyalakan: aktif + pesan bawaan + perkiraan selesai + mulai + oleh; berkas tertulis', $r['ok'] && $s['aktif'] && $s['pesan'] === ModeMaintenance::PESAN_BAWAAN
            && $s['selesai'] === $depan && $s['mulai'] !== null && $s['oleh'] === 'ZZUJI Admin' && is_file(ModeMaintenance::berkas()), json_encode($s));
        $this->cek('untukApi: selesai berdetik, label, coba_lagi ±2 jam', $api['aktif'] === true && $api['selesai'] === $depan . ':00' && $api['selesai_label'] !== ''
            && $api['coba_lagi'] > 6000 && $api['coba_lagi'] <= 7200, json_encode($api));
        $mulai = $s['mulai'];
        $this->cek('selesai sudah lewat ditolak, status tetap', ! ModeMaintenance::simpan(true, 'x', date('Y-m-d H:i', strtotime('-1 hour')), 'Z')['ok'] && ModeMaintenance::status()['pesan'] === ModeMaintenance::PESAN_BAWAAN);
        $this->cek('selesai tidak sah & pesan > 300 huruf ditolak', ! ModeMaintenance::simpan(true, 'x', '30/10/2026', 'Z')['ok'] && ! ModeMaintenance::simpan(true, str_repeat('a', 301), '', 'Z')['ok']);
        $r = ModeMaintenance::simpan(true, 'Perbaikan server.', '', 'ZZUJI Admin');
        $this->cek('perbarui saat aktif: pesan baru, waktu mulai tetap, perkiraan dikosongkan, coba_lagi bawaan', $r['ok'] && str_contains($r['pesan'], 'diperbarui')
            && ModeMaintenance::status()['mulai'] === $mulai && ModeMaintenance::status()['selesai'] === null && ModeMaintenance::cobaLagi() === ModeMaintenance::COBA_LAGI);

        $this->cek('tolakMasuk: Operator ditolak (pesan menyebut hanya Admin), Admin boleh', str_contains((string) ModeMaintenance::tolakMasuk('operator'), 'hanya Admin')
            && ModeMaintenance::tolakMasuk('admin') === null);

        $l = static fn (string $p, string $s = '', string $t = ''): bool => ModeMaintenance::lolos($p, $s, $t);
        $this->cek('lolos: publik (beranda, PKL, Layanan Siswa, Biodata, form guru) selalu ditutup', ! $l('') && ! $l('pkl') && ! $l('layanan/surat/perpanjangan')
            && ! $l('biodata') && ! $l('kesediaan') && ! $l('administrasi'));
        $this->cek('lolos: admin/login & admin/logout terbuka; panel = Admin saja (tanpa sesi diteruskan ke login)', $l('admin/login') && $l('admin/logout', 'operator')
            && $l('admin/dashboard') && $l('admin/dashboard', 'admin') && ! $l('admin/dashboard', 'operator') && ! $l('admin/pkl', 'hubin'));
        $this->cek('lolos: API terbuka hanya login/keluar/bootstrap/status/rilis APK + token Admin', $l('api/v1/auth/login') && $l('api/v1/auth/biometric/login')
            && $l('api/v1/app/status') && $l('api/v1/app/bootstrap') && $l('api/v1/auth/logout') && ! $l('api/v1/publik/beranda') && ! $l('api/v1/auth/me', '', 'operator')
            && ! $l('api/v1/pkl', '', 'hubin') && $l('api/v1/pkl', '', 'admin'));

        $r = ModeMaintenance::simpan(false, '', '', 'ZZUJI Admin');
        $s = ModeMaintenance::status();
        $this->cek('matikan: mati, pesan terakhir disimpan, dimatikan + oleh tercatat', $r['ok'] && ! $s['aktif'] && $s['pesan'] === 'Perbaikan server.'
            && $s['dimatikan'] !== null && $s['dimatikan_oleh'] === 'ZZUJI Admin' && ModeMaintenance::untukApi()['selesai'] === null);
        $n = db_connect()->table('audit_log')->where('tabel', 'maintenance')->like('deskripsi', 'ZZUJI Admin')->countAllResults();
        $this->cek('Audit Log: aktifkan, perbarui, matikan tercatat (3 baris)', $n === 3, (string) $n);
    }

    private function ujiPenyaring(): void
    {
        CLI::write('== Penyaring global ==', 'yellow');
        $f = new MaintenanceFilter();
        $this->cek('maintenance mati → semua lewat', $f->before($this->req('')) === null && $f->before($this->req('api/v1/publik/beranda')) === null);

        ModeMaintenance::simpan(true, 'Sedang perbaikan database.', date('Y-m-d H:i', strtotime('+1 hour')), 'ZZUJI Admin');
        $r = $f->before($this->req(''));
        $this->cek('beranda publik → 503 halaman perawatan (pesan, perkiraan, Coba lagi, Masuk Admin) + Retry-After',
            $r instanceof ResponseInterface && $r->getStatusCode() === 503 && str_contains((string) $r->getBody(), 'Sedang dalam perawatan')
            && str_contains((string) $r->getBody(), 'Sedang perbaikan database.') && str_contains((string) $r->getBody(), 'Perkiraan selesai')
            && str_contains((string) $r->getBody(), 'Masuk Admin') && (int) $r->getHeaderLine('Retry-After') > 3000, $r instanceof ResponseInterface ? (string) $r->getStatusCode() : 'null');
        $r = $f->before($this->req('biodata', ['Accept' => 'application/json']));
        $j = $r ? json_decode((string) $r->getBody(), true) : [];
        $this->cek('form siswa via fetch (Accept JSON) → 503 JSON kode maintenance', $r !== null && $r->getStatusCode() === 503 && ($j['kode'] ?? '') === 'maintenance' && ($j['data']['maintenance']['aktif'] ?? false) === true);

        $this->cek('admin/login tetap terbuka', $f->before($this->req('admin/login')) === null);
        $this->sesi('operator');
        $r = $f->before($this->req('admin/pkl'));
        $this->cek('Operator yang sedang masuk → 503 + tautan Keluar', $r !== null && $r->getStatusCode() === 503 && str_contains((string) $r->getBody(), 'Keluar dari akun'));
        $this->sesi('admin');
        $this->cek('Admin yang sedang masuk → lewat', $f->before($this->req('admin/dashboard')) === null);
        $this->sesi('');

        $r = $f->before($this->req('api/v1/publik/beranda'));
        $j = $r ? json_decode((string) $r->getBody(), true) : [];
        $this->cek('API publik → 503 JSON {ok:false, kode:"maintenance", message, data.maintenance}', $r !== null && $r->getStatusCode() === 503 && ($j['ok'] ?? null) === false
            && ($j['kode'] ?? '') === 'maintenance' && ($j['message'] ?? '') === 'Sedang perbaikan database.' && isset($j['data']['maintenance']['selesai_label']), json_encode($j));
        $this->fx['galat_503_maintenance'] = $j;
        $tOp = (new ApiTokenModel())->issue(3, 'ZZUJI maintenance');
        $tAd = (new ApiTokenModel())->issue(1, 'ZZUJI maintenance');
        array_push($this->token, $tOp, $tAd);
        $r = $f->before($this->req('api/v1/pkl/ajuan', ['Authorization' => 'Bearer ' . $tOp]));
        $this->cek('API dengan token Operator → 503', $r !== null && $r->getStatusCode() === 503);
        $this->cek('API dengan token Admin → lewat; token palsu → 503', $f->before($this->req('api/v1/pkl/ajuan', ['Authorization' => 'Bearer ' . $tAd])) === null
            && $f->before($this->req('api/v1/pkl/ajuan', ['Authorization' => 'Bearer palsu123'])) !== null);
        $this->cek('API login, app/status, preflight OPTIONS → lewat', $f->before($this->req('api/v1/auth/login')) === null && $f->before($this->req('api/v1/app/status')) === null
            && $f->before($this->req('api/v1/pkl/ajuan', [], 'OPTIONS')) === null);
    }

    private function ujiHalaman(): void
    {
        CLI::write('== Halaman Admin ==', 'yellow');
        $html = $this->web('index');
        $this->cek('halaman saat aktif: status AKTIF + Ubah pesan + tombol Matikan + pratinjau', is_string($html) && str_contains($html, 'Mode Maintenance AKTIF')
            && str_contains($html, 'Ubah pesan perawatan') && str_contains($html, 'Matikan mode maintenance') && str_contains($html, 'Lihat tampilan pengunjung'));
        $this->cek('spanduk merah tidak dobel di halaman ini sendiri', is_string($html) && substr_count($html, 'Mode Maintenance AKTIF') === 1);
        $lain = $this->web('index', [], \App\Controllers\Admin\LogError::class);
        $this->cek('spanduk "Mode Maintenance AKTIF" tampil di halaman admin lain', is_string($lain) && str_contains($lain, 'Kelola / matikan'));
        $p = $this->web('pratinjau');
        $this->cek('pratinjau = halaman pengunjung dengan pesan tersimpan', $p instanceof ResponseInterface && str_contains((string) $p->getBody(), 'Sedang perbaikan database.'));

        $r = $this->web('matikan', [], null, 'POST');
        $this->cek('POST matikan → mati + pesan sukses', $r instanceof \CodeIgniter\HTTP\RedirectResponse && ! ModeMaintenance::aktif() && str_contains((string) session('success'), 'dimatikan'));
        $html = $this->web('index');
        $this->cek('halaman saat mati: "Situs berjalan normal" + tombol Aktifkan (dialog konfirmasi), tanpa spanduk', is_string($html) && str_contains($html, 'Situs berjalan normal')
            && str_contains($html, 'Aktifkan mode maintenance') && str_contains($html, 'data-confirm-judul="Aktifkan mode maintenance?"') && ! str_contains($html, 'Kelola / matikan'));
        $r = $this->web('nyalakan', ['pesan' => 'Uji pesan web.', 'selesai' => date('Y-m-d\TH:i', strtotime('-5 minutes'))], null, 'POST');
        $this->cek('POST nyalakan dengan perkiraan lewat → kembali + galat, tetap mati', $r instanceof \CodeIgniter\HTTP\RedirectResponse && ! ModeMaintenance::aktif() && str_contains((string) session('error'), 'akan datang'));
        $r = $this->web('nyalakan', ['pesan' => 'Uji pesan web.', 'selesai' => date('Y-m-d\TH:i', strtotime('+3 hours'))], null, 'POST');
        $this->cek('POST nyalakan → aktif dengan pesan web', $r instanceof \CodeIgniter\HTTP\RedirectResponse && ModeMaintenance::aktif() && ModeMaintenance::status()['pesan'] === 'Uji pesan web.');
    }

    private function ujiApi(): void
    {
        CLI::write('== API ==', 'yellow');
        [$k, $j] = $this->api(\App\Controllers\Api\AppController::class, 'status');
        $this->cek('GET app/status → maintenance aktif + waktu_server', $k === 200 && ($j['data']['maintenance']['aktif'] ?? null) === true && isset($j['data']['waktu_server']));
        $this->fx['app_status_aktif'] = $j;
        [$k, $j] = $this->api(\App\Controllers\Api\AppController::class, 'bootstrap');
        $this->cek('GET app/bootstrap → kunci lama maintenance (bool) + maintenance_message + maintenance_info', $k === 200 && ($j['data']['maintenance'] ?? null) === true
            && ($j['data']['maintenance_message'] ?? '') === 'Uji pesan web.' && ($j['data']['maintenance_info']['aktif'] ?? null) === true);
        $this->fx['app_bootstrap_aktif'] = $j;

        $tolak = new \ReflectionMethod(\App\Controllers\Api\Auth::class, 'tolakAkun');
        $auth  = new \App\Controllers\Api\Auth();
        $auth->initController($this->req('api/v1/auth/login'), service('response'), service('logger'));
        $r = $tolak->invoke($auth, ['aktif' => 1, 'role' => 'operator']);
        $j = $r ? json_decode((string) $r->getBody(), true) : [];
        $this->cek('login API akun Operator saat maintenance → 503 + data.maintenance; Admin → boleh', $r !== null && $r->getStatusCode() === 503
            && ($j['data']['maintenance']['aktif'] ?? null) === true && $tolak->invoke($auth, ['aktif' => 1, 'role' => 'admin']) === null);
        $this->fx['auth_login_503_maintenance'] = $j;

        $S = \App\Controllers\Api\Admin\Sistem::class;
        [$k] = $this->api($S, 'maintenance', 'operator');
        $this->cek('GET sistem/maintenance oleh Operator → 403', $k === 403, (string) $k);
        [$k, $j] = $this->api($S, 'maintenance', 'admin');
        $this->cek('GET sistem/maintenance Admin → status + isian (pesan_tersimpan, selesai_isian, maks_pesan)', $k === 200 && ($j['data']['aktif'] ?? null) === true
            && ($j['data']['pesan_tersimpan'] ?? '') === 'Uji pesan web.' && ($j['data']['maks_pesan'] ?? 0) === ModeMaintenance::MAKS_PESAN && ($j['data']['selesai_isian'] ?? '') !== '');
        $this->fx['sistem_maintenance_get_aktif'] = $j;
        [$k, $j] = $this->api($S, 'simpanMaintenance', 'admin', ['pesan' => 'x']);
        $this->cek('POST tanpa "aktif" → 422', $k === 422 && isset($j['data']['errors']['aktif']));
        [$k, $j] = $this->api($S, 'simpanMaintenance', 'admin', ['aktif' => true, 'pesan' => 'x', 'selesai' => '2020-01-01 10:00']);
        $this->cek('POST selesai lewat → 422 errors.selesai', $k === 422 && isset($j['data']['errors']['selesai']));
        $this->fx['sistem_maintenance_post_422'] = $j;
        [$k, $j] = $this->api($S, 'simpanMaintenance', 'admin', ['aktif' => false]);
        $this->cek('POST {aktif:false} → mati', $k === 200 && ($j['data']['aktif'] ?? null) === false && ! ModeMaintenance::aktif());
        $this->fx['sistem_maintenance_post_mati'] = $j;
        [$k, $j] = $this->api($S, 'simpanMaintenance', 'admin', ['aktif' => true, 'pesan' => 'Lewat aplikasi.', 'selesai' => date('Y-m-d H:i', strtotime('+1 day'))]);
        $this->cek('POST {aktif:true, pesan, selesai} → aktif + oleh = nama Admin', $k === 200 && ($j['data']['aktif'] ?? null) === true && ($j['data']['pesan'] ?? '') === 'Lewat aplikasi.'
            && ($j['data']['oleh'] ?? '') === 'ZZUJI admin');
        $this->fx['sistem_maintenance_post_aktif'] = $j;
        ModeMaintenance::simpan(false, '', '', 'ZZUJI Admin');

        [$k] = $this->api($S, 'logError', 'operator');
        $this->cek('GET sistem/log-error oleh Operator → 403; bersihkan oleh Operator → 403', $k === 403 && $this->api($S, 'bersihkanLog', 'operator')[0] === 403);
        [$k, $j] = $this->api($S, 'logError', 'admin');
        $b = $j['data']['berkas'] ?? [];
        $this->cek('GET sistem/log-error Admin → berkas[] (hitung per tingkat) + terpilih + isi.kelompok + daftar tingkat', $k === 200 && is_array($b)
            && ($b === [] || (isset($b[0]['hitung']['galat']) && ($j['data']['terpilih'] ?? '') !== '' && is_array($j['data']['isi']['kelompok'] ?? null)))
            && count($j['data']['tingkat'] ?? []) === 4, json_encode(array_keys($j['data'] ?? [])));
        if (isset($j['data']['isi']['kelompok'])) {
            $j['data']['isi']['kelompok'] = array_slice($j['data']['isi']['kelompok'], 0, 3);
            $j['data']['berkas']          = array_slice($j['data']['berkas'], 0, 3);
        }
        $this->fx['sistem_log_error'] = $j;
    }

    private function ujiHak(): void
    {
        CLI::write('== Hak (khusus Admin) ==', 'yellow');
        $ok = HakAkses::boleh('admin', 'admin/sistem/maintenance') && HakAkses::boleh('admin', 'admin/sistem/maintenance/nyalakan', 'POST');
        foreach (['operator', 'hubin', 'guru', ''] as $p) {
            $ok = $ok && ! HakAkses::boleh($p, 'admin/sistem/maintenance') && ! HakAkses::boleh($p, 'admin/sistem/maintenance/nyalakan', 'POST')
                && ! HakAkses::boleh($p, 'admin/sistem/maintenance/matikan', 'POST');
        }
        $this->cek('web: hanya Admin (Operator, Waka Hubin, Guru, tanpa peran ditolak)', $ok);
        $this->cek('API: alamat sistem/* hanya Admin', HakAkses::bolehApiAlamat('admin', 'sistem/maintenance') && ! HakAkses::bolehApiAlamat('operator', 'sistem/maintenance')
            && ! HakAkses::bolehApiAlamat('hubin', 'sistem/log-error'));
        $item = array_values(array_filter(array_merge(...array_column(\App\Libraries\MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => $it[0] === 'admin/sistem/maintenance'))[0] ?? null;
        $this->cek('menu "Mode Maintenance" aktif (tidak lagi Segera)', $item !== null && empty($item[4]));
    }

    private function tulisFixture(): void
    {
        $dir = (string) getenv('UJI_FIXTURE');
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }
        foreach ($this->fx as $nama => $isi) {
            file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function req(string $path, array $header = [], string $metode = 'GET'): \CodeIgniter\HTTP\IncomingRequest
    {
        $r = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), $path), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($metode);
        foreach ($header as $k => $v) {
            $r->setHeader($k, $v);
        }

        return $r;
    }

    private function sesi(string $peran): void
    {
        if ($peran === '') {
            session()->remove(['isLoggedIn', 'admin']);

            return;
        }
        session()->set(['isLoggedIn' => true, 'admin' => ['id' => $peran === 'admin' ? 1 : 3, 'full_name' => 'ZZUJI ' . $peran, 'username' => 'zzujimm', 'role' => $peran]]);
    }

    private function web(string $metode, array $post = [], ?string $kelas = null, string $http = 'GET'): mixed
    {
        $request = $this->req($kelas === \App\Controllers\Admin\LogError::class ? 'admin/sistem/log-error' : 'admin/sistem/maintenance', [], $http);
        \Config\Services::injectMock('request', $request);
        $this->sesi('admin');
        $request->setGlobal('post', $post);
        session()->remove(['success', 'error', '_ci_old_input']);
        $kelas ??= \App\Controllers\Admin\Maintenance::class;
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}();
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $kelas, string $metode, ?string $peran = null, ?array $json = null): array
    {
        if ($peran !== null) {
            ApiAuth::set(['id' => $peran === 'admin' ? 1 : 3, 'full_name' => 'ZZUJI ' . $peran, 'username' => 'zzujimm', 'role' => $peran]);
        }
        $request = $this->req('api/v1/x', $json !== null ? ['Content-Type' => 'application/json'] : [], $json !== null ? 'POST' : 'GET');
        if ($json !== null) {
            $request->setBody(json_encode($json));
        }
        \Config\Services::injectMock('request', $request);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}();
        ApiAuth::clear();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }
}
