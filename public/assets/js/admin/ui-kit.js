/**
 * Halaman ui-kit (rombak tampilan F1, khusus laptop; sejak F3 di dalam layouts/admin) — komponen Alpine `uiKit` (aksi contoh +
 * uji-diri) dan `uiKitTema` (pemilih tema CONTOH: menulis <html data-warna data-sidebar>, disimpan di peramban saja).
 *
 * Uji-diri (?uji=1 → <div x-data="uiKit" data-mode-uji="1">): setelah Alpine versi CSP berjalan, tiap komponen kit (ui.js) dicoba
 * lewat DOM — format, dropdown, tab, dialog, laci, toast, peringatan, konfirmasi (tombol, formulir, dari JS) — lalu KERANGKA layout
 * (app.js: cari menu, grup lipat + cookie, ciutkan menu + cookie, laci HP, menu akun). Kegagalan dicatat lewat console.error (dibaca
 * tools/uji-peramban.mjs sebagai galat); window.__ujiSibuk = true selama berjalan (alat menunggu maks 15 detik), hasil akhir di
 * window.__ujiHasil ("19/19 lulus"). Status menu (ciut, grup) dikembalikan seperti semula setelah diuji.
 */
(function () {
    'use strict';

    // Tema contoh → pasangan warna utama × gaya menu samping (sama dengan Libraries\Tampilan::PRESET).
    var TEMA = { terang: ['biru', 'terang'], gelap: ['biru', 'gelap'], lilac: ['lilac', 'warna'] };
    var modeUji = document.querySelector('[data-mode-uji="1"]') !== null;
    if (modeUji) {
        window.__ujiSibuk = true;
        // Penjaga: bila Alpine/kit tidak jalan sama sekali, uji tak pernah selesai → tetap terlapor sebagai galat.
        setTimeout(function () {
            if (window.__ujiSibuk) {
                window.__ujiSibuk = false;
                console.error('ui-kit uji: tidak selesai dalam 14 detik (Alpine / ui.js / app.js tidak berjalan?)');
            }
        }, 14000);
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
    // Terlihat = punya kotak di layar DAN tidak invisible (dialog/laci tertutup memakai visibility: hidden, bukan display: none).
    var tampak = function (el) { return el !== null && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden'; };
    var cari = function (sel) { return document.querySelector(sel); };
    var tekan = function (el, tombol) {
        el.dispatchEvent(new KeyboardEvent('keydown', { key: tombol, bubbles: true, cancelable: true }));
    };
    var dialogKonfirmasi = function () { return cari('#ui-konfirmasi'); };
    var kue = function (nama) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + nama + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    };
    var lebarDesktop = function () { return window.matchMedia('(min-width: 1024px)').matches; };
    var tombolCiut = function () { return cari('button[aria-controls="menu-samping"][aria-pressed]'); };
    // Uji yang butuh menu samping LEBAR: bila pengguna sedang menciutkannya, lebarkan dulu lalu kembalikan sesudahnya.
    var denganMenuLebar = async function (fn) {
        var ciut = lebarDesktop() && document.documentElement.hasAttribute('data-ciut');
        if (ciut) {
            tombolCiut().click();
            await sampai(function () { return !document.documentElement.hasAttribute('data-ciut'); });
        }
        try { return await fn(); } finally {
            if (ciut) { tombolCiut().click(); }
        }
    };

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
        ['isian sandi: tombol lihat & peringatan Caps Lock', async function () {
            var isian = cari('#uk-sandi');
            var tombol = cari('[data-uji="tombol-sandi"]');
            var caps = cari('[data-uji="caps-lock"]');
            if (isian.type !== 'password' || !tampak(tombol)) { return 'awal: bukan password / tombol tak tampak'; }
            tombol.click();
            if (!await sampai(function () { return isian.type === 'text' && tombol.getAttribute('aria-pressed') === 'true'; })) {
                return 'klik pertama: sandi tidak terlihat (type ' + isian.type + ')';
            }
            tombol.click();
            if (!await sampai(function () { return isian.type === 'password' && tombol.getAttribute('aria-pressed') === 'false'; })) {
                return 'klik kedua: sandi tidak tersembunyi lagi';
            }
            isian.dispatchEvent(new KeyboardEvent('keyup', { key: 'A', modifierCapsLock: true, bubbles: true }));
            if (!await sampai(function () { return tampak(caps); })) { return 'peringatan Caps Lock tidak muncul'; }
            isian.dispatchEvent(new KeyboardEvent('keyup', { key: 'a', bubbles: true }));
            return await sampai(function () { return !tampak(caps); }) ? '' : 'peringatan Caps Lock tidak hilang';
        }],
        ['tab pindah (klik) + ikut #alamat (data-hash)', async function () {
            cari('[data-uji="tab-2"]').click();
            var ok = await sampai(function () { return tampak(cari('[data-uji="panel-2"]')) && !tampak(cari('[data-uji="panel-1"]')); });
            var pilih = cari('[data-uji="tab-2"]').getAttribute('aria-selected') === 'true';
            var hash = location.hash;
            cari('[data-uji="tab-1"]').click();
            if (!ok || !pilih) { return 'panel/aria-selected tidak berganti'; }
            return hash === '#rincian' ? '' : 'alamat tidak ikut berubah (hash "' + hash + '")';
        }],
        ['tab: panah kanan/kiri memindah tab & fokus', async function () {
            var t1 = cari('[data-uji="tab-1"]'), t2 = cari('[data-uji="tab-2"]');
            t1.focus();
            tekan(t1, 'ArrowRight');
            if (!await sampai(function () { return t2.getAttribute('aria-selected') === 'true' && document.activeElement === t2; })) {
                return 'panah kanan tidak pindah ke tab 2';
            }
            tekan(t2, 'ArrowLeft');
            return await sampai(function () { return t1.getAttribute('aria-selected') === 'true' && document.activeElement === t1; })
                ? '' : 'panah kiri tidak kembali ke tab 1';
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
            if (jawaban !== false) { return 'Esc tidak dianggap "Batal"'; }
            // Dibuka dari skrip (fokus awal <body>): fokus tidak boleh tertinggal di tombol dialog yang kini tersembunyi.
            return dialogKonfirmasi().contains(document.activeElement) ? 'fokus tertinggal di dalam dialog yang sudah ditutup' : '';
        }],
        ['dialog & konfirmasi dibuka lagi segera setelah ditutup (jeda 0/20/40 md)', async function () {
            var akar = cari('[data-nama="contoh-form"]');
            var jedaDaftar = [0, 20, 40];
            for (var i = 0; i < jedaDaftar.length; i++) {
                var jeda = jedaDaftar[i];
                Ui.bukaDialog('contoh-form');
                if (!await sampai(function () { return tampak(akar); })) { return 'jeda ' + jeda + ': dialog tidak terbuka'; }
                Ui.tutupDialog('contoh-form');
                await tunggu(jeda);
                Ui.bukaDialog('contoh-form');
                await tunggu(250);
                if (!tampak(akar)) { return 'jeda ' + jeda + ': dialog yang dibuka lagi tidak tampil'; }
                Ui.tutupDialog('contoh-form');
                if (!await sampai(function () { return !tampak(akar); })) { return 'jeda ' + jeda + ': dialog tidak tertutup'; }

                var kedua = null;
                Ui.konfirmasi({ pesan: 'Uji buka ulang (pertama)' });
                if (!await sampai(function () { return tampak(dialogKonfirmasi()); })) { return 'jeda ' + jeda + ': konfirmasi pertama tidak muncul'; }
                dialogKonfirmasi().querySelector('[x-ref="tombolBatal"]').click();
                await tunggu(jeda);
                Ui.konfirmasi({ pesan: 'Uji buka ulang (kedua)' }).then(function (ya) { kedua = ya; });
                await tunggu(250);
                if (!tampak(dialogKonfirmasi()) || !dialogKonfirmasi().contains(document.activeElement)) {
                    return 'jeda ' + jeda + ': konfirmasi kedua tidak tampil / tidak terfokus';
                }
                dialogKonfirmasi().querySelector('[x-ref="tombolBatal"]').click();
                if (!await sampai(function () { return kedua === false && !tampak(dialogKonfirmasi()); })) {
                    return 'jeda ' + jeda + ': konfirmasi kedua tidak bisa dijawab';
                }
            }
            return '';
        }],

        // ---------------- kerangka layout (F3, app.js) — berjalan di Alpine versi CSP pada halaman ini ----------------
        ['kerangka: cari menu (cocok, tak ada hasil, dikosongkan)', async function () {
            return await denganMenuLebar(async function () {
                var isian = cari('.sb-cari');
                if (!isian) { return 'kotak cari menu tidak ada'; }
                var ketik = function (t) { isian.value = t; isian.dispatchEvent(new Event('input', { bubbles: true })); };
                var tampil = function () { return Array.prototype.filter.call(document.querySelectorAll('.sb-item'), tampak); };
                var awal = tampil();
                // Kata kunci dari menu TERAKHIR (bisa di dalam grup tertutup — pencarian harus tetap menemukannya).
                var semua = Array.prototype.filter.call(document.querySelectorAll('a.sb-item'), function () { return true; });
                if (semua.length < 2) { return 'menu samping kosong'; }
                var kata = semua[semua.length - 1].textContent.trim().toLowerCase();
                ketik('  ' + kata.toUpperCase() + ' ');
                if (!await sampai(function () {
                    var v = tampil();
                    return v.length >= 1 && v.every(function (el) { return el.textContent.toLowerCase().indexOf(kata) !== -1; });
                })) { ketik(''); return 'hasil cari "' + kata + '" tidak sesuai'; }
                ketik('zzqq-tak-ada');
                if (!await sampai(function () { return tampil().length === 0 && tampak(cari('.sb-kosong')); })) {
                    ketik('');
                    return '"Menu tidak ditemukan" tidak muncul';
                }
                ketik('');
                return await sampai(function () { return tampil().length === awal.length && !tampak(cari('.sb-kosong')); })
                    ? '' : 'menu tidak kembali setelah kotak cari dikosongkan';
            });
        }],
        ['kerangka: grup menu lipat + cookie sb_grup', async function () {
            return await denganMenuLebar(async function () {
                var grup = Array.prototype.find.call(document.querySelectorAll('.sb-grup'), function (g) {
                    return g.getAttribute('data-buka') === '0';
                });
                if (!grup) { return 'tak ada grup tertutup untuk diuji'; }
                var kunci = grup.getAttribute('data-kunci');
                var judul = grup.querySelector('.sb-grup-tombol');
                var isi = grup.querySelector('.sb-anak-daftar');
                if (tampak(isi)) { return 'grup tertutup malah tampil terbuka'; }
                judul.click();
                if (!await sampai(function () {
                    return tampak(isi) && judul.getAttribute('aria-expanded') === 'true' && kue('sb_grup').split(',').indexOf(kunci) !== -1;
                })) { return 'grup tidak terbuka / cookie sb_grup tidak tersimpan'; }
                judul.click();
                return await sampai(function () { return !tampak(isi) && kue('sb_grup').split(',').indexOf(kunci) === -1; }, 2500)
                    ? '' : 'grup tidak tertutup / cookie sb_grup tidak dihapus';
            });
        }],
        ['kerangka: ciutkan menu (desktop) + cookie sb_ciut', async function () {
            if (!lebarDesktop()) { return ''; } // layar sempit: tombol ciut tidak dipakai
            var tombol = tombolCiut();
            var html = document.documentElement;
            var awal = html.hasAttribute('data-ciut');
            tombol.click();
            if (!await sampai(function () {
                return html.hasAttribute('data-ciut') !== awal && (kue('sb_ciut') === '1') !== awal && tombol.getAttribute('aria-pressed') === String(!awal);
            })) { return 'menu tidak berubah (ciut/lebar) atau cookie sb_ciut tidak sesuai'; }
            tombol.click();
            return await sampai(function () { return html.hasAttribute('data-ciut') === awal && (kue('sb_ciut') === '1') === awal; })
                ? '' : 'menu tidak kembali seperti semula';
        }],
        ['kerangka: laci menu (HP) buka & tutup (Esc)', async function () {
            var tombol = Array.prototype.find.call(document.querySelectorAll('button[aria-controls="menu-samping"]'), function (b) {
                return !b.hasAttribute('aria-pressed');
            });
            var sb = cari('#menu-samping');
            if (!tombol || !sb) { return 'tombol laci / menu samping tidak ada'; }
            tombol.click();
            if (!await sampai(function () { return sb.hasAttribute('data-terbuka') && tombol.getAttribute('aria-expanded') === 'true'; })) {
                return 'laci tidak terbuka';
            }
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            return await sampai(function () { return !sb.hasAttribute('data-terbuka') && tombol.getAttribute('aria-expanded') === 'false'; })
                ? '' : 'laci tidak tertutup dengan Esc';
        }],
        ['kerangka: menu akun di bilah atas (buka & Esc)', async function () {
            var pemicu = cari('.navbar [aria-haspopup="true"]');
            var menu = pemicu ? pemicu.parentElement.querySelector('.dropdown-menu') : null;
            if (!menu) { return 'menu akun tidak ada'; }
            pemicu.click();
            if (!await sampai(function () { return tampak(menu) && tampak(menu.querySelector('a[href*="logout"]')); })) { return 'menu akun tidak terbuka'; }
            tekan(pemicu, 'Escape');
            return await sampai(function () { return !tampak(menu); }) ? '' : 'menu akun tidak tertutup dengan Esc';
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
        /* Pemilih tema CONTOH di kepala halaman: pratinjau di halaman ini saja (disimpan di peramban). Belum pernah memilih →
         * yang tertanda = tema yang sedang berlaku (Pengaturan Sekolah → Tampilan). */
        Alpine.data('uiKitTema', function () {
            return {
                tema: '',
                init: function () {
                    var self = this;
                    var t = null;
                    try { t = localStorage.getItem('uikit_tema'); } catch (e) { t = null; }
                    if (Object.prototype.hasOwnProperty.call(TEMA, t)) {
                        this.tema = t;
                        this.terapkan();
                        return;
                    }
                    var w = document.documentElement.getAttribute('data-warna');
                    var s = document.documentElement.getAttribute('data-sidebar');
                    Object.keys(TEMA).forEach(function (k) {
                        if (TEMA[k][0] === w && TEMA[k][1] === s) { self.tema = k; }
                    });
                },
                pilih: function (t) {
                    if (!Object.prototype.hasOwnProperty.call(TEMA, t)) { return; }
                    this.tema = t;
                    try { localStorage.setItem('uikit_tema', t); } catch (e) { /* penyimpanan peramban mati */ }
                    this.terapkan();
                },
                terapkan: function () {
                    document.documentElement.setAttribute('data-warna', TEMA[this.tema][0]);
                    document.documentElement.setAttribute('data-sidebar', TEMA[this.tema][1]);
                },
            };
        });

        Alpine.data('uiKit', function () {
            return {
                cobaNilai: 250000,
                jumlahHapus: 0,
                jumlahKirim: 0,
                hasilUji: 'berjalan…',
                init: function () {
                    var self = this;
                    if (modeUji) { setTimeout(function () { jalankanUji(self); }, 150); }
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
