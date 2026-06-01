<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ===================== PUBLIK (GURU) =====================
$routes->get('/', 'Form::home');
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
