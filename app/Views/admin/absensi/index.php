<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'absensi_v4',
    'helpTitle' => 'Cara mengisi absensi guru',
    'helpBody'  => '<p>Pilih <b>tanggal</b> dan <b>shift (Pagi / Siang)</b>. Sistem menampilkan guru yang mengajar pada shift itu dari jadwal KBM — semua <b>default Hadir</b>. Tandai yang <b>Telat / Izin / Sakit / Alpa</b> lewat status tiap sesi atau <b>Set semua</b>.</p>'
        . '<p class="mt-2"><b>Belum Hadir</b> — saat laporan dikirim masih ada guru yang belum datang? Tandai di panel <b>Belum Hadir</b> (atau tombol <b>Belum hadir</b> di kartu guru). Nama mereka masuk bagian "belum hadir" di pesan WhatsApp. Begitu guru datang, klik <b>Sudah datang</b>: sesi yang sudah dimulai otomatis ditandai <b>Telat</b> dengan jam saat itu, lalu Simpan. Guru yang <b>tetap</b> di daftar ini dihitung <b>tidak hadir</b> di rekap.</p>'
        . '<p class="mt-2"><b>Kirim WhatsApp</b> — sekali klik: absensi disimpan, pesan disusun otomatis sesuai <b>Format Pesan</b>, lalu WhatsApp terbuka tinggal pilih grup. Nama guru yang punya <b>No. WhatsApp</b> (Master Guru) otomatis menjadi <b>tag</b> di grup. Format pesan bisa diubah lewat tombol <b>Format Pesan</b>.</p>'
        . '<p class="mt-2"><b>Kehadiran Kerja</b> — untuk guru/staf yang masuk tanpa jadwal KBM (TU, wakil kepala, dsb.). Guru berjabatan struktural diisikan otomatis bertanda <span class="rounded bg-amber-50 text-amber-700 border border-amber-200 px-1">disarankan</span>; hapus yang tidak masuk.</p>'
        . '<p class="mt-2"><b>Penting:</b> hari yang tidak disimpan tidak dihitung di Rekap. Salah menyimpan hari libur? Gunakan <b>Batalkan pencatatan</b>.</p>',
]) ?>

<?php
    $statusOpts = [
        'hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin',
        'sakit' => 'Sakit', 'alpa'  => 'Alpa (Tidak Hadir)',
    ];
    $jabatanMap = $jabatanMap ?? [];
    $saranKerja = $saranKerja ?? [];

    // Kehadiran kerja tersimpan + saran otomatis jabatan struktural (belum tersimpan).
    $kerjaJson = array_map(static fn ($k) => [
        'guru_id'    => (int) $k['guru_id'],
        'nama'       => $k['nama'],
        'jabatan'    => implode(', ', array_column($jabatanMap[(int) $k['guru_id']] ?? [], 'nama')),
        'status'     => $k['status'],
        'shift'      => $k['shift'] ?? 'penuh',
        'jam_masuk'  => $k['jam_masuk'] ?? '',
        'keterangan' => $k['keterangan'] ?? '',
        'saran'      => false,
    ], $kerja);
    foreach ($saranKerja as $s) {
        $kerjaJson[] = [
            'guru_id'    => (int) $s['guru_id'],
            'nama'       => $s['nama'],
            'jabatan'    => $s['jabatan'],
            'status'     => $s['status'],
            'shift'      => $s['shift'] ?? 'penuh',
            'jam_masuk'  => $s['jam_masuk'],
            'keterangan' => $s['keterangan'],
            'saran'      => true,
        ];
    }
    $guruJson = array_map(static fn ($g) => [
        'id'   => (int) $g['id'],
        'nama' => $g['nama'],
        'kode' => $g['kode_guru'] ?? '',
    ], $guruOptions);

    // Guru yang punya sesi mengajar per shift (untuk filter kartu & daftar pilihan).
    $guruShift = ['pagi' => [], 'siang' => []];
    foreach ($grup as $g) {
        foreach ($g['sesi'] as $s) {
            $sh = $s['jam_shift'] ?? $s['shift'] ?? 'pagi';
            $guruShift[$sh][(int) $g['guru_id']] = true;
        }
    }
    $pageCfg = [
        'tanggal'   => $tanggal,
        'shift'     => $shift,
        'belum'     => ['pagi' => $belum['pagi'] ?? [], 'siang' => $belum['siang'] ?? []],
        'piket'     => ['pagi' => $piket['pagi'] ?? [], 'siang' => $piket['siang'] ?? []],
        'guruShift' => ['pagi' => array_keys($guruShift['pagi']), 'siang' => array_keys($guruShift['siang'])],
        'guru'      => $guruJson,
        'template'  => $waTemplate,
        'custom'    => $waCustom,
        'urlSave'   => site_url('admin/absensi/save'),
        'urlTpl'    => site_url('admin/absensi/template-wa'),
        'urlPesan'  => site_url('admin/absensi/pesan-wa'),
    ];
