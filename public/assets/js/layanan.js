/**
 * LAYANAN SISWA (publik) — komponen Alpine `layananMasuk`: pilih kelas → muat nama (GET layanan/siswa) → pilih nama → isi NISN
 * atau tanggal lahir → formulir biasa dikirim ke server (POST layanan/masuk). Pemeriksa sesungguhnya = server
 * (App\Libraries\LayananSiswa::masuk). Siap CSP: ekspresi di view hanya properti / pemanggilan metode.
 * data-config: { urlSiswa, lama: { kelas, siswa } } — `lama` = pilihan sebelum galat (dipilihkan lagi).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var KEPALA = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    Alpine.data('layananMasuk', function () {
        var akar = null;

        return {
            cfg: {},
            kelasId: '',
            siswaList: [],
            memuat: false,
            pesanDaftar: '',
            cari: '',
            pilih: null,

            init: function () {
                akar = this.$el;
                this.cfg = JSON.parse(akar.getAttribute('data-config') || '{}');
                var lama = this.cfg.lama || {};
                if (lama.kelas) {
                    this.kelasId = String(lama.kelas);
                    this.muatSiswa(lama.siswa || 0);
                }
            },

            /* Muat nama siswa kelas terpilih; $pilihId (opsional) = nama yang dipilihkan lagi setelah galat. */
            muatSiswa: function (pilihId) {
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
                            var lama = Number(pilihId || 0);
                            self.siswaList.forEach(function (s) { if (s.id === lama) { self.pilih = s; } });
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

            adaDaftar: function () { return this.kelasId !== '' && !this.memuat && this.siswaList.length > 0; },
            kosongCari: function () { return this.siswaTersaring().length === 0; },
            terpilih: function (s) { return this.pilih !== null && this.pilih.id === s.id ? 'true' : 'false'; },
            idPilih: function () { return this.pilih ? String(this.pilih.id) : ''; },
            namaPilih: function () { return this.pilih ? this.pilih.nama : ''; },

            pilihSiswa: function (s) {
                var self = this;
                this.pilih = s;
                this.$nextTick(function () {
                    var panel = akar.querySelector('[data-panel-masuk]');
                    if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
            },

            gantiNama: function () { this.pilih = null; },
        };
    });
});
