<?php
/**
 * Kotak "Kabari siswa yang dikeluarkan lewat WhatsApp" (revisi klien 2026-10-20; Libraries\PklWaKeputusan::daftarKeluar). Pola sama
 * dengan _kabar_keputusan.php: tombol hijau = tautan wa.me (tab baru, staf menekan Kirim sendiri) + catat "sudah dikabari" di latar;
 * "Salin pesan" = cadangan. Perilaku: assets/js/admin/pkl-kabar.js (delegasi data-kabar-*, siap CSP). Terbuka sendiri sesudah
 * staf mengeluarkan siswa.
 *
 * @var list<array<string, mixed>> $kabarKeluar PklWaKeputusan::daftarKeluar()
 * @var bool                       $kabarBuka   langsung dibuka
 * @var int                        $id          id ajuan
 */
$urlTandai  = static fn (int $keluarId): string => site_url('admin/pkl/' . $id . '/kabar-keluar/' . $keluarId . '/tandai');
$waktuKabar = static fn (?string $w): string => $w ? date('d-m H:i', strtotime($w)) : '';
?>
<?= ui_dialog_buka(['nama' => 'pkl-kabar-keluar', 'judul' => 'Kabari siswa yang dikeluarkan', 'ukuran' => 'lg', 'awalBuka' => $kabarBuka]) ?>
<div class="dialog-isi space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Siswa berikut <b>dikeluarkan</b> dari ajuan ini dan sekarang boleh mengajukan tempat PKL lain. Tekan tombol hijau: WhatsApp terbuka dengan pesan siap kirim, Anda tinggal menekan <b>Kirim</b>.
    </p>
    <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-600 dark:border-gray-600">
        <?php foreach ($kabarKeluar as $s): $sudah = $s['kabar_at'] !== null; ?>
            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" data-kabar-baris>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900 dark:text-white"><?= esc($s['nama']) ?> <span class="lencana lencana-abu"><?= esc($s['jenis_label']) ?></span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($s['kelas']) ?> · <?= esc($s['hp'] !== '' ? $s['hp'] : 'Nomor HP kosong') ?>
                        <?php if ($s['alasan_tidak'] !== null): ?><span class="font-semibold text-red-600 dark:text-red-400"> — <?= esc($s['alasan_tidak']) ?></span><?php endif; ?></p>
                    <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400" data-kabar-status<?= $sudah ? '' : ' hidden' ?>>
                        <?= ikon('check', 'h-3.5 w-3.5 shrink-0') ?><span data-kabar-status-teks><?= $sudah ? 'Sudah dikabari ' . esc($waktuKabar($s['kabar_at'])) . ($s['kabar_oleh'] ? ' oleh ' . esc($s['kabar_oleh']) : '') : '' ?></span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="btn btn-netral btn-sm" data-kabar-salin="<?= esc($s['pesan'], 'attr') ?>"><?= ikon('file-copy', 'h-4 w-4') ?><span data-kabar-label>Salin pesan</span></button>
                    <?php if ($s['url'] !== null): ?>
                        <a href="<?= esc($s['url'], 'attr') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sukses btn-sm" data-kabar-tandai="<?= esc($urlTandai((int) $s['id']), 'attr') ?>">
                            <?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span data-kabar-label><?= $sudah ? 'Kirim ulang' : 'Kabari via WA' ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="hint">Pesan bisa diubah di <b>PKL → Pengaturan → Pesan WhatsApp ke siswa</b> (bagian "Siswa dikeluarkan / ditolak perusahaan"). Setiap tombol yang ditekan tercatat di Riwayat ajuan.</p>
</div>
<div class="dialog-kaki">
    <button type="button" class="btn btn-netral" @click="tutup()">Tutup</button>
</div>
<?= ui_dialog_tutup() ?>
<script defer src="<?= base_url('assets/js/admin/pkl-kabar.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl-kabar.js') ?>" <?= csp_script_nonce() ?>></script>
