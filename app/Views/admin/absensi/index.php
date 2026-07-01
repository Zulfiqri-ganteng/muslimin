<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'absensi',
    'helpTitle' => 'Cara mengisi absensi guru',
    'helpBody'  => '<p>Pilih <b>tanggal</b>, sistem menampilkan semua sesi mengajar hari itu dari jadwal KBM. Semua <b>default Hadir</b> — Anda cukup menandai guru yang <b>Telat / Izin / Sakit / Alpa (Tidak Hadir)</b>. Saat status bukan Hadir, muncul kolom <b>Jam Masuk</b> (isi bebas sesuai aturan Anda) dan <b>Keterangan</b>. Gunakan <b>Set semua</b> di tiap guru untuk menandai seluruh sesinya sekaligus. Klik <b>Simpan Absensi</b>. Data ini juga tampil di halaman publik.</p>',
]) ?>

<?php
    $statusOpts = [
        'hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin',
        'sakit' => 'Sakit', 'alpa'  => 'Alpa (Tidak Hadir)',
    ];
    // Kelas warna untuk chip ringkasan (statis, agar ke-scan Tailwind).
    $chip = [
        'hadir' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'telat' => 'bg-amber-50 text-amber-700 border-amber-200',
        'izin'  => 'bg-sky-50 text-sky-700 border-sky-200',
        'sakit' => 'bg-violet-50 text-violet-700 border-violet-200',
        'alpa'  => 'bg-red-50 text-red-700 border-red-200',
    ];
?>

<!-- ===================== BAR ATAS: pilih tanggal + ringkasan ===================== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= esc($tanggal) ?>" onchange="this.form.submit()"
                       class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div class="pb-1">
                <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= $namaHari !== '' ? esc($namaHari) : 'Hari tak dikenal' ?>
                </span>
            </div>
            <noscript><button class="rounded-xl bg-brand-700 text-white font-bold px-5 py-2.5 text-sm">Lihat</button></noscript>
        </form>

        <!-- Ringkasan status -->
        <div class="flex flex-wrap gap-2">
            <?php foreach ($statusOpts as $k => $lbl): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold <?= $chip[$k] ?>">
                    <?= esc($lbl) ?>: <?= (int) ($ringkas[$k] ?? 0) ?>
                </span>
            <?php endforeach; ?>
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-slate-600">
                Total sesi: <?= (int) $total ?>
            </span>
        </div>
    </div>
</div>

<?php if (! $hariAktif): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Tanggal ini bukan hari sekolah aktif. Pilih tanggal lain.
    </div>
<?php elseif ($total === 0): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Belum ada jadwal KBM pada hari <?= esc($namaHari) ?>. Susun jadwal dulu di menu <b>Jadwal KBM</b>.
    </div>
<?php else: ?>

<form method="post" action="<?= site_url('admin/absensi/save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

    <div class="space-y-4">
        <?php $i = 0; foreach ($grup as $g): ?>
            <div x-data class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Header guru + set semua -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-3 border-b border-slate-100 bg-slate-50">
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-800 truncate"><?= esc($g['nama']) ?></h3>
                        <p class="text-xs text-slate-400"><?= esc($g['kode']) ?> &middot; <?= count($g['sesi']) ?> sesi</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <label class="text-xs font-semibold text-slate-500">Set semua:</label>
                        <select class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold focus:border-brand-500 outline-none"
                                @change="$dispatch('absen-setall', { gid: <?= (int) $g['guru_id'] ?>, val: $event.target.value }); $event.target.selectedIndex = 0">
                            <option value="">— pilih —</option>
                            <?php foreach ($statusOpts as $k => $lbl): ?>
                                <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Daftar sesi guru -->
                <div class="divide-y divide-slate-100">
                    <?php foreach ($g['sesi'] as $s): ?>
                        <div x-data="{ gid: <?= (int) $s['guru_id'] ?>, st: '<?= esc($s['status'], 'js') ?>', jm: '<?= esc($s['jam_masuk'], 'js') ?>', ket: '<?= esc($s['keterangan'], 'js') ?>' }"
                             x-on:absen-setall.window="if ($event.detail.gid === gid) { st = $event.detail.val; if (st === 'hadir') { jm = ''; ket = ''; } }"
                             class="p-4 border-l-4 transition"
                             :class="{
                                'border-emerald-400': st==='hadir', 'border-amber-400': st==='telat',
                                'border-sky-400': st==='izin', 'border-violet-400': st==='sakit', 'border-red-400': st==='alpa'
                             }">
                            <!-- hidden identitas sesi -->
                            <input type="hidden" name="rows[<?= $i ?>][kelas_id]"  value="<?= (int) $s['kelas_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][jam_id]"    value="<?= (int) $s['jam_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][guru_id]"   value="<?= (int) $s['guru_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][hari_id]"   value="<?= (int) $s['hari_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][mapel_id]"  value="<?= (int) ($s['mapel_id'] ?? 0) ?>">
                            <input type="hidden" name="rows[<?= $i ?>][jadwal_id]" value="<?= (int) $s['jadwal_id'] ?>">

                            <div class="flex flex-col md:flex-row md:items-center gap-3">
                                <!-- Info sesi -->
                                <div class="md:w-64 shrink-0">
                                    <p class="text-sm font-bold text-slate-700">Jam <?= esc($s['jam_ke']) ?>
                                        <span class="text-slate-400 font-normal text-xs">(<?= esc(substr($s['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($s['waktu_selesai'], 0, 5)) ?>)</span>
                                    </p>
                                    <p class="text-sm text-slate-600"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold"><?= esc($s['nama_mapel']) ?></span></p>
                                </div>

                                <!-- Status -->
                                <div class="shrink-0">
                                    <select name="rows[<?= $i ?>][status]" x-model="st"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                                        <?php foreach ($statusOpts as $k => $lbl): ?>
                                            <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Jam masuk + keterangan (muncul bila bukan hadir) -->
                                <div class="flex flex-col sm:flex-row gap-2 flex-1" x-show="st !== 'hadir'" x-cloak>
                                    <div>
                                        <input type="time" name="rows[<?= $i ?>][jam_masuk]" x-model="jm"
                                               class="w-full sm:w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none"
                                               title="Jam masuk (opsional)">
                                    </div>
                                    <input type="text" name="rows[<?= $i ?>][keterangan]" x-model="ket" maxlength="255"
                                           placeholder="Keterangan (opsional)…"
                                           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                                </div>
                            </div>
                        </div>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bar simpan (sticky bawah) -->
    <div class="sticky bottom-0 mt-5 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-white/90 backdrop-blur border-t border-slate-200 flex items-center justify-between gap-3">
        <p class="text-xs text-slate-400 hidden sm:block">Tanggal <b><?= esc($tanggal) ?></b> &middot; <?= esc($namaHari) ?> &middot; <?= (int) $total ?> sesi</p>
        <button type="submit" class="ml-auto inline-flex items-center gap-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-6 py-2.5 text-sm transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Simpan Absensi
        </button>
    </div>
</form>

<?php endif; ?>

<?= $this->endSection() ?>
