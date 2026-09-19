<?php

/**
 * Halaman penerima tautan berbagi — satu dokumen.
 *
 * @var array $d     Baris dokumen
 * @var array $share Baris tautan berbagi
 */
helper('dokumen');

$token    = (string) $share['token'];
$kategori = (string) $d['kategori'];
$ext      = strtolower((string) $d['ekstensi']);
$tautan   = $d['tipe'] === 'tautan';
$boleh    = (int) $share['boleh_unduh'] === 1;
$urlIsi   = site_url('d/' . $token . '/berkas');
$urlUnduh = site_url('d/' . $token . '/unduh');

// Office Viewer butuh berkas yang bisa diambil dari internet — lewat tautan
// berbagi memang bisa, jadi ditawarkan untuk format yang tak punya penampil.
$bolehViewerLuar = ! $tautan && $boleh && in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">

    <!-- Kepala -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-4">
        <div class="flex items-start gap-3">
            <div class="w-11 h-11 rounded-xl bg-brand-50 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold text-slate-800 break-words"><?= esc($d['judul']) ?></h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    <?php if (! $tautan) { ?>
                        <?= esc(strtoupper($ext ?: $kategori)) ?> · <?= esc(dokumen_ukuran_manusia((int) $d['ukuran'])) ?> ·
                    <?php } ?>
                    dibagikan <?= esc(date('d/m/Y', strtotime((string) $share['created_at']))) ?>
                </p>
                <?php if (trim((string) $d['deskripsi']) !== '') { ?>
                    <p class="text-sm text-slate-600 mt-2 whitespace-pre-line"><?= esc($d['deskripsi']) ?></p>
                <?php } ?>
            </div>

            <?php if ($boleh && ! $tautan) { ?>
                <a href="<?= $urlUnduh ?>" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh
                </a>
            <?php } ?>
        </div>

        <?php if (! $boleh && ! $tautan) { ?>
            <p class="mt-3 text-[12px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Dokumen ini dibagikan untuk <b>dibaca saja</b> — pengunduhan dimatikan oleh pengirim.
            </p>
        <?php } ?>
    </div>

    <!-- Penampil -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?= view('partials/dokumen_penampil', [
            'urlIsi'          => $urlIsi,
            'urlUnduh'        => $urlUnduh,
            'kategori'        => $kategori,
            'ext'             => $ext,
            'judul'           => $d['judul'],
            'mime'            => (string) $d['mime'],
            'tautan'          => $tautan,
            'ytId'            => $tautan ? dokumen_youtube_id((string) $d['url_eksternal']) : null,
            'urlEksternal'    => $d['url_eksternal'],
            'penyedia'        => (string) $d['penyedia'],
            'bolehUnduh'      => $boleh,
            'bolehViewerLuar' => $bolehViewerLuar,
            'urlViewerLuar'   => $bolehViewerLuar
                ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($urlIsi)
                : null,
        ]) ?>
    </div>

    <p class="text-center text-xs text-slate-400 mt-4">
        Tautan ini dibagikan oleh <?= esc($setting['school_name'] ?? 'sekolah') ?>. Mohon tidak menyebarkannya ke pihak yang tidak berkepentingan.
    </p>
</div>

<?= $this->endSection() ?>
