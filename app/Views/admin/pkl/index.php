<?php
/**
 * Beranda PKL (staf). Rapi tampilan U2 (2026-10-26, docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md): menjadi **"Tugas hari ini"** — SATU daftar
 * urut pekerjaan yang jumlahnya > 0 (tiap baris: jumlah + kalimat + satu tombol), lalu status form siswa, antrean ajuan, dan ringkasan
 * angka di bawah (angka yang sudah tampil sebagai tugas tidak diulang). Data & hak SAMA dengan sebelumnya (controller tidak diubah).
 * Sebelumnya (F9): kartu angka kit, ui_peringatan, kartu Persiapan, <progress class="progres">, tombol Salin = kit "salinTeks".
 *
 * @var array<string,int>        $hitung    jumlah ajuan per status
 * @var array<string,int|float>  $siswa     ringkasan siswa (sudah/belum mengisi, sudah/belum PKL)
 * @var list<array>              $antrean   ajuan menunggu tertua
 * @var ?string                  $alasan    null = form terbuka; selain itu alasan tertutup
 * @var string                   $tautan    alamat form siswa
 * @var list<string>             $tingkat
 * @var int                      $terlambat ajuan menunggu yang melewati batas keputusan ACC
 * @var int                      $laporBaru laporan "sudah diterima perusahaan" yang belum diproses
 * @var int                      $berakhir7 siswa yang PKL-nya berakhir ≤ 7 hari & belum diperpanjang (0 = bukan pembuat Surat Perpanjangan)
 * @var array<string,int>|null   $suratRingkas belum_bernomor, belum_dikabari, uang_bulan_ini (hak surat/laporan)
 * @var array<string,int>|null   $suratSekolah hitungan surat sekolah per status + belum_unduh
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, $p (pengaturan PKL), $batasHari, $bolehPengaturan, $bolehAcc, $bolehSurat,
 * $bolehLaporan, $bolehHapus, $bolehTtd, $peran, $peranLabel.
 */
$pesanTutup = [
    'belum_dibuka'  => 'Form siswa belum dibuka.',
    'sudah_ditutup' => 'Batas waktu pengisian sudah lewat — form tertutup otomatis.',
    'belum_siap'    => 'Pengaturan belum lengkap (tingkat atau pagar tanggal belum diisi), jadi form belum bisa dibuka.',
];
$hubin = ! empty($bolehAcc); // peran ini bisa memutuskan (ACC) ajuan — diatur Admin di Sistem → Hak Akses

