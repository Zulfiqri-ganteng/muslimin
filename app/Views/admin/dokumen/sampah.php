<?php

/**
 * Tempat sampah Manajemen Dokumen.
 *
 * @var array                         $rows    Dokumen terhapus (halaman ini)
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array                         $folders Folder terhapus
 */
helper('dokumen');

$tgl = static fn ($d) => $d ? date('d/m/Y H:i', strtotime((string) $d)) : '—';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_sampah',
    'helpTitle' => 'Tempat Sampah Dokumen',
    'helpBody'  => '<p>Berkas dan folder yang dihapus ditampung di sini lebih dulu — belum benar-benar hilang, jadi masih bisa <b>dipulihkan</b>.</p>
        <p class="mt-1">• <b>Memulihkan folder</b> sekaligus memulihkan seluruh isi yang dulu ikut terhapus bersamanya.<br>
        • Bila folder asal sebuah berkas masih berada di tempat sampah, berkas yang dipulihkan akan ditaruh di folder utama.<br>
        • <b>Hapus permanen</b> membuang berkas dari penyimpanan server dan <b>tidak bisa dibatalkan</b>.<br>
        • Ruang penyimpanan baru benar-benar kembali setelah berkas dihapus permanen.</p>',
]) ?>

<div class="flex items-center gap-2 mb-4 flex-wrap">
    <a href="<?= site_url('admin/dokumen') ?>"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Dokumen
    </a>

    <?php if ($rows !== [] || $folders !== []) { ?>
        <form method="post" action="<?= site_url('admin/dokumen/sampah/kosongkan') ?>" class="ml-auto"
              onsubmit="return confirm('Kosongkan tempat sampah? Seluruh berkas di dalamnya dihapus PERMANEN dari server dan tidak bisa dikembalikan.')">
            <?= csrf_field() ?>
            <button class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Kosongkan Sampah
            </button>
        </form>
    <?php } ?>
</div>

<?php if ($folders !== []) { ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4">
        <h3 class="font-semibold text-slate-800 text-sm mb-3">Folder terhapus</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <?php foreach ($folders as $f) { ?>
                <div class="flex items-center gap-2 border border-slate-200 rounded-xl p-2.5">
                    <svg class="w-8 h-8 text-slate-300 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-700 truncate"><?= esc($f['nama']) ?></p>
                        <p class="text-[11px] text-slate-400">Dibuang <?= esc($tgl($f['deleted_at'])) ?></p>
                    </div>
                    <form method="post" action="<?= site_url('admin/dokumen/folder/' . $f['id'] . '/pulihkan') ?>">
                        <?= csrf_field() ?>
                        <button class="px-2.5 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 text-xs font-semibold hover:bg-emerald-50">Pulihkan</button>
                    </form>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if ($rows === []) { ?>
        <div class="p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <p class="mt-3 text-slate-500 text-sm">Tempat sampah kosong.</p>
        </div>
    <?php } else { ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Nama</th>
                        <th class="px-3 py-2 text-left hidden md:table-cell">Jenis</th>
                        <th class="px-3 py-2 text-right hidden sm:table-cell">Ukuran</th>
                        <th class="px-3 py-2 text-left hidden lg:table-cell">Dibuang</th>
                        <th class="px-3 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r) { ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2">
                                <span class="font-medium text-slate-700"><?= esc($r['judul']) ?></span>
                                <?php if ($r['nama_asli']) { ?>
                                    <span class="block text-[11px] text-slate-400 truncate max-w-[280px]"><?= esc($r['nama_asli']) ?></span>
                                <?php } ?>
                            </td>
                            <td class="px-3 py-2 hidden md:table-cell">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600"><?= esc(strtoupper($r['ekstensi'] ?: $r['kategori'])) ?></span>
                            </td>
                            <td class="px-3 py-2 text-right text-slate-500 hidden sm:table-cell">
                                <?= $r['tipe'] === 'tautan' ? '—' : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                            </td>
                            <td class="px-3 py-2 text-slate-500 hidden lg:table-cell text-xs"><?= esc($tgl($r['deleted_at'])) ?></td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if ($r['tipe'] !== 'tautan') { ?>
                                        <a href="<?= site_url('admin/dokumen/unduh/' . $r['id']) ?>"
                                           class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs hover:bg-slate-50">Unduh</a>
                                    <?php } ?>
                                    <form method="post" action="<?= site_url('admin/dokumen/' . $r['id'] . '/pulihkan') ?>">
                                        <?= csrf_field() ?>
                                        <button class="px-2.5 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 text-xs font-semibold hover:bg-emerald-50">Pulihkan</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/dokumen/' . $r['id'] . '/hapus-permanen') ?>"
                                          onsubmit="return confirm('Hapus permanen &quot;<?= esc($r['judul'], 'attr') ?>&quot;? Berkasnya dibuang dari server dan TIDAK BISA dikembalikan.')">
                                        <?= csrf_field() ?>
                                        <button class="px-2.5 py-1.5 rounded-lg border border-red-300 text-red-700 text-xs font-semibold hover:bg-red-50">Hapus permanen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<?php if ($pager !== null) { ?>
    <div class="mt-4"><?= $pager->links('dok', 'admin') ?></div>
<?php } ?>

<?= $this->endSection() ?>
