<?php

namespace App\Controllers\Admin;

/**
 * Bagian bersama hapus massal halaman admin (permintaan user 2026-11-22: setiap daftar yang bisa dihapus wajib punya
 * "Hapus terpilih" + "Hapus semua"). Pasangan tampilannya: ui_crud_alat([... 'massalUrl', 'massalHidden' => saringan,
 * 'massalTotal' ...]) + kotak centang .js-check-all / .row-check di tabel (delegasi global admin/app.js).
 *
 * Formulir .js-bulk-form mengirim mode=all ("Hapus semua") ATAU ids[] ("Hapus terpilih"), ditambah isian saringan halaman
 * (massalHidden). "Hapus semua" = semua baris yang COCOK DENGAN SARINGAN saat itu di semua halaman, bukan seluruh tabel.
 *
 * Pemakaian di controller:
 *   [$mode, $ids] = $this->idHapusMassal(
 *       fn () => array_column($this->daftarTersaring(...)->select('x.id AS id_hapus')->findAll(), 'id_hapus'),
 *       fn (array $ids) => array_column($this->model->select('id')->whereIn('id', $ids)->findAll(), 'id')
 *   );
 *   … hapus per baris dengan aturan yang SAMA seperti tombol Hapus satuan …
 *   return redirect()->to($this->alamatDaftarMassal(site_url('admin/…'), ['q', 'status']))->with(…);
 */
trait HapusMassal
{
    /**
     * Id yang akan dihapus dari kiriman hapus massal.
     *
     * @param callable(): array           $semua id seluruh baris yang cocok saringan (mode "Hapus semua")
     * @param callable(list<int>): array  $ada   saring id centangan ke id yang benar-benar ada (mode "Hapus terpilih")
     *
     * @return array{0: string, 1: list<int>} ['semua'|'terpilih', id unik > 0]
     */
    protected function idHapusMassal(callable $semua, callable $ada): array
    {
        $bersih = static fn (array $ids): array => array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $n): bool => $n > 0)));

        if ((string) $this->request->getPost('mode') === 'all') {
            return ['semua', $bersih($semua())];
        }
        $ids = $bersih((array) $this->request->getPost('ids'));

        return ['terpilih', $ids === [] ? [] : $bersih($ada($ids))];
    }

    /**
     * Alamat daftar untuk kembali sesudah hapus massal, beserta saringan yang ikut terkirim (isian massalHidden) — supaya
     * pengguna tetap melihat daftar yang sama.
     *
     * @param list<string> $kunci nama isian saringan, mis. ['q', 'status']
     */
    protected function alamatDaftarMassal(string $alamat, array $kunci): string
    {
        $p = [];
        foreach ($kunci as $k) {
            $v = trim((string) $this->request->getPost($k));
            if ($v !== '') {
                $p[$k] = $v;
            }
        }

        return $alamat . ($p !== [] ? '?' . http_build_query($p) : '');
    }

    /** Pesan galat seragam bila tidak ada yang dipilih / daftar sudah kosong. */
    protected function pesanTakAdaDipilih(string $satuan): string
    {
        return 'Tidak ada ' . $satuan . ' yang dipilih untuk dihapus.';
    }
}
