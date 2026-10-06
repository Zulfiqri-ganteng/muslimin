<?php

namespace App\Libraries;

/**
 * Aturan isian pengajuan PKL — SATU sumber untuk form publik siswa (web),
 * "isi atas nama"/"ubah langsung" staf (Tahap 3), impor riwayat (Tahap 4),
 * dan kelak API Android.
 *
 * proses() merapikan lalu memeriksa kiriman form:
 *   - spasi ganda dibuang; kota yang diketik HURUF KECIL/BESAR SEMUA dijadikan
 *     "Huruf Awal Besar"; nama perusahaan yang diketik huruf kecil semua
 *     dijadikan "PT Maju Jaya" (singkatan badan usaha dikapitalkan, nama yang
 *     sudah BESAR/campuran dibiarkan karena bisa saja singkatan merek),
 *   - nomor telepon diseragamkan ke awalan 0 (+62 / 62 → 0),
 *   - tanggal dicek terhadap pagar dari sekolah (pengaturan PKL) dan lama PKL,
 *   - kolom kosong disimpan NULL.
 * Pesan galat ditulis dalam bahasa sehari-hari karena yang membaca siswa.
 */
final class PklForm
{
    /** Label kolom, urut seperti di form & surat. */
    public const LABEL = [
        'perusahaan_nama'    => 'Nama Perusahaan',
        'perusahaan_alamat'  => 'Alamat Perusahaan',
        'perusahaan_kota'    => 'Kota/Kabupaten',
        'perusahaan_telepon' => 'Telepon Perusahaan',
        'kontak_nama'        => 'Nama Pimpinan/Kontak',
        'kontak_jabatan'     => 'Jabatan Pimpinan/Kontak',
        'tanggal_mulai'      => 'Tanggal Mulai PKL',
        'tanggal_selesai'    => 'Tanggal Selesai PKL',
        'hp'                 => 'No. HP/WhatsApp',
        'tanggal_lahir'      => 'Tanggal Lahir',
    ];

    /** Panjang maksimum = lebar kolom tabel. */
    private const MAKS = [
        'perusahaan_nama' => 150, 'perusahaan_alamat' => 255, 'perusahaan_kota' => 100,
        'kontak_nama' => 150, 'kontak_jabatan' => 100,
    ];

    /** Singkatan yang selalu huruf besar saat nama perusahaan diketik huruf kecil semua. */
    private const SINGKATAN = [
        'PT', 'CV', 'UD', 'PD', 'PO', 'KUD', 'BPR', 'RS', 'RSU', 'RSUD', 'PLN', 'PDAM',
        'BRI', 'BNI', 'BCA', 'BTN', 'SMK', 'SMP', 'SMA', 'LPK', 'BLK', 'UMKM', 'BUMN', 'BUMD', 'TV', 'IT',
    ];

    /** Pesan bila nomor telepon mengandung huruf (mis. "O" yang tertukar dengan angka 0). */
    public const PESAN_HURUF_TELEPON = 'Nomor hanya boleh berisi angka. Periksa apakah ada huruf yang terselip — misalnya huruf "O" yang tertukar dengan angka 0.';

    /** Awalan badan usaha yang dibuang saat membuat nama pembanding (normPerusahaan). */
    private const BADAN_USAHA = ['pt', 'cv', 'ud', 'pd', 'tbk', 'persero', 'perum'];

