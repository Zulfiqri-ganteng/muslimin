<?php
/**
 * Detail satu ajuan PKL + keputusan staf.
 *
 * @var array       $a           ajuan (PklPengajuanModel::detail)
 * @var list<array> $anggota
 * @var list<array> $riwayat
 * @var list<array> $peringatan  [tingkat, teks]
 * @var bool        $adaBahaya
 * @var array       $master      ['persis' => ?array, 'mirip' => list<array>]
 * @var string      $kode
 * @var bool        $bolehHapus
 * @var bool        $bolehAcc    peran ini berhak ACC/tolak/cabut persetujuan (Waka Hubin; Admin cadangan)
 * @var ?int        $sisaHari    sisa hari menuju batas keputusan Waka Hubin (negatif = terlambat), hanya untuk status menunggu
 * @var ?string     $batasKeputusan
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklSurat;

$namaSaya = (string) (session('admin')['full_name'] ?? '');
$id     = (int) $a['id'];
$status = (string) $a['status'];
$hari   = ($a['tanggal_mulai'] && $a['tanggal_selesai']) ? IsianBantu::hariInklusif($a['tanggal_mulai'], $a['tanggal_selesai']) : null;
$fase   = null;
if ($status === 'disetujui' && $a['tanggal_mulai'] && $a['tanggal_selesai']) {
    $hariIni = date('Y-m-d');
    $fase    = $hariIni < $a['tanggal_mulai'] ? 'belum_mulai' : ($hariIni > $a['tanggal_selesai'] ? 'selesai' : 'sedang');
}
$labelAksi = [
    'kirim' => 'Dikirim siswa', 'kirim_ulang' => 'Dikirim ulang (perbaikan)', 'acc' => 'Disetujui', 'kembalikan' => 'Dikembalikan ke siswa',
    'tolak' => 'Ditolak', 'batal_acc' => 'Persetujuan dibatalkan', 'ubah' => 'Data diubah staf', 'isi_atas_nama' => 'Diisi atas nama siswa',
    'tunda' => 'Dikembalikan ke antrean', 'surat' => 'Surat diterbitkan', 'cetak' => 'Surat diunduh', 'impor' => 'Diimpor dari Excel',
];
$warnaPeringatan = [
    'bahaya' => ['border-red-300 bg-red-50 text-red-800', 'BAHAYA', 'bg-red-600'],
    'awas'   => ['border-amber-300 bg-amber-50 text-amber-900', 'PERIKSA', 'bg-amber-500'],
    'info'   => ['border-blue-200 bg-blue-50 text-blue-900', 'INFO', 'bg-blue-500'],
];
$baris = static function (string $label, ?string $isi): string {
    $isi = trim((string) $isi);

    return '<div class="grid grid-cols-[7.5rem_1fr] gap-3 px-5 py-2.5 text-sm"><dt class="text-slate-500">' . esc($label) . '</dt><dd class="break-words font-semibold text-slate-800">'
        . ($isi !== '' ? esc($isi) : '<span class="font-normal text-slate-300">—</span>') . '</dd></div>';
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_detail_v4',
    'helpTitle' => 'Detail Ajuan PKL',
    'helpBody'  => '<p>Periksa dulu <b>peringatan otomatis</b> (merah = bahaya, kuning = periksa, biru = info), lalu putuskan:</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>ACC</b> — <u>hanya Waka Hubin</u> (Admin sebagai cadangan, harus menyatakan mewakili Waka Hubin). Tercatat siapa, kapan, dan tercetak di kaki surat. Perusahaan otomatis didaftarkan ke master, jadi periksa ejaan namanya dulu.</li>'
        . '<li><b>Kembalikan</b> — boleh Operator atau Waka Hubin; siswa memperbaiki sendiri, tulis alasan yang jelas.</li>'
        . '<li><b>Tolak</b> / <b>Batalkan persetujuan</b> — hanya Waka Hubin (atau Admin). Tolak: siswanya bebas mengajukan baru. Batalkan: bila perusahaan menarik diri.</li>'
        . '<li><b>Ubah</b> — memperbaiki langsung (nama perusahaan, daftar siswa, HP) tanpa mengubah statusnya. Ajuan yang sudah disetujui hanya boleh diubah Waka Hubin / Admin.</li>'
        . '<li><b>Batas keputusan</b> — Waka Hubin memutuskan paling lambat sekian hari sejak siswa mengirim (diatur di Pengaturan); yang lewat ditandai merah.</li>'
        . '<li><b>Surat permohonan</b> (setelah Disetujui) — satu tombol menerbitkan nomor dan mengunduh berkas Word. Bila data berubah sesudahnya (termasuk nama/NIP Waka Hubin di Pengaturan) muncul tanda <b>Perlu cetak ulang</b>; nomor tetap sama. Tanda tangan dan stempel Waka Hubin dibubuhkan basah di kertas hasil cetak (ruangnya sengaja dikosongkan).</li>'
        . '</ul>',
]) ?>

<div x-data="{ dlg: '' }" @keydown.escape.window="dlg = ''">

    <?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

    <!-- Kepala -->
    <div class="rise rise-1 mb-5 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <a href="<?= site_url('admin/pkl/daftar/' . $status) ?>" class="text-xs font-semibold text-brand-700 hover:underline">← Kembali ke daftar <?= esc(['menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perbaikan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'][$status] ?? '') ?></a>
        <div class="mt-2 flex flex-wrap items-center gap-2.5">
            <h2 class="text-xl font-extrabold text-slate-800"><?= esc($a['perusahaan_nama']) ?></h2>
            <?= view('admin/pkl/_lencana', ['kode' => $fase ?? $status]) ?>
            <?php if ($a['sumber'] !== 'siswa'): ?><span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700"><?= $a['sumber'] === 'staf' ? 'Diisi staf' : 'Impor data lama' ?></span><?php endif; ?>
            <?php if ((int) $a['kirim_ke'] > 1): ?><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Revisi ke-<?= (int) $a['kirim_ke'] - 1 ?></span><?php endif; ?>
        </div>
        <p class="mt-1 text-xs text-slate-400"><span class="font-mono font-semibold text-slate-500"><?= esc($kode) ?></span> · dikirim <?= esc(date('d-m-Y H:i', strtotime($a['created_at']))) ?> · diperbarui <?= esc(date('d-m-Y H:i', strtotime($a['updated_at']))) ?></p>
        <?php if (! empty($a['catatan_staf']) && in_array($status, ['perbaikan', 'ditolak'], true)): ?>
            <p class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-700"><span class="font-bold">Catatan untuk siswa:</span> <?= esc($a['catatan_staf']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Batas keputusan Waka Hubin -->
    <?php if ($status === 'menunggu' && ($sisaHari ?? null) !== null): ?>
        <div class="rise mb-5 flex flex-col gap-1 rounded-xl border px-4 py-3 text-sm sm:flex-row sm:items-center sm:gap-3 <?= $sisaHari < 0 ? 'border-red-300 bg-red-50 text-red-800' : ($sisaHari <= 1 ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-blue-200 bg-blue-50 text-blue-900') ?>">
            <span class="font-extrabold"><?= $sisaHari < 0 ? 'TERLAMBAT ' . abs($sisaHari) . ' hari' : ($sisaHari === 0 ? 'HARI INI batasnya' : 'Sisa ' . $sisaHari . ' hari') ?></span>
            <span>Batas keputusan Waka Hubin: <b><?= esc(IsianBantu::tanggalIndo(substr((string) $batasKeputusan, 0, 10))) ?></b> (<?= (int) $batasHari ?> hari sejak dikirim siswa <?= esc(date('d-m-Y H:i', strtotime((string) ($a['diajukan_at'] ?? $a['created_at'])))) ?>).</span>
        </div>
    <?php endif; ?>

    <!-- Peringatan otomatis -->
    <?php if ($peringatan !== []): ?>
        <div class="mb-5 space-y-2">
            <?php foreach ($peringatan as $w): [$kelas, $tanda, $titik] = $warnaPeringatan[$w['tingkat']]; ?>
                <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $kelas ?>">
                    <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-extrabold tracking-wide text-white <?= $titik ?>"><?= $tanda ?></span>
                    <span class="leading-relaxed"><?= esc($w['teks']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rise rise-2 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <!-- Kolom kiri: data -->
        <div class="space-y-5 lg:col-span-2">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Perusahaan</h3>
                <dl class="divide-y divide-slate-100">
                    <?= $baris('Nama', $a['perusahaan_nama']) ?>
                    <?= $baris('Alamat', $a['perusahaan_alamat']) ?>
                    <?= $baris('Kota/Kab.', $a['perusahaan_kota']) ?>
                    <?= $baris('Telepon', $a['perusahaan_telepon']) ?>
                    <?= $baris('Pimpinan/Kontak', $a['kontak_nama']) ?>
                    <?= $baris('Jabatan', $a['kontak_jabatan']) ?>
                    <div class="grid grid-cols-[7.5rem_1fr] gap-3 px-5 py-2.5 text-sm">
                        <dt class="text-slate-500">Master</dt>
                        <dd class="font-semibold">
                            <?php if (! empty($a['master_nama'])): ?>
                                <span class="text-green-700">✓ Terdaftar: <?= esc($a['master_nama']) ?></span>
                            <?php else: ?>
                                <span class="font-normal text-slate-400">Belum terdaftar — didaftarkan otomatis saat ACC</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </section>

            <?php if ($a['tanggal_mulai'] || $a['tanggal_selesai']): // tanggal tak ditanyakan lagi; hanya riwayat lama ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Periode PKL</h3>
                <dl class="divide-y divide-slate-100">
                    <?= $baris('Mulai', IsianBantu::tanggalIndo($a['tanggal_mulai'])) ?>
                    <?= $baris('Selesai', IsianBantu::tanggalIndo($a['tanggal_selesai'])) ?>
                    <?= $baris('Lama', $hari !== null ? $hari . ' hari (± ' . max(1, (int) round($hari / 30)) . ' bulan)' : '') ?>
                </dl>
            </section>
            <?php endif; ?>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siswa (<?= count($anggota) ?>)</h3>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($anggota as $s): ?>
                        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">
                                    <?= esc($s['nama']) ?>
                                    <span class="ml-1 rounded-full px-2 py-0.5 align-middle text-[10px] font-bold <?= $s['peran'] === 'pengaju' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' ?>"><?= $s['peran'] === 'pengaju' ? 'PENGAJU' : 'TEMAN' ?></span>
                                </p>
                                <p class="text-xs text-slate-500">Kelas <?= esc($s['nama_kelas'] ?? '—') ?> · NIS <?= esc($s['nis'] ?: '—') ?> · NISN <?= esc($s['nisn'] ?: '—') ?></p>
                            </div>
                            <p class="text-xs text-slate-500 sm:text-right">
                                HP <b class="text-slate-700"><?= esc(($s['hp'] ?? '') !== '' ? $s['hp'] : (($s['hp_master'] ?? '') !== '' ? $s['hp_master'] : '—')) ?></b>
                                <?php if (($s['hp'] ?? '') === '' && ($s['hp_master'] ?? '') !== ''): ?><br><span class="text-slate-400">(dari Master Siswa)</span><?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <!-- Kolom kanan: aksi + riwayat -->
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Keputusan</h3>
                <div class="mt-3 flex flex-col gap-2">
                    <?php if ($bolehAcc && in_array($status, ['menunggu', 'perbaikan', 'ditolak'], true)): ?>
                        <button type="button" @click="dlg = 'acc'" class="rounded-xl bg-green-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 active:scale-95"><?= $status === 'ditolak' ? '✓ ACC (hidupkan kembali)' : '✓ ACC' ?></button>
                    <?php endif; ?>
                    <?php if ($status === 'menunggu'): ?>
                        <button type="button" @click="dlg = 'kembalikan'" class="rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 active:scale-95">↩ Kembalikan untuk diperbaiki</button>
                    <?php endif; ?>
                    <?php if ($bolehAcc && in_array($status, ['menunggu', 'perbaikan'], true)): ?>
                        <button type="button" @click="dlg = 'tolak'" class="rounded-xl border-2 border-red-300 px-4 py-2.5 text-sm font-bold text-red-600 transition hover:bg-red-50 active:scale-95">✕ Tolak</button>
                    <?php endif; ?>
                    <?php if ($bolehAcc && $status === 'disetujui'): ?>
                        <button type="button" @click="dlg = 'batal'" class="rounded-xl border-2 border-amber-300 px-4 py-2.5 text-sm font-bold text-amber-700 transition hover:bg-amber-50 active:scale-95">↩ Batalkan persetujuan</button>
                    <?php endif; ?>
                    <?php if ($status !== 'disetujui' || $bolehAcc): ?>
                        <a href="<?= site_url('admin/pkl/' . $id . '/ubah') ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">✎ Ubah data langsung</a>
                    <?php endif; ?>
                    <?php if (! $bolehAcc): ?>
                        <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-600">🔒 <b>ACC, tolak, dan batalkan persetujuan hanya dilakukan Waka Hubin.</b> Anda bisa memeriksa, mengembalikan ajuan untuk diperbaiki<?= $status === 'disetujui' ? '' : ', dan mengubah datanya' ?>, serta mencetak surat setelah disetujui.</p>
                    <?php endif; ?>
                    <?php if ($bolehHapus && ($status !== 'disetujui' || $peran === 'admin')): ?>
                        <button type="button" @click="dlg = 'hapus'" class="mt-1 rounded-xl px-4 py-2 text-xs font-semibold text-slate-400 transition hover:bg-red-50 hover:text-red-600">Hapus ajuan ini</button>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($status === 'disetujui' && ! empty($a['acc_at'])): $peranAcc = (string) ($a['acc_peran'] ?? ''); ?>
                <section class="rounded-2xl border border-green-200 bg-green-50/60 p-5 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-green-800">Persetujuan (ACC)</h3>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div><dt class="text-xs text-slate-500">Disetujui oleh</dt><dd class="font-semibold text-slate-800"><?= esc($a['acc_nama'] ?: '—') ?></dd></div>
                        <div><dt class="text-xs text-slate-500">Sebagai</dt><dd class="font-semibold text-slate-800"><?= esc(match ($peranAcc) {
                            'hubin' => 'Waka Hubin', 'admin' => 'Admin — mewakili Waka Hubin', 'operator' => 'Operator (tanpa wewenang ACC; data lama)', 'impor' => 'Data riwayat (diimpor, bukan ACC sistem)', default => 'Tidak tercatat',
                        }) ?></dd></div>
                        <div><dt class="text-xs text-slate-500">Waktu</dt><dd class="font-semibold text-slate-800"><?= esc(PklSurat::waktuIndo((string) $a['acc_at'])) ?></dd></div>
                        <?php if (! empty($a['acc_kode'])): ?><div><dt class="text-xs text-slate-500">Kode verifikasi</dt><dd class="font-mono text-xs font-bold text-slate-700"><?= esc($a['acc_kode']) ?></dd></div><?php endif; ?>
                    </dl>
                    <?php if ($peranAcc !== 'hubin'): ?>
                        <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold leading-relaxed text-amber-900">
                            <?= $peranAcc === 'admin' ? 'ACC ini dilakukan Admin sebagai pengganti Waka Hubin. Tanda tangan digital Waka Hubin TIDAK dipasang di surat; kaki surat menjelaskan hal ini.' : 'Persetujuan ini bukan dari Waka Hubin lewat sistem. Mohon dikonfirmasi ke Waka Hubin.' ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($status === 'disetujui'): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Surat permohonan</h3>
                    <?php if (trim((string) ($p['waka_hubin_nama'] ?? '')) === ''): ?>
                        <p class="mt-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs font-semibold leading-relaxed text-red-800">⚠ Nama Waka Hubin belum diisi, jadi di bawah tanda tangan surat hanya titik-titik.
                            <?php if ($bolehPengaturan): ?><a href="<?= site_url('admin/pkl/pengaturan#surat') ?>" class="underline">Isi di Pengaturan PKL</a>, lalu unduh surat lagi (nomor tetap sama).<?php else: ?>Minta Operator mengisinya di Pengaturan PKL.<?php endif; ?></p>
                    <?php endif; ?>
                    <?php if ($surat): ?>
                        <p class="mt-2 text-xs text-slate-500">Nomor surat</p>
                        <p class="font-mono text-sm font-bold text-slate-800"><?= esc($surat['nomor']) ?></p>
                        <p class="mt-1 text-xs text-slate-400">Tanggal <?= esc(IsianBantu::tanggalIndo($surat['tanggal_surat'])) ?> · diunduh <?= (int) $surat['cetak_ke'] ?> kali</p>
                        <?php if ($perluUlang): ?>
                            <p class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold leading-relaxed text-amber-900">⚠ PERLU CETAK ULANG — data ajuan berubah sejak surat terakhir diunduh. Nomor tetap sama.</p>
                        <?php endif; ?>
                        <form method="post" action="<?= site_url('admin/pkl/' . $id . '/surat') ?>" data-unduh class="mt-3">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full rounded-xl <?= $perluUlang ? 'bg-amber-500 hover:bg-amber-600' : 'bg-brand-700 hover:bg-brand-800' ?> px-4 py-3 text-sm font-bold text-white shadow-sm transition active:scale-95">⬇ <?= $perluUlang ? 'Unduh surat terbaru (.docx)' : 'Unduh surat (.docx)' ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= site_url('admin/pkl/' . $id . '/surat') ?>" data-unduh class="mt-2 space-y-2">
                            <?= csrf_field() ?>
                            <label class="lbl" for="tgl_surat">Tanggal surat</label>
                            <input id="tgl_surat" type="date" name="tanggal_surat" value="<?= date('Y-m-d') ?>" class="inp">
                            <button type="submit" class="w-full rounded-xl bg-brand-700 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">⬇ Terbitkan nomor &amp; unduh surat</button>
                            <p class="text-xs leading-relaxed text-slate-400">Nomor ditetapkan sekali. Mengunduh lagi nanti memakai nomor yang sama.</p>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Riwayat</h3>
                <?php if ($riwayat === []): ?>
                    <p class="px-5 py-6 text-center text-sm text-slate-400">Belum ada catatan.</p>
                <?php else: ?>
                    <ol class="space-y-0 px-5 py-4">
                        <?php foreach ($riwayat as $i => $r): ?>
                            <li class="relative pb-4 pl-5 last:pb-0">
                                <span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full <?= in_array($r['aksi'], ['acc'], true) ? 'bg-green-500' : (in_array($r['aksi'], ['tolak', 'batal_acc'], true) ? 'bg-red-500' : (in_array($r['aksi'], ['kembalikan'], true) ? 'bg-amber-500' : 'bg-brand-500')) ?>"></span>
                                <?php if ($i < count($riwayat) - 1): ?><span class="absolute left-[4px] top-4 h-full w-px bg-slate-200"></span><?php endif; ?>
                                <p class="text-sm font-semibold text-slate-700"><?= esc($labelAksi[$r['aksi']] ?? $r['aksi']) ?></p>
                                <p class="text-xs text-slate-400"><?= esc($r['oleh']) ?><?= $r['peran'] ? ' · ' . esc(HakAkses::label($r['peran'])) : '' ?> · <?= esc(date('d-m-Y H:i', strtotime($r['created_at']))) ?></p>
                                <?php if (! empty($r['catatan'])): ?><p class="mt-0.5 text-xs text-slate-500">“<?= esc($r['catatan']) ?>”</p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- ============ Dialog-dialog keputusan ============ -->
    <?php
    $dialog = static function (string $kunci, string $judul, string $aksiUrl, string $isi, string $tombol, string $warnaTombol) use ($id): void {
        echo '<div x-show="dlg === \'' . $kunci . '\'" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true">'
            . '<div class="absolute inset-0 bg-slate-900/50" @click="dlg = \'\'"></div>'
            . '<form method="post" action="' . esc(site_url('admin/pkl/' . $aksiUrl), 'attr') . '" class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">'
            . csrf_field()
            . '<div class="border-b border-slate-100 px-5 py-4"><h3 class="text-lg font-bold text-slate-800">' . $judul . '</h3></div>'
            . '<div class="space-y-4 p-5 text-sm text-slate-600">' . $isi . '</div>'
            . '<div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end">'
            . '<button type="button" @click="dlg = \'\'" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>'
            . '<button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition active:scale-95 ' . $warnaTombol . '">' . $tombol . '</button>'
            . '</div></form></div>';
    };
    $kotakAlasan = static fn (string $ph): string => '<div><label class="lbl" for="alasan">Alasan <span class="text-red-500">*</span></label>'
        . '<textarea id="alasan" name="catatan" rows="3" required minlength="5" maxlength="255" class="inp" placeholder="' . esc($ph, 'attr') . '"></textarea>'
        . '<p class="mt-1 text-xs text-slate-400">Minimal 5 huruf. Siswa akan membaca catatan ini.</p></div>';

    // ----- ACC -----
    $isiAcc = '<p>Ajuan <b>' . esc($kode) . '</b> — ' . esc($a['perusahaan_nama']) . ' (' . count($anggota) . ' siswa) akan disetujui dan seluruh siswanya <b>terkunci</b> (tak bisa mengajukan lagi).</p>';
    if ($master['persis'] !== null) {
        $isiAcc .= '<p class="rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-green-800">Perusahaan ini sudah ada di master (<b>' . esc($master['persis']['nama']) . '</b>) — akan ditautkan otomatis.</p>';
    } elseif ($master['mirip'] !== []) {
        $isiAcc .= '<fieldset class="rounded-xl border border-amber-200 bg-amber-50 p-3"><legend class="px-1 text-xs font-bold text-amber-800">Ada perusahaan terdaftar yang mirip — pilih:</legend>'
            . '<label class="mt-1 flex cursor-pointer items-start gap-2 text-slate-700"><input type="radio" name="perusahaan_id" value="" checked class="mt-0.5"><span><b>Daftarkan sebagai perusahaan baru</b><br><span class="text-xs text-slate-500">' . esc($a['perusahaan_nama']) . '</span></span></label>';
        foreach ($master['mirip'] as $m) {
            $isiAcc .= '<label class="mt-2 flex cursor-pointer items-start gap-2 text-slate-700"><input type="radio" name="perusahaan_id" value="' . (int) $m['id'] . '" class="mt-0.5"><span><b>Sama dengan:</b> ' . esc($m['nama']) . '<br><span class="text-xs text-slate-500">' . esc(trim(($m['kota'] ?? '') . ' ' . ($m['alamat'] ?? ''))) . '</span></span></label>';
        }
        $isiAcc .= '</fieldset>';
    } else {
        $isiAcc .= '<p class="text-xs text-slate-500">Perusahaan ini akan didaftarkan ke master dan menjadi saran nama untuk siswa lain — pastikan ejaannya sudah benar.</p>';
    }
    if ($adaBahaya) {
        $isiAcc .= '<label class="flex cursor-pointer items-start gap-2 rounded-xl border border-red-300 bg-red-50 p-3 text-red-800"><input type="checkbox" name="paham" value="1" required class="mt-0.5"><span class="text-sm font-semibold">Ada peringatan BAHAYA di halaman ini. Saya sudah memeriksanya dan tetap ingin meng-ACC.</span></label>';
    }
    $isiAcc .= '<p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs leading-relaxed text-slate-600">Persetujuan ini akan <b>tercatat atas nama ' . esc($namaSaya) . '</b> (' . esc(HakAkses::label($peran)) . ') lengkap dengan tanggal &amp; jam, dan tercetak di kaki surat permohonan.</p>';
    if ($peran === 'admin') {
        $isiAcc .= '<label class="flex cursor-pointer items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-amber-900"><input type="checkbox" name="wakil" value="1" required class="mt-0.5"><span class="text-sm font-semibold">Saya meng-ACC sebagai <b>Admin yang mewakili Waka Hubin</b> (Waka Hubin berhalangan). Ini akan tertulis jelas di surat.</span></label>';
    }
    $dialog('acc', 'ACC ajuan ' . esc($kode) . '?', $id . '/acc', $isiAcc, '✓ Ya, ACC', 'bg-green-600 hover:bg-green-700');

    $dialog('kembalikan', 'Kembalikan untuk diperbaiki', $id . '/kembalikan',
        '<p>Siswa pengaju akan membuka ajuannya lagi (dengan nomor HP-nya) dan memperbaikinya, lalu mengirim ulang untuk diputuskan Waka Hubin.</p>' . $kotakAlasan('Contoh: Alamat perusahaan belum lengkap, tambahkan nomor gedung dan kode pos.'),
        '↩ Kembalikan', 'bg-amber-500 hover:bg-amber-600');
    $dialog('tolak', 'Tolak ajuan ' . esc($kode), $id . '/tolak',
        '<p>Seluruh siswa dalam ajuan ini <b>dibebaskan</b> dan boleh mengajukan baru dari awal.</p>' . $kotakAlasan('Contoh: Perusahaan tidak menerima siswa PKL pada periode ini.'),
        '✕ Tolak', 'bg-red-600 hover:bg-red-700');
    $dialog('batal', 'Batalkan persetujuan ' . esc($kode), $id . '/batal-acc',
        '<p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-amber-900">Gunakan bila <b>perusahaan menarik diri</b>. Ajuan kembali ke status <b>Perlu perbaikan</b> supaya siswa bisa menggantinya; siswa tetap terkunci sampai mereka memperbaiki atau Anda menolak.</p>' . $kotakAlasan('Contoh: Perusahaan membatalkan penerimaan karena kuota penuh.'),
        'Ya, batalkan persetujuan', 'bg-amber-500 hover:bg-amber-600');
    if ($bolehHapus) {
        $isiHapus = '<p>Ajuan <b>' . esc($kode) . '</b> beserta riwayatnya akan <b>dihapus permanen</b> dan seluruh siswanya dibebaskan. Ringkasannya tetap tercatat di Audit Log.</p><p class="text-xs text-slate-500">Bila ajuan hanya salah/ditolak, lebih aman memakai <b>Tolak</b> agar jejaknya tetap ada.</p>';
        if ($status === 'disetujui') {
            $isiHapus .= '<label class="flex cursor-pointer items-start gap-2 rounded-xl border border-red-300 bg-red-50 p-3 text-red-800"><input type="checkbox" name="paham" value="1" required class="mt-0.5"><span class="text-sm font-semibold">Ajuan ini SUDAH DISETUJUI. Saya yakin ingin menghapusnya.</span></label>';
        }
        $dialog('hapus', 'Hapus ajuan ' . esc($kode) . '?', 'hapus/' . $id, $isiHapus, 'Hapus permanen', 'bg-red-600 hover:bg-red-700');
    }
    ?>
</div>

<?= $this->endSection() ?>
