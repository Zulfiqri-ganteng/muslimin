<?php
/**
 * Lencana status PKL. Kelas ditulis utuh agar terbaca Tailwind.
 *
 * @var string $kode     menunggu | perbaikan | disetujui | ditolak | belum | belum_mulai | sedang | selesai
 * @var bool   $siswa    (opsional) label gaya "Status Siswa" (mis. ditolak = perlu ajukan ulang)
 */
$peta = [
    'menunggu'    => ['Menunggu ACC', 'bg-blue-100 text-blue-700'],
    'perbaikan'   => ['Perlu perbaikan', 'bg-amber-100 text-amber-800'],
    'disetujui'   => ['Disetujui', 'bg-green-100 text-green-700'],
    'ditolak'     => ['Ditolak', 'bg-red-100 text-red-700'],
    'belum'       => ['Belum mengisi', 'bg-slate-200 text-slate-600'],
    'belum_mulai' => ['Disetujui · belum mulai', 'bg-teal-100 text-teal-700'],
    'sedang'      => ['Sedang PKL', 'bg-emerald-100 text-emerald-700'],
    'selesai'     => ['Selesai PKL', 'bg-indigo-100 text-indigo-700'],
];
[$label, $warna] = $peta[$kode] ?? [ucfirst((string) $kode), 'bg-slate-100 text-slate-600'];
if (($siswa ?? false) && $kode === 'ditolak') {
    $label = 'Ditolak · perlu ajukan ulang';
}
?>
<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $warna ?>"><?= esc($label) ?></span>
