<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Jadwal mengajar per guru (pola 4c rombak publik P5): pembuka · saringan dalam kartu (pilih guru → langsung tampil) · satu tabel
 * jadwal per shift (kolom jam terkunci saat digeser di HP, kolom hari ini disorot) · Unduh PDF. Data & logika sama dengan versi lama.
 *
 * @var array<int, string> $guruOpts @var int $guruId @var array|null $guru @var list<array> $hari @var list<array> $jam
 * @var array<string, array> $grid "hariId-jamId" => sel @var array $now (hariId, hariNama, label)
 */
$hariIni = (int) ($now['hariId'] ?? 0);
$waktu   = static fn (array $j): string => substr((string) $j['waktu_mulai'], 0, 5) . '–' . substr((string) $j['waktu_selesai'], 0, 5);
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Guru', site_url('guru')], ['Jadwal mengajar', null]],
    'ilu'   => 'jadwal-guru',
    'label' => 'Layanan guru',
    'judul' => 'Jadwal mengajar',
    'ket'   => 'Jadwal mengajar setiap guru per hari. Pilih nama guru untuk melihat jadwalnya, lalu unduh PDF bila perlu.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-6">
        <form method="get" class="kartu kartu-isi saringan">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label class="lbl" for="pilih-guru">Guru</label>
                <select id="pilih-guru" name="guru_id" class="inp" data-kirim-otomatis required>
                    <option value="">Pilih guru</option>
                    <?php foreach ($guruOpts as $id => $label): ?>
                        <option value="<?= (int) $id ?>"<?= $guruId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="tombol tombol-utama h-11"><?= ikon('search') ?>Tampilkan</button>
            <a href="<?= site_url('jadwal-kelas') ?>" class="tombol tombol-teks sm:ml-auto sm:self-center">Lihat jadwal per kelas<?= ikon('arrow-right', 'h-4 w-4') ?></a>
        </form>

        <?php if (! $guru): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('calendar-month') ?></span><p class="kosong-judul">Belum ada guru dipilih</p><p class="kosong-teks">Pilih nama guru di atas untuk melihat jadwal mengajarnya.</p></div></div>
        <?php elseif (empty($jam) || empty($hari)): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('calendar-month') ?></span><p class="kosong-judul">Jadwal belum tersedia</p><p class="kosong-teks">Jadwal mengajar <?= esc($guru['nama']) ?> belum disusun sekolah.</p></div></div>
        <?php else: ?>
            <?php
            // Jam dipisah per shift → tiap shift satu tabel sendiri.
            $byShift = [];
            foreach ($jam as $j) {
                $byShift[$j['shift']][] = $j;
            }
            ?>
            <div class="space-y-6">
                <div class="hasil-kepala mb-0">
                    <h2 class="judul-sub"><?= esc($guru['nama']) ?></h2>
                    <a href="<?= site_url('jadwal-guru/' . $guruId . '/pdf') ?>" target="_blank" rel="noopener" class="tombol tombol-garis"><?= ikon('file-pdf') ?>Unduh PDF</a>
                </div>
                <?php foreach ($byShift as $shift => $jamShift): ?>
                    <div>
                        <h3 class="judul-kelompok">Shift <?= esc(['pagi' => 'Pagi', 'siang' => 'Siang'][$shift] ?? ucfirst((string) $shift)) ?></h3>
                        <div class="jadwal-bungkus">
                            <table class="jadwal">
                                <thead>
                                    <tr>
                                        <th scope="col" class="jadwal-jam">Jam</th>
                                        <?php foreach ($hari as $h): $td = (int) $h['id'] === $hariIni; ?>
                                            <th scope="col"<?= $td ? ' class="jadwal-hari-ini"' : '' ?>><?= esc($h['nama']) ?><?= $td ? '<span class="jadwal-tanda">Hari ini</span>' : '' ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jamShift as $j): ?>
                                        <?php if (! empty($j['is_istirahat'])): ?>
                                            <tr class="jadwal-istirahat"><td colspan="<?= count($hari) + 1 ?>">Istirahat · <?= esc($waktu($j)) ?></td></tr>
                                        <?php else: ?>
                                            <tr>
                                                <th scope="row" class="jadwal-jam">
                                                    <span class="jadwal-jam-ke">Jam ke-<?= esc($j['jam_ke']) ?></span>
                                                    <span class="jadwal-waktu"><?= esc($waktu($j)) ?></span>
                                                </th>
                                                <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                                                    <td class="jadwal-sel<?= (int) $h['id'] === $hariIni ? ' jadwal-hari-ini' : '' ?>">
                                                        <?php if ($c): ?>
                                                            <div class="jadwal-isi">
                                                                <span class="jadwal-mapel"><?= esc($c['nama_kelas']) ?></span>
                                                                <span class="jadwal-ket"><?= esc($c['nama_mapel']) ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="hint">Di HP, geser tabel ke samping untuk melihat hari lainnya.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
