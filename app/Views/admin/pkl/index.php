<?php
/**
 * Beranda PKL (staf).
 *
 * @var array<string,int>        $hitung    jumlah ajuan per status
 * @var array<string,int|float>  $siswa     ringkasan siswa (sudah/belum mengisi, sudah/belum PKL)
 * @var list<array>              $antrean   ajuan menunggu tertua
 * @var ?string                  $alasan    null = form terbuka; selain itu alasan tertutup
 * @var string                   $tautan    alamat form siswa
 * @var list<string>             $tingkat
 */
$pesanTutup = [
    'belum_dibuka'  => 'Form siswa belum dibuka.',
    'sudah_ditutup' => 'Batas waktu pengisian sudah lewat — form tertutup otomatis.',
    'belum_siap'    => 'Pengaturan belum lengkap (tingkat atau pagar tanggal belum diisi), jadi form belum bisa dibuka.',
];
$hubin = $peran === 'hubin';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_beranda_v3',
    'helpTitle' => 'Beranda PKL',
    'helpBody'  => '<p>Siswa mengisi ajuan PKL sendiri lewat tautan di HP. Di sini ajuan <b>diperiksa</b> lalu diputuskan. <b>ACC dan tolak hanya dilakukan Waka Hubin</b> (Admin sebagai cadangan); Operator memeriksa dan bisa <b>mengembalikan</b> ajuan agar siswa memperbaiki.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Menunggu ACC</b> — antrean ajuan baru, yang paling lama di atas.</li>'
        . '<li><b>Status Siswa</b> — melihat siapa yang <b>sudah/belum mengisi</b> dan siapa yang <b>sudah/belum PKL</b>.</li>'
        . '<li><b>Isi atas Nama</b> — bila siswa tak bisa mengisi sendiri, atau untuk memasukkan data PKL lama.</li>'
        . '<li><b>Persiapan</b> (kotak kuning, hanya Operator/Admin) — daftar yang harus siap sebelum tautan dibagikan: tanggal &amp; tingkat, nama Waka Hubin untuk surat, dan form dibuka. Hilang sendiri bila semuanya siap.</li>'
        . '<li>Setiap keputusan tercatat (siapa, kapan) dan terlihat di riwayat tiap ajuan.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<?php
// Daftar persiapan: hanya untuk yang boleh membuka Pengaturan, dan hanya selama ada yang belum siap.
$wakaOk  = trim((string) ($p['waka_hubin_nama'] ?? '')) !== '';
$pagarOk = $alasan !== 'belum_siap';
$bukaOk  = $alasan === null;
$persiapan = [
    [$pagarOk, 'Tingkat kelas yang boleh mengajukan dipilih', 'Tanpa ini form belum bisa dibuka.', site_url('admin/pkl/pengaturan')],
    [$wakaOk, 'Nama Waka Hubin (penanda tangan surat) diisi', 'Tanpa ini nama di bawah tanda tangan surat hanya titik-titik.', site_url('admin/pkl/pengaturan#surat')],
    [$bukaOk, 'Form dibuka untuk siswa', 'Siswa baru bisa mengajukan setelah form dibuka.', site_url('admin/pkl/pengaturan')],
];
$siap = count(array_filter(array_column($persiapan, 0)));
?>
<?php if ($bolehPengaturan && $siap < count($persiapan)): ?>
    <section class="rise rise-1 mb-5 overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-amber-100 px-5 py-3">
            <h2 class="text-sm font-bold text-amber-900">Persiapan sebelum tautan dibagikan ke siswa</h2>
            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800"><?= $siap ?> / <?= count($persiapan) ?> siap</span>
        </div>
        <ul class="divide-y divide-amber-100">
            <?php foreach ($persiapan as [$ok, $judul, $ket, $url]): ?>
                <li>
                    <a href="<?= esc($url, 'attr') ?>" class="flex items-start gap-3 px-5 py-3 transition hover:bg-amber-50">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold <?= $ok ? 'bg-green-500 text-white' : 'border-2 border-amber-400 bg-white text-transparent' ?>"><?= $ok ? '✓' : '·' ?></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold <?= $ok ? 'text-slate-400 line-through' : 'text-slate-800' ?>"><?= esc($judul) ?></span>
                            <?php if (! $ok): ?><span class="block text-xs text-slate-500"><?= esc($ket) ?></span><?php endif; ?>
                        </span>
                        <?php if (! $ok): ?><span class="ml-auto shrink-0 self-center text-xs font-bold text-amber-700">Atur →</span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<!-- Status form siswa -->
