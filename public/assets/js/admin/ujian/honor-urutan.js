/**
 * Ujian → Honor → Atur urutan (pratinjau) — rombak tampilan F7: dulu x-data objek sebaris ({ hanya: … }). Kini komponen
 * "urutanHonor": saklar "Hanya yang berubah" (nilai awal dari data-hanya = 1 bila ada baris yang berubah) → siap Alpine CSP.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('urutanHonor', function () {
        return {
            hanya: false,
            init: function () { this.hanya = this.$el.getAttribute('data-hanya') === '1'; },
        };
    });
});
