<?php

namespace App\Controllers\Api;

use App\Libraries\KelasSayaData;
use App\Libraries\WaliKelas;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API WALI KELAS (2026-11-07) = halaman web "Kelas Saya" (Admin\KelasSaya); isi dari Libraries\KelasSayaData, akses dari
 * Libraries\WaliKelas (akun yang gurunya wali kelas: hanya kelasnya; Admin: semua kelas). Hanya membaca. Kontrak: docs/API-WALI-KELAS.md.
 *
 *   GET wali/kelas          daftar kelas yang boleh dibuka akun ini
 *   GET wali/kelas/{id}     isi satu kelas: ringkasan, siswa (+ PKL, keterlambatan, WA ortu), keterlambatan terbaru, surat
 */
class Wali extends BaseApiController
{
    public function kelas(): ResponseInterface
    {
        $admin = (array) $this->admin();
        $daftar = $this->daftar($admin);
        if (! WaliKelas::siap()) {
            return $this->failure('Fitur wali kelas belum aktif di server (php spark migrate).', 503);
        }

        return $this->ok([
            'admin'  => ($admin['role'] ?? '') === 'admin',
            'kelas'  => $daftar,
            'bagian' => array_map(static fn (string $k, string $v): array => ['kunci' => $k, 'label' => $v], array_keys(KelasSayaData::BAGIAN), KelasSayaData::BAGIAN),
        ], $daftar === [] ? 'Anda belum tercatat sebagai wali kelas. Hubungi Admin sekolah.' : 'Kelas yang Anda walikan.');
    }

    public function detail($id = null): ResponseInterface
    {
        $admin = (array) $this->admin();
        $id    = (int) $id;
        if (! WaliKelas::siap()) {
            return $this->failure('Fitur wali kelas belum aktif di server (php spark migrate).', 503);
        }
        if (! (new WaliKelas())->bolehKelas($admin, $id)) {
            return $this->forbidden('Anda hanya bisa membuka kelas yang Anda walikan.');
        }
        $data = (new KelasSayaData())->kelas($id, ($admin['role'] ?? '') === 'admin' ? '' : (string) ($admin['full_name'] ?? ''));

        return $data === null ? $this->missing('Kelas tidak ditemukan.') : $this->ok($data, 'Isi kelas ' . $data['kelas']['nama'] . '.');
    }

    /** @return list<array{id: int, nama: string, tingkat: string, wali: ?string}> */
    private function daftar(array $admin): array
    {
        if (($admin['role'] ?? '') === 'admin') {
            $rows = db_connect()->table('kelas k')->select('k.id, k.nama_kelas, k.tingkat, g.nama AS wali')
                ->join('guru g', 'g.id = k.wali_kelas_id', 'left')->where('k.deleted_at', null)->get()->getResultArray();
            $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
            usort($rows, static fn (array $a, array $b): int => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9)) ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));

            return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama' => (string) $r['nama_kelas'], 'tingkat' => (string) $r['tingkat'], 'wali' => $r['wali'] ?: null], $rows);
        }

        return array_map(static fn (array $k): array => ['id' => $k['id'], 'nama' => $k['nama_kelas'], 'tingkat' => $k['tingkat'], 'wali' => $k['guru_nama'] ?: null],
            (new WaliKelas())->kelasAkun((int) ($admin['id'] ?? 0)));
    }
}
