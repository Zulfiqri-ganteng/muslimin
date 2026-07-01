<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'absensi_v2',
    'helpTitle' => 'Cara mengisi absensi guru',
    'helpBody'  => '<p>Pilih <b>tanggal</b>, sistem menampilkan semua sesi mengajar hari itu dari jadwal KBM. Semua <b>default Hadir</b> — Anda cukup menandai guru yang <b>Telat / Izin / Sakit / Alpa (Tidak Hadir)</b>. Saat status bukan Hadir, muncul kolom <b>Jam Masuk</b> (isi bebas sesuai aturan Anda) dan <b>Keterangan</b>. Gunakan <b>Set semua</b> di tiap guru untuk menandai seluruh sesinya sekaligus.</p>'
        . '<p class="mt-2"><b>Penting:</b> klik <b>Simpan Absensi</b> agar hari ini <b>tercatat</b> dan ikut dihitung di Rekap — hari yang tidak Anda simpan (libur/belum dikelola) tidak dihitung. Jika salah menyimpan hari libur, gunakan <b>Batalkan pencatatan</b> untuk mereset hari itu.</p>'
        . '<p class="mt-2">Tombol <b>Bagikan ke WhatsApp</b> menyiapkan daftar guru yang tidak hadir/telat hari itu (teks bisa Anda revisi dulu). Data absensi juga tampil di halaman publik bila diaktifkan di Pengaturan.</p>',
]) ?>

<?php
    $statusOpts = [
        'hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin',
        'sakit' => 'Sakit', 'alpa'  => 'Alpa (Tidak Hadir)',
    ];
    // Kelas warna untuk chip ringkasan (statis, agar ke-scan Tailwind).
    $chip = [
        'hadir' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'telat' => 'bg-amber-50 text-amber-700 border-amber-200',
        'izin'  => 'bg-sky-50 text-sky-700 border-sky-200',
        'sakit' => 'bg-violet-50 text-violet-700 border-violet-200',
        'alpa'  => 'bg-red-50 text-red-700 border-red-200',
    ];
?>

<!-- ===================== BAR ATAS: pilih tanggal + ringkasan ===================== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= esc($tanggal) ?>" onchange="this.form.submit()"
                       class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div class="pb-1">
                <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= $namaHari !== '' ? esc($namaHari) : 'Hari tak dikenal' ?>
                </span>
            </div>
            <noscript><button class="rounded-xl bg-brand-700 text-white font-bold px-5 py-2.5 text-sm">Lihat</button></noscript>
        </form>

        <!-- Ringkasan status -->
        <div class="flex flex-wrap gap-2">
            <?php foreach ($statusOpts as $k => $lbl): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold <?= $chip[$k] ?>">
                    <?= esc($lbl) ?>: <?= (int) ($ringkas[$k] ?? 0) ?>
                </span>
            <?php endforeach; ?>
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-slate-600">
                Total sesi: <?= (int) $total ?>
            </span>
        </div>
    </div>

    <?php if ($hariAktif && $total > 0): ?>
        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <?php if ($recorded): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Hari ini sudah diabsen — dihitung di rekap
                </span>
                <form method="post" action="<?= site_url('admin/absensi/unrecord') ?>" onsubmit="return confirm('Batalkan pencatatan absensi tanggal ini?\n\nSemua tandaan hari ini akan DIHAPUS dan hari ini tidak akan dihitung di rekap.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Batalkan pencatatan
                    </button>
                </form>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 text-xs font-bold text-amber-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Belum diabsen — klik <b>Simpan Absensi</b> untuk mencatat hari ini ke rekap
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (! $hariAktif): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Tanggal ini bukan hari sekolah aktif. Pilih tanggal lain.
    </div>
<?php elseif ($total === 0): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Belum ada jadwal KBM pada hari <?= esc($namaHari) ?>. Susun jadwal dulu di menu <b>Jadwal KBM</b>.
    </div>
<?php else: ?>

