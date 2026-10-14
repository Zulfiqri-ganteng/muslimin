/**
 * Halaman Master Guru.
 * Perluasan masterList: dialog "Atur jabatan" (jabatan per guru, boleh lebih dari satu dengan satu penanda utama).
 * Data dioper lewat atribut: data-all-jabatan, data-jabatan-map (JSON).
 * Siap Alpine versi CSP (rombak tampilan F5): tombol "Atur jabatan" = <button data-atur-jabatan data-id data-nama> lewat delegasi
 * klik (tanpa $el di isian); pemeriksaan di isian memakai metode komponen (dipilih, bukanDiriSendiri), bukan global String / .includes.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('guruPage', function () {
        return window.masterList({
            jabatanOpen: false,
            allJabatan: [],
            jabatanMap: {},
            selectedJabatan: [],
            utamaId: null,
            jabatanUrl: '',
            jabatanGuru: '',

            // Baris DB → form: ikut_absensi "0"/"1" → boolean, induk_id → string.
            mapEdit: function (r) {
                var f = {};
                var d = this.defaults;
                Object.keys(d).forEach(function (k) {
                    f[k] = (r[k] === null || r[k] === undefined) ? d[k] : r[k];
                });
                f.ikut_absensi = String(r.ikut_absensi === undefined || r.ikut_absensi === null ? '1' : r.ikut_absensi) !== '0';
                f.bukan_pengajar = String(r.bukan_pengajar === undefined || r.bukan_pengajar === null ? '0' : r.bukan_pengajar) === '1';
                f.induk_id = r.induk_id ? String(r.induk_id) : '';
                f.id = r.id;
                return f;
            },

            onInit: function () {
                var self = this;
                var akar = this.$el;
                this.allJabatan = window.readJson(akar, 'all-jabatan', []);
                this.jabatanMap = window.readJson(akar, 'jabatan-map', {});
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-atur-jabatan]') : null;
                    if (b && akar.contains(b)) { self.openJabatanEl(b); }
                });
            },

            // Pilihan "Data ganda dari": guru yang sedang diubah tidak boleh memilih dirinya sendiri.
            bukanDiriSendiri: function (id) {
                return String(this.form.id === undefined || this.form.id === null ? '' : this.form.id) !== String(id);
            },

            // Dipanggil dari tombol "Atur jabatan" (id & nama di atribut data-*).
            openJabatanEl: function (el) {
                var id = parseInt(el.getAttribute('data-id'), 10);
                var punya = this.jabatanMap[id] || [];

                this.jabatanGuru     = el.getAttribute('data-nama') || '';
                this.jabatanUrl      = this.base + '/jabatan/' + id;
                this.selectedJabatan = punya.map(function (j) { return Number(j.id); });

                var utama = punya.filter(function (j) { return j.is_utama; })[0];
                this.utamaId = utama ? Number(utama.id) : (this.selectedJabatan[0] || null);

                this.jabatanOpen = true;
                this.fokusDialog();
            },
            tutupJabatan: function () { this.jabatanOpen = false; },

            // Jabatan ini sedang dicentang?
            dipilih: function (id) { return this.selectedJabatan.indexOf(Number(id)) !== -1; },

            /**
             * Jaga agar penanda "utama" selalu menunjuk jabatan yang tercentang:
             * saat jabatan utama dilepas centangnya, utama pindah ke pilihan
             * pertama yang tersisa (server juga memastikan hal yang sama).
             */
            rapikanUtama: function () {
                if (this.selectedJabatan.indexOf(this.utamaId) === -1) {
                    this.utamaId = this.selectedJabatan.length ? this.selectedJabatan[0] : null;
                }
            },
        });
    });
});
