/**
 * Halaman admin Isian Biodata Siswa — komponen Alpine `biodataAdmin`:
 * salin teks, bagikan ke WhatsApp, dan hitung centang "setujui terpilih".
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('biodataAdmin', function () {
        return {
            tersalin: '',
            jumlah: 0,

            /** Di HP bar tab bisa digeser: pastikan tab yang aktif terlihat di tengah. */
            init: function () {
                var nav = this.$refs.tabNav;
                var aktif = nav && nav.querySelector('[aria-current="page"]');
                if (aktif && nav.scrollWidth > nav.clientWidth) {
                    nav.scrollLeft = aktif.offsetLeft - (nav.clientWidth - aktif.clientWidth) / 2;
                }
            },

            salin: function (teks, kunci) {
                var self = this;
                var tandai = function () {
                    self.tersalin = kunci;
                    setTimeout(function () { if (self.tersalin === kunci) { self.tersalin = ''; } }, 2000);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(teks).then(tandai, function () { self.salinLama(teks); tandai(); });
                } else {
                    this.salinLama(teks);
                    tandai();
                }
            },

            /** Cadangan untuk http:// (clipboard API hanya ada di konteks aman). */
            salinLama: function (teks) {
                var ta = document.createElement('textarea');
                ta.value = teks;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) { /* abaikan */ }
                document.body.removeChild(ta);
            },

            wa: function (teks) {
                window.open('https://wa.me/?text=' + encodeURIComponent(teks), '_blank', 'noopener');
            },

            pilihSemua: function (e) {
                document.querySelectorAll('.bio-cek').forEach(function (c) { c.checked = e.target.checked; });
                this.hitung();
            },

            hitung: function () {
                this.jumlah = document.querySelectorAll('.bio-cek:checked').length;
            },
        };
    });

    /**
     * Halaman Periksa Biodata (detail) — rombak tampilan F6: dulu x-data objek sebaris + penugasan di @click (tidak didukung
     * Alpine versi CSP). Saklar "Tampilkan yang berubah saja" (x-model hanyaBeda) & formulir "Kembalikan untuk diperbaiki".
     */
    Alpine.data('periksaBiodata', function () {
        return {
            formKembali: false,
            hanyaBeda: false,

            /** Buka/tutup formulir Kembalikan; saat terbuka, kursor langsung di kotak catatan. */
            alihKembali: function () {
                var self = this;
                this.formKembali = !this.formKembali;
                if (this.formKembali) {
                    this.$nextTick(function () { if (self.$refs.catatan) { self.$refs.catatan.focus(); } });
                }
            },

            tutupKembali: function () { this.formKembali = false; },
        };
    });
});
