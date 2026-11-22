<?php
/**
 * Tab "Jadwal Pengawas" — pengawas per RUANG per sesi (F3 cetak jadwal, permintaan user 2026-11-22; bentuk hasil akhir mengikuti
 * berkas contoh sekolah "jadwal pengawas.xlsx"). Dua kartu:
 *  1. Guru pengawas: centang guru yang ikut diatur (kode = Kode Guru di Master Guru; tanpa kode tak bisa dipilih). Belum ada daftar →
 *     centang disalin dari periode lain yang terakhir disimpan (pgSaran) dan baru berlaku setelah "Simpan daftar guru".
 *  2. Grid per shift: baris = hari × jam ke- (istirahat ditampilkan), kolom = RUANG 1..N, isi = KODE guru (satu <datalist> dipakai
 *     semua kotak supaya halaman tetap ringan). Simpan = ganti seluruh isi shift itu; aturan & pengecekan bentrok di
 *     App\Libraries\JadwalPengawas::simpanGrid(). Tombol Generate otomatis (F4) di atas grid → JadwalPengawas::generate(); di
 *     bawah grid ringkasan beban per guru (cek kemerataan).
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
 */
$centang   =array_flip($pgTerpilih !== [] ? $pgTerpilih : ($pgSaran['ids'] ?? []));
$berkode   = array_values(array_filter($pgGuru, static fn (array $g): bool => $g['kode'] !== ''));
$tanpaKode = count($pgGuru) - count($berkode);
$lama      = old('sel'); // isian sebelum ditolak (dikembalikan bersama pesan galat)
$nilaiSel  = static function (string $kunci, int $ruang) use ($lama, $pgIsi): string {
    if (is_array($lama)) {
        return (string) ($lama[$kunci][$ruang] ?? '');
    }

    return (string) ($pgIsi[$kunci][$ruang] ?? '');
};
$hariNama  = [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => "JUM'AT", 6 => 'SABTU', 7 => 'MINGGU'];
?>

<div class="space-y-4">
    <?= ui_peringatan('info', '', ['html' => 'Atur <b>pengawas per ruang</b> untuk setiap sesi ujian. Sesi (hari &amp; jam) diambil otomatis dari tab <b>Jadwal Ujian</b>; jumlah ruang dari tab <b>Periode</b>. Langkah: <b>1)</b> centang guru pengawas lalu Simpan, <b>2)</b> tekan <b>Generate otomatis</b> (atau isi kode guru manual), <b>3)</b> periksa &amp; ubah bila perlu lalu Simpan, <b>4)</b> tombol <b>Cetak</b> → Excel (lembar Pagi, Siang, Kode Pengawas) atau PDF, berbentuk seperti berkas sekolah.']) ?>

    <!-- ===== 1. Guru pengawas ===== -->
    <section id="guru-pengawas" class="kartu" aria-labelledby="judul-guru-pengawas">
        <div class="kartu-kepala">
            <div>
                <h2 id="judul-guru-pengawas" class="kartu-judul">1. Guru pengawas</h2>
                <p class="kartu-ket"><?= count($pgTerpilih) ?> guru dipilih untuk ujian ini. Kode diambil dari <b>Kode Guru</b> di Master Guru.</p>
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
                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <label class="flex cursor-pointer items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-700 dark:text-white">
                        <input type="checkbox" class="cek" @change="pilihSemuaCentang($event)">
                        Pilih semua guru yang punya kode (<?= count($berkode) ?>)
                        <span class="ms-auto text-xs font-normal text-gray-500 dark:text-gray-300" x-text="teksCentang()"></span>
                    </label>
                    <div class="panel-gulir grid max-h-96 grid-cols-1 gap-x-4 p-2 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($pgGuru as $g): $adaKode = $g['kode'] !== ''; ?>
                            <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm <?= $adaKode ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/60' : 'opacity-60' ?>">
                                <?php if ($adaKode): ?>
                                    <input type="checkbox" name="guru_ids[]" value="<?= (int) $g['id'] ?>" data-centang class="cek"<?= isset($centang[$g['id']]) ? ' checked' : '' ?>>
                                <?php else: ?>
                                    <input type="checkbox" class="cek" disabled aria-label="Tanpa kode">
                                <?php endif; ?>
                                <span class="w-10 shrink-0 font-mono font-semibold text-brand-700 dark:text-brand-400"><?= $adaKode ? esc($g['kode']) : '—' ?></span>
                                <span class="min-w-0 flex-1 truncate text-gray-900 dark:text-white" title="<?= esc($g['nama'], 'attr') ?>"><?= esc($g['nama']) ?></span>
                                <?php if ($g['ganda']): ?><span class="lencana lencana-kuning" title="Kode ini dipakai lebih dari satu guru di Master Guru">kode ganda</span><?php endif; ?>
                            </label>
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
                <p class="kartu-ket">Terisi <b><?= (int) $pgTerisi ?></b> dari <?= (int) $pgTotalSel ?> sel (<?= (int) $pgRuang ?> ruang). Ketik <b>kode guru</b> di tiap ruang; kosong = belum ada pengawas.</p>
            </div>
            <?php if ($pgTerisi > 0 || array_sum($pgBeban ?? []) > 0): ?>
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
                                                           list="dl-pengawas" maxlength="20" autocomplete="off" inputmode="text" class="inp-sel"
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
                    <p class="text-xs text-gray-500 dark:text-gray-400">Satu guru tidak boleh di dua ruang pada jam yang sama, dan tidak boleh bentrok dengan tugasnya di shift lain — sistem menolak dan menyebut selnya.</p>
                </div>
            </form>

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
