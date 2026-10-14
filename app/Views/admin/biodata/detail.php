<?php
/**
 * Periksa satu isian biodata: Master Siswa (lama) vs isian siswa (baru). Rombak tampilan F6: kartu, lencana, peringatan &
 * tombol kit; komponen Alpine "periksaBiodata" di assets/js/admin/biodata.js (tanpa objek sebaris / penugasan di isian) → siap CSP.
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
    'menunggu'  => ['Menunggu verifikasi', 'lencana-merek'],
    'disetujui' => ['Disetujui', 'lencana-hijau'],
    'perbaikan' => ['Perlu perbaikan', 'lencana-kuning'],
][$status] ?? [$status, 'lencana-abu'];
$kembali  = site_url('admin/biodata') . '?' . http_build_query(array_filter(['tab' => $status, 'kelas_id' => $kelasId ?: '']));
$aksi     = site_url('admin/biodata/' . (int) $row['id']);
$data     = \App\Models\BiodataIsianModel::decode($row);
$nama     = $siswa['nama'] ?? ($data['nama'] ?? '—');
$jmlBeda  = (int) $hitung['baru'] + (int) $hitung['ubah'];
$bisaSetujui = $status === 'menunggu' && $siswa !== null && empty($siswa['deleted_at']);
$urlLewati   = $berikutnyaId > 0 ? site_url('admin/biodata/' . $berikutnyaId) . ($kelasId > 0 ? '?kelas_id=' . $kelasId : '') : '';
$v = static fn (string $p): string => base_url($p) . '?v=' . @filemtime(FCPATH . $p);
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($kembali, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke daftar</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="periksaBiodata" class="max-w-5xl space-y-4 pb-2">
    <!-- ================= Kepala ================= -->
    <section class="kartu" aria-labelledby="judul-siswa">
        <div class="kartu-isi">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Isian #<?= str_pad((string) (int) $row['id'], 5, '0', STR_PAD_LEFT) ?></p>
                    <h2 id="judul-siswa" class="mt-0.5 text-xl font-bold break-words text-gray-900 sm:text-2xl dark:text-white"><?= esc($nama) ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kelas <b class="text-gray-900 dark:text-white"><?= esc($siswa['nama_kelas'] ?? '—') ?></b> · NISN <?= esc($data['nisn'] ?? ($row['nisn'] ?? '—')) ?></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($status === 'menunggu' && $sisaMenunggu > 0): ?>
                        <span class="lencana lencana-abu"><span class="titik-status titik-status-biru"></span>Antrean: <?= number_format($sisaMenunggu, 0, ',', '.') ?> isian</span>
                    <?php endif; ?>
                    <span class="lencana <?= $lencana[1] ?>"><?= esc($lencana[0]) ?></span>
                </div>
            </div>

            <!-- Ringkasan perbedaan -->
            <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2.5 dark:border-green-800 dark:bg-green-900/20">
                    <p class="text-2xl font-bold text-green-700 tabular-nums dark:text-green-400"><?= (int) $hitung['baru'] ?></p>
                    <p class="text-xs font-medium text-green-800 dark:text-green-300">kolom baru terisi</p>
                </div>
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2.5 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <p class="text-2xl font-bold text-yellow-700 tabular-nums dark:text-yellow-400"><?= (int) $hitung['ubah'] ?></p>
                    <p class="text-xs font-medium text-yellow-800 dark:text-yellow-300">kolom berubah</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-700/50">
                    <p class="text-2xl font-bold text-gray-900 tabular-nums dark:text-white"><?= (int) $row['kirim_ke'] ?></p>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">kali dikirim siswa</p>
                </div>
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 text-sm sm:grid-cols-3 md:px-6 dark:border-gray-700 dark:bg-gray-800/60">
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Pertama dikirim</dt><dd class="font-medium text-gray-900 tabular-nums dark:text-white"><?= $fmtTgl($row['created_at']) ?></dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Terakhir diperbarui</dt><dd class="font-medium text-gray-900 tabular-nums dark:text-white"><?= $fmtTgl($row['updated_at']) ?></dd></div>
            <div class="col-span-2 sm:col-span-1"><dt class="text-xs text-gray-500 dark:text-gray-400">Alamat IP</dt><dd class="font-medium break-all text-gray-900 dark:text-white"><?= esc($row['ip_address'] ?? '—') ?></dd></div>
        </dl>
    </section>

    <!-- ================= Pemberitahuan ================= -->
    <?php if ($status === 'disetujui'): ?>
        <?= ui_peringatan('sukses', '', ['html' => 'Disetujui <b>' . $fmtTgl($row['diverifikasi_at']) . '</b>' . ($admin ? ' oleh <b>' . esc($admin['full_name'] ?: $admin['username']) . '</b>' : '') . ' — data sudah masuk Master Siswa.']) ?>
    <?php endif; ?>

    <?php if ($siswa === null): ?>
        <?= ui_peringatan('galat', 'Siswa ini sudah dihapus dari Master Siswa, jadi isian tidak bisa disetujui. Hapus isian ini bila tidak diperlukan.') ?>
    <?php elseif (! empty($siswa['deleted_at'])): ?>
        <?= ui_peringatan('galat', 'Siswa ini berada di tempat sampah Master Siswa (terhapus). Pulihkan dulu bila isiannya ingin disetujui.') ?>
    <?php endif; ?>

    <?php if (! empty($row['catatan_admin'])): ?>
        <?= ui_peringatan('awas', '', ['html' => '<b>Catatan yang dikirim ke siswa:</b> ' . esc($row['catatan_admin'])]) ?>
    <?php endif; ?>

    <?php if ($peringatan !== []): ?>
        <?= ui_peringatan('awas', $peringatan, ['judul' => 'Perhatikan sebelum menyetujui:']) ?>
    <?php endif; ?>

    <!-- ================= Pembanding ================= -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-banding">
        <div class="kartu-kepala">
            <div>
                <h2 id="judul-banding" class="kartu-judul">Bandingkan data</h2>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-sm border border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-900/40"></span>baru terisi</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-sm border border-yellow-300 bg-yellow-100 dark:border-yellow-700 dark:bg-yellow-900/40"></span>berubah (nilai lama dicoret)</span>
                </p>
            </div>
            <?php if ($jmlBeda > 0): ?>
                <label class="saklar">
                    <input type="checkbox" x-model="hanyaBeda" class="peer sr-only">
                    <span class="saklar-jalur"></span>
                    <span class="saklar-label">Tampilkan yang berubah saja</span>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($jmlBeda === 0): ?>
            <p class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 md:px-6 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300">Tidak ada perbedaan — isian siswa sama persis dengan data di Master Siswa.</p>
        <?php endif; ?>

        <!-- Judul kolom (layar lebar) -->
        <div class="hidden grid-cols-[13rem_minmax(0,1fr)_minmax(0,1fr)] gap-4 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-xs font-semibold tracking-wide text-gray-500 uppercase md:grid md:px-6 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">
            <span>Kolom</span><span><?= esc($judulLama) ?></span><span>Isian siswa</span>
        </div>

        <?php foreach ($bagian as $judul => $baris):
            $adaBeda = array_filter($baris, static fn ($b) => $b['jenis'] !== 'sama') !== []; ?>
            <section <?= $adaBeda ? '' : 'x-show="!hanyaBeda"' ?>>
                <h3 class="bg-gray-100 px-4 py-2 text-xs font-semibold tracking-wide text-gray-600 uppercase md:px-6 dark:bg-gray-700/60 dark:text-gray-300"><?= esc($judul) ?></h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($baris as $b):
                        $beda  = $b['jenis'] !== 'sama';
                        $latar = $b['jenis'] === 'baru' ? 'bg-green-50 dark:bg-green-900/20' : ($b['jenis'] === 'ubah' ? 'bg-yellow-50 dark:bg-yellow-900/20' : ''); ?>
                        <li <?= $beda ? '' : 'x-show="!hanyaBeda"' ?> class="grid grid-cols-1 gap-x-4 gap-y-1 px-4 py-2.5 text-sm md:grid-cols-[13rem_minmax(0,1fr)_minmax(0,1fr)] md:px-6 <?= $latar ?>">
                            <span class="text-xs font-semibold text-gray-500 md:text-sm md:font-normal dark:text-gray-400"><?= esc($b['label']) ?></span>
                            <!-- Data lama -->
                            <span class="break-words">
                                <span class="inline-block w-16 text-[11px] font-semibold tracking-wide text-gray-400 uppercase md:hidden">Lama</span><span class="<?= $b['lama'] === '' ? 'text-gray-300 dark:text-gray-600' : ($b['jenis'] === 'ubah' ? 'text-red-700 line-through decoration-red-400 dark:text-red-400' : 'text-gray-600 dark:text-gray-300') ?>"><?= $b['lama'] === '' ? '—' : esc($b['lama']) ?></span>
                            </span>
                            <!-- Isian siswa -->
                            <span class="break-words">
                                <span class="inline-block w-16 text-[11px] font-semibold tracking-wide text-gray-400 uppercase md:hidden">Isian</span><?php if ($b['tetap']): ?><span class="text-gray-400">kosong</span> <span class="text-xs text-gray-400">— data lama dipertahankan</span><?php else: ?><span class="font-semibold <?= $b['baru'] === '' ? 'text-gray-300 dark:text-gray-600' : 'text-gray-900 dark:text-white' ?>"><?= $b['baru'] === '' ? '—' : esc($b['baru']) ?></span><?php if ($b['jenis'] === 'baru'): ?> <span class="lencana lencana-hijau ms-1">baru</span><?php endif; ?><?php if ($b['jenis'] === 'ubah'): ?> <span class="lencana lencana-kuning ms-1">berubah</span><?php endif; ?><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </section>

    <!-- ================= Keputusan — menempel di bawah layar ================= -->
    <?php if ($siswa !== null): ?>
        <div class="kartu sticky bottom-3 z-20 p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($bisaSetujui): ?>
                    <form method="post" action="<?= $aksi ?>/setujui" class="flex-1 sm:flex-none">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                        <button type="submit" class="btn btn-sukses w-full"
                                data-confirm="Setujui biodata <?= esc($nama, 'attr') ?>? <?= $jmlBeda > 0 ? $jmlBeda . ' kolom akan diperbarui di Master Siswa.' : 'Data di Master Siswa tidak berubah.' ?>">
                            <?= ikon('check', 'h-4 w-4') ?><span>Setujui<span class="hidden sm:inline"> &amp; masukkan ke Master Siswa</span></span>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($status !== 'perbaikan'): ?>
                    <button type="button" class="btn btn-netral flex-1 sm:flex-none" @click="alihKembali()" :aria-expanded="formKembali" aria-controls="form-kembalikan">
                        <?= ikon('file-pen', 'h-4 w-4 text-yellow-600 dark:text-yellow-400') ?><span>Kembalikan<span class="hidden sm:inline"> untuk diperbaiki</span></span>
                    </button>
                <?php endif; ?>
                <?php if ($status === 'menunggu' && $urlLewati !== ''): ?>
                    <a href="<?= esc($urlLewati, 'attr') ?>" class="btn btn-teks" title="Buka isian menunggu berikutnya tanpa memutuskan yang ini">Lewati<?= ikon('chevron-right', 'h-4 w-4') ?></a>
                <?php endif; ?>
                <form method="post" action="<?= $aksi ?>/hapus" class="ms-auto">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-bahaya"
                            data-confirm="Hapus isian <?= esc($nama, 'attr') ?>? Siswa harus mengisi ulang dari awal. Data yang SUDAH masuk Master Siswa tidak ikut terhapus.">
                        <?= ikon('trash-bin', 'h-4 w-4') ?><span class="hidden sm:inline">Hapus isian</span><span class="sm:hidden">Hapus</span>
                    </button>
                </form>
            </div>
            <?php if ($bisaSetujui): ?>
                <p class="hint mt-2"><?= $berikutnyaId > 0 ? 'Setelah disetujui / dikembalikan, isian menunggu berikutnya langsung terbuka.' : 'Ini isian menunggu terakhir' . ($kelasId > 0 ? ' di kelas ini' : '') . '.' ?></p>
            <?php endif; ?>

            <form id="form-kembalikan" x-cloak x-show="formKembali" x-transition method="post" action="<?= $aksi ?>/kembalikan" class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                <label class="lbl" for="catatan">Apa yang harus diperbaiki siswa? <span class="text-red-600">*</span></label>
                <textarea id="catatan" name="catatan" rows="2" maxlength="255" required x-ref="catatan" class="inp"
                          placeholder="Contoh: NISN salah, cek lagi di kartu pelajar. Tanggal lahir tidak sesuai KK."></textarea>
                <p class="hint">Catatan ini tampil ke siswa saat membuka isiannya lagi (dengan NISN / tanggal lahir). Kabari siswa lewat wali kelas.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-utama"><?= ikon('file-pen', 'h-4 w-4') ?>Kirim ke siswa untuk diperbaiki</button>
                    <button type="button" class="btn btn-netral" @click="tutupKembali()">Batal</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/biodata.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
