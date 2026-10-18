/**
 * Kotak "Kabari siswa lewat WhatsApp" untuk ajuan PKL yang dikembalikan / ditolak (views/admin/pkl/_kabar_keputusan.php).
 *   [data-kabar-tandai="url"]  : tautan wa.me (peramban membukanya di tab baru) + POST di latar untuk mencatat "sudah dikabari";
 *   [data-kabar-salin="pesan"] : salin pesan ke papan klip (cadangan bila WhatsApp tidak terbuka).
 * Tanpa Alpine — cukup delegasi klik (siap CSP). Header token CSRF ditambahkan pembungkus fetch di app.js.
 */
(function () {
    'use strict';

    var kabarMeta = function (nama) {
        var m = document.querySelector('meta[name="' + nama + '"]');
        return m ? m.getAttribute('content') : '';
    };
    var kabarDua = function (n) { return ('0' + n).slice(-2); };
    var kabarWaktu = function (s) {
        var d = new Date(String(s || '').replace(' ', 'T'));
        return isNaN(d) ? String(s || '') : kabarDua(d.getDate()) + '-' + kabarDua(d.getMonth() + 1) + ' ' + kabarDua(d.getHours()) + ':' + kabarDua(d.getMinutes());
    };
    // Token CSRF baru dari balasan POST → <meta> + semua isian token di halaman.
    var kabarCsrf = function (nilai) {
        if (!nilai) { return; }
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) { m.setAttribute('content', nilai); }
        document.querySelectorAll('input[name="' + kabarMeta('csrf-name') + '"]').forEach(function (i) { i.value = nilai; });
    };

    var kabarTandai = function (a) {
        var body = new URLSearchParams();
        body.set(kabarMeta('csrf-name'), kabarMeta('csrf-token'));
        fetch(a.getAttribute('data-kabar-tandai'), {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'Balasan server tidak terbaca.' }; });
        }).then(function (j) {
            kabarCsrf(j.csrf);
            if (!j.ok) {
                if (window.Ui) { window.Ui.toast(j.message || 'Gagal mencatat "sudah dikabari".', 'galat'); }
                return;
            }
            var baris = a.closest('[data-kabar-baris]');
            var status = baris && baris.querySelector('[data-kabar-status]');
            if (status) {
                status.querySelector('[data-kabar-status-teks]').textContent = 'Sudah dikabari ' + kabarWaktu(j.waktu) + (j.oleh ? ' oleh ' + j.oleh : '');
                status.hidden = false;
            }
            var label = a.querySelector('[data-kabar-label]');
            if (label) { label.textContent = 'Kirim ulang'; }
        }).catch(function () {
            if (window.Ui) { window.Ui.toast('Tidak bisa menghubungi server untuk mencatat "sudah dikabari".', 'galat'); }
        });
    };

    var kabarSalin = function (b) {
        if (!navigator.clipboard) { return; }
        navigator.clipboard.writeText(b.getAttribute('data-kabar-salin') || '').then(function () {
            var label = b.querySelector('[data-kabar-label]');
            if (!label) { return; }
            label.textContent = 'Tersalin';
            setTimeout(function () { label.textContent = 'Salin pesan'; }, 1800);
        });
    };

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) { return; }
        var a = e.target.closest('[data-kabar-tandai]');
        if (a) { kabarTandai(a); return; } // tautan tetap dibuka peramban (tanpa preventDefault)
        var b = e.target.closest('[data-kabar-salin]');
        if (b) { kabarSalin(b); }
    });
})();
