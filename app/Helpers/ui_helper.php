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

    /** @param array{nama: string, judul: string, ukuran?: 'sm'|'lg'|'xl'|'penuh', awalBuka?: bool} $opsi */
    function ui_dialog_buka(array $opsi): string
    {
        return ui_partial('dialog_buka', $opsi);
    }

    /** Penutup ui_dialog_buka(): panel + akar dialog. */
    function ui_dialog_tutup(): string
    {
        return "</div>\n</div>\n";
    }

    /**
     * Menu "Lainnya" (U1 rapi tampilan): aksi jarang/berbahaya di balik satu tombol. Butir: label, ikon?, href? | dialog?, bahaya?, atr?.
     *
     * @param list<array<string, mixed>>                      $butir
     * @param array{label?: string, kanan?: bool, kecil?: bool} $opsi
     */
    function ui_menu_lain(array $butir, array $opsi = []): string
    {
        return ui_partial('menu_lain', ['butir' => $butir] + $opsi);
    }

    /** Bagian yang bisa dilipat (<details>, tanpa JS). @param array{judul: string, ringkas?: string, buka?: bool, id?: string, ikon?: string} $opsi */
    function ui_lipat_buka(array $opsi): string
    {
        return ui_partial('lipat_buka', $opsi);
    }

    /** Penutup ui_lipat_buka(). */
    function ui_lipat_tutup(): string
    {
        return "</div>\n</details>\n";
    }

    /** Bilah aksi untuk yang dicentang (di dalam x-data="daftarCentang"). @param array{label?: string} $opsi */
    function ui_bilah_pilih_buka(array $opsi = []): string
    {
        return ui_partial('bilah_pilih_buka', $opsi);
    }

    /** Penutup ui_bilah_pilih_buka(): + tombol "Batal pilih" (melepas semua centang). */
    function ui_bilah_pilih_tutup(): string
    {
        return '<button type="button" class="btn btn-teks btn-sm" @click="pilihSemuaCentang()">' . ikon('close', 'h-4 w-4') . "Batal pilih</button>\n</div>\n";
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

    /*
     * (F5) Halaman data / CRUD — pola template content/crud/users.html. Layout: section 'tata' = crud (kepala putih selebar
     * layar), section 'bantuan' = panduan halaman. Isi: <div x-data="masterList" data-base="…" data-defaults="…">
     *   ui_crud_alat([...])  → tabel .tabel-crud (aksi baris: ui_crud_aksi) → ui_crud_kaki([...])
     *   ui_crud_form_buka(['benda' => 'Jurusan']) … kolom isian … ui_crud_form_tutup()   ui_crud_impor([...])
     * </div>
     */

    /** Bilah alat: cari, filter, "Tampilkan N", Cari/Reset, hapus massal | Tambah, Import, Export. Lihat partial ui/crud_alat. */
    function ui_crud_alat(array $opsi): string
    {
        return ui_partial('crud_alat', $opsi);
    }

    /** Tombol per baris: Detail (opsional), Ubah (biru), Hapus (merah + konfirmasi). Lihat partial ui/crud_aksi. */
    function ui_crud_aksi(array $opsi): string
    {
        return ui_partial('crud_aksi', $opsi);
    }

    /** Bilah bawah yang menempel: info jumlah + paginasi. Lihat partial ui/crud_kaki. */
    function ui_crud_kaki(array $opsi): string
    {
        return ui_partial('crud_kaki', $opsi);
    }

    /** Dialog Import Excel (keadaan importOpen milik masterList). Lihat partial ui/crud_impor. */
    function ui_crud_impor(array $opsi): string
    {
        return ui_partial('crud_impor', $opsi);
    }

    /** Pembuka dialog formulir Tambah/Ubah (keadaan open/mode milik masterList). Lihat partial ui/crud_form_buka. */
    function ui_crud_form_buka(array $opsi): string
    {
        return ui_partial('crud_form_buka', $opsi);
    }

    /** Penutup dialog formulir: Batal + Simpan. */
    function ui_crud_form_tutup(array $opsi = []): string
    {
        return ui_partial('crud_form_tutup', $opsi);
    }
}
