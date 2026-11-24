<?php
/**
 * Tab "Jadwal Pengawas" — pengawas per RUANG per sesi (F3 cetak jadwal, permintaan user 2026-11-22; bentuk hasil akhir mengikuti
 * berkas contoh sekolah "jadwal pengawas.xlsx"). Dua kartu:
 *  1. Guru pengawas: centang guru yang ikut diatur (kode = Kode Guru di Master Guru; tanpa kode tak bisa dipilih). Belum ada daftar →
 *     centang disalin dari periode lain yang terakhir disimpan (pgSaran) dan baru berlaku setelah "Simpan daftar guru".
 *  2. Grid per shift: baris = hari × jam ke- (istirahat ditampilkan), kolom = RUANG 1..N, isi = KODE guru. Dua mode (tombol Ubah &
 *     Hapus, klien 2026-11-24):
 *       - LIHAT (bawaan): angka; klik angka / ikon pensil = dialog ubah satu sesi (pilih guru per ruang, JadwalPengawas::simpanGrid
 *         dengan $hanyaKunci); tong sampah = hapus satu sesi; juga hapus per hari & per shift (JadwalPengawas::kosongkan).
 *       - UBAH SEMUA (?ubah=1, atau isian ditolak): kotak isian seluruh grid (satu <datalist> dipakai semua kotak supaya ringan);
 *         Simpan = ganti seluruh isi shift itu. Tombol hapus/Generate/cetak disembunyikan supaya ketikan tidak hilang.
 *     Tombol Generate otomatis (F4) di atas grid → JadwalPengawas::generate(); di bawah grid ringkasan beban per guru.
 * @var array<int, int> $pgBeban
 * Siap CSP: tanpa skrip sebaris; daftar centang = komponen kit daftarCentang.
 *
 * @var array  $periode
 * @var string $base
 * @var string $tp
 * @var string $qtp
 * @var list<string> $pgShifts
 * @var string $pgShift
 * @var list<array> $pgSesi
 * @var int    $pgRuang
 * @var array<string, array<int, string>> $pgIsi
 * @var int    $pgTerisi
 * @var int    $pgTotalSel
 * @var list<array{id: int, nama: string, kode: string, ganda: bool}> $pgGuru
 * @var list<int> $pgTerpilih
 * @var array{label: string, ids: list<int>}|null $pgSaran
 * @var array<string, array{id: int, nama: string}> $pgKode
 * @var bool $pgUbah mode "Ubah semua" (?ubah=1)
 */
$centang   =array_flip($pgTerpilih !== [] ? $pgTerpilih : ($pgSaran['ids'] ?? []));
$berkode   = array_values(array_filter($pgGuru, static fn (array $g): bool => $g['kode'] !== ''));
$tanpaKode = count($pgGuru) - count($berkode);
$pgMasalah = $pgMasalah ?? [];
$nPerluAngka = count(array_filter($pgGuru, static fn (array $g): bool => ($g['masalah'] ?? '') !== ''));
$lamaGanti = old('pengganti'); // angka pengganti sebelum ditolak
$lama      = old('sel'); // isian sebelum ditolak (dikembalikan bersama pesan galat)
$nilaiSel  = static function (string $kunci, int $ruang) use ($lama, $pgIsi): string {
    if (is_array($lama)) {
        return (string) ($lama[$kunci][$ruang] ?? '');
    }

    return (string) ($pgIsi[$kunci][$ruang] ?? '');
};
$ubahSemua = is_array($lama) || ! empty($pgUbah); // ditolak saat simpan grid → tetap di mode ubah dengan isian terakhir
$urlShift  = static fn (string $s): string => $base . '/jadwal-pengawas' . $qtp . '&shift=' . $s;
// Isian ubah satu sesi yang ditolak → dialog terbuka lagi dengan pilihan terakhir.
$lamaSesi  = is_string(old('kunci')) && is_array(old('sesi')) ? ['kunci' => (string) old('kunci'), 'isi' => old('sesi')] : null;
$isiHari   = []; // tanggal → jumlah sel terisi (tombol "Hapus hari ini" hanya bila ada isinya)
foreach ($pgIsi as $kunciIsi => $perRuang) {
    $tglIsi           = explode('|', (string) $kunciIsi)[0];
    $isiHari[$tglIsi] = ($isiHari[$tglIsi] ?? 0) + count($perRuang);
}
// Tombol hapus: semuanya mengirim formulir "form-kosongkan" (cakupan = sesi|… / hari|… / shift).
$tombolHapus = static fn (string $cakupan, string $isi, string $kelas, string $label, string $tanya, string $judul): string => '<button type="submit" form="form-kosongkan" name="cakupan" value="' . esc($cakupan, 'attr')
    . '" class="btn ' . $kelas . '" title="' . esc($label, 'attr') . '" aria-label="' . esc($label, 'attr') . '" data-confirm="' . esc($tanya, 'attr') . '" data-confirm-judul="' . esc($judul, 'attr')
    . '" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">' . $isi . '</button>';
