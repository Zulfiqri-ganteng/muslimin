<?php
/**
 * Toolbar standar halaman master data — dipakai SEMUA halaman master agar identik.
 *
 * Tata letak 2 baris (anti berantakan di layar sempit):
 *  - Baris 1: kolom pencarian (lebar penuh) + filter + Tampilkan + Cari + Reset
 *  - Baris 2: tombol aksi rata kanan (Tambah, Import | Export, Hapus Terpilih*, Hapus Semua*)
 *    *) Hapus Terpilih muncul saat ada baris tercentang; Hapus Semua muncul saat SEMUA tercentang.
 *
 * Variabel (semua opsional kecuali $baseUrl):
 *
 * @var string      $baseUrl           URL dasar modul, mis. site_url('admin/master/guru')
 * @var string|null $searchPlaceholder Placeholder kolom cari; null = tanpa form cari
 * @var string      $q                 Kata kunci pencarian aktif
 * @var array       $filters           Daftar select filter: [['name','value','all','options'=>[val=>label]]]
 * @var int         $per               Jumlah baris per halaman aktif
 * @var string|null $leftHtml          HTML sisi kiri saat tanpa form cari (mis. tab shift / pemilih kelas)
 * @var bool        $showAdd           Tampilkan tombol Tambah (default true)
 * @var bool        $showImport        Tampilkan tombol Import (default true)
 * @var string|null $exportUrl         URL tombol Export; null = sembunyikan
 * @var string      $exportTitle       Tooltip tombol Export
 * @var string|null $bulkUrl           URL bulk-delete; null = tanpa Hapus Terpilih/Semua
 * @var array       $bulkHidden        Input hidden tambahan pada form bulk, mis. ['shift' => 'pagi']
 */
$searchPlaceholder = $searchPlaceholder ?? null;
$q           = $q ?? '';
$filters     = $filters ?? [];
$per         = $per ?? 10;
$leftHtml    = $leftHtml ?? null;
$showAdd     = $showAdd ?? true;
$showImport  = $showImport ?? true;
$exportUrl   = $exportUrl ?? null;
$exportTitle = $exportTitle ?? 'Keluarkan semua data ke file Excel';
$bulkUrl     = $bulkUrl ?? null;
$bulkHidden  = $bulkHidden ?? [];
$bulkLabel   = $bulkLabel ?? 'data';   // nama entitas pada teks konfirmasi hapus
$bulkWarn    = $bulkWarn ?? '';        // kalimat peringatan tambahan (opsional)

$adaFilterAktif = $q !== '';
foreach ($filters as $f) {
    if (($f['value'] ?? '') !== '') {
        $adaFilterAktif = true;
    }
}

// ---- Blok tombol aksi (dipakai di kedua varian tata letak) ----
ob_start(); ?>
    <?php if ($showAdd): ?>
        <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah
        </button>
    <?php endif; ?>
    <?php if ($showImport): ?>
        <button @click="importOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Import
        </button>
    <?php endif; ?>

    <?php if (($showAdd || $showImport) && $exportUrl): ?>
        <span class="hidden sm:block w-px h-6 bg-slate-200 mx-0.5"></span>
    <?php endif; ?>

    <?php if ($exportUrl): ?>
        <a href="<?= $exportUrl ?>" title="<?= esc($exportTitle, 'attr') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3.5 py-2.5 transition">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
            Export
        </a>
    <?php endif; ?>

    <?php if ($bulkUrl): ?>
        <!-- Muncul hanya saat ada baris tercentang (ditangani global di assets/js/admin/app.js) -->
        <button type="button" data-bulk="selected" class="hidden inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus Terpilih (<span class="js-bulk-count">0</span>)
        </button>
        <!-- Muncul hanya saat SEMUA baris tercentang (select all) -->
        <button type="button" data-bulk="all" class="hidden inline-flex items-center gap-1.5 rounded-lg border text-sm font-semibold px-3.5 py-2.5 transition bg-white text-red-600 border-red-200 hover:bg-red-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus Semua
        </button>
    <?php endif; ?>
<?php $actionsHtml = ob_get_clean(); ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <?php if ($searchPlaceholder !== null): ?>
        <!-- Baris 1: pencarian & filter (lebar penuh) -->
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="<?= esc($searchPlaceholder) ?>"
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <?php foreach ($filters as $f): ?>
                <select name="<?= esc($f['name'], 'attr') ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                    <option value=""><?= esc($f['all']) ?></option>
                    <?php foreach ($f['options'] as $val => $label): ?>
                        <option value="<?= esc($val, 'attr') ?>" <?= (string) ($f['value'] ?? '') === (string) $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endforeach; ?>
            <select name="per" data-autosubmit title="Jumlah data per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition shrink-0">Cari</button>
            <?php if ($adaFilterAktif): ?>
                <a href="<?= $baseUrl ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition text-center shrink-0">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Baris 2: tombol aksi (rata kanan) -->
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 mt-3 pt-3">
            <?= $actionsHtml ?>
        </div>
    <?php else: ?>
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <?php if ($leftHtml !== null): ?>
                <div class="flex flex-1 items-center gap-2 min-w-0"><?= $leftHtml ?></div>
            <?php endif; ?>
            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                <?= $actionsHtml ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($bulkUrl): ?>
        <!-- form tersembunyi untuk hapus massal -->
        <form method="post" action="<?= $bulkUrl ?>" class="hidden js-bulk-form"
              data-label="<?= esc($bulkLabel, 'attr') ?>" data-warn="<?= esc($bulkWarn !== '' ? ' ' . $bulkWarn : '', 'attr') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="">
            <?php foreach ($bulkHidden as $name => $val): ?>
                <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($val, 'attr') ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
</div>
