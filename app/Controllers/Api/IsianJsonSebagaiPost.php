<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Untuk API turunan controller formulir web publik (PklAjukan, BiodataPublik): isian JSON aplikasi dimasukkan ke POST, karena
 * logika web membaca getPost(). Semua nilai jadi teks seperti kiriman peramban: true → "1", false/null → "".
 */
trait IsianJsonSebagaiPost
{
    private function pakaiIsianJson(RequestInterface $request): void
    {
        if (stripos($request->getHeaderLine('Content-Type'), 'application/json') === false) {
            return;
        }
        $json = $request->getJSON(true);
        if (is_array($json)) {
            $request->setGlobal('post', self::sebagaiPost($json));
        }
    }

    /**
     * @param array<mixed> $json
     *
     * @return array<mixed>
     */
    private static function sebagaiPost(array $json): array
    {
        $out = [];
        foreach ($json as $k => $v) {
            $out[$k] = match (true) {
                is_array($v) => self::sebagaiPost($v),
                is_bool($v)  => $v ? '1' : '',
                $v === null  => '',
                default      => (string) $v,
            };
        }

        return $out;
    }
}
