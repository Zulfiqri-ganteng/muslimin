<?php

namespace App\Commands;

use App\Libraries\DashboardData;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji Dashboard Android = web (2026-11-07): D1 sumber data bersama (Libraries\DashboardData), D2 API admin/dashboard (kolom lama
 * utuh + kolom baru), D3 auth/me memuat `wali_kelas`. Hanya MEMBACA data (tidak ada transaksi/penulisan).
 * `--fixture <folder>` menyimpan respons API (admin_dashboard.json) untuk tes aplikasi.
 */
class UjiDashboard extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-dashboard';
    protected $description = 'Uji dashboard admin web & API (sumber data sama), plus kolom wali_kelas di auth/me.';
    protected $usage       = 'dev:uji-dashboard [--fixture <folder>]';

    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . mb_substr($detail, 0, 300) : ''), $ok ? 'green' : 'red');
    }

    public function run(array $params)
    {
        try {
            $this->uji();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }
        CLI::newLine();
        CLI::write("Hasil: {$this->lulus} lulus, {$this->gagal} gagal", $this->gagal === 0 ? 'green' : 'red');
    }

    private function uji(): void
    {
        $admin = db_connect()->table('admins')->where('role', 'admin')->where('aktif', 1)->orderBy('id')->get()->getRowArray();
        $this->cek('ada akun Admin aktif untuk uji', $admin !== null);
        if ($admin === null) {
            return;
        }

        CLI::write('== D1 Sumber data bersama ==', 'yellow');
        $data = new DashboardData();
        $d    = $data->semua('admin');
        $this->cek('semua(): kunci lengkap', array_keys($d) === ['kur', 'stats', 'setting', 'absensi', 'ujian', 'siswa', 'jpTugas', 'modul', 'tindakan', 'aktivitas', 'tren'], implode(',', array_keys($d)));
        $this->cek('tindakan: tiap butir punya tingkat, n > 0, teks, url & alamat relatif', array_reduce($d['tindakan'], static fn (bool $ok, array $t): bool => $ok
            && in_array($t['tingkat'], ['kritis', 'awas', 'info'], true) && $t['n'] > 0 && $t['teks'] !== '' && str_starts_with($t['alamat'], 'admin/')
            && str_ends_with($t['url'], $t['alamat']), true), json_encode($d['tindakan']));
        $baris = $data->barisModul($d['modul']);
        $this->cek('barisModul: minimal baris Kesediaan, bentuk [judul, ikon, ket, ket2|null, alamat]', $baris !== [] && end($baris)[0] === 'Kesediaan guru mengajar'
            && array_reduce($baris, static fn (bool $ok, array $b): bool => $ok && count($b) === 5 && is_string($b[2]) && str_starts_with($b[4], 'admin/'), true));
        $peranLain = $data->tindakan($d['kur'], $d['absensi'], $d['ujian'], $d['siswa'], $d['modul'], 'guru_piket');
        $this->cek('tindakan: butir Periksa Data Siswa hanya untuk peran yang boleh membuka Master Siswa', ! in_array('admin/master/siswa/periksa#ganda', array_column($peranLain, 'alamat'), true));

        CLI::write('== D2 API admin/dashboard ==', 'yellow');
        [$status, $isi] = $this->api('Dashboard', 'index', 'admin/dashboard', $admin, 'admin_dashboard');
        $api = $isi['data'] ?? [];
        $this->cek('200', $status === 200, (string) $status);
        $this->cek('kolom lama utuh (aplikasi lama tetap jalan)', array_diff(['school', 'admin', 'absensi', 'kesediaan', 'penjadwalan', 'beban_chart', 'recent'], array_keys($api)) === []);
        $this->cek('kolom baru ada', array_diff(['siswa', 'jp_tugas', 'tindakan', 'aktivitas', 'tren', 'ujian', 'modul'], array_keys($api)) === [], implode(',', array_keys($api)));
        $this->cek('tindakan API = web (teks & alamat sama)', array_column($api['tindakan'] ?? [], 'teks') === array_column($d['tindakan'], 'teks')
            && array_column($api['tindakan'] ?? [], 'alamat') === array_column($d['tindakan'], 'alamat'));
        $this->cek('modul API = kalimat web', array_column($api['modul'] ?? [], 'ket') === array_column($baris, 2));
        $this->cek('siswa & jp_tugas = web', ($api['siswa']['aktif'] ?? null) === (int) $d['siswa']['aktif'] && ($api['jp_tugas'] ?? null) === (int) $d['jpTugas']);
        $this->cek('ujian: 4 gelombang berlabel + slug', count($api['ujian']['kartu'] ?? []) === count($d['ujian']['kartu'])
            && array_reduce($api['ujian']['kartu'] ?? [], static fn (bool $ok, array $u): bool => $ok && $u['label'] !== '' && $u['slug'] !== '', true));
        $this->cek('aktivitas: nama tidak kosong (Akun terhapus / Sistem)', array_reduce($api['aktivitas'] ?? [], static fn (bool $ok, array $a): bool => $ok && $a['nama'] !== '', true));
        $this->cek('tren: hadir + telat + izin + sakit + alpa = n', array_reduce($api['tren'] ?? [], static fn (bool $ok, array $t): bool => $ok
            && $t['hadir'] + $t['telat'] + $t['izin'] + $t['sakit'] + $t['alpa'] === $t['n'], true));

        CLI::write('== D3 auth/me: wali_kelas ==', 'yellow');
        [$st, $me] = $this->api('Auth', 'me', 'auth/me', $admin, '');
        $this->cek('auth/me 200 + admin.wali_kelas bool (Admin = false)', $st === 200 && ($me['data']['admin']['wali_kelas'] ?? null) === false, json_encode($me['data']['admin'] ?? null));
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $kelas, string $metode, string $alamat, array $admin, string $fixture): array
    {
        \App\Libraries\ApiAuth::set($admin);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/' . $alamat), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $nama = '\\App\\Controllers\\Api\\' . $kelas;
        $c    = new $nama();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}();
        \App\Libraries\ApiAuth::clear();
        $isi = json_decode((string) $r->getBody(), true) ?: [];
        $dir = (string) (CLI::getOption('fixture') ?? '');
        if ($fixture !== '' && $dir !== '') {
            @mkdir($dir, 0775, true);
            file_put_contents(rtrim($dir, '/\\') . '/' . $fixture . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return [$r->getStatusCode(), $isi];
    }
}
