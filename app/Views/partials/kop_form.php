<?php
/**
 * Kepala formulir publik bergaya kop resmi (Kesediaan Guru, Pengajuan PKL, Biodata Siswa): logo, nama sekolah,
 * judul formulir, keterangan singkat, tahun pelajaran. Tenang dan polos — tanpa gradasi/cahaya — dengan garis
 * aksen lilac (tema-lilac pada layout). Dibuat 2026-10-12 menggantikan kepala "hero" bergradasi.
 *
 * @var array       $setting
 * @var string      $judul
 * @var string|null $keterangan (opsional)
 * @var string|null $tahunLabel (opsional) bawaan "Tahun Pelajaran"
 */
$logoKop = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;
?>
<header class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="h-1 bg-brand-600" aria-hidden="true"></div>
    <div class="flex items-center gap-4 px-5 py-5 sm:px-7">
        <?php if ($logoKop): ?>
            <!-- Berkas logo hilang (mis. belum diunggah ulang di hosting) → sembunyikan, jangan tampil ikon rusak. -->
            <img src="<?= esc($logoKop, 'attr') ?>" alt="Logo sekolah" class="h-14 w-14 shrink-0 object-contain sm:h-16 sm:w-16" onerror="this.remove()">
        <?php endif; ?>
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500"><?= esc($setting['school_name'] ?? '') ?></p>
            <h1 class="mt-0.5 text-xl font-semibold leading-snug text-slate-900 sm:text-2xl"><?= esc($judul) ?></h1>
            <?php if (! empty($keterangan)): ?><p class="mt-1 text-sm text-slate-600"><?= esc($keterangan) ?></p><?php endif; ?>
        </div>
    </div>
    <?php if (! empty($setting['academic_year'])): ?>
        <div class="border-t border-brand-100 bg-brand-50 px-5 py-2 text-sm text-brand-800 sm:px-7"><?= esc($tahunLabel ?? 'Tahun Pelajaran') ?> <?= esc($setting['academic_year']) ?></div>
    <?php endif; ?>
</header>
