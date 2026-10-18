<?php

/**
 * Daftar dokumen publik sekolah (digerbangi settings.dokumen_publik) — pola 4c rombak publik P5: pembuka · kotak cari dalam kartu ·
 * daftar berkas (partials/baris_berkas) · paginasi.
 *
 * @var array                         $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 */
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?= view('public/partials/pembuka', [
    'remah' => [['Orang Tua', site_url('orang-tua')], ['Dokumen sekolah', null]],
    'ilu'   => 'dokumen',
    'label' => 'Informasi sekolah',
    'judul' => 'Dokumen sekolah',
    'ket'   => 'Berkas yang dibuka untuk umum oleh ' . ($setting['school_name'] ?? 'sekolah') . ': surat edaran, panduan, dan dokumen resmi lainnya.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-6">
        <form method="get" class="kartu kartu-isi" role="search">
            <label class="lbl" for="cari-dokumen">Cari dokumen</label>
            <div class="cari max-w-2xl">
                <?= ikon('search', 'cari-ikon') ?>
                <input type="search" id="cari-dokumen" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Judul atau keterangan dokumen" class="cari-isian">
                <button type="submit" class="cari-tombol">Cari</button>
            </div>
        </form>

        <?php if ($rows === []): ?>
            <div class="kartu">
                <div class="kosong">
                    <span class="kosong-ikon"><?= ikon('folder-open') ?></span>
                    <p class="kosong-judul"><?= $q !== '' ? 'Tidak ada dokumen yang cocok' : 'Belum ada dokumen' ?></p>
                    <p class="kosong-teks"><?= $q !== '' ? 'Coba kata lain, atau kosongkan pencarian.' : 'Dokumen yang dibuka sekolah untuk umum akan tampil di sini.' ?></p>
                </div>
            </div>
        <?php else: ?>
            <ul class="kartu daftar-baris">
                <?php foreach ($rows as $r): ?>
                    <?= view('public/partials/baris_berkas', [
                        'r'        => $r,
                        'urlLihat' => site_url('dokumen-publik/' . $r['id'] . '/berkas'),
                        'urlUnduh' => site_url('dokumen-publik/' . $r['id'] . '/unduh'),
                    ], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($pager !== null): ?>
            <div><?= $pager->only(['q'])->links('pub', 'publik') ?></div>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>
