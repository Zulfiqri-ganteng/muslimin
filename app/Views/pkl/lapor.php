<?php
/**
 * Form LAPOR DITERIMA PKL — 2 langkah (Alpine: assets/js/pkl-lapor.js). Siswa yang ajuan PKL-nya sudah disetujui melapor bahwa
 * perusahaan sudah menerimanya + periode PKL, sebagai bahan Surat Balasan dari sekolah. Rancangan: docs/DESAIN-HAK-AKSES.md bagian 4.
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
$kepala = static fn (int $no, string $judul): string => '<div class="bio-head"><span class="bio-num">' . $no . '</span><h2 class="text-white font-bold text-lg">' . esc($judul) . '</h2></div>';
$galat  = static fn (string $k): string => '<p class="err-msg" x-cloak x-show="err.' . $k . '" x-text="err.' . $k . '"></p>';
?>
<?= $this->extend('layouts/pkl') ?>
<?= $this->section('content') ?>
<div class="max-w-2xl mx-auto px-4 py-5 sm:py-10" x-data="pklLapor" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE), 'attr') ?>">

    <?= view('partials/kop_form', ['setting' => $setting, 'judul' => 'Lapor Diterima PKL', 'keterangan' => 'Diisi siswa lewat HP, tanpa login, setelah diterima perusahaan.']) ?>

    <div x-show="tahap === 1" class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-4 text-sm leading-relaxed text-brand-900 sm:px-5">
        <p class="font-bold">Kapan mengisi form ini?</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            <li>Ajuan PKL-mu sudah <b>disetujui sekolah</b>, dan</li>
            <li>perusahaan / instansi tempat PKL sudah <b>menyatakan menerima</b> kamu.</li>
        </ul>
        <p class="mt-2">Isi tanggal mulai dan selesai PKL yang disepakati dengan perusahaan. Sekolah lalu membuatkan <b>Surat Balasan</b> untuk perusahaan. Cukup <b>satu orang per kelompok</b> yang melapor.</p>
    </div>

    <!-- ============ LANGKAH 1 — CARI NAMA + NOMOR HP ============ -->
    <section class="bio-card mt-4" x-show="tahap === 1">
        <?= $kepala(1, 'Cari Namamu') ?>
        <div class="space-y-5 p-5 sm:p-6">
            <?php if ($kelas === []): ?>
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">Belum ada siswa dengan ajuan PKL yang sudah disetujui. Ajukan PKL dulu lewat form pengajuan; setelah disetujui dan diterima perusahaan, baru lapor di sini.</p>
            <?php else: ?>
                <div>
                    <label class="lbl" for="pilihKelas">Kelas kamu sekarang <span class="text-red-500">*</span></label>
                    <select id="pilihKelas" class="inp inp-lg" x-model="kelasId" @change="muatSiswa()">
                        <option value="">— Pilih kelas —</option>
                        <?php foreach ($kelas as $tingkat => $daftar): ?>
                            <optgroup label="Kelas <?= esc($tingkat) ?>">
                                <?php foreach ($daftar as $k): ?><option value="<?= (int) $k['id'] ?>"><?= esc($k['nama']) ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">Hanya kelas yang punya siswa dengan ajuan PKL disetujui yang muncul.</p>
                </div>

                <p x-cloak x-show="memuat" class="text-sm text-slate-500">Memuat daftar nama…</p>
                <p x-cloak x-show="pesanDaftar" x-text="pesanDaftar" class="text-sm font-semibold text-red-600"></p>

                <div x-cloak x-show="kelasId && !memuat && siswaList.length">
                    <label class="lbl" for="cariNama">Pilih namamu <span class="text-red-500">*</span></label>
                    <input id="cariNama" type="search" class="inp inp-lg" x-model="cari" placeholder="Ketik namamu untuk mencari…" autocomplete="off">
                    <div class="mt-2 max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                        <template x-for="s in siswaTersaring()" :key="s.id">
                            <button type="button" @click="pilihSiswa(s)" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition"
                                    :class="pilih && pilih.id === s.id ? 'bg-brand-50 ring-2 ring-inset ring-brand-500' : 'hover:bg-slate-50'">
                                <span class="font-medium text-slate-800" x-text="s.nama"></span>
                                <span x-show="lencana(s.status).teks" class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="lencana(s.status).kelas" x-text="lencana(s.status).teks"></span>
                            </button>
                        </template>
                        <p x-show="!siswaTersaring().length" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada nama yang cocok.</p>
                    </div>
                </div>

                <!-- Panel setelah nama dipilih -->
                <div x-ref="panel" x-cloak x-show="pilih">
                    <template x-if="pilih && ['boleh', 'sudah_lapor', 'dikembalikan'].includes(pilih.status)">
                        <div class="space-y-3 rounded-xl border-2 border-brand-200 bg-brand-50 p-4">
                            <div>
                                <p class="text-sm text-slate-600">Kamu memilih:</p>
                                <p class="mt-0.5 text-lg font-extrabold text-brand-800" x-text="pilih.nama"></p>
                                <p class="text-sm font-semibold text-slate-500" x-text="'Kelas ' + namaKelas()"></p>
                            </div>
                            <p x-show="pilih.status === 'sudah_lapor'" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Kelompokmu <b>sudah melapor</b> dan sedang menunggu diproses sekolah. Kamu boleh membukanya untuk melihat atau memperbarui tanggalnya.</p>
                            <p x-show="pilih.status === 'dikembalikan'" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">Laporan kelompokmu <b>dikembalikan</b> sekolah untuk diperbaiki. Buka untuk melihat alasannya.</p>
                            <div>
                                <label class="lbl" for="cekHp">Nomor HP / WhatsApp kamu</label>
                                <input id="cekHp" type="tel" inputmode="tel" x-model="hp" maxlength="20" autocomplete="tel" class="inp inp-lg bg-white" placeholder="Contoh: 081234567890" @keydown.enter.prevent="cek()">
                                <p class="hint">Nomor yang dulu kamu isi saat mengajukan PKL (atau yang tercatat di data sekolah).</p>
                            </div>
                            <p x-show="pesanCek" x-text="pesanCek" class="text-sm font-semibold text-red-600"></p>
                            <button type="button" @click="cek()" :disabled="proses" class="btn-nav-primary w-full disabled:opacity-60">
                                <span x-text="proses ? 'Memeriksa…' : 'Lanjut →'"></span>
                            </button>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'selesai'">
                        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                            <p class="font-extrabold text-green-800">✓ Sudah beres</p>
                            <p class="mt-1 text-sm text-slate-600">Laporan kelompok <span x-text="pilih.nama"></span> sudah diproses / Surat Balasan sudah dibuat sekolah. Tidak perlu lapor lagi. Bila ada data yang salah, hubungi operator sekolah atau Waka Hubin.</p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'belum_disetujui'">
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                            <p class="font-extrabold text-amber-900">Ajuan PKL-mu belum disetujui</p>
                            <p class="mt-1 text-sm text-amber-900">Lapor diterima baru bisa setelah ajuan PKL-mu disetujui sekolah. Tunggu keputusannya; bila ajuanmu dikembalikan untuk diperbaiki, perbaiki lewat form pengajuan PKL.</p>
                        </div>
                    </template>
                    <template x-if="pilih && pilih.status === 'belum_ajuan'">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-extrabold text-slate-800">Kamu belum mengajukan PKL</p>
                            <p class="mt-1 text-sm text-slate-600">Ajukan dulu lewat <a class="font-semibold text-brand-700 underline" href="<?= esc(config('Pkl')->tautan(), 'attr') ?>">form pengajuan PKL</a>. Setelah disetujui dan diterima perusahaan, baru lapor di sini.</p>
                        </div>
                    </template>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ LANGKAH 2 — ISI LAPORAN ============ -->
    <section class="bio-card mt-4" x-cloak x-show="tahap === 2">
        <?= $kepala(2, 'Lapor Diterima') ?>
        <div class="space-y-5 p-5 sm:p-6" x-show="data">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ajuan PKL <span x-text="data && data.kode"></span></p>
                <p class="mt-1 text-base font-bold text-slate-800" x-text="data && data.perusahaan"></p>
                <p class="mt-0.5 text-slate-600" x-show="data && data.alamat" x-text="data && data.alamat"></p>
                <p class="mt-2 text-xs font-semibold text-slate-500">Anggota kelompok:</p>
                <ul class="mt-1 list-disc pl-5 text-slate-700">
                    <template x-for="(a, i) in (data ? data.anggota : [])" :key="i"><li><span x-text="a.nama"></span> <span class="text-slate-400" x-text="a.kelas ? '(' + a.kelas + ')' : ''"></span></li></template>
                </ul>
            </div>

            <div x-show="data && data.lapor && data.lapor.status === 'dikembalikan'" class="rounded-xl border-2 border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">Laporan dikembalikan sekolah. Alasannya:</p>
                <p class="mt-0.5" x-text="data && data.lapor ? data.lapor.alasan : ''"></p>
                <p class="mt-1 text-xs">Perbaiki isian di bawah, lalu kirim ulang.</p>
            </div>
            <div x-show="data && data.lapor && data.lapor.status === 'baru'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Laporan sudah dikirim oleh <b x-text="data && data.lapor ? data.lapor.pelapor : ''"></b> dan sedang menunggu diproses sekolah. Kirim lagi hanya bila ada yang perlu diperbaiki.
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="tglMulai">Tanggal mulai PKL <span class="text-red-500">*</span></label>
                    <input id="tglMulai" type="date" x-model="f.tanggal_mulai" class="inp inp-lg" :class="err.tanggal_mulai && 'inp-err'">
                    <?= $galat('tanggal_mulai') ?>
                </div>
                <div>
                    <label class="lbl" for="tglSelesai">Tanggal selesai PKL <span class="text-red-500">*</span></label>
                    <input id="tglSelesai" type="date" x-model="f.tanggal_selesai" class="inp inp-lg" :class="err.tanggal_selesai && 'inp-err'">
                    <?= $galat('tanggal_selesai') ?>
                </div>
            </div>
            <p x-show="lama()" class="-mt-2 text-sm font-semibold text-brand-700" x-text="lama()"></p>

            <div>
                <label class="lbl" for="alamatPt">Alamat lengkap perusahaan <span class="font-normal text-slate-400">(perbaiki bila kurang lengkap)</span></label>
                <textarea id="alamatPt" x-model="f.alamat" rows="2" maxlength="300" class="inp inp-lg" :class="err.alamat && 'inp-err'"></textarea>
                <p class="hint">Alamat ini tercetak di Surat Balasan. Sertakan nama jalan, desa/kelurahan, kecamatan, kota/kabupaten, dan kode pos bila tahu.</p>
                <?= $galat('alamat') ?>
            </div>
            <div>
                <label class="lbl" for="catatanLapor">Catatan untuk sekolah <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                <textarea id="catatanLapor" x-model="f.catatan" rows="2" maxlength="300" class="inp inp-lg" placeholder="Contoh: nama pembimbing di perusahaan, jam masuk, atau hal lain yang perlu sekolah ketahui."></textarea>
                <?= $galat('catatan') ?>
            </div>

            <div class="hidden" aria-hidden="true"><input type="text" x-ref="jebakan" name="website" tabindex="-1" autocomplete="off"></div>
            <div x-cloak x-show="pesanKirim" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" x-text="pesanKirim"></div>

            <div class="flex items-center justify-between gap-3">
                <button type="button" @click="kembali()" class="btn-nav-secondary">&larr; Kembali</button>
                <button type="button" @click="kirim()" :disabled="mengirim" class="btn-nav-gold disabled:cursor-wait disabled:opacity-70">
                    <span x-text="mengirim ? 'Mengirim…' : (data && data.lapor ? 'Kirim Ulang Laporan' : 'Kirim Laporan')"></span>
                </button>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/pkl-lapor.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/pkl-lapor.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine.min.js') ?>"></script>
<?= $this->endSection() ?>
