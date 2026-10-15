<?php
/**
 * Form staf: isi atas nama siswa (baru) atau ubah langsung (ubah). Komponen Alpine `pklFormStaf`
 * (assets/js/admin/pkl.js) mengurus pemilih siswa. Tanggal PKL & tanggal lahir tidak ditanyakan lagi.
 * Rombak tampilan F9: tab kit (_nav), ui_peringatan untuk galat, kartu kit per bagian, tombol kit ber-ikon, lencana kit,
 * pilihan status awal berbingkai, skrip ber-nonce. Isian Alpine tidak berubah.
 *
 * @var ?array      $a         ajuan (mode ubah) atau null
 * @var array       $orang     ['pengaju' => ?{id,nama,kelas}, 'teman' => list<{id,nama,kelas,hp}>]
 * @var array       $old       isian yang gagal disimpan
 * @var array       $galat     galat per kolom (+ 'umum')
 * @var list<array> $kelas
 * @var bool        $ubah
 * @var list<array> $anggota   anggota ajuan (mode ubah)
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, $p, $bolehAcc.
 */

$val = static fn (string $k): string => (string) ($old[$k] ?? $a[$k] ?? '');
// Isi HP pengaju saat ubah: dari anggota pengaju.
$pengajuRow = null;
foreach ($anggota as $s) {
    if ($s['peran'] === 'pengaju') {
        $pengajuRow = $s;
    }
}
$hp = (string) ($old['hp'] ?? ($pengajuRow['hp'] ?? ''));

$config = [
    'urlSiswa' => site_url('admin/pkl/siswa-kelas'),
    'ubah'     => $ubah,
    'pengaju'  => $orang['pengaju'],
    'teman'    => $orang['teman'],
    'maks'     => \App\Models\PklPengaturanModel::maksSiswa($p),
    'galatHp'  => array_filter($galat, static fn ($k) => str_starts_with((string) $k, 'hp_teman_'), ARRAY_FILTER_USE_KEY),
];

