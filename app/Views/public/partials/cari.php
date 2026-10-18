<?php
/**
 * Kotak cari portal dengan saran (komponen cariPortal; daftar saran dari #data-saran-portal di layout). Tanpa JS tetap jalan:
 * formulir GET ke /cari. Dipakai di kepala (layar lebar & HP) dan halaman /cari (versi besar).
 *
 * @var string      $idCari   id unik formulir (dipakai id isian & daftar saran)
 * @var string|null $nilai    isi awal (halaman /cari)
 * @var bool|null   $besar    versi besar (halaman /cari)
 */
$idCari = (string) ($idCari ?? 'cari');
$nilai  = (string) ($nilai ?? '');
?>
<form id="<?= esc($idCari, 'attr') ?>" class="cari<?= ! empty($besar) ? ' cari-besar' : '' ?>" role="search" aria-label="Cari layanan sekolah"
      action="<?= site_url('cari') ?>" method="get" x-data="cariPortal" @submit="kirim($event)" @focusout="tutupNanti" @keydown.escape="tutup">
    <label for="<?= esc($idCari, 'attr') ?>-q" class="sr-only">Cari layanan, kelas, atau nama guru</label>
    <?= ikon('search', 'cari-ikon') ?>
    <input id="<?= esc($idCari, 'attr') ?>-q" type="search" name="q" value="<?= esc($nilai, 'attr') ?>" class="cari-isian" maxlength="100" autocomplete="off"
           placeholder="Contoh: jadwal kelas, PKL, biodata" role="combobox" aria-autocomplete="list" aria-controls="<?= esc($idCari, 'attr') ?>-saran"
           :aria-expanded="buka" :aria-activedescendant="idAktif" x-model="q" @input="saring" @keydown.down.prevent="turun" @keydown.up.prevent="naik">
    <button type="submit" class="cari-tombol">Cari</button>
    <div id="<?= esc($idCari, 'attr') ?>-saran" class="cari-saran" role="listbox" aria-label="Saran pencarian" x-show="buka" x-cloak>
        <template x-for="(s, i) in hasil" :key="s.url">
            <a class="cari-saran-item" :href="s.url" :id="idButir(i)" role="option" :aria-selected="i === aktif">
                <?= ikon('arrow-right', 'h-5 w-5') ?>
                <span class="min-w-0"><span class="block truncate font-medium" x-text="s.judul"></span><span class="cari-saran-ket truncate" x-text="s.ket"></span></span>
            </a>
        </template>
        <a class="cari-saran-semua" :href="alamatCari"><?= ikon('search', 'h-4 w-4') ?>Cari "<span x-text="q"></span>" di semua halaman</a>
    </div>
</form>
