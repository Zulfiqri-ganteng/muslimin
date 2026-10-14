<?php
/**
 * Bilah alat halaman data (kit F5, pola template content/crud/users.html): kiri = kotak cari ber-ikon + filter + "Tampilkan N" +
 * Cari/Reset + tombol hapus massal (muncul saat ada baris dicentang); kanan = Tambah (biru, ikon +), Import & Export (putih, ikon).
 * Dipakai di dalam komponen Alpine "masterList" (Tambah → openAdd(), Import → bukaImpor()). Menyambung dengan kepala halaman mode
 * CRUD (layout: section 'tata' = crud). Fitur sama dengan toolbar master lama (admin/master/partials/toolbar.php).
 * Panggil: ui_crud_alat([...]).
 *
 * @var array{
 *   baseUrl: string, cari?: string|null, q?: string, filter?: list<array{name: string, value?: string, all: string, options: array<string, string>}>,
 *   per?: int, kiriHtml?: string|null, tambah?: bool, labelTambah?: string, impor?: bool, eksporUrl?: string|null, eksporTitle?: string,
 *   tambahanHtml?: string, massalUrl?: string|null, massalHidden?: array<string, string>, massalLabel?: string, massalPeringatan?: string,
 *   filterLangsung?: bool
 * } $ui
 */
$baseUrl     = (string) ($ui['baseUrl'] ?? '');
$cari        = $ui['cari'] ?? null;
$q           = (string) ($ui['q'] ?? '');
$filter      = $ui['filter'] ?? [];
$per         = (int) ($ui['per'] ?? 10);
$kiriHtml    = $ui['kiriHtml'] ?? null;
$tambah      = (bool) ($ui['tambah'] ?? true);
$labelTambah = (string) ($ui['labelTambah'] ?? 'Tambah');
$impor       = (bool) ($ui['impor'] ?? true);
$eksporUrl   = $ui['eksporUrl'] ?? null;
$eksporTitle = (string) ($ui['eksporTitle'] ?? 'Keluarkan semua data ke file Excel');
$massalUrl   = $ui['massalUrl'] ?? null;
$langsung    = (bool) ($ui['filterLangsung'] ?? false);

$adaFilter = $q !== '';
foreach ($filter as $f) {
    if ((string) ($f['value'] ?? '') !== '') {
        $adaFilter = true;
    }
}
?>
<div class="alat-crud">
    <div class="alat-crud-baris">
        <div class="alat-crud-cari">
            <?php if ($cari !== null): ?>
                <form method="get" id="toolbarFilter" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" role="search">
                    <label class="alat-crud-kotak">
                        <span class="sr-only">Cari</span>
                        <?= ikon('search', '') ?>
                        <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc((string) $cari, 'attr') ?>" class="inp">
                    </label>
                    <?php foreach ($filter as $f): ?>
                        <select name="<?= esc($f['name'], 'attr') ?>" data-filter<?= $langsung ? ' data-autosubmit' : '' ?> class="inp alat-crud-pilih" aria-label="<?= esc($f['all'], 'attr') ?>">
                            <option value=""><?= esc($f['all']) ?></option>
                            <?php foreach ($f['options'] as $val => $label): ?>
                                <option value="<?= esc((string) $val, 'attr') ?>"<?= (string) ($f['value'] ?? '') === (string) $val ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                    <select name="per" data-autosubmit class="inp alat-crud-pilih" aria-label="Jumlah data per halaman" title="Jumlah data per halaman">
                        <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                            <option value="<?= $n ?>"<?= $per === $n ? ' selected' : '' ?>>Tampilkan <?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
                    <?php if ($adaFilter): ?>
                        <a href="<?= esc($baseUrl, 'attr') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
                    <?php endif; ?>
                </form>
            <?php elseif ($kiriHtml !== null): ?>
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2"><?= $kiriHtml ?></div>
            <?php endif; ?>

            <?php if ($massalUrl): ?>
                <?php // Muncul saat ada baris dicentang (delegasi global di app.js). Disembunyikan lewat ATRIBUT hidden (kelas inline-flex menang atas kelas hidden). ?>
                <button type="button" data-bulk="selected" hidden class="btn btn-bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus terpilih (<span class="js-bulk-count">0</span>)</button>
                <button type="button" data-bulk="all" hidden class="btn btn-bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus semua</button>
            <?php endif; ?>
        </div>

        <div class="alat-crud-tombol">
            <?php if ($tambah): ?>
                <button type="button" class="btn btn-utama" @click="openAdd()"><?= ikon('plus', 'h-4 w-4') ?><?= esc($labelTambah) ?></button>
            <?php endif; ?>
            <?php if ($impor): ?>
                <button type="button" class="btn btn-netral" @click="bukaImpor()"><?= ikon('upload', 'h-4 w-4') ?>Import</button>
            <?php endif; ?>
            <?php if ($eksporUrl): ?>
                <a href="<?= esc((string) $eksporUrl, 'attr') ?>"<?= $langsung && $cari !== null ? ' data-export-filter="toolbarFilter"' : '' ?> title="<?= esc($eksporTitle, 'attr') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Export</a>
            <?php endif; ?>
            <?= $ui['tambahanHtml'] ?? '' ?>
        </div>
    </div>

    <?php if ($massalUrl): ?>
        <form method="post" action="<?= esc((string) $massalUrl, 'attr') ?>" class="hidden js-bulk-form"
              data-label="<?= esc((string) ($ui['massalLabel'] ?? 'data'), 'attr') ?>" data-warn="<?= esc(($ui['massalPeringatan'] ?? '') !== '' ? ' ' . $ui['massalPeringatan'] : '', 'attr') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="">
            <?php foreach ($ui['massalHidden'] ?? [] as $name => $val): ?>
                <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc((string) $val, 'attr') ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
</div>
