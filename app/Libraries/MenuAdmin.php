<?php

namespace App\Libraries;

/**
 * Katalog menu admin — SATU sumber untuk (a) menu samping (layouts/admin), (b) penjaga rute (HakPeran::boleh), dan (c) tabel
 * "Menu" di Sistem → Hak Akses. Rancangan: docs/DESAIN-HAK-AKSES.md.
 *
 * Tiap ENTRI = satu unit hak akses: kunci, label, grup, jenis, awalan alamat yang dicakupnya, dan item menu samping (boleh
 * kosong untuk halaman tanpa menu sendiri). Alamat admin yang tidak cocok entri mana pun = khusus Admin (tolak-secara-bawaan).
 *
 * Jenis entri:
 *   - menu  : Tidak / Lihat (hanya GET: membuka & mengunduh) / Kelola (semua aksi).
 *   - modul : Tidak / Boleh — isinya diatur hak rinci (PKL & Surat Sekolah, lihat PklHak).
 *   - admin : khusus Admin, tidak bisa diberikan ke peran lain (Kelola Akun, Hak Akses).
 *   - umum  : terbuka bagi semua peran yang login (Profil Saya).
 * Entri 'ikut' (tersembunyi dari tabel Hak Akses) memakai level TERTINGGI dari entri yang diikutinya, mis. akar admin/ujian.
 *
 * URUTAN GRUP & ITEM mengikuti alur kerja sekolah (aturan urutan sidebar): data induk (Kesiswaan, Guru) → kurikulum & jadwal →
 * ujian → UKK → PKL → laboratorium → tata usaha (surat, arsip) → sistem; dalam grup: data acuan → proses → laporan → pengaturan.
 */
final class MenuAdmin
{
    public const MENU  = 'menu';
    public const MODUL = 'modul';
    public const ADMIN = 'admin';
    public const UMUM  = 'umum';

    public const LIHAT  = 'lihat';
    public const KELOLA = 'kelola';
    public const BOLEH  = 'boleh';

    private const I_DOK    = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    private const I_PAPAN  = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';
    private const I_CENTANG = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4';
    private const I_REKAP  = 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    private const I_KALENDER = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
    private const I_ORANG2 = 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z';
    private const I_BUKU   = 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253';
    private const I_PERISAI = 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z';
    private const I_GERIGI = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z';
    private const I_KUNCI  = 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085';

