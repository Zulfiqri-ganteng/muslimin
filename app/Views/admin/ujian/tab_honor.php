<?php
/**
 * Tab Honor — KHUSUS ADMIN. Daftar penerima + grid isian per komponen + total (dihitung SERVER, Libraries\HonorDokumen).
 * Rombak tampilan F7: kartu kit, alur kerja 5 langkah dengan tombol ber-ikon & aria-expanded, menu Cetak = dropdown kit, kartu
 * Pemeriksaan = <details>, ui_peringatan/ui_kosong, isian kit, lencana status; konfirmasi = data-confirm (dulu onclick confirm);
 * lebar kolom grid (variabel --kolom/--minw) dipasang honor-grid.js lewat CSSOM (dulu style= sebaris); skrip ber-nonce.
 * Id & atribut data-* grid dibaca honor-grid.js — jangan diubah tanpa menyesuaikannya.
 *
 * @var array<string,mixed>      $periode
 * @var string                   $label          mis. "ASTS 1 — TP 2026/2027"
 * @var string                   $base           alamat dasar halaman jenis ini
 * @var string                   $qtp            "?tp=…"
 * @var array<string,mixed>|null $honor          hasil HonorDokumen::muat() atau null bila belum dibuat
 * @var list<array<string,mixed>> $honorCalon    guru yang belum jadi penerima
 * @var list<array<string,mixed>> $honorLain     honor periode lain (untuk "salin penerima dari …")
 * @var list<array<string,mixed>> $honorPratinjau komponen yang akan dipakai bila honor dibuat
 */

use App\Libraries\HonorPengaturan;
use App\Models\UjianPeriodeModel;

$rp     = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$urlAtur = site_url('admin/honor/pengaturan');
$statusLabel = ['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'];
$statusWarna = ['draf' => 'lencana-abu', 'final' => 'lencana-merek', 'dikunci' => 'lencana-hijau'];
$panelKelas  = 'border-t border-gray-200 bg-gray-50 p-4 md:px-6 dark:border-gray-700 dark:bg-gray-800/60';
?>

