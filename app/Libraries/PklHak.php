<?php

namespace App\Libraries;

use App\Models\AuditModel;

/**
 * Hak rinci modul PKL dan Surat Sekolah yang DIATUR ADMIN (Sistem → Hak Akses, bagian "PKL & Surat").
 * Rancangan: docs/DESAIN-HAK-AKSES.md.
 *
 * Satu-satunya sumber jawaban "siapa boleh apa" di PKL & Surat, dibaca oleh: penjaga rute (HakPeran::boleh → AuthFilter),
 * menu samping, tampilan tombol, controller web, API Android, dan pustaka keputusan (PklKeputusan, SuratKeputusan).
 * Penyimpanan: kolom `hak` tabel `peran` (lewat HakPeran). Admin SELALU boleh semuanya (tidak bisa dicabut).
 *
 * Kunci hak:
 *   - PKL: lihat HAK (acc = ACC ajuan PKL, ubah, surat, laporan, ttd, ttd_kepsek, pengaturan, hapus, hapus_disetujui).
 *   - Surat Sekolah per jenis: surat_buat:{jenis}, surat_acc:{jenis} (hanya jenis bernomor), surat_unduh:{jenis}.
 *   - 'surat_sekolah' = kunci LAMA turunan (punya buat/unduh jenis apa pun) — tetap dikirim ke aplikasi lama.
 * Pola pertanyaan boleh(): satu kunci, 'surat_acc:*' (jenis apa pun), atau 'a|b' (salah satu).
 */
final class PklHak
{
    /** Peran yang dulu bisa diatur (format lama pkl_pengaturan.hak_peran). Peran sekarang: HakPeran::peranAtur(). */
    public const PERAN_ATUR = ['hubin', 'operator'];

    /** kunci => [judul pendek, penjelasan]. Urutan = urutan tampil di halaman Hak Akses. */
    public const HAK = [
        'acc' => [
            'ACC ajuan PKL',
            'Memutuskan ajuan PKL siswa: setuju (ACC), tolak, atau batalkan persetujuan. Tercatat atas nama pelakunya dan tercetak di kaki Surat Izin PKL. (ACC surat sekolah diatur per jenis surat di tabel berikutnya.)',
        ],
        'surat' => [
            'Unduh Surat Izin PKL + catat biaya + kabari WhatsApp',
            'Mengunduh surat permohonan PKL (satuan / massal). Saat mengunduh wajib mencatat biaya yang diterima, lalu boleh mengabari siswa lewat WhatsApp dan mengoreksi catatan biaya.',
        ],
        'laporan' => [
            'Laporan Pembayaran',
            'Melihat dan mengunduh Excel laporan pembayaran siswa (siapa, kelas, jurusan, sudah bayar berapa).',
        ],
        'ubah' => [
            'Periksa, kembalikan, ubah & isi atas nama',
            'Mengembalikan ajuan untuk diperbaiki, mengubah data ajuan, dan mengisi ajuan atas nama siswa. (Ajuan yang sudah disetujui hanya boleh diubah peran yang juga punya hak ACC ajuan PKL.)',
        ],
        'ttd' => [
            'Tanda tangan digital Waka Hubin',
            'Mengunggah atau menghapus gambar tanda tangan Waka Hubin. Gambar ini tercetak di semua surat, siapa pun yang meng-ACC.',
        ],
        'ttd_kepsek' => [
            'Tanda tangan + stempel Kepala Sekolah',
            'Mengunggah atau menghapus gambar tanda tangan + stempel Kepala Sekolah yang menggantikan tanda tangan bawaan template di semua surat. Bawaan: hanya Admin.',
        ],
        'pengaturan' => [
            'Pengaturan PKL & impor riwayat',
            'Membuka/menutup form siswa dan form lapor diterima, mengisi data surat (nama penanda tangan, format nomor, template Word), nominal biaya, dan mengimpor riwayat PKL lama.',
        ],
        'hapus' => [
            'Hapus ajuan',
            'Menghapus ajuan PKL yang belum disetujui (permanen, tercatat di Audit Log).',
        ],
        'hapus_disetujui' => [
            'Hapus ajuan yang sudah disetujui',
            'Menghapus ajuan yang SUDAH disetujui (perlu hak "Hapus ajuan" juga). Bawaan: hanya Admin.',
        ],
    ];

