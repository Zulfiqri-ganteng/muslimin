<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Daftar pengumuman publik (acuan halaman berita): kartu bergambar + paginasi. Hanya pengumuman aktif.
 *
 * @var list<array> $rows @var \CodeIgniter\Pager\Pager $pager @var string|null $logo
 */
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Pengumuman', null]],
    'ikon'  => 'bullhorn',
    'ilu'   => 'pengumuman',
    'label' => 'Informasi sekolah',
    'judul' => 'Pengumuman',
    'ket'   => 'Informasi, berita, dan agenda terbaru dari sekolah.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah">
        <?php if ($rows === []): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('bullhorn') ?></span><p class="kosong-judul">Belum ada pengumuman</p><p class="kosong-teks">Pengumuman dari sekolah akan tampil di sini.</p></div></div>
        <?php else: ?>
            <div class="grid-info">
                <?php foreach ($rows as $p): ?>
                    <?= view('public/partials/kartu_info', ['p' => $p, 'logo' => $logo], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
            <div class="mt-8"><?= $pager->links('pg', 'publik') ?></div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
