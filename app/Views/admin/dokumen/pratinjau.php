<?php

/**
 * Pratinjau satu dokumen + pengelolaan tautan berbagi. Rombak tampilan F10: Bagikan / Unduh / Kembali di kepala halaman, jejak
 * folder ber-ikon, kartu kit (penampil, keterangan, tautan berbagi, riwayat), lencana kit, penampil versi admin
 * (admin/dokumen/_penampil.php). Siap CSP: Salin tautan = kit salinTeks (dulu objek x-data bermetode async + fungsi panah),
 * dialog Bagikan = dialog kit (dulu x-show), Cabut = data-confirm kit (dulu onsubmit confirm), onclick select dibuang.
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
$warnaVisib = ['privat' => 'lencana-abu', 'link' => 'lencana-merek', 'publik' => 'lencana-hijau'];
$tgl     = static fn ($x) => $x ? date('d/m/Y H:i', strtotime((string) $x)) : '—';
$sekolah = (new \App\Models\SettingModel())->get()['school_name'] ?? 'Sekolah';
$baris   = static fn (string $dt, string $dd): string => '<div class="flex justify-between gap-3"><dt class="shrink-0 text-gray-500 dark:text-gray-400">' . esc($dt) . '</dt><dd class="text-right break-words text-gray-900 dark:text-white">' . $dd . '</dd></div>';
$kepala  = static fn (string $judul, string $ket = ''): string => '<div class="kartu-kepala"><div><h3 class="kartu-judul text-base">' . esc($judul) . '</h3>' . ($ket !== '' ? '<p class="kartu-ket">' . esc($ket) . '</p>' : '') . '</div></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<button type="button" class="btn btn-sukses" data-buka-dialog="dok-bagikan"><?= ikon('users', 'h-4 w-4') ?>Bagikan</button>
<?php if (! $tautan): ?>
    <a href="<?= $urlUnduh ?>" class="btn btn-utama"><?= ikon('download', 'h-4 w-4') ?>Unduh</a>
<?php endif; ?>
<a href="<?= site_url('admin/dokumen' . ($d['folder_id'] !== null ? '?folder=' . (int) $d['folder_id'] : '')) ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="space-y-4">
    <!-- Jejak folder -->
    <nav class="flex min-w-0 flex-wrap items-center gap-1 text-sm" aria-label="Letak dokumen">
        <a href="<?= site_url('admin/dokumen') ?>" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"><?= ikon('folder-open', 'h-4 w-4') ?>Dokumen</a>
        <?php foreach ($jejak as $j): ?>
            <?= ikon('chevron-right', 'h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400') ?>
            <a href="<?= site_url('admin/dokumen?folder=' . $j['id']) ?>" class="max-w-[160px] truncate rounded-lg px-2 py-1 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"><?= esc($j['nama']) ?></a>
        <?php endforeach; ?>
        <?= ikon('chevron-right', 'h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400') ?>
        <span class="max-w-[220px] truncate px-2 py-1 font-semibold text-brand-700 dark:text-brand-400"><?= esc($d['judul']) ?></span>
    </nav>

    <!-- Penampil dibuat SELEBAR halaman: berkas yang dibaca (PDF/Excel/Word) butuh ruang horizontal, sementara keterangan cukup
         dibaca sekilas — jadi keterangan turun ke bawah, bukan mengapit di samping. -->
    <section class="kartu overflow-hidden" aria-label="Isi dokumen">
        <?= view('admin/dokumen/_penampil', [
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
    </section>

    <!-- Panel bawah: keterangan · tautan berbagi · riwayat -->
    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">

        <!-- Keterangan -->
        <section class="kartu overflow-hidden">
            <?= $kepala('Keterangan berkas') ?>
            <div class="kartu-isi !p-4">
                <dl class="space-y-2 text-sm">
                    <?= $baris('Judul', '<span class="font-medium">' . esc($d['judul']) . '</span>') ?>
                    <?php if (! $tautan): ?>
                        <?= $baris('Nama berkas', '<span class="text-xs break-all">' . esc((string) $d['nama_asli']) . '</span>') ?>
                        <?= $baris('Ukuran', esc(dokumen_ukuran_manusia((int) $d['ukuran']))) ?>
                        <?= $baris('Jenis', esc(strtoupper($ext ?: $kategori))) ?>
                    <?php else: ?>
                        <?= $baris('Tautan', '<a href="' . esc((string) $d['url_eksternal'], 'attr') . '" target="_blank" rel="noopener" class="text-xs break-all text-brand-700 underline dark:text-brand-400">' . esc((string) $d['url_eksternal']) . '</a>') ?>
                    <?php endif; ?>
                    <?= $baris('Akses', '<span class="lencana ' . ($warnaVisib[$d['visibilitas']] ?? 'lencana-abu') . '">' . esc($labelVisib[$d['visibilitas']] ?? $d['visibilitas']) . '</span>') ?>
                    <?= $baris('Folder', esc((string) ($d['folder_nama'] ?? 'Dokumen (utama)'))) ?>
                    <?= $baris('Diunggah', '<span class="text-xs">' . esc($tgl($d['created_at'])) . '<br><span class="text-gray-500 dark:text-gray-400">' . esc((string) ($d['pengunggah'] ?? '—')) . '</span></span>') ?>
                    <?= $baris('Dibuka / diunduh', (int) $d['jml_lihat'] . ' / ' . (int) $d['jml_unduh']) ?>
                </dl>

                <?php if (trim((string) $d['deskripsi']) !== ''): ?>
                    <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Keterangan</p>
                        <p class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300"><?= esc($d['deskripsi']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Tautan berbagi -->
        <section class="kartu overflow-hidden">
            <?= $kepala('Tautan berbagi', 'Guru tidak perlu akun — cukup buka tautannya.') ?>
            <div class="kartu-isi !p-4">
                <?php if ($shares === []): ?>
                    <p class="py-3 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada tautan. Klik <b>Bagikan</b> di atas.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($shares as $s):
                            $url    = site_url('d/' . $s['token']);
                            $mati   = (int) $s['aktif'] !== 1;
                            $lewat  = ! empty($s['expired_at']) && strtotime((string) $s['expired_at']) < time();
                            $habis  = $s['maks_unduh'] !== null && (int) $s['jml_unduh'] >= (int) $s['maks_unduh'];
                            $sehat  = ! $mati && ! $lewat && ! $habis;
                            $pesanWa = rawurlencode('Assalamualaikum. Berikut dokumen "' . $d['judul'] . '" dari ' . $sekolah . ":\n" . $url);
                            ?>
                            <div class="rounded-lg border p-3 <?= $sehat ? 'border-green-200 bg-green-50/40 dark:border-green-900 dark:bg-gray-700/40' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/40' ?>">
                                <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                    <span class="lencana <?= $sehat ? 'lencana-hijau' : 'lencana-abu' ?>"><?= $mati ? 'Dicabut' : ($lewat ? 'Kedaluwarsa' : ($habis ? 'Jatah habis' : 'Aktif')) ?></span>
                                    <?php if ($s['password_hash']): ?><span class="lencana lencana-kuning">Bersandi</span><?php endif; ?>
                                    <?php if ((int) $s['boleh_unduh'] !== 1): ?><span class="lencana lencana-abu">Lihat saja</span><?php endif; ?>
                                </div>

                                <input readonly value="<?= esc($url, 'attr') ?>" class="inp mb-2 !py-1.5 font-mono text-xs" aria-label="Tautan berbagi">

                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button type="button" class="btn btn-netral btn-xs" x-data="salinTeks" data-teks="<?= esc($url, 'attr') ?>" data-pesan="Tautan tersalin." @click="salin()"><?= ikon('file-copy', 'h-3.5 w-3.5') ?><span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin</span></button>
                                    <a href="https://wa.me/?text=<?= $pesanWa ?>" target="_blank" rel="noopener" class="btn btn-sukses btn-xs"><?= ikon('whatsapp', 'h-3.5 w-3.5', 'solid') ?>WhatsApp</a>
                                    <a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-netral btn-xs"><?= ikon('arrow-up-right-from-square', 'h-3.5 w-3.5') ?>Coba buka</a>
                                    <?php if (! $mati): ?>
                                        <form method="post" action="<?= site_url('admin/dokumen/share/' . $s['id'] . '/cabut') ?>" class="ms-auto"
                                              data-confirm="Cabut tautan ini? Guru yang sudah memegangnya tidak bisa lagi membuka dokumen." data-confirm-judul="Cabut tautan?" data-confirm-ya="Ya, cabut" data-confirm-jenis="bahaya">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-bahaya btn-xs"><?= ikon('close-circle', 'h-3.5 w-3.5') ?>Cabut</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Dibuka <?= (int) $s['jml_akses'] ?>× · diunduh <?= (int) $s['jml_unduh'] ?><?= $s['maks_unduh'] !== null ? '/' . (int) $s['maks_unduh'] : '' ?>×
                                    <?php if (! empty($s['expired_at'])): ?> · berlaku s/d <?= esc(date('d/m/Y', strtotime((string) $s['expired_at']))) ?><?php endif; ?>
                                </p>
                                <?php if (! empty($s['catatan'])): ?>
                                    <p class="mt-0.5 text-xs text-gray-500 italic dark:text-gray-400"><?= esc($s['catatan']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Riwayat akses -->
        <?php if ($riwayat !== []): ?>
            <section class="kartu overflow-hidden">
                <?= $kepala('Riwayat akses terakhir') ?>
                <ul class="kartu-isi space-y-1.5 !p-4 text-xs">
                    <?php foreach ($riwayat as $r): ?>
                        <li class="flex items-center gap-2">
                            <span class="lencana <?= $r['aksi'] === 'unduh' ? 'lencana-merek' : 'lencana-abu' ?>"><?= esc(ucfirst($r['aksi'])) ?></span>
                            <span class="truncate text-gray-700 dark:text-gray-300"><?= esc((string) ($r['admin_nama'] ?? 'Tamu lewat tautan')) ?></span>
                            <span class="ms-auto shrink-0 text-gray-500 dark:text-gray-400"><?= esc($tgl($r['created_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</div>

<!-- Dialog: buat tautan berbagi -->
<?= ui_dialog_buka(['nama' => 'dok-bagikan', 'judul' => 'Bagikan dokumen']) ?>
<form method="post" action="<?= site_url('admin/dokumen/' . $id . '/bagikan') ?>">
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <p class="text-sm text-gray-500 dark:text-gray-400">Tautan bisa dibuka siapa pun yang memegangnya, tanpa perlu akun. Semua pembatasan di bawah bersifat opsional.</p>
        <div>
            <label class="lbl" for="bg-sampai">Berlaku sampai tanggal</label>
            <input type="date" id="bg-sampai" name="expired_at" class="inp">
            <p class="hint">Kosongkan bila tautan tidak perlu kedaluwarsa.</p>
        </div>
        <div>
            <label class="lbl" for="bg-sandi">Kata sandi</label>
            <input type="text" id="bg-sandi" name="sandi" autocomplete="off" placeholder="Kosongkan bila tidak perlu" class="inp">
        </div>
        <div>
            <label class="lbl" for="bg-maks">Batas jumlah unduhan</label>
            <input type="number" id="bg-maks" name="maks_unduh" min="1" placeholder="Kosongkan = tanpa batas" class="inp">
        </div>
        <label class="saklar">
            <input type="checkbox" name="boleh_unduh" value="1" checked class="peer sr-only">
            <span class="saklar-jalur"></span>
            <span class="saklar-label">Boleh diunduh (jika dimatikan, hanya bisa dilihat)</span>
        </label>
        <div>
            <label class="lbl" for="bg-catatan">Catatan internal</label>
            <input id="bg-catatan" name="catatan" maxlength="255" placeholder="mis. untuk wali kelas XII" class="inp">
        </div>
        <?php if ($d['visibilitas'] === 'privat'): ?>
            <?= ui_peringatan('info', '', ['html' => 'Dokumen ini masih <b>Privat</b>. Setelah tautan dibuat, aksesnya otomatis diubah menjadi <b>Lewat tautan</b> agar penerima benar-benar bisa membukanya.']) ?>
        <?php endif; ?>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-sukses"><?= ikon('check', 'h-4 w-4') ?>Buat tautan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?= $this->endSection() ?>
