<?php
/**
 * Detail satu surat sekolah: data, siswa, status & ACC, tindakan (ACC / kembalikan / ajukan ulang / unduh / batalkan), riwayat.
 * Rombak tampilan F9: kepala halaman layout + tombol Kembali, kartu kit (kepala surat, Data surat, Siswa, Tindakan, Persetujuan,
 * Riwayat), ui_peringatan, lencana kit, tombol kit ber-ikon (pengganti emoji). Siap CSP: dialog keputusan = dialog kit
 * (ui_dialog_buka, dibuka data-buka-dialog) — dulu x-data akar { dlg } + x-show per dialog; Kabari WA = data-kabari-surat
 * (dulu @click window.dispatchEvent).
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
 * @var bool                           $bolehBuat   peran saya berhak membuat/mengubah/membatalkan surat JENIS ini
 * @var bool                           $bolehUnduh  peran saya berhak mengunduh surat jenis ini
 * @var bool                           $kabarWa     tombol "Kabari siswa via WhatsApp" (Surat Balasan, hak Unduh, status siap unduh)
 * @var array{total:int,dikabari:int}  $hitungWa    siswa pada surat & yang sudah dikabari
 * @var string                         $pemegangAcc / $pemegangUnduh  nama peran yang memegang hak itu (teks)
 * @var string                         $namaSaya
 * @var string                         $alamatUbah  alamat formulir ubah ('' = jenis ini tak bisa diubah)
 * @var string                         $peran
 * @var bool                           $bolehPengaturan
 */

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklSurat;
use App\Libraries\SuratJenis;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratSekolah;
use App\Models\SuratSekolahModel;

[$stLabel, $stKelas] = SuratSekolahModel::TAMPIL_STATUS[$surat['status']] ?? [$surat['status'], 'lencana-abu'];
$id       = (int) $surat['id'];
$jenis    = (string) $surat['jenis'];
$status   = (string) $surat['status'];
$wajibAcc = (int) $surat['perlu_acc'] === 1;
$bernomor = (string) $surat['nomor'] !== '';
$adaBahaya = SuratPeriksa::adaBahaya($periksa);
$kepalaKartu = static fn (string $judul, string $idJudul, string $tambahan = ''): string => '<div class="kartu-kepala"><h3 id="' . $idJudul . '" class="kartu-judul text-base">' . $judul . '</h3>' . $tambahan . '</div>';
$catatanKunci = static fn (string $html): string => '<div class="peringatan peringatan-netral">' . ikon('lock', 'mt-0.5 h-4 w-4 shrink-0') . '<p>' . $html . '</p></div>';
$dt = 'text-xs text-gray-500 dark:text-gray-400';
$dd = 'font-semibold text-gray-900 dark:text-white';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/surat') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Daftar Surat</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_detail_v2',
    'helpTitle' => 'Detail Surat',
    'helpBody'  => '<p>Halaman ini menampilkan <b>isi dan sejarah satu surat</b> dan tombol tindakannya.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>ACC / Kembalikan</b> (peran yang diberi hak ACC jenis surat ini oleh Admin di Sistem → Hak Akses): ACC mencatat nama, jam, dan kode verifikasi yang ikut tercetak di kaki surat. <i>Kembalikan</i> mengirim surat kembali ke pembuatnya beserta alasan.</li>'
        . '<li><b>Unduh surat</b> (peran yang diberi hak Unduh jenis surat ini): nomor surat diterbitkan <b>sekali</b>, saat surat pertama diunduh, satu urutan dengan Surat Izin PKL. Unduh ulang memakai nomor yang sama.</li>'
        . '<li>Bila setelah diunduh data surat atau penanda tangan berubah, muncul tanda <b>perlu cetak ulang</b> &mdash; nomornya tetap sama.</li>'
        . '<li><b>Batalkan surat</b>: surat tidak jadi dipakai. Nomor yang sudah terbit tetap tercatat dan tidak dipakai lagi.</li>'
        . '</ul>',
]) ?>