?>

<div x-data='absensiPage(<?= htmlspecialchars(json_encode($pageCfg), ENT_QUOTES) ?>)' @change="tick++">

<!-- ===================== BAR ATAS: tanggal + shift + ringkasan ===================== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3" data-noload>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= esc($tanggal) ?>" @change.stop="$el.form.submit()"
                       class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                <input type="hidden" name="shift" :value="shift">
            </div>
            <div class="pb-1">
                <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= $namaHari !== '' ? esc($namaHari) : 'Hari tak dikenal' ?>
                </span>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Shift KBM</label>
                <div class="inline-flex rounded-xl border border-slate-300 p-1 bg-slate-50">
                    <?php foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $k => $lbl): ?>
                        <button type="button" @click="setShift('<?= $k ?>')"
                                :class="shift === '<?= $k ?>' ? 'bg-brand-700 text-white shadow-sm' : 'text-slate-600 hover:bg-white'"
                                class="rounded-lg px-4 py-1.5 text-sm font-bold transition"><?= $lbl ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>

        <!-- Ringkasan shift terpilih (hidup mengikuti tanda yang sedang tampil) -->
        <div class="flex flex-wrap gap-2">
            <template x-for="it in stats()" :key="it.k">
                <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold" :class="it.c" x-text="it.l + ': ' + it.v"></span>
            </template>
        </div>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <?php if ($recorded): ?>
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Hari ini sudah diabsen — dihitung di rekap. Perubahan bisa disimpan ulang.
            </span>
            <form method="post" action="<?= site_url('admin/absensi/unrecord') ?>" onsubmit="return confirm('Batalkan pencatatan absensi tanggal ini?\n\nSemua tandaan hari ini (termasuk kehadiran kerja & daftar belum hadir) akan DIHAPUS dan hari ini tidak dihitung di rekap.')">
                <?= csrf_field() ?>
                <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                <input type="hidden" name="shift" :value="shift">
                <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Batalkan pencatatan
                </button>
            </form>
        <?php else: ?>
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 text-xs font-bold text-amber-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Belum diabsen — klik <b class="mx-0.5">Simpan Absensi</b> atau <b class="mx-0.5">Kirim WhatsApp</b> untuk mencatat hari ini
            </span>
        <?php endif; ?>
    </div>
</div>

