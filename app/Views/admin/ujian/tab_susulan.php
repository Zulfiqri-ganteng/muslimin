<?php
/**
 * Tab Ujian Susulan — tindak lanjut atas catatan ketidakhadiran.
 *
 * Alur status: belum → dijadwalkan → selesai (atau batal). Penjadwalan bisa
 * satuan maupun massal lewat centang baris.
 *
 * @var array<int,array>     $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array<string,mixed>  $filter
 * @var int                  $per
 * @var int                  $totalSusulan
 * @var array<string,int>    $ringkasStatus
 * @var array<string,int>    $ringkasAlasan
 * @var array<int,string>    $kelasOpts
 * @var array<int,string>    $mapelOpts
 * @var array<int,string>    $guruOpts
 * @var array<int,string>    $statusList
 * @var array<int,string>    $alasanList
 * @var array<string,string> $statusLabel
 * @var array                $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$jam = static fn ($j) => $j ? substr((string) $j, 0, 5) : '';

$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
$warnaStatus = [
    'belum'       => 'bg-amber-50 text-amber-700 border-amber-200',
    'dijadwalkan' => 'bg-sky-50 text-sky-700 border-sky-200',
    'selesai'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'batal'       => 'bg-slate-100 text-slate-500 border-slate-200',
];
$warnaAlasan = [
    'sakit'   => 'bg-rose-50 text-rose-700 border-rose-200',
    'izin'    => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'alpa'    => 'bg-slate-100 text-slate-600 border-slate-200',
    'lainnya' => 'bg-slate-100 text-slate-600 border-slate-200',
];

$adaFilter = $filter['q'] !== '' || $filter['kelas_id'] > 0 || $filter['mapel_id'] > 0
    || $filter['status'] !== '' || $filter['alasan'] !== '';

// Filter aktif dibawa pulang setelah aksi supaya halaman tidak melompat.
$sisaFilter = http_build_query(array_filter([
    'q'        => $filter['q'],
    'kelas_id' => $filter['kelas_id'] ?: null,
    'mapel_id' => $filter['mapel_id'] ?: null,
    'status'   => $filter['status'],
    'alasan'   => $filter['alasan'],
    'per'      => $per !== 20 ? $per : null,
]));
?>

<div x-data="{
        jadwalOpen:false, statusOpen:false,
        pilih: [],
        form: { tanggal_susulan:'', jam_susulan:'', ruang_susulan:'', pengawas_guru_id:'' },
        statusForm: { id:'', nama:'', status:'', tanggal_pelaksanaan:'<?= date('Y-m-d') ?>' },
        semua(ev){ this.pilih = ev.target.checked ? Array.from(this.$root.querySelectorAll('input[name=\'ids[]\']')).map(function(i){return i.value;}) : []; },
        bukaSatu(r){ this.pilih=[String(r.id)]; this.form={ tanggal_susulan:r.tanggal_susulan||'', jam_susulan:r.jam_susulan||'', ruang_susulan:r.ruang_susulan||'', pengawas_guru_id:r.pengawas_guru_id||'' }; this.jadwalOpen=true; },
        bukaMassal(){ if(this.pilih.length===0){ return; } this.form={ tanggal_susulan:'', jam_susulan:'', ruang_susulan:'', pengawas_guru_id:'' }; this.jadwalOpen=true; },
        bukaStatus(r){ this.statusForm={ id:String(r.id), nama:r.nama, status:r.status, tanggal_pelaksanaan:r.tanggal_pelaksanaan||'<?= date('Y-m-d') ?>' }; this.statusOpen=true; }
     }">

    <!-- Ringkasan status -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <?php
        $kartu = [
            ['belum', 'Belum Dijadwalkan', 'text-amber-700'],
            ['dijadwalkan', 'Sudah Dijadwalkan', 'text-sky-700'],
            ['selesai', 'Selesai', 'text-emerald-700'],
            ['batal', 'Batal', 'text-slate-500'],
        ];
        foreach ($kartu as [$k, $judul, $warna]): ?>
            <a href="<?= $base ?>/susulan<?= $qtp ?>&status=<?= $k ?>"
               class="bg-white rounded-2xl border shadow-sm p-4 transition hover:border-brand-300 <?= $filter['status'] === $k ? 'border-brand-400 ring-2 ring-brand-500/15' : 'border-slate-200' ?>">
                <p class="text-xs font-semibold text-slate-500"><?= esc($judul) ?></p>
                <p class="text-2xl font-extrabold <?= $warna ?> mt-1"><?= number_format((int) ($ringkasStatus[$k] ?? 0), 0, ',', '.') ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= esc($filter['q']) ?>" placeholder="Cari nama atau NIS siswa..."
                       class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <select name="kelas_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua kelas</option>
                <?php foreach ($kelasOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $filter['kelas_id'] === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="mapel_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua mapel</option>
                <?php foreach ($mapelOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $filter['mapel_id'] === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="alasan" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua alasan</option>
                <?php foreach ($alasanList as $a): ?>
                    <option value="<?= esc($a, 'attr') ?>" <?= $filter['alasan'] === $a ? 'selected' : '' ?>><?= esc($labelAlasan[$a] ?? $a) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <option value="">Semua status</option>
                <?php foreach ($statusList as $s): ?>
                    <option value="<?= esc($s, 'attr') ?>" <?= $filter['status'] === $s ? 'selected' : '' ?>><?= esc($statusLabel[$s] ?? $s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per" data-autosubmit title="Jumlah per halaman" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>>Tampilkan <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 shrink-0">Cari</button>
            <?php if ($adaFilter): ?>
                <a href="<?= $base ?>/susulan<?= $qtp ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 text-center shrink-0">Reset</a>
            <?php endif; ?>
        </form>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 mt-3 pt-3">
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5">
                Hasil: <?= (int) $totalSusulan ?>
            </span>
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5"
                  x-show="pilih.length > 0" x-cloak>
                Terpilih: <span class="ml-1" x-text="pilih.length"></span>
            </span>
            <div class="flex-1"></div>
            <button type="button" @click="bukaMassal()" :disabled="pilih.length === 0"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-sm font-semibold px-3.5 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Jadwalkan Terpilih
            </button>
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 w-10 text-center">
                            <input type="checkbox" @change="semua($event)"
                                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        </th>
                        <th class="text-left font-semibold px-4 py-3">Siswa</th>
                        <th class="text-left font-semibold px-4 py-3">Mapel &amp; Tanggal Ujian</th>
                        <th class="text-left font-semibold px-4 py-3">Alasan</th>
                        <th class="text-left font-semibold px-4 py-3">Jadwal Susulan</th>
                        <th class="text-left font-semibold px-4 py-3">Status</th>
                        <th class="text-right font-semibold px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (! $rows): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <?= $adaFilter
                                    ? 'Tidak ada data yang cocok dengan filter.'
                                    : 'Belum ada catatan ketidakhadiran. Data muncul otomatis dari tab <b>Ketidakhadiran</b>.' ?>
                            </td>
                        </tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                        $data = [
                            'id'                  => (string) $r['id'],
                            'nama'                => $r['siswa_nama'] ?? '',
                            'status'              => $r['status'],
                            'tanggal_susulan'     => $r['tanggal_susulan'] ?? '',
                            'jam_susulan'         => $r['jam_susulan'] ? substr((string) $r['jam_susulan'], 0, 5) : '',
                            'ruang_susulan'       => $r['ruang_susulan'] ?? '',
                            'pengawas_guru_id'    => (string) ($r['pengawas_guru_id'] ?? ''),
                            'tanggal_pelaksanaan' => $r['tanggal_pelaksanaan'] ?? '',
                        ];
                        ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" x-model="pilih"
                                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-700"><?= esc($r['siswa_nama'] ?? 'Siswa dihapus') ?></p>
                                <p class="text-xs text-slate-400">
                                    <?= esc($r['nama_kelas'] ?? '—') ?><?= $r['nis'] ? ' · NIS ' . esc($r['nis']) : '' ?>
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-700"><?= esc($r['nama_mapel'] ?? 'Tanpa mapel') ?></p>
                                <p class="text-xs text-slate-400"><?= $tgl($r['tanggal_ujian']) ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $warnaAlasan[$r['alasan']] ?? $warnaAlasan['alpa'] ?>">
                                    <?= esc($labelAlasan[$r['alasan']] ?? $r['alasan']) ?>
                                </span>
                                <?php if ($r['keterangan']): ?>
                                    <p class="text-xs text-slate-400 mt-1"><?= esc($r['keterangan']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($r['tanggal_susulan']): ?>
                                    <p class="text-slate-700"><?= $tgl($r['tanggal_susulan']) ?> <?= esc($jam($r['jam_susulan'])) ?></p>
                                    <p class="text-xs text-slate-400">
                                        <?= $r['ruang_susulan'] ? 'Ruang ' . esc($r['ruang_susulan']) : 'Ruang —' ?>
                                        <?= $r['pengawas_nama'] ? ' · ' . esc($r['pengawas_nama']) : '' ?>
                                    </p>
                                <?php else: ?>
                                    <span class="text-slate-300">Belum dijadwalkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? $warnaStatus['belum'] ?>">
                                    <?= esc($statusLabel[$r['status']] ?? $r['status']) ?>
                                </span>
                                <?php if ($r['status'] === 'selesai' && $r['tanggal_pelaksanaan']): ?>
                                    <p class="text-xs text-slate-400 mt-1">Dilaksanakan <?= $tgl($r['tanggal_pelaksanaan']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Jadwalkan"
                                            @click="bukaSatu(<?= htmlspecialchars(json_encode($data), ENT_QUOTES) ?>)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </button>
                                    <button type="button" title="Ubah status"
                                            @click="bukaStatus(<?= htmlspecialchars(json_encode($data), ENT_QUOTES) ?>)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <form method="post" action="<?= $base ?>/susulan/<?= (int) $r['id'] ?>/hapus" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                        <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">
                                        <button title="Hapus" data-confirm="Hapus catatan ketidakhadiran siswa ini?"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'kelas_id', 'mapel_id', 'status', 'alasan', 'per', 'tp'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Jadwalkan (satuan & massal memakai form yang sama) -->
    <div x-show="jadwalOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="jadwalOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Jadwalkan Ujian Susulan</h3>
            <p class="text-sm text-slate-500 mb-4">
                <span x-text="pilih.length"></span> siswa akan dijadwalkan.
                Baris yang sudah <b>Selesai</b> otomatis dilewati.
            </p>
            <form method="post" action="<?= $base ?>/susulan/jadwalkan">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">
                <template x-for="id in pilih" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Susulan *</label>
                        <input type="date" name="tanggal_susulan" x-model="form.tanggal_susulan" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam</label>
                        <input type="time" name="jam_susulan" x-model="form.jam_susulan"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ruang</label>
                        <input type="text" name="ruang_susulan" x-model="form.ruang_susulan" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Pengawas <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <select name="pengawas_guru_id" x-model="form.pengawas_guru_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak ditentukan —</option>
                            <?php foreach ($guruOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="jadwalOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Status -->
    <div x-show="statusOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="statusOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Ubah Status Susulan</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="statusForm.nama"></p>
            <form method="post" :action="'<?= $base ?>/susulan/' + statusForm.id + '/status'">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">

                <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                <select name="status" x-model="statusForm.status"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc($statusLabel[$s] ?? $s) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="mt-4" x-show="statusForm.status === 'selesai'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Pelaksanaan</label>
                    <input type="date" name="tanggal_pelaksanaan" x-model="statusForm.tanggal_pelaksanaan"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                </div>

                <p class="text-xs text-slate-500 mt-3">
                    Kembalikan ke <b>Belum</b> atau <b>Sudah dijadwalkan</b> lebih dulu bila susulan yang
                    sudah selesai perlu dijadwalkan ulang.
                </p>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="statusOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
