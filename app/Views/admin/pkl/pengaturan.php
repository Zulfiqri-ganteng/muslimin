<?php
/**
 * Pengaturan PKL (Operator/Admin): buka-tutup form siswa, tingkat, maks siswa, batas keputusan Waka Hubin,
 * data surat resmi (Waka Hubin, Kepala Sekolah, kontak NB, format nomor & nama berkas).
 *
 * @var array   $galat   galat per kolom
 * @var array   $old     isian yang gagal disimpan
 * @var string  $tautan  alamat form siswa
 * @var ?string $alasan  null = form sedang terbuka
 * @var array   $siswa   ringkasan siswa
 */

use App\Models\PklPengaturanModel;

$nilai = static fn (string $k, $bawaan = ''): string => (string) ($old[$k] ?? $p[$k] ?? $bawaan);
$err   = static fn (string $k) => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$cls   = static fn (string $k) => isset($galat[$k]) ? 'inp-err' : '';

$buka    = $nilai('form_buka', '0') === '1';
$tingkat = isset($old['tingkat']) ? array_map('strval', (array) $old['tingkat']) : PklPengaturanModel::tingkatBoleh($p);
$tutup   = '';
if ($nilai('form_tutup') !== '') {
    $t     = strtotime($nilai('form_tutup'));
    $tutup = $t ? date('Y-m-d\TH:i', $t) : $nilai('form_tutup');
}
$tinjau = [
    'belum_dibuka'  => 'Form saat ini TERTUTUP (saklar mati).',
    'sudah_ditutup' => 'Form saat ini TERTUTUP karena batas waktu sudah lewat.',
    'belum_siap'    => 'Saklar menyala, tetapi form belum bisa dipakai: pilih dulu tingkat kelas yang boleh mengajukan.',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_pengaturan_v4',
    'helpTitle' => 'Pengaturan PKL',
    'helpBody'  => '<p>Atur <b>kapan</b> siswa boleh mengisi, <b>siapa</b> yang boleh (tingkat), dan data yang tercetak di surat resmi sekolah.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li>Form <b>tertutup</b> secara bawaan. Buka hanya saat tautan siap dibagikan; bisa diberi <b>batas waktu</b> agar menutup sendiri.</li>'
        . '<li><b>Maksimal siswa per perusahaan</b> paling banyak 5 (aturan sekolah).</li>'
        . '<li>Siswa yang sudah mengajukan <b>tidak bisa mengajukan lagi</b> selama ajuannya menunggu keputusan Waka Hubin. <b>Batas keputusan</b> (bawaan 5 hari sejak dikirim) memberi tanda merah pada yang terlambat.</li>'
        . '<li><b>Surat permohonan:</b> isi nama/NIP/jabatan Waka Hubin, nama Kepala Sekolah, kontak "NB" di bawah surat, format nomor surat, dan pola nama berkas. Format surat sudah <u>persis surat resmi sekolah</u>; hanya isi data yang berubah. Tanda tangan digital Waka Hubin diunggah Waka Hubin sendiri di tab <b>Tanda Tangan</b>.</li>'
        . '<li><b>Format nomor surat</b>: pilih salah satu tombol siap pakai (mis. <i>Tiga angka: 001, 002</i>). Nol di depan hanya lewat <code>{urut3}</code>; menulis &quot;{urut}00&quot; menghasilkan 100, 200, 600 sehingga ditolak.</li>'
        . '<li><b>Biaya &amp; pesan WhatsApp</b> (bagian bawah): nominal tiap jenis biaya yang dicatat saat surat diunduh (catatan lama tidak berubah) dan templat pesan yang dikirim manual ke siswa.</li>'
        . '<li><b>Impor riwayat PKL lama</b> dari Excel ada di bagian bawah halaman ini.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url('admin/pkl/pengaturan') ?>" class="mx-auto max-w-3xl space-y-5"
      x-data="{ buka: <?= $buka ? 'true' : 'false' ?>, tersalin: false, salin() { navigator.clipboard.writeText(<?= esc(json_encode($tautan), 'attr') ?>).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000); }); } }">
    <?= csrf_field() ?>

    <?php if ($galat !== []): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-bold text-red-700">Pengaturan belum disimpan. Periksa isian yang bertanda merah.</div>
    <?php endif; ?>

    <!-- Buka / tutup -->
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Form siswa</h3>
        <div class="space-y-4 p-5">
            <?php if ($alasan !== null): ?><p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900"><?= esc($tinjau[$alasan] ?? '') ?></p>
            <?php else: ?><p class="rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-800">Form saat ini TERBUKA untuk siswa.</p><?php endif; ?>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition border-slate-200 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:ring-2 has-[:checked]:ring-green-500/20">
                    <input type="radio" name="form_buka" value="1" x-model.number="buka" :value="1" <?= $buka ? 'checked' : '' ?> class="mt-1" @change="buka = true">
                    <span><b class="block text-sm text-slate-800">Buka form</b><span class="text-xs text-slate-500">Siswa bisa mengisi lewat tautan.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition border-slate-200 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 has-[:checked]:ring-2 has-[:checked]:ring-slate-400/20">
                    <input type="radio" name="form_buka" value="0" <?= $buka ? '' : 'checked' ?> class="mt-1" @change="buka = false">
                    <span><b class="block text-sm text-slate-800">Tutup form</b><span class="text-xs text-slate-500">Siswa melihat pemberitahuan "belum dibuka".</span></span>
                </label>
            </div>

            <div>
                <label class="lbl" for="f_form_tutup">Tutup otomatis pada <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="f_form_tutup" type="datetime-local" name="form_tutup" value="<?= esc($tutup, 'attr') ?>" class="inp max-w-xs <?= $cls('form_tutup') ?>">
                <p class="mt-1 text-xs text-slate-400">Setelah waktu ini, form menutup sendiri. Kosongkan bila ingin menutup manual.</p>
                <?= $err('form_tutup') ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tautan untuk dibagikan ke siswa</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-700 ring-1 ring-slate-200"><?= esc($tautan) ?></code>
                    <button type="button" @click="salin()" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><span x-text="tersalin ? '✓ Tersalin' : 'Salin'">Salin</span></button>
                </div>
            </div>
        </div>
    </section>

    <!-- Siapa -->
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Siapa yang boleh mengajukan</h3>
        <div class="space-y-4 p-5">
            <div>
                <p class="lbl">Tingkat kelas</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (PklPengaturanModel::TINGKAT as $t): ?>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                            <input type="checkbox" name="tingkat[]" value="<?= $t ?>" <?= in_array($t, $tingkat, true) ? 'checked' : '' ?> class="h-4 w-4"> Kelas <?= $t ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $err('tingkat') ?>
            </div>
            <div class="max-w-xs">
                <label class="lbl" for="f_maks_anggota">Maksimal siswa per perusahaan</label>
                <input id="f_maks_anggota" type="number" min="1" max="5" name="maks_anggota" value="<?= esc($nilai('maks_anggota', '5'), 'attr') ?>" class="inp <?= $cls('maks_anggota') ?>">
                <p class="mt-1 text-xs text-slate-400">Termasuk pengaju. Paling banyak 5 (aturan sekolah). 1 = tidak boleh ada teman satu tempat.</p>
                <?= $err('maks_anggota') ?>
            </div>
        </div>
    </section>

    <!-- Batas keputusan Waka Hubin -->
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Batas keputusan Waka Hubin</h3>
        <div class="space-y-3 p-5">
            <div class="max-w-xs">
                <label class="lbl" for="f_batas_hari">Waka Hubin memutuskan paling lambat (hari)</label>
                <input id="f_batas_hari" type="number" min="1" max="30" name="batas_keputusan_hari" value="<?= esc($nilai('batas_keputusan_hari', '5'), 'attr') ?>" class="inp <?= $cls('batas_keputusan_hari') ?>">
                <?= $err('batas_keputusan_hari') ?>
            </div>
            <p class="text-xs leading-relaxed text-slate-500">Dihitung sejak siswa menekan Kirim. Siswa diberi tahu tanggal batasnya dan diminta <b>tidak mengajukan ulang</b> selama menunggu. Ajuan yang lewat batas ditandai merah di Kotak Masuk dan Beranda PKL.</p>
        </div>
    </section>
    <!-- Surat -->
    <section id="surat" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
             x-data="{ pola: <?= esc(json_encode($nilai('format_nomor', \App\Libraries\PklNomorSurat::BAWAAN)), 'attr') ?>,
                       berkas: <?= esc(json_encode($nilai('format_nama_berkas', \App\Libraries\PklNamaBerkas::BAWAAN)), 'attr') ?>,
                       contohBerkas() { return (this.berkas || '').split('{urut}').join('295').split('{urut3}').join('295').split('{urut4}').join('0295').split('{nama_depan}').join('Ilyasha').split('{nama_pengaju}').join('Ilyasha Hawari').split('{all}').join('ALL').split('{kelas}').join('XII TKJ 5').split('{perusahaan}').join('PT Antarestar').split('{thn}').join(new Date().getFullYear()).replace(/\s+/g, ' ').trim() + '.docx'; },
                       contoh() { const d = new Date(), p = n => String(n).padStart(2, '0'), r = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
                                  return (this.pola || '').split('{urut}').join('7').split('{urut3}').join('007').split('{urut4}').join('0007').split('{tgl}').join(p(d.getDate())).split('{bln}').join(p(d.getMonth() + 1)).split('{bln_romawi}').join(r[d.getMonth()]).split('{thn}').join(d.getFullYear()); } }">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Surat permohonan PKL</h3>
        <div class="space-y-4 p-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_waka_nama">Nama Waka Hubin (penanda tangan)</label>
                    <input id="f_waka_nama" type="text" name="waka_hubin_nama" maxlength="150" value="<?= esc($nilai('waka_hubin_nama'), 'attr') ?>" class="inp <?= $cls('waka_hubin_nama') ?>" placeholder="Contoh: Budi Santoso, S.Pd.">
                    <?= $err('waka_hubin_nama') ?>
                    <?php if (trim((string) $nilai('waka_hubin_nama')) === ''): ?><p class="hint font-semibold text-amber-700">Belum diisi: nama di bawah tanda tangan surat akan berupa titik-titik.</p><?php endif; ?>
                </div>
                <div>
                    <label class="lbl" for="f_waka_nip">NIP <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="f_waka_nip" type="text" name="waka_hubin_nip" maxlength="40" inputmode="numeric" value="<?= esc($nilai('waka_hubin_nip'), 'attr') ?>" class="inp" placeholder="Hanya angka">
                </div>
            </div>
            <div>
                <label class="lbl" for="f_waka_jabatan">Jabatan di surat</label>
                <input id="f_waka_jabatan" type="text" name="waka_hubin_jabatan" maxlength="150" value="<?= esc($nilai('waka_hubin_jabatan', 'Wakil Kepala Sekolah Bidang Hubungan Industri'), 'attr') ?>" class="inp <?= $cls('waka_hubin_jabatan') ?>">
                <?= $err('waka_hubin_jabatan') ?>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_kepsek">Nama Kepala Sekolah <span class="font-normal text-slate-400">(di bawah tanda tangan Kepala Sekolah)</span></label>
                    <input id="f_kepsek" type="text" name="kepsek_nama" maxlength="150" value="<?= esc($nilai('kepsek_nama'), 'attr') ?>" class="inp <?= $cls('kepsek_nama') ?>" placeholder="Contoh: Napis Kuturupi, S.T.">
                    <?= $err('kepsek_nama') ?>
                </div>
                <div>
                    <p class="lbl">Tanda tangan digital Waka Hubin</p>
                    <p class="text-xs leading-relaxed text-slate-500">Diunggah oleh Waka Hubin sendiri di tab <b>Tanda Tangan</b> (Operator tidak bisa). Terpasang hanya pada surat yang di-ACC akun Waka Hubin. Saat ini: <b class="<?= \App\Libraries\PklSurat::infoTtd($p) !== null ? 'text-green-700' : 'text-slate-700' ?>"><?= \App\Libraries\PklSurat::infoTtd($p) !== null ? 'sudah ada' : 'belum ada (ruang dikosongkan)' ?></b>.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_kontak_nama">Kontak "NB" di bawah surat <span class="font-normal text-slate-400">(nama)</span></label>
                    <input id="f_kontak_nama" type="text" name="kontak_surat_nama" maxlength="150" value="<?= esc($nilai('kontak_surat_nama'), 'attr') ?>" class="inp <?= $cls('kontak_surat_nama') ?>" placeholder="Contoh: Puguh Wira Sakti, S.Pd.">
                    <?= $err('kontak_surat_nama') ?>
                </div>
                <div>
                    <label class="lbl" for="f_kontak_hp">Kontak "NB" <span class="font-normal text-slate-400">(nomor HP)</span></label>
                    <input id="f_kontak_hp" type="text" name="kontak_surat_hp" maxlength="30" inputmode="tel" value="<?= esc($nilai('kontak_surat_hp'), 'attr') ?>" class="inp <?= $cls('kontak_surat_hp') ?>" placeholder="Contoh: 0812 8584 526">
                    <?= $err('kontak_surat_hp') ?>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_format_nomor">Format nomor surat</label>
                    <input id="f_format_nomor" type="text" name="format_nomor" maxlength="100" x-model="pola" class="inp font-mono <?= $cls('format_nomor') ?>">
                    <p class="mt-1 text-xs text-slate-500">Contoh hasil (surat ke-7): <b class="font-mono text-slate-700" x-text="contoh()"></b></p>
                    <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Pilihan format siap pakai">
                        <?php foreach (\App\Libraries\PklNomorSurat::PRESET as [$polaPilihan, $ketPilihan]): ?>
                            <button type="button" @click="pola = <?= esc(json_encode($polaPilihan), 'attr') ?>" class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition" :class="pola === <?= esc(json_encode($polaPilihan), 'attr') ?> ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"><?= esc($ketPilihan) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-400">Penanda: <code>{urut}</code> (7) <code>{urut3}</code> (007) <code>{urut4}</code> (0007) <code>{tgl}</code> <code>{bln}</code> <code>{bln_romawi}</code> <code>{thn}</code>. <b>Nol di depan hanya lewat <code>{urut3}</code>/<code>{urut4}</code></b> &mdash; menulis &quot;{urut}00&quot; menghasilkan 100, 200, 600. Urutan dihitung per tahun dan ditetapkan sekali per surat; surat yang sudah bernomor tidak berubah.</p>
                    <?= $err('format_nomor') ?>
                </div>
                <div>
                    <label class="lbl" for="f_nomor_awal">Nomor berikutnya (tahun ini)</label>
                    <input id="f_nomor_awal" type="number" min="1" max="99999" name="nomor_awal" value="<?= esc($nilai('nomor_awal', '1'), 'attr') ?>" class="inp <?= $cls('nomor_awal') ?>">
                    <p class="mt-1 text-xs text-slate-400">Isi bila sekolah sudah memakai nomor berjalan (mis. sudah sampai 44 → isi 45). Nomor tak pernah mundur atau ganda.</p>
                    <?= $err('nomor_awal') ?>
                </div>
            </div>
            <div>
                <label class="lbl" for="f_format_nama_berkas">Pola nama berkas surat (satu surat)</label>
                <input id="f_format_nama_berkas" type="text" name="format_nama_berkas" maxlength="150" x-model="berkas" class="inp font-mono <?= $cls('format_nama_berkas') ?>">
                <p class="mt-1 text-xs text-slate-500">Contoh hasil: <b class="font-mono text-slate-700" x-text="contohBerkas()"></b></p>
                <p class="mt-1 text-[11px] leading-relaxed text-slate-400">Penanda: <code>{urut}</code> <code>{nama_depan}</code> <code>{nama_pengaju}</code> <code>{all}</code> (= ALL bila siswa lebih dari satu) <code>{kelas}</code> <code>{perusahaan}</code> <code>{thn}</code>.</p>
                <?= $err('format_nama_berkas') ?>
            </div>
        </div>
    </section>

    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-sm text-slate-600 shadow-sm">
        Dengan pengaturan tersimpan saat ini, <b><?= (int) $siswa['total'] ?></b> siswa dihitung (belum mengisi: <b><?= (int) $siswa['belum_isi'] ?></b>).
        Perubahan tingkat langsung mengubah daftar kelas dan nama yang dilihat siswa.
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan pengaturan</button>
    </div>
