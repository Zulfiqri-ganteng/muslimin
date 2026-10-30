<?php
/**
 * Bilah alat halaman data (kit F5, pola template content/crud/users.html): kiri = kotak cari ber-ikon + filter + "Tampilkan N" +
 * Cari/Reset + tombol hapus massal (muncul saat ada baris dicentang); kanan = Tambah (biru, ikon +), Import & Export (putih, ikon).
 * Dipakai di dalam komponen Alpine "masterList" (Tambah → openAdd(), Import → bukaImpor()). Menyambung dengan kepala halaman mode
 * CRUD (layout: section 'tata' = crud). Fitur sama dengan toolbar master lama (dibuang di F5 Langkah 4).
 * Panggil: ui_crud_alat([...]).
 *
 * @var array{
 *   baseUrl: string, cari?: string|null, q?: string, filter?: list<array{name: string, value?: string, all: string, options: array<string, string>}>,
 *   per?: int, kiriHtml?: string|null, tambah?: bool, labelTambah?: string, impor?: bool, eksporUrl?: string|null, eksporTitle?: string,
 *   tambahanHtml?: string, massalUrl?: string|null, massalHidden?: array<string, string>, massalLabel?: string, massalPeringatan?: string,
 *   massalSemua?: bool, filterLangsung?: bool, hidden?: array<string, string>, lentur?: bool
 * } $ui  massalSemua false = tanpa tombol "Hapus semua". Saringan ≥ 3 → kelas alat-crud-padat (tombol turun ke baris sendiri di lebar laptop).
 * 'hidden' (F7) = isian tersembunyi di formulir cari/filter yang ikut terkirim, mis. ['tp' => '2026/2027'] (tahun pelajaran Ujian).
 * 'lentur' (2026-10-30, Master Siswa; butuh 'cari') = susunan menurut lebar wadah: lebar → baris 1 cari … tombol, baris 2 saringan +
 * Reset … Tampilkan N; sempit (HP) → cari + tombol "Saringan" (panel buka-tutup, app.js data-alih-saringan), lalu Tambah + Lainnya.
 * Import & Export disembunyikan saat sempit → halaman menaruh butir pengganti di menu Lainnya dengan atribut data-hanya-sempit
 * (Import: data-buka-impor; Export: data-export-filter="toolbarFilter").
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
$lentur      = ! empty($ui['lentur']) && $cari !== null;

$adaFilter = $q !== '';
$nSaring   = 0; // saringan terpilih (tanpa kotak cari) — angka di tombol "Saringan" bilah lentur
foreach ($filter as $f) {
    if ((string) ($f['value'] ?? '') !== '') {
        $adaFilter = true;
        $nSaring++;
    }
}

// Bagian-bagian bilah (dipakai kedua susunan).
$kotakCari = static fn (): string => '<label class="alat-crud-kotak"><span class="sr-only">Cari</span>' . ikon('search', '')
    . '<input type="search" name="q" value="' . esc($q, 'attr') . '" placeholder="' . esc((string) $cari, 'attr') . '" enterkeyhint="search" class="inp"></label>';
$pilihan = static function (string $kelas) use ($filter, $langsung): string {
    $h = '';
    foreach ($filter as $f) {
        $h .= '<select name="' . esc($f['name'], 'attr') . '" data-filter' . ($langsung ? ' data-autosubmit' : '') . ' class="inp' . $kelas . '" aria-label="' . esc($f['all'], 'attr') . '">'
            . '<option value="">' . esc($f['all']) . '</option>';
        foreach ($f['options'] as $val => $label) {
            $h .= '<option value="' . esc((string) $val, 'attr') . '"' . ((string) ($f['value'] ?? '') === (string) $val ? ' selected' : '') . '>' . esc($label) . '</option>';
        }
        $h .= '</select>';
    }

    return $h;
};
$pilihPer = static function (string $kelas) use ($per): string {
    $h = '<select name="per" data-autosubmit class="inp' . $kelas . '" aria-label="Jumlah data per halaman" title="Jumlah data per halaman">';
    foreach ([10, 20, 30, 40, 50] as $n) {
        $h .= '<option value="' . $n . '"' . ($per === $n ? ' selected' : '') . '>Tampilkan ' . $n . '</option>';
    }

    return $h . '</select>';
};
$tombolReset = static fn (): string => $adaFilter ? '<a href="' . esc($baseUrl, 'attr') . '" class="btn btn-teks">' . ikon('close', 'h-4 w-4') . 'Reset</a>' : '';
// Muncul saat ada baris dicentang (delegasi global di app.js). Disembunyikan lewat ATRIBUT hidden (kelas inline-flex menang atas kelas hidden).
$tombolMassal = static fn (): string => ! $massalUrl ? '' : '<button type="button" data-bulk="selected" hidden class="btn btn-bahaya">' . ikon('trash-bin', 'h-4 w-4') . 'Hapus terpilih (<span class="js-bulk-count">0</span>)</button>'
    . (($ui['massalSemua'] ?? true) ? '<button type="button" data-bulk="all" hidden class="btn btn-bahaya">' . ikon('trash-bin', 'h-4 w-4') . 'Hapus semua</button>' : '');
$tombolKanan = static function (string $kelasLebar) use ($tambah, $labelTambah, $impor, $eksporUrl, $eksporTitle, $langsung, $cari, $ui): string {
    return ($tambah ? '<button type="button" class="btn btn-utama" @click="openAdd()">' . ikon('plus', 'h-4 w-4') . esc($labelTambah) . '</button>' : '')
        . ($impor ? '<button type="button" class="btn btn-netral' . $kelasLebar . '" @click="bukaImpor()">' . ikon('upload', 'h-4 w-4') . 'Import</button>' : '')
        . ($eksporUrl ? '<a href="' . esc((string) $eksporUrl, 'attr') . '"' . ($langsung && $cari !== null ? ' data-export-filter="toolbarFilter"' : '') . ' title="' . esc($eksporTitle, 'attr') . '" class="btn btn-netral' . $kelasLebar . '">' . ikon('download', 'h-4 w-4') . 'Export</a>' : '')
        . ($ui['tambahanHtml'] ?? '');
};
?>
<?php if ($lentur): ?>
<div class="alat-crud alat-lentur">
    <form method="get" id="toolbarFilter" class="alat-lentur-form" role="search">
        <?php foreach ($ui['hidden'] ?? [] as $name => $val): ?>
            <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc((string) $val, 'attr') ?>">
        <?php endforeach; ?>
        <div class="alat-lentur-atas">
            <?= $kotakCari() ?>
            <?php if ($filter !== []): ?>
                <button type="button" class="btn btn-netral alat-lentur-saring" data-alih-saringan="alat-saringan" aria-controls="alat-saringan" aria-expanded="<?= $nSaring > 0 ? 'true' : 'false' ?>">
                    <?= ikon('adjustments-horizontal', 'h-4 w-4') ?>Saringan<?php if ($nSaring > 0): ?><span class="lencana lencana-merek"><?= $nSaring ?></span><?php endif; ?>
                </button>
            <?php endif; ?>
            <div class="alat-lentur-tombol">
                <?= $tombolMassal() ?>
                <?= $tombolKanan(' alat-lentur-lebar') ?>
            </div>
        </div>
        <?php // Panel saringan: selalu tampil di layar lebar; di layar sempit terbuka bila ada saringan terpilih (supaya terlihat apa yang menyaring daftar). ?>
        <div id="alat-saringan" class="alat-lentur-saringan" data-buka="<?= $nSaring > 0 ? 'true' : 'false' ?>">
            <?= $pilihan('') ?>
            <?= $tombolReset() ?>
            <?= $pilihPer(' alat-lentur-per') ?>
        </div>
    </form>
<?php else: ?>
<div class="alat-crud">
    <div class="alat-crud-baris<?= count($filter) >= 3 ? ' alat-crud-padat' : '' ?>">
        <div class="alat-crud-cari">
            <?php if ($cari !== null): ?>
                <form method="get" id="toolbarFilter" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" role="search">
                    <?php foreach ($ui['hidden'] ?? [] as $name => $val): ?>
                        <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc((string) $val, 'attr') ?>">
                    <?php endforeach; ?>
                    <?= $kotakCari() ?>
                    <?= $pilihan(' alat-crud-pilih') ?>
                    <?= $pilihPer(' alat-crud-pilih') ?>
                    <?php if (! $langsung): ?>
                        <?php // Saringan langsung: tombol "Cari" dobel dengan kotak cari (Enter / tombol Cari papan ketik HP sudah mengirim) — keluhan user 2026-10-30. ?>
                        <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
                    <?php endif; ?>
                    <?= $tombolReset() ?>
                </form>
            <?php elseif ($kiriHtml !== null): ?>
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2"><?= $kiriHtml ?></div>
            <?php endif; ?>

            <?= $tombolMassal() ?>
        </div>

        <div class="alat-crud-tombol">
            <?= $tombolKanan('') ?>
        </div>
    </div>
<?php endif; ?>

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
