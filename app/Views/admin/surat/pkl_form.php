<?php
/**
 * Formulir Surat Balasan PKL / Surat Penarikan Izin PKL — buat baru atau ubah.
 * Rombak tampilan F9: Kembali di kepala halaman, kartu kit per bagian, ui_peringatan, lencana kit, pratinjau = peringatan info
 * kit, tombol kit ber-ikon, skrip ber-nonce. Siap CSP: String(a.id) → a.id (nilai DOM selalu teks), metode periodeTeks/tglIndo →
 * teksPeriode/teksTglIndo, daftar nama siswa = metode daftarNamaSiswa(a) (dulu fungsi sebaris di x-text), emoji dibuang.
 * Rapi tampilan U5 (2026-10-26): cari-lalu-pilih — daftar ajuan tampil setelah mengetik ≥ 2 huruf / "Lihat semua"; yang sudah dipilih
 * selalu tampil (tampilDaftar di surat-pkl.js). Isian & nilai yang terkirim TETAP sama.
 *
 * @var string               $jenis       balasan_pkl | penarikan_pkl
 * @var string               $label
 * @var bool                 $ubah        true = mengubah surat yang sudah ada
 * @var array<string, mixed> $nilai       tanggal_surat, sapaan, periode_mulai, periode_selesai, alamat (ubah), tgl_efektif,
 *                                         alasan_pilih, alasan_lain, ajuan[] (id), siswa[] (id)
 * @var list<array>          $ajuan       SuratPkl::ajuan() — ajuan disetujui (saat ubah: hanya ajuan surat itu, bisa kosong)
 * @var bool                 $perluAcc
 * @var list<string>         $sapaanSaran
 * @var list<string>         $alasanSaran
 * @var array<string, mixed> $batas       awal, akhir (Y-m-d), min, maks (hari) dari Pengaturan PKL
 * @var array<string, mixed> $surat       (hanya saat ubah) baris surat_sekolah
 * @var string               $kode        (hanya saat ubah) kode surat, mis. SRT-00012
 * @var list<array>          $siswaSurat  (hanya saat ubah) siswa pada surat
 * @var array|null           $lapor       (Balasan baru) laporan siswa dari kotak "Lapor Diterima PKL" — formulir terisi dari laporan
 * @var int                  $laporMenunggu (Balasan baru) jumlah laporan siswa yang menunggu diproses
 * @var array{bawaan: string, saran: list<array{judul: string, isi: string}>} $alasanTolak (Balasan) alasan siswa yang tidak diterima
 *
 * Balasan (2026-10-21): tiap ajuan terpilih menampilkan centang "siswa yang diterima perusahaan" (atur_terima[{ajuan}] + terima[{ajuan}][]);
 * siswa yang tidak dicentang dikeluarkan dari ajuan sebagai "ditolak perusahaan" saat disimpan (Libraries\SuratPkl::pilahTerima).
 */

use App\Libraries\SuratJenis;

$balasan = $jenis === SuratJenis::BALASAN;
$lapor   = $ubah ? null : ($lapor ?? null);
$alamat  = SuratJenis::alamat($jenis);
$aksi    = $ubah ? site_url($alamat . '/' . (int) $surat['id'] . '/ubah') : site_url($alamat);

