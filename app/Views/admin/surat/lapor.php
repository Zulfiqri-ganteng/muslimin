<?php
/**
 * Kotak "Lapor Diterima PKL" — laporan siswa (sudah diterima perusahaan + periode PKL) yang diproses menjadi Surat Balasan.
 * Controller: Admin\SuratLapor. Aturan: Libraries\PklLapor.
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
$tombolSetujui = $hak['perlu_acc'] ? '✓ Setujui & buat surat' : 'Buat surat (langsung siap unduh)';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_lapor_v1',
    'helpTitle' => 'Lapor Diterima PKL',
    'helpBody'  => '<p>Siswa yang ajuan PKL-nya sudah <b>disetujui</b> melapor lewat tautan form lapor bahwa <b>perusahaan sudah menerimanya</b>, beserta tanggal mulai &amp; selesai PKL (dan alamat perusahaan bila berubah). Dari laporan itu sekolah membuat <b>Surat Balasan PKL</b>.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>' . esc($tombolSetujui) . '</b>: surat dibuat dari isian laporan (tanggal surat hari ini)' . ($hak['perlu_acc'] ? ' dan langsung disetujui atas nama Anda, lalu siap diunduh' : '') . '. Tombol ini hanya untuk peran yang diberi hak ' . ($hak['perlu_acc'] ? '<b>ACC Surat Balasan</b> (sekarang: ' . esc($pemegangAcc) . ')' : '<b>Buat Surat Balasan</b>') . '.</li>'
        . ($hak['perlu_acc'] ? '<li><b>Buat &amp; ajukan untuk ACC</b>: surat dibuat lalu menunggu ACC (' . esc($pemegangAcc) . ').</li>' : '')
        . '<li><b>Periksa &amp; ubah dulu</b>: membuka formulir Surat Balasan yang sudah terisi dari laporan; ubah bila perlu, lalu simpan. Laporan otomatis ditandai selesai.</li>'
        . '<li><b>Kembalikan</b>: bila isian siswa keliru. Tulis alasannya &mdash; siswa membacanya saat membuka form lapor lagi, lalu bisa mengirim ulang.</li>'
        . '<li>Siapa yang boleh memproses diatur Admin di <b>Sistem → Hak Akses</b> (hak Buat / ACC Surat Balasan). Tanda tangan Waka Hubin dan Kepala Sekolah tetap tercetak, siapa pun yang meng-ACC.</li>'
        . '</ul>',
]) ?>

<div class="rise mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Lapor Diterima PKL</h1>
        <p class="text-sm text-slate-500">Laporan siswa "sudah diterima perusahaan" → Surat Balasan PKL.</p>
    </div>
    <a href="<?= site_url('admin/surat') . '?' . http_build_query(['jenis' => 'balasan_pkl']) ?>" class="text-sm font-semibold text-brand-700 hover:underline">Lihat semua Surat Balasan →</a>
</div>

<!-- Tautan untuk siswa + status form -->
<div class="rise mb-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm" x-data="{ ok: false }">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tautan form lapor untuk siswa
                <?php if ($laporBuka): ?>
                    <span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold normal-case tracking-normal text-green-700">sedang dibuka</span>
                <?php else: ?>
                    <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold normal-case tracking-normal text-red-700">sedang ditutup</span>
                <?php endif; ?>
            </p>
            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-brand-700 ring-1 ring-slate-200"><?= esc($tautanLapor) ?></code>
                <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(<?= esc(json_encode($tautanLapor), 'attr') ?>); ok = true" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><span x-text="ok ? '✓ Tersalin' : 'Salin'">Salin</span></button>
            </div>
            <p class="mt-1.5 text-xs text-slate-400">
                <?= $laporBuka ? 'Bagikan ke siswa (grup kelas / WhatsApp). Tautan ini juga tampil sebagai tombol di halaman form PKL.' : 'Siswa yang membuka tautan melihat pemberitahuan "sedang ditutup".' ?>
                <?php if ($bolehAtur): ?> Buka/tutup form di <a href="<?= site_url('admin/pkl/pengaturan') ?>" class="font-semibold text-brand-700 hover:underline">Pengaturan PKL</a>.<?php endif; ?>
            </p>
        </div>
        <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 lg:max-w-sm">
            <b class="text-slate-700">Hak Anda (<?= esc($peranLabel) ?>):</b>
            <?php if ($hak['setujui'] && $hak['perlu_acc']): ?>
                membuat sekaligus menyetujui Surat Balasan dari laporan.
            <?php elseif ($hak['setujui']): ?>
                membuat Surat Balasan (jenis ini sedang tanpa ACC: langsung siap unduh).
            <?php elseif ($hak['buat']): ?>
                membuat Surat Balasan; ACC oleh <?= esc($pemegangAcc) ?>.
            <?php else: ?>
                melihat &amp; mengembalikan laporan. Membuat surat butuh hak Buat Surat Balasan.
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab status -->
<nav class="rise -mx-1 mb-4 flex gap-1.5 overflow-x-auto px-1 pb-1" aria-label="Status laporan">
    <?php foreach ($tabs as $kode => [$label]): $aktif = $status === $kode; $n = (int) ($hitungLapor[$kode] ?? 0); ?>
        <a href="<?= site_url('admin/surat/balasan/laporan') . ($kode !== 'baru' ? '?status=' . $kode : '') ?>"
           class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition active:scale-95 <?= $aktif ? 'bg-brand-700 text-white shadow-md shadow-brand-700/20' : 'border border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700' ?>"<?= $aktif ? ' aria-current="page"' : '' ?>>
            <?= esc($label) ?>
            <span class="inline-flex min-w-[1.4rem] justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold <?= $aktif ? 'bg-white/20 text-white' : ($kode === 'baru' && $n > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500') ?>"><?= $n ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<p class="rise mb-4 text-xs text-slate-400"><?= esc($tabs[$status][1]) ?></p>

<?php if ($rows === []): ?>
    <div class="rise rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
        <p class="font-semibold text-slate-700"><?= $status === 'baru' ? 'Tidak ada laporan yang menunggu. 🎉' : 'Belum ada laporan di sini.' ?></p>
        <?php if ($status === 'baru'): ?>
            <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-slate-400">Laporan baru muncul di sini setelah siswa mengisi form lapor. Bagikan tautan di atas ke siswa yang ajuan PKL-nya sudah disetujui.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($rows as $r):
            $id        = (int) $r['id'];
            $alamatBaru = (string) ($r['alamat'] ?? '');
            $balasan   = $r['balasan'];
            $bisaBuat  = $status === 'baru' && $r['ajuan_sah'];
        ?>
            <article class="rise overflow-hidden rounded-2xl border <?= $status === 'baru' && ! $r['ajuan_sah'] ? 'border-red-200' : 'border-slate-200' ?> bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="truncate font-bold text-slate-800"><?= esc($r['perusahaan']) ?></h2>
                        <p class="text-xs text-slate-400"><a href="<?= site_url('admin/pkl/' . (int) $r['pengajuan_id']) ?>" class="font-mono font-semibold text-brand-700 hover:underline"><?= esc($r['kode']) ?></a>
                            · dilaporkan <?= esc($waktu($r['updated_at'])) ?><?= (int) $r['kirim_ke'] > 1 ? ' · kiriman ke-' . (int) $r['kirim_ke'] : '' ?></p>
                    </div>
                    <span class="shrink-0 self-start rounded-full px-2.5 py-1 text-xs font-bold <?= esc(PklLapor::TAMPIL[$r['status']][1] ?? 'bg-slate-100 text-slate-600', 'attr') ?>"><?= esc(PklLapor::TAMPIL[$r['status']][0] ?? $r['status']) ?></span>
                </div>

                <div class="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Periode PKL (dari siswa)</dt>
                            <dd class="font-semibold text-slate-800"><?= esc(SuratPkl::lamaTeks((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai'])) ?>, <?= esc($r['periode']) ?></dd>
                            <dd class="text-xs text-slate-400"><?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_mulai'])) ?> s.d. <?= esc(IsianBantu::tanggalIndo((string) $r['tanggal_selesai'])) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Alamat perusahaan</dt>
                            <?php if ($alamatBaru !== ''): ?>
                                <dd class="font-semibold text-slate-800"><?= esc($alamatBaru) ?> <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-700">diperbarui siswa</span></dd>
                                <?php if ($r['alamat_ajuan'] !== ''): ?><dd class="text-xs text-slate-400 line-through decoration-slate-300"><?= esc($r['alamat_ajuan']) ?></dd><?php endif; ?>
                            <?php else: ?>
                                <dd class="text-slate-700"><?= esc($r['alamat_ajuan'] !== '' ? $r['alamat_ajuan'] : '—') ?></dd>
                            <?php endif; ?>
                        </div>
                        <?php if ((string) ($r['catatan'] ?? '') !== ''): ?>
                            <div>
                                <dt class="text-xs text-slate-500">Catatan siswa</dt>
                                <dd class="whitespace-pre-line text-slate-700"><?= esc((string) $r['catatan']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Siswa di Surat Balasan (<?= count($r['anggota']) ?>)</dt>
                            <?php if ($r['anggota'] === []): ?>
                                <dd class="text-slate-400">—</dd>
                            <?php else: ?>
                                <dd><ul class="mt-0.5 space-y-0.5">
                                    <?php foreach ($r['anggota'] as $s): ?>
                                        <li class="text-slate-700"><?= esc($s['nama']) ?> <span class="text-xs text-slate-400"><?= esc($s['kelas']) ?></span></li>
                                    <?php endforeach; ?>
                                </ul></dd>
                            <?php endif; ?>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Pelapor</dt>
                            <dd class="text-slate-700"><?= esc((string) $r['pelapor_nama']) ?><?= (string) ($r['hp'] ?? '') !== '' ? ' · <span class="font-mono text-xs">' . esc((string) $r['hp']) . '</span>' : '' ?></dd>
                        </div>
                    </dl>
                </div>

                <?php if ($r['peringatan'] !== [] || ($status === 'baru' && (! $r['ajuan_sah'] || $balasan !== null))): ?>
                    <div class="space-y-1.5 px-5 pb-4">
                        <?php if ($status === 'baru' && ! $r['ajuan_sah']): ?>
                            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-800">Ajuan ini tidak lagi berstatus disetujui (atau siswanya tidak aktif), jadi Surat Balasan tidak bisa dibuat. Kembalikan laporan dengan alasannya.</p>
                        <?php endif; ?>
                        <?php if ($status === 'baru' && $balasan !== null): ?>
                            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Ajuan ini sudah punya Surat Balasan <a href="<?= site_url('admin/surat/' . (int) $balasan['id']) ?>" class="underline"><?= esc(SuratSekolahModel::kode((int) $balasan['id'])) ?></a>. Memproses laporan ini hanya menandainya selesai (tidak membuat surat baru) — periksa periodenya di surat itu.</p>
                        <?php endif; ?>
                        <?php foreach ($r['peringatan'] as $t): ?>
                            <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">⚠ <?= esc($t) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'diproses'): ?>
                    <div class="flex flex-col gap-1 border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <span>Diproses <?= esc($oleh($r)) ?> · <?= esc($waktu($r['diproses_at'])) ?></span>
                        <?php if (! empty($r['surat_id'])):
                            $st = $balasan !== null && (int) $balasan['id'] === (int) $r['surat_id'] ? (string) $balasan['status'] : ''; ?>
                            <a href="<?= site_url('admin/surat/' . (int) $r['surat_id']) ?>" class="font-semibold text-brand-700 hover:underline">Buka Surat Balasan <?= esc(SuratSekolahModel::kode((int) $r['surat_id'])) ?><?= $st !== '' ? ' (' . esc(SuratSekolahModel::TAMPIL_STATUS[$st][0] ?? $st) . ')' : '' ?> →</a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($status === 'dikembalikan'): ?>
                    <div class="border-t border-slate-100 bg-red-50/60 px-5 py-3 text-xs text-slate-600">
                        <p><b class="text-red-800">Alasan:</b> <?= esc((string) ($r['alasan'] ?? '')) ?></p>
                        <p class="mt-0.5 text-slate-400">Dikembalikan <?= esc($oleh($r)) ?> · <?= esc($waktu($r['diproses_at'])) ?>. Menunggu siswa mengirim ulang.</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-start">
                        <?php if ($bisaBuat && $hak['setujui']): ?>
                            <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/setujui') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" data-confirm="<?= esc(($balasan !== null ? 'Tandai laporan ' . $r['kode'] . ' selesai (Surat Balasan sudah ada)?' : ($hak['perlu_acc'] ? 'Buat Surat Balasan untuk ' . $r['kode'] . ' dan langsung SETUJUI atas nama Anda?' : 'Buat Surat Balasan untuk ' . $r['kode'] . '?')), 'attr') ?>"
                                        class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 active:scale-95 sm:w-auto"><?= esc($balasan !== null ? '✓ Tandai selesai' : $tombolSetujui) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($bisaBuat && $hak['buat']): ?>
                            <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/buat') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" data-confirm="<?= esc($balasan !== null ? 'Tandai laporan ' . $r['kode'] . ' selesai (Surat Balasan sudah ada)?' : 'Buat Surat Balasan untuk ' . $r['kode'] . ' dan ajukan untuk ACC?', 'attr') ?>"
                                        class="w-full rounded-lg <?= $hak['setujui'] ? 'border border-brand-300 bg-white text-brand-700 hover:bg-brand-50' : 'bg-brand-700 text-white hover:bg-brand-800' ?> px-4 py-2 text-sm font-bold shadow-sm transition active:scale-95 sm:w-auto"><?= $balasan !== null ? ($hak['setujui'] ? 'Tandai selesai (tanpa ACC)' : '✓ Tandai selesai') : 'Buat &amp; ajukan untuk ACC' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($bisaBuat && $bolehForm && $balasan === null): ?>
                            <a href="<?= site_url('admin/surat/balasan') . '?lapor=' . $id ?>" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Periksa &amp; ubah dulu</a>
                        <?php endif; ?>
                        <?php if ($hak['kembalikan']): ?>
                            <details class="group sm:ml-auto sm:min-w-[18rem]">
                                <summary class="cursor-pointer list-none rounded-lg border border-red-200 bg-white px-4 py-2 text-center text-sm font-semibold text-red-700 transition hover:bg-red-50">Kembalikan ke siswa…</summary>
                                <form method="post" action="<?= site_url('admin/surat/balasan/laporan/' . $id . '/kembalikan') ?>" class="mt-2 space-y-2">
                                    <?= csrf_field() ?>
                                    <label class="lbl" for="alasan-<?= $id ?>">Alasan (dibaca siswa) <span class="text-red-500">*</span></label>
                                    <textarea id="alasan-<?= $id ?>" name="alasan" rows="2" minlength="5" maxlength="255" required class="inp" placeholder="contoh: tanggal selesai PKL keliru, periksa lagi surat dari perusahaan"></textarea>
                                    <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">Kembalikan laporan</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <?php if ($bisaBuat && ! $hak['setujui'] && ! $hak['buat']): ?>
                            <p class="text-xs text-slate-500">Membuat surat butuh hak <b>Buat Surat Balasan</b> (Sistem → Hak Akses).</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (count($rows) >= 200): ?>
        <p class="mt-4 text-center text-xs text-slate-400">Menampilkan 200 laporan pertama.</p>
    <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