<form id="form-absensi" method="post" action="<?= site_url('admin/absensi/save') ?>" @submit="isiJson()">
    <?= csrf_field() ?>
    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
    <input type="hidden" name="shift" :value="shift">
    <input type="hidden" name="kerja_sync" value="1">
    <!-- Sesi & kehadiran kerja dikirim sebagai JSON (satu isian) agar tidak
         terpotong batas max_input_vars PHP (±340 sesi × 7 isian per hari). -->
    <input type="hidden" name="rows_json" id="rows_json">
    <input type="hidden" name="kerja_json" id="kerja_json">
    <input type="hidden" name="belum_sync" value="1">
    <template x-for="id in belum[shift]" :key="id">
        <input type="hidden" name="belum[]" :value="id">
    </template>

    <!-- ===================== BELUM HADIR (per shift) ===================== -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-800">Belum Hadir <span class="text-slate-400 font-normal text-sm">— KBM <span x-text="shift"></span></span></h3>
                <p class="text-xs text-slate-500 mt-0.5">Guru yang belum datang saat laporan dikirim. Klik <b>Sudah datang</b> ketika guru tiba — sesi yang sudah dimulai otomatis ditandai Telat. Yang tetap di daftar ini dihitung <b>tidak hadir</b> di rekap.</p>
            </div>
            <button type="button" @click="openPicker()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tandai belum hadir
            </button>
        </div>

        <p x-show="belum[shift].length === 0" class="text-sm text-slate-400 py-3 text-center">Belum ada guru yang ditandai belum hadir pada KBM <span x-text="shift"></span>.</p>
        <div x-show="belum[shift].length > 0" x-cloak class="mt-3 space-y-2">
            <template x-for="id in belum[shift]" :key="id">
                <div class="flex items-center gap-3 p-3 rounded-xl border border-red-200 bg-red-50/60">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-slate-700 truncate" x-text="namaGuru(id)"></div>
                        <div class="text-[11px] text-red-700 font-semibold">Belum hadir</div>
                    </div>
                    <button type="button" @click="sudahDatang(id)"
                            class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Sudah datang
                    </button>
                    <button type="button" @click="hapusBelum(id)" class="shrink-0 text-slate-400 hover:text-red-600 p-1.5" title="Hapus dari daftar (salah tandai)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- ===================== KEHADIRAN KERJA (di luar jadwal) ===================== -->
    <div id="panel-kerja" x-data='absensiKerja(<?= htmlspecialchars(json_encode($kerjaJson), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($guruJson), ENT_QUOTES) ?>)'
         class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
        <h3 class="font-bold text-slate-800">Kehadiran Kerja <span class="text-slate-400 font-normal text-sm">(di luar jadwal mengajar)</span></h3>
        <p class="text-xs text-slate-500 mt-0.5">Untuk guru/staf yang <b>masuk kerja</b> tanpa jadwal KBM (staf TU, guru piket, wakil kepala, dsb.). Pilih berlaku <b>Pagi + Siang</b>, pagi saja, atau siang saja. Tetap dihitung di rekap.</p>
        <?php if (! empty($saranKerja)): ?>
            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mt-2">
                <b><?= count($saranKerja) ?> guru berjabatan struktural</b> diisikan otomatis dan ditandai <b>disarankan</b>.
                Belum tersimpan — hapus yang tidak masuk, lalu <b>Simpan</b> / <b>Kirim WhatsApp</b>.
            </p>
        <?php endif; ?>

        <!-- Tambah guru -->
        <div class="flex flex-wrap items-end gap-2 mt-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tambah guru yang masuk</label>
                <select x-model.number="pick" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="0">— pilih guru —</option>
                    <template x-for="g in availableGuru()" :key="g.id">
                        <option :value="g.id" x-text="g.kode ? (g.nama + ' · ' + g.kode) : g.nama"></option>
                    </template>
                </select>
            </div>
            <button type="button" @click="add()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-4 py-2.5 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah
            </button>
        </div>

        <p x-show="rows.length === 0" class="text-sm text-slate-400 py-3 text-center">Belum ada guru yang ditandai masuk kerja pada tanggal ini.</p>
        <div class="space-y-2 mt-4" x-show="rows.length > 0" x-cloak>
            <template x-for="(r, i) in rows" :key="r.guru_id">
                <div class="flex flex-col md:flex-row md:items-center gap-2 p-3 rounded-xl border border-slate-200 bg-slate-50/60">
                    <div class="md:w-52 shrink-0">
                        <div class="font-semibold text-sm text-slate-700" x-text="r.nama"></div>
                        <div class="flex flex-wrap items-center gap-1 mt-0.5">
                            <span x-show="r.jabatan" x-cloak class="text-[11px] text-slate-500" x-text="r.jabatan"></span>
                            <span x-show="isPiket(r.guru_id)" x-cloak
                                  class="rounded-full bg-brand-50 text-brand-700 border border-brand-200 px-1.5 py-0.5 text-[10px] font-semibold" x-text="labelPiket(r.guru_id)"></span>
                            <span x-show="r.saran" x-cloak
                                  title="Disarankan otomatis karena berjabatan struktural. Belum tersimpan."
                                  class="rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 text-[10px] font-semibold">disarankan</span>
                        </div>
                    </div>
                    <select x-model="r.shift" title="Berlaku untuk shift"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-500 outline-none">
                        <option value="penuh">Pagi + Siang</option>
                        <option value="pagi">Pagi saja</option>
                        <option value="siang">Siang saja</option>
                    </select>
                    <select x-model="r.status"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-500 outline-none">
                        <?php foreach ($statusOpts as $k => $lbl): ?>
                            <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="time" x-model="r.jam_masuk" x-show="r.status !== 'hadir'" x-cloak
                           class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none" title="Jam masuk (opsional)">
                    <input type="text" x-model="r.keterangan" maxlength="255"
                           placeholder="Keterangan (opsional)…"
                           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                    <button type="button" @click="remove(i)" class="shrink-0 text-red-500 hover:text-red-600 p-2" title="Hapus">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- ===================== ABSENSI MENGAJAR (shift terpilih) ===================== -->
    <?php if (! $hariAktif): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 mb-5">
            Tanggal ini bukan hari sekolah aktif — <b>tidak ada absensi mengajar</b>. Gunakan panel <b>Kehadiran Kerja</b> di atas bila ada yang masuk.
        </div>
    <?php elseif ($total === 0): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 mb-5">
            Belum ada jadwal KBM pada hari <?= esc($namaHari) ?>. Susun jadwal dulu di menu <b>Jadwal KBM</b> — atau gunakan panel <b>Kehadiran Kerja</b> di atas.
        </div>
    <?php else: ?>
        <div x-show="guruShift[shift].length === 0" x-cloak class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 mb-5">
            Tidak ada sesi mengajar pada KBM <span x-text="shift"></span> hari ini.
        </div>
        <div class="space-y-4">
            <?php foreach ($grup as $g): $gid = (int) $g['guru_id']; ?>
                <div x-show="punyaSesi(<?= $gid ?>)" class="bg-white rounded-2xl border shadow-sm overflow-hidden"
                     :class="isBelum(<?= $gid ?>) ? 'border-red-300' : 'border-slate-200'">
                    <!-- Header guru + set semua -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-3 border-b border-slate-100 bg-slate-50">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-800 truncate"><?= esc($g['nama']) ?>
                                <span x-show="piket[shift].indexOf(<?= $gid ?>) !== -1" x-cloak class="ml-1 align-middle rounded-full bg-brand-50 text-brand-700 border border-brand-200 px-1.5 py-0.5 text-[10px] font-semibold">Piket</span>
                            </h3>
                            <p class="text-xs text-slate-400"><?= esc($g['kode']) ?> &middot; <span x-text="jumlahSesi(<?= $gid ?>) + ' sesi KBM ' + shift"></span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button type="button" x-show="!isBelum(<?= $gid ?>)" @click="tandaiBelum(<?= $gid ?>)"
                                    class="rounded-lg border border-red-200 text-red-600 hover:bg-red-50 px-2.5 py-1.5 text-xs font-bold">Belum hadir</button>
                            <button type="button" x-show="isBelum(<?= $gid ?>)" x-cloak @click="sudahDatang(<?= $gid ?>)"
                                    class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1.5 text-xs font-bold">Sudah datang</button>
                            <label class="text-xs font-semibold text-slate-500">Set semua:</label>
                            <select class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold focus:border-brand-500 outline-none"
                                    @change.stop="$dispatch('absen-setall', { gid: <?= $gid ?>, shift: shift, val: $event.target.value }); $event.target.selectedIndex = 0; $nextTick(() => tick++)">
                                <option value="">— pilih —</option>
                                <?php foreach ($statusOpts as $k => $lbl): ?>
                                    <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Daftar sesi guru (hanya shift terpilih yang tampil) -->
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($g['sesi'] as $s):
                            $sh    = $s['jam_shift'] ?? $s['shift'] ?? 'pagi';
                            $mulai = substr((string) $s['waktu_mulai'], 0, 5);
                        ?>
                            <div x-data="{ gid: <?= $gid ?>, sh: '<?= esc($sh, 'js') ?>', mulai: '<?= esc($mulai, 'js') ?>', st: '<?= esc($s['status'], 'js') ?>', jm: '<?= esc($s['jam_masuk'], 'js') ?>', ket: '<?= esc($s['keterangan'], 'js') ?>' }"
                                 x-show="sh === shift"
                                 x-on:absen-setall.window="if ($event.detail.gid === gid && $event.detail.shift === sh) { st = $event.detail.val; if (st === 'hadir') { jm = ''; ket = ''; } }"
                                 x-on:absen-datang.window="if ($event.detail.gid === gid && $event.detail.shift === sh && st === 'hadir' && mulai <= $event.detail.jam) { st = 'telat'; jm = $event.detail.jam; }"
                                 data-guru-id="<?= $gid ?>" data-guru-asli="<?= (int) $s['guru_id'] ?>" data-shift="<?= esc($sh, 'attr') ?>"
                                 data-kelas="<?= (int) $s['kelas_id'] ?>" data-jam="<?= (int) $s['jam_id'] ?>"
                                 data-hari="<?= (int) $s['hari_id'] ?>" data-mapel="<?= (int) ($s['mapel_id'] ?? 0) ?>"
                                 data-jadwal="<?= (int) $s['jadwal_id'] ?>"
                                 class="absen-row p-4 border-l-4 transition"
                                 :class="{
                                    'border-emerald-400': st==='hadir', 'border-amber-400': st==='telat',
                                    'border-sky-400': st==='izin', 'border-violet-400': st==='sakit', 'border-red-400': st==='alpa'
                                 }">

                                <div class="flex flex-col md:flex-row md:items-center gap-3">
                                    <div class="md:w-64 shrink-0">
                                        <p class="text-sm font-bold text-slate-700">Jam <?= esc($s['jam_ke']) ?>
                                            <span class="text-slate-400 font-normal text-xs">(<?= esc($mulai) ?>–<?= esc(substr((string) $s['waktu_selesai'], 0, 5)) ?>)</span>
                                        </p>
                                        <p class="text-sm text-slate-600"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold"><?= esc($s['nama_mapel']) ?></span></p>
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
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Bar aksi (sticky bawah) -->
    <div class="sticky bottom-0 mt-5 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-white/90 backdrop-blur border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-400 hidden md:block">Tanggal <b><?= esc($tanggal) ?></b> &middot; <?= esc($namaHari) ?> &middot; KBM <b x-text="shift"></b></p>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <button type="button" @click="tplOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-4 py-2.5 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Format Pesan
            </button>
            <button type="button" @click="kirimWa()" :disabled="sending"
                    class="inline-flex items-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white font-bold px-5 py-2.5 text-sm transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span x-text="sending ? 'Menyimpan…' : 'Kirim WhatsApp'"></span>
            </button>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-6 py-2.5 text-sm transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Simpan Absensi
            </button>
        </div>
    </div>
