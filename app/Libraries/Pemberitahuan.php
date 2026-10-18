<?php

namespace App\Libraries;

/**
 * Isi ikon lonceng di bilah atas panel admin (2026-10-18): PEKERJAAN YANG MASIH MENUNGGU staf — bukan kotak pesan "sudah/belum
 * dibaca". Angkanya berkurang sendiri setelah pekerjaannya diproses (tanpa tabel baru). Tiap butir hanya muncul bagi peran yang
 * boleh menanganinya: alamat tujuannya lolos HakAkses::boleh (aturan yang sama dengan menu & penjaga rute) dan, untuk PKL/Surat,
 * hak rinci yang diatur Admin di Sistem → Hak Akses (mis. surat menunggu ACC dihitung per jenis yang boleh di-ACC peran itu).
 *
 * Dipakai layouts/admin.php (dirender server — tanpa kedipan) dan GET admin/pemberitahuan (JSON, diperbarui berkala oleh app.js).
 * Hanya COUNT ringan berindeks status; tabel modul yang belum ada dilewati.
 */
final class Pemberitahuan
{
    /**
     * @return array{total: int, butir: list<array{kunci: string, n: int, teks: string, url: string, ikon: string}>}
     */
    public static function untuk(?string $peran): array
    {
        $peran = (string) $peran;
        $butir = [];
        if (! HakPeran::dikenal($peran)) {
            return ['total' => 0, 'butir' => []];
        }
        $tambah = static function (string $kunci, int $n, string $teks, string $alamat, string $ikon) use (&$butir): void {
            if ($n > 0) {
                $butir[] = ['kunci' => $kunci, 'n' => $n, 'teks' => $teks, 'url' => site_url($alamat), 'ikon' => $ikon];
            }
        };

        try {
            $db  = db_connect();
            $ada = static fn (string $t): bool => $db->tableExists($t);

            // PKL: ajuan siswa menunggu keputusan (pemegang hak ACC atau "periksa & kembalikan").
            if ($ada('pkl_pengajuan') && HakAkses::boleh($peran, 'admin/pkl/daftar/menunggu') && HakAkses::bolehPkl($peran, 'acc|ubah')) {
                $tambah('pkl_menunggu', $db->table('pkl_pengajuan')->where('status', 'menunggu')->countAllResults(),
                    'ajuan PKL menunggu ACC', 'admin/pkl/daftar/menunggu', 'briefcase');
            }

            // PKL: siswa lapor sudah diterima perusahaan → bahan Surat Balasan.
            if ($ada('pkl_lapor') && HakAkses::boleh($peran, 'admin/surat/balasan/laporan') && PklLapor::hak($peran)['lihat']) {
                $tambah('pkl_lapor', $db->table('pkl_lapor')->where('status', 'baru')->countAllResults(),
                    'laporan "diterima PKL" dari siswa', 'admin/surat/balasan/laporan', 'inbox');
            }

            // Surat sekolah: menunggu ACC / sudah di-ACC tapi belum diunduh — dihitung per jenis yang boleh ditangani peran ini.
            if ($ada('surat_sekolah') && HakAkses::boleh($peran, 'admin/surat')) {
                $perJenis = static function (string $where) use ($db): array {
                    $out = [];
                    foreach ($db->query('SELECT jenis, COUNT(*) n FROM surat_sekolah WHERE ' . $where . ' GROUP BY jenis')->getResultArray() as $r) {
                        $out[(string) $r['jenis']] = (int) $r['n'];
                    }

                    return $out;
                };
                $jumlah = static function (array $hitung, string $aksi) use ($peran): int {
                    $n = 0;
                    foreach ($hitung as $jenis => $x) {
                        $n += PklHak::bolehSurat($peran, $aksi, (string) $jenis) ? $x : 0;
                    }

                    return $n;
                };
                $tambah('surat_acc', $jumlah($perJenis("status = 'menunggu'"), 'acc'), 'surat sekolah menunggu ACC', 'admin/surat?status=menunggu', 'envelope');
                $tambah('surat_unduh', $jumlah($perJenis("status = 'disetujui' AND cetak_ke = 0"), 'unduh'), 'surat sudah di-ACC, belum diunduh', 'admin/surat?status=disetujui', 'download');
            }

            // Biodata siswa online: isian masuk menunggu diperiksa.
            if ($ada('biodata_isian') && HakAkses::boleh($peran, 'admin/biodata')) {
                $tambah('biodata', $db->table('biodata_isian')->where('status', 'menunggu')->countAllResults(),
                    'isian biodata siswa menunggu diperiksa', 'admin/biodata', 'profile-card');
            }

            // Laboratorium.
            if ($ada('peminjaman') && HakAkses::boleh($peran, 'admin/peminjaman')) {
                $tambah('lab_terlambat', $db->table('peminjaman')->where('deleted_at', null)->where('status', 'terlambat')->countAllResults(),
                    'peminjaman lab terlambat dikembalikan', 'admin/peminjaman', 'clock-arrow');
            }
            if ($ada('kerusakan') && HakAkses::boleh($peran, 'admin/kerusakan')) {
                $tambah('lab_rusak', $db->table('kerusakan')->where('deleted_at', null)->where('status', 'dilaporkan')->countAllResults(),
                    'laporan kerusakan lab belum diproses', 'admin/kerusakan', 'bug');
            }
        } catch (\Throwable $e) {
            // Lonceng tak boleh membuat halaman gagal dimuat.
            log_message('error', 'Pemberitahuan gagal dihitung: {pesan}', ['pesan' => $e->getMessage()]);
        }

        return ['total' => array_sum(array_column($butir, 'n')), 'butir' => $butir];
    }
}
