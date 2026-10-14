<?php
/**
 * Impor riwayat PKL lama dari Excel (Operator/Admin): format contoh, atau format sekolah "DATA SISWA PRAKERIN"
 * (satu lembar per kelas — sama dengan unduhan menu Data Prakerin).
 *
 * @var ?array  $pratinjau  hasil PklImpor::baca (null = belum unggah)
 * @var string  $token
 */

use App\Libraries\IsianBantu;

$sekolah    = $pratinjau !== null && ($pratinjau['format'] ?? '') === 'sekolah';
$labelBaris = static fn (array $g): string => isset($g['lembar']) ? $g['lembar'] . ' · baris ' . (int) $g['baris'] : 'Baris ' . (int) $g['baris'];
$periode    = static fn (array $k): string => $k['mulai'] !== null || $k['selesai'] !== null
    ? IsianBantu::tanggalIndo($k['mulai']) . ' – ' . IsianBantu::tanggalIndo($k['selesai'])
    : 'tanpa tanggal';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_impor_v3',
    'helpTitle' => 'Impor Riwayat PKL',
    'helpBody'  => '<p>Untuk memasukkan data siswa yang <b>sudah dapat tempat PKL di luar sistem</b>, supaya tercatat sudah PKL, tampil di Data Prakerin, dan tidak bisa mengajukan lagi.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Format sekolah "DATA SISWA PRAKERIN"</b> (satu lembar per kelas, kolom NO, NAMA, Mitra DI/DU, Mulai, Selesai, Lamanya PKL) bisa langsung diunggah. Nama lembar = nama kelas; siswa dikenali dari <b>nama</b> di kelas itu.</li>'
        . '<li>Atau pakai <b>contoh berkas</b>: satu baris per siswa, siswa dikenali dari <b>NIS</b> (atau Nama + Kelas).</li>'
        . '<li>Anda melihat <b>pratinjau dulu</b>; belum ada yang tersimpan sampai menekan Simpan. Hasilnya berstatus Disetujui dan ditandai <b>"diimpor"</b> — bukan persetujuan (ACC) lewat sistem.</li>'
        . '<li>Siswa yang sudah punya ajuan PKL di sistem <b>tidak diubah</b>. Mengunggah berkas yang sama dua kali tidak menggandakan data.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'impor', 'hitungTab' => $hitungTab]) ?>