<div class="rise rise-2 mb-5 flex flex-col gap-3 rounded-2xl border px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between <?= $alasan === null ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' ?>"
     x-data="{ tersalin: false, salin() { navigator.clipboard.writeText(<?= esc(json_encode($tautan), 'attr') ?>).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000); }); } }">
    <div class="min-w-0">
        <p class="flex items-center gap-2 text-sm font-bold <?= $alasan === null ? 'text-green-800' : 'text-amber-900' ?>">
            <span class="inline-block h-2.5 w-2.5 rounded-full <?= $alasan === null ? 'bg-green-500' : 'bg-amber-500' ?>"></span>
            Form siswa: <?= $alasan === null ? 'TERBUKA' : 'TERTUTUP' ?>
        </p>
        <p class="mt-0.5 text-xs <?= $alasan === null ? 'text-green-700' : 'text-amber-800' ?>">
            <?php if ($alasan === null): ?>
                Tingkat <b><?= esc(implode(' & ', $tingkat)) ?></b> · maks <b><?= (int) \App\Models\PklPengaturanModel::maksSiswa($p) ?></b> siswa per ajuan · keputusan Waka Hubin paling lambat <b><?= (int) $batasHari ?> hari</b>
                <?php if (! empty($p['form_tutup'])): ?> · tutup otomatis <b><?= esc(date('d-m-Y H:i', strtotime($p['form_tutup']))) ?></b><?php endif; ?>
            <?php else: ?>
                <?= esc($pesanTutup[$alasan] ?? '') ?>
            <?php endif; ?>
        </p>
        <p class="mt-1 truncate text-xs font-semibold text-slate-600"><?= esc($tautan) ?></p>
    </div>
    <div class="flex shrink-0 flex-wrap gap-2">
        <button type="button" @click="salin()" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            <span x-text="tersalin ? '✓ Tautan disalin' : 'Salin tautan'">Salin tautan</span>
        </button>
        <?php if ($bolehPengaturan): ?>
            <a href="<?= site_url('admin/pkl/pengaturan') ?>" class="rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-800"><?= $alasan === null ? 'Atur / tutup form' : 'Buka lewat Pengaturan' ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if (($terlambat ?? 0) > 0): ?>
    <a href="<?= site_url('admin/pkl/daftar/menunggu') ?>" class="rise mb-5 flex items-center gap-3 rounded-2xl border border-red-300 bg-red-50 px-5 py-3.5 text-sm text-red-800 shadow-sm transition hover:bg-red-100">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-600 text-base font-extrabold text-white" aria-hidden="true">!</span>
        <span><b><?= (int) $terlambat ?> ajuan</b> sudah melewati batas keputusan Waka Hubin (<?= (int) $batasHari ?> hari sejak dikirim siswa). Segera diputuskan.</span>
        <span class="ml-auto shrink-0 font-bold">Lihat →</span>
    </a>
<?php endif; ?>

<!-- Angka status ajuan -->
<div class="rise rise-3 mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([
        ['menunggu', 'Menunggu ACC', 'text-blue-700', $hubin ? 'Menunggu keputusan Anda' : 'Menunggu keputusan Waka Hubin'],
        ['perbaikan', 'Perlu perbaikan', 'text-amber-600', 'Di tangan siswa'],
        ['disetujui', 'Disetujui', 'text-green-600', 'Siap dibuatkan surat'],
        ['ditolak', 'Ditolak', 'text-red-600', 'Siswa boleh mengajukan lagi'],
    ] as [$st, $label, $warna, $ket]): ?>
        <a href="<?= site_url('admin/pkl/daftar/' . $st) ?>" class="lift rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm hover:border-brand-300">
            <p class="text-xs font-semibold text-slate-400"><?= esc($label) ?></p>
            <p class="mt-1 text-3xl font-extrabold <?= $warna ?>"><?= (int) ($hitung[$st] ?? 0) ?></p>
            <p class="mt-0.5 text-[11px] text-slate-400"><?= esc($ket) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Dua angka utama: sudah mengisi, sudah PKL -->
