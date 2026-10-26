<?php
/**
 * Tab status Kotak Masuk PKL (Menunggu ACC / Perbaikan / Disetujui / Ditolak + jumlahnya; kuning bila ada yang menunggu ACC).
 * Rapi tampilan U2 (2026-10-26, docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md): bilah tab berisi SEMUA halaman PKL (Ringkasan, Status Siswa,
 * Data Prakerin, Laporan, Tanda Tangan, Template Alasan, Pengaturan, Hak Akses) DIHAPUS karena sama persis dengan menu samping — kini
 * hanya tab status yang tampil, dan hanya di halaman Kotak Masuk ($tab = daftar_{status}). Halaman lain: partial ini tidak menulis apa pun.
 * Bilah tab kit .tab-daftar/.tab + .tab-jumlah, bergulir menyamping di layar sempit.
 *
 * @var string                $tab        beranda | daftar_{status} | siswa | prakerin | baru | laporan | pengaturan | ttd | alasan | hak_akses
 * @var array<string, int>    $hitungTab  jumlah ajuan per status
 */
if (! str_starts_with((string) $tab, 'daftar_')) {
    return;
}
$tabs = [
    ['daftar_menunggu', 'Menunggu ACC', site_url('admin/pkl/daftar/menunggu'), $hitungTab['menunggu'] ?? 0],
    ['daftar_perbaikan', 'Perbaikan', site_url('admin/pkl/daftar/perbaikan'), $hitungTab['perbaikan'] ?? 0],
    ['daftar_disetujui', 'Disetujui', site_url('admin/pkl/daftar/disetujui'), $hitungTab['disetujui'] ?? 0],
    ['daftar_ditolak', 'Ditolak', site_url('admin/pkl/daftar/ditolak'), $hitungTab['ditolak'] ?? 0],
];
?>
<div class="mb-5 overflow-x-auto">
    <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Status ajuan PKL">
        <?php foreach ($tabs as [$kode, $label, $url, $jumlah]): $aktif = $tab === $kode; ?>
            <a href="<?= esc($url, 'attr') ?>" class="tab whitespace-nowrap"<?= $aktif ? ' aria-current="page"' : '' ?>>
                <?= esc($label) ?>
                <span class="tab-jumlah<?= $kode === 'daftar_menunggu' && $jumlah > 0 ? ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' ?>"><?= (int) $jumlah ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
