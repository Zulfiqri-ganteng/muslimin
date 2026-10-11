<?php
/**
 * Formulir Pernyataan Orang Tua PKL — buat (per kelas / per ajuan PKL / siswa pilihan). Satu siswa = satu halaman.
 *
 * @var string               $label
 * @var array<string, mixed> $nilai  tanggal_surat, cakupan, isi_ortu, kelas[] / ajuan[] / siswa[] (id terpilih)
 * @var list<array>          $kelas  kelas boleh PKL: id, nama, tingkat, jml
 * @var list<array>          $ajuan  ajuan berjalan: id, kode, status, perusahaan, siswa[]
 * @var list<array>          $siswa  siswa aktif kelas-kelas itu: id, nama, nis, kelas_id
 */

use App\Libraries\SuratJenis;

$jadiTeks = static fn (array $ids): array => array_map('strval', $ids);
$cfg      = [
    'cakupan'      => $nilai['cakupan'],
    'kelasDipilih' => $jadiTeks($nilai['kelas']),
    'ajuanDipilih' => $jadiTeks($nilai['ajuan']),
    'siswaDipilih' => array_map('intval', $nilai['siswa']),
    'kelas'        => $kelas,
    'ajuan'        => array_map(static fn (array $a) => [
        'id' => $a['id'], 'kode' => $a['kode'], 'status' => $a['status'], 'perusahaan' => $a['perusahaan'], 'jml' => count($a['siswa']),
        'siswa' => implode(', ', array_slice(array_column($a['siswa'], 'nama'), 0, 4)) . (count($a['siswa']) > 4 ? ', …' : ''),
    ], $ajuan),
    'siswa'        => $siswa,
];
$statusAjuan = ['menunggu' => ['Menunggu ACC', 'bg-blue-100 text-blue-700'], 'perbaikan' => ['Perlu perbaikan', 'bg-amber-100 text-amber-800'], 'disetujui' => ['Disetujui', 'bg-green-100 text-green-700']];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'surat_pernyataan_form_v1',
    'helpTitle' => $label,
    'helpBody'  => '<p>Formulir <b>pernyataan dan persetujuan orang tua/wali</b> untuk PKL, persis contoh sekolah. Dicetak <b>satu halaman per siswa</b>, lalu diisi dan ditandatangani orang tua di atas materai Rp10.000.</p>'
        . '<ul class="mt-2 list-disc space-y-1 pl-5">'
        . '<li><b>Per kelas</b>: satu surat untuk tiap kelas yang dicentang, berisi semua siswa aktifnya. <b>Per ajuan PKL</b>: semua anggota ajuan. <b>Siswa pilihan</b>: pilih siswa satu per satu.</li>'
        . '<li>Nama, kelas, konsentrasi keahlian, dan HP siswa terisi otomatis. Data orang tua (nama, pekerjaan, alamat, HP) diambil dari data sekolah bila ada; yang kosong tetap <b>titik-titik</b> untuk diisi tangan. Pilih "Kosongkan" bila ingin semua data orang tua diisi tangan.</li>'
        . '<li>Surat ini <b>tanpa nomor dan tanpa ACC</b>: setelah dibuat langsung bisa diunduh (Word).</li>'
        . '</ul>',
]) ?>

<a href="<?= site_url('admin/surat') ?>" class="rise mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">← Daftar Surat</a>

