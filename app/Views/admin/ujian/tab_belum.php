<?php
/**
 * Panel untuk tab yang belum digarap.
 *
 * @var string               $tab
 * @var array<string,string> $tabs
 * @var string               $base
 * @var string               $qtp
 */
?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </span>
    <p class="font-bold text-slate-700 mt-3"><?= esc($tabs[$tab]) ?> belum tersedia</p>
    <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
        Bagian ini sedang disiapkan dan akan aktif pada tahap pengembangan berikutnya.
        Sementara itu, susun dulu <a href="<?= $base ?>/jadwal<?= $qtp ?>" class="text-brand-600 font-semibold hover:underline">jadwal ujiannya</a>.
    </p>
</div>
