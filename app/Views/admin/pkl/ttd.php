<?php
/**
 * Tanda tangan digital di surat: (1) Waka Hubin — hak 'ttd'; (2) tanda tangan + stempel Kepala Sekolah — hak 'ttd_kepsek'
 * (bawaan hanya Admin). Siapa memegang hak diatur Admin di Sistem → Hak Akses. Gambar disimpan di writable/pkl (tidak masuk repo).
 * Rombak tampilan F9: tab kit (_nav), kartu kit, pratinjau di atas "kertas" putih (juga di mode gelap, supaya tinta terlihat),
 * area unggah = komponen kit "pilihBerkas", tombol kit ber-ikon, Hapus merah + dialog konfirmasi kit, catatan hak ber-ikon.
 *
 * @var array  $p              baris pkl_pengaturan
 * @var bool   $ada            gambar tanda tangan Waka Hubin sudah ada
 * @var int    $ver            penanda versi gambar Waka Hubin (cache)
 * @var bool   $adaKepsek      gambar tanda tangan + stempel Kepala Sekolah sudah ada
 * @var int    $verKepsek      penanda versi gambar Kepala Sekolah (cache)
 * @var bool   $kolomKepsek    kolom pkl_pengaturan.ttd_kepsek sudah ada (migrasi sudah dijalankan)
 * @var bool   $bolehHubin     peran ini boleh mengubah tanda tangan Waka Hubin
 * @var bool   $bolehKepsek    peran ini boleh mengubah tanda tangan + stempel Kepala Sekolah
 * @var string $pemegangHubin  peran pemegang hak tanda tangan Waka Hubin (teks)
 * @var string $pemegangKepsek peran pemegang hak tanda tangan + stempel Kepala Sekolah (teks)
 * @var array<string,int> $hitungTab
 */
// Area unggah satu gambar (kit pilihBerkas).
$unggah = static fn (string $nama, string $judul): string => '<label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-5 text-center transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">'
    . ikon('upload', 'h-7 w-7 text-gray-500 dark:text-gray-400')
    . '<span class="text-sm font-semibold text-gray-900 dark:text-white">' . esc($judul) . '</span>'
    . '<span class="text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>'
    . '<input type="file" name="' . $nama . '" accept="image/png,image/jpeg" required class="sr-only" @change="catatBerkas($event)"></label>';
$catatanKunci = static fn (string $siapa): string => '<div class="peringatan peringatan-netral">' . ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') . '<p>Diubah oleh ' . esc($siapa) . ' (diatur Admin di Sistem → Hak Akses).</p></div>';
$pratinjau = static fn (string $src, string $alt, string $tinggi): string => '<div class="flex min-h-[9rem] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-4 dark:border-gray-600">'
    . '<img src="' . esc($src, 'attr') . '" alt="' . esc($alt, 'attr') . '" class="' . $tinggi . ' max-w-full object-contain"></div>'
    . '<p class="hint !mt-1">Pratinjau = gambar yang tercetak di surat (sudah dirapikan).</p>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_ttd_v2',
    'helpTitle' => 'Tanda Tangan di Surat',
    'helpBody'  => '<p>Dua gambar yang <b>otomatis tercetak di surat</b> (Surat Izin PKL, Izin ASTS/TKA, Surat Balasan, Penarikan) &mdash; <b>siapa pun yang meng-ACC</b>. Kaki surat tetap mencatat siapa yang menyetujui.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Tanda tangan Waka Hubin</b>: dipasang di atas nama Waka Hubin. Tanpa gambar, ruangnya dikosongkan untuk tanda tangan basah.</li>'
        . '<li><b>Tanda tangan + stempel Kepala Sekolah</b>: SATU gambar berisi tanda tangan dan stempel, menggantikan tanda tangan Kepala Sekolah bawaan template (yang tanpa stempel). Ukuran dan letaknya mengikuti rapian sekolah pada contoh surat, jadi tidak perlu ditempel manual di Word lagi.</li>'
        . '<li><b>Ukuran berapa pun boleh</b> (foto HP atau hasil pindai, PNG/JPG, tanda tangan di kertas putih): sistem otomatis memotong tepi kosong, membuat latar kertas transparan, lalu menyesuaikan ukurannya dengan tempat tanda tangan di surat.</li>'
        . '<li>Siapa yang boleh mengubah diatur Admin di <b>Sistem → Hak Akses</b>. Sekarang: Waka Hubin oleh <b>' . esc($pemegangHubin) . '</b>; Kepala Sekolah oleh <b>' . esc($pemegangKepsek) . '</b>.</li>'
        . '<li>Gambar disimpan di server (folder <i>writable</i>) dan <b>tidak</b> ikut tersimpan di kode program.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'ttd', 'hitungTab' => $hitungTab, 'bolehTtd' => true]) ?>

