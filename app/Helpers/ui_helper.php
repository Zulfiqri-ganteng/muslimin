<?php

/**
 * Partial kit komponen panel admin (rombak tampilan F1; rancangan docs/RENCANA-UPGRADE-TAMPILAN.md). Kelas CSS: bagian
 * "KIT KOMPONEN" di resources/css/admin.css · komponen Alpine: public/assets/js/admin/ui.js · contoh hidup: /admin/ui-kit.
 *
 * Semua partial menerima SATU variabel $ui dan dirender dengan saveData = false. Sebabnya: Config\View::$saveData = true membuat
 * variabel view() terbawa ke view berikutnya (mis. 'aksi' dari kepala halaman ikut muncul di keadaan kosong bila tidak dikirim).
 *
 *   <?= ui_kepala(['judul' => 'Data Guru', 'ket' => '…', 'remah' => [['Data Master'], ['Guru']],
 *                  'aksi' => '<a href="…" class="btn btn-utama">Tambah</a>', 'tab' => [...]]) ?>
 *   <?= ui_tab([['label' => 'Aktif', 'url' => site_url('…'), 'aktif' => true, 'jumlah' => 12], …]) ?>
 *   <?= ui_kosong(['judul' => 'Belum ada data guru', 'teks' => '…', 'ikon' => 'users', 'aksi' => '<a class="btn …">…</a>']) ?>
 *   <?= ui_peringatan('sukses', 'Data tersimpan.') ?>                       jenis: info | sukses | galat | awas | netral
 *   <?= ui_peringatan('galat', ['Baris 1 kosong', 'Baris 2 ganda'], ['judul' => 'Impor gagal', 'tutup' => true]) ?>
 *   <?= ui_dialog_buka(['nama' => 'tambah-guru', 'judul' => 'Tambah Guru', 'ukuran' => 'lg']) ?>
 *       <form …><div class="dialog-isi">…</div><div class="dialog-kaki">…</div></form>
 *   <?= ui_dialog_tutup() ?>                                                 pembuka: <button data-buka-dialog="tambah-guru">
 *   <?= ui_laci_buka(['nama' => 'filter', 'judul' => 'Saring data']) ?> … <?= ui_laci_tutup() ?>
 *   <?= ui_toast() ?> dan <?= ui_konfirmasi() ?>                              sekali per halaman (kelak di layout, F3)
 *
 * 'aksi' & 'html' = HTML apa adanya (susun dari kelas kit; teks dari pengguna tetap di-esc() oleh pemanggil).
 */
if (! function_exists('ui_partial')) {
    /** @param array<string, mixed> $ui */
    function ui_partial(string $nama, array $ui = []): string
    {
        return view('admin/partials/ui/' . $nama, ['ui' => $ui], ['saveData' => false]);
    }

    /**
     * Kepala halaman: remah roti → judul (+ keterangan) dengan tombol aksi di kanan → tab.
     *
     * @param array{judul: string, ket?: string, remah?: list<array{0: string, 1?: string|null}>, aksi?: string, tab?: list<array<string, mixed>>} $opsi
     */
    function ui_kepala(array $opsi): string
    {
        return ui_partial('kepala', $opsi);
    }

    /**
     * Remah roti (breadcrumb). "Beranda" otomatis di depan; butir terakhir = halaman ini (tanpa tautan).
     *
     * @param list<array{0: string, 1?: string|null}> $remah [label, url|null]
     */
    function ui_remah(array $remah): string
    {
        return ui_partial('remah', ['remah' => $remah]);
    }

    /**
     * Tab berupa tautan (pindah halaman / ?tab=…). Tab di sisi peramban: komponen Alpine "tab" (lihat ui.js).
     *
     * @param list<array{label: string, url: string, aktif?: bool, jumlah?: int|string|null, ikon?: string}> $tab
     */
    function ui_tab(array $tab, string $label = 'Tab halaman'): string
    {
        return ui_partial('tab', ['tab' => $tab, 'label' => $label]);
    }

    /** @param array{judul?: string, teks?: string, ikon?: string, aksi?: string} $opsi */
    function ui_kosong(array $opsi = []): string
    {
        return ui_partial('kosong', $opsi);
    }

    /**
     * @param string|list<string>                                     $pesan teks (di-esc) atau daftar teks
     * @param array{judul?: string, html?: string, tutup?: bool}      $opsi  html = isi HTML apa adanya (menggantikan $pesan)
     */
    function ui_peringatan(string $jenis, string|array $pesan, array $opsi = []): string
    {
        return ui_partial('peringatan', ['jenis' => $jenis, 'pesan' => $pesan] + $opsi);
    }

    /** @param array{nama: string, judul: string, ukuran?: 'sm'|'lg'|'xl', awalBuka?: bool} $opsi */
    function ui_dialog_buka(array $opsi): string
    {
        return ui_partial('dialog_buka', $opsi);
    }

    /** Penutup ui_dialog_buka(): panel + akar dialog. */
    function ui_dialog_tutup(): string
    {
        return "</div>\n</div>\n";
    }

    /** @param array{nama: string, judul: string, lebar?: 'lg'|'xl', awalBuka?: bool} $opsi */
    function ui_laci_buka(array $opsi): string
    {
        return ui_partial('laci_buka', $opsi);
    }

    /** Penutup ui_laci_buka(): panel + akar laci. */
    function ui_laci_tutup(): string
    {
        return "</div>\n</div>\n";
    }

    /** @param list<array{pesan: string, jenis?: string}> $awal toast yang langsung tampil (mis. pesan sukses dari server) */
    function ui_toast(array $awal = []): string
    {
        return ui_partial('toast', ['awal' => $awal]);
    }

    function ui_konfirmasi(): string
    {
        return ui_partial('konfirmasi');
    }
}
