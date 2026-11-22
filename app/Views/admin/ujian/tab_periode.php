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

                <?php // F1 cetak jadwal (2026-11-22): dipakai cetakan Jadwal Ujian & Jadwal Pengawas. ?>
                <fieldset class="space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <legend class="sr-only">Untuk cetakan jadwal</legend>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Untuk cetakan Jadwal Ujian &amp; Jadwal Pengawas</h3>
                        <p class="hint mt-0.5">Semua boleh dikosongkan; yang kosong memakai nilai bawaan yang tertulis di bawah tiap isian.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="lbl" for="pr-ketua">Ketua panitia</label>
                            <input type="text" id="pr-ketua" name="ketua_panitia" maxlength="150" list="pr-ketua-opsi" value="<?= esc($old('ketua_panitia', $periode['ketua_panitia'] ?? ''), 'attr') ?>" placeholder="mis. Muslimin, S.Kom" class="inp">
                            <datalist id="pr-ketua-opsi">
                                <?php foreach ($ringkas['namaGuru'] ?? [] as $ng): ?><option value="<?= esc($ng, 'attr') ?>"></option><?php endforeach; ?>
                            </datalist>
                            <p class="hint">Nama di tanda tangan "Ketua Panitia" pada Jadwal Pengawas. Ketik atau pilih dari daftar guru.</p>
                        </div>
                        <div>
                            <label class="lbl" for="pr-sus-jam-mulai">Jam mulai ujian susulan</label>
                            <input type="time" id="pr-sus-jam-mulai" name="susulan_jam_mulai" value="<?= esc(substr((string) $old('susulan_jam_mulai', $periode['susulan_jam_mulai'] ?? ''), 0, 5), 'attr') ?>" class="inp">
                            <p class="hint">Kosong = 07.00.</p>
                        </div>
                        <div>
                            <label class="lbl" for="pr-sus-jam-selesai">Jam selesai ujian susulan</label>
                            <input type="time" id="pr-sus-jam-selesai" name="susulan_jam_selesai" value="<?= esc(substr((string) $old('susulan_jam_selesai', $periode['susulan_jam_selesai'] ?? ''), 0, 5), 'attr') ?>" class="inp">
                            <p class="hint">Kosong = 12.00. Dipakai di baris "UJIAN SUSULAN" Jadwal Ujian.</p>
                        </div>
                        <div>
                            <label class="lbl" for="pr-ruang-pagi">Jumlah ruang ujian pagi</label>
                            <input type="number" id="pr-ruang-pagi" name="ruang_pagi" min="1" max="200" value="<?= esc((string) $old('ruang_pagi', $periode['ruang_pagi'] ?? ''), 'attr') ?>" placeholder="<?= (int) ($ringkas['kelasShift']['pagi'] ?? 0) ?>" class="inp">
                            <p class="hint">Kosong = jumlah kelas pagi (<?= (int) ($ringkas['kelasShift']['pagi'] ?? 0) ?> kelas = <?= (int) ($ringkas['kelasShift']['pagi'] ?? 0) ?> ruang).</p>
                        </div>
                        <div>
                            <label class="lbl" for="pr-ruang-siang">Jumlah ruang ujian siang</label>
                            <input type="number" id="pr-ruang-siang" name="ruang_siang" min="1" max="200" value="<?= esc((string) $old('ruang_siang', $periode['ruang_siang'] ?? ''), 'attr') ?>" placeholder="<?= (int) ($ringkas['kelasShift']['siang'] ?? 0) ?>" class="inp">
                            <p class="hint">Kosong = jumlah kelas siang (<?= (int) ($ringkas['kelasShift']['siang'] ?? 0) ?> kelas = <?= (int) ($ringkas['kelasShift']['siang'] ?? 0) ?> ruang). Menentukan jumlah kolom RUANG di Jadwal Pengawas.</p>
                        </div>
                    </div>
                </fieldset>
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
