<?php
/**
 * Sistem → Sesi Aktif (docs/DESAIN-ALAT-SISTEM.md F5). Dua tabel: masuk di web (aktif < 1 jam) & aplikasi Android (token belum
 * kedaluwarsa). Keluarkan = form POST + dialog konfirmasi kit; sesi sendiri ditandai "Sesi ini" tanpa tombol. Tanpa JS halaman.
 *
 * @var array{web: list<array>, android: list<array>} $sesi SesiAktif::daftar()
 */
use App\Libraries\SesiAktif;

$lalu = static function (?string $w): string {
    if (! $w) {
        return '—';
    }
    $d = time() - strtotime($w);

    return match (true) {
        $d < 60    => 'baru saja',
        $d < 3600  => (int) floor($d / 60) . ' menit lalu',
        $d < 86400 => (int) floor($d / 3600) . ' jam lalu',
        default    => date('d-m-Y H:i', strtotime($w)),
    };
};
$jumlahLain = count(array_filter(array_merge($sesi['web'], $sesi['android']), static fn (array $r): bool => ! $r['ini']));
$tabel = static function (string $jenis, array $baris) use ($lalu): string {
    ob_start(); ?>
    <div class="tabel-bungkus">
        <table class="tabel">
            <thead>
                <tr>
                    <th scope="col">Akun</th>
                    <th scope="col">Perangkat</th>
                    <?php if ($jenis === 'web'): ?><th scope="col">IP</th><?php endif; ?>
                    <th scope="col">Masuk</th>
                    <th scope="col"><?= $jenis === 'web' ? 'Aktif terakhir' : 'Terakhir dipakai' ?></th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($baris as $r): ?>
                    <tr>
                        <td>
                            <span class="font-medium text-gray-900 dark:text-white"><?= esc($r['nama']) ?></span>
                            <span class="lencana lencana-abu ms-1"><?= esc($r['peran_label']) ?></span>
                        </td>
                        <td class="text-sm"><?= esc($r['perangkat'] ?: '—') ?></td>
                        <?php if ($jenis === 'web'): ?><td class="font-mono text-xs text-gray-500 dark:text-gray-400"><?= esc($r['ip'] ?: '—') ?></td><?php endif; ?>
                        <td class="text-xs whitespace-nowrap tabular-nums"><?= esc($r['masuk'] ? date('d-m-Y H:i', strtotime($r['masuk'])) : '—') ?></td>
                        <td class="text-xs whitespace-nowrap"><?= esc($lalu($r['aktif'])) ?></td>
                        <td class="whitespace-nowrap text-end">
                            <?php if ($r['ini']): ?>
                                <span class="lencana lencana-hijau">Sesi ini</span>
                            <?php else: ?>
                                <form method="post" action="<?= site_url('admin/sistem/sesi/' . $jenis . '/' . $r['id'] . '/keluarkan') ?>" class="inline"
                                      data-confirm="<?= esc($r['nama'], 'attr') ?> akan keluar dari <?= $jenis === 'web' ? 'web' : 'aplikasi Android' ?> (<?= esc($r['perangkat'], 'attr') ?>) dan harus login lagi." data-confirm-judul="Keluarkan sesi ini?" data-confirm-ya="Ya, keluarkan" data-confirm-jenis="bahaya">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-teks btn-sm text-red-700 dark:text-red-400"><?= ikon('close-circle', 'h-4 w-4') ?>Keluarkan</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<?php if ($jumlahLain > 0): ?>
<form method="post" action="<?= site_url('admin/sistem/sesi/keluarkan-semua') ?>"
      data-confirm="Semua akun lain (<?= $jumlahLain ?> sesi web & Android) akan keluar dan harus login lagi. Sesi Anda tetap." data-confirm-judul="Keluarkan semua kecuali saya?" data-confirm-ya="Ya, keluarkan semua" data-confirm-jenis="bahaya">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-bahaya"><?= ikon('users-group', 'h-4 w-4') ?>Keluarkan semua kecuali saya</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'sesi_aktif',
    'helpTitle' => 'Sesi Aktif',
    'helpBody'  => '<p>Daftar akun yang sedang masuk. <b>Web</b> = aktif dalam ' . (SesiAktif::BATAS_AKTIF / 60) . ' menit terakhir (sesudah itu keluar otomatis). <b>Aplikasi Android</b> = perangkat yang masih tersambung. <b>Keluarkan</b> dipakai bila ada akun yang lupa keluar di komputer umum, HP hilang, atau sandi bocor — sesudah itu sebaiknya ganti sandi akun tersebut (Kelola Akun).</p>',
]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-sesi-web">
    <div class="kartu-kepala">
        <h2 id="judul-sesi-web" class="kartu-judul flex items-center gap-2">Masuk di web<span class="lencana lencana-abu"><?= count($sesi['web']) ?></span></h2>
    </div>
    <?= $sesi['web'] === [] ? ui_kosong(['ikon' => 'desktop-pc', 'judul' => 'Tidak ada sesi web aktif']) : $tabel('web', $sesi['web']) ?>
</section>

<section class="kartu mt-4 overflow-hidden" aria-labelledby="judul-sesi-android">
    <div class="kartu-kepala">
        <h2 id="judul-sesi-android" class="kartu-judul flex items-center gap-2">Aplikasi Android<span class="lencana lencana-abu"><?= count($sesi['android']) ?></span></h2>
    </div>
    <?= $sesi['android'] === [] ? ui_kosong(['ikon' => 'desktop-pc', 'judul' => 'Tidak ada perangkat Android tersambung']) : $tabel('android', $sesi['android']) ?>
</section>

<?= $this->endSection() ?>
