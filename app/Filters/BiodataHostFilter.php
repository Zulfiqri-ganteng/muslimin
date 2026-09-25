<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Subdomain biodata (mis. datasiswa.kangmuslim.com) berbagi document root
 * dengan domain utama, jadi secara teknis ia bisa membuka SEMUA halaman —
 * termasuk /admin. Filter global ini membatasinya: di subdomain hanya form
 * biodata (`/` dan `biodata/*`) yang dilayani, selebihnya dialihkan ke
 * alamat yang sama di domain utama. Di domain utama filter ini diam.
 */
class BiodataHostFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $host = strtolower((string) $request->getServer('HTTP_HOST'));
        $host = explode(':', $host)[0]; // buang port bila ada
        if ($host === '' || $host !== strtolower(config('Biodata')->host)) {
            return null;
        }

        $path = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';
        if ($path === '' || $path === 'biodata' || str_starts_with($path, 'biodata/')) {
            return null;
        }

        $query = (string) $request->getServer('QUERY_STRING');

        return redirect()->to(rtrim(config('App')->baseURL, '/') . '/' . $path . ($query !== '' ? '?' . $query : ''));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi
    }
}