<div class="mx-auto grid max-w-5xl grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- ================= Waka Hubin ================= -->
    <section class="kartu" aria-labelledby="judul-ttd-hubin">
        <div class="kartu-kepala"><h3 id="judul-ttd-hubin" class="kartu-judul text-base">Tanda tangan Waka Hubin</h3></div>
        <div class="kartu-isi space-y-4">
            <?php if ($ada): ?>
                <div><?= $pratinjau(site_url('admin/pkl/ttd/gambar') . '?v=' . (int) $ver, 'Tanda tangan Waka Hubin', 'max-h-32') ?></div>
                <p class="text-sm text-gray-600 dark:text-gray-300">Atas nama: <b class="text-gray-900 dark:text-white"><?= esc($p['waka_hubin_nama'] ?: '(nama Waka Hubin belum diisi di Pengaturan PKL)') ?></b><br><?= esc($p['waka_hubin_jabatan'] ?? '') ?></p>
            <?php else: ?>
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600"><?= ui_kosong(['ikon' => 'file-pen', 'judul' => 'Belum ada gambar tanda tangan', 'teks' => 'Surat tetap bisa dicetak: ruang tanda tangan Waka Hubin dikosongkan untuk tanda tangan basah.']) ?></div>
            <?php endif; ?>

            <?php if ($bolehHubin): ?>
                <form method="post" action="<?= site_url('admin/pkl/ttd') ?>" enctype="multipart/form-data" class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <?= csrf_field() ?>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= $ada ? 'Ganti gambar' : 'Unggah gambar' ?></p>
                    <?= $unggah('ttd', 'Pilih gambar tanda tangan (PNG / JPG)') ?>
                    <p class="hint">PNG atau JPG · maksimal 5 MB · 100–4000 piksel. Ukuran &amp; tepi bebas: dirapikan otomatis, lalu dicetak paling besar 5 × 2,2 cm di tengah ruang tanda tangan.</p>
                    <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Simpan tanda tangan</button>
                </form>
                <?php if ($ada): ?>
                    <form method="post" action="<?= site_url('admin/pkl/ttd/hapus') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-teks btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700"
                                data-confirm="Hapus tanda tangan Waka Hubin? Surat berikutnya memberi ruang kosong untuk tanda tangan basah." data-confirm-judul="Hapus tanda tangan?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus tanda tangan</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?= $catatanKunci($pemegangHubin) ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- ================= Kepala Sekolah ================= -->
    <section class="kartu" aria-labelledby="judul-ttd-kepsek">
        <div class="kartu-kepala"><h3 id="judul-ttd-kepsek" class="kartu-judul text-base">Tanda tangan + stempel Kepala Sekolah</h3></div>
        <div class="kartu-isi space-y-4">
            <?php if (! $kolomKepsek): ?>
                <?= ui_peringatan('awas', '', ['html' => 'Fitur ini belum aktif di server ini. Jalankan <code>php spark migrate</code> dulu.']) ?>
            <?php else: ?>
                <?php if ($adaKepsek): ?>
                    <div><?= $pratinjau(site_url('admin/pkl/ttd-kepsek/gambar') . '?v=' . (int) $verKepsek, 'Tanda tangan dan stempel Kepala Sekolah', 'max-h-36') ?></div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Atas nama: <b class="text-gray-900 dark:text-white"><?= esc(($p['kepsek_nama'] ?? '') ?: '(nama Kepala Sekolah belum diisi di Pengaturan PKL)') ?></b><br><span class="text-xs text-gray-500 dark:text-gray-400">Menggantikan tanda tangan bawaan template di semua surat bernomor.</span></p>
                <?php else: ?>
                    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600"><?= ui_kosong(['ikon' => 'file-pen', 'judul' => 'Belum ada gambar', 'teks' => 'Surat memakai tanda tangan Kepala Sekolah bawaan template (tanpa stempel).']) ?></div>
                <?php endif; ?>

                <?php if ($bolehKepsek): ?>
                    <form method="post" action="<?= site_url('admin/pkl/ttd-kepsek') ?>" enctype="multipart/form-data" class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <?= csrf_field() ?>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= $adaKepsek ? 'Ganti gambar' : 'Unggah gambar' ?></p>
                        <?= $unggah('ttd_kepsek', 'Pilih gambar tanda tangan + stempel (PNG / JPG)') ?>
                        <p class="hint">SATU gambar berisi tanda tangan <b>dan</b> stempel · PNG atau JPG · maksimal 5 MB · 100–4000 piksel. Ukuran &amp; tepi bebas: dirapikan otomatis, lalu dicetak paling besar 4 × 3,6 cm di posisi rapian sekolah.</p>
                        <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Simpan tanda tangan + stempel</button>
                    </form>
                    <?php if ($adaKepsek): ?>
                        <form method="post" action="<?= site_url('admin/pkl/ttd-kepsek/hapus') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-teks btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700"
                                    data-confirm="Hapus gambar tanda tangan + stempel Kepala Sekolah? Surat kembali memakai tanda tangan bawaan template (tanpa stempel)." data-confirm-judul="Hapus gambar?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus gambar</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <?= $catatanKunci($pemegangKepsek) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
