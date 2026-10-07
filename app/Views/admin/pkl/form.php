<?php
/**
 * Form staf: isi atas nama siswa (baru) atau ubah langsung (ubah). Komponen Alpine `pklFormStaf`
 * (assets/js/admin/pkl.js) mengurus pemilih siswa. Tanggal PKL & tanggal lahir tidak ditanyakan lagi.
 *
 * @var ?array      $a         ajuan (mode ubah) atau null
 * @var array       $orang     ['pengaju' => ?{id,nama,kelas}, 'teman' => list<{id,nama,kelas,hp}>]
 * @var array       $old       isian yang gagal disimpan
 * @var array       $galat     galat per kolom (+ 'umum')
 * @var list<array> $kelas
 * @var bool        $ubah
 */

use App\Libraries\IsianBantu;
use App\Models\PklPengajuanModel;

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
        . (($o['wajib'] ?? false) ? ' <span class="text-red-500">*</span>' : ' <span class="font-normal text-slate-400">(opsional)</span>') . '</label>'
        . '<input id="f_' . $k . '" name="' . $k . '" type="' . ($o['type'] ?? 'text') . '" value="' . esc($nilai, 'attr') . '" class="inp ' . $kelasInp($k) . '"'
        . (isset($o['maks']) ? ' maxlength="' . (int) $o['maks'] . '"' : '') . (isset($o['ph']) ? ' placeholder="' . esc($o['ph'], 'attr') . '"' : '')
        . (isset($o['model']) ? ' x-model="' . $o['model'] . '"' : '') . ' autocomplete="off">'
        . (isset($o['hint']) ? '<p class="mt-1 text-xs text-slate-400">' . $o['hint'] . '</p>' : '') . $err($k) . '</div>';
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_form_v2',
    'helpTitle' => $ubah ? 'Ubah Data Ajuan' : 'Isi atas Nama Siswa',
    'helpBody'  => $ubah
        ? '<p>Perbaiki data ajuan langsung tanpa mengubah statusnya. Pengaju tidak bisa diganti; teman boleh ditambah atau dikurangi.</p><p class="mt-2">Bila ajuan sudah <b>disetujui</b> dan nama perusahaan diubah, tautan ke master ikut menyesuaikan.</p>'
        : '<p>Dipakai bila siswa tidak bisa mengisi sendiri, atau untuk memasukkan data PKL yang sudah ada.</p><ul class="mt-2 list-disc pl-5 space-y-1"><li>Ajuan masuk antrean <b>Menunggu keputusan Waka Hubin</b>. Opsi <b>langsung disetujui</b> (data lama yang sudah pasti) hanya ada untuk Waka Hubin / Admin.</li><li>Nomor HP pengaju dan <b>setiap teman wajib diisi</b> (tercetak di surat) — sama seperti form siswa.</li><li>Waktu PKL dan tanggal lahir tidak perlu diisi.</li></ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url($ubah ? 'admin/pkl/' . $a['id'] . '/ubah' : 'admin/pkl/baru') ?>"
      x-data="pklFormStaf" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>"
      class="mx-auto max-w-3xl space-y-5" @submit="kirimTertunda = true">
    <?= csrf_field() ?>

    <?php if (! empty($galat)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm text-red-700">
            <p class="font-bold">Data belum bisa disimpan. Periksa isian yang bertanda merah.</p>
            <?php if (isset($galat['umum'])): ?><p class="mt-1"><?= esc($galat['umum']) ?></p><?php endif; ?>
            <?php if (isset($galat['siswa_id'])): ?><p class="mt-1"><?= esc($galat['siswa_id']) ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Siswa -->
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siswa</h3>
        <div class="space-y-4 p-5">
            <div>
                <p class="lbl">Pengaju <span class="text-red-500">*</span></p>
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <template x-if="pengaju">
                        <span class="min-w-0 flex-1"><b class="text-slate-800" x-text="pengaju.nama"></b> <span class="text-xs text-slate-500" x-text="pengaju.kelas ? '(' + pengaju.kelas + ')' : ''"></span></span>
                    </template>
                    <span x-show="!pengaju" class="flex-1 text-sm text-slate-400">Belum dipilih</span>
                    <?php if (! $ubah): ?>
                        <button type="button" @click="bukaPemilih('pengaju')" class="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50" x-text="pengaju ? 'Ganti' : 'Pilih siswa'"></button>
                    <?php else: ?>
                        <span class="text-[11px] text-slate-400">Pengaju tidak bisa diganti</span>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="siswa_id" :value="pengaju ? pengaju.id : ''">
                <?= $err('siswa_id') ?>
            </div>

            <div>
                <p class="lbl">Teman satu tempat <span class="font-normal text-slate-400">(<span x-text="teman.length"></span> dari maks <span x-text="maks - 1"></span>)</span></p>
                <ul class="space-y-2">
                    <template x-for="t in teman" :key="t.id">
                        <li class="rounded-xl border border-slate-200 px-4 py-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 text-sm"><b class="text-slate-800" x-text="t.nama"></b> <span class="text-xs text-slate-500" x-text="t.kelas ? '(' + t.kelas + ')' : ''"></span></span>
                                <span class="flex items-center gap-2"><input type="hidden" name="teman[]" :value="t.id"><button type="button" @click="hapusTeman(t.id)" class="rounded-lg px-2.5 py-1 text-xs font-bold text-red-600 hover:bg-red-50">Hapus</button></span>
                            </div>
                            <input type="tel" inputmode="tel" maxlength="20" :name="'teman_hp[' + t.id + ']'" x-model="t.hp" placeholder="No. HP teman (wajib)" required aria-label="No. HP teman (wajib diisi)" class="inp mt-2" :class="errHp(t.id) && 'inp-err'" autocomplete="off">
                            <p class="err-msg" x-show="errHp(t.id)" x-text="errHp(t.id)"></p>
                        </li>
                    </template>
                </ul>
                <button type="button" x-show="teman.length < maks - 1" @click="bukaPemilih('teman')" class="mt-2 rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">+ Tambah teman</button>
                <?= $err('teman') ?>
            </div>

            <!-- Pemilih siswa -->
            <div x-cloak x-show="pick.buka" class="space-y-3 rounded-2xl border-2 border-brand-200 bg-brand-50/50 p-4">
                <p class="text-sm font-semibold text-slate-700" x-text="pick.mode === 'pengaju' ? 'Pilih siswa pengaju' : 'Pilih teman'"></p>
                <select class="inp bg-white" x-model="pick.kelasId" @change="muatPemilih($event)" aria-label="Kelas">
                    <option value="">— Pilih kelas —</option>
                    <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
                <p x-show="pick.memuat" class="text-sm text-slate-500">Memuat…</p>
                <input x-show="pick.list.length" type="search" x-model="pick.cari" class="inp bg-white" placeholder="Ketik nama…" aria-label="Cari nama">
                <div x-show="pick.list.length" class="max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                    <template x-for="s in tersaring()" :key="s.id">
                        <button type="button" @click="pilih(s)" :disabled="alasan(s) !== ''" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm transition disabled:cursor-not-allowed" :class="alasan(s) !== '' ? 'bg-slate-50 text-slate-400' : 'hover:bg-brand-50'">
                            <span x-text="s.nama"></span>
                            <span x-show="alasan(s) !== ''" class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-600" x-text="alasan(s)"></span>
                        </button>
                    </template>
                </div>
                <button type="button" @click="pick.buka = false" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Tutup</button>
            </div>
        </div>
    </section>

    <!-- Perusahaan -->
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Perusahaan</h3>
        <div class="space-y-4 p-5">
            <?php $input('perusahaan_nama', 'Nama perusahaan', $val('perusahaan_nama'), ['wajib' => true, 'maks' => 150, 'ph' => 'Contoh: PT Telkom Indonesia', 'hint' => 'Periksa ejaannya — nama ini jadi saran untuk siswa lain setelah di-ACC.']) ?>
            <div>
                <label class="lbl" for="f_perusahaan_alamat">Alamat <span class="text-red-500">*</span></label>
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
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Kontak pengaju</h3>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <?php $input('hp', 'No. HP / WhatsApp', $hp, ['wajib' => true, 'maks' => 20, 'ph' => '081234567890', 'hint' => 'Wajib diisi. Tercetak di surat.']) ?>
        </div>
    </section>

    <?php if (! $ubah): ?>
        <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Status awal</h3>
            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2">
                <?php $awal = ($old['status_awal'] ?? 'menunggu') === 'disetujui' ? 'disetujui' : 'menunggu'; ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 border-slate-200">
                    <input type="radio" name="status_awal" value="menunggu" <?= $awal === 'menunggu' ? 'checked' : '' ?> class="mt-1">
                    <span><b class="block text-sm text-slate-800">Menunggu pemeriksaan</b><span class="text-xs text-slate-500">Masuk antrean seperti ajuan siswa; diputuskan nanti.</span></span>
                </label>
                <?php if (! empty($bolehAcc)): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:ring-2 has-[:checked]:ring-green-500/20 border-slate-200">
                        <input type="radio" name="status_awal" value="disetujui" <?= $awal === 'disetujui' ? 'checked' : '' ?> class="mt-1">
                        <span><b class="block text-sm text-slate-800">Langsung disetujui</b><span class="text-xs text-slate-500">Untuk data lama yang sudah pasti; tercatat Anda yang menyetujui. Siswa langsung terkunci.</span></span>
                    </label>
                <?php else: ?>
                    <div class="flex items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3.5">
                        <span class="mt-1 text-slate-400" aria-hidden="true">🔒</span>
                        <span><b class="block text-sm text-slate-600">Langsung disetujui</b><span class="text-xs text-slate-500">Hanya Waka Hubin (atau Admin) yang bisa menyetujui. Simpan sebagai menunggu, lalu minta Waka Hubin meng-ACC.</span></span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <a href="<?= site_url($ubah ? 'admin/pkl/' . $a['id'] : 'admin/pkl') ?>" class="rounded-xl border border-slate-300 px-6 py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</a>
        <button type="submit" :disabled="!pengaju || kirimTertunda" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50">
            <?= $ubah ? 'Simpan perubahan' : 'Simpan ajuan' ?>
        </button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl.js') ?>"></script>
<?= $this->endSection() ?>
