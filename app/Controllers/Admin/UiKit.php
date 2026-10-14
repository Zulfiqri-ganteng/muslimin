<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Tampilan;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Halaman ui-kit (rombak tampilan F1, docs/RENCANA-UPGRADE-TAMPILAN.md): contoh hidup semua kit komponen admin sekaligus halaman
 * uji CSP (selalu Alpine versi CSP). KHUSUS LAPTOP — di hosting 404 (Tampilan::halamanLaptop()). Hanya Admin: alamat ini tidak ada
 * di katalog menu, jadi peran lain ditolak penjaga rute. ?uji=1 menjalankan uji-diri komponen (dibaca tools/uji-peramban.mjs).
 */
class UiKit extends BaseController
{
    public function index()
    {
        if (! Tampilan::halamanLaptop()) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Contoh paginasi: 137 data, 10 per halaman (templat pager "admin"), halaman dipilih lewat ?page_kit=.
        $halaman = max(1, min(14, (int) ($this->request->getGet('page_kit') ?? 3)));
        $ikon    = array_map(static fn (string $f): string => basename($f, '.svg'), glob(APPPATH . 'ThirdParty/flowbite-icons/outline/*.svg') ?: []);

        return view('admin/ui_kit/index', [
            'contohPager' => service('pager')->makeLinks($halaman, 10, 137, 'admin', 0, 'kit'),
            'ikonDaftar'  => $ikon,
            'modeUji'     => $this->request->getGet('uji') === '1',
        ]);
    }
}
