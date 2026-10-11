/**
 * Dashboard admin (2026-10-12):
 *  1. [data-hitung="123" data-akhiran="%"] — angka berhitung naik saat terlihat di layar.
 *     Angka akhir sudah tertulis di HTML, jadi tanpa JS / "kurangi gerak" tampilannya tetap benar.
 *  2. [data-tip="…"] — satu tooltip bersama (#vizTip) saat disorot kursor ATAU difokus keyboard.
 *     Isi dipasang lewat textContent (nama guru/jurusan = data, bukan HTML).
 */
(function () {
    'use strict';

    var diam = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var angkaId = new Intl.NumberFormat('id-ID');

    // ---------- 1. Angka berhitung naik ----------
    function hitung(el) {
        var akhir = parseInt(el.getAttribute('data-hitung'), 10) || 0;
        var akhiran = el.getAttribute('data-akhiran') || '';
        if (diam || akhir === 0) { return; }
        var lama = Math.min(1400, 600 + akhir * 2);
        var mulai = null;
        function langkah(t) {
            if (mulai === null) { mulai = t; }
            var p = Math.min(1, (t - mulai) / lama);
            var e = 1 - Math.pow(1 - p, 3); // easeOutCubic
            el.textContent = angkaId.format(Math.round(akhir * e)) + akhiran;
            if (p < 1) { window.requestAnimationFrame(langkah); }
        }
        el.textContent = '0' + akhiran;
        window.requestAnimationFrame(langkah);
    }

    var semua = document.querySelectorAll('[data-hitung]');
    if ('IntersectionObserver' in window) {
        var pengamat = new IntersectionObserver(function (entri) {
            entri.forEach(function (en) {
                if (en.isIntersecting) { pengamat.unobserve(en.target); hitung(en.target); }
            });
        }, { threshold: 0.4 });
        semua.forEach(function (el) { pengamat.observe(el); });
    } else {
        semua.forEach(hitung);
    }

    // ---------- 2. Tooltip bersama ----------
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
