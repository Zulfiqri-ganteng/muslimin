<?php
/**
 * Pratinjau "Atur urutan" honor — KHUSUS ADMIN. Belum ada yang tersimpan: yang berubah HANYA nomor urut (dan label jabatan
 * bila dicentang); angka isian honor tidak pernah disentuh.
 *
 * @var string $slug  @var array $periode  @var string $label
 * @var string $mode     'aturan' (menurut jabatan di Master Guru) | 'excel' (mengikuti berkas Excel sekolah)
 * @var array  $rencana  hasil HonorDokumen::rencanaUrutan*() (baris, pindah, label, + daftar tak cocok untuk Excel)
 * @var string $berkas   nama berkas Excel (mode excel)  @var string $token  @var string $status  @var string $kembali
 */
$baris     = $rencana['baris'];
$terkunci  = $status === 'dikunci';
$jmlBeda   = 0;
foreach ($baris as $b) {
    $jmlBeda += ($b['pindah'] || $b['label_beda']) ? 1 : 0;
}
$tidakAdaDok = $rencana['tak_ada_di_dokumen'] ?? [];
$tidakAdaXl  = $rencana['tak_ada_di_excel'] ?? [];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4" x-data="{ hanya: <?= $jmlBeda > 0 ? 'true' : 'false' ?> }">

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= esc($label) ?></p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Atur urutan penerima honor</h2>
                <?php if ($mode === 'excel'): ?>
                    <p class="mt-1 text-sm text-slate-500">Mengikuti urutan di berkas <b class="text-slate-700"><?= esc($berkas) ?></b>. Nama dicocokkan ke daftar penerima di bawah. <b>Belum ada yang tersimpan.</b></p>
                <?php else: ?>
                    <p class="mt-1 text-sm text-slate-500">Menurut jabatan di Master Guru: jabatan (urutan di Pengaturan Honor), lalu level, lalu abjad &mdash; sama seperti honor yang baru dibuat. <b>Belum ada yang tersimpan.</b></p>
                <?php endif; ?>
            </div>
            <a href="<?= esc($kembali) ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal, kembali</a>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Penerima</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= count($baris) ?></p></div>
            <div class="rounded-xl border px-3 py-2 <?= $rencana['pindah'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Pindah nomor</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= (int) $rencana['pindah'] ?></p></div>
            <div class="rounded-xl border px-3 py-2 <?= $rencana['label'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Label jabatan beda</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= (int) $rencana['label'] ?></p></div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Angka isian</p><p class="text-sm font-extrabold text-emerald-800">Tidak diubah</p></div>
        </div>

        <?php if ($mode === 'aturan' && $rencana['pindah'] > 0): ?>
            <p class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Urutan yang kamu atur sendiri (mis. mengikuti Excel sekolah) akan <b>diganti</b> dengan urutan standar. Mau mempertahankan urutan khusus sekolah? Pakai <b>Atur urutan → Ikuti Excel sekolah</b>.</p>
        <?php endif; ?>
        <?php if ($mode === 'excel' && ($tidakAdaDok !== [] || $tidakAdaXl !== [])): ?>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <?php if ($tidakAdaDok !== []): ?>
                    <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                        <p class="font-bold">Ada di Excel, tidak ada di daftar penerima (<?= count($tidakAdaDok) ?>) &mdash; dilewati:</p>
                        <p class="mt-1"><?= esc(implode(', ', array_slice($tidakAdaDok, 0, 15))) ?><?= count($tidakAdaDok) > 15 ? ', …' : '' ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($tidakAdaXl !== []): ?>
                    <div class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-slate-700">
                        <p class="font-bold">Ada di daftar penerima, tidak ada di Excel (<?= count($tidakAdaXl) ?>) &mdash; ditaruh di bawah:</p>
                        <p class="mt-1"><?= esc(implode(', ', array_slice($tidakAdaXl, 0, 15))) ?><?= count($tidakAdaXl) > 15 ? ', …' : '' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/urutan/terapkan') ?>" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <?= csrf_field() ?>
            <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
            <input type="hidden" name="mode" value="<?= esc($mode, 'attr') ?>">
            <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                <input type="checkbox" name="label" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span>Ganti juga <b>label jabatan</b> (<?= $mode === 'excel' ? 'sesuai tulisan di Excel' : 'sesuai jabatan di Master Guru' ?>). Label yang pernah kamu ubah sendiri ikut tertimpa.</span>
            </label>
            <?php if ($terkunci): ?>
                <p class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500">Honor DIKUNCI &mdash; tidak bisa diubah.</p>
            <?php elseif ($jmlBeda === 0): ?>
                <p class="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">Sudah sesuai &mdash; tidak ada yang perlu diubah.</p>
            <?php else: ?>
                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Terapkan urutan (<?= $jmlBeda ?> baris berubah)</button>
            <?php endif; ?>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-2.5">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Pratinjau perubahan</h3>
            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" x-model="hanya" class="h-4 w-4 rounded border-slate-300"> Hanya yang berubah</label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2">No sekarang</th><th class="px-3 py-2">No baru</th><th class="px-3 py-2">Nama</th><th class="px-3 py-2">Jabatan sekarang</th><th class="px-3 py-2">Jabatan baru</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($baris as $b): $beda = $b['pindah'] || $b['label_beda']; ?>
                        <tr <?= $beda ? '' : 'x-show="!hanya"' ?> class="<?= $beda ? 'bg-amber-50/50' : '' ?>">
                            <td class="px-4 py-2 tabular-nums text-slate-500"><?= (int) $b['posisi_lama'] ?></td>
                            <td class="px-3 py-2 tabular-nums <?= $b['pindah'] ? 'font-extrabold text-amber-700' : 'text-slate-500' ?>"><?= (int) $b['posisi_baru'] ?><?= $b['pindah'] ? ' ↑↓' : '' ?></td>
                            <td class="px-3 py-2 font-semibold text-slate-800"><?= esc($b['nama']) ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= esc($b['jabatan_lama'] !== '' ? $b['jabatan_lama'] : '—') ?></td>
                            <td class="px-3 py-2 <?= $b['label_beda'] ? 'font-bold text-amber-700' : 'text-slate-500' ?>"><?= esc($b['jabatan_baru'] !== '' ? $b['jabatan_baru'] : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($jmlBeda === 0): ?>
                        <tr x-show="hanya"><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada yang berubah.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
