<?php
/**
 * Impor riwayat PKL lama dari Excel (Operator/Admin).
 *
 * @var ?array  $pratinjau  hasil PklImpor::baca (null = belum unggah)
 * @var string  $token
 */

use App\Libraries\IsianBantu;
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_impor_v1',
    'helpTitle' => 'Impor Riwayat PKL Lama',
    'helpBody'  => '<p>Untuk memasukkan data siswa yang <b>sudah PKL sebelum sistem ini dipakai</b>, supaya tercatat sudah PKL dan tidak bisa mengajukan lagi.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li>Unduh <b>contoh berkas</b>, isi satu baris per siswa. Judul kolom di baris pertama jangan diubah.</li>'
        . '<li>Siswa dikenali dari <b>NIS</b>; bila NIS kosong, dari <b>Nama + Kelas</b> (harus sama dengan Master Siswa).</li>'
        . '<li>Baris dengan perusahaan dan tanggal yang sama digabung jadi <b>satu ajuan</b>.</li>'
        . '<li>Anda melihat <b>pratinjau dulu</b>; belum ada yang tersimpan sampai menekan Simpan. Hasilnya berstatus Disetujui.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'impor', 'hitungTab' => $hitungTab]) ?>

<div class="mx-auto max-w-4xl space-y-5">
    <section class="rise rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-800">1. Siapkan &amp; unggah berkas</h2>
        <p class="mt-1 text-sm text-slate-500">Kolom: <b>NIS, Nama, Kelas, Perusahaan, Alamat, Kota, Telepon, Kontak, Jabatan, Mulai, Selesai</b>. Wajib: Perusahaan, Mulai, Selesai, dan NIS (atau Nama + Kelas).</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <a href="<?= site_url('admin/pkl/impor/contoh') ?>" class="inline-flex shrink-0 justify-center rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">⬇ Unduh contoh berkas</a>
            <form method="post" action="<?= site_url('admin/pkl/impor/pratinjau') ?>" enctype="multipart/form-data" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <?= csrf_field() ?>
                <input type="file" name="berkas" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3.5 file:py-2 file:font-semibold file:text-brand-700">
                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Lihat pratinjau</button>
            </form>
        </div>
    </section>

    <?php if ($pratinjau !== null): $r = $pratinjau['ringkas']; ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-800">2. Pratinjau</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach ([['Baris dibaca', $r['baris'], 'text-slate-800'], ['Ajuan akan dibuat', $r['kelompok'], 'text-green-600'], ['Siswa tercatat PKL', $r['siswa'], 'text-brand-700'], ['Baris bermasalah', $r['galat'], $r['galat'] > 0 ? 'text-red-600' : 'text-slate-400']] as [$l, $n, $w]): ?>
                    <div class="rounded-xl border border-slate-200 px-4 py-3"><p class="text-xs font-semibold text-slate-400"><?= esc($l) ?></p><p class="mt-0.5 text-2xl font-extrabold <?= $w ?>"><?= (int) $n ?></p></div>
                <?php endforeach; ?>
            </div>

            <?php if ($pratinjau['galat'] !== []): ?>
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-bold text-red-800">Baris ini TIDAK akan diimpor — perbaiki di Excel lalu unggah lagi bila perlu:</p>
                    <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm text-red-700">
                        <?php foreach ($pratinjau['galat'] as $g): ?><li><b>Baris <?= (int) $g['baris'] ?>:</b> <?= esc($g['pesan']) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($pratinjau['kelompok'] !== []): ?>
                <div class="mt-4 max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                    <?php foreach ($pratinjau['kelompok'] as $k): ?>
                        <div class="px-4 py-3">
                            <p class="font-semibold text-slate-800"><?= esc($k['perusahaan']) ?> <span class="text-xs font-normal text-slate-400"><?= esc($k['kota']) ?></span></p>
                            <p class="text-xs text-slate-500"><?= esc(IsianBantu::tanggalIndo($k['mulai'])) ?> – <?= esc(IsianBantu::tanggalIndo($k['selesai'])) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= esc(implode(', ', array_map(static fn ($s) => $s['nama'] . ' (' . $s['kelas'] . ')', $k['siswa']))) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= site_url('admin/pkl/impor/simpan') ?>" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
                      x-data="{ proses: false }" @submit="proses = true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
                    <p class="text-xs text-slate-500 sm:mr-auto">Hasilnya berstatus <b>Disetujui</b>; siswanya langsung terkunci dan tak bisa mengajukan lagi.</p>
                    <button type="submit" :disabled="proses" class="rounded-xl bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-60">
                        <span x-text="proses ? 'Menyimpan…' : 'Simpan <?= (int) $r['kelompok'] ?> ajuan'"></span>
                    </button>
                </form>
            <?php else: ?>
                <p class="mt-4 text-center text-sm font-semibold text-slate-500">Tidak ada baris yang bisa diimpor.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
