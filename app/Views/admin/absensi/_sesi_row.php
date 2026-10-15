<?php
/**
 * Satu baris sesi absensi — dipakai tampilan PER GURU & PER KELAS (rombak tampilan F6: komponen Alpine "sesiAbsen" di
 * assets/js/admin/absensi/harian.js; nilai awal lewat data-st / data-jm / data-ket / data-mulai → siap CSP).
 * Atribut data-* & data-role dibaca siapkanKirim()/stats() di harian.js: jangan diubah tanpa menyesuaikan skrip di sana.
 *
 * @var array  $s          sesi (AbsensiHarian::muat)
 * @var int    $gid        id ORANG guru pemilik sesi
 * @var array  $statusOpts status => label
 * @var string $mode       'guru' | 'kelas'
 * @var string $namaGuru   nama guru (mode kelas)
 */
$sh    = $s['jam_shift'] ?? $s['shift'] ?? 'pagi';
$mulai = substr((string) $s['waktu_mulai'], 0, 5);
$kelas = ($mode ?? 'guru') === 'kelas';
$st    = (string) ($s['status'] ?? 'hadir');
?>
<div x-data="sesiAbsen" x-show="sh === shift" @absen-setall.window="setDariSemua($event)" @absen-datang.window="tandaiDatang($event)"
     data-guru-id="<?= (int) $gid ?>" data-guru-asli="<?= (int) $s['guru_id'] ?>" data-shift="<?= esc($sh, 'attr') ?>"
     data-kelas="<?= (int) $s['kelas_id'] ?>" data-jam="<?= (int) $s['jam_id'] ?>"
     data-hari="<?= (int) $s['hari_id'] ?>" data-mapel="<?= (int) ($s['mapel_id'] ?? 0) ?>"
     data-jadwal="<?= (int) $s['jadwal_id'] ?>" data-mulai="<?= esc($mulai, 'attr') ?>"
     data-st="<?= esc($st, 'attr') ?>" data-jm="<?= esc((string) ($s['jam_masuk'] ?? ''), 'attr') ?>" data-ket="<?= esc((string) ($s['keterangan'] ?? ''), 'attr') ?>"<?= $kelas ? ' data-mode="kelas"' : '' ?>
     class="absen-row border-l-4 p-4 transition-colors" :class="kelasBaris()">

    <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <div class="shrink-0 md:w-64">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Jam <?= esc($s['jam_ke']) ?>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(<?= esc($mulai) ?>–<?= esc(substr((string) $s['waktu_selesai'], 0, 5)) ?>)</span>
            </p>
            <?php if ($kelas): ?>
                <p class="truncate text-sm font-medium text-gray-900 dark:text-white"><?= esc($namaGuru ?? '') ?>
                    <span x-show="piketShift(gid)" x-cloak class="lencana lencana-merek ms-1 align-middle">Piket</span>
                </p>
                <button type="button" x-show="!isBelum(gid)" @click="tandaiBelum(gid)" class="mt-1 text-xs font-medium text-red-700 hover:underline dark:text-red-400">Tandai belum hadir</button>
                <p x-show="isBelum(gid)" x-cloak class="mt-1 text-xs font-medium">
                    <span class="text-red-700 dark:text-red-500">Belum hadir</span> &middot;
                    <button type="button" @click="sudahDatang(gid)" class="text-green-700 hover:underline dark:text-green-400">Sudah datang</button>
                </p>
            <?php else: ?>
                <p class="text-sm text-gray-600 dark:text-gray-300"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold text-gray-900 dark:text-white"><?= esc($s['nama_mapel']) ?></span></p>
            <?php endif; ?>
        </div>
        <div class="shrink-0">
            <select x-model="st" data-role="status" class="inp w-auto font-medium" aria-label="Status Jam <?= esc((string) $s['jam_ke'], 'attr') ?>">
                <?php foreach ($statusOpts as $k => $lbl): ?>
                    <option value="<?= $k ?>"<?= $st === $k ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-1 flex-col gap-2 sm:flex-row" x-show="st !== 'hadir'" x-cloak>
            <input type="time" x-model="jm" data-role="jm" class="inp sm:w-32" title="Jam masuk (opsional)" aria-label="Jam masuk">
            <input type="text" x-model="ket" data-role="ket" maxlength="255" placeholder="Keterangan (opsional)…" class="inp flex-1" aria-label="Keterangan">
        </div>
    </div>
</div>
