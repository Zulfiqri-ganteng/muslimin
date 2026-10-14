/**
 * Pengaturan Sekolah → Tampilan panel admin (rombak tampilan F3): pemilih tema dengan pratinjau langsung.
 * <section x-data="pilihTema" data-warna="…" data-sidebar="…"> — pilihan tema siap pakai (radio name=pilihan, nilai
 * "warna-sidebar" atau "sendiri") + "Atur sendiri" (select tema_warna & tema_sidebar). Setiap perubahan langsung ditulis ke
 * <html data-warna data-sidebar> (seluruh halaman ikut berubah); baru tersimpan untuk semua akun setelah formulir dikirim.
 * Siap Alpine versi CSP: isian hanya nama properti/metode; akar dibaca di init(), tidak lewat this.$el di metode.
 */
(function () {
    'use strict';

    document.addEventListener('alpine:init', function () {
        Alpine.data('pilihTema', function () {
            var html = document.documentElement;
            var asli = { warna: '', sidebar: '', pilihan: '' };
            return {
                pilihan: '',
                warna: '',
                sidebar: '',
                init: function () {
                    var self = this;
                    var akar = this.$el;
                    asli.warna = akar.getAttribute('data-warna') || 'lilac';
                    asli.sidebar = akar.getAttribute('data-sidebar') || 'warna';
                    var cocok = akar.querySelector('input[name="pilihan"][value="' + asli.warna + '-' + asli.sidebar + '"]');
                    asli.pilihan = cocok ? cocok.value : 'sendiri';
                    this.warna = asli.warna;
                    this.sidebar = asli.sidebar;
                    this.pilihan = asli.pilihan;
                    // Tema siap pakai → isi warna & menu samping; "Atur sendiri" memakai pilihan di kotak pilih.
                    this.$watch('pilihan', function (v) {
                        if (v === 'sendiri') { return; }
                        var bagian = String(v).split('-');
                        self.warna = bagian[0];
                        self.sidebar = bagian[1];
                    });
                    this.$watch('warna', function () { self.terapkan(); });
                    this.$watch('sidebar', function () { self.terapkan(); });
                },
                /* Pratinjau langsung di seluruh halaman. */
                terapkan: function () {
                    html.setAttribute('data-warna', this.warna);
                    html.setAttribute('data-sidebar', this.sidebar);
                },
                berubah: function () { return this.warna !== asli.warna || this.sidebar !== asli.sidebar; },
                batal: function () {
                    this.pilihan = asli.pilihan;
                    this.warna = asli.warna;
                    this.sidebar = asli.sidebar;
                    this.terapkan();
                },
            };
        });
    });
})();
