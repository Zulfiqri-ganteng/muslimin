<?php
/**
 * Periksa satu isian biodata: Master Siswa (lama) vs isian siswa (baru).
 *
 * - Baris "baru" (hijau) & "berubah" (kuning) disorot; nilai lama yang diganti dicoret.
 * - Saklar "Tampilkan yang berubah saja" menyembunyikan baris yang sama persis.
 * - Bar keputusan menempel di bawah layar: Setujui / Kembalikan / Lewati / Hapus.
 *
 * @var array        $row          baris biodata_isian
 * @var array|null   $siswa        baris siswa + nama_kelas (null bila siswa terhapus permanen)
 * @var string       $judulLama    judul kolom pembanding (BiodataVerifikasi::bandingkan)
 * @var array        $bagian       [judul => [[kunci, label, lama, baru, jenis(sama|baru|ubah), tetap]]]
 * @var array        $hitung       ['baru' => n, 'ubah' => n]
 * @var list<string> $peringatan
 * @var array|null   $admin        penyetuju (full_name, username)
 * @var int          $kelasId      konteks saringan kelas asal
 * @var int          $sisaMenunggu jumlah isian menunggu (seluruh sekolah)
 * @var int          $berikutnyaId isian menunggu berikutnya (0 bila tidak ada)
 */
