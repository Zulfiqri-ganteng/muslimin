<?php

/**
 * =====================================================================
 * Cache Helper — pola cache terpusat untuk seluruh aplikasi.
 * =====================================================================
 * Dimuat global lewat app/Config/Autoload.php ($helpers = ['cache']).
 *
 * Pola yang dipakai: "versioned cache" per modul.
 *  - Setiap modul (guru, mapel, kelas, ...) punya nomor versi di cache.
 *  - Kunci data selalu menyertakan nomor versi tersebut.
 *  - Saat data modul berubah, cukup naikkan versinya (master_data_changed)
 *    → semua cache lama modul itu otomatis tidak terpakai dan akan
 *    kadaluarsa sendiri lewat TTL. Tidak perlu menghapus satu per satu.
 *
 * Contoh pakai di controller:
 *   $data = master_cache('guru', "list|q={$q}|per={$per}|p={$page}", 3600,
 *       static fn () => ['rows' => ..., 'total' => ...]);
 *
 *   // setelah insert/update/delete/import:
 *   master_data_changed('guru');
 */

if (! function_exists('master_cache')) {
    /**
     * Ambil (atau hitung lalu simpan) data cache milik satu modul.
     *
     * @param string   $module   Kunci modul, mis. 'guru', 'mapel', 'kelas'
     * @param string   $key      Kunci unik di dalam modul (boleh mengandung filter)
     * @param int      $ttl      Umur cache dalam detik
     * @param callable $producer Closure penghasil data saat cache kosong
     *
     * @return mixed
     */
    function master_cache(string $module, string $key, int $ttl, callable $producer)
    {
        $ver  = (int) (cache('mdver_' . $module) ?? 0);
        $full = 'md_' . $module . '_v' . $ver . '_' . md5($key);

        return cache()->remember($full, $ttl, $producer);
    }
}

if (! function_exists('master_data_changed')) {
    /**
     * Panggil setiap kali data sebuah modul berubah (create/update/delete/import).
     * Menaikkan versi cache modul tsb + modul lain yang menampilkan datanya,
     * dan menghapus kunci cache tunggal (dropdown/rekap) yang terkait.
     */
    function master_data_changed(string ...$modules): void
    {
        // Modul lain yang ikut basi bila modul kunci berubah
        // (mis. nama guru tampil di daftar kelas sebagai wali kelas).
        $ripple = [
            'guru'         => ['kelas', 'pengampu', 'ketersediaan'],
            'mapel'        => ['pengampu'],
            'kelas'        => ['pengampu', 'siswa'],
            'jurusan'      => ['kelas', 'jabatan'],
            'hari'         => ['ketersediaan'],
            'jam'          => ['ketersediaan'],
            'pengampu'     => [],
            'ketersediaan' => [],
            // Nama jabatan tampil di daftar guru, jadi daftar guru ikut basi.
            'jabatan'      => ['guru'],
            'siswa'        => [],
        ];

        // Kunci cache tunggal yang harus dihapus per modul.
        $singles = [
            'guru'     => ['opt_guru', 'rekap_beban', 'dash_kurikulum'],
            'mapel'    => ['opt_mapel', 'rekap_beban', 'dash_kurikulum'],
            'kelas'    => ['opt_kelas', 'rekap_beban', 'dash_kurikulum'],
            'jurusan'  => ['opt_jurusan'],
            'pengampu' => ['rekap_beban', 'dash_kurikulum'],
            'jabatan'  => ['opt_jabatan', 'jabatan_struktural_ids'],
            // publik_stats_v2 memuat jumlah siswa yang tampil di beranda publik.
            'siswa'    => ['stat_siswa', 'siswa_per_kelas', 'publik_stats_v2'],
        ];

        $bump = [];
        foreach ($modules as $m) {
            $bump[$m] = true;
            foreach ($ripple[$m] ?? [] as $r) {
                $bump[$r] = true;
            }
        }

        $cache = cache();
        foreach (array_keys($bump) as $m) {
            $verKey = 'mdver_' . $m;
            $cache->save($verKey, (int) ($cache->get($verKey) ?? 0) + 1, 0);
        }
        foreach ($modules as $m) {
            foreach ($singles[$m] ?? [] as $key) {
                $cache->delete($key);
            }
        }
    }
}
