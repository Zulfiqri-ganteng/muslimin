<?php
/**
 * Pratinjau impor honor dari Excel lama — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memeriksa pencocokan nama
 * ke Master Guru dan memutuskan tiap baris, lalu menekan "Terapkan".
 *
 * @var string $slug  @var array $periode  @var string $label  @var string $token  @var string $berkas
 * @var array  $payload  hasil HonorImpor::baca()
 * @var array  $baris    hasil HonorImpor::cocokkan() (status, guru_id, kandidat)
 * @var array  $analisis hasil HonorImpor::analisis()
 * @var bool   $adaDok   @var int $jmlLama  @var string $kembali
 */
$rp = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$hit = ['cocok' => 0, 'mirip' => 0, 'ganda' => 0, 'tidak' => 0];
foreach ($baris as $b) {
    $hit[$b['status']]++;
}
$badge = [
    'cocok' => ['Cocok', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'mirip' => ['Mirip — periksa', 'bg-amber-50 text-amber-800 border-amber-200'],
    'ganda' => ['Nama ganda — pilih', 'bg-orange-50 text-orange-800 border-orange-200'],
    'tidak' => ['Belum ada di Master Guru', 'bg-red-50 text-red-700 border-red-200'],
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4" x-data="{ semua(v) { document.querySelectorAll('select[data-tidak]').forEach(s => { s.value = v; }); } }">

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= esc($label) ?></p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Periksa hasil bacaan Excel</h2>
                <p class="mt-1 text-sm text-slate-500">Berkas: <b class="text-slate-700"><?= esc($berkas) ?></b> · lembar &quot;<?= esc($payload['lembar']) ?>&quot; · <?= count($baris) ?> baris penerima. <b>Belum ada yang tersimpan.</b></p>
            </div>
            <a href="<?= esc($kembali) ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal, kembali</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Belum ada'] as $k => $l): ?>
                <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= $l ?></p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $hit[$k] ?></p></div>
            <?php endforeach; ?>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total hitungan sistem</p><p class="text-lg font-extrabold tabular-nums text-brand-700">Rp <?= $rp($analisis['total']) ?></p></div>
        </div>
        <?php $peringatan = array_merge($payload['peringatan'], $analisis['peringatan']); ?>
        <?php if ($analisis['kolom_tanpa_komponen'] !== []): ?>
            <?php $peringatan[] = 'Kolom ini di Excel tidak punya komponen padanan di honor, jadi tidak diimpor: ' . implode(', ', $analisis['kolom_tanpa_komponen']) . '.'; ?>
        <?php endif; ?>
        <?php if ($analisis['total_excel'] > 0 && $analisis['total_excel'] !== $analisis['total']): ?>
            <?php $peringatan[] = 'Jumlah kolom TOTAL di Excel (Rp ' . $rp($analisis['total_excel']) . ') berbeda dari hitungan sistem (Rp ' . $rp($analisis['total']) . '). Sistem selalu menghitung ulang dari jumlah dan tarif — periksa selisihnya bila perlu.'; ?>
        <?php endif; ?>
        <?php if ($peringatan !== []): ?>
            <ul class="mt-4 list-disc space-y-1 rounded-xl border border-amber-300 bg-amber-50 px-8 py-3 text-sm text-amber-900"><?php foreach (array_slice($peringatan, 0, 15) as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/impor/terapkan') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if ($hit['tidak'] > 0): ?>
                <div class="flex flex-col gap-2 border-b border-slate-100 bg-red-50 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-red-800"><b><?= $hit['tidak'] ?> nama</b> belum ada di Master Guru. Pilih tindakan tiap nama, atau atur semuanya sekaligus:</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="semua('baru:guru')" class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Tambahkan semua sebagai guru</button>
                        <button type="button" @click="semua('baru:staf')" class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Tambahkan semua sebagai staf</button>
                        <button type="button" @click="semua('lewati')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Lewati semua</button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-2">No</th><th class="px-3 py-2">Nama di Excel</th><th class="px-3 py-2">Jabatan</th><th class="px-3 py-2">Pencocokan</th><th class="px-3 py-2">Tindakan</th><th class="px-4 py-2 text-right">Total (sistem)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($baris as $i => $b):
                            $st      = $b['status'];
                            $bawaan  = $b['guru_id'] !== null ? 'guru:' . (int) $b['guru_id'] : 'lewati';
                            ?>
                            <tr class="align-top hover:bg-slate-50">
                                <td class="px-4 py-2 tabular-nums text-slate-400"><?= $b['no'] ?? ($i + 1) ?></td>
                                <td class="px-3 py-2 font-semibold text-slate-800"><?= esc($b['nama']) ?></td>
                                <td class="px-3 py-2 text-slate-500"><?= esc($b['jabatan'] ?: '—') ?></td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold <?= $badge[$st][1] ?>"><?= $badge[$st][0] ?></span></td>
                                <td class="px-3 py-2">
                                    <select name="aksi[<?= $i ?>]" <?= $st === 'tidak' ? 'data-tidak' : '' ?> class="w-full max-w-xs rounded-lg border border-slate-300 px-2 py-1.5 text-xs outline-none focus:border-brand-500" aria-label="Tindakan untuk <?= esc($b['nama'], 'attr') ?>">
                                        <?php foreach ($b['kandidat'] as $c): ?>
                                            <option value="guru:<?= (int) $c['id'] ?>" <?= $bawaan === 'guru:' . (int) $c['id'] ? 'selected' : '' ?>>Pakai: <?= esc($c['nama']) ?> (kode <?= esc($c['kode_guru']) ?>)<?= $c['staf'] ? ' · staf' : '' ?></option>
                                        <?php endforeach; ?>
                                        <option value="baru:guru">Tambahkan ke Master Guru sebagai guru</option>
                                        <option value="baru:staf">Tambahkan ke Master Guru sebagai staf</option>
                                        <option value="lewati" <?= $bawaan === 'lewati' ? 'selected' : '' ?>>Lewati baris ini</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-right font-bold tabular-nums text-slate-800">Rp <?= $rp($analisis['total_baris'][$i] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <?php if ($jmlLama > 0): ?>
                <label class="mb-4 flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                    <input type="checkbox" name="bersihkan" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                    <span>Ganti seluruh daftar: <b>hapus dulu <?= $jmlLama ?> penerima yang sudah ada</b> di honor ini, lalu isi dari Excel. (Bila tidak dicentang, orang yang sudah ada diperbarui dan yang lain dibiarkan.)</span>
                </label>
            <?php endif; ?>
            <p class="mb-3 text-xs leading-relaxed text-slate-500">
                Yang diimpor: jumlah/nominal tiap komponen. Rupiah <b>dihitung ulang</b> dari tarif honor<?= $adaDok ? '' : ' (honor dibuat otomatis dari komponen aktif di Pengaturan Honor)' ?>. Nama Ketua, Bendahara, dan Kepala Sekolah dari Excel hanya mengisi yang masih kosong.
            </p>
            <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Terapkan impor</button>
        </section>
    </form>
</div>

<?= $this->endSection() ?>
