<?php

/**
 * Halaman penerima tautan berbagi — satu dokumen (pola 4c rombak publik P5): pembuka (judul, jenis · ukuran · tanggal, tombol Unduh)
 * · keterangan · peringatan "dibaca saja" · penampil isi (partials/dokumen_penampil) · catatan agar tautan tidak disebarkan.
 *
 * @var array $d     Baris dokumen
 * @var array $share Baris tautan berbagi
 */
helper('dokumen');

$token    = (string) $share['token'];
$kategori = (string) $d['kategori'];
$ext      = strtolower((string) $d['ekstensi']);
$tautan   = $d['tipe'] === 'tautan';
$boleh    = (int) $share['boleh_unduh'] === 1;
$urlIsi   = site_url('d/' . $token . '/berkas');
$urlUnduh = site_url('d/' . $token . '/unduh');

// Office Viewer butuh berkas yang bisa diambil dari internet — lewat tautan
// berbagi memang bisa, jadi ditawarkan untuk format yang tak punya penampil.
$bolehViewerLuar = ! $tautan && $boleh && in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);

$info = implode(' · ', array_filter([
    $tautan ? 'Tautan ' . (string) $d['penyedia'] : strtoupper($ext ?: $kategori),
    $tautan ? '' : dokumen_ukuran_manusia((int) $d['ukuran']),
    'dibagikan ' . \App\Libraries\LayananPublik::tanggal((string) $share['created_at'], false),
]));
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?= view('public/partials/pembuka', [
    'remah'    => [['Dokumen dibagikan', null]],
    'ikon'     => 'file-lines',
    'label'    => 'Dokumen dibagikan',
    'judul'    => (string) $d['judul'],
    'ket'      => $info,
    'aksiHtml' => $boleh && ! $tautan ? '<a href="' . esc($urlUnduh, 'attr') . '" class="tombol tombol-utama">' . ikon('download') . 'Unduh</a>' : null,
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-4">
        <?php if (trim((string) $d['deskripsi']) !== ''): ?>
            <p class="max-w-3xl text-base leading-relaxed whitespace-pre-line text-teks"><?= esc($d['deskripsi']) ?></p>
        <?php endif; ?>
        <?php if (! $boleh && ! $tautan): ?>
            <div class="peringatan peringatan-awas"><?= ikon('info-circle') ?><p>Dokumen ini dibagikan untuk <b>dibaca saja</b> — pengunduhan dimatikan oleh pengirim.</p></div>
        <?php endif; ?>

        <div class="kartu overflow-hidden">
            <?= view('partials/dokumen_penampil', [
                'urlIsi'          => $urlIsi,
                'urlUnduh'        => $urlUnduh,
                'kategori'        => $kategori,
                'ext'             => $ext,
                'judul'           => $d['judul'],
                'mime'            => (string) $d['mime'],
                'tautan'          => $tautan,
                'ytId'            => $tautan ? dokumen_youtube_id((string) $d['url_eksternal']) : null,
                'urlEksternal'    => $d['url_eksternal'],
                'penyedia'        => (string) $d['penyedia'],
                'bolehUnduh'      => $boleh,
                'bolehViewerLuar' => $bolehViewerLuar,
                'urlViewerLuar'   => $bolehViewerLuar
                    ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($urlIsi)
                    : null,
            ]) ?>
        </div>

        <p class="pt-2 text-center text-sm text-teks-2">
            Tautan ini dibagikan oleh <?= esc($setting['school_name'] ?? 'sekolah') ?>. Mohon tidak menyebarkannya ke pihak yang tidak berkepentingan.
        </p>
    </div>
</section>

<?= $this->endSection() ?>
