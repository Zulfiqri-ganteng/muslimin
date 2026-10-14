<?php
/**
 * Jadwal KBM per kelas — susun manual (tarik & lepas), generate otomatis, atau import Excel. Rombak tampilan F6: kartu, tombol
 * ber-ikon & dialog kit (ui_dialog_*), Export Excel/PDF sebagai dua tombol, pesan lewat toast kit. Komponen Alpine "jadwalGrid"
 * di assets/js/admin/jadwal/kbm.js (dulu fungsi global di skrip sebaris); konfigurasi lewat data-cfg → siap CSP.
 * Kunci sel = "<hari_id>-<jam_id>".
 *
 * @var array<int|string,string> $kelasOpts Opsi kelas
 * @var int                      $kelasId   Kelas terpilih (0 = belum)
 * @var array|null               $kelas     Baris kelas (nama_kelas, tingkat)
 * @var string                   $shift     pagi|siang
 * @var array                    $hari      Hari aktif (id, nama)
 * @var array                    $jam       Jam pelajaran shift ini (id, jam_ke, waktu_mulai, waktu_selesai, is_istirahat)
 * @var array                    $palet     Penugasan kelas (id, nama_mapel, guru_nama, jp, sisa)
 * @var array                    $grid      Sel terisi ["hari-jam" => sel]
 */
$kelas = $kelas ?? null;
$cfg   = [
    'kelasId' => (int) $kelasId,
    'cells'   => $grid ?: new \stdClass(),
    'palet'   => $palet ?: [],
    'url'     => [
        'place'  => site_url('admin/jadwal/place'),
        'move'   => site_url('admin/jadwal/move'),
        'remove' => site_url('admin/jadwal/remove'),
        'bulk'   => site_url('admin/jadwal/bulk-remove'),
    ],
];
$v = static fn (string $p): string => base_url($p) . '?v=' . @filemtime(FCPATH . $p);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_v2',
    'helpTitle' => 'Jadwal KBM — susun manual, generate, atau import Excel',
    'helpBody'  => '<p>Ada <b>3 cara</b> mengisi jadwal:</p>
        <p class="mt-1">1) <b>Manual (tarik & lepas):</b> pilih kelas, lalu seret kartu mapel dari panel kiri ke sel hari/jam. Seret antar sel untuk <b>memindah</b>; lepas di sel terisi untuk <b>menukar</b>. Klik tanda × untuk menghapus.</p>
        <p class="mt-1">2) <b>Generate Otomatis:</b> sistem mengisi sel secara <b>rapi &amp; berurutan</b> — JP mapel yang sama ditaruh bersebelahan, mengisi hari demi hari (bukan acak). Cocok untuk draf awal, lalu rapikan manual.</p>
        <p class="mt-1">3) <b>Import Excel:</b> bila jadwal asli sudah ada di Excel, klik <b>Import Excel</b> → unduh template (kolom: Kelas, Hari, Jam ke, Mapel, Guru) → unggah → <b>periksa/edit pratinjau</b> → Simpan. Bisa satu file untuk banyak kelas sekaligus.</p>
        <p class="mt-2">Sistem otomatis menolak/melewati bila: guru bentrok jam sama (R1), sel sudah terisi (R2), guru tak tersedia (R3), atau kuota JP penuh (R4). Angka <b>sisa</b> pada kartu = JP yang belum terpasang.</p>',
]) ?>

