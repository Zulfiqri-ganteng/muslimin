<?php
/**
 * Peminjaman & Pengembalian aset lab.
 *
 * @var array                         $rows        Baris peminjaman halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci pencarian
 * @var string                        $status      Filter status aktif
 * @var int                           $per         Baris per halaman
 * @var string                        $today       Tanggal hari ini (Y-m-d)
 * @var int                           $sedang      Jumlah sedang dipinjam
 * @var int                           $terlambat   Jumlah terlambat
 * @var array<int,string>             $asetOpts    Opsi aset tersedia
 * @var array<int,string>             $petugasOpts Opsi petugas (teknisi)
 * @var array                         $statusList  Daftar status sah
 * @var array                         $kondisiList Daftar kondisi sah
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$warnaTipe = [
    'guru'  => 'bg-brand-50 text-brand-700',
    'siswa' => 'bg-sky-50 text-sky-700',
    'umum'  => 'bg-slate-100 text-slate-500',
];
$base = site_url('admin/peminjaman');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'peminjaman',
    'helpTitle' => 'Peminjaman & Pengembalian',
    'helpBody'  => '<p>Catat peminjaman barang lab. Saat dipinjam, status aset otomatis menjadi <b>dipinjam</b> (tak bisa dipinjam ganda). Peminjam boleh siapa saja (guru/siswa/umum) — cukup ketik namanya.</p>
        <p class="mt-1">• Klik <b>Kembalikan</b> pada baris untuk memproses pengembalian: isi tanggal & kondisi barang, status aset otomatis kembali <b>tersedia</b>.<br>
        • Barang yang <b>hilang</b> bisa ditandai saat pengembalian (aset ditarik dari daftar; bisa dipulihkan lewat Master Aset).<br>
        • Baris <b>Terlambat</b> = masih dipinjam melewati tanggal rencana kembali.</p>',
]) ?>

<div x-data="{ pinjamOpen:false, kembaliOpen:false, retAction:'', retLabel:'',
      openKembali(id,label){ this.retAction='<?= $base ?>/kembalikan/'+id; this.retLabel=label; this.kembaliOpen=true; } }">

    <!-- Ringkasan + toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari aset atau nama peminjam..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua status</option>
                <?php foreach ($statusList as $s): ?>
                    <option value="<?= esc($s, 'attr') ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit title="Jumlah per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== '' || $status !== ''): ?>
                <a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
        </form>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 mt-3 pt-3">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 text-sky-700 text-xs font-semibold px-3 py-1.5">Sedang dipinjam: <?= (int) $sedang ?></span>
            <?php if ($terlambat > 0): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold px-3 py-1.5">Terlambat: <?= (int) $terlambat ?></span>
            <?php endif; ?>
            <div class="flex-1"></div>
            <button @click="pinjamOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Pinjamkan Barang
            </button>
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Daftar Peminjaman</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Aset</th>
                        <th class="px-4 py-3 font-semibold">Peminjam</th>
                        <th class="px-4 py-3 font-semibold w-28">Tgl Pinjam</th>
                        <th class="px-4 py-3 font-semibold w-32">Rencana Kembali</th>
                        <th class="px-4 py-3 font-semibold w-28">Status</th>
                        <th class="px-4 py-3 font-semibold w-40 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $status !== '' ? 'Tidak ada peminjaman yang cocok.' : 'Belum ada peminjaman. Klik "Pinjamkan Barang" untuk mencatat.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r):
                        $late = $r['status'] === 'dipinjam' && $r['tanggal_kembali_rencana'] && $r['tanggal_kembali_rencana'] < $today;
                        $label = ($r['nomor_aset'] ?? '-') . ' — ' . ($r['aset_nama'] ?? '-'); ?>
                        <tr class="hover:bg-slate-50 <?= $late ? 'bg-red-50/30' : '' ?>">
                            <td class="px-6 py-3">
                                <div class="font-mono font-semibold text-brand-700"><?= esc($r['nomor_aset'] ?? '—') ?></div>
                                <div class="text-slate-700"><?= esc($r['aset_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['peminjam_nama']) ?></div>
                                <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $warnaTipe[$r['peminjam_tipe']] ?? 'bg-slate-100 text-slate-500' ?>"><?= esc(ucfirst($r['peminjam_tipe'])) ?></span>
                                <?php if (! empty($r['tujuan'])): ?><div class="text-xs text-slate-400 mt-0.5"><?= esc($r['tujuan']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $tgl($r['tanggal_pinjam']) ?></td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $tgl($r['tanggal_kembali_rencana']) ?>
                                <?php if ($r['status'] !== 'dipinjam' && $r['tanggal_kembali_aktual']): ?>
                                    <div class="text-xs text-slate-400">kembali: <?= $tgl($r['tanggal_kembali_aktual']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                if ($late) {
                                    $cls = 'bg-red-50 text-red-700 border-red-200';
                                    $txt = 'Terlambat';
                                } elseif ($r['status'] === 'dipinjam') {
                                    $cls = 'bg-sky-50 text-sky-700 border-sky-200';
                                    $txt = 'Dipinjam';
                                } elseif ($r['status'] === 'dikembalikan') {
                                    $cls = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                    $txt = 'Dikembalikan';
                                } else {
                                    $cls = 'bg-slate-100 text-slate-500 border-slate-200';
                                    $txt = ucfirst($r['status']);
                                }
                                ?>
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $cls ?>"><?= esc($txt) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="<?= site_url('admin/lab-gambar/peminjaman/' . (int) $r['id']) ?>" title="Foto" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </a>
                                    <?php if ($r['status'] === 'dipinjam'): ?>
                                        <button type="button" @click="openKembali(<?= (int) $r['id'] ?>, '<?= esc($label, 'js') ?>')"
                                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 py-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h11M9 21V3m0 0l6 6m-6-6L3 9"/></svg>
                                            Kembalikan
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus catatan peminjaman ini?" title="Hapus"
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
                <?= $pager->only(['q', 'status', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Pinjam -->
    <div x-show="pinjamOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="pinjamOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Pinjamkan Barang</h3>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Aset (yang tersedia) *</label>
                        <select name="aset_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih aset —</option>
                            <?php foreach ($asetOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($asetOpts)): ?>
                            <p class="text-xs text-amber-600 mt-1">Tidak ada aset berstatus "tersedia". Tambahkan/ubah di Master Aset.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Peminjam *</label>
                        <input type="text" name="peminjam_nama" required maxlength="150" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tipe Peminjam</label>
                        <select name="peminjam_tipe" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="umum">Umum</option>
                            <option value="guru">Guru</option>
                            <option value="siswa">Siswa</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tujuan / Keperluan</label>
                        <input type="text" name="tujuan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Pinjam</label>
                        <input type="date" name="tanggal_pinjam" value="<?= $today ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Rencana Kembali</label>
                        <input type="date" name="tanggal_kembali_rencana" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kondisi Saat Pinjam</label>
                        <select name="kondisi_pinjam" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($kondisiList as $k): ?>
                                <option value="<?= esc($k, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $k))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Petugas</label>
                        <select name="petugas_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak dipilih —</option>
                            <?php foreach ($petugasOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="pinjamOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kembalikan -->
    <div x-show="kembaliOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="kembaliOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Proses Pengembalian</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="retLabel"></p>
            <form method="post" :action="retAction">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="dikembalikan">Dikembalikan</option>
                            <option value="hilang">Hilang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Kembali</label>
                        <input type="date" name="tanggal_kembali_aktual" value="<?= $today ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kondisi Saat Kembali</label>
                        <select name="kondisi_kembali" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak diubah —</option>
                            <?php foreach ($kondisiList as $k): ?>
                                <option value="<?= esc($k, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $k))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Bila diisi, kondisi aset ikut diperbarui.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="kembaliOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
