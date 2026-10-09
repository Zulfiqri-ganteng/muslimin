/**
 * Formulir Surat Izin ASTS / TKA — komponen Alpine `suratIzinForm`.
 *
 * Menampilkan pratinjau baris "Hari/Tanggal" persis seperti yang akan tercetak (nama hari dihitung dari tanggal,
 * aturan sama dengan Libraries\SuratAcara::hariTanggal), mengisi tanggal dari periode menu Ujian, dan menyaring daftar
 * perusahaan untuk surat per perusahaan.
 *
 * Data dari server lewat atribut data-config (JSON) pada elemen akar:
 *   mode, semester, tp, mulai, selesai, tanggalSurat, periode[], perusahaan[], dipilih[].
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function tgl(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) { return null; }
        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return (d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3]) ? d : null;
    }
    function hb(d) { return d.getUTCDate() + ' ' + BULAN[d.getUTCMonth()]; }
    function ym(d) { return d.getUTCFullYear() * 12 + d.getUTCMonth(); }

    Alpine.data('suratIzinForm', function () {
        return {
            mode: 'umum', semester: 'Ganjil', tp: '', mulai: '', selesai: '', tanggalSurat: '',
            periode: [], perusahaan: [], dipilih: [], pilihPeriode: '', cari: '',

            init: function () {
                var c = JSON.parse(this.$el.getAttribute('data-config') || '{}');
                this.mode = c.mode || 'umum';
                this.semester = c.semester || 'Ganjil';
                this.tp = c.tp || '';
                this.mulai = c.mulai || '';
                this.selesai = c.selesai || '';
                this.tanggalSurat = c.tanggalSurat || '';
                this.periode = c.periode || [];
                this.perusahaan = c.perusahaan || [];
                this.dipilih = c.dipilih || [];
            },

            /* Baris "Hari/Tanggal" seperti di surat. */
            hariTanggal: function () {
                var a = tgl(this.mulai), b = tgl(this.selesai);
                if (!a || !b || b < a) { return ''; }
                var ha = HARI[a.getUTCDay()], hbn = HARI[b.getUTCDay()];
                if (+a === +b) { return ha + ', ' + hb(a) + ' ' + a.getUTCFullYear(); }
                if (ym(a) === ym(b)) { return ha + ' s.d. ' + hbn + ', ' + a.getUTCDate() + ' - ' + hb(b) + ' ' + b.getUTCFullYear(); }
                if (a.getUTCFullYear() === b.getUTCFullYear()) { return ha + ' s.d. ' + hbn + ', ' + hb(a) + ' - ' + hb(b) + ' ' + b.getUTCFullYear(); }
                return ha + ' s.d. ' + hbn + ', ' + hb(a) + ' ' + a.getUTCFullYear() + ' - ' + hb(b) + ' ' + b.getUTCFullYear();
            },

            /* Frasa kalimat: "14 sampai dengan 18 September 2026". */
            tanggalSampai: function () {
                var a = tgl(this.mulai), b = tgl(this.selesai);
                if (!a || !b || b < a) { return ''; }
                if (+a === +b) { return hb(a) + ' ' + a.getUTCFullYear(); }
                if (ym(a) === ym(b)) { return a.getUTCDate() + ' sampai dengan ' + hb(b) + ' ' + b.getUTCFullYear(); }
                if (a.getUTCFullYear() === b.getUTCFullYear()) { return hb(a) + ' sampai dengan ' + hb(b) + ' ' + b.getUTCFullYear(); }
                return hb(a) + ' ' + a.getUTCFullYear() + ' sampai dengan ' + hb(b) + ' ' + b.getUTCFullYear();
            },

            /* Peringatan ringan sebelum disimpan (server tetap memeriksa ulang). */
            peringatan: function () {
                var a = tgl(this.mulai), b = tgl(this.selesai), s = tgl(this.tanggalSurat);
                if (a && b && b < a) { return 'Tanggal selesai sebelum tanggal mulai.'; }
                if (b && s && s > b) { return 'Tanggal surat berada SETELAH kegiatan selesai.'; }
                if (a && b && ((b - a) / 86400000) + 1 > 31) { return 'Rentang kegiatan lebih dari 31 hari — periksa lagi tanggalnya.'; }
                return '';
            },

            /* Isi semester, tahun pelajaran, dan tanggal dari periode di menu Ujian. */
            ambilPeriode: function () {
                var p = this.periode[this.pilihPeriode];
                if (!p) { return; }
                this.semester = p.semester;
                this.tp = p.tahun;
                this.mulai = p.mulai || '';
                this.selesai = p.selesai || '';
            },

            /* Perusahaan yang periode PKL-nya sudah tercatat tetapi tidak menjangkau tanggal kegiatan. */
            luarPeriode: function (p) {
                return !!(p.mulai && p.selesai && this.mulai && this.selesai && (p.selesai < this.mulai || p.mulai > this.selesai));
            },

            tampil: function (p) {
                var q = this.cari.trim().toLowerCase();
                return q === '' || (p.nama + ' ' + (p.kota || '') + ' ' + (p.siswa || '')).toLowerCase().indexOf(q) !== -1;
            },

            pilihSemua: function (hanyaSesuai) {
                var self = this;
                this.dipilih = this.perusahaan.filter(function (p) { return self.tampil(p) && (!hanyaSesuai || !self.luarPeriode(p)); })
                    .map(function (p) { return p.kunci; });
            },
        };
    });
});
