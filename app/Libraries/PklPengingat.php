<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Pengingat LAYAR PENUH untuk pekerjaan PKL yang lewat batas keputusan (keluhan klien 2026-10-21: Waka Hubin jarang membuka
 * sistem sehingga ajuan siswa lama tidak di-ACC).
 *
 * Batas = Pengaturan PKL → "Batas keputusan" (PklPengaturanModel::batasHari, bawaan 5 hari), dihitung SAMA dengan tanda merah
 * "terlambat" di halaman PKL (PklPengajuanModel::hitungTerlambat): lewat bila tanggal kirim + N hari < hari ini.
 *   - ajuan : ajuan MENUNGGU yang lewat batas → bagi pemegang hak "ACC ajuan PKL" (bawaan Waka Hubin). Selama menunggu, siswanya
 *             belum bisa mengambil Surat Izin PKL dan tidak bisa mengajukan tempat lain.
 *   - lapor : laporan hasil perusahaan dari siswa yang BELUM diproses lewat batas (dihitung dari kiriman terakhir) → bagi pemegang
 *             hak memproses kotak laporan (Buat/ACC Surat Balasan; di sekolah = Operator). Siswa yang dilaporkan TIDAK diterima
 *             perusahaan baru bebas mengajukan tempat lain setelah laporan itu diproses (PklLapor::proses).
 *
 * Web: layouts/admin.php menampilkan dialog layar penuh (admin/pkl/_pengingat) di halaman mana pun, KECUALI halaman tempat
 * pekerjaannya diselesaikan (admin/pkl… untuk ajuan, admin/surat/balasan… untuk laporan) dan KECUALI peran Admin (Admin tetap
 * melihat lonceng + tanda merah). "Ingatkan lagi nanti" menundanya 1 jam (sesi); login berikutnya = sesi baru = tampil lagi.
 * Android: GET api/v1/pkl/pengingat (isi yang sama + 'layar_penuh').
 */
final class PklPengingat
{
    /** Kunci sesi: waktu (unix) sampai kapan dialog ditunda. */
    public const SESI_TUNDA = 'pkl_pengingat_tunda';

    public const TUNDA_DETIK = 3600;

    /** Baris yang dirinci per bagian; sisanya cukup jumlahnya + tautan ke daftar lengkap. */
    public const MAKS_BARIS = 10;

    /**
     * Pekerjaan lewat batas yang boleh ditangani peran ini. Bagian yang tidak berlaku / kosong = null.
     *
     * @return array{hari: int, total: int, ajuan: array{n: int, siswa: int, baris: list<array<string, mixed>>}|null,
     *               lapor: array{n: int, tertahan: int, baris: list<array<string, mixed>>}|null}
     */
    public static function untuk(?string $peran, ?array $p = null, bool $ajuan = true, bool $lapor = true): array
    {
        $peran = (string) $peran;
        $p   ??= (new PklPengaturanModel())->ambil();
        $hari  = PklPengaturanModel::batasHari($p);
        $out   = ['hari' => $hari, 'total' => 0, 'ajuan' => null, 'lapor' => null];
        if (! HakPeran::dikenal($peran)) {
            return $out;
        }

        try {
            $db = db_connect();
            if ($ajuan && HakAkses::bolehAcc($peran) && HakAkses::boleh($peran, 'admin/pkl/daftar/menunggu') && $db->tableExists('pkl_pengajuan')) {
                $out['ajuan'] = self::ajuan($db, $hari);
            }
            if ($lapor && PklLapor::tabelAda() && PklLapor::hak($peran, $p)['lihat'] && HakAkses::boleh($peran, 'admin/surat/balasan/laporan')) {
                $out['lapor'] = self::lapor($db, $hari);
            }
        } catch (\Throwable $e) {
            log_message('error', '[PklPengingat] ' . $e->getMessage());

            return ['hari' => $hari, 'total' => 0, 'ajuan' => null, 'lapor' => null];
        }

        foreach (['ajuan', 'lapor'] as $k) {
            if ($out[$k] !== null && $out[$k]['n'] === 0) {
                $out[$k] = null;
            }
        }
        $out['total'] = ($out['ajuan']['n'] ?? 0) + ($out['lapor']['n'] ?? 0);

        return $out;
    }

    /**
     * Isi dialog layar penuh untuk layout admin di alamat $alamat, atau null bila tidak perlu tampil: peran Admin, sedang ditunda,
     * atau sedang berada di halaman tempat pekerjaan itu diselesaikan.
     *
     * @return array<string, mixed>|null
     */
    public static function untukLayout(?string $peran, string $alamat): ?array
    {
        if ((string) $peran === HakPeran::ADMIN || (int) session(self::SESI_TUNDA) > time()) {
            return null;
        }
        $alamat = trim($alamat, '/');
        $ajuan  = ! self::diBawah($alamat, 'admin/pkl');
        $lapor  = ! self::diBawah($alamat, 'admin/surat/balasan');
        if (! $ajuan && ! $lapor) {
            return null;
        }
        $d = self::untuk($peran, null, $ajuan, $lapor);

        return $d['total'] > 0 ? $d : null;
    }

    /** Tombol "Ingatkan lagi nanti": dialog tidak tampil selama TUNDA_DETIK di sesi ini. */
    public static function tunda(): void
    {
        session()->set(self::SESI_TUNDA, time() + self::TUNDA_DETIK);
    }

