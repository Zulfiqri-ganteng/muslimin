<?php
/**
 * Bagian "Istirahat" di tab Jadwal Ujian (keluhan klien 2026-11-24: istirahat tidak muncul & tidak bisa di-set; tahap 1).
 * Isi = persis yang tercetak di Jadwal Ujian dan Jadwal Pengawas (App\Libraries\IstirahatUjian::ringkas): per shift + hari ujian,
 * otomatis dari jeda jam, diatur sendiri, atau ditiadakan. Ubah lewat satu dialog (juga dibuka dari baris ISTIRAHAT di daftar jadwal
 * dan menu "Cetak & lainnya" lewat [data-atur-istirahat]); Hapus & Kembalikan otomatis = formulir kecil ke alamat yang sama.
 * Dialog sengaja DI LUAR <details> (isi details yang tertutup tidak bisa tampil). Komponen: assets/js/admin/ujian/istirahat.js (CSP).
 *
 * @var string      $base
 * @var string      $tp
 * @var list<array> $istirahat IstirahatUjian::ringkas()
 * @var callable    $dataIst   baris ringkas → JSON (sudah di-esc) untuk data-row dialog
 * @var array       $labelShift
 * @var array       $warnaShift
 */
use App\Libraries\IstirahatUjian;
use App\Libraries\JadwalUjianCetak;

$berjadwal = array_values(array_filter($istirahat, static fn (array $h): bool => $h['adaJadwal']));
$belum     = count(array_filter($berjadwal, static fn (array $h): bool => $h['jam'] === [] && $h['mode'] === 'otomatis'));
$ringkasan = $berjadwal === [] ? 'belum ada jadwal' : ($belum > 0 ? $belum . ' hari belum ada' : count($berjadwal) . ' hari ujian');
$warnaMode = ['otomatis' => ['otomatis', 'lencana-abu'], 'diatur' => ['diatur', 'lencana-hijau'], 'ditiadakan' => ['tanpa istirahat', 'lencana-abu']];

// Isian sebelum ditolak → dialog terbuka lagi dengan isi yang sama (jangan sampai mengetik ulang).
$lamaHari = old('ist_hari');
$lama     = null;
if (is_string($lamaHari) && $lamaHari !== '') {
    $lm   = array_values((array) old('ist_mulai'));
    $ls   = array_values((array) old('ist_selesai'));
    $lama = ['hari' => $lamaHari, 'mode' => (string) old('ist_mode', 'atur')];
    for ($i = 1; $i <= IstirahatUjian::MAKS; $i++) {
        $lama['m' . $i] = is_string($lm[$i - 1] ?? null) ? $lm[$i - 1] : '';
        $lama['s' . $i] = is_string($ls[$i - 1] ?? null) ? $ls[$i - 1] : '';
    }
}

