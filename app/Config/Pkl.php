<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Form pengajuan PKL / Prakerin siswa (publik, tanpa login).
 *
 * Form dilayani dua pintu yang menuju controller yang sama:
 *   - https://{host}/            → subdomain khusus yang dibagikan ke siswa
 *   - https://{baseURL}/pkl      → cadangan di domain utama
 *
 * Subdomain berbagi document root dengan domain utama (satu aplikasi, satu
 * database), sama seperti form biodata (Config\Biodata). Filter
 * SubdomainHost memastikan subdomain hanya melayani form ini; halaman lain
 * dialihkan ke domain utama.
 *
 * Ganti lewat .env bila subdomain berubah:  pkl.host = 'nama.domain.com'
 * (lalu tambahkan juga ke App::$allowedHostnames).
 */
class Pkl extends BaseConfig
{
    public string $host = 'pklbinus.kangmuslim.com';
}
