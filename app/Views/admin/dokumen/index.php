<?php

/**
 * Penjelajah Manajemen Dokumen.
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
 * @var string                        $tampilan     grid|daftar
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
        'folder'   => $ganti['folder']   ?? $folderId,
        'q'        => $ganti['q']        ?? $q,
        'kategori' => $ganti['kategori'] ?? $kategori,
        'urut'     => $ganti['urut']     ?? $urut,
        'arah'     => $ganti['arah']     ?? $arah,
        'tampilan' => $ganti['tampilan'] ?? $tampilan,
        'per'      => $ganti['per']      ?? $per,
    ], static fn ($v) => $v !== null && $v !== '');

    return $base . ($p ? '?' . http_build_query($p) : '');
};

/** Lencana warna per kategori. */
$warnaKategori = [
    'pdf'         => 'bg-red-50 text-red-700',
    'dokumen'     => 'bg-blue-50 text-blue-700',
    'spreadsheet' => 'bg-emerald-50 text-emerald-700',
    'presentasi'  => 'bg-orange-50 text-orange-700',
    'gambar'      => 'bg-violet-50 text-violet-700',
    'audio'       => 'bg-pink-50 text-pink-700',
    'video'       => 'bg-rose-50 text-rose-700',
    'arsip'       => 'bg-amber-50 text-amber-700',
    'lainnya'     => 'bg-slate-100 text-slate-600',
];

/** Warna ikon besar pada tampilan ikon — satu warna per jenis berkas
 *  supaya jenis dokumen bisa dikenali sekilas tanpa membaca label. */
$warnaIkon = [
    'pdf'         => 'text-red-500',
    'dokumen'     => 'text-blue-500',
    'spreadsheet' => 'text-emerald-500',
    'presentasi'  => 'text-orange-500',
    'gambar'      => 'text-violet-500',
    'audio'       => 'text-pink-500',
    'video'       => 'text-rose-500',
    'arsip'       => 'text-amber-500',
    'lainnya'     => 'text-slate-400',
];

/** Ikon garis per kategori (path SVG). */
$ikonKategori = [
    'pdf'         => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z M9 13h6m-6 4h4',
    'dokumen'     => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'spreadsheet' => 'M3 10h18M3 14h18m-9-8v16M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'presentasi'  => 'M7 21h10M12 17v4M3 4h18v10a2 2 0 01-2 2H5a2 2 0 01-2-2V4z',
    'gambar'      => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    'audio'       => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z',
    'video'       => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'arsip'       => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    'lainnya'     => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
];

$labelVisib = ['privat' => 'Privat', 'link' => 'Lewat tautan', 'publik' => 'Publik'];
$warnaVisib = [
    'privat' => 'bg-slate-100 text-slate-600',
    'link'   => 'bg-sky-50 text-sky-700',
    'publik' => 'bg-emerald-50 text-emerald-700',
];

$tgl    = static fn ($d) => $d ? date('d/m/Y H:i', strtotime((string) $d)) : '—';
$pakai  = (int) ($pemakaian['total_byte'] ?? 0);
$persen = $kuotaByte > 0 ? min(100, round($pakai / $kuotaByte * 100)) : 0;
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_v2',
    'helpTitle' => 'Manajemen Dokumen',
    'helpBody'  => '<p>Simpan berkas kerja (Word, Excel, PowerPoint, PDF, foto, dan lainnya) dalam <b>folder bertingkat</b> seperti Google Drive, lalu buka kembali kapan saja dari komputer maupun HP.</p>
        <p class="mt-1">• <b>Pindahkan berkas dengan menyeretnya</b> ke folder tujuan — bisa juga ke nama folder di jalur atas untuk memindahkan keluar. Centang beberapa berkas dulu, lalu seret salah satunya, maka semuanya ikut pindah.<br>
        • Tiga <b>tampilan</b> di kanan penyaring: <b>ikon</b> (padat, seperti penjelajah berkas di komputer), <b>kotak</b> (kartu besar bergambar), dan <b>daftar</b> (tabel rinci).<br>
        • <b>Unggah</b> bisa banyak berkas sekaligus — seret ke kotak unggah atau klik untuk memilih.<br>
        • <b>Video</b> tidak disimpan di server (memakan ruang); pakai tombol <b>Tambah Tautan</b> untuk menempelkan tautan YouTube atau Google Drive.<br>
        • <b>Privat</b> = hanya bisa dibuka setelah masuk ke panel ini. <b>Lewat tautan</b> & <b>Publik</b> dipakai saat berkas dibagikan ke guru.<br>
        • <b>Pencarian</b> berlaku ke seluruh arsip, bukan hanya folder yang sedang dibuka.<br>
        • Berkas yang dihapus masuk <b>Tempat Sampah</b> dulu, jadi masih bisa dipulihkan.</p>',
]) ?>

