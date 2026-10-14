/**
 * Halaman masuk panel admin (rombak tampilan F2) — komponen Alpine `formMasuk` (Alpine versi CSP, tanpa skrip sebaris).
 * Tombol lihat sandi + peringatan Caps Lock = komponen kit `isianSandi` (ui.js). Di sini hanya:
 *   - setelah formulir dikirim, tombol "Masuk" menjadi "Memproses…" dan nonaktif (cegah klik dua kali);
 *   - tombol aktif lagi bila halaman dibuka kembali dari riwayat (tombol Kembali / bfcache) atau bila 20 detik tak ada jawaban.
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
                this.kirim = true;
                clearTimeout(waktu);
                waktu = setTimeout(function () { self.kirim = false; }, 20000);
            },
        };
    });
});
