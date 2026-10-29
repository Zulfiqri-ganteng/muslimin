<?php

namespace App\Libraries;

/**
 * Status & penghapusan siswa (permintaan user 2026-10-29, sesudah kasus data ganda Riska/Rizka):
 *   - NONAKTIFKAN = ubah status ke Lulus / Pindah / Keluar (data & riwayat tetap utuh, bisa diaktifkan lagi). Siswa nonaktif tidak
 *     muncul di form siswa (Layanan Siswa, Biodata, PKL) karena form itu hanya memuat status 'aktif'.
 *   - HAPUS PERMANEN = benar-benar dihapus dari database (NIS/NISN langsung bisa dipakai lagi) — HANYA untuk siswa yang belum punya
 *     data terkait apa pun (PKL, biaya, surat, biodata, terlambat, UKK, …). Pengaman: beberapa tabel memakai ON DELETE CASCADE, jadi
 *     menghapus siswa yang sudah punya data akan ikut menghapus riwayatnya.
 *   - TERHAPUS (lama) = data yang dulu "dihapus" (hapus lunak, deleted_at) — bisa dipulihkan atau dihapus permanen.
 * Tabel terkait dibaca otomatis dari kolom `siswa_id` di database, jadi tabel baru kelak ikut terlindungi.
 */
class SiswaStatus
{
    /** Status nonaktif → label. */
    public const NONAKTIF = ['lulus' => 'Lulus', 'pindah' => 'Pindah sekolah', 'keluar' => 'Keluar'];

    private const LABEL_TABEL = [
        'pkl_anggota'         => 'Ajuan PKL',
        'pkl_anggota_keluar'  => 'Riwayat dikeluarkan dari PKL',
        'pkl_pembayaran'      => 'Biaya PKL',
        'pkl_beasiswa'        => 'Beasiswa PKL',
        'pkl_keringanan'      => 'Keringanan biaya PKL',
        'pkl_lapor'           => 'Laporan diterima PKL',
        'surat_sekolah_siswa' => 'Surat sekolah',
        'surat_permintaan'    => 'Permintaan surat',
        'biodata_isian'       => 'Isian biodata',
        'keterlambatan_siswa' => 'Catatan terlambat',
        'peserta_ukk'         => 'Peserta UKK',
        'ujian_susulan'       => 'Ujian susulan',
    ];

    /** @var list<string>|null */
    private static ?array $tabel = null;

    /** Semua tabel (selain siswa) yang punya kolom siswa_id. */
    public static function tabelTerkait(): array
    {
        if (self::$tabel === null) {
            $db          = db_connect();
            self::$tabel = array_values(array_filter(array_map(static fn (array $r): string => (string) array_values($r)[0], $db->query(
                'SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ? ORDER BY TABLE_NAME',
                [$db->getDatabase(), 'siswa_id']
            )->getResultArray()), static fn (string $t): bool => $t !== 'siswa'));
        }

        return self::$tabel;
    }

    /**
     * Data terkait per siswa.
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, int>> siswa_id => [label => jumlah] (siswa tanpa data terkait = [])
     */
    public static function keterkaitan(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = array_fill_keys($ids, []);
        if ($ids === []) {
            return $out;
        }
        $db = db_connect();
        foreach (self::tabelTerkait() as $t) {
            foreach ($db->table($t)->select('siswa_id, COUNT(*) AS n')->whereIn('siswa_id', $ids)->groupBy('siswa_id')->get()->getResultArray() as $r) {
                $out[(int) $r['siswa_id']][self::LABEL_TABEL[$t] ?? $t] = (int) $r['n'];
            }
        }

        return $out;
    }

    /** "Ajuan PKL (1), Biaya PKL (4)" */
    public static function ringkas(array $kait): string
    {
        return implode(', ', array_map(static fn (string $l, int $n): string => $l . ' (' . $n . ')', array_keys($kait), $kait));
    }

