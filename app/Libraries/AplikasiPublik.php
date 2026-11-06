<?php

namespace App\Libraries;

/**
 * Halaman publik UNDUH APLIKASI ANDROID (`/aplikasi`, 2026-11-07 — aplikasi belum rilis di Play Store) dan API-nya
 * (`GET api/v1/publik/aplikasi`). SATU sumber isi untuk web & aplikasi: info berkas APK terbaru (dibaca ApkManager langsung dari
 * berkas di public/downloads/apk/ — yang juga dipakai pembaruan otomatis aplikasi), langkah memasang, tanya-jawab, dan tautan.
 *
 * Tautan "Aplikasi Android" di situs (kotak layanan, kaki halaman, beranda API) = tautan(): isian Pengaturan Sekolah → "Tautan unduh
 * aplikasi Android" bila diisi (mis. kelak Play Store), selain itu halaman /aplikasi bila ada berkas APK; kosong = tidak tampil.
 */
final class AplikasiPublik
{
    /** Lama info berkas disimpan di cache (detik) — membaca APK ±90 MB tidak perlu tiap kunjungan. */
    private const CACHE_DETIK = 600;

    /** Tautan unduh aplikasi untuk situs & API: Pengaturan Sekolah (bila diisi) → halaman /aplikasi (bila ada APK) → ''. */
    public static function tautan(array $setting): string
    {
        $atur = trim((string) ($setting['tautan_aplikasi'] ?? ''));
        if ($atur !== '') {
            return $atur;
        }

        return ApkManager::listApks() !== [] ? site_url('aplikasi') : '';
    }

    /**
     * Info berkas APK terbaru. tersedia=false bila folder kosong.
     *
     * @return array{tersedia: bool, versi: string, build: int, ukuran: int, ukuran_teks: string, diperbarui: ?string, catatan: string,
     *               url_unduh: ?string, nama_berkas: ?string}
     */
    public static function info(): array
    {
        $file = ApkManager::listApks()[0] ?? '';
        if ($file === '') {
            return ['tersedia' => false, 'versi' => '', 'build' => 0, 'ukuran' => 0, 'ukuran_teks' => '', 'diperbarui' => null, 'catatan' => '',
                'url_unduh' => null, 'nama_berkas' => null];
        }
        $path  = ApkManager::dir() . $file;
        $waktu = (int) (@filemtime($path) ?: 0);
        $kunci = 'aplikasi_publik2_' . md5($file . '|' . $waktu . '|' . (int) @filemtime(ApkManager::metaPath()) . '|' . self::namaBerkas());
        $simpan = cache($kunci);
        if (is_array($simpan)) {
            return $simpan;
        }
        $apk  = ApkManager::current();
        $meta = ApkManager::readMeta();
        $diunggah = trim((string) ($meta['uploaded_at'] ?? ''));
        $info = [
            'tersedia'    => true,
            'versi'       => (string) $apk['version'],
            'build'       => (int) $apk['build'],
            'ukuran'      => (int) $apk['size'],
            'ukuran_teks' => self::ukuranTeks((int) $apk['size']),
            'diperbarui'  => $diunggah !== '' && strtotime($diunggah) !== false ? date('Y-m-d H:i:s', strtotime($diunggah)) : ($waktu > 0 ? date('Y-m-d H:i:s', $waktu) : null),
            'catatan'     => trim((string) ($meta['notes'] ?? '')),
            'url_unduh'   => (string) $apk['url'],
            'nama_berkas' => self::namaBerkas(), // nama sekolah, BUKAN nama berkas di server (mis. muslimin-21.apk)
        ];
        cache()->save($kunci, $info, self::CACHE_DETIK);

        return $info;
    }

    /**
     * Nama berkas yang dilihat pengguna (teks di halaman, API, dan nama simpanan di HP saat diunduh — atribut `download`): nama sekolah
     * dari Pengaturan Sekolah, mis. "SMK BINA NUSA.apk". Nama berkas di server tidak ditampilkan (klien 2026-11-07).
     */
    public static function namaBerkas(): string
    {
        $nama = trim((string) preg_replace('/[^\p{L}\p{N} ._-]+/u', '', (string) ((new \App\Models\SettingModel())->get()['school_name'] ?? '')));

        return ($nama !== '' ? $nama : 'Aplikasi Sekolah') . '.apk';
    }

