<?php

namespace App\Libraries;

use App\Models\AuditModel;

/**
 * Hak akses modul PKL yang DIATUR ADMIN (halaman PKL → Hak Akses).
 *
 * Satu-satunya sumber jawaban "siapa boleh apa" di PKL, dibaca oleh: penjaga rute (HakAkses::boleh → AuthFilter),
 * menu samping, tampilan tombol, controller web, API Android, dan pustaka keputusan (PklKeputusan).
 *
 * Tujuh hak (kolom di tabel hak): lihat HAK. Peran yang bisa diatur = Hubin & Operator. ADMIN SELALU boleh semuanya
 * (tidak bisa dicabut, jadi sistem tak mungkin terkunci) dan hanya Admin yang boleh membuka halaman pengaturannya.
 * Peran tak dikenal / kosong tidak boleh apa pun.
 *
 * Tersimpan di pkl_pengaturan.hak_peran sebagai JSON {"hubin":["acc","ubah","ttd"],"operator":[…]}.
 * Kolom kosong / rusak / belum dimigrasi → hak BAWAAN (BAWAAN) yang sama dengan aturan sekolah sebelumnya:
 * Waka Hubin menyetujui, Operator menangani surat & biaya.
 */
final class PklHak
{
    /** Peran yang haknya bisa diatur (Admin tidak, karena selalu penuh). */
    public const PERAN_ATUR = ['hubin', 'operator'];

    /** kunci => [judul pendek, penjelasan]. Urutan = urutan tampil di halaman Hak Akses. */
    public const HAK = [
        'acc' => [
            'ACC, tolak & batalkan persetujuan',
            'Memutuskan ajuan PKL dan surat sekolah (Izin ASTS/TKA, Balasan, Penarikan): setuju (ACC), tolak/kembalikan, atau cabut persetujuan. Tercatat atas nama pelakunya dan tercetak di kaki surat.',
        ],
        'surat' => [
            'Unduh surat + catat biaya + kabari WhatsApp',
            'Mengunduh surat permohonan PKL (satuan / massal). Saat mengunduh wajib mencatat biaya yang diterima, lalu boleh mengabari siswa lewat WhatsApp dan mengoreksi catatan biaya.',
        ],
        'surat_sekolah' => [
            'Surat Sekolah: buat & unduh',
            'Membuat dan mengunduh Surat Izin ASTS, Surat Izin TKA, Pernyataan Orang Tua PKL, Surat Balasan PKL, dan Penarikan Izin PKL (menu Surat Sekolah). Surat yang wajib ACC baru bisa diunduh setelah disetujui peran yang berhak ACC.',
        ],
        'laporan' => [
            'Laporan Pembayaran',
            'Melihat dan mengunduh Excel laporan pembayaran siswa (siapa, kelas, jurusan, sudah bayar berapa).',
        ],
        'ubah' => [
            'Periksa, ubah & isi atas nama',
            'Mengembalikan ajuan untuk diperbaiki, mengubah data ajuan, dan mengisi ajuan atas nama siswa. (Ajuan yang sudah disetujui hanya boleh diubah yang berhak ACC.)',
        ],
        'ttd' => [
            'Tanda tangan digital Waka Hubin',
            'Mengunggah atau menghapus gambar tanda tangan Waka Hubin yang dipasang di surat.',
        ],
        'pengaturan' => [
            'Pengaturan PKL & impor riwayat',
            'Membuka/menutup form siswa, mengisi data surat (nama penanda tangan, format nomor, template Word), nominal biaya, dan mengimpor riwayat PKL lama.',
        ],
        'hapus' => [
            'Hapus ajuan',
            'Menghapus ajuan PKL (permanen, tercatat di Audit Log). Ajuan yang sudah disetujui tetap hanya boleh dihapus Admin.',
        ],
    ];

    /** Hak bawaan = aturan sekolah sebelum hak bisa diatur. */
    public const BAWAAN = [
        'hubin'    => ['acc', 'ubah', 'ttd'],
        'operator' => ['surat', 'surat_sekolah', 'laporan', 'ubah', 'pengaturan'],
    ];

    /** @var array<string, list<string>>|null */
    private static ?array $cache = null;