    /** Jumlah hari sejak tanggal $waktu (Y-m-d …) sampai hari ini. */
    public static function hariSejak(string $waktu): int
    {
        $t = strtotime(substr($waktu, 0, 10));

        return $t === false ? 0 : max(0, (int) round((strtotime(date('Y-m-d')) - $t) / 86400));
    }

    private static function diBawah(string $alamat, string $awalan): bool
    {
        return $alamat === $awalan || str_starts_with($alamat, $awalan . '/');
    }

    /** @return array{n: int, siswa: int, baris: list<array<string, mixed>>} */
    private static function ajuan(BaseConnection $db, int $hari): array
    {
        $syarat = "p.status = 'menunggu' AND DATE_ADD(DATE(COALESCE(p.diajukan_at, p.created_at)), INTERVAL ? DAY) < CURDATE()";
        $hitung = $db->query(
            'SELECT COUNT(*) n, COALESCE(SUM((SELECT COUNT(*) FROM pkl_anggota a WHERE a.pengajuan_id = p.id)), 0) siswa FROM pkl_pengajuan p WHERE ' . $syarat,
            [$hari]
        )->getRowArray() ?? [];
        $n = (int) ($hitung['n'] ?? 0);
        if ($n === 0) {
            return ['n' => 0, 'siswa' => 0, 'baris' => []];
        }
        // Pengaju lebih dulu, lalu urutan masuk — nama & kelas yang mewakili kelompok.
        $wakil = "FROM pkl_anggota a %s WHERE a.pengajuan_id = p.id ORDER BY a.peran = 'pengaju' DESC, a.id LIMIT 1";
        $rows  = $db->query(
            'SELECT p.id, p.perusahaan_nama, COALESCE(p.diajukan_at, p.created_at) AS kirim,'
            . ' (SELECT COUNT(*) FROM pkl_anggota a WHERE a.pengajuan_id = p.id) AS jml,'
            . ' (SELECT s.nama ' . sprintf($wakil, 'JOIN siswa s ON s.id = a.siswa_id') . ') AS nama,'
            . ' (SELECT k.nama_kelas ' . sprintf($wakil, 'LEFT JOIN kelas k ON k.id = a.kelas_id') . ') AS kelas'
            . ' FROM pkl_pengajuan p WHERE ' . $syarat . ' ORDER BY kirim, p.id LIMIT ' . self::MAKS_BARIS,
            [$hari]
        )->getResultArray();

        return [
            'n'     => $n,
            'siswa' => (int) ($hitung['siswa'] ?? 0),
            'baris' => array_map(static fn (array $r): array => [
                'id'            => (int) $r['id'],
                'kode'          => PklPengajuanModel::kode((int) $r['id']),
                'perusahaan'    => (string) $r['perusahaan_nama'],
                'siswa'         => (string) ($r['nama'] ?? '-'),
                'kelas'         => (string) ($r['kelas'] ?? ''),
                'jumlah_siswa'  => (int) $r['jml'],
                'dikirim'       => (string) $r['kirim'],
                'menunggu_hari' => self::hariSejak((string) $r['kirim']),
            ], $rows),
        ];
    }

    /** @return array{n: int, tertahan: int, baris: list<array<string, mixed>>} */
    private static function lapor(BaseConnection $db, int $hari): array
    {
        $hasil = PklLapor::kolomHasil() ? 'l.hasil, l.diterima,' : "'semua' AS hasil, NULL AS diterima,";
        $rows  = $db->query(
            'SELECT l.id, l.pengajuan_id, l.pelapor_nama, ' . $hasil . ' l.updated_at, p.perusahaan_nama,'
            . ' (SELECT COUNT(*) FROM pkl_anggota a WHERE a.pengajuan_id = l.pengajuan_id) AS jml'
            . ' FROM pkl_lapor l JOIN pkl_pengajuan p ON p.id = l.pengajuan_id'
            . " WHERE l.status = 'baru' AND DATE_ADD(DATE(l.updated_at), INTERVAL ? DAY) < CURDATE()"
            . ' ORDER BY l.updated_at, l.id',
            [$hari]
        )->getResultArray();

        $baris    = [];
        $tertahan = 0;
        foreach ($rows as $r) {
            $h = (string) ($r['hasil'] ?? 'semua');
            $h = isset(PklLapor::HASIL[$h]) ? $h : 'semua';
            // Siswa yang dilaporkan TIDAK diterima dan masih terkunci di ajuan sampai laporan diproses.
            $tolak = match ($h) {
                'ditolak'  => (int) $r['jml'],
                'sebagian' => max(0, (int) $r['jml'] - count(PklLapor::idDiterima($r))),
                default    => 0,
            };
            $tertahan += $tolak;
            $baris[] = [
                'id'            => (int) $r['id'],
                'ajuan_id'      => (int) $r['pengajuan_id'],
                'kode'          => PklPengajuanModel::kode((int) $r['pengajuan_id']),
                'perusahaan'    => (string) $r['perusahaan_nama'],
                'pelapor'       => (string) ($r['pelapor_nama'] ?? ''),
                'hasil'         => $h,
                'hasil_teks'    => PklLapor::HASIL[$h],
                'tertahan'      => $tolak,
                'dikirim'       => (string) $r['updated_at'],
                'menunggu_hari' => self::hariSejak((string) $r['updated_at']),
            ];
        }

        return ['n' => count($baris), 'tertahan' => $tertahan, 'baris' => array_slice($baris, 0, self::MAKS_BARIS)];
    }
}
