<?php

namespace App\Libraries;

use App\Models\PklPengaturanModel;

/**
 * Jenis surat sekolah (grup menu SURAT SEKOLAH). Satu-satunya tempat yang tahu: nama, alamat web, apakah bernomor,
 * apakah bawaannya wajib ACC, dan berkas template Word-nya. Rancangan: docs/DESAIN-SURAT-SEKOLAH.md.
 *
 * Aturan ACC: hanya jenis BERNOMOR yang bisa wajib ACC. Admin menyalakan/mematikannya per jenis
 * (pkl_pengaturan.surat_perlu_acc, JSON {"izin_asts":1,...}); kolom kosong/rusak/jenis tak tertulis → bawaan di DATA.
 */
final class SuratJenis
{
    public const ASTS       = 'izin_asts';
    public const TKA        = 'izin_tka';
    public const PERNYATAAN = 'pernyataan_ortu';
    public const BALASAN    = 'balasan_pkl';
    public const PENARIKAN  = 'penarikan_pkl';

    /** Folder template Word (relatif APPPATH). Berkas dibuat per jenis di langkah masing-masing. */
    public const FOLDER_TEMPLATE = 'Libraries/Surat/';

    /**
     * kode => label · singkat · alamat web (menu) · bernomor · wajib ACC bawaan · berkas template · kelas lencana · ringkas.
     * Urutan = urutan tampil di menu dan filter.
     */
    public const DATA = [
        self::ASTS => [
            'label' => 'Surat Izin ASTS', 'singkat' => 'Izin ASTS', 'alamat' => 'admin/surat/asts', 'bernomor' => true, 'acc' => true,
            'template' => 'izin_asts.docx', 'varian' => ['perusahaan' => 'izin_asts_perusahaan.docx'], 'badge' => 'bg-sky-100 text-sky-800',
            'ringkas' => 'Pemberitahuan pelaksanaan ASTS dan permohonan dispensasi bagi siswa yang sedang PKL.',
        ],
        self::TKA => [
            'label' => 'Surat Izin TKA', 'singkat' => 'Izin TKA', 'alamat' => 'admin/surat/tka', 'bernomor' => true, 'acc' => true,
            'template' => 'izin_tka.docx', 'varian' => ['perusahaan' => 'izin_tka_perusahaan.docx'], 'badge' => 'bg-indigo-100 text-indigo-800',
            'ringkas' => 'Pemberitahuan pelaksanaan TKA (per gelombang) dan permohonan dispensasi bagi siswa yang sedang PKL.',
        ],
        self::PERNYATAAN => [
            'label' => 'Pernyataan Orang Tua PKL', 'singkat' => 'Pernyataan Ortu', 'alamat' => 'admin/surat/pernyataan-ortu', 'bernomor' => false, 'acc' => false,
            'template' => 'pernyataan_ortu.docx', 'badge' => 'bg-teal-100 text-teal-800',
            'ringkas' => 'Formulir pernyataan dan persetujuan orang tua/wali untuk PKL (diisi tangan, bermaterai). Tanpa nomor.',
        ],
        self::BALASAN => [
            'label' => 'Surat Balasan PKL', 'singkat' => 'Balasan PKL', 'alamat' => 'admin/surat/balasan', 'bernomor' => true, 'acc' => true,
            'template' => 'balasan_pkl.docx', 'badge' => 'bg-emerald-100 text-emerald-800',
            'ringkas' => 'Penegasan bahwa perusahaan menerima siswa PKL, lengkap dengan periode dan lokasi penempatan.',
        ],
        self::PENARIKAN => [
            'label' => 'Penarikan Izin PKL', 'singkat' => 'Penarikan PKL', 'alamat' => 'admin/surat/penarikan', 'bernomor' => true, 'acc' => true,
            'template' => 'penarikan_pkl.docx', 'badge' => 'bg-rose-100 text-rose-800',
            'ringkas' => 'Pemberhentian dan penarikan siswa dari tempat PKL, lengkap dengan tanggal dan alasan.',
        ],
    ];

    /**
     * Jenis yang halamannya SUDAH dibangun. Menu samping menampilkan sisanya redup bertanda "Segera".
     * Tiap langkah pembangunan (docs/DESAIN-SURAT-SEKOLAH.md, bagian 7) menambahkan kodenya ke sini.
     *
     * @var list<string>
     */
    public const SIAP = [self::ASTS, self::TKA];

