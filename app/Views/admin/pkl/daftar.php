<?php
/**
 * Kotak masuk PKL — daftar ajuan satu status. Rombak tampilan F9: tab kit (_nav), kartu kit dengan bilah cari (kotak cari ber-ikon,
 * saringan kelas langsung), bilah aksi ACC massal / unduh surat (tombol kit ber-ikon), tabel kit (layar lebar) + kartu (HP),
 * lencana sisa hari / pemeriksaan / cetak ulang, ui_kosong. Siap CSP: "Pilih semua" = komponen kit "daftarCentang" (dulu onclick
 * querySelectorAll), konfirmasi ACC = data-confirm kit (dulu onclick confirm), tombol unduh & WhatsApp = atribut data-pkl-*
 * (delegasi di pkl-unduh.js; dulu onclick window.dispatchEvent). Isi atas nama di kepala halaman.
 *
 * @var string      $status   menunggu | perbaikan | disetujui | ditolak
 * @var list<array> $rows
 * @var int         $total
 * @var string      $q
 * @var int         $kelasId
 * @var list<array> $kelas
 * @var int         $page
 * @var int         $jmlHal
 * @var array<int, array>        $cek         (menunggu) hasil pemeriksaan per ajuan: bahaya, awas, pertama
 * @var array<int, array|null>   $statusSurat (disetujui) nomor surat & perlu_ulang per ajuan
 * Bersama dari PklDasar::dasar(): $title, $tab, $hitungTab, $p, $batasHari, $bolehAcc, $bolehSurat, $bolehPengaturan, $peranLabel.
 */

use App\Models\PklPengajuanModel;

