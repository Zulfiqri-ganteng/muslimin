/**
 * Halaman Foto Sekolah (P2a rombak publik) — perluasan masterList + pratinjau foto di dialog.
 * Tiap dialog dibuka: pilihan berkas lama dikosongkan, pratinjau = foto tersimpan (mode ubah) atau kosong (mode tambah).
 * Kolom 0/1 dari DB dipetakan ke boolean agar saklar di form ubah menyala dengan benar.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('fotoSekolahPage', function () {
        var akar = null; // elemen root (di metode Alpine versi CSP this.$el = elemen pemicu)
        return window.masterList({
            pratinjau: '',
            onInit: function () {
                var self = this;
                akar = this.$el;
                this.$watch('open', function (buka) {
                    if (!buka) { return; }
                    var inp = akar.querySelector('input[type="file"][name="foto"]');
                    if (inp) { inp.value = ''; }
                    self.gantiPratinjau(self.form.url || '');
                });
            },
            mapEdit: function (r) {
                return {
                    id: r.id,
                    judul: r.judul || '',
                    keterangan: r.keterangan || '',
                    tautan: r.tautan || '',
                    urutan: Number(r.urutan) || 0,
                    tampil_banner: Number(r.tampil_banner) === 1,
                    is_aktif: Number(r.is_aktif) === 1,
                    url: r.url || '',
                };
            },
            pilihFoto: function (e) {
                var f = e && e.target && e.target.files ? e.target.files[0] : null;
                this.gantiPratinjau(f ? URL.createObjectURL(f) : (this.form.url || ''));
            },
            gantiPratinjau: function (url) {
                if (this.pratinjau && this.pratinjau.indexOf('blob:') === 0) { URL.revokeObjectURL(this.pratinjau); }
                this.pratinjau = url;
            },
        });
    });
});
