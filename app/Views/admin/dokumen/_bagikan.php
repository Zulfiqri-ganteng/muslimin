<?php
/**
 * Dialog "Bagikan" (folder / berkas) + dialog "Tautan siap dibagikan" — dipakai penjelajah (index) & pratinjau (2026-11-22).
 *
 * Keluhan user: sesudah "Ya, buat tautan" pada folder, halaman malah membuka folder itu dan tautannya tidak tampil di mana pun.
 * Kini: tombol Bagikan membuka dialog ini → daftar tautan yang masih aktif (Salin / WhatsApp / Buka / Cabut; diisi dokumen.js
 * dari atribut data-tautan tombol pemicu) + formulir tautan baru (opsi lanjutan dilipat di <details>). Sesudah dibuat, controller
 * kembali ke halaman asal (isian "kembali") dan flash 'tautanBaru' membuka dialog kedua secara otomatis berisi tautannya.
 *
 * Tombol pemicu: data-buka-dialog="dok-bagikan" data-dialog-aksi="rute bagikan" data-dialog-isi='{"nama":"…"}'
 *                data-tautan='[{url, cabut, info, sandi, unduh, catatan}]' (boleh tidak ada).
 *
 * @var string      $kembali     Alamat halaman ini (dikirim balik agar tetap di sini sesudah membuat/mencabut tautan)
 * @var string|null $aksi        Action bawaan formulir (pratinjau: rute bagikan berkas ini)
 * @var string|null $nama        Nama bawaan yang dibagikan
 * @var string|null $catatanHtml Keterangan tambahan di formulir (HTML tepercaya)
 */
$baru    = session()->getFlashdata('tautanBaru');
$sekolah = (string) ((new \App\Models\SettingModel())->get()['school_name'] ?? '');
?>
<?= ui_dialog_buka(['nama' => 'dok-bagikan', 'judul' => 'Bagikan']) ?>
<div class="dialog-isi">
    <p class="text-sm text-gray-700 dark:text-gray-300">Yang dibagikan: <b class="break-words text-gray-900 dark:text-white" data-isi-pemicu="nama"><?= esc((string) ($nama ?? '')) ?></b></p>
    <div data-bagian-tautan hidden>
        <p class="lbl">Tautan yang masih aktif</p>
        <div data-daftar-tautan class="space-y-2"></div>
        <p class="hint mt-2">Salin tautan di atas lalu kirim ke orang yang dituju, atau buat tautan baru di bawah.</p>
    </div>
    <?php // Cetakan satu kartu tautan aktif — disalin & diisi dokumen.js (url, info, lencana, alamat Cabut). ?>
    <template data-templat-tautan data-sekolah="<?= esc($sekolah, 'attr') ?>">
        <div class="rounded-lg border border-green-200 bg-green-50/40 p-3 dark:border-green-900 dark:bg-gray-700/40">
            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                <span class="lencana lencana-hijau">Aktif</span>
                <span class="lencana lencana-kuning" data-t-sandi>Bersandi</span>
                <span class="lencana lencana-abu" data-t-lihat>Lihat saja</span>
            </div>
            <input readonly class="inp mb-2 !py-1.5 font-mono text-xs" aria-label="Tautan berbagi" data-t-url>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" class="btn btn-utama btn-xs" data-t-salin><?= ikon('file-copy', 'h-3.5 w-3.5') ?><span data-t-label>Salin</span></button>
                <a href="#" target="_blank" rel="noopener" class="btn btn-sukses btn-xs" data-t-wa><?= ikon('whatsapp', 'h-3.5 w-3.5', 'solid') ?>WhatsApp</a>
                <a href="#" target="_blank" rel="noopener" class="btn btn-netral btn-xs" data-t-buka><?= ikon('arrow-up-right-from-square', 'h-3.5 w-3.5') ?>Buka</a>
                <form method="post" action="#" class="ms-auto" data-t-cabut
                      data-confirm="Cabut tautan ini? Siapa pun yang sudah memegangnya tidak bisa lagi membuka isinya." data-confirm-judul="Cabut tautan?" data-confirm-ya="Ya, cabut" data-confirm-jenis="bahaya">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kembali" value="<?= esc($kembali, 'attr') ?>">
                    <button type="submit" class="btn btn-bahaya btn-xs"><?= ikon('close-circle', 'h-3.5 w-3.5') ?>Cabut</button>
                </form>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-t-info></p>
            <p class="mt-0.5 text-xs text-gray-500 italic dark:text-gray-400" data-t-catatan></p>
        </div>
    </template>
