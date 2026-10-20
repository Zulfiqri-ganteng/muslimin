/**
 * TEMPLATE ALASAN PKL (revisi klien 2026-10-20) — tombol "alasan cepat" di jendela keputusan (views/admin/pkl/_alasan_cepat.php)
 * dan pemilih siswa di jendela "Keluarkan siswa".
 *   [data-alasan-cepat="idTextarea"] [data-alasan-isi="kalimat"] : klik = kalimat ditambahkan ke kotak alasan (kosong → diisi;
 *                                                                  sudah berisi → disambung spasi; kalimat yang sama tidak digandakan);
 *   [data-alasan-kosong]                                          : kosongkan kotak alasan;
 *   form[data-keluar-form]                                        : jendela Keluarkan siswa — tombol simpan mati bila belum ada
 *                                                                  siswa dicentang atau SEMUA dicentang (minimal satu harus tersisa).
 * Tanpa Alpine — cukup delegasi klik/ubah (siap CSP). Batas maxlength kotak alasan dihormati. Server tetap penentu.
 */
(function () {
    'use strict';
    if (window.pklAlasanTerpasang) { return; }
    window.pklAlasanTerpasang = true;

    var alasanKotak = function (el) {
        var wadah = el.closest('[data-alasan-cepat]');
        return wadah ? document.getElementById(wadah.getAttribute('data-alasan-cepat')) : null;
    };

    var alasanTandai = function (kotak) {
        var wadah = document.querySelector('[data-alasan-cepat="' + kotak.id + '"]');
        if (!wadah) { return; }
        wadah.querySelectorAll('[data-alasan-isi]').forEach(function (b) {
            b.setAttribute('aria-pressed', kotak.value.indexOf(b.getAttribute('data-alasan-isi') || '\u0000') !== -1 ? 'true' : 'false');
        });
    };

    var alasanTambah = function (tombol) {
        var kotak = alasanKotak(tombol);
        if (!kotak) { return; }
        var isi = tombol.getAttribute('data-alasan-isi') || '';
        var lama = kotak.value.replace(/\s+$/, '');
        if (isi === '' || lama.indexOf(isi) !== -1) { kotak.focus(); return; }
        var baru = lama === '' ? isi : lama + ' ' + isi;
        var maks = parseInt(kotak.getAttribute('maxlength') || '0', 10);
        if (maks > 0 && baru.length > maks) {
            baru = baru.slice(0, maks);
            if (window.Ui) { window.Ui.toast('Alasan dipotong: maksimal ' + maks + ' huruf.', 'galat'); }
        }
        kotak.value = baru;
        kotak.dispatchEvent(new Event('input', { bubbles: true }));
        alasanTandai(kotak);
        kotak.focus();
        try { kotak.setSelectionRange(baru.length, baru.length); } catch (e) { /* bukan kotak teks */ }
    };

    var alasanKosong = function (tombol) {
        var kotak = alasanKotak(tombol);
        if (!kotak) { return; }
        kotak.value = '';
        kotak.dispatchEvent(new Event('input', { bubbles: true }));
        alasanTandai(kotak);
        kotak.focus();
    };

    // Jendela Keluarkan siswa: minimal satu dicentang, tidak boleh semua.
    var keluarPeriksa = function (form) {
        var semua = form.querySelectorAll('input[name="siswa_ids[]"]');
        var pilih = form.querySelectorAll('input[name="siswa_ids[]"]:checked').length;
        var habis = semua.length > 0 && pilih >= semua.length;
        var pesan = form.querySelector('[data-keluar-semua]');
        if (pesan) { pesan.hidden = !habis; }
        var kirim = form.querySelector('button[type="submit"]');
        if (kirim) { kirim.disabled = pilih === 0 || habis; }
    };

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-alasan-isi]');
        if (t) { e.preventDefault(); alasanTambah(t); return; }
        var k = e.target.closest('[data-alasan-kosong]');
        if (k) { e.preventDefault(); alasanKosong(k); }
    });
    document.addEventListener('change', function (e) {
        var form = e.target.closest('form[data-keluar-form]');
        if (form) { keluarPeriksa(form); }
    });
    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches('textarea[name="catatan"], textarea[name="alasan"]')) { alasanTandai(e.target); }
    });
    var keluarAwal = function () {
        document.querySelectorAll('form[data-keluar-form]').forEach(keluarPeriksa);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', keluarAwal);
    } else {
        keluarAwal();
    }
})();
