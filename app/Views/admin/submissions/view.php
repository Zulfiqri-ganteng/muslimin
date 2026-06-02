<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
    $hariList = ['Senin','Selasa','Rabu','Kamis','Jumat'];
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
                        <p class="text-sm text-slate-400">Guru Mapel <?= esc($row['guru_mapel'] ?: '-') ?></p>
                        <?php if (empty($row['bersedia_mengajar'])): ?>
                            <span class="mt-1 inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700">TIDAK BERSEDIA</span>
                        <?php else: ?>
                            <span class="mt-1 inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700">BERSEDIA</span>
                        <?php endif; ?>
                    </div>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <?php
                    $fields = [
                        'Nomor HP'            => $row['nomor_hp'] ?: '-',
                        'Pendidikan Terakhir' => $row['pendidikan_terakhir'] ?: '-',
                        'Status Kepegawaian'  => $row['status_kepegawaian'] ?: '-',
                        'Dikirim pada'        => date('d/m/Y H:i', strtotime($row['created_at'])),
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
                            <th class="pb-2 w-8">No</th><th class="pb-2">Mata Pelajaran</th><th class="pb-2">Kelas</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($row['mapel_diampu'] as $i => $m): ?>
                                <tr><td class="py-2 text-slate-400"><?= $i+1 ?></td><td class="py-2 font-medium text-slate-700"><?= esc($m['mapel']) ?></td><td class="py-2 text-slate-600"><?= esc($m['kelas']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?><p class="text-slate-400 text-sm">Tidak ada data.</p><?php endif; ?>
            </div>
        </div>

        <!-- Tugas Tambahan & Jam -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-bold text-slate-800 mb-3">Tugas Tambahan</h2>
                <?php if (! empty($row['tugas_tambahan'])): ?>
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-green-700"><span>✓</span> Bersedia menerima tugas tambahan</p>
                <?php else: ?>
                    <p class="text-sm text-slate-500">Tidak bersedia / belum mengisi.</p>
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

        <!-- Tautan Revisi (muncul bila status = Perlu Revisi) -->
        <?php if ($row['status'] === 'ditolak' && ! empty($row['edit_token'])):
            $revisiUrl = site_url('revisi/' . $row['edit_token']);
            $waNum = preg_replace('/[^0-9]/', '', (string) $row['nomor_hp']);
            if (str_starts_with($waNum, '0')) { $waNum = '62' . substr($waNum, 1); }
            $waMsg = "Yth. Bapak/Ibu " . $row['nama_lengkap'] . ",\n\nMohon perbaiki data kesediaan mengajar Anda melalui tautan berikut:\n" . $revisiUrl;
            if (! empty($row['catatan_admin'])) { $waMsg .= "\n\nCatatan: " . $row['catatan_admin']; }
            $waLink = 'https://wa.me/' . $waNum . '?text=' . rawurlencode($waMsg);
        ?>
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-800 mb-1">Tautan Revisi Guru</h2>
            <p class="text-xs text-slate-400 mb-3">Kirim tautan ini ke guru. Form akan ter-isi data lama, dan tautan hanya berlaku sekali pakai.</p>
            <div class="flex items-stretch gap-2">
                <input id="revisiUrl" type="text" readonly value="<?= esc($revisiUrl, 'attr') ?>" class="flex-1 min-w-0 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-600 outline-none">
                <button type="button" id="copyRevisi" class="shrink-0 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3">Salin</button>
            </div>
            <a href="<?= esc($waLink, 'attr') ?>" target="_blank" rel="noopener" class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2.5 transition">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 018.413 3.488 11.82 11.82 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.039zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                Kirim via WhatsApp
            </a>
        </div>
        <script>
            document.getElementById('copyRevisi')?.addEventListener('click', () => {
                const i = document.getElementById('revisiUrl');
                i.select();
                navigator.clipboard?.writeText(i.value);
                const b = document.getElementById('copyRevisi');
                const t = b.textContent; b.textContent = 'Tersalin!';
                setTimeout(() => b.textContent = t, 1500);
            });
        </script>
        <?php endif; ?>

        <!-- Ketersediaan Hari -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-bold text-slate-800 mb-3">Ketersediaan Hari</h2>
            <div class="space-y-2 text-sm">
                <?php foreach ($hariList as $h): $sesi = $row['ketersediaan_hari'][$h] ?? []; ?>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5">
                        <span class="text-slate-600"><?= $h ?></span>
                        <span class="font-semibold <?= $sesi ? 'text-green-600' : 'text-slate-300' ?>"><?= $sesi ? esc(implode(' & ', (array) $sesi)) : '-' ?></span>
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
