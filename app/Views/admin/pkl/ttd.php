<?php
/**
 * Tanda tangan digital di surat: (1) Waka Hubin — hak 'ttd'; (2) tanda tangan + stempel Kepala Sekolah — hak 'ttd_kepsek'
 * (bawaan hanya Admin). Siapa memegang hak diatur Admin di Sistem → Hak Akses. Gambar disimpan di writable/pkl (tidak masuk repo).
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
 */
$tombolFile = 'block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3.5 file:py-2 file:font-semibold file:text-brand-700';
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

<div class="mx-auto grid max-w-5xl grid-cols-1 gap-5 lg:grid-cols-2">
    <!-- ================= Waka Hubin ================= -->
    <section class="rise overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <h3 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-gray-500">Tanda tangan Waka Hubin</h3>
        <div class="space-y-4 p-5">
            <?php if ($ada): ?>
                <div class="flex min-h-[9rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-4">
                    <img src="<?= esc(site_url('admin/pkl/ttd/gambar') . '?v=' . (int) $ver, 'attr') ?>" alt="Tanda tangan Waka Hubin" class="max-h-32 max-w-full object-contain">
                </div>
                <p class="text-[11px] text-gray-400">Pratinjau = gambar yang tercetak di surat (sudah dirapikan).</p>
                <p class="text-sm text-gray-600">Atas nama: <b><?= esc($p['waka_hubin_nama'] ?: '(nama Waka Hubin belum diisi di Pengaturan PKL)') ?></b><br><?= esc($p['waka_hubin_jabatan'] ?? '') ?></p>
            <?php else: ?>
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                    <p class="font-semibold text-gray-700">Belum ada gambar tanda tangan</p>
                    <p class="mt-1 text-sm text-gray-500">Surat tetap bisa dicetak: ruang tanda tangan Waka Hubin dikosongkan untuk tanda tangan basah.</p>
                </div>
            <?php endif; ?>

            <?php if ($bolehHubin): ?>
                <form method="post" action="<?= site_url('admin/pkl/ttd') ?>" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                    <?= csrf_field() ?>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500"><?= $ada ? 'Ganti gambar' : 'Unggah gambar' ?></p>
                    <input type="file" name="ttd" accept="image/png,image/jpeg" required class="<?= $tombolFile ?>">
                    <p class="text-xs leading-relaxed text-gray-500">PNG atau JPG · maksimal 5 MB · 100–4000 piksel. Ukuran &amp; tepi bebas: dirapikan otomatis, lalu dicetak paling besar 5 × 2,2 cm di tengah ruang tanda tangan.</p>
                    <button type="submit" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Simpan tanda tangan</button>
                </form>
                <?php if ($ada): ?>
                    <form method="post" action="<?= site_url('admin/pkl/ttd/hapus') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" data-confirm="Hapus tanda tangan Waka Hubin? Surat berikutnya memberi ruang kosong untuk tanda tangan basah." class="text-xs font-semibold text-red-600 hover:underline">Hapus tanda tangan</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">🔒 Diubah oleh <?= esc($pemegangHubin) ?> (diatur Admin di Sistem → Hak Akses).</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ================= Kepala Sekolah ================= -->
    <section class="rise overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <h3 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-gray-500">Tanda tangan + stempel Kepala Sekolah</h3>
        <div class="space-y-4 p-5">
            <?php if (! $kolomKepsek): ?>
                <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Fitur ini belum aktif di server ini. Jalankan <code>php spark migrate</code> dulu.</p>
            <?php else: ?>
                <?php if ($adaKepsek): ?>
                    <div class="flex min-h-[9rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-4">
                        <img src="<?= esc(site_url('admin/pkl/ttd-kepsek/gambar') . '?v=' . (int) $verKepsek, 'attr') ?>" alt="Tanda tangan dan stempel Kepala Sekolah" class="max-h-36 max-w-full object-contain">
                    </div>
                    <p class="text-[11px] text-gray-400">Pratinjau = gambar yang tercetak di surat (sudah dirapikan).</p>
                    <p class="text-sm text-gray-600">Atas nama: <b><?= esc(($p['kepsek_nama'] ?? '') ?: '(nama Kepala Sekolah belum diisi di Pengaturan PKL)') ?></b><br><span class="text-xs text-gray-500">Menggantikan tanda tangan bawaan template di semua surat bernomor.</span></p>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                        <p class="font-semibold text-gray-700">Belum ada gambar</p>
                        <p class="mt-1 text-sm text-gray-500">Surat memakai tanda tangan Kepala Sekolah bawaan template (tanpa stempel).</p>
                    </div>
                <?php endif; ?>

                <?php if ($bolehKepsek): ?>
                    <form method="post" action="<?= site_url('admin/pkl/ttd-kepsek') ?>" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                        <?= csrf_field() ?>
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500"><?= $adaKepsek ? 'Ganti gambar' : 'Unggah gambar' ?></p>
                        <input type="file" name="ttd_kepsek" accept="image/png,image/jpeg" required class="<?= $tombolFile ?>">
                        <p class="text-xs leading-relaxed text-gray-500">SATU gambar berisi tanda tangan <b>dan</b> stempel · PNG atau JPG · maksimal 5 MB · 100–4000 piksel. Ukuran &amp; tepi bebas: dirapikan otomatis, lalu dicetak paling besar 4 × 3,6 cm di posisi rapian sekolah.</p>
                        <button type="submit" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Simpan tanda tangan + stempel</button>
                    </form>
                    <?php if ($adaKepsek): ?>
                        <form method="post" action="<?= site_url('admin/pkl/ttd-kepsek/hapus') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" data-confirm="Hapus gambar tanda tangan + stempel Kepala Sekolah? Surat kembali memakai tanda tangan bawaan template (tanpa stempel)." class="text-xs font-semibold text-red-600 hover:underline">Hapus gambar</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">🔒 Diubah oleh <?= esc($pemegangKepsek) ?> (diatur Admin di Sistem → Hak Akses).</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
