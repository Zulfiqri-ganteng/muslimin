<?php
/**
 * Ceklis KOREKSI honor ("KOREKSI NILAI": pembagian lembar jawaban ke guru per rombel) — KHUSUS ADMIN.
 * Angka resmi dihitung SERVER (Libraries\HonorKoreksi); layar hanya menampilkannya.
 * Rombak tampilan F7: kartu kit, alur kerja 4 langkah dengan tombol ber-ikon, panel ber-aria-expanded, ui_peringatan/ui_kosong,
 * kotak peserta = .inp (tanda "diketik" kuning ditukar koreksi-grid.js), matriks dengan lebar kolom kelas Tailwind (tanpa style=)
 * + mode gelap; konfirmasi = data-confirm (dulu onclick confirm; ikut bekerja pada salinan di kartu "Per guru"); skrip ber-nonce.
 * Komponen Alpine "koreksiGrid" (koreksi-grid.js + kartu-guru.js). Id & atribut data-* dibaca JS — jangan diubah tanpa menyesuaikannya.
 *
 * @var string $slug  @var array $periode  @var string $label  @var array $m  hasil HonorKoreksi::muat()
 * @var array  $banding  hasil HonorKoreksi::bandingkanDenganHonor()  @var string $status  @var list<array> $penerima
 * @var list<string> $mapelOpsi  @var list<array> $lain  honor lain yang punya ceklis  @var string $kembali  @var string $base  @var string $qtp
 * @var array{aktif:bool, tahun:string, guru:int, mapel:int, sel:int, url:string} $skbm  isi SKBM untuk tahun ajaran honor ini
 */
use App\Models\UjianPeriodeModel;

$rp        = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$terkunci  = $status === 'dikunci';
$kelas     = $m['kelas'];
$grup      = $m['grup'];
$guru      = $m['guru'];
$nKelas    = count($kelas);
$adaCeklis = $guru !== [];
$jmlBeda   = (int) ($banding['beda'] ?? 0);
// Langkah berikutnya dibuka otomatis: ceklis kosong tetapi SKBM tahun ini ada → "Isi dari SKBM" (satu klik);
// baru saja mengisi ceklis (flash 'koreksi_panel') dan masih ada beda dengan honor → panel "Terapkan ke kolom Koreksi honor".
$panelAwal = '';
if (! $terkunci) {
    if ((string) session()->getFlashdata('koreksi_panel') === 'terapkan' && $adaCeklis && $banding['ada_komponen'] && $jmlBeda > 0) {
        $panelAwal = 'terapkan';
    } elseif (! $adaCeklis && ($skbm['guru'] ?? 0) > 0) {
        $panelAwal = 'isi-skbm';
    }
}
$cfg = [
    'urlSel'       => $base . '/sel',
    'urlPeserta'   => $base . '/peserta',
    'urlMapelUbah' => $base . '/mapel/__ID__/ubah',
    'periodeId'    => (int) $periode['id'],
    'csrfName'     => csrf_token(),
    'terkunci'     => $terkunci,
    'panelAwal'    => $panelAwal,
    // bahan tampilan "Per guru" (kartu-guru.js): kolom kelas menurut urutan tabel + kelompoknya
    'kelas'        => array_map(static fn (array $k): array => ['id' => (int) $k['id'], 'label' => $k['label'], 'nama' => $k['nama']], $kelas),
    'grup'         => $grup,
];
$peringatan = session()->getFlashdata('koreksi_peringatan') ?: [];
$statusLabel = ['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'];
// Langkah yang sebaiknya dikerjakan berikutnya: 1 = isi ceklis, 3 = terapkan ke honor, 0 = semua beres.
$langkahBerikut = ! $adaCeklis ? 1 : (($banding['ada_komponen'] && $jmlBeda > 0) ? 3 : 0);

