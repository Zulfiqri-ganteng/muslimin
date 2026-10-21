<?php
/**
 * Daftar Surat Sekolah — buku agenda surat keluar (semua jenis) dengan tab status dan saringan.
 * Rombak tampilan F9: tab kit (_nav), Excel buku agenda di kepala halaman, kartu kit dengan bilah cari (saringan jenis/tahun
 * langsung), bilah ACC massal (data-confirm kit; dulu onclick confirm) & unduh massal (tombol kit ber-ikon; Kabari WA =
 * data-kabari-surat-jenis, dulu @click dispatchEvent), "Pilih semua" = kit daftarCentang (dulu onclick querySelectorAll),
 * tabel kit + kartu HP, lencana kit (jenis & status), ui_kosong, paginasi tombol kit.
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
$bolehDipilih = static fn (array $r): bool => ! $bisaPilihAcc || in_array((string) $r['jenis'], $jenisAcc ?? [], true);
$lencanaUlang = '<span class="lencana lencana-kuning mt-1">' . ikon('exclamation-circle', 'h-3.5 w-3.5') . 'perlu cetak ulang</span>';
$bilah = 'flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:px-6 dark:border-gray-700 dark:bg-gray-700/40';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<?php // Buku agenda surat keluar: semua surat sesuai saringan yang sedang tampil (bukan hanya halaman ini). ?>
<a href="<?= site_url('admin/surat/excel') . '?' . http_build_query(array_filter($f)) ?>" title="Unduh daftar ini (semua halaman) sebagai Excel buku agenda surat keluar" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Excel buku agenda</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_daftar_v3',
    'helpTitle' => 'Daftar Surat Sekolah',
    'helpBody'  => '<p>Di sini tercatat <b>semua surat sekolah</b> (Izin Ujian ASTS/ASAS/ASAT, Izin TKA, Pernyataan Orang Tua PKL, Surat Balasan PKL, Penarikan Izin PKL) &mdash; siapa yang membuat, statusnya, dan nomornya. Pilih tab status di atas, atau saring menurut jenis, tahun, dan kata kunci (nomor, judul, atau nama perusahaan).</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Menunggu ACC</b>: surat sudah disiapkan dan menunggu persetujuan. <b>Siap unduh</b>: boleh diunduh. <b>Dikembalikan</b>: perlu diperbaiki dulu.</li>'
        . '<li>Nomor surat <b>diterbitkan sekali</b>, saat surat pertama diunduh, dan satu urutan dengan Surat Izin PKL, jadi tidak ada dua surat bernomor sama. Unduh ulang memakai nomor yang sama.</li>'
        . '<li>Di tab <b>Menunggu ACC</b>, yang berhak ACC jenis surat itu (diatur Admin di Sistem → Hak Akses) bisa mencentang lalu <b>ACC terpilih</b>, atau <b>ACC semua yang aman</b>. Surat yang punya peringatan (mis. nama penanda tangan belum diisi) dilewati supaya diperiksa satu per satu di halaman detailnya.</li>'
        . '<li>Di tab <b>Siap unduh</b>, saring dulu ke <b>satu jenis surat</b>; yang berhak mengunduh jenis surat itu (diatur Admin di Sistem → Hak Akses) bisa mengunduh terpilih, yang belum diunduh / perlu cetak ulang, atau semuanya sekaligus (lebih dari 60 surat dikirim sebagai ZIP).</li>'
        . '<li><b>Pernyataan Orang Tua PKL</b> tanpa nomor dan tanpa ACC: langsung siap diunduh, satu halaman per siswa. <b>Surat Balasan</b> dan <b>Penarikan</b> juga bisa dibuat dari halaman detail ajuan PKL (kartu "Surat sekolah").</li>'
        . '<li>Tombol <b>Excel buku agenda</b> (kanan atas) mengunduh <b>buku agenda surat keluar</b>: semua surat sesuai tab &amp; saringan yang sedang tampil (semua halaman), lengkap dengan nomor, tanggal, tujuan, status, dan siapa yang meng-ACC.</li>'
        . '</ul>',
]) ?>

<?= view('admin/surat/_nav', ['hitung' => $hitung, 'f' => $f]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-daftar-surat">
    <div class="kartu-kepala">
        <div class="min-w-0">
            <h2 id="judul-daftar-surat" class="kartu-judul flex items-center gap-2"><?= esc($title) ?><span class="lencana lencana-abu"><?= (int) $total ?></span></h2>
            <p class="kartu-ket max-w-3xl"><?= esc($keterangan[$f['status']] ?? '') ?></p>
        </div>
    </div>

    <div class="alat-crud">
        <form method="get" class="alat-crud-cari" role="search">
            <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= esc($f['status'], 'attr') ?>"><?php endif; ?>
            <label class="alat-crud-kotak">
                <span class="sr-only">Cari</span>
                <?= ikon('search', '') ?>
                <input type="search" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="Cari nomor, judul, atau perusahaan…" class="inp">
            </label>
            <select name="jenis" data-autosubmit class="inp alat-crud-pilih" aria-label="Jenis surat">
                <option value="">Semua jenis</option>
                <?php foreach (SuratJenis::kode() as $k): ?>
                    <option value="<?= esc($k, 'attr') ?>"<?= $f['jenis'] === $k ? ' selected' : '' ?>><?= esc(SuratJenis::label($k)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" data-autosubmit class="inp alat-crud-pilih" aria-label="Tahun">
                <option value="">Semua tahun</option>
                <?php foreach ($tahun as $t): ?>
                    <option value="<?= (int) $t ?>"<?= $f['tahun'] === (int) $t ? ' selected' : '' ?>><?= (int) $t ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            <?php if ($f['jenis'] !== '' || $f['tahun'] > 0 || $f['q'] !== ''): ?>
                <a href="<?= site_url('admin/surat') . ($f['status'] !== '' ? '?status=' . urlencode($f['status']) : '') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($f['status'] === 'menunggu' && $rows !== [] && empty($bolehAcc)): ?>
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 sm:px-6 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300">
            <p class="flex items-start gap-2"><?= ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') ?><span><b>ACC dilakukan peran yang diberi hak ACC jenis surat itu</b> oleh Admin (Sistem → Hak Akses). Sebagai <?= esc($peranLabel ?? 'staf') ?> Anda bisa melihat surat yang menunggu dan membukanya.</span></p>
        </div>
    <?php endif; ?>
    <?php if ($bisaPilihAcc && $rows !== []): ?>
        <form id="formAcc" method="post" action="<?= site_url('admin/surat/acc-massal') ?>" class="<?= $bilah ?>">
            <?= csrf_field() ?>
            <p class="text-sm text-gray-600 sm:me-auto dark:text-gray-300"><b class="text-green-700 dark:text-green-400">ACC massal:</b> hanya surat tanpa peringatan<?= count($jenisAcc ?? []) < count(SuratJenis::kode()) ? ' dan berjenis yang boleh Anda ACC (' . esc(implode(', ', array_map(static fn (string $j): string => SuratJenis::singkat($j), $jenisAcc ?? []))) . ')' : '' ?> yang di-ACC, tercatat atas nama Anda. Yang punya peringatan (mis. nama penanda tangan belum diisi, atau tanpa siswa) dilewati agar Anda periksa satu per satu.</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="mode" value="terpilih" class="btn btn-netral btn-sm"
                        data-confirm="ACC semua surat yang dicentang? Yang punya peringatan akan dilewati." data-confirm-judul="ACC surat terpilih?" data-confirm-ya="Ya, ACC"><?= ikon('check', 'h-4 w-4') ?>ACC terpilih</button>
                <button type="submit" name="mode" value="aman" class="btn btn-sukses btn-sm"
                        data-confirm="ACC SEMUA surat yang menunggu dan tanpa peringatan (maks. 200)? Yang punya peringatan akan dilewati." data-confirm-judul="ACC semua yang aman?" data-confirm-ya="Ya, ACC semua"><?= ikon('check-circle', 'h-4 w-4') ?>ACC semua yang aman</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($bisaPilihUnduh && $rows !== []): ?>
        <form id="formUnduh" method="post" action="<?= site_url('admin/surat/unduh-massal') ?>" data-unduh<?= ! empty($kabarWaJenis) ? ' data-surat-wa-jenis="' . esc($f['jenis'], 'attr') . '"' : '' ?> class="<?= $bilah ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="jenis" value="<?= esc($f['jenis'], 'attr') ?>">
            <p class="text-sm text-gray-600 sm:me-auto dark:text-gray-300">Unduh <?= esc(SuratJenis::label($f['jenis'])) ?>: centang suratnya lalu unduh. Yang belum bernomor otomatis diberi nomor menurut urutan persetujuan. Lebih dari 60 surat dikirim sebagai ZIP.</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="mode" value="terpilih" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh terpilih</button>
                <button type="submit" name="mode" value="belum" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Yang belum diunduh / perlu cetak ulang</button>
                <button type="submit" name="mode" value="semua" class="btn btn-utama btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh SEMUA</button>
                <?php if (! empty($kabarWaJenis)): ?>
                    <button type="button" class="btn btn-sukses btn-sm" data-kabari-surat-jenis="<?= esc($f['jenis'], 'attr') ?>" title="Siswa pada surat yang sudah diunduh dan belum dikabari"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kabari siswa via WhatsApp</button>
                <?php endif; ?>
            </div>
        </form>
    <?php elseif ($f['status'] === 'disetujui' && $rows !== [] && ! empty($bolehUnduh) && $f['jenis'] === ''): ?>
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 sm:px-6 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300">Untuk <b>mengunduh banyak surat sekaligus</b>, saring dulu menurut satu jenis surat (pilihan "Semua jenis" di atas). Unduhan satuan ada di halaman tiap surat.</div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <?php if (! $adaSaringan): ?>
            <!-- Belum ada surat sama sekali: kenalkan jenis-jenisnya -->
            <div class="kartu-isi">
                <?= ui_kosong(['ikon' => 'envelope', 'judul' => 'Belum ada surat yang dibuat', 'teks' => 'Surat dibuat dari menu jenis suratnya masing-masing. Setelah dibuat (dan di-ACC bila perlu), surat muncul di sini lengkap dengan nomor dan riwayatnya.']) ?>
                <ul class="mx-auto grid max-w-4xl gap-3 sm:grid-cols-2">
                    <?php foreach (SuratJenis::kode() as $k):
                        $siap  = SuratJenis::siap($k);
                        $boleh = $siap && HakAkses::boleh($peran, SuratJenis::alamat($k));
                    ?>
                        <li class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-3">
                                <span class="lencana <?= esc(SuratJenis::badge($k), 'attr') ?>"><?= esc(SuratJenis::label($k)) ?></span>
                                <?php if ($boleh): ?>
                                    <a href="<?= site_url(SuratJenis::alamat($k)) ?>" class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">Buka<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                                <?php elseif (! $siap): ?>
                                    <span class="lencana lencana-kuning shrink-0">Segera</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= esc(SuratJenis::DATA[$k]['ringkas']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <?= ui_kosong(['ikon' => 'file-search', 'judul' => 'Tidak ada surat yang cocok dengan saringan ini', 'teks' => 'Ubah pencarian/saringan, atau tekan Reset.']) ?>
        <?php endif; ?>
    <?php else: ?>
        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block"<?= $bisaPilih ? ' x-data="daftarCentang"' : '' ?>>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <?php if ($bisaPilih): ?><th scope="col" class="w-10"><input type="checkbox" class="cek" aria-label="Pilih semua di halaman ini" @change="pilihSemuaCentang($event)"></th><?php endif; ?>
                            <th scope="col">Nomor</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Perihal / tujuan</th>
                            <th scope="col">Tanggal surat</th>
                            <th scope="col">Status</th>
                            <th scope="col">Dibuat oleh</th>
                            <th scope="col" class="text-center">Diunduh</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            [$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$r['status']] ?? [$r['status'], 'lencana-abu'];
                            $lihat = site_url('admin/surat/' . (int) $r['id']);
                        ?>
                            <tr>
                                <?php if ($bisaPilih): ?><td><?php if ($bolehDipilih($r)): ?><input type="checkbox" class="cek" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" data-centang aria-label="Pilih <?= esc($r['judul'], 'attr') ?>"><?php endif; ?></td><?php endif; ?>
                                <td class="font-mono text-xs font-semibold whitespace-nowrap text-gray-700 dark:text-gray-300"><?= (string) $r['nomor'] !== '' ? esc($r['nomor']) : '<span class="font-sans font-normal text-gray-500 dark:text-gray-400">belum bernomor</span>' ?></td>
                                <td><span class="lencana <?= esc(SuratJenis::badge((string) $r['jenis']), 'attr') ?>"><?= esc(SuratJenis::singkat((string) $r['jenis'])) ?></span></td>
                                <td>
                                    <a href="<?= $lihat ?>" class="font-semibold text-gray-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-400"><?= esc($r['judul']) ?></a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc(trim(((string) $r['perusahaan_nama'] !== '' ? $r['perusahaan_nama'] : '') . ((int) $r['jml_siswa'] > 0 ? ((string) $r['perusahaan_nama'] !== '' ? ' · ' : '') . (int) $r['jml_siswa'] . ' siswa' : ''))) ?></p>
                                </td>
                                <td class="text-xs whitespace-nowrap"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?></td>
                                <td>
                                    <span class="lencana <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
                                    <?php if (! empty($perluUlang[(int) $r['id']])): ?><div><?= $lencanaUlang ?></div><?php endif; ?>
                                    <?php if ($r['status'] === 'disetujui' && (string) $r['acc_nama'] !== ''): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">ACC: <?= esc($r['acc_nama']) ?><?= (string) $r['acc_peran'] !== '' ? ' (' . esc(HakAkses::label((string) $r['acc_peran'])) . ')' : '' ?></p><?php endif; ?>
                                    <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 max-w-[14rem] text-xs text-gray-500 dark:text-gray-400"><?= esc($r['catatan_staf']) ?></p><?php endif; ?>
                                </td>
                                <td class="text-xs"><?= esc((string) $r['dibuat_nama'] !== '' ? $r['dibuat_nama'] : '—') ?><span class="block text-gray-500 dark:text-gray-400"><?= esc(date('d-m-Y H:i', strtotime((string) $r['created_at']))) ?></span></td>
                                <td class="text-center text-xs"><?= (int) $r['cetak_ke'] > 0 ? (int) $r['cetak_ke'] . '×' : '<span class="text-gray-500 dark:text-gray-400">—</span>' ?></td>
                                <td class="text-right whitespace-nowrap"><a href="<?= $lihat ?>" class="btn btn-utama btn-sm">Buka</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu (HP): bisa dicentang agar ACC terpilih / unduh terpilih juga jalan di HP -->
        <div class="md:hidden"<?= $bisaPilih ? ' x-data="daftarCentang"' : '' ?>>
            <?php if ($bisaPilih): ?>
                <label class="flex cursor-pointer items-center gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300"><input type="checkbox" class="cek" @change="pilihSemuaCentang($event)"> Pilih semua di halaman ini</label>
            <?php endif; ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($rows as $r):
                    [$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$r['status']] ?? [$r['status'], 'lencana-abu'];
                ?>
                    <li class="flex items-stretch">
                        <?php if ($bisaPilih && $bolehDipilih($r)): ?><label class="flex shrink-0 items-center ps-4"><input type="checkbox" class="cek" name="ids[]" value="<?= (int) $r['id'] ?>" form="<?= $formPilih ?>" data-centang aria-label="Pilih <?= esc($r['judul'], 'attr') ?>"></label><?php endif; ?>
                        <a href="<?= site_url('admin/surat/' . (int) $r['id']) ?>" class="block min-w-0 flex-1 px-4 py-3.5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['judul']) ?></p>
                                <span class="lencana <?= esc($stKelas, 'attr') ?> shrink-0"><?= esc($stLabel) ?></span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="lencana <?= esc(SuratJenis::badge((string) $r['jenis']), 'attr') ?>"><?= esc(SuratJenis::singkat((string) $r['jenis'])) ?></span>
                                <span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?= (string) $r['nomor'] !== '' ? esc($r['nomor']) : 'belum bernomor' ?></span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?><?= (int) $r['jml_siswa'] > 0 ? ' · ' . (int) $r['jml_siswa'] . ' siswa' : '' ?> · oleh <?= esc((string) $r['dibuat_nama'] !== '' ? $r['dibuat_nama'] : '—') ?></p>
                            <?php if (! empty($perluUlang[(int) $r['id']])): ?><div><?= $lencanaUlang ?></div><?php endif; ?>
                            <?php if ($r['status'] === 'disetujui' && (string) $r['acc_nama'] !== ''): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">ACC: <b class="text-gray-700 dark:text-gray-300"><?= esc($r['acc_nama']) ?></b><?= (string) $r['acc_peran'] !== '' ? ' (' . esc(HakAkses::label((string) $r['acc_peran'])) . ')' : '' ?></p><?php endif; ?>
                            <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($jmlHal > 1): ?>
            <div class="kartu-kaki flex items-center justify-between gap-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('chevron-left', 'h-4 w-4') ?>Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-gray-500 dark:text-gray-400">Halaman <b class="text-gray-900 dark:text-white"><?= (int) $page ?></b> dari <b class="text-gray-900 dark:text-white"><?= (int) $jmlHal ?></b></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="btn btn-netral btn-sm">Berikutnya<?= ikon('chevron-right', 'h-4 w-4') ?></a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (! empty($kabarWaJenis)): ?><?= view('admin/surat/_wa') ?><?php endif; ?>

<?= $this->endSection() ?>
