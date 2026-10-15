<?php
/**
 * Profil Saya — kartu identitas + Edit profil + Ganti password. Rombak tampilan F10: kartu kit, ui_peringatan (wajib ganti
 * sandi), unggah foto = kit pilihBerkas, tombol kit ber-ikon; blok gaya sebaris (.lbl/.inp lama) dibuang — kelas kit sudah ada.
 *
 * @var array $admin baris akun yang sedang masuk
 */
$kepala = static fn (string $judul): string => '<div class="kartu-kepala"><h2 class="kartu-judul text-base">' . esc($judul) . '</h2></div>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="max-w-4xl space-y-4">
    <?php if ((int) ($admin['wajib_ganti_sandi'] ?? 0) === 1): ?>
        <?= ui_peringatan('awas', '', ['judul' => 'Ganti kata sandi sementara Anda dulu.',
            'html' => 'Akun ini memakai kata sandi sementara dari admin. Demi keamanan, isi bagian <b>Ganti password</b> di bawah (password lama = sandi sementara). Setelah itu semua menu Anda terbuka.']) ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Kartu profil -->
        <section class="kartu kartu-isi h-fit text-center" aria-label="Profil">
            <div class="mx-auto h-24 w-24 overflow-hidden rounded-full bg-brand-100 ring-4 ring-brand-50 dark:bg-gray-700 dark:ring-gray-600">
                <?php if (! empty($admin['photo'])): ?>
                    <img src="<?= base_url('uploads/' . esc($admin['photo'])) ?>" class="h-full w-full object-cover" alt="Foto <?= esc($admin['full_name'], 'attr') ?>">
                <?php else: ?>
                    <span class="flex h-full w-full items-center justify-center text-3xl font-bold text-brand-700 dark:text-brand-300"><?= esc(strtoupper(substr($admin['full_name'], 0, 1))) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white"><?= esc($admin['full_name']) ?></h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">@<?= esc($admin['username']) ?></p>
            <span class="lencana lencana-merek mt-2"><?= esc(\App\Libraries\HakAkses::label($admin['role'] ?? null)) ?></span>
            <dl class="mt-5 space-y-2 border-t border-gray-200 pt-4 text-left text-sm dark:border-gray-700">
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="truncate font-medium text-gray-900 dark:text-white"><?= esc($admin['email']) ?></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">No. HP</dt><dd class="font-medium text-gray-900 dark:text-white"><?= esc($admin['phone'] ?: '-') ?></dd></div>
            </dl>
        </section>

        <div class="space-y-4 lg:col-span-2">
            <!-- Edit profil -->
            <section class="kartu">
                <?= $kepala('Edit profil') ?>
                <form method="post" action="<?= site_url('admin/profile') ?>" enctype="multipart/form-data" class="kartu-isi space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="lbl" for="pf-nama">Nama lengkap</label>
                        <input type="text" id="pf-nama" name="full_name" value="<?= esc($admin['full_name'], 'attr') ?>" required class="inp">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="lbl" for="pf-username">Username</label>
                            <input type="text" id="pf-username" name="username" value="<?= esc($admin['username'], 'attr') ?>" required class="inp">
                        </div>
                        <div>
                            <label class="lbl" for="pf-hp">No. HP</label>
                            <input type="text" id="pf-hp" name="phone" value="<?= esc($admin['phone'], 'attr') ?>" class="inp">
                        </div>
                    </div>
                    <div>
                        <label class="lbl" for="pf-email">Email</label>
                        <input type="email" id="pf-email" name="email" value="<?= esc($admin['email'], 'attr') ?>" required class="inp">
                    </div>
                    <div>
                        <p class="lbl">Foto profil</p>
                        <label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih"
                               class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-4 transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">
                            <?= ikon('image', 'h-6 w-6 shrink-0 text-gray-500 dark:text-gray-400') ?>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Pilih foto (JPG/PNG)</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>
                            </span>
                            <input type="file" name="photo" accept="image/*" class="sr-only" @change="catatBerkas($event)">
                        </label>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan profil</button>
                    </div>
                </form>
            </section>

            <!-- Ganti password -->
            <section class="kartu">
                <?= $kepala('Ganti password') ?>
                <form method="post" action="<?= site_url('admin/profile/password') ?>" class="kartu-isi space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="lbl" for="pf-lama">Password lama</label>
                        <input type="password" id="pf-lama" name="old_password" required class="inp" autocomplete="current-password" placeholder="••••••••">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="lbl" for="pf-baru">Password baru</label>
                            <input type="password" id="pf-baru" name="new_password" required minlength="8" autocomplete="new-password" class="inp" placeholder="Min. 8 karakter">
                        </div>
                        <div>
                            <label class="lbl" for="pf-ulang">Konfirmasi password baru</label>
                            <input type="password" id="pf-ulang" name="confirm_password" required autocomplete="new-password" class="inp">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-utama"><?= ikon('lock', 'h-4 w-4') ?>Ganti password</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
