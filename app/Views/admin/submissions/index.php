<?php
/**
 * Data Kesediaan Guru — daftar isian formulir kesediaan (rombak tampilan F6: pola halaman data template Flowbite). Tanpa Tambah/Import
 * (data datang dari formulir guru); Export Excel + Rekap PDF ikut saringan status; aksi per baris: Detail, Surat (PDF), Hapus.
 * Siap CSP: tanpa onchange sebaris (pilihan "Tampilkan N" memakai data-autosubmit dari kit).
 *
 * @var string                        $q      Kata kunci
 * @var string                        $status Saringan status kepegawaian
 * @var int                           $per    Baris per halaman
 * @var array                         $rows   Baris halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager  Paginasi
 * @var int                           $total  Total data (sesuai saringan)
 */
$q      = (string) ($q ?? '');
$status = (string) ($status ?? '');
$per    = (int) ($per ?? 10);
$qs     = $status !== '' ? '?status=' . rawurlencode($status) : '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'kesediaan',
    'helpTitle' => 'Data Kesediaan Guru',
    'helpKelas' => '',
    'helpBody'  => '<p>Rekap isian formulir kesediaan mengajar yang dikirim guru (preferensi mapel, hari/jam tersedia, tugas tambahan). Data ini bisa <b>diimpor menjadi Master Guru</b> lewat menu Guru ▸ Import ▸ Impor dari Data Kesediaan.</p>
        <p class="mt-1">• <b>Export</b> (Excel) dan <b>Rekap PDF</b> mengikuti saringan status yang aktif. • <b>Surat</b> mencetak surat kesediaan satu guru (PDF).</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => site_url('admin/submissions'),
    'cari'         => 'Cari nama, NIP/NUPTK, atau mapel…',
    'q'            => $q,
    'filter'       => [['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => ['PNS' => 'PNS', 'PPPK' => 'PPPK', 'GTY' => 'GTY', 'GTT' => 'GTT']]],
    'per'          => $per,
    'tambah'       => false,
    'impor'        => false,
    'eksporUrl'    => site_url('admin/export/excel' . $qs),
    'eksporTitle'  => 'Unduh data kesediaan (sesuai saringan status) ke file Excel',
    'tambahanHtml' => '<a href="' . site_url('admin/export/rekap-pdf' . $qs) . '" target="_blank" rel="noopener" class="btn btn-netral" title="Rekap kesediaan dalam PDF (tab baru)">'
        . ikon('printer', 'h-4 w-4') . 'Rekap PDF</a>',
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col" class="w-12">#</th>
                <th scope="col">Nama guru</th>
                <th scope="col" class="hidden md:table-cell">NIP / NUPTK</th>
                <th scope="col" class="hidden lg:table-cell">Mapel</th>
                <th scope="col">Status</th>
                <th scope="col" class="hidden text-center sm:table-cell">Jam</th>
                <th scope="col" class="hidden lg:table-cell">Tanggal</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8">
                        <?= ui_kosong(['ikon' => 'clipboard-list', 'judul' => ($q !== '' || $status !== '') ? 'Data tidak ditemukan' : 'Belum ada isian kesediaan',
                            'teks' => ($q !== '' || $status !== '') ? 'Ubah pencarian/saringan, atau tekan Reset.' : 'Isian dari formulir kesediaan guru akan muncul di sini.']) ?>
                    </td>
                </tr>
            <?php else: $offset = $pager ? ($pager->getCurrentPage() - 1) * $per : 0; foreach ($rows as $i => $r): ?>
                <tr>
                    <td class="sel-ket"><?= $offset + $i + 1 ?></td>
                    <td class="min-w-44">
                        <div class="font-semibold text-gray-900 dark:text-white"><?= esc($r['nama_lengkap']) ?></div>
                        <div class="sel-ket md:hidden"><?= esc($r['nip_nuptk']) ?></div>
                    </td>
                    <td class="sel-ket hidden whitespace-nowrap md:table-cell"><?= esc($r['nip_nuptk']) ?></td>
                    <td class="sel-ket hidden lg:table-cell"><?= esc($r['guru_mapel'] ?: '—') ?></td>
                    <td><span class="lencana lencana-merek"><?= esc($r['status_kepegawaian']) ?></span></td>
                    <td class="hidden text-center sm:table-cell"><?= (int) $r['total_jam'] ?></td>
                    <td class="sel-ket hidden whitespace-nowrap lg:table-cell"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                    <td class="kolom-aksi">
                        <?= ui_crud_aksi([
                            'ubah'         => false,
                            'detailUrl'    => site_url('admin/submissions/view/' . $r['id']),
                            'hapusUrl'     => site_url('admin/submissions/delete/' . $r['id']),
                            'konfirmasi'   => 'Hapus data kesediaan dari ' . $r['nama_lengkap'] . '?',
                            'tambahanHtml' => '<a href="' . site_url('admin/export/surat/' . $r['id']) . '" target="_blank" rel="noopener" class="btn btn-netral btn-sm" title="Cetak surat kesediaan (PDF, tab baru)">' . ikon('printer', 'h-4 w-4') . 'Surat</a>',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'status', 'per'], 'satuan' => 'isian kesediaan']) ?>
<?= $this->endSection() ?>
