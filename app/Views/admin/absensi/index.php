<?php
/**
 * Absensi Guru harian (rombak tampilan F6: kartu, lencana, grup-tombol, tombol ber-ikon, dialog & toast kit). Pilih tanggal &
 * shift; tandai belum hadir; kehadiran kerja di luar jadwal; absensi mengajar per kelas / per guru; simpan atau kirim WhatsApp.
 * Komponen Alpine di assets/js/admin/absensi/harian.js (dulu fungsi global di skrip sebaris): absensiPage (data-cfg),
 * absensiKerja (data-kerja, data-guru), sesiAbsen (baris _sesi_row.php) → siap CSP.
 *
 * @var string     $tanggal     Tanggal (Y-m-d)
 * @var string     $shift       pagi|siang
 * @var string     $tampilan    kelas|guru
 * @var string     $namaHari    Nama hari tanggal ini
 * @var bool       $hariAktif   Hari sekolah aktif
 * @var bool       $recorded    Tanggal ini sudah diabsen
 * @var int        $total       Jumlah sesi KBM hari ini
 * @var array      $grup        Sesi per guru [{guru_id, nama, kode, sesi[]}]
 * @var array      $kelas       Kelas yang punya sesi (kelas_id, nama, jurusan) — tampilan per kelas
 * @var array      $kerja       Kehadiran kerja tersimpan
 * @var array      $saranKerja  Saran kehadiran kerja (jabatan struktural)
 * @var array      $jabatanMap  [guru_id => [jabatan…]]
 * @var array      $guruOptions Semua guru (id, nama, kode_guru)
 * @var array      $belum       Daftar belum hadir per shift
 * @var array      $piket       Guru piket per shift
 * @var string     $waTemplate  Format pesan WhatsApp
 * @var bool       $waCustom    Format buatan sendiri
 */
$statusOpts = [
    'hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin',
    'sakit' => 'Sakit', 'alpa'  => 'Alpa (Tidak Hadir)',
];
$jabatanMap = $jabatanMap ?? [];
$saranKerja = $saranKerja ?? [];
$jsonFlags  = JSON_INVALID_UTF8_SUBSTITUTE;

// Kehadiran kerja tersimpan + saran otomatis jabatan struktural (belum tersimpan).
$kerjaJson = array_map(static fn ($k) => [
    'guru_id'    => (int) $k['guru_id'],
    'nama'       => $k['nama'],
    'jabatan'    => implode(', ', array_column($jabatanMap[(int) $k['guru_id']] ?? [], 'nama')),
    'status'     => $k['status'],
    'shift'      => $k['shift'] ?? 'penuh',
    'jam_masuk'  => $k['jam_masuk'] ?? '',
    'keterangan' => $k['keterangan'] ?? '',
    'saran'      => false,
], $kerja);
foreach ($saranKerja as $s) {
    $kerjaJson[] = [
        'guru_id'    => (int) $s['guru_id'],
        'nama'       => $s['nama'],
        'jabatan'    => $s['jabatan'],
        'status'     => $s['status'],
        'shift'      => $s['shift'] ?? 'penuh',
        'jam_masuk'  => $s['jam_masuk'],
        'keterangan' => $s['keterangan'],
        'saran'      => true,
    ];
}
$guruJson = array_map(static fn ($g) => [
    'id'   => (int) $g['id'],
    'nama' => $g['nama'],
    'kode' => $g['kode_guru'] ?? '',
], $guruOptions);

// Guru yang punya sesi mengajar per shift (untuk filter kartu & daftar pilihan)
// + kode jurusan kelas per shift (saringan tampilan per kelas).
$guruShift    = ['pagi' => [], 'siang' => []];
$jurusanShift = ['pagi' => [], 'siang' => []];
foreach ($grup as $g) {
    foreach ($g['sesi'] as $s) {
        $sh = $s['jam_shift'] ?? $s['shift'] ?? 'pagi';
        $guruShift[$sh][(int) $g['guru_id']] = true;
        if ((string) ($s['jurusan_kode'] ?? '') !== '') {
            $jurusanShift[$sh][$s['jurusan_kode']] = true;
        }
    }
}
foreach ($jurusanShift as $sh => $set) {
    $jurusanShift[$sh] = array_map('strval', array_keys($set));
    sort($jurusanShift[$sh]);
}

