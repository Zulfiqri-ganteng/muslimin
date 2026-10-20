<?php
/**
 * Tab navigasi PKL (dipakai semua halaman staf PKL) — bilah tab kit .tab-daftar/.tab (rombak tampilan F9), jumlah ajuan di
 * .tab-jumlah (kuning bila ada yang menunggu ACC). Bergulir menyamping di layar sempit.
 *
 * @var string                $tab        beranda | daftar_{status} | siswa | prakerin | baru | laporan | pengaturan | ttd | alasan | hak_akses
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
if (\App\Libraries\PklAlasan::bolehKelola($peranNav)) {
    $tabs[] = ['alasan', 'Template Alasan', site_url('admin/pkl/alasan'), null];
}
if (\App\Libraries\HakAkses::boleh($peranNav, 'admin/pkl/pengaturan')) {
    $tabs[] = ['pengaturan', 'Pengaturan', site_url('admin/pkl/pengaturan'), null];
}
if (\App\Libraries\HakAkses::boleh($peranNav, 'admin/hak-akses')) { // khusus Admin
    $tabs[] = ['hak_akses', 'Hak Akses', site_url('admin/hak-akses?bagian=pkl'), null];
}
?>
<div class="mb-5 overflow-x-auto">
    <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Menu PKL">
        <?php foreach ($tabs as [$kode, $label, $url, $jumlah]): $aktif = $tab === $kode; ?>
            <a href="<?= esc($url, 'attr') ?>" class="tab whitespace-nowrap"<?= $aktif ? ' aria-current="page"' : '' ?>>
                <?= esc($label) ?>
                <?php if ($jumlah !== null): ?>
                    <span class="tab-jumlah<?= $kode === 'daftar_menunggu' && $jumlah > 0 ? ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' ?>"><?= (int) $jumlah ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
