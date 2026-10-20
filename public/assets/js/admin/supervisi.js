/**
 * Supervisi Guru — komponen Alpine halaman Jadwal Supervisi ("supervisiJadwal", perluasan masterList; siap CSP, tanpa skrip sebaris).
 *   - Dialog Tambah/Ubah: memilih guru → GET data-saran?guru_id= (mapel & kelas dari Penugasan, JP terbanyak dulu) → tombol saran
 *     mengisi mapel & kelas sekaligus. Daftar instrumen = kotak centang (form.instrumen = larik id teks).
 *   - Dialog Buat massal: daftar guru (data-guru-massal: {id, nama, sudah}) dengan kotak cari, "Centang yang belum dijadwalkan",
 *     "Kosongkan"; kirim dicegah bila belum ada guru/instrumen yang dicentang.
 *   - Simpan gagal (server mengembalikan isian lama, data-isian-lama) → dialog Tambah dibuka lagi terisi.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    var salinData = function (o) { return JSON.parse(JSON.stringify(o)); };

    Alpine.data('supervisiJadwal', function () {
        var akar = null; // di metode Alpine versi CSP this.$el = elemen pemicu
        var urlSaran = '';
        var semuaGuru = [];
        var massalAwal = { instrumen: [] };
        var nomor = 0; // urutan permintaan saran: jawaban lama yang datang belakangan diabaikan

        return window.masterList({
            saran: [],
            memuatSaran: false,
            pesanSaran: '',
            massalOpen: false,
            massal: { guru: [], instrumen: [] },
            cariGuru: '',
            guruTampil: [],
            pesanMassal: '',

            onInit: function () {
                var self = this;
                akar = this.$el;
                urlSaran = akar.getAttribute('data-saran') || '';
                semuaGuru = window.readJson(akar, 'guru-massal', []) || [];
                massalAwal = window.readJson(akar, 'massal-awal', { instrumen: [] }) || { instrumen: [] };
                this.guruTampil = semuaGuru.slice();
                this.$watch('form.guru_id', function () { self.muatSaran(); });
                var lama = window.readJson(akar, 'isian-lama', null);
                if (lama) {
                    this.openAdd();
                    this.form = Object.assign(this.form, lama);
                }
            },

            // masterList.openAdd menyalin defaults secara dangkal → larik instrumen ikut berubah; di sini disalin utuh.
            openAdd: function () {
                this.mode = 'add';
                this.form = salinData(this.defaults);
                this.actionUrl = this.base;
                this.open = true;
                this.fokusDialog();
            },
            mapEdit: function (r) {
                return {
                    id: r.id,
                    guru_id: String(r.guru_id || ''),
                    supervisor_id: String(r.supervisor_id || ''),
                    tanggal: r.tanggal || '',
                    jam: r.jam || '',
                    mapel_id: String(r.mapel_id || ''),
                    kelas_id: String(r.kelas_id || ''),
                    instrumen: (r.instrumen || []).map(String),
                    catatan: r.catatan || '',
                    batal: !!r.batal,
                    ada_nilai: !!r.ada_nilai,
                };
            },
            punyaInstrumen: function (id) { return (this.form.instrumen || []).indexOf(String(id)) !== -1; },

            /* ---- saran mapel & kelas dari Penugasan ---- */
            muatSaran: function () {
                var self = this;
                var gid = String(this.form.guru_id || '');
                this.saran = [];
                this.pesanSaran = '';
                if (!gid || !urlSaran) { this.memuatSaran = false; return; }
                var ke = ++nomor;
                this.memuatSaran = true;
                fetch(urlSaran + '?guru_id=' + encodeURIComponent(gid), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) {
                        if (ke !== nomor) { return; }
                        self.memuatSaran = false;
                        self.saran = j && Array.isArray(j.saran) ? j.saran : [];
                        self.pesanSaran = !j ? 'Saran gagal dimuat. Muat ulang halaman (mungkin sesi masuk sudah habis).'
                            : (self.saran.length ? '' : 'Guru ini belum punya penugasan mengajar — pilih mapel & kelas sendiri bila perlu.');
                    })
                    .catch(function () {
                        if (ke !== nomor) { return; }
                        self.memuatSaran = false;
                        self.pesanSaran = 'Saran gagal dimuat. Periksa sambungan internet.';
                    });
            },
            adaPesanSaran: function () { return !this.memuatSaran && this.pesanSaran !== ''; },
            kunciSaran: function (s) { return s.mapel_id + '-' + s.kelas_id; },
            labelSaran: function (s) { return s.mapel + ' — ' + s.kelas; },
            saranTerpilih: function (s) {
                return String(this.form.mapel_id) === String(s.mapel_id) && String(this.form.kelas_id) === String(s.kelas_id);
            },
            kelasSaran: function (s) { return { 'ring-2 ring-brand-500': this.saranTerpilih(s) }; },
            pakaiSaran: function (s) {
                this.form.mapel_id = String(s.mapel_id);
                this.form.kelas_id = String(s.kelas_id);
            },

            /* ---- buat massal ---- */
            bukaMassal: function () {
                this.massal = { guru: [], instrumen: salinData(massalAwal.instrumen || []) };
                this.cariGuru = '';
                this.guruTampil = semuaGuru.slice();
                this.pesanMassal = '';
                this.massalOpen = true;
                setTimeout(function () {
                    var el = akar ? akar.querySelector('#sm-sup') : null;
                    if (el) { el.focus(); }
                }, 60);
            },
            tutupMassal: function () { this.massalOpen = false; },
            saringGuru: function () {
                var q = String(this.cariGuru || '').trim().toLowerCase();
                this.guruTampil = q === '' ? semuaGuru.slice() : semuaGuru.filter(function (g) { return g.nama.toLowerCase().indexOf(q) !== -1; });
            },
            centangBelum: function () {
                var pilih = this.massal.guru.slice();
                this.guruTampil.forEach(function (g) {
                    if (!g.sudah && pilih.indexOf(g.id) === -1) { pilih.push(g.id); }
                });
                this.massal.guru = pilih;
            },
            kosongkanGuru: function () { this.massal.guru = []; },
            teksJumlahGuru: function () { return '(' + this.massal.guru.length + ' dicentang)'; },
            kirimMassal: function (e) {
                if (!this.massal.guru.length) {
                    e.preventDefault();
                    this.pesanMassal = 'Centang minimal satu guru.';
                } else if (!this.massal.instrumen.length) {
                    e.preventDefault();
                    this.pesanMassal = 'Centang minimal satu instrumen.';
                }
            },
        });
    });

    /*
     * Lembar penilaian ("supervisiLembar"): tab per instrumen (#ins-{id} di alamat membuka tab itu — dipakai saat simpan gagal),
     * nilai & predikat dihitung ulang tiap pilihan berubah (membaca radio yang tercentang; rumus sama dengan SupervisiPenilaian),
     * "Tidak perlu tindak lanjut" tidak bisa bersamaan dengan tindak lanjut lain, dan "Simpan & selesai" dicegah bila ada butir yang
     * belum diisi (tab instrumen itu dibuka + pesan). Elemen akar: data-predikat (JSON batas bawah → predikat), data-tab-awal.
     */
    Alpine.data('supervisiLembar', function () {
        var akar = null;
        var batas = []; // [[91, 'Amat Baik'], [76, 'Baik'], …] urut menurun

        var bulat2 = function (n) { return Math.round(n * 100) / 100; };
        var teksAngka = function (n) { return n === null || n === undefined ? '—' : n.toFixed(2).replace('.', ','); };
        var predikatDari = function (n) {
            if (n === null || n === undefined) { return ''; }
            for (var i = 0; i < batas.length; i++) {
                if (n >= batas[i][0]) { return batas[i][1]; }
            }
            return batas.length ? batas[batas.length - 1][1] : '';
        };

        return {
            tab: '',
            ringkas: {},
            akhir: null,
            pesan: '',

            init: function () {
                var self = this;
                akar = this.$el;
                var p = window.readJson(akar, 'predikat', {}) || {};
                batas = Object.keys(p).map(function (k) { return [Number(k), p[k]]; }).sort(function (a, b) { return b[0] - a[0]; });
                var h = String(window.location.hash || '').replace('#ins-', '');
                this.tab = h && akar.querySelector('[data-instrumen="' + h + '"]') ? h : (akar.getAttribute('data-tab-awal') || '');
                this.hitung();
                akar.addEventListener('change', function (e) {
                    self.aturTindakLanjut(e.target);
                    self.hitung();
                    if (e.target && e.target.type === 'radio') { self.pesan = ''; }
                });
            },

            pilihTab: function (id) { this.tab = String(id); },
            tabIni: function (id) { return this.tab === String(id); },

            hitung: function () {
                var r = {};
                var semua = [];
                akar.querySelectorAll('[data-instrumen]').forEach(function (sec) {
                    var x = { total: 0, dinilai: 0, td: 0, kosong: 0, jumlah: 0, nilai: null };
                    sec.querySelectorAll('[data-butir]').forEach(function (b) {
                        x.jumlah++;
                        var c = b.querySelector('input[type="radio"]:checked');
                        if (!c) { x.kosong++; } else if (c.value === '0') { x.td++; } else { x.total += parseInt(c.value, 10); x.dinilai++; }
                    });
                    x.maks = x.dinilai * 4;
                    x.nilai = x.dinilai ? bulat2(x.total / x.maks * 100) : null;
                    if (x.nilai !== null) { semua.push(x.nilai); }
                    r[sec.getAttribute('data-instrumen')] = x;
                });
                this.ringkas = r;
                this.akhir = semua.length ? bulat2(semua.reduce(function (a, b) { return a + b; }, 0) / semua.length) : null;
            },
            infoIns: function (id) { return this.ringkas[String(id)] || null; },
            kemajuan: function (id) { var x = this.infoIns(id); return x ? (x.jumlah - x.kosong) + '/' + x.jumlah : ''; },
            lengkap: function (id) { var x = this.infoIns(id); return !!x && x.jumlah > 0 && x.kosong === 0 && x.dinilai > 0; },
            kelasKemajuan: function (id) { return { 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': this.lengkap(id) }; },
            teksSkor: function (id) { var x = this.infoIns(id); return x ? x.total + ' dari ' + x.maks : '—'; },
            teksNilai: function (id) { var x = this.infoIns(id); return teksAngka(x ? x.nilai : null); },
            teksPredikat: function (id) { var x = this.infoIns(id); return (x && predikatDari(x.nilai)) || '—'; },
            teksAkhir: function () { return teksAngka(this.akhir); },
            teksPredikatAkhir: function () { var p = predikatDari(this.akhir); return p ? '(' + p + ')' : ''; },

            aturTindakLanjut: function (el) {
                if (!el || !el.hasAttribute || !el.hasAttribute('data-tl') || !el.checked) { return; }
                var sec = el.closest('[data-instrumen]');
                if (!sec) { return; }
                sec.querySelectorAll('input[data-tl]').forEach(function (c) {
                    if (c !== el && (el.value === 'tidak_perlu' || c.value === 'tidak_perlu')) { c.checked = false; }
                });
            },

            kirim: function (e) {
                var tombol = e.submitter;
                if (!tombol || tombol.value !== 'selesai') { return; }
                this.hitung();
                var secs = akar.querySelectorAll('[data-instrumen]');
                for (var i = 0; i < secs.length; i++) {
                    var id = secs[i].getAttribute('data-instrumen');
                    var x = this.infoIns(id);
                    if (!this.lengkap(id)) {
                        e.preventDefault();
                        this.tab = id;
                        this.pesan = '"' + (secs[i].getAttribute('data-nama') || 'Instrumen') + '" belum lengkap: '
                            + (!x || !x.jumlah ? 'belum punya butir.' : (x.kosong ? x.kosong + ' butir belum diisi.' : 'minimal satu butir harus diberi skor 1–4.'));
                        return;
                    }
                }
            },
        };
    });
});
