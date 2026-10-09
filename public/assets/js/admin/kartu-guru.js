/**
 * Tampilan "Per guru" untuk matriks SKBM dan ceklis Koreksi (campuran/mixin untuk komponen Alpine).
 *
 * Matriks 42 kelas × ±105 baris nyaman di laptop tetapi sulit di HP. Tampilan ini menyajikan DATA YANG SAMA sebagai kartu per guru
 * (dapat dibuka): tiap baris mapel + "chip" kelas (ketuk = nyala/mati, ketuk lama / klik kanan = isi angka khusus). TABEL tetap satu-satunya
 * sumber: kartu dibangun dari baris tabel, klik pada chip diteruskan ke sel tabel yang sama, dan setiap perubahan sel (gayaSel) menyegarkan
 * chip-nya. Angka tetap dari server — kartu tidak menghitung apa pun sendiri.
 *
 * Pemakaian di komponen (skbm-grid.js / koreksi-grid.js):
 *   Object.assign(obj, window.KartuGuru({ nama: 'skbm', kunciGuru: 'guru' }));   // kunciGuru = nama atribut data-* baris guru
 *   - init(): this.mulaiTampilan();
 *   - gayaSel(td, …): this.segarkanChip(td);
 *   - pakaiRingkas(j): setTeks('ct-' + id, …) & setTeks('cm-' + id, …) (total guru / baris di kartu)
 *   - saring(): this.saringKartu(q);
 * Konfigurasi (data-config pada akar): kelas:[{id,label,nama}], grup:[{judul,dari,sampai}] (indeks ke kelas).
 * Atribut yang dibaca dari baris tabel: data-<kunciGuru>, data-judul, data-no, data-kode, data-mapel; tombol nama mapel
 * (button[data-id][data-nama]); sisipan [data-aksi-guru] dan [data-aksi-mapel] disalin ke kartu.
 */
