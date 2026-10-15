<?php
/**
 * Berita Acara pelaksanaan UKK — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, komponen kit "masterList",
 * ui_crud_alat, tabel-crud, ui_crud_aksi Cetak PDF · Ubah · Hapus, ui_crud_kaki, dialog formulir kit "Buat berita acara";
 * pilihan jadwal hanya tampil & wajib saat membuat). Dulu x-data objek bermetode sebaris + @click berisi JSON → siap CSP.
 *
 * @var array                          $rows       Baris berita acara halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 * @var int                           $per
 * @var array<int,string>             $jadwalOpts Opsi jadwal UKK (untuk Tambah)
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/berita-acara-ukk');
$defaults = ['id' => '', 'jadwal_ukk_id' => '', 'tanggal' => date('Y-m-d'), 'catatan' => '', 'keterangan' => ''];
$total = $pager ? $pager->getTotal() : count($rows);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'berita_acara_ukk',
    'helpTitle' => 'Berita Acara UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Buat berita acara resmi pelaksanaan UKK untuk satu jadwal. Nomor dibuat otomatis (format
        <code>BA-UKK-{tahun}-001</code>). Klik <b>PDF</b> untuk dokumen siap tanda tangan berisi daftar
        peserta + penguji.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= $base ?>" data-entity="berita acara" data-defaults="<?= esc((string) json_encode($defaults), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => $base,
        'cari'        => 'Cari nomor BA atau paket soal…',
        'q'           => $q,
        'per'         => $per,
        'labelTambah' => 'Buat berita acara',
        'impor'       => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Nomor</th>
                    <th scope="col">Paket soal</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4">
                            <?= ui_kosong(['ikon' => 'file-lines', 'judul' => $q !== '' ? 'Tidak ada berita acara yang cocok' : 'Belum ada berita acara',
                                'teks' => $q !== '' ? 'Ubah pencarian, atau tekan Reset.' : 'Klik Buat berita acara untuk memulai.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-mono font-semibold whitespace-nowrap text-brand-700 dark:text-brand-400"><?= esc($r['nomor_ba']) ?></td>
                        <td>
                            <p class="sel-judul"><?= esc($r['paket_nama'] ?? '—') ?></p>
                            <p class="sel-ket"><?= esc($r['tempat_nama'] ?? '—') ?></p>
                        </td>
                        <td class="whitespace-nowrap"><?= $tgl($r['tanggal']) ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => ['id' => $r['id'], 'tanggal' => $r['tanggal'], 'catatan' => $r['catatan'], 'keterangan' => $r['keterangan']],
                                'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                                'konfirmasi'   => 'Hapus berita acara ' . $r['nomor_ba'] . '?',
                                'tambahanHtml' => '<a href="' . $base . '/pdf/' . (int) $r['id'] . '" target="_blank" rel="noopener" class="btn btn-netral btn-sm">' . ikon('printer', 'h-4 w-4') . 'PDF</a>',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'per'], 'satuan' => 'berita acara']) ?>

    <?= ui_crud_form_buka(['benda' => 'Berita Acara', 'judulTambah' => 'Buat Berita Acara']) ?>
        <div x-show="mode === 'add'">
            <label class="lbl" for="ba-jadwal">Jadwal UKK <span class="text-red-600">*</span></label>
            <select id="ba-jadwal" name="jadwal_ukk_id" x-model="form.jadwal_ukk_id" :required="mode === 'add'" class="inp">
                <option value="">— Pilih jadwal —</option>
                <?php foreach ($jadwalOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($jadwalOpts)): ?>
                <p class="hint">Belum ada jadwal UKK. Buat dulu di menu Jadwal UKK.</p>
            <?php endif; ?>
        </div>
        <div>
            <label class="lbl" for="ba-tanggal">Tanggal <span class="text-red-600">*</span></label>
            <input type="date" id="ba-tanggal" name="tanggal" x-model="form.tanggal" required class="inp">
        </div>
        <div>
            <label class="lbl" for="ba-catatan">Catatan pelaksanaan</label>
            <textarea id="ba-catatan" name="catatan" x-model="form.catatan" rows="3" class="inp"></textarea>
        </div>
        <div>
            <label class="lbl" for="ba-ket">Keterangan</label>
            <input type="text" id="ba-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
        </div>
    <?= ui_crud_form_tutup() ?>
</div>
<?= $this->endSection() ?>
