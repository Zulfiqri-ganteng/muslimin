<?php
/**
 * Kelola penugasan penguji (internal/eksternal) untuk satu jadwal UKK. Rombak tampilan F8: Kembali di kepala halaman, kartu kit
 * (info jadwal, daftar penguji tertugas, formulir Tugaskan), tabel kit, lencana tipe & peran, tombol Lepas merah + konfirmasi
 * kit, ui_kosong. Pilihan tipe (radio) memunculkan isian guru / penguji eksternal lewat x-show pada objek data { tipe } tanpa
 * metode → siap CSP.
 *
 * @var array              $jadwal        Baris jadwal_ukk::withRelations()
 * @var array              $list          Penguji tertugas (JadwalUkkPengujiModel::forJadwal)
 * @var array<int,string>  $guruOpts      Opsi guru (internal)
 * @var array<int,string>  $eksternalOpts Opsi penguji eksternal
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/jadwal-ukk/penguji/' . (int) $jadwal['id']);
$rentang = $tgl($jadwal['tanggal_mulai'])
    . (! empty($jadwal['tanggal_selesai']) && $jadwal['tanggal_selesai'] !== $jadwal['tanggal_mulai'] ? ' s/d ' . $tgl($jadwal['tanggal_selesai']) : '');
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/jadwal-ukk') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Jadwal UKK</a>
<a href="<?= site_url('admin/penilaian-ukk/jadwal/' . (int) $jadwal['id']) ?>" class="btn btn-sukses"><?= ikon('pen-nib', 'h-4 w-4') ?>Isi nilai</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_ukk_penguji',
    'helpTitle' => 'Penugasan Penguji',
    'helpBody'  => '<p>Tugaskan penguji <b>internal</b> (guru sekolah) dan/atau <b>eksternal</b> (DUDI/industri) untuk
        jadwal UKK ini. Tandai salah satu sebagai <b>Ketua</b> bila perlu. Penguji yang sudah ditugaskan akan muncul
        sebagai kolom nilai pada tahap Penilaian.</p>',
]) ?>

<div class="space-y-4">
    <!-- Info jadwal -->
    <section class="kartu kartu-isi" aria-labelledby="judul-jadwal-penguji">
        <h2 id="judul-jadwal-penguji" class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc($jadwal['paket_kode'] ?? '—') ?> — <?= esc($jadwal['paket_nama'] ?? '—') ?></h2>
        <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><?= ikon('calendar-month', 'h-4 w-4 shrink-0') ?><?= $rentang ?></span>
            <?php if (! empty($jadwal['sesi'])): ?><span class="inline-flex items-center gap-1.5"><?= ikon('clock', 'h-4 w-4 shrink-0') ?><?= esc($jadwal['sesi']) ?></span><?php endif; ?>
            <?php if (! empty($jadwal['tempat_nama'])): ?><span class="inline-flex items-center gap-1.5"><?= ikon('map-pin', 'h-4 w-4 shrink-0') ?><?= esc($jadwal['tempat_nama']) ?></span><?php endif; ?>
        </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Daftar penguji tertugas -->
        <section class="kartu overflow-hidden lg:col-span-2" aria-labelledby="judul-penguji-tertugas">
            <div class="kartu-kepala">
                <h2 id="judul-penguji-tertugas" class="kartu-judul">Penguji tertugas</h2>
                <span class="lencana lencana-abu"><?= count($list) ?> penguji</span>
            </div>
            <?php if (empty($list)): ?>
                <?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada penguji ditugaskan', 'teks' => 'Pilih penguji pada formulir Tugaskan penguji.']) ?>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Tipe</th>
                                <th scope="col">Peran</th>
                                <th scope="col" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $p):
                                $internal = $p['tipe'] === 'internal';
                                $nama     = $internal ? ($p['guru_nama'] ?? '—') : ($p['eksternal_nama'] ?? '—');
                                $sub      = (string) ($internal ? ($p['kode_guru'] ?? '') : ($p['eksternal_instansi'] ?? '')); ?>
                                <tr>
                                    <td>
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc($nama) ?></p>
                                        <?php if ($sub !== ''): ?><p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($sub) ?></p><?php endif; ?>
                                    </td>
                                    <td><span class="lencana <?= $internal ? 'lencana-merek' : 'lencana-kuning' ?>"><?= $internal ? 'Internal' : 'Eksternal' ?></span></td>
                                    <td><?= $p['peran'] === 'ketua' ? '<span class="lencana lencana-ungu">Ketua</span>' : '<span class="text-gray-500 dark:text-gray-400">Anggota</span>' ?></td>
                                    <td class="text-right">
                                        <a href="<?= $base ?>/hapus/<?= (int) $p['id'] ?>" class="btn btn-bahaya btn-sm" data-method="post"
                                           data-confirm="<?= esc('Lepas penugasan ' . $nama . ' dari jadwal ini?', 'attr') ?>" data-confirm-judul="Lepas penguji?" data-confirm-ya="Ya, lepas" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Lepas</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Formulir tugaskan penguji -->
        <section class="kartu self-start" aria-labelledby="judul-tugaskan" x-data="{ tipe: 'internal' }">
            <div class="kartu-kepala"><h2 id="judul-tugaskan" class="kartu-judul">Tugaskan penguji</h2></div>
            <form method="post" action="<?= $base ?>">
                <?= csrf_field() ?>
                <div class="kartu-isi space-y-4">
                    <fieldset>
                        <legend class="lbl">Tipe penguji</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                                <input type="radio" name="tipe" value="internal" x-model="tipe" class="cek" checked> Internal (guru)
                            </label>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                                <input type="radio" name="tipe" value="eksternal" x-model="tipe" class="cek"> Eksternal (DUDI)
                            </label>
                        </div>
                    </fieldset>
                    <div x-show="tipe === 'internal'">
                        <label class="lbl" for="pj-guru">Guru <span class="text-red-600">*</span></label>
                        <select id="pj-guru" name="guru_id" class="inp">
                            <option value="">— Pilih guru —</option>
                            <?php foreach ($guruOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($guruOpts)): ?>
                            <p class="hint">Belum ada data guru. Tambahkan di Master Guru.</p>
                        <?php endif; ?>
                    </div>
                    <div x-show="tipe === 'eksternal'" x-cloak>
                        <label class="lbl" for="pj-eksternal">Penguji eksternal <span class="text-red-600">*</span></label>
                        <select id="pj-eksternal" name="penguji_eksternal_id" class="inp">
                            <option value="">— Pilih penguji —</option>
                            <?php foreach ($eksternalOpts as $id => $lbl): ?>
                                <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($eksternalOpts)): ?>
                            <p class="hint">Belum ada data penguji eksternal. Tambahkan di Master Penguji Eksternal.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="lbl" for="pj-peran">Peran</label>
                        <select id="pj-peran" name="peran" class="inp">
                            <option value="anggota">Anggota</option>
                            <option value="ketua">Ketua</option>
                        </select>
                    </div>
                </div>
                <div class="kartu-kaki flex justify-end">
                    <button type="submit" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Tugaskan</button>
                </div>
            </form>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
