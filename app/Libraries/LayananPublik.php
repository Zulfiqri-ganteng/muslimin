<?php

namespace App\Libraries;

use App\Models\BiodataIsianModel;
use App\Models\PklPengaturanModel;

/**
 * Daftar layanan halaman publik — SATU sumber untuk beranda (ubin & kartu layanan), halaman Siswa / Guru / Orang Tua, panel
 * "Daftar layanan" di kepala, saran kotak cari, halaman /cari, dan tanya-jawab. Rancangan: docs/RENCANA-TAMPILAN-PUBLIK.md bagian 4.
 *
 * Syarat tampil memakai saklar yang SUDAH ada (tidak ada aturan baru):
 *   - disembunyikan bila dimatikan demi privasi: jadwal (jadwal_publik), absensi (absensi_publik), dokumen (dokumen_publik);
 *   - formulir yang sedang ditutup tetap tampil berlabel "Sedang ditutup": PKL & Lapor (Pengaturan PKL), biodata (biodata_open +
 *     biodata_tutup), kesediaan guru (form_open).
 */
final class LayananPublik
{
    public const BUKA  = 'buka';
    public const TUTUP = 'tutup';

    /**
     * Kelompok pengguna (ubin "Jelajahi layanan" di beranda, panel kepala, halaman /siswa, /guru, /orang-tua).
     *
     * @return array<string, array{label: string, ikon: string, url: string, ringkas: string, ket: string}>
     */
    public static function kelompok(): array
    {
        return [
            'siswa' => [
                'label'   => 'Siswa',
                'ikon'    => 'user-graduate',
                'ilu'     => 'siswa',
                'url'     => site_url('siswa'),
                'ringkas' => 'Pengajuan PKL, biodata, dan jadwal pelajaran',
                'ket'     => 'Layanan untuk siswa: pengajuan dan lapor PKL, pengisian biodata, jadwal pelajaran, serta informasi sekolah.',
            ],
            'guru' => [
                'label'   => 'Guru',
                'ikon'    => 'chalkboard-user',
                'ilu'     => 'guru',
                'url'     => site_url('guru'),
                'ringkas' => 'Jadwal mengajar, kehadiran, dan formulir guru',
                'ket'     => 'Layanan untuk guru dan staf: jadwal mengajar, kehadiran guru, formulir kesediaan mengajar, dan panel sekolah.',
            ],
            'orang-tua' => [
                'label'   => 'Orang Tua',
                'ikon'    => 'users-group',
                'ilu'     => 'orang-tua',
                'url'     => site_url('orang-tua'),
                'ringkas' => 'Pengumuman, jadwal anak, dan kontak sekolah',
                'ket'     => 'Informasi untuk orang tua dan masyarakat: pengumuman, jadwal kelas, dokumen sekolah, profil, dan kontak sekolah.',
            ],
            'sekolah' => [
                'label'   => 'Profil Sekolah',
                'ikon'    => 'school',
                'ilu'     => 'sekolah',
                'url'     => site_url('profil'),
                'ringkas' => 'Identitas, foto, dan data sekolah',
                'ket'     => 'Identitas sekolah, foto kegiatan, data sekolah dalam angka, dan kontak.',
            ],
        ];
    }

