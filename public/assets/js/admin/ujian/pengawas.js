/**
 * Ujian → tab Jadwal Pengawas, mode lihat (tombol Ubah & Hapus, klien 2026-11-24). Komponen "pengawasGrid": klik angka ruang atau ikon
 * pensil ([data-ubah-sesi]) di baris sesi (<tr data-sesi data-judul data-isi>) membuka dialog kit "ubah-pengawas" berisi satu pilihan
 * guru per ruang — disalin dari <template id="tpl-pengawas-ruang"> supaya daftar guru cukup ditulis sekali di halaman. Guru yang dipilih
 * di dua ruang langsung ditandai (penolakan sebenarnya tetap di server). data-lama = isian yang ditolak server → dialog dibuka lagi.
 * Tanpa isian JSON di Alpine → siap Alpine versi CSP. Hapus = tombol submit biasa ke form-kosongkan (tanpa skrip).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('pengawasGrid', function () {
        var akar = null;
        var nRuang = 0;
        var baca = function (teks) {
            try { return JSON.parse(teks || '{}') || {}; } catch (err) { return {}; }
        };
        var daftar = function () { return document.getElementById('pg-daftar-ruang'); };
        var semuaPilihan = function () { return Array.prototype.slice.call(daftar().querySelectorAll('select')); };

        // Guru yang sama di dua ruang → tulisan di bawah kedua pilihannya.
        var tandaiGanda = function () {
            var per = {};
            semuaPilihan().forEach(function (s) {
                if (s.value) { (per[s.value] = per[s.value] || []).push(s.getAttribute('data-ruang')); }
            });
            semuaPilihan().forEach(function (s) {
                var ruang = s.getAttribute('data-ruang');
                var lain = (per[s.value] || []).filter(function (r) { return r !== ruang; });
                var pesan = s.parentNode.querySelector('.err-msg');
                pesan.hidden = lain.length === 0;
                pesan.textContent = lain.length === 0 ? '' : 'Guru ini juga dipilih di ruang ' + lain.join(', ') + '.';
            });
        };

        // Satu pilihan per ruang; nilai yang tidak ada di daftar guru tetap ditampilkan (server yang menolak) supaya tidak hilang diam-diam.
        var bangun = function (isi, fokus) {
            var wadah = daftar();
            var contoh = document.getElementById('tpl-pengawas-ruang');
            wadah.textContent = '';
            for (var r = 1; r <= nRuang; r++) {
                var baris = contoh.content.firstElementChild.cloneNode(true);
                var label = baris.querySelector('label');
                var pilih = baris.querySelector('select');
                var nilai = isi[r] === undefined || isi[r] === null ? '' : String(isi[r]);
                label.textContent = 'Ruang ' + r;
                label.htmlFor = pilih.id = 'pg-ruang-' + r;
                pilih.name = 'sesi[' + r + ']';
                pilih.setAttribute('data-ruang', String(r));
                pilih.value = nilai;
                if (nilai !== '' && pilih.value !== nilai) {
                    pilih.add(new Option(nilai + ' (tidak ada di daftar guru pengawas)', nilai));
                    pilih.value = nilai;
                }
                if (String(r) === String(fokus)) { pilih.setAttribute('autofocus', ''); } // dialog kit memfokuskan [autofocus]
                wadah.appendChild(baris);
            }
            tandaiGanda();
        };

        return {
            kunci: '',
            judul: '',

            init: function () {
                var self = this;
                akar = this.$el;
                nRuang = parseInt(akar.getAttribute('data-ruang'), 10) || 0;
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-ubah-sesi]') : null;
                    var tr = b && akar.contains(b) ? b.closest('tr[data-sesi]') : null;
                    if (tr) { self.bukaSesi(tr, baca(tr.getAttribute('data-isi')), b.getAttribute('data-ruang')); }
                });
                daftar().addEventListener('change', tandaiGanda);
                var lama = baca(akar.getAttribute('data-lama'));
                if (!lama.kunci) { return; }
                this.$nextTick(function () {
                    var tr = Array.prototype.filter.call(akar.querySelectorAll('tr[data-sesi]'), function (x) {
                        return x.getAttribute('data-sesi') === lama.kunci;
                    })[0];
                    if (tr) { self.bukaSesi(tr, lama.isi || {}, null); }
                });
            },
            bukaSesi: function (tr, isi, ruang) {
                this.kunci = tr.getAttribute('data-sesi');
                this.judul = tr.getAttribute('data-judul');
                bangun(isi, ruang);
                window.Ui.bukaDialog('ubah-pengawas');
            },
        };
    });
});
