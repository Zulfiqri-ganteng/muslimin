<?php
/**
 * Saringan halaman Supervisi (Jadwal & Penilaian): tahun ajaran, semester, status, guru — formulir GET, terkirim otomatis saat pilihan
 * diganti (data-autosubmit, app.js). Dipanggil: view('admin/supervisi/saringan', [...], ['saveData' => false]).
 *
 * @var string $base
 * @var array{ta: string, semester: string, status: string, guru_id: int} $saring
 * @var list<string> $pilihanTa
 * @var list<array{id: int, nama: string}> $guru
 */

use App\Libraries\Supervisi;
?>
<form method="get" action="<?= $base ?>" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" aria-label="Saring jadwal">
    <select name="ta" class="inp alat-crud-pilih" aria-label="Tahun ajaran" data-autosubmit>
        <?php foreach ($pilihanTa as $ta): ?>
            <option value="<?= esc($ta, 'attr') ?>"<?= $ta === $saring['ta'] ? ' selected' : '' ?>>TA <?= esc($ta) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="semester" class="inp alat-crud-pilih" aria-label="Semester" data-autosubmit>
        <?php foreach (Supervisi::SEMESTER as $k => $label): ?>
            <option value="<?= $k ?>"<?= $k === $saring['semester'] ? ' selected' : '' ?>>Semester <?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="inp alat-crud-pilih" aria-label="Status" data-autosubmit>
        <option value="">Semua status</option>
        <?php foreach (Supervisi::STATUS as $k => $label): ?>
            <option value="<?= $k ?>"<?= $k === $saring['status'] ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="guru_id" class="inp alat-crud-pilih max-w-56" aria-label="Guru" data-autosubmit>
        <option value="">Semua guru</option>
        <?php foreach ($guru as $g): ?>
            <option value="<?= $g['id'] ?>"<?= $g['id'] === $saring['guru_id'] ? ' selected' : '' ?>><?= esc($g['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-netral">Tampilkan</button></noscript>
</form>
