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
    $err = session('errors') ?? [];
?>

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10">

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

    <form action="<?= site_url('kirim') ?>" method="post" enctype="multipart/form-data" id="formKesediaan" class="mt-6 space-y-6">
        <?= csrf_field() ?>

        <!-- ===================== A. IDENTITAS GURU ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">1</span>
                <h2 class="text-white font-bold text-lg">Identitas Guru</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="lbl">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_lengkap" value="<?= old('nama_lengkap') ?>" required class="inp" placeholder="Nama lengkap beserta gelar">
                </div>
                <div>
                    <label class="lbl">NIP / NUPTK <span class="text-red-500">*</span></label>
                    <input type="text" name="nip_nuptk" value="<?= old('nip_nuptk') ?>" required class="inp" placeholder="Nomor NIP / NUPTK">
                    <p class="text-xs text-slate-400 mt-1">Satu NIP/NUPTK hanya bisa mengisi 1 kali.</p>
                </div>
                <div>
                    <label class="lbl">Nomor HP / WhatsApp <span class="text-red-500">*</span></label>
                    <input type="text" name="nomor_hp" value="<?= old('nomor_hp') ?>" required class="inp" placeholder="08xxxxxxxxxx">
                </div>
                <div>
                    <label class="lbl">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="<?= old('tempat_lahir') ?>" class="inp" placeholder="Kota kelahiran">
                </div>
                <div>
                    <label class="lbl">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="<?= old('tanggal_lahir') ?>" class="inp">
                </div>
                <div>
                    <label class="lbl">Pendidikan Terakhir</label>
                    <input type="text" name="pendidikan_terakhir" value="<?= old('pendidikan_terakhir') ?>" class="inp" placeholder="Contoh: S1 Pendidikan Matematika">
                </div>
                <div>
                    <label class="lbl">Jabatan — Guru Mata Pelajaran</label>
                    <input type="text" name="guru_mapel" value="<?= old('guru_mapel') ?>" class="inp" placeholder="Contoh: Matematika">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Status Kepegawaian <span class="text-red-500">*</span></label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $st): ?>
                            <label class="radio-pill">
                                <input type="radio" name="status_kepegawaian" value="<?= $st ?>" class="peer sr-only" <?= old('status_kepegawaian') === $st ? 'checked' : '' ?> required>
                                <span class="pill-label"><?= $st ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== B. MATA PELAJARAN YANG DIAMPU ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">2</span>
                <h2 class="text-white font-bold text-lg">Mata Pelajaran yang Diampu</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-500 mb-4">Bersedia melaksanakan tugas mengajar pada Tahun Pelajaran <?= $tahun ?> sesuai penugasan dari Kepala Sekolah.</p>
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

        <!-- ===================== C. TUGAS TAMBAHAN ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">3</span>
                <h2 class="text-white font-bold text-lg">Kesediaan Tugas Tambahan</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-500 mb-4">Saya bersedia menerima tugas tambahan sesuai kebutuhan sekolah (centang yang sesuai):</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php $oldTugas = old('tugas') ?? []; foreach ($tugasOpt as $t): ?>
                        <label class="check-card">
                            <input type="checkbox" name="tugas[]" value="<?= esc($t) ?>" class="peer sr-only" <?= in_array($t, $oldTugas, true) ? 'checked' : '' ?>>
                            <span class="check-box"></span>
                            <span class="text-sm text-slate-700"><?= esc($t) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <label class="lbl">Tugas lainnya</label>
                    <input type="text" name="tugas_lainnya" value="<?= old('tugas_lainnya') ?>" class="inp" placeholder="Tugas tambahan lain (opsional)">
                </div>
            </div>
        </section>

        <!-- ===================== D. KESEDIAAN JAM MENGAJAR ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-brand-700 px-6 py-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-white text-sm font-bold">4</span>
                <h2 class="text-white font-bold text-lg">Kesediaan Jam Mengajar</h2>
            </div>
            <div class="p-6 space-y-3">
                <?php $oldJam = old('jam_kesediaan') ?? []; foreach ($jamOpt as $j): ?>
                    <label class="check-card">
                        <input type="checkbox" name="jam_kesediaan[]" value="<?= esc($j) ?>" class="peer sr-only" <?= in_array($j, $oldJam, true) ? 'checked' : '' ?>>
                        <span class="check-box"></span>
                        <span class="text-sm text-slate-700"><?= esc($j) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ===================== E. LAMPIRAN ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
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
                        <?php $oldPref = old('pref') ?? []; for ($i = 1; $i <= 3; $i++): ?>
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 text-sm font-bold"><?= $i ?></span>
                                <input type="text" name="pref[<?= $i ?>]" value="<?= esc($oldPref[$i] ?? '') ?>" class="inp" placeholder="Prioritas <?= $i ?> &mdash; mata pelajaran">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <!-- B. Ketersediaan Hari -->
                <div>
                    <h3 class="font-semibold text-brand-700 mb-3 flex items-center gap-2">📅 B. Ketersediaan Hari Mengajar</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <?php $oldHari = old('hari') ?? []; foreach ($hariList as $h): ?>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-2">
                                <span class="text-sm font-medium text-slate-700"><?= $h ?></span>
                                <div class="flex gap-1.5">
                                    <?php foreach (['Ya', 'Tidak'] as $v): ?>
                                        <label class="radio-pill-sm">
                                            <input type="radio" name="hari[<?= $h ?>]" value="<?= $v ?>" class="peer sr-only" <?= (($oldHari[$h] ?? '') === $v) ? 'checked' : '' ?>>
                                            <span class="pill-label-sm <?= $v === 'Ya' ? 'data-ya' : 'data-no' ?>"><?= $v ?></span>
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
                    <textarea name="keterangan_tambahan" rows="3" class="inp" placeholder="Catatan / keterangan tambahan (opsional)"><?= old('keterangan_tambahan') ?></textarea>
                </div>
            </div>
        </section>

        <!-- ===================== F. KOMITMEN & PERNYATAAN ===================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
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
                    <input type="checkbox" name="komitmen_setuju" value="1" class="peer sr-only" required <?= old('komitmen_setuju') ? 'checked' : '' ?>>
                    <span class="check-box mt-0.5"></span>
                    <span class="text-sm text-slate-700 font-medium">Saya menyatakan <b>bersedia</b> melaksanakan tugas mengajar T.P. <?= $tahun ?> dan menyetujui seluruh komitmen di atas. <span class="text-red-500">*</span></span>
                </label>
            </div>
        </section>

        <!-- ===================== SUBMIT ===================== -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <p class="text-xs text-slate-400">Pastikan data sudah benar sebelum mengirim.</p>
            <button type="submit" id="submitBtn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gold-500 hover:bg-gold-400 text-brand-900 font-bold px-8 py-3.5 rounded-xl shadow-lg shadow-gold-500/20 transition active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Kirim Kesediaan
            </button>
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
</style>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const oldMapel = <?= json_encode(old('mapel') ?: []) ?>;
    const oldKelas = <?= json_encode(old('kelas') ?: []) ?>;
    const oldJam   = <?= json_encode(old('jam') ?: []) ?>;

    const body     = document.getElementById('mapelBody');
    const totalEl  = document.getElementById('totalJam');

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
            <td class="px-2 py-2"><input type="text" name="mapel[]" value="${mapel.replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="Mata pelajaran"></td>
            <td class="px-2 py-2"><input type="text" name="kelas[]" value="${kelas.replace(/"/g,'&quot;')}" class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500/30 outline-none" placeholder="X / XI"></td>
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

    // Inisialisasi: kembalikan data lama atau 3 baris kosong
    if (oldMapel.length) {
        oldMapel.forEach((m, i) => addRow(m || '', oldKelas[i] || '', oldJam[i] || ''));
    } else {
        addRow(); addRow(); addRow();
    }
    recalc();

    // Cegah double submit
    document.getElementById('formKesediaan').addEventListener('submit', () => {
        const b = document.getElementById('submitBtn');
        b.disabled = true; b.classList.add('opacity-60', 'cursor-wait');
        b.innerHTML = 'Mengirim...';
    });
</script>
<?= $this->endSection() ?>
