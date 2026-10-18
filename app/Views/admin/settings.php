<?php
/**
 * Pengaturan Sekolah & Tampilan Publik. Rombak tampilan F10: kartu kit per bagian, saklar kit (.saklar) untuk buka/tutup
 * tampilan publik, unggah logo = kit pilihBerkas, tombol kit ber-ikon; blok gaya sebaris (.lbl/.inp lama) dibuang. Kartu
 * "Tampilan panel admin" (pemilih tema, F3/R2) tidak berubah.
 *
 * @var array $setting baris settings
 * @var bool  $bolehTema / array $tema / bool $temaSiap
 */
$kepala = static fn (string $judul, string $ket = ''): string => '<div class="kartu-kepala"><div><h2 class="kartu-judul">' . esc($judul) . '</h2>'
    . ($ket !== '' ? '<p class="kartu-ket">' . $ket . '</p>' : '') . '</div></div>';
// Saklar kit dengan judul + keterangan.
$saklar = static fn (string $nama, bool $nyala, string $judul, string $ket): string => '<label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">'
    . '<span><span class="block text-sm font-semibold text-gray-900 dark:text-white">' . esc($judul) . '</span><span class="block text-xs text-gray-500 dark:text-gray-400">' . $ket . '</span></span>'
    . '<span class="saklar shrink-0"><input type="checkbox" name="' . $nama . '" value="1" class="peer sr-only"' . ($nyala ? ' checked' : '') . '><span class="saklar-jalur"></span></span></label>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'settings',
    'helpTitle' => 'Pengaturan Sekolah & Tampilan Publik',
    'helpBody'  => '<p>Identitas sekolah di sini dipakai pada kop surat, PDF, dan halaman publik. Di bagian bawah ada saklar tampilan publik: <b>Buka pengisian form</b>, <b>Tampilkan jadwal ke publik</b>, dan <b>Tampilkan absensi ke publik</b>. Matikan salah satu untuk menyembunyikan halaman terkait dari pengunjung situs.</p><p class="mt-2">Kartu <b>Situs publik</b> berisi profil singkat sekolah, nomor WhatsApp bantuan, dan tautan unduh aplikasi Android yang tampil di situs publik.</p>',
]) ?>

