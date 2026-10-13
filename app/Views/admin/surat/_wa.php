<?php
/**
 * Dialog "Kabari siswa lewat WhatsApp" — surat siap diambil (Surat Balasan). Komponen Alpine `suratWa`
 * (assets/js/admin/surat-wa.js); dibuka lewat event 'surat-wa' {id | jenis} atau otomatis setelah unduhan selesai.
 * Hanya dimuat bagi pemegang hak Unduh jenis surat itu (Libraries\SuratWa).
 */
$cfgWa = esc(json_encode([
    'urlSurat' => site_url('admin/surat'),
    'urlJenis' => site_url('admin/surat/wa'),
    'csrfName' => csrf_token(),
]), 'attr');
?>
<div x-data="suratWa" data-config="<?= $cfgWa ?>" @keydown.escape.window="buka && tutup()">
    <div x-show="buka" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="judulSuratWa">
        <div class="absolute inset-0 bg-gray-900/50" @click="tutup()"></div>
        <div class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h3 id="judulSuratWa" class="text-lg font-bold text-gray-800">Kabari siswa: surat siap diambil</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Tekan tombol hijau: WhatsApp terbuka dengan pesan siap kirim; Anda tinggal menekan Kirim. <span x-show="daftar.length" x-text="'Belum dikabari: ' + belumDikabari() + ' dari ' + daftar.length"></span></p>
                </div>
                <button type="button" @click="tutup()" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <p x-show="memuat" class="px-5 py-8 text-center text-sm text-gray-500">Memuat…</p>
                <p x-show="galat" x-text="galat" class="m-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"></p>
                <ul class="divide-y divide-gray-100">
                    <template x-for="r in daftar" :key="kunci(r)">
                        <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800" x-text="r.nama"></p>
                                <p class="text-xs text-gray-500"><span x-text="r.kelas"></span> · <span x-text="r.perusahaan"></span> · <span class="font-mono" x-text="r.nomor_surat || r.kode"></span></p>
                                <p class="text-xs text-gray-400"><span x-text="r.hp || 'Nomor HP kosong'"></span>
                                    <span x-show="r.alasan_tidak" class="font-semibold text-red-600" x-text="' — ' + r.alasan_tidak"></span></p>
                                <p class="text-xs font-semibold text-green-700" x-show="r.dikabari_at" x-text="'✓ Sudah dikabari ' + waktu(r.dikabari_at) + (r.dikabari_oleh ? ' oleh ' + r.dikabari_oleh : '')"></p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" @click="salin(r)" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50" x-text="tersalin === kunci(r) ? '✓ Tersalin' : 'Salin pesan'"></button>
                                <a x-show="r.url" :href="r.url" target="_blank" rel="noopener noreferrer" @click="tandai(r)"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3.5 py-2 text-sm font-bold text-white transition hover:bg-green-700 active:scale-95"
                                   x-text="r.dikabari_at ? 'Kirim ulang' : 'Kabari via WA'"></a>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3.5">
                <p class="text-[11px] leading-relaxed text-gray-400">Isi pesan diatur di Pengaturan PKL → Pesan WhatsApp.</p>
                <button type="button" @click="tutup()" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script defer src="<?= base_url('assets/js/admin/surat-wa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-wa.js') ?>"></script>