<!-- ===================== BAGIKAN KE WHATSAPP ===================== -->
<div x-data="{ waOpen:false, waText:'', waNomor:'' }" class="mb-4">
    <button type="button" @click="waText = buildAbsensiPesan(); waOpen = true"
            class="inline-flex items-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 text-sm transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Bagikan ke WhatsApp
    </button>

    <!-- Modal -->
    <div x-show="waOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="waOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden" x-transition>
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Bagikan Absensi ke WhatsApp</h3>
                <button type="button" @click="waOpen=false" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-5 space-y-3">
                <p class="text-xs text-slate-500">Teks di bawah dibuat dari status yang sedang tampil (termasuk perubahan yang belum disimpan). <b>Silakan revisi</b> dulu bila perlu. Kosongkan nomor untuk memilih kontak/grup langsung di WhatsApp.</p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Nomor tujuan (opsional)</label>
                    <input type="text" x-model="waNomor" placeholder="cth: 6281234567890" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Pesan</label>
                    <textarea x-model="waText" rows="12" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 leading-relaxed"></textarea>
                </div>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(waText)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Salin</button>
                <button type="button" @click="waOpen=false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Tutup</button>
                <a :href="'https://wa.me/' + waNomor.replace(/[^0-9]/g,'') + '?text=' + encodeURIComponent(waText)" target="_blank" @click="waOpen=false"
                   class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Kirim
                </a>
            </div>
        </div>
    </div>
</div>

