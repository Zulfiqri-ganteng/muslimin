<?php
/**
 * SURAT SEKOLAH → JADWAL ASTS & TKA (Layanan Siswa, 2026-10-21). Jadwal yang dibuka di sini tampil di Layanan Siswa: siswa yang
 * sedang PKL memilih jadwal lalu mengajukan surat izin untuk perusahaannya. Controller: Admin\SuratJadwal; aturan: Libraries\SuratJadwal.
 * Tanpa Alpine sebaris: tambah/ubah memakai <details>, hapus memakai data-confirm (app.js), buka/tutup = tombol POST.
 *
 * @var array<string, list<array>> $grup      jenis (yang boleh dikelola) => jadwal
 * @var list<array>                $periode   periode ASTS di menu Ujian (SuratAcara::periodeUjian)
 * @var string                     $tahunAjar tahun pelajaran sekarang (Pengaturan Sekolah)
 * @var array<string, string>      $galat     galat isian terakhir
 * @var string                     $gagal     formulir yang gagal disimpan (tambah-izin_asts | tambah-izin_tka | ubah-{id})
 */

use App\Libraries\SuratAcara;
use App\Libraries\SuratJadwal;
use App\Libraries\SuratJenis;

$galat ??= [];
$gagal ??= '';
$wajib = '<span class="text-red-600 dark:text-red-400">*</span>';

/**
 * Isian formulir jadwal. $pre = awalan id unik; $nilai = isian awal; $pakaiOld = formulir ini yang gagal disimpan (pakai old()).
 */
$isian = static function (string $jenis, string $pre, array $nilai, bool $pakaiOld) use ($galat, $periode, $wajib): string {
    $v   = static fn (string $k): string => (string) ($pakaiOld ? (old($k) ?? ($nilai[$k] ?? '')) : ($nilai[$k] ?? ''));
    $err = static fn (string $k): string => $pakaiOld && isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
    $h   = '';
    if ($jenis === SuratJenis::ASTS) {
        if ($periode !== []) {
            $h .= '<div class="md:col-span-2"><label class="lbl" for="' . $pre . '-periode">Ambil dari menu Ujian <span class="font-normal text-gray-500 dark:text-gray-400">(disarankan)</span></label>'
                . '<select id="' . $pre . '-periode" name="periode" class="inp"><option value="">Tidak — isi semester &amp; tanggal sendiri di bawah</option>';
            foreach ($periode as $i => $p) {
                $h .= '<option value="' . $i . '"' . ($v('periode') !== '' && (int) $v('periode') === $i ? ' selected' : '') . '>' . esc($p['label']) . '</option>';
            }
            $h .= '</select><p class="hint">Bila dipilih, ujian, semester, tahun pelajaran, dan tanggal otomatis mengikuti menu Ujian (isian manual di bawah diabaikan).</p>' . $err('periode') . '</div>';
        }
        $h .= '<div class="md:col-span-2"><label class="lbl" for="' . $pre . '-kegiatan">Ujian</label><select id="' . $pre . '-kegiatan" name="kegiatan" class="inp">';
        foreach (SuratAcara::UJIAN as $kode => [$nama]) {
            $h .= '<option value="' . $kode . '"' . (SuratAcara::kodeUjian($v('kegiatan')) === $kode ? ' selected' : '') . '>' . $kode . ' — ' . esc($nama) . '</option>';
        }
        $h .= '</select>' . $err('kegiatan') . '</div>'
            . '<div><label class="lbl" for="' . $pre . '-semester">Semester</label><select id="' . $pre . '-semester" name="semester" class="inp">';
        foreach (['Ganjil', 'Genap'] as $s) {
            $h .= '<option' . ($v('semester') === $s ? ' selected' : '') . '>' . $s . '</option>';
        }
        $h .= '</select><p class="hint">ASAT otomatis semester Genap.</p>' . $err('semester') . '</div>'
            . '<div><label class="lbl" for="' . $pre . '-tp">Tahun pelajaran</label><input id="' . $pre . '-tp" type="text" name="tahun_pelajaran" maxlength="9" class="inp" placeholder="2026/2027" value="' . esc($v('tahun_pelajaran'), 'attr') . '">' . $err('tahun_pelajaran') . '</div>';
    } else {
        $h .= '<div class="md:col-span-2"><label class="lbl" for="' . $pre . '-sesi">Gelombang / sesi ' . $wajib . '</label>'
            . '<input id="' . $pre . '-sesi" type="text" name="sesi" maxlength="40" list="saran-sesi" class="inp" placeholder="Contoh: Gelombang 1" value="' . esc($v('sesi'), 'attr') . '">' . $err('sesi') . '</div>';
    }
    $h .= '<div><label class="lbl" for="' . $pre . '-mulai">Tanggal mulai' . ($jenis === SuratJenis::TKA ? ' ' . $wajib : '') . '</label><input id="' . $pre . '-mulai" type="date" name="tgl_mulai" class="inp" value="' . esc($v('tgl_mulai'), 'attr') . '">' . $err('tgl_mulai') . '</div>'
        . '<div><label class="lbl" for="' . $pre . '-selesai">Tanggal selesai' . ($jenis === SuratJenis::TKA ? ' ' . $wajib : '') . '</label><input id="' . $pre . '-selesai" type="date" name="tgl_selesai" class="inp" value="' . esc($v('tgl_selesai'), 'attr') . '">' . $err('tgl_selesai') . '</div>'
        . '<div class="md:col-span-2"><label class="lbl" for="' . $pre . '-tempat">Tempat</label><input id="' . $pre . '-tempat" type="text" name="tempat" maxlength="100" class="inp" value="' . esc($v('tempat'), 'attr') . '">' . $err('tempat') . '</div>';
    $buka = $pakaiOld ? old('buka') !== null : (int) ($nilai['buka'] ?? 1) === 1;
    $h   .= '<label class="md:col-span-2 inline-flex items-center gap-2 text-sm text-gray-900 dark:text-white"><input type="checkbox" name="buka" value="1" class="cek"' . ($buka ? ' checked' : '') . '>Buka untuk siswa (tampil di Layanan Siswa selama kegiatan belum selesai)</label>';

    return $h;
};

