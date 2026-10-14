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
    'shift'       => 'semua',
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
    'shift'       => $r['shift'],
    'tanggal'     => $r['tanggal'],
    'jam_mulai'   => $r['jam_mulai'] ? substr((string) $r['jam_mulai'], 0, 5) : '',
    'jam_selesai' => $r['jam_selesai'] ? substr((string) $r['jam_selesai'], 0, 5) : '',
    'ruang'       => $r['ruang'] ?? '',
    'keterangan'  => $r['keterangan'] ?? '',
];
// Form Hapus: periode_id wajib ikut (Ujian::hapusJadwal memeriksa periode dari POST).
$formHapus = static function (array $r, int $tak, string $kelas) use ($base, $periode): string {
    return '<form method="post" action="' . $base . '/jadwal/' . (int) $r['id'] . '/hapus" class="inline">' . csrf_field()
        . '<input type="hidden" name="periode_id" value="' . (int) $periode['id'] . '">'
        . '<button type="submit" class="btn btn-bahaya btn-sm' . $kelas . '" data-confirm="' . esc('Hapus jadwal ini?' . ($tak > 0 ? ' ' . $tak . ' catatan ketidakhadiran TETAP tersimpan, hanya dilepas dari jadwal ini.' : '') . ' Penugasan pengawasnya ikut terhapus.', 'attr') . '"'
        . ' data-confirm-judul="Hapus jadwal?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">' . ikon('trash-bin', 'h-4 w-4') . 'Hapus</button></form>';
};
$lencanaJumlah = static fn (int $n): string => $n > 0 ? 'lencana-merek' : 'lencana-abu';
$tingkatOpsi = [];
foreach ($tingkatList as $t) {
    $tingkatOpsi[$t] = 'Tingkat ' . $t;
}
?>

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
        'hidden'         => ['tp' => $tp],
        'labelTambah'    => 'Tambah jadwal',
        'eksporUrl'      => $base . '/jadwal/export' . $qtp,
        'eksporTitle'    => 'Keluarkan jadwal ujian periode ini ke file Excel',
        'tambahanHtml'   => '<a href="' . $base . '/pembuat-soal' . $qtp . '" class="btn btn-netral" title="Tugaskan guru pembuat soal per jadwal (dasar honor Pembuatan Soal)">' . ikon('pen-nib', 'h-4 w-4') . 'Pembuat soal</a>',
    ]) ?>

    <?php if (! $rows): ?>
        <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => $adaFilter ? 'Tidak ada jadwal yang cocok dengan filter' : 'Belum ada jadwal ujian',
            'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Klik Tambah jadwal untuk mulai menyusun, atau Import dari Excel.']) ?>
    <?php else: ?>
        <!-- Kartu (HP): tabel lebar tak muat di layar sempit, jadi tiap jadwal tampil sebagai kartu dengan tombol berlabel -->
        <div class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
            <?php foreach ($rows as $r):
                $tsK  = strtotime((string) $r['tanggal']);
                $jmlK = (int) ($jmlPengawas[$r['id']] ?? 0);
                $pbsK = (int) ($jmlPembuat[$r['id']] ?? 0);
                $takK = (int) ($jmlTakHadir[$r['id']] ?? 0);
                ?>
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
        </div>

        <!-- Tabel (laptop) -->
        <div class="tabel-bungkus hidden md:block">
            <table class="tabel-crud">
                <thead>
                    <tr>
                        <th scope="col">Hari / tanggal</th>
                        <th scope="col">Jam</th>
                        <th scope="col">Mata pelajaran</th>
                        <th scope="col">Sasaran</th>
                        <th scope="col">Ruang</th>
                        <th scope="col" class="text-center">Pengawas</th>
                        <th scope="col" class="text-center">Pembuat soal</th>
                        <th scope="col" class="text-center">Tidak hadir</th>
                        <th scope="col" class="kolom-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $ts  = strtotime((string) $r['tanggal']);
                        $jml = (int) ($jmlPengawas[$r['id']] ?? 0);
                        $pbs = (int) ($jmlPembuat[$r['id']] ?? 0);
                        $tak = (int) ($jmlTakHadir[$r['id']] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <p class="sel-judul"><?= esc($HARI[(int) date('N', $ts)] ?? '') ?></p>
                                <p class="sel-ket"><?= date('d/m/Y', $ts) ?></p>
                            </td>
                            <td class="whitespace-nowrap"><?= esc($jam($r['jam_mulai'], $r['jam_selesai'])) ?></td>
                            <td>
                                <p class="sel-judul"><?= esc($r['nama_mapel'] ?? 'Mapel sudah dihapus') ?></p>
                                <?php if ($r['kode_mapel']): ?><p class="sel-ket"><?= esc($r['kode_mapel']) ?></p><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="font-medium text-gray-900 dark:text-white">Tingkat <?= esc($r['tingkat']) ?></span><?php if ($r['jurusan_kode']): ?><span class="text-gray-500 dark:text-gray-400"> · <?= esc($r['jurusan_kode']) ?></span><?php endif; ?>
                                <span class="lencana <?= $warnaShift[$r['shift']] ?? $warnaShift['semua'] ?> ms-1"><?= esc($labelShift[$r['shift']] ?? $r['shift']) ?></span>
                            </td>
                            <td><?= esc($r['ruang'] ?: '—') ?></td>
                            <td class="text-center"><a href="<?= $base ?>/pengawas/<?= (int) $r['id'] ?>" class="lencana <?= $lencanaJumlah($jml) ?> hover:underline" title="Kelola pengawas" aria-label="Kelola pengawas (<?= $jml ?> orang)"><?= ikon('users', 'h-3.5 w-3.5') ?><?= $jml ?></a></td>
                            <td class="text-center"><a href="<?= $base ?>/pembuat-soal<?= $qtp ?>" class="lencana <?= $lencanaJumlah($pbs) ?> hover:underline" title="Atur pembuat soal" aria-label="Atur pembuat soal (<?= $pbs ?> orang)"><?= ikon('pen-nib', 'h-3.5 w-3.5') ?><?= $pbs ?></a></td>
                            <td class="text-center"><span class="<?= $tak > 0 ? 'font-semibold text-yellow-700 dark:text-yellow-400' : 'text-gray-400 dark:text-gray-500' ?>"><?= $tak ?></span></td>
                            <td class="kolom-aksi">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="<?= $base ?>/daftar-hadir/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm" title="Cetak daftar hadir" aria-label="Cetak daftar hadir"><?= ikon('printer', 'h-4 w-4') ?></a>
                                    <a href="<?= $base ?>/berita-acara/<?= (int) $r['id'] ?><?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm" title="Cetak berita acara" aria-label="Cetak berita acara"><?= ikon('file-lines', 'h-4 w-4') ?></a>
                                    <?= ui_crud_aksi(['row' => $barisUbah($r)]) ?>
                                    <?= $formHapus($r, $tak, '') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $totalJadwal, 'kunci' => ['q', 'tingkat', 'jurusan_id', 'per', 'tp'], 'satuan' => 'jadwal']) ?>

    <?= ui_crud_form_buka(['benda' => 'jadwal ujian', 'ukuran' => 'lg']) ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="id" :value="form.id">
        <div>
            <label class="lbl" for="uj-mapel">Mata pelajaran <span class="text-red-600">*</span></label>
            <select id="uj-mapel" name="mapel_id" x-model="form.mapel_id" required class="inp">
                <option value="">— Pilih mata pelajaran —</option>
                <?php foreach ($mapelOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="uj-tingkat">Tingkat <span class="text-red-600">*</span></label>
                <select id="uj-tingkat" name="tingkat" x-model="form.tingkat" required class="inp">
                    <option value="">— Pilih tingkat —</option>
                    <?php foreach ($tingkatList as $t): ?>
                        <option value="<?= esc($t, 'attr') ?>"><?= esc($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="uj-shift">Shift</label>
                <select id="uj-shift" name="shift" x-model="form.shift" class="inp">
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
                <label class="lbl" for="uj-tanggal">Tanggal <span class="text-red-600">*</span></label>
                <input type="date" id="uj-tanggal" name="tanggal" x-model="form.tanggal" required class="inp">
            </div>
            <div>
                <label class="lbl" for="uj-mulai">Jam mulai</label>
                <input type="time" id="uj-mulai" name="jam_mulai" x-model="form.jam_mulai" class="inp">
            </div>
            <div>
                <label class="lbl" for="uj-selesai">Jam selesai</label>
                <input type="time" id="uj-selesai" name="jam_selesai" x-model="form.jam_selesai" class="inp">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="uj-ruang">Ruang</label>
                <input type="text" id="uj-ruang" name="ruang" x-model="form.ruang" maxlength="100" placeholder="mis. R1–R8 atau Aula" class="inp">
            </div>
            <div>
                <label class="lbl" for="uj-ket">Keterangan</label>
                <input type="text" id="uj-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
        <p class="hint">
            Mapel yang sama di jam sama boleh dibuat lebih dari satu baris (ujian paralel beda ruang).
            Yang ditolak hanya dua <b>mapel berbeda</b> pada tingkat, shift, dan jam yang beririsan.
        </p>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import jadwal ujian',
        'aksi'        => $base . '/jadwal/import-preview' . $qtp,
        'templateUrl' => $base . '/jadwal/template' . $qtp,
        'catatan'     => 'Kolom: <b>Kode Mapel</b>, Tingkat, Kode Jurusan, Shift, Tanggal, Jam Mulai, Jam Selesai, Ruang, Keterangan. Mapel &amp; jurusan dicocokkan lewat <b>kode</b>-nya. Baris dengan mapel, tingkat, jurusan, shift, dan ruang yang sama akan <b>diperbarui</b>, bukan digandakan — jadi berkas boleh diunggah ulang setelah diperbaiki.',
    ]) ?>
</div>
<script defer src="<?= base_url('assets/js/admin/ujian/jadwal.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/jadwal.js') ?>" <?= csp_script_nonce() ?>></script>
