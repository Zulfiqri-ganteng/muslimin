<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 max-w-4xl">
    <!-- Kartu Profil -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center h-fit">
        <div class="mx-auto h-24 w-24 rounded-full overflow-hidden bg-brand-100 ring-4 ring-brand-50">
            <?php if (! empty($admin['photo'])): ?>
                <img src="<?= base_url('uploads/' . esc($admin['photo'])) ?>" class="h-full w-full object-cover" alt="Foto">
            <?php else: ?>
                <span class="flex h-full w-full items-center justify-center text-brand-700 font-extrabold text-3xl"><?= strtoupper(substr($admin['full_name'],0,1)) ?></span>
            <?php endif; ?>
        </div>
        <h2 class="mt-4 font-bold text-lg text-slate-800"><?= esc($admin['full_name']) ?></h2>
        <p class="text-sm text-slate-400">@<?= esc($admin['username']) ?></p>
        <span class="mt-2 inline-block text-xs font-semibold px-3 py-1 rounded-full bg-brand-50 text-brand-700 capitalize"><?= esc($admin['role']) ?></span>
        <dl class="mt-5 text-left text-sm space-y-2 border-t border-slate-100 pt-4">
            <div class="flex justify-between"><dt class="text-slate-400">Email</dt><dd class="font-medium text-slate-600 truncate ml-2"><?= esc($admin['email']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">No. HP</dt><dd class="font-medium text-slate-600"><?= esc($admin['phone'] ?: '-') ?></dd></div>
        </dl>
    </div>

    <!-- Form Edit + Password -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Edit Profil -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Edit Profil</h2></div>
            <form method="post" action="<?= site_url('admin/profile') ?>" enctype="multipart/form-data" class="p-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl">Nama Lengkap</label>
                    <input type="text" name="full_name" value="<?= esc($admin['full_name']) ?>" required class="inp">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">Username</label>
                        <input type="text" name="username" value="<?= esc($admin['username']) ?>" required class="inp">
                    </div>
                    <div>
                        <label class="lbl">No. HP</label>
                        <input type="text" name="phone" value="<?= esc($admin['phone']) ?>" class="inp">
                    </div>
                </div>
                <div>
                    <label class="lbl">Email</label>
                    <input type="email" name="email" value="<?= esc($admin['email']) ?>" required class="inp">
                </div>
                <div>
                    <label class="lbl">Foto Profil</label>
                    <input type="file" name="photo" accept="image/*" class="text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                </div>
                <div class="flex justify-end">
                    <button class="bg-brand-700 hover:bg-brand-800 text-white font-semibold px-6 py-2.5 rounded-lg transition active:scale-95">Simpan Profil</button>
                </div>
            </form>
        </div>

        <!-- Ganti Password -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Ganti Password</h2></div>
            <form method="post" action="<?= site_url('admin/profile/password') ?>" class="p-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl">Password Lama</label>
                    <input type="password" name="old_password" required class="inp" placeholder="••••••••">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">Password Baru</label>
                        <input type="password" name="new_password" required class="inp" placeholder="Min. 6 karakter">
                    </div>
                    <div>
                        <label class="lbl">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" required class="inp">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button class="bg-slate-700 hover:bg-slate-800 text-white font-semibold px-6 py-2.5 rounded-lg transition active:scale-95">Ganti Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style type="text/tailwindcss">
    .lbl { @apply block text-sm font-medium text-slate-600 mb-1.5; }
    .inp { @apply w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition; }
</style>

<?= $this->endSection() ?>
