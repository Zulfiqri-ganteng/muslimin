<?php
/**
 * Instrumen Supervisi (menu KURIKULUM → Supervisi Guru) — daftar lembar penilaian supervisi akademik (kit F5, mode CRUD). Komponen
 * "masterList" bawaan app.js (dialog Tambah/Ubah). Tombol Butir → halaman butir instrumen. Instrumen yang sudah dipakai jadwal/hasil
 * tidak punya tombol Hapus (nonaktifkan lewat Ubah). "Pasang instrumen bawaan" = pasang lagi instrumen bawaan yang belum ada.
 *
 * @var list<array<string, mixed>> $rows   Libraries\Supervisi::daftarInstrumen (+ butir_aktif, butir_semua, dipakai)
 * @var bool                       $kelola boleh menambah/mengubah/menghapus (Hak Akses level Kelola)
 */

use App\Libraries\Supervisi;

$base = site_url('admin/supervisi/instrumen');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_instrumen_v1',
    'helpTitle' => 'Instrumen supervisi',
    'helpKelas' => '',
    'helpBody'  => '<p><b>Instrumen</b> = lembar penilaian yang dipakai supervisor saat menyupervisi guru. Sudah tersedia tiga instrumen bawaan: <b>Perencanaan</b> (telaah modul ajar), <b>Pelaksanaan</b> (observasi kelas), dan <b>Penilaian</b> (asesmen). Tekan <b>Butir</b> untuk melihat atau mengubah butir-butir yang dinilai.</p>'
        . '<p class="mt-2">Tiap butir dinilai <b>1 = Kurang, 2 = Cukup, 3 = Baik, 4 = Sangat Baik</b>. Bila sekolah punya format sendiri, cukup ubah instrumen dan butirnya di sini. Instrumen yang sudah dipakai jadwal tidak bisa dihapus — matikan <b>Aktif</b> agar tidak bisa dipilih lagi.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= $base ?>" data-entity="instrumen"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'kode' => '', 'nama' => '', 'jenis' => 'pelaksanaan', 'keterangan' => '', 'urut' => '', 'aktif' => true]), 'attr') ?>">

    <?php
    $pasang = $kelola
        ? '<form method="post" action="' . $base . '/bawaan">' . csrf_field()
            . '<button type="submit" class="btn btn-netral" data-confirm="Pasang lagi instrumen bawaan (Perencanaan, Pelaksanaan, Penilaian) yang belum ada? Instrumen yang sudah ada tidak diubah." data-confirm-judul="Pasang instrumen bawaan?" data-confirm-ya="Ya, pasang">'
            . ikon('file-copy', 'h-4 w-4') . 'Pasang instrumen bawaan</button></form>'
        : '';
    ?>
    <?= ui_crud_alat([
        'baseUrl'      => $base,
        'cari'         => null,
        'kiriHtml'     => '<p class="text-sm text-gray-500 dark:text-gray-400">Lembar penilaian supervisi akademik. Skor tiap butir 1–4.</p>',
        'tambah'       => $kelola,
        'labelTambah'  => 'Tambah instrumen',
        'impor'        => false,
        'tambahanHtml' => $pasang,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="w-12">No</th>
                    <th scope="col">Instrumen</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="text-right">Butir</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6"><?= ui_kosong(['ikon' => 'clipboard-check', 'judul' => 'Belum ada instrumen', 'teks' => 'Tekan "Pasang instrumen bawaan" atau Tambah instrumen.']) ?></td>
                    </tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <p class="sel-judul"><?= esc($r['nama']) ?></p>
                            <p class="sel-ket">Kode <?= esc($r['kode']) ?><?= ($r['keterangan'] ?? '') !== '' ? ' · ' . esc($r['keterangan']) : '' ?></p>
                        </td>
                        <td><span class="lencana-merek"><?= esc(Supervisi::JENIS[$r['jenis']] ?? $r['jenis']) ?></span></td>
                        <td class="text-right whitespace-nowrap">
                            <?= $r['butir_aktif'] ?><?php if ($r['butir_semua'] > $r['butir_aktif']): ?><span class="sel-ket"> (+<?= $r['butir_semua'] - $r['butir_aktif'] ?> nonaktif)</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $r['aktif'] === 1): ?>
                                <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                            <?php else: ?>
                                <span class="titik-status font-medium">Nonaktif</span>
                            <?php endif; ?>
                            <?php if ($r['dipakai']): ?><p class="sel-ket">Sudah dipakai jadwal</p><?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'tambahanHtml' => '<a href="' . $base . '/' . (int) $r['id'] . '/butir" class="btn btn-netral btn-sm">' . ikon('list', 'h-4 w-4') . 'Butir</a>',
                                'row'          => $kelola ? ['id' => (int) $r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'jenis' => $r['jenis'],
                                    'keterangan' => (string) ($r['keterangan'] ?? ''), 'urut' => (int) $r['urut'], 'aktif' => (int) $r['aktif'] === 1] : null,
                                'hapusUrl'     => $kelola && ! $r['dipakai'] ? $base . '/delete/' . (int) $r['id'] : null,
                                'konfirmasi'   => 'Hapus instrumen "' . $r['nama'] . '" beserta semua butirnya?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'instrumen']) ?>

    <?php if ($kelola): ?>
        <?= ui_crud_form_buka(['benda' => 'Instrumen', 'ukuran' => 'lg']) ?>
            <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                <div>
                    <label class="lbl" for="si-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
                    <input type="text" id="si-kode" name="kode" x-model="form.kode" maxlength="<?= Supervisi::MAKS_KODE ?>" required class="inp uppercase" placeholder="PELAKSANAAN">
                </div>
                <div>
                    <label class="lbl" for="si-nama">Nama instrumen <span class="text-red-600 dark:text-red-400">*</span></label>
                    <input type="text" id="si-nama" name="nama" x-model="form.nama" maxlength="<?= Supervisi::MAKS_NAMA ?>" required class="inp" placeholder="Pelaksanaan Pembelajaran (Observasi Kelas)">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                <div>
                    <label class="lbl" for="si-jenis">Jenis <span class="text-red-600 dark:text-red-400">*</span></label>
                    <select id="si-jenis" name="jenis" x-model="form.jenis" class="inp">
                        <?php foreach (Supervisi::JENIS as $k => $label): ?>
                            <option value="<?= $k ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="si-urut">Urutan</label>
                    <input type="number" id="si-urut" name="urut" x-model="form.urut" min="0" class="inp" placeholder="otomatis">
                </div>
            </div>
            <div>
                <label class="lbl" for="si-ket">Keterangan <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                <input type="text" id="si-ket" name="keterangan" x-model="form.keterangan" maxlength="<?= Supervisi::MAKS_KETERANGAN ?>" class="inp">
            </div>
            <label class="saklar">
                <input type="checkbox" name="aktif" value="1" x-model="form.aktif" class="peer sr-only">
                <span class="saklar-jalur"></span>
                <span class="saklar-label">Aktif (bisa dipilih di Jadwal Supervisi)</span>
            </label>
        <?= ui_crud_form_tutup() ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
