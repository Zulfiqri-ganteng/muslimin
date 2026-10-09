<?php
/**
 * Pratinjau impor ceklis Koreksi dari Excel sekolah — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memeriksa pencocokan
 * kolom kelas dan nama guru, memutuskan tiap guru, lalu menekan "Terapkan".
 *
 * @var string $slug  @var array $periode  @var string $label  @var string $token  @var string $berkas
 * @var array  $payload   hasil HonorKoreksiImpor::baca()+cocokkan()  @var list<array> $penerima  penerima honor (id, nama)
 * @var bool   $adaCeklis @var bool $terkunci  @var string $kembali
 */
$rp = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$jmlSel = 0;
$jmlBaris = 0;
$total = 0;
foreach ($payload['guru'] as $g) {
    $total += $g['total'];
    foreach ($g['baris'] as $br) {
        $jmlBaris++;
        $jmlSel += count($br['sel']);
    }
}
$hit = ['cocok' => 0, 'mirip' => 0, 'ganda' => 0, 'tidak' => 0];
foreach ($payload['guru'] as $g) {
    $hit[$g['status']]++;
}
$kelasTak = array_values(array_filter($payload['kelas'], static fn (array $k): bool => $k['kelas_id'] === null));
$badge = [
    'cocok' => ['Cocok', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'mirip' => ['Mirip — periksa', 'bg-amber-50 text-amber-800 border-amber-200'],
    'ganda' => ['Nama ganda — pilih', 'bg-orange-50 text-orange-800 border-orange-200'],
    'tidak' => ['Tidak ada di penerima honor', 'bg-red-50 text-red-700 border-red-200'],
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= esc($label) ?></p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Periksa hasil bacaan Excel KOREKSI</h2>
                <p class="mt-1 text-sm text-slate-500">Berkas: <b class="text-slate-700"><?= esc($berkas) ?></b> · lembar &quot;<?= esc($payload['lembar']) ?>&quot; · <?= count($payload['guru']) ?> guru, <?= $jmlBaris ?> baris mapel. <b>Belum ada yang tersimpan.</b></p>
            </div>
            <a href="<?= esc($kembali) ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal, kembali</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Tidak ada'] as $k => $l): ?>
                <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= $l ?></p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $hit[$k] ?></p></div>
            <?php endforeach; ?>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total lembar di Excel</p><p class="text-lg font-extrabold tabular-nums text-brand-700"><?= $rp($total) ?></p></div>
        </div>
        <?php if ($kelasTak !== []): ?>
            <p class="mt-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"><b><?= count($kelasTak) ?> kolom kelas tidak dikenal</b> di Master Kelas, angkanya <b>dilewati</b>: <?= esc(implode(', ', array_map(static fn (array $k): string => trim($k['grup'] . ' ' . $k['label']), array_slice($kelasTak, 0, 10)))) ?><?= count($kelasTak) > 10 ? ', …' : '' ?>.</p>
        <?php else: ?>
            <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Semua <?= count($payload['kelas']) ? count($payload['kelas']) : 0 ?> kolom kelas dikenal di Master Kelas.</p>
        <?php endif; ?>
        <?php if (($payload['peringatan'] ?? []) !== []): ?>
            <ul class="mt-4 list-disc space-y-1 rounded-xl border border-amber-300 bg-amber-50 px-8 py-3 text-sm text-amber-900"><?php foreach (array_slice($payload['peringatan'], 0, 15) as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/koreksi/impor/terapkan') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-2">No</th><th class="px-3 py-2">Nama di Excel</th><th class="px-3 py-2 text-right">Baris mapel</th><th class="px-3 py-2 text-right">Lembar</th><th class="px-3 py-2">Pencocokan</th><th class="px-3 py-2">Penerima honor</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($payload['guru'] as $i => $g): ?>
                            <?php [$teks, $kls] = $badge[$g['status']]; ?>
                            <tr class="<?= $g['status'] === 'tidak' || $g['status'] === 'ganda' ? 'bg-red-50/40' : '' ?>">
                                <td class="px-4 py-2 tabular-nums text-slate-500"><?= $g['no'] !== null ? (int) $g['no'] : '' ?></td>
                                <td class="px-3 py-2 font-semibold text-slate-800"><?= esc($g['nama']) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600"><?= count($g['baris']) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600"><?= $rp($g['total']) ?></td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $kls ?>"><?= esc($teks) ?></span></td>
                                <td class="px-3 py-2">
                                    <select name="aksi[<?= $i ?>]" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" aria-label="Penerima honor untuk <?= esc($g['nama'], 'attr') ?>">
                                        <option value="lewati" <?= $g['baris_id'] === null ? 'selected' : '' ?>>— lewati guru ini —</option>
                                        <?php foreach ($penerima as $p): ?>
                                            <option value="baris:<?= (int) $p['id'] ?>" <?= (int) $g['baris_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                <input type="checkbox" name="ganti" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span><b>Ganti seluruh ceklis yang sekarang</b><?= $adaCeklis ? ' <span class="text-amber-700">(ceklis sekarang sudah berisi dan akan dibuang)</span>' : '' ?>. Tanpa tanda ini, guru yang sudah punya isi dilewati.</span>
            </label>
            <p class="mt-2 text-xs leading-relaxed text-slate-500">Jumlah peserta tiap kelas diambil dari angka yang paling sering muncul di kolom kelasnya; sel yang angkanya berbeda disimpan sebagai angka khusus. Angka <b>Koreksi di honor tidak berubah</b> sampai kamu menekan &quot;Terapkan ke kolom Koreksi honor&quot; di halaman ceklis.</p>
            <?php if ($terkunci): ?>
                <p class="mt-3 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">Honor DIKUNCI &mdash; tidak bisa diubah.</p>
            <?php else: ?>
                <button type="submit" class="mt-4 rounded-lg bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Terapkan impor</button>
            <?php endif; ?>
        </section>
    </form>
</div>

<?= $this->endSection() ?>
