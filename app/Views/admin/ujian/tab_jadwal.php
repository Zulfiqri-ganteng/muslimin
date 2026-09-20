<?php
/**
 * Tab Jadwal Ujian — daftar mapel yang diujikan pada satu periode.
 *
 * Satu baris = satu mapel diujikan untuk satu tingkat (opsional dipersempit
 * ke satu jurusan) pada tanggal & jam tertentu. Kolom shift penting karena
 * kelas XII terbagi pagi & siang.
 *
 * @var array<int,array>     $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array<string,mixed>  $filter      q, tingkat, jurusan_id
 * @var int                  $per
 * @var int                  $totalJadwal
 * @var array<int,int>       $jmlPengawas jadwal_id => jumlah pengawas
 * @var array<int,int>       $jmlTakHadir jadwal_id => jumlah siswa tidak hadir
 * @var array<int,string>    $mapelOpts
 * @var array<int,string>    $jurusanOpts
 * @var array<int,string>    $tingkatList
 * @var array<int,string>    $shiftList
 * @var array<string,mixed>  $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

$jam = static fn ($m, $s) => $m
    ? substr((string) $m, 0, 5) . ($s ? '–' . substr((string) $s, 0, 5) : '')
    : 'Sehari penuh';

$labelShift = ['pagi' => 'Pagi', 'siang' => 'Siang', 'semua' => 'Pagi & Siang'];
$warnaShift = [
    'pagi'  => 'bg-amber-50 text-amber-700 border-amber-200',
    'siang' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'semua' => 'bg-slate-100 text-slate-600 border-slate-200',
];

// Nilai awal form tambah: tanggal ikut tanggal mulai periode bila sudah diisi.
$defaults = [
    'id'          => '',
    'mapel_id'    => '',
    'tingkat'     => '',
    'jurusan_id'  => '',
    'shift'       => 'semua',
    'tanggal'     => $periode['tanggal_mulai'] ?: date('Y-m-d'),
    'jam_mulai'   => '',
    'jam_selesai' => '',
    'ruang'       => '',
    'keterangan'  => '',
];
$adaFilter = $filter['q'] !== '' || $filter['tingkat'] !== '' || $filter['jurusan_id'] > 0;
?>

<div x-data="{ open:false, importOpen:false, mode:'add',
      form: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      defaults: <?= htmlspecialchars(json_encode($defaults), ENT_QUOTES) ?>,
      openAdd(){ this.mode='add'; this.form=Object.assign({}, this.defaults); this.open=true; },
      openEdit(r){ this.mode='edit'; this.form=Object.assign({}, this.defaults, r); this.open=true; } }">

    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($filter['q']) ?>" placeholder="Cari mata pelajaran atau ruang..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="tingkat" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua tingkat</option>
                <?php foreach ($tingkatList as $t): ?>
                    <option value="<?= esc($t, 'attr') ?>" <?= $filter['tingkat'] === $t ? 'selected' : '' ?>>Tingkat <?= esc($t) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="jurusan_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua jurusan</option>
                <?php foreach ($jurusanOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $filter['jurusan_id'] === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit title="Jumlah per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($adaFilter): ?>
                <a href="<?= $base ?>/jadwal<?= $qtp ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
        </form>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 mt-3 pt-3">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5">
                Jadwal: <?= (int) $totalJadwal ?>
            </span>
            <div class="flex-1"></div>
            <button type="button" @click="importOpen=true"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3.5 py-2.5 hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import
            </button>
            <a href="<?= $base ?>/jadwal/export<?= $qtp ?>"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3.5 py-2.5 hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
            <button type="button" @click="openAdd()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Jadwal
            </button>
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="text-left font-semibold px-6 py-3">Hari / Tanggal</th>
                        <th class="text-left font-semibold px-4 py-3">Jam</th>
                        <th class="text-left font-semibold px-4 py-3">Mata Pelajaran</th>
                        <th class="text-left font-semibold px-4 py-3">Sasaran</th>
                        <th class="text-left font-semibold px-4 py-3">Ruang</th>
                        <th class="text-center font-semibold px-4 py-3">Pengawas</th>
                        <th class="text-center font-semibold px-4 py-3">Tidak Hadir</th>
                        <th class="text-right font-semibold px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (! $rows): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                <?= $adaFilter
                                    ? 'Tidak ada jadwal yang cocok dengan filter.'
                                    : 'Belum ada jadwal ujian. Klik <b>Tambah Jadwal</b> untuk mulai menyusun.' ?>
                            </td>
                        </tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php $ts = strtotime((string) $r['tanggal']); ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-6 py-3">
                                <p class="font-semibold text-slate-700"><?= esc($HARI[(int) date('N', $ts)] ?? '') ?></p>
                                <p class="text-xs text-slate-500"><?= date('d/m/Y', $ts) ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= esc($jam($r['jam_mulai'], $r['jam_selesai'])) ?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-700"><?= esc($r['nama_mapel'] ?? 'Mapel sudah dihapus') ?></p>
                                <?php if ($r['kode_mapel']): ?>
                                    <p class="text-xs text-slate-400"><?= esc($r['kode_mapel']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-700">Tingkat <?= esc($r['tingkat']) ?></span>
                                <?php if ($r['jurusan_kode']): ?>
                                    <span class="text-slate-500"> · <?= esc($r['jurusan_kode']) ?></span>
                                <?php endif; ?>
                                <span class="ml-1 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold <?= $warnaShift[$r['shift']] ?? $warnaShift['semua'] ?>">
                                    <?= esc($labelShift[$r['shift']] ?? $r['shift']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= esc($r['ruang'] ?: '—') ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php $jml = (int) ($jmlPengawas[$r['id']] ?? 0); ?>
                                <a href="<?= $base ?>/pengawas/<?= (int) $r['id'] ?>" title="Kelola pengawas"
                                   class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-semibold transition <?= $jml > 0
                                       ? 'text-brand-700 hover:bg-brand-50'
                                       : 'text-slate-400 hover:bg-slate-100' ?>">
                                    <?= $jml ?>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php $tak = (int) ($jmlTakHadir[$r['id']] ?? 0); ?>
                                <span class="<?= $tak > 0 ? 'font-bold text-amber-700' : 'text-slate-400' ?>"><?= $tak ?></span>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="<?= $base ?>/daftar-hadir/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" title="Cetak daftar hadir"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    </a>
                                    <a href="<?= $base ?>/berita-acara/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" title="Cetak berita acara"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                    <button type="button" title="Ubah"
                                            @click="openEdit(<?= htmlspecialchars(json_encode([
                                                'id'          => (string) $r['id'],
                                                'mapel_id'    => (string) ($r['mapel_id'] ?? ''),
                                                'tingkat'     => $r['tingkat'],
                                                'jurusan_id'  => (string) ($r['jurusan_id'] ?? ''),
                                                'shift'       => $r['shift'],
                                                'tanggal'     => $r['tanggal'],
                                                'jam_mulai'   => $r['jam_mulai'] ? substr((string) $r['jam_mulai'], 0, 5) : '',
                                                'jam_selesai' => $r['jam_selesai'] ? substr((string) $r['jam_selesai'], 0, 5) : '',
                                                'ruang'       => $r['ruang'] ?? '',
                                                'keterangan'  => $r['keterangan'] ?? '',
                                            ]), ENT_QUOTES) ?>)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form method="post" action="<?= $base ?>/jadwal/<?= (int) $r['id'] ?>/hapus" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                        <button title="Hapus"
                                                data-confirm="Hapus jadwal ini?<?= $tak > 0 ? ' ' . $tak . ' catatan ketidakhadiran TETAP tersimpan, hanya dilepas dari jadwal ini.' : '' ?> Penugasan pengawasnya ikut terhapus."
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'tingkat', 'jurusan_id', 'per', 'tp'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Ubah -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jadwal Ujian' : 'Ubah Jadwal Ujian'"></h3>
            <form method="post" action="<?= $base ?>/jadwal">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <input type="hidden" name="id" :value="form.id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mata Pelajaran *</label>
                        <select name="mapel_id" x-model="form.mapel_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih mata pelajaran —</option>
                            <?php foreach ($mapelOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tingkat *</label>
                        <select name="tingkat" x-model="form.tingkat" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih tingkat —</option>
                            <?php foreach ($tingkatList as $t): ?>
                                <option value="<?= esc($t, 'attr') ?>"><?= esc($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Shift</label>
                        <select name="shift" x-model="form.shift" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($shiftList as $s): ?>
                                <option value="<?= esc($s, 'attr') ?>"><?= esc($labelShift[$s] ?? $s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">
                            Jurusan <span class="text-slate-400 font-normal">(kosongkan bila semua jurusan)</span>
                        </label>
                        <select name="jurusan_id" x-model="form.jurusan_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Semua jurusan —</option>
                            <?php foreach ($jurusanOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal *</label>
                        <input type="date" name="tanggal" x-model="form.tanggal" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Mulai</label>
                        <input type="time" name="jam_mulai" x-model="form.jam_mulai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Selesai</label>
                        <input type="time" name="jam_selesai" x-model="form.jam_selesai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ruang</label>
                        <input type="text" name="ruang" x-model="form.ruang" maxlength="100" placeholder="mis. R1–R8 atau Aula"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>

                <p class="text-xs text-slate-500 mt-4">
                    Mapel yang sama di jam sama boleh dibuat lebih dari satu baris (ujian paralel beda ruang).
                    Yang ditolak hanya dua <b>mapel berbeda</b> pada tingkat, shift, dan jam yang beririsan.
                </p>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Jadwal Ujian',
        'importAction' => $base . '/jadwal/import-preview' . $qtp,
        'templateUrl'  => $base . '/jadwal/template' . $qtp,
        'importNote'   => 'Kolom: <b>Kode Mapel</b>, Tingkat, Kode Jurusan, Shift, Tanggal, Jam Mulai,
            Jam Selesai, Ruang, Keterangan. Mapel &amp; jurusan dicocokkan lewat <b>kode</b>-nya.
            Baris dengan mapel, tingkat, jurusan, shift, dan ruang yang sama akan <b>diperbarui</b>,
            bukan digandakan — jadi berkas boleh diunggah ulang setelah diperbaiki.',
    ]) ?>
</div>