    /**
     * Grup (urutan menu samping) → entri. Entri: [kunci => [label, jenis, item menu samping, opsi]].
     * Item menu samping: [url, label, ikon, persis?]; kosong = halaman tanpa menu sendiri.
     * Opsi: awalan (default = url item), ikut, api, ket (keterangan di Hak Akses).
     *
     * @return list<array{judul: ?string, entri: array<string, array>}>
     */
    private static function definisi(): array
    {
        // Satu item per jenis surat; tepat setelah Surat Balasan: kotak "Lapor Diterima PKL" (laporan siswa → Surat Balasan,
        // terbuka bagi pembuat ATAU penyetuju Surat Balasan — PklHak::hakUntukAlamat).
        $jenisSurat = [];
        foreach (SuratJenis::kode() as $k) {
            $jenisSurat[] = [SuratJenis::alamat($k), SuratJenis::label($k), self::I_DOK, false, ! SuratJenis::siap($k)];
            if ($k === SuratJenis::BALASAN) {
                $jenisSurat[] = [SuratJenis::alamat($k) . '/laporan', 'Lapor Diterima PKL', 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', false, ! SuratJenis::siap($k)];
            }
        }

        return [
            ['judul' => null, 'entri' => [
                'dashboard' => ['Dashboard', self::MENU, [['admin/dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6']]],
            ]],
            ['judul' => 'KESISWAAN', 'entri' => [
                'master_siswa'   => ['Data Siswa', self::MENU, [['admin/master/siswa', 'Data Siswa', 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222']]],
                'master_kelas'   => ['Kelas', self::MENU, [['admin/master/kelas', 'Kelas', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m6-12h2m-2 4h2m-2 4h2']]],
                'master_jurusan' => ['Jurusan', self::MENU, [['admin/master/jurusan', 'Jurusan', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-4h.01M9 7h6m-6 4h6m-2 4h2']]],
                'biodata'        => ['Isian Biodata Siswa', self::MENU, [['admin/biodata', 'Isian Biodata Siswa', 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2']]],
                'tahun_ajaran'   => ['Tahun Ajaran', self::MENU, [], ['awalan' => ['admin/master/tahun-ajaran'], 'ket' => 'Halaman tanpa menu samping (admin/master/tahun-ajaran).']],
            ]],
            ['judul' => 'GURU', 'entri' => [
                'master_guru'         => ['Guru', self::MENU, [['admin/master/guru', 'Guru', self::I_ORANG2]]],
                'master_jabatan'      => ['Jabatan', self::MENU, [['admin/master/jabatan', 'Jabatan', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z']]],
                'master_ketersediaan' => ['Ketersediaan Guru', self::MENU, [['admin/master/ketersediaan', 'Ketersediaan Guru', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z']]],
                'kesediaan'           => ['Data Kesediaan', self::MENU, [['admin/submissions', 'Data Kesediaan', self::I_PAPAN]], ['awalan' => ['admin/submissions', 'admin/export'], 'ket' => 'Termasuk unduhan Excel/PDF kesediaan (admin/export).']],
                'master_pengampu'     => ['Penugasan', self::MENU, [['admin/master/pengampu', 'Penugasan', self::I_CENTANG]]],
                'skbm'                => ['SKBM', self::MENU, [['admin/skbm', 'SKBM', 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z']], ['ket' => 'Sumber ceklis Koreksi honor (data beban mengajar).']],
            ]],
            ['judul' => 'JADWAL & ABSENSI', 'entri' => [
                'master_mapel'  => ['Mata Pelajaran', self::MENU, [['admin/master/mapel', 'Mata Pelajaran', self::I_BUKU]]],
                'master_hari'   => ['Hari', self::MENU, [['admin/master/hari', 'Hari', self::I_KALENDER]]],
                'master_jam'    => ['Jam Pelajaran', self::MENU, [['admin/master/jam', 'Jam Pelajaran', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z']]],
                'jadwal'        => ['Jadwal KBM', self::MENU, [['admin/jadwal', 'Jadwal KBM', self::I_REKAP]], ['awalan' => ['admin/jadwal', 'admin/cetak/jadwal-kelas', 'admin/kurikulum/dashboard'], 'ket' => 'Termasuk cetak jadwal kelas dan dasbor kurikulum.']],
                'jadwal_guru'   => ['Jadwal Guru', self::MENU, [['admin/jadwal-guru', 'Jadwal Guru', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z']], ['awalan' => ['admin/jadwal-guru', 'admin/cetak/jadwal-guru']]],
                'absensi'       => ['Absensi Guru', self::MENU, [['admin/absensi', 'Absensi Guru', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 5l-2 2-1-1']], ['ket' => 'Termasuk pesan WA, tarif transport, dan laporan harian.']],
                'absensi_piket' => ['Jadwal Piket', self::MENU, [['admin/absensi/piket', 'Jadwal Piket', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z']]],
                'bentrok'       => ['Deteksi Bentrok', self::MENU, [['admin/kurikulum/bentrok', 'Deteksi Bentrok', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z']]],
                'rekap_beban'   => ['Rekap Beban', self::MENU, [['admin/kurikulum/rekap', 'Rekap Beban', self::I_REKAP]], ['awalan' => ['admin/kurikulum/rekap', 'admin/cetak/rekap']]],
                'rekap_absensi' => ['Rekap Absensi', self::MENU, [['admin/absensi/rekap', 'Rekap Absensi', self::I_CENTANG]]],
                'fase'          => ['Fase Kurikulum', self::MENU, [], ['awalan' => ['admin/master/fase'], 'ket' => 'Halaman tanpa menu samping (admin/master/fase).']],
            ]],
            ['judul' => 'UJIAN', 'entri' => [
                'ujian_asts1' => ['ASTS 1', self::MENU, [['admin/ujian/asts1', 'ASTS 1', self::I_DOK]]],
                'ujian_asas'  => ['ASAS', self::MENU, [['admin/ujian/asas', 'ASAS', self::I_DOK]]],
                'ujian_asts2' => ['ASTS 2', self::MENU, [['admin/ujian/asts2', 'ASTS 2', self::I_DOK]]],
                'ujian_asat'  => ['ASAT', self::MENU, [['admin/ujian/asat', 'ASAT', self::I_DOK]]],
                // Akar admin/ujian (mengalihkan ke ASTS 1): boleh bila peran memegang salah satu jenis ujian.
                'ujian_akar'  => ['Ujian (akar)', self::MENU, [], ['awalan' => ['admin/ujian'], 'ikut' => ['ujian_asts1', 'ujian_asas', 'ujian_asts2', 'ujian_asat']]],
                'honor_ujian' => ['Honor & Koreksi Ujian', self::MENU, [], [
                    'awalan' => ['admin/ujian/asts1/honor', 'admin/ujian/asas/honor', 'admin/ujian/asts2/honor', 'admin/ujian/asat/honor',
                        'admin/ujian/asts1/pembuat-soal', 'admin/ujian/asas/pembuat-soal', 'admin/ujian/asts2/pembuat-soal', 'admin/ujian/asat/pembuat-soal'],
                    'ket' => 'Tab Honor di halaman tiap ujian (data honor/gaji, Koreksi, pembuat soal). Tanpa ini tab Honor tidak tampil.',
                ]],
                'honor_pengaturan' => ['Pengaturan Honor', self::MENU, [['admin/honor/pengaturan', 'Pengaturan Honor', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z']], ['awalan' => ['admin/honor']]],
            ]],
            ['judul' => 'UJI KOMPETENSI (UKK)', 'entri' => [
                'ukk_paket'    => ['Paket Soal', self::MENU, [['admin/master/paket-soal-ukk', 'Paket Soal', self::I_BUKU]]],
                'ukk_tempat'   => ['Tempat Uji', self::MENU, [['admin/master/tempat-uji', 'Tempat Uji', 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z']]],
                'ukk_penguji'  => ['Penguji Eksternal', self::MENU, [['admin/master/penguji-eksternal', 'Penguji Eksternal', self::I_ORANG2]]],
                'ukk_peserta'  => ['Peserta UKK', self::MENU, [['admin/peserta-ukk', 'Peserta UKK', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z']]],
                'ukk_jadwal'   => ['Jadwal UKK', self::MENU, [['admin/jadwal-ukk', 'Jadwal UKK', self::I_KALENDER]]],
                'ukk_nilai'    => ['Penilaian UKK', self::MENU, [['admin/penilaian-ukk', 'Penilaian UKK', 'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M5 7h4m-4 4h4m-4 4h4M5 7v10a2 2 0 002 2h3']]],
                'ukk_ba'       => ['Berita Acara', self::MENU, [['admin/berita-acara-ukk', 'Berita Acara', self::I_DOK]]],
                'ukk_sertifikat' => ['Sertifikat', self::MENU, [['admin/sertifikat-ukk', 'Sertifikat', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z']]],
                'ukk_rekap'    => ['Rekap UKK', self::MENU, [['admin/laporan-ukk', 'Rekap UKK', self::I_REKAP]]],
            ]],
            ['judul' => 'PKL / PRAKERIN', 'entri' => [
                // Modul: isi menu (Laporan, Tanda Tangan, Pengaturan, …) tampil menurut hak rinci PKL (PklHak).
                'pkl' => ['PKL / Prakerin', self::MODUL, [
                    ['admin/pkl', 'Beranda PKL', 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', true],
                    ['admin/pkl/daftar', 'Kotak Masuk', 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4'],
                    ['admin/pkl/baru', 'Isi atas Nama', 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['admin/pkl/siswa', 'Status Siswa', self::I_CENTANG],
                    ['admin/pkl/prakerin', 'Data Prakerin', self::I_DOK],
                    ['admin/pkl/laporan', 'Laporan Pembayaran', self::I_REKAP],
                    ['admin/pkl/ttd', 'Tanda Tangan', 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
                    ['admin/pkl/pengaturan', 'Pengaturan PKL', self::I_GERIGI],
                ], ['api' => ['pkl'], 'ket' => 'Ajuan PKL siswa, surat izin, biaya, tanda tangan. Aksi di dalamnya diatur di bagian "PKL & Surat".']],
            ]],
            ['judul' => 'LABORATORIUM', 'entri' => [
                'master_lab'       => ['Laboratorium', self::MENU, [['admin/master/lab', 'Laboratorium', 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5']], ['awalan' => ['admin/master/lab', 'admin/lab-gambar/lab']]],
                'master_aset'      => ['Aset / Inventaris', self::MENU, [['admin/master/aset', 'Aset / Inventaris', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4']], ['awalan' => ['admin/master/aset', 'admin/lab-gambar/aset']]],
                'master_sparepart' => ['Sparepart', self::MENU, [['admin/master/sparepart', 'Sparepart', 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z']], ['awalan' => ['admin/master/sparepart', 'admin/lab-gambar/sparepart']]],
                'master_teknisi'   => ['Teknisi', self::MENU, [['admin/master/teknisi', 'Teknisi', self::I_KUNCI . 'm-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26']]],
                'peminjaman'       => ['Peminjaman', self::MENU, [['admin/peminjaman', 'Peminjaman', 'M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4']], ['awalan' => ['admin/peminjaman', 'admin/lab-gambar/peminjaman']]],
                'kerusakan'        => ['Kerusakan', self::MENU, [['admin/kerusakan', 'Kerusakan', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z']], ['awalan' => ['admin/kerusakan', 'admin/lab-gambar/kerusakan']]],
                'perbaikan'        => ['Perbaikan', self::MENU, [['admin/perbaikan', 'Perbaikan', self::I_KUNCI]], ['awalan' => ['admin/perbaikan', 'admin/lab-gambar/perbaikan']]],
                'jadwal_lab'       => ['Jadwal Lab', self::MENU, [['admin/jadwal-lab', 'Jadwal Lab', self::I_KALENDER]]],
                'jurnal_lab'       => ['Jurnal Lab', self::MENU, [['admin/jurnal-lab', 'Jurnal Lab', self::I_CENTANG]]],
                'laporan_lab'      => ['Laporan Lab', self::MENU, [['admin/laporan-lab', 'Laporan Lab', self::I_REKAP]]],
                // Hapus foto (admin/lab-gambar/hapus/{id}): boleh bila memegang salah satu entri ber-foto; controller memeriksa entitasnya.
                'lab_foto'         => ['Foto lab (akar)', self::MENU, [], ['awalan' => ['admin/lab-gambar'], 'ikut' => ['master_lab', 'master_aset', 'master_sparepart', 'peminjaman', 'kerusakan', 'perbaikan']]],
            ]],
            // SURAT SEKOLAH (docs/DESAIN-SURAT-SEKOLAH.md): Daftar Surat + satu menu per jenis surat (dari SuratJenis). Jenis yang
            // halamannya BELUM dibangun (tidak di SuratJenis::SIAP) tampil redup bertanda "Segera". Siapa melihat item jenis =
            // hak rinci per jenis (PklHak).
            ['judul' => 'SURAT SEKOLAH', 'entri' => [
                'surat' => ['Surat Sekolah', self::MODUL, array_merge(
                    [['admin/surat', 'Daftar Surat', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', true]],
                    $jenisSurat
                ), ['api' => ['surat'], 'ket' => 'Daftar Surat dan surat per jenis. Buat/ACC/Unduh tiap jenis diatur di bagian "PKL & Surat".']],
            ]],
            ['judul' => 'ARSIP & INFO', 'entri' => [
                'dokumen'    => ['Arsip Dokumen', self::MENU, [['admin/dokumen', 'Arsip Dokumen', 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z']]],
                'pengumuman' => ['Pengumuman', self::MENU, [['admin/pengumuman', 'Pengumuman', 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z']]],
            ]],
            ['judul' => 'SISTEM', 'entri' => [
                'pengaturan_sekolah' => ['Pengaturan Sekolah', self::MENU, [['admin/settings', 'Pengaturan Sekolah', self::I_GERIGI]]],
                'akun'      => ['Kelola Akun', self::ADMIN, [['admin/akun', 'Kelola Akun', self::I_PERISAI]], ['ket' => 'Khusus Admin: membuat akun dan memilih perannya.']],
                'hak_akses' => ['Hak Akses', self::ADMIN, [['admin/hak-akses', 'Hak Akses', 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z']], ['ket' => 'Khusus Admin: mengatur siapa boleh apa.']],
                'audit'     => ['Audit Log', self::MENU, [['admin/audit', 'Audit Log', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01']]],
                'profil'    => ['Profil Saya', self::UMUM, [['admin/profile', 'Profil Saya', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z']]],
            ]],
        ];
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $katalog = null;

    /**
     * Seluruh entri: kunci => [kunci, label, grup, jenis, item, awalan, ikut, api, ket, tampil].
     * 'tampil' = muncul di tabel Hak Akses (entri 'ikut' disembunyikan).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function katalog(): array
    {
        if (self::$katalog !== null) {
            return self::$katalog;
        }
        $out = [];
        foreach (self::definisi() as $g) {
            foreach ($g['entri'] as $kunci => $e) {
                [$label, $jenis, $item] = $e;
                $opsi   = $e[3] ?? [];
                $awalan = $opsi['awalan'] ?? array_values(array_unique(array_map(static fn (array $it): string => (string) $it[0], array_slice($item, 0, 1))));
                $out[$kunci] = [
                    'kunci'  => $kunci,
                    'label'  => $label,
                    'grup'   => (string) ($g['judul'] ?? 'UMUM'),
                    'jenis'  => $jenis,
                    'item'   => $item,
                    'awalan' => array_values(array_map(static fn (string $a): string => trim(strtolower($a), '/'), $awalan)),
                    'ikut'   => $opsi['ikut'] ?? [],
                    'api'    => $opsi['api'] ?? [],
                    'ket'    => (string) ($opsi['ket'] ?? ''),
                    'tampil' => ($opsi['ikut'] ?? []) === [],
                ];
            }
        }

        return self::$katalog = $out;
    }

    public static function entri(string $kunci): ?array
    {
        return self::katalog()[$kunci] ?? null;
    }

    /**
     * Entri yang mencakup sebuah alamat: awalan TERPANJANG yang cocok per segmen ("admin/pkl" mencakup "admin/pkl/12",
     * bukan "admin/pkl-lain"). null = tidak tercakup entri mana pun (khusus Admin).
     *
     * @param string $alamat jalur relatif tanpa domain, sudah huruf kecil & dirapikan
     */
    public static function cariAlamat(string $alamat): ?array
    {
        $pilih   = null;
        $panjang = -1;
        foreach (self::katalog() as $e) {
            foreach ($e['awalan'] as $a) {
                if (($alamat === $a || str_starts_with($alamat, $a . '/')) && strlen($a) > $panjang) {
                    $pilih   = $e;
                    $panjang = strlen($a);
                }
            }
        }

        return $pilih;
    }

    /**
     * Menu samping dalam bentuk yang dipakai layouts/admin: [['title' => ?string, 'items' => [[url, label, ikon, persis?, segera?], …]], …].
     * Belum disaring per peran (layout menyaringnya dengan HakAkses::boleh).
     *
     * @return list<array{title: ?string, items: list<array>}>
     */
    public static function sidebar(): array
    {
        $out = [];
        foreach (self::definisi() as $g) {
            $items = [];
            foreach ($g['entri'] as $e) {
                foreach ($e[2] as $it) {
                    $items[] = $it;
                }
            }
            if ($items !== []) {
                $out[] = ['title' => $g['judul'], 'items' => $items];
            }
        }

        return $out;
    }

    /**
     * Entri untuk tabel "Menu" di Hak Akses, per grup: hanya jenis menu & modul yang tampil (bukan admin/umum/ikut).
     *
     * @return array<string, list<array<string, mixed>>> judul grup => entri
     */
    public static function untukMatriks(): array
    {
        $out = [];
        foreach (self::katalog() as $e) {
            if ($e['tampil'] && in_array($e['jenis'], [self::MENU, self::MODUL], true)) {
                $out[$e['grup']][] = $e;
            }
        }

        return $out;
    }

    /** Level yang sah untuk entri: menu → lihat|kelola, modul → boleh. */
    public static function levelSah(array $e, string $level): bool
    {
        return $e['jenis'] === self::MODUL ? $level === self::BOLEH : in_array($level, [self::LIHAT, self::KELOLA], true);
    }

    /** Lupakan simpanan (uji). */
    public static function lupakan(): void
    {
        self::$katalog = null;
    }
}
