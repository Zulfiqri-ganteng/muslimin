<?php
/**
 * Sistem → Hak Akses (KHUSUS ADMIN) — docs/DESAIN-HAK-AKSES.md. Tiga bagian: menu per peran, hak rinci PKL & Surat, peran.
 *
 * @var string                               $bagian        menu | pkl | peran
 * @var bool                                 $tabelAda      tabel peran sudah dimigrasi
 * @var array<string, array<string, mixed>>  $semua         kode => peran
 * @var list<string>                         $atur          peran yang bisa diatur (selain Admin)
 * @var array<string, array{n:int,aktif:int}> $akun         jumlah akun per peran
 * @var array<string, list<array>>           $grupMenu      MenuAdmin::untukMatriks()
 * @var array<string, array{0:string,1:string}> $hakPkl, $aksiSurat
 * @var array<string, array<string, bool>>   $matriksPkl    peran => [hak => bool]
 * @var array<string, array<string, array<string, bool>>> $matriksSurat peran => [jenis => [aksi => bool]]
 * @var array<string, list<string>>          $bawaanPkl
 * @var array<string, bool>                  $accSurat      jenis => wajib ACC
 * @var list<string>                         $peringatan
 * @var array<string, array<string, string>> $berandaPilihan peran => [url => label]
 * @var array<string, array{pkl:bool,surat:bool}> $tanpaModul
 */

use App\Libraries\HakPeran;
use App\Libraries\MenuAdmin;
use App\Libraries\PklHak;
use App\Libraries\SuratJenis;

$flashPeringatan = session()->getFlashdata('peringatan_hak') ?: [];
$tabs = ['menu' => 'Menu per peran', 'pkl' => 'PKL & Surat', 'peran' => 'Peran'];
$jmlMenu = static fn (array $p): int => count((array) ($p['menu'] ?? []));
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'hak_akses_v1',
    'helpTitle' => 'Hak Akses',
    'helpBody'  => '<p>Di sini <b>Admin mengatur siapa boleh apa</b>, tanpa perlu mengubah program. Perubahan <u>langsung berlaku</u> di web dan aplikasi Android, dan tercatat di Audit Log.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Menu per peran</b> — tiap menu: <b>Tidak</b> (menu hilang), <b>Lihat</b> (bisa membuka &amp; mengunduh, tidak bisa menyimpan/menghapus), atau <b>Kelola</b> (semua aksi). Modul <b>PKL</b> dan <b>Surat Sekolah</b> cukup <b>Boleh</b>, lalu rinciannya diatur di bagian <b>PKL &amp; Surat</b>.</li>'
        . '<li><b>PKL &amp; Surat</b> — siapa yang boleh ACC ajuan, mengunduh Surat Izin PKL, dan untuk tiap jenis surat sekolah: <b>Buat</b>, <b>ACC</b>, <b>Unduh</b>. Contoh: Surat Balasan di-ACC Operator saja → centang <b>ACC</b> di kolom Operator dan hapus centangnya di kolom Waka Hubin.</li>'
        . '<li>Bila dua peran sama-sama punya hak ACC, <b>keduanya bisa</b>; yang menekan lebih dulu tercatat namanya di kaki surat.</li>'
        . '<li><b>Peran</b> — tambah peran baru (mis. Waka Kesiswaan, Kurikulum, TU), atur menunya, lalu pilih peran itu untuk akun di <b>Kelola Akun</b>.</li>'
        . '<li><b>Admin</b> selalu bisa semuanya. <b>Kelola Akun</b> dan <b>Hak Akses</b> hanya untuk Admin, supaya tidak ada peran yang bisa menaikkan haknya sendiri.</li>'
        . '</ul>',
]) ?>

<?php if (! $tabelAda): ?>
    <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-800"><b>Tabel peran belum ada di database.</b> Jalankan <code>php spark migrate</code> di server. Sampai itu, sistem memakai aturan bawaan dan pengaturan di halaman ini belum bisa disimpan.</div>
<?php endif; ?>

