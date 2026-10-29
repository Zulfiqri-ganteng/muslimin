<?php
/**
 * Periksa Data Siswa (PeriksaSiswa, 2026-10-29) — tujuan butir Dashboard "Tugas hari ini". Ringkasan angka, kemungkinan data ganda
 * (pasangan + Detail keduanya), data tidak sesuai (masalah per siswa + Ubah/Detail), kelas hasil penempatan otomatis. Tanpa JS.
 *
 * @var array  $h         PeriksaSiswa::hitung()
 * @var string $urlDaftar
 */
$j       = $h['jumlah'];
$detail  = static fn (int $id): string => site_url('admin/master/siswa/' . $id);
$ubah    = static fn (array $s): string => site_url('admin/master/siswa') . '?' . http_build_query(['q' => $s['nis'], 'edit' => $s['id']]);
$tgl     = static fn (?string $t): string => $t ? date('d-m-Y', strtotime($t)) : '—';
$kosong  = '<span class="text-gray-400">—</span>';
$identitas = static function (array $s) use ($tgl, $detail): string {
    return '<a href="' . $detail($s['id']) . '" class="font-medium text-gray-900 hover:underline dark:text-white">' . esc($s['nama']) . '</a>'
        . '<div class="sel-ket">' . esc($s['kelas'] ?? 'Tanpa kelas') . ' · NIS ' . esc($s['nis']) . ' · NISN ' . esc($s['nisn'] ?? '—') . ' · lahir ' . esc($tgl($s['tanggal_lahir'])) . '</div>';
};
$ringkasan = [
    ['Kemungkinan data ganda', $j['ganda'], '#ganda', $j['ganda'] > 0 ? 'lencana-merah' : 'lencana-hijau'],
    ['Data tidak sesuai', $j['tidak_sesuai'], '#tidak-sesuai', $j['tidak_sesuai'] > 0 ? 'lencana-kuning' : 'lencana-hijau'],
    ['… tidak bisa masuk Layanan Siswa', $j['tidak_bisa_masuk'], '#tidak-sesuai', $j['tidak_bisa_masuk'] > 0 ? 'lencana-merah' : 'lencana-hijau'],
    ['Penempatan kelas otomatis', $j['penempatan'], '#penempatan', 'lencana-abu'],
    ['Belum punya kelas', $j['tanpa_kelas'], site_url('admin/master/siswa?kelas_id=tanpa'), 'lencana-abu'],
    ['Tidak aktif (lulus/pindah/keluar)', $j['tidak_aktif'], site_url('admin/master/siswa?status=nonaktif'), 'lencana-abu'],
    ['Terhapus (lama)', $j['terhapus'], site_url('admin/master/siswa?status=terhapus'), 'lencana-abu'],
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($urlDaftar, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Daftar siswa</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'siswa_periksa',
    'helpTitle' => 'Periksa Data Siswa',
    'helpBody'  => '<p>Pemeriksaan otomatis data siswa aktif. <b>Data ganda</b> = satu siswa kemungkinan tercatat dua kali (nama mirip di tingkat yang sama, dan salah satunya datanya belum lengkap) — buka Detail keduanya, pakai data yang benar, lalu <b>Hapus permanen</b> atau <b>Nonaktifkan</b> yang salah. Dua data yang sama-sama lengkap (NISN berbeda) tidak dianggap ganda — misalnya anak kembar. <b>Data tidak sesuai</b> = NIS/NISN tidak wajar atau tanggal lahir kosong; siswa tanpa NISN sah DAN tanpa tanggal lahir tidak bisa masuk Layanan Siswa/Biodata — betulkan lewat tombol Ubah.</p>',
]) ?>

