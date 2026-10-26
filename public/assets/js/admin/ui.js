/**
 * =====================================================================
 * Kit komponen panel admin (rombak tampilan F1) — gaya Flowbite, SIAP CSP.
 * =====================================================================
 * Semua logika ada di berkas ini; atribut di view hanya memanggil metode / magic sederhana yang didukung
 * Alpine versi CSP (docs/RENCANA-UPGRADE-TAMPILAN.md §5a). Contoh hidup semua komponen: /admin/ui-kit (khusus laptop).
 * Kelas CSS-nya di resources/css/admin.css (bagian "KIT KOMPONEN"); partial PHP-nya lewat helper ui_*() (app/Helpers/ui_helper.php).
 *
 * Komponen Alpine (x-data="…"):
 *   dropdown    menu tarik-turun — @click="alih()", @click.outside="tutup()", @keydown.escape="tutup(true)",
 *               @keydown.down.prevent="gerak(1)" / up = gerak(-1); tombol pemicu x-ref="pemicu".
 *   dialog      kotak dialog (modal) — pakai ui_dialog_buka()/ui_dialog_tutup(). Dibuka lewat tombol data-buka-dialog="nama",
 *               $dispatch('buka-dialog', { nama: 'nama' }) atau Ui.bukaDialog('nama'); data-awal-buka="1" = langsung terbuka.
 *               (F8) Tombol pemicu boleh membawa data: data-dialog-aksi="URL" → action form[data-aksi-pemicu] di dialog;
 *               data-dialog-isi='{"k":"…"}' → isi elemen [data-isi-pemicu="k"] (isian = value, lainnya = teks). Tanpa JS halaman.
 *   laci        laci samping (drawer) — sama dengan dialog (ui_laci_buka()/ui_laci_tutup()).
 *   tab         tab di sisi peramban — data-awal="nama", tombol role="tab" data-tab="nama" @click="pilih('nama')"
 *               :aria-selected="aktif === 'nama'"; panah kiri/kanan = pindah(-1)/pindah(1); data-hash="1" = ikut #alamat.
 *   toastWadah  wadah notifikasi kecil — sekali per halaman (ui_toast()).
 *   konfirmasi  dialog konfirmasi — sekali per halaman (ui_konfirmasi()).
 *   salinTeks   tombol salin — data-teks="…" atau data-sasaran="#id"; @click="salin()"; x-show="tersalin".
 *   daftarCentang daftar centang + Pilih semua + penghitung (F8) — butir input[data-centang]; @change="hitungCentang()",
 *               pilihSemuaCentang($event), x-text="teksCentang()", :disabled="belumDicentang()"; data-satuan="siswa dipilih".
 *   pilihBerkas nama berkas yang dipilih (F8) — x-data="pilihBerkas" data-kosong="…"; input file @change="catatBerkas($event)";
 *               x-text="namaBerkasTerpilih()". Tautan Kembali tanpa JS halaman: <a href="URL cadangan" data-kembali>.
 *   isianSandi  kata sandi + tombol lihat (F2) — pada pembungkus label+isian: input :type="lihat ? 'text' : 'password'"
 *               @keydown/@keyup="cekCapsLock($event)" @blur="capsLock = false"; tombol .tombol-sandi @mousedown.prevent
 *               @click="alih()" :aria-pressed="lihat"; peringatan x-show="capsLock". Saat formulir dikirim kembali tersembunyi.
 *
 * Magic Alpine (boleh dipanggil dari atribut, pengganti fungsi global): $rupiah(n), $angka(n, desimal), $tglIndo('2026-10-14'),
 *   $hariTanggal('2026-10-14'), $periodeTeks(mulai, selesai), $bulanTahun('2026-10'), $toast(pesan, jenis).
 *   Jenis toast: sukses | galat | awas | info.
 *
 * Dari berkas JS lain (bukan atribut): Ui.toast(pesan, jenis), Ui.konfirmasi({ pesan, judul, ya, batal, jenis }) → Promise<boolean>,
 *   Ui.bukaDialog(nama), Ui.tutupDialog(nama), Ui.format.rupiah(…) dst.
 *
 * Konfirmasi tanpa JS halaman: data-confirm="Pesan?" pada <a>/<button>/<input type=submit> atau pada <form> (saat dikirim);
 *   opsional data-confirm-judul, data-confirm-ya, data-confirm-jenis="bahaya". Hanya aktif bila dialog konfirmasi (ui_konfirmasi())
 *   ada di halaman; tanpa itu atribut yang sama tetap ditangani app.js dengan window.confirm bawaan peramban.
 */
