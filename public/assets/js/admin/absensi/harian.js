/**
 * Absensi Guru harian (Absensi → Absensi Guru) — rombak tampilan F6: dulu fungsi global absensiPage(cfg) & absensiKerja() di skrip
 * sebaris (x-data='absensiPage({…})', isian berisi penugasan & beberapa pernyataan, $dispatch berantai, $el, confirm()/prompt()
 * bawaan, toast buatan sendiri) dan objek sebaris di tiap baris sesi. Kini tiga komponen Alpine.data di berkas ini (siap Alpine
 * versi CSP; isian = properti / panggilan metode sederhana):
 *   - absensiPage  — akar halaman (data-cfg): shift, belum hadir, ringkasan, saringan per kelas, simpan & kirim WhatsApp, format
 *                    pesan. Dialog kit: pilih-belum, jam-datang (pengganti prompt), format-wa, pesan-wa. Pesan lewat Ui.toast,
 *                    pertanyaan lewat Ui.konfirmasi.
 *   - absensiKerja — panel Kehadiran Kerja (data-kerja & data-guru).
 *   - sesiAbsen    — satu baris sesi (_sesi_row.php; nilai awal dari data-st / data-jm / data-ket / data-mulai), mendengar acara
 *                    absen-setall (Set semua) & absen-datang (Sudah datang).
 * Metode komponen bersarang memakai this = gabungan cakupan Alpine, jadi baris sesi bisa memanggil isBelum()/shift milik akar.
 * Atribut data-* & data-role baris dibaca siapkanKirim()/stats(): jangan diubah tanpa menyesuaikan keduanya.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    // Garis kiri baris sesi per status (hadir hijau, telat kuning, izin biru, sakit pink, alpa merah — sama dengan Rekap).
    var WARNA_BARIS = { hadir: 'border-l-green-400', telat: 'border-l-yellow-400', izin: 'border-l-blue-400', sakit: 'border-l-pink-400', alpa: 'border-l-red-400' };

    var dua = function (n) { return (n < 10 ? '0' : '') + n; };
    var beri = function (pesan, jenis) { if (window.Ui) { window.Ui.toast(pesan, jenis || 'sukses'); } };
    var kirimForm = function (url, data) {
        return fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
            .then(function (res) { return res.json(); });
    };
    // Salin ke papan klip; cadangan execCommand untuk http:// (clipboard API hanya di konteks aman).
    var salinLama = function (teks) {
        var ta = document.createElement('textarea');
        ta.value = teks;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    };
    var salin = function (teks) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(teks).then(function () { return true; }, function () { return salinLama(teks); });
        }
        return Promise.resolve(salinLama(teks));
    };

    Alpine.data('absensiPage', function () {
        var cfg = {};

        return {
            tanggal: '',
            shift: 'pagi',
            tampilan: 'kelas',
            belum: { pagi: [], siang: [] },
            guruShift: { pagi: [], siang: [] },
            piket: { pagi: [], siang: [] },
            guru: [],
            tpl: '',
            custom: false,
            tick: 0,
            dirty: false,          // ada perubahan belum disimpan (konfirmasi saat ganti tampilan)
            jurusanShift: { pagi: [], siang: [] },
            kartu: {},             // data saringan kartu kelas: {kelas_id: {shifts, jur, cari, gids}}
            fJur: '',
            fCari: '',
            sending: false,
            pickQ: '',
            pickSel: [],
            waText: '',
            datangGid: 0,
            datangJam: '',

            init: function () {
                var self = this;
                cfg = window.readJson(this.$el, 'cfg', {});
                var b = cfg.belum || {}, p = cfg.piket || {};
                this.tanggal = cfg.tanggal || '';
                this.shift = cfg.shift || 'pagi';
                this.tampilan = cfg.tampilan || 'kelas';
                this.belum = { pagi: (b.pagi || []).map(Number), siang: (b.siang || []).map(Number) };
                this.guruShift = cfg.guruShift || { pagi: [], siang: [] };
                this.piket = { pagi: (p.pagi || []).map(Number), siang: (p.siang || []).map(Number) };
                this.guru = cfg.guru || [];
                this.tpl = cfg.template || '';
                this.custom = !!cfg.custom;
                this.jurusanShift = cfg.jurusan || { pagi: [], siang: [] };
                this.kartu = cfg.kartu || {};
                // Ringkasan dihitung dari isian status baris; baris baru siap setelah pohon Alpine selesai dimulai.
                this.$nextTick(function () { self.tick++; });
            },

            tandaiUbah: function () { this.tick++; this.dirty = true; },
            setShift: function (s) {
                this.shift = s;
                try {
                    var u = new URL(window.location.href);
                    u.searchParams.set('shift', s);
                    history.replaceState(null, '', u);
                } catch (e) { /* abaikan */ }
                this.tick++;
            },
            namaGuru: function (id) {
                var g = this.guru.filter(function (x) { return x.id === Number(id); })[0];
                return g ? g.nama : ('Guru #' + id);
            },
            // Pindah per kelas ↔ per guru = muat ulang halaman (daftar dirender server).
            gantiTampilan: function (t) {
                var self = this;
                if (t === this.tampilan) { return; }
                var lanjut = function () {
                    var u = new URL(window.location.href);
                    u.searchParams.set('tampilan', t);
                    u.searchParams.set('tanggal', self.tanggal);
                    u.searchParams.set('shift', self.shift);
                    window.location.href = u.toString();
                };
                if (!this.dirty) { lanjut(); return; }
                window.Ui.konfirmasi({ judul: 'Ganti tampilan?', pesan: 'Perubahan yang belum disimpan akan hilang. Tetap ganti tampilan?', ya: 'Ganti tampilan' })
                    .then(function (ya) { if (ya) { lanjut(); } });
            },

            // ---- saringan tampilan per kelas ----
            jurusanAktif: function () { return this.jurusanShift[this.shift] || []; },
            adaPilihanJurusan: function () { return this.jurusanAktif().length > 1; },
            // Jurusan terpilih yang tak ada di shift ini dianggap "Semua".
            jurAktif: function () { return this.jurusanAktif().indexOf(this.fJur) !== -1 ? this.fJur : ''; },
            pilihJurusan: function (j) { this.fJur = j; },
            kelasTampil: function (id) {
                var k = this.kartu[id];
                if (!k || (k.shifts || []).indexOf(this.shift) === -1) { return false; }
                var jur = this.jurAktif();
                if (jur && k.jur !== jur) { return false; }
                var q = this.fCari.trim().toLowerCase();
                return !q || (k.cari || '').indexOf(q) !== -1;
            },
            kelasKartu: function (id) {
                var self = this, k = this.kartu[id];
                return k && (k.gids || []).some(function (g) { return self.isBelum(g); }) ? 'border-red-300 dark:border-red-800' : '';
            },
            jumlahKelasTampil: function () {
                var self = this;
                return Object.keys(this.kartu).filter(function (id) { return self.kelasTampil(id); }).length;
            },
            kelasKosong: function () { return !this.tanpaSesi() && this.jumlahKelasTampil() === 0; },
            tanpaSesi: function () { return (this.guruShift[this.shift] || []).length === 0; },
            punyaSesi: function (gid) { return (this.guruShift[this.shift] || []).indexOf(gid) !== -1; },
            jumlahSesi: function (gid) {
                return document.querySelectorAll('.absen-row[data-guru-id="' + gid + '"][data-shift="' + this.shift + '"]').length;
            },
            teksSesi: function (gid) { return this.jumlahSesi(gid) + ' sesi KBM ' + this.shift; },

            // ---- belum hadir & piket ----
            daftarBelum: function () { return this.belum[this.shift] || []; },
            adaBelum: function () { return this.daftarBelum().length > 0; },
            isBelum: function (gid) { return this.daftarBelum().indexOf(Number(gid)) !== -1; },
            isPiket: function (gid) { return this.piket.pagi.indexOf(Number(gid)) !== -1 || this.piket.siang.indexOf(Number(gid)) !== -1; },
            piketShift: function (gid) { return (this.piket[this.shift] || []).indexOf(Number(gid)) !== -1; },
            labelPiket: function (gid) {
                var p = this.piket.pagi.indexOf(Number(gid)) !== -1, s = this.piket.siang.indexOf(Number(gid)) !== -1;
                return 'Piket ' + (p && s ? 'pagi+siang' : (p ? 'pagi' : 'siang'));
            },
            tandaiBelum: function (gid) {
                if (!this.isBelum(gid)) { this.belum[this.shift].push(Number(gid)); }
                this.dirty = true;
                this.tick++;
            },
            hapusBelum: function (gid) {
                this.belum[this.shift] = this.belum[this.shift].filter(function (x) { return x !== Number(gid); });
                this.dirty = true;
                this.tick++;
            },
            jamSekarang: function () { var d = new Date(); return dua(d.getHours()) + ':' + dua(d.getMinutes()); },
            hariIni: function () { var d = new Date(); return d.getFullYear() + '-' + dua(d.getMonth() + 1) + '-' + dua(d.getDate()); },
            // Guru tiba → dialog jam datang (terisi jam sekarang bila tanggal = hari ini). Jam selalu dikonfirmasi karena menentukan potongan.
            sudahDatang: function (gid) {
                this.datangGid = Number(gid);
                this.datangJam = this.tanggal === this.hariIni() ? this.jamSekarang() : '';
                window.Ui.bukaDialog('jam-datang');
            },
            // Keluarkan dari daftar belum hadir; sesi shift ini yang SUDAH dimulai saat ia datang ditandai Telat (jam masuk = jam datang).
            konfirmasiDatang: function () {
                var self = this, gid = this.datangGid, jam = String(this.datangJam || '').trim().slice(0, 5);
                if (!/^\d{1,2}:\d{2}$/.test(jam)) { beri('Format jam tidak valid (contoh 07:45).', 'galat'); return; }
                if (jam.length < 5) { jam = '0' + jam; }
                this.hapusBelum(gid);
                window.dispatchEvent(new CustomEvent('absen-datang', { detail: { gid: gid, shift: this.shift, jam: jam } }));
                this.$nextTick(function () { self.tick++; });
                window.Ui.tutupDialog('jam-datang');
                beri('Ditandai datang pukul ' + jam + '. Jangan lupa Simpan / Kirim WhatsApp.');
            },

            // ---- dialog pilih guru belum hadir ----
            openPicker: function () {
                this.pickQ = '';
                this.pickSel = [];
                window.Ui.bukaDialog('pilih-belum');
            },
            // Guru terjadwal KBM shift ini lebih dulu, lalu guru lain.
            pilihan: function () {
                var self = this, q = this.pickQ.trim().toLowerCase(), kbm = this.guruShift[this.shift] || [];
                return this.guru
                    .filter(function (g) { return !self.isBelum(g.id) && (!q || g.nama.toLowerCase().indexOf(q) !== -1); })
                    .map(function (g) { return { id: g.id, nama: g.nama, kbm: kbm.indexOf(g.id) !== -1 }; })
                    .sort(function (a, b) { return (b.kbm - a.kbm) || a.nama.localeCompare(b.nama); });
            },
            pilihanKosong: function () { return this.pilihan().length === 0; },
            teksTandai: function () { return 'Tandai (' + this.pickSel.length + ')'; },
            terapkanPilihan: function () {
                var self = this;
                this.pickSel.forEach(function (id) { self.tandaiBelum(id); });
                window.Ui.tutupDialog('pilih-belum');
            },

            // ---- ringkasan per guru (status terburuk sesi shift ini) + belum hadir ----
            stats: function () {
                void this.tick; // dihitung ulang saat tanda berubah
                var self = this, rank = { hadir: 0, telat: 1, izin: 2, sakit: 3, alpa: 4 }, per = {};
                document.querySelectorAll('.absen-row[data-shift="' + this.shift + '"]').forEach(function (r) {
                    var sel = r.querySelector('[data-role=status]'), gid = r.dataset.guruId, st = sel ? sel.value : 'hadir';
                    if (!(gid in per) || rank[st] > rank[per[gid]]) { per[gid] = st; }
                });
                var c = { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0 };
                Object.keys(per).forEach(function (gid) {
                    if (self.isBelum(gid) && (per[gid] === 'hadir' || per[gid] === 'telat')) { return; }
                    c[per[gid]]++;
                });
                return [
                    { k: 'hadir', l: 'Hadir', v: c.hadir, c: 'lencana-hijau' },
                    { k: 'telat', l: 'Telat', v: c.telat, c: 'lencana-kuning' },
                    { k: 'belum', l: 'Belum hadir', v: this.daftarBelum().length, c: 'lencana-merah' },
                    { k: 'izin', l: 'Izin', v: c.izin, c: 'lencana-biru' },
                    { k: 'sakit', l: 'Sakit', v: c.sakit, c: 'lencana-pink' },
                    { k: 'alpa', l: 'Alpa', v: c.alpa, c: 'lencana-abu' },
                ];
            },
            teksStat: function (it) { return it.l + ': ' + it.v; },

            // "Set semua" di kartu guru → semua sesi guru itu pada shift ini.
            setSemua: function (e, gid) {
                var self = this, val = e.target.value;
                e.target.selectedIndex = 0;
                if (!val) { return; }
                window.dispatchEvent(new CustomEvent('absen-setall', { detail: { gid: Number(gid), shift: this.shift, val: val } }));
                this.dirty = true;
                this.$nextTick(function () { self.tick++; });
            },

            // ---- simpan & kirim ----
            // Isi rows_json & kerja_json dari tampilan sebelum form dikirim.
            siapkanKirim: function () {
                var rows = [];
                document.querySelectorAll('.absen-row').forEach(function (r) {
                    var st = (r.querySelector('[data-role=status]') || {}).value || 'hadir';
                    var row = {
                        kelas_id: +r.dataset.kelas, jam_id: +r.dataset.jam, guru_id: +r.dataset.guruAsli,
                        hari_id: +r.dataset.hari, mapel_id: +r.dataset.mapel, jadwal_id: +r.dataset.jadwal, status: st,
                    };
                    if (st !== 'hadir') {
                        row.jam_masuk = (r.querySelector('[data-role=jm]') || {}).value || '';
                        row.keterangan = (r.querySelector('[data-role=ket]') || {}).value || '';
                    }
                    rows.push(row);
                });
                var panel = document.getElementById('panel-kerja');
                var kerja = panel ? Alpine.$data(panel).rows.map(function (k) {
                    return { guru_id: k.guru_id, status: k.status, shift: k.shift || 'penuh', jam_masuk: k.status !== 'hadir' ? k.jam_masuk : '', keterangan: k.keterangan };
                }) : [];
                document.getElementById('rows_json').value = JSON.stringify(rows);
                document.getElementById('kerja_json').value = JSON.stringify(kerja);
            },
            // Satu klik: simpan semua → server menyusun pesan → buka WhatsApp.
            // Tab dibuka lebih dulu (sinkron dengan klik) agar tidak diblokir browser.
            kirimWa: function () {
                var self = this;
                if (this.sending) { return; }
                this.sending = true;
                var tab = window.open('', '_blank');
                var gagal = function (pesan) {
                    if (tab) { tab.close(); }
                    beri(pesan || 'Gagal terhubung ke server.', 'galat');
                    self.sending = false;
                };
                try { this.siapkanKirim(); } catch (e) { gagal(e.message); return; }
                kirimForm(cfg.urlSave, new FormData(document.getElementById('form-absensi'))).then(function (out) {
                    if (!out || !out.ok) { gagal((out && out.message) || 'Gagal menyimpan.'); return; }
                    if (tab) {
                        tab.location.href = 'https://wa.me/?text=' + encodeURIComponent(out.pesan_wa);
                        beri('Tersimpan. WhatsApp dibuka — pilih grup lalu kirim.');
                        setTimeout(function () { window.location.reload(); }, 1500);
                    } else {
                        self.tampilkanPesan(out.pesan_wa); // tab diblokir → tampilkan tombol manual
                    }
                    self.sending = false;
                }, function () { gagal(); });
            },

            // ---- dialog pesan WhatsApp ----
            tampilkanPesan: function (teks) {
                this.waText = teks || '';
                window.Ui.bukaDialog('pesan-wa');
            },
            tautanWa: function () { return 'https://wa.me/?text=' + encodeURIComponent(this.waText); },
            salinPesan: function () {
                salin(this.waText).then(function (ok) {
                    beri(ok ? 'Pesan disalin.' : 'Gagal menyalin — salin manual dari kotak pesan.', ok ? 'sukses' : 'galat');
                });
            },

            // ---- dialog format pesan ----
            salinKode: function (e) {
                var kode = e.currentTarget.getAttribute('data-kode') || '';
                salin(kode).then(function (ok) { beri(ok ? 'Disalin: ' + kode : 'Gagal menyalin.', ok ? 'sukses' : 'galat'); });
            },
            simpanTemplate: function () {
                var self = this, fd = new FormData();
                fd.append('template', this.tpl);
                kirimForm(cfg.urlTpl, fd).then(function (out) {
                    if (!out || !out.ok) { beri((out && out.message) || 'Gagal menyimpan format.', 'galat'); return; }
                    self.tpl = out.template;
                    self.custom = out.custom;
                    beri(out.message);
                }, function () { beri('Gagal menyimpan format.', 'galat'); });
            },
            resetTemplate: function () {
                var self = this;
                window.Ui.konfirmasi({ judul: 'Kembalikan format bawaan?', pesan: 'Kembalikan format pesan ke bawaan?', ya: 'Kembalikan', jenis: 'bahaya' }).then(function (ya) {
                    if (!ya) { return; }
                    var fd = new FormData();
                    fd.append('reset', '1');
                    kirimForm(cfg.urlTpl, fd).then(function (out) {
                        self.tpl = out.template;
                        self.custom = out.custom;
                        beri(out.message);
                    }, function () { beri('Gagal mengembalikan format.', 'galat'); });
                });
            },
            pratinjau: function () {
                var self = this;
                fetch(cfg.urlPesan + '?tanggal=' + encodeURIComponent(this.tanggal) + '&shift=' + encodeURIComponent(this.shift),
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.json(); })
                    .then(function (out) {
                        if (out && out.pesan_wa) { self.tampilkanPesan(out.pesan_wa); } else { beri('Belum ada pesan — simpan absensi tanggal ini dulu.', 'info'); }
                    }, function () { beri('Gagal memuat pratinjau.', 'galat'); });
            },
        };
    });

    // Panel Kehadiran Kerja (di luar jadwal): kelola daftar guru + status lokal.
    Alpine.data('absensiKerja', function () {
        return {
            rows: [],
            guruList: [],
            pick: 0,

            init: function () {
                var el = this.$el;
                this.rows = (window.readJson(el, 'kerja', []) || []).map(function (r) {
                    return {
                        guru_id: Number(r.guru_id),
                        nama: r.nama,
                        jabatan: r.jabatan || '',
                        status: r.status || 'hadir',
                        shift: r.shift || 'penuh',
                        jam_masuk: r.jam_masuk || '',
                        keterangan: r.keterangan || '',
                        saran: !!r.saran,
                    };
                });
                this.guruList = window.readJson(el, 'guru', []) || [];
            },
            // Guru yang belum ada di daftar (cegah duplikat).
            availableGuru: function () {
                var used = {};
                this.rows.forEach(function (r) { used[r.guru_id] = true; });
                return this.guruList.filter(function (g) { return !used[g.id]; });
            },
            labelGuru: function (g) { return g.kode ? (g.nama + ' · ' + g.kode) : g.nama; },
            add: function () {
                var id = Number(this.pick);
                if (!id) { return; }
                if (this.rows.some(function (r) { return r.guru_id === id; })) { this.pick = 0; return; }
                var g = this.guruList.filter(function (x) { return x.id === id; })[0];
                if (!g) { return; }
                this.rows.push({ guru_id: id, nama: g.nama, jabatan: g.jabatan || '', status: 'hadir', shift: 'penuh', jam_masuk: '', keterangan: '', saran: false });
                this.pick = 0;
            },
            remove: function (i) { this.rows.splice(i, 1); },
        };
    });

    // Satu baris sesi absensi (_sesi_row.php).
    Alpine.data('sesiAbsen', function () {
        return {
            gid: 0,
            sh: 'pagi',
            mulai: '',
            st: 'hadir',
            jm: '',
            ket: '',
            modeKelas: false,

            init: function () {
                var d = this.$el.dataset;
                this.gid = Number(d.guruId);
                this.sh = d.shift || 'pagi';
                this.mulai = d.mulai || '';
                this.st = d.st || 'hadir';
                this.jm = d.jm || '';
                this.ket = d.ket || '';
                this.modeKelas = d.mode === 'kelas';
            },
            setDariSemua: function (e) {
                var d = e.detail || {};
                if (d.gid !== this.gid || d.shift !== this.sh) { return; }
                this.st = d.val;
                if (this.st === 'hadir') { this.jm = ''; this.ket = ''; }
            },
            tandaiDatang: function (e) {
                var d = e.detail || {};
                if (d.gid === this.gid && d.shift === this.sh && this.st === 'hadir' && this.mulai <= d.jam) {
                    this.st = 'telat';
                    this.jm = d.jam;
                }
            },
            kelasBaris: function () {
                var w = WARNA_BARIS[this.st] || 'border-l-gray-300';
                return this.modeKelas && this.isBelum(this.gid) ? w + ' bg-red-50 dark:bg-red-900/20' : w;
            },
        };
    });
});
