<?php

/**
 * Pratinjau satu dokumen + pengelolaan tautan berbagi.
 *
 * @var array $d       Baris dokumen (+ pengunggah, folder_nama)
 * @var array $jejak   Breadcrumb folder
 * @var array $shares  Tautan berbagi untuk dokumen ini
 * @var array $riwayat Jejak akses terakhir
 */
helper('dokumen');

$id       = (int) $d['id'];
$kategori = (string) $d['kategori'];
$ext      = strtolower((string) $d['ekstensi']);
$tautan   = $d['tipe'] === 'tautan';
$urlIsi   = site_url('admin/dokumen/berkas/' . $id);
$urlUnduh = site_url('admin/dokumen/unduh/' . $id);

// Office Viewer hanya masuk akal untuk berkas yang memang boleh diakses
// dari internet — layanan Microsoft harus bisa mengambil berkasnya sendiri.
$bolehViewerLuar = ! $tautan && in_array($d['visibilitas'], ['link', 'publik'], true)
    && in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);

$labelVisib = ['privat' => 'Privat', 'link' => 'Lewat tautan', 'publik' => 'Publik'];
$warnaVisib = [
    'privat' => 'bg-slate-100 text-slate-600',
    'link'   => 'bg-sky-50 text-sky-700',
    'publik' => 'bg-emerald-50 text-emerald-700',
];
$tgl     = static fn ($x) => $x ? date('d/m/Y H:i', strtotime((string) $x)) : '—';
$sekolah = (new \App\Models\SettingModel())->get()['school_name'] ?? 'Sekolah';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="{
        bagikanOpen: false,
        async salin(teks, el) {
            try { await navigator.clipboard.writeText(teks); }
            catch (e) {
                const t = document.createElement('textarea');
                t.value = teks; document.body.appendChild(t); t.select();
                document.execCommand('copy'); t.remove();
            }
            const asli = el.textContent; el.textContent = 'Tersalin!';
            setTimeout(() => { el.textContent = asli; }, 1600);
        }
     }">

    <!-- Remah jejak + aksi -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <nav class="flex items-center gap-1 text-sm flex-1 min-w-0 flex-wrap">
            <a href="<?= site_url('admin/dokumen') ?>" class="px-2 py-1 rounded-lg hover:bg-slate-100 text-slate-600">Dokumen</a>
            <?php foreach ($jejak as $j) { ?>
                <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <a href="<?= site_url('admin/dokumen?folder=' . $j['id']) ?>" class="px-2 py-1 rounded-lg hover:bg-slate-100 text-slate-600 truncate max-w-[160px]"><?= esc($j['nama']) ?></a>
            <?php } ?>
            <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            <span class="px-2 py-1 font-semibold text-brand-700 truncate max-w-[220px]"><?= esc($d['judul']) ?></span>
        </nav>

        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="bagikanOpen=true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342a3 3 0 100-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684zm0-12.632a3 3 0 105.368-2.684 3 3 0 00-5.368 2.684z"/></svg>
                Bagikan
            </button>
            <?php if (! $tautan) { ?>
                <a href="<?= $urlUnduh ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh
                </a>
            <?php } ?>
            <a href="<?= site_url('admin/dokumen' . ($d['folder_id'] !== null ? '?folder=' . (int) $d['folder_id'] : '')) ?>"
               class="px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">Kembali</a>
        </div>
    </div>

    <!-- Penampil dibuat SELEBAR halaman: berkas yang dibaca (PDF/Excel/Word)
         butuh ruang horizontal, sementara keterangan cukup dibaca sekilas —
         jadi keterangan turun ke bawah, bukan mengapit di samping. -->
    <div class="space-y-4">

        <!-- Penampil -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <?= view('partials/dokumen_penampil', [
                'urlIsi'          => $urlIsi,
                'urlUnduh'        => $urlUnduh,
                'kategori'        => $kategori,
                'ext'             => $ext,
                'judul'           => $d['judul'],
                'mime'            => (string) $d['mime'],
                'tautan'          => $tautan,
                'ytId'            => $tautan ? dokumen_youtube_id((string) $d['url_eksternal']) : null,
                'urlEksternal'    => $d['url_eksternal'],
                'penyedia'        => (string) $d['penyedia'],
                'bolehViewerLuar' => $bolehViewerLuar,
                'urlViewerLuar'   => $bolehViewerLuar
                    ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($urlIsi)
                    : null,
            ]) ?>
        </div>

        <!-- Panel bawah: keterangan · tautan berbagi · riwayat -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

            <!-- Keterangan -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <h3 class="font-semibold text-slate-800 text-sm mb-3">Keterangan Berkas</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 shrink-0">Judul</dt>
                        <dd class="text-slate-800 font-medium text-right break-words"><?= esc($d['judul']) ?></dd>
                    </div>
                    <?php if (! $tautan) { ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 shrink-0">Nama berkas</dt>
                            <dd class="text-slate-700 text-right break-all text-xs"><?= esc((string) $d['nama_asli']) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Ukuran</dt>
                            <dd class="text-slate-700"><?= esc(dokumen_ukuran_manusia((int) $d['ukuran'])) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Jenis</dt>
                            <dd class="text-slate-700"><?= esc(strtoupper($ext ?: $kategori)) ?></dd>
                        </div>
                    <?php } else { ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 shrink-0">Tautan</dt>
                            <dd class="text-right break-all text-xs"><a href="<?= esc($d['url_eksternal']) ?>" target="_blank" rel="noopener" class="text-brand-700 underline"><?= esc($d['url_eksternal']) ?></a></dd>
                        </div>
                    <?php } ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Akses</dt>
                        <dd><span class="px-2 py-0.5 rounded text-[11px] font-medium <?= $warnaVisib[$d['visibilitas']] ?>"><?= esc($labelVisib[$d['visibilitas']]) ?></span></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Folder</dt>
                        <dd class="text-slate-700 text-right"><?= esc((string) ($d['folder_nama'] ?? 'Dokumen (utama)')) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Diunggah</dt>
                        <dd class="text-slate-700 text-right text-xs">
                            <?= esc($tgl($d['created_at'])) ?><br>
                            <span class="text-slate-400"><?= esc((string) ($d['pengunggah'] ?? '—')) ?></span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Dibuka / diunduh</dt>
                        <dd class="text-slate-700"><?= (int) $d['jml_lihat'] ?> / <?= (int) $d['jml_unduh'] ?></dd>
                    </div>
                </dl>

                <?php if (trim((string) $d['deskripsi']) !== '') { ?>
                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <p class="text-xs text-slate-500 mb-1">Keterangan</p>
                        <p class="text-sm text-slate-700 whitespace-pre-line"><?= esc($d['deskripsi']) ?></p>
                    </div>
                <?php } ?>
            </div>

            <!-- Tautan berbagi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <h3 class="font-semibold text-slate-800 text-sm mb-1">Tautan Berbagi</h3>
                <p class="text-xs text-slate-500 mb-3">Guru tidak perlu akun — cukup buka tautannya.</p>

                <?php if ($shares === []) { ?>
                    <p class="text-sm text-slate-400 py-3 text-center">Belum ada tautan. Klik <b>Bagikan</b> di atas.</p>
                <?php } else { ?>
                    <div class="space-y-3">
                        <?php foreach ($shares as $s) {
                            $url    = site_url('d/' . $s['token']);
                            $mati   = (int) $s['aktif'] !== 1;
                            $lewat  = ! empty($s['expired_at']) && strtotime((string) $s['expired_at']) < time();
                            $habis  = $s['maks_unduh'] !== null && (int) $s['jml_unduh'] >= (int) $s['maks_unduh'];
                            $sehat  = ! $mati && ! $lewat && ! $habis;
                            $pesanWa = rawurlencode("Assalamualaikum. Berikut dokumen \"" . $d['judul'] . "\" dari " . $sekolah . ":\n" . $url);
                            ?>
                            <div class="border rounded-xl p-3 <?= $sehat ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50' ?>">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $sehat ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
                                        <?= $mati ? 'DICABUT' : ($lewat ? 'KEDALUWARSA' : ($habis ? 'JATAH HABIS' : 'AKTIF')) ?>
                                    </span>
                                    <?php if ($s['password_hash']) { ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-amber-100 text-amber-700">Bersandi</span>
                                    <?php } ?>
                                    <?php if ((int) $s['boleh_unduh'] !== 1) { ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-slate-200 text-slate-600">Lihat saja</span>
                                    <?php } ?>
                                </div>

                                <input readonly value="<?= esc($url, 'attr') ?>" onclick="this.select()"
                                       class="w-full px-2 py-1.5 rounded-lg border border-slate-300 text-[11px] font-mono bg-white mb-2">

                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" @click="salin('<?= esc($url, 'js') ?>', $el)"
                                            class="px-2.5 py-1 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-white">Salin</button>
                                    <a href="https://wa.me/?text=<?= $pesanWa ?>" target="_blank" rel="noopener"
                                       class="px-2.5 py-1 rounded-lg bg-green-600 text-white text-xs font-medium hover:bg-green-700">WhatsApp</a>
                                    <a href="<?= esc($url) ?>" target="_blank" rel="noopener"
                                       class="px-2.5 py-1 rounded-lg border border-slate-300 text-xs text-slate-600 hover:bg-white">Coba buka</a>
                                    <?php if (! $mati) { ?>
                                        <form method="post" action="<?= site_url('admin/dokumen/share/' . $s['id'] . '/cabut') ?>" class="ml-auto"
                                              onsubmit="return confirm('Cabut tautan ini? Guru yang sudah memegangnya tidak bisa lagi membuka dokumen.')">
                                            <?= csrf_field() ?>
                                            <button class="px-2.5 py-1 rounded-lg border border-red-300 text-xs font-medium text-red-700 hover:bg-red-50">Cabut</button>
                                        </form>
                                    <?php } ?>
                                </div>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Dibuka <?= (int) $s['jml_akses'] ?>× · diunduh <?= (int) $s['jml_unduh'] ?><?= $s['maks_unduh'] !== null ? '/' . (int) $s['maks_unduh'] : '' ?>×
                                    <?php if (! empty($s['expired_at'])) { ?> · berlaku s/d <?= esc(date('d/m/Y', strtotime((string) $s['expired_at']))) ?><?php } ?>
                                </p>
                                <?php if (! empty($s['catatan'])) { ?>
                                    <p class="text-[11px] text-slate-500 italic mt-0.5"><?= esc($s['catatan']) ?></p>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Riwayat akses -->
            <?php if ($riwayat !== []) { ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <h3 class="font-semibold text-slate-800 text-sm mb-3">Riwayat Akses Terakhir</h3>
                    <ul class="space-y-1.5 text-xs">
                        <?php foreach ($riwayat as $r) { ?>
                            <li class="flex items-center gap-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium <?= $r['aksi'] === 'unduh' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' ?>"><?= esc(ucfirst($r['aksi'])) ?></span>
                                <span class="text-slate-600 truncate"><?= esc((string) ($r['admin_nama'] ?? 'Tamu lewat tautan')) ?></span>
                                <span class="text-slate-400 ml-auto shrink-0"><?= esc($tgl($r['created_at'])) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Modal: buat tautan berbagi -->
    <div x-show="bagikanOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="bagikanOpen=false"></div>
        <form method="post" action="<?= site_url('admin/dokumen/' . $id . '/bagikan') ?>" class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <?= csrf_field() ?>
            <h3 class="font-semibold text-slate-800 mb-1">Bagikan Dokumen</h3>
            <p class="text-xs text-slate-500 mb-4">Tautan bisa dibuka siapa pun yang memegangnya, tanpa perlu akun. Semua pembatasan di bawah bersifat opsional.</p>

            <label class="block text-sm text-slate-600 mb-1">Berlaku sampai tanggal</label>
            <input type="date" name="expired_at" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-1">
            <p class="text-[11px] text-slate-400 mb-3">Kosongkan bila tautan tidak perlu kedaluwarsa.</p>

            <label class="block text-sm text-slate-600 mb-1">Kata sandi</label>
            <input type="text" name="sandi" autocomplete="off" placeholder="Kosongkan bila tidak perlu"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">

            <label class="block text-sm text-slate-600 mb-1">Batas jumlah unduhan</label>
            <input type="number" name="maks_unduh" min="1" placeholder="Kosongkan = tanpa batas"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-3">

            <label class="flex items-center gap-2 mb-3 cursor-pointer">
                <input type="checkbox" name="boleh_unduh" value="1" checked class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Boleh diunduh (jika dimatikan, hanya bisa dilihat)</span>
            </label>

            <label class="block text-sm text-slate-600 mb-1">Catatan internal</label>
            <input name="catatan" maxlength="255" placeholder="mis. untuk wali kelas XII"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm mb-4">

            <?php if ($d['visibilitas'] === 'privat') { ?>
                <p class="text-[11px] text-sky-800 bg-sky-50 border border-sky-200 rounded-lg px-3 py-2 mb-4">
                    Dokumen ini masih <b>Privat</b>. Setelah tautan dibuat, aksesnya otomatis
                    diubah menjadi <b>Lewat tautan</b> agar penerima benar-benar bisa membukanya.
                </p>
            <?php } ?>

            <div class="flex justify-end gap-2">
                <button type="button" @click="bagikanOpen=false" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Batal</button>
                <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">Buat Tautan</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
