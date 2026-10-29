<?php
/**
 * Halaman Master Siswa — pola halaman data template Flowbite (rombak tampilan F5). Saringan langsung (kelas/tingkat/status/biodata),
 * Export ikut saringan yang tampil, tombol "Format 8355" (Daftar Nama Siswa resmi), Detail per siswa, formulir buku induk lengkap
 * dalam dialog. Komponen "siswaPage" (master/siswa.js). Siap Alpine versi CSP.
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
use App\Libraries\SiswaStatus;
use App\Models\SiswaModel;

$lencanaStatus = ['aktif' => 'lencana-hijau', 'lulus' => 'lencana-merek', 'pindah' => 'lencana-kuning', 'keluar' => 'lencana-abu'];
// Status: Aktif, "Tidak aktif (semua)" = lulus+pindah+keluar, tiap status nonaktif, dan data terhapus lama (SiswaStatus).
$opsiStatus    = ['aktif' => 'Aktif', 'nonaktif' => 'Tidak aktif (semua)'] + SiswaStatus::NONAKTIF + ['terhapus' => 'Terhapus (lama)'];
$terhapus      = $status === 'terhapus';
$opsiTingkat   = array_combine($tingkatList, array_map(static fn ($t) => 'Kelas ' . $t, $tingkatList));
$opsiBiodata   = ['lengkap' => 'Biodata lengkap', 'belum' => 'Biodata belum'];
$adaSaring     = $q !== '' || $kelasId || $tingkat !== '' || $status !== '' || $biodata !== '';

// URL export ikut membawa filter yang sedang aktif (app.js memperbaruinya dari pilihan yang TAMPIL saat diklik).
$nilaiKelas = $kelasId === -1 ? 'tanpa' : ($kelasId ?: ''); // -1 = saringan "Tanpa kelas"
$qsExport   = array_filter([
    'kelas_id' => $nilaiKelas,
    'tingkat'  => $tingkat,
    'status'   => $status,
    'biodata'  => $biodata,
], static fn ($v) => $v !== '');

$label = \App\Libraries\BiodataForm::LABEL;

/** Satu kolom isian dialog. $o: type, max, span (kelas grid), list (id datalist), mode (inputmode). */
$kolom = static function (string $nama, string $judul, array $o = []): void {
    $id = 'siswa-' . str_replace('_', '-', $nama);
    echo '<div' . (isset($o['span']) ? ' class="' . $o['span'] . '"' : '') . '>'
        . '<label class="lbl" for="' . $id . '">' . esc($judul) . '</label>'
        . '<input type="' . ($o['type'] ?? 'text') . '" id="' . $id . '" name="' . $nama . '" x-model="form.' . $nama . '"'
        . (isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '')
        . (isset($o['list']) ? ' list="' . $o['list'] . '"' : '')
        . (isset($o['mode']) ? ' inputmode="' . $o['mode'] . '"' : '')
        . (($o['type'] ?? '') === 'number' ? ' min="1" max="99"' : '')
        . ' class="inp"></div>';
};

/** Blok alamat terstruktur (siswa: $p = '', orang tua: $p = 'ortu_'). */
$blokAlamat = static function (string $p) use ($kolom, $label): void {
    $kolom($p . 'rt', $label[$p . 'rt'], ['max' => 5, 'mode' => 'numeric']);
    $kolom($p . 'rw', $label[$p . 'rw'], ['max' => 5, 'mode' => 'numeric']);
    $kolom($p . 'kelurahan', $label[$p . 'kelurahan'], ['max' => 100]);
    $kolom($p . 'kecamatan', $label[$p . 'kecamatan'], ['max' => 100]);
    $kolom($p . 'kota', $label[$p . 'kota'], ['max' => 100, 'span' => 'sm:col-span-2']);
};

