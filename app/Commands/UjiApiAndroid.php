<?php

namespace App\Commands;

use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\LayananPublik;
use App\Libraries\MenuAdmin;
use App\Models\PengumumanModel;
use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji API pendukung rombak tampilan aplikasi Android (docs/API-BERANDA-MENU.md):
 *   B1 GET publik/beranda — isi = sumber situs publik web (LayananPublik, Pengaturan, Pengumuman, Foto Sekolah), saklar privasi;
 *   B2 GET auth/menu      — menu = MenuAdmin::sidebar() tersaring hak per peran (Admin, Guru Piket, Operator, Waka Hubin).
 * Pengaturan yang diubah selama uji dikembalikan.
 *
 *   php spark dev:uji-api-android
 */
class UjiApiAndroid extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-api-android';
    protected $description = 'Uji API beranda publik (B1) & menu per akun (B2) untuk aplikasi Android.';

    private int $lulus = 0;
    private int $gagal = 0;

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
        $asli = (new SettingModel())->get();
        try {
            $this->ujiBeranda($asli);
            $this->ujiMenu();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            (new SettingModel())->store(['jadwal_publik' => $asli['jadwal_publik'] ?? 1]);
            ApiAuth::clear();
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // B1 — publik/beranda
    // =================================================================

    private function ujiBeranda(array $asli): void
    {
        CLI::write('== B1: GET publik/beranda ==', 'yellow');
        [$k, $j] = $this->panggil(\App\Controllers\Api\Publik::class, 'beranda');
        $d = $j['data'] ?? [];
        $this->cek('HTTP 200 + semua bagian ada', $k === 200 && array_diff(['sekolah', 'sorotan', 'foto', 'kelompok', 'layanan', 'unggulan', 'statistik', 'grafik_siswa', 'pengumuman', 'tanya_jawab', 'jadwal', 'saklar', 'sekarang', 'web'], array_keys($d)) === [], implode(',', array_keys($d)));
        $s = (new SettingModel())->get();
        $stats = $d['statistik'] ?? [];
        $this->cek('sekolah: nama & "Tentang sekolah" = sumber web (LayananPublik::tentang)', ($d['sekolah']['nama'] ?? null) === (string) $s['school_name'] && ($d['sekolah']['tentang'] ?? '') === LayananPublik::tentang($s, $stats) && $d['sekolah']['tentang'] !== '');

        $semua = LayananPublik::semua($s);
        $kunci = array_column($d['layanan'] ?? [], 'kunci');
        $this->cek('layanan = LayananPublik::semua (urut & jumlah sama), status buka|tutup|null', $kunci === array_keys($semua)
            && array_filter($d['layanan'], static fn (array $l): bool => ! in_array($l['status'], [LayananPublik::BUKA, LayananPublik::TUTUP, null], true)) === [], implode(',', $kunci));
        $this->cek('status tiap layanan sama dengan web', array_filter($d['layanan'], static fn (array $l): bool => $l['status'] !== $semua[$l['kunci']]['status']) === []);
        $kel = array_column($d['kelompok'] ?? [], null, 'kunci');
        $bagianSah = true;
        foreach ($kel as $kk => $g) {
            foreach ($g['bagian'] as $b) {
                $bagianSah = $bagianSah && array_diff($b['layanan'], $kunci) === [] && $b['layanan'] !== [];
            }
        }
        $this->cek('4 kelompok (siswa, guru, orang-tua, sekolah) + bagian berisi kunci layanan yang ada; Profil tanpa bagian', array_keys($kel) === ['siswa', 'guru', 'orang-tua', 'sekolah']
            && $bagianSah && $kel['sekolah']['bagian'] === [] && $kel['siswa']['bagian'] !== [] && str_ends_with((string) $kel['siswa']['ilustrasi'], 'ilustrasi/siswa.svg'));
        $this->cek('unggulan ⊆ layanan, maks 6, sama dengan web', ($d['unggulan'] ?? null) === array_column(LayananPublik::unggulan($s), 'kunci') && count($d['unggulan']) <= 6 && array_diff($d['unggulan'], $kunci) === []);
        $sorotLayanan = array_values(array_filter($d['sorotan'], static fn (array $x): bool => $x['jenis'] === 'layanan'));
        $this->cek('sorotan: ada sambutan; sorotan layanan hanya yang SEDANG DIBUKA', in_array('sambutan', array_column($d['sorotan'], 'jenis'), true)
            && array_filter($sorotLayanan, static fn (array $x): bool => ($semua[$x['aksi']['layanan']]['status'] ?? null) !== LayananPublik::BUKA) === []);
        $this->cek('foto: tiap foto ber-url gambar & tanda banner (boleh kosong)', array_filter($d['foto'], static fn (array $f): bool => ! str_contains((string) $f['gambar'], 'uploads/foto-sekolah/') || ! is_bool($f['banner'])) === []);
        $this->cek('pengumuman: maks 5, kategori sah, gambar URL atau null, url_web', count($d['pengumuman']) <= 5
            && array_filter($d['pengumuman'], static fn (array $p): bool => ! in_array($p['kategori'], PengumumanModel::KATEGORI, true) || ($p['gambar'] !== null && ! str_starts_with($p['gambar'], 'http')) || ! str_contains($p['url_web'], 'pengumuman/')) === []);
        $this->cek('tanya_jawab = LayananPublik::tanyaJawab ({tanya, jawab})', count($d['tanya_jawab']) === count(LayananPublik::tanyaJawab($s)) && isset($d['tanya_jawab'][0]['tanya'], $d['tanya_jawab'][0]['jawab']));
        // Grafik = agregat (per tingkat/jurusan/jenis kelamin/tahun); "nama" hanya nama JURUSAN — tidak ada identitas siswa.
        $g = $d['grafik_siswa'] ?? [];
        $this->cek('statistik & grafik siswa hanya angka agregat (tanpa identitas siswa)', isset($stats['siswa'], $stats['guru'], $g['total'])
            && array_diff(array_keys($g), ['total', 'per_tingkat', 'per_jurusan', 'jenis_kelamin', 'per_tahun', 'tahun']) === []
            && array_filter($g['per_jurusan'] ?? [], static fn (array $x): bool => array_diff(array_keys($x), ['kode', 'nama', 'jumlah']) !== []) === [], json_encode(array_keys($g)));

        // Saklar privasi jadwal: dimatikan → layanan jadwal hilang & pilihan kelas kosong.
        (new SettingModel())->store(['jadwal_publik' => 0]);
        [, $j0] = $this->panggil(\App\Controllers\Api\Publik::class, 'beranda');
        $kunci0 = array_column($j0['data']['layanan'], 'kunci');
        (new SettingModel())->store(['jadwal_publik' => 1]);
        [, $j1] = $this->panggil(\App\Controllers\Api\Publik::class, 'beranda');
        $this->cek('jadwal_publik dimatikan → layanan jadwal disembunyikan & kelas kosong; dinyalakan → kelas terisi', ! in_array('jadwal_kelas', $kunci0, true) && $j0['data']['jadwal']['kelas'] === []
            && $j0['data']['saklar']['jadwal_publik'] === false && in_array('jadwal_kelas', array_column($j1['data']['layanan'], 'kunci'), true) && $j1['data']['jadwal']['kelas'] !== []);
        (new SettingModel())->store(['jadwal_publik' => $asli['jadwal_publik'] ?? 1]);
    }

    // =================================================================
    // B2 — auth/menu
    // =================================================================

    private function ujiMenu(): void
    {
        CLI::newLine();
        CLI::write('== B2: GET auth/menu ==', 'yellow');
        $sidebar = MenuAdmin::sidebar();

        [$k, $j] = $this->panggil(\App\Controllers\Api\Auth::class, 'menu', 'admin');
        $g = $j['data']['grup'] ?? [];
        $this->cek('Admin: 200, semua grup menu samping web (urut sama) + bagian KURIKULUM/LAYANAN SEKOLAH', $k === 200 && count($g) === count($sidebar)
            && array_column($g, 'bagian') === array_column($sidebar, 'bagian') && in_array('KURIKULUM', array_column($g, 'bagian'), true), json_encode(array_column($g, 'judul')));
        $judul = array_column($g, 'judul');
        $this->cek('judul grup ditulis seperti web ("Uji Kompetensi (UKK)", "PKL / Prakerin", "Guru Piket")', in_array('Uji Kompetensi (UKK)', $judul, true) && in_array('PKL / Prakerin', $judul, true) && in_array('Guru Piket', $judul, true), json_encode($judul));
        $semuaItem = array_merge(...array_column($g, 'item'));
        $segera    = array_column(array_filter($semuaItem, static fn (array $i): bool => $i['segera']), 'label');
        $this->cek('item fitur belum jadi bertanda segera (Supervisi, Beasiswa) & tiap item punya kunci katalog + ikon', in_array('Jadwal Supervisi', $segera, true) && in_array('Beasiswa Sekolah', $segera, true)
            && array_filter($semuaItem, static fn (array $i): bool => $i['kunci'] === null || $i['ikon'] === '') === [], json_encode($segera));
        $catat = array_values(array_filter($semuaItem, static fn (array $i): bool => $i['kunci'] === 'keterlambatan'))[0] ?? [];
        $this->cek('item membawa alamat_web, level, dan awalan API (Catat Keterlambatan → api ["keterlambatan"])', ($catat['alamat_web'] ?? '') === 'admin/keterlambatan' && ($catat['api'] ?? []) === ['keterlambatan'] && ($catat['level'] ?? null) !== null);

        [, $j] = $this->panggil(\App\Controllers\Api\Auth::class, 'menu', 'guru_piket');
        $g = $j['data']['grup'] ?? [];
        $isi = [];
        foreach ($g as $x) {
            $isi[(string) $x['judul']] = array_map(static fn (array $i): string => $i['label'] . ':' . $i['level'], $x['item']);
        }
        $this->cek('Guru Piket: hanya grup Guru Piket (Jadwal Piket lihat, Catat kelola, Rekap lihat) + Profil; akses_api benar', array_keys($isi) === ['Guru Piket', 'Sistem']
            && $isi['Guru Piket'] === ['Jadwal Piket:lihat', 'Catat Keterlambatan:kelola', 'Rekap Keterlambatan:lihat'] && $j['data']['akses_api'] === ['keterlambatan', 'keterlambatan/rekap'], json_encode($isi));

        foreach (['operator' => ['Kesiswaan', 'PKL / Prakerin', 'Surat Sekolah', 'Arsip & Info', 'Sistem'], 'hubin' => ['PKL / Prakerin', 'Surat Sekolah', 'Sistem']] as $peran => $harap) {
            [, $j] = $this->panggil(\App\Controllers\Api\Auth::class, 'menu', $peran);
            $judul = array_column($j['data']['grup'] ?? [], 'judul');
            $item  = array_merge(...array_column($j['data']['grup'] ?? [], 'item'));
            $boleh = array_filter($item, static fn (array $i): bool => ! HakAkses::boleh($peran, $i['alamat_web'])) === [];
            $this->cek(HakAkses::label($peran) . ': grup = ' . implode(', ', $harap) . ' (tanpa KURIKULUM), semua item boleh dibuka, tanpa item Segera', $judul === $harap && $boleh
                && array_filter($item, static fn (array $i): bool => $i['segera']) === [], json_encode($judul));
        }
    }

    /** @return array{0: int, 1: array} */
    private function panggil(string $kelas, string $metode, ?string $peran = null): array
    {
        if ($peran !== null) {
            ApiAuth::set(['id' => null, 'full_name' => 'ZZUJI ' . $peran, 'role' => $peran]);
        } else {
            ApiAuth::clear();
        }
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', []);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }
}