// ---- Tugas hari ini: [ikon, warna ikon, judul, keterangan, alamat, tombol] — hanya yang jumlahnya > 0, urut kepentingan.
$warna = [
    'kuning' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/60 dark:text-yellow-300',
    'merah'  => 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300',
    'hijau'  => 'bg-green-100 text-green-700 dark:bg-green-900/60 dark:text-green-300',
    'merek'  => 'bg-brand-100 text-brand-700 dark:bg-gray-700 dark:text-brand-300',
];
$tugas = [];
$nMenunggu = (int) ($hitung['menunggu'] ?? 0);
if ($nMenunggu > 0) {
    $tugas[] = ['inbox', ($terlambat ?? 0) > 0 ? 'merah' : 'kuning',
        $nMenunggu . ' ajuan PKL ' . ($hubin ? 'menunggu keputusan Anda' : 'menunggu ACC'),
        ($terlambat ?? 0) > 0 ? (int) $terlambat . ' ajuan sudah lewat batas ' . (int) $batasHari . ' hari — segera diputuskan.' : 'Yang paling lama menunggu ada di atas.',
        site_url('admin/pkl/daftar/menunggu'), $hubin ? 'Putuskan' : 'Periksa'];
}
if (! empty($laporBaru)) {
    $tugas[] = ['clipboard-check', 'kuning', (int) $laporBaru . ' laporan siswa "sudah diterima perusahaan"', 'Buatkan Surat Balasan PKL dari laporan itu.',
        site_url('admin/surat/balasan/laporan'), 'Proses'];
}
if (! empty($suratSekolah) && (int) $suratSekolah['menunggu'] > 0) {
    $tugas[] = ['envelope', 'kuning', (int) $suratSekolah['menunggu'] . ' surat sekolah menunggu ACC', ! empty($bolehAcc) ? 'Periksa lalu ACC.' : 'Menunggu keputusan pemegang hak ACC.',
        site_url('admin/surat?status=menunggu'), ! empty($bolehAcc) ? 'Periksa' : 'Lihat'];
}
if (! empty($suratSekolah) && (int) $suratSekolah['dikembalikan'] > 0) {
    $tugas[] = ['exclamation-circle', 'merah', (int) $suratSekolah['dikembalikan'] . ' surat sekolah dikembalikan', 'Perbaiki datanya lalu ajukan ulang untuk ACC.',
        site_url('admin/surat?status=dikembalikan'), 'Perbaiki'];
}
if (! empty($suratSekolah) && (int) $suratSekolah['belum_unduh'] > 0) {
    $tugas[] = ['download', 'hijau', (int) $suratSekolah['belum_unduh'] . ' surat sekolah siap diunduh', 'Sudah di-ACC; nomor surat terbit saat diunduh.',
        site_url('admin/surat?status=disetujui'), 'Unduh'];
}
if (! empty($suratRingkas) && ! empty($bolehSurat) && (int) $suratRingkas['belum_bernomor'] > 0) {
    $tugas[] = ['file-lines', 'hijau', (int) $suratRingkas['belum_bernomor'] . ' Surat Izin PKL belum diunduh', 'Ajuan sudah disetujui, suratnya belum terbit.',
        site_url('admin/pkl/daftar/disetujui'), 'Unduh'];
}
if (! empty($suratRingkas) && ! empty($bolehSurat) && (int) $suratRingkas['belum_dikabari'] > 0) {
    $tugas[] = ['whatsapp', 'hijau', (int) $suratRingkas['belum_dikabari'] . ' siswa belum dikabari WhatsApp', 'Surat sudah terbit, siswa belum diberi tahu.',
        site_url('admin/pkl/daftar/disetujui'), 'Kabari'];
}
if (! empty($berakhir7)) {
    $tugas[] = ['calendar-edit', 'merek', (int) $berakhir7 . ' siswa PKL-nya berakhir dalam 7 hari', 'Belum ada surat perpanjangan. Buatkan bila perusahaan memperpanjang PKL-nya.',
        site_url('admin/surat/perpanjangan'), 'Lihat'];
}
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/pkl/baru') ?>" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Isi atas nama</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_beranda_v4',
    'helpTitle' => 'Beranda PKL',
    'helpBody'  => '<p>Siswa mengisi ajuan PKL sendiri lewat tautan di HP. Di sini ajuan <b>diperiksa</b> lalu diputuskan. <b>ACC dan tolak dilakukan peran yang diberi hak ACC ajuan PKL</b> oleh Admin (sekarang: ' . esc(\App\Libraries\PklHak::teksPemegang('acc')) . '; diatur di Sistem → Hak Akses); peran yang memegang hak periksa bisa <b>mengembalikan</b> ajuan agar siswa memperbaiki.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Tugas hari ini</b> — daftar pekerjaan yang perlu dikerjakan sekarang (hanya yang ada). Tekan tombol di kanan untuk langsung mengerjakannya.</li>'
        . '<li><b>Antrean</b> — ajuan yang menunggu keputusan, yang paling lama di atas.</li>'
        . '<li><b>Ringkasan</b> (paling bawah) — jumlah ajuan per status dan kemajuan siswa (sudah/belum mengisi, sudah/belum PKL).</li>'
        . '<li><b>Isi atas nama</b> (tombol di kanan atas) — bila siswa tak bisa mengisi sendiri, atau untuk memasukkan data PKL lama.</li>'
        . '<li><b>Persiapan</b> (kartu berlencana kuning, bagi pemegang hak Pengaturan PKL) — daftar yang harus siap sebelum tautan dibagikan. Hilang sendiri bila semuanya siap.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <?php
    // Daftar persiapan: hanya untuk yang boleh membuka Pengaturan, dan hanya selama ada yang belum siap.
    $wakaOk  = trim((string) ($p['waka_hubin_nama'] ?? '')) !== '';
    $pagarOk = $alasan !== 'belum_siap';
    $bukaOk  = $alasan === null;
    $persiapan = [
        [$pagarOk, 'Tingkat kelas yang boleh mengajukan dipilih', 'Tanpa ini form belum bisa dibuka.', site_url('admin/pkl/pengaturan')],
        [$wakaOk, 'Nama Waka Hubin (penanda tangan surat) diisi', 'Tanpa ini nama di bawah tanda tangan surat hanya titik-titik.', site_url('admin/pkl/pengaturan#surat')],
        [$bukaOk, 'Form dibuka untuk siswa', 'Siswa baru bisa mengajukan setelah form dibuka.', site_url('admin/pkl/pengaturan')],
    ];
    $siap = count(array_filter(array_column($persiapan, 0)));
    ?>
    <?php if ($bolehPengaturan && $siap < count($persiapan)): ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-persiapan">
            <div class="kartu-kepala">
                <h2 id="judul-persiapan" class="kartu-judul text-base">Persiapan sebelum tautan dibagikan ke siswa</h2>
                <span class="lencana lencana-kuning"><?= $siap ?> / <?= count($persiapan) ?> siap</span>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($persiapan as [$ok, $judul, $ket, $url]): ?>
                    <li>
                        <a href="<?= esc($url, 'attr') ?>" class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-50 sm:px-6 dark:hover:bg-gray-700/60">
                            <?= $ok ? ikon('check-circle', 'mt-0.5 h-5 w-5 shrink-0 text-green-600 dark:text-green-400') : ikon('exclamation-circle', 'mt-0.5 h-5 w-5 shrink-0 text-yellow-500 dark:text-yellow-300') ?>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium <?= $ok ? 'text-gray-500 line-through dark:text-gray-400' : 'text-gray-900 dark:text-white' ?>"><?= esc($judul) ?></span>
                                <?php if (! $ok): ?><span class="block text-sm text-gray-500 dark:text-gray-400"><?= esc($ket) ?></span><?php endif; ?>
                            </span>
                            <?php if (! $ok): ?><span class="inline-flex shrink-0 items-center gap-1 self-center text-sm font-medium text-brand-700 dark:text-brand-400">Atur<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Tugas hari ini -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-tugas">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 id="judul-tugas" class="kartu-judul">Tugas hari ini</h2>
                <p class="kartu-ket">Pekerjaan yang perlu dikerjakan sekarang. Tekan tombol di kanan untuk membukanya.</p>
            </div>
            <?php if ($tugas !== []): ?><span class="lencana lencana-kuning shrink-0"><?= count($tugas) ?> tugas</span><?php endif; ?>
        </div>
        <?php if ($tugas === []): ?>
            <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Tidak ada tugas saat ini', 'teks' => 'Semua pekerjaan PKL dan surat sudah beres.']) ?>
        <?php else: ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700" data-tugas>
                <?php foreach ($tugas as [$ikon, $w, $judul, $ket, $url, $tombol]): ?>
                    <li>
                        <a href="<?= esc($url, 'attr') ?>" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 sm:px-6 dark:hover:bg-gray-700/60">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $warna[$w] ?>"><?= ikon($ikon, 'h-5 w-5', $ikon === 'whatsapp' ? 'solid' : 'outline') ?></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?></span>
                                <span class="block text-sm text-gray-500 dark:text-gray-400"><?= esc($ket) ?></span>
                            </span>
                            <span class="btn btn-netral btn-sm shrink-0"><?= esc($tombol) ?><?= ikon('chevron-right', 'h-3.5 w-3.5') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php
    // Status form siswa: hijau = terbuka, kuning = tertutup (beserta alasannya).
    $terbuka = $alasan === null;
    $isiForm = '<p>' . ($terbuka
            ? 'Tingkat <b>' . esc(implode(' & ', $tingkat)) . '</b> · maks <b>' . (int) \App\Models\PklPengaturanModel::maksSiswa($p) . '</b> siswa per ajuan · keputusan ACC paling lambat <b>' . (int) $batasHari . ' hari</b>'
                . (! empty($p['form_tutup']) ? ' · tutup otomatis <b>' . esc(date('d-m-Y H:i', strtotime($p['form_tutup']))) . '</b>' : '')
            : esc($pesanTutup[$alasan] ?? '')) . '</p>'
        . '<p class="mt-1 break-all font-mono text-xs">' . esc($tautan) . '</p>'
        . '<div class="mt-3 flex flex-wrap gap-2">'
        . '<button type="button" class="btn btn-netral btn-sm" x-data="salinTeks" data-teks="' . esc($tautan, 'attr') . '" data-pesan="Tautan form siswa tersalin." @click="salin()">'
        . ikon('file-copy', 'h-4 w-4') . '<span x-show="!tersalin">Salin tautan</span><span x-show="tersalin" x-cloak>Tautan disalin</span></button>'
        . ($bolehPengaturan ? '<a href="' . site_url('admin/pkl/pengaturan') . '" class="btn btn-netral btn-sm">' . ikon('cog', 'h-4 w-4') . ($terbuka ? 'Atur / tutup form' : 'Buka lewat Pengaturan') . '</a>' : '')
        . '</div>';
    ?>
    <?= ui_peringatan($terbuka ? 'sukses' : 'awas', '', ['judul' => 'Form siswa: ' . ($terbuka ? 'TERBUKA' : 'TERTUTUP'), 'html' => $isiForm]) ?>

    <!-- Antrean -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-antrean">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 id="judul-antrean" class="kartu-judul"><?= $hubin ? 'Menunggu keputusan Anda' : 'Antrean ajuan — menunggu keputusan ACC (' . esc(\App\Libraries\PklHak::teksPemegang('acc')) . ')' ?></h2>
                <p class="kartu-ket">Yang paling lama menunggu ada di atas.</p>
            </div>
        </div>

        <?php if ($antrean === []): ?>
            <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Tidak ada ajuan yang menunggu',
                'teks' => $alasan === null ? 'Semua ajuan yang masuk sudah diputuskan.' : 'Form masih tertutup, belum ada ajuan baru.']) ?>
        <?php else: ?>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($antrean as $r): ?>
                    <li>
                        <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="flex flex-col gap-1 px-4 py-3 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6 dark:hover:bg-gray-700/60">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-gray-900 dark:text-white"><?= esc($r['perusahaan_nama']) ?></span>
                                <span class="block truncate text-sm text-gray-500 dark:text-gray-400">
                                    <?= esc($r['pengaju'] ?? '—') ?><?= ! empty($r['pengaju_kelas']) ? ' (' . esc($r['pengaju_kelas']) . ')' : '' ?>
                                    <?= (int) $r['jumlah'] > 1 ? ' + ' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?>
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                <span class="font-mono font-medium"><?= esc(\App\Models\PklPengajuanModel::kode((int) $r['id'])) ?></span>
                                <span class="tabular-nums"><?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></span>
                                <span class="btn btn-utama btn-sm">Periksa</span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($nMenunggu > count($antrean)): ?>
                <div class="kartu-kaki text-center">
                    <a href="<?= site_url('admin/pkl/daftar/menunggu') ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">Lihat semua <?= $nMenunggu ?> ajuan<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Ringkasan: angka yang bukan tugas (status lain, uang tercatat) + kemajuan siswa -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-ringkasan">
        <div class="kartu-kepala"><h2 id="judul-ringkasan" class="kartu-judul text-base">Ringkasan PKL</h2></div>
        <div class="kartu-isi space-y-5">
            <ul class="flex flex-wrap gap-2 text-sm" aria-label="Jumlah ajuan per status">
                <?php foreach ([
                    ['perbaikan', 'Perlu perbaikan (di tangan siswa)', 'lencana-kuning'],
                    ['disetujui', 'Disetujui', 'lencana-hijau'],
                    ['ditolak', 'Ditolak', 'lencana-merah'],
                ] as [$st, $label, $lencana]): ?>
                    <li><a href="<?= site_url('admin/pkl/daftar/' . $st) ?>" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700/60">
                        <?= esc($label) ?> <span class="lencana <?= $lencana ?> tabular-nums"><?= (int) ($hitung[$st] ?? 0) ?></span></a></li>
                <?php endforeach; ?>
                <?php if (! empty($suratRingkas) && ! empty($bolehLaporan)): ?>
                    <li><a href="<?= site_url('admin/pkl/laporan') ?>" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700/60">
                        Uang tercatat bulan ini <span class="font-semibold text-brand-700 tabular-nums dark:text-brand-400"><?= esc(\App\Libraries\PklBiaya::rupiah((int) $suratRingkas['uang_bulan_ini'])) ?></span></a></li>
                <?php endif; ?>
            </ul>
            <div class="grid gap-4 lg:grid-cols-2" aria-label="Kemajuan siswa">
                <?php foreach ([
                    ['Sudah mengisi', $siswa['sudah_isi'], $siswa['belum_isi'], $siswa['persen_isi'], 'belum mengisi', ''],
                    ['Sudah dapat tempat PKL (disetujui)', $siswa['sudah_pkl'], $siswa['belum_pkl'], $siswa['persen_pkl'], 'belum PKL', ' progres-hijau'],
                ] as [$judul, $sudah, $belum, $persen, $labelBelum, $warnaBar]): ?>
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?></p>
                            <p class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white"><?= (int) $persen ?>%</p>
                        </div>
                        <progress class="progres<?= $warnaBar ?> mt-2" value="<?= (int) $persen ?>" max="100" aria-label="<?= esc($judul, 'attr') ?>"><?= (int) $persen ?>%</progress>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400"><b class="font-semibold text-gray-900 dark:text-white"><?= (int) $sudah ?></b> siswa sudah · <b class="font-semibold text-gray-900 dark:text-white"><?= (int) $belum ?></b> <?= esc($labelBelum) ?> · dari <b class="font-semibold text-gray-900 dark:text-white"><?= (int) $siswa['total'] ?></b> siswa tingkat <?= esc(implode('/', $tingkat) ?: '—') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($siswa['sudah_pkl'] > 0): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Dari yang disetujui: <b class="text-gray-900 dark:text-white"><?= (int) $siswa['belum_mulai'] ?></b> belum mulai · <b class="text-gray-900 dark:text-white"><?= (int) $siswa['sedang'] ?></b> sedang PKL · <b class="text-gray-900 dark:text-white"><?= (int) $siswa['selesai'] ?></b> selesai.
                    <a href="<?= site_url('admin/pkl/siswa') ?>" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline dark:text-brand-400">Lihat per siswa<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
