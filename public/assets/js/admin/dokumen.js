/**
 * Penjelajah Manajemen Dokumen (F10) — dulu objek x-data sebaris bermetode (fungsi panah, spread, tulis $refs).
 *
 *   penjelajahDokumen  akar halaman: centang berkas, pilih semua, seret-lepas berkas ke folder, dialog Pindahkan.
 *                      Butir berkas ber-data-dok-id="ID"; status tampil lewat atribut (:data-terpilih / :data-diseret /
 *                      :data-sasaran) yang diberi gaya di admin.css (.dok-*), bukan :class.
 *   unggahDokumen      kotak unggah di dialog Unggah: seret berkas ke kotak, tampilkan jumlah & nama berkas terpilih.
 *
 * Pemindahan lewat seret-lepas memakai formulir tersembunyi [data-form-seret] yang menuju rute yang sama dengan tombol
 * "Pindahkan" — aturan keamanannya satu pintu.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('penjelajahDokumen', function () {
        var akar = null;
        var semuaId = function () {
            return Array.prototype.map.call(akar ? akar.querySelectorAll('[data-dok-id]') : [], function (el) {
                return parseInt(el.getAttribute('data-dok-id'), 10);
            }).filter(function (n, i, arr) { return n > 0 && arr.indexOf(n) === i; });
        };
        return {
            pilih: [],
            dndIds: [],
            dndTarget: null,
            pindahTerbuka: false,

            init: function () { akar = this.$el; },

            adaPilihan: function () { return this.pilih.length > 0; },
            sedangSeret: function () { return this.dndIds.length > 0; },
            dipilih: function (id) { return this.pilih.indexOf(id) !== -1; },
            diseret: function (id) { return this.dndIds.indexOf(id) !== -1; },
            sasaranSeret: function (kunci) { return this.dndTarget === kunci; },
            teksPilihan: function () { return this.pilih.length + ' dokumen dipilih'; },
            teksSeret: function () { return 'Lepaskan di atas folder untuk memindahkan ' + this.dndIds.length + ' dokumen'; },

            centangDok: function (id) {
                var i = this.pilih.indexOf(id);
                if (i < 0) { this.pilih.push(id); } else { this.pilih.splice(i, 1); }
            },
            semuaDipilih: function () {
                var ids = semuaId();
                return ids.length > 0 && ids.every(function (n) { return this.pilih.indexOf(n) !== -1; }, this);
            },
            centangSemuaDok: function () { this.pilih = this.semuaDipilih() ? [] : semuaId(); },
            batalPilih: function () { this.pilih = []; },

            bukaPindah: function () { if (this.adaPilihan()) { this.pindahTerbuka = true; } },
            tutupPindah: function () { this.pindahTerbuka = false; },

            /* Seret berkas: bila yang diseret termasuk yang sedang dicentang, SEMUA yang tercentang ikut pindah — itu yang
               diharapkan orang setelah memilih beberapa berkas. Bila tidak, hanya yang diseret. */
            mulaiSeret: function (id, ev) {
                this.dndIds = this.dipilih(id) ? this.pilih.slice() : [id];
                if (ev && ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    ev.dataTransfer.setData('text/plain', this.dndIds.join(','));
                }
            },
            selesaiSeret: function () { this.dndIds = []; this.dndTarget = null; },
            lewatiFolder: function (kunci, ev) {
                if (!this.sedangSeret()) { return; }
                if (ev && ev.dataTransfer) { ev.dataTransfer.dropEffect = 'move'; }
                this.dndTarget = kunci;
            },
            tinggalkanFolder: function () { this.dndTarget = null; },
            jatuhkan: function (folderId) {
                if (!this.sedangSeret()) { return; }
                var f = akar ? akar.querySelector('form[data-form-seret]') : null;
                this.dndTarget = null;
                if (!f) { return; }
                f.querySelector('input[name="tujuan"]').value = String(folderId || 0);
                Array.prototype.forEach.call(f.querySelectorAll('input[name="ids[]"]'), function (el) { el.parentNode.removeChild(el); });
                this.dndIds.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'ids[]';
                    inp.value = String(id);
                    f.appendChild(inp);
                });
                f.submit();
            },
        };
    });

    Alpine.data('unggahDokumen', function () {
        var akar = null;
        var isian = function () { return akar ? akar.querySelector('input[type="file"]') : null; };
        return {
            seret: false,
            nama: [],

            init: function () { akar = this.$el; },

            seretMasuk: function () { this.seret = true; },
            seretKeluar: function () { this.seret = false; },
            jatuhkanBerkas: function (ev) {
                var inp = isian();
                this.seret = false;
                if (!inp || !ev || !ev.dataTransfer || !ev.dataTransfer.files) { return; }
                try { inp.files = ev.dataTransfer.files; } catch (err) { return; }
                this.catatBerkasUnggah();
            },
            catatBerkasUnggah: function () {
                var inp = isian();
                this.nama = Array.prototype.map.call(inp && inp.files ? inp.files : [], function (f) { return f.name; });
            },
            adaBerkas: function () { return this.nama.length > 0; },
            teksBerkas: function () {
                if (!this.nama.length) { return ''; }
                var tampil = this.nama.slice(0, 3).join(', ');
                return this.nama.length + ' berkas dipilih: ' + tampil + (this.nama.length > 3 ? ', dan ' + (this.nama.length - 3) + ' lainnya' : '');
            },
        };
    });
});