<div class="space-y-4">
    <!-- Kepala surat -->
    <section class="kartu kartu-isi" aria-labelledby="judul-surat">
        <div class="flex flex-wrap items-center gap-2">
            <span class="lencana <?= esc(SuratJenis::badge($jenis), 'attr') ?>"><?= esc(SuratJenis::label($jenis)) ?></span>
            <span class="lencana <?= esc($stKelas, 'attr') ?>"><?= esc($stLabel) ?></span>
            <span class="font-mono text-xs font-semibold text-gray-500 dark:text-gray-400"><?= esc($kode) ?></span>
        </div>
        <h2 id="judul-surat" class="mt-2 text-xl font-bold text-gray-900 dark:text-white"><?= esc($surat['judul']) ?></h2>
    </section>

    <?php if ($perluUlang): ?>
        <?= ui_peringatan('awas', '', ['html' => '<b>Perlu cetak ulang</b> &mdash; data surat atau penanda tangan berubah sejak surat terakhir diunduh. Nomor tetap sama.']) ?>
    <?php endif; ?>
    <?php if ($status === 'dikembalikan' && (string) $surat['catatan_staf'] !== ''): ?>
        <?= ui_peringatan('galat', '', ['html' => '<b>Dikembalikan dengan catatan:</b> ' . esc($surat['catatan_staf'])]) ?>
    <?php endif; ?>
    <?php if ($status === 'dibatalkan'): ?>
        <?= ui_peringatan('netral', '', ['html' => '<b>Surat dibatalkan.</b> ' . ((string) $surat['catatan_staf'] !== '' ? esc($surat['catatan_staf']) : '') . ($bernomor ? ' Nomor ' . esc($surat['nomor']) . ' tetap tercatat dan tidak dipakai lagi.' : '')]) ?>
    <?php endif; ?>
    <?php foreach ($periksa as $w): $bahaya = $w['tingkat'] === 'bahaya'; ?>
        <?= ui_peringatan($bahaya ? 'galat' : 'awas', '', ['html' => ($bahaya ? '<b>Bahaya:</b> ' : '') . esc($w['teks'])
            . (str_contains($w['teks'], 'Pengaturan PKL') && $bolehPengaturan ? ' <a href="' . site_url('admin/pkl/pengaturan#surat') . '" class="font-semibold underline">Isi di Pengaturan PKL</a>, lalu unduh surat lagi (nomor tetap sama).' : '')]) ?>
    <?php endforeach; ?>

    <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-4">
            <!-- Data surat -->
            <section class="kartu overflow-hidden" aria-labelledby="judul-data-surat">
                <?= $kepalaKartu('Data surat', 'judul-data-surat') ?>
                <dl class="kartu-isi grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="<?= $dt ?>">Nomor surat</dt><dd class="font-mono font-bold text-gray-900 dark:text-white"><?= $bernomor ? esc($surat['nomor']) : '<span class="font-sans font-normal text-gray-500 dark:text-gray-400">' . (SuratJenis::bernomor($jenis) ? 'belum diterbitkan' : 'jenis ini tidak bernomor') . '</span>' ?></dd></div>
                    <div><dt class="<?= $dt ?>">Tanggal surat</dt><dd class="<?= $dd ?>"><?= esc(IsianBantu::tanggalIndo((string) $surat['tanggal_surat'])) ?></dd></div>
                    <?php if ((string) $surat['perusahaan_nama'] !== ''): ?>
                        <div class="sm:col-span-2"><dt class="<?= $dt ?>">Perusahaan / tujuan</dt><dd class="<?= $dd ?>"><?= esc($surat['perusahaan_nama']) ?>
                            <?php if (! empty($surat['pengajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . (int) $surat['pengajuan_id']) ?>" class="ms-1 inline-flex items-center gap-0.5 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">lihat ajuan PKL<?= ikon('chevron-right', 'h-3 w-3') ?></a><?php endif; ?></dd></div>
                    <?php endif; ?>
                    <?php foreach ($ringkasIsi as [$label, $nilai]): ?>
                        <div><dt class="<?= $dt ?>"><?= esc($label) ?></dt><dd class="<?= $dd ?>"><?= esc($nilai) ?></dd></div>
                    <?php endforeach; ?>
                    <div><dt class="<?= $dt ?>">Dibuat oleh</dt><dd class="<?= $dd ?>"><?= esc((string) $surat['dibuat_nama'] !== '' ? $surat['dibuat_nama'] : '—') ?></dd><dd class="<?= $dt ?>"><?= esc(date('d-m-Y H:i', strtotime((string) $surat['created_at']))) ?></dd></div>
                </dl>
            </section>

            <!-- Siswa -->
            <?php if ($siswa !== []): ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-siswa-surat">
                    <?= $kepalaKartu('Siswa pada surat', 'judul-siswa-surat', '<span class="lencana lencana-abu">' . count($siswa) . ' siswa</span>') ?>
                    <div class="tabel-bungkus">
                        <table class="tabel min-w-[34rem]">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-12">No</th>
                                    <th scope="col">Nama</th>
                                    <th scope="col">NIS</th>
                                    <th scope="col">Kelas</th>
                                    <th scope="col">Konsentrasi keahlian</th>
                                    <th scope="col">HP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa as $i => $a):
                                    $hp = trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? '');
                                ?>
                                    <tr>
                                        <td class="tabular-nums text-gray-500 dark:text-gray-400"><?= $i + 1 ?></td>
                                        <td class="font-semibold text-gray-900 dark:text-white"><?= esc($a['nama']) ?></td>
                                        <td class="font-mono text-xs"><?= esc((string) $a['nis'] !== '' ? $a['nis'] : '—') ?></td>
                                        <td class="whitespace-nowrap"><?= esc((string) ($a['nama_kelas'] ?? '') !== '' ? $a['nama_kelas'] : '—') ?></td>
                                        <td><?= esc((string) ($a['jurusan_nama'] ?? '') !== '' ? $a['jurusan_nama'] : '—') ?></td>
                                        <td class="font-mono text-xs whitespace-nowrap"><?= $hp !== '' ? esc($hp) : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="space-y-4">
            <!-- Tindakan -->
            <?php if ($status !== 'dibatalkan'): ?>
                <section class="kartu overflow-hidden" aria-labelledby="judul-tindakan">
                    <?= $kepalaKartu('Tindakan', 'judul-tindakan') ?>
                    <div class="kartu-isi space-y-3 !p-4 text-sm">
                        <?php if ($status === 'menunggu'): ?>
                            <?php if ($bolehAcc): ?>
                                <button type="button" class="btn btn-sukses w-full" data-buka-dialog="surat-acc"><?= ikon('check', 'h-4 w-4') ?>ACC surat ini</button>
                                <button type="button" class="btn btn-utama w-full" data-buka-dialog="surat-kembalikan"><?= ikon('file-pen', 'h-4 w-4') ?>Kembalikan untuk diperbaiki</button>
                            <?php else: ?>
                                <?= $catatanKunci('Surat ini menunggu <b>ACC</b> dari ' . esc($pemegangAcc ?? 'peran yang berhak') . '. Akun ' . esc(HakAkses::label($peran)) . ' tidak punya hak ACC jenis surat ini.') ?>
                            <?php endif; ?>
                        <?php elseif ($status === 'dikembalikan'): ?>
                            <?php if ($bolehBuat): ?>
                                <form method="post" action="<?= site_url('admin/surat/' . $id . '/ajukan-ulang') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-utama w-full"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Ajukan ulang untuk ACC</button>
                                </form>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Perbaiki dulu sesuai catatan di atas bila perlu, lalu ajukan ulang. Riwayatnya tercatat.</p>
                            <?php else: ?>
                                <?= ui_peringatan('netral', 'Menunggu pembuat surat memperbaiki dan mengajukan ulang.') ?>
                            <?php endif; ?>
                        <?php elseif ($status === 'disetujui'): ?>
                            <?php if ($bolehUnduh): ?>
                                <?php if ($templateAda): ?>
                                    <form method="post" action="<?= site_url('admin/surat/' . $id . '/unduh') ?>" data-unduh<?= ! empty($kabarWa) ? ' data-surat-wa="' . $id . '"' : '' ?>>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-utama w-full"><?= ikon('download', 'h-4 w-4') ?><?= (int) $surat['cetak_ke'] > 0 ? 'Unduh ulang surat (Word)' : 'Unduh surat (Word)' ?></button>
                                    </form>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= $bernomor || ! SuratJenis::bernomor($jenis) ? 'Unduh ulang memakai nomor yang sama.' : 'Nomor surat diterbitkan sekali, saat surat pertama diunduh.' ?></p>
                                    <?php if (! empty($kabarWa)): $hw = $hitungWa ?? ['total' => 0, 'dikabari' => 0]; $semuaKabar = $hw['dikabari'] >= $hw['total'] && $hw['total'] > 0; ?>
                                        <?php if ((int) $surat['cetak_ke'] > 0): ?>
                                            <button type="button" class="btn btn-sukses w-full" data-kabari-surat="<?= $id ?>"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kabari siswa via WhatsApp</button>
                                            <p class="flex items-start gap-1 text-xs <?= $semuaKabar ? 'font-semibold text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>">
                                                <?= $semuaKabar ? ikon('check', 'mt-0.5 h-3.5 w-3.5 shrink-0') . 'Semua siswa (' . $hw['total'] . ') sudah dikabari surat ini siap diambil.' : 'Sudah dikabari: ' . $hw['dikabari'] . ' dari ' . $hw['total'] . ' siswa. Pesan: surat siap diambil di Tata Usaha.' ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Setelah surat diunduh, siswa bisa dikabari lewat WhatsApp bahwa suratnya siap diambil.</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= ui_peringatan('netral', 'Template Word untuk ' . SuratJenis::label($jenis) . ' belum tersedia, jadi surat ini belum bisa diunduh.') ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= $catatanKunci('Surat ini diunduh oleh ' . esc($pemegangUnduh ?? 'peran yang berhak') . ' (diatur Admin di Sistem → Hak Akses).') ?>
                            <?php endif; ?>
                            <?php if ($bisaBatalAcc && $bolehAcc): ?>
                                <button type="button" class="btn btn-netral btn-sm w-full text-red-700 dark:text-red-400" data-buka-dialog="surat-batalacc"><?= ikon('close-circle', 'h-4 w-4') ?>Batalkan persetujuan</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($bolehBuat && $alamatUbah !== ''): ?>
                            <a href="<?= esc($alamatUbah, 'attr') ?>" class="btn btn-netral btn-sm w-full"><?= ikon('edit', 'h-4 w-4') ?>Ubah data surat</a>
                        <?php endif; ?>
                        <?php if ($bolehBuat): ?>
                            <button type="button" class="btn btn-teks btn-sm w-full text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700" data-buka-dialog="surat-batal"><?= ikon('trash-bin', 'h-4 w-4') ?>Batalkan surat</button>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Status & ACC -->
            <section class="kartu overflow-hidden" aria-labelledby="judul-persetujuan">
                <?= $kepalaKartu('Persetujuan &amp; unduhan', 'judul-persetujuan') ?>
                <div class="kartu-isi space-y-4 !p-4 text-sm">
                    <div>
                        <p class="<?= $dt ?>">Aturan jenis ini</p>
                        <p class="<?= $dd ?>"><?= $wajibAcc ? 'Wajib ACC sebelum diunduh' : 'Tanpa ACC (langsung siap unduh)' ?></p>
                    </div>
                    <?php if (! empty($surat['acc_at'])): ?>
                        <div>
                            <p class="<?= $dt ?>">Disetujui oleh</p>
                            <p class="<?= $dd ?>"><?= esc((string) $surat['acc_nama']) ?></p>
                            <p class="<?= $dt ?>"><?= esc(HakAkses::label((string) $surat['acc_peran'])) ?> · <?= esc(PklSurat::waktuIndo((string) $surat['acc_at'])) ?></p>
                            <?php if ((string) $surat['acc_kode'] !== ''): ?><p class="mt-1 font-mono text-xs font-semibold text-gray-700 dark:text-gray-300"><?= esc($surat['acc_kode']) ?></p><?php endif; ?>
                        </div>
                    <?php elseif ($status === 'menunggu'): ?>
                        <?= ui_peringatan('awas', 'Menunggu persetujuan' . ((string) $surat['diajukan_at'] !== '' ? ' sejak ' . date('d-m-Y H:i', strtotime((string) $surat['diajukan_at'])) : '') . '.') ?>
                    <?php endif; ?>
                    <div>
                        <p class="<?= $dt ?>">Diunduh</p>
                        <p class="<?= $dd ?>"><?= (int) $surat['cetak_ke'] > 0 ? (int) $surat['cetak_ke'] . ' kali' : 'belum pernah' ?></p>
                        <?php if (! empty($surat['terakhir_cetak_at'])): ?><p class="<?= $dt ?>">terakhir <?= esc(date('d-m-Y H:i', strtotime((string) $surat['terakhir_cetak_at']))) ?></p><?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Riwayat -->
            <section class="kartu overflow-hidden" aria-labelledby="judul-riwayat-surat">
                <?= $kepalaKartu('Riwayat', 'judul-riwayat-surat') ?>
                <?php if ($riwayat === []): ?>
                    <p class="px-4 py-5 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada riwayat.</p>
                <?php else: ?>
                    <ol class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($riwayat as $h): ?>
                            <li class="px-4 py-3 text-sm">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc(SuratSekolah::AKSI[$h['aksi']] ?? $h['aksi']) ?></p>
                                <?php if ((string) $h['catatan'] !== ''): ?><p class="text-xs text-gray-600 dark:text-gray-300"><?= esc($h['catatan']) ?></p><?php endif; ?>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"><?= esc($h['oleh']) ?><?= (string) $h['peran'] !== '' ? ' (' . esc(HakAkses::label((string) $h['peran'])) . ')' : '' ?> · <?= esc(date('d-m-Y H:i', strtotime((string) $h['created_at']))) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<!-- ============ Dialog-dialog keputusan (dialog kit, dibuka tombol data-buka-dialog) ============ -->
<?php
$dialog = static function (string $nama, string $judul, string $aksiUrl, string $isi, string $tombol, string $kelasTombol) use ($id): string {
    return ui_dialog_buka(['nama' => 'surat-' . $nama, 'judul' => $judul])
        . '<form method="post" action="' . esc(site_url('admin/surat/' . $id . '/' . $aksiUrl), 'attr') . '">' . csrf_field()
        . '<div class="dialog-isi text-sm text-gray-600 dark:text-gray-300">' . $isi . '</div>'
        . '<div class="dialog-kaki"><button type="button" class="btn btn-netral" @click="tutup()">Batal</button>'
        . '<button type="submit" class="btn ' . $kelasTombol . '">' . $tombol . '</button></div></form>'
        . ui_dialog_tutup();
};
$kotakAlasan = static fn (string $ph, string $untuk): string => '<div><label class="lbl" for="alasan_' . $untuk . '">Alasan <span class="text-red-600">*</span></label>'
    . '<textarea id="alasan_' . $untuk . '" name="catatan" rows="3" required minlength="5" maxlength="255" class="inp" placeholder="' . esc($ph, 'attr') . '"></textarea>'
    . '<p class="hint">Minimal 5 huruf. Tercatat di riwayat surat.</p></div>';

if ($status === 'menunggu' && $bolehAcc) {
    $isiAcc = '<p>Surat <b>' . esc($kode) . '</b> — ' . esc($surat['judul']) . ' akan disetujui dan siap diunduh oleh yang berhak (nomor surat terbit saat pertama diunduh).</p>';
    if ($adaBahaya) {
        $isiAcc .= '<label class="flex cursor-pointer items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-gray-700 dark:text-red-400"><input type="checkbox" name="paham" value="1" required class="cek mt-0.5"><span class="text-sm font-semibold">Ada peringatan BAHAYA di halaman ini. Saya sudah memeriksanya dan tetap ingin meng-ACC.</span></label>';
    }
    $isiAcc .= ui_peringatan('netral', '', ['html' => 'Persetujuan ini akan <b>tercatat atas nama ' . esc($namaSaya) . '</b> (' . esc(HakAkses::label($peran)) . ') lengkap dengan tanggal &amp; jam, dan tercetak di kaki surat.']);
    echo $dialog('acc', 'ACC surat ' . $kode . '?', 'acc', $isiAcc, ikon('check', 'h-4 w-4') . 'Ya, ACC', 'btn-sukses');
    echo $dialog('kembalikan', 'Kembalikan untuk diperbaiki', 'kembalikan',
        '<p>Surat kembali ke pembuatnya untuk diperbaiki, lalu diajukan ulang kepada Anda.</p>' . $kotakAlasan('Contoh: Tanggal pelaksanaan belum sesuai kalender ujian.', 'kembalikan'),
        ikon('file-pen', 'h-4 w-4') . 'Kembalikan', 'btn-utama');
}
if ($status === 'disetujui' && $bisaBatalAcc && $bolehAcc) {
    echo $dialog('batalacc', 'Batalkan persetujuan ' . $kode, 'batal-acc',
        ui_peringatan('awas', '', ['html' => 'Surat kembali ke status <b>Dikembalikan</b> supaya diperbaiki, lalu perlu di-ACC lagi. Hanya bisa selama surat belum bernomor dan belum diunduh.'])
            . $kotakAlasan('Contoh: Ada siswa yang salah masuk daftar.', 'batalacc'),
        'Ya, batalkan persetujuan', 'btn-bahaya');
}
if ($bolehBuat && in_array($status, ['menunggu', 'dikembalikan', 'disetujui'], true)) {
    echo $dialog('batal', 'Batalkan surat ' . $kode . '?', 'batal',
        '<p>Surat ini <b>tidak jadi dipakai</b> dan tidak bisa diunduh lagi.' . ($bernomor ? ' Nomor <b>' . esc($surat['nomor']) . '</b> tetap tercatat dan tidak dipakai lagi.' : '') . ' Jejaknya tetap ada di riwayat dan Daftar Surat.</p>' . $kotakAlasan('Contoh: Dibuat dua kali, dipakai surat yang satunya.', 'batal'),
        ikon('trash-bin', 'h-4 w-4') . 'Ya, batalkan surat', 'btn-bahaya');
}
?>

<?php if (! empty($kabarWa)): ?><?= view('admin/surat/_wa') ?><?php endif; ?>

<?= $this->endSection() ?>
