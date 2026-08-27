<?php
/**
 * Kelola penugasan penguji (internal/eksternal) untuk satu jadwal UKK.
 *
 * @var array              $jadwal        Baris jadwal_ukk::withRelations()
 * @var array              $list          Penguji tertugas (JadwalUkkPengujiModel::forJadwal)
 * @var array<int,string>  $guruOpts      Opsi guru (internal)
 * @var array<int,string>  $eksternalOpts Opsi penguji eksternal
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/jadwal-ukk/penguji/' . (int) $jadwal['id']);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_ukk_penguji',
    'helpTitle' => 'Penugasan Penguji',
    'helpBody'  => '<p>Tugaskan penguji <b>internal</b> (guru sekolah) dan/atau <b>eksternal</b> (DUDI/industri) untuk
        jadwal UKK ini. Tandai salah satu sebagai <b>Ketua</b> bila perlu. Penguji yang sudah ditugaskan akan muncul
        di pilihan penilaian pada tahap Penilaian.</p>',
]) ?>

<a href="<?= site_url('admin/jadwal-ukk') ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mb-4">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali ke Jadwal UKK
</a>

<!-- Info jadwal -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-bold text-slate-800 text-lg"><?= esc($jadwal['paket_kode'] ?? '—') ?> — <?= esc($jadwal['paket_nama'] ?? '—') ?></h2>
            <p class="text-sm text-slate-500 mt-0.5">
                <?= $tgl($jadwal['tanggal_mulai']) ?><?= ! empty($jadwal['tanggal_selesai']) && $jadwal['tanggal_selesai'] !== $jadwal['tanggal_mulai'] ? ' s/d ' . $tgl($jadwal['tanggal_selesai']) : '' ?>
                <?= ! empty($jadwal['sesi']) ? ' · ' . esc($jadwal['sesi']) : '' ?>
                <?= ! empty($jadwal['tempat_nama']) ? ' · ' . esc($jadwal['tempat_nama']) : '' ?>
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Daftar penguji tertugas -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Penguji Tertugas (<?= count($list) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold w-24">Tipe</th>
                        <th class="px-4 py-3 font-semibold w-24">Peran</th>
                        <th class="px-4 py-3 font-semibold w-16 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($list)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">Belum ada penguji ditugaskan.</td></tr>
                    <?php else: foreach ($list as $p):
                        $internal = $p['tipe'] === 'internal';
                        $nama     = $internal ? ($p['guru_nama'] ?? '—') : ($p['eksternal_nama'] ?? '—');
                        $sub      = $internal ? ($p['kode_guru'] ?? '') : ($p['eksternal_instansi'] ?? ''); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <div class="font-medium text-slate-800"><?= esc($nama) ?></div>
                                <?php if ($sub !== ''): ?><div class="text-xs text-slate-400"><?= esc($sub) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold <?= $internal ? 'bg-brand-50 text-brand-700 border-brand-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                                    <?= $internal ? 'Internal' : 'Eksternal' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= esc(ucfirst($p['peran'])) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= $base ?>/hapus/<?= (int) $p['id'] ?>" data-confirm="Lepas penugasan penguji ini?" title="Hapus"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form tugaskan penguji -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5" x-data="{ tipe: 'internal' }">
        <h2 class="font-bold text-slate-800 mb-4">Tugaskan Penguji</h2>
        <form method="post" action="<?= $base ?>">
            <?= csrf_field() ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tipe Penguji</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                            <input type="radio" name="tipe" value="internal" x-model="tipe" class="text-brand-600 focus:ring-brand-500"> Internal (guru)
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                            <input type="radio" name="tipe" value="eksternal" x-model="tipe" class="text-brand-600 focus:ring-brand-500"> Eksternal (DUDI)
                        </label>
                    </div>
                </div>
                <div x-show="tipe === 'internal'">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Guru *</label>
                    <select name="guru_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="">— Pilih guru —</option>
                        <?php foreach ($guruOpts as $id => $lbl): ?>
                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($guruOpts)): ?>
                        <p class="text-xs text-amber-600 mt-1">Belum ada data guru. Tambahkan di Master Guru.</p>
                    <?php endif; ?>
                </div>
                <div x-show="tipe === 'eksternal'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Penguji Eksternal *</label>
                    <select name="penguji_eksternal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="">— Pilih penguji —</option>
                        <?php foreach ($eksternalOpts as $id => $lbl): ?>
                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($eksternalOpts)): ?>
                        <p class="text-xs text-amber-600 mt-1">Belum ada data penguji eksternal. Tambahkan di Master Penguji Eksternal.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Peran</label>
                    <select name="peran" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="anggota">Anggota</option>
                        <option value="ketua">Ketua</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Tugaskan</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