</form>

<!-- ===================== MODAL: pilih guru belum hadir ===================== -->
<div x-show="pickOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="pickOpen = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800">Tandai Belum Hadir — KBM <span x-text="shift"></span></h3>
            <input type="text" x-model="pickQ" placeholder="Cari nama guru…"
                   class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
            <template x-for="g in pilihan()" :key="g.id">
                <label class="flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" :value="g.id" x-model.number="pickSel" class="h-4 w-4 rounded border-slate-300 text-brand-600">
                    <span class="flex-1 text-sm text-slate-700" x-text="g.nama"></span>
                    <span x-show="g.kbm" class="rounded-full bg-brand-50 text-brand-700 px-2 py-0.5 text-[10px] font-bold">KBM</span>
                </label>
            </template>
            <p x-show="pilihan().length === 0" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada guru cocok.</p>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-2">
            <button type="button" @click="pickOpen = false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
            <button type="button" @click="terapkanPilihan()" class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white px-5 py-2 text-sm font-bold"
                    x-text="'Tandai (' + pickSel.length + ')'"></button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: format pesan WhatsApp ===================== -->
<div x-show="tplOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="tplOpen = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800">Format Pesan WhatsApp</h3>
                <p class="text-xs text-slate-500" x-text="custom ? 'Memakai format buatan sendiri.' : 'Memakai format bawaan.'"></p>
            </div>
            <button type="button" @click="tplOpen = false" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto p-5 grid md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <textarea x-model="tpl" rows="18" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono leading-relaxed outline-none focus:border-brand-500"></textarea>
                <p class="text-xs text-slate-400 mt-1">Baris daftar (mis. <code>{daftar_belum_hadir}</code>) yang kosong otomatis dihapus bersama satu baris judul tepat di atasnya.</p>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 mb-2">Isian otomatis (klik untuk menyalin)</p>
                <div class="space-y-1.5">
                    <?php foreach (\App\Libraries\AbsensiWa::PLACEHOLDERS as $kode => $arti): ?>
                        <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText('<?= esc($kode, 'js') ?>')"
                                class="block w-full text-left rounded-lg border border-slate-200 px-2.5 py-1.5 hover:bg-slate-50">
                            <code class="text-xs font-bold text-brand-700"><?= esc($kode) ?></code>
                            <span class="block text-[11px] text-slate-500"><?= esc($arti) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <button type="button" @click="resetTemplate()" class="text-sm font-semibold text-red-600 hover:text-red-700">Kembalikan ke bawaan</button>
            <div class="flex gap-2">
                <button type="button" @click="pratinjau()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Pratinjau (data tersimpan)</button>
                <button type="button" @click="simpanTemplate()" class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white px-5 py-2 text-sm font-bold">Simpan Format</button>
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODAL: teks pesan (pratinjau / cadangan bila tab diblokir) ===================== -->
<div x-show="waText !== ''" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="waText = ''"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-800">Pesan WhatsApp</div>
        <div class="p-5">
            <textarea readonly rows="14" x-model="waText" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm leading-relaxed outline-none"></textarea>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-2">
            <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(waText); notify('Pesan disalin.')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Salin</button>
            <button type="button" @click="waText = ''" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Tutup</button>
            <a :href="'https://wa.me/?text=' + encodeURIComponent(waText)" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 text-sm">Buka WhatsApp</a>
        </div>
    </div>