<div class="rise rise-4 mb-5 grid grid-cols-1 gap-3 lg:grid-cols-2">
    <?php foreach ([
        ['Sudah mengisi', $siswa['sudah_isi'], $siswa['belum_isi'], $siswa['persen_isi'], 'belum mengisi', 'bg-blue-500', 'belum_mengisi'],
        ['Sudah dapat tempat PKL (disetujui)', $siswa['sudah_pkl'], $siswa['belum_pkl'], $siswa['persen_pkl'], 'belum PKL', 'bg-green-500', 'sudah_pkl'],
    ] as [$judul, $sudah, $belum, $persen, $labelBelum, $warnaBar, $saring]): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-sm font-bold text-slate-700"><?= esc($judul) ?></p>
                <p class="text-2xl font-extrabold text-slate-800"><?= (int) $persen ?>%</p>
            </div>
            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-100"><div class="bar-grow h-full rounded-full <?= $warnaBar ?>" style="width: <?= (int) $persen ?>%"></div></div>
            <p class="mt-2 text-xs text-slate-500"><b class="text-slate-700"><?= (int) $sudah ?></b> siswa sudah · <b class="text-slate-700"><?= (int) $belum ?></b> <?= esc($labelBelum) ?> · dari <b><?= (int) $siswa['total'] ?></b> siswa tingkat <?= esc(implode('/', $tingkat) ?: '—') ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($siswa['sudah_pkl'] > 0): ?>
    <p class="-mt-2 mb-5 text-xs text-slate-500">
        Dari yang disetujui: <b><?= (int) $siswa['belum_mulai'] ?></b> belum mulai · <b><?= (int) $siswa['sedang'] ?></b> sedang PKL · <b><?= (int) $siswa['selesai'] ?></b> selesai.
        <a href="<?= site_url('admin/pkl/siswa') ?>" class="font-semibold text-brand-700 hover:underline">Lihat per siswa →</a>
    </p>
<?php endif; ?>

<!-- Antrean -->
<div class="rise rise-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-bold text-slate-800"><?= $hubin ? 'Menunggu keputusan Anda' : 'Antrean ajuan — menunggu keputusan Waka Hubin' ?></h2>
            <p class="text-xs text-slate-400">Yang paling lama menunggu ada di atas.</p>
        </div>
        <a href="<?= site_url('admin/pkl/baru') ?>" class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">+ Isi atas Nama</a>
    </div>

    <?php if ($antrean === []): ?>
        <div class="px-5 py-10 text-center">
            <p class="text-3xl">✓</p>
            <p class="mt-2 font-semibold text-slate-700">Tidak ada ajuan yang menunggu</p>
            <p class="mt-1 text-sm text-slate-400"><?= $alasan === null ? 'Semua ajuan yang masuk sudah diputuskan.' : 'Form masih tertutup, belum ada ajuan baru.' ?></p>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($antrean as $r): ?>
                <li>
                    <a href="<?= site_url('admin/pkl/' . $r['id']) ?>" class="flex flex-col gap-1 px-5 py-3.5 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-slate-800"><?= esc($r['perusahaan_nama']) ?></span>
                            <span class="block truncate text-xs text-slate-500">
                                <?= esc($r['pengaju'] ?? '—') ?><?= ! empty($r['pengaju_kelas']) ? ' (' . esc($r['pengaju_kelas']) . ')' : '' ?>
                                <?= (int) $r['jumlah'] > 1 ? ' + ' . ((int) $r['jumlah'] - 1) . ' teman' : '' ?>
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3 text-xs text-slate-400">
                            <span class="font-mono font-semibold text-slate-500"><?= esc(\App\Models\PklPengajuanModel::kode((int) $r['id'])) ?></span>
                            <span><?= esc(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></span>
                            <span class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Periksa</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ((int) $hitung['menunggu'] > count($antrean)): ?>
            <a href="<?= site_url('admin/pkl/daftar/menunggu') ?>" class="block border-t border-slate-100 px-5 py-3 text-center text-sm font-semibold text-brand-700 hover:bg-slate-50">Lihat semua <?= (int) $hitung['menunggu'] ?> ajuan →</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
