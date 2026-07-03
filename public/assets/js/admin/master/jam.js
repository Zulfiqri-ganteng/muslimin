/**
 * Halaman Master Jam Pelajaran.
 * Perluasan masterList: waktu HH:MM:SS dari DB dipotong jadi HH:MM untuk
 * input type="time", dan `is_istirahat` dipetakan ke boolean.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('jamPage', function () {
        return window.masterList({
            mapEdit: function (r) {
                return {
                    shift: r.shift || 'pagi',
                    jam_ke: r.jam_ke,
                    waktu_mulai: (r.waktu_mulai || '').substring(0, 5),
                    waktu_selesai: (r.waktu_selesai || '').substring(0, 5),
                    is_istirahat: Number(r.is_istirahat) === 1,
                };
            },
        });
    });
});
