<?php
/**
 * Tab Ujian Susulan — tindak lanjut atas catatan ketidakhadiran. Rombak tampilan F7: kartu status bisa diklik, bilah alat kit
 * (ui_crud_alat), tabel-crud + lencana, tombol baris ber-ikon (Jadwalkan biru, Status, Hapus merah), ui_crud_kaki, dialog kit;
 * komponen Alpine "susulanUjian" di assets/js/admin/ujian/susulan.js → siap CSP.
 *
 * Alur status: belum → dijadwalkan → selesai (atau batal). Penjadwalan bisa
 * satuan maupun massal lewat centang baris.
 *
 * @var array<int,array>     $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array<string,mixed>  $filter
 * @var int                  $per
 * @var int                  $totalSusulan
 * @var array<string,int>    $ringkasStatus
 * @var array<string,int>    $ringkasAlasan
 * @var array<int,string>    $kelasOpts
 * @var array<int,string>    $mapelOpts
 * @var array<int,string>    $guruOpts
 * @var array<int,string>    $statusList
 * @var array<int,string>    $alasanList
 * @var array<string,string> $statusLabel
 * @var array                $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$jam = static fn ($j) => $j ? substr((string) $j, 0, 5) : '';

$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
$warnaStatus = ['belum' => 'lencana-kuning', 'dijadwalkan' => 'lencana-merek', 'selesai' => 'lencana-hijau', 'batal' => 'lencana-abu'];
$warnaAlasan = ['sakit' => 'lencana-pink', 'izin' => 'lencana-biru', 'alpa' => 'lencana-merah', 'lainnya' => 'lencana-abu'];

$adaFilter = $filter['q'] !== '' || $filter['kelas_id'] > 0 || $filter['mapel_id'] > 0
    || $filter['status'] !== '' || $filter['alasan'] !== '';

// Filter aktif dibawa pulang setelah aksi supaya halaman tidak melompat.
$sisaFilter = http_build_query(array_filter([
    'q'        => $filter['q'],
    'kelas_id' => $filter['kelas_id'] ?: null,
    'mapel_id' => $filter['mapel_id'] ?: null,
    'status'   => $filter['status'],
    'alasan'   => $filter['alasan'],
    'per'      => $per !== 20 ? $per : null,
]));

$opsiAlasan = [];
foreach ($alasanList as $a) {
    $opsiAlasan[$a] = $labelAlasan[$a] ?? $a;
}
$opsiStatus = [];
foreach ($statusList as $s) {
    $opsiStatus[$s] = $statusLabel[$s] ?? $s;
}
$kartuStatus = [
    ['belum', 'Belum dijadwalkan', 'text-yellow-700 dark:text-yellow-400'],
    ['dijadwalkan', 'Sudah dijadwalkan', 'text-brand-700 dark:text-brand-400'],
    ['selesai', 'Selesai', 'text-green-700 dark:text-green-400'],
    ['batal', 'Batal', 'text-gray-500 dark:text-gray-400'],
];
?>

<div x-data="susulanUjian" data-base="<?= esc($base, 'attr') ?>" data-hari-ini="<?= date('Y-m-d') ?>" class="space-y-4">

    <!-- Ringkasan status (klik = saring status) -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php foreach ($kartuStatus as [$k, $judul, $warna]): $aktif = $filter['status'] === $k; ?>
            <a href="<?= $base ?>/susulan<?= $qtp ?>&amp;status=<?= $k ?>"<?= $aktif ? ' aria-current="page"' : '' ?>
               class="kartu kartu-isi kartu-tautan<?= $aktif ? ' ring-2 ring-brand-500 dark:ring-brand-400' : '' ?>">
                <p class="stat-label text-sm"><?= esc($judul) ?></p>
                <p class="stat-angka mt-2 tabular-nums <?= $warna ?>"><?= number_format((int) ($ringkasStatus[$k] ?? 0), 0, ',', '.') ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="kartu overflow-hidden" aria-label="Daftar ujian susulan">
        <?= ui_crud_alat([
            'baseUrl'        => $base . '/susulan' . $qtp,
            'cari'           => 'Cari nama atau NIS siswa…',
            'q'              => (string) $filter['q'],
            'filter'         => [
                ['name' => 'kelas_id', 'value' => $filter['kelas_id'] > 0 ? (string) $filter['kelas_id'] : '', 'all' => 'Semua kelas', 'options' => $kelasOpts],
                ['name' => 'mapel_id', 'value' => $filter['mapel_id'] > 0 ? (string) $filter['mapel_id'] : '', 'all' => 'Semua mapel', 'options' => $mapelOpts],
                ['name' => 'alasan', 'value' => (string) $filter['alasan'], 'all' => 'Semua alasan', 'options' => $opsiAlasan],
                ['name' => 'status', 'value' => (string) $filter['status'], 'all' => 'Semua status', 'options' => $opsiStatus],
            ],
            'per'            => $per,
            'filterLangsung' => true,
            'hidden'         => ['tp' => $tp],
            'tambah'         => false,
            'impor'          => false,
            'tambahanHtml'   => '<span class="lencana lencana-merek" x-show="adaPilih()" x-cloak>Terpilih: <span x-text="jumlahPilih()">0</span></span>'
                . '<button type="button" class="btn btn-utama" @click="bukaMassal()" :disabled="tanpaPilih()">' . ikon('calendar-plus', 'h-4 w-4') . 'Jadwalkan terpilih</button>',
        ]) ?>

        <div class="tabel-bungkus">
            <table class="tabel-crud">
                <thead>
                    <tr>
                        <th scope="col" class="kolom-cek"><input type="checkbox" class="cek" @change="semua($event)" aria-label="Pilih semua baris di halaman ini"></th>
                        <th scope="col">Siswa</th>
                        <th scope="col">Mapel &amp; tanggal ujian</th>
                        <th scope="col">Alasan</th>
                        <th scope="col">Jadwal susulan</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="kolom-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! $rows): ?>
                        <tr>
                            <td colspan="7">
                                <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => $adaFilter ? 'Tidak ada data yang cocok dengan filter' : 'Belum ada catatan ketidakhadiran',
                                    'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Data muncul otomatis dari tab Ketidakhadiran.']) ?>
                            </td>
                        </tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                        $data = [
                            'id'                  => (string) $r['id'],
                            'nama'                => $r['siswa_nama'] ?? '',
                            'status'              => $r['status'],
                            'tanggal_susulan'     => $r['tanggal_susulan'] ?? '',
                            'jam_susulan'         => $r['jam_susulan'] ? substr((string) $r['jam_susulan'], 0, 5) : '',
                            'ruang_susulan'       => $r['ruang_susulan'] ?? '',
                            'pengawas_guru_id'    => (string) ($r['pengawas_guru_id'] ?? ''),
                            'tanggal_pelaksanaan' => $r['tanggal_pelaksanaan'] ?? '',
                        ];
                        $dataRow = esc((string) json_encode($data), 'attr');
                        ?>
                        <tr>
                            <td class="kolom-cek"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" x-model="pilih" class="cek" aria-label="Pilih <?= esc($r['siswa_nama'] ?? 'siswa', 'attr') ?>"></td>
                            <td>
                                <p class="sel-judul"><?= esc($r['siswa_nama'] ?? 'Siswa dihapus') ?></p>
                                <p class="sel-ket"><?= esc($r['nama_kelas'] ?? '—') ?><?= $r['nis'] ? ' · NIS ' . esc($r['nis']) : '' ?></p>
                            </td>
                            <td>
                                <p class="text-gray-900 dark:text-white"><?= esc($r['nama_mapel'] ?? 'Tanpa mapel') ?></p>
                                <p class="sel-ket"><?= $tgl($r['tanggal_ujian']) ?></p>
                            </td>
                            <td>
                                <span class="lencana <?= $warnaAlasan[$r['alasan']] ?? 'lencana-abu' ?>"><?= esc($labelAlasan[$r['alasan']] ?? $r['alasan']) ?></span>
                                <?php if ($r['keterangan']): ?><p class="sel-ket mt-1"><?= esc($r['keterangan']) ?></p><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['tanggal_susulan']): ?>
                                    <p class="whitespace-nowrap text-gray-900 dark:text-white"><?= $tgl($r['tanggal_susulan']) ?> <?= esc($jam($r['jam_susulan'])) ?></p>
                                    <p class="sel-ket"><?= $r['ruang_susulan'] ? 'Ruang ' . esc($r['ruang_susulan']) : 'Ruang —' ?><?= $r['pengawas_nama'] ? ' · ' . esc($r['pengawas_nama']) : '' ?></p>
                                <?php else: ?>
                                    <span class="text-gray-400 dark:text-gray-500">Belum dijadwalkan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lencana <?= $warnaStatus[$r['status']] ?? $warnaStatus['belum'] ?>"><?= esc($statusLabel[$r['status']] ?? $r['status']) ?></span>
                                <?php if ($r['status'] === 'selesai' && $r['tanggal_pelaksanaan']): ?>
                                    <p class="sel-ket mt-1">Dilaksanakan <?= $tgl($r['tanggal_pelaksanaan']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="kolom-aksi">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <button type="button" class="btn btn-utama btn-sm" data-jadwalkan data-row="<?= $dataRow ?>" title="Jadwalkan susulan"><?= ikon('calendar-plus', 'h-4 w-4') ?>Jadwalkan</button>
                                    <button type="button" class="btn btn-netral btn-sm" data-ubah-status data-row="<?= $dataRow ?>" title="Ubah status"><?= ikon('check-circle', 'h-4 w-4') ?>Status</button>
                                    <form method="post" action="<?= $base ?>/susulan/<?= (int) $r['id'] ?>/hapus" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
                                        <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">
                                        <button type="submit" class="btn btn-bahaya btn-sm" data-confirm="Hapus catatan ketidakhadiran siswa ini?" data-confirm-judul="Hapus catatan?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $totalSusulan, 'kunci' => ['q', 'kelas_id', 'mapel_id', 'status', 'alasan', 'per', 'tp'], 'satuan' => 'catatan']) ?>
    </section>

    <!-- Dialog Jadwalkan (satuan & massal memakai form yang sama) -->
    <?= ui_dialog_buka(['nama' => 'jadwal-susulan', 'judul' => 'Jadwalkan ujian susulan', 'ukuran' => 'lg']) ?>
    <form method="post" action="<?= $base ?>/susulan/jadwalkan">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">
        <template x-for="id in pilih" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
        <div class="dialog-isi">
            <p class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white" x-text="jumlahPilih()">0</b> siswa akan dijadwalkan. Baris yang sudah <b>Selesai</b> otomatis dilewati.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="lbl" for="sl-tanggal">Tanggal susulan <span class="text-red-600">*</span></label>
                    <input type="date" id="sl-tanggal" name="tanggal_susulan" x-model="form.tanggal_susulan" required class="inp">
                </div>
                <div>
                    <label class="lbl" for="sl-jam">Jam</label>
                    <input type="time" id="sl-jam" name="jam_susulan" x-model="form.jam_susulan" class="inp">
                </div>
                <div>
                    <label class="lbl" for="sl-ruang">Ruang</label>
                    <input type="text" id="sl-ruang" name="ruang_susulan" x-model="form.ruang_susulan" maxlength="100" class="inp">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="sl-pengawas">Pengawas <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                    <select id="sl-pengawas" name="pengawas_guru_id" x-model="form.pengawas_guru_id" class="inp">
                        <option value="">— Tidak ditentukan —</option>
                        <?php foreach ($guruOpts as $id => $lbl): ?>
                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan jadwal</button>
        </div>
    </form>
    <?= ui_dialog_tutup() ?>

    <!-- Dialog Ubah Status -->
    <?= ui_dialog_buka(['nama' => 'status-susulan', 'judul' => 'Ubah status susulan']) ?>
    <form method="post" :action="statusUrl()">
        <?= csrf_field() ?>
        <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
        <input type="hidden" name="kembali" value="<?= esc($sisaFilter, 'attr') ?>">
        <div class="dialog-isi">
            <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="statusForm.nama"></p>
            <div>
                <label class="lbl" for="st-status">Status</label>
                <select id="st-status" name="status" x-model="statusForm.status" class="inp">
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc($statusLabel[$s] ?? $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div x-show="statusSelesai()" x-cloak>
                <label class="lbl" for="st-tanggal">Tanggal pelaksanaan</label>
                <input type="date" id="st-tanggal" name="tanggal_pelaksanaan" x-model="statusForm.tanggal_pelaksanaan" class="inp">
            </div>
            <p class="hint">Kembalikan ke <b>Belum</b> atau <b>Sudah dijadwalkan</b> lebih dulu bila susulan yang sudah selesai perlu dijadwalkan ulang.</p>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
        </div>
    </form>
    <?= ui_dialog_tutup() ?>
</div>
<script defer src="<?= base_url('assets/js/admin/ujian/susulan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/susulan.js') ?>" <?= csp_script_nonce() ?>></script>
