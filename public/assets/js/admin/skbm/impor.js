/**
 * SKBM → Impor Excel (pratinjau) — rombak tampilan F7: dulu x-data objek sebaris ({ tujuan, berisi }) + x-text berisi
 * penyambungan teks. Kini komponen "imporSkbm": tahun tujuan (x-model, nilai awal data-tujuan) & jumlah guru SKBM yang sudah
 * ada per tahun (data-berisi JSON) → peringatan "akan dibuang" bila tahun tujuan sudah berisi. Siap Alpine versi CSP.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('imporSkbm', function () {
        return {
            tujuan: '',
            berisi: {},
            init: function () {
                this.tujuan = this.$el.getAttribute('data-tujuan') || '';
                this.berisi = window.readJson(this.$el, 'berisi', {}) || {};
            },
            adaIsi: function () { return !!this.berisi[this.tujuan]; },
            teksIsi: function () {
                return 'SKBM ' + this.tujuan + ' sekarang sudah berisi ' + (this.berisi[this.tujuan] || 0) + ' guru dan akan dibuang.';
            },
        };
    });
});