(function () {
    'use strict';

    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /* ---------------------------------------------------------------
     * 1. Pembantu format — hasil SAMA dengan sisi server:
     *    angka/rupiah = number_format(n, d, ',', '.') (PklBiaya::rupiah), tglIndo = IsianBantu::tanggalIndo,
     *    periodeTeks = SuratPkl::periodeTeks. Masukan tak sah → teks kosong.
     * ------------------------------------------------------------- */
    var keTanggal = function (s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s === null || s === undefined ? '' : s));
        if (!m) { return null; }
        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return (d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3]) ? d : null;
    };
    var hariBulan = function (d) { return d.getUTCDate() + ' ' + BULAN[d.getUTCMonth()]; };

    var Format = {
        angka: function (n, desimal) {
            if (n === null || n === undefined || n === '') { return ''; }
            var x = Number(n);
            if (!isFinite(x)) { return ''; }
            var d = desimal > 0 ? Math.min(Math.floor(desimal), 10) : 0;
            var bagian = Math.abs(x).toFixed(d).split('.');
            var hasil = bagian[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (d > 0 ? ',' + bagian[1] : '');
            return (x < 0 && Number(bagian.join('.')) !== 0 ? '-' : '') + hasil;
        },
        rupiah: function (n) {
            var a = Format.angka(n, 0);
            return a === '' ? '' : 'Rp ' + a;
        },
        tglIndo: function (s) {
            var d = keTanggal(s);
            return d ? hariBulan(d) + ' ' + d.getUTCFullYear() : '';
        },
        hariTanggal: function (s) {
            var d = keTanggal(s);
            return d ? HARI[d.getUTCDay()] + ', ' + hariBulan(d) + ' ' + d.getUTCFullYear() : '';
        },
        periodeTeks: function (mulai, selesai) {
            var a = keTanggal(mulai), b = keTanggal(selesai);
            if (!a || !b || b < a) { return ''; }
            if (a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth()) {
                return a.getUTCDate() + ' s.d. ' + hariBulan(b) + ' ' + b.getUTCFullYear();
            }
            if (a.getUTCFullYear() === b.getUTCFullYear()) { return hariBulan(a) + ' s.d. ' + hariBulan(b) + ' ' + b.getUTCFullYear(); }
            return hariBulan(a) + ' ' + a.getUTCFullYear() + ' s.d. ' + hariBulan(b) + ' ' + b.getUTCFullYear();
        },
        bulanTahun: function (s) {
            var m = /^(\d{4})-(\d{2})/.exec(String(s === null || s === undefined ? '' : s));
            return (m && +m[2] >= 1 && +m[2] <= 12) ? BULAN[+m[2] - 1] + ' ' + m[1] : '';
        },
    };

    var bacaJson = function (el, nama, cadangan) {
        var isi = el.getAttribute(nama);
        if (!isi) { return cadangan; }
        try { return JSON.parse(isi); } catch (e) { return cadangan; }
    };

    /* ---------------------------------------------------------------
     * 2. Fokus & gulir untuk dialog / laci / konfirmasi.
     * ------------------------------------------------------------- */
    var FOKUS = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), '
        + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var bisaFokus = function (wadah) {
        return Array.prototype.filter.call(wadah.querySelectorAll(FOKUS), function (x) { return x.getClientRects().length > 0; });
    };
    // Halaman di belakang dialog tidak ikut tergulir. Penghitung: dialog bisa bertumpuk (mis. konfirmasi di atas dialog form).
    var jumlahTerbuka = 0;
    var kunciGulir = function (n) {
        jumlahTerbuka = Math.max(0, jumlahTerbuka + n);
        document.documentElement.classList.toggle('overflow-hidden', jumlahTerbuka > 0);
    };
    // Tab / Shift+Tab berputar di dalam panel (dipasang sebagai metode komponen: @keydown.tab="jebakFokus($event)").
    var jebakFokus = function (e) {
        var daftar = bisaFokus(this.$refs.panel || this.$el);
        if (daftar.length === 0) { e.preventDefault(); return; }
        var pertama = daftar[0], terakhir = daftar[daftar.length - 1];
        if (e.shiftKey && document.activeElement === pertama) {
            e.preventDefault();
            terakhir.focus();
        } else if (!e.shiftKey && document.activeElement === terakhir) {
            e.preventDefault();
            pertama.focus();
        }
    };
    // Fokus ke elemen di dialog yang baru dibuka. Isian & tombol berkelas "transition" (Tailwind v4 ikut mentransisikan visibility)
    // masih "hidden" sesaat setelah dialog tampil, sehingga focus() ditolak peramban → ulangi tiap 20 md (maks ±200 md).
    // Berhasil = elemen itu yang aktif DAN sudah terlihat (fokus lama yang tertinggal di tombol tersembunyi tidak dihitung).
    var fokuskan = function (el, masihBuka, sisa) {
        if (!el || !masihBuka()) { return; }
        el.focus();
        var berhasil = document.activeElement === el && getComputedStyle(el).visibility !== 'hidden';
        if (!berhasil && sisa > 0) {
            setTimeout(function () { fokuskan(el, masihBuka, sisa - 1); }, 20);
        }
    };
    // Setelah dialog ditutup: fokus kembali ke elemen asal (tombol pembuka). Bila asalnya tak bisa difokus (mis. <body> karena
    // dialog dibuka dari skrip) dan fokus masih tertinggal di dalam dialog yang kini tersembunyi → lepaskan.
    var kembalikanFokus = function (el, wadah) {
        if (el && el.focus && document.contains(el)) {
            try { el.focus(); } catch (e) { /* elemen tak bisa difokus lagi */ }
        }
        var aktif = document.activeElement;
        if (wadah && aktif && wadah.contains(aktif) && aktif.blur) { aktif.blur(); }
    };

    /* ---------------------------------------------------------------
     * 3. Toast — notifikasi kecil di pojok kanan bawah (wadah: ui_toast()).
     * ------------------------------------------------------------- */
    var JENIS_TOAST = ['sukses', 'galat', 'awas', 'info'];
    var wadahToast = null;   // komponen toastWadah di halaman ini
    var antreToast = [];     // panggilan sebelum wadah siap (mis. dari skrip halaman saat dimuat)
    var nomorToast = 0;
    var toast = function (pesan, jenis, lama) {
        var t = {
            pesan: String(pesan === null || pesan === undefined ? '' : pesan),
            jenis: JENIS_TOAST.indexOf(jenis) !== -1 ? jenis : 'info',
            lama: typeof lama === 'number' ? lama : (jenis === 'galat' ? 7000 : 4000),
        };
        if (t.pesan === '') { return; }
        if (wadahToast) { wadahToast.tambah(t); } else { antreToast.push(t); }
    };

    /* ---------------------------------------------------------------
     * 4. Konfirmasi — pengganti confirm() (dialog: ui_konfirmasi()).
     * ------------------------------------------------------------- */
    var dialogKonfirmasi = null;   // komponen konfirmasi di halaman ini
    var elKonfirmasi = null;
    var adaKonfirmasi = function () { return dialogKonfirmasi !== null && elKonfirmasi !== null && document.contains(elKonfirmasi); };
    var konfirmasi = function (opsi) {
        opsi = typeof opsi === 'string' ? { pesan: opsi } : (opsi || {});
        if (!adaKonfirmasi()) { return Promise.resolve(window.confirm(opsi.pesan || 'Lanjutkan?')); }
        return dialogKonfirmasi.tanya(opsi);
    };
    var opsiDari = function (el) {
        return {
            pesan: el.getAttribute('data-confirm') || '',
            judul: el.getAttribute('data-confirm-judul') || '',
            ya: el.getAttribute('data-confirm-ya') || '',
            jenis: el.getAttribute('data-confirm-jenis') || '',
        };
    };

    // Aksi POST di halaman "Lihat saja" (<main data-lihat-saja>, kecuali [data-lihat-boleh]) pasti ditolak — jangan ditanyakan dulu;
    // app.js yang mencegatnya dan memberi tahu lewat toast.
    var lihatSajaPost = function (el) {
        if (!el.closest || !el.closest('[data-lihat-saja]') || el.closest('[data-lihat-boleh]')) { return false; }
        if (el.matches && el.matches('a[data-method="post"]')) { return true; }
        var f = el.tagName === 'FORM' ? el : (el.form || null);
        return f !== null && String(f.getAttribute('method') || 'get').toLowerCase() === 'post';
    };
    // Klik pada pemicu ber-data-confirm: ditahan di fase tangkap (window → sebelum penangan lain, termasuk @click Alpine & tautan
    // POST app.js), ditanyakan lewat dialog, lalu bila "Ya" diklik ulang dengan tanda lolos sehingga aksi aslinya berjalan.
    var PEMICU = 'a[data-confirm], button[data-confirm], input[type="submit"][data-confirm], input[type="button"][data-confirm], [role="button"][data-confirm]';
    window.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest(PEMICU) : null;
        if (!el || !adaKonfirmasi() || lihatSajaPost(el)) { return; }
        if (el.__uiLolos) { el.__uiLolos = false; return; }
        if (el.disabled) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        konfirmasi(opsiDari(el)).then(function (ya) {
            if (!ya) { return; }
            el.__uiLolos = true;
            el.click();
        });
    }, true);
    // <form data-confirm="…">: ditanyakan saat dikirim; tombol pengirim (name/value) tetap ikut terkirim.
    window.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.tagName !== 'FORM' || !f.hasAttribute('data-confirm') || !adaKonfirmasi() || lihatSajaPost(f)) { return; }
        if (f.__uiLolos) { f.__uiLolos = false; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        var pengirim = e.submitter && e.submitter.form === f ? e.submitter : null;
        konfirmasi(opsiDari(f)).then(function (ya) {
            if (!ya) { return; }
            f.__uiLolos = true;
            if (f.requestSubmit) { f.requestSubmit(pengirim || undefined); } else { f.submit(); }
        });
    }, true);

    /* ---------------------------------------------------------------
     * 5. Salin ke papan klip (https / localhost: Clipboard API; selain itu cara lama).
     * ------------------------------------------------------------- */
    var salinKePapan = function (teks) {
        if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(teks); }
        return new Promise(function (ok, gagal) {
            var t = document.createElement('textarea');
            t.value = teks;
            t.setAttribute('readonly', '');
            t.className = 'sr-only';
            document.body.appendChild(t);
            t.select();
            var berhasil = false;
            try { berhasil = document.execCommand('copy'); } catch (err) { berhasil = false; }
            document.body.removeChild(t);
            if (berhasil) { ok(); } else { gagal(new Error('Salin gagal')); }
        });
    };

    /* ---------------------------------------------------------------
     * 6. Pembuka dialog/laci tanpa Alpine di tombolnya: <button type="button" data-buka-dialog="nama">.
     * ------------------------------------------------------------- */
    var kirimAcara = function (nama, jenis) {
        window.dispatchEvent(new CustomEvent(jenis, { detail: { nama: String(nama) } }));
    };
    // (F8) Data dari tombol pemicu ke dialog — tanpa JS halaman (siap CSP):
    //   data-dialog-aksi="URL"          → action semua form[data-aksi-pemicu] di dalam dialog;
    //   data-dialog-isi='{"k":"nilai"}' → elemen [data-isi-pemicu="k"]: input/select/textarea dapat value (kotak centang:
    //                                     dicentang bila nilai "1"/"true"/sama dengan value-nya), elemen lain dapat teks.
    var isiDialogDariPemicu = function (nama, el) {
        var aksi = el.getAttribute('data-dialog-aksi');
        var raw = el.getAttribute('data-dialog-isi');
        if (aksi === null && raw === null) { return; }
        var sel = '[x-data="dialog"][data-nama="' + nama + '"], [x-data="laci"][data-nama="' + nama + '"]';
        var dlg = null;
        try { dlg = document.querySelector(sel); } catch (err) { dlg = null; }
        if (!dlg) { return; }
        if (aksi !== null) {
            Array.prototype.forEach.call(dlg.querySelectorAll('form[data-aksi-pemicu]'), function (f) { f.setAttribute('action', aksi); });
        }
        if (raw === null) { return; }
        var isi = {};
        try { isi = JSON.parse(raw) || {}; } catch (err) { isi = {}; }
        Array.prototype.forEach.call(dlg.querySelectorAll('[data-isi-pemicu]'), function (t) {
            var k = t.getAttribute('data-isi-pemicu');
            if (!Object.prototype.hasOwnProperty.call(isi, k)) { return; }
            var v = isi[k] === null || isi[k] === undefined ? '' : String(isi[k]);
            if (t.type === 'checkbox' || t.type === 'radio') {
                t.checked = t.type === 'radio' ? t.value === v : (v === '1' || v === 'true' || v === t.value);
            } else if (/^(INPUT|SELECT|TEXTAREA)$/.test(t.tagName)) {
                t.value = v;
            } else {
                t.textContent = v;
                return;
            }
            t.dispatchEvent(new Event('input', { bubbles: true }));   // isian ber-x-model ikut diperbarui
            t.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-buka-dialog]') : null;
        if (!el || e.defaultPrevented) { return; }
        e.preventDefault();
        var nama = el.getAttribute('data-buka-dialog');
        isiDialogDariPemicu(nama, el);
        kirimAcara(nama, 'buka-dialog');
    });

    // (F5) Kotak peringatan ber-data-hilang="ms" (ui_peringatan … ['hilang' => ms]) memudar lalu hilang sendiri — aturan user:
    // semua pesan sesudah aksi hilang otomatis (di layout admin lewat toast; ini untuk halaman di luar layout, mis. halaman masuk).
    var hilangkan = function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-hilang]'), function (el) {
            var ms = parseInt(el.getAttribute('data-hilang'), 10) || 0;
            el.removeAttribute('data-hilang');
            if (ms <= 0) { return; }
            setTimeout(function () {
                el.classList.add('peringatan-pudar');
                setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 350);
            }, ms);
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', hilangkan); } else { hilangkan(); }

    window.Ui = {
        format: Format,
        toast: toast,
        konfirmasi: konfirmasi,
        adaKonfirmasi: adaKonfirmasi,
        bukaDialog: function (nama) { kirimAcara(nama, 'buka-dialog'); },
        tutupDialog: function (nama) { kirimAcara(nama, 'tutup-dialog'); },
    };

    /* ---------------------------------------------------------------
     * 7. Registrasi komponen & magic Alpine.
     * ------------------------------------------------------------- */
    document.addEventListener('alpine:init', function () {

        Alpine.magic('rupiah', function () { return Format.rupiah; });
        Alpine.magic('angka', function () { return Format.angka; });
        Alpine.magic('tglIndo', function () { return Format.tglIndo; });
        Alpine.magic('hariTanggal', function () { return Format.hariTanggal; });
        Alpine.magic('periodeTeks', function () { return Format.periodeTeks; });
        Alpine.magic('bulanTahun', function () { return Format.bulanTahun; });
        Alpine.magic('toast', function () { return toast; });

        /* Menu tarik-turun. Menu = elemen .dropdown-menu (x-show="buka"); butir = .dropdown-item. */
        /* CATATAN Alpine versi CSP: di dalam metode, this.$el = elemen yang MEMICU (mis. tombol yang diklik), bukan akar komponen.
         * Karena itu akar disimpan sekali di init() (variabel "akar") dan dipakai di semua metode. */
        Alpine.data('dropdown', function () {
            var akar = null;
            return {
                buka: false,
                init: function () { akar = this.$el; },
                alih: function () { this.buka = !this.buka; },
                tutup: function (fokusKePemicu) {
                    if (!this.buka) { return; }
                    this.buka = false;
                    if (fokusKePemicu === true && this.$refs.pemicu) { this.$refs.pemicu.focus(); }
                },
                // Panah atas/bawah: buka menu lalu pindah fokus antarbutir.
                gerak: function (n) {
                    var self = this;
                    this.buka = true;
                    this.$nextTick(function () {
                        var butir = Array.prototype.filter.call(akar.querySelectorAll('.dropdown-item'), function (x) {
                            return !x.disabled && x.getClientRects().length > 0;
                        });
                        if (butir.length === 0) { return; }
                        var i = butir.indexOf(document.activeElement);
                        butir[i === -1 ? (n > 0 ? 0 : butir.length - 1) : (i + n + butir.length) % butir.length].focus();
                    });
                },
            };
        });

        /* Dialog & laci: satu logika, beda tampilan (partial ui/dialog_buka & ui/laci_buka). Panel = x-ref="panel".
         * Tampil lewat :data-buka="buka" + CSS (bukan x-show beranimasi, yang menyembunyikan tertunda satu frame dan bisa
         * menelan dialog yang dibuka lagi tepat setelah ditutup) — begitu buka = true, panel langsung terlihat & bisa difokus. */
        var pabrikDialog = function () {
            var asal = null;   // elemen yang difokus sebelum dibuka → dikembalikan saat ditutup
            var akar = null;
            return {
                buka: false,
                nama: '',
                init: function () {
                    akar = this.$el;
                    this.nama = this.$el.getAttribute('data-nama') || '';
                    if (this.$el.getAttribute('data-awal-buka') === '1') { this.bukaDialog(); }
                },
                bukaDialog: function () {
                    if (this.buka) { return; }
                    var self = this;
                    asal = document.activeElement;
                    this.buka = true;
                    kunciGulir(1);
                    this.$nextTick(function () {
                        if (!self.buka) { return; }   // sudah ditutup lagi sebelum sempat difokus
                        var panel = self.$refs.panel || akar;
                        var sasaran = panel.querySelector('[autofocus]') || bisaFokus(panel).filter(function (x) {
                            return !x.hasAttribute('data-tutup');
                        })[0] || panel;
                        fokuskan(sasaran, function () { return self.buka; }, 10);
                    });
                    this.$dispatch('dialog-terbuka', { nama: this.nama });
                },
                tutup: function () {
                    if (!this.buka) { return; }
                    this.buka = false;
                    kunciGulir(-1);
                    kembalikanFokus(asal, akar);
                    asal = null;
                    this.$dispatch('dialog-tertutup', { nama: this.nama });
                },
                // @buka-dialog.window / @tutup-dialog.window — hanya untuk dialog yang namanya cocok.
                dariAcara: function (e) {
                    var d = e.detail || {};
                    if (!this.nama || d.nama !== this.nama) { return; }
                    if (e.type === 'tutup-dialog') { this.tutup(); } else { this.bukaDialog(); }
                },
                jebakFokus: jebakFokus,
            };
        };
        Alpine.data('dialog', pabrikDialog);
        Alpine.data('laci', pabrikDialog);

        /* Tab di sisi peramban. Hanya tombol tab milik komponen ini (bukan tab bersarang) yang dihitung. */
        Alpine.data('tab', function () {
            var akar = null;
            return {
                aktif: '',
                init: function () {
                    var self = this;
                    akar = this.$el;
                    var nama = this.daftarTab().map(function (b) { return b.getAttribute('data-tab'); });
                    var awal = akar.getAttribute('data-awal') || nama[0] || '';
                    if (akar.getAttribute('data-hash') === '1') {
                        var dariHash = function () {
                            var h = '';
                            try { h = decodeURIComponent(location.hash.replace(/^#/, '')); } catch (e) { h = ''; }
                            return nama.indexOf(h) !== -1 ? h : '';
                        };
                        awal = dariHash() || awal;
                        window.addEventListener('hashchange', function () { var h = dariHash(); if (h) { self.aktif = h; } });
                    }
                    this.aktif = awal;
                },
                daftarTab: function () {
                    return Array.prototype.filter.call(akar.querySelectorAll('[role="tab"][data-tab]'), function (b) {
                        return b.closest('[x-data]') === akar;
                    });
                },
                pilih: function (nama) {
                    this.aktif = String(nama);
                    if (akar.getAttribute('data-hash') === '1' && window.history && history.replaceState) {
                        history.replaceState(null, '', '#' + encodeURIComponent(this.aktif));
                    }
                },
                // Panah kiri/kanan di daftar tab (pola tab WAI-ARIA).
                pindah: function (n) {
                    var tombol = this.daftarTab();
                    if (tombol.length === 0) { return; }
                    var i = -1;
                    for (var k = 0; k < tombol.length; k++) { if (tombol[k].getAttribute('data-tab') === this.aktif) { i = k; } }
                    var j = (i + n + tombol.length) % tombol.length;
                    this.pilih(tombol[j].getAttribute('data-tab'));
                    tombol[j].focus();
                },
            };
        });

        /* Wadah toast. data-awal = JSON [{pesan, jenis}] (mis. pesan sukses dari server). */
        Alpine.data('toastWadah', function () {
            return {
                daftar: [],
                init: function () {
                    wadahToast = this;
                    bacaJson(this.$el, 'data-awal', []).concat(antreToast.splice(0)).forEach(function (t) {
                        toast(t.pesan, t.jenis, t.lama);
                    });
                },
                butir: function (id) {
                    var item = null;
                    this.daftar.forEach(function (x) { if (x.id === id) { item = x; } });
                    return item;
                },
                tambah: function (t) {
                    var self = this;
                    var id = ++nomorToast;
                    // Masuk dengan tampil = false lalu dinyalakan → transisi masuk ikut berjalan.
                    this.daftar.push({ id: id, pesan: t.pesan, jenis: t.jenis, tampil: false, keluar: false });
                    this.$nextTick(function () { var x = self.butir(id); if (x && !x.keluar) { x.tampil = true; } });
                    var aktif = this.daftar.filter(function (x) { return !x.keluar; });
                    if (aktif.length > 5) { this.hapus(aktif[0].id); }
                    if (t.lama > 0) { setTimeout(function () { self.hapus(id); }, t.lama); }
                },
                hapus: function (id) {
                    var self = this;
                    var item = this.butir(id);
                    if (!item || item.keluar) { return; }
                    item.keluar = true;
                    item.tampil = false;   // jalankan transisi keluar, baru buang dari daftar
                    setTimeout(function () {
                        self.daftar = self.daftar.filter(function (x) { return x.id !== id; });
                    }, 200);
                },
            };
        });

        /* Dialog konfirmasi bersama (satu per halaman). */
        Alpine.data('konfirmasi', function () {
            var jawab = null;   // resolve Promise pertanyaan yang sedang tampil
            var asal = null;
            var akar = null;
            return {
                buka: false,
                judul: '',
                pesan: '',
                ya: '',
                batal: '',
                bahaya: false,
                init: function () {
                    akar = this.$el;
                    dialogKonfirmasi = this;
                    elKonfirmasi = this.$el;
                },
                tanya: function (opsi) {
                    var self = this;
                    if (jawab) { this.selesai(false); }   // pertanyaan lama dianggap batal
                    this.bahaya = opsi.jenis === 'bahaya';
                    this.judul = opsi.judul || 'Konfirmasi';
                    this.pesan = opsi.pesan || 'Lanjutkan?';
                    this.ya = opsi.ya || 'Ya, lanjutkan';
                    this.batal = opsi.batal || 'Batal';
                    asal = document.activeElement;
                    this.buka = true;
                    kunciGulir(1);
                    // Aksi berbahaya: fokus awal di "Batal" (Enter tidak langsung menjalankan aksinya).
                    this.$nextTick(function () {
                        fokuskan(self.$refs[self.bahaya ? 'tombolBatal' : 'tombolYa'], function () { return self.buka; }, 10);
                    });
                    return new Promise(function (ok) { jawab = ok; });
                },
                setuju: function () { this.selesai(true); },
                tolak: function () { this.selesai(false); },
                selesai: function (hasil) {
                    if (!this.buka) { return; }
                    this.buka = false;
                    kunciGulir(-1);
                    kembalikanFokus(asal, akar);
                    asal = null;
                    var j = jawab;
                    jawab = null;
                    if (j) { j(hasil); }
                },
                jebakFokus: jebakFokus,
            };
        });

        /* Isian kata sandi: tombol lihat/sembunyikan + peringatan Caps Lock. */
        Alpine.data('isianSandi', function () {
            return {
                lihat: false,
                capsLock: false,
                init: function () {
                    var self = this;
                    // Sandi kembali tersembunyi saat formulir dikirim (tidak terlihat selama "Memproses…", dan pengelola
                    // sandi peramban tetap mengenali isiannya sebagai kata sandi).
                    var form = this.$el.closest('form');
                    if (form) { form.addEventListener('submit', function () { self.lihat = false; }); }
                },
                alih: function () { this.lihat = !this.lihat; },
                cekCapsLock: function (e) {
                    if (e && typeof e.getModifierState === 'function') { this.capsLock = e.getModifierState('CapsLock'); }
                },
            };
        });

        /* Tombol salin. */
        Alpine.data('salinTeks', function () {
            var waktu = null;
            var akar = null;
            return {
                tersalin: false,
                init: function () { akar = this.$el; },
                salin: function () {
                    var self = this;
                    var el = akar;   // atribut data-teks / data-sasaran ada di akar komponen
                    var teks = el.getAttribute('data-teks');
                    var sasaran = el.getAttribute('data-sasaran');
                    if (teks === null && sasaran) {
                        var s = document.querySelector(sasaran);
                        teks = s ? (('value' in s && s.tagName !== 'BUTTON') ? s.value : s.textContent) : '';
                    }
                    salinKePapan(String(teks || '')).then(function () {
                        self.tersalin = true;
                        if (el.getAttribute('data-toast') !== '0') { toast(el.getAttribute('data-pesan') || 'Tersalin ke papan klip.', 'sukses'); }
                        clearTimeout(waktu);
                        waktu = setTimeout(function () { self.tersalin = false; }, 2000);
                    }, function () {
                        toast('Gagal menyalin. Pilih teksnya lalu tekan Ctrl+C.', 'galat');
                    });
                },
            };
        });

        /* (F8) Daftar centang + "Pilih semua" + penghitung. Pada pembungkus: x-data="daftarCentang" data-satuan="siswa dipilih"
         * @change="hitungCentang()"; butir = input[type=checkbox][data-centang]; kotak "Pilih semua" (tanpa data-centang)
         * @change="pilihSemuaCentang($event)"; penghitung x-text="teksCentang()"; tombol kirim :disabled="belumDicentang()". */
        Alpine.data('daftarCentang', function () {
            var akar = null;
            var butir = function () { return akar ? akar.querySelectorAll('input[type="checkbox"][data-centang]') : []; };
            return {
                jumlah: 0,
                init: function () { akar = this.$el; this.hitungCentang(); },
                pilihSemuaCentang: function (e) {
                    var on = !!(e && e.target && e.target.checked);
                    Array.prototype.forEach.call(butir(), function (c) { if (!c.disabled) { c.checked = on; } });
                    // Tanpa event = tombol "Batal pilih" di bilah: kotak "Pilih semua" ikut dikosongkan.
                    if (!e && akar) { Array.prototype.forEach.call(akar.querySelectorAll('input[type="checkbox"]:not([data-centang])'), function (c) { c.checked = false; }); }
                    this.hitungCentang();
                },
                hitungCentang: function () {
                    this.jumlah = Array.prototype.filter.call(butir(), function (c) { return c.checked; }).length;
                },
                belumDicentang: function () { return this.jumlah === 0; },
                teksCentang: function () { return this.jumlah + ' ' + ((akar && akar.getAttribute('data-satuan')) || 'dipilih'); },
            };
        });

        /* (F8) Pilih berkas: tampilkan nama berkas yang dipilih. Pada pembungkus label+isian file: x-data="pilihBerkas"
         * data-kosong="Belum ada berkas dipilih"; isian file @change="catatBerkas($event)"; teks x-text="namaBerkasTerpilih()". */
        Alpine.data('pilihBerkas', function () {
            var kosong = 'Belum ada berkas dipilih';
            return {
                nama: '',
                init: function () { kosong = this.$el.getAttribute('data-kosong') || kosong; },
                catatBerkas: function (e) {
                    var berkas = (e && e.target && e.target.files) ? e.target.files : [];
                    this.nama = Array.prototype.map.call(berkas, function (f) { return f.name; }).join(', ');
                },
                namaBerkasTerpilih: function () { return this.nama !== '' ? this.nama : kosong; },
            };
        });
    });

    /* (F8) Tautan Kembali: <a href="URL cadangan" data-kembali> — kembali ke halaman sebelumnya bila datang dari situs ini,
     * selain itu ikuti href (pengganti onclick="history.back()" yang ditolak CSP). */
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[data-kembali]') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
        var dariSini = false;
        try { dariSini = document.referrer !== '' && new URL(document.referrer).origin === window.location.origin; } catch (err) { dariSini = false; }
        if (dariSini && window.history.length > 1) {
            e.preventDefault();
            window.history.back();
        }
    });
})();
