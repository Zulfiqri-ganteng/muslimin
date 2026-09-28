<?php
/**
 * Satu baris sesi absensi — dipakai tampilan PER GURU & PER KELAS.
 * Atribut data-* & data-role dibaca isiJson()/stats() di index.php: jangan
 * diubah tanpa menyesuaikan skrip di sana.
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
?>
<div x-data="{ gid: <?= (int) $gid ?>, sh: '<?= esc($sh, 'js') ?>', mulai: '<?= esc($mulai, 'js') ?>', st: '<?= esc($s['status'], 'js') ?>', jm: '<?= esc($s['jam_masuk'], 'js') ?>', ket: '<?= esc($s['keterangan'], 'js') ?>' }"
     x-show="sh === shift"
     x-on:absen-setall.window="if ($event.detail.gid === gid && $event.detail.shift === sh) { st = $event.detail.val; if (st === 'hadir') { jm = ''; ket = ''; } }"
     x-on:absen-datang.window="if ($event.detail.gid === gid && $event.detail.shift === sh && st === 'hadir' && mulai <= $event.detail.jam) { st = 'telat'; jm = $event.detail.jam; }"
     data-guru-id="<?= (int) $gid ?>" data-guru-asli="<?= (int) $s['guru_id'] ?>" data-shift="<?= esc($sh, 'attr') ?>"
     data-kelas="<?= (int) $s['kelas_id'] ?>" data-jam="<?= (int) $s['jam_id'] ?>"
     data-hari="<?= (int) $s['hari_id'] ?>" data-mapel="<?= (int) ($s['mapel_id'] ?? 0) ?>"
     data-jadwal="<?= (int) $s['jadwal_id'] ?>"
     class="absen-row p-4 border-l-4 transition"
     :class="{
        'border-emerald-400': st==='hadir', 'border-amber-400': st==='telat',
        'border-sky-400': st==='izin', 'border-violet-400': st==='sakit', 'border-red-400': st==='alpa'<?php if ($kelas): ?>,
        'bg-red-50': isBelum(gid)<?php endif; ?>
     }">

    <div class="flex flex-col md:flex-row md:items-center gap-3">
        <div class="md:w-64 shrink-0">
            <p class="text-sm font-bold text-slate-700">Jam <?= esc($s['jam_ke']) ?>
                <span class="text-slate-400 font-normal text-xs">(<?= esc($mulai) ?>–<?= esc(substr((string) $s['waktu_selesai'], 0, 5)) ?>)</span>
            </p>
            <?php if ($kelas): ?>
                <p class="text-sm font-semibold text-slate-700 truncate"><?= esc($namaGuru ?? '') ?>
                    <span x-show="piket[shift].indexOf(gid) !== -1" x-cloak class="ml-1 align-middle rounded-full bg-brand-50 text-brand-700 border border-brand-200 px-1.5 py-0.5 text-[10px] font-semibold">Piket</span>
                </p>
                <button type="button" x-show="!isBelum(gid)" @click="tandaiBelum(gid)"
                        class="mt-1 text-xs font-semibold text-red-600 hover:text-red-700">Tandai belum hadir</button>
                <p x-show="isBelum(gid)" x-cloak class="mt-1 text-xs font-semibold">
                    <span class="text-red-700">Belum hadir</span> &middot;
                    <button type="button" @click="sudahDatang(gid)" class="text-emerald-700 hover:text-emerald-800">Sudah datang</button>
                </p>
            <?php else: ?>
                <p class="text-sm text-slate-600"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold"><?= esc($s['nama_mapel']) ?></span></p>
            <?php endif; ?>
        </div>
        <div class="shrink-0">
            <select x-model="st" data-role="status"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                <?php foreach ($statusOpts as $k => $lbl): ?>
                    <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 flex-1" x-show="st !== 'hadir'" x-cloak>
            <input type="time" x-model="jm" data-role="jm"
                   class="w-full sm:w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none"
                   title="Jam masuk (opsional)">
            <input type="text" x-model="ket" data-role="ket" maxlength="255"
                   placeholder="Keterangan (opsional)…"
                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
        </div>
    </div>
</div>
