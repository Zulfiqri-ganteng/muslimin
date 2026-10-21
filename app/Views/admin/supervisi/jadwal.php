<?php
/**
 * Jadwal Supervisi (menu KURIKULUM → Supervisi Guru) — kit F5, mode CRUD. Saringan GET (tahun ajaran, semester, status, guru),
 * ringkasan periode, tabel kunjungan, dialog Tambah/Ubah (saran mapel & kelas dari Penugasan guru, GET …/jadwal/saran), dialog
 * Buat massal (banyak guru dibagi rata ke hari sekolah). Komponen Alpine "supervisiJadwal" (perluasan masterList) di
 * assets/js/admin/supervisi.js — tanpa skrip sebaris (siap CSP).
 *
 * @var array{ta: string, semester: string, status: string, guru_id: int} $saring
 * @var list<array<string, mixed>> $rows       Libraries\Supervisi::daftarJadwal (+ instrumen_id, instrumen_nama, ada_nilai)
 * @var array{dijadwalkan: int, selesai: int, batal: int, total: int, guru_belum: list<array>, guru_sudah: list<int>} $ringkas
 * @var list<string> $pilihanTa
 * @var list<array{id: int, nama: string, nip: string}> $guru
 * @var array{saran: list<array{id: int, nama: string, jabatan: string}>, lain: list<array{id: int, nama: string}>} $supervisor
 * @var list<array{id: int, nama: string}> $mapel
 * @var list<array{id: int, nama: string}> $kelas
 * @var list<array<string, mixed>> $instrumen  semua instrumen (aktif & nonaktif)
 * @var bool $kelola
 */

use App\Libraries\Keterlambatan;
use App\Libraries\Supervisi;

$base      = site_url('admin/supervisi/jadwal');
$periode   = Supervisi::labelPeriode($saring['ta'], $saring['semester']);
$qsKini    = http_build_query(array_filter(['ta' => $saring['ta'], 'semester' => $saring['semester'], 'status' => $saring['status'], 'guru_id' => $saring['guru_id'] ?: null]));
$insAktif  = array_values(array_filter($instrumen, static fn (array $i): bool => (int) $i['aktif'] === 1));
$idInsAktif = array_map(static fn (array $i): string => (string) $i['id'], $insAktif);
$sudah     = array_fill_keys($ringkas['guru_sudah'], true);

// Rentang bawaan Buat massal: hari ini (atau awal semester yang ditampilkan bila bukan semester sekarang) s.d. 2 minggu, dalam semester itu.
[$taAwal] = explode('/', $saring['ta']);
$awalSem  = $saring['semester'] === 'ganjil' ? $taAwal . '-07-01' : ((int) $taAwal + 1) . '-01-01';
$akhirSem = $saring['semester'] === 'ganjil' ? $taAwal . '-12-31' : ((int) $taAwal + 1) . '-06-30';
$mulaiM   = max($awalSem, min(date('Y-m-d'), $akhirSem));
$selesaiM = min($akhirSem, date('Y-m-d', strtotime($mulaiM . ' +13 days')));

// Isian sebelum galat simpan (dialog Tambah dibuka lagi terisi).
$lama = old('guru_id') !== null ? [
    'guru_id' => (string) old('guru_id'), 'supervisor_id' => (string) old('supervisor_id'), 'tanggal' => (string) old('tanggal'), 'jam' => (string) old('jam'),
    'mapel_id' => (string) old('mapel_id'), 'kelas_id' => (string) old('kelas_id'), 'catatan' => (string) old('catatan'),
    'instrumen' => array_map('strval', (array) (old('instrumen') ?? [])),
] : null;

$opsiSupervisor = static function () use ($supervisor): string {
    $h = '<option value="">— pilih supervisor —</option>';
    if ($supervisor['saran'] !== []) {
        $h .= '<optgroup label="Kepala sekolah & wakil">';
        foreach ($supervisor['saran'] as $s) {
            $h .= '<option value="' . $s['id'] . '">' . esc($s['nama']) . ' — ' . esc($s['jabatan']) . '</option>';
        }
        $h .= '</optgroup>';
    }
    $h .= '<optgroup label="Guru lain">';
    foreach ($supervisor['lain'] as $s) {
        $h .= '<option value="' . $s['id'] . '">' . esc($s['nama']) . '</option>';
    }

    return $h . '</optgroup>';
};
$wajib = '<span class="text-red-600 dark:text-red-400">*</span>';