// Tampilan per kelas: sesi dikelompokkan per KELAS (urut natural dari AbsensiHarian::muat) + data saringan tiap kartu
// (shift, jurusan, teks cari, guru) untuk absensiPage.kelasTampil() — tanpa membaca elemen dari isian.
$perKelas   = [];
$kartuKelas = [];
if ($hariAktif && $total > 0 && $tampilan === 'kelas') {
    foreach ($kelas ?? [] as $k) {
        $perKelas[(int) $k['kelas_id']] = $k + ['sesi' => [], 'shifts' => [], 'gids' => [], 'cari' => [strtolower($k['nama'])]];
    }
    foreach ($grup as $g) {
        foreach ($g['sesi'] as $s) {
            $kid = (int) $s['kelas_id'];
            $perKelas[$kid]['sesi'][]                    = ['s' => $s, 'gid' => (int) $g['guru_id'], 'nama' => $g['nama']];
            $perKelas[$kid]['shifts'][$s['jam_shift']]   = true;
            $perKelas[$kid]['gids'][(int) $g['guru_id']] = true;
            $perKelas[$kid]['cari'][]                    = strtolower($g['nama']);
        }
    }
    foreach ($perKelas as $kid => $k) {
        $kartuKelas[$kid] = [
            'shifts' => array_map('strval', array_keys($k['shifts'] ?? [])),
            'jur'    => (string) ($k['jurusan'] ?? ''),
            'cari'   => implode('|', array_unique($k['cari'] ?? [])),
            'gids'   => array_keys($k['gids'] ?? []),
        ];
    }
}

$pageCfg = [
    'tanggal'   => $tanggal,
    'shift'     => $shift,
    'tampilan'  => $tampilan,
    'jurusan'   => $jurusanShift,
    'kartu'     => (object) $kartuKelas,
    'belum'     => ['pagi' => $belum['pagi'] ?? [], 'siang' => $belum['siang'] ?? []],
    'piket'     => ['pagi' => $piket['pagi'] ?? [], 'siang' => $piket['siang'] ?? []],
    'guruShift' => ['pagi' => array_keys($guruShift['pagi']), 'siang' => array_keys($guruShift['siang'])],
    'guru'      => $guruJson,
    'template'  => $waTemplate,
    'custom'    => $waCustom,
    'urlSave'   => site_url('admin/absensi/save'),
    'urlTpl'    => site_url('admin/absensi/template-wa'),
    'urlPesan'  => site_url('admin/absensi/pesan-wa'),
];
$v = static fn (string $p): string => base_url($p) . '?v=' . @filemtime(FCPATH . $p);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'absensi_v5',
    'helpTitle' => 'Cara mengisi absensi guru',
    'helpBody'  => '<p>Pilih <b>tanggal</b> dan <b>shift (Pagi / Siang)</b>. Sistem menampilkan guru yang mengajar pada shift itu dari jadwal KBM — semua <b>default Hadir</b>. Tandai yang <b>Telat / Izin / Sakit / Alpa</b> lewat status tiap sesi atau <b>Set semua</b>.</p>'
        . '<p class="mt-2"><b>Per Kelas / Per Guru</b> — <b>Per Kelas</b> (bawaan) menampilkan tiap kelas urut X → XII (TKJ 1, 2 … 10) berisi jam & guru pengajarnya; saring per jurusan atau cari nama kelas / guru. <b>Per Guru</b> menampilkan kartu per nama guru dengan tombol <b>Set semua</b>. Pesan WhatsApp selalu ditulis per kelas: <i>nama kelas X jam ke 1-3</i>.</p>'
        . '<p class="mt-2"><b>Belum Hadir</b> — saat laporan dikirim masih ada guru yang belum datang? Tandai di panel <b>Belum Hadir</b> (atau tombol <b>Belum hadir</b> di kartu guru). Nama mereka masuk bagian "belum hadir" di pesan WhatsApp. Begitu guru datang, klik <b>Sudah datang</b> lalu isi jam datangnya: sesi yang sudah dimulai otomatis ditandai <b>Telat</b> dengan jam itu, lalu Simpan. Guru yang <b>tetap</b> di daftar ini dihitung <b>tidak hadir</b> di rekap.</p>'
        . '<p class="mt-2"><b>Kirim WhatsApp</b> — sekali klik: absensi disimpan, pesan disusun otomatis sesuai <b>Format Pesan</b>, lalu WhatsApp terbuka tinggal pilih grup. Nama guru yang punya <b>No. WhatsApp</b> (Master Guru) otomatis menjadi <b>tag</b> di grup. Format pesan bisa diubah lewat tombol <b>Format Pesan</b>.</p>'
        . '<p class="mt-2"><b>Kehadiran Kerja</b> — untuk guru/staf yang masuk tanpa jadwal KBM (TU, wakil kepala, dsb.). Guru berjabatan struktural diisikan otomatis bertanda <span class="lencana lencana-kuning">disarankan</span>; hapus yang tidak masuk.</p>'
        . '<p class="mt-2"><b>Penting:</b> hari yang tidak disimpan tidak dihitung di Rekap. Salah menyimpan hari libur? Gunakan <b>Batalkan pencatatan</b>.</p>',
]) ?>

