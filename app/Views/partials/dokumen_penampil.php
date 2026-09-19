<?php

/**
 * Penampil isi dokumen — dipakai BERSAMA oleh halaman pratinjau admin dan
 * halaman penerima tautan berbagi, supaya guru yang membuka tautan melihat
 * persis apa yang dilihat admin (dan tak ada dua salinan kode yang bisa
 * berbeda perilaku).
 *
 * @var string      $urlIsi          URL penyaji berkas (inline)
 * @var string      $urlUnduh        URL unduhan
 * @var string      $kategori        pdf|dokumen|spreadsheet|presentasi|gambar|audio|video|arsip|lainnya
 * @var string      $ext             ekstensi berkas (huruf kecil)
 * @var string      $judul
 * @var string      $mime
 * @var bool        $tautan          dokumen ini berupa tautan eksternal?
 * @var string|null $ytId            id video YouTube (bila tautan YouTube)
 * @var string|null $urlEksternal
 * @var string      $penyedia
 * @var bool        $bolehViewerLuar tampilkan tombol Office Viewer?
 * @var string|null $urlViewerLuar
 * @var bool        $bolehUnduh      tombol unduh ditampilkan?
 */
$bolehUnduh      = $bolehUnduh      ?? true;
$bolehViewerLuar = $bolehViewerLuar ?? false;
$ytId            = $ytId            ?? null;
$penyedia        = $penyedia        ?? 'lainnya';
$pakaiPustaka    = ! $tautan && in_array($kategori, ['spreadsheet', 'dokumen'], true);
?>

<?php if ($pakaiPustaka) { ?>
    <?php if ($kategori === 'spreadsheet' && $ext !== 'csv') { ?>
        <script defer src="<?= base_url('assets/js/vendor/xlsx.full.min.js') ?>"></script>
    <?php } elseif ($ext === 'csv') { ?>
        <script defer src="<?= base_url('assets/js/vendor/xlsx.full.min.js') ?>"></script>
    <?php } ?>
    <?php if ($ext === 'docx') { ?>
        <script defer src="<?= base_url('assets/js/vendor/mammoth.browser.min.js') ?>"></script>
    <?php } ?>
<?php } ?>

