<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Pengaturan Honor Ujian — komponen & tarif, tunjangan panitia per jabatan, nama tanda tangan.
 * KHUSUS ADMIN (dijaga filter rute + diperiksa lagi di controller). Semua perubahan tercatat di Audit Log.
 *
 * Komponen honor (honor_komponen):
 *   tipe `tetap`   = nominal per orang (Tunjangan Panitia/Struktural/Wali Kelas) — diisi per penerima;
 *   tipe `satuan`  = jumlah × tarif (Pembuatan Soal, Transport, Pengawas, Koreksi, Rapot, Lembur …).
 *   `sumber` menentukan asal angka: manual (diketik), atau koreksi/rapot/soal (bisa dihitung otomatis dari data
 *   sekolah, Fase 3). Komponen bawaan sistem (bawaan=1) hanya boleh diubah nama/tarif/satuan/aktif/urutan/jenis ujian —
 *   kode & sumber terkunci, dan tidak bisa dihapus (cukup dinonaktifkan). Komponen tambahan Admin: sumber manual.
 *
 * Uang selalu bilangan bulat rupiah (tanpa pecahan) — tidak ada float di perhitungan.
 */
final class HonorPengaturan
{
    /** Batas wajar satu nominal/tarif (99.999.999) — menolak salah ketik berlebih. */
    public const MAKS_RUPIAH   = 99999999;
    public const MAKS_KOMPONEN = 20;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Pembantu murni (tanpa database)
    // =================================================================

