<?php
/**
 * Isian Biodata Siswa — halaman utama admin.
 *
 * Urutan layar mengikuti urutan kerja admin:
 *   1. Pengingat tugas   — apa yang harus dikerjakan sekarang (periksa / buka form)
 *   2. Kartu angka       — bisa diklik, langsung membuka tab statusnya
 *   3. Form & tautan     — saklar buka/tutup, batas waktu, salin/bagikan tautan
 *      Laporan Excel     — unduh semua kelas / satu kelas
 *   4. Tab daftar        — baris tabel di layar lebar, kartu di HP (tanpa geser samping)
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

// Warna per status — dipakai kartu angka, tab, dan lencana supaya konsisten.
$warna = [
    'menunggu'  => ['titik' => 'bg-blue-500',    'teks' => 'text-blue-700',    'latar' => 'bg-blue-50',    'garis' => 'border-blue-200',    'aktif' => 'bg-blue-600', 'cincin' => 'ring-blue-300'],
    'perbaikan' => ['titik' => 'bg-amber-500',   'teks' => 'text-amber-800',   'latar' => 'bg-amber-50',   'garis' => 'border-amber-200',   'aktif' => 'bg-amber-500', 'cincin' => 'ring-amber-300'],
    'disetujui' => ['titik' => 'bg-emerald-500', 'teks' => 'text-emerald-700', 'latar' => 'bg-emerald-50', 'garis' => 'border-emerald-200', 'aktif' => 'bg-emerald-600', 'cincin' => 'ring-emerald-300'],
    'belum'     => ['titik' => 'bg-slate-400',   'teks' => 'text-slate-700',   'latar' => 'bg-slate-50',   'garis' => 'border-slate-200',   'aktif' => 'bg-slate-600', 'cincin' => 'ring-slate-300'],
    'kelas'     => ['titik' => 'bg-brand-500',   'teks' => 'text-brand-700',   'latar' => 'bg-brand-50',   'garis' => 'border-brand-200',   'aktif' => 'bg-brand-600', 'cincin' => 'ring-brand-300'],
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
    ? ['bg-emerald-500', 'text-emerald-700']
    : ($r >= 34 ? ['bg-amber-500', 'text-amber-700'] : ['bg-red-500', 'text-red-700']);

// Ikon kecil (garis) — dipakai ulang.
$ikon = static fn (string $d, string $kelas = 'w-4 h-4'): string => '<svg class="' . $kelas . '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
$IKON = [
    'periksa' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    'unduh'   => 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'salin'   => 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
    'jam'     => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'kosong'  => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    'panah'   => 'M13 7l5 5m0 0l-5 5m5-5H6',
    'orang'   => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
];
$waSvg = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

/** Keadaan kosong yang ramah: ikon + judul + saran langkah berikutnya. */
$kosong = static function (string $judul, string $saran, string $aksiHtml = '') use ($ikon, $IKON): string {
    return '<div class="px-6 py-12 text-center">'
        . '<div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">' . $ikon($IKON['kosong'], 'w-7 h-7') . '</div>'
        . '<p class="mt-3 font-bold text-slate-700">' . $judul . '</p>'
        . '<p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">' . $saran . '</p>'
        . ($aksiHtml !== '' ? '<div class="mt-4 flex flex-wrap justify-center gap-2">' . $aksiHtml . '</div>' : '')
        . '</div>';
};
$tombolSekunder = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition';
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
        • Tab <b>Belum Mengisi</b> + pilih satu kelas → muncul pesan WhatsApp berisi daftar nama untuk dikirim ke wali kelas.</p>
        <p class="mt-1">• Form bisa <b>dibuka/ditutup</b> kapan saja, dan bisa diberi <b>batas waktu</b> agar tertutup otomatis.</p>
        <p class="mt-1">• <b>Unduh Laporan</b> — Excel siap cetak berisi rekap per kelas dan daftar nama tiap kelas (lengkap / belum lengkap + kolom yang masih kurang), lengkap dengan kop dan tanda tangan. Data biodata lengkap (nama orang tua, alamat, dll.) ada di <b>Master Data → Siswa → Export</b>. “Lengkap” dihitung dari data <b>Master Siswa</b>, jadi isian yang belum disetujui belum terhitung lengkap.</p>',
]) ?>

