<?php
/**
 * Pembuat soal per jadwal ujian — KHUSUS ADMIN. Jumlah penugasan tiap guru dalam periode ini menjadi
 * angka "Pembuatan Soal" otomatis di honor (masih bisa diubah di grid honor).
 * Rombak tampilan F7: kartu per jadwal, lencana guru dengan tombol cabut, tombol ber-ikon, keadaan kosong kit.
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

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Honor</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ujian_pembuat_soal_v1',
    'helpTitle' => 'Pembuat Soal Ujian',
    'helpBody'  => '<p>Tugaskan guru pembuat soal untuk tiap jadwal ujian. Satu penugasan = satu set soal.
        Jumlahnya dipakai sebagai angka <b>Pembuatan Soal</b> di honor ujian (tombol <b>Hitung otomatis</b>) dan tetap bisa diubah di honor.</p>
        <p class="mt-1">Satu jadwal boleh punya lebih dari satu pembuat soal; guru yang sama tidak bisa dipilih dua kali pada jadwal yang sama.</p>',
]) ?>

<div class="mx-auto max-w-5xl space-y-4">
    <div class="kartu kartu-isi">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($label) ?></p>
        <h2 class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Pembuat soal per jadwal</h2>
    </div>

    <?php if ($jadwal === []): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal ujian pada periode ini', 'teks' => 'Isi dulu di tab Jadwal Ujian, lalu kembali ke sini.']) ?></div>
    <?php endif; ?>

    <?php foreach ($jadwal as $j):
        $ts  = strtotime((string) $j['tanggal']);
        $jam = $j['jam_mulai'] ? substr((string) $j['jam_mulai'], 0, 5) . ($j['jam_selesai'] ? '–' . substr((string) $j['jam_selesai'], 0, 5) : '') : 'Sehari penuh';
        $isi = $tugas[(int) $j['id']] ?? [];
        ?>
        <section class="kartu kartu-isi" aria-label="<?= esc($j['nama_mapel'] ?? 'Mapel', 'attr') ?>">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($j['nama_mapel'] ?? 'Mapel sudah dihapus') ?></h3>
                <p class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><?= ikon('calendar-month', 'h-3.5 w-3.5 shrink-0') ?><?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?> · <?= esc($jam) ?> · Tingkat <?= esc($j['tingkat']) ?><?= $j['jurusan_kode'] ? ' ' . esc($j['jurusan_kode']) : '' ?></p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php if ($isi === []): ?><span class="text-sm text-gray-500 dark:text-gray-400">Belum ada pembuat soal.</span><?php endif; ?>
                <?php foreach ($isi as $t): ?>
                    <form method="post" action="<?= $aksi ?>/<?= (int) $t['id'] ?>/hapus" class="lencana lencana-merek gap-1 py-1 ps-3 pe-1 text-sm">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <?= esc($t['nama']) ?>
                        <button type="submit" class="rounded-full p-0.5 hover:bg-brand-200 hover:text-red-700 dark:hover:bg-brand-800" aria-label="Cabut <?= esc($t['nama'], 'attr') ?>" title="Cabut penugasan"><?= ikon('close', 'h-3.5 w-3.5') ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= $aksi ?>" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="jadwal_id" value="<?= (int) $j['id'] ?>">
                <select name="guru_id" required class="inp sm:max-w-sm" aria-label="Pilih pembuat soal">
                    <option value="">— pilih guru —</option>
                    <?php foreach ($guruOpts as $gid => $gl): ?><option value="<?= (int) $gid ?>"><?= esc($gl) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-utama shrink-0"><?= ikon('plus', 'h-4 w-4') ?>Tambah pembuat soal</button>
            </form>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