<?php if ($honor === null): ?>
    <!-- ===================== Belum ada honor ===================== -->
    <section class="kartu mx-auto max-w-3xl overflow-hidden" aria-labelledby="judul-honor-baru">
        <div class="kartu-isi py-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-900 dark:text-brand-300">
                <?= ikon('receipt', 'h-7 w-7') ?>
            </div>
            <h3 id="judul-honor-baru" class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Belum ada honor untuk <?= esc($label) ?></h3>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                Honor dibuat sekali per ujian. Daftar komponen dan tarif di bawah disalin ke honor ini, jadi bila tarif diubah nanti,
                honor yang sudah dibuat tidak ikut berubah.
            </p>
        </div>

        <?php if ($honorPratinjau === []): ?>
            <div class="px-4 pb-6 md:px-6">
                <?= ui_peringatan('awas', '', ['html' => 'Belum ada komponen honor yang aktif untuk ujian ini. Aktifkan dulu di <a href="' . $urlAtur . '" class="font-semibold underline">Pengaturan Honor</a>.']) ?>
            </div>
        <?php else: ?>
            <div class="<?= $panelKelas ?>">
                <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Komponen yang dipakai</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($honorPratinjau as $k): ?>
                        <span class="lencana lencana-abu gap-1.5 py-1">
                            <b class="font-semibold text-gray-900 dark:text-white"><?= esc($k['nama']) ?></b>
                            <?= $k['tipe'] === 'tetap' ? 'nominal per orang' : 'Rp ' . $rp($k['tarif']) . ' / ' . esc($k['satuan'] ?: 'satuan') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="hint">Tarif diatur di <a href="<?= $urlAtur ?>" class="font-medium text-brand-700 underline dark:text-brand-400">Pengaturan Honor</a>.</p>
            </div>

            <form method="post" action="<?= $base ?>/honor/buat" class="kartu-isi border-t border-gray-200 dark:border-gray-700">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <?php if ($honorLain !== []): ?>
                    <label class="lbl" for="honor-salin-dari">Mulai dari</label>
                    <select id="honor-salin-dari" name="salin_dari" class="inp mb-4">
                        <option value="0">Daftar kosong (pilih penerima sendiri)</option>
                        <?php foreach ($honorLain as $d): ?>
                            <option value="<?= (int) $d['id'] ?>">Salin penerima &amp; tunjangan tetap dari <?= esc((UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran']) ?> (<?= (int) $d['jml'] ?> orang)</option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-utama w-full sm:w-auto"><?= ikon('plus', 'h-4 w-4') ?>Buat honor <?= esc($label) ?></button>
            </form>
            <form method="post" action="<?= $base ?>/honor/impor/unggah" enctype="multipart/form-data" class="<?= $panelKelas ?>">
                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <label class="lbl" for="honor-berkas-baru">Atau impor dari Excel lama</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="file" id="honor-berkas-baru" name="berkas" accept=".xlsx" required class="inp-berkas">
                    <button type="submit" class="btn btn-netral shrink-0"><?= ikon('file-search', 'h-4 w-4') ?>Baca berkas</button>
                </div>
                <p class="hint">Honor dibuat otomatis setelah kamu menyetujui hasil bacaan.</p>
            </form>
        <?php endif; ?>
    </section>

<?php else:
    $dok      = $honor['dokumen'];
    $komp     = $honor['komponen'];
    $baris    = $honor['baris'];
    $terkunci = $dok['status'] === 'dikunci';
    $nKomp    = count($komp);
    // Laptop: Nama+jabatan | komponen… | Total | hapus. Lebar minimum dihitung dari jumlah komponen (kolom Nama menempel saat digulir).
    $kolom    = 'minmax(12rem,1.6fr) repeat(' . $nKomp . ',minmax(5.75rem,1fr)) minmax(7rem,.9fr) 5.75rem';
    $minW     = 12 + 5.75 * $nKomp + 7 + 5.75 + 0.5 * ($nKomp + 2) + 1.5;
    $cfg      = [
        'urlNilai'    => $base . '/honor/nilai',
        'urlJabatan'  => $base . '/honor/baris/__ID__/jabatan',
        'urlPindah'   => $base . '/honor/baris/__ID__/pindah',
        'periodeId'   => (int) $periode['id'],
        'csrfName'    => csrf_token(),
        'terkunci'    => $terkunci,
        // (F7) dipasang honor-grid.js sebagai variabel CSS --kolom / --minw (pengganti style= sebaris)
        'kolom'       => $kolom,
        'minW'        => $minW,
    ];
    $tglDok = $dok['tanggal'] ? date('Y-m-d', strtotime((string) $dok['tanggal'])) : '';
    $olds   = static fn (string $k, $asal) => old($k, null, false) ?? $asal;
    $bukaSurat = old('judul', null, false) !== null;
    ?>
    <div x-data="honorGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>"<?= $bukaSurat ? ' data-panel-awal="surat"' : '' ?> class="space-y-4">
        <input type="hidden" id="honor-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

        <?php $peringatanImpor = session()->getFlashdata('honor_peringatan') ?: []; ?>
        <?php if ($peringatanImpor !== []): ?>
            <?= ui_peringatan('awas', $peringatanImpor, ['judul' => 'Perhatikan hasil impor:']) ?>
        <?php endif; ?>

        <!-- Pesan simpan otomatis (satu baris, diganti tiap simpan) -->
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" x-cloak x-show="pesan !== ''" x-transition.opacity>
            <div class="pointer-events-auto max-w-md rounded-lg px-4 py-3 text-sm font-medium text-white shadow-sm"
                 :class="galat ? 'bg-red-700' : 'bg-green-700'" x-text="pesan" role="status" aria-live="polite"></div>
        </div>

        <!-- ===== Ringkasan & aksi ===== -->
        <section class="kartu" aria-labelledby="judul-honor">
            <div class="kartu-isi">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 id="judul-honor" class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($dok['judul'] ?: 'Honor ' . $label) ?></h3>
                            <span class="lencana <?= $statusWarna[$dok['status']] ?? $statusWarna['draf'] ?>"><?= esc($statusLabel[$dok['status']] ?? $dok['status']) ?></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?= esc($label) ?>
                            <?php if ($dok['tanggal']): ?>&nbsp;·&nbsp;<?= esc(($dok['tempat'] ? $dok['tempat'] . ', ' : '') . date('d/m/Y', strtotime((string) $dok['tanggal']))) ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:flex sm:gap-6">
                        <div>
                            <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Penerima</p>
                            <p class="text-xl font-bold text-gray-900 tabular-nums dark:text-white" id="ringkas-penerima"><?= count($baris) ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Total honor</p>
                            <p class="text-xl font-bold text-brand-700 tabular-nums dark:text-brand-400">Rp <span id="ringkas-total"><?= $rp($honor['total']) ?></span></p>
                        </div>
                    </div>
                </div>

                <?php if ($terkunci): ?>
                    <div class="mt-3"><?= ui_peringatan('sukses', 'Honor ini sudah DIKUNCI' . (! empty($dok['dikunci_oleh']) ? ' oleh ' . $dok['dikunci_oleh'] : '') . (! empty($dok['dikunci_at']) ? ' pada ' . date('d/m/Y H:i', strtotime((string) $dok['dikunci_at'])) : '') . ' — tidak bisa diubah.') ?></div>
                <?php endif; ?>
                <?php if (($honorUtuh ?? null) === false): ?>
                    <div class="mt-3"><?= ui_peringatan('galat', 'PERINGATAN: isi honor ini BERUBAH sejak dikunci (tidak sama dengan sidik jari saat dikunci). Periksa Audit Log; bila perubahan tidak sah, hubungi pengelola sistem.') ?></div>
                <?php endif; ?>

                <?php
                // ---- Alur kerja honor: 5 langkah, yang berikutnya disorot (menggantikan deretan tombol datar) ----
                $nPerlu       = count(array_filter($honorPeriksa ?? [], static fn (array $t): bool => $t['level'] === 'peringatan'));
                $adaBaris     = $baris !== [];
                $suratLengkap = trim((string) $dok['ketua_nama']) !== '' && trim((string) $dok['bendahara_nama']) !== '' && trim((string) $dok['kepsek_nama']) !== '';
                $selesai      = [1 => $adaBaris, 2 => $suratLengkap, 3 => (int) $honor['total'] > 0, 4 => $adaBaris && $nPerlu === 0, 5 => $dok['status'] !== 'draf'];
                $berikut      = 0;
                foreach ([1, 2, 3, 4, 5] as $n) {
                    if (! $selesai[$n]) {
                        $berikut = $n;
                        break;
                    }
                }
                $judulLangkah = [1 => 'Penerima', 2 => 'Data surat', 3 => 'Isi angka honor', 4 => 'Periksa', 5 => 'Final & cetak'];
                $kartuL = static fn (int $n): string => 'rounded-lg border p-3 ' . ($berikut === $n
                    ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600 dark:border-brand-500 dark:bg-brand-900/20 dark:ring-brand-500'
                    : ($selesai[$n] ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800'));
                $bulatL = static fn (int $n): string => 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold '
                    . ($selesai[$n] ? 'bg-green-600 text-white' : ($berikut === $n ? 'bg-brand-700 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-200'));
                $isiBulatL = static fn (int $n): string => $selesai[$n] ? ikon('check', 'h-3.5 w-3.5') . '<span class="sr-only">selesai</span>' : (string) $n;
                ?>
                <!-- Alur kerja honor -->
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Alur kerja honor</p>
                        <?php if ($terkunci): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Honor dikunci &mdash; hanya bisa dilihat, dicetak, atau dibuka kuncinya.</p>
                        <?php elseif ($berikut > 0): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Berikutnya: <b class="text-brand-700 dark:text-brand-400">Langkah <?= $berikut ?> &mdash; <?= esc($judulLangkah[$berikut]) ?></b></p>
                        <?php else: ?>
                            <p class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400"><?= ikon('check-circle', 'h-4 w-4') ?>Semua langkah beres.</p>
                        <?php endif; ?>
                    </div>
                    <ol class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <!-- 1. Penerima -->
                        <li class="<?= $kartuL(1) ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="<?= $bulatL(1) ?>"><?= $isiBulatL(1) ?></span>
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Penerima</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= $adaBaris ? count($baris) . ' orang menerima honor' : 'Belum ada penerima' ?></p></div>
                            </div>
                            <?php if (! $terkunci): ?>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-utama btn-xs" @click="alihPanel('penerima')" :aria-expanded="panel === 'penerima'" aria-controls="penerima"><?= ikon('plus', 'h-3.5 w-3.5') ?>Tambah penerima<?= $honorCalon !== [] ? ' (' . count($honorCalon) . ' tersedia)' : '' ?></button>
                                    <?php if ($adaBaris): ?><button type="button" class="btn btn-netral btn-xs" @click="alihPanel('urutan')" :aria-expanded="panel === 'urutan'" aria-controls="urutan">Atur urutan</button><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                        <!-- 2. Data surat -->
                        <li class="<?= $kartuL(2) ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="<?= $bulatL(2) ?>"><?= $isiBulatL(2) ?></span>
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Data surat &amp; tarif</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= $suratLengkap ? 'Nama penanda tangan terisi' : 'Nama ketua / bendahara / kepala sekolah belum lengkap' ?></p></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn btn-netral btn-xs" @click="alihPanel('surat')" :aria-expanded="panel === 'surat'" aria-controls="surat"><?= ikon('file-pen', 'h-3.5 w-3.5') ?>Data surat &amp; tanda tangan</button>
                                <a href="<?= $urlAtur ?>" class="btn btn-netral btn-xs"><?= ikon('cog', 'h-3.5 w-3.5') ?>Pengaturan tarif</a>
                            </div>
                        </li>
                        <!-- 3. Isi angka honor -->
                        <li class="<?= $kartuL(3) ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="<?= $bulatL(3) ?>"><?= $isiBulatL(3) ?></span>
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Isi angka honor</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= $selesai[3] ? 'Total Rp ' . $rp($honor['total']) : 'Belum ada angka' ?></p></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="<?= $base ?>/pembuat-soal<?= $qtp ?>" class="btn btn-netral btn-xs"><?= ikon('pen-nib', 'h-3.5 w-3.5') ?>Pembuat soal</a>
                                <a href="<?= $base ?>/honor/koreksi<?= $qtp ?>" class="btn btn-netral btn-xs flex-wrap" title="Ceklis pembagian lembar jawaban ke guru per rombel (Excel KOREKSI NILAI)">
                                    <?= ikon('clipboard-check', 'h-3.5 w-3.5') ?>Koreksi
                                    <?php if (($honorKoreksi ?? null) !== null): ?><span class="lencana lencana-merek px-1.5 py-0 text-[11px]"><?= (int) $honorKoreksi['guru'] ?> guru · <?= $rp($honorKoreksi['total']) ?> lembar</span><?php else: ?><span class="lencana lencana-abu px-1.5 py-0 text-[11px]">belum diisi</span><?php endif; ?>
                                </a>
                                <?php if (! $terkunci): ?>
                                    <button type="button" class="btn btn-sukses btn-xs" @click="alihPanel('hitung')" :aria-expanded="panel === 'hitung'" aria-controls="hitung"><?= ikon('chart', 'h-3.5 w-3.5') ?>Hitung otomatis</button>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-[11px] leading-snug text-gray-500 dark:text-gray-400">Pengawas &amp; transport diketik langsung di tabel di bawah.</p>
                        </li>
                        <!-- 4. Periksa -->
                        <li class="<?= $kartuL(4) ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="<?= $bulatL(4) ?>"><?= $isiBulatL(4) ?></span>
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white">Periksa</p><p class="text-xs text-gray-500 dark:text-gray-400"><?= ! $adaBaris ? 'Menunggu penerima' : ($nPerlu > 0 ? $nPerlu . ' temuan perlu dicek' : 'Tidak ada temuan') ?></p></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="#pemeriksaan" class="btn btn-netral btn-xs" @click.prevent="bukaPeriksa()"><?= ikon('file-search', 'h-3.5 w-3.5') ?>Lihat pemeriksaan</a>
                            </div>
                        </li>
                        <!-- 5. Final & cetak -->
                        <li class="<?= $kartuL(5) ?>">
                            <div class="flex items-start gap-2.5">
                                <span class="<?= $bulatL(5) ?>"><?= $isiBulatL(5) ?></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Final &amp; cetak</p>
                                    <ol class="mt-1 flex items-center gap-1 text-[11px] font-semibold" aria-label="Tahap honor">
                                        <?php foreach (['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'] as $kS => $lS): ?>
                                            <li class="rounded-full px-2 py-0.5 <?= $dok['status'] === $kS ? 'bg-brand-700 text-white' : 'border border-gray-200 bg-white text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400' ?>"<?= $dok['status'] === $kS ? ' aria-current="step"' : '' ?>><?= $lS ?></li>
                                            <?php if ($kS !== 'dikunci'): ?><li class="text-gray-300 dark:text-gray-600" aria-hidden="true">›</li><?php endif; ?>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                <?= $dok['status'] === 'draf' ? 'Masih disusun. Tandai Final setelah angka diperiksa.' : ($dok['status'] === 'final' ? 'Siap dibayar. Kunci setelah dibayar supaya tidak bisa berubah.' : 'Terkunci. Buka kunci hanya bila benar-benar perlu.') ?>
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php if ($dok['status'] === 'draf' && $baris !== []): ?>
                                    <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="final">
                                        <button type="submit" class="btn btn-utama btn-xs"><?= ikon('check', 'h-3.5 w-3.5') ?>Tandai Final</button></form>
                                <?php elseif ($dok['status'] === 'final'): ?>
                                    <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="draf">
                                        <button type="submit" class="btn btn-netral btn-xs">Kembalikan ke Draf</button></form>
                                    <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="dikunci">
                                        <button type="submit" class="btn btn-sukses btn-xs"
                                                data-confirm="KUNCI honor ini?<?= $nPerlu > 0 ? ' Masih ada ' . $nPerlu . ' temuan yang perlu dicek (lihat kartu Pemeriksaan).' : '' ?> Setelah dikunci tidak ada yang bisa diubah; kunci bisa dibuka lagi dengan alasan yang tercatat."
                                                data-confirm-judul="Kunci honor?" data-confirm-ya="Ya, kunci"><?= ikon('lock', 'h-3.5 w-3.5') ?>Kunci honor</button></form>
                                <?php elseif ($dok['status'] === 'dikunci'): ?>
                                    <button type="button" class="btn btn-netral btn-xs text-yellow-700 dark:text-yellow-400" @click="alihPanel('bukakunci')" :aria-expanded="panel === 'bukakunci'" aria-controls="bukakunci"><?= ikon('lock', 'h-3.5 w-3.5') ?>Buka kunci…</button>
                                <?php endif; ?>
                                <?php if ($baris !== []): ?>
                                    <div class="relative" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
                                        <button type="button" class="btn btn-utama btn-xs" x-ref="pemicu" @click="alih()" :aria-expanded="buka" aria-haspopup="true">
                                            <?= ikon('printer', 'h-3.5 w-3.5') ?>Cetak / Unduh<?= ikon('chevron-down', 'h-3.5 w-3.5') ?>
                                        </button>
                                        <div class="dropdown-menu w-72 xl:right-0" x-show="buka" x-cloak x-transition.origin.top.left.duration.150ms>
                                            <ul class="py-1">
                                                <li><a href="<?= $base ?>/honor/cetak/rekap-pdf<?= $qtp ?>" target="_blank" rel="noopener" class="dropdown-item block" @click="tutup()"><b class="block font-semibold text-gray-900 dark:text-white">Rekap honor (PDF)</b><span class="text-xs text-gray-500 dark:text-gray-400">Folio landscape, siap cetak &amp; tanda tangan</span></a></li>
                                                <li><a href="<?= $base ?>/honor/cetak/rekap-xlsx<?= $qtp ?>" class="dropdown-item block" @click="tutup()"><b class="block font-semibold text-gray-900 dark:text-white">Excel: Rekap + Slip</b><span class="text-xs text-gray-500 dark:text-gray-400">Rumus hidup, format rekap sekolah</span></a></li>
                                                <li><a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>" target="_blank" rel="noopener" class="dropdown-item block" @click="tutup()"><b class="block font-semibold text-gray-900 dark:text-white">Slip semua penerima (PDF)</b><span class="text-xs text-gray-500 dark:text-gray-400">Dua slip per halaman A4</span></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ol>

                    <!-- Aksi jarang dipakai -->
                    <details class="group mt-3 rounded-lg border border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-xs font-medium text-gray-700 select-none dark:text-gray-300">
                            Aksi lain (impor Excel, perbarui tarif, hapus honor)<?= ikon('chevron-down', 'h-4 w-4 shrink-0 text-gray-500 transition-transform group-open:rotate-180') ?>
                        </summary>
                        <div class="flex flex-wrap gap-2 border-t border-gray-200 px-3 py-3 dark:border-gray-600">
                            <?php if (! $terkunci): ?>
                                <button type="button" class="btn btn-netral btn-xs" @click="alihPanel('impor')" :aria-expanded="panel === 'impor'" aria-controls="impor"><?= ikon('upload', 'h-3.5 w-3.5') ?>Impor dari Excel</button>
                                <form method="post" action="<?= $base ?>/honor/sinkron" class="inline">
                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                    <button type="submit" class="btn btn-netral btn-xs" data-confirm="Perbarui komponen &amp; tarif honor ini dari Pengaturan Honor? Isian angka tetap aman." data-confirm-judul="Perbarui tarif?" data-confirm-ya="Ya, perbarui"><?= ikon('cog', 'h-3.5 w-3.5') ?>Perbarui tarif dari pengaturan</button>
                                </form>
                                <form method="post" action="<?= $base ?>/honor/hapus" class="ms-auto inline">
                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                    <button type="submit" class="btn btn-bahaya btn-xs" data-confirm="HAPUS seluruh honor <?= esc($label, 'attr') ?> (<?= count($baris) ?> penerima)? Tidak bisa dibatalkan." data-confirm-judul="Hapus honor?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-3.5 w-3.5') ?>Hapus honor ini</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Tidak ada aksi lain saat honor dikunci.</span>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

            <div id="bukakunci" x-cloak x-show="panel === 'bukakunci'" x-transition class="border-t border-yellow-200 bg-yellow-50 p-4 md:px-6 dark:border-yellow-800 dark:bg-yellow-900/20">
                <form method="post" action="<?= $base ?>/honor/status" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="final">
                    <label class="lbl" for="alasan-buka">Alasan membuka kunci (wajib, tercatat di Audit Log)</label>
                    <textarea id="alasan-buka" name="alasan" rows="2" required minlength="5" maxlength="200" class="inp" placeholder="Contoh: koreksi salah ketik jumlah pengawas Bu Rina"></textarea>
                    <button type="submit" class="btn btn-utama"><?= ikon('lock', 'h-4 w-4') ?>Buka kunci</button>
                </form>
            </div>

            <!-- Panel: tambah penerima -->
            <div id="penerima" x-cloak x-show="panel === 'penerima'" x-transition class="<?= $panelKelas ?>">
                <?php if ($honorCalon === []): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Semua orang di Master Guru sudah ada di daftar.</p>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/honor/penerima">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative w-full sm:max-w-xs">
                                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                                <input type="search" x-model="qCalon" placeholder="Cari nama…" class="inp py-2 ps-9" aria-label="Cari calon penerima">
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Centang orang yang menerima honor, lalu tekan Tambah.</span>
                        </div>
                        <div class="panel-gulir grid max-h-72 gap-1.5 rounded-lg border border-gray-200 bg-white p-2 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-600 dark:bg-gray-800">
                            <?php foreach ($honorCalon as $g): ?>
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                       data-cari="<?= esc(mb_strtolower((string) $g['nama'] . ' ' . $g['jabatan']), 'attr') ?>" x-show="cocokCalon($el)">
                                    <input type="checkbox" name="guru_ids[]" value="<?= (int) $g['id'] ?>" class="cek mt-0.5">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?></span>
                                        <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400"><?= esc($g['jabatan']) ?><?= (int) $g['bukan_pengajar'] === 1 ? ' · staf' : '' ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Tambah yang dicentang</button>
                            <button type="submit" formaction="<?= $base ?>/honor/penerima-semua" class="btn btn-netral"
                                    data-confirm="Tambahkan SEMUA <?= count($honorCalon) ?> orang yang belum ada? Yang tidak menerima honor bisa dihapus dari daftar." data-confirm-judul="Tambah semua?" data-confirm-ya="Ya, tambahkan"><?= ikon('users', 'h-4 w-4') ?>Tambah semua (<?= count($honorCalon) ?> orang)</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: hitung otomatis -->
            <div id="hitung" x-cloak x-show="panel === 'hitung'" x-transition class="<?= $panelKelas ?>">
                <?php
                $otoKomp = array_values(array_filter($komp, static fn (array $k): bool => in_array($k['sumber'], ['koreksi', 'rapot', 'soal'], true)));
                $penjelasan = [
                    'koreksi' => ($honorKoreksi ?? null) !== null
                        ? 'dari ceklis Koreksi (' . (int) $honorKoreksi['guru'] . ' guru, peserta ujian per kelas) — diatur di tombol Koreksi'
                        : 'perkiraan: jumlah siswa dari semua kelas yang diampu guru. Untuk hasil persis seperti Excel sekolah, isi dulu ceklis di tombol Koreksi',
                    'rapot' => 'jumlah siswa di kelas yang diwalikan', 'soal' => 'jumlah penugasan pembuat soal (diatur di tombol Pembuat soal)',
                ];
                ?>
                <?php if ($otoKomp === []): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Honor ini tidak memuat komponen yang bisa dihitung otomatis.</p>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/honor/hitung">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="mb-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">Angka diisi dari data sekolah sebagai <b>saran awal</b>. Tiap angka tetap bisa kamu ubah, dan isian yang <b>sudah kamu ubah tidak ditimpa</b>. Pengawas tidak dihitung otomatis (diketik sendiri).</p>
                        <div class="space-y-2">
                            <?php foreach ($otoKomp as $k): $g = $honorGambaran[$k['sumber']] ?? ['orang' => 0, 'total' => 0];
                                // Rapot di ASTS biasanya 0 (kolomnya tetap ada di rekap): jangan dicentang otomatis.
                                $centang = $k['sumber'] !== 'rapot' || in_array($periode['jenis'], ['ASAS', 'ASAT'], true); ?>
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <input type="checkbox" name="sumber[]" value="<?= esc($k['sumber'], 'attr') ?>"<?= $centang ? ' checked' : '' ?> class="cek mt-0.5">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-gray-900 dark:text-white"><?= esc($k['nama']) ?></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400"><?= esc($penjelasan[$k['sumber']] ?? '') ?> — saat ini <?= (int) $g['orang'] ?> orang, total <?= $rp($g['total']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-gray-900 dark:text-white">
                            <input type="checkbox" name="timpa" value="1" class="cek mt-0.5">
                            Timpa juga isian yang sudah saya ubah sendiri
                        </label>
                        <button type="submit" class="btn btn-sukses mt-4"><?= ikon('chart', 'h-4 w-4') ?>Hitung sekarang</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: impor Excel -->
            <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="<?= $panelKelas ?>">
                <form method="post" action="<?= $base ?>/honor/impor/unggah" enctype="multipart/form-data" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Pilih rekap honor Excel lama (.xlsx). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> — belum ada yang tersimpan sampai kamu menekan Terapkan.</p>
                    <label class="lbl" for="honor-berkas">Berkas Excel (.xlsx)</label>
                    <input type="file" id="honor-berkas" name="berkas" accept=".xlsx" required class="inp-berkas">
                    <button type="submit" class="btn btn-utama"><?= ikon('file-search', 'h-4 w-4') ?>Baca berkas</button>
                </form>
            </div>

            <!-- Panel: atur urutan -->
            <div id="urutan" x-cloak x-show="panel === 'urutan'" x-transition class="<?= $panelKelas ?>">
                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">Mengatur ulang <b>nomor urut</b> penerima (dan, bila kamu mau, label jabatan). <b>Angka isian tidak berubah.</b> Kamu melihat pratinjau dulu; belum ada yang tersimpan sampai kamu menekan Terapkan.</p>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-600 dark:bg-gray-800">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Menurut jabatan</p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">Kepala Sekolah, Kepala TU, para Waka, Kaprog, … lalu guru &amp; staf &mdash; sama seperti honor yang baru dibuat. Cocok setelah jabatan di Master Guru dirapikan. Urutan manual yang sekarang akan diganti.</p>
                        <a href="<?= $base ?>/honor/urutan<?= $qtp ?>&amp;mode=aturan" class="btn btn-netral btn-sm mt-3"><?= ikon('eye', 'h-4 w-4') ?>Lihat pratinjau</a>
                    </div>
                    <form method="post" action="<?= $base ?>/honor/urutan/unggah" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-600 dark:bg-gray-800">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Ikuti Excel sekolah</p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">Unggah rekap honor Excel (mis. HONOR ASTS.xlsx). Hanya <b>urutan nama dan tulisan jabatan</b> yang dipakai &mdash; angkanya diabaikan.</p>
                        <label class="sr-only" for="urutan-berkas">Berkas Excel (.xlsx)</label>
                        <input type="file" id="urutan-berkas" name="berkas" accept=".xlsx" required class="inp-berkas mt-3">
                        <button type="submit" class="btn btn-utama btn-sm mt-3"><?= ikon('file-search', 'h-4 w-4') ?>Baca &amp; lihat pratinjau</button>
                    </form>
                </div>
            </div>

            <!-- Panel: data surat -->
            <div id="surat" x-cloak x-show="panel === 'surat'" x-transition class="<?= $panelKelas ?>">
                <form method="post" action="<?= $base ?>/honor/dokumen" class="grid gap-3 sm:grid-cols-2">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <div class="sm:col-span-2">
                        <label class="lbl" for="surat-judul">Judul rekap</label>
                        <input type="text" id="surat-judul" name="judul" maxlength="200" required value="<?= esc((string) $olds('judul', $dok['judul']), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="surat-tempat">Tempat</label>
                        <input type="text" id="surat-tempat" name="tempat" maxlength="80" value="<?= esc((string) $olds('tempat', $dok['tempat']), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="surat-tanggal">Tanggal surat</label>
                        <input type="date" id="surat-tanggal" name="tanggal" value="<?= esc((string) $olds('tanggal', $tglDok), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="surat-ketua">Ketua panitia</label>
                        <input type="text" id="surat-ketua" name="ketua_nama" maxlength="150" value="<?= esc((string) $olds('ketua_nama', $dok['ketua_nama']), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="surat-bendahara">Bendahara</label>
                        <input type="text" id="surat-bendahara" name="bendahara_nama" maxlength="150" value="<?= esc((string) $olds('bendahara_nama', $dok['bendahara_nama']), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl" for="surat-kepsek">Kepala Sekolah (menyetujui)</label>
                        <input type="text" id="surat-kepsek" name="kepsek_nama" maxlength="150" value="<?= esc((string) $olds('kepsek_nama', $dok['kepsek_nama']), 'attr') ?>"<?= $terkunci ? ' disabled' : '' ?> class="inp">
                    </div>
                    <?php if (! $terkunci): ?>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan data surat</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- ===== Pemeriksaan honor ===== -->
        <?php $periksa = $honorPeriksa ?? []; $nPer = count(array_filter($periksa, static fn (array $t): bool => $t['level'] === 'peringatan')); $nCat = count($periksa) - $nPer; ?>
        <details id="pemeriksaan" class="kartu group scroll-mt-20 <?= $nPer > 0 ? 'border-yellow-300 dark:border-yellow-800' : '' ?>"<?= $nPer > 0 ? ' open' : '' ?>>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 md:px-6">
                <span class="text-base font-semibold text-gray-900 dark:text-white">Pemeriksaan honor</span>
                <span class="flex flex-wrap items-center gap-1.5">
                    <?php if ($periksa === []): ?><span class="lencana lencana-hijau">Tidak ada temuan</span><?php endif; ?>
                    <?php if ($nPer > 0): ?><span class="lencana lencana-kuning"><?= $nPer ?> perlu dicek</span><?php endif; ?>
                    <?php if ($nCat > 0): ?><span class="lencana lencana-abu"><?= $nCat ?> catatan</span><?php endif; ?>
                    <?= ikon('chevron-down', 'h-4 w-4 text-gray-500 transition-transform group-open:rotate-180') ?>
                </span>
            </summary>
            <ul class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <?php if ($periksa === []): ?><li class="px-4 py-3 text-sm text-gray-500 md:px-6 dark:text-gray-400">Semua pemeriksaan lolos. Diperbarui setiap halaman dimuat.</li><?php endif; ?>
                <?php foreach ($periksa as $t): $perlu = $t['level'] === 'peringatan'; ?>
                    <li class="flex gap-2.5 px-4 py-2.5 text-sm md:px-6 <?= $perlu ? 'bg-yellow-50 text-yellow-900 dark:bg-yellow-900/20 dark:text-yellow-200' : 'text-gray-600 dark:text-gray-300' ?>">
                        <?= ikon($perlu ? 'exclamation-circle' : 'info-circle', 'mt-0.5 h-4 w-4 shrink-0 ' . ($perlu ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-400')) ?>
                        <span><?= esc($t['teks']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>

        <!-- ===== Grid isian ===== -->
        <?php if ($baris === []): ?>
            <div class="kartu"><?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada penerima', 'teks' => 'Tekan Tambah penerima di atas untuk memilih guru dan staf yang menerima honor.']) ?></div>
        <?php else: ?>
            <section class="kartu overflow-hidden" aria-label="Isian honor per penerima">
                <div class="kartu-kepala">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Isian <b>tersimpan otomatis</b> begitu kamu pindah kolom. Angka rupiah dan total dihitung oleh server.</p>
                    <div class="relative w-full sm:w-64">
                        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                        <input type="search" x-model="cari" placeholder="Cari nama penerima…" class="inp py-2 ps-9" aria-label="Cari nama penerima">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="px-3 py-3 md:min-w-[var(--minw)] md:px-0 md:py-0">
                        <!-- Judul kolom (laptop) -->
                        <div class="hidden border-b border-gray-200 bg-gray-50 px-3 py-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase md:grid md:gap-2 md:[grid-template-columns:var(--kolom)] dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            <div class="md:sticky md:left-0 md:z-10 md:bg-gray-50 dark:md:bg-gray-700">Nama &amp; jabatan</div>
                            <?php foreach ($komp as $k): ?>
                                <div class="text-right">
                                    <?= esc($k['nama']) ?>
                                    <span class="block font-normal tracking-normal text-gray-400 normal-case"><?= $k['tipe'] === 'tetap' ? 'nominal (Rp)' : 'Rp ' . $rp($k['tarif']) . ' / ' . esc($k['satuan'] ?: 'satuan') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-right">Total</div>
                            <div></div>
                        </div>

                        <?php foreach ($baris as $i => $b): ?>
                            <div data-baris-row data-baris-id="<?= (int) $b['id'] ?>" data-nama="<?= esc(mb_strtolower((string) $b['nama']), 'attr') ?>"
                                 class="group mb-3 rounded-lg border border-gray-200 bg-white p-3 md:mb-0 md:grid md:items-center md:gap-2 md:rounded-none md:border-0 md:border-b md:border-gray-200 md:px-3 md:py-2 md:[grid-template-columns:var(--kolom)] md:hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:md:hover:bg-gray-700/60">
                                <div class="md:sticky md:left-0 md:z-10 md:bg-white md:pr-2 md:group-hover:bg-gray-50 dark:md:bg-gray-800 dark:md:group-hover:bg-gray-700">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 items-start gap-1.5">
                                            <?php if (! $terkunci): ?>
                                                <input type="text" inputmode="numeric" value="<?= $i + 1 ?>" data-no data-baris="<?= (int) $b['id'] ?>" data-awal="<?= $i + 1 ?>" maxlength="3"
                                                       aria-label="Nomor urut <?= esc($b['nama'], 'attr') ?>" title="Ketik nomor lalu Enter untuk memindahkan baris ini"
                                                       @focus="$el.select()" @change="pindah($el)" @keydown.enter.prevent="$el.blur()"
                                                       class="mt-px h-6 w-9 shrink-0 rounded-sm border border-gray-200 bg-white text-center text-xs text-gray-500 tabular-nums outline-hidden focus:border-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            <?php else: ?>
                                                <span class="font-normal text-gray-400"><?= $i + 1 ?>.</span>
                                            <?php endif; ?>
                                            <p class="min-w-0 text-sm leading-snug font-semibold text-gray-900 dark:text-white"><?= esc($b['nama']) ?></p>
                                        </div>
                                        <div class="flex shrink-0 gap-1.5 md:hidden">
                                            <?php if (! $terkunci): ?>
                                                <button type="button" class="btn btn-netral btn-xs px-2" @click="geserBaris($el, -1)" data-baris="<?= (int) $b['id'] ?>" aria-label="Naikkan <?= esc($b['nama'], 'attr') ?>"><?= ikon('caret-up', 'h-3.5 w-3.5', 'solid') ?></button>
                                                <button type="button" class="btn btn-netral btn-xs px-2" @click="geserBaris($el, 1)" data-baris="<?= (int) $b['id'] ?>" aria-label="Turunkan <?= esc($b['nama'], 'attr') ?>"><?= ikon('caret-down', 'h-3.5 w-3.5', 'solid') ?></button>
                                            <?php endif; ?>
                                            <a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>&amp;baris=<?= (int) $b['id'] ?>" target="_blank" rel="noopener" class="btn btn-netral btn-xs"><?= ikon('printer', 'h-3.5 w-3.5') ?>Slip</a>
                                            <?php if (! $terkunci): ?>
                                                <button type="submit" form="hb-<?= (int) $b['id'] ?>" class="btn btn-bahaya btn-xs" data-confirm="Hapus <?= esc($b['nama'], 'attr') ?> dari daftar honor?" data-confirm-judul="Hapus penerima?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-3.5 w-3.5') ?>Hapus</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <label class="mt-2 mb-0.5 block text-[11px] font-semibold text-gray-500 md:hidden dark:text-gray-400" for="jab-<?= (int) $b['id'] ?>">Jabatan</label>
                                    <input type="text" id="jab-<?= (int) $b['id'] ?>" maxlength="120" value="<?= esc((string) $b['jabatan'], 'attr') ?>" data-jabatan data-baris="<?= (int) $b['id'] ?>" data-awal="<?= esc((string) $b['jabatan'], 'attr') ?>"
                                           <?= $terkunci ? 'disabled' : '' ?> @change="simpanJabatan($el)" placeholder="Jabatan" aria-label="Jabatan <?= esc($b['nama'], 'attr') ?>"
                                           class="w-full rounded-md border border-transparent bg-transparent py-0.5 text-xs text-gray-500 outline-hidden transition hover:border-gray-200 hover:px-1.5 focus:border-brand-500 focus:bg-white focus:px-1.5 max-md:rounded-lg max-md:border-gray-200 max-md:px-2 max-md:py-1.5 max-md:text-sm max-md:text-gray-600 dark:text-gray-400 dark:hover:border-gray-600 dark:focus:bg-gray-700 dark:max-md:border-gray-600 dark:max-md:text-gray-300">
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2 md:contents">
                                    <?php foreach ($komp as $k):
                                        $kid  = (int) $k['id'];
                                        $isi  = $b['nilai'][$kid] ?? ['nilai' => 0, 'otomatis' => null];
                                        $tetap = $k['tipe'] === 'tetap';
                                        $tampil = $tetap ? $rp($isi['nilai']) : (string) (int) $isi['nilai'];
                                        $st   = $isi['otomatis'] === null ? '' : ((int) $isi['otomatis'] === (int) $isi['nilai'] ? 'otomatis' : 'diubah');
                                        ?>
                                        <?php // div.min-w-0 = wadah satu sel (dicari honor-grid.js pakai() lewat closest('div.min-w-0')). ?>
                                        <div class="min-w-0">
                                            <label class="mb-0.5 block truncate text-[11px] font-semibold text-gray-500 md:hidden dark:text-gray-400"><?= esc($k['nama']) ?><?= $tetap ? '' : ' (' . esc($k['satuan'] ?: 'satuan') . ')' ?></label>
                                            <input type="text" inputmode="numeric" autocomplete="off" value="<?= esc($tampil, 'attr') ?>"
                                                   data-nilai data-baris="<?= (int) $b['id'] ?>" data-dk="<?= $kid ?>" data-tipe="<?= $tetap ? 'tetap' : 'satuan' ?>" data-awal="<?= esc($tampil, 'attr') ?>"
                                                   <?= $terkunci ? 'disabled' : '' ?> aria-label="<?= esc($k['nama'] . ' — ' . $b['nama'], 'attr') ?>"
                                                   @focus="$el.select()" @change="simpanSel($el)" @keydown.enter.prevent="turun($el)"
                                                   class="inp px-2 py-1.5 text-right tabular-nums">
                                            <p class="mt-0.5 flex flex-wrap items-center justify-end gap-1 text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                                                <?php if ($k['kode'] === 'pengawas' && ! $terkunci && (int) ($honorPetunjuk[(int) ($b['guru_id'] ?? 0)] ?? 0) > 0 && (int) ($honorPetunjuk[(int) $b['guru_id']]) !== (int) $isi['nilai']): ?>
                                                    <button type="button" @click="pakai($el)" data-jumlah="<?= (int) $honorPetunjuk[(int) $b['guru_id']] ?>" class="lencana lencana-merek px-1 py-0 text-[10px] underline" title="Jumlah sesi menurut jadwal pengawas — klik untuk memakainya">jadwal: <?= (int) $honorPetunjuk[(int) $b['guru_id']] ?></button>
                                                <?php endif; ?>
                                                <?php if (! $tetap): ?><span id="rp-<?= (int) $b['id'] ?>-<?= $kid ?>">Rp <?= $rp($b['per'][$kid] ?? 0) ?></span><?php endif; ?>
                                                <span id="st-<?= (int) $b['id'] ?>-<?= $kid ?>" class="lencana px-1 py-0 text-[10px] <?= $st === 'diubah' ? 'lencana-kuning' : 'lencana-hijau' ?>"><?= $st === '' ? '' : ($st === 'diubah' ? 'diubah' : 'otomatis') ?></span>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-2 md:mt-0 md:block md:border-0 md:pt-0 md:text-right dark:border-gray-700">
                                    <span class="text-xs font-semibold text-gray-500 md:hidden dark:text-gray-400">Total</span>
                                    <span class="text-sm font-bold text-gray-900 tabular-nums dark:text-white">Rp <span id="tb-<?= (int) $b['id'] ?>"><?= $rp($b['total']) ?></span></span>
                                </div>

                                <div class="hidden items-center justify-end gap-0.5 md:flex">
                                    <?php if (! $terkunci): ?>
                                        <button type="button" @click="geserBaris($el, -1)" data-baris="<?= (int) $b['id'] ?>" class="tombol-tutup p-1" title="Naikkan satu baris" aria-label="Naikkan <?= esc($b['nama'], 'attr') ?>"><?= ikon('caret-up', 'h-3.5 w-3.5', 'solid') ?></button>
                                        <button type="button" @click="geserBaris($el, 1)" data-baris="<?= (int) $b['id'] ?>" class="tombol-tutup p-1" title="Turunkan satu baris" aria-label="Turunkan <?= esc($b['nama'], 'attr') ?>"><?= ikon('caret-down', 'h-3.5 w-3.5', 'solid') ?></button>
                                    <?php endif; ?>
                                    <a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>&amp;baris=<?= (int) $b['id'] ?>" target="_blank" rel="noopener" class="tombol-tutup p-1.5" title="Cetak slip <?= esc($b['nama'], 'attr') ?>" aria-label="Cetak slip <?= esc($b['nama'], 'attr') ?>"><?= ikon('printer', 'h-4 w-4') ?></a>
                                    <?php if (! $terkunci): ?>
                                        <button type="submit" form="hb-<?= (int) $b['id'] ?>" class="tombol-tutup p-1.5 hover:text-red-700 dark:hover:text-red-500" title="Hapus dari daftar" aria-label="Hapus <?= esc($b['nama'], 'attr') ?> dari daftar"
                                                data-confirm="Hapus <?= esc($b['nama'], 'attr') ?> dari daftar honor?" data-confirm-judul="Hapus penerima?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                                    <?php endif; ?>
                                </div>
                                <form id="hb-<?= (int) $b['id'] ?>" method="post" action="<?= $base ?>/honor/baris/<?= (int) $b['id'] ?>/hapus" class="hidden">
                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <p x-cloak x-show="tanpaHasil()" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada nama yang cocok.</p>

                        <!-- JUMLAH -->
                        <div class="mt-1 rounded-lg border-2 border-gray-200 bg-gray-50 p-3 md:mt-0 md:grid md:items-center md:gap-2 md:rounded-none md:border-0 md:border-t-2 md:px-3 md:py-3 md:[grid-template-columns:var(--kolom)] dark:border-gray-600 dark:bg-gray-700">
                            <div class="text-sm font-bold tracking-wide text-gray-900 uppercase md:sticky md:left-0 md:z-10 md:bg-gray-50 dark:text-white dark:md:bg-gray-700">Jumlah</div>
                            <div class="mt-2 grid grid-cols-2 gap-2 md:contents">
                                <?php foreach ($komp as $k): $kid = (int) $k['id']; ?>
                                    <div class="min-w-0 md:text-right">
                                        <p class="truncate text-[11px] font-semibold text-gray-500 md:hidden dark:text-gray-400"><?= esc($k['nama']) ?></p>
                                        <p class="text-sm font-bold text-gray-900 tabular-nums dark:text-white">Rp <span id="tk-<?= $kid ?>"><?= $rp($honor['total_komponen'][$kid] ?? 0) ?></span></p>
                                        <?php if ($k['tipe'] === 'satuan'): ?>
                                            <p class="text-[11px] text-gray-500 tabular-nums dark:text-gray-400"><span id="tj-<?= $kid ?>"><?= $rp($honor['total_jumlah'][$kid] ?? 0) ?></span> <?= esc($k['satuan'] ?: 'satuan') ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-2 md:mt-0 md:block md:border-0 md:pt-0 md:text-right dark:border-gray-600">
                                <span class="text-xs font-semibold text-gray-500 md:hidden dark:text-gray-400">Total keseluruhan</span>
                                <span class="text-base font-bold text-brand-700 tabular-nums dark:text-brand-400">Rp <span id="tot-semua"><?= $rp($honor['total']) ?></span></span>
                            </div>
                            <div class="hidden md:block"></div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <script defer src="<?= base_url('assets/js/admin/honor-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/honor-grid.js') ?>" <?= csp_script_nonce() ?>></script>
<?php endif; ?>