    /**
     * Semua layanan yang boleh tampil, urut sesuai katalog.
     * $cekStatus = false: tanpa membaca status buka/tutup formulir (kepala & saran cari di setiap halaman → tanpa kueri tambahan).
     *
     * @return array<string, array{kunci: string, judul: string, ket: string, ikon: string, url: string, luar: bool,
     *                             kelompok: list<string>, bagian: array<string, string>, status: ?string, kata: string}>
     */
    public static function semua(array $setting, bool $cekStatus = true): array
    {
        $jadwal  = (int) ($setting['jadwal_publik'] ?? 1) === 1;
        $absensi = (int) ($setting['absensi_publik'] ?? 1) === 1;
        $dokumen = (int) ($setting['dokumen_publik'] ?? 0) === 1;
        $pkl     = $cekStatus ? self::statusPkl() : ['pkl' => null, 'lapor' => null];
        $status  = static fn (?bool $buka): ?string => $buka === null ? null : ($buka ? self::BUKA : self::TUTUP);
        $cfgPkl  = config('Pkl');
        $aplikasi = trim((string) ($setting['tautan_aplikasi'] ?? ''));

        $daftar = [
            'pkl' => [
                'judul'    => 'Ajukan tempat PKL',
                'ket'      => 'Siswa mengajukan tempat Praktik Kerja Lapangan (PKL/Prakerin) lewat HP, tanpa login.',
                'ikon'     => 'briefcase',
                'ilu'      => 'pkl',
                'url'      => $cfgPkl->tautan(),
                'kelompok' => ['siswa'],
                'bagian'   => ['siswa' => 'PKL / Prakerin'],
                'status'   => $status($pkl['pkl']),
                'kata'     => 'pkl prakerin praktik kerja lapangan magang perusahaan industri ajukan pengajuan',
            ],
            'lapor_pkl' => [
                'judul'    => 'Lapor diterima PKL',
                'ket'      => 'Sudah diterima perusahaan? Laporkan tanggal PKL agar sekolah membuatkan Surat Balasan.',
                'ikon'     => 'clipboard-check',
                'ilu'      => 'lapor-pkl',
                'url'      => $cfgPkl->tautanLapor(),
                'kelompok' => ['siswa'],
                'bagian'   => ['siswa' => 'PKL / Prakerin'],
                'status'   => $status($pkl['lapor']),
                'kata'     => 'lapor diterima pkl prakerin surat balasan perusahaan tanggal',
            ],
            'biodata' => [
                'judul'    => 'Isi biodata siswa',
                'ket'      => 'Lengkapi data diri siswa untuk administrasi sekolah. Dibuka sesuai pengumuman sekolah.',
                'ikon'     => 'profile-card',
                'ilu'      => 'biodata',
                'url'      => self::tautanBiodata(),
                'kelompok' => ['siswa'],
                'bagian'   => ['siswa' => 'Data diri'],
                'status'   => $cekStatus ? $status(BiodataIsianModel::formTerbuka($setting)) : null,
                'kata'     => 'biodata data diri siswa formulir isi data nisn alamat orang tua',
            ],
            'jadwal_kelas' => [
                'judul'    => 'Jadwal kelas',
                'ket'      => 'Jadwal pelajaran mingguan setiap kelas, bisa diunduh sebagai PDF.',
                'ikon'     => 'calendar-week',
                'ilu'      => 'jadwal-kelas',
                'url'      => site_url('jadwal-kelas'),
                'kelompok' => ['siswa', 'orang-tua'],
                'bagian'   => ['siswa' => 'Jadwal & informasi', 'orang-tua' => 'Jadwal & informasi'],
                'tampil'   => $jadwal,
                'kata'     => 'jadwal pelajaran kelas mingguan roster mapel',
            ],
            'jadwal_guru' => [
                'judul'    => 'Jadwal mengajar',
                'ket'      => 'Jadwal mengajar setiap guru per hari, bisa diunduh sebagai PDF.',
                'ikon'     => 'calendar-month',
                'ilu'      => 'jadwal-guru',
                'url'      => site_url('jadwal-guru'),
                'kelompok' => ['guru'],
                'bagian'   => ['guru' => 'Jadwal & kehadiran'],
                'tampil'   => $jadwal,
                'kata'     => 'jadwal guru mengajar jam pelajaran',
            ],
            'absensi' => [
                'judul'    => 'Kehadiran guru',
                'ket'      => 'Status kehadiran mengajar guru pada tanggal yang dipilih, setelah dicatat sekolah.',
                'ikon'     => 'clipboard-list',
                'ilu'      => 'absensi',
                'url'      => site_url('absensi'),
                'kelompok' => ['guru'],
                'bagian'   => ['guru' => 'Jadwal & kehadiran'],
                'tampil'   => $absensi,
                'kata'     => 'absensi kehadiran guru hadir izin sakit telat',
            ],
            'kesediaan' => [
                'judul'    => 'Formulir kesediaan mengajar',
                'ket'      => 'Guru mengisi mata pelajaran, tugas tambahan, dan hari mengajar untuk penyusunan jadwal.',
                'ikon'     => 'file-pen',
                'ilu'      => 'kesediaan',
                'url'      => site_url('isi'),
                'kelompok' => ['guru'],
                'bagian'   => ['guru' => 'Formulir guru'],
                'status'   => $cekStatus ? $status(! empty($setting['form_open'])) : null,
                'kata'     => 'kesediaan mengajar formulir guru mata pelajaran tugas tambahan',
            ],
            'pengumuman' => [
                'judul'    => 'Pengumuman',
                'ket'      => 'Informasi, berita, dan agenda terbaru dari sekolah.',
                'ikon'     => 'bullhorn',
                'ilu'      => 'pengumuman',
                'url'      => site_url('pengumuman'),
                'kelompok' => ['siswa', 'guru', 'orang-tua'],
                'bagian'   => ['siswa' => 'Jadwal & informasi', 'guru' => 'Informasi', 'orang-tua' => 'Jadwal & informasi'],
                'kata'     => 'pengumuman berita informasi agenda kegiatan prestasi',
            ],
            'dokumen' => [
                'judul'    => 'Dokumen sekolah',
                'ket'      => 'Dokumen resmi yang dibagikan sekolah untuk umum.',
                'ikon'     => 'folder-open',
                'ilu'      => 'dokumen',
                'url'      => site_url('dokumen-publik'),
                'kelompok' => ['siswa', 'orang-tua'],
                'bagian'   => ['siswa' => 'Jadwal & informasi', 'orang-tua' => 'Jadwal & informasi'],
                'tampil'   => $dokumen,
                'kata'     => 'dokumen unduh berkas surat edaran file',
            ],
            'masuk' => [
                'judul'    => 'Masuk panel guru & staf',
                'ket'      => 'Panel kerja guru, operator, dan staf sekolah. Akun dibuat oleh Admin sekolah.',
                'ikon'     => 'arrow-right-to-bracket',
                'ilu'      => 'masuk',
                'url'      => site_url('admin/login'),
                'kelompok' => ['guru'],
                'bagian'   => ['guru' => 'Panel sekolah'],
                'kata'     => 'masuk login admin panel akun staf operator kata sandi',
            ],
            'aplikasi' => [
                'judul'    => 'Aplikasi Android',
                'ket'      => 'Unduh aplikasi sekolah untuk guru dan staf di HP Android.',
                'ikon'     => 'mobile-phone',
                'ilu'      => 'aplikasi',
                'url'      => $aplikasi,
                'luar'     => true,
                'kelompok' => ['guru'],
                'bagian'   => ['guru' => 'Panel sekolah'],
                'tampil'   => $aplikasi !== '',
                'kata'     => 'aplikasi android hp unduh download play store',
            ],
            'profil' => [
                'judul'    => 'Profil sekolah',
                'ket'      => 'Identitas, foto kegiatan, dan data sekolah dalam angka.',
                'ikon'     => 'school',
                'ilu'      => 'sekolah',
                'url'      => site_url('profil'),
                'kelompok' => ['orang-tua'],
                'bagian'   => ['orang-tua' => 'Tentang sekolah'],
                'kata'     => 'profil sekolah alamat kepala sekolah akreditasi foto galeri identitas',
            ],
            'bantuan' => [
                'judul'    => 'Bantuan & kontak',
                'ket'      => 'Pertanyaan yang sering ditanyakan dan cara menghubungi sekolah.',
                'ikon'     => 'question-circle',
                'ilu'      => 'bantuan',
                'url'      => site_url('bantuan'),
                'kelompok' => ['orang-tua'],
                'bagian'   => ['orang-tua' => 'Tentang sekolah'],
                'kata'     => 'bantuan kontak telepon whatsapp email pertanyaan faq tanya',
            ],
        ];

        $hasil = [];
        foreach ($daftar as $kunci => $l) {
            if (($l['tampil'] ?? true) !== true) {
                continue;
            }
            unset($l['tampil']);
            $hasil[$kunci] = $l + ['kunci' => $kunci, 'luar' => false, 'status' => null];
        }

        return $hasil;
    }

