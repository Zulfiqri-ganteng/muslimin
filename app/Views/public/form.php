<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>

<?php
/**
 * Format Kesediaan Guru Mengajar — wizard 6 langkah, dikirim sebagai form biasa (POST kirim / revisi/{token}).
 * (P7 rombak publik) Bingkai layouts/formulir.php + kop partials/kop_formulir + kit publik.css; skrip sebaris dipindah ke
 * assets/js/kesediaan.js (logika sama), data awal tabel mapel lewat blok JSON (bukan skrip yang dijalankan → siap CSP).
 * Nama isian (name=…), nilai lama (old()), mode revisi, dan validasi server TIDAK berubah.
 */
$tahun  = esc($setting['academic_year'] ?? '2026/2027');
$jamOpt = [
    'Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.',
    'Bersedia mengajar lintas kelas/program keahlian sesuai kompetensi.',
    'Bersedia mengajar pada jadwal pagi.',
    'Bersedia mengajar pada jadwal siang.',
    'Bersedia mengajar pada jadwal pagi & siang.',
];
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
$sesiList = ['Pagi', 'Siang'];
$komitmen = [
    'Melaksanakan proses pembelajaran sesuai ketentuan yang berlaku.',
    'Menyusun perangkat ajar secara lengkap dan tepat waktu.',
    'Melaksanakan asesmen dan evaluasi pembelajaran secara objektif.',
    'Menjaga disiplin, etika profesi, dan nama baik sekolah.',
    'Mendukung seluruh program sekolah.',
    'Mengikuti rapat, pelatihan, workshop, dan kegiatan sekolah yang ditugaskan.',
    'Melaksanakan tugas tambahan dengan penuh tanggung jawab.',
    'Mencapai target kinerja yang ditetapkan sekolah.',
];
$stepLabels = ['Kesediaan', 'Identitas', 'Mata Pelajaran', 'Tugas & Jam', 'Lampiran', 'Pernyataan'];
$err = session('errors') ?? [];

// ---- Mode: pengisian baru ATAU revisi ----
$d          = $data ?? [];
$isEdit     = ! empty($editToken);
$formAction = $isEdit ? site_url('revisi/' . $editToken) : site_url('kirim');

$fv = static function (string $field, $default = '') use ($d) {
    $o = old($field);
    return $o !== null ? $o : ($d[$field] ?? $default);
};

$selBersedia = old('bersedia') ?? (isset($d['bersedia_mengajar']) ? ($d['bersedia_mengajar'] ? 'ya' : 'tidak') : '');
$selStatus   = old('status_kepegawaian') ?? ($d['status_kepegawaian'] ?? '');
$tugasChecked = (old('tugas_bersedia') ?? (! empty($d['tugas_tambahan']) ? '1' : '')) ? true : false;
$selJam      = old('jam_kesediaan') ?? ($d['kesediaan_jam'] ?? []);
$selHari     = old('hari') ?? ($d['ketersediaan_hari'] ?? []);

/** Kepala kartu langkah. */
$kepala = static fn (int $no, string $judul): string => '<div class="kartu-langkah-kepala"><span class="kartu-langkah-no">' . $no . '</span><h2 class="kartu-langkah-judul">' . esc($judul) . '</h2></div>';
/** Kotak centang gaya kit (input asli tersembunyi). */
$centang = static fn (string $nama, string $nilai, bool $cek, string $teksHtml, string $kelas = ''): string => '<label class="pilih-cek ' . $kelas . '">'
    . '<input type="checkbox" name="' . $nama . '" value="' . esc($nilai, 'attr') . '" class="sr-only"' . ($cek ? ' checked' : '') . '>'
    . '<span class="pilih-cek-kotak"></span><span class="text-sm text-teks">' . $teksHtml . '</span></label>';
?>

