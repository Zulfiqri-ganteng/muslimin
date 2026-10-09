<?php

namespace App\Libraries;

/**
 * Pemeriksaan sebelum sebuah surat sekolah di-ACC atau diunduh. Murni (tanpa DB): hasilnya ditampilkan di halaman detail
 * dan dipakai ACC massal — surat yang punya peringatan TIDAK ikut di-ACC massal (harus diperiksa satu per satu).
 *
 * Tingkat: 'bahaya' = surat tidak layak jalan (ACC satuan wajib mencentang "sudah saya periksa");
 *          'awas'   = surat bisa jalan, tetapi ada bagian yang akan tercetak kosong/titik-titik.
 */
final class SuratPeriksa
{
    /** Jenis yang tidak masuk akal tanpa daftar siswa. */
    private const BUTUH_SISWA = [SuratJenis::PERNYATAAN, SuratJenis::BALASAN, SuratJenis::PENARIKAN];

    /** Jenis yang selalu ditujukan ke satu perusahaan. */
    private const BUTUH_PERUSAHAAN = [SuratJenis::BALASAN, SuratJenis::PENARIKAN];

    /**
     * @param array<string, mixed>       $surat baris surat_sekolah (isian JSON boleh sudah didekode di 'isi_arr')
     * @param list<array<string, mixed>> $siswa hasil SuratSekolah::siswa()
     * @param array<string, mixed>       $p     baris pkl_pengaturan
     *
     * @return list<array{tingkat: string, teks: string}>
     */
    public static function untuk(array $surat, array $siswa, array $p): array
    {
        $jenis = (string) $surat['jenis'];
        $isi   = is_array($surat['isi_arr'] ?? null) ? $surat['isi_arr'] : SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''));
        $out   = [];

        if (in_array($jenis, self::BUTUH_SISWA, true) && $siswa === []) {
            $out[] = ['tingkat' => 'bahaya', 'teks' => 'Surat ini belum memuat satu pun siswa.'];
        }
        if ((in_array($jenis, self::BUTUH_PERUSAHAAN, true) || ($isi['mode'] ?? '') === 'perusahaan') && trim((string) ($surat['perusahaan_nama'] ?? '')) === '') {
            $out[] = ['tingkat' => 'bahaya', 'teks' => 'Nama perusahaan tujuan surat belum diisi.'];
        }

        // Surat bernomor memuat blok tanda tangan + "NB" yang diambil dari Pengaturan PKL.
        if (SuratJenis::bernomor($jenis)) {
            if (trim((string) ($p['kepsek_nama'] ?? '')) === '') {
                $out[] = ['tingkat' => 'awas', 'teks' => 'Nama Kepala Sekolah belum diisi di Pengaturan PKL, jadi di bawah tanda tangan Kepala Sekolah hanya titik-titik.'];
            }
            if (trim((string) ($p['waka_hubin_nama'] ?? '')) === '') {
                $out[] = ['tingkat' => 'awas', 'teks' => 'Nama Waka Hubin belum diisi di Pengaturan PKL, jadi di bawah tanda tangan Waka Hubin hanya titik-titik.'];
            }
            if (trim((string) ($p['kontak_surat_nama'] ?? '')) === '') {
                $out[] = ['tingkat' => 'awas', 'teks' => 'Kontak "NB" di bawah surat belum diisi di Pengaturan PKL.'];
            }
        }

        return $out;
    }

    /** @param list<array{tingkat: string, teks: string}> $peringatan */
    public static function adaBahaya(array $peringatan): bool
    {
        return in_array('bahaya', array_column($peringatan, 'tingkat'), true);
    }
}
