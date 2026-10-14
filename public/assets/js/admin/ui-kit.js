/**
 * Halaman ui-kit (rombak tampilan F1, khusus laptop) — komponen Alpine `uiKit`: pemilih tema contoh + aksi contoh.
 *
 * Uji-diri (?uji=1 → <body data-uji="1">): setelah Alpine versi CSP berjalan, tiap komponen kit (ui.js) dicoba lewat DOM —
 * format, dropdown, tab, dialog, laci, toast, peringatan, konfirmasi (tombol, formulir, dari JS). Kegagalan dicatat lewat
 * console.error (dibaca tools/uji-peramban.mjs sebagai galat); window.__ujiSibuk = true selama berjalan (alat menunggu),
 * hasil akhir di window.__ujiHasil ("12/12 lulus").
 */
(function () {
    'use strict';

    var TEMA = ['terang', 'gelap', 'lilac'];
    var modeUji = document.body && document.body.getAttribute('data-uji') === '1';
    if (modeUji) {
        window.__ujiSibuk = true;
        // Penjaga: bila Alpine/kit tidak jalan sama sekali, uji tak pernah selesai → tetap terlapor sebagai galat.
        setTimeout(function () {
            if (window.__ujiSibuk) {
                window.__ujiSibuk = false;
                console.error('ui-kit uji: tidak selesai dalam 12 detik (Alpine / ui.js tidak berjalan?)');
            }
        }, 12000);
    }

    // ------------------------------------------------------------------ uji-diri
    var tunggu = function (ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); };
    var sampai = async function (syarat, batas) {
        var akhir = Date.now() + (batas || 1500);
        while (Date.now() < akhir) {
            if (syarat()) { return true; }
            await tunggu(25);
        }
        return syarat();
    };
    var tampak = function (el) { return el !== null && el.getClientRects().length > 0; };
    var cari = function (sel) { return document.querySelector(sel); };
    var tekan = function (el, tombol) {
        el.dispatchEvent(new KeyboardEvent('keydown', { key: tombol, bubbles: true, cancelable: true }));
    };
    var dialogKonfirmasi = function () { return cari('#ui-konfirmasi'); };

    var daftarUji = [
        ['format (magic $rupiah, $tglIndo, $periodeTeks, …)', async function () {
            var salah = [];
            document.querySelectorAll('[data-harap]').forEach(function (el) {
                if (el.textContent !== el.getAttribute('data-harap')) {
                    salah.push('"' + el.getAttribute('data-harap') + '" ≠ "' + el.textContent + '"');
                }
            });
            if (document.querySelectorAll('[data-harap]').length < 10) { salah.push('baris contoh kurang dari 10'); }
            return salah.join('; ');
        }],
        ['dropdown buka (klik) & tutup (Esc)', async function () {
            var pemicu = cari('[data-uji="dropdown"]');
            var menu = cari('[data-uji="dropdown-menu"]');
            pemicu.click();
            if (!await sampai(function () { return tampak(menu); })) { return 'menu tidak terbuka'; }
            if (pemicu.getAttribute('aria-expanded') !== 'true') { return 'aria-expanded bukan true'; }
            tekan(pemicu, 'Escape');
            if (!await sampai(function () { return !tampak(menu); })) { return 'menu tidak tertutup dengan Esc'; }
            return document.activeElement === pemicu ? '' : 'fokus tidak kembali ke tombol';
        }],
        ['tab pindah (klik)', async function () {
            cari('[data-uji="tab-2"]').click();
            var ok = await sampai(function () { return tampak(cari('[data-uji="panel-2"]')) && !tampak(cari('[data-uji="panel-1"]')); });
            var pilih = cari('[data-uji="tab-2"]').getAttribute('aria-selected') === 'true';
            cari('[data-uji="tab-1"]').click();
            return ok && pilih ? '' : 'panel/aria-selected tidak berganti';
        }],
        ['dialog buka (Ui.bukaDialog) & tutup (Esc)', async function () {
            var akar = cari('[data-nama="contoh-form"]');
            Ui.bukaDialog('contoh-form');
            if (!await sampai(function () { return tampak(akar); })) { return 'dialog tidak terbuka'; }
            if (!await sampai(function () { return akar.contains(document.activeElement); })) { return 'fokus tidak masuk ke dialog'; }
            if (!document.documentElement.classList.contains('overflow-hidden')) { return 'halaman belakang tidak dikunci'; }
            tekan(document.activeElement, 'Escape');
            if (!await sampai(function () { return !tampak(akar); })) { return 'dialog tidak tertutup dengan Esc'; }
            return document.documentElement.classList.contains('overflow-hidden') ? 'kunci gulir tidak dilepas' : '';
        }],
        ['laci buka (data-buka-dialog) & tutup (tombol ✕)', async function () {
            var akar = cari('[data-nama="contoh-laci"]');
            cari('[data-uji="buka-laci"]').focus();   // klik lewat skrip tidak memindah fokus seperti klik mouse
            cari('[data-uji="buka-laci"]').click();
            if (!await sampai(function () { return tampak(akar); })) { return 'laci tidak terbuka'; }
            akar.querySelector('[data-tutup]').click();
            if (!await sampai(function () { return !tampak(akar); })) { return 'laci tidak tertutup'; }
            return document.activeElement === cari('[data-uji="buka-laci"]') ? '' : 'fokus tidak kembali ke tombol pembuka';
        }],
        ['toast tampil (Ui.toast)', async function () {
            Ui.toast('Uji toast kit', 'sukses');
            var ok = await sampai(function () {
                return Array.prototype.some.call(document.querySelectorAll('.toast-wadah .toast'), function (t) {
                    return tampak(t) && t.textContent.indexOf('Uji toast kit') !== -1 && t.classList.contains('toast-sukses');
                });
            });
            return ok ? '' : 'toast tidak muncul';
        }],
        ['peringatan ditutup (tombol ✕)', async function () {
            var kotak = cari('[data-uji="peringatan-tutup"] .peringatan');
            kotak.querySelector('button').click();
            return await sampai(function () { return !tampak(kotak); }) ? '' : 'peringatan tidak tertutup';
        }],
        ['konfirmasi tombol: "Ya" menjalankan @click', async function () {
            var hitung = cari('[data-uji="hitung-konfirmasi"]');
            cari('[data-uji="tombol-konfirmasi"]').click();
            if (!await sampai(function () { return tampak(dialogKonfirmasi()); })) { return 'dialog konfirmasi tidak muncul'; }
            if (hitung.textContent !== '0') { return '@click jalan sebelum dijawab'; }
            if (dialogKonfirmasi().querySelector('#ui-konfirmasi-judul').textContent !== 'Hapus data') { return 'judul data-confirm-judul tidak dipakai'; }
            dialogKonfirmasi().querySelector('[x-ref="tombolYa"]').click();
            if (!await sampai(function () { return hitung.textContent === '1'; })) { return 'aksi tidak jalan setelah "Ya" (hitung ' + hitung.textContent + ')'; }
            return await sampai(function () { return !tampak(dialogKonfirmasi()); }) ? '' : 'dialog tidak tertutup';
        }],
        ['konfirmasi tombol: "Batal" membatalkan', async function () {
            var hitung = cari('[data-uji="hitung-konfirmasi"]');
            cari('[data-uji="tombol-konfirmasi"]').click();
            if (!await sampai(function () { return tampak(dialogKonfirmasi()); })) { return 'dialog konfirmasi tidak muncul'; }
            dialogKonfirmasi().querySelector('[x-ref="tombolBatal"]').click();
            await sampai(function () { return !tampak(dialogKonfirmasi()); });
            await tunggu(100);
            return hitung.textContent === '1' ? '' : 'aksi tetap jalan setelah "Batal"';
        }],
        ['konfirmasi formulir: "Ya" mengirim formulir', async function () {
            var hitung = cari('[data-uji="hitung-kirim"]');
            cari('[data-uji="kirim-konfirmasi"]').click();
            if (!await sampai(function () { return tampak(dialogKonfirmasi()); })) { return 'dialog konfirmasi tidak muncul'; }
            if (hitung.textContent !== '0') { return 'formulir terkirim sebelum dijawab'; }
            dialogKonfirmasi().querySelector('[x-ref="tombolYa"]').click();
            return await sampai(function () { return hitung.textContent === '1'; }) ? '' : 'formulir tidak terkirim setelah "Ya"';
        }],
        ['konfirmasi dari JS (Ui.konfirmasi → Promise)', async function () {
            var jawaban = null;
            Ui.konfirmasi({ pesan: 'Uji dari JS?' }).then(function (ya) { jawaban = ya; });
            if (!await sampai(function () { return tampak(dialogKonfirmasi()) && dialogKonfirmasi().contains(document.activeElement); })) {
                return 'dialog konfirmasi tidak muncul / fokus tidak masuk';
            }
            tekan(document.activeElement, 'Escape');
            if (!await sampai(function () { return jawaban !== null; })) { return 'Promise tidak selesai setelah Esc'; }
            return jawaban === false ? '' : 'Esc tidak dianggap "Batal"';
        }],
    ];

    var jalankanUji = async function (komponen) {
        var lulus = 0;
        for (var i = 0; i < daftarUji.length; i++) {
            var alasan;
            try { alasan = await daftarUji[i][1](); } catch (e) { alasan = 'galat: ' + e.message; }
            if (alasan) {
                console.error('ui-kit uji GAGAL: ' + daftarUji[i][0] + ' — ' + alasan);
            } else {
                lulus++;
            }
            await tunggu(30);
        }
        var hasil = lulus + '/' + daftarUji.length + ' lulus';
        komponen.hasilUji = hasil;
        window.__ujiHasil = hasil;
        window.__ujiSibuk = false;
    };

    // ------------------------------------------------------------------ komponen halaman
    document.addEventListener('alpine:init', function () {
        Alpine.data('uiKit', function () {
            return {
                tema: 'lilac',
                cobaNilai: 250000,
                jumlahHapus: 0,
                jumlahKirim: 0,
                hasilUji: 'berjalan…',
                init: function () {
                    var self = this;
                    var t = null;
                    try { t = localStorage.getItem('uikit_tema'); } catch (e) { t = null; }
                    if (TEMA.indexOf(t) !== -1) { this.tema = t; }
                    this.terapkanTema();
                    if (modeUji) { setTimeout(function () { jalankanUji(self); }, 150); }
                },
                pilihTema: function (t) {
                    this.tema = t;
                    try { localStorage.setItem('uikit_tema', t); } catch (e) { /* penyimpanan peramban mati */ }
                    this.terapkanTema();
                },
                // Komponen kit hanya membaca warna utama: biru (Flowbite Terang/Gelap) atau lilac. Gaya sidebar = F3.
                terapkanTema: function () {
                    document.documentElement.classList.toggle('tema-lilac', this.tema === 'lilac');
                },
                tambahHitung: function () { this.jumlahHapus++; },
                hapusContoh: function () {
                    this.jumlahHapus++;
                    Ui.toast('Contoh saja: tidak ada data yang dihapus.', 'sukses');
                },
                kirimContoh: function () {
                    this.jumlahKirim++;
                    Ui.toast('Formulir contoh terkirim (tidak disimpan ke mana pun).', 'sukses');
                },
                kirimDialog: function () {
                    Ui.tutupDialog('contoh-form');
                    Ui.toast('Contoh: guru "tersimpan" (tidak disimpan ke mana pun).', 'sukses');
                },
                tanyaDariJs: function () {
                    Ui.konfirmasi({ judul: 'Simpan perubahan?', pesan: 'Perubahan jadwal akan langsung terlihat oleh semua guru.', ya: 'Ya, simpan' })
                        .then(function (ya) { Ui.toast(ya ? 'Anda memilih "Ya, simpan".' : 'Dibatalkan.', ya ? 'sukses' : 'info'); });
                },
            };
        });
    });
})();
