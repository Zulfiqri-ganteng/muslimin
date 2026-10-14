/**
 * Halaman Penugasan Mengajar (Pengampu).
 * Komponen khusus: pilihan guru disaring sesuai kompetensi mapel,
 * JP terisi otomatis dari standar mapel.
 * Data dioper lewat atribut: data-base, data-all-guru, data-komp-map,
 * data-mapel-jp (JSON).
 * Rombak tampilan F5 (siap Alpine versi CSP): tombol Ubah = <button data-ubah data-row> lewat delegasi klik (tanpa $el di isian);
 * dialog Tambah/Ubah memakai tampilan dialog kit (tutupForm / tutupEdit). Centang & hapus massal ditangani global di app.js
 * (.row-check, [data-bulk], dialog konfirmasi kit) — kode lama di komponen ini tidak dipakai lagi, jadi dibuang.
 */
document.addEventListener('alpine:init', function () {
    // Fokus ke isian pertama dialog yang baru terbuka → Esc & Tab langsung bekerja (sama dengan masterList).
    var fokusDialog = function (akar) {
        setTimeout(function () {
            var el = akar ? akar.querySelector('.dialog[data-buka] .dialog-isi :is(input, select, textarea):not([type="hidden"]):not([disabled])') : null;
            if (el) { el.focus(); }
        }, 60);
    };

    Alpine.data('pengampuPage', function () {
        var akar = null; // elemen root (closure, bukan data reaktif)
        return {
            open: false,
            editOpen: false,
            base: '',
            allGuru: [],
            kompMap: {},
            mapelJp: {},
            form: { mapel_id: '', guru_id: '', jp: 2 },
            eform: { guru_id: '', jp: 2 },
            editUrl: '',
            editMapel: '',
            editGuru: [],

            init: function () {
                var self = this;
                akar = this.$el;
                this.base    = akar.getAttribute('data-base') || '';
                this.allGuru = window.readJson(akar, 'all-guru', []);
                this.kompMap = window.readJson(akar, 'komp-map', {});
                this.mapelJp = window.readJson(akar, 'mapel-jp', {});
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-ubah]') : null;
                    if (b && akar.contains(b)) { self.openEditEl(b); }
                });
            },

            get filteredGuru() {
                var ids = this.kompMap[this.form.mapel_id];
                if (!this.form.mapel_id || !ids || ids.length === 0) { return this.allGuru; }
                return this.allGuru.filter(function (g) { return ids.indexOf(g.id) !== -1; });
            },

            onMapelChange: function () {
                this.form.jp      = this.mapelJp[this.form.mapel_id] || 2;
                this.form.guru_id = '';
            },

            openAdd: function () {
                this.form = { mapel_id: '', guru_id: '', jp: 2 };
                this.open = true;
                fokusDialog(akar);
            },
            tutupForm: function () { this.open = false; },
            openEditEl: function (el) {
                var r = JSON.parse(el.getAttribute('data-row') || '{}');
                this.editUrl   = this.base + '/' + r.id;
                this.editMapel = r.kode_mapel + ' — ' + r.nama_mapel;
                this.editGuru  = this.allGuru; // saat edit boleh pilih guru manapun
                this.eform     = { guru_id: Number(r.guru_id), jp: Number(r.jp) };
                this.editOpen  = true;
                fokusDialog(akar);
            },
            tutupEdit: function () { this.editOpen = false; },
        };
    });
});
