<?php
/**
 * Kartu "Hubungi sekolah": telepon, WhatsApp, email, alamat (+ tautan peta) dari Pengaturan Sekolah; yang kosong tidak tampil.
 *
 * @var array $setting
 */
$telp   = trim((string) ($setting['phone'] ?? ''));
$email  = trim((string) ($setting['email'] ?? ''));
$waAsli = trim((string) ($setting['wa_bantuan'] ?? ''));
$wa     = preg_replace('/\D+/', '', $waAsli);
$waUrl  = $wa !== '' ? 'https://wa.me/' . (str_starts_with($wa, '0') ? '62' . substr($wa, 1) : $wa) : '';
$alamat = trim(implode(', ', array_filter([trim((string) ($setting['address'] ?? '')), trim((string) ($setting['city'] ?? '')), trim((string) ($setting['school_postal_code'] ?? ''))])));
$peta   = $alamat !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($setting['school_name'] ?? '') . ' ' . $alamat) : '';
$ada    = $telp !== '' || $email !== '' || $waUrl !== '' || $alamat !== '';
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2 class="kartu-judul">Hubungi sekolah</h2>
            <p class="kartu-ket">Tata usaha sekolah siap membantu pada jam kerja.</p>
        </div>
    </div>
    <div class="kartu-isi">
        <?php if (! $ada): ?>
            <p class="text-sm text-teks-2">Kontak sekolah belum diisi. Silakan datang langsung ke tata usaha sekolah.</p>
        <?php else: ?>
            <ul class="grid gap-4 text-sm">
                <?php if ($alamat !== ''): ?>
                    <li class="flex gap-3"><?= ikon('map-pin-alt', 'mt-0.5 h-5 w-5 shrink-0 text-brand-700') ?>
                        <span><span class="block font-semibold text-teks">Alamat</span><span class="text-teks-2"><?= esc($alamat) ?></span>
                        <?php if ($peta !== ''): ?><a href="<?= esc($peta, 'attr') ?>" class="mt-1 block font-semibold text-brand-700 hover:underline" target="_blank" rel="noopener">Buka di peta</a><?php endif; ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($telp !== ''): ?>
                    <li class="flex gap-3"><?= ikon('phone', 'mt-0.5 h-5 w-5 shrink-0 text-brand-700') ?>
                        <span><span class="block font-semibold text-teks">Telepon</span><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $telp), 'attr') ?>" class="text-teks-2 hover:text-brand-700 hover:underline"><?= esc($telp) ?></a></span>
                    </li>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <li class="flex gap-3"><?= ikon('envelope', 'mt-0.5 h-5 w-5 shrink-0 text-brand-700') ?>
                        <span class="min-w-0"><span class="block font-semibold text-teks">Email</span><a href="mailto:<?= esc($email, 'attr') ?>" class="break-all text-teks-2 hover:text-brand-700 hover:underline"><?= esc($email) ?></a></span>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ($waUrl !== ''): ?>
                <a href="<?= esc($waUrl, 'attr') ?>" class="tombol tombol-utama tombol-blok mt-5" target="_blank" rel="noopener"><?= ikon('whatsapp', 'h-5 w-5', 'solid') ?>Chat WhatsApp sekolah</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
