<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Kelola Akun Staf — khusus peran admin.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,mixed>       $ringkas
 * @var array<string,array>       $peran     Config\Peran (kode => label, ringkas, ...)
 * @var int                       $saya      id akun yang sedang login
 * @var ?array                    $baru      sandi sementara (flash, sekali tampil)
 * @var ?array                    $formLama  isian yang gagal disimpan (flash)
 * @var string                    $sekolah
 */

// Warna lencana per peran — kelas ditulis utuh agar terbaca Tailwind.
$warnaPeran = [
    'admin'    => 'bg-brand-50 text-brand-700 ring-brand-200',
    'operator' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'hubin'    => 'bg-amber-50 text-amber-700 ring-amber-200',
];
$warnaNetral = 'bg-slate-100 text-slate-600 ring-slate-200';
$labelPeran  = array_map(static fn (array $p): string => (string) $p['label'], $peran);
$tglLogin    = static fn (?string $t): ?string => $t ? date('d-m-Y H:i', strtotime($t)) : null;

$dataBaris = static fn (array $r, int $saya): string => esc(json_encode([
    'id'        => (int) $r['id'],
    'full_name' => $r['full_name'],
    'email'     => $r['email'],
    'phone'     => (string) ($r['phone'] ?? ''),
    'role'      => $r['role'],
    'aktif'     => (int) $r['aktif'],
    'saya'      => (int) $r['id'] === $saya,
], JSON_UNESCAPED_UNICODE), 'attr');

?>

<?= view('admin/partials/help', [
    'helpKey'   => 'akun',
    'helpTitle' => 'Kelola Akun Staf',
    'helpBody'  => '<p>Di sini admin menambah akun untuk <b>Operator Sekolah</b> dan <b>Waka Hubin</b>. Satu halaman login untuk semua: sistem mengenali peran dari <b>email</b> akun, lalu menampilkan menu yang sesuai.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Tambah Akun</b> — isi nama, email, dan peran. Kata sandi sementara dibuat <b>otomatis</b> dan hanya tampil <b>sekali</b>; salin lalu sampaikan ke yang bersangkutan. Ia wajib menggantinya saat login pertama.</li>'
        . '<li><b>Reset sandi</b> — bila lupa. Sandi lama langsung tidak berlaku dan aplikasi Android milik akun itu keluar otomatis.</li>'
        . '<li><b>Nonaktifkan</b> — untuk staf yang berhenti. Akun tidak dihapus (riwayat tetap utuh) dan <b>langsung</b> tidak bisa masuk, bahkan bila sedang login.</li>'
        . '<li>Anda tidak bisa menonaktifkan, mereset, atau mengubah peran akun Anda sendiri, dan sistem selalu menyisakan minimal satu admin aktif.</li>'
        . '</ul>',
]) ?>

