<?php
/**
 * Pratinjau "Atur urutan" honor — KHUSUS ADMIN. Belum ada yang tersimpan: yang berubah HANYA nomor urut (dan label jabatan
 * bila dicentang); angka isian honor tidak pernah disentuh.
 * Rombak tampilan F7: kartu angka, ui_peringatan, tabel kit, saklar kit "Hanya yang berubah"; komponen Alpine "urutanHonor"
 * (assets/js/admin/ujian/honor-urutan.js) → siap CSP.
 *
 * @var string $slug  @var array $periode  @var string $label
 * @var string $mode     'aturan' (menurut jabatan di Master Guru) | 'excel' (mengikuti berkas Excel sekolah)
 * @var array  $rencana  hasil HonorDokumen::rencanaUrutan*() (baris, pindah, label, + daftar tak cocok untuk Excel)
 * @var string $berkas   nama berkas Excel (mode excel)  @var string $token  @var string $status  @var string $kembali
 */
$baris     = $rencana['baris'];
$terkunci  = $status === 'dikunci';
$jmlBeda   = 0;
foreach ($baris as $b) {
    $jmlBeda += ($b['pindah'] || $b['label_beda']) ? 1 : 0;
}
$tidakAdaDok = $rencana['tak_ada_di_dokumen'] ?? [];
$tidakAdaXl  = $rencana['tak_ada_di_excel'] ?? [];
$angka = static fn (string $lbl, string $isi, bool $sorot): string => '<div class="rounded-lg border px-3 py-2 '
    . ($sorot ? 'border-yellow-300 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50') . '">'
    . '<p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">' . $lbl . '</p>'
    . '<p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white">' . $isi . '</p></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('close', 'h-4 w-4') ?>Batal, kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4" x-data="urutanHonor" data-hanya="<?= $jmlBeda > 0 ? '1' : '0' ?>">

    <section class="kartu" aria-labelledby="judul-urutan">
        <div class="kartu-isi">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($label) ?></p>
            <h2 id="judul-urutan" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Atur urutan penerima honor</h2>
            <?php if ($mode === 'excel'): ?>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Mengikuti urutan di berkas <b class="text-gray-900 dark:text-white"><?= esc($berkas) ?></b>. Nama dicocokkan ke daftar penerima di bawah. <b>Belum ada yang tersimpan.</b></p>
            <?php else: ?>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menurut jabatan di Master Guru: jabatan (urutan di Pengaturan Honor), lalu level, lalu abjad &mdash; sama seperti honor yang baru dibuat. <b>Belum ada yang tersimpan.</b></p>
            <?php endif; ?>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?= $angka('Penerima', (string) count($baris), false) ?>
                <?= $angka('Pindah nomor', (string) (int) $rencana['pindah'], $rencana['pindah'] > 0) ?>
                <?= $angka('Label jabatan beda', (string) (int) $rencana['label'], $rencana['label'] > 0) ?>
                <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 dark:border-green-800 dark:bg-green-900/20">
                    <p class="text-[11px] font-semibold tracking-wide text-green-700 uppercase dark:text-green-400">Angka isian</p>
                    <p class="text-sm font-bold text-green-800 dark:text-green-300">Tidak diubah</p>
                </div>
            </div>

            <?php if ($mode === 'aturan' && $rencana['pindah'] > 0): ?>
                <div class="mt-4"><?= ui_peringatan('awas', '', ['html' => 'Urutan yang kamu atur sendiri (mis. mengikuti Excel sekolah) akan <b>diganti</b> dengan urutan standar. Mau mempertahankan urutan khusus sekolah? Pakai <b>Atur urutan → Ikuti Excel sekolah</b>.']) ?></div>
            <?php endif; ?>
            <?php if ($mode === 'excel' && ($tidakAdaDok !== [] || $tidakAdaXl !== [])): ?>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?php if ($tidakAdaDok !== []): ?>
                        <?= ui_peringatan('awas', implode(', ', array_slice($tidakAdaDok, 0, 15)) . (count($tidakAdaDok) > 15 ? ', …' : ''), ['judul' => 'Ada di Excel, tidak ada di daftar penerima (' . count($tidakAdaDok) . ') — dilewati:']) ?>
                    <?php endif; ?>
                    <?php if ($tidakAdaXl !== []): ?>
                        <?= ui_peringatan('netral', implode(', ', array_slice($tidakAdaXl, 0, 15)) . (count($tidakAdaXl) > 15 ? ', …' : ''), ['judul' => 'Ada di daftar penerima, tidak ada di Excel (' . count($tidakAdaXl) . ') — ditaruh di bawah:']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= site_url('admin/ujian/' . $slug . '/honor/urutan/terapkan') ?>" class="kartu-kaki flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <?= csrf_field() ?>
            <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
            <input type="hidden" name="mode" value="<?= esc($mode, 'attr') ?>">
            <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                <input type="checkbox" name="label" value="1" checked class="cek mt-0.5">
                <span>Ganti juga <b>label jabatan</b> (<?= $mode === 'excel' ? 'sesuai tulisan di Excel' : 'sesuai jabatan di Master Guru' ?>). Label yang pernah kamu ubah sendiri ikut tertimpa.</span>
            </label>
            <?php if ($terkunci): ?>
                <span class="lencana lencana-abu shrink-0 py-1.5"><?= ikon('lock', 'h-4 w-4') ?>Honor DIKUNCI — tidak bisa diubah</span>
            <?php elseif ($jmlBeda === 0): ?>
                <span class="lencana lencana-hijau shrink-0 py-1.5"><?= ikon('check-circle', 'h-4 w-4') ?>Sudah sesuai — tidak ada yang perlu diubah</span>
            <?php else: ?>
                <button type="submit" class="btn btn-utama shrink-0"><?= ikon('check', 'h-4 w-4') ?>Terapkan urutan (<?= $jmlBeda ?> baris berubah)</button>
            <?php endif; ?>
        </form>
    </section>

    <section class="kartu overflow-hidden" aria-labelledby="judul-pratinjau">
        <div class="kartu-kepala">
            <h3 id="judul-pratinjau" class="kartu-judul">Pratinjau perubahan</h3>
            <label class="saklar">
                <input type="checkbox" x-model="hanya" class="peer sr-only">
                <span class="saklar-jalur"></span>
                <span class="saklar-label">Hanya yang berubah</span>
            </label>
        </div>
        <div class="tabel-bungkus">
            <table class="tabel min-w-[44rem]">
                <thead>
                    <tr>
                        <th scope="col">No sekarang</th>
                        <th scope="col">No baru</th>
                        <th scope="col">Nama</th>
                        <th scope="col">Jabatan sekarang</th>
                        <th scope="col">Jabatan baru</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($baris as $b): $beda = $b['pindah'] || $b['label_beda']; ?>
                        <tr <?= $beda ? '' : 'x-show="!hanya"' ?> class="<?= $beda ? 'bg-yellow-50 dark:bg-yellow-900/20' : '' ?>">
                            <td class="text-gray-500 tabular-nums dark:text-gray-400"><?= (int) $b['posisi_lama'] ?></td>
                            <td class="tabular-nums <?= $b['pindah'] ? 'font-bold text-yellow-700 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= (int) $b['posisi_baru'] ?><?php if ($b['pindah']): ?> <span class="lencana lencana-kuning ms-1">pindah</span><?php endif; ?></td>
                            <td class="font-semibold text-gray-900 dark:text-white"><?= esc($b['nama']) ?></td>
                            <td class="text-gray-500 dark:text-gray-400"><?= esc($b['jabatan_lama'] !== '' ? $b['jabatan_lama'] : '—') ?></td>
                            <td class="<?= $b['label_beda'] ? 'font-semibold text-yellow-700 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= esc($b['jabatan_baru'] !== '' ? $b['jabatan_baru'] : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($jmlBeda === 0): ?>
                        <tr x-show="hanya"><td colspan="5" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada yang berubah.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/ujian/honor-urutan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/honor-urutan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
