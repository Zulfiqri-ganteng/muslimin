<?php
/**
 * RIWAYAT PKL SATU SISWA (revisi klien 2026-10-20): semua ajuan tempat ia tercatat (status apa pun) + pernah dikeluarkan dari
 * kelompok mana, kapan, oleh siapa, dan alasannya. Data: Libraries\PklKeluar::riwayatSiswa(). Controller: Admin\Pkl::riwayat.
 *
 * @var array{siswa: array<string, mixed>, aktif: ?array<string, mixed>, jumlah_ditolak: int, riwayat: list<array<string, mixed>>} $r
 * @var string $kembali id ajuan asal (dari halaman detail ajuan) atau kosong
 * Bersama dari dasar(): $tab, $hitungTab, …
 */

use App\Models\PklPengajuanModel;

$s      = $r['siswa'];
$balik  = ctype_digit($kembali) && (int) $kembali > 0 ? ['admin/pkl/' . (int) $kembali, 'Kembali ke ' . PklPengajuanModel::kode((int) $kembali)] : ['admin/pkl/siswa?q=' . rawurlencode((string) $s['nama']), 'Kembali ke Status Siswa'];
$warna  = static fn (array $x): string => match (true) {
    $x['status'] === 'disetujui'                                 => 'bg-green-500',
    $x['status'] === 'ditolak' || ($x['tolak_sumber'] ?? null) === 'perusahaan' => 'bg-red-500',
    $x['status'] === 'dikeluarkan'                               => 'bg-yellow-400',
    default                                                      => 'bg-brand-500',
};
$lencana = static fn (array $x): string => match ($x['status']) {
    'disetujui'   => '<span class="lencana lencana-hijau">Disetujui</span>',
    'menunggu'    => '<span class="lencana lencana-merek">Menunggu ACC</span>',
    'perbaikan'   => '<span class="lencana lencana-kuning">Perlu perbaikan</span>',
    'ditolak'     => '<span class="lencana lencana-merah">' . (($x['tolak_sumber'] ?? null) === 'perusahaan' ? 'Ditolak perusahaan' : 'Ditolak sekolah') . '</span>',
    'dikeluarkan' => '<span class="lencana lencana-kuning">Dikeluarkan dari kelompok</span>',
    default       => '<span class="lencana lencana-abu">' . esc((string) $x['status']) . '</span>',
};
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url($balik[0]) ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?><?= esc($balik[1]) ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <section class="kartu kartu-isi" aria-labelledby="judul-riwayat-siswa">
        <div class="flex flex-wrap items-center gap-2.5">
            <h2 id="judul-riwayat-siswa" class="text-xl font-bold text-gray-900 dark:text-white"><?= esc((string) $s['nama']) ?></h2>
            <?php if ($r['jumlah_ditolak'] > 0): ?><span class="lencana lencana-kuning">Pernah ditolak/dikeluarkan <?= (int) $r['jumlah_ditolak'] ?>×</span><?php endif; ?>
            <?php if ($s['status'] !== 'aktif'): ?><span class="lencana lencana-abu">Siswa <?= esc((string) $s['status']) ?></span><?php endif; ?>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelas <?= esc($s['kelas'] !== '' ? $s['kelas'] : '—') ?> · NIS <?= esc((string) ($s['nis'] ?: '—')) ?> · NISN <?= esc((string) ($s['nisn'] ?: '—')) ?></p>
        <div class="mt-3">
            <?php if ($r['aktif'] !== null): $ak = $r['aktif']; ?>
                <?= ui_peringatan('info', '', ['html' => '<b>Sekarang:</b> ' . esc($ak['keterangan']) . ' — <a href="' . site_url('admin/pkl/' . (int) $ak['ajuan_id']) . '" class="font-semibold underline">' . esc($ak['kode']) . '</a> ' . esc($ak['perusahaan']) . ' (' . ($ak['peran'] === 'pengaju' ? 'pengaju' : 'teman') . ').']) ?>
            <?php else: ?>
                <?= ui_peringatan('awas', '', ['html' => '<b>Sekarang:</b> tidak punya ajuan PKL yang aktif' . ($r['riwayat'] !== [] ? ' — boleh mengajukan tempat PKL baru.' : ' (belum pernah mengajukan).')]) ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="kartu overflow-hidden" aria-labelledby="judul-linimasa">
        <div class="kartu-kepala"><h3 id="judul-linimasa" class="kartu-judul text-base">Riwayat PKL</h3><span class="lencana lencana-abu"><?= count($r['riwayat']) ?> catatan</span></div>
        <?php if ($r['riwayat'] === []): ?>
            <?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada riwayat PKL', 'teks' => 'Siswa ini belum pernah tercatat di ajuan PKL mana pun.']) ?>
        <?php else: ?>
            <ol class="px-4 py-4 sm:px-6">
                <?php foreach ($r['riwayat'] as $i => $x): ?>
                    <li class="relative ps-5 pb-5 last:pb-0">
                        <span class="absolute start-0 top-1.5 h-2.5 w-2.5 rounded-full <?= $warna($x) ?>" aria-hidden="true"></span>
                        <?php if ($i < count($r['riwayat']) - 1): ?><span class="absolute start-[4px] top-4 h-full w-px bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span><?php endif; ?>
                        <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            <?= esc($x['perusahaan']) ?> <?= $lencana($x) ?>
                            <a href="<?= site_url('admin/pkl/' . (int) $x['ajuan_id']) ?>" class="font-mono text-xs font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= esc($x['kode']) ?></a>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($x['keterangan']) ?> · <?= esc(date('d-m-Y H:i', strtotime((string) $x['waktu']))) ?> · sebagai <?= $x['peran'] === 'pengaju' ? 'pengaju' : 'teman' ?> · ajuan dikirim <?= esc(date('d-m-Y', strtotime((string) $x['diajukan']))) ?></p>
                        <?php if (($x['alasan'] ?? '') !== ''): ?><p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">“<?= esc((string) $x['alasan']) ?>”</p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
