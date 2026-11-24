<?php
/**
 * Tab Jadwal Ujian — daftar mapel yang diujikan pada satu periode. Rombak tampilan F7: pola halaman data kit (ui_crud_alat,
 * tabel-crud, ui_crud_aksi, ui_crud_kaki, dialog ui_crud_form_*, dialog Import ui_crud_impor — modal Import gaya lama dibuang);
 * komponen Alpine "ujianJadwal" (perluasan masterList) di assets/js/admin/ujian/jadwal.js → siap CSP.
 *
 * Satu baris = satu mapel diujikan untuk satu tingkat (opsional dipersempit
 * ke satu jurusan) pada tanggal & jam tertentu. Kolom shift penting karena
 * kelas XII terbagi pagi & siang.
 *
 * @var array<int,array>     $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array<string,mixed>  $filter      q, tingkat, jurusan_id
 * @var int                  $per
 * @var int                  $totalJadwal
 * @var array<int,int>       $jmlPengawas jadwal_id => jumlah pengawas
 * @var array<int,int>       $jmlTakHadir jadwal_id => jumlah siswa tidak hadir
 * @var array<int,int>       $jmlPembuat  jadwal_id => jumlah pembuat soal
 * @var array<int,string>    $mapelOpts
 * @var array<int,string>    $jurusanOpts
 * @var array<int,string>    $tingkatList
 * @var array<int,string>    $shiftList
 * @var list<array>          $istirahat   IstirahatUjian::ringkas() — bagian Istirahat (2026-11-24)
 * @var array<int,list<array>> $istSisipan baris ISTIRAHAT sebelum baris jadwal ke-n (kunci = jumlah baris → sesudah baris terakhir)
 * @var array<string,mixed>  $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

$jam = static fn ($m, $s) => $m
    ? substr((string) $m, 0, 5) . ($s ? '–' . substr((string) $s, 0, 5) : '')
    : 'Sehari penuh';

$labelShift = ['pagi' => 'Pagi', 'siang' => 'Siang', 'semua' => 'Pagi & Siang'];
$warnaShift = ['pagi' => 'lencana-kuning', 'siang' => 'lencana-merek', 'semua' => 'lencana-abu'];

// Nilai awal form tambah: tanggal ikut tanggal mulai periode bila sudah diisi.
$defaults = [
    'id'          => '',
    'mapel_id'    => '',
    'tingkat'     => '',
    'jurusan_id'  => '',
    'shift'       => '', // wajib dipilih: Pagi atau Siang (2026-11-23, tanpa "Pagi & Siang")
    'tanggal'     => $periode['tanggal_mulai'] ?: date('Y-m-d'),
    'jam_mulai'   => '',
    'jam_selesai' => '',
    'ruang'       => '',
    'keterangan'  => '',
];
$adaFilter = $filter['q'] !== '' || $filter['tingkat'] !== '' || $filter['jurusan_id'] > 0;
$jmlPembuat = $jmlPembuat ?? [];

// Data baris untuk dialog Ubah (data-row → masterList.openEdit).
$barisUbah = static fn (array $r): array => [
    'id'          => (string) $r['id'],
    'mapel_id'    => (string) ($r['mapel_id'] ?? ''),
    'tingkat'     => $r['tingkat'],
    'jurusan_id'  => (string) ($r['jurusan_id'] ?? ''),
    // Data lama "Pagi & Siang" (semua) → kosong: saat diubah, pengguna wajib memilih Pagi atau Siang.
    'shift'       => in_array($r['shift'], ['pagi', 'siang'], true) ? $r['shift'] : '',
    'tanggal'     => $r['tanggal'],
    'jam_mulai'   => $r['jam_mulai'] ? substr((string) $r['jam_mulai'], 0, 5) : '',
    'jam_selesai' => $r['jam_selesai'] ? substr((string) $r['jam_selesai'], 0, 5) : '',
    'ruang'       => $r['ruang'] ?? '',
    'keterangan'  => $r['keterangan'] ?? '',
];
// Form Hapus: periode_id wajib ikut (Ujian::hapusJadwal memeriksa periode dari POST).
// $ikonSaja (tabel laptop) = tombol ikon tempat sampah saja, label lewat title/aria-label.
$formHapus = static function (array $r, int $tak, string $kelas, bool $ikonSaja = false) use ($base, $periode): string {
    return '<form method="post" action="' . $base . '/jadwal/' . (int) $r['id'] . '/hapus" class="inline">' . csrf_field()
        . '<input type="hidden" name="periode_id" value="' . (int) $periode['id'] . '">'
        . '<button type="submit" class="btn btn-bahaya btn-sm' . $kelas . '"' . ($ikonSaja ? ' title="Hapus jadwal" aria-label="Hapus jadwal"' : '')
        . ' data-confirm="' . esc('Hapus jadwal ini?' . ($tak > 0 ? ' ' . $tak . ' catatan ketidakhadiran TETAP tersimpan, hanya dilepas dari jadwal ini.' : '') . ' Penugasan pengawasnya ikut terhapus. Bila ini jadwal terakhir di jam itu, isi Jadwal Pengawas jam itu juga ikut terhapus.', 'attr') . '"'
        . ' data-confirm-judul="Hapus jadwal?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">' . ikon('trash-bin', 'h-4 w-4') . ($ikonSaja ? '' : 'Hapus') . '</button></form>';
};
$lencanaJumlah = static fn (int $n): string => $n > 0 ? 'lencana-merek' : 'lencana-abu';

// ---- Istirahat (2026-11-24) ----
$istirahat  = $istirahat ?? [];
$istSisipan = $istSisipan ?? [];
// Satu hari istirahat → JSON (sudah di-esc) untuk dialog "Atur istirahat".
$dataIst = static function (array $h): string {
    $r = ['hari' => $h['shift'] . '|' . $h['tanggal'], 'mode' => 'atur'];
    foreach (array_slice($h['jam'], 0, \App\Libraries\IstirahatUjian::MAKS) as $n => $j) {
        $r['m' . ($n + 1)] = substr($j['mulai'], 0, 5);
        $r['s' . ($n + 1)] = substr($j['selesai'], 0, 5);
    }

    return esc((string) json_encode($r), 'attr');
};
$tombolIst = static fn (array $h, string $kelas, string $isi): string => '<button type="button" class="btn btn-netral btn-sm' . $kelas . '" title="Ubah istirahat" aria-label="'
    . esc('Ubah istirahat ' . $h['shift'] . ' ' . $h['hari'] . ' ' . $h['tgl'], 'attr') . '" data-atur-istirahat data-row="' . $dataIst($h) . '">' . ikon('edit', 'h-4 w-4') . $isi . '</button>';
$ketIst = static fn (array $h): string => $h['mode'] === 'otomatis' ? 'otomatis' : 'diatur';
// Baris ISTIRAHAT di tabel (laptop) dan kartu (HP) — di tempatnya menurut jam.
$istTabel = static function (array $daftar) use ($jam, $labelShift, $warnaShift, $tombolIst, $ketIst): string {
    $h = '';
    foreach ($daftar as $i) {
        $h .= '<tr class="bg-yellow-50 dark:bg-yellow-900/20"><td class="whitespace-nowrap"><p class="sel-judul">' . esc($i['hari'] . ', ' . $i['tgl']) . '</p><p class="sel-ket">' . esc($jam($i['mulai'], $i['selesai'])) . '</p></td>'
            . '<td><p class="sel-judul">ISTIRAHAT</p></td>'
            . '<td class="whitespace-nowrap"><span class="lencana ' . $warnaShift[$i['shift']] . '">' . esc($labelShift[$i['shift']]) . '</span></td>'
            . '<td colspan="4"><span class="sel-ket">' . esc($ketIst($i)) . '</span></td>'
            . '<td class="kolom-aksi"><div class="aksi-baris">' . $tombolIst($i, ' btn-ikon', '') . '</div></td></tr>';
    }

    return $h;
};
$istKartu = static function (array $daftar) use ($jam, $labelShift, $warnaShift, $tombolIst, $ketIst): string {
    $h = '';
    foreach ($daftar as $i) {
        $h .= '<div class="flex items-center justify-between gap-3 bg-yellow-50 p-4 dark:bg-yellow-900/20"><div class="min-w-0"><p class="font-semibold text-gray-900 dark:text-white">ISTIRAHAT <span class="lencana ' . $warnaShift[$i['shift']] . ' ms-1">' . esc($labelShift[$i['shift']]) . '</span></p>'
            . '<p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">' . esc($i['hari'] . ', ' . $i['tgl'] . ' · ' . $jam($i['mulai'], $i['selesai']) . ' · ' . $ketIst($i)) . '</p></div>'
            . $tombolIst($i, '', 'Ubah') . '</div>';
    }

    return $h;
};
$tingkatOpsi = [];
foreach ($tingkatList as $t) {
    $tingkatOpsi[$t] = 'Tingkat ' . $t;
}
?>

<?= view('admin/ujian/_istirahat', ['base' => $base, 'tp' => $tp, 'istirahat' => $istirahat, 'dataIst' => $dataIst, 'labelShift' => $labelShift, 'warnaShift' => $warnaShift]) ?>

<div x-data="ujianJadwal" data-base="<?= $base ?>/jadwal" data-entity="jadwal ujian" data-defaults="<?= esc((string) json_encode($defaults), 'attr') ?>" class="kartu overflow-hidden">

    <?= ui_crud_alat([
        'baseUrl'        => $base . '/jadwal' . $qtp,
        'cari'           => 'Cari mata pelajaran atau ruang…',
        'q'              => (string) $filter['q'],
        'filter'         => [
            ['name' => 'tingkat', 'value' => (string) $filter['tingkat'], 'all' => 'Semua tingkat', 'options' => $tingkatOpsi],
            ['name' => 'jurusan_id', 'value' => $filter['jurusan_id'] > 0 ? (string) $filter['jurusan_id'] : '', 'all' => 'Semua jurusan', 'options' => $jurusanOpts],
        ],
        'per'            => $per,
        'filterLangsung' => true,
        // Rapi tampilan (2026-11-23, keluhan user): pola Master Siswa — laptop 2 baris (cari … tombol / saringan … Tampilkan N);
        // HP: cari + "Saringan" buka-tutup, lalu Tambah + menu. Cetak jadwal & Pembuat soal disatukan di SATU menu supaya deret
        // tombol kanan tidak berdesakan; Import & Export ikut masuk menu HANYA di layar sempit (data-hanya-sempit).
        'lentur'         => true,
        'hidden'         => ['tp' => $tp],
        'labelTambah'    => 'Tambah jadwal',
        'eksporUrl'      => $base . '/jadwal/export' . $qtp,
        'eksporTitle'    => 'Keluarkan jadwal ujian periode ini ke file Excel',
        // F2 cetak jadwal (2026-11-22): Jadwal Ujian berbentuk matriks persis berkas contoh sekolah (lembar PAGI & SIANG).
        'tambahanHtml'   => ui_menu_lain([
            ['label' => 'Cetak Jadwal Ujian (Excel)', 'ikon' => 'file-csv', 'href' => $base . '/cetak-jadwal/excel' . $qtp],
            ['label' => 'Cetak Jadwal Ujian (PDF)', 'ikon' => 'file-pdf', 'href' => $base . '/cetak-jadwal/pdf' . $qtp, 'atr' => 'target="_blank" rel="noopener"'],
            ['label' => 'Guru pembuat soal', 'ikon' => 'pen-nib', 'href' => $base . '/pembuat-soal' . $qtp],
            ...(array_filter($istirahat, static fn (array $h): bool => $h['adaJadwal']) !== [] ? [['label' => 'Atur istirahat', 'ikon' => 'clock', 'atr' => 'data-atur-istirahat data-row="{}"']] : []),
            ['label' => 'Import dari Excel', 'ikon' => 'upload', 'atr' => 'data-hanya-sempit data-buka-impor'],
            ['label' => 'Export ke Excel', 'ikon' => 'download', 'href' => $base . '/jadwal/export' . $qtp, 'atr' => 'data-hanya-sempit data-export-filter="toolbarFilter"'],
        ], ['label' => 'Cetak & lainnya', 'kecil' => false]),
    ]) ?>

    <?php if (! $rows): ?>
        <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => $adaFilter ? 'Tidak ada jadwal yang cocok dengan filter' : 'Belum ada jadwal ujian',
            'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Klik Tambah jadwal untuk mulai menyusun, atau Import dari Excel.']) ?>
    <?php else: ?>
        <!-- Kartu (HP): tabel lebar tak muat di layar sempit, jadi tiap jadwal tampil sebagai kartu dengan tombol berlabel -->
        <div class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
            <?php foreach ($rows as $urutK => $r):
                $tsK  = strtotime((string) $r['tanggal']);
                $jmlK = (int) ($jmlPengawas[$r['id']] ?? 0);
                $pbsK = (int) ($jmlPembuat[$r['id']] ?? 0);
                $takK = (int) ($jmlTakHadir[$r['id']] ?? 0);
                ?>
                <?= $istKartu($istSisipan[$urutK] ?? []) ?>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['nama_mapel'] ?? 'Mapel sudah dihapus') ?></p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"><?= esc($HARI[(int) date('N', $tsK)] ?? '') ?>, <?= date('d/m/Y', $tsK) ?> · <?= esc($jam($r['jam_mulai'], $r['jam_selesai'])) ?></p>
                        </div>
                        <span class="lencana <?= $warnaShift[$r['shift']] ?? $warnaShift['semua'] ?> shrink-0"><?= esc($labelShift[$r['shift']] ?? $r['shift']) ?></span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Tingkat <?= esc($r['tingkat']) ?><?= $r['jurusan_kode'] ? ' · ' . esc($r['jurusan_kode']) : '' ?> · Ruang <?= esc($r['ruang'] ?: '—') ?></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a href="<?= $base ?>/pengawas/<?= (int) $r['id'] ?>" class="lencana <?= $lencanaJumlah($jmlK) ?> hover:underline">Pengawas: <?= $jmlK ?></a>
                        <a href="<?= $base ?>/pembuat-soal<?= $qtp ?>" class="lencana <?= $lencanaJumlah($pbsK) ?> hover:underline">Pembuat soal: <?= $pbsK ?></a>
                        <span class="lencana <?= $takK > 0 ? 'lencana-kuning' : 'lencana-abu' ?>">Tidak hadir: <?= $takK ?></span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a href="<?= $base ?>/daftar-hadir/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm"><?= ikon('printer', 'h-4 w-4') ?>Daftar hadir</a>
                        <a href="<?= $base ?>/berita-acara/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm"><?= ikon('file-lines', 'h-4 w-4') ?>Berita acara</a>
                        <button type="button" class="btn btn-utama btn-sm" data-ubah data-row="<?= esc((string) json_encode($barisUbah($r)), 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?>Ubah</button>
                        <?= $formHapus($r, $takK, ' w-full') ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?= $istKartu($istSisipan[count($rows)] ?? []) ?>
        </div>

        <!-- Tabel (laptop) -->
        <div class="tabel-bungkus hidden md:block">
            <table class="tabel-crud">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Mata pelajaran</th>
                        <th scope="col">Sasaran</th>
                        <th scope="col">Ruang</th>
                        <th scope="col" class="whitespace-nowrap text-center">Pengawas</th>
                        <th scope="col" class="whitespace-nowrap text-center">Pembuat soal</th>
                        <th scope="col" class="whitespace-nowrap text-center">Tidak hadir</th>
                        <th scope="col" class="kolom-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $urut => $r):
                        $ts  = strtotime((string) $r['tanggal']);
                        $jml = (int) ($jmlPengawas[$r['id']] ?? 0);
                        $pbs = (int) ($jmlPembuat[$r['id']] ?? 0);
                        $tak = (int) ($jmlTakHadir[$r['id']] ?? 0);
                        ?>
                        <?= $istTabel($istSisipan[$urut] ?? []) ?>
                        <tr>
                            <?php // Hari, tanggal & jam satu kolom "Waktu" (dulu 2 kolom → tabel terlalu lebar, kolom Aksi terdesak). ?>
                            <td class="whitespace-nowrap">
                                <p class="sel-judul"><?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?></p>
                                <p class="sel-ket"><?= esc($jam($r['jam_mulai'], $r['jam_selesai'])) ?></p>
                            </td>
                            <td>
                                <p class="sel-judul"><?= esc($r['nama_mapel'] ?? 'Mapel sudah dihapus') ?></p>
                                <?php if ($r['kode_mapel']): ?><p class="sel-ket"><?= esc($r['kode_mapel']) ?></p><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <p class="sel-judul">Tingkat <?= esc($r['tingkat']) ?><?php if ($r['jurusan_kode']): ?><span class="font-normal text-gray-500 dark:text-gray-400"> · <?= esc($r['jurusan_kode']) ?></span><?php endif; ?></p>
                                <p class="mt-1"><span class="lencana <?= $warnaShift[$r['shift']] ?? $warnaShift['semua'] ?>"><?= esc($labelShift[$r['shift']] ?? $r['shift']) ?></span></p>
                            </td>
                            <td class="whitespace-nowrap"><?= esc($r['ruang'] ?: '—') ?></td>
                            <td class="text-center"><a href="<?= $base ?>/pengawas/<?= (int) $r['id'] ?>" class="lencana <?= $lencanaJumlah($jml) ?> hover:underline" title="Kelola pengawas" aria-label="Kelola pengawas (<?= $jml ?> orang)"><?= ikon('users', 'h-3.5 w-3.5') ?><?= $jml ?></a></td>
                            <td class="text-center"><a href="<?= $base ?>/pembuat-soal<?= $qtp ?>" class="lencana <?= $lencanaJumlah($pbs) ?> hover:underline" title="Atur pembuat soal" aria-label="Atur pembuat soal (<?= $pbs ?> orang)"><?= ikon('pen-nib', 'h-3.5 w-3.5') ?><?= $pbs ?></a></td>
                            <td class="text-center"><span class="<?= $tak > 0 ? 'font-semibold text-yellow-700 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= $tak ?></span></td>
                            <?php // Aksi satu deret rata: [cetak daftar hadir] [cetak berita acara] [Ubah] [hapus] — ikon kotak seragam (btn-ikon).
                            // Dulu aksi-baris bersarang di dalam div lain + tombol Hapus berlabel → lebar tak rata. Menu tarik-turun sengaja
                            // TIDAK dipakai di baris: .tabel-bungkus overflow-x-auto memotong menu di baris paling bawah. ?>
                            <td class="kolom-aksi">
                                <div class="aksi-baris">
                                    <a href="<?= $base ?>/daftar-hadir/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm btn-ikon" title="Cetak daftar hadir" aria-label="Cetak daftar hadir"><?= ikon('printer', 'h-4 w-4') ?></a>
                                    <a href="<?= $base ?>/berita-acara/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm btn-ikon" title="Cetak berita acara" aria-label="Cetak berita acara"><?= ikon('file-lines', 'h-4 w-4') ?></a>
                                    <button type="button" class="btn btn-utama btn-sm btn-ikon" title="Ubah jadwal" aria-label="Ubah jadwal" data-ubah data-row="<?= esc((string) json_encode($barisUbah($r)), 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?></button>
                                    <?= $formHapus($r, $tak, ' btn-ikon', true) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $istTabel($istSisipan[count($rows)] ?? []) ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $totalJadwal, 'kunci' => ['q', 'tingkat', 'jurusan_id', 'per', 'tp'], 'satuan' => 'jadwal']) ?>

    <?= ui_crud_form_buka(['benda' => 'jadwal ujian', 'ukuran' => 'lg']) ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="id" :value="form.id">
        <div>
            <label class="lbl" for="uj-mapel">Mata pelajaran <span class="text-red-600 dark:text-red-400">*</span></label>
            <select id="uj-mapel" name="mapel_id" x-model="form.mapel_id" required class="inp">
                <option value="">— Pilih mata pelajaran —</option>
                <?php foreach ($mapelOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="uj-tingkat">Tingkat <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="uj-tingkat" name="tingkat" x-model="form.tingkat" required class="inp">
                    <option value="">— Pilih tingkat —</option>
                    <?php foreach ($tingkatList as $t): ?>
                        <option value="<?= esc($t, 'attr') ?>"><?= esc($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="uj-shift">Shift <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="uj-shift" name="shift" x-model="form.shift" required class="inp">
                    <option value="" disabled>— Pilih Pagi atau Siang —</option>
                    <?php foreach ($shiftList as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc($labelShift[$s] ?? $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="lbl" for="uj-jurusan">Jurusan <span class="font-normal text-gray-500 dark:text-gray-400">(kosongkan bila semua jurusan)</span></label>
            <select id="uj-jurusan" name="jurusan_id" x-model="form.jurusan_id" class="inp">
                <option value="">— Semua jurusan —</option>
                <?php foreach ($jurusanOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="lbl" for="uj-tanggal">Tanggal <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="date" id="uj-tanggal" name="tanggal" x-model="form.tanggal" required class="inp">
            </div>
            <div>
                <label class="lbl" for="uj-mulai">Jam mulai</label>
                <input type="time" id="uj-mulai" name="jam_mulai" x-model="form.jam_mulai" class="inp">
            </div>
            <div>
                <label class="lbl" for="uj-selesai">Jam selesai</label>
                <input type="time" id="uj-selesai" name="jam_selesai" x-model="form.jam_selesai" :required="form.jam_mulai !== ''" class="inp">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="uj-ruang">Ruang <span class="font-normal text-gray-500 dark:text-gray-400">(opsional, hanya catatan)</span></label>
                <input type="text" id="uj-ruang" name="ruang" x-model="form.ruang" maxlength="100" placeholder="boleh dikosongkan" class="inp">
                <p class="hint">Tidak ikut tercetak di Jadwal Ujian/Pengawas. Ruang pengawas diatur lewat Jumlah ruang di tab Periode.</p>
            </div>
            <div>
                <label class="lbl" for="uj-ket">Keterangan</label>
                <input type="text" id="uj-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
        <p class="hint">
            Mapel yang sama di jam sama boleh dibuat lebih dari satu baris (ujian paralel beda ruang).
            Yang ditolak hanya dua <b>mapel berbeda</b> pada tingkat, shift, dan jam yang beririsan.
            <b>Istirahat</b> tidak perlu ditambahkan sebagai jadwal: dihitung dari jeda jam, atau diatur di bagian Istirahat.
        </p>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import jadwal ujian',
        'aksi'        => $base . '/jadwal/import-preview' . $qtp,
        'templateUrl' => $base . '/jadwal/template' . $qtp,
        'catatan'     => 'Kolom: <b>Kode Mapel</b>, Tingkat, Kode Jurusan, <b>Shift (wajib: pagi atau siang)</b>, Tanggal, Jam Mulai, Jam Selesai, Ruang (boleh kosong), Keterangan. Mapel &amp; jurusan dicocokkan lewat <b>kode</b>-nya. Baris dengan mapel, tingkat, jurusan, shift, dan ruang yang sama akan <b>diperbarui</b>, bukan digandakan — jadi berkas boleh diunggah ulang setelah diperbaiki.',
    ]) ?>
</div>
<script defer src="<?= base_url('assets/js/admin/ujian/jadwal.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/jadwal.js') ?>" <?= csp_script_nonce() ?>></script>
