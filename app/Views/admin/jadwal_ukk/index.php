<?php
/**
 * Jadwal pelaksanaan UKK.
 *
 * @var array                          $rows          Baris jadwal halaman ini
 * @var array<int,int>                 $jumlahPenguji jadwal_ukk_id => jumlah penguji
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 * @var int                           $paketId
 * @var int                           $per
 * @var array<int,string>             $paketOpts
 * @var array<int,string>             $tempatOpts
 * @var array<int,string>             $tahunOpts
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/jadwal-ukk');
$defaults = [
    'paket_soal_id' => '', 'tempat_uji_id' => '', 'tahun_ajaran_id' => '',
    'tanggal_mulai' => date('Y-m-d'), 'tanggal_selesai' => '', 'sesi' => '', 'keterangan' => '',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_ukk',
    'helpTitle' => 'Jadwal UKK',
    'helpBody'  => '<p>Buat jadwal pelaksanaan UKK per paket soal (tanggal, sesi, dan tempat uji). Setelah jadwal
        dibuat, klik <b>Kelola Penguji</b> untuk menugaskan penguji internal (guru sekolah) dan/atau eksternal
        (DUDI/industri) pada jadwal tersebut.</p>',
]) ?>

<div x-data="{ open:false, mode:'add', actionUrl:'', form: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      defaults: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      openAdd(){ this.mode='add'; this.form=Object.assign({}, this.defaults); this.actionUrl='<?= $base ?>'; this.open=true; },
      openEdit(r){ this.mode='edit'; this.form=Object.assign({}, this.defaults, r); this.actionUrl='<?= $base ?>/'+r.id; this.open=true; } }">

    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari paket soal atau tempat uji..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="paket_soal_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua paket soal</option>
                <?php foreach ($paketOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $paketId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit title="Jumlah per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== '' || $paketId > 0): ?>
                <a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
            <div class="flex-1 hidden sm:block"></div>
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Jadwal
            </button>
        </form>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Daftar Jadwal UKK</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Paket Soal</th>
                        <th class="px-4 py-3 font-semibold">Tempat Uji</th>
                        <th class="px-4 py-3 font-semibold w-40">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Sesi</th>
                        <th class="px-4 py-3 font-semibold w-24 text-center">Penguji</th>
                        <th class="px-4 py-3 font-semibold w-48 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $paketId > 0 ? 'Tidak ada jadwal yang cocok.' : 'Belum ada jadwal UKK. Klik "Tambah Jadwal" untuk memulai.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['paket_kode'] ?? '—') ?></div>
                                <div class="text-xs text-slate-400"><?= esc($r['paket_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= esc($r['tempat_nama'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $tgl($r['tanggal_mulai']) ?>
                                <?php if (! empty($r['tanggal_selesai']) && $r['tanggal_selesai'] !== $r['tanggal_mulai']): ?>
                                    <div class="text-xs text-slate-400">s/d <?= $tgl($r['tanggal_selesai']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= esc($r['sesi'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-600 text-xs font-semibold w-7 h-7"><?= (int) ($jumlahPenguji[$r['id']] ?? 0) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="<?= $base ?>/penguji/<?= (int) $r['id'] ?>" title="Kelola Penguji"
                                       class="inline-flex items-center gap-1 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-2.5 py-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z"/></svg>
                                        Penguji
                                    </a>
                                    <a href="<?= site_url('admin/penilaian-ukk/jadwal/' . (int) $r['id']) ?>" title="Isi Nilai"
                                       class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 py-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3"/></svg>
                                        Nilai
                                    </a>
                                    <button type="button" title="Ubah"
                                            @click="openEdit(<?= htmlspecialchars(json_encode([
                                                'id' => $r['id'], 'paket_soal_id' => $r['paket_soal_id'],
                                                'tempat_uji_id' => $r['tempat_uji_id'], 'tahun_ajaran_id' => $r['tahun_ajaran_id'],
                                                'tanggal_mulai' => $r['tanggal_mulai'], 'tanggal_selesai' => $r['tanggal_selesai'],
                                                'sesi' => $r['sesi'], 'keterangan' => $r['keterangan'],
                                            ]), ENT_QUOTES) ?>)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus jadwal UKK ini? Penugasan penguji ikut terhapus; peserta yang sudah terdaftar TIDAK terhapus (hanya lepas dari jadwal ini)." title="Hapus"
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
                <?= $pager->only(['q', 'paket_soal_id', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Ubah -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jadwal UKK' : 'Ubah Jadwal UKK'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Paket Soal *</label>
                        <select name="paket_soal_id" x-model="form.paket_soal_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih paket soal —</option>
                            <?php foreach ($paketOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tempat Uji</label>
                        <select name="tempat_uji_id" x-model="form.tempat_uji_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ditentukan —</option>
                            <?php foreach ($tempatOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Ajaran</label>
                        <select name="tahun_ajaran_id" x-model="form.tahun_ajaran_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ditentukan —</option>
                            <?php foreach ($tahunOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" x-model="form.tanggal_mulai" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" x-model="form.tanggal_selesai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Sesi</label>
                        <input type="text" name="sesi" x-model="form.sesi" maxlength="50" placeholder="mis. Sesi 1 (08.00-12.00)" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
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
