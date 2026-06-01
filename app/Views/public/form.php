<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<?php
    $tahun     = esc($setting['academic_year'] ?? '2026/2027');
    $tugasOpt  = [
        'Wali Kelas', 'Pembina Ekstrakurikuler', 'Koordinator Program',
        'Ketua Kompetensi Keahlian', 'Tim Kurikulum', 'Tim Penjaminan Mutu',
        'Operator Sekolah', 'Tim IT Sekolah',
    ];
    $jamOpt = [
        'Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.',
        'Bersedia mengajar lintas kelas/program keahlian sesuai kompetensi.',
        'Bersedia mengajar pada jadwal pagi maupun siang sesuai kebutuhan sekolah.',
    ];
    $hariList   = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $komitmen   = [
        'Melaksanakan proses pembelajaran sesuai ketentuan yang berlaku.',
        'Menyusun perangkat ajar secara lengkap dan tepat waktu.',
        'Melaksanakan asesmen dan evaluasi pembelajaran secara objektif.',
        'Menjaga disiplin, etika profesi, dan nama baik sekolah.',
        'Mendukung seluruh program sekolah.',
        'Mengikuti rapat, pelatihan, workshop, dan kegiatan sekolah yang ditugaskan.',
        'Melaksanakan tugas tambahan dengan penuh tanggung jawab.',
        'Mencapai target kinerja yang ditetapkan sekolah.',
    ];
    $stepLabels = ['Identitas', 'Mata Pelajaran', 'Tugas Tambahan', 'Jam Mengajar', 'Lampiran', 'Pernyataan'];
    $err = session('errors') ?? [];

    // ---- Mode: pengisian baru ATAU revisi (ter-isi data lama) ----
    $d          = $data ?? [];                       // data lama (sudah di-decode) saat mode revisi
    $isEdit     = ! empty($editToken);
    $formAction = $isEdit ? site_url('revisi/' . $editToken) : site_url('kirim');

    // Nilai field: utamakan old() (saat validasi gagal), lalu data lama
    $fv = static function (string $field, $default = '') use ($d) {
        $o = old($field);
        return $o !== null ? $o : ($d[$field] ?? $default);
    };

    // Pilihan yang sudah ter-set (radio / checkbox / array)
    $selStatus = old('status_kepegawaian') ?? ($d['status_kepegawaian'] ?? '');
    $selTugas  = old('tugas')              ?? ($d['tugas_tambahan'] ?? []);
    $selJam    = old('jam_kesediaan')      ?? ($d['kesediaan_jam'] ?? []);
    $selHari   = old('hari')               ?? ($d['ketersediaan_hari'] ?? []);
    $selPref   = old('pref');
    if ($selPref === null) {
        $selPref = [];
        foreach (($d['preferensi'] ?? []) as $p) {
            $selPref[$p['prioritas']] = $p['mapel'];
        }
    }
