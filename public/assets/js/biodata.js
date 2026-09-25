/**
 * Form isian biodata siswa (publik) — komponen Alpine `biodataForm`.
 *
 * Validasi di sini hanya untuk kenyamanan (pesan langsung di tiap langkah);
 * penentu tetap server (App\Libraries\BiodataForm). Kunci galat memakai
 * nama kolom yang SAMA dengan server, jadi galat dari server langsung
 * menempel di kolom yang benar.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var DRAF_PREFIX = 'biodata_draft_v1_';
    var DRAF_UMUR = 7 * 24 * 3600 * 1000; // draf lebih tua dari 7 hari dibuang

    /** Langkah tempat tiap kunci galat berada (untuk melompat ke galat pertama). */
    var LANGKAH_KUNCI = {
        1: ['nama', 'nisn', 'nis', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'status_keluarga', 'anak_ke'],
        2: ['alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'no_hp', 'sekolah_asal', 'diterima_kelas', 'diterima_tanggal'],
        3: ['nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'ortu_alamat', 'ortu_rt', 'ortu_rw',
            'ortu_kelurahan', 'ortu_kecamatan', 'ortu_kota', 'ortu_telepon'],
        4: ['punya_wali', 'nama_wali', 'alamat_wali', 'no_hp_wali', 'pekerjaan_wali'],
        5: ['pernyataan'],
    };
    var ALAMAT = ['alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota'];

    // ---------------- util ----------------
    function isiKosong() {
        return {
            nama: '', nisn: '', nis: '', tempat_lahir: '', lahir_d: '', lahir_m: '', lahir_y: '',
            jenis_kelamin: '', agama: '', status_keluarga: '', anak_ke: '',
            alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '', kota: '', no_hp: '',
            sekolah_asal: '', diterima_kelas: '', terima_d: '', terima_m: '', terima_y: '',
            nama_ayah: '', pekerjaan_ayah: '', pekerjaan_ayah_lain: '',
            nama_ibu: '', pekerjaan_ibu: '', pekerjaan_ibu_lain: '',
            ortu_sama: true, ortu_alamat: '', ortu_rt: '', ortu_rw: '', ortu_kelurahan: '', ortu_kecamatan: '', ortu_kota: '',
            ortu_telepon1: '', ortu_telepon2: '',
            punya_wali: '', nama_wali: '', alamat_wali: '', no_hp_wali: '', pekerjaan_wali: '', pekerjaan_wali_lain: '',
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
    function telpSah(v) { return /^0\d{7,14}$/.test(telp(v)); }
    function tanggalSah(y, m, d) {
        var t = new Date(Number(y), Number(m) - 1, Number(d));
        return t.getFullYear() === Number(y) && t.getMonth() === Number(m) - 1 && t.getDate() === Number(d);
    }
    function rtSah(v) { var s = teks(v).replace(/\D/g, ''); return s !== '' && s.length <= 3 && Number(s) > 0; }
    function simpanLokal(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* abaikan */ } }
    function bacaLokal(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
    function hapusLokal(k) { try { localStorage.removeItem(k); } catch (e) { /* abaikan */ } }

    Alpine.data('biodataForm', function () {
        return {
            cfg: {},
            step: 0,
            judulLangkah: ['Cari Nama', 'Data Diri', 'Alamat & Sekolah Asal', 'Orang Tua', 'Wali', 'Periksa & Kirim'],
            langkahBagian: { 'Data Diri': 1, 'Alamat & Sekolah Asal': 2, 'Orang Tua': 3, 'Wali': 4 },

            // Langkah 1
            kelasId: '',
            siswaList: [],
            memuat: false,
            pesanDaftar: '',
            cari: '',
            pilih: null,
            buka: { nisn: '', d: '', m: '', y: '', pesan: '', proses: false },
            // Pengingat Kartu Keluarga (pop-up sebelum mulai mengisi)
            kk: { buka: false, setuju: false, centang: false, aksi: '' },

            // Isian
            f: isiKosong(),
            err: {},
            modeRevisi: false,
            catatan: '',
            mengirim: false,
            pesanKirim: '',
            draftInfo: '',
            _tunda: null,

            init: function () {
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                var self = this;
                // Draf disimpan setiap ada ketikan (ditunda 400 ms agar hemat).
                this.$el.addEventListener('input', function () { self.jadwalkanDraf(); });
                this.$el.addEventListener('change', function () { self.jadwalkanDraf(); });
            },

            // ================= Langkah 1: cari nama =================
            muatSiswa: function () {
                var self = this;
                this.pilih = null;
                this.siswaList = [];
                this.cari = '';
                this.pesanDaftar = '';
                if (!this.kelasId) { return; }
                this.memuat = true;
                fetch(this.cfg.urlSiswa + '?kelas_id=' + encodeURIComponent(this.kelasId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.ok) {
                            self.siswaList = j.data || [];
                            if (!self.siswaList.length) { self.pesanDaftar = 'Belum ada nama siswa di kelas ini. Hubungi wali kelas.'; }
                        } else {
                            self.pesanDaftar = j.message || 'Daftar nama gagal dimuat.';
                        }
                    })
                    .catch(function () { self.pesanDaftar = 'Gagal terhubung. Periksa internet lalu pilih kelas lagi.'; })
                    .finally(function () { self.memuat = false; });
            },

            siswaTersaring: function () {
                var q = this.cari.trim().toLowerCase();
                if (!q) { return this.siswaList; }
                return this.siswaList.filter(function (s) { return s.nama.toLowerCase().indexOf(q) !== -1; });
            },

            lencana: function (status) {
                return {
                    menunggu: { teks: 'Sudah mengisi', kelas: 'bg-blue-100 text-blue-700' },
                    disetujui: { teks: 'Terverifikasi', kelas: 'bg-green-100 text-green-700' },
                    perbaikan: { teks: 'Perlu perbaikan', kelas: 'bg-amber-100 text-amber-800' },
                }[status] || { teks: '', kelas: '' };
            },

            namaKelas: function () {
                var k = this.cfg.kelas[this.kelasId];
                return k ? 'Kelas ' + k.nama : '';
            },

            pilihSiswa: function (s) {
                var self = this;
                this.pilih = s;
                this.buka = { nisn: '', d: '', m: '', y: '', pesan: '', proses: false };
                this.$nextTick(function () {
                    if (self.$refs.panel) { self.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
            },

            // ================= Pengingat Kartu Keluarga =================
            /**
             * Pop-up KK muncul sebelum siswa mulai / membuka isian. Cukup sekali
             * per kunjungan: setelah dicentang & disetujui, aksi berikutnya langsung jalan.
             */
            mintaKK: function (aksi) {
                if (aksi !== 'mulai' && aksi !== 'bukaIsian') { return; }
                if (this.kk.setuju) { this[aksi](); return; }
                this.kk.aksi = aksi;
                this.kk.centang = false;
                this.kk.buka = true;
            },

            setujuKK: function () {
                if (!this.kk.centang) { return; }
                this.kk.setuju = true;
                this.kk.buka = false;
                this[this.kk.aksi]();
            },

            tutupKK: function () {
                this.kk.buka = false;
            },

            /** Siswa belum pernah mengisi → siapkan isian (nama & JK dari data sekolah, lalu draf bila ada). */
            mulai: function () {
                var s = this.pilih;
                var k = this.cfg.kelas[this.kelasId] || {};
                this.f = isiKosong();
                this.f.nama = s.nama;
                this.f.jenis_kelamin = s.jk || '';
                // Siswa kelas X: kelas saat diterima = kelasnya sekarang.
                if (k.tingkat === 'X') { this.f.diterima_kelas = k.nama; }
                this.modeRevisi = false;
                this.catatan = '';
                this.err = {};
                this.pesanKirim = '';

                var draf = bacaLokal(DRAF_PREFIX + s.id);
                if (draf && draf.f && (Date.now() - draf.t) < DRAF_UMUR) {
                    this.f = Object.assign(isiKosong(), draf.f);
                    this.draftInfo = 'Isianmu yang belum terkirim sudah dipulihkan.';
                } else {
                    this.draftInfo = '';
                }
                this.keLangkah(1);
            },

            /** Siswa berstatus perbaikan → buka isian lama dengan NISN / tanggal lahir. */
            bukaIsian: function () {
                var self = this;
                var b = this.buka;
                var tgl = '';
                if (b.d || b.m || b.y) {
                    if (!(b.d && b.m && b.y) || !tanggalSah(b.y, b.m, b.d)) {
                        b.pesan = 'Lengkapi tanggal lahir dengan benar.';
                        return;
                    }
                    tgl = b.y + '-' + dua(b.m) + '-' + dua(b.d);
                }
                if (!teks(b.nisn) && !tgl) {
                    b.pesan = 'Isi NISN atau tanggal lahirmu.';
                    return;
                }
                b.pesan = '';
                b.proses = true;
                var fd = new FormData();
                fd.append('siswa_id', this.pilih.id);
                fd.append('nisn', teks(b.nisn));
                fd.append('tanggal_lahir', tgl);
                fetch(this.cfg.urlBuka, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j.ok) { b.pesan = j.message || 'Isian gagal dibuka.'; return; }
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

            /** Isi form dari data isian lama (format kolom server). */
            isiDari: function (d) {
                var f = isiKosong();
                var pekerjaan = this.cfg.pekerjaan || [];
                var ambil = function (k) { return d[k] === null || d[k] === undefined ? '' : String(d[k]); };
                Object.keys(f).forEach(function (k) { if (d[k] !== undefined && typeof f[k] === 'string') { f[k] = ambil(k); } });

                var pecah = function (tgl, awalan) {
                    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(tgl || '');
                    if (m) { f[awalan + '_y'] = String(Number(m[1])); f[awalan + '_m'] = String(Number(m[2])); f[awalan + '_d'] = String(Number(m[3])); }
                };
                pecah(d.tanggal_lahir, 'lahir');
                pecah(d.diterima_tanggal, 'terima');

                ['pekerjaan_ayah', 'pekerjaan_ibu', 'pekerjaan_wali'].forEach(function (k) {
                    var v = ambil(k);
                    if (v !== '' && pekerjaan.indexOf(v) === -1) { f[k] = '__lain'; f[k + '_lain'] = v; } else { f[k] = v; }
                });

                f.ortu_sama = ALAMAT.every(function (k) { return ambil(k) === ambil('ortu_' + k); });
                var tel = ambil('ortu_telepon').split('/').map(function (s) { return s.trim(); });
                f.ortu_telepon1 = tel[0] || '';
                f.ortu_telepon2 = tel[1] || '';
                f.punya_wali = ambil('nama_wali') !== '' ? 'ya' : 'tidak';
                f.pernyataan = false;
                this.f = f;
            },

            // ================= Navigasi =================
            keLangkah: function (n) {
                this.step = n;
                this.$nextTick(function () {
                    var top = document.getElementById('bioTop');
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
                var e = {};
                var wajib = function (k, pesan) { if (kosong(f[k])) { e[k] = pesan; } };
                var nama = function (k, label) {
                    if (kosong(f[k])) { e[k] = label + ' wajib diisi.'; } else if (!namaSah(f[k])) { e[k] = label + ' hanya boleh berisi huruf.'; }
                };
                var pekerjaan = function (k, label) {
                    if (kosong(f[k]) || (f[k] === '__lain' && kosong(f[k + '_lain']))) { e[k] = 'Pekerjaan ' + label + ' wajib diisi.'; }
                };
                var alamat = function (p, siapa) {
                    wajib(p + 'alamat', 'Alamat' + siapa + ' wajib diisi (nama jalan/perumahan, blok, nomor).');
                    ['rt', 'rw'].forEach(function (k) {
                        if (kosong(f[p + k])) { e[p + k] = k.toUpperCase() + siapa + ' wajib diisi.'; } else if (!rtSah(f[p + k])) { e[p + k] = k.toUpperCase() + siapa + ' tidak valid (contoh: 18).'; }
                    });
                    wajib(p + 'kelurahan', 'Kelurahan/desa' + siapa + ' wajib diisi.');
                    wajib(p + 'kecamatan', 'Kecamatan' + siapa + ' wajib diisi.');
                    wajib(p + 'kota', 'Kota/kabupaten' + siapa + ' wajib diisi.');
                };

                if (langkah === 1) {
                    nama('nama', 'Nama lengkap');
                    var nisn = teks(f.nisn).replace(/\s/g, '');
                    if (!nisn) { e.nisn = 'NISN wajib diisi. Lihat di kartu pelajar atau rapor.'; } else if (!/^\d{10}$/.test(nisn)) { e.nisn = 'NISN harus tepat 10 angka (contoh: 0094624339).'; }
                    if (!kosong(f.nis) && !/^[0-9A-Za-z./-]{1,30}$/.test(teks(f.nis).replace(/\s/g, ''))) { e.nis = 'NIS hanya boleh berisi angka.'; }
                    wajib('tempat_lahir', 'Tempat lahir wajib diisi.');
                    if (!f.lahir_d || !f.lahir_m || !f.lahir_y) { e.tanggal_lahir = 'Lengkapi tanggal, bulan, dan tahun lahir.'; } else if (!tanggalSah(f.lahir_y, f.lahir_m, f.lahir_d)) { e.tanggal_lahir = 'Tanggal lahir tidak valid. Periksa tanggal, bulan, dan tahunnya.'; }
                    if (f.jenis_kelamin !== 'L' && f.jenis_kelamin !== 'P') { e.jenis_kelamin = 'Pilih jenis kelamin.'; }
                    wajib('agama', 'Pilih agama.');
                    wajib('status_keluarga', 'Pilih status dalam keluarga.');
                    var anak = teks(f.anak_ke);
                    if (!/^\d{1,2}$/.test(anak) || Number(anak) < 1 || Number(anak) > 30) { e.anak_ke = 'Isi anak ke berapa dengan angka (contoh: 1).'; }
                }
                if (langkah === 2) {
                    alamat('', '');
                    if (!kosong(f.no_hp) && !telpSah(f.no_hp)) { e.no_hp = 'Nomor telepon tidak valid (contoh: 081234567890).'; }
                    wajib('sekolah_asal', 'Sekolah asal (SMP/MTs) wajib diisi.');
                    wajib('diterima_kelas', 'Kelas saat pertama diterima wajib diisi (contoh: X TKJ 8).');
                    var adaTerima = f.terima_d || f.terima_m || f.terima_y;
                    if (adaTerima && (!(f.terima_d && f.terima_m && f.terima_y) || !tanggalSah(f.terima_y, f.terima_m, f.terima_d))) {
                        e.diterima_tanggal = 'Lengkapi tanggal, bulan, dan tahun — atau kosongkan ketiganya.';
                    }
                }
                if (langkah === 3) {
                    nama('nama_ayah', 'Nama ayah');
                    pekerjaan('pekerjaan_ayah', 'ayah');
                    nama('nama_ibu', 'Nama ibu');
                    pekerjaan('pekerjaan_ibu', 'ibu');
                    if (!f.ortu_sama) { alamat('ortu_', ' orang tua'); }
                    if (kosong(f.ortu_telepon1)) { e.ortu_telepon = 'Nomor telepon orang tua wajib diisi.'; } else if (!telpSah(f.ortu_telepon1) || (!kosong(f.ortu_telepon2) && !telpSah(f.ortu_telepon2))) { e.ortu_telepon = 'Nomor telepon orang tua tidak valid (contoh: 081234567890).'; }
                }
                if (langkah === 4) {
                    if (f.punya_wali !== 'ya' && f.punya_wali !== 'tidak') { e.punya_wali = 'Pilih apakah kamu memiliki wali selain orang tua.'; }
                    if (f.punya_wali === 'ya') {
                        nama('nama_wali', 'Nama wali');
                        if (kosong(f.no_hp_wali)) { e.no_hp_wali = 'Nomor telepon wali wajib diisi.'; } else if (!telpSah(f.no_hp_wali)) { e.no_hp_wali = 'Nomor telepon wali tidak valid (contoh: 081234567890).'; }
                    }
                }
                if (langkah === 5 && !f.pernyataan) {
                    e.pernyataan = 'Centang pernyataan bahwa data sudah benar dan sesuai Kartu Keluarga.';
                }
                return e;
            },

            // ================= Ringkasan =================
            /** Isian final dalam format kolom server. */
            kiriman: function () {
                var f = this.f;
                var tgl = function (p) { return (f[p + '_y'] && f[p + '_m'] && f[p + '_d']) ? f[p + '_y'] + '-' + dua(f[p + '_m']) + '-' + dua(f[p + '_d']) : ''; };
                var pk = function (k) { return f[k] === '__lain' ? teks(f[k + '_lain']) : teks(f[k]); };
                var wali = f.punya_wali === 'ya';
                var p = {
                    nama: teks(f.nama), nisn: teks(f.nisn), nis: teks(f.nis), tempat_lahir: teks(f.tempat_lahir),
                    tanggal_lahir: tgl('lahir'), jenis_kelamin: f.jenis_kelamin, agama: f.agama,
                    status_keluarga: f.status_keluarga, anak_ke: teks(f.anak_ke),
                    no_hp: teks(f.no_hp), sekolah_asal: teks(f.sekolah_asal), diterima_kelas: teks(f.diterima_kelas),
                    diterima_tanggal: tgl('terima'),
                    nama_ayah: teks(f.nama_ayah), pekerjaan_ayah: pk('pekerjaan_ayah'),
                    nama_ibu: teks(f.nama_ibu), pekerjaan_ibu: pk('pekerjaan_ibu'),
                    ortu_sama: f.ortu_sama ? '1' : '0',
                    ortu_telepon1: teks(f.ortu_telepon1), ortu_telepon2: teks(f.ortu_telepon2),
                    punya_wali: f.punya_wali,
                    nama_wali: wali ? teks(f.nama_wali) : '', alamat_wali: wali ? teks(f.alamat_wali) : '',
                    no_hp_wali: wali ? teks(f.no_hp_wali) : '', pekerjaan_wali: wali ? pk('pekerjaan_wali') : '',
                    pernyataan: f.pernyataan ? '1' : '',
                };
                ALAMAT.forEach(function (k) {
                    p[k] = teks(f[k]);
                    p['ortu_' + k] = f.ortu_sama ? teks(f[k]) : teks(f['ortu_' + k]);
                });
                return p;
            },

            tampil: function (k) {
                var p = this.kiriman();
                var bulan = this.cfg.bulan || [];
                var tanggal = function (v) {
                    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
                    return m ? Number(m[3]) + ' ' + bulan[Number(m[2]) - 1] + ' ' + m[1] : '';
                };
                var v;
                if (k === 'jenis_kelamin') { v = { L: 'Laki-laki', P: 'Perempuan' }[p.jenis_kelamin] || ''; }
                else if (k === 'tanggal_lahir' || k === 'diterima_tanggal') { v = tanggal(p[k]); }
                else if (k === 'ortu_telepon') { v = [p.ortu_telepon1, p.ortu_telepon2].filter(Boolean).join(' / '); }
                else { v = p[k]; }
                return teks(v) || '—';
            },

            // ================= Kirim =================
            kirim: function () {
                var self = this;
                var semua = {};
                var langkahGalat = 0;
                for (var n = 1; n <= 5; n++) {
                    var e = this.validasi(n);
                    if (Object.keys(e).length && !langkahGalat) { langkahGalat = n; }
                    Object.assign(semua, e);
                }
                this.err = semua;
                if (langkahGalat) {
                    this.pesanKirim = langkahGalat < 5 ? 'Masih ada isian yang belum benar. Periksa bagian yang ditandai merah.' : '';
                    if (langkahGalat !== this.step) { this.keLangkah(langkahGalat); }
                    this.gulirKeGalat();
                    return;
                }

                this.mengirim = true;
                this.pesanKirim = '';
                var fd = new FormData();
                var p = this.kiriman();
                Object.keys(p).forEach(function (k) { fd.append(k, p[k]); });
                fd.append('siswa_id', this.pilih.id);
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
                        self.pesanKirim = j.message || 'Biodata gagal dikirim.';
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
                    simpanLokal(DRAF_PREFIX + self.pilih.id, { t: Date.now(), f: self.f });
                }, 400);
            },
        };
    });
});