    public static function rupiah(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /**
     * Isian rupiah → bilangan bulat. Menerima "20000", "20.000", "Rp 20.000", " 20 000 ".
     * Kosong → null. DITOLAK (null): minus, pecahan koma/titik-desimal ("12,5", "1.5"), huruf, di atas MAKS_RUPIAH.
     * Titik dianggap pemisah ribuan hanya bila setiap kelompok setelahnya tepat 3 angka ("1.500.000" sah; "1.5" tidak).
     */
    public static function angka(mixed $v): ?int
    {
        $s = trim((string) $v);
        $s = preg_replace('/^\s*rp\.?\s*/i', '', $s) ?? $s;
        $s = str_replace(' ', '', $s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        if (! ctype_digit($s) || strlen($s) > 9) {
            return null;
        }
        $n = (int) $s;

        return $n <= self::MAKS_RUPIAH ? $n : null;
    }

    /** Potongan nama yang rapi: spasi ganda dibuang, tak boleh berisi karakter kontrol. */
    public static function rapikan(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** Daftar jenis ujian ("ASAS,ASAT" atau null) → array jenis yang memakai. null = semua jenis. */
    public static function jenisBerlaku(?string $berlakuDi): array
    {
        $semua = UjianPeriodeModel::JENIS;
        if ($berlakuDi === null || trim($berlakuDi) === '') {
            return $semua;
        }
        $pilih = array_filter(array_map('trim', explode(',', $berlakuDi)));

        return array_values(array_intersect($semua, $pilih));
    }

    /** Apakah komponen (baris honor_komponen) dipakai pada jenis ujian ini? */
    public static function berlakuUntuk(array $komponen, string $jenis): bool
    {
        return in_array($jenis, self::jenisBerlaku($komponen['berlaku_di'] ?? null), true);
    }

    // =================================================================
    // Baca
    // =================================================================

    /**
     * Semua komponen (urut tampil). $aktifSaja: hanya yang aktif. $jenis: hanya yang berlaku pada jenis ujian itu.
     *
     * @return list<array<string,mixed>>
     */
    public function komponen(bool $aktifSaja = false, ?string $jenis = null): array
    {
        $b = $this->db->table('honor_komponen');
        if ($aktifSaja) {
            $b->where('aktif', 1);
        }
        $rows = $b->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        if ($jenis !== null) {
            $rows = array_values(array_filter($rows, static fn (array $k): bool => self::berlakuUntuk($k, $jenis)));
        }

        return $rows;
    }

    /**
     * Semua jabatan + nominal panitia bawaan (0 = belum diisi) + urutan di rekap. Urut level lalu nama.
     * `urutan` = angka yang diatur Admin (null = belum diatur); `urutan_bawaan` = angka yang dipakai bila belum diatur.
     *
     * @return list<array<string,mixed>>
     */
    public function panitia(): array
    {
        $adaUrutan = $this->db->tableExists('honor_jabatan_urutan');
        $q = $this->db->table('jabatan j')
            ->select('j.id, j.kode, j.nama, j.level, COALESCE(hp.nominal, 0) AS nominal, ' . ($adaUrutan ? 'hu.urutan AS urutan' : 'NULL AS urutan'))
            ->join('honor_panitia_jabatan hp', 'hp.jabatan_id = j.id', 'left');
        if ($adaUrutan) {
            $q->join('honor_jabatan_urutan hu', 'hu.jabatan_id = j.id', 'left');
        }
        $rows = $q->orderBy('j.level', 'ASC')->orderBy('j.nama', 'ASC')->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['urutan']        = $r['urutan'] === null ? null : (int) $r['urutan'];
            $r['urutan_bawaan'] = HonorDokumen::urutanBawaan($r);
        }
        unset($r);

        return $rows;
    }

    /**
     * Simpan urutan jabatan di rekap honor (angka kecil tampil di atas, 1–9999). Kosong = pakai urutan bawaan.
     * Semua isian diperiksa dulu; tak ada yang tersimpan bila ada yang salah.
     *
     * @param array<int|string,mixed> $urutan jabatan_id => isian
     */
    public function simpanUrutan(array $urutan): array
    {
        if (! $this->db->tableExists('honor_jabatan_urutan')) {
            return $this->gagal('Urutan jabatan belum bisa disimpan: migrasi database belum dijalankan di server ini.');
        }
        $jabatan = [];
        foreach ($this->panitia() as $j) {
            $jabatan[(int) $j['id']] = $j;
        }
        $baru = [];
        foreach ($urutan as $idKirim => $isi) {
            $id = (int) $idKirim;
            if (! isset($jabatan[$id])) {
                return $this->gagal('Ada jabatan yang tidak dikenal. Muat ulang halaman lalu coba lagi.');
            }
            $t = trim((string) $isi);
            if ($t === '') {
                $baru[$id] = null;
                continue;
            }
            if (! ctype_digit($t) || (int) $t < 1 || (int) $t > 9999) {
                return $this->gagal('Urutan untuk "' . $jabatan[$id]['nama'] . '" harus bilangan bulat 1–9999 (kosongkan untuk memakai urutan bawaan).');
            }
            $baru[$id] = (int) $t;
        }

        $catat = [];
        $now   = date('Y-m-d H:i:s');
        $this->db->transStart();
        foreach ($baru as $id => $n) {
            $lama = $jabatan[$id]['urutan'];
            if ($n === $lama) {
                continue;
            }
            if ($n === null) {
                $this->db->table('honor_jabatan_urutan')->where('jabatan_id', $id)->delete();
            } elseif ($lama === null) {
                $this->db->table('honor_jabatan_urutan')->insert(['jabatan_id' => $id, 'urutan' => $n, 'updated_at' => $now]);
            } else {
                $this->db->table('honor_jabatan_urutan')->where('jabatan_id', $id)->update(['urutan' => $n, 'updated_at' => $now]);
            }
            $catat[] = $jabatan[$id]['nama'] . ' ' . ($lama ?? 'bawaan') . '→' . ($n ?? 'bawaan');
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyimpan urutan jabatan. Coba lagi.');
        }
        if ($catat === []) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan urutan.', 'jumlah' => 0];
        }
        $this->audit('update', 'honor_jabatan_urutan', null, 'Urutan jabatan di rekap: ' . implode('; ', $catat));

        return ['ok' => true, 'pesan' => 'Urutan ' . count($catat) . ' jabatan disimpan.', 'jumlah' => count($catat)];
    }

    /** @return array{ketua_nama:?string, bendahara_nama:?string} */
    public function tandaTangan(): array
    {
        $r = $this->db->table('honor_pengaturan')->where('id', 1)->get()->getRowArray();

        return ['ketua_nama' => $r['ketua_nama'] ?? null, 'bendahara_nama' => $r['bendahara_nama'] ?? null];
    }

    // =================================================================
    // Tulis — mengembalikan ['ok' => bool, 'pesan' => string]
    // =================================================================

    /**
     * Simpan semua komponen sekaligus.
     *
     * @param array<int|string, array<string,mixed>> $in id => ['nama','tarif','satuan','aktif','urut','jenis'=>[…]]
     */
    public function simpanKomponen(array $in): array
    {
        $ada = [];
        foreach ($this->komponen() as $k) {
            $ada[(int) $k['id']] = $k;
        }
        if ($in === []) {
            return $this->gagal('Tidak ada data komponen yang dikirim.');
        }

        $baru     = [];
        $namaDipakai = [];
        $adaAktif = false;
        foreach ($in as $idKirim => $f) {
            $id = (int) $idKirim;
            if (! isset($ada[$id]) || ! is_array($f)) {
                return $this->gagal('Ada komponen yang tidak dikenal. Muat ulang halaman lalu coba lagi.');
            }
            $lama = $ada[$id];
            $nama = self::rapikan((string) ($f['nama'] ?? ''));
            if ($nama === '' || mb_strlen($nama) > 80) {
                return $this->gagal('Nama komponen wajib diisi (maksimal 80 huruf).');
            }
            $kunciNama = mb_strtolower($nama);
            if (isset($namaDipakai[$kunciNama])) {
                return $this->gagal('Nama komponen "' . $nama . '" dipakai dua kali. Tiap komponen harus punya nama berbeda.');
            }
            $namaDipakai[$kunciNama] = true;

            $tarif = self::angka($f['tarif'] ?? '');
            if ($tarif === null) {
                return $this->gagal('Tarif "' . $nama . '" tidak sah. Isi angka bulat rupiah 0 sampai ' . number_format(self::MAKS_RUPIAH, 0, ',', '.') . ', tanpa koma.');
            }
            $satuan = self::rapikan((string) ($f['satuan'] ?? ''));
            if (mb_strlen($satuan) > 30) {
                return $this->gagal('Satuan "' . $nama . '" terlalu panjang (maksimal 30 huruf).');
            }
            if ($lama['tipe'] === 'tetap') {
                $satuan = '';
            }
            $urut = (int) ($f['urut'] ?? 0);
            if ($urut < 1 || $urut > 9999) {
                return $this->gagal('Urutan "' . $nama . '" harus angka 1 sampai 9999.');
            }
            $judulCetak = self::rapikan((string) ($f['judul_cetak'] ?? ''));
            if (mb_strlen($judulCetak) > 80) {
                return $this->gagal('Judul cetakan "' . $nama . '" terlalu panjang (maksimal 80 huruf).');
            }
            $jenis = array_values(array_intersect(UjianPeriodeModel::JENIS, array_map('strval', (array) ($f['jenis'] ?? []))));
            if ($jenis === []) {
                return $this->gagal('"' . $nama . '": pilih minimal satu jenis ujian yang memakainya (atau matikan komponennya).');
            }
            $aktif = ! empty($f['aktif']) ? 1 : 0;
            $adaAktif = $adaAktif || $aktif === 1;

            $baru[$id] = [
                'nama'       => $nama,
                'judul_cetak' => $judulCetak !== '' ? $judulCetak : null,
                'tarif'      => $tarif,
                'satuan'     => $satuan !== '' ? $satuan : null,
                'urut'       => $urut,
                'aktif'      => $aktif,
                'berlaku_di' => count($jenis) === count(UjianPeriodeModel::JENIS) ? null : implode(',', $jenis),
            ];
        }
        // Komponen yang tidak dikirim tetap dianggap ada (aktif/tidaknya ikut keadaan lama).
        foreach ($ada as $id => $k) {
            if (! isset($baru[$id])) {
                $adaAktif = $adaAktif || (int) $k['aktif'] === 1;
                $kunciNama = mb_strtolower((string) $k['nama']);
                if (isset($namaDipakai[$kunciNama])) {
                    return $this->gagal('Nama komponen "' . $k['nama'] . '" bentrok dengan komponen lain.');
                }
            }
        }
        if (! $adaAktif) {
            return $this->gagal('Minimal satu komponen harus aktif.');
        }

        // Hanya baris yang benar-benar berubah yang ditulis.
        $ubah = [];
        $catat = [];
        foreach ($baru as $id => $n) {
            $lama = $ada[$id];
            $beda = [];
            foreach ($n as $kolom => $nilai) {
                $lawan = $lama[$kolom];
                if ($kolom === 'tarif' || $kolom === 'urut' || $kolom === 'aktif') {
                    $lawan = (int) $lawan;
                }
                if ($nilai !== $lawan) {
                    $beda[] = $kolom;
                }
            }
            if ($beda === []) {
                continue;
            }
            $ubah[$id] = $n + ['updated_at' => date('Y-m-d H:i:s')];
            $bagian = [];
            if (in_array('tarif', $beda, true)) {
                $bagian[] = 'tarif ' . number_format((int) $lama['tarif'], 0, ',', '.') . '→' . number_format($n['tarif'], 0, ',', '.');
            }
            if (in_array('aktif', $beda, true)) {
                $bagian[] = $n['aktif'] ? 'diaktifkan' : 'dimatikan';
            }
            if (array_diff($beda, ['tarif', 'aktif']) !== []) {
                $bagian[] = 'detail diubah';
            }
            $catat[] = $n['nama'] . ' (' . implode(', ', $bagian) . ')';
        }
        if ($ubah === []) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.'];
        }

        $this->db->transStart();
        foreach ($ubah as $id => $isi) {
            $this->db->table('honor_komponen')->where('id', $id)->update($isi);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyimpan komponen. Coba lagi.');
        }
        $this->audit('update', 'honor_komponen', null, 'Pengaturan honor: ' . implode('; ', $catat));

        return ['ok' => true, 'pesan' => count($ubah) . ' komponen disimpan. Honor yang sudah dibuat memakai tarif saat dibuat — tidak ikut berubah.'];
    }

