<?php
/**
 * Isian Biodata Siswa — halaman utama admin (rombak tampilan F6: kartu, lencana, tombol & keadaan kosong kit; bilah progres
 * <progress class="progres"> tanpa style= sebaris; komponen Alpine "biodataAdmin" di assets/js/admin/biodata.js → siap CSP).
 *
 * Urutan layar mengikuti urutan kerja admin:
 *   1. Pengingat tugas   — apa yang harus dikerjakan sekarang (periksa / buka form)
 *   2. Kartu angka       — bisa diklik, langsung membuka tab statusnya
 *   3. Form & tautan     — saklar buka/tutup, batas waktu, salin/bagikan tautan
 *      Laporan Excel     — unduh semua kelas / satu kelas
 *   4. Tab daftar        — baris tabel di layar lebar, kartu di HP (tanpa geser samping)
 *
 * Warna status (sama dengan halaman Periksa): menunggu = merek (ikut tema), perbaikan = kuning, disetujui = hijau, belum = abu.
 *
 * @var string                        $tab        menunggu|perbaikan|disetujui|belum|kelas
 * @var int                           $kelasId
 * @var string                        $kelasNama
 * @var string                        $q
 * @var array                         $rows
 * @var int                           $total
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var int                           $mulai      nomor urut awal halaman ini
 * @var array                         $ringkas    total/sudah/menunggu/disetujui/perbaikan/belum/persen
 * @var int                           $pertamaId  isian menunggu terlama (0 bila tidak ada)
 * @var array                         $perKelas
 * @var array<int,string>             $kelasOpts
 * @var array                         $setting
 * @var bool                          $terbuka
 * @var string                        $tautan       subdomain untuk siswa
 * @var string                        $tautanAlt    cadangan di domain utama
 * @var string                        $batasTeks    batas waktu siap baca ('' bila tanpa batas)
 * @var string                        $pesanBagikan pesan WA ajakan mengisi (BiodataPesan)
 * @var string                        $pesanBelum   pesan WA daftar belum mengisi satu kelas ('' bila tak berlaku)
 * @var int                           $maksMassal
 * @var int                           $bisaKembali   jumlah isian yang bisa dikembalikan sekaligus (tab menunggu/disetujui)
 * @var string                        $catatanBawaan catatan bawaan "Kembalikan semua"
 */
$fmtAngka = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$fmtTgl   = static fn (?string $s): string => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
// Waktu yang mudah dibaca: "5 menit lalu", "2 jam lalu", "kemarin 14.05", "3 hari lalu".
$relatif = static function (?string $s): string {
    if (! $s) {
        return '—';
    }
    $t = strtotime($s);
    $d = time() - $t;
    if ($d < 60) {
        return 'baru saja';
    }
    if ($d < 3600) {
        return (int) floor($d / 60) . ' menit lalu';
    }
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return (int) floor($d / 3600) . ' jam lalu';
    }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return 'kemarin ' . date('H.i', $t);
    }
    if ($d < 7 * 86400) {
        return max(2, (int) floor($d / 86400)) . ' hari lalu';
    }

    return date('d/m/Y', $t);
};

$bukaDiSetelan = ! empty($setting['biodata_open']);
$batas         = $setting['biodata_tutup'] ?? null;
$batasInput    = ! empty($batas) ? date('Y-m-d\TH:i', strtotime($batas)) : '';
$lewatBatas    = $bukaDiSetelan && ! $terbuka; // dibuka, tapi batas waktunya sudah lewat
$bisaKembali   = (int) ($bisaKembali ?? 0);

// Warna per status — dipakai kartu angka, tab, dan lencana supaya konsisten.
$warna = [
    'menunggu'  => ['titik' => 'titik-status-merek', 'lencana' => 'lencana-merek', 'teks' => 'text-brand-700 dark:text-brand-400'],
    'perbaikan' => ['titik' => 'titik-status-kuning', 'lencana' => 'lencana-kuning', 'teks' => 'text-yellow-700 dark:text-yellow-400'],
    'disetujui' => ['titik' => 'titik-status-hijau', 'lencana' => 'lencana-hijau', 'teks' => 'text-green-700 dark:text-green-400'],
    'belum'     => ['titik' => '', 'lencana' => 'lencana-abu', 'teks' => 'text-gray-700 dark:text-gray-300'],
    'kelas'     => ['titik' => 'titik-status-merek', 'lencana' => 'lencana-merek', 'teks' => 'text-brand-700 dark:text-brand-400'],
];
$tabs = [
    'menunggu'  => ['Menunggu Verifikasi', $ringkas['menunggu']],
    'perbaikan' => ['Perlu Perbaikan', $ringkas['perbaikan']],
    'disetujui' => ['Disetujui', $ringkas['disetujui']],
    'belum'     => ['Belum Mengisi', $ringkas['belum']],
    'kelas'     => ['Rekap per Kelas', null],
];
$urlTab = static fn (string $t): string => site_url('admin/biodata') . '?' . http_build_query(array_filter([
    'tab' => $t, 'kelas_id' => $kelasId ?: '', 'q' => $q,
], static fn ($v) => $v !== ''));
$urlDetail = static fn (int $id): string => site_url('admin/biodata/' . $id) . ($kelasId > 0 ? '?kelas_id=' . $kelasId : '');

