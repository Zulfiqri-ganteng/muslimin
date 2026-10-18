/**
 * Halaman Pengumuman (F10) — perluasan masterList (dulu fungsi global pengPage() di skrip sebaris).
 * Kolom `is_aktif` (0/1 dari DB) dipetakan ke boolean agar kotak centang di form ubah tercentang dengan benar.
 * P2b: kategori + gambar opsional — tiap dialog dibuka, pilihan berkas lama dikosongkan dan pratinjau = gambar tersimpan;
 * centang "Hapus gambar" hanya muncul bila pengumuman yang diubah punya gambar.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('pengumumanPage', function () {
        var akar = null; // elemen root (di metode Alpine versi CSP this.$el = elemen pemicu)
        return window.masterList({
            pratinjau: '',
            punyaGambarLama: false,
            onInit: function () {
                var self = this;
                akar = this.$el;
                this.$watch('open', function (buka) {
                    if (!buka) { return; }
                    var inp = akar.querySelector('input[type="file"][name="gambar"]');
                    if (inp) { inp.value = ''; }
                    self.punyaGambarLama = self.mode !== 'add' && !!self.form.url;
                    self.gantiPratinjau(self.form.url || '');
                });
            },
            mapEdit: function (r) {
                return {
                    id: r.id,
                    judul: r.judul || '',
                    isi: r.isi || '',
                    kategori: r.kategori || 'Pengumuman',
                    is_aktif: Number(r.is_aktif) === 1,
                    url: r.url || '',
                    hapus_gambar: false,
                };
            },
            pilihGambar: function (e) {
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
