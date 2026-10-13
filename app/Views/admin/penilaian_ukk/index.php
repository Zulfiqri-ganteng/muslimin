<?php
/**
 * Pintu masuk Penilaian UKK: pilih jadwal, lihat progres nilai.
 *
 * @var array             $rows             Semua jadwal_ukk::withRelations()
 * @var array<int,int>    $totalPerJadwal   jadwal_ukk_id => jumlah peserta
 * @var array<int,int>    $dinilaiPerJadwal jadwal_ukk_id => jumlah sudah dinilai
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'penilaian_ukk',
    'helpTitle' => 'Penilaian UKK',
    'helpBody'  => '<p>Pilih jadwal untuk menilai peserta pada jadwal tersebut. Nilai diisi <b>per penguji</b>
        (internal & eksternal masing-masing punya kolom sendiri) — nilai akhir peserta otomatis jadi rata-rata
        seluruh penguji setelah minimal satu penguji mengisi nilai.</p>',
]) ?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800">Pilih Jadwal untuk Dinilai</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold">Paket Soal</th>
                    <th class="px-4 py-3 font-semibold w-32">Tanggal</th>
                    <th class="px-4 py-3 font-semibold">Tempat Uji</th>
                    <th class="px-4 py-3 font-semibold w-48">Progres Nilai</th>
                    <th class="px-4 py-3 font-semibold w-24 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">Belum ada jadwal UKK. Buat dulu di menu Jadwal UKK.</td></tr>
                <?php else: foreach ($rows as $r):
                    $total   = $totalPerJadwal[$r['id']] ?? 0;
                    $dinilai = $dinilaiPerJadwal[$r['id']] ?? 0;
                    $pct     = $total > 0 ? (int) round($dinilai / $total * 100) : 0; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <div class="font-medium text-gray-800"><?= esc($r['paket_kode'] ?? '—') ?></div>
                            <div class="text-xs text-gray-400"><?= esc($r['paket_nama'] ?? '—') ?></div>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= $tgl($r['tanggal_mulai']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= esc($r['tempat_nama'] ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <?php if ($total === 0): ?>
                                <span class="text-xs text-gray-400">Belum ada peserta</span>
                            <?php else: ?>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full bg-emerald-500" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 w-16 text-right"><?= $dinilai ?>/<?= $total ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= site_url('admin/penilaian-ukk/jadwal/' . (int) $r['id']) ?>"
                               class="inline-flex items-center gap-1 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-xs font-semibold px-3 py-1.5">Nilai</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
