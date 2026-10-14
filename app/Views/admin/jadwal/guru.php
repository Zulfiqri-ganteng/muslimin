<?php
/**
 * Halaman Jadwal Guru (admin) — grid jadwal mengajar satu guru, siap dicetak PDF / diunduh Excel untuk dibagikan ke guru ybs.
 * Rombak tampilan F6: kartu + grid kit (pemilih guru otomatis menyaring lewat data-autosubmit; Cetak PDF & Unduh Excel di kepala).
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

<?php if ($guruId): ?>
    <?= $this->section('aksi') ?>
    <a href="<?= site_url('admin/cetak/jadwal-guru/' . $guruId . '/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Cetak PDF</a>
    <a href="<?= site_url('admin/cetak/jadwal-guru/' . $guruId . '/excel') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel</a>
    <?= $this->endSection() ?>
<?php endif; ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_guru',
    'helpTitle' => 'Jadwal Guru',
    'helpBody'  => '<p>Lihat jadwal mengajar <b>per guru</b> (hari, jam, kelas, dan mapel yang diajar). Pilih guru, lalu gunakan tombol <b>Cetak PDF</b> atau <b>Unduh Excel</b> di kanan atas untuk membagikan jadwal ke guru yang bersangkutan satu per satu.</p>
        <p class="mt-1">Isi jadwal diambil otomatis dari menu <b>Jadwal KBM</b> — bila ada perubahan jadwal kelas, halaman ini ikut terbarui.</p>',
]) ?>

<div class="space-y-4">
    <!-- Pemilih guru -->
    <form method="get" class="kartu kartu-isi flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="text-sm font-medium text-gray-900 dark:text-white" for="jg-guru">Guru</label>
        <select id="jg-guru" name="guru_id" data-autosubmit class="inp sm:max-w-md">
            <option value=""<?= $guruId === 0 ? ' selected' : '' ?>><?= empty($guruOpts) ? '— belum ada guru —' : '— pilih guru —' ?></option>
            <?php foreach ($guruOpts as $id => $label): ?>
                <option value="<?= (int) $id ?>"<?= $guruId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (! $guru): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Pilih guru terlebih dahulu', 'teks' => 'Jadwal mengajar guru yang dipilih akan tampil di sini.']) ?></div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada hari aktif atau jam pelajaran', 'teks' => 'Atur Master Hari dan Master Jam terlebih dahulu.']) ?></div>
    <?php elseif (empty($grid)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal mengajar', 'teks' => 'Belum ada jadwal untuk ' . $guru['nama'] . '. Susun dulu di menu Jadwal KBM.']) ?></div>
    <?php else: ?>
        <?php
            // Pisahkan jam per shift → tiap shift jadi kartu/tabel sendiri; shift yang tak dipakai guru ini disembunyikan.
            $byShift = [];
            foreach ($jam as $j) {
                $byShift[$j['shift']][] = $j;
            }
            $shiftLabel = ['pagi' => 'Pagi', 'siang' => 'Siang'];
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
        <!-- Ringkasan guru -->
        <div class="kartu kartu-isi flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc($guru['nama']) ?></h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Kode <?= esc($guru['kode_guru']) ?><?= $guru['nip'] ? ' · NIP ' . esc($guru['nip']) : '' ?></p>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400"><span class="text-2xl font-bold text-gray-900 dark:text-white"><?= count($grid) ?></span> JP/minggu terjadwal</p>
        </div>

        <!-- Satu kartu per shift -->
        <?php foreach ($byShift as $shift => $jamShift): ?>
            <section class="kartu" aria-label="Shift <?= esc($shiftLabel[$shift] ?? ucfirst($shift), 'attr') ?>">
                <div class="kartu-kepala"><h3 class="text-base font-semibold text-gray-900 dark:text-white">Shift <?= esc($shiftLabel[$shift] ?? ucfirst($shift)) ?></h3></div>
                <div class="tabel-bungkus">
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-gray-100 text-xs font-medium text-gray-500 uppercase dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="w-36 p-3 text-left">Jam</th>
                                <?php foreach ($hari as $h): ?>
                                    <th scope="col" class="min-w-28 border-l border-gray-200 p-3 text-center dark:border-gray-600"><?= esc($h['nama']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($jamShift as $j): ?>
                                <?php if (! empty($j['is_istirahat'])): ?>
                                    <tr>
                                        <td colspan="<?= count($hari) + 1 ?>" class="bg-yellow-50 py-1.5 text-center text-xs font-semibold tracking-wider text-yellow-800 dark:bg-yellow-500/10 dark:text-yellow-300">
                                            ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td class="p-3 align-top whitespace-nowrap">
                                            <span class="font-semibold text-gray-900 dark:text-white">Jam ke <?= esc($j['jam_ke']) ?></span><br>
                                            <span class="text-xs text-gray-500 dark:text-gray-400"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                        </td>
                                        <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                                            <td class="border-l border-gray-200 p-1.5 align-top dark:border-gray-700">
                                                <?php if ($c): ?>
                                                    <div class="rounded-lg border border-green-200 bg-green-50 p-1.5 dark:border-green-500/30 dark:bg-green-500/10">
                                                        <p class="text-sm leading-tight text-green-700 dark:text-green-400"><?= esc($c['nama_kelas']) ?></p>
                                                        <p class="text-sm leading-tight font-semibold text-green-900 dark:text-green-300"><?= esc($c['nama_mapel']) ?></p>
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
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
