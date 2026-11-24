/**
 * Pencarian LANGSUNG SAAT MENGETIK di panel admin (permintaan klien 2026-11-22: "ketika mengetik nama langsung muncul tanpa klik tombol
 * search, minimal 2–3 huruf"). Berlaku untuk kotak <input data-cari-langsung> di dalam <form method="get"> (bilah alat ui_crud_alat
 * memasangnya otomatis).
 *
 * Cara kerja: berhenti mengetik ±0,35 dtk → halaman yang SAMA diminta lagi dengan isian formulir itu (persis seperti menekan Enter, jadi
 * aturan cari & saringan di server tak berubah) → hasilnya menggantikan daftar TANPA memuat ulang halaman. Kotak cari sendiri tidak
 * pernah disentuh, jadi kursor, papan ketik HP, dan ketikan yang sedang berjalan aman. Yang diganti: semua isi bilah alat di sekitar kotak
 * (tombol Reset, tautan Export, formulir Hapus semua — supaya ikut pencarian yang baru) dan semua yang ada SESUDAH bilah alat (daftar,
 * nomor halaman, dialog); bila wilayahnya disebut sendiri: seluruh isi wilayah itu (mis. angka jumlah di kepala kartu). Elemen Alpine yang
 * baru dihidupkan sendiri oleh Alpine (MutationObserver). JANGAN memasukkan komponen yang memasang pendengar window/document di init()
 * (mis. pklUnduh, suratWa) ke dalam wilayah — tiap kali diganti pendengarnya akan bertambah.
 * Alamat di bilah peramban ikut berubah (muat ulang / kembali / halaman 2 tetap memakai kata cari yang sama).
 *
 *   data-cari-langsung="2"      jumlah huruf minimal (bawaan 2); kosong = tampilkan semua lagi; Enter = cari saat itu juga
 *   data-cari-wilayah="#id"     bagian yang diganti (seluruh isinya); bawaan = bilah alat (.alat-crud) + semua sesudahnya
 *                               <details id="…"> di dalamnya tetap terbuka/tertutup seperti pilihan pengguna
 *   data-cari-ikut + id         di elemen mana pun di LUAR wilayah (mis. tombol Excel di kepala halaman): ikut diperbarui
 * Sesudah diganti: peristiwa "cari-selesai" di document (detail.wilayah) untuk halaman yang perlu menyesuaikan diri.
 * Cadangan: tanpa JavaScript Enter tetap mengirim formulir; sesi habis / struktur halaman tak cocok → pindah halaman biasa.
 */
