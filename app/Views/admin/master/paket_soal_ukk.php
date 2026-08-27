<?php
/**
 * Halaman Master Paket Soal UKK.
 *
 * @var string                        $q           Kata kunci pencarian
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris paket soal halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data
 * @var array<int,string>             $jurusanOpts Opsi jurusan
 * @var array<int,string>             $tahunOpts   Opsi tahun ajaran
 */
helper('ukkdoc');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'paket_soal_ukk',
    'helpTitle' => 'Master Paket Soal UKK',
    'helpBody'  => '<p>Paket soal Uji Kompetensi Keahlian (UKK), lengkap dengan berkas <b>Kisi-kisi</b> & <b>Jobsheet</b> (PDF, maks 10 MB).</p>
        <p class="mt-1"><b>Bobot 5 komponen penilaian</b> (Persiapan/Proses/Hasil/Sikap/Waktu) harus berjumlah <b>100%</b> — dipakai otomatis saat menghitung <b>Nilai Akhir</b> di menu Penilaian. <b>KKM</b> menentukan batas kelulusan peserta.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/paket-soal-ukk') ?>"
     data-entity="paket_soal_ukk"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'jurusan_id' => '', 'tahun_ajaran_id' => '',
         'deskripsi' => '', 'bobot_persiapan' => 10, 'bobot_proses' => 30, 'bobot_hasil' => 40,
         'bobot_sikap' => 10, 'bobot_waktu' => 10, 'kkm' => 70, 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/paket-soal-ukk'),
        'searchPlaceholder' => 'Cari kode atau nama paket soal...',
        'q'                 => $q,
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/paket-soal-ukk/export'),
        'exportTitle'       => 'Keluarkan semua paket soal ke file Excel',
        'bulkUrl'           => site_url('admin/master/paket-soal-ukk/bulk-delete'),
        'bulkLabel'         => 'paket soal',
    ]) ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Paket Soal <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-24">Kode</th>
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold w-32">Jurusan</th>
                        <th class="px-4 py-3 font-semibold w-16 text-center">KKM</th>
                        <th class="px-4 py-3 font-semibold w-40">Dokumen</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' ? 'Tidak ada paket soal yang cocok dengan pencarian ini.' : 'Belum ada data paket soal. Tambah manual atau import Excel.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['nama']) ?></div>
                                <div class="text-xs text-slate-400">Bobot: <?= (float) $r['bobot_persiapan'] ?>/<?= (float) $r['bobot_proses'] ?>/<?= (float) $r['bobot_hasil'] ?>/<?= (float) $r['bobot_sikap'] ?>/<?= (float) $r['bobot_waktu'] ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['jurusan_nama'] !== null ? esc($r['jurusan_nama']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= (float) $r['kkm'] ?></td>
                            <td class="px-4 py-3 text-xs space-y-1">
                                <?php if (! empty($r['kisi_kisi_file'])): ?>
                                    <div><a href="<?= esc(ukkdoc_url($r['kisi_kisi_file'])) ?>" target="_blank" class="text-brand-700 hover:underline font-medium">Kisi-kisi</a>
                                        <a href="<?= site_url('admin/master/paket-soal-ukk/hapus-berkas/' . $r['id'] . '/kisi-kisi') ?>" data-confirm="Hapus berkas kisi-kisi ini?" class="text-red-500 hover:underline ml-1">hapus</a></div>
                                <?php else: ?>
                                    <div class="text-slate-300">Kisi-kisi: —</div>
                                <?php endif; ?>
                                <?php if (! empty($r['jobsheet_file'])): ?>
                                    <div><a href="<?= esc(ukkdoc_url($r['jobsheet_file'])) ?>" target="_blank" class="text-brand-700 hover:underline font-medium">Jobsheet</a>
                                        <a href="<?= site_url('admin/master/paket-soal-ukk/hapus-berkas/' . $r['id'] . '/jobsheet') ?>" data-confirm="Hapus berkas jobsheet ini?" class="text-red-500 hover:underline ml-1">hapus</a></div>
                                <?php else: ?>
                                    <div class="text-slate-300">Jobsheet: —</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/paket-soal-ukk/delete/' . $r['id']),
                                    'confirm'   => 'Hapus paket soal ini? Berkas kisi-kisi/jobsheet ikut terhapus.',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Paket Soal' : 'Edit Paket Soal'"></h3>
            <form method="post" :action="actionUrl" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode *</label>
                        <input type="text" name="kode" x-model="form.kode" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">KKM</label>
                        <input type="number" name="kkm" x-model="form.kkm" min="0" max="100" step="0.5"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jurusan</label>
                        <select name="jurusan_id" x-model="form.jurusan_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Semua jurusan —</option>
                            <?php foreach ($jurusanOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Ajaran</label>
                        <select name="tahun_ajaran_id" x-model="form.tahun_ajaran_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Belum ditentukan —</option>
                            <?php foreach ($tahunOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                        <input type="text" name="deskripsi" x-model="form.deskripsi" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-slate-100">
                        <p class="text-sm font-semibold text-slate-700 mb-2">Bobot Komponen Penilaian (%) <span class="text-slate-400 font-normal">— total harus 100</span></p>
                        <div class="grid grid-cols-5 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Persiapan</label>
                                <input type="number" name="bobot_persiapan" x-model="form.bobot_persiapan" min="0" max="100" step="0.5"
                                       class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Proses</label>
                                <input type="number" name="bobot_proses" x-model="form.bobot_proses" min="0" max="100" step="0.5"
                                       class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Hasil</label>
                                <input type="number" name="bobot_hasil" x-model="form.bobot_hasil" min="0" max="100" step="0.5"
                                       class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Sikap</label>
                                <input type="number" name="bobot_sikap" x-model="form.bobot_sikap" min="0" max="100" step="0.5"
                                       class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Waktu</label>
                                <input type="number" name="bobot_waktu" x-model="form.bobot_waktu" min="0" max="100" step="0.5"
                                       class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-slate-100">
                        <p class="text-sm font-semibold text-slate-700 mb-2">Dokumen (PDF, maks 10 MB)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Kisi-kisi</label>
                                <input type="file" name="kisi_kisi" accept="application/pdf"
                                       class="w-full text-sm rounded-lg border border-slate-300 px-2 py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                                <p class="text-xs text-slate-400 mt-1">Kosongkan bila tidak ingin mengganti berkas yang sudah ada.</p>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Jobsheet</label>
                                <input type="file" name="jobsheet" accept="application/pdf"
                                       class="w-full text-sm rounded-lg border border-slate-300 px-2 py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                                <p class="text-xs text-slate-400 mt-1">Kosongkan bila tidak ingin mengganti berkas yang sudah ada.</p>
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Paket Soal UKK',
        'importAction' => site_url('admin/master/paket-soal-ukk/import-preview'),
        'templateUrl'  => site_url('admin/master/paket-soal-ukk/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Bobot komponen & KKM diisi default (10/30/40/10/10, KKM 70) hanya untuk paket BARU — kode yang sudah ada tidak diubah bobotnya. Dokumen kisi-kisi/jobsheet tetap diunggah manual lewat form.',
    ]) ?>
</div>

<?= $this->endSection() ?>
