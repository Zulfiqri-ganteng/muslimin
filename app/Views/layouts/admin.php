<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Admin') ?> &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <?= view('partials/favicon') ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="adminLayout">

<!-- ===================== LOADING OVERLAY (global, semua form) ===================== -->
<div x-show="loading" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[70] flex items-center justify-center bg-white/60 backdrop-blur-sm">
    <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin h-10 w-10 text-brand-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-sm font-semibold text-brand-700" x-text="pesan">Memproses…</p>
    </div>
</div>

<?php
    $cur = uri_string();
    // Grup menu: title=null artinya item lepas (tanpa judul). Item: [url, label, icon, persis?].
    // URUTAN GRUP mengikuti alur kerja sekolah: data induk (Kesiswaan, Guru) → kurikulum & jadwal → ujian → UKK → PKL →
    // laboratorium → tata usaha (surat, arsip) → sistem. Satu modul = satu grup (laporan ikut modulnya).
    // URUTAN ITEM dalam grup: data acuan (master) → proses harian → laporan/rekap → pengaturan. Data acuan ikut modul yang
    // memakainya (Kelas/Jurusan di Kesiswaan, Mapel/Hari/Jam di Jadwal, Paket Soal/Tempat Uji di UKK, dst.).
    // Alamat halaman TIDAK berubah; menu tiap peran tetap disaring oleh HakAkses (sumber aturan sama dengan penjaga rute).
    $groups = [
        ['title' => null, 'items' => [
            ['admin/dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ]],
        ['title' => 'KESISWAAN', 'items' => [
            ['admin/master/siswa',   'Data Siswa',          'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222'],
            ['admin/master/kelas',   'Kelas',               'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m6-12h2m-2 4h2m-2 4h2'],
            ['admin/master/jurusan', 'Jurusan',             'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-4h.01M9 7h6m-6 4h6m-2 4h2'],
            ['admin/biodata',        'Isian Biodata Siswa', 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2'],
        ]],
        ['title' => 'GURU', 'items' => [
            ['admin/master/guru',         'Guru',              'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
            ['admin/master/jabatan',      'Jabatan',           'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            ['admin/master/ketersediaan', 'Ketersediaan Guru', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['admin/submissions',         'Data Kesediaan',    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['admin/master/pengampu',     'Penugasan',         'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            // Khusus Admin (sumber ceklis Koreksi honor): Operator & Waka Hubin tidak melihat item ini — disaring HakAkses seperti menu lain.
            ['admin/skbm',                'SKBM',              'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ]],
        ['title' => 'JADWAL & ABSENSI', 'items' => [
            ['admin/master/mapel',   'Mata Pelajaran', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['admin/master/hari',    'Hari',           'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['admin/master/jam',     'Jam Pelajaran',  'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['admin/jadwal',         'Jadwal KBM',     'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/jadwal-guru',    'Jadwal Guru',    'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['admin/absensi',        'Absensi Guru',   'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 5l-2 2-1-1'],
            ['admin/absensi/piket',  'Jadwal Piket',   'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin/kurikulum/bentrok', 'Deteksi Bentrok', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['admin/kurikulum/rekap',   'Rekap Beban',     'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/absensi/rekap',     'Rekap Absensi',   'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ]],
        ['title' => 'UJIAN', 'items' => [
            ['admin/ujian/asts1', 'ASTS 1', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/ujian/asas',  'ASAS',   'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/ujian/asts2', 'ASTS 2', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/ujian/asat',  'ASAT',   'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            // Khusus Admin (data gaji): Operator & Waka Hubin tidak melihat item ini — disaring HakAkses seperti menu lain.
            ['admin/honor/pengaturan', 'Pengaturan Honor', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ]],
        ['title' => 'UJI KOMPETENSI (UKK)', 'items' => [
            ['admin/master/paket-soal-ukk',    'Paket Soal',        'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['admin/master/tempat-uji',        'Tempat Uji',        'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin/master/penguji-eksternal', 'Penguji Eksternal', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
            ['admin/peserta-ukk',      'Peserta UKK',   'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            ['admin/jadwal-ukk',       'Jadwal UKK',    'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['admin/penilaian-ukk',    'Penilaian UKK', 'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M5 7h4m-4 4h4m-4 4h4M5 7v10a2 2 0 002 2h3'],
            ['admin/berita-acara-ukk', 'Berita Acara',  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/sertifikat-ukk',   'Sertifikat',    'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['admin/laporan-ukk',      'Rekap UKK',     'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ]],
        ['title' => 'PKL / PRAKERIN', 'items' => [
            ['admin/pkl',            'Beranda PKL',        'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', true],
            ['admin/pkl/daftar',     'Kotak Masuk',        'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4'],
            ['admin/pkl/baru',       'Isi atas Nama',      'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['admin/pkl/siswa',      'Status Siswa',       'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            // Menu bersyarat hak yang diatur Admin (PKL → Hak Akses): tampil hanya bagi yang berhak.
            ['admin/pkl/laporan',    'Laporan Pembayaran', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin/pkl/ttd',        'Tanda Tangan',       'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
            ['admin/pkl/pengaturan', 'Pengaturan PKL',     'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin/pkl/hak-akses',  'Hak Akses PKL',      'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ]],
        ['title' => 'LABORATORIUM', 'items' => [
            ['admin/master/lab',       'Laboratorium',       'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5'],
            ['admin/master/aset',      'Aset / Inventaris',  'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['admin/master/sparepart', 'Sparepart',          'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z'],
            ['admin/master/teknisi',   'Teknisi',            'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26'],
            ['admin/peminjaman',       'Peminjaman',         'M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4'],
            ['admin/kerusakan',        'Kerusakan',          'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['admin/perbaikan',        'Perbaikan',          'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085'],
            ['admin/jadwal-lab',       'Jadwal Lab',         'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['admin/jurnal-lab',       'Jurnal Lab',         'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            ['admin/laporan-lab',      'Laporan Lab',        'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ]],
        // SURAT SEKOLAH (docs/DESAIN-SURAT-SEKOLAH.md): Daftar Surat + satu menu per jenis surat. Item jenis dibangun dari
        // Libraries\SuratJenis; yang halamannya BELUM dibangun (tidak ada di SuratJenis::SIAP) tampil redup bertanda "Segera"
        // dan tidak bisa diklik. Elemen ke-5 item = 'segera'. Alamat tiap item tetap dipakai sebagai penentu SIAPA YANG MELIHAT
        // (HakAkses::boleh → hak 'surat_sekolah'), jadi menu "Segera" pun hanya terlihat oleh yang kelak berhak membukanya.
        ['title' => 'SURAT SEKOLAH', 'items' => array_merge(
            [['admin/surat', 'Daftar Surat', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', true]],
            array_map(
                static fn (string $k): array => [\App\Libraries\SuratJenis::alamat($k), \App\Libraries\SuratJenis::label($k), 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', false, ! \App\Libraries\SuratJenis::siap($k)],
                \App\Libraries\SuratJenis::kode()
            )
        )],
        ['title' => 'ARSIP & INFO', 'items' => [
            ['admin/dokumen',    'Arsip Dokumen', 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
            ['admin/pengumuman', 'Pengumuman',    'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
        ]],
        ['title' => 'SISTEM', 'items' => [
            ['admin/settings', 'Pengaturan Sekolah', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin/akun',     'Kelola Akun',       'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['admin/audit',    'Audit Log',         'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            ['admin/profile',  'Profil Saya',       'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ]],
    ];
    $admin = session('admin') ?? [];

    // Menu per peran: hanya tampilkan yang boleh dibuka (sumber aturan SAMA dengan
    // penjaga rute — Config\Peran — jadi menu & akses tak mungkin berselisih).
    $peranKini = (string) ($admin['role'] ?? '');
    $groups    = array_values(array_filter(array_map(static function (array $g) use ($peranKini): array {
        $g['items'] = array_values(array_filter(
            $g['items'],
            static fn (array $it): bool => \App\Libraries\HakAkses::boleh($peranKini, $it[0])
        ));
        return $g;
    }, $groups), static fn (array $g): bool => $g['items'] !== []));
    $labelPeranKini  = \App\Libraries\HakAkses::label($peranKini);
    $bolehFormPublik = \App\Libraries\HakAkses::boleh($peranKini, 'admin/submissions');

    $setting = (new \App\Models\SettingModel())->get();
    $schoolName = $setting['school_name'] ?? 'Panel Admin';
    $schoolLogo = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;

    // Item menu menyala bila alamat sekarang sama persis, atau (kecuali $it[3] = persis) berada di bawahnya.
    // Dicocokkan sebagai prefiks SEGMEN agar 'admin/jadwal' tidak ikut menyala saat membuka 'admin/jadwal-guru'.
    $itemAktif = static function (array $it) use ($cur): bool {
        $persis = (bool) ($it[3] ?? false);

        return $cur === $it[0] || (! $persis && str_starts_with($cur, $it[0] . '/'));
    };
    // Atribut pencarian menu: item tampil bila teks cari kosong atau cocok dengan labelnya.
    $atrCari = static fn (string $label): string => ' x-show="cari === \'\' || ' . esc(json_encode(mb_strtolower($label)), 'attr') . '.includes(cari.toLowerCase())"';

    // helper render satu link menu
    $renderLink = static function (array $it) use ($itemAktif, $atrCari) {
        [$url, $label, $icon] = $it;
        $active = $itemAktif($it);
        $cls = $active ? 'bg-white/15 text-white' : 'text-brand-100 hover:bg-white/10';
        echo '<a href="' . site_url($url) . '" title="' . esc($label) . '" ' . ($active ? 'aria-current="page" ' : '') . ltrim($atrCari($label)) . ' '
            . ':class="collapsed && !sidebar ? \'lg:justify-center\' : \'\'" '
            . 'class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition ' . $cls . '">'
            . '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icon . '"/></svg>'
            . '<span x-show="!collapsed || sidebar">' . esc($label) . '</span>'
            . '</a>';
    };

    // Menu rancangan (fitur belum jadi; item bertanda $it[4] = true): redup, tak bisa diklik, bertanda "Segera".
    $renderSegera = static function (array $it) use ($atrCari) {
        [, $label, $icon] = $it;
        echo '<div title="' . esc($label) . ' — segera hadir" aria-disabled="true"' . $atrCari($label) . ' '
            . ':class="collapsed && !sidebar ? \'lg:justify-center\' : \'\'" '
            . 'class="flex cursor-not-allowed select-none items-center gap-3 rounded-lg px-3.5 py-2 text-sm font-medium text-brand-300/70">'
            . '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icon . '"/></svg>'
            . '<span class="min-w-0 flex-1 leading-snug" x-show="!collapsed || sidebar">' . esc($label) . '</span>'
            . '<span class="shrink-0 rounded-full bg-gold-400/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-gold-400" x-show="!collapsed || sidebar">Segera</span>'
            . '</div>';
    };
    $renderItem = static fn (array $g, array $it) => ($it[4] ?? false) ? $renderSegera($it) : $renderLink($it);
?>

<!-- Overlay mobile -->
<div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

<!-- ===================== SIDEBAR ===================== -->
<aside class="fixed inset-y-0 left-0 z-40 flex flex-col w-64 bg-brand-800 text-white transform transition-all duration-200 lg:translate-x-0"
       :class="{ 'translate-x-0': sidebar, '-translate-x-full': !sidebar, 'lg:w-20': collapsed, 'lg:w-64': !collapsed }">
    <!-- Brand -->
    <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10 shrink-0"
         :class="collapsed && !sidebar ? 'lg:px-0 lg:justify-center' : ''">
        <?php if ($schoolLogo): ?>
            <img src="<?= esc($schoolLogo) ?>" alt="Logo" class="h-9 w-9 rounded-lg object-contain shrink-0">
        <?php else: ?>
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gold-400 text-brand-900 font-extrabold shrink-0"><?= strtoupper(substr($schoolName, 0, 1)) ?></div>
        <?php endif; ?>
        <div class="leading-tight overflow-hidden" x-show="!collapsed || sidebar">
            <p class="font-bold text-sm truncate" title="<?= esc($schoolName) ?>"><?= esc($schoolName) ?></p>
            <p class="text-[11px] text-brand-200">Sistem Akademik Sekolah</p>
        </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1" x-data="{ cari: '' }" aria-label="Menu utama">
        <!-- Cari menu: ±60 menu, jadi ada kotak pencarian (hanya saat sidebar lebar) -->
        <div class="px-0.5 pb-2" x-show="!collapsed || sidebar">
            <label class="relative block">
                <span class="sr-only">Cari menu</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input type="search" x-model="cari" placeholder="Cari menu…" autocomplete="off"
                       class="w-full rounded-lg border-0 bg-white/10 py-2 pl-9 pr-3 text-sm text-white placeholder-brand-300 transition focus:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/30">
            </label>
        </div>
        <?php foreach ($groups as $g): ?>
            <?php if ($g['title'] === null): ?>
                <?php foreach ($g['items'] as $it) { $renderItem($g, $it); } ?>
            <?php else:
                // Akordeon: grup yang berisi halaman sekarang selalu terbuka; grup lain terlipat dan ingatan buka/tutupnya
                // disimpan per peramban (localStorage). Saat mencari, semua grup yang cocok terbuka.
                $grupAktif = false;
                foreach ($g['items'] as $it) {
                    $grupAktif = $grupAktif || (! ($it[4] ?? false) && $itemAktif($it));
                }
                $labelGrup = array_map(static fn (array $it): string => mb_strtolower($it[1]), $g['items']);
                $kunciGrup = 'sb_g_' . substr(md5((string) $g['title']), 0, 8);
            ?>
                <div x-data="{ open: <?= $grupAktif ? 'true' : 'false' ?>, kunci: '<?= $kunciGrup ?>',
                               init() { if (! <?= $grupAktif ? 'true' : 'false' ?>) { try { this.open = localStorage.getItem(this.kunci) === '1'; } catch (e) {} } },
                               ganti() { this.open = ! this.open; try { localStorage.setItem(this.kunci, this.open ? '1' : '0'); } catch (e) {} } }"
                     x-show="cari === '' || <?= esc(json_encode($labelGrup), 'attr') ?>.some(l => l.includes(cari.toLowerCase()))" class="pt-2">
                    <!-- Judul grup (klik untuk buka/tutup) - hanya saat lebar -->
                    <button type="button" @click="ganti()" x-show="!collapsed || sidebar" :aria-expanded="open || cari !== ''"
                            class="flex w-full items-center justify-between rounded-md px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider transition hover:text-white <?= $grupAktif ? 'text-white' : 'text-brand-300' ?>">
                        <span class="flex items-center gap-1.5"><?= esc($g['title']) ?><?php if ($grupAktif): ?><span class="h-1.5 w-1.5 rounded-full bg-gold-400" aria-hidden="true"></span><?php endif; ?></span>
                        <svg class="h-3.5 w-3.5 transition-transform" :class="(open || cari !== '') ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <!-- Saat menciut: garis pemisah tipis pengganti judul -->
                    <div x-show="collapsed && !sidebar" class="mx-3 my-1 border-t border-white/10"></div>
                    <div class="space-y-1 mt-1" x-show="open || cari !== '' || (collapsed && !sidebar)" x-collapse.duration.200ms>
                        <?php foreach ($g['items'] as $it) { $renderItem($g, $it); } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php $semuaLabel = []; foreach ($groups as $g) { foreach ($g['items'] as $it) { $semuaLabel[] = mb_strtolower($it[1]); } } ?>
        <p x-cloak x-show="cari !== '' && ! <?= esc(json_encode($semuaLabel), 'attr') ?>.some(l => l.includes(cari.toLowerCase()))" class="px-3.5 py-3 text-xs text-brand-300">Menu tidak ditemukan.</p>

        <?php if ($bolehFormPublik): // tautan form kesediaan guru — tak relevan bagi Operator/Hubin ?>
        <div class="pt-2" x-show="cari === ''">
            <a href="<?= site_url('isi') ?>" target="_blank" title="Lihat Form Publik"
               :class="collapsed && !sidebar ? 'lg:justify-center' : ''"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10 transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span x-show="!collapsed || sidebar">Lihat Form Publik</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Footer: tombol keluar (flex, tidak lagi absolute → tak menumpuk) -->
    <div class="p-3 border-t border-white/10 shrink-0">
        <a href="<?= site_url('admin/logout') ?>" title="Keluar"
           :class="collapsed && !sidebar ? 'lg:justify-center' : ''"
           class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-red-200 hover:bg-red-500/20 transition">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span x-show="!collapsed || sidebar">Keluar</span>
        </a>
    </div>
</aside>

<!-- ===================== MAIN ===================== -->
<div class="min-h-screen flex flex-col transition-all duration-200" :class="collapsed ? 'lg:ml-20' : 'lg:ml-64'">
    <!-- Topbar -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20">
        <div class="flex items-center gap-3">
            <!-- Mobile: buka drawer -->
            <button @click="sidebar=true" class="lg:hidden text-slate-500 hover:text-slate-700"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <!-- Desktop: ciutkan/lebarkan sidebar -->
            <button @click="collapsed=!collapsed" class="hidden lg:inline-flex text-slate-500 hover:text-slate-700" :title="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <h1 class="font-bold text-slate-800 text-lg"><?= esc($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block leading-tight">
                <p class="text-sm font-semibold text-slate-700"><?= esc($admin['full_name'] ?? 'Admin') ?></p>
                <p class="text-xs text-slate-400"><span class="font-semibold text-brand-600"><?= esc($labelPeranKini) ?></span> · @<?= esc($admin['username'] ?? '') ?></p>
            </div>
            <a href="<?= site_url('admin/profile') ?>" class="block h-9 w-9 rounded-full overflow-hidden bg-brand-100 ring-2 ring-brand-200">
                <?php if (! empty($admin['photo'])): ?>
                    <img src="<?= base_url('uploads/' . esc($admin['photo'])) ?>" class="h-full w-full object-cover" alt="">
                <?php else: ?>
                    <span class="flex h-full w-full items-center justify-center text-brand-700 font-bold text-sm"><?= strtoupper(substr($admin['full_name'] ?? 'A', 0, 1)) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6">
        <?php if (session('success')): ?>
            <div class="mb-5 rounded-xl bg-green-50 border border-green-200 px-5 py-3.5 text-sm text-green-700 flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?= esc(session('success')) ?>
            </div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-5 py-3.5 text-sm text-red-700"><?= esc(session('error')) ?></div>
        <?php endif; ?>
        <?php if (session('errors')): ?>
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-5 py-3.5 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-0.5"><?php foreach (session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>

    <footer class="px-4 sm:px-6 pb-5 text-center text-xs text-slate-400">
        <p>&copy; <?= date('Y') ?> &middot; Sistem Informasi Akademik Sekolah (BINUS)</p>
        <?= view('partials/kredit', ['kreditKelas' => '']) ?>
    </footer>
</div>

<?php // Skrip halaman dimuat lebih dulu (defer = urut dokumen), lalu util global, terakhir Alpine. ?>
<?= $this->renderSection('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/app.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine-collapse.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine-collapse.min.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine.min.js') ?>"></script>
</body>
</html>