    /** 90170663 → "86 MB". */
    public static function ukuranTeks(int $byte): string
    {
        if ($byte <= 0) {
            return '';
        }

        return $byte >= 1048576 ? number_format($byte / 1048576, 0, ',', '.') . ' MB' : max(1, (int) ceil($byte / 1024)) . ' KB';
    }

    /**
     * Langkah memasang (judul + isi), kalimat awam; dipakai web & API.
     *
     * @return list<array{judul: string, isi: string}>
     */
    public static function langkah(string $ukuranTeks = ''): array
    {
        return [
            ['judul' => 'Unduh berkas aplikasi', 'isi' => 'Tekan tombol Unduh aplikasi di halaman ini' . ($ukuranTeks !== '' ? ' (ukuran ' . $ukuranTeks . ', sebaiknya pakai Wi-Fi)' : '') . '. Tunggu sampai unduhan selesai.'],
            ['judul' => 'Buka berkasnya', 'isi' => 'Tekan pemberitahuan "Unduhan selesai", atau buka aplikasi File / Unduhan di HP lalu tekan berkas berakhiran .apk.'],
            ['judul' => 'Izinkan pemasangan', 'isi' => 'Bila muncul "Pemasangan diblokir" atau "Izinkan dari sumber ini", tekan Setelan lalu nyalakan "Izinkan dari sumber ini", kemudian tekan tombol kembali. Ini wajar karena aplikasi belum ada di Play Store.'],
            ['judul' => 'Pasang', 'isi' => 'Tekan Pasang / Instal dan tunggu sampai selesai. Bila muncul peringatan Play Protect, pastikan berkas diunduh dari halaman ini, lalu pilih Detail lainnya → Tetap instal.'],
            ['judul' => 'Buka aplikasinya', 'isi' => 'Tekan Buka. Siswa dan orang tua bisa langsung memakai layanan tanpa akun; guru dan staf masuk dengan akun dari sekolah.'],
        ];
    }

    /**
     * Tanya-jawab halaman aplikasi, format partial public/partials/tanya_jawab: [pertanyaan, jawaban] — teks polos (partial meng-esc).
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function tanyaJawab(array $setting): array
    {
        $sekolah = (string) ($setting['school_name'] ?? 'sekolah');
        $host    = (string) parse_url(site_url('/'), PHP_URL_HOST);

        return [
            ['Apakah aplikasi ini aman?', 'Ya. Aplikasi ini resmi dari ' . $sekolah . ' dan hanya dibagikan lewat halaman ini (alamat ' . $host . '). Jangan memasang berkas aplikasi sekolah yang dikirim dari sumber lain.'],
            ['Kenapa tidak ada di Play Store?', 'Aplikasi belum dirilis di Play Store, jadi untuk sementara dipasang langsung dari berkas APK. Karena itu HP akan meminta izin "pasang dari sumber ini" satu kali.'],
            ['Siapa yang bisa memakai aplikasi ini?', 'Siswa dan orang tua: layanan tanpa akun (mis. ajukan dan lapor PKL, minta surat, isi biodata, jadwal). Guru dan staf: masuk dengan akun yang diberikan sekolah.'],
            ['Bagaimana memperbarui aplikasi?', 'Aplikasi memberi tahu sendiri bila ada versi baru. Ikuti petunjuknya; tidak perlu menghapus aplikasi lama dan data tidak hilang.'],
            ['Bisa dipakai di iPhone?', 'Belum. Pengguna iPhone tetap bisa memakai semua layanan lewat situs ini di peramban (Safari/Chrome).'],
            ['Gagal memasang?', 'Pastikan ruang penyimpanan HP cukup (sekitar dua kali ukuran berkas), unduh ulang bila berkas rusak, dan pastikan izin "pasang dari sumber ini" sudah dinyalakan. Masih gagal? Hubungi sekolah lewat halaman Bantuan.'],
        ];
    }

    /** Teks siap kirim untuk berbagi tautan halaman (WhatsApp / bagikan aplikasi). */
    public static function teksBagikan(array $setting): string
    {
        return 'Unduh aplikasi Android ' . trim((string) ($setting['school_name'] ?? 'sekolah')) . ': ' . site_url('aplikasi');
    }

    /** Tautan Play Store (atau tautan lain) dari Pengaturan Sekolah bila BUKAN halaman /aplikasi itu sendiri, selain itu null. */
    public static function tautanLuar(array $setting): ?string
    {
        $atur = trim((string) ($setting['tautan_aplikasi'] ?? ''));

        return $atur !== '' && rtrim($atur, '/') !== rtrim(site_url('aplikasi'), '/') ? $atur : null;
    }
}
