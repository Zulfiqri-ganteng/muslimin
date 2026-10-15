<?php
/**
 * Beranda PKL (staf) — rombak tampilan F9: tab kit (_nav), kartu angka kit (kartu-tautan + stat-*), ui_peringatan untuk status
 * form siswa / laporan baru / ajuan terlambat, kartu Persiapan, bilah persen = <progress class="progres"> (dulu style= sebaris),
 * tombol Salin tautan = komponen kit "salinTeks" (dulu objek x-data bermetode + navigator.clipboard), ui_kosong. Isi atas nama di
 * kepala halaman. Tanpa hiasan animasi, gradien, atau emoji gaya lama.
 *
 * @var array<string,int>        $hitung    jumlah ajuan per status
 * @var array<string,int|float>  $siswa     ringkasan siswa (sudah/belum mengisi, sudah/belum PKL)
 * @var list<array>              $antrean   ajuan menunggu tertua
 * @var ?string                  $alasan    null = form terbuka; selain itu alasan tertutup
 * @var string                   $tautan    alamat form siswa
 * @var list<string>             $tingkat
 * @var int                      $terlambat ajuan menunggu yang melewati batas keputusan ACC
 * @var int                      $laporBaru laporan "sudah diterima perusahaan" yang belum diproses
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
$netral = 'text-gray-900 dark:text-white';
$merek  = 'text-brand-700 dark:text-brand-400';
$kuning = 'text-yellow-700 dark:text-yellow-400';
$hijau  = 'text-green-700 dark:text-green-400';
$merah  = 'text-red-700 dark:text-red-400';
// Kartu angka yang bisa diklik (pola kit kartu-tautan): label, angka besar berwarna, keterangan.
$kartuAngka = static fn (string $url, string $label, string $angka, string $warna, string $ket): string => '<a href="' . esc($url, 'attr') . '" class="kartu kartu-isi kartu-tautan">'
    . '<p class="stat-label text-sm">' . esc($label) . '</p>'
    . '<p class="stat-angka mt-2 tabular-nums ' . $warna . '">' . $angka . '</p>'
    . '<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">' . esc($ket) . '</p></a>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/pkl/baru') ?>" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Isi atas nama</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_beranda_v3',
    'helpTitle' => 'Beranda PKL',
    'helpBody'  => '<p>Siswa mengisi ajuan PKL sendiri lewat tautan di HP. Di sini ajuan <b>diperiksa</b> lalu diputuskan. <b>ACC dan tolak dilakukan peran yang diberi hak ACC ajuan PKL</b> oleh Admin (sekarang: ' . esc(\App\Libraries\PklHak::teksPemegang('acc')) . '; diatur di Sistem → Hak Akses); peran yang memegang hak periksa bisa <b>mengembalikan</b> ajuan agar siswa memperbaiki.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Menunggu ACC</b> — antrean ajuan baru, yang paling lama di atas.</li>'
        . '<li><b>Status Siswa</b> — melihat siapa yang <b>sudah/belum mengisi</b> dan siapa yang <b>sudah/belum PKL</b>.</li>'
        . '<li><b>Isi atas Nama</b> — bila siswa tak bisa mengisi sendiri, atau untuk memasukkan data PKL lama.</li>'
        . '<li><b>Persiapan</b> (kartu berlencana kuning, bagi pemegang hak Pengaturan PKL) — daftar yang harus siap sebelum tautan dibagikan: tanggal &amp; tingkat, nama Waka Hubin untuk surat, dan form dibuka. Hilang sendiri bila semuanya siap.</li>'
        . '<li>Setiap keputusan tercatat (siapa, kapan) dan terlihat di riwayat tiap ajuan.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <?php if (! empty($suratRingkas)): // Surat & biaya: hanya bagi yang memegang hak unduh surat / laporan pembayaran ?>
        <section class="grid gap-4 sm:grid-cols-3" aria-label="Surat dan biaya PKL">
            <?php if (! empty($bolehSurat)): ?>
                <?= $kartuAngka(site_url('admin/pkl/daftar/disetujui'), 'Surat belum diunduh', (string) (int) $suratRingkas['belum_bernomor'],
                    $suratRingkas['belum_bernomor'] > 0 ? $kuning : $netral, 'Ajuan disetujui yang suratnya belum terbit') ?>
                <?= $kartuAngka(site_url('admin/pkl/daftar/disetujui'), 'Siswa belum dikabari WhatsApp', (string) (int) $suratRingkas['belum_dikabari'],
                    $suratRingkas['belum_dikabari'] > 0 ? $hijau : $netral, 'Surat sudah terbit, siswa belum diberi tahu') ?>
            <?php endif; ?>
            <?php if (! empty($bolehLaporan)): ?>
                <?= $kartuAngka(site_url('admin/pkl/laporan'), 'Uang tercatat bulan ini', esc(\App\Libraries\PklBiaya::rupiah((int) $suratRingkas['uang_bulan_ini'])),
                    $merek, 'Buka Laporan Pembayaran') ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (! empty($laporBaru)): // Laporan "sudah diterima perusahaan" dari siswa → Surat Balasan ?>
        <?= ui_peringatan('awas', '', [
            'judul' => (int) $laporBaru . ' laporan siswa "sudah diterima perusahaan"',
            'html'  => 'Menunggu dibuatkan Surat Balasan PKL. <a href="' . site_url('admin/surat/balasan/laporan') . '" class="font-semibold underline">Proses sekarang</a>',
        ]) ?>
    <?php endif; ?>

    <?php if (! empty($suratSekolah)): // Surat Sekolah (ASTS, TKA, Pernyataan, Balasan, Penarikan) — bagi pemegang hak ACC / pembuat surat ?>
        <section class="grid gap-4 sm:grid-cols-3" aria-label="Surat sekolah">
            <?= $kartuAngka(site_url('admin/surat?status=menunggu'), 'Surat sekolah menunggu ACC', (string) (int) $suratSekolah['menunggu'],
                $suratSekolah['menunggu'] > 0 ? $kuning : $netral, ! empty($bolehAcc) ? 'Periksa lalu ACC' : 'Menunggu keputusan ACC') ?>
            <?= $kartuAngka(site_url('admin/surat?status=disetujui'), 'Surat sekolah belum diunduh', (string) (int) $suratSekolah['belum_unduh'],
                $suratSekolah['belum_unduh'] > 0 ? $hijau : $netral, 'Sudah di-ACC, nomor terbit saat diunduh') ?>
            <?= $kartuAngka(site_url('admin/surat?status=dikembalikan'), 'Surat sekolah dikembalikan', (string) (int) $suratSekolah['dikembalikan'],
                $suratSekolah['dikembalikan'] > 0 ? $merah : $netral, 'Perlu diperbaiki lalu diajukan ulang') ?>
        </section>
    <?php endif; ?>

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
        . ($bolehPengaturan ? '<a href="' . site_url('admin/pkl/pengaturan') . '" class="btn btn-utama btn-sm">' . ikon('cog', 'h-4 w-4') . ($terbuka ? 'Atur / tutup form' : 'Buka lewat Pengaturan') . '</a>' : '')
        . '</div>';
    ?>
    <?= ui_peringatan($terbuka ? 'sukses' : 'awas', '', ['judul' => 'Form siswa: ' . ($terbuka ? 'TERBUKA' : 'TERTUTUP'), 'html' => $isiForm]) ?>

    <?php if (($terlambat ?? 0) > 0): ?>
        <?= ui_peringatan('galat', '', ['html' => '<b>' . (int) $terlambat . ' ajuan</b> sudah melewati batas keputusan ACC (' . (int) $batasHari . ' hari sejak dikirim siswa). Segera diputuskan. <a href="' . site_url('admin/pkl/daftar/menunggu') . '" class="font-semibold underline">Lihat ajuan</a>']) ?>
    <?php endif; ?>

    <!-- Angka status ajuan -->
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Jumlah ajuan per status">
        <?php foreach ([
            ['menunggu', 'Menunggu ACC', $merek, $hubin ? 'Menunggu keputusan Anda' : 'Menunggu keputusan ACC'],
            ['perbaikan', 'Perlu perbaikan', $kuning, 'Di tangan siswa'],
            ['disetujui', 'Disetujui', $hijau, 'Siap dibuatkan surat'],
            ['ditolak', 'Ditolak', $merah, 'Siswa boleh mengajukan lagi'],
        ] as [$st, $label, $warna, $ket]): ?>
            <?= $kartuAngka(site_url('admin/pkl/daftar/' . $st), $label, (string) (int) ($hitung[$st] ?? 0), $warna, $ket) ?>
        <?php endforeach; ?>
    </section>

    <!-- Dua angka utama: sudah mengisi, sudah PKL -->
    <section class="grid gap-4 lg:grid-cols-2" aria-label="Kemajuan siswa">
        <?php foreach ([
            ['Sudah mengisi', $siswa['sudah_isi'], $siswa['belum_isi'], $siswa['persen_isi'], 'belum mengisi', ''],
            ['Sudah dapat tempat PKL (disetujui)', $siswa['sudah_pkl'], $siswa['belum_pkl'], $siswa['persen_pkl'], 'belum PKL', ' progres-hijau'],
        ] as [$judul, $sudah, $belum, $persen, $labelBelum, $warnaBar]): ?>
            <div class="kartu kartu-isi">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?></p>
                    <p class="stat-angka tabular-nums"><?= (int) $persen ?>%</p>
                </div>
                <progress class="progres<?= $warnaBar ?> mt-3" value="<?= (int) $persen ?>" max="100" aria-label="<?= esc($judul, 'attr') ?>"><?= (int) $persen ?>%</progress>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><b class="font-semibold text-gray-900 dark:text-white"><?= (int) $sudah ?></b> siswa sudah · <b class="font-semibold text-gray-900 dark:text-white"><?= (int) $belum ?></b> <?= esc($labelBelum) ?> · dari <b class="font-semibold text-gray-900 dark:text-white"><?= (int) $siswa['total'] ?></b> siswa tingkat <?= esc(implode('/', $tingkat) ?: '—') ?></p>
            </div>
        <?php endforeach; ?>
    </section>
    <?php if ($siswa['sudah_pkl'] > 0): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Dari yang disetujui: <b class="text-gray-900 dark:text-white"><?= (int) $siswa['belum_mulai'] ?></b> belum mulai · <b class="text-gray-900 dark:text-white"><?= (int) $siswa['sedang'] ?></b> sedang PKL · <b class="text-gray-900 dark:text-white"><?= (int) $siswa['selesai'] ?></b> selesai.
            <a href="<?= site_url('admin/pkl/siswa') ?>" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline dark:text-brand-400">Lihat per siswa<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
        </p>
    <?php endif; ?>

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
            <?php if ((int) $hitung['menunggu'] > count($antrean)): ?>
                <div class="kartu-kaki text-center">
                    <a href="<?= site_url('admin/pkl/daftar/menunggu') ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">Lihat semua <?= (int) $hitung['menunggu'] ?> ajuan<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
