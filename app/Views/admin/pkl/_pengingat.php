<?php
/**
 * Dialog LAYAR PENUH: pekerjaan PKL yang lewat batas keputusan (Libraries\PklPengingat). Dipanggil layouts/admin.php di halaman
 * mana pun bagi Waka Hubin (ajuan belum di-ACC) dan Operator (laporan siswa belum diproses). Terbuka sendiri saat halaman dimuat.
 * Tutup (×, Esc, klik latar) = sembunyi di halaman ini saja; "Ingatkan lagi 1 jam lagi" = POST admin/pemberitahuan/tunda-pengingat.
 *
 * @var array<string, mixed> $pengingat PklPengingat::untukLayout()
 * @var string               $kembali   alamat halaman ini (tujuan kembali sesudah menunda)
 */
$hari  = (int) $pengingat['hari'];
$ajuan = $pengingat['ajuan'];
$lapor = $pengingat['lapor'];
$tgl   = static fn (string $w): string => \App\Libraries\IsianBantu::tanggalIndo(substr($w, 0, 10));
$lama  = static fn (int $n): string => $n . ' hari';
?>
<?= ui_dialog_buka(['nama' => 'pengingat-pkl', 'judul' => 'Perlu tindakan Anda sekarang', 'ukuran' => 'penuh', 'awalBuka' => true]) ?>
<div class="dialog-isi pengingat-isi">
    <?php if ($ajuan !== null): ?>
        <section class="pengingat-bagian" aria-labelledby="pengingat-ajuan">
            <h4 id="pengingat-ajuan" class="pengingat-judul"><?= ikon('clock', 'h-6 w-6 shrink-0') ?><span><?= (int) $ajuan['n'] ?> ajuan PKL belum di-ACC lebih dari <?= $hari ?> hari</span></h4>
            <p class="pengingat-teks">
                <b><?= (int) $ajuan['siswa'] ?> siswa</b> menunggu keputusan sekolah melewati batas <?= $hari ?> hari. Selama belum diputuskan, mereka
                <b>belum bisa mengambil Surat Izin PKL</b> dan <b>tidak bisa mengajukan tempat lain</b>. Putuskan tiap ajuan: ACC, kembalikan untuk diperbaiki, atau tolak.
            </p>
            <div class="tabel-bungkus pengingat-tabel">
                <table class="tabel">
                    <thead>
                        <tr><th scope="col">Ajuan</th><th scope="col">Siswa</th><th scope="col">Tempat PKL</th><th scope="col">Dikirim</th><th scope="col">Menunggu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ajuan['baris'] as $r): ?>
                            <tr>
                                <th scope="row"><a href="<?= site_url('admin/pkl/' . (int) $r['id']) ?>"><?= esc($r['kode']) ?></a></th>
                                <td><?= esc($r['siswa']) ?><?= $r['jumlah_siswa'] > 1 ? ' <span class="text-gray-500 dark:text-gray-400">+ ' . ((int) $r['jumlah_siswa'] - 1) . ' teman</span>' : '' ?><?= $r['kelas'] !== '' ? '<br><span class="text-xs text-gray-500 dark:text-gray-400">' . esc($r['kelas']) . '</span>' : '' ?></td>
                                <td><?= esc($r['perusahaan']) ?></td>
                                <td class="whitespace-nowrap"><?= esc($tgl($r['dikirim'])) ?></td>
                                <td class="whitespace-nowrap"><span class="lencana-merah"><?= esc($lama((int) $r['menunggu_hari'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($ajuan['n'] > count($ajuan['baris'])): ?>
                <p class="hint">Dan <?= (int) $ajuan['n'] - count($ajuan['baris']) ?> ajuan lainnya — semuanya ada di daftar ajuan menunggu.</p>
            <?php endif; ?>
            <a href="<?= site_url('admin/pkl/daftar/menunggu') ?>" class="btn btn-utama btn-lg"><?= ikon('arrow-right') ?>Buka &amp; putuskan sekarang</a>
        </section>
    <?php endif; ?>

    <?php if ($lapor !== null): ?>
        <section class="pengingat-bagian" aria-labelledby="pengingat-lapor">
            <h4 id="pengingat-lapor" class="pengingat-judul"><?= ikon('inbox', 'h-6 w-6 shrink-0') ?><span><?= (int) $lapor['n'] ?> laporan siswa belum diproses lebih dari <?= $hari ?> hari</span></h4>
            <p class="pengingat-teks">
                Siswa sudah melaporkan hasil dari perusahaan, tetapi laporannya belum diproses. Siswa yang diterima masih menunggu Surat Balasan PKL dibuatkan.
                <?php if ((int) $lapor['tertahan'] > 0): ?>
                    <b><?= (int) $lapor['tertahan'] ?> siswa dilaporkan TIDAK diterima perusahaan</b> dan masih tercatat di ajuan lamanya, jadi mereka
                    <b>belum bisa mengajukan tempat PKL lain</b> sampai laporan diproses.
                <?php endif; ?>
            </p>
            <div class="tabel-bungkus pengingat-tabel">
                <table class="tabel">
                    <thead>
                        <tr><th scope="col">Ajuan</th><th scope="col">Tempat PKL</th><th scope="col">Hasil dari perusahaan</th><th scope="col">Dilaporkan</th><th scope="col">Menunggu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lapor['baris'] as $r): ?>
                            <tr>
                                <th scope="row"><?= esc($r['kode']) ?><?= $r['pelapor'] !== '' ? '<br><span class="text-xs font-normal text-gray-500 dark:text-gray-400">oleh ' . esc($r['pelapor']) . '</span>' : '' ?></th>
                                <td><?= esc($r['perusahaan']) ?></td>
                                <td><?= esc($r['hasil_teks']) ?><?= $r['tertahan'] > 0 ? '<br><span class="text-xs text-gray-500 dark:text-gray-400">' . (int) $r['tertahan'] . ' siswa tidak diterima, masih terkunci</span>' : '' ?></td>
                                <td class="whitespace-nowrap"><?= esc($tgl($r['dikirim'])) ?></td>
                                <td class="whitespace-nowrap"><span class="lencana-merah"><?= esc($lama((int) $r['menunggu_hari'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($lapor['n'] > count($lapor['baris'])): ?>
                <p class="hint">Dan <?= (int) $lapor['n'] - count($lapor['baris']) ?> laporan lainnya — semuanya ada di kotak laporan.</p>
            <?php endif; ?>
            <a href="<?= site_url('admin/surat/balasan/laporan') ?>" class="btn btn-utama btn-lg"><?= ikon('arrow-right') ?>Proses laporan sekarang</a>
        </section>
    <?php endif; ?>
</div>
<div class="dialog-kaki">
    <p class="hint mr-auto">Bila ditutup, peringatan ini muncul lagi di halaman berikutnya dan setiap kali login, sampai semuanya diproses.</p>
    <form method="post" action="<?= site_url('admin/pemberitahuan/tunda-pengingat') ?>" data-lihat-boleh>
        <?= csrf_field() ?>
        <input type="hidden" name="kembali" value="<?= esc($kembali, 'attr') ?>">
        <button type="submit" class="btn btn-netral"><?= ikon('clock-arrow') ?>Ingatkan lagi 1 jam lagi</button>
    </form>
</div>
<?= ui_dialog_tutup() ?>
