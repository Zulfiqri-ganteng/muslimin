<?php
/**
 * Formulir Surat Izin ASTS / Surat Izin TKA — buat baru atau ubah.
 * Rombak tampilan F9: Kembali di kepala halaman, kartu kit per bagian, pratinjau = peringatan info kit, pilihan cakupan
 * berbingkai, tombol kit ber-ikon, lencana kit, skrip ber-nonce. Isian Alpine suratIzinForm tidak berubah (emoji tanda awas dibuang).
 *
 * @var string                  $jenis      izin_asts | izin_tka
 * @var string                  $label
 * @var bool                    $ubah       true = mengubah surat yang sudah ada
 * @var array<string, mixed>    $nilai      isian (old() sudah diperhitungkan); 'perusahaan' = daftar kunci yang dicentang
 * @var list<array>             $periode    periode ASTS di menu Ujian (hanya ASTS)
 * @var list<array>             $perusahaan perusahaan yang punya siswa PKL (hanya saat membuat)
 * @var bool                    $perluAcc   jenis ini wajib ACC?
 * @var list<string>            $sesiSaran
 * @var array|null              $surat / string $kode / int $jmlSiswa (mode ubah)
 * @var bool                    $bisaLangsungAcc / string $pemegangAcc
 */

use App\Libraries\SuratJenis;

$asts   = $jenis === SuratJenis::ASTS;
$alamat = SuratJenis::alamat($jenis);
$aksi   = $ubah ? site_url($alamat . '/' . (int) $surat['id'] . '/ubah') : site_url($alamat);
$kembali = $ubah ? site_url('admin/surat/' . (int) $surat['id']) : site_url('admin/surat');
$cfg    = [
    'mode' => $nilai['mode'], 'semester' => $nilai['semester'], 'tp' => $nilai['tahun_pelajaran'], 'mulai' => $nilai['tgl_mulai'], 'selesai' => $nilai['tgl_selesai'],
    'tanggalSurat' => $nilai['tanggal_surat'], 'dipilih' => $nilai['perusahaan'],
    'periode' => array_map(static fn (array $p) => ['semester' => $p['semester'], 'tahun' => $p['tahun'], 'mulai' => $p['mulai'], 'selesai' => $p['selesai']], $periode),
    'perusahaan' => array_map(static fn (array $g) => [
        'kunci' => $g['kunci'], 'nama' => $g['nama'], 'kota' => $g['kota'], 'jml' => count($g['siswa']), 'mulai' => $g['mulai'], 'selesai' => $g['selesai'],
        'siswa' => implode(', ', array_slice(array_column($g['siswa'], 'nama'), 0, 4)) . (count($g['siswa']) > 4 ? ', …' : ''),
    ], $perusahaan),
];
$kepala = static fn (string $judul): string => '<div class="kartu-kepala"><h3 class="kartu-judul text-base">' . esc($judul) . '</h3></div>';
$pilihanBingkai = 'flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-gray-600 dark:has-[:checked]:bg-gray-700';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= $kembali ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?><?= $ubah ? 'Kembali ke surat' : 'Daftar Surat' ?></a>
<?= $this->endSection() ?>

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

