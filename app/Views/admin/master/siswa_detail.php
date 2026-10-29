<?php
/**
 * Detail Siswa — semua data satu siswa, read-only (rombak tampilan F5: kartu profil + kartu per bagian, pola template Flowbite).
 * Isi dirakit App\Libraries\SiswaDetail (dipakai juga API). Tombol di kepala: Kembali ke daftar (membawa saringan) & Ubah data.
 *
 * @var array{siswa: array<string,mixed>, bagian: list<array>, biodata: array<string,mixed>, pkl: ?array<string,mixed>, jejak: array<string,mixed>} $d
 * @var bool   $bolehPkl  peran ini boleh membuka ajuan PKL
 * @var bool   $bolehBio  peran ini boleh membuka Isian Biodata
 * @var string $urlDaftar kembali ke daftar (membawa saringan)
 * @var array<string, int> $terkait  data terkait siswa (SiswaStatus::keterkaitan) — kosong = boleh hapus permanen
 * @var bool   $bolehUbah peran ini boleh mengubah status / menghapus
 */
use App\Libraries\SiswaStatus;

$s             = $d['siswa'];
$lencanaStatus = ['aktif' => 'lencana-hijau', 'lulus' => 'lencana-merek', 'pindah' => 'lencana-kuning', 'keluar' => 'lencana-abu'];
$statusPkl     = [
    'menunggu'  => ['Menunggu keputusan Waka Hubin', 'lencana-merek'],
    'perbaikan' => ['Perlu perbaikan', 'lencana-kuning'],
    'disetujui' => ['Disetujui', 'lencana-hijau'],
];
$urlEdit = site_url('admin/master/siswa') . '?' . http_build_query(['q' => (string) $s['nis'], 'edit' => (int) $s['id']]);
$kosong  = '<span class="text-gray-500 dark:text-gray-400">—</span>';
$bio     = $d['biodata'];
$inisial = mb_strtoupper(mb_substr(trim((string) $s['nama']), 0, 1)) ?: '?';
// Satu baris data: label kiri, isi kanan.
$baris = static fn (string $label, string $isiHtml): string => '<div class="grid grid-cols-5 gap-3 px-4 py-3 text-sm sm:px-6">'
    . '<dt class="col-span-2 text-gray-500 dark:text-gray-400">' . esc($label) . '</dt>'
    . '<dd class="col-span-3 font-medium break-words text-gray-900 dark:text-white">' . $isiHtml . '</dd></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($urlDaftar, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Daftar siswa</a>
