<?php
/**
 * Halaman Jadwal Guru (admin) — grid jadwal mengajar satu guru,
 * siap dicetak PDF / diunduh Excel untuk dibagikan ke guru ybs.
 *
 * @var array      $guruOpts Opsi guru (id => "kode - nama")
 * @var int        $guruId   Guru terpilih (0 = belum pilih)
 * @var array|null $guru     Baris guru terpilih
 * @var array      $hari     Hari aktif terurut
 * @var array      $jam      Seluruh jam (kedua shift, urut waktu, termasuk istirahat)
 * @var array      $grid     Peta sel jadwal (kunci "hariId-jamId")
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_guru',
    'helpTitle' => 'Jadwal Guru',
    'helpBody'  => '<p>Lihat jadwal mengajar <b>per guru</b> (hari, jam, kelas, dan mapel yang diajar). Pilih guru pada dropdown, lalu gunakan tombol <b>Cetak PDF</b> atau <b>Unduh Excel</b> untuk membagikan jadwal ke guru yang bersangkutan satu per satu.</p>
        <p class="mt-1">Isi jadwal diambil otomatis dari menu <b>Jadwal KBM</b> — bila ada perubahan jadwal kelas, halaman ini ikut terbarui.</p>',
]) ?>

<div>
    <!-- Pemilih guru + tombol cetak -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <form method="get" class="flex flex-1 items-center gap-2 min-w-0">
                <label class="text-sm font-semibold text-slate-600 shrink-0">Guru:</label>
                <select name="guru_id" data-autosubmit class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none min-w-[200px]">
                    <option value="" <?= $guruId === 0 ? 'selected' : '' ?>><?= empty($guruOpts) ? '— belum ada guru —' : '— pilih guru —' ?></option>
                    <?php foreach ($guruOpts as $id => $label): ?>
                        <option value="<?= $id ?>" <?= $guruId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($guruId): ?>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    <a href="<?= site_url('admin/cetak/jadwal-guru/' . $guruId . '/pdf') ?>" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak PDF
                    </a>
                    <a href="<?= site_url('admin/cetak/jadwal-guru/' . $guruId . '/excel') ?>"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
                        Unduh Excel
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (! $guru): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Pilih guru terlebih dahulu untuk melihat jadwal mengajarnya.</div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada data hari aktif atau jam pelajaran.</div>
    <?php elseif (empty($grid)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada jadwal mengajar untuk <?= esc($guru['nama']) ?>. Susun dulu di menu Jadwal KBM.</div>
    <?php else: ?>
        <?php
            // Pisahkan jam per shift → tiap shift jadi kartu/tabel sendiri.
            $byShift = [];
            foreach ($jam as $j) {
                $byShift[$j['shift']][] = $j;
            }
            $shiftLabel = ['pagi' => 'Pagi', 'siang' => 'Siang'];

            // Sembunyikan shift yang sama sekali tidak dipakai guru ini.
            foreach ($byShift as $shift => $jamShift) {
                $dipakai = false;
                foreach ($jamShift as $j) {
                    foreach ($hari as $h) {
                        if (isset($grid[$h['id'] . '-' . $j['id']])) {
                            $dipakai = true;
                            break 2;
                        }
                    }
                }
                if (! $dipakai) {
                    unset($byShift[$shift]);
                }
            }
        ?>
        <div class="space-y-5">
            <!-- Ringkasan guru -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-lg text-slate-800"><?= esc($guru['nama']) ?></h2>
                    <p class="text-sm text-slate-400">Kode <?= esc($guru['kode_guru']) ?><?= $guru['nip'] ? ' · NIP ' . esc($guru['nip']) : '' ?></p>
                </div>
                <p class="text-sm text-slate-500"><span class="font-extrabold text-brand-700 text-lg"><?= count($grid) ?></span> <span class="text-slate-400">JP/minggu terjadwal</span></p>
            </div>

            <!-- Satu kartu per shift -->
            <?php foreach ($byShift as $shift => $jamShift): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 bg-brand-50">
                        <h3 class="font-bold text-brand-700 text-sm uppercase tracking-wider">Shift <?= esc($shiftLabel[$shift] ?? ucfirst($shift)) ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500">
                                    <th class="px-3 py-3 text-left font-semibold w-36 border-b border-slate-100">Jam</th>
                                    <?php foreach ($hari as $h): ?>
                                        <th class="px-2 py-3 text-center font-semibold border-b border-l border-slate-100 min-w-[110px]"><?= esc($h['nama']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jamShift as $j): ?>
                                    <?php if (! empty($j['is_istirahat'])): ?>
                                        <tr><td colspan="<?= count($hari) + 1 ?>" class="bg-amber-50 text-amber-700 text-center text-xs font-semibold py-1.5 border-b border-slate-100 tracking-wider">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td></tr>
                                    <?php else: ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3 py-2 border-b border-slate-100 whitespace-nowrap align-top">
                                                <span class="font-bold text-brand-700">Jam ke <?= esc($j['jam_ke']) ?></span><br>
                                                <span class="text-slate-400 text-xs"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                            </td>
                                            <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                                                <td class="p-1.5 border-b border-l border-slate-100 align-top">
                                                    <?php if ($c): ?>
                                                        <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-1.5">
                                                            <p class="text-emerald-700 text-sm leading-tight"><?= esc($c['nama_kelas']) ?></p>
                                                            <p class="text-sm font-bold text-emerald-800 leading-tight"><?= esc($c['nama_mapel']) ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
