/**
 * Halaman publik — komponen Alpine versi CSP (@alpinejs/csp: tanpa eval, isian atribut hanya nama metode/ekspresi sederhana).
 * Rancangan: docs/RENCANA-TAMPILAN-PUBLIK.md. Jebakan versi CSP: di dalam metode, this.$el = elemen PEMICU — elemen akar
 * komponen disimpan di init().
 *
 *   kepalaPortal  : panel "Daftar layanan" (Esc, Tab berputar, fokus kembali; laci menu HP dihapus 2026-10-20) (tombol kisi, acuan rumah.pendidikan.go.id)
 *   cariPortal    : kotak cari dengan saran (data dari <script type="application/json" id="data-saran-portal">), panah atas/bawah, Enter
 *   bannerGeser   : banner foto geser (scroll-snap) + tombol kiri/kanan, titik, jeda; berhenti saat "kurangi gerakan"
 *   aksesibilitas : ukuran huruf, kontras tinggi, garis bawah tautan — disimpan di cookie, dibaca server (tanpa kedipan)
 * Di luar Alpine: grafik tumbuh saat terlihat, saringan [data-kirim-otomatis] (pilih → formulir langsung dikirim).
 */
// Grafik "Sekolah dalam angka" tumbuh saat bagiannya masuk layar (letaknya di bawah; tanpa ini animasi selesai sebelum terlihat).
// Tanpa IntersectionObserver / JS mati: grafik langsung tampil utuh.
(function () {
    if (!('IntersectionObserver' in window)) { return; }
    var mulai = function () {
        var daftar = document.querySelectorAll('[data-grafik]');
        if (daftar.length === 0) { return; }
        document.documentElement.classList.add('grafik-siap');
        var pengamat = new IntersectionObserver(function (isi) {
            isi.forEach(function (x) {
                if (x.isIntersecting) {
                    x.target.classList.add('terlihat');
                    pengamat.unobserve(x.target);
                }
            });
        }, { threshold: 0.2 });
        Array.prototype.forEach.call(daftar, function (el) { pengamat.observe(el); });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mulai); } else { mulai(); }
})();

// Saringan yang langsung tampil saat dipilih (pilih kelas/guru, tanggal absensi): <select|input data-kirim-otomatis> di dalam
// <form> — pengganti onchange="this.form.submit()" sebaris (siap CSP). Tombol "Tampilkan" tetap ada bila JS mati.
document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.form || !el.hasAttribute || !el.hasAttribute('data-kirim-otomatis') || el.value === '') { return; }
    if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.submit(); }
});