// Bawaan pilihan "Ambil dari menu Ujian": periode bertanggal yang belum lewat (terdekat); tidak ada → isi manual.
$periodeAwal = '';
foreach ($periode as $i => $p) {
    if ($p['selesai'] !== null && $p['selesai'] >= date('Y-m-d') && ($periodeAwal === '' || $p['mulai'] < $periode[(int) $periodeAwal]['mulai'])) {
        $periodeAwal = (string) $i;
    }
}

$judulJenis = [
    SuratJenis::ASTS => ['Jadwal Ujian (ASTS, ASAS, ASAT)', 'Siswa kelas XI dan XII yang sedang PKL bisa meminta surat izin ujian untuk perusahaannya.'],
    SuratJenis::TKA  => ['Jadwal TKA', 'Khusus siswa kelas XII yang sedang PKL. Surat Izin TKA hanya memuat siswa kelas XII.'],
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/surat') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Daftar Surat</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_jadwal_v1',
    'helpTitle' => 'Jadwal ASTS & TKA',
    'helpBody'  => '<p>Siswa tidak tahu tanggal ujian, jadi sekolah yang membuka jadwalnya di sini. Jadwal yang <b>dibuka</b> akan tampil di halaman <b>Layanan Siswa</b>: siswa yang sedang PKL tinggal memilih jadwal, lalu Surat Izin untuk perusahaannya masuk ke Daftar Surat untuk di-ACC.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Ujian (ASTS, ASAS, ASAT)</b>: pilih periodenya dari <b>menu Ujian</b> supaya ujian dan tanggalnya sama. Bila tanggal di menu Ujian belum diisi, isi manual.</li>'
        . '<li><b>TKA</b>: buat satu jadwal per <b>gelombang</b> (Gelombang 1, Gelombang 2, …). TKA khusus kelas XII.</li>'
        . '<li>Jadwal otomatis berhenti tampil bagi siswa setelah kegiatannya selesai. Tombol <b>Tutup</b> menghentikannya lebih awal.</li>'
        . '<li>Jadwal yang sudah dipakai permintaan siswa tidak bisa diubah atau dihapus (suratnya memakai tanggal itu) — tutup lalu buat jadwal baru.</li>'
        . '<li>Halaman Layanan Siswa sedang dibangun; jadwal yang dibuka sekarang otomatis muncul di sana begitu halamannya aktif.</li>'
        . '</ul>',
]) ?>

<datalist id="saran-sesi"><?php foreach (SuratAcara::SESI_SARAN as $s): ?><option value="<?= esc($s, 'attr') ?>"></option><?php endforeach; ?></datalist>

