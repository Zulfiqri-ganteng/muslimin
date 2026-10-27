/**
 * Halaman masuk panel admin (rombak tampilan F2; kartu bergaris cahaya 2026-10-27) — komponen Alpine (Alpine versi CSP, tanpa skrip
 * sebaris). Tombol lihat sandi + peringatan Caps Lock = komponen kit `isianSandi` (ui.js). Di sini hanya:
 *   formMasuk (pada .masuk-kartu):
 *   - setelah formulir dikirim, tombol "Masuk" menjadi "Memproses…" dan nonaktif (cegah klik dua kali), dan kartu diberi data-kirim
 *     (garis cahaya berputar lebih cepat — admin.css "HALAMAN MASUK");
 *   - tombol aktif lagi bila halaman dibuka kembali dari riwayat (tombol Kembali / bfcache) atau bila 20 detik tak ada jawaban;
 *   - gagal masuk: server memberi kartu data-galat (cahaya merah); hilang begitu pengguna mengetik lagi.
 *   temaMasuk: tombol ganti tema di pojok kanan atas.
 * Logika masuk tetap di server (Admin\Auth::attemptLogin); formulir yang isiannya belum lengkap ditahan peramban (required)
 * sebelum peristiwa submit terjadi, jadi tombol tidak pernah terkunci karenanya.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('formMasuk', function () {
        var waktu = null;
        return {
            kirim: false,
            init: function () {
                var self = this;
                window.addEventListener('pageshow', function () {
                    clearTimeout(waktu);
                    self.kirim = false;
                });
            },
            kirimForm: function () {
                var self = this;
                this.hapusGalat();
                this.kirim = true;
                clearTimeout(waktu);
                waktu = setTimeout(function () { self.kirim = false; }, 20000);
            },
            hapusGalat: function () {
                this.$root.removeAttribute('data-galat');
            },
        };
    });

    /* Sama dengan tombol tema di kaki menu samping (app.js gantiTema): Terang → Gelap → Warna Sekolah, langsung tanpa memuat ulang.
     * Disimpan per peramban di cookie tema_mode (dibaca Libraries\Tampilan), jadi panel admin sesudah masuk ikut tema ini.
     * Ikon mengikuti <html data-mode> lewat CSS (.ikon-tema). */
    var URUT_TEMA = ['terang', 'gelap', 'sekolah'];
    var PASANG_TEMA = { terang: ['biru', 'terang'], gelap: ['biru', 'gelap'], sekolah: ['lilac', 'warna'] };

    Alpine.data('temaMasuk', function () {
        return {
            ganti: function () {
                var html = document.documentElement;
                var baru = URUT_TEMA[(URUT_TEMA.indexOf(html.getAttribute('data-mode')) + 1) % URUT_TEMA.length];
                html.setAttribute('data-mode', baru);
                html.setAttribute('data-warna', PASANG_TEMA[baru][0]);
                html.setAttribute('data-sidebar', PASANG_TEMA[baru][1]);
                document.cookie = 'tema_mode=' + baru + '; Path=/; Max-Age=31536000; SameSite=Lax';
            },
        };
    });
});
