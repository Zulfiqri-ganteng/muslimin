<?php
/**
 * Pengaturan PKL (Operator/Admin): buka-tutup form siswa, tingkat, maks siswa, batas keputusan Waka Hubin,
 * data surat resmi (Waka Hubin, Kepala Sekolah, kontak NB, format nomor & nama berkas).
 * Rombak tampilan F9: tab kit (_nav), kartu kit per bagian, ui_peringatan, pilihan berbingkai, tombol kit ber-ikon. Siap CSP:
 * Salin tautan = kit salinTeks (dulu objek x-data bermetode + navigator.clipboard); contoh nomor surat / nama berkas / pesan WA
 * = komponen pklPolaSurat & pklPesanWa (assets/js/admin/pkl-pengaturan.js; dulu metode + fungsi panah sebaris); unggah template
 * = kit pilihBerkas; hapus template = data-confirm (dulu onsubmit confirm).
 *
 * @var array   $galat   galat per kolom
 * @var array   $old     isian yang gagal disimpan
 * @var string  $tautan  alamat form siswa
 * @var ?string $alasan  null = form sedang terbuka
 * @var array   $siswa   ringkasan siswa
 * @var list<array>|null $jenisBiaya jenis biaya (hak biaya)
 * @var string  $waPesan / $waPesanBalasan / $sekolahNama
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, $p.
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
$v = static fn (string $f): string => base_url($f) . '?v=' . @filemtime(FCPATH . $f);
$kepala = static fn (string $judul): string => '<div class="kartu-kepala"><h3 class="kartu-judul text-base">' . esc($judul) . '</h3></div>';
// Pilihan radio berbingkai (menyala saat dipilih).
$pilihan = static fn (string $nama, string $nilaiPil, bool $dipilih, string $judul, string $ket, string $nyala): string => '<label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 transition-colors dark:border-gray-600 dark:has-[:checked]:bg-gray-700 ' . $nyala . '">'
    . '<input type="radio" name="' . $nama . '" value="' . $nilaiPil . '"' . ($dipilih ? ' checked' : '') . ' class="cek mt-0.5">'
    . '<span><b class="block text-sm text-gray-900 dark:text-white">' . esc($judul) . '</b><span class="text-xs text-gray-500 dark:text-gray-300">' . esc($ket) . '</span></span></label>';
$nyalaHijau = 'has-[:checked]:border-green-500 has-[:checked]:bg-green-50';
$nyalaAbu   = 'has-[:checked]:border-gray-500 has-[:checked]:bg-gray-50';
// Kotak tautan + tombol Salin (kit salinTeks).
$kotakTautan = static fn (string $judul, string $url, string $pesan, string $ket = ''): string => '<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-700">'
    . '<p class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-300">' . esc($judul) . '</p>'
    . '<div class="mt-1.5 flex flex-wrap items-center gap-2">'
    . '<code class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-brand-400 dark:ring-gray-600">' . esc($url) . '</code>'
    . '<button type="button" class="btn btn-netral btn-sm" x-data="salinTeks" data-teks="' . esc($url, 'attr') . '" data-pesan="' . esc($pesan, 'attr') . '" @click="salin()">'
    . ikon('file-copy', 'h-4 w-4') . '<span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin</span></button></div>'
    . ($ket !== '' ? '<p class="hint !mt-1.5 dark:text-gray-300">' . esc($ket) . '</p>' : '') . '</div>';
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
        . '<li>Siswa yang sudah mengajukan <b>tidak bisa mengajukan lagi</b> selama ajuannya menunggu keputusan (ACC). <b>Batas keputusan</b> (bawaan 5 hari sejak dikirim) memberi tanda merah pada yang terlambat.</li>'
        . '<li><b>Surat permohonan:</b> isi nama/NIP/jabatan Waka Hubin, nama Kepala Sekolah, kontak "NB" di bawah surat, format nomor surat, dan pola nama berkas. Format surat sudah <u>persis surat resmi sekolah</u>; hanya isi data yang berubah. Tanda tangan digital Waka Hubin (dan tanda tangan + stempel Kepala Sekolah) diunggah di tab <b>Tanda Tangan</b> oleh peran yang diberi hak itu.</li>'
        . '<li><b>Format nomor surat</b>: pilih salah satu tombol siap pakai (mis. <i>Tiga angka: 001, 002</i>). Nol di depan hanya lewat <code>{urut3}</code>; menulis &quot;{urut}00&quot; menghasilkan 100, 200, 600 sehingga ditolak.</li>'
        . '<li><b>Biaya &amp; pesan WhatsApp</b> (bagian bawah): nominal tiap jenis biaya yang dicatat saat surat diunduh (catatan lama tidak berubah) dan templat pesan yang dikirim manual ke siswa.</li>'
        . '<li><b>Impor riwayat PKL lama</b> dari Excel ada di bagian bawah halaman ini.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<form method="post" action="<?= site_url('admin/pkl/pengaturan') ?>" class="mx-auto max-w-3xl space-y-4">
    <?= csrf_field() ?>

    <?php if ($galat !== []): ?>
        <?= ui_peringatan('galat', 'Pengaturan belum disimpan. Periksa isian yang bertanda merah.') ?>
    <?php endif; ?>

    <!-- Buka / tutup -->
    <section class="kartu">
        <?= $kepala('Form siswa') ?>
        <div class="kartu-isi space-y-4">
            <?= $alasan !== null ? ui_peringatan('awas', (string) ($tinjau[$alasan] ?? '')) : ui_peringatan('sukses', 'Form saat ini TERBUKA untuk siswa.') ?>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <?= $pilihan('form_buka', '1', $buka, 'Buka form', 'Siswa bisa mengisi lewat tautan.', $nyalaHijau) ?>
                <?= $pilihan('form_buka', '0', ! $buka, 'Tutup form', 'Siswa melihat pemberitahuan "belum dibuka".', $nyalaAbu) ?>
            </div>

            <div>
                <label class="lbl" for="f_form_tutup">Tutup otomatis pada <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                <input id="f_form_tutup" type="datetime-local" name="form_tutup" value="<?= esc($tutup, 'attr') ?>" class="inp max-w-xs <?= $cls('form_tutup') ?>">
                <p class="hint">Setelah waktu ini, form menutup sendiri. Kosongkan bila ingin menutup manual.</p>
                <?= $err('form_tutup') ?>
            </div>

            <?= $kotakTautan('Tautan untuk dibagikan ke siswa', $tautan, 'Tautan form siswa tersalin.') ?>
        </div>
    </section>

    <?php if (\App\Libraries\PklLapor::tabelAda()): $laporBuka = $nilai('lapor_buka', '1') === '1'; $tautanLapor = config('Pkl')->tautanLapor(); ?>
        <!-- Form lapor diterima (bahan Surat Balasan) -->
        <section class="kartu">
            <?= $kepala('Form lapor diterima (bahan Surat Balasan)') ?>
            <div class="kartu-isi space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">Siswa yang ajuannya sudah <b>disetujui</b> melapor bahwa perusahaan sudah menerimanya + tanggal PKL. Laporan masuk ke kotak <b>Lapor Diterima PKL</b> (menu Surat Sekolah), lalu staf membuatkan Surat Balasan. Saklar ini <u>terpisah</u> dari form pengajuan di atas, jadi siswa tetap bisa melapor walau pendaftaran PKL sudah ditutup.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <?= $pilihan('lapor_buka', '1', $laporBuka, 'Buka form lapor', 'Bawaan. Siswa bisa melapor lewat tautan di bawah.', $nyalaHijau) ?>
                    <?= $pilihan('lapor_buka', '0', ! $laporBuka, 'Tutup form lapor', 'Siswa melihat pemberitahuan "sedang ditutup".', $nyalaAbu) ?>
                </div>
                <?= $kotakTautan('Tautan lapor diterima untuk dibagikan ke siswa', $tautanLapor, 'Tautan lapor diterima tersalin.', 'Tautan ini juga muncul sebagai tombol "Lapor" di halaman form pengajuan PKL.') ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Siapa -->
    <section class="kartu">
        <?= $kepala('Siapa yang boleh mengajukan') ?>
        <div class="kartu-isi space-y-4">
            <div>
                <p class="lbl">Tingkat kelas</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (PklPengaturanModel::TINGKAT as $t): ?>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-900 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 dark:border-gray-600 dark:text-white dark:has-[:checked]:bg-gray-700 dark:has-[:checked]:text-white">
                            <input type="checkbox" name="tingkat[]" value="<?= $t ?>"<?= in_array($t, $tingkat, true) ? ' checked' : '' ?> class="cek"> Kelas <?= $t ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $err('tingkat') ?>
            </div>
            <div class="max-w-xs">
                <label class="lbl" for="f_maks_anggota">Maksimal siswa per perusahaan</label>
                <input id="f_maks_anggota" type="number" min="1" max="5" name="maks_anggota" value="<?= esc($nilai('maks_anggota', '5'), 'attr') ?>" class="inp <?= $cls('maks_anggota') ?>">
                <p class="hint">Termasuk pengaju. Paling banyak 5 (aturan sekolah). 1 = tidak boleh ada teman satu tempat.</p>
                <?= $err('maks_anggota') ?>
            </div>
        </div>
    </section>

    <!-- Batas keputusan ACC -->
    <section class="kartu">
        <?= $kepala('Batas keputusan ACC') ?>
        <div class="kartu-isi space-y-3">
            <div class="max-w-xs">
                <label class="lbl" for="f_batas_hari">Ajuan diputuskan (ACC/tolak) paling lambat (hari)</label>
                <input id="f_batas_hari" type="number" min="1" max="30" name="batas_keputusan_hari" value="<?= esc($nilai('batas_keputusan_hari', '5'), 'attr') ?>" class="inp <?= $cls('batas_keputusan_hari') ?>">
                <?= $err('batas_keputusan_hari') ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Dihitung sejak siswa menekan Kirim. Siswa diberi tahu tanggal batasnya dan diminta <b>tidak mengajukan ulang</b> selama menunggu. Ajuan yang lewat batas ditandai merah di Kotak Masuk dan Beranda PKL.</p>
        </div>
    </section>

    <!-- Surat -->
    <section id="surat" class="kartu scroll-mt-24" x-data="pklPolaSurat"
             data-pola="<?= esc($nilai('format_nomor', \App\Libraries\PklNomorSurat::BAWAAN), 'attr') ?>"
             data-berkas="<?= esc($nilai('format_nama_berkas', \App\Libraries\PklNamaBerkas::BAWAAN), 'attr') ?>">
        <?= $kepala('Surat permohonan PKL') ?>
        <div class="kartu-isi space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_waka_nama">Nama Waka Hubin (penanda tangan)</label>
                    <input id="f_waka_nama" type="text" name="waka_hubin_nama" maxlength="150" value="<?= esc($nilai('waka_hubin_nama'), 'attr') ?>" class="inp <?= $cls('waka_hubin_nama') ?>" placeholder="Contoh: Budi Santoso, S.Pd.">
                    <?= $err('waka_hubin_nama') ?>
                    <?php if (trim((string) $nilai('waka_hubin_nama')) === ''): ?><p class="hint font-semibold !text-yellow-700 dark:!text-yellow-400">Belum diisi: nama di bawah tanda tangan surat akan berupa titik-titik.</p><?php endif; ?>
                </div>
                <div>
                    <label class="lbl" for="f_waka_nip">NIP <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
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
                    <label class="lbl" for="f_kepsek">Nama Kepala Sekolah <span class="font-normal text-gray-500 dark:text-gray-400">(di bawah tanda tangan Kepala Sekolah)</span></label>
                    <input id="f_kepsek" type="text" name="kepsek_nama" maxlength="150" value="<?= esc($nilai('kepsek_nama'), 'attr') ?>" class="inp <?= $cls('kepsek_nama') ?>" placeholder="Contoh: Napis Kuturupi, S.T.">
                    <?= $err('kepsek_nama') ?>
                </div>
                <div>
                    <p class="lbl">Tanda tangan digital Waka Hubin</p>
                    <?php $adaTtd = \App\Libraries\PklSurat::infoTtd($p) !== null; $adaTtdKepsek = \App\Libraries\PklSurat::infoTtdKepsek($p) !== null; ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Diunggah di tab <b>Tanda Tangan</b> oleh peran yang diberi hak itu (diatur Admin). Tercetak di semua surat, siapa pun yang meng-ACC. Saat ini: <span class="lencana <?= $adaTtd ? 'lencana-hijau' : 'lencana-abu' ?>"><?= $adaTtd ? 'sudah ada' : 'belum ada (ruang dikosongkan)' ?></span></p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tanda tangan + stempel Kepala Sekolah (tab yang sama): <span class="lencana <?= $adaTtdKepsek ? 'lencana-hijau' : 'lencana-abu' ?> whitespace-normal"><?= $adaTtdKepsek ? 'sudah ada — menggantikan tanda tangan bawaan template' : 'belum ada (memakai tanda tangan bawaan template, tanpa stempel)' ?></span></p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_kontak_nama">Kontak "NB" di bawah surat <span class="font-normal text-gray-500 dark:text-gray-400">(nama)</span></label>
                    <input id="f_kontak_nama" type="text" name="kontak_surat_nama" maxlength="150" value="<?= esc($nilai('kontak_surat_nama'), 'attr') ?>" class="inp <?= $cls('kontak_surat_nama') ?>" placeholder="Contoh: Puguh Wira Sakti, S.Pd.">
                    <?= $err('kontak_surat_nama') ?>
                </div>
                <div>
                    <label class="lbl" for="f_kontak_hp">Kontak "NB" <span class="font-normal text-gray-500 dark:text-gray-400">(nomor HP)</span></label>
                    <input id="f_kontak_hp" type="text" name="kontak_surat_hp" maxlength="30" inputmode="tel" value="<?= esc($nilai('kontak_surat_hp'), 'attr') ?>" class="inp <?= $cls('kontak_surat_hp') ?>" placeholder="Contoh: 0812 8584 526">
                    <?= $err('kontak_surat_hp') ?>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="f_format_nomor">Format nomor surat</label>
                    <input id="f_format_nomor" type="text" name="format_nomor" maxlength="100" x-model="pola" value="<?= esc($nilai('format_nomor', \App\Libraries\PklNomorSurat::BAWAAN), 'attr') ?>" class="inp font-mono <?= $cls('format_nomor') ?>">
                    <p class="hint">Contoh hasil (surat ke-7): <b class="font-mono text-gray-900 dark:text-white" x-text="contohNomorSurat()"></b></p>
                    <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Pilihan format siap pakai">
                        <?php foreach (\App\Libraries\PklNomorSurat::PRESET as [$polaPilihan, $ketPilihan]): $jsPola = esc(json_encode($polaPilihan), 'attr'); ?>
                            <button type="button" class="tombol-saring" @click="pola = <?= $jsPola ?>" :aria-pressed="pola === <?= $jsPola ?>"><?= esc($ketPilihan) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Penanda: <code>{urut}</code> (7) <code>{urut3}</code> (007) <code>{urut4}</code> (0007) <code>{tgl}</code> <code>{bln}</code> <code>{bln_romawi}</code> <code>{thn}</code>. <b>Nol di depan hanya lewat <code>{urut3}</code>/<code>{urut4}</code></b> &mdash; menulis &quot;{urut}00&quot; menghasilkan 100, 200, 600. Urutan dihitung per tahun dan ditetapkan sekali per surat; surat yang sudah bernomor tidak berubah.</p>
                    <?= $err('format_nomor') ?>
                </div>
                <div>
                    <label class="lbl" for="f_nomor_awal">Nomor berikutnya (tahun ini)</label>
                    <input id="f_nomor_awal" type="number" min="1" max="99999" name="nomor_awal" value="<?= esc($nilai('nomor_awal', '1'), 'attr') ?>" class="inp <?= $cls('nomor_awal') ?>">
                    <p class="hint">Isi bila sekolah sudah memakai nomor berjalan (mis. sudah sampai 44 → isi 45). Nomor tak pernah mundur atau ganda. <b>Satu urutan untuk Surat Izin PKL dan semua Surat Sekolah</b> (Izin ASTS/TKA, Balasan, Penarikan), jadi tidak ada dua surat bernomor sama.</p>
                    <?= $err('nomor_awal') ?>
                </div>
            </div>
            <div>
                <label class="lbl" for="f_format_nama_berkas">Pola nama berkas surat (satu surat)</label>
                <input id="f_format_nama_berkas" type="text" name="format_nama_berkas" maxlength="150" x-model="berkas" value="<?= esc($nilai('format_nama_berkas', \App\Libraries\PklNamaBerkas::BAWAAN), 'attr') ?>" class="inp font-mono <?= $cls('format_nama_berkas') ?>">
                <p class="hint">Contoh hasil: <b class="font-mono text-gray-900 dark:text-white" x-text="contohNamaBerkas()"></b></p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Penanda: <code>{urut}</code> <code>{nama_depan}</code> <code>{nama_pengaju}</code> <code>{all}</code> (= ALL bila siswa lebih dari satu) <code>{kelas}</code> <code>{perusahaan}</code> <code>{thn}</code>.</p>
                <?= $err('format_nama_berkas') ?>
            </div>
        </div>
    </section>

    <?= ui_peringatan('info', '', ['html' => 'Dengan pengaturan tersimpan saat ini, <b>' . (int) $siswa['total'] . '</b> siswa dihitung (belum mengisi: <b>' . (int) $siswa['belum_isi'] . '</b>). Perubahan tingkat langsung mengubah daftar kelas dan nama yang dilihat siswa.']) ?>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pengaturan</button>
    </div>
</form>

<?php $templateAda = \App\Libraries\PklSurat::pathTemplate($p) !== null; ?>
<div class="mx-auto mt-4 max-w-3xl space-y-4">
    <section class="kartu">
        <?= $kepala('Template surat Word milik sekolah') ?>
        <div class="kartu-isi space-y-3 text-sm text-gray-600 dark:text-gray-300">
            <p>
                Saat ini surat memakai
                <span class="lencana <?= $templateAda ? 'lencana-hijau' : 'lencana-abu' ?>"><?= $templateAda ? 'TEMPLATE UNGGAHAN sekolah' : 'FORMAT SURAT RESMI SEKOLAH (bawaan sistem)' ?></span>.
                Format bawaan sudah persis surat resmi sekolah (kop, kalimat, tabel, tanda tangan). Bila format surat kelak berubah, unduh template, sunting di Word (jangan ubah penanda <code>${...}</code>), lalu unggah.
            </p>
            <div class="flex flex-wrap gap-2">
                <a href="<?= site_url('admin/pkl/pengaturan/template?contoh=1') ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh template format sekolah</a>
                <?php if ($templateAda): ?><a href="<?= site_url('admin/pkl/pengaturan/template') ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh template aktif</a><?php endif; ?>
            </div>
            <form method="post" action="<?= site_url('admin/pkl/pengaturan/template') ?>" enctype="multipart/form-data" class="space-y-3">
                <?= csrf_field() ?>
                <label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih"
                       class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-5 text-center transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">
                    <?= ikon('upload', 'h-7 w-7 text-gray-500 dark:text-gray-400') ?>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Pilih template Word (.docx)</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>
                    <input type="file" name="template" accept=".docx" required class="sr-only" @change="catatBerkas($event)">
                </label>
                <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Unggah template</button>
            </form>
            <?php if ($templateAda): ?>
                <form method="post" action="<?= site_url('admin/pkl/pengaturan/template/hapus') ?>"
                      data-confirm="Hapus template dan kembali ke surat bawaan?" data-confirm-judul="Hapus template?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-teks btn-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus template unggahan (kembali ke format sekolah bawaan)</button>
                </form>
            <?php endif; ?>
            <details class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs dark:border-gray-600 dark:bg-gray-700">
                <summary class="cursor-pointer font-semibold text-gray-900 dark:text-white">Daftar penanda yang bisa dipakai</summary>
                <p class="mt-2"><b>Umum:</b> <?php foreach (\App\Libraries\PklSurat::SKALAR as $k): ?><code class="me-1">${<?= $k ?>}</code><?php endforeach; ?></p>
                <p class="mt-2"><b>Tabel siswa</b> (taruh dalam SATU baris tabel; baris itu otomatis digandakan sebanyak siswa): <?php foreach (\App\Libraries\PklSurat::BARIS as $k): ?><code class="me-1">${<?= $k ?>}</code><?php endforeach; ?></p>
            </details>
        </div>
    </section>

    <?php if (isset($jenisBiaya)): $galatBiaya = (array) (session()->getFlashdata('galat_biaya') ?? []); $waNilai = (string) (old('wa_pesan') ?? ($waPesan ?? ''));
        $waBalasanAda = \App\Libraries\SuratWa::kolomAda(); $waNilaiB = (string) (old('wa_pesan_balasan') ?? ($waPesanBalasan ?? '')); ?>
        <form id="biaya" method="post" action="<?= site_url('admin/pkl/pengaturan/biaya') ?>" class="scroll-mt-24 space-y-4" x-data="pklPesanWa"
              data-pesan="<?= esc($waNilai !== '' ? $waNilai : \App\Libraries\PklWa::PESAN_BAWAAN, 'attr') ?>"
              data-pesan-balasan="<?= esc($waNilaiB !== '' ? $waNilaiB : \App\Libraries\SuratWa::PESAN_BAWAAN, 'attr') ?>"
              data-sekolah="<?= esc((string) ($sekolahNama ?? 'SMK Bina Nusa'), 'attr') ?>">
            <?= csrf_field() ?>
            <section class="kartu">
                <?= $kepala('Biaya yang dicatat saat surat diunduh') ?>
                <div class="kartu-isi space-y-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nominal ini dipakai untuk catatan <b>berikutnya</b>; catatan yang sudah ada menyimpan nominalnya sendiri dan tidak berubah. <b>Bulanan</b> = dicatat per bulan (pilihan bulan saat mengunduh); <b>sekali per kegiatan</b> = sekali per tahun ajaran. Jenis yang dinonaktifkan tidak muncul di kotak pencatatan.</p>
                    <?php if (isset($galatBiaya['umum'])): ?><p class="err-msg"><?= esc($galatBiaya['umum']) ?></p><?php endif; ?>
                    <?php foreach ($jenisBiaya as $jb): $kb = $jb['kode']; ?>
                        <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-[1fr_9rem_9rem_auto] sm:items-end dark:border-gray-600">
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
                            <p class="pb-2.5"><span class="lencana <?= $jb['siklus'] === 'bulanan' ? 'lencana-biru' : 'lencana-ungu' ?>"><?= $jb['siklus'] === 'bulanan' ? 'Bulanan' : 'Sekali per kegiatan' ?></span></p>
                            <label class="flex items-center gap-2 pb-2.5 text-sm font-medium text-gray-900 dark:text-white"><input type="checkbox" name="biaya[<?= $kb ?>][aktif]" value="1"<?= (old('biaya') !== null ? ! empty(old('biaya')[$kb]['aktif']) : (bool) $jb['aktif']) ? ' checked' : '' ?> class="cek"> Aktif</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="kartu">
                <?= $kepala('Pesan WhatsApp ke siswa') ?>
                <div class="kartu-isi space-y-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Dikirim <b>manual</b> oleh staf lewat tombol &quot;Kabari via WA&quot; setelah surat diunduh (WhatsApp terbuka dengan pesan ini; tinggal menekan Kirim). Sebaiknya tanpa rincian uang. Penanda: <?php foreach (\App\Libraries\PklWa::TOKEN as $tk): ?><code class="me-1"><?= esc($tk) ?></code><?php endforeach; ?></p>
                    <textarea name="wa_pesan" rows="3" maxlength="<?= \App\Libraries\PklWa::MAKS_PESAN ?>" x-model="pesan" class="inp" aria-label="Pesan WhatsApp surat PKL"><?= esc($waNilai !== '' ? $waNilai : \App\Libraries\PklWa::PESAN_BAWAAN) ?></textarea>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Contoh hasil: <span class="font-medium text-gray-900 dark:text-white" x-text="contohPesanWa(pesan)"></span></p>
                    <button type="button" class="btn btn-teks btn-sm" @click="pesan = <?= esc(json_encode(\App\Libraries\PklWa::PESAN_BAWAAN), 'attr') ?>"><?= ikon('clock-arrow', 'h-4 w-4') ?>Kembalikan ke pesan bawaan</button>
                    <?php if ($waBalasanAda): ?>
                        <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white">Pesan Surat Balasan siap diambil</b> — dikirim manual lewat tombol &quot;Kabari siswa via WhatsApp&quot; di halaman Surat Balasan setelah suratnya diunduh. Penanda sama seperti di atas.</p>
                            <textarea name="wa_pesan_balasan" rows="3" maxlength="<?= \App\Libraries\PklWa::MAKS_PESAN ?>" x-model="pesanB" class="inp" aria-label="Pesan WhatsApp Surat Balasan"><?= esc($waNilaiB !== '' ? $waNilaiB : \App\Libraries\SuratWa::PESAN_BAWAAN) ?></textarea>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Contoh hasil: <span class="font-medium text-gray-900 dark:text-white" x-text="contohPesanWa(pesanB)"></span></p>
                            <button type="button" class="btn btn-teks btn-sm" @click="pesanB = <?= esc(json_encode(\App\Libraries\SuratWa::PESAN_BAWAAN), 'attr') ?>"><?= ikon('clock-arrow', 'h-4 w-4') ?>Kembalikan ke pesan bawaan</button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan biaya &amp; pesan WhatsApp</button>
            </div>
        </form>
    <?php endif; ?>

    <section class="kartu kartu-isi">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Data PKL lama</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Punya daftar siswa yang sudah PKL sebelum sistem ini (di Excel)? Impor supaya mereka tercatat sudah PKL dan tak bisa mengajukan lagi.</p>
        <a href="<?= site_url('admin/pkl/impor') ?>" class="btn btn-netral mt-3"><?= ikon('upload', 'h-4 w-4') ?>Impor riwayat PKL dari Excel</a>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/pkl-pengaturan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
