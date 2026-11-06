<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * KELAS SAYA — wali kelas (2026-11-07). Pilih kelas (wali: kelas yang ia walikan; Admin: semua kelas) lalu tab:
 * Siswa (HP + tombol WhatsApp orang tua, biodata) · PKL (status = menu PKL → Status Siswa) · Keterlambatan (Guru Piket) · Surat.
 * Isi dari Libraries\KelasSayaData (sama dengan API wali/kelas/{id}). Hanya melihat. Tabel di layar lebar, kartu di HP.
 *
 * @var list<array{id: int, nama_kelas: string, tingkat: string, guru_id: int, guru_nama: string}> $kelas
 * @var int                       $pilih
 * @var array<string, mixed>|null $data   KelasSayaData::kelas()
 * @var string                    $tab
 * @var bool                      $admin
 * @var bool                      $siap
 */
use App\Libraries\IsianBantu;
use App\Libraries\KelasSayaData;

$tgl   = static fn (?string $t): string => $t ? IsianBantu::tanggalIndo(substr($t, 0, 10)) : '—';
$url   = static fn (string $t) => site_url('admin/kelas-saya') . '?' . http_build_query(['kelas' => $pilih, 'tab' => $t]);
$tombolWa = static fn (?string $u, string $nama): string => $u !== null
    ? '<a href="' . esc($u, 'attr') . '" target="_blank" rel="noopener" class="btn btn-sukses btn-sm" aria-label="WhatsApp orang tua ' . esc($nama, 'attr') . '">' . ikon('share-nodes', 'h-4 w-4') . 'WA ortu</a>'
    : '<span class="text-xs text-gray-500 dark:text-gray-400">HP ortu kosong</span>';
$lencanaPkl = static fn (?array $p): string => $p === null ? '<span class="text-xs text-gray-500 dark:text-gray-400">—</span>'
    : '<span class="lencana ' . (KelasSayaData::WARNA_PKL[$p['fase']] ?? 'lencana-abu') . '">' . esc($p['label']) . '</span>' . ($p['pernah_ditolak'] ? ' <span class="lencana lencana-merah">pernah ditolak</span>' : '');
?>

<?= view('admin/partials/help', [
    'helpKey'   => 'kelas_saya_v2',
    'helpTitle' => 'Kelas Saya',
    'helpBody'  => '<p>Halaman khusus <b>wali kelas</b>: semua yang perlu Anda pantau dari siswa kelas Anda, di satu tempat. Halaman ini <b>hanya untuk melihat</b> — perubahan data tetap lewat operator sekolah.</p>'
        . '<ol class="mt-2 list-decimal space-y-1 pl-5">'
        . '<li><b>Siswa</b> — NIS/NISN, HP siswa, orang tua &amp; HP-nya, status biodata. Tombol hijau <b>WA ortu</b> membuka WhatsApp ke orang tua dengan salam pembuka dari Anda; lanjutkan pesannya sendiri.</li>'
        . '<li><b>Kenapa tombol WA ortu tidak muncul?</b> Nomor HP orang tua baru ada setelah siswa mengisi <b>Biodata Siswa</b>. Kotak kuning di tab Siswa berisi tautan biodata untuk dibagikan ke grup kelas.</li>'
        . '<li><b>PKL</b> — status PKL tiap siswa (belum mengajukan, menunggu ACC, sedang PKL, dll.), sama dengan data Waka Hubin. Pakai untuk mengingatkan siswa yang <b>belum mengajukan</b>.</li>'
        . '<li><b>Keterlambatan</b> — catatan Guru Piket: jumlah terlambat tiap siswa tahun ajaran ini dan daftar terbaru.</li>'
        . '<li><b>Surat</b> — surat sekolah yang memuat siswa kelas Anda (izin ASTS/TKA, surat PKL).</li>'
        . '</ol>'
        . '<p class="mt-2">Wali lebih dari satu kelas? Pilih kelasnya di kotak pilihan atas. Di HP, tab bisa digeser ke samping dan tiap siswa tampil sebagai kartu.</p>'
        . '<p class="mt-2"><b>Kenapa kelas saya tidak muncul?</b> Akun Anda harus dihubungkan ke data guru Anda dan Anda harus tercatat sebagai wali di Master Kelas. Minta Admin memeriksanya di <b>Kelola Akun</b>.</p>'
        . '<p class="mt-2"><b>Kenapa tab PKL kosong?</b> Tingkat kelas Anda belum termasuk yang boleh PKL di Pengaturan PKL (mis. kelas X).</p>',
]) ?>