// Saringan (kiri bilah alat): partial bersama halaman Jadwal & Penilaian.
$saringanHtml = view('admin/supervisi/saringan', ['base' => $base, 'saring' => $saring, 'pilihanTa' => $pilihanTa, 'guru' => $guru], ['saveData' => false]);
$massalTombol = ($kelola ? '<button type="button" class="btn btn-netral" @click="bukaMassal()">' . ikon('users-group', 'h-4 w-4') . 'Buat massal</button>' : '')
    . '<a href="' . site_url('admin/supervisi/program') . ($qsKini !== '' ? '?' . esc($qsKini, 'attr') : '') . '" class="btn btn-netral" title="Program supervisi periode ini (ikut saringan) ke Excel">'
    . ikon('download', 'h-4 w-4') . 'Unduh Program (Excel)</a>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_jadwal_v1',
    'helpTitle' => 'Jadwal supervisi',
    'helpKelas' => '',
    'helpBody'  => '<p>Susun <b>kunjungan supervisi</b> tiap semester: guru yang disupervisi, <b>supervisor</b> (penilai, biasanya kepala sekolah/wakil), tanggal, dan <b>instrumen</b> yang dipakai. Setelah memilih guru, tekan salah satu <b>saran mapel & kelas</b> dari Penugasan agar terisi otomatis.</p>'
        . '<p class="mt-2"><b>Buat massal</b>: centang banyak guru sekaligus, pilih supervisor dan rentang tanggal — jadwal dibagi rata ke hari sekolah (paling banyak N guru per hari). Mapel & kelas diambil dari Penugasan; ubah satu per satu bila perlu. Guru yang sudah punya jadwal semester itu dilewati.</p>'
        . '<p class="mt-2">Tidak jadi? <b>Ubah → Batalkan</b>. Jadwal yang sudah ada penilaiannya tidak bisa dihapus.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="supervisiJadwal" data-base="<?= $base ?>" data-entity="jadwal" data-saran="<?= $base ?>/saran"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'guru_id' => '', 'supervisor_id' => '', 'tanggal' => '', 'jam' => '', 'mapel_id' => '', 'kelas_id' => '',
         'instrumen' => $idInsAktif, 'catatan' => '', 'batal' => false, 'ada_nilai' => false]), 'attr') ?>"
     data-guru-massal="<?= esc((string) json_encode(array_map(static fn (array $g): array => ['id' => (string) $g['id'], 'nama' => $g['nama'], 'sudah' => isset($sudah[$g['id']])], $guru)), 'attr') ?>"
     data-massal-awal="<?= esc((string) json_encode(['instrumen' => $idInsAktif]), 'attr') ?>"
     data-isian-lama="<?= esc((string) json_encode($lama), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'      => $base,
        'cari'         => null,
        'kiriHtml'     => $saringanHtml,
        'tambah'       => $kelola,
        'labelTambah'  => 'Tambah jadwal',
        'impor'        => false,
        'tambahanHtml' => $massalTombol,
    ]) ?>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
        <span class="font-semibold text-gray-900 dark:text-white"><?= esc($periode) ?></span>
        <span><span class="titik-status titik-status-biru"><?= $ringkas['dijadwalkan'] ?> dijadwalkan</span></span>
        <span><span class="titik-status titik-status-hijau"><?= $ringkas['selesai'] ?> selesai</span></span>
        <?php if ($ringkas['batal'] > 0): ?><span><span class="titik-status titik-status-merah"><?= $ringkas['batal'] ?> batal</span></span><?php endif; ?>
        <?php if ($ringkas['guru_belum'] !== []): ?>
            <span class="lencana lencana-kuning" title="<?= esc(implode(', ', array_column($ringkas['guru_belum'], 'nama')), 'attr') ?>"><?= count($ringkas['guru_belum']) ?> guru belum dijadwalkan</span>
        <?php elseif ($guru !== []): ?>
            <span class="lencana lencana-hijau">Semua guru sudah dijadwalkan</span>
        <?php endif; ?>
    </div>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Guru</th>
                    <th scope="col">Mapel &amp; kelas</th>
                    <th scope="col">Supervisor</th>
                    <th scope="col">Instrumen</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal supervisi',
                            'teks' => 'Belum ada kunjungan supervisi ' . $periode . ($saring['status'] !== '' || $saring['guru_id'] > 0 ? ' dengan saringan ini' : '') . '.'
                                . ($kelola ? ' Tekan Tambah jadwal atau Buat massal.' : '')]) ?></td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php
                    $batal = $r['status'] === 'batal';
                    $titik = ['dijadwalkan' => 'titik-status-biru', 'selesai' => 'titik-status-hijau', 'batal' => 'titik-status-merah'][$r['status']] ?? '';
                    ?>
                    <tr<?= $batal ? ' class="opacity-60"' : '' ?>>
                        <td class="whitespace-nowrap">
                            <p class="sel-judul"><?= esc(Keterlambatan::hariTanggal((string) $r['tanggal'])) ?></p>
                            <p class="sel-ket"><?= $r['jam'] !== null ? 'Jam ' . esc(substr((string) $r['jam'], 0, 5)) : 'Jam belum diisi' ?></p>
                        </td>
                        <td>
                            <p class="sel-judul"><?= esc($r['nama_guru']) ?></p>
                            <?php if (($r['nip_guru'] ?? '') !== ''): ?><p class="sel-ket">NIP <?= esc($r['nip_guru']) ?></p><?php endif; ?>
                        </td>
                        <td>
                            <p><?= esc($r['nama_mapel'] ?? '—') ?></p>
                            <p class="sel-ket"><?= esc($r['nama_kelas'] ?? 'Tanpa kelas') ?></p>
                        </td>
                        <td>
                            <p><?= esc($r['nama_supervisor']) ?></p>
                            <?php if (($r['jabatan_supervisor'] ?? '') !== ''): ?><p class="sel-ket"><?= esc($r['jabatan_supervisor']) ?></p><?php endif; ?>
                        </td>
                        <td>
                            <div class="flex max-w-xs flex-wrap gap-1">
                                <?php foreach ($r['instrumen_nama'] as $n): ?>
                                    <span class="lencana lencana-abu"><?= esc(preg_replace('/\s*\(.*\)$/', '', $n)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="titik-status <?= $titik ?> font-medium"><?= esc(Supervisi::STATUS[$r['status']] ?? $r['status']) ?></span>
                            <?php if ($r['nilai_akhir'] !== null): ?><p class="sel-ket">Nilai <?= esc(number_format((float) $r['nilai_akhir'], 2, ',', '.')) ?> · <?= esc((string) $r['predikat']) ?></p><?php endif; ?>
                            <?php if (($r['catatan'] ?? '') !== ''): ?><p class="sel-ket max-w-48 truncate" title="<?= esc($r['catatan'], 'attr') ?>"><?= esc($r['catatan']) ?></p><?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'tambahanHtml' => $batal ? '' : '<a href="' . site_url('admin/supervisi/penilaian/' . (int) $r['id']) . '?kembali=' . rawurlencode($qsKini) . '" class="btn btn-sukses btn-sm">'
                                    . ikon('file-pen', 'h-4 w-4') . ($r['status'] === 'selesai' ? 'Hasil' : 'Nilai') . '</a>',
                                'row'        => $kelola ? [
                                    'id' => (int) $r['id'], 'guru_id' => (string) ($r['guru_id'] ?? ''), 'supervisor_id' => (string) ($r['supervisor_id'] ?? ''),
                                    'tanggal' => (string) $r['tanggal'], 'jam' => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : '',
                                    'mapel_id' => (string) ($r['mapel_id'] ?? ''), 'kelas_id' => (string) ($r['kelas_id'] ?? ''),
                                    'instrumen' => array_map('strval', $r['instrumen_id']), 'catatan' => (string) ($r['catatan'] ?? ''),
                                    'batal' => $batal, 'ada_nilai' => $r['ada_nilai'],
                                ] : null,
                                'hapusUrl'   => $kelola && ! $r['ada_nilai'] && $r['status'] !== 'selesai' ? $base . '/delete/' . (int) $r['id'] : null,
                                'konfirmasi' => 'Hapus jadwal supervisi ' . $r['nama_guru'] . ' (' . Keterlambatan::hariTanggal((string) $r['tanggal']) . ')?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'jadwal']) ?>

    <?php if ($kelola): ?>
        <!-- ===== Dialog Tambah / Ubah jadwal ===== -->
        <?= ui_crud_form_buka(['benda' => 'Jadwal Supervisi', 'judulTambah' => 'Tambah Jadwal Supervisi', 'ukuran' => 'lg']) ?>
            <input type="hidden" name="kembali" value="<?= esc($qsKini, 'attr') ?>">
            <div>
                <label class="lbl" for="sj-guru">Guru yang disupervisi <?= $wajib ?></label>
                <select id="sj-guru" name="guru_id" x-model="form.guru_id" required class="inp">
                    <option value="">— pilih guru —</option>
                    <?php foreach ($guru as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= esc($g['nama']) ?><?= $g['nip'] !== '' ? ' (NIP ' . esc($g['nip']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1" x-show="form.ada_nilai" x-cloak>Kunjungan ini sudah ada penilaiannya — gurunya tidak bisa diganti.</p>
            </div>

            <div x-show="form.guru_id" x-cloak>
                <p class="lbl">Saran dari Penugasan</p>
                <p class="hint" x-show="memuatSaran">Memuat…</p>
                <p class="hint" x-show="adaPesanSaran()" x-text="pesanSaran"></p>
                <div class="flex flex-wrap gap-2" x-show="saran.length">
                    <template x-for="s in saran" :key="kunciSaran(s)">
                        <button type="button" class="btn btn-netral btn-xs" :class="kelasSaran(s)" :aria-pressed="saranTerpilih(s)" @click="pakaiSaran(s)" x-text="labelSaran(s)"></button>
                    </template>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="sj-mapel">Mata pelajaran</label>
                    <select id="sj-mapel" name="mapel_id" x-model="form.mapel_id" class="inp">
                        <option value="">— tanpa mapel —</option>
                        <?php foreach ($mapel as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= esc($m['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="sj-kelas">Kelas yang diamati</label>
                    <select id="sj-kelas" name="kelas_id" x-model="form.kelas_id" class="inp">
                        <option value="">— tanpa kelas —</option>
                        <?php foreach ($kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= esc($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="lbl" for="sj-sup">Supervisor (penilai) <?= $wajib ?></label>
                <select id="sj-sup" name="supervisor_id" x-model="form.supervisor_id" required class="inp"><?= $opsiSupervisor() ?></select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="sj-tanggal">Tanggal <?= $wajib ?></label>
                    <input type="date" id="sj-tanggal" name="tanggal" x-model="form.tanggal" required class="inp">
                </div>
                <div>
                    <label class="lbl" for="sj-jam">Jam <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                    <input type="time" id="sj-jam" name="jam" x-model="form.jam" class="inp">
                </div>
            </div>

            <fieldset>
                <legend class="lbl">Instrumen yang dipakai <?= $wajib ?></legend>
                <div class="space-y-2">
                    <?php foreach ($instrumen as $ins): ?>
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300"<?= (int) $ins['aktif'] === 1 ? '' : ' x-show="punyaInstrumen(\'' . (int) $ins['id'] . '\')" x-cloak' ?>>
                            <input type="checkbox" name="instrumen[]" value="<?= (int) $ins['id'] ?>" x-model="form.instrumen" class="cek mt-0.5">
                            <span><?= esc($ins['nama']) ?><?= (int) $ins['aktif'] === 1 ? '' : ' <span class="lencana lencana-abu">nonaktif</span>' ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div>
                <label class="lbl" for="sj-catatan">Catatan <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                <input type="text" id="sj-catatan" name="catatan" x-model="form.catatan" maxlength="<?= Supervisi::MAKS_CATATAN ?>" class="inp" placeholder="mis. ruang praktik, jam ke-3">
            </div>

            <label class="saklar" x-show="mode !== 'add'" x-cloak>
                <input type="checkbox" name="batal" value="1" x-model="form.batal" class="peer sr-only">
                <span class="saklar-jalur"></span>
                <span class="saklar-label">Batalkan kunjungan ini</span>
            </label>
        <?= ui_crud_form_tutup() ?>

        <!-- ===== Dialog Buat massal ===== -->
        <div class="dialog" :data-buka="massalOpen" @keydown.escape.stop="tutupMassal()" role="dialog" aria-modal="true" aria-labelledby="sj-massal-judul">
            <div class="dialog-latar" @click="tutupMassal()" aria-hidden="true"></div>
            <div class="dialog-panel dialog-panel-xl" tabindex="-1">
                <div class="dialog-kepala">
                    <h3 id="sj-massal-judul" class="dialog-judul">Buat Jadwal Supervisi Massal</h3>
                    <button type="button" class="tombol-tutup" @click="tutupMassal()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
                </div>
                <form method="post" action="<?= $base ?>/massal" @submit="kirimMassal">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kembali" value="<?= esc($qsKini, 'attr') ?>">
                    <div class="dialog-isi grid gap-5 lg:grid-cols-2">
                        <fieldset class="min-w-0">
                            <legend class="lbl">Guru yang disupervisi <?= $wajib ?> <span class="font-normal text-gray-500 dark:text-gray-400" x-text="teksJumlahGuru()"></span></legend>
                            <input type="search" class="inp" placeholder="Cari nama guru" x-model="cariGuru" @input="saringGuru()" aria-label="Cari nama guru">
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" class="btn btn-teks btn-xs" @click="centangBelum()">Centang yang belum dijadwalkan</button>
                                <button type="button" class="btn btn-teks btn-xs" @click="kosongkanGuru()">Kosongkan</button>
                            </div>
                            <div class="mt-2 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                <template x-for="g in guruTampil" :key="g.id">
                                    <label class="flex items-center gap-2 rounded px-1 py-0.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                        <input type="checkbox" name="guru_ids[]" :value="g.id" x-model="massal.guru" class="cek">
                                        <span class="min-w-0 flex-1 truncate" x-text="g.nama"></span>
                                        <span class="lencana lencana-abu" x-show="g.sudah">sudah dijadwalkan</span>
                                    </label>
                                </template>
                                <p class="hint px-1" x-show="!guruTampil.length">Tidak ada guru dengan nama itu.</p>
                            </div>
                            <p class="hint mt-1">Tanda "sudah dijadwalkan" = <?= esc($periode) ?>. Guru yang sudah punya jadwal di semester itu dilewati.</p>
                        </fieldset>

                        <div class="min-w-0 space-y-4">
                            <div>
                                <label class="lbl" for="sm-sup">Supervisor (penilai) <?= $wajib ?></label>
                                <select id="sm-sup" name="supervisor_id" required class="inp"><?= $opsiSupervisor() ?></select>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="lbl" for="sm-mulai">Dari tanggal <?= $wajib ?></label>
                                    <input type="date" id="sm-mulai" name="mulai" value="<?= esc($mulaiM, 'attr') ?>" required class="inp">
                                </div>
                                <div>
                                    <label class="lbl" for="sm-selesai">Sampai tanggal <?= $wajib ?></label>
                                    <input type="date" id="sm-selesai" name="selesai" value="<?= esc($selesaiM, 'attr') ?>" required class="inp">
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="lbl" for="sm-per">Maks. guru per hari <?= $wajib ?></label>
                                    <input type="number" id="sm-per" name="per_hari" value="<?= Supervisi::MASSAL_PER_HARI ?>" min="1" max="<?= Supervisi::MASSAL_MAKS_PER_HARI ?>" required class="inp">
                                </div>
                                <div>
                                    <label class="lbl" for="sm-jam">Jam <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                                    <input type="time" id="sm-jam" name="jam" class="inp">
                                </div>
                            </div>
                            <fieldset>
                                <legend class="lbl">Instrumen yang dipakai <?= $wajib ?></legend>
                                <div class="space-y-2">
                                    <?php foreach ($insAktif as $ins): ?>
                                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="instrumen[]" value="<?= (int) $ins['id'] ?>" x-model="massal.instrumen" class="cek mt-0.5">
                                            <span><?= esc($ins['nama']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <?= ui_peringatan('info', 'Jadwal dibagi rata ke hari sekolah dalam rentang itu (urut nama guru). Mapel & kelas diisi dari Penugasan (jam pelajaran terbanyak) — ubah satu per satu bila perlu.') ?>
                            <p class="err-msg" x-show="pesanMassal !== ''" x-text="pesanMassal" x-cloak aria-live="polite"></p>
                        </div>
                    </div>
                    <div class="dialog-kaki">
                        <button type="button" class="btn btn-netral" @click="tutupMassal()">Batal</button>
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Buat jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/supervisi.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/supervisi.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
