<?php
/**
 * Berita Acara pelaksanaan UKK.
 *
 * @var array                          $rows       Baris berita acara halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 * @var int                           $per
 * @var array<int,string>             $jadwalOpts Opsi jadwal UKK (untuk Tambah)
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/berita-acara-ukk');
$defaults = ['jadwal_ukk_id' => '', 'tanggal' => date('Y-m-d'), 'catatan' => '', 'keterangan' => ''];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'berita_acara_ukk',
    'helpTitle' => 'Berita Acara UKK',
    'helpBody'  => '<p>Buat berita acara resmi pelaksanaan UKK untuk satu jadwal. Nomor dibuat otomatis (format
        <code>BA-UKK-{tahun}-001</code>). Klik <b>Cetak PDF</b> untuk dokumen siap tanda tangan berisi daftar
        peserta + penguji.</p>',
]) ?>

<div x-data="{ open:false, mode:'add', actionUrl:'', form: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      defaults: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      openAdd(){ this.mode='add'; this.form=Object.assign({}, this.defaults); this.actionUrl='<?= $base ?>'; this.open=true; },
      openEdit(r){ this.mode='edit'; this.form=Object.assign({}, this.defaults, r); this.actionUrl='<?= $base ?>/'+r.id; this.open=true; } }">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nomor BA atau paket soal..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== ''): ?>
                <a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
            <div class="flex-1 hidden sm:block"></div>
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Buat Berita Acara
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Daftar Berita Acara</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Nomor</th>
                        <th class="px-4 py-3 font-semibold">Paket Soal</th>
                        <th class="px-4 py-3 font-semibold w-28">Tanggal</th>
                        <th class="px-4 py-3 font-semibold w-40 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' ? 'Tidak ada berita acara yang cocok.' : 'Belum ada berita acara. Klik "Buat Berita Acara" untuk memulai.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-semibold text-brand-700"><?= esc($r['nomor_ba']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-700"><?= esc($r['paket_nama'] ?? '—') ?></div>
                                <div class="text-xs text-slate-400"><?= esc($r['tempat_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $tgl($r['tanggal']) ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="<?= $base ?>/pdf/<?= (int) $r['id'] ?>" target="_blank" title="Cetak PDF"
                                       class="inline-flex items-center gap-1 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-2.5 py-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-8 4h4v-6H8v6z"/></svg>
                                        PDF
                                    </a>
                                    <button type="button" title="Ubah"
                                            @click="openEdit(<?= htmlspecialchars(json_encode([
                                                'id' => $r['id'], 'tanggal' => $r['tanggal'],
                                                'catatan' => $r['catatan'], 'keterangan' => $r['keterangan'],
                                            ]), ENT_QUOTES) ?>)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus berita acara ini?" title="Hapus"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Ubah -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Buat Berita Acara' : 'Ubah Berita Acara'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div x-show="mode==='add'">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jadwal UKK *</label>
                        <select name="jadwal_ukk_id" x-model="form.jadwal_ukk_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih jadwal —</option>
                            <?php foreach ($jadwalOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal *</label>
                        <input type="date" name="tanggal" x-model="form.tanggal" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Catatan Pelaksanaan</label>
                        <textarea name="catatan" x-model="form.catatan" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
