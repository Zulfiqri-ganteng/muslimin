<?php
/**
 * LAYANAN SISWA — langkah kenali diri: pilih kelas → pilih nama (Alpine `layananMasuk`, assets/js/layanan.js) → isi NISN ATAU
 * tanggal lahir → POST layanan/masuk (formulir biasa; pemeriksa = server, LayananSiswa::masuk).
 *
 * @var array                                            $setting
 * @var array<string, list<array{id: int, nama: string}>> $kelas  kelas per tingkat (tingkat yang dilayani)
 * @var string                                           $tujuan kunci layanan tujuan setelah masuk ('' = halaman siswa)
 * @var string                                           $pesan  galat percobaan sebelumnya
 */

$config = [
    'urlSiswa' => site_url('layanan/siswa'),
    'lama'     => ['kelas' => (int) (old('kelas_id') ?? 0), 'siswa' => (int) (old('siswa_id') ?? 0)],
];
// Datang dari ikon surat di beranda (tujuan = kunci surat): judul menyebut suratnya, setelah masuk langsung ke formulirnya.
$suratTujuan = \App\Libraries\LayananSiswa::SURAT[$tujuan][0] ?? null;
$judulMasuk  = $suratTujuan !== null ? 'Minta ' . $suratTujuan : 'Masuk Layanan Siswa';
$ketMasuk    = $suratTujuan !== null ? 'Langkah 1 dari 2: pilih kelas dan namamu, lalu isi NISN atau tanggal lahir. Setelah itu formulir suratnya terbuka.' : 'Pilih kelas dan namamu, lalu isi NISN atau tanggal lahir.';
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit" x-data="layananMasuk" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judulMasuk, 'keterangan' => $ketMasuk]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('layanan/masuk') ?>" class="kartu-langkah mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="tujuan" value="<?= esc($tujuan, 'attr') ?>">
        <input type="hidden" name="siswa_id" :value="idPilih()">
        <div class="kartu-langkah-kepala"><span class="kartu-langkah-no">1</span><h2 class="kartu-langkah-judul">Cari namamu</h2></div>
        <div class="kartu-langkah-isi">
            <?php if ($kelas === []): ?>
                <p class="rounded-lg border border-garis bg-permukaan px-4 py-4 text-sm text-teks-2">Belum ada kelas yang bisa memakai layanan ini. Hubungi operator sekolah.</p>
            <?php else: ?>
                <div>
                    <label class="lbl" for="pilihKelas">Kelas kamu sekarang <span class="tanda-wajib">*</span></label>
                    <select id="pilihKelas" name="kelas_id" class="inp" x-model="kelasId" @change="muatSiswa()">
                        <option value="">Pilih kelas</option>
                        <?php foreach ($kelas as $tingkat => $daftar): ?>
                            <optgroup label="Kelas <?= esc($tingkat) ?>">
                                <?php foreach ($daftar as $k): ?><option value="<?= (int) $k['id'] ?>"><?= esc($k['nama']) ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <p x-cloak x-show="memuat" class="flex items-center gap-2 text-sm text-teks-2" role="status"><span class="pemutar text-brand-700" aria-hidden="true"></span>Memuat daftar nama…</p>
                <p x-cloak x-show="pesanDaftar" x-text="pesanDaftar" class="text-sm font-semibold text-red-700"></p>

                <div x-cloak x-show="adaDaftar()">
                    <template x-if="!pilih">
                        <div>
                            <label class="lbl" for="cariNama">Pilih namamu <span class="tanda-wajib">*</span></label>
                            <input id="cariNama" type="search" class="inp" x-model="cari" placeholder="Ketik namamu untuk mencari…" autocomplete="off">
                            <div class="daftar-pilih">
                                <template x-for="s in siswaTersaring()" :key="s.id">
                                    <button type="button" @click="pilihSiswa(s)" class="pilih-item" :aria-pressed="terpilih(s)">
                                        <span class="font-medium" x-text="s.nama"></span>
                                    </button>
                                </template>
                                <p x-show="kosongCari()" class="px-4 py-6 text-center text-sm text-teks-2">Tidak ada nama yang cocok.</p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Setelah nama dipilih -->
                <div x-cloak x-show="pilih" data-panel-masuk class="space-y-3 rounded-lg border-2 border-brand-200 bg-brand-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-teks-2">Kamu memilih:</p>
                            <p class="mt-0.5 text-heading-md text-brand-900" x-text="namaPilih()"></p>
                        </div>
                        <button type="button" class="tombol tombol-teks shrink-0" @click="gantiNama()">Bukan saya</button>
                    </div>
                    <div>
                        <label class="lbl" for="nisn">NISN</label>
                        <input id="nisn" name="nisn" type="text" inputmode="numeric" maxlength="20" autocomplete="off" class="inp" placeholder="10 angka, contoh: 0081234567">
                    </div>
                    <p class="text-center text-sm font-semibold text-teks-2">atau</p>
                    <div>
                        <label class="lbl" for="tanggalLahir">Tanggal lahir</label>
                        <input id="tanggalLahir" name="tanggal_lahir" type="date" class="inp">
                        <p class="hint">Cukup isi <b>salah satu</b>: NISN atau tanggal lahir, sesuai data di sekolah.</p>
                    </div>
                    <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok">Masuk<?= ikon('angle-right') ?></button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('/') ?>" class="font-semibold text-brand-700 hover:underline">Kembali ke beranda</a></p>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/layanan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/layanan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
