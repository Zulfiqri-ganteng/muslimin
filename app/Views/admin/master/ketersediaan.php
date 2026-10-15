<?php
/**
 * Halaman Ketersediaan Guru — grid sel "tidak tersedia" per hari × jam (rombak tampilan F5: kartu template Flowbite; pemilih guru +
 * grup tombol shift; sel hijau = tersedia, merah = tidak tersedia). Komponen "ketPage" (master/ketersediaan.js) — isian Alpine hanya
 * panggilan metode berargumen teks/angka → siap Alpine versi CSP.
 *
 * @var array  $guruOpts    Opsi guru (id => label)
 * @var int    $guruId      Guru terpilih
 * @var string $shift       Shift aktif (pagi/siang)
 * @var array  $hari        Hari aktif terurut
 * @var array  $jam         Jam KBM shift aktif
 * @var array  $unavailable Set slot tidak tersedia (kunci "hariId-jamId")
 */
$jamIds = array_map(static fn ($j) => (int) $j['id'], $jam);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ketersediaan',
    'helpTitle' => 'Ketersediaan Guru',
    'helpBody'  => '<p>Tandai jam/hari di mana guru <b>TIDAK dapat</b> mengajar (mis. sedang kuliah, tugas lain). Pilih guru &amp; shift, lalu klik sel untuk mengubahnya. Sel hijau = tersedia, merah = tidak tersedia.</p>
        <p class="mt-1">Klik nama <b>hari</b> untuk menandai/menghapus satu kolom sekaligus. Data ini dipakai sistem agar tidak menempatkan guru pada jam yang tak tersedia (aturan bentrok). Jangan lupa <b>Simpan</b>.</p>',
]) ?>

<div x-data="ketPage" data-selected="<?= esc(json_encode(array_keys($unavailable)), 'attr') ?>" data-jam-ids="<?= esc(json_encode($jamIds), 'attr') ?>" class="space-y-4">

    <!-- Pemilih guru + shift -->
    <form method="get" class="kartu kartu-isi flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="text-sm font-medium text-gray-900 dark:text-white" for="ket-guru">Guru</label>
        <select id="ket-guru" name="guru_id" data-autosubmit class="inp sm:max-w-md">
            <?php if (empty($guruOpts)): ?>
                <option value="">— belum ada guru —</option>
            <?php else: foreach ($guruOpts as $id => $label): ?>
                <option value="<?= (int) $id ?>"<?= $guruId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; endif; ?>
        </select>
        <div class="grup-tombol sm:ms-auto" role="group" aria-label="Pilih shift">
            <?php foreach (['pagi' => 'Shift pagi', 'siang' => 'Shift siang'] as $key => $label): ?>
                <button type="submit" name="shift" value="<?= $key ?>" aria-pressed="<?= $shift === $key ? 'true' : 'false' ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if (! $guruId): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'users', 'judul' => 'Pilih guru terlebih dahulu', 'teks' => 'Pilih guru di atas (atau tambahkan guru di menu Master Guru).']) ?></div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada hari aktif atau jam pelajaran', 'teks' => 'Atur Master Hari dan Master Jam untuk shift ' . $shift . ' terlebih dahulu.']) ?></div>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/master/ketersediaan') ?>" class="kartu">
            <?= csrf_field() ?>
            <input type="hidden" name="guru_id" value="<?= (int) $guruId ?>">
            <input type="hidden" name="shift" value="<?= esc($shift, 'attr') ?>">

            <div class="kartu-kepala">
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded-sm border border-green-300 bg-green-100 dark:border-green-500/40 dark:bg-green-500/20"></span>Tersedia</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded-sm border border-red-300 bg-red-100 dark:border-red-500/40 dark:bg-red-500/20"></span>Tidak tersedia</span>
                    <span class="text-gray-500 dark:text-gray-400">(<b class="text-gray-900 dark:text-white" x-text="sel.length"></b> slot ditandai)</span>
                </div>
                <button type="button" class="btn btn-teks btn-sm" @click="clearAll()"><?= ikon('close', 'h-4 w-4') ?>Kosongkan semua</button>
            </div>

            <div class="tabel-bungkus">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-100 text-xs font-medium text-gray-600 uppercase dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="w-40 p-3 text-left">Jam</th>
                            <?php foreach ($hari as $h): ?>
                                <th scope="col" class="border-l border-gray-200 p-2 text-center dark:border-gray-600">
                                    <button type="button" class="rounded-md px-2 py-1 font-medium uppercase transition-colors hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white" @click="toggleDay(<?= (int) $h['id'] ?>)" title="Tandai / hapus satu hari"><?= esc($h['nama']) ?></button>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($jam as $j): ?>
                            <tr>
                                <td class="p-3 whitespace-nowrap">
                                    <span class="font-semibold text-gray-900 dark:text-white">Jam <?= esc($j['jam_ke']) ?></span>
                                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                </td>
                                <?php foreach ($hari as $h): $key = $h['id'] . '-' . $j['id']; ?>
                                    <td class="border-l border-gray-200 p-1 text-center dark:border-gray-700">
                                        <button type="button" @click="toggle('<?= $key ?>')" aria-label="<?= esc($h['nama'] . ' jam ' . $j['jam_ke'], 'attr') ?>"
                                                :class="has('<?= $key ?>') ? 'bg-red-100 text-red-700 ring-1 ring-red-300 dark:bg-red-500/20 dark:text-red-400 dark:ring-red-500/40' : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-500/10 dark:text-green-400 dark:hover:bg-green-500/20'"
                                                class="flex h-9 w-full items-center justify-center rounded-md transition-colors">
                                            <span x-show="!has('<?= $key ?>')"><?= ikon('check', 'h-4 w-4') ?></span>
                                            <span x-show="has('<?= $key ?>')" x-cloak><?= ikon('close', 'h-4 w-4') ?></span>
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="kartu-kaki flex justify-end">
                <!-- input tersembunyi untuk tiap slot tidak-tersedia -->
                <template x-for="key in sel" :key="key"><input type="hidden" name="unavailable[]" :value="key"></template>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan ketersediaan</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/ketersediaan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/ketersediaan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
