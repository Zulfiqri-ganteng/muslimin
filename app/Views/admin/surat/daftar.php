<?php
/**
 * Daftar Surat Sekolah — buku agenda surat keluar (semua jenis) dengan tab status dan saringan.
 *
 * @var string               $peran
 * @var array<string, int>   $hitung
 * @var array<string, mixed> $f       saringan aktif: status, jenis, tahun, q
 * @var list<array>          $rows
 * @var int                  $total
 * @var int                  $page
 * @var int                  $jmlHal
 * @var list<int>            $tahun   tahun yang ada di data
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\SuratJenis;
use App\Models\SuratSekolahModel;

$adaSaringan = $f['jenis'] !== '' || $f['tahun'] > 0 || $f['q'] !== '' || $f['status'] !== '';

// Kotak centang: ACC massal pada tab Menunggu (hanya yang berhak ACC); unduh massal pada tab Siap unduh yang sudah
// disaring ke satu jenis (hanya yang berhak mengelola surat dan bila template jenis itu ada).
$bisaPilihAcc   = $f['status'] === 'menunggu' && ! empty($bolehAcc);
$bisaPilihUnduh = ! empty($bisaUnduhMassal);
$bisaPilih      = $bisaPilihAcc || $bisaPilihUnduh;
$formPilih      = $bisaPilihAcc ? 'formAcc' : 'formUnduh';
$keterangan  = [
    ''             => 'Semua surat sekolah yang pernah dibuat, dari semua jenis. Nomor surat diterbitkan sekali, saat surat pertama kali diunduh.',
    'menunggu'     => 'Menunggu persetujuan (ACC). Yang paling lama menunggu ada di atas.',
    'dikembalikan' => 'Dikembalikan oleh yang meng-ACC dan menunggu diperbaiki.',
    'disetujui'    => 'Sudah boleh diunduh: sudah di-ACC, atau jenis surat yang tidak memerlukan ACC.',
    'dibatalkan'   => 'Dibatalkan. Nomor yang sudah terbit tetap tercatat dan tidak dipakai lagi.',
];
$urlHal = static function (int $hal) use ($f): string {
    return site_url('admin/surat') . '?' . http_build_query(array_filter($f + ['page' => $hal > 1 ? $hal : null], static fn ($v) => $v !== '' && $v !== 0 && $v !== null));
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_daftar_v2',
    'helpTitle' => 'Daftar Surat Sekolah',
    'helpBody'  => '<p>Di sini tercatat <b>semua surat sekolah</b> (Izin ASTS, Izin TKA, Pernyataan Orang Tua PKL, Surat Balasan PKL, Penarikan Izin PKL) &mdash; siapa yang membuat, statusnya, dan nomornya. Pilih tab status di atas, atau saring menurut jenis, tahun, dan kata kunci (nomor, judul, atau nama perusahaan).</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Menunggu ACC</b>: surat sudah disiapkan dan menunggu persetujuan. <b>Siap unduh</b>: boleh diunduh. <b>Dikembalikan</b>: perlu diperbaiki dulu.</li>'
        . '<li>Nomor surat <b>diterbitkan sekali</b>, saat surat pertama diunduh, dan satu urutan dengan Surat Izin PKL, jadi tidak ada dua surat bernomor sama. Unduh ulang memakai nomor yang sama.</li>'
        . '<li>Di tab <b>Menunggu ACC</b>, yang berhak ACC (bawaan: Waka Hubin) bisa mencentang lalu <b>ACC terpilih</b>, atau <b>ACC semua yang aman</b>. Surat yang punya peringatan (mis. nama penanda tangan belum diisi) dilewati supaya diperiksa satu per satu di halaman detailnya.</li>'
        . '<li>Di tab <b>Siap unduh</b>, saring dulu ke <b>satu jenis surat</b>; yang berhak mengelola surat (bawaan: Operator) bisa mengunduh terpilih, yang belum diunduh / perlu cetak ulang, atau semuanya sekaligus (lebih dari 60 surat dikirim sebagai ZIP).</li>'
        . '<li>Menu jenis surat di samping yang masih bertanda <i>Segera</i> belum bisa dipakai.</li>'
        . '</ul>',
]) ?>

<?= view('admin/surat/_nav', ['hitung' => $hitung, 'f' => $f]) ?>

<div class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="flex items-center gap-2 font-bold text-slate-800"><?= esc($title) ?> <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500"><?= (int) $total ?></span></h2>
        <p class="mt-0.5 max-w-2xl text-xs text-slate-400"><?= esc($keterangan[$f['status']] ?? '') ?></p>

        <form method="get" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_12rem_9rem_auto]">
            <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= esc($f['status'], 'attr') ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="Cari nomor, judul, atau perusahaan…" class="inp" aria-label="Cari">
            <select name="jenis" class="inp" aria-label="Jenis surat">
                <option value="">Semua jenis</option>
                <?php foreach (SuratJenis::kode() as $k): ?>
                    <option value="<?= esc($k, 'attr') ?>" <?= $f['jenis'] === $k ? 'selected' : '' ?>><?= esc(SuratJenis::label($k)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" class="inp" aria-label="Tahun">
                <option value="">Semua tahun</option>
                <?php foreach ($tahun as $t): ?>
                    <option value="<?= (int) $t ?>" <?= $f['tahun'] === (int) $t ? 'selected' : '' ?>><?= (int) $t ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Cari</button>
                <?php if ($f['jenis'] !== '' || $f['tahun'] > 0 || $f['q'] !== ''): ?>
                    <a href="<?= site_url('admin/surat') . ($f['status'] !== '' ? '?status=' . urlencode($f['status']) : '') ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($f['status'] === 'menunggu' && $rows !== [] && empty($bolehAcc)): ?>
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-600">🔒 <b>ACC dilakukan peran yang diberi hak ACC</b> (bawaan: Waka Hubin). Sebagai <?= esc($peranLabel ?? 'staf') ?> Anda bisa melihat surat yang menunggu dan membukanya.</div>
    <?php endif; ?>
    <?php if ($bisaPilihAcc && $rows !== []): ?>
        <form id="formAcc" method="post" action="<?= site_url('admin/surat/acc-massal') ?>" class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center">
            <?= csrf_field() ?>
            <?php if (($peran ?? '') === 'admin'): ?>
                <label class="flex w-full cursor-pointer items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900"><input type="checkbox" name="wakil" value="1" required class="mt-0.5"> <span>Saya meng-ACC sebagai <b>Admin yang mewakili Waka Hubin</b> (Waka Hubin berhalangan). Tercatat atas nama saya dan tertulis di surat.</span></label>
            <?php endif; ?>
            <span class="text-xs leading-relaxed text-slate-500 sm:mr-auto"><b class="text-green-700">ACC massal:</b> hanya surat tanpa peringatan yang di-ACC. Yang punya peringatan (mis. nama penanda tangan belum diisi, atau tanpa siswa) dilewati agar Anda periksa satu per satu.</span>
            <button type="submit" name="mode" value="terpilih" onclick="return confirm('ACC semua surat yang dicentang? Yang punya peringatan akan dilewati.')" class="rounded-lg border border-green-300 bg-white px-3.5 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">✓ ACC terpilih</button>
            <button type="submit" name="mode" value="aman" onclick="return confirm('ACC SEMUA surat yang menunggu dan tanpa peringatan (maks. 200)? Yang punya peringatan akan dilewati.')" class="rounded-lg bg-green-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-green-700">✓ ACC semua yang aman</button>
        </form>
    <?php endif; ?>
    <?php if ($bisaPilihUnduh && $rows !== []): ?>
        <form id="formUnduh" method="post" action="<?= site_url('admin/surat/unduh-massal') ?>" data-unduh class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="jenis" value="<?= esc($f['jenis'], 'attr') ?>">
            <span class="text-xs leading-relaxed text-slate-500 sm:mr-auto">Unduh <?= esc(SuratJenis::label($f['jenis'])) ?>: centang suratnya lalu unduh. Yang belum bernomor otomatis diberi nomor menurut urutan persetujuan. Lebih dari 60 surat dikirim sebagai ZIP.</span>
            <button type="submit" name="mode" value="terpilih" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">⬇ Unduh terpilih</button>
            <button type="submit" name="mode" value="belum" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">⬇ Yang belum diunduh / perlu cetak ulang</button>
            <button type="submit" name="mode" value="semua" class="rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">⬇ Unduh SEMUA</button>
        </form>
    <?php elseif ($f['status'] === 'disetujui' && $rows !== [] && ! empty($bolehBuat) && $f['jenis'] === ''): ?>
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-500">Untuk <b>mengunduh banyak surat sekaligus</b>, saring dulu menurut satu jenis surat (pilihan "Semua jenis" di atas). Unduhan satuan ada di halaman tiap surat.</div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <?php if (! $adaSaringan): ?>
            <!-- Belum ada surat sama sekali: kenalkan jenis-jenisnya -->
            <div class="px-5 py-8">
                <p class="text-center font-semibold text-slate-700">Belum ada surat yang dibuat.</p>
                <p class="mx-auto mt-1 max-w-xl text-center text-xs leading-relaxed text-slate-400">Surat dibuat dari menu jenis suratnya masing-masing. Setelah dibuat (dan di-ACC bila perlu), surat muncul di sini lengkap dengan nomor dan riwayatnya.</p>
                <ul class="mx-auto mt-6 grid max-w-4xl gap-3 sm:grid-cols-2">
                    <?php foreach (SuratJenis::kode() as $k):
                        $siap  = SuratJenis::siap($k);
                        $boleh = $siap && HakAkses::boleh($peran, SuratJenis::alamat($k));
                    ?>
                        <li class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc(SuratJenis::badge($k), 'attr') ?>"><?= esc(SuratJenis::label($k)) ?></span>
                                <?php if ($boleh): ?>
                                    <a href="<?= site_url(SuratJenis::alamat($k)) ?>" class="shrink-0 text-xs font-semibold text-brand-700 hover:underline">Buka →</a>
                                <?php elseif (! $siap): ?>
                                    <span class="shrink-0 rounded-full bg-gold-400/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Segera</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500"><?= esc(SuratJenis::DATA[$k]['ringkas']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="px-5 py-12 text-center">
                <p class="font-semibold text-slate-700">Tidak ada surat yang cocok dengan saringan ini.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <?php if ($bisaPilih): ?><th class="w-10 px-5 py-3"><input type="checkbox" aria-label="Pilih semua" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"></th><?php endif; ?>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-3 py-3 font-semibold">Jenis</th>
                        <th class="px-3 py-3 font-semibold">Perihal / tujuan</th>
                        <th class="px-3 py-3 font-semibold">Tanggal surat</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-3 py-3 font-semibold">Dibuat oleh</th>
                        <th class="px-3 py-3 text-center font-semibold">Diunduh</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r):
                        [$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$r['status']] ?? [$r['status'], 'bg-slate-100 text-slate-600'];
                        $lihat = site_url('admin/surat/' . (int) $r['id']);
                    ?>
                        <tr class="hover:bg-slate-50">
                            <?php if ($bisaPilih): ?><td class="px-5 py-3"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" aria-label="Pilih <?= esc($r['judul'], 'attr') ?>"></td><?php endif; ?>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs font-semibold text-slate-700"><?= (string) $r['nomor'] !== '' ? esc($r['nomor']) : '<span class="font-sans font-normal text-slate-300">belum bernomor</span>' ?></td>
                            <td class="px-3 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc(SuratJenis::badge((string) $r['jenis']), 'attr') ?>"><?= esc(SuratJenis::singkat((string) $r['jenis'])) ?></span></td>
                            <td class="px-3 py-3">
                                <a href="<?= $lihat ?>" class="font-semibold text-slate-800 hover:text-brand-700"><?= esc($r['judul']) ?></a>
                                <p class="text-xs text-slate-400"><?= esc(trim(((string) $r['perusahaan_nama'] !== '' ? $r['perusahaan_nama'] : '') . ((int) $r['jml_siswa'] > 0 ? ((string) $r['perusahaan_nama'] !== '' ? ' · ' : '') . (int) $r['jml_siswa'] . ' siswa' : ''))) ?></p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?></td>
                            <td class="px-3 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
                                <?php if (! empty($perluUlang[(int) $r['id']])): ?><p class="mt-1 text-xs font-bold text-amber-600">⚠ perlu cetak ulang</p><?php endif; ?>
                                <?php if ($r['status'] === 'disetujui' && (string) $r['acc_nama'] !== ''): ?><p class="mt-1 text-xs text-slate-400">ACC: <?= esc($r['acc_nama']) ?><?= (string) $r['acc_peran'] === 'admin' ? ' (Admin, mewakili Hubin)' : '' ?></p><?php endif; ?>
                                <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 max-w-[14rem] text-xs text-slate-500"><?= esc($r['catatan_staf']) ?></p><?php endif; ?></td>
                            <td class="px-3 py-3 text-xs text-slate-500"><?= esc((string) $r['dibuat_nama'] !== '' ? $r['dibuat_nama'] : '—') ?><span class="block text-slate-400"><?= esc(date('d-m-Y H:i', strtotime((string) $r['created_at']))) ?></span></td>
                            <td class="px-3 py-3 text-center text-xs text-slate-500"><?= (int) $r['cetak_ke'] > 0 ? (int) $r['cetak_ke'] . '×' : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right"><a href="<?= $lihat ?>" class="inline-flex rounded-lg bg-brand-700 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">Buka</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP): bisa dicentang agar ACC terpilih / unduh terpilih juga jalan di HP -->
        <?php if ($bisaPilih): ?>
            <label class="flex items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-500 md:hidden"><input type="checkbox" onclick="document.querySelectorAll('.pilih-hp').forEach(c => c.checked = this.checked)" class="h-4 w-4"> Pilih semua di halaman ini</label>
        <?php endif; ?>
        <ul class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $r):
                [$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$r['status']] ?? [$r['status'], 'bg-slate-100 text-slate-600'];
            ?>
                <li class="flex items-stretch">
                    <?php if ($bisaPilih): ?><label class="flex shrink-0 items-center pl-4"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" class="pilih-hp h-4 w-4" aria-label="Pilih <?= esc($r['judul'], 'attr') ?>"></label><?php endif; ?>
                    <a href="<?= site_url('admin/surat/' . (int) $r['id']) ?>" class="block min-w-0 flex-1 px-4 py-3.5 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-slate-800"><?= esc($r['judul']) ?></p>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
                        </div>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold <?= esc(SuratJenis::badge((string) $r['jenis']), 'attr') ?>"><?= esc(SuratJenis::singkat((string) $r['jenis'])) ?></span>
                            <span class="font-mono font-semibold text-slate-600"><?= (string) $r['nomor'] !== '' ? esc($r['nomor']) : 'belum bernomor' ?></span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?><?= (int) $r['jml_siswa'] > 0 ? ' · ' . (int) $r['jml_siswa'] . ' siswa' : '' ?> · oleh <?= esc((string) $r['dibuat_nama'] !== '' ? $r['dibuat_nama'] : '—') ?></p>
                        <?php if (! empty($perluUlang[(int) $r['id']])): ?><p class="mt-1 text-xs font-bold text-amber-600">⚠ perlu cetak ulang</p><?php endif; ?>
                        <?php if ($r['status'] === 'disetujui' && (string) $r['acc_nama'] !== ''): ?><p class="mt-1 text-xs text-slate-500">ACC: <b><?= esc($r['acc_nama']) ?></b><?= (string) $r['acc_peran'] === 'admin' ? ' (Admin, mewakili Hubin)' : '' ?></p><?php endif; ?>
                        <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 text-xs text-slate-500"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($jmlHal > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">← Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-slate-400">Halaman <?= (int) $page ?> dari <?= (int) $jmlHal ?></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">Berikutnya →</a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
