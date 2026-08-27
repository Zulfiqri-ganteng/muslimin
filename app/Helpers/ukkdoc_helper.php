<?php

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * =====================================================================
 * Helper Dokumen UKK — unggah PDF (kisi-kisi, jobsheet) apa adanya.
 * =====================================================================
 * Beda dari labimage_helper: TIDAK ada konversi (dokumen bukan gambar),
 * cukup validasi PDF + batas ukuran, lalu simpan ke public/uploads/ukk/
 * dengan nama unik. Maksimal 10 MB per berkas.
 *
 * Dipakai web: helper('ukkdoc'); $nama = ukkdoc_save($file, $err);
 */

if (! function_exists('ukkdoc_dir')) {
    /** Path absolut folder penyimpanan (dibuat bila belum ada). */
    function ukkdoc_dir(): string
    {
        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'ukk' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}

if (! function_exists('ukkdoc_url')) {
    /** URL publik sebuah berkas dokumen UKK (atau null bila kosong). */
    function ukkdoc_url(?string $file): ?string
    {
        $file = trim((string) $file);

        return $file === '' ? null : base_url('uploads/ukk/' . $file);
    }
}

if (! function_exists('ukkdoc_delete')) {
    /** Hapus berkas fisik dokumen UKK (aman bila tak ada). */
    function ukkdoc_delete(?string $file): void
    {
        $file = basename(trim((string) $file));
        if ($file === '') {
            return;
        }
        $path = ukkdoc_dir() . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (! function_exists('ukkdoc_save')) {
    /**
     * Simpan satu unggahan PDF. Mengembalikan nama berkas (mis.
     * "ukk_a1b2c3.pdf") atau null bila gagal (alasan diisi di $error).
     */
    function ukkdoc_save(UploadedFile $file, ?string &$error = null, int $maxBytes = 10 * 1024 * 1024): ?string
    {
        $error = null;
        if (! $file->isValid()) {
            $error = 'Berkas tidak valid: ' . $file->getErrorString();

            return null;
        }
        if ($file->getSize() > $maxBytes) {
            $error = 'Ukuran berkas melebihi ' . round($maxBytes / 1024 / 1024) . ' MB.';

            return null;
        }
        $mime = strtolower((string) $file->getMimeType());
        if ($mime !== 'application/pdf') {
            $error = 'Berkas harus berformat PDF.';

            return null;
        }

        $name = 'ukk_' . uniqid('', true) . '.pdf';
        if (! $file->move(ukkdoc_dir(), $name)) {
            $error = 'Gagal menyimpan berkas.';

            return null;
        }

        return $name;
    }
}
