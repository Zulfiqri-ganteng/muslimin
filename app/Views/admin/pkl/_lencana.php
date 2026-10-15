<?php
/**
 * Lencana status PKL — kelas lencana kit (rombak tampilan F9; dulu warna amber/teal/emerald/fuchsia buatan sendiri).
 *
 * @var string $kode     menunggu | perbaikan | disetujui | ditolak | belum | belum_mulai | sedang | selesai
 * @var bool   $siswa    (opsional) label gaya "Status Siswa" (mis. ditolak = perlu ajukan ulang)
 */
$peta = [
    'menunggu'    => ['Menunggu ACC', 'lencana-merek'],
    'perbaikan'   => ['Perlu perbaikan', 'lencana-kuning'],
    'disetujui'   => ['Disetujui', 'lencana-hijau'],
    'ditolak'     => ['Ditolak', 'lencana-merah'],
    'belum'       => ['Belum mengisi', 'lencana-abu'],
    'belum_mulai' => ['Disetujui · belum mulai', 'lencana-biru'],
    'sedang'      => ['Sedang PKL', 'lencana-hijau'],
    'selesai'     => ['Selesai PKL', 'lencana-ungu'],
];
[$label, $warna] = $peta[$kode] ?? [ucfirst((string) $kode), 'lencana-abu'];
if (($siswa ?? false) && $kode === 'ditolak') {
    $label = 'Ditolak · perlu ajukan ulang';
}
?>
<span class="lencana <?= $warna ?> whitespace-nowrap"><?= esc($label) ?></span>