(function () {
    'use strict';

    var TUNDA = 350;          // jeda sesudah huruf terakhir (ms)
    var TUNDA_TANDA = 200;    // tanda "memuat" baru tampil bila server lebih lambat dari ini
    var PILIH = 'input[data-cari-langsung]';
    var catatan = new WeakMap(); // kotak cari → { timer, tanda, minta, terakhir }

    function catatanKotak(kotak) {
        if (!catatan.has(kotak)) { catatan.set(kotak, { timer: 0, tanda: 0, minta: null, terakhir: kotak.defaultValue.trim() }); }
        return catatan.get(kotak);
    }
    function wilayahCari(kotak) {
        var sel = kotak.getAttribute('data-cari-wilayah');
        if (sel) {
            var w = kotak.ownerDocument.querySelector(sel);
            return w && w.contains(kotak) ? w : null;
        }
        var alat = kotak.closest('.alat-crud');
        return alat ? alat.parentElement : null;
    }
    function bungkusKotak(kotak) { return kotak.closest('label') || kotak; }

    /* Jalur dari pembungkus kotak cari (label) naik sampai wilayah: [label, …, wilayah]; null bila tak sampai. */
    function jalurKotak(kotak, wilayah) {
        var out = [];
        for (var el = bungkusKotak(kotak); el; el = el.parentElement) {
            out.push(el);
            if (el === wilayah) { return out; }
        }
        return null;
    }

    function alamatCari(kotak) {
        var form = kotak.form;
        var u = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
        u.hash = '';
        u.search = new URLSearchParams(new FormData(form)).toString();
        return u;
    }

    /* Ganti saudara-saudara `anak` di dalam `induk` dengan saudara `anakBaru`. Skrip halaman dibiarkan (sudah berjalan). */
    function gantiSaudaraCari(induk, anak, anakBaru, hanyaSesudah) {
        var buang = function (dari, arah) {
            for (var n = dari[arah]; n;) {
                var lanjut = n[arah];
                if (n.nodeName !== 'SCRIPT') { induk.removeChild(n); }
                n = lanjut;
            }
        };
        var salin = function (n) { return document.importNode(n, true); };
        var n, acuan;
        if (!hanyaSesudah) {
            buang(anak, 'previousSibling');
            for (n = anakBaru.parentNode.firstChild; n && n !== anakBaru; n = n.nextSibling) {
                if (n.nodeName !== 'SCRIPT') { induk.insertBefore(salin(n), anak); }
            }
        }
        buang(anak, 'nextSibling');
        acuan = anak.nextSibling; // skrip yang dibiarkan (bila ada) tetap di ujung
        for (n = anakBaru.nextSibling; n; n = n.nextSibling) {
            if (n.nodeName !== 'SCRIPT') { induk.insertBefore(salin(n), acuan); }
        }
    }

    /* Pasang hasil dari dokumen baru. false = strukturnya tak cocok (pemanggil pindah halaman biasa). */
    function pasangHasil(kotak, dok) {
        var semua = Array.prototype.slice.call(document.querySelectorAll(PILIH));
        var kotakBaru = dok.querySelectorAll(PILIH)[semua.indexOf(kotak)];
        var wLama = wilayahCari(kotak);
        var wBaru = kotakBaru ? wilayahCari(kotakBaru) : null;
        var a = wLama ? jalurKotak(kotak, wLama) : null;
        var b = wBaru ? jalurKotak(kotakBaru, wBaru) : null;
        if (!a || !b || a.length !== b.length) { return false; }
        for (var i = 0; i < a.length; i++) { if (a[i].nodeName !== b[i].nodeName) { return false; } }

        // Panel saringan HP yang sedang dibuka pengguna tetap terbuka; fokus di luar kotak cari dikembalikan bila elemennya masih ada.
        var panel = wLama.querySelector('.alat-lentur-saringan');
        var buka = panel ? panel.getAttribute('data-buka') : null;
        var lipatan = Array.prototype.map.call(wLama.querySelectorAll('details[id]'), function (d) { return [d.id, d.open]; });
        var fokus = document.activeElement;
        var idFokus = fokus && fokus !== kotak && fokus.id && wLama.contains(fokus) ? fokus.id : '';

        var hanyaSesudah = !kotak.hasAttribute('data-cari-wilayah');
        for (var t = 1; t < a.length; t++) { gantiSaudaraCari(a[t], a[t - 1], b[t - 1], hanyaSesudah && a[t] === wLama); }
        // Tombol di luar wilayah yang ikut kata cari (mis. Unduh Excel di kepala halaman): id sama → diganti versi barunya.
        Array.prototype.forEach.call(document.querySelectorAll('[data-cari-ikut][id]'), function (el) {
            var baru = wLama.contains(el) ? null : dok.getElementById(el.id);
            if (baru) { el.replaceWith(document.importNode(baru, true)); }
        });
        lipatan.forEach(function (l) {
            var d = document.getElementById(l[0]);
            if (d && wLama.contains(d)) { d.open = l[1]; }
        });

        panel = wLama.querySelector('.alat-lentur-saringan');
        if (panel && buka !== null) {
            panel.setAttribute('data-buka', buka);
            var tombol = wLama.querySelector('[data-alih-saringan]');
            if (tombol) { tombol.setAttribute('aria-expanded', buka); }
        }
        if (idFokus && document.activeElement !== kotak) {
            var lagi = document.getElementById(idFokus);
            if (lagi) { lagi.focus({ preventScroll: true }); }
        }
        return true;
    }

    function tandaMuat(kotak, nyala) {
        var k = catatanKotak(kotak);
        clearTimeout(k.tanda);
        var w = wilayahCari(kotak);
        var ubah = function () {
            kotak.parentElement.toggleAttribute('data-memuat', nyala);
            if (w) {
                w.toggleAttribute('data-cari-memuat', nyala);
                if (nyala) { w.setAttribute('aria-busy', 'true'); } else { w.removeAttribute('aria-busy'); }
            }
        };
        if (nyala) { k.tanda = setTimeout(ubah, TUNDA_TANDA); } else { ubah(); }
    }

    /* Pembaca layar diberi tahu hasilnya (tanpa memindahkan fokus). */
    function kabariPembaca(wilayah) {
        var el = document.getElementById('cari-kabar');
        if (!el) {
            el = document.createElement('p');
            el.id = 'cari-kabar';
            el.className = 'sr-only';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        var info = wilayah.querySelector('.paginasi-info');
        el.textContent = wilayah.querySelector('.kosong') ? 'Tidak ada data yang cocok.' : (info ? info.textContent.replace(/\s+/g, ' ').trim() : 'Daftar diperbarui.');
    }

    function jalankanCari(kotak) {
        var k = catatanKotak(kotak);
        clearTimeout(k.timer);
        if (k.minta) { k.minta.abort(); }
        var u = alamatCari(kotak);
        var minta = new AbortController();
        k.minta = minta;
        k.terakhir = kotak.value.trim();
        tandaMuat(kotak, true);
        fetch(u.href, { credentials: 'same-origin', signal: minta.signal, headers: { Accept: 'text/html' } }).then(function (r) {
            // Sesi habis (dialihkan ke halaman masuk) atau galat server → biarkan peramban membuka halamannya seperti biasa.
            if (!r.ok || new URL(r.url).pathname !== u.pathname) { window.location.assign(u.href); return null; }
            return r.text();
        }).then(function (teks) {
            if (teks === null || k.minta !== minta) { return; }
            k.minta = null;
            var dok = new DOMParser().parseFromString(teks, 'text/html');
            if (!pasangHasil(kotak, dok)) { window.location.assign(u.href); return; }
            tandaMuat(kotak, false);
            window.history.replaceState(window.history.state, '', u.href);
            var w = wilayahCari(kotak);
            kabariPembaca(w);
            document.dispatchEvent(new CustomEvent('cari-selesai', { detail: { wilayah: w, kotak: kotak } }));
        }).catch(function (e) {
            if (e && e.name === 'AbortError') { return; }
            if (k.minta === minta) { k.minta = null; }
            tandaMuat(kotak, false);
            k.terakhir = null; // boleh dicoba lagi dengan kata yang sama
            if (window.Ui && window.Ui.toast) { window.Ui.toast('Pencarian gagal dimuat. Periksa koneksi internet, lalu ketik lagi.', 'galat'); }
        });
    }

    function kotakDariAcara(e) {
        var el = e.target;
        return el && el.matches && el.matches(PILIH) && el.form ? el : null;
    }

    document.addEventListener('input', function (e) {
        var kotak = kotakDariAcara(e);
        if (!kotak || e.isComposing) { return; }
        var k = catatanKotak(kotak);
        clearTimeout(k.timer);
        var kata = kotak.value.trim();
        var min = parseInt(kotak.getAttribute('data-cari-langsung'), 10) || 2;
        if (kata === k.terakhir) { // diketik lalu dihapus lagi: hasil yang tampil sudah benar, permintaan yang berjalan tetap dipakai
            return;
        }
        if (kata.length > 0 && kata.length < min) { return; }
        k.timer = setTimeout(function () { jalankanCari(kotak); }, TUNDA);
    });
    // Selesai menyusun huruf (papan ketik prediktif) → anggap satu ketikan.
    document.addEventListener('compositionend', function (e) {
        var kotak = kotakDariAcara(e);
        if (kotak) { kotak.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    // Enter = cari sekarang (tanpa memuat ulang halaman).
    document.addEventListener('keydown', function (e) {
        var kotak = e.key === 'Enter' && !e.isComposing ? kotakDariAcara(e) : null;
        if (!kotak) { return; }
        e.preventDefault();
        jalankanCari(kotak);
    });
})();
