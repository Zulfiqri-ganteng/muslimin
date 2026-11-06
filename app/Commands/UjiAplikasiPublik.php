<?php

namespace App\Commands;

use App\Libraries\AplikasiPublik;
use App\Libraries\ApkManager;
use App\Libraries\LayananPublik;
use App\Models\SettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji halaman publik UNDUH APLIKASI ANDROID `/aplikasi` + API `publik/aplikasi` (LY8, 2026-11-07) + tautan "Aplikasi Android" di kotak
 * layanan, kaki halaman & beranda API. Dua keadaan: TANPA berkas APK (belum tersedia) dan DENGAN APK contoh (dibuat sementara di
 * public/downloads/apk/ lalu dihapus; berkas/meta asli yang sudah ada tidak disentuh — bila folder sudah berisi APK, bagian "tanpa APK"
 * dilewati). `--fixture <folder>` menyimpan respons API untuk tes aplikasi Flutter.
 *
 *   php spark dev:uji-aplikasi-publik [--fixture C:\flutter-muslimin\test\fixtures\publik]
 */
class UjiAplikasiPublik extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-aplikasi-publik';
    protected $description = 'Uji halaman publik Unduh Aplikasi Android + API publik/aplikasi.';

    private const BERKAS = 'zzuji-aplikasi-999.apk';

    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . mb_substr($detail, 0, 300) : ''), $ok ? 'green' : 'red');
    }

    public function run(array $params)
    {
        $dir      = ApkManager::dir();
        $dirBaru  = ! is_dir($dir);
        $metaAda  = is_file(ApkManager::metaPath());
        $setting  = (new SettingModel())->get();
        $tanpaAtur = ['tautan_aplikasi' => ''] + $setting;
        try {
            if (ApkManager::listApks() === []) {
                CLI::write('== Tanpa berkas APK ==', 'yellow');
                $this->cek('tautan kosong bila Pengaturan kosong & tanpa APK', AplikasiPublik::tautan($tanpaAtur) === '');
                $this->cek('tautan = isian Pengaturan bila diisi', AplikasiPublik::tautan(['tautan_aplikasi' => 'https://play.google.com/x'] + $setting) === 'https://play.google.com/x');
                $this->cek('kotak layanan "Aplikasi Android" tidak tampil', ! isset(LayananPublik::semua($tanpaAtur)['aplikasi']) || empty(LayananPublik::semua($tanpaAtur)['aplikasi']['tampil'] ?? true) || LayananPublik::semua($tanpaAtur)['aplikasi']['url'] === '');
                $html = $this->web();
                $this->cek('halaman /aplikasi 200: "Aplikasi belum tersedia untuk diunduh" + tanya-jawab', str_contains($html, 'Aplikasi belum tersedia untuk diunduh') && str_contains($html, 'Kenapa tidak ada di Play Store?'));
                [$k, $j] = $this->api('publik_aplikasi_kosong');
                $this->cek('API publik/aplikasi: tersedia=false, url_unduh null, langkah & tanya tetap ada', $k === 200 && ($j['data']['tersedia'] ?? true) === false
                    && array_key_exists('url_unduh', $j['data'] ?? []) && $j['data']['url_unduh'] === null && count($j['data']['langkah'] ?? []) === 5 && count($j['data']['tanya'] ?? []) === 6, json_encode($j));
            } else {
                CLI::write('(folder downloads/apk sudah berisi APK — bagian "tanpa APK" dilewati)', 'yellow');
            }

            CLI::write('== Dengan APK contoh ==', 'yellow');
            if ($dirBaru) {
                mkdir($dir, 0755, true);
            }
            $zip = new \ZipArchive();
            $zip->open($dir . self::BERKAS, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('isi.txt', str_repeat('ZZUJI ', 400000)); // ±2,3 MB tanpa kompresi
            $zip->setCompressionName('isi.txt', \ZipArchive::CM_STORE);
            $zip->close();
            touch($dir . self::BERKAS, time() + 120); // paling baru di folder (listApks urut waktu ubah)
            if (! $metaAda) {
                ApkManager::writeMeta(['min_build' => 1, 'notes' => "Perbaikan uji.\nBaris kedua.", 'uploaded_at' => '2026-11-07 08:00:00']);
            }
            $info = AplikasiPublik::info();
            $this->cek('info(): tersedia, berkas contoh, ukuran teks "2 MB", url unduh downloads/apk/', $info['tersedia'] && $info['nama_berkas'] === self::BERKAS
                && $info['ukuran_teks'] === '2 MB' && str_ends_with((string) $info['url_unduh'], 'downloads/apk/' . self::BERKAS), json_encode($info));
            $this->cek('tautan = halaman /aplikasi (Pengaturan kosong)', AplikasiPublik::tautan($tanpaAtur) === site_url('aplikasi'));
            $tile = LayananPublik::semua($tanpaAtur)['aplikasi'] ?? [];
            $this->cek('kotak layanan "Aplikasi Android": tampil, ke /aplikasi, tab yang sama, untuk siswa/guru/orang tua', ($tile['url'] ?? '') === site_url('aplikasi')
                && empty($tile['luar']) && ($tile['kelompok'] ?? []) === ['siswa', 'guru', 'orang-tua'], json_encode($tile));
            $tileLuar = LayananPublik::semua(['tautan_aplikasi' => 'https://play.google.com/x'] + $setting)['aplikasi'] ?? [];
            $this->cek('Pengaturan berisi Play Store → kotak ke Play Store (tab baru) & tautanLuar terisi', ($tileLuar['url'] ?? '') === 'https://play.google.com/x' && ! empty($tileLuar['luar'])
                && AplikasiPublik::tautanLuar(['tautan_aplikasi' => 'https://play.google.com/x']) === 'https://play.google.com/x' && AplikasiPublik::tautanLuar(['tautan_aplikasi' => site_url('aplikasi')]) === null);

            $html = $this->web();
            $this->cek('halaman /aplikasi: tombol Unduh (atribut download) + ukuran + nama berkas', str_contains($html, 'downloads/apk/' . self::BERKAS) && str_contains($html, ' download>')
                && str_contains($html, 'Unduh aplikasi (2 MB)') && str_contains($html, self::BERKAS));
            $this->cek('… cara memasang 5 langkah + tanya-jawab + bagikan WhatsApp + bantuan', substr_count($html, 'kartu-langkah-no') === 5 && str_contains($html, 'Cara memasang')
                && str_contains($html, 'https://wa.me/?text=') && str_contains($html, 'Bagikan ke teman') && str_contains($html, site_url('bantuan')));
            if (! $metaAda) {
                $this->cek('… catatan rilis tampil ("Yang baru di versi ini")', str_contains($html, 'Yang baru di versi ini') && str_contains($html, 'Perbaikan uji.'));
            }
            $this->cek('… kaki halaman: tombol "Aplikasi Android" ke /aplikasi (tab sama)', (bool) preg_match('~href="' . preg_quote(site_url('aplikasi'), '~') . '" class="kaki-tombol">~', $html));

            [$k, $j] = $this->api('publik_aplikasi');
            $d = $j['data'] ?? [];
            $this->cek('API publik/aplikasi: tersedia, versi/build/ukuran/diperbarui, url_unduh, url_halaman, teks_bagikan, langkah 5, tanya 6 {tanya, jawab}', $k === 200
                && ($d['tersedia'] ?? false) === true && array_key_exists('versi', $d) && array_key_exists('build', $d) && ($d['ukuran'] ?? 0) > 0 && ($d['ukuran_teks'] ?? '') === '2 MB'
                && array_key_exists('diperbarui', $d) && str_ends_with((string) ($d['url_unduh'] ?? ''), self::BERKAS) && ($d['url_halaman'] ?? '') === site_url('aplikasi')
                && str_contains((string) ($d['teks_bagikan'] ?? ''), site_url('aplikasi')) && count($d['langkah'] ?? []) === 5 && isset($d['tanya'][0]['tanya'], $d['tanya'][0]['jawab'])
                && array_key_exists('play_store', $d), json_encode($d));
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            @unlink($dir . self::BERKAS);
            if (! $metaAda) {
                @unlink(ApkManager::metaPath());
            }
            if ($dirBaru) {
                @rmdir($dir);
                @rmdir(dirname($dir)); // public/downloads (bila kosong)
            }
            cache()->deleteMatching('aplikasi_publik_*');
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function web(): string
    {
        \Config\Services::resetSingle('renderer');
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'aplikasi'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $c = new \App\Controllers\Publik();
        $c->initController($request, service('response'), service('logger'));

        return html_entity_decode((string) $c->aplikasi(), ENT_QUOTES | ENT_HTML5, 'UTF-8'); // atribut href disandikan esc(…, 'attr')
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $fixture): array
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/publik/aplikasi'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $c = new \App\Controllers\Api\Publik();
        $c->initController($request, service('response'), service('logger'));
        $r   = $c->aplikasi();
        $isi = json_decode((string) $r->getBody(), true) ?: [];
        $dir = (string) (CLI::getOption('fixture') ?? '');
        if ($dir !== '') {
            @mkdir($dir, 0775, true);
            file_put_contents(rtrim($dir, '/\\') . '/' . $fixture . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return [$r->getStatusCode(), $isi];
    }
}
