<?php
/**
 * Catat Keterlambatan (menu KURIKULUM → Guru Piket) — kartu formulir catat cepat + kartu daftar terlambat per tanggal + dialog Ubah
 * (layar ≥ 1536 px: formulir di kiri, daftar di kanan; lebih sempit: formulir di atas dengan isian 2 kolom, tabel selebar halaman)
 * (kit F5). Komponen Alpine "keterlambatanPage" (perluasan masterList) di assets/js/admin/keterlambatan.js: kotak cari siswa
 * (GET admin/keterlambatan/siswa, hasil dari server) + dialog ubah — tanpa skrip sebaris (siap CSP).
 *
 * @var string     $tanggal     Y-m-d yang ditampilkan/dicatat
 * @var bool       $hariIni
 * @var list<array> $rows       catatan tanggal itu (Libraries\Keterlambatan::daftar)
 * @var array{pagi: list<array{id: int, nama: string}>, siang: list<array{id: int, nama: string}>} $piket  jadwal piket hari itu
 * @var list<array{id: int, nama: string}> $guru  semua guru yang bisa dipilih
 * @var list<string> $saranSanksi
 * @var int        $guruIngat   guru piket yang terakhir dipilih hari ini (0 = belum)
 * @var bool       $bolehKabari boleh menekan tombol WA orang tua & mengisi nomor HP orang tua (level Kelola)
 * @var bool       $bolehPesan  boleh mengubah templat pesan WA (khusus Admin)
 * @var string     $templatWa   templat pesan WA yang berlaku
 * Tiap baris $rows memuat 'kabar' (Libraries\KeterlambatanWa::kabar: url wa.me / alasan tidak bisa).
 */

use App\Libraries\Keterlambatan as Buku;

$base        = site_url('admin/keterlambatan');
$hariTanggal = Buku::hariTanggal($tanggal);
$namaHari    = Buku::HARI[(int) date('N', strtotime($tanggal))];
$kemarin     = date('Y-m-d', strtotime($tanggal . ' -1 day'));
$besok       = date('Y-m-d', strtotime($tanggal . ' +1 day'));
$guruPilih   = (int) (old('guru_id') ?: $guruIngat);
$idPiket     = array_column(array_merge($piket['pagi'], $piket['siang']), 'id');
$guruLain    = array_values(array_filter($guru, static fn (array $g): bool => ! in_array($g['id'], $idPiket, true)));
$siswaAwal   = old('siswa_id') ? ['id' => (int) old('siswa_id'), 'nama' => (string) old('siswa_label'), 'kelas' => '', 'nis' => ''] : null;
$bolehRekap  = \App\Libraries\HakAkses::boleh((string) (session('admin')['role'] ?? ''), 'admin/keterlambatan/rekap');

// Pilihan guru piket: jadwal piket hari itu (pagi, siang) di atas, lalu guru lain. $model = atribut tambahan pada <select>.
$opsiGuru = static function (int $pilih) use ($piket, $guruLain, $namaHari, $hariIni): string {
    $h   = '<option value="">— pilih guru piket —</option>';
    $ket = $hariIni ? 'hari ini' : $namaHari;
    foreach (['pagi' => 'Piket pagi ' . $ket, 'siang' => 'Piket siang ' . $ket] as $sh => $label) {
        if ($piket[$sh] !== []) {
            $h .= '<optgroup label="' . esc($label, 'attr') . '">';
            foreach ($piket[$sh] as $g) {
                $h .= '<option value="' . $g['id'] . '"' . ($g['id'] === $pilih ? ' selected' : '') . '>' . esc($g['nama']) . '</option>';
            }
            $h .= '</optgroup>';
        }
    }
    $h .= '<optgroup label="Guru lain">';
    foreach ($guruLain as $g) {
        $h .= '<option value="' . $g['id'] . '"' . ($g['id'] === $pilih ? ' selected' : '') . '>' . esc($g['nama']) . '</option>';
    }

    return $h . '</optgroup>';
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'keterlambatan_v1',
    'helpTitle' => 'Catat keterlambatan siswa',
    'helpBody'  => '<p>Catat siswa yang <b>datang terlambat</b>. Pilih dulu <b>guru piket</b> yang bertugas (disarankan dari Jadwal Piket hari itu; pilihan diingat sampai hari berganti), lalu ketik <b>nama atau NIS</b> siswa — kelasnya terisi otomatis. Sanksi dan keterangan bebas ditulis.</p>'
        . '<p class="mt-2">Satu siswa hanya bisa dicatat <b>sekali per hari</b>. Salah pilih siswa? Hapus catatannya lalu catat ulang. Untuk melihat atau mencatat hari lain, pilih tanggal di atas daftar.</p>',
]) ?>