    /**
     * Layanan satu kelompok pengguna, dikelompokkan per bagian (urutan bagian = urutan kemunculan di katalog).
     *
     * @return array<string, list<array>> bagian => layanan
     */
    public static function untukKelompok(string $kelompok, array $setting): array
    {
        $bagian = [];
        foreach (self::semua($setting) as $l) {
            if (in_array($kelompok, $l['kelompok'], true)) {
                $bagian[$l['bagian'][$kelompok] ?? 'Layanan'][] = $l;
            }
        }

        return $bagian;
    }

    /**
     * Kartu "Layanan unggulan" beranda: layanan utama yang tampil, yang sedang dibuka/selalu ada didahulukan.
     *
     * @return list<array>
     */
    public static function unggulan(array $setting, int $maks = 6): array
    {
        $semua  = self::semua($setting);
        $pilih  = [];
        foreach (['pkl', 'lapor_pkl', 'biodata', 'jadwal_kelas', 'jadwal_guru', 'absensi', 'kesediaan', 'dokumen', 'pengumuman'] as $k) {
            if (isset($semua[$k])) {
                $pilih[] = $semua[$k];
            }
        }
        // Urutan stabil: yang tertutup turun ke belakang, sisanya tetap urut katalog.
        $buka  = array_values(array_filter($pilih, static fn (array $l): bool => $l['status'] !== self::TUTUP));
        $tutup = array_values(array_filter($pilih, static fn (array $l): bool => $l['status'] === self::TUTUP));

        return array_slice(array_merge($buka, $tutup), 0, $maks);
    }

