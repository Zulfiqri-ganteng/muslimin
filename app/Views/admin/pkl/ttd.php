<?php
/**
 * Tanda tangan Waka Hubin (diunggah Hubin sendiri atau Admin).
 *
 * @var array  $p    baris pkl_pengaturan
 * @var bool   $ada  gambar tanda tangan sudah ada
 * @var int    $ver  penanda versi gambar (cache)
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_ttd_v1',
    'helpTitle' => 'Tanda Tangan Waka Hubin',
    'helpBody'  => '<p>Gambar tanda tangan Waka Hubin yang <b>dipasang otomatis di surat permohonan PKL</b> — di sisi kiri tanda tangan Kepala Sekolah, tepat di atas nama Waka Hubin.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li>Gambar hanya terpasang pada surat yang <b>di-ACC oleh akun Waka Hubin</b>. Bila Admin yang meng-ACC (cadangan), ruangnya dikosongkan untuk tanda tangan basah dan kaki surat menjelaskan siapa yang menyetujui.</li>'
        . '<li>Pakai foto/pindaian tanda tangan di <b>kertas putih</b>, format PNG atau JPG, maksimal 1 MB. Latar putih otomatis dibuat transparan.</li>'
        . '<li>Hanya Waka Hubin dan Admin yang bisa mengubahnya. Operator tidak.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'ttd', 'hitungTab' => $hitungTab, 'bolehTtd' => true]) ?>

<div class="mx-auto max-w-2xl space-y-5">
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Tanda tangan saat ini</h3>
        <div class="p-5">
            <?php if ($ada): ?>
                <div class="flex min-h-[9rem] items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-4">
                    <img src="<?= esc(site_url('admin/pkl/ttd/gambar') . '?v=' . (int) $ver, 'attr') ?>" alt="Tanda tangan Waka Hubin" class="max-h-32 max-w-full object-contain">
                </div>
                <p class="mt-3 text-sm text-slate-600">Atas nama: <b><?= esc($p['waka_hubin_nama'] ?: '(nama Waka Hubin belum diisi di Pengaturan PKL)') ?></b><br><?= esc($p['waka_hubin_jabatan'] ?? '') ?></p>
                <form method="post" action="<?= site_url('admin/pkl/ttd/hapus') ?>" class="mt-4" onsubmit="return confirm('Hapus tanda tangan ini? Surat berikutnya memberi ruang kosong untuk tanda tangan basah.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Hapus tanda tangan</button>
                </form>
            <?php else: ?>
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                    <p class="font-semibold text-slate-700">Belum ada gambar tanda tangan</p>
                    <p class="mt-1 text-sm text-slate-500">Surat tetap bisa dicetak: ruang tanda tangan Waka Hubin dibiarkan kosong untuk tanda tangan basah.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500"><?= $ada ? 'Ganti tanda tangan' : 'Unggah tanda tangan' ?></h3>
        <form method="post" action="<?= site_url('admin/pkl/ttd') ?>" enctype="multipart/form-data" class="space-y-3 p-5">
            <?= csrf_field() ?>
            <input type="file" name="ttd" accept="image/png,image/jpeg" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3.5 file:py-2 file:font-semibold file:text-brand-700">
            <p class="text-xs leading-relaxed text-slate-500">PNG atau JPG · maksimal 1 MB · lebar dan tinggi antara 100 dan 4000 piksel. Gambar mendatar (sekitar 3:1) paling pas; ukurannya dikecilkan otomatis agar muat di surat.</p>
            <button type="submit" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Simpan tanda tangan</button>
        </form>
    </section>
</div>

<?= $this->endSection() ?>