/** Judul bagian di dalam dialog. */
$bagian = static fn (string $judul, string $ket = ''): string => '<p class="border-t border-gray-200 pt-4 text-xs font-semibold tracking-wide text-gray-500 uppercase first:border-t-0 first:pt-0 dark:border-gray-600 dark:text-gray-400">'
    . $judul . ($ket !== '' ? ' <span class="font-normal normal-case">' . $ket . '</span>' : '') . '</p>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'siswa_v5',
    'helpTitle' => 'Master Siswa',
    'helpKelas' => '',
    'helpBody'  => '<p>Data siswa lengkap sesuai buku induk: identitas, alamat, sekolah asal, orang tua, wali, dan kelas. Tingkat dan jurusan <b>mengikuti kelas</b> siswa, jadi cukup pilih kelasnya saja.</p>
        <p class="mt-1">• <b>Biodata dari siswa</b> — siswa bisa mengisi biodatanya sendiri lewat menu <b>Isian Biodata Siswa</b>. Setelah admin menyetujui isiannya, datanya otomatis masuk ke sini dan siswa ditandai lencana hijau <b>Biodata</b>. Pakai saringan <b>Biodata lengkap / belum</b> untuk melihat siapa yang belum.<br>
        • Data yang sudah masuk tetap bisa <b>diubah manual</b> lewat tombol Ubah.</p>
        <p class="mt-1">• <b>Import</b> — unduh template, isi di Excel, unggah, periksa di pratinjau, simpan. NIS yang sudah ada akan <b>diperbarui</b>, bukan diduplikat. <b>Sel yang dikosongkan tidak mengubah data lama</b>, jadi aman mengimpor ulang daftar yang hanya berisi NIS, nama, dan kelas.<br>
        • Kolom <b>Kelas</b> pada file impor diisi <b>nama kelas</b> persis seperti di Master Kelas (mis. <i>X TKJ 1</i>).<br>
        • <b>Penting:</b> NIS bisa berganti ke NIS asli setelah biodata disetujui. Untuk impor ulang, pakai file hasil <b>Export terbaru</b>, bukan file lama — NIS lama akan dianggap siswa baru.<br>
        • <b>Filter</b> — memilih kelas/tingkat/status/biodata langsung menyaring tabel (tanpa klik Cari). Pilihan <b>— Tanpa kelas —</b> menampilkan siswa yang belum ditempatkan di kelas.<br>
        • <b>Detail</b> — klik <b>nama siswa</b> atau tombol <b>Detail</b> di kolom Aksi untuk melihat SEMUA data satu siswa (identitas, kelas, alamat, orang tua, wali, STTB, status biodata &amp; PKL). Dari sana tombol <b>Ubah data</b> membuka form ubah.<br>
        • <b>Data Sekolah</b> — nama orang tua versi sekolah, nomor &amp; tahun STTB (ijazah SMP) sesuai Daftar Nama Siswa resmi (Format 8355). Kolom ini ada di <b>paling kanan</b> template impor.<br>
        • <b>Export</b> — mengunduh sesuai pilihan filter (pilih satu kelas = hanya kelas itu), berisi seluruh kolom biodata. Bila isinya lebih dari satu kelas, file Excel berisi tab <b>Semua Kelas</b> + <b>satu tab per kelas</b> (klik nama kelas di bawah layar Excel).</p>
        <p class="mt-1">• Status <b>Aktif</b> yang dihitung pada grafik jumlah siswa di halaman publik. Siswa lulus/pindah/keluar tetap tersimpan sebagai arsip.<br>
        • <b>Nonaktifkan</b> — siswa yang lulus, pindah sekolah, atau keluar: statusnya diganti, data &amp; riwayat (PKL, surat, biodata) tetap utuh, dan siswa tidak muncul lagi di formulir siswa. Bisa <b>Aktifkan</b> lagi kapan saja.<br>
        • <b>Hapus permanen</b> (halaman Detail, atau centang lalu Hapus terpilih) — hanya untuk data salah input yang belum punya data terkait; NIS/NISN-nya langsung bisa dipakai lagi. Siswa yang sudah punya data PKL/surat/biodata tidak bisa dihapus — nonaktifkan saja.<br>
        • Saringan Status <b>Terhapus (lama)</b> — data yang dulu "dihapus" dengan cara lama: Pulihkan atau Hapus permanen.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="siswaPage" data-base="<?= site_url('admin/master/siswa') ?>" data-entity="siswa"
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
         // Daftar Nama Siswa resmi sekolah (Format 8355)
         'nama_orang_tua' => '', 'sttb_nomor' => '', 'sttb_tahun' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'        => site_url('admin/master/siswa'),
        'cari'           => 'Cari nama, NIS, atau NISN…',
        'q'              => $q,
        'per'            => $per,
        'filter'         => [
            ['name' => 'kelas_id', 'value' => (string) $nilaiKelas, 'all' => 'Semua kelas', 'options' => ['tanpa' => '— Tanpa kelas —'] + $kelasOpts],
            ['name' => 'tingkat', 'value' => $tingkat, 'all' => 'Semua tingkat', 'options' => $opsiTingkat],
            ['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => $opsiStatus],
            ['name' => 'biodata', 'value' => $biodata, 'all' => 'Semua biodata', 'options' => $opsiBiodata],
        ],
        'filterLangsung' => true,
        'eksporUrl'      => site_url('admin/master/siswa/export') . ($qsExport !== [] ? '?' . http_build_query($qsExport) : ''),
        'eksporTitle'    => 'Unduh data siswa sesuai filter yang dipilih (mis. satu kelas saja) ke file Excel',
        'tambahanHtml'   => '<a href="' . site_url('admin/master/siswa/periksa') . '" class="btn btn-netral" title="Kemungkinan data ganda, data tidak sesuai, kelas hasil penempatan otomatis">'
            . ikon('clipboard-check', 'h-4 w-4') . 'Periksa data</a>'
            . '<a href="' . site_url('admin/master/siswa/export-resmi') . '" class="btn btn-netral" title="Unduh Daftar Nama Siswa Kelas X, XI, XII dengan format persis berkas sekolah (Format 8355): semua siswa aktif per jurusan, lengkap dengan tanda tangan">'
            . '<span class="text-green-600 dark:text-green-400">' . ikon('file-lines', 'h-4 w-4') . '</span>Format 8355</a>',
        'massalUrl'        => site_url('admin/master/siswa/bulk-delete'),
        'massalLabel'      => 'siswa',
        'massalSemua'      => false, // "Hapus semua" tidak tersedia untuk siswa
        'massalPeringatan' => 'Ini HAPUS PERMANEN: hanya siswa yang belum punya data terkait (PKL, surat, biodata, dll.) yang terhapus; selebihnya dilewati — nonaktifkan saja.',
    ]) ?>

    <?php if ($terhapus): ?>
        <div class="px-4 pt-4"><?= ui_peringatan('info', 'Ini data siswa yang dulu "dihapus" dengan cara lama (masih tersimpan, NIS/NISN-nya masih terpakai). Pulihkan bila ternyata siswa masih ada, atau Hapus permanen bila data itu salah input. Hapus permanen hanya bisa bila belum punya data terkait.', ['judul' => 'Terhapus (lama)']) ?></div>
    <?php endif; ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua siswa di halaman ini"></th>
                    <th scope="col">NIS</th>
                    <th scope="col">Nama siswa</th>
                    <th scope="col" class="text-center">JK</th>
                    <th scope="col">Kelas</th>
                    <th scope="col">Jurusan</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'user-graduate', 'judul' => $adaSaring ? 'Siswa tidak ditemukan' : 'Belum ada data siswa',
                                'teks' => $adaSaring ? 'Tidak ada siswa yang cocok dengan saringan ini. Tekan Reset.' : 'Tambah siswa satu per satu, atau Import Excel (lebih cepat untuk satu kelas sekaligus).']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $urlDetail = site_url('admin/master/siswa/' . (int) $r['id']); ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih siswa <?= esc($r['nis'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['nis']) ?></td>
                        <td class="min-w-48">
                            <a href="<?= $urlDetail ?>" class="font-medium hover:text-brand-700 hover:underline dark:hover:text-brand-400" title="Lihat detail siswa"><?= esc($r['nama']) ?></a>
                            <?php if (! empty($r['nisn']) || ! empty($r['biodata_at'])): ?>
                                <div class="sel-ket">
                                    <?php if (! empty($r['nisn'])): ?>NISN <?= esc($r['nisn']) ?><?php endif; ?>
                                    <?php if (! empty($r['biodata_at'])): ?>
                                        <span class="lencana lencana-hijau ms-1" title="Biodata disahkan <?= esc(date('d/m/Y H:i', strtotime($r['biodata_at'])), 'attr') ?>"><?= ikon('check', 'h-3 w-3') ?>Biodata</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $r['jenis_kelamin'] !== null ? esc($r['jenis_kelamin']) : '<span class="sel-ket">—</span>' ?></td>
                        <td class="whitespace-nowrap"><?= $r['nama_kelas'] !== null ? esc($r['nama_kelas']) : '<span class="sel-ket">Belum ada kelas</span>' ?></td>
                        <td class="sel-ket"><?= $r['jurusan_kode'] !== null ? esc($r['jurusan_kode']) : '—' ?></td>
                        <td><span class="lencana <?= $terhapus ? 'lencana-merah' : ($lencanaStatus[$r['status']] ?? 'lencana-abu') ?>"><?= $terhapus ? 'Terhapus' : esc(SiswaStatus::label((string) $r['status'])) ?></span></td>
                        <td class="kolom-aksi">
                            <?php if ($terhapus): ?>
                                <div class="inline-flex items-center justify-end gap-2">
                                    <form method="post" action="<?= site_url('admin/master/siswa/' . (int) $r['id'] . '/pulihkan') ?>" data-confirm="Pulihkan data <?= esc($r['nama'], 'attr') ?> (NIS <?= esc($r['nis'], 'attr') ?>)? Siswa kembali muncul di daftar dengan status <?= esc(SiswaStatus::label((string) $r['status']), 'attr') ?>." data-confirm-judul="Pulihkan siswa?" data-confirm-ya="Ya, pulihkan">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sukses btn-sm"><?= ikon('check-circle', 'h-4 w-4') ?>Pulihkan</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/master/siswa/delete/' . (int) $r['id']) ?>" data-confirm="<?= esc($r['nama'], 'attr') ?> (NIS <?= esc($r['nis'], 'attr') ?>) dihapus PERMANEN. Hanya bisa bila belum punya data terkait." data-confirm-judul="Hapus permanen?" data-confirm-ya="Ya, hapus permanen" data-confirm-jenis="bahaya">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-bahaya btn-sm"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus permanen</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?php
                                // Satu tombol status per baris: Nonaktifkan (aktif) / Aktifkan (tidak aktif). Hapus permanen ada di halaman Detail & hapus massal.
                                $tombolStatus = $r['status'] === 'aktif'
                                    ? '<a href="' . site_url('admin/master/siswa/' . (int) $r['id'] . '/nonaktifkan') . '" class="btn btn-netral btn-sm">' . ikon('user-circle', 'h-4 w-4') . 'Nonaktifkan</a>'
                                    : '<form method="post" action="' . site_url('admin/master/siswa/' . (int) $r['id'] . '/aktifkan') . '" class="inline" data-confirm="' . esc($r['nama'], 'attr') . ' diaktifkan kembali." data-confirm-judul="Aktifkan kembali?" data-confirm-ya="Ya, aktifkan">'
                                        . csrf_field() . '<button type="submit" class="btn btn-sukses btn-sm">' . ikon('check-circle', 'h-4 w-4') . 'Aktifkan</button></form>';
                                ?>
                                <?= ui_crud_aksi([
                                    'row'          => $r,
                                    'detailUrl'    => $urlDetail,
                                    'tambahanHtml' => $tombolStatus,
                                ]) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'kelas_id', 'tingkat', 'status', 'biodata', 'per'], 'satuan' => 'siswa']) ?>

    <?= ui_crud_form_buka(['benda' => 'Siswa', 'ukuran' => 'lg']) ?>
        <?= $bagian('Identitas') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="siswa-nis">NIS <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="siswa-nis" name="nis" x-model="form.nis" maxlength="30" required class="inp">
            </div>
            <div>
                <label class="lbl" for="siswa-nisn">NISN</label>
                <input type="text" id="siswa-nisn" name="nisn" x-model="form.nisn" maxlength="30" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="siswa-nama">Nama lengkap <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="siswa-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="siswa-jk">Jenis kelamin</label>
                <select id="siswa-jk" name="jenis_kelamin" x-model="form.jenis_kelamin" class="inp">
                    <option value="">— Pilih —</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="siswa-agama">Agama</label>
                <input type="text" id="siswa-agama" name="agama" x-model="form.agama" maxlength="25" list="daftar-agama" class="inp">
                <datalist id="daftar-agama">
                    <?php foreach (SiswaModel::AGAMA as $a): ?><option value="<?= esc($a, 'attr') ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="lbl" for="siswa-tempat-lahir">Tempat lahir</label>
                <input type="text" id="siswa-tempat-lahir" name="tempat_lahir" x-model="form.tempat_lahir" maxlength="100" class="inp">
            </div>
            <div>
                <label class="lbl" for="siswa-tanggal-lahir">Tanggal lahir</label>
                <input type="date" id="siswa-tanggal-lahir" name="tanggal_lahir" x-model="form.tanggal_lahir" class="inp">
            </div>
            <div>
                <label class="lbl" for="siswa-status-keluarga"><?= esc($label['status_keluarga']) ?></label>
                <select id="siswa-status-keluarga" name="status_keluarga" x-model="form.status_keluarga" class="inp">
                    <option value="">— Pilih —</option>
                    <?php foreach (SiswaModel::STATUS_KELUARGA as $sk): ?>
                        <option value="<?= esc($sk, 'attr') ?>"><?= esc($sk) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php $kolom('anak_ke', $label['anak_ke'], ['type' => 'number']) ?>
        </div>

        <?= $bagian('Alamat &amp; kontak siswa') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $kolom('alamat', 'Alamat (jalan / perumahan, blok, nomor)', ['max' => 255, 'span' => 'sm:col-span-2']) ?>
            <?php $blokAlamat('') ?>
            <?php $kolom('no_hp', 'No HP siswa', ['max' => 25, 'mode' => 'tel']) ?>
        </div>

        <?= $bagian('Riwayat masuk') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $kolom('sekolah_asal', $label['sekolah_asal'], ['max' => 150, 'span' => 'sm:col-span-2']) ?>
            <?php $kolom('diterima_kelas', $label['diterima_kelas'], ['max' => 50, 'list' => 'daftar-kelas-siswa']) ?>
            <?php $kolom('diterima_tanggal', $label['diterima_tanggal'], ['type' => 'date']) ?>
            <datalist id="daftar-kelas-siswa">
                <?php foreach ($kelasOpts as $namaKelas): ?><option value="<?= esc($namaKelas, 'attr') ?>"></option><?php endforeach; ?>
            </datalist>
        </div>

        <?= $bagian('Orang tua') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $kolom('nama_ayah', $label['nama_ayah'], ['max' => 150]) ?>
            <?php $kolom('pekerjaan_ayah', $label['pekerjaan_ayah'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
            <?php $kolom('nama_ibu', $label['nama_ibu'], ['max' => 150]) ?>
            <?php $kolom('pekerjaan_ibu', $label['pekerjaan_ibu'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
            <?php $kolom('ortu_alamat', $label['ortu_alamat'], ['max' => 255, 'span' => 'sm:col-span-2']) ?>
            <?php $blokAlamat('ortu_') ?>
            <?php $kolom('ortu_telepon', $label['ortu_telepon'] . ' (bisa 2 nomor, pisahkan dengan /)', ['max' => 60, 'mode' => 'tel', 'span' => 'sm:col-span-2']) ?>
            <datalist id="daftar-pekerjaan">
                <?php foreach (SiswaModel::PEKERJAAN as $pk): ?><option value="<?= esc($pk, 'attr') ?>"></option><?php endforeach; ?>
            </datalist>
        </div>

        <?= $bagian('Wali', '(kosongkan bila tidak ada wali selain orang tua)') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $kolom('nama_wali', $label['nama_wali'], ['max' => 150]) ?>
            <?php $kolom('no_hp_wali', 'No HP wali', ['max' => 25, 'mode' => 'tel']) ?>
            <?php $kolom('alamat_wali', $label['alamat_wali'], ['max' => 255, 'span' => 'sm:col-span-2']) ?>
            <?php $kolom('pekerjaan_wali', $label['pekerjaan_wali'], ['max' => 100, 'list' => 'daftar-pekerjaan']) ?>
        </div>

        <?= $bagian('Data sekolah', '(sesuai Daftar Nama Siswa resmi — Format 8355)') ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?php $kolom('nama_orang_tua', SiswaModel::LABEL_SEKOLAH['nama_orang_tua'], ['max' => 150, 'span' => 'sm:col-span-2']) ?>
            <?php $kolom('sttb_nomor', SiswaModel::LABEL_SEKOLAH['sttb_nomor'] . ' (ijazah SMP/MTs)', ['max' => 60]) ?>
            <?php $kolom('sttb_tahun', SiswaModel::LABEL_SEKOLAH['sttb_tahun'], ['max' => 4, 'mode' => 'numeric']) ?>
        </div>

        <?= $bagian('Kelas &amp; status') ?>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="lbl" for="siswa-kelas">Kelas</label>
                <select id="siswa-kelas" name="kelas_id" x-model="form.kelas_id" class="inp">
                    <option value="">— Belum ada kelas —</option>
                    <?php foreach ($kelasOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1">Tingkat &amp; jurusan mengikuti kelas.</p>
            </div>
            <div>
                <label class="lbl" for="siswa-tahun-masuk">Tahun masuk</label>
                <input type="number" id="siswa-tahun-masuk" name="tahun_masuk" x-model="form.tahun_masuk" min="1990" max="2100" class="inp">
            </div>
            <div>
                <label class="lbl" for="siswa-status">Status</label>
                <select id="siswa-status" name="status" x-model="form.status" class="inp">
                    <?php foreach ($opsiStatus as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="lbl" for="siswa-keterangan">Keterangan</label>
                <input type="text" id="siswa-keterangan" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Siswa',
        'aksi'        => site_url('admin/master/siswa/import-preview'),
        'templateUrl' => site_url('admin/master/siswa/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template — cocok untuk memasukkan satu kelas (mis. 50 siswa) sekaligus. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. <b>NIS</b> yang sudah ada akan diperbarui, bukan diduplikat — dan <b>sel yang kosong tidak mengubah data lama</b> (biodata yang sudah diisi siswa aman). Kolom <b>Kelas</b> diisi nama kelas persis seperti di Master Kelas. Kolom biodata (orang tua, wali, dsb.) ada di <b>bagian kanan</b> template.',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/siswa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/siswa.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
