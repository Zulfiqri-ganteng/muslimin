<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Kelola Akun Staf — khusus peran admin. Komponen Alpine `akunPage` (assets/js/admin/akun.js).
 * Rombak tampilan F10: kartu angka kit, kartu + tabel kit (layar lebar) dan kartu (HP), lencana peran/status kit, tombol aksi
 * berwarna ber-ikon berlabel (Ubah biru · Reset sandi · Nonaktifkan merah · Aktifkan hijau), tiga dialog dengan bingkai kit
 * (.dialog + :data-buka; dulu x-show), skrip ber-nonce. Isian Alpine tidak berubah selain emoji centang dibuang.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,mixed>       $ringkas
 * @var array<string,array>       $peran     HakAkses::semua() — peran yang diatur Admin di Sistem → Hak Akses (kode => label, ringkas, menu, hak, …)
 * @var int                       $saya      id akun yang sedang login
 * @var ?array                    $baru      sandi sementara (flash, sekali tampil)
 * @var ?array                    $formLama  isian yang gagal disimpan (flash)
 * @var string                    $sekolah
 */

// Warna lencana per peran (kelas lencana kit).
$warnaPeran  = ['admin' => 'lencana-merek', 'operator' => 'lencana-hijau', 'hubin' => 'lencana-kuning'];
$warnaNetral = 'lencana-abu';
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
$lencanaStatus = static function (array $r): string {
    $h = (int) $r['aktif'] === 1 ? '<span class="lencana lencana-hijau">Aktif</span>' : '<span class="lencana lencana-abu">Nonaktif</span>';
    if ((int) $r['wajib_ganti_sandi'] === 1) {
        $h .= '<span class="lencana lencana-kuning" title="Masih memakai sandi sementara">Sandi sementara</span>';
    }

    return $h;
};
$avatar = static fn (array $r, string $ukuran): string => '<span class="flex ' . $ukuran . ' shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-gray-700 dark:text-brand-300">'
    . esc(strtoupper(mb_substr($r['full_name'], 0, 1))) . '</span>';
$tombolAksi = static function (bool $milikSaya, bool $aktif): string {
    $h = '<button type="button" class="btn btn-utama btn-sm" @click="ubah(baris($el))" title="Ubah data & peran">' . ikon('edit', 'h-4 w-4') . 'Ubah</button>';
    if (! $milikSaya) {
        $h .= '<button type="button" class="btn btn-netral btn-sm" @click="tanya(\'reset\', baris($el))" title="Reset kata sandi">' . ikon('lock', 'h-4 w-4') . 'Reset sandi</button>';
        $h .= $aktif
            ? '<button type="button" class="btn btn-bahaya btn-sm" @click="tanya(\'nonaktif\', baris($el))" title="Nonaktifkan akun">' . ikon('close-circle', 'h-4 w-4') . 'Nonaktifkan</button>'
            : '<button type="button" class="btn btn-sukses btn-sm" @click="tanya(\'aktif\', baris($el))" title="Aktifkan kembali">' . ikon('check-circle', 'h-4 w-4') . 'Aktifkan</button>';
    }

    return $h;
};
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

