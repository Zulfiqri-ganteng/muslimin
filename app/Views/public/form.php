<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<?php
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
    $tugasChecked= (old('tugas_bersedia') ?? (! empty($d['tugas_tambahan']) ? '1' : '')) ? true : false;
    $selJam      = old('jam_kesediaan') ?? ($d['kesediaan_jam'] ?? []);
    $selHari     = old('hari') ?? ($d['ketersediaan_hari'] ?? []);
?>

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10" id="formTop">

    <!-- HEADER -->
    <div class="bg-gradient-to-br from-brand-700 to-brand-900 rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-8 sm:px-10 sm:py-10 text-center text-white">
            <?php if (! empty($setting['logo'])): ?>
                <span class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-lg ring-4 ring-white/15">
                    <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" alt="Logo" class="h-14 w-14 object-contain">
                </span>
            <?php endif; ?>
            <p class="text-brand-200 text-sm font-medium tracking-wide uppercase"><?= esc($setting['school_name'] ?? '') ?></p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold leading-tight">Format Kesediaan Guru Mengajar</h1>
            <span class="inline-block mt-3 bg-gold-400 text-brand-900 text-sm font-bold px-4 py-1.5 rounded-full">Tahun Pelajaran <?= $tahun ?></span>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="mt-5 rounded-xl bg-amber-50 border border-amber-200 px-5 py-4">
            <p class="font-semibold text-amber-800">✎ Anda sedang memperbaiki data kesediaan.</p>
            <?php if (! empty($adminNote)): ?><p class="mt-1 text-sm text-amber-700"><span class="font-medium">Catatan admin:</span> <?= esc($adminNote) ?></p><?php endif; ?>
            <p class="mt-1 text-xs text-amber-600">Perbaiki seperlunya, lalu simpan. Tautan ini hanya berlaku satu kali.</p>
        </div>
    <?php endif; ?>

    <?php if (session('error') || ! empty($err)): ?>
        <div class="mt-5 rounded-xl bg-red-50 border border-red-200 px-5 py-4">
            <p class="font-semibold text-red-700">Terdapat kesalahan pada isian:</p>
            <ul class="mt-1 list-disc list-inside text-sm text-red-600 space-y-0.5">
                <?php if (session('error')): ?><li><?= esc(session('error')) ?></li><?php endif; ?>
                <?php foreach ($err as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- STEPPER -->
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
                <button type="button" class="step-dot flex-1" data-goto="<?= $i ?>"><span class="dot-circle"><?= $i + 1 ?></span><span class="dot-label"><?= esc($lbl) ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>

    <form action="<?= $formAction ?>" method="post" id="formKesediaan" class="mt-6">
        <?= csrf_field() ?>

        <!-- STEP 1 — KESEDIAAN -->
        <section class="wizard-step bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Kesediaan">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">1</span>
                <h2 class="text-white font-bold text-lg">Kesediaan Mengisi</h2>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label class="lbl">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_lengkap" value="<?= esc($fv('nama_lengkap'), 'attr') ?>" class="inp" placeholder="Nama lengkap beserta gelar">
                </div>
                <div>
                    <label class="lbl">Apakah Anda bersedia mengisi formulir kesediaan guru mengajar T.P. <?= $tahun ?>? <span class="text-red-500">*</span></label>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="radio-pill">
                            <input type="radio" name="bersedia" value="ya" class="peer sr-only" <?= $selBersedia === 'ya' ? 'checked' : '' ?>>
                            <span class="pill-label !block text-center !px-3">✓ Ya, saya bersedia</span>
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="bersedia" value="tidak" class="peer sr-only" <?= $selBersedia === 'tidak' ? 'checked' : '' ?>>
                            <span class="pill-label !block text-center !px-3">Tidak bersedia</span>
                        </label>
                    </div>
                </div>
                <div id="declineMsg" class="hidden rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    Jika Anda <b>tidak bersedia</b>, maka Anda harus <b>konfirmasi kepada pihak sekolah</b>. Klik <b>Kirim</b> untuk mencatat jawaban Anda.
                </div>
            </div>
        </section>

        <!-- STEP 2 — IDENTITAS -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Identitas Guru">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">2</span>
                <h2 class="text-white font-bold text-lg">Identitas Guru</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="lbl">Nomor HP / WhatsApp <span class="text-red-500">*</span></label>
                    <input type="text" name="nomor_hp" value="<?= esc($fv('nomor_hp'), 'attr') ?>" class="inp" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lbl">Pendidikan Terakhir</label>
                    <input type="text" name="pendidikan_terakhir" value="<?= esc($fv('pendidikan_terakhir'), 'attr') ?>" class="inp" placeholder="Contoh: S1 Pendidikan Matematika">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Guru Mapel</label>
                    <input type="text" name="guru_mapel" value="<?= esc($fv('guru_mapel'), 'attr') ?>" class="inp" placeholder="Contoh: Matematika">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Status Kepegawaian <span class="text-red-500">*</span></label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $st): ?>
                            <label class="radio-pill">
                                <input type="radio" name="status_kepegawaian" value="<?= $st ?>" class="peer sr-only" <?= $selStatus === $st ? 'checked' : '' ?>>
                                <span class="pill-label"><?= $st ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- STEP 3 — MATA PELAJARAN -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Mata Pelajaran yang Diampu">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">3</span>
                <h2 class="text-white font-bold text-lg">Mata Pelajaran yang Diampu</h2>
            </div>
            <div class="p-6">
                <div class="rounded-xl bg-brand-50 border border-brand-100 px-4 py-3 text-sm text-brand-800 mb-4">
                    Bersedia melaksanakan tugas mengajar pada Tahun Pelajaran <?= $tahun ?> sesuai penugasan yang diberikan oleh Kepala Sekolah.
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 text-left">
                                <th class="px-3 py-2 rounded-l-lg w-10">No</th>
                                <th class="px-3 py-2">Mata Pelajaran</th>
                                <th class="px-3 py-2 w-40">Kelas</th>
                                <th class="px-3 py-2 rounded-r-lg w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="mapelBody"></tbody>
                    </table>
                </div>
                <button type="button" id="addMapel" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah baris mata pelajaran
                </button>
            </div>
        </section>

        <!-- STEP 4 — TUGAS TAMBAHAN & JAM MENGAJAR -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Tugas Tambahan & Jam Mengajar">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">4</span>
                <h2 class="text-white font-bold text-lg">Tugas Tambahan &amp; Jam Mengajar</h2>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <p class="text-sm font-semibold text-slate-700 mb-2">Kesediaan Tugas Tambahan</p>
                    <label class="check-card !items-start bg-brand-50 border-brand-200">
                        <input type="checkbox" name="tugas_bersedia" value="1" class="peer sr-only" <?= $tugasChecked ? 'checked' : '' ?>>
                        <span class="check-box mt-0.5"></span>
                        <span class="text-sm text-slate-700 font-medium">Saya bersedia menerima tugas tambahan sesuai kebutuhan sekolah.</span>
                    </label>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-700 mb-2">Kesediaan Jam Mengajar</p>
                    <div class="space-y-3">
                        <?php foreach ($jamOpt as $j): ?>
                            <label class="check-card">
                                <input type="checkbox" name="jam_kesediaan[]" value="<?= esc($j, 'attr') ?>" class="peer sr-only" <?= in_array($j, $selJam, true) ? 'checked' : '' ?>>
                                <span class="check-box"></span>
                                <span class="text-sm text-slate-700"><?= esc($j) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- STEP 5 — LAMPIRAN -->
        <section class="wizard-step hidden bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" data-title="Lampiran Kesediaan">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">5</span>
                <h2 class="text-white font-bold text-lg">Lampiran Kesediaan</h2>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">📅 Ketersediaan Hari Mengajar</h3>
                    <p class="text-xs text-slate-400 mb-3">Centang sesi yang Anda bersedia mengajar pada tiap hari.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($hariList as $h): ?>
                            <div class="rounded-lg border border-slate-200 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-700 mb-2"><?= $h ?></p>
                                <div class="flex gap-2">
                                    <?php foreach ($sesiList as $sesi): ?>
                                        <label class="check-card flex-1 !py-2">
                                            <input type="checkbox" name="hari[<?= $h ?>][]" value="<?= $sesi ?>" class="peer sr-only" <?= in_array($sesi, $selHari[$h] ?? [], true) ? 'checked' : '' ?>>
                                            <span class="check-box"></span>
                                            <span class="text-sm text-slate-700"><?= $sesi ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">✎ Keterangan Tambahan</h3>
                    <textarea name="keterangan_tambahan" rows="3" class="inp" placeholder="Catatan / keterangan tambahan (opsional)"><?= esc($fv('keterangan_tambahan')) ?></textarea>
                </div>
            </div>
        </section>

        <!-- STEP 6 — KOMITMEN & PERNYATAAN -->
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
                    <input type="checkbox" name="komitmen_setuju" value="1" class="peer sr-only" <?= $fv('komitmen_setuju') ? 'checked' : '' ?>>
                    <span class="check-box mt-0.5"></span>
                    <span class="text-sm text-slate-700 font-medium">Saya menyatakan <b>bersedia</b> melaksanakan tugas mengajar T.P. <?= $tahun ?> dan menyetujui seluruh komitmen di atas. <span class="text-red-500">*</span></span>
                </label>
            </div>
        </section>

        <!-- NAVIGASI -->
        <div class="mt-6 space-y-3">
            <p id="submitHint" class="hidden text-center text-xs text-slate-400 leading-snug">Lengkapi data wajib untuk <?= $isEdit ? 'menyimpan' : 'mengirim' ?>.</p>
            <div class="flex items-center justify-between gap-2 sm:gap-3">
                <button type="button" id="prevBtn" class="btn-nav-secondary invisible">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </button>
                <button type="button" id="nextBtn" class="btn-nav-primary">Lanjut
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="submit" id="submitBtn" class="btn-nav-gold hidden">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?= $isEdit ? 'Simpan Perbaikan' : 'Kirim' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    /* ---- Tabel Mata Pelajaran (tanpa jam) ---- */
    const oldMapel  = <?= json_encode(old('mapel') ?: []) ?>;
    const oldKelas  = <?= json_encode(old('kelas') ?: []) ?>;
    const editMapel = <?= json_encode($d['mapel_diampu'] ?? []) ?>;
    const body = document.getElementById('mapelBody');

    function renumber() { body.querySelectorAll('tr').forEach((tr, i) => tr.querySelector('.row-no').textContent = i + 1); }
    function addRow(mapel = '', kelas = '') {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100';
        tr.innerHTML = `
            <td class="px-3 py-2 text-center text-slate-500 row-no"></td>
            <td class="px-2 py-2"><input type="text" name="mapel[]" value="${String(mapel).replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="Mata pelajaran"></td>
            <td class="px-2 py-2"><input type="text" name="kelas[]" value="${String(kelas).replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="X / XI / XII"></td>
            <td class="px-2 py-2 text-center"><button type="button" class="del-row text-slate-300 hover:text-red-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></td>`;
        body.appendChild(tr);
        tr.querySelector('.del-row').addEventListener('click', () => { if (body.children.length > 1) { tr.remove(); renumber(); } });
        renumber();
    }
    document.getElementById('addMapel').addEventListener('click', () => addRow());
    if (oldMapel.length) { oldMapel.forEach((m, i) => addRow(m || '', oldKelas[i] || '')); }
    else if (editMapel.length) { editMapel.forEach(m => addRow(m.mapel || '', m.kelas || '')); }
    else { addRow(); addRow(); addRow(); }

    /* ---- Wizard ---- */
    const steps     = Array.from(document.querySelectorAll('.wizard-step'));
    const dots      = Array.from(document.querySelectorAll('.step-dot'));
    const prevBtn   = document.getElementById('prevBtn');
    const nextBtn   = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const submitHint= document.getElementById('submitHint');
    const declineMsg= document.getElementById('declineMsg');
    const curStepEl = document.getElementById('curStep');
    const titleEl   = document.getElementById('stepTitle');
    const barEl     = document.getElementById('progressBar');
    const formTop   = document.getElementById('formTop');
    const formEl    = document.getElementById('formKesediaan');
    let current = 0;

    const valOf   = (n) => { const e = formEl.querySelector('[name="' + n + '"]'); return e ? String(e.value).trim() : ''; };
    const radioOf = (n) => { const r = formEl.querySelector('input[name="' + n + '"]:checked'); return r ? r.value : ''; };
    const willing = () => radioOf('bersedia');
    const namaOk  = () => valOf('nama_lengkap') !== '';
    function isComplete() {
        return namaOk() && willing() === 'ya' && valOf('nomor_hp') !== '' &&
               radioOf('status_kepegawaian') !== '' && formEl.querySelector('[name="komitmen_setuju"]').checked;
    }

    function render(scroll = true) {
        steps.forEach((s, i) => s.classList.toggle('hidden', i !== current));
        dots.forEach((d, i) => { d.classList.toggle('is-active', i === current); d.classList.toggle('is-done', i < current); });
        curStepEl.textContent = current + 1;
        titleEl.textContent   = steps[current].dataset.title.replace('&amp;', '&');
        barEl.style.width     = ((current + 1) / steps.length * 100) + '%';
        prevBtn.classList.toggle('invisible', current === 0);
        updateNav();
        const el = steps[current];
        el.classList.remove('step-anim'); void el.offsetWidth; el.classList.add('step-anim');
        if (scroll) window.scrollTo({ top: formTop.offsetTop - 16, behavior: 'smooth' });
    }

    function updateNav() {
        const last = current === steps.length - 1;
        // Mode "tidak bersedia" hanya di langkah 1
        if (current === 0 && willing() === 'tidak') {
            declineMsg.classList.remove('hidden');
            nextBtn.classList.add('hidden');
            const ok = namaOk();
            submitBtn.classList.toggle('hidden', !ok);
            submitHint.classList.toggle('hidden', ok);
            return;
        }
        declineMsg.classList.add('hidden');
        if (!last) {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
            submitHint.classList.add('hidden');
        } else {
            nextBtn.classList.add('hidden');
            const ok = isComplete();
            if (ok && submitBtn.classList.contains('hidden')) {
                submitBtn.classList.remove('hidden');
                submitBtn.classList.remove('pop-anim'); void submitBtn.offsetWidth; submitBtn.classList.add('pop-anim');
            } else if (!ok) {
                submitBtn.classList.add('hidden');
            }
            submitHint.classList.toggle('hidden', ok);
        }
    }

    function validateStep(idx) {
        if (idx === 0) {
            const nm = formEl.querySelector('[name="nama_lengkap"]');
            if (!nm.value.trim()) { alert('Mohon isi Nama Lengkap.'); nm.focus(); return false; }
            if (willing() === '') { alert('Silakan pilih kesediaan Anda terlebih dahulu.'); return false; }
        }
        if (idx === 1) {
            const hp = formEl.querySelector('[name="nomor_hp"]');
            if (!hp.value.trim()) { alert('Mohon isi Nomor HP / WhatsApp.'); hp.focus(); return false; }
            if (radioOf('status_kepegawaian') === '') { alert('Silakan pilih Status Kepegawaian.'); return false; }
        }
        return true;
    }

    nextBtn.addEventListener('click', () => { if (validateStep(current) && current < steps.length - 1) { current++; render(); } });
    prevBtn.addEventListener('click', () => { if (current > 0) { current--; render(); } });
    dots.forEach((d, i) => d.addEventListener('click', () => { if (i < current) { current = i; render(); } }));
    formEl.addEventListener('input', updateNav);
    formEl.addEventListener('change', updateNav);

    render(false);

    formEl.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-70', 'cursor-wait');
        submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> <?= $isEdit ? 'Menyimpan...' : 'Mengirim...' ?>';
    });
</script>
<?= $this->endSection() ?>