?>

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10" id="formTop">

    <!-- ===================== HEADER ===================== -->
    <div class="bg-gradient-to-br from-brand-700 to-brand-900 rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-8 sm:px-10 sm:py-10 text-center text-white relative">
            <?php if (! empty($setting['logo'])): ?>
                <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" alt="Logo" class="h-16 mx-auto mb-4 object-contain">
            <?php endif; ?>
            <p class="text-brand-200 text-sm font-medium tracking-wide uppercase"><?= esc($setting['school_name'] ?? '') ?></p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold leading-tight">Format Kesediaan Guru Mengajar</h1>
            <span class="inline-block mt-3 bg-gold-400 text-brand-900 text-sm font-bold px-4 py-1.5 rounded-full">
                Tahun Pelajaran <?= $tahun ?>
            </span>
            <p class="mt-4 text-brand-100 text-sm max-w-xl mx-auto leading-relaxed">
                <?= esc($setting['form_intro'] ?? 'Surat pernyataan kesediaan guru untuk melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.') ?>
            </p>
        </div>
    </div>

    <!-- ===================== BANNER MODE REVISI ===================== -->
    <?php if ($isEdit): ?>
        <div class="mt-5 rounded-xl bg-amber-50 border border-amber-200 px-5 py-4">
            <p class="font-semibold text-amber-800">✎ Anda sedang memperbaiki data kesediaan.</p>
            <?php if (! empty($adminNote)): ?>
                <p class="mt-1 text-sm text-amber-700"><span class="font-medium">Catatan admin:</span> <?= esc($adminNote) ?></p>
            <?php endif; ?>
            <p class="mt-1 text-xs text-amber-600">Perbaiki bagian yang perlu, lalu simpan. Tautan ini hanya berlaku satu kali.</p>
        </div>
    <?php endif; ?>

    <!-- ===================== ALERT ERROR ===================== -->
    <?php if (session('error') || ! empty($err)): ?>
        <div class="mt-5 rounded-xl bg-red-50 border border-red-200 px-5 py-4">
            <p class="font-semibold text-red-700">Terdapat kesalahan pada isian:</p>
            <ul class="mt-1 list-disc list-inside text-sm text-red-600 space-y-0.5">
                <?php if (session('error')): ?><li><?= esc(session('error')) ?></li><?php endif; ?>
                <?php foreach ($err as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ===================== STEPPER ===================== -->
    <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-brand-700">Langkah <span id="curStep">1</span> dari <?= count($stepLabels) ?></p>
            <p class="text-xs font-medium text-slate-400" id="stepTitle"><?= esc($stepLabels[0]) ?></p>
        </div>
        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
            <div id="progressBar" class="h-full bg-brand-600 transition-all duration-300" style="width: <?= round(100 / count($stepLabels)) ?>%"></div>
        </div>
        <div class="mt-4 hidden sm:flex items-start justify-between gap-1" id="stepDots">
            <?php foreach ($stepLabels as $i => $lbl): ?>
                <button type="button" class="step-dot flex-1" data-goto="<?= $i ?>">
                    <span class="dot-circle"><?= $i + 1 ?></span>
                    <span class="dot-label"><?= esc($lbl) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <form action="<?= $formAction ?>" method="post" enctype="multipart/form-data" id="formKesediaan" class="mt-6">
        <?= csrf_field() ?>

        <!-- ===================== STEP 1 — IDENTITAS GURU ===================== -->
        <section class="wizard-step bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Identitas Guru">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">1</span>
                <h2 class="text-white font-bold text-lg">Identitas Guru</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="lbl">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_lengkap" value="<?= esc($fv('nama_lengkap'), 'attr') ?>" required class="inp" placeholder="Nama lengkap beserta gelar">
                </div>
                <div>
                    <label class="lbl">NIP / NUPTK <span class="text-red-500">*</span></label>
                    <input type="text" name="nip_nuptk" value="<?= esc($fv('nip_nuptk'), 'attr') ?>" required class="inp" placeholder="Nomor NIP / NUPTK">
                    <?php if (! $isEdit): ?><p class="text-xs text-slate-400 mt-1">Satu NIP/NUPTK hanya bisa mengisi 1 kali.</p><?php endif; ?>
                </div>
                <div>
                    <label class="lbl">Nomor HP / WhatsApp <span class="text-red-500">*</span></label>
                    <input type="text" name="nomor_hp" value="<?= esc($fv('nomor_hp'), 'attr') ?>" required class="inp" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lbl">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="<?= esc($fv('tempat_lahir'), 'attr') ?>" class="inp" placeholder="Kota kelahiran">
                </div>
                <div>
                    <label class="lbl">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="<?= esc($fv('tanggal_lahir'), 'attr') ?>" class="inp">
                </div>
                <div>
                    <label class="lbl">Pendidikan Terakhir</label>
                    <input type="text" name="pendidikan_terakhir" value="<?= esc($fv('pendidikan_terakhir'), 'attr') ?>" class="inp" placeholder="Contoh: S1 Pendidikan Matematika">
                </div>
                <div>
                    <label class="lbl">Jabatan — Guru Mata Pelajaran</label>
                    <input type="text" name="guru_mapel" value="<?= esc($fv('guru_mapel'), 'attr') ?>" class="inp" placeholder="Contoh: Matematika">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Status Kepegawaian <span class="text-red-500">*</span></label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $st): ?>
                            <label class="radio-pill">
                                <input type="radio" name="status_kepegawaian" value="<?= $st ?>" class="peer sr-only" <?= $selStatus === $st ? 'checked' : '' ?> required>
                                <span class="pill-label"><?= $st ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== STEP 2 — MATA PELAJARAN YANG DIAMPU ===================== -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Mata Pelajaran yang Diampu">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">2</span>
                <h2 class="text-white font-bold text-lg">Mata Pelajaran yang Diampu</h2>
            </div>
            <div class="p-6">
                <div class="rounded-xl bg-brand-50 border border-brand-100 px-4 py-3 text-sm text-brand-800 mb-4">
                    Bersedia melaksanakan tugas mengajar pada Tahun Pelajaran <?= $tahun ?> sesuai penugasan yang diberikan oleh Kepala Sekolah.
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="mapelTable">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 text-left">
                                <th class="px-3 py-2 rounded-l-lg w-10">No</th>
                                <th class="px-3 py-2">Mata Pelajaran</th>
                                <th class="px-3 py-2 w-32">Kelas</th>
                                <th class="px-3 py-2 w-28">Jam/Minggu</th>
                                <th class="px-3 py-2 rounded-r-lg w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="mapelBody"><!-- diisi JS --></tbody>
                        <tfoot>
                            <tr class="font-semibold text-slate-700">
                                <td colspan="3" class="px-3 py-2 text-right">TOTAL JAM / MINGGU</td>
                                <td class="px-3 py-2 text-center"><span id="totalJam">0</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" id="addMapel" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah baris mata pelajaran
                </button>
            </div>
        </section>

        <!-- ===================== STEP 3 — TUGAS TAMBAHAN ===================== -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Kesediaan Tugas Tambahan">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">3</span>
                <h2 class="text-white font-bold text-lg">Kesediaan Tugas Tambahan</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-500 mb-4">Saya bersedia menerima tugas tambahan sesuai kebutuhan sekolah (centang yang sesuai):</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($tugasOpt as $t): ?>
                        <label class="check-card">
                            <input type="checkbox" name="tugas[]" value="<?= esc($t, 'attr') ?>" class="peer sr-only" <?= in_array($t, $selTugas, true) ? 'checked' : '' ?>>
                            <span class="check-box"></span>
                            <span class="text-sm text-slate-700"><?= esc($t) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <label class="lbl">Tugas lainnya</label>
                    <input type="text" name="tugas_lainnya" value="<?= esc($fv('tugas_lainnya'), 'attr') ?>" class="inp" placeholder="Tugas tambahan lain (opsional)">
                </div>
            </div>
        </section>

        <!-- ===================== STEP 4 — KESEDIAAN JAM MENGAJAR ===================== -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Kesediaan Jam Mengajar">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">4</span>
                <h2 class="text-white font-bold text-lg">Kesediaan Jam Mengajar</h2>
            </div>
            <div class="p-6 space-y-3">
                <?php foreach ($jamOpt as $j): ?>
                    <label class="check-card">
                        <input type="checkbox" name="jam_kesediaan[]" value="<?= esc($j, 'attr') ?>" class="peer sr-only" <?= in_array($j, $selJam, true) ? 'checked' : '' ?>>
                        <span class="check-box"></span>
                        <span class="text-sm text-slate-700"><?= esc($j) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ===================== STEP 5 — LAMPIRAN ===================== -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Lampiran Kesediaan">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">5</span>
                <h2 class="text-white font-bold text-lg">Lampiran Kesediaan</h2>
            </div>
            <div class="p-6 space-y-6">
                <!-- A. Preferensi Mata Pelajaran -->
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">
                        <span class="text-gold-500">★</span> A. Preferensi Mata Pelajaran
                    </h3>
                    <div class="space-y-2">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 text-sm font-bold"><?= $i ?></span>
                                <input type="text" name="pref[<?= $i ?>]" value="<?= esc($selPref[$i] ?? '', 'attr') ?>" class="inp" placeholder="Prioritas <?= $i ?> &mdash; mata pelajaran">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <!-- B. Ketersediaan Hari -->
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">📅 B. Ketersediaan Hari Mengajar</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <?php foreach ($hariList as $h): ?>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-2">
                                <span class="text-sm font-medium text-slate-700"><?= $h ?></span>
                                <div class="flex gap-1.5">
                                    <?php foreach (['Ya', 'Tidak'] as $opt): ?>
                                        <label class="radio-pill-sm">
                                            <input type="radio" name="hari[<?= $h ?>]" value="<?= $opt ?>" class="peer sr-only" <?= (($selHari[$h] ?? '') === $opt) ? 'checked' : '' ?>>
                                            <span class="pill-label-sm <?= $opt === 'Ya' ? 'data-ya' : 'data-no' ?>"><?= $opt ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- C. Keterangan Tambahan -->
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">✎ C. Keterangan Tambahan</h3>
                    <textarea name="keterangan_tambahan" rows="3" class="inp" placeholder="Catatan / keterangan tambahan (opsional)"><?= esc($fv('keterangan_tambahan')) ?></textarea>
                </div>
            </div>
        </section>

        <!-- ===================== STEP 6 — KOMITMEN & PERNYATAAN ===================== -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Komitmen &amp; Pernyataan">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">6</span>
                <h2 class="text-white font-bold text-lg">Komitmen &amp; Pernyataan</h2>
            </div>
            <div class="p-6">
                <p class="text-sm font-medium text-slate-600 mb-2">Saya berkomitmen untuk:</p>
                <ol class="list-decimal list-inside space-y-1 text-sm text-slate-600 mb-5">
                    <?php foreach ($komitmen as $k): ?><li><?= esc($k) ?></li><?php endforeach; ?>
                </ol>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600 leading-relaxed">
                    Demikian surat pernyataan ini saya buat dengan sebenarnya. Apabila di kemudian hari saya tidak melaksanakan tugas sesuai komitmen yang telah disepakati, saya bersedia menerima pembinaan dan ketentuan yang berlaku di sekolah.
                </div>

                <label class="check-card mt-5 !items-start bg-brand-50 border-brand-200">
                    <input type="checkbox" name="komitmen_setuju" value="1" class="peer sr-only" required <?= $fv('komitmen_setuju') ? 'checked' : '' ?>>
                    <span class="check-box mt-0.5"></span>
                    <span class="text-sm text-slate-700 font-medium">Saya menyatakan <b>bersedia</b> melaksanakan tugas mengajar T.P. <?= $tahun ?> dan menyetujui seluruh komitmen di atas. <span class="text-red-500">*</span></span>
                </label>
            </div>
        </section>

        <!-- ===================== NAVIGASI WIZARD ===================== -->
        <div class="mt-6 flex items-center justify-between gap-3">
            <button type="button" id="prevBtn" class="btn-nav-secondary invisible">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Sebelumnya
            </button>
            <div class="flex items-center gap-3">
                <span id="submitHint" class="hidden text-right text-xs text-slate-400 max-w-[170px] leading-snug">
                    Lengkapi data wajib &amp; centang pernyataan untuk <?= $isEdit ? 'menyimpan' : 'mengirim' ?>.
                </span>
                <button type="button" id="nextBtn" class="btn-nav-primary">
                    Lanjut
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="submit" id="submitBtn" class="btn-nav-gold hidden">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?= $isEdit ? 'Simpan Perbaikan' : 'Kirim Kesediaan' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<style type="text/tailwindcss">
    .lbl { @apply block text-sm font-medium text-slate-600 mb-1.5; }
    .inp { @apply w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition; }
    .radio-pill .pill-label { @apply cursor-pointer inline-block rounded-lg border-2 border-slate-200 px-5 py-2 text-sm font-semibold text-slate-500 transition; }
    .radio-pill input:checked + .pill-label { @apply border-brand-600 bg-brand-600 text-white; }
    .radio-pill-sm .pill-label-sm { @apply cursor-pointer inline-block rounded-md border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 transition; }
    .radio-pill-sm input:checked + .data-ya { @apply border-green-600 bg-green-600 text-white; }
    .radio-pill-sm input:checked + .data-no { @apply border-red-500 bg-red-500 text-white; }
    .check-card { @apply flex items-center gap-3 cursor-pointer rounded-lg border border-slate-200 px-4 py-3 hover:bg-slate-50 transition; }
    .check-box { @apply flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 border-slate-300 transition; }
    .check-card input:checked ~ .check-box { @apply border-brand-600 bg-brand-600; }
    .check-card input:checked ~ .check-box::after { content: '✓'; @apply text-white text-xs font-bold; }
    .check-card:has(input:checked) { @apply border-brand-300 bg-brand-50; }

    /* Stepper */
    .step-dot .dot-circle { @apply mx-auto flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-200 bg-white text-sm font-bold text-slate-400 transition; }
    .step-dot .dot-label { @apply block mt-1.5 text-center text-[11px] font-medium text-slate-400 transition; }
    .step-dot.is-active .dot-circle { @apply border-brand-600 bg-brand-600 text-white; }
    .step-dot.is-done .dot-circle { @apply border-brand-600 bg-brand-100 text-brand-700; }
    .step-dot.is-active .dot-label, .step-dot.is-done .dot-label { @apply text-brand-700; }
    .step-dot.is-done { @apply cursor-pointer; }

    /* Tombol navigasi */
    .btn-nav-secondary { @apply inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-95; }
    .btn-nav-primary { @apply inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-7 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/20 hover:bg-brand-700 transition active:scale-95; }
    .btn-nav-gold { @apply inline-flex items-center gap-2 rounded-xl bg-gold-500 px-7 py-3 text-sm font-bold text-brand-900 shadow-lg shadow-gold-500/20 hover:bg-gold-400 transition active:scale-95; }
