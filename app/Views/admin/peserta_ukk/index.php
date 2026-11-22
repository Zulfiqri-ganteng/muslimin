<?php
/**
 * Daftar peserta UKK (pendaftaran) — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, ui_crud_alat,
 * tabel-crud, status bertitik, ui_crud_aksi, ui_crud_kaki, dialog kit "Ubah status" diisi dari tombol barisnya lewat
 * data-dialog-aksi / data-dialog-isi). Tanpa Alpine di halaman → siap CSP.
 *
 * @var array                          $rows       Baris peserta halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager      Paginasi
 * @var string                        $q          Kata kunci pencarian
 * @var int                           $paketId    Filter paket soal aktif
 * @var string                        $status     Filter status aktif
 * @var int                           $per        Baris per halaman
 * @var int                           $total      Total peserta (sesuai filter paket)
 * @var int                           $lulus      Jumlah lulus
 * @var int                           $tidakLulus Jumlah tidak lulus
 * @var array<int,string>             $paketOpts  Opsi paket soal
 * @var array                         $statusList Daftar status sah
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/peserta-ukk');
$warnaStatus = ['terdaftar' => '', 'hadir' => 'titik-status-biru', 'tidak_hadir' => 'titik-status-kuning', 'lulus' => 'titik-status-hijau', 'tidak_lulus' => 'titik-status-merah'];
$labelStatus = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s));
$opsiStatus  = [];
foreach ($statusList as $s) {
    $opsiStatus[$s] = $labelStatus($s);
}
$opsiPaket = [];
foreach ($paketOpts as $id => $lbl) {
    $opsiPaket[(string) $id] = $lbl;
}
$adaFilter = $q !== '' || $paketId > 0 || $status !== '';
$jumlahBaris = $pager ? $pager->getTotal() : count($rows);
$ringkas = '<span class="lencana lencana-abu py-1">Total: ' . (int) $total . '</span>'
    . '<span class="lencana lencana-hijau py-1">Lulus: ' . (int) $lulus . '</span>'
    . '<span class="lencana lencana-merah py-1">Tidak lulus: ' . (int) $tidakLulus . '</span>';
// Dialog "Hapus semua": sebutkan persis berapa & yang mana yang terhapus (saringan aktif = hanya yang cocok saringan).
$peringatanSemua = ($adaFilter
        ? 'Saringan sedang dipakai, jadi yang terhapus HANYA ' . (int) $jumlahBaris . ' peserta yang cocok dengan saringan ini (di semua halaman).'
        : 'Yang terhapus: SELURUH ' . (int) $jumlahBaris . ' peserta UKK dari semua paket soal. Untuk menghapus satu paket soal saja, pilih paketnya dulu di saringan.')
    . ' Siswanya bisa didaftarkan lagi lewat tombol Daftarkan peserta.';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'peserta_ukk',
    'helpTitle' => 'Peserta UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar siswa yang mengikuti Uji Kompetensi Keahlian (UKK) per paket soal.</p>
        <p class="mt-3"><b>1. Mencari peserta</b><br>
        • Ketik nama, NIS, atau nomor peserta di kotak <b>Cari</b>, lalu tekan Enter.<br>
        • Pilihan <b>Semua paket soal</b> dan <b>Semua status</b> langsung menyaring daftar begitu dipilih. <b>Reset</b> = hapus saringan.<br>
        • <b>Tampilkan 10–50</b> = jumlah peserta per halaman. Angka <b>Total / Lulus / Tidak lulus</b> mengikuti paket soal yang dipilih.</p>
        <p class="mt-3"><b>2. Mendaftarkan peserta</b><br>
        • Tekan tombol biru <b>Daftarkan peserta</b> (kanan atas) → pilih paket soal + kelas → centang siswa yang ikut → simpan. Satu kelas bisa didaftarkan sekaligus.<br>
        • Siswa yang sudah terdaftar di paket soal itu otomatis disembunyikan, jadi tidak bisa dobel.<br>
        • Nomor peserta dibuat otomatis (format <code>UKK-{KODE PAKET}-001</code>).</p>
        <p class="mt-3"><b>3. Tombol di tiap peserta</b> (kolom Aksi paling kanan; di HP geser tabel ke kanan)<br>
        • <b>Status</b> (biru) — mengubah status manual: Terdaftar, Hadir, Tidak hadir, Lulus, Tidak lulus. Lulus/Tidak lulus normalnya terisi otomatis sesudah penilaian.<br>
        • <b>Hapus</b> (merah) — menghapus pendaftaran satu peserta.</p>
        <p class="mt-3"><b>4. Menghapus banyak peserta sekaligus</b><br>
        • Centang kotak di depan nama peserta yang ingin dihapus → muncul tombol merah <b>Hapus terpilih (angka)</b> di sebelah kotak cari → tekan, lalu pilih <b>Ya, hapus</b>.<br>
        • Centang kotak di <b>judul kolom</b> (paling kiri, sebelah "No. peserta") = pilih semua peserta di halaman ini. Saat semua tercentang, muncul juga tombol <b>Hapus semua</b>.<br>
        • <b>Hapus semua</b> menghapus semua peserta di <b>semua halaman</b> (bukan hanya 10 yang tampil), mengikuti saringan yang sedang dipakai. Contoh: pilih paket soal <i>0001</i> dulu → centang judul kolom → <b>Hapus semua</b> = hanya peserta paket 0001 yang terhapus. Tanpa saringan = seluruh peserta UKK terhapus.<br>
        • Sebelum terhapus selalu muncul pertanyaan yang menyebut jumlahnya, jadi periksa dulu angkanya.</p>
        <p class="mt-3"><b>5. Kenapa tombol Hapus semua tidak langsung terlihat?</b><br>
        Sengaja disembunyikan supaya tidak terpencet tidak sengaja. Tombol baru muncul sesudah kotak di judul kolom dicentang.</p>
        <p class="mt-3"><b>6. Salah hapus, bisa dikembalikan?</b><br>
        Daftarkan lagi siswanya lewat <b>Daftarkan peserta</b> (paket soal + kelas yang sama). Siswa itu akan muncul lagi dengan <b>nomor peserta baru</b> dan status Terdaftar.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'        => $base,
    'cari'           => 'Cari nama, NIS, atau no. peserta…',
    'q'              => $q,
    'filter'         => [
        ['name' => 'paket_soal_id', 'value' => $paketId > 0 ? (string) $paketId : '', 'all' => 'Semua paket soal', 'options' => $opsiPaket],
        ['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => $opsiStatus],
    ],
    'per'            => $per,
    'filterLangsung' => true,
    'tambah'         => false,
    'impor'          => false,
    'tambahanHtml'   => $ringkas . '<a href="' . site_url('admin/peserta-ukk/daftarkan') . '" class="btn btn-utama">' . ikon('plus', 'h-4 w-4') . 'Daftarkan peserta</a>',
    // Hapus massal (pola Master Data, delegasi global app.js). Saringan ikut terkirim → "Hapus semua" = semua yang cocok saringan.
    'massalUrl'             => $base . '/bulk-delete',
    'massalLabel'           => 'peserta',
    'massalHidden'          => ['q' => $q, 'paket_soal_id' => $paketId > 0 ? (string) $paketId : '', 'status' => $status],
    'massalPeringatan'      => 'Siswanya bisa didaftarkan lagi lewat tombol Daftarkan peserta.',
    'massalPeringatanSemua' => $peringatanSemua,
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua peserta di halaman ini"></th>
                <th scope="col">No. peserta</th>
                <th scope="col">Siswa</th>
                <th scope="col">Paket soal</th>
                <th scope="col">Jadwal</th>
                <th scope="col" class="text-right">Nilai</th>
                <th scope="col">Status</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8">
                        <?= ui_kosong(['ikon' => 'user-graduate', 'judul' => $adaFilter ? 'Tidak ada peserta yang cocok' : 'Belum ada peserta terdaftar',
                            'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Klik Daftarkan peserta untuk memulai.']) ?>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih peserta <?= esc($r['siswa_nama'] ?? (string) $r['id'], 'attr') ?>"></td>
                    <td class="font-mono font-semibold whitespace-nowrap text-brand-700 dark:text-brand-400"><?= esc($r['no_peserta'] ?? '—') ?></td>
                    <td>
                        <p class="sel-judul"><?= esc($r['siswa_nama'] ?? '—') ?></p>
                        <p class="sel-ket"><?= esc($r['nis'] ?? '—') ?> · <?= esc($r['nama_kelas'] ?? '—') ?></p>
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-white"><?= esc($r['paket_kode'] ?? '—') ?></p>
                        <p class="sel-ket"><?= esc($r['paket_nama'] ?? '—') ?></p>
                    </td>
                    <td class="whitespace-nowrap"><?= $tgl($r['jadwal_tanggal'] ?? null) ?></td>
                    <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= $r['nilai_akhir'] !== null ? number_format((float) $r['nilai_akhir'], 1) : '—' ?></td>
                    <td><span class="titik-status <?= $warnaStatus[$r['status']] ?? '' ?> font-medium"><?= esc($labelStatus($r['status'])) ?></span></td>
                    <td class="kolom-aksi">
                        <?= ui_crud_aksi([
                            'ubah'         => false,
                            'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                            'konfirmasi'   => 'Hapus pendaftaran peserta ini?',
                            'tambahanHtml' => '<button type="button" class="btn btn-utama btn-sm" data-buka-dialog="status-peserta" data-dialog-aksi="' . esc($base . '/status/' . (int) $r['id'], 'attr') . '" data-dialog-isi="' . esc((string) json_encode(['label' => $r['siswa_nama'] ?? '-', 'status' => $r['status']]), 'attr') . '">' . ikon('edit', 'h-4 w-4') . 'Status</button>',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $jumlahBaris, 'kunci' => ['q', 'paket_soal_id', 'status', 'per'], 'satuan' => 'peserta']) ?>

<!-- Dialog Ubah status (diisi dari tombol baris) -->
<?= ui_dialog_buka(['nama' => 'status-peserta', 'judul' => 'Ubah status peserta', 'ukuran' => 'sm']) ?>
<form method="post" action="<?= $base ?>" data-aksi-pemicu>
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <p class="text-sm font-medium text-gray-900 dark:text-white" data-isi-pemicu="label"></p>
        <div>
            <label class="lbl" for="ps-status">Status</label>
            <select id="ps-status" name="status" data-isi-pemicu="status" class="inp">
                <?php foreach ($statusList as $s): ?>
                    <option value="<?= esc($s, 'attr') ?>"><?= esc($labelStatus($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>
<?= $this->endSection() ?>
