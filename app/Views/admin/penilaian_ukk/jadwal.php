<?php
/**
 * Matriks penilaian: peserta (baris) x penguji tertugas (kolom), 1 jadwal.
 *
 * @var array      $jadwal      jadwal_ukk::withRelations() satu baris
 * @var array|null $paket       paket_soal_ukk (untuk bobot & KKM)
 * @var array      $peserta     peserta_ukk::withRelations() milik jadwal ini
 * @var array      $pengujiList JadwalUkkPengujiModel::forJadwal()
 * @var array      $nilaiMap    "{pesertaId}-{tipe}-{pengujiId}" => baris nilai_ukk
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$num  = static fn ($v) => $v !== null ? number_format((float) $v, 1) : '';
$saveUrl = site_url('admin/penilaian-ukk/jadwal/' . (int) $jadwal['id'] . '/simpan');
$warnaStatus = [
    'terdaftar'   => 'bg-slate-100 text-slate-600 border-slate-200',
    'hadir'       => 'bg-sky-50 text-sky-700 border-sky-200',
    'tidak_hadir' => 'bg-amber-50 text-amber-700 border-amber-200',
    'lulus'       => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'tidak_lulus' => 'bg-red-50 text-red-700 border-red-200',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'penilaian_ukk_jadwal',
    'helpTitle' => 'Isi Nilai',
    'helpBody'  => '<p>Klik sel pada kolom penguji untuk mengisi 5 komponen nilai (Persiapan, Proses, Hasil Kerja,
        Sikap Kerja, Waktu — masing-masing 0-100). Nilai akhir per penguji dihitung otomatis sesuai bobot paket
        soal. Kolom <b>Nilai Akhir</b> peserta = rata-rata seluruh penguji yang sudah mengisi.</p>',
]) ?>

<a href="<?= site_url('admin/penilaian-ukk') ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mb-4">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali ke Penilaian UKK
</a>

<!-- Info jadwal -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-5">
    <h2 class="font-bold text-slate-800 text-lg"><?= esc($jadwal['paket_kode'] ?? '—') ?> — <?= esc($jadwal['paket_nama'] ?? '—') ?></h2>
    <p class="text-sm text-slate-500 mt-0.5">
        <?= $tgl($jadwal['tanggal_mulai']) ?><?= ! empty($jadwal['tempat_nama']) ? ' · ' . esc($jadwal['tempat_nama']) : '' ?>
        <?= $paket ? ' · KKM ' . esc((string) $paket['kkm']) : '' ?>
    </p>
</div>

<?php if (empty($pengujiList)): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-5 mb-5 text-sm">
        Belum ada penguji ditugaskan pada jadwal ini.
        <a href="<?= site_url('admin/jadwal-ukk/penguji/' . (int) $jadwal['id']) ?>" class="font-semibold underline">Tugaskan penguji dulu</a>.
    </div>
<?php elseif (empty($peserta)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400">
        Belum ada peserta terdaftar pada jadwal ini.
        <a href="<?= site_url('admin/peserta-ukk/daftarkan') ?>" class="text-brand-600 font-semibold hover:underline">Daftarkan peserta</a>.
    </div>
<?php else: ?>
    <div x-data="{ open:false, form:{}, label:'',
          openNilai(peserta_ukk_id, tipe, guru_id, penguji_eksternal_id, label, existing) {
              this.form = Object.assign({
                  peserta_ukk_id: peserta_ukk_id, tipe_penguji: tipe, guru_id: guru_id, penguji_eksternal_id: penguji_eksternal_id,
                  persiapan_skor: '', proses_skor: '', hasil_skor: '', sikap_skor: '', waktu_skor: '',
                  tanggal_nilai: '<?= date('Y-m-d') ?>', keterangan: ''
              }, existing || {});
              this.label = label;
              this.open = true;
          } }">

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="font-bold text-slate-800">Matriks Nilai (<?= count($peserta) ?> peserta)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Peserta</th>
                            <?php foreach ($pengujiList as $pg):
                                $pgLabel = $pg['tipe'] === 'internal' ? ($pg['guru_nama'] ?? '—') : ($pg['eksternal_nama'] ?? '—'); ?>
                                <th class="px-3 py-3 font-semibold text-center w-28">
                                    <?= esc($pgLabel) ?>
                                    <div class="text-[10px] font-normal text-slate-400"><?= $pg['tipe'] === 'internal' ? 'Internal' : 'Eksternal' ?></div>
                                </th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 font-semibold w-24 text-right">Nilai Akhir</th>
                            <th class="px-4 py-3 font-semibold w-28">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($peserta as $p): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3">
                                    <div class="font-medium text-slate-800"><?= esc($p['siswa_nama'] ?? '—') ?></div>
                                    <div class="text-xs text-slate-400 font-mono"><?= esc($p['no_peserta'] ?? '—') ?></div>
                                </td>
                                <?php foreach ($pengujiList as $pg):
                                    $pengujiId = (int) ($pg['tipe'] === 'internal' ? $pg['guru_id'] : $pg['penguji_eksternal_id']);
                                    $pgLabel   = $pg['tipe'] === 'internal' ? ($pg['guru_nama'] ?? '—') : ($pg['eksternal_nama'] ?? '—');
                                    $n         = $nilaiMap[$p['id'] . '-' . $pg['tipe'] . '-' . $pengujiId] ?? null;
                                    $cellLabel = ($p['siswa_nama'] ?? '') . ' — ' . $pgLabel;
                                    $existingJs = $n ? json_encode([
                                        'persiapan_skor' => $n['persiapan_skor'], 'proses_skor' => $n['proses_skor'],
                                        'hasil_skor' => $n['hasil_skor'], 'sikap_skor' => $n['sikap_skor'], 'waktu_skor' => $n['waktu_skor'],
                                        'tanggal_nilai' => $n['tanggal_nilai'], 'keterangan' => $n['keterangan'],
                                    ]) : '{}'; ?>
                                    <td class="px-3 py-3 text-center">
                                        <button type="button"
                                                @click="openNilai(<?= (int) $p['id'] ?>, '<?= $pg['tipe'] ?>', <?= $pg['tipe'] === 'internal' ? $pengujiId : 'null' ?>, <?= $pg['tipe'] === 'eksternal' ? $pengujiId : 'null' ?>, '<?= esc($cellLabel, 'js') ?>', <?= htmlspecialchars($existingJs, ENT_QUOTES) ?>)"
                                                class="<?= $n ? 'text-brand-700 font-semibold hover:underline' : 'text-slate-300 hover:text-brand-600 text-xs' ?>">
                                            <?= $n ? $num($n['nilai_akhir']) : 'Isi' ?>
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3 text-right font-semibold text-slate-700"><?= $p['nilai_akhir'] !== null ? $num($p['nilai_akhir']) : '—' ?></td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$p['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal isi nilai -->
        <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-bold text-lg text-slate-800 mb-1">Isi Nilai</h3>
                <p class="text-sm text-slate-500 mb-4" x-text="label"></p>
                <form method="post" action="<?= $saveUrl ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="peserta_ukk_id" x-model="form.peserta_ukk_id">
                    <input type="hidden" name="tipe_penguji" x-model="form.tipe_penguji">
                    <input type="hidden" name="guru_id" x-model="form.guru_id">
                    <input type="hidden" name="penguji_eksternal_id" x-model="form.penguji_eksternal_id">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Persiapan Kerja</label>
                            <input type="number" name="persiapan_skor" x-model="form.persiapan_skor" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Proses</label>
                            <input type="number" name="proses_skor" x-model="form.proses_skor" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Hasil Kerja</label>
                            <input type="number" name="hasil_skor" x-model="form.hasil_skor" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Sikap Kerja</label>
                            <input type="number" name="sikap_skor" x-model="form.sikap_skor" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Waktu</label>
                            <input type="number" name="waktu_skor" x-model="form.waktu_skor" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Nilai</label>
                            <input type="date" name="tanggal_nilai" x-model="form.tanggal_nilai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                            <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                    </div>
                    <?php if ($paket): ?>
                        <p class="text-xs text-slate-400 mt-3">Bobot: Persiapan <?= esc((string) $paket['bobot_persiapan']) ?>% ·
                            Proses <?= esc((string) $paket['bobot_proses']) ?>% · Hasil <?= esc((string) $paket['bobot_hasil']) ?>% ·
                            Sikap <?= esc((string) $paket['bobot_sikap']) ?>% · Waktu <?= esc((string) $paket['bobot_waktu']) ?>%</p>
                    <?php endif; ?>
                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                        <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan Nilai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
