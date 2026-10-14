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

    /**
     * Grup (urutan menu samping) → entri. Entri: [kunci => [label, jenis, item menu samping, opsi]].
     * Item menu samping: [url, label, ikon, persis?, segera?]; kosong = halaman tanpa menu sendiri. Ikon = nama Flowbite Icons
     * (helper ikon(); nama baru: tambahkan di resources/ikon.json lalu npm run vendor — php spark dev:cek-ui memeriksa semua ikon menu).
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
            $jenisSurat[] = [SuratJenis::alamat($k), SuratJenis::label($k), 'file-lines', false, ! SuratJenis::siap($k)];
            if ($k === SuratJenis::BALASAN) {
                $jenisSurat[] = [SuratJenis::alamat($k) . '/laporan', 'Lapor Diterima PKL', 'inbox-full', false, ! SuratJenis::siap($k)];
            }
        }

        return [
            ['judul' => null, 'entri' => [
                'dashboard' => ['Dashboard', self::MENU, [['admin/dashboard', 'Dashboard', 'chart-pie']]],
            ]],
            ['judul' => 'KESISWAAN', 'entri' => [
                'master_siswa'   => ['Data Siswa', self::MENU, [['admin/master/siswa', 'Data Siswa', 'user-graduate']]],
                'master_kelas'   => ['Kelas', self::MENU, [['admin/master/kelas', 'Kelas', 'chalkboard']]],
                'master_jurusan' => ['Jurusan', self::MENU, [['admin/master/jurusan', 'Jurusan', 'school']]],
                'biodata'        => ['Isian Biodata Siswa', self::MENU, [['admin/biodata', 'Isian Biodata Siswa', 'profile-card']]],
                'tahun_ajaran'   => ['Tahun Ajaran', self::MENU, [], ['awalan' => ['admin/master/tahun-ajaran'], 'ket' => 'Halaman tanpa menu samping (admin/master/tahun-ajaran).']],
            ]],
            ['judul' => 'GURU', 'entri' => [
                'master_guru'         => ['Guru', self::MENU, [['admin/master/guru', 'Guru', 'users']]],
                'master_jabatan'      => ['Jabatan', self::MENU, [['admin/master/jabatan', 'Jabatan', 'badge-check']]],
                'master_ketersediaan' => ['Ketersediaan Guru', self::MENU, [['admin/master/ketersediaan', 'Ketersediaan Guru', 'calendar-edit']]],
                'kesediaan'           => ['Data Kesediaan', self::MENU, [['admin/submissions', 'Data Kesediaan', 'clipboard-list']], ['awalan' => ['admin/submissions', 'admin/export'], 'ket' => 'Termasuk unduhan Excel/PDF kesediaan (admin/export).']],
                'master_pengampu'     => ['Penugasan', self::MENU, [['admin/master/pengampu', 'Penugasan', 'clipboard-check']]],
                'skbm'                => ['SKBM', self::MENU, [['admin/skbm', 'SKBM', 'file-lines']], ['ket' => 'Sumber ceklis Koreksi honor (data beban mengajar).']],
            ]],
            ['judul' => 'JADWAL & ABSENSI', 'entri' => [
                'master_mapel'  => ['Mata Pelajaran', self::MENU, [['admin/master/mapel', 'Mata Pelajaran', 'book']]],
                'master_hari'   => ['Hari', self::MENU, [['admin/master/hari', 'Hari', 'calendar-month']]],
                'master_jam'    => ['Jam Pelajaran', self::MENU, [['admin/master/jam', 'Jam Pelajaran', 'clock']]],
                'jadwal'        => ['Jadwal KBM', self::MENU, [['admin/jadwal', 'Jadwal KBM', 'grid']], ['awalan' => ['admin/jadwal', 'admin/cetak/jadwal-kelas', 'admin/kurikulum/dashboard'], 'ket' => 'Termasuk cetak jadwal kelas dan dasbor kurikulum.']],
                'jadwal_guru'   => ['Jadwal Guru', self::MENU, [['admin/jadwal-guru', 'Jadwal Guru', 'chalkboard-user']], ['awalan' => ['admin/jadwal-guru', 'admin/cetak/jadwal-guru']]],
                'absensi'       => ['Absensi Guru', self::MENU, [['admin/absensi', 'Absensi Guru', 'fingerprint']], ['ket' => 'Termasuk pesan WA, tarif transport, dan laporan harian.']],
                'absensi_piket' => ['Jadwal Piket', self::MENU, [['admin/absensi/piket', 'Jadwal Piket', 'calendar-plus']]],
                'bentrok'       => ['Deteksi Bentrok', self::MENU, [['admin/kurikulum/bentrok', 'Deteksi Bentrok', 'exclamation-circle']]],
                'rekap_beban'   => ['Rekap Beban', self::MENU, [['admin/kurikulum/rekap', 'Rekap Beban', 'chart']], ['awalan' => ['admin/kurikulum/rekap', 'admin/cetak/rekap']]],
                'rekap_absensi' => ['Rekap Absensi', self::MENU, [['admin/absensi/rekap', 'Rekap Absensi', 'chart-line-up']]],
                'fase'          => ['Fase Kurikulum', self::MENU, [], ['awalan' => ['admin/master/fase'], 'ket' => 'Halaman tanpa menu samping (admin/master/fase).']],
            ]],
            ['judul' => 'UJIAN', 'entri' => [
                'ujian_asts1' => ['ASTS 1', self::MENU, [['admin/ujian/asts1', 'ASTS 1', 'file-pen']]],
                'ujian_asas'  => ['ASAS', self::MENU, [['admin/ujian/asas', 'ASAS', 'file-pen']]],
                'ujian_asts2' => ['ASTS 2', self::MENU, [['admin/ujian/asts2', 'ASTS 2', 'file-pen']]],
                'ujian_asat'  => ['ASAT', self::MENU, [['admin/ujian/asat', 'ASAT', 'file-pen']]],
                // Akar admin/ujian (mengalihkan ke ASTS 1): boleh bila peran memegang salah satu jenis ujian.
                'ujian_akar'  => ['Ujian (akar)', self::MENU, [], ['awalan' => ['admin/ujian'], 'ikut' => ['ujian_asts1', 'ujian_asas', 'ujian_asts2', 'ujian_asat']]],
                'honor_ujian' => ['Honor & Koreksi Ujian', self::MENU, [], [
                    'awalan' => ['admin/ujian/asts1/honor', 'admin/ujian/asas/honor', 'admin/ujian/asts2/honor', 'admin/ujian/asat/honor',
                        'admin/ujian/asts1/pembuat-soal', 'admin/ujian/asas/pembuat-soal', 'admin/ujian/asts2/pembuat-soal', 'admin/ujian/asat/pembuat-soal'],
                    'ket' => 'Tab Honor di halaman tiap ujian (data honor/gaji, Koreksi, pembuat soal). Tanpa ini tab Honor tidak tampil.',
                ]],
                'honor_pengaturan' => ['Pengaturan Honor', self::MENU, [['admin/honor/pengaturan', 'Pengaturan Honor', 'adjustments-horizontal']], ['awalan' => ['admin/honor']]],
            ]],
            ['judul' => 'UJI KOMPETENSI (UKK)', 'entri' => [
                'ukk_paket'    => ['Paket Soal', self::MENU, [['admin/master/paket-soal-ukk', 'Paket Soal', 'layers']]],
                'ukk_tempat'   => ['Tempat Uji', self::MENU, [['admin/master/tempat-uji', 'Tempat Uji', 'map-pin']]],
                'ukk_penguji'  => ['Penguji Eksternal', self::MENU, [['admin/master/penguji-eksternal', 'Penguji Eksternal', 'address-book']]],
                'ukk_peserta'  => ['Peserta UKK', self::MENU, [['admin/peserta-ukk', 'Peserta UKK', 'users-group']]],
                'ukk_jadwal'   => ['Jadwal UKK', self::MENU, [['admin/jadwal-ukk', 'Jadwal UKK', 'calendar-week']]],
                'ukk_nilai'    => ['Penilaian UKK', self::MENU, [['admin/penilaian-ukk', 'Penilaian UKK', 'star']]],
                'ukk_ba'       => ['Berita Acara', self::MENU, [['admin/berita-acara-ukk', 'Berita Acara', 'newspaper']]],
                'ukk_sertifikat' => ['Sertifikat', self::MENU, [['admin/sertifikat-ukk', 'Sertifikat', 'award']]],
                'ukk_rekap'    => ['Rekap UKK', self::MENU, [['admin/laporan-ukk', 'Rekap UKK', 'chart-mixed']]],
            ]],
            ['judul' => 'PKL / PRAKERIN', 'entri' => [
                // Modul: isi menu (Laporan, Tanda Tangan, Pengaturan, …) tampil menurut hak rinci PKL (PklHak).
                'pkl' => ['PKL / Prakerin', self::MODUL, [
                    ['admin/pkl', 'Beranda PKL', 'briefcase', true],
                    ['admin/pkl/daftar', 'Kotak Masuk', 'inbox'],
                    ['admin/pkl/baru', 'Isi atas Nama', 'user-edit'],
                    ['admin/pkl/siswa', 'Status Siswa', 'user-circle'],
                    ['admin/pkl/laporan', 'Laporan Pembayaran', 'receipt'],
                    ['admin/pkl/ttd', 'Tanda Tangan', 'pen-nib'],
                    ['admin/pkl/pengaturan', 'Pengaturan PKL', 'adjustments-horizontal'],
                ], ['api' => ['pkl'], 'ket' => 'Ajuan PKL siswa, surat izin, biaya, tanda tangan. Aksi di dalamnya diatur di bagian "PKL & Surat".']],
            ]],
            ['judul' => 'LABORATORIUM', 'entri' => [
                'master_lab'       => ['Laboratorium', self::MENU, [['admin/master/lab', 'Laboratorium', 'desktop-pc']], ['awalan' => ['admin/master/lab', 'admin/lab-gambar/lab']]],
                'master_aset'      => ['Aset / Inventaris', self::MENU, [['admin/master/aset', 'Aset / Inventaris', 'archive']], ['awalan' => ['admin/master/aset', 'admin/lab-gambar/aset']]],
                'master_sparepart' => ['Sparepart', self::MENU, [['admin/master/sparepart', 'Sparepart', 'tools']], ['awalan' => ['admin/master/sparepart', 'admin/lab-gambar/sparepart']]],
                'master_teknisi'   => ['Teknisi', self::MENU, [['admin/master/teknisi', 'Teknisi', 'user-headset']]],
                'peminjaman'       => ['Peminjaman', self::MENU, [['admin/peminjaman', 'Peminjaman', 'clock-arrow']], ['awalan' => ['admin/peminjaman', 'admin/lab-gambar/peminjaman']]],
                'kerusakan'        => ['Kerusakan', self::MENU, [['admin/kerusakan', 'Kerusakan', 'bug']], ['awalan' => ['admin/kerusakan', 'admin/lab-gambar/kerusakan']]],
                'perbaikan'        => ['Perbaikan', self::MENU, [['admin/perbaikan', 'Perbaikan', 'hammer']], ['awalan' => ['admin/perbaikan', 'admin/lab-gambar/perbaikan']]],
                'jadwal_lab'       => ['Jadwal Lab', self::MENU, [['admin/jadwal-lab', 'Jadwal Lab', 'calendar-month']]],
                'jurnal_lab'       => ['Jurnal Lab', self::MENU, [['admin/jurnal-lab', 'Jurnal Lab', 'book-open']]],
                'laporan_lab'      => ['Laporan Lab', self::MENU, [['admin/laporan-lab', 'Laporan Lab', 'file-chart-bar']]],
                // Hapus foto (admin/lab-gambar/hapus/{id}): boleh bila memegang salah satu entri ber-foto; controller memeriksa entitasnya.
                'lab_foto'         => ['Foto lab (akar)', self::MENU, [], ['awalan' => ['admin/lab-gambar'], 'ikut' => ['master_lab', 'master_aset', 'master_sparepart', 'peminjaman', 'kerusakan', 'perbaikan']]],
            ]],
            // SURAT SEKOLAH (docs/DESAIN-SURAT-SEKOLAH.md): Daftar Surat + satu menu per jenis surat (dari SuratJenis). Jenis yang
            // halamannya BELUM dibangun (tidak di SuratJenis::SIAP) tampil redup bertanda "Segera". Siapa melihat item jenis =
            // hak rinci per jenis (PklHak).
            ['judul' => 'SURAT SEKOLAH', 'entri' => [
                'surat' => ['Surat Sekolah', self::MODUL, array_merge(
                    [['admin/surat', 'Daftar Surat', 'envelope', true]],
                    $jenisSurat
                ), ['api' => ['surat'], 'ket' => 'Daftar Surat dan surat per jenis. Buat/ACC/Unduh tiap jenis diatur di bagian "PKL & Surat".']],
            ]],
            ['judul' => 'ARSIP & INFO', 'entri' => [
                'dokumen'    => ['Arsip Dokumen', self::MENU, [['admin/dokumen', 'Arsip Dokumen', 'folder-open']]],
                'pengumuman' => ['Pengumuman', self::MENU, [['admin/pengumuman', 'Pengumuman', 'bullhorn']]],
            ]],
            ['judul' => 'SISTEM', 'entri' => [
                'pengaturan_sekolah' => ['Pengaturan Sekolah', self::MENU, [['admin/settings', 'Pengaturan Sekolah', 'cog']]],
                'akun'      => ['Kelola Akun', self::ADMIN, [['admin/akun', 'Kelola Akun', 'user-settings']], ['ket' => 'Khusus Admin: membuat akun dan memilih perannya.']],
                'hak_akses' => ['Hak Akses', self::ADMIN, [['admin/hak-akses', 'Hak Akses', 'lock']], ['ket' => 'Khusus Admin: mengatur siapa boleh apa.']],
                'audit'     => ['Audit Log', self::MENU, [['admin/audit', 'Audit Log', 'file-search']]],
                'profil'    => ['Profil Saya', self::UMUM, [['admin/profile', 'Profil Saya', 'user']]],
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
