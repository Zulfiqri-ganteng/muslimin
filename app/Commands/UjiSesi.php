<?php

namespace App\Commands;

use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\SesiAktif;
use App\Models\ApiTokenModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji Sistem → Sesi Aktif (docs/DESAIN-ALAT-SISTEM.md F5). Memakai DUA akun Admin sementara + id sesi tiruan; sesi & token akun lain
 * di database laptop tidak disentuh ("keluarkan semua" diuji di dalam transaksi yang dibatalkan).
 *
 *   php spark dev:uji-sesi
 *   UJI_FIXTURE=C:\flutter-muslimin\test\fixtures\sistem php spark dev:uji-sesi
 */
class UjiSesi extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-sesi';
    protected $description = 'Uji Sesi Aktif (catat, periksa, keluarkan web & Android, halaman, API, hak).';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private int $lulus = 0;
    private int $gagal = 0;
    private array $fx = [];
    private int $a1 = 0;
    private int $a2 = 0;

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
        $db        = db_connect();
        $auditAwal = (int) ($db->table('audit_log')->selectMax('id', 'm')->get()->getRow()->m ?? 0);
        try {
            foreach (['zzujisesi1' => 'a1', 'zzujisesi2' => 'a2'] as $u => $p) {
                $db->table('admins')->insert(['username' => $u, 'email' => $u . '@contoh.test', 'full_name' => 'ZZUJI ' . $u, 'password' => 'x',
                    'role' => 'admin', 'aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
                $this->{$p} = (int) $db->insertID();
            }
            $this->ujiPustaka();
            $this->ujiWeb();
            $this->ujiApi();
            $this->ujiHak();
            $dir = (string) getenv('UJI_FIXTURE');
            if ($dir !== '' && is_dir($dir)) {
                foreach ($this->fx as $nama => $isi) {
                    file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
                $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
            }
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            SesiAktif::pakaiSid(null);
            ApiAuth::clear();
            session()->remove(['isLoggedIn', 'admin', 'lastActivity']);
            $ids = array_filter([$this->a1, $this->a2]);
            if ($ids !== []) {
                $db->table('sesi_admin')->whereIn('admin_id', $ids)->delete();
                $db->table('api_tokens')->whereIn('admin_id', $ids)->delete();
                $db->table('admins')->whereIn('id', $ids)->delete();
            }
            $db->table('audit_log')->where('id >', $auditAwal)->where('tabel', 'sesi')->delete();
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function baris(string $sid): ?array
    {
        return db_connect()->table('sesi_admin')->where('sesi_hash', SesiAktif::sidik($sid))->get()->getRowArray();
    }

    private function ujiPustaka(): void
    {
        CLI::write('== Pustaka SesiAktif ==', 'yellow');
        $this->cek('perangkat dari User-Agent', SesiAktif::perangkat(self::UA) === 'Chrome 130 · Windows 10', SesiAktif::perangkat(self::UA));
        SesiAktif::pakaiSid('zzuji-sesi-A');
        SesiAktif::catatMasuk($this->a1, '10.0.0.1', self::UA);
        $a = $this->baris('zzuji-sesi-A');
        $this->cek('login: sesi dicatat (sidik sha256, bukan id sesi mentah), perangkat, IP', $a !== null && (int) $a['admin_id'] === $this->a1 && strlen($a['sesi_hash']) === 64
            && $a['sesi_hash'] !== 'zzuji-sesi-A' && $a['perangkat'] === 'Chrome 130 · Windows 10' && $a['ip'] === '10.0.0.1');
        db_connect()->table('sesi_admin')->where('id', $a['id'])->update(['aktif_at' => date('Y-m-d H:i:s', time() - 900)]);
        $this->cek('periksa: boleh lanjut + aktivitas lama (>5 menit) diperbarui', SesiAktif::periksa($this->a1, '10.0.0.2', self::UA) === null
            && strtotime((string) $this->baris('zzuji-sesi-A')['aktif_at']) > time() - 60 && $this->baris('zzuji-sesi-A')['ip'] === '10.0.0.2');
        $this->cek('periksa: sidik sesi milik akun lain → dipaksa keluar', SesiAktif::periksa($this->a2, '10.0.0.2', self::UA) === SesiAktif::PESAN_DIKELUARKAN);

        SesiAktif::pakaiSid('zzuji-sesi-B');
        $this->cek('periksa: sesi lama tanpa catatan (login sebelum fitur) → dicatat saat itu', SesiAktif::periksa($this->a1, '10.0.0.3', self::UA) === null && $this->baris('zzuji-sesi-B') !== null);
        $d = SesiAktif::daftar(SesiAktif::sidik('zzuji-sesi-A'));
        $saya = array_values(array_filter($d['web'], fn (array $r): bool => $r['admin_id'] === $this->a1));
        $this->cek('daftar web: 2 sesi akun uji, tepat satu bertanda "ini", peran_label', count($saya) === 2 && count(array_filter($saya, static fn (array $r): bool => $r['ini'])) === 1
            && $saya[0]['peran_label'] !== '');
        $idB = (int) $this->baris('zzuji-sesi-B')['id'];
        $this->cek('keluarkan sesi web B → periksa B ditolak dengan pesan Admin', SesiAktif::keluarkan('web', $idB, 'ZZUJI') && SesiAktif::periksa($this->a1, '10.0.0.3', self::UA) === SesiAktif::PESAN_DIKELUARKAN
            && $this->baris('zzuji-sesi-B')['keluar_alasan'] === 'dicabut' && $this->baris('zzuji-sesi-B')['dikeluarkan_oleh'] === 'ZZUJI');
        $this->cek('keluarkan lagi sesi yang sudah keluar → false', ! SesiAktif::keluarkan('web', $idB, 'ZZUJI'));
        SesiAktif::pakaiSid('zzuji-sesi-A');
        SesiAktif::catatKeluar('keluar');
        $this->cek('logout: sesi A berakhir (alasan keluar), tidak tampil di daftar', $this->baris('zzuji-sesi-A')['keluar_alasan'] === 'keluar'
            && ! in_array($this->a1, array_column(SesiAktif::daftar()['web'], 'admin_id'), true));

        $t1 = (new ApiTokenModel())->issue($this->a2, 'Samsung A15 (uji)');
        $d  = SesiAktif::daftar(null, null);
        $an = array_values(array_filter($d['android'], fn (array $r): bool => $r['admin_id'] === $this->a2))[0] ?? null;
        $this->cek('daftar Android: token akun uji + nama perangkat', $an !== null && $an['perangkat'] === 'Samsung A15 (uji)' && $an['jenis'] === 'android');
        $this->cek('keluarkan Android → token terhapus (aplikasi akan 401)', SesiAktif::keluarkan('android', (int) $an['id'], 'ZZUJI') && (new ApiTokenModel())->findValid($t1) === null);

        SesiAktif::pakaiSid('zzuji-sesi-C');
        SesiAktif::catatMasuk($this->a2, '10.0.0.4', self::UA);
        $t2 = (new ApiTokenModel())->issue($this->a2, 'HP uji');
        $n  = SesiAktif::keluarkanSemua('ZZUJI', $this->a1, $this->a2);
        $this->cek('keluarkan semua sesi satu akun: web + Android', $n['web'] === 1 && $n['android'] === 1 && (new ApiTokenModel())->findValid($t2) === null, json_encode($n));
        $n = SesiAktif::keluarkanSemua('ZZUJI', $this->a2, $this->a2);
        $this->cek('akun yang dikecualikan (diri sendiri) tidak tersentuh', $n === ['web' => 0, 'android' => 0], json_encode($n));
    }

    private function ujiWeb(): void
    {
        CLI::write('== Halaman web ==', 'yellow');
        SesiAktif::pakaiSid('zzuji-sesi-D');
        SesiAktif::catatMasuk($this->a1, '10.0.0.5', self::UA);
        SesiAktif::pakaiSid('zzuji-sesi-E');
        SesiAktif::catatMasuk($this->a2, '10.0.0.6', self::UA);
        SesiAktif::pakaiSid('zzuji-sesi-D');
        $html = $this->web('index');
        $this->cek('halaman: dua tabel, "Sesi ini" untuk diri sendiri, tombol Keluarkan & Keluarkan semua', is_string($html) && str_contains($html, 'Masuk di web')
            && str_contains($html, 'Aplikasi Android') && str_contains($html, 'Sesi ini') && str_contains($html, 'Keluarkan semua kecuali saya') && str_contains($html, 'ZZUJI zzujisesi2'));
        $idD = (int) $this->baris('zzuji-sesi-D')['id'];
        $idE = (int) $this->baris('zzuji-sesi-E')['id'];
        $this->web('keluarkan', 'web', (string) $idD);
        $this->cek('keluarkan sesi sendiri → ditolak', str_contains((string) session('error'), 'sesi Anda sendiri') && $this->baris('zzuji-sesi-D')['keluar_at'] === null);
        $this->web('keluarkan', 'web', (string) $idE);
        $this->cek('keluarkan sesi akun lain → sukses + Audit Log', str_contains((string) session('success'), 'dikeluarkan') && $this->baris('zzuji-sesi-E')['keluar_alasan'] === 'dicabut'
            && db_connect()->table('audit_log')->where('tabel', 'sesi')->like('deskripsi', 'zzujisesi2')->countAllResults() >= 1);
        $this->web('keluarkan', 'web', '999999999');
        $this->cek('keluarkan id tidak dikenal → galat', str_contains((string) session('error'), 'tidak ditemukan'));

        $db = db_connect();
        $db->transBegin(); // "keluarkan semua" menyentuh semua akun — diuji lalu dibatalkan
        $this->web('keluarkanSemua');
        $sisaLain = $db->table('sesi_admin')->where('keluar_at', null)->where('admin_id !=', $this->a1)->countAllResults()
            + $db->table('api_tokens')->where('admin_id !=', $this->a1)->countAllResults();
        $milikSaya = $this->baris('zzuji-sesi-D')['keluar_at'] === null;
        $db->transRollback();
        $this->cek('keluarkan semua kecuali saya → tak tersisa sesi akun lain, sesi sendiri tetap (lalu dibatalkan)', $sisaLain === 0 && $milikSaya
            && str_contains((string) session('success'), 'Dikeluarkan'));
    }

    private function ujiApi(): void
    {
        CLI::write('== API ==', 'yellow');
        $S   = \App\Controllers\Api\Admin\Sistem::class;
        $tok = (new ApiTokenModel())->issue($this->a1, 'HP Admin uji');
        $tId = (int) db_connect()->table('api_tokens')->where('token_hash', hash('sha256', $tok))->get()->getRow()->id;
        $lain = (new ApiTokenModel())->issue($this->a2, 'HP lain uji');
        $lId  = (int) db_connect()->table('api_tokens')->where('token_hash', hash('sha256', $lain))->get()->getRow()->id;
        $this->cek('GET sistem/sesi oleh Operator → 403', $this->api($S, 'sesi', 'operator')[0] === 403);
        [$k, $j] = $this->api($S, 'sesi', 'admin', null, $tId);
        $saya = array_values(array_filter($j['data']['android'] ?? [], static fn (array $r): bool => $r['id'] === $tId))[0] ?? null;
        $this->cek('GET sistem/sesi → web[] + android[] ("ini" = token sendiri) + batas_aktif_menit', $k === 200 && is_array($j['data']['web'] ?? null)
            && ($saya['ini'] ?? false) === true && ($j['data']['batas_aktif_menit'] ?? 0) === 60);
        $this->fx['sistem_sesi'] = $this->saring($j);
        [$k] = $this->api($S, 'keluarkanSesi', 'admin', ['jenis' => 'apa', 'id' => 1], $tId);
        $this->cek('POST keluarkan jenis salah → 422', $k === 422);
        [$k] = $this->api($S, 'keluarkanSesi', 'admin', ['jenis' => 'android', 'id' => $tId], $tId);
        $this->cek('POST keluarkan token sendiri → 422', $k === 422);
        [$k, $j] = $this->api($S, 'keluarkanSesi', 'admin', ['jenis' => 'android', 'id' => $lId], $tId);
        $this->cek('POST keluarkan token lain → 200, token terhapus', $k === 200 && (new ApiTokenModel())->findValid($lain) === null);
        $this->fx['sistem_sesi_keluarkan'] = $this->saring($j);
    }

    private function ujiHak(): void
    {
        CLI::write('== Hak (khusus Admin) ==', 'yellow');
        $ok = HakAkses::boleh('admin', 'admin/sistem/sesi') && HakAkses::boleh('admin', 'admin/sistem/sesi/web/1/keluarkan', 'POST');
        foreach (['operator', 'hubin', 'guru', ''] as $p) {
            $ok = $ok && ! HakAkses::boleh($p, 'admin/sistem/sesi') && ! HakAkses::boleh($p, 'admin/sistem/sesi/keluarkan-semua', 'POST');
        }
        $this->cek('web: hanya Admin', $ok);
        $this->cek('API sistem/sesi hanya Admin', HakAkses::bolehApiAlamat('admin', 'sistem/sesi') && ! HakAkses::bolehApiAlamat('operator', 'sistem/sesi'));
        $item = array_values(array_filter(array_merge(...array_column(\App\Libraries\MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => $it[0] === 'admin/sistem/sesi'))[0] ?? null;
        $this->cek('menu "Sesi Aktif" aktif (tidak lagi Segera) — keempat menu Sistem kini aktif', $item !== null && empty($item[4])
            && array_filter(array_merge(...array_column(\App\Libraries\MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => str_starts_with($it[0], 'admin/sistem/') && ! empty($it[4])) === []);
    }

    /** Fixture: hanya baris akun uji (data akun lain di laptop tidak ikut). */
    private function saring(array $j): array
    {
        foreach (['web', 'android'] as $k) {
            if (isset($j['data'][$k])) {
                $j['data'][$k] = array_values(array_filter($j['data'][$k], fn (array $r): bool => in_array($r['admin_id'], [$this->a1, $this->a2], true)));
            }
        }

        return $j;
    }

    private function web(string $metode, string ...$arg): mixed
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/sistem/sesi'), null, new \CodeIgniter\HTTP\UserAgent()))
            ->withMethod($metode === 'index' ? 'GET' : 'POST');
        \Config\Services::injectMock('request', $request);
        session()->set(['isLoggedIn' => true, 'admin' => ['id' => $this->a1, 'full_name' => 'ZZUJI zzujisesi1', 'username' => 'zzujisesi1', 'role' => 'admin']]);
        session()->remove(['success', 'error']);
        $c = new \App\Controllers\Admin\Sesi();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $kelas, string $metode, string $peran, ?array $json = null, ?int $tokenId = null): array
    {
        ApiAuth::set(['id' => $peran === 'admin' ? $this->a1 : 3, 'full_name' => 'ZZUJI ' . $peran, 'username' => 'zzujisesi1', 'role' => $peran], $tokenId);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/sistem/sesi'), null, new \CodeIgniter\HTTP\UserAgent()))
            ->withMethod($json !== null ? 'POST' : 'GET');
        if ($json !== null) {
            $request->setHeader('Content-Type', 'application/json');
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
