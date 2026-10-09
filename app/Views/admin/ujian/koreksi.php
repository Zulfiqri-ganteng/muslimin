<?php
/**
 * Ceklis KOREKSI honor ("KOREKSI NILAI": pembagian lembar jawaban ke guru per rombel) — KHUSUS ADMIN.
 * Angka resmi dihitung SERVER (Libraries\HonorKoreksi); layar hanya menampilkannya.
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
$tombol = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50';
// Langkah yang sebaiknya dikerjakan berikutnya: 1 = isi ceklis, 3 = terapkan ke honor, 0 = semua beres.
$langkahBerikut = ! $adaCeklis ? 1 : (($banding['ada_komponen'] && $jmlBeda > 0) ? 3 : 0);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="koreksiGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>" class="mx-auto max-w-[110rem] space-y-4">
    <input type="hidden" id="koreksi-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

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
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= esc($label) ?> · <?= esc($statusLabel[$status] ?? $status) ?></p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">Koreksi &mdash; pembagian lembar jawaban ke guru</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">Lembar tiap guru = jumlah <b>peserta ujian</b> di kelas yang ia koreksi (per mata pelajaran). Total guru otomatis menjadi angka <b>Koreksi</b> di honor, dan bisa diunduh sebagai Excel &quot;KOREKSI NILAI&quot;.</p>
            </div>
            <a href="<?= esc($kembali) ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">&larr; Kembali ke Honor</a>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Guru di ceklis</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-guru"><?= (int) $m['jumlah_guru'] ?></p></div>
            <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Baris mapel</p><p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-mapel"><?= (int) $m['jumlah_mapel'] ?></p></div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total lembar</p><p class="text-xl font-extrabold tabular-nums text-brand-700" id="ringkas-total"><?= $rp($m['total']) ?></p></div>
            <div class="rounded-xl border px-3 py-2 <?= $jmlBeda > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Beda dengan kolom Koreksi di honor</p>
                <p class="text-xl font-extrabold tabular-nums text-slate-800"><?= $banding['ada_komponen'] ? $jmlBeda . ' orang' : '—' ?></p>
            </div>
        </div>

        <!-- Alur kerja: 4 langkah, yang berikutnya disorot -->
        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Alur kerja</p>
                <?php if ($terkunci): ?>
                    <p class="text-xs text-slate-500">Honor dikunci &mdash; hanya bisa dilihat dan diunduh.</p>
                <?php elseif ($langkahBerikut === 1): ?>
                    <p class="text-xs text-slate-500">Berikutnya: <b class="text-brand-700">Langkah 1 &mdash; isi ceklis</b><?= ($skbm['guru'] ?? 0) > 0 ? ' (SKBM ' . esc($skbm['tahun']) . ' sudah siap dipakai)' : '' ?></p>
                <?php elseif ($langkahBerikut === 3): ?>
                    <p class="text-xs text-slate-500">Berikutnya: <b class="text-brand-700">Langkah 3 &mdash; terapkan ke honor</b> (<?= $jmlBeda ?> orang masih beda)</p>
                <?php else: ?>
                    <p class="text-xs font-semibold text-emerald-700">Semua langkah beres &mdash; angka Koreksi di honor sudah sama dengan ceklis.</p>
                <?php endif; ?>
            </div>
            <ol class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <?php
                $kartu = static fn (bool $selesai, bool $sorot): string => 'rounded-xl border p-3 ' . ($sorot ? 'border-brand-600 bg-brand-50/40 ring-1 ring-brand-600' : ($selesai ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white'));
                $bulat = static fn (bool $selesai, bool $sorot): string => 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ' . ($selesai ? 'bg-emerald-600 text-white' : ($sorot ? 'bg-brand-700 text-white' : 'bg-slate-200 text-slate-600'));
                $sel3 = $adaCeklis && $banding['ada_komponen'] && $jmlBeda === 0;
                ?>
                <li class="<?= $kartu($adaCeklis, $langkahBerikut === 1) ?>">
                    <div class="flex items-start gap-2.5">
                        <span class="<?= $bulat($adaCeklis, $langkahBerikut === 1) ?>"><?= $adaCeklis ? '&#10003;' : '1' ?></span>
                        <div class="min-w-0"><p class="text-sm font-bold text-slate-800">Isi ceklis</p><p class="text-xs text-slate-500"><?= $adaCeklis ? (int) $m['jumlah_guru'] . ' guru · ' . (int) $m['jumlah_mapel'] . ' baris mapel' : 'Belum diisi — pilih sumbernya' ?></p></div>
                    </div>
                    <?php if (! $terkunci): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="panel = (panel === 'isi-skbm' ? '' : 'isi-skbm')" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">Isi dari SKBM</button>
                            <button type="button" @click="panel = (panel === 'isi' ? '' : 'isi')" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Isi dari data pengampu</button>
                            <button type="button" @click="panel = (panel === 'impor' ? '' : 'impor')" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Impor dari Excel KOREKSI</button>
                            <?php if ($lain !== []): ?><button type="button" @click="panel = (panel === 'salin' ? '' : 'salin')" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Salin dari honor lain</button><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
                <li class="<?= $kartu(false, false) ?>">
                    <div class="flex items-start gap-2.5">
                        <span class="<?= $bulat(false, false) ?>">2</span>
                        <div class="min-w-0"><p class="text-sm font-bold text-slate-800">Periksa &amp; koreksi</p><p class="text-xs text-slate-500"><?= $adaCeklis ? 'Ketuk sel kelas untuk mengubah; atur peserta tiap kelas' : 'Menunggu ceklis terisi' ?></p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php if (! $terkunci): ?><button type="button" @click="panel = (panel === 'tambah' ? '' : 'tambah')" class="inline-flex items-center rounded-lg border border-brand-600 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">+ Tambah guru / mapel</button><?php endif; ?>
                        <a href="#peserta-kelas" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Peserta per kelas</a>
                    </div>
                </li>
                <li class="<?= $kartu($sel3, $langkahBerikut === 3) ?>">
                    <div class="flex items-start gap-2.5">
                        <span class="<?= $bulat($sel3, $langkahBerikut === 3) ?>"><?= $sel3 ? '&#10003;' : '3' ?></span>
                        <div class="min-w-0"><p class="text-sm font-bold text-slate-800">Terapkan ke honor</p><p class="text-xs text-slate-500"><?php
                            if (! $adaCeklis) { echo 'Menunggu ceklis terisi'; } elseif (! $banding['ada_komponen']) { echo 'Honor tak punya komponen Koreksi'; } elseif ($jmlBeda > 0) { echo $jmlBeda . ' orang beda dengan kolom Koreksi'; } else { echo 'Sudah sama dengan kolom Koreksi'; }
                        ?></p></div>
                    </div>
                    <?php if ($adaCeklis): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="panel = (panel === 'terapkan' ? '' : 'terapkan')" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">Terapkan ke kolom Koreksi honor<?= $jmlBeda > 0 ? ' (' . $jmlBeda . ' beda)' : '' ?></button>
                        </div>
                    <?php endif; ?>
                </li>
                <li class="<?= $kartu(false, false) ?>">
                    <div class="flex items-start gap-2.5">
                        <span class="<?= $bulat(false, false) ?>">4</span>
                        <div class="min-w-0"><p class="text-sm font-bold text-slate-800">Unduh Excel</p><p class="text-xs text-slate-500">Format &quot;KOREKSI NILAI&quot; sekolah</p></div>
                    </div>
                    <?php if ($adaCeklis): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="<?= $base ?>/xlsx<?= $qtp ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">Unduh Excel KOREKSI</a>
                        </div>
                    <?php endif; ?>
                </li>
            </ol>
            <?php if (! $terkunci && $adaCeklis): ?>
                <p class="mt-3 text-right"><button type="button" @click="panel = (panel === 'kosong' ? '' : 'kosong')" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">Kosongkan ceklis</button></p>
            <?php endif; ?>
        </div>
        <?php if ($terkunci): ?>
            <p class="mt-3 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">Honor ini DIKUNCI &mdash; ceklis hanya bisa dilihat dan diunduh. Buka kunci di tab Honor bila perlu mengubah.</p>
        <?php endif; ?>

        <?php if (! $terkunci): ?>
            <!-- Panel: isi dari SKBM -->
            <div id="isi-skbm" x-cloak x-show="panel === 'isi-skbm'" x-transition class="mt-4 rounded-xl border border-brand-200 bg-brand-50/40 p-4">
                <?php if (! $skbm['aktif']): ?>
                    <p class="text-sm leading-relaxed text-slate-600">Fitur SKBM belum aktif di server ini: migrasi database belum dijalankan (<b>php spark migrate</b>).</p>
                <?php elseif ($skbm['guru'] === 0): ?>
                    <p class="text-sm leading-relaxed text-slate-600">SKBM tahun ajaran <b><?= esc($skbm['tahun']) ?></b> belum diisi. Isi dulu di menu <b>Guru &rarr; SKBM</b> (impor dari Excel jadwal sekolah, atau salin dari tahun lain), lalu kembali ke sini dan tekan tombol ini lagi.</p>
                    <a href="<?= esc($skbm['url'], 'attr') ?>#impor" class="mt-3 inline-flex items-center rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800">Buka menu SKBM</a>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/isi-skbm">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-slate-600">Ceklis diisi dari <b>SKBM <?= esc($skbm['tahun']) ?></b> (<?= (int) $skbm['guru'] ?> guru, <?= (int) $skbm['mapel'] ?> baris mapel, <?= (int) $skbm['sel'] ?> kelas): guru &times; mapel &times; kelas persis seperti SK Pembagian Tugas Mengajar. Hanya guru yang sudah jadi <b>penerima honor</b> yang dimasukkan, satu baris per nama mapel. Lembar tiap kelas mengikuti jumlah peserta kelas itu, dan hasilnya masih bisa dikoreksi per sel.</p>
                        <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
                            <input type="checkbox" name="ganti" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                            <span>Ganti seluruh ceklis yang sekarang <?= $adaCeklis ? '<b class="text-amber-700">(ceklis sekarang sudah berisi)</b>' : '' ?> (tanpa ini, guru yang sudah punya isi dilewati)</span>
                        </label>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Isi dari SKBM</button>
                            <a href="<?= esc($skbm['url'], 'attr') ?>" class="text-sm font-semibold text-brand-700 hover:underline">Lihat / ubah SKBM <?= esc($skbm['tahun']) ?></a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: isi dari pengampu -->
            <div id="isi" x-cloak x-show="panel === 'isi'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form method="post" action="<?= $base ?>/isi-pengampu">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-slate-600">Ceklis diisi dari <b>data pengampu</b> di Master (guru &times; mapel &times; kelas). Hanya guru yang sudah jadi <b>penerima honor</b> yang dimasukkan, satu baris per nama mapel. Hasilnya masih bisa dikoreksi per sel. Bila data pengampu belum sama dengan SK Pembagian Tugas Mengajar, lebih tepat pakai <b>Isi dari SKBM</b>; atau <b>Impor dari Excel KOREKSI</b> bila ingin persis seperti berkas sekolah.</p>
                    <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
                        <input type="checkbox" name="ganti" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                        <span>Ganti seluruh ceklis yang sekarang (tanpa ini, guru yang sudah punya isi dilewati)</span>
                    </label>
                    <button type="submit" class="mt-4 rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Isi dari data pengampu</button>
                </form>
            </div>

            <!-- Panel: impor Excel -->
            <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form method="post" action="<?= $base ?>/impor/unggah<?= $qtp ?>" enctype="multipart/form-data" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-slate-600">Pilih Excel &quot;KOREKSI NILAI&quot; dari sekolah (.xlsx). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> &mdash; belum ada yang tersimpan sampai kamu menekan Terapkan. Jumlah peserta tiap kelas diambil dari angka di kolom kelasnya.</p>
                    <input type="file" name="berkas" accept=".xlsx" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                    <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Baca berkas</button>
                </form>
            </div>

            <!-- Panel: salin dari honor lain -->
            <?php if ($lain !== []): ?>
                <div id="salin" x-cloak x-show="panel === 'salin'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <form method="post" action="<?= $base ?>/salin" class="space-y-3">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-slate-600">Salin pembagian guru &times; mapel &times; kelas dari honor ujian lain (mis. ASTS ke ASAS). Penerima dicocokkan lewat Master Guru.</p>
                        <select name="sumber" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:max-w-md">
                            <?php foreach ($lain as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= esc((UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran']) ?> (<?= (int) $d['guru'] ?> guru)</option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-600"><input type="checkbox" name="peserta" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300"><span>Salin juga jumlah peserta tiap kelas (tanpa ini, peserta mengikuti siswa aktif)</span></label>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-600"><input type="checkbox" name="ganti" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300"><span>Ganti ceklis yang sekarang <?= $adaCeklis ? '<b class="text-amber-700">(ceklis sekarang sudah berisi)</b>' : '' ?></span></label>
                        <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Salin ceklis</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Panel: tambah guru / mapel -->
            <div id="tambah" x-cloak x-show="panel === 'tambah'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form method="post" action="<?= $base ?>/mapel" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="k-baris">Penerima honor</label>
                        <select id="k-baris" name="baris" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">— pilih —</option>
                            <?php foreach ($penerima as $b): ?><option value="<?= (int) $b['id'] ?>"><?= esc($b['nama']) ?><?= $b['jabatan'] ? ' — ' . esc($b['jabatan']) : '' ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500" for="k-mapel">Mata pelajaran (tulis <b>-</b> bila tidak mengoreksi mapel)</label>
                        <input id="k-mapel" name="nama" type="text" required maxlength="150" list="k-mapel-opsi" autocomplete="off" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500">
                        <datalist id="k-mapel-opsi"><?php foreach ($mapelOpsi as $nm): ?><option value="<?= esc($nm, 'attr') ?>"><?php endforeach; ?></datalist>
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Tambah baris</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">Setelah baris ditambahkan, klik sel kelas di tabel untuk menentukan kelas yang dikoreksi.</p>
            </div>

            <?php if ($adaCeklis): ?>
                <!-- Panel: kosongkan -->
                <div id="kosong" x-cloak x-show="panel === 'kosong'" x-transition class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <form method="post" action="<?= $base ?>/kosongkan">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm leading-relaxed text-red-800">Semua baris mapel dan kelasnya dibuang (<?= (int) $m['jumlah_mapel'] ?> baris, <?= (int) $m['jumlah_guru'] ?> guru). Angka Koreksi yang sudah ada di honor <b>tidak ikut berubah</b>.</p>
                        <label class="mt-2 flex cursor-pointer items-start gap-2.5 text-sm text-red-800"><input type="checkbox" name="peserta" value="1" class="mt-0.5 h-4 w-4 rounded border-red-300"><span>Buang juga jumlah peserta per kelas yang saya ketik</span></label>
                        <button type="submit" onclick="return confirm('Kosongkan seluruh ceklis Koreksi? Tidak bisa dibatalkan.')" class="mt-3 rounded-lg bg-red-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 active:scale-95">Kosongkan ceklis</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($adaCeklis): ?>
            <!-- Panel: terapkan ke honor -->
            <div id="terapkan" x-cloak x-show="panel === 'terapkan'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <?php if (! $banding['ada_komponen']): ?>
                    <p class="text-sm text-slate-600">Honor ini tidak memuat komponen <b>Koreksi</b>, jadi tidak ada kolom yang bisa diisi. Aktifkan komponen Koreksi di Pengaturan Honor lalu tekan &quot;Perbarui tarif dari pengaturan&quot; di tab Honor.</p>
                <?php else: ?>
                    <?php $beda = array_values(array_filter($banding['baris'], static fn (array $b): bool => $b['beda'])); ?>
                    <?php if ($beda === []): ?>
                        <p class="text-sm font-semibold text-emerald-700">Sudah sama: angka Koreksi di honor tiap guru sama dengan total ceklis.</p>
                    <?php else: ?>
                        <p class="text-sm leading-relaxed text-slate-600">Angka berikut akan menggantikan isian <b>Koreksi</b> di honor. Isian yang kamu ketik sendiri (bertanda <span class="rounded bg-amber-100 px-1 text-amber-800">diketik</span>) baru diganti bila kotak &quot;timpa&quot; dicentang. Rupiah dan total honor dihitung ulang otomatis.</p>
                        <div class="mt-3 max-h-72 overflow-auto rounded-lg border border-slate-200 bg-white">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Nama</th><th class="px-3 py-2 text-right">Di honor sekarang</th><th class="px-3 py-2 text-right">Dari ceklis</th><th class="px-3 py-2"></th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($beda as $b): ?>
                                        <tr><td class="px-3 py-1.5 font-semibold text-slate-700"><?= esc($b['nama']) ?></td><td class="px-3 py-1.5 text-right tabular-nums text-slate-500"><?= $rp($b['sekarang']) ?></td><td class="px-3 py-1.5 text-right font-bold tabular-nums text-brand-700"><?= $rp($b['ceklis']) ?></td><td class="px-3 py-1.5"><?= $b['manual'] ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">diketik</span>' : '' ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (! $terkunci): ?>
                            <form method="post" action="<?= $base ?>/terapkan" class="mt-3">
                                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-600"><input type="checkbox" name="timpa" value="1" <?= count(array_filter($beda, static fn ($b) => $b['manual'])) > 0 ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300"><span>Timpa juga isian Koreksi yang sudah saya ketik sendiri</span></label>
                                <button type="submit" class="mt-3 rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 active:scale-95">Terapkan <?= count($beda) ?> perubahan ke honor</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== Peserta per kelas ===== -->
    <section id="peserta-kelas" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Jumlah peserta ujian per kelas</h3>
                <p class="mt-0.5 text-xs text-slate-500">Satu angka per kelas, dipakai semua mapel di kelas itu. Bawaannya <b>siswa aktif</b>; ketik angka lain bila yang ikut ujian berbeda (kotak kuning = diketik).</p>
            </div>
            <?php if (! $terkunci): ?>
                <form method="post" action="<?= $base ?>/segarkan-peserta" class="flex flex-wrap items-center gap-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="semua" value="1" class="h-4 w-4 rounded border-slate-300"> termasuk yang diketik</label>
                    <button type="submit" onclick="return confirm('Samakan jumlah peserta dengan siswa aktif saat ini?')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Samakan dengan siswa aktif</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="space-y-3 p-4">
            <?php foreach ($grup as $g): ?>
                <div>
                    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-400"><?= esc($g['judul']) ?></p>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-8 xl:grid-cols-10">
                        <?php for ($i = $g['dari']; $i <= $g['sampai']; $i++): $k = $kelas[$i]; ?>
                            <label class="block rounded-lg border border-slate-200 px-2 py-1.5">
                                <span class="flex items-baseline justify-between gap-1"><span class="truncate text-xs font-bold text-slate-700" title="<?= esc($k['nama'], 'attr') ?>"><?= esc($k['label']) ?></span><span class="text-[10px] text-slate-400" title="Siswa aktif di sistem"><?= (int) $k['siswa'] ?></span></span>
                                <input type="text" inputmode="numeric" maxlength="3" value="<?= (int) $k['peserta'] ?>" data-kelas="<?= (int) $k['id'] ?>" data-awal="<?= (int) $k['peserta'] ?>"
                                       <?= $terkunci ? 'disabled' : '' ?> @change="simpanPeserta($el)" @focus="$el.select()" aria-label="Peserta ujian kelas <?= esc($k['nama'], 'attr') ?>"
                                       class="mt-1 w-full rounded-md border px-2 py-1 text-right text-sm tabular-nums outline-none focus:border-brand-500 <?= $k['manual'] ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white' ?>">
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($kelas === []): ?><p class="text-sm text-slate-400">Belum ada kelas di Master Kelas.</p><?php endif; ?>
        </div>
    </section>

    <!-- ===== Ceklis (matriks) ===== -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Ceklis pembagian lembar jawaban</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    <span class="mr-1 inline-block h-3 w-3 rounded-sm bg-sky-400 align-middle"></span>biru = guru mengoreksi kelas itu (angka = lembar) ·
                    <span class="mx-1 inline-block h-3 w-3 rounded-sm bg-slate-200 align-middle"></span>abu-abu = tidak ·
                    <?php if (! $terkunci): ?><b>klik</b> sel = nyalakan/matikan · <b>klik kanan</b> = angka lembar khusus (bingkai kuning)<?php else: ?>honor dikunci<?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($adaCeklis): ?>
                    <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 text-xs font-semibold" role="group" aria-label="Tampilan data">
                        <button type="button" @click="pilihTampilan('matriks')" :class="tampilan === 'matriks' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-2" title="Tabel besar: semua guru dan kelas sekaligus (nyaman di laptop)">Tabel</button>
                        <button type="button" @click="pilihTampilan('guru')" :class="tampilan === 'guru' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="border-l border-slate-300 px-3 py-2" title="Satu kartu per guru (nyaman di HP)">Per guru</button>
                    </div>
                    <?php if (! $terkunci): ?>
                        <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 text-xs font-semibold" role="group" aria-label="Fungsi klik pada sel">
                            <button type="button" @click="mode = 'klik'" :class="mode === 'klik' ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-2">Klik = nyala/mati</button>
                            <button type="button" @click="mode = 'khusus'" :class="mode === 'khusus' ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="border-l border-slate-300 px-3 py-2" title="Untuk layar sentuh: ketuk sel lalu isi jumlah lembar khusus">Klik = angka khusus</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <input type="search" x-model="cari" placeholder="Cari nama guru…" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 sm:w-56" aria-label="Cari nama guru">
            </div>
        </div>

        <?php if (! $adaCeklis): ?>
            <div class="px-5 py-10 text-center">
                <p class="text-base font-bold text-slate-700">Ceklis belum diisi</p>
                <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500">Pilih salah satu: <b>Isi dari SKBM</b> (disarankan &mdash; sesuai SK Pembagian Tugas Mengajar<?= $skbm['guru'] > 0 ? ', SKBM ' . esc($skbm['tahun']) . ' sudah berisi ' . (int) $skbm['guru'] . ' guru' : '' ?>), <b>Isi dari data pengampu</b> (otomatis dari Master), <b>Impor dari Excel KOREKSI</b> (persis berkas sekolah), atau <b>Salin dari honor lain</b>. Setelah itu klik sel kelas untuk mengoreksi per guru.</p>
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
                            <th rowspan="2" class="sticky top-0 z-10 border-b border-r border-slate-300 bg-slate-100 px-2 py-2" style="min-width:4rem">Lembar</th>
                            <th rowspan="2" class="sticky top-0 z-10 border-b border-slate-300 bg-slate-100 px-2 py-2" style="min-width:4.5rem">Total guru</th>
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
                                <tr data-nama="<?= esc(mb_strtolower($g['nama']), 'attr') ?>" data-judul="<?= esc($g['nama'], 'attr') ?>" data-no="<?= (int) $g['no'] ?>" data-kode="<?= esc($x['kode'], 'attr') ?>" data-baris="<?= (int) $g['baris_id'] ?>" data-mapel="<?= (int) $x['id'] ?>" class="hover:bg-slate-50">
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center align-middle tabular-nums text-slate-500 md:sticky md:left-0 md:z-[5]"><?= (int) $g['no'] ?></td>
                                        <td rowspan="<?= $jml ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 align-middle md:sticky md:left-[2.5rem] md:z-[5]">
                                            <span class="block text-sm font-semibold text-slate-800"><?= esc($g['nama']) ?></span>
                                            <span class="block text-[11px] text-slate-400"><?= esc($g['jabatan']) ?></span>
                                            <?php if (! $terkunci): ?>
                                                <form data-aksi-guru method="post" action="<?= $base ?>/guru/<?= (int) $g['baris_id'] ?>/hapus" class="mt-0.5">
                                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Keluarkan <?= esc($g['nama'], 'js') ?> dari ceklis? (angka Koreksi di honor tidak berubah)')" class="text-[11px] font-semibold text-red-500 hover:underline">keluarkan dari ceklis</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center font-semibold text-slate-500"><?= esc($x['kode']) ?></td>
                                    <td class="border-b border-r border-slate-200 bg-white px-2 py-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <button type="button" data-id="<?= (int) $x['id'] ?>" data-nama="<?= esc($x['nama'], 'attr') ?>" @click="ubahNama($el)" <?= $terkunci ? 'disabled' : '' ?> title="Klik untuk mengganti nama mapel" class="min-w-0 truncate text-left text-slate-700 <?= $terkunci ? '' : 'hover:text-brand-700 hover:underline' ?>"><?= esc($x['nama']) ?></button>
                                            <?php if (! $terkunci): ?>
                                                <form data-aksi-mapel method="post" action="<?= $base ?>/mapel/<?= (int) $x['id'] ?>/hapus" class="shrink-0">
                                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Hapus baris mapel ini beserta kelasnya?')" class="text-red-400 hover:text-red-600" aria-label="Hapus baris mapel <?= esc($x['nama'], 'attr') ?>" title="Hapus baris mapel">&times;</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php foreach ($kelas as $k):
                                        $ada    = isset($x['sel'][$k['id']]);
                                        $khusus = $ada && ! empty($x['ubah'][$k['id']]);
                                        ?>
                                        <td data-k="<?= (int) $k['id'] ?>" data-aktif="<?= $ada ? '1' : '0' ?>" data-khusus="<?= $khusus ? '1' : '' ?>"
                                            class="border-b border-r border-slate-300 px-0.5 py-1 text-center tabular-nums <?= $terkunci ? '' : 'cursor-pointer' ?> <?= $ada ? 'bg-sky-400 font-semibold text-white' : 'bg-slate-200' ?> <?= $khusus ? 'ring-2 ring-inset ring-amber-400' : '' ?>"><?= $ada ? (int) $x['sel'][$k['id']] : '' ?></td>
                                    <?php endforeach; ?>
                                    <td id="mt-<?= (int) $x['id'] ?>" class="border-b border-r border-slate-200 bg-white px-2 py-1 text-center tabular-nums text-slate-600"><?= $rp($x['total']) ?></td>
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= $jml ?>" id="gt-<?= (int) $g['baris_id'] ?>" class="border-b border-slate-200 bg-white px-2 py-1 text-center align-middle text-base font-extrabold tabular-nums text-slate-800"><?= $rp($g['total']) ?></td>
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
                            <td class="border-t border-slate-300 px-2 py-2 text-center text-base font-extrabold tabular-nums text-brand-700" id="tot-semua"><?= $rp($m['total']) ?></td>
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
<script defer src="<?= base_url('assets/js/admin/koreksi-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/koreksi-grid.js') ?>"></script>
<?= $this->endSection() ?>
