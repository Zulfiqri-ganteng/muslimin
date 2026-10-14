<?php
/**
 * Kelola pengawas satu sesi ujian (rombak tampilan F7: kartu identitas sesi, formulir & tabel kit, lencana peran,
 * tombol Lepas merah dengan dialog konfirmasi kit).
 *
 * Halaman terpisah (pola `admin/jadwal_ukk/penguji`) karena isinya daftar
 * penugasan, bukan satu form. Pengawas SEPENUHNYA OPSIONAL — sesi ujian tetap
 * sah walau daftar ini dibiarkan kosong.
 *
 * @var string            $slug
 * @var array             $periode
 * @var string            $label
 * @var array             $jadwal    baris jadwal + relasinya
 * @var array<int,array>  $list      pengawas yang sudah ditugaskan
 * @var array<int,string> $guruOpts
 * @var array<int,string> $peranList
 * @var string            $kembali   URL kembali ke tab jadwal
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$ts   = strtotime((string) $jadwal['tanggal']);
$jam  = $jadwal['jam_mulai']
    ? substr((string) $jadwal['jam_mulai'], 0, 5) . ($jadwal['jam_selesai'] ? '–' . substr((string) $jadwal['jam_selesai'], 0, 5) : '')
    : 'Sehari penuh';

$aksi       = site_url('admin/ujian/' . $slug . '/pengawas/' . $jadwal['id']);
$labelPeran = ['pengawas' => 'Pengawas', 'cadangan' => 'Cadangan'];
$warnaPeran = ['pengawas' => 'lencana-hijau', 'cadangan' => 'lencana-abu'];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Jadwal</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ujian_pengawas_v1',
    'helpTitle' => 'Pengawas Ujian',
    'helpBody'  => '<p>Pendataan guru yang mengawasi sesi ujian ini. Bagian ini <b>opsional</b> — jadwal tetap
        sah walau tidak diisi, jadi silakan lewati bila sekolah belum menetapkan pengawas.</p>
        <p class="mt-1">• Kosongkan kolom <b>Ruang</b> untuk mengikuti ruang sesi ujiannya.<br>
        • Seorang guru tidak bisa ditugaskan dua kali pada sesi yang sama, dan juga ditolak bila jamnya
        bertabrakan dengan sesi lain yang sudah ia awasi — termasuk sesi dari gelombang ujian berbeda.</p>',
]) ?>

<div class="space-y-4">
    <!-- Identitas sesi -->
    <section class="kartu kartu-isi" aria-labelledby="judul-sesi">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($label) ?></p>
        <h2 id="judul-sesi" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white"><?= esc($jadwal['nama_mapel'] ?? 'Mapel sudah dihapus') ?></h2>
        <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><?= ikon('calendar-month', 'h-4 w-4 shrink-0') ?><?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?></span>
            <span class="inline-flex items-center gap-1.5"><?= ikon('clock', 'h-4 w-4 shrink-0') ?><?= esc($jam) ?></span>
            <span>Tingkat <?= esc($jadwal['tingkat']) ?><?= $jadwal['jurusan_kode'] ? ' ' . esc($jadwal['jurusan_kode']) : '' ?></span>
            <span class="inline-flex items-center gap-1.5"><?= ikon('map-pin', 'h-4 w-4 shrink-0') ?>Ruang <?= esc($jadwal['ruang'] ?: '—') ?></span>
        </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Form tugaskan -->
        <section class="kartu h-max lg:col-span-1" aria-labelledby="judul-tugaskan">
            <div class="kartu-kepala">
                <h3 id="judul-tugaskan" class="kartu-judul">Tugaskan pengawas</h3>
            </div>
            <form method="post" action="<?= $aksi ?>" class="kartu-isi space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl" for="pw-guru">Guru <span class="text-red-600">*</span></label>
                    <select id="pw-guru" name="guru_id" required class="inp">
                        <option value="">— Pilih guru —</option>
                        <?php foreach ($guruOpts as $id => $lbl): ?>
                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="pw-peran">Peran</label>
                    <select id="pw-peran" name="peran" class="inp">
                        <?php foreach ($peranList as $p): ?>
                            <option value="<?= esc($p, 'attr') ?>"><?= esc($labelPeran[$p] ?? $p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="pw-ruang">Ruang <span class="font-normal text-gray-500 dark:text-gray-400">(ikut sesi bila kosong)</span></label>
                    <input type="text" id="pw-ruang" name="ruang" maxlength="100" placeholder="<?= esc($jadwal['ruang'] ?: 'mis. R1', 'attr') ?>" class="inp">
                </div>
                <div>
                    <label class="lbl" for="pw-ket">Keterangan</label>
                    <input type="text" id="pw-ket" name="keterangan" maxlength="255" class="inp">
                </div>
                <button type="submit" class="btn btn-utama w-full"><?= ikon('plus', 'h-4 w-4') ?>Tugaskan</button>
            </form>
        </section>

        <!-- Daftar pengawas -->
        <section class="kartu overflow-hidden lg:col-span-2" aria-labelledby="judul-bertugas">
            <div class="kartu-kepala">
                <h3 id="judul-bertugas" class="kartu-judul">Pengawas bertugas</h3>
                <span class="lencana lencana-abu"><?= count($list) ?> orang</span>
            </div>
            <?php if (! $list): ?>
                <?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada pengawas', 'teks' => 'Bagian ini boleh dibiarkan kosong.']) ?>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Guru</th>
                                <th scope="col">Peran</th>
                                <th scope="col">Ruang</th>
                                <th scope="col">Keterangan</th>
                                <th scope="col" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $r): ?>
                                <tr>
                                    <td>
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['guru_nama'] ?? 'Guru sudah dihapus') ?></p>
                                        <?php if ($r['kode_guru']): ?><p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['kode_guru']) ?></p><?php endif; ?>
                                    </td>
                                    <td><span class="lencana <?= $warnaPeran[$r['peran']] ?? $warnaPeran['cadangan'] ?>"><?= esc($labelPeran[$r['peran']] ?? $r['peran']) ?></span></td>
                                    <td><?= esc($r['ruang'] ?: '—') ?></td>
                                    <td class="text-gray-500 dark:text-gray-400"><?= esc($r['keterangan'] ?: '—') ?></td>
                                    <td class="text-right">
                                        <form method="post" action="<?= $aksi ?>/hapus/<?= (int) $r['id'] ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-bahaya btn-sm" data-confirm="Lepas penugasan pengawas ini?" data-confirm-jenis="bahaya" data-confirm-ya="Lepas"><?= ikon('trash-bin', 'h-3.5 w-3.5') ?>Lepas</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