<a href="<?= esc($urlEdit, 'attr') ?>" class="btn btn-utama"><?= ikon('edit', 'h-4 w-4') ?>Ubah data</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="space-y-4">

    <!-- Kartu profil -->
    <div class="kartu kartu-isi flex flex-wrap items-center gap-4">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xl font-bold text-brand-700 dark:bg-brand-900 dark:text-brand-300" aria-hidden="true"><?= esc($inisial) ?></span>
        <div class="min-w-0 flex-1">
            <p class="text-xl font-bold break-words text-gray-900 dark:text-white"><?= esc($s['nama']) ?></p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                NIS <b class="text-gray-900 dark:text-white"><?= esc($s['nis']) ?></b>
                <?php if (! empty($s['nisn'])): ?> &middot; NISN <b class="text-gray-900 dark:text-white"><?= esc($s['nisn']) ?></b><?php endif; ?>
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="lencana lencana-abu"><?= ! empty($s['nama_kelas']) ? esc($s['nama_kelas']) : 'Belum ada kelas' ?></span>
                <span class="lencana <?= $lencanaStatus[$s['status']] ?? 'lencana-abu' ?>"><?= esc(SiswaStatus::label((string) $s['status'])) ?><?= $s['status'] !== 'aktif' ? ' (tidak aktif)' : '' ?></span>
                <?php if (! empty($s['biodata_at'])): ?><span class="lencana lencana-hijau"><?= ikon('check', 'h-3 w-3') ?>Biodata</span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($bolehUbah): ?>
    <!-- Status siswa: nonaktifkan / aktifkan / hapus permanen (SiswaStatus) -->
    <section class="kartu kartu-isi space-y-3" aria-labelledby="judul-status-siswa">
        <h2 id="judul-status-siswa" class="text-base font-semibold text-gray-900 dark:text-white">Status siswa</h2>
        <p class="text-sm text-gray-700 dark:text-gray-300">
            <?php if ($s['status'] === 'aktif'): ?>
                Siswa <b>aktif</b>. Bila sudah lulus, pindah sekolah, atau keluar, pakai <b>Nonaktifkan</b> — datanya tetap tersimpan sebagai arsip dan siswa tidak muncul lagi di formulir siswa (Layanan Siswa, Biodata, PKL).
            <?php else: ?>
                Siswa <b>tidak aktif</b> (<?= esc(SiswaStatus::label((string) $s['status'])) ?>). Datanya tersimpan sebagai arsip; tekan <b>Aktifkan kembali</b> bila keliru.
            <?php endif; ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <?php if ($s['status'] === 'aktif'): ?>
                <a href="<?= site_url('admin/master/siswa/' . (int) $s['id'] . '/nonaktifkan') ?>" class="btn btn-netral"><?= ikon('user-circle', 'h-4 w-4') ?>Nonaktifkan</a>
            <?php else: ?>
                <form method="post" action="<?= site_url('admin/master/siswa/' . (int) $s['id'] . '/aktifkan') ?>" data-confirm="<?= esc($s['nama'], 'attr') ?> diaktifkan kembali dan muncul lagi di formulir siswa." data-confirm-judul="Aktifkan kembali?" data-confirm-ya="Ya, aktifkan">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sukses"><?= ikon('check-circle', 'h-4 w-4') ?>Aktifkan kembali</button>
                </form>
            <?php endif; ?>
            <?php if ($terkait === []): ?>
                <form method="post" action="<?= site_url('admin/master/siswa/delete/' . (int) $s['id']) ?>" data-confirm="<?= esc($s['nama'], 'attr') ?> (NIS <?= esc($s['nis'], 'attr') ?>) dihapus PERMANEN dari database dan tidak bisa dikembalikan. NIS/NISN-nya bisa dipakai lagi." data-confirm-judul="Hapus permanen?" data-confirm-ya="Ya, hapus permanen" data-confirm-jenis="bahaya">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus permanen</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($terkait !== []): ?>
            <p class="hint">Tidak bisa dihapus permanen karena sudah punya data: <?= esc(SiswaStatus::ringkas($terkait)) ?>. Pakai Nonaktifkan bila siswa sudah tidak bersekolah di sini.</p>
        <?php else: ?>
            <p class="hint">Hapus permanen hanya untuk data yang salah input (belum punya data PKL, surat, biodata, dll.).</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Data per bagian -->
    <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($d['bagian'] as [$judul, $daftar]): ?>
            <section class="kartu" aria-label="<?= esc($judul, 'attr') ?>">
                <div class="kartu-kepala"><h2 class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?></h2></div>
                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($daftar as [$kunci, $label, $nilai]): ?>
                        <div class="grid grid-cols-5 gap-3 px-4 py-3 text-sm sm:px-6" data-kunci="<?= esc($kunci, 'attr') ?>">
                            <dt class="col-span-2 text-gray-500 dark:text-gray-400"><?= esc($label) ?></dt>
                            <dd class="col-span-3 font-medium break-words text-gray-900 dark:text-white"><?= $nilai !== null && $nilai !== '' ? esc($nilai) : $kosong ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>

        <!-- Biodata -->
        <section class="kartu" aria-label="Isian biodata">
            <div class="kartu-kepala"><h2 class="text-base font-semibold text-gray-900 dark:text-white">Isian biodata</h2></div>
            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                <?= $baris('Disahkan', $bio['disahkan_pada'] !== null ? esc($bio['disahkan_pada']) : 'Belum disahkan') ?>
                <?= $baris('Kelengkapan', $bio['kurang'] === []
                    ? '<span class="text-green-700 dark:text-green-400">Lengkap</span>'
                    : count($bio['kurang']) . ' kolom wajib belum terisi<span class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">' . esc(implode(', ', $bio['kurang'])) . '</span>') ?>
                <?php if ($bio['isian'] !== null): ?>
                    <?= $baris('Isian siswa', esc($bio['isian']['status_label'])
                        . ($bolehBio ? ' <a href="' . site_url('admin/biodata/' . (int) $bio['isian']['id']) . '" class="ms-1 font-semibold text-brand-700 hover:underline dark:text-brand-400">Buka →</a>' : '')) ?>
                <?php endif; ?>
            </dl>
        </section>

        <!-- PKL -->
        <section class="kartu" aria-label="PKL / Prakerin">
            <div class="kartu-kepala"><h2 class="text-base font-semibold text-gray-900 dark:text-white">PKL / Prakerin</h2></div>
            <?php if ($d['pkl'] === null): ?>
                <p class="px-4 py-4 text-sm text-gray-500 sm:px-6 dark:text-gray-400">Belum punya ajuan PKL yang aktif.</p>
            <?php else: $p = $d['pkl']; [$labelPkl, $lencanaPkl] = $statusPkl[$p['status']] ?? [ucfirst((string) $p['status']), 'lencana-abu']; ?>
                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?= $baris('Status', '<span class="lencana ' . $lencanaPkl . '">' . esc($labelPkl) . '</span>') ?>
                    <?= $baris('Perusahaan', esc($p['perusahaan']) . (! empty($p['kota']) ? ' &middot; ' . esc($p['kota']) : '')) ?>
                    <?= $baris('Peran di ajuan', $p['peran'] === 'pengaju' ? 'Pengaju' : 'Teman satu tempat') ?>
                    <?= $baris('Nomor surat', $p['nomor_surat'] !== null ? esc($p['nomor_surat']) : $kosong) ?>
                    <?= $baris('Nomor bukti', esc($p['kode'])
                        . ($bolehPkl ? ' <a href="' . site_url('admin/pkl/' . (int) $p['ajuan_id']) . '" class="ms-1 font-semibold text-brand-700 hover:underline dark:text-brand-400">Buka ajuan →</a>' : '')) ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <p class="px-1 text-xs text-gray-500 dark:text-gray-400">
        Dibuat <?= esc($d['jejak']['dibuat'] ?? '—') ?> &middot; terakhir diubah <?= esc($d['jejak']['diubah'] ?? '—') ?>
    </p>
</div>
<?= $this->endSection() ?>
