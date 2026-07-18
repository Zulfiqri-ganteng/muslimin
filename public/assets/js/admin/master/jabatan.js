/**
 * Halaman Master Jabatan.
 * Perluasan masterList: `is_struktural` (0/1 dari DB) dipetakan ke boolean agar
 * checkbox tercentang benar saat edit, dan `id` ikut dibawa ke form supaya
 * jabatan tidak bisa dipilih sebagai induk bagi dirinya sendiri.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('jabatanPage', function () {
        return window.masterList({
            mapEdit: function (r) {
                return {
                    id: r.id,
                    kode: r.kode || '',
                    nama: r.nama || '',
                    kategori: r.kategori || 'lainnya',
                    // select mengirim string; null dari DB jadi "" (tanpa induk)
                    parent_id: r.parent_id === null || r.parent_id === undefined ? '' : String(r.parent_id),
                    jurusan_id: r.jurusan_id === null || r.jurusan_id === undefined ? '' : String(r.jurusan_id),
                    level: r.level,
                    is_struktural: Number(r.is_struktural) === 1,
                    keterangan: r.keterangan || '',
                };
            },
        });
    });
});
