/**
 * Form LAPOR DITERIMA PKL (publik) — komponen Alpine `pklLapor`. docs/DESAIN-HAK-AKSES.md bagian 4.
 *
 * Langkah 1: pilih kelas → nama (status saja) → nomor HP (dibuktikan server). Langkah 2: periode PKL + alamat + catatan → kirim.
 * Validasi di sini hanya kenyamanan; penentu tetap server (App\Libraries\PklLapor). Kunci galat = nama kolom server.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    function teks(v) { return String(v === null || v === undefined ? '' : v).trim(); }
    function telp(v) {
        var s = teks(v).replace(/\D/g, '');
        if (s.indexOf('62') === 0 && s.length >= 10) { s = '0' + s.slice(2); }
        return s;
    }
    function murni(v) { return /^\+?[\d\s().\-]+$/.test(teks(v)); }
    function hpSah(v) { var s = telp(v); return /^08\d{8,12}$/.test(s) && !/^0(\d)\1+$/.test(s); }
    function tglSah(v) { return /^\d{4}-\d{2}-\d{2}$/.test(teks(v)); }
    var KEPALA = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    Alpine.data('pklLapor', function () {
        return {
            cfg: {},
            tahap: 1,
            kelasId: '',
            siswaList: [],
            memuat: false,
            pesanDaftar: '',
            cari: '',
            pilih: null,
            hp: '',
            pesanCek: '',
            proses: false,
            data: null,
            f: { tanggal_mulai: '', tanggal_selesai: '', alamat: '', catatan: '' },
            err: {},
            mengirim: false,
            pesanKirim: '',

            init: function () {
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
            },

            muatSiswa: function () {
                var self = this;
                var id = this.kelasId;
                this.pilih = null;
                this.siswaList = [];
                this.cari = '';
                this.pesanDaftar = '';
                if (!id) { return; }
                this.memuat = true;
                fetch(this.cfg.urlSiswa + '?kelas_id=' + encodeURIComponent(id), { headers: KEPALA, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .catch(function () { return { ok: false, message: 'Gagal terhubung. Periksa internet lalu pilih kelas lagi.' }; })
                    .then(function (j) {
                        if (id !== self.kelasId) { return; }
                        if (j.ok) {
                            self.siswaList = j.data || [];
                            if (!self.siswaList.length) { self.pesanDaftar = 'Belum ada nama siswa di kelas ini.'; }
                        } else {
                            self.pesanDaftar = j.message || 'Daftar nama gagal dimuat.';
                        }
                    })
                    .finally(function () { self.memuat = false; });
            },

            siswaTersaring: function () {
                var q = this.cari.trim().toLowerCase();
                return q ? this.siswaList.filter(function (s) { return s.nama.toLowerCase().indexOf(q) !== -1; }) : this.siswaList;
            },

            lencana: function (status) {
                return {
                    boleh: { teks: 'Bisa lapor', kelas: 'bg-brand-100 text-brand-800' },
                    sudah_lapor: { teks: 'Sudah lapor', kelas: 'bg-amber-100 text-amber-800' },
                    dikembalikan: { teks: 'Perlu diperbaiki', kelas: 'bg-red-100 text-red-700' },
                    selesai: { teks: 'Selesai', kelas: 'bg-green-100 text-green-700' },
                    belum_disetujui: { teks: 'Ajuan belum disetujui', kelas: 'bg-slate-100 text-slate-500' },
                    belum_ajuan: { teks: '', kelas: '' },
                }[status] || { teks: '', kelas: '' };
            },

            namaKelas: function () {
                var k = this.cfg.kelas[this.kelasId];
                return k ? k.nama : '';
            },

            pilihSiswa: function (s) {
                var self = this;
                this.pilih = s;
                this.hp = '';
                this.pesanCek = '';
                this.$nextTick(function () {
                    if (self.$refs.panel) { self.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
            },

            cek: function () {
                var self = this;
                if (this.proses || !this.pilih) { return; }
                if (!teks(this.hp)) { this.pesanCek = 'Isi nomor HP-mu.'; return; }
                if (!murni(this.hp)) { this.pesanCek = 'Nomor hanya boleh berisi angka (periksa huruf "O" yang tertukar dengan angka 0).'; return; }
                if (!hpSah(this.hp)) { this.pesanCek = 'Nomor HP tidak valid (contoh: 081234567890).'; return; }
                this.pesanCek = '';
                this.proses = true;
                var fd = new FormData();
                fd.append('siswa_id', this.pilih.id);
                fd.append('hp', teks(this.hp));
                fetch(this.cfg.urlCek, { method: 'POST', body: fd, credentials: 'same-origin', headers: KEPALA })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j.ok) { self.pesanCek = j.message || 'Gagal memeriksa.'; return; }
                        self.isiDari(j.data || {});
                        self.tahap = 2;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    })
                    .catch(function () { self.pesanCek = 'Gagal terhubung. Periksa internet lalu coba lagi.'; })
                    .finally(function () { self.proses = false; });
            },

            /** Isi langkah 2 dari ringkasan server: laporan lama (bila ada) → periode ajuan → kosong. */
            isiDari: function (d) {
                this.data = d;
                var l = d.lapor || {};
                this.f = {
                    tanggal_mulai: l.mulai || d.mulai || '',
                    tanggal_selesai: l.selesai || d.selesai || '',
                    alamat: l.alamat || d.alamat || '',
                    catatan: l.catatan || '',
                };
                this.err = {};
                this.pesanKirim = '';
            },

            /** "± 3 bulan (92 hari)" dari dua tanggal, atau kosong. */
            lama: function () {
                if (!tglSah(this.f.tanggal_mulai) || !tglSah(this.f.tanggal_selesai)) { return ''; }
                var a = new Date(this.f.tanggal_mulai + 'T00:00:00');
                var b = new Date(this.f.tanggal_selesai + 'T00:00:00');
                var hari = Math.round((b - a) / 86400000) + 1;
                if (hari < 2) { return ''; }
                var bln = Math.round(hari / 30.4375);
                return 'Lama PKL: ' + hari + ' hari' + (bln >= 1 ? ' (± ' + bln + ' bulan)' : '');
            },

            periksa: function () {
                var e = {};
                if (!tglSah(this.f.tanggal_mulai)) { e.tanggal_mulai = 'Isi tanggal mulai PKL.'; }
                if (!tglSah(this.f.tanggal_selesai)) { e.tanggal_selesai = 'Isi tanggal selesai PKL.'; }
                if (!e.tanggal_mulai && !e.tanggal_selesai && this.f.tanggal_selesai <= this.f.tanggal_mulai) {
                    e.tanggal_selesai = 'Tanggal selesai harus setelah tanggal mulai.';
                }
                this.err = e;
                return Object.keys(e).length === 0;
            },

            kirim: function () {
                var self = this;
                if (this.mengirim || !this.data) { return; }
                if (!this.periksa()) { this.pesanKirim = 'Periksa lagi isian yang ditandai merah.'; return; }
                this.pesanKirim = '';
                this.mengirim = true;
                var fd = new FormData();
                fd.append('ajuan_id', this.data.ajuan_id);
                ['tanggal_mulai', 'tanggal_selesai', 'alamat', 'catatan'].forEach(function (k) { fd.append(k, teks(self.f[k])); });
                fd.append('website', this.$refs.jebakan ? this.$refs.jebakan.value : '');
                fetch(this.cfg.urlKirim, { method: 'POST', body: fd, credentials: 'same-origin', headers: KEPALA })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.ok) { window.location.href = j.redirect; return; }
                        self.err = j.errors || {};
                        self.pesanKirim = j.message || 'Laporan gagal dikirim.';
                        self.mengirim = false;
                    })
                    .catch(function () { self.pesanKirim = 'Gagal terhubung. Isianmu tidak hilang — periksa internet lalu tekan Kirim lagi.'; self.mengirim = false; });
            },

            kembali: function () {
                this.tahap = 1;
                this.err = {};
                this.pesanKirim = '';
            },
        };
    });
});