    /**
     * Halaman portal (untuk saran cari & navigasi).
     *
     * @return list<array{judul: string, ket: string, ikon: string, url: string, kata: string}>
     */
    public static function halaman(): array
    {
        $daftar = [['judul' => 'Beranda', 'ket' => 'Halaman utama', 'ikon' => 'home', 'url' => site_url('/'), 'kata' => 'beranda utama home']];
        foreach (self::kelompok() as $k => $g) {
            $daftar[] = ['judul' => $g['label'], 'ket' => $g['ringkas'], 'ikon' => $g['ikon'], 'ilu' => $g['ilu'], 'url' => $g['url'], 'kata' => str_replace('-', ' ', $k) . ' ' . strtolower($g['ringkas'])];
        }

        return $daftar;
    }

    /**
     * Data saran kotak cari (dikirim sebagai JSON di atribut data-saran; tanpa kueri basis data).
     *
     * @return list<array{judul: string, ket: string, ikon: string, url: string, kata: string}>
     */
    public static function saran(array $setting): array
    {
        $daftar = self::halaman();
        foreach (self::semua($setting, false) as $l) {
            $daftar[] = ['judul' => $l['judul'], 'ket' => $l['ket'], 'ikon' => $l['ikon'], 'url' => $l['url'], 'kata' => $l['kata']];
        }

        return $daftar;
    }

    /**
     * Layanan & halaman yang cocok dengan kata kunci (semua kata harus muncul di judul/keterangan/kata kunci).
     *
     * @return list<array>
     */
    public static function cari(string $q, array $setting): array
    {
        $kata = array_values(array_filter(explode(' ', self::normal($q))));
        if ($kata === []) {
            return [];
        }
        $hasil = [];
        foreach (array_merge(self::semua($setting), self::halaman()) as $l) {
            $teks = self::normal($l['judul'] . ' ' . $l['ket'] . ' ' . $l['kata']);
            $cocok = true;
            foreach ($kata as $k) {
                if (! str_contains($teks, $k)) {
                    $cocok = false;
                    break;
                }
            }
            if ($cocok && ! isset($hasil[$l['url']])) {
                $hasil[$l['url']] = $l;
            }
        }

        return array_values($hasil);
    }

    /**
     * Paragraf "Tentang sekolah" (beranda web & API aplikasi): profil singkat dari Pengaturan Sekolah; bila kosong, kalimat faktual
     * dari data sekolah (jenjang, kota, tahun pelajaran, jumlah siswa/guru/kelas, akreditasi).
     *
     * @param array{siswa?: int, guru?: int, kelas?: int} $stats
     */
    public static function tentang(array $setting, array $stats): string
    {
        $tentang = trim((string) ($setting['profil_singkat'] ?? ''));
        if ($tentang !== '') {
            return $tentang;
        }
        $sekolah = (string) ($setting['school_name'] ?? 'Sekolah');
        $tahun   = trim((string) ($setting['academic_year'] ?? ''));
        $jenjang = trim((string) ($setting['school_level'] ?? ''));
        $kota    = trim((string) ($setting['city'] ?? ''));
        $angka   = static fn ($n): string => number_format((int) $n, 0, ',', '.');

        return $sekolah . ($jenjang !== '' || $kota !== '' ? ' adalah ' . ($jenjang !== '' ? $jenjang : 'sekolah') . ($kota !== '' ? ' di ' . $kota : '') : '') . '.'
            . ($tahun !== '' ? ' Pada tahun pelajaran ' . $tahun . ' sekolah ini' : ' Sekolah ini')
            . ' memiliki ' . $angka($stats['siswa'] ?? 0) . ' siswa aktif, ' . $angka($stats['guru'] ?? 0) . ' guru, dan ' . $angka($stats['kelas'] ?? 0) . ' kelas.'
            . (! empty($setting['school_accreditation']) ? ' Terakreditasi ' . $setting['school_accreditation'] . '.' : '');
    }

