/**
 * Pengaturan PKL — dua komponen Alpine kecil (F9, siap CSP; dulu objek x-data bermetode sebaris dengan fungsi panah):
 *   pklPolaSurat : contoh hasil format nomor surat & pola nama berkas. Nilai awal dari data-pola / data-berkas pada akar;
 *                  isian memakai x-model="pola" / x-model="berkas"; contoh: contohNomorSurat() / contohNamaBerkas().
 *   pklPesanWa   : contoh hasil templat pesan WhatsApp. Nilai awal dari data-pesan / data-pesan-balasan, nama sekolah dari
 *                  data-sekolah; isian x-model="pesan" / x-model="pesanB"; contoh: contohPesanWa(pesan).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var ROMAWI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    var dua = function (n) { return ('0' + n).slice(-2); };
    // Ganti tiap penanda {..} dengan contoh nilainya.
    var ganti = function (teks, peta) {
        var hasil = String(teks || '');
        Object.keys(peta).forEach(function (k) { hasil = hasil.split(k).join(String(peta[k])); });
        return hasil;
    };

    Alpine.data('pklPolaSurat', function () {
        return {
            pola: '',
            berkas: '',
            init: function () {
                this.pola = this.$el.getAttribute('data-pola') || '';
                this.berkas = this.$el.getAttribute('data-berkas') || '';
            },
            // Contoh nomor surat ke-7 hari ini.
            contohNomorSurat: function () {
                var d = new Date();
                return ganti(this.pola, {
                    '{urut}': '7', '{urut3}': '007', '{urut4}': '0007', '{tgl}': dua(d.getDate()), '{bln}': dua(d.getMonth() + 1),
                    '{bln_romawi}': ROMAWI[d.getMonth()], '{thn}': d.getFullYear(),
                });
            },
            contohNamaBerkas: function () {
                return ganti(this.berkas, {
                    '{urut}': '295', '{urut3}': '295', '{urut4}': '0295', '{nama_depan}': 'Ilyasha', '{nama_pengaju}': 'Ilyasha Hawari',
                    '{all}': 'ALL', '{kelas}': 'XII TKJ 5', '{perusahaan}': 'PT Antarestar', '{thn}': new Date().getFullYear(),
                }).replace(/\s+/g, ' ').trim() + '.docx';
            },
        };
    });

    Alpine.data('pklPesanWa', function () {
        var sekolah = 'SMK Bina Nusa';
        var tautan = 'https://pklbinanusa.kangmuslim.com/';
        return {
            pesan: '',
            pesanB: '',
            pesanP: '',
            pesanT: '',
            init: function () {
                this.pesan = this.$el.getAttribute('data-pesan') || '';
                this.pesanB = this.$el.getAttribute('data-pesan-balasan') || '';
                this.pesanP = this.$el.getAttribute('data-pesan-perbaikan') || '';
                this.pesanT = this.$el.getAttribute('data-pesan-tolak') || '';
                var s = this.$el.getAttribute('data-sekolah');
                if (s !== null) { sekolah = s; }
                var t = this.$el.getAttribute('data-tautan');
                if (t !== null) { tautan = t; }
            },
            contohPesanWa: function (t) {
                return ganti(t, {
                    '{nama}': 'Dewi Lestari', '{kelas}': 'XI TKJ 1', '{perusahaan}': 'PT Antarestar', '{nomor_surat}': '007/SMK-BN/PKL/X/2026',
                    '{tanggal_surat}': '8 Oktober 2026', '{sekolah}': sekolah, '{kode}': 'PKL-00012',
                    '{alasan}': 'Alamat perusahaan belum lengkap', '{tautan}': tautan,
                });
            },
        };
    });
});
