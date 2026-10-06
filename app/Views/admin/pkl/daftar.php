<?php
/**
 * Kotak masuk PKL — daftar ajuan satu status.
 *
 * @var string      $status   menunggu | perbaikan | disetujui | ditolak
 * @var list<array> $rows
 * @var int         $total
 * @var string      $q
 * @var int         $kelasId
 * @var list<array> $kelas
 * @var int         $page
 * @var int         $jmlHal
 */

use App\Libraries\IsianBantu;
use App\Models\PklPengajuanModel;

$keterangan = [
    'menunggu'  => 'Ajuan baru dari siswa. Periksa lalu ACC, kembalikan, atau tolak. Yang paling lama menunggu ada di atas.',
    'perbaikan' => 'Sudah dikembalikan ke siswa dan menunggu mereka memperbaiki. Bila siswa kesulitan, Anda bisa mengubah langsung.',
    'disetujui' => 'Sudah di-ACC. Siswa-siswa di sini terkunci dan tidak bisa mengajukan lagi.',
    'ditolak'   => 'Ditolak. Siswanya sudah bebas dan boleh mengajukan lagi dari awal.',
];
$urlHal = static function (int $hal) use ($status, $q, $kelasId): string {
    return site_url('admin/pkl/daftar/' . $status) . '?' . http_build_query(array_filter(['q' => $q, 'kelas_id' => $kelasId ?: null, 'page' => $hal > 1 ? $hal : null]));
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_daftar_v1',
    'helpTitle' => 'Kotak Masuk PKL',
    'helpBody'  => '<p>Pilih tab status di atas. Cari berdasarkan <b>nama perusahaan</b> atau <b>nama siswa</b>, atau saring per kelas. Klik baris untuk membuka detail dan mengambil keputusan.</p>'
        . '<p class="mt-2">Satu baris = satu <b>perusahaan</b> dengan rombongan siswanya (pengaju + teman satu tempat).</p>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-slate-800"><?= esc($title) ?> <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500"><?= (int) $total ?></span></h2>
                <p class="mt-0.5 max-w-2xl text-xs text-slate-400"><?= esc($keterangan[$status] ?? '') ?></p>
            </div>
            <a href="<?= site_url('admin/pkl/baru') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">+ Isi atas Nama</a>
        </div>

        <form method="get" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_14rem_auto]">
            <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari perusahaan atau nama siswa…" class="inp" aria-label="Cari">
            <select name="kelas_id" class="inp" aria-label="Kelas">
                <option value="">Semua kelas</option>
                <?php foreach ($kelas as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>><?= esc($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Cari</button>
                <?php if ($q !== '' || $kelasId > 0): ?>
                    <a href="<?= site_url('admin/pkl/daftar/' . $status) ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($rows === []): ?>
        <div class="px-5 py-12 text-center">
            <p class="font-semibold text-slate-700"><?= ($q !== '' || $kelasId > 0) ? 'Tidak ada ajuan yang cocok dengan pencarian.' : 'Belum ada ajuan di sini.' ?></p>
        </div>
    <?php else: ?>
        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Bukti</th>
                        <th class="px-3 py-3 font-semibold">Perusahaan</th>
                        <th class="px-3 py-3 font-semibold">Pengaju</th>
                        <th class="px-3 py-3 font-semibold">Periode</th>
                        <th class="px-3 py-3 font-semibold">Diperbarui</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-500"><?= esc(PklPengajuanModel::kode((int) $r['id'])) ?></td>
                            <td class="px-3 py-3">
                                <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="font-semibold text-slate-800 hover:text-brand-700"><?= esc($r['perusahaan_nama']) ?></a>
                                <p class="text-xs text-slate-400"><?= esc($r['perusahaan_kota'] ?? '') ?><?= $r['sumber'] !== 'siswa' ? ' · ' . ($r['sumber'] === 'staf' ? 'diisi staf' : 'impor') : '' ?><?= (int) $r['kirim_ke'] > 1 ? ' · revisi ke-' . ((int) $r['kirim_ke'] - 1) : '' ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <p class="font-medium text-slate-700"><?= esc($r['pengaju'] ?? '—') ?></p>
                                <p class="text-xs text-slate-400"><?= esc($r['pengaju_kelas'] ?? '') ?><?= (int) $r['jumlah'] > 1 ? ' · +' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?></p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500"><?= $r['tanggal_mulai'] ? esc(IsianBantu::tanggalIndo($r['tanggal_mulai'])) . '<br>s/d ' . esc(IsianBantu::tanggalIndo($r['tanggal_selesai'])) : '—' ?></td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-400"><?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></td>
                            <td class="px-5 py-3 text-right"><a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="inline-flex rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800"><?= $status === 'menunggu' ? 'Periksa' : 'Buka' ?></a></td>
                        </tr>
                        <?php if (! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
                            <tr><td></td><td colspan="5" class="px-3 pb-3 text-xs text-slate-500"><span class="font-semibold text-slate-600">Catatan sekolah:</span> <?= esc($r['catatan_staf']) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP) -->
        <ul class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $r): ?>
                <li>
                    <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="block px-4 py-3.5 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-slate-800"><?= esc($r['perusahaan_nama']) ?></p>
                            <span class="shrink-0 font-mono text-[11px] font-semibold text-slate-400"><?= esc(PklPengajuanModel::kode((int) $r['id'])) ?></span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500"><?= esc($r['pengaju'] ?? '—') ?> (<?= esc($r['pengaju_kelas'] ?? '') ?>)<?= (int) $r['jumlah'] > 1 ? ' + ' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?></p>
                        <p class="mt-1 text-xs text-slate-400"><?= $r['tanggal_mulai'] ? esc(IsianBantu::tanggalIndo($r['tanggal_mulai'])) . ' – ' . esc(IsianBantu::tanggalIndo($r['tanggal_selesai'])) : 'Tanggal belum ada' ?> · <?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></p>
                        <?php if (! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
                            <p class="mt-1 text-xs text-slate-500"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($jmlHal > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">← Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-slate-400">Halaman <?= $page ?> dari <?= $jmlHal ?></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">Berikutnya →</a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