// Balasan: siswa yang awalnya TIDAK dicentang ("ajuan:siswa" => true) — isian lama sesudah galat simpan, atau (ubah) anggota aktif ajuan
// yang tidak tercantum di surat.
$tolakAwal = [];
if ($balasan) {
    $aturLama = old('atur_terima');
    foreach ($ajuan as $a) {
        if (is_array($aturLama)) {
            if (! isset($aturLama[$a['id']])) {
                continue;
            }
            $dicentang = array_map('intval', (array) (old('terima')[$a['id']] ?? []));
        } elseif ($ubah) {
            $dicentang = array_map('intval', array_column($siswaSurat, 'siswa_id'));
        } else {
            continue;
        }
        foreach ($a['siswa'] as $s) {
            if (! in_array($s['siswa_id'], $dicentang, true)) {
                $tolakAwal[$a['id'] . ':' . $s['siswa_id']] = true;
            }
        }
    }
}
$kembali = $ubah ? site_url('admin/surat/' . (int) $surat['id']) : site_url($lapor !== null ? 'admin/surat/balasan/laporan' : 'admin/surat');
$cfg     = [
    'jenis' => $balasan ? 'balasan' : 'penarikan', 'ubah' => $ubah, 'tempat' => $ubah ? (string) $surat['perusahaan_nama'] : '',
    'dipilih' => array_map('strval', $nilai['ajuan']), 'siswaDipilih' => array_map('strval', $nilai['siswa']),
    'mulai' => $nilai['periode_mulai'], 'selesai' => $nilai['periode_selesai'], 'tglEfektif' => $nilai['tgl_efektif'],
    'alasanPilih' => $nilai['alasan_pilih'], 'alasanLain' => $nilai['alasan_lain'], 'alasanSaran' => $alasanSaran, 'batas' => $batas,
    'tolakAwal' => (object) $tolakAwal, 'alasanTolak' => (string) (old('alasan_tolak') ?? $alasanTolak['bawaan']), 'alasanTolakSaran' => $alasanTolak['saran'],
    'ajuan' => array_map(static fn (array $a) => [
        'id' => $a['id'], 'kode' => $a['kode'], 'perusahaan' => $a['perusahaan'], 'alamat' => $a['alamat'], 'mulai' => $a['mulai'], 'selesai' => $a['selesai'],
        'siswa' => array_map(static fn (array $s) => ['id' => $s['siswa_id'], 'nama' => $s['nama'], 'kelas' => $s['kelas']], $a['siswa']),
        'balasan' => $a['balasan'] !== null ? ['id' => $a['balasan']['id'], 'kode' => \App\Models\SuratSekolahModel::kode($a['balasan']['id']), 'nomor' => $a['balasan']['nomor']] : null,
        'penarikan' => $a['penarikan'],
    ], $ajuan),
];
$kepala = static fn (string $judul): string => '<div class="kartu-kepala"><h3 class="kartu-judul text-base">' . $judul . '</h3></div>';
// Balasan: centang siswa yang DITERIMA perusahaan untuk satu ajuan `a` (dipakai di daftar ajuan [buat] dan di formulir ubah).
$blokTerima = static fn (string $kelas): string => '<div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700 ' . $kelas . '">'
    . '<input type="hidden" :name="namaAtur(a)" value="1">'
    . '<p class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Siswa yang diterima perusahaan</p>'
    . '<div class="mt-2 space-y-1.5"><template x-for="s in a.siswa" :key="s.id">'
    . '<label class="flex cursor-pointer flex-wrap items-center gap-2 text-sm text-gray-900 dark:text-white">'
    . '<input type="checkbox" :name="namaTerima(a)" :value="s.id" :checked="diterima(a, s)" @change="ubahTerima(a, s)" class="cek">'
    . '<span><span class="font-semibold" x-text="s.nama"></span> <span class="text-xs text-gray-500 dark:text-gray-400" x-text="s.kelas"></span></span>'
    . '<span x-show="!diterima(a, s)" class="lencana lencana-merah">Tidak diterima</span></label></template></div>'
    . '<p x-show="jumlahTerima(a) === 0" class="err-msg mt-2">Minimal satu siswa harus diterima. Bila semua ditolak, buka ajuan PKL-nya lalu tekan "Ditolak perusahaan".</p></div>';