<div x-data="keterlambatanPage" data-base="<?= $base ?>" data-entity="catatan" data-cari="<?= $base ?>/siswa" data-tanggal="<?= esc($tanggal, 'attr') ?>"
     data-hari-ini="<?= $hariIni ? '1' : '0' ?>" data-siswa-awal="<?= esc((string) json_encode($siswaAwal), 'attr') ?>"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'nama_siswa' => '', 'jam' => '', 'guru_id' => '', 'sanksi' => '', 'keterangan' => '']), 'attr') ?>"
     class="grid items-start gap-4 2xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">

    <!-- ===== Formulir catat ===== -->
    <form method="post" action="<?= $base ?>" class="kartu" @submit="kirim">
        <?= csrf_field() ?>
        <input type="hidden" name="tanggal" value="<?= esc($tanggal, 'attr') ?>">
        <input type="hidden" name="siswa_id" :value="siswaId()">
        <input type="hidden" name="siswa_label" :value="namaSiswa()">

        <div class="kartu-kepala">
            <h2 class="kartu-judul">Catat siswa terlambat</h2>
            <p class="kartu-ket"><?= esc($hariTanggal) ?></p>
            <?php if (! $hariIni): ?>
                <p class="mt-2"><span class="lencana lencana-kuning">Mencatat untuk tanggal yang sudah lewat</span></p>
            <?php endif; ?>
        </div>

        <div class="kartu-isi grid items-start gap-4 lg:grid-cols-2 2xl:grid-cols-1">
            <div>
                <label class="lbl" for="tl-guru">Guru piket <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="tl-guru" name="guru_id" class="inp" required><?= $opsiGuru($guruPilih) ?></select>
                <?php if ($idPiket === []): ?>
                    <p class="hint mt-1">Jadwal Piket hari <?= esc($namaHari) ?> belum diisi — pilih dari daftar guru.</p>
                <?php endif; ?>
            </div>

            <div>
                <label class="lbl" for="tl-cari">Siswa yang terlambat <span class="text-red-600 dark:text-red-400">*</span></label>
                <div class="relative" x-show="! siswa">
                    <input type="search" id="tl-cari" class="inp" placeholder="Ketik nama atau NIS (min. 2 huruf)" autocomplete="off"
                           role="combobox" aria-autocomplete="list" aria-controls="tl-hasil" :aria-expanded="terbuka"
                           x-model="q" @input.debounce.300ms="cari()" @keydown.arrow-down.prevent="pindahHasil(1)" @keydown.arrow-up.prevent="pindahHasil(-1)"
                           @keydown.enter.prevent="pilihAktif()" @keydown.escape="tutupHasil()">
                    <ul id="tl-hasil" class="dropdown-menu right-0 left-0 mt-1 max-h-72 w-auto overflow-y-auto" role="listbox" aria-label="Hasil pencarian siswa" x-show="terbuka" x-cloak>
                        <template x-for="(s, i) in hasil" :key="s.id">
                            <li role="option" :aria-selected="i === aktif" :aria-disabled="sudah(s)">
                                <button type="button" class="dropdown-item flex-col items-start gap-0.5" :class="kelasHasil(i, s)" :disabled="sudah(s)" @click="pilih(s)">
                                    <span class="font-medium" x-text="s.nama"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="ketHasil(s)"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="hint mt-1" x-show="pesanCari !== ''" x-text="pesanCari" x-cloak aria-live="polite"></p>
                </div>
                <div class="flex items-center justify-between gap-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-800 dark:bg-brand-900/30" x-show="siswa" x-cloak>
                    <div class="min-w-0">
                        <p class="sel-judul" x-text="namaSiswa()"></p>
                        <p class="sel-ket" x-text="ketSiswa()"></p>
                    </div>
                    <button type="button" class="btn btn-netral btn-sm shrink-0" @click="gantiSiswa()"><?= ikon('close', 'h-4 w-4') ?>Ganti</button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)]">
                <div>
                    <label class="lbl" for="tl-jam">Jam datang</label>
                    <input type="time" id="tl-jam" name="jam" class="inp" x-ref="jam" @input="tandaiJam()"
                           value="<?= esc((string) (old('jam') ?? ($hariIni ? date('H:i') : '')), 'attr') ?>">
                </div>
                <div>
                    <label class="lbl" for="tl-sanksi">Sanksi</label>
                    <input type="text" id="tl-sanksi" name="sanksi" class="inp" maxlength="<?= Buku::MAKS_SANKSI ?>" list="tl-saran-sanksi"
                           placeholder="Bebas, mis. membersihkan halaman" value="<?= esc((string) old('sanksi'), 'attr') ?>">
                </div>
            </div>

            <div>
                <label class="lbl" for="tl-ket">Keterangan</label>
                <input type="text" id="tl-ket" name="keterangan" class="inp" maxlength="<?= Buku::MAKS_KETERANGAN ?>"
                       placeholder="Opsional, mis. alasan terlambat" value="<?= esc((string) old('keterangan'), 'attr') ?>">
            </div>
        </div>

        <div class="kartu-kaki flex justify-end">
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan catatan</button>
        </div>
    </form>

    <!-- ===== Daftar terlambat tanggal itu ===== -->
    <div class="kartu min-w-0">
        <div class="kartu-kepala flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="kartu-judul">Daftar terlambat</h2>
                <p class="kartu-ket"><?= esc($hariTanggal) ?> · <?= count($rows) ?> siswa</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <a href="<?= $base ?>/excel?tanggal=<?= $tanggal ?>" class="btn btn-netral btn-sm" title="Buku piket hari ini, format sekolah (lembar 50 baris)"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel</a>
                    <?php if ($bolehRekap): ?>
                        <a href="<?= $base ?>/rekap" class="btn btn-teks btn-sm"><?= ikon('chart', 'h-4 w-4') ?>Rekap &amp; unduh per rentang</a>
                    <?php endif; ?>
                    <?php if ($bolehPesan): ?>
                        <button type="button" class="btn btn-teks btn-sm" data-buka-dialog="tl-pesan-wa"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?>Pesan WA ke ortu</button>
                    <?php endif; ?>
                </div>
            </div>
            <form method="get" action="<?= $base ?>" class="flex flex-wrap items-center gap-2">
                <a href="<?= $base ?>?tanggal=<?= $kemarin ?>" class="btn btn-netral btn-sm" title="Hari sebelumnya" aria-label="Hari sebelumnya"><?= ikon('chevron-left', 'h-4 w-4') ?></a>
                <input type="date" name="tanggal" value="<?= esc($tanggal, 'attr') ?>" max="<?= date('Y-m-d') ?>" class="inp w-auto py-1.5 text-sm" aria-label="Pilih tanggal">
                <button type="submit" class="btn btn-netral btn-sm">Tampilkan</button>
                <?php if ($besok <= date('Y-m-d')): ?>
                    <a href="<?= $besok === date('Y-m-d') ? $base : $base . '?tanggal=' . $besok ?>" class="btn btn-netral btn-sm" title="Hari berikutnya" aria-label="Hari berikutnya"><?= ikon('chevron-right', 'h-4 w-4') ?></a>
                <?php endif; ?>
                <?php if (! $hariIni): ?>
                    <a href="<?= $base ?>" class="btn btn-teks btn-sm">Hari ini</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($rows === []): ?>
            <?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada siswa terlambat', 'teks' => 'Catatan untuk ' . $hariTanggal . ' akan tampil di sini.']) ?>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col" class="w-12">No</th>
                            <th scope="col">Jam</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Sanksi</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col">Guru piket</th>
                            <th scope="col" class="kolom-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): $k = $r['kabar']; $sudah = $r['dikabari_at'] !== null; ?>
                            <tr data-kabar-baris>
                                <td><?= $i + 1 ?></td>
                                <td class="whitespace-nowrap"><?= $r['jam'] !== null ? esc(substr((string) $r['jam'], 0, 5)) : '—' ?></td>
                                <td>
                                    <p class="sel-judul"><?= esc($r['nama_siswa']) ?></p>
                                    <?php if (! empty($r['nis'])): ?><p class="sel-ket">NIS <?= esc((string) $r['nis']) ?></p><?php endif; ?>
                                    <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400" data-kabar-status<?= $sudah ? '' : ' hidden' ?>>
                                        <?= ikon('check', 'h-3.5 w-3.5 shrink-0') ?><span data-kabar-status-teks><?= $sudah ? 'Sudah dikabari ' . esc(date('d-m H:i', strtotime((string) $r['dikabari_at']))) . ($r['dikabari_oleh'] ? ' oleh ' . esc((string) $r['dikabari_oleh']) : '') : '' ?></span>
                                    </p>
                                    <?php if ($bolehKabari && $k['url'] === null): ?><p class="sel-ket"><?= esc((string) $k['alasan_tidak']) ?></p><?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap"><?= esc((string) ($r['nama_kelas'] ?? '—')) ?></td>
                                <td><?= esc((string) ($r['sanksi'] ?? '')) ?: '—' ?></td>
                                <td><?= esc((string) ($r['keterangan'] ?? '')) ?: '—' ?></td>
                                <td>
                                    <?= esc((string) ($r['guru_piket'] ?? '—')) ?>
                                    <?php if (! empty($r['dicatat_oleh'])): ?><p class="sel-ket">dicatat akun <?= esc((string) $r['dicatat_oleh']) ?></p><?php endif; ?>
                                </td>
                                <td class="kolom-aksi">
                                    <?php
                                    $tombolWa = '';
                                    if ($bolehKabari && $k['url'] !== null) {
                                        $tombolWa = '<a href="' . esc($k['url'], 'attr') . '" target="_blank" rel="noopener noreferrer" class="btn btn-sukses btn-sm"'
                                            . ' data-kabar-tandai="' . esc($base . '/' . (int) $r['id'] . '/kabari', 'attr') . '" title="' . esc('Kirim ke ' . $k['hp'] . ' (HP ' . $k['sumber'] . ')', 'attr') . '">'
                                            . ikon('whatsapp', 'h-4 w-4', 'solid') . '<span data-kabar-label>' . ($sudah ? 'Kirim ulang' : 'Kabari ortu') . '</span></a>';
                                    } elseif ($bolehKabari && $r['siswa_id'] !== null) {
                                        $tombolWa = '<button type="button" class="btn btn-netral btn-sm" data-buka-dialog="tl-isi-hp" data-isi-hp="' . esc($base . '/' . (int) $r['id'] . '/hp', 'attr') . '"'
                                            . ' data-isi-nama="' . esc($r['nama_siswa'] . ' (' . ($r['nama_kelas'] ?? 'tanpa kelas') . ')', 'attr') . '">' . ikon('phone', 'h-4 w-4') . 'Isi no. ortu</button>';
                                    }
                                    ?>
                                    <?= ui_crud_aksi([
                                        'tambahanHtml' => $tombolWa,
                                        'row'        => ['id' => (int) $r['id'], 'nama_siswa' => $r['nama_siswa'] . ' (' . ($r['nama_kelas'] ?? 'tanpa kelas') . ')',
                                            'jam' => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : '', 'guru_id' => (string) ($r['guru_id'] ?? ''),
                                            'sanksi' => (string) ($r['sanksi'] ?? ''), 'keterangan' => (string) ($r['keterangan'] ?? '')],
                                        'hapusUrl'   => $base . '/delete/' . (int) $r['id'],
                                        'konfirmasi' => 'Hapus catatan terlambat ' . $r['nama_siswa'] . ' pada ' . $hariTanggal . '?',
                                    ]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Dialog ubah (siswa & tanggal tetap) ===== -->
    <?= ui_crud_form_buka(['benda' => 'Catatan Keterlambatan']) ?>
        <div>
            <p class="lbl">Siswa</p>
            <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="form.nama_siswa"></p>
            <p class="hint mt-1">Salah siswa? Hapus catatan ini lalu catat ulang.</p>
        </div>
        <div>
            <label class="lbl" for="tl-ubah-guru">Guru piket <span class="text-red-600 dark:text-red-400">*</span></label>
            <select id="tl-ubah-guru" name="guru_id" class="inp" required x-model="form.guru_id"><?= $opsiGuru(0) ?></select>
        </div>
        <div class="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)]">
            <div>
                <label class="lbl" for="tl-ubah-jam">Jam datang</label>
                <input type="time" id="tl-ubah-jam" name="jam" class="inp" x-model="form.jam">
            </div>
            <div>
                <label class="lbl" for="tl-ubah-sanksi">Sanksi</label>
                <input type="text" id="tl-ubah-sanksi" name="sanksi" class="inp" maxlength="<?= Buku::MAKS_SANKSI ?>" list="tl-saran-sanksi" x-model="form.sanksi">
            </div>
        </div>
        <div>
            <label class="lbl" for="tl-ubah-ket">Keterangan</label>
            <input type="text" id="tl-ubah-ket" name="keterangan" class="inp" maxlength="<?= Buku::MAKS_KETERANGAN ?>" x-model="form.keterangan">
        </div>
    <?= ui_crud_form_tutup() ?>

    <?php if ($bolehKabari): ?>
        <!-- ===== Dialog isi nomor HP orang tua (form action diisi keterlambatan.js dari tombol "Isi no. ortu") ===== -->
        <?= ui_dialog_buka(['nama' => 'tl-isi-hp', 'judul' => 'Isi nomor HP orang tua', 'ukuran' => 'sm']) ?>
        <form method="post" action="<?= $base ?>" id="tl-form-hp">
            <?= csrf_field() ?>
            <div class="dialog-isi space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Siswa: <b class="text-gray-900 dark:text-white" id="tl-hp-nama"></b></p>
                <div>
                    <label class="lbl" for="tl-hp">Nomor WhatsApp orang tua / wali</label>
                    <input type="tel" id="tl-hp" name="hp" class="inp" inputmode="tel" maxlength="30" required placeholder="0812 3456 7890" autocomplete="off">
                </div>
                <p class="hint">Disimpan ke <b>Data Siswa</b> (kolom Telepon orang tua) supaya lain kali tidak perlu diisi lagi. Nomor yang sudah ada di Data Siswa tidak pernah ditimpa.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan nomor</button>
            </div>
        </form>
        <?= ui_dialog_tutup() ?>
    <?php endif; ?>

    <?php if ($bolehPesan): ?>
        <!-- ===== Dialog templat pesan WA (khusus Admin) ===== -->
        <?= ui_dialog_buka(['nama' => 'tl-pesan-wa', 'judul' => 'Pesan WhatsApp ke orang tua', 'ukuran' => 'lg']) ?>
        <form method="post" action="<?= $base ?>/pesan">
            <?= csrf_field() ?>
            <input type="hidden" name="tanggal" value="<?= esc($tanggal, 'attr') ?>">
            <div class="dialog-isi space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Dikirim <b>manual</b> oleh guru piket lewat tombol hijau <b>Kabari ortu</b>: WhatsApp terbuka dengan pesan ini, tinggal menekan Kirim. Penanda yang bisa dipakai:
                    <?php foreach (\App\Libraries\KeterlambatanWa::TOKEN as $tk): ?><code class="me-1"><?= esc($tk) ?></code><?php endforeach; ?>
                    — <code>{waktu}</code> = hari, tanggal, dan jam datang.</p>
                <label class="lbl" for="tl-pesan">Isi pesan</label>
                <textarea id="tl-pesan" name="wa_pesan" rows="5" maxlength="<?= \App\Libraries\PklWa::MAKS_PESAN ?>" class="inp"><?= esc($templatWa) ?></textarea>
                <p class="hint">Kosongkan lalu simpan untuk kembali ke pesan bawaan.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pesan</button>
            </div>
        </form>
        <?= ui_dialog_tutup() ?>
    <?php endif; ?>

    <datalist id="tl-saran-sanksi">
        <?php foreach ($saranSanksi as $s): ?>
            <option value="<?= esc($s, 'attr') ?>"></option>
        <?php endforeach; ?>
    </datalist>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/keterlambatan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/keterlambatan.js') ?>" <?= csp_script_nonce() ?>></script>
<?php if ($bolehKabari): ?>
<script defer src="<?= base_url('assets/js/admin/pkl-kabar.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl-kabar.js') ?>" <?= csp_script_nonce() ?>></script>
<?php endif; ?>
<?= $this->endSection() ?>
