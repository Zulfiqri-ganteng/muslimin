/**
 * Halaman Master Guru.
 * Perluasan masterList: modal "Atur Jabatan" (jabatan per guru, boleh lebih
 * dari satu dengan satu penanda utama).
 * Data dioper lewat atribut: data-all-jabatan, data-jabatan-map (JSON).
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

            onInit: function () {
                this.allJabatan = window.readJson(this.$el, 'all-jabatan', []);
                this.jabatanMap = window.readJson(this.$el, 'jabatan-map', {});
            },

            // Dipanggil dari tombol "Atur Jabatan" (id & nama di atribut data-*).
            openJabatanEl: function (el) {
                var id = parseInt(el.getAttribute('data-id'), 10);
                var punya = this.jabatanMap[id] || [];

                this.jabatanGuru     = el.getAttribute('data-nama') || '';
                this.jabatanUrl      = this.base + '/jabatan/' + id;
                this.selectedJabatan = punya.map(function (j) { return Number(j.id); });

                var utama = punya.filter(function (j) { return j.is_utama; })[0];
                this.utamaId = utama ? Number(utama.id) : (this.selectedJabatan[0] || null);

                this.jabatanOpen = true;
            },

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