$kotak = static fn (string $lbl, string $isi, string $id = '', string $warna = ''): string => '<div class="rounded-lg border px-3 py-2 '
    . ($warna !== '' ? $warna : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50') . '">'
    . '<p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">' . $lbl . '</p>'
    . '<p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . $isi . '</p></div>';
$kuning = 'border-yellow-300 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20';
// Kartu langkah alur kerja & bulatan nomornya.
$kartuLangkah = static fn (bool $selesai, bool $sorot): string => 'rounded-lg border p-3 ' . ($sorot
    ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600 dark:border-brand-500 dark:bg-brand-900/20 dark:ring-brand-500'
    : ($selesai ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800'));
$bulat = static fn (bool $selesai, bool $sorot): string => 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold '
    . ($selesai ? 'bg-green-600 text-white' : ($sorot ? 'bg-brand-700 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-200'));
$isiBulat = static fn (bool $selesai, string $no): string => $selesai ? ikon('check', 'h-3.5 w-3.5') . '<span class="sr-only">selesai</span>' : $no;
$panelKelas = 'border-t border-gray-200 bg-gray-50 p-4 md:px-6 dark:border-gray-700 dark:bg-gray-800/60';
// Kelas sel matriks.
$kepalaTh = 'sticky top-0 border-b border-r border-gray-300 bg-gray-100 px-2 py-2 dark:border-gray-600 dark:bg-gray-700';
$sel      = 'border-b border-r border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-800';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Honor</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="koreksiGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>" class="mx-auto max-w-[110rem] space-y-4">
    <input type="hidden" id="koreksi-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <?php if ($peringatan !== []): ?>
        <?= ui_peringatan('awas', $peringatan, ['judul' => 'Perhatikan hasil impor:']) ?>
    <?php endif; ?>

    <!-- Pesan simpan otomatis (satu baris, diganti tiap simpan) -->
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" x-cloak x-show="pesan !== ''" x-transition.opacity>
        <div class="pointer-events-auto max-w-md rounded-lg px-4 py-3 text-sm font-medium text-white shadow-sm" :class="galat ? 'bg-red-700' : 'bg-green-700'" x-text="pesan" role="status" aria-live="polite"></div>
    </div>

    <!-- ===== Ringkasan & aksi ===== -->
    <section class="kartu" aria-labelledby="judul-koreksi">
        <div class="kartu-isi">
            <p class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                <?= esc($label) ?>
                <span class="lencana <?= $status === 'dikunci' ? 'lencana-abu' : ($status === 'final' ? 'lencana-hijau' : 'lencana-kuning') ?> normal-case"><?= esc($statusLabel[$status] ?? $status) ?></span>
            </p>
            <h2 id="judul-koreksi" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">Koreksi &mdash; pembagian lembar jawaban ke guru</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Lembar tiap guru = jumlah <b>peserta ujian</b> di kelas yang ia koreksi (per mata pelajaran). Total guru otomatis menjadi angka <b>Koreksi</b> di honor, dan bisa diunduh sebagai Excel &quot;KOREKSI NILAI&quot;.</p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?= $kotak('Guru di ceklis', (string) (int) $m['jumlah_guru'], 'ringkas-guru') ?>
                <?= $kotak('Baris mapel', (string) (int) $m['jumlah_mapel'], 'ringkas-mapel') ?>
                <div class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-800 dark:bg-brand-900/30">
                    <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:text-brand-300">Total lembar</p>
                    <p class="text-xl font-bold text-brand-700 tabular-nums dark:text-brand-300" id="ringkas-total"><?= $rp($m['total']) ?></p>
                </div>
                <?= $kotak('Beda dengan kolom Koreksi di honor', $banding['ada_komponen'] ? $jmlBeda . ' orang' : '—', '', $jmlBeda > 0 ? $kuning : '') ?>
            </div>

            <!-- Alur kerja: 4 langkah, yang berikutnya disorot -->
            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Alur kerja</p>
                    <?php if ($terkunci): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Honor dikunci &mdash; hanya bisa dilihat dan diunduh.</p>
                    <?php elseif ($langkahBerikut === 1): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Berikutnya: <b class="text-brand-700 dark:text-brand-400">Langkah 1 &mdash; isi ceklis</b><?= ($skbm['guru'] ?? 0) > 0 ? ' (SKBM ' . esc($skbm['tahun']) . ' sudah siap dipakai)' : '' ?></p>
                    <?php elseif ($langkahBerikut === 3): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Berikutnya: <b class="text-brand-700 dark:text-brand-400">Langkah 3 &mdash; terapkan ke honor</b> (<?= $jmlBeda ?> orang masih beda)</p>
                    <?php else: ?>
                        <p class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400"><?= ikon('check-circle', 'h-4 w-4') ?>Semua langkah beres &mdash; angka Koreksi di honor sudah sama dengan ceklis.</p>
                    <?php endif; ?>
                </div>
                <ol class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <?php $sel3 = $adaCeklis && $banding['ada_komponen'] && $jmlBeda === 0; ?>
                    <li class="<?= $kartuLangkah($adaCeklis, $langkahBerikut === 1) ?>">
                        <div class="flex items-start gap-2.5">
                            <span class="<?= $bulat($adaCeklis, $langkahBerikut === 1) ?>"><?= $isiBulat($adaCeklis, '1') ?></span>
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Isi ceklis</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= $adaCeklis ? (int) $m['jumlah_guru'] . ' guru · ' . (int) $m['jumlah_mapel'] . ' baris mapel' : 'Belum diisi — pilih sumbernya' ?></p></div>
                        </div>
                        <?php if (! $terkunci): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn btn-utama btn-xs" @click="alihPanel('isi-skbm')" :aria-expanded="panel === 'isi-skbm'" aria-controls="isi-skbm"><?= ikon('clipboard-list', 'h-3.5 w-3.5') ?>Isi dari SKBM</button>
                                <button type="button" class="btn btn-netral btn-xs" @click="alihPanel('isi')" :aria-expanded="panel === 'isi'" aria-controls="isi">Isi dari data pengampu</button>
                                <button type="button" class="btn btn-netral btn-xs" @click="alihPanel('impor')" :aria-expanded="panel === 'impor'" aria-controls="impor"><?= ikon('upload', 'h-3.5 w-3.5') ?>Impor dari Excel KOREKSI</button>
                                <?php if ($lain !== []): ?><button type="button" class="btn btn-netral btn-xs" @click="alihPanel('salin')" :aria-expanded="panel === 'salin'" aria-controls="salin"><?= ikon('file-copy', 'h-3.5 w-3.5') ?>Salin dari honor lain</button><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                    <li class="<?= $kartuLangkah(false, false) ?>">
                        <div class="flex items-start gap-2.5">
                            <span class="<?= $bulat(false, false) ?>">2</span>
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Periksa &amp; koreksi</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= $adaCeklis ? 'Ketuk sel kelas untuk mengubah; atur peserta tiap kelas' : 'Menunggu ceklis terisi' ?></p></div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php if (! $terkunci): ?><button type="button" class="btn btn-netral btn-xs" @click="alihPanel('tambah')" :aria-expanded="panel === 'tambah'" aria-controls="tambah"><?= ikon('plus', 'h-3.5 w-3.5') ?>Tambah guru / mapel</button><?php endif; ?>
                            <a href="#peserta-kelas" class="btn btn-netral btn-xs"><?= ikon('users', 'h-3.5 w-3.5') ?>Peserta per kelas</a>
                        </div>
                    </li>
                    <li class="<?= $kartuLangkah($sel3, $langkahBerikut === 3) ?>">
                        <div class="flex items-start gap-2.5">
                            <span class="<?= $bulat($sel3, $langkahBerikut === 3) ?>"><?= $isiBulat($sel3, '3') ?></span>
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Terapkan ke honor</p><p class="text-xs text-gray-500 dark:text-gray-400"><?php
                                if (! $adaCeklis) { echo 'Menunggu ceklis terisi'; } elseif (! $banding['ada_komponen']) { echo 'Honor tak punya komponen Koreksi'; } elseif ($jmlBeda > 0) { echo $jmlBeda . ' orang beda dengan kolom Koreksi'; } else { echo 'Sudah sama dengan kolom Koreksi'; }
                            ?></p></div>
                        </div>
                        <?php if ($adaCeklis): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sukses btn-xs" @click="alihPanel('terapkan')" :aria-expanded="panel === 'terapkan'" aria-controls="terapkan"><?= ikon('check', 'h-3.5 w-3.5') ?>Terapkan ke kolom Koreksi honor<?= $jmlBeda > 0 ? ' (' . $jmlBeda . ' beda)' : '' ?></button>
                            </div>
                        <?php endif; ?>
                    </li>
                    <li class="<?= $kartuLangkah(false, false) ?>">
                        <div class="flex items-start gap-2.5">
                            <span class="<?= $bulat(false, false) ?>">4</span>
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Unduh Excel</p><p class="text-xs text-gray-500 dark:text-gray-400">Format &quot;KOREKSI NILAI&quot; sekolah</p></div>
                        </div>
                        <?php if ($adaCeklis): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="<?= $base ?>/xlsx<?= $qtp ?>" class="btn btn-utama btn-xs"><?= ikon('download', 'h-3.5 w-3.5') ?>Unduh Excel KOREKSI</a>
                            </div>
                        <?php endif; ?>
                    </li>
                </ol>
                <?php if (! $terkunci && $adaCeklis): ?>
                    <p class="mt-3 text-right"><button type="button" class="btn btn-netral btn-xs text-red-700 dark:text-red-500" @click="alihPanel('kosong')" :aria-expanded="panel === 'kosong'" aria-controls="kosong"><?= ikon('trash-bin', 'h-3.5 w-3.5') ?>Kosongkan ceklis</button></p>
                <?php endif; ?>
            </div>
            <?php if ($terkunci): ?>
                <div class="mt-3"><?= ui_peringatan('netral', 'Honor ini DIKUNCI — ceklis hanya bisa dilihat dan diunduh. Buka kunci di tab Honor bila perlu mengubah.') ?></div>
            <?php endif; ?>
        </div>

        <?php if (! $terkunci): ?>
            <!-- Panel: isi dari SKBM -->
            <div id="isi-skbm" x-cloak x-show="panel === 'isi-skbm'" x-transition class="border-t border-brand-200 bg-brand-50 p-4 md:px-6 dark:border-brand-800 dark:bg-brand-900/20">
                <?php if (! $skbm['aktif']): ?>
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Fitur SKBM belum aktif di server ini: migrasi database belum dijalankan (<b>php spark migrate</b>).</p>
                <?php elseif ($skbm['guru'] === 0): ?>
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">SKBM tahun ajaran <b><?= esc($skbm['tahun']) ?></b> belum diisi. Isi dulu di menu <b>Guru &rarr; SKBM</b> (impor dari Excel jadwal sekolah, atau salin dari tahun lain), lalu kembali ke sini dan tekan tombol ini lagi.</p>
                    <a href="<?= esc($skbm['url'], 'attr') ?>#impor" class="btn btn-utama mt-3"><?= ikon('clipboard-list', 'h-4 w-4') ?>Buka menu SKBM</a>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/isi-skbm">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Ceklis diisi dari <b>SKBM <?= esc($skbm['tahun']) ?></b> (<?= (int) $skbm['guru'] ?> guru, <?= (int) $skbm['mapel'] ?> baris mapel, <?= (int) $skbm['sel'] ?> kelas): guru &times; mapel &times; kelas persis seperti SK Pembagian Tugas Mengajar. Hanya guru yang sudah jadi <b>penerima honor</b> yang dimasukkan, satu baris per nama mapel. Lembar tiap kelas mengikuti jumlah peserta kelas itu, dan hasilnya masih bisa dikoreksi per sel.</p>
                        <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                            <input type="checkbox" name="ganti" value="1" class="cek mt-0.5">
                            <span>Ganti seluruh ceklis yang sekarang <?= $adaCeklis ? '<b class="text-yellow-700 dark:text-yellow-400">(ceklis sekarang sudah berisi)</b>' : '' ?> (tanpa ini, guru yang sudah punya isi dilewati)</span>
                        </label>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button type="submit" class="btn btn-utama"><?= ikon('clipboard-list', 'h-4 w-4') ?>Isi dari SKBM</button>
                            <a href="<?= esc($skbm['url'], 'attr') ?>" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400">Lihat / ubah SKBM <?= esc($skbm['tahun']) ?></a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: isi dari pengampu -->
            <div id="isi" x-cloak x-show="panel === 'isi'" x-transition class="<?= $panelKelas ?>">
                <form method="post" action="<?= $base ?>/isi-pengampu">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Ceklis diisi dari <b>data pengampu</b> di Master (guru &times; mapel &times; kelas). Hanya guru yang sudah jadi <b>penerima honor</b> yang dimasukkan, satu baris per nama mapel. Hasilnya masih bisa dikoreksi per sel. Bila data pengampu belum sama dengan SK Pembagian Tugas Mengajar, lebih tepat pakai <b>Isi dari SKBM</b>; atau <b>Impor dari Excel KOREKSI</b> bila ingin persis seperti berkas sekolah.</p>
                    <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                        <input type="checkbox" name="ganti" value="1" class="cek mt-0.5">
                        <span>Ganti seluruh ceklis yang sekarang (tanpa ini, guru yang sudah punya isi dilewati)</span>
                    </label>
                    <button type="submit" class="btn btn-utama mt-4"><?= ikon('users', 'h-4 w-4') ?>Isi dari data pengampu</button>
                </form>
            </div>

            <!-- Panel: impor Excel -->
            <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="<?= $panelKelas ?>">
                <form method="post" action="<?= $base ?>/impor/unggah<?= $qtp ?>" enctype="multipart/form-data" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Pilih Excel &quot;KOREKSI NILAI&quot; dari sekolah (.xlsx). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> &mdash; belum ada yang tersimpan sampai kamu menekan Terapkan. Jumlah peserta tiap kelas diambil dari angka di kolom kelasnya.</p>
                    <label class="lbl" for="koreksi-berkas">Berkas Excel (.xlsx)</label>
                    <input type="file" id="koreksi-berkas" name="berkas" accept=".xlsx" required class="inp-berkas">
                    <button type="submit" class="btn btn-utama"><?= ikon('file-search', 'h-4 w-4') ?>Baca berkas</button>
                </form>
            </div>

            <!-- Panel: salin dari honor lain -->
            <?php if ($lain !== []): ?>
                <div id="salin" x-cloak x-show="panel === 'salin'" x-transition class="<?= $panelKelas ?>">
                    <form method="post" action="<?= $base ?>/salin" class="space-y-3">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Salin pembagian guru &times; mapel &times; kelas dari honor ujian lain (mis. ASTS ke ASAS). Penerima dicocokkan lewat Master Guru.</p>
                        <label class="sr-only" for="koreksi-sumber">Salin dari honor</label>
                        <select id="koreksi-sumber" name="sumber" class="inp sm:max-w-md">
                            <?php foreach ($lain as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= esc((UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran']) ?> (<?= (int) $d['guru'] ?> guru)</option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="peserta" value="1" class="cek mt-0.5"><span>Salin juga jumlah peserta tiap kelas (tanpa ini, peserta mengikuti siswa aktif)</span></label>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="ganti" value="1" class="cek mt-0.5"><span>Ganti ceklis yang sekarang <?= $adaCeklis ? '<b class="text-yellow-700 dark:text-yellow-400">(ceklis sekarang sudah berisi)</b>' : '' ?></span></label>
                        <button type="submit" class="btn btn-utama"><?= ikon('file-copy', 'h-4 w-4') ?>Salin ceklis</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Panel: tambah guru / mapel -->
            <div id="tambah" x-cloak x-show="panel === 'tambah'" x-transition class="<?= $panelKelas ?>">
                <form method="post" action="<?= $base ?>/mapel" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <div>
                        <label class="lbl" for="k-baris">Penerima honor</label>
                        <select id="k-baris" name="baris" required class="inp">
                            <option value="">— pilih —</option>
                            <?php foreach ($penerima as $b): ?><option value="<?= (int) $b['id'] ?>"><?= esc($b['nama']) ?><?= $b['jabatan'] ? ' — ' . esc($b['jabatan']) : '' ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="lbl" for="k-mapel">Mata pelajaran (tulis <b>-</b> bila tidak mengoreksi mapel)</label>
                        <input id="k-mapel" name="nama" type="text" required maxlength="150" list="k-mapel-opsi" autocomplete="off" class="inp">
                        <datalist id="k-mapel-opsi"><?php foreach ($mapelOpsi as $nm): ?><option value="<?= esc($nm, 'attr') ?>"><?php endforeach; ?></datalist>
                    </div>
                    <button type="submit" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Tambah baris</button>
                </form>
                <p class="hint">Setelah baris ditambahkan, klik sel kelas di tabel untuk menentukan kelas yang dikoreksi.</p>
            </div>

            <?php if ($adaCeklis): ?>
                <!-- Panel: kosongkan -->
                <div id="kosong" x-cloak x-show="panel === 'kosong'" x-transition class="border-t border-red-200 bg-red-50 p-4 md:px-6 dark:border-red-900 dark:bg-red-900/20">
                    <form method="post" action="<?= $base ?>/kosongkan">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-red-800 dark:text-red-300">Semua baris mapel dan kelasnya dibuang (<?= (int) $m['jumlah_mapel'] ?> baris, <?= (int) $m['jumlah_guru'] ?> guru). Angka Koreksi yang sudah ada di honor <b>tidak ikut berubah</b>.</p>
                        <label class="mt-2 flex cursor-pointer items-start gap-2.5 text-sm text-red-800 dark:text-red-300"><input type="checkbox" name="peserta" value="1" class="cek mt-0.5"><span>Buang juga jumlah peserta per kelas yang saya ketik</span></label>
                        <button type="submit" class="btn btn-bahaya mt-3" data-confirm="Kosongkan seluruh ceklis Koreksi? Tidak bisa dibatalkan." data-confirm-judul="Kosongkan ceklis?" data-confirm-ya="Ya, kosongkan" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Kosongkan ceklis</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($adaCeklis): ?>
            <!-- Panel: terapkan ke honor -->
            <div id="terapkan" x-cloak x-show="panel === 'terapkan'" x-transition class="<?= $panelKelas ?>">
                <?php if (! $banding['ada_komponen']): ?>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Honor ini tidak memuat komponen <b>Koreksi</b>, jadi tidak ada kolom yang bisa diisi. Aktifkan komponen Koreksi di Pengaturan Honor lalu tekan &quot;Perbarui tarif dari pengaturan&quot; di tab Honor.</p>
                <?php else: ?>
                    <?php $beda = array_values(array_filter($banding['baris'], static fn (array $b): bool => $b['beda'])); ?>
                    <?php if ($beda === []): ?>
                        <?= ui_peringatan('sukses', 'Sudah sama: angka Koreksi di honor tiap guru sama dengan total ceklis.') ?>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Angka berikut akan menggantikan isian <b>Koreksi</b> di honor. Isian yang kamu ketik sendiri (bertanda <span class="lencana lencana-kuning">diketik</span>) baru diganti bila kotak &quot;timpa&quot; dicentang. Rupiah dan total honor dihitung ulang otomatis.</p>
                        <div class="tabel-bungkus panel-gulir mt-3 max-h-72 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                            <table class="tabel">
                                <thead class="sticky top-0 z-10">
                                    <tr><th scope="col">Nama</th><th scope="col" class="text-right">Di honor sekarang</th><th scope="col" class="text-right">Dari ceklis</th><th scope="col"><span class="sr-only">Tanda</span></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($beda as $b): ?>
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white"><?= esc($b['nama']) ?></td>
                                            <td class="text-right text-gray-500 tabular-nums dark:text-gray-400"><?= $rp($b['sekarang']) ?></td>
                                            <td class="text-right font-semibold text-brand-700 tabular-nums dark:text-brand-400"><?= $rp($b['ceklis']) ?></td>
                                            <td><?= $b['manual'] ? '<span class="lencana lencana-kuning">diketik</span>' : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (! $terkunci): ?>
                            <form method="post" action="<?= $base ?>/terapkan" class="mt-3">
                                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="timpa" value="1"<?= count(array_filter($beda, static fn ($b) => $b['manual'])) > 0 ? ' checked' : '' ?> class="cek mt-0.5"><span>Timpa juga isian Koreksi yang sudah saya ketik sendiri</span></label>
                                <button type="submit" class="btn btn-sukses mt-3"><?= ikon('check', 'h-4 w-4') ?>Terapkan <?= count($beda) ?> perubahan ke honor</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== Peserta per kelas ===== -->
    <section id="peserta-kelas" class="kartu scroll-mt-20" aria-labelledby="judul-peserta">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h3 id="judul-peserta" class="kartu-judul">Jumlah peserta ujian per kelas</h3>
                <p class="kartu-ket">Satu angka per kelas, dipakai semua mapel di kelas itu. Bawaannya <b>siswa aktif</b>; ketik angka lain bila yang ikut ujian berbeda (kotak kuning = diketik).</p>
            </div>
            <?php if (! $terkunci): ?>
                <form method="post" action="<?= $base ?>/segarkan-peserta" class="flex flex-wrap items-center gap-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-gray-900 dark:text-white"><input type="checkbox" name="semua" value="1" class="cek"> termasuk yang diketik</label>
                    <button type="submit" class="btn btn-netral btn-xs" data-confirm="Samakan jumlah peserta dengan siswa aktif saat ini?" data-confirm-judul="Samakan peserta?" data-confirm-ya="Ya, samakan"><?= ikon('users', 'h-3.5 w-3.5') ?>Samakan dengan siswa aktif</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="kartu-isi space-y-3">
            <?php foreach ($grup as $g): ?>
                <div>
                    <p class="mb-1.5 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($g['judul']) ?></p>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-8 xl:grid-cols-10">
                        <?php for ($i = $g['dari']; $i <= $g['sampai']; $i++): $k = $kelas[$i]; ?>
                            <label class="block rounded-lg border border-gray-200 px-2 py-1.5 dark:border-gray-600">
                                <span class="flex items-baseline justify-between gap-1"><span class="truncate text-xs font-semibold text-gray-900 dark:text-white" title="<?= esc($k['nama'], 'attr') ?>"><?= esc($k['label']) ?></span><span class="text-[10px] text-gray-500 dark:text-gray-400" title="Siswa aktif di sistem"><?= (int) $k['siswa'] ?></span></span>
                                <input type="text" inputmode="numeric" maxlength="3" value="<?= (int) $k['peserta'] ?>" data-kelas="<?= (int) $k['id'] ?>" data-awal="<?= (int) $k['peserta'] ?>"
                                       <?= $terkunci ? 'disabled' : '' ?> @change="simpanPeserta($el)" @focus="$el.select()" aria-label="Peserta ujian kelas <?= esc($k['nama'], 'attr') ?>"
                                       class="inp mt-1 px-2 py-1 text-right tabular-nums<?= $k['manual'] ? ' border-yellow-400 bg-yellow-50 dark:bg-yellow-900/30' : '' ?>">
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($kelas === []): ?><p class="text-sm text-gray-500 dark:text-gray-400">Belum ada kelas di Master Kelas.</p><?php endif; ?>
        </div>
    </section>

    <!-- ===== Ceklis (matriks) ===== -->
    <section class="kartu" aria-labelledby="judul-ceklis">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h3 id="judul-ceklis" class="kartu-judul">Ceklis pembagian lembar jawaban</h3>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-xs bg-brand-500"></span>guru mengoreksi kelas itu (angka = lembar)</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-xs bg-gray-200 dark:bg-gray-700"></span>tidak</span>
                    <?php if (! $terkunci): ?><span><b>klik</b> sel = nyalakan/matikan · <b>klik kanan</b> = angka lembar khusus (bingkai kuning)</span><?php else: ?><span>honor dikunci</span><?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($adaCeklis): ?>
                    <div class="grup-tombol" role="group" aria-label="Tampilan data">
                        <button type="button" class="py-1.5 text-xs" @click="pilihTampilan('matriks')" :aria-pressed="tampilan === 'matriks'" title="Tabel besar: semua guru dan kelas sekaligus (nyaman di laptop)">Tabel</button>
                        <button type="button" class="py-1.5 text-xs" @click="pilihTampilan('guru')" :aria-pressed="tampilan === 'guru'" title="Satu kartu per guru (nyaman di HP)">Per guru</button>
                    </div>
                    <?php if (! $terkunci): ?>
                        <div class="grup-tombol" role="group" aria-label="Fungsi klik pada sel">
                            <button type="button" class="py-1.5 text-xs" @click="pilihMode('klik')" :aria-pressed="mode === 'klik'">Klik = nyala/mati</button>
                            <button type="button" class="py-1.5 text-xs" @click="pilihMode('khusus')" :aria-pressed="mode === 'khusus'" title="Untuk layar sentuh: ketuk sel lalu isi jumlah lembar khusus">Klik = angka khusus</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="relative w-full sm:w-56">
                    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                    <input type="search" x-model="cari" placeholder="Cari nama guru…" class="inp py-2 ps-9" aria-label="Cari nama guru">
                </div>
            </div>
        </div>

        <?php if (! $adaCeklis): ?>
            <?= ui_kosong(['ikon' => 'clipboard-check', 'judul' => 'Ceklis belum diisi',
                'teks' => 'Pilih salah satu: Isi dari SKBM (disarankan — sesuai SK Pembagian Tugas Mengajar' . ($skbm['guru'] > 0 ? ', SKBM ' . $skbm['tahun'] . ' sudah berisi ' . (int) $skbm['guru'] . ' guru' : '') . '), Isi dari data pengampu (otomatis dari Master), Impor dari Excel KOREKSI (persis berkas sekolah), atau Salin dari honor lain. Setelah itu klik sel kelas untuk mengoreksi per guru.']) ?>
        <?php else: ?>
            <!-- Tampilan "Per guru": kartu dibangun dari tabel oleh kartu-guru.js (hanya saat dipilih) -->
            <div id="kartu-guru" x-cloak x-show="tampilan === 'guru'" class="space-y-2 p-3 sm:p-4"></div>
            <div x-show="tampilan === 'matriks'" class="max-h-[78vh] overflow-auto">
                <table class="border-separate border-spacing-0 text-xs text-gray-700 dark:text-gray-300">
                    <thead>
                        <tr>
                            <th rowspan="2" class="<?= $kepalaTh ?> z-20 min-w-[2.5rem] md:left-0 md:z-30">No</th>
                            <th rowspan="2" class="<?= $kepalaTh ?> z-20 min-w-48 text-left md:left-[2.5rem] md:z-30">Nama Guru</th>
                            <th rowspan="2" class="<?= $kepalaTh ?> z-20 min-w-14">Kode</th>
                            <th rowspan="2" class="<?= $kepalaTh ?> z-20 min-w-56 text-left">Mata Pelajaran</th>
                            <?php foreach ($grup as $g): ?>
                                <th colspan="<?= $g['sampai'] - $g['dari'] + 1 ?>" class="<?= $kepalaTh ?> z-10 py-1.5 text-center"><?= esc($g['judul']) ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2" class="<?= $kepalaTh ?> z-10 min-w-16">Lembar</th>
                            <th rowspan="2" class="sticky top-0 z-10 min-w-[4.5rem] border-b border-gray-300 bg-gray-100 px-2 py-2 dark:border-gray-600 dark:bg-gray-700">Total guru</th>
                        </tr>
                        <tr>
                            <?php foreach ($kelas as $k): ?>
                                <th class="sticky top-[2.1rem] z-10 min-w-[2.4rem] border-b border-r border-gray-300 bg-gray-100 px-0.5 py-1 text-[10px] font-bold text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300" title="<?= esc($k['nama'], 'attr') ?>"><?= esc($k['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody @click="klikSel($event)" @contextmenu="klikKanan($event)">
                        <?php foreach ($guru as $g): $jml = count($g['mapel']); ?>
                            <?php foreach ($g['mapel'] as $i => $x): ?>
                                <tr data-nama="<?= esc(mb_strtolower($g['nama']), 'attr') ?>" data-judul="<?= esc($g['nama'], 'attr') ?>" data-no="<?= (int) $g['no'] ?>" data-kode="<?= esc($x['kode'], 'attr') ?>" data-baris="<?= (int) $g['baris_id'] ?>" data-mapel="<?= (int) $x['id'] ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/60">
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" class="<?= $sel ?> text-center align-middle text-gray-500 tabular-nums md:sticky md:left-0 md:z-[5] dark:text-gray-400"><?= (int) $g['no'] ?></td>
                                        <td rowspan="<?= $jml ?>" class="<?= $sel ?> align-middle md:sticky md:left-[2.5rem] md:z-[5]">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?></span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400"><?= esc($g['jabatan']) ?></span>
                                            <?php if (! $terkunci): ?>
                                                <form data-aksi-guru method="post" action="<?= $base ?>/guru/<?= (int) $g['baris_id'] ?>/hapus" class="mt-0.5">
                                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                                    <button type="submit" class="text-[11px] font-semibold text-red-700 hover:underline dark:text-red-500"
                                                            data-confirm="Keluarkan <?= esc($g['nama'], 'attr') ?> dari ceklis? (angka Koreksi di honor tidak berubah)" data-confirm-judul="Keluarkan dari ceklis?" data-confirm-ya="Ya, keluarkan" data-confirm-jenis="bahaya">keluarkan dari ceklis</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="<?= $sel ?> text-center font-semibold text-gray-500 dark:text-gray-400"><?= esc($x['kode']) ?></td>
                                    <td class="<?= $sel ?>">
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" data-id="<?= (int) $x['id'] ?>" data-nama="<?= esc($x['nama'], 'attr') ?>" @click="ubahNama($el)"<?= $terkunci ? ' disabled' : '' ?> title="Klik untuk mengganti nama mapel" class="min-w-0 truncate text-left text-gray-900 dark:text-white<?= $terkunci ? '' : ' hover:text-brand-700 hover:underline dark:hover:text-brand-400' ?>"><?= esc($x['nama']) ?></button>
                                            <?php if (! $terkunci): ?>
                                                <form data-aksi-mapel method="post" action="<?= $base ?>/mapel/<?= (int) $x['id'] ?>/hapus" class="shrink-0">
                                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                                    <button type="submit" class="rounded-sm p-0.5 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-500 dark:hover:bg-red-900/30" aria-label="Hapus baris mapel <?= esc($x['nama'], 'attr') ?>" title="Hapus baris mapel"
                                                            data-confirm="Hapus baris mapel ini beserta kelasnya?" data-confirm-judul="Hapus baris mapel?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('close', 'h-3.5 w-3.5') ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php foreach ($kelas as $k):
                                        $ada    = isset($x['sel'][$k['id']]);
                                        $khusus = $ada && ! empty($x['ubah'][$k['id']]);
                                        ?>
                                        <td data-k="<?= (int) $k['id'] ?>" data-aktif="<?= $ada ? '1' : '0' ?>" data-khusus="<?= $khusus ? '1' : '' ?>"
                                            class="border-b border-r border-gray-300 px-0.5 py-1 text-center tabular-nums dark:border-gray-600<?= $terkunci ? '' : ' cursor-pointer' ?> <?= $ada ? 'bg-brand-500 font-semibold text-white' : 'bg-gray-200 dark:bg-gray-700' ?><?= $khusus ? ' ring-2 ring-inset ring-yellow-400' : '' ?>"><?= $ada ? (int) $x['sel'][$k['id']] : '' ?></td>
                                    <?php endforeach; ?>
                                    <td id="mt-<?= (int) $x['id'] ?>" class="<?= $sel ?> text-center text-gray-600 tabular-nums dark:text-gray-300"><?= $rp($x['total']) ?></td>
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" id="gt-<?= (int) $g['baris_id'] ?>" class="border-b border-gray-200 bg-white px-2 py-1 text-center align-middle text-base font-bold text-gray-900 tabular-nums dark:border-gray-700 dark:bg-gray-800 dark:text-white"><?= $rp($g['total']) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <td colspan="4" class="border-t border-gray-300 px-2 py-2 text-right text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:border-gray-600 dark:text-gray-400">Jumlah baris mapel per kelas</td>
                            <?php foreach ($kelas as $k): ?><td id="kc-<?= (int) $k['id'] ?>" class="border-t border-r border-gray-200 px-0.5 py-2 text-center font-semibold text-gray-600 tabular-nums dark:border-gray-600 dark:text-gray-300"><?= (int) $k['jml'] ?></td><?php endforeach; ?>
                            <td class="border-t border-gray-300 dark:border-gray-600"></td>
                            <td class="border-t border-gray-300 px-2 py-2 text-center text-base font-bold text-brand-700 tabular-nums dark:border-gray-600 dark:text-brand-400" id="tot-semua"><?= $rp($m['total']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/kartu-guru.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/kartu-guru.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/admin/koreksi-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/koreksi-grid.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
