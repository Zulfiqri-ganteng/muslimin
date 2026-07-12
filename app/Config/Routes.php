<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ===================== PUBLIK =====================
$routes->get('/', 'Publik::home');
$routes->get('jadwal-kelas', 'Publik::jadwalKelas');
$routes->get('jadwal-guru', 'Publik::jadwalGuru');
$routes->get('jadwal-kelas/(:num)/pdf', 'Publik::cetakKelas/$1');
$routes->get('jadwal-guru/(:num)/pdf', 'Publik::cetakGuru/$1');
$routes->get('absensi', 'Publik::absensi');

// Form kesediaan guru (sekunder)
$routes->get('isi', 'Form::index');
$routes->post('kirim', 'Form::submit');
$routes->get('terima-kasih', 'Form::success');
$routes->get('tutup', 'Form::closed');

// Revisi (tautan token dari admin — form ter-isi data lama)
$routes->get('revisi/(:segment)', 'Form::edit/$1');
$routes->post('revisi/(:segment)', 'Form::updateSubmission/$1');

// ===================== ADMIN =====================
$routes->group('admin', static function ($routes) {
    // Autentikasi (tanpa filter)
    $routes->get('login', 'Admin\Auth::login');
    $routes->post('login', 'Admin\Auth::attemptLogin');
    $routes->get('logout', 'Admin\Auth::logout');

    // Area terproteksi
    $routes->group('', ['filter' => 'auth'], static function ($routes) {
        $routes->get('/', 'Admin\Dashboard::index');
        $routes->get('dashboard', 'Admin\Dashboard::index');

        // Submissions
        $routes->get('submissions', 'Admin\Submissions::index');
        $routes->get('submissions/view/(:num)', 'Admin\Submissions::view/$1');
        $routes->post('submissions/status/(:num)', 'Admin\Submissions::updateStatus/$1');
        $routes->get('submissions/delete/(:num)', 'Admin\Submissions::delete/$1');

        // ===== MASTER DATA (Penjadwalan KBM) =====
        $routes->group('master', static function ($routes) {
            // Guru
            $routes->get('guru', 'Admin\Master\Guru::index');
            $routes->post('guru', 'Admin\Master\Guru::store');
            $routes->post('guru/(:num)', 'Admin\Master\Guru::update/$1');
            $routes->get('guru/delete/(:num)', 'Admin\Master\Guru::delete/$1');
            $routes->get('guru/export', 'Admin\Master\Guru::export');
            $routes->get('guru/template', 'Admin\Master\Guru::template');
            $routes->post('guru/import-preview', 'Admin\Master\Guru::importPreview');
            $routes->post('guru/import-commit', 'Admin\Master\Guru::importCommit');
            $routes->post('guru/bulk-delete', 'Admin\Master\Guru::bulkDelete');
            $routes->get('guru/import-kesediaan', 'Admin\Master\Guru::importFromSubmissions');

            // Mata Pelajaran
            $routes->get('mapel', 'Admin\Master\MataPelajaran::index');
            $routes->post('mapel', 'Admin\Master\MataPelajaran::store');
            $routes->post('mapel/(:num)', 'Admin\Master\MataPelajaran::update/$1');
            $routes->get('mapel/delete/(:num)', 'Admin\Master\MataPelajaran::delete/$1');
            $routes->post('mapel/kompetensi/(:num)', 'Admin\Master\MataPelajaran::kompetensi/$1');
            $routes->get('mapel/export', 'Admin\Master\MataPelajaran::export');
            $routes->get('mapel/template', 'Admin\Master\MataPelajaran::template');
            $routes->post('mapel/import-preview', 'Admin\Master\MataPelajaran::importPreview');
            $routes->post('mapel/import-commit', 'Admin\Master\MataPelajaran::importCommit');
            $routes->post('mapel/bulk-delete', 'Admin\Master\MataPelajaran::bulkDelete');

            // Kelas
            $routes->get('kelas', 'Admin\Master\Kelas::index');
            $routes->post('kelas', 'Admin\Master\Kelas::store');
            $routes->post('kelas/(:num)', 'Admin\Master\Kelas::update/$1');
            $routes->get('kelas/delete/(:num)', 'Admin\Master\Kelas::delete/$1');
            $routes->get('kelas/export', 'Admin\Master\Kelas::export');
            $routes->get('kelas/template', 'Admin\Master\Kelas::template');
            $routes->post('kelas/import-preview', 'Admin\Master\Kelas::importPreview');
            $routes->post('kelas/import-commit', 'Admin\Master\Kelas::importCommit');
            $routes->post('kelas/bulk-delete', 'Admin\Master\Kelas::bulkDelete');

            // Pengampu (penugasan)
            $routes->get('pengampu', 'Admin\Master\Pengampu::index');
            $routes->post('pengampu', 'Admin\Master\Pengampu::store');
            $routes->post('pengampu/(:num)', 'Admin\Master\Pengampu::update/$1');
            $routes->get('pengampu/delete/(:num)', 'Admin\Master\Pengampu::delete/$1');
            $routes->get('pengampu/export', 'Admin\Master\Pengampu::export');
            $routes->post('pengampu/bulk-delete', 'Admin\Master\Pengampu::bulkDelete');

            // Ketersediaan Guru
            $routes->get('ketersediaan', 'Admin\Master\Ketersediaan::index');
            $routes->post('ketersediaan', 'Admin\Master\Ketersediaan::save');

            // Jurusan
            $routes->get('jurusan', 'Admin\Master\Jurusan::index');
            $routes->post('jurusan', 'Admin\Master\Jurusan::store');
            $routes->post('jurusan/(:num)', 'Admin\Master\Jurusan::update/$1');
            $routes->get('jurusan/delete/(:num)', 'Admin\Master\Jurusan::delete/$1');
            $routes->get('jurusan/export', 'Admin\Master\Jurusan::export');
            $routes->get('jurusan/template', 'Admin\Master\Jurusan::template');
            $routes->post('jurusan/import-preview', 'Admin\Master\Jurusan::importPreview');
            $routes->post('jurusan/import-commit', 'Admin\Master\Jurusan::importCommit');
            $routes->post('jurusan/bulk-delete', 'Admin\Master\Jurusan::bulkDelete');

            // Hari
            $routes->get('hari', 'Admin\Master\Hari::index');
            $routes->post('hari', 'Admin\Master\Hari::store');
            $routes->post('hari/(:num)', 'Admin\Master\Hari::update/$1');
            $routes->get('hari/delete/(:num)', 'Admin\Master\Hari::delete/$1');
            $routes->get('hari/export', 'Admin\Master\Hari::export');
            $routes->get('hari/template', 'Admin\Master\Hari::template');
            $routes->post('hari/import-preview', 'Admin\Master\Hari::importPreview');
            $routes->post('hari/import-commit', 'Admin\Master\Hari::importCommit');
            $routes->post('hari/bulk-delete', 'Admin\Master\Hari::bulkDelete');

            // Jam Pelajaran
            $routes->get('jam', 'Admin\Master\JamPelajaran::index');
            $routes->post('jam', 'Admin\Master\JamPelajaran::store');
            $routes->post('jam/(:num)', 'Admin\Master\JamPelajaran::update/$1');
            $routes->get('jam/delete/(:num)', 'Admin\Master\JamPelajaran::delete/$1');
            $routes->get('jam/export', 'Admin\Master\JamPelajaran::export');
            $routes->get('jam/template', 'Admin\Master\JamPelajaran::template');
            $routes->post('jam/import-preview', 'Admin\Master\JamPelajaran::importPreview');
            $routes->post('jam/import-commit', 'Admin\Master\JamPelajaran::importCommit');
            $routes->post('jam/bulk-delete', 'Admin\Master\JamPelajaran::bulkDelete');
        });

        // ===== PENJADWALAN =====
        $routes->get('jadwal', 'Admin\Jadwal::index');
        $routes->post('jadwal/place', 'Admin\Jadwal::place');
        $routes->post('jadwal/remove', 'Admin\Jadwal::remove');
        $routes->post('jadwal/bulk-remove', 'Admin\Jadwal::bulkRemove');
        $routes->post('jadwal/move', 'Admin\Jadwal::move');
        $routes->post('jadwal/generate', 'Admin\Jadwal::generate');
        $routes->get('jadwal/template', 'Admin\Jadwal::template');
        $routes->post('jadwal/import-preview', 'Admin\Jadwal::importPreview');
        $routes->post('jadwal/import-commit', 'Admin\Jadwal::importCommit');

        // Jadwal per guru (untuk dibagikan ke tiap guru; cetak via Admin\Cetak)
        $routes->get('jadwal-guru', 'Admin\JadwalGuru::index');

        // ===== ABSENSI GURU (manual, per sesi) =====
        $routes->get('absensi', 'Admin\Absensi::index');
        $routes->post('absensi/save', 'Admin\Absensi::save');
        $routes->post('absensi/save-kerja', 'Admin\Absensi::saveKerja');
        $routes->post('absensi/unrecord', 'Admin\Absensi::unrecord');
        $routes->get('absensi/rekap', 'Admin\Absensi::rekap');
        $routes->get('absensi/rekap/guru/(:num)', 'Admin\Absensi::rekapGuru/$1');
        $routes->get('absensi/rekap/(:segment)', 'Admin\Absensi::rekap/$1');

        // ===== LAPORAN KURIKULUM =====
        $routes->get('kurikulum/dashboard', 'Admin\Kurikulum::dashboard');
        $routes->get('kurikulum/rekap', 'Admin\Kurikulum::rekap');
        $routes->get('kurikulum/bentrok', 'Admin\Kurikulum::bentrok');

        // ===== PENGUMUMAN =====
        $routes->get('pengumuman', 'Admin\Pengumuman::index');
        $routes->post('pengumuman', 'Admin\Pengumuman::store');
        $routes->post('pengumuman/(:num)', 'Admin\Pengumuman::update/$1');
        $routes->get('pengumuman/delete/(:num)', 'Admin\Pengumuman::delete/$1');

        // ===== AUDIT LOG =====
        $routes->get('audit', 'Admin\AuditLog::index');
        $routes->get('audit/purge', 'Admin\AuditLog::purge');

        // ===== CETAK / EXPORT KURIKULUM (PDF & Excel) =====
        $routes->get('cetak/jadwal-kelas/(:num)/(:segment)', 'Admin\Cetak::jadwalKelas/$1/$2');
        $routes->get('cetak/jadwal-guru/(:num)/(:segment)', 'Admin\Cetak::jadwalGuru/$1/$2');
        $routes->get('cetak/rekap/(:segment)', 'Admin\Cetak::rekap/$1');

        // Export
        $routes->get('export/excel', 'Admin\Export::excel');
        $routes->get('export/rekap-pdf', 'Admin\Export::recapPdf');
        $routes->get('export/surat/(:num)', 'Admin\Export::surat/$1');

        // Pengaturan sekolah
        $routes->get('settings', 'Admin\Settings::index');
        $routes->post('settings', 'Admin\Settings::save');

        // Profil admin
        $routes->get('profile', 'Admin\Profile::index');
        $routes->post('profile', 'Admin\Profile::update');
        $routes->post('profile/password', 'Admin\Profile::password');
    });
});

