/**
 * Dashboard admin (versi profesional, 2026-10-12): satu tooltip bersama (#vizTip) untuk [data-tip] — muncul saat
 * disorot kursor ATAU difokus keyboard. Isi dipasang lewat textContent (nama guru/jurusan = data, bukan HTML).
 * Semua nilai juga tertulis di halaman, jadi tooltip hanya pelengkap. (Angka berhitung naik sengaja dihapus.)
 */
(function () {
    'use strict';

    var tip = document.getElementById('vizTip');
    if (!tip) { return; }

    function tampil(el, x, y) {
        tip.textContent = el.getAttribute('data-tip');
        tip.classList.remove('hidden');
        var r = tip.getBoundingClientRect();
        var kiri = Math.min(window.innerWidth - r.width - 8, Math.max(8, x - r.width / 2));
        var atas = y - r.height - 12;
        if (atas < 8) { atas = y + 18; }
        tip.style.left = kiri + 'px';
        tip.style.top = atas + 'px';
    }
    function sembunyi() { tip.classList.add('hidden'); }

    document.addEventListener('pointermove', function (e) {
        var el = e.target.closest ? e.target.closest('[data-tip]') : null;
        if (el) { tampil(el, e.clientX, e.clientY); } else { sembunyi(); }
    });
    document.addEventListener('focusin', function (e) {
        var el = e.target.closest ? e.target.closest('[data-tip]') : null;
        if (!el) { return; }
        var r = el.getBoundingClientRect();
        tampil(el, r.left + r.width / 2, r.top);
    });
    document.addEventListener('focusout', sembunyi);
    window.addEventListener('scroll', sembunyi, { passive: true });
})();
