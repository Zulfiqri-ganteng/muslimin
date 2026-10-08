/**
 * PKL staf — dua kotak dialog (Alpine):
 *
 *   pklUnduh : sebelum surat diunduh WAJIB mencatat biaya (Biaya PKL, SPP, Tabungan Wajib, Iuran OSIS) + beasiswa /
 *              keringanan. Satu surat → tabel per siswa; banyak surat → satu set centang untuk semua siswa.
 *              Dibuka lewat window.dispatchEvent(new CustomEvent('pkl-unduh', {detail: {ajuan: 12}})) atau
 *              {detail: {mode: 'terpilih'|'belum'|'semua'}}. Aturan sebenarnya dijaga server (Libraries\PklBiaya);
 *              di sini hanya kenyamanan + pesan lebih awal.
 *   pklWa    : daftar siswa + tombol WhatsApp (tautan wa.me, dikirim manual oleh staf). Terbuka otomatis setelah
 *              unduhan selesai (event 'pkl:unduh-selesai' dari app.js) atau lewat event 'pkl-wa' {detail: {ids: [..]}}.
 *
 * Konfigurasi dari atribut data-config pada elemen akar (lihat views/admin/pkl/_unduh.php).
 */
(function () {
    'use strict';

    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function geser(b, n) {
        var p = b.split('-');
        var idx = (+p[0]) * 12 + (+p[1] - 1) + n;
        return Math.floor(idx / 12) + '-' + ('0' + (idx % 12 + 1)).slice(-2);
    }
    function labelBulan(b) {
        var p = b.split('-');
        return BULAN[+p[1] - 1] + ' ' + p[0];
    }
    function rupiah(n) {
        return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function tokenCsrf(nama) {
        var i = document.querySelector('input[name="' + nama + '"]');
        return i ? i.value : '';
    }
    function perbaruiCsrf(nama, nilai) {
        if (!nilai) { return; }
        document.querySelectorAll('input[name="' + nama + '"]').forEach(function (i) { i.value = nilai; });
    }
    function ambilJson(url, opsi) {
        return fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, opsi || {}))
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, message: 'Balasan server tidak terbaca.' }; })
                    .then(function (j) { j._status = r.status; return j; });
            });
    }

    document.addEventListener('alpine:init', function () {

        // ===================================================== dialog biaya sebelum unduh
        Alpine.data('pklUnduh', function () {
            return {
                cfg: {},
                buka: false,
                memuat: false,
                galat: '',
                data: null,
                mode: 'satu',          // satu | massal (menurut jumlah surat)
                modeKirim: 'terpilih', // terpilih | belum | semua (dikirim ke server pada unduh massal)
                tujuan: '',
                ids: [],
                per: {},
                semua: { jenis: [], bulan: '', jumlah_bulan: 1 },
                daftarTerbuka: false,
                tanggalSurat: '',

                init: function () {
                    var self = this;
                    this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                    window.addEventListener('pkl-unduh', function (e) { self.mulai(e.detail || {}); });
                    // Unduhan selesai: tutup dialog biaya (halaman dimuat ulang oleh dialog WhatsApp setelah ditutup).
                    window.addEventListener('pkl:unduh-selesai', function () { self.buka = false; });
                    this.$watch('buka', function (v) { document.body.classList.toggle('overflow-hidden', !!v); });
                },

                tutup: function () { this.buka = false; },

                reset: function () {
                    this.galat = ''; this.data = null; this.per = {}; this.ids = []; this.daftarTerbuka = false;
                    this.semua = { jenis: [], bulan: '', jumlah_bulan: 1 };
                    this.tanggalSurat = this.cfg.hariIni || '';
                },

                mulai: function (d) {
                    var self = this;
                    this.reset();
                    this.buka = true;
                    var q = new URLSearchParams();
                    if (d.ajuan) {
                        this.modeKirim = 'terpilih';
                        this.ids = [String(d.ajuan)];
                        this.tujuan = this.cfg.urlSuratAwal + d.ajuan + this.cfg.urlSuratAkhir;
                        q.set('mode', 'terpilih');
                        q.append('ids[]', d.ajuan);
                    } else {
                        this.modeKirim = d.mode || 'terpilih';
                        this.tujuan = this.cfg.urlMassal;
                        q.set('mode', this.modeKirim);
                        if (this.modeKirim === 'terpilih') {
                            var cek = document.querySelectorAll('input[name="ids[]"]:checked');
                            this.ids = Array.prototype.map.call(cek, function (c) { return c.value; });
                            if (this.ids.length === 0) {
                                this.galat = 'Centang dulu ajuan yang suratnya mau diunduh.';
                                this.memuat = false;
                                return;
                            }
                            this.ids.forEach(function (i) { q.append('ids[]', i); });
                        }
                    }
                    this.memuat = true;
                    ambilJson(this.cfg.urlSiap + '?' + q.toString()).then(function (j) {
                        self.memuat = false;
                        if (!j.ok) { self.galat = j.message || 'Gagal memuat data biaya.'; return; }
                        if (j.data.kosong) { self.galat = j.data.pesan || 'Tidak ada surat yang perlu diunduh.'; return; }
                        // Isian per siswa disiapkan SEBELUM data dipasang, supaya tampilan tak pernah membaca isian yang belum ada.
                        var per = {};
                        j.data.siswa.forEach(function (s) {
                            per[s.siswa_id] = { jenis: [], bulan: j.data.bulan_default, jumlah_bulan: 1, beasiswa: '', beasiswa_ket: '', ringan: false, keringanan: '' };
                        });
                        self.per = per;
                        self.semua.bulan = j.data.bulan_default;
                        self.mode = j.data.surat.length === 1 ? 'satu' : 'massal';
                        // Surat belum bernomor → boleh memilih tanggal surat (satu surat saja).
                        self.perluTanggal = j.data.surat.length === 1 && !j.data.surat[0].nomor;
                        self.data = j.data;
                    }).catch(function () {
                        self.memuat = false;
                        self.galat = 'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.';
                    });
                },

                perluTanggal: false,

                // ---------- bantu tampilan ----------
                rupiah: rupiah,
                labelBulan: labelBulan,

                opsiBulan: function () {
                    if (!this.data) { return []; }
                    var dasar = this.data.bulan_default, out = [];
                    for (var i = -12; i <= 2; i++) { var b = geser(dasar, i); out.push({ v: b, t: labelBulan(b) }); }
                    return out.reverse();
                },

                bulananAda: function () {
                    return !!this.data && this.data.jenis.some(function (j) { return j.siklus === 'bulanan'; });
                },

                // Periode yang dimaksud untuk jenis j pada siswa s (kegiatan: tahun ajaran; bulanan: rentang bulan).
                periodeUntuk: function (s, j) {
                    if (j.siklus !== 'bulanan') { return [s.periode_kegiatan]; }
                    var p = this.per[s.siswa_id] || {};
                    var bulan = this.mode === 'massal' ? this.semua.bulan : (p.bulan || this.data.bulan_default);
                    var n = Math.max(1, Math.min(12, parseInt(this.mode === 'massal' ? this.semua.jumlah_bulan : p.jumlah_bulan, 10) || 1));
                    var out = [];
                    for (var i = 0; i < n; i++) { out.push(geser(bulan, i)); }
                    return out;
                },

                tercatat: function (s, j) {
                    var ada = (s.tercatat && s.tercatat[j.kode]) || [];
                    return this.periodeUntuk(s, j).every(function (p) { return ada.indexOf(p) !== -1; });
                },

                bebasSpp: function (s) {
                    var p = this.per[s.siswa_id] || {};
                    return !!s.beasiswa || !!p.beasiswa;
                },

                // Jenis yang dipilih staf untuk siswa s (per siswa + set bersama), tanpa SPP bila beasiswa.
                dipilih: function (s) {
                    var self = this;
                    var p = this.per[s.siswa_id] || { jenis: [] };
                    var kode = p.jenis.concat(this.semua.jenis).filter(function (k, i, a) { return a.indexOf(k) === i; });
                    return this.data.jenis.filter(function (j) {
                        return kode.indexOf(j.kode) !== -1 && !(j.kode === 'spp' && self.bebasSpp(s));
                    });
                },

                // Persis aturan server: minimal satu catatan (biaya baru/sudah tercatat, beasiswa, atau keringanan beralasan).
                memenuhi: function (s) {
                    var p = this.per[s.siswa_id] || {};
                    if (s.milik_ajuan > 0 || s.kegiatan_ada || s.ada_keringanan || this.bebasSpp(s)) { return true; }
                    if (p.ringan && (p.keringanan || '').trim().length >= 5) { return true; }
                    var self = this;
                    var terisi = this.dipilih(s).length > 0;
                    // biaya yang sudah tercatat untuk periode yang tampil juga dihitung (dikirim sebagai kiriman tersembunyi)
                    var sudah = this.data.jenis.some(function (j) { return self.tercatat(s, j) && !(j.kode === 'spp' && self.bebasSpp(s)); });
                    return terisi || sudah;
                },

                kurang: function () {
                    var self = this;
                    return this.data ? this.data.siswa.filter(function (s) { return !self.memenuhi(s); }) : [];
                },

                totalBaru: function () {
                    var self = this, total = 0;
                    if (!this.data) { return 0; }
                    this.data.siswa.forEach(function (s) {
                        self.dipilih(s).forEach(function (j) {
                            var ada = (s.tercatat && s.tercatat[j.kode]) || [];
                            self.periodeUntuk(s, j).forEach(function (p) { if (ada.indexOf(p) === -1) { total += j.nominal; } });
                        });
                    });
                    return total;
                },

                catatanTercatat: function (s) {
                    var self = this, out = [];
                    this.data.jenis.forEach(function (j) {
                        var ada = (s.tercatat && s.tercatat[j.kode]) || [];
                        if (ada.length) {
                            out.push(j.nama + ' ' + ada.map(function (p) { return j.siklus === 'bulanan' ? labelBulan(p) : p; }).join(', '));
                        }
                    });
                    return out;
                },

                // Dipanggil saat form dikirim: tahan di sini bila belum memenuhi aturan (server tetap penentu).
                cek: function (ev) {
                    var k = this.kurang();
                    if (k.length > 0) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        this.galat = (this.mode === 'massal' ? k.length + ' siswa' : k.map(function (s) { return s.nama; }).join(', '))
                            + ' belum punya catatan. Centang minimal satu biaya (atau Beasiswa / Keringanan beralasan).';
                        return false;
                    }
                    this.galat = '';
                    return true;
                },
            };
        });

        // ===================================================== dialog kabar WhatsApp
        Alpine.data('pklWa', function () {
            return {
                cfg: {},
                buka: false,
                memuat: false,
                galat: '',
                daftar: [],
                tersalin: 0,
                berubah: false, // ada unduhan baru / ada yang ditandai → muat ulang halaman saat ditutup (nomor & status segar)

                init: function () {
                    var self = this;
                    this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                    window.addEventListener('pkl-wa', function (e) { self.dariIds((e.detail && e.detail.ids) || []); });
                    window.addEventListener('pkl:unduh-selesai', function (e) { self.dariUnduhan(e.detail && e.detail.token); });
                },

                tutup: function () {
                    this.buka = false;
                    if (this.berubah) { window.location.reload(); }
                },

                dariUnduhan: function (token) {
                    var self = this;
                    if (!token) { return; }
                    this.berubah = true;
                    ambilJson(this.cfg.urlHasil + '/' + encodeURIComponent(token)).then(function (j) {
                        if (j.ok && j.data && j.data.length) { self.daftar = j.data; self.galat = ''; self.buka = true; }
                        else { window.location.reload(); }
                    }).catch(function () { window.location.reload(); });
                },

                dariIds: function (ids) {
                    var self = this;
                    if (!ids.length) { return; }
                    this.daftar = []; this.galat = ''; this.buka = true; this.memuat = true;
                    var q = new URLSearchParams();
                    ids.forEach(function (i) { q.append('ids[]', i); });
                    ambilJson(this.cfg.urlWa + '?' + q.toString()).then(function (j) {
                        self.memuat = false;
                        if (!j.ok) { self.galat = j.message || 'Gagal memuat daftar.'; return; }
                        self.daftar = j.data || [];
                        if (!self.daftar.length) { self.galat = 'Belum ada surat bernomor pada ajuan ini. Unduh suratnya dulu.'; }
                    }).catch(function () { self.memuat = false; self.galat = 'Tidak bisa menghubungi server.'; });
                },

                belumDikabari: function () {
                    return this.daftar.filter(function (r) { return !r.dikabari_at && r.url; }).length;
                },

                waktu: function (s) {
                    if (!s) { return ''; }
                    var d = new Date(String(s).replace(' ', 'T'));
                    return isNaN(d) ? s : ('0' + d.getDate()).slice(-2) + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
                },

                // Tautan WhatsApp dibuka oleh <a target="_blank">; di sini hanya mencatat "sudah dikabari".
                tandai: function (r) {
                    var self = this;
                    var nama = this.cfg.csrfName, token = tokenCsrf(nama);
                    var body = new URLSearchParams();
                    body.set(nama, token);
                    ambilJson(this.cfg.urlTandaiAwal + r.ajuan_id + '/wa/' + r.siswa_id + '/tandai', {
                        method: 'POST', body: body,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                    }).then(function (j) {
                        perbaruiCsrf(nama, j.csrf);
                        if (j.ok) { r.dikabari_at = j.waktu; r.dikabari_oleh = j.oleh; self.berubah = true; }
                    });
                },

                salin: function (r) {
                    var self = this;
                    if (!navigator.clipboard) { return; }
                    navigator.clipboard.writeText(r.pesan).then(function () {
                        self.tersalin = r.siswa_id;
                        setTimeout(function () { if (self.tersalin === r.siswa_id) { self.tersalin = 0; } }, 1800);
                    });
                },
            };
        });
    });
})();
