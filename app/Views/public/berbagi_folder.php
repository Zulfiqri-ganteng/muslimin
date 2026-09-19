<?php

/**
 * Halaman penerima tautan berbagi — satu folder beserta isinya.
 *
 * @var array $folder
 * @var array $rows   Dokumen di dalam folder (termasuk subfolder)
 * @var array $share
 */
helper('dokumen');

$token = (string) $share['token'];
$boleh = (int) $share['boleh_unduh'] === 1;

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

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-4">
        <div class="flex items-start gap-3">
            <svg class="w-11 h-11 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
            <div class="min-w-0">
                <h1 class="text-lg font-bold text-slate-800 break-words"><?= esc($folder['nama']) ?></h1>
                <p class="text-xs text-slate-500 mt-0.5"><?= count($rows) ?> berkas · dibagikan <?= esc(date('d/m/Y', strtotime((string) $share['created_at']))) ?></p>
                <?php if (trim((string) $folder['deskripsi']) !== '') { ?>
                    <p class="text-sm text-slate-600 mt-2"><?= esc($folder['deskripsi']) ?></p>
                <?php } ?>
            </div>
        </div>
        <?php if (! $boleh) { ?>
            <p class="mt-3 text-[12px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Berkas di folder ini dibagikan untuk <b>dibaca saja</b> — pengunduhan dimatikan oleh pengirim.
            </p>
        <?php } ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if ($rows === []) { ?>
            <div class="p-10 text-center text-sm text-slate-500">Folder ini masih kosong.</div>
        <?php } else { ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($rows as $r) {
                    $kat  = (string) $r['kategori'];
                    $link = $r['tipe'] === 'tautan';
                    ?>
                    <li class="flex items-center gap-3 p-3 hover:bg-slate-50">
                        <span class="px-2 py-1 rounded text-[10px] font-bold shrink-0 <?= $warnaKategori[$kat] ?? $warnaKategori['lainnya'] ?>">
                            <?= esc(strtoupper($r['ekstensi'] ?: ($link ? 'LINK' : $kat))) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800 truncate"><?= esc($r['judul']) ?></p>
                            <p class="text-[11px] text-slate-400">
                                <?= $link ? 'Tautan ' . esc($r['penyedia']) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <?php if ($link) { ?>
                                <a href="<?= esc($r['url_eksternal']) ?>" target="_blank" rel="noopener"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-white">Buka</a>
                            <?php } else { ?>
                                <a href="<?= site_url('d/' . $token . '/berkas/' . $r['id']) ?>" target="_blank" rel="noopener"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-white">Lihat</a>
                                <?php if ($boleh) { ?>
                                    <a href="<?= site_url('d/' . $token . '/unduh/' . $r['id']) ?>"
                                       class="px-3 py-1.5 rounded-lg bg-brand-700 text-white text-xs font-semibold hover:bg-brand-800">Unduh</a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>

    <p class="text-center text-xs text-slate-400 mt-4">
        Tautan ini dibagikan oleh <?= esc($setting['school_name'] ?? 'sekolah') ?>. Mohon tidak menyebarkannya ke pihak yang tidak berkepentingan.
    </p>
</div>

<?= $this->endSection() ?>
