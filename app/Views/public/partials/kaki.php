<?php
/**
 * Kaki portal publik (acuan rumah.pendidikan.go.id: latar gelap #1E1F1F — identitas & alamat lembaga, navigasi, unduh aplikasi).
 * Kontak & tautan aplikasi dari Pengaturan Sekolah; yang kosong tidak ditampilkan. Kredit desainer tetap di baris bawah.
 *
 * @var array       $setting
 * @var string      $schoolName
 * @var string|null $logoUrl
 */
use App\Libraries\LayananPublik;

$kelompok = LayananPublik::kelompok();
$layanan  = LayananPublik::semua($setting, false);
$telp     = trim((string) ($setting['phone'] ?? ''));
$email    = trim((string) ($setting['email'] ?? ''));
$situs    = trim((string) ($setting['website'] ?? ''));
$wa       = preg_replace('/\D+/', '', (string) ($setting['wa_bantuan'] ?? ''));
$waUrl    = $wa !== '' ? 'https://wa.me/' . (str_starts_with($wa, '0') ? '62' . substr($wa, 1) : $wa) : '';
$aplikasi = trim((string) ($setting['tautan_aplikasi'] ?? ''));
$tahun    = trim((string) ($setting['academic_year'] ?? ''));
$alamat   = trim(implode(', ', array_filter([trim((string) ($setting['address'] ?? '')), trim((string) ($setting['city'] ?? '')), trim((string) ($setting['school_postal_code'] ?? ''))])));
$situsUrl = $situs !== '' ? (preg_match('#^https?://#i', $situs) === 1 ? $situs : 'https://' . $situs) : '';
?>
<footer class="kaki-portal">
    <div class="wadah">
        <div class="kaki-grid">
            <div>
                <span class="kaki-logo">
                    <?php if ($logoUrl): ?>
                        <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo <?= esc($schoolName, 'attr') ?>" width="56" height="56">
                    <?php else: ?>
                        <!-- Berkas logo belum diunggah di Pengaturan Sekolah → gambar sekolah sebagai pengganti -->
                        <img src="<?= LayananPublik::ilustrasi('sekolah') ?>" alt="" width="56" height="56">
                    <?php endif; ?>
                </span>
                <p class="kaki-nama"><?= esc($schoolName) ?></p>
                <?php if ($alamat !== ''): ?><p class="kaki-alamat"><?= esc($alamat) ?></p><?php endif; ?>
                <ul class="kaki-daftar">
                    <?php if ($telp !== ''): ?>
                        <li class="kaki-kontak"><?= ikon('phone') ?><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $telp), 'attr') ?>" class="kaki-tautan"><?= esc($telp) ?></a></li>
                    <?php endif; ?>
                    <?php if ($waUrl !== ''): ?>
                        <li class="kaki-kontak"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><a href="<?= esc($waUrl, 'attr') ?>" class="kaki-tautan" target="_blank" rel="noopener">WhatsApp <?= esc($setting['wa_bantuan']) ?></a></li>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <li class="kaki-kontak"><?= ikon('envelope') ?><a href="mailto:<?= esc($email, 'attr') ?>" class="kaki-tautan break-all"><?= esc($email) ?></a></li>
                    <?php endif; ?>
                    <?php if ($situsUrl !== ''): ?>
                        <li class="kaki-kontak"><?= ikon('globe') ?><a href="<?= esc($situsUrl, 'attr') ?>" class="kaki-tautan break-all" target="_blank" rel="noopener"><?= esc($situs) ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <nav aria-label="Navigasi kaki">
                <p class="kaki-judul">Navigasi</p>
                <ul class="kaki-daftar">
                    <li><a href="<?= site_url('/') ?>" class="kaki-tautan">Beranda</a></li>
                    <?php foreach ($kelompok as $g): ?>
                        <li><a href="<?= esc($g['url'], 'attr') ?>" class="kaki-tautan"><?= esc($g['label']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?= site_url('bantuan') ?>" class="kaki-tautan">Bantuan</a></li>
                </ul>
            </nav>

            <nav aria-label="Layanan">
                <p class="kaki-judul">Layanan</p>
                <ul class="kaki-daftar">
                    <?php foreach (['pengumuman', 'jadwal_kelas', 'jadwal_guru', 'absensi', 'dokumen', 'pkl', 'biodata'] as $k): if (isset($layanan[$k])): ?>
                        <li><a href="<?= esc($layanan[$k]['url'], 'attr') ?>" class="kaki-tautan"><?= esc($layanan[$k]['judul']) ?></a></li>
                    <?php endif; endforeach; ?>
                </ul>
            </nav>

            <div>
                <p class="kaki-judul"><?= $aplikasi !== '' ? 'Unduh aplikasi' : 'Panel sekolah' ?></p>
                <div class="mt-4 flex flex-col items-start gap-3">
                    <?php if ($aplikasi !== ''): ?>
                        <a href="<?= esc($aplikasi, 'attr') ?>" class="kaki-tombol" target="_blank" rel="noopener"><?= ikon('mobile-phone', 'h-5 w-5') ?>Aplikasi Android</a>
                    <?php endif; ?>
                    <a href="<?= site_url('admin/login') ?>" class="kaki-tombol"><?= ikon('arrow-right-to-bracket', 'h-5 w-5') ?>Masuk guru &amp; staf</a>
                </div>
            </div>
        </div>

        <div class="kaki-bawah">
            <p>&copy; <?= date('Y') ?> <?= esc($schoolName) ?><?= $tahun !== '' ? ' &middot; Tahun Pelajaran ' . esc($tahun) : '' ?></p>
            <?= view('partials/kredit', ['kreditKelas' => ''], ['saveData' => false]) ?>
        </div>
    </div>
</footer>