<div x-data="jadwalGrid" data-cfg="<?= esc((string) json_encode($cfg), 'attr') ?>" class="space-y-4">
    <!-- Bilah alat: pemilih kelas (kiri) + aksi (kanan) -->
    <div class="kartu kartu-isi flex flex-col gap-3 lg:flex-row lg:items-center">
        <form method="get" class="flex min-w-0 flex-1 items-center gap-2">
            <label class="shrink-0 text-sm font-medium text-gray-900 dark:text-white" for="pilihKelas">Kelas</label>
            <select id="pilihKelas" name="kelas_id" data-autosubmit class="inp max-w-xs min-w-44 flex-1">
                <?php if (empty($kelasOpts)): ?>
                    <option value="">— belum ada kelas —</option>
                <?php else: ?>
                    <option value=""<?= $kelasId ? '' : ' selected' ?>>— Pilih kelas —</option>
                    <?php foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php if ($kelas): ?>
                <span class="lencana lencana-abu hidden shrink-0 sm:inline-flex">
                    <?= esc($kelas['tingkat']) ?> ·
                    <span class="<?= $shift === 'pagi' ? 'text-yellow-700 dark:text-yellow-400' : 'text-brand-700 dark:text-brand-400' ?>"><?= esc(ucfirst($shift)) ?></span>
                </span>
            <?php endif; ?>
        </form>

        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <?php if ($kelas): ?>
                <button type="button" class="btn btn-utama" data-buka-dialog="generate-jadwal"><?= ikon('calendar-plus', 'h-4 w-4') ?>Generate</button>
            <?php endif; ?>
            <button type="button" class="btn btn-netral" data-buka-dialog="impor-jadwal"><?= ikon('upload', 'h-4 w-4') ?>Import Excel</button>
            <?php if ($kelas): ?>
                <a href="<?= site_url('admin/cetak/jadwal-kelas/' . (int) $kelasId . '/excel') ?>" class="btn btn-netral" title="Unduh jadwal kelas ini (Excel)"><?= ikon('download', 'h-4 w-4') ?>Excel</a>
                <a href="<?= site_url('admin/cetak/jadwal-kelas/' . (int) $kelasId . '/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral" title="Cetak jadwal kelas ini (PDF)"><?= ikon('printer', 'h-4 w-4') ?>PDF</a>
                <button type="button" class="btn" :class="selectMode ? 'btn-utama' : 'btn-netral'" @click="alihMassal()" :aria-pressed="selectMode">
                    <?= ikon('trash-bin', 'h-4 w-4') ?><span x-text="selectMode ? 'Selesai' : 'Hapus massal'">Hapus massal</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (! $kelasId): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Pilih kelas dulu', 'teks' => 'Pilih kelas di atas untuk menyusun jadwalnya (atau tambahkan kelas di menu Kelas).']) ?></div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada hari aktif atau jam pelajaran', 'teks' => 'Untuk shift ' . $shift . '. Atur di menu Hari & Jam Pelajaran.']) ?></div>
    <?php else: ?>
        <!-- ============ BAR HAPUS MASSAL ============ -->
        <div x-show="selectMode" x-cloak x-transition class="kartu kartu-isi flex flex-col gap-3 sm:flex-row sm:items-center">
            <p class="flex-1 text-sm text-gray-600 dark:text-gray-300">
                Mode hapus massal aktif. <b>Klik sel</b> yang ingin dihapus, lalu tekan <b>Hapus terpilih</b>.
                <span class="lencana lencana-merah ms-1"><span x-text="jumlahPilih()">0</span> dipilih</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-netral btn-sm" @click="pilihSemua()">Pilih semua</button>
                <button type="button" class="btn btn-netral btn-sm" @click="batalPilih()">Batal pilih</button>
                <button type="button" class="btn btn-bahaya btn-sm" @click="hapusTerpilih()" :disabled="tanpaPilihan()">
                    <?= ikon('trash-bin', 'h-4 w-4') ?><span>Hapus terpilih (<span x-text="jumlahPilih()">0</span>)</span>
                </button>
                <button type="button" class="btn btn-netral btn-sm text-red-700 dark:text-red-500" @click="kosongkanSemua()"><?= ikon('close-circle', 'h-4 w-4') ?>Kosongkan semua</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[260px_1fr]">
            <!-- ============ PALET MAPEL ============ -->
            <section class="kartu kartu-isi h-max lg:sticky lg:top-20" aria-labelledby="judul-palet">
                <h2 id="judul-palet" class="text-base font-semibold text-gray-900 dark:text-white">Mapel kelas ini</h2>
                <p class="mt-1 mb-3 text-xs text-gray-500 dark:text-gray-400">Seret ke grid. Angka = sisa JP.</p>
                <?php if (empty($palet)): ?>
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">Belum ada penugasan. Atur dulu di menu <a href="<?= site_url('admin/master/pengampu?kelas_id=' . (int) $kelasId) ?>" class="font-medium text-brand-700 underline dark:text-brand-400">Penugasan</a>.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <template x-for="p in palet" :key="p.id">
                            <div :draggable="p.sisa > 0" @dragstart="mulaiPalet($event, p)"
                                 :class="p.sisa > 0 ? 'cursor-grab hover:border-brand-400 dark:hover:border-brand-500' : 'cursor-not-allowed opacity-40'"
                                 class="rounded-lg border border-gray-200 bg-gray-50 p-2.5 transition-colors select-none dark:border-gray-600 dark:bg-gray-700">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="line-clamp-2 text-[13px] leading-tight font-semibold text-brand-700 dark:text-brand-400" :title="p.nama_mapel" x-text="p.nama_mapel"></span>
                                    <span class="lencana shrink-0 tabular-nums" :class="kelasSisa(p)" x-text="teksSisa(p)"></span>
                                </div>
                                <p class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400" :title="p.guru_nama" x-text="p.guru_nama"></p>
                            </div>
                        </template>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============ GRID ============ -->
            <section class="kartu overflow-x-auto" aria-label="Grid jadwal kelas">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-700 uppercase dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="w-36 border-b border-gray-200 px-3 py-3 text-left font-semibold dark:border-gray-600">Jam</th>
                            <?php foreach ($hari as $h): ?>
                                <th scope="col" class="min-w-[120px] border-b border-l border-gray-200 px-2 py-3 text-center font-semibold dark:border-gray-600"><?= esc($h['nama']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jam as $j): ?>
                            <?php if ($j['is_istirahat']): ?>
                                <tr>
                                    <td colspan="<?= count($hari) + 1 ?>" class="border-b border-gray-200 bg-yellow-50 py-1.5 text-center text-xs font-semibold tracking-wider text-yellow-800 dark:border-gray-700 dark:bg-yellow-900/20 dark:text-yellow-300">
                                        ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th scope="row" class="border-b border-gray-200 px-3 py-2 text-left align-top font-normal whitespace-nowrap dark:border-gray-700">
                                        <span class="font-semibold text-brand-700 dark:text-brand-400">Jam ke <?= esc($j['jam_ke']) ?></span><br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                    </th>
                                    <?php foreach ($hari as $h): $k = (int) $h['id'] . '-' . (int) $j['id']; ?>
                                        <td class="h-16 border-b border-l border-gray-200 p-1 align-top dark:border-gray-700"
                                            @dragover.prevent @dragenter.prevent @drop="lepas($event, <?= (int) $h['id'] ?>, <?= (int) $j['id'] ?>)">
                                            <!-- sel terisi -->
                                            <div x-show="ada('<?= $k ?>')" x-cloak :draggable="!selectMode" @dragstart="mulaiSel($event, '<?= $k ?>')" @click="klikSel('<?= $k ?>')"
                                                 class="group relative h-full rounded-lg border p-1.5 transition-colors" :class="kelasSel('<?= $k ?>')">
                                                <button type="button" x-show="!selectMode" @click.stop="hapusSel('<?= $k ?>')" title="Hapus" aria-label="Kosongkan sel ini"
                                                        class="absolute end-0.5 top-0.5 hidden h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white group-hover:flex focus-visible:flex"><?= ikon('close', 'h-3 w-3') ?></button>
                                                <span x-show="selectMode" aria-hidden="true" class="absolute end-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-sm border"
                                                      :class="terpilih('<?= $k ?>') ? 'border-red-600 bg-red-600 text-white' : 'border-gray-300 bg-white text-transparent dark:border-gray-500 dark:bg-gray-700'"><?= ikon('check', 'h-3 w-3') ?></span>
                                                <p class="line-clamp-2 pe-4 text-[13px] leading-tight font-semibold text-brand-700 dark:text-brand-300" :title="judulMapel('<?= $k ?>')" x-text="namaMapel('<?= $k ?>')"></p>
                                                <p class="truncate text-[11px] leading-tight text-gray-500 dark:text-gray-400" :title="judulGuru('<?= $k ?>')" x-text="namaGuru('<?= $k ?>')"></p>
                                            </div>
                                            <!-- sel kosong -->
                                            <div x-show="!ada('<?= $k ?>')" class="flex h-full min-h-12 items-center justify-center text-gray-300 dark:text-gray-600"><?= ikon('plus', 'h-4 w-4') ?></div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php if ($kelas): ?>
    <!-- Dialog Generate otomatis -->
    <?= ui_dialog_buka(['nama' => 'generate-jadwal', 'judul' => 'Generate jadwal otomatis']) ?>
    <form method="post" action="<?= site_url('admin/jadwal/generate') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">
        <div class="dialog-isi space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Sistem mengisi sel secara <b>rapi &amp; berurutan</b> (JP mapel sama bersebelahan, mengisi hari demi hari) berdasarkan penugasan kelas ini, sambil menghormati ketersediaan guru (R3) dan menghindari bentrok guru (R1). Penugasan yang tak muat akan dilaporkan.</p>
            <label class="flex items-start gap-2 text-sm text-gray-900 dark:text-white">
                <input type="checkbox" name="reset" value="1" class="cek mt-0.5">
                <span><b>Kosongkan dulu</b> jadwal kelas ini sebelum generate (buat ulang dari nol). Jika tidak dicentang, hanya mengisi sel yang masih kosong.</span>
            </label>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('calendar-plus', 'h-4 w-4') ?>Generate sekarang</button>
        </div>
    </form>
    <?= ui_dialog_tutup() ?>
