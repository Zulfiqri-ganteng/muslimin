<?php
/**
 * Hak Akses PKL — KHUSUS ADMIN. Tabel centang: peran (Waka Hubin, Operator) × hak (Libraries\PklHak::HAK).
 * Admin selalu memegang semua hak dan tidak bisa dicabut.
 *
 * @var array<string, array<string, bool>> $matriks    peran => [hak => bool]
 * @var array<string, array{0:string,1:string}> $hakDaftar
 * @var list<string>                       $peringatan peringatan pemisahan tugas (kondisi tersimpan)
 * @var array<string, list<string>>        $bawaan     hak bawaan
 */

use App\Libraries\HakAkses;

$peran = ['hubin', 'operator'];
$flashPeringatan = session()->getFlashdata('peringatan_hak') ?: [];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_hak_akses_v1',
    'helpTitle' => 'Hak Akses PKL',
    'helpBody'  => '<p>Di sini <b>Admin menentukan siapa boleh melakukan apa</b> di modul PKL. Centang = boleh. Berlaku <u>langsung</u> pada web dan aplikasi Android.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li>Tujuan utama: <b>memisahkan yang menyetujui (ACC) dari yang mencetak surat</b>, supaya jelas siapa melakukan apa. Bawaan: Waka Hubin hanya ACC; Operator mengunduh surat dan mencatat biaya.</li>'
        . '<li>Admin <b>selalu</b> boleh semua (sebagai cadangan) dan tidak bisa dicabut. ACC oleh Admin wajib menyatakan &quot;mewakili Waka Hubin&quot;.</li>'
        . '<li>Hak ACC dan Unduh surat masing-masing harus dipegang minimal satu peran.</li>'
        . '<li>Bila satu peran memegang ACC <i>sekaligus</i> Unduh surat, sistem tetap menyimpan tetapi memberi <b>peringatan</b> (pemisahan tugas hilang). Setiap perubahan tercatat di Audit Log.</li>'
        . '<li>Halaman ini hanya bisa dibuka Admin; Operator dan Waka Hubin tidak melihatnya.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url('admin/pkl/hak-akses') ?>" class="mx-auto max-w-4xl space-y-5">
    <?= csrf_field() ?>

    <?php if ($flashPeringatan !== []): ?>
        <div class="rise rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-bold">Hak tersimpan, tetapi perhatikan:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($flashPeringatan as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        </div>
    <?php elseif ($peringatan !== []): ?>
        <div class="rise rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-bold">Peringatan pada pengaturan saat ini</p>
            <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($peringatan as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siapa boleh apa</h3>

        <!-- Tabel (layar lebar) -->
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500">
                    <tr class="border-b border-slate-100">
                        <th class="px-5 py-3 font-semibold">Hak</th>
                        <?php foreach ($peran as $p): ?><th class="w-32 px-3 py-3 text-center font-semibold"><?= esc(HakAkses::label($p)) ?></th><?php endforeach; ?>
                        <th class="w-24 px-3 py-3 text-center font-semibold">Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($hakDaftar as $kunci => [$judul, $ket]): ?>
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-800"><?= esc($judul) ?></p>
                                <p class="mt-0.5 max-w-xl text-xs leading-relaxed text-slate-500"><?= esc($ket) ?></p>
                            </td>
                            <?php foreach ($peran as $p): ?>
                                <td class="px-3 py-3.5 text-center">
                                    <label class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 hover:bg-slate-50">
                                        <input type="checkbox" name="hak[<?= $p ?>][]" value="<?= $kunci ?>" <?= ! empty($matriks[$p][$kunci]) ? 'checked' : '' ?> class="h-5 w-5" aria-label="<?= esc(HakAkses::label($p) . ': ' . $judul, 'attr') ?>">
                                    </label>
                                    <?php if (in_array($kunci, $bawaan[$p] ?? [], true)): ?><p class="mt-1 text-[10px] text-slate-400">bawaan</p><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="px-3 py-3.5 text-center"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-green-600" title="Admin selalu boleh">✓</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP) -->
        <div class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($hakDaftar as $kunci => [$judul, $ket]): ?>
                <div class="px-4 py-4">
                    <p class="font-semibold text-slate-800"><?= esc($judul) ?></p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500"><?= esc($ket) ?></p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <?php foreach ($peran as $p): ?>
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="checkbox" name="hak[<?= $p ?>][]" value="<?= $kunci ?>" <?= ! empty($matriks[$p][$kunci]) ? 'checked' : '' ?> class="h-5 w-5"> <?= esc(HakAkses::label($p)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button type="submit" formaction="<?= site_url('admin/pkl/hak-akses/bawaan') ?>" formnovalidate onclick="return confirm('Kembalikan semua hak ke bawaan sekolah (Waka Hubin = ACC; Operator = surat & biaya)?')"
                class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Kembalikan ke bawaan sekolah</button>
        <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan hak akses</button>
    </div>
</form>

<?= $this->endSection() ?>
