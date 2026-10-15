/**
 * Halaman Pengumuman (F10) — perluasan masterList (dulu fungsi global pengPage() di skrip sebaris).
 * Kolom `is_aktif` (0/1 dari DB) dipetakan ke boolean agar kotak centang di form ubah tercentang dengan benar.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('pengumumanPage', function () {
        return window.masterList({
            mapEdit: function (r) {
                return {
                    id: r.id,
                    judul: r.judul || '',
                    isi: r.isi || '',
                    is_aktif: Number(r.is_aktif) === 1,
                };
            },
        });
    });
});