</style>

<style>
    /* Animasi transisi antar-langkah & kemunculan tombol */
    @keyframes stepIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes popIn  { 0% { opacity: 0; transform: scale(.85); } 60% { transform: scale(1.04); } 100% { opacity: 1; transform: scale(1); } }
    .step-anim { animation: stepIn .38s cubic-bezier(.16,.84,.44,1) both; }
    .pop-anim  { animation: popIn .32s cubic-bezier(.16,.84,.44,1) both; }
    @media (prefers-reduced-motion: reduce) { .step-anim, .pop-anim { animation: none; } }
</style>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    /* ---------- Tabel Mata Pelajaran ---------- */
    const oldMapel  = <?= json_encode(old('mapel') ?: []) ?>;
    const oldKelas  = <?= json_encode(old('kelas') ?: []) ?>;
    const oldJamM   = <?= json_encode(old('jam') ?: []) ?>;
    const editMapel = <?= json_encode($d['mapel_diampu'] ?? []) ?>;

    const body    = document.getElementById('mapelBody');
    const totalEl = document.getElementById('totalJam');

    function recalc() {
        let t = 0;
        body.querySelectorAll('input[name="jam[]"]').forEach(i => t += parseInt(i.value) || 0);
        totalEl.textContent = t;
    }
    function renumber() {
        body.querySelectorAll('tr').forEach((tr, i) => tr.querySelector('.row-no').textContent = i + 1);
    }
    function addRow(mapel = '', kelas = '', jam = '') {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100';
        tr.innerHTML = `
            <td class="px-3 py-2 text-center text-slate-500 row-no"></td>
            <td class="px-2 py-2"><input type="text" name="mapel[]" value="${String(mapel).replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="Mata pelajaran"></td>
            <td class="px-2 py-2"><input type="text" name="kelas[]" value="${String(kelas).replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="X / XI"></td>
            <td class="px-2 py-2"><input type="number" min="0" name="jam[]" value="${jam}" class="jam-input w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm text-center focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="0"></td>
            <td class="px-2 py-2 text-center"><button type="button" class="del-row text-slate-300 hover:text-red-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></td>`;
        body.appendChild(tr);
        tr.querySelector('.jam-input').addEventListener('input', recalc);
        tr.querySelector('.del-row').addEventListener('click', () => {
            if (body.children.length > 1) { tr.remove(); renumber(); recalc(); }
        });
        renumber();
    }
    document.getElementById('addMapel').addEventListener('click', () => addRow());

    if (oldMapel.length) {
        oldMapel.forEach((m, i) => addRow(m || '', oldKelas[i] || '', oldJamM[i] || ''));
    } else if (editMapel.length) {
        editMapel.forEach(m => addRow(m.mapel || '', m.kelas || '', m.jam || ''));
    } else {
        addRow(); addRow(); addRow();
    }
    recalc();

    /* ---------- Navigasi Wizard ---------- */
    const steps     = Array.from(document.querySelectorAll('.wizard-step'));
    const dots      = Array.from(document.querySelectorAll('.step-dot'));
    const prevBtn   = document.getElementById('prevBtn');
    const nextBtn   = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const submitHint= document.getElementById('submitHint');
    const curStepEl = document.getElementById('curStep');
    const titleEl   = document.getElementById('stepTitle');
    const barEl     = document.getElementById('progressBar');
    const formTop   = document.getElementById('formTop');
    const formEl    = document.getElementById('formKesediaan');
    let current = 0;

    function render(scroll = true) {
        steps.forEach((s, i) => s.classList.toggle('hidden', i !== current));
        dots.forEach((d, i) => {
            d.classList.toggle('is-active', i === current);
            d.classList.toggle('is-done', i < current);
        });
        curStepEl.textContent = current + 1;
        titleEl.textContent   = steps[current].dataset.title.replace('&amp;', '&');
        barEl.style.width     = ((current + 1) / steps.length * 100) + '%';
        prevBtn.classList.toggle('invisible', current === 0);
        const last = current === steps.length - 1;
        nextBtn.classList.toggle('hidden', last);
        updateSubmitVisibility();

        // animasi: langkah aktif "masuk"
        const el = steps[current];
        el.classList.remove('step-anim');
        void el.offsetWidth;            // paksa reflow agar animasi terulang
        el.classList.add('step-anim');

        if (scroll) window.scrollTo({ top: formTop.offsetTop - 16, behavior: 'smooth' });
    }

    // Cek semua field WAJIB sudah terisi (Nama, NIP, HP, Status, centang pernyataan)
    function isComplete() {
        const req = formEl.querySelectorAll('[required]');
        for (const f of req) {
            if (f.type === 'radio') {
                const nm = (window.CSS && CSS.escape) ? CSS.escape(f.name) : f.name;
                if (!formEl.querySelector('input[name="' + nm + '"]:checked')) return false;
            } else if (f.type === 'checkbox') {
                if (!f.checked) return false;
            } else if (!String(f.value).trim()) {
                return false;
            }
        }
        return true;
    }

    // Tombol "Kirim" hanya muncul di langkah terakhir & saat data wajib lengkap
    function updateSubmitVisibility() {
        const last = current === steps.length - 1;
        if (!last) {
            submitBtn.classList.add('hidden');
            submitHint.classList.add('hidden');
            return;
        }
        const ok = isComplete();
        if (ok) {
            if (submitBtn.classList.contains('hidden')) {
                submitBtn.classList.remove('hidden');
                submitBtn.classList.remove('pop-anim');
                void submitBtn.offsetWidth;
                submitBtn.classList.add('pop-anim');
            }
        } else {
            submitBtn.classList.add('hidden');
        }
        submitHint.classList.toggle('hidden', ok);
    }

    function validateStep(idx) {
        const stepEl = steps[idx];
        const fields = stepEl.querySelectorAll('input:not([type=radio]):not([type=checkbox]), select, textarea');
        for (const f of fields) {
            if (!f.checkValidity()) { f.reportValidity(); return false; }
        }
        const reqRadios = Array.from(stepEl.querySelectorAll('input[type=radio][required]'));
        const names = [...new Set(reqRadios.map(r => r.name))];
        for (const n of names) {
            const sel = 'input[name="' + (window.CSS && CSS.escape ? CSS.escape(n) : n) + '"]:checked';
            if (!stepEl.querySelector(sel)) {
                alert('Silakan pilih ' + (n === 'status_kepegawaian' ? 'Status Kepegawaian' : 'pilihan yang wajib') + ' terlebih dahulu.');
                return false;
            }
        }
        return true;
    }

    nextBtn.addEventListener('click', () => {
        if (!validateStep(current)) return;
        if (current < steps.length - 1) { current++; render(); }
    });
    prevBtn.addEventListener('click', () => {
        if (current > 0) { current--; render(); }
    });
    dots.forEach((d, i) => d.addEventListener('click', () => {
        if (i < current) { current = i; render(); }
    }));

    // Re-cek kelengkapan setiap kali ada perubahan input (untuk tombol Kirim)
    formEl.addEventListener('input', updateSubmitVisibility);
    formEl.addEventListener('change', updateSubmitVisibility);

    render(false);

    /* ---------- Loading state + cegah double submit ---------- */
    formEl.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-70', 'cursor-wait');
        submitBtn.innerHTML =
            '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
            '</svg> <?= $isEdit ? 'Menyimpan...' : 'Mengirim...' ?>';
    });
</script>
<?= $this->endSection() ?>