// Formulir kecil satu tombol: Hapus (tanpa) & Kembalikan otomatis.
$tombolMode = static function (array $h, string $mode, string $ikon, string $label, string $tanya, string $judul, string $ya, string $warna) use ($base, $tp): string {
    return '<form method="post" action="' . esc($base, 'attr') . '/istirahat" class="inline">' . csrf_field()
        . '<input type="hidden" name="tp" value="' . esc($tp, 'attr') . '">'
        . '<input type="hidden" name="ist_hari" value="' . esc($h['shift'] . '|' . $h['tanggal'], 'attr') . '">'
        . '<input type="hidden" name="ist_mode" value="' . esc($mode, 'attr') . '">'
        . '<button type="submit" class="btn ' . $warna . ' btn-sm btn-ikon" title="' . esc($label, 'attr') . '" aria-label="' . esc($label . ' ' . $h['shift'] . ' ' . $h['hari'] . ' ' . $h['tgl'], 'attr') . '"'
        . ' data-confirm="' . esc($tanya, 'attr') . '" data-confirm-judul="' . esc($judul, 'attr') . '" data-confirm-ya="' . esc($ya, 'attr') . '"'
        . ($warna === 'btn-bahaya' ? ' data-confirm-jenis="bahaya"' : '') . '>' . ikon($ikon, 'h-4 w-4') . '</button></form>';
};
?>
<div id="istirahat" class="mb-4 scroll-mt-24" x-data="istirahatUjian"<?= $lama !== null ? ' data-lama="' . esc((string) json_encode($lama), 'attr') . '"' : '' ?>>
    <?= ui_lipat_buka(['judul' => 'Istirahat', 'ringkas' => $ringkasan, 'ikon' => 'clock', 'buka' => (bool) session('bukaIstirahat') || $belum > 0]) ?>
        <?php if ($istirahat === []): ?>
            <p class="px-4 py-4 text-sm text-gray-500 sm:px-6 dark:text-gray-400">Istirahat bisa diatur setelah jadwal ujian diisi. Tambah jadwal dulu di bawah.</p>
        <?php else: ?>
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <p class="min-w-0 flex-1 basis-64 text-sm text-gray-500 dark:text-gray-400">Jam di sini sama dengan yang tampil di <b>Jadwal Ujian</b> dan <b>Jadwal Pengawas</b>, di layar maupun cetakan. Yang belum diatur dihitung <b>otomatis</b> dari jeda antara dua jam ujian.</p>
                <?php if ($berjadwal !== []): ?>
                    <button type="button" class="btn btn-utama" data-atur-istirahat data-row="{}"><?= ikon('plus', 'h-4 w-4') ?>Atur istirahat</button>
                <?php endif; ?>
            </div>
            <ul class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <?php foreach ($istirahat as $h): [$teksMode, $kelasMode] = $warnaMode[$h['mode']]; ?>
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-6">
                        <div class="w-full sm:w-72">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($h['hari']) ?>, <?= esc($h['tgl']) ?> <span class="lencana <?= $warnaShift[$h['shift']] ?> ms-1"><?= esc($labelShift[$h['shift']]) ?></span></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $h['sesi'] === [] ? 'Tidak ada ujian' : 'Ujian ' . esc(implode(', ', $h['sesi'])) ?></p>
                        </div>
                        <div class="min-w-0 flex-1 basis-48">
                            <p class="flex flex-wrap items-center gap-2 text-sm">
                                <?php if ($h['jam'] !== []): ?>
                                    <b class="text-gray-900 dark:text-white"><?= esc(IstirahatUjian::teksJam($h['jam'])) ?></b>
                                    <span class="lencana <?= $kelasMode ?>"><?= esc($teksMode) ?></span>
                                <?php elseif ($h['mode'] === 'ditiadakan'): ?>
                                    <span class="lencana <?= $kelasMode ?>"><?= esc($teksMode) ?></span>
                                <?php else: ?>
                                    <span class="lencana lencana-kuning">belum ada istirahat</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($h['catatan'] !== ''): ?><p class="mt-1 text-xs text-yellow-700 dark:text-yellow-400"><?= esc($h['catatan']) ?></p><?php endif; ?>
                        </div>
                        <div class="aksi-baris">
                            <?php if ($h['adaJadwal']): ?>
                                <button type="button" class="btn btn-utama btn-sm btn-ikon" title="Ubah istirahat" aria-label="Ubah istirahat <?= esc($h['shift'] . ' ' . $h['hari'] . ' ' . $h['tgl'], 'attr') ?>" data-atur-istirahat data-row="<?= $dataIst($h) ?>"><?= ikon('edit', 'h-4 w-4') ?></button>
                                <?php if ($h['mode'] !== 'otomatis'): ?>
                                    <?= $tombolMode($h, 'otomatis', 'clock-arrow', 'Kembalikan otomatis', 'Istirahat ' . $h['shift'] . ' ' . $h['hari'] . ', ' . $h['tgl'] . ' dihitung lagi otomatis dari jeda jam ujian. Jam yang diatur sendiri dibuang.', 'Kembalikan otomatis?', 'Ya, otomatis', 'btn-netral') ?>
                                <?php endif; ?>
                                <?php if ($h['jam'] !== []): ?>
                                    <?= $tombolMode($h, 'tanpa', 'trash-bin', 'Hapus istirahat', 'Istirahat ' . $h['shift'] . ' ' . $h['hari'] . ', ' . $h['tgl'] . ' tidak akan tampil lagi di Jadwal Ujian dan Jadwal Pengawas. Jam ujian dan pengawas tidak berubah.', 'Hapus istirahat?', 'Ya, hapus', 'btn-bahaya') ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= $tombolMode($h, 'otomatis', 'trash-bin', 'Hapus pengaturan', 'Hapus pengaturan istirahat ' . $h['shift'] . ' ' . $h['hari'] . ', ' . $h['tgl'] . '? Tidak ada jadwal ujian di hari itu.', 'Hapus pengaturan?', 'Ya, hapus', 'btn-bahaya') ?>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?= ui_lipat_tutup() ?>

    <?php if ($berjadwal !== []): ?>
        <?= ui_dialog_buka(['nama' => 'atur-istirahat', 'judul' => 'Atur istirahat', 'awalBuka' => $lama !== null]) ?>
        <form method="post" action="<?= esc($base, 'attr') ?>/istirahat">
            <?= csrf_field() ?>
            <input type="hidden" name="tp" value="<?= esc($tp, 'attr') ?>">
            <div class="dialog-isi">
                <div>
                    <label class="lbl" for="ist-hari">Untuk hari <span class="text-red-600 dark:text-red-400">*</span></label>
                    <select id="ist-hari" name="ist_hari" x-model="form.hari" required class="inp">
                        <option value="">— Pilih hari ujian —</option>
                        <?php foreach (IstirahatUjian::SHIFT as $s):
                            $hariShift = array_values(array_filter($berjadwal, static fn (array $h): bool => $h['shift'] === $s));
                            if ($hariShift === []) {
                                continue;
                            } ?>
                            <optgroup label="<?= esc($labelShift[$s], 'attr') ?>">
                                <?php if (count($hariShift) > 1): ?><option value="<?= esc($s, 'attr') ?>|semua">Semua hari ujian <?= esc($s) ?> (<?= count($hariShift) ?> hari)</option><?php endif; ?>
                                <?php foreach ($hariShift as $h): ?>
                                    <option value="<?= esc($s . '|' . $h['tanggal'], 'attr') ?>"><?= esc($labelShift[$s]) ?> — <?= esc($h['hari'] . ', ' . $h['tgl']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <fieldset class="space-y-2">
                    <legend class="lbl">Istirahat</legend>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white"><input type="radio" name="ist_mode" value="atur" x-model="form.mode" class="cek">Atur jamnya sendiri</label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white"><input type="radio" name="ist_mode" value="otomatis" x-model="form.mode" class="cek">Otomatis dari jeda jam ujian</label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white"><input type="radio" name="ist_mode" value="tanpa" x-model="form.mode" class="cek">Tanpa istirahat</label>
                </fieldset>
                <div class="space-y-4" x-show="modeAtur()">
                    <?php for ($i = 1; $i <= IstirahatUjian::MAKS; $i++): ?>
                        <div class="grid grid-cols-2 gap-4"<?= $i > 1 ? ' x-show="tampil' . $i . '()" x-cloak' : '' ?>>
                            <div>
                                <label class="lbl" for="ist-m<?= $i ?>">Mulai<?= $i > 1 ? ' ke-' . $i : '' ?><?php if ($i === 1): ?> <span class="text-red-600 dark:text-red-400">*</span><?php endif; ?></label>
                                <input type="time" id="ist-m<?= $i ?>" name="ist_mulai[]" x-model="form.m<?= $i ?>"<?= $i === 1 ? ' :required="modeAtur()"' : '' ?> class="inp">
                            </div>
                            <div>
                                <label class="lbl" for="ist-s<?= $i ?>">Selesai<?= $i > 1 ? ' ke-' . $i : '' ?><?php if ($i === 1): ?> <span class="text-red-600 dark:text-red-400">*</span><?php endif; ?></label>
                                <input type="time" id="ist-s<?= $i ?>" name="ist_selesai[]" x-model="form.s<?= $i ?>"<?= $i === 1 ? ' :required="modeAtur()"' : '' ?> class="inp">
                            </div>
                        </div>
                    <?php endfor; ?>
                    <p class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-netral btn-sm" x-show="bisaTambah()" @click="tambahBaris()"><?= ikon('plus', 'h-4 w-4') ?>Tambah istirahat lagi</button>
                        <button type="button" class="btn btn-netral btn-sm" x-show="bisaKurang()" x-cloak @click="kurangBaris()"><?= ikon('close', 'h-4 w-4') ?>Batalkan yang terakhir</button>
                    </p>
                </div>
                <p class="hint">Istirahat tidak boleh menabrak jam ujian shift itu. Contoh: ujian 07.00–08.30 lalu 09.10–10.40, istirahatnya 08.30–09.10. Bila memilih <b>semua hari</b>, hari yang jamnya tidak cocok dilewati dan disebutkan.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
            </div>
        </form>
        <?= ui_dialog_tutup() ?>
    <?php endif; ?>
</div>
<script defer src="<?= base_url('assets/js/admin/ujian/istirahat.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ujian/istirahat.js') ?>" <?= csp_script_nonce() ?>></script>