</div>

<!-- Toast -->
<div x-show="toast.show" x-cloak x-transition class="fixed top-5 left-1/2 -translate-x-1/2 z-[95] rounded-xl px-4 py-2.5 text-sm font-semibold shadow-lg"
     :class="toast.err ? 'bg-red-600 text-white' : 'bg-slate-800 text-white'" x-text="toast.msg"></div>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Halaman absensi: shift, belum hadir, kirim WhatsApp & format pesan.
    function absensiPage(cfg) {
        return {
            tanggal: cfg.tanggal,
            shift: cfg.shift,
            belum: { pagi: (cfg.belum.pagi || []).map(Number), siang: (cfg.belum.siang || []).map(Number) },
            guruShift: cfg.guruShift,
            piket: { pagi: (cfg.piket.pagi || []).map(Number), siang: (cfg.piket.siang || []).map(Number) },
            guru: cfg.guru || [],
            tpl: cfg.template,
            custom: !!cfg.custom,
            tick: 0,
            sending: false,
            tplOpen: false,
            pickOpen: false, pickQ: '', pickSel: [],
            waText: '',
            toast: { show: false, msg: '', err: false },

            setShift(s) {
                this.shift = s;
                try {
                    const u = new URL(window.location.href);
                    u.searchParams.set('shift', s);
                    history.replaceState(null, '', u);
                } catch (e) {}
                this.tick++;
            },
            namaGuru(id) {
                const g = this.guru.find(function (x) { return x.id === Number(id); });
                return g ? g.nama : ('Guru #' + id);
            },
            punyaSesi(gid) { return this.guruShift[this.shift].indexOf(gid) !== -1; },
            jumlahSesi(gid) {
                return document.querySelectorAll('.absen-row[data-guru-id="' + gid + '"][data-shift="' + this.shift + '"]').length;
            },
            isBelum(gid) { return this.belum[this.shift].indexOf(Number(gid)) !== -1; },
            isPiket(gid) { return this.piket.pagi.indexOf(Number(gid)) !== -1 || this.piket.siang.indexOf(Number(gid)) !== -1; },
            labelPiket(gid) {
                const p = this.piket.pagi.indexOf(Number(gid)) !== -1, s = this.piket.siang.indexOf(Number(gid)) !== -1;
                return 'Piket ' + (p && s ? 'pagi+siang' : (p ? 'pagi' : 'siang'));
            },
            tandaiBelum(gid) {
                if (!this.isBelum(gid)) this.belum[this.shift].push(Number(gid));
                this.tick++;
            },
            hapusBelum(gid) {
                this.belum[this.shift] = this.belum[this.shift].filter(function (x) { return x !== Number(gid); });
                this.tick++;
            },
            jamSekarang() {
                const d = new Date();
                return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            },
            hariIni() {
                const d = new Date();
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            },
            // Guru tiba: keluarkan dari daftar belum hadir; sesi shift ini yang
            // SUDAH dimulai saat ia datang ditandai Telat (jam masuk = jam datang).
            // Jam selalu dikonfirmasi (terisi jam sekarang) karena menentukan potongan.
            sudahDatang(gid) {
                let jam = prompt('Jam datang ' + this.namaGuru(gid) + ' (JJ:MM):',
                    this.tanggal === this.hariIni() ? this.jamSekarang() : '');
                if (jam === null) return;
                if (!/^\d{1,2}:\d{2}$/.test(jam.trim())) { this.notify('Format jam tidak valid (contoh 07:45).', true); return; }
                jam = jam.trim().padStart(5, '0');
                this.hapusBelum(gid);
                window.dispatchEvent(new CustomEvent('absen-datang', { detail: { gid: Number(gid), shift: this.shift, jam: jam } }));
                this.$nextTick(() => { this.tick++; });
                this.notify('Ditandai datang pukul ' + jam + '. Jangan lupa Simpan / Kirim WhatsApp.');
            },
            openPicker() { this.pickQ = ''; this.pickSel = []; this.pickOpen = true; },
            // Guru terjadwal KBM shift ini lebih dulu, lalu guru lain.
            pilihan() {
                const q = this.pickQ.trim().toLowerCase();
                const kbm = this.guruShift[this.shift];
                const self = this;
                return this.guru
                    .filter(function (g) { return !self.isBelum(g.id) && (!q || g.nama.toLowerCase().indexOf(q) !== -1); })
                    .map(function (g) { return { id: g.id, nama: g.nama, kbm: kbm.indexOf(g.id) !== -1 }; })
                    .sort(function (a, b) { return (b.kbm - a.kbm) || a.nama.localeCompare(b.nama); });
            },
            terapkanPilihan() {
                const self = this;
                this.pickSel.forEach(function (id) { self.tandaiBelum(id); });
                this.pickOpen = false;
            },
            // Ringkasan per guru (status terburuk sesi shift ini) + belum hadir.
            stats() {
                this.tick;
                const rank = { hadir: 0, telat: 1, izin: 2, sakit: 3, alpa: 4 };
                const per = {};
                document.querySelectorAll('.absen-row[data-shift="' + this.shift + '"]').forEach(function (r) {
                    const sel = r.querySelector('[data-role=status]');
                    const gid = r.dataset.guruId;
                    const st = sel ? sel.value : 'hadir';
                    if (!(gid in per) || rank[st] > rank[per[gid]]) per[gid] = st;
                });
                const c = { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0 };
                const self = this;
                Object.keys(per).forEach(function (gid) {
                    if (self.isBelum(gid) && (per[gid] === 'hadir' || per[gid] === 'telat')) return;
                    c[per[gid]]++;
                });
                return [
                    { k: 'hadir', l: 'Hadir', v: c.hadir, c: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                    { k: 'telat', l: 'Telat', v: c.telat, c: 'bg-amber-50 text-amber-700 border-amber-200' },
                    { k: 'belum', l: 'Belum hadir', v: this.belum[this.shift].length, c: 'bg-red-50 text-red-700 border-red-200' },
                    { k: 'izin', l: 'Izin', v: c.izin, c: 'bg-sky-50 text-sky-700 border-sky-200' },
                    { k: 'sakit', l: 'Sakit', v: c.sakit, c: 'bg-violet-50 text-violet-700 border-violet-200' },
                    { k: 'alpa', l: 'Alpa', v: c.alpa, c: 'bg-slate-50 text-slate-600 border-slate-200' },
                ];
            },
            notify(msg, err) {
                this.toast = { show: true, msg: msg, err: !!err };
                clearTimeout(this._t);
                this._t = setTimeout(() => { this.toast.show = false; }, 3500);
            },
            async postForm(url, data) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                return res.json();
            },
            // Isi rows_json & kerja_json dari tampilan sebelum form dikirim.
            isiJson() {
                const rows = [];
                document.querySelectorAll('.absen-row').forEach(function (r) {
                    const st = (r.querySelector('[data-role=status]') || {}).value || 'hadir';
                    const row = {
                        kelas_id: +r.dataset.kelas, jam_id: +r.dataset.jam, guru_id: +r.dataset.guruAsli,
                        hari_id: +r.dataset.hari, mapel_id: +r.dataset.mapel, jadwal_id: +r.dataset.jadwal, status: st,
                    };
                    if (st !== 'hadir') {
                        row.jam_masuk = (r.querySelector('[data-role=jm]') || {}).value || '';
                        row.keterangan = (r.querySelector('[data-role=ket]') || {}).value || '';
                    }
                    rows.push(row);
                });
                const panel = document.getElementById('panel-kerja');
                const kerja = panel ? Alpine.$data(panel).rows.map(function (k) {
                    return { guru_id: k.guru_id, status: k.status, shift: k.shift || 'penuh', jam_masuk: k.status !== 'hadir' ? k.jam_masuk : '', keterangan: k.keterangan };
                }) : [];
                document.getElementById('rows_json').value = JSON.stringify(rows);
                document.getElementById('kerja_json').value = JSON.stringify(kerja);
            },
            // Satu klik: simpan semua → server menyusun pesan → buka WhatsApp.
            // Tab dibuka lebih dulu (sinkron dengan klik) agar tidak diblokir browser.
            async kirimWa() {
                if (this.sending) return;
                this.sending = true;
                const tab = window.open('', '_blank');
                try {
                    this.isiJson();
                    const out = await this.postForm(cfg.urlSave, new FormData(document.getElementById('form-absensi')));
                    if (!out || !out.ok) throw new Error((out && out.message) || 'Gagal menyimpan.');
                    const url = 'https://wa.me/?text=' + encodeURIComponent(out.pesan_wa);
                    if (tab) {
                        tab.location.href = url;
                        this.notify('Tersimpan. WhatsApp dibuka — pilih grup lalu kirim.');
                        setTimeout(() => { window.location.reload(); }, 1500);
                    } else {
                        this.waText = out.pesan_wa; // tab diblokir → tampilkan tombol manual
                    }
                } catch (e) {
                    if (tab) tab.close();
                    this.notify(e.message || 'Gagal terhubung ke server.', true);
                } finally {
                    this.sending = false;
                }
            },
            async simpanTemplate() {
                const fd = new FormData();
                fd.append('template', this.tpl);
                try {
                    const out = await this.postForm(cfg.urlTpl, fd);
                    if (!out.ok) throw new Error(out.message);
                    this.tpl = out.template; this.custom = out.custom;
                    this.notify(out.message);
                } catch (e) { this.notify(e.message || 'Gagal menyimpan format.', true); }
            },
            async resetTemplate() {
                if (!confirm('Kembalikan format pesan ke bawaan?')) return;
                const fd = new FormData();
                fd.append('reset', '1');
                try {
                    const out = await this.postForm(cfg.urlTpl, fd);
                    this.tpl = out.template; this.custom = out.custom;
                    this.notify(out.message);
                } catch (e) { this.notify('Gagal mengembalikan format.', true); }
            },
            async pratinjau() {
                try {
                    const res = await fetch(cfg.urlPesan + '?tanggal=' + encodeURIComponent(this.tanggal) + '&shift=' + this.shift,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const out = await res.json();
                    this.waText = out.pesan_wa || '';
                } catch (e) { this.notify('Gagal memuat pratinjau.', true); }
            },
        };
    }

    // Panel Kehadiran Kerja (di luar jadwal): kelola daftar guru + status lokal.
    function absensiKerja(initial, guru) {
        return {
            rows: (initial || []).map(function (r) {
                return {
                    guru_id: Number(r.guru_id),
                    nama: r.nama,
                    jabatan: r.jabatan || '',
                    status: r.status || 'hadir',
                    shift: r.shift || 'penuh',
                    jam_masuk: r.jam_masuk || '',
                    keterangan: r.keterangan || '',
                    saran: !!r.saran
                };
            }),
            guruList: guru || [],
            pick: 0,
            // Guru yang belum ada di daftar (cegah duplikat).
            availableGuru: function () {
                var used = {};
                this.rows.forEach(function (r) { used[r.guru_id] = true; });
                return this.guruList.filter(function (g) { return !used[g.id]; });
            },
            add: function () {
                var id = Number(this.pick);
                if (!id) return;
                if (this.rows.some(function (r) { return r.guru_id === id; })) { this.pick = 0; return; }
                var g = this.guruList.find(function (x) { return x.id === id; });
                if (!g) return;
                this.rows.push({ guru_id: id, nama: g.nama, jabatan: g.jabatan || '', status: 'hadir', shift: 'penuh', jam_masuk: '', keterangan: '', saran: false });
                this.pick = 0;
            },
            remove: function (i) { this.rows.splice(i, 1); }
        };
    }
</script>
<?= $this->endSection() ?>
