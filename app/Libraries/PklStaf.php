<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;

/**
 * Pembantu sisi staf yang dipakai bersama web (Admin\Pkl) dan API Android (Api\Pkl).
 */
final class PklStaf
{
    /**
     * Pengaju + teman: aktif, belum terkunci di ajuan lain (selain ajuan ini sendiri). Staf boleh memilih siswa dari
     * tingkat mana pun (riwayat lama), jadi tingkat TIDAK dibatasi di sini.
     *
     * @param list<int>          $temanIds
     * @param array<int, string> $hpTeman  id siswa → HP teman yang sudah dirapikan (PklForm)
     *
     * @return array{0: list<array{siswa_id:int, kelas_id:?int, peran:string, hp?:?string}>, 1: array<string, string>}
     */
    public static function periksaAnggota(int $pengajuId, array $temanIds, ?int $ajuanSendiri, array $hpTeman = []): array
    {
        $model = new PklPengajuanModel();
        $info  = $model->siswaUntukDipilih(array_merge([$pengajuId], $temanIds));
        $galat = [];
        $baris = [];

        $pg = $info[$pengajuId] ?? null;
        if ($pg === null || $pg['status'] !== 'aktif') {
            $galat['siswa_id'] = 'Pilih siswa pengaju yang masih aktif.';
        } elseif ($pg['aktif_di'] !== null && (int) $pg['aktif_di'] !== (int) $ajuanSendiri) {
            $galat['siswa_id'] = $pg['nama'] . ' sudah punya ajuan PKL aktif (' . PklPengajuanModel::kode((int) $pg['aktif_di']) . ').';
        } else {
            $baris[] = ['siswa_id' => $pengajuId, 'kelas_id' => (int) $pg['kelas_id'] ?: null, 'peran' => 'pengaju'];
        }

        $masalah = [];
        foreach ($temanIds as $tid) {
            $s = $info[$tid] ?? null;
            if ($tid === $pengajuId) {
                $masalah[] = 'pengaju tak perlu dipilih sebagai teman';
            } elseif ($s === null || $s['status'] !== 'aktif') {
                $masalah[] = 'ada siswa yang tidak ditemukan/tidak aktif';
            } elseif ($s['aktif_di'] !== null && (int) $s['aktif_di'] !== (int) $ajuanSendiri) {
                $masalah[] = $s['nama'] . ' (sudah punya ajuan ' . PklPengajuanModel::kode((int) $s['aktif_di']) . ')';
            } else {
                $baris[] = ['siswa_id' => $tid, 'kelas_id' => (int) $s['kelas_id'] ?: null, 'peran' => 'teman', 'hp' => $hpTeman[$tid] ?? null];
            }
        }
        if ($masalah !== []) {
            $galat['teman'] = 'Teman berikut tidak bisa ditambahkan: ' . implode('; ', $masalah) . '.';
        }

        return [$baris, $galat];
    }
}
