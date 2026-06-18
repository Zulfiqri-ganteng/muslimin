<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'kesediaan',
    'helpTitle' => 'Data Kesediaan Guru',
    'helpBody'  => '<p>Rekap isian formulir kesediaan mengajar yang dikirim guru (preferensi mapel, hari/jam tersedia, tugas tambahan). Data ini bisa <b>diimpor menjadi Master Guru</b> lewat menu Guru ▸ Import ▸ Impor dari Data Kesediaan.</p>',
]) ?>

<!-- Toolbar -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-center gap-3">
        <form method="get" class="flex flex-1 flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nama, NIP/NUPTK, atau mapel..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                <option value="">Semua Status</option>
                <?php foreach (['PNS','PPPK','GTY','GTT'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">Cari</button>
            <?php if ($q || $status): ?>
                <a href="<?= site_url('admin/submissions') ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition text-center">Reset</a>
            <?php endif; ?>
        </form>
        <div class="flex gap-2">
            <a href="<?= site_url('admin/export/excel' . ($status ? '?status='.$status : '')) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </a>
            <a href="<?= site_url('admin/export/rekap-pdf' . ($status ? '?status='.$status : '')) ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                Rekap PDF
            </a>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-bold text-slate-800">Daftar Kesediaan <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
    </div>

    <?php if (empty($rows)): ?>
        <div class="p-12 text-center">
            <p class="text-slate-400">Belum ada data<?= ($q || $status) ? ' yang cocok dengan filter' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 bg-slate-50 border-b border-slate-100">
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Nama Guru</th>
                        <th class="px-4 py-3 font-semibold hidden md:table-cell">NIP / NUPTK</th>
                        <th class="px-4 py-3 font-semibold hidden lg:table-cell">Mapel</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-center hidden sm:table-cell">Jam</th>
                        <th class="px-4 py-3 font-semibold hidden lg:table-cell">Tanggal</th>
                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $i => $r): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-700"><?= esc($r['nama_lengkap']) ?></p>
                                <p class="text-xs text-slate-400 md:hidden"><?= esc($r['nip_nuptk']) ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= esc($r['nip_nuptk']) ?></td>
                            <td class="px-4 py-3 text-slate-500 hidden lg:table-cell"><?= esc($r['guru_mapel'] ?: '-') ?></td>
                            <td class="px-4 py-3"><span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700"><?= esc($r['status_kepegawaian']) ?></span></td>
                            <td class="px-4 py-3 text-center text-slate-600 hidden sm:table-cell"><?= $r['total_jam'] ?></td>
                            <td class="px-4 py-3 text-slate-400 text-xs hidden lg:table-cell"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="<?= site_url('admin/submissions/view/' . $r['id']) ?>" title="Lihat detail" class="p-1.5 rounded-lg text-brand-600 hover:bg-brand-50"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                    <a href="<?= site_url('admin/export/surat/' . $r['id']) ?>" target="_blank" title="Cetak surat (PDF)" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
                                    <a href="<?= site_url('admin/submissions/delete/' . $r['id']) ?>" onclick="return confirm('Hapus data kesediaan dari <?= esc($r['nama_lengkap'], 'js') ?>?')" title="Hapus" class="p-1.5 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'status'])->links() ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
