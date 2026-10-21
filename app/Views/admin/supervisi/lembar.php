<?php
/**
 * Lembar Penilaian Supervisi satu kunjungan (menu KURIKULUM → Supervisi Guru → Penilaian). Kartu identitas, satu formulir berisi tab
 * per instrumen: tiap butir = pilihan 1–4 / TD (tidak dinilai) berbentuk tombol (radio asli, tanpa skrip pun tetap bisa dikirim) +
 * catatan opsional (<details>), lalu ringkasan nilai, kekuatan, kelemahan, rekomendasi, tindak lanjut. Bilah bawah: nilai akhir +
 * Simpan draf / Simpan & selesai. Komponen Alpine "supervisiLembar" (assets/js/admin/supervisi.js) menghitung nilai di layar &
 * memeriksa kelengkapan sebelum "selesai" (server tetap memeriksa). Akun level Lihat: semua isian nonaktif (fieldset disabled).
 *
 * @var array{jadwal: array<string, mixed>, instrumen: list<array<string, mixed>>, ringkas: array{nilai: ?float, predikat: ?string, lengkap: bool}} $lembar
 * @var bool   $kelola
 * @var string $kembali query string daftar penilaian (saringan semula)
 */

use App\Libraries\Keterlambatan;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiPenilaian as Penilaian;

$j       = $lembar['jadwal'];
$selesai = $j['status'] === 'selesai';
$batal   = $j['status'] === 'batal';
$boleh   = $kelola && ! $batal;
$lama    = old('nilai', null, false); // isian sebelum galat simpan
$lama    = is_array($lama) ? $lama : null;
$daftar  = site_url('admin/supervisi/penilaian') . ($kembali !== '' ? '?' . ltrim($kembali, '?') : '');
$tabAwal = (string) ($lembar['instrumen'][0]['id'] ?? '');
$warnaStatus = ['dijadwalkan' => 'lencana-biru', 'selesai' => 'lencana-hijau', 'batal' => 'lencana-merah'];

// Isian satu instrumen: isian lama (galat simpan) didahulukan, lalu yang tersimpan.
$nilaiIsian = static function (array $ins, string $kunci, string $bagian = 'skor') use ($lama): mixed {
    $l = $lama[$ins['id']] ?? null;
    if ($l !== null) {
        return $bagian === 'teks' ? ($l[$kunci] ?? '') : ($bagian === 'tl' ? (array) ($l['tindak_lanjut'] ?? []) : ($l[$bagian][$kunci] ?? ''));
    }
    if ($bagian === 'teks') {
        return $ins[$kunci];
    }
    if ($bagian === 'tl') {
        return $ins['tindak_lanjut'];
    }
    foreach ($ins['butir'] as $b) {
        if ($b['kunci'] === $kunci) {
            return $bagian === 'skor' ? ($b['skor'] === null ? '' : (string) $b['skor']) : $b['catatan'];
        }
    }

    return '';
};
$pilihan = Penilaian::SKOR + [0 => 'Tidak dinilai'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_lembar_v1',
    'helpTitle' => 'Mengisi lembar penilaian',
    'helpBody'  => '<p>Pilih tab <b>instrumen</b>, lalu beri skor tiap butir: <b>1 Kurang · 2 Cukup · 3 Baik · 4 Sangat Baik</b>. Butir yang tidak bisa diamati pilih <b>TD</b> (tidak dinilai) — tidak ikut dihitung. Tekan <b>Catatan</b> di bawah butir untuk menulis catatan singkat.</p>'
        . '<p class="mt-2">Nilai instrumen = jumlah skor ÷ (butir yang dinilai × 4) × 100. Nilai akhir = rata-rata nilai semua instrumen. Isi juga <b>kekuatan, kelemahan, rekomendasi</b>, dan <b>tindak lanjut</b> di bawah tiap instrumen.</p>'
        . '<p class="mt-2"><b>Simpan draf</b> menyimpan tanpa harus lengkap. <b>Simpan &amp; selesai</b> hanya bisa bila semua butir semua instrumen sudah diisi.</p>',
]) ?>

