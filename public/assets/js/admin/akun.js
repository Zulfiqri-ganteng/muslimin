/**
 * Halaman admin Kelola Akun Staf — komponen Alpine `akunPage`:
 * dialog tambah/ubah, dialog konfirmasi (reset sandi / nonaktifkan / aktifkan),
 * dan dialog sandi sementara yang hanya tampil sekali.
 *
 * Data dari server dibawa lewat atribut data-* pada elemen akar:
 *   data-base, data-login, data-sekolah, data-peran (kode → label),
 *   data-buka (isian yang gagal disimpan), data-baru (sandi sementara).
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('akunPage', function () {
        return {
            // dialog tambah/ubah
            formTerbuka: false,
            mode: 'tambah',
            urlForm: '',
            peranTerkunci: false,
            form: { full_name: '', email: '', phone: '', role: 'operator' },

            // dialog konfirmasi
            tanyaTerbuka: false,
            tanyaData: { judul: '', pesan: '', tombol: '', bahaya: false, url: '', aktif: '' },

            // dialog sandi sementara
            sandi: null,
            tersalin: '',

            // data dari server
            base: '',
            urlLogin: '',
            sekolah: '',
            labelPeran: {},

            init: function () {
                var akar = this.$el;
                this.base = akar.getAttribute('data-base') || '';
                this.urlLogin = akar.getAttribute('data-login') || '';
                this.sekolah = akar.getAttribute('data-sekolah') || '';
                this.labelPeran = window.readJson(akar, 'peran', {});
                this.sandi = window.readJson(akar, 'baru', null);

                // Simpan gagal (mis. email dobel): buka lagi dialog dengan isian tadi.
                var lama = window.readJson(akar, 'buka', null);
                if (lama && !this.sandi) {
                    this.bukaForm(lama.id > 0 ? 'ubah' : 'tambah', lama.id, {
                        full_name: lama.full_name || '',
                        email: lama.email || '',
                        phone: lama.phone || '',
                        role: lama.role || 'operator'
                    }, false);
                }
            },

            /** Baca data satu akun dari baris/kartu terdekat. */
            baris: function (el) {
                var kontainer = el.closest('[data-akun]');
                try { return JSON.parse(kontainer.getAttribute('data-akun')); } catch (e) { return {}; }
            },

            tambah: function () {
                this.bukaForm('tambah', 0, { full_name: '', email: '', phone: '', role: 'operator' }, false);
            },

            ubah: function (r) {
                this.bukaForm('ubah', r.id, {
                    full_name: r.full_name, email: r.email, phone: r.phone, role: r.role
                }, !!r.saya);
            },

            bukaForm: function (mode, id, isi, terkunci) {
                var self = this;
                this.mode = mode;
                this.form = isi;
                this.peranTerkunci = terkunci;
                this.urlForm = mode === 'tambah' ? this.base : this.base + '/' + id;
                this.formTerbuka = true;
                this.$nextTick(function () { if (self.$refs.nama) { self.$refs.nama.focus(); } });
            },

            /** Siapkan dialog konfirmasi yang menjelaskan AKIBAT tindakan dengan jelas. */
            tanya: function (jenis, r) {
                var nama = r.full_name;
                var d = { judul: '', pesan: '', tombol: '', bahaya: false, url: '', aktif: '' };
                if (jenis === 'reset') {
                    d.judul = 'Reset kata sandi ' + nama + '?';
                    d.pesan = 'Sandi lama langsung tidak berlaku dan aplikasi Android miliknya akan keluar otomatis. Sistem membuat sandi sementara baru (tampil sekali) yang wajib diganti saat login berikutnya.';
                    d.tombol = 'Ya, reset sandi';
                    d.url = this.base + '/' + r.id + '/reset-sandi';
                } else if (jenis === 'nonaktif') {
                    d.judul = 'Nonaktifkan ' + nama + '?';
                    d.pesan = 'Akun ini langsung tidak bisa dipakai masuk — bahkan bila sedang login. Data dan riwayatnya tidak dihapus, dan bisa diaktifkan kembali kapan saja.';
                    d.tombol = 'Ya, nonaktifkan';
                    d.bahaya = true;
                    d.url = this.base + '/' + r.id + '/status';
                    d.aktif = '0';
                } else {
                    d.judul = 'Aktifkan kembali ' + nama + '?';
                    d.pesan = 'Akun ini bisa dipakai masuk lagi dengan sandi terakhirnya. Bila sandinya lupa, gunakan Reset sandi.';
                    d.tombol = 'Ya, aktifkan';
                    d.url = this.base + '/' + r.id + '/status';
                    d.aktif = '1';
                }
                this.tanyaData = d;
                this.tanyaTerbuka = true;
            },

            /** Esc menutup dialog biasa — TIDAK menutup dialog sandi (supaya tak hilang sebelum dicatat). */
            tutupDialog: function () {
                this.formTerbuka = false;
                this.tanyaTerbuka = false;
            },

            /** Teks siap tempel ke WhatsApp. */
            pesanLengkap: function () {
                if (!this.sandi) { return ''; }
                var s = this.sandi;
                return 'Halo ' + s.nama + ', akun ' + this.sekolah + ' Anda sudah siap.\n\n'
                    + 'Alamat login: ' + this.urlLogin + '\n'
                    + 'Email: ' + s.email + '\n'
                    + 'Kata sandi sementara: ' + s.sandi + '\n'
                    + 'Peran: ' + s.peran + '\n\n'
                    + 'Setelah login Anda diminta mengganti kata sandi. Mohon segera diganti dan jangan dibagikan ke siapa pun.';
            },

            salin: function (teks, kunci) {
                var self = this;
                var tandai = function () {
                    self.tersalin = kunci;
                    setTimeout(function () { if (self.tersalin === kunci) { self.tersalin = ''; } }, 2200);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(teks).then(tandai, function () { self.salinLama(teks); tandai(); });
                } else {
                    this.salinLama(teks);
                    tandai();
                }
            },

            /** Cadangan untuk http:// (clipboard API hanya ada di konteks aman). */
            salinLama: function (teks) {
                var ta = document.createElement('textarea');
                ta.value = teks;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) { /* biarkan */ }
                document.body.removeChild(ta);
            }
        };
    });
});
