<?php
/**
 * Form pengajuan PKL — wizard 5 langkah (Alpine: assets/js/pkl.js).
 *
 * Seluruh isian dikirim lewat fetch sehingga bila ada galat dari server,
 * isian di layar TIDAK hilang; draf juga disimpan di HP siswa (localStorage)
 * agar sinyal putus / tab tertutup tidak membuat siswa mengulang dari awal.
 *
 * (P6a rombak publik) Bingkai layouts/formulir.php + kit publik.css + Alpine versi CSP: alur, isian, kunci galat, dan logika
 * pkl.js TIDAK berubah — yang berubah hanya kelas tampilan; bilah langkah bersegmen (tanpa :style teks), ekspresi ber-fungsi
 * global diganti metode pkl.js (lokasiSaran, infoTeman), pilihan nama memakai aria-pressed.
 *
 * @var array                                            $setting
 * @var array                                            $p         baris pkl_pengaturan
 * @var array<string, list<array{id:int, nama:string}>>  $kelas     per tingkat
 * @var int                                              $batasHari batas keputusan sekolah (hari)
 * @var int                                              $maksSiswa maksimal siswa per ajuan (≤ 5)
 */

use App\Libraries\IsianBantu;

$bulan       = IsianBantu::BULAN;
$maksAnggota = max(1, (int) ($maksSiswa ?? 5));
$batasHari   = max(1, (int) ($batasHari ?? 5));

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
    'batasHari'      => $batasHari,
];

$langkah = ['Cari Nama', 'Perusahaan', 'Teman', 'Kontak', 'Kirim'];

// ---------------------------------------------------------------------
// Pembangun elemen form (menjaga markup tiap kolom seragam)
// ---------------------------------------------------------------------

/** Tanda wajib / opsional di label. */
$tanda = static fn (bool $wajib): string => $wajib
    ? ' <span class="tanda-wajib">*</span>'
    : ' <span class="tanda-opsional">(boleh kosong)</span>';

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
    echo '<input type="text" id="f_' . $k . '" x-model="f.' . $k . '" class="inp" :class="err.' . $err . ' && \'inp-err\'"'
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

/** Kepala kartu langkah. */
$kepala = static fn (int $no, string $judul): string => '<div class="kartu-langkah-kepala"><span class="kartu-langkah-no">' . $no . '</span><h2 class="kartu-langkah-judul">' . esc($judul) . '</h2></div>';

/** Pilihan kelas (dikelompokkan per tingkat) — dipakai langkah 1 dan pemilih teman. */
$pilihanKelas = static function () use ($kelas): void {
    echo '<option value="">Pilih kelas</option>';
    foreach ($kelas as $tingkat => $daftar) {
        echo '<optgroup label="Kelas ' . esc($tingkat) . '">';
        foreach ($daftar as $k) {
            echo '<option value="' . (int) $k['id'] . '">' . esc($k['nama']) . '</option>';
        }
        echo '</optgroup>';
    }
};

/** Tautan "Lapor diterima" (langkah 1 & halaman tutup). */
$tautanLapor = '<a href="' . site_url('pkl/lapor') . '" class="peringatan peringatan-sukses items-center justify-between transition-colors hover:bg-emerald-100">'
    . '<span><b>Sudah diterima perusahaan?</b> Ajuan PKL-mu sudah disetujui dan perusahaan menerimamu — laporkan di sini supaya sekolah membuatkan Surat Balasan.</span>'
    . '<span class="flex shrink-0 items-center gap-1 font-semibold">Lapor' . ikon('arrow-right', 'h-4 w-4') . '</span></a>';
?>
<?= $this->extend('layouts/formulir') ?>

