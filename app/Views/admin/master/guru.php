<?php
/**
 * Halaman Master Guru — pola halaman data template Flowbite (rombak tampilan F5). Komponen "guruPage" (master/guru.js) = masterList
 * + dialog "Atur jabatan" (boleh beberapa jabatan, satu utama bertanda ikon bintang; struktural = lencana kuning). Siap Alpine versi CSP.
 *
 * @var string                         $q          Kata kunci pencarian
 * @var string                         $status     Filter status kepegawaian
 * @var int                            $per        Baris per halaman
 * @var array                          $rows       Baris guru halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager      Paginasi
 * @var int                            $total      Total seluruh data
 * @var string[]                       $statusList Pilihan status kepegawaian
 * @var array                          $jabatanMap guru_id => daftar jabatan
 * @var array                          $allJabatan Pilihan jabatan untuk dialog
 * @var array<int, string>             $guruOpsi   Opsi guru (untuk "Data ganda dari")
 */
$adaSaring = $q !== '' || $status !== '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'guru_v2',
    'helpTitle' => 'Master Guru',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar semua guru pengajar. Tambah satu per satu, atau gunakan tombol di kanan atas:</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah file Excel, datanya akan <b>ditambahkan otomatis</b> ke daftar (kode guru yang sama akan diperbarui). Unduh template dulu agar kolomnya sesuai.<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh data guru yang ada saat ini menjadi file Excel.<br>
        • <b>Hapus terpilih / Hapus semua</b> — centang baris untuk menghapus sebagian, atau hapus seluruh data sekaligus.</p>
        <p class="mt-1"><b>Maks beban</b> = batas jam mengajar (JP) per minggu untuk guru itu. Kompetensi guru diatur di menu <b>Mata Pelajaran ▸ Atur guru</b>.</p>
        <p class="mt-1"><b>Kolom Jabatan.</b> Tombol <b>Atur jabatan</b> di tiap baris menentukan jabatan yang disandang guru. Satu guru <b>boleh menyandang beberapa jabatan</b> sekaligus (mis. <i>Guru Mata Pelajaran</i> sekaligus <i>Wakasek Kurikulum</i>) — centang semua yang sesuai, lalu pilih salah satu sebagai <b>utama</b> lewat tombol bulat di kanannya. Jabatan utama (bertanda ikon bintang) yang tampil lebih dulu di daftar, rekap, dan file Export.</p>
        <p class="mt-1">Jabatan bertanda <b>struktural</b> (lencana kuning) punya efek nyata: penyandangnya <b>otomatis diisikan</b> ke panel <b>Kehadiran Kerja</b> di halaman <b>Absensi Guru</b> — termasuk Sabtu/Minggu saat tidak ada jadwal KBM sama sekali — sehingga wakil kepala yang masuk kerja tetap bisa diabsen dan terhitung di rekap.</p>
        <p class="mt-1">Daftar jabatannya sendiri dibuat &amp; diubah di menu <b>Master Data ▸ Jabatan</b>. Menghapus guru otomatis melepas jabatannya (jabatannya sendiri tidak ikut terhapus).</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="guruPage" data-base="<?= site_url('admin/master/guru') ?>" data-entity="guru"
     data-all-jabatan="<?= esc(json_encode($allJabatan), 'attr') ?>"
     data-jabatan-map="<?= esc(json_encode($jabatanMap), 'attr') ?>"
     data-defaults="<?= esc(json_encode(['kode_guru' => '', 'nip' => '', 'nama' => '', 'no_wa' => '', 'ikut_absensi' => true, 'bukan_pengajar' => false, 'induk_id' => '', 'jenis_kelamin' => '', 'status_guru' => '', 'max_beban' => 24, 'keterangan' => '']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/guru'),
        'cari'             => 'Cari nama, kode, atau NIP…',
        'q'                => $q,
        'filter'           => [['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => array_combine($statusList, $statusList)]],
        'per'              => $per,
        'eksporUrl'        => site_url('admin/master/guru/export'),
        'eksporTitle'      => 'Keluarkan semua data guru ke file Excel',
        'massalUrl'        => site_url('admin/master/guru/bulk-delete'),
        'massalLabel'      => 'data guru',
        'massalPeringatan' => 'Penugasan, jadwal, dan data terkait guru tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua guru di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama guru</th>
                    <th scope="col">NIP</th>
                    <th scope="col">JK</th>
                    <th scope="col">Status</th>
                    <th scope="col">Jabatan</th>
                    <th scope="col">Maks JP</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="9">
                            <?= ui_kosong(['ikon' => 'users', 'judul' => $adaSaring ? 'Guru tidak ditemukan' : 'Belum ada data guru',
                                'teks' => $adaSaring ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Tambah guru satu per satu, Import Excel, atau impor dari Data Kesediaan (di dialog Import).']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $jbt = $jabatanMap[(int) $r['id']] ?? []; ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih guru <?= esc($r['kode_guru'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode_guru']) ?></td>
                        <td class="min-w-48">
                            <div class="font-medium"><?= esc($r['nama']) ?></div>
                            <div class="text-xs <?= ! empty($r['no_wa']) ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= ! empty($r['no_wa']) ? 'WA ' . esc($r['no_wa']) : 'Belum ada No. WA' ?></div>
                            <?php if (! empty($r['induk_id'])): ?>
                                <div class="text-xs font-semibold text-yellow-700 dark:text-yellow-400">Data ganda dari <?= esc($guruOpsi[$r['induk_id']] ?? ('#' . $r['induk_id'])) ?></div>
                            <?php endif; ?>
                            <?php if (isset($r['ikut_absensi']) && (int) $r['ikut_absensi'] === 0): ?>
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tidak ikut absensi</div>
                            <?php endif; ?>
                            <?php if (! empty($r['bukan_pengajar'])): ?>
                                <div class="text-xs font-semibold text-purple-700 dark:text-purple-400">Bukan pengajar (staf)</div>
                            <?php endif; ?>
                        </td>
                        <td class="sel-ket whitespace-nowrap"><?= esc($r['nip'] ?: '—') ?></td>
                        <td><?= esc($r['jenis_kelamin'] ?: '—') ?></td>
                        <td><?= $r['status_guru'] ? '<span class="lencana lencana-abu">' . esc($r['status_guru']) . '</span>' : '—' ?></td>
                        <td class="min-w-56">
                            <div class="flex flex-wrap items-center gap-1">
                                <?php foreach ($jbt as $j): ?>
                                    <span class="lencana <?= $j['is_struktural'] ? 'lencana-kuning' : 'lencana-abu' ?>"
                                          title="<?= $j['is_utama'] ? 'Jabatan utama' : 'Jabatan tambahan' ?><?= $j['is_struktural'] ? ' • struktural (wajib hadir walau tanpa jadwal)' : '' ?>">
                                        <?php if ($j['is_utama']): ?><?= ikon('star', 'h-3 w-3', 'solid') ?><?php endif; ?><?= esc($j['nama']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <button type="button" class="btn btn-netral btn-xs" data-atur-jabatan data-id="<?= (int) $r['id'] ?>" data-nama="<?= esc($r['nama'], 'attr') ?>" title="Atur jabatan guru ini">
                                    <?= ikon($jbt === [] ? 'plus' : 'edit', 'h-3.5 w-3.5') ?><?= $jbt === [] ? 'Atur jabatan' : 'Ubah' ?>
                                </button>
                            </div>
                        </td>
                        <td class="sel-ket whitespace-nowrap"><?= esc($r['max_beban']) ?> JP</td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/guru/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus guru ' . $r['nama'] . '? Penugasan, jadwal, dan data terkait guru ini ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'status', 'per'], 'satuan' => 'guru']) ?>

    <?= ui_crud_form_buka(['benda' => 'Guru', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="guru-kode">Kode guru <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="guru-kode" name="kode_guru" x-model="form.kode_guru" maxlength="20" required class="inp">
            </div>
            <div>
                <label class="lbl" for="guru-nip">NIP</label>
                <input type="text" id="guru-nip" name="nip" x-model="form.nip" maxlength="60" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="guru-nama">Nama guru <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="guru-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="guru-jk">Jenis kelamin</label>
                <select id="guru-jk" name="jenis_kelamin" x-model="form.jenis_kelamin" class="inp">
                    <option value="">—</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="guru-status">Status</label>
                <select id="guru-status" name="status_guru" x-model="form.status_guru" class="inp">
                    <option value="">—</option>
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="guru-beban">Maks beban (JP/minggu)</label>
                <input type="number" id="guru-beban" name="max_beban" x-model="form.max_beban" min="0" max="50" class="inp">
            </div>
            <div>
                <label class="lbl" for="guru-ket">Keterangan</label>
                <input type="text" id="guru-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="guru-wa">No. WhatsApp</label>
                <input type="tel" id="guru-wa" name="no_wa" x-model="form.no_wa" maxlength="20" placeholder="cth: 081234567890" class="inp">
                <p class="hint mt-1">Dipakai untuk tag otomatis (@nomor) saat laporan absensi dikirim ke grup WhatsApp.</p>
            </div>
            <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:col-span-2 dark:border-gray-600 dark:bg-gray-800">
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="ikut_absensi" value="0">
                    <input type="checkbox" name="ikut_absensi" value="1" x-model="form.ikut_absensi" class="cek mt-0.5">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Ikut absensi &amp; laporan</span>
                        <span class="hint block">Matikan untuk ketua yayasan / kepala sekolah yang tidak diabsen. Tidak muncul di absensi, pesan WA, maupun rekap.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="bukan_pengajar" value="0">
                    <input type="checkbox" name="bukan_pengajar" value="1" x-model="form.bukan_pengajar" class="cek mt-0.5">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Bukan pengajar (staf TU / operator)</span>
                        <span class="hint block">Tetap tercatat di sini (dan bisa menerima honor), tetapi tidak dihitung sebagai guru di Dashboard maupun &quot;Guru Kurang Jam&quot;.</span>
                    </span>
                </label>
                <div>
                    <label class="lbl" for="guru-induk">Data ganda dari</label>
                    <select id="guru-induk" name="induk_id" x-model="form.induk_id" class="inp">
                        <option value="">— bukan data ganda —</option>
                        <?php foreach ($guruOpsi as $gid => $label): ?>
                            <option value="<?= (int) $gid ?>" x-show="bukanDiriSendiri(<?= (int) $gid ?>)"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint mt-1">Isi bila guru yang sama dibuat dua kali (mis. untuk kelas gabungan). Absensi, pesan WA &amp; rekap dihitung sebagai satu orang.</p>
                </div>
            </div>
        </div>
        <p class="hint">Mata pelajaran yang diampu (kompetensi) diatur pada menu Mata Pelajaran / Pengampu.</p>
    <?= ui_crud_form_tutup() ?>

    <!-- Dialog "Atur jabatan" — tampilan dialog kit, keadaan jabatanOpen milik guruPage -->
    <div class="dialog" :data-buka="jabatanOpen" @keydown.escape.stop="tutupJabatan()" role="dialog" aria-modal="true" aria-labelledby="dialog-jabatan-judul">
        <div class="dialog-latar" @click="tutupJabatan()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-lg flex max-h-[85vh] flex-col" tabindex="-1">
            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="dialog-jabatan-judul" class="dialog-judul">Jabatan guru</h3>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="jabatanGuru"></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutupJabatan()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" :action="jabatanUrl" class="flex min-h-0 flex-1 flex-col">
                <?= csrf_field() ?>
                <div class="dialog-isi min-h-0 flex-1 overflow-y-auto">
                    <template x-if="allJabatan.length === 0">
                        <p class="py-6 text-center text-sm text-gray-500">Belum ada data jabatan. Tambahkan di menu <b>Master Data ▸ Jabatan</b> terlebih dahulu.</p>
                    </template>
                    <div class="space-y-1">
                        <template x-for="j in allJabatan" :key="j.id">
                            <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-600">
                                <label class="flex min-w-0 flex-1 cursor-pointer items-center">
                                    <input type="checkbox" name="jabatan_ids[]" :value="j.id" x-model.number="selectedJabatan" @change="rapikanUtama()" class="cek">
                                    <span class="cek-label truncate" x-text="j.label"></span>
                                    <span x-show="j.is_struktural" class="lencana lencana-kuning ms-2 shrink-0">struktural</span>
                                </label>
                                <!-- Penanda utama hanya bisa dipilih dari jabatan yang dicentang -->
                                <label class="flex shrink-0 items-center gap-1 text-xs" :class="dipilih(j.id) ? 'cursor-pointer text-gray-600 dark:text-gray-300' : 'cursor-not-allowed text-gray-300 dark:text-gray-500'">
                                    <input type="radio" name="utama_id" :value="j.id" x-model.number="utamaId" :disabled="!dipilih(j.id)" class="cek">
                                    utama
                                </label>
                            </div>
                        </template>
                    </div>
                    <p class="hint">Jabatan <b>utama</b> yang ditampilkan lebih dulu pada daftar dan laporan. Bila tidak dipilih, jabatan pertama dipakai otomatis.</p>
                </div>
                <div class="dialog-kaki justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white" x-text="selectedJabatan.length"></b> jabatan dipilih</span>
                    <div class="flex gap-3">
                        <button type="button" class="btn btn-netral" @click="tutupJabatan()">Batal</button>
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?= ui_crud_impor([
        'judul'        => 'Import Guru',
        'aksi'         => site_url('admin/master/guru/import-preview'),
        'templateUrl'  => site_url('admin/master/guru/template'),
        'catatan'      => '<b>Import = memasukkan data.</b> Unggah file Excel sesuai template. Setelah diunggah, data akan ditampilkan dalam <b>pratinjau yang bisa diedit langsung</b> sebelum benar-benar disimpan. Baris dengan <b>Kode guru</b> yang sudah ada akan diperbarui (tidak dobel).',
        'tambahanHtml' => '<p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Atau ambil otomatis dari data kesediaan yang sudah masuk:</p>
            <a href="' . site_url('admin/master/guru/import-kesediaan') . '" class="btn btn-netral" data-confirm="Impor semua guru dari Data Kesediaan? Yang sudah ada akan dilewati." data-confirm-judul="Impor dari Data Kesediaan?" data-confirm-ya="Ya, impor">'
            . ikon('clipboard-list', 'h-4 w-4') . 'Impor dari Data Kesediaan</a>',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/guru.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/guru.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
