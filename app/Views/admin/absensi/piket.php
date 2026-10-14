<?php
/**
 * Jadwal guru piket per hari, terpisah KBM pagi & siang (rombak tampilan F6: kartu + tabel kit, lencana guru dengan tombol hapus).
 * Komponen Alpine "jadwalPiket" di assets/js/admin/absensi/piket.js (tanpa skrip sebaris; konfigurasi lewat data-cfg) → siap CSP.
 *
 * @var array $hari Hari aktif (id, nama)
 * @var array $grid [hari_id][pagi|siang] => [guru_id…]
 * @var array $guru Opsi guru [{id, nama}]
 */
$cfg = [
    'hari' => array_map(static fn ($h) => ['id' => (int) $h['id'], 'nama' => $h['nama']], $hari),
    'grid' => (object) $grid,
    'guru' => $guru,
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'piket_v1',
    'helpTitle' => 'Jadwal guru piket',
    'helpBody'  => '<p>Atur siapa yang <b>piket</b> tiap hari, terpisah untuk <b>KBM pagi</b> dan <b>KBM siang</b> (guru yang piket pagi saja cukup diisi di kolom Pagi). Pada halaman <b>Absensi Guru</b>, guru piket hari itu otomatis diisikan ke panel <b>Kehadiran Kerja</b> dan muncul di bagian <b>"Guru piket yang hadir"</b> pada pesan WhatsApp.</p>',
]) ?>

<?php if (empty($hari)): ?>
    <div class="kartu"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada hari aktif', 'teks' => 'Atur dulu di Master Data ▸ Hari.']) ?></div>
<?php else: ?>
    <form method="post" action="<?= site_url('admin/absensi/piket') ?>" class="kartu" x-data="jadwalPiket" data-cfg="<?= esc((string) json_encode($cfg), 'attr') ?>" @submit="siapkanKirim()">
        <?= csrf_field() ?>
        <input type="hidden" name="grid_json" x-ref="json">

        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th scope="col" class="w-32">Hari</th>
                        <th scope="col">Piket KBM pagi</th>
                        <th scope="col">Piket KBM siang</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="h in hari" :key="h.id">
                        <tr class="align-top">
                            <th scope="row" x-text="h.nama"></th>
                            <template x-for="sh in shift" :key="sh">
                                <td>
                                    <div class="mb-2 flex flex-wrap gap-1.5">
                                        <template x-for="gid in daftar(h.id, sh)" :key="gid">
                                            <span class="lencana lencana-merek gap-1 pe-1">
                                                <span x-text="nama(gid)"></span>
                                                <button type="button" class="rounded-full p-0.5 hover:bg-brand-200 dark:hover:bg-brand-800" @click="hapus(h.id, sh, gid)" aria-label="Hapus dari daftar piket"><?= ikon('close', 'h-3.5 w-3.5') ?></button>
                                            </span>
                                        </template>
                                        <span x-show="kosong(h.id, sh)" class="text-xs text-gray-400 dark:text-gray-500">— belum ada —</span>
                                    </div>
                                    <select data-tambah-piket :data-hari="h.id" :data-shift="sh" class="inp max-w-xs py-1.5 text-xs" aria-label="Tambah guru piket">
                                        <option value="">+ tambah guru piket…</option>
                                        <template x-for="g in guru" :key="g.id">
                                            <option :value="g.id" x-text="g.nama" :disabled="sudahAda(h.id, sh, g.id)"></option>
                                        </template>
                                    </select>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="kartu-kaki flex justify-end">
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan jadwal piket</button>
        </div>
    </form>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/absensi/piket.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/absensi/piket.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