<div x-data="biodataAdmin" class="space-y-5">

    <!-- ================= 1. Pengingat tugas ================= -->
    <?php if (! $terbuka): ?>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4">
            <div class="flex items-start gap-3 flex-1 min-w-0">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><?= $ikon('M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'w-5 h-5') ?></span>
                <div class="text-sm text-amber-900">
                    <p class="font-bold">Form sedang DITUTUP — siswa belum bisa mengisi.</p>
                    <p class="mt-0.5"><?= $lewatBatas ? 'Batas waktu pengisian (' . esc($fmtTgl($batas)) . ') sudah lewat.' : 'Siswa yang membuka tautan melihat pesan “Pengisian Sedang Ditutup”.' ?></p>
                </div>
            </div>
            <a href="#formIsian" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 px-4 py-2.5 text-sm font-bold text-white shrink-0">Atur Form <?= $ikon($IKON['panah']) ?></a>
        </div>
    <?php endif; ?>

    <?php if ($ringkas['menunggu'] > 0): ?>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-white px-5 py-4">
            <div class="flex items-start gap-3 flex-1 min-w-0">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white"><?= $ikon($IKON['periksa'], 'w-5 h-5') ?></span>
                <div class="text-sm text-slate-700">
                    <p class="font-bold text-slate-900">Ada <span class="tabular-nums"><?= $fmtAngka($ringkas['menunggu']) ?></span> isian menunggu diperiksa.</p>
                    <p class="mt-0.5">Mulai dari kiriman terlama — setelah disetujui, isian berikutnya langsung terbuka.</p>
                </div>
            </div>
            <?php if ($pertamaId > 0): ?>
                <a href="<?= esc(site_url('admin/biodata/' . $pertamaId), 'attr') ?>" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shrink-0">Periksa Sekarang <?= $ikon($IKON['panah']) ?></a>
            <?php endif; ?>
        </div>
    <?php elseif ($terbuka && $ringkas['sudah'] > 0): ?>
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm text-emerald-800">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white"><?= $ikon('M5 13l4 4L19 7', 'w-5 h-5') ?></span>
            <p><b>Semua isian yang masuk sudah diperiksa.</b> Isian baru dari siswa akan muncul di sini.</p>
        </div>
    <?php endif; ?>

    <!-- ================= 2. Kartu angka (bisa diklik) ================= -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-4">
        <a href="<?= esc($urlTab('kelas'), 'attr') ?>" class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm hover:border-brand-300 hover:shadow transition">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Progres pengisian</p>
            <p class="mt-1 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-slate-900 tabular-nums"><?= esc($ringkas['persen_teks']) ?></span>
                <span class="text-sm text-slate-500"><b class="text-slate-700 tabular-nums"><?= $fmtAngka($ringkas['sudah']) ?></b> dari <span class="tabular-nums"><?= $fmtAngka($ringkas['total']) ?></span> siswa aktif</span>
            </p>
            <div class="mt-3 h-2.5 w-full rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow="<?= $ringkas['rasio'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pengisian biodata">
                <div class="h-full rounded-full bg-brand-500" style="width: <?= $ringkas['rasio'] ?>%"></div>
            </div>
            <p class="mt-2 text-xs font-semibold text-brand-600">Lihat rekap per kelas →</p>
        </a>
        <?php foreach ([
            'menunggu'  => ['Menunggu', 'perlu diperiksa'],
            'perbaikan' => ['Perbaikan', 'sedang diperbaiki siswa'],
            'disetujui' => ['Disetujui', 'sudah di Master Siswa'],
            'belum'     => ['Belum mengisi', 'perlu dikejar'],
        ] as $kunci => [$label, $ket]): $w = $warna[$kunci]; $aktif = $tab === $kunci; ?>
            <a href="<?= esc($urlTab($kunci), 'attr') ?>" aria-current="<?= $aktif ? 'page' : 'false' ?>"
               class="rounded-2xl border p-4 shadow-sm transition hover:shadow <?= $aktif ? $w['latar'] . ' ' . $w['garis'] . ' ring-2 ring-offset-1 ' . $w['cincin'] : 'bg-white border-slate-200 hover:border-slate-300' ?>">
                <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide <?= $w['teks'] ?>">
                    <span class="h-2 w-2 rounded-full <?= $w['titik'] ?>" aria-hidden="true"></span><?= esc($label) ?>
                </p>
                <p class="mt-1 text-2xl sm:text-3xl font-extrabold text-slate-900 tabular-nums"><?= $fmtAngka($ringkas[$kunci]) ?></p>
                <p class="text-xs text-slate-500"><?= esc($ket) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ================= 3. Form & tautan + Laporan ================= -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
        <section id="formIsian" class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm scroll-mt-24">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
                <div>
                    <h2 class="font-bold text-slate-800">Form Isian untuk Siswa</h2>
                    <p class="text-xs text-slate-500"><?= $terbuka ? ($batasTeks !== '' ? 'Tertutup otomatis ' . esc($batasTeks) . '.' : 'Terbuka tanpa batas waktu.') : 'Siswa belum bisa mengisi.' ?></p>
                </div>
                <!-- Saklar buka/tutup: satu klik langsung tersimpan (dengan konfirmasi). -->
                <form method="post" action="<?= site_url('admin/biodata/pengaturan') ?>" class="flex items-center gap-3">
                    <?= csrf_field() ?>
                    <?php if ($terbuka): ?>
                        <input type="hidden" name="biodata_tutup" value="<?= esc($batasInput, 'attr') ?>">
                        <span class="text-sm font-extrabold text-emerald-700">DIBUKA</span>
                        <button type="submit" role="switch" aria-checked="true" aria-label="Tutup form isian"
                                data-confirm="Tutup form sekarang? Siswa tidak bisa mengisi sampai form dibuka lagi."
                                class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full bg-emerald-500 transition hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2">
                            <span class="inline-block h-5 w-5 translate-x-6 rounded-full bg-white shadow transition"></span>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="biodata_open" value="1">
                        <input type="hidden" name="biodata_tutup" value="<?= $lewatBatas ? '' : esc($batasInput, 'attr') ?>">
                        <span class="text-sm font-extrabold text-slate-500">DITUTUP</span>
                        <button type="submit" role="switch" aria-checked="false" aria-label="Buka form isian"
                                data-confirm="<?= $lewatBatas ? 'Buka form sekarang? Batas waktu lama sudah lewat, jadi batas waktu dihapus (form terbuka sampai Anda menutupnya).' : 'Buka form sekarang? Siswa bisa langsung mengisi lewat tautan.' ?>"
                                class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full bg-slate-300 transition hover:bg-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
                            <span class="inline-block h-5 w-5 translate-x-1 rounded-full bg-white shadow transition"></span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="px-5 sm:px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5" for="tautanForm">Tautan untuk dibagikan</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input id="tautanForm" type="text" readonly value="<?= esc($tautan, 'attr') ?>" class="flex-1 min-w-0 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-brand-700" @focus="$event.target.select()">
                        <div class="grid grid-cols-2 sm:flex gap-2">
                            <button type="button" @click="salin(<?= esc(json_encode($tautan), 'attr') ?>, 'tautan')" class="<?= $tombolSekunder ?> !py-2.5">
                                <?= $ikon($IKON['salin']) ?><span x-text="tersalin === 'tautan' ? 'Tersalin ✓' : 'Salin'">Salin</span>
                            </button>
                            <button type="button" @click="wa($refs.pesanBagikan.value)" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition">
                                <?= $waSvg ?><span class="sm:hidden">Bagikan</span><span class="hidden sm:inline">Bagikan ke WA</span>
                            </button>
                        </div>
                    </div>
                    <textarea x-ref="pesanBagikan" class="hidden" aria-hidden="true"><?= esc($pesanBagikan) ?></textarea>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Tautan cadangan (bila subdomain bermasalah): <a href="<?= esc($tautanAlt, 'attr') ?>" target="_blank" rel="noopener" class="font-semibold text-brand-600 hover:underline break-all"><?= esc($tautanAlt) ?></a>
                    </p>
                </div>

                <!-- Batas waktu (opsional) -->
                <form method="post" action="<?= site_url('admin/biodata/pengaturan') ?>" class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                    <?= csrf_field() ?>
                    <?php if ($bukaDiSetelan): ?><input type="hidden" name="biodata_open" value="1"><?php endif; ?>
                    <label class="block text-sm font-semibold text-slate-700" for="batasTutup">Tutup otomatis pada <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <div class="mt-1.5 flex flex-col sm:flex-row gap-2">
                        <input type="datetime-local" id="batasTutup" name="biodata_tutup" value="<?= esc($batasInput, 'attr') ?>"
                               class="flex-1 min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                        <button class="rounded-lg bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-semibold text-white transition">Simpan Batas Waktu</button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Lewat waktu ini form tertutup sendiri. Kosongkan lalu simpan untuk menghapus batas.</p>
                </form>

                <?php if ((int) $ringkas['sudah'] === 0): ?>
                    <ol class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600">
                        <li class="rounded-lg border border-slate-200 px-3 py-2"><b class="text-brand-700">1.</b> Nyalakan saklar <b>DIBUKA</b>.</li>
                        <li class="rounded-lg border border-slate-200 px-3 py-2"><b class="text-brand-700">2.</b> <b>Bagikan ke WA</b> grup siswa / wali kelas.</li>
                        <li class="rounded-lg border border-slate-200 px-3 py-2"><b class="text-brand-700">3.</b> Periksa &amp; <b>setujui</b> isian yang masuk.</li>
                    </ol>
                <?php endif; ?>
            </div>
        </section>

        <!-- Laporan Excel siap cetak -->
        <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                <h2 class="font-bold text-slate-800">Laporan Kelengkapan (Excel)</h2>
                <p class="text-xs text-slate-500">Siap cetak, dengan kop &amp; tanda tangan.</p>
            </div>
            <form method="get" action="<?= site_url('admin/biodata/laporan') ?>" data-noload class="px-5 sm:px-6 py-5 flex flex-col gap-3 flex-1">
                <ul class="space-y-1.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Rekap jumlah per kelas</li>
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Daftar nama tiap kelas: lengkap / belum</li>
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Kolom yang masih kurang per siswa</li>
                </ul>
                <div class="mt-auto space-y-2">
                    <label class="sr-only" for="laporanKelas">Kelas untuk laporan</label>
                    <select id="laporanKelas" name="kelas_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="">Semua kelas</option>
                        <?php foreach ($kelasOpts as $id => $nama): ?>
                            <option value="<?= (int) $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition">
                        <?= $ikon($IKON['unduh']) ?>Unduh Laporan
                    </button>
                    <p class="text-[11px] text-slate-400 leading-snug">Butuh data lengkap (nama orang tua, alamat, dll.)? Pakai <a href="<?= site_url('admin/master/siswa') ?>" class="font-semibold text-brand-600 hover:underline">Master Siswa → Export</a>.</p>
                </div>
            </form>
        </section>
    </div>

    <!-- ================= 4. Tab daftar ================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" id="daftar">
        <nav x-ref="tabNav" class="relative flex gap-1 overflow-x-auto border-b border-slate-200 px-2 sm:px-3 pt-2" aria-label="Tab isian biodata">
            <?php foreach ($tabs as $kunci => [$label, $jumlah]): $aktif = $tab === $kunci; $w = $warna[$kunci]; ?>
                <a href="<?= esc($urlTab($kunci), 'attr') ?>" <?= $aktif ? 'aria-current="page"' : '' ?>
                   class="shrink-0 inline-flex items-center gap-2 rounded-t-lg px-3.5 sm:px-4 py-2.5 text-sm font-semibold border-b-2 transition <?= $aktif ? 'border-current ' . $w['teks'] . ' ' . $w['latar'] : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50' ?>">
                    <?php if ($kunci !== 'kelas'): ?><span class="h-2 w-2 rounded-full <?= $w['titik'] ?>" aria-hidden="true"></span><?php endif; ?>
                    <?= esc($label) ?>
                    <?php if ($jumlah !== null): ?><span class="rounded-full px-2 py-0.5 text-xs tabular-nums <?= $aktif ? $w['aktif'] . ' text-white' : 'bg-slate-100 text-slate-600' ?>"><?= $fmtAngka($jumlah) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tab !== 'kelas'): ?>
            <form method="get" action="<?= site_url('admin/biodata') ?>" data-noload class="flex flex-col sm:flex-row gap-2 px-4 sm:px-5 py-3.5 border-b border-slate-100 bg-slate-50/60">
                <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                <select name="kelas_id" data-autosubmit class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 outline-none" aria-label="Saring kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelasOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><?= $ikon('M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z') ?></span>
                    <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama atau NISN…" aria-label="Cari nama atau NISN"
                           class="w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-sm focus:border-brand-500 outline-none">
                </div>
                <div class="flex gap-2">
                    <button class="flex-1 sm:flex-none rounded-lg bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-semibold text-white">Cari</button>
                    <?php if ($kelasId > 0 || $q !== ''): ?>
                        <a href="<?= site_url('admin/biodata') . '?tab=' . $tab ?>" class="flex-1 sm:flex-none <?= $tombolSekunder ?>">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($tab === 'menunggu'): ?>
            <!-- ============ MENUNGGU ============ -->
            <?php if ($total > 0): ?>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 sm:px-5 py-3 border-b border-slate-100">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <label class="inline-flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" @change="pilihSemua($event)" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Pilih semua
                        </label>
                        <span class="text-slate-500"><b class="tabular-nums text-slate-700"><?= $fmtAngka($total) ?></b> menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama) : '' ?> · terlama di atas</span>
                    </div>
                    <div class="grid grid-cols-2 sm:flex gap-2">
                        <button type="submit" form="formMassal" :disabled="jumlah === 0" data-confirm="Setujui isian yang dicentang? Data akan langsung masuk ke Master Siswa."
                                class="rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed px-4 py-2 text-sm font-semibold text-white transition">
                            ✓ Setujui<span class="hidden sm:inline">&nbsp;terpilih</span> (<span x-text="jumlah">0</span>)
                        </button>
                        <form method="post" action="<?= site_url('admin/biodata/setujui-massal') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="all">
                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                            <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                            <?php $n = min($total, $maksMassal); ?>
                            <button data-confirm="Setujui <?= $n ?> isian menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama, 'attr') : '' ?> TANPA diperiksa satu per satu? Data akan langsung masuk ke Master Siswa."
                                    class="w-full rounded-lg border border-emerald-300 bg-white hover:bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition">
                                Setujui semua (<?= $n ?>)
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
                        ? $kosong('Tidak ada isian menunggu yang cocok.', 'Coba kelas lain atau hapus kata kunci pencarian.', '<a href="' . site_url('admin/biodata') . '?tab=menunggu" class="' . $tombolSekunder . '">Tampilkan semua</a>')
                        : $kosong('Tidak ada isian yang menunggu diperiksa.', 'Isian baru dari siswa otomatis muncul di sini. Kejar siswa yang belum mengisi lewat tab <b>Belum Mengisi</b>.', '<a href="' . esc($urlTab('belum'), 'attr') . '" class="' . $tombolSekunder . '">' . $ikon($IKON['orang']) . 'Lihat yang belum mengisi</a>') ?>
                <?php else: ?>
                    <div class="hidden md:grid grid-cols-[2.5rem_minmax(0,1fr)_8rem_10rem_7.5rem] gap-3 px-5 py-2.5 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 border-b border-slate-100">
                        <span></span><span>Nama Siswa</span><span>Kelas</span><span>Dikirim</span><span class="text-right">Aksi</span>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r): $perbaikanKe = (int) $r['kirim_ke'] - 1; ?>
                            <li class="grid grid-cols-[2rem_minmax(0,1fr)_auto] md:grid-cols-[2.5rem_minmax(0,1fr)_8rem_10rem_7.5rem] items-center gap-x-3 gap-y-1 px-4 md:px-5 py-3 hover:bg-slate-50 transition">
                                <input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="bio-cek h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Pilih <?= esc($r['nama'], 'attr') ?>">
                                <div class="min-w-0">
                                    <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-semibold text-slate-800 hover:text-brand-700"><?= esc($r['nama']) ?></a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                        <span>NISN <?= esc($r['nisn'] ?? '—') ?></span>
                                        <span class="md:hidden basis-full"><?= esc($r['nama_kelas'] ?? '—') ?> · <span title="<?= esc($fmtTgl($r['updated_at']), 'attr') ?>"><?= esc($relatif($r['updated_at'])) ?></span></span>
                                        <?php if ($perbaikanKe > 0): ?><span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-bold text-amber-800">Hasil perbaikan<?= $perbaikanKe > 1 ? ' ke-' . $perbaikanKe : '' ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <span class="hidden md:block text-sm text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                                <span class="hidden md:flex items-center gap-1.5 text-sm text-slate-600" title="<?= esc($fmtTgl($r['updated_at']), 'attr') ?>"><?= $ikon($IKON['jam'], 'w-3.5 h-3.5 text-slate-400') ?><?= esc($relatif($r['updated_at'])) ?></span>
                                <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="justify-self-end inline-flex items-center gap-1 rounded-lg bg-blue-600 hover:bg-blue-700 px-3.5 py-2 text-xs font-bold text-white transition">Periksa <?= $ikon($IKON['panah'], 'w-3.5 h-3.5') ?></a>
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
                    ? $kosong('Tidak ada data yang cocok.', 'Coba kelas lain atau hapus kata kunci pencarian.')
                    : ($diperbaiki
                        ? $kosong('Tidak ada isian yang sedang diperbaiki.', 'Isian yang Anda <b>Kembalikan</b> ke siswa akan tampil di sini sampai siswa mengirim ulang.')
                        : $kosong('Belum ada isian yang disetujui.', 'Periksa isian di tab <b>Menunggu Verifikasi</b>, lalu setujui agar datanya masuk ke Master Siswa.', '<a href="' . esc($urlTab('menunggu'), 'attr') . '" class="' . $tombolSekunder . '">Buka tab Menunggu</a>')) ?>
            <?php else: ?>
                <div class="hidden md:grid grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)_10rem_6rem] gap-3 px-5 py-2.5 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 border-b border-slate-100">
                    <span>Nama Siswa</span><span>Kelas</span><span><?= $diperbaiki ? 'Catatan untuk siswa' : 'NISN' ?></span><span><?= $diperbaiki ? 'Dikembalikan' : 'Disetujui' ?></span><span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): $waktu = $diperbaiki ? $r['updated_at'] : $r['diverifikasi_at']; ?>
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] md:grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)_10rem_6rem] items-center gap-x-3 gap-y-1 px-4 md:px-5 py-3 hover:bg-slate-50 transition">
                            <div class="min-w-0">
                                <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-semibold text-slate-800 hover:text-brand-700"><?= esc($r['nama']) ?></a>
                                <p class="md:hidden mt-0.5 text-xs text-slate-500"><?= esc($r['nama_kelas'] ?? '—') ?> · <?= esc($relatif($waktu)) ?></p>
                                <?php if ($diperbaiki): ?><p class="md:hidden mt-1 text-xs text-amber-800 bg-amber-50 rounded px-2 py-1"><?= esc($r['catatan_admin'] ?? '—') ?></p><?php endif; ?>
                            </div>
                            <span class="hidden md:block text-sm text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                            <span class="hidden md:block text-sm <?= $diperbaiki ? 'text-amber-800' : 'text-slate-600 tabular-nums' ?>"><?= esc($diperbaiki ? ($r['catatan_admin'] ?? '—') : ($r['nisn'] ?? '—')) ?></span>
                            <span class="hidden md:block text-sm text-slate-600" title="<?= esc($fmtTgl($waktu), 'attr') ?>"><?= esc($relatif($waktu)) ?></span>
                            <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="justify-self-end rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Lihat</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php elseif ($tab === 'belum'): ?>
            <!-- ============ BELUM MENGISI ============ -->
            <?php if ($pesanBelum !== ''): ?>
                <div class="px-4 sm:px-5 py-4 bg-emerald-50/70 border-b border-emerald-100">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?= $fmtAngka($total) ?> siswa <?= esc($kelasNama) ?> belum mengisi</p>
                            <p class="text-xs text-slate-600">Kirim daftar namanya ke wali kelas / grup kelas lewat WhatsApp.</p>
                        </div>
                        <div class="grid grid-cols-2 sm:flex gap-2">
                            <button type="button" @click="wa($refs.pesanBelum.value)" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition"><?= $waSvg ?>Kirim ke WA</button>
                            <button type="button" @click="salin($refs.pesanBelum.value, 'belum')" class="<?= $tombolSekunder ?> !py-2.5"><?= $ikon($IKON['salin']) ?><span x-text="tersalin === 'belum' ? 'Tersalin ✓' : 'Salin pesan'">Salin pesan</span></button>
                        </div>
                    </div>
                    <details class="mt-3 group">
                        <summary class="cursor-pointer text-xs font-semibold text-emerald-800 hover:underline">Lihat isi pesan</summary>
                        <textarea x-ref="pesanBelum" readonly rows="6" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 font-mono"><?= esc($pesanBelum) ?></textarea>
                    </details>
                </div>
            <?php elseif ($kelasId === 0 && $total > 0): ?>
                <div class="flex items-start gap-2 px-4 sm:px-5 py-3 text-sm text-slate-600 bg-blue-50/60 border-b border-blue-100">
                    <span class="text-blue-600 mt-0.5"><?= $ikon('M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z') ?></span>
                    <p>Pilih <b>satu kelas</b> di atas untuk mendapatkan pesan WhatsApp berisi daftar nama yang belum mengisi.</p>
                </div>
            <?php endif; ?>
            <?php if ($rows === []): ?>
                <?= $kosong($kelasId || $q !== '' ? 'Semua siswa yang cocok sudah mengisi 🎉' : 'Semua siswa aktif sudah mengisi 🎉', 'Tidak ada lagi yang perlu dikejar di daftar ini.') ?>
            <?php else: ?>
                <div class="hidden md:grid grid-cols-[3.5rem_minmax(0,1fr)_4rem_9rem] gap-3 px-5 py-2.5 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 border-b border-slate-100">
                    <span>No</span><span>Nama Siswa</span><span class="text-center">JK</span><span>Kelas</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($rows as $i => $r): ?>
                        <li class="grid grid-cols-[2.5rem_minmax(0,1fr)_auto] md:grid-cols-[3.5rem_minmax(0,1fr)_4rem_9rem] items-center gap-3 px-4 md:px-5 py-2.5 text-sm hover:bg-slate-50">
                            <span class="text-slate-400 tabular-nums"><?= $mulai + $i + 1 ?></span>
                            <span class="font-medium text-slate-800 min-w-0"><?= esc($r['nama']) ?><span class="md:hidden text-xs font-normal text-slate-500"> · <?= esc($r['jenis_kelamin'] ?? '—') ?></span></span>
                            <span class="hidden md:block text-center text-slate-600"><?= esc($r['jenis_kelamin'] ?? '—') ?></span>
                            <span class="text-xs md:text-sm text-slate-600 md:text-slate-700 text-right md:text-left"><?= esc($r['nama_kelas'] ?? '—') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: ?>
            <!-- ============ REKAP PER KELAS ============ -->
            <?php if ($perKelas === []): ?>
                <?= $kosong('Belum ada kelas yang berisi siswa aktif.', 'Tambahkan siswa di Master Data → Siswa.') ?>
            <?php else: ?>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 sm:px-5 py-2.5 text-xs text-slate-500 border-b border-slate-100">
                    <span>Warna progres:</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-red-500"></span>di bawah 34%</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-amber-500"></span>34–66%</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full bg-emerald-500"></span>67% ke atas</span>
                </div>
                <div class="hidden xl:grid grid-cols-[7rem_minmax(0,1fr)_3.5rem_4.5rem_4.5rem_4.75rem_3.5rem_8.5rem_10.75rem] gap-x-3 px-5 py-2.5 bg-slate-50 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-100">
                    <span>Kelas</span><span>Wali Kelas</span><span class="text-right">Siswa</span><span class="text-right">Menunggu</span><span class="text-right">Disetujui</span><span class="text-right">Perbaikan</span><span class="text-right">Belum</span><span>Sudah mengisi</span><span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($perKelas as $k): [$bar, $teksPersen] = $warnaProgres((float) $k['rasio']); ?>
                        <li class="grid grid-cols-2 xl:grid-cols-[7rem_minmax(0,1fr)_3.5rem_4.5rem_4.5rem_4.75rem_3.5rem_8.5rem_10.75rem] items-center gap-x-3 gap-y-2 px-4 xl:px-5 py-3 hover:bg-slate-50 text-sm">
                            <div class="col-span-2 xl:col-span-1 flex items-baseline justify-between xl:block">
                                <span class="font-bold text-slate-800 whitespace-nowrap"><?= esc($k['nama_kelas']) ?></span>
                                <span class="xl:hidden text-xs text-slate-500 truncate ml-2"><?= esc($k['wali'] ?? '—') ?></span>
                            </div>
                            <span class="hidden xl:block text-slate-600 truncate" title="<?= esc($k['wali'] ?? '', 'attr') ?>"><?= esc($k['wali'] ?? '—') ?></span>
                            <span class="hidden xl:block text-right tabular-nums text-slate-700"><?= $k['total'] ?></span>
                            <span class="hidden xl:block text-right tabular-nums <?= $k['menunggu'] > 0 ? 'font-bold text-blue-700' : 'text-slate-400' ?>"><?= $k['menunggu'] ?></span>
                            <span class="hidden xl:block text-right tabular-nums <?= $k['disetujui'] > 0 ? 'text-emerald-700' : 'text-slate-400' ?>"><?= $k['disetujui'] ?></span>
                            <span class="hidden xl:block text-right tabular-nums <?= $k['perbaikan'] > 0 ? 'text-amber-700' : 'text-slate-400' ?>"><?= $k['perbaikan'] ?></span>
                            <span class="hidden xl:block text-right tabular-nums font-semibold <?= $k['belum'] > 0 ? 'text-slate-800' : 'text-slate-400' ?>"><?= $k['belum'] ?></span>
                            <!-- HP: ringkasan angka dalam satu baris -->
                            <p class="xl:hidden col-span-2 text-xs text-slate-500">
                                <?= $k['total'] ?> siswa · <span class="<?= $k['menunggu'] > 0 ? 'font-bold text-blue-700' : '' ?>"><?= $k['menunggu'] ?> menunggu</span> · <?= $k['disetujui'] ?> disetujui · <?= $k['perbaikan'] ?> perbaikan · <b class="text-slate-700"><?= $k['belum'] ?> belum</b>
                            </p>
                            <div class="col-span-2 xl:col-span-1 flex items-center gap-2" title="<?= $k['sudah'] ?> dari <?= $k['total'] ?> siswa sudah mengisi">
                                <div class="h-2.5 flex-1 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full <?= $bar ?>" style="width: <?= $k['rasio'] ?>%"></div></div>
                                <span class="w-12 shrink-0 text-right text-xs font-bold tabular-nums <?= $teksPersen ?>"><?= esc($k['persen_teks']) ?></span>
                            </div>
                            <div class="col-span-2 xl:col-span-1 flex gap-2 xl:justify-end">
                                <?php if ($k['belum'] > 0): ?>
                                    <a href="<?= site_url('admin/biodata') . '?tab=belum&kelas_id=' . (int) $k['id'] ?>" class="flex-1 xl:flex-none inline-flex items-center justify-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50" title="Daftar & pesan WA siswa yang belum mengisi">Yang belum <?= $ikon($IKON['panah'], 'w-3.5 h-3.5') ?></a>
                                <?php else: ?>
                                    <span class="flex-1 xl:flex-none inline-flex items-center justify-center rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700">Semua mengisi ✓</span>
                                <?php endif; ?>
                                <a href="<?= site_url('admin/biodata/laporan') . '?kelas_id=' . (int) $k['id'] ?>" title="Unduh laporan Excel kelas <?= esc($k['nama_kelas'], 'attr') ?>" class="flex-1 xl:flex-none inline-flex items-center justify-center gap-1 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"><?= $ikon($IKON['unduh'], 'w-3.5 h-3.5') ?><span class="xl:sr-only">Excel</span></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($pager): ?>
            <div class="px-5 py-4 border-t border-slate-100">
                <?= $pager->only(['tab', 'kelas_id', 'q'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/biodata.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/biodata.js') ?>"></script>
<?= $this->endSection() ?>
