<?php
/**
 * Pengaturan Honor Ujian — data gaji; hak menu diatur Admin (bawaan hanya Admin).
 * Rombak tampilan F7: kartu kit, isian kit (Rp sebagai awalan di dalam kotak), lencana sifat komponen, tombol ber-ikon;
 * hapus komponen lewat dialog konfirmasi kit (dulu onclick confirm), "Tambah komponen baru" = <details> (tanpa Alpine) → siap CSP.
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
$rpAwal  = '<span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Rp</span>';
$lblHp   = 'mb-1 block text-xs font-medium text-gray-500 md:hidden dark:text-gray-400'; // label kolom yang hanya tampil di HP
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'honor_pengaturan_v1',
    'helpTitle' => 'Pengaturan Honor Ujian',
    'helpBody'  => '<p>Di sini <b>Admin mengatur tarif honor</b> yang dipakai semua honor ujian (ASTS 1, ASAS, ASTS 2, ASAT). Siapa yang boleh membuka halaman ini diatur Admin di Sistem → Hak Akses (bawaan: hanya Admin).</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Komponen</b> = jenis honor: Tunjangan Panitia, Pembuatan Soal, Transport, Pengawas, Koreksi, Rapot, dan seterusnya. Yang tidak dipakai cukup dimatikan; komponen baru bisa ditambah.</li>'
        . '<li>Honor yang <u>sudah dibuat</u> menyimpan tarif saat dibuat, jadi mengubah tarif di sini tidak mengubah honor periode lama.</li>'
        . '<li><b>Tunjangan panitia</b> diisi per jabatan sebagai nominal awal; di honor tiap orang tetap bisa diubah.</li>'
        . '<li><b>Urutan jabatan</b> di rekap (Kepala Sekolah, Kepala TU, para Waka, …) juga diatur di bagian itu; kosongkan untuk memakai urutan bawaan.</li>'
        . '<li>Tarif ditulis angka bulat rupiah, boleh pakai titik ribuan (20.000). Tanpa koma.</li>'
        . '<li>Setiap perubahan tercatat di Audit Log.</li></ul>',
]) ?>

<div class="mx-auto max-w-5xl space-y-4">

    <!-- ===== 1. Komponen & tarif ===== -->
    <section id="komponen" class="kartu scroll-mt-20 overflow-hidden" aria-labelledby="judul-komponen">
        <div class="kartu-kepala">
            <h2 id="judul-komponen" class="kartu-judul">Komponen honor &amp; tarif</h2>
            <span class="lencana lencana-abu"><?= count($komponen) ?> dari maksimal <?= (int) $maksKomp ?> komponen</span>
        </div>

        <form method="post" action="<?= site_url('admin/honor/pengaturan/komponen') ?>">
            <?= csrf_field() ?>

            <!-- Judul kolom (laptop) -->
            <div class="hidden grid-cols-12 gap-3 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-semibold text-gray-500 uppercase md:grid dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <div class="col-span-1">Aktif</div>
                <div class="col-span-3">Komponen</div>
                <div class="col-span-2">Tarif / nominal</div>
                <div class="col-span-2">Satuan</div>
                <div class="col-span-1">Urut</div>
                <div class="col-span-3">Dipakai di ujian</div>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
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
                    <div class="grid gap-3 px-4 py-4 transition-colors has-[input.js-aktif:not(:checked)]:bg-gray-50 md:grid-cols-12 md:items-start md:px-5 dark:has-[input.js-aktif:not(:checked)]:bg-gray-800/60">
                        <div class="md:col-span-1 md:pt-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                <input type="checkbox" name="k[<?= $id ?>][aktif]" value="1"<?= $aktif ? ' checked' : '' ?> class="cek js-aktif h-5 w-5" aria-label="Aktifkan <?= esc($k['nama'], 'attr') ?>">
                                <span class="md:sr-only">Aktif</span>
                            </label>
                        </div>

                        <div class="md:col-span-3">
                            <label class="<?= $lblHp ?>" for="k-nama-<?= $id ?>">Nama komponen</label>
                            <input type="text" id="k-nama-<?= $id ?>" name="k[<?= $id ?>][nama]" value="<?= esc((string) $nama, 'attr') ?>" maxlength="80" required class="inp font-semibold">
                            <input type="text" name="k[<?= $id ?>][judul_cetak]" value="<?= esc((string) $nilaiOld("$p.judul_cetak", (string) ($k['judul_cetak'] ?? '')), 'attr') ?>" maxlength="80"
                                   placeholder="Judul kolom di cetakan: <?= esc(mb_strtoupper((string) $k['nama']), 'attr') ?>" aria-label="Judul di cetakan <?= esc($k['nama'], 'attr') ?>"
                                   class="inp mt-1.5 py-1.5 text-xs">
                            <p class="mt-1.5 flex flex-wrap gap-1.5">
                                <span class="lencana <?= (int) $k['bawaan'] === 1 ? 'lencana-abu' : 'lencana-pink' ?>"><?= (int) $k['bawaan'] === 1 ? 'Bawaan' : 'Tambahan' ?></span>
                                <span class="lencana lencana-merek"><?= $tetap ? 'Nominal per orang' : 'Jumlah × tarif' ?></span>
                                <span class="lencana <?= $k['sumber'] === 'manual' ? 'lencana-abu' : 'lencana-hijau' ?>"><?= esc($sumberLabel[$k['sumber']] ?? $k['sumber']) ?></span>
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="<?= $lblHp ?>" for="k-tarif-<?= $id ?>"><?= $tetap ? 'Nominal bawaan per orang' : 'Tarif per satuan' ?></label>
                            <div class="relative">
                                <?= $rpAwal ?>
                                <input type="text" inputmode="numeric" id="k-tarif-<?= $id ?>" name="k[<?= $id ?>][tarif]" value="<?= esc((string) $tarif, 'attr') ?>" required
                                       class="inp ps-9 text-right font-semibold tabular-nums" aria-label="Tarif <?= esc($k['nama'], 'attr') ?>">
                            </div>
                            <?php if ($k['kode'] === 'tunj_panitia'): ?>
                                <p class="mt-1 text-xs leading-snug text-gray-500 dark:text-gray-400">Nominal awal diatur per jabatan di bagian bawah.</p>
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-2">
                            <label class="<?= $lblHp ?>"<?= $tetap ? '' : ' for="k-satuan-' . $id . '"' ?>>Satuan</label>
                            <?php if ($tetap): ?>
                                <p class="py-2.5 text-sm text-gray-500 dark:text-gray-400">per orang</p>
                                <input type="hidden" name="k[<?= $id ?>][satuan]" value="">
                            <?php else: ?>
                                <input type="text" id="k-satuan-<?= $id ?>" name="k[<?= $id ?>][satuan]" value="<?= esc((string) $satuan, 'attr') ?>" maxlength="30" placeholder="mis. lembar" class="inp">
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-1">
                            <label class="<?= $lblHp ?>" for="k-urut-<?= $id ?>">Urutan tampil</label>
                            <input type="number" id="k-urut-<?= $id ?>" min="1" max="9999" name="k[<?= $id ?>][urut]" value="<?= (int) $urut ?>" required class="inp px-2 text-center tabular-nums" aria-label="Urutan <?= esc($k['nama'], 'attr') ?>">
                        </div>

                        <div class="md:col-span-3">
                            <p class="<?= $lblHp ?>">Dipakai di ujian</p>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach ($jenisLabel as $kodeJenis => $lblJenis): ?>
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700 dark:border-gray-600 dark:text-gray-300 dark:has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/30 dark:has-[:checked]:text-brand-300">
                                        <input type="checkbox" name="k[<?= $id ?>][jenis][]" value="<?= $kodeJenis ?>"<?= in_array($kodeJenis, $jenisK, true) ? ' checked' : '' ?> class="cek">
                                        <?= esc($lblJenis) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ((int) $k['bawaan'] === 0): ?>
                                <button type="submit" form="hapus-<?= $id ?>" formnovalidate class="btn btn-teks btn-sm mt-2 px-0 text-red-700 dark:text-red-500"
                                        data-confirm="Hapus komponen &quot;<?= esc($k['nama'], 'attr') ?>&quot;? Hanya bisa bila belum dipakai di honor mana pun."
                                        data-confirm-judul="Hapus komponen?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus komponen ini</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="kartu-kaki flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">Mengubah tarif <b>tidak</b> mengubah honor yang sudah dibuat.</p>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan komponen &amp; tarif</button>
            </div>
        </form>

        <!-- form hapus (di luar form utama; HTML tidak boleh bersarang) -->
        <?php foreach ($komponen as $k): ?>
            <?php if ((int) $k['bawaan'] === 0): ?>
                <form id="hapus-<?= (int) $k['id'] ?>" method="post" action="<?= site_url('admin/honor/pengaturan/komponen/' . (int) $k['id'] . '/hapus') ?>"><?= csrf_field() ?></form>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Tambah komponen (terbuka sendiri bila simpan sebelumnya gagal) -->
        <details class="group border-t border-gray-200 dark:border-gray-700"<?= $tambah ? ' open' : '' ?>>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-medium text-brand-700 hover:bg-gray-50 dark:text-brand-400 dark:hover:bg-gray-700">
                <span class="inline-flex items-center gap-2"><?= ikon('plus', 'h-4 w-4') ?>Tambah komponen baru (mis. Lembur, Konsumsi, Honor Pengetikan)</span>
                <?= ikon('chevron-down', 'h-4 w-4 shrink-0 text-gray-500 transition-transform group-open:rotate-180') ?>
            </summary>
            <form method="post" action="<?= site_url('admin/honor/pengaturan/komponen/tambah') ?>" class="grid gap-3 border-t border-gray-200 px-5 py-4 md:grid-cols-12 md:items-end dark:border-gray-700">
                <?= csrf_field() ?>
                <div class="md:col-span-4">
                    <label class="lbl" for="kb-nama">Nama komponen</label>
                    <input type="text" id="kb-nama" name="nama" maxlength="80" required value="<?= esc((string) old('nama', null, false), 'attr') ?>" class="inp">
                </div>
                <div class="md:col-span-3">
                    <label class="lbl" for="kb-tipe">Cara hitung</label>
                    <?php $tipeOld = (string) old('tipe', null, false); ?>
                    <select id="kb-tipe" name="tipe" class="inp">
                        <option value="satuan"<?= $tipeOld !== 'tetap' ? ' selected' : '' ?>>Jumlah × tarif</option>
                        <option value="tetap"<?= $tipeOld === 'tetap' ? ' selected' : '' ?>>Nominal tetap per orang</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="lbl" for="kb-tarif">Tarif / nominal</label>
                    <div class="relative">
                        <?= $rpAwal ?>
                        <input type="text" inputmode="numeric" id="kb-tarif" name="tarif" value="<?= esc((string) old('tarif', '0', false), 'attr') ?>" class="inp ps-9 text-right tabular-nums">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="lbl" for="kb-satuan">Satuan</label>
                    <input type="text" id="kb-satuan" name="satuan" maxlength="30" placeholder="mis. kali" value="<?= esc((string) old('satuan', null, false), 'attr') ?>" class="inp">
                </div>
                <div class="md:col-span-1">
                    <button type="submit" class="btn btn-sukses w-full px-3"><?= ikon('plus', 'h-4 w-4') ?>Tambah</button>
                </div>
            </form>
        </details>
    </section>

    <!-- ===== 2. Tunjangan panitia per jabatan ===== -->
    <section id="panitia" class="kartu scroll-mt-20 overflow-hidden" aria-labelledby="judul-panitia">
        <div class="kartu-kepala">
            <h2 id="judul-panitia" class="kartu-judul">Tunjangan panitia &amp; urutan per jabatan</h2>
        </div>
        <form method="post" action="<?= site_url('admin/honor/pengaturan/panitia') ?>">
            <?= csrf_field() ?>
            <div class="kartu-isi space-y-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                <p><b>Tunjangan:</b> nominal awal Tunjangan Panitia untuk pemegang jabatan ini. Kosongkan bila jabatan itu <b>tidak</b> mendapat tunjangan panitia. Di honor tiap orang, angka ini tetap bisa diubah.</p>
                <p><b>Urutan di rekap:</b> angka kecil tampil di atas (1&nbsp;=&nbsp;paling atas). Angka abu-abu di dalam kotak adalah urutan bawaan; <b>kosongkan</b> kotaknya bila ingin memakai urutan bawaan.
                    Orang yang punya beberapa jabatan ditempatkan menurut jabatannya yang paling atas. Urutan ini dipakai saat <b>menambah penerima</b> dan pada tombol <b>Atur urutan</b> di tab Honor.</p>
            </div>
            <?php if ($panitia === []): ?>
                <?= ui_kosong(['ikon' => 'briefcase', 'judul' => 'Belum ada jabatan', 'teks' => 'Tambahkan di menu Guru → Jabatan.']) ?>
            <?php else: ?>
                <div class="px-4 pb-4 md:px-6">
                    <div class="mb-1 hidden items-center gap-3 rounded-t-lg bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500 uppercase sm:flex dark:bg-gray-700 dark:text-gray-300">
                        <span class="flex-1">Jabatan</span><span class="w-40 shrink-0 text-right">Tunjangan panitia</span><span class="w-24 shrink-0 text-right">Urutan</span>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($panitia as $j): ?>
                            <?php
                            $jid = (int) $j['id'];
                            $isi = $adaOldP ? (string) old("nominal.$jid", '', false) : ((int) $j['nominal'] > 0 ? $rp($j['nominal']) : '');
                            $urt = $adaOldP ? (string) old("urutan.$jid", '', false) : ($j['urutan'] !== null ? (string) $j['urutan'] : '');
                            ?>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-1 py-2 sm:px-3">
                                <span class="min-w-0 flex-1 basis-40 text-sm font-medium text-gray-900 dark:text-white"><?= esc($j['nama']) ?></span>
                                <span class="relative w-40 shrink-0">
                                    <?= $rpAwal ?>
                                    <input type="text" inputmode="numeric" name="nominal[<?= $jid ?>]" value="<?= esc($isi, 'attr') ?>" placeholder="—"
                                           class="inp ps-9 text-right tabular-nums" aria-label="Tunjangan panitia <?= esc($j['nama'], 'attr') ?>">
                                </span>
                                <input type="text" inputmode="numeric" name="urutan[<?= $jid ?>]" value="<?= esc($urt, 'attr') ?>" placeholder="<?= (int) $j['urutan_bawaan'] ?>" maxlength="4"
                                       class="inp w-24 shrink-0 text-right tabular-nums" aria-label="Urutan di rekap untuk <?= esc($j['nama'], 'attr') ?> (bawaan <?= (int) $j['urutan_bawaan'] ?>)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="kartu-kaki flex justify-end">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan tunjangan &amp; urutan</button>
            </div>
        </form>
    </section>

    <!-- ===== 3. Tanda tangan ===== -->
    <section id="tanda-tangan" class="kartu scroll-mt-20 overflow-hidden" aria-labelledby="judul-ttd">
        <div class="kartu-kepala">
            <h2 id="judul-ttd" class="kartu-judul">Tanda tangan rekap honor</h2>
        </div>
        <form method="post" action="<?= site_url('admin/honor/pengaturan/tanda-tangan') ?>">
            <?= csrf_field() ?>
            <div class="kartu-isi grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="ttd-ketua">Ketua panitia</label>
                    <input type="text" id="ttd-ketua" name="ketua_nama" maxlength="150" value="<?= esc((string) $nilaiOld('ketua_nama', (string) ($ttd['ketua_nama'] ?? '')), 'attr') ?>" placeholder="Nama beserta gelar" class="inp">
                </div>
                <div>
                    <label class="lbl" for="ttd-bendahara">Bendahara</label>
                    <input type="text" id="ttd-bendahara" name="bendahara_nama" maxlength="150" value="<?= esc((string) $nilaiOld('bendahara_nama', (string) ($ttd['bendahara_nama'] ?? '')), 'attr') ?>" placeholder="Nama beserta gelar" class="inp">
                </div>
                <p class="text-xs leading-relaxed text-gray-500 sm:col-span-2 dark:text-gray-400">
                    Kepala Sekolah (&quot;Menyetujui&quot;) diambil dari <a href="<?= site_url('admin/settings') ?>" class="font-medium text-brand-700 underline dark:text-brand-400">Pengaturan Sekolah</a>.
                    Nama ini dipakai sebagai isian awal setiap honor baru dan masih bisa diubah per honor.
                </p>
            </div>
            <div class="kartu-kaki flex justify-end">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan nama tanda tangan</button>
            </div>
        </form>
    </section>
</div>

<?= $this->endSection() ?>
