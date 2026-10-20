<?php

namespace App\Libraries;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;

/**
 * TEMPLATE ALASAN PKL — kalimat siap pakai untuk kotak "Alasan" (revisi klien 2026-10-20; docs/DESAIN-PKL.md). Dikelola SENDIRI oleh
 * pemegang hak "Kelola template alasan" (PklHak 'alasan', bawaan Waka Hubin; Admin selalu boleh) di PKL → Template Alasan, atau
 * lewat API. Siapa pun yang membuka jendela keputusan boleh MEMAKAI template (klik = kalimat masuk ke kotak alasan, masih bisa
 * diubah/ditambah). Isi awal = "Bank Kalimat Alasan" sekolah (migrasi 2026-10-25-000001_PklKeluarAlasan).
 *
 * Jenis (menentukan jendela tempat template tampil — lihat DIALOG):
 *   kembalikan = ajuan dikembalikan untuk diperbaiki · tolak = ajuan ditolak sekolah · keluar = siswa dikeluarkan dari ajuan /
 *   ajuan ditolak perusahaan · lapor = laporan "diterima perusahaan" dikembalikan ke siswa.
 * SATU sumber aturan untuk web (Admin\PklAlasan + jendela di detail ajuan & kotak laporan) dan API (Api\Pkl alasan*).
 */
final class PklAlasan
{
    /** jenis => [judul kelompok, penjelasan singkat] */
    public const JENIS = [
        'kembalikan' => ['Dikembalikan untuk perbaikan', 'Tampil di jendela "Kembalikan untuk diperbaiki" dan "Batalkan persetujuan".'],
        'tolak'      => ['Ditolak / belum dapat disetujui', 'Tampil di jendela "Tolak" dan "Batalkan persetujuan", juga di "Keluarkan siswa" (alasan administrasi).'],
        'keluar'     => ['Siswa dikeluarkan / ditolak perusahaan', 'Tampil di jendela "Keluarkan siswa" dan "Ditolak perusahaan".'],
        'lapor'      => ['Laporan diterima dikembalikan', 'Tampil di kotak Lapor Diterima PKL saat laporan siswa dikembalikan.'],
    ];

    /** Jendela keputusan => jenis template yang ditampilkan (urutan = urutan kelompok tombol). */
    public const DIALOG = [
        'kembalikan'         => ['kembalikan'],
        'tolak'              => ['tolak'],
        'batal_acc'          => ['kembalikan', 'tolak'],
        'keluarkan'          => ['keluar', 'tolak'],
        'ditolak_perusahaan' => ['keluar'],
        'lapor'              => ['lapor'],
    ];

    public const MAKS_JUDUL = 80;
    public const MAKS_ISI   = 1000;
    public const MIN_ISI    = 5;
    /** Batas jumlah template per jenis (tombol yang terlalu banyak malah menyulitkan). */
    public const MAKS_PER_JENIS = 40;

    private static ?bool $tabelAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Tabel pkl_alasan sudah ada? (server yang belum `spark migrate` menyembunyikan fiturnya.) */
    public static function tabelAda(): bool
    {
        if (self::$tabelAda === null) {
            try {
                self::$tabelAda = db_connect()->tableExists('pkl_alasan');
            } catch (\Throwable $e) {
                self::$tabelAda = false;
            }
        }

        return self::$tabelAda;
    }

    public static function bolehKelola(?string $peran): bool
    {
        return self::tabelAda() && HakAkses::bolehPkl($peran, 'alasan');
    }

