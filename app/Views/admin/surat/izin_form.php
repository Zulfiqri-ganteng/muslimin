<?php
/**
 * Formulir Surat Izin ASTS / Surat Izin TKA — buat baru atau ubah.
 *
 * @var string                  $jenis      izin_asts | izin_tka
 * @var string                  $label
 * @var bool                    $ubah       true = mengubah surat yang sudah ada
 * @var array<string, mixed>    $nilai      isian (old() sudah diperhitungkan); 'perusahaan' = daftar kunci yang dicentang
 * @var list<array>             $periode    periode ASTS di menu Ujian (hanya ASTS)
 * @var list<array>             $perusahaan perusahaan yang punya siswa PKL (hanya saat membuat)
 * @var bool                    $perluAcc   jenis ini wajib ACC?
 * @var list<string>            $sesiSaran
 */

use App\Libraries\SuratJenis;

$asts   = $jenis === SuratJenis::ASTS;
$alamat = SuratJenis::alamat($jenis);
$aksi   = $ubah ? site_url($alamat . '/' . (int) $surat['id'] . '/ubah') : site_url($alamat);
$cfg    = [
    'mode' => $nilai['mode'], 'semester' => $nilai['semester'], 'tp' => $nilai['tahun_pelajaran'], 'mulai' => $nilai['tgl_mulai'], 'selesai' => $nilai['tgl_selesai'],
    'tanggalSurat' => $nilai['tanggal_surat'], 'dipilih' => $nilai['perusahaan'],
    'periode' => array_map(static fn (array $p) => ['semester' => $p['semester'], 'tahun' => $p['tahun'], 'mulai' => $p['mulai'], 'selesai' => $p['selesai']], $periode),
    'perusahaan' => array_map(static fn (array $g) => [
        'kunci' => $g['kunci'], 'nama' => $g['nama'], 'kota' => $g['kota'], 'jml' => count($g['siswa']), 'mulai' => $g['mulai'], 'selesai' => $g['selesai'],
        'siswa' => implode(', ', array_slice(array_column($g['siswa'], 'nama'), 0, 4)) . (count($g['siswa']) > 4 ? ', …' : ''),
    ], $perusahaan),
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_izin_form_v1',
    'helpTitle' => $label,
    'helpBody'  => '<p>Surat ini <b>memberi tahu perusahaan tempat siswa PKL</b> bahwa siswa harus mengikuti ' . ($asts ? 'ASTS' : 'TKA') . ' di sekolah, dan memohon dispensasi. Isi tanggalnya, lalu periksa <b>pratinjau</b> &mdash; nama hari dihitung otomatis dari tanggal.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Surat umum</b>: SATU surat (satu nomor) untuk semua perusahaan, persis contoh sekolah &mdash; difotokopi untuk tiap tempat PKL.</li>'
        . '<li><b>Per perusahaan</b>: satu surat untuk tiap perusahaan yang dicentang, memuat nama perusahaan dan daftar siswanya, masing-masing bernomor sendiri. Perusahaan yang periode PKL-nya sudah tercatat tetapi tidak menjangkau tanggal kegiatan diberi tanda.</li>'
        . '<li>Kegiatan, tanggal, dan cakupan yang sama <b>tidak bisa dibuat dua kali</b> (kecuali suratnya sudah dibatalkan).</li>'
        . '<li>' . ($perluAcc ? 'Surat menunggu <b>ACC</b> (' . esc($pemegangAcc ?? 'peran yang berhak') . ', diatur Admin di Sistem → Hak Akses). Setelah disetujui, surat diunduh dalam bentuk Word.' . (! empty($bisaLangsungAcc) ? ' Anda juga memegang hak ACC jenis ini, jadi bisa langsung memakai tombol <b>Simpan &amp; setujui</b>.' : '') : 'Jenis ini saat ini <b>tanpa ACC</b>: surat langsung siap diunduh.') . '</li>'
        . '</ul>',
]) ?>

<a href="<?= $ubah ? site_url('admin/surat/' . (int) $surat['id']) : site_url('admin/surat') ?>" class="rise mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">← <?= $ubah ? 'Kembali ke surat' : 'Daftar Surat' ?></a>