    /**
     * Rapikan + periksa kiriman form.
     *
     * Kunci kiriman: perusahaan_nama/_alamat/_kota/_telepon, kontak_nama, kontak_jabatan,
     * tanggal_mulai, tanggal_selesai (Y-m-d), hp, tanggal_lahir (Y-m-d), teman[] (id siswa),
     * pernyataan ('1').
     *
     * $p    = baris pkl_pengaturan (pagar tanggal, lama PKL, maks anggota).
     * $opsi = ['pernyataan' => false] lewati centang pernyataan (staf mengisi atas nama);
     *         ['batas' => false]      lewati pagar tanggal & lama PKL (staf, dengan konfirmasi);
     *         ['kontak' => false]     HP & tanggal lahir pengaju boleh kosong (staf / data lama).
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [data siap simpan, galat per kolom]
     */
    public static function proses(array $post, array $p, array $opsi = []): array
    {
        $in = static fn (string $k): string => IsianBantu::rapikan((string) ($post[$k] ?? ''));
        $d  = [];
        $e  = [];

        // ================= Perusahaan =================
        $d['perusahaan_nama'] = self::namaPerusahaan($in('perusahaan_nama'));
        if ($d['perusahaan_nama'] === '') {
            $e['perusahaan_nama'] = 'Nama perusahaan wajib diisi.';
        } elseif (mb_strlen($d['perusahaan_nama']) < 3 || ! preg_match('/\p{L}{2}/u', $d['perusahaan_nama'])) {
            $e['perusahaan_nama'] = 'Nama perusahaan belum benar. Tulis nama lengkapnya, jangan disingkat.';
        }

        $d['perusahaan_alamat'] = $in('perusahaan_alamat');
        if ($d['perusahaan_alamat'] === '') {
            $e['perusahaan_alamat'] = 'Alamat perusahaan wajib diisi.';
        } elseif (mb_strlen($d['perusahaan_alamat']) < 8) {
            $e['perusahaan_alamat'] = 'Alamat perusahaan terlalu singkat. Tulis nama jalan, nomor, dan daerahnya.';
        }

        $d['perusahaan_kota'] = IsianBantu::judul($in('perusahaan_kota'));
        if ($d['perusahaan_kota'] === '') {
            $e['perusahaan_kota'] = 'Kota/kabupaten perusahaan wajib diisi.';
        }

        $telpMentah              = $in('perusahaan_telepon');
        $d['perusahaan_telepon'] = IsianBantu::telepon($telpMentah);
        if ($telpMentah !== '' && ! IsianBantu::teleponMurni($telpMentah)) {
            $e['perusahaan_telepon'] = self::PESAN_HURUF_TELEPON;
        } elseif ($d['perusahaan_telepon'] !== '' && ! IsianBantu::teleponSah($d['perusahaan_telepon'])) {
            $e['perusahaan_telepon'] = 'Nomor telepon perusahaan tidak valid (contoh: 02188776655). Kosongkan bila tidak tahu.';
        }

        $d['kontak_nama'] = IsianBantu::judul($in('kontak_nama'));
        if ($d['kontak_nama'] !== '' && ! IsianBantu::namaOrangSah($d['kontak_nama'])) {
            $e['kontak_nama'] = 'Nama pimpinan/kontak hanya boleh berisi huruf. Kosongkan bila tidak tahu.';
        }
        $d['kontak_jabatan'] = $in('kontak_jabatan');

        // ================= Periode PKL =================
        $thn     = (int) date('Y');
        $mulai   = IsianBantu::tanggal($in('tanggal_mulai'), $thn - 1, $thn + 2);
        $selesai = IsianBantu::tanggal($in('tanggal_selesai'), $thn - 1, $thn + 2);
        if ($mulai === null) {
            $e['tanggal_mulai'] = $in('tanggal_mulai') === ''
                ? 'Tanggal mulai PKL wajib diisi.'
                : 'Tanggal mulai tidak valid. Periksa tanggal, bulan, dan tahunnya.';
        }
        if ($selesai === null) {
            $e['tanggal_selesai'] = $in('tanggal_selesai') === ''
                ? 'Tanggal selesai PKL wajib diisi.'
                : 'Tanggal selesai tidak valid. Periksa tanggal, bulan, dan tahunnya.';
        }
        $d['tanggal_mulai']   = $mulai;
        $d['tanggal_selesai'] = $selesai;

        if ($mulai !== null && $selesai !== null && $selesai <= $mulai) {
            $e['tanggal_selesai'] = 'Tanggal selesai harus setelah tanggal mulai.';
        }
        if (($opsi['batas'] ?? true) === true) {
            self::periksaBatas($e, $p, $mulai, $selesai);
        }

        // ================= Kontak pengaju =================
        $kontakWajib = ($opsi['kontak'] ?? true) === true;
        $hpMentah    = $in('hp');
        $d['hp']     = IsianBantu::telepon($hpMentah);
        if ($hpMentah === '') {
            if ($kontakWajib) {
                $e['hp'] = 'Nomor HP/WhatsApp wajib diisi.';
            }
        } elseif (! IsianBantu::teleponMurni($hpMentah)) {
            $e['hp'] = self::PESAN_HURUF_TELEPON;
        } elseif (! IsianBantu::hpSah($d['hp'])) {
            $e['hp'] = 'Nomor HP tidak valid. Harus diawali 08 dan 10–14 angka (contoh: 081234567890).';
        }

        $d['tanggal_lahir'] = IsianBantu::tanggal($in('tanggal_lahir'), $thn - 40, $thn - 8);
        if ($d['tanggal_lahir'] === null && ($kontakWajib || $in('tanggal_lahir') !== '')) {
            $e['tanggal_lahir'] = $in('tanggal_lahir') === ''
                ? 'Tanggal lahir wajib diisi (dipakai untuk membuka ajuanmu bila perlu diperbaiki).'
                : 'Tanggal lahir tidak valid. Periksa tanggal, bulan, dan tahunnya.';
        }

        // ================= Teman satu tempat =================
        $d['teman'] = self::idTeman($post['teman'] ?? []);
        $maksTeman  = max(0, (int) ($p['maks_anggota'] ?? 5) - 1);
        if (count($d['teman']) > $maksTeman) {
            $e['teman'] = $maksTeman === 0
                ? 'Perusahaan ini hanya menerima satu siswa per ajuan. Hapus temanmu dari daftar.'
                : 'Maksimal ' . ($maksTeman + 1) . ' siswa per perusahaan (termasuk kamu). Kurangi ' . (count($d['teman']) - $maksTeman) . ' teman.';
        }

        // ================= Pernyataan =================
        if (($opsi['pernyataan'] ?? true) === true && ($post['pernyataan'] ?? '') !== '1') {
            $e['pernyataan'] = 'Centang pernyataan bahwa data sudah benar.';
        }

        // ================= Batas panjang =================
        foreach (self::MAKS as $k => $maks) {
            if (! isset($e[$k]) && mb_strlen((string) ($d[$k] ?? '')) > $maks) {
                $e[$k] = self::LABEL[$k] . ' terlalu panjang (maksimal ' . $maks . ' huruf).';
            }
        }

        // Nama pembanding untuk cari & deteksi perusahaan ganda.
        $d['perusahaan_norm'] = self::normPerusahaan($d['perusahaan_nama']);

        // Kosong → NULL (kecuali daftar teman).
        foreach ($d as $k => $v) {
            if ($k !== 'teman' && ($v === '' || $v === null)) {
                $d[$k] = null;
            }
        }

        return [$d, $e];
    }

