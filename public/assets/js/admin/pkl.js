/**
 * Halaman staf PKL — komponen Alpine `pklFormStaf` (form "Isi atas Nama" & "Ubah langsung"):
 *   - pemilih siswa (pengaju & teman): kelas → nama, siswa yang sudah punya ajuan aktif terkunci;
 *   - lama PKL langsung terhitung, dan peringatan + centang konfirmasi bila tanggal di luar
 *     pagar sekolah (server tetap penentu: PklForm::proses).
 * Data dari server lewat atribut data-config pada elemen <form>.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    /** Selisih hari inklusif dua tanggal Y-m-d (6 Jan s/d 6 Jan = 1). */
    function hariInklusif(a, b) {
        var p = function (s) { var x = s.split('-'); return Date.UTC(Number(x[0]), Number(x[1]) - 1, Number(x[2])); };
        return Math.round((p(b) - p(a)) / 86400000) + 1;
    }

    var LABEL_STATUS = { menunggu: 'Menunggu ACC', perbaikan: 'Perlu perbaikan', disetujui: 'Disetujui' };

    Alpine.data('pklFormStaf', function () {
        return {
            cfg: {},
            pengaju: null,
            teman: [],
            asal: [],            // id siswa yang sudah ada di ajuan ini (mode ubah) — tetap boleh dipilih lagi
            maks: 5,
            mulai: '',
            selesai: '',
            konfirmLuar: false,
            kirimTertunda: false,
            pick: { buka: false, mode: 'teman', kelasId: '', kelasNama: '', list: [], memuat: false, cari: '' },

            init: function () {
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                this.pengaju = this.cfg.pengaju || null;
                this.teman = this.cfg.teman || [];
                this.maks = this.cfg.maks || 1;
                this.mulai = this.cfg.mulai || '';
                this.selesai = this.cfg.selesai || '';
                this.konfirmLuar = !!this.cfg.luar;
                if (this.cfg.ubah) {
                    this.asal = (this.pengaju ? [this.pengaju.id] : []).concat(this.teman.map(function (t) { return t.id; }));
                }
            },

            // ---------- pemilih siswa ----------
            bukaPemilih: function (mode) {
                if (this.pick.buka && this.pick.mode === mode) { this.pick.buka = false; return; }
                this.pick = { buka: true, mode: mode, kelasId: '', kelasNama: '', list: [], memuat: false, cari: '' };
            },

            muatPemilih: function (ev) {
                var self = this;
                var p = this.pick;
                p.list = [];
                p.cari = '';
                p.kelasNama = ev && ev.target && ev.target.selectedOptions[0] ? ev.target.selectedOptions[0].textContent.trim() : '';
                if (!p.kelasId) { return; }
                var id = p.kelasId;
                p.memuat = true;
                fetch(this.cfg.urlSiswa + '?kelas_id=' + encodeURIComponent(id), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) { if (id === p.kelasId) { p.list = (j && j.data) || []; } })
                    .catch(function () { p.list = []; })
                    .finally(function () { p.memuat = false; });
            },

            tersaring: function () {
                var q = this.pick.cari.trim().toLowerCase();
                if (!q) { return this.pick.list; }
                return this.pick.list.filter(function (s) { return s.nama.toLowerCase().indexOf(q) !== -1; });
            },

            /** Alasan seorang siswa TIDAK bisa dipilih ('' = bisa). */
            alasan: function (s) {
                if (this.pengaju && s.id === this.pengaju.id) { return 'Pengaju'; }
                if (this.teman.some(function (t) { return t.id === s.id; })) { return 'Sudah dipilih'; }
                if (LABEL_STATUS[s.status] && this.asal.indexOf(s.id) === -1) { return LABEL_STATUS[s.status]; }
                return '';
            },

            pilih: function (s) {
                if (this.alasan(s) !== '') { return; }
                var orang = { id: s.id, nama: s.nama, kelas: this.pick.kelasNama };
                if (this.pick.mode === 'pengaju') {
                    this.pengaju = orang;
                    this.teman = this.teman.filter(function (t) { return t.id !== s.id; });
                } else if (this.teman.length < this.maks - 1) {
                    this.teman.push(orang);
                }
                this.pick.buka = false;
            },

            hapusTeman: function (id) {
                this.teman = this.teman.filter(function (t) { return t.id !== id; });
            },

            // ---------- tanggal ----------
            tanggalSiap: function () {
                return this.mulai && this.selesai && this.selesai > this.mulai;
            },

            infoDurasi: function () {
                if (!this.tanggalSiap()) { return ''; }
                var h = hariInklusif(this.mulai, this.selesai);
                var bln = Math.round(h / 30);
                return 'Lama PKL: ' + h + ' hari' + (bln >= 1 ? ' (± ' + bln + ' bulan)' : '');
            },

            /** Tanggal/lama di luar aturan sekolah → butuh centang konfirmasi. */
            luarBatas: function () {
                if (!this.tanggalSiap()) { return false; }
                var b = this.cfg.batas || {};
                var h = hariInklusif(this.mulai, this.selesai);
                return !!((b.awal && this.mulai < b.awal) || (b.akhir && this.selesai > b.akhir)
                    || (b.akhir && this.mulai > b.akhir) || (b.awal && this.selesai < b.awal)
                    || (b.min > 0 && h < b.min) || (b.maks > 0 && h > b.maks));
            },
        };
    });
});