</form>

<?php $templateAda = \App\Libraries\PklSurat::pathTemplate($p) !== null; ?>
<div class="mx-auto mt-5 max-w-3xl space-y-5">
    <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Template surat Word milik sekolah</h3>
        <div class="space-y-3 p-5 text-sm text-slate-600">
            <p>
                Saat ini surat memakai
                <b class="<?= $templateAda ? 'text-green-700' : 'text-slate-800' ?>"><?= $templateAda ? 'TEMPLATE UNGGAHAN sekolah' : 'FORMAT SURAT RESMI SEKOLAH (bawaan sistem)' ?></b>.
                Format bawaan sudah persis surat resmi sekolah (kop, kalimat, tabel, tanda tangan). Bila format surat kelak berubah, unduh template, sunting di Word (jangan ubah penanda <code>${...}</code>), lalu unggah.
            </p>
            <div class="flex flex-wrap gap-2">
                <a href="<?= site_url('admin/pkl/pengaturan/template?contoh=1') ?>" class="rounded-lg border border-slate-300 px-3.5 py-2 font-semibold text-slate-700 hover:bg-slate-50">⬇ Unduh template format sekolah</a>
                <?php if ($templateAda): ?><a href="<?= site_url('admin/pkl/pengaturan/template') ?>" class="rounded-lg border border-slate-300 px-3.5 py-2 font-semibold text-slate-700 hover:bg-slate-50">⬇ Unduh template aktif</a><?php endif; ?>
            </div>
            <form method="post" action="<?= site_url('admin/pkl/pengaturan/template') ?>" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <?= csrf_field() ?>
                <input type="file" name="template" accept=".docx" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3.5 file:py-2 file:font-semibold file:text-brand-700">
                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-4 py-2.5 font-semibold text-white hover:bg-brand-800">Unggah template</button>
            </form>
            <?php if ($templateAda): ?>
                <form method="post" action="<?= site_url('admin/pkl/pengaturan/template/hapus') ?>" onsubmit="return confirm('Hapus template dan kembali ke surat bawaan?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Hapus template unggahan (kembali ke format sekolah bawaan)</button>
                </form>
            <?php endif; ?>
            <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed">
                <summary class="cursor-pointer font-bold text-slate-700">Daftar penanda yang bisa dipakai</summary>
                <p class="mt-2"><b>Umum:</b> <?php foreach (\App\Libraries\PklSurat::SKALAR as $k): ?><code class="mr-1">${<?= $k ?>}</code><?php endforeach; ?></p>
                <p class="mt-2"><b>Tabel siswa</b> (taruh dalam SATU baris tabel; baris itu otomatis digandakan sebanyak siswa): <?php foreach (\App\Libraries\PklSurat::BARIS as $k): ?><code class="mr-1">${<?= $k ?>}</code><?php endforeach; ?></p>
            </details>
        </div>
    </section>

    <?php if (isset($jenisBiaya)): $galatBiaya = (array) (session()->getFlashdata('galat_biaya') ?? []); $waNilai = (string) (old('wa_pesan') ?? ($waPesan ?? '')); ?>
    <form id="biaya" method="post" action="<?= site_url('admin/pkl/pengaturan/biaya') ?>" class="scroll-mt-24 space-y-5"
          x-data="{ pesan: <?= esc(json_encode($waNilai !== '' ? $waNilai : \App\Libraries\PklWa::PESAN_BAWAAN), 'attr') ?>,
                    contoh() { return (this.pesan || '').split('{nama}').join('Dewi Lestari').split('{kelas}').join('XI TKJ 1').split('{perusahaan}').join('PT Antarestar').split('{nomor_surat}').join('007/SMK-BN/PKL/X/2026').split('{tanggal_surat}').join('8 Oktober 2026').split('{sekolah}').join(<?= esc(json_encode((string) ($sekolahNama ?? 'SMK Bina Nusa')), 'attr') ?>); } }">
        <?= csrf_field() ?>
        <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Biaya yang dicatat saat surat diunduh</h3>
            <div class="space-y-3 p-5">
                <p class="text-xs leading-relaxed text-slate-500">Nominal ini dipakai untuk catatan <b>berikutnya</b>; catatan yang sudah ada menyimpan nominalnya sendiri dan tidak berubah. <b>Bulanan</b> = dicatat per bulan (pilihan bulan saat mengunduh); <b>sekali per kegiatan</b> = sekali per tahun ajaran. Jenis yang dinonaktifkan tidak muncul di kotak pencatatan.</p>
                <?php if (isset($galatBiaya['umum'])): ?><p class="err-msg"><?= esc($galatBiaya['umum']) ?></p><?php endif; ?>
                <?php foreach ($jenisBiaya as $jb): $kb = $jb['kode']; ?>
                    <div class="grid grid-cols-1 gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[1fr_9rem_9rem_auto] sm:items-end">
                        <div>
                            <label class="lbl" for="biaya_<?= $kb ?>_nama">Nama</label>
                            <input id="biaya_<?= $kb ?>_nama" type="text" name="biaya[<?= $kb ?>][nama]" maxlength="80" value="<?= esc((string) (old('biaya')[$kb]['nama'] ?? $jb['nama']), 'attr') ?>" class="inp <?= isset($galatBiaya[$kb . '.nama']) ? 'inp-err' : '' ?>">
                            <?php if (isset($galatBiaya[$kb . '.nama'])): ?><p class="err-msg"><?= esc($galatBiaya[$kb . '.nama']) ?></p><?php endif; ?>
                        </div>
                        <div>
                            <label class="lbl" for="biaya_<?= $kb ?>_nominal">Nominal (Rp)</label>
                            <input id="biaya_<?= $kb ?>_nominal" type="text" inputmode="numeric" name="biaya[<?= $kb ?>][nominal]" value="<?= esc((string) (old('biaya')[$kb]['nominal'] ?? $jb['nominal']), 'attr') ?>" class="inp text-right font-mono <?= isset($galatBiaya[$kb . '.nominal']) ? 'inp-err' : '' ?>">
                            <?php if (isset($galatBiaya[$kb . '.nominal'])): ?><p class="err-msg"><?= esc($galatBiaya[$kb . '.nominal']) ?></p><?php endif; ?>
                        </div>
                        <p class="pb-2.5 text-xs font-semibold text-slate-500"><?= $jb['siklus'] === 'bulanan' ? 'Bulanan' : 'Sekali per kegiatan' ?></p>
                        <label class="flex items-center gap-2 pb-2 text-sm font-medium text-slate-600"><input type="checkbox" name="biaya[<?= $kb ?>][aktif]" value="1" <?= (old('biaya') !== null ? ! empty(old('biaya')[$kb]['aktif']) : (bool) $jb['aktif']) ? 'checked' : '' ?> class="h-4 w-4"> Aktif</label>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rise overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Pesan WhatsApp ke siswa</h3>
            <div class="space-y-3 p-5">
                <p class="text-xs leading-relaxed text-slate-500">Dikirim <b>manual</b> oleh staf lewat tombol &quot;Kabari via WA&quot; setelah surat diunduh (WhatsApp terbuka dengan pesan ini; tinggal menekan Kirim). Sebaiknya tanpa rincian uang. Penanda: <?php foreach (\App\Libraries\PklWa::TOKEN as $tk): ?><code class="mr-1"><?= esc($tk) ?></code><?php endforeach; ?></p>
                <textarea name="wa_pesan" rows="3" maxlength="<?= \App\Libraries\PklWa::MAKS_PESAN ?>" x-model="pesan" class="inp"></textarea>
                <p class="text-xs text-slate-500">Contoh hasil: <span class="font-semibold text-slate-700" x-text="contoh()"></span></p>
                <button type="button" @click="pesan = <?= esc(json_encode(\App\Libraries\PklWa::PESAN_BAWAAN), 'attr') ?>" class="text-xs font-semibold text-brand-700 hover:underline">Kembalikan ke pesan bawaan</button>
            </div>
        </section>
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-brand-700 px-8 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95">Simpan biaya &amp; pesan WhatsApp</button>
        </div>
    </form>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Data PKL lama</h3>
        <p class="mt-2 text-sm text-slate-600">Punya daftar siswa yang sudah PKL sebelum sistem ini (di Excel)? Impor supaya mereka tercatat sudah PKL dan tak bisa mengajukan lagi.</p>
        <a href="<?= site_url('admin/pkl/impor') ?>" class="mt-3 inline-flex rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100">Impor riwayat PKL dari Excel →</a>
    </section>
</div>
<?= $this->endSection() ?>
