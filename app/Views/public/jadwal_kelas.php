<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Jadwal pelajaran per kelas (pola 4c rombak publik P5): pembuka · saringan dalam kartu (pilih kelas → langsung tampil lewat
 * [data-kirim-otomatis] di portal.js; tombol Tampilkan bila JS mati) · tabel jadwal (kolom jam terkunci saat digeser di HP,
 * kolom hari ini disorot) · Unduh PDF. Data & logika sama dengan versi lama.
 *
 * @var array<int, string> $kelasOpts @var int $kelasId @var array|null $kelas @var list<array> $hari @var list<array> $jam
 * @var array<string, array> $grid "hariId-jamId" => sel @var array $now (hariId, hariNama, label)
 */
$hariIni = (int) ($now['hariId'] ?? 0);
$waktu   = static fn (array $j): string => substr((string) $j['waktu_mulai'], 0, 5) . '–' . substr((string) $j['waktu_selesai'], 0, 5);
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Siswa', site_url('siswa')], ['Jadwal kelas', null]],
    'ilu'   => 'jadwal-kelas',
    'label' => 'Layanan siswa',
    'judul' => 'Jadwal kelas',
    'ket'   => 'Jadwal pelajaran mingguan setiap kelas. Pilih kelas untuk melihat jadwalnya, lalu unduh PDF bila perlu.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-6">
        <form method="get" class="kartu kartu-isi saringan">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <label class="lbl" for="pilih-kelas">Kelas</label>
                <select id="pilih-kelas" name="kelas_id" class="inp" data-kirim-otomatis required>
                    <option value="">Pilih kelas</option>
                    <?php foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="tombol tombol-utama h-11"><?= ikon('search') ?>Tampilkan</button>
            <a href="<?= site_url('jadwal-guru') ?>" class="tombol tombol-teks sm:ml-auto sm:self-center">Lihat jadwal mengajar guru<?= ikon('arrow-right', 'h-4 w-4') ?></a>
        </form>

        <?php if (! $kelas): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('calendar-week') ?></span><p class="kosong-judul">Belum ada kelas dipilih</p><p class="kosong-teks">Pilih kelas di atas untuk melihat jadwal pelajaran mingguannya.</p></div></div>
        <?php elseif (empty($jam) || empty($hari)): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('calendar-week') ?></span><p class="kosong-judul">Jadwal belum tersedia</p><p class="kosong-teks">Jadwal untuk kelas <?= esc($kelas['nama_kelas']) ?> belum disusun sekolah.</p></div></div>
        <?php else: ?>
            <div>
                <div class="hasil-kepala">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="judul-sub"><?= esc($kelas['nama_kelas']) ?></h2>
                        <span class="label <?= $kelas['shift'] === 'pagi' ? 'label-kuning' : 'label-merek' ?>">Shift <?= esc(ucfirst((string) $kelas['shift'])) ?></span>
                    </div>
                    <a href="<?= site_url('jadwal-kelas/' . $kelasId . '/pdf') ?>" target="_blank" rel="noopener" class="tombol tombol-garis"><?= ikon('file-pdf') ?>Unduh PDF</a>
                </div>
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
                            <?php foreach ($jam as $j): ?>
                                <?php if ($j['is_istirahat']): ?>
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
                                                        <span class="jadwal-mapel"><?= esc($c['nama_mapel']) ?></span>
                                                        <span class="jadwal-ket"><?= esc($c['guru_nama']) ?></span>
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
                <p class="hint">Di HP, geser tabel ke samping untuk melihat hari lainnya.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