<div x-data="penampilDokumen({ kategori: '<?= esc($kategori, 'js') ?>', ext: '<?= esc($ext, 'js') ?>', url: '<?= esc($urlIsi, 'js') ?>' })" x-init="muat()">

    <?php if ($tautan && $ytId !== null) { ?>
        <div class="aspect-video bg-slate-900">
            <iframe class="w-full h-full" src="https://www.youtube-nocookie.com/embed/<?= esc($ytId) ?>"
                    title="<?= esc($judul) ?>" frameborder="0" allowfullscreen
                    allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"></iframe>
        </div>

    <?php } elseif ($tautan) { ?>
        <div class="p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m8.656-4.828l1.5-1.5a4 4 0 115.656 5.656l-3 3a4 4 0 01-5.656 0"/></svg>
            <p class="mt-3 text-slate-600 text-sm">Dokumen ini berupa tautan ke <b><?= esc($penyedia) ?></b>.</p>
            <p class="text-xs text-slate-400 mt-1 break-all"><?= esc((string) ($urlEksternal ?? '')) ?></p>
            <a href="<?= esc((string) ($urlEksternal ?? '')) ?>" target="_blank" rel="noopener"
               class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Buka Tautan</a>
        </div>

    <?php } elseif ($kategori === 'pdf') { ?>
        <!-- Penampil ini mengisi lebar penuh halaman, jadi tingginya ikut
             dinaikkan supaya satu halaman A4 terbaca tanpa perlu digulir. -->
        <iframe src="<?= esc($urlIsi) ?>#view=FitH" class="w-full h-[85vh] min-h-[520px] bg-slate-100" title="<?= esc($judul) ?>"></iframe>

    <?php } elseif ($kategori === 'gambar' && ! in_array($ext, ['heic', 'heif', 'svg'], true)) { ?>
        <div class="bg-slate-900 flex items-center justify-center min-h-[50vh]">
            <img src="<?= esc($urlIsi) ?>" alt="<?= esc($judul) ?>" class="max-w-full max-h-[78vh] object-contain">
        </div>

    <?php } elseif ($kategori === 'gambar') { ?>
        <div class="p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="mt-3 text-slate-600 text-sm font-medium">Pratinjau <?= esc(strtoupper($ext)) ?> tidak tersedia di peramban</p>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                <?= $ext === 'svg'
                    ? 'Berkas SVG sengaja tidak ditampilkan langsung karena bisa memuat skrip. Unduh untuk membukanya.'
                    : 'Foto iPhone (HEIC) belum didukung peramban. Berkasnya tetap utuh — unduh untuk membukanya.' ?>
            </p>
            <?php if ($bolehUnduh) { ?>
                <a href="<?= esc($urlUnduh) ?>" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Unduh Berkas</a>
            <?php } ?>
        </div>

    <?php } elseif ($kategori === 'audio') { ?>
        <div class="p-8 flex flex-col items-center gap-4">
            <svg class="w-14 h-14 text-brand-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
            <audio controls preload="metadata" class="w-full max-w-md"><source src="<?= esc($urlIsi) ?>" type="<?= esc($mime) ?>"></audio>
        </div>

    <?php } elseif ($kategori === 'video') { ?>
        <div class="bg-slate-900">
            <video controls preload="metadata" class="w-full max-h-[78vh]"><source src="<?= esc($urlIsi) ?>" type="<?= esc($mime) ?>"></video>
        </div>

    <?php } elseif ($pakaiPustaka) { ?>
        <div class="p-4">
            <div x-show="status === 'muat'" class="py-16 text-center text-sm text-slate-500">
                <svg class="w-6 h-6 mx-auto animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="mt-2">Membuka isi berkas…</p>
            </div>

            <div x-show="status === 'gagal'" x-cloak class="py-12 text-center">
                <p class="text-sm text-slate-600 font-medium">Isi berkas tidak bisa ditampilkan di sini</p>
                <p class="text-xs text-slate-500 mt-1" x-text="pesan"></p>
                <?php if ($bolehUnduh) { ?>
                    <a href="<?= esc($urlUnduh) ?>" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Unduh Berkas</a>
                <?php } ?>
            </div>

            <div x-show="status === 'siap' && lembar.length > 1" x-cloak class="flex gap-1 flex-wrap mb-3 border-b border-slate-200 pb-2">
                <template x-for="(nama, i) in lembar" :key="nama">
                    <button type="button" @click="pilihLembar(i)"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium"
                            :class="i === lembarAktif ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            x-text="nama"></button>
                </template>
            </div>

            <div x-show="status === 'siap'" x-cloak class="dok-pratinjau overflow-auto max-h-[72vh]" x-html="isi"></div>
        </div>

    <?php } else { ?>
        <div class="p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <p class="mt-3 text-slate-600 text-sm font-medium">
                <?= $kategori === 'presentasi'
                    ? 'PowerPoint tidak bisa ditampilkan langsung di peramban'
                    : 'Jenis berkas ini tidak punya pratinjau' ?>
            </p>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Berkasnya tersimpan utuh. Unduh untuk membukanya dengan aplikasi di komputer<?= $kategori === 'presentasi' ? ' (PowerPoint, WPS, LibreOffice)' : '' ?>.
            </p>
            <div class="mt-4 flex items-center justify-center gap-2 flex-wrap">
                <?php if ($bolehUnduh) { ?>
                    <a href="<?= esc($urlUnduh) ?>" class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Unduh Berkas</a>
                <?php } ?>
                <?php if ($bolehViewerLuar && ! empty($urlViewerLuar)) { ?>
                    <a href="<?= esc($urlViewerLuar) ?>" target="_blank" rel="noopener"
                       class="px-4 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">Buka di Office Viewer</a>
                <?php } ?>
            </div>
            <?php if ($bolehViewerLuar && ! empty($urlViewerLuar)) { ?>
                <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3 inline-block max-w-sm">
                    Office Viewer membuka berkas lewat server Microsoft, jadi berkas ini dikirim ke sana.
                    Tombol ini hanya muncul untuk berkas yang memang sudah boleh diakses dari luar.
                </p>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<?php if (! defined('DOK_PENAMPIL_JS')) { define('DOK_PENAMPIL_JS', true); ?>
<script>
/**
 * Pembaca isi Excel & Word. Seluruh pengolahan terjadi DI PERAMBAN memakai
 * pustaka yang dilayani server ini sendiri, jadi berkas tak pernah dikirim
 * ke layanan pihak ketiga.
 */
function penampilDokumen(opt) {
    return {
        status: 'muat', pesan: '', isi: '', lembar: [], lembarAktif: 0, _wb: null,

        async muat() {
            if (!['spreadsheet', 'dokumen'].includes(opt.kategori)) { this.status = 'siap'; return; }

            try {
                const res = await fetch(opt.url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Berkas tidak bisa diambil (HTTP ' + res.status + ')');

                if (opt.ext === 'csv') {
                    this.dariCsv(await res.text());
                } else if (opt.ext === 'txt' || opt.ext === 'md') {
                    this.dariTeks(await res.text());
                } else if (opt.ext === 'docx') {
                    await this.dariDocx(await res.arrayBuffer());
                } else if (['xlsx', 'xls', 'ods'].includes(opt.ext)) {
                    this.dariExcel(await res.arrayBuffer());
                } else {
                    throw new Error('Format ' + opt.ext.toUpperCase() + ' hanya bisa dibuka setelah diunduh.');
                }
                this.status = 'siap';
            } catch (e) {
                this.pesan = e.message || 'Terjadi kesalahan saat membaca berkas.';
                this.status = 'gagal';
            }
        },

        dariTeks(teks) {
            const aman = teks.replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
            this.isi = '<pre class="text-xs whitespace-pre-wrap font-mono text-slate-700">' + aman + '</pre>';
        },

        dariCsv(teks) {
            if (typeof XLSX === 'undefined') { this.dariTeks(teks); return; }
            this._wb = XLSX.read(teks, { type: 'string' });
            this.lembar = this._wb.SheetNames;
            this.pilihLembar(0);
        },

        dariExcel(buf) {
            if (typeof XLSX === 'undefined') throw new Error('Pustaka penampil Excel belum termuat.');
            this._wb = XLSX.read(new Uint8Array(buf), { type: 'array' });
            this.lembar = this._wb.SheetNames;
            if (!this.lembar.length) throw new Error('Berkas Excel ini tidak berisi lembar apa pun.');
            this.pilihLembar(0);
        },

        pilihLembar(i) {
            this.lembarAktif = i;
            this.isi = XLSX.utils.sheet_to_html(this._wb.Sheets[this.lembar[i]], { editable: false });
        },

        async dariDocx(buf) {
            if (typeof mammoth === 'undefined') throw new Error('Pustaka penampil Word belum termuat.');
            const hasil = await mammoth.convertToHtml({ arrayBuffer: buf });
            this.isi = hasil.value && hasil.value.trim() !== ''
                ? hasil.value
                : '<p class="text-sm text-slate-500">Dokumen ini tidak memuat teks yang bisa ditampilkan (kemungkinan isinya berupa gambar).</p>';
        },
    };
}
</script>
<?php } ?>
