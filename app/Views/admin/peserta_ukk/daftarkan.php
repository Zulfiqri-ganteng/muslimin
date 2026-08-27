<?php
/**
 * Daftarkan peserta UKK: pilih paket soal + kelas → checklist siswa.
 *
 * @var int                $paketId
 * @var int                $kelasId
 * @var array|null         $paket
 * @var array              $siswaTersedia
 * @var int                $sudahTerdaftarCount
 * @var array<int,string>  $paketOpts
 * @var array<int,string>  $kelasOpts
 * @var array<int,string>  $jadwalOpts
 */
$base = site_url('admin/peserta-ukk/daftarkan');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'peserta_ukk_daftarkan',
    'helpTitle' => 'Daftarkan Peserta UKK',
    'helpBody'  => '<p>Pilih <b>Paket Soal</b> dan <b>Kelas</b> terlebih dahulu. Siswa yang sudah terdaftar pada paket
        soal tersebut otomatis disembunyikan dari daftar. Centang siswa yang ikut, lalu klik Daftarkan.</p>',
]) ?>

<a href="<?= site_url('admin/peserta-ukk') ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mb-4">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali ke Daftar Peserta
</a>

<!-- Langkah 1: pilih paket soal + kelas -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-5">
    <h2 class="font-bold text-slate-800 mb-3">1. Pilih Paket Soal &amp; Kelas</h2>
    <form method="get" action="<?= $base ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1">Paket Soal *</label>
            <select name="paket_soal_id" data-autosubmit required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                <option value="">— Pilih paket soal —</option>
                <?php foreach ($paketOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $paketId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1">Kelas *</label>
            <select name="kelas_id" data-autosubmit required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                <option value="">— Pilih kelas —</option>
                <?php foreach ($kelasOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($paketId > 0 && $kelasId > 0): ?>
    <?php if (! $paket): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 text-slate-500">Paket soal tidak ditemukan.</div>
    <?php else: ?>
        <!-- Langkah 2: checklist siswa -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5" x-data="{ checked: [] }">
            <div class="flex items-center justify-between mb-1">
                <h2 class="font-bold text-slate-800">2. Pilih Siswa</h2>
                <?php if ($sudahTerdaftarCount > 0): ?>
                    <span class="text-xs text-slate-400"><?= (int) $sudahTerdaftarCount ?> siswa di paket ini sudah terdaftar (disembunyikan)</span>
                <?php endif; ?>
            </div>

            <?php if (empty($siswaTersedia)): ?>
                <p class="text-slate-400 py-6 text-center">Semua siswa aktif di kelas ini sudah terdaftar pada paket soal ini, atau kelas belum punya siswa aktif.</p>
            <?php else: ?>
                <form method="post" action="<?= site_url('admin/peserta-ukk/daftarkan') ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="paket_soal_id" value="<?= (int) $paketId ?>">

                    <?php if (! empty($jadwalOpts)): ?>
                        <div class="mb-4 max-w-sm">
                            <label class="block text-sm font-medium text-slate-600 mb-1">Jadwal UKK (opsional)</label>
                            <select name="jadwal_ukk_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                                <option value="">— Belum ditentukan —</option>
                                <?php foreach ($jadwalOpts as $id => $lbl): ?>
                                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <label class="flex items-center gap-2 pb-2 mb-2 border-b border-slate-100 text-sm font-medium text-slate-600">
                        <input type="checkbox" @click="checked = $el.checked ? [<?= implode(',', array_column($siswaTersedia, 'id')) ?>] : []"
                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Pilih semua (<?= count($siswaTersedia) ?> siswa)
                    </label>

                    <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                        <?php foreach ($siswaTersedia as $s): ?>
                            <label class="flex items-center gap-3 py-2.5 hover:bg-slate-50 px-1 cursor-pointer">
                                <input type="checkbox" name="siswa_ids[]" value="<?= (int) $s['id'] ?>"
                                       x-model.number="checked"
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="flex-1">
                                    <span class="font-medium text-slate-800"><?= esc($s['nama']) ?></span>
                                    <span class="text-xs text-slate-400 ml-1"><?= esc($s['nis']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-slate-100">
                        <span class="flex-1 self-center text-sm text-slate-500" x-text="checked.length + ' siswa dipilih'"></span>
                        <button :disabled="checked.length === 0" :class="checked.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Daftarkan</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
