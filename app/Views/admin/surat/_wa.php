<?php
/**
 * Dialog "Kabari siswa lewat WhatsApp" — surat siap diambil (semua jenis surat). Komponen Alpine `suratWa`
 * (assets/js/admin/surat-wa.js); dibuka lewat event 'surat-wa' {id | jenis} atau otomatis setelah unduhan selesai.
 * Tombol pemicu di halaman: data-kabari-surat="ID" / data-kabari-surat-jenis="jenis" (delegasi di surat-wa.js).
 * Rombak tampilan F9: bingkai dialog kit (.dialog + :data-buka; kepala/kaki diam, isi bergulir), tombol kit ber-ikon, ikon
 * pengganti emoji/svg, skrip ber-nonce. Isian Alpine tetap.
 * Hanya dimuat bagi pemegang hak Unduh jenis surat itu (Libraries\SuratWa).
 */
$cfgWa = esc(json_encode([
    'urlSurat' => site_url('admin/surat'),
    'urlJenis' => site_url('admin/surat/wa'),
    'csrfName' => csrf_token(),
]), 'attr');
?>
<div x-data="suratWa" data-config="<?= $cfgWa ?>" @keydown.escape.window="buka && tutup()">
    <div class="dialog" :data-buka="buka" role="dialog" aria-modal="true" aria-labelledby="judulSuratWa">
        <div class="dialog-latar" @click="tutup()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-lg flex max-h-[calc(100vh-2rem)] flex-col" tabindex="-1">
            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="judulSuratWa" class="dialog-judul">Kabari siswa: surat siap diambil</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Tekan tombol hijau: WhatsApp terbuka dengan pesan siap kirim; Anda tinggal menekan Kirim. <span x-show="daftar.length" x-text="'Belum dikabari: ' + belumDikabari() + ' dari ' + daftar.length"></span></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutup()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <p x-show="memuat" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Memuat…</p>
                <div x-show="galat" class="peringatan peringatan-awas m-4 md:m-5"><p class="font-medium" x-text="galat"></p></div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-600">
                    <template x-for="r in daftar" :key="kunci(r)">
                        <li class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between md:px-5">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white" x-text="r.nama"></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400"><span x-text="r.kelas"></span> · <span x-text="r.perusahaan"></span> · <span class="font-mono" x-text="r.nomor_surat || r.kode"></span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="r.hp || 'Nomor HP kosong'"></span>
                                    <span x-show="r.alasan_tidak" class="font-semibold text-red-600 dark:text-red-400" x-text="' — ' + r.alasan_tidak"></span></p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400" x-show="r.dikabari_at"><?= ikon('check', 'h-3.5 w-3.5 shrink-0') ?><span x-text="'Sudah dikabari ' + waktu(r.dikabari_at) + (r.dikabari_oleh ? ' oleh ' + r.dikabari_oleh : '')"></span></p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="btn btn-netral btn-sm" @click="salin(r)"><?= ikon('file-copy', 'h-4 w-4') ?><span x-text="tersalin === kunci(r) ? 'Tersalin' : 'Salin pesan'"></span></button>
                                <a x-show="r.url" :href="r.url" target="_blank" rel="noopener noreferrer" @click="tandai(r)" class="btn btn-sukses btn-sm"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span x-text="r.dikabari_at ? 'Kirim ulang' : 'Kabari via WA'"></span></a>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="dialog-kaki justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">Isi pesan diatur di Pengaturan PKL → Pesan WhatsApp.</p>
                <button type="button" class="btn btn-netral" @click="tutup()">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script defer src="<?= base_url('assets/js/admin/surat-wa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-wa.js') ?>" <?= csp_script_nonce() ?>></script>
