<?php
/**
 * Detail satu surat sekolah: data, siswa, status & ACC, tindakan (ACC / kembalikan / ajukan ulang / unduh / batalkan), riwayat.
 *
 * @var array<string, mixed>           $surat       baris surat_sekolah + 'isi_arr'
 * @var list<array>                    $siswa
 * @var list<array>                    $riwayat
 * @var string                         $kode        mis. SRT-00012
 * @var bool                           $perluUlang  data berubah sejak terakhir diunduh
 * @var list<array{0:string,1:string}> $ringkasIsi
 * @var list<array{tingkat:string,teks:string}> $periksa peringatan sebelum ACC / unduh
 * @var bool                           $templateAda berkas template Word jenis ini sudah ada
 * @var bool                           $bisaBatalAcc persetujuan masih bisa dibatalkan (belum bernomor / diunduh)
 * @var bool                           $bolehAcc    peran saya berhak ACC
 * @var bool                           $bolehBuat   peran saya berhak mengelola surat sekolah
 * @var string                         $namaSaya
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklSurat;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratSekolah;
use App\Models\SuratSekolahModel;

[$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$surat['status']] ?? [$surat['status'], 'bg-slate-100 text-slate-600'];
$id       = (int) $surat['id'];
$jenis    = (string) $surat['jenis'];
$status   = (string) $surat['status'];
$wajibAcc = (int) $surat['perlu_acc'] === 1;
$bernomor = (string) $surat['nomor'] !== '';
$adaBahaya = SuratPeriksa::adaBahaya($periksa);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_detail_v2',
    'helpTitle' => 'Detail Surat',
    'helpBody'  => '<p>Halaman ini menampilkan <b>isi dan sejarah satu surat</b> dan tombol tindakannya.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>ACC / Kembalikan</b> (khusus yang berhak ACC, bawaan Waka Hubin): ACC mencatat nama, jam, dan kode verifikasi yang ikut tercetak di kaki surat. <i>Kembalikan</i> mengirim surat kembali ke pembuatnya beserta alasan.</li>'
        . '<li><b>Unduh surat</b> (khusus yang berhak mengelola surat, bawaan Operator): nomor surat diterbitkan <b>sekali</b>, saat surat pertama diunduh, satu urutan dengan Surat Izin PKL. Unduh ulang memakai nomor yang sama.</li>'
        . '<li>Bila setelah diunduh data surat atau penanda tangan berubah, muncul tanda <b>perlu cetak ulang</b> &mdash; nomornya tetap sama.</li>'
        . '<li><b>Batalkan surat</b>: surat tidak jadi dipakai. Nomor yang sudah terbit tetap tercatat dan tidak dipakai lagi.</li>'
        . '</ul>',
]) ?>

<div x-data="{ dlg: '' }">

<a href="<?= site_url('admin/surat') ?>" class="rise mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">← Daftar Surat</a>

<div class="rise mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc(SuratJenis::badge($jenis), 'attr') ?>"><?= esc(SuratJenis::label($jenis)) ?></span>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
            <span class="font-mono text-xs font-semibold text-slate-400"><?= esc($kode) ?></span>
        </div>
        <h1 class="mt-2 text-xl font-bold leading-snug text-slate-800"><?= esc($surat['judul']) ?></h1>
    </div>
</div>

<?php if ($perluUlang): ?>
    <p class="rise mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold leading-relaxed text-amber-900">⚠ PERLU CETAK ULANG &mdash; data surat atau penanda tangan berubah sejak surat terakhir diunduh. Nomor tetap sama.</p>
<?php endif; ?>
<?php if ($status === 'dikembalikan' && (string) $surat['catatan_staf'] !== ''): ?>
    <p class="rise mb-4 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm leading-relaxed text-orange-900"><b>Dikembalikan dengan catatan:</b> <?= esc($surat['catatan_staf']) ?></p>
<?php endif; ?>
<?php if ($status === 'dibatalkan'): ?>
    <p class="rise mb-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm leading-relaxed text-slate-700"><b>Surat dibatalkan.</b> <?= (string) $surat['catatan_staf'] !== '' ? esc($surat['catatan_staf']) : '' ?><?= $bernomor ? ' Nomor ' . esc($surat['nomor']) . ' tetap tercatat dan tidak dipakai lagi.' : '' ?></p>
<?php endif; ?>
<?php foreach ($periksa as $w): $bahaya = $w['tingkat'] === 'bahaya'; ?>
    <p class="rise mb-3 rounded-xl border px-4 py-3 text-sm font-semibold leading-relaxed <?= $bahaya ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-900' ?>">⚠ <?= $bahaya ? 'BAHAYA: ' : '' ?><?= esc($w['teks']) ?>
        <?php if (str_contains($w['teks'], 'Pengaturan PKL') && $bolehPengaturan): ?><a href="<?= site_url('admin/pkl/pengaturan#surat') ?>" class="underline">Isi di Pengaturan PKL</a>, lalu unduh surat lagi (nomor tetap sama).<?php endif; ?></p>
<?php endforeach; ?>

<div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
    <div class="space-y-5">
        <!-- Data surat -->
        <section class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Data surat</h3>
            <dl class="grid gap-x-6 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Nomor surat</dt><dd class="font-mono font-bold text-slate-800"><?= $bernomor ? esc($surat['nomor']) : '<span class="font-sans font-normal text-slate-400">' . (SuratJenis::bernomor($jenis) ? 'belum diterbitkan' : 'jenis ini tidak bernomor') . '</span>' ?></dd></div>
                <div><dt class="text-xs text-slate-500">Tanggal surat</dt><dd class="font-semibold text-slate-800"><?= esc(IsianBantu::tanggalIndo((string) $surat['tanggal_surat'])) ?></dd></div>
                <?php if ((string) $surat['perusahaan_nama'] !== ''): ?>
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Perusahaan / tujuan</dt><dd class="font-semibold text-slate-800"><?= esc($surat['perusahaan_nama']) ?>
                        <?php if (! empty($surat['pengajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . (int) $surat['pengajuan_id']) ?>" class="ml-1 text-xs font-semibold text-brand-700 hover:underline">lihat ajuan PKL →</a><?php endif; ?></dd></div>
                <?php endif; ?>
                <?php foreach ($ringkasIsi as [$label, $nilai]): ?>
                    <div><dt class="text-xs text-slate-500"><?= esc($label) ?></dt><dd class="font-semibold text-slate-800"><?= esc($nilai) ?></dd></div>
                <?php endforeach; ?>
                <div><dt class="text-xs text-slate-500">Dibuat oleh</dt><dd class="font-semibold text-slate-800"><?= esc((string) $surat['dibuat_nama'] !== '' ? $surat['dibuat_nama'] : '—') ?></dd><dd class="text-xs text-slate-400"><?= esc(date('d-m-Y H:i', strtotime((string) $surat['created_at']))) ?></dd></div>
            </dl>
        </section>

        <!-- Siswa -->
        <?php if ($siswa !== []): ?>
            <section class="rise rise-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siswa pada surat <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] text-slate-600"><?= count($siswa) ?></span></h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead class="text-left text-slate-500">
                            <tr class="border-b border-slate-100">
                                <th class="w-12 px-5 py-2.5 font-semibold">No</th>
                                <th class="px-3 py-2.5 font-semibold">Nama</th>
                                <th class="px-3 py-2.5 font-semibold">NIS</th>
                                <th class="px-3 py-2.5 font-semibold">Kelas</th>
                                <th class="px-3 py-2.5 font-semibold">Konsentrasi keahlian</th>
                                <th class="px-5 py-2.5 font-semibold">HP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($siswa as $i => $a):
                                $hp = trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? '');
                            ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-3 py-2.5 font-semibold text-slate-800"><?= esc($a['nama']) ?></td>
                                    <td class="px-3 py-2.5 font-mono text-xs text-slate-600"><?= esc((string) $a['nis'] !== '' ? $a['nis'] : '—') ?></td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-slate-600"><?= esc((string) ($a['nama_kelas'] ?? '') !== '' ? $a['nama_kelas'] : '—') ?></td>
                                    <td class="px-3 py-2.5 text-slate-600"><?= esc((string) ($a['jurusan_nama'] ?? '') !== '' ? $a['jurusan_nama'] : '—') ?></td>
                                    <td class="whitespace-nowrap px-5 py-2.5 font-mono text-xs text-slate-600"><?= $hp !== '' ? esc($hp) : '<span class="text-slate-300">—</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="space-y-5">
        <!-- Tindakan -->
        <?php if ($status !== 'dibatalkan'): ?>
            <section class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Tindakan</h3>
                <div class="space-y-3 px-5 py-5 text-sm">
                    <?php if ($status === 'menunggu'): ?>
                        <?php if ($bolehAcc): ?>
                            <button type="button" @click="dlg = 'acc'" class="w-full rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-green-700 active:scale-95">✓ ACC surat ini</button>
                            <button type="button" @click="dlg = 'kembalikan'" class="w-full rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-800 transition hover:bg-amber-100 active:scale-95">↩ Kembalikan untuk diperbaiki</button>
                        <?php else: ?>
                            <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-600">🔒 Surat ini menunggu <b>ACC</b> dari peran yang berhak (bawaan: Waka Hubin). Akun <?= esc(HakAkses::label($peran)) ?> tidak punya hak ACC.</p>
                        <?php endif; ?>
                    <?php elseif ($status === 'dikembalikan'): ?>
                        <?php if ($bolehBuat): ?>
                            <form method="post" action="<?= site_url('admin/surat/' . $id . '/ajukan-ulang') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="w-full rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">Ajukan ulang untuk ACC</button>
                            </form>
                            <p class="text-xs leading-relaxed text-slate-500">Perbaiki dulu sesuai catatan di atas bila perlu, lalu ajukan ulang. Riwayatnya tercatat.</p>
                        <?php else: ?>
                            <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-600">Menunggu pembuat surat memperbaiki dan mengajukan ulang.</p>
                        <?php endif; ?>
                    <?php elseif ($status === 'disetujui'): ?>
                        <?php if ($bolehBuat): ?>
                            <?php if ($templateAda): ?>
                                <form method="post" action="<?= site_url('admin/surat/' . $id . '/unduh') ?>" data-unduh>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="w-full rounded-xl bg-brand-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-95">⬇ <?= (int) $surat['cetak_ke'] > 0 ? 'Unduh ulang surat (Word)' : 'Unduh surat (Word)' ?></button>
                                </form>
                                <p class="text-xs leading-relaxed text-slate-500"><?= $bernomor || ! SuratJenis::bernomor($jenis) ? 'Unduh ulang memakai nomor yang sama.' : 'Nomor surat diterbitkan sekali, saat surat pertama diunduh.' ?></p>
                            <?php else: ?>
                                <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-600">Template Word untuk <?= esc(SuratJenis::label($jenis)) ?> belum tersedia, jadi surat ini belum bisa diunduh.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-600">🔒 Surat diunduh oleh peran yang diberi hak itu oleh Admin (bawaan: Operator Sekolah), supaya jelas siapa yang menyetujui dan siapa yang mencetak.</p>
                        <?php endif; ?>
                        <?php if ($bisaBatalAcc && $bolehAcc): ?>
                            <button type="button" @click="dlg = 'batalacc'" class="w-full rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-50">Batalkan persetujuan</button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($bolehBuat && $alamatUbah !== ''): ?>
                        <a href="<?= esc($alamatUbah, 'attr') ?>" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-center text-xs font-bold text-slate-700 transition hover:bg-slate-50">✎ Ubah data surat</a>
                    <?php endif; ?>
                    <?php if ($bolehBuat): ?>
                        <button type="button" @click="dlg = 'batal'" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700">Batalkan surat</button>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Status & ACC -->
        <section class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Persetujuan &amp; unduhan</h3>
            <div class="space-y-4 px-5 py-5 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Aturan jenis ini</p>
                    <p class="font-semibold text-slate-800"><?= $wajibAcc ? 'Wajib ACC sebelum diunduh' : 'Tanpa ACC (langsung siap unduh)' ?></p>
                </div>
                <?php if (! empty($surat['acc_at'])): ?>
                    <div>
                        <p class="text-xs text-slate-500">Disetujui oleh</p>
                        <p class="font-semibold text-slate-800"><?= esc((string) $surat['acc_nama']) ?></p>
                        <p class="text-xs text-slate-500"><?= esc(HakAkses::label((string) $surat['acc_peran'])) ?><?= (string) $surat['acc_peran'] === 'admin' ? ' (mewakili Waka Hubin)' : '' ?></p>
                        <p class="text-xs text-slate-400"><?= esc(PklSurat::waktuIndo((string) $surat['acc_at'])) ?></p>
                        <?php if ((string) $surat['acc_kode'] !== ''): ?><p class="mt-1 font-mono text-xs font-semibold text-slate-600"><?= esc($surat['acc_kode']) ?></p><?php endif; ?>
                    </div>
                <?php elseif ($status === 'menunggu'): ?>
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold leading-relaxed text-amber-800">Menunggu persetujuan<?= (string) $surat['diajukan_at'] !== '' ? ' sejak ' . esc(date('d-m-Y H:i', strtotime((string) $surat['diajukan_at']))) : '' ?>.</p>
                <?php endif; ?>
                <div>
                    <p class="text-xs text-slate-500">Diunduh</p>
                    <p class="font-semibold text-slate-800"><?= (int) $surat['cetak_ke'] > 0 ? (int) $surat['cetak_ke'] . ' kali' : 'belum pernah' ?></p>
                    <?php if (! empty($surat['terakhir_cetak_at'])): ?><p class="text-xs text-slate-400">terakhir <?= esc(date('d-m-Y H:i', strtotime((string) $surat['terakhir_cetak_at']))) ?></p><?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Riwayat -->
        <section class="rise rise-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Riwayat</h3>
            <?php if ($riwayat === []): ?>
                <p class="px-5 py-5 text-center text-sm text-slate-400">Belum ada riwayat.</p>
            <?php else: ?>
                <ol class="divide-y divide-slate-100">
                    <?php foreach ($riwayat as $h): ?>
                        <li class="px-5 py-3 text-sm">
                            <p class="font-semibold text-slate-800"><?= esc(SuratSekolah::AKSI[$h['aksi']] ?? $h['aksi']) ?></p>
                            <?php if ((string) $h['catatan'] !== ''): ?><p class="text-xs leading-relaxed text-slate-500"><?= esc($h['catatan']) ?></p><?php endif; ?>
                            <p class="mt-0.5 text-xs text-slate-400"><?= esc($h['oleh']) ?><?= (string) $h['peran'] !== '' ? ' (' . esc(HakAkses::label((string) $h['peran'])) . ')' : '' ?> · <?= esc(date('d-m-Y H:i', strtotime((string) $h['created_at']))) ?></p>
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
        . '<form method="post" action="' . esc(site_url('admin/surat/' . $id . '/' . $aksiUrl), 'attr') . '" class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">'
        . csrf_field()
        . '<div class="border-b border-slate-100 px-5 py-4"><h3 class="text-lg font-bold text-slate-800">' . $judul . '</h3></div>'
        . '<div class="space-y-4 p-5 text-sm text-slate-600">' . $isi . '</div>'
        . '<div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end">'
        . '<button type="button" @click="dlg = \'\'" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>'
        . '<button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition active:scale-95 ' . $warnaTombol . '">' . $tombol . '</button>'
        . '</div></form></div>';
};
$kotakAlasan = static fn (string $ph, string $untuk): string => '<div><label class="lbl" for="alasan_' . $untuk . '">Alasan <span class="text-red-500">*</span></label>'
    . '<textarea id="alasan_' . $untuk . '" name="catatan" rows="3" required minlength="5" maxlength="255" class="inp" placeholder="' . esc($ph, 'attr') . '"></textarea>'
    . '<p class="mt-1 text-xs text-slate-400">Minimal 5 huruf. Tercatat di riwayat surat.</p></div>';

if ($status === 'menunggu' && $bolehAcc) {
    $isiAcc = '<p>Surat <b>' . esc($kode) . '</b> — ' . esc($surat['judul']) . ' akan disetujui dan siap diunduh oleh yang berhak (nomor surat terbit saat pertama diunduh).</p>';
    if ($adaBahaya) {
        $isiAcc .= '<label class="flex cursor-pointer items-start gap-2 rounded-xl border border-red-300 bg-red-50 p-3 text-red-800"><input type="checkbox" name="paham" value="1" required class="mt-0.5"><span class="text-sm font-semibold">Ada peringatan BAHAYA di halaman ini. Saya sudah memeriksanya dan tetap ingin meng-ACC.</span></label>';
    }
    $isiAcc .= '<p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs leading-relaxed text-slate-600">Persetujuan ini akan <b>tercatat atas nama ' . esc($namaSaya) . '</b> (' . esc(HakAkses::label($peran)) . ') lengkap dengan tanggal &amp; jam, dan tercetak di kaki surat.</p>';
    if ($peran === 'admin') {
        $isiAcc .= '<label class="flex cursor-pointer items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-amber-900"><input type="checkbox" name="wakil" value="1" required class="mt-0.5"><span class="text-sm font-semibold">Saya meng-ACC sebagai <b>Admin yang mewakili Waka Hubin</b> (Waka Hubin berhalangan). Ini akan tertulis jelas di surat.</span></label>';
    }
    $dialog('acc', 'ACC surat ' . esc($kode) . '?', 'acc', $isiAcc, '✓ Ya, ACC', 'bg-green-600 hover:bg-green-700');
    $dialog('kembalikan', 'Kembalikan untuk diperbaiki', 'kembalikan',
        '<p>Surat kembali ke pembuatnya untuk diperbaiki, lalu diajukan ulang kepada Anda.</p>' . $kotakAlasan('Contoh: Tanggal pelaksanaan belum sesuai kalender ujian.', 'kembalikan'),
        '↩ Kembalikan', 'bg-amber-500 hover:bg-amber-600');
}
if ($status === 'disetujui' && $bisaBatalAcc && $bolehAcc) {
    $dialog('batalacc', 'Batalkan persetujuan ' . esc($kode), 'batal-acc',
        '<p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-amber-900">Surat kembali ke status <b>Dikembalikan</b> supaya diperbaiki, lalu perlu di-ACC lagi. Hanya bisa selama surat belum bernomor dan belum diunduh.</p>' . $kotakAlasan('Contoh: Ada siswa yang salah masuk daftar.', 'batalacc'),
        'Ya, batalkan persetujuan', 'bg-amber-500 hover:bg-amber-600');
}
if ($bolehBuat && in_array($status, ['menunggu', 'dikembalikan', 'disetujui'], true)) {
    $dialog('batal', 'Batalkan surat ' . esc($kode) . '?', 'batal',
        '<p>Surat ini <b>tidak jadi dipakai</b> dan tidak bisa diunduh lagi.' . ($bernomor ? ' Nomor <b>' . esc($surat['nomor']) . '</b> tetap tercatat dan tidak dipakai lagi.' : '') . ' Jejaknya tetap ada di riwayat dan Daftar Surat.</p>' . $kotakAlasan('Contoh: Dibuat dua kali, dipakai surat yang satunya.', 'batal'),
        'Ya, batalkan surat', 'bg-red-600 hover:bg-red-700');
}
?>

</div>

<?= $this->endSection() ?>
