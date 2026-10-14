<?php
/**
 * Tab Periode — ringkasan angka + form pengaturan periode ujian (rombak tampilan F7: kartu angka stat-*, formulir kit).
 *
 * @var array<string,mixed>  $periode
 * @var string               $label
 * @var string               $base        URL dasar jenis ujian ini
 * @var string               $tp          tahun pelajaran yang sedang dilihat
 * @var array<string,mixed>  $ringkas
 * @var array<string,string> $labelStatus
 */
$old = static fn (string $f, $fallback) => old($f) ?? ($fallback ?? '');
$kartu = [
    ['Jadwal ujian', $ringkas['jadwal'], 'Mapel terjadwal', 'calendar-week', 'text-brand-700 dark:text-brand-400'],
    ['Siswa aktif', $ringkas['siswaAktif'], 'Calon peserta', 'users', 'text-gray-900 dark:text-white'],
    ['Tidak hadir', $ringkas['tidakHadir'], 'Tercatat berhalangan', 'exclamation-circle', 'text-yellow-700 dark:text-yellow-400'],
    ['Susulan selesai', $ringkas['statusList']['selesai'], 'Sudah dilaksanakan', 'check-circle', 'text-green-700 dark:text-green-400'],
];
?>

<div class="space-y-4">
    <!-- ===== Kartu ringkasan ===== -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php foreach ($kartu as [$judul, $angka, $ket, $ik, $warna]): ?>
            <div class="kartu kartu-isi">
                <p class="stat-label flex items-center gap-2 text-sm"><?= ikon($ik, 'h-4 w-4 shrink-0') ?><?= esc($judul) ?></p>
                <p class="stat-angka mt-2 tabular-nums <?= $warna ?>"><?= number_format((int) $angka, 0, ',', '.') ?></p>
                <p class="stat-ket"><?= esc($ket) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== Form pengaturan periode ===== -->
    <section class="kartu" aria-labelledby="judul-periode">
        <div class="kartu-kepala">
            <div>
                <h2 id="judul-periode" class="kartu-judul">Pengaturan periode</h2>
                <p class="kartu-ket">Tanggal boleh dikosongkan dulu dan diisi belakangan.</p>
            </div>
        </div>

        <form method="post" action="<?= $base ?>/periode">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $periode['id'] ?>">

            <div class="kartu-isi space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="lbl" for="pr-mulai">Tanggal mulai ujian</label>
                        <input type="date" id="pr-mulai" name="tanggal_mulai" value="<?= esc($old('tanggal_mulai', $periode['tanggal_mulai']), 'attr') ?>" class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="pr-selesai">Tanggal selesai ujian</label>
                        <input type="date" id="pr-selesai" name="tanggal_selesai" value="<?= esc($old('tanggal_selesai', $periode['tanggal_selesai']), 'attr') ?>" class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="pr-sus-mulai">Mulai ujian susulan</label>
                        <input type="date" id="pr-sus-mulai" name="susulan_mulai" value="<?= esc($old('susulan_mulai', $periode['susulan_mulai']), 'attr') ?>" class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="pr-sus-selesai">Selesai ujian susulan</label>
                        <input type="date" id="pr-sus-selesai" name="susulan_selesai" value="<?= esc($old('susulan_selesai', $periode['susulan_selesai']), 'attr') ?>" class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="pr-status">Status</label>
                        <select id="pr-status" name="status" class="inp">
                            <?php foreach ($labelStatus as $k => $lbl): ?>
                                <option value="<?= esc($k, 'attr') ?>"<?= $old('status', $periode['status']) === $k ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="lbl" for="pr-nama">Nama tampil <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                        <input type="text" id="pr-nama" name="nama" maxlength="100" value="<?= esc($old('nama', $periode['nama']), 'attr') ?>" placeholder="<?= esc($label, 'attr') ?>" class="inp">
                    </div>
                </div>

                <div>
                    <label class="lbl" for="pr-ket">Keterangan</label>
                    <input type="text" id="pr-ket" name="keterangan" maxlength="255" value="<?= esc($old('keterangan', $periode['keterangan']), 'attr') ?>" placeholder="Catatan internal, mis. nomor SK panitia" class="inp">
                </div>
            </div>

            <div class="kartu-kaki flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pengaturan</button>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Tahun pelajaran <b><?= esc($tp) ?></b> &amp; semester <b><?= esc($periode['semester']) ?></b>
                    mengikuti jenis ujian dan Pengaturan Sekolah — tidak diubah dari sini.
                </p>
            </div>
        </form>
    </section>
</div>
