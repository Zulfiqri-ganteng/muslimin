<?php
/**
 * Kotak masuk PKL — daftar ajuan satu status.
 *
 * @var string      $status   menunggu | perbaikan | disetujui | ditolak
 * @var list<array> $rows
 * @var int         $total
 * @var string      $q
 * @var int         $kelasId
 * @var list<array> $kelas
 * @var int         $page
 * @var int         $jmlHal
 */

use App\Libraries\IsianBantu;
use App\Models\PklPengajuanModel;

// Kotak centang tabel: ACC massal hanya untuk yang berhak ACC (Waka Hubin / Admin); surat massal untuk semua staf.
$bisaPilih  = ($status === 'disetujui' && ! empty($bolehSurat)) || ($status === 'menunggu' && ! empty($bolehAcc));
$formPilih  = $status === 'menunggu' ? 'formAcc' : 'formMassal';
$keterangan = [
    'menunggu'  => 'Ajuan baru dari siswa, menunggu keputusan Waka Hubin (ACC hanya oleh Waka Hubin). Yang paling lama menunggu ada di atas.',
    'perbaikan' => 'Sudah dikembalikan ke siswa dan menunggu mereka memperbaiki. Bila siswa kesulitan, Anda bisa mengubah langsung.',
    'disetujui' => 'Sudah di-ACC. Siswa-siswa di sini terkunci dan tidak bisa mengajukan lagi.',
    'ditolak'   => 'Ditolak. Siswanya sudah bebas dan boleh mengajukan lagi dari awal.',
];
$urlHal = static function (int $hal) use ($status, $q, $kelasId): string {
    return site_url('admin/pkl/daftar/' . $status) . '?' . http_build_query(array_filter(['q' => $q, 'kelas_id' => $kelasId ?: null, 'page' => $hal > 1 ? $hal : null]));
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_daftar_v4',
    'helpTitle' => 'Kotak Masuk PKL',
    'helpBody'  => '<p>Pilih tab status di atas. Cari berdasarkan <b>nama perusahaan</b> atau <b>nama siswa</b>, atau saring per kelas. Klik baris untuk membuka detail dan mengambil keputusan.</p>'
        . '<p class="mt-2">Satu baris = satu <b>perusahaan</b> dengan rombongan siswanya (pengaju + teman satu tempat).</p>'
        . '<p class="mt-2">Di tab <b>Menunggu ACC</b> kolom <b>Pemeriksaan</b> menandai ajuan <b>✓ Aman</b> atau <b>⚠ ada peringatan</b>. Tombol <b>ACC massal</b> hanya menyetujui yang Aman; sisanya dilewati supaya diperiksa satu per satu. Di tab <b>Disetujui</b> yang berhak (diatur Admin) bisa mengunduh surat PKL banyak sekaligus: <b>Unduh surat terpilih</b> (yang dicentang), <b>Yang belum dicetak / perlu cetak ulang</b>, atau <b>Unduh SEMUA surat</b>. Sebelum berkas dibuat muncul kotak <b>Catat biaya</b> (Biaya PKL, SPP, Tabungan, OSIS, beasiswa) &mdash; wajib minimal satu centang per siswa. Setelah unduhan selesai muncul tombol <b>Kabari via WhatsApp</b> untuk memberi tahu siswa. Di HP, centang ada di kiri tiap kartu.</p>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-slate-800"><?= esc($title) ?> <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500"><?= (int) $total ?></span></h2>
                <p class="mt-0.5 max-w-2xl text-xs text-slate-400"><?= esc($keterangan[$status] ?? '') ?></p>
            </div>
            <a href="<?= site_url('admin/pkl/baru') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">+ Isi atas Nama</a>
        </div>

        <form method="get" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_14rem_auto]">
            <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari perusahaan atau nama siswa…" class="inp" aria-label="Cari">
            <select name="kelas_id" class="inp" aria-label="Kelas">
                <option value="">Semua kelas</option>
                <?php foreach ($kelas as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>><?= esc($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Cari</button>
                <?php if ($q !== '' || $kelasId > 0): ?>
                    <a href="<?= site_url('admin/pkl/daftar/' . $status) ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($status === 'menunggu' && $rows !== [] && empty($bolehAcc)): ?>
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-600">🔒 <b>ACC hanya dilakukan Waka Hubin.</b> Sebagai <?= esc($peranLabel ?? 'staf') ?> Anda bisa memeriksa ajuan, mengembalikannya untuk diperbaiki, dan mengubah datanya.</div>
    <?php endif; ?>
    <?php if ($status === 'menunggu' && $rows !== [] && ! empty($bolehAcc)): ?>
        <form id="formAcc" method="post" action="<?= site_url('admin/pkl/acc-massal') ?>" class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center">
            <?= csrf_field() ?>
            <?php if (($peran ?? '') === 'admin'): ?>
                <label class="flex w-full cursor-pointer items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900"><input type="checkbox" name="wakil" value="1" required class="mt-0.5"> <span>Saya meng-ACC sebagai <b>Admin yang mewakili Waka Hubin</b> (Waka Hubin berhalangan). Tercatat atas nama saya dan tertulis di surat.</span></label>
            <?php endif; ?>
            <span class="text-xs leading-relaxed text-slate-500 sm:mr-auto"><b class="text-green-700">ACC massal:</b> hanya ajuan berlabel <b class="text-green-700">✓ Aman</b> (tanpa peringatan) yang di-ACC. Yang bertanda ⚠ dilewati agar Anda periksa satu per satu.</span>
            <button type="submit" name="mode" value="terpilih" onclick="return confirm('ACC semua ajuan yang dicentang? Yang punya peringatan akan dilewati.')" class="rounded-lg border border-green-300 bg-white px-3.5 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">✓ ACC terpilih</button>
            <button type="submit" name="mode" value="aman" onclick="return confirm('ACC SEMUA ajuan yang menunggu dan berlabel Aman (maks. 200)? Yang punya peringatan akan dilewati.')" class="rounded-lg bg-green-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-green-700">✓ ACC semua yang aman</button>
        </form>
    <?php endif; ?>
    <?php if ($status === 'disetujui' && $rows !== []): ?>
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center">
            <?php if (trim((string) ($p['waka_hubin_nama'] ?? '')) === ''): ?>
                <p class="w-full rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs font-semibold leading-relaxed text-red-800">⚠ Nama Waka Hubin belum diisi, jadi di bawah tanda tangan surat hanya titik-titik.
                    <?php if ($bolehPengaturan): ?><a href="<?= site_url('admin/pkl/pengaturan#surat') ?>" class="underline">Isi di Pengaturan PKL</a>, lalu unduh ulang.<?php else: ?>Minta yang berhak mengisinya di Pengaturan PKL.<?php endif; ?></p>
            <?php endif; ?>
            <?php if (! empty($bolehSurat)): ?>
                <form id="formMassal" onsubmit="return false"></form>
                <span class="text-xs leading-relaxed text-slate-500 sm:mr-auto">Surat PKL: centang ajuan lalu unduh. Sebelum berkas dibuat, Anda <b>wajib mencatat biaya</b> yang diterima dari tiap siswa. Yang belum bernomor otomatis diberi nomor sesuai urutan persetujuan.</span>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('pkl-unduh', {detail: {mode: 'terpilih'}}))" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">⬇ Unduh surat terpilih</button>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('pkl-unduh', {detail: {mode: 'belum'}}))" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">⬇ Yang belum dicetak / perlu cetak ulang</button>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('pkl-unduh', {detail: {mode: 'semua'}}))" class="rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">⬇ Unduh SEMUA surat</button>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('pkl-wa', {detail: {ids: Array.prototype.map.call(document.querySelectorAll('input[name=&quot;ids[]&quot;]:checked'), function (c) { return c.value; })}}))" class="rounded-lg border border-green-300 bg-white px-3.5 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">💬 Kabari via WhatsApp (terpilih)</button>
            <?php else: ?>
                <p class="text-xs leading-relaxed text-slate-600">🔒 <b>Surat diunduh oleh peran yang diberi hak itu oleh Admin</b> (bawaan: Operator Sekolah), supaya jelas siapa yang menyetujui dan siapa yang mencetak. Anda tetap bisa melihat nomor dan status tiap surat di sini.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($rows === []): ?>
        <div class="px-5 py-12 text-center">
            <p class="font-semibold text-slate-700"><?= ($q !== '' || $kelasId > 0) ? 'Tidak ada ajuan yang cocok dengan pencarian.' : 'Belum ada ajuan di sini.' ?></p>
        </div>
    <?php else: ?>
        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <?php if ($bisaPilih): ?><th class="w-10 px-5 py-3"><input type="checkbox" aria-label="Pilih semua" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"></th><?php endif; ?><th class="px-5 py-3 font-semibold">Bukti</th>
                        <th class="px-3 py-3 font-semibold">Perusahaan</th>
                        <th class="px-3 py-3 font-semibold">Pengaju</th>
                        <th class="px-3 py-3 font-semibold">Dikirim siswa</th>
                        <th class="px-3 py-3 font-semibold">Diperbarui</th>
                        <?php if ($status === 'disetujui'): ?><th class="px-3 py-3 font-semibold">Disetujui oleh</th><?php endif; ?>
                        <?php if ($status === 'disetujui'): ?><th class="px-3 py-3 font-semibold">Surat</th><?php endif; ?><?php if ($status === 'menunggu'): ?><th class="px-3 py-3 font-semibold">Pemeriksaan</th><?php endif; ?><th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <?php if ($bisaPilih): ?><td class="px-5 py-3"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" aria-label="Pilih <?= esc($r['perusahaan_nama'], 'attr') ?>"></td><?php endif; ?><td class="px-5 py-3 font-mono text-xs font-semibold text-slate-500"><?= esc(PklPengajuanModel::kode((int) $r['id'])) ?></td>
                            <td class="px-3 py-3">
                                <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="font-semibold text-slate-800 hover:text-brand-700"><?= esc($r['perusahaan_nama']) ?></a>
                                <p class="text-xs text-slate-400"><?= esc($r['perusahaan_kota'] ?? '') ?><?= $r['sumber'] !== 'siswa' ? ' · ' . ($r['sumber'] === 'staf' ? 'diisi staf' : 'impor') : '' ?><?= (int) $r['kirim_ke'] > 1 ? ' · revisi ke-' . ((int) $r['kirim_ke'] - 1) : '' ?></p>
                            </td>
                            <td class="px-3 py-3">
                                <p class="font-medium text-slate-700"><?= esc($r['pengaju'] ?? '—') ?></p>
                                <p class="text-xs text-slate-400"><?= esc($r['pengaju_kelas'] ?? '') ?><?= (int) $r['jumlah'] > 1 ? ' · +' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?></p>
                            </td>
                            <?php $kirimAt = (string) ($r['diajukan_at'] ?? $r['created_at']); $sisa = $status === 'menunggu' ? PklPengajuanModel::sisaHari($kirimAt, (int) ($batasHari ?? 5)) : null; ?>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500"><?= esc(date('d-m-Y H:i', strtotime($kirimAt))) ?>
                                <?php if ($sisa !== null): ?><br><span class="font-bold <?= $sisa < 0 ? 'text-red-600' : ($sisa <= 1 ? 'text-amber-600' : 'text-slate-400') ?>"><?= $sisa < 0 ? 'TERLAMBAT ' . abs($sisa) . ' hari' : ($sisa === 0 ? 'batas HARI INI' : 'sisa ' . $sisa . ' hari') ?></span><?php endif; ?></td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-400"><?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></td>
                            <?php if ($status === 'disetujui'): ?><td class="px-3 py-3 text-xs"><?php if (! empty($r['acc_nama'])): ?><span class="font-semibold text-slate-700"><?= esc($r['acc_nama']) ?></span><span class="block text-slate-400"><?= esc(match ((string) ($r['acc_peran'] ?? '')) { 'hubin' => 'Waka Hubin', 'admin' => 'Admin (mewakili Hubin)', 'operator' => 'Operator (data lama)', 'impor' => 'Diimpor', default => '' }) ?><?= ! empty($r['acc_at']) ? ' · ' . esc(date('d-m-Y H:i', strtotime((string) $r['acc_at']))) : '' ?></span><?php else: ?><span class="text-slate-300">—</span><?php endif; ?></td><?php endif; ?>
                            <?php if ($status === 'disetujui'): $ss = $statusSurat[(int) $r['id']] ?? null; ?><td class="px-3 py-3 text-xs"><?php if ($ss): ?><span class="font-mono font-semibold text-slate-600"><?= esc($ss['nomor']) ?></span><?php if ($ss['perlu_ulang']): ?><span class="mt-0.5 block font-bold text-amber-600">⚠ perlu cetak ulang</span><?php endif; ?><?php else: ?><span class="text-slate-300">belum ada</span><?php endif; ?></td><?php endif; ?><?php if ($status === 'menunggu'): $c = $cek[(int) $r['id']] ?? null; ?><td class="px-3 py-3 text-xs"><?php if ($c && ($c['bahaya'] + $c['awas']) === 0): ?><span class="font-bold text-green-600">✓ Aman</span><?php elseif ($c): ?><span class="font-bold <?= $c['bahaya'] ? 'text-red-600' : 'text-amber-600' ?>" title="<?= esc($c['pertama'], 'attr') ?>">⚠ <?= (int) ($c['bahaya'] + $c['awas']) ?> peringatan</span><?php endif; ?></td><?php endif; ?><td class="whitespace-nowrap px-5 py-3 text-right"><?php if ($status === 'disetujui' && ! empty($bolehSurat) && ! empty($statusSurat[(int) $r['id']])): ?><button type="button" title="Kabari siswa lewat WhatsApp" onclick="window.dispatchEvent(new CustomEvent('pkl-wa', {detail: {ids: [<?= (int) $r['id'] ?>]}}))" class="mr-1.5 inline-flex rounded-lg border border-green-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-green-700 transition hover:bg-green-50">💬 WA</button><?php endif; ?><a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="inline-flex rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800"><?= $status === 'menunggu' ? 'Periksa' : 'Buka' ?></a></td>
                        </tr>
                        <?php if (! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
                            <tr><td></td><td colspan="5" class="px-3 pb-3 text-xs text-slate-500"><span class="font-semibold text-slate-600">Catatan sekolah:</span> <?= esc($r['catatan_staf']) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP): bisa dicentang agar ACC terpilih / unduh surat terpilih juga jalan di HP -->
        <?php if ($bisaPilih): ?>
            <label class="flex items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-500 md:hidden"><input type="checkbox" onclick="document.querySelectorAll('.pilih-hp').forEach(c => c.checked = this.checked)" class="h-4 w-4"> Pilih semua di halaman ini</label>
        <?php endif; ?>
        <ul class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $r): ?>
                <li class="flex items-stretch">
                    <?php if ($bisaPilih): ?><label class="flex shrink-0 items-center pl-4"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" class="pilih-hp h-4 w-4" aria-label="Pilih <?= esc($r['perusahaan_nama'], 'attr') ?>"></label><?php endif; ?>
                    <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="block min-w-0 flex-1 px-4 py-3.5 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-slate-800"><?= esc($r['perusahaan_nama']) ?></p>
                            <span class="shrink-0 font-mono text-[11px] font-semibold text-slate-400"><?= esc(PklPengajuanModel::kode((int) $r['id'])) ?></span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500"><?= esc($r['pengaju'] ?? '—') ?> (<?= esc($r['pengaju_kelas'] ?? '') ?>)<?= (int) $r['jumlah'] > 1 ? ' + ' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?></p>
                        <?php $kirimHp = (string) ($r['diajukan_at'] ?? $r['created_at']); $sisaHp = $status === 'menunggu' ? PklPengajuanModel::sisaHari($kirimHp, (int) ($batasHari ?? 5)) : null; ?>
                        <p class="mt-1 text-xs text-slate-400">Dikirim <?= esc(date('d-m-Y H:i', strtotime($kirimHp))) ?><?php if ($sisaHp !== null): ?> · <b class="<?= $sisaHp < 0 ? 'text-red-600' : ($sisaHp <= 1 ? 'text-amber-600' : 'text-slate-500') ?>"><?= $sisaHp < 0 ? 'TERLAMBAT ' . abs($sisaHp) . ' hari' : ($sisaHp === 0 ? 'batas HARI INI' : 'sisa ' . $sisaHp . ' hari') ?></b><?php endif; ?></p>
                        <?php if ($status === 'disetujui' && ! empty($r['acc_nama'])): ?><p class="mt-1 text-xs text-slate-500">Disetujui: <b><?= esc($r['acc_nama']) ?></b><?= ($r['acc_peran'] ?? '') === 'admin' ? ' (Admin, mewakili Hubin)' : '' ?></p><?php endif; ?>
                        <?php if ($status === 'menunggu' && ($cHp = $cek[(int) $r['id']] ?? null)): ?><p class="mt-1 text-xs font-bold <?= ($cHp['bahaya'] + $cHp['awas']) === 0 ? 'text-green-600' : ($cHp['bahaya'] ? 'text-red-600' : 'text-amber-600') ?>"><?= ($cHp['bahaya'] + $cHp['awas']) === 0 ? '✓ Aman' : '⚠ ' . (int) ($cHp['bahaya'] + $cHp['awas']) . ' peringatan' ?></p><?php endif; ?>
                        <?php if ($status === 'disetujui'): $ssHp = $statusSurat[(int) $r['id']] ?? null; ?><p class="mt-1 text-xs"><?= $ssHp ? '<span class="font-mono font-semibold text-slate-600">' . esc($ssHp['nomor']) . '</span>' . ($ssHp['perlu_ulang'] ? ' <b class="text-amber-600">⚠ perlu cetak ulang</b>' : '') : '<span class="text-slate-300">surat belum ada</span>' ?></p><?php endif; ?>
                        <?php if (! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
                            <p class="mt-1 text-xs text-slate-500"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($jmlHal > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">← Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-slate-400">Halaman <?= $page ?> dari <?= $jmlHal ?></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">Berikutnya →</a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($status === 'disetujui' && ! empty($bolehSurat)): // kotak dialog catat biaya + kabar WhatsApp ?>
    <?= view('admin/pkl/_unduh') ?>
<?php endif; ?>

<?= $this->endSection() ?>
