<?php

namespace App\Libraries;

use App\Models\SiswaModel;

/**
 * Aturan isian biodata siswa — SATU sumber untuk form publik (web), kelak
 * API Android, halaman verifikasi admin, dan laporan.
 *
 * proses() merapikan lalu memeriksa kiriman form:
 *   - spasi ganda dibuang, nama yang diketik HURUF KECIL/BESAR SEMUA dijadikan
 *     "Huruf Awal Besar" (sama dengan gaya data siswa yang sudah ada),
 *   - nomor telepon diseragamkan ke awalan 0 (+62 / 62 → 0),
 *   - RT/RW jadi angka polos ("018" → "18"),
 *   - kolom kosong disimpan NULL.
 * Pesan galat ditulis dalam bahasa sehari-hari karena yang membaca siswa.
 */
final class BiodataForm
{
    /** Label tiap kolom, urut sesuai buku induk. */
    public const LABEL = [
        'nama'             => 'Nama Lengkap',
        'nisn'             => 'NISN',
        'nis'              => 'NIS',
        'tempat_lahir'     => 'Tempat Lahir',
        'tanggal_lahir'    => 'Tanggal Lahir',
        'jenis_kelamin'    => 'Jenis Kelamin',
        'agama'            => 'Agama',
        'status_keluarga'  => 'Status dalam Keluarga',
        'anak_ke'          => 'Anak Ke',
        'alamat'           => 'Alamat Siswa',
        'rt'               => 'RT',
        'rw'               => 'RW',
        'kelurahan'        => 'Kelurahan/Desa',
        'kecamatan'        => 'Kecamatan',
        'kota'             => 'Kota/Kabupaten',
        'no_hp'            => 'No. Telepon/HP Siswa',
        'sekolah_asal'     => 'Sekolah Asal',
        'diterima_kelas'   => 'Diterima di Kelas',
        'diterima_tanggal' => 'Diterima pada Tanggal',
        'nama_ayah'        => 'Nama Ayah',
        'nama_ibu'         => 'Nama Ibu',
        'pekerjaan_ayah'   => 'Pekerjaan Ayah',
        'pekerjaan_ibu'    => 'Pekerjaan Ibu',
        'ortu_alamat'      => 'Alamat Orang Tua',
        'ortu_rt'          => 'RT Orang Tua',
        'ortu_rw'          => 'RW Orang Tua',
        'ortu_kelurahan'   => 'Kelurahan/Desa Orang Tua',
        'ortu_kecamatan'   => 'Kecamatan Orang Tua',
        'ortu_kota'        => 'Kota/Kabupaten Orang Tua',
        'ortu_telepon'     => 'No. Telepon Orang Tua',
        'nama_wali'        => 'Nama Wali',
        'alamat_wali'      => 'Alamat Wali',
        'no_hp_wali'       => 'No. Telepon Wali',
        'pekerjaan_wali'   => 'Pekerjaan Wali',
    ];

    /** Pengelompokan kolom per bagian (ringkasan form & halaman verifikasi). */
    public const BAGIAN = [
        'Data Diri' => [
            'nama', 'nisn', 'nis', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'agama', 'status_keluarga', 'anak_ke',
        ],
        'Alamat & Sekolah Asal' => [
            'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'no_hp',
            'sekolah_asal', 'diterima_kelas', 'diterima_tanggal',
        ],
        'Orang Tua' => [
            'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu',
            'ortu_alamat', 'ortu_rt', 'ortu_rw', 'ortu_kelurahan', 'ortu_kecamatan', 'ortu_kota', 'ortu_telepon',
        ],
        'Wali' => ['nama_wali', 'alamat_wali', 'no_hp_wali', 'pekerjaan_wali'],
    ];

    /**
     * Kolom yang WAJIB di form isian siswa — sekaligus ukuran "biodata
     * lengkap" di laporan (NIS, No HP siswa, tanggal diterima, dan data wali
     * boleh kosong di form, jadi tidak dihitung).
     */
    public const WAJIB = [
        'nama', 'nisn', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'status_keluarga', 'anak_ke',
        'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'sekolah_asal', 'diterima_kelas',
        'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu',
        'ortu_alamat', 'ortu_rt', 'ortu_rw', 'ortu_kelurahan', 'ortu_kecamatan', 'ortu_kota', 'ortu_telepon',
    ];

