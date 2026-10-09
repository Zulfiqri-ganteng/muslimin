<?php
/**
 * Pengaturan Honor Ujian — KHUSUS ADMIN.
 *
 * @var list<array<string,mixed>> $komponen   honor_komponen (urut tampil)
 * @var list<array<string,mixed>> $panitia    jabatan + nominal tunjangan panitia bawaan
 * @var array<string,?string>     $ttd        ketua_nama, bendahara_nama
 * @var array<string,string>      $jenisLabel ASTS1 => "ASTS 1" …
 * @var int                       $maksKomp   batas jumlah komponen
 */

use App\Libraries\HonorPengaturan as Aturan;

// Setelah simpan GAGAL, isian pengguna dipulihkan (old) supaya tidak diketik ulang; selain itu tampilkan isi tersimpan.
$ada      = static fn (string $kunci): bool => old($kunci, null, false) !== null;
$adaOldK  = $ada('k');
$adaOldP  = $ada('nominal');
$rp       = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$nilaiOld = static fn (string $kunci, $asal) => old($kunci, null, false) ?? $asal;
$tambah   = $ada('nama') || $ada('tarif');
$sumberLabel = ['manual' => 'Diketik', 'koreksi' => 'Otomatis dari siswa', 'rapot' => 'Otomatis dari wali kelas', 'soal' => 'Otomatis dari pembuat soal'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'honor_pengaturan_v1',
    'helpTitle' => 'Pengaturan Honor Ujian',
    'helpBody'  => '<p>Di sini <b>Admin mengatur tarif honor</b> yang dipakai semua honor ujian (ASTS 1, ASAS, ASTS 2, ASAT). Halaman ini hanya bisa dibuka Admin.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Komponen</b> = jenis honor: Tunjangan Panitia, Pembuatan Soal, Transport, Pengawas, Koreksi, Rapot, dan seterusnya. Yang tidak dipakai cukup dimatikan; komponen baru bisa ditambah.</li>'
        . '<li>Honor yang <u>sudah dibuat</u> menyimpan tarif saat dibuat, jadi mengubah tarif di sini tidak mengubah honor periode lama.</li>'
        . '<li><b>Tunjangan panitia</b> diisi per jabatan sebagai nominal awal; di honor tiap orang tetap bisa diubah.</li>'
        . '<li>Tarif ditulis angka bulat rupiah, boleh pakai titik ribuan (20.000). Tanpa koma.</li>'
        . '<li>Setiap perubahan tercatat di Audit Log.</li></ul>',
]) ?>

