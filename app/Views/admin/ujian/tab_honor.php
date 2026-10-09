<?php
/**
 * Tab Honor — KHUSUS ADMIN. Daftar penerima + grid isian per komponen + total (dihitung SERVER, Libraries\HonorDokumen).
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
$statusWarna = ['draf' => 'bg-slate-100 text-slate-600 border-slate-200', 'final' => 'bg-sky-50 text-sky-700 border-sky-200', 'dikunci' => 'bg-emerald-50 text-emerald-700 border-emerald-200'];
?>

<?php if ($honor === null): ?>
    <!-- ===================== Belum ada honor ===================== -->
    <section class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="px-5 py-8 text-center sm:px-8">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="mt-4 text-lg font-bold text-slate-800">Belum ada honor untuk <?= esc($label) ?></h3>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-500">
                Honor dibuat sekali per ujian. Daftar komponen dan tarif di bawah disalin ke honor ini, jadi bila tarif diubah nanti,
                honor yang sudah dibuat tidak ikut berubah.
            </p>
        </div>

        <?php if ($honorPratinjau === []): ?>
            <div class="mx-5 mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:mx-8">
                Belum ada komponen honor yang aktif untuk ujian ini. Aktifkan dulu di
                <a href="<?= $urlAtur ?>" class="font-semibold underline">Pengaturan Honor</a>.
            </div>
        <?php else: ?>
            <div class="border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-8">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Komponen yang dipakai</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($honorPratinjau as $k): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                            <?= esc($k['nama']) ?>
                            <span class="font-normal text-slate-400">
                                <?= $k['tipe'] === 'tetap' ? 'nominal per orang' : 'Rp ' . $rp($k['tarif']) . ' / ' . esc($k['satuan'] ?: 'satuan') ?>
                            </span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 text-xs text-slate-500">Tarif diatur di <a href="<?= $urlAtur ?>" class="font-semibold text-brand-700 underline">Pengaturan Honor</a>.</p>
            </div>

            <form method="post" action="<?= $base ?>/honor/buat" class="border-t border-slate-100 px-5 py-5 sm:px-8">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <?php if ($honorLain !== []): ?>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Mulai dari</label>
                    <select name="salin_dari" class="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500">
                        <option value="0">Daftar kosong (pilih penerima sendiri)</option>
                        <?php foreach ($honorLain as $d): ?>
                            <option value="<?= (int) $d['id'] ?>">Salin penerima &amp; tunjangan tetap dari <?= esc((UjianPeriodeModel::JENIS_LABEL[$d['jenis']] ?? $d['jenis']) . ' — TP ' . $d['tahun_ajaran']) ?> (<?= (int) $d['jml'] ?> orang)</option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="submit" class="w-full rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95 sm:w-auto">Buat honor <?= esc($label) ?></button>
            </form>
            <form method="post" action="<?= $base ?>/honor/impor/unggah" enctype="multipart/form-data" class="border-t border-slate-100 bg-slate-50 px-5 py-5 sm:px-8">
                <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Atau impor dari Excel lama</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="file" name="berkas" accept=".xlsx" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                    <button type="submit" class="shrink-0 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Baca berkas</button>
                </div>
                <p class="mt-2 text-xs text-slate-500">Honor dibuat otomatis setelah kamu menyetujui hasil bacaan.</p>
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
    ];
    $tglDok = $dok['tanggal'] ? date('Y-m-d', strtotime((string) $dok['tanggal'])) : '';
    $olds   = static fn (string $k, $asal) => old($k, null, false) ?? $asal;
    $bukaSurat = old('judul', null, false) !== null;
    ?>
    <div x-data="honorGrid" data-config="<?= esc(json_encode($cfg), 'attr') ?>" class="space-y-4">
        <input type="hidden" id="honor-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

        <?php $peringatanImpor = session()->getFlashdata('honor_peringatan') ?: []; ?>
        <?php if ($peringatanImpor !== []): ?>
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <p class="font-bold">Perhatikan hasil impor:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($peringatanImpor as $w): ?><li><?= esc($w) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <!-- Pesan simpan otomatis -->
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" x-cloak x-show="pesan !== ''" x-transition.opacity>
            <div class="pointer-events-auto max-w-md rounded-xl px-4 py-3 text-sm font-semibold shadow-lg"
                 :class="galat ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'" x-text="pesan" role="status" aria-live="polite"></div>
        </div>

        <!-- ===== Ringkasan & aksi ===== -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-bold text-slate-800"><?= esc($dok['judul'] ?: 'Honor ' . $label) ?></h3>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $statusWarna[$dok['status']] ?? $statusWarna['draf'] ?>"><?= esc($statusLabel[$dok['status']] ?? $dok['status']) ?></span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        <?= esc($label) ?>
                        <?php if ($dok['tanggal']): ?>&nbsp;·&nbsp;<?= esc(($dok['tempat'] ? $dok['tempat'] . ', ' : '') . date('d/m/Y', strtotime((string) $dok['tanggal']))) ?><?php endif; ?>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex sm:gap-6">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Penerima</p>
                        <p class="text-xl font-extrabold tabular-nums text-slate-800" id="ringkas-penerima"><?= count($baris) ?></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total honor</p>
                        <p class="text-xl font-extrabold tabular-nums text-brand-700">Rp <span id="ringkas-total"><?= $rp($honor['total']) ?></span></p>
                    </div>
                </div>
            </div>

            <?php if ($terkunci): ?>
                <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Honor ini sudah DIKUNCI<?= ! empty($dok['dikunci_oleh']) ? ' oleh ' . esc($dok['dikunci_oleh']) : '' ?><?= ! empty($dok['dikunci_at']) ? ' pada ' . esc(date('d/m/Y H:i', strtotime((string) $dok['dikunci_at']))) : '' ?> — tidak bisa diubah.</p>
            <?php endif; ?>
            <?php if (($honorUtuh ?? null) === false): ?>
                <p class="mt-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-bold text-red-800" role="alert">PERINGATAN: isi honor ini BERUBAH sejak dikunci (tidak sama dengan sidik jari saat dikunci). Periksa Audit Log; bila perubahan tidak sah, hubungi pengelola sistem.</p>
            <?php endif; ?>

            <!-- Status: Draf → Final → Dikunci -->
            <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                <ol class="flex items-center gap-1.5 text-xs font-semibold" aria-label="Tahap honor">
                    <?php foreach (['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'] as $kS => $lS): ?>
                        <li class="rounded-full px-2.5 py-1 <?= $dok['status'] === $kS ? 'bg-brand-700 text-white' : 'bg-white text-slate-400 border border-slate-200' ?>" <?= $dok['status'] === $kS ? 'aria-current="step"' : '' ?>><?= $lS ?></li>
                        <?php if ($kS !== 'dikunci'): ?><li class="text-slate-300" aria-hidden="true">›</li><?php endif; ?>
                    <?php endforeach; ?>
                </ol>
                <span class="text-xs text-slate-500">
                    <?= $dok['status'] === 'draf' ? 'Masih disusun. Tandai Final setelah angka diperiksa.' : ($dok['status'] === 'final' ? 'Siap dibayar. Kunci setelah dibayar supaya tidak bisa berubah.' : 'Terkunci. Buka kunci hanya bila benar-benar perlu.') ?>
                </span>
                <div class="ml-auto flex flex-wrap gap-2">
                    <?php $nPerlu = count(array_filter($honorPeriksa ?? [], static fn (array $t): bool => $t['level'] === 'peringatan')); ?>
                    <?php if ($dok['status'] === 'draf' && $baris !== []): ?>
                        <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="final">
                            <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-sky-700 active:scale-95">Tandai Final</button></form>
                    <?php elseif ($dok['status'] === 'final'): ?>
                        <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="draf">
                            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">Kembalikan ke Draf</button></form>
                        <form method="post" action="<?= $base ?>/honor/status" class="inline"><?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="dikunci">
                            <button type="submit" onclick="return confirm('KUNCI honor ini?<?= $nPerlu > 0 ? ' Masih ada ' . $nPerlu . ' temuan yang perlu dicek (lihat kartu Pemeriksaan).' : '' ?> Setelah dikunci tidak ada yang bisa diubah; kunci bisa dibuka lagi dengan alasan yang tercatat.')"
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-700 active:scale-95">Kunci honor</button></form>
                    <?php elseif ($dok['status'] === 'dikunci'): ?>
                        <button type="button" @click="panel = (panel === 'bukakunci' ? '' : 'bukakunci')" class="rounded-lg border border-amber-400 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-100">Buka kunci…</button>
                    <?php endif; ?>
                </div>
            </div>
            <div id="bukakunci" x-cloak x-show="panel === 'bukakunci'" x-transition class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <form method="post" action="<?= $base ?>/honor/status" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>"><input type="hidden" name="ke" value="final">
                    <label class="block text-sm font-semibold text-amber-900" for="alasan-buka">Alasan membuka kunci (wajib, tercatat di Audit Log)</label>
                    <textarea id="alasan-buka" name="alasan" rows="2" required minlength="5" maxlength="200" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm outline-none focus:border-amber-500" placeholder="Contoh: koreksi salah ketik jumlah pengawas Bu Rina"></textarea>
                    <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-amber-700 active:scale-95">Buka kunci</button>
                </form>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <?php if (! $terkunci): ?>
                    <button type="button" @click="panel = (panel === 'penerima' ? '' : 'penerima')" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-600 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">+ Tambah penerima<?= $honorCalon !== [] ? ' (' . count($honorCalon) . ' tersedia)' : '' ?></button>
                <?php endif; ?>
                <button type="button" @click="panel = (panel === 'surat' ? '' : 'surat')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Data surat &amp; tanda tangan</button>
                <a href="<?= $urlAtur ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Pengaturan tarif</a>
                <a href="<?= $base ?>/pembuat-soal<?= $qtp ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Pembuat soal</a>
                <?php if ($baris !== []): ?>
                    <div class="relative" @click.outside="menuCetak = false">
                        <button type="button" @click="menuCetak = !menuCetak" aria-haspopup="true" :aria-expanded="menuCetak" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-800">
                            Cetak / Unduh
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-cloak x-show="menuCetak" x-transition class="absolute left-0 z-30 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <a href="<?= $base ?>/honor/cetak/rekap-pdf<?= $qtp ?>" target="_blank" rel="noopener" class="block px-4 py-2.5 text-sm hover:bg-slate-50"><b class="block text-slate-800">Rekap honor (PDF)</b><span class="text-xs text-slate-500">Folio landscape, siap cetak &amp; tanda tangan</span></a>
                            <a href="<?= $base ?>/honor/cetak/rekap-xlsx<?= $qtp ?>" class="block px-4 py-2.5 text-sm hover:bg-slate-50"><b class="block text-slate-800">Excel: Rekap + Slip</b><span class="text-xs text-slate-500">Rumus hidup, format rekap sekolah</span></a>
                            <a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>" target="_blank" rel="noopener" class="block px-4 py-2.5 text-sm hover:bg-slate-50"><b class="block text-slate-800">Slip semua penerima (PDF)</b><span class="text-xs text-slate-500">Dua slip per halaman A4</span></a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (! $terkunci): ?>
                    <button type="button" @click="panel = (panel === 'hitung' ? '' : 'hitung')" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-emerald-50 px-3.5 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">Hitung otomatis</button>
                    <button type="button" @click="panel = (panel === 'impor' ? '' : 'impor')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Impor dari Excel</button>
                <?php endif; ?>
                <?php if (! $terkunci): ?>
                    <form method="post" action="<?= $base ?>/honor/sinkron" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <button type="submit" onclick="return confirm('Perbarui komponen & tarif honor ini dari Pengaturan Honor? Isian angka tetap aman.')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Perbarui tarif dari pengaturan</button>
                    </form>
                    <form method="post" action="<?= $base ?>/honor/hapus" class="ml-auto inline">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <button type="submit" onclick="return confirm('HAPUS seluruh honor <?= esc($label, 'js') ?> (<?= count($baris) ?> penerima)? Tidak bisa dibatalkan.')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3.5 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50">Hapus honor ini</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: tambah penerima -->
            <div id="penerima" x-cloak x-show="panel === 'penerima'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4" x-data="{ q: '' }">
                <?php if ($honorCalon === []): ?>
                    <p class="text-sm text-slate-500">Semua orang di Master Guru sudah ada di daftar.</p>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/honor/penerima">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="search" x-model="q" placeholder="Cari nama…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 sm:max-w-xs" aria-label="Cari calon penerima">
                            <span class="text-xs text-slate-500">Centang orang yang menerima honor, lalu tekan Tambah.</span>
                        </div>
                        <div class="grid max-h-72 gap-1.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($honorCalon as $g): ?>
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                                       x-show="q === '' || <?= esc(json_encode(mb_strtolower((string) $g['nama'] . ' ' . $g['jabatan'])), 'attr') ?>.includes(q.toLowerCase())">
                                    <input type="checkbox" name="guru_ids[]" value="<?= (int) $g['id'] ?>" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-slate-700"><?= esc($g['nama']) ?></span>
                                        <span class="block truncate text-[11px] text-slate-400"><?= esc($g['jabatan']) ?><?= (int) $g['bukan_pengajar'] === 1 ? ' · staf' : '' ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="submit" class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Tambah yang dicentang</button>
                            <button type="submit" formaction="<?= $base ?>/honor/penerima-semua"
                                    onclick="return confirm('Tambahkan SEMUA <?= count($honorCalon) ?> orang yang belum ada? Yang tidak menerima honor bisa dihapus dari daftar.')"
                                    class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Tambah semua (<?= count($honorCalon) ?> orang)</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: hitung otomatis -->
            <div id="hitung" x-cloak x-show="panel === 'hitung'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <?php
                $otoKomp = array_values(array_filter($komp, static fn (array $k): bool => in_array($k['sumber'], ['koreksi', 'rapot', 'soal'], true)));
                $penjelasan = ['koreksi' => 'jumlah siswa dari semua kelas yang diampu guru', 'rapot' => 'jumlah siswa di kelas yang diwalikan', 'soal' => 'jumlah penugasan pembuat soal (diatur di tombol Pembuat soal)'];
                ?>
                <?php if ($otoKomp === []): ?>
                    <p class="text-sm text-slate-500">Honor ini tidak memuat komponen yang bisa dihitung otomatis.</p>
                <?php else: ?>
                    <form method="post" action="<?= $base ?>/honor/hitung">
                        <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                        <p class="mb-3 text-sm leading-relaxed text-slate-600">Angka diisi dari data sekolah sebagai <b>saran awal</b>. Tiap angka tetap bisa kamu ubah, dan isian yang <b>sudah kamu ubah tidak ditimpa</b>. Pengawas tidak dihitung otomatis (diketik sendiri).</p>
                        <div class="space-y-2">
                            <?php foreach ($otoKomp as $k): $g = $honorGambaran[$k['sumber']] ?? ['orang' => 0, 'total' => 0];
                                // Rapot di ASTS biasanya 0 (kolomnya tetap ada di rekap): jangan dicentang otomatis.
                                $centang = $k['sumber'] !== 'rapot' || in_array($periode['jenis'], ['ASAS', 'ASAT'], true); ?>
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm">
                                    <input type="checkbox" name="sumber[]" value="<?= esc($k['sumber'], 'attr') ?>" <?= $centang ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-700"><?= esc($k['nama']) ?></span>
                                        <span class="block text-xs text-slate-500"><?= esc($penjelasan[$k['sumber']] ?? '') ?> — saat ini <?= (int) $g['orang'] ?> orang, total <?= $rp($g['total']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="mt-3 flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
                            <input type="checkbox" name="timpa" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                            Timpa juga isian yang sudah saya ubah sendiri
                        </label>
                        <button type="submit" class="mt-4 rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 active:scale-95">Hitung sekarang</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Panel: impor Excel -->
            <div id="impor" x-cloak x-show="panel === 'impor'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form method="post" action="<?= $base ?>/honor/impor/unggah" enctype="multipart/form-data" class="space-y-3">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <p class="text-sm leading-relaxed text-slate-600">Pilih rekap honor Excel lama (.xlsx). Berkas <b>dibaca dulu dan ditampilkan untuk diperiksa</b> — belum ada yang tersimpan sampai kamu menekan Terapkan.</p>
                    <input type="file" name="berkas" accept=".xlsx" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700">
                    <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Baca berkas</button>
                </form>
            </div>

            <!-- Panel: data surat -->
            <div id="surat" x-cloak x-show="panel === 'surat'" x-transition class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4" <?= $bukaSurat ? 'x-init="panel = \'surat\'"' : '' ?>>
                <form method="post" action="<?= $base ?>/honor/dokumen" class="grid gap-3 sm:grid-cols-2">
                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Judul rekap</label>
                        <input type="text" name="judul" maxlength="200" required value="<?= esc((string) $olds('judul', $dok['judul']), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Tempat</label>
                        <input type="text" name="tempat" maxlength="80" value="<?= esc((string) $olds('tempat', $dok['tempat']), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Tanggal surat</label>
                        <input type="date" name="tanggal" value="<?= esc((string) $olds('tanggal', $tglDok), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Ketua panitia</label>
                        <input type="text" name="ketua_nama" maxlength="150" value="<?= esc((string) $olds('ketua_nama', $dok['ketua_nama']), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Bendahara</label>
                        <input type="text" name="bendahara_nama" maxlength="150" value="<?= esc((string) $olds('bendahara_nama', $dok['bendahara_nama']), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Kepala Sekolah (menyetujui)</label>
                        <input type="text" name="kepsek_nama" maxlength="150" value="<?= esc((string) $olds('kepsek_nama', $dok['kepsek_nama']), 'attr') ?>" <?= $terkunci ? 'disabled' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                    </div>
                    <?php if (! $terkunci): ?>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Simpan data surat</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- ===== Pemeriksaan honor ===== -->
        <?php $periksa = $honorPeriksa ?? []; $nPer = count(array_filter($periksa, static fn (array $t): bool => $t['level'] === 'peringatan')); $nCat = count($periksa) - $nPer; ?>
        <section class="rounded-2xl border bg-white shadow-sm <?= $nPer > 0 ? 'border-amber-300' : 'border-slate-200' ?>" x-data="{ buka: <?= $nPer > 0 ? 'true' : 'false' ?> }">
            <button type="button" @click="buka = !buka" class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left" :aria-expanded="buka">
                <span class="text-sm font-bold text-slate-800">Pemeriksaan honor</span>
                <span class="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                    <?php if ($periksa === []): ?><span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-emerald-700">Tidak ada temuan</span><?php endif; ?>
                    <?php if ($nPer > 0): ?><span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-800"><?= $nPer ?> perlu dicek</span><?php endif; ?>
                    <?php if ($nCat > 0): ?><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-600"><?= $nCat ?> catatan</span><?php endif; ?>
                    <span class="text-slate-400" x-text="buka ? '−' : '+'"></span>
                </span>
            </button>
            <ul x-cloak x-show="buka" class="divide-y divide-slate-100 border-t border-slate-100">
                <?php if ($periksa === []): ?><li class="px-4 py-3 text-sm text-slate-500">Semua pemeriksaan lolos. Diperbarui setiap halaman dimuat.</li><?php endif; ?>
                <?php foreach ($periksa as $t): ?>
                    <li class="flex gap-2.5 px-4 py-2.5 text-sm <?= $t['level'] === 'peringatan' ? 'bg-amber-50/60 text-amber-900' : 'text-slate-600' ?>">
                        <span class="mt-0.5 shrink-0 text-xs font-bold <?= $t['level'] === 'peringatan' ? 'text-amber-600' : 'text-slate-400' ?>"><?= $t['level'] === 'peringatan' ? '⚠' : 'ⓘ' ?></span>
                        <span><?= esc($t['teks']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- ===== Grid isian ===== -->
        <?php if ($baris === []): ?>
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
                <p class="font-semibold text-slate-700">Belum ada penerima.</p>
                <p class="mt-1 text-sm text-slate-500">Tekan <b>+ Tambah penerima</b> di atas untuk memilih guru dan staf yang menerima honor.</p>
            </section>
        <?php else: ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Isian <b>tersimpan otomatis</b> begitu kamu pindah kolom. Angka rupiah dan total dihitung oleh server.</p>
                    <input type="search" x-model="cari" placeholder="Cari nama penerima…" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 sm:w-64" aria-label="Cari nama penerima">
                </div>

                <div class="overflow-x-auto">
                    <div class="px-3 py-3 md:min-w-[var(--minw)] md:px-0 md:py-0" style="--minw: <?= $minW ?>rem">
                        <!-- Judul kolom (laptop) -->
                        <div class="hidden border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 md:grid md:gap-2 md:[grid-template-columns:var(--kolom)]" style="--kolom: <?= $kolom ?>">
                            <div class="md:sticky md:left-0 md:z-10 md:bg-slate-50">Nama &amp; jabatan</div>
                            <?php foreach ($komp as $k): ?>
                                <div class="text-right">
                                    <?= esc($k['nama']) ?>
                                    <span class="block font-normal normal-case tracking-normal text-slate-400"><?= $k['tipe'] === 'tetap' ? 'nominal (Rp)' : 'Rp ' . $rp($k['tarif']) . ' / ' . esc($k['satuan'] ?: 'satuan') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-right">Total</div>
                            <div></div>
                        </div>

                        <?php foreach ($baris as $i => $b): ?>
                            <div data-baris-row data-baris-id="<?= (int) $b['id'] ?>" data-nama="<?= esc(mb_strtolower((string) $b['nama']), 'attr') ?>"
                                 class="group mb-3 rounded-xl border border-slate-200 bg-white p-3 md:mb-0 md:items-center md:gap-2 md:rounded-none md:border-0 md:border-b md:border-slate-100 md:px-3 md:py-2 md:hover:bg-slate-50 md:grid md:[grid-template-columns:var(--kolom)]"
                                 style="--kolom: <?= $kolom ?>">
                                <div class="md:sticky md:left-0 md:z-10 md:bg-white md:pr-2 md:group-hover:bg-slate-50">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex min-w-0 items-start gap-1.5">
                                            <?php if (! $terkunci): ?>
                                                <input type="text" inputmode="numeric" value="<?= $i + 1 ?>" data-no data-baris="<?= (int) $b['id'] ?>" data-awal="<?= $i + 1 ?>" maxlength="3"
                                                       aria-label="Nomor urut <?= esc($b['nama'], 'attr') ?>" title="Ketik nomor lalu Enter untuk memindahkan baris ini"
                                                       @focus="$el.select()" @change="pindah($el)" @keydown.enter.prevent="$el.blur()"
                                                       class="mt-px h-6 w-9 shrink-0 rounded border border-slate-200 bg-white text-center text-xs tabular-nums text-slate-500 outline-none focus:border-brand-500">
                                            <?php else: ?>
                                                <span class="font-normal text-slate-400"><?= $i + 1 ?>.</span>
                                            <?php endif; ?>
                                            <p class="min-w-0 text-sm font-semibold leading-snug text-slate-800"><?= esc($b['nama']) ?></p>
                                        </div>
                                        <div class="flex shrink-0 gap-1.5 md:hidden">
                                            <?php if (! $terkunci): ?>
                                                <button type="button" @click="geser($el, -1)" data-baris="<?= (int) $b['id'] ?>" aria-label="Naikkan <?= esc($b['nama'], 'attr') ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">▲</button>
                                                <button type="button" @click="geser($el, 1)" data-baris="<?= (int) $b['id'] ?>" aria-label="Turunkan <?= esc($b['nama'], 'attr') ?>" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">▼</button>
                                            <?php endif; ?>
                                            <a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>&amp;baris=<?= (int) $b['id'] ?>" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">Slip</a>
                                            <?php if (! $terkunci): ?>
                                                <button type="submit" form="hb-<?= (int) $b['id'] ?>" onclick="return confirm('Hapus <?= esc($b['nama'], 'js') ?> dari daftar honor?')"
                                                        class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <label class="mb-0.5 mt-2 block text-[11px] font-semibold text-slate-400 md:hidden">Jabatan</label>
                                    <input type="text" maxlength="120" value="<?= esc((string) $b['jabatan'], 'attr') ?>" data-jabatan data-baris="<?= (int) $b['id'] ?>" data-awal="<?= esc((string) $b['jabatan'], 'attr') ?>"
                                           <?= $terkunci ? 'disabled' : '' ?> @change="simpanJabatan($el)" placeholder="Jabatan" aria-label="Jabatan <?= esc($b['nama'], 'attr') ?>"
                                           class="w-full rounded-md border border-transparent bg-transparent py-0.5 text-xs text-slate-500 outline-none transition hover:border-slate-200 hover:px-1.5 focus:border-brand-500 focus:bg-white focus:px-1.5 max-md:rounded-lg max-md:border-slate-200 max-md:px-2 max-md:py-1.5 max-md:text-sm max-md:text-slate-600">
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2 md:contents">
                                    <?php foreach ($komp as $k):
                                        $kid  = (int) $k['id'];
                                        $isi  = $b['nilai'][$kid] ?? ['nilai' => 0, 'otomatis' => null];
                                        $tetap = $k['tipe'] === 'tetap';
                                        $tampil = $tetap ? $rp($isi['nilai']) : (string) (int) $isi['nilai'];
                                        $st   = $isi['otomatis'] === null ? '' : ((int) $isi['otomatis'] === (int) $isi['nilai'] ? 'otomatis' : 'diubah');
                                        ?>
                                        <div class="min-w-0">
                                            <label class="mb-0.5 block truncate text-[11px] font-semibold text-slate-400 md:hidden"><?= esc($k['nama']) ?><?= $tetap ? '' : ' (' . esc($k['satuan'] ?: 'satuan') . ')' ?></label>
                                            <input type="text" inputmode="numeric" autocomplete="off" value="<?= esc($tampil, 'attr') ?>"
                                                   data-nilai data-baris="<?= (int) $b['id'] ?>" data-dk="<?= $kid ?>" data-tipe="<?= $tetap ? 'tetap' : 'satuan' ?>" data-awal="<?= esc($tampil, 'attr') ?>"
                                                   <?= $terkunci ? 'disabled' : '' ?> aria-label="<?= esc($k['nama'] . ' — ' . $b['nama'], 'attr') ?>"
                                                   @focus="$el.select()" @change="simpanSel($el)" @keydown.enter.prevent="turun($el)"
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-right text-sm tabular-nums text-slate-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100 disabled:bg-slate-50">
                                            <p class="mt-0.5 flex flex-wrap items-center justify-end gap-1 text-[11px] tabular-nums text-slate-400">
                                                <?php if ($k['kode'] === 'pengawas' && ! $terkunci && (int) ($honorPetunjuk[(int) ($b['guru_id'] ?? 0)] ?? 0) > 0 && (int) ($honorPetunjuk[(int) $b['guru_id']]) !== (int) $isi['nilai']): ?>
                                                    <button type="button" @click="pakai($el)" data-jumlah="<?= (int) $honorPetunjuk[(int) $b['guru_id']] ?>" class="rounded bg-sky-50 px-1 text-[10px] font-semibold text-sky-700 underline" title="Jumlah sesi menurut jadwal pengawas — klik untuk memakainya">jadwal: <?= (int) $honorPetunjuk[(int) $b['guru_id']] ?></button>
                                                <?php endif; ?>
                                                <?php if (! $tetap): ?><span id="rp-<?= (int) $b['id'] ?>-<?= $kid ?>">Rp <?= $rp($b['per'][$kid] ?? 0) ?></span><?php endif; ?>
                                                <span id="st-<?= (int) $b['id'] ?>-<?= $kid ?>" class="rounded px-1 text-[10px] font-semibold <?= $st === 'diubah' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' ?>"><?= $st === '' ? '' : ($st === 'diubah' ? 'diubah' : 'otomatis') ?></span>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 md:mt-0 md:block md:border-0 md:pt-0 md:text-right">
                                    <span class="text-xs font-semibold text-slate-400 md:hidden">Total</span>
                                    <span class="text-sm font-extrabold tabular-nums text-slate-800">Rp <span id="tb-<?= (int) $b['id'] ?>"><?= $rp($b['total']) ?></span></span>
                                </div>

                                <div class="hidden items-center justify-end gap-0.5 md:flex">
                                    <?php if (! $terkunci): ?>
                                        <button type="button" @click="geser($el, -1)" data-baris="<?= (int) $b['id'] ?>" class="rounded-lg p-1 text-xs leading-none text-slate-300 transition hover:bg-slate-100 hover:text-slate-600" title="Naikkan satu baris" aria-label="Naikkan <?= esc($b['nama'], 'attr') ?>">▲</button>
                                        <button type="button" @click="geser($el, 1)" data-baris="<?= (int) $b['id'] ?>" class="rounded-lg p-1 text-xs leading-none text-slate-300 transition hover:bg-slate-100 hover:text-slate-600" title="Turunkan satu baris" aria-label="Turunkan <?= esc($b['nama'], 'attr') ?>">▼</button>
                                    <?php endif; ?>
                                    <a href="<?= $base ?>/honor/cetak/slip-pdf<?= $qtp ?>&amp;baris=<?= (int) $b['id'] ?>" target="_blank" rel="noopener" class="rounded-lg p-1.5 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600" title="Cetak slip <?= esc($b['nama'], 'attr') ?>" aria-label="Cetak slip <?= esc($b['nama'], 'attr') ?>">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    </a>
                                    <?php if (! $terkunci): ?>
                                        <button type="submit" form="hb-<?= (int) $b['id'] ?>" onclick="return confirm('Hapus <?= esc($b['nama'], 'js') ?> dari daftar honor?')"
                                                class="rounded-lg p-1.5 text-slate-300 transition hover:bg-red-50 hover:text-red-600" title="Hapus dari daftar" aria-label="Hapus <?= esc($b['nama'], 'attr') ?> dari daftar">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <form id="hb-<?= (int) $b['id'] ?>" method="post" action="<?= $base ?>/honor/baris/<?= (int) $b['id'] ?>/hapus" class="hidden">
                                    <?= csrf_field() ?><input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <p x-cloak x-show="cari !== '' && tampilCount === 0" class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada nama yang cocok.</p>

                        <!-- JUMLAH -->
                        <div class="mt-1 rounded-xl border-2 border-slate-200 bg-slate-50 p-3 md:mt-0 md:grid md:items-center md:gap-2 md:rounded-none md:border-0 md:border-t-2 md:px-3 md:py-3 md:[grid-template-columns:var(--kolom)]" style="--kolom: <?= $kolom ?>">
                            <div class="text-sm font-extrabold uppercase tracking-wide text-slate-700 md:sticky md:left-0 md:z-10 md:bg-slate-50">Jumlah</div>
                            <div class="mt-2 grid grid-cols-2 gap-2 md:contents">
                                <?php foreach ($komp as $k): $kid = (int) $k['id']; ?>
                                    <div class="min-w-0 md:text-right">
                                        <p class="truncate text-[11px] font-semibold text-slate-400 md:hidden"><?= esc($k['nama']) ?></p>
                                        <p class="text-sm font-bold tabular-nums text-slate-800">Rp <span id="tk-<?= $kid ?>"><?= $rp($honor['total_komponen'][$kid] ?? 0) ?></span></p>
                                        <?php if ($k['tipe'] === 'satuan'): ?>
                                            <p class="text-[11px] tabular-nums text-slate-400"><span id="tj-<?= $kid ?>"><?= $rp($honor['total_jumlah'][$kid] ?? 0) ?></span> <?= esc($k['satuan'] ?: 'satuan') ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2 md:mt-0 md:block md:border-0 md:pt-0 md:text-right">
                                <span class="text-xs font-semibold text-slate-500 md:hidden">Total keseluruhan</span>
                                <span class="text-base font-extrabold tabular-nums text-brand-700">Rp <span id="tot-semua"><?= $rp($honor['total']) ?></span></span>
                            </div>
                            <div class="hidden md:block"></div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <script defer src="<?= base_url('assets/js/admin/honor-grid.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/honor-grid.js') ?>"></script>
<?php endif; ?>
