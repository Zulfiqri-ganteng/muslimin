<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
    $hariList = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $statusBadge = [
        'baru'         => ['Baru', 'bg-blue-100 text-blue-700'],
        'diverifikasi' => ['Diverifikasi', 'bg-green-100 text-green-700'],
        'ditolak'      => ['Perlu Revisi', 'bg-red-100 text-red-700'],
    ];
    $sb = $statusBadge[$row['status']] ?? $statusBadge['baru'];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <a href="<?= site_url('admin/submissions') ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke daftar
    </a>
    <div class="flex gap-2">
        <a href="<?= site_url('admin/export/surat/' . $row['id']) ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
            Cetak Surat (PDF)
        </a>
        <a href="<?= site_url('admin/submissions/delete/' . $row['id']) ?>" onclick="return confirm('Hapus data kesediaan ini?')" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-sm font-semibold px-4 py-2.5 transition">Hapus</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- KOLOM UTAMA -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Identitas -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-brand-700 px-6 py-4 flex items-center justify-between">
                <h2 class="text-white font-bold">Identitas Guru</h2>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $sb[1] ?>"><?= $sb[0] ?></span>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 mb-5">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-extrabold text-xl"><?= strtoupper(substr($row['nama_lengkap'],0,1)) ?></div>
                    <div>
                        <p class="text-lg font-bold text-slate-800"><?= esc($row['nama_lengkap']) ?></p>
                        <p class="text-sm text-slate-400">Guru Mata Pelajaran <?= esc($row['guru_mapel'] ?: '-') ?></p>
                    </div>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <?php
                    $ttl = $row['tempat_lahir'];
                    if ($row['tanggal_lahir']) $ttl .= ($ttl ? ', ' : '') . date('d F Y', strtotime($row['tanggal_lahir']));
                    $fields = [
                        'NIP / NUPTK'        => $row['nip_nuptk'],
                        'Nomor HP'           => $row['nomor_hp'],
                        'Tempat, Tgl Lahir'  => $ttl ?: '-',
                        'Pendidikan Terakhir'=> $row['pendidikan_terakhir'] ?: '-',
                        'Status Kepegawaian' => $row['status_kepegawaian'],
                        'Dikirim pada'       => date('d/m/Y H:i', strtotime($row['created_at'])),
                    ];
                    foreach ($fields as $k => $v): ?>
                        <div>
                            <dt class="text-slate-400 text-xs"><?= $k ?></dt>
                            <dd class="font-medium text-slate-700"><?= esc($v) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <!-- Mata Pelajaran Diampu -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="font-bold text-slate-800">Mata Pelajaran yang Diampu</h2></div>
            <div class="p-6">
                <?php if (! empty($row['mapel_diampu'])): ?>
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="pb-2 w-8">No</th><th class="pb-2">Mata Pelajaran</th><th class="pb-2">Kelas</th><th class="pb-2 text-center">Jam</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($row['mapel_diampu'] as $i => $m): ?>
                                <tr><td class="py-2 text-slate-400"><?= $i+1 ?></td><td class="py-2 font-medium text-slate-700"><?= esc($m['mapel']) ?></td><td class="py-2 text-slate-600"><?= esc($m['kelas']) ?></td><td class="py-2 text-center text-slate-600"><?= esc($m['jam']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="font-bold text-slate-800 border-t border-slate-200"><td colspan="3" class="pt-2 text-right">Total Jam/Minggu</td><td class="pt-2 text-center"><?= $row['total_jam'] ?></td></tr></tfoot>
                    </table>
                <?php else: ?><p class="text-slate-400 text-sm">Tidak ada data.</p><?php endif; ?>
            </div>
        </div>

        <!-- Tugas Tambahan & Jam -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-bold text-slate-800 mb-3">Tugas Tambahan</h2>
                <?php if (! empty($row['tugas_tambahan'])): ?>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($row['tugas_tambahan'] as $t): ?>
                            <li class="flex items-start gap-2 text-slate-600"><span class="text-green-500 mt-0.5">✓</span><?= esc($t) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?><p class="text-slate-400 text-sm">Tidak ada.</p><?php endif; ?>
                <?php if (! empty($row['tugas_lainnya'])): ?>
                    <p class="mt-3 text-sm text-slate-600"><span class="text-slate-400">Lainnya:</span> <?= esc($row['tugas_lainnya']) ?></p>
                <?php endif; ?>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-bold text-slate-800 mb-3">Kesediaan Jam Mengajar</h2>
                <?php if (! empty($row['kesediaan_jam'])): ?>
                    <ul class="space-y-1.5 text-sm">
                        <?php foreach ($row['kesediaan_jam'] as $j): ?>
                            <li class="flex items-start gap-2 text-slate-600"><span class="text-green-500 mt-0.5">✓</span><?= esc($j) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?><p class="text-slate-400 text-sm">Tidak ada.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SIDEBAR DETAIL -->
    <div class="space-y-5">
        <!-- Ubah Status -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-800 mb-4">Verifikasi Admin</h2>
            <form method="post" action="<?= site_url('admin/submissions/status/' . $row['id']) ?>" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <option value="baru" <?= $row['status']==='baru'?'selected':'' ?>>Baru</option>
                        <option value="diverifikasi" <?= $row['status']==='diverifikasi'?'selected':'' ?>>Diverifikasi</option>
                        <option value="ditolak" <?= $row['status']==='ditolak'?'selected':'' ?>>Perlu Revisi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Catatan Admin</label>
                    <textarea name="catatan_admin" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none" placeholder="Catatan internal (opsional)"><?= esc($row['catatan_admin']) ?></textarea>
                </div>
                <button class="w-full bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">Simpan</button>
            </form>
        </div>

        <!-- Preferensi -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-800 mb-3">Preferensi Mapel</h2>
            <?php if (! empty($row['preferensi'])): ?>
                <ol class="space-y-2 text-sm">
                    <?php foreach ($row['preferensi'] as $p): ?>
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-gold-400 text-brand-900 text-xs font-bold"><?= esc($p['prioritas']) ?></span>
                            <span class="text-slate-700"><?= esc($p['mapel']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p class="text-slate-400 text-sm">Tidak ada.</p><?php endif; ?>
        </div>

        <!-- Ketersediaan Hari -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-800 mb-3">Ketersediaan Hari</h2>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <?php foreach ($hariList as $h): $v = $row['ketersediaan_hari'][$h] ?? '-'; ?>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5">
                        <span class="text-slate-600"><?= $h ?></span>
                        <span class="font-semibold <?= $v==='Ya'?'text-green-600':($v==='Tidak'?'text-red-500':'text-slate-300') ?>"><?= esc($v) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Keterangan -->
        <?php if (! empty($row['keterangan_tambahan'])): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-bold text-slate-800 mb-2">Keterangan Tambahan</h2>
                <p class="text-sm text-slate-600 whitespace-pre-line"><?= esc($row['keterangan_tambahan']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
