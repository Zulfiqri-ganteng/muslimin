/**
 * Ujian → tab Ketidakhadiran — rombak tampilan F7: dulu x-data objek bermetode di kartu ({ jumlah, hitung(){…} }) dan objek
 * sebaris di tiap baris ({ on: … }), tidak cocok untuk Alpine versi CSP. Kini dua komponen di berkas ini:
 *   - takHadirKelas — kartu daftar siswa: penghitung "Tercentang" (dihitung dari kotak centang di dalam kartu).
 *   - takHadirBaris — satu baris siswa: on (dicentang = tidak hadir; nilai awal data-on), alasan & keterangan aktif bila on.
 * Baris memanggil hitung() milik kartu (cakupan Alpine bersarang).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('takHadirKelas', function () {
        var akar = null; // elemen kartu (closure, bukan data reaktif)
        return {
            jumlah: 0,
            init: function () {
                akar = this.$el;
                this.hitung();
            },
            hitung: function () {
                this.jumlah = akar ? akar.querySelectorAll('input[name="tidak_hadir[]"]:checked').length : 0;
            },
        };
    });

    Alpine.data('takHadirBaris', function () {
        return {
            on: false,
            init: function () { this.on = this.$el.getAttribute('data-on') === '1'; },
            hadir: function () { return !this.on; },
        };
    });
});
