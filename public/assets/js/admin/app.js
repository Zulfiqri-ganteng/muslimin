/**
 * =====================================================================
 * Panel Admin — JavaScript global (dipisah dari view, tanpa inline).
 * =====================================================================
 * Dimuat di layouts/admin.php SEBELUM Alpine.js sehingga semua komponen
 * terdaftar saat event `alpine:init` berjalan.
 *
 * Isi:
 *  1. Delegasi global : [data-confirm], [data-autosubmit], [data-lihat-saja] (menu "Lihat saja", pesan lewat toast kit)
 *  2. adminLayout     : laci menu (HP), ciutkan menu (cookie sb_ciut), overlay loading
 *     sbMenu / sbGrup : cari menu, grup menu lipat (cookie sb_grup)
 *  3. helpCard        : kartu panduan halaman (default TERTUTUP)
 *  4. masterList      : pabrik komponen halaman daftar master data
 *  5. importPreview   : editor pratinjau impor Excel
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * 1. Delegasi global — pengganti inline handler.
     * ------------------------------------------------------------- */

    // Halaman "Lihat saja" (<main data-lihat-saja>, menu yang diberi Admin level Lihat): formulir POST di isi halaman tidak
    // bisa dikirim — penjaga rute pasti menolaknya. Tombol kirim diredupkan saat halaman dimuat; formulir/tautan POST yang
    // muncul belakangan (Alpine) dicegat saat dikirim. Formulir bertanda data-lihat-boleh dikecualikan.
    function lihatSaja(el) {
        var m = el && el.closest ? el.closest('[data-lihat-saja]') : null;
        return m !== null && !(el.closest && el.closest('[data-lihat-boleh]'));
    }
    function isPost(form) {
        return String(form.getAttribute('method') || 'get').toLowerCase() === 'post';
    }
    var PESAN_LIHAT = 'Mode lihat saja: akun Anda hanya bisa melihat halaman ini, tidak bisa mengubah data. Admin bisa mengubahnya di Sistem → Hak Akses.';
    // Pesan lewat toast kit (ui.js) bila ada; halaman tanpa kit tetap memakai alert.
    var beritahuLihat = function () {
        if (window.Ui && window.Ui.toast) { window.Ui.toast(PESAN_LIHAT, 'awas', 6000); } else { window.alert(PESAN_LIHAT); }
    };
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f && f.tagName === 'FORM' && isPost(f) && lihatSaja(f)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            beritahuLihat();
        }
    }, true);
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-method="post"]');
        if (a && lihatSaja(a)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            beritahuLihat();
        }
    }, true);
    document.addEventListener('DOMContentLoaded', function () {
        var m = document.querySelector('[data-lihat-saja]');
        if (!m) { return; }
        Array.prototype.forEach.call(m.querySelectorAll('form'), function (f) {
            if (!isPost(f) || !lihatSaja(f)) { return; }
            Array.prototype.forEach.call(f.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'), function (b) {
                b.disabled = true;
                b.title = 'Mode lihat saja';
                b.classList.add('opacity-50', 'cursor-not-allowed');
            });
        });
    });

    // <a data-confirm="Yakin?"> — batalkan klik bila user menolak. Bila halaman memuat dialog konfirmasi kit (ui.js +
    // ui_konfirmasi(), rombak tampilan F1/F3), dialog itulah yang bertanya — di sini dilewati agar tidak bertanya dua kali.
    document.addEventListener('click', function (e) {
        if (window.Ui && window.Ui.adaKonfirmasi()) { return; }
        var el = e.target.closest('[data-confirm]');
        if (el && !window.confirm(el.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

    // Token CSRF dari <meta> di layout admin (filter csrf aktif di semua rute admin).
    function meta(nama) {
        var m = document.querySelector('meta[name="' + nama + '"]');
        return m ? m.getAttribute('content') : '';
    }

    // <a data-method="post" href="…"> — aksi hapus dikirim sebagai POST + token, bukan
    // GET (tautan GET bisa dipicu dari situs lain). Bila ada data-confirm, pertanyaannya
    // sudah dijawab "ya" oleh delegasi di atas (fase capture) sebelum sampai ke sini.
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-method="post"]');
        if (!a || e.defaultPrevented) { return; }
        e.preventDefault();
        var form = document.createElement('form');
        form.method = 'post';
        form.action = a.getAttribute('href');
        form.style.display = 'none';
        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = meta('csrf-name');
        token.value = meta('csrf-token');
        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    });

    // fetch() POST/PUT/PATCH/DELETE ke situs sendiri otomatis membawa header token.
    if (window.fetch && meta('csrf-header')) {
        var fetchAsli = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var req = (typeof Request !== 'undefined' && input instanceof Request) ? input : null;
            var metode = String(init.method || (req ? req.method : 'GET')).toUpperCase();
            var alamat = new URL(req ? req.url : String(input), window.location.href);
            if (metode !== 'GET' && metode !== 'HEAD' && alamat.origin === window.location.origin) {
                var h = new Headers(init.headers || (req ? req.headers : undefined));
                if (!h.has(meta('csrf-header'))) { h.set(meta('csrf-header'), meta('csrf-token')); }
                init = Object.assign({}, init, { headers: h });
            }
            return fetchAsli.call(window, input, init);
        };
    }

    // <select data-autosubmit> — kirim form induk saat nilai berubah.
    document.addEventListener('change', function (e) {
        var el = e.target.closest('[data-autosubmit]');
        if (el && el.form) {
            if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.submit(); }
        }
    });

    // <a data-export-filter="idForm"> — Export memakai pilihan filter yang TAMPIL di
    // layar saat diklik (bukan filter saat halaman dimuat), jadi pilih "X TKJ 2"
    // lalu langsung klik Export tetap mengunduh X TKJ 2 saja.
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-export-filter]');
        var form = a && document.getElementById(a.getAttribute('data-export-filter'));
        if (!form) { return; }
        var url = new URL(a.getAttribute('href'), window.location.href);
        form.querySelectorAll('select[data-filter]').forEach(function (s) {
            if (s.value) { url.searchParams.set(s.name, s.value); } else { url.searchParams.delete(s.name); }
        });
        e.preventDefault();
        window.location.href = url.toString();
    });

    /** Baca atribut data-* berisi JSON dengan aman. */
    function readJson(root, name, fallback) {
        var raw = root.getAttribute('data-' + name);
        if (!raw) { return fallback; }
        try { return JSON.parse(raw); } catch (err) { return fallback; }
    }
    window.readJson = readJson;

    /* Cookie status menu samping (sb_ciut, sb_grup) — dibaca server (Libraries\Tampilan) agar menu tak berkedip saat pindah halaman. */
    var bacaKue = function (nama) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + nama + '=([^;]*)'));
        if (!m) { return ''; }
        try { return decodeURIComponent(m[1]); } catch (err) { return ''; }
    };
    var tulisKue = function (nama, nilai) {
        document.cookie = nama + '=' + encodeURIComponent(nilai) + '; Path=/; Max-Age=' + (nilai === '' ? 0 : 31536000) + '; SameSite=Lax';
    };
    var DESKTOP = window.matchMedia ? window.matchMedia('(min-width: 1024px)') : null;

    /* ---------------------------------------------------------------
     * (2026-10-18) Menu samping MENCIUT (desktop, <html data-ciut>): sorot / fokus sebuah ikon → nama menunya melayang di samping;
     * ikon GRUP → daftar isi grupnya bisa langsung diklik tanpa melebarkan menu (klik ikon grup tetap = lebarkan + buka grup).
     * Satu panel #sb-melayang di <body> (keluar dari wadah bergulir yang memotong isi), diisi SALINAN tautan grup (elemen baru tanpa
     * atribut Alpine). Posisi lewat CSSOM (el.style) — diizinkan CSP, beda dengan atribut style= di HTML.
     * ------------------------------------------------------------- */
    function pasangMenuMelayang() {
        var sb = document.getElementById('menu-samping');
        if (!sb || document.getElementById('sb-melayang')) { return; }
        var panel = document.createElement('div');
        panel.id = 'sb-melayang';
        panel.className = 'sb-melayang';
        panel.hidden = true;
        document.body.appendChild(panel);
        var sumber = null;
        var timer = null;

        var aktif = function () {
            return document.documentElement.hasAttribute('data-ciut') && (DESKTOP ? DESKTOP.matches : true);
        };
        // Selama panel tampil, title bawaan peramban disimpan dulu supaya tak muncul tooltip ganda.
        var pulihkanJudul = function () {
            if (sumber && sumber.hasAttribute('data-judul-asli')) {
                sumber.setAttribute('title', sumber.getAttribute('data-judul-asli'));
                sumber.removeAttribute('data-judul-asli');
            }
        };
        var sembunyi = function () {
            clearTimeout(timer);
            panel.hidden = true;
            panel.textContent = '';
            pulihkanJudul();
            sumber = null;
        };
        var tunda = function () {
            clearTimeout(timer);
            timer = setTimeout(sembunyi, 180);
        };
        var teksDari = function (el) {
            var l = el.querySelector('.sb-label');
            return String((l ? l.textContent : (el.getAttribute('data-judul-asli') || el.getAttribute('title'))) || '').trim();
        };
        var tampil = function (el) {
            clearTimeout(timer);
            if (sumber === el && !panel.hidden) { return; }
            pulihkanJudul();
            sumber = el;
            var judulAsli = el.getAttribute('title');
            if (judulAsli) {
                el.setAttribute('data-judul-asli', judulAsli);
                el.removeAttribute('title');
            }
            panel.textContent = '';
            var grup = el.classList.contains('sb-grup-tombol') ? el.closest('.sb-grup') : null;
            panel.classList.toggle('sb-melayang-grup', !!grup);
            var judul = document.createElement('p');
            judul.className = 'sb-melayang-judul';
            judul.textContent = teksDari(el);
            panel.appendChild(judul);
            if (grup) {
                var ul = document.createElement('ul');
                Array.prototype.forEach.call(grup.querySelectorAll('.sb-anak-daftar > li'), function (li) {
                    var asal = li.querySelector('.sb-item');
                    if (!asal) { return; }
                    var baru = document.createElement(asal.tagName === 'A' ? 'a' : 'span');
                    baru.className = 'sb-melayang-item' + (asal.tagName === 'A' ? '' : ' sb-melayang-segera');
                    baru.textContent = teksDari(asal) + (asal.tagName === 'A' ? '' : ' (segera)');
                    if (asal.tagName === 'A') {
                        baru.href = asal.href;
                        if (asal.getAttribute('aria-current')) { baru.setAttribute('aria-current', 'page'); }
                    }
                    var item = document.createElement('li');
                    item.appendChild(baru);
                    ul.appendChild(item);
                });
                panel.appendChild(ul);
            }
            panel.hidden = false;
            var r = el.getBoundingClientRect();
            var tinggi = panel.offsetHeight;
            var atas = grup ? r.top : r.top + (r.height - tinggi) / 2;
            panel.style.top = Math.max(72, Math.min(atas, window.innerHeight - tinggi - 8)) + 'px';
            panel.style.left = (r.right + 10) + 'px';
        };
        var pemicu = function (t) {
            var el = t && t.closest ? t.closest('.sb-item, .sb-bawah-tombol') : null;
            return el && sb.contains(el) ? el : null;
        };

        sb.addEventListener('mouseover', function (e) {
            var el = aktif() ? pemicu(e.target) : null;
            if (el) { tampil(el); }
        });
        sb.addEventListener('mouseout', function (e) {
            var ke = e.relatedTarget;
            if (ke && (panel.contains(ke) || pemicu(ke))) { return; }
            tunda();
        });
        sb.addEventListener('focusin', function (e) {
            var el = aktif() ? pemicu(e.target) : null;
            if (el) { tampil(el); } else { sembunyi(); }
        });
        sb.addEventListener('focusout', function (e) {
            if (!e.relatedTarget || !panel.contains(e.relatedTarget)) { tunda(); }
        });
        sb.addEventListener('click', sembunyi);
        panel.addEventListener('mouseover', function () { clearTimeout(timer); });
        panel.addEventListener('mouseleave', tunda);
        var gulir = sb.querySelector('.sb-gulir');
        if (gulir) { gulir.addEventListener('scroll', sembunyi, { passive: true }); }
        window.addEventListener('resize', sembunyi);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { sembunyi(); } });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', pasangMenuMelayang); } else { pasangMenuMelayang(); }

    /* ---------------------------------------------------------------
     * Pilih baris & hapus massal (SEMUA halaman master) — plain JS
     * global tanpa ketergantungan komponen halaman:
     *   .js-check-all   : checkbox header (pilih semua baris)
     *   .row-check      : checkbox tiap baris
     *   [data-bulk]     : tombol hapus ("selected" | "all")
     *   .js-bulk-form   : form tersembunyi tujuan POST
     *                     (data-label = nama entitas, data-warn = peringatan ekstra)
     * ------------------------------------------------------------- */
    function updateBulkUI() {
        var total   = document.querySelectorAll('.row-check').length;
        var checked = document.querySelectorAll('.row-check:checked').length;
        var allOn   = total > 0 && checked === total;

        document.querySelectorAll('.js-bulk-count').forEach(function (n) { n.textContent = checked; });
        // Atribut hidden (bukan kelas): tombolnya ber-kelas inline-flex, yang di Tailwind v4 menang atas kelas "hidden".
        document.querySelectorAll('[data-bulk="selected"]').forEach(function (b) { b.hidden = checked === 0; });
        document.querySelectorAll('[data-bulk="all"]').forEach(function (b) { b.hidden = !allOn; });

        var master = document.querySelector('.js-check-all');
        if (master) { master.checked = allOn; }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('js-check-all')) {
            var on = e.target.checked;
            document.querySelectorAll('.row-check').forEach(function (c) { c.checked = on; });
            updateBulkUI();
        } else if (e.target.classList && e.target.classList.contains('row-check')) {
            updateBulkUI();
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-bulk]');
        if (!btn) { return; }
        var form = document.querySelector('.js-bulk-form');
        if (!form) { return; }

        var mode  = btn.getAttribute('data-bulk');
        var label = form.getAttribute('data-label') || 'data';
        var warn  = form.getAttribute('data-warn') || '';

        form.querySelectorAll('input[name="ids[]"]').forEach(function (n) { n.remove(); });

        // (F5) Pertanyaan lewat dialog konfirmasi kit (merah) bila tersedia; bila tidak, confirm() bawaan peramban.
        var tanya = function (judul, pesan, ya) {
            if (window.Ui && window.Ui.adaKonfirmasi()) {
                return window.Ui.konfirmasi({ judul: judul, pesan: pesan, ya: ya, batal: 'Batal', jenis: 'bahaya' });
            }
            return Promise.resolve(window.confirm(pesan));
        };
        var checked = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked'));
        var janji;
        if (mode === 'all') {
            janji = tanya('Hapus semua ' + label + '?', 'Tindakan ini menghapus SELURUH daftar dan tidak dapat dibatalkan.' + warn, 'Ya, hapus semua');
        } else {
            if (checked.length === 0) {
                if (window.Ui && window.Ui.toast) { window.Ui.toast('Centang dulu data yang ingin dihapus.', 'awas'); } else { window.alert('Centang dulu data yang ingin dihapus.'); }
                return;
            }
            janji = tanya('Hapus ' + checked.length + ' ' + label + '?', 'Hapus ' + checked.length + ' ' + label + ' terpilih? Tindakan ini tidak dapat dibatalkan.' + warn, 'Ya, hapus');
        }
        janji.then(function (setuju) {
            if (!setuju) { return; }
            if (mode !== 'all') {
                checked.forEach(function (c) {
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = 'ids[]';
                    i.value = c.value;
                    form.appendChild(i);
                });
            }
            form.querySelector('[name=mode]').value = mode;
            form.submit();
        });
    });

    /* ---------------------------------------------------------------
     * 4. masterList — pabrik komponen halaman daftar master data.
     *    Konfigurasi lewat atribut data-* pada elemen root:
     *      data-base     : URL dasar modul (untuk aksi simpan/edit)
     *      data-defaults : JSON nilai awal form tambah
     *      data-entity   : label entitas utk teks konfirmasi ("guru", dst.)
     *    Perilaku khusus per halaman dioper lewat `overrides`.
     * ------------------------------------------------------------- */
    window.masterList = function (overrides) {
        var akar = null; // elemen root komponen (disimpan di init; di metode Alpine versi CSP this.$el = elemen pemicu)
        // (F5) Fokus ke kolom isian pertama dialog yang baru terbuka.
        var fokusIsian = function () {
            setTimeout(function () {
                var el = akar ? akar.querySelector('.dialog[data-buka] .dialog-isi :is(input, select, textarea):not([type="hidden"]):not([disabled])') : null;
                if (el) { el.focus(); }
            }, 60);
        };
        var base = {
            open: false,
            importOpen: false,
            mode: 'add',
            actionUrl: '',
            base: '',
            entity: 'data',
            defaults: {},
            form: {},
            bulkSelected: 0,
            allChecked: false, // semua baris tercentang → tombol "Hapus Semua" muncul

            init: function () {
                var self = this;
                akar          = this.$el;
                this.base     = this.$el.getAttribute('data-base') || '';
                this.entity   = this.$el.getAttribute('data-entity') || 'data';
                this.defaults = readJson(this.$el, 'defaults', {});
                this.form     = Object.assign({}, this.defaults);
                // (F5) Tombol Ubah kit (ui_crud_aksi): <button data-ubah data-row="JSON"> — delegasi, tanpa isian Alpine (siap CSP).
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-ubah]') : null;
                    if (b && akar.contains(b)) { self.openEditEl(b); }
                });
                if (this.onInit) { this.onInit(); }
            },

            /* ---- modal tambah / edit ---- */
            openAdd: function () {
                this.mode      = 'add';
                this.form      = Object.assign({}, this.defaults);
                this.actionUrl = this.base;
                this.open      = true;
                fokusIsian();
            },
            tutupForm: function () { this.open = false; },
            // Untuk dialog tambahan halaman (mis. Atur jabatan/Atur guru): fokus ke isian pertama → Esc & Tab langsung bekerja.
            fokusDialog: function () { fokusIsian(); },
            bukaImpor: function () { this.importOpen = true; fokusIsian(); },
            tutupImpor: function () { this.importOpen = false; },
            // Dipanggil dari tombol edit: data baris ada di atribut data-row (JSON).
            openEditEl: function (el) {
                this.openEdit(JSON.parse(el.getAttribute('data-row') || '{}'));
            },
            openEdit: function (r) {
                this.mode      = 'edit';
                this.form      = this.mapEdit(r);
                this.actionUrl = this.base + '/' + r.id;
                this.open      = true;
                fokusIsian();
            },
            /** Petakan baris DB → form. Override bila perlu konversi khusus. */
            mapEdit: function (r) {
                var f = {};
                var d = this.defaults;
                Object.keys(d).forEach(function (k) {
                    f[k] = (r[k] === null || r[k] === undefined) ? d[k] : r[k];
                });
                return f;
            },

            /* ---- pilih baris & hapus massal ---- */
            refresh: function () {
                var all     = this.$el.querySelectorAll('.row-check').length;
                var checked = this.$el.querySelectorAll('.row-check:checked').length;
                this.bulkSelected = checked;
                this.allChecked   = all > 0 && checked === all;
            },
            toggleAll: function (e) {
                this.$el.querySelectorAll('.row-check').forEach(function (c) { c.checked = e.target.checked; });
                this.refresh();
            },
            submitBulk: function (mode) {
                var form = this.$refs.bulkForm;
                form.querySelectorAll('input[name="ids[]"]').forEach(function (n) { n.remove(); });

                if (mode === 'all') {
                    if (!window.confirm('HAPUS SEMUA ' + this.entity + '? Tindakan ini menghapus seluruh daftar dan tidak dapat dibatalkan.')) { return; }
                    form.querySelector('[name=mode]').value = 'all';
                } else {
                    var checked = Array.prototype.slice.call(this.$el.querySelectorAll('.row-check:checked'));
                    if (checked.length === 0) { window.alert('Centang dulu data yang ingin dihapus.'); return; }
                    if (!window.confirm('Hapus ' + checked.length + ' ' + this.entity + ' terpilih?')) { return; }
                    checked.forEach(function (c) {
                        var i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = 'ids[]';
                        i.value = c.value;
                        form.appendChild(i);
                    });
                    form.querySelector('[name=mode]').value = 'selected';
                }
                form.submit();
            },
        };

        return Object.assign(base, overrides || {});
    };

    /* ---------------------------------------------------------------
     * Registrasi komponen Alpine.
     * ------------------------------------------------------------- */
    document.addEventListener('alpine:init', function () {

        /* 2. Layout admin (layouts/admin.php, rombak F3): laci menu di HP, ciutkan menu di desktop (<html data-ciut> + cookie
         *    sb_ciut), layar "Memproses…" saat formulir dikirim. Siap Alpine versi CSP: metode tanpa argumen rumit, dan
         *    `this.$el` tidak dipakai di metode (di versi CSP itu = elemen pemicu). */
        Alpine.data('adminLayout', function () {
            return {
                sidebar: false,     // laci menu terbuka (HP/tablet)
                collapsed: false,   // menu menciut jadi ikon (desktop)
                desktop: DESKTOP ? DESKTOP.matches : true,
                cari: '',           // teks cari menu — satu nilai untuk kotak di bilah atas (desktop) & di laci (HP)
                loading: false,
                pesan: 'Memproses…',
                init: function () {
                    var self = this;
                    this.collapsed = document.documentElement.hasAttribute('data-ciut');
                    // Mencari saat menu menciut: lebarkan dulu supaya hasilnya terbaca (label tampil).
                    this.$watch('cari', function (v) {
                        if (String(v || '').trim() !== '' && self.sempit()) { self.alihCiut(); }
                    });
                    // Pindahkan pilihan lama (localStorage sb_collapsed, sebelum F3) ke cookie, sekali saja.
                    try {
                        var lama = localStorage.getItem('sb_collapsed');
                        if (lama !== null) {
                            localStorage.removeItem('sb_collapsed');
                            if (lama === 'true' && !this.collapsed) { this.alihCiut(); }
                        }
                    } catch (err) { /* penyimpanan diblokir: abaikan */ }
                    if (DESKTOP) {
                        var ubah = function (e) {
                            self.desktop = e.matches;
                            if (e.matches) { self.sidebar = false; }
                        };
                        if (DESKTOP.addEventListener) { DESKTOP.addEventListener('change', ubah); } else { DESKTOP.addListener(ubah); }
                    }
                    document.addEventListener('submit', function (e) {
                        var f = e.target;
                        // Formulir yang dibatalkan penangan lain (@submit.prevent, validasi, lihat saja) atau dibuka di tab baru
                        // tidak memuat ulang halaman → layar Memproses tak boleh muncul (bisa menutupi halaman selamanya).
                        if (!f || f.tagName !== 'FORM' || e.defaultPrevented || f.hasAttribute('data-noload') || f.target === '_blank') { return; }
                        if (f.hasAttribute('data-unduh')) { self.mulaiUnduh(f); return; }
                        self.pesan = 'Memproses…';
                        self.loading = true;
                    });
                    window.addEventListener('pageshow', function () { self.loading = false; });
                    this.$nextTick(function () { self.aturGulirMenu(); });
                },
                alihLaci: function () {
                    this.sidebar = !this.sidebar;
                    if (this.sidebar) {
                        // Fokus ke kotak cari menu supaya bisa langsung mengetik / Tab ke menu.
                        this.$nextTick(function () {
                            var cari = document.querySelector('#menu-samping .sb-cari');
                            if (cari) { cari.focus({ preventScroll: true }); }
                        });
                    }
                },
                tutupLaci: function () { this.sidebar = false; },
                alihCiut: function () {
                    this.collapsed = !this.collapsed;
                    if (this.collapsed) {
                        document.documentElement.setAttribute('data-ciut', '');
                    } else {
                        document.documentElement.removeAttribute('data-ciut');
                    }
                    tulisKue('sb_ciut', this.collapsed ? '1' : '');
                },
                /* Menu samping sedang tampil sebagai deret ikon (desktop + diciutkan): isi grup disembunyikan, hanya ikonnya. */
                sempit: function () { return this.collapsed && this.desktop; },
                /* Enter di kotak cari menu → buka menu pertama yang cocok (yang sedang tampil). */
                bukaHasilPertama: function () {
                    var semua = document.querySelectorAll('#menu-samping a.sb-item[href]');
                    for (var i = 0; i < semua.length; i++) {
                        if (semua[i].getClientRects().length > 0) {
                            if (semua[i].target === '_blank') { window.open(semua[i].href, '_blank', 'noopener'); } else { window.location.href = semua[i].href; }
                            return;
                        }
                    }
                },
                /* Daftar menu sidebar panjang (±60 item) dan tiap halaman baru memuatnya dari atas, sehingga
                 * menu yang sedang dibuka sering tersembunyi di bawah. Posisi gulir diingat per tab
                 * (sessionStorage) dan menu aktif dipastikan terlihat; bila di luar layar ditaruh di tengah.
                 * (F5) Pengembaliannya kini dikerjakan kerangka-awal.js SEBELUM halaman digambar (tanpa loncatan);
                 * di sini cukup menyimpan posisi saat pindah halaman — cadangan lama hanya bila skrip awal tak jalan. */
                aturGulirMenu: function () {
                    var nav = document.querySelector('aside nav[aria-label="Menu utama"]');
                    if (!nav) { return; }
                    var kunci = 'sb_gulir';
                    window.addEventListener('pagehide', function () {
                        try { sessionStorage.setItem(kunci, String(nav.scrollTop)); } catch (e) {}
                    });
                    if (nav.hasAttribute('data-gulir-siap')) { return; }
                    try { nav.scrollTop = parseInt(sessionStorage.getItem(kunci) || '0', 10) || 0; } catch (e) {}
                    var aktif = nav.querySelector('a[aria-current="page"]');
                    if (!aktif) { return; }
                    var atas = aktif.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop;
                    var tinggi = aktif.offsetHeight;
                    if (atas < nav.scrollTop + 8 || atas + tinggi > nav.scrollTop + nav.clientHeight - 8) {
                        nav.scrollTop = Math.max(0, atas - (nav.clientHeight - tinggi) / 2);
                    }
                },
                /* Form yang hasilnya BERKAS unduhan (halaman tidak dimuat ulang, jadi 'pageshow' tak pernah
                 * terjadi). Server menyetel cookie `unduh_selesai=<token>` pada respons berkas; layar
                 * loading ditutup begitu cookie itu terbaca (atau paling lama 2 menit). Bila server
                 * malah mengalihkan (mis. pesan galat), halaman dimuat ulang dan layar hilang sendiri. */
                mulaiUnduh: function (form) {
                    var self = this;
                    var token = String(Date.now()) + String(Math.floor(Math.random() * 1000000));
                    var isian = form.querySelector('input[name="unduh_token"]');
                    if (!isian) {
                        isian = document.createElement('input');
                        isian.type = 'hidden';
                        isian.name = 'unduh_token';
                        form.appendChild(isian);
                    }
                    isian.value = token;
                    self.pesan = 'Menyiapkan berkas surat…';
                    self.loading = true;
                    var batas = Date.now() + 120000;
                    var adaWa = form.hasAttribute('data-pkl-wa');
                    /* Surat Balasan: id surat (data-surat-wa) atau jenis (unduh massal, data-surat-wa-jenis) → surat-wa.js. */
                    var waSurat = form.getAttribute('data-surat-wa');
                    var waJenis = form.getAttribute('data-surat-wa-jenis');
                    var timer = setInterval(function () {
                        var selesai = document.cookie.split('; ').indexOf('unduh_selesai=' + token) !== -1;
                        if (selesai || Date.now() > batas) {
                            clearInterval(timer);
                            self.loading = false;
                            document.cookie = 'unduh_selesai=; Max-Age=0; Path=/';
                            /* Unduhan surat PKL selesai → halaman boleh menawarkan "Kabari lewat WhatsApp" (pkl-unduh.js). */
                            if (selesai && adaWa) {
                                window.dispatchEvent(new CustomEvent('pkl:unduh-selesai', { detail: { token: token } }));
                            }
                            /* Surat sekolah yang punya kabar "siap diambil" → tawarkan "Kabari siswa lewat WhatsApp". */
                            if (selesai && (waSurat || waJenis)) {
                                window.dispatchEvent(new CustomEvent('surat:unduh-selesai', { detail: waSurat ? { id: waSurat } : { jenis: waJenis } }));
                            }
                        }
                    }, 300);
                },
            };
        });

        /* 2a. Cari menu (<nav x-data="sbMenu" data-label='["…"]'>): item tampil bila teks cari kosong atau cocok dengan labelnya.
         *     Nilai `cari` milik adminLayout (induk) — dipakai bersama kotak cari di bilah atas & di laci HP. */
        Alpine.data('sbMenu', function () {
            var semua = [];
            return {
                init: function () { semua = readJson(this.$el, 'label', []); },
                kata: function () { return String(this.cari || '').trim().toLowerCase(); },
                sesuai: function (label) {
                    var q = this.kata();
                    return q === '' || String(label).indexOf(q) !== -1;
                },
                adaHasil: function () {
                    var q = this.kata();
                    return q === '' || semua.some(function (l) { return String(l).indexOf(q) !== -1; });
                },
            };
        });

        /* 2c. Lonceng bilah atas (<div x-data="lonceng" data-url data-awal='{"total":3,"butir":[…]}'>, di dalam x-data="dropdown"
         *     milik kit — buka/alih/tutup diambil dari induk). Isi = pekerjaan yang masih menunggu (Libraries\Pemberitahuan): awalnya
         *     dari server, lalu dimuat ulang tiap 2 menit selama tab terlihat, saat tab kembali dilihat, dan saat lonceng dibuka. */
        Alpine.data('lonceng', function () {
            var url = '';
            var terakhir = 0;
            return {
                total: 0,
                butir: [],
                init: function () {
                    var self = this;
                    var awal = readJson(this.$el, 'awal', {});
                    url = this.$el.getAttribute('data-url') || '';
                    this.total = Number(awal.total) || 0;
                    this.butir = Array.isArray(awal.butir) ? awal.butir : [];
                    terakhir = Date.now();
                    if (!url || !window.fetch) { return; }
                    setInterval(function () { if (!document.hidden) { self.muat(); } }, 120000);
                    document.addEventListener('visibilitychange', function () {
                        if (!document.hidden && Date.now() - terakhir > 30000) { self.muat(); }
                    });
                },
                muat: function () {
                    var self = this;
                    terakhir = Date.now();
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (j) {
                            if (j && j.ok) {
                                self.total = Number(j.total) || 0;
                                self.butir = Array.isArray(j.butir) ? j.butir : [];
                            }
                        })
                        .catch(function () { /* jaringan putus: biarkan angka lama */ });
                },
                alihLonceng: function () {
                    this.alih();
                    if (this.buka && Date.now() - terakhir > 10000) { this.muat(); }
                },
                teksTotal: function () { return this.total > 99 ? '99+' : String(this.total); },
                labelLonceng: function () {
                    return this.total > 0 ? 'Pemberitahuan: ' + this.total + ' pekerjaan menunggu' : 'Pemberitahuan: tidak ada yang menunggu';
                },
            };
        });

        /* 2b. Grup menu lipat (<div x-data="sbGrup" data-kunci data-buka data-label>). Grup yang dibuka pengguna disimpan di
         *     cookie sb_grup (dibaca server → grup tetap terbuka saat pindah halaman, tanpa kedipan). */
        Alpine.data('sbGrup', function () {
            var kunci = '';
            var label = [];
            return {
                buka: false,
                init: function () {
                    kunci = this.$el.getAttribute('data-kunci') || '';
                    label = readJson(this.$el, 'label', []);
                    this.buka = this.$el.getAttribute('data-buka') === '1';
                },
                alih: function () {
                    // Menu menciut (deret ikon): klik ikon grup = lebarkan menu lalu buka grup ini.
                    if (this.sempit()) {
                        this.alihCiut();
                        if (this.buka) { return; }
                    }
                    this.buka = !this.buka;
                    var daftar = bacaKue('sb_grup').split(',').filter(function (k) {
                        return /^sb_g_[0-9a-f]{8}$/.test(k) && k !== kunci;
                    });
                    if (this.buka) { daftar.push(kunci); }
                    tulisKue('sb_grup', daftar.join(','));
                },
                /* Saat mencari: grup tampil bila salah satu menunya cocok (cari = milik adminLayout). */
                grupCocok: function () {
                    var q = String(this.cari || '').trim().toLowerCase();
                    return q === '' || label.some(function (l) { return String(l).indexOf(q) !== -1; });
                },
            };
        });

        /* 3. Kartu panduan: TERTUTUP secara default, terbuka hanya bila
         *    user pernah menekan "Baca panduan" (tersimpan di localStorage). */
        Alpine.data('helpCard', function () {
            return {
                key: '',
                show: false,
                init: function () {
                    this.key  = 'help_' + (this.$el.getAttribute('data-help-key') || '');
                    try { this.show = window.localStorage.getItem(this.key) === '1'; } catch (e) { this.show = false; }
                },
                openHelp: function ()  { this.show = true;  try { window.localStorage.setItem(this.key, '1'); } catch (e) {} },
                closeHelp: function () { this.show = false; try { window.localStorage.removeItem(this.key); } catch (e) {} },
            };
        });

        /* Halaman master standar tanpa perilaku khusus. */
        Alpine.data('masterList', function () { return window.masterList(); });

        /* 5. Editor pratinjau impor (dipakai semua modul master). */
        Alpine.data('importPreview', function () {
            return {
                cols: [],
                rows: [],
                init: function () {
                    this.cols = readJson(this.$el, 'cols', []);
                    this.rows = readJson(this.$el, 'rows', []).map(function (r, i) {
                        return Object.assign({}, r, { _uid: 'r' + i + '_' + Date.now(), _status: r._status || 'baru' });
                    });
                },
                addRow: function () {
                    var blank = { _uid: 'n' + Date.now() + Math.random(), _status: 'baru' };
                    this.cols.forEach(function (k) { blank[k] = ''; });
                    this.rows.push(blank);
                },
                removeRow: function (i) { this.rows.splice(i, 1); },
                // Bangun input tersembunyi rows[i][key] tepat sebelum submit.
                prepare: function () {
                    var box = this.$refs.payload;
                    var cols = this.cols;
                    box.innerHTML = '';
                    var n = 0;
                    this.rows.forEach(function (row) {
                        var isEmpty = cols.every(function (k) { return !String(row[k] === undefined || row[k] === null ? '' : row[k]).trim(); });
                        if (isEmpty) { return; }
                        cols.forEach(function (k) {
                            var inp = document.createElement('input');
                            inp.type  = 'hidden';
                            inp.name  = 'rows[' + n + '][' + k + ']';
                            inp.value = (row[k] === undefined || row[k] === null) ? '' : row[k];
                            box.appendChild(inp);
                        });
                        n++;
                    });
                },
            };
        });
    });
})();
