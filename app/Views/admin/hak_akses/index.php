<?php
/**
 * Sistem → Hak Akses (KHUSUS ADMIN) — docs/DESAIN-HAK-AKSES.md. Tiga bagian: menu per peran, hak rinci PKL & Surat, peran.
 * Rombak tampilan F10: kartu angka per peran, tab kit, kartu + tabel kit (kolom pertama menempel, latar ikut mode gelap),
 * kotak centang/pilihan berbingkai, ui_peringatan, lencana kit, tombol kit ber-ikon, hapus peran = merah + dialog konfirmasi kit.
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
$jenisPeran = static fn (array $p): array => ! empty($p['penuh']) ? ['Penuh', 'lencana-merek'] : (! empty($p['sistem']) ? ['Bawaan', 'lencana-abu'] : ['Buatan', 'lencana-hijau']);
$kolomTempel = 'sticky start-0 z-10 bg-white dark:bg-gray-800';
$kepalaKartu = static fn (string $judul): string => '<div class="kartu-kepala"><h3 class="kartu-judul text-base">' . esc($judul) . '</h3></div>';
$kotakCentang = 'inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-gray-200 transition-colors hover:bg-gray-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-gray-600 dark:hover:bg-gray-700 dark:has-[:checked]:bg-gray-700';
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

<div class="space-y-4">
    <?php if (! $tabelAda): ?>
        <?= ui_peringatan('galat', '', ['html' => '<b>Tabel peran belum ada di database.</b> Jalankan <code>php spark migrate</code> di server. Sampai itu, sistem memakai aturan bawaan dan pengaturan di halaman ini belum bisa disimpan.']) ?>
    <?php endif; ?>

    <!-- Ringkasan peran -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan peran">
        <?php foreach ($semua as $kode => $p): $n = $akun[$kode] ?? ['n' => 0, 'aktif' => 0]; [$jenisTeks, $jenisWarna] = $jenisPeran($p); ?>
            <div class="kartu kartu-isi !p-4">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-gray-900 dark:text-white"><?= esc($p['label']) ?></p>
                    <span class="lencana <?= $jenisWarna ?> shrink-0"><?= $jenisTeks ?></span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <?= (int) $n['aktif'] ?> akun aktif<?= $n['n'] > $n['aktif'] ? ' (+' . ($n['n'] - $n['aktif']) . ' nonaktif)' : '' ?>
                    <?php if (empty($p['penuh'])): ?> · <?= $jmlMenu($p) ?> menu · <?= count((array) ($p['hak'] ?? [])) ?> hak PKL/Surat<?php else: ?> · semua menu &amp; hak<?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="overflow-x-auto">
        <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Bagian Hak Akses">
            <?php foreach ($tabs as $k => $label): ?>
                <a href="<?= site_url('admin/hak-akses') . '?bagian=' . $k ?>" class="tab whitespace-nowrap"<?= $bagian === $k ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($flashPeringatan !== []): ?>
        <?= ui_peringatan('awas', array_values($flashPeringatan), ['judul' => 'Tersimpan. Perhatikan:']) ?>
    <?php endif; ?>

    <?php if ($atur === []): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'user-settings', 'judul' => 'Belum ada peran selain Admin']) ?></div>
    <?php elseif ($bagian === 'menu'): ?>
        <!-- ================= MENU PER PERAN ================= -->
        <form method="post" action="<?= site_url('admin/hak-akses/menu') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="bagian" value="menu">
            <p class="text-sm text-gray-600 dark:text-gray-300"><b>Tidak</b> = menu hilang · <b>Lihat</b> = buka &amp; unduh saja · <b>Kelola</b> = semua aksi · <b>Boleh</b> = modul terbuka, rinciannya di bagian <a class="font-semibold text-brand-700 underline dark:text-brand-400" href="<?= site_url('admin/hak-akses?bagian=pkl') ?>">PKL &amp; Surat</a>.</p>
            <?php foreach ($grupMenu as $judul => $entri): ?>
                <section class="kartu overflow-hidden">
                    <?= $kepalaKartu($judul) ?>
                    <div class="tabel-bungkus">
                        <table class="tabel min-w-[36rem]">
                            <thead>
                                <tr>
                                    <th scope="col" class="<?= $kolomTempel ?> !bg-gray-50 dark:!bg-gray-700">Menu</th>
                                    <?php foreach ($atur as $kode): ?><th scope="col" class="w-36 text-center"><?= esc(HakPeran::label($kode)) ?></th><?php endforeach; ?>
                                    <th scope="col" class="w-20 text-center">Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entri as $e): $modul = $e['jenis'] === MenuAdmin::MODUL; ?>
                                    <tr class="align-top">
                                        <td class="<?= $kolomTempel ?>">
                                            <p class="flex flex-wrap items-center gap-1.5 font-semibold text-gray-900 dark:text-white"><?= esc($e['label']) ?><?php if ($modul): ?><span class="lencana lencana-merek">modul</span><?php endif; ?><?php if ($e['item'] === []): ?><span class="lencana lencana-abu">tanpa menu samping</span><?php endif; ?></p>
                                            <?php if ($e['ket'] !== ''): ?><p class="mt-0.5 max-w-md text-xs text-gray-500 dark:text-gray-400"><?= esc($e['ket']) ?></p><?php endif; ?>
                                        </td>
                                        <?php foreach ($atur as $kode): $lv = (string) ($semua[$kode]['menu'][$e['kunci']] ?? ''); ?>
                                            <td class="text-center">
                                                <select name="menu[<?= esc($kode, 'attr') ?>][<?= esc($e['kunci'], 'attr') ?>]" class="inp !py-1.5 <?= $lv !== '' ? '!border-brand-300 !bg-brand-50 font-semibold !text-brand-800 dark:!border-brand-700 dark:!bg-gray-700 dark:!text-brand-300' : '' ?>" aria-label="<?= esc(HakPeran::label($kode) . ': ' . $e['label'], 'attr') ?>">
                                                    <option value="">— Tidak</option>
                                                    <?php if ($modul): ?>
                                                        <option value="boleh"<?= $lv === 'boleh' ? ' selected' : '' ?>>Boleh</option>
                                                    <?php else: ?>
                                                        <option value="lihat"<?= $lv === 'lihat' ? ' selected' : '' ?>>Lihat</option>
                                                        <option value="kelola"<?= $lv === 'kelola' ? ' selected' : '' ?>>Kelola</option>
                                                    <?php endif; ?>
                                                </select>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center"><span class="lencana lencana-hijau" title="Admin selalu boleh">Penuh</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Selalu khusus Admin (tidak ada di tabel): <b>Kelola Akun</b> dan <b>Hak Akses</b>. Semua peran bisa membuka <b>Profil Saya</b>.</p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <button type="submit" formaction="<?= site_url('admin/hak-akses/bawaan') ?>" formnovalidate class="btn btn-netral"
                        data-confirm="Kembalikan MENU Operator Sekolah & Waka Hubin ke aturan bawaan sekolah? Peran buatan tidak diubah." data-confirm-judul="Kembalikan ke bawaan?" data-confirm-ya="Ya, kembalikan"><?= ikon('clock-arrow', 'h-4 w-4') ?>Kembalikan menu ke bawaan</button>
                <button type="submit" class="btn btn-utama"<?= $tabelAda ? '' : ' disabled' ?>><?= ikon('check', 'h-4 w-4') ?>Simpan menu</button>
            </div>
        </form>

    <?php elseif ($bagian === 'pkl'): ?>
        <!-- ================= PKL & SURAT ================= -->
        <form method="post" action="<?= site_url('admin/hak-akses/pkl') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="bagian" value="pkl">
            <?php if ($peringatan !== [] && $flashPeringatan === []): ?>
                <?= ui_peringatan('awas', array_values($peringatan), ['judul' => 'Catatan atas pengaturan saat ini']) ?>
            <?php endif; ?>

            <section class="kartu overflow-hidden">
                <?= $kepalaKartu('PKL / Prakerin') ?>
                <div class="tabel-bungkus">
                    <table class="tabel min-w-[36rem]">
                        <thead>
                            <tr>
                                <th scope="col" class="<?= $kolomTempel ?> !bg-gray-50 dark:!bg-gray-700">Hak</th>
                                <?php foreach ($atur as $kode): ?><th scope="col" class="w-32 text-center"><?= esc(HakPeran::label($kode)) ?><?php if ($tanpaModul[$kode]['pkl']): ?><span class="block text-[10px] font-normal normal-case text-yellow-700 dark:text-yellow-400">modul PKL belum diberi</span><?php endif; ?></th><?php endforeach; ?>
                                <th scope="col" class="w-20 text-center">Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hakPkl as $kunci => [$judul, $ket]): ?>
                                <tr class="align-top">
                                    <td class="<?= $kolomTempel ?>">
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?></p>
                                        <p class="mt-0.5 max-w-xl text-xs text-gray-500 dark:text-gray-400"><?= esc($ket) ?></p>
                                    </td>
                                    <?php foreach ($atur as $kode): ?>
                                        <td class="text-center">
                                            <label class="<?= $kotakCentang ?>">
                                                <input type="checkbox" name="hak[<?= esc($kode, 'attr') ?>][]" value="<?= esc($kunci, 'attr') ?>"<?= ! empty($matriksPkl[$kode][$kunci]) ? ' checked' : '' ?> class="cek" aria-label="<?= esc(HakPeran::label($kode) . ': ' . $judul, 'attr') ?>">
                                            </label>
                                            <?php if (in_array($kunci, $bawaanPkl[$kode] ?? [], true)): ?><p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">bawaan</p><?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center"><?= ikon('check', 'mx-auto h-4 w-4 text-green-600 dark:text-green-400') ?><span class="sr-only">Admin selalu boleh</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="kartu overflow-hidden">
                <?= $kepalaKartu('Surat Sekolah — per jenis surat') ?>
                <p class="border-b border-gray-200 px-4 py-3 text-sm text-gray-500 sm:px-6 dark:border-gray-700 dark:text-gray-400">
                    <b>Buat</b> = membuat, mengubah, mengajukan ulang, membatalkan · <b>ACC</b> = menyetujui / mengembalikan (tercatat di kaki surat) · <b>Unduh</b> = mencetak (nomor terbit saat unduhan pertama).
                    <b>Wajib ACC</b> tidak dicentang = surat langsung siap unduh setelah dibuat. Peran yang memegang <b>Buat + ACC</b> mendapat tombol satu klik <b>Simpan &amp; setujui</b>.
                </p>
                <div class="tabel-bungkus">
                    <table class="tabel min-w-[40rem]">
                        <thead>
                            <tr>
                                <th scope="col" class="<?= $kolomTempel ?> !bg-gray-50 dark:!bg-gray-700">Jenis surat</th>
                                <th scope="col" class="w-24 text-center">Wajib ACC</th>
                                <?php foreach ($atur as $kode): ?><th scope="col" class="w-44 text-center"><?= esc(HakPeran::label($kode)) ?><?php if ($tanpaModul[$kode]['surat']): ?><span class="block text-[10px] font-normal normal-case text-yellow-700 dark:text-yellow-400">modul Surat belum diberi</span><?php endif; ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (SuratJenis::kode() as $j): $bernomor = SuratJenis::bernomor($j); ?>
                                <tr class="align-top">
                                    <td class="<?= $kolomTempel ?>">
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc(SuratJenis::label($j)) ?></p>
                                        <p class="mt-0.5 max-w-xs text-xs text-gray-500 dark:text-gray-400"><?= esc(SuratJenis::DATA[$j]['ringkas']) ?></p>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($bernomor): ?>
                                            <label class="<?= $kotakCentang ?>"><input type="checkbox" name="acc[]" value="<?= esc($j, 'attr') ?>"<?= ! empty($accSurat[$j]) ? ' checked' : '' ?> class="cek" aria-label="<?= esc(SuratJenis::label($j) . ' wajib ACC', 'attr') ?>"></label>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" title="Tanpa nomor, tidak memakai ACC">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($atur as $kode): ?>
                                        <td>
                                            <div class="flex flex-wrap justify-center gap-1.5">
                                                <?php foreach (PklHak::aksiUntuk($j) as $aksi): $ada = ! empty($matriksSurat[$kode][$j][$aksi]); ?>
                                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1.5 text-xs font-semibold text-gray-600 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 dark:border-gray-600 dark:text-gray-300 dark:has-[:checked]:bg-gray-700 dark:has-[:checked]:text-brand-300">
                                                        <input type="checkbox" name="hak[<?= esc($kode, 'attr') ?>][]" value="<?= esc(PklHak::kunciSurat($aksi, $j), 'attr') ?>"<?= $ada ? ' checked' : '' ?> class="cek !h-3.5 !w-3.5">
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
                <button type="submit" formaction="<?= site_url('admin/hak-akses/bawaan') ?>" formnovalidate class="btn btn-netral"
                        data-confirm="Kembalikan hak PKL & Surat Operator Sekolah & Waka Hubin ke bawaan sekolah (Waka Hubin = ACC; Operator = surat & biaya)? Peran buatan tidak diubah." data-confirm-judul="Kembalikan ke bawaan?" data-confirm-ya="Ya, kembalikan"><?= ikon('clock-arrow', 'h-4 w-4') ?>Kembalikan ke bawaan sekolah</button>
                <button type="submit" class="btn btn-utama"<?= $tabelAda ? '' : ' disabled' ?>><?= ikon('check', 'h-4 w-4') ?>Simpan hak PKL &amp; Surat</button>
            </div>
        </form>

    <?php else: ?>
        <!-- ================= PERAN ================= -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <?php foreach ($semua as $kode => $p): $n = $akun[$kode] ?? ['n' => 0, 'aktif' => 0]; $penuh = ! empty($p['penuh']); ?>
                    <section class="kartu kartu-isi">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc($p['label']) ?> <span class="ms-1 font-mono text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($kode) ?></span></p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"><?= $n['n'] ?> akun · halaman awal: <b><?= esc(HakPeran::beranda($kode)) ?></b> · aplikasi Android: <b><?= ! empty($p['api']) ? 'boleh' : 'tidak' ?></b></p>
                                <?php if ((string) ($p['ringkas'] ?? '') !== ''): ?><p class="mt-1 max-w-2xl text-xs text-gray-500 dark:text-gray-400"><?= esc($p['ringkas']) ?></p><?php endif; ?>
                            </div>
                            <span class="lencana <?= $penuh ? 'lencana-merek' : (! empty($p['sistem']) ? 'lencana-abu' : 'lencana-hijau') ?>"><?= $penuh ? 'Penuh, tidak bisa diubah' : (! empty($p['sistem']) ? 'Bawaan sistem' : 'Buatan Admin') ?></span>
                        </div>
                        <?php if (! $penuh): ?>
                            <details class="mt-3 rounded-lg border border-gray-200 open:bg-gray-50 dark:border-gray-700 dark:open:bg-gray-700/40">
                                <summary class="cursor-pointer px-4 py-2.5 text-sm font-semibold text-brand-700 dark:text-brand-400">Ubah nama, halaman awal, aplikasi</summary>
                                <form method="post" action="<?= site_url('admin/hak-akses/peran/' . rawurlencode($kode)) ?>" class="space-y-3 px-4 pb-4">
                                    <?= csrf_field() ?>
                                    <div><label class="lbl" for="lbl_<?= esc($kode, 'attr') ?>">Nama peran</label><input id="lbl_<?= esc($kode, 'attr') ?>" name="label" required minlength="3" maxlength="60" value="<?= esc($p['label'], 'attr') ?>" class="inp"></div>
                                    <div><label class="lbl" for="rk_<?= esc($kode, 'attr') ?>">Keterangan (tampil di Kelola Akun)</label><textarea id="rk_<?= esc($kode, 'attr') ?>" name="ringkas" rows="2" maxlength="300" class="inp"><?= esc((string) ($p['ringkas'] ?? '')) ?></textarea></div>
                                    <div>
                                        <label class="lbl" for="br_<?= esc($kode, 'attr') ?>">Halaman awal setelah login</label>
                                        <select id="br_<?= esc($kode, 'attr') ?>" name="beranda" class="inp">
                                            <option value="">(otomatis: menu pertama yang boleh dibuka)</option>
                                            <?php foreach ($berandaPilihan[$kode] ?? [] as $url => $lbl): ?><option value="<?= esc($url, 'attr') ?>"<?= trim((string) $p['beranda'], '/') === $url ? ' selected' : '' ?>><?= esc($lbl) ?></option><?php endforeach; ?>
                                        </select>
                                        <p class="hint">Hanya menu yang sudah diberikan ke peran ini yang bisa dipilih.</p>
                                    </div>
                                    <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="api" value="1"<?= ! empty($p['api']) ? ' checked' : '' ?> class="cek mt-0.5"> <span>Boleh memakai <b>aplikasi Android</b> <span class="block text-xs text-gray-500 dark:text-gray-400">Di aplikasi, peran selain Admin hanya mendapat modul PKL &amp; Surat Sekolah (bila modulnya diberikan).</span></span></label>
                                    <div class="flex justify-end"><button type="submit" class="btn btn-utama btn-sm"><?= ikon('check', 'h-4 w-4') ?>Simpan peran</button></div>
                                </form>
                            </details>
                            <?php if (empty($p['sistem'])): ?>
                                <form method="post" action="<?= site_url('admin/hak-akses/peran/' . rawurlencode($kode) . '/hapus') ?>" class="mt-2 text-right">
                                    <?= csrf_field() ?>
                                    <?php if ($n['n'] > 0): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tidak bisa dihapus: masih dipakai <?= $n['n'] ?> akun (ganti perannya di Kelola Akun dulu).</p>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-teks btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700"
                                                data-confirm="Hapus peran &quot;<?= esc($p['label'], 'attr') ?>&quot;? Menu dan haknya ikut terhapus." data-confirm-judul="Hapus peran?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus peran ini</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <section class="kartu h-max">
                <?= $kepalaKartu('Tambah peran baru') ?>
                <form method="post" action="<?= site_url('admin/hak-akses/peran') ?>" class="kartu-isi space-y-3">
                    <?= csrf_field() ?>
                    <div><label class="lbl" for="peran_baru">Nama peran <span class="text-red-600 dark:text-red-400">*</span></label><input id="peran_baru" name="label" required minlength="3" maxlength="60" placeholder="mis. Waka Kesiswaan" class="inp"></div>
                    <div><label class="lbl" for="peran_ket">Keterangan</label><textarea id="peran_ket" name="ringkas" rows="2" maxlength="300" placeholder="Tugas singkat peran ini" class="inp"></textarea></div>
                    <div>
                        <label class="lbl" for="peran_salin">Salin menu &amp; hak dari</label>
                        <select id="peran_salin" name="salin" class="inp">
                            <option value="">(kosong — atur sendiri sesudahnya)</option>
                            <?php foreach ($atur as $kode): ?><option value="<?= esc($kode, 'attr') ?>"><?= esc(HakPeran::label($kode)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="api" value="1" class="cek mt-0.5"> <span>Boleh memakai aplikasi Android</span></label>
                    <button type="submit" class="btn btn-utama w-full"<?= $tabelAda ? '' : ' disabled' ?>><?= ikon('plus', 'h-4 w-4') ?>Tambah peran</button>
                    <p class="hint">Setelah dibuat, atur menunya di bagian <b>Menu per peran</b>, lalu pilih peran ini untuk akun di <a class="font-semibold text-brand-700 underline dark:text-brand-400" href="<?= site_url('admin/akun') ?>">Kelola Akun</a>.</p>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
