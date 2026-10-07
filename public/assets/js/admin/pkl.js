/**
 * Halaman staf PKL — komponen Alpine `pklFormStaf` (form "Isi atas Nama" & "Ubah langsung"):
 *   - pemilih siswa (pengaju & teman): kelas → nama, siswa yang sudah punya ajuan aktif terkunci;
 *   - HP tiap teman (wajib; server tetap penentu: PklForm::proses).
 * Data dari server lewat atribut data-config pada elemen <form>.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var LABEL_STATUS = { menunggu: 'Menunggu ACC', perbaikan: 'Perlu perbaikan', disetujui: 'Disetujui' };

    Alpine.data('pklFormStaf', function () {
        return {
            cfg: {},
            pengaju: null,
            teman: [],
            asal: [],            // id siswa yang sudah ada di ajuan ini (mode ubah) — tetap boleh dipilih lagi
            maks: 5,
            galatHp: {},
            kirimTertunda: false,
            pick: { buka: false, mode: 'teman', kelasId: '', kelasNama: '', list: [], memuat: false, cari: '' },

            init: function () {
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                this.pengaju = this.cfg.pengaju || null;
                this.teman = (this.cfg.teman || []).map(function (t) { return Object.assign({ hp: '' }, t); });
                this.maks = this.cfg.maks || 1;
                this.galatHp = this.cfg.galatHp || {};
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
                var orang = { id: s.id, nama: s.nama, kelas: this.pick.kelasNama, hp: '' };
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

            /** Pesan galat HP satu teman dari server (kunci hp_teman_<id>). */
            errHp: function (id) { return this.galatHp['hp_teman_' + id] || ''; },        };
    });
});