    /** URL ilustrasi HD berwarna (public/assets/img/ilustrasi/{nama}.svg) — ubin, kartu layanan, laci daftar layanan, banner. */
    public static function ilustrasi(string $nama): string
    {
        return base_url('assets/img/ilustrasi/' . $nama . '.svg');
    }

    /** Tanggal gaya acuan: "13 Juli 2026 | 15.59 WIB" (tanpa jam: "13 Juli 2026"). */
    public static function tanggal(string $waktu, bool $denganJam = true): string
    {
        $ts = strtotime($waktu);
        if ($ts === false) {
            return '';
        }
        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $teks  = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);

        return $denganJam ? $teks . ' | ' . date('H.i', $ts) . ' WIB' : $teks;
    }

    /** URL gambar unggahan di public/uploads/{folder}/ bila berkasnya ada (mis. gambar pengumuman, foto sekolah). */
    public static function gambar(string $folder, ?string $berkas): ?string
    {
        $berkas = basename(trim((string) $berkas));

        return $berkas !== '' && is_file(FCPATH . 'uploads/' . $folder . '/' . $berkas) ? base_url('uploads/' . $folder . '/' . rawurlencode($berkas)) : null;
    }

    /** Huruf kecil, tanpa tanda baca, spasi tunggal — pembanding kata kunci. */
    public static function normal(string $s): string
    {
        $s = mb_strtolower($s);
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Alamat form biodata: subdomain di hosting, /biodata di laptop (pola sama dengan Config\Pkl::tautan()). */
    public static function tautanBiodata(): string
    {
        $host  = (string) (parse_url((string) config('App')->baseURL)['host'] ?? 'localhost');
        $lokal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false;

        return $lokal ? site_url('biodata') : 'https://' . config('Biodata')->host . '/';
    }

    /**
     * Tanya-jawab (halaman Bantuan & beranda). Isi mengikuti alur yang sebenarnya di formulir; pertanyaan tentang layanan yang
     * dimatikan tidak ditampilkan.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function tanyaJawab(array $setting): array
    {
        $daftar = [
            ['Bagaimana cara mengajukan tempat PKL?',
                'Buka "Ajukan tempat PKL" dari HP, tanpa login. Pilih kelas dan namamu, isi data tempat PKL, lalu kirim. Setelah dikirim, ajuan terkunci dan diperiksa Waka Hubin. Untuk membuka ajuanmu lagi, gunakan nomor HP yang kamu isi di formulir.'],
            ['Saya sudah diterima perusahaan. Apa langkah berikutnya?',
                'Isi "Lapor diterima PKL": masukkan tanggal mulai dan selesai PKL yang disepakati dengan perusahaan. Cukup satu orang per kelompok. Sekolah lalu membuatkan Surat Balasan untuk perusahaan. Lapor baru bisa dilakukan setelah ajuan PKL disetujui sekolah.'],
            ['Kenapa formulir biodata tidak bisa dibuka?',
                'Formulir biodata hanya dibuka pada waktu tertentu sesuai pengumuman sekolah. Bila tertulis sedang ditutup, tunggu informasi dari wali kelas atau pengumuman sekolah.'],
        ];
        if ((int) ($setting['jadwal_publik'] ?? 1) === 1) {
            $daftar[] = ['Di mana melihat jadwal pelajaran?',
                'Buka "Jadwal kelas" lalu pilih kelas. Jadwal mingguan tampil dan bisa diunduh sebagai PDF. Guru dapat melihat jadwalnya di "Jadwal mengajar".'];
        }
        $daftar[] = ['Bagaimana guru dan staf masuk ke sistem?',
            'Tekan tombol Masuk di kanan atas, lalu gunakan akun dari Admin sekolah. Lupa kata sandi? Hubungi Admin sekolah untuk dibuatkan sandi sementara.'];
        $daftar[] = ['Apakah data pribadi siswa ditampilkan di situs ini?',
            'Tidak. Halaman umum hanya menampilkan jumlah siswa dalam angka, tanpa nama atau data pribadi siswa.'];

        return $daftar;
    }

    /**
     * Status formulir PKL & Lapor diterima (Pengaturan PKL). Gagal membaca (tabel belum ada) = dianggap tertutup.
     *
     * @return array{pkl: bool, lapor: bool}
     */
    private static function statusPkl(): array
    {
        try {
            $p = (new PklPengaturanModel())->ambil();

            return ['pkl' => PklPengaturanModel::formTerbuka($p), 'lapor' => PklLapor::terbuka($p)];
        } catch (\Throwable) {
            return ['pkl' => false, 'lapor' => false];
        }
    }
}
