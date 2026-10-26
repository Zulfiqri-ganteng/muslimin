<?php
/**
 * Surat Permohonan Perpanjangan PKL — halaman staf (docs/DESAIN-PERPANJANGAN-PKL.md bagian 6). Pola tampilan baru (rencana
 * docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md): cari lalu pilih (tidak menampilkan ratusan ajuan), satu kartu per langkah, SATU
 * tombol utama. Siap CSP: komponen Alpine `suratPerpanjanganForm` (assets/js/admin/surat-perpanjangan.js), data lewat data-config.
 *
 * @var bool                       $ubah
 * @var string                     $q         kata kunci cari (mode pilih)
 * @var list<array<string, mixed>> $hasil     SuratPerpanjangan::cari()
 * @var list<array<string, mixed>> $berakhir  SuratPerpanjangan::berakhir(7)
 * @var array<string, mixed>|null  $ajuan     SuratPerpanjangan::ajuanForm() — null = mode pilih
 * @var array<string, mixed>       $nilai     tanggal_surat, sapaan, selesai_lama, selesai_baru, alasan_pilih, alasan_lain, siswa[]
 * @var list<array{0: string, 1: string}> $alasanSaran
 * @var array{buka: bool, sebelum: int, sesudah: int} $setelan
 * @var array<string, mixed>       $batas     akhir (Y-m-d), maks (hari total), maksHari (lama perpanjangan)
 * @var bool                       $perluAcc
 * @var bool                       $bisaLangsungAcc
 * @var string                     $pemegangAcc
 * @var callable                   $tglIndo
 * @var array<string, mixed>       $surat     (ubah) baris surat_sekolah
 * @var string                     $kode      (ubah)
 */

use App\Libraries\SuratJenis;

$alamat  = SuratJenis::alamat(SuratJenis::PERPANJANGAN);
$aksi    = $ubah ? site_url($alamat . '/' . (int) $surat['id'] . '/ubah') : site_url($alamat);
$kembali = $ubah ? site_url('admin/surat/' . (int) $surat['id']) : site_url('admin/surat');
$wajib   = ' <span class="text-red-600 dark:text-red-400">*</span>';
$kepala  = static fn (string $no, string $judul, string $ket = ''): string => '<div class="kartu-kepala"><div><h3 class="kartu-judul text-base">'
    . ($no !== '' ? '<span class="me-1.5 text-brand-700 dark:text-brand-400">' . $no . '.</span>' : '') . $judul . '</h3>'
    . ($ket !== '' ? '<p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">' . $ket . '</p>' : '') . '</div></div>';
$pilihUrl = static fn (int $aid, int $sid = 0): string => site_url($alamat) . '?' . http_build_query(['ajuan' => $aid] + ($sid > 0 ? ['siswa' => $sid] : []));
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= $kembali ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?><?= $ubah ? 'Kembali ke surat' : 'Daftar Surat' ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_perpanjangan_form_v1',
    'helpTitle' => 'Surat Perpanjangan PKL',
    'helpBody'  => '<p>Surat permohonan ke perusahaan bahwa PKL siswa <b>diperpanjang</b> sampai tanggal tertentu. Format sama dengan Surat Balasan sekolah.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li>Cari nama siswa atau tempat PKL, pilih, lalu centang siswa yang diperpanjang. Teman yang tidak diperpanjang tidak usah dicentang.</li>'
        . '<li>Perpanjangan <b>hanya boleh satu kali</b> per siswa. Siswa yang sudah punya surat perpanjangan tampil redup.</li>'
        . '<li>Tanggal selesai semula diambil dari Surat Balasan / laporan siswa. Bila belum tercatat, isi sendiri.</li>'
        . '<li>Tanggal baru <b>berlaku setelah surat di-ACC</b>' . ($perluAcc ? ' (' . esc($pemegangAcc) . ')' : '') . '. Data PKL lama (Surat Balasan) tidak berubah.</li>'
        . '<li>Siswa juga bisa meminta surat ini sendiri lewat Layanan Siswa mulai ' . (int) $setelan['sebelum'] . ' hari sebelum PKL selesai' . ($setelan['sesudah'] > 0 ? ' sampai ' . (int) $setelan['sesudah'] . ' hari sesudahnya' : '') . ' (diatur di Pengaturan PKL).</li>'
        . '</ul>',
]) ?>

