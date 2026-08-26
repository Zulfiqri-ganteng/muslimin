<?php
/**
 * Jurnal realisasi pemakaian lab.
 *
 * @var array                         $rows        Baris jurnal
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci
 * @var int                           $labId       Filter lab
 * @var string                        $dari        Filter tanggal dari
 * @var string                        $sampai      Filter tanggal sampai
 * @var int                           $per         Baris per halaman
 * @var array<int,string>             $labOpts     Opsi lab
 * @var array<int,string>             $guruOpts    Opsi guru
 * @var array<int,string>             $kelasOpts   Opsi kelas
 * @var array<int,string>             $teknisiOpts Opsi teknisi
 * @var array                         $kondisiList Daftar kondisi setelah
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$jam  = static fn ($t) => $t ? substr((string) $t, 0, 5) : null;
$base = site_url('admin/jurnal-lab');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jurnal_lab',
    'helpTitle' => 'Jurnal Pemakaian Lab',
    'helpBody'  => '<p>Catatan <b>realisasi</b> pemakaian lab tiap sesi: kapan, oleh siapa, kegiatan apa, berapa yang hadir, dan kondisi lab setelah dipakai. Berbeda dari <b>Jadwal Lab</b> (rencana); jurnal ini adalah kenyataannya.</p>',
]) ?>

<div x-data="{ open:false }">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-2">
            <div class="relative flex-1 min-w-[180px]">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Cari</label>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Kegiatan / guru / lab..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Lab</label>
                <select name="lab_id" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua lab</option>
                    <?php foreach ($labOpts as $id => $nama): ?><option value="<?= (int) $id ?>" <?= $labId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Dari</label>
                <input type="date" name="dari" value="<?= esc($dari) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Sampai</label>
                <input type="date" name="sampai" value="<?= esc($sampai) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Filter</button>
            <?php if ($q !== '' || $labId || $dari !== '' || $sampai !== ''): ?><a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center">Reset</a><?php endif; ?>
        </form>
        <div class="flex items-center justify-end border-t border-slate-100 mt-3 pt-3">
            <button @click="open=true" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Jurnal
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Jurnal Pemakaian</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold w-28">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Lab</th>
                        <th class="px-4 py-3 font-semibold">Guru / Kelas</th>
                        <th class="px-4 py-3 font-semibold">Kegiatan</th>
                        <th class="px-4 py-3 font-semibold w-20 text-center">Hadir</th>
                        <th class="px-4 py-3 font-semibold w-28">Kondisi</th>
                        <th class="px-4 py-3 font-semibold w-16 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada jurnal pemakaian.</td></tr>
                    <?php else: foreach ($rows as $r):
                        $jm = $jam($r['jam_mulai']); $js = $jam($r['jam_selesai']); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 text-slate-700 font-medium">
                                <?= $tgl($r['tanggal']) ?>
                                <?php if ($jm): ?><div class="text-xs text-slate-400"><?= $jm ?><?= $js ? '–' . $js : '' ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= esc($r['lab_nama'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $r['guru_nama'] !== null ? esc($r['guru_nama']) : '<span class="text-slate-300">—</span>' ?>
                                <?php if (! empty($r['nama_kelas'])): ?><div class="text-xs text-slate-400"><?= esc($r['nama_kelas']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['kegiatan'] !== null ? esc($r['kegiatan']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= $r['jumlah_hadir'] !== null ? (int) $r['jumlah_hadir'] : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <?php if ($r['kondisi_setelah'] === 'ada_kendala'): ?>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200" title="<?= esc($r['kendala'] ?? '', 'attr') ?>">Ada kendala</span>
                                <?php else: ?>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Baik</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus jurnal ini?" title="Hapus"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?><div class="px-6 py-4 border-t border-slate-100"><?= $pager->only(['q', 'lab_id', 'dari', 'sampai', 'per'])->links('default', 'admin') ?></div><?php endif; ?>
    </div>

    <!-- Modal Tambah Jurnal -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Tambah Jurnal Pemakaian</h3>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Lab *</label>
                        <select name="lab_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih lab —</option>
                            <?php foreach ($labOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
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
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kegiatan / Materi</label>
                        <input type="text" name="kegiatan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jumlah Hadir</label>
                        <input type="number" name="jumlah_hadir" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kondisi Setelah</label>
                        <select name="kondisi_setelah" x-data @change="$refs.kendalaWrap.style.display = ($event.target.value==='ada_kendala' ? 'block':'none')"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="baik">Baik</option>
                            <option value="ada_kendala">Ada kendala</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2" x-ref="kendalaWrap" style="display:none">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kendala</label>
                        <input type="text" name="kendala" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Teknisi</label>
                        <select name="teknisi_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak dipilih —</option>
                            <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
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
</div>

<?= $this->endSection() ?>