<form method="post" action="<?= site_url('admin/settings') ?>" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    <?= csrf_field() ?>

    <section class="kartu">
        <?= $kepala('Identitas sekolah', 'Data ini dipakai pada kop surat, PDF, dan halaman form guru.') ?>
        <div class="kartu-isi space-y-5">
            <!-- Logo -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-600 dark:bg-gray-700">
                    <?php // Gambar hanya bila berkasnya ADA (pola partials/favicon.php) — tanpa gambar rusak & galat 404 di konsol.
                    $adaLogo = ! empty($setting['logo']) && is_file(FCPATH . 'uploads/' . basename((string) $setting['logo'])); ?>
                    <?php if ($adaLogo): ?>
                        <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" class="h-full w-full object-contain" alt="Logo sekolah">
                    <?php else: ?>
                        <?= ikon('image', 'h-8 w-8 text-gray-500 dark:text-gray-400') ?>
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="lbl">Logo sekolah</p>
                    <?php if (! empty($setting['logo']) && ! $adaLogo): ?>
                        <p class="hint mb-2 text-yellow-700 dark:text-yellow-400">Berkas logo tersimpan tidak ditemukan di server — silakan unggah ulang.</p>
                    <?php endif; ?>
                    <label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih"
                           class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-3 transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">
                        <?= ikon('upload', 'h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400') ?>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">Pilih logo (PNG/JPG, disarankan persegi)</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>
                        </span>
                        <input type="file" name="logo" accept="image/*" class="sr-only" @change="catatBerkas($event)">
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lbl" for="st-jenjang">Jenjang</label>
                    <input type="text" id="st-jenjang" name="school_level" value="<?= esc($setting['school_level'], 'attr') ?>" class="inp" placeholder="SMK / SMA / SMP">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="st-nama">Nama sekolah <span class="text-red-600 dark:text-red-400">*</span></label>
                    <input type="text" id="st-nama" name="school_name" value="<?= esc($setting['school_name'], 'attr') ?>" required class="inp" placeholder="Nama sekolah">
                </div>
            </div>

            <div>
                <label class="lbl" for="st-alamat">Alamat</label>
                <textarea id="st-alamat" name="address" rows="2" class="inp" placeholder="Alamat lengkap sekolah"><?= esc($setting['address']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lbl" for="st-kota">Kota</label>
                    <input type="text" id="st-kota" name="city" value="<?= esc($setting['city'], 'attr') ?>" class="inp" placeholder="Bekasi">
                </div>
                <div>
                    <label class="lbl" for="st-telp">Telepon</label>
                    <input type="text" id="st-telp" name="phone" value="<?= esc($setting['phone'], 'attr') ?>" class="inp">
                </div>
                <div>
                    <label class="lbl" for="st-email">Email</label>
                    <input type="text" id="st-email" name="email" value="<?= esc($setting['email'], 'attr') ?>" class="inp">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="st-web">Website</label>
                    <input type="text" id="st-web" name="website" value="<?= esc($setting['website'], 'attr') ?>" class="inp" placeholder="zulfiqri.it.com">
                </div>
                <div>
                    <label class="lbl" for="st-ta">Tahun ajaran</label>
                    <input type="text" id="st-ta" name="academic_year" value="<?= esc($setting['academic_year'], 'attr') ?>" class="inp" placeholder="2026/2027">
                </div>
            </div>
        </div>
    </section>

    <section class="kartu">
        <?= $kepala('Kepala sekolah & form') ?>
        <div class="kartu-isi space-y-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="st-kepsek">Nama kepala sekolah</label>
                    <input type="text" id="st-kepsek" name="headmaster_name" value="<?= esc($setting['headmaster_name'], 'attr') ?>" class="inp" placeholder="Nama & gelar">
                </div>
                <div>
                    <label class="lbl" for="st-kepsek-nip">NIP kepala sekolah</label>
                    <input type="text" id="st-kepsek-nip" name="headmaster_nip" value="<?= esc($setting['headmaster_nip'], 'attr') ?>" class="inp" placeholder="Kosongkan bila tidak ada">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="st-pengawas">Pengawas pembina</label>
                    <input type="text" id="st-pengawas" name="supervisor_name" value="<?= esc($setting['supervisor_name'] ?? '', 'attr') ?>" class="inp" placeholder="Nama & gelar">
                    <p class="hint">Tanda tangan "Mengetahui," pada Daftar Nama Siswa (Format 8355).</p>
                </div>
                <div>
                    <label class="lbl" for="st-pengawas-nip">NIP pengawas pembina</label>
                    <input type="text" id="st-pengawas-nip" name="supervisor_nip" value="<?= esc($setting['supervisor_nip'] ?? '', 'attr') ?>" class="inp">
                </div>
                <div>
                    <label class="lbl" for="st-kodepos">Kode pos sekolah</label>
                    <input type="text" id="st-kodepos" name="school_postal_code" value="<?= esc($setting['school_postal_code'] ?? '', 'attr') ?>" class="inp" maxlength="10">
                </div>
                <div>
                    <label class="lbl" for="st-akreditasi">Status akreditasi</label>
                    <input type="text" id="st-akreditasi" name="school_accreditation" value="<?= esc($setting['school_accreditation'] ?? '', 'attr') ?>" class="inp" maxlength="20" placeholder="Contoh: B">
                </div>
            </div>
            <div>
                <label class="lbl" for="st-intro">Teks pengantar form</label>
                <textarea id="st-intro" name="form_intro" rows="2" class="inp" placeholder="Kalimat pengantar di atas form guru"><?= esc($setting['form_intro']) ?></textarea>
            </div>
            <div class="space-y-3">
                <?= $saklar('form_open', (bool) $setting['form_open'], 'Buka pengisian form', 'Jika dimatikan, guru tidak bisa mengisi form (ditampilkan halaman "ditutup").') ?>
                <?= $saklar('jadwal_publik', (bool) ($setting['jadwal_publik'] ?? 1), 'Tampilkan jadwal ke publik', 'Jika dimatikan, halaman Jadwal Kelas/Guru di situs publik disembunyikan.') ?>
                <?= $saklar('absensi_publik', (bool) ($setting['absensi_publik'] ?? 1), 'Tampilkan absensi ke publik', 'Jika dimatikan, halaman Absensi Guru di situs publik disembunyikan (pengelolaan di admin tetap jalan).') ?>
                <?= $saklar('dokumen_publik', (bool) ($setting['dokumen_publik'] ?? 0), 'Tampilkan halaman Dokumen publik', 'Menampilkan menu "Dokumen" di situs publik berisi berkas yang ditandai <b>Publik</b>. Berkas privat & yang dibagikan lewat tautan tidak ikut tampil.') ?>
            </div>
        </div>
    </section>

    <!-- ===== Situs publik (rombak publik P2b) ===== -->
    <section class="kartu">
        <?= $kepala('Situs publik', 'Isian untuk beranda, halaman Profil, dan kaki halaman situs sekolah. Yang dikosongkan tidak tampil. Foto sekolah diatur di menu <a href="' . site_url('admin/foto-sekolah') . '" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Foto Sekolah</a>.') ?>
        <div class="kartu-isi space-y-5">
            <div>
                <label class="lbl" for="st-profil">Profil singkat sekolah</label>
                <textarea id="st-profil" name="profil_singkat" rows="4" maxlength="1000" class="inp" placeholder="Dua atau tiga kalimat tentang sekolah: berdiri sejak, jurusan, keunggulan."><?= esc(old('profil_singkat', (string) ($setting['profil_singkat'] ?? ''))) ?></textarea>
                <p class="hint">Tampil di bagian "Tentang sekolah" beranda, di bawah judul halaman Profil, dan sebagai keterangan situs di mesin pencari. Kosong = kalimat otomatis dari data sekolah.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="st-wa">Nomor WhatsApp bantuan</label>
                    <input type="tel" id="st-wa" name="wa_bantuan" value="<?= esc(old('wa_bantuan', (string) ($setting['wa_bantuan'] ?? '')), 'attr') ?>" maxlength="20" class="inp" placeholder="081234567890">
                    <p class="hint">Tombol WhatsApp di kotak "Hubungi sekolah" dan kaki halaman.</p>
                </div>
                <div>
                    <label class="lbl" for="st-aplikasi">Tautan unduh aplikasi Android</label>
                    <input type="url" id="st-aplikasi" name="tautan_aplikasi" value="<?= esc(old('tautan_aplikasi', (string) ($setting['tautan_aplikasi'] ?? '')), 'attr') ?>" maxlength="255" class="inp" placeholder="https://play.google.com/store/apps/details?id=…">
                    <p class="hint">Tampil sebagai "Unduh aplikasi" di kaki halaman situs publik.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Manajemen Dokumen ===== -->
    <section class="kartu">
        <?= $kepala('Manajemen dokumen') ?>
        <div class="kartu-isi space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="st-maks">Batas ukuran per berkas (MB)</label>
                    <input type="number" id="st-maks" name="dok_maks_mb" min="1" max="200" value="<?= esc((string) ($setting['dok_maks_mb'] ?? 25), 'attr') ?>" class="inp">
                    <p class="hint">Server juga punya batasnya sendiri. Bila unggahan gagal padahal masih di bawah angka ini, berarti batas server (<code>upload_max_filesize</code>) lebih kecil — naikkan lewat cPanel.</p>
                </div>
                <div>
                    <label class="lbl" for="st-kuota">Pagu total penyimpanan (MB)</label>
                    <input type="number" id="st-kuota" name="dok_kuota_mb" min="100" value="<?= esc((string) ($setting['dok_kuota_mb'] ?? 2048), 'attr') ?>" class="inp">
                    <p class="hint">Dipakai untuk bar "penyimpanan terpakai" dan peringatan saat hampir penuh. Sesuaikan dengan kuota disk hosting.</p>
                </div>
            </div>
            <?= $saklar('dok_izinkan_video', (bool) ($setting['dok_izinkan_video'] ?? 0), 'Izinkan unggah berkas video', 'Sengaja <b>dimatikan</b>: satu video kegiatan bisa ratusan MB dan cepat menghabiskan disk hosting. Selama mati, video ditempel sebagai <b>tautan</b> YouTube/Google Drive. Nyalakan hanya bila kuota disk memang lega.') ?>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pengaturan</button>
    </div>
</form>

<?php if ($bolehTema): // ===== Tampilan panel admin (rombak F3/R2) — khusus Admin; formulir sendiri ===== ?>
    <section id="tampilan" class="kartu mt-6 max-w-3xl scroll-mt-24" x-data="pilihTema" data-mode-awal="<?= esc($tema['mode'], 'attr') ?>">
        <div class="kartu-kepala">
            <div>
                <h2 class="kartu-judul">Tampilan panel admin</h2>
                <p class="kartu-ket">Klik salah satu mode — tampilan <b>langsung berubah dan tersimpan</b> untuk <b>semua akun</b> panel admin (termasuk halaman masuk).</p>
            </div>
        </div>
        <form method="post" action="<?= site_url('admin/settings/tampilan') ?>" class="kartu-isi space-y-5" x-ref="formTema">
            <?= csrf_field() ?>
            <?php if (! $temaSiap): ?>
                <?= ui_peringatan('awas', 'Kolom tema belum ada di database, jadi pilihan belum bisa disimpan. Jalankan migrasi: php spark migrate.') ?>
            <?php endif; ?>
            <fieldset>
                <legend class="lbl">Mode tampilan</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <?php foreach (\App\Libraries\Tampilan::MODE as $kunci => $m): ?>
                        <label class="flex cursor-pointer flex-col gap-2 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50 has-[:checked]:border-brand-600 has-[:checked]:ring-1 has-[:checked]:ring-brand-600 dark:border-gray-700 dark:hover:bg-gray-700">
                            <span class="pratinjau-tema" data-pratinjau="<?= $kunci ?>" data-warna="<?= $m['warna'] ?>" data-sidebar="<?= $m['sidebar'] ?>" aria-hidden="true">
                                <span class="pratinjau-tema-bilah"></span>
                                <span class="pratinjau-tema-badan">
                                    <span class="pratinjau-tema-sb"><span></span><span class="aktif"></span><span></span><span></span></span>
                                    <span class="pratinjau-tema-isi"><span></span><span class="w-2/3"></span><span class="tombol"></span></span>
                                </span>
                            </span>
                            <span class="flex items-center">
                                <input type="radio" name="pilihan" value="<?= $kunci ?>" class="cek" x-model="pilihan"<?= $kunci === $tema['mode'] ? ' checked' : '' ?><?= $temaSiap ? '' : ' disabled' ?>>
                                <span class="cek-label"><?= esc($m['nama']) ?></span>
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?= esc($m['ket']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php // Dengan JavaScript: klik mode = langsung tersimpan (tampilan.js). Tanpa JavaScript: tombol Simpan cadangan. ?>
            <noscript><button type="submit" class="btn btn-utama"<?= $temaSiap ? '' : ' disabled' ?>><?= ikon('check', 'h-4 w-4') ?>Simpan tema</button></noscript>
        </form>
    </section>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/tampilan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/tampilan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
