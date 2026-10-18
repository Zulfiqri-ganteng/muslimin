<?php

/**
 * Halaman penerima tautan berbagi — satu folder beserta isinya (pola 4c rombak publik P5): pembuka · peringatan "dibaca saja" ·
 * daftar berkas (partials/baris_berkas) · catatan agar tautan tidak disebarkan.
 *
 * @var array $folder
 * @var array $rows   Dokumen di dalam folder (termasuk subfolder)
 * @var array $share
 */
$token = (string) $share['token'];
$boleh = (int) $share['boleh_unduh'] === 1;
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?= view('public/partials/pembuka', [
    'remah' => [['Folder dibagikan', null]],
    'ikon'  => 'folder-open',
    'label' => 'Folder dibagikan',
    'judul' => (string) $folder['nama'],
    'ket'   => trim((string) $folder['deskripsi']) !== ''
        ? (string) $folder['deskripsi']
        : count($rows) . ' berkas · dibagikan ' . \App\Libraries\LayananPublik::tanggal((string) $share['created_at'], false),
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-4">
        <?php if (trim((string) $folder['deskripsi']) !== ''): ?>
            <p class="text-sm text-teks-2"><?= count($rows) ?> berkas · dibagikan <?= esc(\App\Libraries\LayananPublik::tanggal((string) $share['created_at'], false)) ?></p>
        <?php endif; ?>
        <?php if (! $boleh): ?>
            <div class="peringatan peringatan-awas"><?= ikon('info-circle') ?><p>Berkas di folder ini dibagikan untuk <b>dibaca saja</b> — pengunduhan dimatikan oleh pengirim.</p></div>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('folder-open') ?></span><p class="kosong-judul">Folder ini masih kosong</p></div></div>
        <?php else: ?>
            <ul class="kartu daftar-baris">
                <?php foreach ($rows as $r): ?>
                    <?= view('public/partials/baris_berkas', [
                        'r'        => $r,
                        'urlLihat' => site_url('d/' . $token . '/berkas/' . $r['id']),
                        'urlUnduh' => $boleh ? site_url('d/' . $token . '/unduh/' . $r['id']) : null,
                        'tanggal'  => false,
                    ], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="pt-2 text-center text-sm text-teks-2">
            Tautan ini dibagikan oleh <?= esc($setting['school_name'] ?? 'sekolah') ?>. Mohon tidak menyebarkannya ke pihak yang tidak berkepentingan.
        </p>
    </div>
</section>

<?= $this->endSection() ?>