<section class="kartu" aria-labelledby="judul-ringkas-periksa">
    <div class="kartu-kepala"><h2 id="judul-ringkas-periksa" class="kartu-judul">Ringkasan</h2></div>
    <ul class="kartu-isi grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($ringkasan as [$label, $n, $url, $lencana]): ?>
            <li><a href="<?= esc($url, 'attr') ?>" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                <span class="text-gray-900 dark:text-white"><?= esc($label) ?></span><span class="lencana <?= $lencana ?> tabular-nums"><?= (int) $n ?></span></a></li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="kartu mt-4 overflow-hidden" id="ganda" aria-labelledby="judul-ganda">
    <div class="kartu-kepala">
        <h2 id="judul-ganda" class="kartu-judul flex items-center gap-2">Kemungkinan data ganda<span class="lencana <?= $j['ganda'] > 0 ? 'lencana-merah' : 'lencana-hijau' ?>"><?= (int) $j['ganda'] ?></span></h2>
        <p class="kartu-ket">Kiri = data yang lebih lengkap (biasanya data resmi). Kanan = data yang kemungkinan salah input.</p>
    </div>
    <?php if ($h['ganda'] === []): ?>
        <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Tidak ada kemungkinan data ganda']) ?>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead><tr><th scope="col">Data lebih lengkap</th><th scope="col">Kemungkinan salah input</th><th scope="col">Alasan</th></tr></thead>
                <tbody>
                    <?php foreach ($h['ganda'] as $g): ?>
                        <tr>
                            <td><?= $identitas($g['a']) ?></td>
                            <td><?= $identitas($g['b']) ?></td>
                            <td class="text-xs"><?= esc(ucfirst($g['alasan'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="kartu mt-4 overflow-hidden" id="tidak-sesuai" aria-labelledby="judul-tidak-sesuai">
    <div class="kartu-kepala">
        <h2 id="judul-tidak-sesuai" class="kartu-judul flex items-center gap-2">Data tidak sesuai<span class="lencana <?= $j['tidak_sesuai'] > 0 ? 'lencana-kuning' : 'lencana-hijau' ?>"><?= (int) $j['tidak_sesuai'] ?></span></h2>
        <p class="kartu-ket">NIS wajar = 4–12 angka; NISN sah = 10 angka; keduanya bukan angka berulang.</p>
    </div>
    <?php if ($h['tidak_sesuai'] === []): ?>
        <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Semua data siswa aktif wajar']) ?>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead><tr><th scope="col">Siswa</th><th scope="col">Masalah</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    <?php foreach ($h['tidak_sesuai'] as $s): ?>
                        <tr>
                            <td><?= $identitas($s) ?></td>
                            <td><div class="flex flex-wrap gap-1"><?php foreach ($s['masalah'] as $m): ?>
                                <span class="lencana <?= $m === 'Tidak bisa masuk Layanan Siswa' ? 'lencana-merah' : 'lencana-kuning' ?>"><?= esc($m) ?></span>
                            <?php endforeach; ?></div></td>
                            <td class="whitespace-nowrap text-end">
                                <a href="<?= esc($ubah($s), 'attr') ?>" class="btn btn-utama btn-sm"><?= ikon('edit', 'h-4 w-4') ?>Ubah</a>
                                <a href="<?= $detail($s['id']) ?>" class="btn btn-netral btn-sm"><?= ikon('eye', 'h-4 w-4') ?>Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="kartu mt-4 overflow-hidden" id="penempatan" aria-labelledby="judul-penempatan">
    <div class="kartu-kepala">
        <h2 id="judul-penempatan" class="kartu-judul flex items-center gap-2">Kelas hasil penempatan otomatis<span class="lencana lencana-abu"><?= (int) $j['penempatan'] ?></span></h2>
        <p class="kartu-ket">Data resmi sekolah hanya mencatat tingkat & jurusan (mis. "XII TKJ"), jadi nomor kelasnya dipilih otomatis. Pastikan benar, lalu Ubah bila salah (keterangan "Perlu penempatan kelas" boleh dihapus saat menyimpan).</p>
    </div>
    <?php if ($h['penempatan'] === []): ?>
        <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Tidak ada']) ?>
    <?php else: ?>
        <?= ui_lipat_buka(['judul' => 'Daftar siswa', 'ringkas' => (int) $j['penempatan'] . ' siswa', 'id' => 'lipat-penempatan']) ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th scope="col">Siswa</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($h['penempatan'] as $s): ?>
                            <tr>
                                <td><?= $identitas($s) ?></td>
                                <td class="whitespace-nowrap text-end"><a href="<?= esc($ubah($s), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('edit', 'h-4 w-4') ?>Ubah</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?= ui_lipat_tutup() ?>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
