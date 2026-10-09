<?php
/**
 * Tab status Surat Sekolah (Daftar Surat). Tab membawa serta saringan lain (jenis, tahun, pencarian) yang sedang dipakai.
 *
 * @var array<string, int>   $hitung jumlah surat: semua + per status
 * @var array<string, mixed> $f      saringan aktif: status, jenis, tahun, q
 */

use App\Models\SuratSekolahModel;

$statusAktif = (string) ($f['status'] ?? '');
$bawa        = array_filter(
    ['jenis' => $f['jenis'] ?? '', 'tahun' => $f['tahun'] ?? 0, 'q' => $f['q'] ?? ''],
    static fn ($v) => $v !== '' && $v !== 0 && $v !== null
);
$tabs = [['', 'Semua', (int) ($hitung['semua'] ?? 0)]];
foreach (SuratSekolahModel::STATUS as $s) {
    $tabs[] = [$s, SuratSekolahModel::TAMPIL_STATUS[$s][0], (int) ($hitung[$s] ?? 0)];
}
?>
<nav class="rise -mx-1 mb-5 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:flex-wrap lg:overflow-visible" aria-label="Status surat">
    <?php foreach ($tabs as [$kode, $label, $jumlah]):
        $aktif = $statusAktif === $kode;
        $url   = site_url('admin/surat') . ($kode !== '' || $bawa !== [] ? '?' . http_build_query($bawa + ($kode !== '' ? ['status' => $kode] : [])) : '');
    ?>
        <a href="<?= esc($url, 'attr') ?>"
           class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition active:scale-95 <?= $aktif ? 'bg-brand-700 text-white shadow-md shadow-brand-700/20' : 'border border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' ?>"<?= $aktif ? ' aria-current="page"' : '' ?>>
            <?= esc($label) ?>
            <span class="inline-flex min-w-[1.4rem] justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold <?= $aktif ? 'bg-white/20 text-white' : ($kode === 'menunggu' && $jumlah > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500') ?>"><?= $jumlah ?></span>
        </a>
    <?php endforeach; ?>
</nav>
