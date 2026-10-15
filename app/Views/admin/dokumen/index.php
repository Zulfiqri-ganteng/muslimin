<?php

/**
 * Penjelajah Manajemen Dokumen. Rombak tampilan F10: Unggah / Folder baru / Tambah tautan / Sampah di kepala halaman, satu kartu
 * kit (remah jejak + penyimpanan, penyaring .alat-crud, bilah aksi massal, isi, paginasi), ikon Flowbite per jenis berkas,
 * lencana kit, tombol kit ber-ikon (Ubah, Bagikan hijau, Buang merah). Tiga tampilan (ikon · kotak · daftar) tetap.
 *
 * Siap CSP: logika pindah ke komponen penjelajahDokumen & unggahDokumen (assets/js/admin/dokumen.js; dulu objek x-data sebaris
 * bermetode + fungsi panah + spread + tulis $refs). Status butir lewat :data-terpilih / :data-diseret / :data-sasaran (gaya .dok-*
 * di admin.css), bukan :class. Dialog Folder baru / Unggah / Tautan / Ubah dokumen / Ubah folder = dialog kit (isi & alamat form
 * dari tombol pemicu: data-dialog-isi / data-dialog-aksi); dialog Pindahkan milik komponen (butuh daftar centang). Konfirmasi =
 * data-confirm kit (dulu onsubmit confirm). Penanda kemajuan penyimpanan = <progress> (dulu style= lebar).
 * Perbaikan: "Pindahkan ke" di Ubah folder kini terisi letak folder saat ini (dulu selalu "folder utama", jadi mengganti nama
 * subfolder ikut memindahkannya ke folder utama). Tombol aksi folder & kotak centang ikon kini tampak di layar sentuh.
 *
 * @var array                         $rows         Dokumen pada halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array                         $subfolder    Subfolder langsung
 * @var array<int,int>                $isiFolder    [folder_id => jumlah dokumen]
 * @var array                         $jejak        Breadcrumb dari akar
 * @var int|null                      $folderId     Folder yang sedang dibuka
 * @var array|null                    $folderKini
 * @var string                        $q
 * @var string                        $kategori
 * @var string                        $urut
 * @var string                        $arah
 * @var string                        $tampilan     ikon|grid|daftar
 * @var int                           $per
 * @var bool                          $cariGlobal
 * @var array                         $kategoriList
 * @var array<int,string>             $folderOpts
 * @var array                         $pemakaian
 * @var int                           $kuotaByte
 * @var int                           $maksByte
 * @var int                           $jmlSampah
 */
helper('dokumen');

$base = site_url('admin/dokumen');

/** Tautan penjelajah dengan parameter yang dipertahankan. */
$url = static function (array $ganti = []) use ($base, $folderId, $q, $kategori, $urut, $arah, $tampilan, $per) {
    $p = array_filter([
        'folder'   => array_key_exists('folder', $ganti) ? $ganti['folder'] : $folderId,
        'q'        => array_key_exists('q', $ganti) ? $ganti['q'] : $q,
        'kategori' => $ganti['kategori'] ?? $kategori,
        'urut'     => $ganti['urut']     ?? $urut,
        'arah'     => $ganti['arah']     ?? $arah,
        'tampilan' => $ganti['tampilan'] ?? $tampilan,
        'per'      => $ganti['per']      ?? $per,
    ], static fn ($v) => $v !== null && $v !== '');

    return $base . ($p ? '?' . http_build_query($p) : '');
};

/** Lencana & ikon per jenis berkas — satu warna per jenis supaya dikenali sekilas tanpa membaca label. */
$lencanaKategori = [
    'pdf' => 'lencana-merah', 'dokumen' => 'lencana-biru', 'spreadsheet' => 'lencana-hijau', 'presentasi' => 'lencana-kuning',
    'gambar' => 'lencana-ungu', 'audio' => 'lencana-pink', 'video' => 'lencana-merah', 'arsip' => 'lencana-kuning', 'lainnya' => 'lencana-abu',
];
$ikonKategori = [
    'pdf'         => ['file-pdf', 'text-red-600 dark:text-red-400'],
    'dokumen'     => ['file-word', 'text-blue-600 dark:text-blue-400'],
    'spreadsheet' => ['file-csv', 'text-green-600 dark:text-green-400'],
    'presentasi'  => ['file-ppt', 'text-orange-500 dark:text-orange-400'],
    'gambar'      => ['file-image', 'text-purple-600 dark:text-purple-400'],
    'audio'       => ['file-music', 'text-pink-600 dark:text-pink-400'],
    'video'       => ['file-video', 'text-rose-600 dark:text-rose-400'],
    'arsip'       => ['file-zip', 'text-yellow-500 dark:text-yellow-400'],
    'lainnya'     => ['file', 'text-gray-500 dark:text-gray-400'],
];
$labelVisib   = ['privat' => 'Privat', 'link' => 'Lewat tautan', 'publik' => 'Publik'];
$lencanaVisib = ['privat' => 'lencana-abu', 'link' => 'lencana-merek', 'publik' => 'lencana-hijau'];

