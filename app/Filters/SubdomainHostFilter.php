<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Subdomain publik (datasiswa.kangmuslim.com untuk biodata, pklbinus.kangmuslim.com
 * untuk PKL) berbagi document root dengan domain utama, jadi secara teknis
 * ia bisa membuka SEMUA halaman — termasuk /admin. Filter global ini
 * membatasinya: di tiap subdomain hanya beranda (`/`) dan alamat berawalan
 * modulnya (`biodata/*` atau `pkl/*`) yang dilayani, selebihnya dialihkan ke
 * alamat yang sama di domain utama. Di domain utama dan host lain filter ini diam.
 *
 * Menambah subdomain baru cukup menambah satu baris di peta().
 */
class SubdomainHostFilter implements FilterInterface
{
    /**
     * host (huruf kecil) => awalan alamat yang boleh dilayani di host itu.
     *
     * @return array<string, string>
     */
    private function peta(): array
    {
        return [
            strtolower(config('Biodata')->host) => 'biodata',
            strtolower(config('Pkl')->host)     => 'pkl',
        ];
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $host = strtolower((string) $request->getServer('HTTP_HOST'));
        $host = explode(':', $host)[0]; // buang port bila ada
        if ($host === '') {
            return null;
        }

        $awalan = $this->peta()[$host] ?? null;
        if ($awalan === null) {
            return null;
        }

        $path = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';
        if ($path === '' || $path === $awalan || str_starts_with($path, $awalan . '/')) {
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
