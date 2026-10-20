<?php
/**
 * Butir satu Instrumen Supervisi (kit F5, mode CRUD) — tabel butir berkelompok aspek, tombol geser naik/turun (POST), dialog
 * Tambah/Ubah (komponen "masterList" bawaan app.js; isian aspek memakai datalist aspek yang sudah ada).
 *
 * @var array<string, mixed>       $instrumen
 * @var list<array<string, mixed>> $rows   butir urut (Libraries\Supervisi::daftarButir)
 * @var list<string>               $aspek  aspek yang sudah dipakai (saran isian)
 * @var bool                       $kelola
 */

use App\Libraries\Supervisi;

$base   = site_url('admin/supervisi/instrumen/' . (int) $instrumen['id'] . '/butir');
$jumlah = count($rows);
$aktif  = count(array_filter($rows, static fn (array $r): bool => (int) $r['aktif'] === 1));
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_butir_v1',
    'helpTitle' => 'Butir instrumen',
    'helpKelas' => '',
    'helpBody'  => '<p>Butir = hal yang dinilai supervisor, dikelompokkan per <b>aspek</b> (mis. Kegiatan Pendahuluan, Kegiatan Inti). Urutan di sini = urutan di lembar penilaian; pakai tombol panah untuk memindahkan.</p>'
        . '<p class="mt-2">Mengubah atau menghapus butir <b>tidak mengubah hasil supervisi yang sudah tersimpan</b> (hasil lama menyimpan salinan butirnya). Butir yang dimatikan <b>Aktif</b>-nya tidak muncul di lembar penilaian baru.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= $base ?>" data-entity="butir"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'aspek' => $aspek !== [] ? end($aspek) : '', 'uraian' => '', 'aktif' => true]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'      => $base,
        'cari'         => null,
        'kiriHtml'     => '<a href="' . site_url('admin/supervisi/instrumen') . '" class="btn btn-teks -ms-2">' . ikon('chevron-left', 'h-4 w-4') . 'Semua instrumen</a>'
            . '<span class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white">' . esc($instrumen['nama']) . '</b> · '
            . esc(Supervisi::JENIS[$instrumen['jenis']] ?? $instrumen['jenis']) . ' · ' . $aktif . ' butir aktif'
            . ((int) $instrumen['aktif'] === 1 ? '' : ' · <span class="lencana lencana-kuning">Instrumen nonaktif</span>') . '</span>',
        'tambah'       => $kelola,
        'labelTambah'  => 'Tambah butir',
        'impor'        => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="w-12">No</th>
                    <th scope="col">Butir yang dinilai</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="4"><?= ui_kosong(['ikon' => 'list', 'judul' => 'Belum ada butir', 'teks' => 'Tekan Tambah butir untuk mengisi lembar penilaian ini.']) ?></td>
                    </tr>
                <?php else: $aspekLalu = null; foreach ($rows as $i => $r): ?>
                    <?php if ($r['aspek'] !== $aspekLalu): $aspekLalu = $r['aspek']; ?>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th scope="colgroup" colspan="4" class="px-4 py-2 text-left text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300"><?= esc($r['aspek']) ?></th>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="max-w-2xl"><p class="whitespace-normal text-gray-900 dark:text-white"><?= esc($r['uraian']) ?></p></td>
                        <td>
                            <?php if ((int) $r['aktif'] === 1): ?>
                                <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                            <?php else: ?>
                                <span class="titik-status font-medium">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?php
                            $geser = '';
                            if ($kelola) {
                                foreach (['naik' => [$i > 0, 'Pindah ke atas', 'rotate-180'], 'turun' => [$i < $jumlah - 1, 'Pindah ke bawah', '']] as $arah => [$bisa, $label, $putar]) {
                                    $geser .= '<form method="post" action="' . site_url('admin/supervisi/butir/' . (int) $r['id'] . '/geser') . '">' . csrf_field()
                                        . '<input type="hidden" name="arah" value="' . $arah . '">'
                                        . '<button type="submit" class="btn btn-netral btn-sm btn-ikon" title="' . $label . '" aria-label="' . $label . '"' . ($bisa ? '' : ' disabled') . '>'
                                        . ikon('chevron-down', 'h-4 w-4 ' . $putar) . '</button></form>';
                                }
                            }
                            ?>
                            <?= ui_crud_aksi([
                                'tambahanHtml' => $geser,
                                'row'          => $kelola ? ['id' => (int) $r['id'], 'aspek' => $r['aspek'], 'uraian' => $r['uraian'], 'aktif' => (int) $r['aktif'] === 1] : null,
                                'hapusUrl'     => $kelola ? site_url('admin/supervisi/butir/delete/' . (int) $r['id']) : null,
                                'konfirmasi'   => 'Hapus butir "' . mb_strimwidth((string) $r['uraian'], 0, 80, '…') . '"? Hasil supervisi lama tetap menyimpannya.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => $jumlah, 'satuan' => 'butir']) ?>

    <?php if ($kelola): ?>
        <?= ui_crud_form_buka(['benda' => 'Butir', 'ukuran' => 'lg']) ?>
            <div>
                <label class="lbl" for="sb-aspek">Aspek <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="sb-aspek" name="aspek" x-model="form.aspek" list="sb-aspek-daftar" maxlength="<?= Supervisi::MAKS_ASPEK ?>" required class="inp" placeholder="mis. Kegiatan Inti">
                <datalist id="sb-aspek-daftar">
                    <?php foreach ($aspek as $a): ?>
                        <option value="<?= esc($a, 'attr') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="hint mt-1">Butir dengan aspek yang sama dikelompokkan di lembar penilaian. Aspek baru → tulis nama baru.</p>
            </div>
            <div>
                <label class="lbl" for="sb-uraian">Uraian butir <span class="text-red-600 dark:text-red-400">*</span></label>
                <textarea id="sb-uraian" name="uraian" x-model="form.uraian" rows="3" maxlength="<?= Supervisi::MAKS_URAIAN ?>" required class="inp"
                          placeholder="mis. Guru menyampaikan tujuan pembelajaran di awal kegiatan."></textarea>
            </div>
            <label class="saklar">
                <input type="checkbox" name="aktif" value="1" x-model="form.aktif" class="peer sr-only">
                <span class="saklar-jalur"></span>
                <span class="saklar-label">Aktif (tampil di lembar penilaian)</span>
            </label>
        <?= ui_crud_form_tutup() ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
