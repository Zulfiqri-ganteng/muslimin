/**
 * Halaman Master Mata Pelajaran.
 * Perluasan masterList: modal "Atur Guru" (kompetensi per mapel).
 * Data dioper lewat atribut: data-all-guru, data-komp-map (JSON).
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
                this.allGuru = window.readJson(this.$el, 'all-guru', []);
                this.kompMap = window.readJson(this.$el, 'komp-map', {});
            },

            // Dipanggil dari tombol "Atur Guru" (id & nama di atribut data-*).
            openKompetensiEl: function (el) {
                var id = parseInt(el.getAttribute('data-id'), 10);
                this.kompMapel = el.getAttribute('data-nama') || '';
                this.kompUrl   = this.base + '/kompetensi/' + id;
                this.selected  = (this.kompMap[id] || []).map(Number);
                this.kompOpen  = true;
            },
        });
    });
});