    /**
     * Semua template (atau satu jenis), urut per jenis lalu urutan.
     *
     * @return list<array{id: int, jenis: string, judul: string, isi: string, urut: int, diubah_oleh: ?string, updated_at: ?string}>
     */
    public function daftar(?string $jenis = null): array
    {
        if (! self::tabelAda()) {
            return [];
        }
        $b = $this->db->table('pkl_alasan');
        if ($jenis !== null) {
            $b->where('jenis', $jenis);
        }
        $urutJenis = "FIELD(jenis, '" . implode("','", array_keys(self::JENIS)) . "')";
        $rows      = $b->orderBy($urutJenis, '', false)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'jenis' => (string) $r['jenis'], 'judul' => (string) $r['judul'], 'isi' => (string) $r['isi'],
            'urut' => (int) $r['urut'], 'diubah_oleh' => $r['diubah_oleh'] ?? null, 'updated_at' => $r['updated_at'] ?? null,
        ], $rows);
    }

    /**
     * Template untuk satu jendela keputusan, berkelompok: [['jenis', 'judul', 'item' => [[id, judul, isi], …]], …] (kelompok kosong dibuang).
     *
     * @return list<array{jenis: string, judul: string, item: list<array{id: int, judul: string, isi: string}>}>
     */
    public function untukDialog(string $dialog): array
    {
        $out = [];
        foreach (self::DIALOG[$dialog] ?? [] as $jenis) {
            $item = array_map(static fn (array $r): array => ['id' => $r['id'], 'judul' => $r['judul'], 'isi' => $r['isi']], $this->daftar($jenis));
            if ($item !== []) {
                $out[] = ['jenis' => $jenis, 'judul' => self::JENIS[$jenis][0], 'item' => $item];
            }
        }

        return $out;
    }

    /**
     * Tambah ($id null) atau ubah satu template. $in: jenis, judul, isi.
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip', 'saluran']
     *
     * @return array{ok: bool, http: int, pesan: string, galat?: array<string, string>, id?: int}
     */
    public function simpan(array $in, ?int $id, array $konteks): array
    {
        if (! self::bolehKelola((string) ($konteks['peran'] ?? ''))) {
            return $this->tolakHak($konteks);
        }
        $lama = null;
        if ($id !== null) {
            $lama = $this->db->table('pkl_alasan')->where('id', $id)->get()->getRowArray();
            if ($lama === null) {
                return ['ok' => false, 'http' => 404, 'pesan' => 'Template tidak ditemukan (mungkin sudah dihapus). Muat ulang halaman.'];
            }
        }
        $jenis = trim((string) ($in['jenis'] ?? ($lama['jenis'] ?? '')));
        $judul = IsianBantu::rapikan((string) ($in['judul'] ?? ''));
        $isi   = IsianBantu::rapikan((string) ($in['isi'] ?? ''));
        $galat = [];
        if (! isset(self::JENIS[$jenis])) {
            $galat['jenis'] = 'Pilih jenis template: ' . implode(', ', array_map(static fn (array $j): string => $j[0], self::JENIS)) . '.';
        }
        if (mb_strlen($judul) < 3 || mb_strlen($judul) > self::MAKS_JUDUL) {
            $galat['judul'] = 'Judul tombol 3–' . self::MAKS_JUDUL . ' huruf (contoh: Administrasi belum lunas).';
        }
        if (mb_strlen($isi) < self::MIN_ISI || mb_strlen($isi) > self::MAKS_ISI) {
            $galat['isi'] = 'Kalimat alasan ' . self::MIN_ISI . '–' . self::MAKS_ISI . ' huruf.';
        }
        if ($galat === []) {
            $sama = $this->db->table('pkl_alasan')->where('jenis', $jenis)->where('judul', $judul);
            if ($id !== null) {
                $sama->where('id !=', $id);
            }
            if ($sama->countAllResults() > 0) {
                $galat['judul'] = 'Judul ini sudah dipakai template lain di kelompok yang sama. Pakai judul lain agar tombolnya tidak kembar.';
            }
        }
        if ($galat === [] && ($id === null || $jenis !== $lama['jenis'])
            && $this->db->table('pkl_alasan')->where('jenis', $jenis)->countAllResults() >= self::MAKS_PER_JENIS) {
            $galat['jenis'] = 'Kelompok ini sudah berisi ' . self::MAKS_PER_JENIS . ' template (batas). Hapus yang tidak dipakai dulu.';
        }
        if ($galat !== []) {
            return ['ok' => false, 'http' => 422, 'pesan' => 'Periksa lagi isian template.', 'galat' => $galat];
        }

        $now  = date('Y-m-d H:i:s');
        $oleh = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        if ($id === null) {
            $urut = (int) ($this->db->table('pkl_alasan')->selectMax('urut', 'm')->where('jenis', $jenis)->get()->getRowArray()['m'] ?? 0) + 1;
            $this->db->table('pkl_alasan')->insert(['jenis' => $jenis, 'judul' => $judul, 'isi' => $isi, 'urut' => $urut, 'diubah_oleh' => $oleh, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
            $this->audit('create', $id, 'Template alasan PKL ditambahkan (' . self::JENIS[$jenis][0] . '): ' . $judul, $konteks);

            return ['ok' => true, 'http' => 201, 'pesan' => 'Template "' . $judul . '" ditambahkan.', 'id' => $id];
        }
        $ubah = ['judul' => $judul, 'isi' => $isi, 'diubah_oleh' => $oleh, 'updated_at' => $now];
        if ($jenis !== $lama['jenis']) { // pindah kelompok → paling bawah di kelompok barunya
            $ubah['jenis'] = $jenis;
            $ubah['urut']  = (int) ($this->db->table('pkl_alasan')->selectMax('urut', 'm')->where('jenis', $jenis)->get()->getRowArray()['m'] ?? 0) + 1;
        }
        $this->db->table('pkl_alasan')->where('id', $id)->update($ubah);
        $this->audit('update', $id, 'Template alasan PKL diubah (' . self::JENIS[$jenis][0] . '): ' . $judul, $konteks);

        return ['ok' => true, 'http' => 200, 'pesan' => 'Template "' . $judul . '" disimpan.', 'id' => $id];
    }

    /**
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function hapus(int $id, array $konteks): array
    {
        if (! self::bolehKelola((string) ($konteks['peran'] ?? ''))) {
            return $this->tolakHak($konteks);
        }
        $r = $this->db->table('pkl_alasan')->where('id', $id)->get()->getRowArray();
        if ($r === null) {
            return ['ok' => false, 'http' => 404, 'pesan' => 'Template tidak ditemukan (mungkin sudah dihapus). Muat ulang halaman.'];
        }
        $this->db->table('pkl_alasan')->where('id', $id)->delete();
        $this->audit('delete', $id, 'Template alasan PKL dihapus (' . (self::JENIS[$r['jenis']][0] ?? $r['jenis']) . '): ' . $r['judul'], $konteks);

        return ['ok' => true, 'http' => 200, 'pesan' => 'Template "' . $r['judul'] . '" dihapus. Alasan yang sudah tercatat di ajuan tidak berubah.'];
    }

    /**
     * Urutkan ulang satu kelompok: $ids = SEMUA id kelompok itu dalam urutan baru.
     *
     * @param list<int|string>     $ids
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function urutkan(string $jenis, array $ids, array $konteks): array
    {
        if (! self::bolehKelola((string) ($konteks['peran'] ?? ''))) {
            return $this->tolakHak($konteks);
        }
        if (! isset(self::JENIS[$jenis])) {
            return ['ok' => false, 'http' => 422, 'pesan' => 'Jenis template tidak dikenal.'];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ada = array_map('intval', array_column($this->db->table('pkl_alasan')->select('id')->where('jenis', $jenis)->get()->getResultArray(), 'id'));
        sort($ada);
        $cek = $ids;
        sort($cek);
        if ($cek !== $ada) {
            return ['ok' => false, 'http' => 409, 'pesan' => 'Daftar template sudah berubah (mungkin diubah orang lain). Muat ulang halaman lalu atur lagi.'];
        }
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $i => $id) {
            $this->db->table('pkl_alasan')->where('id', $id)->update(['urut' => $i + 1, 'updated_at' => $now]);
        }
        $this->audit('update', null, 'Urutan template alasan PKL (' . self::JENIS[$jenis][0] . ') diubah', $konteks);

        return ['ok' => true, 'http' => 200, 'pesan' => 'Urutan disimpan.'];
    }

    /**
     * Geser satu template satu langkah ke atas ($arah -1) atau ke bawah (+1) di kelompoknya.
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function geser(int $id, int $arah, array $konteks): array
    {
        $r = $this->db->table('pkl_alasan')->select('jenis')->where('id', $id)->get()->getRowArray();
        if ($r === null) {
            return ['ok' => false, 'http' => 404, 'pesan' => 'Template tidak ditemukan. Muat ulang halaman.'];
        }
        $ids = array_column($this->daftar((string) $r['jenis']), 'id');
        $pos = array_search($id, $ids, true);
        $ke  = $pos === false ? -1 : $pos + ($arah < 0 ? -1 : 1);
        if ($ke < 0 || $ke >= count($ids)) {
            return ['ok' => true, 'http' => 200, 'pesan' => 'Urutan tidak berubah.'];
        }
        [$ids[$pos], $ids[$ke]] = [$ids[$ke], $ids[$pos]];

        return $this->urutkan((string) $r['jenis'], $ids, $konteks);
    }

    /** @return array{ok: false, http: int, pesan: string} */
    private function tolakHak(array $konteks): array
    {
        if (! self::tabelAda()) {
            return ['ok' => false, 'http' => 503, 'pesan' => 'Fitur template alasan belum aktif di server ini: jalankan "php spark migrate".'];
        }

        return ['ok' => false, 'http' => 403, 'pesan' => 'Akun ' . HakAkses::label((string) ($konteks['peran'] ?? '')) . ' tidak punya hak mengelola template alasan (sekarang: ' . PklHak::teksPemegang('alasan') . '). Admin bisa mengaturnya di Sistem → Hak Akses.'];
    }

    private function audit(string $aksi, ?int $id, string $teks, array $konteks): void
    {
        (new AuditModel())->record($aksi, 'pkl_alasan', $id, mb_substr($teks . ' — oleh ' . ($konteks['oleh'] ?? '?') . ((($konteks['saluran'] ?? 'web') === 'aplikasi') ? ' (via aplikasi)' : ''), 0, 255));
    }
}