<div class="space-y-6">
    <?php foreach ($grup as $jenis => $daftar):
        [$judul, $ket] = $judulJenis[$jenis];
        $idTambah   = 'tambah-' . $jenis;
        $gagalTambah = $gagal === $idTambah;
        $awal = $jenis === SuratJenis::ASTS
            ? ['periode' => $periodeAwal, 'kegiatan' => 'ASTS', 'semester' => 'Ganjil', 'tahun_pelajaran' => $tahunAjar, 'tempat' => SuratAcara::TEMPAT_BAWAAN, 'buka' => 1]
            : ['sesi' => 'Gelombang ' . (count($daftar) + 1), 'tempat' => SuratAcara::TEMPAT_BAWAAN, 'buka' => 1];
    ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-<?= esc($jenis, 'attr') ?>">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 id="judul-<?= esc($jenis, 'attr') ?>" class="kartu-judul inline-flex items-center gap-2 text-base"><?= ikon('calendar-month', 'h-5 w-5') ?><?= esc($judul) ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($ket) ?></p>
                </div>
                <span class="lencana lencana-abu shrink-0"><?= count($daftar) ?> jadwal</span>
            </div>

            <details id="<?= esc($idTambah, 'attr') ?>" class="scroll-mt-24 border-b border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-700"<?= $gagalTambah || $daftar === [] ? ' open' : '' ?>>
                <summary class="btn btn-utama btn-sm cursor-pointer list-none"><?= ikon('plus', 'h-4 w-4') ?>Tambah jadwal <?= $jenis === SuratJenis::ASTS ? 'ujian' : 'TKA' ?></summary>
                <form method="post" action="<?= site_url('admin/surat/jadwal') ?>" class="mt-3 grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-2 dark:border-gray-600">
                    <?= csrf_field() ?>
                    <input type="hidden" name="jenis" value="<?= esc($jenis, 'attr') ?>">
                    <?= $isian($jenis, $idTambah, $awal, $gagalTambah) ?>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan jadwal</button>
                    </div>
                </form>
            </details>

            <?php if ($daftar === []): ?>
                <p class="px-4 py-5 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">Belum ada jadwal. Tambahkan jadwal supaya siswa bisa mengajukan surat izinnya.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($daftar as $j):
                        [$teksKeadaan, $lencana] = SuratJadwal::TAMPIL[$j['keadaan']];
                        $idUbah = 'ubah-' . $j['id'];
                    ?>
                        <li id="jadwal-<?= $j['id'] ?>" class="scroll-mt-24 px-4 py-3 sm:px-6">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900 dark:text-white"><?= esc($j['label']) ?> <span class="lencana <?= $lencana ?>"><?= esc($teksKeadaan) ?></span></p>
                                    <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300"><?= esc($j['hari_tanggal']) ?> · <?= esc($j['tempat']) ?></p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"><?= $j['jml_permintaan'] > 0 ? $j['jml_permintaan'] . ' permintaan siswa' : 'Belum ada permintaan siswa' ?><?= $j['dibuat_nama'] ? ' · dibuat ' . esc((string) $j['dibuat_nama']) : '' ?></p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <?php if ($j['keadaan'] === 'buka'): ?>
                                        <form method="post" action="<?= site_url('admin/surat/jadwal/' . $j['id'] . '/tutup') ?>"><?= csrf_field() ?>
                                            <button type="submit" class="btn btn-netral btn-sm"><?= ikon('lock', 'h-4 w-4') ?>Tutup</button>
                                        </form>
                                    <?php elseif ($j['keadaan'] === 'tutup'): ?>
                                        <form method="post" action="<?= site_url('admin/surat/jadwal/' . $j['id'] . '/buka') ?>"><?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sukses btn-sm"><?= ikon('check-circle', 'h-4 w-4') ?>Buka untuk siswa</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($j['jml_permintaan'] === 0): ?>
                                        <form method="post" action="<?= site_url('admin/surat/jadwal/' . $j['id'] . '/hapus') ?>"><?= csrf_field() ?>
                                            <button type="submit" class="btn btn-netral btn-sm text-red-700 dark:text-red-400" data-confirm-judul="Hapus jadwal?" data-confirm-ya="Ya, hapus"
                                                    data-confirm="<?= esc('Hapus jadwal ' . $j['label'] . ' (' . $j['hari_tanggal'] . ')?', 'attr') ?>"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($j['jml_permintaan'] === 0): ?>
                                <details id="<?= $idUbah ?>" class="mt-2 scroll-mt-24"<?= $gagal === $idUbah ? ' open' : '' ?>>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= ikon('edit', 'h-4 w-4') ?>Ubah</summary>
                                    <form method="post" action="<?= site_url('admin/surat/jadwal/' . $j['id']) ?>" class="mt-2 grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-2 dark:border-gray-600">
                                        <?= csrf_field() ?>
                                        <?= $isian($jenis, $idUbah, ['periode' => ''] + $j, $gagal === $idUbah) ?>
                                        <div class="md:col-span-2 flex justify-end">
                                            <button type="submit" class="btn btn-utama btn-sm"><?= ikon('check', 'h-4 w-4') ?>Simpan perubahan</button>
                                        </div>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
