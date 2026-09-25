<?php
/**
 * Halaman Master Siswa — pola tampilan mengikuti Master Guru/Jabatan.
 *
 * @var string                        $q           Kata kunci pencarian
 * @var int                           $kelasId     Filter kelas aktif
 * @var string                        $tingkat     Filter tingkat aktif
 * @var string                        $status      Filter status aktif
 * @var string                        $biodata     Filter kelengkapan biodata (lengkap|belum|'')
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris siswa halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data (sesuai filter)
 * @var array<int,string>             $kelasOpts   Opsi kelas
 * @var array                         $tingkatList Daftar tingkat sah
 * @var array                         $statusList  Daftar status sah
 */
$warnaStatus = [
    'aktif'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'lulus'  => 'bg-blue-50 text-blue-700 border-blue-200',
    'pindah' => 'bg-amber-50 text-amber-700 border-amber-200',
    'keluar' => 'bg-slate-100 text-slate-500 border-slate-200',
];
$opsiStatus  = array_combine($statusList, array_map('ucfirst', $statusList));
$opsiTingkat = array_combine($tingkatList, array_map(static fn ($t) => 'Kelas ' . $t, $tingkatList));
$opsiBiodata = ['lengkap' => 'Biodata lengkap', 'belum' => 'Biodata belum'];

// URL export ikut membawa filter yang sedang aktif.
$qsExport = array_filter([
    'kelas_id' => $kelasId ?: '',
    'tingkat'  => $tingkat,
    'status'   => $status,
    'biodata'  => $biodata,
], static fn ($v) => $v !== '');

$label  = \App\Libraries\BiodataForm::LABEL;
$kelasInput = 'w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none';

/**
 * Satu kolom isian modal (gaya sama dengan kolom lain di form ini).
 * $o: type, max, span (kelas grid), list (id datalist), mode (inputmode).
 */
$kolom = static function (string $nama, string $judul, array $o = []) use ($kelasInput): void {
    echo '<div class="' . ($o['span'] ?? '') . '">'
        . '<label class="block text-sm font-medium text-slate-600 mb-1">' . esc($judul) . '</label>'
        . '<input type="' . ($o['type'] ?? 'text') . '" name="' . $nama . '" x-model="form.' . $nama . '"'
        . (isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '')
        . (isset($o['list']) ? ' list="' . $o['list'] . '"' : '')
        . (isset($o['mode']) ? ' inputmode="' . $o['mode'] . '"' : '')
        . (($o['type'] ?? '') === 'number' ? ' min="1" max="99"' : '')
        . ' class="' . $kelasInput . '"></div>';
};

/** Blok alamat terstruktur (siswa: $p = '', orang tua: $p = 'ortu_'). */
$blokAlamat = static function (string $p) use ($kolom, $label): void {
    $kolom($p . 'rt', $label[$p . 'rt'], ['max' => 5, 'mode' => 'numeric']);
    $kolom($p . 'rw', $label[$p . 'rw'], ['max' => 5, 'mode' => 'numeric']);
    $kolom($p . 'kelurahan', $label[$p . 'kelurahan'], ['max' => 100]);
    $kolom($p . 'kecamatan', $label[$p . 'kecamatan'], ['max' => 100]);
    $kolom($p . 'kota', $label[$p . 'kota'], ['max' => 100, 'span' => 'sm:col-span-2']);
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'siswa_v3',
    'helpTitle' => 'Master Siswa',
    'helpBody'  => '<p>Data siswa lengkap sesuai buku induk: identitas, alamat, sekolah asal, orang tua, wali, dan kelas. Tingkat dan jurusan <b>mengikuti kelas</b> siswa, jadi cukup pilih kelasnya saja.</p>
        <p class="mt-1">• <b>Biodata dari siswa</b> — siswa bisa mengisi biodatanya sendiri lewat menu <b>Isian Biodata Siswa</b>. Setelah admin menyetujui isiannya, datanya otomatis masuk ke sini dan siswa ditandai <span class="font-semibold text-emerald-700">Biodata ✓</span>. Pakai saringan <b>Biodata lengkap / belum</b> untuk melihat siapa yang belum.<br>
        • Data yang sudah masuk tetap bisa <b>diubah manual</b> lewat tombol edit.</p>
        <p class="mt-1">• <b>Import</b> — unduh template, isi di Excel, unggah, periksa di pratinjau, simpan. NIS yang sudah ada akan <b>diperbarui</b>, bukan diduplikat. <b>Sel yang dikosongkan tidak mengubah data lama</b>, jadi aman mengimpor ulang daftar yang hanya berisi NIS, nama, dan kelas.<br>
        • Kolom <b>Kelas</b> pada file impor diisi <b>nama kelas</b> persis seperti di Master Kelas (mis. <i>X TKJ 1</i>).<br>
        • <b>Penting:</b> NIS bisa berganti ke NIS asli setelah biodata disetujui. Untuk impor ulang, pakai file hasil <b>Export terbaru</b>, bukan file lama — NIS lama akan dianggap siswa baru.<br>
        • <b>Filter</b> — memilih kelas/tingkat/status/biodata langsung menyaring tabel (tanpa klik Cari).<br>
        • <b>Export</b> — mengunduh sesuai pilihan filter (pilih satu kelas = hanya kelas itu), berisi seluruh kolom biodata. Bila isinya lebih dari satu kelas, file Excel berisi tab <b>Semua Kelas</b> + <b>satu tab per kelas</b> (klik nama kelas di bawah layar Excel).</p>
        <p class="mt-1">• Status <b>Aktif</b> yang dihitung pada grafik jumlah siswa di halaman publik. Siswa lulus/pindah/keluar tetap tersimpan sebagai arsip.</p>',
]) ?>

