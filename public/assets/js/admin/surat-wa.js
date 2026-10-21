/**
 * Kabar WhatsApp "surat siap diambil" (semua jenis surat) — komponen Alpine `suratWa`. Pola sama dengan kabar Surat Izin PKL
 * (pkl-unduh.js → pklWa): daftar siswa + tombol hijau yang membuka WhatsApp staf dengan pesan siap kirim (wa.me); staf tinggal
 * menekan Kirim. Server: Admin\Surat::wa / waJenis / tandaiWa, aturan di Libraries\SuratWa.
 *
 * Dibuka lewat:
 *   - event 'surat-wa' {detail: {id}} (satu surat) atau {detail: {jenis}} (kabar massal: yang belum dikabari);
 *   - otomatis setelah unduhan selesai (app.js mengirim 'surat:unduh-selesai' untuk form ber-data-surat-wa / data-surat-wa-jenis):
 *     kotak hanya muncul bila masih ada siswa bernomor HP yang belum dikabari; selain itu halaman dimuat ulang (nomor & status segar).
 * (F9) Tombol pemicu tanpa JS sebaris (siap CSP): data-kabari-surat="ID" atau data-kabari-surat-jenis="jenis" → event 'surat-wa'
 *     (dulu @click="window.dispatchEvent(…)"; nama atribut sengaja beda dari penanda form data-surat-wa milik app.js).
 */
document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-kabari-surat], [data-kabari-surat-jenis]') : null;
    if (!b) { return; }
    var d = b.hasAttribute('data-kabari-surat') ? { id: b.getAttribute('data-kabari-surat') } : { jenis: b.getAttribute('data-kabari-surat-jenis') };
    window.dispatchEvent(new CustomEvent('surat-wa', { detail: d }));
});

document.addEventListener('alpine:init', function () {
    'use strict';

    function tokenCsrf(nama) {
        var i = document.querySelector('input[name="' + nama + '"]');
        return i ? i.value : '';
    }
    function perbaruiCsrf(nama, nilai) {
        if (!nilai) { return; }
        document.querySelectorAll('input[name="' + nama + '"]').forEach(function (i) { i.value = nilai; });
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) { m.setAttribute('content', nilai); }
    }
    function ambilJson(url, opsi) {
        return fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, opsi || {}))
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, message: 'Balasan server tidak terbaca.' }; });
            });
    }

    Alpine.data('suratWa', function () {
        return {
            cfg: {},
            buka: false,
            memuat: false,
            galat: '',
            daftar: [],
            tersalin: '',
            berubah: false, // ada unduhan baru / ada yang ditandai → muat ulang halaman saat ditutup

            init: function () {
                var self = this;
                this.cfg = JSON.parse(this.$el.dataset.config || '{}');
                window.addEventListener('surat-wa', function (e) { self.muat(e.detail || {}, false); });
                window.addEventListener('surat:unduh-selesai', function (e) { self.muat(e.detail || {}, true); });
            },

            alamat: function (d) {
                return d.id ? this.cfg.urlSurat + '/' + encodeURIComponent(d.id) + '/wa' : this.cfg.urlJenis + '?jenis=' + encodeURIComponent(d.jenis || '');
            },

            muat: function (d, dariUnduhan) {
                var self = this;
                if (!d.id && !d.jenis) { return; }
                if (dariUnduhan) { this.berubah = true; }
                else { this.daftar = []; this.galat = ''; this.buka = true; this.memuat = true; }
                ambilJson(this.alamat(d)).then(function (j) {
                    self.memuat = false;
                    if (dariUnduhan) {
                        var perlu = j.ok && j.data ? j.data.filter(function (r) { return r.url && !r.dikabari_at; }) : [];
                        if (perlu.length) { self.daftar = j.data; self.galat = ''; self.buka = true; } else { window.location.reload(); }
                        return;
                    }
                    if (!j.ok) { self.galat = j.message || 'Gagal memuat daftar.'; return; }
                    self.daftar = j.data || [];
                    if (!self.daftar.length) { self.galat = d.id ? 'Unduh suratnya dulu, baru siswa bisa dikabari.' : 'Semua siswa pada surat yang sudah diunduh sudah dikabari.'; }
                }).catch(function () {
                    if (dariUnduhan) { window.location.reload(); return; }
                    self.memuat = false; self.galat = 'Tidak bisa menghubungi server.';
                });
            },

            tutup: function () {
                this.buka = false;
                if (this.berubah) { window.location.reload(); }
            },

            belumDikabari: function () {
                return this.daftar.filter(function (r) { return !r.dikabari_at && r.url; }).length;
            },

            kunci: function (r) { return r.surat_id + '-' + r.siswa_id; },

            waktu: function (s) {
                if (!s) { return ''; }
                var d = new Date(String(s).replace(' ', 'T'));
                return isNaN(d) ? s : ('0' + d.getDate()).slice(-2) + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            },

            // Tautan WhatsApp dibuka oleh <a target="_blank">; di sini hanya mencatat "sudah dikabari".
            tandai: function (r) {
                var self = this;
                var nama = this.cfg.csrfName, token = tokenCsrf(nama) || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var body = new URLSearchParams();
                body.set(nama, token);
                ambilJson(this.cfg.urlSurat + '/' + r.surat_id + '/wa/' + r.siswa_id + '/tandai', {
                    method: 'POST', body: body,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                }).then(function (j) {
                    perbaruiCsrf(nama, j.csrf);
                    if (j.ok) { r.dikabari_at = j.waktu; r.dikabari_oleh = j.oleh; self.berubah = true; }
                });
            },

            salin: function (r) {
                var self = this;
                if (!navigator.clipboard) { return; }
                navigator.clipboard.writeText(r.pesan).then(function () {
                    self.tersalin = self.kunci(r);
                    setTimeout(function () { if (self.tersalin === self.kunci(r)) { self.tersalin = ''; } }, 1800);
                });
            },
        };
    });
});
