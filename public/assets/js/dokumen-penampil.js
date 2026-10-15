/**
 * Penampil isi dokumen (Excel/CSV/Word/teks) — komponen Alpine `penampilDokumen` (F10; dulu fungsi global di skrip sebaris).
 * Dipakai halaman pratinjau admin (admin/dokumen/_penampil.php) dan halaman publik berbagi (partials/dokumen_penampil.php).
 * Seluruh pengolahan terjadi DI PERAMBAN memakai pustaka yang dilayani server ini sendiri (xlsx, mammoth), jadi berkas tak pernah
 * dikirim ke layanan pihak ketiga.
 *
 * Konfigurasi dari atribut akar: data-kategori, data-ext, data-url. Isi HTML dipasang ke elemen [data-isi-dokumen] lewat JS —
 * pengganti x-html, yang tidak didukung Alpine versi CSP.
 * Berkas ini dimuat TANPA defer: di layout publik Alpine (defer di <head>) langsung berjalan, jadi komponen harus terdaftar lebih dulu.
 */
(function () {
    'use strict';

    var aman = function (t) {
        return String(t).replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; });
    };

    var daftar = function () {
        Alpine.data('penampilDokumen', function () {
            var akar = null;
            var buku = null; // workbook XLSX
            return {
                status: 'muat',
                pesan: '',
                lembar: [],
                lembarAktif: 0,
                kategori: '',
                ext: '',
                url: '',

                init: function () {
                    akar = this.$el;
                    this.kategori = akar.getAttribute('data-kategori') || '';
                    this.ext = akar.getAttribute('data-ext') || '';
                    this.url = akar.getAttribute('data-url') || '';
                    this.muat();
                },

                pasangIsi: function (html) {
                    var t = akar ? akar.querySelector('[data-isi-dokumen]') : null;
                    if (t) { t.innerHTML = html; }
                },

                muat: function () {
                    var self = this;
                    if (['spreadsheet', 'dokumen'].indexOf(this.kategori) === -1) { this.status = 'siap'; return; }
                    fetch(this.url, { credentials: 'same-origin' }).then(function (res) {
                        if (!res.ok) { throw new Error('Berkas tidak bisa diambil (HTTP ' + res.status + ')'); }
                        var e = self.ext;
                        if (e === 'csv') { return res.text().then(function (t) { self.dariCsv(t); }); }
                        if (e === 'txt' || e === 'md') { return res.text().then(function (t) { self.dariTeks(t); }); }
                        if (e === 'docx') { return res.arrayBuffer().then(function (b) { return self.dariDocx(b); }); }
                        if (['xlsx', 'xls', 'ods'].indexOf(e) !== -1) { return res.arrayBuffer().then(function (b) { self.dariExcel(b); }); }
                        throw new Error('Format ' + e.toUpperCase() + ' hanya bisa dibuka setelah diunduh.');
                    }).then(function () {
                        self.status = 'siap';
                    }).catch(function (err) {
                        self.pesan = (err && err.message) || 'Terjadi kesalahan saat membaca berkas.';
                        self.status = 'gagal';
                    });
                },

                dariTeks: function (teks) {
                    this.pasangIsi('<pre class="whitespace-pre-wrap font-mono text-xs">' + aman(teks) + '</pre>');
                },

                dariCsv: function (teks) {
                    if (typeof XLSX === 'undefined') { this.dariTeks(teks); return; }
                    buku = XLSX.read(teks, { type: 'string' });
                    this.lembar = buku.SheetNames;
                    this.pilihLembar(0);
                },

                dariExcel: function (buf) {
                    if (typeof XLSX === 'undefined') { throw new Error('Pustaka penampil Excel belum termuat.'); }
                    buku = XLSX.read(new Uint8Array(buf), { type: 'array' });
                    this.lembar = buku.SheetNames;
                    if (!this.lembar.length) { throw new Error('Berkas Excel ini tidak berisi lembar apa pun.'); }
                    this.pilihLembar(0);
                },

                pilihLembar: function (i) {
                    if (!buku) { return; }
                    this.lembarAktif = i;
                    this.pasangIsi(XLSX.utils.sheet_to_html(buku.Sheets[this.lembar[i]], { editable: false }));
                },

                dariDocx: function (buf) {
                    var self = this;
                    if (typeof mammoth === 'undefined') { throw new Error('Pustaka penampil Word belum termuat.'); }
                    return mammoth.convertToHtml({ arrayBuffer: buf }).then(function (hasil) {
                        self.pasangIsi(hasil.value && hasil.value.trim() !== ''
                            ? hasil.value
                            : '<p class="text-sm">Dokumen ini tidak memuat teks yang bisa ditampilkan (kemungkinan isinya berupa gambar).</p>');
                    });
                },
            };
        });
    };

    if (window.Alpine) { daftar(); } else { document.addEventListener('alpine:init', daftar); }
})();
