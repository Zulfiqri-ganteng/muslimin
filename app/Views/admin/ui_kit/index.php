<?php
/**
 * Halaman ui-kit (rombak tampilan F1; sejak F3 memakai layouts/admin) — contoh hidup SEMUA kit komponen + cara pakainya, sekaligus
 * halaman uji CSP: grup 'kit' ada di Config\Tampilan::$alpineCsp, jadi layout memuat Alpine versi CSP di sini (kerangka ikut teruji).
 * KHUSUS LAPTOP (Admin\UiKit). ?uji=1 → uji-diri komponen + kerangka (public/assets/js/admin/ui-kit.js; dibaca tools/uji-peramban.mjs).
 *
 * @var string       $contohPager HTML paginasi contoh (templat pager "admin")
 * @var list<string> $ikonDaftar  nama ikon outline yang tersedia (resources/ikon.json → npm run vendor)
 * @var bool         $modeUji
 */
$kode = static fn (string $teks): string => '<code class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-800">' . esc($teks) . '</code>';
?>
<?= $this->extend('layouts/admin') ?>

<?php // Pemilih tema CONTOH (hanya halaman ini, disimpan di peramban); tema semua akun = Pengaturan Sekolah → Tampilan. ?>
<?= $this->section('aksi') ?>
<div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1" role="radiogroup" aria-label="Tema contoh" x-data="uiKitTema">
    <button type="button" role="radio" :aria-checked="tema === 'terang'" @click="pilih('terang')"
            class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-900 aria-checked:bg-white aria-checked:text-gray-900 aria-checked:shadow-xs">Flowbite Terang</button>
    <button type="button" role="radio" :aria-checked="tema === 'gelap'" @click="pilih('gelap')"
            class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-900 aria-checked:bg-white aria-checked:text-gray-900 aria-checked:shadow-xs">Flowbite Gelap</button>
    <button type="button" role="radio" :aria-checked="tema === 'lilac'" @click="pilih('lilac')"
            class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-900 aria-checked:bg-white aria-checked:text-gray-900 aria-checked:shadow-xs">Lilac Sekolah</button>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="mx-auto max-w-6xl space-y-6" x-data="uiKit"<?= $modeUji ? ' data-mode-uji="1"' : '' ?>>
    <?php if ($modeUji): ?>
        <div class="peringatan peringatan-info" role="status">
            <?= ikon('info-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
            <p>Mode uji-diri: komponen dicoba otomatis satu per satu. Hasil: <b x-text="hasilUji">berjalan…</b></p>
        </div>
    <?php endif; ?>

    <?= ui_peringatan('netral', '', ['judul' => 'Cara memakai halaman ini', 'html' =>
        '<p>Setiap kotak = satu komponen + cara pakainya. Kelas CSS di <b>resources/css/admin.css</b> (bagian KIT KOMPONEN), komponen Alpine di '
        . '<b>public/assets/js/admin/ui.js</b>, partial PHP lewat helper <b>ui_*()</b> (app/Helpers/ui_helper.php). Atribut Alpine hanya isian '
        . 'sederhana yang didukung versi CSP (rancangan §5a). Pemilih tema di kanan atas hanya <b>pratinjau di halaman ini</b> (menu samping & '
        . 'warna utama ikut berubah); tema untuk semua akun diatur Admin di <b>Pengaturan Sekolah → Tampilan</b>.</p>']) ?>

    <nav aria-label="Daftar isi" class="flex flex-wrap gap-2 text-sm">
        <?php foreach (['kepala' => 'Kepala halaman', 'tombol' => 'Tombol', 'formulir' => 'Formulir', 'kartu' => 'Kartu & tabel', 'lencana' => 'Lencana',
                        'tab' => 'Tab', 'dropdown' => 'Dropdown', 'dialog' => 'Dialog & laci', 'konfirmasi' => 'Konfirmasi', 'pesan' => 'Toast & peringatan',
                        'kosong' => 'Keadaan kosong', 'paginasi' => 'Paginasi', 'format' => 'Format & salin', 'ikon' => 'Ikon'] as $id => $label): ?>
            <a href="#<?= $id ?>" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100 hover:text-brand-700"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- ================= Kepala halaman ================= -->
    <section id="kepala" class="kartu kartu-isi scroll-mt-24">
        <?= ui_kepala([
            'tingkat' => 2, // contoh di dalam halaman: <h2> (judul <h1> halaman ini milik kepala layout)
            'judul' => 'Data Guru',
            'ket'   => 'Contoh kepala halaman: remah roti, judul, keterangan, tombol aksi, lalu tab.',
            'remah' => [['Data Master'], ['Guru']],
            'aksi'  => '<a href="#kepala" class="btn btn-netral">' . ikon('download', 'h-4 w-4') . 'Unduh Excel</a>'
                     . '<button type="button" class="btn btn-utama" data-buka-dialog="contoh-form">' . ikon('plus', 'h-4 w-4') . 'Tambah guru</button>',
            'tab'   => [
                ['label' => 'Aktif', 'url' => site_url('admin/ui-kit') . '#kepala', 'aktif' => true, 'jumlah' => 52],
                ['label' => 'Nonaktif', 'url' => site_url('admin/ui-kit') . '#kepala', 'jumlah' => 4],
                ['label' => 'Impor', 'url' => site_url('admin/ui-kit') . '#kepala', 'ikon' => 'file-lines'],
            ],
        ]) ?>
        <p class="hint">Pakai: <?= $kode("<?= ui_kepala(['judul' => 'Data Guru', 'ket' => '…', 'remah' => [['Data Master'], ['Guru']], 'aksi' => '<a class=\"btn btn-utama\" …>', 'tab' => [...]]) ?>") ?>
            — <?= $kode("'tingkat' => 2") ?> = judul &lt;h2&gt; (kepala di dalam halaman yang sudah ber-&lt;h1&gt;); remah saja: <?= $kode('ui_remah([...])') ?>, tab tautan saja: <?= $kode("ui_tab([['label' => …, 'url' => …, 'aktif' => true, 'jumlah' => 3]])") ?>.</p>
    </section>

    <!-- ================= Tombol ================= -->
    <section id="tombol" class="kartu scroll-mt-24">
        <div class="kartu-kepala"><div><h2 class="kartu-judul">Tombol</h2><p class="kartu-ket">Satu tombol utama per kelompok; aksi lain netral. Merah hanya untuk menghapus.</p></div></div>
        <div class="kartu-isi space-y-5">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-utama">Simpan</button>
                <button type="button" class="btn btn-netral">Batal</button>
                <button type="button" class="btn btn-sukses">Setujui</button>
                <button type="button" class="btn btn-bahaya">Hapus</button>
                <button type="button" class="btn btn-teks">Lewati</button>
                <a href="#tombol" class="btn btn-netral">Tautan bergaya tombol</a>
                <button type="button" class="btn btn-utama" disabled>Nonaktif</button>
                <button type="button" class="btn btn-utama" disabled><span class="pemutar" aria-hidden="true"></span>Memproses…</button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-utama btn-lg">Besar</button>
                <button type="button" class="btn btn-utama">Biasa</button>
                <button type="button" class="btn btn-utama btn-sm">Kecil</button>
                <button type="button" class="btn btn-utama btn-xs">Sangat kecil</button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-utama"><?= ikon('plus', 'h-4 w-4') ?>Tambah</button>
                <button type="button" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh</button>
                <button type="button" class="btn btn-netral btn-ikon" aria-label="Ubah"><?= ikon('edit', 'h-4 w-4') ?></button>
                <button type="button" class="btn btn-bahaya btn-ikon" aria-label="Hapus"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                <button type="button" class="btn btn-teks btn-ikon btn-sm" aria-label="Menu lain"><?= ikon('dots-vertical', 'h-4 w-4') ?></button>
            </div>
            <p class="hint"><?= $kode('class="btn btn-utama"') ?> · varian: <?= $kode('btn-netral btn-sukses btn-bahaya btn-teks') ?> · ukuran: <?= $kode('btn-lg btn-sm btn-xs') ?> · hanya ikon: <?= $kode('btn-ikon') ?> + <?= $kode('aria-label') ?> · tanda memuat: <?= $kode('<span class="pemutar" aria-hidden="true"></span>') ?> · ikon: <?= $kode("<?= ikon('plus', 'h-4 w-4') ?>") ?></p>
        </div>
    </section>

    <!-- ================= Formulir ================= -->
    <section id="formulir" class="kartu scroll-mt-24">
        <div class="kartu-kepala"><div><h2 class="kartu-judul">Formulir</h2><p class="kartu-ket">Gaya isian Flowbite: latar abu muda, garis abu, cincin fokus warna utama.</p></div></div>
        <div class="kartu-isi">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="uk-nama" class="lbl">Nama lengkap</label>
                    <input type="text" id="uk-nama" class="inp" placeholder="mis. Siti Aminah">
                    <p class="hint">Teks bantuan di bawah isian.</p>
                </div>
                <div>
                    <label for="uk-nip" class="lbl">NIP</label>
                    <input type="text" id="uk-nip" class="inp inp-err" value="12ab" aria-invalid="true" aria-describedby="uk-nip-galat">
                    <p id="uk-nip-galat" class="err-msg">NIP hanya boleh berisi angka.</p>
                </div>
                <div>
                    <label for="uk-kelas" class="lbl">Kelas</label>
                    <select id="uk-kelas" class="inp">
                        <option>X TKJ 1</option><option>X TKJ 2</option><option>XI Akuntansi dan Keuangan Lembaga 1 (nama panjang)</option>
                    </select>
                </div>
                <div>
                    <label for="uk-tanggal" class="lbl">Tanggal</label>
                    <input type="date" id="uk-tanggal" class="inp" value="2026-10-14">
                </div>
                <div>
                    <label for="uk-nonaktif" class="lbl">Tidak bisa diubah</label>
                    <input type="text" id="uk-nonaktif" class="inp" value="Terkunci" disabled>
                </div>
                <div x-data="isianSandi">
                    <label for="uk-sandi" class="lbl">Kata sandi</label>
                    <div class="isian-sandi">
                        <input type="password" :type="lihat ? 'text' : 'password'" id="uk-sandi" class="inp" value="contoh-sandi" autocomplete="off"
                               @keydown="cekCapsLock($event)" @keyup="cekCapsLock($event)" @blur="capsLock = false">
                        <button type="button" class="tombol-sandi" data-uji="tombol-sandi" x-cloak @mousedown.prevent @click="alih()"
                                aria-controls="uk-sandi" aria-label="Tampilkan kata sandi" :aria-pressed="lihat" :title="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <span x-show="!lihat"><?= ikon('eye', 'h-5 w-5') ?></span>
                            <span x-show="lihat"><?= ikon('eye-slash', 'h-5 w-5') ?></span>
                        </button>
                    </div>
                    <p class="hint flex items-center gap-1.5 font-medium text-yellow-700" data-uji="caps-lock" x-show="capsLock" x-cloak role="status"><?= ikon('exclamation-circle', 'h-4 w-4 shrink-0') ?>Caps Lock sedang menyala.</p>
                </div>
                <div>
                    <label for="uk-berkas" class="lbl">Berkas Excel</label>
                    <input type="file" id="uk-berkas" class="inp-berkas" accept=".xlsx">
                </div>
                <div class="sm:col-span-2">
                    <label for="uk-catatan" class="lbl">Catatan</label>
                    <textarea id="uk-catatan" rows="3" class="inp" placeholder="Tulis catatan…"></textarea>
                </div>
                <fieldset class="space-y-2">
                    <legend class="lbl">Pilihan (checkbox &amp; radio)</legend>
                    <div class="flex items-center"><input id="uk-c1" type="checkbox" class="cek" checked><label for="uk-c1" class="cek-label">Kirim kabar WhatsApp</label></div>
                    <div class="flex items-center"><input id="uk-c2" type="checkbox" class="cek"><label for="uk-c2" class="cek-label">Tampilkan siswa keluar</label></div>
                    <div class="flex items-center"><input id="uk-r1" type="radio" name="uk-r" class="cek" checked><label for="uk-r1" class="cek-label">Semester ganjil</label></div>
                    <div class="flex items-center"><input id="uk-r2" type="radio" name="uk-r" class="cek"><label for="uk-r2" class="cek-label">Semester genap</label></div>
                </fieldset>
                <div class="space-y-3">
                    <p class="lbl">Saklar</p>
                    <label class="saklar"><input type="checkbox" class="peer sr-only" checked><span class="saklar-jalur"></span><span class="saklar-label">Form publik dibuka</span></label>
                    <label class="saklar"><input type="checkbox" class="peer sr-only"><span class="saklar-jalur"></span><span class="saklar-label">Mode lihat saja</span></label>
                </div>
            </div>
            <p class="hint">Label <?= $kode('class="lbl"') ?> · isian/select/textarea <?= $kode('class="inp"') ?> (galat: + <?= $kode('inp-err') ?> dan <?= $kode('<p class="err-msg">') ?>) · bantuan <?= $kode('class="hint"') ?> · kata sandi + tombol lihat: <?= $kode('<div x-data="isianSandi"> label + <div class="isian-sandi"><input :type="lihat ? \'text\' : \'password\'" …><button class="tombol-sandi" @mousedown.prevent @click="alih()" :aria-pressed="lihat">') ?> + peringatan <?= $kode('x-show="capsLock"') ?> (contoh lengkap: admin/login.php) · berkas <?= $kode('class="inp-berkas"') ?> · checkbox/radio <?= $kode('class="cek"') ?> + <?= $kode('class="cek-label"') ?> · saklar <?= $kode('<label class="saklar"><input type="checkbox" class="peer sr-only"><span class="saklar-jalur"></span>…') ?></p>
        </div>
    </section>

    <!-- ================= Kartu & tabel ================= -->
    <section id="kartu" class="kartu scroll-mt-24">
        <div class="kartu-kepala">
            <div><h2 class="kartu-judul">Daftar guru <span class="font-normal text-gray-400">(3)</span></h2><p class="kartu-ket">Kartu berkepala + tabel + kaki berisi paginasi.</p></div>
            <button type="button" class="btn btn-utama btn-sm"><?= ikon('plus', 'h-4 w-4') ?>Tambah</button>
        </div>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th scope="col" class="w-4"><input type="checkbox" class="cek" aria-label="Pilih semua"></th>
                        <th scope="col">Nama</th>
                        <th scope="col">Mapel</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ([['Ahmad Fauzi, S.Kom.', 'Dasar-dasar TJKT', 'Hadir', 'hijau'], ['Dewi Lestari, S.Pd.', 'Bahasa Indonesia', 'Menunggu', 'kuning'], ['Rudi Hartono', 'Matematika', 'Alpa', 'merah']] as [$nama, $mapel, $status, $warna]): ?>
                        <tr>
                            <td><input type="checkbox" class="cek" aria-label="Pilih <?= esc($nama, 'attr') ?>"></td>
                            <th scope="row"><?= esc($nama) ?></th>
                            <td><?= esc($mapel) ?></td>
                            <td><span class="lencana lencana-<?= $warna ?>"><?= esc($status) ?></span></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" class="btn btn-netral btn-ikon btn-sm" aria-label="Ubah <?= esc($nama, 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?></button>
                                <button type="button" class="btn btn-netral btn-ikon btn-sm text-red-600 hover:text-red-700" aria-label="Hapus <?= esc($nama, 'attr') ?>"
                                        data-confirm="Hapus <?= esc($nama, 'attr') ?> dari daftar contoh?" data-confirm-jenis="bahaya" data-confirm-ya="Ya, hapus" @click="hapusContoh()"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="kartu-kaki"><?= $contohPager ?></div>
        <div class="kartu-isi border-t border-gray-200">
            <p class="hint mt-0">Kartu: <?= $kode('class="kartu"') ?> + <?= $kode('kartu-kepala / kartu-isi / kartu-kaki') ?>, judul <?= $kode('kartu-judul') ?> + <?= $kode('kartu-ket') ?>. Tabel: <?= $kode('<div class="tabel-bungkus"><table class="tabel">') ?> — kolom utama pakai <?= $kode('<th scope="row">') ?>. Dropdown di dalam tabel akan terpotong (wadahnya bergulir) → pakai tombol ikon.</p>
        </div>
    </section>

    <!-- ================= Lencana ================= -->
    <section id="lencana" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Lencana</h2>
        <div class="flex flex-wrap gap-2">
            <span class="lencana lencana-hijau">Disetujui</span>
            <span class="lencana lencana-kuning">Menunggu</span>
            <span class="lencana lencana-merah">Ditolak</span>
            <span class="lencana lencana-abu">Draf</span>
            <span class="lencana lencana-biru">Info</span>
            <span class="lencana lencana-merek">Warna tema</span>
            <span class="lencana lencana-abu"><?= ikon('calendar-month', 'h-3.5 w-3.5') ?>14 Okt</span>
        </div>
        <p class="hint"><?= $kode('class="lencana lencana-hijau"') ?> · warna status tetap: hijau = hadir/disetujui, kuning = menunggu, merah = ditolak/alpa, abu = draf; merek = ikut tema; biru = info.</p>
    </section>

    <!-- ================= Tab ================= -->
    <section id="tab" class="kartu kartu-isi scroll-mt-24 space-y-4" x-data="tab" data-awal="ringkasan" data-hash="1">
        <h2 class="kartu-judul">Tab (di sisi peramban)</h2>
        <div class="tab-daftar" role="tablist" aria-label="Contoh tab" @keydown.right.prevent="pindah(1)" @keydown.left.prevent="pindah(-1)">
            <button type="button" role="tab" class="tab" data-tab="ringkasan" data-uji="tab-1" :aria-selected="aktif === 'ringkasan'" :tabindex="aktif === 'ringkasan' ? '0' : '-1'" @click="pilih('ringkasan')">Ringkasan</button>
            <button type="button" role="tab" class="tab" data-tab="rincian" data-uji="tab-2" :aria-selected="aktif === 'rincian'" :tabindex="aktif === 'rincian' ? '0' : '-1'" @click="pilih('rincian')">Rincian <span class="tab-jumlah">12</span></button>
            <button type="button" role="tab" class="tab" data-tab="riwayat" :aria-selected="aktif === 'riwayat'" :tabindex="aktif === 'riwayat' ? '0' : '-1'" @click="pilih('riwayat')">Riwayat</button>
        </div>
        <div role="tabpanel" data-uji="panel-1" x-show="aktif === 'ringkasan'"><p class="text-sm text-gray-600">Isi tab Ringkasan. Pindah tab dengan klik atau panah kiri/kanan.</p></div>
        <div role="tabpanel" data-uji="panel-2" x-show="aktif === 'rincian'" x-cloak><p class="text-sm text-gray-600">Isi tab Rincian (12 baris).</p></div>
        <div role="tabpanel" x-show="aktif === 'riwayat'" x-cloak><p class="text-sm text-gray-600">Isi tab Riwayat.</p></div>
        <p class="hint"><?= $kode('<div x-data="tab" data-awal="ringkasan"> … <button type="button" role="tab" class="tab" data-tab="ringkasan" :aria-selected="aktif === \'ringkasan\'" @click="pilih(\'ringkasan\')">') ?> + panel <?= $kode('x-show="aktif === \'ringkasan\'"') ?>; <?= $kode('data-hash="1"') ?> = tab ikut alamat (#rincian). Tab berupa tautan (pindah halaman): <?= $kode('ui_tab([...])') ?>.</p>
    </section>

    <!-- ================= Dropdown ================= -->
    <section id="dropdown" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Dropdown</h2>
        <div class="relative inline-block" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
            <button type="button" class="btn btn-netral" x-ref="pemicu" data-uji="dropdown" @click="alih()" :aria-expanded="buka" aria-haspopup="true">Aksi lain <?= ikon('chevron-down', 'h-4 w-4') ?></button>
            <div class="dropdown-menu" data-uji="dropdown-menu" x-show="buka" x-cloak x-transition.origin.top.left.duration.150ms>
                <div class="dropdown-kepala"><p class="text-sm font-medium">Ahmad Fauzi</p><p class="truncate text-xs text-gray-500">ahmad@sekolah.sch.id</p></div>
                <ul class="py-1">
                    <li><a href="#dropdown" class="dropdown-item" @click="tutup()"><?= ikon('edit', 'h-4 w-4') ?>Ubah</a></li>
                    <li><a href="#dropdown" class="dropdown-item" @click="tutup()"><?= ikon('download', 'h-4 w-4') ?>Unduh</a></li>
                </ul>
                <ul class="py-1">
                    <li><button type="button" class="dropdown-item dropdown-item-bahaya" @click="tutup()"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</button></li>
                </ul>
            </div>
        </div>
        <p class="hint"><?= $kode('<div class="relative inline-block" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">') ?> · pemicu <?= $kode('x-ref="pemicu" @click="alih()" :aria-expanded="buka"') ?> · menu <?= $kode('<div class="dropdown-menu" x-show="buka" x-cloak>') ?> (rata kanan: + <?= $kode('right-0') ?>) · butir <?= $kode('class="dropdown-item"') ?>.</p>
    </section>

    <!-- ================= Dialog & laci ================= -->
    <section id="dialog" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Dialog &amp; laci</h2>
        <div class="flex flex-wrap gap-3">
            <button type="button" class="btn btn-utama" data-buka-dialog="contoh-form">Buka dialog formulir</button>
            <button type="button" class="btn btn-netral" data-buka-dialog="contoh-laci" data-uji="buka-laci">Buka laci saring</button>
        </div>
        <p class="hint">Dialog: <?= $kode("<?= ui_dialog_buka(['nama' => 'tambah-guru', 'judul' => 'Tambah Guru', 'ukuran' => 'lg']) ?> <form …><div class=\"dialog-isi\">…</div><div class=\"dialog-kaki\">…</div></form> <?= ui_dialog_tutup() ?>") ?>.
            Laci: <?= $kode("ui_laci_buka(['nama' => 'saring', 'judul' => 'Saring data'])") ?> + <?= $kode('laci-isi / laci-kaki') ?> + <?= $kode('ui_laci_tutup()') ?>.
            Membuka: <?= $kode('<button type="button" data-buka-dialog="tambah-guru">') ?>, dari komponen <?= $kode("\$dispatch('buka-dialog', { nama: 'tambah-guru' })") ?>, dari JS <?= $kode("Ui.bukaDialog('tambah-guru')") ?>. Menutup: <?= $kode('@click="tutup()"') ?>, Esc, klik latar. Form yang gagal disimpan: <?= $kode("'awalBuka' => true") ?>.</p>
    </section>

    <?= ui_dialog_buka(['nama' => 'contoh-form', 'judul' => 'Tambah guru']) ?>
        <form @submit.prevent="kirimDialog()">
            <div class="dialog-isi">
                <div>
                    <label for="uk-d-nama" class="lbl">Nama lengkap</label>
                    <input type="text" id="uk-d-nama" class="inp" placeholder="mis. Siti Aminah" autocomplete="off">
                </div>
                <div>
                    <label for="uk-d-status" class="lbl">Status</label>
                    <select id="uk-d-status" class="inp"><option>Aktif</option><option>Nonaktif</option></select>
                </div>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                <button type="submit" class="btn btn-utama">Simpan</button>
            </div>
        </form>
    <?= ui_dialog_tutup() ?>

    <?= ui_laci_buka(['nama' => 'contoh-laci', 'judul' => 'Saring data']) ?>
        <div class="laci-isi">
            <div>
                <label for="uk-l-kelas" class="lbl">Kelas</label>
                <select id="uk-l-kelas" class="inp"><option>Semua kelas</option><option>X TKJ 1</option></select>
            </div>
            <div class="flex items-center"><input id="uk-l-c" type="checkbox" class="cek"><label for="uk-l-c" class="cek-label">Hanya yang belum lengkap</label></div>
        </div>
        <div class="laci-kaki">
            <button type="button" class="btn btn-utama" @click="tutup()">Terapkan</button>
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        </div>
    <?= ui_laci_tutup() ?>

    <!-- ================= Konfirmasi ================= -->
    <section id="konfirmasi" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Dialog konfirmasi <span class="font-normal text-gray-400">(pengganti confirm())</span></h2>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-bahaya" data-uji="tombol-konfirmasi" data-confirm="Hapus data contoh ini? Data yang dihapus tidak bisa dikembalikan." data-confirm-judul="Hapus data" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya" @click="tambahHitung()"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus (tombol)</button>
            <form data-uji="form-konfirmasi" data-confirm="Kirim formulir contoh ini?" @submit.prevent="kirimContoh()">
                <button type="submit" class="btn btn-utama" data-uji="kirim-konfirmasi">Kirim (formulir)</button>
            </form>
            <a href="#konfirmasi" class="btn btn-netral" data-confirm="Pindah ke bagian ini?">Tautan</a>
            <button type="button" class="btn btn-netral" @click="tanyaDariJs()">Dari berkas JS</button>
        </div>
        <p class="text-sm text-gray-600">Tombol hapus disetujui: <b data-uji="hitung-konfirmasi" x-text="jumlahHapus">0</b> kali · formulir terkirim: <b data-uji="hitung-kirim" x-text="jumlahKirim">0</b> kali</p>
        <p class="hint"><?= $kode('data-confirm="Hapus data ini?"') ?> pada <?= $kode('<a>') ?>/<?= $kode('<button>') ?> atau <?= $kode('<form>') ?> (+ <?= $kode('data-confirm-judul') ?>, <?= $kode('data-confirm-ya') ?>, <?= $kode('data-confirm-jenis="bahaya"') ?>) — aksi aslinya (tautan, kirim form, <?= $kode('@click') ?>) baru jalan setelah "Ya". Dari JS: <?= $kode("Ui.konfirmasi({ judul, pesan, ya, jenis }).then(…)") ?>. Dialognya dipasang sekali per halaman: <?= $kode('<?= ui_konfirmasi() ?>') ?>.</p>
    </section>

    <!-- ================= Toast & peringatan ================= -->
    <section id="pesan" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Toast &amp; peringatan</h2>
        <div class="flex flex-wrap gap-3">
            <button type="button" class="btn btn-netral" @click="$toast('Data guru tersimpan.', 'sukses')">Toast sukses</button>
            <button type="button" class="btn btn-netral" @click="$toast('Gagal menyimpan: koneksi terputus.', 'galat')">Toast galat</button>
            <button type="button" class="btn btn-netral" @click="$toast('3 siswa belum punya nomor HP.', 'awas')">Toast awas</button>
            <button type="button" class="btn btn-netral" @click="$toast('Laporan sedang disiapkan.', 'info')">Toast info</button>
        </div>
        <?= ui_peringatan('info', 'Form biodata ditutup otomatis pada 30 Oktober 2026.') ?>
        <?= ui_peringatan('sukses', 'Data siswa berhasil diimpor: 36 baris.', ['tutup' => true]) ?>
        <div data-uji="peringatan-tutup"><?= ui_peringatan('galat', ['Baris 4: NIS kosong.', 'Baris 9: kelas "X TKJ 9" tidak ada.'], ['judul' => 'Impor gagal', 'tutup' => true]) ?></div>
        <?= ui_peringatan('awas', 'Tahun pelajaran 2026/2027 belum punya data SKBM.', ['judul' => 'Perlu tindakan']) ?>
        <p class="hint">Toast dari atribut: <?= $kode("@click=\"\$toast('Tersimpan.', 'sukses')\"") ?>, dari JS: <?= $kode("Ui.toast('Tersimpan.', 'sukses')") ?> (jenis: sukses, galat, awas, info). Wadahnya sekali per halaman: <?= $kode('<?= ui_toast() ?>') ?>.
            Peringatan: <?= $kode("<?= ui_peringatan('sukses', 'Teks', ['judul' => '…', 'tutup' => true]) ?>") ?>; pesan boleh berupa daftar (array).</p>
    </section>

    <!-- ================= Keadaan kosong ================= -->
    <section id="kosong" class="kartu scroll-mt-24">
        <?= ui_kosong([
            'judul' => 'Belum ada data guru',
            'teks'  => 'Tambahkan guru satu per satu atau impor dari berkas Excel sekolah.',
            'ikon'  => 'users',
            'aksi'  => '<button type="button" class="btn btn-utama btn-sm" data-buka-dialog="contoh-form">' . ikon('plus', 'h-4 w-4') . 'Tambah guru</button>'
                     . '<a href="#kosong" class="btn btn-netral btn-sm">Impor Excel</a>',
        ]) ?>
        <div class="kartu-kaki"><p class="hint mt-0"><?= $kode("<?= ui_kosong(['judul' => '…', 'teks' => '…', 'ikon' => 'users', 'aksi' => '<a class=\"btn …\">…</a>']) ?>") ?></p></div>
    </section>

    <!-- ================= Paginasi ================= -->
    <section id="paginasi" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Paginasi</h2>
        <?= $contohPager ?>
        <p class="hint">Templat pager <?= $kode('admin') ?>: <?= $kode("\$pager->only(['q', 'per'])->links('default', 'admin')") ?>. Halaman publik memakai templat <?= $kode('publik') ?> (gaya lama). Paginasi buatan JS: <?= $kode('<ul class="paginasi"><li><a class="paginasi-item" aria-current="page">') ?>.</p>
    </section>

    <!-- ================= Format & salin ================= -->
    <section id="format" class="kartu scroll-mt-24">
        <div class="kartu-kepala"><div><h2 class="kartu-judul">Pembantu format &amp; salin teks</h2><p class="kartu-ket">Magic Alpine pengganti fungsi global (rupiah, tglIndo, periodeTeks) — hasilnya sama dengan sisi server.</p></div></div>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead><tr><th scope="col">Pemanggilan di atribut</th><th scope="col">Hasil</th></tr></thead>
                <tbody>
                    <tr><td><?= $kode("x-text=\"\$rupiah(1500000)\"") ?></td><td data-harap="Rp 1.500.000" x-text="$rupiah(1500000)"></td></tr>
                    <tr><td><?= $kode("\$rupiah(-2500)") ?></td><td data-harap="Rp -2.500" x-text="$rupiah(-2500)"></td></tr>
                    <tr><td><?= $kode("\$angka(1234567.891, 2)") ?></td><td data-harap="1.234.567,89" x-text="$angka(1234567.891, 2)"></td></tr>
                    <tr><td><?= $kode("\$tglIndo('2026-10-14')") ?></td><td data-harap="14 Oktober 2026" x-text="$tglIndo('2026-10-14')"></td></tr>
                    <tr><td><?= $kode("\$tglIndo('2026-02-30') (tanggal tak sah)") ?></td><td data-harap="" x-text="$tglIndo('2026-02-30')"></td></tr>
                    <tr><td><?= $kode("\$hariTanggal('2026-10-14')") ?></td><td data-harap="Rabu, 14 Oktober 2026" x-text="$hariTanggal('2026-10-14')"></td></tr>
                    <tr><td><?= $kode("\$periodeTeks('2026-07-01', '2026-07-31')") ?></td><td data-harap="1 s.d. 31 Juli 2026" x-text="$periodeTeks('2026-07-01', '2026-07-31')"></td></tr>
                    <tr><td><?= $kode("\$periodeTeks('2026-07-01', '2026-09-30')") ?></td><td data-harap="1 Juli s.d. 30 September 2026" x-text="$periodeTeks('2026-07-01', '2026-09-30')"></td></tr>
                    <tr><td><?= $kode("\$periodeTeks('2026-12-15', '2027-01-15')") ?></td><td data-harap="15 Desember 2026 s.d. 15 Januari 2027" x-text="$periodeTeks('2026-12-15', '2027-01-15')"></td></tr>
                    <tr><td><?= $kode("\$bulanTahun('2026-10')") ?></td><td data-harap="Oktober 2026" x-text="$bulanTahun('2026-10')"></td></tr>
                </tbody>
            </table>
        </div>
        <div class="kartu-isi grid gap-5 border-t border-gray-200 sm:grid-cols-2">
            <div>
                <label for="uk-angka" class="lbl">Coba angka</label>
                <input type="number" id="uk-angka" class="inp" x-model="cobaNilai">
                <p class="hint">Rupiah: <b class="text-gray-900" x-text="$rupiah(cobaNilai)"></b></p>
            </div>
            <div>
                <label for="uk-tautan" class="lbl">Salin teks</label>
                <div class="flex gap-2">
                    <input type="text" id="uk-tautan" class="inp" value="https://kangmuslim.com/isi" readonly>
                    <button type="button" class="btn btn-netral shrink-0" x-data="salinTeks" data-sasaran="#uk-tautan" @click="salin()">
                        <span class="inline-flex items-center gap-2" x-show="!tersalin"><?= ikon('file-copy', 'h-4 w-4') ?>Salin</span>
                        <span class="inline-flex items-center gap-2" x-show="tersalin" x-cloak><?= ikon('check', 'h-4 w-4') ?>Tersalin</span>
                    </button>
                </div>
                <p class="hint"><?= $kode('<button type="button" x-data="salinTeks" data-sasaran="#id" @click="salin()">') ?> atau <?= $kode('data-teks="…"') ?>; <?= $kode('x-show="tersalin"') ?> untuk label "Tersalin".</p>
            </div>
        </div>
    </section>

    <!-- ================= Ikon ================= -->
    <section id="ikon" class="kartu kartu-isi scroll-mt-24 space-y-4">
        <h2 class="kartu-judul">Ikon tersedia <span class="font-normal text-gray-400">(<?= count($ikonDaftar) ?>)</span></h2>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            <?php foreach ($ikonDaftar as $nama): ?>
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600"><?= ikon($nama, 'h-5 w-5 shrink-0 text-gray-700') ?><span class="truncate"><?= esc($nama) ?></span></div>
            <?php endforeach; ?>
        </div>
        <p class="hint"><?= $kode("<?= ikon('users', 'h-5 w-5') ?>") ?> — ikon baru: tambah namanya di <?= $kode('resources/ikon.json') ?> lalu <?= $kode('npm run vendor') ?> (nama: flowbite.com/icons).</p>
    </section>
</div>
<?= $this->endSection() ?>

<?php // Wadah toast & dialog konfirmasi disediakan layout; ui.js, app.js, dan Alpine (versi CSP untuk grup 'kit') dimuat layout. ?>
<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/ui-kit.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ui-kit.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