    // =================================================================
    // Pertanyaan "boleh?"
    // =================================================================

    public static function boleh(?string $peran, string $hak): bool
    {
        if (! isset(self::HAK[$hak])) {
            return false;
        }
        $peran = (string) $peran;
        if ($peran === 'admin') {
            return true;
        }
        if (! in_array($peran, self::PERAN_ATUR, true)) {
            return false;
        }

        return in_array($hak, self::peranMemegang()[$peran] ?? [], true);
    }

    /**
     * Hak yang dipegang suatu peran, sebagai peta lengkap kunci => bool (untuk tampilan & API).
     *
     * @return array<string, bool>
     */
    public static function petaUntuk(?string $peran): array
    {
        $out = [];
        foreach (array_keys(self::HAK) as $hak) {
            $out[$hak] = self::boleh($peran, $hak);
        }

        return $out;
    }

    /**
     * Hak apa yang dituntut sebuah alamat web (tanpa domain)? null = tidak dibatasi hak khusus.
     * Dipakai HakAkses::boleh() setelah peran lolos 'admin/pkl'. 'khusus_admin' = hanya Admin.
     */
    public static function hakUntukAlamat(string $alamat): ?string
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

        return match (true) {
            (bool) preg_match('~^hak-akses(/|$)~', $sisa)                       => 'khusus_admin',
            (bool) preg_match('~^(pengaturan|impor)(/|$)~', $sisa)              => 'pengaturan',
            (bool) preg_match('~^hapus(/|$)~', $sisa)                           => 'hapus',
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
     * Hak yang dituntut sebuah alamat Surat Sekolah (bagian setelah "admin/surat/"). Daftar Surat dan detail surat terbuka
     * bagi semua yang punya akses; ACC / kembalikan butuh hak 'acc'; halaman membuat, mengubah, membatalkan, dan mengunduh
     * surat (asts, tka, pernyataan-ortu, balasan, penarikan, {id}/unduh …) butuh 'surat_sekolah'.
     */
    private static function hakSurat(string $sisa): ?string
    {
        return match (true) {
            $sisa === '' || (bool) preg_match('~^\d+$~', $sisa)                       => null,
            (bool) preg_match('~^(acc-massal|\d+/(acc|batal-acc|kembalikan))$~', $sisa) => 'acc',
            default                                                                   => 'surat_sekolah',
        };
    }

    // =================================================================
    // Baca & simpan
    // =================================================================

    /**
     * Matriks lengkap untuk halaman pengaturan: peran => [hak => bool].
     *
     * @return array<string, array<string, bool>>
     */
    public static function matriks(): array
    {
        $out = [];
        foreach (self::PERAN_ATUR as $peran) {
            $out[$peran] = [];
            foreach (array_keys(self::HAK) as $hak) {
                $out[$peran][$hak] = in_array($hak, self::peranMemegang()[$peran] ?? [], true);
            }
        }

        return $out;
    }

    /** Lupakan simpanan per-permintaan (dipanggil setelah menyimpan, dan oleh uji). */
    public static function lupakan(): void
    {
        self::$cache = null;
    }

    /** @return array<string, list<string>> peran => daftar kunci hak */
    private static function peranMemegang(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $dipakai = self::BAWAAN;
        try {
            $baris = db_connect()->table('pkl_pengaturan')->select('hak_peran')->where('id', 1)->get()->getRowArray();
            $mentah = (string) ($baris['hak_peran'] ?? '');
            if ($mentah !== '') {
                $dekode = json_decode($mentah, true);
                if (is_array($dekode)) {
                    $dipakai = [];
                    foreach (self::PERAN_ATUR as $peran) {
                        $daftar = is_array($dekode[$peran] ?? null) ? $dekode[$peran] : [];
                        $dipakai[$peran] = array_values(array_intersect(array_keys(self::HAK), array_map('strval', $daftar)));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Kolom belum dimigrasi / DB bermasalah → hak bawaan (aman: sama dengan aturan sekolah).
            $dipakai = self::BAWAAN;
        }

        return self::$cache = $dipakai;
    }

    /**
     * Simpan matriks dari kiriman halaman: $kirim[peran][] = kunci hak.
     * Aturan: ACC dan Unduh-surat wajib dipegang minimal satu peran (Hubin/Operator); kalau tidak, pekerjaan
     * sekolah berhenti diam-diam atau terpaksa selalu lewat Admin.
     *
     * @param array<string, mixed> $kirim
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array{ok: bool, pesan: string, peringatan: list<string>}
     */
    public static function simpan(array $kirim, array $konteks): array
    {
        $baru = [];
        foreach (self::PERAN_ATUR as $peran) {
            $pilih = $kirim[$peran] ?? [];
            $pilih = is_array($pilih) ? array_map('strval', $pilih) : [];
            $baru[$peran] = array_values(array_intersect(array_keys(self::HAK), $pilih)); // urutan baku, nilai asing dibuang
        }

        foreach (['acc', 'surat'] as $wajib) {
            $ada = false;
            foreach ($baru as $daftar) {
                $ada = $ada || in_array($wajib, $daftar, true);
            }
            if (! $ada) {
                return ['ok' => false, 'pesan' => 'Hak "' . self::HAK[$wajib][0] . '" harus dipegang minimal satu peran (Waka Hubin atau Operator). Tanpa itu pekerjaan sekolah berhenti, atau hanya Admin yang bisa melakukannya.', 'peringatan' => []];
            }
        }

        $lama = self::matriks();
        $json = json_encode($baru, JSON_UNESCAPED_UNICODE);
        $baris = db_connect()->table('pkl_pengaturan')->where('id', 1);
        $baris->update(['hak_peran' => $json, 'updated_at' => date('Y-m-d H:i:s')]);
        self::lupakan();

        $ubahan = [];
        foreach (self::PERAN_ATUR as $peran) {
            foreach (array_keys(self::HAK) as $hak) {
                $sekarang = in_array($hak, $baru[$peran], true);
                if ($sekarang !== ($lama[$peran][$hak] ?? false)) {
                    $ubahan[] = HakAkses::label($peran) . ($sekarang ? ' +' : ' −') . ' ' . self::HAK[$hak][0];
                }
            }
        }
        $peringatan = self::peringatan($baru);
        (new AuditModel())->record('update', 'pkl_pengaturan', 1,
            mb_substr('Hak akses PKL diubah oleh ' . ($konteks['oleh'] ?? '?') . ': ' . ($ubahan === [] ? '(tanpa perubahan)' : implode('; ', $ubahan))
                . ($peringatan !== [] ? ' | PERINGATAN: ' . implode(' ', $peringatan) : ''), 0, 255));

        return ['ok' => true, 'pesan' => $ubahan === [] ? 'Tidak ada perubahan hak.' : 'Hak akses PKL disimpan (' . count($ubahan) . ' perubahan). Berlaku langsung.', 'peringatan' => $peringatan];
    }

    /**
     * Peringatan pemisahan tugas: satu peran memegang ACC sekaligus unduh surat; atau Operator boleh ACC.
     *
     * @param array<string, list<string>>|null $daftar peran => hak (null = yang tersimpan sekarang)
     *
     * @return list<string>
     */
    public static function peringatan(?array $daftar = null): array
    {
        if ($daftar === null) {
            $daftar = [];
            foreach (self::matriks() as $peran => $hak) {
                $daftar[$peran] = array_keys(array_filter($hak));
            }
        }
        $out = [];
        foreach (self::PERAN_ATUR as $peran) {
            $punya = $daftar[$peran] ?? [];
            if (in_array('acc', $punya, true) && (in_array('surat', $punya, true) || in_array('surat_sekolah', $punya, true))) {
                $out[] = HakAkses::label($peran) . ' memegang ACC sekaligus unduh surat: pemisahan tugas (siapa yang menyetujui ≠ siapa yang mencetak) hilang.';
            }
        }
        if (in_array('acc', $daftar['operator'] ?? [], true)) {
            $out[] = 'Operator Sekolah diberi wewenang ACC: surat yang di-ACC Operator tidak memakai tanda tangan digital Waka Hubin, dan kaki surat menuliskan hal itu.';
        }

        return $out;
    }
}