<div class="mx-auto max-w-3xl space-y-4">
<?php if ($ajuan === null): ?>
    <!-- Mode pilih: cari, atau pilih dari daftar PKL yang segera berakhir -->
    <section class="kartu">
        <?= $kepala('1', 'Pilih siswa', 'Ketik nama siswa, nama tempat PKL, atau nomor ajuan (PKL-00123).') ?>
        <div class="kartu-isi space-y-4">
            <form method="get" action="<?= site_url($alamat) ?>" class="flex flex-col gap-2 sm:flex-row" role="search">
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" minlength="2" required class="inp sm:flex-1" placeholder="Contoh: Siti Aminah, PT Maju Jaya, PKL-00123" aria-label="Cari siswa atau tempat PKL" autofocus>
                <button type="submit" class="btn btn-utama"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            </form>

            <?php if ($q !== ''): ?>
                <?php if ($hasil === []): ?>
                    <?= ui_peringatan('netral', 'Tidak ada ajuan PKL disetujui yang cocok dengan "' . $q . '". Periksa ejaannya, atau cari dengan nama tempat PKL.') ?>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                        <?php foreach ($hasil as $a): ?>
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($a['perusahaan']) ?> <span class="font-mono text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($a['kode']) ?></span></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc(implode(', ', array_column($a['siswa'], 'nama'))) ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">PKL selesai: <?= $a['selesai'] !== null ? esc($tglIndo($a['selesai'])) : '<span class="text-yellow-700 dark:text-yellow-300">belum tercatat</span>' ?></p>
                                </div>
                                <?php if ((int) $a['bisa'] > 0): ?>
                                    <a href="<?= esc($pilihUrl((int) $a['id']), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('arrow-right', 'h-4 w-4') ?>Pilih</a>
                                <?php else: ?>
                                    <span class="lencana lencana-abu">Semua sudah diperpanjang / ditarik</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">PKL berakhir dalam 7 hari <span class="font-normal text-gray-500 dark:text-gray-400">(belum diperpanjang)</span></p>
                    <?php if ($berakhir === []): ?>
                        <p class="hint mt-1">Tidak ada. Daftar ini hanya memuat ajuan yang tanggal selesai PKL-nya sudah tercatat (dari Surat Balasan / laporan siswa).</p>
                    <?php else: ?>
                        <ul class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <?php foreach ($berakhir as $b): ?>
                                <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($b['nama']) ?> <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($b['kelas']) ?></span></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($b['perusahaan']) ?> · selesai <?= esc($tglIndo($b['selesai'])) ?></p>
                                    </div>
                                    <span class="lencana <?= $b['sisa'] <= 2 ? 'lencana-merah' : 'lencana-kuning' ?>"><?= $b['sisa'] === 0 ? 'Hari ini' : $b['sisa'] . ' hari lagi' ?></span>
                                    <a href="<?= esc($pilihUrl($b['ajuan_id'], $b['siswa_id']), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('arrow-right', 'h-4 w-4') ?>Pilih</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php else:
    $lamaTerkunci = $ajuan['sumber_lama'] === 'sekolah' && $ajuan['selesai_lama'] !== null;
    $cfg = [
        'lama' => $lamaTerkunci ? (string) $ajuan['selesai_lama'] : $nilai['selesai_lama'], 'lamaTerkunci' => $lamaTerkunci,
        'baru' => $nilai['selesai_baru'], 'mulaiAjuan' => (string) ($ajuan['mulai'] ?? ''),
        'alasanPilih' => $nilai['alasan_pilih'], 'alasanLain' => $nilai['alasan_lain'], 'alasanSaran' => $alasanSaran, 'batas' => $batas,
    ];
    $bisa = (int) $ajuan['bisa'];
