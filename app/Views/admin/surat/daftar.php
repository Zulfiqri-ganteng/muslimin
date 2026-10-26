<?php
/**
 * Daftar Surat Sekolah — buku agenda surat keluar (semua jenis) dengan tab status dan saringan.
 * Rombak tampilan F9: tab kit (_nav), Excel buku agenda di kepala halaman, kartu kit dengan bilah cari (saringan jenis/tahun
 * langsung), bilah ACC massal (data-confirm kit; dulu onclick confirm) & unduh massal (tombol kit ber-ikon; Kabari WA =
 * data-kabari-surat-jenis, dulu @click dispatchEvent), "Pilih semua" = kit daftarCentang (dulu onclick querySelectorAll),
 * tabel kit + kartu HP, lencana kit (jenis & status), ui_kosong, paginasi tombol kit.
 * Rapi tampilan U3 (2026-10-26, docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md): "Permintaan dari siswa" jadi satu baris ringkas (hanya jenis yang
 * ada angkanya); tombol massal bergantung centang (ACC terpilih, Unduh terpilih) di BILAH yang muncul setelah dicentang; ACC semua yang
 * aman / Unduh SEMUA = satu tombol di kepala kartu + menu "Lainnya"; per baris: nomor, jenis, perihal, tanggal, status, Buka (pembuat, ACC,
 * jumlah unduhan → halaman detail). Formulir (id, action, nama isian, tombol name=value) TETAP sama.
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
// Bilah aksi untuk yang dicentang (tampil hanya bila ada centang; di tabel & kartu HP masing-masing).
$bilahPilih = static function () use ($bisaPilihAcc): string {
    $isi = $bisaPilihAcc
        ? '<button type="submit" form="formAcc" name="mode" value="terpilih" class="btn btn-sukses btn-sm" data-confirm="ACC semua surat yang dicentang? Yang punya peringatan akan dilewati." data-confirm-judul="ACC surat terpilih?" data-confirm-ya="Ya, ACC">'
            . ikon('check', 'h-4 w-4') . 'ACC terpilih</button>'
        : '<button type="submit" form="formUnduh" name="mode" value="terpilih" class="btn btn-utama btn-sm">' . ikon('download', 'h-4 w-4') . 'Unduh terpilih</button>';

    return '<div class="px-4 pb-3 sm:px-6">' . ui_bilah_pilih_buka(['label' => 'Aksi untuk surat yang dicentang']) . $isi . ui_bilah_pilih_tutup() . '</div>';
};
// "Permintaan dari siswa": hanya jenis yang punya angka (perlu ACC / siap dicetak).
$permintaanAda = array_filter($permintaanSiswa ?? [], static fn (array $n): bool => ($n['perlu_acc'] && $n['menunggu'] > 0) || $n['siap'] > 0);
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<?php // Buku agenda surat keluar: semua surat sesuai saringan yang sedang tampil (bukan hanya halaman ini). ?>
<a href="<?= site_url('admin/surat/excel') . '?' . http_build_query(array_filter($f)) ?>" title="Unduh daftar ini (semua halaman) sebagai Excel buku agenda surat keluar" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Excel buku agenda</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_daftar_v4',
    'helpTitle' => 'Daftar Surat Sekolah',
    'helpBody'  => '<p>Di sini tercatat <b>semua surat sekolah</b> (Izin Ujian ASTS/ASAS/ASAT, Izin TKA, Pernyataan Orang Tua PKL, Surat Balasan, Penarikan, dan Perpanjangan PKL) beserta status dan nomornya. Pilih tab status di atas, atau saring menurut jenis, tahun, dan kata kunci (nomor, judul, atau nama perusahaan). Pembuat, yang meng-ACC, dan jumlah unduhan ada di halaman tiap surat (tombol <b>Buka</b>).</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Menunggu ACC</b>: surat sudah disiapkan dan menunggu persetujuan. <b>Siap unduh</b>: boleh diunduh. <b>Dikembalikan</b>: perlu diperbaiki dulu.</li>'
        . '<li>Nomor surat <b>diterbitkan sekali</b>, saat surat pertama diunduh, dan satu urutan dengan Surat Izin PKL, jadi tidak ada dua surat bernomor sama. Unduh ulang memakai nomor yang sama.</li>'
        . '<li>Di tab <b>Menunggu ACC</b>, yang berhak ACC jenis surat itu (diatur Admin di Sistem → Hak Akses) memakai tombol <b>ACC semua yang aman</b> di atas daftar, atau mencentang surat lalu <b>ACC terpilih</b> (bilah tombolnya muncul setelah ada yang dicentang). Surat yang punya peringatan (mis. nama penanda tangan belum diisi) dilewati supaya diperiksa satu per satu di halaman detailnya.</li>'
        . '<li>Di tab <b>Siap unduh</b>, saring dulu ke <b>satu jenis surat</b>; yang berhak mengunduh jenis surat itu memakai <b>Unduh SEMUA</b> (lebih dari 60 surat dikirim sebagai ZIP), mencentang lalu <b>Unduh terpilih</b>, atau menu <b>Lainnya</b> untuk yang belum diunduh / perlu cetak ulang dan untuk mengabari siswa via WhatsApp.</li>'
        . '<li><b>Pernyataan Orang Tua PKL</b> tanpa nomor dan tanpa ACC: langsung siap diunduh, satu halaman per siswa. <b>Surat Balasan</b> dan <b>Penarikan</b> juga bisa dibuat dari halaman detail ajuan PKL (kartu "Surat sekolah").</li>'
        . '<li>Tombol <b>Excel buku agenda</b> (kanan atas) mengunduh <b>buku agenda surat keluar</b>: semua surat sesuai tab &amp; saringan yang sedang tampil (semua halaman), lengkap dengan nomor, tanggal, tujuan, status, dan siapa yang meng-ACC.</li>'
        . '</ul>',
]) ?>

<?php if (! empty($permintaanSiswa)): // Layanan Siswa: surat yang diminta siswa sendiri — satu baris; klik = daftar tersaring ?>
    <section class="kartu kartu-isi mb-5 flex flex-wrap items-center gap-2 py-3" aria-labelledby="judul-permintaan-siswa">
        <h2 id="judul-permintaan-siswa" class="me-1 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300"><?= ikon('users', 'h-4 w-4') ?>Permintaan dari siswa:</h2>
        <?php if ($permintaanAda === []): ?>
            <span class="text-sm text-gray-500 dark:text-gray-400">tidak ada yang perlu ditindaklanjuti.</span>
        <?php endif; ?>
        <?php foreach ($permintaanAda as $j => $n):
            $saring = static fn (string $st): string => site_url('admin/surat') . '?' . http_build_query(['jenis' => $j, 'asal' => 'siswa', 'status' => $st]);
        ?>
            <?php if ($n['perlu_acc'] && $n['menunggu'] > 0): ?>
                <a href="<?= esc($saring('menunggu'), 'attr') ?>" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700/60">
                    <?= esc(SuratJenis::singkat($j)) ?> · Perlu ACC <span class="lencana lencana-kuning tabular-nums"><?= (int) $n['menunggu'] ?></span></a>
            <?php endif; ?>
            <?php if ($n['siap'] > 0): ?>
                <a href="<?= esc($saring('disetujui'), 'attr') ?>" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700/60">
                    <?= esc(SuratJenis::singkat($j)) ?> · Siap dicetak <span class="lencana lencana-hijau tabular-nums"><?= (int) $n['siap'] ?></span></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (! empty($tkaGanda)): // F4: satu siswa hanya boleh SATU gelombang TKA — data ganda (dua surat TKA aktif) ?>
    <section class="peringatan peringatan-awas mb-5" aria-labelledby="judul-tka-ganda">
        <?= ikon('exclamation-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <div class="min-w-0 flex-1 space-y-2">
            <p id="judul-tka-ganda" class="font-semibold"><?= count($tkaGanda) ?> siswa tercantum di lebih dari satu Surat Izin TKA (satu siswa hanya ikut satu gelombang).</p>
            <p class="text-sm"><?= ! empty($bolehPindahTka)
                ? 'Buka surat yang <b>salah</b>, lalu di tabel siswa tekan <b>Pindah</b> → <b>Keluarkan saja dari surat ini</b>. Nomor surat tetap; surat yang sudah diunduh tinggal diunduh ulang.'
                : 'Pemegang hak ACC Surat Izin TKA bisa membereskannya lewat tombol <b>Pindah</b> di halaman surat.' ?></p>
            <details class="text-sm">
                <summary class="cursor-pointer font-semibold">Lihat daftar siswanya</summary>
                <ul class="mt-2 space-y-1">
                    <?php foreach ($tkaGanda as $g): ?>
                        <li><b><?= esc($g['nama']) ?></b><?= $g['kelas'] !== '' ? ' (' . esc($g['kelas']) . ')' : '' ?>:
                            <?= implode(' · ', array_map(static fn (array $s): string => '<a href="' . esc(site_url('admin/surat/' . $s['id']), 'attr') . '" class="font-semibold underline">' . esc($s['kode']) . '</a> ' . esc($s['label'] . ', ' . $s['status_teks']), $g['surat'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php if (! $f['ganda']): ?>
                <a href="<?= site_url('admin/surat') . '?ganda=1' ?>" class="btn btn-netral btn-sm"><?= ikon('adjustments-horizontal', 'h-4 w-4') ?>Tampilkan suratnya saja</a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?= view('admin/surat/_nav', ['hitung' => $hitung, 'f' => $f]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-daftar-surat">
    <div class="kartu-kepala">
        <div class="min-w-0">
            <h2 id="judul-daftar-surat" class="kartu-judul flex items-center gap-2"><?= esc($title) ?><span class="lencana lencana-abu"><?= (int) $total ?></span></h2>
            <p class="kartu-ket max-w-3xl"><?= esc($keterangan[$f['status']] ?? '') ?></p>
        </div>
        <?php if ($bisaPilihAcc && $rows !== []): ?>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="submit" form="formAcc" name="mode" value="aman" class="btn btn-sukses btn-sm" title="Hanya surat tanpa peringatan<?= count($jenisAcc ?? []) < count(SuratJenis::kode()) ? ' dan berjenis yang boleh Anda ACC (' . esc(implode(', ', array_map(static fn (string $j): string => SuratJenis::singkat($j), $jenisAcc ?? []))) . ')' : '' ?>; yang punya peringatan dilewati."
                        data-confirm="ACC SEMUA surat yang menunggu dan tanpa peringatan (maks. 200)? Yang punya peringatan akan dilewati." data-confirm-judul="ACC semua yang aman?" data-confirm-ya="Ya, ACC semua"><?= ikon('check-circle', 'h-4 w-4') ?>ACC semua yang aman</button>
            </div>
        <?php elseif ($bisaPilihUnduh && $rows !== []): ?>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="submit" form="formUnduh" name="mode" value="semua" class="btn btn-utama btn-sm" title="Lebih dari 60 surat dikirim sebagai ZIP"><?= ikon('download', 'h-4 w-4') ?>Unduh SEMUA</button>
                <?= ui_menu_lain(array_values(array_filter([
                    ['label' => 'Yang belum diunduh / perlu cetak ulang', 'ikon' => 'download', 'kirim' => ['form' => 'formUnduh', 'name' => 'mode', 'value' => 'belum']],
                    ! empty($kabarWaJenis) ? ['label' => 'Kabari siswa via WhatsApp', 'ikon' => 'users', 'atr' => 'data-kabari-surat-jenis="' . esc($f['jenis'], 'attr') . '" title="Siswa pada surat yang sudah diunduh dan belum dikabari"'] : null,
                ]))) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="alat-crud">
        <form method="get" class="alat-crud-cari" role="search">
            <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= esc($f['status'], 'attr') ?>"><?php endif; ?>
            <?php if (! empty($f['ganda'])): ?><input type="hidden" name="ganda" value="1"><?php endif; ?>
            <label class="alat-crud-kotak">
                <span class="sr-only">Cari</span>
                <?= ikon('search', '') ?>
                <input type="search" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="Cari nomor, judul, perusahaan, atau nama siswa…" class="inp">
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
            <?php if (\App\Libraries\SuratSekolah::adaPermintaan()): ?>
                <select name="asal" data-autosubmit class="inp alat-crud-pilih" aria-label="Asal surat">
                    <option value="">Semua asal</option>
                    <option value="siswa"<?= $f['asal'] === 'siswa' ? ' selected' : '' ?>>Diajukan siswa</option>
                    <option value="staf"<?= $f['asal'] === 'staf' ? ' selected' : '' ?>>Dibuat staf</option>
                </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            <?php if (! empty($f['ganda'])): ?><span class="lencana lencana-kuning self-center">Hanya surat TKA dengan siswa ganda</span><?php endif; ?>
            <?php if ($f['jenis'] !== '' || $f['tahun'] > 0 || $f['q'] !== '' || $f['asal'] !== '' || ! empty($f['ganda'])): ?>
                <a href="<?= site_url('admin/surat') . ($f['status'] !== '' ? '?status=' . urlencode($f['status']) : '') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($f['status'] === 'menunggu' && $rows !== [] && empty($bolehAcc)): ?>
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 sm:px-6 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300">
            <p class="flex items-start gap-2"><?= ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') ?><span><b>ACC dilakukan peran yang diberi hak ACC jenis surat itu</b> oleh Admin (Sistem → Hak Akses). Sebagai <?= esc($peranLabel ?? 'staf') ?> Anda bisa melihat surat yang menunggu dan membukanya.</span></p>
        </div>
    <?php endif; ?>
    <?php if ($bisaPilihAcc && $rows !== []): // formulir ACC massal: tombolnya di kepala kartu & bilah pilih (atribut form=) ?>
        <form id="formAcc" method="post" action="<?= site_url('admin/surat/acc-massal') ?>" class="hidden"><?= csrf_field() ?></form>
    <?php endif; ?>
    <?php if ($bisaPilihUnduh && $rows !== []): // formulir unduh massal: tombolnya di kepala kartu, menu Lainnya & bilah pilih ?>
        <form id="formUnduh" method="post" action="<?= site_url('admin/surat/unduh-massal') ?>" data-unduh<?= ! empty($kabarWaJenis) ? ' data-surat-wa-jenis="' . esc($f['jenis'], 'attr') . '"' : '' ?> class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="jenis" value="<?= esc($f['jenis'], 'attr') ?>">
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
        <div class="hidden md:block"<?= $bisaPilih ? ' x-data="daftarCentang" data-satuan="dipilih" @change="hitungCentang()"' : '' ?>>
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
                                    <?php if ((int) ($r['jml_minta'] ?? 0) > 0): ?><span class="lencana lencana-hijau ms-1 align-middle"><?= (int) $r['jml_minta'] > 1 ? 'Diajukan ' . (int) $r['jml_minta'] . ' siswa' : 'Diajukan siswa' ?></span><?php endif; ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc(trim(((string) $r['perusahaan_nama'] !== '' ? $r['perusahaan_nama'] : '') . ((int) $r['jml_siswa'] > 0 ? ((string) $r['perusahaan_nama'] !== '' ? ' · ' : '') . (int) $r['jml_siswa'] . ' siswa' : ''))) ?></p>
                                </td>
                                <td class="text-xs whitespace-nowrap"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?></td>
                                <td>
                                    <span class="lencana <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
                                    <?php if (! empty($perluUlang[(int) $r['id']])): ?><div><?= $lencanaUlang ?></div><?php endif; ?>
                                    <?php if ($r['status'] === 'disetujui' && (string) $r['acc_nama'] !== ''): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">ACC: <?= esc($r['acc_nama']) ?><?= (string) $r['acc_peran'] !== '' ? ' (' . esc(HakAkses::label((string) $r['acc_peran'])) . ')' : '' ?></p><?php endif; ?>
                                    <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 max-w-[14rem] text-xs text-gray-500 dark:text-gray-400"><?= esc($r['catatan_staf']) ?></p><?php endif; ?>
                                </td>
                                <td class="text-right whitespace-nowrap"><a href="<?= $lihat ?>" class="btn btn-netral btn-sm">Buka</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= $bisaPilih ? $bilahPilih() : '' ?>
        </div>

        <!-- Kartu (HP): bisa dicentang agar ACC terpilih / unduh terpilih juga jalan di HP -->
        <div class="md:hidden"<?= $bisaPilih ? ' x-data="daftarCentang" data-satuan="dipilih" @change="hitungCentang()"' : '' ?>>
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
                                <?php if ((int) ($r['jml_minta'] ?? 0) > 0): ?><span class="lencana lencana-hijau">Diajukan siswa</span><?php endif; ?>
                                <span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?= (string) $r['nomor'] !== '' ? esc($r['nomor']) : 'belum bernomor' ?></span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_surat'])) ?><?= (int) $r['jml_siswa'] > 0 ? ' · ' . (int) $r['jml_siswa'] . ' siswa' : '' ?></p>
                            <?php if (! empty($perluUlang[(int) $r['id']])): ?><div><?= $lencanaUlang ?></div><?php endif; ?>
                            <?php if (in_array($r['status'], ['dikembalikan', 'dibatalkan'], true) && (string) $r['catatan_staf'] !== ''): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span class="font-semibold">Catatan:</span> <?= esc($r['catatan_staf']) ?></p><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= $bisaPilih ? $bilahPilih() : '' ?>
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
