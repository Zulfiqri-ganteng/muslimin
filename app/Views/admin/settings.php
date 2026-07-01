<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'settings',
    'helpTitle' => 'Pengaturan Sekolah & Tampilan Publik',
    'helpBody'  => '<p>Identitas sekolah di sini dipakai pada kop surat, PDF, dan halaman publik. Di bagian bawah ada saklar tampilan publik: <b>Buka pengisian form</b>, <b>Tampilkan jadwal ke publik</b>, dan <b>Tampilkan absensi ke publik</b>. Matikan salah satu untuk menyembunyikan halaman terkait dari pengunjung situs.</p>',
]) ?>

<form method="post" action="<?= site_url('admin/settings') ?>" enctype="multipart/form-data" class="max-w-3xl">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Identitas Sekolah</h2>
            <p class="text-sm text-slate-400">Data ini dipakai pada kop surat, PDF, dan halaman form guru.</p>
        </div>
        <div class="p-6 space-y-5">
            <!-- Logo -->
            <div class="flex items-center gap-5">
                <div class="h-20 w-20 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <?php if (! empty($setting['logo'])): ?>
                        <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" class="h-full w-full object-contain" alt="Logo">
                    <?php else: ?>
                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="lbl">Logo Sekolah</label>
                    <input type="file" name="logo" accept="image/*" class="text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                    <p class="text-xs text-slate-400 mt-1">PNG/JPG, disarankan rasio persegi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <label class="lbl">Jenjang</label>
                    <input type="text" name="school_level" value="<?= esc($setting['school_level']) ?>" class="inp" placeholder="SMK / SMA / SMP">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Nama Sekolah <span class="text-red-500">*</span></label>
                    <input type="text" name="school_name" value="<?= esc($setting['school_name']) ?>" required class="inp" placeholder="Nama sekolah">
                </div>
            </div>

            <div>
                <label class="lbl">Alamat</label>
                <textarea name="address" rows="2" class="inp" placeholder="Alamat lengkap sekolah"><?= esc($setting['address']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="lbl">Kota</label>
                    <input type="text" name="city" value="<?= esc($setting['city']) ?>" class="inp" placeholder="Bekasi">
                </div>
                <div>
                    <label class="lbl">Telepon</label>
                    <input type="text" name="phone" value="<?= esc($setting['phone']) ?>" class="inp">
                </div>
                <div>
                    <label class="lbl">Email</label>
                    <input type="text" name="email" value="<?= esc($setting['email']) ?>" class="inp">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Website</label>
                    <input type="text" name="website" value="<?= esc($setting['website']) ?>" class="inp" placeholder="zulfiqri.it.com">
                </div>
                <div>
                    <label class="lbl">Tahun Pelajaran</label>
                    <input type="text" name="academic_year" value="<?= esc($setting['academic_year']) ?>" class="inp" placeholder="2026/2027">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-5">
        <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Kepala Sekolah &amp; Form</h2></div>
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Nama Kepala Sekolah</label>
                    <input type="text" name="headmaster_name" value="<?= esc($setting['headmaster_name']) ?>" class="inp" placeholder="Nama & gelar">
                </div>
                <div>
                    <label class="lbl">NIP Kepala Sekolah</label>
                    <input type="text" name="headmaster_nip" value="<?= esc($setting['headmaster_nip']) ?>" class="inp">
                </div>
            </div>
            <div>
                <label class="lbl">Teks Pengantar Form</label>
                <textarea name="form_intro" rows="2" class="inp" placeholder="Kalimat pengantar di atas form guru"><?= esc($setting['form_intro']) ?></textarea>
            </div>
            <label class="flex items-center gap-3 cursor-pointer rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
                <input type="checkbox" name="form_open" value="1" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= $setting['form_open'] ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Buka pengisian form</span>
                    <span class="block text-xs text-slate-400">Jika dimatikan, guru tidak bisa mengisi form (ditampilkan halaman "ditutup").</span>
                </span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
                <input type="checkbox" name="jadwal_publik" value="1" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= ($setting['jadwal_publik'] ?? 1) ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Tampilkan jadwal ke publik</span>
                    <span class="block text-xs text-slate-400">Jika dimatikan, halaman Jadwal Kelas/Guru di situs publik disembunyikan.</span>
                </span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
                <input type="checkbox" name="absensi_publik" value="1" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= ($setting['absensi_publik'] ?? 1) ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Tampilkan absensi ke publik</span>
                    <span class="block text-xs text-slate-400">Jika dimatikan, halaman Absensi Guru di situs publik disembunyikan (pengelolaan di admin tetap jalan).</span>
                </span>
            </label>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <button class="inline-flex items-center gap-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold px-7 py-3 rounded-xl transition active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Simpan Pengaturan
        </button>
    </div>
</form>

<style type="text/tailwindcss">
    .lbl { @apply block text-sm font-medium text-slate-600 mb-1.5; }
    .inp { @apply w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition; }
</style>

<?= $this->endSection() ?>
