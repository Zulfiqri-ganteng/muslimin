<?php
/**
 * Form pengajuan PKL — wizard 5 langkah (Alpine: assets/js/pkl.js).
 *
 * Seluruh isian dikirim lewat fetch sehingga bila ada galat dari server,
 * isian di layar TIDAK hilang; draf juga disimpan di HP siswa (localStorage)
 * agar sinyal putus / tab tertutup tidak membuat siswa mengulang dari awal.
 *
 * @var array                                            $setting
 * @var array                                            $p       baris pkl_pengaturan
 * @var array<string, list<array{id:int, nama:string}>>  $kelas   per tingkat
 */

use App\Libraries\IsianBantu;

$bulan = IsianBantu::BULAN;
$thn   = (int) date('Y');

$awal       = (string) ($p['mulai_paling_awal'] ?? '');
$akhir      = (string) ($p['selesai_paling_akhir'] ?? '');
$thnAwal    = (int) substr($awal, 0, 4);
$thnAkhir   = (int) substr($akhir, 0, 4);
$maksAnggota = max(1, (int) ($p['maks_anggota'] ?? 1));
$durMin     = (int) ($p['durasi_min_hari'] ?? 0);
$durMaks    = (int) ($p['durasi_maks_hari'] ?? 0);

$kelasMap = [];
foreach ($kelas as $tingkat => $daftar) {
    foreach ($daftar as $k) {
        $kelasMap[$k['id']] = ['nama' => $k['nama'], 'tingkat' => $tingkat];
    }
}

$config = [
    'urlSiswa'       => site_url('pkl/siswa'),
    'urlPerusahaan'  => site_url('pkl/perusahaan'),
    'urlBuka'        => site_url('pkl/buka'),
    'urlKirim'       => site_url('pkl/kirim'),
    'kelas'          => $kelasMap,
    'bulan'          => $bulan,
    'maksAnggota'    => $maksAnggota,
    // Pagar tanggal dalam satu tahun → tahunnya terisi otomatis (kurangi satu ketukan & satu salah pilih).
    'tahunTunggal'   => ($thnAwal > 0 && $thnAwal === $thnAkhir) ? $thnAwal : null,
    'batas'          => ['awal' => $awal, 'akhir' => $akhir, 'min' => $durMin, 'maks' => $durMaks],
];

$langkah = ['Cari Nama', 'Perusahaan', 'Teman', 'Waktu & Kontak', 'Kirim'];

// ---------------------------------------------------------------------
// Pembangun elemen form (menjaga markup tiap kolom seragam)
// ---------------------------------------------------------------------

/** Tanda wajib / opsional di label. */
$tanda = static fn (bool $wajib): string => $wajib
    ? ' <span class="text-red-500">*</span>'
    : ' <span class="text-slate-400 font-normal">(boleh kosong)</span>';

/** Baris pesan galat untuk satu kunci. */
$galat = static fn (string $k): string => '<p class="err-msg" x-cloak x-show="err.' . $k . '" x-text="err.' . $k . '"></p>';

/**
 * Input teks. $o: wajib, hint, ph (placeholder), mode (inputmode), maks, auto
 * (autocomplete), err (kunci galat, default = $k), cls (kelas pembungkus), ekstra (atribut tambahan).
 */
$input = static function (string $k, string $label, array $o = []) use ($tanda, $galat): void {
    $err = $o['err'] ?? $k;
    echo '<div class="' . ($o['cls'] ?? '') . '">';
    echo '<label class="lbl" for="f_' . $k . '">' . esc($label) . $tanda($o['wajib'] ?? true) . '</label>';
    echo '<input type="text" id="f_' . $k . '" x-model="f.' . $k . '" class="inp inp-lg" :class="err.' . $err . ' && \'inp-err\'"'
        . (isset($o['ph']) ? ' placeholder="' . esc($o['ph'], 'attr') . '"' : '')
        . (isset($o['mode']) ? ' inputmode="' . $o['mode'] . '"' : '')
        . (isset($o['maks']) ? ' maxlength="' . (int) $o['maks'] . '"' : '')
        . ' autocomplete="' . ($o['auto'] ?? 'off') . '"'
        . ($o['ekstra'] ?? '')
        . '>';
    if (! empty($o['hint'])) {
        echo '<p class="hint">' . $o['hint'] . '</p>';
    }
    echo $galat($err) . '</div>';
};

