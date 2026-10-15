<?php
/**
 * Pratinjau impor honor dari Excel lama — KHUSUS ADMIN. Belum ada yang tersimpan; Admin memeriksa pencocokan nama
 * ke Master Guru dan memutuskan tiap baris, lalu menekan "Terapkan".
 * Rombak tampilan F7: kartu angka, ui_peringatan, tabel kit + lencana pencocokan, tombol ber-ikon; komponen Alpine "imporHonor"
 * (assets/js/admin/ujian/honor-impor.js) untuk tombol "atur semua sekaligus" → siap CSP.
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
    'cocok' => ['Cocok', 'lencana-hijau'],
    'mirip' => ['Mirip — periksa', 'lencana-kuning'],
    'ganda' => ['Nama ganda — pilih', 'lencana-ungu'],
    'tidak' => ['Belum ada di Master Guru', 'lencana-merah'],
];
$peringatan = array_merge($payload['peringatan'], $analisis['peringatan']);
if ($analisis['kolom_tanpa_komponen'] !== []) {
    $peringatan[] = 'Kolom ini di Excel tidak punya komponen padanan di honor, jadi tidak diimpor: ' . implode(', ', $analisis['kolom_tanpa_komponen']) . '.';
}
if ($analisis['total_excel'] > 0 && $analisis['total_excel'] !== $analisis['total']) {
    $peringatan[] = 'Jumlah kolom TOTAL di Excel (Rp ' . $rp($analisis['total_excel']) . ') berbeda dari hitungan sistem (Rp ' . $rp($analisis['total']) . '). Sistem selalu menghitung ulang dari jumlah dan tarif — periksa selisihnya bila perlu.';
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('close', 'h-4 w-4') ?>Batal, kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4" x-data="imporHonor">

    <section class="kartu kartu-isi" aria-labelledby="judul-bacaan">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($label) ?></p>
        <h2 id="judul-bacaan" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Periksa hasil bacaan Excel</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Berkas: <b class="text-gray-900 dark:text-white"><?= esc($berkas) ?></b> · lembar &quot;<?= esc($payload['lembar']) ?>&quot; · <?= count($baris) ?> baris penerima. <b>Belum ada yang tersimpan.</b></p>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <?php foreach (['cocok' => 'Cocok', 'mirip' => 'Mirip', 'ganda' => 'Nama ganda', 'tidak' => 'Belum ada'] as $k => $l): ?>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700/50">
                    <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= $l ?></p>
                    <p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white"><?= $hit[$k] ?></p>
                </div>
            <?php endforeach; ?>
            <div class="col-span-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 sm:col-span-1 dark:border-brand-800 dark:bg-brand-900/30">
                <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:text-brand-300">Total hitungan sistem</p>
                <p class="text-lg font-bold text-brand-700 tabular-nums dark:text-brand-300">Rp <?= $rp($analisis['total']) ?></p>
            </div>
        </div>
        <?php if ($peringatan !== []): ?>
            <div class="mt-4"><?= ui_peringatan('awas', array_slice($peringatan, 0, 15)) ?></div>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/impor/terapkan') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

        <section class="kartu overflow-hidden" aria-label="Baris penerima">
            <?php if ($hit['tidak'] > 0): ?>
                <div class="flex flex-col gap-2 border-b border-gray-200 bg-red-50 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-red-900/20">
                    <p class="text-red-800 dark:text-red-300"><b><?= $hit['tidak'] ?> nama</b> belum ada di Master Guru. Pilih tindakan tiap nama, atau atur semuanya sekaligus:</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-netral btn-sm" @click="semua('baru:guru')"><?= ikon('user-graduate', 'h-4 w-4') ?>Tambahkan semua sebagai guru</button>
                        <button type="button" class="btn btn-netral btn-sm" @click="semua('baru:staf')"><?= ikon('user', 'h-4 w-4') ?>Tambahkan semua sebagai staf</button>
                        <button type="button" class="btn btn-teks btn-sm" @click="semua('lewati')">Lewati semua</button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="tabel-bungkus">
                <table class="tabel min-w-[52rem]">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama di Excel</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col">Pencocokan</th>
                            <th scope="col">Tindakan</th>
                            <th scope="col" class="text-right">Total (sistem)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $i => $b):
                            $st     = $b['status'];
                            $bawaan = $b['guru_id'] !== null ? 'guru:' . (int) $b['guru_id'] : 'lewati';
                            ?>
                            <tr class="align-top">
                                <td class="text-gray-500 tabular-nums dark:text-gray-400"><?= $b['no'] ?? ($i + 1) ?></td>
                                <td class="font-semibold text-gray-900 dark:text-white"><?= esc($b['nama']) ?></td>
                                <td class="text-gray-500 dark:text-gray-400"><?= esc($b['jabatan'] ?: '—') ?></td>
                                <td><span class="lencana <?= $badge[$st][1] ?>"><?= $badge[$st][0] ?></span></td>
                                <td>
                                    <select name="aksi[<?= $i ?>]"<?= $st === 'tidak' ? ' data-tidak' : '' ?> class="inp max-w-xs py-1.5 text-xs" aria-label="Tindakan untuk <?= esc($b['nama'], 'attr') ?>">
                                        <?php foreach ($b['kandidat'] as $c): ?>
                                            <option value="guru:<?= (int) $c['id'] ?>"<?= $bawaan === 'guru:' . (int) $c['id'] ? ' selected' : '' ?>>Pakai: <?= esc($c['nama']) ?> (kode <?= esc($c['kode_guru']) ?>)<?= $c['staf'] ? ' · staf' : '' ?></option>
                                        <?php endforeach; ?>
                                        <option value="baru:guru">Tambahkan ke Master Guru sebagai guru</option>
                                        <option value="baru:staf">Tambahkan ke Master Guru sebagai staf</option>
                                        <option value="lewati"<?= $bawaan === 'lewati' ? ' selected' : '' ?>>Lewati baris ini</option>
                                    </select>
                                </td>
                                <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white">Rp <?= $rp($analisis['total_baris'][$i] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kartu kartu-isi space-y-3" aria-label="Terapkan impor">
            <?php if ($jmlLama > 0): ?>
                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                    <input type="checkbox" name="bersihkan" value="1" class="cek mt-0.5">
                    <span>Ganti seluruh daftar: <b>hapus dulu <?= $jmlLama ?> penerima yang sudah ada</b> di honor ini, lalu isi dari Excel. (Bila tidak dicentang, orang yang sudah ada diperbarui dan yang lain dibiarkan.)</span>
                </label>
            <?php endif; ?>
            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                Yang diimpor: jumlah/nominal tiap komponen. Rupiah <b>dihitung ulang</b> dari tarif honor<?= $adaDok ? '' : ' (honor dibuat otomatis dari komponen aktif di Pengaturan Honor)' ?>. Nama Ketua, Bendahara, dan Kepala Sekolah dari Excel hanya mengisi yang masih kosong.
            </p>
            <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Terapkan impor</button>
        </section>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/ujian/honor-impor.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/honor-impor.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
