<?php
/**
 * Tab Rekap — ringkasan satu periode + pintu ke cetakan PDF/Excel.
 *
 * Angkanya berasal dari App\Libraries\UjianReport::hitung(), sumber yang
 * sama dengan berkas cetak, jadi layar dan cetakan tidak mungkin berbeda.
 *
 * @var array            $periode
 * @var string           $base
 * @var string           $qtp
 * @var int              $jadwalTotal
 * @var array            $jadwalPerTingkat
 * @var int              $pengawasTotal
 * @var int              $takHadirTotal
 * @var array            $perStatus
 * @var array            $perAlasan
 * @var array<int,array> $perKelas
 * @var array<int,array> $perMapel
 * @var array<int,array> $siswaTerbanyak
 */
$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
?>

<!-- Tombol cetak -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5 flex flex-wrap items-center gap-2">
    <div>
        <h2 class="font-bold text-slate-800">Rekap Periode</h2>
        <p class="text-xs text-slate-500 mt-0.5">Angka di layar ini sama persis dengan isi berkas cetaknya.</p>
    </div>
    <div class="flex-1"></div>
    <a href="<?= $base ?>/laporan/pdf<?= $qtp ?>" target="_blank"
       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3.5 py-2.5 hover:bg-slate-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Rekap PDF
    </a>
    <a href="<?= $base ?>/laporan/excel<?= $qtp ?>"
       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3.5 py-2.5 hover:bg-slate-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Rekap Excel
    </a>
</div>

<!-- Kartu ringkasan -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
    <?php
    $kartu = [
        ['Sesi Terjadwal', $jadwalTotal, 'X ' . $jadwalPerTingkat['X'] . ' · XI ' . $jadwalPerTingkat['XI'] . ' · XII ' . $jadwalPerTingkat['XII'], 'text-brand-700'],
        ['Penugasan Pengawas', $pengawasTotal, 'Opsional', 'text-slate-700'],
        ['Siswa Tidak Hadir', $takHadirTotal, 'Total catatan', 'text-amber-700'],
        ['Susulan Selesai', $perStatus['selesai'], 'Sudah dilaksanakan', 'text-emerald-700'],
    ];
    foreach ($kartu as [$judul, $angka, $ket, $warna]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500"><?= esc($judul) ?></p>
            <p class="text-2xl font-extrabold <?= $warna ?> mt-1"><?= number_format((int) $angka, 0, ',', '.') ?></p>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= esc($ket) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-5 mb-5">
    <!-- Status susulan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-bold text-slate-800">Status Ujian Susulan</h3></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <?php foreach (['belum' => 'Belum dijadwalkan', 'dijadwalkan' => 'Sudah dijadwalkan', 'selesai' => 'Selesai', 'batal' => 'Batal'] as $k => $lbl): ?>
                    <tr>
                        <td class="px-6 py-2.5 text-slate-600"><?= esc($lbl) ?></td>
                        <td class="px-6 py-2.5 text-right font-bold text-slate-700"><?= (int) ($perStatus[$k] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Alasan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-bold text-slate-800">Alasan Ketidakhadiran</h3></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($labelAlasan as $k => $lbl): ?>
                    <tr>
                        <td class="px-6 py-2.5 text-slate-600"><?= esc($lbl) ?></td>
                        <td class="px-6 py-2.5 text-right font-bold text-slate-700"><?= (int) ($perAlasan[$k] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Rekap per kelas -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-5">
    <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-bold text-slate-800">Rekap per Kelas</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="text-left font-semibold px-6 py-3">Kelas</th>
                    <th class="text-left font-semibold px-4 py-3">Tingkat</th>
                    <th class="text-center font-semibold px-4 py-3">Tidak Hadir</th>
                    <th class="text-center font-semibold px-4 py-3">Belum</th>
                    <th class="text-center font-semibold px-4 py-3">Dijadwalkan</th>
                    <th class="text-center font-semibold px-4 py-3">Selesai</th>
                    <th class="text-center font-semibold px-6 py-3">Batal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($perKelas === []): ?>
                    <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada catatan ketidakhadiran.</td></tr>
                <?php else: foreach ($perKelas as $r): ?>
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-2.5 font-semibold text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                        <td class="px-4 py-2.5 text-slate-500"><?= esc($r['tingkat'] ?? '—') ?></td>
                        <td class="px-4 py-2.5 text-center font-bold text-amber-700"><?= (int) $r['total'] ?></td>
                        <td class="px-4 py-2.5 text-center text-slate-600"><?= (int) $r['belum'] ?></td>
                        <td class="px-4 py-2.5 text-center text-slate-600"><?= (int) $r['dijadwalkan'] ?></td>
                        <td class="px-4 py-2.5 text-center text-emerald-700"><?= (int) $r['selesai'] ?></td>
                        <td class="px-6 py-2.5 text-center text-slate-400"><?= (int) $r['batal'] ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Rekap per mapel -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-5">
    <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-bold text-slate-800">Rekap per Mata Pelajaran</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="text-left font-semibold px-6 py-3">Mata Pelajaran</th>
                    <th class="text-center font-semibold px-4 py-3">Tidak Hadir</th>
                    <th class="text-center font-semibold px-4 py-3">Belum</th>
                    <th class="text-center font-semibold px-6 py-3">Selesai</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($perMapel === []): ?>
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">Belum ada catatan ketidakhadiran.</td></tr>
                <?php else: foreach ($perMapel as $r): ?>
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-2.5">
                            <span class="font-semibold text-slate-700"><?= esc($r['nama_mapel'] ?? '—') ?></span>
                            <span class="text-xs text-slate-400"><?= $r['kode_mapel'] ? ' · ' . esc($r['kode_mapel']) : '' ?></span>
                        </td>
                        <td class="px-4 py-2.5 text-center font-bold text-amber-700"><?= (int) $r['total'] ?></td>
                        <td class="px-4 py-2.5 text-center text-slate-600"><?= (int) $r['belum'] ?></td>
                        <td class="px-6 py-2.5 text-center text-emerald-700"><?= (int) $r['selesai'] ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($siswaTerbanyak !== []): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800">Perlu Perhatian</h3>
            <p class="text-xs text-slate-500 mt-0.5">Siswa yang tidak hadir pada lebih dari satu mata pelajaran.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="text-left font-semibold px-6 py-3">Siswa</th>
                        <th class="text-left font-semibold px-4 py-3">Kelas</th>
                        <th class="text-center font-semibold px-6 py-3">Jumlah Mapel</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($siswaTerbanyak as $r): ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-6 py-2.5">
                                <p class="font-semibold text-slate-700"><?= esc($r['nama'] ?? '—') ?></p>
                                <p class="text-xs text-slate-400">NIS <?= esc($r['nis'] ?? '—') ?></p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                            <td class="px-6 py-2.5 text-center font-bold text-amber-700"><?= (int) $r['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