<div x-data="akunPage" class="space-y-4"
     data-base="<?= esc(site_url('admin/akun'), 'attr') ?>"
     data-login="<?= esc(site_url('admin/login'), 'attr') ?>"
     data-sekolah="<?= esc($sekolah, 'attr') ?>"
     data-peran="<?= esc(json_encode($labelPeran, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     data-buka="<?= esc(json_encode($formLama ?: null, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     data-baru="<?= esc(json_encode($baru ?: null, JSON_UNESCAPED_UNICODE), 'attr') ?>"
     @keydown.escape.window="tutupDialog()">

    <!-- Ringkasan -->
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Ringkasan akun">
        <?php foreach ([
            ['Total akun', $ringkas['total'], 'text-gray-900 dark:text-white'],
            ['Akun aktif', $ringkas['aktif'], 'text-green-700 dark:text-green-400'],
            ['Admin', $ringkas['peran']['admin'] ?? 0, 'text-brand-700 dark:text-brand-400'],
            ['Peran lain (bukan Admin)', array_sum($ringkas['peran']) - ($ringkas['peran']['admin'] ?? 0), 'text-yellow-700 dark:text-yellow-400'],
        ] as [$label, $nilai, $warna]): ?>
            <div class="kartu kartu-isi">
                <p class="stat-label text-sm"><?= esc($label) ?></p>
                <p class="stat-angka mt-2 tabular-nums <?= $warna ?>"><?= (int) $nilai ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- Daftar akun -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-akun">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 id="judul-akun" class="kartu-judul">Daftar akun staf</h2>
                <p class="kartu-ket">Login dengan email, lalu menu menyesuaikan peran.</p>
            </div>
            <button type="button" class="btn btn-utama" @click="tambah()"><?= ikon('plus', 'h-4 w-4') ?>Tambah akun</button>
        </div>

        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Akun</th>
                            <th scope="col">Peran</th>
                            <th scope="col">Status</th>
                            <th scope="col">Terakhir login</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): $milikSaya = (int) $r['id'] === $saya; $aktif = (int) $r['aktif'] === 1; ?>
                            <tr<?= $aktif ? '' : ' class="!bg-gray-50 dark:!bg-gray-800/60"' ?> data-akun="<?= $dataBaris($r, $saya) ?>">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <?= $avatar($r, 'h-9 w-9') ?>
                                        <div class="min-w-0">
                                            <p class="flex flex-wrap items-center gap-1.5 font-semibold <?= $aktif ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' ?>">
                                                <span class="truncate"><?= esc($r['full_name']) ?></span>
                                                <?php if ($milikSaya): ?><span class="lencana lencana-abu">Anda</span><?php endif; ?>
                                            </p>
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?= esc($r['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="lencana <?= $warnaPeran[$r['role']] ?? $warnaNetral ?>"><?= esc($labelPeran[$r['role']] ?? $r['role']) ?></span></td>
                                <td><div class="flex flex-wrap items-center gap-1.5"><?= $lencanaStatus($r) ?></div></td>
                                <td class="text-xs whitespace-nowrap"><?= $tglLogin($r['last_login_at']) ?? '<span class="text-gray-500 dark:text-gray-400">Belum pernah</span>' ?></td>
                                <td class="text-right whitespace-nowrap"><div class="inline-flex items-center justify-end gap-2"><?= $tombolAksi($milikSaya, $aktif) ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu (HP) -->
        <div class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
            <?php foreach ($rows as $r): $milikSaya = (int) $r['id'] === $saya; $aktif = (int) $r['aktif'] === 1; ?>
                <div class="space-y-3 px-4 py-4<?= $aktif ? '' : ' bg-gray-50 dark:bg-gray-800/60' ?>" data-akun="<?= $dataBaris($r, $saya) ?>">
                    <div class="flex items-start gap-3">
                        <?= $avatar($r, 'h-10 w-10') ?>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-1.5 font-semibold <?= $aktif ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' ?>">
                                <?= esc($r['full_name']) ?>
                                <?php if ($milikSaya): ?><span class="lencana lencana-abu">Anda</span><?php endif; ?>
                            </p>
                            <p class="text-xs break-all text-gray-500 dark:text-gray-400"><?= esc($r['email']) ?></p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="lencana <?= $warnaPeran[$r['role']] ?? $warnaNetral ?>"><?= esc($labelPeran[$r['role']] ?? $r['role']) ?></span>
                                <?= $lencanaStatus($r) ?>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Terakhir login: <?= $tglLogin($r['last_login_at']) ?? 'belum pernah' ?></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2"><?= $tombolAksi($milikSaya, $aktif) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Hak akses tiap peran (rujukan cepat; aturannya di Sistem → Hak Akses) -->
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-3" aria-label="Ringkasan peran">
        <?php foreach ($peran as $kode => $p):
            $level = array_count_values(array_map('strval', array_values((array) ($p['menu'] ?? []))));
        ?>
            <div class="kartu kartu-isi !p-4">
                <span class="lencana <?= $warnaPeran[$kode] ?? $warnaNetral ?>"><?= esc($p['label']) ?></span>
                <?php if ((string) ($p['ringkas'] ?? '') !== ''): ?><p class="mt-2 text-xs text-gray-500 dark:text-gray-400"><?= esc($p['ringkas']) ?></p><?php endif; ?>
                <p class="mt-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                    <?php if (! empty($p['penuh'])): ?>
                        Semua menu &amp; hak — tidak bisa dibatasi.
                    <?php else: ?>
                        Menu: <?= (int) ($level['kelola'] ?? 0) ?> kelola · <?= (int) ($level['lihat'] ?? 0) ?> lihat saja · <?= (int) ($level['boleh'] ?? 0) ?> modul — hak PKL &amp; Surat: <?= count((array) ($p['hak'] ?? [])) ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>
    </section>
    <p class="text-sm text-gray-500 dark:text-gray-400">Siapa boleh membuka menu apa, dan hak PKL &amp; Surat tiap peran, diatur di <a href="<?= site_url('admin/hak-akses') ?>" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Sistem → Hak Akses</a> (keterangan peran juga bisa diubah di sana).</p>

    <!-- ============ Dialog: tambah / ubah akun ============ -->
    <div class="dialog" :data-buka="formTerbuka" role="dialog" aria-modal="true" aria-labelledby="judul-form-akun">
        <div class="dialog-latar" @click="formTerbuka=false" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-lg" tabindex="-1">
            <div class="dialog-kepala">
                <h3 id="judul-form-akun" class="dialog-judul"><span x-show="mode==='tambah'">Tambah akun staf</span><span x-show="mode!=='tambah'" x-cloak>Ubah akun</span></h3>
                <button type="button" class="tombol-tutup" @click="formTerbuka=false" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" :action="urlForm">
                <?= csrf_field() ?>
                <div class="dialog-isi">
                    <div>
                        <label class="lbl" for="akun-nama">Nama lengkap <span class="text-red-600 dark:text-red-400">*</span></label>
                        <input id="akun-nama" type="text" name="full_name" x-model="form.full_name" x-ref="nama" maxlength="150" required autocomplete="off" class="inp" placeholder="Contoh: Siti Aminah, S.Pd.">
                    </div>
                    <div>
                        <label class="lbl" for="akun-email">Email <span class="text-red-600 dark:text-red-400">*</span></label>
                        <input id="akun-email" type="email" name="email" x-model="form.email" maxlength="150" required autocomplete="off" inputmode="email" class="inp" placeholder="nama@sekolah.sch.id">
                        <p class="hint">Dipakai untuk login — pastikan penulisannya benar.</p>
                    </div>
                    <div>
                        <label class="lbl" for="akun-hp">No. HP <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                        <input id="akun-hp" type="tel" name="phone" x-model="form.phone" maxlength="20" autocomplete="off" inputmode="tel" class="inp" placeholder="08xxxxxxxxxx">
                    </div>

                    <fieldset>
                        <legend class="lbl">Peran <span class="text-red-600 dark:text-red-400">*</span></legend>
                        <div class="space-y-2">
                            <?php foreach ($peran as $kode => $p): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                                       :class="[form.role === '<?= esc($kode, 'js') ?>' ? 'border-brand-500 bg-brand-50 dark:bg-gray-700' : 'border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700', peranTerkunci ? 'cursor-not-allowed opacity-60' : '']">
                                    <input type="radio" name="role" value="<?= esc($kode, 'attr') ?>" x-model="form.role" :disabled="peranTerkunci" class="cek mt-0.5">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= esc($p['label']) ?></span>
                                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400"><?= esc($p['ringkas']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p x-show="peranTerkunci" x-cloak class="mt-2 text-xs font-semibold text-yellow-700 dark:text-yellow-400">Peran akun Anda sendiri tidak bisa diubah (agar Anda tidak terkunci).</p>
                    </fieldset>

                    <div x-show="mode==='tambah'"><?= ui_peringatan('info', '', ['html' => '<b>Kata sandi sementara dibuat otomatis</b> oleh sistem dan ditampilkan <b>sekali</b> setelah akun disimpan. Pemilik akun wajib menggantinya saat login pertama.']) ?></div>
                </div>
                <div class="dialog-kaki">
                    <button type="button" class="btn btn-netral" @click="formTerbuka=false">Batal</button>
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><span x-show="mode==='tambah'">Simpan akun</span><span x-show="mode!=='tambah'" x-cloak>Simpan perubahan</span></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ Dialog: konfirmasi (reset / nonaktifkan / aktifkan) ============ -->
    <div class="dialog" :data-buka="tanyaTerbuka" role="alertdialog" aria-modal="true" aria-labelledby="judul-tanya-akun">
        <div class="dialog-latar" @click="tanyaTerbuka=false" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-sm" tabindex="-1">
            <div class="dialog-isi">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                          :class="tanyaData.bahaya ? 'bg-red-100 text-red-600 dark:bg-gray-700 dark:text-red-400' : 'bg-yellow-100 text-yellow-700 dark:bg-gray-700 dark:text-yellow-300'"><?= ikon('exclamation-circle', 'h-5 w-5') ?></span>
                    <div class="min-w-0">
                        <h3 id="judul-tanya-akun" class="text-base font-semibold text-gray-900 dark:text-white" x-text="tanyaData.judul"></h3>
                        <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300" x-text="tanyaData.pesan"></p>
                    </div>
                </div>
            </div>
            <form method="post" :action="tanyaData.url" class="dialog-kaki">
                <?= csrf_field() ?>
                <input type="hidden" name="aktif" :value="tanyaData.aktif">
                <button type="button" class="btn btn-netral" @click="tanyaTerbuka=false">Batal</button>
                <button type="submit" class="btn" :class="tanyaData.bahaya ? 'btn-bahaya' : 'btn-utama'" x-text="tanyaData.tombol"></button>
            </form>
        </div>
    </div>

    <!-- ============ Dialog: sandi sementara (SEKALI tampil, tak bisa ditutup tak sengaja) ============ -->
    <div class="dialog z-[60]" :data-buka="sandi !== null" role="alertdialog" aria-modal="true" aria-labelledby="judul-sandi-akun">
        <div class="dialog-latar" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-lg overflow-hidden" tabindex="-1">
            <div class="bg-brand-700 px-4 py-4 md:px-5">
                <h3 id="judul-sandi-akun" class="text-lg font-semibold text-white"><span x-show="sandi && sandi.jenis==='direset'">Kata sandi sementara baru</span><span x-show="!(sandi && sandi.jenis==='direset')">Akun berhasil dibuat</span></h3>
                <p class="text-sm text-brand-100" x-text="sandi ? sandi.nama + ' — ' + sandi.peran : ''"></p>
            </div>
            <div class="dialog-isi">
                <?= ui_peringatan('awas', '', ['judul' => 'Catat sekarang — sandi ini tidak akan ditampilkan lagi.', 'html' => '<p class="text-xs">Bila terlewat, cukup lakukan "Reset sandi" untuk membuat yang baru.</p>']) ?>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Email (untuk login)</dt>
                        <dd class="mt-0.5 font-medium break-all text-gray-900 dark:text-white" x-text="sandi ? sandi.email : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Kata sandi sementara</dt>
                        <dd class="mt-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-center font-mono text-2xl font-bold tracking-widest text-gray-900 select-all dark:border-gray-600 dark:bg-gray-700 dark:text-white" x-text="sandi ? sandi.sandi : ''"></dd>
                    </div>
                </dl>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" class="btn btn-netral" @click="salin(sandi.sandi, 'sandi')"><?= ikon('file-copy', 'h-4 w-4') ?><span x-text="tersalin==='sandi' ? 'Sandi tersalin' : 'Salin sandi'"></span></button>
                    <button type="button" class="btn btn-sukses" @click="salin(pesanLengkap(), 'pesan')"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span x-text="tersalin==='pesan' ? 'Pesan tersalin' : 'Salin pesan untuk WhatsApp'"></span></button>
                </div>
                <p class="hint">Yang bersangkutan akan diminta mengganti sandi ini saat login pertama.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-utama w-full" @click="sandi=null"><?= ikon('check', 'h-4 w-4') ?>Sudah saya catat</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/akun.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/akun.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
