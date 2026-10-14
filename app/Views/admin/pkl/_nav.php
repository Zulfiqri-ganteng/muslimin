<?php
/**
 * Tab navigasi PKL (dipakai semua halaman staf PKL).
 *
 * @var string                $tab        beranda | daftar_{status} | siswa | prakerin | baru | laporan | pengaturan | ttd | hak_akses
 * @var array<string, int>    $hitungTab  jumlah ajuan per status
 */
$tabs = [
    ['beranda', 'Ringkasan', site_url('admin/pkl'), null],
    ['daftar_menunggu', 'Menunggu ACC', site_url('admin/pkl/daftar/menunggu'), $hitungTab['menunggu'] ?? 0],
    ['daftar_perbaikan', 'Perbaikan', site_url('admin/pkl/daftar/perbaikan'), $hitungTab['perbaikan'] ?? 0],
    ['daftar_disetujui', 'Disetujui', site_url('admin/pkl/daftar/disetujui'), $hitungTab['disetujui'] ?? 0],
    ['daftar_ditolak', 'Ditolak', site_url('admin/pkl/daftar/ditolak'), $hitungTab['ditolak'] ?? 0],
    ['siswa', 'Status Siswa', site_url('admin/pkl/siswa'), null],
    ['prakerin', 'Data Prakerin', site_url('admin/pkl/prakerin'), null],
];
// Menu bersyarat: mengikuti hak yang diatur Admin (Sistem → Hak Akses).
$peranNav = (string) (session('admin')['role'] ?? '');
if (\App\Libraries\HakAkses::bolehPkl($peranNav, 'laporan')) {
    $tabs[] = ['laporan', 'Laporan Pembayaran', site_url('admin/pkl/laporan'), null];
}
if (\App\Libraries\HakAkses::boleh($peranNav, 'admin/pkl/ttd')) {
    $tabs[] = ['ttd', 'Tanda Tangan', site_url('admin/pkl/ttd'), null];
}
if (\App\Libraries\HakAkses::boleh($peranNav, 'admin/pkl/pengaturan')) {
    $tabs[] = ['pengaturan', 'Pengaturan', site_url('admin/pkl/pengaturan'), null];
}
if (\App\Libraries\HakAkses::boleh($peranNav, 'admin/hak-akses')) { // khusus Admin
    $tabs[] = ['hak_akses', 'Hak Akses', site_url('admin/hak-akses?bagian=pkl'), null];
}
?>
<nav class="rise -mx-1 mb-5 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:flex-wrap lg:overflow-visible" aria-label="Menu PKL">
    <?php foreach ($tabs as [$kode, $label, $url, $jumlah]): $aktif = $tab === $kode; ?>
        <a href="<?= esc($url, 'attr') ?>"
           class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition active:scale-95 <?= $aktif ? 'bg-brand-700 text-white shadow-md shadow-brand-700/20' : 'border border-gray-200 bg-white text-gray-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' ?>"<?= $aktif ? ' aria-current="page"' : '' ?>>
            <?= esc($label) ?>
            <?php if ($jumlah !== null): ?>
                <span class="inline-flex min-w-[1.4rem] justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold <?= $aktif ? 'bg-white/20 text-white' : ($kode === 'daftar_menunggu' && $jumlah > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500') ?>"><?= (int) $jumlah ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
