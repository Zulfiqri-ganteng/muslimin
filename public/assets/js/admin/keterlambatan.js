/**
 * Catat Keterlambatan (menu Guru Piket) — perluasan masterList (dialog Ubah) + kotak cari siswa di formulir catat.
 *   - Cari: ketik ≥ 2 huruf → GET data-cari?q=&tanggal= (hasil dari server, siswa aktif). Panah atas/bawah + Enter memilih;
 *     siswa yang sudah dicatat pada tanggal itu tampil redup dan tidak bisa dipilih.
 *   - Memilih siswa saat mencatat HARI INI mengisi jam datang dengan jam sekarang, kecuali jamnya sudah diubah tangan.
 *   - Kirim tanpa siswa terpilih dicegah (pesan di bawah kotak cari).
 * Elemen akar: data-cari, data-tanggal, data-hari-ini, data-siswa-awal (JSON siswa terpilih sebelum galat simpan).
 * Tombol "Isi no. ortu" (data-isi-hp="url" data-isi-nama): dialog kit "tl-isi-hp" dibuka oleh ui.js (data-buka-dialog); di sini
 * alamat formulirnya & nama siswa diisi. Tombol WA "Kabari ortu" ditangani assets/js/admin/pkl-kabar.js (data-kabar-*).
 */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var b = e.target && e.target.closest ? e.target.closest('[data-isi-hp]') : null;
        var form = document.getElementById('tl-form-hp');
        if (!b || !form) { return; }
        form.setAttribute('action', b.getAttribute('data-isi-hp'));
        var nama = document.getElementById('tl-hp-nama');
        if (nama) { nama.textContent = b.getAttribute('data-isi-nama') || ''; }
        var hp = document.getElementById('tl-hp');
        if (hp) { hp.value = ''; }
    });
})();

document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('keterlambatanPage', function () {
        var akar = null; // di metode Alpine versi CSP this.$el = elemen pemicu
        var urlCari = '';
        var tanggal = '';
        var hariIni = false;
        var jamDiubah = false;
        var nomor = 0; // urutan permintaan cari: jawaban lama yang datang belakangan diabaikan

        function duaAngka(n) { return (n < 10 ? '0' : '') + n; }
        function fokus(sel) {
            setTimeout(function () {
                var el = akar ? akar.querySelector(sel) : null;
                if (el) { el.focus(); }
            }, 30);
        }

        return window.masterList({
            q: '',
            hasil: [],
            siswa: null,
            terbuka: false,
            aktif: -1,
            pesanCari: '',

            onInit: function () {
                akar = this.$el;
                urlCari = akar.getAttribute('data-cari') || '';
                tanggal = akar.getAttribute('data-tanggal') || '';
                hariIni = akar.getAttribute('data-hari-ini') === '1';
                var awal = window.readJson(akar, 'siswa-awal', null);
                if (awal && awal.id) { this.siswa = awal; }
            },

            cari: function () {
                var self = this;
                var q = String(this.q || '').trim();
                this.aktif = -1;
                if (q.length < 2) {
                    this.hasil = [];
                    this.terbuka = false;
                    this.pesanCari = q === '' ? '' : 'Ketik minimal 2 huruf.';
                    return;
                }
                var ke = ++nomor;
                this.pesanCari = 'Mencari…';
                fetch(urlCari + '?q=' + encodeURIComponent(q) + '&tanggal=' + encodeURIComponent(tanggal), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) {
                        if (ke !== nomor) { return; }
                        if (!j || !j.ok) {
                            self.hasil = [];
                            self.terbuka = false;
                            self.pesanCari = 'Pencarian gagal. Muat ulang halaman (mungkin sesi masuk sudah habis).';
                            return;
                        }
                        self.hasil = Array.isArray(j.hasil) ? j.hasil : [];
                        self.terbuka = self.hasil.length > 0;
                        self.pesanCari = self.hasil.length ? '' : 'Tidak ada siswa aktif dengan nama/NIS itu.';
                        self.aktif = self.hasil.findIndex(function (s) { return !s.tercatat; });
                    })
                    .catch(function () {
                        if (ke === nomor) { self.pesanCari = 'Pencarian gagal. Periksa sambungan internet.'; }
                    });
            },
            sudah: function (s) { return !!(s && s.tercatat); },
            ketHasil: function (s) {
                var bagian = [s.kelas || 'tanpa kelas'];
                if (s.nis) { bagian.push('NIS ' + s.nis); }
                if (s.tercatat) { bagian.push('sudah dicatat (' + s.tercatat + ')'); }
                return bagian.join(' · ');
            },
            kelasHasil: function (i, s) {
                return { 'bg-gray-100 dark:bg-gray-600': i === this.aktif, 'opacity-60 cursor-not-allowed': this.sudah(s) };
            },
            pindahHasil: function (arah) {
                if (!this.terbuka || !this.hasil.length) { return; }
                var n = this.hasil.length, i = this.aktif;
                for (var k = 0; k < n; k++) {
                    i = (i + arah + n) % n;
                    if (!this.sudah(this.hasil[i])) { this.aktif = i; return; }
                }
            },
            pilihAktif: function () {
                if (this.terbuka && this.aktif >= 0 && this.hasil[this.aktif]) { this.pilih(this.hasil[this.aktif]); }
            },
            tutupHasil: function () { this.terbuka = false; },
            pilih: function (s) {
                if (!s || this.sudah(s)) { return; }
                this.siswa = s;
                this.q = '';
                this.hasil = [];
                this.terbuka = false;
                this.pesanCari = '';
                if (hariIni && !jamDiubah && this.$refs.jam) {
                    var d = new Date();
                    this.$refs.jam.value = duaAngka(d.getHours()) + ':' + duaAngka(d.getMinutes());
                }
                fokus('#tl-sanksi');
            },
            gantiSiswa: function () {
                this.siswa = null;
                fokus('#tl-cari');
            },
            tandaiJam: function () { jamDiubah = true; },

            siswaId: function () { return this.siswa ? this.siswa.id : ''; },
            namaSiswa: function () { return this.siswa ? this.siswa.nama : ''; },
            ketSiswa: function () {
                if (!this.siswa) { return ''; }
                var bagian = [];
                if (this.siswa.kelas) { bagian.push(this.siswa.kelas); }
                if (this.siswa.nis) { bagian.push('NIS ' + this.siswa.nis); }
                return bagian.join(' · ');
            },
            kirim: function (e) {
                if (!this.siswa) {
                    e.preventDefault();
                    this.pesanCari = 'Pilih dulu siswa yang terlambat dari hasil pencarian.';
                    fokus('#tl-cari');
                }
            },
        });
    });
});