// ============================================================
// ===================== API v1 (MOBILE) ======================
// ============================================================
// Dikonsumsi aplikasi Flutter (fluter-muslimin). Respons JSON beramplop
// standar. CORS aktif untuk seluruh /api; endpoint admin dilindungi filter
// 'apiauth' (Bearer token). Tidak mengubah rute web yang sudah live.
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors'], static function ($routes) {

    // ---------- Auth ----------
    $routes->post('auth/login', 'Auth::login');
    $routes->post('auth/biometric/login', 'Auth::biometricLogin'); // login sidik jari (tanpa token)
    $routes->options('(:any)', static fn () => service('response')->setStatusCode(204)); // preflight

    // ---------- APP / OTA (PUBLIK) ----------
    // Bootstrap dipanggil app saat start (info versi + update otomatis).
    $routes->get('app/bootstrap', 'AppController::bootstrap');
    // Daftarkan APK rilis (dipakai skrip release.ps1; auth via header X-Apk-Token).
    $routes->post('app/apk/register', 'AppController::register');

    // ---------- PUBLIK (tanpa token) ----------
    $routes->get('home', 'Publik::home');
    $routes->get('absensi', 'Publik::absensi');
    $routes->get('jadwal/kelas-options', 'Publik::kelasOptions');
    $routes->get('jadwal/guru-options', 'Publik::guruOptions');
    $routes->get('jadwal/kelas/(:num)', 'Publik::jadwalKelas/$1');
    $routes->get('jadwal/guru/(:num)', 'Publik::jadwalGuru/$1');

    // Form kesediaan guru
    $routes->get('form/meta', 'Form::meta');
    $routes->post('form/submit', 'Form::submit');
    $routes->get('form/revisi/(:segment)', 'Form::revisi/$1');
    $routes->post('form/revisi/(:segment)', 'Form::updateRevisi/$1');

    // ---------- ADMIN (butuh token) ----------
    $routes->group('', ['filter' => 'apiauth'], static function ($routes) {
        $routes->get('auth/me', 'Auth::me');
        $routes->post('auth/logout', 'Auth::logout');

        // Kelola login sidik jari (butuh sesi aktif)
        $routes->post('auth/biometric/register', 'Auth::biometricRegister');
        $routes->post('auth/biometric/disable', 'Auth::biometricDisable');
        $routes->get('auth/biometric/status', 'Auth::biometricStatus');

        // Dashboard
        $routes->get('admin/dashboard', 'Dashboard::index');

        // Submissions (kesediaan guru)
        $routes->get('admin/submissions', 'Submissions::index');
        $routes->get('admin/submissions/(:num)', 'Submissions::view/$1');
        $routes->post('admin/submissions/(:num)/status', 'Submissions::updateStatus/$1');
        $routes->delete('admin/submissions/(:num)', 'Submissions::delete/$1');

        // ---------- MASTER DATA ----------
        // Dropdown pendukung form (guru/mapel/kelas/jurusan)
        $routes->get('admin/master/options', 'Admin\Options::index');

        // 5 master data ber-pola CRUD identik (guru, mapel, kelas, jurusan, hari)
        foreach (['guru' => 'Guru', 'mapel' => 'Mapel', 'kelas' => 'Kelas', 'jurusan' => 'Jurusan', 'hari' => 'Hari'] as $seg => $ctrl) {
            $routes->get("admin/master/{$seg}", "Admin\\{$ctrl}::index");
            $routes->post("admin/master/{$seg}", "Admin\\{$ctrl}::store");
            $routes->post("admin/master/{$seg}/bulk-delete", "Admin\\{$ctrl}::bulkDestroy");
            $routes->post("admin/master/{$seg}/(:num)", "Admin\\{$ctrl}::update/\$1");
            $routes->delete("admin/master/{$seg}/(:num)", "Admin\\{$ctrl}::destroy/\$1");
        }
        // Mapel — kompetensi (guru pengampu) & daftar kelompok
        $routes->get('admin/master/mapel/kelompok-list', 'Admin\Mapel::kelompokList');
        $routes->get('admin/master/mapel/(:num)/kompetensi', 'Admin\Mapel::kompetensiGet/$1');
        $routes->post('admin/master/mapel/(:num)/kompetensi', 'Admin\Mapel::kompetensiSet/$1');

        // Jam Pelajaran (daftar per shift)
        $routes->get('admin/master/jam', 'Admin\JamPelajaran::index');
        $routes->post('admin/master/jam', 'Admin\JamPelajaran::store');
        $routes->post('admin/master/jam/bulk-delete', 'Admin\JamPelajaran::bulkDestroy');
        $routes->post('admin/master/jam/(:num)', 'Admin\JamPelajaran::update/$1');
        $routes->delete('admin/master/jam/(:num)', 'Admin\JamPelajaran::destroy/$1');

        // Pengampu / Penugasan (daftar per kelas)
        $routes->get('admin/master/pengampu', 'Admin\Pengampu::index');
        $routes->post('admin/master/pengampu', 'Admin\Pengampu::store');
        $routes->post('admin/master/pengampu/(:num)', 'Admin\Pengampu::update/$1');
        $routes->delete('admin/master/pengampu/(:num)', 'Admin\Pengampu::destroy/$1');

        // Ketersediaan Guru
        $routes->get('admin/master/ketersediaan', 'Admin\Ketersediaan::index');
        $routes->post('admin/master/ketersediaan', 'Admin\Ketersediaan::save');

        // ---------- PENGUMUMAN ----------
        $routes->get('admin/pengumuman', 'Admin\Pengumuman::index');
        $routes->post('admin/pengumuman', 'Admin\Pengumuman::store');
        $routes->post('admin/pengumuman/(:num)', 'Admin\Pengumuman::update/$1');
        $routes->delete('admin/pengumuman/(:num)', 'Admin\Pengumuman::destroy/$1');

        // ---------- ABSENSI ----------
        $routes->get('admin/absensi', 'Admin\Absensi::index');
        $routes->post('admin/absensi/save', 'Admin\Absensi::save');
        $routes->post('admin/absensi/save-kerja', 'Admin\Absensi::saveKerja');
        $routes->post('admin/absensi/unrecord', 'Admin\Absensi::unrecord');
        $routes->get('admin/absensi/rekap', 'Admin\Absensi::rekap');
        $routes->get('admin/absensi/rekap/export/(:segment)', 'Admin\Absensi::rekapExport/$1');
        $routes->get('admin/absensi/rekap/(:num)', 'Admin\Absensi::rekapGuru/$1');

        // ---------- PROFIL & PENGATURAN ----------
        $routes->get('admin/profile', 'Admin\Profile::show');
        $routes->post('admin/profile', 'Admin\Profile::update');
        $routes->post('admin/profile/password', 'Admin\Profile::password');
        $routes->get('admin/settings', 'Admin\Settings::show');
        $routes->post('admin/settings', 'Admin\Settings::save');
    });
});