$tgl      = static fn ($d) => $d ? date('d/m/Y H:i', strtotime((string) $d)) : '—';
$pakai    = (int) ($pemakaian['total_byte'] ?? 0);
$persen   = $kuotaByte > 0 ? min(100, (int) round($pakai / $kuotaByte * 100)) : 0;
$indukIni = (string) (int) $folderId;
$tautanHal = $pager !== null ? trim((string) $pager->only(['q', 'kategori', 'urut', 'arah', 'tampilan', 'per', 'folder'])->links('dok', 'admin')) : '';

/** Ikon jenis berkas (tautan non-video = ikon rantai). */
$ikonDok = static function (array $r, string $ukuran) use ($ikonKategori): string {
    $kat = (string) $r['kategori'];
    [$nama, $warna] = $r['tipe'] === 'tautan' && $kat === 'lainnya'
        ? ['link', 'text-brand-600 dark:text-brand-400']
        : ($ikonKategori[$kat] ?? $ikonKategori['lainnya']);

    return ikon($nama, $ukuran . ' shrink-0 ' . $warna);
};

/** Alamat gambar sampul (thumbnail unggahan atau sampul YouTube), null bila tak ada. */
$sampul = static function (array $r): ?string {
    if (! empty($r['thumb'])) {
        return site_url('admin/dokumen/thumb/' . (int) $r['id']);
    }
    if ($r['tipe'] === 'tautan' && $r['penyedia'] === 'youtube') {
        return dokumen_thumb_url_eksternal((string) $r['url_eksternal']);
    }

    return null;
};

