<?php
/**
 * Form isian biodata siswa — wizard 6 langkah (Alpine: assets/js/biodata.js).
 *
 * Seluruh isian dikirim lewat fetch sehingga bila ada galat dari server,
 * isian di layar TIDAK hilang; draf juga disimpan di HP siswa (localStorage)
 * agar sinyal putus / tab tertutup tidak membuat siswa mengulang dari awal.
 *
 * @var array                                            $setting
 * @var array<string, list<array{id:int, nama:string}>>  $kelas   per tingkat
 */

use App\Libraries\BiodataForm;
use App\Models\SiswaModel;

$bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$thn   = (int) date('Y');

$kelasMap = [];
foreach ($kelas as $tingkat => $daftar) {
    foreach ($daftar as $k) {
        $kelasMap[$k['id']] = ['nama' => $k['nama'], 'tingkat' => $tingkat];
    }
}

$config = [
    'urlSiswa'  => site_url('biodata/siswa'),
    'urlBuka'   => site_url('biodata/buka'),
    'urlKirim'  => site_url('biodata/kirim'),
    'kelas'     => $kelasMap,
    'pekerjaan' => SiswaModel::PEKERJAAN,
    'label'     => BiodataForm::LABEL,
    'bagian'    => BiodataForm::BAGIAN,
    'bulan'     => $bulan,
];

$langkah = ['Cari Nama', 'Data Diri', 'Alamat', 'Orang Tua', 'Wali', 'Kirim'];

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
 * (autocomplete), err (kunci galat, default = $k), cls (kelas pembungkus).
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
        . (isset($o['list']) ? ' list="' . $o['list'] . '"' : '')
        . '>';
    if (! empty($o['hint'])) {
        echo '<p class="hint">' . $o['hint'] . '</p>';
    }
    echo $galat($err) . '</div>';
};

/** Tanggal sebagai 3 pilihan (tgl / bulan / tahun) — lebih mudah dari date picker HP. */
$tanggal = static function (string $p, string $err, string $label, int $dari, int $sampai, bool $wajib, string $hint = '') use ($tanda, $galat, $bulan): void {
    echo '<div><span class="lbl">' . esc($label) . $tanda($wajib) . '</span>';
    echo '<div class="grid grid-cols-[4.5rem_1fr_5.5rem] gap-2">';
    echo '<select x-model="f.' . $p . '_d" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Tanggal"><option value="">Tgl</option>';
    for ($i = 1; $i <= 31; $i++) {
        echo '<option value="' . $i . '">' . $i . '</option>';
    }
    echo '</select><select x-model="f.' . $p . '_m" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Bulan"><option value="">Bulan</option>';
    foreach ($bulan as $i => $b) {
        echo '<option value="' . ($i + 1) . '">' . $b . '</option>';
    }
    echo '</select><select x-model="f.' . $p . '_y" class="inp inp-lg !px-2" :class="err.' . $err . ' && \'inp-err\'" aria-label="Tahun"><option value="">Tahun</option>';
    for ($y = $sampai; $y >= $dari; $y--) {
        echo '<option value="' . $y . '">' . $y . '</option>';
    }
    echo '</select></div>';
    if ($hint !== '') {
        echo '<p class="hint">' . $hint . '</p>';
    }
    echo $galat($err) . '</div>';
};