<div class="formulir max-w-3xl" id="formTop">

    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Format Kesediaan Guru Mengajar', 'tahunLabel' => 'Tahun Ajaran'], ['saveData' => false]) ?>

    <?php if ($isEdit): ?>
        <div class="peringatan peringatan-awas mt-5">
            <?= ikon('info-circle') ?>
            <div>
                <p class="font-semibold">Anda sedang memperbaiki data kesediaan.</p>
                <?php if (! empty($adminNote)): ?><p class="mt-1"><span class="font-semibold">Catatan admin:</span> <?= esc($adminNote) ?></p><?php endif; ?>
                <p class="mt-1">Perbaiki seperlunya, lalu simpan. Tautan ini hanya berlaku satu kali.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session('error') || ! empty($err)): ?>
        <div class="peringatan peringatan-galat mt-5" role="alert">
            <?= ikon('exclamation-circle') ?>
            <div>
                <p class="font-semibold">Terdapat kesalahan pada isian:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    <?php if (session('error')): ?><li><?= esc(session('error')) ?></li><?php endif; ?>
                    <?php foreach ($err as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Penunjuk langkah -->
    <div class="langkah-panel mt-6">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-brand-800">Langkah <span id="curStep">1</span> dari <?= count($stepLabels) ?></p>
            <p class="text-sm text-teks-2" id="stepTitle"><?= esc($stepLabels[0]) ?></p>
        </div>
        <div class="langkah-bilah grid-cols-6" aria-hidden="true">
            <?php foreach ($stepLabels as $i => $lbl): ?><span<?= $i === 0 ? ' class="is-done"' : '' ?>></span><?php endforeach; ?>
        </div>
        <div class="langkah-daftar">
            <?php foreach ($stepLabels as $i => $lbl): ?>
                <button type="button" class="langkah-titik<?= $i === 0 ? ' is-active' : '' ?>"><span class="langkah-titik-no"><?= $i + 1 ?></span><span class="langkah-titik-label"><?= esc($lbl) ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>

    <form action="<?= $formAction ?>" method="post" id="formKesediaan" class="mt-6">
        <?= csrf_field() ?>

        <!-- STEP 1 — KESEDIAAN -->
        <section class="wizard-step kartu-langkah" data-title="Kesediaan">
            <?= $kepala(1, 'Kesediaan Mengisi') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <label class="lbl" for="kg-nama">Nama Lengkap <span class="tanda-wajib">*</span></label>
                    <input type="text" id="kg-nama" name="nama_lengkap" value="<?= esc($fv('nama_lengkap'), 'attr') ?>" class="inp" placeholder="Nama lengkap beserta gelar">
                </div>
                <div>
                    <span class="lbl">Apakah Anda bersedia mengisi formulir kesediaan guru mengajar T.P. <?= $tahun ?>? <span class="tanda-wajib">*</span></span>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="pilihan-tombol"><input type="radio" name="bersedia" value="ya" class="sr-only" <?= $selBersedia === 'ya' ? 'checked' : '' ?>><span>Ya, saya bersedia</span></label>
                        <label class="pilihan-tombol"><input type="radio" name="bersedia" value="tidak" class="sr-only" <?= $selBersedia === 'tidak' ? 'checked' : '' ?>><span>Tidak bersedia</span></label>
                    </div>
                </div>
                <div id="declineMsg" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Jika Anda <b>tidak bersedia</b>, maka Anda harus <b>konfirmasi kepada pihak sekolah</b>. Klik <b>Kirim</b> untuk mencatat jawaban Anda.
                </div>
            </div>
        </section>

        <!-- STEP 2 — IDENTITAS -->
        <section class="wizard-step kartu-langkah hidden" data-title="Identitas Guru">
            <?= $kepala(2, 'Identitas Guru') ?>
            <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                    <label class="lbl" for="kg-hp">Nomor HP / WhatsApp <span class="tanda-wajib">*</span></label>
                    <input type="text" id="kg-hp" name="nomor_hp" value="<?= esc($fv('nomor_hp'), 'attr') ?>" class="inp" inputmode="tel" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lbl" for="kg-pendidikan">Pendidikan Terakhir</label>
                    <input type="text" id="kg-pendidikan" name="pendidikan_terakhir" value="<?= esc($fv('pendidikan_terakhir'), 'attr') ?>" class="inp" placeholder="Contoh: S1 Pendidikan Matematika">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="kg-mapel">Guru Mapel</label>
                    <input type="text" id="kg-mapel" name="guru_mapel" value="<?= esc($fv('guru_mapel'), 'attr') ?>" class="inp" placeholder="Contoh: Matematika">
                </div>
                <div class="sm:col-span-2">
                    <span class="lbl">Status Kepegawaian <span class="tanda-wajib">*</span></span>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $st): ?>
                            <label class="pilihan-tombol"><input type="radio" name="status_kepegawaian" value="<?= $st ?>" class="sr-only" <?= $selStatus === $st ? 'checked' : '' ?>><span><?= $st ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- STEP 3 — MATA PELAJARAN -->
        <section class="wizard-step kartu-langkah hidden" data-title="Mata Pelajaran yang Diampu">
            <?= $kepala(3, 'Mata Pelajaran yang Diampu') ?>
            <div class="kartu-langkah-isi">
                <div class="rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900">
                    Bersedia melaksanakan tugas mengajar pada Tahun Ajaran <?= $tahun ?> sesuai penugasan yang diberikan oleh Kepala Sekolah.
                </div>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12 text-center">No</th>
                                <th scope="col">Mata Pelajaran</th>
                                <th scope="col" class="w-40">Kelas</th>
                                <th scope="col" class="w-14"><span class="sr-only">Hapus</span></th>
                            </tr>
                        </thead>
                        <tbody id="mapelBody"></tbody>
                    </table>
                </div>
                <template id="ikonHapus"><?= ikon('close', 'h-5 w-5') ?></template>
                <button type="button" id="addMapel" class="tombol tombol-teks"><?= ikon('plus', 'h-4 w-4') ?>Tambah baris mata pelajaran</button>
            </div>
        </section>

        <!-- STEP 4 — TUGAS TAMBAHAN & JAM MENGAJAR -->
        <section class="wizard-step kartu-langkah hidden" data-title="Tugas Tambahan &amp; Jam Mengajar">
            <?= $kepala(4, 'Tugas Tambahan & Jam Mengajar') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <p class="lbl">Kesediaan Tugas Tambahan</p>
                    <?= $centang('tugas_bersedia', '1', $tugasChecked, '<span class="font-medium">Saya bersedia menerima tugas tambahan sesuai kebutuhan sekolah.</span>', 'border-brand-200 bg-brand-50') ?>
                </div>
                <div>
                    <p class="lbl">Kesediaan Jam Mengajar</p>
                    <div class="space-y-3">
                        <?php foreach ($jamOpt as $j): ?>
                            <?= $centang('jam_kesediaan[]', $j, in_array($j, $selJam, true), esc($j), 'items-center') ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- STEP 5 — LAMPIRAN -->
        <section class="wizard-step kartu-langkah hidden" data-title="Lampiran Kesediaan">
            <?= $kepala(5, 'Lampiran Kesediaan') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <h3 class="sub-judul">Ketersediaan Hari Mengajar</h3>
                    <p class="hint mb-3">Centang sesi yang Anda bersedia mengajar pada tiap hari.</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <?php foreach ($hariList as $h): ?>
                            <div class="rounded-lg border border-garis px-4 py-3">
                                <p class="mb-2 text-sm font-semibold text-teks"><?= $h ?></p>
                                <div class="flex gap-2">
                                    <?php foreach ($sesiList as $sesi): ?>
                                        <?= $centang('hari[' . $h . '][]', $sesi, in_array($sesi, $selHari[$h] ?? [], true), $sesi, 'flex-1 items-center py-2') ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="sub-judul mb-2 block" for="kg-ket">Keterangan Tambahan</label>
                    <textarea id="kg-ket" name="keterangan_tambahan" rows="3" class="inp" placeholder="Catatan / keterangan tambahan (opsional)"><?= esc($fv('keterangan_tambahan')) ?></textarea>
                </div>
            </div>
        </section>

        <!-- STEP 6 — KOMITMEN & PERNYATAAN -->
        <section class="wizard-step kartu-langkah hidden" data-title="Komitmen &amp; Pernyataan">
            <?= $kepala(6, 'Komitmen & Pernyataan') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <p class="mb-2 text-sm font-medium text-teks">Saya berkomitmen untuk:</p>
                    <ol class="list-inside list-decimal space-y-1 text-sm text-teks">
                        <?php foreach ($komitmen as $k): ?><li><?= esc($k) ?></li><?php endforeach; ?>
                    </ol>
                </div>
                <div class="rounded-lg border border-garis bg-permukaan p-4 text-sm leading-relaxed text-teks">
                    Demikian surat pernyataan ini saya buat dengan sebenarnya. Apabila di kemudian hari saya tidak melaksanakan tugas sesuai komitmen yang telah disepakati, saya bersedia menerima pembinaan dan ketentuan yang berlaku di sekolah.
                </div>
                <?= $centang('komitmen_setuju', '1', (bool) $fv('komitmen_setuju'), '<span class="font-medium">Saya menyatakan <b>bersedia</b> melaksanakan tugas mengajar T.P. ' . $tahun . ' dan menyetujui seluruh komitmen di atas. <span class="tanda-wajib">*</span></span>', 'border-brand-200 bg-brand-50') ?>
            </div>
        </section>

        <!-- NAVIGASI -->
        <div class="mt-6 space-y-3">
            <p id="submitHint" class="hidden text-center text-sm text-teks-2">Lengkapi data wajib untuk <?= $isEdit ? 'menyimpan' : 'mengirim' ?>.</p>
            <div class="flex items-center justify-between gap-2 sm:gap-3">
                <button type="button" id="prevBtn" class="tombol tombol-netral tombol-besar invisible"><?= ikon('angle-left') ?>Kembali</button>
                <button type="button" id="nextBtn" class="tombol tombol-utama tombol-besar">Lanjut<?= ikon('angle-right') ?></button>
                <button type="submit" id="submitBtn" class="tombol tombol-utama tombol-besar hidden" data-teks-proses="<?= $isEdit ? 'Menyimpan...' : 'Mengirim...' ?>"><?= ikon('check') ?><?= $isEdit ? 'Simpan Perbaikan' : 'Kirim' ?></button>
            </div>
        </div>
    </form>
</div>

<!-- Data awal tabel mata pelajaran (isian lama setelah galat / data revisi) — data, bukan skrip yang dijalankan (siap CSP) -->
<script type="application/json" id="data-kesediaan"><?= json_encode([
    'oldMapel'  => old('mapel') ?: [],
    'oldKelas'  => old('kelas') ?: [],
    'editMapel' => $d['mapel_diampu'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/kesediaan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/kesediaan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