    /** Label status untuk tampilan: "Aktif", "Lulus", … */
    public static function label(string $status): string
    {
        return $status === 'aktif' ? 'Aktif' : (self::NONAKTIF[$status] ?? ucfirst($status));
    }

    /**
     * Nonaktifkan (lulus/pindah/keluar). Catatan (alasan) bila diisi ditambahkan ke keterangan siswa.
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function nonaktifkan(int $id, string $status, string $catatan, string $oleh): array
    {
        $s = self::siswa($id);
        if ($s === null) {
            return ['ok' => false, 'pesan' => 'Siswa tidak ditemukan.'];
        }
        if (! isset(self::NONAKTIF[$status])) {
            return ['ok' => false, 'pesan' => 'Pilih status: Lulus, Pindah sekolah, atau Keluar.'];
        }
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if (trim($catatan) !== '') {
            $baru               = self::NONAKTIF[$status] . ' ' . date('d-m-Y') . ': ' . trim($catatan);
            $data['keterangan'] = mb_substr(trim(($s['keterangan'] ? $s['keterangan'] . ' · ' : '') . $baru), -255);
        }
        db_connect()->table('siswa')->where('id', $id)->update($data);
        self::catat('update', $id, 'Siswa dinonaktifkan (' . self::NONAKTIF[$status] . '): ' . $s['nama'] . ' NIS ' . $s['nis'] . ' oleh ' . $oleh . (trim($catatan) !== '' ? ' — ' . trim($catatan) : ''));

        return ['ok' => true, 'pesan' => $s['nama'] . ' kini berstatus ' . self::NONAKTIF[$status] . ' (tidak aktif). Datanya tetap tersimpan.'];
    }

    /** @return array{ok: bool, pesan: string} */
    public static function aktifkan(int $id, string $oleh): array
    {
        $s = self::siswa($id);
        if ($s === null) {
            return ['ok' => false, 'pesan' => 'Siswa tidak ditemukan.'];
        }
        if ($s['status'] === 'aktif') {
            return ['ok' => true, 'pesan' => $s['nama'] . ' memang sudah aktif.'];
        }
        db_connect()->table('siswa')->where('id', $id)->update(['status' => 'aktif', 'updated_at' => date('Y-m-d H:i:s')]);
        self::catat('update', $id, 'Siswa diaktifkan kembali (sebelumnya ' . self::label($s['status']) . '): ' . $s['nama'] . ' NIS ' . $s['nis'] . ' oleh ' . $oleh);

        return ['ok' => true, 'pesan' => $s['nama'] . ' aktif kembali.'];
    }