(function () {
    'use strict';

    // Kelas Tailwind chip — berkas ini dipindai Tailwind (lihat tailwind.config.js), jadi kelas di sini ikut terbentuk.
    var KELAS = {
        dasar: 'inline-flex min-w-[3.4rem] flex-col items-center justify-center rounded-lg border px-2 py-1.5 leading-tight transition active:scale-95',
        mati: 'border-slate-200 bg-slate-50 text-slate-400',
        aktif: 'border-sky-500 bg-sky-500 text-white',
        tanpaJp: 'border-amber-400 bg-amber-300 text-amber-900',
        khusus: 'ring-2 ring-amber-400 ring-offset-1'
    };

    function el(tag, kelas, teks) {
        var e = document.createElement(tag);
        if (kelas) { e.className = kelas; }
        if (teks !== undefined) { e.textContent = teks; }
        return e;
    }

    window.KartuGuru = function (opsi) {
        return {
            tampilan: 'matriks',
            kartuSiap: false,

            /** Pilihan terakhir pengguna (disimpan di peramban); bawaan: layar sempit → per guru, layar lebar → matriks. */
            mulaiTampilan: function () {
                var simpan = null;
                try { simpan = window.localStorage.getItem('tampilan_' + opsi.nama); } catch (e) { simpan = null; }
                var sempit = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
                this.pilihTampilan(simpan === 'guru' || simpan === 'matriks' ? simpan : (sempit ? 'guru' : 'matriks'), true);
            },

            pilihTampilan: function (t, tanpaSimpan) {
                this.tampilan = t;
                if (t === 'guru') { this.bangunKartu(); }
                if (!tanpaSimpan) {
                    try { window.localStorage.setItem('tampilan_' + opsi.nama, t); } catch (e) { /* peramban menolak penyimpanan: abaikan */ }
                }
            },

            /** Bangun kartu guru (hanya judulnya; isi kartu dibangun saat dibuka agar ringan di HP). */
            bangunKartu: function () {
                var self = this;
                var wadah = this.$root.querySelector('#kartu-guru');
                if (!wadah || this.kartuSiap) { return; }
                this.kartuSiap = true;
                var per = {};
                var urut = [];
                this.$root.querySelectorAll('tbody tr[data-' + opsi.kunciGuru + ']').forEach(function (tr) {
                    var g = tr.dataset[opsi.kunciGuru];
                    if (!per[g]) {
                        per[g] = { id: g, judul: tr.dataset.judul || '', no: tr.dataset.no || '', baris: [] };
                        urut.push(g);
                    }
                    per[g].baris.push(tr);
                });
                var frag = document.createDocumentFragment();
                urut.forEach(function (g) { frag.appendChild(self.kartuGuru(per[g])); });
                wadah.appendChild(frag);
                // 'toggle' tidak menggelembung → dipasang di fase tangkap pada wadah
                wadah.addEventListener('toggle', function (e) {
                    var d = e.target;
                    if (d.tagName === 'DETAILS' && d.open && !d.dataset.isi) {
                        d.dataset.isi = '1';
                        self.isiKartu(d, per[d.dataset.g]);
                    }
                }, true);
                wadah.addEventListener('click', function (e) {
                    var c = e.target.closest('[data-chip]');
                    if (c) { self.klikChip(c, false); }
                });
                wadah.addEventListener('contextmenu', function (e) {
                    var c = e.target.closest('[data-chip]');
                    if (c) { e.preventDefault(); self.klikChip(c, true); }
                });
                if (this.cari) { this.saringKartu(String(this.cari).trim().toLowerCase()); }
            },

            teksDari: function (id) {
                var e = document.getElementById(id);
                return e ? e.textContent.trim() : '';
            },

            kartuGuru: function (g) {
                var d = el('details', 'rounded-xl border border-slate-200 bg-white');
                d.dataset.g = g.id;
                d.dataset.kartuNama = g.judul.toLowerCase();
                var s = el('summary', 'flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3');
                var kiri = el('span', 'flex min-w-0 items-baseline gap-2');
                kiri.appendChild(el('span', 'text-xs tabular-nums text-slate-400', g.no));
                kiri.appendChild(el('span', 'truncate font-semibold text-slate-800', g.judul));
                kiri.appendChild(el('span', 'shrink-0 text-xs text-slate-400', g.baris.length + ' mapel'));
                var kanan = el('span', 'flex shrink-0 items-center gap-2');
                var tot = el('span', 'text-sm font-extrabold tabular-nums text-slate-800', this.teksDari('gt-' + g.id));
                tot.id = 'ct-' + g.id;
                kanan.appendChild(tot);
                kanan.appendChild(el('span', 'text-slate-400', '▾'));
                s.appendChild(kiri);
                s.appendChild(kanan);
                d.appendChild(s);

                return d;
            },

            /** Isi kartu: tiap baris mapel + chip kelas per kelompok. */
            isiKartu: function (d, g) {
                var self = this;
                var isi = el('div', 'space-y-4 border-t border-slate-100 px-4 py-3');
                g.baris.forEach(function (tr) {
                    var blok = el('div', 'space-y-2');
                    var kepala = el('div', 'flex items-center justify-between gap-2');
                    var kiri = el('div', 'flex min-w-0 items-baseline gap-2');
                    kiri.appendChild(el('span', 'shrink-0 text-xs font-semibold text-slate-400', tr.dataset.kode || ''));
                    var m = tr.querySelector('button[data-id][data-nama]');
                    if (m) {
                        var tombol = el('button', 'min-w-0 truncate text-left text-sm font-semibold text-slate-700 hover:text-brand-700 hover:underline', m.dataset.nama);
                        tombol.type = 'button';
                        tombol.dataset.id = m.dataset.id;
                        tombol.dataset.nama = m.dataset.nama;
                        tombol.title = 'Ketuk untuk mengganti nama mapel';
                        if (m.disabled) { tombol.disabled = true; }
                        tombol.addEventListener('click', function () { self.ubahNama(tombol); });
                        kiri.appendChild(tombol);
                    }
                    var kanan = el('div', 'flex shrink-0 items-center gap-2');
                    var tm = el('span', 'text-xs font-semibold tabular-nums text-slate-500', self.teksDari('mt-' + tr.dataset.mapel));
                    tm.id = 'cm-' + tr.dataset.mapel;
                    kanan.appendChild(tm);
                    var hapus = tr.querySelector('[data-aksi-mapel]');
                    if (hapus) { kanan.appendChild(hapus.cloneNode(true)); }
                    kepala.appendChild(kiri);
                    kepala.appendChild(kanan);
                    blok.appendChild(kepala);

                    (self.cfg.grup || []).forEach(function (gr) {
                        blok.appendChild(el('p', 'text-[11px] font-bold uppercase tracking-wide text-slate-400', gr.judul));
                        var baris = el('div', 'flex flex-wrap gap-1.5');
                        for (var i = gr.dari; i <= gr.sampai; i++) {
                            var k = self.cfg.kelas[i];
                            var td = tr.querySelector('td[data-k="' + k.id + '"]');
                            if (!td) { continue; }
                            var chip = el('button', '');
                            chip.type = 'button';
                            chip.dataset.chip = tr.dataset.mapel + '-' + k.id;
                            chip.dataset.m = tr.dataset.mapel;
                            chip.dataset.k = String(k.id);
                            chip.title = k.nama;
                            chip.appendChild(el('span', 'text-[10px] opacity-80', k.label));
                            chip.appendChild(el('span', 'text-sm font-bold tabular-nums', ''));
                            self.gayaChip(chip, td);
                            baris.appendChild(chip);
                        }
                        blok.appendChild(baris);
                    });
                    isi.appendChild(blok);
                });
                var aksi = g.baris[0].querySelector('[data-aksi-guru]');
                if (aksi) {
                    var pie = el('div', 'border-t border-slate-100 pt-3');
                    pie.appendChild(aksi.cloneNode(true));
                    isi.appendChild(pie);
                }
                d.appendChild(isi);
            },

            gayaChip: function (chip, td) {
                var aktif = td.dataset.aktif === '1';
                var tanpaJp = aktif && td.dataset.jp === '';       // hanya SKBM: mengajar tetapi JP belum diisi
                var khusus = aktif && td.dataset.khusus === '1';   // hanya Koreksi: angka lembar diketik
                chip.className = KELAS.dasar + ' ' + (!aktif ? KELAS.mati : (tanpaJp ? KELAS.tanpaJp : KELAS.aktif)) + (khusus ? ' ' + KELAS.khusus : '');
                chip.lastChild.textContent = aktif ? td.textContent.trim() : '–';
            },

            /** Sel tabel berubah → chip pasangannya (bila kartu sudah dibangun & dibuka) ikut berubah. */
            segarkanChip: function (td) {
                var tr = td.closest('tr');
                if (!tr || !this.kartuSiap) { return; }
                var chip = this.$root.querySelector('[data-chip="' + tr.dataset.mapel + '-' + td.dataset.k + '"]');
                if (chip) { this.gayaChip(chip, td); }
            },

            /** Semua chip satu kelas (mis. peserta kelas berubah → angka banyak sel ikut berubah). */
            segarkanChipKelas: function (kelasId) {
                var self = this;
                if (!this.kartuSiap) { return; }
                this.$root.querySelectorAll('[data-chip][data-k="' + kelasId + '"]').forEach(function (chip) {
                    var td = self.$root.querySelector('tbody tr[data-mapel="' + chip.dataset.m + '"] td[data-k="' + kelasId + '"]');
                    if (td) { self.gayaChip(chip, td); }
                });
            },

            klikChip: function (chip, kanan) {
                var td = this.$root.querySelector('tbody tr[data-mapel="' + chip.dataset.m + '"] td[data-k="' + chip.dataset.k + '"]');
                if (!td) { return; }
                var e = { target: td, preventDefault: function () { /* tak ada aksi bawaan untuk ditahan */ } };
                if (kanan) { this.klikKanan(e); } else { this.klikSel(e); }
            },

            /** Saring kartu menurut nama guru; bila tepat satu yang cocok, kartunya dibuka. */
            saringKartu: function (q) {
                if (!this.kartuSiap) { return; }
                var cocok = [];
                this.$root.querySelectorAll('#kartu-guru > details').forEach(function (d) {
                    var tampil = q === '' || d.dataset.kartuNama.indexOf(q) >= 0;
                    d.classList.toggle('hidden', !tampil);
                    if (tampil) { cocok.push(d); }
                });
                if (q !== '' && cocok.length === 1) { cocok[0].open = true; }
            }
        };
    };
})();
