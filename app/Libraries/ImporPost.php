<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Baris kiriman halaman pratinjau impor (admin/master/import_preview — semua Master, Jadwal KBM, Jadwal Ujian).
 * Sejak 2026-11-23 halaman mengirim SATU isian JSON "rows_json" (dulu rows[i][kolom] = puluhan ribu isian → terpotong batas
 * max_input_vars PHP ±1.000 sehingga sebagian baris hilang diam-diam). Bentuk lama rows[...] tetap diterima (peramban yang
 * masih memegang app.js lama).
 */
class ImporPost
{
    /** @return list<array<string, mixed>> */
    public static function baris(IncomingRequest $request): array
    {
        $raw = (string) $request->getPost('rows_json');
        if ($raw !== '') {
            $val = json_decode($raw, true);

            return is_array($val) ? array_values(array_filter($val, 'is_array')) : [];
        }

        return array_values(array_filter((array) $request->getPost('rows'), 'is_array'));
    }
}
