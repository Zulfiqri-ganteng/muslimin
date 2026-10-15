<?php
/**
 * Matriks penilaian: peserta (baris) x penguji tertugas (kolom), 1 jadwal. Rombak tampilan F8: Kembali di kepala halaman,
 * kartu kit, tabel kit, status bertitik, ui_peringatan/ui_kosong; dialog kit "Isi nilai" diisi dari tombol selnya lewat
 * data-dialog-isi (kit ui.js; sel kosong mengosongkan semua isian) — dulu x-data objek bermetode + @click berisi JSON → siap CSP.
 *
 * @var array      $jadwal      jadwal_ukk::withRelations() satu baris
 * @var array|null $paket       paket_soal_ukk (untuk bobot & KKM)
 * @var array      $peserta     peserta_ukk::withRelations() milik jadwal ini
 * @var array      $pengujiList JadwalUkkPengujiModel::forJadwal()
 * @var array      $nilaiMap    "{pesertaId}-{tipe}-{pengujiId}" => baris nilai_ukk
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$num  = static fn ($v) => $v !== null ? number_format((float) $v, 1) : '';
$saveUrl = site_url('admin/penilaian-ukk/jadwal/' . (int) $jadwal['id'] . '/simpan');
$warnaStatus = ['terdaftar' => '', 'hadir' => 'titik-status-biru', 'tidak_hadir' => 'titik-status-kuning', 'lulus' => 'titik-status-hijau', 'tidak_lulus' => 'titik-status-merah'];
$hariIni = date('Y-m-d');
$komponen = [
    'persiapan_skor' => 'Persiapan kerja',
    'proses_skor'    => 'Proses',
    'hasil_skor'     => 'Hasil kerja',
    'sikap_skor'     => 'Sikap kerja',
    'waktu_skor'     => 'Waktu',
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/penilaian-ukk') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Penilaian UKK</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'penilaian_ukk_jadwal',
    'helpTitle' => 'Isi Nilai',
    'helpBody'  => '<p>Klik sel pada kolom penguji untuk mengisi 5 komponen nilai (Persiapan, Proses, Hasil Kerja,
        Sikap Kerja, Waktu — masing-masing 0-100). Nilai akhir per penguji dihitung otomatis sesuai bobot paket
        soal. Kolom <b>Nilai Akhir</b> peserta = rata-rata seluruh penguji yang sudah mengisi.</p>',
]) ?>

<div class="space-y-4">
    <!-- Info jadwal -->
    <section class="kartu kartu-isi" aria-labelledby="judul-jadwal-nilai">
        <h2 id="judul-jadwal-nilai" class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc($jadwal['paket_kode'] ?? '—') ?> — <?= esc($jadwal['paket_nama'] ?? '—') ?></h2>
        <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><?= ikon('calendar-month', 'h-4 w-4 shrink-0') ?><?= $tgl($jadwal['tanggal_mulai']) ?></span>
            <?php if (! empty($jadwal['tempat_nama'])): ?><span class="inline-flex items-center gap-1.5"><?= ikon('map-pin', 'h-4 w-4 shrink-0') ?><?= esc($jadwal['tempat_nama']) ?></span><?php endif; ?>
            <?php if ($paket): ?><span class="lencana lencana-merek">KKM <?= esc((string) $paket['kkm']) ?></span><?php endif; ?>
        </p>
    </section>

    <?php if (empty($pengujiList)): ?>
        <?= ui_peringatan('awas', '', ['html' => 'Belum ada penguji ditugaskan pada jadwal ini. <a href="' . site_url('admin/jadwal-ukk/penguji/' . (int) $jadwal['id']) . '" class="font-semibold underline">Tugaskan penguji dulu</a>.']) ?>
    <?php elseif (empty($peserta)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'user-graduate', 'judul' => 'Belum ada peserta terdaftar pada jadwal ini',
            'aksi' => '<a href="' . site_url('admin/peserta-ukk/daftarkan') . '" class="btn btn-utama">' . ikon('plus', 'h-4 w-4') . 'Daftarkan peserta</a>']) ?></div>
    <?php else: ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-matriks-nilai">
            <div class="kartu-kepala">
                <h2 id="judul-matriks-nilai" class="kartu-judul">Matriks nilai</h2>
                <span class="lencana lencana-abu"><?= count($peserta) ?> peserta</span>
            </div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Peserta</th>
                            <?php foreach ($pengujiList as $pg):
                                $pgLabel = $pg['tipe'] === 'internal' ? ($pg['guru_nama'] ?? '—') : ($pg['eksternal_nama'] ?? '—'); ?>
                                <th scope="col" class="w-28 text-center">
                                    <?= esc($pgLabel) ?>
                                    <span class="block text-[10px] font-normal text-gray-500 normal-case dark:text-gray-400"><?= $pg['tipe'] === 'internal' ? 'Internal' : 'Eksternal' ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="text-right">Nilai akhir</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($peserta as $p): ?>
                            <tr>
                                <td>
                                    <p class="font-semibold text-gray-900 dark:text-white"><?= esc($p['siswa_nama'] ?? '—') ?></p>
                                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400"><?= esc($p['no_peserta'] ?? '—') ?></p>
                                </td>
                                <?php foreach ($pengujiList as $pg):
                                    $pengujiId = (int) ($pg['tipe'] === 'internal' ? $pg['guru_id'] : $pg['penguji_eksternal_id']);
                                    $pgLabel   = $pg['tipe'] === 'internal' ? ($pg['guru_nama'] ?? '—') : ($pg['eksternal_nama'] ?? '—');
                                    $n         = $nilaiMap[$p['id'] . '-' . $pg['tipe'] . '-' . $pengujiId] ?? null;
                                    // Data dialog: semua kunci selalu dikirim supaya sel kosong mengosongkan isian sel sebelumnya.
                                    $isi = [
                                        'label'                => ($p['siswa_nama'] ?? '') . ' — ' . $pgLabel,
                                        'peserta_ukk_id'       => (int) $p['id'],
                                        'tipe_penguji'         => $pg['tipe'],
                                        'guru_id'              => $pg['tipe'] === 'internal' ? $pengujiId : '',
                                        'penguji_eksternal_id' => $pg['tipe'] === 'eksternal' ? $pengujiId : '',
                                        'persiapan_skor'       => $n['persiapan_skor'] ?? '',
                                        'proses_skor'          => $n['proses_skor'] ?? '',
                                        'hasil_skor'           => $n['hasil_skor'] ?? '',
                                        'sikap_skor'           => $n['sikap_skor'] ?? '',
                                        'waktu_skor'           => $n['waktu_skor'] ?? '',
                                        'tanggal_nilai'        => $n['tanggal_nilai'] ?? $hariIni,
                                        'keterangan'           => $n['keterangan'] ?? '',
                                    ]; ?>
                                    <td class="text-center">
                                        <button type="button" data-buka-dialog="isi-nilai" data-dialog-isi="<?= esc((string) json_encode($isi), 'attr') ?>"
                                                class="<?= $n ? 'btn btn-netral btn-xs font-semibold text-brand-700 tabular-nums dark:text-brand-400' : 'btn btn-teks btn-xs' ?>"
                                                aria-label="<?= $n ? 'Ubah' : 'Isi' ?> nilai <?= esc($isi['label'], 'attr') ?>">
                                            <?= $n ? $num($n['nilai_akhir']) : ikon('plus', 'h-3.5 w-3.5') . 'Isi' ?>
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= $p['nilai_akhir'] !== null ? $num($p['nilai_akhir']) : '—' ?></td>
                                <td><span class="titik-status <?= $warnaStatus[$p['status']] ?? '' ?> font-medium"><?= esc(ucfirst(str_replace('_', ' ', $p['status']))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Dialog isi nilai (diisi dari tombol sel) -->
        <?= ui_dialog_buka(['nama' => 'isi-nilai', 'judul' => 'Isi nilai', 'ukuran' => 'lg']) ?>
        <form method="post" action="<?= $saveUrl ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="peserta_ukk_id" data-isi-pemicu="peserta_ukk_id">
            <input type="hidden" name="tipe_penguji" data-isi-pemicu="tipe_penguji">
            <input type="hidden" name="guru_id" data-isi-pemicu="guru_id">
            <input type="hidden" name="penguji_eksternal_id" data-isi-pemicu="penguji_eksternal_id">
            <div class="dialog-isi">
                <p class="text-sm font-medium text-gray-900 dark:text-white" data-isi-pemicu="label"></p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ($komponen as $nama => $lbl): ?>
                        <div>
                            <label class="lbl" for="nl-<?= $nama ?>"><?= esc($lbl) ?></label>
                            <input type="number" id="nl-<?= $nama ?>" name="<?= $nama ?>" data-isi-pemicu="<?= $nama ?>" min="0" max="100" step="0.01" class="inp tabular-nums">
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <label class="lbl" for="nl-tanggal">Tanggal nilai</label>
                        <input type="date" id="nl-tanggal" name="tanggal_nilai" data-isi-pemicu="tanggal_nilai" value="<?= $hariIni ?>" class="inp">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl" for="nl-ket">Keterangan</label>
                        <input type="text" id="nl-ket" name="keterangan" data-isi-pemicu="keterangan" maxlength="255" class="inp">
                    </div>
                </div>
                <?php if ($paket): ?>
                    <p class="hint">Bobot: Persiapan <?= esc((string) $paket['bobot_persiapan']) ?>% ·
                        Proses <?= esc((string) $paket['bobot_proses']) ?>% · Hasil <?= esc((string) $paket['bobot_hasil']) ?>% ·
                        Sikap <?= esc((string) $paket['bobot_sikap']) ?>% · Waktu <?= esc((string) $paket['bobot_waktu']) ?>%</p>
                <?php endif; ?>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan nilai</button>
            </div>
        </form>
        <?= ui_dialog_tutup() ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
