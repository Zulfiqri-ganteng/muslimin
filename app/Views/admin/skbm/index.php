<?php
/**
 * SKBM — SK Pembagian Tugas Mengajar per tahun ajaran — KHUSUS ADMIN. Matriks guru × mapel × kelas, isi sel = JP per minggu.
 * Angka resmi dihitung SERVER (Libraries\Skbm); layar hanya menampilkannya.
 * Rombak tampilan F7: kartu kit, tombol ber-ikon, grup-tombol, ui_peringatan/ui_kosong; onchange/onclick sebaris → data-autosubmit
 * & data-confirm (dialog kit, juga pada salinannya di kartu "Per guru"); lebar kolom = kelas Tailwind (tanpa style=). Komponen
 * Alpine "skbmGrid" (skbm-grid.js + kartu-guru.js). Id & atribut data-* tabel dibaca JS — jangan diubah tanpa menyesuaikannya.
 *
 * @var string $tahun  @var list<string> $tahunOpsi  @var array<string,array{guru:int,mapel:int}> $berisi  @var array $m  hasil Skbm::muat()
 * @var ?int $selisih  beda dengan Penugasan (null = SKBM kosong)  @var int $tanpaJp  sel yang JP-nya belum diisi
 * @var list<array> $guruOpsi  @var list<string> $mapelOpsi  @var string $base
 * @var list<array{label:string, url:string}> $honorTahun  honor ujian tahun ini (tautan ke halaman Koreksi-nya)
 * @var string $nomorSk  nomor SK tahun ajaran ini ("" bila belum diisi)
 */
use App\Libraries\Skbm as DataSkbm;

