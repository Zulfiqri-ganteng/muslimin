<?php
/**
 * Pratinjau impor ceklis Koreksi dari Excel sekolah — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memeriksa pencocokan
 * kolom kelas dan nama guru, memutuskan tiap guru, lalu menekan "Terapkan".
 * Rombak tampilan F7: kartu angka, ui_peringatan, tabel kit + lencana pencocokan, tombol ber-ikon (tanpa Alpine).
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
    'cocok' => ['Cocok', 'lencana-hijau'],
    'mirip' => ['Mirip — periksa', 'lencana-kuning'],
    'ganda' => ['Nama ganda — pilih', 'lencana-ungu'],
    'tidak' => ['Tidak ada di penerima honor', 'lencana-merah'],
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('close', 'h-4 w-4') ?>Batal, kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="kartu kartu-isi" aria-labelledby="judul-bacaan">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($label) ?></p>
        <h2 id="judul-bacaan" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Periksa hasil bacaan Excel KOREKSI</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Berkas: <b class="text-gray-900 dark:text-white"><?= esc($berkas) ?></b> · lembar &quot;<?= esc($payload['lembar']) ?>&quot; · <?= count($payload['guru']) ?> guru, <?= $jmlBaris ?> baris mapel. <b>Belum ada yang tersimpan.</b></p>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Tidak ada'] as $k => $l): ?>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700/50">
                    <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= $l ?></p>
                    <p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white"><?= $hit[$k] ?></p>
                </div>
            <?php endforeach; ?>
            <div class="col-span-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 sm:col-span-1 dark:border-brand-800 dark:bg-brand-900/30">
                <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:text-brand-300">Total lembar di Excel</p>
                <p class="text-lg font-bold text-brand-700 tabular-nums dark:text-brand-300"><?= $rp($total) ?></p>
            </div>
        </div>
        <div class="mt-4 space-y-3">
            <?php if ($kelasTak !== []): ?>
                <?= ui_peringatan('galat', '', ['html' => '<b>' . count($kelasTak) . ' kolom kelas tidak dikenal</b> di Master Kelas, angkanya <b>dilewati</b>: ' . esc(implode(', ', array_map(static fn (array $k): string => trim($k['grup'] . ' ' . $k['label']), array_slice($kelasTak, 0, 10)))) . (count($kelasTak) > 10 ? ', …' : '') . '.']) ?>
            <?php else: ?>
                <?= ui_peringatan('sukses', 'Semua ' . count($payload['kelas']) . ' kolom kelas dikenal di Master Kelas.') ?>
            <?php endif; ?>
            <?php if (($payload['peringatan'] ?? []) !== []): ?>
                <?= ui_peringatan('awas', array_slice($payload['peringatan'], 0, 15)) ?>
            <?php endif; ?>
        </div>
    </section>

    <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/koreksi/impor/terapkan') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

        <section class="kartu overflow-hidden" aria-label="Guru di Excel">
            <div class="tabel-bungkus">
                <table class="tabel min-w-[52rem]">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama di Excel</th>
                            <th scope="col" class="text-right">Baris mapel</th>
                            <th scope="col" class="text-right">Lembar</th>
                            <th scope="col">Pencocokan</th>
                            <th scope="col">Penerima honor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payload['guru'] as $i => $g): ?>
                            <?php [$teks, $kls] = $badge[$g['status']]; ?>
                            <tr class="<?= $g['status'] === 'tidak' || $g['status'] === 'ganda' ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
                                <td class="text-gray-500 tabular-nums dark:text-gray-400"><?= $g['no'] !== null ? (int) $g['no'] : '' ?></td>
                                <td class="font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?></td>
                                <td class="text-right tabular-nums"><?= count($g['baris']) ?></td>
                                <td class="text-right tabular-nums"><?= $rp($g['total']) ?></td>
                                <td><span class="lencana <?= $kls ?>"><?= esc($teks) ?></span></td>
                                <td>
                                    <select name="aksi[<?= $i ?>]" class="inp py-1.5" aria-label="Penerima honor untuk <?= esc($g['nama'], 'attr') ?>">
                                        <option value="lewati"<?= $g['baris_id'] === null ? ' selected' : '' ?>>— lewati guru ini —</option>
                                        <?php foreach ($penerima as $p): ?>
                                            <option value="baris:<?= (int) $p['id'] ?>"<?= (int) $g['baris_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= esc($p['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kartu kartu-isi space-y-3" aria-label="Terapkan impor">
            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                <input type="checkbox" name="ganti" value="1" checked class="cek mt-0.5">
                <span><b>Ganti seluruh ceklis yang sekarang</b><?= $adaCeklis ? ' <span class="text-yellow-700 dark:text-yellow-400">(ceklis sekarang sudah berisi dan akan dibuang)</span>' : '' ?>. Tanpa tanda ini, guru yang sudah punya isi dilewati.</span>
            </label>
            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">Jumlah peserta tiap kelas diambil dari angka yang paling sering muncul di kolom kelasnya; sel yang angkanya berbeda disimpan sebagai angka khusus. Angka <b>Koreksi di honor tidak berubah</b> sampai kamu menekan &quot;Terapkan ke kolom Koreksi honor&quot; di halaman ceklis.</p>
            <?php if ($terkunci): ?>
                <?= ui_peringatan('netral', 'Honor DIKUNCI — tidak bisa diubah.') ?>
            <?php else: ?>
                <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Terapkan impor</button>
            <?php endif; ?>
        </section>
    </form>
</div>

<?= $this->endSection() ?>
