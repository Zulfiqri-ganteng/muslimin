<?php
/**
 * Kerusakan aset lab.
 *
 * @var array                         $rows        Baris kerusakan
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci
 * @var string                        $status      Filter status
 * @var int                           $per         Baris per halaman
 * @var int                           $terbuka     Jumlah kerusakan terbuka
 * @var array<int,string>             $asetOpts    Opsi aset
 * @var array<int,string>             $teknisiOpts Opsi teknisi
 * @var array                         $tingkatList Daftar tingkat
 * @var array                         $statusList  Daftar status
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/kerusakan');
$warnaTingkat = ['ringan' => 'bg-slate-100 text-slate-600', 'sedang' => 'bg-amber-50 text-amber-700', 'berat' => 'bg-red-50 text-red-700'];
$warnaStatus  = [
    'dilaporkan'   => 'bg-amber-50 text-amber-700 border-amber-200',
    'diproses'     => 'bg-sky-50 text-sky-700 border-sky-200',
    'selesai'      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'tak_teratasi' => 'bg-slate-100 text-slate-500 border-slate-200',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'kerusakan',
    'helpTitle' => 'Kerusakan Aset',
    'helpBody'  => '<p>Catat laporan kerusakan aset. Saat dilaporkan, status aset otomatis menjadi <b>perbaikan</b>. Ubah status ke <b>Selesai</b> jika sudah beres — aset otomatis kembali <b>tersedia</b> (bila tak ada kerusakan lain).</p>
        <p class="mt-1">Untuk mencatat <b>tindakan perbaikan</b> (termasuk penggantian komponen yang mengurangi stok sparepart), buka menu <b>Perbaikan</b> lalu kaitkan ke kerusakan ini.</p>',
]) ?>

<div x-data="{ laporOpen:false, statusOpen:false, stAction:'', stLabel:'',
      openStatus(id,label){ this.stAction='<?= $base ?>/status/'+id; this.stLabel=label; this.statusOpen=true; } }">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari aset atau deskripsi..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua status</option>
                <?php foreach ($statusList as $s): ?>
                    <option value="<?= esc($s, 'attr') ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?><option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option><?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== '' || $status !== ''): ?><a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a><?php endif; ?>
        </form>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 mt-3 pt-3">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold px-3 py-1.5">Kerusakan terbuka: <?= (int) $terbuka ?></span>
            <div class="flex-1"></div>
            <button @click="laporOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Lapor Kerusakan
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Daftar Kerusakan</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Aset</th>
                        <th class="px-4 py-3 font-semibold">Deskripsi</th>
                        <th class="px-4 py-3 font-semibold w-24">Tingkat</th>
                        <th class="px-4 py-3 font-semibold w-28">Tgl Lapor</th>
                        <th class="px-4 py-3 font-semibold w-28">Status</th>
                        <th class="px-4 py-3 font-semibold w-32 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400"><?= $q !== '' || $status !== '' ? 'Tidak ada kerusakan yang cocok.' : 'Belum ada laporan kerusakan.' ?></td></tr>
                    <?php else: foreach ($rows as $r):
                        $open = in_array($r['status'], ['dilaporkan', 'diproses'], true);
                        $label = ($r['nomor_aset'] ?? '-') . ' — ' . mb_substr((string) $r['deskripsi'], 0, 40); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <div class="font-mono font-semibold text-brand-700"><?= esc($r['nomor_aset'] ?? '—') ?></div>
                                <div class="text-slate-700"><?= esc($r['aset_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= esc($r['deskripsi']) ?>
                                <?php if (! empty($r['pelapor'])): ?><div class="text-xs text-slate-400">Pelapor: <?= esc($r['pelapor']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $warnaTingkat[$r['tingkat']] ?? 'bg-slate-100 text-slate-600' ?>"><?= esc(ucfirst($r['tingkat'])) ?></span></td>
                            <td class="px-4 py-3 text-slate-600"><?= $tgl($r['tanggal_lapor']) ?></td>
                            <td class="px-4 py-3"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['status']))) ?></span></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="<?= site_url('admin/lab-gambar/kerusakan/' . (int) $r['id']) ?>" title="Foto" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </a>
                                    <?php if ($open): ?>
                                        <button type="button" @click="openStatus(<?= (int) $r['id'] ?>, '<?= esc($label, 'js') ?>')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs font-semibold px-2.5 py-1.5">Ubah Status</button>
                                    <?php endif; ?>
                                    <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus laporan kerusakan ini?" title="Hapus"
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
        <?php if ($pager): ?><div class="px-6 py-4 border-t border-slate-100"><?= $pager->only(['q', 'status', 'per'])->links('default', 'admin') ?></div><?php endif; ?>
    </div>

    <!-- Modal Lapor -->
    <div x-show="laporOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="laporOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Lapor Kerusakan</h3>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Aset *</label>
                        <select name="aset_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih aset —</option>
                            <?php foreach ($asetOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi Kerusakan *</label>
                        <input type="text" name="deskripsi" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tingkat</label>
                        <select name="tingkat" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($tingkatList as $t): ?><option value="<?= esc($t, 'attr') ?>"><?= esc(ucfirst($t)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Lapor</label>
                        <input type="date" name="tanggal_lapor" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Pelapor</label>
                        <input type="text" name="pelapor" maxlength="150" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Teknisi</label>
                        <select name="teknisi_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak dipilih —</option>
                            <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="laporOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Status -->
    <div x-show="statusOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="statusOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Ubah Status Kerusakan</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="stLabel"></p>
            <form method="post" :action="stAction">
                <?= csrf_field() ?>
                <label class="block text-sm font-medium text-slate-600 mb-1">Status Baru</label>
                <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="diproses">Diproses</option>
                    <option value="selesai">Selesai</option>
                    <option value="tak_teratasi">Tak Teratasi</option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Selesai / Tak teratasi akan membebaskan aset (jika tak ada kerusakan lain).</p>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="statusOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