<form method="post" action="<?= esc($aksi, 'attr') ?>" x-data="suratIzinForm" data-config="<?= esc(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" class="mx-auto max-w-4xl space-y-5">
    <?= csrf_field() ?>

    <h1 class="rise text-xl font-bold text-gray-800"><?= $ubah ? 'Ubah ' . esc($label) . ' <span class="font-mono text-sm font-semibold text-gray-400">' . esc($kode) . '</span>' : 'Buat ' . esc($label) ?></h1>

    <section class="rise overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <h3 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-gray-500">Kegiatan</h3>
        <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
            <?php if ($asts && $periode !== []): ?>
                <div class="sm:col-span-2">
                    <label class="lbl" for="pilihPeriode">Isi otomatis dari menu Ujian <span class="font-normal text-gray-400">(opsional)</span></label>
                    <select id="pilihPeriode" x-model="pilihPeriode" @change="ambilPeriode()" class="inp">
                        <option value="">— pilih periode ASTS —</option>
                        <?php foreach ($periode as $i => $p): ?>
                            <option value="<?= (int) $i ?>"><?= esc($p['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Mengisi semester, tahun pelajaran, dan tanggal di bawah. Tanggalnya tetap bisa Anda ubah.</p>
                </div>
            <?php endif; ?>

            <?php if ($asts): ?>
                <div>
                    <label class="lbl" for="semester">Semester <span class="text-red-500">*</span></label>
                    <select id="semester" name="semester" x-model="semester" required class="inp">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="tahun_pelajaran">Tahun pelajaran <span class="text-red-500">*</span></label>
                    <input id="tahun_pelajaran" name="tahun_pelajaran" type="text" x-model="tp" required maxlength="9" placeholder="2026/2027" class="inp">
                </div>
            <?php else: ?>
                <div class="sm:col-span-2">
                    <label class="lbl" for="sesi">Sesi / gelombang <span class="text-red-500">*</span></label>
                    <input id="sesi" name="sesi" type="text" value="<?= esc($nilai['sesi'], 'attr') ?>" required maxlength="40" list="saranSesi" placeholder="Gelombang 1" class="inp">
                    <datalist id="saranSesi"><?php foreach ($sesiSaran as $s): ?><option value="<?= esc($s, 'attr') ?>"><?php endforeach; ?></datalist>
                    <p class="mt-1 text-xs text-gray-400">Tercetak di baris "Sesi" pada surat. Pilih saran atau ketik sendiri.</p>
                </div>
            <?php endif; ?>

            <div>
                <label class="lbl" for="tgl_mulai">Tanggal mulai <span class="text-red-500">*</span></label>
                <input id="tgl_mulai" name="tgl_mulai" type="date" x-model="mulai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="tgl_selesai">Tanggal selesai <span class="text-red-500">*</span></label>
                <input id="tgl_selesai" name="tgl_selesai" type="date" x-model="selesai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="tempat">Tempat</label>
                <input id="tempat" name="tempat" type="text" value="<?= esc($nilai['tempat'], 'attr') ?>" maxlength="100" class="inp">
            </div>
            <div>
                <label class="lbl" for="tanggal_surat">Tanggal surat <span class="text-red-500">*</span></label>
                <input id="tanggal_surat" name="tanggal_surat" type="date" x-model="tanggalSurat" required class="inp">
                <p class="mt-1 text-xs text-gray-400">Menentukan bulan dan tahun pada nomor surat. Tidak boleh setelah kegiatan selesai.</p>
            </div>

            <!-- Pratinjau -->
            <div class="sm:col-span-2 rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-3 text-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-brand-700">Pratinjau di surat</p>
                <template x-if="hariTanggal() !== ''">
                    <div class="mt-1.5 space-y-0.5 text-gray-700">
                        <p><span class="inline-block w-28 text-gray-500">Hari/Tanggal</span>: <b x-text="hariTanggal()"></b></p>
                        <p class="text-xs text-gray-500">Kalimat dispensasi: "…di sekolah pada tanggal <b class="text-gray-700" x-text="tanggalSampai()"></b> sesuai dengan jadwal…"</p>
                    </div>
                </template>
                <p x-show="hariTanggal() === ''" class="mt-1.5 text-xs text-gray-500">Isi tanggal mulai dan selesai untuk melihat pratinjau.</p>
                <p x-show="peringatan() !== ''" x-text="'⚠ ' + peringatan()" class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900"></p>
            </div>
        </div>
    </section>

    <section class="rise overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <h3 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-gray-500">Cakupan surat</h3>
        <div class="space-y-4 px-5 py-5">
            <?php if ($ubah): ?>
                <p class="text-sm text-gray-700">
                    <?php if ($nilai['mode'] === 'perusahaan'): ?>
                        Surat <b>per perusahaan</b>: <b><?= esc($surat['perusahaan_nama']) ?></b> (<?= (int) $jmlSiswa ?> siswa).
                    <?php else: ?>
                        <b>Surat umum</b> &mdash; satu surat untuk semua perusahaan.
                    <?php endif; ?>
                </p>
                <p class="text-xs leading-relaxed text-gray-400">Cakupan dan daftar siswa tidak bisa diubah di sini. Bila perlu cakupan lain, batalkan surat ini lalu buat yang baru.</p>
            <?php else: ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="mode" value="umum" x-model="mode" class="mt-1 h-4 w-4">
                        <span><span class="block text-sm font-semibold text-gray-800">Surat umum</span><span class="block text-xs leading-relaxed text-gray-500">Satu surat, satu nomor, ditujukan ke "Pimpinan / Pembimbing PKL". Persis contoh sekolah.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="mode" value="perusahaan" x-model="mode" class="mt-1 h-4 w-4">
                        <span><span class="block text-sm font-semibold text-gray-800">Per perusahaan</span><span class="block text-xs leading-relaxed text-gray-500">Satu surat untuk tiap perusahaan yang dipilih, memuat nama perusahaan dan daftar siswanya. Nomor berbeda-beda.</span></span>
                    </label>
                </div>

                <div x-show="mode === 'perusahaan'" x-cloak class="space-y-3">
                    <?php if ($perusahaan === []): ?>
                        <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Belum ada perusahaan dengan siswa PKL yang sudah disetujui, jadi belum ada yang bisa dipilih.</p>
                    <?php else: ?>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="search" x-model="cari" placeholder="Cari perusahaan, kota, atau nama siswa…" class="inp sm:flex-1" aria-label="Cari perusahaan">
                            <button type="button" @click="pilihSemua(true)" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">Pilih semua yang sesuai</button>
                            <button type="button" @click="dipilih = []" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Kosongkan</button>
                        </div>
                        <p class="text-xs text-gray-500"><b x-text="dipilih.length"></b> dari <?= count($perusahaan) ?> perusahaan dipilih.</p>
                        <ul class="max-h-96 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200">
                            <template x-for="p in perusahaan" :key="p.kunci">
                                <li x-show="tampil(p)">
                                    <label class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-gray-50">
                                        <input type="checkbox" name="perusahaan[]" :value="p.kunci" x-model="dipilih" class="mt-1 h-4 w-4">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-800" x-text="p.nama"></span>
                                            <span class="block text-xs text-gray-500"><span x-text="p.jml"></span> siswa<span x-show="p.kota" x-text="' · ' + p.kota"></span> — <span x-text="p.siswa"></span></span>
                                            <span x-show="luarPeriode(p)" class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">PKL <span x-text="p.mulai + ' s.d. ' + p.selesai"></span> — di luar tanggal kegiatan</span>
                                        </span>
                                    </label>
                                </li>
                            </template>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <a href="<?= $ubah ? site_url('admin/surat/' . (int) $surat['id']) : site_url('admin/surat') ?>" class="rounded-xl border border-gray-300 bg-white px-5 py-3 text-center text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</a>
        <?php if (! $ubah && ! empty($bisaLangsungAcc)): ?>
            <?php // Dua tombol: yang pertama (tombol Enter) = aman, masuk antrean ACC; yang kedua = peran ini juga memegang hak ACC jenis ini. ?>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <button type="submit" name="langsung_acc" value="0" class="rounded-xl border border-brand-300 bg-white px-6 py-3 text-sm font-bold text-brand-700 shadow-xs transition hover:bg-brand-50 active:scale-95">Buat &amp; ajukan untuk ACC</button>
                <button type="submit" name="langsung_acc" value="1" class="rounded-xl bg-green-600 px-8 py-3 text-sm font-bold text-white shadow-xs transition hover:bg-green-700 active:scale-95">✓ Simpan &amp; setujui (ACC)</button>
            </div>
        <?php else: ?>
            <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-xs transition hover:bg-brand-800 active:scale-95">
                <?= $ubah ? 'Simpan perubahan' : ($perluAcc ? 'Buat surat &amp; ajukan untuk ACC' : 'Buat surat') ?>
            </button>
        <?php endif; ?>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/surat-izin.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-izin.js') ?>"></script>
<?= $this->endSection() ?>