    /** Nama kunci isian JSON (surat_sekolah.isi) → label yang ramah dibaca di halaman detail. */
    public const LABEL_ISI = [
        'kegiatan' => 'Kegiatan', 'semester' => 'Semester', 'tahun_pelajaran' => 'Tahun pelajaran', 'sesi' => 'Sesi',
        'tgl_mulai' => 'Mulai', 'tgl_selesai' => 'Selesai', 'tempat' => 'Tempat', 'mode' => 'Cakupan',
        'sapaan' => 'Ditujukan kepada', 'periode_mulai' => 'PKL mulai', 'periode_selesai' => 'PKL selesai',
        'tgl_efektif' => 'Berlaku sejak', 'alasan' => 'Alasan', 'kelas' => 'Kelas', 'isi_ortu' => 'Data orang tua diisi',
    ];

    /** Kunci isian yang untuk mesin saja (tidak ditampilkan di halaman detail). */
    public const ISI_TERSEMBUNYI = ['kunci', 'pkey'];

    /** Nilai isian berkode → teks yang ramah dibaca. */
    public const NILAI_ISI = [
        'mode' => ['umum' => 'Surat umum (satu surat, satu nomor)', 'perusahaan' => 'Per perusahaan (nama perusahaan + daftar siswa)'],
    ];

    /** @return list<string> */
    public static function kode(): array
    {
        return array_keys(self::DATA);
    }

    public static function ada(?string $kode): bool
    {
        return $kode !== null && isset(self::DATA[$kode]);
    }

    public static function label(string $kode): string
    {
        return self::DATA[$kode]['label'] ?? $kode;
    }

    public static function singkat(string $kode): string
    {
        return self::DATA[$kode]['singkat'] ?? $kode;
    }

    public static function alamat(string $kode): string
    {
        return self::DATA[$kode]['alamat'] ?? 'admin/surat';
    }

    public static function badge(string $kode): string
    {
        return self::DATA[$kode]['badge'] ?? 'bg-slate-100 text-slate-700';
    }

    /** Halaman jenis ini sudah dibangun (menu aktif)? */
    public static function siap(string $kode): bool
    {
        return in_array($kode, self::SIAP, true);
    }

    /** Apakah surat jenis ini diberi nomor surat keluar? */
    public static function bernomor(string $kode): bool
    {
        return (bool) (self::DATA[$kode]['bernomor'] ?? false);
    }

    /**
     * Penimpa path template per jenis (kode => path) — HANYA untuk uji (perintah dev:uji-surat), supaya unduhan bisa diuji
     * sebelum/sesudah template aslinya ada tanpa menyentuh folder Libraries/Surat. Kosong di pemakaian biasa.
     *
     * @var array<string, string>
     */
    public static array $templateTimpa = [];

    /**
     * Varian template untuk sebuah surat: '' = template utama, atau kunci di DATA[…]['varian']. Surat acara (ASTS/TKA)
     * per perusahaan memakai varian "perusahaan" (halaman Lampiran daftar siswa); selain itu template utama.
     *
     * @param array<string, mixed> $isi isian JSON surat yang sudah didekode
     */
    public static function varian(string $kode, array $isi): string
    {
        $v = (($isi['mode'] ?? '') === 'perusahaan') ? 'perusahaan' : '';

        return $v !== '' && isset(self::DATA[$kode]['varian'][$v]) ? $v : '';
    }

    /** Path lengkap berkas template Word jenis ini (belum tentu sudah ada — dibuat di langkah jenis itu). */
    public static function pathTemplate(string $kode, string $varian = ''): string
    {
        if (isset(self::$templateTimpa[$kode])) {
            return self::$templateTimpa[$kode];
        }
        $berkas = ($varian !== '' ? (self::DATA[$kode]['varian'][$varian] ?? null) : null) ?? self::DATA[$kode]['template'] ?? '';

        return APPPATH . self::FOLDER_TEMPLATE . $berkas;
    }

