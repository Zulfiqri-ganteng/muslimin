<?php
/**
 * Kotak "Lapor Diterima PKL" — laporan siswa (sudah diterima perusahaan + periode PKL) yang diproses menjadi Surat Balasan.
 * Controller: Admin\SuratLapor. Aturan: Libraries\PklLapor.
 * Rombak tampilan F9: kepala halaman layout (+ Lihat semua Surat Balasan), kartu tautan form lapor (Salin = kit salinTeks; dulu
 * x-data { ok } + navigator.clipboard di @click), tab status kit, satu kartu kit per laporan, ui_peringatan, lencana kit, tombol
 * kit ber-ikon (pengganti emoji), ui_kosong.
 *
 * @var string                $status      tab aktif: baru | dikembalikan | diproses
 * @var list<array>           $rows        PklLapor::daftar()
 * @var array<string, int>    $hitungLapor status => jumlah
 * @var array<string, bool>   $hak         PklLapor::hak(): lihat, setujui, buat, kembalikan, perlu_acc
 * @var bool                  $bolehForm   boleh membuka formulir Surat Balasan ("Periksa & ubah dulu")
 * @var string                $tautanLapor tautan form lapor untuk siswa
 * @var bool                  $laporBuka   form lapor sedang dibuka
 * @var bool                  $bolehAtur   boleh membuka Pengaturan PKL
 * @var string                $pemegangAcc peran pemegang ACC Surat Balasan (teks)
 * @var string                $peranLabel
 * @var list<array>           $alasanLapor  template alasan "laporan dikembalikan" (PklAlasan::untukDialog('lapor'))
 * @var bool                  $kelolaAlasan boleh mengelola template alasan
 * @var int                   $maksAlasan   batas huruf alasan
 * Revisi 2026-10-20: tiap laporan punya HASIL perusahaan (semua / sebagian / semua ditolak). Sebagian → siswa yang tidak diterima
 * dikeluarkan saat diproses, Balasan hanya untuk yang diterima; semua ditolak → "Konfirmasi ditolak perusahaan" (tanpa surat).
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklLapor;
use App\Libraries\SuratPkl;
use App\Models\SuratSekolahModel;

$waktu = static fn (?string $t): string => $t !== null && $t !== '' ? date('d-m-Y H:i', strtotime($t)) : '—';
$oleh  = static fn (array $r): string => (string) ($r['diproses_oleh'] ?? '') . ((string) ($r['diproses_peran'] ?? '') !== '' ? ' (' . HakAkses::label((string) $r['diproses_peran']) . ')' : '');
$tabs  = [
    'baru'         => ['Menunggu diproses', 'Laporan siswa yang belum dibuatkan Surat Balasan. Yang paling lama menunggu ada di atas.'],
    'dikembalikan' => ['Dikembalikan', 'Laporan yang dikembalikan ke siswa dengan alasan. Siswa bisa memperbaiki dan mengirim ulang; laporannya pindah lagi ke "Menunggu diproses".'],
    'diproses'     => ['Sudah dibuat suratnya', 'Laporan yang sudah dibuatkan Surat Balasan. Terbaru di atas.'],
];
$tombolSetujui = $hak['perlu_acc'] ? 'Setujui & buat surat' : 'Buat surat (langsung siap unduh)';
$dt = 'text-xs text-gray-500 dark:text-gray-400';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/surat') . '?' . http_build_query(['jenis' => 'balasan_pkl']) ?>" class="btn btn-netral"><?= ikon('envelope', 'h-4 w-4') ?>Lihat semua Surat Balasan</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_lapor_v2',
    'helpTitle' => 'Lapor Diterima PKL',
    'helpBody'  => '<p>Siswa yang ajuan PKL-nya sudah <b>disetujui</b> melapor lewat tautan form lapor bahwa <b>perusahaan sudah menerimanya</b>, beserta tanggal mulai &amp; selesai PKL (dan alamat perusahaan bila berubah). Dari laporan itu sekolah membuat <b>Surat Balasan PKL</b>.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>' . esc($tombolSetujui) . '</b>: surat dibuat dari isian laporan (tanggal surat hari ini)' . ($hak['perlu_acc'] ? ' dan langsung disetujui atas nama Anda, lalu siap diunduh' : '') . '. Tombol ini hanya untuk peran yang diberi hak ' . ($hak['perlu_acc'] ? '<b>ACC Surat Balasan</b> (sekarang: ' . esc($pemegangAcc) . ')' : '<b>Buat Surat Balasan</b>') . '.</li>'
        . ($hak['perlu_acc'] ? '<li><b>Buat &amp; ajukan untuk ACC</b>: surat dibuat lalu menunggu ACC (' . esc($pemegangAcc) . ').</li>' : '')
        . '<li><b>Periksa &amp; ubah dulu</b>: membuka formulir Surat Balasan yang sudah terisi dari laporan; ubah bila perlu, lalu simpan. Laporan otomatis ditandai selesai.</li>'
        . '<li><b>Hasil perusahaan</b>: siswa memilih <b>semua diterima</b>, <b>sebagian diterima</b> (mencentang siapa yang diterima), atau <b>semua ditolak</b>. Laporan <b>sebagian</b>: saat Anda memproses, siswa yang tidak diterima otomatis <b>dikeluarkan</b> dari ajuan (bebas mengajukan tempat lain, tetap tercatat) lalu Surat Balasan hanya memuat siswa yang diterima. Laporan <b>semua ditolak</b>: tekan <b>Konfirmasi ditolak perusahaan</b> — ajuan menjadi Ditolak perusahaan, tanpa surat.</li>'
        . '<li><b>Kembalikan</b>: bila isian siswa keliru. Tulis alasannya &mdash; siswa membacanya saat membuka form lapor lagi, lalu bisa mengirim ulang.</li>'
        . '<li>Siapa yang boleh memproses diatur Admin di <b>Sistem → Hak Akses</b> (hak Buat / ACC Surat Balasan). Tanda tangan Waka Hubin dan Kepala Sekolah tetap tercetak, siapa pun yang meng-ACC.</li>'
        . '</ul>',
]) ?>

<div class="space-y-4">
    <!-- Tautan untuk siswa + status form -->
    <section class="kartu kartu-isi" aria-label="Tautan form lapor">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Tautan form lapor untuk siswa
                    <span class="lencana <?= $laporBuka ? 'lencana-hijau' : 'lencana-merah' ?> normal-case"><?= $laporBuka ? 'sedang dibuka' : 'sedang ditutup' ?></span>
                </p>
                <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-brand-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-brand-300 dark:ring-gray-600"><?= esc($tautanLapor) ?></code>
                    <button type="button" class="btn btn-netral btn-sm" x-data="salinTeks" data-teks="<?= esc($tautanLapor, 'attr') ?>" data-pesan="Tautan form lapor tersalin." @click="salin()"><?= ikon('file-copy', 'h-4 w-4') ?><span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin</span></button>
                </div>
                <p class="hint !mt-1.5">
                    <?= $laporBuka ? 'Bagikan ke siswa (grup kelas / WhatsApp). Tautan ini juga tampil sebagai tombol di halaman form PKL.' : 'Siswa yang membuka tautan melihat pemberitahuan "sedang ditutup".' ?>
                    <?php if ($bolehAtur): ?> Buka/tutup form di <a href="<?= site_url('admin/pkl/pengaturan') ?>" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Pengaturan PKL</a>.<?php endif; ?>
                </p>
            </div>
            <div class="peringatan peringatan-netral lg:max-w-sm">
                <?= ikon('user-settings', 'mt-0.5 h-4 w-4 shrink-0') ?>
                <p><b>Hak Anda (<?= esc($peranLabel) ?>):</b>
                    <?php if ($hak['setujui'] && $hak['perlu_acc']): ?>
                        membuat sekaligus menyetujui Surat Balasan dari laporan.
                    <?php elseif ($hak['setujui']): ?>
                        membuat Surat Balasan (jenis ini sedang tanpa ACC: langsung siap unduh).
                    <?php elseif ($hak['buat']): ?>
                        membuat Surat Balasan; ACC oleh <?= esc($pemegangAcc) ?>.
                    <?php else: ?>
                        melihat &amp; mengembalikan laporan. Membuat surat butuh hak Buat Surat Balasan.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Tab status -->
    <div>
        <div class="overflow-x-auto">
            <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Status laporan">
                <?php foreach ($tabs as $kode => [$label]): $aktif = $status === $kode; $n = (int) ($hitungLapor[$kode] ?? 0); ?>
                    <a href="<?= site_url('admin/surat/balasan/laporan') . ($kode !== 'baru' ? '?status=' . $kode : '') ?>" class="tab whitespace-nowrap"<?= $aktif ? ' aria-current="page"' : '' ?>>
                        <?= esc($label) ?>
                        <span class="tab-jumlah<?= $kode === 'baru' && $n > 0 ? ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' ?>"><?= $n ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400"><?= esc($tabs[$status][1]) ?></p>
    </div>

    <?php if ($rows === []): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => $status === 'baru' ? 'check-circle' : 'inbox', 'judul' => $status === 'baru' ? 'Tidak ada laporan yang menunggu' : 'Belum ada laporan di sini',
            'teks' => $status === 'baru' ? 'Laporan baru muncul di sini setelah siswa mengisi form lapor. Bagikan tautan di atas ke siswa yang ajuan PKL-nya sudah disetujui.' : '']) ?></div>
    <?php else: ?>
        <?php foreach ($rows as $r):
            $id         = (int) $r['id'];
            $alamatBaru = (string) ($r['alamat'] ?? '');
            $balasan    = $r['balasan'];
            $bisaBuat   = $status === 'baru' && $r['ajuan_sah'];
            [$lpTeks, $lpWarna] = PklLapor::TAMPIL[$r['status']] ?? [$r['status'], 'lencana-abu'];
            $hasil      = (string) ($r['hasil'] ?? 'semua');
            $ditolakSemua = $hasil === 'ditolak';
            $sebagian   = $hasil === 'sebagian';
            $dikeluarkan = $sebagian ? $r['siswa_ditolak'] : [];
        ?>
            <article class="kartu overflow-hidden<?= $status === 'baru' && ! $r['ajuan_sah'] ? ' border-red-300 dark:border-red-800' : '' ?>" aria-labelledby="judul-lapor-<?= $id ?>">
                <div class="kartu-kepala">
                    <div class="min-w-0">
                        <h2 id="judul-lapor-<?= $id ?>" class="kartu-judul truncate text-base"><?= esc($r['perusahaan']) ?></h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><a href="<?= site_url('admin/pkl/' . (int) $r['pengajuan_id']) ?>" class="font-mono font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= esc($r['kode']) ?></a>
                            · dilaporkan <?= esc($waktu($r['updated_at'])) ?><?= (int) $r['kirim_ke'] > 1 ? ' · kiriman ke-' . (int) $r['kirim_ke'] : '' ?></p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                        <?php if ($ditolakSemua): ?><span class="lencana lencana-merah">Semua ditolak perusahaan</span>
                        <?php elseif ($sebagian): ?><span class="lencana lencana-kuning">Sebagian diterima (<?= count($r['siswa_diterima']) ?> dari <?= count($r['anggota']) ?>)</span><?php endif; ?>
                        <span class="lencana <?= esc($lpWarna, 'attr') ?>"><?= esc($lpTeks) ?></span>
                    </div>
                </div>

                <div class="kartu-isi grid grid-cols-1 gap-4 md:grid-cols-2">
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="<?= $dt ?>">Periode PKL (dari siswa)</dt>
                            <?php if ($ditolakSemua): ?>
                                <dd class="font-semibold text-red-700 dark:text-red-400">— (perusahaan menolak semua siswa)</dd>
                            <?php else: ?>
                                <dd class="font-semibold text-gray-900 dark:text-white"><?= esc(SuratPkl::lamaTeks((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai'])) ?>, <?= esc($r['periode']) ?></dd>
                                <dd class="<?= $dt ?>"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_mulai'])) ?> s.d. <?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_selesai'])) ?></dd>
                            <?php endif; ?>
                        </div>
                        <div>
                            <dt class="<?= $dt ?>">Alamat perusahaan</dt>
                            <?php if ($alamatBaru !== ''): ?>
                                <dd class="font-semibold text-gray-900 dark:text-white"><?= esc($alamatBaru) ?> <span class="lencana lencana-biru ms-1">diperbarui siswa</span></dd>
                                <?php if ($r['alamat_ajuan'] !== ''): ?><dd class="text-xs text-gray-400 line-through dark:text-gray-500"><?= esc($r['alamat_ajuan']) ?></dd><?php endif; ?>
                            <?php else: ?>
                                <dd class="text-gray-700 dark:text-gray-300"><?= esc($r['alamat_ajuan'] !== '' ? $r['alamat_ajuan'] : '—') ?></dd>
                            <?php endif; ?>
                        </div>
                        <?php if ((string) ($r['catatan'] ?? '') !== ''): ?>
                            <div>
                                <dt class="<?= $dt ?>">Catatan siswa</dt>
                                <dd class="whitespace-pre-line text-gray-700 dark:text-gray-300"><?= esc((string) $r['catatan']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <?php $diSurat = $ditolakSemua ? [] : ($status === 'baru' ? $r['siswa_diterima'] : $r['anggota']); ?>
                            <dt class="<?= $dt ?>"><?= $ditolakSemua ? 'Siswa yang ditolak perusahaan (' . count($r['anggota']) . ')' : 'Siswa di Surat Balasan (' . count($diSurat) . ')' ?></dt>
                            <?php if (($ditolakSemua ? $r['anggota'] : $diSurat) === []): ?>
                                <dd class="text-gray-500 dark:text-gray-400">—</dd>
                            <?php else: ?>
                                <dd><ul class="mt-0.5 space-y-0.5">
                                    <?php foreach ($ditolakSemua ? $r['anggota'] : $diSurat as $s): ?>
                                        <li class="<?= $ditolakSemua ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' ?>"><?= esc($s['nama']) ?> <span class="text-xs text-gray-500 dark:text-gray-400"><?= esc($s['kelas']) ?></span></li>
                                    <?php endforeach; ?>
                                </ul></dd>
                            <?php endif; ?>
                        </div>
                        <?php if ($dikeluarkan !== [] && $status === 'baru'): ?>
                            <div>
                                <dt class="<?= $dt ?>">Ditolak perusahaan — dikeluarkan saat diproses (<?= count($dikeluarkan) ?>)</dt>
                                <dd><ul class="mt-0.5 space-y-0.5">
                                    <?php foreach ($dikeluarkan as $s): ?>
                                        <li class="text-red-700 line-through dark:text-red-400"><?= esc($s['nama']) ?> <span class="text-xs text-gray-500 no-underline dark:text-gray-400"><?= esc($s['kelas']) ?></span></li>
                                    <?php endforeach; ?>
                                </ul></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt class="<?= $dt ?>">Pelapor</dt>
                            <dd class="text-gray-700 dark:text-gray-300"><?= esc((string) $r['pelapor_nama']) ?><?= (string) ($r['hp'] ?? '') !== '' ? ' · <span class="font-mono text-xs">' . esc((string) $r['hp']) . '</span>' : '' ?></dd>
                        </div>
                    </dl>
                </div>

                <?php if ($r['peringatan'] !== [] || ($status === 'baru' && (! $r['ajuan_sah'] || $balasan !== null))): ?>
                    <div class="space-y-2 px-4 pb-4 sm:px-6">
                        <?php if ($status === 'baru' && ! $r['ajuan_sah'] && ! $ditolakSemua): ?>
                            <?= ui_peringatan('galat', 'Ajuan ini tidak lagi berstatus disetujui (atau siswanya tidak aktif), jadi Surat Balasan tidak bisa dibuat. Kembalikan laporan dengan alasannya.') ?>
                        <?php endif; ?>
                        <?php if ($status === 'baru' && $balasan !== null): ?>
                            <?= ui_peringatan('sukses', '', ['html' => 'Ajuan ini sudah punya Surat Balasan <a href="' . site_url('admin/surat/' . (int) $balasan['id']) . '" class="font-semibold underline">' . esc(SuratSekolahModel::kode((int) $balasan['id'])) . '</a>. Memproses laporan ini hanya menandainya selesai (tidak membuat surat baru) — periksa periodenya di surat itu.']) ?>
                        <?php endif; ?>
                        <?php foreach ($r['peringatan'] as $t): ?>
                            <?= ui_peringatan('awas', (string) $t) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'diproses'): ?>
                    <div class="kartu-kaki flex flex-col gap-1 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:text-gray-300">
                        <span>Diproses <?= esc($oleh($r)) ?> · <?= esc($waktu($r['diproses_at'])) ?></span>
                        <?php if (! empty($r['surat_id'])):
                            $st = $balasan !== null && (int) $balasan['id'] === (int) $r['surat_id'] ? (string) $balasan['status'] : ''; ?>
                            <a href="<?= site_url('admin/surat/' . (int) $r['surat_id']) ?>" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline dark:text-brand-400">Buka Surat Balasan <?= esc(SuratSekolahModel::kode((int) $r['surat_id'])) ?><?= $st !== '' ? ' (' . esc(SuratSekolahModel::TAMPIL_STATUS[$st][0] ?? $st) . ')' : '' ?><?= ikon('chevron-right', 'h-3.5 w-3.5') ?></a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($status === 'dikembalikan'): ?>
                    <div class="kartu-kaki text-sm text-gray-600 dark:text-gray-300">
                        <p><b class="text-red-700 dark:text-red-400">Alasan:</b> <?= esc((string) ($r['alasan'] ?? '')) ?></p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Dikembalikan <?= esc($oleh($r)) ?> · <?= esc($waktu($r['diproses_at'])) ?>. Menunggu siswa mengirim ulang.</p>
                    </div>
                <?php else: ?>
                    <div class="kartu-kaki flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                        <?php if ($status === 'baru' && $ditolakSemua && $hak['lihat']): // semua ditolak: konfirmasi, tanpa surat (juga bila ajuan sudah ditolak lebih dulu) ?>
                            <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/setujui') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-bahaya w-full sm:w-auto" data-confirm-judul="Konfirmasi ditolak perusahaan?" data-confirm-ya="Ya, konfirmasi"
                                        data-confirm="<?= esc('Ajuan ' . $r['kode'] . ' (' . $r['perusahaan'] . ') akan ditandai DITOLAK PERUSAHAAN dan seluruh siswanya bebas mengajukan tempat lain. Tidak ada surat yang dibuat.', 'attr') ?>"><?= ikon('close', 'h-4 w-4') ?>Konfirmasi ditolak perusahaan</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($ditolakSemua): // tombol surat tidak berlaku ?>
                        <?php elseif ($bisaBuat && $hak['setujui']): ?>
                            <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/setujui') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sukses w-full sm:w-auto" data-confirm-judul="Proses laporan?" data-confirm-ya="Ya, lanjutkan"
                                        data-confirm="<?= esc(($dikeluarkan !== [] ? implode(', ', array_column($dikeluarkan, 'nama')) . ' akan DIKELUARKAN dari ajuan (ditolak perusahaan). ' : '') . ($balasan !== null ? 'Tandai laporan ' . $r['kode'] . ' selesai (Surat Balasan sudah ada)?' : ($hak['perlu_acc'] ? 'Buat Surat Balasan untuk ' . $r['kode'] . ' dan langsung SETUJUI atas nama Anda?' : 'Buat Surat Balasan untuk ' . $r['kode'] . '?')), 'attr') ?>"><?= ikon('check', 'h-4 w-4') ?><?= esc($balasan !== null ? 'Tandai selesai' : $tombolSetujui) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($bisaBuat && $hak['buat'] && ! $ditolakSemua): ?>
                            <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/buat') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn <?= $hak['setujui'] ? 'btn-netral' : 'btn-utama' ?> w-full sm:w-auto" data-confirm-judul="Proses laporan?" data-confirm-ya="Ya, lanjutkan"
                                        data-confirm="<?= esc(($dikeluarkan !== [] ? implode(', ', array_column($dikeluarkan, 'nama')) . ' akan DIKELUARKAN dari ajuan (ditolak perusahaan). ' : '') . ($balasan !== null ? 'Tandai laporan ' . $r['kode'] . ' selesai (Surat Balasan sudah ada)?' : 'Buat Surat Balasan untuk ' . $r['kode'] . ' dan ajukan untuk ACC?'), 'attr') ?>"><?= ikon('envelope', 'h-4 w-4') ?><?= $balasan !== null ? ($hak['setujui'] ? 'Tandai selesai (tanpa ACC)' : 'Tandai selesai') : 'Buat &amp; ajukan untuk ACC' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($bisaBuat && $bolehForm && $balasan === null && $hasil === 'semua'): // laporan sebagian/ditolak hanya lewat tombol di sini ?>
                            <a href="<?= site_url('admin/surat/balasan') . '?lapor=' . $id ?>" class="btn btn-netral"><?= ikon('edit', 'h-4 w-4') ?>Periksa &amp; ubah dulu</a>
                        <?php endif; ?>
                        <?php if ($hak['kembalikan']): ?>
                            <details class="sm:ms-auto sm:min-w-[18rem]">
                                <summary class="btn btn-netral w-full cursor-pointer list-none text-red-700 dark:text-red-400"><?= ikon('close-circle', 'h-4 w-4') ?>Kembalikan ke siswa…</summary>
                                <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/kembalikan') ?>" class="mt-2 space-y-2">
                                    <?= csrf_field() ?>
                                    <?= view('admin/pkl/_alasan_cepat', ['grup' => $alasanLapor ?? [], 'target' => 'alasan-' . $id, 'kelola' => ! empty($kelolaAlasan)]) ?>
                                    <label class="lbl" for="alasan-<?= $id ?>">Alasan (dibaca siswa) <span class="text-red-600 dark:text-red-400">*</span></label>
                                    <textarea id="alasan-<?= $id ?>" name="alasan" rows="3" minlength="5" maxlength="<?= (int) ($maksAlasan ?? 255) ?>" required class="inp" placeholder="contoh: tanggal selesai PKL keliru, periksa lagi surat dari perusahaan"></textarea>
                                    <button type="submit" class="btn btn-bahaya w-full">Kembalikan laporan</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <?php if ($bisaBuat && ! $ditolakSemua && ! $hak['setujui'] && ! $hak['buat']): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Membuat surat butuh hak <b>Buat Surat Balasan</b> (Sistem → Hak Akses).</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (count($rows) >= 200): ?>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">Menampilkan 200 laporan pertama.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script defer src="<?= base_url('assets/js/admin/pkl-alasan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl-alasan.js') ?>" <?= csp_script_nonce() ?>></script>

<?= $this->endSection() ?>