$rp       = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$kelas    = $m['kelas'];
$grup     = $m['grup'];
$guru     = $m['guru'];
$adaData  = $guru !== [];
$opsi     = $tahunOpsi;
if (! in_array($tahun, $opsi, true)) {
    $opsi[] = $tahun;
    rsort($opsi);
}
$lain = array_filter($berisi, static fn (string $t): bool => $t !== $tahun, ARRAY_FILTER_USE_KEY);
$cfg  = [
    'urlSel'       => $base . '/sel',
    'urlMapelUbah' => $base . '/mapel/__ID__/ubah',
    'csrfName'     => csrf_token(),
    'tahun'        => $tahun,
    'maksJp'       => DataSkbm::MAKS_JP,
    // bahan tampilan "Per guru" (kartu-guru.js): kolom kelas menurut urutan tabel + kelompoknya
    'kelas'        => array_map(static fn (array $k): array => ['id' => (int) $k['id'], 'label' => $k['label'], 'nama' => $k['nama']], $kelas),
    'grup'         => $grup,
];
$peringatan = session()->getFlashdata('skbm_peringatan') ?: [];
$urlXlsx    = $base . '/xlsx?tahun=' . rawurlencode($tahun);
$urlBanding = $base . '/bandingkan?tahun=' . rawurlencode($tahun);
$kotak = static fn (string $lbl, string $isi, string $id = '', string $warna = ''): string => '<div class="rounded-lg border px-3 py-2 '
    . ($warna !== '' ? $warna : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50') . '">'
    . '<p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">' . $lbl . '</p>'
    . '<p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . $isi . '</p></div>';
$kuning = 'border-yellow-300 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20';
// Kelas sel matriks: dasar + warna keadaan (warna keadaan ditukar skbm-grid.js: KELAS_JP / KELAS_TANPA_JP / KELAS_MATI).
$selDasar = 'cursor-pointer border-b border-r border-gray-300 px-0.5 py-1 text-center tabular-nums dark:border-gray-600';
$kepalaTh = 'sticky top-0 border-b border-r border-gray-300 bg-gray-100 px-2 py-2 dark:border-gray-600 dark:bg-gray-700';
$sel      = 'border-b border-r border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-800';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="skbmGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>" class="mx-auto max-w-[110rem] space-y-4">
    <input type="hidden" id="skbm-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <?php if ($peringatan !== []): ?>
        <?= ui_peringatan('awas', $peringatan, ['judul' => 'Perhatikan hasil impor:']) ?>
    <?php endif; ?>

    <!-- Pesan simpan otomatis (satu baris, diganti tiap simpan) -->
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" x-cloak x-show="pesan !== ''" x-transition.opacity>
        <div class="pointer-events-auto max-w-md rounded-lg px-4 py-3 text-sm font-medium text-white shadow-sm" :class="galat ? 'bg-red-700' : 'bg-green-700'" x-text="pesan" role="status" aria-live="polite"></div>
    </div>

    <!-- ===== Ringkasan & aksi ===== -->
    <section class="kartu" aria-labelledby="judul-skbm">
        <div class="kartu-isi">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Sumber ceklis Koreksi honor</p>
                    <h2 id="judul-skbm" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">SKBM &mdash; SK Pembagian Tugas Mengajar</h2>
                    <p class="mt-1 hidden max-w-3xl text-sm leading-relaxed text-gray-500 sm:block dark:text-gray-400">Siapa mengajar <b>mapel apa</b> di <b>kelas mana</b> dan berapa <b>JP</b> per minggu, untuk satu tahun ajaran. Datanya terpisah dari menu Penugasan (tidak mengubah jadwal), dan dipakai di <b>Honor &rarr; Koreksi</b> lewat tombol &quot;Isi dari SKBM&quot;.</p>
                    <p class="mt-2 flex flex-wrap items-center gap-1.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        <?php if ($honorTahun !== []): ?>
                            Pakai di honor <?= esc($tahun) ?>:
                            <?php foreach ($honorTahun as $h): ?><a href="<?= esc($h['url'], 'attr') ?>" class="lencana lencana-merek hover:underline"><?= esc($h['label']) ?> &rarr; Koreksi</a><?php endforeach; ?>
                        <?php else: ?>
                            Belum ada honor ujian untuk tahun ajaran ini &mdash; buat di menu <b>Ujian &rarr; tab Honor</b>, lalu ceklis Koreksinya bisa diisi dari SKBM ini.
                        <?php endif; ?>
                    </p>
                    <form method="post" action="<?= esc($base, 'attr') ?>/nomor" class="mt-3 flex flex-wrap items-center gap-2">
                        <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                        <label for="nomor-sk" class="text-xs font-medium text-gray-900 dark:text-white">Nomor SK</label>
                        <input id="nomor-sk" name="nomor" type="text" maxlength="120" value="<?= esc($nomorSk, 'attr') ?>" placeholder="mis. 123/SMK-BN/SKBM/VII/<?= esc(substr($tahun, 0, 4), 'attr') ?>" class="inp w-full max-w-xs py-1.5 sm:w-72">
                        <button type="submit" class="btn btn-netral btn-sm"><?= ikon('check', 'h-3.5 w-3.5') ?>Simpan</button>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400">tercetak di baris &quot;Nomor :&quot; pada Excel SKBM</span>
                    </form>
                </div>
                <form method="get" action="<?= esc($base, 'attr') ?>" class="shrink-0">
                    <label for="skbm-tahun" class="lbl">Tahun ajaran</label>
                    <select id="skbm-tahun" name="tahun" data-autosubmit class="inp font-semibold">
                        <?php foreach ($opsi as $t): ?>
                            <option value="<?= esc($t, 'attr') ?>"<?= $t === $tahun ? ' selected' : '' ?>><?= esc($t) ?> &middot; <?= isset($berisi[$t]) ? (int) $berisi[$t]['guru'] . ' guru' : 'kosong' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-netral btn-sm mt-2">Buka</button></noscript>
                </form>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <?= $kotak('Guru', (string) (int) $m['jumlah_guru'], 'ringkas-guru') ?>
                <?= $kotak('Baris mapel', (string) (int) $m['jumlah_mapel'], 'ringkas-mapel') ?>
                <?= $kotak('Guru &times; kelas', (string) (int) $m['jumlah_sel'], 'ringkas-sel') ?>
                <div class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-800 dark:bg-brand-900/30">
                    <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:text-brand-300">Total JP / minggu</p>
                    <p class="text-xl font-bold text-brand-700 tabular-nums dark:text-brand-300" id="ringkas-total"><?= $rp($m['total_jp']) ?></p>
                </div>
                <?= $kotak('JP belum diisi', $adaData ? (string) (int) $tanpaJp : '—', 'ringkas-tanpajp', $tanpaJp > 0 ? $kuning : '') ?>
                <a href="<?= esc($urlBanding, 'attr') ?>" class="kartu-tautan block rounded-lg border px-3 py-2 <?= ($selisih ?? 0) > 0 ? $kuning : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50' ?>">
                    <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Beda dengan Penugasan</p>
                    <p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white"><?= $selisih === null ? '—' : (int) $selisih ?></p>
                </a>
            </div>
        </div>

        <div class="kartu-kaki flex flex-wrap gap-2">
            <button type="button" class="btn btn-utama" @click="alihPanel('impor')" :aria-expanded="panel === 'impor'" aria-controls="impor"><?= ikon('upload', 'h-4 w-4') ?>Impor dari Excel SKBM</button>
            <?php if ($lain !== []): ?><button type="button" class="btn btn-netral" @click="alihPanel('salin')" :aria-expanded="panel === 'salin'" aria-controls="salin"><?= ikon('file-copy', 'h-4 w-4') ?>Salin dari tahun lain</button><?php endif; ?>
            <button type="button" class="btn btn-netral" @click="alihPanel('tambah')" :aria-expanded="panel === 'tambah'" aria-controls="tambah"><?= ikon('plus', 'h-4 w-4') ?>Tambah guru / mapel</button>
            <?php if ($adaData): ?>
                <a href="<?= esc($urlBanding, 'attr') ?>" class="btn btn-netral"><?= ikon('file-search', 'h-4 w-4') ?>Bandingkan dengan Penugasan</a>
                <a href="<?= esc($urlXlsx, 'attr') ?>" class="btn btn-netral" title="Unduh SKBM <?= esc($tahun, 'attr') ?> sebagai Excel — bisa diedit lalu diimpor kembali"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel SKBM</a>
                <button type="button" class="btn btn-netral ms-auto text-red-700 dark:text-red-500" @click="alihPanel('kosong')" :aria-expanded="panel === 'kosong'" aria-controls="kosong"><?= ikon('trash-bin', 'h-4 w-4') ?>Kosongkan SKBM <?= esc($tahun) ?></button>
            <?php endif; ?>
        </div>

        <!-- Panel: impor Excel -->
        <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="border-t border-gray-200 bg-gray-50 p-4 md:px-6 dark:border-gray-700 dark:bg-gray-800/60">
            <form method="post" action="<?= esc($base, 'attr') ?>/impor/unggah" enctype="multipart/form-data" class="space-y-3">
                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Pilih Excel jadwal sekolah yang memuat lembar <b>SKBM</b> (.xlsx, maksimal 8 MB; lembar lain di berkas itu diabaikan). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> &mdash; belum ada yang tersimpan sampai kamu menekan Terapkan. Bila berkas terlalu berat, salin lembar SKBM saja ke berkas Excel baru.
                    <?php if ($adaData): ?>Atau <a href="<?= esc($urlXlsx, 'attr') ?>" class="font-medium text-brand-700 underline dark:text-brand-400">unduh Excel SKBM <?= esc($tahun) ?></a> yang sekarang, ubah di Excel, lalu impor kembali.
                    <?php else: ?>Belum punya berkasnya? <a href="<?= esc($urlXlsx, 'attr') ?>" class="font-medium text-brand-700 underline dark:text-brand-400">Unduh template Excel</a> (kolom kelas sudah terisi), isi guru, mapel, dan JP-nya, lalu impor di sini.<?php endif; ?></p>
                <label class="lbl" for="skbm-berkas">Berkas Excel (.xlsx)</label>
                <input type="file" id="skbm-berkas" name="berkas" accept=".xlsx" required class="inp-berkas">
                <button type="submit" class="btn btn-utama"><?= ikon('file-search', 'h-4 w-4') ?>Baca berkas</button>
            </form>
        </div>

        <!-- Panel: salin dari tahun lain -->
        <?php if ($lain !== []): ?>
            <div id="salin" x-cloak x-show="panel === 'salin'" x-transition class="border-t border-gray-200 bg-gray-50 p-4 md:px-6 dark:border-gray-700 dark:bg-gray-800/60">
                <form method="post" action="<?= esc($base, 'attr') ?>/salin" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Salin seluruh SKBM tahun ajaran lain ke <b><?= esc($tahun) ?></b> sebagai titik awal (guru, mapel, kelas, dan JP), lalu sesuaikan yang berubah.</p>
                    <label class="sr-only" for="skbm-dari">Salin dari tahun ajaran</label>
                    <select id="skbm-dari" name="dari" class="inp sm:max-w-md">
                        <?php foreach ($lain as $t => $n): ?><option value="<?= esc($t, 'attr') ?>"><?= esc($t) ?> (<?= (int) $n['guru'] ?> guru, <?= (int) $n['mapel'] ?> baris mapel)</option><?php endforeach; ?>
                    </select>
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="ganti" value="1" class="cek mt-0.5"><span>Ganti SKBM <?= esc($tahun) ?> yang sekarang <?= $adaData ? '<b class="text-yellow-700 dark:text-yellow-400">(sekarang sudah berisi dan akan dibuang)</b>' : '' ?></span></label>
                    <button type="submit" class="btn btn-utama"><?= ikon('file-copy', 'h-4 w-4') ?>Salin ke <?= esc($tahun) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Panel: tambah guru / mapel -->
        <div id="tambah" x-cloak x-show="panel === 'tambah'" x-transition class="border-t border-gray-200 bg-gray-50 p-4 md:px-6 dark:border-gray-700 dark:bg-gray-800/60">
            <form method="post" action="<?= esc($base, 'attr') ?>/mapel" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                <div>
                    <label class="lbl" for="s-guru">Guru (dari Master Guru)</label>
                    <select id="s-guru" name="guru" x-ref="pilihGuru" required class="inp">
                        <option value="">— pilih —</option>
                        <?php foreach ($guruOpsi as $g): ?><option value="<?= (int) $g['id'] ?>"><?= esc($g['nama']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="s-mapel">Mata pelajaran (tulis <b>-</b> bila tidak mengampu mapel)</label>
                    <input id="s-mapel" name="nama" type="text" required maxlength="<?= DataSkbm::MAKS_NAMA ?>" list="s-mapel-opsi" autocomplete="off" class="inp">
                    <datalist id="s-mapel-opsi"><?php foreach ($mapelOpsi as $nm): ?><option value="<?= esc($nm, 'attr') ?>"><?php endforeach; ?></datalist>
                </div>
                <button type="submit" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Tambah baris</button>
            </form>
            <p class="hint">Setelah baris ditambahkan, klik sel kelas di tabel untuk menentukan kelas yang diajar, lalu isi JP-nya.</p>
        </div>

        <?php if ($adaData): ?>
            <!-- Panel: kosongkan -->
            <div id="kosong" x-cloak x-show="panel === 'kosong'" x-transition class="border-t border-red-200 bg-red-50 p-4 md:px-6 dark:border-red-900 dark:bg-red-900/20">
                <form method="post" action="<?= esc($base, 'attr') ?>/kosongkan">
                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                    <p class="text-sm leading-relaxed text-red-800 dark:text-red-300">Seluruh SKBM <b><?= esc($tahun) ?></b> dibuang (<?= (int) $m['jumlah_mapel'] ?> baris mapel, <?= (int) $m['jumlah_guru'] ?> guru). Ceklis Koreksi di honor yang sudah terisi <b>tidak ikut berubah</b>.</p>
                    <button type="submit" class="btn btn-bahaya mt-3" data-confirm="Kosongkan seluruh SKBM <?= esc($tahun, 'attr') ?>? Tidak bisa dibatalkan." data-confirm-judul="Kosongkan SKBM?" data-confirm-ya="Ya, kosongkan" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Kosongkan SKBM</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== Matriks SKBM ===== -->
    <section class="kartu" aria-labelledby="judul-matriks">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h3 id="judul-matriks" class="kartu-judul">Pembagian tugas mengajar &middot; <?= esc($tahun) ?></h3>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-xs bg-brand-500"></span>mengajar di kelas itu (angka = JP per minggu)</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-xs bg-yellow-300"></span>&#10003; = mengajar, JP belum diisi</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-xs bg-gray-200 dark:bg-gray-700"></span>tidak</span>
                    <span><b>klik</b> sel = nyalakan/matikan · <b>klik kanan</b> = isi JP</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($adaData): ?>
                    <div class="grup-tombol" role="group" aria-label="Tampilan data">
                        <button type="button" class="py-1.5 text-xs" @click="pilihTampilan('matriks')" :aria-pressed="tampilan === 'matriks'" title="Tabel besar: semua guru dan kelas sekaligus (nyaman di laptop)">Tabel</button>
                        <button type="button" class="py-1.5 text-xs" @click="pilihTampilan('guru')" :aria-pressed="tampilan === 'guru'" title="Satu kartu per guru (nyaman di HP)">Per guru</button>
                    </div>
                <?php endif; ?>
                <div class="grup-tombol" role="group" aria-label="Fungsi klik pada sel">
                    <button type="button" class="py-1.5 text-xs" @click="pilihMode('klik')" :aria-pressed="mode === 'klik'">Klik = nyala/mati</button>
                    <button type="button" class="py-1.5 text-xs" @click="pilihMode('jp')" :aria-pressed="mode === 'jp'">Klik = isi JP</button>
                </div>
                <div class="relative w-full sm:w-56">
                    <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                    <input type="search" x-model="cari" placeholder="Cari nama guru…" class="inp py-2 ps-9" aria-label="Cari nama guru">
                </div>
            </div>
        </div>

        <?php if (! $adaData): ?>
            <?= ui_kosong(['ikon' => 'clipboard-list', 'judul' => 'SKBM ' . $tahun . ' belum diisi',
                'teks' => 'Cara tercepat: Impor dari Excel SKBM (lembar SKBM di berkas jadwal sekolah). Bisa juga Salin dari tahun lain, atau Tambah guru / mapel lalu klik sel kelasnya satu per satu.',
                'aksi' => '<button type="button" class="btn btn-utama" @click="bukaPanel(\'impor\')">' . ikon('upload', 'h-4 w-4') . 'Impor dari Excel SKBM</button>']) ?>
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
                            <th rowspan="2" class="<?= $kepalaTh ?> z-10 min-w-14">JP</th>
                            <th rowspan="2" class="sticky top-0 z-10 min-w-[4.5rem] border-b border-gray-300 bg-gray-100 px-2 py-2 dark:border-gray-600 dark:bg-gray-700">Total JP guru</th>
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
                                <tr data-nama="<?= esc(mb_strtolower($g['nama']), 'attr') ?>" data-judul="<?= esc($g['nama'], 'attr') ?>" data-no="<?= (int) $g['no'] ?>" data-kode="<?= esc($x['kode'], 'attr') ?>" data-guru="<?= (int) $g['guru_id'] ?>" data-mapel="<?= (int) $x['id'] ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/60">
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" class="<?= $sel ?> text-center align-middle text-gray-500 tabular-nums md:sticky md:left-0 md:z-[5] dark:text-gray-400"><?= (int) $g['no'] ?></td>
                                        <td rowspan="<?= $jml ?>" class="<?= $sel ?> align-middle md:sticky md:left-[2.5rem] md:z-[5]">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?></span>
                                            <span data-aksi-guru class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                                <button type="button" @click="tambahUntuk(<?= (int) $g['guru_id'] ?>)" class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= ikon('plus', 'h-3 w-3') ?>mapel</button>
                                                <form method="post" action="<?= esc($base, 'attr') ?>/guru/<?= (int) $g['guru_id'] ?>/hapus">
                                                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                                                    <button type="submit" class="text-[11px] font-semibold text-red-700 hover:underline dark:text-red-500"
                                                            data-confirm="Keluarkan <?= esc($g['nama'], 'attr') ?> dari SKBM <?= esc($tahun, 'attr') ?>?" data-confirm-judul="Keluarkan guru?" data-confirm-ya="Ya, keluarkan" data-confirm-jenis="bahaya">keluarkan</button>
                                                </form>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="<?= $sel ?> text-center font-semibold text-gray-500 dark:text-gray-400"><?= esc($x['kode']) ?></td>
                                    <td class="<?= $sel ?>">
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" data-id="<?= (int) $x['id'] ?>" data-nama="<?= esc($x['nama'], 'attr') ?>" @click="ubahNama($el)" title="Klik untuk mengganti nama mapel" class="min-w-0 truncate text-left text-gray-900 hover:text-brand-700 hover:underline dark:text-white dark:hover:text-brand-400"><?= esc($x['nama']) ?></button>
                                            <form data-aksi-mapel method="post" action="<?= esc($base, 'attr') ?>/mapel/<?= (int) $x['id'] ?>/hapus" class="shrink-0">
                                                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                                                <button type="submit" class="rounded-sm p-0.5 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-500 dark:hover:bg-red-900/30" aria-label="Hapus baris mapel <?= esc($x['nama'], 'attr') ?>" title="Hapus baris mapel"
                                                        data-confirm="Hapus baris mapel ini beserta kelasnya?" data-confirm-judul="Hapus baris mapel?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('close', 'h-3.5 w-3.5') ?></button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php foreach ($kelas as $k):
                                        $ada = array_key_exists($k['id'], $x['sel']);
                                        $jp  = $ada ? $x['sel'][$k['id']] : null;
                                        ?>
                                        <td data-k="<?= (int) $k['id'] ?>" data-aktif="<?= $ada ? '1' : '0' ?>" data-jp="<?= $jp === null ? '' : (int) $jp ?>"
                                            class="<?= $selDasar ?> <?= ! $ada ? 'bg-gray-200 dark:bg-gray-700' : ($jp === null ? 'bg-yellow-300 font-semibold text-yellow-900' : 'bg-brand-500 font-semibold text-white') ?>"><?= ! $ada ? '' : ($jp === null ? '&#10003;' : (int) $jp) ?></td>
                                    <?php endforeach; ?>
                                    <td id="mt-<?= (int) $x['id'] ?>" class="<?= $sel ?> text-center text-gray-600 tabular-nums dark:text-gray-300"><?= $rp($x['total_jp']) ?></td>
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" id="gt-<?= (int) $g['guru_id'] ?>" class="border-b border-gray-200 bg-white px-2 py-1 text-center align-middle text-base font-bold text-gray-900 tabular-nums dark:border-gray-700 dark:bg-gray-800 dark:text-white"><?= $rp($g['total_jp']) ?></td>
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
                            <td class="border-t border-gray-300 dark:border-gray-600"></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-2 py-2 text-right text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Jumlah JP per kelas</td>
                            <?php foreach ($kelas as $k): ?><td id="kj-<?= (int) $k['id'] ?>" class="border-r border-gray-200 px-0.5 py-2 text-center font-semibold text-gray-600 tabular-nums dark:border-gray-600 dark:text-gray-300"><?= (int) $k['jp'] ?></td><?php endforeach; ?>
                            <td class="px-2 py-2 text-center font-bold text-brand-700 tabular-nums dark:text-brand-400" id="tot-semua"><?= $rp($m['total_jp']) ?></td>
                            <td></td>
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
<script defer src="<?= base_url('assets/js/admin/skbm-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/skbm-grid.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