<div class="mx-auto max-w-5xl space-y-6">

    <!-- ===== 1. Komponen & tarif ===== -->
    <section id="komponen" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-5 py-3">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Komponen honor &amp; tarif</h2>
            <span class="text-xs text-slate-400"><?= count($komponen) ?> dari maksimal <?= (int) $maksKomp ?> komponen</span>
        </div>

        <form method="post" action="<?= site_url('admin/honor/pengaturan/komponen') ?>">
            <?= csrf_field() ?>

            <!-- Judul kolom (laptop) -->
            <div class="hidden grid-cols-12 gap-3 border-b border-slate-100 px-5 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-400 md:grid">
                <div class="col-span-1">Aktif</div>
                <div class="col-span-3">Komponen</div>
                <div class="col-span-2">Tarif / nominal</div>
                <div class="col-span-2">Satuan</div>
                <div class="col-span-1">Urut</div>
                <div class="col-span-3">Dipakai di ujian</div>
            </div>

            <div class="divide-y divide-slate-100">
                <?php foreach ($komponen as $k): ?>
                    <?php
                    $id     = (int) $k['id'];
                    $p      = "k.$id";
                    $aktif  = $adaOldK ? $ada("$p.aktif") : (int) $k['aktif'] === 1;
                    $nama   = $nilaiOld("$p.nama", $k['nama']);
                    $tarif  = $nilaiOld("$p.tarif", $rp($k['tarif']));
                    $satuan = $nilaiOld("$p.satuan", (string) ($k['satuan'] ?? ''));
                    $urut   = $nilaiOld("$p.urut", (int) $k['urut']);
                    $jenisK = $adaOldK ? (array) (old("$p.jenis", null, false) ?? []) : Aturan::jenisBerlaku($k['berlaku_di']);
                    $tetap  = $k['tipe'] === 'tetap';
                    ?>
                    <div class="grid gap-3 px-4 py-4 transition has-[input.js-aktif:not(:checked)]:bg-slate-50 has-[input.js-aktif:not(:checked)]:opacity-70 md:grid-cols-12 md:items-start md:px-5">
                        <div class="md:col-span-1">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                                <input type="checkbox" name="k[<?= $id ?>][aktif]" value="1" <?= $aktif ? 'checked' : '' ?> class="js-aktif h-5 w-5 rounded border-slate-300">
                                <span class="md:sr-only">Aktif</span>
                            </label>
                        </div>

                        <div class="md:col-span-3">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-400 md:hidden">Nama komponen</label>
                            <input type="text" name="k[<?= $id ?>][nama]" value="<?= esc((string) $nama, 'attr') ?>" maxlength="80" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 outline-none focus:border-brand-500">
                            <input type="text" name="k[<?= $id ?>][judul_cetak]" value="<?= esc((string) $nilaiOld("$p.judul_cetak", (string) ($k['judul_cetak'] ?? '')), 'attr') ?>" maxlength="80"
                                   placeholder="Judul kolom di cetakan: <?= esc(mb_strtoupper((string) $k['nama']), 'attr') ?>" aria-label="Judul di cetakan <?= esc($k['nama'], 'attr') ?>"
                                   class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 outline-none placeholder:text-slate-300 focus:border-brand-500">
                            <p class="mt-1.5 flex flex-wrap gap-1.5 text-[10px] font-semibold">
                                <span class="rounded-full <?= (int) $k['bawaan'] === 1 ? 'bg-slate-100 text-slate-500' : 'bg-violet-50 text-violet-700' ?> px-2 py-0.5"><?= (int) $k['bawaan'] === 1 ? 'Bawaan' : 'Tambahan' ?></span>
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-sky-700"><?= $tetap ? 'Nominal per orang' : 'Jumlah × tarif' ?></span>
                                <span class="rounded-full <?= $k['sumber'] === 'manual' ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700' ?> px-2 py-0.5"><?= esc($sumberLabel[$k['sumber']] ?? $k['sumber']) ?></span>
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-400 md:hidden"><?= $tetap ? 'Nominal bawaan per orang' : 'Tarif per satuan' ?></label>
                            <div class="flex items-center rounded-lg border border-slate-300 bg-white focus-within:border-brand-500">
                                <span class="pl-3 text-xs font-semibold text-slate-400">Rp</span>
                                <input type="text" inputmode="numeric" name="k[<?= $id ?>][tarif]" value="<?= esc((string) $tarif, 'attr') ?>" required
                                       class="w-full min-w-0 rounded-lg bg-transparent px-2 py-2 text-right text-sm font-semibold tabular-nums text-slate-800 outline-none" aria-label="Tarif <?= esc($k['nama'], 'attr') ?>">
                            </div>
                            <?php if ($k['kode'] === 'tunj_panitia'): ?>
                                <p class="mt-1 text-[10px] leading-snug text-slate-400">Nominal awal diatur per jabatan di bagian bawah.</p>
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-400 md:hidden">Satuan</label>
                            <?php if ($tetap): ?>
                                <p class="py-2 text-sm text-slate-400">per orang</p>
                                <input type="hidden" name="k[<?= $id ?>][satuan]" value="">
                            <?php else: ?>
                                <input type="text" name="k[<?= $id ?>][satuan]" value="<?= esc((string) $satuan, 'attr') ?>" maxlength="30" placeholder="mis. lembar"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-400 md:hidden">Urutan tampil</label>
                            <input type="number" min="1" max="9999" name="k[<?= $id ?>][urut]" value="<?= (int) $urut ?>" required
                                   class="w-full rounded-lg border border-slate-300 px-2 py-2 text-center text-sm tabular-nums outline-none focus:border-brand-500" aria-label="Urutan <?= esc($k['nama'], 'attr') ?>">
                        </div>

                        <div class="md:col-span-3">
                            <label class="mb-1 block text-[11px] font-semibold text-slate-400 md:hidden">Dipakai di ujian</label>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($jenisLabel as $kodeJenis => $lblJenis): ?>
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                        <input type="checkbox" name="k[<?= $id ?>][jenis][]" value="<?= $kodeJenis ?>" <?= in_array($kodeJenis, $jenisK, true) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                                        <?= esc($lblJenis) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ((int) $k['bawaan'] === 0): ?>
                                <button type="submit" form="hapus-<?= $id ?>" formnovalidate
                                        onclick="return confirm('Hapus komponen &quot;<?= esc($k['nama'], 'js') ?>&quot;? Hanya bisa bila belum dipakai di honor mana pun.')"
                                        class="mt-2 text-xs font-semibold text-red-600 underline underline-offset-2 hover:text-red-700">Hapus komponen ini</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Mengubah tarif <b>tidak</b> mengubah honor yang sudah dibuat.</p>
                <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan komponen &amp; tarif</button>
            </div>
        </form>

        <!-- form hapus (di luar form utama; HTML tidak boleh bersarang) -->
        <?php foreach ($komponen as $k): ?>
            <?php if ((int) $k['bawaan'] === 0): ?>
                <form id="hapus-<?= (int) $k['id'] ?>" method="post" action="<?= site_url('admin/honor/pengaturan/komponen/' . (int) $k['id'] . '/hapus') ?>"><?= csrf_field() ?></form>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Tambah komponen -->
        <div class="border-t border-slate-100" x-data="{ buka: <?= $tambah ? 'true' : 'false' ?> }">
            <button type="button" @click="buka = !buka" class="flex w-full items-center justify-between px-5 py-3 text-left text-sm font-semibold text-brand-700 hover:bg-slate-50">
                <span>+ Tambah komponen baru (mis. Lembur, Konsumsi, Honor Pengetikan)</span>
                <span class="text-slate-400" x-text="buka ? '−' : '+'"></span>
            </button>
            <form x-show="buka" x-cloak method="post" action="<?= site_url('admin/honor/pengaturan/komponen/tambah') ?>" class="grid gap-3 border-t border-slate-100 px-5 py-4 md:grid-cols-12 md:items-end">
                <?= csrf_field() ?>
                <div class="md:col-span-4">
                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Nama komponen</label>
                    <input type="text" name="nama" maxlength="80" required value="<?= esc((string) old('nama', null, false), 'attr') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                </div>
                <div class="md:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Cara hitung</label>
                    <?php $tipeOld = (string) old('tipe', null, false); ?>
                    <select name="tipe" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                        <option value="satuan" <?= $tipeOld !== 'tetap' ? 'selected' : '' ?>>Jumlah × tarif</option>
                        <option value="tetap" <?= $tipeOld === 'tetap' ? 'selected' : '' ?>>Nominal tetap per orang</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Tarif / nominal (Rp)</label>
                    <input type="text" inputmode="numeric" name="tarif" value="<?= esc((string) old('tarif', '0', false), 'attr') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm tabular-nums outline-none focus:border-brand-500">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Satuan</label>
                    <input type="text" name="satuan" maxlength="30" placeholder="mis. kali" value="<?= esc((string) old('satuan', null, false), 'attr') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                </div>
                <div class="md:col-span-1">
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 active:scale-95">Tambah</button>
                </div>
            </form>
        </div>
    </section>

    <!-- ===== 2. Tunjangan panitia per jabatan ===== -->
    <section id="panitia" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Tunjangan panitia per jabatan</h2>
        </div>
        <form method="post" action="<?= site_url('admin/honor/pengaturan/panitia') ?>">
            <?= csrf_field() ?>
            <p class="px-5 pt-4 text-sm leading-relaxed text-slate-600">
                Nominal awal Tunjangan Panitia untuk pemegang jabatan ini. Kosongkan bila jabatan itu <b>tidak</b> mendapat tunjangan panitia.
                Di honor tiap orang, angka ini tetap bisa diubah.
            </p>
            <?php if ($panitia === []): ?>
                <p class="px-5 py-6 text-sm text-slate-400">Belum ada jabatan. Tambahkan di menu Guru → Jabatan.</p>
            <?php else: ?>
                <div class="grid gap-x-6 gap-y-3 px-5 py-4 sm:grid-cols-2">
                    <?php foreach ($panitia as $j): ?>
                        <?php
                        $jid = (int) $j['id'];
                        $isi = $adaOldP ? (string) old("nominal.$jid", '', false) : ((int) $j['nominal'] > 0 ? $rp($j['nominal']) : '');
                        ?>
                        <label class="flex items-center gap-3">
                            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700"><?= esc($j['nama']) ?></span>
                            <span class="flex w-40 shrink-0 items-center rounded-lg border border-slate-300 bg-white focus-within:border-brand-500">
                                <span class="pl-3 text-xs font-semibold text-slate-400">Rp</span>
                                <input type="text" inputmode="numeric" name="nominal[<?= $jid ?>]" value="<?= esc($isi, 'attr') ?>" placeholder="—"
                                       class="w-full min-w-0 rounded-lg bg-transparent px-2 py-2 text-right text-sm tabular-nums outline-none" aria-label="Tunjangan panitia <?= esc($j['nama'], 'attr') ?>">
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4">
                <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan tunjangan panitia</button>
            </div>
        </form>
    </section>

    <!-- ===== 3. Tanda tangan ===== -->
    <section id="tanda-tangan" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Tanda tangan rekap honor</h2>
        </div>
        <form method="post" action="<?= site_url('admin/honor/pengaturan/tanda-tangan') ?>">
            <?= csrf_field() ?>
            <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Ketua panitia</label>
                    <input type="text" name="ketua_nama" maxlength="150" value="<?= esc((string) $nilaiOld('ketua_nama', (string) ($ttd['ketua_nama'] ?? '')), 'attr') ?>" placeholder="Nama beserta gelar"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Bendahara</label>
                    <input type="text" name="bendahara_nama" maxlength="150" value="<?= esc((string) $nilaiOld('bendahara_nama', (string) ($ttd['bendahara_nama'] ?? '')), 'attr') ?>" placeholder="Nama beserta gelar"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500">
                </div>
                <p class="text-xs leading-relaxed text-slate-500 sm:col-span-2">
                    Kepala Sekolah (&quot;Menyetujui&quot;) diambil dari <a href="<?= site_url('admin/settings') ?>" class="font-semibold text-brand-700 underline">Pengaturan Sekolah</a>.
                    Nama ini dipakai sebagai isian awal setiap honor baru dan masih bisa diubah per honor.
                </p>
            </div>
            <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4">
                <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan nama tanda tangan</button>
            </div>
        </form>
    </section>
</div>

<?= $this->endSection() ?>
