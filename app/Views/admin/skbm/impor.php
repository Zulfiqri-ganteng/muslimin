<?php
/**
 * Pratinjau impor SKBM dari Excel sekolah — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memilih blok kolom kelas,
 * memeriksa pencocokan kolom kelas & nama guru, memutuskan tiap guru, lalu menekan "Terapkan impor".
 * Rombak tampilan F7: kartu pilihan blok, kartu angka, ui_peringatan, tabel kit + lencana pencocokan, formulir kit;
 * komponen Alpine "imporSkbm" (assets/js/admin/skbm/impor.js) → siap CSP.
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
    'cocok' => ['Cocok', 'lencana-hijau'],
    'mirip' => ['Mirip — periksa', 'lencana-kuning'],
    'ganda' => ['Nama ganda — pilih', 'lencana-ungu'],
    'tidak' => ['Tidak ada di Master Guru', 'lencana-merah'],
];
$totalJp  = 0;
$totalSel = 0;
foreach ($payload['guru'] as $g) {
    $totalJp  += $g['jp'];
    $totalSel += $g['kelas'];
}
$kotakAngka = static fn (string $lbl, string $isi): string => '<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700/50">'
    . '<p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">' . $lbl . '</p>'
    . '<p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white">' . $isi . '</p></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($back, 'attr') ?>" class="btn btn-netral"><?= ikon('close', 'h-4 w-4') ?>Batal, kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="kartu kartu-isi" aria-labelledby="judul-bacaan">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">SKBM &middot; impor Excel</p>
        <h2 id="judul-bacaan" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Periksa hasil bacaan Excel SKBM</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Berkas: <b class="text-gray-900 dark:text-white"><?= esc($berkas) ?></b> &middot; lembar &quot;<?= esc($payload['lembar']) ?>&quot; &middot; <?= count($payload['guru']) ?> guru, <?= $jmlBaris ?> baris mapel. <b>Belum ada yang tersimpan.</b></p>

        <!-- Pilihan blok kolom kelas -->
        <div class="mt-4">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Blok kolom kelas di lembar ini</p>
            <?php if (count($blok) > 1): ?>
                <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Lembar ini punya <b><?= count($blok) ?> blok</b> kolom kelas yang berdampingan. Yang dipilih otomatis ialah blok dengan kolom kelas paling banyak dikenal Master Kelas (seri &rarr; yang paling kanan). Bila isinya bukan penugasan SK yang benar, pilih blok lain.</p>
            <?php endif; ?>
            <div class="mt-2 grid gap-3 <?= $kolBlok ?>">
                <?php foreach ($blok as $b): $aktif = $b['indeks'] === $pilih; ?>
                    <a href="<?= esc($base . '/impor?blok=' . (int) $b['indeks'], 'attr') ?>"<?= $aktif ? ' aria-current="true"' : '' ?>
                       class="block rounded-lg border px-4 py-3 transition-colors <?= $aktif ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600 dark:border-brand-500 dark:bg-brand-900/30 dark:ring-brand-500' : 'border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700' ?>">
                        <p class="flex items-center justify-between gap-2 text-sm font-semibold <?= $aktif ? 'text-brand-700 dark:text-brand-300' : 'text-gray-900 dark:text-white' ?>">
                            <span>Blok <?= (int) $b['indeks'] + 1 ?> &middot; kolom <?= esc($b['dari']) ?>&ndash;<?= esc($b['sampai']) ?></span>
                            <?php if ($aktif): ?><span class="lencana lencana-merek"><?= ikon('check', 'h-3 w-3') ?>dipakai</span><?php endif; ?>
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= count($b['kelas']) ?> kolom kelas (<b class="<?= $b['dikenal'] === count($b['kelas']) ? 'text-green-700 dark:text-green-400' : 'text-yellow-700 dark:text-yellow-400' ?>"><?= (int) $b['dikenal'] ?> dikenal</b>) &middot; <?= $rp($statBlok[$b['indeks']]['sel']) ?> sel berangka &middot; <?= $rp($statBlok[$b['indeks']]['jp']) ?> JP</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-6">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Tidak ada'] as $k => $l): ?>
                <?= $kotakAngka($l, (string) $hit[$k]) ?>
            <?php endforeach; ?>
            <?= $kotakAngka('Guru &times; kelas', $rp($totalSel)) ?>
            <div class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-800 dark:bg-brand-900/30">
                <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:text-brand-300">Total JP di Excel</p>
                <p class="text-xl font-bold text-brand-700 tabular-nums dark:text-brand-300"><?= $rp($totalJp) ?></p>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <?php if ($kelasTak !== []): ?>
                <?= ui_peringatan('galat', '', ['html' => '<b>' . count($kelasTak) . ' kolom kelas tidak dikenal</b> di Master Kelas, angkanya <b>dilewati</b>: ' . esc(implode(', ', array_map(static fn (array $k): string => trim($k['grup'] . ' ' . $k['tingkat'] . ' ' . $k['label']), array_slice($kelasTak, 0, 10)))) . (count($kelasTak) > 10 ? ', …' : '') . '.']) ?>
            <?php else: ?>
                <?= ui_peringatan('sukses', 'Semua ' . count($blok[$pilih]['kelas']) . ' kolom kelas pada blok ini dikenal di Master Kelas.') ?>
            <?php endif; ?>
            <?php if ($tanpa !== []): ?>
                <?= ui_peringatan('awas', '', ['html' => '<b>' . count($tanpa) . ' guru tidak ditemukan di Master Guru</b> (' . esc(implode(', ', array_slice($tanpa, 0, 8))) . (count($tanpa) > 8 ? ', …' : '') . '). Pilih orangnya di kolom kanan bila namanya hanya beda penulisan, atau biarkan dilewati.']) ?>
            <?php endif; ?>
            <?php if (($payload['peringatan'] ?? []) !== []): ?>
                <?= ui_peringatan('awas', array_slice($payload['peringatan'], 0, 15)) ?>
            <?php endif; ?>
        </div>
    </section>

    <form method="post" action="<?= esc($base, 'attr') ?>/impor/terapkan" class="space-y-4" x-data="imporSkbm" data-tujuan="<?= esc($tujuan, 'attr') ?>" data-berisi="<?= esc((string) json_encode((object) $berisi), 'attr') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
        <input type="hidden" name="blok" value="<?= $pilih ?>">

        <section class="kartu overflow-hidden" aria-label="Guru di Excel">
            <div class="tabel-bungkus">
                <table class="tabel min-w-[56rem]">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama di Excel</th>
                            <th scope="col" class="text-right">Baris mapel</th>
                            <th scope="col" class="text-right">Kelas</th>
                            <th scope="col" class="text-right">JP</th>
                            <th scope="col">Pencocokan</th>
                            <th scope="col">Guru di Master Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payload['guru'] as $i => $g): ?>
                            <?php [$teks, $kls] = $badge[$g['status']]; $kand = $g['kandidat'] ?? []; ?>
                            <tr class="<?= $g['status'] === 'tidak' || $g['status'] === 'ganda' ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
                                <td class="text-gray-500 tabular-nums dark:text-gray-400"><?= $g['no'] !== null ? (int) $g['no'] : '' ?></td>
                                <td class="font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?></td>
                                <td class="text-right tabular-nums"><?= count($g['baris']) ?></td>
                                <td class="text-right tabular-nums"><?= (int) $g['kelas'] ?></td>
                                <td class="text-right tabular-nums"><?= $rp($g['jp']) ?></td>
                                <td><span class="lencana <?= $kls ?>"><?= esc($teks) ?></span></td>
                                <td>
                                    <select name="aksi[<?= $i ?>]" class="inp py-1.5" aria-label="Guru di Master Guru untuk <?= esc($g['nama'], 'attr') ?>">
                                        <option value="lewati"<?= $g['guru_id'] === null ? ' selected' : '' ?>>— lewati guru ini —</option>
                                        <?php if ($kand !== []): ?>
                                            <optgroup label="Kemungkinan">
                                                <?php foreach ($kand as $c): ?><option value="guru:<?= (int) $c['id'] ?>"<?= (int) $g['guru_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= esc($c['nama']) ?></option><?php endforeach; ?>
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

        <section class="kartu kartu-isi space-y-4" aria-label="Terapkan impor">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,16rem)_1fr] sm:items-start">
                <div>
                    <label for="tujuan" class="lbl">Simpan ke tahun ajaran</label>
                    <input id="tujuan" name="tujuan" type="text" x-model="tujuan" value="<?= esc($tujuan, 'attr') ?>" required pattern="\d{4}/\d{4}" maxlength="9" inputmode="numeric" placeholder="2026/2027" class="inp font-semibold tabular-nums">
                    <p class="hint"><?= $payload['tahun'] !== null ? 'Terbaca dari lembar: ' . esc($payload['tahun']) . '.' : 'Tahun tidak terbaca dari lembar — isi sendiri.' ?></p>
                </div>
                <div>
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                        <input type="checkbox" name="ganti" value="1" checked class="cek mt-0.5">
                        <span><b>Ganti seluruh SKBM tahun itu</b>. Tanpa tanda ini, guru yang sudah ada di SKBM tahun itu dilewati.
                            <b x-show="adaIsi()" x-cloak class="block text-yellow-700 dark:text-yellow-400" x-text="teksIsi()"></b>
                        </span>
                    </label>
                    <p class="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">Yang disimpan hanya SKBM (data di menu Penugasan, Jadwal KBM, dan honor <b>tidak berubah</b>). Isi sel angka = JP per minggu. Ceklis Koreksi honor baru terisi saat kamu menekan &quot;Isi dari SKBM&quot; di halaman Koreksi.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Terapkan impor</button>
        </section>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/skbm/impor.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/skbm/impor.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