// Pratinjau: kotak info kit dengan judul.
$bukaPratinjau = '<div class="peringatan peringatan-info sm:col-span-2">' . ikon('eye', 'mt-0.5 h-5 w-5 shrink-0') . '<div class="min-w-0 flex-1"><p class="font-semibold">Pratinjau di surat</p>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= $kembali ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?><?= $ubah ? 'Kembali ke surat' : ($lapor !== null ? 'Kotak Lapor Diterima PKL' : 'Daftar Surat') ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => $balasan ? 'surat_balasan_form_v2' : 'surat_penarikan_form_v2',
    'helpTitle' => $label,
    'helpBody'  => $balasan
        ? '<p>Surat ini <b>menegaskan bahwa perusahaan menerima siswa PKL</b>, lengkap dengan periode dan lokasi penempatan — persis contoh sekolah. Hanya untuk ajuan PKL yang sudah <b>disetujui</b>.</p>'
            . '<ul class="mt-2 list-disc space-y-1 pl-5">'
            . '<li><b>Cari dulu</b>: ketik minimal 2 huruf nama siswa, perusahaan, atau nomor ajuan; daftar ajuan muncul dan ajuan yang sudah dicentang tetap tampil. Tombol <b>Lihat semua</b> menampilkan seluruh ajuan.</li>'
            . '<li>Centang satu atau beberapa ajuan, isi <b>periode PKL</b> (sama untuk semua yang dicentang). Lama PKL "3 (tiga) bulan" dihitung otomatis dari tanggalnya.</li>'
            . '<li>Periode ikut <b>tersimpan di ajuan PKL</b> (tampil di detail ajuan dan Status Siswa) dan tercatat di riwayatnya.</li>'
            . '<li>Satu ajuan hanya punya satu Surat Balasan. Bila salah setelah bernomor, batalkan suratnya lalu buat lagi.</li>'
            . '<li>' . ($perluAcc ? 'Surat menunggu <b>ACC</b> (' . esc($pemegangAcc ?? 'peran yang berhak') . ', diatur Admin di Sistem → Hak Akses). Setelah disetujui, surat diunduh dalam bentuk Word.' . (! empty($bisaLangsungAcc) ? ' Anda juga memegang hak ACC jenis ini, jadi bisa langsung memakai tombol <b>Simpan &amp; setujui</b>.' : '') : 'Jenis ini saat ini <b>tanpa ACC</b>: surat langsung siap diunduh.') . '</li>'
            . '</ul>'
        : '<p>Surat <b>pemberhentian dan penarikan siswa dari tempat PKL</b>, persis contoh sekolah. Cari ajuannya (ketik minimal 2 huruf nama siswa / perusahaan, atau <b>Lihat semua</b>), pilih, centang siswa yang ditarik (boleh sebagian), isi tanggal berlakunya dan alasannya.</p>'
            . '<ul class="mt-2 list-disc space-y-1 pl-5">'
            . '<li>Status PKL siswa <b>tidak berubah</b> otomatis; surat ini tercatat di riwayat ajuan.</li>'
            . '<li>Alasan masuk ke kalimat "Penarikan tersebut dilakukan karena …". Pilih alasan yang ada atau tulis sendiri — periksa pratinjaunya.</li>'
            . '<li>' . ($perluAcc ? 'Surat menunggu <b>ACC</b> (' . esc($pemegangAcc ?? 'peran yang berhak') . ', diatur Admin di Sistem → Hak Akses). Setelah disetujui, surat diunduh dalam bentuk Word.' . (! empty($bisaLangsungAcc) ? ' Anda juga memegang hak ACC jenis ini, jadi bisa langsung memakai tombol <b>Simpan &amp; setujui</b>.' : '') : 'Jenis ini saat ini <b>tanpa ACC</b>: surat langsung siap diunduh.') . '</li>'
            . '</ul>',
]) ?>

