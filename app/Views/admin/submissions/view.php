<?php
/**
 * Detail Kesediaan Guru (rombak tampilan F6: kartu template Flowbite). Tombol di kepala: Kembali, Cetak surat (PDF), Hapus.
 * Kolom kanan: verifikasi admin, tautan revisi (salin = komponen kit "salinTeks", tanpa skrip sebaris → siap CSP; kirim lewat WhatsApp),
 * ketersediaan hari, keterangan.
 *
 * @var array $row Data kesediaan (mapel_diampu, kesediaan_jam, ketersediaan_hari sudah berupa array)
 */
$hariList    = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
$statusBadge = [
    'baru'         => ['Baru', 'lencana-merek'],
    'diverifikasi' => ['Diverifikasi', 'lencana-hijau'],
    'ditolak'      => ['Perlu revisi', 'lencana-merah'],
];
$sb = $statusBadge[$row['status']] ?? $statusBadge['baru'];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/submissions') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke daftar</a>
<a href="<?= site_url('admin/export/surat/' . $row['id']) ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Cetak surat (PDF)</a>
<a href="<?= site_url('admin/submissions/delete/' . $row['id']) ?>" class="btn btn-bahaya" data-method="post" data-confirm="Hapus data kesediaan dari <?= esc($row['nama_lengkap'], 'attr') ?>?" data-confirm-judul="Hapus data?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <!-- KOLOM UTAMA -->
    <div class="space-y-4 lg:col-span-2">
        <!-- Identitas -->
        <section class="kartu" aria-labelledby="judul-identitas">
            <div class="kartu-kepala">
                <h2 id="judul-identitas" class="kartu-judul">Identitas guru</h2>
                <span class="lencana <?= $sb[1] ?>"><?= $sb[0] ?></span>
            </div>
            <div class="kartu-isi">
                <div class="mb-5 flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xl font-bold text-brand-700 dark:bg-brand-900 dark:text-brand-300" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) $row['nama_lengkap'], 0, 1))) ?></span>
                    <div class="min-w-0">
                        <p class="text-lg font-bold text-gray-900 dark:text-white"><?= esc($row['nama_lengkap']) ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Guru mapel <?= esc($row['guru_mapel'] ?: '—') ?></p>
                        <?php if (empty($row['bersedia_mengajar'])): ?>
                            <span class="lencana lencana-merah mt-1">Tidak bersedia</span>
                        <?php else: ?>
                            <span class="lencana lencana-hijau mt-1"><?= ikon('check', 'h-3 w-3') ?>Bersedia</span>
                        <?php endif; ?>
                    </div>
                </div>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <?php foreach ([
                        'Nomor HP'            => $row['nomor_hp'] ?: '—',
                        'Pendidikan terakhir' => $row['pendidikan_terakhir'] ?: '—',
                        'Status kepegawaian'  => $row['status_kepegawaian'] ?: '—',
                        'Dikirim pada'        => date('d/m/Y H:i', strtotime($row['created_at'])),
                    ] as $k => $v): ?>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400"><?= $k ?></dt>
                            <dd class="font-medium text-gray-900 dark:text-white"><?= esc($v) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>

        <!-- Mata pelajaran diampu -->
        <section class="kartu" aria-labelledby="judul-mapel">
            <div class="kartu-kepala"><h2 id="judul-mapel" class="kartu-judul">Mata pelajaran yang diampu</h2></div>
            <?php if (! empty($row['mapel_diampu'])): ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th scope="col" class="w-12">No</th><th scope="col">Mata pelajaran</th><th scope="col">Kelas</th></tr></thead>
                        <tbody>
                            <?php foreach ($row['mapel_diampu'] as $i => $m): ?>
                                <tr><td><?= $i + 1 ?></td><th scope="row"><?= esc($m['mapel']) ?></th><td><?= esc($m['kelas']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="kartu-isi text-sm text-gray-500 dark:text-gray-400">Tidak ada data.</p>
            <?php endif; ?>
        </section>

        <!-- Tugas tambahan & jam -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <section class="kartu kartu-isi" aria-labelledby="judul-tugas">
                <h2 id="judul-tugas" class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Tugas tambahan</h2>
                <?php if (! empty($row['tugas_tambahan'])): ?>
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-400"><?= ikon('check-circle', 'h-4 w-4') ?>Bersedia menerima tugas tambahan</p>
                <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak bersedia / belum mengisi.</p>
                <?php endif; ?>
            </section>
            <section class="kartu kartu-isi" aria-labelledby="judul-jam">
                <h2 id="judul-jam" class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Kesediaan jam mengajar</h2>
                <?php if (! empty($row['kesediaan_jam'])): ?>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($row['kesediaan_jam'] as $j): ?>
                            <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300"><span class="mt-0.5 text-green-600 dark:text-green-400"><?= ikon('check', 'h-4 w-4') ?></span><?= esc($j) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- KOLOM SAMPING -->
    <div class="space-y-4">
        <!-- Verifikasi admin -->
        <section class="kartu kartu-isi" aria-labelledby="judul-verifikasi">
            <h2 id="judul-verifikasi" class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Verifikasi admin</h2>
            <form method="post" action="<?= site_url('admin/submissions/status/' . $row['id']) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl" for="kes-status">Status</label>
                    <select id="kes-status" name="status" class="inp">
                        <option value="baru"<?= $row['status'] === 'baru' ? ' selected' : '' ?>>Baru</option>
                        <option value="diverifikasi"<?= $row['status'] === 'diverifikasi' ? ' selected' : '' ?>>Diverifikasi</option>
                        <option value="ditolak"<?= $row['status'] === 'ditolak' ? ' selected' : '' ?>>Perlu revisi</option>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="kes-catatan">Catatan admin</label>
                    <textarea id="kes-catatan" name="catatan_admin" rows="3" class="inp" placeholder="Catatan internal (opsional)"><?= esc($row['catatan_admin']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-utama w-full"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
            </form>
        </section>

        <!-- Tautan revisi (muncul bila status = Perlu revisi) -->
        <?php if ($row['status'] === 'ditolak' && ! empty($row['edit_token'])):
            $revisiUrl = site_url('revisi/' . $row['edit_token']);
            $waNum = preg_replace('/[^0-9]/', '', (string) $row['nomor_hp']);
            if (str_starts_with($waNum, '0')) {
                $waNum = '62' . substr($waNum, 1);
            }
            $waMsg = 'Yth. Bapak/Ibu ' . $row['nama_lengkap'] . ",\n\nMohon perbaiki data kesediaan mengajar Anda melalui tautan berikut:\n" . $revisiUrl;
            if (! empty($row['catatan_admin'])) {
                $waMsg .= "\n\nCatatan: " . $row['catatan_admin'];
            }
            $waLink = 'https://wa.me/' . $waNum . '?text=' . rawurlencode($waMsg);
        ?>
            <section class="kartu kartu-isi border-yellow-300 dark:border-yellow-700" aria-labelledby="judul-revisi">
                <h2 id="judul-revisi" class="text-base font-semibold text-gray-900 dark:text-white">Tautan revisi guru</h2>
                <p class="hint mt-1 mb-3">Kirim tautan ini ke guru. Formulir akan terisi data lama, dan tautan hanya berlaku sekali pakai.</p>
                <div class="flex items-stretch gap-2">
                    <label class="sr-only" for="revisiUrl">Tautan revisi</label>
                    <input id="revisiUrl" type="text" readonly value="<?= esc($revisiUrl, 'attr') ?>" class="inp min-w-0 flex-1 text-xs">
                    <button type="button" class="btn btn-netral shrink-0" x-data="salinTeks" data-sasaran="#revisiUrl" data-pesan="Tautan revisi tersalin." @click="salin()">
                        <?= ikon('file-copy', 'h-4 w-4') ?><span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin</span>
                    </button>
                </div>
                <a href="<?= esc($waLink, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-sukses mt-3 w-full"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Kirim via WhatsApp</a>
            </section>
        <?php endif; ?>

        <!-- Ketersediaan hari -->
        <section class="kartu kartu-isi" aria-labelledby="judul-hari">
            <h2 id="judul-hari" class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Ketersediaan hari</h2>
            <ul class="space-y-2 text-sm">
                <?php foreach ($hariList as $h): $sesi = $row['ketersediaan_hari'][$h] ?? []; ?>
                    <li class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-1.5 dark:bg-gray-700">
                        <span class="text-gray-600 dark:text-gray-300"><?= $h ?></span>
                        <span class="font-semibold <?= $sesi ? 'text-green-700 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' ?>"><?= $sesi ? esc(implode(' & ', (array) $sesi)) : '—' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Keterangan -->
        <?php if (! empty($row['keterangan_tambahan'])): ?>
            <section class="kartu kartu-isi" aria-labelledby="judul-ket">
                <h2 id="judul-ket" class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Keterangan tambahan</h2>
                <p class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300"><?= esc($row['keterangan_tambahan']) ?></p>
            </section>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
