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

                var data = {
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
                    diterima_tanggal: teks(r.diterima_tanggal).substring(0, 10),
                };
                // Kolom biodata buku induk (teks polos) — urutan sama dengan form.
                [
                    'status_keluarga', 'anak_ke', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota',
                    'sekolah_asal', 'diterima_kelas', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu',
                    'ortu_alamat', 'ortu_rt', 'ortu_rw', 'ortu_kelurahan', 'ortu_kecamatan', 'ortu_kota',
                    'ortu_telepon', 'alamat_wali', 'pekerjaan_wali',
                ].forEach(function (k) { data[k] = teks(r[k]); });

                return data;
            },
        });
    });
});
