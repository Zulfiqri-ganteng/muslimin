/**
 * Catat Keterlambatan (menu Guru Piket) — perluasan masterList (dialog Ubah) + kotak cari siswa di formulir catat.
 *   - Cari: ketik ≥ 2 huruf → GET data-cari?q=&tanggal= (hasil dari server, siswa aktif + saran siswa BELUM TERDAFTAR yang pernah
 *     dicatat manual). Panah atas/bawah + Enter memilih; siswa yang sudah dicatat pada tanggal itu tampil redup dan tidak bisa dipilih.
 *   - "Siswa belum terdaftar? Tulis manual": isian nama (awal = ketikan cari), kelas, HP orang tua; memilih saran manual mengisinya
 *     dengan ejaan yang sama. Isian manual nonaktif saat tidak dipakai (tidak terkirim).
 *   - "Pilih dari kelas" (tanpa mengetik): tombol kelas → GET data-cari?kelas_id=&tanggal= (semua siswa aktif kelas itu + siswa belum
 *     terdaftar kelas itu) → tekan nama = sama dengan memilih hasil cari. "Namanya tidak ada?" → isian manual dengan kelas terpilih.
 *   - Memilih siswa saat mencatat HARI INI mengisi jam datang dengan jam sekarang, kecuali jamnya sudah diubah tangan.
 *   - Kirim tanpa siswa terpilih / isian manual belum lengkap dicegah (pesan di bawah kotak cari).
 * Elemen akar: data-cari, data-tanggal, data-hari-ini, data-siswa-awal & data-manual-awal (isian sebelum galat simpan), data-nama-min.
 * Tombol "Isi no. ortu" (data-isi-hp="url" data-isi-nama [data-isi-manual]): dialog kit "tl-isi-hp" dibuka oleh ui.js
 * (data-buka-dialog); di sini alamat formulirnya, nama siswa, dan keterangan simpan diisi. Tombol WA "Kabari ortu" ditangani
 * assets/js/admin/pkl-kabar.js (data-kabar-*). Tombol "Tautkan" (data-tautkan="url" data-tautkan-nama data-tautkan-q) → komponen
 * "tautkanSiswa" di dialog "tl-tautkan".
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
        var manual = b.getAttribute('data-isi-manual') === '1';
        var ketData = document.getElementById('tl-hp-ket-data');
        var ketManual = document.getElementById('tl-hp-ket-manual');
        if (ketData) { ketData.hidden = manual; }
        if (ketManual) { ketManual.hidden = !manual; }
    });
})();

document.addEventListener('alpine:init', function () {
    'use strict';

    function duaAngka(n) { return (n < 10 ? '0' : '') + n; }
    function ambil(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.ok ? r.json() : null; });
    }
    /** "X TKJ 1 · NIS 123 · sudah dicatat (jam 07:20)" */
    function keterangan(s, denganManual) {
        var bagian = [s.kelas || 'tanpa kelas'];
        if (s.nis) { bagian.push('NIS ' + s.nis); }
        if (denganManual && s.manual) { bagian.push('belum terdaftar, dicatat manual ' + (s.jumlah || 1) + '×'); }
        if (s.tercatat) { bagian.push('sudah dicatat (' + s.tercatat + ')'); }
        return bagian.join(' · ');
    }

    Alpine.data('keterlambatanPage', function () {
        var akar = null; // di metode Alpine versi CSP this.$el = elemen pemicu
        var urlCari = '';
        var tanggal = '';
        var hariIni = false;
        var jamDiubah = false;
        var namaMin = 3;
        var nomor = 0; // urutan permintaan cari: jawaban lama yang datang belakangan diabaikan
        var nomorKelas = 0; // sama, untuk daftar siswa per kelas

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
            manual: false,
            mNama: '',
            mKelas: '',
            mHp: '',
            // "Pilih dari kelas": kelas yang sedang dibuka {id, nama} + daftar siswanya
            kelasAktif: null,
            daftarKelas: [],
            memuatKelas: false,
            pesanKelas: '',

            onInit: function () {
                akar = this.$el;
                urlCari = akar.getAttribute('data-cari') || '';
                tanggal = akar.getAttribute('data-tanggal') || '';
                hariIni = akar.getAttribute('data-hari-ini') === '1';
                namaMin = parseInt(akar.getAttribute('data-nama-min') || '3', 10) || 3;
                var awal = window.readJson(akar, 'siswa-awal', null);
                if (awal && awal.id) { this.siswa = awal; }
                var m = window.readJson(akar, 'manual-awal', null);
                if (m) { this.isiManual(m.nama || '', m.kelas_id || '', m.hp_ortu || ''); }
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
                ambil(urlCari + '?q=' + encodeURIComponent(q) + '&tanggal=' + encodeURIComponent(tanggal))
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
                        self.pesanCari = self.hasil.length ? '' : 'Tidak ada siswa aktif dengan nama/NIS itu. Belum terdaftar? Tekan "Tulis manual".';
                        self.aktif = self.hasil.findIndex(function (s) { return !s.tercatat; });
                    })
                    .catch(function () {
                        if (ke === nomor) { self.pesanCari = 'Pencarian gagal. Periksa sambungan internet.'; }
                    });
            },
            kunci: function (s) { return s.manual ? 'm' + s.nama + '|' + s.kelas_id : 'i' + s.id; },
            sudah: function (s) { return !!(s && s.tercatat); },
            ketHasil: function (s) { return keterangan(s, true); },
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
            isiJamSekarang: function () {
                if (hariIni && !jamDiubah && this.$refs.jam) {
                    var d = new Date();
                    this.$refs.jam.value = duaAngka(d.getHours()) + ':' + duaAngka(d.getMinutes());
                }
            },
            pilih: function (s) {
                if (!s || this.sudah(s)) { return; }
                this.q = '';
                this.hasil = [];
                this.terbuka = false;
                this.pesanCari = '';
                this.isiJamSekarang();
                if (s.manual) {
                    // Saran "pernah dicatat manual": isian manual dengan ejaan, kelas, dan nomor yang sama.
                    this.isiManual(s.nama, s.kelas_id ? String(s.kelas_id) : '', s.hp_ortu || '');
                    fokus('#tl-sanksi');
                    return;
                }
                this.siswa = s;
                fokus('#tl-sanksi');
            },
            gantiSiswa: function () {
                this.siswa = null;
                fokus('#tl-cari');
            },
            tandaiJam: function () { jamDiubah = true; },

            // ----- pilih dari kelas: tombol kelas [data-kelas-id][data-kelas-nama] → GET data-cari?kelas_id=&tanggal= -----
            bukaKelas: function () {
                var self = this;
                var id = parseInt(this.$el.getAttribute('data-kelas-id') || '0', 10);
                if (!id) { return; }
                var ke = ++nomorKelas;
                this.kelasAktif = { id: id, nama: this.$el.getAttribute('data-kelas-nama') || '' };
                this.daftarKelas = [];
                this.memuatKelas = true;
                this.pesanKelas = '';
                ambil(urlCari + '?kelas_id=' + id + '&tanggal=' + encodeURIComponent(tanggal))
                    .then(function (j) {
                        if (ke !== nomorKelas) { return; }
                        self.memuatKelas = false;
                        if (!j || !j.ok) {
                            self.pesanKelas = 'Daftar siswa gagal dimuat. Muat ulang halaman (mungkin sesi masuk sudah habis).';
                            return;
                        }
                        self.daftarKelas = Array.isArray(j.hasil) ? j.hasil : [];
                        fokus('[aria-label="Siswa di kelas terpilih"] button:not([disabled])');
                    })
                    .catch(function () {
                        if (ke === nomorKelas) {
                            self.memuatKelas = false;
                            self.pesanKelas = 'Daftar siswa gagal dimuat. Periksa sambungan internet.';
                        }
                    });
            },
            tutupKelas: function () {
                var id = this.kelasAktif ? this.kelasAktif.id : 0;
                nomorKelas++;
                this.kelasAktif = null;
                this.daftarKelas = [];
                this.memuatKelas = false;
                this.pesanKelas = '';
                if (id) { fokus('[data-kelas-id="' + id + '"]'); }
            },
            judulKelas: function () {
                if (!this.kelasAktif) { return ''; }
                if (this.memuatKelas) { return 'Memuat siswa ' + this.kelasAktif.nama + '…'; }
                if (this.pesanKelas) { return this.pesanKelas; }
                var n = this.daftarKelas.filter(function (s) { return !s.manual; }).length;
                return n ? this.kelasAktif.nama + ' · ' + n + ' siswa — tekan nama siswa yang terlambat.'
                    : 'Belum ada siswa aktif di ' + this.kelasAktif.nama + '.';
            },
            /** "NIS 123 · sudah dicatat (jam 07:20)" — kelas tidak diulang (sudah jelas dari judul). */
            ketDiKelas: function (s) {
                var bagian = [];
                if (s.nis) { bagian.push('NIS ' + s.nis); }
                if (s.manual) { bagian.push('belum terdaftar, dicatat manual ' + (s.jumlah || 1) + '×'); }
                if (s.tercatat) { bagian.push('sudah dicatat (' + s.tercatat + ')'); }
                return bagian.join(' · ');
            },
            /** Nama anak tidak ada di daftar kelas → isian manual dengan kelas itu sudah terpilih. */
            manualDiKelas: function () {
                if (!this.kelasAktif) { return; }
                this.isiManual('', String(this.kelasAktif.id), '');
                this.isiJamSekarang();
                fokus('#tl-m-nama');
            },

            // ----- siswa belum terdaftar (manual) -----
            mencari: function () { return !this.siswa && !this.manual; },
            isiManual: function (nama, kelasId, hp) {
                this.siswa = null;
                this.manual = true;
                this.mNama = nama;
                this.mKelas = kelasId;
                this.mHp = hp;
            },
            mulaiManual: function () {
                var q = String(this.q || '').trim();
                this.hasil = [];
                this.terbuka = false;
                this.pesanCari = '';
                // Ketikan cari dipakai sebagai nama awal, kecuali berupa NIS (angka).
                this.isiManual(/^\d+$/.test(q) ? '' : q.toUpperCase(), '', '');
                this.isiJamSekarang();
                fokus(this.mNama ? '#tl-m-kelas' : '#tl-m-nama');
            },
            batalManual: function () {
                this.manual = false;
                this.q = this.mNama;
                fokus('#tl-cari');
                this.cari();
            },
            nilaiManual: function () { return this.manual ? '1' : ''; },

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
                if (this.manual) {
                    if (String(this.mNama || '').trim().length < namaMin) {
                        e.preventDefault();
                        fokus('#tl-m-nama');
                    } else if (!this.mKelas) {
                        e.preventDefault();
                        fokus('#tl-m-kelas');
                    }
                    return;
                }
                if (!this.siswa) {
                    e.preventDefault();
                    this.pesanCari = 'Pilih dulu siswa yang terlambat: ketik namanya, atau tekan kelasnya lalu namanya (bila belum terdaftar, tekan "Tulis manual").';
                    fokus('#tl-cari');
                }
            },
        });
    });

    /**
     * Dialog "Tautkan ke Data Siswa": cari siswa terdaftar (data-cari?manual=0), pilih, kirim POST {id}/tautkan.
     * Tombol pemicu: [data-tautkan="url"] + data-tautkan-nama (teks catatan) + data-tautkan-q (nama untuk pencarian awal).
     */
    Alpine.data('tautkanSiswa', function () {
        var akar = null;
        var urlCari = '';
        var tanggal = '';
        var nomor = 0;

        return {
            nama: '',
            q: '',
            hasil: [],
            pesan: '',
            pilihan: null,

            init: function () {
                var self = this;
                akar = this.$el;
                urlCari = akar.getAttribute('data-cari') || '';
                tanggal = akar.getAttribute('data-tanggal') || '';
                document.addEventListener('click', function (e) {
                    var b = e.target && e.target.closest ? e.target.closest('[data-tautkan]') : null;
                    if (b) { self.buka(b); }
                });
            },
            buka: function (b) {
                akar.setAttribute('action', b.getAttribute('data-tautkan'));
                var semua = akar.querySelector('input[name="semua"]');
                if (semua) { semua.checked = true; }
                this.nama = b.getAttribute('data-tautkan-nama') || '';
                this.q = b.getAttribute('data-tautkan-q') || '';
                this.hasil = [];
                this.pilihan = null;
                this.pesan = '';
                this.cari();
                setTimeout(function () {
                    var el = akar.querySelector('#tl-tautkan-cari');
                    if (el) { el.focus(); }
                }, 80);
            },
            cari: function () {
                var self = this;
                var q = String(this.q || '').trim();
                if (q.length < 2) {
                    this.hasil = [];
                    this.pesan = q === '' ? '' : 'Ketik minimal 2 huruf.';
                    return;
                }
                var ke = ++nomor;
                this.pesan = 'Mencari…';
                ambil(urlCari + '?manual=0&q=' + encodeURIComponent(q) + '&tanggal=' + encodeURIComponent(tanggal))
                    .then(function (j) {
                        if (ke !== nomor) { return; }
                        self.hasil = j && j.ok && Array.isArray(j.hasil) ? j.hasil : [];
                        self.pesan = !j || !j.ok ? 'Pencarian gagal. Muat ulang halaman.'
                            : (self.hasil.length ? '' : 'Belum ada di Data Siswa. Masukkan dulu siswanya lewat menu Data Siswa, lalu tautkan.');
                    })
                    .catch(function () {
                        if (ke === nomor) { self.pesan = 'Pencarian gagal. Periksa sambungan internet.'; }
                    });
            },
            ket: function (s) { return keterangan(s, false); },
            pilih: function (s) { this.pilihan = s; },
            ganti: function () {
                this.pilihan = null;
                setTimeout(function () {
                    var el = akar.querySelector('#tl-tautkan-cari');
                    if (el) { el.focus(); }
                }, 30);
            },
            idPilihan: function () { return this.pilihan ? this.pilihan.id : ''; },
            namaPilihan: function () { return this.pilihan ? this.pilihan.nama : ''; },
            ketPilihan: function () { return this.pilihan ? keterangan(this.pilihan, false) : ''; },
            kirim: function (e) {
                if (!this.pilihan) {
                    e.preventDefault();
                    this.pesan = 'Pilih dulu siswanya dari hasil pencarian Data Siswa.';
                }
            },
        };
    });
});
