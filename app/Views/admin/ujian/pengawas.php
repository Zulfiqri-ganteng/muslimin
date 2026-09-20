<?php
/**
 * Kelola pengawas satu sesi ujian.
 *
 * Halaman terpisah (pola `admin/jadwal_ukk/penguji`) karena isinya daftar
 * penugasan, bukan satu form. Pengawas SEPENUHNYA OPSIONAL — sesi ujian tetap
 * sah walau daftar ini dibiarkan kosong.
 *
 * @var string            $slug
 * @var array             $periode
 * @var string            $label
 * @var array             $jadwal    baris jadwal + relasinya
 * @var array<int,array>  $list      pengawas yang sudah ditugaskan
 * @var array<int,string> $guruOpts
 * @var array<int,string> $peranList
 * @var string            $kembali   URL kembali ke tab jadwal
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$ts   = strtotime((string) $jadwal['tanggal']);
$jam  = $jadwal['jam_mulai']
    ? substr((string) $jadwal['jam_mulai'], 0, 5) . ($jadwal['jam_selesai'] ? '–' . substr((string) $jadwal['jam_selesai'], 0, 5) : '')
    : 'Sehari penuh';

$aksi        = site_url('admin/ujian/' . $slug . '/pengawas/' . $jadwal['id']);
$labelPeran  = ['pengawas' => 'Pengawas', 'cadangan' => 'Cadangan'];
$warnaPeran  = [
    'pengawas' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'cadangan' => 'bg-slate-100 text-slate-600 border-slate-200',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ujian_pengawas_v1',
    'helpTitle' => 'Pengawas Ujian',
    'helpBody'  => '<p>Pendataan guru yang mengawasi sesi ujian ini. Bagian ini <b>opsional</b> — jadwal tetap
        sah walau tidak diisi, jadi silakan lewati bila sekolah belum menetapkan pengawas.</p>
        <p class="mt-1">• Kosongkan kolom <b>Ruang</b> untuk mengikuti ruang sesi ujiannya.<br>
        • Seorang guru tidak bisa ditugaskan dua kali pada sesi yang sama, dan juga ditolak bila jamnya
        bertabrakan dengan sesi lain yang sudah ia awasi — termasuk sesi dari gelombang ujian berbeda.</p>',
]) ?>

<!-- Identitas sesi -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?= esc($label) ?></p>
            <h2 class="font-bold text-slate-800 text-lg mt-0.5"><?= esc($jadwal['nama_mapel'] ?? 'Mapel sudah dihapus') ?></h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?>
                &nbsp;·&nbsp; <?= esc($jam) ?>
                &nbsp;·&nbsp; Tingkat <?= esc($jadwal['tingkat']) ?><?= $jadwal['jurusan_kode'] ? ' ' . esc($jadwal['jurusan_kode']) : '' ?>
                &nbsp;·&nbsp; Ruang <?= esc($jadwal['ruang'] ?: '—') ?>
            </p>
        </div>
        <a href="<?= esc($kembali) ?>" class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Jadwal
        </a>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
    <!-- Form tugaskan -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800">Tugaskan Pengawas</h3>
            </div>
            <form method="post" action="<?= $aksi ?>" class="p-5 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Guru *</label>
                    <select name="guru_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="">— Pilih guru —</option>
                        <?php foreach ($guruOpts as $id => $lbl): ?>
                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Peran</label>
                    <select name="peran" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <?php foreach ($peranList as $p): ?>
                            <option value="<?= esc($p, 'attr') ?>"><?= esc($labelPeran[$p] ?? $p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Ruang <span class="text-slate-400 font-normal">(ikut sesi bila kosong)</span>
                    </label>
                    <input type="text" name="ruang" maxlength="100" placeholder="<?= esc($jadwal['ruang'] ?: 'mis. R1', 'attr') ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                    <input type="text" name="keterangan" maxlength="255"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                </div>
                <button class="w-full rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">
                    Tugaskan
                </button>
            </form>
        </div>
    </div>

    <!-- Daftar pengawas -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Pengawas Bertugas</h3>
                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5">
                    <?= count($list) ?> orang
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left font-semibold px-6 py-3">Guru</th>
                            <th class="text-left font-semibold px-4 py-3">Peran</th>
                            <th class="text-left font-semibold px-4 py-3">Ruang</th>
                            <th class="text-left font-semibold px-4 py-3">Keterangan</th>
                            <th class="text-right font-semibold px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (! $list): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                    Belum ada pengawas. Bagian ini boleh dibiarkan kosong.
                                </td>
                            </tr>
                        <?php else: foreach ($list as $r): ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-slate-700"><?= esc($r['guru_nama'] ?? 'Guru sudah dihapus') ?></p>
                                    <?php if ($r['kode_guru']): ?>
                                        <p class="text-xs text-slate-400"><?= esc($r['kode_guru']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $warnaPeran[$r['peran']] ?? $warnaPeran['cadangan'] ?>">
                                        <?= esc($labelPeran[$r['peran']] ?? $r['peran']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= esc($r['ruang'] ?: '—') ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= esc($r['keterangan'] ?: '—') ?></td>
                                <td class="px-6 py-3 text-right">
                                    <form method="post" action="<?= $aksi ?>/hapus/<?= (int) $r['id'] ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <button title="Lepas penugasan" data-confirm="Lepas penugasan pengawas ini?"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
