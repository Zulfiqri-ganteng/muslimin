/**
 * Halaman Ketersediaan Guru.
 * Grid toggle slot "tidak tersedia" per hari-jam.
 * Data dioper lewat atribut: data-selected (JSON array kunci "hari-jam"),
 * data-jam-ids (JSON array id jam KBM shift aktif).
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('ketPage', function () {
        return {
            sel: [],
            jamIds: [],

            init: function () {
                this.sel    = window.readJson(this.$el, 'selected', []);
                this.jamIds = window.readJson(this.$el, 'jam-ids', []);
            },

            has: function (k) { return this.sel.indexOf(k) !== -1; },
            toggle: function (k) {
                var i = this.sel.indexOf(k);
                if (i === -1) { this.sel.push(k); } else { this.sel.splice(i, 1); }
            },
            toggleDay: function (hariId) {
                var sel  = this.sel;
                var keys = this.jamIds.map(function (j) { return hariId + '-' + j; });
                var allOn = keys.every(function (k) { return sel.indexOf(k) !== -1; });
                if (allOn) {
                    this.sel = sel.filter(function (k) { return keys.indexOf(k) === -1; });
                } else {
                    keys.forEach(function (k) { if (sel.indexOf(k) === -1) { sel.push(k); } });
                }
            },
            clearAll: function () { this.sel = []; },
        };
    });
});
