<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;

/**
 * Peringatan otomatis di halaman detail ajuan — membantu staf menangkap kesalahan
 * manusia SEBELUM menekan ACC: perusahaan ganda/mirip, anggota bermasalah,
 * HP siswa kosong (tercetak di surat), dan tanda ajuan "iseng". Tanggal PKL tidak
 * lagi ditanyakan, jadi tak ada peringatan tanggal.
 *
 * Tiap peringatan: ['tingkat' => 'bahaya'|'awas'|'info', 'teks' => string].
 *   bahaya = jangan ACC sebelum dibereskan · awas = periksa dulu · info = sekadar tahu.
 */
final class PklPeringatan
{
    /**
     * Perusahaan di master yang sama persis / mirip dengan nama pembanding.
     *
     * @return array{persis: ?array<string, mixed>, mirip: list<array<string, mixed>>}
     */
    public static function kandidatMaster(string $norm): array
    {
        $hasil = ['persis' => null, 'mirip' => []];
        if ($norm === '') {
            return $hasil;
        }

        $rows = db_connect()->table('pkl_perusahaan')->select('id, nama, nama_norm, alamat, kota')->orderBy('id')->get()->getResultArray();
        foreach ($rows as $r) {
            $lain = (string) $r['nama_norm'];
            if ($lain === $norm) {
                $hasil['persis'] ??= $r;
                continue;
            }
            similar_text($norm, $lain, $persen);
            $berisi = mb_strlen($norm) >= 5 && mb_strlen($lain) >= 5 && (str_contains($lain, $norm) || str_contains($norm, $lain));
            if ($persen >= 80.0 || $berisi) {
                $hasil['mirip'][] = $r;
            }
        }
        $hasil['mirip'] = array_slice($hasil['mirip'], 0, 5);

        return $hasil;
    }

    /**
     * @param array<string, mixed>       $ajuan   baris pkl_pengajuan (PklPengajuanModel::detail)
     * @param list<array<string, mixed>> $anggota PklPengajuanModel::anggotaDetail
     * @param array<string, mixed>       $p       baris pkl_pengaturan
     *
     * @return list<array{tingkat: string, teks: string}>
     */
    public static function untuk(array $ajuan, array $anggota, array $p): array
    {
        $w    = [];
        $tmb  = static function (string $tingkat, string $teks) use (&$w): void {
            $w[] = ['tingkat' => $tingkat, 'teks' => $teks];
        };
        $aktif = PklPengajuanModel::aktif((string) $ajuan['status']);
        // Data yang diisi staf/impor (mis. riwayat lama) sengaja boleh di luar pagar & tanpa HP: cukup "info".
        $ringan = ($ajuan['sumber'] ?? 'siswa') !== 'siswa' ? 'info' : 'awas';

        // ---------- Perusahaan ----------
        if ($ajuan['status'] !== 'disetujui') {
            $k = self::kandidatMaster((string) $ajuan['perusahaan_norm']);
            if ($k['persis'] !== null) {
                $tmb('info', 'Perusahaan ini sudah terdaftar di master: ' . $k['persis']['nama'] . ' — akan ditautkan otomatis saat ACC.');
            }
            if ($k['mirip'] !== []) {
                $nama = implode('; ', array_map(static fn (array $r) => $r['nama'] . ($r['kota'] ? ' (' . $r['kota'] . ')' : ''), $k['mirip']));
                $tmb('awas', 'Nama mirip dengan perusahaan terdaftar: ' . $nama . '. Bila itu perusahaan yang sama, pilih salah satunya saat ACC agar tidak jadi dua data.');
            }
        }
        $serupa = (new PklPengajuanModel())->ajuanSerupa((string) $ajuan['perusahaan_norm'], (int) $ajuan['id']);
        if ($serupa !== []) {
            $daftar = implode(', ', array_map(static fn (array $r) => PklPengajuanModel::kode((int) $r['id']) . ' (' . $r['status'] . ')', array_slice($serupa, 0, 5)));
            $tmb('info', 'Perusahaan yang sama/mirip juga diajukan di: ' . $daftar . '.');
        }
        if (trim((string) ($ajuan['perusahaan_telepon'] ?? '')) === '') {
            $tmb('info', 'Telepon perusahaan kosong — sekolah tak bisa mengonfirmasi lewat telepon.');
        }
        if (trim((string) ($ajuan['perusahaan_alamat'] ?? '')) === '') {
            $tmb('awas', 'Alamat perusahaan kosong — surat tak bisa dialamatkan.');
        }

        // ---------- Anggota ----------
        $boleh = PklPengaturanModel::tingkatBoleh($p);
        $maks  = PklPengaturanModel::maksSiswa($p);
        if ($maks > 0 && count($anggota) > $maks) {
            $tmb('awas', 'Jumlah siswa (' . count($anggota) . ') melebihi batas ' . $maks . ' per perusahaan.');
        }
        foreach ($anggota as $a) {
            $nama = (string) $a['nama'];
            if ($a['siswa_dihapus'] !== null || $a['status_siswa'] !== 'aktif') {
                $tmb('bahaya', $nama . ' sudah tidak berstatus siswa aktif (' . ($a['siswa_dihapus'] !== null ? 'dihapus dari Master Siswa' : $a['status_siswa']) . ').');
            }
            if ($a['kelas_id'] !== null && $a['kelas_sekarang'] !== null && (int) $a['kelas_id'] !== (int) $a['kelas_sekarang']) {
                $tmb('info', $nama . ' kini sudah pindah/naik kelas (di surat tetap tertulis kelas saat mengajukan: ' . $a['nama_kelas'] . ').');
            }
            if ($boleh !== [] && $a['tingkat'] !== null && ! in_array($a['tingkat'], $boleh, true)) {
                $tmb('awas', $nama . ' berada di tingkat ' . $a['tingkat'] . ', di luar tingkat yang diizinkan sekarang (' . implode(', ', $boleh) . ').');
            }
            if ($aktif && $a['siswa_aktif'] === null) {
                $tmb('bahaya', 'Data ' . $nama . ' tidak terkunci padahal ajuan aktif — jalankan pemeriksaan data.');
            }
            // HP tercetak di tabel surat: cukup bila ada di ajuan ATAU di Master Siswa (no_hp).
            if (trim((string) ($a['hp'] ?? '')) === '' && trim((string) ($a['hp_master'] ?? '')) === '') {
                $tmb($ringan, 'No. HP ' . ($a['peran'] === 'pengaju' ? 'pengaju ' : '') . '(' . $nama . ') kosong — di surat akan tertulis "-" dan sekolah tak bisa menghubunginya.');
            }
        }

        // Urut: bahaya, awas, info.
        $bobot = ['bahaya' => 0, 'awas' => 1, 'info' => 2];
        usort($w, static fn ($x, $y) => $bobot[$x['tingkat']] <=> $bobot[$y['tingkat']]);

        return $w;
    }
}
