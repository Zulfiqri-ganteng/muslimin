<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ===================== PUBLIK =====================
$routes->get('/', 'Publik::home');
$routes->get('jadwal-kelas', 'Publik::jadwalKelas');
$routes->get('jadwal-guru', 'Publik::jadwalGuru');
$routes->get('jadwal-kelas/(:num)/pdf', 'Publik::cetakKelas/$1');
$routes->get('jadwal-guru/(:num)/pdf', 'Publik::cetakGuru/$1');
$routes->get('absensi', 'Publik::absensi');

// ===== Berbagi dokumen (TANPA login, dijaga token) =====
// Rute yang lebih spesifik didahulukan agar tidak tertelan pola (:segment).
$routes->get('d/(:segment)/berkas/(:num)', 'Berbagi::berkas/$1/$2');
$routes->get('d/(:segment)/unduh/(:num)', 'Berbagi::unduh/$1/$2');
$routes->get('d/(:segment)/berkas', 'Berbagi::berkas/$1');
$routes->get('d/(:segment)/unduh', 'Berbagi::unduh/$1');
$routes->post('d/(:segment)/buka', 'Berbagi::buka/$1');
$routes->get('d/(:segment)', 'Berbagi::lihat/$1');

// Daftar dokumen publik (digerbangi settings.dokumen_publik)
$routes->get('dokumen-publik', 'Berbagi::publik');
$routes->get('dokumen-publik/(:num)/berkas', 'Berbagi::berkasPublik/$1');
$routes->get('dokumen-publik/(:num)/unduh', 'Berbagi::unduhPublik/$1');

// Form kesediaan guru (sekunder)
$routes->get('isi', 'Form::index');
$routes->post('kirim', 'Form::submit');
$routes->get('terima-kasih', 'Form::success');
$routes->get('tutup', 'Form::closed');

// Revisi (tautan token dari admin — form ter-isi data lama)
$routes->get('revisi/(:segment)', 'Form::edit/$1');
$routes->post('revisi/(:segment)', 'Form::updateSubmission/$1');

// ===== Isian biodata siswa (TANPA login) =====
// Pintu utama: subdomain (config Biodata::$host) — beranda subdomain langsung
// form. Rute hostname MENIMPA rute '/' di atas hanya untuk host tersebut.
// Pintu cadangan: /biodata di domain utama.
$routes->get('/', 'Biodata::index', ['hostname' => config('Biodata')->host]);
$routes->get('biodata', 'Biodata::index');
$routes->get('biodata/siswa', 'Biodata::siswa');
$routes->post('biodata/buka', 'Biodata::buka');
$routes->post('biodata/kirim', 'Biodata::kirim');
$routes->get('biodata/selesai', 'Biodata::selesai');

// ===== Pengajuan PKL / Prakerin siswa (TANPA login) — docs/DESAIN-PKL.md =====
// Pola sama dengan biodata: pintu utama subdomain (config Pkl::$host), pintu
// cadangan /pkl di domain utama. Alamat staf ada di admin/pkl (grup admin).
$routes->get('/', 'Pkl::index', ['hostname' => config('Pkl')->host]);
$routes->get('pkl', 'Pkl::index');
$routes->get('pkl/siswa', 'Pkl::siswa');
$routes->get('pkl/perusahaan', 'Pkl::perusahaan');
$routes->post('pkl/buka', 'Pkl::buka');
$routes->post('pkl/kirim', 'Pkl::kirim');
$routes->get('pkl/selesai', 'Pkl::selesai');

