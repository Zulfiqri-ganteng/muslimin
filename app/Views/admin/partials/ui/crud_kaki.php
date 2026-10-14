<?php
/**
 * Bilah bawah halaman data (kit F5, pola template content/crud/users.html: menempel di bawah layar, "Showing 1-20 of 2290" +
 * tombol halaman). Berisi paginasi kit (pager/admin.php: "Menampilkan x–y dari N" + nomor halaman); bila hanya satu halaman,
 * cukup jumlah datanya. Panggil: ui_crud_kaki(['pager' => $pager, 'total' => $total, 'kunci' => ['q', 'per']]).
 *
 * @var array{pager?: \CodeIgniter\Pager\Pager|null, total?: int, kunci?: list<string>, grup?: string, satuan?: string} $ui
 */
$pager  = $ui['pager'] ?? null;
$total  = (int) ($ui['total'] ?? 0);
$tautan = $pager ? trim((string) $pager->only($ui['kunci'] ?? ['q', 'per'])->links($ui['grup'] ?? 'default', 'admin')) : '';
?>
<div class="kaki-crud">
    <?php if ($tautan !== ''): ?>
        <?= $tautan ?>
    <?php else: ?>
        <p class="paginasi-info">Menampilkan <b><?= number_format($total, 0, ',', '.') ?></b> <?= esc((string) ($ui['satuan'] ?? 'data')) ?></p>
    <?php endif; ?>
</div>