    /** Aksi surat sekolah per jenis: kunci => [judul, penjelasan]. */
    public const AKSI_SURAT = [
        'buat'  => ['Buat', 'Membuat, mengubah, mengajukan ulang, dan membatalkan surat jenis ini.'],
        'acc'   => ['ACC', 'Menyetujui, mengembalikan, dan membatalkan persetujuan surat jenis ini. Tercatat atas nama pelakunya di kaki surat.'],
        'unduh' => ['Unduh', 'Mengunduh (mencetak) surat jenis ini. Nomor surat terbit saat unduhan pertama.'],
    ];

    /** Hak bawaan dalam FORMAT LAMA (Waka Hubin menyetujui; Operator menangani surat & biaya). Lihat bawaanLengkap(). */
    public const BAWAAN = [
        'hubin'    => ['acc', 'ubah', 'ttd'],
        'operator' => ['surat', 'surat_sekolah', 'laporan', 'ubah', 'pengaturan'],
    ];

    /** @var array<string, list<string>>|null hak lama tersimpan di pkl_pengaturan.hak_peran (sudah diperluas) */
    private static ?array $lama = null;

    // =================================================================
    // Kunci
    // =================================================================

    public static function kunciSurat(string $aksi, string $jenis): string
    {
        return 'surat_' . $aksi . ':' . $jenis;
    }

    /** Aksi yang berlaku untuk jenis surat (ACC hanya untuk jenis bernomor). @return list<string> */
    public static function aksiUntuk(string $jenis): array
    {
        return SuratJenis::bernomor($jenis) ? ['buat', 'acc', 'unduh'] : ['buat', 'unduh'];
    }

    /** Semua kunci hak yang dikenal (urutan baku: PKL lalu surat per jenis). @return list<string> */
    public static function semuaKunci(): array
    {
        $out = array_keys(self::HAK);
        foreach (SuratJenis::kode() as $j) {
            foreach (self::aksiUntuk($j) as $a) {
                $out[] = self::kunciSurat($a, $j);
            }
        }

        return $out;
    }

    /**
     * Ubah daftar hak format lama ke format baru: 'acc' lama = ACC ajuan + ACC semua jenis surat; 'surat_sekolah' = buat & unduh
     * semua jenis surat. Kunci asing dibuang.
     *
     * @param list<string> $daftar
     *
     * @return list<string>
     */
    public static function perluasLama(array $daftar): array
    {
        $daftar = array_map('strval', $daftar);
        $out    = array_values(array_intersect(array_keys(self::HAK), $daftar));
        foreach (SuratJenis::kode() as $j) {
            if (in_array('acc', $daftar, true) && SuratJenis::bernomor($j)) {
                $out[] = self::kunciSurat('acc', $j);
            }
            if (in_array('surat_sekolah', $daftar, true)) {
                $out[] = self::kunciSurat('buat', $j);
                $out[] = self::kunciSurat('unduh', $j);
            }
        }

        return array_values(array_intersect(self::semuaKunci(), $out));
    }

    /** Hak bawaan format baru: peran => daftar kunci. @return array<string, list<string>> */
    public static function bawaanLengkap(): array
    {
        $out = [];
        foreach (self::BAWAAN as $peran => $daftar) {
            $out[$peran] = self::perluasLama($daftar);
        }

        return $out;
    }

    /**
     * Hak yang dulu tersimpan di pkl_pengaturan.hak_peran (sebelum tabel peran ada), sudah diperluas ke format baru.
     * [] bila kolom kosong/rusak/tidak ada. Peran yang tak tertulis di JSON yang sah = tanpa hak (sama dengan aturan lama).
     *
     * @return array<string, list<string>>
     */
    public static function hakLamaTersimpan(): array
    {
        if (self::$lama !== null) {
            return self::$lama;
        }
        $out = [];
        try {
            $baris  = db_connect()->table('pkl_pengaturan')->select('hak_peran')->where('id', 1)->get()->getRowArray();
            $mentah = (string) ($baris['hak_peran'] ?? '');
            $dekode = $mentah !== '' ? json_decode($mentah, true) : null;
            if (is_array($dekode)) {
                foreach (self::PERAN_ATUR as $peran) {
                    $out[$peran] = self::perluasLama(is_array($dekode[$peran] ?? null) ? $dekode[$peran] : []);
                }
            }
        } catch (\Throwable $e) {
            $out = []; // kolom belum dimigrasi / DB bermasalah → hak bawaan
        }

        return self::$lama = $out;
    }

    // =================================================================
    // Pertanyaan "boleh?"
    // =================================================================

