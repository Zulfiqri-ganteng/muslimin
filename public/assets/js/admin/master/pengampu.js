/**
 * Halaman Penugasan Mengajar (Pengampu).
 * Komponen khusus: pilihan guru disaring sesuai kompetensi mapel,
 * JP terisi otomatis dari standar mapel.
 * Data dioper lewat atribut: data-base, data-all-guru, data-komp-map,
 * data-mapel-jp (JSON).
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('pengampuPage', function () {
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
            bulkSelected: 0,
            allChecked: false, // semua baris tercentang → tombol "Hapus Semua" muncul

            init: function () {
                this.base    = this.$el.getAttribute('data-base') || '';
                this.allGuru = window.readJson(this.$el, 'all-guru', []);
                this.kompMap = window.readJson(this.$el, 'komp-map', {});
                this.mapelJp = window.readJson(this.$el, 'mapel-jp', {});
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
            },
            openEditEl: function (el) {
                var r = JSON.parse(el.getAttribute('data-row') || '{}');
                this.editUrl   = this.base + '/' + r.id;
                this.editMapel = r.kode_mapel + ' — ' + r.nama_mapel;
                this.editGuru  = this.allGuru; // saat edit boleh pilih guru manapun
                this.eform     = { guru_id: Number(r.guru_id), jp: Number(r.jp) };
                this.editOpen  = true;
            },

            /* ---- pilih baris & hapus massal (penugasan kelas terpilih) ---- */
            refresh: function () {
                var all     = this.$el.querySelectorAll('.row-check').length;
                var checked = this.$el.querySelectorAll('.row-check:checked').length;
                this.bulkSelected = checked;
                this.allChecked   = all > 0 && checked === all;
            },
            toggleAll: function (e) {
                this.$el.querySelectorAll('.row-check').forEach(function (c) { c.checked = e.target.checked; });
                this.refresh();
            },
            submitBulk: function (mode) {
                var form = this.$refs.bulkForm;
                form.querySelectorAll('input[name="ids[]"]').forEach(function (n) { n.remove(); });

                if (mode === 'all') {
                    if (!window.confirm('HAPUS SEMUA penugasan pada kelas ini? Jadwal yang memakai penugasan tersebut ikut terhapus.')) { return; }
                    form.querySelector('[name=mode]').value = 'all';
                } else {
                    var checked = Array.prototype.slice.call(this.$el.querySelectorAll('.row-check:checked'));
                    if (checked.length === 0) { window.alert('Centang dulu data yang ingin dihapus.'); return; }
                    if (!window.confirm('Hapus ' + checked.length + ' penugasan terpilih? Jadwal yang memakainya ikut terhapus.')) { return; }
                    checked.forEach(function (c) {
                        var i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = 'ids[]';
                        i.value = c.value;
                        form.appendChild(i);
                    });
                    form.querySelector('[name=mode]').value = 'selected';
                }
                form.submit();
            },
        };
    });
});