/** Pilihan berbentuk tombol (radio). $opsi = [nilai => label]. */
$pil = static function (string $k, string $label, array $opsi, int $kolom = 2, string $hint = '') use ($tanda, $galat): void {
    echo '<div><span class="lbl">' . esc($label) . $tanda(true) . '</span>';
    echo '<div class="grid gap-2 ' . ($kolom === 3 ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-2') . '">';
    foreach ($opsi as $nilai => $teks) {
        echo '<label class="radio-pill"><input type="radio" value="' . esc((string) $nilai, 'attr') . '" x-model="f.' . $k . '" class="peer sr-only">'
            . '<span class="pill-label !block text-center !px-3 !py-2.5">' . esc($teks) . '</span></label>';
    }
    echo '</div>';
    if ($hint !== '') {
        echo '<p class="hint">' . $hint . '</p>';
    }
    echo $galat($k) . '</div>';
};

/** Pekerjaan: daftar baku + "Lainnya" yang memunculkan isian bebas. */
$pekerjaan = static function (string $k, string $label, bool $wajib) use ($tanda, $galat): void {
    echo '<div><label class="lbl" for="f_' . $k . '">' . esc($label) . $tanda($wajib) . '</label>';
    echo '<select id="f_' . $k . '" x-model="f.' . $k . '" class="inp inp-lg" :class="err.' . $k . ' && \'inp-err\'"><option value="">— Pilih pekerjaan —</option>';
    foreach (SiswaModel::PEKERJAAN as $p) {
        echo '<option value="' . esc($p, 'attr') . '">' . esc($p) . '</option>';
    }
    echo '<option value="__lain">Lainnya (tulis sendiri)</option></select>';
    echo '<input type="text" x-cloak x-show="f.' . $k . ' === \'__lain\'" x-model="f.' . $k . '_lain" maxlength="100" class="inp inp-lg mt-2" :class="err.' . $k . ' && \'inp-err\'" placeholder="Tulis pekerjaannya">';
    echo $galat($k) . '</div>';
};

/** Blok alamat terstruktur; $p = '' (siswa) atau 'ortu_'. */
$alamat = static function (string $p) use ($input): void {
    $input($p . 'alamat', 'Alamat (jalan / perumahan, blok, nomor rumah)', [
        'ph' => 'Contoh: Jl. Padjajaran Blok AK 10 No. 19, Perum VGH', 'maks' => 255,
        'hint' => 'Tulis sesuai alamat di <b>Kartu Keluarga</b>.',
    ]);
    echo '<div class="grid grid-cols-2 gap-3">';
    $input($p . 'rt', 'RT', ['ph' => 'Contoh: 18', 'mode' => 'numeric', 'maks' => 3]);
    $input($p . 'rw', 'RW', ['ph' => 'Contoh: 22', 'mode' => 'numeric', 'maks' => 3]);
    echo '</div>';
    $input($p . 'kelurahan', 'Kelurahan / Desa', ['ph' => 'Contoh: Kebalen', 'maks' => 100]);
    $input($p . 'kecamatan', 'Kecamatan', ['ph' => 'Contoh: Babelan', 'maks' => 100]);
    $input($p . 'kota', 'Kota / Kabupaten', ['ph' => 'Contoh: Bekasi', 'maks' => 100]);
};

/** Kepala kartu langkah. */
$kepala = static fn (int $no, string $judul): string => '<div class="bio-head"><span class="bio-num">' . $no . '</span><h2 class="text-white font-bold text-lg">' . esc($judul) . '</h2></div>';
?>
<?= $this->extend('layouts/biodata') ?>

<?= $this->section('content') ?>
<div class="max-w-2xl mx-auto px-4 py-5 sm:py-10"
     x-data="biodataForm"
     data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <?= view('biodata/_header', ['setting' => $setting]) ?>

    <!-- Peringatan KK — selalu tampil -->
    <div class="mt-4 rounded-2xl border-2 border-amber-300 bg-amber-50 px-4 py-4 sm:px-5 flex gap-3">
        <svg class="w-7 h-7 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div class="text-sm text-amber-900 leading-relaxed">
            <p class="font-extrabold">WAJIB: Siapkan Kartu Keluarga (KK) sebelum mengisi.</p>
            <p class="mt-0.5">Nama, tempat &amp; tanggal lahir, nama orang tua, dan alamat harus diisi <b>sama persis dengan yang tertulis di KK</b>. Data ini dipakai untuk buku induk dan ijazah.</p>
        </div>
    </div>

    <!-- Stepper -->
    <div class="mt-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-5" id="bioTop">
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

    <!-- Catatan admin saat perbaikan -->
    <div x-cloak x-show="modeRevisi && step > 0" class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
        <p class="font-bold">Kamu sedang memperbaiki biodata.</p>
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
                        <option value="">— Pilih kelas —</option>
                        <?php foreach ($kelas as $tingkat => $daftar): ?>
                            <optgroup label="Kelas <?= esc($tingkat) ?>">
                                <?php foreach ($daftar as $k): ?>
                                    <option value="<?= (int) $k['id'] ?>"><?= esc($k['nama']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
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
                    <template x-if="pilih && pilih.status === 'belum'">
                        <div class="rounded-xl border-2 border-brand-200 bg-brand-50 p-4">
                            <p class="text-sm text-slate-600">Kamu memilih:</p>
                            <p class="mt-0.5 text-lg font-extrabold text-brand-800" x-text="pilih.nama"></p>
                            <p class="text-sm font-semibold text-slate-500" x-text="namaKelas()"></p>
                            <p class="mt-3 text-sm text-slate-600">Benar ini kamu? Pastikan tidak salah pilih nama teman.</p>
                            <button type="button" @click="mulai()" class="btn-nav-primary w-full mt-3">Ya, ini saya — Mulai Isi &rarr;</button>
                        </div>
                    </template>
                    <template x-if="pilih && (pilih.status === 'menunggu' || pilih.status === 'disetujui')">
                        <div class="rounded-xl border p-4" :class="pilih.status === 'disetujui' ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50'">
                            <p class="font-extrabold" :class="pilih.status === 'disetujui' ? 'text-green-800' : 'text-blue-800'" x-text="pilih.status === 'disetujui' ? '✓ Biodata sudah diverifikasi sekolah' : '✓ Kamu sudah mengisi biodata'"></p>
                            <p class="mt-1 text-sm text-slate-600">
                                <span x-text="pilih.nama"></span> tidak perlu mengisi lagi<span x-show="pilih.status === 'menunggu'"> — isianmu sedang diperiksa sekolah</span>.
                                Jika ada data yang salah, hubungi wali kelas atau operator sekolah.
                            </p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'perbaikan'">
                        <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4 space-y-3">
                            <div>
                                <p class="font-extrabold text-amber-900">Sekolah meminta kamu memperbaiki biodata.</p>
                                <p class="mt-1 text-sm text-amber-900">Untuk membuka isianmu, masukkan <b>NISN</b> yang dulu kamu isi <b>atau</b> tanggal lahirmu (cukup salah satu).</p>
                            </div>
                            <div>
                                <label class="lbl" for="bukaNisn">NISN</label>
                                <input id="bukaNisn" type="text" x-model="buka.nisn" inputmode="numeric" maxlength="10" class="inp inp-lg bg-white" placeholder="10 angka" autocomplete="off">
                            </div>
                            <div>
                                <span class="lbl">atau Tanggal Lahir</span>
                                <div class="grid grid-cols-[4.5rem_1fr_5.5rem] gap-2">
                                    <select x-model="buka.d" class="inp inp-lg !px-2 bg-white" aria-label="Tanggal"><option value="">Tgl</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select>
                                    <select x-model="buka.m" class="inp inp-lg !px-2 bg-white" aria-label="Bulan"><option value="">Bulan</option><?php foreach ($bulan as $i => $b): ?><option value="<?= $i + 1 ?>"><?= $b ?></option><?php endforeach; ?></select>
                                    <select x-model="buka.y" class="inp inp-lg !px-2 bg-white" aria-label="Tahun"><option value="">Tahun</option><?php for ($y = $thn - 8; $y >= $thn - 40; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select>
                                </div>
                            </div>
                            <p x-show="buka.pesan" x-text="buka.pesan" class="text-sm font-semibold text-red-600"></p>
                            <button type="button" @click="bukaIsian()" :disabled="buka.proses" class="btn-nav-primary w-full disabled:opacity-60">
                                <span x-text="buka.proses ? 'Memeriksa…' : 'Buka Isian Saya'"></span>
                            </button>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Namamu tidak ada di daftar, atau salah kelas? Jangan memilih nama orang lain —
                    hubungi wali kelas atau operator sekolah agar datamu diperbaiki dulu.
                </p>
            </div>
        </section>

        <!-- ============ LANGKAH 2 — DATA DIRI ============ -->
        <section class="bio-card" x-cloak x-show="step === 1">
            <?= $kepala(2, 'Data Diri') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <?php $input('nama', 'Nama Lengkap', ['maks' => 150, 'auto' => 'name', 'hint' => 'Tulis <b>sesuai KK / akta kelahiran</b>, lengkap tanpa disingkat.']) ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="lbl" for="f_nisn">NISN <span class="text-red-500">*</span></label>
                        <input type="text" id="f_nisn" x-model="f.nisn" inputmode="numeric" maxlength="10" autocomplete="off" class="inp inp-lg tracking-wider" :class="err.nisn && 'inp-err'" placeholder="Contoh: 0094624339">
                        <p class="hint"><span x-text="f.nisn.replace(/\D/g, '').length"></span>/10 angka · ada di kartu pelajar atau rapor.</p>
                        <?= $galat('nisn') ?>
                    </div>
                    <?php $input('nis', 'NIS', ['wajib' => false, 'mode' => 'numeric', 'maks' => 30, 'ph' => 'Contoh: 252610280', 'hint' => 'Nomor induk dari sekolah (kartu pelajar).']) ?>
                </div>
                <?php $input('tempat_lahir', 'Tempat Lahir', ['maks' => 100, 'ph' => 'Contoh: Jakarta', 'hint' => 'Sesuai KK / akta kelahiran.']) ?>
                <?php $tanggal('lahir', 'tanggal_lahir', 'Tanggal Lahir', $thn - 40, $thn - 8, true) ?>
                <?php $pil('jenis_kelamin', 'Jenis Kelamin', ['L' => 'Laki-laki', 'P' => 'Perempuan']) ?>
                <div>
                    <label class="lbl" for="f_agama">Agama <span class="text-red-500">*</span></label>
                    <select id="f_agama" x-model="f.agama" class="inp inp-lg" :class="err.agama && 'inp-err'">
                        <option value="">— Pilih agama —</option>
                        <?php foreach (SiswaModel::AGAMA as $a): ?><option value="<?= esc($a, 'attr') ?>"><?= esc($a) ?></option><?php endforeach; ?>
                    </select>
                    <?= $galat('agama') ?>
                </div>
                <?php $pil('status_keluarga', 'Status dalam Keluarga', array_combine(SiswaModel::STATUS_KELUARGA, SiswaModel::STATUS_KELUARGA), 3, 'Lihat kolom <b>Status Hubungan dalam Keluarga</b> di KK.') ?>
                <?php $input('anak_ke', 'Anak Ke', ['mode' => 'numeric', 'maks' => 2, 'ph' => 'Contoh: 1', 'cls' => 'max-w-[10rem]']) ?>
            </div>
        </section>

        <!-- ============ LANGKAH 3 — ALAMAT & SEKOLAH ASAL ============ -->
        <section class="bio-card" x-cloak x-show="step === 2">
            <?= $kepala(3, 'Alamat & Sekolah Asal') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <p class="bio-sub">Alamat tempat tinggal siswa</p>
                <?php $alamat('') ?>
                <?php $input('no_hp', 'No. Telepon / HP Siswa', ['wajib' => false, 'mode' => 'tel', 'maks' => 20, 'ph' => 'Contoh: 087863419679', 'auto' => 'tel']) ?>

                <hr class="border-slate-100">
                <p class="bio-sub">Riwayat masuk sekolah</p>
                <?php $input('sekolah_asal', 'Sekolah Asal (SMP/MTs)', ['maks' => 150, 'ph' => 'Contoh: SMP NEGERI 6 BABELAN']) ?>
                <div>
                    <span class="lbl">Diterima di Sekolah</span>
                    <p class="rounded-lg bg-slate-50 border border-slate-200 px-3.5 py-3 text-base font-semibold text-slate-600"><?= esc($setting['school_name'] ?? '') ?></p>
                </div>
                <?php $input('diterima_kelas', 'Diterima di Kelas', ['maks' => 50, 'ph' => 'Contoh: X TKJ 8', 'list' => 'daftarKelas', 'hint' => 'Kelas saat <b>pertama kali</b> kamu diterima di sekolah ini, bukan kelas sekarang (kecuali kamu kelas X).']) ?>
                <datalist id="daftarKelas">
                    <?php foreach ($kelasMap as $k): ?><option value="<?= esc($k['nama'], 'attr') ?>"></option><?php endforeach; ?>
                </datalist>
                <?php $tanggal('terima', 'diterima_tanggal', 'Diterima pada Tanggal', 2010, $thn + 1, false, 'Tanggal pertama kali masuk sekolah ini. Kosongkan jika tidak tahu.') ?>
            </div>
        </section>

        <!-- ============ LANGKAH 4 — ORANG TUA ============ -->
        <section class="bio-card" x-cloak x-show="step === 3">
            <?= $kepala(4, 'Data Orang Tua') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <p class="hint !mt-0">Tulis nama ayah &amp; ibu <b>sesuai KK</b>. Jika sudah meninggal, tetap tulis namanya dan pilih pekerjaan “Sudah Meninggal”.</p>
                <?php $input('nama_ayah', 'Nama Ayah', ['maks' => 150]) ?>
                <?php $pekerjaan('pekerjaan_ayah', 'Pekerjaan Ayah', true) ?>
                <?php $input('nama_ibu', 'Nama Ibu', ['maks' => 150]) ?>
                <?php $pekerjaan('pekerjaan_ibu', 'Pekerjaan Ibu', true) ?>

                <hr class="border-slate-100">
                <p class="bio-sub">Alamat orang tua</p>
                <label class="check-card">
                    <input type="checkbox" x-model="f.ortu_sama" class="sr-only">
                    <span class="check-box"></span>
                    <span class="text-sm font-semibold text-slate-700">Alamat orang tua <b>sama</b> dengan alamat saya</span>
                </label>
                <div x-cloak x-show="!f.ortu_sama" class="space-y-5">
                    <?php $alamat('ortu_') ?>
                </div>

                <div>
                    <span class="lbl">No. Telepon / HP Orang Tua <span class="text-red-500">*</span></span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" x-model="f.ortu_telepon1" inputmode="tel" maxlength="20" autocomplete="off" class="inp inp-lg" :class="err.ortu_telepon && 'inp-err'" placeholder="Nomor 1 (wajib)" aria-label="Nomor telepon orang tua 1">
                        <input type="text" x-model="f.ortu_telepon2" inputmode="tel" maxlength="20" autocomplete="off" class="inp inp-lg" :class="err.ortu_telepon && 'inp-err'" placeholder="Nomor 2 (boleh kosong)" aria-label="Nomor telepon orang tua 2">
                    </div>
                    <p class="hint">Nomor yang aktif dan bisa dihubungi sekolah.</p>
                    <?= $galat('ortu_telepon') ?>
                </div>
            </div>
        </section>

        <!-- ============ LANGKAH 5 — WALI ============ -->
        <section class="bio-card" x-cloak x-show="step === 4">
            <?= $kepala(5, 'Data Wali') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <?php $pil('punya_wali', 'Apakah kamu memiliki wali selain ayah/ibu?', ['tidak' => 'Tidak ada', 'ya' => 'Ya, ada wali'], 2, 'Wali = orang selain ayah/ibu yang mengasuh dan mengurus sekolahmu (mis. kakek, paman). Pilih <b>Tidak ada</b> jika kamu tinggal bersama orang tua.') ?>
                <div x-cloak x-show="f.punya_wali === 'ya'" class="space-y-5">
                    <?php $input('nama_wali', 'Nama Wali', ['maks' => 150]) ?>
                    <?php $input('no_hp_wali', 'No. Telepon / HP Wali', ['mode' => 'tel', 'maks' => 20, 'ph' => 'Contoh: 081234567890']) ?>
                    <?php $input('alamat_wali', 'Alamat Wali', ['wajib' => false, 'maks' => 255, 'ph' => 'Alamat lengkap wali']) ?>
                    <?php $pekerjaan('pekerjaan_wali', 'Pekerjaan Wali', false) ?>
                </div>
            </div>
        </section>

        <!-- ============ LANGKAH 6 — PERIKSA & KIRIM ============ -->
        <section class="bio-card" x-cloak x-show="step === 5">
            <?= $kepala(6, 'Periksa & Kirim') ?>
            <div class="p-5 sm:p-6 space-y-5">
                <p class="text-sm text-slate-600">Periksa sekali lagi. Setelah dikirim, isian <b>tidak bisa diubah sendiri</b>.</p>
                <template x-for="(kolom, bagian) in cfg.bagian" :key="bagian">
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                            <h3 class="text-sm font-bold text-slate-700" x-text="bagian"></h3>
                            <button type="button" @click="keLangkah(langkahBagian[bagian])" class="text-xs font-bold text-brand-600 hover:text-brand-800">Ubah</button>
                        </div>
                        <dl class="divide-y divide-slate-100">
                            <template x-if="bagian === 'Wali' && f.punya_wali !== 'ya'">
                                <div class="px-4 py-2.5 text-sm text-slate-500">Tidak ada wali selain orang tua.</div>
                            </template>
                            <template x-for="k in kolom" :key="k">
                                <div x-show="bagian !== 'Wali' || f.punya_wali === 'ya'" class="grid grid-cols-[8.5rem_1fr] sm:grid-cols-[12rem_1fr] gap-3 px-4 py-2.5 text-sm">
                                    <dt class="text-slate-500" x-text="cfg.label[k]"></dt>
                                    <dd class="font-semibold text-slate-800 break-words" x-text="tampil(k)"></dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </template>

                <label class="check-card !items-start" :class="err.pernyataan && '!border-red-400'">
                    <input type="checkbox" x-model="f.pernyataan" class="sr-only">
                    <span class="check-box mt-0.5"></span>
                    <span class="text-sm text-slate-700">Saya menyatakan data di atas <b>benar dan sesuai Kartu Keluarga</b>. Saya siap memperbaikinya bila diminta sekolah.</span>
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
            <button type="button" x-show="step < 5" @click="lanjut()" class="btn-nav-primary">Lanjut &rarr;</button>
            <button type="button" x-show="step === 5" @click="kirim()" :disabled="mengirim" class="btn-nav-gold disabled:opacity-70 disabled:cursor-wait">
                <svg x-show="mengirim" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="mengirim ? 'Mengirim…' : 'Kirim Biodata'"></span>
            </button>
        </div>
        <p x-cloak x-show="step > 0 && draftInfo" class="mt-3 text-center text-xs text-slate-400" x-text="draftInfo"></p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/biodata.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/biodata.js') ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<?= $this->endSection() ?>