document.addEventListener('alpine:init', function () {
    var normal = function (s) {
        return String(s || '').toLowerCase().replace(/[^0-9a-zÀ-ɏ]+/g, ' ').replace(/\s+/g, ' ').trim();
    };

    Alpine.data('kepalaPortal', function () {
        var akar = null;
        var asal = null; // elemen yang difokus sebelum laci dibuka → dikembalikan saat ditutup
        return {
            laci: false,   // laci menu navigasi (HP)
            daftar: false, // laci "Daftar layanan" (tombol kisi, acuan: sheet daftar ruang)
            init: function () { akar = this.$el; },
            buka: function (nama) {
                var self = this;
                asal = document.activeElement;
                this.laci = nama === 'laci';
                this.daftar = nama === 'daftar';
                document.documentElement.classList.add('kunci-gulir');
                this.$nextTick(function () {
                    var p = akar.querySelector((self.daftar ? '#daftar-layanan' : '#laci-portal') + ' .laci-panel');
                    if (p) { p.focus({ preventScroll: true }); } // tanpa gulir: panel masih di luar layar saat animasi mulai
                });
            },
            bukaLaci: function () { this.buka('laci'); },
            bukaDaftar: function () { this.buka('daftar'); },
            tutupLaci: function () { this.tutupSemua(); },
            // Tab / Shift+Tab berputar di dalam laci selama terbuka.
            kunciTab: function (e) {
                var p = e.currentTarget;
                if (!p) { return; }
                var daftar = Array.prototype.filter.call(
                    p.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'),
                    function (x) { return x.getClientRects().length > 0; }
                );
                if (daftar.length === 0) { return; }
                var awal = daftar[0];
                var akhir = daftar[daftar.length - 1];
                if (e.shiftKey && (document.activeElement === awal || document.activeElement === p)) {
                    e.preventDefault();
                    akhir.focus();
                } else if (!e.shiftKey && document.activeElement === akhir) {
                    e.preventDefault();
                    awal.focus();
                }
            },
            tutupSemua: function () {
                if (!this.laci && !this.daftar) { return; }
                this.laci = false;
                this.daftar = false;
                document.documentElement.classList.remove('kunci-gulir');
                if (asal && asal.focus) { asal.focus({ preventScroll: true }); }
                asal = null;
            },
        };
    });

    Alpine.data('cariPortal', function () {
        var akar = null;
        var daftar = [];
        var dasar = '';
        return {
            q: '',
            hasil: [],
            aktif: -1,
            buka: false,
            init: function () {
                akar = this.$el;
                dasar = akar.getAttribute('action') || '';
                var sumber = document.getElementById('data-saran-portal');
                try { daftar = sumber ? JSON.parse(sumber.textContent || '[]') : []; } catch (e) { daftar = []; }
                var isian = akar.querySelector('input[name="q"]');
                this.q = isian ? isian.value : '';
            },
            get idAktif() { return this.aktif >= 0 && akar ? akar.id + '-saran-' + this.aktif : ''; },
            get alamatCari() { return dasar + '?q=' + encodeURIComponent(this.q); },
            idButir: function (i) { return akar ? akar.id + '-saran-' + i : ''; },
            saring: function () {
                var q = normal(this.q);
                if (q === '') { this.tutup(); return; }
                var kata = q.split(' ');
                this.hasil = daftar.filter(function (s) {
                    var teks = normal(s.judul + ' ' + s.ket + ' ' + s.kata);
                    return kata.every(function (k) { return teks.indexOf(k) !== -1; });
                }).slice(0, 6);
                this.aktif = -1;
                this.buka = true;
            },
            turun: function () {
                if (!this.buka) { this.saring(); return; }
                if (this.hasil.length === 0) { return; }
                this.aktif = (this.aktif + 1) % this.hasil.length;
            },
            naik: function () {
                if (!this.buka || this.hasil.length === 0) { return; }
                this.aktif = this.aktif <= 0 ? this.hasil.length - 1 : this.aktif - 1;
            },
            // Enter: saran yang disorot dibuka langsung; kotak kosong tidak dikirim.
            kirim: function (e) {
                if (this.buka && this.aktif >= 0 && this.hasil[this.aktif]) {
                    e.preventDefault();
                    window.location.href = this.hasil[this.aktif].url;
                    return;
                }
                if (normal(this.q) === '') {
                    e.preventDefault();
                    var isian = akar.querySelector('input[name="q"]');
                    if (isian) { isian.focus(); }
                }
            },
            tutup: function () { this.buka = false; this.aktif = -1; },
            // Fokus pindah ke luar kotak cari (Tab / klik di tempat lain) → saran ditutup.
            tutupNanti: function () {
                var self = this;
                setTimeout(function () {
                    if (akar && !akar.contains(document.activeElement)) { self.tutup(); }
                }, 150);
            },
        };
    });

    Alpine.data('bannerGeser', function () {
        var akar = null;
        var jalur = null;
        var waktu = null;
        var tunda = null;
        var diam = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        return {
            i: 0,
            n: 0,
            main: false,
            get tanpaMain() { return !this.main; },
            init: function () {
                var self = this;
                akar = this.$el;
                jalur = akar.querySelector('.banner-jalur');
                this.n = jalur ? jalur.children.length : 0;
                if (!jalur || this.n < 2) { return; }
                jalur.addEventListener('scroll', function () {
                    clearTimeout(tunda);
                    tunda = setTimeout(function () {
                        var lebar = jalur.clientWidth || 1;
                        self.i = Math.max(0, Math.min(self.n - 1, Math.round(jalur.scrollLeft / lebar)));
                    }, 90);
                }, { passive: true });
                akar.addEventListener('mouseenter', function () { clearInterval(waktu); });
                akar.addEventListener('mouseleave', function () { self.lanjut(); });
                akar.addEventListener('focusin', function () { clearInterval(waktu); });
                akar.addEventListener('focusout', function (e) {
                    if (!e.relatedTarget || !akar.contains(e.relatedTarget)) { self.lanjut(); }
                });
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) { clearInterval(waktu); } else { self.lanjut(); }
                });
                this.main = !diam;
                this.lanjut();
            },
            ke: function (k) {
                if (!jalur || this.n === 0) { return; }
                this.i = (k + this.n) % this.n;
                jalur.scrollTo({ left: this.i * jalur.clientWidth, behavior: diam ? 'auto' : 'smooth' });
            },
            maju: function () { this.ke(this.i + 1); },
            mundur: function () { this.ke(this.i - 1); },
            majuTombol: function () { this.berhenti(); this.maju(); },
            mundurTombol: function () { this.berhenti(); this.mundur(); },
            pilihTitik: function (e) {
                this.berhenti();
                this.ke(Number(e.currentTarget.getAttribute('data-i')) || 0);
            },
            lanjut: function () {
                var self = this;
                clearInterval(waktu);
                if (!this.main || this.n < 2) { return; }
                waktu = setInterval(function () { self.maju(); }, 6500);
            },
            berhenti: function () { this.main = false; clearInterval(waktu); },
            alihMain: function () {
                this.main = !this.main;
                if (this.main) { this.lanjut(); } else { clearInterval(waktu); }
            },
        };
    });

    Alpine.data('aksesibilitas', function () {
        var html = document.documentElement;
        var simpan = function (nama, nilai) {
            document.cookie = nama + '=' + (nilai || '') + '; path=/; max-age=' + (nilai ? 31536000 : 0) + '; SameSite=Lax';
        };
        return {
            buka: false,
            huruf: 'normal',
            kontras: false,
            garis: false,
            init: function () {
                this.huruf = html.getAttribute('data-aks-huruf') || 'normal';
                this.kontras = html.hasAttribute('data-aks-kontras');
                this.garis = html.hasAttribute('data-aks-garis');
            },
            alih: function () { this.buka = !this.buka; },
            tutup: function () { this.buka = false; },
            pilihHuruf: function (e) {
                var nilai = e.currentTarget.getAttribute('data-nilai') || 'normal';
                this.huruf = nilai;
                if (nilai === 'normal') { html.removeAttribute('data-aks-huruf'); } else { html.setAttribute('data-aks-huruf', nilai); }
                simpan('aks_huruf', nilai === 'normal' ? '' : nilai);
            },
            alihKontras: function () {
                this.kontras = !this.kontras;
                if (this.kontras) { html.setAttribute('data-aks-kontras', ''); } else { html.removeAttribute('data-aks-kontras'); }
                simpan('aks_kontras', this.kontras ? '1' : '');
            },
            alihGaris: function () {
                this.garis = !this.garis;
                if (this.garis) { html.setAttribute('data-aks-garis', ''); } else { html.removeAttribute('data-aks-garis'); }
                simpan('aks_garis', this.garis ? '1' : '');
            },
            aturUlang: function () {
                this.huruf = 'normal';
                this.kontras = false;
                this.garis = false;
                html.removeAttribute('data-aks-huruf');
                html.removeAttribute('data-aks-kontras');
                html.removeAttribute('data-aks-garis');
                simpan('aks_huruf', '');
                simpan('aks_kontras', '');
                simpan('aks_garis', '');
            },
        };
    });
});