    /**
     * Tambah komponen baru buatan Admin (sumber selalu manual).
     *
     * @param array{nama?:mixed,tipe?:mixed,tarif?:mixed,satuan?:mixed} $f
     */
    public function tambahKomponen(array $f): array
    {
        $nama = self::rapikan((string) ($f['nama'] ?? ''));
        if ($nama === '' || mb_strlen($nama) > 80) {
            return $this->gagal('Nama komponen wajib diisi (maksimal 80 huruf).');
        }
        $tipe = (string) ($f['tipe'] ?? 'satuan');
        if (! in_array($tipe, ['tetap', 'satuan'], true)) {
            return $this->gagal('Jenis komponen tidak dikenal.');
        }
        $tarif = self::angka($f['tarif'] ?? '0');
        if ($tarif === null) {
            return $this->gagal('Tarif tidak sah. Isi angka bulat rupiah, tanpa koma.');
        }
        $satuan = $tipe === 'satuan' ? self::rapikan((string) ($f['satuan'] ?? '')) : '';
        if (mb_strlen($satuan) > 30) {
            return $this->gagal('Satuan terlalu panjang (maksimal 30 huruf).');
        }

        $semua = $this->komponen();
        if (count($semua) >= self::MAKS_KOMPONEN) {
            return $this->gagal('Komponen sudah mencapai batas ' . self::MAKS_KOMPONEN . '. Hapus atau pakai komponen yang ada.');
        }
        $urutMaks = 0;
        foreach ($semua as $k) {
            if (mb_strtolower((string) $k['nama']) === mb_strtolower($nama)) {
                return $this->gagal('Sudah ada komponen bernama "' . $k['nama'] . '".');
            }
            $urutMaks = max($urutMaks, (int) $k['urut']);
        }

        $kode = $this->kodeBaru($nama);
        $now  = date('Y-m-d H:i:s');
        $ok   = $this->db->table('honor_komponen')->insert([
            'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'tarif' => $tarif, 'satuan' => $satuan !== '' ? $satuan : null,
            'sumber' => 'manual', 'berlaku_di' => null, 'aktif' => 1, 'urut' => min(9999, $urutMaks + 10), 'bawaan' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        if (! $ok) {
            return $this->gagal('Gagal menambah komponen. Coba lagi.');
        }
        $this->audit('create', 'honor_komponen', (int) $this->db->insertID(), 'Tambah komponen honor "' . $nama . '" (' . $tipe . ', ' . number_format($tarif, 0, ',', '.') . ')');

        return ['ok' => true, 'pesan' => 'Komponen "' . $nama . '" ditambahkan.'];
    }

    /** Hapus komponen tambahan yang belum dipakai honor mana pun. Komponen bawaan tak bisa dihapus. */
    public function hapusKomponen(int $id): array
    {
        $k = $this->db->table('honor_komponen')->where('id', $id)->get()->getRowArray();
        if ($k === null) {
            return $this->gagal('Komponen tidak ditemukan.');
        }
        if ((int) $k['bawaan'] === 1) {
            return $this->gagal('Komponen bawaan sistem tidak bisa dihapus — cukup dimatikan.');
        }
        if ($this->db->tableExists('honor_dok_komponen')
            && $this->db->table('honor_dok_komponen')->where('komponen_id', $id)->countAllResults() > 0) {
            return $this->gagal('"' . $k['nama'] . '" sudah dipakai di honor. Matikan saja supaya tidak muncul lagi.');
        }
        $this->db->table('honor_komponen')->where('id', $id)->delete();
        $this->audit('delete', 'honor_komponen', $id, 'Hapus komponen honor "' . $k['nama'] . '"');

        return ['ok' => true, 'pesan' => 'Komponen "' . $k['nama'] . '" dihapus.'];
    }

    /**
     * Simpan nominal tunjangan panitia per jabatan. Kosong/0 = jabatan itu tidak mendapat tunjangan panitia bawaan.
     *
     * @param array<int|string,mixed> $nominal jabatan_id => isian rupiah
     */
    public function simpanPanitia(array $nominal): array
    {
        $jabatan = [];
        foreach ($this->panitia() as $j) {
            $jabatan[(int) $j['id']] = $j;
        }
        $baru = [];
        foreach ($nominal as $idKirim => $isi) {
            $id = (int) $idKirim;
            if (! isset($jabatan[$id])) {
                return $this->gagal('Ada jabatan yang tidak dikenal. Muat ulang halaman lalu coba lagi.');
            }
            $n = trim((string) $isi) === '' ? 0 : self::angka($isi);
            if ($n === null) {
                return $this->gagal('Nominal untuk "' . $jabatan[$id]['nama'] . '" tidak sah. Isi angka bulat rupiah, tanpa koma.');
            }
            $baru[$id] = $n;
        }

        $catat = [];
        $now   = date('Y-m-d H:i:s');
        $this->db->transStart();
        foreach ($baru as $id => $n) {
            $lama = (int) $jabatan[$id]['nominal'];
            if ($n === $lama) {
                continue;
            }
            if ($n === 0) {
                $this->db->table('honor_panitia_jabatan')->where('jabatan_id', $id)->delete();
            } elseif ($lama === 0) {
                $this->db->table('honor_panitia_jabatan')->insert(['jabatan_id' => $id, 'nominal' => $n, 'updated_at' => $now]);
            } else {
                $this->db->table('honor_panitia_jabatan')->where('jabatan_id', $id)->update(['nominal' => $n, 'updated_at' => $now]);
            }
            $catat[] = $jabatan[$id]['nama'] . ' ' . number_format($lama, 0, ',', '.') . '→' . number_format($n, 0, ',', '.');
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyimpan tunjangan panitia. Coba lagi.');
        }
        if ($catat === []) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.'];
        }
        $this->audit('update', 'honor_panitia_jabatan', null, 'Tunjangan panitia per jabatan: ' . implode('; ', $catat));

        return ['ok' => true, 'pesan' => count($catat) . ' jabatan disimpan.'];
    }

    /** Simpan nama Ketua & Bendahara (tanda tangan rekap). Kosong = dikosongkan. */
    public function simpanTandaTangan(string $ketua, string $bendahara): array
    {
        $ketua     = IsianBantu::rapikanGelar(self::rapikan($ketua));
        $bendahara = IsianBantu::rapikanGelar(self::rapikan($bendahara));
        foreach (['Ketua panitia' => $ketua, 'Bendahara' => $bendahara] as $label => $nama) {
            if ($nama === '') {
                continue;
            }
            if (mb_strlen($nama) > 150 || ! IsianBantu::namaOrangSah($nama)) {
                return $this->gagal($label . ': nama hanya boleh huruf, titik, koma, tanda petik, dan strip (maksimal 150 huruf).');
            }
        }
        $this->db->table('honor_pengaturan')->where('id', 1)->update([
            'ketua_nama' => $ketua !== '' ? $ketua : null,
            'bendahara_nama' => $bendahara !== '' ? $bendahara : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit('update', 'honor_pengaturan', 1, 'Tanda tangan honor: Ketua "' . $ketua . '", Bendahara "' . $bendahara . '"');

        return ['ok' => true, 'pesan' => 'Nama tanda tangan disimpan.'];
    }

    // =================================================================
    // Internal
    // =================================================================

    /** Kode unik dari nama: huruf kecil, angka, garis bawah, maks 30 huruf; bentrok → _2, _3 … */
    private function kodeBaru(string $nama): string
    {
        $dasar = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nama)));
        $dasar = trim($dasar, '_');
        $dasar = $dasar !== '' ? 'k_' . $dasar : 'k_baru';
        $dasar = substr($dasar, 0, 26);
        $kode  = $dasar;
        for ($i = 2; $this->db->table('honor_komponen')->where('kode', $kode)->countAllResults() > 0 && $i < 1000; $i++) {
            $kode = $dasar . '_' . $i;
        }

        return $kode;
    }

    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'pesan' => $pesan];
    }

    private function audit(string $aksi, string $tabel, ?int $id, string $deskripsi): void
    {
        (new AuditModel())->record($aksi, $tabel, $id, $deskripsi);
    }
}
