<?php
/**
 * Daftar peserta UKK (pendaftaran).
 *
 * @var array                          $rows       Baris peserta halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager      Paginasi
 * @var string                        $q          Kata kunci pencarian
 * @var int                           $paketId    Filter paket soal aktif
 * @var string                        $status     Filter status aktif
 * @var int                           $per        Baris per halaman
 * @var int                           $total      Total peserta (sesuai filter paket)
 * @var int                           $lulus      Jumlah lulus
 * @var int                           $tidakLulus Jumlah tidak lulus
 * @var array<int,string>             $paketOpts  Opsi paket soal
 * @var array                         $statusList Daftar status sah
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/peserta-ukk');
$warnaStatus = [
    'terdaftar'   => 'bg-slate-100 text-slate-600 border-slate-200',
    'hadir'       => 'bg-sky-50 text-sky-700 border-sky-200',
    'tidak_hadir' => 'bg-amber-50 text-amber-700 border-amber-200',
    'lulus'       => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'tidak_lulus' => 'bg-red-50 text-red-700 border-red-200',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'peserta_ukk',
    'helpTitle' => 'Peserta UKK',
    'helpBody'  => '<p>Daftar siswa yang mengikuti Uji Kompetensi Keahlian per paket soal. Klik <b>Daftarkan Peserta</b>
        untuk mendaftarkan satu kelas sekaligus — pilih paket soal + kelas, sistem otomatis menyembunyikan siswa yang
        sudah terdaftar, lalu centang siswa yang ikut.</p>
        <p class="mt-1">• Nomor peserta dibuat otomatis (format <code>UKK-{KODE PAKET}-001</code>).<br>
        • Status <b>Lulus/Tidak Lulus</b> normalnya terisi otomatis setelah penilaian selesai, tapi bisa diubah manual
        di sini bila perlu.</p>',
]) ?>

<div x-data="{ statusOpen:false, statusAction:'', statusLabel:'', statusNow:'',
      openStatus(id,label,now){ this.statusAction='<?= $base ?>/status/'+id; this.statusLabel=label; this.statusNow=now; this.statusOpen=true; } }">

    <!-- Ringkasan + toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nama, NIS, atau no. peserta..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="paket_soal_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua paket soal</option>
                <?php foreach ($paketOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $paketId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua status</option>
                <?php foreach ($statusList as $s): ?>
                    <option value="<?= esc($s, 'attr') ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit title="Jumlah per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($q !== '' || $paketId > 0 || $status !== ''): ?>
                <a href="<?= $base ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
        </form>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 mt-3 pt-3">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5">Total: <?= (int) $total ?></span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5">Lulus: <?= (int) $lulus ?></span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold px-3 py-1.5">Tidak Lulus: <?= (int) $tidakLulus ?></span>
            <div class="flex-1"></div>
            <a href="<?= site_url('admin/peserta-ukk/daftarkan') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Daftarkan Peserta
            </a>
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Daftar Peserta</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold">No. Peserta</th>
                        <th class="px-4 py-3 font-semibold">Siswa</th>
                        <th class="px-4 py-3 font-semibold">Paket Soal</th>
                        <th class="px-4 py-3 font-semibold w-28">Jadwal</th>
                        <th class="px-4 py-3 font-semibold w-24 text-right">Nilai</th>
                        <th class="px-4 py-3 font-semibold w-32">Status</th>
                        <th class="px-4 py-3 font-semibold w-32 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $paketId > 0 || $status !== '' ? 'Tidak ada peserta yang cocok.' : 'Belum ada peserta terdaftar. Klik "Daftarkan Peserta" untuk memulai.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-mono font-semibold text-brand-700"><?= esc($r['no_peserta'] ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['siswa_nama'] ?? '—') ?></div>
                                <div class="text-xs text-slate-400"><?= esc($r['nis'] ?? '—') ?> · <?= esc($r['nama_kelas'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-700"><?= esc($r['paket_kode'] ?? '—') ?></div>
                                <div class="text-xs text-slate-400"><?= esc($r['paket_nama'] ?? '—') ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $tgl($r['jadwal_tanggal'] ?? null) ?></td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-700"><?= $r['nilai_akhir'] !== null ? number_format((float) $r['nilai_akhir'], 1) : '—' ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['status']))) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button type="button" @click="openStatus(<?= (int) $r['id'] ?>, '<?= esc($r['siswa_nama'] ?? '-', 'js') ?>', '<?= esc($r['status'], 'js') ?>')"
                                            title="Ubah status" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= $base ?>/delete/<?= (int) $r['id'] ?>" data-confirm="Hapus pendaftaran peserta ini?" title="Hapus"
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
                <?= $pager->only(['q', 'paket_soal_id', 'status', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Ubah Status -->
    <div x-show="statusOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="statusOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Ubah Status Peserta</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="statusLabel"></p>
            <form method="post" :action="statusAction">
                <?= csrf_field() ?>
                <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                <select name="status" x-model="statusNow" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="statusOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