<?php if (! $siap): ?>
    <section class="kartu"><?= ui_kosong(['ikon' => 'users-group', 'judul' => 'Fitur wali kelas belum aktif', 'teks' => 'Server belum diperbarui (php spark migrate). Hubungi Admin.']) ?></section>
<?php elseif ($kelas === [] || $data === null): ?>
    <section class="kartu"><?= ui_kosong(['ikon' => 'users-group', 'judul' => 'Anda belum tercatat sebagai wali kelas',
        'teks' => 'Akun Anda belum dihubungkan ke data guru, atau Anda belum menjadi wali di Master Kelas. Hubungi Admin sekolah.']) ?></section>
<?php else:
    $r = $data['ringkasan']; ?>
<div class="space-y-4">
    <section class="kartu overflow-hidden" aria-labelledby="judul-kelas-saya">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 id="judul-kelas-saya" class="kartu-judul"><?= esc($data['kelas']['nama']) ?></h2>
                <p class="kartu-ket">Wali kelas: <?= esc($data['kelas']['wali'] ?? '—') ?><?= $data['kelas']['jurusan'] ? ' · ' . esc($data['kelas']['jurusan']) : '' ?><?= $data['tahun_ajaran'] ? ' · TA ' . esc($data['tahun_ajaran']) : '' ?></p>
            </div>
            <span class="lencana lencana-merek shrink-0"><?= (int) $r['siswa'] ?> siswa</span>
        </div>
        <?php if (count($kelas) > 1): ?>
            <div class="alat-crud">
                <form method="get" class="alat-crud-cari">
                    <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                    <select name="kelas" data-autosubmit class="inp alat-crud-pilih" aria-label="Pilih kelas">
                        <?php foreach ($kelas as $k): ?>
                            <option value="<?= (int) $k['id'] ?>"<?= $k['id'] === $pilih ? ' selected' : '' ?>><?= esc($k['nama_kelas']) ?><?= $admin && $k['guru_nama'] !== '' ? ' — ' . esc($k['guru_nama']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-netral">Tampilkan</button></noscript>
                </form>
            </div>
        <?php endif; ?>
        <div class="kartu-isi">
            <dl class="grid grid-cols-2 gap-3 text-center text-sm sm:grid-cols-4">
                <?php foreach (array_filter([
                    ['Laki-laki / Perempuan', $r['laki'] . ' / ' . $r['perempuan']],
                    ['Biodata terisi', $r['biodata_terisi'] . ' / ' . $r['siswa']],
                    $r['pkl'] !== null ? ['Belum mengajukan PKL', (string) $r['pkl']['belum_mengajukan']] : null,
                    ['Siswa pernah terlambat', (string) $r['telat_siswa']],
                ]) as [$label, $nilai]): ?>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"><dt class="text-gray-500 dark:text-gray-400"><?= esc($label) ?></dt><dd class="text-lg font-bold text-gray-900 dark:text-white"><?= esc($nilai) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <div class="overflow-x-auto">
        <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Bagian kelas">
            <?php foreach (KelasSayaData::BAGIAN as $kode => $label):
                $jml = match ($kode) { 'siswa' => $r['siswa'], 'pkl' => $r['pkl'] !== null ? $r['pkl']['belum_mengajukan'] : 0, 'telat' => $r['telat_kejadian'], default => $r['surat'] }; ?>
                <a href="<?= esc($url($kode), 'attr') ?>" class="tab whitespace-nowrap"<?= $tab === $kode ? ' aria-current="page"' : '' ?>><?= esc($label) ?><span class="tab-jumlah"><?= (int) $jml ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <section class="kartu overflow-hidden">
    <?php if ($tab === 'siswa'): ?>
        <?php if ($r['tanpa_hp_ortu'] > 0 && $data['siswa'] !== []): ?>
            <div class="kartu-isi border-b border-gray-200 dark:border-gray-700">
                <?= ui_peringatan('awas', '', ['html' => '<b>' . (int) $r['tanpa_hp_ortu'] . ' dari ' . (int) $r['siswa'] . ' siswa</b> belum punya nomor HP orang tua di data sekolah, jadi tombol WA ortu tidak muncul. Nomor itu terisi saat siswa mengisi <b>Biodata Siswa</b>: bagikan tautan <a href="' . esc($data['tautan_biodata'], 'attr') . '" target="_blank" rel="noopener" class="font-semibold underline">' . esc($data['tautan_biodata']) . '</a> ke grup kelas.']) ?>
            </div>
        <?php endif; ?>
        <?php if ($data['siswa'] === []): ?><?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada siswa aktif di kelas ini', 'teks' => '']) ?><?php else: ?>
            <div class="tabel-bungkus hidden md:block">
                <table class="tabel">
                    <thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">NIS / NISN</th><th scope="col">HP siswa</th><th scope="col">Orang tua / wali</th><th scope="col">Biodata</th><th scope="col"><span class="sr-only">WhatsApp</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($data['siswa'] as $i => $s): ?>
                            <tr>
                                <td class="tabular-nums"><?= $i + 1 ?></td>
                                <td class="font-medium text-gray-900 dark:text-white"><?= esc($s['nama']) ?> <span class="text-xs text-gray-500 dark:text-gray-400">(<?= esc($s['jk'] ?: '-') ?>)</span></td>
                                <td class="tabular-nums"><?= esc($s['nis'] ?? '-') ?><br><span class="text-xs text-gray-500 dark:text-gray-400"><?= esc($s['nisn'] ?? '-') ?></span></td>
                                <td class="tabular-nums"><?= esc($s['hp'] ?? '-') ?></td>
                                <td><?= esc($s['ortu_nama'] ?? '-') ?><br><span class="text-xs tabular-nums text-gray-500 dark:text-gray-400"><?= esc($s['ortu_hp'] ?? '-') ?></span></td>
                                <td><?= $s['biodata'] ? '<span class="lencana lencana-hijau">Terisi</span>' : '<span class="lencana lencana-abu">Belum</span>' ?></td>
                                <td class="whitespace-nowrap text-end"><?= $tombolWa($s['wa_ortu'], $s['nama']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
                <?php foreach ($data['siswa'] as $i => $s): ?>
                    <li class="space-y-1 px-4 py-3 text-sm">
                        <p class="flex items-start justify-between gap-2"><span class="font-semibold text-gray-900 dark:text-white"><?= $i + 1 ?>. <?= esc($s['nama']) ?></span>
                            <?= $s['biodata'] ? '<span class="lencana lencana-hijau shrink-0">Biodata terisi</span>' : '<span class="lencana lencana-abu shrink-0">Biodata belum</span>' ?></p>
                        <p class="text-gray-600 dark:text-gray-300">NIS <?= esc($s['nis'] ?? '-') ?> · NISN <?= esc($s['nisn'] ?? '-') ?> · <?= esc($s['jk'] ?: '-') ?> · HP <?= esc($s['hp'] ?? '-') ?></p>
                        <p class="text-gray-600 dark:text-gray-300">Orang tua: <?= esc($s['ortu_nama'] ?? '-') ?> · <?= esc($s['ortu_hp'] ?? '-') ?></p>
                        <div class="pt-1"><?= $tombolWa($s['wa_ortu'], $s['nama']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    <?php elseif ($tab === 'pkl'): ?>
        <?php if ($r['pkl'] === null): ?>
            <?= ui_kosong(['ikon' => 'briefcase', 'judul' => 'Kelas ini belum termasuk tingkat yang PKL', 'teks' => 'Tingkat yang boleh mengajukan PKL diatur di Pengaturan PKL.']) ?>
        <?php else: ?>
            <div class="kartu-isi border-b border-gray-200 text-sm dark:border-gray-700">
                <p><b><?= (int) $r['pkl']['sudah_pkl'] ?></b> siswa sudah dapat tempat PKL (<?= (int) $r['pkl']['persen_pkl'] ?>%) · <b><?= (int) $r['pkl']['menunggu'] ?></b> menunggu keputusan · <b class="text-red-700 dark:text-red-400"><?= (int) $r['pkl']['belum_mengajukan'] ?></b> belum mengajukan.</p>
            </div>
            <div class="tabel-bungkus hidden md:block">
                <table class="tabel">
                    <thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">Status PKL</th><th scope="col">Tempat PKL</th><th scope="col">Periode</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['siswa'] as $i => $s): $p = $s['pkl']; ?>
                            <tr>
                                <td class="tabular-nums"><?= $i + 1 ?></td>
                                <td class="font-medium text-gray-900 dark:text-white"><?= esc($s['nama']) ?></td>
                                <td><?= $lencanaPkl($p) ?></td>
                                <td><?= esc($p['perusahaan'] ?? '—') ?><?= ! empty($p['kota']) ? '<br><span class="text-xs text-gray-500 dark:text-gray-400">' . esc($p['kota']) . '</span>' : '' ?></td>
                                <td class="text-xs"><?= ! empty($p['mulai']) ? esc($tgl($p['mulai'])) . ' s.d. ' . esc($tgl($p['selesai'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
                <?php foreach ($data['siswa'] as $i => $s): $p = $s['pkl']; ?>
                    <li class="space-y-1 px-4 py-3 text-sm">
                        <p class="font-semibold text-gray-900 dark:text-white"><?= $i + 1 ?>. <?= esc($s['nama']) ?></p>
                        <p><?= $lencanaPkl($p) ?></p>
                        <?php if (! empty($p['perusahaan'])): ?><p class="text-gray-600 dark:text-gray-300"><?= esc($p['perusahaan']) ?><?= ! empty($p['mulai']) ? ' · ' . esc($tgl($p['mulai'])) . ' s.d. ' . esc($tgl($p['selesai'])) : '' ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    <?php elseif ($tab === 'telat'): ?>
        <?php $pernah = array_values(array_filter($data['siswa'], static fn (array $s): bool => $s['telat']['jumlah'] > 0));
        usort($pernah, static fn (array $a, array $b): int => $b['telat']['jumlah'] <=> $a['telat']['jumlah']); ?>
        <?php if ($data['keterlambatan'] === [] && $pernah === []): ?>
            <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Belum ada catatan keterlambatan', 'teks' => 'Catatan berasal dari Guru Piket.']) ?>
        <?php else: ?>
            <div class="kartu-isi border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Paling sering terlambat<?= $data['tahun_ajaran'] ? ' (TA ' . esc($data['tahun_ajaran']) . ')' : '' ?></h3>
                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                    <?php foreach (array_slice($pernah, 0, 10) as $s): ?>
                        <li class="rounded-lg border border-gray-200 px-3 py-1.5 dark:border-gray-700"><?= esc($s['nama']) ?> <b><?= (int) $s['telat']['jumlah'] ?>×</b></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Nama</th><th scope="col">Sanksi / keterangan</th><th scope="col">Guru piket</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['keterlambatan'] as $t): ?>
                            <tr>
                                <td class="whitespace-nowrap text-xs tabular-nums"><?= esc($tgl($t['tanggal'])) ?><?= $t['jam'] ? '<br>' . esc($t['jam']) : '' ?></td>
                                <td class="font-medium text-gray-900 dark:text-white"><?= esc($t['nama']) ?><?= $t['ortu_dikabari'] ? ' <span class="lencana lencana-hijau">ortu dikabari</span>' : '' ?></td>
                                <td class="text-sm"><?= esc(trim(($t['sanksi'] ?? '') . ($t['keterangan'] ? ' — ' . $t['keterangan'] : ''), ' —') ?: '—') ?></td>
                                <td class="text-sm"><?= esc($t['guru_piket'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <?php if ($data['surat'] === []): ?>
            <?= ui_kosong(['ikon' => 'file-lines', 'judul' => 'Belum ada surat untuk siswa kelas ini', 'teks' => 'Surat izin ASTS/TKA dan surat PKL yang memuat siswa kelas ini akan tampil di sini.']) ?>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Siswa</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['surat'] as $s): ?>
                            <tr>
                                <td class="whitespace-nowrap text-xs tabular-nums"><?= esc($tgl($s['tanggal'])) ?><br><span class="text-gray-500 dark:text-gray-400"><?= esc($s['nomor'] ?? $s['kode']) ?></span></td>
                                <td class="text-sm"><?= esc($s['jenis_label']) ?></td>
                                <td class="text-sm"><?= esc($s['siswa']) ?></td>
                                <td><span class="lencana <?= esc(\App\Models\SuratSekolahModel::TAMPIL_STATUS[$s['status']][1] ?? 'lencana-abu', 'attr') ?>"><?= esc($s['status_label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
