<?php
/**
 * Perbaikan / Maintenance / Penggantian komponen.
 *
 * @var array                         $rows          Baris perbaikan
 * @var \CodeIgniter\Pager\Pager|null $pager         Paginasi
 * @var string                        $q             Kata kunci
 * @var string                        $jenis         Filter jenis
 * @var int                           $per           Baris per halaman
 * @var array<int,string>             $asetOpts      Opsi aset
 * @var array<int,string>             $teknisiOpts   Opsi teknisi
 * @var array<int,string>             $sparepartOpts Opsi sparepart
 * @var array<int,string>             $kerusakanOpts Opsi kerusakan terbuka
 * @var array                         $jenisList     Daftar jenis
 * @var array                         $hasilList     Daftar hasil
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/perbaikan');
$warnaJenis = ['perbaikan' => 'bg-sky-50 text-sky-700', 'maintenance' => 'bg-violet-50 text-violet-700', 'penggantian' => 'bg-amber-50 text-amber-700'];
$warnaHasil = ['berhasil' => 'text-emerald-600', 'sebagian' => 'text-amber-600', 'gagal' => 'text-red-600', 'ganti_unit' => 'text-slate-500'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'perbaikan',
    'helpTitle' => 'Perbaikan & Maintenance',
    'helpBody'  => '<p>Catat tindakan atas aset: <b>perbaikan</b>, <b>maintenance</b> rutin, atau <b>penggantian komponen</b>. Bila mengisi bagian "Penggantian Komponen", stok sparepart otomatis <b>berkurang</b> (dan dipulihkan bila catatan ini dihapus).</p>
        <p class="mt-1">Kaitkan ke sebuah <b>kerusakan</b> lalu set status <b>Selesai</b> → kerusakan itu otomatis ditandai selesai & asetnya kembali <b>tersedia</b>.</p>',
]) ?>

<div x-data="{ open:false }">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari aset atau tindakan..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="jenis" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua jenis</option>
                <?php foreach ($jenisList as $j): ?><option value="<?= esc($j, 'attr') ?>" <?= $jenis === $j ? 'selected' : '' ?>><?= esc(ucfirst($j)) ?></option><?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?><option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option><?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== '' || $jenis !== ''): ?><a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a><?php endif; ?>
        </form>
        <div class="flex items-center justify-end border-t border-slate-100 mt-3 pt-3">
            <button @click="open=true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Catat Perbaikan
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Daftar Perbaikan</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Aset</th>
                        <th class="px-4 py-3 font-semibold w-28">Jenis</th>
                        <th class="px-4 py-3 font-semibold">Tindakan</th>
                        <th class="px-4 py-3 font-semibold w-40">Komponen</th>
                        <th class="px-4 py-3 font-semibold w-28">Tanggal</th>
                        <th class="px-4 py-3 font-semibold w-16 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400"><?= $q !== '' || $jenis !== '' ? 'Tidak ada perbaikan yang cocok.' : 'Belum ada catatan perbaikan.' ?></td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <div class="font-mono font-semibold text-brand-700"><?= esc($r['nomor_aset'] ?? '—') ?></div>
                                <div class="text-slate-700"><?= esc($r['aset_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $warnaJenis[$r['jenis']] ?? 'bg-slate-100 text-slate-600' ?>"><?= esc(ucfirst($r['jenis'])) ?></span></td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= esc($r['tindakan']) ?>
                                <div class="text-xs mt-0.5">
                                    <span class="font-semibold <?= $warnaHasil[$r['hasil']] ?? 'text-slate-500' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['hasil']))) ?></span>
                                    <?php if ($r['biaya'] !== null): ?><span class="text-slate-400"> · Rp<?= number_format((float) $r['biaya'], 0, ',', '.') ?></span><?php endif; ?>
                                    <?php if ($r['status'] === 'proses'): ?><span class="text-amber-600"> · proses</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <?php if (! empty($r['komponen_nama'])): ?>
                                    <?= esc($r['komponen_nama']) ?> <span class="text-slate-400">×<?= (int) $r['komponen_jumlah'] ?> <?= esc($r['komponen_satuan'] ?? '') ?></span>
                                <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $tgl($r['tanggal']) ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus catatan perbaikan ini? Stok komponen (bila ada) akan dipulihkan." title="Hapus"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?><div class="px-6 py-4 border-t border-slate-100"><?= $pager->only(['q', 'jenis', 'per'])->links('default', 'admin') ?></div><?php endif; ?>
    </div>

    <!-- Modal Catat Perbaikan -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Catat Perbaikan</h3>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Aset *</label>
                        <select name="aset_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih aset —</option>
                            <?php foreach ($asetOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kaitkan Kerusakan</label>
                        <select name="kerusakan_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak dikaitkan —</option>
                            <?php foreach ($kerusakanOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis</label>
                        <select name="jenis" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($jenisList as $j): ?><option value="<?= esc($j, 'attr') ?>"><?= esc(ucfirst($j)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tindakan *</label>
                        <input type="text" name="tindakan" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Hasil</label>
                        <select name="hasil" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($hasilList as $h): ?><option value="<?= esc($h, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $h))) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="selesai">Selesai</option>
                            <option value="proses">Proses</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Biaya (Rp)</label>
                        <input type="number" name="biaya" min="0" step="1" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Teknisi</label>
                        <select name="teknisi_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak dipilih —</option>
                            <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Penggantian komponen (opsional) -->
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                    <p class="text-sm font-semibold text-amber-800 mb-2">Penggantian Komponen (opsional)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Sparepart</label>
                            <select name="sparepart_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                                <option value="">— Tidak ada penggantian —</option>
                                <?php foreach ($sparepartOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Jumlah</label>
                            <input type="number" name="jumlah_komponen" min="0" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                        </div>
                    </div>
                    <p class="text-xs text-amber-700 mt-2">Bila diisi, stok sparepart berkurang otomatis sesuai jumlah.</p>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                    <input type="text" name="keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
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