</div>
<form method="post" action="<?= esc((string) ($aksi ?? ''), 'attr') ?>" data-aksi-pemicu>
    <?= csrf_field() ?>
    <input type="hidden" name="kembali" value="<?= esc($kembali, 'attr') ?>">
    <div class="dialog-isi !pt-0">
        <?= ui_peringatan('info', '', ['html' => 'Siapa pun yang memegang tautannya bisa membuka <b>tanpa login</b>. Untuk folder: seluruh berkas di dalamnya (termasuk subfolder) ikut terbuka.']) ?>
        <details class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <summary class="cursor-pointer text-sm font-medium text-gray-900 dark:text-white">Atur batas waktu, kata sandi, dll. (opsional)</summary>
            <div class="mt-3 space-y-4">
                <div>
                    <label class="lbl" for="bg-sampai">Berlaku sampai tanggal</label>
                    <input type="date" id="bg-sampai" name="expired_at" class="inp">
                    <p class="hint">Kosongkan bila tautan tidak perlu kedaluwarsa.</p>
                </div>
                <div>
                    <label class="lbl" for="bg-sandi">Kata sandi</label>
                    <input type="text" id="bg-sandi" name="sandi" autocomplete="off" placeholder="Kosongkan bila tidak perlu" class="inp">
                </div>
                <div>
                    <label class="lbl" for="bg-maks">Batas jumlah unduhan</label>
                    <input type="number" id="bg-maks" name="maks_unduh" min="1" placeholder="Kosongkan = tanpa batas" class="inp">
                </div>
                <label class="saklar">
                    <input type="checkbox" name="boleh_unduh" value="1" checked class="peer sr-only">
                    <span class="saklar-jalur"></span>
                    <span class="saklar-label">Boleh diunduh (jika dimatikan, hanya bisa dilihat)</span>
                </label>
                <div>
                    <label class="lbl" for="bg-catatan">Catatan internal</label>
                    <input id="bg-catatan" name="catatan" maxlength="255" placeholder="mis. untuk wali kelas XII" class="inp">
                </div>
            </div>
        </details>
        <?= $catatanHtml ?? '' ?>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-sukses"><?= ikon('link', 'h-4 w-4') ?>Buat tautan baru</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?php if (is_array($baru) && ! empty($baru['url'])): ?>
    <?= ui_dialog_buka(['nama' => 'dok-tautan-baru', 'judul' => 'Tautan siap dibagikan', 'awalBuka' => true]) ?>
    <div class="dialog-isi">
        <p class="text-sm text-gray-700 dark:text-gray-300">
            Tautan untuk <?= esc((string) $baru['jenis']) ?> <b class="break-words text-gray-900 dark:text-white">"<?= esc((string) $baru['nama']) ?>"</b> sudah dibuat.
            Tekan <b>Salin tautan</b>, lalu tempel (paste) di WhatsApp, email, atau grup guru.
        </p>
        <div>
            <label class="lbl" for="dok-tautan-baru-url">Tautan</label>
            <input id="dok-tautan-baru-url" readonly value="<?= esc((string) $baru['url'], 'attr') ?>" class="inp font-mono text-sm">
        </div>
        <ul class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
            <li>• Bisa dibuka tanpa login<?= ! empty($baru['unduh']) ? ', boleh diunduh' : ', hanya bisa dilihat (tidak bisa diunduh)' ?>.</li>
            <?php if (! empty($baru['sampai'])): ?><li>• Berlaku sampai <?= esc((string) $baru['sampai']) ?>.</li><?php endif; ?>
            <li>• Tautan ini selalu bisa dilihat & dicabut lagi lewat tombol <b>Bagikan</b>.</li>
        </ul>
        <?php if (! empty($baru['sandi'])): ?>
            <?= ui_peringatan('awas', '', ['html' => 'Tautan ini <b>bersandi</b>. Beri tahu kata sandinya kepada penerima secara terpisah.']) ?>
        <?php endif; ?>
    </div>
    <div class="dialog-kaki flex-wrap">
        <a href="<?= esc((string) $baru['wa'], 'attr') ?>" target="_blank" rel="noopener" class="btn btn-sukses"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>WhatsApp</a>
        <a href="<?= esc((string) $baru['url'], 'attr') ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('arrow-up-right-from-square', 'h-4 w-4') ?>Buka</a>
        <button type="button" class="btn btn-utama" x-data="salinTeks" data-teks="<?= esc((string) $baru['url'], 'attr') ?>" data-pesan="Tautan tersalin. Tinggal tempel (paste)." @click="salin()">
            <?= ikon('file-copy', 'h-4 w-4') ?><span x-show="!tersalin">Salin tautan</span><span x-show="tersalin" x-cloak>Tersalin</span>
        </button>
    </div>
    <?= ui_dialog_tutup() ?>
<?php endif; ?>