// Kotak centang tabel: ACC massal hanya untuk pemegang hak ACC ajuan PKL (diatur Admin); surat massal untuk semua staf.
$bisaPilih  = ($status === 'disetujui' && ! empty($bolehSurat)) || ($status === 'menunggu' && ! empty($bolehAcc));
$formPilih  = $status === 'menunggu' ? 'formAcc' : 'formMassal';
$keterangan = [
    'menunggu'  => 'Ajuan baru dari siswa, menunggu keputusan ACC (oleh ' . \App\Libraries\PklHak::teksPemegang('acc') . '). Yang paling lama menunggu ada di atas.',
    'perbaikan' => 'Sudah dikembalikan ke siswa dan menunggu mereka memperbaiki. Bila siswa kesulitan, Anda bisa mengubah langsung.',
    'disetujui' => 'Sudah di-ACC. Siswa-siswa di sini terkunci dan tidak bisa mengajukan lagi.',
    'ditolak'   => 'Ditolak. Siswanya sudah bebas dan boleh mengajukan lagi dari awal.',
];
$adaSaring = $q !== '' || $kelasId > 0;
$urlHal = static function (int $hal) use ($status, $q, $kelasId): string {
    return site_url('admin/pkl/daftar/' . $status) . '?' . http_build_query(array_filter(['q' => $q, 'kelas_id' => $kelasId ?: null, 'page' => $hal > 1 ? $hal : null]));
};
// Sisa hari menuju batas keputusan ACC: terlambat = merah, hari ini / besok = kuning, selain itu teks abu.
$teksSisa = static fn (int $sisa): string => $sisa < 0 ? 'TERLAMBAT ' . abs($sisa) . ' hari' : ($sisa === 0 ? 'batas HARI INI' : 'sisa ' . $sisa . ' hari');
$lencanaSisa = static function (int $sisa) use ($teksSisa): string {
    if ($sisa <= 1) {
        return '<span class="lencana ' . ($sisa < 0 ? 'lencana-merah' : 'lencana-kuning') . '">' . esc($teksSisa($sisa)) . '</span>';
    }

    return '<span class="text-xs text-gray-500 dark:text-gray-400">' . esc($teksSisa($sisa)) . '</span>';
};
// Hasil pemeriksaan otomatis (tab Menunggu ACC): Aman / N peringatan (merah bila ada yang berbahaya).
$lencanaCek = static function (?array $c): string {
    if ($c === null) {
        return '';
    }
    $n = (int) ($c['bahaya'] + $c['awas']);
    if ($n === 0) {
        return '<span class="lencana lencana-hijau">' . ikon('check', 'h-3.5 w-3.5') . 'Aman</span>';
    }

    return '<span class="lencana ' . ($c['bahaya'] ? 'lencana-merah' : 'lencana-kuning') . '" title="' . esc((string) $c['pertama'], 'attr') . '">'
        . ikon('exclamation-circle', 'h-3.5 w-3.5') . $n . ' peringatan</span>';
};
$lencanaUlang = '<span class="lencana lencana-kuning mt-1">' . ikon('exclamation-circle', 'h-3.5 w-3.5') . 'perlu cetak ulang</span>';
$peranAcc = static fn (array $r): string => match ((string) ($r['acc_peran'] ?? '')) { 'impor' => 'Diimpor', '' => '', default => \App\Libraries\HakAkses::label((string) ($r['acc_peran'] ?? '')) };
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/pkl/baru') ?>" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Isi atas nama</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_daftar_v4',
    'helpTitle' => 'Kotak Masuk PKL',
    'helpBody'  => '<p>Pilih tab status di atas. Cari berdasarkan <b>nama perusahaan</b> atau <b>nama siswa</b>, atau saring per kelas. Klik baris untuk membuka detail dan mengambil keputusan.</p>'
        . '<p class="mt-2">Satu baris = satu <b>perusahaan</b> dengan rombongan siswanya (pengaju + teman satu tempat).</p>'
        . '<p class="mt-2">Di tab <b>Menunggu ACC</b> kolom <b>Pemeriksaan</b> menandai ajuan <b>Aman</b> (hijau) atau <b>ada peringatan</b> (kuning/merah). Tombol <b>ACC massal</b> hanya menyetujui yang Aman; sisanya dilewati supaya diperiksa satu per satu. Di tab <b>Disetujui</b> yang berhak (diatur Admin) bisa mengunduh surat PKL banyak sekaligus: <b>Unduh surat terpilih</b> (yang dicentang), <b>Yang belum dicetak / perlu cetak ulang</b>, atau <b>Unduh SEMUA surat</b>. Sebelum berkas dibuat muncul kotak <b>Catat biaya</b> (Biaya PKL, SPP, Tabungan, OSIS, beasiswa) &mdash; wajib minimal satu centang per siswa. Setelah unduhan selesai muncul tombol <b>Kabari via WhatsApp</b> untuk memberi tahu siswa. Di HP, centang ada di kiri tiap kartu.</p>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-daftar-pkl">
    <div class="kartu-kepala">
        <div class="min-w-0">
            <h2 id="judul-daftar-pkl" class="kartu-judul flex items-center gap-2"><?= esc($title) ?><span class="lencana lencana-abu"><?= (int) $total ?></span></h2>
            <p class="kartu-ket max-w-3xl"><?= esc($keterangan[$status] ?? '') ?></p>
        </div>
    </div>

    <div class="alat-crud">
        <form method="get" class="alat-crud-cari" role="search">
            <label class="alat-crud-kotak">
                <span class="sr-only">Cari</span>
                <?= ikon('search', '') ?>
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari perusahaan atau nama siswa…" class="inp">
            </label>
            <select name="kelas_id" data-autosubmit class="inp alat-crud-pilih" aria-label="Kelas">
                <option value="">Semua kelas</option>
                <?php foreach ($kelas as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"<?= (int) $k['id'] === $kelasId ? ' selected' : '' ?>><?= esc($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            <?php if ($adaSaring): ?>
                <a href="<?= site_url('admin/pkl/daftar/' . $status) ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($status === 'menunggu' && $rows !== [] && empty($bolehAcc)): ?>
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 sm:px-6 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300">
            <p class="flex items-start gap-2"><?= ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') ?><span><b>ACC dilakukan peran yang diberi hak ACC ajuan PKL</b> (<?= esc(\App\Libraries\PklHak::teksPemegang('acc')) ?>). Sebagai <?= esc($peranLabel ?? 'staf') ?> Anda bisa memeriksa ajuan, mengembalikannya untuk diperbaiki, dan mengubah datanya.</span></p>
        </div>
    <?php endif; ?>
    <?php if ($status === 'menunggu' && $rows !== [] && ! empty($bolehAcc)): ?>
        <form id="formAcc" method="post" action="<?= site_url('admin/pkl/acc-massal') ?>" class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:px-6 dark:border-gray-700 dark:bg-gray-700/40">
            <?= csrf_field() ?>
            <p class="text-sm text-gray-600 sm:me-auto dark:text-gray-300"><b class="text-green-700 dark:text-green-400">ACC massal:</b> hanya ajuan berlabel <b class="text-green-700 dark:text-green-400">Aman</b> (tanpa peringatan) yang di-ACC. Yang berperingatan dilewati agar Anda periksa satu per satu.</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="mode" value="terpilih" class="btn btn-netral btn-sm"
                        data-confirm="ACC semua ajuan yang dicentang? Yang punya peringatan akan dilewati." data-confirm-judul="ACC ajuan terpilih?" data-confirm-ya="Ya, ACC"><?= ikon('check', 'h-4 w-4') ?>ACC terpilih</button>
                <button type="submit" name="mode" value="aman" class="btn btn-sukses btn-sm"
                        data-confirm="ACC SEMUA ajuan yang menunggu dan berlabel Aman (maks. 200)? Yang punya peringatan akan dilewati." data-confirm-judul="ACC semua yang aman?" data-confirm-ya="Ya, ACC semua"><?= ikon('check-circle', 'h-4 w-4') ?>ACC semua yang aman</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($status === 'disetujui' && $rows !== []): ?>
        <div class="space-y-3 border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 dark:border-gray-700 dark:bg-gray-700/40">
            <?php if (trim((string) ($p['waka_hubin_nama'] ?? '')) === ''): ?>
                <?= ui_peringatan('galat', '', ['html' => 'Nama Waka Hubin belum diisi, jadi di bawah tanda tangan surat hanya titik-titik. '
                    . ($bolehPengaturan ? '<a href="' . site_url('admin/pkl/pengaturan#surat') . '" class="font-semibold underline">Isi di Pengaturan PKL</a>, lalu unduh ulang.' : 'Minta yang berhak mengisinya di Pengaturan PKL.')]) ?>
            <?php endif; ?>
            <?php if (! empty($bolehSurat)): ?>
                <form id="formMassal"></form>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <p class="text-sm text-gray-600 lg:me-auto dark:text-gray-300">Surat PKL: centang ajuan lalu unduh. Bila ada siswa yang belum tercatat biayanya, kotak <b>Catat biaya</b> muncul dulu (bulan terpilih otomatis); bila semua sudah tercatat, surat langsung terunduh. Yang belum bernomor otomatis diberi nomor sesuai urutan persetujuan.</p>
                    <div class="flex flex-wrap gap-2 lg:shrink-0 lg:justify-end">
                        <button type="button" class="btn btn-netral btn-sm" data-pkl-unduh="terpilih"><?= ikon('download', 'h-4 w-4') ?>Unduh surat terpilih</button>
                        <button type="button" class="btn btn-netral btn-sm" data-pkl-unduh="belum"><?= ikon('download', 'h-4 w-4') ?>Yang belum dicetak / perlu cetak ulang</button>
                        <button type="button" class="btn btn-utama btn-sm" data-pkl-unduh="semua"><?= ikon('download', 'h-4 w-4') ?>Unduh SEMUA surat</button>
                        <button type="button" class="btn btn-sukses btn-sm" data-pkl-wa-terpilih><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kabari via WhatsApp (terpilih)</button>
                    </div>
                </div>
            <?php else: ?>
                <p class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300"><?= ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') ?><span><b>Surat diunduh oleh peran yang diberi hak itu oleh Admin</b> (<?= esc(\App\Libraries\PklHak::teksPemegang('surat')) ?>), supaya jelas siapa yang menyetujui dan siapa yang mencetak. Anda tetap bisa melihat nomor dan status tiap surat di sini.</span></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <?= ui_kosong(['ikon' => 'inbox', 'judul' => $adaSaring ? 'Tidak ada ajuan yang cocok dengan pencarian' : 'Belum ada ajuan di sini',
            'teks' => $adaSaring ? 'Ubah pencarian/saringan, atau tekan Reset.' : '']) ?>
    <?php else: ?>
        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block"<?= $bisaPilih ? ' x-data="daftarCentang"' : '' ?>>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <?php if ($bisaPilih): ?><th scope="col" class="w-10"><input type="checkbox" class="cek" aria-label="Pilih semua di halaman ini" @change="pilihSemuaCentang($event)"></th><?php endif; ?>
                            <th scope="col">Bukti</th>
                            <th scope="col">Perusahaan</th>
                            <th scope="col">Pengaju</th>
                            <th scope="col">Dikirim siswa</th>
                            <th scope="col">Diperbarui</th>
                            <?php if ($status === 'disetujui'): ?><th scope="col">Disetujui oleh</th><th scope="col">Surat</th><?php endif; ?>
                            <?php if ($status === 'menunggu'): ?><th scope="col">Pemeriksaan</th><?php endif; ?>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $id      = (int) $r['id'];
                            $kirimAt = (string) ($r['diajukan_at'] ?? $r['created_at']);
                            $sisa    = $status === 'menunggu' ? PklPengajuanModel::sisaHari($kirimAt, (int) ($batasHari ?? 5)) : null;
                            $ss      = $status === 'disetujui' ? ($statusSurat[$id] ?? null) : null;
                            $catatan = ! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true); ?>
                            <tr<?= $catatan ? ' class="border-b-0"' : '' ?>>
                                <?php if ($bisaPilih): ?><td><input type="checkbox" class="cek" name="ids[]" value="<?= $id ?>" form="<?= $formPilih ?>" data-centang aria-label="Pilih <?= esc($r['perusahaan_nama'], 'attr') ?>"></td><?php endif; ?>
                                <td class="font-mono text-xs font-semibold whitespace-nowrap text-gray-500 dark:text-gray-400"><?= esc(PklPengajuanModel::kode($id)) ?></td>
                                <td>
                                    <a href="<?= site_url('admin/pkl/' . $id) ?>" class="font-semibold text-gray-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-400"><?= esc($r['perusahaan_nama']) ?></a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['perusahaan_kota'] ?? '') ?><?= $r['sumber'] !== 'siswa' ? ' · ' . ($r['sumber'] === 'staf' ? 'diisi staf' : 'impor') : '' ?><?= (int) $r['kirim_ke'] > 1 ? ' · revisi ke-' . ((int) $r['kirim_ke'] - 1) : '' ?></p>
                                    <?php if (($r['tolak_sumber'] ?? null) === 'perusahaan'): ?><span class="lencana lencana-merah mt-1">Ditolak perusahaan</span><?php elseif (($r['tolak_sumber'] ?? null) === 'gabung'): ?><span class="lencana lencana-biru mt-1">Digabung ke ajuan lain</span><?php endif; ?>
                                </td>
                                <td>
                                    <p class="font-medium text-gray-900 dark:text-white"><?= esc($r['pengaju'] ?? '—') ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['pengaju_kelas'] ?? '') ?><?= (int) $r['jumlah'] > 1 ? ' · +' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?><?= (int) ($r['jumlah_keluar'] ?? 0) > 0 ? ' · ' . (int) $r['jumlah_keluar'] . ' dikeluarkan' : '' ?></p>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="text-xs tabular-nums"><?= esc(date('d-m-Y H:i', strtotime($kirimAt))) ?></span>
                                    <?php if ($sisa !== null): ?><div class="mt-1"><?= $lencanaSisa($sisa) ?></div><?php endif; ?>
                                </td>
                                <td class="text-xs whitespace-nowrap tabular-nums text-gray-500 dark:text-gray-400"><?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></td>
                                <?php if ($status === 'disetujui'): ?>
                                    <td class="text-xs">
                                        <?php if (! empty($r['acc_nama'])): ?>
                                            <span class="font-semibold text-gray-900 dark:text-white"><?= esc($r['acc_nama']) ?></span>
                                            <span class="block text-gray-500 dark:text-gray-400"><?= esc($peranAcc($r)) ?><?= ! empty($r['acc_at']) ? ' · ' . esc(date('d-m-Y H:i', strtotime((string) $r['acc_at']))) : '' ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-500 dark:text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs">
                                        <?php if ($ss): ?>
                                            <span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?= esc($ss['nomor']) ?></span>
                                            <?php if ($ss['perlu_ulang']): ?><div><?= $lencanaUlang ?></div><?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-500 dark:text-gray-400">belum ada</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($status === 'menunggu'): ?><td><?= $lencanaCek($cek[$id] ?? null) ?></td><?php endif; ?>
                                <td class="text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <?php if ($status === 'disetujui' && ! empty($bolehSurat) && ! empty($statusSurat[$id])): ?>
                                            <button type="button" class="btn btn-sukses btn-sm" data-pkl-wa-ajuan="<?= $id ?>" title="Kabari siswa lewat WhatsApp"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>WA</button>
                                        <?php endif; ?>
                                        <a href="<?= site_url('admin/pkl/' . $id) ?>" class="btn btn-utama btn-sm"><?= $status === 'menunggu' ? 'Periksa' : 'Buka' ?></a>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($catatan): ?>
                                <tr><td></td><td colspan="5" class="pt-0 text-xs text-gray-500 dark:text-gray-400"><span class="font-semibold text-gray-700 dark:text-gray-300">Catatan sekolah:</span> <?= esc($r['catatan_staf']) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu (HP): bisa dicentang agar ACC terpilih / unduh surat terpilih juga jalan di HP -->
        <div class="md:hidden"<?= $bisaPilih ? ' x-data="daftarCentang"' : '' ?>>
            <?php if ($bisaPilih): ?>
                <label class="flex cursor-pointer items-center gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300"><input type="checkbox" class="cek" @change="pilihSemuaCentang($event)"> Pilih semua di halaman ini</label>
            <?php endif; ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($rows as $r):
                    $id     = (int) $r['id'];
                    $kirimHp = (string) ($r['diajukan_at'] ?? $r['created_at']);
                    $sisaHp = $status === 'menunggu' ? PklPengajuanModel::sisaHari($kirimHp, (int) ($batasHari ?? 5)) : null;
                    $ssHp   = $status === 'disetujui' ? ($statusSurat[$id] ?? null) : null; ?>
                    <li class="flex items-stretch">
                        <?php if ($bisaPilih): ?><label class="flex shrink-0 items-center ps-4"><input type="checkbox" class="cek" name="ids[]" value="<?= $id ?>" form="<?= $formPilih ?>" data-centang aria-label="Pilih <?= esc($r['perusahaan_nama'], 'attr') ?>"></label><?php endif; ?>
                        <a href="<?= site_url('admin/pkl/' . $id) ?>" class="block min-w-0 flex-1 px-4 py-3.5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['perusahaan_nama']) ?></p>
                                <span class="shrink-0 font-mono text-xs font-semibold text-gray-500 dark:text-gray-400"><?= esc(PklPengajuanModel::kode($id)) ?></span>
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"><?= esc($r['pengaju'] ?? '—') ?> (<?= esc($r['pengaju_kelas'] ?? '') ?>)<?= (int) $r['jumlah'] > 1 ? ' + ' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?><?= (int) ($r['jumlah_keluar'] ?? 0) > 0 ? ' · ' . (int) $r['jumlah_keluar'] . ' dikeluarkan' : '' ?></p>
                            <?php if (($r['tolak_sumber'] ?? null) === 'perusahaan'): ?><p class="mt-1"><span class="lencana lencana-merah">Ditolak perusahaan</span></p><?php elseif (($r['tolak_sumber'] ?? null) === 'gabung'): ?><p class="mt-1"><span class="lencana lencana-biru">Digabung ke ajuan lain</span></p><?php endif; ?>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">Dikirim <?= esc(date('d-m-Y H:i', strtotime($kirimHp))) ?><?= $sisaHp !== null ? $lencanaSisa($sisaHp) : '' ?></p>
                            <?php if ($status === 'disetujui' && ! empty($r['acc_nama'])): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Disetujui: <b class="text-gray-700 dark:text-gray-300"><?= esc($r['acc_nama']) ?></b><?= (string) ($r['acc_peran'] ?? '') !== '' && ($r['acc_peran'] ?? '') !== 'impor' ? ' (' . esc(\App\Libraries\HakAkses::label((string) ($r['acc_peran'] ?? ''))) . ')' : '' ?></p><?php endif; ?>
                            <?php if ($status === 'menunggu' && isset($cek[$id])): ?><div class="mt-1.5"><?= $lencanaCek($cek[$id]) ?></div><?php endif; ?>
                            <?php if ($status === 'disetujui'): ?>
                                <div class="mt-1 text-xs"><?= $ssHp ? '<span class="font-mono font-semibold text-gray-700 dark:text-gray-300">' . esc($ssHp['nomor']) . '</span>' . ($ssHp['perlu_ulang'] ? ' ' . $lencanaUlang : '') : '<span class="text-gray-500 dark:text-gray-400">surat belum ada</span>' ?></div>
                            <?php endif; ?>
                            <?php if (! empty($r['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($jmlHal > 1): ?>
            <div class="kartu-kaki flex items-center justify-between gap-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('chevron-left', 'h-4 w-4') ?>Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-gray-500 dark:text-gray-400">Halaman <b class="text-gray-900 dark:text-white"><?= $page ?></b> dari <b class="text-gray-900 dark:text-white"><?= $jmlHal ?></b></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="btn btn-netral btn-sm">Berikutnya<?= ikon('chevron-right', 'h-4 w-4') ?></a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($status === 'disetujui' && ! empty($bolehSurat)): // kotak dialog catat biaya + kabar WhatsApp ?>
    <?= view('admin/pkl/_unduh') ?>
<?php endif; ?>

<?= $this->endSection() ?>
