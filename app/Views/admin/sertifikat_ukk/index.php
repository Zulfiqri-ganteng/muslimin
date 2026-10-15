<?php
/**
 * Sertifikat kelulusan UKK — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, komponen kit "masterList",
 * ui_crud_alat, tabel-crud, ui_crud_aksi Cetak PDF · Ubah · Hapus, ui_crud_kaki, dialog formulir kit "Terbitkan sertifikat";
 * pilihan peserta hanya tampil & wajib saat menerbitkan). Dulu x-data objek bermetode sebaris + @click berisi JSON → siap CSP.
 *
 * @var array                          $rows        Baris sertifikat halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 * @var int                           $per
 * @var array<int,string>             $pesertaOpts Peserta lulus & belum bersertifikat
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/sertifikat-ukk');
$defaults = ['id' => '', 'peserta_ukk_id' => '', 'tanggal_terbit' => date('Y-m-d'), 'keterangan' => ''];
$total = $pager ? $pager->getTotal() : count($rows);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'sertifikat_ukk',
    'helpTitle' => 'Sertifikat UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Terbitkan sertifikat untuk peserta yang sudah berstatus <b>Lulus</b> (lihat Peserta UKK / hasil
        Penilaian). Nomor dibuat otomatis (format <code>SERT-UKK-{tahun}-001</code>). Satu peserta hanya bisa punya
        satu sertifikat.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= $base ?>" data-entity="sertifikat" data-defaults="<?= esc((string) json_encode($defaults), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => $base,
        'cari'        => 'Cari nomor sertifikat, nama, atau NIS…',
        'q'           => $q,
        'per'         => $per,
        'labelTambah' => 'Terbitkan sertifikat',
        'impor'       => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Nomor</th>
                    <th scope="col">Peserta</th>
                    <th scope="col">Paket soal</th>
                    <th scope="col" class="text-right">Nilai</th>
                    <th scope="col">Terbit</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong(['ikon' => 'award', 'judul' => $q !== '' ? 'Tidak ada sertifikat yang cocok' : 'Belum ada sertifikat',
                                'teks' => $q !== '' ? 'Ubah pencarian, atau tekan Reset.' : 'Klik Terbitkan sertifikat untuk peserta yang sudah lulus.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="font-mono font-semibold whitespace-nowrap text-brand-700 dark:text-brand-400"><?= esc($r['nomor_sertifikat']) ?></td>
                        <td>
                            <p class="sel-judul"><?= esc($r['siswa_nama'] ?? '—') ?></p>
                            <p class="sel-ket"><?= esc($r['nis'] ?? '—') ?></p>
                        </td>
                        <td><?= esc($r['paket_nama'] ?? '—') ?></td>
                        <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= $r['nilai_akhir'] !== null ? number_format((float) $r['nilai_akhir'], 1) : '—' ?></td>
                        <td class="whitespace-nowrap"><?= $tgl($r['tanggal_terbit']) ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => ['id' => $r['id'], 'tanggal_terbit' => $r['tanggal_terbit'], 'keterangan' => $r['keterangan']],
                                'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                                'konfirmasi'   => 'Hapus sertifikat ' . $r['nomor_sertifikat'] . '?',
                                'tambahanHtml' => '<a href="' . $base . '/pdf/' . (int) $r['id'] . '" target="_blank" rel="noopener" class="btn btn-netral btn-sm">' . ikon('printer', 'h-4 w-4') . 'PDF</a>',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'per'], 'satuan' => 'sertifikat']) ?>

    <?= ui_crud_form_buka(['benda' => 'Sertifikat', 'judulTambah' => 'Terbitkan Sertifikat']) ?>
        <div x-show="mode === 'add'">
            <label class="lbl" for="su-peserta">Peserta (lulus, belum bersertifikat) <span class="text-red-600 dark:text-red-400">*</span></label>
            <select id="su-peserta" name="peserta_ukk_id" x-model="form.peserta_ukk_id" :required="mode === 'add'" class="inp">
                <option value="">— Pilih peserta —</option>
                <?php foreach ($pesertaOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($pesertaOpts)): ?>
                <p class="hint">Belum ada peserta berstatus lulus yang belum bersertifikat.</p>
            <?php endif; ?>
        </div>
        <div>
            <label class="lbl" for="su-tanggal">Tanggal terbit <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="date" id="su-tanggal" name="tanggal_terbit" x-model="form.tanggal_terbit" required class="inp">
        </div>
        <div>
            <label class="lbl" for="su-ket">Keterangan</label>
            <input type="text" id="su-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
        </div>
    <?= ui_crud_form_tutup() ?>
</div>
<?= $this->endSection() ?>
