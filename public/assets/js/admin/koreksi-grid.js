/**
 * Ceklis Koreksi honor (Ujian) — matriks guru × mapel × kelas dengan simpan otomatis (Alpine).
 *
 * Klik sel = nyalakan/matikan kelas pada baris mapel itu; klik kanan = angka lembar khusus untuk sel itu; ubah angka
 * "peserta" sebuah kelas = semua sel kelas itu ikut. Setiap perubahan dikirim ke server (POST JSON); server menyimpan lalu
 * MENGEMBALIKAN angka resmi (total tiap baris mapel, tiap guru, tiap kelas, dan keseluruhan). Layar hanya menampilkan
 * angka dari server — tidak ada hitungan sendiri di sini, jadi tak mungkin berbeda dari Excel. Pengiriman diantre satu per
 * satu karena token CSRF berganti setiap POST (token baru dibawa balasan).
 *
 * Konfigurasi lewat data-config pada elemen akar (lihat views/admin/ujian/koreksi.php).
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
    var KELAS_AKTIF = ['bg-sky-400', 'text-white', 'font-semibold'];
    var KELAS_MATI = ['bg-slate-200'];
    var KELAS_KHUSUS = ['ring-2', 'ring-inset', 'ring-amber-400'];

    Alpine.data('koreksiGrid', function () {
        return {
            cfg: {},
            panel: '',
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
                if (['isi', 'impor', 'salin', 'tambah', 'terapkan', 'kosong'].indexOf(h) >= 0) { this.panel = h; }
            },

            tampil: function (teks, galat) {
                var self = this;
                this.pesan = teks;
                this.galat = !!galat;
                clearTimeout(this.timer);
                this.timer = setTimeout(function () { self.pesan = ''; }, galat ? 6000 : 1500);
            },

            // ------------------------------------------------------------------ kirim (antre, token CSRF bergilir)
            kirim: function (url, data) {
                var self = this;
                this.tertunda++;
                var tugas = function () {
                    var token = document.getElementById('koreksi-csrf');
                    var body = new URLSearchParams();
                    body.append(self.cfg.csrfName, token ? token.value : '');
                    body.append('periode_id', self.cfg.periodeId);
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
                                if (j.csrf && token) { token.value = j.csrf; }
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
                if (typeof j.total === 'number') { setTeks('tot-semua', ribuan(j.total)); }
                Object.keys(j.per_mapel || {}).forEach(function (id) { setTeks('mt-' + id, ribuan(j.per_mapel[id])); });
                Object.keys(j.per_baris || {}).forEach(function (id) { setTeks('gt-' + id, ribuan(j.per_baris[id])); });
                Object.keys(j.per_kelas || {}).forEach(function (id) { setTeks('kc-' + id, String(j.per_kelas[id].jml)); });
                if (typeof j.jumlah_guru === 'number') { setTeks('ringkas-guru', String(j.jumlah_guru)); }
                if (typeof j.jumlah_mapel === 'number') { setTeks('ringkas-mapel', String(j.jumlah_mapel)); }
                if (typeof j.total === 'number') { setTeks('ringkas-total', ribuan(j.total)); }
            },

            gayaSel: function (td, aktif, nilai, khusus) {
                td.dataset.aktif = aktif ? '1' : '0';
                td.dataset.khusus = aktif && khusus ? '1' : '';
                td.textContent = aktif ? String(nilai) : '';
                KELAS_AKTIF.forEach(function (c) { td.classList.toggle(c, aktif); });
                KELAS_MATI.forEach(function (c) { td.classList.toggle(c, !aktif); });
                KELAS_KHUSUS.forEach(function (c) { td.classList.toggle(c, aktif && khusus); });
            },

            // ------------------------------------------------------------------ klik sel
            klikSel: function (e) {
                var td = e.target.closest('td[data-k]');
                if (!td || this.cfg.terkunci) { return; }
                this.kirimSel(td, td.closest('tr').dataset.mapel, td.dataset.aktif !== '1', null);
            },

            klikKanan: function (e) {
                var td = e.target.closest('td[data-k]');
                if (!td || this.cfg.terkunci) { return; }
                e.preventDefault();
                var awal = td.dataset.khusus === '1' ? td.textContent.trim() : '';
                var v = window.prompt('Jumlah lembar KHUSUS untuk sel ini.\nKosongkan = ikut jumlah peserta kelas.', awal);
                if (v === null) { return; }
                v = v.trim();
                if (!/^[0-9]*$/.test(v)) { this.tampil('Isi dengan angka bulat saja (tanpa huruf, koma, atau minus).', true); return; }
                this.kirimSel(td, td.closest('tr').dataset.mapel, true, v);
            },

            kirimSel: function (td, mapel, aktif, jumlah) {
                var self = this;
                var data = { mapel: mapel, kelas: td.dataset.k, aktif: aktif ? '1' : '0' };
                if (jumlah !== null) { data.jumlah = jumlah; }
                td.classList.add('opacity-50');
                this.kirim(this.cfg.urlSel, data).then(function (j) {
                    td.classList.remove('opacity-50');
                    if (!j.ok) { self.tampil(j.pesan || 'Gagal menyimpan.', true); return; }
                    var peserta = j.per_kelas && j.per_kelas[td.dataset.k] ? j.per_kelas[td.dataset.k].peserta : null;
                    self.gayaSel(td, aktif, j.nilai, aktif && peserta !== null && Number(j.nilai) !== Number(peserta));
                    self.pakaiRingkas(j);
                    self.tampil('Tersimpan.');
                });
            },

            // ------------------------------------------------------------------ peserta per kelas
            simpanPeserta: function (el) {
                var self = this;
                var awal = el.dataset.awal || '';
                var v = el.value.trim();
                if (v === awal) { return; }
                if (!/^[0-9]*$/.test(v)) {
                    el.value = awal;
                    this.tampil('Isi dengan angka bulat saja (tanpa huruf, koma, atau minus).', true);
                    return;
                }
                el.classList.add('opacity-50');
                this.kirim(this.cfg.urlPeserta, { kelas: el.dataset.kelas, nilai: v }).then(function (j) {
                    el.classList.remove('opacity-50');
                    if (!j.ok) { el.value = awal; self.tampil(j.pesan || 'Gagal menyimpan.', true); return; }
                    var info = (j.per_kelas || {})[el.dataset.kelas];
                    if (info) {
                        el.value = String(info.peserta);
                        el.dataset.awal = String(info.peserta);
                        el.classList.toggle('border-amber-400', !!info.manual);
                        el.classList.toggle('bg-amber-50', !!info.manual);
                        // semua sel kelas ini yang memakai angka peserta ikut berubah
                        self.$root.querySelectorAll('td[data-k="' + el.dataset.kelas + '"][data-aktif="1"]').forEach(function (td) {
                            if (td.dataset.khusus !== '1') { td.textContent = String(info.peserta); }
                        });
                    }
                    self.pakaiRingkas(j);
                    self.tampil('Tersimpan.');
                });
            },

            // ------------------------------------------------------------------ ubah nama mapel
            ubahNama: function (btn) {
                var self = this;
                if (this.cfg.terkunci) { return; }
                var baru = window.prompt('Nama mata pelajaran (tulis - bila guru ini tidak mengoreksi mapel):', btn.dataset.nama);
                if (baru === null) { return; }
                baru = baru.trim();
                if (baru === '' || baru === btn.dataset.nama) { return; }
                this.kirim(this.cfg.urlMapelUbah.replace('__ID__', btn.dataset.id), { nama: baru }).then(function (j) {
                    if (!j.ok) { self.tampil(j.pesan || 'Gagal menyimpan.', true); return; }
                    btn.dataset.nama = j.nama;
                    btn.textContent = j.nama;
                    self.tampil('Tersimpan.');
                });
            },

            // ------------------------------------------------------------------ saring nama guru
            saring: function () {
                var q = this.cari.trim().toLowerCase();
                this.$root.querySelectorAll('tbody tr[data-nama]').forEach(function (tr) {
                    tr.classList.toggle('hidden', q !== '' && tr.dataset.nama.indexOf(q) < 0);
                });
            }
        };
    });
});
