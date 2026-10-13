<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'audit',
    'helpTitle' => 'Audit Log',
    'helpBody'  => '<p>Jejak seluruh aktivitas admin (tambah/ubah/hapus/import) pada data sistem — beserta waktu, pelaku, dan alamat IP. Berguna untuk pelacakan & akuntabilitas. Gunakan tombol bersihkan untuk membuang log lama agar database tetap ringan.</p>',
]) ?>

<?php
$badge = [
    'create' => 'bg-emerald-100 text-emerald-700',
    'update' => 'bg-brand-100 text-brand-700',
    'delete' => 'bg-red-100 text-red-700',
    'import' => 'bg-teal-100 text-teal-700',
];
?>
<!-- Toolbar -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-4 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
        <form method="get" class="flex flex-1 flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari keterangan..."
                       class="w-full rounded-lg border border-gray-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-hidden">
            </div>
            <select name="aksi" class="rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-hidden">
                <option value="">Semua Aksi</option>
                <?php foreach (['create' => 'Tambah', 'update' => 'Ubah', 'delete' => 'Hapus', 'import' => 'Import'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $aksi === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tabel" class="rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-hidden">
                <option value="">Semua Tabel</option>
                <?php foreach ($tabelList as $t): ?>
                    <option value="<?= esc($t) ?>" <?= $tabel === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">Cari</button>
            <?php if ($q || $aksi || $tabel): ?>
                <a href="<?= site_url('admin/audit') ?>" class="rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2.5 hover:bg-gray-50 transition text-center">Reset</a>
            <?php endif; ?>
        </form>
        <a href="<?= site_url('admin/audit/purge') ?>" data-method="post" data-confirm="Hapus semua log yang lebih lama dari 90 hari?"
           class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Bersihkan &gt;90 hari
        </a>
    </div>
</div>

<!-- Tabel -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800">Riwayat Aktivitas <span class="text-gray-400 font-normal">(<?= $total ?>)</span></h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold w-40">Waktu</th>
                    <th class="px-6 py-3 font-semibold">Admin</th>
                    <th class="px-6 py-3 font-semibold w-24">Aksi</th>
                    <th class="px-6 py-3 font-semibold w-32">Tabel</th>
                    <th class="px-6 py-3 font-semibold">Keterangan</th>
                    <th class="px-6 py-3 font-semibold w-28">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Belum ada aktivitas tercatat.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-gray-500 whitespace-nowrap"><?= esc(date('d-m-Y H:i', strtotime($r['created_at']))) ?></td>
                        <td class="px-6 py-3"><?= esc($r['admin_nama'] ?: '—') ?></td>
                        <td class="px-6 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $badge[$r['aksi']] ?? 'bg-gray-100 text-gray-600' ?>"><?= esc($r['aksi']) ?></span></td>
                        <td class="px-6 py-3 text-gray-500"><?= esc($r['tabel']) ?><?= $r['record_id'] ? ' #' . esc($r['record_id']) : '' ?></td>
                        <td class="px-6 py-3 text-gray-600"><?= esc($r['deskripsi'] ?: '—') ?></td>
                        <td class="px-6 py-3 text-gray-400 text-xs"><?= esc($r['ip_address'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager): ?>
        <div class="px-6 py-4 border-t border-gray-100"><?= $pager->only(['q', 'aksi', 'tabel'])->links() ?></div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