<form method="post" action="<?= site_url('admin/absensi/save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

    <div class="space-y-4">
        <?php $i = 0; foreach ($grup as $g): ?>
            <div x-data class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Header guru + set semua -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-3 border-b border-slate-100 bg-slate-50">
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-800 truncate"><?= esc($g['nama']) ?></h3>
                        <p class="text-xs text-slate-400"><?= esc($g['kode']) ?> &middot; <?= count($g['sesi']) ?> sesi</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <label class="text-xs font-semibold text-slate-500">Set semua:</label>
                        <select class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold focus:border-brand-500 outline-none"
                                @change="$dispatch('absen-setall', { gid: <?= (int) $g['guru_id'] ?>, val: $event.target.value }); $event.target.selectedIndex = 0">
                            <option value="">— pilih —</option>
                            <?php foreach ($statusOpts as $k => $lbl): ?>
                                <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Daftar sesi guru -->
                <div class="divide-y divide-slate-100">
                    <?php foreach ($g['sesi'] as $s):
                        $info = 'Jam ' . $s['jam_ke'] . ' (' . substr($s['waktu_mulai'], 0, 5) . '–' . substr($s['waktu_selesai'], 0, 5) . ') · ' . $s['nama_kelas'] . ' · ' . $s['nama_mapel'];
                    ?>
                        <div x-data="{ gid: <?= (int) $s['guru_id'] ?>, st: '<?= esc($s['status'], 'js') ?>', jm: '<?= esc($s['jam_masuk'], 'js') ?>', ket: '<?= esc($s['keterangan'], 'js') ?>' }"
                             x-on:absen-setall.window="if ($event.detail.gid === gid) { st = $event.detail.val; if (st === 'hadir') { jm = ''; ket = ''; } }"
                             data-guru="<?= esc($g['nama'], 'attr') ?>" data-info="<?= esc($info, 'attr') ?>"
                             class="absen-row p-4 border-l-4 transition"
                             :class="{
                                'border-emerald-400': st==='hadir', 'border-amber-400': st==='telat',
                                'border-sky-400': st==='izin', 'border-violet-400': st==='sakit', 'border-red-400': st==='alpa'
                             }">
                            <!-- hidden identitas sesi -->
                            <input type="hidden" name="rows[<?= $i ?>][kelas_id]"  value="<?= (int) $s['kelas_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][jam_id]"    value="<?= (int) $s['jam_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][guru_id]"   value="<?= (int) $s['guru_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][hari_id]"   value="<?= (int) $s['hari_id'] ?>">
                            <input type="hidden" name="rows[<?= $i ?>][mapel_id]"  value="<?= (int) ($s['mapel_id'] ?? 0) ?>">
                            <input type="hidden" name="rows[<?= $i ?>][jadwal_id]" value="<?= (int) $s['jadwal_id'] ?>">

                            <div class="flex flex-col md:flex-row md:items-center gap-3">
                                <!-- Info sesi -->
                                <div class="md:w-64 shrink-0">
                                    <p class="text-sm font-bold text-slate-700">Jam <?= esc($s['jam_ke']) ?>
                                        <span class="text-slate-400 font-normal text-xs">(<?= esc(substr($s['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($s['waktu_selesai'], 0, 5)) ?>)</span>
                                    </p>
                                    <p class="text-sm text-slate-600"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold"><?= esc($s['nama_mapel']) ?></span></p>
                                </div>

                                <!-- Status -->
                                <div class="shrink-0">
                                    <select name="rows[<?= $i ?>][status]" x-model="st" data-role="status"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                                        <?php foreach ($statusOpts as $k => $lbl): ?>
                                            <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Jam masuk + keterangan (muncul bila bukan hadir) -->
                                <div class="flex flex-col sm:flex-row gap-2 flex-1" x-show="st !== 'hadir'" x-cloak>
                                    <div>
                                        <input type="time" name="rows[<?= $i ?>][jam_masuk]" x-model="jm" data-role="jm"
                                               class="w-full sm:w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none"
                                               title="Jam masuk (opsional)">
                                    </div>
                                    <input type="text" name="rows[<?= $i ?>][keterangan]" x-model="ket" data-role="ket" maxlength="255"
                                           placeholder="Keterangan (opsional)…"
                                           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                                </div>
                            </div>
                        </div>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bar simpan (sticky bawah) -->
    <div class="sticky bottom-0 mt-5 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-white/90 backdrop-blur border-t border-slate-200 flex items-center justify-between gap-3">
        <p class="text-xs text-slate-400 hidden sm:block">Tanggal <b><?= esc($tanggal) ?></b> &middot; <?= esc($namaHari) ?> &middot; <?= (int) $total ?> sesi</p>
        <button type="submit" class="ml-auto inline-flex items-center gap-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-6 py-2.5 text-sm transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Simpan Absensi
        </button>
    </div>
</form>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php
    $bulanFull = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $tsIdx   = strtotime($tanggal);
    $tglIndo = (int) date('j', $tsIdx) . ' ' . $bulanFull[(int) date('n', $tsIdx)] . ' ' . date('Y', $tsIdx);
?>
<script>
    const ABSEN_HEADER = {
        hari:    <?= json_encode($namaHari) ?>,
        tanggal: <?= json_encode($tglIndo) ?>,
        sekolah: <?= json_encode($sekolah) ?>
    };

    // Bangun pesan WhatsApp dari status yang sedang tampil (baca DOM live).
    function buildAbsensiPesan() {
        const stLabel = { telat: 'Telat', izin: 'Izin', sakit: 'Sakit', alpa: 'Tidak Hadir' };
        const grup = {}, order = [];
        document.querySelectorAll('.absen-row').forEach(function (r) {
            const sel = r.querySelector('[data-role=status]');
            if (!sel) return;
            const st = sel.value;
            if (st === 'hadir') return;
            const guru = r.dataset.guru || '-';
            if (!grup[guru]) { grup[guru] = []; order.push(guru); }
            grup[guru].push({
                info: r.dataset.info || '',
                st:   st,
                jm:   (r.querySelector('[data-role=jm]') || {}).value || '',
                ket:  (r.querySelector('[data-role=ket]') || {}).value || ''
            });
        });

        let msg = '*ABSENSI GURU*\n' + ABSEN_HEADER.hari + ', ' + ABSEN_HEADER.tanggal + '\n' + ABSEN_HEADER.sekolah + '\n\n';
        if (order.length === 0) {
            return msg + 'Semua guru *HADIR*. ✅\n\nTerima kasih.';
        }
        msg += 'Guru tidak hadir / telat:\n\n';
        let n = 1;
        order.forEach(function (guru) {
            msg += n + '. *' + guru + '*\n';
            grup[guru].forEach(function (it) {
                let line = '   • ' + (stLabel[it.st] || it.st);
                if (it.st === 'telat' && it.jm) line += ' (masuk ' + it.jm + ')';
                if (it.info) line += ' — ' + it.info;
                msg += line + '\n';
                if (it.ket) msg += '     _' + it.ket + '_\n';
            });
            msg += '\n';
            n++;
        });
        return msg + 'Terima kasih.';
    }
</script>
<?= $this->endSection() ?>