<div x-data="supervisiLembar" data-predikat="<?= esc((string) json_encode(Penilaian::PREDIKAT), 'attr') ?>" data-tab-awal="<?= esc($tabAwal, 'attr') ?>" class="space-y-4">

    <!-- ===== Identitas kunjungan ===== -->
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2 class="kartu-judul">Lembar Penilaian Supervisi Akademik</h2>
                <p class="kartu-ket"><?= esc(Supervisi::labelPeriode((string) $j['tahun_ajaran'], (string) $j['semester'])) ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="lencana <?= $warnaStatus[$j['status']] ?? 'lencana-abu' ?>"><?= esc(Supervisi::STATUS[$j['status']] ?? $j['status']) ?></span>
                <?php if (! $batal && $lembar['instrumen'] !== []): ?>
                    <a href="<?= site_url('admin/supervisi/penilaian/' . (int) $j['id'] . '/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm"
                       title="<?= $selesai ? 'Lembar hasil siap tanda tangan' : 'Belum selesai: PDF bertanda draf (bisa dicetak untuk diisi tangan)' ?>"><?= ikon('printer', 'h-4 w-4') ?>Cetak Lembar Hasil (PDF)</a>
                <?php endif; ?>
                <a href="<?= esc($daftar, 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke daftar</a>
            </div>
        </div>
        <dl class="kartu-isi grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ([
                ['Guru', $j['nama_guru'] . (($j['nip_guru'] ?? '') !== '' ? ' · NIP ' . $j['nip_guru'] : '')],
                ['Mata pelajaran / kelas', ($j['nama_mapel'] ?? '—') . ' / ' . ($j['nama_kelas'] ?? '—')],
                ['Hari, tanggal', Keterlambatan::hariTanggal((string) $j['tanggal']) . ($j['jam'] !== null ? ', jam ' . substr((string) $j['jam'], 0, 5) : '')],
                ['Supervisor', $j['nama_supervisor'] . (($j['jabatan_supervisor'] ?? '') !== '' ? ' (' . $j['jabatan_supervisor'] . ')' : '')],
            ] as [$label, $isi]): ?>
                <div class="min-w-0">
                    <dt class="text-gray-500 dark:text-gray-400"><?= esc($label) ?></dt>
                    <dd class="mt-0.5 font-medium text-gray-900 dark:text-white"><?= esc($isi) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>

    <?php if ($batal): ?>
        <?= ui_peringatan('galat', 'Kunjungan ini dibatalkan, jadi tidak bisa dinilai. Bila jadi dilaksanakan, buka Jadwal Supervisi → Ubah → matikan "Batalkan kunjungan ini".') ?>
    <?php elseif (! $kelola): ?>
        <?= ui_peringatan('info', 'Akun Anda hanya bisa melihat lembar ini (hak Lihat).') ?>
    <?php elseif ($selesai): ?>
        <?= ui_peringatan('sukses', 'Penilaian sudah selesai. Perubahan disimpan dengan tombol "Simpan perubahan".') ?>
    <?php endif; ?>

    <?php if ($lembar['instrumen'] === []): ?>
        <?= ui_kosong(['ikon' => 'clipboard-check', 'judul' => 'Jadwal ini belum punya instrumen', 'teks' => 'Ubah jadwalnya di Jadwal Supervisi dan centang instrumen yang dipakai.']) ?>
    <?php else: ?>
    <form method="post" action="<?= site_url('admin/supervisi/penilaian/' . (int) $j['id']) ?>" @submit="kirim">
        <?= csrf_field() ?>
        <input type="hidden" name="kembali" value="<?= esc($kembali, 'attr') ?>">
        <fieldset<?= $boleh ? '' : ' disabled' ?> class="kartu min-w-0">
            <div class="tab-daftar px-2 sm:px-4" role="tablist" aria-label="Instrumen">
                <?php foreach ($lembar['instrumen'] as $ins): $id = (string) $ins['id']; ?>
                    <button type="button" role="tab" id="tab-ins-<?= $id ?>" aria-controls="ins-<?= $id ?>" class="tab"
                            :aria-selected="tabIni('<?= $id ?>')" @click="pilihTab('<?= $id ?>')">
                        <?= esc(preg_replace('/\s*\(.*\)$/', '', $ins['nama'])) ?>
                        <span class="tab-jumlah" :class="kelasKemajuan('<?= $id ?>')" x-text="kemajuan('<?= $id ?>')"><?= count($ins['butir']) - $ins['ringkas']['kosong'] ?>/<?= count($ins['butir']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($lembar['instrumen'] as $ins): $id = (string) $ins['id']; $no = 0; $aspekLalu = null; ?>
                <section id="ins-<?= $id ?>" role="tabpanel" aria-labelledby="tab-ins-<?= $id ?>" data-instrumen="<?= $id ?>" data-nama="<?= esc($ins['nama'], 'attr') ?>"
                         x-show="tabIni('<?= $id ?>')"<?= $id === $tabAwal ? '' : ' x-cloak' ?> class="kartu-isi space-y-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white"><?= esc($ins['nama']) ?></h3>

                    <?php if ($ins['butir'] === []): ?>
                        <?= ui_peringatan('awas', 'Instrumen ini belum punya butir aktif. Isi butirnya di Instrumen Supervisi, atau cabut instrumen ini dari jadwal.') ?>
                    <?php else: ?>
                        <ol class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <?php foreach ($ins['butir'] as $b): $no++; $skor = (string) $nilaiIsian($ins, $b['kunci']); $cat = (string) $nilaiIsian($ins, $b['kunci'], 'catatan'); ?>
                                <?php if ($b['aspek'] !== $aspekLalu): $aspekLalu = $b['aspek']; ?>
                                    <li class="bg-gray-50 px-4 py-2 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:bg-gray-800 dark:text-gray-300"><?= esc($b['aspek']) ?></li>
                                <?php endif; ?>
                                <li class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-start lg:justify-between" data-butir>
                                    <div class="min-w-0 flex-1 text-sm">
                                        <p class="text-gray-900 dark:text-white"><span class="me-1 text-gray-500 dark:text-gray-400"><?= $no ?>.</span><?= esc($b['uraian']) ?></p>
                                        <details class="mt-1"<?= $cat !== '' ? ' open' : '' ?>>
                                            <summary class="cursor-pointer text-xs font-medium text-brand-700 dark:text-brand-400">Catatan</summary>
                                            <input type="text" name="nilai[<?= $id ?>][catatan][<?= esc($b['kunci'], 'attr') ?>]" value="<?= esc($cat, 'attr') ?>" maxlength="<?= Penilaian::MAKS_CATATAN ?>"
                                                   class="inp mt-1 text-sm" aria-label="Catatan butir <?= $no ?>" placeholder="Catatan singkat (opsional)">
                                        </details>
                                    </div>
                                    <div class="flex shrink-0 gap-1.5" role="radiogroup" aria-label="Skor butir <?= $no ?>">
                                        <?php foreach ($pilihan as $nilai => $label): ?>
                                            <label class="cursor-pointer" title="<?= esc($label, 'attr') ?>">
                                                <input type="radio" name="nilai[<?= $id ?>][skor][<?= esc($b['kunci'], 'attr') ?>]" value="<?= $nilai ?>" class="peer sr-only"<?= $skor === (string) $nilai ? ' checked' : '' ?>>
                                                <span class="flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 transition peer-checked:border-brand-700 peer-checked:bg-brand-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:peer-checked:border-brand-500 dark:peer-checked:bg-brand-600 <?= $nilai === 0 ? 'min-w-11' : 'w-9' ?>">
                                                    <?= $nilai === 0 ? 'TD' : $nilai ?><span class="sr-only"> <?= esc($label) ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                        <div class="grid gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-3 dark:bg-gray-800">
                            <p><span class="text-gray-500 dark:text-gray-400">Jumlah skor</span><br><b class="text-gray-900 dark:text-white" x-text="teksSkor('<?= $id ?>')"><?= $ins['ringkas']['total'] ?> dari <?= $ins['ringkas']['maks'] ?></b></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Nilai instrumen</span><br><b class="text-lg text-gray-900 dark:text-white" x-text="teksNilai('<?= $id ?>')"><?= esc(Penilaian::teksNilai($ins['ringkas']['nilai'])) ?></b></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Predikat</span><br><b class="text-gray-900 dark:text-white" x-text="teksPredikat('<?= $id ?>')"><?= esc($ins['ringkas']['predikat'] ?? '—') ?></b></p>
                        </div>
                    <?php endif; ?>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="lbl" for="kk-<?= $id ?>">Kekuatan</label>
                            <textarea id="kk-<?= $id ?>" name="nilai[<?= $id ?>][kekuatan]" rows="3" maxlength="<?= Penilaian::MAKS_TEKS ?>" class="inp" placeholder="Hal yang sudah baik"><?= esc((string) $nilaiIsian($ins, 'kekuatan', 'teks')) ?></textarea>
                        </div>
                        <div>
                            <label class="lbl" for="kl-<?= $id ?>">Kelemahan / yang perlu diperbaiki</label>
                            <textarea id="kl-<?= $id ?>" name="nilai[<?= $id ?>][kelemahan]" rows="3" maxlength="<?= Penilaian::MAKS_TEKS ?>" class="inp"><?= esc((string) $nilaiIsian($ins, 'kelemahan', 'teks')) ?></textarea>
                        </div>
                    </div>
                    <div>
                        <label class="lbl" for="rk-<?= $id ?>">Rekomendasi</label>
                        <textarea id="rk-<?= $id ?>" name="nilai[<?= $id ?>][rekomendasi]" rows="2" maxlength="<?= Penilaian::MAKS_TEKS ?>" class="inp"><?= esc((string) $nilaiIsian($ins, 'rekomendasi', 'teks')) ?></textarea>
                    </div>
                    <fieldset>
                        <legend class="lbl">Tindak lanjut</legend>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <?php $tl = $nilaiIsian($ins, '', 'tl'); foreach (Penilaian::TINDAK_LANJUT as $k => $label): ?>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="nilai[<?= $id ?>][tindak_lanjut][]" value="<?= $k ?>" data-tl class="cek"<?= in_array($k, $tl, true) ? ' checked' : '' ?>>
                                    <?= esc($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php if ($ins['dinilai_at'] !== null): ?>
                        <p class="hint">Terakhir disimpan <?= esc(date('d-m-Y H:i', strtotime((string) $ins['dinilai_at']))) ?><?= $ins['dinilai_oleh'] ? ' oleh ' . esc((string) $ins['dinilai_oleh']) : '' ?>.</p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <div class="kartu-kaki sticky bottom-0 z-10 flex flex-col gap-3 rounded-b-lg border-t border-gray-200 bg-white sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Nilai akhir kunjungan:</span>
                    <b class="ms-1 text-lg text-gray-900 dark:text-white" x-text="teksAkhir()"><?= esc(Penilaian::teksNilai($lembar['ringkas']['nilai'])) ?></b>
                    <span class="ms-1 text-gray-700 dark:text-gray-300" x-text="teksPredikatAkhir()"><?= esc($lembar['ringkas']['predikat'] ?? '') ?></span>
                    <p class="err-msg mt-1" x-show="pesan !== ''" x-text="pesan" x-cloak aria-live="polite"></p>
                </div>
                <?php if ($boleh): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php if (! $selesai): ?>
                            <button type="submit" name="aksi" value="draf" class="btn btn-netral"><?= ikon('file-lines', 'h-4 w-4') ?>Simpan draf</button>
                        <?php endif; ?>
                        <button type="submit" name="aksi" value="selesai" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><?= $selesai ? 'Simpan perubahan' : 'Simpan &amp; selesai' ?></button>
                    </div>
                <?php endif; ?>
            </div>
        </fieldset>
    </form>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/supervisi.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/supervisi.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
