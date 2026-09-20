<?php
/**
 * Tab Periode — ringkasan angka + form pengaturan periode ujian.
 *
 * @var array<string,mixed>  $periode
 * @var string               $label
 * @var string               $base        URL dasar jenis ujian ini
 * @var string               $tp          tahun pelajaran yang sedang dilihat
 * @var array<string,mixed>  $ringkas
 * @var array<string,string> $labelStatus
 */
$old = static fn (string $f, $fallback) => old($f) ?? ($fallback ?? '');
?>

<!-- ===== Kartu ringkasan ===== -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
    <?php
    $kartu = [
        ['Jadwal Ujian', $ringkas['jadwal'], 'Mapel terjadwal', 'text-brand-700'],
        ['Siswa Aktif', $ringkas['siswaAktif'], 'Calon peserta', 'text-slate-700'],
        ['Tidak Hadir', $ringkas['tidakHadir'], 'Tercatat berhalangan', 'text-amber-700'],
        ['Susulan Selesai', $ringkas['statusList']['selesai'], 'Sudah dilaksanakan', 'text-emerald-700'],
    ];
    foreach ($kartu as [$judul, $angka, $ket, $warna]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500"><?= esc($judul) ?></p>
            <p class="text-2xl font-extrabold <?= $warna ?> mt-1"><?= number_format((int) $angka, 0, ',', '.') ?></p>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= esc($ket) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- ===== Form pengaturan periode ===== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-bold text-slate-800">Pengaturan Periode</h2>
        <p class="text-xs text-slate-500 mt-0.5">Tanggal boleh dikosongkan dulu dan diisi belakangan.</p>
    </div>

    <form method="post" action="<?= $base ?>/periode" class="p-6 space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $periode['id'] ?>">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Mulai Ujian</label>
                <input type="date" name="tanggal_mulai" value="<?= esc($old('tanggal_mulai', $periode['tanggal_mulai']), 'attr') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Selesai Ujian</label>
                <input type="date" name="tanggal_selesai" value="<?= esc($old('tanggal_selesai', $periode['tanggal_selesai']), 'attr') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mulai Ujian Susulan</label>
                <input type="date" name="susulan_mulai" value="<?= esc($old('susulan_mulai', $periode['susulan_mulai']), 'attr') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Selesai Ujian Susulan</label>
                <input type="date" name="susulan_selesai" value="<?= esc($old('susulan_selesai', $periode['susulan_selesai']), 'attr') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Status</label>
                <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <?php foreach ($labelStatus as $k => $lbl): ?>
                        <option value="<?= esc($k, 'attr') ?>" <?= $old('status', $periode['status']) === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Nama Tampil <span class="font-normal text-slate-400">(opsional)</span>
                </label>
                <input type="text" name="nama" maxlength="100" value="<?= esc($old('nama', $periode['nama']), 'attr') ?>"
                       placeholder="<?= esc($label, 'attr') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Keterangan</label>
            <input type="text" name="keterangan" maxlength="255" value="<?= esc($old('keterangan', $periode['keterangan']), 'attr') ?>"
                   placeholder="Catatan internal, mis. nomor SK panitia"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">
                Simpan Pengaturan
            </button>
            <p class="text-xs text-slate-500">
                Tahun pelajaran <b><?= esc($tp) ?></b> &amp; semester <b><?= esc($periode['semester']) ?></b>
                mengikuti jenis ujian dan Pengaturan Sekolah — tidak diubah dari sini.
            </p>
        </div>
    </form>
</div>
