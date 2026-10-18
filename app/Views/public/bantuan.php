<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Bantuan (tujuan ikon tanda tanya di kepala; acuan: "Pusat Bantuan"): tanya-jawab + kontak sekolah.
 *
 * @var array $setting @var list<array{0: string, 1: string}> $tanya
 */
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Bantuan', null]],
    'ikon'  => 'question-circle',
    'ilu'   => 'bantuan',
    'label' => 'Pusat bantuan',
    'judul' => 'Bantuan',
    'ket'   => 'Jawaban atas pertanyaan yang sering ditanyakan, dan cara menghubungi sekolah bila masih ada kendala.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="lg:col-span-2">
            <h2 class="judul-sub mb-4">Pertanyaan yang sering ditanyakan</h2>
            <?= view('public/partials/tanya_jawab', ['tanya' => $tanya, 'bukaPertama' => true], ['saveData' => false]) ?>
        </div>
        <div class="space-y-6">
            <?= view('public/partials/kontak', ['setting' => $setting], ['saveData' => false]) ?>
            <div class="kartu kartu-isi">
                <h2 class="kartu-judul">Mencari layanan?</h2>
                <p class="kartu-ket">Pilih sesuai kebutuhan Anda.</p>
                <ul class="mt-4 grid gap-1">
                    <?php foreach (\App\Libraries\LayananPublik::kelompok() as $g): ?>
                        <li><a href="<?= esc($g['url'], 'attr') ?>" class="laci-tautan text-sm"><?= ikon($g['ikon']) ?><?= esc($g['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
