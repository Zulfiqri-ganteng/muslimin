<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Pemeriksaan honor sebelum difinalkan/dikunci (Fase 5). Hanya MENAMPILKAN temuan; tidak memblokir apa pun
 * (kecuali aturan status di HonorDokumen::ubahStatus). Tiap temuan: level 'peringatan' (perlu dicek) atau 'info'.
 *
 *   - penerima tanpa honor (total Rp 0), nama ganda, penerima yang sudah tak ada di Master Guru
 *   - komponen aktif yang belum diisi sama sekali
 *   - Koreksi / Rapot yang menyimpang jauh (>25 %) dari hitungan sistem; Pengawas yang berbeda dari jadwal
 *   - total seseorang sangat besar (kemungkinan salah ketik)
 *   - Ketua / Bendahara / Kepala Sekolah / tanggal belum diisi (tanda tangan di cetakan kosong)
 *   - perbandingan total dengan honor ujian yang sama pada tahun pelajaran sebelumnya
 */
final class HonorPeriksa
{
    public const BATAS_TOTAL_ORANG = 10000000;
    public const SIMPANG_PERSEN    = 25;
    public const SIMPANG_MIN       = 20;
    private const MAKS_DAFTAR      = 6;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array<string,mixed> $m       hasil HonorDokumen::muat()
     * @param array<string,mixed> $periode baris ujian_periode
     *
     * @return list<array{level:string, kode:string, teks:string}>
     */
    public function periksa(array $m, array $periode): array
    {
        $t = [];
        $tambah = static function (string $level, string $kode, string $teks) use (&$t): void {
            $t[] = ['level' => $level, 'kode' => $kode, 'teks' => $teks];
        };
        $daftar = static function (array $nama): string {
            $tampil = array_slice($nama, 0, self::MAKS_DAFTAR);

            return implode(', ', $tampil) . (count($nama) > self::MAKS_DAFTAR ? ' dan ' . (count($nama) - self::MAKS_DAFTAR) . ' lainnya' : '');
        };
        $rp = static fn (int $n): string => 'Rp ' . number_format($n, 0, ',', '.');
        $dok = $m['dokumen'];

        if ($m['baris'] === []) {
            $tambah('peringatan', 'kosong', 'Belum ada penerima.');

            return $t;
        }

        // 1. total Rp 0
        $nol = array_map(static fn (array $b): string => (string) $b['nama'], array_filter($m['baris'], static fn (array $b): bool => (int) $b['total'] === 0));
        if ($nol !== []) {
            $tambah('peringatan', 'total_nol', count($nol) . ' penerima bertotal Rp 0 (hapus dari daftar bila tidak menerima honor): ' . $daftar(array_values($nol)) . '.');
        }

        // 2. nama ganda
        $seen = [];
        $ganda = [];
        foreach ($m['baris'] as $b) {
            $n = HonorImpor::normalNama((string) $b['nama']);
            if ($n === '') {
                continue;
            }
            if (isset($seen[$n])) {
                $ganda[$n] = $b['nama'];
            }
            $seen[$n] = true;
        }
        if ($ganda !== []) {
            $tambah('peringatan', 'nama_ganda', 'Nama yang muncul lebih dari sekali (kemungkinan terhitung dua kali): ' . $daftar(array_values($ganda)) . '.');
        }

        // 3. penerima yang gurunya sudah dihapus
        $ids = array_values(array_filter(array_map(static fn (array $b): int => (int) ($b['guru_id'] ?? 0), $m['baris'])));
        $ada = $ids === [] ? [] : array_map('intval', array_column($this->db->table('guru')->select('id')->whereIn('id', $ids)->where('deleted_at', null)->get()->getResultArray(), 'id'));
        $hilang = [];
        foreach ($m['baris'] as $b) {
            if ($b['guru_id'] === null || ! in_array((int) $b['guru_id'], $ada, true)) {
                $hilang[] = (string) $b['nama'];
            }
        }
        if ($hilang !== []) {
            $tambah('info', 'tanpa_master', count($hilang) . ' penerima sudah tidak ada di Master Guru (honor tetap tersimpan, tetapi hitung otomatis melewatinya): ' . $daftar($hilang) . '.');
        }

        // 4. komponen yang belum diisi sama sekali
        foreach ($m['komponen'] as $k) {
            if ((int) ($m['total_jumlah'][(int) $k['id']] ?? 0) === 0 && (int) ($m['total_komponen'][(int) $k['id']] ?? 0) === 0) {
                $tambah('info', 'komponen_kosong', 'Komponen "' . $k['nama'] . '" belum diisi untuk siapa pun.');
            }
        }

        // 5. total seseorang sangat besar
        $besar = [];
        foreach ($m['baris'] as $b) {
            if ((int) $b['total'] > self::BATAS_TOTAL_ORANG) {
                $besar[] = $b['nama'] . ' (' . $rp((int) $b['total']) . ')';
            }
        }
        if ($besar !== []) {
            $tambah('peringatan', 'total_besar', 'Total di atas ' . $rp(self::BATAS_TOTAL_ORANG) . ' — periksa kemungkinan salah ketik: ' . $daftar($besar) . '.');
        }

        // 6. Koreksi / Rapot menyimpang dari hitungan sistem; Pengawas ≠ jadwal
        $hit = new HonorHitung($this->db);
        foreach ($m['komponen'] as $k) {
            if (! in_array($k['sumber'], ['koreksi', 'rapot'], true)) {
                continue;
            }
            $sistem = $hit->sumber($k['sumber'], (int) $dok['periode_id']);
            $beda   = [];
            foreach ($m['baris'] as $b) {
                if ($b['guru_id'] === null || ! in_array((int) $b['guru_id'], $ada, true)) {
                    continue;
                }
                $nilai = (int) ($b['nilai'][(int) $k['id']]['nilai'] ?? 0);
                $s     = (int) ($sistem[(int) $b['guru_id']] ?? 0);
                $selisih = abs($nilai - $s);
                if ($selisih >= self::SIMPANG_MIN && $selisih * 100 > self::SIMPANG_PERSEN * max($s, 1)) {
                    $beda[] = $b['nama'] . ' (' . $nilai . ' vs hitungan ' . $s . ')';
                }
            }
            if ($beda !== []) {
                $tambah('info', 'menyimpang_' . $k['sumber'], count($beda) . ' isian ' . $k['nama'] . ' menyimpang lebih dari ' . self::SIMPANG_PERSEN . ' % dari hitungan sistem (wajar bila ada kebijakan khusus): ' . $daftar($beda) . '.');
            }
        }
        $peng = null;
        foreach ($m['komponen'] as $k) {
            if ($k['kode'] === 'pengawas') {
                $peng = (int) $k['id'];
            }
        }
        if ($peng !== null) {
            $petunjuk = $hit->pengawasPetunjuk((int) $dok['periode_id']);
            $beda     = [];
            foreach ($m['baris'] as $b) {
                $nilai = (int) ($b['nilai'][$peng]['nilai'] ?? 0);
                $j     = (int) ($petunjuk[(int) ($b['guru_id'] ?? 0)] ?? 0);
                if ($j > 0 && $j !== $nilai) {
                    $beda[] = $b['nama'] . ' (' . $nilai . ' vs jadwal ' . $j . ')';
                }
            }
            if ($beda !== []) {
                $tambah('info', 'pengawas_beda', count($beda) . ' isian Pengawas berbeda dari jadwal pengawas (jumlah sesi memang diketik manual): ' . $daftar($beda) . '.');
            }
        }

        // 7. penanda tangan & tanggal
        foreach (['ketua_nama' => 'Ketua panitia', 'bendahara_nama' => 'Bendahara', 'kepsek_nama' => 'Kepala Sekolah'] as $kol => $label) {
            if (trim((string) ($dok[$kol] ?? '')) === '') {
                $tambah('peringatan', 'ttd_' . $kol, 'Nama ' . $label . ' belum diisi — tanda tangan di cetakan akan kosong (isi di "Data surat & tanda tangan").');
            }
        }
        if (empty($dok['tanggal'])) {
            $tambah('info', 'tanggal_kosong', 'Tanggal surat belum diisi.');
        }

        // 8. bandingkan dengan tahun pelajaran sebelumnya (jenis ujian sama)
        $lalu = $this->db->table('honor_dokumen d')->select('d.id, p.tahun_ajaran')->join('ujian_periode p', 'p.id = d.periode_id')
            ->where('p.jenis', $periode['jenis'])->where('p.tahun_ajaran <', $periode['tahun_ajaran'])->orderBy('p.tahun_ajaran', 'DESC')->get()->getRowArray();
        if ($lalu !== null) {
            $ml = (new HonorDokumen($this->db))->muat((int) $lalu['id']);
            if ($ml !== null && (int) $ml['total'] > 0) {
                $persen = (int) round(((int) $m['total'] - (int) $ml['total']) * 100 / (int) $ml['total']);
                $tambah('info', 'banding', 'Total ' . $rp((int) $m['total']) . ' — ' . ($persen >= 0 ? 'naik ' : 'turun ') . abs($persen) . ' % dibanding TP ' . $lalu['tahun_ajaran'] . ' (' . $rp((int) $ml['total']) . ').');
            }
        }

        return $t;
    }
}