<div x-data="absensiPage" data-cfg="<?= esc((string) json_encode($pageCfg, $jsonFlags), 'attr') ?>" @change="tandaiUbah()" class="space-y-4">

    <!-- ===================== BAR ATAS: tanggal + shift + ringkasan ===================== -->
    <section class="kartu" aria-label="Tanggal & shift">
        <div class="kartu-isi flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form method="get" class="flex flex-wrap items-end gap-3" data-noload>
                <div>
                    <label class="lbl" for="absTanggal">Tanggal</label>
                    <input type="date" id="absTanggal" name="tanggal" value="<?= esc($tanggal, 'attr') ?>" data-autosubmit class="inp">
                    <input type="hidden" name="shift" :value="shift">
                </div>
                <span class="lencana lencana-abu mb-1.5 py-1.5"><?= ikon('calendar-month', 'h-4 w-4') ?><?= $namaHari !== '' ? esc($namaHari) : 'Hari tak dikenal' ?></span>
                <div>
                    <span class="lbl" id="labelShift">Shift KBM</span>
                    <div class="grup-tombol" role="group" aria-labelledby="labelShift">
                        <?php foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $k => $lbl): ?>
                            <button type="button" @click="setShift('<?= $k ?>')" :aria-pressed="shift === '<?= $k ?>'"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

            <!-- Ringkasan shift terpilih (hidup mengikuti tanda yang sedang tampil) -->
            <div class="flex flex-wrap gap-2" aria-live="polite">
                <template x-for="it in stats()" :key="it.k">
                    <span class="lencana py-1" :class="it.c" x-text="teksStat(it)"></span>
                </template>
            </div>
        </div>

        <div class="kartu-kaki flex flex-wrap items-center justify-between gap-2">
            <?php if ($recorded): ?>
                <span class="lencana lencana-hijau py-1 whitespace-normal"><?= ikon('check-circle', 'h-4 w-4 shrink-0') ?><span>Hari ini sudah diabsen — dihitung di rekap. Perubahan bisa disimpan ulang.</span></span>
                <form method="post" action="<?= site_url('admin/absensi/unrecord') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal, 'attr') ?>">
                    <input type="hidden" name="shift" :value="shift">
                    <button type="submit" class="btn btn-teks btn-sm text-red-700 dark:text-red-500"
                            data-confirm="Batalkan pencatatan absensi tanggal ini? Semua tandaan hari ini (termasuk kehadiran kerja & daftar belum hadir) akan DIHAPUS dan hari ini tidak dihitung di rekap."
                            data-confirm-judul="Batalkan pencatatan?" data-confirm-ya="Ya, batalkan" data-confirm-jenis="bahaya">
                        <?= ikon('trash-bin', 'h-4 w-4') ?>Batalkan pencatatan
                    </button>
                </form>
            <?php else: ?>
                <span class="lencana lencana-kuning py-1 whitespace-normal"><?= ikon('info-circle', 'h-4 w-4 shrink-0') ?><span>Belum diabsen — klik <b>Simpan absensi</b> atau <b>Kirim WhatsApp</b> untuk mencatat hari ini</span></span>
            <?php endif; ?>
        </div>
    </section>

    <form id="form-absensi" method="post" action="<?= site_url('admin/absensi/save') ?>" @submit="siapkanKirim()" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal" value="<?= esc($tanggal, 'attr') ?>">
        <input type="hidden" name="shift" :value="shift">
        <input type="hidden" name="kerja_sync" value="1">
        <!-- Sesi & kehadiran kerja dikirim sebagai JSON (satu isian) agar tidak
             terpotong batas max_input_vars PHP (±340 sesi × 7 isian per hari). -->
        <input type="hidden" name="rows_json" id="rows_json">
        <input type="hidden" name="kerja_json" id="kerja_json">
        <input type="hidden" name="belum_sync" value="1">
        <template x-for="id in daftarBelum()" :key="id">
            <input type="hidden" name="belum[]" :value="id">
        </template>

        <!-- ===================== BELUM HADIR (per shift) ===================== -->
        <section class="kartu" aria-labelledby="judul-belum">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 id="judul-belum" class="kartu-judul">Belum hadir <span class="text-sm font-normal text-gray-500 dark:text-gray-400">— KBM <span x-text="shift"></span></span></h2>
                    <p class="kartu-ket max-w-3xl">Guru yang belum datang saat laporan dikirim. Klik <b>Sudah datang</b> ketika guru tiba — sesi yang sudah dimulai otomatis ditandai Telat. Yang tetap di daftar ini dihitung <b>tidak hadir</b> di rekap.</p>
                </div>
                <button type="button" class="btn btn-netral" @click="openPicker()"><?= ikon('plus', 'h-4 w-4') ?>Tandai belum hadir</button>
            </div>
            <div class="kartu-isi">
                <p x-show="!adaBelum()" class="py-2 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada guru yang ditandai belum hadir pada KBM <span x-text="shift"></span>.</p>
                <div x-show="adaBelum()" x-cloak class="space-y-2">
                    <template x-for="id in daftarBelum()" :key="id">
                        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="namaGuru(id)"></p>
                                <p class="text-xs font-medium text-red-700 dark:text-red-400">Belum hadir</p>
                            </div>
                            <button type="button" class="btn btn-sukses btn-sm shrink-0" @click="sudahDatang(id)"><?= ikon('check', 'h-4 w-4') ?>Sudah datang</button>
                            <button type="button" class="tombol-tutup shrink-0" @click="hapusBelum(id)" title="Hapus dari daftar (salah tandai)" aria-label="Hapus dari daftar belum hadir"><?= ikon('close', 'h-4 w-4') ?></button>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ===================== KEHADIRAN KERJA (di luar jadwal) ===================== -->
        <section id="panel-kerja" x-data="absensiKerja" data-kerja="<?= esc((string) json_encode($kerjaJson, $jsonFlags), 'attr') ?>" data-guru="<?= esc((string) json_encode($guruJson, $jsonFlags), 'attr') ?>"
                 class="kartu" aria-labelledby="judul-kerja">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 id="judul-kerja" class="kartu-judul">Kehadiran kerja <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(di luar jadwal mengajar)</span></h2>
                    <p class="kartu-ket max-w-3xl">Untuk guru/staf yang <b>masuk kerja</b> tanpa jadwal KBM (staf TU, guru piket, wakil kepala, dsb.). Pilih berlaku <b>Pagi + Siang</b>, pagi saja, atau siang saja. Tetap dihitung di rekap.</p>
                </div>
            </div>
            <div class="kartu-isi space-y-4">
                <?php if (! empty($saranKerja)): ?>
                    <?= ui_peringatan('awas', '', ['html' => '<b>' . count($saranKerja) . ' guru berjabatan struktural</b> diisikan otomatis dan ditandai <b>disarankan</b>. Belum tersimpan — hapus yang tidak masuk, lalu <b>Simpan</b> / <b>Kirim WhatsApp</b>.']) ?>
                <?php endif; ?>

                <!-- Tambah guru -->
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-[200px] flex-1">
                        <label class="lbl" for="kerjaPilih">Tambah guru yang masuk</label>
                        <select id="kerjaPilih" x-model.number="pick" class="inp">
                            <option value="0">— pilih guru —</option>
                            <template x-for="g in availableGuru()" :key="g.id">
                                <option :value="g.id" x-text="labelGuru(g)"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" class="btn btn-utama" @click="add()"><?= ikon('plus', 'h-4 w-4') ?>Tambah</button>
                </div>

                <p x-show="rows.length === 0" class="py-2 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada guru yang ditandai masuk kerja pada tanggal ini.</p>
                <div class="space-y-2" x-show="rows.length > 0" x-cloak>
                    <template x-for="(r, i) in rows" :key="r.guru_id">
                        <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 md:flex-row md:items-center dark:border-gray-600 dark:bg-gray-700/50">
                            <div class="shrink-0 md:w-52">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="r.nama"></p>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                    <span x-show="r.jabatan" x-cloak class="text-[11px] text-gray-500 dark:text-gray-400" x-text="r.jabatan"></span>
                                    <span x-show="isPiket(r.guru_id)" x-cloak class="lencana lencana-merek" x-text="labelPiket(r.guru_id)"></span>
                                    <span x-show="r.saran" x-cloak class="lencana lencana-kuning" title="Disarankan otomatis karena berjabatan struktural. Belum tersimpan.">disarankan</span>
                                </div>
                            </div>
                            <select x-model="r.shift" class="inp md:w-40" title="Berlaku untuk shift" aria-label="Berlaku untuk shift">
                                <option value="penuh">Pagi + Siang</option>
                                <option value="pagi">Pagi saja</option>
                                <option value="siang">Siang saja</option>
                            </select>
                            <select x-model="r.status" class="inp md:w-48" aria-label="Status kehadiran kerja">
                                <?php foreach ($statusOpts as $k => $lbl): ?>
                                    <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="time" x-model="r.jam_masuk" x-show="r.status !== 'hadir'" x-cloak class="inp md:w-32" title="Jam masuk (opsional)" aria-label="Jam masuk">
                            <input type="text" x-model="r.keterangan" maxlength="255" placeholder="Keterangan (opsional)…" class="inp flex-1" aria-label="Keterangan">
                            <button type="button" class="tombol-tutup shrink-0 text-red-600 hover:text-red-700 dark:text-red-500" @click="remove(i)" title="Hapus" aria-label="Hapus dari kehadiran kerja"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ===================== ABSENSI MENGAJAR (shift terpilih) ===================== -->
        <?php if (! $hariAktif): ?>
            <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-month', 'judul' => 'Bukan hari sekolah aktif',
                'teks' => 'Tanggal ini tidak ada absensi mengajar. Gunakan panel Kehadiran Kerja di atas bila ada yang masuk.']) ?></div>
        <?php elseif ($total === 0): ?>
            <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal KBM pada hari ' . $namaHari,
                'teks' => 'Susun jadwal dulu di menu Jadwal KBM — atau gunakan panel Kehadiran Kerja di atas.',
                'aksi' => '<a href="' . site_url('admin/jadwal') . '" class="btn btn-netral">' . ikon('calendar-week', 'h-4 w-4') . 'Buka Jadwal KBM</a>']) ?></div>
        <?php else: ?>
            <div x-show="tanpaSesi()" x-cloak class="kartu kartu-isi py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                Tidak ada sesi mengajar pada KBM <span x-text="shift"></span> hari ini.
            </div>

            <!-- Pilihan tampilan (per kelas / per guru) + saringan kelas -->
            <div x-show="!tanpaSesi()" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="grup-tombol self-start" role="group" aria-label="Pilihan tampilan">
                    <?php foreach (['kelas' => ['Per kelas', 'school'], 'guru' => ['Per guru', 'users']] as $k => [$lbl, $ik]): ?>
                        <button type="button" class="inline-flex items-center gap-2" @click="gantiTampilan('<?= $k ?>')" aria-pressed="<?= $tampilan === $k ? 'true' : 'false' ?>"><?= ikon($ik, 'h-4 w-4') ?><?= $lbl ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if ($tampilan === 'kelas'): ?>
                    <div class="flex flex-wrap items-center gap-2 lg:flex-1">
                        <template x-if="adaPilihanJurusan()">
                            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Saring jurusan">
                                <button type="button" class="tombol-saring" @click="pilihJurusan('')" :aria-pressed="jurAktif() === ''">Semua</button>
                                <template x-for="j in jurusanAktif()" :key="j">
                                    <button type="button" class="tombol-saring" @click="pilihJurusan(j)" :aria-pressed="jurAktif() === j" x-text="j"></button>
                                </template>
                            </div>
                        </template>
                        <div class="relative w-full sm:w-56 lg:ms-auto">
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
                            <input type="search" x-model="fCari" @change.stop @keydown.enter.prevent placeholder="Cari kelas / guru…" aria-label="Cari kelas atau guru" class="inp ps-9">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($tampilan === 'kelas'): ?>
                <p x-show="kelasKosong()" x-cloak class="kartu kartu-isi py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada kelas yang cocok dengan pencarian.</p>
                <div class="space-y-4">
                    <?php foreach ($perKelas as $kid => $k):
                        usort($k['sesi'], static fn ($a, $b) => [$a['s']['jam_shift'], (int) $a['s']['jam_ke']] <=> [$b['s']['jam_shift'], (int) $b['s']['jam_ke']]);
                        $gids = array_keys($k['gids']);
                    ?>
                        <section class="kartu overflow-hidden" x-show="kelasTampil(<?= (int) $kid ?>)" :class="kelasKartu(<?= (int) $kid ?>)" aria-label="Kelas <?= esc($k['nama'], 'attr') ?>">
                            <div class="kartu-kepala bg-gray-50 dark:bg-gray-700/50">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($k['nama']) ?>
                                    <?php if (! empty($k['jurusan'])): ?><span class="lencana lencana-merek ms-1 align-middle"><?= esc($k['jurusan']) ?></span><?php endif; ?>
                                </h3>
                                <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400"><?= count($k['sesi']) ?> jam KBM &middot; <?= count($gids) ?> guru</p>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($k['sesi'] as $it): ?>
                                    <?= view('admin/absensi/_sesi_row', ['s' => $it['s'], 'gid' => $it['gid'], 'namaGuru' => $it['nama'], 'statusOpts' => $statusOpts, 'mode' => 'kelas'], ['saveData' => false]) ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($grup as $g): $gid = (int) $g['guru_id']; ?>
                        <section class="kartu overflow-hidden" x-show="punyaSesi(<?= $gid ?>)" :class="isBelum(<?= $gid ?>) ? 'border-red-300 dark:border-red-800' : ''" aria-label="<?= esc($g['nama'], 'attr') ?>">
                            <!-- Kepala guru + set semua -->
                            <div class="kartu-kepala bg-gray-50 dark:bg-gray-700/50">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white"><?= esc($g['nama']) ?>
                                        <span x-show="piketShift(<?= $gid ?>)" x-cloak class="lencana lencana-merek ms-1 align-middle">Piket</span>
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($g['kode']) ?> &middot; <span x-text="teksSesi(<?= $gid ?>)"></span></p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <button type="button" x-show="!isBelum(<?= $gid ?>)" @click="tandaiBelum(<?= $gid ?>)" class="btn btn-netral btn-sm text-red-700 dark:text-red-500">Belum hadir</button>
                                    <button type="button" x-show="isBelum(<?= $gid ?>)" x-cloak @click="sudahDatang(<?= $gid ?>)" class="btn btn-sukses btn-sm"><?= ikon('check', 'h-3.5 w-3.5') ?>Sudah datang</button>
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400" for="setSemua<?= $gid ?>">Set semua:</label>
                                    <select id="setSemua<?= $gid ?>" class="inp w-auto py-1.5 text-xs" @change.stop="setSemua($event, <?= $gid ?>)">
                                        <option value="">— pilih —</option>
                                        <?php foreach ($statusOpts as $k => $lbl): ?>
                                            <option value="<?= $k ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Daftar sesi guru (hanya shift terpilih yang tampil) -->
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($g['sesi'] as $s): ?>
                                    <?= view('admin/absensi/_sesi_row', ['s' => $s, 'gid' => $gid, 'statusOpts' => $statusOpts, 'mode' => 'guru'], ['saveData' => false]) ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Bar aksi (menempel di bawah layar) -->
        <div class="kartu sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 p-3 sm:p-4">
            <p class="hidden text-xs text-gray-500 md:block dark:text-gray-400">Tanggal <b><?= esc($tanggal) ?></b> &middot; <?= esc($namaHari) ?> &middot; KBM <b x-text="shift"></b></p>
            <div class="ms-auto flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-netral" data-buka-dialog="format-wa"><?= ikon('edit', 'h-4 w-4') ?>Format pesan</button>
                <button type="button" class="btn btn-sukses" @click="kirimWa()" :disabled="sending">
                    <?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span x-text="sending ? 'Menyimpan…' : 'Kirim WhatsApp'">Kirim WhatsApp</span>
                </button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan absensi</button>
            </div>
        </div>
    </form>

    <!-- ===================== DIALOG: pilih guru belum hadir ===================== -->
    <?= ui_dialog_buka(['nama' => 'pilih-belum', 'judul' => 'Tandai belum hadir', 'ukuran' => 'lg']) ?>
    <div class="dialog-isi">
        <p class="text-sm text-gray-500 dark:text-gray-400">KBM <b x-text="shift"></b> — centang guru yang belum datang. Guru yang terjadwal mengajar di shift ini tampil lebih dulu.</p>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-500 dark:text-gray-400"><?= ikon('search', 'h-4 w-4') ?></span>
            <input type="search" x-model="pickQ" @keydown.enter.prevent placeholder="Cari nama guru…" aria-label="Cari nama guru" class="inp ps-9">
        </div>
        <div class="panel-gulir max-h-80 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
            <template x-for="g in pilihan()" :key="g.id">
                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <input type="checkbox" :value="g.id" x-model.number="pickSel" class="cek">
                    <span class="flex-1 text-sm text-gray-900 dark:text-white" x-text="g.nama"></span>
                    <span x-show="g.kbm" class="lencana lencana-merek">KBM</span>
                </label>
            </template>
            <p x-show="pilihanKosong()" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada guru cocok.</p>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="button" class="btn btn-utama" @click="terapkanPilihan()"><?= ikon('check', 'h-4 w-4') ?><span x-text="teksTandai()">Tandai (0)</span></button>
    </div>
    <?= ui_dialog_tutup() ?>

    <!-- ===================== DIALOG: jam datang (pengganti prompt) ===================== -->
    <?= ui_dialog_buka(['nama' => 'jam-datang', 'judul' => 'Guru sudah datang', 'ukuran' => 'sm']) ?>
    <div class="dialog-isi">
        <p class="text-sm text-gray-600 dark:text-gray-300"><b class="text-gray-900 dark:text-white" x-text="namaGuru(datangGid)"></b> dikeluarkan dari daftar belum hadir. Sesi KBM <span x-text="shift"></span> yang sudah dimulai pada jam datang otomatis ditandai <b>Telat</b>.</p>
        <div>
            <label class="lbl" for="jamDatang">Jam datang</label>
            <input type="time" id="jamDatang" x-model="datangJam" @keydown.enter.prevent="konfirmasiDatang()" class="inp" required>
            <p class="hint">Jam ini menentukan potongan — periksa sebelum menandai.</p>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="button" class="btn btn-sukses" @click="konfirmasiDatang()"><?= ikon('check', 'h-4 w-4') ?>Tandai datang</button>
    </div>
    <?= ui_dialog_tutup() ?>

    <!-- ===================== DIALOG: format pesan WhatsApp ===================== -->
    <?= ui_dialog_buka(['nama' => 'format-wa', 'judul' => 'Format pesan WhatsApp', 'ukuran' => 'xl']) ?>
    <div class="dialog-isi grid gap-4 space-y-0 md:grid-cols-3">
        <div class="md:col-span-2">
            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400" x-text="custom ? 'Memakai format buatan sendiri.' : 'Memakai format bawaan.'"></p>
            <label class="sr-only" for="tplWa">Format pesan</label>
            <textarea id="tplWa" x-model="tpl" rows="18" class="inp font-mono leading-relaxed"></textarea>
            <p class="hint">Baris daftar (mis. <code>{daftar_belum_hadir}</code>) yang kosong otomatis dihapus bersama satu baris judul tepat di atasnya.</p>
        </div>
        <div>
            <p class="mb-2 text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">Isian otomatis (klik untuk menyalin)</p>
            <div class="panel-gulir space-y-1.5">
                <?php foreach (\App\Libraries\AbsensiWa::PLACEHOLDERS as $kode => $arti): ?>
                    <button type="button" data-kode="<?= esc($kode, 'attr') ?>" @click="salinKode($event)"
                            class="block w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-left transition-colors hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                        <code class="text-xs font-semibold text-brand-700 dark:text-brand-400"><?= esc($kode) ?></code>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400"><?= esc($arti) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="dialog-kaki justify-between">
        <button type="button" class="btn btn-teks text-red-700 dark:text-red-500" @click="resetTemplate()"><?= ikon('close-circle', 'h-4 w-4') ?>Kembalikan ke bawaan</button>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-netral" @click="pratinjau()"><?= ikon('eye', 'h-4 w-4') ?>Pratinjau (data tersimpan)</button>
            <button type="button" class="btn btn-utama" @click="simpanTemplate()"><?= ikon('check', 'h-4 w-4') ?>Simpan format</button>
        </div>
    </div>
    <?= ui_dialog_tutup() ?>

    <!-- ===================== DIALOG: teks pesan (pratinjau / cadangan bila tab diblokir) ===================== -->
    <?= ui_dialog_buka(['nama' => 'pesan-wa', 'judul' => 'Pesan WhatsApp', 'ukuran' => 'lg']) ?>
    <div class="dialog-isi">
        <label class="sr-only" for="teksWa">Isi pesan WhatsApp</label>
        <textarea id="teksWa" readonly rows="14" x-model="waText" class="inp leading-relaxed"></textarea>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Tutup</button>
        <button type="button" class="btn btn-netral" @click="salinPesan()"><?= ikon('file-copy', 'h-4 w-4') ?>Salin</button>
        <a :href="tautanWa()" target="_blank" rel="noopener" class="btn btn-sukses"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Buka WhatsApp</a>
    </div>
    <?= ui_dialog_tutup() ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/absensi/harian.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