// Progres per kelas: merah = sedikit, kuning = separuh jalan, hijau = hampir/sudah lengkap.
$warnaProgres = static fn (float $r): array => $r >= 67
    ? ['progres-hijau', 'text-green-700 dark:text-green-400']
    : ($r >= 34 ? ['progres-kuning', 'text-yellow-700 dark:text-yellow-400'] : ['progres-merah', 'text-red-700 dark:text-red-400']);

// Kelas bersama: judul kolom daftar (layar lebar) & baris daftar.
$kepalaDaftar = 'hidden gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs font-semibold text-gray-500 uppercase dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400';
$barisDaftar  = 'items-center gap-x-3 gap-y-1 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60';
$v = static fn (string $p): string => base_url($p) . '?v=' . @filemtime(FCPATH . $p);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'biodata_v2',
    'helpTitle' => 'Isian Biodata Siswa',
    'helpBody'  => '<p>Siswa mengisi biodatanya sendiri lewat tautan (tanpa login): <b>pilih kelas → pilih nama → isi → kirim</b>. Satu siswa hanya bisa mengirim <b>satu kali</b>; NISN tidak bisa dipakai dua siswa.</p>
        <p class="mt-1">• <b>Cara pakai:</b> nyalakan saklar <b>Form DIBUKA</b> → <b>Bagikan ke WA</b> → periksa isian yang masuk lewat tombol <b>Periksa Sekarang</b>.<br>
        • Kartu angka di atas bisa diklik untuk langsung membuka daftarnya.<br>
        • Isian <b>tidak langsung</b> mengubah Master Siswa. Buka <b>Periksa</b> untuk membandingkan data lama dengan isian siswa, lalu <b>Setujui</b> — baru saat itu datanya masuk ke Master Siswa (web &amp; aplikasi Android). Setelah menyetujui, isian berikutnya langsung terbuka.<br>
        • Banyak isian sekaligus? Centang lalu <b>Setujui terpilih</b>, atau <b>Setujui semua</b> (maks. ' . (int) $maksMassal . ' per klik).<br>
        • Ada yang salah? <b>Kembalikan</b> dengan catatan — siswa membuka isiannya lagi di form memakai NISN / tanggal lahir. <b>Hapus isian</b> membuat siswa mengisi dari nol.<br>
        • Mau mengembalikan <b>semua</b> yang sudah mengisi sekaligus (mis. setelah data sekolah diperbarui)? Di tab <b>Menunggu</b> atau <b>Disetujui</b> buka bar kuning <b>Kembalikan semua…</b>, tulis catatan, lalu kirim. Bisa dipersempit per kelas lewat saringan kelas.<br>
        • Tab <b>Belum Mengisi</b> + pilih satu kelas → muncul pesan WhatsApp berisi daftar nama untuk dikirim ke wali kelas.</p>
        <p class="mt-1">• Form bisa <b>dibuka/ditutup</b> kapan saja, dan bisa diberi <b>batas waktu</b> agar tertutup otomatis.</p>
        <p class="mt-1">• <b>Unduh Laporan</b> — Excel siap cetak berisi rekap per kelas dan daftar nama tiap kelas (lengkap / belum lengkap + kolom yang masih kurang), lengkap dengan kop dan tanda tangan. Data biodata lengkap (nama orang tua, alamat, dll.) ada di <b>Master Data → Siswa → Export</b>. “Lengkap” dihitung dari data <b>Master Siswa</b>, jadi isian yang belum disetujui belum terhitung lengkap.</p>',
]) ?>

