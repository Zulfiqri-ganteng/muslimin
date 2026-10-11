<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Filter `csrf` aplikasi (SEC1, 2026-10-12) — dipasang untuk SELURUH rute admin
 * lewat Config\Filters::$filters (sebelumnya hanya modul PKL/Surat/Ujian/Honor/SKBM).
 *
 * Sama persis dengan filter CSRF bawaan CI4, ditambah satu kasus: unggahan yang
 * melebihi `post_max_size` membuat PHP MENGOSONGKAN $_POST & $_FILES — token ikut
 * hilang, sehingga admin akan melihat pesan "halaman kedaluwarsa" yang menyesatkan.
 * Di sini kasus itu dijawab dengan pesan ukuran berkas yang benar.
 */
class CsrfFilter extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $panjang = (int) $request->getServer('CONTENT_LENGTH');
        $batas   = self::keByte((string) ini_get('post_max_size'));

        if ($request->getMethod() === 'POST' && $batas > 0 && $panjang > $batas && $_POST === [] && $_FILES === []) {
            $pesan = sprintf(
                'Berkas terlalu besar: kiriman %s MB, batas server %s MB. Kecilkan berkas lalu coba lagi.',
                self::mb($panjang),
                self::mb($batas)
            );

            if ($request->isAJAX()) {
                return service('response')->setStatusCode(413)
                    ->setJSON(['ok' => false, 'msg' => $pesan, 'pesan' => $pesan, 'message' => $pesan]);
            }

            return redirect()->back()->with('error', $pesan);
        }

        return parent::before($request, $arguments);
    }

    /** "8M" / "512K" / "1G" → byte. */
    private static function keByte(string $nilai): int
    {
        $nilai = trim($nilai);
        $angka = (int) $nilai;

        return match (strtoupper(substr($nilai, -1))) {
            'G'     => $angka * 1024 ** 3,
            'M'     => $angka * 1024 ** 2,
            'K'     => $angka * 1024,
            default => $angka,
        };
    }

    private static function mb(int $byte): string
    {
        return number_format($byte / 1024 / 1024, 1, ',', '.');
    }
}
