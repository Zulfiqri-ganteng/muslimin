<?php
/**
 * Daftar tanya-jawab (acuan "Pertanyaan yang paling sering ditanyakan") — <details> tanpa JS. Butir pertama terbuka bila diminta.
 *
 * @var list<array{0: string, 1: string}> $tanya   LayananPublik::tanyaJawab()
 * @var bool|null                         $bukaPertama
 */
?>
<div class="tanya-daftar">
    <?php foreach ($tanya as $i => [$t, $j]): ?>
        <details class="tanya"<?= ! empty($bukaPertama) && $i === 0 ? ' open' : '' ?>>
            <summary><?= esc($t) ?><?= ikon('chevron-down', 'tanya-panah') ?></summary>
            <p class="tanya-isi"><?= esc($j) ?></p>
        </details>
    <?php endforeach; ?>
</div>