<div x-data="akunPage"
     data-base="<?= esc(site_url('admin/akun'), 'attr') ?>"
     data-login="<?= esc(site_url('admin/login'), 'attr') ?>"
     data-sekolah="<?= esc($sekolah, 'attr') ?>"
     data-peran="<?= esc(json_encode($labelPeran, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     data-buka="<?= esc(json_encode($formLama ?: null, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     data-baru="<?= esc(json_encode($baru ?: null, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     @keydown.escape.window="tutupDialog()">

    <!-- Ringkasan -->
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php foreach ([
            ['Total akun', $ringkas['total'], 'text-slate-800'],
            ['Akun aktif', $ringkas['aktif'], 'text-emerald-600'],
            ['Admin', $ringkas['peran']['admin'] ?? 0, 'text-brand-700'],
            ['Operator & Hubin', ($ringkas['peran']['operator'] ?? 0) + ($ringkas['peran']['hubin'] ?? 0), 'text-amber-600'],
        ] as [$label, $nilai, $warna]): ?>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-xs font-semibold text-slate-400"><?= esc($label) ?></p>
                <p class="mt-1 text-2xl font-extrabold <?= $warna ?>"><?= (int) $nilai ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Daftar akun -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-slate-800">Daftar Akun Staf</h2>
                <p class="text-xs text-slate-400">Login dengan email, lalu menu menyesuaikan peran.</p>
            </div>
            <button type="button" @click="tambah()"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-95">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Akun
            </button>
        </div>

        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Akun</th>
                        <th class="px-5 py-3 font-semibold">Peran</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Terakhir login</th>
                        <th class="w-36 px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): $milikSaya = (int) $r['id'] === $saya; $aktif = (int) $r['aktif'] === 1; ?>
                        <tr class="<?= $aktif ? 'hover:bg-slate-50' : 'bg-slate-50/60' ?>" data-akun="<?= $dataBaris($r, $saya) ?>">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"><?= esc(strtoupper(mb_substr($r['full_name'], 0, 1))) ?></span>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold <?= $aktif ? 'text-slate-800' : 'text-slate-400' ?>">
                                            <?= esc($r['full_name']) ?>
                                            <?php if ($milikSaya): ?><span class="ml-1 rounded-md bg-slate-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-slate-500">Anda</span><?php endif; ?>
                                        </p>
                                        <p class="truncate text-xs text-slate-400"><?= esc($r['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= $warnaPeran[$r['role']] ?? $warnaNetral ?>"><?= esc($labelPeran[$r['role']] ?? $r['role']) ?></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <?php if ($aktif): ?>
                                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Aktif</span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-500">Nonaktif</span>
                                    <?php endif; ?>
                                    <?php if ((int) $r['wajib_ganti_sandi'] === 1): ?>
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-200" title="Masih memakai sandi sementara">Sandi sementara</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500"><?= $tglLogin($r['last_login_at']) ?? '<span class="text-slate-300">Belum pernah</span>' ?></td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button type="button" @click="ubah(baris($el))" title="Ubah data & peran"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 transition hover:bg-brand-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <?php if (! $milikSaya): ?>
                                        <button type="button" @click="tanya('reset', baris($el))" title="Reset kata sandi"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-amber-600 transition hover:bg-amber-50">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                        </button>
                                        <?php if ($aktif): ?>
                                            <button type="button" @click="tanya('nonaktif', baris($el))" title="Nonaktifkan akun"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 transition hover:bg-red-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" @click="tanya('aktif', baris($el))" title="Aktifkan kembali"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-emerald-600 transition hover:bg-emerald-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP) -->
        <div class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $r): $milikSaya = (int) $r['id'] === $saya; $aktif = (int) $r['aktif'] === 1; ?>
                <div class="space-y-3 px-4 py-4 <?= $aktif ? '' : 'bg-slate-50/60' ?>" data-akun="<?= $dataBaris($r, $saya) ?>">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"><?= esc(strtoupper(mb_substr($r['full_name'], 0, 1))) ?></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold <?= $aktif ? 'text-slate-800' : 'text-slate-400' ?>">
                                <?= esc($r['full_name']) ?>
                                <?php if ($milikSaya): ?><span class="ml-1 rounded-md bg-slate-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-slate-500">Anda</span><?php endif; ?>
                            </p>
                            <p class="break-all text-xs text-slate-400"><?= esc($r['email']) ?></p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= $warnaPeran[$r['role']] ?? $warnaNetral ?>"><?= esc($labelPeran[$r['role']] ?? $r['role']) ?></span>
                                <?php if ($aktif): ?>
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-500">Nonaktif</span>
                                <?php endif; ?>
                                <?php if ((int) $r['wajib_ganti_sandi'] === 1): ?>
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">Sandi sementara</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-400">Terakhir login: <?= $tglLogin($r['last_login_at']) ?? 'belum pernah' ?></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="ubah(baris($el))" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Ubah</button>
                        <?php if (! $milikSaya): ?>
                            <button type="button" @click="tanya('reset', baris($el))" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">Reset sandi</button>
                            <?php if ($aktif): ?>
                                <button type="button" @click="tanya('nonaktif', baris($el))" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Nonaktifkan</button>
                            <?php else: ?>
                                <button type="button" @click="tanya('aktif', baris($el))" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Aktifkan</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hak akses tiap peran (rujukan cepat) -->
    <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-3">
        <?php foreach ($peran as $kode => $p): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= $warnaPeran[$kode] ?? $warnaNetral ?>"><?= esc($p['label']) ?></span>
                <p class="mt-2 text-xs leading-relaxed text-slate-500"><?= esc($p['ringkas']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ Dialog: tambah / ubah akun ============ -->
    <div x-show="formTerbuka" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" @click="formTerbuka=false"></div>
        <div class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-xl sm:rounded-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <h3 class="text-lg font-bold text-slate-800" x-text="mode==='tambah' ? 'Tambah Akun Staf' : 'Ubah Akun'"></h3>
                <button type="button" @click="formTerbuka=false" class="text-slate-400 hover:text-slate-600" title="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="post" :action="urlForm" class="space-y-4 p-5 sm:p-6">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl" for="akun-nama">Nama lengkap <span class="text-red-500">*</span></label>
                    <input id="akun-nama" type="text" name="full_name" x-model="form.full_name" x-ref="nama" maxlength="150" required autocomplete="off" class="inp" placeholder="Contoh: Siti Aminah, S.Pd.">
                </div>
                <div>
                    <label class="lbl" for="akun-email">Email <span class="text-red-500">*</span></label>
                    <input id="akun-email" type="email" name="email" x-model="form.email" maxlength="150" required autocomplete="off" inputmode="email" class="inp" placeholder="nama@sekolah.sch.id">
                    <p class="mt-1 text-xs text-slate-400">Dipakai untuk login — pastikan penulisannya benar.</p>
                </div>
                <div>
                    <label class="lbl" for="akun-hp">No. HP <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="akun-hp" type="tel" name="phone" x-model="form.phone" maxlength="20" autocomplete="off" inputmode="tel" class="inp" placeholder="08xxxxxxxxxx">
                </div>

                <fieldset>
                    <legend class="lbl">Peran <span class="text-red-500">*</span></legend>
                    <div class="space-y-2">
                        <?php foreach ($peran as $kode => $p): ?>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                   :class="[form.role === '<?= esc($kode, 'js') ?>' ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-200 hover:bg-slate-50', peranTerkunci ? 'cursor-not-allowed opacity-60' : '']">
                                <input type="radio" name="role" value="<?= esc($kode, 'attr') ?>" x-model="form.role" :disabled="peranTerkunci" class="mt-1 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800"><?= esc($p['label']) ?></span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= esc($p['ringkas']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p x-show="peranTerkunci" x-cloak class="mt-2 text-xs font-semibold text-amber-700">Peran akun Anda sendiri tidak bisa diubah (agar Anda tidak terkunci).</p>
                </fieldset>

                <div x-show="mode==='tambah'" class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-xs leading-relaxed text-brand-800">
                    <b>Kata sandi sementara dibuat otomatis</b> oleh sistem dan ditampilkan <b>sekali</b> setelah akun disimpan. Pemilik akun wajib menggantinya saat login pertama.
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="formTerbuka=false" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-95" x-text="mode==='tambah' ? 'Simpan Akun' : 'Simpan Perubahan'"></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ Dialog: konfirmasi (reset / nonaktifkan / aktifkan) ============ -->
    <div x-show="tanyaTerbuka" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-50 flex items-center justify-center p-4" role="alertdialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" @click="tanyaTerbuka=false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                      :class="tanyaData.bahaya ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-slate-800" x-text="tanyaData.judul"></h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600" x-text="tanyaData.pesan"></p>
                </div>
            </div>
            <form method="post" :action="tanyaData.url" class="mt-5 flex justify-end gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="aktif" :value="tanyaData.aktif">
                <button type="button" @click="tanyaTerbuka=false" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                <button class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition active:scale-95"
                        :class="tanyaData.bahaya ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-700 hover:bg-brand-800'" x-text="tanyaData.tombol"></button>
            </form>
        </div>
    </div>

    <!-- ============ Dialog: sandi sementara (SEKALI tampil, tak bisa ditutup tak sengaja) ============ -->
    <div x-show="sandi" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4" role="alertdialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/60"></div>
        <div class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" x-show="sandi">
            <div class="bg-brand-700 px-5 py-4 sm:rounded-t-2xl sm:px-6">
                <h3 class="text-lg font-bold text-white" x-text="sandi && sandi.jenis==='direset' ? 'Kata sandi sementara baru' : 'Akun berhasil dibuat'"></h3>
                <p class="text-sm text-brand-100" x-text="sandi ? sandi.nama + ' — ' + sandi.peran : ''"></p>
            </div>
            <div class="space-y-4 p-5 sm:p-6">
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-bold">Catat sekarang — sandi ini tidak akan ditampilkan lagi.</p>
                    <p class="mt-0.5 text-xs text-amber-700">Bila terlewat, cukup lakukan "Reset sandi" untuk membuat yang baru.</p>
                </div>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email (untuk login)</dt>
                        <dd class="mt-0.5 break-all font-medium text-slate-800" x-text="sandi ? sandi.email : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kata sandi sementara</dt>
                        <dd class="mt-1 select-all rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-center font-mono text-2xl font-bold tracking-widest text-slate-900" x-text="sandi ? sandi.sandi : ''"></dd>
                    </div>
                </dl>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" @click="salin(sandi.sandi, 'sandi')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <span x-text="tersalin==='sandi' ? '✓ Sandi tersalin' : 'Salin sandi'"></span>
                    </button>
                    <button type="button" @click="salin(pesanLengkap(), 'pesan')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                        <span x-text="tersalin==='pesan' ? '✓ Pesan tersalin' : 'Salin pesan untuk WhatsApp'"></span>
                    </button>
                </div>
                <p class="text-xs leading-relaxed text-slate-500">Yang bersangkutan akan diminta mengganti sandi ini saat login pertama.</p>
                <button type="button" @click="sandi=null" class="w-full rounded-lg bg-brand-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Sudah saya catat</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/akun.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/akun.js') ?>"></script>
<?= $this->endSection() ?>
