<?php

namespace App\Libraries;

use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AbsensiSnapshotModel;
use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JadwalPiketModel;

/**
 * Muat / simpan / batalkan absensi SATU tanggal — dipakai bersama web
 * (Admin\Absensi) dan API (Api\Admin\Absensi) supaya perilaku keduanya sama.
 */
class AbsensiHarian
{
    private const HARI_NAMA = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    /**
     * Simpan absensi satu tanggal. Setiap bagian hanya disentuh bila dikirim:
     *   rows   — sesi mengajar (lihat AbsensiGuruModel::syncDate)
     *   kerja  — daftar kehadiran kerja LENGKAP (sinkron: yang tak dikirim dihapus)
     *   belum  — daftar guru_id belum hadir untuk `shift` (sinkron per shift)
     * Tanggal selalu ditandai tercatat agar masuk rekap.
     *
     * @param array{rows?:array|null,kerja?:array|null,shift?:string|null,belum?:array|null} $in
     */
    public static function simpan(string $tanggal, array $in, ?int $adminId): void
    {
        if (is_array($in['rows'] ?? null)) {
            (new AbsensiGuruModel())->syncDate($tanggal, $in['rows'], $adminId);
        }
        if (is_array($in['kerja'] ?? null)) {
            (new AbsensiKerjaModel())->syncDate($tanggal, $in['kerja'], $adminId);
        }
        if (is_array($in['belum'] ?? null) && in_array($in['shift'] ?? null, AbsensiBelumModel::SHIFTS, true)) {
            (new AbsensiBelumModel())->syncShift($tanggal, $in['shift'], $in['belum'], $adminId);
        }
        (new AbsensiHariModel())->mark($tanggal, $adminId);

        // Salinan jadwal hari itu: rekap bulan lalu tak berubah bila jadwal diubah kelak.
        $hari = (new HariModel())->byWeekday((int) date('N', strtotime($tanggal)));
        $sesi = $hari && (int) $hari['aktif'] === 1 ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
        (new AbsensiSnapshotModel())->simpan($tanggal, $sesi);
    }