    /** $hak: satu kunci, 'surat_acc:*' (jenis apa pun), 'surat_sekolah' (lama), atau gabungan 'a|b'. */
    public static function boleh(?string $peran, string $hak): bool
    {
        if (! HakPeran::dikenal($peran)) {
            return false;
        }
        foreach (explode('|', $hak) as $satu) {
            if (self::bolehSatu((string) $peran, trim($satu))) {
                return true;
            }
        }

        return false;
    }

    private static function bolehSatu(string $peran, string $hak): bool
    {
        if ($hak === 'surat_sekolah') {
            return self::bolehSatu($peran, 'surat_buat:*') || self::bolehSatu($peran, 'surat_unduh:*');
        }
        if (str_ends_with($hak, ':*')) {
            $awal = substr($hak, 0, -1);
            if (! in_array($awal, ['surat_buat:', 'surat_acc:', 'surat_unduh:'], true)) {
                return false;
            }
            foreach (HakPeran::hakDaftar($peran) as $k) {
                if (str_starts_with($k, $awal)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($hak, self::semuaKunci(), true) && HakPeran::punyaHak($peran, $hak);
    }

    /** Boleh melakukan $aksi (buat|acc|unduh) pada surat jenis $jenis? */
    public static function bolehSurat(?string $peran, string $aksi, string $jenis): bool
    {
        return SuratJenis::ada($jenis) && in_array($aksi, self::aksiUntuk($jenis), true) && self::boleh($peran, self::kunciSurat($aksi, $jenis));
    }

    /**
     * Nama peran (selain Admin) yang memegang hak $hak — untuk pesan seperti "menunggu ACC oleh Waka Hubin". Kosong = hanya Admin.
     *
     * @return list<string>
     */
    public static function pemegang(string $hak): array
    {
        $out = [];
        foreach (HakPeran::peranAtur() as $peran) {
            if (self::boleh($peran, $hak)) {
                $out[] = HakPeran::label($peran);
            }
        }

        return $out;
    }

    /** "Waka Hubin / Operator Sekolah", atau "Admin" bila tak ada peran lain yang memegang hak itu. */
    public static function teksPemegang(string $hak): string
    {
        $p = self::pemegang($hak);

        return $p !== [] ? implode(' / ', $p) : 'Admin';
    }

    /** Jenis surat yang boleh di-$aksi peran ini. @return list<string> */
    public static function jenisBoleh(?string $peran, string $aksi): array
    {
        return array_values(array_filter(SuratJenis::kode(), static fn (string $j): bool => self::bolehSurat($peran, $aksi, $j)));
    }

    /**
     * Hak PKL yang dipegang suatu peran sebagai peta kunci => bool (untuk tampilan & API). Ikut dikirim kunci lama turunan
     * 'surat_sekolah' supaya aplikasi Android versi lama tetap jalan.
     *
     * @return array<string, bool>
     */
    public static function petaUntuk(?string $peran): array
    {
        $out = [];
        foreach (array_keys(self::HAK) as $hak) {
            $out[$hak] = self::boleh($peran, $hak);
            if ($hak === 'surat') {
                $out['surat_sekolah'] = self::boleh($peran, 'surat_sekolah');
            }
        }

        return $out;
    }

    /**
     * Hak surat per jenis: jenis => ['buat' => bool, 'acc' => bool|null (null = jenis tanpa ACC), 'unduh' => bool].
     *
     * @return array<string, array{buat: bool, acc: bool|null, unduh: bool}>
     */
    public static function petaSurat(?string $peran): array
    {
        $out = [];
        foreach (SuratJenis::kode() as $j) {
            $out[$j] = [
                'buat'  => self::bolehSurat($peran, 'buat', $j),
                'acc'   => SuratJenis::bernomor($j) ? self::bolehSurat($peran, 'acc', $j) : null,
                'unduh' => self::bolehSurat($peran, 'unduh', $j),
            ];
        }

        return $out;
    }

    /**
     * Hak apa yang dituntut sebuah alamat web (tanpa domain)? null = tidak dibatasi hak rinci. 'khusus_admin' = hanya Admin.
     * Dipakai HakPeran::boleh() setelah peran lolos modul PKL / Surat Sekolah.
     */
    public static function hakUntukAlamat(string $alamat, string $metode = 'GET'): ?string
    {
        $a = strtolower($alamat);
        $a = preg_split('/[?#]/', $a, 2)[0];
        $a = trim((string) preg_replace('#/{2,}#', '/', $a), '/');
        if ($a === 'admin/surat' || str_starts_with($a, 'admin/surat/')) {
            return self::hakSurat(ltrim(substr($a, strlen('admin/surat')), '/'));
        }
        if ($a !== 'admin/pkl' && ! str_starts_with($a, 'admin/pkl/')) {
            return null;
        }
        $sisa = ltrim(substr($a, strlen('admin/pkl')), '/');
        if ($sisa === '') {
            return null;
        }
        $baca = HakPeran::metodeBaca($metode);

        return match (true) {
            (bool) preg_match('~^hak-akses(/|$)~', $sisa)                       => 'khusus_admin',
            (bool) preg_match('~^(pengaturan|impor)(/|$)~', $sisa)              => 'pengaturan',
            (bool) preg_match('~^hapus(/|$)~', $sisa)                           => 'hapus',
            // Halaman Tanda Tangan memuat dua bagian (Waka Hubin & Kepsek): membuka halaman / pratinjau gambar cukup salah satu
            // hak; mengunggah & menghapus menuntut hak bagiannya.
            $baca && (bool) preg_match('~^(ttd|ttd/gambar|ttd-kepsek/gambar)$~', $sisa) => 'ttd|ttd_kepsek',
            (bool) preg_match('~^ttd-kepsek(/|$)~', $sisa)                      => 'ttd_kepsek',
            (bool) preg_match('~^ttd(/|$)~', $sisa)                             => 'ttd',
            (bool) preg_match('~^laporan(/|$)~', $sisa)                         => 'laporan',
            (bool) preg_match('~^(surat|surat-massal|wa)(/|$)~', $sisa)         => 'surat',
            (bool) preg_match('~^acc-massal$~', $sisa)                          => 'acc',
            (bool) preg_match('~^(baru|siswa-kelas)$~', $sisa)                  => 'ubah',
            (bool) preg_match('~^\d+/(acc|tolak|batal-acc)$~', $sisa)           => 'acc',
            (bool) preg_match('~^\d+/(ubah|kembalikan)$~', $sisa)               => 'ubah',
            (bool) preg_match('~^\d+/(surat|pembayaran|wa)(/|$)~', $sisa)       => 'surat',
            default                                                             => null,
        };
    }

    /**
     * Hak yang dituntut alamat Surat Sekolah (bagian setelah "admin/surat/"). Daftar, detail, dan Excel daftar terbuka bagi
     * semua yang boleh membuka modul Surat; alamat yang memakai {id} menuntut hak aksi itu untuk jenis APA PUN — jenis
     * persisnya diperiksa lagi oleh SuratKeputusan / controller setelah surat dimuat. Halaman jenis menuntut hak Buat jenis itu.
     */
    private static function hakSurat(string $sisa): ?string
    {
        if ($sisa === '' || $sisa === 'excel' || preg_match('~^\d+$~', $sisa) === 1) {
            return null;
        }
        if (preg_match('~^(acc-massal|\d+/(acc|batal-acc|kembalikan))$~', $sisa) === 1) {
            return 'surat_acc:*';
        }
        if (preg_match('~^(unduh-massal|\d+/unduh)$~', $sisa) === 1) {
            return 'surat_unduh:*';
        }
        // Kabar WhatsApp "siap diambil" (SuratWa) = yang mengunduh surat; jenis persisnya diperiksa controller.
        if (preg_match('~^(wa|\d+/wa(/\d+/tandai)?)$~', $sisa) === 1) {
            return 'surat_unduh:*';
        }
        foreach (SuratJenis::kode() as $j) {
            $seg = substr(SuratJenis::alamat($j), strlen('admin/surat/'));
            if ($sisa === $seg || str_starts_with($sisa, $seg . '/')) {
                // Kotak "laporan diterima dari siswa": diproses pembuat ATAU penyetuju Surat Balasan.
                if ($j === SuratJenis::BALASAN && preg_match('~^' . preg_quote($seg, '~') . '/laporan(/|$)~', $sisa) === 1) {
                    return self::kunciSurat('buat', $j) . '|' . self::kunciSurat('acc', $j);
                }

                return self::kunciSurat('buat', $j);
            }
        }

        return 'surat_buat:*';
    }

    // =================================================================
    // Baca & simpan (halaman Hak Akses / API)
    // =================================================================

    /**
     * Matriks hak PKL untuk halaman pengaturan: peran (selain Admin) => [hak => bool].
     *
     * @return array<string, array<string, bool>>
     */
    public static function matriks(): array
    {
        $out = [];
        foreach (HakPeran::peranAtur() as $peran) {
            foreach (array_keys(self::HAK) as $hak) {
                $out[$peran][$hak] = HakPeran::punyaHak($peran, $hak);
            }
        }

        return $out;
    }

    /**
     * Matriks hak surat: peran => [jenis => [aksi => bool]].
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public static function matriksSurat(): array
    {
        $out = [];
        foreach (HakPeran::peranAtur() as $peran) {
            foreach (SuratJenis::kode() as $j) {
                foreach (self::aksiUntuk($j) as $a) {
                    $out[$peran][$j][$a] = HakPeran::punyaHak($peran, self::kunciSurat($a, $j));
                }
            }
        }

        return $out;
    }

    /** Lupakan simpanan per-permintaan (setelah menyimpan, dan oleh uji). */
    public static function lupakan(): void
    {
        self::$lama = null;
        HakPeran::lupakan();
    }

    /** Hanya simpanan hak lama (dipanggil HakPeran::lupakan, tanpa memutar balik). */
    public static function lupakanLama(): void
    {
        self::$lama = null;
    }

    /**
     * Simpan hak dari kiriman: $kirim[peran][] = kunci hak. Peran yang tidak dikirim / tanpa centang = tanpa hak.
     *
     * $formatBaru = true (halaman Hak Akses): kiriman memuat kunci PKL + kunci per jenis surat dan disimpan apa adanya.
     * Format LAMA (aplikasi Android lama: 8 kunci PKL termasuk 'surat_sekolah'): kunci PKL disimpan; hak per jenis surat
     * DIPERTAHANKAN, kecuali saklar lama 'acc' / 'surat_sekolah' berubah dibanding sekarang (maka semua jenis ikut berubah).
     * Kiriman yang memuat kunci 'surat_…:…' selalu dianggap format baru.
     *
     * @param array<string, mixed> $kirim
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array{ok: bool, pesan: string, peringatan: list<string>}
     */
    public static function simpan(array $kirim, array $konteks, bool $formatBaru = false): array
    {
        if (! HakPeran::tabelAda()) {
            return ['ok' => false, 'pesan' => 'Tabel peran belum ada di database. Jalankan "php spark migrate" lebih dulu, lalu ulangi.', 'peringatan' => []];
        }
        foreach ($kirim as $daftar) {
            foreach ((array) $daftar as $k) {
                if (is_string($k) && str_starts_with($k, 'surat_') && str_contains($k, ':')) {
                    $formatBaru = true;
                }
            }
        }

        $lama = [];
        foreach (HakPeran::peranAtur() as $peran) {
            $lama[$peran] = HakPeran::hakDaftar($peran);
        }
        $baru = [];
        foreach (HakPeran::peranAtur() as $peran) {
            $pilih = array_map('strval', array_filter((array) ($kirim[$peran] ?? []), 'is_scalar'));
            if ($formatBaru) {
                $baru[$peran] = array_values(array_intersect(self::semuaKunci(), $pilih));
                continue;
            }
            $pkl   = array_values(array_intersect(array_keys(self::HAK), $pilih));
            $surat = array_values(array_filter($lama[$peran], static fn (string $k): bool => str_starts_with($k, 'surat_') && str_contains($k, ':')));
            $accKini = in_array('acc', $pilih, true);
            if (in_array('acc', $lama[$peran], true) !== $accKini) {
                $surat = array_values(array_filter($surat, static fn (string $k): bool => ! str_starts_with($k, 'surat_acc:')));
                if ($accKini) {
                    $surat = array_merge($surat, array_values(array_filter(self::perluasLama(['acc']), static fn (string $k): bool => str_starts_with($k, 'surat_acc:'))));
                }
            }
            $ssDulu = self::adaAwalan($lama[$peran], 'surat_buat:') || self::adaAwalan($lama[$peran], 'surat_unduh:');
            $ssKini = in_array('surat_sekolah', $pilih, true);
            if ($ssDulu !== $ssKini) {
                $surat = array_values(array_filter($surat, static fn (string $k): bool => str_starts_with($k, 'surat_acc:')));
                if ($ssKini) {
                    $surat = array_merge($surat, self::perluasLama(['surat_sekolah']));
                }
            }
            $baru[$peran] = array_values(array_intersect(self::semuaKunci(), array_merge($pkl, $surat)));
        }

        if (! HakPeran::simpanHak($baru)) {
            return ['ok' => false, 'pesan' => 'Hak akses gagal disimpan. Coba lagi.', 'peringatan' => []];
        }

        $ubahan = [];
        foreach ($baru as $peran => $daftar) {
            $tambah = array_diff($daftar, $lama[$peran] ?? []);
            $kurang = array_diff($lama[$peran] ?? [], $daftar);
            foreach ($tambah as $k) {
                $ubahan[] = HakPeran::label($peran) . ' + ' . self::labelKunci($k);
            }
            foreach ($kurang as $k) {
                $ubahan[] = HakPeran::label($peran) . ' − ' . self::labelKunci($k);
            }
        }
        $peringatan = self::peringatan($baru);
        (new AuditModel())->record('update', 'peran', null,
            mb_substr('Hak PKL & Surat diubah oleh ' . ($konteks['oleh'] ?? '?') . ': ' . ($ubahan === [] ? '(tanpa perubahan)' : implode('; ', $ubahan))
                . ($peringatan !== [] ? ' | PERINGATAN: ' . implode(' ', $peringatan) : ''), 0, 255));

        return ['ok' => true, 'pesan' => $ubahan === [] ? 'Tidak ada perubahan hak.' : 'Hak PKL & Surat disimpan (' . count($ubahan) . ' perubahan). Berlaku langsung.', 'peringatan' => $peringatan];
    }

    /** Teks ramah sebuah kunci hak, mis. "ACC Surat Balasan PKL". */
    public static function labelKunci(string $k): string
    {
        if (isset(self::HAK[$k])) {
            return self::HAK[$k][0];
        }
        if (preg_match('/^surat_(buat|acc|unduh):(.+)$/', $k, $m) === 1) {
            return self::AKSI_SURAT[$m[1]][0] . ' ' . SuratJenis::label($m[2]);
        }

        return $k;
    }

    /**
     * Peringatan atas pembagian hak (tidak memblokir — keputusan Admin dihormati): peran memegang ACC sekaligus unduh (pemisahan
     * tugas hilang), atau suatu aksi penting tak dipegang peran mana pun selain Admin.
     *
     * @param array<string, list<string>>|null $daftar peran => hak (null = yang tersimpan sekarang)
     *
     * @return list<string>
     */
    public static function peringatan(?array $daftar = null): array
    {
        if ($daftar === null) {
            $daftar = [];
            foreach (HakPeran::peranAtur() as $peran) {
                $daftar[$peran] = HakPeran::hakDaftar($peran);
            }
        }
        $out = [];
        foreach ($daftar as $peran => $punya) {
            $acc   = in_array('acc', $punya, true) || self::adaAwalan($punya, 'surat_acc:');
            $unduh = in_array('surat', $punya, true) || self::adaAwalan($punya, 'surat_unduh:');
            if ($acc && $unduh) {
                $out[] = HakPeran::label($peran) . ' memegang ACC sekaligus unduh surat: pemisahan tugas (siapa yang menyetujui ≠ siapa yang mencetak) hilang. Tidak apa-apa bila memang disengaja.';
            }
        }
        $dipegang = static function (string $k) use ($daftar): bool {
            foreach ($daftar as $punya) {
                if (in_array($k, $punya, true)) {
                    return true;
                }
            }

            return false;
        };
        if (! $dipegang('acc')) {
            $out[] = 'Tidak ada peran selain Admin yang bisa ACC ajuan PKL.';
        }
        if (! $dipegang('surat')) {
            $out[] = 'Tidak ada peran selain Admin yang bisa mengunduh Surat Izin PKL.';
        }
        $p = (new \App\Models\PklPengaturanModel())->ambil();
        foreach (SuratJenis::kode() as $j) {
            $kurang = [];
            foreach (self::aksiUntuk($j) as $a) {
                if ($a === 'acc' && ! SuratJenis::perluAcc($j, $p)) {
                    continue;
                }
                if (! $dipegang(self::kunciSurat($a, $j))) {
                    $kurang[] = mb_strtolower(self::AKSI_SURAT[$a][0]);
                }
            }
            if ($kurang !== []) {
                $out[] = SuratJenis::label($j) . ': hanya Admin yang bisa ' . implode(', ', $kurang) . '.';
            }
        }

        return $out;
    }

    /** @param list<string> $daftar */
    private static function adaAwalan(array $daftar, string $awal): bool
    {
        foreach ($daftar as $k) {
            if (str_starts_with($k, $awal)) {
                return true;
            }
        }

        return false;
    }
}
