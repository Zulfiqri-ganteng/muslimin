<?php
/**
 * Form LAPOR DITERIMA PKL — 2 langkah (Alpine: assets/js/pkl-lapor.js). Siswa yang ajuan PKL-nya sudah disetujui melapor bahwa
 * perusahaan sudah menerimanya + periode PKL, sebagai bahan Surat Balasan dari sekolah. Rancangan: docs/DESAIN-HAK-AKSES.md bagian 4.
 * (P6b rombak publik) Bingkai layouts/formulir.php + kop partials/kop_formulir + kit publik.css + Alpine versi CSP: alur, isian, dan
 * logika pkl-lapor.js TIDAK berubah; ekspresi berisi array diganti metode (bolehBuka, anggotaKelompok), pilihan nama aria-pressed.
 *
 * @var array                                            $setting
 * @var array<string, list<array{id:int, nama:string}>>  $kelas  kelas yang punya siswa berajuan disetujui, per tingkat
 * @var array                                            $p      baris pkl_pengaturan
 */

use App\Libraries\IsianBantu;

$kelasMap = [];
foreach ($kelas as $tingkat => $daftar) {
    foreach ($daftar as $k) {
        $kelasMap[$k['id']] = ['nama' => $k['nama'], 'tingkat' => $tingkat];
    }
}
$config = [
    'urlSiswa' => site_url('pkl/lapor/siswa'),
    'urlCek'   => site_url('pkl/lapor/cek'),
    'urlKirim' => site_url('pkl/lapor/kirim'),
    'kelas'    => $kelasMap,
    'bulan'    => IsianBantu::BULAN,
];
$kepala = static fn (int $no, string $judul): string => '<div class="kartu-langkah-kepala"><span class="kartu-langkah-no">' . $no . '</span><h2 class="kartu-langkah-judul">' . esc($judul) . '</h2></div>';
$galat  = static fn (string $k): string => '<p class="err-msg" x-cloak x-show="err.' . $k . '" x-text="err.' . $k . '"></p>';
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir" x-data="pklLapor" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <?= view('pkl/_kop_lapor', ['setting' => $setting]) ?>

    <div x-show="tahap === 1" class="peringatan peringatan-info mt-4">
        <?= ikon('info-circle') ?>
        <div class="leading-relaxed">
            <p class="font-semibold">Kapan mengisi form ini?</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                <li>Ajuan PKL-mu sudah <b>disetujui sekolah</b>, dan</li>
                <li>perusahaan / instansi sudah <b>memberi jawaban</b>: menerima semua, menerima <b>sebagian</b> siswa saja, atau menolak.</li>
            </ul>
            <p class="mt-2">Isi hasilnya dan tanggal mulai–selesai PKL yang disepakati. Sekolah lalu membuatkan <b>Surat Balasan</b> untuk siswa yang diterima. Cukup <b>satu orang per kelompok</b> yang melapor. Teman yang tidak diterima akan dikeluarkan dari kelompok oleh sekolah dan bisa mengajukan tempat PKL lain.</p>
        </div>
    </div>

    <!-- ============ LANGKAH 1 — CARI NAMA + NOMOR HP ============ -->
    <section class="kartu-langkah mt-4" x-show="tahap === 1">
        <?= $kepala(1, 'Cari Namamu') ?>
        <div class="kartu-langkah-isi">
            <?php if ($kelas === []): ?>
                <p class="rounded-lg border border-garis bg-permukaan px-4 py-4 text-sm text-teks-2">Belum ada siswa dengan ajuan PKL yang sudah disetujui. Ajukan PKL dulu lewat form pengajuan; setelah disetujui dan diterima perusahaan, baru lapor di sini.</p>
            <?php else: ?>
                <div>
                    <label class="lbl" for="pilihKelas">Kelas kamu sekarang <span class="tanda-wajib">*</span></label>
                    <select id="pilihKelas" class="inp" x-model="kelasId" @change="muatSiswa()">
                        <option value="">Pilih kelas</option>
                        <?php foreach ($kelas as $tingkat => $daftar): ?>
                            <optgroup label="Kelas <?= esc($tingkat) ?>">
                                <?php foreach ($daftar as $k): ?><option value="<?= (int) $k['id'] ?>"><?= esc($k['nama']) ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">Hanya kelas yang punya siswa dengan ajuan PKL disetujui yang muncul.</p>
                </div>

                <p x-cloak x-show="memuat" class="flex items-center gap-2 text-sm text-teks-2" role="status"><span class="pemutar text-brand-700" aria-hidden="true"></span>Memuat daftar nama…</p>
                <p x-cloak x-show="pesanDaftar" x-text="pesanDaftar" class="text-sm font-semibold text-red-700"></p>

                <div x-cloak x-show="kelasId && !memuat && siswaList.length">
                    <label class="lbl" for="cariNama">Pilih namamu <span class="tanda-wajib">*</span></label>
                    <input id="cariNama" type="search" class="inp" x-model="cari" placeholder="Ketik namamu untuk mencari…" autocomplete="off">
                    <div class="daftar-pilih">
                        <template x-for="s in siswaTersaring()" :key="s.id">
                            <button type="button" @click="pilihSiswa(s)" class="pilih-item" :aria-pressed="pilih && pilih.id === s.id">
                                <span class="font-medium" x-text="s.nama"></span>
                                <span x-show="lencana(s.status).teks" class="label shrink-0" :class="lencana(s.status).kelas" x-text="lencana(s.status).teks"></span>
                            </button>
                        </template>
                        <p x-show="!siswaTersaring().length" class="px-4 py-6 text-center text-sm text-teks-2">Tidak ada nama yang cocok.</p>
                    </div>
                </div>

                <!-- Panel setelah nama dipilih -->
                <div x-ref="panel" x-cloak x-show="pilih">
                    <template x-if="bolehBuka()">
                        <div class="space-y-3 rounded-lg border-2 border-brand-200 bg-brand-50 p-4">
                            <div>
                                <p class="text-sm text-teks-2">Kamu memilih:</p>
                                <p class="mt-0.5 text-heading-md text-brand-900" x-text="pilih.nama"></p>
                                <p class="text-sm font-medium text-teks-2" x-text="'Kelas ' + namaKelas()"></p>
                            </div>
                            <p x-show="pilih.status === 'sudah_lapor'" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Kelompokmu <b>sudah melapor</b> dan sedang menunggu diproses sekolah. Kamu boleh membukanya untuk melihat atau memperbarui tanggalnya.</p>
                            <p x-show="pilih.status === 'dikembalikan'" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">Laporan kelompokmu <b>dikembalikan</b> sekolah untuk diperbaiki. Buka untuk melihat alasannya.</p>
                            <div>
                                <label class="lbl" for="cekHp">Nomor HP / WhatsApp kamu</label>
                                <input id="cekHp" type="tel" inputmode="tel" x-model="hp" maxlength="20" autocomplete="tel" class="inp" placeholder="Contoh: 081234567890" @keydown.enter.prevent="cek()">
                                <p class="hint">Nomor yang dulu kamu isi saat mengajukan PKL (atau yang tercatat di data sekolah).</p>
                            </div>
                            <p x-show="pesanCek" x-text="pesanCek" class="text-sm font-semibold text-red-700"></p>
                            <button type="button" @click="cek()" :disabled="proses" class="tombol tombol-utama tombol-besar tombol-blok">
                                <span x-text="proses ? 'Memeriksa…' : 'Lanjut'"></span><span x-show="!proses"><?= ikon('angle-right') ?></span>
                            </button>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'selesai'">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="flex items-center gap-2 font-semibold text-emerald-900"><?= ikon('check-circle') ?>Sudah beres</p>
                            <p class="mt-1 text-sm text-teks">Laporan kelompok <span x-text="pilih.nama"></span> sudah diproses / Surat Balasan sudah dibuat sekolah. Tidak perlu lapor lagi. Bila ada data yang salah, hubungi operator sekolah atau Waka Hubin.</p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'belum_disetujui'">
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">Ajuan PKL-mu belum disetujui</p>
                            <p class="mt-1 text-sm text-amber-900">Lapor diterima baru bisa setelah ajuan PKL-mu disetujui sekolah. Tunggu keputusannya; bila ajuanmu dikembalikan untuk diperbaiki, perbaiki lewat form pengajuan PKL.</p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'ajukan_lagi'">
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">Kamu tidak punya ajuan PKL yang aktif</p>
                            <p class="mt-1 text-sm text-amber-900">Ajuan atau kelompok PKL-mu sebelumnya ditolak / kamu dikeluarkan dari kelompok (misalnya tidak diterima perusahaan). Kamu boleh mengajukan tempat PKL baru lewat <a class="font-semibold text-brand-700 underline" href="<?= esc(config('Pkl')->tautan(), 'attr') ?>">form pengajuan PKL</a>.</p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'belum_ajuan'">
                        <div class="rounded-lg border border-garis bg-permukaan p-4">
                            <p class="font-semibold text-teks">Kamu belum mengajukan PKL</p>
                            <p class="mt-1 text-sm text-teks-2">Ajukan dulu lewat <a class="font-semibold text-brand-700 underline" href="<?= esc(config('Pkl')->tautan(), 'attr') ?>">form pengajuan PKL</a>. Setelah disetujui dan diterima perusahaan, baru lapor di sini.</p>
                        </div>
                    </template>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ LANGKAH 2 — ISI LAPORAN ============ -->
    <section class="kartu-langkah mt-4" x-cloak x-show="tahap === 2">
        <?= $kepala(2, 'Lapor Diterima') ?>
        <div class="kartu-langkah-isi" x-show="data">
            <div class="rounded-lg border border-garis bg-permukaan px-4 py-3 text-sm">
                <p class="text-xs font-semibold tracking-wide text-teks-2 uppercase">Ajuan PKL <span x-text="data && data.kode"></span></p>
                <p class="mt-1 text-base font-semibold text-teks" x-text="data && data.perusahaan"></p>
                <p class="mt-0.5 text-teks-2" x-show="data && data.alamat" x-text="data && data.alamat"></p>
                <p class="mt-2 text-xs font-semibold text-teks-2">Anggota kelompok:</p>
                <ul class="mt-1 list-disc pl-5 text-teks">
                    <template x-for="(a, i) in anggotaKelompok()" :key="i"><li><span x-text="a.nama"></span> <span class="text-teks-2" x-text="a.kelas ? '(' + a.kelas + ')' : ''"></span></li></template>
                </ul>
            </div>

            <div x-show="data && data.lapor && data.lapor.status === 'dikembalikan'" class="rounded-lg border-2 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Laporan dikembalikan sekolah. Alasannya:</p>
                <p class="mt-0.5" x-text="data && data.lapor ? data.lapor.alasan : ''"></p>
                <p class="mt-1">Perbaiki isian di bawah, lalu kirim ulang.</p>
            </div>
            <div x-show="data && data.lapor && data.lapor.status === 'baru'" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Laporan sudah dikirim oleh <b x-text="data && data.lapor ? data.lapor.pelapor : ''"></b> dan sedang menunggu diproses sekolah. Kirim lagi hanya bila ada yang perlu diperbaiki.
            </div>

            <!-- Hasil dari perusahaan (revisi 2026-10-20): semua / sebagian / semua ditolak -->
            <fieldset x-show="data && data.hasil_ada">
                <legend class="lbl">Jawaban perusahaan <span class="tanda-wajib">*</span></legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="pilih-item cursor-pointer" :aria-pressed="f.hasil === 'semua'"><input type="radio" class="sr-only" name="hasil" value="semua" x-model="f.hasil"><span class="font-medium">Semua diterima</span></label>
                    <label class="pilih-item cursor-pointer" x-show="bolehSebagian()" :aria-pressed="f.hasil === 'sebagian'"><input type="radio" class="sr-only" name="hasil" value="sebagian" x-model="f.hasil"><span class="font-medium">Sebagian diterima</span></label>
                    <label class="pilih-item cursor-pointer" :aria-pressed="f.hasil === 'ditolak'"><input type="radio" class="sr-only" name="hasil" value="ditolak" x-model="f.hasil"><span class="font-medium">Semua ditolak</span></label>
                </div>
                <?= $galat('hasil') ?>
            </fieldset>

            <div x-show="f.hasil === 'sebagian'" class="rounded-lg border-2 border-brand-200 bg-brand-50 px-4 py-3">
                <p class="text-sm font-semibold text-teks">Centang siswa yang <b>DITERIMA</b> perusahaan:</p>
                <div class="mt-2 space-y-1.5">
                    <template x-for="a in anggotaKelompok()" :key="a.siswa_id">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-teks">
                            <input type="checkbox" class="cek" :value="a.siswa_id" :checked="diterimaAda(a.siswa_id)" @change="ubahDiterima(a.siswa_id)">
                            <span x-text="a.nama"></span> <span class="text-teks-2" x-text="a.kelas ? '(' + a.kelas + ')' : ''"></span>
                        </label>
                    </template>
                </div>
                <p class="hint mt-2">Teman yang <b>tidak</b> dicentang akan dikeluarkan dari kelompok oleh sekolah (setelah diperiksa), lalu boleh mengajukan tempat PKL lain. Surat Balasan hanya memuat siswa yang diterima.</p>
                <?= $galat('diterima') ?>
            </div>

            <div x-show="f.hasil === 'ditolak'" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Perusahaan <b>menolak semua</b> anggota kelompok. Setelah sekolah mengonfirmasi, kalian semua boleh mengajukan tempat PKL lain. Tulis alasan dari perusahaan di kolom catatan (bila tahu).
            </div>

            <div class="grid gap-4 sm:grid-cols-2" x-show="f.hasil !== 'ditolak'">
                <div>
                    <label class="lbl" for="tglMulai">Tanggal mulai PKL <span class="tanda-wajib">*</span></label>
                    <input id="tglMulai" type="date" x-model="f.tanggal_mulai" class="inp" :class="err.tanggal_mulai && 'inp-err'">
                    <?= $galat('tanggal_mulai') ?>
                </div>
                <div>
                    <label class="lbl" for="tglSelesai">Tanggal selesai PKL <span class="tanda-wajib">*</span></label>
                    <input id="tglSelesai" type="date" x-model="f.tanggal_selesai" class="inp" :class="err.tanggal_selesai && 'inp-err'">
                    <?= $galat('tanggal_selesai') ?>
                </div>
            </div>
            <p x-show="f.hasil !== 'ditolak' && lama()" class="-mt-2 text-sm font-semibold text-brand-800" x-text="lama()"></p>

            <div x-show="f.hasil !== 'ditolak'">
                <label class="lbl" for="alamatPt">Alamat lengkap perusahaan <span class="tanda-opsional">(perbaiki bila kurang lengkap)</span></label>
                <textarea id="alamatPt" x-model="f.alamat" rows="2" maxlength="300" class="inp" :class="err.alamat && 'inp-err'"></textarea>
                <p class="hint">Alamat ini tercetak di Surat Balasan. Sertakan nama jalan, desa/kelurahan, kecamatan, kota/kabupaten, dan kode pos bila tahu.</p>
                <?= $galat('alamat') ?>
            </div>
            <div>
                <label class="lbl" for="catatanLapor">Catatan untuk sekolah <span class="tanda-opsional">(boleh kosong)</span></label>
                <textarea id="catatanLapor" x-model="f.catatan" rows="2" maxlength="300" class="inp" placeholder="Contoh: nama pembimbing di perusahaan, jam masuk, alasan perusahaan menolak, atau hal lain yang perlu sekolah ketahui."></textarea>
                <?= $galat('catatan') ?>
            </div>

            <div class="hidden" aria-hidden="true"><input type="text" x-ref="jebakan" name="website" tabindex="-1" autocomplete="off"></div>
            <div x-cloak x-show="pesanKirim" class="peringatan peringatan-galat font-semibold" role="alert"><?= ikon('exclamation-circle') ?><span x-text="pesanKirim"></span></div>

            <div class="formulir-nav mt-0">
                <button type="button" @click="kembali()" class="tombol tombol-netral tombol-besar"><?= ikon('angle-left') ?>Kembali</button>
                <button type="button" @click="kirim()" :disabled="mengirim" class="tombol tombol-utama tombol-besar disabled:cursor-wait">
                    <span x-show="mengirim" class="pemutar" aria-hidden="true"></span>
                    <span x-text="mengirim ? 'Mengirim…' : (data && data.lapor ? 'Kirim Ulang Laporan' : 'Kirim Laporan')"></span>
                </button>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/pkl-lapor.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/pkl-lapor.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
