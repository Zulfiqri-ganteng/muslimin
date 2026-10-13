<?php

namespace App\Commands;

use App\Libraries\HakPeran;

/**
 * Pembantu uji (dipakai perintah dev:uji-*): menyimpan isi tabel `peran` sebelum uji, menyetel peran bawaan sistem (Admin,
 * Operator Sekolah, Waka Hubin) ke aturan bawaan selama uji, lalu memulihkan isi semula di akhir — jadi uji TIDAK PERNAH
 * mengubah hak akses yang diatur Admin di database. Tabel belum ada (belum migrate) → semua langkah dilewati.
 * Rancangan hak akses: docs/DESAIN-HAK-AKSES.md.
 */
trait UjiPeranAman
{
    /** @var list<array<string, mixed>>|null */
    private ?array $peranAsliUji = null;

    private function simpanPeranAsli(): void
    {
        $db = db_connect();
        if ($this->peranAsliUji !== null || ! $db->tableExists('peran')) {
            return;
        }
        $this->peranAsliUji = $db->table('peran')->get()->getResultArray();
    }

    /** Peran bawaan sistem → aturan bawaan (menu & hak); peran buatan Admin dibiarkan. */
    private function setelPeranBawaan(): void
    {
        $db = db_connect();
        HakPeran::lupakan();
        if (! $db->tableExists('peran')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (HakPeran::bawaan() as $kode => $p) {
            $data = [
                'label' => $p['label'], 'beranda' => $p['beranda'], 'api' => $p['api'] ? 1 : 0,
                'menu'  => json_encode($p['menu'], JSON_FORCE_OBJECT), 'hak' => json_encode(array_values($p['hak'])), 'updated_at' => $now,
            ];
            if ($db->table('peran')->where('kode', $kode)->countAllResults() > 0) {
                $db->table('peran')->where('kode', $kode)->update($data);
            } else {
                $db->table('peran')->insert($data + ['kode' => $kode, 'ringkas' => $p['ringkas'], 'sistem' => 1, 'urut' => $p['urut'], 'created_at' => $now]);
            }
        }
        HakPeran::lupakan();
    }

    private function pulihkanPeran(): void
    {
        $db = db_connect();
        if ($this->peranAsliUji !== null && $db->tableExists('peran')) {
            $db->table('peran')->truncate();
            foreach ($this->peranAsliUji as $r) {
                $db->table('peran')->insert($r);
            }
            $this->peranAsliUji = null;
        }
        HakPeran::lupakan();
    }
}