<?= $this->section('content') ?>
<div class="formulir"
     x-data="pklForm"
     data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <?= view('pkl/_header', ['setting' => $setting]) ?>

    <div x-show="step === 0">
    <?php if (! empty($laporTerbuka)): ?>
        <div class="mt-4"><?= $tautanLapor ?></div>
    <?php endif; ?>
    </div>

    <!-- Persiapan — hanya di langkah pertama -->
    <div x-show="step === 0" class="peringatan peringatan-awas mt-4">
        <?= ikon('exclamation-circle') ?>
        <div class="leading-relaxed">
            <p class="font-semibold">Siapkan dulu sebelum mengisi:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                <li>Nama, <b>alamat lengkap</b>, dan telepon perusahaan tempat PKL</li>
                <li>Nama pimpinan atau kontak di perusahaan (bila ada)</li>
                <li><b>Nomor HP aktif</b> kamu dan semua temanmu (tercetak di surat)</li>
                <li>Nama teman yang PKL di tempat yang sama (maksimal <b><?= $maksAnggota ?></b> siswa per perusahaan, termasuk kamu)</li>
            </ul>
            <p class="mt-2 font-semibold">Satu siswa hanya boleh punya satu ajuan. Jangan mengisi atas nama orang lain.</p>
            <p class="mt-2 rounded-md bg-amber-100 px-3 py-2 font-semibold">Setelah dikirim, <b>jangan mengajukan ulang</b>. Tunggu keputusan sekolah, paling lambat <b><?= $batasHari ?> hari</b> sejak kamu mengirim.</p>
        </div>
    </div>

    <!-- Penunjuk langkah -->
    <div class="langkah-panel mt-4" id="pklTop">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-brand-800">Langkah <span x-text="step + 1">1</span> dari <?= count($langkah) ?></p>
            <p class="text-sm text-teks-2" x-text="judulLangkah[step]"><?= esc($langkah[0]) ?></p>
        </div>
        <div class="langkah-bilah grid-cols-5" aria-hidden="true">
            <?php foreach ($langkah as $i => $lbl): ?>
                <span :class="{ 'is-done': step >= <?= $i ?> }"<?= $i === 0 ? ' class="is-done"' : '' ?>></span>
            <?php endforeach; ?>
        </div>
        <div class="langkah-daftar">
            <?php foreach ($langkah as $i => $lbl): ?>
                <button type="button" class="langkah-titik" :class="{ 'is-active': step === <?= $i ?>, 'is-done': step > <?= $i ?> }" @click="step > <?= $i ?> && <?= $i ?> > 0 && keLangkah(<?= $i ?>)">
                    <span class="langkah-titik-no"><?= $i + 1 ?></span><span class="langkah-titik-label"><?= esc($lbl) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Catatan staf saat perbaikan -->
    <div x-cloak x-show="modeRevisi && step > 0" class="peringatan peringatan-awas mt-4">
        <?= ikon('info-circle') ?>
        <div>
            <p class="font-semibold">Kamu sedang memperbaiki ajuan PKL.</p>
            <p class="mt-0.5" x-show="catatan"><span class="font-semibold">Catatan dari sekolah:</span> <span x-text="catatan"></span></p>
        </div>
    </div>

    <div class="mt-4">
        <!-- ============ LANGKAH 1 — CARI NAMA ============ -->
        <section class="kartu-langkah" x-show="step === 0">
            <?= $kepala(1, 'Cari Namamu') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <label class="lbl" for="pilihKelas">Kelas kamu sekarang <span class="tanda-wajib">*</span></label>
                    <select id="pilihKelas" class="inp" x-model="kelasId" @change="muatSiswa()">
                        <?php $pilihanKelas() ?>
                    </select>
                </div>

                <p x-cloak x-show="memuat" class="flex items-center gap-2 text-sm text-teks-2" role="status">
                    <span class="pemutar text-brand-700" aria-hidden="true"></span>
                    Memuat daftar nama…
                </p>
                <p x-cloak x-show="pesanDaftar" x-text="pesanDaftar" class="text-sm font-semibold text-red-700"></p>

                <div x-cloak x-show="kelasId && !memuat && siswaList.length">
                    <label class="lbl" for="cariNama">Pilih namamu <span class="tanda-wajib">*</span></label>
                    <input id="cariNama" type="search" class="inp" x-model="cari" placeholder="Ketik namamu untuk mencari…" autocomplete="off">
                    <div class="daftar-pilih">
                        <template x-for="s in siswaTersaring()" :key="s.id">
                            <button type="button" @click="pilihSiswa(s)" class="pilih-item" :aria-pressed="pilih && pilih.id === s.id">
                                <span class="font-medium" x-text="s.nama"></span>
                                <span x-show="s.status !== 'belum'" class="label shrink-0" :class="lencana(s.status).kelas" x-text="lencana(s.status).teks"></span>
                            </button>
                        </template>
                        <p x-show="!siswaTersaring().length" class="px-4 py-6 text-center text-sm text-teks-2">Tidak ada nama yang cocok dengan “<span x-text="cari"></span>”.</p>
                    </div>
                </div>

                <!-- Panel setelah nama dipilih -->
                <div x-ref="panel" x-cloak x-show="pilih">
                    <!-- Belum punya ajuan aktif (termasuk yang pernah ditolak) -->
                    <template x-if="pilih && (pilih.status === 'belum' || pilih.status === 'ditolak')">
                        <div class="rounded-lg border-2 border-brand-200 bg-brand-50 p-4">
                            <p class="text-sm text-teks-2">Kamu memilih:</p>
                            <p class="mt-0.5 text-heading-md text-brand-900" x-text="pilih.nama"></p>
                            <p class="text-sm font-medium text-teks-2" x-text="'Kelas ' + namaKelas()"></p>
                            <p x-show="pilih.status === 'ditolak'" class="peringatan peringatan-galat mt-3">
                                <span>Ajuan PKL-mu sebelumnya <b>ditolak</b>, jadi kamu boleh mengajukan lagi. Tanyakan alasannya ke operator sekolah atau Waka Hubin agar tidak terulang.</span>
                            </p>
                            <p class="mt-3 text-sm text-teks-2">Benar ini kamu? Pastikan tidak salah pilih nama teman.</p>
                            <button type="button" @click="mulai()" class="tombol tombol-utama tombol-besar tombol-blok mt-3">Ya, ini saya — Mulai isi<?= ikon('arrow-right') ?></button>
                        </div>
                    </template>

                    <!-- Sudah punya ajuan: menunggu / disetujui -->
                    <template x-if="pilih && (pilih.status === 'menunggu' || pilih.status === 'disetujui')">
                        <div class="rounded-lg border p-4" :class="pilih.status === 'disetujui' ? 'border-emerald-200 bg-emerald-50' : 'border-brand-200 bg-brand-50'">
                            <p class="flex items-center gap-2 font-semibold" :class="pilih.status === 'disetujui' ? 'text-emerald-900' : 'text-brand-900'">
                                <?= ikon('check-circle') ?><span x-text="pilih.status === 'disetujui' ? 'Ajuan PKL sudah disetujui sekolah' : 'Ajuan PKL sudah dikirim'"></span>
                            </p>
                            <p class="mt-1 text-sm text-teks">
                                <span x-text="pilih.nama"></span> tidak perlu mengisi lagi<span x-show="pilih.status === 'menunggu'"> — ajuannya sedang menunggu keputusan sekolah</span>.
                                <b>Jangan mengajukan ulang.</b>
                            </p>
                            <p x-show="pilih.peran === 'teman'" class="mt-2 text-sm text-teks">
                                Namamu <b>didaftarkan oleh temanmu</b> yang PKL di tempat yang sama. Tidak merasa ikut? Hubungi operator sekolah atau Waka Hubin agar namamu dikeluarkan.
                            </p>
                            <p x-show="pilih.status === 'menunggu' && pilih.batas" class="mt-2 rounded-md border border-brand-200 bg-white px-3 py-2 text-sm font-semibold text-brand-900">
                                Keputusan sekolah paling lambat <span x-text="tglIndo(pilih.batas)"></span>. Lewat tanggal itu belum ada kabar? Hubungi operator sekolah atau Waka Hubin.
                            </p>
                            <?php if (! empty($laporTerbuka)): ?>
                                <p x-show="pilih.status === 'disetujui'" class="mt-2 rounded-md border border-emerald-200 bg-white px-3 py-2 text-sm text-emerald-900">Sudah diterima perusahaan? <a href="<?= site_url('pkl/lapor') ?>" class="font-semibold underline">Lapor diterima di sini</a> supaya sekolah membuatkan Surat Balasan.</p>
                            <?php endif; ?>
                            <p class="mt-2 text-sm text-teks-2">Jika ada data yang salah, hubungi operator sekolah atau Waka Hubin.</p>
                        </div>
                    </template>

                    <!-- Dikembalikan sekolah: hanya PENGAJU yang bisa membuka -->
                    <template x-if="pilih && pilih.status === 'perbaikan' && pilih.peran === 'pengaju'">
                        <div class="space-y-3 rounded-lg border-2 border-amber-300 bg-amber-50 p-4">
                            <div>
                                <p class="font-semibold text-amber-900">Sekolah meminta kamu memperbaiki ajuan PKL.</p>
                                <p class="mt-1 text-sm text-amber-900">Untuk membuka ajuanmu, masukkan <b>nomor HP</b> yang dulu kamu isi di formulir ini.</p>
                            </div>
                            <div>
                                <label class="lbl" for="bukaHp">Nomor HP / WhatsApp</label>
                                <input id="bukaHp" type="tel" inputmode="tel" x-model="buka.hp" maxlength="20" autocomplete="tel" class="inp" placeholder="Contoh: 081234567890">
                            </div>
                            <p x-show="buka.pesan" x-text="buka.pesan" class="text-sm font-semibold text-red-700"></p>
                            <button type="button" @click="bukaAjuan()" :disabled="buka.proses" class="tombol tombol-utama tombol-besar tombol-blok">
                                <span x-text="buka.proses ? 'Memeriksa…' : 'Buka Ajuan Saya'"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Dikembalikan sekolah, tapi dia hanya TEMAN di ajuan itu -->
                    <template x-if="pilih && pilih.status === 'perbaikan' && pilih.peran !== 'pengaju'">
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">Ajuan PKL-mu sedang diminta perbaikan</p>
                            <p class="mt-1 text-sm text-amber-900">
                                Namamu tercantum di ajuan yang diajukan <b>temanmu</b>. Hanya yang mengajukan yang bisa memperbaikinya —
                                minta dia membukanya, atau hubungi operator sekolah.
                            </p>
                        </div>
                    </template>
                </div>

                <p class="text-sm leading-relaxed text-teks-2">
                    Namamu tidak ada di daftar, atau salah kelas? Jangan memilih nama orang lain —
                    hubungi wali kelas atau operator sekolah agar datamu diperbaiki dulu.
                </p>
            </div>
        </section>

        <!-- ============ LANGKAH 2 — PERUSAHAAN ============ -->
        <section class="kartu-langkah" x-cloak x-show="step === 1">
            <?= $kepala(2, 'Tempat PKL') ?>
            <div class="kartu-langkah-isi">
                <div>
                    <label class="lbl" for="f_perusahaan_nama">Nama Perusahaan<?= $tanda(true) ?></label>
                    <input type="text" id="f_perusahaan_nama" x-model="f.perusahaan_nama" maxlength="150" autocomplete="off"
                           @input="cariSaran()" @focus="saran.hasil.length && (saran.buka = true)" @blur="tutupSaran()" @keydown.escape="saran.buka = false"
                           class="inp" :class="err.perusahaan_nama && 'inp-err'" placeholder="Contoh: PT Telkom Indonesia">
                    <p class="hint">Tulis nama <b>lengkap</b>, jangan disingkat. Ketik beberapa huruf — bila perusahaan ini pernah diajukan siswa lain, pilihannya muncul dan datanya terisi sendiri.</p>
                    <?= $galat('perusahaan_nama') ?>

                    <div x-cloak x-show="saran.buka && saran.hasil.length" class="mt-2 overflow-hidden rounded-lg border border-brand-200 bg-white">
                        <p class="bg-brand-50 px-4 py-2 text-xs font-semibold tracking-wide text-brand-800 uppercase">Pernah diajukan &amp; disetujui — ketuk untuk mengisi otomatis</p>
                        <div class="divide-y divide-garis">
                            <template x-for="s in saran.hasil" :key="s.id">
                                <button type="button" @mousedown.prevent @click="pakaiSaran(s)" class="pilih-item flex-col items-start gap-0.5">
                                    <span class="block font-semibold" x-text="s.nama"></span>
                                    <span class="pilih-ket" x-text="lokasiSaran(s)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p x-cloak x-show="saran.terpakai" class="peringatan peringatan-sukses mt-2">
                        <?= ikon('check-circle') ?><span>Data diisi dari perusahaan yang sudah terdaftar. Periksa lagi dan ubah bila ada yang berbeda.</span>
                    </p>
                </div>

                <div>
                    <label class="lbl" for="f_perusahaan_alamat">Alamat Perusahaan<?= $tanda(true) ?></label>
                    <textarea id="f_perusahaan_alamat" x-model="f.perusahaan_alamat" rows="3" maxlength="255" autocomplete="off"
                              class="inp" :class="err.perusahaan_alamat && 'inp-err'" placeholder="Contoh: Jl. Raya Industri No. 12, Kawasan MM2100, Cikarang Barat"></textarea>
                    <p class="hint">Nama jalan, nomor, dan daerahnya — supaya surat dari sekolah sampai ke tujuan.</p>
                    <?= $galat('perusahaan_alamat') ?>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <?php $input('perusahaan_kota', 'Kota / Kabupaten', ['maks' => 100, 'ph' => 'Contoh: Bekasi']) ?>
                    <?php $input('perusahaan_telepon', 'Telepon Perusahaan', ['wajib' => false, 'mode' => 'tel', 'maks' => 30, 'ph' => 'Contoh: 02188776655', 'hint' => 'Kosongkan bila kamu tidak tahu.']) ?>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <?php $input('kontak_nama', 'Nama Pimpinan / Kontak', ['wajib' => false, 'maks' => 150, 'auto' => 'off', 'ph' => 'Contoh: Bapak Andi Wijaya', 'hint' => 'Orang yang dituju di perusahaan, bila kamu tahu.']) ?>
                    <?php $input('kontak_jabatan', 'Jabatan', ['wajib' => false, 'maks' => 100, 'ph' => 'Contoh: Manajer HRD']) ?>
                </div>
            </div>
        </section>

        <!-- ============ LANGKAH 3 — TEMAN ============ -->
        <section class="kartu-langkah" x-cloak x-show="step === 2">
            <?= $kepala(3, 'Teman Satu Tempat') ?>
            <div class="kartu-langkah-isi">
                <div class="rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm leading-relaxed text-teks">
                    <?php if ($maksAnggota > 1): ?>
                        <p>Ada temanmu yang PKL di <b>perusahaan yang sama</b>? Tambahkan di sini — cukup <b>satu orang</b> yang mengisi, temanmu <b>tidak perlu mengisi lagi</b>. Isi juga <b>nomor HP</b> tiap teman. Kalau PKL sendirian, langsung tekan <b>Lanjut</b>.</p>
                        <p class="mt-1.5 text-teks-2">Maksimal <b><?= $maksAnggota ?></b> siswa per perusahaan (termasuk kamu). Hanya siswa yang belum mengajukan PKL yang bisa dipilih.</p>
                    <?php else: ?>
                        <p>Perusahaan hanya menerima <b>satu siswa</b> per ajuan, jadi langkah ini bisa dilewati. Tekan <b>Lanjut</b>.</p>
                    <?php endif; ?>
                </div>

                <div x-show="<?= $maksAnggota > 1 ? 'true' : 'false' ?>">
                    <p class="lbl">Teman yang dipilih (<span x-text="teman.length"></span> dari <span x-text="maksTeman()"></span>)</p>
                    <p x-show="!teman.length" class="rounded-lg border-2 border-dashed border-garis-2 px-4 py-5 text-center text-sm text-teks-2">Belum ada teman yang dipilih.</p>
                    <ul class="space-y-2">
                        <template x-for="t in teman" :key="t.id">
                            <li class="rounded-lg border border-garis bg-white px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-teks" x-text="t.nama"></span>
                                        <span class="pilih-ket" x-text="t.kelas ? 'Kelas ' + t.kelas : ''"></span>
                                    </span>
                                    <button type="button" @click="hapusTeman(t.id)" class="tombol tombol-kecil shrink-0 text-red-700 hover:bg-red-50" :aria-label="'Hapus ' + t.nama">Hapus</button>
                                </div>
                                <div class="mt-2">
                                    <label class="lbl" :for="'hp_teman_' + t.id">No. HP <span x-text="t.nama.split(' ')[0]"></span> <span class="tanda-wajib">*</span></label>
                                    <input type="tel" inputmode="tel" maxlength="20" autocomplete="off" :id="'hp_teman_' + t.id" x-model="t.hp"
                                           class="inp" :class="err['hp_teman_' + t.id] && 'inp-err'" placeholder="Contoh: 081234567890">
                                    <p class="err-msg" x-cloak x-show="err['hp_teman_' + t.id]" x-text="err['hp_teman_' + t.id]"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <?= $galat('teman') ?>
                </div>

                <?php if ($maksAnggota > 1): ?>
                    <div>
                        <button type="button" x-show="bisaTambahTeman()" @click="bukaPemilih()" class="tombol tombol-garis tombol-besar tombol-blok">
                            <span x-text="pick.buka ? 'Tutup daftar' : '+ Tambah teman'"></span>
                        </button>
                        <p x-cloak x-show="!bisaTambahTeman()" class="text-center text-sm font-semibold text-teks-2">Batas jumlah siswa per perusahaan sudah tercapai.</p>
                    </div>

                    <div x-cloak x-show="pick.buka && bisaTambahTeman()" class="space-y-4 rounded-lg border-2 border-brand-200 bg-brand-50 p-4">
                        <div>
                            <label class="lbl" for="pickKelas">Kelas temanmu</label>
                            <select id="pickKelas" class="inp" x-model="pick.kelasId" @change="muatPemilih()">
                                <?php $pilihanKelas() ?>
                            </select>
                        </div>
                        <p x-cloak x-show="pick.memuat" class="flex items-center gap-2 text-sm text-teks-2" role="status"><span class="pemutar text-brand-700" aria-hidden="true"></span>Memuat daftar nama…</p>
                        <p x-cloak x-show="pick.pesan" x-text="pick.pesan" class="text-sm font-semibold text-red-700"></p>
                        <div x-cloak x-show="pick.kelasId && !pick.memuat && pick.list.length">
                            <input type="search" class="inp" x-model="pick.cari" placeholder="Ketik nama temanmu…" autocomplete="off" aria-label="Cari nama teman">
                            <div class="daftar-pilih max-h-72">
                                <template x-for="s in pemilihTersaring()" :key="s.id">
                                    <button type="button" @click="tambahTeman(s)" :disabled="alasanTak(s) !== ''" class="pilih-item">
                                        <span class="font-medium" x-text="s.nama"></span>
                                        <span x-show="alasanTak(s) !== ''" class="label label-tutup shrink-0" x-text="alasanTak(s)"></span>
                                        <span x-show="alasanTak(s) === ''" class="shrink-0 text-sm font-semibold text-brand-700">+ Pilih</span>
                                    </button>
                                </template>
                                <p x-show="!pemilihTersaring().length" class="px-4 py-6 text-center text-sm text-teks-2">Tidak ada nama yang cocok.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============ LANGKAH 4 — KONTAK ============ -->
        <section class="kartu-langkah" x-cloak x-show="step === 3">
            <?= $kepala(4, 'Kontak Kamu') ?>
            <div class="kartu-langkah-isi">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900">
                    Waktu PKL <b>tidak perlu diisi</b> — sekolah yang menyepakatinya dengan perusahaan.
                    Pastikan nomor HP di bawah <b>aktif</b>, karena tercetak di surat dan dipakai bila perusahaan atau sekolah menghubungimu.
                </div>

                <?php $input('hp', 'No. HP / WhatsApp kamu', ['mode' => 'tel', 'maks' => 20, 'auto' => 'tel', 'ph' => 'Contoh: 081234567890', 'hint' => 'Nomor <b>yang aktif sekarang</b>. Nomor ini juga dipakai untuk membuka ajuanmu bila sekolah memintamu memperbaikinya.']) ?>
            </div>
        </section>
        <!-- ============ LANGKAH 5 — PERIKSA & KIRIM ============ -->
        <section class="kartu-langkah" x-cloak x-show="step === 4">
            <?= $kepala(5, 'Periksa & Kirim') ?>
            <div class="kartu-langkah-isi">
                <p class="text-sm leading-relaxed text-teks-2">Periksa sekali lagi. Setelah dikirim, ajuan <b>terkunci</b> dan tidak bisa kamu ubah sendiri. Keputusan Waka Hubin keluar paling lambat <b><?= $batasHari ?> hari</b> setelah kamu mengirim.</p>

                <!-- Siswa -->
                <div class="overflow-hidden rounded-lg border border-garis">
                    <div class="flex items-center justify-between gap-3 bg-permukaan px-4 py-2.5">
                        <p class="sub-judul">Siswa yang PKL (<span x-text="1 + teman.length"></span>)</p>
                        <button type="button" @click="keLangkah(2)" class="tombol tombol-teks text-sm">Ubah teman</button>
                    </div>
                    <ul class="divide-y divide-garis text-sm">
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0"><span class="block truncate font-semibold text-teks" x-text="pilih ? pilih.nama : ''"></span><span class="pilih-ket" x-text="'Kelas ' + namaKelas() + ' · HP ' + tampil('hp')"></span></span>
                            <span class="label label-merek shrink-0">Pengaju</span>
                        </li>
                        <template x-for="t in teman" :key="t.id">
                            <li class="px-4 py-2.5"><span class="block font-semibold text-teks" x-text="t.nama"></span><span class="pilih-ket" x-text="infoTeman(t)"></span></li>
                        </template>
                    </ul>
                </div>

                <!-- Perusahaan -->
                <div class="overflow-hidden rounded-lg border border-garis">
                    <div class="flex items-center justify-between gap-3 bg-permukaan px-4 py-2.5">
                        <p class="sub-judul">Tempat PKL</p>
                        <button type="button" @click="keLangkah(1)" class="tombol tombol-teks text-sm">Ubah</button>
                    </div>
                    <dl class="divide-y divide-garis">
                        <?php foreach (['perusahaan_nama' => 'Perusahaan', 'perusahaan_alamat' => 'Alamat', 'perusahaan_kota' => 'Kota/Kab.', 'perusahaan_telepon' => 'Telepon', 'kontak_nama' => 'Pimpinan/Kontak', 'kontak_jabatan' => 'Jabatan'] as $k => $lbl): ?>
                            <div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5 text-sm sm:grid-cols-[9rem_1fr]">
                                <dt class="text-teks-2"><?= esc($lbl) ?></dt>
                                <dd class="font-semibold break-words text-teks" x-text="tampil('<?= $k ?>')"></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>

                <label class="pilih-cek" :class="err.pernyataan && 'is-galat'">
                    <input type="checkbox" x-model="f.pernyataan" class="sr-only">
                    <span class="pilih-cek-kotak"></span>
                    <span class="text-sm text-teks">Saya menyatakan data di atas <b>sudah benar</b>, siap memperbaikinya bila diminta sekolah, dan <b>tidak akan mengajukan ulang</b> — saya menunggu keputusan sekolah (paling lambat <?= $batasHari ?> hari).</span>
                </label>
                <?= $galat('pernyataan') ?>
            </div>
        </section>

        <!-- Pesan kirim (galat umum dari server / koneksi) -->
        <div x-cloak x-show="pesanKirim" class="peringatan peringatan-galat mt-4 font-semibold" role="alert"><?= ikon('exclamation-circle') ?><span x-text="pesanKirim"></span></div>

        <!-- Jebakan bot: tak terlihat manusia -->
        <div class="hidden" aria-hidden="true"><input type="text" x-ref="hp" name="website" tabindex="-1" autocomplete="off"></div>

        <!-- Navigasi -->
        <div x-cloak x-show="step > 0" class="formulir-nav">
            <button type="button" @click="kembali()" class="tombol tombol-netral tombol-besar"><?= ikon('angle-left') ?>Kembali</button>
            <button type="button" x-show="step < 4" @click="lanjut()" class="tombol tombol-utama tombol-besar">Lanjut<?= ikon('angle-right') ?></button>
            <button type="button" x-show="step === 4" @click="kirim()" :disabled="mengirim" class="tombol tombol-utama tombol-besar disabled:cursor-wait">
                <span x-show="mengirim" class="pemutar" aria-hidden="true"></span>
                <span x-text="mengirim ? 'Mengirim…' : (modeRevisi ? 'Kirim Perbaikan' : 'Kirim Ajuan')"></span>
            </button>
        </div>
        <p x-cloak x-show="step > 0 && draftInfo" class="mt-3 text-center text-sm text-teks-2" x-text="draftInfo"></p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/pkl.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
