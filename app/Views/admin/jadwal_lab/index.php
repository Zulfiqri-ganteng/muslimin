<?php
/**
 * Jadwal pemakaian lab + jadwal praktik guru.
 *
 * @var array             $rows      Baris jadwal (mode lab atau guru)
 * @var int               $labId     Lab terpilih
 * @var int               $guruId    Guru terpilih (mode praktik guru)
 * @var array<int,string> $labOpts   Opsi lab
 * @var array<int,string> $guruOpts  Opsi guru
 * @var array<int,string> $kelasOpts Opsi kelas
 * @var array<int,string> $mapelOpts Opsi mapel
 * @var array<int,string> $hariOpts  Opsi hari
 * @var array<int,string> $jamOpts   Opsi jam
 */
$base    = site_url('admin/jadwal-lab');
$modeLab = $labId > 0;
$modeGuru = ! $modeLab && $guruId > 0;
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_lab',
    'helpTitle' => 'Jadwal Lab',
    'helpBody'  => '<p>Atur jadwal pemakaian tiap laboratorium. Pilih <b>lab</b> untuk mengelola slotnya (satu lab tak bisa dipakai dua kegiatan pada jam yang sama; guru pun tak bisa dijadwalkan di dua lab bersamaan).</p>
        <p class="mt-1">Pilih <b>guru</b> untuk melihat <b>jadwal praktik guru</b> tersebut lintas lab (tampilan baca saja).</p>',
]) ?>

<div x-data="{ open:false }">

    <!-- Pemilih -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3">
            <form method="get" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Kelola Jadwal Lab</label>
                    <select name="lab_id" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none min-w-[180px]">
                        <option value="">— Pilih lab —</option>
                        <?php foreach ($labOpts as $id => $nama): ?><option value="<?= (int) $id ?>" <?= $labId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5">Tampilkan</button>
            </form>
            <div class="hidden lg:block w-px h-10 bg-slate-200"></div>
            <form method="get" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Jadwal Praktik Guru</label>
                    <select name="guru_id" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none min-w-[180px]">
                        <option value="">— Pilih guru —</option>
                        <?php foreach ($guruOpts as $id => $nama): ?><option value="<?= (int) $id ?>" <?= $guruId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button class="rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold px-4 py-2.5">Lihat</button>
            </form>
            <div class="flex-1"></div>
            <?php if ($modeLab): ?>
                <button @click="open=true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Jadwal
                </button>
            <?php endif; ?>
        </div>
        <?php if ($modeLab): ?>
            <p class="text-xs text-slate-400 mt-2">Menampilkan jadwal untuk lab: <b><?= esc($labOpts[$labId] ?? '-') ?></b></p>
        <?php elseif ($modeGuru): ?>
            <p class="text-xs text-slate-400 mt-2">Jadwal praktik guru: <b><?= esc($guruOpts[$guruId] ?? '-') ?></b> (baca saja)</p>
        <?php endif; ?>
    </div>

    <?php if (! $modeLab && ! $modeGuru): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
            Pilih lab untuk mengelola jadwal, atau pilih guru untuk melihat jadwal praktiknya.
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-6 py-3 font-semibold w-28">Hari</th>
                            <th class="px-4 py-3 font-semibold w-48">Jam</th>
                            <?php if ($modeGuru): ?><th class="px-4 py-3 font-semibold">Lab</th><?php else: ?><th class="px-4 py-3 font-semibold">Guru</th><?php endif; ?>
                            <th class="px-4 py-3 font-semibold">Kelas / Kegiatan</th>
                            <?php if ($modeLab): ?><th class="px-4 py-3 font-semibold w-16 text-right">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada jadwal.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-semibold text-slate-700"><?= esc(ucfirst(strtolower((string) $r['hari_nama']))) ?></td>
                                <td class="px-4 py-3 text-slate-600">Jam <?= (int) $r['jam_ke'] ?> <span class="text-slate-400 text-xs">(<?= substr((string) $r['waktu_mulai'], 0, 5) ?>–<?= substr((string) $r['waktu_selesai'], 0, 5) ?>)</span></td>
                                <?php if ($modeGuru): ?>
                                    <td class="px-4 py-3 text-slate-700"><?= esc($r['lab_nama'] ?? '—') ?></td>
                                <?php else: ?>
                                    <td class="px-4 py-3 text-slate-700"><?= $r['guru_nama'] !== null ? esc($r['guru_nama']) : '<span class="text-slate-300">—</span>' ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-slate-600">
                                    <?php
                                    $bits = array_filter([
                                        $r['nama_kelas'] ?? null,
                                        $r['nama_mapel'] ?? null,
                                        $r['kegiatan'] ?? null,
                                    ]);
                                    echo $bits ? esc(implode(' · ', $bits)) : '<span class="text-slate-300">—</span>';
                                    ?>
                                </td>
                                <?php if ($modeLab): ?>
                                    <td class="px-4 py-3 text-right">
                                        <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus slot jadwal ini?" title="Hapus"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Tambah Jadwal (mode lab) -->
    <?php if ($modeLab): ?>
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Tambah Jadwal — <?= esc($labOpts[$labId] ?? '') ?></h3>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="lab_id" value="<?= $labId ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Hari *</label>
                        <select name="hari_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih —</option>
                            <?php foreach ($hariOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc(ucfirst(strtolower($nama))) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam *</label>
                        <select name="jam_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih —</option>
                            <?php foreach ($jamOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Guru</label>
                        <select name="guru_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ada —</option>
                            <?php foreach ($guruOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kelas</label>
                        <select name="kelas_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ada —</option>
                            <?php foreach ($kelasOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mapel</label>
                        <select name="mapel_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ada —</option>
                            <?php foreach ($mapelOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kegiatan</label>
                        <input type="text" name="kegiatan" maxlength="150" placeholder="mis. Praktik Jaringan" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
