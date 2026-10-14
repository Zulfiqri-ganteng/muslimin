/**
 * Jadwal KBM per kelas (Jadwal → KBM) — rombak tampilan F6: dulu fungsi global jadwalGrid() di <script> sebaris (data PHP di
 * dalam skrip, ?. di isian, penugasan & && di @click, confirm() bawaan, toast buatan sendiri). Kini komponen
 * Alpine.data('jadwalGrid') di berkas ini: konfigurasi dari data-cfg (JSON: kelasId, cells, palet, url{place,move,remove,bulk}),
 * isian = panggilan metode sederhana (siap Alpine versi CSP), pesan lewat toast kit (Ui.toast), "Kosongkan semua" ditanyakan
 * lewat dialog kit (Ui.konfirmasi). Kunci sel: "<hari_id>-<jam_id>". Token CSRF ditambahkan otomatis ke fetch oleh app.js.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('jadwalGrid', function () {
        var drag = null; // sumber seret yang sedang berjalan (bukan data reaktif)
        var url = {};

        // POST x-www-form-urlencoded (larik → nama[]); jawaban JSON {ok, msg, …}.
        var kirim = function (alamat, data) {
            var p = new URLSearchParams();
            Object.keys(data).forEach(function (k) {
                var v = data[k];
                if (Array.isArray(v)) { v.forEach(function (x) { p.append(k + '[]', x); }); } else { p.append(k, v); }
            });
            return fetch(alamat, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: p })
                .then(function (res) { return res.json(); })
                .catch(function () { return { ok: false, msg: 'Gagal terhubung ke server.' }; });
        };
        var beri = function (pesan, jenis) {
            if (window.Ui) { window.Ui.toast(pesan || 'Terjadi kesalahan.', jenis || 'sukses'); }
        };

        return {
            kelasId: 0,
            cells: {},
            palet: [],
            selectMode: false,
            selected: {},

            init: function () {
                var cfg = window.readJson(this.$el, 'cfg', {});
                this.kelasId = cfg.kelasId || 0;
                this.cells = cfg.cells || {};
                this.palet = cfg.palet || [];
                url = cfg.url || {};
            },

            // ---- isi sel & palet (dipakai isian) ----
            ada: function (k) { return !!this.cells[k]; },
            namaMapel: function (k) { var c = this.cells[k]; return c ? (c.nama_mapel || c.kode_mapel || '') : ''; },
            judulMapel: function (k) { var c = this.cells[k]; return c ? (c.nama_mapel || '') : ''; },
            namaGuru: function (k) { var c = this.cells[k]; return c ? (c.guru_nama || c.kode_guru || '') : ''; },
            judulGuru: function (k) { var c = this.cells[k]; return c ? (c.guru_nama || '') : ''; },
            terpilih: function (k) { return !!this.selected[k]; },
            kelasSel: function (k) {
                if (!this.selectMode) {
                    return 'cursor-grab border-brand-200 bg-brand-50 hover:border-brand-400 dark:border-brand-800 dark:bg-brand-900/30';
                }
                return this.selected[k]
                    ? 'cursor-pointer border-red-500 bg-red-50 ring-2 ring-red-400 dark:bg-red-900/30'
                    : 'cursor-pointer border-brand-200 bg-brand-50 hover:border-red-300 dark:border-brand-800 dark:bg-brand-900/30';
            },
            kelasSisa: function (p) { return p.sisa === 0 ? 'lencana-hijau' : (p.sisa < 0 ? 'lencana-merah' : 'lencana-kuning'); },
            teksSisa: function (p) { return 'sisa ' + p.sisa + '/' + p.jp; },

            // ---- hapus massal ----
            alihMassal: function () {
                this.selectMode = !this.selectMode;
                if (!this.selectMode) { this.selected = {}; }
            },
            klikSel: function (k) {
                if (!this.selectMode || !this.cells[k]) { return; }
                if (this.selected[k]) { delete this.selected[k]; } else { this.selected[k] = true; }
            },
            jumlahPilih: function () { return Object.keys(this.selected).length; },
            tanpaPilihan: function () { return this.jumlahPilih() === 0; },
            pilihSemua: function () {
                var self = this, sel = {};
                Object.keys(this.cells).forEach(function (k) { if (self.cells[k]) { sel[k] = true; } });
                this.selected = sel;
            },
            batalPilih: function () { this.selected = {}; },
            perbaruiSisa: function (s) {
                if (!s) { return; }
                var p = this.palet.filter(function (x) { return x.id === s.pengampu_id; })[0];
                if (p) { p.sisa = s.sisa; }
            },
            terapkanSisa: function (list) {
                var self = this;
                (list || []).forEach(function (s) { self.perbaruiSisa(s); });
            },
            hapusTerpilih: function () {
                var self = this;
                var keys = Object.keys(this.selected).filter(function (k) { return self.cells[k]; });
                if (!keys.length) { beri('Belum ada sel dipilih.', 'galat'); return; }
                var ids = keys.map(function (k) { return self.cells[k].id; });
                kirim(url.bulk, { mode: 'selected', kelas_id: this.kelasId, ids: ids }).then(function (r) {
                    if (!r.ok) { beri(r.msg, 'galat'); return; }
                    (r.removedKeys || keys).forEach(function (k) { delete self.cells[k]; });
                    self.terapkanSisa(r.sisaAll);
                    self.selected = {};
                    beri((r.count || keys.length) + ' sel dihapus.');
                });
            },
            kosongkanSemua: function () {
                var self = this;
                if (!Object.keys(this.cells).length) { beri('Jadwal sudah kosong.', 'galat'); return; }
                window.Ui.konfirmasi({
                    judul: 'Kosongkan jadwal?',
                    pesan: 'Kosongkan SEMUA jadwal kelas ini? Tindakan ini tidak bisa dibatalkan.',
                    ya: 'Ya, kosongkan',
                    jenis: 'bahaya',
                }).then(function (ya) {
                    if (!ya) { return; }
                    kirim(url.bulk, { mode: 'all', kelas_id: self.kelasId }).then(function (r) {
                        if (!r.ok) { beri(r.msg, 'galat'); return; }
                        self.cells = {};
                        self.terapkanSisa(r.sisaAll);
                        self.selected = {};
                        self.selectMode = false;
                        beri((r.count || 0) + ' sel dihapus. Jadwal dikosongkan.');
                    });
                });
            },

            // ---- sumber seret ----
            mulaiPalet: function (e, p) {
                if (p.sisa <= 0) { e.preventDefault(); return; }
                drag = { type: 'palette', pengampu_id: p.id };
                e.dataTransfer.effectAllowed = 'copy';
                try { e.dataTransfer.setData('text/plain', String(p.id)); } catch (err) { /* Firefox butuh data agar seret berjalan */ }
            },
            mulaiSel: function (e, k) {
                var c = this.cells[k];
                if (!c || this.selectMode) { e.preventDefault(); return; }
                drag = { type: 'cell', id: c.id, fromKey: k };
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', k); } catch (err) { /* idem */ }
            },

            // ---- sasaran lepas ----
            lepas: function (e, hariId, jamId) {
                e.preventDefault(); // Firefox: jangan buka teks seretan sebagai alamat
                var d = drag;
                drag = null;
                if (!d) { return; }
                if (d.type === 'palette') { this.pasang(hariId, jamId, d.pengampu_id); } else { this.pindah(d.id, hariId, jamId, d.fromKey); }
            },
            pasang: function (hariId, jamId, pengampuId) {
                var self = this;
                kirim(url.place, { kelas_id: this.kelasId, hari_id: hariId, jam_id: jamId, pengampu_id: pengampuId }).then(function (r) {
                    if (!r.ok) { beri(r.msg, 'galat'); return; }
                    self.cells[r.cell.hari_id + '-' + r.cell.jam_id] = r.cell;
                    self.perbaruiSisa(r.sisa);
                    beri('Jadwal ditambahkan.');
                });
            },
            hapusSel: function (k) {
                var self = this, c = this.cells[k];
                if (!c) { return; }
                kirim(url.remove, { id: c.id }).then(function (r) {
                    if (!r.ok) { beri(r.msg, 'galat'); return; }
                    delete self.cells[k];
                    self.perbaruiSisa(r.sisa);
                    beri('Sel dikosongkan.');
                });
            },
            pindah: function (id, hariId, jamId, fromKey) {
                var self = this;
                if (fromKey === hariId + '-' + jamId) { return; }
                kirim(url.move, { id: id, to_hari_id: hariId, to_jam_id: jamId }).then(function (r) {
                    if (!r.ok) { beri(r.msg, 'galat'); return; }
                    if (r.move) {
                        delete self.cells[r.move.from.hari_id + '-' + r.move.from.jam_id];
                        self.cells[r.move.cell.hari_id + '-' + r.move.cell.jam_id] = r.move.cell;
                        beri('Jadwal dipindahkan.');
                    } else if (r.swap) {
                        r.swap.forEach(function (s) { self.cells[s.cell.hari_id + '-' + s.cell.jam_id] = s.cell; });
                        beri('Jadwal ditukar.');
                    }
                });
            },
        };
    });
});
