/**
 * Halaman Master Mata Pelajaran.
 * Perluasan masterList: dialog "Atur guru" (kompetensi per mapel).
 * Data dioper lewat atribut: data-all-guru, data-komp-map (JSON). Tombol "Atur guru" = <button data-atur-guru data-id data-nama>
 * ditangkap lewat delegasi klik (rombak tampilan F5: tanpa $el di isian Alpine → siap Alpine versi CSP).
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('mapelPage', function () {
        return window.masterList({
            kompOpen: false,
            allGuru: [],
            kompMap: {},
            selected: [],
            kompUrl: '',
            kompMapel: '',

            onInit: function () {
                var self = this;
                var akar = this.$el;
                this.allGuru = window.readJson(akar, 'all-guru', []);
                this.kompMap = window.readJson(akar, 'komp-map', {});
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-atur-guru]') : null;
                    if (b && akar.contains(b)) { self.openKompetensiEl(b); }
                });
            },

            // Dipanggil dari tombol "Atur guru" (id & nama di atribut data-*).
            openKompetensiEl: function (el) {
                var id = parseInt(el.getAttribute('data-id'), 10);
                this.kompMapel = el.getAttribute('data-nama') || '';
                this.kompUrl   = this.base + '/kompetensi/' + id;
                this.selected  = (this.kompMap[id] || []).map(Number);
                this.kompOpen  = true;
            },
            tutupKompetensi: function () { this.kompOpen = false; },
        });
    });
});