<div x-data="siswaPage"
     data-base="<?= site_url('admin/master/siswa') ?>"
     data-entity="siswa"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'nis' => '', 'nisn' => '', 'nama' => '', 'jenis_kelamin' => '',
         'tempat_lahir' => '', 'tanggal_lahir' => '', 'agama' => '', 'alamat' => '',
         'no_hp' => '', 'nama_wali' => '', 'no_hp_wali' => '', 'kelas_id' => '',
         'tahun_masuk' => date('Y'), 'status' => 'aktif', 'keterangan' => '',
         // Biodata buku induk
         'status_keluarga' => '', 'anak_ke' => '', 'rt' => '', 'rw' => '', 'kelurahan' => '',
         'kecamatan' => '', 'kota' => '', 'sekolah_asal' => '', 'diterima_kelas' => '',
         'diterima_tanggal' => '', 'nama_ayah' => '', 'pekerjaan_ayah' => '', 'nama_ibu' => '',
         'pekerjaan_ibu' => '', 'ortu_alamat' => '', 'ortu_rt' => '', 'ortu_rw' => '',
         'ortu_kelurahan' => '', 'ortu_kecamatan' => '', 'ortu_kota' => '', 'ortu_telepon' => '',
         'alamat_wali' => '', 'pekerjaan_wali' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/siswa'),
        'searchPlaceholder' => 'Cari nama, NIS, atau NISN...',
        'q'                 => $q,
        'per'               => $per,
        'filters'           => [
            ['name' => 'kelas_id', 'value' => (string) ($kelasId ?: ''), 'all' => 'Semua kelas',   'options' => $kelasOpts],
            ['name' => 'tingkat',  'value' => $tingkat,                  'all' => 'Semua tingkat', 'options' => $opsiTingkat],
            ['name' => 'status',   'value' => $status,                   'all' => 'Semua status',  'options' => $opsiStatus],
            ['name' => 'biodata',  'value' => $biodata,                  'all' => 'Semua biodata', 'options' => $opsiBiodata],
        ],
        'exportUrl'   => site_url('admin/master/siswa/export') . ($qsExport !== [] ? '?' . http_build_query($qsExport) : ''),
        'exportTitle' => 'Unduh data siswa sesuai filter yang dipilih (mis. satu kelas saja) ke file Excel',
        'filterLangsung' => true,
        'bulkUrl'     => site_url('admin/master/siswa/bulk-delete'),
        'bulkLabel'   => 'siswa',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Siswa <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-32">NIS</th>
                        <th class="px-4 py-3 font-semibold">Nama Siswa</th>
                        <th class="px-4 py-3 font-semibold w-14 text-center">JK</th>
                        <th class="px-4 py-3 font-semibold w-32">Kelas</th>
                        <th class="px-4 py-3 font-semibold w-24">Jurusan</th>
                        <th class="px-4 py-3 font-semibold w-24">Status</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $kelasId || $tingkat !== '' || $status !== '' || $biodata !== ''
                                ? 'Tidak ada siswa yang cocok dengan filter ini.'
                                : 'Belum ada data siswa. Tambah manual atau import Excel (lebih cepat untuk satu kelas sekaligus).' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-semibold text-brand-700"><?= esc($r['nis']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['nama']) ?></div>
                                <?php if (! empty($r['nisn']) || ! empty($r['biodata_at'])): ?>
                                    <div class="text-xs text-slate-400">
                                        <?php if (! empty($r['nisn'])): ?>NISN <?= esc($r['nisn']) ?><?php endif; ?>
                                        <?php if (! empty($r['biodata_at'])): ?>
                                            <span class="ml-1 font-semibold text-emerald-700" title="Biodata disahkan <?= esc(date('d/m/Y H:i', strtotime($r['biodata_at'])), 'attr') ?>">Biodata ✓</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= $r['jenis_kelamin'] !== null ? esc($r['jenis_kelamin']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= $r['nama_kelas'] !== null ? esc($r['nama_kelas']) : '<span class="text-slate-300">Belum ada kelas</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['jurusan_kode'] !== null ? esc($r['jurusan_kode']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst($r['status'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/siswa/delete/' . $r['id']),
                                    'confirm'   => 'Hapus data siswa ini?',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'kelas_id', 'tingkat', 'status', 'biodata', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Siswa' : 'Edit Siswa'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Identitas</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NIS *</label>
                        <input type="text" name="nis" x-model="form.nis" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NISN</label>
                        <input type="text" name="nisn" x-model="form.nisn" maxlength="30"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" x-model="form.jenis_kelamin"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih —</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Agama</label>
                        <input type="text" name="agama" x-model="form.agama" maxlength="25" list="daftar-agama"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <datalist id="daftar-agama">
                            <?php foreach (\App\Models\SiswaModel::AGAMA as $a): ?>
                                <option value="<?= $a ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" x-model="form.tempat_lahir" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" x-model="form.tanggal_lahir"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1"><?= esc($label['status_keluarga']) ?></label>
                        <select name="status_keluarga" x-model="form.status_keluarga" class="<?= $kelasInput ?>">
                            <option value="">— Pilih —</option>
                            <?php foreach (\App\Models\SiswaModel::STATUS_KELUARGA as $sk): ?>
                                <option value="<?= esc($sk, 'attr') ?>"><?= esc($sk) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php $kolom('anak_ke', $label['anak_ke'], ['type' => 'number']) ?>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Alamat &amp; Kontak Siswa</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php $kolom('alamat', 'Alamat (jalan / perumahan, blok, nomor)', ['max' => 255, 'span' => 'sm:col-span-2']) ?>
                    <?php $blokAlamat('') ?>
                    <?php $kolom('no_hp', 'No HP Siswa', ['max' => 25, 'mode' => 'tel']) ?>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Riwayat Masuk</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php $kolom('sekolah_asal', $label['sekolah_asal'], ['max' => 150, 'span' => 'sm:col-span-2']) ?>
                    <?php $kolom('diterima_kelas', $label['diterima_kelas'], ['max' => 50, 'list' => 'daftar-kelas-siswa']) ?>
                    <?php $kolom('diterima_tanggal', $label['diterima_tanggal'], ['type' => 'date']) ?>
                    <datalist id="daftar-kelas-siswa">
                        <?php foreach ($kelasOpts as $namaKelas): ?><option value="<?= esc($namaKelas, 'attr') ?>"></option><?php endforeach; ?>
                    </datalist>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Orang Tua</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php $kolom('nama_ayah', $label['nama_ayah'], ['max' => 150]) ?>
                    <?php $kolom('pekerjaan_ayah', $label['pekerjaan_ayah'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
                    <?php $kolom('nama_ibu', $label['nama_ibu'], ['max' => 150]) ?>
                    <?php $kolom('pekerjaan_ibu', $label['pekerjaan_ibu'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
                    <?php $kolom('ortu_alamat', $label['ortu_alamat'], ['max' => 255, 'span' => 'sm:col-span-2']) ?>
                    <?php $blokAlamat('ortu_') ?>
                    <?php $kolom('ortu_telepon', $label['ortu_telepon'] . ' (bisa 2 nomor, pisahkan dengan /)', ['max' => 60, 'mode' => 'tel', 'span' => 'sm:col-span-2']) ?>
                    <datalist id="daftar-pekerjaan">
                        <?php foreach (\App\Models\SiswaModel::PEKERJAAN as $pk): ?><option value="<?= esc($pk, 'attr') ?>"></option><?php endforeach; ?>
                    </datalist>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Wali <span class="normal-case font-normal">(kosongkan bila tidak ada wali selain orang tua)</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php $kolom('nama_wali', $label['nama_wali'], ['max' => 150]) ?>
                    <?php $kolom('no_hp_wali', 'No HP Wali', ['max' => 25, 'mode' => 'tel']) ?>
                    <?php $kolom('alamat_wali', $label['alamat_wali'], ['max' => 255, 'span' => 'sm:col-span-2']) ?>
                    <?php $kolom('pekerjaan_wali', $label['pekerjaan_wali'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Kelas &amp; Status</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kelas</label>
                        <select name="kelas_id" x-model="form.kelas_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Belum ada kelas —</option>
                            <?php foreach ($kelasOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Tingkat &amp; jurusan mengikuti kelas.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Masuk</label>
                        <input type="number" name="tahun_masuk" x-model="form.tahun_masuk" min="1990" max="2100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status" x-model="form.status"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiStatus as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
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
        'importTitle'  => 'Import Siswa',
        'importAction' => site_url('admin/master/siswa/import-preview'),
        'templateUrl'  => site_url('admin/master/siswa/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template — cocok untuk memasukkan satu kelas (mis. 50 siswa) sekaligus. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. <b>NIS</b> yang sudah ada akan diperbarui, bukan diduplikat — dan <b>sel yang kosong tidak mengubah data lama</b> (biodata yang sudah diisi siswa aman). Kolom <b>Kelas</b> diisi nama kelas persis seperti di Master Kelas. Kolom biodata (orang tua, wali, dsb.) ada di <b>bagian kanan</b> template.',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/siswa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/siswa.js') ?>"></script>
<?= $this->endSection() ?>