$hariNama  = [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => "JUM'AT", 6 => 'SABTU', 7 => 'MINGGU'];
?>

<div class="space-y-4">
    <?= ui_peringatan('info', '', ['html' => '<b>1.</b> Centang guru → Simpan daftar guru &nbsp;·&nbsp; <b>2.</b> Generate otomatis &nbsp;·&nbsp; <b>3.</b> Periksa, klik angka untuk mengubah &nbsp;·&nbsp; <b>4.</b> Cetak. Hari, jam, dan istirahat mengikuti tab <b>Jadwal Ujian</b>.']) ?>

    <!-- ===== 1. Guru pengawas ===== -->
    <section id="guru-pengawas" class="kartu" aria-labelledby="judul-guru-pengawas">
        <div class="kartu-kepala">
            <div>
                <h2 id="judul-guru-pengawas" class="kartu-judul">1. Guru pengawas</h2>
                <p class="kartu-ket"><?= count($pgTerpilih) ?> guru dipilih untuk ujian ini. Kode pengawas = <b>angka</b> di depan Kode Guru, hurufnya dibuang (13a → 13, 36(1) → 36). Kode Guru untuk KBM tidak berubah.</p>
            </div>
        </div>
        <form method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/guru" x-data="daftarCentang" data-satuan="guru dipilih" @change="hitungCentang()">
            <?= csrf_field() ?>
            <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
            <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
            <div class="kartu-isi space-y-3">
                <?php if ($pgTerpilih === [] && $pgSaran !== null): ?>
                    <?= ui_peringatan('awas', '', ['html' => 'Centang di bawah <b>disalin dari ' . esc($pgSaran['label']) . '</b> (' . count($pgSaran['ids']) . ' guru) dan <b>belum tersimpan</b>. Periksa, lalu tekan <b>Simpan daftar guru</b>.']) ?>
                <?php endif; ?>
                <?php if ($tanpaKode > 0): ?>
                    <p class="hint"><?= $tanpaKode ?> guru belum punya Kode Guru sehingga tidak bisa dipilih — isi kodenya di <a class="font-medium text-brand-700 underline dark:text-brand-400" href="<?= site_url('admin/master/guru') ?>">Master Guru</a>.</p>
                <?php endif; ?>
                <?php if ($nPerluAngka > 0): ?>
                    <?= ui_peringatan('awas', '', ['html' => '<b>' . $nPerluAngka . ' guru perlu angka pengganti</b> — Kode Guru-nya tidak berangka, atau angkanya sama dengan guru lain (orang berbeda, mis. 15 &amp; 15A). Isi angka di <b>kotak samping namanya</b> (angka yang belum dipakai guru lain), lalu Simpan. Angka tersimpan dan dipakai lagi di ujian berikutnya. Bila hanya satu dari dua guru berangka sama yang ikut mengawasi, cukup centang dia saja.']) ?>
                <?php endif; ?>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <label class="flex cursor-pointer items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-700 dark:text-white">
                        <input type="checkbox" class="cek" @change="pilihSemuaCentang($event)">
                        Pilih semua guru yang punya kode (<?= count($berkode) ?>)
                        <span class="ms-auto text-xs font-normal text-gray-500 dark:text-gray-300" x-text="teksCentang()"></span>
                    </label>
                    <div class="panel-gulir grid max-h-96 grid-cols-1 gap-x-4 p-2 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($pgGuru as $g):
                            $adaKode    = $g['kode'] !== '';
                            $masalahG   = $g['masalah'] ?? '';
                            // Kotak angka pengganti: hanya guru yang kodenya tak berangka / angkanya bentrok, atau sudah punya pengganti.
                            $perluKotak = $adaKode && ($masalahG !== '' || ($g['pengganti'] ?? '') !== '');
                            $nilaiGanti = is_array($lamaGanti) && array_key_exists($g['id'], $lamaGanti) ? (string) $lamaGanti[$g['id']] : (string) ($g['pengganti'] ?? '');
                            $judulKbm   = $adaKode && $g['kode'] !== ($g['nomor'] ?? '') ? 'Kode Guru KBM: ' . $g['kode'] : '';
                        ?>
                            <div class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm <?= $adaKode ? 'hover:bg-gray-50 dark:hover:bg-gray-700/60' : 'opacity-60' ?>">
                                <label class="flex min-w-0 flex-1 items-center gap-2 <?= $adaKode ? 'cursor-pointer' : '' ?>">
                                    <?php if ($adaKode): ?>
                                        <input type="checkbox" name="guru_ids[]" value="<?= (int) $g['id'] ?>" data-centang class="cek"<?= isset($centang[$g['id']]) ? ' checked' : '' ?>>
                                    <?php else: ?>
                                        <input type="checkbox" class="cek" disabled aria-label="Tanpa kode">
                                    <?php endif; ?>
                                    <span class="w-10 shrink-0 font-mono font-semibold text-brand-700 dark:text-brand-400"<?= $judulKbm !== '' ? ' title="' . esc($judulKbm, 'attr') . '"' : '' ?>><?= ! $adaKode ? '—' : (($g['nomor'] ?? '') !== '' ? esc($g['nomor']) : '?') ?></span>
                                    <span class="min-w-0 flex-1 truncate text-gray-900 dark:text-white" title="<?= esc($g['nama'] . ($judulKbm !== '' ? ' — ' . $judulKbm : ''), 'attr') ?>"><?= esc($g['nama']) ?><?php if ($judulKbm !== ''): ?> <span class="text-xs text-gray-500 dark:text-gray-400">(<?= esc($g['kode']) ?>)</span><?php endif; ?></span>
                                </label>
                                <?php if ($perluKotak): ?>
                                    <input type="text" name="pengganti[<?= (int) $g['id'] ?>]" value="<?= esc($nilaiGanti, 'attr') ?>" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="angka" class="inp-sel"
                                           aria-label="Angka pengawas untuk <?= esc($g['nama'], 'attr') ?>" title="Angka pengawas pengganti (kosong = pakai angka dari Kode Guru)">
                                <?php endif; ?>
                                <?php if ($masalahG === 'tanpa-angka'): ?>
                                    <span class="lencana lencana-kuning" title="Kode Guru tidak diawali angka — isi angka di kotak">isi angka</span>
                                <?php elseif ($masalahG === 'bentrok'): ?>
                                    <span class="lencana lencana-kuning" title="Angka <?= esc($g['nomor'], 'attr') ?> juga dipakai guru lain (orang berbeda)">angka sama</span>
                                <?php elseif ($g['ganda']): ?>
                                    <span class="lencana lencana-kuning" title="Kode ini dipakai lebih dari satu guru di Master Guru">kode ganda</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="kartu-kaki flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan daftar guru</button>
                <p class="text-xs text-gray-500 dark:text-gray-400">Guru yang dikeluarkan dari daftar otomatis dikosongkan dari jadwal pengawas. Guru yang tercatat dua kali (nama sama, kode beda) cukup dicentang salah satu.</p>
            </div>
        </form>
    </section>

    <!-- ===== 2. Grid pengawas per ruang ===== -->
    <section id="grid-pengawas" class="kartu" aria-labelledby="judul-grid-pengawas">
        <div class="kartu-kepala flex-wrap gap-3">
            <div>
                <h2 id="judul-grid-pengawas" class="kartu-judul">2. Pengawas per ruang</h2>
                <p class="kartu-ket">Terisi <b><?= (int) $pgTerisi ?></b> dari <?= (int) $pgTotalSel ?> sel (<?= (int) $pgRuang ?> ruang). <?= $ubahSemua ? 'Ketik <b>angka pengawas</b> di tiap ruang; kosong = belum ada pengawas.' : 'Angka = kode pengawas; kuning = belum ada pengawas.' ?></p>
            </div>
            <?php // 2026-11-23 (klien): jumlah ruang bisa ditambah/dikurangi langsung di sini (sama dengan isian di tab Periode). ?>
            <?php if (! $ubahSemua): ?>
            <form method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/ruang" class="flex items-center gap-2"
                  data-confirm="Ubah jumlah ruang <?= esc($pgShift, 'attr') ?>? Bila dikurangi, pengawas di ruang yang dihapus ikut dikosongkan; bila ditambah, ruang baru masih kosong (Generate ulang atau isi manual)." data-confirm-judul="Ubah jumlah ruang?" data-confirm-ya="Ya, ubah">
                <?= csrf_field() ?>
                <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
                <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
                <label class="whitespace-nowrap text-sm text-gray-700 dark:text-gray-300" for="pg-jumlah-ruang">Jumlah ruang <?= esc($pgShift) ?></label>
                <input type="number" id="pg-jumlah-ruang" name="jumlah" min="1" max="200" required value="<?= (int) $pgRuang ?>" class="inp w-20"
                       title="Bawaan = jumlah kelas <?= esc($pgShift, 'attr') ?> (<?= (int) ($pgKelasShift ?? 0) ?>)">
                <button type="submit" class="btn btn-netral">Ubah</button>
            </form>
            <?php endif; ?>
            <?php if (! $ubahSemua && ($pgTerisi > 0 || array_sum($pgBeban ?? []) > 0)): ?>
                <?php // F5: cetak persis berkas contoh sekolah (Excel 3 lembar, PDF jadwal Pagi+Siang, PDF daftar kode). ?>
                <div class="ms-auto">
                    <?= ui_menu_lain([
                        ['label' => 'Jadwal Pengawas (Excel)', 'ikon' => 'file-csv', 'href' => $base . '/cetak-pengawas/excel' . $qtp],
                        ['label' => 'Jadwal Pengawas (PDF)', 'ikon' => 'file-pdf', 'href' => $base . '/cetak-pengawas/pdf' . $qtp, 'atr' => 'target="_blank" rel="noopener"'],
                        ['label' => 'Daftar Kode Pengawas (PDF)', 'ikon' => 'file-pdf', 'href' => $base . '/cetak-pengawas/kode-pdf' . $qtp, 'atr' => 'target="_blank" rel="noopener"'],
                    ], ['label' => 'Cetak', 'kecil' => false]) ?>
                </div>
            <?php endif; ?>
            <?php if (count($pgShifts) > 1): ?>
                <div class="flex gap-2" role="group" aria-label="Pilih shift">
                    <?php foreach ($pgShifts as $s): ?>
                        <a href="<?= esc($base . '/jadwal-pengawas' . $qtp . '&shift=' . $s, 'attr') ?>#grid-pengawas" class="tombol-saring"<?= $s === $pgShift ? ' aria-current="true"' : '' ?>><?= esc(ucfirst($s)) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pgSesi === []): ?>
            <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal ujian shift ' . $pgShift, 'teks' => 'Isi Jadwal Ujian dulu; sesi pengawas mengikuti hari & jam jadwal ujian.',
                'aksi' => '<a href="' . esc($base . '/jadwal' . $qtp, 'attr') . '" class="btn btn-utama">' . ikon('calendar-week', 'h-4 w-4') . 'Buka Jadwal Ujian</a>']) ?>
        <?php elseif ($pgKode === []): ?>
            <?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada guru pengawas', 'teks' => 'Centang dan simpan daftar guru pengawas di kartu nomor 1 dulu.']) ?>
        <?php else: ?>
            <?php if ($pgMasalah !== []): ?>
                <div class="px-4 pt-4 md:px-6">
                    <?= ui_peringatan('galat', '', ['html' => '<b>Angka pengawas belum beres</b> — Generate, simpan, dan cetak ditahan sampai dibetulkan di kartu nomor 1:<br>• ' . implode('<br>• ', array_map('esc', $pgMasalah))]) ?>
                </div>
            <?php endif; ?>
            <?php if (! $ubahSemua): ?>
            <!-- F4: Generate otomatis (pola contoh sekolah; aturan di JadwalPengawas::generate) -->
            <form method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/generate" class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 md:px-6 dark:border-gray-700 dark:bg-gray-800"
                  data-confirm="Generate akan MENGGANTI seluruh isi jadwal pengawas shift yang dipilih dengan susunan baru (shift lain tidak berubah). Lanjutkan?" data-confirm-judul="Generate jadwal pengawas?" data-confirm-ya="Ya, generate">
                <?= csrf_field() ?>
                <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
                <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
                <label class="sr-only" for="pg-cakupan">Shift yang di-generate</label>
                <select id="pg-cakupan" name="cakupan" class="inp w-auto">
                    <?php if (count($pgShifts) > 1): ?><option value="semua">Semua shift (disarankan)</option><?php endif; ?>
                    <?php foreach ($pgShifts as $s): ?>
                        <option value="<?= esc($s, 'attr') ?>"><?= esc(ucfirst($s)) ?> saja</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sukses"><?= ikon('play', 'h-4 w-4') ?>Generate otomatis</button>
                <p class="hint basis-full sm:basis-auto sm:flex-1">Mengisi semua ruang dari daftar guru secara <b>merata</b>; tak ada guru di dua tempat sekaligus; sesi beruntun dijaga orang yang sama; sesudah istirahat pengawas pindah ruang. Hasilnya tetap bisa diubah manual di bawah.</p>
            </form>
            <?php endif; ?>

            <?php if (! $ubahSemua): ?>
            <!-- Mode lihat: ubah & hapus per sesi / hari / shift (klien 2026-11-24) -->
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 md:px-6">
                <p class="min-w-0 flex-1 basis-64 text-sm text-gray-500 dark:text-gray-400">Tekan <b>angka</b> atau ikon pensil untuk mengubah pengawas satu sesi, tong sampah untuk menghapusnya. Arahkan kursor ke angka untuk melihat nama guru.</p>
                <a href="<?= esc($urlShift($pgShift) . '&ubah=1', 'attr') ?>#grid-pengawas" class="btn btn-utama"><?= ikon('edit', 'h-4 w-4') ?>Ubah semua</a>
                <?php if ($pgTerisi > 0): ?>
                    <?= $tombolHapus('shift', ikon('trash-bin', 'h-4 w-4') . 'Hapus semua ' . esc($pgShift), 'btn-bahaya', 'Hapus semua pengawas ' . $pgShift,
                        'Semua pengawas shift ' . $pgShift . ' (' . (int) $pgTerisi . ' sel) dihapus. Jadwal ujian, daftar guru pengawas, dan shift lain tidak berubah. Tidak bisa dibatalkan.', 'Hapus semua pengawas ' . $pgShift . '?') ?>
                <?php endif; ?>
            </div>
            <form id="form-kosongkan" method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/kosongkan">
                <?= csrf_field() ?>
                <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
                <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
            </form>
            <div x-data="pengawasGrid" data-ruang="<?= (int) $pgRuang ?>"<?= $lamaSesi !== null ? ' data-lama="' . esc((string) json_encode($lamaSesi), 'attr') . '"' : '' ?>>
                <div class="tabel-bungkus border-t border-gray-200 dark:border-gray-700">
                    <table class="tabel text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="whitespace-nowrap">Hari / tgl</th>
                                <th scope="col" class="whitespace-nowrap !px-2 text-center">Jam ke</th>
                                <th scope="col" class="whitespace-nowrap !px-2">Waktu</th>
                                <th scope="col" class="!px-2 text-center">Aksi</th>
                                <?php for ($r = 1; $r <= $pgRuang; $r++): ?>
                                    <th scope="col" class="!px-1 text-center">R<?= $r ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pgSesi as $h):
                                $jml   = count($h['baris']);
                                $nHari = $hariNama[(int) date('N', strtotime($h['tanggal']))] ?? '';
                                $tglH  = \App\Libraries\JadwalUjianCetak::tanggalTeks($h['tanggal']);
                            ?>
                                <?php foreach ($h['baris'] as $j => $b):
                                    $adaSesi = $b['jenis'] === 'sesi';
                                    $isiSesi = $adaSesi ? ($pgIsi[$b['kunci']] ?? []) : [];
                                    $judulS  = $nHari . ' ' . $tglH . ' · jam ke-' . (int) $b['jamKe'] . ' · ' . $b['waktu'];
                                ?>
                                    <tr class="<?= $j === 0 ? 'border-t-2 border-gray-300 dark:border-gray-600' : '' ?>"<?= $adaSesi ? ' data-sesi="' . esc($b['kunci'], 'attr') . '" data-judul="' . esc($judulS, 'attr') . '" data-isi="' . esc((string) json_encode((object) $isiSesi), 'attr') . '"' : '' ?>>
                                        <?php if ($j === 0): ?>
                                            <td rowspan="<?= $jml ?>" class="whitespace-nowrap align-top font-semibold text-gray-900 dark:text-white">
                                                <?= esc($nHari) ?><br>
                                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($tglH) ?></span>
                                                <?php if (($isiHari[$h['tanggal']] ?? 0) > 0): ?>
                                                    <div class="mt-1"><?= $tombolHapus('hari|' . $h['tanggal'], ikon('trash-bin', 'h-3.5 w-3.5') . 'Hapus hari ini', 'btn-teks btn-xs -ms-3 font-normal', 'Hapus pengawas ' . $pgShift . ' ' . $nHari . ' ' . $tglH,
                                                        'Semua pengawas ' . $pgShift . ' hari ' . $nHari . ' ' . $tglH . ' (' . (int) $isiHari[$h['tanggal']] . ' sel) dihapus. Jadwal ujian tidak berubah.', 'Hapus pengawas hari ini?') ?></div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="!px-2 text-center"><?= $b['jamKe'] !== null ? (int) $b['jamKe'] : '' ?></td>
                                        <td class="whitespace-nowrap !px-2"><?= esc($b['waktu']) ?></td>
                                        <?php if (! $adaSesi): ?>
                                            <td colspan="<?= (int) $pgRuang + 1 ?>" class="bg-gray-50 text-center text-xs text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">Istirahat</td>
                                        <?php else: ?>
                                            <td class="!px-2">
                                                <div class="flex gap-1.5">
                                                    <button type="button" class="btn btn-utama btn-xs btn-ikon" data-ubah-sesi title="Ubah pengawas sesi ini" aria-label="Ubah pengawas <?= esc($judulS, 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?></button>
                                                    <?php if ($isiSesi !== []): ?>
                                                        <?= $tombolHapus('sesi|' . $b['kunci'], ikon('trash-bin', 'h-4 w-4'), 'btn-bahaya btn-xs btn-ikon', 'Hapus pengawas ' . $judulS,
                                                            'Pengawas ' . $pgShift . ' ' . $judulS . ' (' . count($isiSesi) . ' ruang) dihapus. Jadwal ujian tidak berubah.', 'Hapus pengawas sesi ini?') ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <?php for ($r = 1; $r <= $pgRuang; $r++):
                                                $kodeS = (string) ($isiSesi[$r] ?? '');
                                                $namaS = $kodeS !== '' ? (string) ($pgKode[$kodeS]['nama'] ?? '') : '';
                                                $ketS  = 'Ruang ' . $r . ': ' . ($kodeS === '' ? 'belum ada pengawas' : $kodeS . ($namaS !== '' ? ' — ' . $namaS : ''));
                                            ?>
                                                <td class="!p-0.5"><button type="button" class="sel-lihat" data-ubah-sesi data-ruang="<?= $r ?>"<?= $kodeS === '' ? ' data-kosong' : '' ?> title="<?= esc($ketS, 'attr') ?>" aria-label="<?= esc('Ubah ' . $ketS . ', ' . $judulS, 'attr') ?>"><?= $kodeS === '' ? '–' : esc($kodeS) ?></button></td>
                                            <?php endfor; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Satu baris isian ruang (disalin pengawas.js per ruang) -->
                <template id="tpl-pengawas-ruang">
                    <div>
                        <label class="lbl"></label>
                        <select class="inp">
                            <option value="">— kosong —</option>
                            <?php foreach ($pgKode as $kode => $g): ?><option value="<?= esc((string) $kode, 'attr') ?>"><?= esc($kode . ' — ' . $g['nama']) ?></option><?php endforeach; ?>
                        </select>
                        <p class="err-msg" hidden></p>
                    </div>
                </template>
                <?= ui_dialog_buka(['nama' => 'ubah-pengawas', 'judul' => 'Ubah pengawas ' . $pgShift, 'ukuran' => 'lg']) ?>
                <form method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/sesi">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
                    <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
                    <input type="hidden" name="kunci" :value="kunci">
                    <div class="dialog-isi">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="judul"></p>
                        <div id="pg-daftar-ruang" class="grid gap-4 sm:grid-cols-2"></div>
                        <p class="hint">Hanya sesi ini yang berubah. Satu guru tidak boleh di dua ruang pada jam yang sama, dan tidak boleh bentrok dengan tugasnya di shift lain.</p>
                    </div>
                    <div class="dialog-kaki">
                        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
                    </div>
                </form>
                <?= ui_dialog_tutup() ?>
            </div>
            <script defer src="<?= base_url('assets/js/admin/ujian/pengawas.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/pengawas.js') ?>" <?= csp_script_nonce() ?>></script>
            <?php else: ?>
            <!-- Mode ubah semua: kotak isian seluruh grid -->
            <form method="post" action="<?= esc($base, 'attr') ?>/jadwal-pengawas/simpan">
                <?= csrf_field() ?>
                <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
                <input type="hidden" name="shift" value="<?= esc($pgShift, 'attr') ?>">
                <datalist id="dl-pengawas">
                    <?php foreach ($pgKode as $kode => $g): ?><option value="<?= esc((string) $kode, 'attr') ?>"><?= esc($g['nama']) ?></option><?php endforeach; ?>
                </datalist>
                <div class="tabel-bungkus">
                    <table class="tabel text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="whitespace-nowrap">Hari / tgl</th>
                                <th scope="col" class="whitespace-nowrap text-center">Jam ke</th>
                                <th scope="col" class="whitespace-nowrap">Waktu</th>
                                <?php for ($r = 1; $r <= $pgRuang; $r++): ?>
                                    <th scope="col" class="!px-1 text-center">R<?= $r ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pgSesi as $h): $jml = count($h['baris']); ?>
                                <?php foreach ($h['baris'] as $j => $b): ?>
                                    <tr class="<?= $j === 0 ? 'border-t-2 border-gray-300 dark:border-gray-600' : '' ?>">
                                        <?php if ($j === 0): ?>
                                            <td rowspan="<?= $jml ?>" class="whitespace-nowrap align-top font-semibold text-gray-900 dark:text-white">
                                                <?= esc($hariNama[(int) date('N', strtotime($h['tanggal']))] ?? '') ?><br>
                                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc(\App\Libraries\JadwalUjianCetak::tanggalTeks($h['tanggal'])) ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center"><?= $b['jamKe'] !== null ? (int) $b['jamKe'] : '' ?></td>
                                        <td class="whitespace-nowrap"><?= esc($b['waktu']) ?></td>
                                        <?php if ($b['jenis'] === 'istirahat'): ?>
                                            <td colspan="<?= (int) $pgRuang ?>" class="bg-gray-50 text-center text-xs text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">Istirahat</td>
                                        <?php else: ?>
                                            <?php for ($r = 1; $r <= $pgRuang; $r++): ?>
                                                <td class="!p-0.5">
                                                    <input type="text" name="sel[<?= esc($b['kunci'], 'attr') ?>][<?= $r ?>]" value="<?= esc($nilaiSel($b['kunci'], $r), 'attr') ?>"
                                                           list="dl-pengawas" maxlength="4" autocomplete="off" inputmode="numeric" class="inp-sel"
                                                           aria-label="Kode pengawas ruang <?= $r ?>, <?= esc(($hariNama[(int) date('N', strtotime($h['tanggal']))] ?? '') . ' jam ke-' . (int) $b['jamKe'], 'attr') ?>">
                                                </td>
                                            <?php endfor; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="kartu-kaki flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pengawas <?= esc($pgShift) ?></button>
                    <a href="<?= esc($urlShift($pgShift), 'attr') ?>#grid-pengawas" class="btn btn-netral"><?= ikon('close', 'h-4 w-4') ?>Batal</a>
                    <p class="min-w-0 flex-1 basis-64 text-xs text-gray-500 dark:text-gray-400">Satu guru tidak boleh di dua ruang pada jam yang sama, dan tidak boleh bentrok dengan tugasnya di shift lain — sistem menolak dan menyebut selnya.</p>
                </div>
            </form>
            <?php endif; ?>

            <?php // F4: beban mengawasi per guru (semua shift) — supaya kemerataan bisa dicek sendiri. ?>
            <div class="border-t border-gray-200 px-4 py-3 md:px-6 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Beban mengawasi per guru <span class="font-normal text-gray-500 dark:text-gray-400">(jumlah sesi, semua shift)</span></p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <?php foreach ($pgKode as $kode => $g): $n = (int) ($pgBeban[$g['id']] ?? 0); ?>
                        <span class="lencana <?= $n === 0 ? 'lencana-abu' : 'lencana-merek' ?>" title="<?= esc($g['nama'], 'attr') ?>"><span class="font-mono"><?= esc((string) $kode) ?></span>: <?= $n ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
