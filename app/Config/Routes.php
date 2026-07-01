<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ===================== PUBLIK =====================
$routes->get('/', 'Publik::home');
$routes->get('jadwal-kelas', 'Publik::jadwalKelas');
$routes->get('jadwal-guru', 'Publik::jadwalGuru');
$routes->get('jadwal-kelas/(:num)/pdf', 'Publik::cetakKelas/$1');
$routes->get('jadwal-guru/(:num)/pdf', 'Publik::cetakGuru/$1');

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
