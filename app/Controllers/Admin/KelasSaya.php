<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\KelasSayaData;
use App\Libraries\WaliKelas;

/**
 * KELAS SAYA — halaman wali kelas (2026-11-07): pilih kelas + tab Siswa (WA orang tua) / PKL / Keterlambatan / Surat. Isi dari
 * Libraries\KelasSayaData — SAMA dengan API wali/kelas/{id}. Hanya melihat.
 * Terbuka bagi semua akun di penjaga rute (Config\Peran::$umum), isinya dijaga Libraries\WaliKelas: hanya kelas yang diwalikan akun
 * ini (lewat Kelola Akun → guru), Admin boleh semua kelas.
 */
class KelasSaya extends BaseController
{
    public function index()
    {
        $admin = (array) session('admin');
        $wali  = new WaliKelas();
        $kelas = ($admin['role'] ?? '') === 'admin' ? $this->semuaKelas() : $wali->kelasAkun((int) ($admin['id'] ?? 0));
        $pilih = (int) $this->request->getGet('kelas');
        $ids   = array_column($kelas, 'id');
        if (! in_array($pilih, $ids, true)) {
            $pilih = (int) ($ids[0] ?? 0);
        }
        if ($pilih > 0 && ! $wali->bolehKelas($admin, $pilih)) { // jaga ganda
            $pilih = 0;
        }

        $tab = (string) $this->request->getGet('tab');

        return view('admin/kelas_saya/index', [
            'title' => 'Kelas Saya',
            'kelas' => $kelas,
            'pilih' => $pilih,
            'data'  => $pilih > 0 ? (new KelasSayaData())->kelas($pilih, ($admin['role'] ?? '') === 'admin' ? '' : (string) ($admin['full_name'] ?? '')) : null,
            'tab'   => isset(KelasSayaData::BAGIAN[$tab]) ? $tab : 'siswa',
            'admin' => ($admin['role'] ?? '') === 'admin',
            'siap'  => WaliKelas::siap(),
        ]);
    }

    /** @return list<array{id: int, nama_kelas: string, tingkat: string, guru_id: int, guru_nama: string}> */
    private function semuaKelas(): array
    {
        $rows = db_connect()->table('kelas k')->select('k.id, k.nama_kelas, k.tingkat, g.id AS guru_id, g.nama AS guru_nama')
            ->join('guru g', 'g.id = k.wali_kelas_id', 'left')->where('k.deleted_at', null)->get()->getResultArray();
        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($rows, static fn (array $a, array $b): int => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9)) ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama_kelas' => (string) $r['nama_kelas'], 'tingkat' => (string) $r['tingkat'],
            'guru_id' => (int) ($r['guru_id'] ?? 0), 'guru_nama' => (string) ($r['guru_nama'] ?? '')], $rows);
    }
}
