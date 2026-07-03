/**
 * =====================================================================
 * Panel Admin — JavaScript global (dipisah dari view, tanpa inline).
 * =====================================================================
 * Dimuat di layouts/admin.php SEBELUM Alpine.js sehingga semua komponen
 * terdaftar saat event `alpine:init` berjalan.
 *
 * Isi:
 *  1. Delegasi global : [data-confirm], [data-autosubmit]
 *  2. adminLayout     : sidebar, ciutkan menu, overlay loading
 *  3. helpCard        : kartu panduan halaman (default TERTUTUP)
 *  4. masterList      : pabrik komponen halaman daftar master data
 *  5. importPreview   : editor pratinjau impor Excel
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * 1. Delegasi global — pengganti inline handler.
     * ------------------------------------------------------------- */

    // <a data-confirm="Yakin?"> — batalkan klik bila user menolak.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (el && !window.confirm(el.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

    // <select data-autosubmit> — kirim form induk saat nilai berubah.
    document.addEventListener('change', function (e) {
        var el = e.target.closest('[data-autosubmit]');
        if (el && el.form) {
            if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.submit(); }
        }
    });

    /** Baca atribut data-* berisi JSON dengan aman. */
    function readJson(root, name, fallback) {
        var raw = root.getAttribute('data-' + name);
        if (!raw) { return fallback; }
        try { return JSON.parse(raw); } catch (err) { return fallback; }
    }
    window.readJson = readJson;

    /* ---------------------------------------------------------------
     * 4. masterList — pabrik komponen halaman daftar master data.
     *    Konfigurasi lewat atribut data-* pada elemen root:
     *      data-base     : URL dasar modul (untuk aksi simpan/edit)
     *      data-defaults : JSON nilai awal form tambah
     *      data-entity   : label entitas utk teks konfirmasi ("guru", dst.)
     *    Perilaku khusus per halaman dioper lewat `overrides`.
     * ------------------------------------------------------------- */
    window.masterList = function (overrides) {
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
                this.base     = this.$el.getAttribute('data-base') || '';
                this.entity   = this.$el.getAttribute('data-entity') || 'data';
                this.defaults = readJson(this.$el, 'defaults', {});
                this.form     = Object.assign({}, this.defaults);
                if (this.onInit) { this.onInit(); }
            },

            /* ---- modal tambah / edit ---- */
            openAdd: function () {
                this.mode      = 'add';
                this.form      = Object.assign({}, this.defaults);
                this.actionUrl = this.base;
                this.open      = true;
            },
            // Dipanggil dari tombol edit: data baris ada di atribut data-row (JSON).
            openEditEl: function (el) {
                this.openEdit(JSON.parse(el.getAttribute('data-row') || '{}'));
            },
            openEdit: function (r) {
                this.mode      = 'edit';
                this.form      = this.mapEdit(r);
                this.actionUrl = this.base + '/' + r.id;
                this.open      = true;
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

        /* 2. Layout admin: sidebar + overlay loading semua form. */
        Alpine.data('adminLayout', function () {
            return {
                sidebar: false,
                collapsed: JSON.parse(localStorage.getItem('sb_collapsed') || 'false'),
                loading: false,
                init: function () {
                    var self = this;
                    this.$watch('collapsed', function (v) { localStorage.setItem('sb_collapsed', v); });
                    document.addEventListener('submit', function (e) {
                        if (e.target.tagName === 'FORM' && !e.target.hasAttribute('data-noload')) { self.loading = true; }
                    });
                    window.addEventListener('pageshow', function () { self.loading = false; });
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
                    this.show = localStorage.getItem(this.key) === '1';
                },
                openHelp: function ()  { this.show = true;  localStorage.setItem(this.key, '1'); },
                closeHelp: function () { this.show = false; localStorage.removeItem(this.key); },
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
