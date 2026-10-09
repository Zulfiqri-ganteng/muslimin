<?php
/**
 * Pembuat soal per jadwal ujian — KHUSUS ADMIN. Jumlah penugasan tiap guru dalam periode ini menjadi
 * angka "Pembuatan Soal" otomatis di honor (masih bisa diubah di grid honor).
 *
 * @var string $slug  @var array $periode  @var string $label
 * @var list<array<string,mixed>> $jadwal  jadwal ujian periode (dengan nama_mapel, jurusan_kode)
 * @var array<int,list<array<string,mixed>>> $tugas  jadwal_id => [id, nama]
 * @var array<int,string> $guruOpts  id => "kode - nama"
 * @var string $kembali
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$aksi = site_url('admin/ujian/' . $slug . '/pembuat-soal');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ujian_pembuat_soal_v1',
    'helpTitle' => 'Pembuat Soal Ujian',
    'helpBody'  => '<p>Tugaskan guru pembuat soal untuk tiap jadwal ujian. Satu penugasan = satu set soal.
        Jumlahnya dipakai sebagai angka <b>Pembuatan Soal</b> di honor ujian (tombol <b>Hitung otomatis</b>) dan tetap bisa diubah di honor.</p>
        <p class="mt-1">Satu jadwal boleh punya lebih dari satu pembuat soal; guru yang sama tidak bisa dipilih dua kali pada jadwal yang sama.</p>',
]) ?>

<div class="mx-auto max-w-5xl space-y-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= esc($label) ?></p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Pembuat soal per jadwal</h2>
            </div>
            <a href="<?= esc($kembali) ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Kembali ke Honor</a>
        </div>
    </section>

    <?php if ($jadwal === []): ?>
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
            <p class="font-semibold text-slate-700">Belum ada jadwal ujian pada periode ini.</p>
            <p class="mt-1 text-sm text-slate-500">Isi dulu di tab <b>Jadwal Ujian</b>, lalu kembali ke sini.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($jadwal as $j):
        $ts  = strtotime((string) $j['tanggal']);
        $jam = $j['jam_mulai'] ? substr((string) $j['jam_mulai'], 0, 5) . ($j['jam_selesai'] ? '–' . substr((string) $j['jam_selesai'], 0, 5) : '') : 'Sehari penuh';
        $isi = $tugas[(int) $j['id']] ?? [];
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                <h3 class="font-bold text-slate-800"><?= esc($j['nama_mapel'] ?? 'Mapel sudah dihapus') ?></h3>
                <p class="text-xs text-slate-500"><?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?> · <?= esc($jam) ?> · Tingkat <?= esc($j['tingkat']) ?><?= $j['jurusan_kode'] ? ' ' . esc($j['jurusan_kode']) : '' ?></p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php if ($isi === []): ?><span class="text-sm text-slate-400">Belum ada pembuat soal.</span><?php endif; ?>
                <?php foreach ($isi as $t): ?>
                    <form method="post" action="<?= $aksi ?>/<?= (int) $t['id'] ?>/hapus" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 py-1 pl-3 pr-1 text-sm font-semibold text-slate-700">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <?= esc($t['nama']) ?>
                        <button type="submit" class="rounded-full px-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Cabut <?= esc($t['nama'], 'attr') ?>" title="Cabut penugasan">×</button>
                    </form>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= $aksi ?>" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="jadwal_id" value="<?= (int) $j['id'] ?>">
                <select name="guru_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 sm:max-w-sm" aria-label="Pilih pembuat soal">
                    <option value="">— pilih guru —</option>
                    <?php foreach ($guruOpts as $gid => $gl): ?><option value="<?= (int) $gid ?>"><?= esc($gl) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">+ Tambah pembuat soal</button>
            </form>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
