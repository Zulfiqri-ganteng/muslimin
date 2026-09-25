<?php
/**
 * Isian Biodata Siswa — halaman utama admin.
 *
 * @var string                        $tab        menunggu|perbaikan|disetujui|belum|kelas
 * @var int                           $kelasId
 * @var string                        $kelasNama
 * @var string                        $q
 * @var array                         $rows
 * @var int                           $total
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var int                           $mulai      nomor urut awal halaman ini
 * @var array                         $ringkas    total/sudah/menunggu/disetujui/perbaikan/belum/persen
 * @var array                         $perKelas
 * @var array<int,string>             $kelasOpts
 * @var array                         $setting
 * @var bool                          $terbuka
 * @var string                        $tautan       subdomain untuk siswa
 * @var string                        $tautanAlt    cadangan di domain utama
 * @var string                        $batasTeks    batas waktu siap baca ('' bila tanpa batas)
 * @var string                        $pesanBagikan pesan WA ajakan mengisi (BiodataPesan)
 * @var string                        $pesanBelum   pesan WA daftar belum mengisi satu kelas ('' bila tak berlaku)
 * @var int                           $maksMassal
 */
$fmtAngka = static fn (int $n): string => number_format($n, 0, ',', '.');
$fmtTgl   = static fn (?string $s): string => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
$batas    = $setting['biodata_tutup'] ?? null;

$tabs = [
    'menunggu'  => ['Menunggu Verifikasi', $ringkas['menunggu']],
    'perbaikan' => ['Perlu Perbaikan', $ringkas['perbaikan']],
    'disetujui' => ['Disetujui', $ringkas['disetujui']],
    'belum'     => ['Belum Mengisi', $ringkas['belum']],
    'kelas'     => ['Rekap per Kelas', null],
];
$urlTab = static fn (string $t): string => site_url('admin/biodata') . '?' . http_build_query(array_filter([
    'tab' => $t, 'kelas_id' => $kelasId ?: '', 'q' => $q,
], static fn ($v) => $v !== ''));
$urlDetail = static fn (int $id): string => site_url('admin/biodata/' . $id) . ($kelasId > 0 ? '?kelas_id=' . $kelasId : '');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'biodata',
    'helpTitle' => 'Isian Biodata Siswa',
    'helpBody'  => '<p>Siswa mengisi biodatanya sendiri lewat tautan (tanpa login): <b>pilih kelas → pilih nama → isi → kirim</b>. Satu siswa hanya bisa mengirim <b>satu kali</b>; NISN tidak bisa dipakai dua siswa.</p>
        <p class="mt-1">• Isian <b>tidak langsung</b> mengubah Master Siswa. Buka <b>Periksa</b> untuk membandingkan data lama dengan isian siswa, lalu <b>Setujui</b> — baru saat itu datanya masuk ke Master Siswa (web &amp; aplikasi Android).<br>
        • Banyak isian sekaligus? Centang lalu <b>Setujui terpilih</b>, atau <b>Setujui semua</b> (maks. ' . (int) $maksMassal . ' per klik).<br>
        • Ada yang salah? <b>Kembalikan</b> dengan catatan — siswa membuka isiannya lagi di form memakai NISN / tanggal lahir. <b>Hapus isian</b> membuat siswa mengisi dari nol.<br>
        • Tab <b>Belum Mengisi</b> + pilih satu kelas → muncul pesan WhatsApp berisi daftar nama untuk dikirim ke wali kelas.</p>
        <p class="mt-1">• Form bisa <b>dibuka/ditutup</b> kapan saja, dan bisa diberi <b>batas waktu</b> agar tertutup otomatis.</p>
        <p class="mt-1">• <b>Unduh Laporan</b> — Excel siap cetak berisi rekap per kelas dan daftar nama tiap kelas (lengkap / belum lengkap + kolom yang masih kurang), lengkap dengan kop dan tanda tangan. Bisa semua kelas atau satu kelas; di tab <b>Rekap per Kelas</b> ada tautan <b>Excel</b> per kelas. “Lengkap” dihitung dari data <b>Master Siswa</b>, jadi isian yang belum disetujui belum terhitung lengkap.</p>',
]) ?>

