<?php
/**
 * Periksa satu isian biodata: Master Siswa (lama) vs isian siswa (baru).
 *
 * @var array        $row         baris biodata_isian
 * @var array|null   $siswa       baris siswa + nama_kelas (null bila siswa terhapus permanen)
 * @var array        $bagian      [judul => [[label, lama, baru, jenis(sama|baru|ubah), tetap]]]
 * @var array        $hitung      ['baru' => n, 'ubah' => n]
 * @var list<string> $peringatan
 * @var array|null   $admin       penyetuju (full_name, username)
 * @var int          $kelasId     konteks saringan kelas asal
 */
$status  = $row['status'];
$fmtTgl  = static fn (?string $s): string => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
$lencana = [
    'menunggu'  => ['Menunggu verifikasi', 'bg-blue-50 text-blue-700 border-blue-200'],
    'disetujui' => ['Disetujui', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'perbaikan' => ['Perlu perbaikan', 'bg-amber-50 text-amber-800 border-amber-200'],
][$status] ?? [$status, 'bg-slate-100 text-slate-600 border-slate-200'];
$kembali = site_url('admin/biodata') . '?' . http_build_query(array_filter(['tab' => $status, 'kelas_id' => $kelasId ?: '']));
$aksi    = site_url('admin/biodata/' . (int) $row['id']);
$judulLama = $status === 'disetujui' ? 'Sebelum disetujui' : 'Data sekarang (Master Siswa)';
$data      = \App\Models\BiodataIsianModel::decode($row);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="{ formKembali: false }" class="space-y-5 max-w-5xl">
    <a href="<?= esc($kembali, 'attr') ?>" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke daftar</a>

    <!-- Kepala -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Isian #<?= str_pad((string) (int) $row['id'], 5, '0', STR_PAD_LEFT) ?></p>
                <h1 class="mt-0.5 text-xl font-extrabold text-slate-800"><?= esc($siswa['nama'] ?? ($data['nama'] ?? '—')) ?></h1>
                <p class="text-sm text-slate-500">Kelas <?= esc($siswa['nama_kelas'] ?? '—') ?></p>
            </div>
            <span class="rounded-full border px-3 py-1 text-xs font-bold <?= $lencana[1] ?>"><?= esc($lencana[0]) ?></span>
        </div>
        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div><dt class="text-xs text-slate-400">Pertama dikirim</dt><dd class="font-semibold text-slate-700 tabular-nums"><?= $fmtTgl($row['created_at']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Terakhir diperbarui</dt><dd class="font-semibold text-slate-700 tabular-nums"><?= $fmtTgl($row['updated_at']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Kiriman ke</dt><dd class="font-semibold text-slate-700"><?= (int) $row['kirim_ke'] ?></dd></div>
            <div><dt class="text-xs text-slate-400">Alamat IP</dt><dd class="font-semibold text-slate-700 break-all"><?= esc($row['ip_address'] ?? '—') ?></dd></div>
        </dl>
        <?php if ($status === 'disetujui'): ?>
            <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-800">
                Disetujui <b><?= $fmtTgl($row['diverifikasi_at']) ?></b><?= $admin ? ' oleh <b>' . esc($admin['full_name'] ?: $admin['username']) . '</b>' : '' ?> — data sudah masuk Master Siswa.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($siswa === null): ?>
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Siswa ini sudah dihapus dari Master Siswa, jadi isian tidak bisa disetujui. Hapus isian ini bila tidak diperlukan.</div>
    <?php elseif (! empty($siswa['deleted_at'])): ?>
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">Siswa ini berada di tempat sampah Master Siswa (terhapus). Pulihkan dulu bila isiannya ingin disetujui.</div>
    <?php endif; ?>

    <?php if (! empty($row['catatan_admin'])): ?>
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900"><b>Catatan yang dikirim ke siswa:</b> <?= esc($row['catatan_admin']) ?></div>
    <?php endif; ?>

    <?php if ($peringatan !== []): ?>
        <div class="rounded-xl bg-amber-50 border border-amber-300 px-4 py-3">
            <p class="text-sm font-bold text-amber-900">Perhatikan sebelum menyetujui:</p>
            <ul class="mt-1 list-disc list-inside space-y-0.5 text-sm text-amber-900">
                <?php foreach ($peringatan as $p): ?><li><?= esc($p) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Pembanding -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Bandingkan Data</h2>
            <p class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <span class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">baru</span> <?= (int) $hitung['baru'] ?> kolom baru terisi
                <span class="ml-2 rounded border border-amber-300 bg-amber-50 px-2 py-0.5 font-bold text-amber-800">berubah</span> <?= (int) $hitung['ubah'] ?> kolom berubah
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-4 pr-2 sm:px-5 py-3 font-semibold w-[30%] sm:w-52">Kolom</th>
                        <th class="px-2 sm:px-4 py-3 font-semibold"><?= esc($judulLama) ?></th>
                        <th class="px-2 sm:px-4 py-3 font-semibold">Isian siswa</th>
                    </tr>
                </thead>
                <?php foreach ($bagian as $judul => $baris): ?>
                    <tbody class="divide-y divide-slate-100">
                        <tr><th colspan="3" class="bg-slate-100/70 px-5 py-2 text-left text-xs font-bold uppercase tracking-wide text-slate-600"><?= esc($judul) ?></th></tr>
                        <?php foreach ($baris as $b): ?>
                            <tr class="<?= $b['jenis'] === 'baru' ? 'bg-emerald-50/50' : ($b['jenis'] === 'ubah' ? 'bg-amber-50/70' : '') ?>">
                                <td class="pl-4 pr-2 sm:px-5 py-2.5 text-slate-500 break-words"><?= esc($b['label']) ?></td>
                                <td class="px-2 sm:px-4 py-2.5 break-words <?= $b['lama'] === '' ? 'text-slate-300' : 'text-slate-600' ?>"><?= $b['lama'] === '' ? '—' : esc($b['lama']) ?></td>
                                <td class="px-2 sm:px-4 py-2.5 break-words">
                                    <?php if ($b['tetap']): ?>
                                        <span class="text-slate-400">kosong</span> <span class="text-xs text-slate-400">— data lama dipertahankan</span>
                                    <?php else: ?>
                                        <span class="font-semibold <?= $b['baru'] === '' ? 'text-slate-300' : 'text-slate-800' ?>"><?= $b['baru'] === '' ? '—' : esc($b['baru']) ?></span>
                                        <?php if ($b['jenis'] === 'baru'): ?><span class="ml-1 rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">baru</span><?php endif; ?>
                                        <?php if ($b['jenis'] === 'ubah'): ?><span class="ml-1 rounded border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">berubah</span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- Keputusan — MENEMPEL di bawah layar: admin yang memeriksa ratusan
         isian tak perlu menggulir melewati 34 baris tiap kali. -->
    <?php if ($siswa !== null): ?>
        <div class="sticky bottom-3 z-20 bg-white rounded-2xl border border-slate-200 shadow-xl p-4 sm:p-5">
            <h2 class="hidden sm:block font-bold text-slate-800">Keputusan</h2>
            <div class="sm:mt-3 flex flex-wrap gap-2">
                <?php if ($status === 'menunggu' && empty($siswa['deleted_at'])): ?>
                    <form method="post" action="<?= $aksi ?>/setujui">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                        <button class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 sm:px-5 py-2.5 text-sm font-bold text-white">✓ Setujui<span class="hidden sm:inline"> &amp; masukkan ke Master Siswa</span></button>
                    </form>
                <?php endif; ?>
                <?php if ($status !== 'perbaikan'): ?>
                    <button type="button" @click="formKembali = !formKembali" class="rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 px-4 sm:px-5 py-2.5 text-sm font-bold text-amber-800">↩ Kembalikan<span class="hidden sm:inline"> untuk diperbaiki</span></button>
                <?php endif; ?>
                <form method="post" action="<?= $aksi ?>/hapus" class="ml-auto">
                    <?= csrf_field() ?>
                    <button data-confirm="Hapus isian ini? Siswa harus mengisi ulang dari awal. Data yang SUDAH masuk Master Siswa tidak ikut terhapus."
                            class="rounded-lg border border-red-200 px-3 sm:px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus<span class="hidden sm:inline"> isian</span></button>
                </form>
            </div>

            <form x-cloak x-show="formKembali" method="post" action="<?= $aksi ?>/kembalikan" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                <label class="block text-sm font-semibold text-slate-700" for="catatan">Apa yang harus diperbaiki siswa? <span class="text-red-500">*</span></label>
                <textarea id="catatan" name="catatan" rows="2" maxlength="255" required
                          class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
                          placeholder="Contoh: NISN salah, cek lagi di kartu pelajar. Tanggal lahir tidak sesuai KK."></textarea>
                <p class="mt-1 text-xs text-slate-500">Catatan ini tampil ke siswa saat membuka isiannya lagi (dengan NISN / tanggal lahir). Kabari siswa lewat wali kelas.</p>
                <button class="mt-3 rounded-lg bg-amber-600 hover:bg-amber-700 px-5 py-2 text-sm font-bold text-white">Kirim ke siswa untuk diperbaiki</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
