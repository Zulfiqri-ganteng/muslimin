<?php
/**
 * Detail satu ajuan PKL + keputusan staf. Rombak tampilan F9: Kembali di kepala halaman, tab kit (_nav), kartu kit (kepala ajuan,
 * Perusahaan, Periode, Siswa, Keputusan, Persetujuan, Surat permohonan, Pembayaran, Surat sekolah, Riwayat), ui_peringatan untuk
 * batas keputusan & peringatan otomatis, lencana kit, tombol kit ber-ikon (pengganti emoji). Siap CSP: dialog keputusan = dialog
 * kit (ui_dialog_buka, dibuka data-buka-dialog; hapus catatan biaya & cabut beasiswa diisi data-dialog-isi) — dulu satu x-data
 * akar + x-show per dialog + @click berisi beberapa penugasan; tombol unduh/WhatsApp = atribut data-pkl-* (pkl-unduh.js).
 *
 * @var array       $a           ajuan (PklPengajuanModel::detail)
 * @var list<array> $anggota
 * @var list<array> $riwayat
 * @var list<array> $peringatan  [tingkat, teks]
 * @var bool        $adaBahaya
 * @var array       $master      ['persis' => ?array, 'mirip' => list<array>]
 * @var string      $kode
 * @var bool        $bolehHapus
 * @var bool        $bolehAcc    peran ini berhak ACC/tolak/cabut persetujuan (hak "ACC ajuan PKL", diatur Admin)
 * @var ?int        $sisaHari    sisa hari menuju batas keputusan ACC (negatif = terlambat), hanya untuk status menunggu
 * @var ?string     $batasKeputusan
 * @var list<array> $suratSekolah       surat sekolah yang tertaut ke ajuan ini (Balasan, Penarikan, Pernyataan, ASTS/TKA per perusahaan)
 * @var bool        $bolehSuratSekolah  peran ini boleh membuat salah satu surat sekolah dari halaman ini
 * @var array<string, bool> $bolehBuatSurat jenis surat (Balasan/Penarikan/Pernyataan) => boleh membuka formulirnya
 * @var array|null  $lapor             laporan "sudah diterima perusahaan" dari siswa (baris pkl_lapor) atau null
 * @var bool        $bolehKotakLapor   boleh membuka kotak Lapor Diterima PKL
 * @var array|null  $surat             surat permohonan PKL (nomor, tanggal_surat, cetak_ke) atau null
 * @var bool        $perluUlang        data ajuan berubah sejak surat terakhir diunduh
 * @var list<array>|null $pembayaran   catatan biaya per siswa (hak surat/laporan)
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, $p, $peran, $batasHari, $bolehUbah, $bolehSurat, $bolehLaporan, $bolehPengaturan.
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklSurat;

$namaSaya = (string) (session('admin')['full_name'] ?? '');
$id     = (int) $a['id'];
$status = (string) $a['status'];
$hari   = ($a['tanggal_mulai'] && $a['tanggal_selesai']) ? IsianBantu::hariInklusif($a['tanggal_mulai'], $a['tanggal_selesai']) : null;
$fase   = null;
if ($status === 'disetujui' && $a['tanggal_mulai'] && $a['tanggal_selesai']) {
    $hariIni = date('Y-m-d');
    $fase    = $hariIni < $a['tanggal_mulai'] ? 'belum_mulai' : ($hariIni > $a['tanggal_selesai'] ? 'selesai' : 'sedang');
}
$labelAksi = [
    'kirim' => 'Dikirim siswa', 'kirim_ulang' => 'Dikirim ulang (perbaikan)', 'acc' => 'Disetujui', 'kembalikan' => 'Dikembalikan ke siswa',
    'tolak' => 'Ditolak', 'batal_acc' => 'Persetujuan dibatalkan', 'ubah' => 'Data diubah staf', 'isi_atas_nama' => 'Diisi atas nama siswa',
    'tunda' => 'Dikembalikan ke antrean', 'surat' => 'Surat diterbitkan', 'cetak' => 'Surat diunduh', 'impor' => 'Diimpor dari Excel',
    'bayar' => 'Biaya dicatat', 'koreksi_bayar' => 'Catatan biaya dikoreksi', 'kabari' => 'Siswa dikabari (WhatsApp)',
    'periode' => 'Periode PKL diisi', 'surat_sekolah' => 'Surat sekolah',
    'lapor_diterima' => 'Siswa lapor diterima perusahaan', 'lapor_proses' => 'Laporan diterima diproses', 'lapor_kembali' => 'Laporan diterima dikembalikan',
];
// Peringatan otomatis → jenis peringatan kit + kata awal.
$jenisPeringatan = ['bahaya' => ['galat', 'Bahaya'], 'awas' => ['awas', 'Periksa'], 'info' => ['info', 'Info']];
$labelDaftar = ['menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perbaikan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'][$status] ?? '';
$baris = static function (string $label, ?string $isi): string {
    $isi = trim((string) $isi);

    return '<div class="grid grid-cols-[7.5rem_1fr] gap-3 px-4 py-2.5 text-sm sm:px-6"><dt class="text-gray-500 dark:text-gray-400">' . esc($label) . '</dt><dd class="font-medium break-words text-gray-900 dark:text-white">'
        . ($isi !== '' ? esc($isi) : '<span class="font-normal text-gray-400 dark:text-gray-500">—</span>') . '</dd></div>';
};
$kepalaKartu = static fn (string $judul, string $id): string => '<div class="kartu-kepala"><h3 id="' . $id . '" class="kartu-judul text-base">' . $judul . '</h3></div>';
$catatanKunci = static fn (string $html): string => '<div class="peringatan peringatan-netral">' . ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') . '<p>' . $html . '</p></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/pkl/daftar/' . $status) ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke daftar <?= esc($labelDaftar) ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_detail_v5',
    'helpTitle' => 'Detail Ajuan PKL',
    'helpBody'  => '<p>Periksa dulu <b>peringatan otomatis</b> (merah = bahaya, kuning = periksa, biru = info), lalu putuskan:</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>ACC</b> — hanya peran yang diberi hak ACC ajuan PKL oleh Admin (sekarang: ' . esc(\App\Libraries\PklHak::teksPemegang('acc')) . '; Admin selalu bisa). Tercatat siapa (nama + peran), kapan, dan tercetak di kaki surat. Perusahaan otomatis didaftarkan ke master, jadi periksa ejaan namanya dulu.</li>'
        . '<li><b>Kembalikan</b> — siswa memperbaiki sendiri, tulis alasan yang jelas.</li>'
        . '<li><b>Tolak</b> / <b>Batalkan persetujuan</b> — hanya yang berhak ACC. Tolak: siswanya bebas mengajukan baru. Batalkan: bila perusahaan menarik diri.</li>'
        . '<li><b>Ubah</b> — memperbaiki langsung (nama perusahaan, daftar siswa, HP) tanpa mengubah statusnya. Ajuan yang sudah disetujui hanya boleh diubah yang berhak ACC.</li>'
        . '<li><b>Batas keputusan</b> — keputusan diharapkan paling lambat sekian hari sejak siswa mengirim (diatur di Pengaturan); yang lewat ditandai merah.</li>'
        . '<li><b>Surat permohonan</b> (setelah Disetujui) — hanya yang diberi hak <i>unduh surat</i> oleh Admin (sekarang: ' . esc(\App\Libraries\PklHak::teksPemegang('surat')) . '). Sebelum berkas dibuat <u>wajib mencatat biaya</u> yang diterima tiap siswa (Biaya PKL, SPP, Tabungan, OSIS; atau Beasiswa / Keringanan beralasan) — bulannya terpilih otomatis (bulan pertama yang belum lunas). Bila semua siswa sudah tercatat, <b>Unduh surat</b> langsung mengunduh tanpa kotak biaya; untuk mencatat biaya tambahan pakai <b>Catat biaya lain</b>. Nomor ditetapkan sekali; bila data berubah sesudahnya muncul tanda <b>Perlu cetak ulang</b> dan nomor tetap sama. Setelah unduhan selesai, kotak <b>Kabari via WhatsApp</b> muncul bila masih ada siswa yang belum dikabari. Salah catat biaya? Hapus catatannya di kartu <b>Pembayaran siswa</b> (wajib beralasan).</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <!-- Kepala ajuan -->
    <section class="kartu kartu-isi" aria-labelledby="judul-ajuan">
        <div class="flex flex-wrap items-center gap-2.5">
            <h2 id="judul-ajuan" class="text-xl font-bold text-gray-900 dark:text-white"><?= esc($a['perusahaan_nama']) ?></h2>
            <?= view('admin/pkl/_lencana', ['kode' => $fase ?? $status]) ?>
            <?php if ($a['sumber'] !== 'siswa'): ?><span class="lencana lencana-ungu"><?= $a['sumber'] === 'staf' ? 'Diisi staf' : 'Impor data lama' ?></span><?php endif; ?>
            <?php if ((int) $a['kirim_ke'] > 1): ?><span class="lencana lencana-abu">Revisi ke-<?= (int) $a['kirim_ke'] - 1 ?></span><?php endif; ?>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?= esc($kode) ?></span> · dikirim <?= esc(date('d-m-Y H:i', strtotime($a['created_at']))) ?> · diperbarui <?= esc(date('d-m-Y H:i', strtotime($a['updated_at']))) ?></p>
        <?php if (! empty($a['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
            <div class="mt-3"><?= ui_peringatan('netral', '', ['html' => '<b>Catatan untuk siswa:</b> ' . esc($a['catatan_staf'])]) ?></div>
        <?php endif; ?>
    </section>

    <!-- Batas keputusan ACC -->
    <?php if ($status === 'menunggu' && ($sisaHari ?? null) !== null): ?>
        <?= ui_peringatan($sisaHari < 0 ? 'galat' : ($sisaHari <= 1 ? 'awas' : 'info'), '', ['html' => '<b>' . ($sisaHari < 0 ? 'TERLAMBAT ' . abs($sisaHari) . ' hari' : ($sisaHari === 0 ? 'HARI INI batasnya' : 'Sisa ' . $sisaHari . ' hari')) . '</b> · '
            . 'Batas keputusan ACC: <b>' . esc(IsianBantu::tanggalIndo(substr((string) $batasKeputusan, 0, 10))) . '</b> (' . (int) $batasHari . ' hari sejak dikirim siswa ' . esc(date('d-m-Y H:i', strtotime((string) ($a['diajukan_at'] ?? $a['created_at'])))) . ').']) ?>
    <?php endif; ?>

    <!-- Peringatan otomatis -->
    <?php if ($peringatan !== []): ?>
        <div class="space-y-2">
            <?php foreach ($peringatan as $w): [$jenis, $kata] = $jenisPeringatan[$w['tingkat']] ?? ['info', 'Info']; ?>
                <?= ui_peringatan($jenis, '', ['html' => '<b>' . $kata . ':</b> ' . esc($w['teks'])]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Kolom kiri: data -->
        <div class="space-y-4 lg:col-span-2">
            <section class="kartu overflow-hidden" aria-labelledby="judul-perusahaan">
                <?= $kepalaKartu('Perusahaan', 'judul-perusahaan') ?>
                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?= $baris('Nama', $a['perusahaan_nama']) ?>
                    <?= $baris('Alamat', $a['perusahaan_alamat']) ?>
                    <?= $baris('Kota/Kab.', $a['perusahaan_kota']) ?>
                    <?= $baris('Telepon', $a['perusahaan_telepon']) ?>
                    <?= $baris('Pimpinan/Kontak', $a['kontak_nama']) ?>
                    <?= $baris('Jabatan', $a['kontak_jabatan']) ?>
                    <div class="grid grid-cols-[7.5rem_1fr] gap-3 px-4 py-2.5 text-sm sm:px-6">
                        <dt class="text-gray-500 dark:text-gray-400">Master</dt>
                        <dd>
                            <?php if (! empty($a['master_nama'])): ?>
                                <span class="inline-flex items-center gap-1.5 font-medium text-green-700 dark:text-green-400"><?= ikon('check-circle', 'h-4 w-4 shrink-0') ?>Terdaftar: <?= esc($a['master_nama']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400">Belum terdaftar — didaftarkan otomatis saat ACC</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </section>

            <?php if ($a['tanggal_mulai'] || $a['tanggal_selesai']): // diisi lewat Surat Balasan (form siswa tak menanyakan tanggal) ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-periode">
                    <?= $kepalaKartu('Periode PKL', 'judul-periode') ?>
                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?= $baris('Mulai', IsianBantu::tanggalIndo($a['tanggal_mulai'])) ?>
                        <?= $baris('Selesai', IsianBantu::tanggalIndo($a['tanggal_selesai'])) ?>
                        <?= $baris('Lama', $hari !== null ? $hari . ' hari (± ' . max(1, (int) round($hari / 30)) . ' bulan)' : '') ?>
                    </dl>
                </section>
            <?php endif; ?>

            <section class="kartu overflow-hidden" aria-labelledby="judul-siswa">
                <div class="kartu-kepala">
                    <h3 id="judul-siswa" class="kartu-judul text-base">Siswa</h3>
                    <span class="lencana lencana-abu"><?= count($anggota) ?> siswa</span>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($anggota as $s): ?>
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900 dark:text-white">
                                    <?= esc($s['nama']) ?>
                                    <span class="lencana <?= $s['peran'] === 'pengaju' ? 'lencana-merek' : 'lencana-abu' ?>"><?= $s['peran'] === 'pengaju' ? 'Pengaju' : 'Teman' ?></span>
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Kelas <?= esc($s['nama_kelas'] ?? '—') ?> · NIS <?= esc($s['nis'] ?: '—') ?> · NISN <?= esc($s['nisn'] ?: '—') ?></p>
                            </div>
                            <p class="text-sm text-gray-500 sm:text-right dark:text-gray-400">
                                HP <b class="font-semibold text-gray-900 dark:text-white"><?= esc(($s['hp'] ?? '') !== '' ? $s['hp'] : (($s['hp_master'] ?? '') !== '' ? $s['hp_master'] : '—')) ?></b>
                                <?php if (($s['hp'] ?? '') === '' && ($s['hp_master'] ?? '') !== ''): ?><br><span class="text-xs">(dari Master Siswa)</span><?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <!-- Kolom kanan: aksi + riwayat -->
        <div class="space-y-4">
            <section class="kartu overflow-hidden" aria-labelledby="judul-keputusan">
                <?= $kepalaKartu('Keputusan', 'judul-keputusan') ?>
                <div class="kartu-isi flex flex-col gap-2 !p-4">
                    <?php if ($bolehAcc && in_array($status, ['menunggu', 'perbaikan', 'ditolak'], true)): ?>
                        <button type="button" class="btn btn-sukses w-full" data-buka-dialog="pkl-acc"><?= ikon('check', 'h-4 w-4') ?><?= $status === 'ditolak' ? 'ACC (hidupkan kembali)' : 'ACC' ?></button>
                    <?php endif; ?>
                    <?php if ($status === 'menunggu' && ! empty($bolehUbah)): ?>
                        <button type="button" class="btn btn-utama w-full" data-buka-dialog="pkl-kembalikan"><?= ikon('file-pen', 'h-4 w-4') ?>Kembalikan untuk diperbaiki</button>
                    <?php endif; ?>
                    <?php if ($bolehAcc && in_array($status, ['menunggu', 'perbaikan'], true)): ?>
                        <button type="button" class="btn btn-bahaya w-full" data-buka-dialog="pkl-tolak"><?= ikon('close', 'h-4 w-4') ?>Tolak</button>
                    <?php endif; ?>
                    <?php if ($bolehAcc && $status === 'disetujui'): ?>
                        <button type="button" class="btn btn-netral w-full text-red-700 dark:text-red-400" data-buka-dialog="pkl-batal"><?= ikon('close-circle', 'h-4 w-4') ?>Batalkan persetujuan</button>
                    <?php endif; ?>
                    <?php if (! empty($bolehUbah) && ($status !== 'disetujui' || $bolehAcc)): ?>
                        <a href="<?= site_url('admin/pkl/' . $id . '/ubah') ?>" class="btn btn-netral w-full"><?= ikon('edit', 'h-4 w-4') ?>Ubah data langsung</a>
                    <?php endif; ?>
                    <?php if (! $bolehAcc): ?>
                        <?= $catatanKunci('<b>ACC, tolak, dan batalkan persetujuan hanya untuk peran yang diberi hak ACC ajuan PKL oleh Admin</b> (sekarang: ' . esc(\App\Libraries\PklHak::teksPemegang('acc')) . ').'
                            . (! empty($bolehUbah) ? ' Anda bisa memeriksa, mengembalikan ajuan untuk diperbaiki' . ($status === 'disetujui' ? '' : ', dan mengubah datanya') . '.' : '')
                            . (! empty($bolehSurat) ? ' Surat diunduh dan biayanya dicatat oleh Anda setelah ajuan disetujui.' : '')) ?>
                    <?php endif; ?>
                    <?php if ($bolehHapus && ($status !== 'disetujui' || \App\Libraries\PklHak::boleh($peran, 'hapus_disetujui'))): ?>
                        <button type="button" class="btn btn-teks btn-sm mt-1 w-full text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700" data-buka-dialog="pkl-hapus"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus ajuan ini</button>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($status === 'disetujui' && ! empty($a['acc_at'])): $peranAcc = (string) ($a['acc_peran'] ?? ''); ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-acc">
                    <div class="kartu-kepala">
                        <h3 id="judul-acc" class="kartu-judul inline-flex items-center gap-2 text-base"><?= ikon('check-circle', 'h-5 w-5 text-green-600 dark:text-green-400') ?>Persetujuan (ACC)</h3>
                    </div>
                    <dl class="kartu-isi space-y-2.5 !p-4 text-sm">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Disetujui oleh</dt><dd class="font-semibold text-gray-900 dark:text-white"><?= esc($a['acc_nama'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Sebagai</dt><dd class="font-semibold text-gray-900 dark:text-white"><?= esc(match ($peranAcc) {
                            'impor' => 'Data riwayat (diimpor, bukan ACC sistem)', '' => 'Tidak tercatat', default => \App\Libraries\HakAkses::label($peranAcc),
                        }) ?></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Waktu</dt><dd class="font-semibold text-gray-900 dark:text-white"><?= esc(PklSurat::waktuIndo((string) $a['acc_at'])) ?></dd></div>
                        <?php if (! empty($a['acc_kode'])): ?><div><dt class="text-xs text-gray-500 dark:text-gray-400">Kode verifikasi</dt><dd class="font-mono text-xs font-bold text-gray-700 dark:text-gray-300"><?= esc($a['acc_kode']) ?></dd></div><?php endif; ?>
                        <?php if ($peranAcc === 'impor'): ?>
                            <div class="pt-1"><?= ui_peringatan('awas', 'Data ini diimpor dari riwayat lama, bukan persetujuan lewat sistem.') ?></div>
                        <?php endif; ?>
                    </dl>
                </section>
            <?php endif; ?>

            <?php if ($status === 'disetujui'): ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-surat-pkl">
                    <?= $kepalaKartu('Surat permohonan', 'judul-surat-pkl') ?>
                    <div class="kartu-isi space-y-3 !p-4 text-sm">
                        <?php if (trim((string) ($p['waka_hubin_nama'] ?? '')) === ''): ?>
                            <?= ui_peringatan('galat', '', ['html' => 'Nama Waka Hubin belum diisi, jadi di bawah tanda tangan surat hanya titik-titik. '
                                . ($bolehPengaturan ? '<a href="' . site_url('admin/pkl/pengaturan#surat') . '" class="font-semibold underline">Isi di Pengaturan PKL</a>, lalu unduh surat lagi (nomor tetap sama).' : 'Minta Operator mengisinya di Pengaturan PKL.')]) ?>
                        <?php endif; ?>
                        <?php if ($surat): ?>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Nomor surat</p>
                                <p class="font-mono font-bold text-gray-900 dark:text-white"><?= esc($surat['nomor']) ?></p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tanggal <?= esc(IsianBantu::tanggalIndo($surat['tanggal_surat'])) ?> · diunduh <?= (int) $surat['cetak_ke'] ?> kali</p>
                            </div>
                            <?php if ($perluUlang): ?>
                                <?= ui_peringatan('awas', '', ['html' => '<b>Perlu cetak ulang</b> — data ajuan berubah sejak surat terakhir diunduh. Nomor tetap sama.']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-gray-500 dark:text-gray-400">Surat belum diterbitkan. Nomor ditetapkan sekali, saat surat pertama diunduh.</p>
                        <?php endif; ?>
                        <?php if (! empty($bolehSurat)): ?>
                            <div class="flex flex-col gap-2">
                                <button type="button" class="btn btn-utama w-full" data-pkl-unduh-ajuan="<?= $id ?>"><?= ikon('download', 'h-4 w-4') ?><?= $surat ? (! empty($perluUlang) ? 'Unduh surat terbaru' : 'Unduh surat') : 'Terbitkan nomor &amp; unduh surat' ?></button>
                                <?php if ($surat): ?>
                                    <button type="button" class="btn btn-netral btn-sm w-full" data-pkl-unduh-ajuan="<?= $id ?>" data-pkl-unduh-paksa><?= ikon('plus', 'h-4 w-4') ?>Catat biaya lain, lalu unduh</button>
                                    <button type="button" class="btn btn-sukses w-full" data-pkl-wa-ajuan="<?= $id ?>"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kabari siswa via WhatsApp</button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?= $catatanKunci('Surat diunduh oleh peran yang diberi hak itu oleh Admin (' . esc(\App\Libraries\PklHak::teksPemegang('surat')) . '), supaya jelas siapa yang menyetujui dan siapa yang mencetak.') ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if (isset($pembayaran) && $pembayaran !== null): ?>
                    <section class="kartu overflow-hidden" aria-labelledby="judul-bayar">
                        <?= $kepalaKartu('Pembayaran siswa', 'judul-bayar') ?>
                        <?php if (! array_filter($pembayaran, static fn (array $x) => $x['pembayaran'] !== [] || $x['beasiswa'] !== null || $x['keringanan'] !== [])): ?>
                            <p class="px-4 py-5 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada biaya yang dicatat. Dicatat saat surat diunduh.</p>
                        <?php endif; ?>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($pembayaran as $x): if ($x['pembayaran'] === [] && $x['beasiswa'] === null && $x['keringanan'] === []) { continue; } ?>
                                <li class="px-4 py-3.5 text-sm">
                                    <p class="font-semibold text-gray-900 dark:text-white"><?= esc($x['nama']) ?> <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($x['kelas']) ?></span></p>
                                    <?php if ($x['beasiswa'] !== null): ?>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium <?= $x['beasiswa']['berlaku'] ? 'text-brand-700 dark:text-brand-400' : 'text-gray-400 line-through dark:text-gray-500' ?>">
                                            <?= ikon('award', 'h-4 w-4 shrink-0') ?>Beasiswa 3 tahun — <?= esc($x['beasiswa']['sumber_label']) ?><?= ! empty($x['beasiswa']['berakhir_at']) ? ' s.d. ' . esc(IsianBantu::tanggalIndo($x['beasiswa']['berakhir_at'])) : '' ?> (SPP dibebaskan)
                                            <?php if (! empty($bolehSurat)): ?>
                                                <button type="button" class="font-semibold text-red-600 no-underline hover:underline dark:text-red-400" data-buka-dialog="pkl-cabutbeasiswa"
                                                        data-dialog-isi="<?= esc((string) json_encode(['siswa_id' => (int) $x['siswa_id'], 'teks' => $x['nama']]), 'attr') ?>">cabut</button>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($x['pembayaran'] !== []): ?>
                                        <ul class="mt-1.5 space-y-1">
                                            <?php foreach ($x['pembayaran'] as $b): ?>
                                                <li class="flex items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
                                                    <span><?= esc($b['nama']) ?> <span class="text-gray-500 dark:text-gray-400">· <?= esc($b['periode_label']) ?> · <?= esc($b['oleh']) ?>, <?= esc(date('d-m-Y H:i', strtotime($b['waktu']))) ?></span></span>
                                                    <span class="flex shrink-0 items-center gap-1.5"><b class="text-gray-900 dark:text-white"><?= esc(\App\Libraries\PklBiaya::rupiah((int) $b['nominal'])) ?></b>
                                                        <?php if (! empty($bolehSurat)): ?>
                                                            <button type="button" class="tombol-tutup h-6 w-6 hover:bg-red-50 hover:text-red-600 dark:hover:bg-gray-700" title="Hapus catatan (salah centang)" aria-label="Hapus catatan <?= esc($b['nama'], 'attr') ?>"
                                                                    data-buka-dialog="pkl-hapusbayar" data-dialog-isi="<?= esc((string) json_encode(['pembayaran_id' => (int) $b['id'], 'teks' => $x['nama'] . ' — ' . $b['nama'] . ' ' . $b['periode_label']]), 'attr') ?>"><?= ikon('close', 'h-3.5 w-3.5') ?></button>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p class="mt-1.5 border-t border-gray-200 pt-1.5 text-right text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">Total dibayar: <b class="text-gray-900 dark:text-white"><?= esc(\App\Libraries\PklBiaya::rupiah((int) $x['total'])) ?></b></p>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($x['keringanan'], 0, 1) as $k): ?>
                                        <p class="mt-1.5 rounded-lg bg-yellow-50 px-3 py-1.5 text-xs text-yellow-800 dark:bg-gray-700 dark:text-yellow-300"><b>Keringanan/ditunda:</b> <?= esc($k['alasan']) ?> — <?= esc($k['oleh']) ?>, <?= esc(date('d-m-Y', strtotime($k['waktu']))) ?></p>
                                    <?php endforeach; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (! empty($bolehLaporan)): ?>
                            <div class="kartu-kaki text-right"><a href="<?= site_url('admin/pkl/laporan') ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">Buka Laporan Pembayaran<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

            <?php $suratSekolah ??= []; $bolehSuratSekolah ??= false; $lapor ??= null; $bolehBuatSurat ??= []; ?>
            <?php if ($suratSekolah !== [] || $lapor !== null || ($bolehSuratSekolah && in_array($status, ['menunggu', 'perbaikan', 'disetujui'], true))): ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-surat-sekolah">
                    <?= $kepalaKartu('Surat sekolah', 'judul-surat-sekolah') ?>
                    <div class="kartu-isi space-y-3 !p-4 text-sm">
                        <?php if ($lapor !== null): [$lpTeks, $lpWarna] = \App\Libraries\PklLapor::TAMPIL[$lapor['status']] ?? [$lapor['status'], 'lencana-abu']; ?>
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-gray-700 dark:border-blue-800 dark:bg-gray-700 dark:text-gray-300">
                                <p class="flex flex-wrap items-center gap-2"><b>Lapor diterima dari siswa</b><span class="lencana <?= $lpWarna ?>"><?= esc($lpTeks) ?></span></p>
                                <p class="mt-0.5">Periode <?= esc(\App\Libraries\SuratPkl::periodeTeks((string) $lapor['tanggal_mulai'], (string) $lapor['tanggal_selesai'])) ?> · oleh <?= esc((string) $lapor['pelapor_nama']) ?></p>
                                <?php if ($lapor['status'] === 'dikembalikan' && (string) ($lapor['alasan'] ?? '') !== ''): ?><p class="mt-0.5 text-red-700 dark:text-red-400">Alasan dikembalikan: <?= esc((string) $lapor['alasan']) ?></p><?php endif; ?>
                                <?php if ($lapor['status'] === 'baru' && ! empty($bolehKotakLapor)): ?><a href="<?= site_url('admin/surat/balasan/laporan') ?>" class="mt-1 inline-flex items-center gap-1 font-semibold text-brand-700 hover:underline dark:text-brand-400">Proses di kotak Lapor Diterima PKL<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($suratSekolah !== []): ?>
                            <ul class="space-y-2">
                                <?php foreach ($suratSekolah as $ss): [$stTeks, $stWarna] = \App\Models\SuratSekolahModel::TAMPIL_STATUS[$ss['status']] ?? [$ss['status'], 'lencana-abu']; ?>
                                    <li>
                                        <a href="<?= site_url('admin/surat/' . (int) $ss['id']) ?>" class="block rounded-lg border border-gray-200 px-3 py-2 text-xs transition-colors hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                                            <span class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-900 dark:text-white"><?= esc(\App\Libraries\SuratJenis::singkat((string) $ss['jenis'])) ?></span><span class="lencana <?= $stWarna ?>"><?= esc($stTeks) ?></span></span>
                                            <span class="mt-0.5 block font-mono text-gray-500 dark:text-gray-400"><?= esc((string) ($ss['nomor'] ?? '') !== '' ? $ss['nomor'] : \App\Models\SuratSekolahModel::kode((int) $ss['id'])) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($bolehSuratSekolah && in_array($status, ['menunggu', 'perbaikan', 'disetujui'], true)): ?>
                            <div class="flex flex-col gap-2">
                                <?php if ($status === 'disetujui' && ! empty($bolehBuatSurat[\App\Libraries\SuratJenis::BALASAN])): ?>
                                    <a href="<?= site_url('admin/surat/balasan') . ($lapor !== null && $lapor['status'] === 'baru' ? '?lapor=' . (int) $lapor['id'] : '?ajuan=' . $id) ?>" class="btn btn-netral w-full"><?= ikon('envelope', 'h-4 w-4') ?>Buat Surat Balasan</a>
                                <?php endif; ?>
                                <?php if ($status === 'disetujui' && ! empty($bolehBuatSurat[\App\Libraries\SuratJenis::PENARIKAN])): ?>
                                    <a href="<?= site_url('admin/surat/penarikan?ajuan=' . $id) ?>" class="btn btn-netral w-full"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Buat Surat Penarikan</a>
                                <?php endif; ?>
                                <?php if (! empty($bolehBuatSurat[\App\Libraries\SuratJenis::PERNYATAAN])): ?>
                                    <a href="<?= site_url('admin/surat/pernyataan-ortu?ajuan=' . $id) ?>" class="btn btn-netral w-full"><?= ikon('file-pen', 'h-4 w-4') ?>Cetak Pernyataan Orang Tua</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="kartu overflow-hidden" aria-labelledby="judul-riwayat">
                <?= $kepalaKartu('Riwayat', 'judul-riwayat') ?>
                <?php if ($riwayat === []): ?>
                    <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada catatan.</p>
                <?php else: ?>
                    <ol class="px-4 py-4 sm:px-6">
                        <?php foreach ($riwayat as $i => $r):
                            $titik = $r['aksi'] === 'acc' ? 'bg-green-500' : (in_array($r['aksi'], ['tolak', 'batal_acc'], true) ? 'bg-red-500' : ($r['aksi'] === 'kembalikan' ? 'bg-yellow-400' : 'bg-brand-500')); ?>
                            <li class="relative ps-5 pb-4 last:pb-0">
                                <span class="absolute start-0 top-1.5 h-2.5 w-2.5 rounded-full <?= $titik ?>" aria-hidden="true"></span>
                                <?php if ($i < count($riwayat) - 1): ?><span class="absolute start-[4px] top-4 h-full w-px bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span><?php endif; ?>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($labelAksi[$r['aksi']] ?? $r['aksi']) ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['oleh']) ?><?= $r['peran'] ? ' · ' . esc(HakAkses::label($r['peran'])) : '' ?> · <?= esc(date('d-m-Y H:i', strtotime($r['created_at']))) ?></p>
                                <?php if (! empty($r['catatan'])): ?><p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">“<?= esc($r['catatan']) ?>”</p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<!-- ============ Dialog-dialog keputusan (dialog kit, dibuka tombol data-buka-dialog) ============ -->
<?php
$dialog = static function (string $nama, string $judul, string $aksiUrl, string $isi, string $tombol, string $kelasTombol): string {
    return ui_dialog_buka(['nama' => 'pkl-' . $nama, 'judul' => $judul])
        . '<form method="post" action="' . esc(site_url('admin/pkl/' . $aksiUrl), 'attr') . '">' . csrf_field()
        . '<div class="dialog-isi text-sm text-gray-600 dark:text-gray-300">' . $isi . '</div>'
        . '<div class="dialog-kaki"><button type="button" class="btn btn-netral" @click="tutup()">Batal</button>'
        . '<button type="submit" class="btn ' . $kelasTombol . '">' . $tombol . '</button></div></form>'
        . ui_dialog_tutup();
};
$kotakAlasan = static fn (string $idIsian, string $ph): string => '<div><label class="lbl" for="' . $idIsian . '">Alasan <span class="text-red-600">*</span></label>'
    . '<textarea id="' . $idIsian . '" name="catatan" rows="3" required minlength="5" maxlength="255" class="inp" placeholder="' . esc($ph, 'attr') . '"></textarea>'
    . '<p class="hint">Minimal 5 huruf. Siswa akan membaca catatan ini.</p></div>';
$kotakPaham = static fn (string $teks): string => '<label class="flex cursor-pointer items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-gray-700 dark:text-red-400">'
    . '<input type="checkbox" name="paham" value="1" required class="cek mt-0.5"><span class="text-sm font-semibold">' . $teks . '</span></label>';

// ----- ACC -----
$isiAcc = '<p>Ajuan <b>' . esc($kode) . '</b> — ' . esc($a['perusahaan_nama']) . ' (' . count($anggota) . ' siswa) akan disetujui dan seluruh siswanya <b>terkunci</b> (tak bisa mengajukan lagi).</p>';
if ($master['persis'] !== null) {
    $isiAcc .= ui_peringatan('sukses', '', ['html' => 'Perusahaan ini sudah ada di master (<b>' . esc($master['persis']['nama']) . '</b>) — akan ditautkan otomatis.']);
} elseif ($master['mirip'] !== []) {
    $isiAcc .= '<fieldset class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-gray-700"><legend class="px-1 text-xs font-bold text-yellow-800 dark:text-yellow-300">Ada perusahaan terdaftar yang mirip — pilih:</legend>'
        . '<label class="mt-1 flex cursor-pointer items-start gap-2 text-gray-900 dark:text-white"><input type="radio" name="perusahaan_id" value="" checked class="cek mt-0.5"><span><b>Daftarkan sebagai perusahaan baru</b><br><span class="text-xs text-gray-500 dark:text-gray-400">' . esc($a['perusahaan_nama']) . '</span></span></label>';
    foreach ($master['mirip'] as $m) {
        $isiAcc .= '<label class="mt-2 flex cursor-pointer items-start gap-2 text-gray-900 dark:text-white"><input type="radio" name="perusahaan_id" value="' . (int) $m['id'] . '" class="cek mt-0.5"><span><b>Sama dengan:</b> ' . esc($m['nama']) . '<br><span class="text-xs text-gray-500 dark:text-gray-400">' . esc(trim(($m['kota'] ?? '') . ' ' . ($m['alamat'] ?? ''))) . '</span></span></label>';
    }
    $isiAcc .= '</fieldset>';
} else {
    $isiAcc .= '<p class="hint">Perusahaan ini akan didaftarkan ke master dan menjadi saran nama untuk siswa lain — pastikan ejaannya sudah benar.</p>';
}
if ($adaBahaya) {
    $isiAcc .= $kotakPaham('Ada peringatan BAHAYA di halaman ini. Saya sudah memeriksanya dan tetap ingin meng-ACC.');
}
$isiAcc .= ui_peringatan('netral', '', ['html' => 'Persetujuan ini akan <b>tercatat atas nama ' . esc($namaSaya) . '</b> (' . esc(HakAkses::label($peran)) . ') lengkap dengan tanggal &amp; jam, dan tercetak di kaki surat permohonan.']);
echo $dialog('acc', 'ACC ajuan ' . $kode . '?', $id . '/acc', $isiAcc, ikon('check', 'h-4 w-4') . 'Ya, ACC', 'btn-sukses');

echo $dialog('kembalikan', 'Kembalikan untuk diperbaiki', $id . '/kembalikan',
    '<p>Siswa pengaju akan membuka ajuannya lagi (dengan nomor HP-nya) dan memperbaikinya, lalu mengirim ulang untuk diputuskan (ACC).</p>' . $kotakAlasan('alasan-kembalikan', 'Contoh: Alamat perusahaan belum lengkap, tambahkan nomor gedung dan kode pos.'),
    ikon('file-pen', 'h-4 w-4') . 'Kembalikan', 'btn-utama');
echo $dialog('tolak', 'Tolak ajuan ' . $kode, $id . '/tolak',
    '<p>Seluruh siswa dalam ajuan ini <b>dibebaskan</b> dan boleh mengajukan baru dari awal.</p>' . $kotakAlasan('alasan-tolak', 'Contoh: Perusahaan tidak menerima siswa PKL pada periode ini.'),
    ikon('close', 'h-4 w-4') . 'Tolak', 'btn-bahaya');
echo $dialog('batal', 'Batalkan persetujuan ' . $kode, $id . '/batal-acc',
    ui_peringatan('awas', '', ['html' => 'Gunakan bila <b>perusahaan menarik diri</b>. Ajuan kembali ke status <b>Perlu perbaikan</b> supaya siswa bisa menggantinya; siswa tetap terkunci sampai mereka memperbaiki atau Anda menolak.'])
        . $kotakAlasan('alasan-batal', 'Contoh: Perusahaan membatalkan penerimaan karena kuota penuh.'),
    'Ya, batalkan persetujuan', 'btn-bahaya');
if (! empty($bolehSurat)) {
    echo $dialog('hapusbayar', 'Hapus catatan biaya?', $id . '/pembayaran/hapus',
        '<input type="hidden" name="pembayaran_id" data-isi-pemicu="pembayaran_id">'
        . '<p>Catatan <b data-isi-pemicu="teks"></b> akan <b>dihapus</b> (misalnya karena salah centang). Tercatat di riwayat ajuan dan Audit Log.</p>'
        . '<div><label class="lbl" for="alasanBayar">Alasan koreksi <span class="text-red-600">*</span></label><textarea id="alasanBayar" name="alasan" rows="2" required minlength="5" maxlength="200" class="inp" placeholder="Contoh: Salah centang, uang SPP belum diterima."></textarea></div>',
        ikon('trash-bin', 'h-4 w-4') . 'Hapus catatan', 'btn-bahaya');
    echo $dialog('cabutbeasiswa', 'Cabut beasiswa?', $id . '/pembayaran/beasiswa-cabut',
        '<input type="hidden" name="siswa_id" data-isi-pemicu="siswa_id">'
        . '<p>Beasiswa <b data-isi-pemicu="teks"></b> akan <b>dicabut</b>; SPP-nya kembali wajib dibayar.</p>'
        . '<div><label class="lbl" for="alasanBeasiswa">Alasan <span class="text-red-600">*</span></label><textarea id="alasanBeasiswa" name="alasan" rows="2" required minlength="5" maxlength="200" class="inp" placeholder="Contoh: Salah memilih beasiswa pada siswa ini."></textarea></div>',
        'Cabut beasiswa', 'btn-bahaya');
}
if ($bolehHapus) {
    $isiHapus = '<p>Ajuan <b>' . esc($kode) . '</b> beserta riwayatnya akan <b>dihapus permanen</b> dan seluruh siswanya dibebaskan. Ringkasannya tetap tercatat di Audit Log.</p><p class="hint">Bila ajuan hanya salah/ditolak, lebih aman memakai <b>Tolak</b> agar jejaknya tetap ada.</p>';
    if ($status === 'disetujui') {
        $isiHapus .= $kotakPaham('Ajuan ini SUDAH DISETUJUI. Saya yakin ingin menghapusnya.');
    }
    echo $dialog('hapus', 'Hapus ajuan ' . $kode . '?', 'hapus/' . $id, $isiHapus, ikon('trash-bin', 'h-4 w-4') . 'Hapus permanen', 'btn-bahaya');
}
?>

<?php if (! empty($bolehSurat) && $status === 'disetujui'): // kotak dialog catat biaya + kabar WhatsApp ?>
    <?= view('admin/pkl/_unduh') ?>
<?php endif; ?>

<?= $this->endSection() ?>