// ===================== ADMIN =====================
$routes->group('admin', static function ($routes) {
    // Autentikasi (tanpa filter)
    $routes->get('login', 'Admin\Auth::login');
    $routes->post('login', 'Admin\Auth::attemptLogin');
    $routes->get('logout', 'Admin\Auth::logout');

    // Area terproteksi
    $routes->group('', ['filter' => 'auth'], static function ($routes) {
        $routes->get('/', 'Admin\Dashboard::index');
        $routes->get('dashboard', 'Admin\Dashboard::index');

        // Submissions
        $routes->get('submissions', 'Admin\Submissions::index');
        $routes->get('submissions/view/(:num)', 'Admin\Submissions::view/$1');
        $routes->post('submissions/status/(:num)', 'Admin\Submissions::updateStatus/$1');
        $routes->get('submissions/delete/(:num)', 'Admin\Submissions::delete/$1');

        // ===== Isian Biodata Siswa (kotak masuk form publik) =====
        // Semua aksi pengubah data memakai POST. Rute statis sebelum (:num).
        $routes->get('biodata', 'Admin\Biodata::index');
        $routes->get('biodata/laporan', 'Admin\Biodata::laporan');
        $routes->post('biodata/pengaturan', 'Admin\Biodata::pengaturan');
        $routes->post('biodata/setujui-massal', 'Admin\Biodata::setujuiMassal');
        $routes->post('biodata/kembalikan-massal', 'Admin\Biodata::kembalikanMassal');
        $routes->get('biodata/(:num)', 'Admin\Biodata::detail/$1');
        $routes->post('biodata/(:num)/setujui', 'Admin\Biodata::setujui/$1');
        $routes->post('biodata/(:num)/kembalikan', 'Admin\Biodata::kembalikan/$1');
        $routes->post('biodata/(:num)/hapus', 'Admin\Biodata::hapus/$1');

        // ===== PKL / Prakerin (staf) — docs/DESAIN-PKL.md =====
        // Aksi pengubah data = POST + CSRF (halaman berformulir ikut difilter csrf agar cookie
        // token terkirim). Rute statis SEBELUM pola (:num). Awalan admin/pkl/pengaturan dan
        // admin/pkl/hapus DITOLAK untuk Hubin oleh Config\Peran ('kecuali').
        $routes->get('pkl', 'Admin\Pkl::index');
        $routes->get('pkl/daftar', static fn () => redirect()->to(site_url('admin/pkl/daftar/menunggu')));
        $routes->get('pkl/daftar/(:segment)', 'Admin\Pkl::daftar/$1', ['filter' => 'csrf']);
        $routes->get('pkl/siswa', 'Admin\Pkl::siswa');
        $routes->get('pkl/siswa/excel', 'Admin\PklBerkas::siswaExcel');
        $routes->get('pkl/siswa-kelas', 'Admin\Pkl::siswaKelas');
        $routes->get('pkl/baru', 'Admin\Pkl::baru', ['filter' => 'csrf']);
        $routes->post('pkl/baru', 'Admin\Pkl::simpanBaru', ['filter' => 'csrf']);
        $routes->get('pkl/pengaturan', 'Admin\Pkl::pengaturan', ['filter' => 'csrf']);
        $routes->post('pkl/pengaturan', 'Admin\Pkl::simpanPengaturan', ['filter' => 'csrf']);
        $routes->post('pkl/hapus/(:num)', 'Admin\Pkl::hapus/$1', ['filter' => 'csrf']);
        // Tahap 4: surat Word, template, impor (lihat Admin\PklBerkas)
        $routes->post('pkl/acc-massal', 'Admin\Pkl::accMassal', ['filter' => 'csrf']);
        $routes->post('pkl/surat-massal', 'Admin\PklBerkas::suratMassal', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/surat', 'Admin\PklBerkas::surat/$1', ['filter' => 'csrf']);
        $routes->get('pkl/pengaturan/template', 'Admin\PklBerkas::template');
        $routes->post('pkl/pengaturan/template', 'Admin\PklBerkas::unggahTemplate', ['filter' => 'csrf']);
        $routes->post('pkl/pengaturan/template/hapus', 'Admin\PklBerkas::hapusTemplate', ['filter' => 'csrf']);
        // Tanda tangan Waka Hubin: Hubin & Admin saja (Operator ditolak oleh Config\Peran 'kecuali').
        $routes->get('pkl/ttd', 'Admin\PklBerkas::ttd', ['filter' => 'csrf']);
        $routes->get('pkl/ttd/gambar', 'Admin\PklBerkas::ttdGambar');
        $routes->post('pkl/ttd', 'Admin\PklBerkas::unggahTtd', ['filter' => 'csrf']);
        $routes->post('pkl/ttd/hapus', 'Admin\PklBerkas::hapusTtd', ['filter' => 'csrf']);
        $routes->get('pkl/impor', 'Admin\PklBerkas::impor', ['filter' => 'csrf']);
        $routes->get('pkl/impor/contoh', 'Admin\PklBerkas::imporContoh');
        $routes->post('pkl/impor/pratinjau', 'Admin\PklBerkas::imporPratinjau', ['filter' => 'csrf']);
        $routes->post('pkl/impor/simpan', 'Admin\PklBerkas::imporSimpan', ['filter' => 'csrf']);
        $routes->get('pkl/(:num)', 'Admin\Pkl::detail/$1', ['filter' => 'csrf']);
        $routes->get('pkl/(:num)/ubah', 'Admin\Pkl::ubah/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/ubah', 'Admin\Pkl::simpanUbah/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/acc', 'Admin\Pkl::acc/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/kembalikan', 'Admin\Pkl::kembalikan/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/tolak', 'Admin\Pkl::tolak/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/batal-acc', 'Admin\Pkl::batalAcc/$1', ['filter' => 'csrf']);
        // Hak yang diatur Admin (Libraries\PklHak): biaya saat unduh surat + kabar WhatsApp + koreksi ('surat'), Laporan
        // Pembayaran ('laporan'), nominal biaya ('pengaturan'). Halaman Hak Akses KHUSUS ADMIN. Dijaga HakAkses::boleh
        // (lapis kedua PKL) dan diperiksa lagi di tiap aksi controller.
        $routes->get('pkl/surat/siap', 'Admin\PklBiaya::siap');
        $routes->get('pkl/surat/hasil/(:segment)', 'Admin\PklBiaya::hasil/$1');
        $routes->get('pkl/wa', 'Admin\PklBiaya::wa');
        $routes->post('pkl/(:num)/wa/(:num)/tandai', 'Admin\PklBiaya::tandaiWa/$1/$2', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/pembayaran/hapus', 'Admin\PklBiaya::hapusPembayaran/$1', ['filter' => 'csrf']);
        $routes->post('pkl/(:num)/pembayaran/beasiswa-cabut', 'Admin\PklBiaya::cabutBeasiswa/$1', ['filter' => 'csrf']);
        $routes->post('pkl/pengaturan/biaya', 'Admin\PklBiaya::simpanBiaya', ['filter' => 'csrf']);
        $routes->get('pkl/laporan', 'Admin\PklBiaya::laporan');
        $routes->get('pkl/laporan/excel', 'Admin\PklBiaya::laporanExcel');
        $routes->get('pkl/hak-akses', 'Admin\PklHakAkses::index', ['filter' => 'csrf']);
        $routes->post('pkl/hak-akses', 'Admin\PklHakAkses::simpan', ['filter' => 'csrf']);
        $routes->post('pkl/hak-akses/bawaan', 'Admin\PklHakAkses::bawaan', ['filter' => 'csrf']);
        $routes->post('pkl/hak-akses/surat-acc', 'Admin\PklHakAkses::simpanAccSurat', ['filter' => 'csrf']);

        // ===== Surat Sekolah (staf) — docs/DESAIN-SURAT-SEKOLAH.md =====
        // Daftar Surat (buku agenda surat keluar), detail, keputusan (ACC / kembalikan / batalkan), unduhan Word.
        // Halaman membuat tiap jenis surat ditambahkan per langkah. Hak per alamat: Libraries\PklHak::hakUntukAlamat
        // (admin/surat/… → null | 'acc' | 'surat_sekolah') dan diperiksa lagi di Libraries\SuratKeputusan / controller.
        // Aksi pengubah data = POST + CSRF; rute statis SEBELUM pola (:num).
        $routes->get('surat', 'Admin\Surat::index', ['filter' => 'csrf']);
        // Formulir membuat / mengubah Surat Izin ASTS dan TKA (Admin\SuratIzin)
        $routes->get('surat/asts', 'Admin\SuratIzin::asts', ['filter' => 'csrf']);
        $routes->post('surat/asts', 'Admin\SuratIzin::simpanAsts', ['filter' => 'csrf']);
        $routes->get('surat/asts/(:num)/ubah', 'Admin\SuratIzin::ubahAsts/$1', ['filter' => 'csrf']);
        $routes->post('surat/asts/(:num)/ubah', 'Admin\SuratIzin::simpanUbahAsts/$1', ['filter' => 'csrf']);
        $routes->get('surat/tka', 'Admin\SuratIzin::tka', ['filter' => 'csrf']);
        $routes->post('surat/tka', 'Admin\SuratIzin::simpanTka', ['filter' => 'csrf']);
        $routes->get('surat/tka/(:num)/ubah', 'Admin\SuratIzin::ubahTka/$1', ['filter' => 'csrf']);
        $routes->post('surat/tka/(:num)/ubah', 'Admin\SuratIzin::simpanUbahTka/$1', ['filter' => 'csrf']);
        $routes->post('surat/acc-massal', 'Admin\Surat::accMassal', ['filter' => 'csrf']);
        $routes->post('surat/unduh-massal', 'Admin\Surat::unduhMassal', ['filter' => 'csrf']);
        $routes->get('surat/(:num)', 'Admin\Surat::detail/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/acc', 'Admin\Surat::acc/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/kembalikan', 'Admin\Surat::kembalikan/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/batal-acc', 'Admin\Surat::batalAcc/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/ajukan-ulang', 'Admin\Surat::ajukanUlang/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/batal', 'Admin\Surat::batal/$1', ['filter' => 'csrf']);
        $routes->post('surat/(:num)/unduh', 'Admin\Surat::unduh/$1', ['filter' => 'csrf']);

        // ===== Kelola Akun Staf (khusus peran admin — dijaga Config\Peran) =====
        // Semua aksi pengubah data memakai POST. Rute ini bisa menaikkan peran
        // seseorang, jadi DILINDUNGI CSRF walau filter csrf belum aktif global
        // (utang lama SEC1): formulirnya memuat csrf_field(), dan GET ikut difilter
        // agar cookie token terkirim saat halaman dibuka.
        $routes->get('akun', 'Admin\Akun::index', ['filter' => 'csrf']);
        $routes->post('akun', 'Admin\Akun::store', ['filter' => 'csrf']);
        $routes->post('akun/(:num)', 'Admin\Akun::update/$1', ['filter' => 'csrf']);
        $routes->post('akun/(:num)/reset-sandi', 'Admin\Akun::resetSandi/$1', ['filter' => 'csrf']);
        $routes->post('akun/(:num)/status', 'Admin\Akun::status/$1', ['filter' => 'csrf']);

        // ===== Manajemen Dokumen =====
        // Semua aksi yang mengubah data memakai POST (modul baru sengaja
        // tidak ikut pola hapus-lewat-GET yang jadi utang CSRF di modul lama).
        $routes->get('dokumen', 'Admin\Dokumen::index');
        $routes->get('dokumen/sampah', 'Admin\Dokumen::sampah');
        $routes->get('dokumen/pratinjau/(:num)', 'Admin\Dokumen::pratinjau/$1');
        $routes->get('dokumen/penyimpanan', 'Admin\Dokumen::penyimpanan');
        $routes->post('dokumen/penyimpanan/bersihkan', 'Admin\Dokumen::bersihkanYatim');
        $routes->post('dokumen/sampah/kosongkan', 'Admin\Dokumen::kosongkanSampah');
        $routes->post('dokumen/unggah', 'Admin\Dokumen::unggah');
        $routes->post('dokumen/tautan', 'Admin\Dokumen::tautanStore');
        $routes->post('dokumen/pindah', 'Admin\Dokumen::pindah');
        $routes->post('dokumen/hapus-massal', 'Admin\Dokumen::hapusMassal');
        // Rute folder diletakkan SEBELUM pola (:num) generik agar tidak tertelan.
        $routes->post('dokumen/folder', 'Admin\Dokumen::folderStore');
        $routes->post('dokumen/folder/(:num)', 'Admin\Dokumen::folderUpdate/$1');
        $routes->post('dokumen/folder/(:num)/hapus', 'Admin\Dokumen::folderHapus/$1');
        $routes->post('dokumen/folder/(:num)/pulihkan', 'Admin\Dokumen::pulihkanFolder/$1');
        $routes->post('dokumen/(:num)', 'Admin\Dokumen::update/$1');
        $routes->post('dokumen/(:num)/hapus', 'Admin\Dokumen::hapus/$1');
        $routes->post('dokumen/(:num)/pulihkan', 'Admin\Dokumen::pulihkan/$1');
        $routes->post('dokumen/(:num)/hapus-permanen', 'Admin\Dokumen::hapusPermanen/$1');
        $routes->post('dokumen/(:num)/bagikan', 'Admin\Dokumen::bagikan/$1');
        $routes->post('dokumen/folder/(:num)/bagikan', 'Admin\Dokumen::bagikanFolder/$1');
        $routes->post('dokumen/share/(:num)/cabut', 'Admin\Dokumen::cabutShare/$1');

        // ===== Manajemen Dokumen: penyaji berkas =====
        // Berkas dokumen ada di luar webroot, jadi HANYA bisa keluar lewat
        // rute ini — yang sudah dijaga filter 'auth' grup ini.
        // HEAD ikut didaftarkan: pengelola unduhan & pemutar video kerap
        // menanyakan ukuran berkas lebih dulu sebelum menarik isinya.
        $routes->match(['GET', 'HEAD'], 'dokumen/berkas/(:num)', 'Admin\DokumenFile::lihat/$1');
        $routes->match(['GET', 'HEAD'], 'dokumen/unduh/(:num)', 'Admin\DokumenFile::unduh/$1');
        $routes->match(['GET', 'HEAD'], 'dokumen/thumb/(:num)', 'Admin\DokumenFile::thumb/$1');

        // ===== Laboratorium: Peminjaman & Pengembalian =====
        $routes->get('peminjaman', 'Admin\Peminjaman::index');
        $routes->post('peminjaman', 'Admin\Peminjaman::store');
        $routes->post('peminjaman/kembalikan/(:num)', 'Admin\Peminjaman::kembalikan/$1');
        $routes->get('peminjaman/delete/(:num)', 'Admin\Peminjaman::delete/$1');

        // ===== Laboratorium: Kerusakan & Perbaikan =====
        $routes->get('kerusakan', 'Admin\Kerusakan::index');
        $routes->post('kerusakan', 'Admin\Kerusakan::store');
        $routes->post('kerusakan/status/(:num)', 'Admin\Kerusakan::status/$1');
        $routes->get('kerusakan/delete/(:num)', 'Admin\Kerusakan::delete/$1');

        $routes->get('perbaikan', 'Admin\Perbaikan::index');
        $routes->post('perbaikan', 'Admin\Perbaikan::store');
        $routes->get('perbaikan/delete/(:num)', 'Admin\Perbaikan::delete/$1');

        // ===== Laboratorium: Jadwal & Jurnal Lab =====
        $routes->get('jadwal-lab', 'Admin\JadwalLab::index');
        $routes->post('jadwal-lab', 'Admin\JadwalLab::store');
        $routes->get('jadwal-lab/delete/(:num)', 'Admin\JadwalLab::delete/$1');

        $routes->get('jurnal-lab', 'Admin\JurnalLab::index');
        $routes->post('jurnal-lab', 'Admin\JurnalLab::store');
        $routes->get('jurnal-lab/delete/(:num)', 'Admin\JurnalLab::delete/$1');

        // ===== Laboratorium: Laporan =====
        $routes->get('laporan-lab', 'Admin\LaporanLab::index');
        $routes->get('laporan-lab/pdf', 'Admin\LaporanLab::pdf');
        $routes->get('laporan-lab/excel', 'Admin\LaporanLab::excel');

        // ===== Laboratorium: Galeri Foto (semua entitas) =====
        $routes->get('lab-gambar/hapus/(:num)', 'Admin\LabGambar::delete/$1');
        $routes->get('lab-gambar/(:segment)/(:num)', 'Admin\LabGambar::index/$1/$2');
        $routes->post('lab-gambar/(:segment)/(:num)', 'Admin\LabGambar::upload/$1/$2');

        // ===== MASTER DATA (Penjadwalan KBM) =====
        $routes->group('master', static function ($routes) {
            // Guru
            $routes->get('guru', 'Admin\Master\Guru::index');
            $routes->post('guru', 'Admin\Master\Guru::store');
            $routes->post('guru/(:num)', 'Admin\Master\Guru::update/$1');
            $routes->get('guru/delete/(:num)', 'Admin\Master\Guru::delete/$1');
            $routes->get('guru/export', 'Admin\Master\Guru::export');
            $routes->get('guru/template', 'Admin\Master\Guru::template');
            $routes->post('guru/import-preview', 'Admin\Master\Guru::importPreview');
            $routes->post('guru/import-commit', 'Admin\Master\Guru::importCommit');
            $routes->post('guru/bulk-delete', 'Admin\Master\Guru::bulkDelete');
            $routes->get('guru/import-kesediaan', 'Admin\Master\Guru::importFromSubmissions');
            $routes->post('guru/jabatan/(:num)', 'Admin\Master\Guru::jabatan/$1');

            // Siswa
            $routes->get('siswa', 'Admin\Master\Siswa::index');
            $routes->post('siswa', 'Admin\Master\Siswa::store');
            $routes->post('siswa/(:num)', 'Admin\Master\Siswa::update/$1');
            $routes->get('siswa/delete/(:num)', 'Admin\Master\Siswa::delete/$1');
            $routes->get('siswa/(:num)', 'Admin\Master\Siswa::detail/$1');
            $routes->get('siswa/export', 'Admin\Master\Siswa::export');
            $routes->get('siswa/export-resmi', 'Admin\Master\Siswa::exportResmi');
            $routes->get('siswa/template', 'Admin\Master\Siswa::template');
            $routes->post('siswa/import-preview', 'Admin\Master\Siswa::importPreview');
            $routes->post('siswa/import-commit', 'Admin\Master\Siswa::importCommit');
            $routes->post('siswa/bulk-delete', 'Admin\Master\Siswa::bulkDelete');

            // Jabatan
            $routes->get('jabatan', 'Admin\Master\Jabatan::index');
            $routes->post('jabatan', 'Admin\Master\Jabatan::store');
            $routes->post('jabatan/(:num)', 'Admin\Master\Jabatan::update/$1');
            $routes->get('jabatan/delete/(:num)', 'Admin\Master\Jabatan::delete/$1');
            $routes->get('jabatan/export', 'Admin\Master\Jabatan::export');
            $routes->get('jabatan/template', 'Admin\Master\Jabatan::template');
            $routes->post('jabatan/import-preview', 'Admin\Master\Jabatan::importPreview');
            $routes->post('jabatan/import-commit', 'Admin\Master\Jabatan::importCommit');
            $routes->post('jabatan/bulk-delete', 'Admin\Master\Jabatan::bulkDelete');

            // ===== Laboratorium & Inventaris (SIMLAB) =====
            // Teknisi / Penanggung Jawab
            $routes->get('teknisi', 'Admin\Master\Teknisi::index');
            $routes->post('teknisi', 'Admin\Master\Teknisi::store');
            $routes->post('teknisi/(:num)', 'Admin\Master\Teknisi::update/$1');
            $routes->get('teknisi/delete/(:num)', 'Admin\Master\Teknisi::delete/$1');
            $routes->get('teknisi/export', 'Admin\Master\Teknisi::export');
            $routes->get('teknisi/template', 'Admin\Master\Teknisi::template');
            $routes->post('teknisi/import-preview', 'Admin\Master\Teknisi::importPreview');
            $routes->post('teknisi/import-commit', 'Admin\Master\Teknisi::importCommit');
            $routes->post('teknisi/bulk-delete', 'Admin\Master\Teknisi::bulkDelete');

            // Laboratorium
            $routes->get('lab', 'Admin\Master\Lab::index');
            $routes->post('lab', 'Admin\Master\Lab::store');
            $routes->post('lab/(:num)', 'Admin\Master\Lab::update/$1');
            $routes->get('lab/delete/(:num)', 'Admin\Master\Lab::delete/$1');
            $routes->get('lab/export', 'Admin\Master\Lab::export');
            $routes->get('lab/template', 'Admin\Master\Lab::template');
            $routes->post('lab/import-preview', 'Admin\Master\Lab::importPreview');
            $routes->post('lab/import-commit', 'Admin\Master\Lab::importCommit');
            $routes->post('lab/bulk-delete', 'Admin\Master\Lab::bulkDelete');

            // Sparepart
            $routes->get('sparepart', 'Admin\Master\Sparepart::index');
            $routes->post('sparepart', 'Admin\Master\Sparepart::store');
            $routes->post('sparepart/(:num)', 'Admin\Master\Sparepart::update/$1');
            $routes->get('sparepart/delete/(:num)', 'Admin\Master\Sparepart::delete/$1');
            $routes->get('sparepart/export', 'Admin\Master\Sparepart::export');
            $routes->get('sparepart/template', 'Admin\Master\Sparepart::template');
            $routes->post('sparepart/import-preview', 'Admin\Master\Sparepart::importPreview');
            $routes->post('sparepart/import-commit', 'Admin\Master\Sparepart::importCommit');
            $routes->post('sparepart/bulk-delete', 'Admin\Master\Sparepart::bulkDelete');

            // Aset / Inventaris (+ detail komputer 1:1)
            $routes->get('aset', 'Admin\Master\Aset::index');
            $routes->post('aset', 'Admin\Master\Aset::store');
            $routes->get('aset/komputer/(:num)', 'Admin\Master\Aset::komputer/$1');
            $routes->post('aset/komputer/(:num)', 'Admin\Master\Aset::komputerSave/$1');
            $routes->post('aset/(:num)', 'Admin\Master\Aset::update/$1');
            $routes->get('aset/delete/(:num)', 'Admin\Master\Aset::delete/$1');
            $routes->get('aset/export', 'Admin\Master\Aset::export');
            $routes->get('aset/template', 'Admin\Master\Aset::template');
            $routes->post('aset/import-preview', 'Admin\Master\Aset::importPreview');
            $routes->post('aset/import-commit', 'Admin\Master\Aset::importCommit');
            $routes->post('aset/bulk-delete', 'Admin\Master\Aset::bulkDelete');

            // Mata Pelajaran
            $routes->get('mapel', 'Admin\Master\MataPelajaran::index');
            $routes->post('mapel', 'Admin\Master\MataPelajaran::store');
            $routes->post('mapel/(:num)', 'Admin\Master\MataPelajaran::update/$1');
            $routes->get('mapel/delete/(:num)', 'Admin\Master\MataPelajaran::delete/$1');
            $routes->post('mapel/kompetensi/(:num)', 'Admin\Master\MataPelajaran::kompetensi/$1');
            $routes->get('mapel/export', 'Admin\Master\MataPelajaran::export');
            $routes->get('mapel/template', 'Admin\Master\MataPelajaran::template');
            $routes->post('mapel/import-preview', 'Admin\Master\MataPelajaran::importPreview');
            $routes->post('mapel/import-commit', 'Admin\Master\MataPelajaran::importCommit');
            $routes->post('mapel/bulk-delete', 'Admin\Master\MataPelajaran::bulkDelete');

            // Kelas
            $routes->get('kelas', 'Admin\Master\Kelas::index');
            $routes->post('kelas', 'Admin\Master\Kelas::store');
            $routes->post('kelas/(:num)', 'Admin\Master\Kelas::update/$1');
            $routes->get('kelas/delete/(:num)', 'Admin\Master\Kelas::delete/$1');
            $routes->get('kelas/export', 'Admin\Master\Kelas::export');
            $routes->get('kelas/template', 'Admin\Master\Kelas::template');
            $routes->post('kelas/import-preview', 'Admin\Master\Kelas::importPreview');
            $routes->post('kelas/import-commit', 'Admin\Master\Kelas::importCommit');
            $routes->post('kelas/bulk-delete', 'Admin\Master\Kelas::bulkDelete');

            // Pengampu (penugasan)
            $routes->get('pengampu', 'Admin\Master\Pengampu::index');
            $routes->post('pengampu', 'Admin\Master\Pengampu::store');
            $routes->post('pengampu/(:num)', 'Admin\Master\Pengampu::update/$1');
            $routes->get('pengampu/delete/(:num)', 'Admin\Master\Pengampu::delete/$1');
            $routes->get('pengampu/export', 'Admin\Master\Pengampu::export');
            $routes->post('pengampu/bulk-delete', 'Admin\Master\Pengampu::bulkDelete');

            // Ketersediaan Guru
            $routes->get('ketersediaan', 'Admin\Master\Ketersediaan::index');
            $routes->post('ketersediaan', 'Admin\Master\Ketersediaan::save');

            // Jurusan
            $routes->get('jurusan', 'Admin\Master\Jurusan::index');
            $routes->post('jurusan', 'Admin\Master\Jurusan::store');
            $routes->post('jurusan/(:num)', 'Admin\Master\Jurusan::update/$1');
            $routes->get('jurusan/delete/(:num)', 'Admin\Master\Jurusan::delete/$1');
            $routes->get('jurusan/export', 'Admin\Master\Jurusan::export');
            $routes->get('jurusan/template', 'Admin\Master\Jurusan::template');
            $routes->post('jurusan/import-preview', 'Admin\Master\Jurusan::importPreview');
            $routes->post('jurusan/import-commit', 'Admin\Master\Jurusan::importCommit');
            $routes->post('jurusan/bulk-delete', 'Admin\Master\Jurusan::bulkDelete');

            // Hari
            $routes->get('hari', 'Admin\Master\Hari::index');
            $routes->post('hari', 'Admin\Master\Hari::store');
            $routes->post('hari/(:num)', 'Admin\Master\Hari::update/$1');
            $routes->get('hari/delete/(:num)', 'Admin\Master\Hari::delete/$1');
            $routes->get('hari/export', 'Admin\Master\Hari::export');
            $routes->get('hari/template', 'Admin\Master\Hari::template');
            $routes->post('hari/import-preview', 'Admin\Master\Hari::importPreview');
            $routes->post('hari/import-commit', 'Admin\Master\Hari::importCommit');
            $routes->post('hari/bulk-delete', 'Admin\Master\Hari::bulkDelete');

            // Fase (Kurikulum Merdeka)
            $routes->get('fase', 'Admin\Master\Fase::index');
            $routes->post('fase', 'Admin\Master\Fase::store');
            $routes->post('fase/(:num)', 'Admin\Master\Fase::update/$1');
            $routes->get('fase/delete/(:num)', 'Admin\Master\Fase::delete/$1');
            $routes->get('fase/export', 'Admin\Master\Fase::export');
            $routes->get('fase/template', 'Admin\Master\Fase::template');
            $routes->post('fase/import-preview', 'Admin\Master\Fase::importPreview');
            $routes->post('fase/import-commit', 'Admin\Master\Fase::importCommit');
            $routes->post('fase/bulk-delete', 'Admin\Master\Fase::bulkDelete');

            // ===== Sistem UKK: Tempat Uji & Penguji Eksternal =====
            $routes->get('tempat-uji', 'Admin\Master\TempatUji::index');
            $routes->post('tempat-uji', 'Admin\Master\TempatUji::store');
            $routes->post('tempat-uji/(:num)', 'Admin\Master\TempatUji::update/$1');
            $routes->get('tempat-uji/delete/(:num)', 'Admin\Master\TempatUji::delete/$1');
            $routes->get('tempat-uji/export', 'Admin\Master\TempatUji::export');
            $routes->get('tempat-uji/template', 'Admin\Master\TempatUji::template');
            $routes->post('tempat-uji/import-preview', 'Admin\Master\TempatUji::importPreview');
            $routes->post('tempat-uji/import-commit', 'Admin\Master\TempatUji::importCommit');
            $routes->post('tempat-uji/bulk-delete', 'Admin\Master\TempatUji::bulkDelete');

            $routes->get('penguji-eksternal', 'Admin\Master\PengujiEksternal::index');
            $routes->post('penguji-eksternal', 'Admin\Master\PengujiEksternal::store');
            $routes->post('penguji-eksternal/(:num)', 'Admin\Master\PengujiEksternal::update/$1');
            $routes->get('penguji-eksternal/delete/(:num)', 'Admin\Master\PengujiEksternal::delete/$1');
            $routes->get('penguji-eksternal/export', 'Admin\Master\PengujiEksternal::export');
            $routes->get('penguji-eksternal/template', 'Admin\Master\PengujiEksternal::template');
            $routes->post('penguji-eksternal/import-preview', 'Admin\Master\PengujiEksternal::importPreview');
            $routes->post('penguji-eksternal/import-commit', 'Admin\Master\PengujiEksternal::importCommit');
            $routes->post('penguji-eksternal/bulk-delete', 'Admin\Master\PengujiEksternal::bulkDelete');

            $routes->get('paket-soal-ukk', 'Admin\Master\PaketSoalUkk::index');
            $routes->post('paket-soal-ukk', 'Admin\Master\PaketSoalUkk::store');
            $routes->get('paket-soal-ukk/hapus-berkas/(:num)/(:segment)', 'Admin\Master\PaketSoalUkk::hapusBerkas/$1/$2');
            $routes->post('paket-soal-ukk/(:num)', 'Admin\Master\PaketSoalUkk::update/$1');
            $routes->get('paket-soal-ukk/delete/(:num)', 'Admin\Master\PaketSoalUkk::delete/$1');
            $routes->get('paket-soal-ukk/export', 'Admin\Master\PaketSoalUkk::export');
            $routes->get('paket-soal-ukk/template', 'Admin\Master\PaketSoalUkk::template');
            $routes->post('paket-soal-ukk/import-preview', 'Admin\Master\PaketSoalUkk::importPreview');
            $routes->post('paket-soal-ukk/import-commit', 'Admin\Master\PaketSoalUkk::importCommit');
            $routes->post('paket-soal-ukk/bulk-delete', 'Admin\Master\PaketSoalUkk::bulkDelete');

            // Tahun Ajaran
            $routes->get('tahun-ajaran', 'Admin\Master\TahunAjaran::index');
            $routes->post('tahun-ajaran', 'Admin\Master\TahunAjaran::store');
            $routes->post('tahun-ajaran/(:num)', 'Admin\Master\TahunAjaran::update/$1');
            $routes->get('tahun-ajaran/delete/(:num)', 'Admin\Master\TahunAjaran::delete/$1');
            $routes->get('tahun-ajaran/export', 'Admin\Master\TahunAjaran::export');
            $routes->get('tahun-ajaran/template', 'Admin\Master\TahunAjaran::template');
            $routes->post('tahun-ajaran/import-preview', 'Admin\Master\TahunAjaran::importPreview');
            $routes->post('tahun-ajaran/import-commit', 'Admin\Master\TahunAjaran::importCommit');
            $routes->post('tahun-ajaran/bulk-delete', 'Admin\Master\TahunAjaran::bulkDelete');
            $routes->post('tahun-ajaran/(:num)/aktifkan', 'Admin\Master\TahunAjaran::aktifkan/$1');

            // Jam Pelajaran
            $routes->get('jam', 'Admin\Master\JamPelajaran::index');
            $routes->post('jam', 'Admin\Master\JamPelajaran::store');
            $routes->post('jam/(:num)', 'Admin\Master\JamPelajaran::update/$1');
            $routes->get('jam/delete/(:num)', 'Admin\Master\JamPelajaran::delete/$1');
            $routes->get('jam/export', 'Admin\Master\JamPelajaran::export');
            $routes->get('jam/template', 'Admin\Master\JamPelajaran::template');
            $routes->post('jam/import-preview', 'Admin\Master\JamPelajaran::importPreview');
            $routes->post('jam/import-commit', 'Admin\Master\JamPelajaran::importCommit');
            $routes->post('jam/bulk-delete', 'Admin\Master\JamPelajaran::bulkDelete');
        });

        // ===== UKK: Pendaftaran Peserta =====
        $routes->get('peserta-ukk', 'Admin\PesertaUkk::index');
        $routes->get('peserta-ukk/daftarkan', 'Admin\PesertaUkk::daftarkanForm');
        $routes->post('peserta-ukk/daftarkan', 'Admin\PesertaUkk::daftarkanStore');
        $routes->post('peserta-ukk/status/(:num)', 'Admin\PesertaUkk::status/$1');
        $routes->get('peserta-ukk/delete/(:num)', 'Admin\PesertaUkk::delete/$1');

        // ===== UKK: Jadwal + Penugasan Penguji =====
        $routes->get('jadwal-ukk', 'Admin\JadwalUkk::index');
        $routes->post('jadwal-ukk', 'Admin\JadwalUkk::store');
        $routes->get('jadwal-ukk/penguji/(:num)', 'Admin\JadwalUkk::penguji/$1');
        $routes->post('jadwal-ukk/penguji/(:num)', 'Admin\JadwalUkk::pengujiStore/$1');
        $routes->get('jadwal-ukk/penguji/(:num)/hapus/(:num)', 'Admin\JadwalUkk::pengujiHapus/$1/$2');
        $routes->post('jadwal-ukk/(:num)', 'Admin\JadwalUkk::update/$1');
        $routes->get('jadwal-ukk/delete/(:num)', 'Admin\JadwalUkk::delete/$1');

        // ===== UKK: Penilaian =====
        $routes->get('penilaian-ukk', 'Admin\PenilaianUkk::index');
        $routes->get('penilaian-ukk/jadwal/(:num)', 'Admin\PenilaianUkk::jadwal/$1');
        $routes->post('penilaian-ukk/jadwal/(:num)/simpan', 'Admin\PenilaianUkk::simpan/$1');

        // ===== UKK: Berita Acara =====
        $routes->get('berita-acara-ukk', 'Admin\BeritaAcaraUkk::index');
        $routes->post('berita-acara-ukk', 'Admin\BeritaAcaraUkk::store');
        $routes->get('berita-acara-ukk/pdf/(:num)', 'Admin\BeritaAcaraUkk::pdf/$1');
        $routes->post('berita-acara-ukk/(:num)', 'Admin\BeritaAcaraUkk::update/$1');
        $routes->get('berita-acara-ukk/delete/(:num)', 'Admin\BeritaAcaraUkk::delete/$1');

        // ===== UKK: Sertifikat =====
        $routes->get('sertifikat-ukk', 'Admin\SertifikatUkk::index');
        $routes->post('sertifikat-ukk', 'Admin\SertifikatUkk::store');
        $routes->get('sertifikat-ukk/pdf/(:num)', 'Admin\SertifikatUkk::pdf/$1');
        $routes->post('sertifikat-ukk/(:num)', 'Admin\SertifikatUkk::update/$1');
        $routes->get('sertifikat-ukk/delete/(:num)', 'Admin\SertifikatUkk::delete/$1');

        // ===== MENU UJIAN (ASTS 1 / ASAS / ASTS 2 / ASAT) =====
        // Satu controller melayani keempat jenis lewat slug; sub-menu "ujian
        // susulan" jadi tab, bukan rute terpisah. Aksi ubah data lewat POST
        // (modul baru tidak ikut pola hapus-lewat-GET modul lama).
        // Rute POST diletakkan SEBELUM pola (:segment)/(:segment) generik.
        $routes->get('ujian', 'Admin\Ujian::index');
        $routes->post('ujian/(:segment)/periode', 'Admin\Ujian::simpanPeriode/$1');
        $routes->post('ujian/(:segment)/jadwal', 'Admin\Ujian::simpanJadwal/$1');
        $routes->get('ujian/(:segment)/jadwal/template', 'Admin\UjianBerkas::templateJadwal/$1');
        $routes->get('ujian/(:segment)/jadwal/export', 'Admin\UjianBerkas::exportJadwal/$1');
        $routes->post('ujian/(:segment)/jadwal/import-preview', 'Admin\UjianBerkas::importPreviewJadwal/$1');
        $routes->post('ujian/(:segment)/jadwal/import-commit', 'Admin\UjianBerkas::importCommitJadwal/$1');
        $routes->post('ujian/(:segment)/jadwal/(:num)/hapus', 'Admin\Ujian::hapusJadwal/$1/$2');
        $routes->post('ujian/(:segment)/ketidakhadiran', 'Admin\Ujian::simpanKetidakhadiran/$1');
        $routes->post('ujian/(:segment)/susulan/jadwalkan', 'Admin\Ujian::jadwalkanSusulan/$1');
        $routes->post('ujian/(:segment)/susulan/(:num)/status', 'Admin\Ujian::statusSusulan/$1/$2');
        $routes->post('ujian/(:segment)/susulan/(:num)/hapus', 'Admin\Ujian::hapusSusulan/$1/$2');
        $routes->get('ujian/(:segment)/laporan/pdf', 'Admin\LaporanUjian::pdf/$1');
        $routes->get('ujian/(:segment)/laporan/excel', 'Admin\LaporanUjian::excel/$1');
        $routes->get('ujian/(:segment)/daftar-hadir/(:num)', 'Admin\LaporanUjian::daftarHadir/$1/$2');
        $routes->get('ujian/(:segment)/berita-acara/(:num)', 'Admin\LaporanUjian::beritaAcara/$1/$2');
        $routes->get('ujian/(:segment)/pengawas/(:num)', 'Admin\Ujian::pengawas/$1/$2');
        $routes->post('ujian/(:segment)/pengawas/(:num)', 'Admin\Ujian::simpanPengawas/$1/$2');
        $routes->post('ujian/(:segment)/pengawas/(:num)/hapus/(:num)', 'Admin\Ujian::hapusPengawas/$1/$2/$3');
        // Tab Honor (KHUSUS ADMIN — data gaji). Halaman: GET ujian/{jenis}/honor (pola generik di bawah).
        $routes->post('ujian/(:segment)/honor/buat', 'Admin\UjianHonor::buat/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/dokumen', 'Admin\UjianHonor::simpanDokumen/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/penerima', 'Admin\UjianHonor::tambahPenerima/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/penerima-semua', 'Admin\UjianHonor::tambahSemua/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/baris/(:num)/hapus', 'Admin\UjianHonor::hapusBaris/$1/$2', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/baris/(:num)/jabatan', 'Admin\UjianHonor::ubahJabatan/$1/$2', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/baris/(:num)/pindah', 'Admin\UjianHonor::pindah/$1/$2', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/nilai', 'Admin\UjianHonor::simpanNilai/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/sinkron', 'Admin\UjianHonor::sinkron/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/hapus', 'Admin\UjianHonor::hapusDokumen/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/hitung', 'Admin\UjianHonor::hitung/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/status', 'Admin\UjianHonor::status/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/honor/impor/unggah', 'Admin\UjianHonor::imporUnggah/$1', ['filter' => 'csrf']);
        $routes->get('ujian/(:segment)/honor/impor', 'Admin\UjianHonor::impor/$1');
        $routes->post('ujian/(:segment)/honor/impor/terapkan', 'Admin\UjianHonor::imporTerapkan/$1', ['filter' => 'csrf']);
        // Cetak / unduh (GET, hanya baca; tiap unduhan dicatat di Audit Log).
        $routes->get('ujian/(:segment)/honor/cetak/rekap-xlsx', 'Admin\UjianHonorCetak::rekapXlsx/$1');
        $routes->get('ujian/(:segment)/honor/cetak/rekap-pdf', 'Admin\UjianHonorCetak::rekapPdf/$1');
        $routes->get('ujian/(:segment)/honor/cetak/slip-pdf', 'Admin\UjianHonorCetak::slipPdf/$1');
        $routes->get('ujian/(:segment)/pembuat-soal', 'Admin\UjianHonor::pembuatSoal/$1');
        $routes->post('ujian/(:segment)/pembuat-soal', 'Admin\UjianHonor::simpanPembuatSoal/$1', ['filter' => 'csrf']);
        $routes->post('ujian/(:segment)/pembuat-soal/(:num)/hapus', 'Admin\UjianHonor::hapusPembuatSoal/$1/$2', ['filter' => 'csrf']);
        $routes->get('ujian/(:segment)', 'Admin\Ujian::jenis/$1');
        $routes->get('ujian/(:segment)/(:segment)', 'Admin\Ujian::jenis/$1/$2');

        // ===== HONOR UJIAN (KHUSUS ADMIN — data gaji) =====
        // Tidak ada di daftar hak Operator/Waka Hubin (Config\Peran) → ditolak penjaga rute; controller memeriksa ulang.
        // Semua aksi pengubah data memakai POST + CSRF.
        $routes->get('honor/pengaturan', 'Admin\HonorPengaturan::index');
        $routes->post('honor/pengaturan/komponen', 'Admin\HonorPengaturan::simpanKomponen', ['filter' => 'csrf']);
        $routes->post('honor/pengaturan/komponen/tambah', 'Admin\HonorPengaturan::tambahKomponen', ['filter' => 'csrf']);
        $routes->post('honor/pengaturan/komponen/(:num)/hapus', 'Admin\HonorPengaturan::hapusKomponen/$1', ['filter' => 'csrf']);
        $routes->post('honor/pengaturan/panitia', 'Admin\HonorPengaturan::simpanPanitia', ['filter' => 'csrf']);
        $routes->post('honor/pengaturan/tanda-tangan', 'Admin\HonorPengaturan::simpanTandaTangan', ['filter' => 'csrf']);

        // ===== PENJADWALAN =====
        $routes->get('jadwal', 'Admin\Jadwal::index');
        $routes->post('jadwal/place', 'Admin\Jadwal::place');
        $routes->post('jadwal/remove', 'Admin\Jadwal::remove');
        $routes->post('jadwal/bulk-remove', 'Admin\Jadwal::bulkRemove');
        $routes->post('jadwal/move', 'Admin\Jadwal::move');
        $routes->post('jadwal/generate', 'Admin\Jadwal::generate');
        $routes->get('jadwal/template', 'Admin\Jadwal::template');
        $routes->post('jadwal/import-preview', 'Admin\Jadwal::importPreview');
        $routes->post('jadwal/import-commit', 'Admin\Jadwal::importCommit');

        // Jadwal per guru (untuk dibagikan ke tiap guru; cetak via Admin\Cetak)
        $routes->get('jadwal-guru', 'Admin\JadwalGuru::index');

        // ===== ABSENSI GURU (manual, per sesi) =====
        $routes->get('absensi', 'Admin\Absensi::index');
        $routes->post('absensi/save', 'Admin\Absensi::save');
        $routes->post('absensi/save-kerja', 'Admin\Absensi::saveKerja');
        $routes->post('absensi/unrecord', 'Admin\Absensi::unrecord');
        $routes->post('absensi/template-wa', 'Admin\Absensi::templateWa');
        $routes->get('absensi/pesan-wa', 'Admin\Absensi::pesanWa');
        $routes->get('absensi/piket', 'Admin\AbsensiPiket::index');
        $routes->post('absensi/piket', 'Admin\AbsensiPiket::save');
        $routes->get('absensi/laporan/(:segment)', 'Admin\Absensi::laporan/$1');
        $routes->post('absensi/tarif', 'Admin\Absensi::tarif');
        $routes->get('absensi/rekap', 'Admin\Absensi::rekap');
        $routes->get('absensi/rekap/guru/(:num)', 'Admin\Absensi::rekapGuru/$1');
        $routes->get('absensi/rekap/(:segment)', 'Admin\Absensi::rekap/$1');

        // ===== LAPORAN UKK =====
        $routes->get('laporan-ukk', 'Admin\LaporanUkk::index');
        $routes->get('laporan-ukk/pdf', 'Admin\LaporanUkk::pdf');
        $routes->get('laporan-ukk/excel', 'Admin\LaporanUkk::excel');

        // ===== LAPORAN KURIKULUM =====
        $routes->get('kurikulum/dashboard', 'Admin\Kurikulum::dashboard');
        $routes->get('kurikulum/rekap', 'Admin\Kurikulum::rekap');
        $routes->get('kurikulum/bentrok', 'Admin\Kurikulum::bentrok');

        // ===== PENGUMUMAN =====
        $routes->get('pengumuman', 'Admin\Pengumuman::index');
        $routes->post('pengumuman', 'Admin\Pengumuman::store');
        $routes->post('pengumuman/(:num)', 'Admin\Pengumuman::update/$1');
        $routes->get('pengumuman/delete/(:num)', 'Admin\Pengumuman::delete/$1');

        // ===== AUDIT LOG =====
        $routes->get('audit', 'Admin\AuditLog::index');
        $routes->get('audit/purge', 'Admin\AuditLog::purge');

        // ===== CETAK / EXPORT KURIKULUM (PDF & Excel) =====
        $routes->get('cetak/jadwal-kelas/(:num)/(:segment)', 'Admin\Cetak::jadwalKelas/$1/$2');
        $routes->get('cetak/jadwal-guru/(:num)/(:segment)', 'Admin\Cetak::jadwalGuru/$1/$2');
        $routes->get('cetak/rekap/(:segment)', 'Admin\Cetak::rekap/$1');

        // Export
        $routes->get('export/excel', 'Admin\Export::excel');
        $routes->get('export/rekap-pdf', 'Admin\Export::recapPdf');
        $routes->get('export/surat/(:num)', 'Admin\Export::surat/$1');

        // Pengaturan sekolah
        $routes->get('settings', 'Admin\Settings::index');
        $routes->post('settings', 'Admin\Settings::save');

        // Profil admin
        $routes->get('profile', 'Admin\Profile::index');
        $routes->post('profile', 'Admin\Profile::update');
        $routes->post('profile/password', 'Admin\Profile::password');
    });
});

// ============================================================
// ===================== API v1 (MOBILE) ======================
// ============================================================
// Dikonsumsi aplikasi Flutter (fluter-muslimin). Respons JSON beramplop
// standar. CORS aktif untuk seluruh /api; endpoint admin dilindungi filter
// 'apiauth' (Bearer token). Tidak mengubah rute web yang sudah live.
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors'], static function ($routes) {

    // ---------- Auth ----------
    $routes->post('auth/login', 'Auth::login');
    $routes->post('auth/biometric/login', 'Auth::biometricLogin'); // login sidik jari (tanpa token)
    $routes->options('(:any)', static fn () => service('response')->setStatusCode(204)); // preflight

    // ---------- APP / OTA (PUBLIK) ----------
    // Bootstrap dipanggil app saat start (info versi + update otomatis).
    $routes->get('app/bootstrap', 'AppController::bootstrap');
    // Daftarkan APK rilis (dipakai skrip release.ps1; auth via header X-Apk-Token).
    $routes->post('app/apk/register', 'AppController::register');

    // ---------- PUBLIK (tanpa token) ----------
    $routes->get('home', 'Publik::home');
    $routes->get('absensi', 'Publik::absensi');
    // Statistik siswa (agregat saja — tanpa identitas siswa)
    $routes->get('statistik/siswa', 'Publik::statistikSiswa');
    $routes->get('jadwal/kelas-options', 'Publik::kelasOptions');
    $routes->get('jadwal/guru-options', 'Publik::guruOptions');
    $routes->get('jadwal/kelas/(:num)', 'Publik::jadwalKelas/$1');
    $routes->get('jadwal/guru/(:num)', 'Publik::jadwalGuru/$1');

    // Form kesediaan guru
    $routes->get('form/meta', 'Form::meta');
    $routes->post('form/submit', 'Form::submit');
    $routes->get('form/revisi/(:segment)', 'Form::revisi/$1');
    $routes->post('form/revisi/(:segment)', 'Form::updateRevisi/$1');

    // ---------- ADMIN (butuh token) ----------
    $routes->group('', ['filter' => 'apiauth'], static function ($routes) {
        $routes->get('auth/me', 'Auth::me');
        $routes->post('auth/logout', 'Auth::logout');

        // Kelola login sidik jari (butuh sesi aktif)
        $routes->post('auth/biometric/register', 'Auth::biometricRegister');
        $routes->post('auth/biometric/disable', 'Auth::biometricDisable');
        $routes->get('auth/biometric/status', 'Auth::biometricStatus');

        // Dashboard
        $routes->get('admin/dashboard', 'Dashboard::index');

        // Submissions (kesediaan guru)
        $routes->get('admin/submissions', 'Submissions::index');
        $routes->get('admin/submissions/(:num)', 'Submissions::view/$1');
        $routes->post('admin/submissions/(:num)/status', 'Submissions::updateStatus/$1');
        $routes->delete('admin/submissions/(:num)', 'Submissions::delete/$1');

        // ---------- MASTER DATA ----------
        // Dropdown pendukung form (guru/mapel/kelas/jurusan)
        $routes->get('admin/master/options', 'Admin\Options::index');

        // 7 master data ber-pola CRUD identik
        foreach ([
            'guru'         => 'Guru',
            'mapel'        => 'Mapel',
            'kelas'        => 'Kelas',
            'jurusan'      => 'Jurusan',
            'hari'         => 'Hari',
            'jabatan'      => 'Jabatan',
            'siswa'        => 'Siswa',
            'tahun-ajaran' => 'TahunAjaran',
            'fase'         => 'Fase',
            'lab'          => 'Lab',
            'teknisi'      => 'Teknisi',
            'aset'         => 'Aset',
            'sparepart'    => 'Sparepart',
        ] as $seg => $ctrl) {
            $routes->get("admin/master/{$seg}", "Admin\\{$ctrl}::index");
            $routes->post("admin/master/{$seg}", "Admin\\{$ctrl}::store");
            $routes->post("admin/master/{$seg}/bulk-delete", "Admin\\{$ctrl}::bulkDestroy");
            $routes->post("admin/master/{$seg}/(:num)", "Admin\\{$ctrl}::update/\$1");
            $routes->delete("admin/master/{$seg}/(:num)", "Admin\\{$ctrl}::destroy/\$1");
        }
        // Aset — detail komputer 1:1 (di atas rute (:num) generik agar cocok)
        $routes->get('admin/master/aset/(:num)/komputer', 'Admin\Aset::komputerGet/$1');
        $routes->post('admin/master/aset/(:num)/komputer', 'Admin\Aset::komputerSet/$1');
        // Tahun Ajaran — aktifkan satu (menonaktifkan yang lain)
        $routes->post('admin/master/tahun-ajaran/(:num)/aktifkan', 'Admin\TahunAjaran::aktifkan/$1');

        // Jabatan — daftar ringkas untuk dropdown
        $routes->get('admin/master/jabatan/options', 'Admin\Jabatan::options');

        // Siswa — ringkasan agregat (untuk dashboard admin)
        $routes->get('admin/master/siswa/statistik', 'Admin\Siswa::statistik');
        // Siswa — detail lengkap satu siswa (id angka; 'statistik' di atas tidak bentrok)
        $routes->get('admin/master/siswa/(:num)', 'Admin\Siswa::show/$1');

        // Guru — jabatan yang disandang (boleh lebih dari satu, satu utama)
        $routes->get('admin/master/guru/(:num)/jabatan', 'Admin\Guru::jabatanGet/$1');
        $routes->post('admin/master/guru/(:num)/jabatan', 'Admin\Guru::jabatanSet/$1');

        // Mapel — kompetensi (guru pengampu) & daftar kelompok
        $routes->get('admin/master/mapel/kelompok-list', 'Admin\Mapel::kelompokList');
        $routes->get('admin/master/mapel/(:num)/kompetensi', 'Admin\Mapel::kompetensiGet/$1');
        $routes->post('admin/master/mapel/(:num)/kompetensi', 'Admin\Mapel::kompetensiSet/$1');

        // Jam Pelajaran (daftar per shift)
        $routes->get('admin/master/jam', 'Admin\JamPelajaran::index');
        $routes->post('admin/master/jam', 'Admin\JamPelajaran::store');
        $routes->post('admin/master/jam/bulk-delete', 'Admin\JamPelajaran::bulkDestroy');
        $routes->post('admin/master/jam/(:num)', 'Admin\JamPelajaran::update/$1');
        $routes->delete('admin/master/jam/(:num)', 'Admin\JamPelajaran::destroy/$1');

        // Pengampu / Penugasan (daftar per kelas)
        $routes->get('admin/master/pengampu', 'Admin\Pengampu::index');
        $routes->post('admin/master/pengampu', 'Admin\Pengampu::store');
        $routes->post('admin/master/pengampu/(:num)', 'Admin\Pengampu::update/$1');
        $routes->delete('admin/master/pengampu/(:num)', 'Admin\Pengampu::destroy/$1');

        // Ketersediaan Guru
        $routes->get('admin/master/ketersediaan', 'Admin\Ketersediaan::index');
        $routes->post('admin/master/ketersediaan', 'Admin\Ketersediaan::save');

        // ---------- LABORATORIUM (operasional / jadwal / laporan) ----------
        $routes->get('admin/peminjaman', 'Admin\Peminjaman::index');
        $routes->post('admin/peminjaman', 'Admin\Peminjaman::store');
        $routes->post('admin/peminjaman/(:num)/kembalikan', 'Admin\Peminjaman::kembalikan/$1');
        $routes->delete('admin/peminjaman/(:num)', 'Admin\Peminjaman::destroy/$1');

        $routes->get('admin/kerusakan', 'Admin\Kerusakan::index');
        $routes->post('admin/kerusakan', 'Admin\Kerusakan::store');
        $routes->post('admin/kerusakan/(:num)/status', 'Admin\Kerusakan::status/$1');
        $routes->delete('admin/kerusakan/(:num)', 'Admin\Kerusakan::destroy/$1');

        $routes->get('admin/perbaikan', 'Admin\Perbaikan::index');
        $routes->post('admin/perbaikan', 'Admin\Perbaikan::store');
        $routes->delete('admin/perbaikan/(:num)', 'Admin\Perbaikan::destroy/$1');

        $routes->get('admin/jadwal-lab', 'Admin\JadwalLab::index');
        $routes->post('admin/jadwal-lab', 'Admin\JadwalLab::store');
        $routes->delete('admin/jadwal-lab/(:num)', 'Admin\JadwalLab::destroy/$1');

        $routes->get('admin/jurnal-lab', 'Admin\JurnalLab::index');
        $routes->post('admin/jurnal-lab', 'Admin\JurnalLab::store');
        $routes->delete('admin/jurnal-lab/(:num)', 'Admin\JurnalLab::destroy/$1');

        $routes->get('admin/laporan-lab', 'Admin\LaporanLab::index');

        // ---------- MENU UJIAN (ASTS 1 / ASAS / ASTS 2 / ASAT) ----------
        // {slug} = asts1|asas|asts2|asat. Semua endpoint menerima ?tp= untuk
        // membuka tahun pelajaran lama (kosong = tahun berjalan).
        // Rute ber-segmen literal didaftarkan SEBELUM pola (:num) agar tidak
        // tertelan, dan sebelum 'ujian/(:segment)' yang paling generik.
        $routes->get('admin/ujian', 'Admin\Ujian::index');

        $routes->get('admin/ujian/(:segment)/jadwal', 'Admin\UjianJadwal::index/$1');
        $routes->post('admin/ujian/(:segment)/jadwal', 'Admin\UjianJadwal::store/$1');
        $routes->get('admin/ujian/(:segment)/jadwal/(:num)/kelas', 'Admin\UjianJadwal::kelas/$1/$2');
        $routes->get('admin/ujian/(:segment)/jadwal/(:num)/pengawas', 'Admin\UjianJadwal::pengawasIndex/$1/$2');
        $routes->post('admin/ujian/(:segment)/jadwal/(:num)/pengawas', 'Admin\UjianJadwal::pengawasStore/$1/$2');
        $routes->delete('admin/ujian/(:segment)/jadwal/(:num)/pengawas/(:num)', 'Admin\UjianJadwal::pengawasDestroy/$1/$2/$3');
        $routes->delete('admin/ujian/(:segment)/jadwal/(:num)', 'Admin\UjianJadwal::destroy/$1/$2');

        $routes->get('admin/ujian/(:segment)/ketidakhadiran', 'Admin\UjianSusulan::ketidakhadiran/$1');
        $routes->post('admin/ujian/(:segment)/ketidakhadiran', 'Admin\UjianSusulan::simpanKetidakhadiran/$1');

        $routes->get('admin/ujian/(:segment)/susulan', 'Admin\UjianSusulan::index/$1');
        $routes->post('admin/ujian/(:segment)/susulan/jadwalkan', 'Admin\UjianSusulan::jadwalkan/$1');
        $routes->post('admin/ujian/(:segment)/susulan/(:num)/status', 'Admin\UjianSusulan::status/$1/$2');
        $routes->delete('admin/ujian/(:segment)/susulan/(:num)', 'Admin\UjianSusulan::destroy/$1/$2');

        // Unduhan berkas cetak (balasannya BINER, bukan JSON beramplop).
        $routes->get('admin/ujian/(:segment)/cetak', 'Admin\UjianCetak::index/$1');
        $routes->get('admin/ujian/(:segment)/cetak/rekap-pdf', 'Admin\UjianCetak::rekapPdf/$1');
        $routes->get('admin/ujian/(:segment)/cetak/rekap-excel', 'Admin\UjianCetak::rekapExcel/$1');
        $routes->get('admin/ujian/(:segment)/cetak/jadwal-excel', 'Admin\UjianCetak::jadwalExcel/$1');

        // ===== Isian Biodata Siswa (cermin menu web; form siswa tetap di web) =====
        $routes->get('admin/biodata', 'Admin\Biodata::index');
        $routes->get('admin/biodata/meta', 'Admin\Biodata::meta');
        $routes->post('admin/biodata/pengaturan', 'Admin\Biodata::pengaturan');
        $routes->get('admin/biodata/kelas', 'Admin\Biodata::kelas');
        $routes->get('admin/biodata/isian', 'Admin\Biodata::isian');
        $routes->get('admin/biodata/belum', 'Admin\Biodata::belum');
        $routes->get('admin/biodata/laporan', 'Admin\Biodata::laporan');
        $routes->post('admin/biodata/setujui-massal', 'Admin\Biodata::setujuiMassal');
        $routes->get('admin/biodata/isian/(:num)', 'Admin\Biodata::detail/$1');
        $routes->post('admin/biodata/isian/(:num)/setujui', 'Admin\Biodata::setujui/$1');
        $routes->post('admin/biodata/isian/(:num)/kembalikan', 'Admin\Biodata::kembalikan/$1');
        $routes->delete('admin/biodata/isian/(:num)', 'Admin\Biodata::hapus/$1');
        $routes->get('admin/ujian/(:segment)/cetak/daftar-hadir/(:num)', 'Admin\UjianCetak::daftarHadir/$1/$2');
        $routes->get('admin/ujian/(:segment)/cetak/berita-acara/(:num)', 'Admin\UjianCetak::beritaAcara/$1/$2');

        // ---------- HONOR UJIAN (KHUSUS ADMIN — data gaji; kontrak: docs/API-HONOR.md) ----------
        // Operator & Waka Hubin sudah ditolak penyaring apiauth (hanya awalan 'pkl'); controller memeriksa ulang peran Admin.
        // `tp` (tahun pelajaran) di query atau body; aksi mengubah data memakai POST/DELETE. Impor Excel hanya di web.
        $routes->get('admin/honor/pengaturan', 'Admin\HonorPengaturan::show');
        $routes->post('admin/honor/pengaturan/komponen', 'Admin\HonorPengaturan::komponen');
        $routes->post('admin/honor/pengaturan/komponen/tambah', 'Admin\HonorPengaturan::tambah');
        $routes->delete('admin/honor/pengaturan/komponen/(:num)', 'Admin\HonorPengaturan::hapus/$1');
        $routes->post('admin/honor/pengaturan/panitia', 'Admin\HonorPengaturan::panitia');
        $routes->post('admin/honor/pengaturan/tanda-tangan', 'Admin\HonorPengaturan::tandaTangan');

        $routes->get('admin/ujian/(:segment)/honor', 'Admin\Honor::show/$1');
        $routes->post('admin/ujian/(:segment)/honor', 'Admin\Honor::store/$1');
        $routes->delete('admin/ujian/(:segment)/honor', 'Admin\Honor::destroy/$1');
        $routes->get('admin/ujian/(:segment)/honor/calon', 'Admin\Honor::calon/$1');
        $routes->post('admin/ujian/(:segment)/honor/dokumen', 'Admin\Honor::dokumen/$1');
        $routes->post('admin/ujian/(:segment)/honor/penerima', 'Admin\Honor::penerima/$1');
        $routes->post('admin/ujian/(:segment)/honor/penerima/semua', 'Admin\Honor::penerimaSemua/$1');
        $routes->delete('admin/ujian/(:segment)/honor/baris/(:num)', 'Admin\Honor::hapusBaris/$1/$2');
        $routes->post('admin/ujian/(:segment)/honor/baris/(:num)/jabatan', 'Admin\Honor::jabatan/$1/$2');
        $routes->post('admin/ujian/(:segment)/honor/baris/(:num)/pindah', 'Admin\Honor::pindah/$1/$2');
        $routes->post('admin/ujian/(:segment)/honor/nilai', 'Admin\Honor::nilai/$1');
        $routes->post('admin/ujian/(:segment)/honor/sinkron', 'Admin\Honor::sinkron/$1');
        $routes->post('admin/ujian/(:segment)/honor/hitung', 'Admin\Honor::hitung/$1');
        $routes->post('admin/ujian/(:segment)/honor/status', 'Admin\Honor::status/$1');
        $routes->get('admin/ujian/(:segment)/honor/cetak', 'Admin\HonorCetak::index/$1');
        $routes->get('admin/ujian/(:segment)/honor/cetak/rekap-pdf', 'Admin\HonorCetak::rekapPdf/$1');
        $routes->get('admin/ujian/(:segment)/honor/cetak/rekap-xlsx', 'Admin\HonorCetak::rekapXlsx/$1');
        $routes->get('admin/ujian/(:segment)/honor/cetak/slip-pdf', 'Admin\HonorCetak::slipPdf/$1');
        $routes->get('admin/ujian/(:segment)/pembuat-soal', 'Admin\Honor::pembuatSoal/$1');
        $routes->post('admin/ujian/(:segment)/pembuat-soal', 'Admin\Honor::pembuatSoalTambah/$1');
        $routes->delete('admin/ujian/(:segment)/pembuat-soal/(:num)', 'Admin\Honor::pembuatSoalCabut/$1/$2');

        $routes->get('admin/ujian/(:segment)/rekap', 'Admin\Ujian::rekap/$1');
        $routes->post('admin/ujian/(:segment)/periode', 'Admin\Ujian::simpanPeriode/$1');
        $routes->get('admin/ujian/(:segment)', 'Admin\Ujian::show/$1');

        // Galeri foto SIMLAB (semua entitas) — unggah multipart, auto-WEBP
        // ===== Manajemen Dokumen (SIMDOK) =====
        // Rute spesifik didahulukan agar tidak tertelan pola (:num).
        $routes->get('admin/dokumen', 'Admin\Dokumen::index');
        $routes->get('admin/dokumen/sampah', 'Admin\Dokumen::sampah');
        $routes->get('admin/dokumen/penyimpanan', 'Admin\Dokumen::penyimpanan');
        $routes->post('admin/dokumen/unggah', 'Admin\Dokumen::unggah');
        $routes->post('admin/dokumen/tautan', 'Admin\Dokumen::tautanStore');
        $routes->post('admin/dokumen/pindah', 'Admin\Dokumen::pindah');
        $routes->post('admin/dokumen/folder', 'Admin\Dokumen::folderStore');
        $routes->post('admin/dokumen/folder/(:num)', 'Admin\Dokumen::folderUpdate/$1');
        $routes->delete('admin/dokumen/folder/(:num)', 'Admin\Dokumen::folderDestroy/$1');
        $routes->post('admin/dokumen/folder/(:num)/pulihkan', 'Admin\Dokumen::pulihkanFolder/$1');
        $routes->post('admin/dokumen/folder/(:num)/bagikan', 'Admin\Dokumen::bagikanFolder/$1');
        $routes->post('admin/dokumen/share/(:num)/cabut', 'Admin\Dokumen::cabutShare/$1');
        $routes->match(['GET', 'HEAD'], 'admin/dokumen/(:num)/berkas', 'Admin\Dokumen::berkas/$1');
        $routes->match(['GET', 'HEAD'], 'admin/dokumen/(:num)/unduh', 'Admin\Dokumen::unduh/$1');
        $routes->match(['GET', 'HEAD'], 'admin/dokumen/(:num)/thumb', 'Admin\Dokumen::thumb/$1');
        $routes->post('admin/dokumen/(:num)/pulihkan', 'Admin\Dokumen::pulihkan/$1');
        $routes->post('admin/dokumen/(:num)/bagikan', 'Admin\Dokumen::bagikan/$1');
        $routes->delete('admin/dokumen/(:num)/permanen', 'Admin\Dokumen::hapusPermanen/$1');
        $routes->get('admin/dokumen/(:num)', 'Admin\Dokumen::detail/$1');
        $routes->post('admin/dokumen/(:num)', 'Admin\Dokumen::update/$1');
        $routes->delete('admin/dokumen/(:num)', 'Admin\Dokumen::destroy/$1');

        $routes->delete('admin/lab-gambar/(:num)', 'Admin\LabGambar::destroy/$1');
        $routes->get('admin/lab-gambar/(:segment)/(:num)', 'Admin\LabGambar::index/$1/$2');
        $routes->post('admin/lab-gambar/(:segment)/(:num)', 'Admin\LabGambar::upload/$1/$2');

        // ---------- PENGUMUMAN ----------
        $routes->get('admin/pengumuman', 'Admin\Pengumuman::index');
        $routes->post('admin/pengumuman', 'Admin\Pengumuman::store');
        $routes->post('admin/pengumuman/(:num)', 'Admin\Pengumuman::update/$1');
        $routes->delete('admin/pengumuman/(:num)', 'Admin\Pengumuman::destroy/$1');

        // ---------- ABSENSI ----------
        $routes->get('admin/absensi', 'Admin\Absensi::index');
        $routes->post('admin/absensi/save', 'Admin\Absensi::save');
        $routes->post('admin/absensi/save-kerja', 'Admin\Absensi::saveKerja');
        $routes->post('admin/absensi/unrecord', 'Admin\Absensi::unrecord');
        $routes->get('admin/absensi/template-wa', 'Admin\Absensi::templateWa');
        $routes->post('admin/absensi/template-wa', 'Admin\Absensi::templateWaSave');
        $routes->get('admin/absensi/pesan-wa', 'Admin\Absensi::pesanWa');
        $routes->get('admin/absensi/piket', 'Admin\AbsensiPiket::index');
        $routes->post('admin/absensi/piket', 'Admin\AbsensiPiket::save');
        $routes->get('admin/absensi/laporan/(:segment)', 'Admin\Absensi::laporan/$1');
        $routes->get('admin/absensi/tarif', 'Admin\Absensi::tarif');
        $routes->post('admin/absensi/tarif', 'Admin\Absensi::tarifSave');
        $routes->get('admin/absensi/rekap', 'Admin\Absensi::rekap');
        $routes->get('admin/absensi/rekap/export/(:segment)', 'Admin\Absensi::rekapExport/$1');
        $routes->get('admin/absensi/rekap/(:num)', 'Admin\Absensi::rekapGuru/$1');

        // ---------- NOTIFIKASI JADWAL GURU (Firebase) ----------
        $routes->get('admin/notif', 'Admin\Notif::index');
        $routes->get('admin/notif/opsi', 'Admin\Notif::opsi');
        $routes->post('admin/notif/pengaturan', 'Admin\Notif::pengaturan');
        $routes->get('admin/notif/aturan', 'Admin\Notif::aturan');
        $routes->post('admin/notif/aturan', 'Admin\Notif::aturanStore');
        $routes->post('admin/notif/aturan/(:num)', 'Admin\Notif::aturanUpdate/$1');
        $routes->delete('admin/notif/aturan/(:num)', 'Admin\Notif::aturanDestroy/$1');
        $routes->post('admin/notif/perangkat', 'Admin\Notif::perangkat');
        $routes->post('admin/notif/perangkat/terima', 'Admin\Notif::perangkatTerima');
        $routes->post('admin/notif/perangkat/hapus', 'Admin\Notif::perangkatHapus');
        $routes->get('admin/notif/pratinjau', 'Admin\Notif::pratinjau');
        $routes->post('admin/notif/uji', 'Admin\Notif::uji');
        $routes->get('admin/notif/riwayat', 'Admin\Notif::riwayat');

        // ---------- PKL / PRAKERIN (Operator, Waka Hubin, Admin) ----------
        // Gerbang peran: Config\Peran 'api_akses' (awalan "pkl"). Aturan ACC (hanya Waka Hubin, Admin = cadangan
        // "mewakili") dijaga di Libraries\PklKeputusan — sama persis dengan web. Dokumentasi: docs/API-PKL.md.
        $routes->get('pkl/meta', 'Pkl::meta');
        $routes->get('pkl/ringkasan', 'Pkl::ringkasan');
        $routes->get('pkl/ajuan', 'Pkl::daftar');
        $routes->post('pkl/ajuan', 'Pkl::buat');
        $routes->get('pkl/ajuan/(:num)', 'Pkl::detail/$1');
        $routes->post('pkl/ajuan/(:num)/ubah', 'Pkl::ubah/$1');
        $routes->delete('pkl/ajuan/(:num)', 'Pkl::hapus/$1');
        $routes->post('pkl/ajuan/(:num)/acc', 'Pkl::acc/$1');
        $routes->post('pkl/ajuan/(:num)/kembalikan', 'Pkl::kembalikan/$1');
        $routes->post('pkl/ajuan/(:num)/tolak', 'Pkl::tolak/$1');
        $routes->post('pkl/ajuan/(:num)/batal-acc', 'Pkl::batalAcc/$1');
        $routes->post('pkl/ajuan/(:num)/surat', 'Pkl::surat/$1');
        $routes->post('pkl/acc-massal', 'Pkl::accMassal');
        $routes->post('pkl/surat-massal', 'Pkl::suratMassal');
        $routes->get('pkl/siswa', 'Pkl::siswa');
        $routes->get('pkl/siswa/ringkasan', 'Pkl::siswaRingkas');
        $routes->get('pkl/siswa-kelas', 'Pkl::siswaKelas');
        $routes->get('pkl/ttd', 'Pkl::ttd');
        $routes->get('pkl/ttd/gambar', 'Pkl::ttdGambar');
        $routes->post('pkl/ttd', 'Pkl::ttdUnggah');
        $routes->delete('pkl/ttd', 'Pkl::ttdHapus');
        // Hak yang diatur Admin (PklHak) — biaya saat unduh surat, WhatsApp manual, Laporan Pembayaran, Hak Akses. Hak dijaga di controller.
        $routes->get('pkl/surat/siap', 'Pkl::suratSiap');
        $routes->get('pkl/biaya', 'Pkl::biaya');
        $routes->post('pkl/biaya', 'Pkl::biayaSimpan');
        $routes->get('pkl/ajuan/(:num)/pembayaran', 'Pkl::pembayaran/$1');
        $routes->post('pkl/ajuan/(:num)/pembayaran/hapus', 'Pkl::pembayaranHapus/$1');
        $routes->post('pkl/ajuan/(:num)/pembayaran/beasiswa-cabut', 'Pkl::beasiswaCabut/$1');
        $routes->get('pkl/wa', 'Pkl::wa');
        $routes->post('pkl/ajuan/(:num)/wa/(:num)/tandai', 'Pkl::waTandai/$1/$2');
        $routes->get('pkl/laporan', 'Pkl::laporan');
        $routes->get('pkl/laporan/excel', 'Pkl::laporanExcel');
        $routes->get('pkl/hak-akses', 'Pkl::hakAkses');
        $routes->post('pkl/hak-akses', 'Pkl::hakAksesSimpan');


        // ---------- PROFIL & PENGATURAN ----------
        $routes->get('admin/profile', 'Admin\Profile::show');
        $routes->post('admin/profile', 'Admin\Profile::update');
        $routes->post('admin/profile/password', 'Admin\Profile::password');
        $routes->get('admin/settings', 'Admin\Settings::show');
        $routes->post('admin/settings', 'Admin\Settings::save');
    });
});