<form method="post" action="<?= esc($aksi, 'attr') ?>" x-data="suratIzinForm" data-config="<?= esc(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" class="mx-auto max-w-4xl space-y-4">
    <?= csrf_field() ?>

    <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"><?= $ubah ? 'Ubah ' . esc($label) . ' <span class="lencana lencana-abu font-mono">' . esc($kode) . '</span>' : 'Buat ' . esc($label) ?></h2>

    <section class="kartu">
        <?= $kepala('Kegiatan') ?>
        <div class="kartu-isi grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php if ($asts && $periode !== []): ?>
                <div class="sm:col-span-2">
                    <label class="lbl" for="pilihPeriode">Isi otomatis dari menu Ujian <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                    <select id="pilihPeriode" x-model="pilihPeriode" @change="ambilPeriode()" class="inp">
                        <option value="">— pilih periode ASTS —</option>
                        <?php foreach ($periode as $i => $p): ?>
                            <option value="<?= (int) $i ?>"><?= esc($p['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">Mengisi semester, tahun pelajaran, dan tanggal di bawah. Tanggalnya tetap bisa Anda ubah.</p>
                </div>
            <?php endif; ?>

            <?php if ($asts): ?>
                <div>
                    <label class="lbl" for="semester">Semester <span class="text-red-600">*</span></label>
                    <select id="semester" name="semester" x-model="semester" required class="inp">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="tahun_pelajaran">Tahun pelajaran <span class="text-red-600">*</span></label>
                    <input id="tahun_pelajaran" name="tahun_pelajaran" type="text" x-model="tp" required maxlength="9" placeholder="2026/2027" class="inp">
                </div>
            <?php else: ?>
                <div class="sm:col-span-2">
                    <label class="lbl" for="sesi">Sesi / gelombang <span class="text-red-600">*</span></label>
                    <input id="sesi" name="sesi" type="text" value="<?= esc($nilai['sesi'], 'attr') ?>" required maxlength="40" list="saranSesi" placeholder="Gelombang 1" class="inp">
                    <datalist id="saranSesi"><?php foreach ($sesiSaran as $s): ?><option value="<?= esc($s, 'attr') ?>"><?php endforeach; ?></datalist>
                    <p class="hint">Tercetak di baris "Sesi" pada surat. Pilih saran atau ketik sendiri.</p>
                </div>
            <?php endif; ?>

            <div>
                <label class="lbl" for="tgl_mulai">Tanggal mulai <span class="text-red-600">*</span></label>
                <input id="tgl_mulai" name="tgl_mulai" type="date" x-model="mulai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="tgl_selesai">Tanggal selesai <span class="text-red-600">*</span></label>
                <input id="tgl_selesai" name="tgl_selesai" type="date" x-model="selesai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="tempat">Tempat</label>
                <input id="tempat" name="tempat" type="text" value="<?= esc($nilai['tempat'], 'attr') ?>" maxlength="100" class="inp">
            </div>
            <div>
                <label class="lbl" for="tanggal_surat">Tanggal surat <span class="text-red-600">*</span></label>
                <input id="tanggal_surat" name="tanggal_surat" type="date" x-model="tanggalSurat" required class="inp">
                <p class="hint">Menentukan bulan dan tahun pada nomor surat. Tidak boleh setelah kegiatan selesai.</p>
            </div>

            <!-- Pratinjau -->
            <div class="peringatan peringatan-info sm:col-span-2">
                <?= ikon('eye', 'mt-0.5 h-5 w-5 shrink-0') ?>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Pratinjau di surat</p>
                    <template x-if="hariTanggal() !== ''">
                        <div class="mt-1.5 space-y-0.5">
                            <p><span class="inline-block w-28">Hari/Tanggal</span>: <b x-text="hariTanggal()"></b></p>
                            <p class="text-xs">Kalimat dispensasi: "…di sekolah pada tanggal <b x-text="tanggalSampai()"></b> sesuai dengan jadwal…"</p>
                        </div>
                    </template>
                    <p x-show="hariTanggal() === ''" class="mt-1.5 text-xs">Isi tanggal mulai dan selesai untuk melihat pratinjau.</p>
                    <p x-show="peringatan() !== ''" class="mt-2 flex items-start gap-1.5 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800 dark:border-yellow-800 dark:bg-gray-700 dark:text-yellow-300"><?= ikon('exclamation-circle', 'h-4 w-4 shrink-0') ?><span x-text="peringatan()"></span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="kartu">
        <?= $kepala('Cakupan surat') ?>
        <div class="kartu-isi space-y-4">
            <?php if ($ubah): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    <?php if ($nilai['mode'] === 'perusahaan'): ?>
                        Surat <b>per perusahaan</b>: <b><?= esc($surat['perusahaan_nama']) ?></b> (<?= (int) $jmlSiswa ?> siswa).
                    <?php else: ?>
                        <b>Surat umum</b> &mdash; satu surat untuk semua perusahaan.
                    <?php endif; ?>
                </p>
                <p class="hint">Cakupan dan daftar siswa tidak bisa diubah di sini. Bila perlu cakupan lain, batalkan surat ini lalu buat yang baru.</p>
            <?php else: ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="<?= $pilihanBingkai ?>">
                        <input type="radio" name="mode" value="umum" x-model="mode" class="cek mt-0.5">
                        <span><span class="block text-sm font-semibold text-gray-900 dark:text-white">Surat umum</span><span class="block text-xs text-gray-500 dark:text-gray-400">Satu surat, satu nomor, ditujukan ke "Pimpinan / Pembimbing PKL". Persis contoh sekolah.</span></span>
                    </label>
                    <label class="<?= $pilihanBingkai ?>">
                        <input type="radio" name="mode" value="perusahaan" x-model="mode" class="cek mt-0.5">
                        <span><span class="block text-sm font-semibold text-gray-900 dark:text-white">Per perusahaan</span><span class="block text-xs text-gray-500 dark:text-gray-400">Satu surat untuk tiap perusahaan yang dipilih, memuat nama perusahaan dan daftar siswanya. Nomor berbeda-beda.</span></span>
                    </label>
                </div>

                <div x-show="mode === 'perusahaan'" x-cloak class="space-y-3">
                    <?php if ($perusahaan === []): ?>
                        <?= ui_peringatan('awas', 'Belum ada perusahaan dengan siswa PKL yang sudah disetujui, jadi belum ada yang bisa dipilih.') ?>
                    <?php else: ?>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="search" x-model="cari" placeholder="Cari perusahaan, kota, atau nama siswa…" class="inp sm:flex-1" aria-label="Cari perusahaan">
                            <button type="button" class="btn btn-netral" @click="pilihSemua(true)"><?= ikon('check', 'h-4 w-4') ?>Pilih semua yang sesuai</button>
                            <button type="button" class="btn btn-teks" @click="dipilih = []"><?= ikon('close', 'h-4 w-4') ?>Kosongkan</button>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white" x-text="dipilih.length"></b> dari <?= count($perusahaan) ?> perusahaan dipilih.</p>
                        <ul class="max-h-96 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <template x-for="p in perusahaan" :key="p.kunci">
                                <li x-show="tampil(p)">
                                    <label class="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60">
                                        <input type="checkbox" name="perusahaan[]" :value="p.kunci" x-model="dipilih" class="cek mt-0.5">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white" x-text="p.nama"></span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400"><span x-text="p.jml"></span> siswa<span x-show="p.kota" x-text="' · ' + p.kota"></span> — <span x-text="p.siswa"></span></span>
                                            <span x-show="luarPeriode(p)" class="lencana lencana-kuning mt-1">PKL <span x-text="p.mulai + ' s.d. ' + p.selesai"></span> — di luar tanggal kegiatan</span>
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
        <a href="<?= $kembali ?>" class="btn btn-netral">Batal</a>
        <?php if (! $ubah && ! empty($bisaLangsungAcc)): ?>
            <?php // Dua tombol: yang pertama (tombol Enter) = aman, masuk antrean ACC; yang kedua = peran ini juga memegang hak ACC jenis ini. ?>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <button type="submit" name="langsung_acc" value="0" class="btn btn-netral"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Buat &amp; ajukan untuk ACC</button>
                <button type="submit" name="langsung_acc" value="1" class="btn btn-sukses"><?= ikon('check', 'h-4 w-4') ?>Simpan &amp; setujui (ACC)</button>
            </div>
        <?php else: ?>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><?= $ubah ? 'Simpan perubahan' : ($perluAcc ? 'Buat surat &amp; ajukan untuk ACC' : 'Buat surat') ?></button>
        <?php endif; ?>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/surat-izin.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-izin.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