/** Tombol aksi satu dokumen: Unduh · Ubah · Buang ke sampah. */
$aksiDok = static function (array $r): string {
    $id     = (int) $r['id'];
    $tautan = $r['tipe'] === 'tautan';
    $judul  = esc($r['judul'], 'attr');
    $isi    = ['judul' => $r['judul'], 'deskripsi' => (string) $r['deskripsi'], 'visib' => $r['visibilitas']]
        + ($tautan ? ['url' => (string) $r['url_eksternal']] : []);

    $h = '';
    if (! $tautan) {
        $h .= '<a href="' . site_url('admin/dokumen/unduh/' . $id) . '" class="btn btn-netral btn-ikon btn-xs" title="Unduh" aria-label="Unduh ' . $judul . '">' . ikon('download', 'h-4 w-4') . '</a>';
    }
    $h .= '<button type="button" class="btn btn-netral btn-ikon btn-xs" title="Ubah" aria-label="Ubah ' . $judul . '"'
        . ' data-buka-dialog="' . ($tautan ? 'dok-ubah-tautan' : 'dok-ubah') . '" data-dialog-aksi="' . esc(site_url('admin/dokumen/' . $id), 'attr') . '"'
        . ' data-dialog-isi="' . esc((string) json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') . '">' . ikon('edit', 'h-4 w-4') . '</button>';
    $h .= '<form method="post" action="' . site_url('admin/dokumen/' . $id . '/hapus') . '" class="ms-auto"'
        . ' data-confirm="Buang &quot;' . $judul . '&quot; ke tempat sampah? Masih bisa dipulihkan dari Sampah." data-confirm-judul="Buang ke sampah?" data-confirm-ya="Ya, buang" data-confirm-jenis="bahaya">'
        . csrf_field() . '<button type="submit" class="btn btn-bahaya btn-ikon btn-xs" title="Buang ke sampah" aria-label="Buang ' . $judul . ' ke sampah">' . ikon('trash-bin', 'h-4 w-4') . '</button></form>';

    return $h;
};

/** Formulir buang folder (dipakai tampilan kotak/daftar & tampilan ikon). */
$formBuangFolder = static function (array $f, string $kelasForm = ''): string {
    $nama = esc($f['nama'], 'attr');

    return '<form method="post" action="' . site_url('admin/dokumen/folder/' . (int) $f['id'] . '/hapus') . '"' . ($kelasForm !== '' ? ' class="' . $kelasForm . '"' : '')
        . ' data-confirm="Buang folder &quot;' . $nama . '&quot; beserta SELURUH isinya ke tempat sampah?" data-confirm-judul="Buang folder?" data-confirm-ya="Ya, buang" data-confirm-jenis="bahaya">'
        . csrf_field() . '<button type="submit" class="btn btn-bahaya btn-ikon btn-xs" title="Buang folder" aria-label="Buang folder ' . $nama . '">' . ikon('trash-bin', 'h-4 w-4') . '</button></form>';
};

/** Tombol aksi satu folder: Ubah · Bagikan · Buang. */
$aksiFolder = static function (array $f) use ($indukIni, $formBuangFolder): string {
    $id   = (int) $f['id'];
    $nama = esc($f['nama'], 'attr');
    $isi  = ['nama' => $f['nama'], 'deskripsi' => (string) $f['deskripsi'], 'visib' => $f['visibilitas'], 'induk' => $indukIni];

    return '<button type="button" class="btn btn-netral btn-ikon btn-xs" title="Ubah folder" aria-label="Ubah folder ' . $nama . '"'
        . ' data-buka-dialog="dok-folder-ubah" data-dialog-aksi="' . esc(site_url('admin/dokumen/folder/' . $id), 'attr') . '"'
        . ' data-dialog-isi="' . esc((string) json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') . '">' . ikon('edit', 'h-4 w-4') . '</button>'
        . '<form method="post" action="' . site_url('admin/dokumen/folder/' . $id . '/bagikan') . '"'
        . ' data-confirm="Buat tautan berbagi untuk folder &quot;' . $nama . '&quot; beserta isinya? Siapa pun yang memegang tautannya bisa membuka isi folder." data-confirm-judul="Bagikan folder?" data-confirm-ya="Ya, buat tautan">'
        . csrf_field() . '<input type="hidden" name="boleh_unduh" value="1"><button type="submit" class="btn btn-sukses btn-ikon btn-xs" title="Bagikan folder" aria-label="Bagikan folder ' . $nama . '">' . ikon('share-nodes', 'h-4 w-4') . '</button></form>'
        . $formBuangFolder($f);
};

/** Atribut sasaran seret (folder / remah jejak). Kunci: id folder, atau 'akar' untuk folder utama. */
$sasaran = static function (int $idTujuan, string $kunci): string {
    return '@dragover.prevent="lewatiFolder(' . $kunci . ', $event)" @dragleave="tinggalkanFolder()" @drop.prevent="jatuhkan(' . $idTujuan . ')" :data-sasaran="sasaranSeret(' . $kunci . ')"';
};

/** Atribut butir dokumen yang bisa dicentang & diseret. */
$butir = static function (int $id): string {
    return 'draggable="true" data-dok-id="' . $id . '" @dragstart="mulaiSeret(' . $id . ', $event)" @dragend="selesaiSeret()" :data-terpilih="dipilih(' . $id . ')" :data-diseret="diseret(' . $id . ')"';
};

/** Kotak centang satu dokumen. */
$centang = static function (array $r): string {
    $id = (int) $r['id'];

    return '<span class="sr-only">Pilih ' . esc($r['judul']) . '</span><input type="checkbox" class="cek" :checked="dipilih(' . $id . ')" @change="centangDok(' . $id . ')">';
};

$opsiAkses = static fn (bool $panjang): string => '<option value="privat">Privat' . ($panjang ? ' — hanya admin yang masuk' : '') . '</option>'
    . '<option value="link">Lewat tautan' . ($panjang ? ' — siapa pun yang punya tautannya' : '') . '</option>'
    . '<option value="publik">Publik' . ($panjang ? ' — tampil di situs sekolah' : '') . '</option>';

$tampilanList = [
    'ikon'   => ['grid', 'Ikon', 'Tampilan ikon — padat, seperti penjelajah berkas di komputer'],
    'grid'   => ['image', 'Kotak', 'Tampilan kotak — kartu besar bergambar'],
    'daftar' => ['list', 'Daftar', 'Tampilan daftar — tabel rinci'],
];
$judulKosong = $cariGlobal ? 'Tidak ada dokumen yang cocok' : ($subfolder !== [] ? 'Belum ada berkas di folder ini' : 'Folder ini masih kosong');
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<button type="button" class="btn btn-utama" data-buka-dialog="dok-unggah"><?= ikon('upload', 'h-4 w-4') ?>Unggah berkas</button>
<button type="button" class="btn btn-sukses" data-buka-dialog="dok-folder-baru"><?= ikon('folder-plus', 'h-4 w-4') ?>Folder baru</button>
<button type="button" class="btn btn-netral" data-buka-dialog="dok-tautan"><?= ikon('link', 'h-4 w-4') ?>Tambah tautan</button>
<a href="<?= site_url('admin/dokumen/sampah') ?>" class="btn btn-netral"><?= ikon('trash-bin', 'h-4 w-4') ?>Sampah<?php if ($jmlSampah > 0): ?><span class="lencana lencana-abu"><?= (int) $jmlSampah ?></span><?php endif; ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_v2',
    'helpTitle' => 'Manajemen Dokumen',
    'helpBody'  => '<p>Simpan berkas kerja (Word, Excel, PowerPoint, PDF, foto, dan lainnya) dalam <b>folder bertingkat</b> seperti Google Drive, lalu buka kembali kapan saja dari komputer maupun HP.</p>
        <p class="mt-1">• <b>Pindahkan berkas dengan menyeretnya</b> ke folder tujuan — bisa juga ke nama folder di jalur atas untuk memindahkan keluar. Centang beberapa berkas dulu, lalu seret salah satunya, maka semuanya ikut pindah. Di HP: centang berkasnya lalu pakai tombol <b>Pindahkan</b>.<br>
        • Tiga <b>tampilan</b> di kanan penyaring: <b>Ikon</b> (padat, seperti penjelajah berkas di komputer), <b>Kotak</b> (kartu besar bergambar), dan <b>Daftar</b> (tabel rinci).<br>
        • <b>Unggah</b> bisa banyak berkas sekaligus — seret ke kotak unggah atau klik untuk memilih.<br>
        • <b>Video</b> tidak disimpan di server (memakan ruang); pakai tombol <b>Tambah tautan</b> untuk menempelkan tautan YouTube atau Google Drive.<br>
        • <b>Privat</b> = hanya bisa dibuka setelah masuk ke panel ini. <b>Lewat tautan</b> &amp; <b>Publik</b> dipakai saat berkas dibagikan ke guru.<br>
        • <b>Pencarian</b> berlaku ke seluruh arsip, bukan hanya folder yang sedang dibuka.<br>
        • Berkas yang dihapus masuk <b>Tempat Sampah</b> dulu, jadi masih bisa dipulihkan.</p>',
]) ?>