<?php endif; ?>

<!-- Dialog Import Excel -->
<?= ui_dialog_buka(['nama' => 'impor-jadwal', 'judul' => 'Import jadwal dari Excel', 'ukuran' => 'lg']) ?>
<form method="post" action="<?= site_url('admin/jadwal/import-preview') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">
    <div class="dialog-isi space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Unggah file Excel berformat <b>grid</b> (Jam × Hari, sama seperti hasil cetak/Excel). Isi tiap sel dengan <b>nama/kode mapel</b> — guru diambil otomatis dari <b>Penugasan</b>. (Opsional: tulis <b>Mapel / Guru</b> untuk menentukan guru langsung.) Setelah diunggah Anda bisa <b>memeriksa &amp; mengedit</b> sebelum disimpan.</p>
        <div class="flex items-start gap-2 rounded-lg border border-brand-200 bg-brand-50 p-3 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
            <?= ikon('download', 'mt-0.5 h-4 w-4 shrink-0') ?>
            <p>Belum punya filenya? <a href="<?= site_url('admin/jadwal/template' . ($kelasId ? '?kelas_id=' . (int) $kelasId : '')) ?>" class="font-semibold underline">Unduh template Excel<?= $kelas ? ' (' . esc($kelas['nama_kelas']) . ')' : '' ?></a> lalu isi grid-nya sesuai jadwal asli.</p>
        </div>
        <div>
            <label class="lbl" for="berkasJadwal">Berkas Excel (.xlsx / .xls)</label>
            <input type="file" id="berkasJadwal" name="file" accept=".xlsx,.xls" required class="inp-berkas">
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Lanjut ke pratinjau</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/jadwal/kbm.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
