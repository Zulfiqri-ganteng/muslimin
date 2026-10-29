<?php

namespace App\Controllers\Api;

use App\Libraries\ApkManager;
use App\Libraries\ModeMaintenance;

/**
 * Endpoint "bootstrap" aplikasi mobile (fluter-muslimin).
 *
 * Dipanggil app saat START untuk mengetahui:
 *   - apakah ada versi baru (update otomatis / OTA Android),
 *   - apakah update WAJIB (build app di bawah min_build),
 *   - mode pemeliharaan.
 *
 * Semua info APK (versi/build/ukuran) dibaca LANGSUNG dari file APK yang
 * terpasang di public/downloads/apk/ (lihat ApkManager), jadi tak ada nilai
 * yang perlu diisi manual → mustahil "update tersedia terus" karena salah ketik.
 */
class AppController extends BaseApiController
{
    /**
     * GET /api/v1/app/bootstrap — PUBLIK (tanpa token).
     */
    public function bootstrap()
    {
        $apk  = ApkManager::current();
        $meta = ApkManager::readMeta();

        return $this->ok([
            // Kontrol versi (force-update). min_build diatur saat rilis (mandatory).
            'min_build'           => (int) ($meta['min_build'] ?? 1),
            'latest_version'      => $apk['version'] ?: '',

            // Info APK untuk update otomatis (Android). App bandingkan build-nya:
            //   build < min_build  → WAJIB update; build < apk_build → update tersedia.
            'apk_available'       => $apk['available'],
            'apk_url'             => $apk['available'] ? $apk['url'] : null,
            'apk_version'         => $apk['version'],
            'apk_build'           => $apk['build'],
            'apk_size'            => $apk['size'],

            // Mode Maintenance (Sistem → Mode Maintenance; BLUEPRINT-SISTEM.md M1). Dua kunci lama dipertahankan untuk
            // aplikasi yang sudah beredar; rincian di 'maintenance_info' (sama dengan GET app/status).
            'maintenance'         => ModeMaintenance::aktif(),
            'maintenance_message' => ModeMaintenance::status()['pesan'],
            'maintenance_info'    => ModeMaintenance::untukApi(),
        ], 'OK');
    }

    /**
     * GET /api/v1/app/status — PUBLIK & tetap terbuka saat maintenance (dipakai tombol "Coba lagi" di layar perawatan).
     * Ringan: hanya membaca berkas status maintenance, tanpa database.
     */
    public function status()
    {
        return $this->ok(['maintenance' => ModeMaintenance::untukApi(), 'waktu_server' => date('Y-m-d H:i:s')], 'OK');
    }

    /**
     * POST /api/v1/app/apk/register — PUBLIK (auth via token rilis, BUKAN sesi user).
     * Dipanggil skrip rilis (release.ps1) SETELAH APK diunggah via FTP ke
     * public/downloads/apk/. Body kecil (metadata) → lolos batas upload PHP.
     *
     * Header: X-Apk-Token: <APK_UPLOAD_TOKEN dari .env> (dibandingkan constant-time).
     * Body JSON: {version, build, filename?, notes?, min_build?}.
     */
    public function register()
    {
        $expected = (string) env('APK_UPLOAD_TOKEN', '');
        $given    = trim($this->request->getHeaderLine('X-Apk-Token'));
        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            return $this->failure('Token rilis tidak valid.', 401);
        }

        $in = $this->request->getJSON(true);
        if (!is_array($in) || !$in) {
            $in = (array) $this->request->getPost();
        }

        $filename = null;
        if (!empty($in['filename'])) {
            $filename = (string) $in['filename'];
        } elseif (!empty($in['apk_url'])) {
            $filename = basename((string) (parse_url((string) $in['apk_url'], PHP_URL_PATH) ?: ''));
        }

        $version  = trim((string) ($in['version'] ?? ''));
        $build    = (int) ($in['build'] ?? 0);
        $notes    = trim((string) ($in['notes'] ?? ''));
        $minBuild = (isset($in['min_build']) && $in['min_build'] !== '') ? (int) $in['min_build'] : null;

        $res = ApkManager::register($filename, $version, $build, $notes, $minBuild);
        if (!$res['ok']) {
            $status = ($res['code'] ?? '') === 'apk_not_found' ? 404 : 422;
            return $this->failure($res['message'], $status, ['code' => $res['code'] ?? null]);
        }
        return $this->ok($res['data'], $res['message']);
    }
}