<div class="mx-auto max-w-4xl space-y-5">
    <section class="rise rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-800">1. Siapkan &amp; unggah berkas</h2>
        <ul class="mt-2 space-y-1.5 text-sm text-slate-500">
            <li><b class="text-slate-700">Format sekolah "DATA SISWA PRAKERIN"</b> — satu lembar per kelas (nama lembar = nama kelas), kolom <b>NO, NAMA, Mitra DI/DU, Mulai, Selesai, Lamanya PKL</b>. Baris tanpa Mitra dilewati. <a href="<?= site_url('admin/pkl/prakerin') ?>" class="font-semibold text-brand-700 hover:underline">Unduh format ini dari Data Prakerin →</a></li>
            <li><b class="text-slate-700">Format contoh</b> — kolom <b>NIS, Nama, Kelas, Perusahaan, Alamat, Kota, Telepon, Kontak, Jabatan, Mulai, Selesai</b>. Wajib: Perusahaan, dan NIS (atau Nama + Kelas).</li>
        </ul>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <a href="<?= site_url('admin/pkl/impor/contoh') ?>" class="inline-flex shrink-0 justify-center rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">⬇ Unduh contoh berkas</a>
            <form method="post" action="<?= site_url('admin/pkl/impor/pratinjau') ?>" enctype="multipart/form-data" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <?= csrf_field() ?>
                <input type="file" name="berkas" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3.5 file:py-2 file:font-semibold file:text-brand-700">
                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Lihat pratinjau</button>
            </form>
        </div>
    </section>

    <?php if ($pratinjau !== null && ! $sekolah): $r = $pratinjau['ringkas']; ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-800">2. Pratinjau</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach ([['Baris dibaca', $r['baris'], 'text-slate-800'], ['Ajuan akan dibuat', $r['kelompok'], 'text-green-600'], ['Siswa tercatat PKL', $r['siswa'], 'text-brand-700'], ['Baris bermasalah', $r['galat'], $r['galat'] > 0 ? 'text-red-600' : 'text-slate-400']] as [$l, $n, $w]): ?>
                    <div class="rounded-xl border border-slate-200 px-4 py-3"><p class="text-xs font-semibold text-slate-400"><?= esc($l) ?></p><p class="mt-0.5 text-2xl font-extrabold <?= $w ?>"><?= (int) $n ?></p></div>
                <?php endforeach; ?>
            </div>

            <?php if ($pratinjau['galat'] !== []): ?>
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-bold text-red-800">Baris ini TIDAK akan diimpor — perbaiki di Excel lalu unggah lagi bila perlu:</p>
                    <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm text-red-700">
                        <?php foreach ($pratinjau['galat'] as $g): ?><li><b><?= esc($labelBaris($g)) ?>:</b> <?= esc($g['pesan']) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($pratinjau['kelompok'] !== []): ?>
                <div class="mt-4 max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                    <?php foreach ($pratinjau['kelompok'] as $k): ?>
                        <div class="px-4 py-3">
                            <p class="font-semibold text-slate-800"><?= esc($k['perusahaan']) ?> <span class="text-xs font-normal text-slate-400"><?= esc($k['kota']) ?></span></p>
                            <p class="text-xs text-slate-500"><?= esc(IsianBantu::tanggalIndo($k['mulai'])) ?> – <?= esc(IsianBantu::tanggalIndo($k['selesai'])) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= esc(implode(', ', array_map(static fn ($s) => $s['nama'] . ' (' . $s['kelas'] . ')', $k['siswa']))) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= site_url('admin/pkl/impor/simpan') ?>" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
                      x-data="{ proses: false }" @submit="proses = true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
                    <p class="text-xs text-slate-500 sm:mr-auto">Hasilnya berstatus <b>Disetujui</b>; siswanya langsung terkunci dan tak bisa mengajukan lagi.</p>
                    <button type="submit" :disabled="proses" class="rounded-xl bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-60">
                        <span x-text="proses ? 'Menyimpan…' : 'Simpan <?= (int) $r['kelompok'] ?> ajuan'"></span>
                    </button>
                </form>
            <?php else: ?>
                <p class="mt-4 text-center text-sm font-semibold text-slate-500">Tidak ada baris yang bisa diimpor.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($sekolah):
        $r       = $pratinjau['ringkas'];
        $beda    = array_values(array_filter($pratinjau['sudah'], static fn (array $s): bool => $s['beda']));
        $samaMit = array_values(array_filter($pratinjau['sudah'], static fn (array $s): bool => ! $s['beda']));
        $perKls  = [];
        foreach ($pratinjau['kelompok'] as $k) {
            $perKls[$k['kelas']][] = $k;
        }
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-800">2. Pratinjau — format DATA SISWA PRAKERIN</h2>
            <p class="mt-1 text-sm text-slate-500">
                <?= count($pratinjau['lembar']) ?> lembar (kelas)<?= ! empty($pratinjau['tahun_ajaran']) ? ' · Tahun Pelajaran <b>' . esc($pratinjau['tahun_ajaran']) . '</b>' : '' ?>
                · satu ajuan berisi siswa sekelas dengan mitra &amp; tanggal yang sama, paling banyak <?= (int) $pratinjau['maks_siswa'] ?> siswa.
            </p>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <?php foreach ([
                    ['Nama dibaca', $r['baris'], 'text-slate-800', $r['mitra'] . ' ada mitra'],
                    ['Siap diimpor', $r['siswa'], 'text-green-600', 'nama cocok dengan Data Siswa'],
                    ['Nama mirip — cek dulu', $r['saran'], $r['saran'] > 0 ? 'text-amber-600' : 'text-slate-400', 'centang di bawah bila benar'],
                    ['Sudah ada di sistem', $r['sudah'], 'text-brand-700', count($beda) > 0 ? count($beda) . ' beda mitra — tidak diubah' : 'tidak diubah'],
                    ['Belum ada mitra', $r['tanpa_mitra'] + $r['lewati'], 'text-slate-500', $r['lewati'] > 0 ? 'termasuk ' . $r['lewati'] . ' bertulis "PKL"/"-"' : 'kolom Mitra kosong'],
                    ['Bermasalah', $r['galat'], $r['galat'] > 0 ? 'text-red-600' : 'text-slate-400', 'tidak diimpor'],
                ] as [$l, $n, $w, $ket]): ?>
                    <div class="rounded-xl border border-slate-200 px-4 py-3">
                        <p class="text-xs font-semibold text-slate-400"><?= esc($l) ?></p>
                        <p class="mt-0.5 text-2xl font-extrabold <?= $w ?>"><?= (int) $n ?></p>
                        <p class="text-[11px] text-slate-400"><?= esc($ket) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-slate-50 text-left text-xs text-slate-500">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Lembar</th>
                            <th class="px-3 py-2 font-semibold">Kelas di sistem</th>
                            <th class="px-3 py-2 text-right font-semibold">Nama</th>
                            <th class="px-3 py-2 text-right font-semibold">Ada mitra</th>
                            <th class="px-3 py-2 text-right font-semibold">Siap</th>
                            <th class="px-3 py-2 text-right font-semibold">Mirip</th>
                            <th class="px-3 py-2 text-right font-semibold">Sudah ada</th>
                            <th class="px-3 py-2 text-right font-semibold">"PKL"/"-"</th>
                            <th class="px-3 py-2 text-right font-semibold">Masalah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pratinjau['lembar'] as $l): ?>
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-800"><?= esc($l['judul']) ?></td>
                                <td class="px-3 py-2"><?= $l['kelas'] !== null ? esc($l['kelas']) : '<span class="font-semibold text-red-600">tidak dikenali</span>' ?></td>
                                <td class="px-3 py-2 text-right text-slate-600"><?= (int) $l['nama'] ?></td>
                                <td class="px-3 py-2 text-right text-slate-600"><?= (int) $l['mitra'] ?></td>
                                <td class="px-3 py-2 text-right font-semibold text-green-700"><?= (int) $l['siap'] ?></td>
                                <td class="px-3 py-2 text-right <?= $l['saran'] > 0 ? 'font-semibold text-amber-600' : 'text-slate-300' ?>"><?= (int) $l['saran'] ?></td>
                                <td class="px-3 py-2 text-right text-slate-600"><?= (int) $l['sudah'] ?></td>
                                <td class="px-3 py-2 text-right text-slate-600"><?= (int) $l['lewati'] ?></td>
                                <td class="px-3 py-2 text-right <?= $l['galat'] > 0 ? 'font-semibold text-red-600' : 'text-slate-300' ?>"><?= (int) $l['galat'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pratinjau['galat'] !== []): ?>
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-bold text-red-800">Baris ini TIDAK akan diimpor — perbaiki di Excel lalu unggah lagi bila perlu:</p>
                    <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm text-red-700">
                        <?php foreach ($pratinjau['galat'] as $g): ?><li><b><?= esc($labelBaris($g)) ?>:</b> <?= esc($g['pesan']) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($beda !== []): ?>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-900">Sudah punya ajuan di sistem dengan mitra BERBEDA — tidak diubah (<?= count($beda) ?>):</p>
                    <p class="text-xs text-amber-800">Bila Excel yang benar, ubah ajuannya lewat Kotak Masuk (buka nomor ajuannya).</p>
                    <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm text-amber-900">
                        <?php foreach ($beda as $s): ?><li><b><?= esc($labelBaris($s)) ?>: <?= esc($s['nama']) ?></b> — di sistem <b><?= esc($s['sistem']) ?></b> (<?= esc($s['kode']) ?>, <?= esc($s['status']) ?>), di Excel <b><?= esc($s['mitra']) ?></b></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($pratinjau['kelompok'] !== []): ?>
                <form method="post" action="<?= site_url('admin/pkl/impor/simpan') ?>" class="mt-4 space-y-4" x-data="{ proses: false }" @submit="proses = true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

                    <?php if ($pratinjau['saran'] !== []): ?>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-bold text-amber-900">Nama mirip — centang bila orang yang sama (<?= count($pratinjau['saran']) ?>)</p>
                            <p class="text-xs text-amber-800">Ejaan di Excel sedikit beda dengan Data Siswa. Yang <b>tidak dicentang</b> tidak diimpor.</p>
                            <ul class="mt-2 max-h-72 space-y-1.5 overflow-y-auto">
                                <?php foreach ($pratinjau['saran'] as $s): ?>
                                    <li>
                                        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="pakai[]" value="<?= esc($s['kunci'], 'attr') ?>" <?= $s['centang'] ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                            <span><b><?= esc($labelBaris($s)) ?>:</b> Excel "<?= esc($s['excel']) ?>" → Data Siswa "<b><?= esc($s['sistem']) ?></b>"<?= $s['kelas'] !== '' ? ' (' . esc($s['kelas']) . ')' : '' ?> · mirip <?= (int) $s['skor'] ?>% · <?= esc($s['mitra']) ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <details class="rounded-xl border border-slate-200">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Lihat ajuan yang akan dibuat (<?= count($pratinjau['kelompok']) ?>)</summary>
                        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto border-t border-slate-100">
                            <?php foreach ($perKls as $kls => $daftar): ?>
                                <p class="bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-500"><?= esc((string) $kls) ?></p>
                                <?php foreach ($daftar as $k): ?>
                                    <div class="px-4 py-2.5">
                                        <p class="font-semibold text-slate-800"><?= esc($k['perusahaan']) ?> <span class="text-xs font-normal text-slate-400"><?= esc($periode($k)) ?></span></p>
                                        <p class="mt-0.5 text-sm text-slate-600"><?= esc(implode(', ', array_map(static fn ($s) => $s['nama'] . (! empty($s['saran']) ? ' (nama mirip)' : '') . (isset($s['catatan']) ? ' (' . $s['catatan'] . ')' : ''), $k['siswa']))) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <?php if ($pratinjau['lewati'] !== [] || $samaMit !== []): ?>
                        <details class="rounded-xl border border-slate-200">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Lihat yang dilewati (<?= count($pratinjau['lewati']) + count($samaMit) ?>)</summary>
                            <ul class="max-h-72 space-y-1 overflow-y-auto border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
                                <?php foreach ($pratinjau['lewati'] as $s): ?><li><b><?= esc($labelBaris($s)) ?>:</b> <?= esc($s['nama']) ?> — Mitra "<?= esc($s['mitra']) ?>" belum jelas</li><?php endforeach; ?>
                                <?php foreach ($samaMit as $s): ?><li><b><?= esc($labelBaris($s)) ?>:</b> <?= esc($s['nama']) ?> — sudah ada di sistem (<?= esc($s['kode']) ?>, <?= esc($s['status']) ?>)</li><?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <p class="text-xs text-slate-500 sm:mr-auto">Hasilnya berstatus <b>Disetujui</b> (ditandai "diimpor"); siswanya langsung tercatat sudah PKL dan tak bisa mengajukan lagi.</p>
                        <button type="submit" :disabled="proses" class="rounded-xl bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-60">
                            <span x-text="proses ? 'Menyimpan…' : 'Simpan ke sistem'"></span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p class="mt-4 text-center text-sm font-semibold text-slate-500">Tidak ada siswa baru yang bisa diimpor<?= $r['sudah'] > 0 ? ' — yang bermitra sudah tercatat di sistem' : '' ?>.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
