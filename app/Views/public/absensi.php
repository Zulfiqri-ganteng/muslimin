<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Kehadiran guru per tanggal (pola 4c rombak publik P5): pembuka · kartu saringan tanggal (langsung tampil lewat
 * [data-kirim-otomatis]) + ringkasan jumlah per status · Kehadiran kerja (di luar jadwal mengajar) · absensi mengajar per guru.
 * Data & logika sama dengan versi lama; warna status = satu label per status (hadir hijau, telat kuning, izin lilac, sakit pink,
 * tidak hadir merah).
 *
 * @var string $tanggal @var string $hariNama @var array<string, int> $ringkas @var bool $recorded @var bool $hariAktif
 * @var list<array> $kerja @var int $total @var list<array{nama: string, sesi: list<array>}> $grup
 */
$statusOpts = ['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Tidak hadir'];
$labelKelas = ['hadir' => 'label-buka', 'telat' => 'label-kuning', 'izin' => 'label-merek', 'sakit' => 'label-pink', 'alpa' => 'label-merah'];
// Status + jam masuk + keterangan satu sesi/kehadiran.
$status = static function (array $s) use ($statusOpts, $labelKelas): string {
    $k    = (string) ($s['status'] ?? 'hadir');
    $html = '<span class="label ' . ($labelKelas[$k] ?? 'label-buka') . '">' . esc($statusOpts[$k] ?? $k) . '</span>';
    if ((string) ($s['jam_masuk'] ?? '') !== '') {
        $html .= '<span class="text-sm text-teks-2">masuk ' . esc((string) $s['jam_masuk']) . '</span>';
    }
    if ((string) ($s['keterangan'] ?? '') !== '') {
        $html .= '<span class="text-sm text-teks-2">“' . esc((string) $s['keterangan']) . '”</span>';
    }

    return $html;
};
$kosong = static fn (string $judul, string $teks): string => '<div class="kartu"><div class="kosong"><span class="kosong-ikon">' . ikon('clipboard-list')
    . '</span><p class="kosong-judul">' . esc($judul) . '</p><p class="kosong-teks">' . esc($teks) . '</p></div></div>';
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Guru', site_url('guru')], ['Kehadiran guru', null]],
    'ilu'   => 'absensi',
    'label' => 'Layanan guru',
    'judul' => 'Kehadiran guru',
    'ket'   => 'Status kehadiran mengajar guru per tanggal, setelah dicatat sekolah. Tanpa tanda khusus berarti hadir.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-6">
        <div class="kartu">
            <form method="get" class="kartu-isi saringan">
                <div>
                    <label class="lbl" for="pilih-tanggal">Tanggal</label>
                    <input type="date" id="pilih-tanggal" name="tanggal" value="<?= esc($tanggal, 'attr') ?>" class="inp sm:w-56" data-kirim-otomatis required>
                </div>
                <button type="submit" class="tombol tombol-utama h-11"><?= ikon('search') ?>Tampilkan</button>
                <p class="text-base font-semibold text-teks sm:ml-auto sm:self-center"><?= $hariNama !== '' ? esc($hariNama) . ', ' : '' ?><?= esc(\App\Libraries\LayananPublik::tanggal($tanggal, false)) ?></p>
            </form>
            <dl class="ringkas-grid kartu-kaki bg-permukaan-2">
                <?php foreach ($statusOpts as $k => $lbl): ?>
                    <div class="ringkas"><dt><?= esc($lbl) ?></dt><dd><?= (int) ($ringkas[$k] ?? 0) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>

        <?php if (! $recorded): ?>
            <?= $hariAktif
                ? $kosong('Absensi tanggal ini belum tersedia', 'Data kehadiran tampil setelah dicatat oleh pihak sekolah.')
                : $kosong('Bukan hari sekolah', 'Tanggal ini bukan hari sekolah aktif. Pilih tanggal lain.') ?>
        <?php else: ?>
            <?php if (! empty($kerja)): ?>
                <div class="kartu">
                    <div class="kartu-kepala">
                        <div>
                            <h2 class="kartu-judul">Kehadiran kerja</h2>
                            <p class="kartu-ket">Kehadiran di luar jadwal mengajar.</p>
                        </div>
                    </div>
                    <div class="daftar-baris">
                        <?php foreach ($kerja as $k): ?>
                            <div class="baris">
                                <p class="baris-judul sm:w-64 sm:shrink-0"><?= esc($k['nama']) ?></p>
                                <div class="flex flex-wrap items-center gap-2"><?= $status($k) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hariAktif && $total > 0): ?>
                <div>
                    <?php if (! empty($kerja)): ?>
                        <h2 class="judul-kelompok">Absensi mengajar</h2>
                    <?php endif; ?>
                    <div class="space-y-4">
                        <?php foreach ($grup as $g): ?>
                            <div class="kartu">
                                <div class="kartu-kepala"><h3 class="kartu-judul"><?= esc($g['nama']) ?></h3></div>
                                <div class="daftar-baris">
                                    <?php foreach ($g['sesi'] as $s): ?>
                                        <div class="baris">
                                            <div class="sm:w-64 sm:shrink-0">
                                                <p class="font-semibold text-teks">Jam ke-<?= esc($s['jam_ke']) ?> <span class="text-sm font-normal text-teks-2">(<?= esc(substr((string) $s['waktu_mulai'], 0, 5)) ?>–<?= esc(substr((string) $s['waktu_selesai'], 0, 5)) ?>)</span></p>
                                                <p class="text-sm text-teks-2"><?= esc($s['nama_kelas']) ?> · <span class="font-medium text-teks"><?= esc($s['nama_mapel']) ?></span></p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2"><?= $status($s) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (empty($kerja)): ?>
                <?= $hariAktif
                    ? $kosong('Belum ada jadwal', 'Belum ada jadwal mengajar pada hari ' . $hariNama . '.')
                    : $kosong('Tidak ada absensi mengajar', 'Tidak ada absensi mengajar pada tanggal ini.') ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
