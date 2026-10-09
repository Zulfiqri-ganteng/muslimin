/**
 * SKBM (SK Pembagian Tugas Mengajar) — matriks guru × mapel × kelas dengan simpan otomatis (Alpine).
 *
 * Klik sel = nyalakan/matikan kelas pada baris mapel itu; klik kanan (atau mode "Klik = isi JP", untuk layar sentuh) = isi JP
 * per minggu. Setiap perubahan dikirim ke server (POST JSON); server menyimpan lalu MENGEMBALIKAN angka resmi (JP tiap baris
 * mapel, tiap guru, tiap kelas, dan keseluruhan). Layar hanya menampilkan angka dari server — tidak ada hitungan sendiri di sini.
 * Pengiriman diantre satu per satu; token CSRF terkini dibawa balasan dan dipasang ke semua isian token di halaman.
 *
 * Konfigurasi lewat data-config pada elemen akar (lihat views/admin/skbm/index.php).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    function ribuan(n) {
        return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function setTeks(id, teks) {
        var e = document.getElementById(id);
        if (e) { e.textContent = teks; }
    }
    var KELAS_JP = ['bg-sky-400', 'text-white', 'font-semibold'];
    var KELAS_TANPA_JP = ['bg-amber-300', 'text-amber-900', 'font-semibold'];
    var KELAS_MATI = ['bg-slate-200'];

    Alpine.data('skbmGrid', function () {
        var dasar = {
            cfg: {},
            panel: '',
            mode: 'klik',
            pesan: '',
            galat: false,
            cari: '',
            antre: Promise.resolve(),
            tertunda: 0,
            timer: null,

            init: function () {
                var self = this;
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                this.$watch('cari', function () { self.saring(); });
                window.addEventListener('beforeunload', function (e) {
                    if (self.tertunda > 0) { e.preventDefault(); e.returnValue = ''; }
                });
                var h = window.location.hash.replace('#', '');
                if (['impor', 'salin', 'tambah', 'kosong'].indexOf(h) >= 0) { this.panel = h; }
                this.mulaiTampilan();
            },

            tampil: function (teks, galat) {
                var self = this;
                this.pesan = teks;
                this.galat = !!galat;
                clearTimeout(this.timer);
                this.timer = setTimeout(function () { self.pesan = ''; }, galat ? 6000 : 1500);
            },

            // Form lain di halaman (Impor, Salin, Tambah, Hapus, …) dibuat dengan token saat halaman dimuat; token terkini dari
            // balasan server dipasang ke SEMUA isian token supaya form itu tidak ditolak ("Halaman ini sudah kedaluwarsa").
            segarkanToken: function (baru) {
                var nama = this.cfg.csrfName;
                document.querySelectorAll('input[name="' + nama + '"]').forEach(function (i) { i.value = baru; });
            },

            // ------------------------------------------------------------------ kirim (antre)
            kirim: function (url, data) {
                var self = this;
                this.tertunda++;
                var tugas = function () {
                    var token = document.getElementById('skbm-csrf');
                    var body = new URLSearchParams();
                    body.append(self.cfg.csrfName, token ? token.value : '');
                    body.append('tahun', self.cfg.tahun);
                    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
                    return fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    }).then(function (r) {
                        return r.json().catch(function () { return { ok: false, pesan: 'Balasan server tidak terbaca. Muat ulang halaman.' }; })
                            .then(function (j) {
                                j._status = r.status;
                                if (j.csrf) { self.segarkanToken(j.csrf); }
                                if (r.status === 403 && !j.pesan) { j.ok = false; j.pesan = 'Sesi habis atau tidak berhak. Muat ulang halaman.'; }
                                return j;
                            });
                    }).catch(function () {
                        return { ok: false, pesan: 'Koneksi terputus. Perubahan belum tersimpan — periksa jaringan lalu ulangi.' };
                    }).then(function (j) { self.tertunda--; return j; });
                };
                var hasil = this.antre.then(tugas, tugas);
                this.antre = hasil.catch(function () { /* antre tetap jalan */ });
                return hasil;
            },

            // ------------------------------------------------------------------ angka resmi dari server
            pakaiRingkas: function (j) {
                if (typeof j.total === 'number') { setTeks('tot-semua', ribuan(j.total)); setTeks('ringkas-total', ribuan(j.total)); }
                Object.keys(j.per_mapel || {}).forEach(function (id) { setTeks('mt-' + id, ribuan(j.per_mapel[id])); setTeks('cm-' + id, ribuan(j.per_mapel[id])); });
                Object.keys(j.per_baris || {}).forEach(function (id) { setTeks('gt-' + id, ribuan(j.per_baris[id])); setTeks('ct-' + id, ribuan(j.per_baris[id])); });
                Object.keys(j.per_kelas || {}).forEach(function (id) {
                    setTeks('kc-' + id, String(j.per_kelas[id].jml));
                    setTeks('kj-' + id, String(j.per_kelas[id].jp));
                });
                if (typeof j.jumlah_guru === 'number') { setTeks('ringkas-guru', String(j.jumlah_guru)); }
                if (typeof j.jumlah_mapel === 'number') { setTeks('ringkas-mapel', String(j.jumlah_mapel)); }
                if (typeof j.jumlah_sel === 'number') { setTeks('ringkas-sel', String(j.jumlah_sel)); }
                if (typeof j.tanpa_jp === 'number') { setTeks('ringkas-tanpajp', String(j.tanpa_jp)); }
            },

            gayaSel: function (td, aktif, jp) {
                var adaJp = aktif && jp !== null && jp !== undefined;
                var tanpaJp = aktif && !adaJp;
                td.dataset.aktif = aktif ? '1' : '0';
                td.dataset.jp = adaJp ? String(jp) : '';
                td.textContent = !aktif ? '' : (adaJp ? String(jp) : '✓');
                KELAS_JP.forEach(function (c) { td.classList.toggle(c, adaJp); });
                KELAS_TANPA_JP.forEach(function (c) { td.classList.toggle(c, tanpaJp); });
                KELAS_MATI.forEach(function (c) { td.classList.toggle(c, !aktif); });
                this.segarkanChip(td);
            },

            // ------------------------------------------------------------------ klik sel
            klikSel: function (e) {
                var td = e.target.closest('td[data-k]');
                if (!td) { return; }
                if (this.mode === 'jp') { this.tanyaJp(td); return; }
                this.kirimSel(td, td.closest('tr').dataset.mapel, td.dataset.aktif !== '1', null);
            },

            klikKanan: function (e) {
                var td = e.target.closest('td[data-k]');
                if (!td) { return; }
                e.preventDefault();
                this.tanyaJp(td);
            },

            tanyaJp: function (td) {
                var maks = Number(this.cfg.maksJp) || 40;
                var v = window.prompt('JP per minggu untuk sel ini (1–' + maks + ').\nKosongkan = JP belum diisi.', td.dataset.jp || '');
                if (v === null) { return; }
                v = v.trim();
                if (!/^[0-9]*$/.test(v) || (v !== '' && (Number(v) < 1 || Number(v) > maks))) {
                    this.tampil('Isi JP dengan angka bulat 1–' + maks + ' (atau kosongkan).', true);
                    return;
                }
                this.kirimSel(td, td.closest('tr').dataset.mapel, true, v);
            },

            kirimSel: function (td, mapel, aktif, jp) {
                var self = this;
                var data = { mapel: mapel, kelas: td.dataset.k, aktif: aktif ? '1' : '0' };
                if (jp !== null) { data.jp = jp; }
                td.classList.add('opacity-50');
                this.kirim(this.cfg.urlSel, data).then(function (j) {
                    td.classList.remove('opacity-50');
                    if (!j.ok) { self.tampil(j.pesan || 'Gagal menyimpan.', true); return; }
                    self.gayaSel(td, aktif, typeof j.jp === 'number' ? j.jp : null);
                    self.pakaiRingkas(j);
                    self.tampil('Tersimpan.');
                });
            },

            // ------------------------------------------------------------------ ubah nama mapel
            ubahNama: function (btn) {
                var self = this;
                var baru = window.prompt('Nama mata pelajaran (tulis - bila guru ini tidak mengampu mapel):', btn.dataset.nama);
                if (baru === null) { return; }
                baru = baru.trim();
                if (baru === '' || baru === btn.dataset.nama) { return; }
                this.kirim(this.cfg.urlMapelUbah.replace('__ID__', btn.dataset.id), { nama: baru }).then(function (j) {
                    if (!j.ok) { self.tampil(j.pesan || 'Gagal menyimpan.', true); return; }
                    // tombol nama mapel yang sama ada di tabel DAN di kartu per guru → perbarui semuanya
                    self.$root.querySelectorAll('button[data-id="' + btn.dataset.id + '"]').forEach(function (b) {
                        b.dataset.nama = j.nama;
                        b.textContent = j.nama;
                    });
                    self.tampil('Tersimpan.');
                });
            },

            // ------------------------------------------------------------------ tambah baris mapel untuk guru yang sudah ada
            tambahUntuk: function (guruId) {
                var self = this;
                this.panel = 'tambah';
                this.$nextTick(function () {
                    var s = self.$refs.pilihGuru;
                    if (s) { s.value = String(guruId); }
                    var p = document.getElementById('tambah');
                    if (p) { p.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    var n = document.getElementById('s-mapel');
                    if (n) { n.focus({ preventScroll: true }); }
                });
            },

            // ------------------------------------------------------------------ saring nama guru
            saring: function () {
                var q = this.cari.trim().toLowerCase();
                this.$root.querySelectorAll('tbody tr[data-nama]').forEach(function (tr) {
                    tr.classList.toggle('hidden', q !== '' && tr.dataset.nama.indexOf(q) < 0);
                });
                this.saringKartu(q);
            }
        };
        // tampilan "Per guru" (kartu + chip kelas) dipakai bersama SKBM & Koreksi: lihat kartu-guru.js
        return Object.assign(dasar, window.KartuGuru({ nama: 'skbm', kunciGuru: 'guru' }));
    });
});