    /**
     * Nama pembanding: huruf kecil, tanda baca dibuang, "&" menjadi "dan", dan
     * awalan badan usaha (PT/CV/UD/PD/Tbk/Persero/Perum) dibuang — sehingga
     * "PT. Telkom Indonesia, Tbk" dan "telkom indonesia" dianggap nama yang sama.
     */
    public static function normPerusahaan(string $nama): string
    {
        $s = mb_strtolower($nama);
        $s = str_replace('&', ' dan ', $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? '';

        $kata = array_values(array_filter(
            explode(' ', $s),
            static fn (string $k) => $k !== '' && ! in_array($k, self::BADAN_USAHA, true)
        ));
        $hasil = implode(' ', $kata);

        // Nama yang isinya cuma "PT"/"CV" jangan jadi kosong.
        return $hasil !== '' ? $hasil : $s;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Pagar tanggal dari sekolah + lama PKL. Tidak menimpa galat yang sudah ada. */
    private static function periksaBatas(array &$e, array $p, ?string $mulai, ?string $selesai): void
    {
        $awal  = ($p['mulai_paling_awal'] ?? null) ?: null;
        $akhir = ($p['selesai_paling_akhir'] ?? null) ?: null;

        if ($mulai !== null && $awal !== null && $mulai < $awal && ! isset($e['tanggal_mulai'])) {
            $e['tanggal_mulai'] = 'Tanggal mulai paling awal ' . IsianBantu::tanggalIndo($awal) . '.';
        }
        if ($mulai !== null && $akhir !== null && $mulai > $akhir && ! isset($e['tanggal_mulai'])) {
            $e['tanggal_mulai'] = 'Tanggal mulai tidak boleh setelah ' . IsianBantu::tanggalIndo($akhir) . '.';
        }
        if ($selesai !== null && $akhir !== null && $selesai > $akhir && ! isset($e['tanggal_selesai'])) {
            $e['tanggal_selesai'] = 'Tanggal selesai paling lambat ' . IsianBantu::tanggalIndo($akhir) . '.';
        }
        if ($selesai !== null && $awal !== null && $selesai < $awal && ! isset($e['tanggal_selesai'])) {
            $e['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum ' . IsianBantu::tanggalIndo($awal) . '.';
        }

        if ($mulai !== null && $selesai !== null && ! isset($e['tanggal_selesai'])) {
            $hari = IsianBantu::hariInklusif($mulai, $selesai);
            $min  = (int) ($p['durasi_min_hari'] ?? 0);
            $maks = (int) ($p['durasi_maks_hari'] ?? 0);
            if ($min > 0 && $hari < $min) {
                $e['tanggal_selesai'] = 'PKL minimal ' . $min . ' hari, sedangkan yang kamu isi hanya ' . $hari . ' hari. Periksa tanggal selesainya.';
            } elseif ($maks > 0 && $hari > $maks) {
                $e['tanggal_selesai'] = 'PKL maksimal ' . $maks . ' hari, sedangkan yang kamu isi ' . $hari . ' hari. Periksa tahun/bulan tanggal selesainya.';
            }
        }
    }

    /** Nama perusahaan huruf kecil semua → "PT Maju Jaya". Selain itu dibiarkan. */
    private static function namaPerusahaan(string $s): string
    {
        if ($s === '' || $s !== mb_strtolower($s)) {
            return $s;
        }

        $kata = explode(' ', mb_convert_case($s, MB_CASE_TITLE));
        foreach ($kata as &$k) {
            $inti = trim($k, '.,');
            if ($inti !== '' && in_array(mb_strtoupper($inti), self::SINGKATAN, true)) {
                $k = str_replace($inti, mb_strtoupper($inti), $k);
            }
        }
        unset($k);

        return implode(' ', $kata);
    }

    /**
     * Daftar id teman dari kiriman (array atau "1,2,3"): hanya angka > 0, tanpa duplikat.
     *
     * @param mixed $mentah
     *
     * @return list<int>
     */
    private static function idTeman($mentah): array
    {
        if (! is_array($mentah)) {
            $mentah = trim((string) $mentah) === '' ? [] : explode(',', (string) $mentah);
        }

        $ids = [];
        foreach ($mentah as $v) {
            $v = trim((string) $v);
            if (ctype_digit($v) && (int) $v > 0) {
                $ids[(int) $v] = true;
            }
        }

        return array_keys($ids);
    }
}