<!-- Ringkasan peran -->
<div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($semua as $kode => $p): $n = $akun[$kode] ?? ['n' => 0, 'aktif' => 0]; ?>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="font-semibold text-slate-800"><?= esc($p['label']) ?></p>
                <span class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold <?= ! empty($p['penuh']) ? 'bg-brand-50 text-brand-700' : (! empty($p['sistem']) ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700') ?>"><?= ! empty($p['penuh']) ? 'Penuh' : (! empty($p['sistem']) ? 'Bawaan' : 'Buatan') ?></span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                <?= (int) $n['aktif'] ?> akun aktif<?= $n['n'] > $n['aktif'] ? ' (+' . ($n['n'] - $n['aktif']) . ' nonaktif)' : '' ?>
                <?php if (empty($p['penuh'])): ?> · <?= $jmlMenu($p) ?> menu · <?= count((array) ($p['hak'] ?? [])) ?> hak PKL/Surat<?php else: ?> · semua menu &amp; hak<?php endif; ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<nav class="mb-5 flex gap-1.5 overflow-x-auto pb-1" aria-label="Bagian Hak Akses">
    <?php foreach ($tabs as $k => $label): $aktif = $bagian === $k; ?>
        <a href="<?= site_url('admin/hak-akses') . '?bagian=' . $k ?>" class="inline-flex shrink-0 items-center rounded-xl px-3.5 py-2 text-sm font-semibold transition <?= $aktif ? 'bg-brand-700 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' ?>"<?= $aktif ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($flashPeringatan !== []): ?>
    <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        <p class="font-bold">Tersimpan. Perhatikan:</p>
        <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($flashPeringatan as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($atur === []): ?>
    <p class="rounded-2xl border border-slate-200 bg-white px-5 py-6 text-center text-sm text-slate-500">Belum ada peran selain Admin.</p>
<?php elseif ($bagian === 'menu'): ?>
    <!-- ================= MENU PER PERAN ================= -->
    <form method="post" action="<?= site_url('admin/hak-akses/menu') ?>" class="space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="bagian" value="menu">
        <p class="text-sm text-slate-600"><b>Tidak</b> = menu hilang · <b>Lihat</b> = buka &amp; unduh saja · <b>Kelola</b> = semua aksi · <b>Boleh</b> = modul terbuka, rinciannya di bagian <a class="font-semibold text-brand-700 underline" href="<?= site_url('admin/hak-akses?bagian=pkl') ?>">PKL &amp; Surat</a>.</p>
        <?php foreach ($grupMenu as $judul => $entri): ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500"><?= esc($judul) ?></h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead class="text-left text-xs text-slate-500">
                            <tr class="border-b border-slate-100">
                                <th class="sticky left-0 z-10 bg-white px-5 py-2.5 font-semibold">Menu</th>
                                <?php foreach ($atur as $kode): ?><th class="w-36 px-2 py-2.5 text-center font-semibold"><?= esc(HakPeran::label($kode)) ?></th><?php endforeach; ?>
                                <th class="w-20 px-2 py-2.5 text-center font-semibold">Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($entri as $e): $modul = $e['jenis'] === MenuAdmin::MODUL; ?>
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-2.5">
                                        <p class="font-semibold text-slate-800"><?= esc($e['label']) ?><?php if ($modul): ?> <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-brand-700">modul</span><?php endif; ?><?php if ($e['item'] === []): ?> <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">tanpa menu samping</span><?php endif; ?></p>
                                        <?php if ($e['ket'] !== ''): ?><p class="mt-0.5 max-w-md text-xs leading-relaxed text-slate-500"><?= esc($e['ket']) ?></p><?php endif; ?>
                                    </td>
                                    <?php foreach ($atur as $kode): $lv = (string) ($semua[$kode]['menu'][$e['kunci']] ?? ''); ?>
                                        <td class="px-2 py-2 text-center">
                                            <select name="menu[<?= esc($kode, 'attr') ?>][<?= esc($e['kunci'], 'attr') ?>]" class="w-full rounded-lg border px-2 py-1.5 text-sm <?= $lv !== '' ? 'border-brand-300 bg-brand-50 font-semibold text-brand-800' : 'border-slate-200 bg-white text-slate-500' ?>" aria-label="<?= esc(HakPeran::label($kode) . ': ' . $e['label'], 'attr') ?>">
                                                <option value="">— Tidak</option>
                                                <?php if ($modul): ?>
                                                    <option value="boleh" <?= $lv === 'boleh' ? 'selected' : '' ?>>Boleh</option>
                                                <?php else: ?>
                                                    <option value="lihat" <?= $lv === 'lihat' ? 'selected' : '' ?>>Lihat</option>
                                                    <option value="kelola" <?= $lv === 'kelola' ? 'selected' : '' ?>>Kelola</option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-2 py-2.5 text-center text-xs font-semibold text-green-700" title="Admin selalu boleh">Penuh</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
        <p class="text-xs text-slate-500">Selalu khusus Admin (tidak ada di tabel): <b>Kelola Akun</b> dan <b>Hak Akses</b>. Semua peran bisa membuka <b>Profil Saya</b>.</p>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <button type="submit" formaction="<?= site_url('admin/hak-akses/bawaan') ?>" formnovalidate data-confirm="Kembalikan MENU Operator Sekolah & Waka Hubin ke aturan bawaan sekolah? Peran buatan tidak diubah."
                    class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Kembalikan menu ke bawaan</button>
            <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95"<?= $tabelAda ? '' : ' disabled' ?>>Simpan menu</button>
        </div>
    </form>

<?php elseif ($bagian === 'pkl'): ?>
    <!-- ================= PKL & SURAT ================= -->
    <form method="post" action="<?= site_url('admin/hak-akses/pkl') ?>" class="space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="bagian" value="pkl">
        <?php if ($peringatan !== [] && $flashPeringatan === []): ?>
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <p class="font-bold">Catatan atas pengaturan saat ini</p>
                <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($peringatan as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">PKL / Prakerin</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="text-left text-xs text-slate-500">
                        <tr class="border-b border-slate-100">
                            <th class="sticky left-0 z-10 bg-white px-5 py-2.5 font-semibold">Hak</th>
                            <?php foreach ($atur as $kode): ?><th class="w-32 px-2 py-2.5 text-center font-semibold"><?= esc(HakPeran::label($kode)) ?><?php if ($tanpaModul[$kode]['pkl']): ?><span class="block text-[10px] font-normal text-amber-700">modul PKL belum diberi</span><?php endif; ?></th><?php endforeach; ?>
                            <th class="w-20 px-2 py-2.5 text-center font-semibold">Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($hakPkl as $kunci => [$judul, $ket]): ?>
                            <tr class="align-top hover:bg-slate-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <p class="font-semibold text-slate-800"><?= esc($judul) ?></p>
                                    <p class="mt-0.5 max-w-xl text-xs leading-relaxed text-slate-500"><?= esc($ket) ?></p>
                                </td>
                                <?php foreach ($atur as $kode): ?>
                                    <td class="px-2 py-3 text-center">
                                        <label class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 hover:bg-slate-50">
                                            <input type="checkbox" name="hak[<?= esc($kode, 'attr') ?>][]" value="<?= esc($kunci, 'attr') ?>" <?= ! empty($matriksPkl[$kode][$kunci]) ? 'checked' : '' ?> class="h-5 w-5" aria-label="<?= esc(HakPeran::label($kode) . ': ' . $judul, 'attr') ?>">
                                        </label>
                                        <?php if (in_array($kunci, $bawaanPkl[$kode] ?? [], true)): ?><p class="mt-1 text-[10px] text-slate-400">bawaan</p><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-2 py-3 text-center text-xs font-semibold text-green-700">✓</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Surat Sekolah — per jenis surat</h3>
            <p class="border-b border-slate-100 px-5 py-3 text-xs leading-relaxed text-slate-500">
                <b>Buat</b> = membuat, mengubah, mengajukan ulang, membatalkan · <b>ACC</b> = menyetujui / mengembalikan (tercatat di kaki surat) · <b>Unduh</b> = mencetak (nomor terbit saat unduhan pertama).
                <b>Wajib ACC</b> tidak dicentang = surat langsung siap unduh setelah dibuat. Peran yang memegang <b>Buat + ACC</b> mendapat tombol satu klik <b>Simpan &amp; setujui</b>.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="text-left text-xs text-slate-500">
                        <tr class="border-b border-slate-100">
                            <th class="sticky left-0 z-10 bg-white px-5 py-2.5 font-semibold">Jenis surat</th>
                            <th class="w-24 px-2 py-2.5 text-center font-semibold">Wajib ACC</th>
                            <?php foreach ($atur as $kode): ?><th class="w-44 px-2 py-2.5 text-center font-semibold"><?= esc(HakPeran::label($kode)) ?><?php if ($tanpaModul[$kode]['surat']): ?><span class="block text-[10px] font-normal text-amber-700">modul Surat belum diberi</span><?php endif; ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (SuratJenis::kode() as $j): $bernomor = SuratJenis::bernomor($j); ?>
                            <tr class="align-top hover:bg-slate-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <p class="font-semibold text-slate-800"><?= esc(SuratJenis::label($j)) ?></p>
                                    <p class="mt-0.5 max-w-xs text-xs leading-relaxed text-slate-500"><?= esc(SuratJenis::DATA[$j]['ringkas']) ?></p>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <?php if ($bernomor): ?>
                                        <input type="checkbox" name="acc[]" value="<?= esc($j, 'attr') ?>" <?= ! empty($accSurat[$j]) ? 'checked' : '' ?> class="h-5 w-5" aria-label="<?= esc(SuratJenis::label($j) . ' wajib ACC', 'attr') ?>">
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400" title="Tanpa nomor, tidak memakai ACC">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($atur as $kode): ?>
                                    <td class="px-2 py-3">
                                        <div class="flex flex-wrap justify-center gap-1.5">
                                            <?php foreach (PklHak::aksiUntuk($j) as $aksi): $ada = ! empty($matriksSurat[$kode][$j][$aksi]); ?>
                                                <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border px-2 py-1.5 text-xs font-semibold transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 <?= $ada ? '' : 'border-slate-200 text-slate-500' ?>">
                                                    <input type="checkbox" name="hak[<?= esc($kode, 'attr') ?>][]" value="<?= esc(PklHak::kunciSurat($aksi, $j), 'attr') ?>" <?= $ada ? 'checked' : '' ?> class="h-3.5 w-3.5">
                                                    <?= esc($aksiSurat[$aksi][0]) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <button type="submit" formaction="<?= site_url('admin/hak-akses/bawaan') ?>" formnovalidate data-confirm="Kembalikan hak PKL & Surat Operator Sekolah & Waka Hubin ke bawaan sekolah (Waka Hubin = ACC; Operator = surat & biaya)? Peran buatan tidak diubah."
                    class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Kembalikan ke bawaan sekolah</button>
            <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95"<?= $tabelAda ? '' : ' disabled' ?>>Simpan hak PKL &amp; Surat</button>
        </div>
    </form>

<?php else: ?>
    <!-- ================= PERAN ================= -->
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            <?php foreach ($semua as $kode => $p): $n = $akun[$kode] ?? ['n' => 0, 'aktif' => 0]; $penuh = ! empty($p['penuh']); ?>
                <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-slate-800"><?= esc($p['label']) ?> <span class="ml-1 font-mono text-xs font-normal text-slate-400"><?= esc($kode) ?></span></p>
                            <p class="mt-0.5 text-xs text-slate-500"><?= $n['n'] ?> akun · halaman awal: <b><?= esc(HakPeran::beranda($kode)) ?></b> · aplikasi Android: <b><?= ! empty($p['api']) ? 'boleh' : 'tidak' ?></b></p>
                            <?php if ((string) ($p['ringkas'] ?? '') !== ''): ?><p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500"><?= esc($p['ringkas']) ?></p><?php endif; ?>
                        </div>
                        <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold <?= $penuh ? 'bg-brand-50 text-brand-700' : (! empty($p['sistem']) ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700') ?>"><?= $penuh ? 'Penuh, tidak bisa diubah' : (! empty($p['sistem']) ? 'Bawaan sistem' : 'Buatan Admin') ?></span>
                    </div>
                    <?php if (! $penuh): ?>
                        <details class="mt-3 rounded-xl border border-slate-200 open:bg-slate-50">
                            <summary class="cursor-pointer px-4 py-2.5 text-sm font-semibold text-brand-700">Ubah nama, halaman awal, aplikasi</summary>
                            <form method="post" action="<?= site_url('admin/hak-akses/peran/' . rawurlencode($kode)) ?>" class="space-y-3 px-4 pb-4">
                                <?= csrf_field() ?>
                                <div><label class="lbl" for="lbl_<?= esc($kode, 'attr') ?>">Nama peran</label><input id="lbl_<?= esc($kode, 'attr') ?>" name="label" required minlength="3" maxlength="60" value="<?= esc($p['label'], 'attr') ?>" class="inp"></div>
                                <div><label class="lbl" for="rk_<?= esc($kode, 'attr') ?>">Keterangan (tampil di Kelola Akun)</label><textarea id="rk_<?= esc($kode, 'attr') ?>" name="ringkas" rows="2" maxlength="300" class="inp"><?= esc((string) ($p['ringkas'] ?? '')) ?></textarea></div>
                                <div>
                                    <label class="lbl" for="br_<?= esc($kode, 'attr') ?>">Halaman awal setelah login</label>
                                    <select id="br_<?= esc($kode, 'attr') ?>" name="beranda" class="inp">
                                        <option value="">(otomatis: menu pertama yang boleh dibuka)</option>
                                        <?php foreach ($berandaPilihan[$kode] ?? [] as $url => $lbl): ?><option value="<?= esc($url, 'attr') ?>" <?= trim((string) $p['beranda'], '/') === $url ? 'selected' : '' ?>><?= esc($lbl) ?></option><?php endforeach; ?>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-400">Hanya menu yang sudah diberikan ke peran ini yang bisa dipilih.</p>
                                </div>
                                <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="api" value="1" <?= ! empty($p['api']) ? 'checked' : '' ?> class="mt-0.5 h-4 w-4"> <span>Boleh memakai <b>aplikasi Android</b> <span class="block text-xs text-slate-500">Di aplikasi, peran selain Admin hanya mendapat modul PKL &amp; Surat Sekolah (bila modulnya diberikan).</span></span></label>
                                <div class="flex justify-end"><button type="submit" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Simpan peran</button></div>
                            </form>
                        </details>
                        <?php if (empty($p['sistem'])): ?>
                            <form method="post" action="<?= site_url('admin/hak-akses/peran/' . rawurlencode($kode) . '/hapus') ?>" class="mt-2 text-right">
                                <?= csrf_field() ?>
                                <?php if ($n['n'] > 0): ?>
                                    <p class="text-xs text-slate-400">Tidak bisa dihapus: masih dipakai <?= $n['n'] ?> akun (ganti perannya di Kelola Akun dulu).</p>
                                <?php else: ?>
                                    <button type="submit" data-confirm="Hapus peran &quot;<?= esc($p['label'], 'attr') ?>&quot;? Menu dan haknya ikut terhapus." class="text-xs font-semibold text-red-600 hover:underline">Hapus peran ini</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="h-max rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Tambah peran baru</h3>
            <form method="post" action="<?= site_url('admin/hak-akses/peran') ?>" class="space-y-3 px-5 py-4">
                <?= csrf_field() ?>
                <div><label class="lbl" for="peran_baru">Nama peran <span class="text-red-500">*</span></label><input id="peran_baru" name="label" required minlength="3" maxlength="60" placeholder="mis. Waka Kesiswaan" class="inp"></div>
                <div><label class="lbl" for="peran_ket">Keterangan</label><textarea id="peran_ket" name="ringkas" rows="2" maxlength="300" placeholder="Tugas singkat peran ini" class="inp"></textarea></div>
                <div>
                    <label class="lbl" for="peran_salin">Salin menu &amp; hak dari</label>
                    <select id="peran_salin" name="salin" class="inp">
                        <option value="">(kosong — atur sendiri sesudahnya)</option>
                        <?php foreach ($atur as $kode): ?><option value="<?= esc($kode, 'attr') ?>"><?= esc(HakPeran::label($kode)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="api" value="1" class="mt-0.5 h-4 w-4"> <span>Boleh memakai aplikasi Android</span></label>
                <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800"<?= $tabelAda ? '' : ' disabled' ?>>Tambah peran</button>
                <p class="text-xs leading-relaxed text-slate-500">Setelah dibuat, atur menunya di bagian <b>Menu per peran</b>, lalu pilih peran ini untuk akun di <a class="font-semibold text-brand-700 underline" href="<?= site_url('admin/akun') ?>">Kelola Akun</a>.</p>
            </form>
        </section>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
