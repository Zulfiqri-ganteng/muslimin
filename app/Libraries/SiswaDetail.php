<?php

namespace App\Libraries;

use App\Models\BiodataIsianModel;
use App\Models\PklPengajuanModel;
use App\Models\SiswaModel;

/**
 * Detail LENGKAP satu siswa — satu sumber untuk halaman web "Detail Siswa" dan API aplikasi
 * (GET admin/master/siswa/{id}), supaya isi, label, dan urutannya selalu sama.
 *
 * Bentuk hasil muat():
 *   siswa     baris siswa mentah + kelas/tingkat/jurusan (SiswaModel::withRelations)
 *   bagian    list [judul, baris[[kunci, label, nilai|null]]]  — nilai SUDAH diformat tampil (tanggal Indonesia, L → Laki-laki…)
 *   biodata   disahkan_pada, kurang[] (label kolom wajib yang masih kosong), isian (isian terakhir siswa) | null
 *   pkl       ajuan PKL aktif siswa (status, kode, perusahaan, nomor surat) | null
 *   jejak     dibuat, diubah
 */
final class SiswaDetail
{
    private const BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** @return array<string, mixed>|null null bila siswa tak ada / sudah dihapus */
    public static function muat(int $id): ?array
    {
        $r = (new SiswaModel())->withRelations()->where('siswa.id', $id)->first();
        if ($r === null) {
            return null;
        }

        $v = static fn (string $k): ?string => (isset($r[$k]) && trim((string) $r[$k]) !== '') ? trim((string) $r[$k]) : null;
        $rtRw = static function (string $rt, string $rw) use ($v): ?string {
            $a = $v($rt);
            $b = $v($rw);

            return $a === null && $b === null ? null : trim(($a !== null ? 'RT ' . $a : '') . ($a !== null && $b !== null ? ' / ' : '') . ($b !== null ? 'RW ' . $b : ''));
        };
        $jk = match ($r['jenis_kelamin'] ?? null) {
            'L' => 'Laki-laki', 'P' => 'Perempuan', default => null,
        };
        $kelas = $v('nama_kelas');

        $bagian = [
            ['Identitas', [
                ['nis', 'NIS', $v('nis')],
                ['nisn', 'NISN', $v('nisn')],
                ['nama', 'Nama Lengkap', $v('nama')],
                ['jenis_kelamin', 'Jenis Kelamin', $jk],
                ['tempat_lahir', 'Tempat Lahir', $v('tempat_lahir')],
                ['tanggal_lahir', 'Tanggal Lahir', self::tanggal($v('tanggal_lahir'))],
                ['agama', 'Agama', $v('agama')],
                ['status_keluarga', 'Status dalam Keluarga', $v('status_keluarga')],
                ['anak_ke', 'Anak Ke', $v('anak_ke')],
            ]],
            ['Kelas & Status', [
                ['kelas', 'Kelas', $kelas ?? 'Belum ada kelas'],
                ['tingkat', 'Tingkat', $v('tingkat')],
                ['jurusan', 'Jurusan', $v('jurusan_nama') !== null ? $v('jurusan_nama') . ($v('jurusan_kode') !== null ? ' (' . $v('jurusan_kode') . ')' : '') : null],
                ['tahun_masuk', 'Tahun Masuk', $v('tahun_masuk')],
                ['status', 'Status', ucfirst((string) ($r['status'] ?? 'aktif'))],
                ['keterangan', 'Keterangan', $v('keterangan')],
            ]],
            ['Alamat & Kontak Siswa', [
                ['alamat', 'Alamat', $v('alamat')],
                ['rt_rw', 'RT / RW', $rtRw('rt', 'rw')],
                ['kelurahan', 'Kelurahan/Desa', $v('kelurahan')],
                ['kecamatan', 'Kecamatan', $v('kecamatan')],
                ['kota', 'Kota/Kabupaten', $v('kota')],
                ['no_hp', 'No. HP Siswa', $v('no_hp')],
            ]],
            ['Riwayat Masuk & STTB', [
                ['sekolah_asal', 'Sekolah Asal', $v('sekolah_asal')],
                ['diterima_kelas', 'Diterima di Kelas', $v('diterima_kelas')],
                ['diterima_tanggal', 'Diterima pada Tanggal', self::tanggal($v('diterima_tanggal'))],
                ['sttb_nomor', 'STTB Nomor', $v('sttb_nomor')],
                ['sttb_tahun', 'STTB Tahun', $v('sttb_tahun')],
            ]],
            ['Orang Tua', [
                ['nama_orang_tua', 'Nama Orang Tua (versi sekolah)', $v('nama_orang_tua')],
                ['nama_ayah', 'Nama Ayah', $v('nama_ayah')],
                ['pekerjaan_ayah', 'Pekerjaan Ayah', $v('pekerjaan_ayah')],
                ['nama_ibu', 'Nama Ibu', $v('nama_ibu')],
                ['pekerjaan_ibu', 'Pekerjaan Ibu', $v('pekerjaan_ibu')],
                ['ortu_alamat', 'Alamat Orang Tua', $v('ortu_alamat')],
                ['ortu_rt_rw', 'RT / RW Orang Tua', $rtRw('ortu_rt', 'ortu_rw')],
                ['ortu_kelurahan', 'Kelurahan/Desa', $v('ortu_kelurahan')],
                ['ortu_kecamatan', 'Kecamatan', $v('ortu_kecamatan')],
                ['ortu_kota', 'Kota/Kabupaten', $v('ortu_kota')],
                ['ortu_telepon', 'No. Telepon Orang Tua', $v('ortu_telepon')],
            ]],
            ['Wali', [
                ['nama_wali', 'Nama Wali', $v('nama_wali')],
                ['pekerjaan_wali', 'Pekerjaan Wali', $v('pekerjaan_wali')],
                ['alamat_wali', 'Alamat Wali', $v('alamat_wali')],
                ['no_hp_wali', 'No. HP Wali', $v('no_hp_wali')],
            ]],
        ];

        // Isian biodata terakhir siswa (bila pernah mengisi lewat formulir publik).
        $isian = (new BiodataIsianModel())->milikSiswa($id);

        // Ajuan PKL yang sedang aktif (menunggu / perbaikan / disetujui).
        $pkl = null;
        $a   = (new PklPengajuanModel())->aktifMilik($id);
        if ($a !== null) {
            $surat = db_connect()->table('pkl_surat')->select('nomor')->where('pengajuan_id', (int) $a['id'])->get()->getRowArray();
            $pkl   = [
                'ajuan_id' => (int) $a['id'], 'kode' => PklPengajuanModel::kode((int) $a['id']), 'status' => (string) $a['status'],
                'peran' => (string) $a['peran'], 'perusahaan' => (string) $a['perusahaan_nama'], 'kota' => $a['perusahaan_kota'] ?? null,
                'nomor_surat' => $surat['nomor'] ?? null, 'disetujui_oleh' => $a['acc_nama'] ?? null,
            ];
        }

        return [
            'siswa'   => $r,
            'bagian'  => $bagian,
            'biodata' => [
                'disahkan_pada' => ! empty($r['biodata_at']) ? self::waktu((string) $r['biodata_at']) : null,
                'kurang'        => BiodataForm::kolomKosong($r),
                'isian'         => $isian === null ? null : [
                    'id' => (int) $isian['id'], 'status' => (string) $isian['status'],
                    'status_label' => ['menunggu' => 'Menunggu verifikasi', 'disetujui' => 'Disetujui', 'perbaikan' => 'Perlu perbaikan'][$isian['status']] ?? (string) $isian['status'],
                    'dikirim_at' => $isian['updated_at'] ?? $isian['created_at'] ?? null,
                ],
            ],
            'pkl'     => $pkl,
            'jejak'   => ['dibuat' => self::waktu((string) ($r['created_at'] ?? '')), 'diubah' => self::waktu((string) ($r['updated_at'] ?? ''))],
        ];
    }

    /** "2010-10-11" → "11 Oktober 2010" (NULL bila kosong/tak sah). */
    public static function tanggal(?string $ymd): ?string
    {
        if ($ymd === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            return null;
        }

        return (int) $m[3] . ' ' . self::BULAN[(int) $m[2]] . ' ' . $m[1];
    }

    /** "2026-10-08 10:23:14" → "8 Oktober 2026, 10.23" (NULL bila kosong). */
    public static function waktu(string $dt): ?string
    {
        $t = $dt !== '' ? strtotime($dt) : false;

        return $t === false ? null : (int) date('j', $t) . ' ' . self::BULAN[(int) date('n', $t)] . ' ' . date('Y, H.i', $t);
    }
}
