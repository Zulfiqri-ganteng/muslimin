<?php
/**
 * Tab Ketidakhadiran — inti modul.
 *
 * Alur tiga langkah: pilih sesi ujian → pilih kelas (otomatis disaring sesuai
 * tingkat/jurusan/shift sesi itu) → centang siswa yang TIDAK hadir.
 * Siswa yang hadir tidak pernah disimpan; yang dicentang langsung jadi baris
 * daftar ujian susulan.
 *
 * @var array                $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 * @var int                  $jadwalId
 * @var int                  $kelasId
 * @var array|null           $jadwalPilih
 * @var array|null           $kelasPilih
 * @var array<int,array>     $kelasSasaran
 * @var array<int,array>     $siswa
 * @var array<int,array>     $tercatat       siswa_id => baris susulan
 * @var array<int,string>    $jadwalOpts
 * @var array<int,string>    $alasanList
 * @var int                  $jmlTakHadirSesi
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
$labelStatusSusulan = [
    'belum'       => 'Belum dijadwalkan',
    'dijadwalkan' => 'Sudah dijadwalkan',
    'selesai'     => 'Selesai',
    'batal'       => 'Batal',
];
$warnaStatusSusulan = [
    'belum'       => 'bg-amber-50 text-amber-700 border-amber-200',
    'dijadwalkan' => 'bg-sky-50 text-sky-700 border-sky-200',
    'selesai'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'batal'       => 'bg-slate-100 text-slate-500 border-slate-200',
];
?>

<!-- Langkah 1 & 2: pilih sesi lalu kelas -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <form method="get" action="<?= $base ?>/ketidakhadiran" class="grid sm:grid-cols-2 gap-4">
        <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">1. Sesi Ujian</label>
            <select name="jadwal_id" data-autosubmit class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                <option value="">— Pilih sesi ujian —</option>
                <?php foreach ($jadwalOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>" <?= $jadwalId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">2. Kelas</label>
            <select name="kelas_id" data-autosubmit <?= $jadwalPilih ? '' : 'disabled' ?>
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none disabled:bg-slate-50 disabled:text-slate-400">
                <option value="">— Pilih kelas —</option>
                <?php foreach ($kelasSasaran as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelasId === (int) $k['id'] ? 'selected' : '' ?>>
                        <?= esc($k['nama_kelas']) ?> (<?= esc($k['shift']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($jadwalPilih && $kelasSasaran === []): ?>
                <p class="text-xs text-amber-700 mt-1.5">
                    Tidak ada kelas yang cocok dengan tingkat/jurusan/shift sesi ini.
                </p>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($jadwalPilih): ?>
        <?php $ts = strtotime((string) $jadwalPilih['tanggal']); ?>
        <div class="border-t border-slate-100 mt-4 pt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 font-semibold px-3 py-1.5">
                <?= esc($jadwalPilih['nama_mapel'] ?? 'Tanpa mapel') ?>
            </span>
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 font-semibold px-3 py-1.5">
                <?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?>
            </span>
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 font-semibold px-3 py-1.5">
                Tingkat <?= esc($jadwalPilih['tingkat']) ?><?= $jadwalPilih['jurusan_kode'] ? ' ' . esc($jadwalPilih['jurusan_kode']) : '' ?>
                · <?= esc($jadwalPilih['shift']) ?>
            </span>
            <span class="inline-flex items-center rounded-full <?= $jmlTakHadirSesi > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' ?> font-semibold px-3 py-1.5">
                Tidak hadir di sesi ini: <?= (int) $jmlTakHadirSesi ?>
            </span>
            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 font-semibold px-3 py-1.5">
                <?= count($kelasSasaran) ?> kelas sasaran
            </span>
        </div>
    <?php endif; ?>
</div>

<?php if (! $jadwalPilih): ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        <p class="font-bold text-slate-700 mt-3">Pilih sesi ujian dulu</p>
        <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
            Setelah sesi dipilih, daftar kelas sasarannya muncul otomatis sesuai tingkat, jurusan, dan shift sesi itu.
            <?php if ($jadwalOpts === []): ?>
                <br>Belum ada sesi ujian — susun dulu di <a href="<?= $base ?>/jadwal<?= $qtp ?>" class="text-brand-600 font-semibold hover:underline">tab Jadwal Ujian</a>.
            <?php endif; ?>
        </p>
    </div>

<?php elseif (! $kelasPilih): ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
        <p class="font-bold text-slate-700">Pilih kelas</p>
        <p class="text-sm text-slate-500 mt-1">Daftar siswa muncul setelah kelas dipilih.</p>
    </div>

<?php elseif ($siswa === []): ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
        <p class="font-bold text-slate-700">Kelas <?= esc($kelasPilih['nama_kelas']) ?> belum punya siswa aktif</p>
        <p class="text-sm text-slate-500 mt-1">Tambahkan siswanya lebih dulu di Master Data → Siswa.</p>
    </div>

<?php else: ?>

    <form method="post" action="<?= $base ?>/ketidakhadiran">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="jadwal_id" value="<?= (int) $jadwalId ?>">
        <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">

        <?php // x-data dipasang di kartu (bukan di tabel) supaya penghitung di
              // kepala kartu ikut masuk cakupan Alpine yang sama. ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden"
             x-data="{ jumlah: <?= count(array_intersect_key($tercatat, array_column($siswa, null, 'id'))) ?>,
                 hitung(){ this.jumlah = this.$root.querySelectorAll('input[name=\'tidak_hadir[]\']:checked').length; } }">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-bold text-slate-800">3. Centang siswa yang TIDAK hadir</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Kelas <?= esc($kelasPilih['nama_kelas']) ?> — <?= count($siswa) ?> siswa aktif.
                        Siswa yang hadir tidak perlu diapa-apakan.
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-3 py-1.5">
                    Tercentang: <span class="ml-1" x-text="jumlah"></span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-center font-semibold px-4 py-3 w-16">Tidak<br>Hadir</th>
                            <th class="text-left font-semibold px-4 py-3">Siswa</th>
                            <th class="text-left font-semibold px-4 py-3 w-40">Alasan</th>
                            <th class="text-left font-semibold px-4 py-3">Keterangan</th>
                            <th class="text-left font-semibold px-4 py-3 w-44">Status Susulan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($siswa as $s): ?>
                            <?php
                            $sid  = (int) $s['id'];
                            $lama = $tercatat[$sid] ?? null;
                            $on   = $lama !== null;
                            ?>
                            <tr x-data="{ on: <?= $on ? 'true' : 'false' ?> }" :class="on ? 'bg-amber-50/50' : ''">
                                <td class="px-4 py-2.5 text-center">
                                    <input type="checkbox" name="tidak_hadir[]" value="<?= $sid ?>"
                                           x-model="on" @change="hitung()"
                                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="font-semibold text-slate-700"><?= esc($s['nama']) ?></p>
                                    <p class="text-xs text-slate-400">NIS <?= esc($s['nis']) ?></p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <select name="alasan[<?= $sid ?>]" :disabled="!on"
                                            class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 outline-none disabled:bg-slate-50 disabled:text-slate-400">
                                        <?php foreach ($alasanList as $a): ?>
                                            <option value="<?= esc($a, 'attr') ?>" <?= ($lama['alasan'] ?? 'alpa') === $a ? 'selected' : '' ?>>
                                                <?= esc($labelAlasan[$a] ?? $a) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-4 py-2.5">
                                    <input type="text" name="keterangan[<?= $sid ?>]" maxlength="255" :disabled="!on"
                                           value="<?= esc($lama['keterangan'] ?? '', 'attr') ?>"
                                           placeholder="mis. surat dokter"
                                           class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 outline-none disabled:bg-slate-50">
                                </td>
                                <td class="px-4 py-2.5">
                                    <?php if ($lama): ?>
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $warnaStatusSusulan[$lama['status']] ?? $warnaStatusSusulan['belum'] ?>">
                                            <?= esc($labelStatusSusulan[$lama['status']] ?? $lama['status']) ?>
                                        </span>
                                        <?php if ($lama['status'] === 'selesai'): ?>
                                            <p class="text-[11px] text-slate-400 mt-1">Dikunci — tak bisa dibatalkan dari sini.</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-center gap-3">
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">
                    Simpan Pendataan
                </button>
                <p class="text-xs text-slate-500">
                    Centangan yang dilepas berarti siswa ternyata hadir — catatannya dihapus.
                    Susulan yang sudah <b>Selesai</b> tetap dipertahankan.
                </p>
            </div>
        </div>
    </form>

<?php endif; ?>
