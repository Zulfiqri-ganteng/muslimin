/**
 * Kerangka panel admin — langkah AWAL (dimuat SINKRON tepat setelah menu samping di layouts/admin.php, sebelum halaman pertama kali
 * digambar). Mengembalikan posisi gulir menu samping dari halaman sebelumnya (sessionStorage 'sb_gulir', disimpan app.js saat
 * pagehide) dan memastikan menu aktif terlihat. Dulu ini dikerjakan app.js setelah Alpine siap → menu samping sempat tampil dari
 * atas lalu meloncat ("patah-patah" saat pindah menu). Berkas kecil tanpa ketergantungan; siap CSP (bukan skrip sebaris).
 */
(function () {
    'use strict';

    // Pindah halaman beranimasi (@view-transition di admin.css): bila ukuran layar berubah di tengah animasi (jendela diubah, zoom,
    // bilah gulir), peramban membatalkannya dan janji ViewTransition menolak → "Uncaught (in promise) InvalidStateError: Transition
    // was aborted … Viewport size changed" di konsol. Halaman tetap tampil normal; tolakannya ditangkap di sini (harus dipasang
    // sebelum halaman pertama kali digambar → di berkas sinkron ini).
    var redam = function (e) {
        var vt = e && e.viewTransition;
        if (!vt) { return; }
        ['ready', 'finished', 'updateCallbackDone'].forEach(function (k) {
            if (vt[k] && typeof vt[k].catch === 'function') { vt[k].catch(function () {}); }
        });
    };
    window.addEventListener('pagereveal', redam);
    window.addEventListener('pageswap', redam);

    var nav = document.querySelector('#menu-samping nav.sb-gulir');
    if (!nav) { return; }

    try {
        var y = parseInt(window.sessionStorage.getItem('sb_gulir') || '0', 10) || 0;
        if (y > 0) { nav.scrollTop = y; }
    } catch (e) { /* sessionStorage tidak tersedia: mulai dari atas */ }

    // Menu aktif di luar layar → taruh di tengah daftar.
    var aktif = nav.querySelector('a[aria-current="page"]');
    if (aktif && aktif.getClientRects().length > 0) {
        var atas = aktif.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop;
        var tinggi = aktif.offsetHeight;
        if (atas < nav.scrollTop + 8 || atas + tinggi > nav.scrollTop + nav.clientHeight - 8) {
            nav.scrollTop = Math.max(0, atas - (nav.clientHeight - tinggi) / 2);
        }
    }
    nav.setAttribute('data-gulir-siap', '1');
})();
