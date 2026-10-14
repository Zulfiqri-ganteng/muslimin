/**
 * Pengaturan Sekolah → Tampilan panel admin (rombak tampilan F3 + R2): pemilih MODE (Terang / Gelap / Warna Sekolah).
 * <section x-data="pilihTema" data-mode-awal="…"> berisi radio name=pilihan (nilai = kunci mode) di dalam <form x-ref="formTema">.
 * Klik mode = tampilan langsung berubah (ditulis ke <html data-mode data-warna data-sidebar>) DAN formulir langsung dikirim, jadi
 * pilihan tersimpan untuk semua akun tanpa tombol Simpan (dulu pratinjau + "Simpan tema" — mudah terlewat, lalu tema "kembali"
 * saat pindah halaman). Tanpa JavaScript: tombol Simpan cadangan di <noscript>. Pasangan warna & menu samping tiap mode =
 * Libraries\Tampilan::MODE. Siap Alpine versi CSP: isian hanya nama properti; akar dibaca di init(), tidak lewat this.$el di metode.
 */
(function () {
    'use strict';

    var MODE = { terang: ['biru', 'terang'], gelap: ['biru', 'gelap'], sekolah: ['lilac', 'warna'] };

    document.addEventListener('alpine:init', function () {
        Alpine.data('pilihTema', function () {
            var html = document.documentElement;
            var awal = 'terang';
            return {
                pilihan: '',
                init: function () {
                    var self = this;
                    var m = this.$el.getAttribute('data-mode-awal');
                    awal = Object.prototype.hasOwnProperty.call(MODE, m) ? m : 'terang';
                    this.pilihan = awal;
                    this.$watch('pilihan', function () { self.simpan(); });
                },
                /* Tampilan langsung berubah, lalu pilihan dikirim ke server (layar "Memproses…" dari layout tampil sebentar). */
                simpan: function () {
                    if (!Object.prototype.hasOwnProperty.call(MODE, this.pilihan) || this.pilihan === awal) { return; }
                    html.setAttribute('data-mode', this.pilihan);
                    html.setAttribute('data-warna', MODE[this.pilihan][0]);
                    html.setAttribute('data-sidebar', MODE[this.pilihan][1]);
                    var form = this.$refs.formTema;
                    if (form) {
                        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
                    }
                },
            };
        });
    });
})();
