/**
 * Halaman Master Hari.
 * Perluasan masterList: kolom `aktif` (0/1 dari DB) dipetakan ke boolean
 * agar checkbox pada form edit tercentang dengan benar.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('hariPage', function () {
        return window.masterList({
            mapEdit: function (r) {
                return {
                    nama: r.nama || '',
                    urutan: r.urutan,
                    aktif: Number(r.aktif) === 1,
                };
            },
        });
    });
});
