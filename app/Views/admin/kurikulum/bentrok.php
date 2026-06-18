<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'bentrok',
    'helpTitle' => 'Deteksi Bentrok',
    'helpBody'  => '<p>Pemeriksaan menyeluruh jadwal (selalu real-time): bentrok guru (R1), bentrok kelas (R2), guru dijadwalkan saat tidak tersedia (R3), dan jumlah JP belum sesuai penugasan (R4). Perbaiki di menu Jadwal KBM.</p>',
]) ?>

<?php
$totalBentrok = count($bentrokGuru) + count($bentrokKelas) + count($ketViolations);
?>
<?php if ($totalBentrok === 0 && empty($jpMismatch)): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 flex items-center gap-3 mb-5">
        <svg class="w-8 h-8 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div><p class="font-bold text-emerald-800">Tidak ada bentrok 🎉</p><p class="text-sm text-emerald-600">Semua jadwal valid dan jumlah JP sudah sesuai penugasan.</p></div>
    </div>
<?php else: ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 mb-5 text-sm text-red-700">
        Ditemukan <b><?= $totalBentrok ?></b> bentrok dan <b><?= count($jpMismatch) ?></b> ketidaksesuaian JP. Detail di bawah.
    </div>
<?php endif; ?>

<?php
$section = static function (string $title, string $color, array $rows, array $cols, callable $rowCb) {
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-5">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full <?= $color ?>"></span>
            <h2 class="font-bold text-slate-800"><?= esc($title) ?> <span class="text-slate-400 font-normal">(<?= count($rows) ?>)</span></h2>
        </div>
        <?php if (empty($rows)): ?>
            <p class="px-5 py-6 text-sm text-slate-400">Tidak ada.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left"><tr>
                        <?php foreach ($cols as $c): ?><th class="px-5 py-2 font-semibold"><?= esc($c) ?></th><?php endforeach; ?>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r) {
                            echo '<tr>';
                            foreach ($rowCb($r) as $cell) {
                                echo '<td class="px-5 py-2">' . $cell . '</td>';
                            }
                            echo '</tr>';
                        } ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
};

// R1 Bentrok Guru
$section('Bentrok Guru (R1)', 'bg-red-500', $bentrokGuru,
    ['Guru', 'Hari', 'Jam ke', 'Kelas yang bentrok'],
    fn ($r) => [esc($r['kode_guru'] . ' · ' . $r['guru_nama']), esc($r['hari']), esc($r['jam_ke']), esc($r['kelas_list'])]);

// R2 Bentrok Kelas
$section('Bentrok Kelas (R2)', 'bg-red-500', $bentrokKelas,
    ['Kelas', 'Hari', 'Jam ke', 'Mapel yang bentrok'],
    fn ($r) => [esc($r['nama_kelas']), esc($r['hari']), esc($r['jam_ke']), esc($r['mapel_list'])]);

// R3 Pelanggaran Ketersediaan
$section('Guru Dijadwalkan Saat Tidak Tersedia (R3)', 'bg-amber-500', $ketViolations,
    ['Guru', 'Kelas', 'Mapel', 'Hari', 'Jam ke'],
    fn ($r) => [esc($r['kode_guru'] . ' · ' . $r['guru_nama']), esc($r['nama_kelas']), esc($r['nama_mapel']), esc($r['hari']), esc($r['jam_ke'])]);

// R4 JP belum sesuai
$section('Jumlah JP Belum Sesuai (R4)', 'bg-indigo-500', $jpMismatch,
    ['Kelas', 'Mapel', 'Guru', 'Terjadwal', 'Seharusnya'],
    fn ($r) => [
        esc($r['nama_kelas']), esc($r['nama_mapel']), esc($r['guru_nama']),
        '<b class="' . ((int) $r['jp_terjadwal'] < (int) $r['jp_harus'] ? 'text-amber-600' : 'text-orange-600') . '">' . (int) $r['jp_terjadwal'] . ' JP</b>',
        (int) $r['jp_harus'] . ' JP',
    ]);
?>

<?= $this->endSection() ?>
