<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap_absensi_v3',
    'helpTitle' => 'Rekap Absensi Guru',
    'helpBody'  => '<p>Ringkasan kehadiran tiap guru pada rentang tanggal yang dipilih. Rekap <b>hanya menghitung hari yang sudah diabsen</b> (hari yang Anda buka lalu klik Simpan) — hari libur atau yang belum dikelola tidak dihitung. <b>Total Hari</b> dihitung per hari (bukan per sesi) dan <b>menggabungkan dua sumber</b>: hari mengajar terjadwal + hari masuk kerja di luar jadwal. <b>Hadir</b> = Total − (Telat + Izin + Sakit + Alpa). Ubah <b>Dari</b>/<b>Sampai</b> lalu klik Tampilkan.</p>
        <p class="mt-2"><b>BARU — kolom &amp; filter Jabatan.</b> Kolom <b>Jabatan</b> menampilkan jabatan utama guru (warna kuning = jabatan struktural); arahkan kursor untuk melihat seluruh jabatannya bila lebih dari satu. Pakai filter <b>Jabatan</b> untuk melihat kelompok tertentu saja, mis. hanya para <b>Wakil Kepala Sekolah</b>. Jabatan diatur di <b>Master Data ▸ Jabatan</b> lalu dipasang ke guru lewat <b>Master Guru ▸ Atur Jabatan</b>.</p>
        <p class="mt-2">Tombol <b>Export</b> (PDF / Excel) <b>mengikuti filter yang sedang aktif</b> — bila Anda memfilter satu jabatan, berkas yang terunduh berisi kelompok itu saja dan judulnya mencantumkan nama jabatannya. Kolom Jabatan ikut tercetak, cocok sebagai lampiran perhitungan gaji atau SK tugas tambahan. Klik nama guru untuk melihat rincian tanggalnya.</p>',
]) ?>

<?php
    $jabatanId   = (int) ($jabatanId ?? 0);
    $jabatanOpts = $jabatanOpts ?? [];
    // Filter jabatan ikut terbawa ke tautan rincian & tombol export.
    $qs = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai)
        . ($jabatanId > 0 ? '&jabatan_id=' . $jabatanId : '');
    $cols = [
        'hadir' => ['Hadir', 'text-emerald-700'],
        'telat' => ['Telat', 'text-amber-700'],
        'izin'  => ['Izin', 'text-sky-700'],
        'sakit' => ['Sakit', 'text-violet-700'],
        'alpa'  => ['Alpa', 'text-red-700'],
    ];
?>

<!-- Filter rentang + export -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Dari</label>
                <input type="date" name="dari" value="<?= esc($dari) ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Sampai</label>
                <input type="date" name="sampai" value="<?= esc($sampai) ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Jabatan</label>
                <select name="jabatan_id" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua jabatan</option>
                    <?php foreach ($jabatanOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>" <?= $jabatanId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-5 py-2.5 text-sm transition">Tampilkan</button>
            <?php if ($jabatanId > 0): ?>
                <a href="<?= site_url('admin/absensi/rekap') ?>?dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>"
                   class="rounded-xl border border-slate-300 text-slate-600 font-semibold px-4 py-2.5 text-sm hover:bg-slate-50 transition">Reset</a>
            <?php endif; ?>
        </form>
        <?php if (! empty($rows)): ?>
            <div class="flex flex-wrap gap-2">
                <a href="<?= site_url('admin/absensi/rekap/excel') . $qs ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Excel
                </a>
                <a href="<?= site_url('admin/absensi/rekap/pdf') . $qs ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                    Export PDF
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
        <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p class="text-slate-500 font-semibold">Belum ada hari yang diabsen pada periode ini.</p>
        <p class="text-sm text-slate-400 mt-1">Rekap akan terisi setelah Anda membuka tanggal di menu <a href="<?= site_url('admin/absensi') ?>" class="text-brand-600 underline">Absensi Guru</a> lalu klik <b>Simpan Absensi</b>.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3">
            <p class="font-bold text-slate-800">Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></p>
            <p class="text-sm text-slate-400"><?= (int) ($hariTercatat ?? 0) ?> hari diabsen &middot; <?= count($rows) ?> guru</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold w-10 text-center">#</th>
                        <th class="px-4 py-2.5 font-semibold">Guru</th>
                        <th class="px-4 py-2.5 font-semibold w-56">Jabatan</th>
                        <th class="px-4 py-2.5 font-semibold w-24 text-center">Total Hari</th>
                        <?php foreach ($cols as [$lbl, $tc]): ?>
                            <th class="px-4 py-2.5 font-semibold w-20 text-center <?= $tc ?>"><?= $lbl ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $i => $r): ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-2.5 text-center text-slate-400"><?= $i + 1 ?></td>
                            <td class="px-4 py-2.5">
                                <a href="<?= site_url('admin/absensi/rekap/guru/' . (int) $r['id']) . $qs ?>" class="group inline-flex items-center gap-1.5 hover:text-brand-700">
                                    <span class="text-slate-400"><?= esc($r['kode']) ?></span> · <span class="font-semibold text-slate-700 group-hover:text-brand-700 group-hover:underline"><?= esc($r['nama']) ?></span>
                                    <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                            <td class="px-4 py-2.5">
                                <?php if (($r['jabatan'] ?? '') !== ''): ?>
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold <?= ! empty($r['struktural']) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-600 border-slate-200' ?>"
                                          title="<?= esc($r['jabatan_all'] ?? '', 'attr') ?>"><?= esc($r['jabatan']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold text-slate-700"><?= (int) $r['total'] ?></td>
                            <?php foreach (array_keys($cols) as $k): $v = (int) $r[$k]; ?>
                                <td class="px-4 py-2.5 text-center <?= $v > 0 ? $cols[$k][1] . ' font-bold' : 'text-slate-300' ?>"><?= $v ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-bold text-slate-700">
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5">TOTAL</td>
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5 text-center"><?= (int) $sum['total'] ?></td>
                        <?php foreach (array_keys($cols) as $k): ?>
                            <td class="px-4 py-2.5 text-center"><?= (int) $sum[$k] ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