?>
    <form method="post" action="<?= esc($aksi, 'attr') ?>" x-data="suratPerpanjanganForm" data-config="<?= esc(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="ajuan_id" value="<?= (int) $ajuan['id'] ?>">

        <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"><?= $ubah ? 'Ubah Surat Perpanjangan PKL <span class="lencana lencana-abu font-mono">' . esc($kode) . '</span>' : 'Buat Surat Perpanjangan PKL' ?></h2>

        <!-- 1. Tempat PKL & siswa -->
        <section class="kartu">
            <?= $kepala('1', 'Siswa yang diperpanjang') ?>
            <div class="kartu-isi space-y-3">
                <div class="flex flex-wrap items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($ajuan['perusahaan']) ?> <a href="<?= site_url('admin/pkl/' . (int) $ajuan['id']) ?>" class="font-mono text-xs font-normal text-brand-700 hover:underline dark:text-brand-400"><?= esc($ajuan['kode']) ?></a></p>
                        <?php if ($ajuan['alamat'] !== ''): ?><p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($ajuan['alamat']) ?></p><?php endif; ?>
                    </div>
                    <?php if (! $ubah): ?><a href="<?= site_url($alamat) ?>" class="btn btn-teks btn-sm"><?= ikon('edit', 'h-4 w-4') ?>Ganti</a><?php endif; ?>
                </div>
                <div class="space-y-1.5">
                    <?php foreach ($ajuan['siswa'] as $s): $cek = in_array($s['siswa_id'], $nilai['siswa'], true); ?>
                        <label class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 <?= $s['bisa'] ? 'cursor-pointer text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-700/60' : 'cursor-not-allowed text-gray-500 opacity-70 dark:text-gray-400' ?>">
                            <input type="checkbox" name="siswa[]" value="<?= (int) $s['siswa_id'] ?>" class="cek"<?= $s['bisa'] ? ($cek ? ' checked' : '') : ' disabled' ?>>
                            <span class="font-semibold"><?= esc($s['nama']) ?></span> <span class="text-xs text-gray-500 dark:text-gray-400"><?= esc($s['kelas']) ?></span>
                            <?php if ($s['pj'] !== null): ?>
                                <a href="<?= site_url('admin/surat/' . (int) $s['pj']['surat_id']) ?>" class="lencana lencana-kuning ms-auto hover:underline">Sudah diperpanjang s.d. <?= esc($tglIndo($s['pj']['selesai_baru'])) ?> (<?= esc($s['pj']['kode']) ?>)</a>
                            <?php elseif ($s['ditarik'] !== null): ?>
                                <span class="lencana lencana-merah ms-auto">Ditarik (<?= esc($s['ditarik']) ?>)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($bisa === 0): ?>
                    <?= ui_peringatan('awas', 'Semua siswa ajuan ini sudah pernah diperpanjang atau sedang ditarik. Perpanjangan PKL hanya boleh satu kali.') ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($bisa > 0): ?>
        <!-- 2. Tanggal -->
        <section class="kartu">
            <?= $kepala('2', 'Tanggal perpanjangan') ?>
            <div class="kartu-isi space-y-4">
                <div>
                    <p class="lbl">PKL selesai semula</p>
                    <?php if ($lamaTerkunci): ?>
                        <p class="text-sm text-gray-900 dark:text-white"><b><?= esc($tglIndo((string) $ajuan['selesai_lama'])) ?></b> <span class="text-xs text-gray-500 dark:text-gray-400">· dari data sekolah (Surat Balasan / laporan siswa)</span></p>
                    <?php else: ?>
                        <input id="selesai_lama" name="selesai_lama" type="date" x-model="lama" required class="inp sm:max-w-md" aria-describedby="hint-lama">
                        <p id="hint-lama" class="hint">Belum tercatat di sistem (ajuan ini belum punya Surat Balasan). Isi tanggal selesai PKL yang sekarang — tanyakan ke siswa atau perusahaan.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="lbl" for="selesai_baru">Diperpanjang sampai<?= $wajib ?></label>
                    <div class="mb-2 flex flex-wrap gap-2" role="group" aria-label="Pilihan cepat">
                        <button type="button" class="btn btn-netral btn-sm" :class="cepatAktif('hari', 14) && 'ring-2 ring-brand-500'" :disabled="!adaLama()" @click="pakaiHari(14)">+2 minggu</button>
                        <button type="button" class="btn btn-netral btn-sm" :class="cepatAktif('bulan', 1) && 'ring-2 ring-brand-500'" :disabled="!adaLama()" @click="pakaiBulan(1)">+1 bulan</button>
                        <button type="button" class="btn btn-netral btn-sm" :class="cepatAktif('bulan', 2) && 'ring-2 ring-brand-500'" :disabled="!adaLama()" @click="pakaiBulan(2)">+2 bulan</button>
                        <button type="button" class="btn btn-netral btn-sm" :class="cepatAktif('bulan', 3) && 'ring-2 ring-brand-500'" :disabled="!adaLama()" @click="pakaiBulan(3)">+3 bulan</button>
                    </div>
                    <input id="selesai_baru" name="selesai_baru" type="date" x-model="baru" :min="minBaru()" required class="inp sm:max-w-md">
                </div>
                <div x-show="ringkasCetak() !== ''" x-cloak class="peringatan peringatan-info">
                    <?= ikon('eye', 'mt-0.5 h-5 w-5 shrink-0') ?>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold" x-text="ringkasHari()"></p>
                        <p class="mt-1 text-sm">Tertulis di surat: <b x-text="ringkasCetak()"></b></p>
                    </div>
                </div>
                <template x-for="t in peringatanList()" :key="t">
                    <p class="flex items-start gap-1.5 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800 dark:border-yellow-800 dark:bg-gray-700 dark:text-yellow-300"><?= ikon('exclamation-circle', 'h-4 w-4 shrink-0') ?><span x-text="t"></span></p>
                </template>
            </div>
        </section>

        <!-- 3. Alasan & isi surat -->
        <section class="kartu">
            <?= $kepala('3', 'Alasan perpanjangan') ?>
            <div class="kartu-isi space-y-4">
                <fieldset class="space-y-1.5">
                    <legend class="sr-only">Alasan perpanjangan</legend>
                    <?php foreach ($alasanSaran as $i => [, $pendek]): ?>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                            <input type="radio" name="alasan_pilih" value="<?= (int) $i ?>" x-model="alasanPilih" class="cek"> <?= esc($pendek) ?>
                        </label>
                    <?php endforeach; ?>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                        <input type="radio" name="alasan_pilih" value="lain" x-model="alasanPilih" class="cek"> Lainnya — tulis sendiri
                    </label>
                </fieldset>
                <div x-show="alasanPilih === 'lain'" x-cloak>
                    <label class="lbl" for="alasan_lain">Alasan (tulis sendiri)</label>
                    <textarea id="alasan_lain" name="alasan_lain" x-model="alasanLain" rows="2" maxlength="200" class="inp" placeholder="contoh: perusahaan membutuhkan bantuan peserta didik sampai akhir proyek"></textarea>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">Di surat: "Perpanjangan ini kami ajukan karena <b x-text="alasanKalimat() || '……'"></b>."</p>

                <div class="grid grid-cols-1 gap-4 border-t border-gray-200 pt-4 sm:grid-cols-2 dark:border-gray-700">
                    <div>
                        <label class="lbl" for="sapaan">Kepada Yth. Bapak/Ibu …</label>
                        <input id="sapaan" name="sapaan" type="text" value="<?= esc($nilai['sapaan'], 'attr') ?>" maxlength="60" list="saranSapaan" class="inp" placeholder="Pimpinan">
                        <datalist id="saranSapaan"><?php foreach ($sapaanSaran as $s): ?><option value="<?= esc($s, 'attr') ?>"><?php endforeach; ?></datalist>
                    </div>
                    <div>
                        <label class="lbl" for="tanggal_surat">Tanggal surat<?= $wajib ?></label>
                        <input id="tanggal_surat" name="tanggal_surat" type="date" value="<?= esc($nilai['tanggal_surat'], 'attr') ?>" required class="inp">
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <a href="<?= $kembali ?>" class="btn btn-teks">Batal</a>
            <?php if ($bisa > 0 && $ubah): ?>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan perubahan</button>
            <?php elseif ($bisa > 0 && ! empty($bisaLangsungAcc)): ?>
                <button type="submit" name="langsung_acc" value="1" class="btn btn-sukses"><?= ikon('check', 'h-4 w-4') ?>Simpan &amp; setujui (ACC)</button>
            <?php elseif ($bisa > 0): ?>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><?= $perluAcc ? 'Buat surat &amp; ajukan untuk ACC' : 'Buat surat' ?></button>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/surat-perpanjangan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-perpanjangan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
