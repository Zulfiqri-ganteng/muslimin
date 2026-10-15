<?php
/**
 * Tab status Surat Sekolah (Daftar Surat). Tab membawa serta saringan lain (jenis, tahun, pencarian) yang sedang dipakai.
 * Rombak tampilan F9: bilah tab kit .tab-daftar/.tab + .tab-jumlah (kuning bila ada yang menunggu ACC), bergulir menyamping
 * di layar sempit.
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
<div class="mb-5 overflow-x-auto">
    <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Status surat">
        <?php foreach ($tabs as [$kode, $label, $jumlah]):
            $aktif = $statusAktif === $kode;
            $url   = site_url('admin/surat') . ($kode !== '' || $bawa !== [] ? '?' . http_build_query($bawa + ($kode !== '' ? ['status' => $kode] : [])) : '');
        ?>
            <a href="<?= esc($url, 'attr') ?>" class="tab whitespace-nowrap"<?= $aktif ? ' aria-current="page"' : '' ?>>
                <?= esc($label) ?>
                <span class="tab-jumlah<?= $kode === 'menunggu' && $jumlah > 0 ? ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' ?>"><?= $jumlah ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
