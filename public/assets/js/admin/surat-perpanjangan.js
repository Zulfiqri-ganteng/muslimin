/**
 * Formulir Surat Permohonan Perpanjangan PKL (admin/surat/perpanjangan) — komponen Alpine `suratPerpanjanganForm`:
 * tombol cepat tanggal (+2 minggu / +1, +2, +3 bulan dari tanggal selesai semula), kalimat ringkas seperti yang tercetak, peringatan
 * terhadap Pengaturan PKL, dan pratinjau alasan. Rumus SAMA dengan server (Libraries\SuratPerpanjangan::lamaTeks, SuratPkl::periodeTeks,
 * SuratPkl::alasan); server tetap memeriksa ulang semuanya. Data dari server lewat atribut data-config (JSON) pada elemen akar.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    function pjTgl(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) { return null; }
        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return (d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3]) ? d : null;
    }
    function pjYmd(d) { return d.toISOString().slice(0, 10); }
    function pjHb(d) { return d.getUTCDate() + ' ' + BULAN[d.getUTCMonth()]; }
    function pjIndo(s) { var d = pjTgl(s); return d ? pjHb(d) + ' ' + d.getUTCFullYear() : ''; }
    function pjSelisih(a, b) { var x = pjTgl(a), y = pjTgl(b); return x && y ? Math.round((y - x) / 86400000) : null; }
    function pjTambahHari(s, n) { var d = pjTgl(s); if (!d) { return ''; } d.setUTCDate(d.getUTCDate() + n); return pjYmd(d); }
    /* Tanggal yang sama n bulan kemudian; tanggal yang tak ada (31 → Februari) jatuh ke akhir bulan. */
    function pjTambahBulan(s, n) {
        var d = pjTgl(s);
        if (!d) { return ''; }
        var y = d.getUTCFullYear(), m = d.getUTCMonth() + n, hari = d.getUTCDate();
        var akhir = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
        return pjYmd(new Date(Date.UTC(y, m, Math.min(hari, akhir))));
    }
    /* Angka → kata (sampai 999), sama dengan HonorCetak::terbilang. */
    function pjKata(n) {
        if (n < 12) { return SATUAN[n]; }
        if (n < 20) { return SATUAN[n - 10] + ' belas'; }
        if (n < 100) { return (SATUAN[Math.floor(n / 10)] + ' puluh ' + SATUAN[n % 10]).trim(); }
        if (n < 200) { return ('seratus ' + pjKata(n - 100)).trim(); }
        return (SATUAN[Math.floor(n / 100)] + ' ratus ' + pjKata(n % 100)).trim();
    }
    /* "1 (satu) bulan" bila genap bulan (toleransi 3 hari), selain itu "14 (empat belas) hari". */
    function pjLama(lama, baru) {
        var hari = pjSelisih(lama, baru);
        if (hari === null || hari <= 0) { return ''; }
        var bulan = Math.round(hari / 30.4375);
        if (bulan >= 1 && Math.abs(hari - bulan * 30.4375) <= 3) { return bulan + ' (' + pjKata(bulan) + ') bulan'; }
        return hari + ' (' + pjKata(hari) + ') hari';
    }
    function pjPeriode(mulai, selesai) {
        var a = pjTgl(mulai), b = pjTgl(selesai);
        if (!a || !b || b < a) { return ''; }
        if (a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth()) { return a.getUTCDate() + ' s.d. ' + pjHb(b) + ' ' + b.getUTCFullYear(); }
        if (a.getUTCFullYear() === b.getUTCFullYear()) { return pjHb(a) + ' s.d. ' + pjHb(b) + ' ' + b.getUTCFullYear(); }
        return pjHb(a) + ' ' + a.getUTCFullYear() + ' s.d. ' + pjHb(b) + ' ' + b.getUTCFullYear();
    }

    Alpine.data('suratPerpanjanganForm', function () {
        return {
            lama: '', lamaTerkunci: false, baru: '', mulaiAjuan: '', alasanPilih: '0', alasanLain: '', alasanSaran: [], batas: {},

            init: function () {
                var c = JSON.parse(this.$el.getAttribute('data-config') || '{}');
                this.lama = c.lama || '';
                this.lamaTerkunci = !!c.lamaTerkunci;
                this.baru = c.baru || '';
                this.mulaiAjuan = c.mulaiAjuan || '';
                this.alasanPilih = c.alasanPilih || '0';
                this.alasanLain = c.alasanLain || '';
                this.alasanSaran = c.alasanSaran || [];
                this.batas = c.batas || {};
            },

            adaLama: function () { return pjTgl(this.lama) !== null; },
            minBaru: function () { return this.adaLama() ? pjTambahHari(this.lama, 1) : ''; },
            /* Tombol cepat: 14 = +2 minggu (hari), 1/2/3 = bulan. */
            pakaiHari: function (n) { if (this.adaLama()) { this.baru = pjTambahHari(this.lama, n); } },
            pakaiBulan: function (n) { if (this.adaLama()) { this.baru = pjTambahBulan(this.lama, n); } },
            cepatAktif: function (jenis, n) {
                if (!this.adaLama() || !this.baru) { return false; }
                return this.baru === (jenis === 'hari' ? pjTambahHari(this.lama, n) : pjTambahBulan(this.lama, n));
            },
            teksLamaIndo: function () { return pjIndo(this.lama); },

            /* "Diperpanjang 31 hari: 31 Oktober s.d. 30 November 2026." + kalimat tercetak. */
            ringkasHari: function () {
                var hari = pjSelisih(this.lama, this.baru);
                if (hari === null || hari <= 0) { return ''; }
                return 'Diperpanjang ' + hari + ' hari: ' + pjPeriode(pjTambahHari(this.lama, 1), this.baru) + '.';
            },
            ringkasCetak: function () {
                var lama = pjLama(this.lama, this.baru);
                return lama ? lama + ', ' + pjPeriode(pjTambahHari(this.lama, 1), this.baru) : '';
            },
            peringatanList: function () {
                var out = [], hari = pjSelisih(this.lama, this.baru);
                if (!this.adaLama() || !pjTgl(this.baru)) { return out; }
                if (hari <= 0) { return ['Tanggal perpanjangan harus sesudah tanggal selesai semula (' + pjIndo(this.lama) + ').']; }
                if (this.batas.maksHari > 0 && hari > this.batas.maksHari) { out.push('Perpanjangan ' + hari + ' hari terlalu lama (paling lama ' + this.batas.maksHari + ' hari).'); }
                if (this.batas.akhir && this.baru > this.batas.akhir) { out.push('Melewati batas selesai PKL sekolah ' + pjIndo(this.batas.akhir) + '.'); }
                if (this.batas.maks > 0 && this.mulaiAjuan) {
                    var total = pjSelisih(this.mulaiAjuan, this.baru) + 1;
                    if (total > this.batas.maks) { out.push('Total PKL menjadi ' + total + ' hari, lebih dari maksimal ' + this.batas.maks + ' hari di Pengaturan PKL.'); }
                }
                return out;
            },

            /* Alasan seperti yang akan tercetak (sama dengan SuratPerpanjangan::alasan / SuratPkl::alasan). */
            alasanKalimat: function () {
                if (this.alasanPilih !== 'lain') { var s = this.alasanSaran[Number(this.alasanPilih)]; return s ? s[0] : ''; }
                var t = this.alasanLain.replace(/\s+/g, ' ').trim().replace(/^(karena|sebab|dikarenakan|disebabkan)\s+/i, '').replace(/[ .]+$/, '');
                var a = t.charAt(0), b = t.charAt(1);
                if (b && a !== a.toLowerCase() && b === b.toLowerCase() && b !== b.toUpperCase()) { t = a.toLowerCase() + t.slice(1); }
                return t;
            },
        };
    });
});