    /**
     * Label kolom wajib yang masih kosong pada satu baris siswa.
     *
     * @return list<string>
     */
    public static function kolomKosong(array $siswa): array
    {
        $kosong = [];
        foreach (self::WAJIB as $k) {
            if (trim((string) ($siswa[$k] ?? '')) === '') {
                $kosong[] = self::LABEL[$k];
            }
        }

        return $kosong;
    }

    /** Panjang maksimum = lebar kolom tabel siswa. */
    private const MAKS = [
        'nama' => 150, 'nis' => 30, 'tempat_lahir' => 100, 'alamat' => 255,
        'kelurahan' => 100, 'kecamatan' => 100, 'kota' => 100,
        'sekolah_asal' => 150, 'diterima_kelas' => 50,
        'nama_ayah' => 150, 'nama_ibu' => 150, 'pekerjaan_ayah' => 100, 'pekerjaan_ibu' => 100,
        'ortu_alamat' => 255, 'ortu_kelurahan' => 100, 'ortu_kecamatan' => 100, 'ortu_kota' => 100,
        'nama_wali' => 150, 'alamat_wali' => 255, 'pekerjaan_wali' => 100,
    ];

    /** Kolom alamat yang disalin bila "alamat orang tua sama dengan alamat siswa". */
    private const SALIN_ALAMAT = [
        'alamat' => 'ortu_alamat', 'rt' => 'ortu_rt', 'rw' => 'ortu_rw',
        'kelurahan' => 'ortu_kelurahan', 'kecamatan' => 'ortu_kecamatan', 'kota' => 'ortu_kota',
    ];

