/**
 * Halaman Master Siswa.
 * Perluasan masterList: id ikut dibawa ke form, nilai NULL dari DB dipetakan ke
 * string kosong (agar select/input tidak menampilkan "null"), dan tanggal lahir
 * dipotong ke YYYY-MM-DD supaya input type="date" mau menampilkannya.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('siswaPage', function () {
        return window.masterList({
            mapEdit: function (r) {
                var teks = function (v) {
                    return v === null || v === undefined ? '' : String(v);
                };

                return {
                    id: r.id,
                    nis: teks(r.nis),
                    nisn: teks(r.nisn),
                    nama: teks(r.nama),
                    jenis_kelamin: teks(r.jenis_kelamin),
                    tempat_lahir: teks(r.tempat_lahir),
                    // DB bisa mengirim "2009-08-17" atau "2009-08-17 00:00:00"
                    tanggal_lahir: teks(r.tanggal_lahir).substring(0, 10),
                    agama: teks(r.agama),
                    alamat: teks(r.alamat),
                    no_hp: teks(r.no_hp),
                    nama_wali: teks(r.nama_wali),
                    no_hp_wali: teks(r.no_hp_wali),
                    kelas_id: teks(r.kelas_id),
                    tahun_masuk: teks(r.tahun_masuk),
                    status: teks(r.status) || 'aktif',
                    keterangan: teks(r.keterangan),
                };
            },
        });
    });
});
