/**
 * Pengaturan Sekolah → Tampilan panel admin (rombak tampilan F3 + R2): pemilih MODE (Terang / Gelap / Warna Sekolah) dengan pratinjau
 * langsung. <section x-data="pilihTema" data-mode-awal="…"> berisi radio name=pilihan (nilai = kunci mode). Setiap pilihan langsung
 * ditulis ke <html data-mode data-warna data-sidebar> (seluruh halaman ikut berubah); baru tersimpan untuk semua akun setelah
 * formulir dikirim. Pasangan warna & menu samping tiap mode = Libraries\Tampilan::MODE.
 * Siap Alpine versi CSP: isian hanya nama properti/metode; akar dibaca di init(), tidak lewat this.$el di metode.
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
                    this.$watch('pilihan', function () { self.terapkan(); });
                },
                /* Pratinjau langsung di seluruh halaman. */
                terapkan: function () {
                    if (!Object.prototype.hasOwnProperty.call(MODE, this.pilihan)) { return; }
                    html.setAttribute('data-mode', this.pilihan);
                    html.setAttribute('data-warna', MODE[this.pilihan][0]);
                    html.setAttribute('data-sidebar', MODE[this.pilihan][1]);
                },
                berubah: function () { return this.pilihan !== awal; },
                batal: function () {
                    this.pilihan = awal;
                    this.terapkan();
                },
            };
        });
    });
})();
