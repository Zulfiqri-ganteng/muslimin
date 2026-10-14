<?php
/**
 * Tab Ketidakhadiran — inti modul. Rombak tampilan F7: kartu pemilih kit, lencana sesi, tabel kit, ui_kosong; komponen Alpine
 * takHadirKelas / takHadirBaris di assets/js/admin/ujian/ketidakhadiran.js (tanpa objek sebaris di x-data) → siap CSP.
 *
 * Alur tiga langkah: pilih sesi ujian → pilih kelas (otomatis disaring sesuai
 * tingkat/jurusan/shift sesi itu) → centang siswa yang TIDAK hadir.
 * Siswa yang hadir tidak pernah disimpan; yang dicentang langsung jadi baris
 * daftar ujian susulan.
 *
 * @var array                $periode
 * @var string               $base
 * @var string               $tp
 * @var string               $qtp
 * @var int                  $jadwalId
 * @var int                  $kelasId
 * @var array|null           $jadwalPilih
 * @var array|null           $kelasPilih
 * @var array<int,array>     $kelasSasaran
 * @var array<int,array>     $siswa
 * @var array<int,array>     $tercatat       siswa_id => baris susulan
 * @var array<int,string>    $jadwalOpts
 * @var array<int,string>    $alasanList
 * @var int                  $jmlTakHadirSesi
 */
$HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
$labelStatusSusulan = [
    'belum'       => 'Belum dijadwalkan',
    'dijadwalkan' => 'Sudah dijadwalkan',
    'selesai'     => 'Selesai',
    'batal'       => 'Batal',
];
$warnaStatusSusulan = ['belum' => 'lencana-kuning', 'dijadwalkan' => 'lencana-merek', 'selesai' => 'lencana-hijau', 'batal' => 'lencana-abu'];
?>

