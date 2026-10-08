<?php
/**
 * Detail Siswa — semua data satu siswa, read-only. Isi dirakit App\Libraries\SiswaDetail (dipakai juga API).
 *
 * @var array{siswa: array<string,mixed>, bagian: list<array>, biodata: array<string,mixed>, pkl: ?array<string,mixed>, jejak: array<string,mixed>} $d
 * @var bool   $bolehPkl  peran ini boleh membuka ajuan PKL
 * @var bool   $bolehBio  peran ini boleh membuka Isian Biodata
 * @var string $urlDaftar kembali ke daftar (membawa saringan)
 */
$s = $d['siswa'];
$warnaStatus = [
    'aktif'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'lulus'  => 'bg-blue-50 text-blue-700 border-blue-200',
    'pindah' => 'bg-amber-50 text-amber-700 border-amber-200',
    'keluar' => 'bg-slate-100 text-slate-500 border-slate-200',
];
$statusPkl = [
    'menunggu' => ['Menunggu keputusan Waka Hubin', 'bg-blue-50 text-blue-700 border-blue-200'],
    'perbaikan' => ['Perlu perbaikan', 'bg-amber-50 text-amber-700 border-amber-200'],
    'disetujui' => ['Disetujui', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
];
$urlEdit = site_url('admin/master/siswa') . '?' . http_build_query(['q' => (string) $s['nis'], 'edit' => (int) $s['id']]);
$kosong  = '<span class="text-slate-300">—</span>';
$bio     = $d['biodata'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-5xl space-y-5">

    <!-- Kepala -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <a href="<?= esc($urlDaftar, 'attr') ?>" class="text-sm font-semibold text-brand-700 hover:underline">&larr; Daftar Siswa</a>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="break-words text-xl font-extrabold text-slate-900 sm:text-2xl"><?= esc($s['nama']) ?></h1>
                <p class="mt-1 text-sm text-slate-500">
                    NIS <b class="text-slate-700"><?= esc($s['nis']) ?></b>
                    <?php if (! empty($s['nisn'])): ?> &middot; NISN <b class="text-slate-700"><?= esc($s['nisn']) ?></b><?php endif; ?>
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-700"><?= ! empty($s['nama_kelas']) ? esc($s['nama_kelas']) : 'Belum ada kelas' ?></span>
                    <span class="rounded-full border px-2.5 py-1 <?= $warnaStatus[$s['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst((string) $s['status'])) ?></span>
                    <?php if (! empty($s['biodata_at'])): ?><span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Biodata &#10003;</span><?php endif; ?>
                </div>
            </div>
            <a href="<?= esc($urlEdit, 'attr') ?>" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit data
            </a>
        </div>
    </div>

    <!-- Data per bagian -->
    <div class="grid gap-5 md:grid-cols-2">
        <?php foreach ($d['bagian'] as [$judul, $baris]): ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500"><?= esc($judul) ?></h2>
                <dl class="divide-y divide-slate-100">
                    <?php foreach ($baris as [$kunci, $label, $nilai]): ?>
                        <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm" data-kunci="<?= esc($kunci, 'attr') ?>">
                            <dt class="col-span-2 text-slate-500"><?= esc($label) ?></dt>
                            <dd class="col-span-3 break-words font-medium text-slate-800"><?= $nilai !== null && $nilai !== '' ? esc($nilai) : $kosong ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>

        <!-- Biodata -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Isian Biodata</h2>
            <dl class="divide-y divide-slate-100">
                <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                    <dt class="col-span-2 text-slate-500">Disahkan</dt>
                    <dd class="col-span-3 font-medium text-slate-800"><?= $bio['disahkan_pada'] !== null ? esc($bio['disahkan_pada']) : 'Belum disahkan' ?></dd>
                </div>
                <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                    <dt class="col-span-2 text-slate-500">Kelengkapan</dt>
                    <dd class="col-span-3 font-medium text-slate-800">
                        <?php if ($bio['kurang'] === []): ?>
                            <span class="text-emerald-700">Lengkap</span>
                        <?php else: ?>
                            <?= count($bio['kurang']) ?> kolom wajib belum terisi
                            <span class="mt-1 block text-xs font-normal text-slate-500"><?= esc(implode(', ', $bio['kurang'])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if ($bio['isian'] !== null): ?>
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Isian siswa</dt>
                        <dd class="col-span-3 font-medium text-slate-800">
                            <?= esc($bio['isian']['status_label']) ?>
                            <?php if ($bolehBio): ?><a href="<?= site_url('admin/biodata/' . (int) $bio['isian']['id']) ?>" class="ml-1 font-semibold text-brand-700 hover:underline">Buka &rarr;</a><?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <!-- PKL -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">PKL / Prakerin</h2>
            <?php if ($d['pkl'] === null): ?>
                <p class="px-5 py-4 text-sm text-slate-500">Belum punya ajuan PKL yang aktif.</p>
            <?php else: $p = $d['pkl']; [$labelPkl, $warnaPkl] = $statusPkl[$p['status']] ?? [ucfirst($p['status']), 'bg-slate-100 text-slate-600 border-slate-200']; ?>
                <dl class="divide-y divide-slate-100">
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Status</dt>
                        <dd class="col-span-3"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaPkl ?>"><?= esc($labelPkl) ?></span></dd>
                    </div>
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Perusahaan</dt>
                        <dd class="col-span-3 break-words font-medium text-slate-800"><?= esc($p['perusahaan']) ?><?= ! empty($p['kota']) ? ' &middot; ' . esc($p['kota']) : '' ?></dd>
                    </div>
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Peran di ajuan</dt>
                        <dd class="col-span-3 font-medium text-slate-800"><?= $p['peran'] === 'pengaju' ? 'Pengaju' : 'Teman satu tempat' ?></dd>
                    </div>
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Nomor surat</dt>
                        <dd class="col-span-3 font-medium text-slate-800"><?= $p['nomor_surat'] !== null ? esc($p['nomor_surat']) : $kosong ?></dd>
                    </div>
                    <div class="grid grid-cols-5 gap-3 px-5 py-2.5 text-sm">
                        <dt class="col-span-2 text-slate-500">Nomor bukti</dt>
                        <dd class="col-span-3 font-medium text-slate-800"><?= esc($p['kode']) ?>
                            <?php if ($bolehPkl): ?><a href="<?= site_url('admin/pkl/' . (int) $p['ajuan_id']) ?>" class="ml-1 font-semibold text-brand-700 hover:underline">Buka ajuan &rarr;</a><?php endif; ?>
                        </dd>
                    </div>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <p class="px-1 text-xs text-slate-400">
        Dibuat <?= esc($d['jejak']['dibuat'] ?? '—') ?> &middot; terakhir diubah <?= esc($d['jejak']['diubah'] ?? '—') ?>
    </p>
</div>

<?= $this->endSection() ?>