<div x-data="penjelajahDokumen">

    <!-- Formulir tersembunyi untuk pemindahan lewat seret-lepas (diisi komponen). Rutenya sama dengan tombol "Pindahkan" agar
         aturan keamanannya satu pintu (tak ada jalur khusus untuk seret-lepas). -->
    <form method="post" action="<?= site_url('admin/dokumen/pindah') ?>" class="hidden" data-form-seret>
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
        <input type="hidden" name="tujuan" value="0">
    </form>

    <section class="kartu overflow-hidden" aria-label="Penjelajah dokumen">

        <!-- Remah jejak (juga sasaran seret) + penyimpanan terpakai -->
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <nav class="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-sm" aria-label="Letak folder">
                <a href="<?= esc($url(['folder' => null, 'q' => null]), 'attr') ?>" <?= $sasaran(0, "'akar'") ?>
                   class="dok-sasaran inline-flex items-center gap-1.5 rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $folderId === null ? 'font-semibold text-brand-700 dark:text-brand-400' : 'text-gray-600 dark:text-gray-300' ?>">
                    <?= ikon('home', 'h-4 w-4') ?>Dokumen
                </a>
                <?php foreach ($jejak as $j): ?>
                    <?= ikon('chevron-right', 'h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400') ?>
                    <a href="<?= esc($url(['folder' => $j['id'], 'q' => null]), 'attr') ?>" <?= $sasaran((int) $j['id'], (string) (int) $j['id']) ?>
                       class="dok-sasaran max-w-[180px] truncate rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700 <?= (int) $j['id'] === $folderId ? 'font-semibold text-brand-700 dark:text-brand-400' : 'text-gray-600 dark:text-gray-300' ?>"><?= esc($j['nama']) ?></a>
                <?php endforeach; ?>
            </nav>

            <a href="<?= site_url('admin/dokumen/penyimpanan') ?>" class="flex shrink-0 items-center gap-3 text-xs text-gray-500 hover:text-brand-700 dark:text-gray-400 dark:hover:text-brand-400" title="Lihat rincian penyimpanan">
                <progress class="progres w-28 sm:w-40 <?= $persen >= 90 ? 'progres-merah' : ($persen >= 70 ? 'progres-kuning' : '') ?>" value="<?= $persen ?>" max="100" aria-label="Penyimpanan terpakai <?= $persen ?> persen"></progress>
                <span>
                    <?= esc(dokumen_ukuran_manusia($pakai)) ?> dari <?= esc(dokumen_ukuran_manusia($kuotaByte)) ?> · <?= (int) ($pemakaian['total_berkas'] ?? 0) ?> berkas
                    <span class="hidden xl:inline">· maks. <?= esc(dokumen_ukuran_manusia($maksByte)) ?>/berkas</span>
                </span>
            </a>
        </div>

        <!-- Penyaring + pilihan tampilan -->
        <div class="alat-crud">
            <div class="alat-crud-baris">
                <form method="get" class="alat-crud-cari" role="search">
                    <?php if ($folderId !== null): ?><input type="hidden" name="folder" value="<?= (int) $folderId ?>"><?php endif; ?>
                    <input type="hidden" name="tampilan" value="<?= esc($tampilan, 'attr') ?>">
                    <label class="alat-crud-kotak">
                        <span class="sr-only">Cari dokumen</span>
                        <?= ikon('search', '') ?>
                        <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari di seluruh arsip…" class="inp">
                    </label>
                    <select name="kategori" data-autosubmit class="inp alat-crud-pilih" aria-label="Jenis berkas">
                        <option value="">Semua jenis</option>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= esc($k, 'attr') ?>"<?= $kategori === $k ? ' selected' : '' ?>><?= esc(ucfirst($k)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="urut" data-autosubmit class="inp alat-crud-pilih" aria-label="Urutkan menurut">
                        <option value="created_at"<?= $urut === 'created_at' ? ' selected' : '' ?>>Terbaru</option>
                        <option value="judul"<?= $urut === 'judul' ? ' selected' : '' ?>>Nama</option>
                        <option value="ukuran"<?= $urut === 'ukuran' ? ' selected' : '' ?>>Ukuran</option>
                        <option value="jml_unduh"<?= $urut === 'jml_unduh' ? ' selected' : '' ?>>Paling sering diunduh</option>
                    </select>
                    <select name="arah" data-autosubmit class="inp alat-crud-pilih" aria-label="Arah urutan">
                        <option value="DESC"<?= $arah === 'DESC' ? ' selected' : '' ?>>Turun</option>
                        <option value="ASC"<?= $arah === 'ASC' ? ' selected' : '' ?>>Naik</option>
                    </select>
                    <button type="submit" class="btn btn-netral btn-sm"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
                </form>

                <div class="alat-crud-tombol" role="group" aria-label="Pilihan tampilan">
                    <?php foreach ($tampilanList as $kunci => [$ik, $label, $ket]): ?>
                        <a href="<?= esc($url(['tampilan' => $kunci]), 'attr') ?>" class="tombol-saring inline-flex items-center gap-1.5" title="<?= esc($ket, 'attr') ?>"<?= $tampilan === $kunci ? ' aria-current="true"' : '' ?>>
                            <?= ikon($ik, 'h-4 w-4') ?><?= esc($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($cariGlobal): ?>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan hasil pencarian <b class="text-gray-900 dark:text-white">"<?= esc($q) ?>"</b> dari seluruh arsip.
                    <a href="<?= esc($url(['q' => null]), 'attr') ?>" class="font-medium text-brand-700 underline dark:text-brand-400">Kembali menelusuri folder</a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Bilah aksi massal -->
        <div x-show="adaPilihan()" x-cloak class="flex flex-wrap items-center gap-2 border-b border-brand-200 bg-brand-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-700">
            <span class="text-sm font-semibold text-brand-800 dark:text-white" x-text="teksPilihan()"></span>
            <button type="button" class="btn btn-utama btn-xs" @click="bukaPindah()"><?= ikon('folder-open', 'h-4 w-4') ?>Pindahkan</button>
            <form method="post" action="<?= site_url('admin/dokumen/hapus-massal') ?>"
                  data-confirm="Pindahkan dokumen terpilih ke tempat sampah? Masih bisa dipulihkan dari Sampah." data-confirm-judul="Buang ke sampah?" data-confirm-ya="Ya, buang" data-confirm-jenis="bahaya">
                <?= csrf_field() ?>
                <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
                <template x-for="id in pilih" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button type="submit" class="btn btn-bahaya btn-xs"><?= ikon('trash-bin', 'h-4 w-4') ?>Buang ke sampah</button>
            </form>
            <button type="button" class="btn btn-teks btn-xs ms-auto" @click="batalPilih()"><?= ikon('close', 'h-4 w-4') ?>Batal pilih</button>
        </div>

        <!-- Subfolder (tampilan kotak & daftar; tampilan ikon membaurkannya dengan berkas) -->
        <?php if ($subfolder !== [] && $tampilan !== 'ikon'): ?>
            <div class="grid grid-cols-1 gap-3 border-b border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 dark:border-gray-700">
                <?php foreach ($subfolder as $f): $fid = (int) $f['id']; ?>
                    <div class="dok-folder dok-sasaran group" <?= $sasaran($fid, (string) $fid) ?>>
                        <a href="<?= esc($url(['folder' => $fid, 'q' => null]), 'attr') ?>" draggable="false" class="flex min-w-0 flex-1 items-center gap-3">
                            <?= ikon('folder', 'h-9 w-9 shrink-0 text-yellow-400', 'solid') ?>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white" title="<?= esc($f['nama'], 'attr') ?>"><?= esc($f['nama']) ?></span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400"><?= (int) ($isiFolder[$fid] ?? 0) ?> berkas</span>
                            </span>
                        </a>
                        <div class="dok-sorot flex shrink-0 items-center gap-1"><?= $aksiFolder($f) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Isi -->
        <?php if ($rows === [] && ($tampilan !== 'ikon' || $subfolder === [])): ?>
            <?= ui_kosong([
                'ikon'  => $cariGlobal ? 'file-search' : 'folder-open',
                'judul' => $judulKosong,
                'teks'  => $cariGlobal ? 'Coba kata kunci lain atau ganti penyaring jenis berkas.' : 'Unggah berkas atau tambahkan tautan ke folder ini.',
                'aksi'  => $cariGlobal ? '' : '<button type="button" class="btn btn-utama" data-buka-dialog="dok-unggah">' . ikon('upload', 'h-4 w-4') . 'Unggah berkas</button>',
            ]) ?>

        <?php elseif ($tampilan === 'ikon'): ?>
            <!-- Tampilan ikon: folder dan berkas berbaur dalam satu petak padat, seperti penjelajah berkas di komputer. Folder didahulukan. -->
            <div class="grid grid-cols-3 gap-1 p-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8">
                <?php foreach ($subfolder as $f): $fid = (int) $f['id']; ?>
                    <div class="dok-butir dok-sasaran group" <?= $sasaran($fid, (string) $fid) ?>>
                        <a href="<?= esc($url(['folder' => $fid, 'q' => null]), 'attr') ?>" draggable="false" class="block p-2 text-center" title="<?= esc($f['nama'], 'attr') ?>">
                            <?= ikon('folder', 'mx-auto h-12 w-12 text-yellow-400', 'solid') ?>
                            <p class="mt-1 line-clamp-2 text-[11px] leading-tight font-medium break-words text-gray-700 dark:text-gray-200"><?= esc($f['nama']) ?></p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400"><?= (int) ($isiFolder[$fid] ?? 0) ?> berkas</p>
                        </a>
                        <?= $formBuangFolder($f, 'dok-sorot absolute end-1 top-1') ?>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($rows as $r):
                    $id  = (int) $r['id'];
                    $gbr = $sampul($r);
                    ?>
                    <div class="dok-butir group cursor-grab active:cursor-grabbing" <?= $butir($id) ?>>
                        <label class="dok-sorot absolute start-1 top-1 z-10 cursor-pointer"><?= $centang($r) ?></label>
                        <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false" class="block p-2 text-center" title="<?= esc($r['judul'], 'attr') ?>">
                            <span class="flex h-12 items-center justify-center">
                                <?php if ($gbr !== null): ?>
                                    <img src="<?= esc($gbr, 'attr') ?>" alt="" loading="lazy" draggable="false" class="h-12 w-12 rounded-lg border border-gray-200 object-cover dark:border-gray-600">
                                <?php else: ?>
                                    <?= $ikonDok($r, 'h-11 w-11') ?>
                                <?php endif; ?>
                            </span>
                            <p class="mt-1 line-clamp-2 text-[11px] leading-tight break-words text-gray-700 dark:text-gray-200"><?= esc($r['judul']) ?></p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400"><?= $r['tipe'] === 'tautan' ? esc(strtoupper((string) $r['penyedia'])) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($tampilan === 'grid'): ?>
            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <?php foreach ($rows as $r):
                    $id   = (int) $r['id'];
                    $kat  = (string) $r['kategori'];
                    $link = $r['tipe'] === 'tautan';
                    $gbr  = $sampul($r);
                    ?>
                    <div class="dok-kartu group cursor-grab active:cursor-grabbing" <?= $butir($id) ?>>
                        <label class="absolute start-2 top-2 z-10 cursor-pointer"><?= $centang($r) ?></label>
                        <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false" class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-700" tabindex="-1" aria-hidden="true">
                            <?php if ($gbr !== null): ?>
                                <img src="<?= esc($gbr, 'attr') ?>" alt="" loading="lazy" draggable="false" class="h-full w-full object-cover">
                            <?php else: ?>
                                <?= $ikonDok($r, 'h-12 w-12') ?>
                            <?php endif; ?>
                        </a>
                        <div class="flex flex-1 flex-col p-3">
                            <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false" class="truncate text-sm font-medium text-gray-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-400" title="<?= esc($r['judul'], 'attr') ?>"><?= esc($r['judul']) ?></a>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                <span class="lencana <?= $lencanaKategori[$kat] ?? 'lencana-abu' ?>"><?= esc(strtoupper($r['ekstensi'] ?: $kat)) ?></span>
                                <span class="lencana <?= $lencanaVisib[$r['visibilitas']] ?? 'lencana-abu' ?>"><?= esc($labelVisib[$r['visibilitas']] ?? $r['visibilitas']) ?></span>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <?= $link ? 'Tautan ' . esc((string) $r['penyedia']) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?> · <?= esc($tgl($r['created_at'])) ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 border-t border-gray-200 px-3 py-2 dark:border-gray-700"><?= $aksiDok($r) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col" class="w-4">
                                <label class="cursor-pointer"><span class="sr-only">Pilih semua dokumen di halaman ini</span>
                                    <input type="checkbox" class="cek" :checked="semuaDipilih()" @change="centangSemuaDok()"></label>
                            </th>
                            <th scope="col">Nama</th>
                            <th scope="col" class="hidden md:table-cell">Jenis</th>
                            <th scope="col" class="hidden lg:table-cell">Akses</th>
                            <th scope="col" class="hidden text-right sm:table-cell">Ukuran</th>
                            <th scope="col" class="hidden lg:table-cell">Diunggah</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $id  = (int) $r['id'];
                            $kat = (string) $r['kategori'];
                            ?>
                            <tr class="dok-baris cursor-grab active:cursor-grabbing" <?= $butir($id) ?>>
                                <td><label class="cursor-pointer"><?= $centang($r) ?></label></td>
                                <td>
                                    <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false" class="group/nama flex items-center gap-2">
                                        <?= $ikonDok($r, 'h-5 w-5') ?>
                                        <span class="max-w-[280px] truncate font-medium text-gray-900 group-hover/nama:text-brand-700 dark:text-white dark:group-hover/nama:text-brand-400"><?= esc($r['judul']) ?></span>
                                    </a>
                                </td>
                                <td class="hidden md:table-cell"><span class="lencana <?= $lencanaKategori[$kat] ?? 'lencana-abu' ?>"><?= esc(strtoupper($r['ekstensi'] ?: $kat)) ?></span></td>
                                <td class="hidden lg:table-cell"><span class="lencana <?= $lencanaVisib[$r['visibilitas']] ?? 'lencana-abu' ?>"><?= esc($labelVisib[$r['visibilitas']] ?? $r['visibilitas']) ?></span></td>
                                <td class="hidden text-right tabular-nums sm:table-cell"><?= $r['tipe'] === 'tautan' ? '—' : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?></td>
                                <td class="hidden text-xs lg:table-cell">
                                    <?= esc($tgl($r['created_at'])) ?><br>
                                    <span class="text-gray-500 dark:text-gray-400"><?= esc((string) ($r['pengunggah'] ?? '—')) ?></span>
                                </td>
                                <td><div class="flex items-center justify-end gap-1.5"><?= $aksiDok($r) ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tautanHal !== ''): ?><div class="kartu-kaki"><?= $tautanHal ?></div><?php endif; ?>
    </section>

    <!-- Petunjuk saat menyeret -->
    <div x-show="sedangSeret()" x-cloak class="pointer-events-none fixed bottom-5 left-1/2 z-[60] -translate-x-1/2 rounded-full bg-gray-900 px-4 py-2 text-sm text-white shadow-sm dark:bg-gray-700" x-text="teksSeret()"></div>

    <!-- Dialog: pindahkan dokumen terpilih (milik komponen — butuh daftar centang) -->
    <div class="dialog" :data-buka="pindahTerbuka" @keydown.escape="tutupPindah()" role="dialog" aria-modal="true" aria-labelledby="judul-pindah-dok">
        <div class="dialog-latar" @click="tutupPindah()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-sm" tabindex="-1">
            <div class="dialog-kepala">
                <h3 id="judul-pindah-dok" class="dialog-judul">Pindahkan dokumen</h3>
                <button type="button" class="tombol-tutup" @click="tutupPindah()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" action="<?= site_url('admin/dokumen/pindah') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
                <template x-for="id in pilih" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <div class="dialog-isi">
                    <p class="text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold text-gray-900 dark:text-white" x-text="teksPilihan()"></span> akan dipindahkan.</p>
                    <div>
                        <label class="lbl" for="dok-pindah-tujuan">Folder tujuan</label>
                        <select id="dok-pindah-tujuan" name="tujuan" class="inp">
                            <option value="0">Dokumen (folder utama)</option>
                            <?php foreach ($folderOpts as $fid => $flabel): ?>
                                <option value="<?= (int) $fid ?>"><?= esc($flabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="dialog-kaki">
                    <button type="button" class="btn btn-netral" @click="tutupPindah()">Batal</button>
                    <button type="submit" class="btn btn-utama"><?= ikon('folder-open', 'h-4 w-4') ?>Pindahkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= Dialog kit ================= -->
<?= ui_dialog_buka(['nama' => 'dok-folder-baru', 'judul' => 'Folder baru', 'ukuran' => 'sm']) ?>
<form method="post" action="<?= site_url('admin/dokumen/folder') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="parent_id" value="<?= (int) $folderId ?>">
    <div class="dialog-isi">
        <p class="text-sm text-gray-500 dark:text-gray-400">Dibuat di: <b class="text-gray-900 dark:text-white"><?= esc($folderKini['nama'] ?? 'Dokumen (utama)') ?></b></p>
        <div>
            <label class="lbl" for="dok-fb-nama">Nama folder <span class="text-red-600 dark:text-red-400">*</span></label>
            <input id="dok-fb-nama" name="nama" required maxlength="150" class="inp" placeholder="mis. Proposal Kegiatan 2026">
        </div>
        <div>
            <label class="lbl" for="dok-fb-ket">Keterangan</label>
            <input id="dok-fb-ket" name="deskripsi" maxlength="255" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-fb-akses">Akses</label>
            <select id="dok-fb-akses" name="visibilitas" class="inp"><?= $opsiAkses(true) ?></select>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-sukses"><?= ikon('folder-plus', 'h-4 w-4') ?>Buat folder</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?= ui_dialog_buka(['nama' => 'dok-unggah', 'judul' => 'Unggah berkas']) ?>
<form method="post" action="<?= site_url('admin/dokumen/unggah') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
    <div class="dialog-isi">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Ke folder: <b class="text-gray-900 dark:text-white"><?= esc($folderKini['nama'] ?? 'Dokumen (utama)') ?></b> · maksimal <?= esc(dokumen_ukuran_manusia($maksByte)) ?> per berkas
        </p>
        <div x-data="unggahDokumen">
            <label class="dok-zona" :data-seret="seret" @dragover.prevent="seretMasuk()" @dragleave.prevent="seretKeluar()" @drop.prevent="jatuhkanBerkas($event)">
                <?= ikon('upload', 'mx-auto h-10 w-10 text-gray-500 dark:text-gray-400') ?>
                <span class="mt-2 block text-sm text-gray-600 dark:text-gray-300">Seret berkas ke sini, atau <span class="font-semibold text-brand-700 dark:text-brand-400">pilih dari perangkat</span></span>
                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Word, Excel, PowerPoint, PDF, gambar, zip — boleh banyak sekaligus</span>
                <input type="file" name="berkas[]" multiple class="sr-only" @change="catatBerkasUnggah()">
            </label>
            <p x-show="adaBerkas()" x-cloak class="mt-2 text-center text-xs font-medium text-brand-700 dark:text-brand-400" x-text="teksBerkas()"></p>
        </div>
        <div>
            <label class="lbl" for="dok-ung-akses">Akses berkas</label>
            <select id="dok-ung-akses" name="visibilitas" class="inp"><?= $opsiAkses(true) ?></select>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Unggah</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?= ui_dialog_buka(['nama' => 'dok-tautan', 'judul' => 'Tambah tautan', 'ukuran' => 'sm']) ?>
<form method="post" action="<?= site_url('admin/dokumen/tautan') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
    <div class="dialog-isi">
        <p class="text-sm text-gray-500 dark:text-gray-400">Untuk video YouTube, berkas Google Drive, atau alamat web lain. Video sengaja tidak disimpan di server agar ruang penyimpanan tidak cepat penuh.</p>
        <div>
            <label class="lbl" for="dok-tt-url">Alamat tautan <span class="text-red-600 dark:text-red-400">*</span></label>
            <input id="dok-tt-url" name="url_eksternal" required type="url" placeholder="https://youtu.be/…" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-tt-judul">Judul</label>
            <input id="dok-tt-judul" name="judul" maxlength="200" placeholder="mis. Dokumentasi Upacara HUT RI" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-tt-ket">Keterangan</label>
            <input id="dok-tt-ket" name="deskripsi" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-tt-akses">Akses</label>
            <select id="dok-tt-akses" name="visibilitas" class="inp"><?= $opsiAkses(false) ?></select>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('link', 'h-4 w-4') ?>Simpan tautan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?php foreach (['dok-ubah' => false, 'dok-ubah-tautan' => true] as $namaDialog => $untukTautan): ?>
    <?= ui_dialog_buka(['nama' => $namaDialog, 'judul' => $untukTautan ? 'Ubah tautan' : 'Ubah dokumen', 'ukuran' => 'sm']) ?>
    <form method="post" action="<?= $base ?>" data-aksi-pemicu>
        <?= csrf_field() ?>
        <div class="dialog-isi">
            <div>
                <label class="lbl" for="<?= $namaDialog ?>-judul">Judul <span class="text-red-600 dark:text-red-400">*</span></label>
                <input id="<?= $namaDialog ?>-judul" name="judul" data-isi-pemicu="judul" required maxlength="200" class="inp">
            </div>
            <?php if ($untukTautan): ?>
                <div>
                    <label class="lbl" for="<?= $namaDialog ?>-url">Alamat tautan</label>
                    <input id="<?= $namaDialog ?>-url" name="url_eksternal" data-isi-pemicu="url" type="url" class="inp">
                </div>
            <?php endif; ?>
            <div>
                <label class="lbl" for="<?= $namaDialog ?>-ket">Keterangan</label>
                <textarea id="<?= $namaDialog ?>-ket" name="deskripsi" data-isi-pemicu="deskripsi" rows="2" class="inp"></textarea>
            </div>
            <div>
                <label class="lbl" for="<?= $namaDialog ?>-akses">Akses</label>
                <select id="<?= $namaDialog ?>-akses" name="visibilitas" data-isi-pemicu="visib" class="inp"><?= $opsiAkses(true) ?></select>
            </div>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
        </div>
    </form>
    <?= ui_dialog_tutup() ?>
<?php endforeach; ?>

<?= ui_dialog_buka(['nama' => 'dok-folder-ubah', 'judul' => 'Ubah folder', 'ukuran' => 'sm']) ?>
<form method="post" action="<?= $base ?>" data-aksi-pemicu>
    <?= csrf_field() ?>
    <input type="hidden" name="pindah" value="1">
    <div class="dialog-isi">
        <div>
            <label class="lbl" for="dok-fu-nama">Nama folder <span class="text-red-600 dark:text-red-400">*</span></label>
            <input id="dok-fu-nama" name="nama" data-isi-pemicu="nama" required maxlength="150" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-fu-ket">Keterangan</label>
            <input id="dok-fu-ket" name="deskripsi" data-isi-pemicu="deskripsi" maxlength="255" class="inp">
        </div>
        <div>
            <label class="lbl" for="dok-fu-akses">Akses</label>
            <select id="dok-fu-akses" name="visibilitas" data-isi-pemicu="visib" class="inp"><?= $opsiAkses(false) ?></select>
        </div>
        <div>
            <label class="lbl" for="dok-fu-induk">Letak folder</label>
            <select id="dok-fu-induk" name="induk_baru" data-isi-pemicu="induk" class="inp">
                <option value="0">Dokumen (folder utama)</option>
                <?php foreach ($folderOpts as $fid => $flabel): ?>
                    <option value="<?= (int) $fid ?>"><?= esc($flabel) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Ganti untuk memindahkan folder ini beserta isinya.</p>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/dokumen.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/dokumen.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
