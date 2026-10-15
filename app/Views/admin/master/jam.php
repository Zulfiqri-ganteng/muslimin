<?php
/**
 * Halaman Master Jam Pelajaran — pola halaman data template Flowbite (rombak tampilan F5). Kiri bilah alat = grup tombol shift
 * Pagi/Siang (bukan kotak cari). Baris ISTIRAHAT berlatar kuning muda + lencana "Istirahat". Komponen "jamPage" (master/jam.js).
 *
 * @var string $shift Shift aktif (pagi/siang)
 * @var array  $rows  Slot jam shift aktif
 */
ob_start(); ?>
<div class="grup-tombol" role="group" aria-label="Pilih shift">
    <?php foreach (['pagi' => 'Shift pagi', 'siang' => 'Shift siang'] as $key => $label): ?>
        <a href="<?= site_url('admin/master/jam?shift=' . $key) ?>"<?= $shift === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
</div>
<?php $kiriHtml = (string) ob_get_clean(); ?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jam',
    'helpTitle' => 'Master Jam Pelajaran',
    'helpKelas' => '',
    'helpBody'  => '<p>Slot waktu KBM per <b>shift</b> (Pagi &amp; Siang dikelola terpisah lewat tombol shift di kiri atas). Durasi dihitung otomatis dari selisih waktu mulai–selesai.</p>
        <p class="mt-1">Centang <b>ISTIRAHAT</b> untuk baris jeda — baris ini muncul di jadwal tapi <b>tidak dihitung</b> sebagai jam mengajar.</p>
        <p class="mt-1">• <b>Import</b> — unggah Excel (shift + jam ke yang sama diperbarui). • <b>Export</b> — unduh seluruh jam (kedua shift). • <b>Hapus terpilih / Hapus semua</b> berlaku untuk shift yang sedang tampil.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="jamPage" data-base="<?= site_url('admin/master/jam') ?>" data-entity="jam pelajaran (shift <?= esc($shift, 'attr') ?>)"
     data-defaults="<?= esc(json_encode(['shift' => $shift, 'jam_ke' => 1, 'waktu_mulai' => '', 'waktu_selesai' => '', 'is_istirahat' => false]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/jam'),
        'kiriHtml'         => $kiriHtml,
        'eksporUrl'        => site_url('admin/master/jam/export'),
        'eksporTitle'      => 'Keluarkan seluruh jam pelajaran (kedua shift) ke file Excel',
        'massalUrl'        => site_url('admin/master/jam/bulk-delete'),
        'massalHidden'     => ['shift' => $shift],
        'massalLabel'      => 'jam pelajaran shift ' . $shift,
        'massalPeringatan' => 'Jadwal pada slot tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua jam di shift ini"></th>
                    <th scope="col">Jam ke</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Durasi</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada jam untuk shift ' . $shift, 'teks' => 'Tambah jam satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $istirahat = (bool) $r['is_istirahat']; $waktu = substr($r['waktu_mulai'], 0, 5) . ' – ' . substr($r['waktu_selesai'], 0, 5); ?>
                    <tr<?= $istirahat ? ' class="bg-yellow-50 dark:bg-yellow-500/10"' : '' ?>>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih jam <?= esc($waktu, 'attr') ?>"></td>
                        <td class="sel-judul"><?= $istirahat ? '<span class="sel-ket">—</span>' : esc($r['jam_ke']) ?></td>
                        <td class="whitespace-nowrap font-medium"><?= esc($waktu) ?></td>
                        <td class="sel-ket whitespace-nowrap"><?= esc($r['durasi']) ?> menit</td>
                        <td><?= $istirahat ? '<span class="lencana lencana-kuning">Istirahat</span>' : '<span class="lencana lencana-abu">KBM</span>' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/jam/delete/' . $r['id']),
                                'konfirmasi' => $istirahat ? 'Hapus jam istirahat ' . $waktu . '?' : 'Hapus jam ke-' . $r['jam_ke'] . ' (' . $waktu . ')? Jadwal pada slot tersebut ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'jam pelajaran (shift ' . $shift . ')']) ?>

    <?= ui_crud_form_buka(['benda' => 'Jam Pelajaran']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="jam-shift">Shift</label>
                <select id="jam-shift" name="shift" x-model="form.shift" class="inp">
                    <option value="pagi">Pagi</option>
                    <option value="siang">Siang</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="jam-ke">Jam ke <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="number" id="jam-ke" name="jam_ke" x-model="form.jam_ke" min="1" required class="inp">
            </div>
            <div>
                <label class="lbl" for="jam-mulai">Mulai <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="time" id="jam-mulai" name="waktu_mulai" x-model="form.waktu_mulai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="jam-selesai">Selesai <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="time" id="jam-selesai" name="waktu_selesai" x-model="form.waktu_selesai" required class="inp">
            </div>
        </div>
        <label class="flex items-center">
            <input type="checkbox" name="is_istirahat" value="1" x-model="form.is_istirahat" class="cek">
            <span class="cek-label">Tandai sebagai jam ISTIRAHAT</span>
        </label>
        <p class="hint">Durasi dihitung otomatis dari selisih waktu.</p>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Jam Pelajaran',
        'aksi'        => site_url('admin/master/jam/import-preview'),
        'templateUrl' => site_url('admin/master/jam/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kombinasi shift + jam ke yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/jam.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/jam.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
