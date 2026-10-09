<?php
/**
 * Pratinjau impor SKBM dari Excel sekolah — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memilih blok kolom kelas,
 * memeriksa pencocokan kolom kelas & nama guru, memutuskan tiap guru, lalu menekan "Terapkan impor".
 *
 * @var string               $token
 * @var string               $berkas
 * @var array                $payload  hasil SkbmImpor::baca()+cocokkan()
 * @var string               $tujuan   tahun ajaran tujuan (tebakan)
 * @var array<string,int>    $berisi   tahun => jumlah guru yang sudah ada di SKBM
 * @var list<array>          $guruOpsi
 * @var string               $back
 */
$rp    = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$base  = site_url('admin/skbm');
$pilih = (int) $payload['blok_dipilih'];
$blok  = $payload['blok'];

// per blok: jumlah sel berangka dan JP (untuk membantu memilih blok yang benar)
$statBlok = [];
foreach ($blok as $b) {
    $statBlok[$b['indeks']] = ['sel' => 0, 'jp' => 0];
}
foreach ($payload['guru'] as $g) {
    foreach ($g['baris'] as $br) {
        foreach ($br['sel'] as $bi => $isi) {
            $statBlok[$bi]['sel'] += count($isi);
            $statBlok[$bi]['jp']  += array_sum($isi);
        }
    }
}
$jmlBaris = 0;
$tanpa    = [];
$hit      = ['cocok' => 0, 'mirip' => 0, 'ganda' => 0, 'tidak' => 0];
foreach ($payload['guru'] as $g) {
    $jmlBaris += count($g['baris']);
    $hit[$g['status']]++;
    if ($g['status'] === 'tidak') {
        $tanpa[] = $g['nama'];
    }
}
$kelasTak = array_values(array_filter($blok[$pilih]['kelas'], static fn (array $k): bool => $k['kelas_id'] === null));
$kolBlok  = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3'][min(3, max(1, count($blok)))]; // nama kelas utuh agar terbaca pemindai Tailwind
$badge = [
    'cocok' => ['Cocok', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'mirip' => ['Mirip — periksa', 'bg-amber-50 text-amber-800 border-amber-200'],
    'ganda' => ['Nama ganda — pilih', 'bg-orange-50 text-orange-800 border-orange-200'],
    'tidak' => ['Tidak ada di Master Guru', 'bg-red-50 text-red-700 border-red-200'],
];
$totalJp  = 0;
$totalSel = 0;
foreach ($payload['guru'] as $g) {
    $totalJp  += $g['jp'];
    $totalSel += $g['kelas'];
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">SKBM &middot; khusus Admin</p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Periksa hasil bacaan Excel SKBM</h2>
                <p class="mt-1 text-sm text-slate-500">Berkas: <b class="text-slate-700"><?= esc($berkas) ?></b> &middot; lembar &quot;<?= esc($payload['lembar']) ?>&quot; &middot; <?= count($payload['guru']) ?> guru, <?= $jmlBaris ?> baris mapel. <b>Belum ada yang tersimpan.</b></p>
            </div>
            <a href="<?= esc($back, 'attr') ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal, kembali</a>
        </div>

        <!-- Pilihan blok kolom kelas -->
        <div class="mt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Blok kolom kelas di lembar ini</p>
            <?php if (count($blok) > 1): ?>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">Lembar ini punya <b><?= count($blok) ?> blok</b> kolom kelas yang berdampingan. Yang dipilih otomatis ialah blok dengan kolom kelas paling banyak dikenal Master Kelas (seri &rarr; yang paling kanan). Bila isinya bukan penugasan SK yang benar, pilih blok lain.</p>
            <?php endif; ?>
            <div class="mt-2 grid gap-3 <?= $kolBlok ?>">
                <?php foreach ($blok as $b): $aktif = $b['indeks'] === $pilih; ?>
                    <a href="<?= esc($base . '/impor?blok=' . (int) $b['indeks'], 'attr') ?>" class="block rounded-xl border px-4 py-3 transition <?= $aktif ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600' : 'border-slate-200 hover:bg-slate-50' ?>" <?= $aktif ? 'aria-current="true"' : '' ?>>
                        <p class="flex items-center justify-between text-sm font-bold <?= $aktif ? 'text-brand-700' : 'text-slate-700' ?>">Blok <?= (int) $b['indeks'] + 1 ?> &middot; kolom <?= esc($b['dari']) ?>&ndash;<?= esc($b['sampai']) ?><?= $aktif ? '<span class="rounded-full bg-brand-700 px-2 py-0.5 text-[11px] font-semibold text-white">dipakai</span>' : '' ?></p>
                        <p class="mt-1 text-xs text-slate-500"><?= count($b['kelas']) ?> kolom kelas (<b class="<?= $b['dikenal'] === count($b['kelas']) ? 'text-emerald-700' : 'text-amber-700' ?>"><?= (int) $b['dikenal'] ?> dikenal</b>) &middot; <?= $rp($statBlok[$b['indeks']]['sel']) ?> sel berangka &middot; <?= $rp($statBlok[$b['indeks']]['jp']) ?> JP</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-6">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Tidak ada'] as $k => $l): ?>
                <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= $l ?></p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $hit[$k] ?></p></div>
            <?php endforeach; ?>
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Guru &times; kelas</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $rp($totalSel) ?></p></div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total JP di Excel</p><p class="text-xl font-extrabold tabular-nums text-brand-700"><?= $rp($totalJp) ?></p></div>
        </div>

        <?php if ($kelasTak !== []): ?>
            <p class="mt-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"><b><?= count($kelasTak) ?> kolom kelas tidak dikenal</b> di Master Kelas, angkanya <b>dilewati</b>: <?= esc(implode(', ', array_map(static fn (array $k): string => trim($k['grup'] . ' ' . $k['tingkat'] . ' ' . $k['label']), array_slice($kelasTak, 0, 10)))) ?><?= count($kelasTak) > 10 ? ', …' : '' ?>.</p>
        <?php else: ?>
            <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Semua <?= count($blok[$pilih]['kelas']) ?> kolom kelas pada blok ini dikenal di Master Kelas.</p>
        <?php endif; ?>
        <?php if ($tanpa !== []): ?>
            <p class="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><b><?= count($tanpa) ?> guru tidak ditemukan di Master Guru</b> (<?= esc(implode(', ', array_slice($tanpa, 0, 8))) ?><?= count($tanpa) > 8 ? ', …' : '' ?>). Pilih orangnya di kolom kanan bila namanya hanya beda penulisan, atau biarkan dilewati.</p>
        <?php endif; ?>
        <?php if (($payload['peringatan'] ?? []) !== []): ?>
            <ul class="mt-3 list-disc space-y-1 rounded-xl border border-amber-300 bg-amber-50 px-8 py-3 text-sm text-amber-900"><?php foreach (array_slice($payload['peringatan'], 0, 15) as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= esc($base, 'attr') ?>/impor/terapkan" class="space-y-4" x-data="{ tujuan: '<?= esc($tujuan, 'js') ?>', berisi: <?= esc(json_encode((object) $berisi), 'attr') ?> }">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
        <input type="hidden" name="blok" value="<?= $pilih ?>">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-2">No</th><th class="px-3 py-2">Nama di Excel</th><th class="px-3 py-2 text-right">Baris mapel</th><th class="px-3 py-2 text-right">Kelas</th><th class="px-3 py-2 text-right">JP</th><th class="px-3 py-2">Pencocokan</th><th class="px-3 py-2">Guru di Master Guru</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($payload['guru'] as $i => $g): ?>
                            <?php [$teks, $kls] = $badge[$g['status']]; $kand = $g['kandidat'] ?? []; ?>
                            <tr class="<?= $g['status'] === 'tidak' || $g['status'] === 'ganda' ? 'bg-red-50/40' : '' ?>">
                                <td class="px-4 py-2 tabular-nums text-slate-500"><?= $g['no'] !== null ? (int) $g['no'] : '' ?></td>
                                <td class="px-3 py-2 font-semibold text-slate-800"><?= esc($g['nama']) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600"><?= count($g['baris']) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600"><?= (int) $g['kelas'] ?></td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600"><?= $rp($g['jp']) ?></td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $kls ?>"><?= esc($teks) ?></span></td>
                                <td class="px-3 py-2">
                                    <select name="aksi[<?= $i ?>]" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" aria-label="Guru di Master Guru untuk <?= esc($g['nama'], 'attr') ?>">
                                        <option value="lewati" <?= $g['guru_id'] === null ? 'selected' : '' ?>>— lewati guru ini —</option>
                                        <?php if ($kand !== []): ?>
                                            <optgroup label="Kemungkinan">
                                                <?php foreach ($kand as $c): ?><option value="guru:<?= (int) $c['id'] ?>" <?= (int) $g['guru_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['nama']) ?></option><?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <optgroup label="Semua guru">
                                            <?php foreach ($guruOpsi as $o): ?><option value="guru:<?= (int) $o['id'] ?>"><?= esc($o['nama']) ?></option><?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,16rem)_1fr] sm:items-start">
                <div>
                    <label for="tujuan" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Simpan ke tahun ajaran</label>
                    <input id="tujuan" name="tujuan" type="text" value="<?= esc($tujuan, 'attr') ?>" x-model="tujuan" required pattern="\d{4}/\d{4}" maxlength="9" inputmode="numeric" placeholder="2026/2027" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold tabular-nums outline-none focus:border-brand-500">
                    <p class="mt-1 text-xs text-slate-500"><?= $payload['tahun'] !== null ? 'Terbaca dari lembar: ' . esc($payload['tahun']) . '.' : 'Tahun tidak terbaca dari lembar — isi sendiri.' ?></p>
                </div>
                <div>
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                        <input type="checkbox" name="ganti" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300">
                        <span><b>Ganti seluruh SKBM tahun itu</b>. Tanpa tanda ini, guru yang sudah ada di SKBM tahun itu dilewati.
                            <template x-if="berisi[tujuan]"><b class="block text-amber-700" x-text="'SKBM ' + tujuan + ' sekarang sudah berisi ' + berisi[tujuan] + ' guru dan akan dibuang.'"></b></template>
                        </span>
                    </label>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Yang disimpan hanya SKBM (data di menu Penugasan, Jadwal KBM, dan honor <b>tidak berubah</b>). Isi sel angka = JP per minggu. Ceklis Koreksi honor baru terisi saat kamu menekan &quot;Isi dari SKBM&quot; di halaman Koreksi.</p>
                </div>
            </div>
            <button type="submit" class="mt-4 rounded-lg bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Terapkan impor</button>
        </section>
    </form>
</div>

<?= $this->endSection() ?>