<div class="space-y-4">
    <!-- Langkah 1 & 2: pilih sesi lalu kelas -->
    <section class="kartu" aria-label="Pilih sesi & kelas">
        <form method="get" action="<?= $base ?>/ketidakhadiran" class="kartu-isi grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
            <div>
                <label class="lbl" for="th-sesi">1. Sesi ujian</label>
                <select id="th-sesi" name="jadwal_id" data-autosubmit class="inp">
                    <option value="">— Pilih sesi ujian —</option>
                    <?php foreach ($jadwalOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"<?= $jadwalId === (int) $id ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="th-kelas">2. Kelas</label>
                <select id="th-kelas" name="kelas_id" data-autosubmit<?= $jadwalPilih ? '' : ' disabled' ?> class="inp">
                    <option value="">— Pilih kelas —</option>
                    <?php foreach ($kelasSasaran as $k): ?>
                        <option value="<?= (int) $k['id'] ?>"<?= $kelasId === (int) $k['id'] ? ' selected' : '' ?>><?= esc($k['nama_kelas']) ?> (<?= esc($k['shift']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($jadwalPilih && $kelasSasaran === []): ?>
                    <p class="hint text-yellow-700 dark:text-yellow-400">Tidak ada kelas yang cocok dengan tingkat/jurusan/shift sesi ini.</p>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($jadwalPilih): ?>
            <?php $ts = strtotime((string) $jadwalPilih['tanggal']); ?>
            <div class="kartu-kaki flex flex-wrap items-center gap-2">
                <span class="lencana lencana-abu"><?= esc($jadwalPilih['nama_mapel'] ?? 'Tanpa mapel') ?></span>
                <span class="lencana lencana-abu"><?= ikon('calendar-month', 'h-3.5 w-3.5') ?><?= esc($HARI[(int) date('N', $ts)] ?? '') ?>, <?= date('d/m/Y', $ts) ?></span>
                <span class="lencana lencana-abu">Tingkat <?= esc($jadwalPilih['tingkat']) ?><?= $jadwalPilih['jurusan_kode'] ? ' ' . esc($jadwalPilih['jurusan_kode']) : '' ?> · <?= esc($jadwalPilih['shift']) ?></span>
                <span class="lencana <?= $jmlTakHadirSesi > 0 ? 'lencana-kuning' : 'lencana-abu' ?>">Tidak hadir di sesi ini: <?= (int) $jmlTakHadirSesi ?></span>
                <span class="lencana lencana-abu"><?= count($kelasSasaran) ?> kelas sasaran</span>
            </div>
        <?php endif; ?>
    </section>

    <?php if (! $jadwalPilih): ?>

        <div class="kartu"><?= ui_kosong([
            'ikon'  => 'clipboard-check',
            'judul' => 'Pilih sesi ujian dulu',
            'teks'  => 'Setelah sesi dipilih, daftar kelas sasarannya muncul otomatis sesuai tingkat, jurusan, dan shift sesi itu.' . ($jadwalOpts === [] ? ' Belum ada sesi ujian — susun dulu di tab Jadwal Ujian.' : ''),
            'aksi'  => $jadwalOpts === [] ? '<a href="' . $base . '/jadwal' . $qtp . '" class="btn btn-netral">' . ikon('calendar-week', 'h-4 w-4') . 'Buka tab Jadwal Ujian</a>' : '',
        ]) ?></div>

    <?php elseif (! $kelasPilih): ?>

        <div class="kartu"><?= ui_kosong(['ikon' => 'school', 'judul' => 'Pilih kelas', 'teks' => 'Daftar siswa muncul setelah kelas dipilih.']) ?></div>

    <?php elseif ($siswa === []): ?>

        <div class="kartu"><?= ui_kosong(['ikon' => 'users', 'judul' => 'Kelas ' . $kelasPilih['nama_kelas'] . ' belum punya siswa aktif', 'teks' => 'Tambahkan siswanya lebih dulu di Master Data → Siswa.']) ?></div>

    <?php else: ?>

        <form method="post" action="<?= $base ?>/ketidakhadiran">
            <?= csrf_field() ?>
            <input type="hidden" name="periode_id" value="<?= (int) $periode['id'] ?>">
            <input type="hidden" name="jadwal_id" value="<?= (int) $jadwalId ?>">
            <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">

            <?php // x-data dipasang di kartu (bukan di tabel) supaya penghitung di
                  // kepala kartu ikut masuk cakupan Alpine yang sama. ?>
            <section class="kartu overflow-hidden" x-data="takHadirKelas" aria-labelledby="judul-centang">
                <div class="kartu-kepala">
                    <div>
                        <h2 id="judul-centang" class="kartu-judul">3. Centang siswa yang TIDAK hadir</h2>
                        <p class="kartu-ket">Kelas <?= esc($kelasPilih['nama_kelas']) ?> — <?= count($siswa) ?> siswa aktif. Siswa yang hadir tidak perlu diapa-apakan.</p>
                    </div>
                    <span class="lencana lencana-kuning">Tercentang: <span x-text="jumlah">0</span></span>
                </div>

                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col" class="w-16 text-center">Tidak<br>hadir</th>
                                <th scope="col">Siswa</th>
                                <th scope="col" class="w-40">Alasan</th>
                                <th scope="col">Keterangan</th>
                                <th scope="col" class="w-44">Status susulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa as $s): ?>
                                <?php
                                $sid  = (int) $s['id'];
                                $lama = $tercatat[$sid] ?? null;
                                $on   = $lama !== null;
                                ?>
                                <tr x-data="takHadirBaris" data-on="<?= $on ? '1' : '0' ?>" :class="on ? 'bg-yellow-50 dark:bg-yellow-900/20' : ''">
                                    <td class="text-center">
                                        <input type="checkbox" name="tidak_hadir[]" value="<?= $sid ?>"<?= $on ? ' checked' : '' ?> x-model="on" @change="hitung()" class="cek" aria-label="<?= esc($s['nama'], 'attr') ?> tidak hadir">
                                    </td>
                                    <td>
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc($s['nama']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">NIS <?= esc($s['nis']) ?></p>
                                    </td>
                                    <td>
                                        <select name="alasan[<?= $sid ?>]" :disabled="hadir()"<?= $on ? '' : ' disabled' ?> class="inp py-1.5" aria-label="Alasan">
                                            <?php foreach ($alasanList as $a): ?>
                                                <option value="<?= esc($a, 'attr') ?>"<?= ($lama['alasan'] ?? 'alpa') === $a ? ' selected' : '' ?>><?= esc($labelAlasan[$a] ?? $a) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan[<?= $sid ?>]" maxlength="255" :disabled="hadir()"<?= $on ? '' : ' disabled' ?> value="<?= esc($lama['keterangan'] ?? '', 'attr') ?>" placeholder="mis. surat dokter" class="inp py-1.5" aria-label="Keterangan">
                                    </td>
                                    <td>
                                        <?php if ($lama): ?>
                                            <span class="lencana <?= $warnaStatusSusulan[$lama['status']] ?? $warnaStatusSusulan['belum'] ?>"><?= esc($labelStatusSusulan[$lama['status']] ?? $lama['status']) ?></span>
                                            <?php if ($lama['status'] === 'selesai'): ?>
                                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Dikunci — tak bisa dibatalkan dari sini.</p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="kartu-kaki flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan pendataan</button>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Centangan yang dilepas berarti siswa ternyata hadir — catatannya dihapus.
                        Susulan yang sudah <b>Selesai</b> tetap dipertahankan.
                    </p>
                </div>
            </section>
        </form>
        <script defer src="<?= base_url('assets/js/admin/ujian/ketidakhadiran.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/ketidakhadiran.js') ?>" <?= csp_script_nonce() ?>></script>

    <?php endif; ?>
</div>
