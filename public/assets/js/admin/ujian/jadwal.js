/**
 * Ujian → tab Jadwal Ujian — rombak tampilan F7: dulu x-data objek bermetode sebaris ({ open, importOpen, mode, form, openAdd(),
 * openEdit(r) }) + @click="openEdit({…JSON…})" + modal Import gaya lama. Kini perluasan masterList (kit CRUD): tombol Ubah lewat
 * delegasi [data-ubah] (ui_crud_aksi / kartu HP), dialog ui_crud_form_*, dialog Import ui_crud_impor → siap Alpine versi CSP.
 * Beda dengan master: tambah & ubah dikirim ke alamat yang SAMA (data-base = …/jadwal); id ikut lewat isian tersembunyi.
 */
document.addEventListener('alpine:init', function () {
    'use strict';

    Alpine.data('ujianJadwal', function () {
        return window.masterList({
            openEdit: function (r) {
                this.mode = 'edit';
                this.form = this.mapEdit(r);
                this.actionUrl = this.base;
                this.open = true;
                this.fokusDialog();
            },
        });
    });
});
