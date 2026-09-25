<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Form isian biodata siswa (publik, tanpa login).
 *
 * Form dilayani dua pintu yang menuju controller yang sama:
 *   - https://{host}/            → subdomain khusus yang dibagikan ke siswa
 *   - https://{baseURL}/biodata  → cadangan di domain utama
 *
 * Subdomain berbagi document root dengan domain utama (satu aplikasi, satu
 * database). Filter BiodataHost memastikan subdomain hanya melayani form ini;
 * halaman lain dialihkan ke domain utama.
 *
 * Ganti lewat .env bila subdomain berubah:  biodata.host = 'nama.domain.com'
 * (lalu tambahkan juga ke App::$allowedHostnames).
 */
class Biodata extends BaseConfig
{
    public string $host = 'datasiswa.kangmuslim.com';
}
