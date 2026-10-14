<?php
/**
 * Panel untuk tab yang belum digarap (rombak tampilan F7: keadaan kosong kit).
 *
 * @var string               $tab
 * @var array<string,string> $tabs
 * @var string               $base
 * @var string               $qtp
 */
?>
<div class="kartu">
    <?= ui_kosong([
        'ikon'  => 'clock',
        'judul' => ($tabs[$tab] ?? 'Bagian ini') . ' belum tersedia',
        'teks'  => 'Bagian ini sedang disiapkan dan akan aktif pada tahap pengembangan berikutnya. Sementara itu, susun dulu jadwal ujiannya.',
        'aksi'  => '<a href="' . $base . '/jadwal' . $qtp . '" class="btn btn-netral">' . ikon('calendar-week', 'h-4 w-4') . 'Buka jadwal ujian</a>',
    ]) ?>
</div>