<form method="post" action="<?= esc(site_url(SuratJenis::alamat(SuratJenis::PERNYATAAN)), 'attr') ?>" x-data="suratOrtuForm" data-config="<?= esc(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" class="mx-auto max-w-4xl space-y-5">
    <?= csrf_field() ?>

    <h1 class="rise text-xl font-bold text-slate-800">Buat <?= esc($label) ?></h1>

    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Untuk siapa</h3>
        <div class="space-y-4 px-5 py-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <?php foreach (['kelas' => ['Per kelas', 'Semua siswa aktif di kelas yang dicentang. Satu surat per kelas.'], 'ajuan' => ['Per ajuan PKL', 'Semua anggota ajuan PKL yang dicentang. Satu surat per ajuan.'], 'siswa' => ['Siswa pilihan', 'Pilih siswa satu per satu (boleh beda kelas). Satu surat.']] as $k => [$judul, $ket]): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="cakupan" value="<?= $k ?>" x-model="cakupan" class="mt-1 h-4 w-4">
                        <span><span class="block text-sm font-semibold text-slate-800"><?= $judul ?></span><span class="block text-xs leading-relaxed text-slate-500"><?= $ket ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Per kelas -->
            <div x-show="cakupan === 'kelas'" class="space-y-3">
                <?php if ($kelas === []): ?>
                    <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Belum ada kelas berisi siswa aktif pada tingkat yang boleh PKL (Pengaturan PKL → Tingkat).</p>
                <?php else: ?>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="search" x-model="cariKelas" placeholder="Cari kelas…" class="inp sm:flex-1" aria-label="Cari kelas">
                        <button type="button" @click="pilihSemuaKelas()" class="rounded-lg border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">Pilih semua yang tampil</button>
                        <button type="button" @click="kelasDipilih = []" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Kosongkan</button>
                    </div>
                    <ul class="grid max-h-96 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-3">
                        <template x-for="k in kelas" :key="k.id">
                            <li x-show="tampilKelas(k)">
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 transition hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                    <input type="checkbox" name="kelas[]" :value="String(k.id)" x-model="kelasDipilih" class="h-4 w-4">
                                    <span class="min-w-0"><span class="block text-sm font-semibold text-slate-800" x-text="k.nama"></span><span class="block text-xs text-slate-500" x-text="k.jml + ' siswa'"></span></span>
                                </label>
                            </li>
                        </template>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Per ajuan -->
            <div x-show="cakupan === 'ajuan'" x-cloak class="space-y-3">
                <?php if ($ajuan === []): ?>
                    <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Belum ada ajuan PKL yang sedang berjalan (menunggu ACC, perbaikan, atau disetujui).</p>
                <?php else: ?>
                    <input type="search" x-model="cariAjuan" placeholder="Cari perusahaan, nomor ajuan, atau nama siswa…" class="inp" aria-label="Cari ajuan">
                    <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                        <template x-for="a in ajuan" :key="a.id">
                            <li x-show="tampilAjuan(a)">
                                <label class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-slate-50">
                                    <input type="checkbox" name="ajuan[]" :value="String(a.id)" x-model="ajuanDipilih" class="mt-1 h-4 w-4">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-slate-800"><span x-text="a.perusahaan"></span> <span class="font-mono text-xs font-normal text-slate-400" x-text="a.kode"></span></span>
                                        <span class="block text-xs text-slate-500"><span x-text="a.jml"></span> siswa — <span x-text="a.siswa"></span></span>
                                        <?php foreach ($statusAjuan as $st => [$teks, $warna]): ?>
                                            <span x-show="a.status === '<?= $st ?>'" class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-bold <?= $warna ?>"><?= $teks ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </label>
                            </li>
                        </template>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Siswa pilihan -->
            <div x-show="cakupan === 'siswa'" x-cloak class="space-y-3">
                <div class="grid gap-2 sm:grid-cols-2">
                    <select x-model="kelasSiswa" class="inp" aria-label="Kelas">
                        <template x-for="k in kelas" :key="k.id"><option :value="String(k.id)" x-text="k.nama"></option></template>
                    </select>
                    <input type="search" x-model="cariSiswa" placeholder="Cari nama / NIS di semua kelas (min. 3 huruf)…" class="inp" aria-label="Cari siswa">
                </div>
                <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                    <template x-for="s in daftarSiswa()" :key="s.id">
                        <li>
                            <button type="button" @click="togelSiswa(s.id)" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left transition hover:bg-slate-50" :class="dipilihSiswa(s.id) && 'bg-brand-50'">
                                <span class="min-w-0"><span class="block text-sm font-medium text-slate-800" x-text="s.nama"></span><span class="block text-xs text-slate-500" x-text="namaKelas(s.kelas_id) + (s.nis ? ' · NIS ' + s.nis : '')"></span></span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="dipilihSiswa(s.id) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500'" x-text="dipilihSiswa(s.id) ? '✓ dipilih' : '+ pilih'"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="daftarSiswa().length === 0" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada siswa yang cocok.</li>
                </ul>
                <div x-show="siswaDipilih.length" class="rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-brand-700"><span x-text="siswaDipilih.length"></span> siswa dipilih</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <template x-for="id in siswaDipilih" :key="id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-brand-200">
                                <span x-text="namaSiswa(id)"></span>
                                <button type="button" @click="togelSiswa(id)" class="text-slate-400 hover:text-red-600" aria-label="Lepas">✕</button>
                                <input type="hidden" name="siswa[]" :value="id">
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-500">Akan dicetak <b class="text-slate-700" x-text="jumlahHalaman()"></b> halaman (satu halaman per siswa).</p>
        </div>
    </section>

    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Isian</h3>
        <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
            <div>
                <label class="lbl" for="tanggal_surat">Tanggal dibuat <span class="text-red-500">*</span></label>
                <input id="tanggal_surat" name="tanggal_surat" type="date" value="<?= esc($nilai['tanggal_surat'], 'attr') ?>" required class="inp">
                <p class="mt-1 text-xs text-slate-400">Menentukan tahun di baris "Bekasi, …… <?= esc(substr($nilai['tanggal_surat'], 0, 4)) ?>". Tanggal & bulannya tetap titik-titik, diisi orang tua.</p>
            </div>
            <div>
                <span class="lbl">Data orang tua</span>
                <label class="mt-1 flex cursor-pointer items-start gap-2 text-sm text-slate-700"><input type="radio" name="isi_ortu" value="1" <?= $nilai['isi_ortu'] !== '0' ? 'checked' : '' ?> class="mt-0.5 h-4 w-4"> <span>Isi dari data sekolah (yang kosong tetap titik-titik)</span></label>
                <label class="mt-1.5 flex cursor-pointer items-start gap-2 text-sm text-slate-700"><input type="radio" name="isi_ortu" value="0" <?= $nilai['isi_ortu'] === '0' ? 'checked' : '' ?> class="mt-0.5 h-4 w-4"> <span>Kosongkan — semua diisi tangan oleh orang tua</span></label>
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <a href="<?= site_url('admin/surat') ?>" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</a>
        <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Buat surat</button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/surat-pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-pkl.js') ?>"></script>
<?= $this->endSection() ?>