<div x-data="{
        folderOpen:false, unggahOpen:false, tautanOpen:false,
        editOpen:false, editAction:'', editJudul:'', editDeskripsi:'', editVisib:'privat', editUrl:'', editTipe:'berkas',
        folderEditOpen:false, folderAction:'', folderNama:'', folderDeskripsi:'', folderVisib:'privat',
        pindahOpen:false, pilih:[], seret:false,
        dndIds:[], dndTarget:null, dndTujuan:0,
        get adaPilihan(){ return this.pilih.length > 0 },
        get sedangSeret(){ return this.dndIds.length > 0 },
        toggle(id){ const i=this.pilih.indexOf(id); i<0 ? this.pilih.push(id) : this.pilih.splice(i,1) },
        pilihSemua(ids){ this.pilih = this.pilih.length === ids.length ? [] : [...ids] },

        /* --- Seret dokumen ke folder ---
           Kalau dokumen yang diseret termasuk yang sedang dicentang, SEMUA
           yang tercentang ikut pindah — itu yang diharapkan orang setelah
           memilih beberapa berkas. Kalau tidak, cuma yang diseret. */
        mulaiSeret(id, ev){
            this.dndIds = this.pilih.includes(id) ? [...this.pilih] : [id];
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', this.dndIds.join(','));
        },
        selesaiSeret(){ this.dndIds = []; this.dndTarget = null },
        lewatiFolder(kunci, ev){
            if (!this.sedangSeret) return;
            ev.dataTransfer.dropEffect = 'move';
            this.dndTarget = kunci;
        },
        jatuhkan(folderId){
            if (!this.sedangSeret) return;
            this.dndTujuan = folderId === null ? 0 : folderId;
            this.dndTarget = null;
            this.$nextTick(() => this.$refs.dndForm.submit());
        },
        bukaEdit(el){ const d=el.dataset; this.editAction=d.action; this.editJudul=d.judul; this.editDeskripsi=d.deskripsi;
                      this.editVisib=d.visib; this.editUrl=d.url; this.editTipe=d.tipe; this.editOpen=true },
        bukaFolderEdit(el){ const d=el.dataset; this.folderAction=d.action; this.folderNama=d.nama;
                            this.folderDeskripsi=d.deskripsi; this.folderVisib=d.visib; this.folderEditOpen=true }
     }">

    <!-- Formulir tersembunyi untuk pemindahan lewat seret-lepas.
         Memakai rute yang sama dengan tombol "Pindahkan" agar aturan
         keamanannya satu pintu (tak ada jalur khusus untuk drag-drop). -->
    <form method="post" action="<?= site_url('admin/dokumen/pindah') ?>" x-ref="dndForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
        <input type="hidden" name="tujuan" :value="dndTujuan">
        <template x-for="id in dndIds" :key="'dnd' + id"><input type="hidden" name="ids[]" :value="id"></template>
    </form>

    <!-- ============ Bar atas: remah jejak + aksi ============ -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">

            <!-- Remah jejak -->
            <nav class="flex items-center gap-1 text-sm flex-1 min-w-0 flex-wrap">
                <a href="<?= esc($url(['folder' => null, 'q' => null])) ?>"
                   @dragover.prevent="lewatiFolder('akar', $event)" @dragleave="dndTarget=null" @drop.prevent="jatuhkan(null)"
                   :class="dndTarget === 'akar' ? 'ring-2 ring-brand-500 bg-brand-50' : ''"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-slate-100 <?= $folderId === null ? 'font-semibold text-brand-700' : 'text-slate-600' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dokumen
                </a>
                <?php foreach ($jejak as $j) { ?>
                    <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <a href="<?= esc($url(['folder' => $j['id'], 'q' => null])) ?>"
                       @dragover.prevent="lewatiFolder(<?= (int) $j['id'] ?>, $event)" @dragleave="dndTarget=null" @drop.prevent="jatuhkan(<?= (int) $j['id'] ?>)"
                       :class="dndTarget === <?= (int) $j['id'] ?> ? 'ring-2 ring-brand-500 bg-brand-50' : ''"
                       class="px-2 py-1 rounded-lg hover:bg-slate-100 truncate max-w-[180px] <?= (int) $j['id'] === $folderId ? 'font-semibold text-brand-700' : 'text-slate-600' ?>"><?= esc($j['nama']) ?></a>
                <?php } ?>
            </nav>

            <!-- Aksi utama -->
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="unggahOpen=true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Unggah Berkas
                </button>
                <button type="button" @click="folderOpen=true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m3-3H9m-6 5V7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    Folder Baru
                </button>
                <button type="button" @click="tautanOpen=true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m8.656-4.828l1.5-1.5a4 4 0 115.656 5.656l-3 3a4 4 0 01-5.656 0"/></svg>
                    Tambah Tautan
                </button>
                <a href="<?= site_url('admin/dokumen/sampah') ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:border-red-200 hover:text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Sampah<?= $jmlSampah > 0 ? ' (' . $jmlSampah . ')' : '' ?>
                </a>
            </div>
        </div>

        <!-- Penyimpanan terpakai -->
        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-500">
            <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden max-w-xs">
                <div class="h-full rounded-full <?= $persen >= 90 ? 'bg-red-500' : ($persen >= 70 ? 'bg-amber-500' : 'bg-brand-600') ?>"
                     style="width: <?= $persen ?>%"></div>
            </div>
            <a href="<?= site_url('admin/dokumen/penyimpanan') ?>" class="hover:text-brand-700 hover:underline">
                <?= esc(dokumen_ukuran_manusia($pakai)) ?> dari <?= esc(dokumen_ukuran_manusia($kuotaByte)) ?> terpakai
                (<?= (int) ($pemakaian['total_berkas'] ?? 0) ?> berkas)
            </a>
            <span class="ml-auto hidden sm:inline">Batas per berkas: <?= esc(dokumen_ukuran_manusia($maksByte)) ?></span>
        </div>
    </div>

    <!-- ============ Penyaring ============ -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 mb-4">
        <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
            <?php if ($folderId !== null) { ?><input type="hidden" name="folder" value="<?= (int) $folderId ?>"><?php } ?>
            <input type="hidden" name="tampilan" value="<?= esc($tampilan) ?>">

            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Cari di seluruh arsip…"
                       class="w-full pl-10 pr-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-brand-200 focus:border-brand-500">
            </div>

            <select name="kategori" class="px-3 py-2 rounded-xl border border-slate-300 text-sm">
                <option value="">Semua jenis</option>
                <?php foreach ($kategoriList as $k) { ?>
                    <option value="<?= esc($k) ?>" <?= $kategori === $k ? 'selected' : '' ?>><?= esc(ucfirst($k)) ?></option>
                <?php } ?>
            </select>

            <select name="urut" class="px-3 py-2 rounded-xl border border-slate-300 text-sm">
                <option value="created_at" <?= $urut === 'created_at' ? 'selected' : '' ?>>Terbaru</option>
                <option value="judul"      <?= $urut === 'judul' ? 'selected' : '' ?>>Nama</option>
                <option value="ukuran"     <?= $urut === 'ukuran' ? 'selected' : '' ?>>Ukuran</option>
                <option value="jml_unduh"  <?= $urut === 'jml_unduh' ? 'selected' : '' ?>>Paling sering diunduh</option>
            </select>
            <select name="arah" class="px-3 py-2 rounded-xl border border-slate-300 text-sm">
                <option value="DESC" <?= $arah === 'DESC' ? 'selected' : '' ?>>Turun</option>
                <option value="ASC"  <?= $arah === 'ASC' ? 'selected' : '' ?>>Naik</option>
            </select>

            <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-medium hover:bg-slate-900">Terapkan</button>

            <div class="flex items-center gap-1 ml-auto">
                <a href="<?= esc($url(['tampilan' => 'ikon'])) ?>" title="Tampilan ikon — padat, seperti penjelajah berkas Windows"
                   class="p-2 rounded-lg border <?= $tampilan === 'ikon' ? 'bg-brand-50 border-brand-300 text-brand-700' : 'border-slate-300 text-slate-500 hover:bg-slate-50' ?>">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h4v4H4V4zm6 0h4v4h-4V4zm6 0h4v4h-4V4zM4 10h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 16h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z"/></svg>
                </a>
                <a href="<?= esc($url(['tampilan' => 'grid'])) ?>" title="Tampilan kotak — kartu besar bergambar"
                   class="p-2 rounded-lg border <?= $tampilan === 'grid' ? 'bg-brand-50 border-brand-300 text-brand-700' : 'border-slate-300 text-slate-500 hover:bg-slate-50' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </a>
                <a href="<?= esc($url(['tampilan' => 'daftar'])) ?>" title="Tampilan daftar — tabel rinci"
                   class="p-2 rounded-lg border <?= $tampilan === 'daftar' ? 'bg-brand-50 border-brand-300 text-brand-700' : 'border-slate-300 text-slate-500 hover:bg-slate-50' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </a>
            </div>
        </form>

        <?php if ($cariGlobal) { ?>
            <p class="mt-2 text-xs text-slate-500">
                Menampilkan hasil pencarian <b>"<?= esc($q) ?>"</b> dari seluruh arsip.
                <a href="<?= esc($url(['q' => null])) ?>" class="text-brand-700 underline">Kembali menelusuri folder</a>
            </p>
        <?php } ?>
    </div>

    <!-- ============ Bar aksi massal ============ -->
    <div x-show="adaPilihan" x-cloak x-transition.opacity.duration.200ms
         class="bg-brand-50 border border-brand-200 rounded-2xl p-3 mb-4 flex items-center gap-3 flex-wrap">
        <span class="text-sm font-semibold text-brand-800"><span x-text="pilih.length"></span> dokumen dipilih</span>
        <button type="button" @click="pindahOpen=true"
                class="px-3 py-1.5 rounded-lg bg-white border border-brand-300 text-sm font-medium text-brand-700 hover:bg-brand-100">Pindahkan</button>
        <form method="post" action="<?= site_url('admin/dokumen/hapus-massal') ?>"
              onsubmit="return confirm('Pindahkan dokumen terpilih ke tempat sampah?')">
            <?= csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
            <template x-for="id in pilih" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <button class="px-3 py-1.5 rounded-lg bg-white border border-red-300 text-sm font-medium text-red-700 hover:bg-red-50">Buang ke Sampah</button>
        </form>
        <button type="button" @click="pilih=[]" class="text-sm text-slate-500 hover:text-slate-700 ml-auto">Batal pilih</button>
    </div>

    <!-- Petunjuk saat menyeret -->
    <div x-show="sedangSeret" x-cloak
         class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] px-4 py-2 rounded-full bg-slate-900 text-white text-sm shadow-lg pointer-events-none">
        Lepaskan di atas folder untuk memindahkan <span x-text="dndIds.length"></span> dokumen
    </div>

    <!-- ============ Subfolder ============ -->
    <?php if ($subfolder !== [] && $tampilan !== 'ikon') { ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-5">
            <?php foreach ($subfolder as $f) { ?>
                <div class="group relative rounded-xl border transition"
                     @dragover.prevent="lewatiFolder(<?= (int) $f['id'] ?>, $event)"
                     @dragleave="dndTarget=null"
                     @drop.prevent="jatuhkan(<?= (int) $f['id'] ?>)"
                     :class="dndTarget === <?= (int) $f['id'] ?>
                        ? 'border-brand-500 ring-2 ring-brand-300 bg-brand-50 scale-[1.02]'
                        : 'bg-white border-slate-200 hover:border-brand-300 hover:shadow-sm'">
                    <a href="<?= esc($url(['folder' => $f['id'], 'q' => null])) ?>" class="block p-3">
                        <div class="flex items-start gap-2">
                            <svg class="w-9 h-9 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm text-slate-800 truncate" title="<?= esc($f['nama']) ?>"><?= esc($f['nama']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5"><?= (int) ($isiFolder[(int) $f['id']] ?? 0) ?> berkas</p>
                            </div>
                        </div>
                    </a>
                    <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1">
                        <button type="button" title="Ubah folder"
                                data-action="<?= site_url('admin/dokumen/folder/' . $f['id']) ?>"
                                data-nama="<?= esc($f['nama'], 'attr') ?>"
                                data-deskripsi="<?= esc((string) $f['deskripsi'], 'attr') ?>"
                                data-visib="<?= esc($f['visibilitas'], 'attr') ?>"
                                @click="bukaFolderEdit($el)"
                                class="p-1 rounded bg-white/90 border border-slate-200 text-slate-500 hover:text-brand-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <form method="post" action="<?= site_url('admin/dokumen/folder/' . $f['id'] . '/bagikan') ?>"
                              onsubmit="return confirm('Buat tautan berbagi untuk folder ini beserta isinya? Siapa pun yang memegang tautannya bisa membuka isi folder.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="boleh_unduh" value="1">
                            <button class="p-1 rounded bg-white/90 border border-slate-200 text-slate-500 hover:text-emerald-700" title="Bagikan folder">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342a3 3 0 100-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684zm0-12.632a3 3 0 105.368-2.684 3 3 0 00-5.368 2.684z"/></svg>
                            </button>
                        </form>
                        <form method="post" action="<?= site_url('admin/dokumen/folder/' . $f['id'] . '/hapus') ?>"
                              onsubmit="return confirm('Buang folder ini beserta SELURUH isinya ke tempat sampah?')">
                            <?= csrf_field() ?>
                            <button class="p-1 rounded bg-white/90 border border-slate-200 text-slate-500 hover:text-red-700" title="Buang folder">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- ============ Daftar dokumen ============ -->
    <?php if ($rows === [] && ($tampilan !== 'ikon' || $subfolder === [])) { ?>
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            <p class="mt-3 text-slate-500 text-sm">
                <?= $cariGlobal ? 'Tidak ada dokumen yang cocok dengan pencarian.' : 'Folder ini masih kosong.' ?>
            </p>
            <?php if (! $cariGlobal) { ?>
                <button type="button" @click="unggahOpen=true" class="mt-3 px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800">Unggah berkas pertama</button>
            <?php } ?>
        </div>

    <?php } elseif ($tampilan === 'ikon') { ?>
        <!-- Tampilan ikon: folder dan berkas berbaur dalam satu petak padat,
             seperti penjelajah berkas di komputer. Folder didahulukan. -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3">
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-1">

                <?php foreach ($subfolder as $f) { ?>
                    <div class="group relative rounded-lg transition"
                         @dragover.prevent="lewatiFolder(<?= (int) $f['id'] ?>, $event)"
                         @dragleave="dndTarget=null"
                         @drop.prevent="jatuhkan(<?= (int) $f['id'] ?>)"
                         :class="dndTarget === <?= (int) $f['id'] ?> ? 'bg-brand-100 ring-2 ring-brand-400' : 'hover:bg-slate-100'">
                        <a href="<?= esc($url(['folder' => $f['id'], 'q' => null])) ?>" draggable="false"
                           class="block p-2 text-center" title="<?= esc($f['nama']) ?>">
                            <svg class="w-12 h-12 mx-auto text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
                            <p class="mt-1 text-[11px] leading-tight text-slate-700 font-medium break-words line-clamp-2"><?= esc($f['nama']) ?></p>
                            <p class="text-[10px] text-slate-400"><?= (int) ($isiFolder[(int) $f['id']] ?? 0) ?> berkas</p>
                        </a>
                        <form method="post" action="<?= site_url('admin/dokumen/folder/' . $f['id'] . '/hapus') ?>"
                              class="absolute top-1 right-1 hidden group-hover:block"
                              onsubmit="return confirm('Buang folder ini beserta SELURUH isinya ke tempat sampah?')">
                            <?= csrf_field() ?>
                            <button class="p-1 rounded bg-white/90 border border-slate-200 text-slate-400 hover:text-red-700" title="Buang folder">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                <?php } ?>

                <?php foreach ($rows as $r) {
                    $id   = (int) $r['id'];
                    $kat  = (string) $r['kategori'];
                    $link = $r['tipe'] === 'tautan';
                    ?>
                    <div class="group relative rounded-lg transition cursor-grab active:cursor-grabbing"
                         draggable="true"
                         @dragstart="mulaiSeret(<?= $id ?>, $event)" @dragend="selesaiSeret()"
                         :class="{ 'bg-brand-100 ring-2 ring-brand-400': pilih.includes(<?= $id ?>),
                                   'hover:bg-slate-100': !pilih.includes(<?= $id ?>),
                                   'opacity-40': dndIds.includes(<?= $id ?>) }">

                        <label class="absolute top-1 left-1 z-10 cursor-pointer opacity-0 group-hover:opacity-100"
                               :class="pilih.includes(<?= $id ?>) ? 'opacity-100' : ''">
                            <input type="checkbox" :checked="pilih.includes(<?= $id ?>)" @change="toggle(<?= $id ?>)"
                                   class="w-3.5 h-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        </label>

                        <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false"
                           class="block p-2 text-center" title="<?= esc($r['judul']) ?>">
                            <div class="h-12 flex items-center justify-center">
                                <?php if ($r['thumb']) { ?>
                                    <img src="<?= site_url('admin/dokumen/thumb/' . $id) ?>" alt="" loading="lazy" draggable="false"
                                         class="h-12 w-12 object-cover rounded border border-slate-200">
                                <?php } elseif ($link && $r['penyedia'] === 'youtube' && ($sampul = dokumen_thumb_url_eksternal((string) $r['url_eksternal']))) { ?>
                                    <img src="<?= esc($sampul) ?>" alt="" loading="lazy" draggable="false"
                                         class="h-12 w-12 object-cover rounded border border-slate-200">
                                <?php } else { ?>
                                    <svg class="w-11 h-11 <?= $warnaIkon[$kat] ?? $warnaIkon['lainnya'] ?>" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= esc($ikonKategori[$kat] ?? $ikonKategori['lainnya']) ?>"/></svg>
                                <?php } ?>
                            </div>
                            <p class="mt-1 text-[11px] leading-tight text-slate-700 break-words line-clamp-2"><?= esc($r['judul']) ?></p>
                            <p class="text-[10px] text-slate-400">
                                <?= $link ? esc(strtoupper((string) $r['penyedia'])) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                            </p>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>

    <?php } elseif ($tampilan === 'grid') { ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <?php foreach ($rows as $r) {
                $id   = (int) $r['id'];
                $kat  = (string) $r['kategori'];
                $link = $r['tipe'] === 'tautan';
                ?>
                <div class="group relative bg-white rounded-xl border border-slate-200 hover:shadow-md hover:border-brand-300 transition overflow-hidden cursor-grab active:cursor-grabbing"
                     draggable="true"
                     @dragstart="mulaiSeret(<?= $id ?>, $event)" @dragend="selesaiSeret()"
                     :class="{ 'ring-2 ring-brand-500 border-brand-400': pilih.includes(<?= $id ?>),
                               'opacity-40': dndIds.includes(<?= $id ?>) }">

                    <label class="absolute top-2 left-2 z-10 cursor-pointer">
                        <input type="checkbox" :checked="pilih.includes(<?= $id ?>)" @change="toggle(<?= $id ?>)"
                               class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    </label>

                    <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" draggable="false"
                       class="block aspect-[4/3] bg-slate-50 flex items-center justify-center overflow-hidden">
                        <?php if ($r['thumb']) { ?>
                            <img src="<?= site_url('admin/dokumen/thumb/' . $id) ?>" alt="" loading="lazy" draggable="false" class="w-full h-full object-cover">
                        <?php } elseif ($link && $r['penyedia'] === 'youtube' && ($sampul = dokumen_thumb_url_eksternal((string) $r['url_eksternal']))) { ?>
                            <img src="<?= esc($sampul) ?>" alt="" loading="lazy" draggable="false" class="w-full h-full object-cover">
                        <?php } else { ?>
                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= esc($ikonKategori[$kat] ?? $ikonKategori['lainnya']) ?>"/></svg>
                        <?php } ?>
                    </a>

                    <div class="p-2.5">
                        <p class="text-sm font-medium text-slate-800 truncate" title="<?= esc($r['judul']) ?>"><?= esc($r['judul']) ?></p>
                        <div class="flex items-center gap-1 mt-1 flex-wrap">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $warnaKategori[$kat] ?? $warnaKategori['lainnya'] ?>"><?= esc(strtoupper($r['ekstensi'] ?: $kat)) ?></span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] <?= $warnaVisib[$r['visibilitas']] ?>"><?= esc($labelVisib[$r['visibilitas']]) ?></span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">
                            <?= $link ? 'Tautan ' . esc($r['penyedia']) : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                            · <?= esc($tgl($r['created_at'])) ?>
                        </p>
                    </div>

                    <div class="px-2.5 pb-2.5 flex items-center gap-1">
                        <?php if (! $link) { ?>
                            <a href="<?= site_url('admin/dokumen/unduh/' . $id) ?>" title="Unduh"
                               class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-brand-700 hover:border-brand-300">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        <?php } ?>
                        <button type="button" title="Ubah"
                                data-action="<?= site_url('admin/dokumen/' . $id) ?>"
                                data-judul="<?= esc($r['judul'], 'attr') ?>"
                                data-deskripsi="<?= esc((string) $r['deskripsi'], 'attr') ?>"
                                data-visib="<?= esc($r['visibilitas'], 'attr') ?>"
                                data-url="<?= esc((string) $r['url_eksternal'], 'attr') ?>"
                                data-tipe="<?= esc($r['tipe'], 'attr') ?>"
                                @click="bukaEdit($el)"
                                class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-brand-700 hover:border-brand-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <form method="post" action="<?= site_url('admin/dokumen/' . $id . '/hapus') ?>" class="ml-auto"
                              onsubmit="return confirm('Buang dokumen ini ke tempat sampah?')">
                            <?= csrf_field() ?>
                            <button class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-red-700 hover:border-red-300" title="Buang ke sampah">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 w-8">
                                <input type="checkbox" @change="pilihSemua([<?= implode(',', array_map('intval', array_column($rows, 'id'))) ?>])"
                                       class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            </th>
                            <th class="px-3 py-2 text-left">Nama</th>
                            <th class="px-3 py-2 text-left hidden md:table-cell">Jenis</th>
                            <th class="px-3 py-2 text-left hidden lg:table-cell">Akses</th>
                            <th class="px-3 py-2 text-right hidden sm:table-cell">Ukuran</th>
                            <th class="px-3 py-2 text-left hidden lg:table-cell">Diunggah</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r) {
                            $id   = (int) $r['id'];
                            $kat  = (string) $r['kategori'];
                            $link = $r['tipe'] === 'tautan';
                            ?>
                            <tr class="hover:bg-slate-50 cursor-grab active:cursor-grabbing"
                                draggable="true"
                                @dragstart="mulaiSeret(<?= $id ?>, $event)" @dragend="selesaiSeret()"
                                :class="{ 'bg-brand-50': pilih.includes(<?= $id ?>), 'opacity-40': dndIds.includes(<?= $id ?>) }">
                                <td class="px-3 py-2">
                                    <input type="checkbox" :checked="pilih.includes(<?= $id ?>)" @change="toggle(<?= $id ?>)"
                                           class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                </td>
                                <td class="px-3 py-2">
                                    <a href="<?= site_url('admin/dokumen/pratinjau/' . $id) ?>" class="flex items-center gap-2 group">
                                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= esc($ikonKategori[$kat] ?? $ikonKategori['lainnya']) ?>"/></svg>
                                        <span class="font-medium text-slate-800 group-hover:text-brand-700 truncate max-w-[280px]"><?= esc($r['judul']) ?></span>
                                    </a>
                                </td>
                                <td class="px-3 py-2 hidden md:table-cell">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $warnaKategori[$kat] ?? $warnaKategori['lainnya'] ?>"><?= esc(strtoupper($r['ekstensi'] ?: $kat)) ?></span>
                                </td>
                                <td class="px-3 py-2 hidden lg:table-cell">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] <?= $warnaVisib[$r['visibilitas']] ?>"><?= esc($labelVisib[$r['visibilitas']]) ?></span>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-500 hidden sm:table-cell">
                                    <?= $link ? '—' : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?>
                                </td>
                                <td class="px-3 py-2 text-slate-500 hidden lg:table-cell text-xs">
                                    <?= esc($tgl($r['created_at'])) ?><br>
                                    <span class="text-slate-400"><?= esc((string) ($r['pengunggah'] ?? '—')) ?></span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <?php if (! $link) { ?>
                                            <a href="<?= site_url('admin/dokumen/unduh/' . $id) ?>" title="Unduh"
                                               class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-brand-700 hover:border-brand-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        <?php } ?>
                                        <button type="button" title="Ubah"
                                                data-action="<?= site_url('admin/dokumen/' . $id) ?>"
                                                data-judul="<?= esc($r['judul'], 'attr') ?>"
                                                data-deskripsi="<?= esc((string) $r['deskripsi'], 'attr') ?>"
                                                data-visib="<?= esc($r['visibilitas'], 'attr') ?>"
                                                data-url="<?= esc((string) $r['url_eksternal'], 'attr') ?>"
                                                data-tipe="<?= esc($r['tipe'], 'attr') ?>"
                                                @click="bukaEdit($el)"
                                                class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-brand-700 hover:border-brand-300">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="post" action="<?= site_url('admin/dokumen/' . $id . '/hapus') ?>"
                                              onsubmit="return confirm('Buang dokumen ini ke tempat sampah?')">
                                            <?= csrf_field() ?>
                                            <button class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-red-700 hover:border-red-300" title="Buang ke sampah">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>

    <?php if ($pager !== null) { ?>
        <div class="mt-4"><?= $pager->only(['q', 'kategori', 'urut', 'arah', 'tampilan', 'per', 'folder'])->links('dok', 'admin') ?></div>
    <?php } ?>

    <!-- ================= Modal: Folder baru ================= -->
    <div x-show="folderOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="folderOpen=false"></div>
        <form method="post" action="<?= site_url('admin/dokumen/folder') ?>" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="parent_id" value="<?= (int) $folderId ?>">
            <h3 class="font-semibold text-slate-800 mb-3">Folder Baru</h3>
            <label class="block text-sm text-slate-600 mb-1">Nama folder <span class="text-red-500">*</span></label>
            <input name="nama" required maxlength="150" autofocus class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3" placeholder="mis. Proposal Kegiatan 2026">
            <label class="block text-sm text-slate-600 mb-1">Keterangan</label>
            <input name="deskripsi" maxlength="255" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Akses</label>
            <select name="visibilitas" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="privat">Privat — hanya admin yang masuk</option>
                <option value="link">Lewat tautan — siapa pun yang punya tautannya</option>
                <option value="publik">Publik — tampil di situs sekolah</option>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" @click="folderOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Buat Folder</button>
            </div>
        </form>
    </div>

    <!-- ================= Modal: Unggah ================= -->
    <div x-show="unggahOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="unggahOpen=false"></div>
        <form method="post" action="<?= site_url('admin/dokumen/unggah') ?>" enctype="multipart/form-data"
              class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
            <h3 class="font-semibold text-slate-800 mb-1">Unggah Berkas</h3>
            <p class="text-xs text-slate-500 mb-3">
                Ke folder: <b><?= esc($folderKini['nama'] ?? 'Dokumen (utama)') ?></b> ·
                maksimal <?= esc(dokumen_ukuran_manusia($maksByte)) ?> per berkas
            </p>

            <label class="block border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition"
                   :class="seret ? 'border-brand-500 bg-brand-50' : 'border-slate-300 hover:border-brand-400 hover:bg-slate-50'"
                   @dragover.prevent="seret=true" @dragleave.prevent="seret=false"
                   @drop.prevent="seret=false; $refs.berkas.files = $event.dataTransfer.files">
                <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <p class="mt-2 text-sm text-slate-600">Seret berkas ke sini, atau <span class="text-brand-700 font-semibold">pilih dari komputer</span></p>
                <p class="text-xs text-slate-400 mt-1">Word, Excel, PowerPoint, PDF, gambar, zip — boleh banyak sekaligus</p>
                <input type="file" name="berkas[]" multiple x-ref="berkas" class="hidden"
                       @change="$refs.jml.textContent = $event.target.files.length + ' berkas dipilih'">
            </label>
            <p x-ref="jml" class="text-xs text-brand-700 font-medium mt-2 text-center"></p>

            <label class="block text-sm text-slate-600 mt-3 mb-1">Akses berkas</label>
            <select name="visibilitas" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="privat">Privat — hanya admin yang masuk</option>
                <option value="link">Lewat tautan — siapa pun yang punya tautannya</option>
                <option value="publik">Publik — tampil di situs sekolah</option>
            </select>

            <div class="flex justify-end gap-2">
                <button type="button" @click="unggahOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Unggah</button>
            </div>
        </form>
    </div>

    <!-- ================= Modal: Tautan ================= -->
    <div x-show="tautanOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="tautanOpen=false"></div>
        <form method="post" action="<?= site_url('admin/dokumen/tautan') ?>" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
            <h3 class="font-semibold text-slate-800 mb-1">Tambah Tautan</h3>
            <p class="text-xs text-slate-500 mb-3">Untuk video YouTube, berkas Google Drive, atau alamat web lain. Video sengaja tidak disimpan di server agar ruang penyimpanan tidak cepat penuh.</p>
            <label class="block text-sm text-slate-600 mb-1">Alamat tautan <span class="text-red-500">*</span></label>
            <input name="url_eksternal" required type="url" placeholder="https://youtu.be/…" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Judul</label>
            <input name="judul" maxlength="200" placeholder="mis. Dokumentasi Upacara HUT RI" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Keterangan</label>
            <input name="deskripsi" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Akses</label>
            <select name="visibilitas" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="privat">Privat</option>
                <option value="link">Lewat tautan</option>
                <option value="publik">Publik</option>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" @click="tautanOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Simpan Tautan</button>
            </div>
        </form>
    </div>

    <!-- ================= Modal: Ubah dokumen ================= -->
    <div x-show="editOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="editOpen=false"></div>
        <form method="post" :action="editAction" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <h3 class="font-semibold text-slate-800 mb-3">Ubah Dokumen</h3>
            <label class="block text-sm text-slate-600 mb-1">Judul <span class="text-red-500">*</span></label>
            <input name="judul" x-model="editJudul" required maxlength="200" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <template x-if="editTipe === 'tautan'">
                <div class="mb-3">
                    <label class="block text-sm text-slate-600 mb-1">Alamat tautan</label>
                    <input name="url_eksternal" x-model="editUrl" type="url" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">
                </div>
            </template>
            <label class="block text-sm text-slate-600 mb-1">Keterangan</label>
            <textarea name="deskripsi" x-model="editDeskripsi" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3"></textarea>
            <label class="block text-sm text-slate-600 mb-1">Akses</label>
            <select name="visibilitas" x-model="editVisib" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="privat">Privat — hanya admin yang masuk</option>
                <option value="link">Lewat tautan — siapa pun yang punya tautannya</option>
                <option value="publik">Publik — tampil di situs sekolah</option>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" @click="editOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>

    <!-- ================= Modal: Ubah folder ================= -->
    <div x-show="folderEditOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="folderEditOpen=false"></div>
        <form method="post" :action="folderAction" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <h3 class="font-semibold text-slate-800 mb-3">Ubah Folder</h3>
            <label class="block text-sm text-slate-600 mb-1">Nama folder <span class="text-red-500">*</span></label>
            <input name="nama" x-model="folderNama" required maxlength="150" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Keterangan</label>
            <input name="deskripsi" x-model="folderDeskripsi" maxlength="255" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
            <label class="block text-sm text-slate-600 mb-1">Akses</label>
            <select name="visibilitas" x-model="folderVisib" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">
                <option value="privat">Privat</option>
                <option value="link">Lewat tautan</option>
                <option value="publik">Publik</option>
            </select>
            <label class="block text-sm text-slate-600 mb-1">Pindahkan ke</label>
            <input type="hidden" name="pindah" value="1">
            <select name="induk_baru" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="0">Dokumen (folder utama)</option>
                <?php foreach ($folderOpts as $fid => $flabel) { ?>
                    <option value="<?= (int) $fid ?>"><?= esc($flabel) ?></option>
                <?php } ?>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" @click="folderEditOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>

    <!-- ================= Modal: Pindahkan dokumen ================= -->
    <div x-show="pindahOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="pindahOpen=false"></div>
        <form method="post" action="<?= site_url('admin/dokumen/pindah') ?>" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
            <template x-for="id in pilih" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <h3 class="font-semibold text-slate-800 mb-1">Pindahkan Dokumen</h3>
            <p class="text-xs text-slate-500 mb-3"><span x-text="pilih.length"></span> dokumen akan dipindahkan.</p>
            <label class="block text-sm text-slate-600 mb-1">Folder tujuan</label>
            <select name="tujuan" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">
                <option value="0">Dokumen (folder utama)</option>
                <?php foreach ($folderOpts as $fid => $flabel) { ?>
                    <option value="<?= (int) $fid ?>"><?= esc($flabel) ?></option>
                <?php } ?>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" @click="pindahOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold">Pindahkan</button>
            </div>
        </form>
    </div>

</div>

<?= $this->endSection() ?>