<div class="mx-auto max-w-4xl space-y-4">
    <?php if ($lapor !== null): ?>
        <?= ui_peringatan('info', '', ['judul' => 'Dari laporan siswa ' . (string) $lapor['pelapor_nama'] . ' (' . date('d-m-Y H:i', strtotime((string) $lapor['updated_at'])) . ')',
            'html' => 'Periode' . ((string) ($lapor['alamat'] ?? '') !== '' ? ' dan alamat perusahaan' : '') . ' sudah diisi dari laporan &mdash; periksa, ubah bila perlu, lalu simpan. Laporan otomatis ditandai selesai setelah surat dibuat.'
                . ((string) ($lapor['catatan'] ?? '') !== '' ? '<p class="mt-1.5 text-xs"><b>Catatan siswa:</b> ' . esc((string) $lapor['catatan']) . '</p>' : '')]) ?>
    <?php elseif ($balasan && ! $ubah && (int) ($laporMenunggu ?? 0) > 0): ?>
        <?= ui_peringatan('awas', '', ['html' => 'Ada <b>' . (int) $laporMenunggu . ' laporan siswa</b> "sudah diterima perusahaan" yang menunggu dibuatkan Surat Balasan. <a href="' . site_url('admin/surat/balasan/laporan') . '" class="font-semibold underline">Buka kotak laporan</a>']) ?>
    <?php endif; ?>

    <form method="post" action="<?= esc($aksi, 'attr') ?>" x-data="suratPklForm" data-config="<?= esc(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <?php if ($lapor !== null): ?><input type="hidden" name="lapor_id" value="<?= (int) $lapor['id'] ?>"><?php endif; ?>

        <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"><?= $ubah ? 'Ubah ' . esc($label) . ' <span class="lencana lencana-abu font-mono">' . esc($kode) . '</span>' : 'Buat ' . esc($label) ?></h2>

        <!-- Tempat PKL -->
        <section class="kartu">
            <?= $kepala('Tempat PKL' . ($balasan ? '' : ' &amp; siswa yang ditarik')) ?>
            <div class="kartu-isi space-y-3">
                <?php if ($ubah): ?>
                    <p class="text-sm text-gray-700 dark:text-gray-300"><b><?= esc((string) $surat['perusahaan_nama']) ?></b><?php if (! empty($surat['pengajuan_id'])): ?> — <a href="<?= site_url('admin/pkl/' . (int) $surat['pengajuan_id']) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400">lihat ajuan PKL</a><?php endif; ?></p>
                    <?php if ($balasan && $ajuan === []): ?>
                        <?= ui_peringatan('awas', 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi daftar siswa tidak bisa diubah (tetap: ' . implode(', ', array_column($siswaSurat, 'nama')) . ').') ?>
                    <?php elseif ($balasan): ?>
                        <p class="hint">Tempat tidak bisa diganti di sini (bila keliru, batalkan surat ini lalu buat yang baru). Lepas centang siswa yang <b>tidak diterima</b> perusahaan.</p>
                        <template x-for="a in ajuan" :key="a.id"><?= $blokTerima('') ?></template>
                    <?php elseif ($ajuan === []): ?>
                        <?= ui_peringatan('awas', 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi daftar siswa tidak bisa diubah (tetap: ' . implode(', ', array_column($siswaSurat, 'nama')) . ').') ?>
                        <?php foreach ($siswaSurat as $s): ?><input type="hidden" name="siswa[]" value="<?= (int) $s['siswa_id'] ?>"><?php endforeach; ?>
                    <?php endif; ?>
                <?php elseif ($ajuan === []): ?>
                    <?= ui_peringatan('awas', 'Belum ada ajuan PKL yang sudah disetujui, jadi belum ada tempat yang bisa dipilih.') ?>
                <?php else: ?>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="search" x-model="cari" placeholder="Ketik nama siswa, perusahaan, atau nomor ajuan…" class="inp sm:flex-1" aria-label="Cari ajuan">
                        <button type="button" class="btn btn-teks" @click="lihatSemua()"><span x-text="semua ? 'Sembunyikan daftar' : 'Lihat semua (<?= count($ajuan) ?>)'">Lihat semua (<?= count($ajuan) ?>)</span></button>
                        <?php if ($balasan && $lapor === null): ?>
                            <button type="button" class="btn btn-netral" @click="pilihSemua()" title="Semua yang cocok dengan pencarian (atau semua bila kotak cari kosong)"><?= ikon('check', 'h-4 w-4') ?>Pilih semua yang belum punya Balasan</button>
                            <button type="button" class="btn btn-teks" x-show="dipilih.length > 0" x-cloak @click="dipilih = []"><?= ikon('close', 'h-4 w-4') ?>Kosongkan</button>
                        <?php endif; ?>
                    </div>
                    <p x-show="!mencari() && !adaPilihan()" class="hint">Ketik minimal 2 huruf untuk mencari di antara <?= count($ajuan) ?> ajuan yang sudah disetujui, lalu pilih ajuannya.</p>
                    <p x-show="mencari() && jumlahCocok() === 0" x-cloak class="hint">Tidak ada ajuan yang cocok dengan pencarian ini.</p>
                    <?php if ($balasan): ?><p x-show="adaPilihan()" x-cloak class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white" x-text="dipilih.length"></b> dari <?= count($ajuan) ?> ajuan dipilih.</p><?php endif; ?>
                    <ul x-show="mencari() || adaPilihan()" x-cloak class="max-h-96 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                        <template x-for="a in ajuan" :key="a.id">
                            <li x-show="tampilDaftar(a)">
                                <label class="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60" :class="jenis === 'balasan' && a.balasan && 'cursor-not-allowed opacity-60'">
                                    <?php if ($balasan): ?>
                                        <input type="checkbox" name="ajuan[]" :value="a.id" x-model="dipilih" :disabled="!!a.balasan" class="cek mt-0.5">
                                    <?php else: ?>
                                        <input type="radio" name="ajuan[]" :value="a.id" x-model="ajuanTunggal" class="cek mt-0.5">
                                    <?php endif; ?>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white"><span x-text="a.perusahaan"></span> <span class="font-mono text-xs font-normal text-gray-500 dark:text-gray-400" x-text="a.kode"></span></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400"><span x-text="a.siswa.length"></span> siswa — <span x-text="daftarNamaSiswa(a)"></span></span>
                                        <span x-show="a.alamat" class="block text-xs text-gray-500 dark:text-gray-400" x-text="a.alamat"></span>
                                        <span x-show="a.mulai && a.selesai" class="lencana lencana-abu mt-1" x-text="'Periode tercatat: ' + teksPeriode(a.mulai, a.selesai)"></span>
                                        <a x-show="a.balasan" :href="'<?= site_url('admin/surat') ?>/' + (a.balasan ? a.balasan.id : '')" class="lencana lencana-hijau mt-1 hover:underline" x-text="a.balasan ? 'Sudah ada Surat Balasan ' + a.balasan.kode + (a.balasan.nomor ? ' (' + a.balasan.nomor + ')' : '') : ''"></a>
                                        <span x-show="jenis === 'penarikan' && a.penarikan > 0" class="lencana lencana-merah mt-1" x-text="a.penarikan + ' surat penarikan sudah ada'"></span>
                                    </span>
                                </label>
                                <?php if ($balasan): ?><template x-if="terpilih(a)"><?= $blokTerima('mx-4 mb-3') ?></template><?php endif; ?>
                            </li>
                        </template>
                    </ul>
                <?php endif; ?>

                <?php if ($balasan && $ajuan !== []): ?>
                    <div x-show="adaTolak()" x-cloak class="peringatan peringatan-awas">
                        <?= ikon('exclamation-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
                        <div class="min-w-0 flex-1 space-y-2">
                            <p>Siswa yang tidak dicentang akan <b>dikeluarkan dari ajuan</b> sebagai <b>"Ditolak perusahaan"</b> saat disimpan: ia bebas mengajukan tempat PKL lain dan tetap tercatat di riwayatnya. Surat Balasan hanya memuat siswa yang diterima.</p>
                            <label class="lbl" for="alasan_tolak">Alasan untuk siswa yang tidak diterima <span class="text-red-600 dark:text-red-400">*</span></label>
                            <textarea id="alasan_tolak" name="alasan_tolak" x-model="alasanTolak" rows="2" maxlength="1000" class="inp"></textarea>
                            <?php if ($alasanTolak['saran'] !== []): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($alasanTolak['saran'] as $i => $t): ?>
                                        <button type="button" class="btn btn-netral btn-sm" @click="pakaiAlasanTolak(<?= (int) $i ?>)"><?= ikon('file-lines', 'h-4 w-4') ?><?= esc($t['judul']) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p class="hint">Siswa membaca alasan ini di halaman status PKL. Template alasan bisa diatur di PKL → Template alasan.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (! $balasan && $ajuan !== []): ?>
                    <div x-show="siswaAjuan().length" class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                        <p class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Siswa yang ditarik <span class="text-red-600 dark:text-red-400">*</span></p>
                        <div class="mt-2 space-y-1.5">
                            <template x-for="s in siswaAjuan()" :key="s.id">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                                    <input type="checkbox" name="siswa[]" :value="s.id" x-model="siswaDipilih" class="cek">
                                    <span><span class="font-semibold" x-text="s.nama"></span> <span class="text-xs text-gray-500 dark:text-gray-400" x-text="s.kelas"></span></span>
                                </label>
                            </template>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Isi surat -->
        <section class="kartu">
            <?= $kepala('Isi surat') ?>
            <div class="kartu-isi grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="sapaan">Kepada Yth. Bapak/Ibu …</label>
                    <input id="sapaan" name="sapaan" type="text" value="<?= esc($nilai['sapaan'], 'attr') ?>" maxlength="60" list="saranSapaan" class="inp" placeholder="Pimpinan">
                    <datalist id="saranSapaan"><?php foreach ($sapaanSaran as $s): ?><option value="<?= esc($s, 'attr') ?>"><?php endforeach; ?></datalist>
                    <p class="hint">Tercetak "Bapak/Ibu <?= esc($nilai['sapaan']) ?>". Kosong = Pimpinan.</p>
                </div>
                <div>
                    <label class="lbl" for="tanggal_surat">Tanggal surat <span class="text-red-600 dark:text-red-400">*</span></label>
                    <input id="tanggal_surat" name="tanggal_surat" type="date" value="<?= esc($nilai['tanggal_surat'], 'attr') ?>" required class="inp">
                    <p class="hint">Menentukan bulan dan tahun pada nomor surat.</p>
                </div>

                <?php if ($balasan): ?>
                    <div>
                        <label class="lbl" for="periode_mulai">PKL mulai <span class="text-red-600 dark:text-red-400">*</span></label>
                        <input id="periode_mulai" name="periode_mulai" type="date" x-model="mulai" required class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="periode_selesai">PKL selesai <span class="text-red-600 dark:text-red-400">*</span></label>
                        <input id="periode_selesai" name="periode_selesai" type="date" x-model="selesai" required class="inp">
                    </div>
                    <?php if ($ubah || $lapor !== null): ?>
                        <div class="sm:col-span-2">
                            <label class="lbl" for="alamat">Alamat perusahaan (baris "Alamat" di surat)</label>
                            <input id="alamat" name="alamat" type="text" value="<?= esc($nilai['alamat'], 'attr') ?>" maxlength="300" class="inp">
                            <?php if ($lapor !== null): ?><p class="hint"><?= (string) ($lapor['alamat'] ?? '') !== '' ? 'Alamat baru dari laporan siswa.' : 'Alamat dari ajuan PKL.' ?> Kosongkan = alamat di ajuan PKL.</p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?= $bukaPratinjau ?>
                        <p x-show="lamaTeks() !== ''" class="mt-1.5"><span class="inline-block w-28">Periode PKL</span>: <b x-text="lamaTeks() + ', bulan ' + teksPeriode(mulai, selesai)"></b></p>
                        <p x-show="lamaTeks() === ''" class="mt-1.5 text-xs">Isi tanggal mulai dan selesai PKL untuk melihat pratinjau.</p>
                        <template x-for="t in peringatan()" :key="t"><p class="mt-2 flex items-start gap-1.5 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold text-yellow-800 dark:border-yellow-800 dark:bg-gray-700 dark:text-yellow-300"><?= ikon('exclamation-circle', 'h-4 w-4 shrink-0') ?><span x-text="t"></span></p></template>
                    </div></div>
                <?php else: ?>
                    <div>
                        <label class="lbl" for="tgl_efektif">Ditarik terhitung sejak tanggal <span class="text-red-600 dark:text-red-400">*</span></label>
                        <input id="tgl_efektif" name="tgl_efektif" type="date" x-model="tglEfektif" required class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="alasan_pilih">Alasan <span class="text-red-600 dark:text-red-400">*</span></label>
                        <select id="alasan_pilih" name="alasan_pilih" x-model="alasanPilih" class="inp">
                            <?php foreach ($alasanSaran as $i => $a): ?><option value="<?= (int) $i ?>"><?= esc(ucfirst($a)) ?></option><?php endforeach; ?>
                            <option value="lain">Lainnya — tulis sendiri</option>
                        </select>
                    </div>
                    <div x-show="alasanPilih === 'lain'" x-cloak class="sm:col-span-2">
                        <label class="lbl" for="alasan_lain">Alasan (tulis sendiri)</label>
                        <textarea id="alasan_lain" name="alasan_lain" x-model="alasanLain" rows="2" maxlength="300" class="inp" placeholder="contoh: peserta didik yang bersangkutan mengundurkan diri dari sekolah"></textarea>
                    </div>
                    <?= $bukaPratinjau ?>
                        <p class="mt-1.5">"…terhitung sejak tanggal <b x-text="teksTglIndo(tglEfektif) || '……'"></b>, diberhentikan dan ditarik dari kegiatan Praktik Kerja Lapangan di <b x-text="namaTempat() || '……'"></b>. Penarikan tersebut dilakukan karena <b x-text="alasanTeks() || '……'"></b>."</p>
                    </div></div>
                <?php endif; ?>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <a href="<?= $kembali ?>" class="btn btn-netral">Batal</a>
            <?php if (! $ubah && ! empty($bisaLangsungAcc)): ?>
                <?php // Dua tombol: yang pertama (tombol Enter) = aman, masuk antrean ACC; yang kedua = peran ini juga memegang hak ACC jenis ini. ?>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <button type="submit" name="langsung_acc" value="0" class="btn btn-netral"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Buat &amp; ajukan untuk ACC</button>
                    <button type="submit" name="langsung_acc" value="1" class="btn btn-sukses"><?= ikon('check', 'h-4 w-4') ?>Simpan &amp; setujui (ACC)</button>
                </div>
            <?php else: ?>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><?= $ubah ? 'Simpan perubahan' : ($perluAcc ? 'Buat surat &amp; ajukan untuk ACC' : 'Buat surat') ?></button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/surat-pkl.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/surat-pkl.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
