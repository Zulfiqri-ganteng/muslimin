<?php
/**
 * Pratinjau impor yang dapat diedit — dipakai semua modul master (+ Jadwal KBM & Jadwal Ujian). Rombak tampilan F5: kartu + tabel
 * gaya template Flowbite; logika di komponen `importPreview` (assets/js/admin/app.js).
 * Siap Alpine versi CSP (dan tetap jalan di Alpine biasa — halaman Jadwal/Ujian belum versi CSP): sel diikat x-model="row.<kunci>"
 * (notasi titik; semua kunci kolom = nama sah), lebar kolom lewat kelas min-w-* (bukan atribut style).
 *
 * @var string $subtitle  Nama master (mis. "Master Guru")
 * @var array  $cols      Daftar kolom: ['key','label','type'(text|number|select|datalist),'options'?,'required'?,'width'?]
 * @var array  $rows      Baris hasil baca Excel (assoc, boleh ada kunci '_status')
 * @var string $commitUrl URL tujuan simpan
 * @var string $backUrl   URL batal
 */
// Gabungkan nilai yang ADA di data ke daftar opsi select (agar nilai hasil impor
// selalu tampil & terpilih walau belum terdaftar di master). Datalist tak perlu ini.
foreach ($cols as &$c) {
    if (($c['type'] ?? '') === 'select') {
        $opts = $c['options'] ?? [];
        foreach ($rows as $r) {
            $v = trim((string) ($r[$c['key']] ?? ''));
            if ($v !== '' && ! in_array($v, $opts, true)) {
                $opts[] = $v;
            }
        }
        $c['options'] = $opts;
    }
}
unset($c);

// Lebar minimum kolom (px dari pengendali) → kelas Tailwind TERTULIS LENGKAP (dibaca saat build); nilai lain = yang terdekat.
$kelasLebar = [70 => 'min-w-18', 80 => 'min-w-20', 90 => 'min-w-23', 100 => 'min-w-25', 110 => 'min-w-28', 120 => 'min-w-30', 130 => 'min-w-33',
    140 => 'min-w-35', 150 => 'min-w-38', 160 => 'min-w-40', 180 => 'min-w-45', 200 => 'min-w-50', 220 => 'min-w-55', 240 => 'min-w-60',
    260 => 'min-w-65', 280 => 'min-w-70', 320 => 'min-w-80'];
$lebar = static function (?int $px) use ($kelasLebar): string {
    if ($px === null || $px <= 0) {
        return '';
    }
    $terdekat = 70;
    foreach (array_keys($kelasLebar) as $k) {
        if (abs($k - $px) < abs($terdekat - $px)) {
            $terdekat = $k;
        }
    }

    return $kelasLebar[$terdekat];
};
// Ikatan x-model ke kolom baris: notasi titik bila kunci = nama sah (selalu, untuk kolom kita), cadangan kurung siku.
$ikat = static fn (string $key): string => preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $key) ? 'row.' . $key : "row['" . esc($key, 'js') . "']";
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div x-data="importPreview" data-cols="<?= esc(json_encode(array_column($cols, 'key')), 'attr') ?>" data-rows="<?= esc(json_encode(array_values($rows)), 'attr') ?>">
    <form method="post" action="<?= esc($commitUrl, 'attr') ?>" @submit="prepare()" class="space-y-4">
        <?= csrf_field() ?>
        <?php foreach ($cols as $c): if (($c['type'] ?? '') === 'datalist'): ?>
            <datalist id="dl_<?= esc($c['key'], 'attr') ?>">
                <?php foreach (($c['options'] ?? []) as $opt): ?><option value="<?= esc($opt, 'attr') ?>"></option><?php endforeach; ?>
            </datalist>
        <?php endif; endforeach; ?>

        <div class="kartu">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 class="kartu-judul"><?= esc($subtitle) ?></h2>
                    <p class="kartu-ket">Periksa &amp; <b>ubah langsung</b> data di bawah sebelum disimpan: ubah sel, hapus baris, atau tambah baris baru. Tekan <b>Simpan</b> bila sudah benar.</p>
                </div>
                <span class="lencana lencana-hijau text-sm"><b x-text="rows.length"></b>&nbsp;baris</span>
            </div>

            <div class="tabel-bungkus">
                <table class="tabel-crud">
                    <thead>
                        <tr>
                            <th scope="col" class="w-12 text-center">#</th>
                            <th scope="col">Status</th>
                            <?php foreach ($cols as $c): ?>
                                <th scope="col" class="<?= $lebar(isset($c['width']) ? (int) $c['width'] : null) ?>">
                                    <?= esc($c['label']) ?><?= ! empty($c['required']) ? ' <span class="text-red-600 dark:text-red-400">*</span>' : '' ?>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="kolom-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in rows" :key="row._uid">
                            <tr class="align-top">
                                <td class="sel-ket text-center" x-text="idx + 1"></td>
                                <td>
                                    <span class="lencana" :class="row._status === 'perbarui' ? 'lencana-kuning' : 'lencana-hijau'" x-text="row._status === 'perbarui' ? 'Perbarui' : 'Baru'"></span>
                                </td>
                                <?php foreach ($cols as $c): $key = (string) $c['key']; $type = $c['type'] ?? 'text'; ?>
                                    <td class="px-2 py-2">
                                        <?php if ($type === 'select'): ?>
                                            <select x-model="<?= $ikat($key) ?>" class="inp py-1.5" aria-label="<?= esc($c['label'], 'attr') ?>">
                                                <option value="">—</option>
                                                <?php foreach (($c['options'] ?? []) as $opt): ?>
                                                    <option value="<?= esc($opt, 'attr') ?>"><?= esc($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($type === 'datalist'): ?>
                                            <input type="text" list="dl_<?= esc($key, 'attr') ?>" x-model="<?= $ikat($key) ?>" class="inp py-1.5" aria-label="<?= esc($c['label'], 'attr') ?>">
                                        <?php else: ?>
                                            <input type="<?= $type === 'number' ? 'number' : 'text' ?>" x-model="<?= $ikat($key) ?>" class="inp py-1.5" aria-label="<?= esc($c['label'], 'attr') ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="kolom-aksi">
                                    <button type="button" class="btn btn-bahaya btn-sm" @click="removeRow(idx)" aria-label="Hapus baris ini"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0" x-cloak>
                            <td colspan="<?= count($cols) + 3 ?>"><?= ui_kosong(['ikon' => 'file-lines', 'judul' => 'Tidak ada baris', 'teks' => 'Tambah baris, atau batalkan impor.']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="kartu-kaki">
                <button type="button" class="btn btn-netral btn-sm" @click="addRow()"><?= ikon('plus', 'h-4 w-4') ?>Tambah baris</button>
            </div>
        </div>

        <!-- payload tersembunyi (diisi saat submit) -->
        <div x-ref="payload"></div>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="<?= esc($backUrl, 'attr') ?>" class="btn btn-netral">Batal</a>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan <span x-text="rows.length"></span> data</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