/** Tanggal sebagai 3 pilihan (tgl / bulan / tahun) — lebih mudah dari date picker HP. */
$tanggal = static function (string $awalan, string $err, string $label, int $dari, int $sampai, bool $turun, string $hint = '') use ($tanda, $galat, $bulan): void {
    echo '<div><span class="lbl">' . esc($label) . $tanda(true) . '</span>';
    echo '<div class="grid grid-cols-[4.5rem_1fr_5.5rem] gap-2">';
    echo '<select x-model="f.' . $awalan . '_d" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Tanggal"><option value="">Tgl</option>';
    for ($i = 1; $i <= 31; $i++) {
        echo '<option value="' . $i . '">' . $i . '</option>';
    }
    echo '</select><select x-model="f.' . $awalan . '_m" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Bulan"><option value="">Bulan</option>';
    foreach ($bulan as $i => $b) {
        echo '<option value="' . ($i + 1) . '">' . $b . '</option>';
    }
    echo '</select><select x-model="f.' . $awalan . '_y" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Tahun"><option value="">Tahun</option>';
    $tahun = range($dari, $sampai);
    foreach ($turun ? array_reverse($tahun) : $tahun as $y) {
        echo '<option value="' . $y . '">' . $y . '</option>';
    }
    echo '</select></div>';
    if ($hint !== '') {
        echo '<p class="hint">' . $hint . '</p>';
    }
    echo $galat($err) . '</div>';
};

/** Kepala kartu langkah. */
$kepala = static fn (int $no, string $judul): string => '<div class="bio-head"><span class="bio-num">' . $no . '</span><h2 class="text-white font-bold text-lg">' . esc($judul) . '</h2></div>';

/** Pilihan kelas (dikelompokkan per tingkat) — dipakai langkah 1 dan pemilih teman. */
$pilihanKelas = static function () use ($kelas): void {
    echo '<option value="">— Pilih kelas —</option>';
    foreach ($kelas as $tingkat => $daftar) {
        echo '<optgroup label="Kelas ' . esc($tingkat) . '">';
        foreach ($daftar as $k) {
            echo '<option value="' . (int) $k['id'] . '">' . esc($k['nama']) . '</option>';
        }
        echo '</optgroup>';
    }
};

/** Ikon peringatan segitiga. */
$ikonAwas = '<svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
?>
<?= $this->extend('layouts/pkl') ?>

