/**
 * Tab Honor (Ujian) — grid isian honor dengan simpan otomatis per sel (Alpine).
 *
 * Tiap sel yang berubah dikirim ke server (POST JSON); server memeriksa, menyimpan, lalu MENGEMBALIKAN angka resmi
 * (rupiah sel, total baris, total kolom, total keseluruhan). Layar hanya menampilkan angka dari server — tidak ada
 * hitungan sendiri di sini, jadi tak mungkin berbeda dari Excel/PDF. Pengiriman dibuat antre satu per satu karena
 * token CSRF berganti setiap POST (token baru dibawa balasan).
 *
 * Konfigurasi lewat data-config pada elemen akar (lihat views/admin/ujian/tab_honor.php).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    function ribuan(n) {
        return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function polos(s) {
        return String(s).replace(/[.\s]/g, '');
    }
    function setTeks(id, teks) {
        var e = document.getElementById(id);
        if (e) { e.textContent = teks; }
    }

    Alpine.data('honorGrid', function () {
        return {
            cfg: {},
            panel: '',
            menuCetak: false,
            pesan: '',
            galat: false,
            cari: '',
            tampilCount: 0,
            antre: Promise.resolve(),
            tertunda: 0,
            timer: null,

            init: function () {
                var self = this;
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                this.tampilCount = this.$root.querySelectorAll('[data-baris-row]').length;
                // Pencarian lewat $watch: tidak bergantung pada urutan x-model / @input.
                this.$watch('cari', function () { self.saring(); });
                // Jangan tinggalkan halaman diam-diam bila masih ada isian yang belum terkirim.
                window.addEventListener('beforeunload', function (e) {
                    if (self.tertunda > 0) { e.preventDefault(); e.returnValue = ''; }
                });
                if (window.location.hash === '#penerima') { this.panel = 'penerima'; }
            },

            // ------------------------------------------------------------------ pesan
            tampil: function (teks, galat) {
                var self = this;
                this.pesan = teks;
                this.galat = !!galat;
                clearTimeout(this.timer);
                this.timer = setTimeout(function () { self.pesan = ''; }, galat ? 6000 : 1800);
            },

            // ------------------------------------------------------------------ kirim (antre, token CSRF bergilir)
            kirim: function (url, data) {
                var self = this;
                this.tertunda++;
                var tugas = function () {
                    var token = document.getElementById('honor-csrf');
                    var body = new URLSearchParams();
                    body.append(self.cfg.csrfName, token ? token.value : '');
                    body.append('periode_id', self.cfg.periodeId);
                    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
                    return fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true, // tetap terkirim bila halaman keburu ditinggalkan
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
                        return { ok: false, pesan: 'Koneksi terputus. Isian belum tersimpan — periksa jaringan lalu ulangi.' };
                    }).then(function (j) { self.tertunda--; return j; });
                };
                var hasil = this.antre.then(tugas, tugas);
                this.antre = hasil.catch(function () { /* antre tetap jalan */ });
                return hasil;
            },

            // ------------------------------------------------------------------ simpan satu sel
            simpanSel: function (el) {
                var self = this;
                var awal = el.dataset.awal || '';
                var nilai = el.value.trim();
                if (polos(nilai) === polos(awal)) { el.value = awal; return; }
                if (!/^[0-9.\s]*$/.test(nilai)) {
                    el.value = awal;
                    this.kedip(el, false);
                    this.tampil('Isi dengan angka saja (tanpa huruf, koma, atau tanda minus).', true);
                    return;
                }
                el.classList.add('opacity-50');
                this.kirim(this.cfg.urlNilai, { baris: el.dataset.baris, komponen: el.dataset.dk, nilai: nilai }).then(function (j) {
                    el.classList.remove('opacity-50');
                    if (!j.ok) {
                        el.value = awal;
                        self.kedip(el, false);
                        self.tampil(j.pesan || 'Gagal menyimpan.', true);
                        return;
                    }
                    var tetap = el.dataset.tipe === 'tetap';
                    el.value = tetap ? ribuan(j.nilai) : String(j.nilai);
                    el.dataset.awal = el.value;
                    var b = el.dataset.baris, k = el.dataset.dk;
                    setTeks('rp-' + b + '-' + k, 'Rp ' + ribuan(j.rupiah));
                    setTeks('tb-' + b, ribuan(j.total_baris));
                    setTeks('tk-' + k, ribuan(j.total_komponen));
                    setTeks('tj-' + k, ribuan(j.total_jumlah));
                    setTeks('tot-semua', ribuan(j.total));
                    setTeks('ringkas-total', ribuan(j.total));
                    var st = document.getElementById('st-' + b + '-' + k);
                    if (st) {
                        if (j.otomatis === null || j.otomatis === undefined) {
                            st.textContent = '';
                        } else {
                            var sama = Number(j.otomatis) === Number(j.nilai);
                            st.textContent = sama ? 'otomatis' : 'diubah';
                            st.className = 'rounded px-1 text-[10px] font-semibold ' + (sama ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700');
                        }
                    }
                    self.kedip(el, true);
                });
            },

            // ------------------------------------------------------------------ label jabatan
            simpanJabatan: function (el) {
                var self = this;
                var awal = el.dataset.awal || '';
                if (el.value.trim() === awal) { return; }
                el.classList.add('opacity-50');
                this.kirim(this.cfg.urlJabatan.replace('__ID__', el.dataset.baris), { jabatan: el.value }).then(function (j) {
                    el.classList.remove('opacity-50');
                    if (!j.ok) {
                        el.value = awal;
                        self.kedip(el, false);
                        self.tampil(j.pesan || 'Gagal menyimpan.', true);
                        return;
                    }
                    el.value = j.jabatan || '';
                    el.dataset.awal = el.value;
                    self.kedip(el, true);
                });
            },

            kedip: function (el, baik) {
                var kelas = baik ? ['ring-2', 'ring-emerald-300'] : ['ring-2', 'ring-red-400'];
                kelas.forEach(function (c) { el.classList.add(c); });
                setTimeout(function () { kelas.forEach(function (c) { el.classList.remove(c); }); }, baik ? 700 : 1800);
            },

            // ------------------------------------------------------------------ pakai petunjuk jadwal pengawas
            pakai: function (btn) {
                var sel = btn.closest('div.min-w-0');
                var input = sel ? sel.querySelector('input[data-nilai]') : null;
                if (!input) { return; }
                input.value = btn.dataset.jumlah;
                this.simpanSel(input);
                btn.style.display = 'none';
            },

            // ------------------------------------------------------------------ Enter = turun satu baris (seperti Excel)
            turun: function (el) {
                var semua = Array.prototype.filter.call(
                    this.$root.querySelectorAll('input[data-nilai][data-dk="' + el.dataset.dk + '"]'),
                    function (i) { return i.offsetParent !== null; }
                );
                var n = semua[semua.indexOf(el) + 1];
                if (n) { n.focus(); n.select(); } else { el.blur(); }
            },

            // ------------------------------------------------------------------ cari nama
            saring: function () {
                var q = this.cari.trim().toLowerCase();
                var tampil = 0;
                this.$root.querySelectorAll('[data-baris-row]').forEach(function (row) {
                    var cocok = q === '' || (row.dataset.nama || '').indexOf(q) !== -1;
                    row.style.display = cocok ? '' : 'none';
                    if (cocok) { tampil++; }
                });
                this.tampilCount = tampil;
            }
        };
    });
});
