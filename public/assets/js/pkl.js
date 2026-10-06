/**
 * Form pengajuan PKL siswa (publik) — komponen Alpine `pklForm`.
 *
 * Validasi di sini hanya untuk kenyamanan (pesan langsung di tiap langkah);
 * penentu tetap server (App\Libraries\PklForm). Kunci galat memakai nama
 * kolom yang SAMA dengan server, jadi galat dari server langsung menempel di
 * kolom yang benar. Isian dikirim lewat fetch sehingga bila server menolak,
 * isian di layar TIDAK hilang; draf juga disimpan di HP siswa (localStorage).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var DRAF_PREFIX = 'pkl_draft_v1_';
    var DRAF_UMUR = 7 * 24 * 3600 * 1000; // draf lebih tua dari 7 hari dibuang

    /** Langkah tempat tiap kunci galat berada (untuk melompat ke galat pertama). */
    var LANGKAH_KUNCI = {
        1: ['perusahaan_nama', 'perusahaan_alamat', 'perusahaan_kota', 'perusahaan_telepon', 'kontak_nama', 'kontak_jabatan'],
        2: ['teman'],
        3: ['tanggal_mulai', 'tanggal_selesai', 'hp', 'tanggal_lahir'],
        4: ['pernyataan'],
    };

    // ---------------- util ----------------
    function isiKosong(cfg) {
        var thn = cfg && cfg.tahunTunggal ? String(cfg.tahunTunggal) : '';
        return {
            perusahaan_nama: '', perusahaan_alamat: '', perusahaan_kota: '', perusahaan_telepon: '',
            kontak_nama: '', kontak_jabatan: '',
            mulai_d: '', mulai_m: '', mulai_y: thn, selesai_d: '', selesai_m: '', selesai_y: thn,
            hp: '', lahir_d: '', lahir_m: '', lahir_y: '',
            pernyataan: false,
        };
    }
    function teks(v) { return String(v === null || v === undefined ? '' : v).trim(); }
    function kosong(v) { return teks(v) === ''; }
    function dua(n) { return String(n).padStart(2, '0'); }
    function namaSah(v) { return /^\p{L}[\p{L} .,'`\-]*$/u.test(teks(v).replace(/\s+/g, ' ')); }
    function telp(v) {
        var s = teks(v).replace(/\D/g, '');
        if (s.indexOf('62') === 0 && s.length >= 10) { s = '0' + s.slice(2); }
        return s;
    }
    // Sama dengan IsianBantu::teleponMurni / teleponSah / hpSah di server. Huruf DITOLAK, bukan dibuang diam-diam
    // (huruf "O" yang tertukar dengan angka 0 adalah salah ketik paling umum).
    var PESAN_HURUF = 'Nomor hanya boleh berisi angka. Periksa apakah ada huruf yang terselip — misalnya huruf "O" yang tertukar dengan angka 0.';
    function murni(v) { return /^\+?[\d\s().\-]+$/.test(teks(v)); }
    function telpSah(v) { var s = telp(v); return /^0\d{7,14}$/.test(s) && !/^0(\d)\1+$/.test(s); }
    function hpSah(v) { var s = telp(v); return /^08\d{8,12}$/.test(s) && !/^0(\d)\1+$/.test(s); }
    function tanggalSah(y, m, d) {
        var t = new Date(Number(y), Number(m) - 1, Number(d));
        return t.getFullYear() === Number(y) && t.getMonth() === Number(m) - 1 && t.getDate() === Number(d);
    }
    /** Selisih hari inklusif dua tanggal Y-m-d (6 Jan s/d 6 Jan = 1). */
    function hariInklusif(a, b) {
        var p = function (s) { var x = s.split('-'); return Date.UTC(Number(x[0]), Number(x[1]) - 1, Number(x[2])); };
        return Math.round((p(b) - p(a)) / 86400000) + 1;
    }
    function simpanLokal(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* abaikan */ } }
    function bacaLokal(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
    function hapusLokal(k) { try { localStorage.removeItem(k); } catch (e) { /* abaikan */ } }

    Alpine.data('pklForm', function () {
        return {
            cfg: {},
            step: 0,
            judulLangkah: ['Cari Nama', 'Perusahaan', 'Teman Satu Tempat', 'Waktu & Kontak', 'Periksa & Kirim'],

            // Langkah 1 (indeks 0)
            kelasId: '',
            siswaList: [],
            memuat: false,
            pesanDaftar: '',
            cari: '',
            pilih: null,
            buka: { d: '', m: '', y: '', pesan: '', proses: false },

            // Isian
            f: isiKosong({}),
            teman: [],
            temanAsal: [],        // id teman di ajuan yang sedang diperbaiki (tetap boleh dipilih lagi)
            ajuanId: 0,
            err: {},
            modeRevisi: false,
            catatan: '',
            mengirim: false,
            pesanKirim: '',
            draftInfo: '',
            _tunda: null,

            // Saran perusahaan (dari perusahaan yang pernah disetujui)
            saran: { buka: false, hasil: [], memuat: false, terpakai: false, _t: null, _n: 0 },

            // Pemilih teman
            pick: { buka: false, kelasId: '', list: [], memuat: false, pesan: '', cari: '' },

            init: function () {
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                this.f = isiKosong(this.cfg);
                var self = this;
                // Draf disimpan setiap ada ketikan (ditunda 400 ms agar hemat).
                this.$el.addEventListener('input', function () { self.jadwalkanDraf(); });
                this.$el.addEventListener('change', function () { self.jadwalkanDraf(); });
            },

            // ================= Daftar nama (dipakai langkah 1 & pemilih teman) =================
            /** Ambil daftar nama satu kelas; $sasaran = objek yang punya properti `memuat` (indikator "memuat…"). */
            ambilDaftar: function (kelasId, sasaran) {
                sasaran.memuat = true;
                return fetch(this.cfg.urlSiswa + '?kelas_id=' + encodeURIComponent(kelasId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .catch(function () { return { ok: false, message: 'Gagal terhubung. Periksa internet lalu pilih kelas lagi.' }; })
                    .finally(function () { sasaran.memuat = false; });
            },

            // ================= Langkah 1: cari nama =================
            muatSiswa: function () {
                var self = this;
                this.pilih = null;
                this.siswaList = [];
                this.cari = '';
                this.pesanDaftar = '';
                if (!this.kelasId) { return; }
                var id = this.kelasId;
                this.ambilDaftar(id, this).then(function (j) {
                    if (id !== self.kelasId) { return; } // pengguna sudah ganti kelas
                    if (j.ok) {
                        self.siswaList = j.data || [];
                        if (!self.siswaList.length) { self.pesanDaftar = 'Belum ada nama siswa di kelas ini. Hubungi wali kelas.'; }
                    } else {
                        self.pesanDaftar = j.message || 'Daftar nama gagal dimuat.';
                    }
                });
            },

            siswaTersaring: function () {
                var q = this.cari.trim().toLowerCase();
                if (!q) { return this.siswaList; }
                return this.siswaList.filter(function (s) { return s.nama.toLowerCase().indexOf(q) !== -1; });
            },

            lencana: function (status) {
                return {
                    menunggu: { teks: 'Menunggu ACC', kelas: 'bg-blue-100 text-blue-700' },
                    disetujui: { teks: 'Disetujui', kelas: 'bg-green-100 text-green-700' },
                    perbaikan: { teks: 'Perlu perbaikan', kelas: 'bg-amber-100 text-amber-800' },
                    ditolak: { teks: 'Ditolak — boleh ajukan lagi', kelas: 'bg-red-100 text-red-700' },
                }[status] || { teks: '', kelas: '' };
            },

            namaKelas: function (id) {
                var k = this.cfg.kelas[id === undefined ? this.kelasId : id];
                return k ? k.nama : '';
            },

            pilihSiswa: function (s) {
                var self = this;
                this.pilih = s;
                this.buka = { d: '', m: '', y: '', pesan: '', proses: false };
                this.$nextTick(function () {
                    if (self.$refs.panel) { self.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
            },

            /** Siswa belum punya ajuan aktif → siapkan isian baru (lalu draf bila ada). */
            mulai: function () {
                this.f = isiKosong(this.cfg);
                this.teman = [];
                this.temanAsal = [];
                this.ajuanId = 0;
                this.modeRevisi = false;
                this.catatan = '';
                this.err = {};
                this.pesanKirim = '';
                this.saran = { buka: false, hasil: [], memuat: false, terpakai: false, _t: null, _n: 0 };
                this.pick = { buka: false, kelasId: '', list: [], memuat: false, pesan: '', cari: '' };

                var draf = bacaLokal(DRAF_PREFIX + this.pilih.id);
                if (draf && draf.f && (Date.now() - draf.t) < DRAF_UMUR) {
                    this.f = Object.assign(isiKosong(this.cfg), draf.f);
                    this.teman = Array.isArray(draf.teman) ? draf.teman : [];
                    this.draftInfo = 'Isianmu yang belum terkirim sudah dipulihkan.';
                } else {
                    this.draftInfo = '';
                }
                this.keLangkah(1);
            },

            /** Siswa = pengaju ajuan berstatus perbaikan → buka isian lama dengan tanggal lahir. */
            bukaAjuan: function () {
                var self = this;
                var b = this.buka;
                if (b.proses) { return; }
                if (!(b.d && b.m && b.y)) { b.pesan = 'Lengkapi tanggal, bulan, dan tahun lahirmu.'; return; }
                if (!tanggalSah(b.y, b.m, b.d)) { b.pesan = 'Tanggal lahir tidak valid. Periksa lagi.'; return; }
                b.pesan = '';
                b.proses = true;
                var fd = new FormData();
                fd.append('siswa_id', this.pilih.id);
                fd.append('tanggal_lahir', b.y + '-' + dua(b.m) + '-' + dua(b.d));
                fetch(this.cfg.urlBuka, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j.ok) { b.pesan = j.message || 'Ajuan gagal dibuka.'; return; }
                        self.isiDari(j.data || {});
                        self.catatan = j.catatan || '';
                        self.modeRevisi = true;
                        self.err = {};
                        self.pesanKirim = '';
                        self.draftInfo = '';
                        self.keLangkah(1);
                    })
                    .catch(function () { b.pesan = 'Gagal terhubung. Periksa internet lalu coba lagi.'; })
                    .finally(function () { b.proses = false; });
            },

            /** Isi form dari ajuan lama (format kolom server). */
            isiDari: function (d) {
                var f = isiKosong(this.cfg);
                var ambil = function (k) { return d[k] === null || d[k] === undefined ? '' : String(d[k]); };
                ['perusahaan_nama', 'perusahaan_alamat', 'perusahaan_kota', 'perusahaan_telepon', 'kontak_nama', 'kontak_jabatan', 'hp'].forEach(function (k) { f[k] = ambil(k); });
                var pecah = function (tgl, awalan) {
                    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(tgl || '');
                    if (m) { f[awalan + '_y'] = String(Number(m[1])); f[awalan + '_m'] = String(Number(m[2])); f[awalan + '_d'] = String(Number(m[3])); }
                };
                pecah(d.tanggal_mulai, 'mulai');
                pecah(d.tanggal_selesai, 'selesai');
                pecah(d.tanggal_lahir, 'lahir');
                f.pernyataan = false;
                this.f = f;
                this.teman = (d.teman || []).map(function (t) { return { id: Number(t.id), nama: t.nama, kelas: t.kelas || '' }; });
                this.temanAsal = this.teman.map(function (t) { return t.id; });
                this.ajuanId = Number(d.ajuan_id) || 0;
            },

            // ================= Langkah 2: saran perusahaan =================
            cariSaran: function () {
                var self = this;
                var s = this.saran;
                s.terpakai = false;
                clearTimeout(s._t);
                var q = teks(this.f.perusahaan_nama);
                if (q.length < 2) { s.hasil = []; s.buka = false; return; }
                s._t = setTimeout(function () {
                    var n = ++s._n;
                    s.memuat = true;
                    fetch(self.cfg.urlPerusahaan + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (n !== s._n) { return; } // jawaban basi
                            s.hasil = j.ok ? (j.data || []) : [];
                            s.buka = s.hasil.length > 0;
                        })
                        .catch(function () { if (n === s._n) { s.hasil = []; s.buka = false; } })
                        .finally(function () { if (n === s._n) { s.memuat = false; } });
                }, 250);
            },

            tutupSaran: function () {
                var s = this.saran;
                setTimeout(function () { s.buka = false; }, 150); // beri waktu klik pada saran
            },

            pakaiSaran: function (p) {
                var f = this.f;
                var isi = function (v) { return v === null || v === undefined ? '' : String(v); };
                f.perusahaan_nama = isi(p.nama);
                f.perusahaan_alamat = isi(p.alamat);
                f.perusahaan_kota = isi(p.kota);
                f.perusahaan_telepon = isi(p.telepon);
                f.kontak_nama = isi(p.kontak_nama);
                f.kontak_jabatan = isi(p.kontak_jabatan);
                this.saran.hasil = [];
                this.saran.buka = false;
                this.saran.terpakai = true;
                this.err = Object.assign({}, this.err, { perusahaan_nama: '', perusahaan_alamat: '', perusahaan_kota: '', perusahaan_telepon: '', kontak_nama: '' });
            },

            // ================= Langkah 3: teman =================
            maksTeman: function () { return Math.max(0, (this.cfg.maksAnggota || 1) - 1); },
            bisaTambahTeman: function () { return this.teman.length < this.maksTeman(); },

            bukaPemilih: function () {
                this.pick.buka = !this.pick.buka;
                this.pick.pesan = '';
            },

            muatPemilih: function () {
                var self = this;
                var p = this.pick;
                p.list = [];
                p.cari = '';
                p.pesan = '';
                if (!p.kelasId) { return; }
                var id = p.kelasId;
                this.ambilDaftar(id, p).then(function (j) {
                    if (id !== p.kelasId) { return; }
                    if (j.ok) {
                        p.list = j.data || [];
                        if (!p.list.length) { p.pesan = 'Belum ada nama siswa di kelas ini.'; }
                    } else {
                        p.pesan = j.message || 'Daftar nama gagal dimuat.';
                    }
                });
            },

            pemilihTersaring: function () {
                var q = this.pick.cari.trim().toLowerCase();
                if (!q) { return this.pick.list; }
                return this.pick.list.filter(function (s) { return s.nama.toLowerCase().indexOf(q) !== -1; });
            },

            /** Alasan seorang siswa TIDAK bisa dipilih sebagai teman ('' = bisa). */
            alasanTak: function (s) {
                if (this.pilih && s.id === this.pilih.id) { return 'Ini kamu'; }
                if (this.teman.some(function (t) { return t.id === s.id; })) { return 'Sudah dipilih'; }
                if (s.status !== 'belum' && s.status !== 'ditolak' && this.temanAsal.indexOf(s.id) === -1) {
                    return this.lencana(s.status).teks;
                }
                return '';
            },

            tambahTeman: function (s) {
                if (this.alasanTak(s) !== '' || !this.bisaTambahTeman()) { return; }
                this.teman.push({ id: s.id, nama: s.nama, kelas: this.namaKelas(this.pick.kelasId) });
                this.err = Object.assign({}, this.err, { teman: '' });
                this.pick.buka = false;
                this.jadwalkanDraf();
            },

            hapusTeman: function (id) {
                this.teman = this.teman.filter(function (t) { return t.id !== id; });
                this.jadwalkanDraf();
            },

            // ================= Langkah 4: tanggal =================
            tglYmd: function (p) {
                var f = this.f;
                return (f[p + '_y'] && f[p + '_m'] && f[p + '_d']) ? f[p + '_y'] + '-' + dua(f[p + '_m']) + '-' + dua(f[p + '_d']) : '';
            },

            tglIndo: function (ymd) {
                var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
                return m ? Number(m[3]) + ' ' + this.cfg.bulan[Number(m[2]) - 1] + ' ' + m[1] : '';
            },

            /** Teks lama PKL di bawah kolom tanggal ('' bila tanggal belum lengkap/sah). */
            infoDurasi: function () {
                var a = this.tglYmd('mulai');
                var b = this.tglYmd('selesai');
                if (!a || !b || !tanggalSah(this.f.mulai_y, this.f.mulai_m, this.f.mulai_d) || !tanggalSah(this.f.selesai_y, this.f.selesai_m, this.f.selesai_d) || b <= a) { return ''; }
                var h = hariInklusif(a, b);
                var bln = Math.round(h / 30);
                return 'Lama PKL: ' + h + ' hari' + (bln >= 1 ? ' (± ' + bln + ' bulan)' : '');
            },

            durasiWajar: function () {
                var a = this.tglYmd('mulai');
                var b = this.tglYmd('selesai');
                if (!a || !b || b <= a) { return true; }
                var h = hariInklusif(a, b);
                return h >= this.cfg.batas.min && h <= this.cfg.batas.maks;
            },

            // ================= Navigasi =================
            keLangkah: function (n) {
                this.step = n;
                this.$nextTick(function () {
                    var top = document.getElementById('pklTop');
                    if (top) { window.scrollTo({ top: top.getBoundingClientRect().top + window.scrollY - 12, behavior: 'smooth' }); }
                });
            },

            lanjut: function () {
                var e = this.validasi(this.step);
                this.err = e;
                if (Object.keys(e).length) { this.gulirKeGalat(); return; }
                this.keLangkah(this.step + 1);
            },

            kembali: function () {
                this.err = {};
                this.pesanKirim = '';
                this.keLangkah(Math.max(0, this.step - 1));
            },

            gulirKeGalat: function () {
                this.$nextTick(function () {
                    var el = document.querySelector('.inp-err, .err-msg:not([style*="display: none"])');
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
            },

            // ================= Validasi (cermin server) =================
            validasi: function (langkah) {
                var f = this.f;
                var c = this.cfg;
                var e = {};
                var wajib = function (k, pesan) { if (kosong(f[k])) { e[k] = pesan; } };

                if (langkah === 1) {
                    var nama = teks(f.perusahaan_nama);
                    if (!nama) { e.perusahaan_nama = 'Nama perusahaan wajib diisi.'; } else if (nama.length < 3 || !/\p{L}{2}/u.test(nama)) { e.perusahaan_nama = 'Nama perusahaan belum benar. Tulis nama lengkapnya, jangan disingkat.'; }
                    var alamat = teks(f.perusahaan_alamat);
                    if (!alamat) { e.perusahaan_alamat = 'Alamat perusahaan wajib diisi.'; } else if (alamat.length < 8) { e.perusahaan_alamat = 'Alamat perusahaan terlalu singkat. Tulis nama jalan, nomor, dan daerahnya.'; }
                    wajib('perusahaan_kota', 'Kota/kabupaten perusahaan wajib diisi.');
                    if (!kosong(f.perusahaan_telepon)) {
                        if (!murni(f.perusahaan_telepon)) { e.perusahaan_telepon = PESAN_HURUF; }
                        else if (!telpSah(f.perusahaan_telepon)) { e.perusahaan_telepon = 'Nomor telepon perusahaan tidak valid (contoh: 02188776655). Kosongkan bila tidak tahu.'; }
                    }
                    if (!kosong(f.kontak_nama) && !namaSah(f.kontak_nama)) { e.kontak_nama = 'Nama pimpinan/kontak hanya boleh berisi huruf. Kosongkan bila tidak tahu.'; }
                }

                if (langkah === 2 && this.teman.length > this.maksTeman()) {
                    e.teman = 'Maksimal ' + c.maksAnggota + ' siswa per perusahaan (termasuk kamu). Kurangi ' + (this.teman.length - this.maksTeman()) + ' teman.';
                }

                if (langkah === 3) {
                    var tanggal = function (p, kunci, label) {
                        if (!f[p + '_d'] || !f[p + '_m'] || !f[p + '_y']) { e[kunci] = 'Lengkapi tanggal, bulan, dan tahun ' + label + '.'; return ''; }
                        if (!tanggalSah(f[p + '_y'], f[p + '_m'], f[p + '_d'])) { e[kunci] = 'Tanggal ' + label + ' tidak valid. Periksa tanggal, bulan, dan tahunnya.'; return ''; }
                        return f[p + '_y'] + '-' + dua(f[p + '_m']) + '-' + dua(f[p + '_d']);
                    };
                    var mulai = tanggal('mulai', 'tanggal_mulai', 'mulai PKL');
                    var selesai = tanggal('selesai', 'tanggal_selesai', 'selesai PKL');
                    var b = c.batas;
                    if (mulai && selesai && selesai <= mulai) { e.tanggal_selesai = 'Tanggal selesai harus setelah tanggal mulai.'; }
                    if (mulai && b.awal && mulai < b.awal && !e.tanggal_mulai) { e.tanggal_mulai = 'Tanggal mulai paling awal ' + this.tglIndo(b.awal) + '.'; }
                    if (mulai && b.akhir && mulai > b.akhir && !e.tanggal_mulai) { e.tanggal_mulai = 'Tanggal mulai tidak boleh setelah ' + this.tglIndo(b.akhir) + '.'; }
                    if (selesai && b.akhir && selesai > b.akhir && !e.tanggal_selesai) { e.tanggal_selesai = 'Tanggal selesai paling lambat ' + this.tglIndo(b.akhir) + '.'; }
                    if (selesai && b.awal && selesai < b.awal && !e.tanggal_selesai) { e.tanggal_selesai = 'Tanggal selesai tidak boleh sebelum ' + this.tglIndo(b.awal) + '.'; }
                    if (mulai && selesai && !e.tanggal_selesai) {
                        var h = hariInklusif(mulai, selesai);
                        if (b.min > 0 && h < b.min) { e.tanggal_selesai = 'PKL minimal ' + b.min + ' hari, sedangkan yang kamu isi hanya ' + h + ' hari. Periksa tanggal selesainya.'; }
                        else if (b.maks > 0 && h > b.maks) { e.tanggal_selesai = 'PKL maksimal ' + b.maks + ' hari, sedangkan yang kamu isi ' + h + ' hari. Periksa tahun/bulan tanggal selesainya.'; }
                    }

                    if (kosong(f.hp)) { e.hp = 'Nomor HP/WhatsApp wajib diisi.'; }
                    else if (!murni(f.hp)) { e.hp = PESAN_HURUF; }
                    else if (!hpSah(f.hp)) { e.hp = 'Nomor HP tidak valid. Harus diawali 08 dan 10–14 angka (contoh: 081234567890).'; }

                    if (!f.lahir_d || !f.lahir_m || !f.lahir_y) { e.tanggal_lahir = 'Lengkapi tanggal, bulan, dan tahun lahirmu (dipakai untuk membuka ajuanmu bila perlu diperbaiki).'; }
                    else if (!tanggalSah(f.lahir_y, f.lahir_m, f.lahir_d)) { e.tanggal_lahir = 'Tanggal lahir tidak valid. Periksa tanggal, bulan, dan tahunnya.'; }
                }

                if (langkah === 4 && !f.pernyataan) { e.pernyataan = 'Centang pernyataan bahwa data sudah benar.'; }
                return e;
            },

            // ================= Ringkasan =================
            /** Isian final dalam format kolom server. */
            kiriman: function () {
                var f = this.f;
                return {
                    perusahaan_nama: teks(f.perusahaan_nama), perusahaan_alamat: teks(f.perusahaan_alamat),
                    perusahaan_kota: teks(f.perusahaan_kota), perusahaan_telepon: teks(f.perusahaan_telepon),
                    kontak_nama: teks(f.kontak_nama), kontak_jabatan: teks(f.kontak_jabatan),
                    tanggal_mulai: this.tglYmd('mulai'), tanggal_selesai: this.tglYmd('selesai'),
                    hp: teks(f.hp), tanggal_lahir: this.tglYmd('lahir'),
                    pernyataan: f.pernyataan ? '1' : '',
                };
            },

            tampil: function (k) {
                var p = this.kiriman();
                var v;
                if (k === 'tanggal_mulai' || k === 'tanggal_selesai' || k === 'tanggal_lahir') { v = this.tglIndo(p[k]); }
                else if (k === 'perusahaan_telepon' || k === 'hp') { v = telp(p[k]); }
                else { v = p[k]; }
                return teks(v) || '—';
            },

            // ================= Kirim =================
            kirim: function () {
                // Ketukan ganda cepat: atribut `disabled` baru terpasang setelah render, jadi tahan di sini.
                if (this.mengirim) { return; }
                var self = this;
                var semua = {};
                var langkahGalat = 0;
                for (var n = 1; n <= 4; n++) {
                    var e = this.validasi(n);
                    if (Object.keys(e).length && !langkahGalat) { langkahGalat = n; }
                    Object.assign(semua, e);
                }
                this.err = semua;
                if (langkahGalat) {
                    this.pesanKirim = 'Masih ada isian yang belum benar. Periksa bagian yang ditandai merah.';
                    if (langkahGalat !== this.step) { this.keLangkah(langkahGalat); }
                    this.gulirKeGalat();
                    return;
                }
                if (!this.f.pernyataan) {
                    this.err = { pernyataan: 'Centang pernyataan bahwa data sudah benar.' };
                    this.gulirKeGalat();
                    return;
                }

                this.mengirim = true;
                this.pesanKirim = '';
                var fd = new FormData();
                var p = this.kiriman();
                Object.keys(p).forEach(function (k) { fd.append(k, p[k]); });
                this.teman.forEach(function (t) { fd.append('teman[]', t.id); });
                fd.append('siswa_id', this.pilih.id);
                if (this.ajuanId) { fd.append('ajuan_id', this.ajuanId); }
                fd.append('website', this.$refs.hp ? this.$refs.hp.value : '');

                fetch(this.cfg.urlKirim, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.ok) {
                            hapusLokal(DRAF_PREFIX + self.pilih.id);
                            window.location.href = j.redirect;
                            return;
                        }
                        self.mengirim = false;
                        self.err = j.errors || {};
                        self.pesanKirim = j.message || 'Ajuan gagal dikirim.';
                        var tuju = 0;
                        Object.keys(LANGKAH_KUNCI).some(function (n) {
                            var ada = LANGKAH_KUNCI[n].some(function (k) { return self.err[k]; });
                            if (ada) { tuju = Number(n); }
                            return ada;
                        });
                        if (tuju && tuju !== self.step) { self.keLangkah(tuju); }
                        if (tuju) { self.gulirKeGalat(); }
                    })
                    .catch(function () {
                        self.mengirim = false;
                        self.pesanKirim = 'Gagal terhubung ke server. Periksa internet lalu tekan Kirim lagi — isianmu tidak hilang.';
                    });
            },

            // ================= Draf di HP =================
            jadwalkanDraf: function () {
                if (!this.pilih || this.step < 1 || this.modeRevisi) { return; }
                var self = this;
                clearTimeout(this._tunda);
                this._tunda = setTimeout(function () {
                    simpanLokal(DRAF_PREFIX + self.pilih.id, { t: Date.now(), f: self.f, teman: self.teman });
                }, 400);
            },
        };
    });
});
