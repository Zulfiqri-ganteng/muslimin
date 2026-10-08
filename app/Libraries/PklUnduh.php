<?php

namespace App\Libraries;

/**
 * Satu pintu "unduh surat PKL" untuk web (Admin\PklBerkas) dan API Android (Api\Pkl), supaya aturannya SAMA:
 *
 *   1. yang boleh hanya peran berhak 'surat' (PklHak, diatur Admin) — penjagaan terakhir selain filter rute;
 *   2. biaya divalidasi DULU (PklBiaya::rencana): tiap siswa wajib punya minimal satu catatan, kalau tidak
 *      berkas tidak dibuat dan nomor surat tidak terpakai;
 *   3. surat dibangun (PklSurat::bangun — nomor diterbitkan bila belum ada);
 *   4. baru setelah berkas jadi, biaya dicatat (satu transaksi) untuk ajuan yang benar-benar terbit.
 *
 * Kembalian: ['ok' => true, 'biner', 'nama', 'jumlah', 'ids', 'catat' => ['item','total']]
 *        atau ['ok' => false, 'http' => 403|422|500, 'kode' => 'dilarang'|'biaya'|'surat'|'catat', 'pesan', 'galat' => []].
 */
final class PklUnduh
{
    /**
     * @param list<int>            $ids
     * @param array<string, mixed> $in      kiriman biaya (lihat PklBiaya)
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array<string, mixed>
     */
    public static function proses(array $ids, string $tanggal, array $in, array $konteks): array
    {
        $peran = (string) ($konteks['peran'] ?? '');
        if (! HakAkses::bolehPkl($peran, 'surat')) {
            return ['ok' => false, 'http' => 403, 'kode' => 'dilarang', 'galat' => [],
                'pesan' => 'Akun ' . HakAkses::label($peran) . ' tidak punya hak mengunduh surat PKL. Hak ini diatur Admin di PKL → Hak Akses.'];
        }

        $biaya  = new PklBiaya();
        $rencana = $biaya->rencana($ids, $in);
        if (! $rencana['ok']) {
            return ['ok' => false, 'http' => 422, 'kode' => 'biaya', 'galat' => $rencana['galat'], 'pesan' => implode(' ', array_values(array_unique($rencana['galat'])))];
        }

        $hasil = (new PklSurat())->bangun($ids, $konteks, $tanggal);
        if (! $hasil['ok']) {
            return ['ok' => false, 'http' => 422, 'kode' => 'surat', 'galat' => [], 'pesan' => (string) ($hasil['pesan'] ?? 'Surat gagal dibuat.')];
        }

        $terbit  = array_map('intval', $hasil['ids'] ?? []);
        $dicatat = array_filter($rencana['rencana'], static fn (array $r) => in_array((int) $r['ajuan_id'], $terbit, true));
        $catat   = $biaya->catat($dicatat, $konteks);
        if (! $catat['ok']) {
            return ['ok' => false, 'http' => 500, 'kode' => 'catat', 'galat' => [], 'pesan' => (string) ($catat['pesan'] ?? 'Catatan biaya gagal disimpan.')];
        }

        return $hasil + ['catat' => ['item' => $catat['item'], 'total' => $catat['total']]];
    }
}
