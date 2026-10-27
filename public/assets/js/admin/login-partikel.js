/**
 * Halaman masuk — latar partikel (permintaan user 2026-10-27: "animasi di background … particle gitu"). Kanvas di belakang isi halaman:
 * titik-titik kecil melayang pelan, dua titik yang berdekatan tersambung garis tipis (makin dekat makin jelas), dan saat tetikus
 * bergerak titik di sekitar kursor ikut tersambung ke kursor. Kartu masuk menutupi bagian tengah (latar kartu tidak tembus pandang).
 * Warna dibaca dari CSS (satu sumber dengan garis cahaya kartu, admin.css "HALAMAN MASUK"): titik = --masuk-kepala & --masuk-kepala-2
 * (.masuk-kartu) — jadi ikut tema, gagal masuk (merah), dan ganti tema tanpa memuat ulang (MutationObserver). Formulir dikirim
 * (.masuk-kartu[data-kirim]) → partikel melaju lebih cepat (dipercepat halus, tanpa loncatan).
 * Hemat: jumlah titik mengikuti luas layar (24–80), requestAnimationFrame berhenti sendiri saat tab tersembunyi. "Kurangi gerakan"
 * (Windows) = gambar diam tanpa gerak. Tanpa pustaka, siap CSP (berkas terpisah).
 */
(function () {
    'use strict';

    var kanvas = document.querySelector('.masuk-partikel');
    var kartu = document.querySelector('.masuk-kartu');
    if (!kanvas || !kartu || !kanvas.getContext) { return; }

    var JARAK = 130;        // titik sejauh ini atau kurang tersambung garis (px)
    var JARAK_KURSOR = 170; // titik sejauh ini dari kursor tersambung ke kursor (px)
    var diam = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    var ctx = kanvas.getContext('2d');
    var lebar = 0;
    var tinggi = 0;
    var titik = [];
    var warna = { satu: '#1d4ed8', dua: '#06b6d4' };
    var kursor = null;
    var laju = 1;
    var lajuTuju = 1;
    var terakhir = 0;
    var putaran = null;

    function bacaWarna() {
        var k = getComputedStyle(kartu);
        warna.satu = k.getPropertyValue('--masuk-kepala').trim() || warna.satu;
        warna.dua = k.getPropertyValue('--masuk-kepala-2').trim() || warna.dua;
        lajuTuju = kartu.hasAttribute('data-kirim') ? 4 : 1;
    }

    function ukur() {
        var rasio = Math.min(window.devicePixelRatio || 1, 2);
        lebar = window.innerWidth;
        tinggi = window.innerHeight;
        kanvas.width = Math.round(lebar * rasio);
        kanvas.height = Math.round(tinggi * rasio);
        ctx.setTransform(rasio, 0, 0, rasio, 0, 0);
    }

    function buatTitik() {
        var jumlah = Math.max(24, Math.min(80, Math.round(lebar * tinggi / 15000)));
        titik = [];
        for (var i = 0; i < jumlah; i++) {
            var sudut = Math.random() * Math.PI * 2;
            var cepat = 8 + Math.random() * 14; // px/detik
            titik.push({
                x: Math.random() * lebar,
                y: Math.random() * tinggi,
                vx: Math.cos(sudut) * cepat,
                vy: Math.sin(sudut) * cepat,
                r: 1.3 + Math.random() * 1.2,
                warna: i % 2 ? 'dua' : 'satu',
            });
        }
    }

    function gambar() {
        ctx.clearRect(0, 0, lebar, tinggi);
        ctx.lineWidth = 1;

        // Garis antartitik: alfa turun mengikuti jarak; warna = warna titik pertama.
        for (var i = 0; i < titik.length; i++) {
            var a = titik[i];
            for (var j = i + 1; j < titik.length; j++) {
                var b = titik[j];
                var dx = a.x - b.x;
                var dy = a.y - b.y;
                if (dx > JARAK || dx < -JARAK || dy > JARAK || dy < -JARAK) { continue; }
                var d = Math.sqrt(dx * dx + dy * dy);
                if (d >= JARAK) { continue; }
                ctx.globalAlpha = (1 - d / JARAK) * 0.35;
                ctx.strokeStyle = warna[a.warna];
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }
        }

        // Garis ke kursor (hanya tetikus).
        if (kursor) {
            for (var k = 0; k < titik.length; k++) {
                var t = titik[k];
                var jk = Math.hypot(t.x - kursor.x, t.y - kursor.y);
                if (jk >= JARAK_KURSOR) { continue; }
                ctx.globalAlpha = (1 - jk / JARAK_KURSOR) * 0.5;
                ctx.strokeStyle = warna[t.warna];
                ctx.beginPath();
                ctx.moveTo(t.x, t.y);
                ctx.lineTo(kursor.x, kursor.y);
                ctx.stroke();
            }
        }

        ctx.globalAlpha = 0.75;
        titik.forEach(function (p) {
            ctx.fillStyle = warna[p.warna];
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        });
        ctx.globalAlpha = 1;
    }

    function bingkai(t) {
        var dt = terakhir ? Math.min(0.05, (t - terakhir) / 1000) : 0;
        terakhir = t;
        laju += (lajuTuju - laju) * Math.min(1, dt * 3);
        var tepi = 20;
        titik.forEach(function (p) {
            p.x += p.vx * laju * dt;
            p.y += p.vy * laju * dt;
            // Keluar dari satu sisi → masuk lagi dari sisi seberang.
            if (p.x < -tepi) { p.x = lebar + tepi; } else if (p.x > lebar + tepi) { p.x = -tepi; }
            if (p.y < -tepi) { p.y = tinggi + tepi; } else if (p.y > tinggi + tepi) { p.y = -tepi; }
        });
        gambar();
        putaran = window.requestAnimationFrame(bingkai);
    }

    function mulai() {
        ukur();
        bacaWarna();
        buatTitik();
        gambar();
        if (!diam) { putaran = window.requestAnimationFrame(bingkai); }

        var jeda = null;
        window.addEventListener('resize', function () {
            clearTimeout(jeda);
            jeda = setTimeout(function () {
                ukur();
                buatTitik();
                gambar();
            }, 200);
        });
        if (!diam) {
            window.addEventListener('pointermove', function (e) {
                kursor = e.pointerType === 'mouse' ? { x: e.clientX, y: e.clientY } : null;
            }, { passive: true });
            document.documentElement.addEventListener('mouseleave', function () { kursor = null; });
        }
        // Ganti tema (data-mode), gagal masuk dihapus (data-galat), formulir dikirim (data-kirim) → baca ulang warna & laju.
        if (window.MutationObserver) {
            var amati = new MutationObserver(function () {
                bacaWarna();
                if (diam) { gambar(); }
            });
            amati.observe(document.documentElement, { attributes: true, attributeFilter: ['data-mode', 'data-warna'] });
            amati.observe(kartu, { attributes: true, attributeFilter: ['data-galat', 'data-kirim'] });
        }
        window.addEventListener('pageshow', function (e) {
            if (e.persisted && !diam) {
                window.cancelAnimationFrame(putaran);
                terakhir = 0;
                putaran = window.requestAnimationFrame(bingkai);
            }
        });
    }

    mulai();
})();