<?= $this->section('content') ?>
<div class="max-w-2xl mx-auto px-4 py-5 sm:py-10"
     x-data="pklForm"
     data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <?= view('pkl/_header', ['setting' => $setting]) ?>

    <!-- Persiapan — hanya di langkah pertama -->
    <div x-show="step === 0" class="rise rise-2 mt-4 rounded-2xl border-2 border-amber-300 bg-amber-50 px-4 py-4 sm:px-5 flex gap-3 text-amber-500">
        <?= $ikonAwas ?>
        <div class="text-sm text-amber-900 leading-relaxed">
            <p class="font-extrabold">Siapkan dulu sebelum mengisi:</p>
            <ul class="mt-1 list-disc pl-5 space-y-0.5">
                <li>Nama, <b>alamat lengkap</b>, dan telepon perusahaan tempat PKL</li>
                <li>Nama pimpinan atau kontak di perusahaan (bila ada)</li>
                <li>Tanggal mulai &amp; selesai PKL — antara <b><?= esc(IsianBantu::tanggalIndo($awal)) ?></b> dan <b><?= esc(IsianBantu::tanggalIndo($akhir)) ?></b></li>
                <li>Nama teman yang PKL di tempat yang sama (maksimal <b><?= $maksAnggota ?></b> siswa per perusahaan)</li>
            </ul>
            <p class="mt-2 font-semibold">Satu siswa hanya boleh punya satu ajuan. Jangan mengisi atas nama orang lain.</p>
        </div>
    </div>

    <!-- Stepper -->
    <div class="rise rise-3 mt-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-5" id="pklTop">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-brand-700">Langkah <span x-text="step + 1">1</span> dari <?= count($langkah) ?></p>
            <p class="text-xs font-semibold text-slate-400" x-text="judulLangkah[step]"><?= esc($langkah[0]) ?></p>
        </div>
        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full bg-brand-600 transition-all duration-300" :style="'width:' + ((step + 1) / <?= count($langkah) ?> * 100) + '%'" style="width: <?= round(100 / count($langkah)) ?>%"></div>
        </div>
        <div class="mt-4 hidden sm:flex items-start justify-between gap-1">
            <?php foreach ($langkah as $i => $lbl): ?>
                <button type="button" class="step-dot flex-1" :class="{ 'is-active': step === <?= $i ?>, 'is-done': step > <?= $i ?> }" @click="step > <?= $i ?> && <?= $i ?> > 0 && keLangkah(<?= $i ?>)">
                    <span class="dot-circle"><?= $i + 1 ?></span><span class="dot-label"><?= esc($lbl) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Catatan staf saat perbaikan -->
    <div x-cloak x-show="modeRevisi && step > 0" class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
        <p class="font-bold">Kamu sedang memperbaiki ajuan PKL.</p>
        <p class="mt-0.5" x-show="catatan"><span class="font-semibold">Catatan dari sekolah:</span> <span x-text="catatan"></span></p>
    </div>

    <div class="mt-4">
        <!-- ============ LANGKAH 1 — CARI NAMA ============ -->
        <section class="bio-card" x-show="step === 0">
            <?= $kepala(1, 'Cari Namamu') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <div>
                    <label class="lbl" for="pilihKelas">Kelas kamu sekarang <span class="text-red-500">*</span></label>
                    <select id="pilihKelas" class="inp inp-lg" x-model="kelasId" @change="muatSiswa()">
                        <?php $pilihanKelas() ?>
                    </select>
                </div>

                <p x-cloak x-show="memuat" class="flex items-center gap-2 text-sm text-slate-500">
                    <svg class="animate-spin w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Memuat daftar nama…
                </p>
                <p x-cloak x-show="pesanDaftar" x-text="pesanDaftar" class="text-sm font-semibold text-red-600"></p>

                <div x-cloak x-show="kelasId && !memuat && siswaList.length">
                    <label class="lbl" for="cariNama">Pilih namamu <span class="text-red-500">*</span></label>
                    <input id="cariNama" type="search" class="inp inp-lg" x-model="cari" placeholder="Ketik namamu untuk mencari…" autocomplete="off">
                    <div class="mt-2 max-h-80 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                        <template x-for="s in siswaTersaring()" :key="s.id">
                            <button type="button" @click="pilihSiswa(s)"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left transition"
                                    :class="pilih && pilih.id === s.id ? 'bg-brand-50 ring-2 ring-inset ring-brand-500' : 'hover:bg-slate-50'">
                                <span class="font-medium text-slate-800" x-text="s.nama"></span>
                                <span x-show="s.status !== 'belum'" class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="lencana(s.status).kelas" x-text="lencana(s.status).teks"></span>
                            </button>
                        </template>
                        <p x-show="!siswaTersaring().length" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada nama yang cocok dengan “<span x-text="cari"></span>”.</p>
                    </div>
                </div>

                <!-- Panel setelah nama dipilih -->
                <div x-ref="panel" x-cloak x-show="pilih">
                    <!-- Belum punya ajuan aktif (termasuk yang pernah ditolak) -->
                    <template x-if="pilih && (pilih.status === 'belum' || pilih.status === 'ditolak')">
                        <div class="rounded-xl border-2 border-brand-200 bg-brand-50 p-4">
                            <p class="text-sm text-slate-600">Kamu memilih:</p>
                            <p class="mt-0.5 text-lg font-extrabold text-brand-800" x-text="pilih.nama"></p>
                            <p class="text-sm font-semibold text-slate-500" x-text="'Kelas ' + namaKelas()"></p>
                            <p x-show="pilih.status === 'ditolak'" class="mt-3 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-800">
                                Ajuan PKL-mu sebelumnya <b>ditolak</b>, jadi kamu boleh mengajukan lagi. Tanyakan alasannya ke operator sekolah atau Waka Hubin agar tidak terulang.
                            </p>
                            <p class="mt-3 text-sm text-slate-600">Benar ini kamu? Pastikan tidak salah pilih nama teman.</p>
                            <button type="button" @click="mulai()" class="btn-nav-primary w-full mt-3">Ya, ini saya — Mulai Isi &rarr;</button>
                        </div>
                    </template>

                    <!-- Sudah punya ajuan: menunggu / disetujui -->
                    <template x-if="pilih && (pilih.status === 'menunggu' || pilih.status === 'disetujui')">
                        <div class="rounded-xl border p-4" :class="pilih.status === 'disetujui' ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50'">
                            <p class="font-extrabold" :class="pilih.status === 'disetujui' ? 'text-green-800' : 'text-blue-800'" x-text="pilih.status === 'disetujui' ? '✓ Ajuan PKL sudah disetujui sekolah' : '✓ Ajuan PKL sudah dikirim'"></p>
                            <p class="mt-1 text-sm text-slate-600">
                                <span x-text="pilih.nama"></span> tidak perlu mengisi lagi<span x-show="pilih.status === 'menunggu'"> — ajuannya sedang diperiksa sekolah</span>.
                                Jika ada data yang salah, hubungi operator sekolah atau Waka Hubin.
                            </p>
                        </div>
                    </template>

                    <!-- Dikembalikan sekolah: hanya PENGAJU yang bisa membuka -->
                    <template x-if="pilih && pilih.status === 'perbaikan' && pilih.peran === 'pengaju'">
                        <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4 space-y-3">
                            <div>
                                <p class="font-extrabold text-amber-900">Sekolah meminta kamu memperbaiki ajuan PKL.</p>
                                <p class="mt-1 text-sm text-amber-900">Untuk membuka ajuanmu, masukkan <b>tanggal lahir</b> yang dulu kamu isi di formulir ini.</p>
                            </div>
                            <div>
                                <span class="lbl">Tanggal Lahir</span>
                                <div class="grid grid-cols-[4.5rem_1fr_5.5rem] gap-2">
                                    <select x-model="buka.d" class="inp inp-lg !px-2 bg-white" aria-label="Tanggal"><option value="">Tgl</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select>
                                    <select x-model="buka.m" class="inp inp-lg !px-2 bg-white" aria-label="Bulan"><option value="">Bulan</option><?php foreach ($bulan as $i => $b): ?><option value="<?= $i + 1 ?>"><?= $b ?></option><?php endforeach; ?></select>
                                    <select x-model="buka.y" class="inp inp-lg !px-2 bg-white" aria-label="Tahun"><option value="">Tahun</option><?php for ($y = $thn - 8; $y >= $thn - 40; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select>
                                </div>
                            </div>
                            <p x-show="buka.pesan" x-text="buka.pesan" class="text-sm font-semibold text-red-600"></p>
                            <button type="button" @click="bukaAjuan()" :disabled="buka.proses" class="btn-nav-primary w-full disabled:opacity-60">
                                <span x-text="buka.proses ? 'Memeriksa…' : 'Buka Ajuan Saya'"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Dikembalikan sekolah, tapi dia hanya TEMAN di ajuan itu -->
                    <template x-if="pilih && pilih.status === 'perbaikan' && pilih.peran !== 'pengaju'">
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                            <p class="font-extrabold text-amber-900">Ajuan PKL-mu sedang diminta perbaikan</p>
                            <p class="mt-1 text-sm text-amber-900">
                                Namamu tercantum di ajuan yang diajukan <b>temanmu</b>. Hanya yang mengajukan yang bisa memperbaikinya —
                                minta dia membukanya, atau hubungi operator sekolah.
                            </p>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Namamu tidak ada di daftar, atau salah kelas? Jangan memilih nama orang lain —
                    hubungi wali kelas atau operator sekolah agar datamu diperbaiki dulu.
                </p>
            </div>
        </section>

        <!-- ============ LANGKAH 2 — PERUSAHAAN ============ -->
        <section class="bio-card" x-cloak x-show="step === 1">
            <?= $kepala(2, 'Tempat PKL') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <div>
                    <label class="lbl" for="f_perusahaan_nama">Nama Perusahaan<?= $tanda(true) ?></label>
                    <input type="text" id="f_perusahaan_nama" x-model="f.perusahaan_nama" maxlength="150" autocomplete="off"
                           @input="cariSaran()" @focus="saran.hasil.length && (saran.buka = true)" @blur="tutupSaran()" @keydown.escape="saran.buka = false"
                           class="inp inp-lg" :class="err.perusahaan_nama && 'inp-err'" placeholder="Contoh: PT Telkom Indonesia">
                    <p class="hint">Tulis nama <b>lengkap</b>, jangan disingkat. Ketik beberapa huruf — bila perusahaan ini pernah diajukan siswa lain, pilihannya muncul dan datanya terisi sendiri.</p>
                    <?= $galat('perusahaan_nama') ?>

                    <div x-cloak x-show="saran.buka && saran.hasil.length" class="mt-2 overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm">
                        <p class="bg-brand-50 px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-brand-700">Pernah diajukan &amp; disetujui — ketuk untuk mengisi otomatis</p>
                        <div class="divide-y divide-slate-100">
                            <template x-for="s in saran.hasil" :key="s.id">
                                <button type="button" @mousedown.prevent @click="pakaiSaran(s)" class="w-full px-4 py-3 text-left transition hover:bg-slate-50">
                                    <span class="block font-semibold text-slate-800" x-text="s.nama"></span>
                                    <span class="block text-xs text-slate-500" x-text="[s.kota, s.alamat].filter(Boolean).join(' · ')"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p x-cloak x-show="saran.terpakai" class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800">
                        ✓ Data diisi dari perusahaan yang sudah terdaftar. Periksa lagi dan ubah bila ada yang berbeda.
                    </p>
                </div>

                <div>
                    <label class="lbl" for="f_perusahaan_alamat">Alamat Perusahaan<?= $tanda(true) ?></label>
                    <textarea id="f_perusahaan_alamat" x-model="f.perusahaan_alamat" rows="3" maxlength="255" autocomplete="off"
                              class="inp inp-lg" :class="err.perusahaan_alamat && 'inp-err'" placeholder="Contoh: Jl. Raya Industri No. 12, Kawasan MM2100, Cikarang Barat"></textarea>
                    <p class="hint">Nama jalan, nomor, dan daerahnya — supaya surat dari sekolah sampai ke tujuan.</p>
                    <?= $galat('perusahaan_alamat') ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <?php $input('perusahaan_kota', 'Kota / Kabupaten', ['maks' => 100, 'ph' => 'Contoh: Bekasi']) ?>
                    <?php $input('perusahaan_telepon', 'Telepon Perusahaan', ['wajib' => false, 'mode' => 'tel', 'maks' => 30, 'ph' => 'Contoh: 02188776655', 'hint' => 'Kosongkan bila kamu tidak tahu.']) ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <?php $input('kontak_nama', 'Nama Pimpinan / Kontak', ['wajib' => false, 'maks' => 150, 'auto' => 'off', 'ph' => 'Contoh: Bapak Andi Wijaya', 'hint' => 'Orang yang dituju di perusahaan, bila kamu tahu.']) ?>
                    <?php $input('kontak_jabatan', 'Jabatan', ['wajib' => false, 'maks' => 100, 'ph' => 'Contoh: Manajer HRD']) ?>
                </div>
            </div>
        </section>

        <!-- ============ LANGKAH 3 — TEMAN ============ -->
        <section class="bio-card" x-cloak x-show="step === 2">
            <?= $kepala(3, 'Teman Satu Tempat') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <div class="rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm leading-relaxed text-slate-700">
                    <?php if ($maksAnggota > 1): ?>
                        <p>Ada temanmu yang PKL di <b>perusahaan yang sama</b>? Tambahkan di sini — cukup <b>satu orang</b> yang mengisi, temanmu <b>tidak perlu mengisi lagi</b>. Kalau PKL sendirian, langsung tekan <b>Lanjut</b>.</p>
                        <p class="mt-1.5 text-xs text-slate-500">Maksimal <b><?= $maksAnggota ?></b> siswa per perusahaan (termasuk kamu). Hanya siswa yang belum mengajukan PKL yang bisa dipilih.</p>
                    <?php else: ?>
                        <p>Perusahaan hanya menerima <b>satu siswa</b> per ajuan, jadi langkah ini bisa dilewati. Tekan <b>Lanjut</b>.</p>
                    <?php endif; ?>
                </div>

                <div x-show="<?= $maksAnggota > 1 ? 'true' : 'false' ?>">
                    <p class="lbl">Teman yang dipilih (<span x-text="teman.length"></span> dari <span x-text="maksTeman()"></span>)</p>
                    <p x-show="!teman.length" class="rounded-xl border-2 border-dashed border-slate-200 px-4 py-5 text-center text-sm text-slate-500">Belum ada teman yang dipilih.</p>
                    <ul class="space-y-2">
                        <template x-for="t in teman" :key="t.id">
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-slate-800" x-text="t.nama"></span>
                                    <span class="block text-xs text-slate-500" x-text="t.kelas ? 'Kelas ' + t.kelas : ''"></span>
                                </span>
                                <button type="button" @click="hapusTeman(t.id)" class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50" :aria-label="'Hapus ' + t.nama">Hapus</button>
                            </li>
                        </template>
                    </ul>
                    <?= $galat('teman') ?>
                </div>

                <?php if ($maksAnggota > 1): ?>
                    <div>
                        <button type="button" x-show="bisaTambahTeman()" @click="bukaPemilih()" class="btn-nav-secondary w-full">
                            <span x-text="pick.buka ? 'Tutup daftar' : '+ Tambah teman'"></span>
                        </button>
                        <p x-cloak x-show="!bisaTambahTeman()" class="text-center text-sm font-semibold text-slate-500">Batas jumlah siswa per perusahaan sudah tercapai.</p>
                    </div>

                    <div x-cloak x-show="pick.buka && bisaTambahTeman()" class="space-y-4 rounded-2xl border-2 border-brand-200 bg-brand-50/50 p-4">
                        <div>
                            <label class="lbl" for="pickKelas">Kelas temanmu</label>
                            <select id="pickKelas" class="inp inp-lg bg-white" x-model="pick.kelasId" @change="muatPemilih()">
                                <?php $pilihanKelas() ?>
                            </select>
                        </div>
                        <p x-cloak x-show="pick.memuat" class="text-sm text-slate-500">Memuat daftar nama…</p>
                        <p x-cloak x-show="pick.pesan" x-text="pick.pesan" class="text-sm font-semibold text-red-600"></p>
                        <div x-cloak x-show="pick.kelasId && !pick.memuat && pick.list.length">
                            <input type="search" class="inp inp-lg bg-white" x-model="pick.cari" placeholder="Ketik nama temanmu…" autocomplete="off" aria-label="Cari nama teman">
                            <div class="mt-2 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                                <template x-for="s in pemilihTersaring()" :key="s.id">
                                    <button type="button" @click="tambahTeman(s)" :disabled="alasanTak(s) !== ''"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition disabled:cursor-not-allowed"
                                            :class="alasanTak(s) !== '' ? 'bg-slate-50' : 'hover:bg-brand-50'">
                                        <span class="font-medium" :class="alasanTak(s) !== '' ? 'text-slate-400' : 'text-slate-800'" x-text="s.nama"></span>
                                        <span x-show="alasanTak(s) !== ''" class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-600" x-text="alasanTak(s)"></span>
                                        <span x-show="alasanTak(s) === ''" class="shrink-0 text-xs font-bold text-brand-600">+ Pilih</span>
                                    </button>
                                </template>
                                <p x-show="!pemilihTersaring().length" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada nama yang cocok.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============ LANGKAH 4 — WAKTU & KONTAK ============ -->
        <section class="bio-card" x-cloak x-show="step === 3">
            <?= $kepala(4, 'Waktu PKL & Kontak') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900">
                    Periode PKL harus <b>di antara <?= esc(IsianBantu::tanggalIndo($awal)) ?> dan <?= esc(IsianBantu::tanggalIndo($akhir)) ?></b>,
                    dengan lama <b><?= $durMin ?>&ndash;<?= $durMaks ?> hari</b>.
                </div>

                <?php $tanggal('mulai', 'tanggal_mulai', 'Tanggal Mulai PKL', $thnAwal, $thnAkhir, false) ?>
                <?php $tanggal('selesai', 'tanggal_selesai', 'Tanggal Selesai PKL', $thnAwal, $thnAkhir, false) ?>
                <p x-cloak x-show="infoDurasi()" x-text="infoDurasi()" class="text-sm font-bold" :class="durasiWajar() ? 'text-brand-700' : 'text-red-600'"></p>

                <hr class="border-slate-200">

                <?php $input('hp', 'No. HP / WhatsApp kamu', ['mode' => 'tel', 'maks' => 20, 'auto' => 'tel', 'ph' => 'Contoh: 081234567890', 'hint' => 'Nomor <b>yang aktif sekarang</b> — dipakai sekolah bila perlu menghubungimu.']) ?>

                <?php $tanggal('lahir', 'tanggal_lahir', 'Tanggal Lahir', $thn - 40, $thn - 8, true, 'Dipakai untuk <b>membuka ajuanmu</b> bila sekolah memintamu memperbaikinya. Ingat tanggal ini.') ?>
            </div>
        </section>

        <!-- ============ LANGKAH 5 — PERIKSA & KIRIM ============ -->
        <section class="bio-card" x-cloak x-show="step === 4">
            <?= $kepala(5, 'Periksa & Kirim') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <p class="text-sm leading-relaxed text-slate-600">Periksa sekali lagi. Setelah dikirim, ajuan <b>terkunci</b> dan tidak bisa kamu ubah sendiri.</p>

                <!-- Siswa -->
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                        <p class="bio-sub">Siswa yang PKL (<span x-text="1 + teman.length"></span>)</p>
                        <button type="button" @click="keLangkah(2)" class="text-xs font-bold text-brand-600 hover:text-brand-800">Ubah teman</button>
                    </div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0"><span class="block truncate font-semibold text-slate-800" x-text="pilih ? pilih.nama : ''"></span><span class="block text-xs text-slate-500" x-text="'Kelas ' + namaKelas()"></span></span>
                            <span class="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-700">Pengaju</span>
                        </li>
                        <template x-for="t in teman" :key="t.id">
                            <li class="px-4 py-2.5"><span class="block font-semibold text-slate-800" x-text="t.nama"></span><span class="block text-xs text-slate-500" x-text="t.kelas ? 'Kelas ' + t.kelas : ''"></span></li>
                        </template>
                    </ul>
                </div>

                <!-- Perusahaan -->
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                        <p class="bio-sub">Tempat PKL</p>
                        <button type="button" @click="keLangkah(1)" class="text-xs font-bold text-brand-600 hover:text-brand-800">Ubah</button>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        <?php foreach (['perusahaan_nama' => 'Perusahaan', 'perusahaan_alamat' => 'Alamat', 'perusahaan_kota' => 'Kota/Kab.', 'perusahaan_telepon' => 'Telepon', 'kontak_nama' => 'Pimpinan/Kontak', 'kontak_jabatan' => 'Jabatan'] as $k => $lbl): ?>
                            <div class="grid grid-cols-[7rem_1fr] sm:grid-cols-[9rem_1fr] gap-3 px-4 py-2.5 text-sm">
                                <dt class="text-slate-500"><?= esc($lbl) ?></dt>
                                <dd class="font-semibold text-slate-800 break-words" x-text="tampil('<?= $k ?>')"></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>

                <!-- Waktu & kontak -->
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                        <p class="bio-sub">Waktu &amp; Kontak</p>
                        <button type="button" @click="keLangkah(3)" class="text-xs font-bold text-brand-600 hover:text-brand-800">Ubah</button>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        <?php foreach (['tanggal_mulai' => 'Mulai', 'tanggal_selesai' => 'Selesai', 'hp' => 'No. HP', 'tanggal_lahir' => 'Tanggal Lahir'] as $k => $lbl): ?>
                            <div class="grid grid-cols-[7rem_1fr] sm:grid-cols-[9rem_1fr] gap-3 px-4 py-2.5 text-sm">
                                <dt class="text-slate-500"><?= esc($lbl) ?></dt>
                                <dd class="font-semibold text-slate-800 break-words" x-text="tampil('<?= $k ?>')"></dd>
                            </div>
                        <?php endforeach; ?>
                        <div class="grid grid-cols-[7rem_1fr] sm:grid-cols-[9rem_1fr] gap-3 px-4 py-2.5 text-sm" x-show="infoDurasi()">
                            <dt class="text-slate-500">Lama PKL</dt>
                            <dd class="font-semibold text-slate-800" x-text="infoDurasi().replace('Lama PKL: ', '')"></dd>
                        </div>
                    </dl>
                </div>

                <label class="check-card !items-start" :class="err.pernyataan && '!border-red-400'">
                    <input type="checkbox" x-model="f.pernyataan" class="sr-only">
                    <span class="check-box mt-0.5"></span>
                    <span class="text-sm text-slate-700">Saya menyatakan data di atas <b>sudah benar</b>, dan siap memperbaikinya bila diminta sekolah.</span>
                </label>
                <?= $galat('pernyataan') ?>
            </div>
        </section>

        <!-- Pesan kirim (galat umum dari server / koneksi) -->
        <div x-cloak x-show="pesanKirim" class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm font-semibold text-red-700" x-text="pesanKirim"></div>

        <!-- Jebakan bot: tak terlihat manusia -->
        <div class="hidden" aria-hidden="true"><input type="text" x-ref="hp" name="website" tabindex="-1" autocomplete="off"></div>

        <!-- Navigasi -->
        <div x-cloak x-show="step > 0" class="mt-5 flex items-center justify-between gap-3">
            <button type="button" @click="kembali()" class="btn-nav-secondary">&larr; Kembali</button>
            <button type="button" x-show="step < 4" @click="lanjut()" class="btn-nav-primary">Lanjut &rarr;</button>
            <button type="button" x-show="step === 4" @click="kirim()" :disabled="mengirim" class="btn-nav-gold disabled:opacity-70 disabled:cursor-wait">
                <svg x-show="mengirim" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="mengirim ? 'Mengirim…' : (modeRevisi ? 'Kirim Perbaikan' : 'Kirim Ajuan')"></span>
            </button>
        </div>
        <p x-cloak x-show="step > 0 && draftInfo" class="mt-3 text-center text-xs text-slate-400" x-text="draftInfo"></p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/pkl.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine.min.js') ?>"></script>
<?= $this->endSection() ?>