    /** Pulihkan data yang dulu "dihapus" (hapus lunak). Status semula dipertahankan. @return array{ok: bool, pesan: string} */
    public static function pulihkan(int $id, string $oleh): array
    {
        $s = self::siswa($id, true);
        if ($s === null || $s['deleted_at'] === null) {
            return ['ok' => false, 'pesan' => 'Siswa terhapus tidak ditemukan.'];
        }
        db_connect()->table('siswa')->where('id', $id)->update(['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        self::catat('update', $id, 'Siswa terhapus dipulihkan: ' . $s['nama'] . ' NIS ' . $s['nis'] . ' (status ' . self::label($s['status']) . ') oleh ' . $oleh);

        return ['ok' => true, 'pesan' => $s['nama'] . ' dipulihkan (status ' . self::label($s['status']) . ').'];
    }

    /**
     * Hapus permanen siswa yang TIDAK punya data terkait; sisanya ditolak beserta alasannya. Berlaku juga untuk data terhapus lama.
     *
     * @param list<int> $ids
     *
     * @return array{dihapus: list<string>, ditolak: list<array{nama: string, terkait: string}>}
     */
    public static function hapusPermanen(array $ids, string $oleh): array
    {
        $db     = db_connect();
        $siswa  = $ids === [] ? [] : $db->table('siswa')->select('id, nama, nis')->whereIn('id', array_map('intval', $ids))->get()->getResultArray();
        $kait   = self::keterkaitan(array_column($siswa, 'id'));
        $out    = ['dihapus' => [], 'ditolak' => []];
        $hapus  = [];
        foreach ($siswa as $s) {
            if ($kait[(int) $s['id']] !== []) {
                $out['ditolak'][] = ['nama' => (string) $s['nama'], 'terkait' => self::ringkas($kait[(int) $s['id']])];
            } else {
                $hapus[]          = (int) $s['id'];
                $out['dihapus'][] = $s['nama'] . ' (NIS ' . $s['nis'] . ')';
            }
        }
        if ($hapus !== []) {
            $db->table('siswa')->whereIn('id', $hapus)->delete();
            self::catat('delete', count($hapus) === 1 ? $hapus[0] : null, mb_substr('Hapus PERMANEN ' . count($hapus) . ' siswa oleh ' . $oleh . ': ' . implode(', ', $out['dihapus']), 0, 255));
        }

        return $out;
    }

    /** Pesan ringkas hasil hapusPermanen untuk ditampilkan. */
    public static function pesanHapus(array $r): string
    {
        $p = [];
        if ($r['dihapus'] !== []) {
            $p[] = count($r['dihapus']) . ' siswa dihapus permanen.';
        }
        foreach (array_slice($r['ditolak'], 0, 5) as $t) {
            $p[] = $t['nama'] . ' TIDAK dihapus karena punya data: ' . $t['terkait'] . ' — pakai Nonaktifkan.';
        }
        if (count($r['ditolak']) > 5) {
            $p[] = '… dan ' . (count($r['ditolak']) - 5) . ' siswa lain yang punya data terkait.';
        }

        return $p === [] ? 'Tidak ada siswa yang dihapus.' : implode(' ', $p);
    }

    /**
     * Pemilik NIS/NISN lain (termasuk data terhapus) — untuk pesan bentrok yang jelas.
     *
     * @return array{id: int, nama: string, kelas: ?string, terhapus: bool}|null
     */
    public static function pemilik(string $kolom, ?string $nilai, int $kecualiId = 0): ?array
    {
        if (! in_array($kolom, ['nis', 'nisn'], true) || trim((string) $nilai) === '') {
            return null;
        }
        $r = db_connect()->table('siswa s')->select('s.id, s.nama, s.deleted_at, k.nama_kelas')->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.' . $kolom, trim((string) $nilai))->where('s.id !=', $kecualiId)->get()->getRowArray();

        return $r === null ? null : ['id' => (int) $r['id'], 'nama' => (string) $r['nama'], 'kelas' => $r['nama_kelas'], 'terhapus' => $r['deleted_at'] !== null];
    }

    /** Kalimat galat bentrok NIS/NISN, atau null bila tidak bentrok. */
    public static function pesanBentrok(string $kolom, ?string $nilai, int $kecualiId = 0): ?string
    {
        $p = self::pemilik($kolom, $nilai, $kecualiId);
        if ($p === null) {
            return null;
        }
        $siapa = $p['nama'] . ($p['kelas'] ? ' (' . $p['kelas'] . ')' : '');

        return $p['terhapus']
            ? strtoupper($kolom) . ' ini masih dipakai data siswa yang TERHAPUS: ' . $siapa . '. Buka saringan Status → "Terhapus (lama)", lalu Pulihkan atau Hapus permanen.'
            : strtoupper($kolom) . ' ini sudah dipakai siswa lain: ' . $siapa . '.';
    }

    // =================================================================

    private static function siswa(int $id, bool $termasukTerhapus = false): ?array
    {
        $q = db_connect()->table('siswa')->where('id', $id);
        if (! $termasukTerhapus) {
            $q->where('deleted_at', null);
        }

        return $q->get()->getRowArray();
    }

    private static function catat(string $aksi, ?int $id, string $deskripsi): void
    {
        master_data_changed('siswa');
        (new \App\Models\AuditModel())->record($aksi, 'siswa', $id, $deskripsi);
    }
}
