/**
 * Formulir Surat Sekolah yang bersumber dari data PKL — komponen Alpine:
 *   - `suratOrtuForm`  : Pernyataan Orang Tua PKL (cakupan per kelas / per ajuan / siswa pilihan);
 *   - `suratPklForm`   : Surat Balasan PKL & Surat Penarikan Izin PKL (pilih ajuan, pratinjau kalimat surat).
 *
 * Rumus pratinjau SAMA dengan server (Libraries\SuratPkl): lamaBulan (sisa ≥ 15 hari = sebulan), periodeTeks, alasan.
 * Data dari server lewat atribut data-config (JSON) pada elemen akar.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var ANGKA = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    function tgl(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) { return null; }
        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return (d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3]) ? d : null;
    }
    function hb(d) { return d.getUTCDate() + ' ' + BULAN[d.getUTCMonth()]; }
    function tglIndo(s) { var d = tgl(s); return d ? hb(d) + ' ' + d.getUTCFullYear() : ''; }
    function hariInklusif(a, b) { return Math.round((b - a) / 86400000) + 1; }
    function terbilang(n) {
        if (n < 12) { return ANGKA[n]; }
        if (n < 20) { return ANGKA[n - 10] + ' belas'; }
        return String(n);
    }

    /* Lama PKL = selisih hari ÷ 30,4375, dibulatkan, minimal 1 (sama dengan SuratPkl::lamaBulan). */
    function lamaBulan(mulai, selesai) {
        var a = tgl(mulai), b = tgl(selesai);
        if (!a || !b || b <= a) { return 0; }
        return Math.max(1, Math.round(Math.round((b - a) / 86400000) / 30.4375));
    }
    function periodeTeks(mulai, selesai) {
        var a = tgl(mulai), b = tgl(selesai);
        if (!a || !b || b < a) { return ''; }
        if (a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth()) {
            return a.getUTCDate() + ' s.d. ' + hb(b) + ' ' + b.getUTCFullYear();
        }
        if (a.getUTCFullYear() === b.getUTCFullYear()) { return hb(a) + ' s.d. ' + hb(b) + ' ' + b.getUTCFullYear(); }
        return hb(a) + ' ' + a.getUTCFullYear() + ' s.d. ' + hb(b) + ' ' + b.getUTCFullYear();
    }
    function cocok(teks, q) { return q === '' || String(teks).toLowerCase().indexOf(q) !== -1; }

    // ================================================================= Pernyataan Orang Tua
    Alpine.data('suratOrtuForm', function () {
        return {
            cakupan: 'kelas', kelas: [], ajuan: [], siswa: [], kelasDipilih: [], ajuanDipilih: [], siswaDipilih: [],
            cariKelas: '', cariAjuan: '', cariSiswa: '', kelasSiswa: '', _nama: {},

            init: function () {
                var c = JSON.parse(this.$el.getAttribute('data-config') || '{}');
                this.cakupan = c.cakupan || 'kelas';
                this.kelas = c.kelas || [];
                this.ajuan = c.ajuan || [];
                this.siswa = c.siswa || [];
                this.kelasDipilih = c.kelasDipilih || [];
                this.ajuanDipilih = c.ajuanDipilih || [];
                this.siswaDipilih = c.siswaDipilih || [];
                this.kelasSiswa = this.kelas.length ? String(this.kelas[0].id) : '';
                var nama = {};
                this.siswa.forEach(function (s) { nama[s.id] = s; });
                this._nama = nama;
            },

            tampilKelas: function (k) { return cocok(k.nama, this.cariKelas.trim().toLowerCase()); },
            pilihSemuaKelas: function () {
                var self = this;
                this.kelasDipilih = this.kelas.filter(function (k) { return self.tampilKelas(k); }).map(function (k) { return String(k.id); });
            },
            tampilAjuan: function (a) { return cocok(a.perusahaan + ' ' + a.kode + ' ' + a.siswa, this.cariAjuan.trim().toLowerCase()); },

            namaKelas: function (id) {
                for (var i = 0; i < this.kelas.length; i++) { if (this.kelas[i].id === id) { return this.kelas[i].nama; } }
                return '';
            },
            /* Siswa kelas terpilih; dengan kata kunci ≥ 3 huruf mencari di semua kelas (nama atau NIS). */
            daftarSiswa: function () {
                var q = this.cariSiswa.trim().toLowerCase(), kid = Number(this.kelasSiswa);
                return this.siswa.filter(function (s) {
                    return q.length >= 3 ? (s.nama.toLowerCase().indexOf(q) !== -1 || String(s.nis).indexOf(q) !== -1) : s.kelas_id === kid;
                }).slice(0, 300);
            },
            dipilihSiswa: function (id) { return this.siswaDipilih.indexOf(id) !== -1; },
            togelSiswa: function (id) {
                var i = this.siswaDipilih.indexOf(id);
                if (i === -1) { this.siswaDipilih.push(id); } else { this.siswaDipilih.splice(i, 1); }
            },
            namaSiswa: function (id) { var s = this._nama[id]; return s ? s.nama + ' (' + this.namaKelas(s.kelas_id) + ')' : '#' + id; },

            jumlahHalaman: function () {
                var self = this, n = 0;
                if (this.cakupan === 'kelas') {
                    this.kelas.forEach(function (k) { if (self.kelasDipilih.indexOf(String(k.id)) !== -1) { n += k.jml; } });
                } else if (this.cakupan === 'ajuan') {
                    this.ajuan.forEach(function (a) { if (self.ajuanDipilih.indexOf(String(a.id)) !== -1) { n += a.jml; } });
                } else {
                    n = this.siswaDipilih.length;
                }
                return n;
            },
        };
    });

    // ================================================================= Balasan & Penarikan
    Alpine.data('suratPklForm', function () {
        return {
            jenis: 'balasan', ubah: false, tempat: '', ajuan: [], dipilih: [], ajuanTunggal: '', siswaDipilih: [], cari: '',
            mulai: '', selesai: '', tglEfektif: '', alasanPilih: '0', alasanLain: '', alasanSaran: [], batas: {},
            // Balasan: siswa yang TIDAK diterima perusahaan ("ajuan:siswa" => true) + alasannya (dibaca siswa).
            tolak: {}, alasanTolak: '', alasanTolakSaran: [],

            init: function () {
                var self = this;
                var c = JSON.parse(this.$el.getAttribute('data-config') || '{}');
                this.jenis = c.jenis || 'balasan';
                this.ubah = !!c.ubah;
                this.tempat = c.tempat || '';
                this.ajuan = c.ajuan || [];
                this.dipilih = c.dipilih || [];
                this.ajuanTunggal = this.dipilih.length ? String(this.dipilih[0]) : '';
                this.siswaDipilih = c.siswaDipilih || [];
                this.mulai = c.mulai || '';
                this.selesai = c.selesai || '';
                this.tglEfektif = c.tglEfektif || '';
                this.alasanPilih = c.alasanPilih || '0';
                this.alasanLain = c.alasanLain || '';
                this.alasanSaran = c.alasanSaran || [];
                this.batas = c.batas || {};
                this.tolak = c.tolakAwal || {};
                this.alasanTolak = c.alasanTolak || '';
                this.alasanTolakSaran = c.alasanTolakSaran || [];

                // Balasan: satu ajuan dipilih dan periodenya sudah tercatat → isi otomatis bila kolom periode masih kosong.
                this.$watch('dipilih', function (v) {
                    if (self.jenis !== 'balasan' || v.length !== 1 || self.mulai || self.selesai) { return; }
                    var a = self.cariAjuan(v[0]);
                    if (a && a.mulai && a.selesai) { self.mulai = a.mulai; self.selesai = a.selesai; }
                });
                // Penarikan: ganti tempat → pilihan siswa dikosongkan.
                this.$watch('ajuanTunggal', function () { if (!self.ubah) { self.siswaDipilih = []; } });
            },

            cariAjuan: function (id) {
                for (var i = 0; i < this.ajuan.length; i++) { if (String(this.ajuan[i].id) === String(id)) { return this.ajuan[i]; } }
                return null;
            },
            tampil: function (a) {
                var q = this.cari.trim().toLowerCase();
                return cocok(a.perusahaan + ' ' + a.kode + ' ' + a.alamat + ' ' + a.siswa.map(function (s) { return s.nama; }).join(' '), q);
            },
            pilihSemua: function () {
                var self = this;
                this.dipilih = this.ajuan.filter(function (a) { return !a.balasan && self.tampil(a); }).map(function (a) { return String(a.id); });
            },
            siswaAjuan: function () { var a = this.cariAjuan(this.ajuanTunggal); return a ? a.siswa : []; },

            /* Balasan: centang "siswa yang diterima perusahaan" per ajuan terpilih (ubah: ajuan surat itu). */
            terpilih: function (a) { return this.ubah || this.dipilih.indexOf(String(a.id)) !== -1; },
            kunciTerima: function (a, s) { return String(a.id) + ':' + String(s.id); },
            diterima: function (a, s) { return !this.tolak[this.kunciTerima(a, s)]; },
            ubahTerima: function (a, s) {
                var baru = Object.assign({}, this.tolak), k = this.kunciTerima(a, s);
                if (baru[k]) { delete baru[k]; } else { baru[k] = true; }
                this.tolak = baru;
            },
            namaTerima: function (a) { return 'terima[' + a.id + '][]'; },
            namaAtur: function (a) { return 'atur_terima[' + a.id + ']'; },
            jumlahTerima: function (a) { var self = this; return a.siswa.filter(function (s) { return self.diterima(a, s); }).length; },
            adaTolak: function () {
                var self = this;
                return this.ajuan.some(function (a) { return self.terpilih(a) && a.siswa.some(function (s) { return !self.diterima(a, s); }); });
            },
            pakaiAlasanTolak: function (i) { var t = this.alasanTolakSaran[i]; if (t) { this.alasanTolak = t.isi; } },
            // (F9) nama siswa satu ajuan, dipisah koma (dulu fungsi sebaris di x-text — tak didukung Alpine versi CSP).
            daftarNamaSiswa: function (a) { return (a && a.siswa ? a.siswa : []).map(function (s) { return s.nama; }).join(", "); },
            namaTempat: function () { var a = this.cariAjuan(this.ajuanTunggal); return this.tempat || (a ? a.perusahaan : ''); },

            // (F9) nama metode beda dari fungsi di atas — dev:cek-ui menolak isian yang mirip panggilan fungsi global.
            teksPeriode: periodeTeks,
            teksTglIndo: tglIndo,
            lamaTeks: function () { var n = lamaBulan(this.mulai, this.selesai); return n > 0 ? n + ' (' + terbilang(n) + ') bulan' : ''; },

            /* Peringatan terhadap Pengaturan PKL (server memeriksa ulang; tidak memblokir). */
            peringatan: function () {
                var a = tgl(this.mulai), b = tgl(this.selesai), out = [];
                if (a && b && b <= a) { return ['Tanggal selesai PKL harus setelah tanggal mulai.']; }
                if (!a || !b) { return out; }
                if (this.batas.awal && this.mulai < this.batas.awal) { out.push('Tanggal mulai lebih awal dari batas sekolah ' + tglIndo(this.batas.awal) + '.'); }
                if (this.batas.akhir && this.selesai > this.batas.akhir) { out.push('Tanggal selesai melewati batas sekolah ' + tglIndo(this.batas.akhir) + '.'); }
                var hari = hariInklusif(a, b);
                if (this.batas.min > 0 && hari < this.batas.min) { out.push('Lama PKL ' + hari + ' hari, kurang dari minimal ' + this.batas.min + ' hari di Pengaturan PKL.'); }
                else if (this.batas.maks > 0 && hari > this.batas.maks) { out.push('Lama PKL ' + hari + ' hari, lebih dari maksimal ' + this.batas.maks + ' hari di Pengaturan PKL.'); }
                return out;
            },

            /* Alasan seperti yang akan tercetak (sama dengan SuratPkl::alasan). */
            alasanTeks: function () {
                if (this.alasanPilih !== 'lain') { return this.alasanSaran[Number(this.alasanPilih)] || ''; }
                var t = this.alasanLain.replace(/\s+/g, ' ').trim().replace(/^(karena|sebab|dikarenakan|disebabkan)\s+/i, '').replace(/[ .]+$/, '');
                var a = t.charAt(0), b = t.charAt(1);
                if (b && a !== a.toLowerCase() && b === b.toLowerCase() && b !== b.toUpperCase()) { t = a.toLowerCase() + t.slice(1); }
                return t;
            },
        };
    });
});