$status  = $row['status'];
$fmtTgl  = static fn (?string $s): string => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
$lencana = [
    'menunggu'  => ['Menunggu verifikasi', 'bg-blue-50 text-blue-700 border-blue-200'],
    'disetujui' => ['Disetujui', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'perbaikan' => ['Perlu perbaikan', 'bg-amber-50 text-amber-800 border-amber-200'],
][$status] ?? [$status, 'bg-slate-100 text-slate-600 border-slate-200'];
$kembali  = site_url('admin/biodata') . '?' . http_build_query(array_filter(['tab' => $status, 'kelas_id' => $kelasId ?: '']));
$aksi     = site_url('admin/biodata/' . (int) $row['id']);
$data     = \App\Models\BiodataIsianModel::decode($row);
$nama     = $siswa['nama'] ?? ($data['nama'] ?? '—');
$jmlBeda  = (int) $hitung['baru'] + (int) $hitung['ubah'];
$bisaSetujui = $status === 'menunggu' && $siswa !== null && empty($siswa['deleted_at']);
$urlLewati   = $berikutnyaId > 0 ? site_url('admin/biodata/' . $berikutnyaId) . ($kelasId > 0 ? '?kelas_id=' . $kelasId : '') : '';
$ikon = static fn (string $d, string $kelas = 'w-4 h-4'): string => '<svg class="' . $kelas . '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="{ formKembali: false, hanyaBeda: false }" class="space-y-5 max-w-5xl pb-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="<?= esc($kembali, 'attr') ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke daftar</a>
        <?php if ($status === 'menunggu' && $sisaMenunggu > 0): ?>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                <span class="h-2 w-2 rounded-full bg-blue-500"></span>Antrean menunggu: <?= number_format($sisaMenunggu, 0, ',', '.') ?> isian
            </span>
        <?php endif; ?>
    </div>

    <!-- ================= Kepala ================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Isian #<?= str_pad((string) (int) $row['id'], 5, '0', STR_PAD_LEFT) ?></p>
                    <h1 class="mt-0.5 text-xl sm:text-2xl font-extrabold text-slate-900 break-words"><?= esc($nama) ?></h1>
                    <p class="text-sm text-slate-500">Kelas <b class="text-slate-700"><?= esc($siswa['nama_kelas'] ?? '—') ?></b> · NISN <?= esc($data['nisn'] ?? ($row['nisn'] ?? '—')) ?></p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-bold <?= $lencana[1] ?>"><?= esc($lencana[0]) ?></span>
            </div>

            <!-- Ringkasan perbedaan -->
            <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                    <p class="text-2xl font-extrabold text-emerald-700 tabular-nums"><?= (int) $hitung['baru'] ?></p>
                    <p class="text-xs font-semibold text-emerald-800">kolom baru terisi</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                    <p class="text-2xl font-extrabold text-amber-700 tabular-nums"><?= (int) $hitung['ubah'] ?></p>
                    <p class="text-xs font-semibold text-amber-800">kolom berubah</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <p class="text-2xl font-extrabold text-slate-700 tabular-nums"><?= (int) $row['kirim_ke'] ?></p>
                    <p class="text-xs font-semibold text-slate-600">kali dikirim siswa</p>
                </div>
            </div>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 border-t border-slate-100 bg-slate-50/60 px-5 sm:px-6 py-3 text-sm">
            <div><dt class="text-xs text-slate-400">Pertama dikirim</dt><dd class="font-semibold text-slate-700 tabular-nums"><?= $fmtTgl($row['created_at']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Terakhir diperbarui</dt><dd class="font-semibold text-slate-700 tabular-nums"><?= $fmtTgl($row['updated_at']) ?></dd></div>
            <div class="col-span-2 sm:col-span-1"><dt class="text-xs text-slate-400">Alamat IP</dt><dd class="font-semibold text-slate-700 break-all"><?= esc($row['ip_address'] ?? '—') ?></dd></div>
        </dl>
    </div>

    <!-- ================= Pemberitahuan ================= -->
    <?php if ($status === 'disetujui'): ?>
        <div class="flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            <span class="mt-0.5 text-emerald-600"><?= $ikon('M5 13l4 4L19 7', 'w-5 h-5') ?></span>
            <p>Disetujui <b><?= $fmtTgl($row['diverifikasi_at']) ?></b><?= $admin ? ' oleh <b>' . esc($admin['full_name'] ?: $admin['username']) . '</b>' : '' ?> — data sudah masuk Master Siswa.</p>
        </div>
    <?php endif; ?>

    <?php if ($siswa === null): ?>
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Siswa ini sudah dihapus dari Master Siswa, jadi isian tidak bisa disetujui. Hapus isian ini bila tidak diperlukan.</div>
    <?php elseif (! empty($siswa['deleted_at'])): ?>
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Siswa ini berada di tempat sampah Master Siswa (terhapus). Pulihkan dulu bila isiannya ingin disetujui.</div>
    <?php endif; ?>

    <?php if (! empty($row['catatan_admin'])): ?>
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900"><b>Catatan yang dikirim ke siswa:</b> <?= esc($row['catatan_admin']) ?></div>
    <?php endif; ?>

    <?php if ($peringatan !== []): ?>
        <div class="flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-300 px-4 py-3">
            <span class="mt-0.5 text-amber-600"><?= $ikon('M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'w-5 h-5') ?></span>
            <div>
                <p class="text-sm font-bold text-amber-900">Perhatikan sebelum menyetujui:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5 text-sm text-amber-900">
                    <?php foreach ($peringatan as $p): ?><li><?= esc($p) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================= Pembanding ================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-slate-800">Bandingkan Data</h2>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-emerald-100 border border-emerald-300"></span>baru terisi</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-amber-100 border border-amber-300"></span>berubah (nilai lama dicoret)</span>
                </p>
            </div>
            <?php if ($jmlBeda > 0): ?>
                <label class="inline-flex items-center gap-2.5 cursor-pointer select-none rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                    <input type="checkbox" x-model="hanyaBeda" class="sr-only peer">
                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-slate-300 transition peer-checked:bg-brand-600" aria-hidden="true">
                        <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition" :class="hanyaBeda ? 'translate-x-4' : 'translate-x-0.5'"></span>
                    </span>
                    <span class="text-sm font-semibold text-slate-700">Tampilkan yang berubah saja</span>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($jmlBeda === 0): ?>
            <p class="px-5 py-3 text-sm text-slate-600 bg-slate-50 border-b border-slate-100">Tidak ada perbedaan — isian siswa sama persis dengan data di Master Siswa.</p>
        <?php endif; ?>

        <!-- Judul kolom (layar lebar) -->
        <div class="hidden md:grid grid-cols-[13rem_minmax(0,1fr)_minmax(0,1fr)] gap-4 px-5 py-2.5 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 border-b border-slate-100">
            <span>Kolom</span><span><?= esc($judulLama) ?></span><span>Isian siswa</span>
        </div>

        <?php foreach ($bagian as $judul => $baris):
            $adaBeda = array_filter($baris, static fn ($b) => $b['jenis'] !== 'sama') !== []; ?>
            <section <?= $adaBeda ? '' : 'x-show="!hanyaBeda"' ?>>
                <h3 class="bg-slate-100/80 px-5 py-2 text-xs font-bold uppercase tracking-wide text-slate-600"><?= esc($judul) ?></h3>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($baris as $b):
                        $beda  = $b['jenis'] !== 'sama';
                        $latar = $b['jenis'] === 'baru' ? 'bg-emerald-50/60' : ($b['jenis'] === 'ubah' ? 'bg-amber-50/80' : ''); ?>
                        <li <?= $beda ? '' : 'x-show="!hanyaBeda"' ?> class="grid grid-cols-1 md:grid-cols-[13rem_minmax(0,1fr)_minmax(0,1fr)] gap-x-4 gap-y-1 px-5 py-2.5 text-sm <?= $latar ?>">
                            <span class="text-xs md:text-sm font-semibold md:font-normal text-slate-500"><?= esc($b['label']) ?></span>
                            <!-- Data lama -->
                            <span class="break-words">
                                <span class="md:hidden text-[11px] font-semibold uppercase tracking-wide text-slate-400 inline-block w-16">Lama</span><span class="<?= $b['lama'] === '' ? 'text-slate-300' : ($b['jenis'] === 'ubah' ? 'text-red-700/80 line-through decoration-red-400' : 'text-slate-600') ?>"><?= $b['lama'] === '' ? '—' : esc($b['lama']) ?></span>
                            </span>
                            <!-- Isian siswa -->
                            <span class="break-words">
                                <span class="md:hidden text-[11px] font-semibold uppercase tracking-wide text-slate-400 inline-block w-16">Isian</span><?php if ($b['tetap']): ?><span class="text-slate-400">kosong</span> <span class="text-xs text-slate-400">— data lama dipertahankan</span><?php else: ?><span class="font-semibold <?= $b['baru'] === '' ? 'text-slate-300' : 'text-slate-900' ?>"><?= $b['baru'] === '' ? '—' : esc($b['baru']) ?></span><?php if ($b['jenis'] === 'baru'): ?> <span class="ml-1 rounded border border-emerald-300 bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">baru</span><?php endif; ?><?php if ($b['jenis'] === 'ubah'): ?> <span class="ml-1 rounded border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">berubah</span><?php endif; ?><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- ================= Keputusan — menempel di bawah layar ================= -->
    <?php if ($siswa !== null): ?>
        <div class="sticky bottom-3 z-20 bg-white rounded-2xl border border-slate-200 shadow-xl p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($bisaSetujui): ?>
                    <form method="post" action="<?= $aksi ?>/setujui" class="flex-1 sm:flex-none">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                        <button data-confirm="Setujui biodata <?= esc($nama, 'attr') ?>? <?= $jmlBeda > 0 ? $jmlBeda . ' kolom akan diperbarui di Master Siswa.' : 'Data di Master Siswa tidak berubah.' ?>"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition">
                            <?= $ikon('M5 13l4 4L19 7') ?>Setujui<span class="hidden sm:inline -ml-1">&nbsp;&amp; masukkan ke Master Siswa</span>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($status !== 'perbaikan'): ?>
                    <button type="button" @click="formKembali = !formKembali" :aria-expanded="formKembali"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 px-4 sm:px-5 py-2.5 text-sm font-bold text-amber-800 transition">
                        ↩ Kembalikan<span class="hidden sm:inline -ml-1">&nbsp;untuk diperbaiki</span>
                    </button>
                <?php endif; ?>
                <?php if ($status === 'menunggu' && $urlLewati !== ''): ?>
                    <a href="<?= esc($urlLewati, 'attr') ?>" title="Buka isian menunggu berikutnya tanpa memutuskan yang ini"
                       class="inline-flex items-center justify-center gap-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-600 transition">
                        Lewati <?= $ikon('M13 7l5 5m0 0l-5 5m5-5H6') ?>
                    </a>
                <?php endif; ?>
                <form method="post" action="<?= $aksi ?>/hapus" class="ml-auto">
                    <?= csrf_field() ?>
                    <button data-confirm="Hapus isian <?= esc($nama, 'attr') ?>? Siswa harus mengisi ulang dari awal. Data yang SUDAH masuk Master Siswa tidak ikut terhapus."
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 sm:px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition">
                        <?= $ikon('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16') ?><span class="hidden sm:inline">Hapus isian</span><span class="sm:hidden">Hapus</span>
                    </button>
                </form>
            </div>
            <?php if ($bisaSetujui): ?>
                <p class="mt-2 text-xs text-slate-500"><?= $berikutnyaId > 0 ? 'Setelah disetujui / dikembalikan, isian menunggu berikutnya langsung terbuka.' : 'Ini isian menunggu terakhir' . ($kelasId > 0 ? ' di kelas ini' : '') . '.' ?></p>
            <?php endif; ?>

            <form x-cloak x-show="formKembali" x-transition method="post" action="<?= $aksi ?>/kembalikan" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                <label class="block text-sm font-semibold text-slate-700" for="catatan">Apa yang harus diperbaiki siswa? <span class="text-red-500">*</span></label>
                <textarea id="catatan" name="catatan" rows="2" maxlength="255" required
                          class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
                          placeholder="Contoh: NISN salah, cek lagi di kartu pelajar. Tanggal lahir tidak sesuai KK."></textarea>
                <p class="mt-1 text-xs text-slate-500">Catatan ini tampil ke siswa saat membuka isiannya lagi (dengan NISN / tanggal lahir). Kabari siswa lewat wali kelas.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button class="rounded-lg bg-amber-600 hover:bg-amber-700 px-5 py-2 text-sm font-bold text-white transition">Kirim ke siswa untuk diperbaiki</button>
                    <button type="button" @click="formKembali = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
