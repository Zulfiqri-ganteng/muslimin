<?php
/**
 * SKBM — SK Pembagian Tugas Mengajar per tahun ajaran — KHUSUS ADMIN. Matriks guru × mapel × kelas, isi sel = JP per minggu.
 * Angka resmi dihitung SERVER (Libraries\Skbm); layar hanya menampilkannya.
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
$tombol = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="skbmGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>" class="mx-auto max-w-[110rem] space-y-4">
    <input type="hidden" id="skbm-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <?php if ($peringatan !== []): ?>
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-bold">Perhatikan hasil impor:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($peringatan as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" x-cloak x-show="pesan !== ''" x-transition.opacity>
        <div class="pointer-events-auto max-w-md rounded-xl px-4 py-3 text-sm font-semibold shadow-lg" :class="galat ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="pesan" role="status" aria-live="polite"></div>
    </div>

    <!-- ===== Ringkasan & aksi ===== -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Khusus Admin · sumber ceklis Koreksi honor</p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">SKBM &mdash; SK Pembagian Tugas Mengajar</h2>
                <p class="mt-1 hidden max-w-3xl text-sm leading-relaxed text-slate-500 sm:block">Siapa mengajar <b>mapel apa</b> di <b>kelas mana</b> dan berapa <b>JP</b> per minggu, untuk satu tahun ajaran. Datanya terpisah dari menu Penugasan (tidak mengubah jadwal), dan dipakai di <b>Honor &rarr; Koreksi</b> lewat tombol &quot;Isi dari SKBM&quot;.</p>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                    <?php if ($honorTahun !== []): ?>
                        Pakai di honor <?= esc($tahun) ?>:
                        <?php foreach ($honorTahun as $h): ?><a href="<?= esc($h['url'], 'attr') ?>" class="ml-1 inline-flex items-center rounded-full border border-sky-300 bg-sky-50 px-2.5 py-0.5 font-semibold text-sky-800 transition hover:bg-sky-100"><?= esc($h['label']) ?> &rarr; Koreksi</a><?php endforeach; ?>
                    <?php else: ?>
                        Belum ada honor ujian untuk tahun ajaran ini &mdash; buat di menu <b>Ujian &rarr; tab Honor</b>, lalu ceklis Koreksinya bisa diisi dari SKBM ini.
                    <?php endif; ?>
                </p>
                <form method="post" action="<?= esc($base, 'attr') ?>/nomor" class="mt-3 flex flex-wrap items-center gap-2">
                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                    <label for="nomor-sk" class="text-xs font-semibold text-slate-500">Nomor SK</label>
                    <input id="nomor-sk" name="nomor" type="text" maxlength="120" value="<?= esc($nomorSk, 'attr') ?>" placeholder="mis. 123/SMK-BN/SKBM/VII/<?= esc(substr($tahun, 0, 4), 'attr') ?>" class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-brand-500 sm:w-72">
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Simpan</button>
                    <span class="text-[11px] text-slate-400">tercetak di baris &quot;Nomor :&quot; pada Excel SKBM</span>
                </form>
            </div>
            <form method="get" action="<?= esc($base, 'attr') ?>" class="flex shrink-0 items-center gap-2">
                <label for="skbm-tahun" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahun ajaran</label>
                <select id="skbm-tahun" name="tahun" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                    <?php foreach ($opsi as $t): ?>
                        <option value="<?= esc($t, 'attr') ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= esc($t) ?> &middot; <?= isset($berisi[$t]) ? (int) $berisi[$t]['guru'] . ' guru' : 'kosong' ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Buka</button></noscript>
            </form>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Guru</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-guru"><?= (int) $m['jumlah_guru'] ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Baris mapel</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-mapel"><?= (int) $m['jumlah_mapel'] ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Guru &times; kelas</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-sel"><?= (int) $m['jumlah_sel'] ?></p></div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total JP / minggu</p><p class="text-xl font-extrabold tabular-nums text-brand-700" id="ringkas-total"><?= $rp($m['total_jp']) ?></p></div>
            <div class="rounded-xl border px-3 py-2 <?= $tanpaJp > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">JP belum diisi</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-tanpajp"><?= $adaData ? (int) $tanpaJp : '—' ?></p></div>
            <a href="<?= esc($base . '/bandingkan?tahun=' . rawurlencode($tahun), 'attr') ?>" class="block rounded-xl border px-3 py-2 transition hover:bg-slate-50 <?= ($selisih ?? 0) > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Beda dengan Penugasan</p>
                <p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $selisih === null ? '—' : (int) $selisih ?></p>
            </a>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <button type="button" @click="panel = (panel === 'impor' ? '' : 'impor')" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-800">Impor dari Excel SKBM</button>
            <?php if ($lain !== []): ?><button type="button" @click="panel = (panel === 'salin' ? '' : 'salin')" class="<?= $tombol ?>">Salin dari tahun lain</button><?php endif; ?>
            <button type="button" @click="panel = (panel === 'tambah' ? '' : 'tambah')" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-600 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">+ Tambah guru / mapel</button>
            <?php if ($adaData): ?>
                <a href="<?= esc($base . '/bandingkan?tahun=' . rawurlencode($tahun), 'attr') ?>" class="<?= $tombol ?>">Bandingkan dengan Penugasan</a>
                <a href="<?= esc($base . '/xlsx?tahun=' . rawurlencode($tahun), 'attr') ?>" class="<?= $tombol ?>" title="Unduh SKBM <?= esc($tahun, 'attr') ?> sebagai Excel — bisa diedit lalu diimpor kembali">Unduh Excel SKBM</a>
                <button type="button" @click="panel = (panel === 'kosong' ? '' : 'kosong')" class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3.5 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">Kosongkan SKBM <?= esc($tahun) ?></button>
            <?php endif; ?>
        </div>

        <!-- Panel: impor Excel -->
        <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <form method="post" action="<?= esc($base, 'attr') ?>/impor/unggah" enctype="multipart/form-data" class="space-y-3">
                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                <p class="text-sm leading-relaxed text-slate-600">Pilih Excel jadwal sekolah yang memuat lembar <b>SKBM</b> (.xlsx, maksimal 8 MB; lembar lain di berkas itu diabaikan). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> &mdash; belum ada yang tersimpan sampai kamu menekan Terapkan. Bila berkas terlalu berat, salin lembar SKBM saja ke berkas Excel baru.
                    <?php if ($adaData): ?>Atau <a href="<?= esc($base . '/xlsx?tahun=' . rawurlencode($tahun), 'attr') ?>" class="font-semibold text-brand-700 underline">unduh Excel SKBM <?= esc($tahun) ?></a> yang sekarang, ubah di Excel, lalu impor kembali.
                    <?php else: ?>Belum punya berkasnya? <a href="<?= esc($base . '/xlsx?tahun=' . rawurlencode($tahun), 'attr') ?>" class="font-semibold text-brand-700 underline">Unduh template Excel</a> (kolom kelas sudah terisi), isi guru, mapel, dan JP-nya, lalu impor di sini.<?php endif; ?></p>
                <input type="file" name="berkas" accept=".xlsx" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Baca berkas</button>
            </form>
        </div>

        <!-- Panel: salin dari tahun lain -->
        <?php if ($lain !== []): ?>
            <div id="salin" x-cloak x-show="panel === 'salin'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form method="post" action="<?= esc($base, 'attr') ?>/salin" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                    <p class="text-sm leading-relaxed text-slate-600">Salin seluruh SKBM tahun ajaran lain ke <b><?= esc($tahun) ?></b> sebagai titik awal (guru, mapel, kelas, dan JP), lalu sesuaikan yang berubah.</p>
                    <select name="dari" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:max-w-md">
                        <?php foreach ($lain as $t => $n): ?><option value="<?= esc($t, 'attr') ?>"><?= esc($t) ?> (<?= (int) $n['guru'] ?> guru, <?= (int) $n['mapel'] ?> baris mapel)</option><?php endforeach; ?>
                    </select>
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-600"><input type="checkbox" name="ganti" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300"><span>Ganti SKBM <?= esc($tahun) ?> yang sekarang <?= $adaData ? '<b class="text-amber-700">(sekarang sudah berisi dan akan dibuang)</b>' : '' ?></span></label>
                    <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Salin ke <?= esc($tahun) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Panel: tambah guru / mapel -->
        <div id="tambah" x-cloak x-show="panel === 'tambah'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <form method="post" action="<?= esc($base, 'attr') ?>/mapel" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500" for="s-guru">Guru (dari Master Guru)</label>
                    <select id="s-guru" name="guru" x-ref="pilihGuru" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">— pilih —</option>
                        <?php foreach ($guruOpsi as $g): ?><option value="<?= (int) $g['id'] ?>"><?= esc($g['nama']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500" for="s-mapel">Mata pelajaran (tulis <b>-</b> bila tidak mengampu mapel)</label>
                    <input id="s-mapel" name="nama" type="text" required maxlength="<?= DataSkbm::MAKS_NAMA ?>" list="s-mapel-opsi" autocomplete="off" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500">
                    <datalist id="s-mapel-opsi"><?php foreach ($mapelOpsi as $nm): ?><option value="<?= esc($nm, 'attr') ?>"><?php endforeach; ?></datalist>
                </div>
                <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Tambah baris</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">Setelah baris ditambahkan, klik sel kelas di tabel untuk menentukan kelas yang diajar, lalu isi JP-nya.</p>
        </div>

        <?php if ($adaData): ?>
            <!-- Panel: kosongkan -->
            <div id="kosong" x-cloak x-show="panel === 'kosong'" x-transition class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                <form method="post" action="<?= esc($base, 'attr') ?>/kosongkan">
                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                    <p class="text-sm leading-relaxed text-red-800">Seluruh SKBM <b><?= esc($tahun) ?></b> dibuang (<?= (int) $m['jumlah_mapel'] ?> baris mapel, <?= (int) $m['jumlah_guru'] ?> guru). Ceklis Koreksi di honor yang sudah terisi <b>tidak ikut berubah</b>.</p>
                    <button type="submit" onclick="return confirm('Kosongkan seluruh SKBM <?= esc($tahun, 'js') ?>? Tidak bisa dibatalkan.')" class="mt-3 rounded-lg bg-red-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 active:scale-95">Kosongkan SKBM</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== Matriks SKBM ===== -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Pembagian tugas mengajar &middot; <?= esc($tahun) ?></h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    <span class="mr-1 inline-block h-3 w-3 rounded-sm bg-sky-400 align-middle"></span>biru = mengajar di kelas itu (angka = JP per minggu) ·
                    <span class="mx-1 inline-block h-3 w-3 rounded-sm bg-amber-300 align-middle"></span>kuning &#10003; = mengajar, JP belum diisi ·
                    <span class="mx-1 inline-block h-3 w-3 rounded-sm bg-slate-200 align-middle"></span>abu-abu = tidak ·
                    <b>klik</b> sel = nyalakan/matikan · <b>klik kanan</b> = isi JP
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($adaData): ?>
                    <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 text-xs font-semibold" role="group" aria-label="Tampilan data">
                        <button type="button" @click="pilihTampilan('matriks')" :class="tampilan === 'matriks' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-2" title="Tabel besar: semua guru dan kelas sekaligus (nyaman di laptop)">Tabel</button>
                        <button type="button" @click="pilihTampilan('guru')" :class="tampilan === 'guru' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="border-l border-slate-300 px-3 py-2" title="Satu kartu per guru (nyaman di HP)">Per guru</button>
                    </div>
                <?php endif; ?>
                <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 text-xs font-semibold" role="group" aria-label="Fungsi klik pada sel">
                    <button type="button" @click="mode = 'klik'" :class="mode === 'klik' ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-2">Klik = nyala/mati</button>
                    <button type="button" @click="mode = 'jp'" :class="mode === 'jp' ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="border-l border-slate-300 px-3 py-2">Klik = isi JP</button>
                </div>
                <input type="search" x-model="cari" placeholder="Cari nama guru…" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 sm:w-56" aria-label="Cari nama guru">
            </div>
        </div>

        <?php if (! $adaData): ?>
            <div class="px-5 py-10 text-center">
                <p class="text-base font-bold text-slate-700">SKBM <?= esc($tahun) ?> belum diisi</p>
                <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500">Cara tercepat: <b>Impor dari Excel SKBM</b> (lembar SKBM di berkas jadwal sekolah). Bisa juga <b>Salin dari tahun lain</b>, atau <b>Tambah guru / mapel</b> lalu klik sel kelasnya satu per satu.</p>
                <button type="button" @click="panel = 'impor'; $nextTick(() => document.getElementById('impor').scrollIntoView({behavior:'smooth', block:'center'}))" class="mt-4 inline-flex items-center rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800">Impor dari Excel SKBM</button>
            </div>
        <?php else: ?>
            <!-- Tampilan "Per guru": kartu dibangun dari tabel oleh kartu-guru.js (hanya saat dipilih) -->
            <div id="kartu-guru" x-cloak x-show="tampilan === 'guru'" class="space-y-2 p-3 sm:p-4"></div>
            <div x-show="tampilan === 'matriks'" class="max-h-[78vh] overflow-auto">
                <table class="border-separate border-spacing-0 text-xs">
                    <thead>
                        <tr class="text-slate-700">
                            <th rowspan="2" class="sticky top-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-2 py-2 md:left-0 md:z-30" style="min-width:2.5rem">No</th>
                            <th rowspan="2" class="sticky top-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-2 py-2 text-left md:left-[2.5rem] md:z-30" style="min-width:12rem">Nama Guru</th>
                            <th rowspan="2" class="sticky top-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-2 py-2" style="min-width:3.5rem">Kode</th>
                            <th rowspan="2" class="sticky top-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-2 py-2 text-left" style="min-width:14rem">Mata Pelajaran</th>
                            <?php foreach ($grup as $g): ?>
                                <th colspan="<?= $g['sampai'] - $g['dari'] + 1 ?>" class="sticky top-0 z-10 border-b border-r border-slate-300 bg-slate-100 px-2 py-1.5 text-center"><?= esc($g['judul']) ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2" class="sticky top-0 z-10 border-b border-r border-slate-300 bg-slate-100 px-2 py-2" style="min-width:3.5rem">JP</th>
                            <th rowspan="2" class="sticky top-0 z-10 border-b border-slate-300 bg-slate-100 px-2 py-2" style="min-width:4.5rem">Total JP guru</th>
                        </tr>
                        <tr>
                            <?php foreach ($kelas as $k): ?>
                                <th class="sticky z-10 border-b border-r border-slate-300 bg-slate-100 px-0.5 py-1 text-[10px] font-bold text-slate-600" style="top:2.1rem;min-width:2.4rem" title="<?= esc($k['nama'], 'attr') ?>"><?= esc($k['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody @click="klikSel($event)" @contextmenu="klikKanan($event)">
                        <?php foreach ($guru as $g): $jml = count($g['mapel']); ?>
                            <?php foreach ($g['mapel'] as $i => $x): ?>
                                <tr data-nama="<?= esc(mb_strtolower($g['nama']), 'attr') ?>" data-judul="<?= esc($g['nama'], 'attr') ?>" data-no="<?= (int) $g['no'] ?>" data-kode="<?= esc($x['kode'], 'attr') ?>" data-guru="<?= (int) $g['guru_id'] ?>" data-mapel="<?= (int) $x['id'] ?>" class="hover:bg-slate-50">
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center align-middle tabular-nums text-slate-500 md:sticky md:left-0 md:z-[5]"><?= (int) $g['no'] ?></td>
                                        <td rowspan="<?= $jml ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 align-middle md:sticky md:left-[2.5rem] md:z-[5]">
                                            <span class="block text-sm font-semibold text-slate-800"><?= esc($g['nama']) ?></span>
                                            <span data-aksi-guru class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                                <button type="button" @click="tambahUntuk(<?= (int) $g['guru_id'] ?>)" class="text-[11px] font-semibold text-brand-700 hover:underline">+ mapel</button>
                                                <form method="post" action="<?= esc($base, 'attr') ?>/guru/<?= (int) $g['guru_id'] ?>/hapus">
                                                    <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                                                    <button type="submit" onclick="return confirm('Keluarkan <?= esc($g['nama'], 'js') ?> dari SKBM <?= esc($tahun, 'js') ?>?')" class="text-[11px] font-semibold text-red-500 hover:underline">keluarkan</button>
                                                </form>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center font-semibold text-slate-500"><?= esc($x['kode']) ?></td>
                                    <td class="border-b border-r border-slate-200 bg-white px-2 py-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" data-id="<?= (int) $x['id'] ?>" data-nama="<?= esc($x['nama'], 'attr') ?>" @click="ubahNama($el)" title="Klik untuk mengganti nama mapel" class="min-w-0 truncate text-left text-slate-700 hover:text-brand-700 hover:underline"><?= esc($x['nama']) ?></button>
                                            <form data-aksi-mapel method="post" action="<?= esc($base, 'attr') ?>/mapel/<?= (int) $x['id'] ?>/hapus" class="shrink-0">
                                                <?= csrf_field() ?><input type="hidden" name="tahun" value="<?= esc($tahun, 'attr') ?>">
                                                <button type="submit" onclick="return confirm('Hapus baris mapel ini beserta kelasnya?')" class="text-red-400 hover:text-red-600" aria-label="Hapus baris mapel <?= esc($x['nama'], 'attr') ?>" title="Hapus baris mapel">&times;</button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php foreach ($kelas as $k):
                                        $ada = array_key_exists($k['id'], $x['sel']);
                                        $jp  = $ada ? $x['sel'][$k['id']] : null;
                                        ?>
                                        <td data-k="<?= (int) $k['id'] ?>" data-aktif="<?= $ada ? '1' : '0' ?>" data-jp="<?= $jp === null ? '' : (int) $jp ?>"
                                            class="cursor-pointer border-b border-r border-slate-300 px-0.5 py-1 text-center tabular-nums <?= ! $ada ? 'bg-slate-200' : ($jp === null ? 'bg-amber-300 font-semibold text-amber-900' : 'bg-sky-400 font-semibold text-white') ?>"><?= ! $ada ? '' : ($jp === null ? '&#10003;' : (int) $jp) ?></td>
                                    <?php endforeach; ?>
                                    <td id="mt-<?= (int) $x['id'] ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center tabular-nums text-slate-600"><?= $rp($x['total_jp']) ?></td>
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" id="gt-<?= (int) $g['guru_id'] ?>" class="border-b border-slate-200 bg-white px-2 py-1 text-center align-middle text-base font-extrabold tabular-nums text-slate-800"><?= $rp($g['total_jp']) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50">
                            <td colspan="4" class="border-t border-slate-300 px-2 py-2 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Jumlah baris mapel per kelas</td>
                            <?php foreach ($kelas as $k): ?><td id="kc-<?= (int) $k['id'] ?>" class="border-t border-r border-slate-200 px-0.5 py-2 text-center font-semibold tabular-nums text-slate-600"><?= (int) $k['jml'] ?></td><?php endforeach; ?>
                            <td class="border-t border-slate-300"></td>
                            <td class="border-t border-slate-300"></td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td colspan="4" class="px-2 py-2 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Jumlah JP per kelas</td>
                            <?php foreach ($kelas as $k): ?><td id="kj-<?= (int) $k['id'] ?>" class="border-r border-slate-200 px-0.5 py-2 text-center font-semibold tabular-nums text-slate-600"><?= (int) $k['jp'] ?></td><?php endforeach; ?>
                            <td class="px-2 py-2 text-center font-extrabold tabular-nums text-brand-700" id="tot-semua"><?= $rp($m['total_jp']) ?></td>
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
<script defer src="<?= base_url('assets/js/admin/kartu-guru.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/kartu-guru.js') ?>"></script>
<script defer src="<?= base_url('assets/js/admin/skbm-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/skbm-grid.js') ?>"></script>
<?= $this->endSection() ?>
