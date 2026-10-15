/**
 * Ujian → Honor → Impor Excel (pratinjau) — rombak tampilan F7: dulu x-data objek bermetode sebaris dengan fungsi panah &
 * document.querySelectorAll di isian (tidak didukung Alpine versi CSP). Kini komponen "imporHonor": tombol "Tambahkan semua
 * sebagai guru/staf" & "Lewati semua" mengisi semua pilihan tindakan baris yang namanya belum ada di Master Guru
 * (select[data-tidak]) di dalam komponen ini.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('imporHonor', function () {
        var akar = null; // elemen akar (closure, bukan data reaktif)
        return {
            init: function () { akar = this.$el; },
            semua: function (v) {
                if (!akar) { return; }
                akar.querySelectorAll('select[data-tidak]').forEach(function (s) { s.value = v; });
            },
        };
    });
});
