<?php
/**
 * Tab navigasi PKL (dipakai semua halaman staf PKL).
 *
 * @var string                $tab        beranda | daftar_{status} | siswa | baru | pengaturan
 * @var array<string, int>    $hitungTab  jumlah ajuan per status
 */
$tabs = [
    ['beranda', 'Ringkasan', site_url('admin/pkl'), null],
    ['daftar_menunggu', 'Menunggu ACC', site_url('admin/pkl/daftar/menunggu'), $hitungTab['menunggu'] ?? 0],
    ['daftar_perbaikan', 'Perbaikan', site_url('admin/pkl/daftar/perbaikan'), $hitungTab['perbaikan'] ?? 0],
    ['daftar_disetujui', 'Disetujui', site_url('admin/pkl/daftar/disetujui'), $hitungTab['disetujui'] ?? 0],
    ['daftar_ditolak', 'Ditolak', site_url('admin/pkl/daftar/ditolak'), $hitungTab['ditolak'] ?? 0],
    ['siswa', 'Status Siswa', site_url('admin/pkl/siswa'), null],
];
?>
<nav class="-mx-1 mb-5 flex gap-1.5 overflow-x-auto px-1 pb-1" aria-label="Menu PKL">
    <?php foreach ($tabs as [$kode, $label, $url, $jumlah]): $aktif = $tab === $kode; ?>
        <a href="<?= esc($url, 'attr') ?>"
           class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition <?= $aktif ? 'bg-brand-700 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>">
            <?= esc($label) ?>
            <?php if ($jumlah !== null): ?>
                <span class="inline-flex min-w-[1.4rem] justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold <?= $aktif ? 'bg-white/20 text-white' : ($kode === 'daftar_menunggu' && $jumlah > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500') ?>"><?= (int) $jumlah ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