$err = static fn (string $k) => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$kelasInp = static fn (string $k) => isset($galat[$k]) ? 'inp-err' : '';
$input = static function (string $k, string $label, string $nilai, array $o = []) use ($err, $kelasInp): void {
    echo '<div class="' . ($o['cls'] ?? '') . '"><label class="lbl" for="f_' . $k . '">' . esc($label)
        . (($o['wajib'] ?? false) ? ' <span class="text-red-600 dark:text-red-400">*</span>' : ' <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span>') . '</label>'
        . '<input id="f_' . $k . '" name="' . $k . '" type="' . ($o['type'] ?? 'text') . '" value="' . esc($nilai, 'attr') . '" class="inp ' . $kelasInp($k) . '"'
        . (isset($o['maks']) ? ' maxlength="' . (int) $o['maks'] . '"' : '') . (isset($o['ph']) ? ' placeholder="' . esc($o['ph'], 'attr') . '"' : '')
        . (isset($o['model']) ? ' x-model="' . $o['model'] . '"' : '') . ' autocomplete="off">'
        . (isset($o['hint']) ? '<p class="hint">' . $o['hint'] . '</p>' : '') . $err($k) . '</div>';
};
$kepala = static fn (string $judul): string => '<div class="kartu-kepala"><h3 class="kartu-judul text-base">' . esc($judul) . '</h3></div>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_form_v2',
    'helpTitle' => $ubah ? 'Ubah Data Ajuan' : 'Isi atas Nama Siswa',
    'helpBody'  => $ubah
        ? '<p>Perbaiki data ajuan langsung tanpa mengubah statusnya. Pengaju tidak bisa diganti; teman boleh ditambah atau dikurangi.</p><p class="mt-2">Bila ajuan sudah <b>disetujui</b> dan nama perusahaan diubah, tautan ke master ikut menyesuaikan.</p>'
        : '<p>Dipakai bila siswa tidak bisa mengisi sendiri, atau untuk memasukkan data PKL yang sudah ada.</p><ul class="mt-2 list-disc pl-5 space-y-1"><li>Ajuan masuk antrean <b>Menunggu keputusan ACC</b>. Opsi <b>langsung disetujui</b> (data lama yang sudah pasti) hanya ada untuk peran yang memegang hak ACC ajuan PKL.</li><li>Nomor HP pengaju dan <b>setiap teman wajib diisi</b> (tercetak di surat) — sama seperti form siswa.</li><li>Waktu PKL dan tanggal lahir tidak perlu diisi.</li></ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url($ubah ? 'admin/pkl/' . $a['id'] . '/ubah' : 'admin/pkl/baru') ?>"
      x-data="pklFormStaf" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>"
      class="mx-auto max-w-3xl space-y-4" @submit="kirimTertunda = true">
    <?= csrf_field() ?>

    <?php if (! empty($galat)): ?>
        <?= ui_peringatan('galat', array_values(array_filter([$galat['umum'] ?? null, $galat['siswa_id'] ?? null])), ['judul' => 'Data belum bisa disimpan. Periksa isian yang bertanda merah.']) ?>
    <?php endif; ?>

    <!-- Siswa -->
    <section class="kartu">
        <?= $kepala('Siswa') ?>
        <div class="kartu-isi space-y-4">
            <div>
                <p class="lbl">Pengaju <span class="text-red-600 dark:text-red-400">*</span></p>
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700">
                    <template x-if="pengaju">
                        <span class="min-w-0 flex-1"><b class="text-gray-900 dark:text-white" x-text="pengaju.nama"></b> <span class="text-xs text-gray-500 dark:text-gray-300" x-text="pengaju.kelas ? '(' + pengaju.kelas + ')' : ''"></span></span>
                    </template>
                    <span x-show="!pengaju" class="flex-1 text-sm text-gray-500 dark:text-gray-300">Belum dipilih</span>
                    <?php if (! $ubah): ?>
                        <button type="button" class="btn btn-netral btn-sm" @click="bukaPemilih('pengaju')"><?= ikon('user', 'h-4 w-4') ?><span x-text="pengaju ? 'Ganti' : 'Pilih siswa'">Pilih siswa</span></button>
                    <?php else: ?>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Pengaju tidak bisa diganti</span>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="siswa_id" :value="pengaju ? pengaju.id : ''">
                <?= $err('siswa_id') ?>
            </div>

            <div>
                <p class="lbl">Teman satu tempat <span class="font-normal text-gray-500 dark:text-gray-400">(<span x-text="teman.length"></span> dari maks <span x-text="maks - 1"></span>)</span></p>
                <ul class="space-y-2">
                    <template x-for="t in teman" :key="t.id">
                        <li class="rounded-lg border border-gray-200 px-4 py-2.5 dark:border-gray-600">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 text-sm"><b class="text-gray-900 dark:text-white" x-text="t.nama"></b> <span class="text-xs text-gray-500 dark:text-gray-400" x-text="t.kelas ? '(' + t.kelas + ')' : ''"></span></span>
                                <span class="flex items-center gap-2"><input type="hidden" name="teman[]" :value="t.id"><button type="button" class="btn btn-teks btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700" @click="hapusTeman(t.id)"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</button></span>
                            </div>
                            <input type="tel" inputmode="tel" maxlength="20" :name="'teman_hp[' + t.id + ']'" x-model="t.hp" placeholder="No. HP teman (wajib)" required aria-label="No. HP teman (wajib diisi)" class="inp mt-2" :class="errHp(t.id) && 'inp-err'" autocomplete="off">
                            <p class="err-msg" x-show="errHp(t.id)" x-text="errHp(t.id)"></p>
                        </li>
                    </template>
                </ul>
                <button type="button" class="btn btn-netral btn-sm mt-2" x-show="teman.length < maks - 1" @click="bukaPemilih('teman')"><?= ikon('plus', 'h-4 w-4') ?>Tambah teman</button>
                <?= $err('teman') ?>
            </div>

            <!-- Pemilih siswa -->
            <div x-cloak x-show="pick.buka" class="space-y-3 rounded-lg border border-brand-200 bg-brand-50/60 p-4 dark:border-gray-600 dark:bg-gray-700/50">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="pick.mode === 'pengaju' ? 'Pilih siswa pengaju' : 'Pilih teman'"></p>
                <select class="inp" x-model="pick.kelasId" @change="muatPemilih($event)" aria-label="Kelas">
                    <option value="">— Pilih kelas —</option>
                    <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
                <p x-show="pick.memuat" class="text-sm text-gray-500 dark:text-gray-400">Memuat…</p>
                <input x-show="pick.list.length" type="search" x-model="pick.cari" class="inp" placeholder="Ketik nama…" aria-label="Cari nama">
                <div x-show="pick.list.length" class="max-h-64 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 bg-white dark:divide-gray-600 dark:border-gray-600 dark:bg-gray-800">
                    <template x-for="s in tersaring()" :key="s.id">
                        <button type="button" @click="pilih(s)" :disabled="alasan(s) !== ''" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm transition-colors disabled:cursor-not-allowed"
                                :class="alasan(s) !== '' ? 'bg-gray-50 text-gray-400 dark:bg-gray-700 dark:text-gray-500' : 'text-gray-900 hover:bg-brand-50 dark:text-white dark:hover:bg-gray-700'">
                            <span x-text="s.nama"></span>
                            <span x-show="alasan(s) !== ''" class="lencana lencana-abu shrink-0" x-text="alasan(s)"></span>
                        </button>
                    </template>
                </div>
                <button type="button" class="btn btn-teks btn-sm" @click="pick.buka = false"><?= ikon('close', 'h-4 w-4') ?>Tutup</button>
            </div>
        </div>
    </section>

    <!-- Perusahaan -->
    <section class="kartu">
        <?= $kepala('Perusahaan') ?>
        <div class="kartu-isi space-y-4">
            <?php $input('perusahaan_nama', 'Nama perusahaan', $val('perusahaan_nama'), ['wajib' => true, 'maks' => 150, 'ph' => 'Contoh: PT Telkom Indonesia', 'hint' => 'Periksa ejaannya — nama ini jadi saran untuk siswa lain setelah di-ACC.']) ?>
            <div>
                <label class="lbl" for="f_perusahaan_alamat">Alamat <span class="text-red-600 dark:text-red-400">*</span></label>
                <textarea id="f_perusahaan_alamat" name="perusahaan_alamat" rows="2" maxlength="255" class="inp <?= $kelasInp('perusahaan_alamat') ?>" placeholder="Jalan, nomor, daerah"><?= esc($val('perusahaan_alamat')) ?></textarea>
                <?= $err('perusahaan_alamat') ?>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php $input('perusahaan_kota', 'Kota / Kabupaten', $val('perusahaan_kota'), ['wajib' => true, 'maks' => 100]) ?>
                <?php $input('perusahaan_telepon', 'Telepon perusahaan', $val('perusahaan_telepon'), ['maks' => 30, 'ph' => '02188776655']) ?>
                <?php $input('kontak_nama', 'Nama pimpinan / kontak', $val('kontak_nama'), ['maks' => 150]) ?>
                <?php $input('kontak_jabatan', 'Jabatan', $val('kontak_jabatan'), ['maks' => 100]) ?>
            </div>
        </div>
    </section>

    <!-- Kontak pengaju -->
    <section class="kartu">
        <?= $kepala('Kontak pengaju') ?>
        <div class="kartu-isi grid grid-cols-1 gap-4 sm:grid-cols-2">
            <?php $input('hp', 'No. HP / WhatsApp', $hp, ['wajib' => true, 'maks' => 20, 'ph' => '081234567890', 'hint' => 'Wajib diisi. Tercetak di surat.']) ?>
        </div>
    </section>

    <?php if (! $ubah): ?>
        <section class="kartu">
            <?= $kepala('Status awal') ?>
            <div class="kartu-isi grid grid-cols-1 gap-3 sm:grid-cols-2">
                <?php $awal = ($old['status_awal'] ?? 'menunggu') === 'disetujui' ? 'disetujui' : 'menunggu'; ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3.5 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-gray-600 dark:has-[:checked]:bg-gray-700">
                    <input type="radio" name="status_awal" value="menunggu" <?= $awal === 'menunggu' ? 'checked' : '' ?> class="cek mt-0.5">
                    <span><b class="block text-sm text-gray-900 dark:text-white">Menunggu pemeriksaan</b><span class="text-xs text-gray-600 dark:text-gray-300">Masuk antrean seperti ajuan siswa; diputuskan nanti.</span></span>
                </label>
                <?php if (! empty($bolehAcc)): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3.5 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-50 dark:border-gray-600 dark:has-[:checked]:bg-gray-700">
                        <input type="radio" name="status_awal" value="disetujui" <?= $awal === 'disetujui' ? 'checked' : '' ?> class="cek mt-0.5">
                        <span><b class="block text-sm text-gray-900 dark:text-white">Langsung disetujui</b><span class="text-xs text-gray-500 dark:text-gray-400">Untuk data lama yang sudah pasti; tercatat Anda yang menyetujui. Siswa langsung terkunci.</span></span>
                    </label>
                <?php else: ?>
                    <div class="flex items-start gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3.5 dark:border-gray-600 dark:bg-gray-700/50">
                        <?= ikon('lock', 'mt-0.5 h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400') ?>
                        <span><b class="block text-sm text-gray-700 dark:text-gray-300">Langsung disetujui</b><span class="text-xs text-gray-500 dark:text-gray-400">Hanya peran yang memegang hak ACC ajuan PKL yang bisa menyetujui. Simpan sebagai menunggu, lalu minta pemegang hak ACC meng-ACC.</span></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <a href="<?= site_url($ubah ? 'admin/pkl/' . $a['id'] : 'admin/pkl') ?>" class="btn btn-netral">Batal</a>
        <button type="submit" class="btn btn-utama" :disabled="!pengaju || kirimTertunda"><?= ikon('check', 'h-4 w-4') ?><?= $ubah ? 'Simpan perubahan' : 'Simpan ajuan' ?></button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
