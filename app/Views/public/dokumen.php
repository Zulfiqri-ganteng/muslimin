<?php

/**
 * Daftar dokumen publik sekolah (digerbangi settings.dokumen_publik).
 *
 * @var array                         $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 */
helper('dokumen');

$warnaKategori = [
    'pdf'         => 'bg-red-50 text-red-700',
    'dokumen'     => 'bg-blue-50 text-blue-700',
    'spreadsheet' => 'bg-emerald-50 text-emerald-700',
    'presentasi'  => 'bg-orange-50 text-orange-700',
    'gambar'      => 'bg-violet-50 text-violet-700',
    'audio'       => 'bg-pink-50 text-pink-700',
    'video'       => 'bg-rose-50 text-rose-700',
    'arsip'       => 'bg-amber-50 text-amber-700',
    'lainnya'     => 'bg-slate-100 text-slate-600',
];
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

    <div class="text-center mb-6">
        <h1 class="text-2xl font-extrabold text-slate-800">Dokumen Sekolah</h1>
        <p class="text-sm text-slate-500 mt-1">Berkas yang dibuka untuk umum oleh <?= esc($setting['school_name'] ?? 'sekolah') ?>.</p>
    </div>

    <form method="get" class="mb-5">
        <div class="relative">
            <svg class="w-5 h-5 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Cari dokumen…"
                   class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-300 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if ($rows === []) { ?>
            <div class="p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                <p class="mt-3 text-sm text-slate-500">
                    <?= $q !== '' ? 'Tidak ada dokumen yang cocok dengan pencarian.' : 'Belum ada dokumen yang dibuka untuk umum.' ?>
                </p>
            </div>
        <?php } else { ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($rows as $r) {
                    $kat  = (string) $r['kategori'];
                    $link = $r['tipe'] === 'tautan';
                    ?>
                    <li class="flex items-center gap-3 p-4 hover:bg-slate-50">
                        <span class="px-2 py-1 rounded text-[10px] font-bold shrink-0 <?= $warnaKategori[$kat] ?? $warnaKategori['lainnya'] ?>">
                            <?= esc(strtoupper($r['ekstensi'] ?: ($link ? 'LINK' : $kat))) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800 truncate"><?= esc($r['judul']) ?></p>
                            <?php if (trim((string) $r['deskripsi']) !== '') { ?>
                                <p class="text-xs text-slate-500 truncate"><?= esc($r['deskripsi']) ?></p>
                            <?php } ?>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                <?= $link ? 'Tautan ' . esc($r['penyedia']) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                                · <?= esc(date('d/m/Y', strtotime((string) $r['created_at']))) ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <?php if ($link) { ?>
                                <a href="<?= esc($r['url_eksternal']) ?>" target="_blank" rel="noopener"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-white">Buka</a>
                            <?php } else { ?>
                                <a href="<?= site_url('dokumen-publik/' . $r['id'] . '/berkas') ?>" target="_blank" rel="noopener"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-white">Lihat</a>
                                <a href="<?= site_url('dokumen-publik/' . $r['id'] . '/unduh') ?>"
                                   class="px-3 py-1.5 rounded-lg bg-brand-700 text-white text-xs font-semibold hover:bg-brand-800">Unduh</a>
                            <?php } ?>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>

    <?php if ($pager !== null) { ?>
        <div class="mt-5"><?= $pager->only(['q'])->links('pub', 'admin') ?></div>
    <?php } ?>
</div>

<?= $this->endSection() ?>