<div x-data="biodataAdmin" class="space-y-4">

    <!-- ================= 1. Pengingat tugas ================= -->
    <?php if (! $terbuka): ?>
        <?= ui_peringatan('awas', '', [
            'judul' => 'Form sedang DITUTUP — siswa belum bisa mengisi.',
            'html'  => '<p>' . ($lewatBatas ? 'Batas waktu pengisian (' . esc($fmtTgl($batas)) . ') sudah lewat.' : 'Siswa yang membuka tautan melihat pesan “Pengisian Sedang Ditutup”.') . '</p>'
                . '<a href="#formIsian" class="btn btn-netral btn-sm mt-3">' . ikon('cog', 'h-3.5 w-3.5') . 'Atur form</a>',
        ]) ?>
    <?php endif; ?>

    <?php if ($ringkas['menunggu'] > 0): ?>
        <div class="kartu kartu-isi flex flex-col gap-3 sm:flex-row sm:items-center">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-900 dark:text-brand-300"><?= ikon('clipboard-check', 'h-5 w-5') ?></span>
            <div class="min-w-0 flex-1 text-sm">
                <p class="font-semibold text-gray-900 dark:text-white">Ada <span class="tabular-nums"><?= $fmtAngka($ringkas['menunggu']) ?></span> isian menunggu diperiksa.</p>
                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Mulai dari kiriman terlama — setelah disetujui, isian berikutnya langsung terbuka.</p>
            </div>
            <?php if ($pertamaId > 0): ?>
                <a href="<?= esc(site_url('admin/biodata/' . $pertamaId), 'attr') ?>" class="btn btn-utama shrink-0">Periksa sekarang<?= ikon('chevron-right', 'h-4 w-4') ?></a>
            <?php endif; ?>
        </div>
    <?php elseif ($terbuka && $ringkas['sudah'] > 0): ?>
        <?= ui_peringatan('sukses', '', ['html' => '<b>Semua isian yang masuk sudah diperiksa.</b> Isian baru dari siswa akan muncul di sini.']) ?>
    <?php endif; ?>

    <!-- ================= 2. Kartu angka (bisa diklik) ================= -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
        <a href="<?= esc($urlTab('kelas'), 'attr') ?>" class="kartu kartu-isi kartu-tautan col-span-2">
            <p class="stat-label">Progres pengisian</p>
            <p class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span class="stat-angka tabular-nums"><?= esc($ringkas['persen_teks']) ?></span>
                <span class="text-sm text-gray-500 dark:text-gray-400"><b class="font-semibold text-gray-900 tabular-nums dark:text-white"><?= $fmtAngka($ringkas['sudah']) ?></b> dari <span class="tabular-nums"><?= $fmtAngka($ringkas['total']) ?></span> siswa aktif</span>
            </p>
            <progress class="progres mt-3" value="<?= (float) $ringkas['rasio'] ?>" max="100" aria-label="Progres pengisian biodata"><?= esc($ringkas['persen_teks']) ?></progress>
            <p class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-brand-700 dark:text-brand-400">Lihat rekap per kelas<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></p>
        </a>
        <?php foreach ([
            'menunggu'  => ['Menunggu', 'perlu diperiksa'],
            'perbaikan' => ['Perbaikan', 'sedang diperbaiki siswa'],
            'disetujui' => ['Disetujui', 'sudah di Master Siswa'],
            'belum'     => ['Belum mengisi', 'perlu dikejar'],
        ] as $kunci => [$label, $ket]): $w = $warna[$kunci]; $aktif = $tab === $kunci; ?>
            <a href="<?= esc($urlTab($kunci), 'attr') ?>"<?= $aktif ? ' aria-current="page"' : '' ?>
               class="kartu kartu-isi kartu-tautan<?= $aktif ? ' ring-2 ring-brand-500 dark:ring-brand-400' : '' ?>">
                <p class="titik-status <?= $w['titik'] ?> text-sm font-medium <?= $w['teks'] ?>"><?= esc($label) ?></p>
                <p class="stat-angka mt-2 tabular-nums"><?= $fmtAngka($ringkas[$kunci]) ?></p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc($ket) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ================= 3. Form & tautan + Laporan ================= -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
        <section id="formIsian" class="kartu scroll-mt-24 lg:col-span-3" aria-labelledby="judul-form">
            <div class="kartu-kepala">
                <div>
                    <h2 id="judul-form" class="kartu-judul">Form isian untuk siswa</h2>
                    <p class="kartu-ket"><?= $terbuka ? ($batasTeks !== '' ? 'Tertutup otomatis ' . esc($batasTeks) . '.' : 'Terbuka tanpa batas waktu.') : 'Siswa belum bisa mengisi.' ?></p>
                </div>
                <!-- Saklar buka/tutup: satu klik langsung tersimpan (dengan konfirmasi). -->
                <form method="post" action="<?= site_url('admin/biodata/pengaturan') ?>" class="flex items-center gap-3">
                    <?= csrf_field() ?>
                    <?php if ($terbuka): ?>
                        <input type="hidden" name="biodata_tutup" value="<?= esc($batasInput, 'attr') ?>">
                        <span class="text-sm font-bold text-green-700 dark:text-green-400">DIBUKA</span>
                        <button type="submit" role="switch" aria-checked="true" aria-label="Tutup form isian"
                                data-confirm="Tutup form sekarang? Siswa tidak bisa mengisi sampai form dibuka lagi."
                                class="saklar-tombol bg-green-600 hover:bg-green-700">
                            <span class="saklar-kenop translate-x-5 border-white"></span>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="biodata_open" value="1">
                        <input type="hidden" name="biodata_tutup" value="<?= $lewatBatas ? '' : esc($batasInput, 'attr') ?>">
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">DITUTUP</span>
                        <button type="submit" role="switch" aria-checked="false" aria-label="Buka form isian"
                                data-confirm="<?= $lewatBatas ? 'Buka form sekarang? Batas waktu lama sudah lewat, jadi batas waktu dihapus (form terbuka sampai Anda menutupnya).' : 'Buka form sekarang? Siswa bisa langsung mengisi lewat tautan.' ?>"
                                class="saklar-tombol bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600">
                            <span class="saklar-kenop translate-x-0"></span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="kartu-isi space-y-4">
                <div>
                    <label class="lbl" for="tautanForm">Tautan untuk dibagikan</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="tautanForm" x-ref="tautanForm" type="text" readonly value="<?= esc($tautan, 'attr') ?>" class="inp min-w-0 flex-1 font-medium text-brand-700 dark:text-brand-300" @focus="pilihTeks($event)">
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <button type="button" class="btn btn-netral" @click="salin($refs.tautanForm.value, 'tautan')">
                                <?= ikon('file-copy', 'h-4 w-4') ?><span x-text="tersalin === 'tautan' ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                            <button type="button" class="btn btn-sukses" @click="wa($refs.pesanBagikan.value)">
                                <?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span class="sm:hidden">Bagikan</span><span class="hidden sm:inline">Bagikan ke WA</span>
                            </button>
                        </div>
                    </div>
                    <textarea x-ref="pesanBagikan" class="hidden" aria-hidden="true"><?= esc($pesanBagikan) ?></textarea>
                    <p class="hint">
                        Tautan cadangan (bila subdomain bermasalah): <a href="<?= esc($tautanAlt, 'attr') ?>" target="_blank" rel="noopener" class="font-medium break-all text-brand-700 hover:underline dark:text-brand-400"><?= esc($tautanAlt) ?></a>
                    </p>
                </div>

                <!-- Batas waktu (opsional) -->
                <form method="post" action="<?= site_url('admin/biodata/pengaturan') ?>" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-700/50">
                    <?= csrf_field() ?>
                    <?php if ($bukaDiSetelan): ?><input type="hidden" name="biodata_open" value="1"><?php endif; ?>
                    <label class="lbl" for="batasTutup">Tutup otomatis pada <span class="font-normal text-gray-500 dark:text-gray-400">(boleh kosong)</span></label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="datetime-local" id="batasTutup" name="biodata_tutup" value="<?= esc($batasInput, 'attr') ?>" class="inp min-w-0 flex-1">
                        <button type="submit" class="btn btn-utama"><?= ikon('clock', 'h-4 w-4') ?>Simpan batas waktu</button>
                    </div>
                    <p class="hint">Lewat waktu ini form tertutup sendiri. Kosongkan lalu simpan untuk menghapus batas.</p>
                </form>

                <?php if ((int) $ringkas['sudah'] === 0): ?>
                    <ol class="grid grid-cols-1 gap-2 text-sm text-gray-600 sm:grid-cols-3 dark:text-gray-300">
                        <li class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600"><b class="text-brand-700 dark:text-brand-400">1.</b> Nyalakan saklar <b>DIBUKA</b>.</li>
                        <li class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600"><b class="text-brand-700 dark:text-brand-400">2.</b> <b>Bagikan ke WA</b> grup siswa / wali kelas.</li>
                        <li class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600"><b class="text-brand-700 dark:text-brand-400">3.</b> Periksa &amp; <b>setujui</b> isian yang masuk.</li>
                    </ol>
                <?php endif; ?>
            </div>
        </section>

        <!-- Laporan Excel siap cetak -->
        <section class="kartu flex flex-col lg:col-span-2" aria-labelledby="judul-laporan">
            <div class="kartu-kepala">
                <div>
                    <h2 id="judul-laporan" class="kartu-judul">Laporan kelengkapan (Excel)</h2>
                    <p class="kartu-ket">Siap cetak, dengan kop &amp; tanda tangan.</p>
                </div>
            </div>
            <form method="get" action="<?= site_url('admin/biodata/laporan') ?>" data-noload class="kartu-isi flex flex-1 flex-col gap-4">
                <ul class="space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                    <li class="flex items-center gap-2"><?= ikon('check', 'h-4 w-4 shrink-0 text-green-600 dark:text-green-400') ?>Rekap jumlah per kelas</li>
                    <li class="flex items-center gap-2"><?= ikon('check', 'h-4 w-4 shrink-0 text-green-600 dark:text-green-400') ?>Daftar nama tiap kelas: lengkap / belum</li>
                    <li class="flex items-center gap-2"><?= ikon('check', 'h-4 w-4 shrink-0 text-green-600 dark:text-green-400') ?>Kolom yang masih kurang per siswa</li>
                </ul>
                <div class="mt-auto space-y-2">
                    <label class="sr-only" for="laporanKelas">Kelas untuk laporan</label>
                    <select id="laporanKelas" name="kelas_id" class="inp">
                        <option value="">Semua kelas</option>
                        <?php foreach ($kelasOpts as $id => $nama): ?>
                            <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sukses w-full"><?= ikon('download', 'h-4 w-4') ?>Unduh laporan</button>
                    <p class="hint">Butuh data lengkap (nama orang tua, alamat, dll.)? Pakai <a href="<?= site_url('admin/master/siswa') ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Master Siswa → Export</a>.</p>
                </div>
            </form>
        </section>
    </div>

    <!-- ================= 4. Tab daftar ================= -->
    <section id="daftar" class="kartu overflow-hidden" aria-label="Daftar isian biodata">
        <nav x-ref="tabNav" class="flex gap-1 overflow-x-auto border-b border-gray-200 px-2 text-sm font-medium sm:px-3 dark:border-gray-700" aria-label="Tab isian biodata">
            <?php foreach ($tabs as $kunci => [$label, $jumlah]): $aktif = $tab === $kunci; $w = $warna[$kunci]; ?>
                <a href="<?= esc($urlTab($kunci), 'attr') ?>"<?= $aktif ? ' aria-current="page"' : '' ?>
                   class="-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 transition-colors sm:px-4 <?= $aktif ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' ?>">
                    <?php if ($kunci !== 'kelas'): ?><span class="titik-status <?= $w['titik'] ?>" aria-hidden="true"></span><?php endif; ?>
                    <?= esc($label) ?>
                    <?php if ($jumlah !== null): ?><span class="lencana <?= $aktif ? $w['lencana'] : 'lencana-abu' ?> tabular-nums"><?= $fmtAngka($jumlah) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tab !== 'kelas'): ?>
            <form method="get" action="<?= site_url('admin/biodata') ?>" data-noload class="flex flex-col gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row dark:border-gray-700 dark:bg-gray-800/60">
                <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                <select name="kelas_id" data-autosubmit class="inp sm:w-48" aria-label="Saring kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelasOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="relative min-w-0 flex-1">
                    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                    <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama atau NISN…" aria-label="Cari nama atau NISN" class="inp ps-9">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-utama flex-1 sm:flex-none"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
                    <?php if ($kelasId > 0 || $q !== ''): ?>
                        <a href="<?= site_url('admin/biodata') . '?tab=' . $tab ?>" class="btn btn-netral flex-1 sm:flex-none"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>

        <?php if (in_array($tab, ['menunggu', 'disetujui'], true) && $bisaKembali > 0): ?>
            <!-- Kembalikan SEMUA yang sudah mengisi (menunggu + disetujui), sesuai saringan kelas/pencarian di atas -->
            <details class="group border-b border-gray-200 bg-yellow-50 dark:border-gray-700 dark:bg-yellow-900/20">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-yellow-800 hover:bg-yellow-100 dark:text-yellow-300 dark:hover:bg-yellow-900/30">
                    <span class="inline-flex items-center gap-2"><?= ikon('file-pen', 'h-4 w-4 shrink-0') ?>Kembalikan semua yang sudah mengisi untuk diperbaiki (<?= $fmtAngka($bisaKembali) ?><?= $kelasNama !== '' ? ' di ' . esc($kelasNama) : '' ?>)</span>
                    <span class="inline-flex shrink-0 items-center gap-1 text-xs font-normal"><span class="group-open:hidden">Klik untuk membuka</span><?= ikon('chevron-down', 'h-4 w-4 transition-transform group-open:rotate-180') ?></span>
                </summary>
                <form method="post" action="<?= site_url('admin/biodata/kembalikan-massal') ?>" class="space-y-3 px-4 pb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                    <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                    <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                    <div>
                        <label class="lbl" for="catatanMassal">Catatan untuk siswa (sama untuk semua)</label>
                        <textarea id="catatanMassal" name="catatan" rows="3" maxlength="255" required class="inp"><?= esc($catatanBawaan ?? '') ?></textarea>
                        <p class="hint">Mencakup isian <b>menunggu</b> dan <b>disetujui</b><?= $kelasNama !== '' ? ' di ' . esc($kelasNama) : ($q !== '' ? ' yang cocok dengan pencarian' : ' dari SEMUA kelas') ?>. Isian pindah ke tab <b>Perbaikan</b>; data di Master Siswa <b>tidak berubah</b>. Siswa membukanya lagi di form (pakai NISN atau tanggal lahir yang dulu diisi), memeriksa, lalu mengirim ulang.</p>
                    </div>
                    <button type="submit" class="btn btn-utama"
                            data-confirm="Kembalikan <?= $bisaKembali ?> isian<?= $kelasNama !== '' ? ' di ' . esc($kelasNama, 'attr') : '' ?> ke siswa untuk diperbaiki? Siswa harus membuka dan mengirim ulang isiannya.">
                        <?= ikon('file-pen', 'h-4 w-4') ?>Kembalikan <?= $fmtAngka($bisaKembali) ?> isian
                    </button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ($tab === 'menunggu'): ?>
            <!-- ============ MENUNGGU ============ -->
            <?php if ($total > 0): ?>
                <div class="flex flex-col justify-between gap-2 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-center dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <label class="inline-flex cursor-pointer items-center gap-2 font-medium text-gray-900 dark:text-white">
                            <input type="checkbox" class="cek" @change="pilihSemua($event)">Pilih semua
                        </label>
                        <span class="text-gray-500 dark:text-gray-400"><b class="font-semibold text-gray-900 tabular-nums dark:text-white"><?= $fmtAngka($total) ?></b> menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama) : '' ?> · terlama di atas</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex">
                        <button type="submit" form="formMassal" class="btn btn-sukses" :disabled="jumlah === 0" data-confirm="Setujui isian yang dicentang? Data akan langsung masuk ke Master Siswa.">
                            <?= ikon('check', 'h-4 w-4') ?><span>Setujui<span class="hidden sm:inline"> terpilih</span> (<span x-text="jumlah">0</span>)</span>
                        </button>
                        <form method="post" action="<?= site_url('admin/biodata/setujui-massal') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="all">
                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                            <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                            <?php $n = min($total, $maksMassal); ?>
                            <button type="submit" class="btn btn-netral w-full"
                                    data-confirm="Setujui <?= $n ?> isian menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama, 'attr') : '' ?> TANPA diperiksa satu per satu? Data akan langsung masuk ke Master Siswa.">
                                <?= ikon('check-circle', 'h-4 w-4 text-green-600 dark:text-green-400') ?>Setujui semua (<?= $n ?>)
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <form id="formMassal" method="post" action="<?= site_url('admin/biodata/setujui-massal') ?>" @change="hitung()">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="selected">
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                <?php if ($rows === []): ?>
                    <?= $q !== '' || $kelasId
                        ? ui_kosong(['ikon' => 'search', 'judul' => 'Tidak ada isian menunggu yang cocok.', 'teks' => 'Coba kelas lain atau hapus kata kunci pencarian.',
                            'aksi' => '<a href="' . site_url('admin/biodata') . '?tab=menunggu" class="btn btn-netral">Tampilkan semua</a>'])
                        : ui_kosong(['ikon' => 'inbox', 'judul' => 'Tidak ada isian yang menunggu diperiksa.', 'teks' => 'Isian baru dari siswa otomatis muncul di sini. Kejar siswa yang belum mengisi lewat tab Belum Mengisi.',
                            'aksi' => '<a href="' . esc($urlTab('belum'), 'attr') . '" class="btn btn-netral">' . ikon('users', 'h-4 w-4') . 'Lihat yang belum mengisi</a>']) ?>
                <?php else: ?>
                    <div class="<?= $kepalaDaftar ?> grid-cols-[2.5rem_minmax(0,1fr)_8rem_10rem_7.5rem] md:grid">
                        <span></span><span>Nama siswa</span><span>Kelas</span><span>Dikirim</span><span class="text-right">Aksi</span>
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($rows as $r): $perbaikanKe = (int) $r['kirim_ke'] - 1; ?>
                            <li class="<?= $barisDaftar ?> grid grid-cols-[2rem_minmax(0,1fr)_auto] md:grid-cols-[2.5rem_minmax(0,1fr)_8rem_10rem_7.5rem]">
                                <input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="bio-cek cek" aria-label="Pilih <?= esc($r['nama'], 'attr') ?>">
                                <div class="min-w-0">
                                    <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-semibold text-gray-900 hover:text-brand-700 hover:underline dark:text-white dark:hover:text-brand-400"><?= esc($r['nama']) ?></a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span>NISN <?= esc($r['nisn'] ?? '—') ?></span>
                                        <span class="basis-full md:hidden"><?= esc($r['nama_kelas'] ?? '—') ?> · <span title="<?= esc($fmtTgl($r['updated_at']), 'attr') ?>"><?= esc($relatif($r['updated_at'])) ?></span></span>
                                        <?php if ($perbaikanKe > 0): ?><span class="lencana lencana-kuning">Hasil perbaikan<?= $perbaikanKe > 1 ? ' ke-' . $perbaikanKe : '' ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <span class="hidden text-sm text-gray-900 md:block dark:text-white"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                                <span class="hidden items-center gap-1.5 text-sm text-gray-500 md:flex dark:text-gray-400" title="<?= esc($fmtTgl($r['updated_at']), 'attr') ?>"><?= ikon('clock', 'h-3.5 w-3.5 shrink-0') ?><?= esc($relatif($r['updated_at'])) ?></span>
                                <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="btn btn-utama btn-sm justify-self-end">Periksa<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </form>

        <?php elseif ($tab === 'perbaikan' || $tab === 'disetujui'): ?>
            <!-- ============ PERBAIKAN / DISETUJUI ============ -->
            <?php $diperbaiki = $tab === 'perbaikan'; ?>
            <?php if ($rows === []): ?>
                <?= $q !== '' || $kelasId
                    ? ui_kosong(['ikon' => 'search', 'judul' => 'Tidak ada data yang cocok.', 'teks' => 'Coba kelas lain atau hapus kata kunci pencarian.'])
                    : ($diperbaiki
                        ? ui_kosong(['ikon' => 'file-pen', 'judul' => 'Tidak ada isian yang sedang diperbaiki.', 'teks' => 'Isian yang Anda kembalikan ke siswa akan tampil di sini sampai siswa mengirim ulang.'])
                        : ui_kosong(['ikon' => 'check-circle', 'judul' => 'Belum ada isian yang disetujui.', 'teks' => 'Periksa isian di tab Menunggu Verifikasi, lalu setujui agar datanya masuk ke Master Siswa.',
                            'aksi' => '<a href="' . esc($urlTab('menunggu'), 'attr') . '" class="btn btn-netral">Buka tab Menunggu</a>'])) ?>
            <?php else: ?>
                <div class="<?= $kepalaDaftar ?> grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)_10rem_6rem] md:grid">
                    <span>Nama siswa</span><span>Kelas</span><span><?= $diperbaiki ? 'Catatan untuk siswa' : 'NISN' ?></span><span><?= $diperbaiki ? 'Dikembalikan' : 'Disetujui' ?></span><span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($rows as $r): $waktu = $diperbaiki ? $r['updated_at'] : $r['diverifikasi_at']; ?>
                        <li class="<?= $barisDaftar ?> grid grid-cols-[minmax(0,1fr)_auto] md:grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)_10rem_6rem]">
                            <div class="min-w-0">
                                <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-semibold text-gray-900 hover:text-brand-700 hover:underline dark:text-white dark:hover:text-brand-400"><?= esc($r['nama']) ?></a>
                                <p class="mt-0.5 text-xs text-gray-500 md:hidden dark:text-gray-400"><?= esc($r['nama_kelas'] ?? '—') ?> · <?= esc($relatif($waktu)) ?></p>
                                <?php if ($diperbaiki): ?><p class="mt-1 rounded-md bg-yellow-50 px-2 py-1 text-xs text-yellow-800 md:hidden dark:bg-yellow-900/30 dark:text-yellow-300"><?= esc($r['catatan_admin'] ?? '—') ?></p><?php endif; ?>
                            </div>
                            <span class="hidden text-sm text-gray-900 md:block dark:text-white"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                            <span class="hidden text-sm md:block <?= $diperbaiki ? 'text-yellow-800 dark:text-yellow-300' : 'text-gray-600 tabular-nums dark:text-gray-300' ?>"><?= esc($diperbaiki ? ($r['catatan_admin'] ?? '—') : ($r['nisn'] ?? '—')) ?></span>
                            <span class="hidden text-sm text-gray-500 md:block dark:text-gray-400" title="<?= esc($fmtTgl($waktu), 'attr') ?>"><?= esc($relatif($waktu)) ?></span>
                            <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="btn btn-netral btn-sm justify-self-end"><?= ikon('eye', 'h-3.5 w-3.5') ?>Lihat</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php elseif ($tab === 'belum'): ?>
            <!-- ============ BELUM MENGISI ============ -->
            <?php if ($pesanBelum !== ''): ?>
                <div class="border-b border-gray-200 bg-green-50 px-4 py-4 dark:border-gray-700 dark:bg-green-900/20">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= $fmtAngka($total) ?> siswa <?= esc($kelasNama) ?> belum mengisi</p>
                            <p class="text-xs text-gray-600 dark:text-gray-300">Kirim daftar namanya ke wali kelas / grup kelas lewat WhatsApp.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <button type="button" class="btn btn-sukses" @click="wa($refs.pesanBelum.value)"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kirim ke WA</button>
                            <button type="button" class="btn btn-netral" @click="salin($refs.pesanBelum.value, 'belum')"><?= ikon('file-copy', 'h-4 w-4') ?><span x-text="tersalin === 'belum' ? 'Tersalin' : 'Salin pesan'">Salin pesan</span></button>
                        </div>
                    </div>
                    <details class="group mt-3">
                        <summary class="cursor-pointer text-xs font-medium text-green-800 hover:underline dark:text-green-300">Lihat isi pesan</summary>
                        <textarea x-ref="pesanBelum" readonly rows="6" class="inp mt-2 font-mono text-xs" aria-label="Isi pesan WhatsApp"><?= esc($pesanBelum) ?></textarea>
                    </details>
                </div>
            <?php elseif ($kelasId === 0 && $total > 0): ?>
                <div class="flex items-start gap-2 border-b border-gray-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-gray-700 dark:bg-gray-800 dark:text-blue-400">
                    <?= ikon('info-circle', 'mt-0.5 h-4 w-4 shrink-0') ?>
                    <p>Pilih <b>satu kelas</b> di atas untuk mendapatkan pesan WhatsApp berisi daftar nama yang belum mengisi.</p>
                </div>
            <?php endif; ?>
            <?php if ($rows === []): ?>
                <?= ui_kosong(['ikon' => 'check-circle', 'judul' => $kelasId || $q !== '' ? 'Semua siswa yang cocok sudah mengisi' : 'Semua siswa aktif sudah mengisi',
                    'teks' => 'Tidak ada lagi yang perlu dikejar di daftar ini.']) ?>
            <?php else: ?>
                <div class="<?= $kepalaDaftar ?> grid-cols-[3.5rem_minmax(0,1fr)_4rem_9rem] md:grid">
                    <span>No</span><span>Nama siswa</span><span class="text-center">JK</span><span>Kelas</span>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($rows as $i => $r): ?>
                        <li class="<?= $barisDaftar ?> grid grid-cols-[2.5rem_minmax(0,1fr)_auto] text-sm md:grid-cols-[3.5rem_minmax(0,1fr)_4rem_9rem]">
                            <span class="text-gray-500 tabular-nums dark:text-gray-400"><?= $mulai + $i + 1 ?></span>
                            <span class="min-w-0 font-medium text-gray-900 dark:text-white"><?= esc($r['nama']) ?><span class="text-xs font-normal text-gray-500 md:hidden dark:text-gray-400"> · <?= esc($r['jenis_kelamin'] ?? '—') ?></span></span>
                            <span class="hidden text-center text-gray-600 md:block dark:text-gray-300"><?= esc($r['jenis_kelamin'] ?? '—') ?></span>
                            <span class="text-right text-xs text-gray-600 md:text-left md:text-sm dark:text-gray-300"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: ?>
            <!-- ============ REKAP PER KELAS ============ -->
            <?php if ($perKelas === []): ?>
                <?= ui_kosong(['ikon' => 'school', 'judul' => 'Belum ada kelas yang berisi siswa aktif.', 'teks' => 'Tambahkan siswa di Master Data → Siswa.']) ?>
            <?php else: ?>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-gray-200 px-4 py-2.5 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <span>Warna progres:</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-red-500"></span>di bawah 34%</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-yellow-400"></span>34–66%</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-green-500"></span>67% ke atas</span>
                </div>
                <div class="hidden grid-cols-[7rem_minmax(0,1fr)_3.5rem_4.5rem_4.5rem_4.75rem_3.5rem_8.5rem_10.75rem] gap-x-3 border-b border-gray-200 bg-gray-50 px-4 py-3 text-[11px] font-semibold text-gray-500 uppercase xl:grid dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    <span>Kelas</span><span>Wali kelas</span><span class="text-right">Siswa</span><span class="text-right">Menunggu</span><span class="text-right">Disetujui</span><span class="text-right">Perbaikan</span><span class="text-right">Belum</span><span>Sudah mengisi</span><span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($perKelas as $k): [$bar, $teksPersen] = $warnaProgres((float) $k['rasio']); ?>
                        <li class="grid grid-cols-2 items-center gap-x-3 gap-y-2 px-4 py-3 text-sm transition-colors hover:bg-gray-50 xl:grid-cols-[7rem_minmax(0,1fr)_3.5rem_4.5rem_4.5rem_4.75rem_3.5rem_8.5rem_10.75rem] dark:hover:bg-gray-700/60">
                            <div class="col-span-2 flex items-baseline justify-between xl:col-span-1 xl:block">
                                <span class="font-semibold whitespace-nowrap text-gray-900 dark:text-white"><?= esc($k['nama_kelas']) ?></span>
                                <span class="ms-2 truncate text-xs text-gray-500 xl:hidden dark:text-gray-400"><?= esc($k['wali'] ?? '—') ?></span>
                            </div>
                            <span class="hidden truncate text-gray-600 xl:block dark:text-gray-300" title="<?= esc($k['wali'] ?? '', 'attr') ?>"><?= esc($k['wali'] ?? '—') ?></span>
                            <span class="hidden text-right text-gray-900 tabular-nums xl:block dark:text-white"><?= $k['total'] ?></span>
                            <span class="hidden text-right tabular-nums xl:block <?= $k['menunggu'] > 0 ? 'font-semibold text-brand-700 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= $k['menunggu'] ?></span>
                            <span class="hidden text-right tabular-nums xl:block <?= $k['disetujui'] > 0 ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= $k['disetujui'] ?></span>
                            <span class="hidden text-right tabular-nums xl:block <?= $k['perbaikan'] > 0 ? 'text-yellow-700 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= $k['perbaikan'] ?></span>
                            <span class="hidden text-right font-semibold tabular-nums xl:block <?= $k['belum'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' ?>"><?= $k['belum'] ?></span>
                            <!-- HP: ringkasan angka dalam satu baris -->
                            <p class="col-span-2 text-xs text-gray-500 xl:hidden dark:text-gray-400">
                                <?= $k['total'] ?> siswa · <span class="<?= $k['menunggu'] > 0 ? 'font-semibold text-brand-700 dark:text-brand-400' : '' ?>"><?= $k['menunggu'] ?> menunggu</span> · <?= $k['disetujui'] ?> disetujui · <?= $k['perbaikan'] ?> perbaikan · <b class="text-gray-900 dark:text-white"><?= $k['belum'] ?> belum</b>
                            </p>
                            <div class="col-span-2 flex items-center gap-2 xl:col-span-1" title="<?= $k['sudah'] ?> dari <?= $k['total'] ?> siswa sudah mengisi">
                                <progress class="progres <?= $bar ?> flex-1" value="<?= (float) $k['rasio'] ?>" max="100" aria-label="Kelas <?= esc($k['nama_kelas'], 'attr') ?>: <?= esc($k['persen_teks'], 'attr') ?> sudah mengisi"><?= esc($k['persen_teks']) ?></progress>
                                <span class="w-12 shrink-0 text-right text-xs font-semibold tabular-nums <?= $teksPersen ?>"><?= esc($k['persen_teks']) ?></span>
                            </div>
                            <div class="col-span-2 flex gap-2 xl:col-span-1 xl:justify-end">
                                <?php if ($k['belum'] > 0): ?>
                                    <a href="<?= site_url('admin/biodata') . '?tab=belum&kelas_id=' . (int) $k['id'] ?>" class="btn btn-netral btn-sm flex-1 xl:flex-none" title="Daftar & pesan WA siswa yang belum mengisi">Yang belum<?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                                <?php else: ?>
                                    <span class="lencana lencana-hijau flex-1 justify-center py-1.5 xl:flex-none"><?= ikon('check', 'h-3.5 w-3.5') ?>Semua mengisi</span>
                                <?php endif; ?>
                                <a href="<?= site_url('admin/biodata/laporan') . '?kelas_id=' . (int) $k['id'] ?>" class="btn btn-netral btn-sm flex-1 xl:flex-none" title="Unduh laporan Excel kelas <?= esc($k['nama_kelas'], 'attr') ?>"><?= ikon('download', 'h-3.5 w-3.5 text-green-600 dark:text-green-400') ?><span class="xl:sr-only">Excel</span></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($pager): ?>
            <div class="border-t border-gray-200 px-4 py-4 dark:border-gray-700">
                <?= $pager->only(['tab', 'kelas_id', 'q'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/biodata.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
