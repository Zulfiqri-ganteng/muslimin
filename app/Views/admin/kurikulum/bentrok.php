<?php
/**
 * Deteksi Bentrok jadwal (rombak tampilan F6: kartu + tabel kit). R1 bentrok guru, R2 bentrok kelas, R3 guru dijadwalkan saat tidak
 * tersedia, R4 jumlah JP belum sesuai penugasan. Ringkasan di atas = kotak peringatan kit (status halaman, bukan pesan aksi).
 *
 * @var array $bentrokGuru   R1
 * @var array $bentrokKelas  R2
 * @var array $ketViolations R3
 * @var array $jpMismatch    R4
 */
$totalBentrok = count($bentrokGuru) + count($bentrokKelas) + count($ketViolations);

// Satu kartu bagian: judul + titik warna + jumlah, lalu tabel (atau "Tidak ada").
$bagian = static function (string $judul, string $titik, array $rows, array $kolom, callable $sel): void { ?>
    <section class="kartu" aria-label="<?= esc($judul, 'attr') ?>">
        <div class="kartu-kepala">
            <h2 class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                <span class="h-2.5 w-2.5 rounded-full <?= $titik ?>" aria-hidden="true"></span><?= esc($judul) ?>
            </h2>
            <span class="lencana <?= $rows === [] ? 'lencana-hijau' : 'lencana-merah' ?>"><?= count($rows) ?></span>
        </div>
        <?php if ($rows === []): ?>
            <p class="kartu-isi text-sm text-gray-500 dark:text-gray-400">Tidak ada.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><?php foreach ($kolom as $k): ?><th scope="col"><?= esc($k) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr><?php foreach ($sel($r) as $isi): ?><td><?= $isi ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php };
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'bentrok',
    'helpTitle' => 'Deteksi Bentrok',
    'helpBody'  => '<p>Pemeriksaan menyeluruh jadwal (selalu real-time): bentrok guru (R1), bentrok kelas (R2), guru dijadwalkan saat tidak tersedia (R3), dan jumlah JP belum sesuai penugasan (R4). Perbaiki di menu Jadwal KBM.</p>',
]) ?>

<div class="space-y-4">
    <?php if ($totalBentrok === 0 && empty($jpMismatch)): ?>
        <?= ui_peringatan('sukses', 'Semua jadwal valid dan jumlah JP sudah sesuai penugasan.', ['judul' => 'Tidak ada bentrok']) ?>
    <?php else: ?>
        <?= ui_peringatan('galat', 'Ditemukan ' . $totalBentrok . ' bentrok dan ' . count($jpMismatch) . ' ketidaksesuaian JP. Rinciannya di bawah — perbaiki di menu Jadwal KBM.', ['judul' => 'Jadwal perlu diperbaiki']) ?>
    <?php endif; ?>

    <?php
    $bagian('Bentrok guru (R1)', 'bg-red-500', $bentrokGuru,
        ['Guru', 'Hari', 'Jam ke', 'Kelas yang bentrok'],
        fn ($r) => [esc($r['kode_guru'] . ' · ' . $r['guru_nama']), esc($r['hari']), esc($r['jam_ke']), esc($r['kelas_list'])]);

    $bagian('Bentrok kelas (R2)', 'bg-red-500', $bentrokKelas,
        ['Kelas', 'Hari', 'Jam ke', 'Mapel yang bentrok'],
        fn ($r) => [esc($r['nama_kelas']), esc($r['hari']), esc($r['jam_ke']), esc($r['mapel_list'])]);

    $bagian('Guru dijadwalkan saat tidak tersedia (R3)', 'bg-yellow-400', $ketViolations,
        ['Guru', 'Kelas', 'Mapel', 'Hari', 'Jam ke'],
        fn ($r) => [esc($r['kode_guru'] . ' · ' . $r['guru_nama']), esc($r['nama_kelas']), esc($r['nama_mapel']), esc($r['hari']), esc($r['jam_ke'])]);

    $bagian('Jumlah JP belum sesuai (R4)', 'bg-brand-500', $jpMismatch,
        ['Kelas', 'Mapel', 'Guru', 'Terjadwal', 'Seharusnya'],
        fn ($r) => [
            esc($r['nama_kelas']), esc($r['nama_mapel']), esc($r['guru_nama']),
            '<b class="' . ((int) $r['jp_terjadwal'] < (int) $r['jp_harus'] ? 'text-yellow-700 dark:text-yellow-400' : 'text-red-600 dark:text-red-500') . '">' . (int) $r['jp_terjadwal'] . ' JP</b>',
            (int) $r['jp_harus'] . ' JP',
        ]);
    ?>
</div>
<?= $this->endSection() ?>
