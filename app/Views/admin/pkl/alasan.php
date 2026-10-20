<?php
/**
 * PKL → TEMPLATE ALASAN (revisi klien 2026-10-20). Kalimat alasan siap klik untuk jendela keputusan PKL; dikelola sendiri oleh
 * pemegang hak "Kelola template alasan" (bawaan Waka Hubin). Controller: Admin\PklAlasan; aturan: Libraries\PklAlasan.
 * Tanpa Alpine sebaris: ubah memakai <details>, hapus memakai data-confirm (app.js), urutan memakai tombol naik/turun (POST).
 *
 * @var array<string, list<array>> $grup   jenis => template (urut)
 * @var array<string, string>      $galat  galat isian terakhir
 * @var int                        $ubahId template yang gagal disimpan (formulirnya dibuka lagi)
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, …
 */

use App\Libraries\PklAlasan;

$galat ??= [];
$err   = static fn (string $k): string => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$lama  = static fn (string $k, string $bawaan = ''): string => (string) (old($k) ?? $bawaan);
$opsiJenis = static function (string $pilih): string {
    $h = '';
    foreach (PklAlasan::JENIS as $kode => [$label]) {
        $h .= '<option value="' . esc($kode, 'attr') . '"' . ($kode === $pilih ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
$tambahGagal = $galat !== [] && $ubahId === 0;
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_alasan_v1',
    'helpTitle' => 'Template Alasan',
    'helpBody'  => '<p>Kalimat di halaman ini muncul sebagai <b>tombol alasan cepat</b> di jendela keputusan PKL. Klik tombolnya, kalimat langsung masuk ke kotak alasan (masih bisa diubah atau ditambah, boleh klik lebih dari satu).</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Dikembalikan untuk perbaikan</b> — jendela "Kembalikan untuk diperbaiki" dan "Batalkan persetujuan".</li>'
        . '<li><b>Ditolak / belum dapat disetujui</b> — jendela "Tolak", "Batalkan persetujuan", dan "Keluarkan siswa".</li>'
        . '<li><b>Siswa dikeluarkan / ditolak perusahaan</b> — jendela "Keluarkan siswa" dan "Ditolak perusahaan".</li>'
        . '<li><b>Laporan diterima dikembalikan</b> — kotak Lapor Diterima PKL.</li>'
        . '<li><b>Judul</b> = tulisan di tombol (singkat). <b>Kalimat</b> = isi alasan yang dibaca siswa (juga di pesan WhatsApp).</li>'
        . '<li>Mengubah/menghapus template <b>tidak</b> mengubah alasan yang sudah tercatat di ajuan.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <!-- Tambah template -->
    <details id="tambah" class="kartu scroll-mt-24"<?= $tambahGagal ? ' open' : '' ?>>
        <summary class="kartu-kepala cursor-pointer list-none">
            <h2 class="kartu-judul inline-flex items-center gap-2 text-base"><?= ikon('plus', 'h-5 w-5') ?>Tambah template</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">klik untuk membuka</span>
        </summary>
        <form method="post" action="<?= site_url('admin/pkl/alasan') ?>" class="kartu-isi grid gap-3 md:grid-cols-[14rem_1fr]">
            <?= csrf_field() ?>
            <div>
                <label class="lbl" for="tambah-jenis">Kelompok <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="tambah-jenis" name="jenis" class="inp"><?= $opsiJenis($tambahGagal ? $lama('jenis', 'tolak') : 'tolak') ?></select>
                <?= $tambahGagal ? $err('jenis') : '' ?>
            </div>
            <div>
                <label class="lbl" for="tambah-judul">Judul tombol <span class="text-red-600 dark:text-red-400">*</span></label>
                <input id="tambah-judul" type="text" name="judul" maxlength="<?= PklAlasan::MAKS_JUDUL ?>" required class="inp" placeholder="Contoh: Administrasi belum lunas" value="<?= esc($tambahGagal ? $lama('judul') : '', 'attr') ?>">
                <?= $tambahGagal ? $err('judul') : '' ?>
            </div>
            <div class="md:col-span-2">
                <label class="lbl" for="tambah-isi">Kalimat alasan <span class="text-red-600 dark:text-red-400">*</span></label>
                <textarea id="tambah-isi" name="isi" rows="3" maxlength="<?= PklAlasan::MAKS_ISI ?>" required class="inp" placeholder="Kalimat lengkap yang dibaca siswa."><?= esc($tambahGagal ? $lama('isi') : '') ?></textarea>
                <?= $tambahGagal ? $err('isi') : '' ?>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan template</button>
            </div>
        </form>
    </details>

    <?php foreach (PklAlasan::JENIS as $jenis => [$judulJenis, $ketJenis]): $daftar = $grup[$jenis] ?? []; ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-<?= esc($jenis, 'attr') ?>">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 id="judul-<?= esc($jenis, 'attr') ?>" class="kartu-judul text-base"><?= esc($judulJenis) ?></h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($ketJenis) ?></p>
                </div>
                <span class="lencana lencana-abu shrink-0"><?= count($daftar) ?> template</span>
            </div>
            <?php if ($daftar === []): ?>
                <p class="px-4 py-5 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">Belum ada template di kelompok ini.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($daftar as $i => $t): $gagalIni = $ubahId === $t['id']; ?>
                        <li id="alasan-<?= $t['id'] ?>" class="scroll-mt-24 px-4 py-3 sm:px-6">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white"><?= esc($t['judul']) ?></p>
                                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300"><?= esc($t['isi']) ?></p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Diubah <?= esc((string) ($t['diubah_oleh'] ?? '—')) ?><?= $t['updated_at'] ? ' · ' . esc(date('d-m-Y H:i', strtotime((string) $t['updated_at']))) : '' ?></p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <form method="post" action="<?= site_url('admin/pkl/alasan/' . $t['id'] . '/naik') ?>"><?= csrf_field() ?>
                                        <button type="submit" class="btn btn-netral btn-sm" title="Naikkan" aria-label="Naikkan <?= esc($t['judul'], 'attr') ?>"<?= $i === 0 ? ' disabled' : '' ?>><?= ikon('angle-down', 'h-4 w-4 rotate-180') ?></button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/pkl/alasan/' . $t['id'] . '/turun') ?>"><?= csrf_field() ?>
                                        <button type="submit" class="btn btn-netral btn-sm" title="Turunkan" aria-label="Turunkan <?= esc($t['judul'], 'attr') ?>"<?= $i === count($daftar) - 1 ? ' disabled' : '' ?>><?= ikon('angle-down', 'h-4 w-4') ?></button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/pkl/alasan/' . $t['id'] . '/hapus') ?>"><?= csrf_field() ?>
                                        <button type="submit" class="btn btn-netral btn-sm text-red-700 dark:text-red-400" data-confirm-judul="Hapus template?" data-confirm-ya="Ya, hapus"
                                                data-confirm="<?= esc('Hapus template "' . $t['judul'] . '"? Alasan yang sudah tercatat di ajuan tidak berubah.', 'attr') ?>"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</button>
                                    </form>
                                </div>
                            </div>
                            <details class="mt-2"<?= $gagalIni ? ' open' : '' ?>>
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= ikon('edit', 'h-4 w-4') ?>Ubah</summary>
                                <form method="post" action="<?= site_url('admin/pkl/alasan/' . $t['id']) ?>" class="mt-2 grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-[14rem_1fr] dark:border-gray-600">
                                    <?= csrf_field() ?>
                                    <div>
                                        <label class="lbl" for="jenis-<?= $t['id'] ?>">Kelompok</label>
                                        <select id="jenis-<?= $t['id'] ?>" name="jenis" class="inp"><?= $opsiJenis($gagalIni ? $lama('jenis', $t['jenis']) : $t['jenis']) ?></select>
                                        <?= $gagalIni ? $err('jenis') : '' ?>
                                    </div>
                                    <div>
                                        <label class="lbl" for="judul-<?= $t['id'] ?>">Judul tombol</label>
                                        <input id="judul-<?= $t['id'] ?>" type="text" name="judul" maxlength="<?= PklAlasan::MAKS_JUDUL ?>" required class="inp" value="<?= esc($gagalIni ? $lama('judul', $t['judul']) : $t['judul'], 'attr') ?>">
                                        <?= $gagalIni ? $err('judul') : '' ?>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="lbl" for="isi-<?= $t['id'] ?>">Kalimat alasan</label>
                                        <textarea id="isi-<?= $t['id'] ?>" name="isi" rows="3" maxlength="<?= PklAlasan::MAKS_ISI ?>" required class="inp"><?= esc($gagalIni ? $lama('isi', $t['isi']) : $t['isi']) ?></textarea>
                                        <?= $gagalIni ? $err('isi') : '' ?>
                                    </div>
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="btn btn-utama btn-sm"><?= ikon('check', 'h-4 w-4') ?>Simpan perubahan</button>
                                    </div>
                                </form>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
