/**
 * Ujian → tab Ujian Susulan — rombak tampilan F7: dulu x-data objek bermetode sebaris (Array.from, fungsi di isian,
 * @click="bukaSatu({…JSON…})", :action berisi penyambungan teks) + modal buatan sendiri. Kini komponen "susulanUjian":
 *   - centang baris (x-model="pilih") + "pilih semua" (semua($event));
 *   - tombol baris lewat delegasi: [data-jadwalkan] / [data-ubah-status] dengan data-row (JSON) → tanpa JSON di isian;
 *   - dialog kit "jadwal-susulan" (satuan & massal memakai form yang sama) dan "status-susulan" (Ui.bukaDialog).
 * Konfigurasi: data-base (URL jenis ujian), data-hari-ini (Y-m-d server). Siap Alpine versi CSP.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('susulanUjian', function () {
        var akar = null; // elemen akar (closure, bukan data reaktif)
        var formKosong = function () { return { tanggal_susulan: '', jam_susulan: '', ruang_susulan: '', pengawas_guru_id: '' }; };

        return {
            pilih: [],
            form: formKosong(),
            statusForm: { id: '', nama: '', status: '', tanggal_pelaksanaan: '' },
            base: '',
            hariIni: '',

            init: function () {
                var self = this;
                akar = this.$el;
                this.base = akar.getAttribute('data-base') || '';
                this.hariIni = akar.getAttribute('data-hari-ini') || '';
                this.statusForm.tanggal_pelaksanaan = this.hariIni;
                akar.addEventListener('click', function (e) {
                    var b = e.target.closest ? e.target.closest('[data-jadwalkan], [data-ubah-status]') : null;
                    if (!b || !akar.contains(b)) { return; }
                    var r = {};
                    try { r = JSON.parse(b.getAttribute('data-row') || '{}'); } catch (err) { r = {}; }
                    if (b.hasAttribute('data-jadwalkan')) { self.bukaSatu(r); } else { self.bukaStatus(r); }
                });
            },

            // "Pilih semua" — hanya kotak centang baris (bukan isian tersembunyi ids[] di dialog).
            semua: function (e) {
                this.pilih = e.target.checked && akar
                    ? Array.prototype.map.call(akar.querySelectorAll('input[type="checkbox"][name="ids[]"]'), function (i) { return i.value; })
                    : [];
            },
            jumlahPilih: function () { return this.pilih.length; },
            adaPilih: function () { return this.pilih.length > 0; },
            tanpaPilih: function () { return this.pilih.length === 0; },

            bukaSatu: function (r) {
                this.pilih = [String(r.id)];
                this.form = {
                    tanggal_susulan: r.tanggal_susulan || '',
                    jam_susulan: r.jam_susulan || '',
                    ruang_susulan: r.ruang_susulan || '',
                    pengawas_guru_id: r.pengawas_guru_id || '',
                };
                window.Ui.bukaDialog('jadwal-susulan');
            },
            bukaMassal: function () {
                if (this.pilih.length === 0) { return; }
                this.form = formKosong();
                window.Ui.bukaDialog('jadwal-susulan');
            },
            bukaStatus: function (r) {
                this.statusForm = { id: String(r.id), nama: r.nama || '', status: r.status || '', tanggal_pelaksanaan: r.tanggal_pelaksanaan || this.hariIni };
                window.Ui.bukaDialog('status-susulan');
            },
            statusUrl: function () { return this.base + '/susulan/' + this.statusForm.id + '/status'; },
            statusSelesai: function () { return this.statusForm.status === 'selesai'; },
        };
    });
});