    /**
     * Rapikan + periksa kiriman form.
     *
     * Kunci khusus di luar kolom siswa:
     *   ortu_sama (1/0), ortu_telepon1, ortu_telepon2, punya_wali (ya/tidak), pernyataan (1).
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [data siap simpan, galat per kolom]
     */
    public static function proses(array $post): array
    {
        $in = static fn (string $k): string => self::rapikan((string) ($post[$k] ?? ''));
        $d  = [];
        $e  = [];

        // ================= Data diri =================
        $d['nama'] = self::judul($in('nama'));
        self::cekNama($d, $e, 'nama', 'Nama lengkap');

        $d['nisn'] = preg_replace('/\s+/', '', $in('nisn'));
        if ($d['nisn'] === '') {
            $e['nisn'] = 'NISN wajib diisi. Lihat di kartu pelajar atau rapor.';
        } elseif (! preg_match('/^\d{10}$/', $d['nisn'])) {
            $e['nisn'] = 'NISN harus tepat 10 angka (contoh: 0094624339).';
        }

        $d['nis'] = preg_replace('/\s+/', '', $in('nis'));
        if ($d['nis'] !== '' && ! preg_match('/^[0-9A-Za-z.\/-]{1,30}$/', $d['nis'])) {
            $e['nis'] = 'NIS hanya boleh berisi angka.';
        }

        $d['tempat_lahir'] = self::judul($in('tempat_lahir'));
        self::wajib($d, $e, 'tempat_lahir', 'Tempat lahir wajib diisi.');

        $thn                = (int) date('Y');
        $d['tanggal_lahir'] = self::tanggal($in('tanggal_lahir'), $thn - 40, $thn - 8);
        if ($d['tanggal_lahir'] === null) {
            $e['tanggal_lahir'] = $in('tanggal_lahir') === ''
                ? 'Tanggal lahir wajib diisi.'
                : 'Tanggal lahir tidak valid. Periksa tanggal, bulan, dan tahunnya.';
        }

        $d['jenis_kelamin'] = strtoupper($in('jenis_kelamin'));
        if (! in_array($d['jenis_kelamin'], ['L', 'P'], true)) {
            $e['jenis_kelamin'] = 'Pilih jenis kelamin.';
        }

        $d['agama'] = $in('agama');
        if (! in_array($d['agama'], SiswaModel::AGAMA, true)) {
            $e['agama'] = 'Pilih agama.';
        }

        $d['status_keluarga'] = $in('status_keluarga');
        if (! in_array($d['status_keluarga'], SiswaModel::STATUS_KELUARGA, true)) {
            $e['status_keluarga'] = 'Pilih status dalam keluarga.';
        }

        $anakKe       = $in('anak_ke');
        $d['anak_ke'] = ctype_digit($anakKe) ? (int) $anakKe : null;
        if ($d['anak_ke'] === null || $d['anak_ke'] < 1 || $d['anak_ke'] > 30) {
            $e['anak_ke'] = 'Isi anak ke berapa dengan angka (contoh: 1).';
        }

        // ================= Alamat & kontak siswa =================
        self::alamat($d, $e, $in, '');

        $d['no_hp'] = self::telepon($in('no_hp'));
        if ($d['no_hp'] !== '' && ! self::teleponSah($d['no_hp'])) {
            $e['no_hp'] = 'Nomor telepon tidak valid (contoh: 081234567890).';
        }

        // ================= Riwayat masuk =================
        $d['sekolah_asal'] = $in('sekolah_asal');
        if ($d['sekolah_asal'] === mb_strtolower($d['sekolah_asal'])) {
            // Nama sekolah lazim ditulis kapital: "smp negeri 6" → "SMP NEGERI 6".
            $d['sekolah_asal'] = mb_strtoupper($d['sekolah_asal']);
        }
        self::wajib($d, $e, 'sekolah_asal', 'Sekolah asal (SMP/MTs) wajib diisi.');

        $d['diterima_kelas'] = mb_strtoupper($in('diterima_kelas'));
        self::wajib($d, $e, 'diterima_kelas', 'Kelas saat pertama diterima wajib diisi (contoh: X TKJ 8).');

        $d['diterima_tanggal'] = null;
        if ($in('diterima_tanggal') !== '') {
            $d['diterima_tanggal'] = self::tanggal($in('diterima_tanggal'), 2010, $thn + 1);
            if ($d['diterima_tanggal'] === null) {
                $e['diterima_tanggal'] = 'Tanggal diterima tidak valid.';
            }
        }

        // ================= Orang tua =================
        $d['nama_ayah'] = self::judul($in('nama_ayah'));
        self::cekNama($d, $e, 'nama_ayah', 'Nama ayah');
        $d['nama_ibu'] = self::judul($in('nama_ibu'));
        self::cekNama($d, $e, 'nama_ibu', 'Nama ibu');

        $d['pekerjaan_ayah'] = self::pekerjaan($in('pekerjaan_ayah'));
        self::wajib($d, $e, 'pekerjaan_ayah', 'Pekerjaan ayah wajib diisi.');
        $d['pekerjaan_ibu'] = self::pekerjaan($in('pekerjaan_ibu'));
        self::wajib($d, $e, 'pekerjaan_ibu', 'Pekerjaan ibu wajib diisi.');

        if (($post['ortu_sama'] ?? '') === '1') {
            foreach (self::SALIN_ALAMAT as $dari => $ke) {
                $d[$ke] = $d[$dari];
            }
        } else {
            self::alamat($d, $e, $in, 'ortu_');
        }

        $tel1 = self::telepon($in('ortu_telepon1'));
        $tel2 = self::telepon($in('ortu_telepon2'));
        if ($tel1 === '') {
            $e['ortu_telepon'] = 'Nomor telepon orang tua wajib diisi.';
        } elseif (! self::teleponSah($tel1) || ($tel2 !== '' && ! self::teleponSah($tel2))) {
            $e['ortu_telepon'] = 'Nomor telepon orang tua tidak valid (contoh: 081234567890).';
        }
        $d['ortu_telepon'] = implode(' / ', array_unique(array_filter([$tel1, $tel2])));

        // ================= Wali (opsional) =================
        $punyaWali = $in('punya_wali');
        if (! in_array($punyaWali, ['ya', 'tidak'], true)) {
            $e['punya_wali'] = 'Pilih apakah kamu memiliki wali selain orang tua.';
        }
        if ($punyaWali === 'ya') {
            $d['nama_wali'] = self::judul($in('nama_wali'));
            self::cekNama($d, $e, 'nama_wali', 'Nama wali');
            $d['alamat_wali'] = $in('alamat_wali');
            $d['no_hp_wali']  = self::telepon($in('no_hp_wali'));
            if ($d['no_hp_wali'] === '') {
                $e['no_hp_wali'] = 'Nomor telepon wali wajib diisi.';
            } elseif (! self::teleponSah($d['no_hp_wali'])) {
                $e['no_hp_wali'] = 'Nomor telepon wali tidak valid (contoh: 081234567890).';
            }
            $d['pekerjaan_wali'] = self::pekerjaan($in('pekerjaan_wali'));
        } else {
            $d['nama_wali'] = $d['alamat_wali'] = $d['no_hp_wali'] = $d['pekerjaan_wali'] = '';
        }

        if (($post['pernyataan'] ?? '') !== '1') {
            $e['pernyataan'] = 'Centang pernyataan bahwa data sudah benar dan sesuai Kartu Keluarga.';
        }

        // ================= Batas panjang =================
        foreach (self::MAKS as $k => $maks) {
            if (! isset($e[$k]) && mb_strlen((string) ($d[$k] ?? '')) > $maks) {
                $e[$k] = self::LABEL[$k] . ' terlalu panjang (maksimal ' . $maks . ' huruf).';
            }
        }

        // Kosong → NULL, urut sesuai LABEL.
        $bersih = [];
        foreach (array_keys(self::LABEL) as $k) {
            $v          = $d[$k] ?? null;
            $bersih[$k] = ($v === '' || $v === null) ? null : $v;
        }

        return [$bersih, $e];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Alamat terstruktur; $p = '' (siswa) atau 'ortu_'. */
    private static function alamat(array &$d, array &$e, callable $in, string $p): void
    {
        $siapa = $p === '' ? '' : ' orang tua';

        $d[$p . 'alamat'] = $in($p . 'alamat');
        self::wajib($d, $e, $p . 'alamat', 'Alamat' . $siapa . ' wajib diisi (nama jalan/perumahan, blok, nomor).');

        foreach (['rt' => 'RT', 'rw' => 'RW'] as $k => $label) {
            $v = preg_replace('/\D/', '', $in($p . $k));
            $d[$p . $k] = $v === '' ? '' : (string) (int) $v;
            if ($v === '') {
                $e[$p . $k] = $label . $siapa . ' wajib diisi.';
            } elseif (strlen($v) > 3 || (int) $v === 0) {
                $e[$p . $k] = $label . $siapa . ' tidak valid (contoh: 18).';
            }
        }

        foreach (['kelurahan' => 'Kelurahan/desa', 'kecamatan' => 'Kecamatan', 'kota' => 'Kota/kabupaten'] as $k => $label) {
            $d[$p . $k] = self::judul($in($p . $k));
            self::wajib($d, $e, $p . $k, $label . $siapa . ' wajib diisi.');
        }
    }

    private static function wajib(array $d, array &$e, string $k, string $pesan): void
    {
        if (($d[$k] ?? '') === '' && ! isset($e[$k])) {
            $e[$k] = $pesan;
        }
    }

    /** Nama orang: wajib, hanya huruf (boleh titik, koma, petik, strip). */
    private static function cekNama(array $d, array &$e, string $k, string $label): void
    {
        if ($d[$k] === '') {
            $e[$k] = $label . ' wajib diisi.';
        } elseif (! preg_match("/^\\p{L}[\\p{L} .,'`\\-]*$/u", $d[$k])) {
            $e[$k] = $label . ' hanya boleh berisi huruf.';
        }
    }

    /** Buang spasi di tepi, spasi ganda, dan karakter kendali. */
    private static function rapikan(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    /**
     * Ketikan huruf kecil semua / BESAR semua → "Huruf Awal Besar".
     * Campuran dibiarkan apa adanya (siswa sengaja menulis begitu).
     */
    private static function judul(string $s): string
    {
        if ($s !== '' && ($s === mb_strtolower($s) || $s === mb_strtoupper($s))) {
            return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
        }

        return $s;
    }

    /**
     * Pekerjaan dari daftar baku memakai ejaan bakunya ("pns" → "PNS",
     * bukan "Pns"); isian bebas ("Lainnya") dirapikan seperti nama.
     */
    private static function pekerjaan(string $s): string
    {
        foreach (SiswaModel::PEKERJAAN as $baku) {
            if (mb_strtolower($baku) === mb_strtolower($s)) {
                return $baku;
            }
        }

        return self::judul($s);
    }

    /** Hanya angka, awalan +62/62 diseragamkan menjadi 0. */
    private static function telepon(string $s): string
    {
        $s = preg_replace('/\D/', '', $s) ?? '';
        if (str_starts_with($s, '62') && strlen($s) >= 10) {
            $s = '0' . substr($s, 2);
        }

        return $s;
    }

    private static function teleponSah(string $s): bool
    {
        return (bool) preg_match('/^0\d{7,14}$/', $s);
    }

    /** Y-m-d yang sah & tahunnya dalam rentang, selain itu null. */
    private static function tanggal(string $s, int $minTahun, int $maksTahun): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return null;
        }
        [, $y, $mo, $dd] = array_map('intval', $m);
        if (! checkdate($mo, $dd, $y) || $y < $minTahun || $y > $maksTahun) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $dd);
    }
}
