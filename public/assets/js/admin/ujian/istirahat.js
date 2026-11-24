/**
 * Ujian → tab Jadwal Ujian → bagian Istirahat (2026-11-24). Komponen "istirahatUjian": satu dialog kit "atur-istirahat" untuk tambah &
 * ubah. Pembuka = tombol mana pun ber-atribut data-atur-istirahat + data-row (JSON {hari, mode, m1, s1, …}) — di bagian Istirahat, di
 * baris ISTIRAHAT daftar jadwal, dan di menu "Cetak & lainnya" (karena itu didengarkan di document). Tanpa isian JSON di Alpine → siap CSP.
 * data-lama = isian sebelum ditolak server (dialognya dibuka server lewat data-awal-buka).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('istirahatUjian', function () {
        var MAKS = 3;
        var kosong = function () { return { hari: '', mode: 'atur', m1: '', s1: '', m2: '', s2: '', m3: '', s3: '' }; };
        var baca = function (teks) {
            try { return JSON.parse(teks || '{}') || {}; } catch (err) { return {}; }
        };
        var hitung = function (f) { return f.m3 || f.s3 ? 3 : (f.m2 || f.s2 ? 2 : 1); };

        return {
            form: kosong(),
            jumlah: 1, // baris jam yang tampil (1–3)

            init: function () {
                var self = this;
                this.isi(baca(this.$el.getAttribute('data-lama')));
                document.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-atur-istirahat]') : null;
                    if (!b) { return; }
                    self.isi(baca(b.getAttribute('data-row')));
                    window.Ui.bukaDialog('atur-istirahat');
                });
            },
            isi: function (r) {
                this.form = Object.assign(kosong(), r);
                this.jumlah = hitung(this.form);
            },

            modeAtur: function () { return this.form.mode === 'atur'; },
            tampil2: function () { return this.jumlah >= 2; },
            tampil3: function () { return this.jumlah >= 3; },
            bisaTambah: function () { return this.jumlah < MAKS; },
            bisaKurang: function () { return this.jumlah > 1; },
            tambahBaris: function () { if (this.jumlah < MAKS) { this.jumlah += 1; } },
            // Isi baris terakhir ikut dikosongkan supaya tidak terkirim diam-diam.
            kurangBaris: function () {
                if (this.jumlah < 2) { return; }
                this.form['m' + this.jumlah] = '';
                this.form['s' + this.jumlah] = '';
                this.jumlah -= 1;
            },
        };
    });
});