<div x-data="biodataAdmin" class="space-y-5">

    <!-- ================= Form & tautan ================= -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-bold text-slate-800">Tautan Form untuk Siswa</h2>
                <?php if ($terbuka): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>DIBUKA</span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"><span class="h-2 w-2 rounded-full bg-slate-400"></span>DITUTUP</span>
                <?php endif; ?>
            </div>
            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                <input type="text" readonly value="<?= esc($tautan, 'attr') ?>" class="flex-1 min-w-0 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-brand-700" @focus="$event.target.select()" aria-label="Tautan form biodata">
                <div class="flex gap-2">
                    <button type="button" @click="salin(<?= esc(json_encode($tautan), 'attr') ?>, 'tautan')" class="flex-1 sm:flex-none rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <span x-text="tersalin === 'tautan' ? '✓ Tersalin' : 'Salin'">Salin</span>
                    </button>
                    <button type="button" @click="wa($refs.pesanBagikan.value)" class="flex-1 sm:flex-none rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Bagikan ke WA</button>
                </div>
            </div>
            <textarea x-ref="pesanBagikan" class="hidden" aria-hidden="true"><?= esc($pesanBagikan) ?></textarea>
            <p class="mt-2 text-xs text-slate-500">
                Cadangan (bila subdomain bermasalah): <a href="<?= esc($tautanAlt, 'attr') ?>" target="_blank" rel="noopener" class="font-semibold text-brand-600 hover:underline"><?= esc($tautanAlt) ?></a>
            </p>
            <?php if (! $terbuka): ?>
                <p class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">Form sedang <b>ditutup</b> — siswa yang membuka tautan melihat pesan “Pengisian Sedang Ditutup”. Buka lewat pengaturan di samping.</p>
            <?php elseif ($batasTeks !== ''): ?>
                <p class="mt-3 text-xs text-slate-500">Tertutup otomatis pada <b><?= esc($batasTeks) ?></b>.</p>
            <?php endif; ?>
            <ol class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-slate-600">
                <li class="rounded-lg bg-slate-50 px-3 py-2"><b class="text-brand-700">1.</b> Centang <b>Buka form isian</b> lalu simpan.</li>
                <li class="rounded-lg bg-slate-50 px-3 py-2"><b class="text-brand-700">2.</b> <b>Bagikan ke WA</b> grup siswa / wali kelas.</li>
                <li class="rounded-lg bg-slate-50 px-3 py-2"><b class="text-brand-700">3.</b> Periksa &amp; <b>setujui</b> isian di tab Menunggu.</li>
            </ol>
        </div>

        <form method="post" action="<?= site_url('admin/biodata/pengaturan') ?>" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <?= csrf_field() ?>
            <h2 class="font-bold text-slate-800">Pengaturan Pengisian</h2>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="biodata_open" value="1" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= ! empty($setting['biodata_open']) ? 'checked' : '' ?>>
                <span class="text-sm text-slate-700"><b>Buka form isian</b><br><span class="text-xs text-slate-500">Matikan untuk menutup pengisian kapan saja.</span></span>
            </label>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="batasTutup">Batas waktu <span class="text-slate-400 font-normal">(boleh kosong)</span></label>
                <input type="datetime-local" id="batasTutup" name="biodata_tutup" value="<?= ! empty($batas) ? esc(date('Y-m-d\TH:i', strtotime($batas)), 'attr') : '' ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                <p class="text-xs text-slate-400 mt-1">Lewat waktu ini form tertutup sendiri.</p>
            </div>
            <button class="w-full rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan Pengaturan</button>
        </form>
    </div>

    <!-- ================= Angka utama ================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ([
                ['Siswa aktif', $ringkas['total'], 'seluruh kelas', null],
                ['Sudah mengisi', $ringkas['sudah'], $ringkas['persen_teks'] . ' dari siswa aktif', null],
                ['Menunggu verifikasi', $ringkas['menunggu'], 'perlu diperiksa', 'bg-blue-500'],
                ['Disetujui', $ringkas['disetujui'], 'sudah masuk Master Siswa', 'bg-emerald-500'],
                ['Perlu perbaikan', $ringkas['perbaikan'], 'menunggu siswa', 'bg-amber-500'],
                ['Belum mengisi', $ringkas['belum'], 'perlu dikejar', 'bg-slate-400'],
            ] as [$label, $nilai, $ket, $titik]): ?>
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <?php if ($titik): ?><span class="h-2 w-2 rounded-full <?= $titik ?>" aria-hidden="true"></span><?php endif; ?>
                        <?= esc($label) ?>
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-800 tabular-nums"><?= $fmtAngka($nilai) ?></p>
                    <p class="text-xs text-slate-400"><?= esc($ket) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-5">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-slate-700">Progres pengisian</span>
                <span class="font-bold text-slate-800 tabular-nums"><?= esc($ringkas['persen_teks']) ?> <span class="font-normal text-slate-500">(<?= $fmtAngka($ringkas['sudah']) ?> dari <?= $fmtAngka($ringkas['total']) ?> siswa)</span></span>
            </div>
            <div class="mt-2 h-3 w-full rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow="<?= $ringkas['rasio'] ?>" aria-valuemin="0" aria-valuemax="100" title="<?= esc($ringkas['persen_teks'], 'attr') ?> siswa sudah mengisi">
                <div class="h-full rounded-full bg-brand-500" style="width: <?= $ringkas['rasio'] ?>%"></div>
            </div>
        </div>

        <!-- Laporan Excel siap cetak -->
        <form method="get" action="<?= site_url('admin/biodata/laporan') ?>" data-noload
              class="mt-5 flex flex-col sm:flex-row sm:items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-700">Laporan kelengkapan biodata (Excel, siap cetak)</p>
                <p class="text-xs text-slate-500">Rekap per kelas + daftar nama tiap kelas: lengkap / belum lengkap &amp; kolom yang masih kurang, dengan kop &amp; tanda tangan.</p>
            </div>
            <select name="kelas_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 outline-none" aria-label="Kelas untuk laporan">
                <option value="">Semua kelas</option>
                <?php foreach ($kelasOpts as $id => $nama): ?>
                    <option value="<?= (int) $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-600 bg-white hover:bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Unduh Laporan
            </button>
        </form>
    </div>

    <!-- ================= Tab + saringan ================= -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3" aria-label="Tab isian biodata">
            <?php foreach ($tabs as $kunci => [$label, $jumlah]): $aktif = $tab === $kunci; ?>
                <a href="<?= esc($urlTab($kunci), 'attr') ?>" class="shrink-0 rounded-t-lg px-4 py-2.5 text-sm font-semibold border-b-2 <?= $aktif ? 'border-brand-600 text-brand-700 bg-brand-50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50' ?>">
                    <?= esc($label) ?><?php if ($jumlah !== null): ?> <span class="ml-1 rounded-full px-2 py-0.5 text-xs <?= $aktif ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' ?>"><?= $fmtAngka($jumlah) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tab !== 'kelas'): ?>
            <form method="get" action="<?= site_url('admin/biodata') ?>" data-noload class="flex flex-col sm:flex-row gap-2 px-5 py-4 border-b border-slate-100">
                <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                <select name="kelas_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none" aria-label="Saring kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelasOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama atau NISN…" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-semibold text-white">Cari</button>
                <?php if ($kelasId > 0 || $q !== ''): ?>
                    <a href="<?= site_url('admin/biodata') . '?tab=' . $tab ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($tab === 'menunggu'): ?>
            <!-- ============ MENUNGGU ============ -->
            <?php if ($total > 0): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 bg-slate-50 border-b border-slate-100">
                    <p class="text-sm text-slate-600"><b class="tabular-nums"><?= $fmtAngka($total) ?></b> isian menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama) : '' ?>. Antrean terlama di atas.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" form="formMassal" :disabled="jumlah === 0" data-confirm="Setujui isian yang dicentang? Data akan langsung masuk ke Master Siswa."
                                class="rounded-lg bg-brand-700 hover:bg-brand-800 disabled:opacity-40 disabled:cursor-not-allowed px-4 py-2 text-sm font-semibold text-white">
                            Setujui terpilih (<span x-text="jumlah">0</span>)
                        </button>
                        <form method="post" action="<?= site_url('admin/biodata/setujui-massal') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="all">
                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                            <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                            <?php $n = min($total, $maksMassal); ?>
                            <button data-confirm="Setujui <?= $n ?> isian menunggu<?= $kelasNama !== '' ? ' di ' . esc($kelasNama, 'attr') : '' ?> TANPA diperiksa satu per satu? Data akan langsung masuk ke Master Siswa."
                                    class="rounded-lg border border-brand-300 bg-white hover:bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700">
                                Setujui semua (<?= $n ?>)
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <form id="formMassal" method="post" action="<?= site_url('admin/biodata/setujui-massal') ?>" @change="hitung()">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="selected">
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?: '' ?>">
                <input type="hidden" name="q" value="<?= esc($q, 'attr') ?>">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-left">
                            <tr>
                                <th class="pl-5 pr-2 py-3 w-10"><input type="checkbox" @change="pilihSemua($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                                <th class="px-4 py-3 font-semibold">Nama Siswa</th>
                                <th class="px-4 py-3 font-semibold w-32">Kelas</th>
                                <th class="px-4 py-3 font-semibold w-40">Dikirim</th>
                                <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($rows === []): ?>
                                <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400"><?= $q !== '' || $kelasId ? 'Tidak ada isian menunggu yang cocok.' : 'Belum ada isian yang menunggu verifikasi.' ?></td></tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="pl-5 pr-2 py-3"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="bio-cek rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Pilih <?= esc($r['nama'], 'attr') ?>"></td>
                                    <td class="px-4 py-3">
                                        <a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-medium text-slate-800 hover:text-brand-700"><?= esc($r['nama']) ?></a>
                                        <div class="text-xs text-slate-400">NISN <?= esc($r['nisn'] ?? '—') ?><?php if ((int) $r['kirim_ke'] > 1): ?> · <span class="font-semibold text-amber-700">perbaikan ke-<?= (int) $r['kirim_ke'] - 1 ?></span><?php endif; ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-slate-600 tabular-nums"><?= $fmtTgl($r['updated_at']) ?></td>
                                    <td class="px-4 py-3 text-right"><a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 hover:bg-brand-100">Periksa</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

        <?php elseif ($tab === 'perbaikan' || $tab === 'disetujui'): ?>
            <!-- ============ PERBAIKAN / DISETUJUI ============ -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Nama Siswa</th>
                            <th class="px-4 py-3 font-semibold w-32">Kelas</th>
                            <th class="px-4 py-3 font-semibold"><?= $tab === 'perbaikan' ? 'Catatan untuk siswa' : 'NISN' ?></th>
                            <th class="px-4 py-3 font-semibold w-40"><?= $tab === 'perbaikan' ? 'Dikembalikan' : 'Disetujui' ?></th>
                            <th class="px-4 py-3 font-semibold w-24 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($rows === []): ?>
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Tidak ada data.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3"><a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="font-medium text-slate-800 hover:text-brand-700"><?= esc($r['nama']) ?></a></td>
                                <td class="px-4 py-3 text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= esc($tab === 'perbaikan' ? ($r['catatan_admin'] ?? '—') : ($r['nisn'] ?? '—')) ?></td>
                                <td class="px-4 py-3 text-slate-600 tabular-nums"><?= $fmtTgl($tab === 'perbaikan' ? $r['updated_at'] : $r['diverifikasi_at']) ?></td>
                                <td class="px-4 py-3 text-right"><a href="<?= esc($urlDetail((int) $r['id']), 'attr') ?>" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">Lihat</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'belum'): ?>
            <!-- ============ BELUM MENGISI ============ -->
            <?php if ($pesanBelum !== ''): ?>
                <div class="px-5 py-4 bg-emerald-50/60 border-b border-emerald-100">
                    <p class="text-sm font-semibold text-slate-700">Pesan WhatsApp untuk wali kelas / grup <?= esc($kelasNama) ?></p>
                    <textarea x-ref="pesanBelum" readonly rows="5" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 font-mono"><?= esc($pesanBelum) ?></textarea>
                    <div class="mt-2 flex gap-2">
                        <button type="button" @click="salin($refs.pesanBelum.value, 'belum')" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><span x-text="tersalin === 'belum' ? '✓ Tersalin' : 'Salin pesan'">Salin pesan</span></button>
                        <button type="button" @click="wa($refs.pesanBelum.value)" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Kirim ke WA</button>
                    </div>
                </div>
            <?php elseif ($kelasId === 0 && $total > 0): ?>
                <p class="px-5 py-3 text-xs text-slate-500 bg-slate-50 border-b border-slate-100">Pilih <b>satu kelas</b> untuk mendapatkan pesan WhatsApp berisi daftar nama yang belum mengisi.</p>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-5 py-3 font-semibold w-14">No</th>
                            <th class="px-4 py-3 font-semibold">Nama Siswa</th>
                            <th class="px-4 py-3 font-semibold w-14 text-center">JK</th>
                            <th class="px-4 py-3 font-semibold w-32">Kelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($rows === []): ?>
                            <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400"><?= $kelasId || $q !== '' ? 'Semua siswa yang cocok sudah mengisi. 🎉' : 'Semua siswa aktif sudah mengisi. 🎉' ?></td></tr>
                        <?php else: foreach ($rows as $i => $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2.5 text-slate-400 tabular-nums"><?= $mulai + $i + 1 ?></td>
                                <td class="px-4 py-2.5 font-medium text-slate-800"><?= esc($r['nama']) ?></td>
                                <td class="px-4 py-2.5 text-center text-slate-600"><?= esc($r['jenis_kelamin'] ?? '—') ?></td>
                                <td class="px-4 py-2.5 text-slate-700"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- ============ REKAP PER KELAS ============ -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Kelas</th>
                            <th class="px-4 py-3 font-semibold">Wali Kelas</th>
                            <th class="px-3 py-3 font-semibold text-right">Siswa</th>
                            <th class="px-3 py-3 font-semibold text-right">Menunggu</th>
                            <th class="px-3 py-3 font-semibold text-right">Disetujui</th>
                            <th class="px-3 py-3 font-semibold text-right">Perbaikan</th>
                            <th class="px-3 py-3 font-semibold text-right">Belum</th>
                            <th class="px-4 py-3 font-semibold w-48">Sudah mengisi</th>
                            <th class="px-4 py-3 font-semibold w-44 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($perKelas === []): ?>
                            <tr><td colspan="9" class="px-6 py-10 text-center text-slate-400">Belum ada kelas yang berisi siswa aktif.</td></tr>
                        <?php else: foreach ($perKelas as $k): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-2.5 font-semibold text-slate-800 whitespace-nowrap"><?= esc($k['nama_kelas']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600"><?= esc($k['wali'] ?? '—') ?></td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700"><?= $k['total'] ?></td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700"><?= $k['menunggu'] ?></td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700"><?= $k['disetujui'] ?></td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700"><?= $k['perbaikan'] ?></td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold <?= $k['belum'] > 0 ? 'text-slate-800' : 'text-slate-400' ?>"><?= $k['belum'] ?></td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2" title="<?= $k['sudah'] ?> dari <?= $k['total'] ?> siswa sudah mengisi">
                                        <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-brand-500" style="width: <?= $k['rasio'] ?>%"></div></div>
                                        <span class="w-10 text-right text-xs font-bold tabular-nums text-slate-700"><?= esc($k['persen_teks']) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <?php if ($k['belum'] > 0): ?>
                                        <a href="<?= site_url('admin/biodata') . '?tab=belum&kelas_id=' . (int) $k['id'] ?>" class="text-xs font-bold text-brand-600 hover:text-brand-800">Yang belum →</a>
                                    <?php else: ?>
                                        <span class="text-xs font-semibold text-emerald-700">Semua mengisi ✓</span>
                                    <?php endif; ?>
                                    <a href="<?= site_url('admin/biodata/laporan') . '?kelas_id=' . (int) $k['id'] ?>" title="Unduh laporan Excel kelas <?= esc($k['nama_kelas'], 'attr') ?>" class="ml-2 text-xs font-bold text-emerald-700 hover:text-emerald-900">Excel</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pager): ?>
            <div class="px-5 py-4 border-t border-slate-100">
                <?= $pager->only(['tab', 'kelas_id', 'q'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/biodata.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/biodata.js') ?>"></script>
<?= $this->endSection() ?>
