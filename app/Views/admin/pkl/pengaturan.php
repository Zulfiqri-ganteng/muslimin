<?php
/**
 * Pengaturan PKL (Operator/Admin): buka-tutup form siswa, tingkat, pagar tanggal, lama PKL.
 *
 * @var array   $galat   galat per kolom
 * @var array   $old     isian yang gagal disimpan
 * @var string  $tautan  alamat form siswa
 * @var ?string $alasan  null = form sedang terbuka
 * @var array   $siswa   ringkasan siswa
 */

use App\Models\PklPengaturanModel;

$nilai = static fn (string $k, $bawaan = ''): string => (string) ($old[$k] ?? $p[$k] ?? $bawaan);
$err   = static fn (string $k) => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$cls   = static fn (string $k) => isset($galat[$k]) ? 'inp-err' : '';

$buka    = $nilai('form_buka', '0') === '1';
$tingkat = isset($old['tingkat']) ? array_map('strval', (array) $old['tingkat']) : PklPengaturanModel::tingkatBoleh($p);
$tutup   = '';
if ($nilai('form_tutup') !== '') {
    $t     = strtotime($nilai('form_tutup'));
    $tutup = $t ? date('Y-m-d\TH:i', $t) : $nilai('form_tutup');
}
$tinjau = [
    'belum_dibuka'  => 'Form saat ini TERTUTUP (saklar mati).',
    'sudah_ditutup' => 'Form saat ini TERTUTUP karena batas waktu sudah lewat.',
    'belum_siap'    => 'Saklar menyala, tetapi form belum bisa dipakai: tingkat atau pagar tanggal belum lengkap.',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_pengaturan_v1',
    'helpTitle' => 'Pengaturan PKL',
    'helpBody'  => '<p>Atur <b>kapan</b> siswa boleh mengisi, <b>siapa</b> yang boleh (tingkat), dan <b>pagar tanggal</b> PKL.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Pagar tanggal</b> menjaga salah ketik tahun/bulan: siswa tidak bisa memilih tanggal di luar rentang ini. Perusahaan boleh punya tanggal berbeda-beda, asal di dalam pagar.</li>'
        . '<li><b>Lama PKL</b> menjaga PKL yang terlalu singkat/panjang akibat salah ketik.</li>'
        . '<li>Form <b>tertutup</b> secara bawaan. Buka hanya saat tautan siap dibagikan; bisa diberi <b>batas waktu</b> agar menutup sendiri.</li>'
        . '<li>Siswa yang sudah punya ajuan aktif tetap tidak bisa mengajukan lagi, apa pun pengaturannya.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url('admin/pkl/pengaturan') ?>" class="mx-auto max-w-3xl space-y-5"
      x-data="{ buka: <?= $buka ? 'true' : 'false' ?>, tersalin: false, salin() { navigator.clipboard.writeText(<?= esc(json_encode($tautan), 'attr') ?>).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000); }); } }">
    <?= csrf_field() ?>

    <?php if ($galat !== []): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-bold text-red-700">Pengaturan belum disimpan. Periksa isian yang bertanda merah.</div>
    <?php endif; ?>

    <!-- Buka / tutup -->
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Form siswa</h3>
        <div class="space-y-4 p-5">
            <?php if ($alasan !== null): ?><p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900"><?= esc($tinjau[$alasan] ?? '') ?></p>
            <?php else: ?><p class="rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-800">Form saat ini TERBUKA untuk siswa.</p><?php endif; ?>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition border-slate-200 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:ring-2 has-[:checked]:ring-green-500/20">
                    <input type="radio" name="form_buka" value="1" x-model.number="buka" :value="1" <?= $buka ? 'checked' : '' ?> class="mt-1" @change="buka = true">
                    <span><b class="block text-sm text-slate-800">Buka form</b><span class="text-xs text-slate-500">Siswa bisa mengisi lewat tautan.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition border-slate-200 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 has-[:checked]:ring-2 has-[:checked]:ring-slate-400/20">
                    <input type="radio" name="form_buka" value="0" <?= $buka ? '' : 'checked' ?> class="mt-1" @change="buka = false">
                    <span><b class="block text-sm text-slate-800">Tutup form</b><span class="text-xs text-slate-500">Siswa melihat pemberitahuan "belum dibuka".</span></span>
                </label>
            </div>

            <div>
                <label class="lbl" for="f_form_tutup">Tutup otomatis pada <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="f_form_tutup" type="datetime-local" name="form_tutup" value="<?= esc($tutup, 'attr') ?>" class="inp max-w-xs <?= $cls('form_tutup') ?>">
                <p class="mt-1 text-xs text-slate-400">Setelah waktu ini, form menutup sendiri. Kosongkan bila ingin menutup manual.</p>
                <?= $err('form_tutup') ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tautan untuk dibagikan ke siswa</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-700 ring-1 ring-slate-200"><?= esc($tautan) ?></code>
                    <button type="button" @click="salin()" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><span x-text="tersalin ? '✓ Tersalin' : 'Salin'">Salin</span></button>
                </div>
            </div>
        </div>
    </section>

    <!-- Siapa -->
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siapa yang boleh mengajukan</h3>
        <div class="space-y-4 p-5">
            <div>
                <p class="lbl">Tingkat kelas</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (PklPengaturanModel::TINGKAT as $t): ?>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                            <input type="checkbox" name="tingkat[]" value="<?= $t ?>" <?= in_array($t, $tingkat, true) ? 'checked' : '' ?> class="h-4 w-4"> Kelas <?= $t ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $err('tingkat') ?>
            </div>
            <div class="max-w-xs">
                <label class="lbl" for="f_maks_anggota">Maksimal siswa per perusahaan</label>
                <input id="f_maks_anggota" type="number" min="1" max="20" name="maks_anggota" value="<?= esc($nilai('maks_anggota', '5'), 'attr') ?>" class="inp <?= $cls('maks_anggota') ?>">
                <p class="mt-1 text-xs text-slate-400">Termasuk pengaju. 1 = tidak boleh ada teman satu tempat.</p>
                <?= $err('maks_anggota') ?>
            </div>
        </div>
    </section>

    <!-- Pagar tanggal -->
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Pagar tanggal &amp; lama PKL</h3>
        <div class="space-y-4 p-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_awal">PKL paling awal mulai</label>
                    <input id="f_awal" type="date" name="mulai_paling_awal" value="<?= esc($nilai('mulai_paling_awal'), 'attr') ?>" class="inp <?= $cls('mulai_paling_awal') ?>">
                    <?= $err('mulai_paling_awal') ?>
                </div>
                <div>
                    <label class="lbl" for="f_akhir">PKL paling akhir selesai</label>
                    <input id="f_akhir" type="date" name="selesai_paling_akhir" value="<?= esc($nilai('selesai_paling_akhir'), 'attr') ?>" class="inp <?= $cls('selesai_paling_akhir') ?>">
                    <?= $err('selesai_paling_akhir') ?>
                </div>
                <div>
                    <label class="lbl" for="f_dmin">Lama PKL minimal (hari)</label>
                    <input id="f_dmin" type="number" min="1" max="365" name="durasi_min_hari" value="<?= esc($nilai('durasi_min_hari', '30'), 'attr') ?>" class="inp <?= $cls('durasi_min_hari') ?>">
                    <?= $err('durasi_min_hari') ?>
                </div>
                <div>
                    <label class="lbl" for="f_dmaks">Lama PKL maksimal (hari)</label>
                    <input id="f_dmaks" type="number" min="1" max="730" name="durasi_maks_hari" value="<?= esc($nilai('durasi_maks_hari', '270'), 'attr') ?>" class="inp <?= $cls('durasi_maks_hari') ?>">
                    <?= $err('durasi_maks_hari') ?>
                </div>
            </div>
            <p class="text-xs text-slate-400">Contoh: PKL boleh mulai paling awal 4 Januari dan selesai paling akhir 30 Juni; tiap perusahaan boleh memilih tanggalnya sendiri di dalam rentang itu.</p>
        </div>
    </section>

    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-sm text-slate-600 shadow-sm">
        Dengan pengaturan tersimpan saat ini, <b><?= (int) $siswa['total'] ?></b> siswa dihitung (belum mengisi: <b><?= (int) $siswa['belum_isi'] ?></b>).
        Perubahan tingkat langsung mengubah daftar kelas dan nama yang dilihat siswa.
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan pengaturan</button>
    </div>
</form>

<?= $this->endSection() ?>