    /**
     * Data input absensi satu tanggal — sumber tunggal halaman web & API.
     *
     * Sesi dikelompokkan per ORANG: data guru ganda (guru.induk_id) digabung ke
     * data utamanya; guru yang tidak ikut absensi (guru.ikut_absensi = 0)
     * dibuang. Tiap sesi tetap membawa guru_id aslinya untuk disimpan.
     *
     * @return array{hariId:int|null,namaHari:string,hariAktif:bool,recorded:bool,total:int,
     *               grup:list<array>,kerja:list<array>,saran:list<array>,guruOptions:list<array>,
     *               belum:array,piket:array,jabatanMap:array}
     */
    public static function muat(string $tanggal): array
    {
        $ts        = strtotime($tanggal);
        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $namaHari  = $hari['nama'] ?? (self::HARI_NAMA[(int) date('N', $ts)] ?? '');
        $hariAktif = $hari && (int) $hari['aktif'] === 1;
        $hariId    = $hari ? (int) $hari['id'] : null;

        $sessions = $hariAktif ? (new JadwalModel())->sessionsForHari($hariId) : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);
        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);
        $peta     = GuruModel::petaOrang();
        $keluar   = GuruModel::tidakIkutAbsensi();
        $orang    = static fn (int $gid): int => $peta[$gid] ?? $gid;

        // Nama & kode per guru (termasuk terhapus agar data lama tetap bernama).
        $guruInfo = [];
        foreach ((new GuruModel())->withDeleted()->select('id, kode_guru, nama')->findAll() as $g) {
            $guruInfo[(int) $g['id']] = $g;
        }

        $grup  = [];
        $total = 0;
        foreach ($sessions as $s) {
            $gid = (int) $s['guru_id'];
            $oid = $orang($gid);
            if (isset($keluar[$oid]) || isset($keluar[$gid])) {
                continue;
            }
            $ex              = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $s['status']     = $ex['status'] ?? 'hadir';
            $s['jam_masuk']  = $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : '';
            $s['keterangan'] = $ex['keterangan'] ?? '';
            $s['jam_shift']  = $s['jam_shift'] ?? $s['shift'] ?? 'pagi';

            if (! isset($grup[$oid])) {
                $info        = $guruInfo[$oid] ?? ['nama' => $s['guru_nama'], 'kode_guru' => $s['kode_guru']];
                $grup[$oid]  = ['guru_id' => $oid, 'nama' => $info['nama'], 'kode' => $info['kode_guru'], 'sesi' => []];
            }
            $grup[$oid]['sesi'][] = $s;
            $total++;
        }
        uasort($grup, static fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));
        foreach ($grup as &$g) {
            usort($g['sesi'], static fn ($a, $b) => strcmp((string) $a['waktu_mulai'], (string) $b['waktu_mulai']));
        }
        unset($g);

        $kerja       = (new AbsensiKerjaModel())->forDate($tanggal);
        $guruJabatan = new GuruJabatanModel();
        $jabatanMap  = $guruJabatan->mapByGuru();
        $piket       = $hariId ? (new JadwalPiketModel())->forHari($hariId) : ['pagi' => [], 'siang' => []];
        foreach ($piket as $sh => $ids) {
            $piket[$sh] = array_values(array_unique(array_filter(
                array_map($orang, $ids),
                static fn ($id) => ! isset($keluar[$id])
            )));
        }

        // Pilihan guru (tambah kehadiran kerja / tandai belum hadir): hanya data
        // utama yang ikut absensi.
        $guruOptions = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $id = (int) $g['id'];
            if (! isset($peta[$id]) && ! isset($keluar[$id])) {
                $guruOptions[] = ['id' => $id, 'nama' => $g['nama'], 'kode_guru' => $g['kode_guru'] ?? null];
            }
        }
        $namaOpsi = array_column($guruOptions, null, 'id');

        // SARAN isian Kehadiran Kerja (belum tersimpan; hanya sebelum tanggal
        // tercatat agar guru yang sengaja dihapus admin tidak muncul lagi):
        //  - jabatan struktural / wajib hadir harian (TU) tanpa sesi hari itu → penuh
        //  - guru piket pada shift yang tidak ia ajar → shift piketnya
        $saran = [];
        if (! $recorded) {
            $sudahAda = array_flip(array_map($orang, array_column($kerja, 'guru_id')));
            $cakupan  = [];
            foreach ($guruJabatan->guruHadirHarianIds() as $gid) {
                $oid = $orang($gid);
                if (! isset($grup[$oid])) {
                    $cakupan[$oid] = ['pagi' => true, 'siang' => true];
                }
            }
            foreach ($piket as $sh => $ids) {
                foreach ($ids as $oid) {
                    $ajarShift = false;
                    foreach ($grup[$oid]['sesi'] ?? [] as $s) {
                        $ajarShift = $ajarShift || $s['jam_shift'] === $sh;
                    }
                    if (! $ajarShift) {
                        $cakupan[$oid][$sh] = true;
                    }
                }
            }
            foreach ($cakupan as $oid => $sh) {
                if (isset($sudahAda[$oid]) || ! isset($namaOpsi[$oid])) {
                    continue;
                }
                $saran[] = [
                    'guru_id'    => $oid,
                    'nama'       => $namaOpsi[$oid]['nama'],
                    'kode_guru'  => $namaOpsi[$oid]['kode_guru'],
                    'jabatan'    => implode(', ', array_column($jabatanMap[$oid] ?? [], 'nama')),
                    'status'     => 'hadir',
                    'shift'      => count($sh) === 2 ? 'penuh' : array_key_first($sh),
                    'jam_masuk'  => '',
                    'keterangan' => '',
                ];
            }
            usort($saran, static fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));
        }

        return [
            'hariId'      => $hariId,
            'namaHari'    => $namaHari,
            'hariAktif'   => $hariAktif,
            'recorded'    => $recorded,
            'total'       => $total,
            'grup'        => array_values($grup),
            'kerja'       => $kerja,
            'saran'       => $saran,
            'guruOptions' => $guruOptions,
            'belum'       => (new AbsensiBelumModel())->forDate($tanggal),
            'piket'       => $piket,
            'jabatanMap'  => $jabatanMap,
        ];
    }

    /** Batalkan pencatatan satu hari: hapus registry + semua tandaan hari itu. */
    public static function batalkan(string $tanggal): void
    {
        (new AbsensiGuruModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiKerjaModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiBelumModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiSnapshotModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiHariModel())->unmark($tanggal);
    }
}