    /** Template utama DAN semua variannya sudah ada (unduhan massal bisa memuat surat dari varian mana pun). */
    public static function templateLengkap(string $kode): bool
    {
        if (! is_file(self::pathTemplate($kode))) {
            return false;
        }
        foreach (array_keys(self::DATA[$kode]['varian'] ?? []) as $v) {
            if (! is_file(self::pathTemplate($kode, (string) $v))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Wajib ACC? Jenis tanpa nomor tidak pernah. Selain itu: pengaturan Admin, atau bawaan bila tak diatur.
     *
     * @param array<string, mixed>|null $p baris pkl_pengaturan (dibaca bila null)
     */
    public static function perluAcc(string $kode, ?array $p = null): bool
    {
        if (! self::bernomor($kode)) {
            return false;
        }
        $bawaan = (bool) self::DATA[$kode]['acc'];
        $p ??= (new PklPengaturanModel())->ambil();
        $mentah = trim((string) ($p['surat_perlu_acc'] ?? ''));
        if ($mentah === '') {
            return $bawaan;
        }
        $peta = json_decode($mentah, true);
        if (! is_array($peta) || ! array_key_exists($kode, $peta)) {
            return $bawaan;
        }

        return filter_var($peta[$kode], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Peta lengkap kode => wajib ACC? (untuk halaman pengaturan).
     *
     * @param array<string, mixed>|null $p
     *
     * @return array<string, bool>
     */
    public static function petaAcc(?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $out = [];
        foreach (self::kode() as $k) {
            $out[$k] = self::perluAcc($k, $p);
        }

        return $out;
    }

    /**
     * Simpan aturan ACC per jenis (khusus Admin; pemeriksaan peran dilakukan pemanggil). $dicentang = kode jenis yang
     * WAJIB ACC. Hanya jenis bernomor yang bisa diatur; kode asing diabaikan. Berlaku untuk surat yang dibuat SESUDAH ini
     * (surat yang sudah menunggu ACC tetap menunggu). Tercatat di Audit Log.
     *
     * @param list<string>         $dicentang
     * @param array<string, mixed> $konteks   ['oleh', ...]
     *
     * @return array{ok: bool, pesan: string, ubah: list<string>}
     */
    public static function simpanAcc(array $dicentang, array $konteks): array
    {
        $dicentang = array_map('strval', $dicentang);
        $lama      = self::petaAcc();
        $baru      = [];
        foreach (self::kode() as $k) {
            if (self::bernomor($k)) {
                $baru[$k] = in_array($k, $dicentang, true) ? 1 : 0;
            }
        }
        (new PklPengaturanModel())->update(1, ['surat_perlu_acc' => json_encode($baru)]);

        $ubah = [];
        foreach ($baru as $k => $v) {
            if ((bool) $v !== ($lama[$k] ?? false)) {
                $ubah[] = self::label($k) . ($v ? ' → wajib ACC' : ' → tanpa ACC');
            }
        }
        (new \App\Models\AuditModel())->record('update', 'pkl_pengaturan', 1, mb_substr('Aturan ACC surat sekolah diubah oleh ' . ($konteks['oleh'] ?? '?') . ': ' . ($ubah === [] ? '(tanpa perubahan)' : implode('; ', $ubah)), 0, 255));

        return [
            'ok'    => true,
            'ubah'  => $ubah,
            'pesan' => $ubah === [] ? 'Tidak ada perubahan aturan ACC.' : 'Aturan ACC surat sekolah disimpan (' . count($ubah) . ' perubahan). Berlaku untuk surat yang dibuat mulai sekarang.',
        ];
    }

    /**
     * Isian JSON surat → daftar [label, nilai teks] untuk ditampilkan. Tanggal Y-m-d ditulis "6 Oktober 2026",
     * boolean "Ya/Tidak", daftar teks digabung koma; nilai kosong dan yang bukan teks dilewati.
     *
     * @param array<string, mixed> $isi
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function ringkasIsi(array $isi): array
    {
        $out = [];
        foreach ($isi as $k => $v) {
            if (in_array($k, self::ISI_TERSEMBUNYI, true)) {
                continue;
            }
            if (is_scalar($v) && isset(self::NILAI_ISI[$k][(string) $v])) {
                $v = self::NILAI_ISI[$k][(string) $v];
            }
            $label = self::LABEL_ISI[$k] ?? ucfirst(str_replace('_', ' ', (string) $k));
            if (is_bool($v)) {
                $teks = $v ? 'Ya' : 'Tidak';
            } elseif (is_array($v)) {
                $teks = implode(', ', array_filter(array_map(static fn ($x) => is_scalar($x) ? trim((string) $x) : '', $v), static fn (string $x) => $x !== ''));
            } elseif (is_scalar($v)) {
                $teks = trim((string) $v);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks) === 1) {
                    $teks = IsianBantu::tanggalIndo($teks);
                }
            } else {
                $teks = '';
            }
            if ($teks !== '') {
                $out[] = [$label, $teks];
            }
        }

        return $out;
    }
}
