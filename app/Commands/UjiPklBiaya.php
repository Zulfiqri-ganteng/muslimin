<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklAjuan;
use App\Libraries\PklBiaya;
use App\Libraries\PklHak;
use App\Libraries\PklLaporanBiaya;
use App\Libraries\PklNomorSurat;
use App\Libraries\PklSurat;
use App\Libraries\PklUnduh;
use App\Libraries\PklWa;
use App\Models\PklPengajuanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji regresi hak akses PKL yang diatur Admin, biaya saat unduh surat, WhatsApp manual, Laporan Pembayaran,
 * nomor surat 001 dan titik gelar Kepala Sekolah (rancangan: docs/DESAIN-PKL.md, bagian "Hak akses, biaya, WhatsApp").
 *
 * Data uji diberi tanda NIS "ZZUJIBY…" dan alamat IP "10.9.8.8", dibuat dan dihapus sendiri; Pengaturan PKL dan
 * jenis biaya dipulihkan ke nilai semula di akhir (juga bila uji berhenti di tengah, pada jalan berikutnya).
 *
 * Jalankan:  php spark dev:uji-pkl-biaya
 */
class UjiPklBiaya extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-pkl-biaya';
    protected $description = 'Uji hak akses PKL (diatur Admin), biaya saat unduh surat, WhatsApp manual, laporan pembayaran, nomor 001.';

    private const NIS = 'ZZUJIBY';
    private const IP  = '10.9.8.8';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    private ?array $biayaAsli = null;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        try {
            return $this->doRun();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function doRun(): int
    {
        $this->db = db_connect();
        $this->bersihkan();

        $this->ujiMurni();
        $this->ujiHak();
        $this->ujiBiayaDanUnduh();
        $this->ujiWa();
        $this->ujiLaporan();
        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // 1. Pembantu murni
    // =================================================================

    private function ujiMurni(): void
    {
        $this->bagian('Nomor surat 001, titik gelar, bulan, WhatsApp (tanpa DB)');
        $N = PklNomorSurat::class;
        $t = new \DateTimeImmutable('2026-10-08');

        $this->cek('{urut3} → 006/SMK-BN/PKL/X/2026; {urut4} → 0006; {urut} → 6', $N::format('{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}', 6, $t) === '006/SMK-BN/PKL/X/2026' && $N::format('{urut4}/X', 6, $t) === '0006/X' && $N::format('{urut}/X', 6, $t) === '6/X');
        $this->cek('nomor 1000 tetap utuh pada {urut3}', $N::format('{urut3}/X', 1000, $t) === '1000/X');
        $this->cek('salah ketik "{urut}00" DITOLAK (hasilnya 600, bukan 006) dan "00{urut}" juga', $N::periksa('{urut}00/SMK-BN/PKL/{bln_romawi}/{thn}') !== null && $N::periksa('00{urut}/X') !== null && $N::periksa('{urut3}7/X') !== null && $N::periksa('{urut}/X/2026{urut}') !== null);
        $this->cek('format sah tetap lolos: bawaan, {urut3}, {urut4}, "PKL-{urut}-{thn}"', $N::periksa($N::BAWAAN) === null && $N::periksa('{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}') === null && $N::periksa('{urut4}/X') === null && $N::periksa('PKL-{urut}-{thn}') === null);
        $semuaPresetSah = true;
        foreach ($N::PRESET as [$pola, $ket]) {
            $semuaPresetSah = $semuaPresetSah && $N::periksa($pola) === null && $ket !== '';
        }
        $this->cek('semua pilihan siap pakai (PRESET) sah, ada yang berformat 001', $semuaPresetSah && $N::format($N::PRESET[1][0], 1, $t) === '001/SMK-BN/PKL/X/2026');

        $this->cek('rapikanGelar: "Napis Kuturupi, S.T" → "…, S.T."', IsianBantu::rapikanGelar('Napis Kuturupi, S.T') === 'Napis Kuturupi, S.T.');
        $this->cek('rapikanGelar: sudah bertitik / bergelar jamak / nama biasa tidak diubah', IsianBantu::rapikanGelar('Napis Kuturupi, S.T.') === 'Napis Kuturupi, S.T.' && IsianBantu::rapikanGelar('Budi Santoso') === 'Budi Santoso' && IsianBantu::rapikanGelar('Dr. Ahmad') === 'Dr. Ahmad' && IsianBantu::rapikanGelar('') === '');
        $this->cek('rapikanGelar: S.Pd, S.E, M.Pd.I, "S.Pd., M.M" mendapat titik penutup', IsianBantu::rapikanGelar('Puguh Wira Sakti, S.Pd') === 'Puguh Wira Sakti, S.Pd.' && IsianBantu::rapikanGelar('Hj. Siti Aminah, S.E') === 'Hj. Siti Aminah, S.E.' && IsianBantu::rapikanGelar('Ali, M.Pd.I') === 'Ali, M.Pd.I.' && IsianBantu::rapikanGelar('Siti, S.Pd., M.M') === 'Siti, S.Pd., M.M.');

        $this->cek('rupiah: 300000 → "Rp 300.000"; 0 → "Rp 0"', PklBiaya::rupiah(300000) === 'Rp 300.000' && PklBiaya::rupiah(0) === 'Rp 0' && PklBiaya::rupiah(1250000) === 'Rp 1.250.000');
        $this->cek('geserBulan: lintas tahun (2026-11 +2 = 2027-01; 2026-01 −1 = 2025-12)', PklBiaya::geserBulan('2026-11', 2) === '2027-01' && PklBiaya::geserBulan('2026-01', -1) === '2025-12' && PklBiaya::geserBulan('2026-10', 0) === '2026-10');
        $thn = (int) date('Y');
        $this->cek('bulanSah: format YYYY-MM, bulan 01–12, tahun ±1 dari sekarang', PklBiaya::bulanSah($thn . '-10') === $thn . '-10' && PklBiaya::bulanSah($thn . '-13') === null && PklBiaya::bulanSah($thn . '-00') === null && PklBiaya::bulanSah(($thn + 5) . '-01') === null && PklBiaya::bulanSah('abc') === null && PklBiaya::bulanSah($thn . '-1') === null);
        $this->cek('labelBulan: "2026-10" → "Okt 2026"; labelPeriode kegiatan apa adanya', PklBiaya::labelBulan('2026-10') === 'Okt 2026' && PklBiaya::labelPeriode('kegiatan', '2026/2027') === '2026/2027' && PklBiaya::labelPeriode('bulanan', '2026-05') === 'Mei 2026');
        $this->cek('akhirBeasiswa: tahun masuk + 3 → 30 Juni; tahun tak wajar / sudah lewat → null', PklBiaya::akhirBeasiswa($thn) === ($thn + 3) . '-06-30' && PklBiaya::akhirBeasiswa(null) === null && PklBiaya::akhirBeasiswa(1990) === null && PklBiaya::akhirBeasiswa(2010) === null);

        $this->cek('nomorWa: 0812-3456-7890 / +62 812… / 62812… → 6281234567890', PklWa::nomorWa('0812-3456-7890') === '6281234567890' && PklWa::nomorWa('+62 812 3456 7890') === '6281234567890' && PklWa::nomorWa('6281234567890') === '6281234567890');
        $this->cek('nomorWa: telepon rumah, terlalu pendek, kosong, angka berulang → null', PklWa::nomorWa('02188776655') === null && PklWa::nomorWa('0812345') === null && PklWa::nomorWa('') === null && PklWa::nomorWa('0888888888') === null);
        $this->cek('isi pesan: penanda diganti, spasi ganda dirapikan', PklWa::isi('Halo {nama}  ({kelas})', ['nama' => 'Dewi', 'kelas' => 'XI TKJ 1']) === 'Halo Dewi (XI TKJ 1)');
        $this->cek('periksaPesan: bawaan sah; penanda asing, kosong, terlalu panjang ditolak', PklWa::periksaPesan(PklWa::PESAN_BAWAAN) === null && PklWa::periksaPesan('Halo {nama} {asing}') !== null && PklWa::periksaPesan('  ') !== null && PklWa::periksaPesan(str_repeat('a', PklWa::MAKS_PESAN + 1)) !== null);

        $H = PklHak::class;
        $peta = [
            'admin/pkl' => null, 'admin/pkl/12' => null, 'admin/pkl/daftar/disetujui' => null, 'admin/pkl/siswa' => null, 'admin/pkl/siswa/excel' => null,
            'admin/pkl/12/surat' => 'surat', 'admin/pkl/surat-massal' => 'surat', 'admin/pkl/surat/siap' => 'surat', 'admin/pkl/surat/hasil/123' => 'surat', 'admin/pkl/wa' => 'surat',
            'admin/pkl/12/wa/5/tandai' => 'surat', 'admin/pkl/12/pembayaran/hapus' => 'surat', 'admin/pkl/12/pembayaran/beasiswa-cabut' => 'surat',
            'admin/pkl/12/acc' => 'acc', 'admin/pkl/12/tolak' => 'acc', 'admin/pkl/12/batal-acc' => 'acc', 'admin/pkl/acc-massal' => 'acc',
            'admin/pkl/baru' => 'ubah', 'admin/pkl/12/ubah' => 'ubah', 'admin/pkl/12/kembalikan' => 'ubah', 'admin/pkl/siswa-kelas' => 'ubah',
            'admin/pkl/ttd' => 'ttd', 'admin/pkl/ttd/gambar' => 'ttd', 'admin/pkl/ttd/hapus' => 'ttd',
            'admin/pkl/pengaturan' => 'pengaturan', 'admin/pkl/pengaturan/template' => 'pengaturan', 'admin/pkl/pengaturan/biaya' => 'pengaturan', 'admin/pkl/impor/simpan' => 'pengaturan',
            'admin/pkl/hapus/12' => 'hapus', 'admin/pkl/laporan' => 'laporan', 'admin/pkl/laporan/excel' => 'laporan', 'admin/pkl/hak-akses' => 'khusus_admin', 'admin/pkl/hak-akses/bawaan' => 'khusus_admin',
            'admin/master/siswa' => null, 'admin/pkl-lain/surat' => null,
        ];
        $salah = [];
        foreach ($peta as $alamat => $harap) {
            if ($H::hakUntukAlamat($alamat) !== $harap) {
                $salah[] = $alamat . ' → ' . json_encode($H::hakUntukAlamat($alamat));
            }
        }
        $this->cek('alamat → hak yang dituntut (' . count($peta) . ' alamat web)', $salah === [], implode(' | ', $salah));
        $this->cek('alamat dengan huruf besar / garis ganda / query tetap terpetakan', $H::hakUntukAlamat('ADMIN/PKL/Surat-Massal') === 'surat' && $H::hakUntukAlamat('admin//pkl//12//surat') === 'surat' && $H::hakUntukAlamat('admin/pkl/laporan?x=1') === 'laporan' && $H::hakUntukAlamat('/admin/pkl/hak-akses/') === 'khusus_admin');
    }

    // =================================================================
    // 2. Hak akses (diatur Admin)
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Hak akses PKL yang diatur Admin');
        $H  = HakAkses::class;
        $PH = PklHak::class;
        $this->simpanPengaturanAsli();
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => null]);
        $PH::lupakan();
        $adm = ['oleh' => 'Admin Uji', 'admin_id' => 8, 'peran' => 'admin', 'ip' => self::IP];

        $this->cek('bawaan: Hubin = ACC + ubah + tanda tangan; TIDAK surat, laporan, pengaturan, hapus', $PH::boleh('hubin', 'acc') && $PH::boleh('hubin', 'ubah') && $PH::boleh('hubin', 'ttd') && ! $PH::boleh('hubin', 'surat') && ! $PH::boleh('hubin', 'laporan') && ! $PH::boleh('hubin', 'pengaturan') && ! $PH::boleh('hubin', 'hapus'));
        $this->cek('bawaan: Operator = surat + laporan + ubah + pengaturan; TIDAK ACC, tanda tangan, hapus', $PH::boleh('operator', 'surat') && $PH::boleh('operator', 'laporan') && $PH::boleh('operator', 'ubah') && $PH::boleh('operator', 'pengaturan') && ! $PH::boleh('operator', 'acc') && ! $PH::boleh('operator', 'ttd') && ! $PH::boleh('operator', 'hapus'));
        $semua = array_keys($PH::HAK);
        $adminPenuh = true;
        foreach ($semua as $h) {
            $adminPenuh = $adminPenuh && $PH::boleh('admin', $h);
        }
        $this->cek('Admin SELALU boleh semua hak; peran tak dikenal / kosong / hak tak dikenal TIDAK', $adminPenuh && ! $PH::boleh('xyz', 'acc') && ! $PH::boleh(null, 'surat') && ! $PH::boleh('', 'surat') && ! $PH::boleh('admin', 'ngawur') && ! $PH::boleh('hubin', 'ngawur'));

        // Jalur nyata: AuthFilter memakai HakAkses::boleh(alamat).
        $this->cek('Hubin TIDAK bisa membuka/menjalankan unduh surat, siap, WhatsApp, koreksi biaya, laporan (bawaan)', ! $H::boleh('hubin', 'admin/pkl/12/surat') && ! $H::boleh('hubin', 'admin/pkl/surat-massal') && ! $H::boleh('hubin', 'admin/pkl/surat/siap') && ! $H::boleh('hubin', 'admin/pkl/wa') && ! $H::boleh('hubin', 'admin/pkl/12/pembayaran/hapus') && ! $H::boleh('hubin', 'admin/pkl/laporan') && ! $H::boleh('hubin', 'admin/pkl/laporan/excel'));
        $this->cek('Hubin tetap bisa: beranda, daftar, detail, ACC, ACC massal, ubah, kembalikan, tanda tangan, status siswa', $H::boleh('hubin', 'admin/pkl') && $H::boleh('hubin', 'admin/pkl/daftar/disetujui') && $H::boleh('hubin', 'admin/pkl/12') && $H::boleh('hubin', 'admin/pkl/12/acc') && $H::boleh('hubin', 'admin/pkl/acc-massal') && $H::boleh('hubin', 'admin/pkl/12/ubah') && $H::boleh('hubin', 'admin/pkl/12/kembalikan') && $H::boleh('hubin', 'admin/pkl/ttd') && $H::boleh('hubin', 'admin/pkl/siswa'));
        $this->cek('Operator bisa unduh surat/massal/siap/WhatsApp/koreksi/laporan; TIDAK ACC, tanda tangan, hapus, Hak Akses', $H::boleh('operator', 'admin/pkl/12/surat') && $H::boleh('operator', 'admin/pkl/surat-massal') && $H::boleh('operator', 'admin/pkl/surat/siap') && $H::boleh('operator', 'admin/pkl/wa') && $H::boleh('operator', 'admin/pkl/12/pembayaran/hapus') && $H::boleh('operator', 'admin/pkl/laporan') && ! $H::boleh('operator', 'admin/pkl/12/acc') && ! $H::boleh('operator', 'admin/pkl/acc-massal') && ! $H::boleh('operator', 'admin/pkl/ttd') && ! $H::boleh('operator', 'admin/pkl/hapus/3') && ! $H::boleh('operator', 'admin/pkl/hak-akses'));
        $this->cek('halaman Hak Akses KHUSUS Admin (Hubin & Operator & tipuan huruf ditolak)', $H::boleh('admin', 'admin/pkl/hak-akses') && ! $H::boleh('hubin', 'admin/pkl/hak-akses') && ! $H::boleh('operator', 'ADMIN/PKL/Hak-Akses') && ! $H::boleh('operator', 'admin//pkl/hak-akses/bawaan'));
        $this->cek('area non-PKL tidak terpengaruh: Hubin tetap tak boleh Biodata/Master; Operator tetap boleh Biodata/Siswa/Kelas', ! $H::boleh('hubin', 'admin/biodata') && ! $H::boleh('hubin', 'admin/master/siswa') && $H::boleh('operator', 'admin/biodata') && $H::boleh('operator', 'admin/master/siswa') && $H::boleh('operator', 'admin/master/kelas') && $H::boleh('admin', 'admin/dashboard'));
        $this->cek('bolehAcc: Admin & Hubin YA; Operator TIDAK (bawaan)', $H::bolehAcc('admin') && $H::bolehAcc('hubin') && ! $H::bolehAcc('operator') && ! $H::bolehAcc(null));

        // ---- simpan
        $r = $PH::simpan(['hubin' => ['ubah', 'ttd'], 'operator' => ['surat', 'ubah']], $adm);
        $this->cek('menolak bila tak seorang pun (Hubin/Operator) memegang ACC', ! $r['ok'] && str_contains($r['pesan'], 'ACC'), $r['pesan']);
        $r = $PH::simpan(['hubin' => ['acc', 'ubah'], 'operator' => ['laporan', 'ubah']], $adm);
        $this->cek('menolak bila tak seorang pun memegang Unduh surat', ! $r['ok'] && str_contains($r['pesan'], 'Unduh surat'), $r['pesan']);
        $this->cek('penolakan tidak mengubah apa pun (hak bawaan masih berlaku)', $PH::boleh('hubin', 'acc') && ! $PH::boleh('hubin', 'surat') && $PH::boleh('operator', 'surat'));

        $r = $PH::simpan(['hubin' => ['acc', 'ubah', 'ttd', 'ngawur'], 'operator' => ['acc', 'surat', 'laporan', 'ubah', 'pengaturan', 'hapus'], 'admin' => ['acc']], $adm);
        $this->cek('menyimpan: kunci asing ("ngawur") dan peran "admin" dibuang', $r['ok'] && ! isset($PH::matriks()['admin']) && ! isset($PH::matriks()['hubin']['ngawur']), $r['pesan']);
        $this->cek('berlaku LANGSUNG: Operator kini boleh ACC & hapus; alamat ACC/hapus ikut terbuka', $H::bolehAcc('operator') && $H::boleh('operator', 'admin/pkl/12/acc') && $H::boleh('operator', 'admin/pkl/acc-massal') && $H::boleh('operator', 'admin/pkl/hapus/3'));
        $this->cek('peringatan: Operator memegang ACC + Unduh surat (pemisahan tugas hilang) dan Operator ber-ACC', count(array_filter($r['peringatan'], static fn (string $w) => str_contains($w, 'pemisahan tugas'))) === 1 && count(array_filter($r['peringatan'], static fn (string $w) => str_contains($w, 'wewenang ACC'))) === 1, json_encode($r['peringatan']));

        $r = $PH::simpan(['hubin' => ['acc', 'surat', 'ubah', 'ttd'], 'operator' => ['laporan', 'ubah', 'pengaturan']], $adm);
        $this->cek('Hubin diberi unduh surat → boleh; Operator dicabut → alamat surat tertutup baginya', $r['ok'] && $H::boleh('hubin', 'admin/pkl/12/surat') && ! $H::boleh('operator', 'admin/pkl/12/surat') && ! $H::boleh('operator', 'admin/pkl/surat/siap'));
        $this->cek('peringatan: Hubin memegang ACC + Unduh surat', count(array_filter($r['peringatan'], static fn (string $w) => str_contains($w, 'Waka Hubin memegang ACC sekaligus unduh surat'))) === 1);

        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => '{rusak']);
        $PH::lupakan();
        $this->cek('JSON hak rusak → kembali ke hak bawaan (aman, tidak galat)', $PH::boleh('hubin', 'acc') && ! $PH::boleh('hubin', 'surat') && $PH::boleh('operator', 'surat'));
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => null]);
        $PH::lupakan();
        $kembali = $PH::simpan($PH::BAWAAN, $adm);
        $this->cek('"Kembalikan ke bawaan" (simpan BAWAAN) sah & tanpa peringatan pemisahan tugas', $kembali['ok'] && array_filter($kembali['peringatan'], static fn (string $w) => str_contains($w, 'pemisahan')) === []);
        $audit = $this->db->table('audit_log')->like('deskripsi', 'Hak akses PKL diubah')->countAllResults();
        $this->cek('setiap perubahan hak tercatat di Audit Log', $audit >= 3, 'jumlah catatan: ' . $audit);

        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => null]);
        $PH::lupakan();
    }

    // =================================================================
    // 3. Biaya + unduh surat
    // =================================================================

    private function buatSiswa(int $n, int $kelasId, ?int $tahunMasuk = null): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('siswa')->insert([
            'nis' => self::NIS . $n, 'nama' => 'ZZUJI BY ' . $n, 'jenis_kelamin' => 'L', 'kelas_id' => $kelasId,
            'status' => 'aktif', 'tanggal_lahir' => '2010-05-17', 'tahun_masuk' => $tahunMasuk, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<array{0:int,1:string,2:string}> $anggota [siswa_id, peran, hp] */
    private function buatAjuan(string $perusahaan, array $anggota, int $kelasId): int
    {
        $baris = [];
        foreach ($anggota as [$sid, $peran, $hp]) {
            $baris[] = ['siswa_id' => $sid, 'kelas_id' => $kelasId, 'peran' => $peran, 'hp' => $hp];
        }
        $data = [
            'perusahaan_nama' => $perusahaan, 'perusahaan_norm' => \App\Libraries\PklForm::normPerusahaan($perusahaan),
            'perusahaan_alamat' => 'Jl. Uji Biaya No. 1', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null, 'hp' => $anggota[0][2], 'tanggal_lahir' => null, 'teman' => [],
        ];
        $r = (new PklAjuan())->kirimBaru($data, $baris, ['oleh' => 'Budi Hubin', 'admin_id' => 7, 'peran' => 'hubin', 'ip' => self::IP,
            'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui', 'tahun_ajaran' => '2026/2027']);

        return (int) ($r['id'] ?? 0);
    }

    private function jumlah(string $tabel, array $where): int
    {
        return (int) $this->db->table($tabel)->where($where)->countAllResults();
    }

    private function ujiBiayaDanUnduh(): void
    {
        $this->bagian('Biaya saat unduh: gerbang, catat, beasiswa, keringanan, koreksi, unduh surat');
        $k = $this->db->table('kelas')->select('id')->where('tingkat', 'XI')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('bahan uji: kelas XI tersedia', false);

            return;
        }
        $kelas = (int) $k['id'];
        $this->simpanPengaturanAsli();
        $this->simpanBiayaAsli();
        $this->db->table('pkl_pengaturan')->where('id', 1)->update([
            'hak_peran' => null, 'format_nomor' => '{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}', 'kepsek_nama' => 'Napis Kuturupi, S.T', 'waka_hubin_nama' => 'Puguh Wira Sakti, S.Pd',
        ]);
        PklHak::lupakan();

        $thn = (int) date('Y');
        $s = [];
        foreach (range(1, 10) as $n) {
            $s[$n] = $this->buatSiswa($n, $kelas, $n === 4 ? $thn : null);
        }
        $A = $this->buatAjuan('PT ZZUJIBY Alfa', [[$s[1], 'pengaju', '081200000001'], [$s[2], 'teman', '081200000002'], [$s[3], 'teman', '081200000003']], $kelas);
        $B = $this->buatAjuan('PT ZZUJIBY Beta', [[$s[4], 'pengaju', '081200000004']], $kelas);
        $C = $this->buatAjuan('PT ZZUJIBY Gamma', [[$s[5], 'pengaju', '081200000005'], [$s[6], 'teman', '12345']], $kelas);
        $D = $this->buatAjuan('PT ZZUJIBY Delta', [[$s[7], 'pengaju', '081200000007'], [$s[8], 'teman', '081200000008']], $kelas);
        $E = $this->buatAjuan('PT ZZUJIBY Epsilon', [[$s[9], 'pengaju', '081200000009']], $kelas);
        $F = $this->buatAjuan('PT ZZUJIBY Zeta', [[$s[10], 'pengaju', '081200000010']], $kelas); // tak pernah diunduh → status "Belum" di laporan
        $this->cek('bahan uji: 6 ajuan disetujui dibuat (A 3 siswa, B 1, C 2, D 2, E 1, F 1)', min($A, $B, $C, $D, $E, $F) > 0 && $this->jumlah('pkl_pengajuan', ['status' => 'disetujui', 'ip_address' => self::IP]) === 6);

        $bi   = new PklBiaya();
        $bulan = date('Y-m');
        $hub  = ['oleh' => 'Budi Hubin', 'admin_id' => 7, 'peran' => 'hubin', 'ip' => self::IP];
        $adm  = ['oleh' => 'Admin Uji', 'admin_id' => 8, 'peran' => 'admin', 'ip' => self::IP];
        $opr  = ['oleh' => 'Operator Uji', 'admin_id' => 9, 'peran' => 'operator', 'ip' => self::IP];

        // ---------- jenis & siap ----------
        $jenis = $bi->jenis();
        $this->cek('4 jenis biaya awal: Biaya PKL 300.000 (kegiatan), SPP 150.000, Tabungan 50.000, OSIS 10.000 (bulanan)',
            count($jenis) === 4 && array_column($jenis, 'kode') === ['pkl', 'spp', 'tabungan', 'osis'] && array_column($jenis, 'nominal') === [300000, 150000, 50000, 10000]
            && array_column($jenis, 'siklus') === ['kegiatan', 'bulanan', 'bulanan', 'bulanan']);
        $siap = $bi->siap([$A]);
        $this->cek('siap(A): 1 surat, 3 siswa (pengaju dulu), periode kegiatan = tahun ajaran ajuan', count($siap['surat']) === 1 && count($siap['siswa']) === 3 && $siap['surat'][0]['periode_kegiatan'] === '2026/2027' && $siap['siswa'][$s[1]]['peran'] === 'pengaju' && $siap['siswa'][$s[1]]['hp'] === '081200000001');
        $this->cek('siap: ajuan yang BELUM disetujui / id ngawur diabaikan', $bi->siap([999999999])['siswa'] === [] && $bi->siap([])['surat'] === []);

        // ---------- gerbang ----------
        $r = $bi->rencana([$A], []);
        $this->cek('GERBANG: tanpa centang sama sekali → ditolak, menyebut 3 siswa', ! $r['ok'] && str_contains($r['galat']['umum'] ?? '', '3 siswa') && str_contains($r['galat']['umum'], 'ZZUJI BY 1'), $r['galat']['umum'] ?? '');
        $r = $bi->rencana([$A], ['biaya' => [$s[1] => ['jenis' => ['pkl']], $s[2] => ['jenis' => ['pkl']]]]);
        $this->cek('GERBANG: 2 dari 3 siswa dicentang → ditolak, menyebut yang ketiga saja', ! $r['ok'] && str_contains($r['galat']['umum'] ?? '', '1 siswa') && str_contains($r['galat']['umum'], 'ZZUJI BY 3') && ! str_contains($r['galat']['umum'], 'ZZUJI BY 1'), $r['galat']['umum'] ?? '');

        // ---------- validasi kiriman ----------
        $r = $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'bulan' => $thn . '-13']]);
        $this->cek('bulan tak sah (-13) ditolak', ! $r['ok'] && $r['galat'] !== []);
        $r = $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'jumlah_bulan' => 13]]);
        $this->cek('jumlah bulan 13 ditolak; "abc" ditolak', ! $r['ok'] && ! $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'jumlah_bulan' => 'abc']])['ok']);
        $r = $bi->rencana([$A], ['semua' => ['jenis' => ['xyz']]]);
        $this->cek('jenis biaya tak dikenal ditolak (pesan umum)', ! $r['ok'] && str_contains($r['galat']['umum'] ?? '', 'tidak dikenal'));
        $r = $bi->rencana([$A], ['biaya' => [$s[1] => ['jenis' => ['pkl'], 'beasiswa' => 'ngawur']], 'semua' => ['jenis' => ['pkl']]]);
        $this->cek('sumber beasiswa tak dikenal ditolak', ! $r['ok'] && isset($r['galat'][(string) $s[1]]));
        $r = $bi->rencana([$A], ['biaya' => [$s[1] => ['keringanan' => 'ab']], 'semua' => ['jenis' => ['pkl']]]);
        $this->cek('alasan keringanan < 5 huruf ditolak', ! $r['ok'] && isset($r['galat'][(string) $s[1]]));

        // ---------- catat: Biaya PKL untuk semua ----------
        $r = $bi->rencana([$A], ['semua' => ['jenis' => ['pkl']]]);
        $this->cek('rencana semua=PKL: 3 item baru, total Rp 900.000', $r['ok'] && $r['ringkas']['item_baru'] === 3 && $r['ringkas']['total_baru'] === 900000, json_encode($r['ringkas']));
        $c = $bi->catat($r['rencana'], $opr);
        $this->cek('catat: 3 baris pembayaran, nominal 300.000, periode = tahun ajaran, dicatat Operator Uji', $c['ok'] && $c['item'] === 3 && $c['total'] === 900000
            && $this->db->table('pkl_pembayaran')->whereIn('siswa_id', [$s[1], $s[2], $s[3]])->where('nominal', 300000)->where('periode', '2026/2027')->where('dicatat_oleh', 'Operator Uji')->countAllResults() === 3);
        $this->cek('riwayat ajuan A mencatat "bayar"', $this->jumlah('pkl_riwayat', ['pengajuan_id' => $A, 'aksi' => 'bayar']) === 1);

        $r2 = $bi->rencana([$A], []);
        $this->cek('cetak ulang tanpa centang: LOLOS (sudah tercatat), tidak ada item baru', $r2['ok'] && $r2['ringkas']['item_baru'] === 0);
        $c2 = $bi->catat($r2['rencana'], $opr);
        $this->cek('mencatat ulang tidak menggandakan (tetap 3 baris)', $c2['ok'] && $c2['item'] === 0 && $this->db->table('pkl_pembayaran')->whereIn('siswa_id', [$s[1], $s[2], $s[3]])->countAllResults() === 3);
        $r3 = $bi->rencana([$A], ['semua' => ['jenis' => ['pkl']]]);
        $this->cek('centang PKL lagi untuk yang sudah tercatat: 0 item baru, tetap lolos', $r3['ok'] && $r3['ringkas']['item_baru'] === 0);

        // ---------- bulanan ----------
        $r = $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'bulan' => $bulan, 'jumlah_bulan' => 3]]);
        $this->cek('SPP 3 bulan × 3 siswa: 9 item, Rp 1.350.000, bulan berurutan mulai bulan dipilih', $r['ok'] && $r['ringkas']['item_baru'] === 9 && $r['ringkas']['total_baru'] === 1350000
            && array_column($r['rencana'][$s[1]]['baru'], 'periode') === [$bulan, PklBiaya::geserBulan($bulan, 1), PklBiaya::geserBulan($bulan, 2)]);
        $bi->catat($r['rencana'], $opr);
        $sppBaris = (int) $this->db->query("SELECT COUNT(*) n FROM pkl_pembayaran p JOIN pkl_biaya b ON b.id = p.biaya_id WHERE b.kode = 'spp' AND p.siswa_id IN (" . implode(',', [$s[1], $s[2], $s[3]]) . ')')->getRowArray()['n'];
        $this->cek('SPP tersimpan tepat 9 baris', $sppBaris === 9, (string) $sppBaris);
        $this->cek('SPP bulan yang sama dicentang lagi → 0 baru (idempoten)', $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'bulan' => $bulan]])['ringkas']['item_baru'] === 0);
        $this->cek('SPP bulan BERBEDA (dua bulan sesudahnya + 1) → 3 item baru', $bi->rencana([$A], ['semua' => ['jenis' => ['spp'], 'bulan' => PklBiaya::geserBulan($bulan, 3)]])['ringkas']['item_baru'] === 3 || PklBiaya::bulanSah(PklBiaya::geserBulan($bulan, 3)) === null);

        // ---------- beasiswa (B: satu siswa, tahun masuk = tahun ini) ----------
        $r = $bi->rencana([$B], ['biaya' => [$s[4] => ['jenis' => ['spp', 'osis'], 'bulan' => $bulan, 'beasiswa' => 'sktm', 'beasiswa_ket' => 'SK Desa 12']]]);
        $this->cek('beasiswa SKTM: SPP dilewati (dibebaskan), OSIS tercatat, beasiswa baru dengan akhir 30 Juni (masuk+3)', $r['ok'] && count($r['rencana'][$s[4]]['baru']) === 1 && $r['rencana'][$s[4]]['baru'][0]['kode'] === 'osis'
            && $r['rencana'][$s[4]]['lewat'] !== [] && $r['rencana'][$s[4]]['beasiswa_baru']['sumber'] === 'sktm' && $r['rencana'][$s[4]]['beasiswa_baru']['berakhir_at'] === ($thn + 3) . '-06-30', json_encode($r['rencana'][$s[4]] ?? null));
        $bi->catat($r['rencana'], $opr);
        $this->cek('beasiswa & OSIS tersimpan; tidak ada baris SPP untuk siswa beasiswa', $this->jumlah('pkl_beasiswa', ['siswa_id' => $s[4], 'sumber' => 'sktm']) === 1 && $this->jumlah('pkl_pembayaran', ['siswa_id' => $s[4]]) === 1);
        $r = $bi->rencana([$B], ['semua' => ['jenis' => ['spp']]]);
        $this->cek('siswa beasiswa: centang SPP diabaikan tetapi LOLOS gerbang (beasiswa = satu catatan)', $r['ok'] && $r['ringkas']['item_baru'] === 0);
        $r = $bi->rencana([$B], []);
        $this->cek('siswa beasiswa tanpa centang apa pun → lolos (beasiswa berlaku)', $r['ok']);

        // ---------- keringanan (C: s5 keringanan, s6 tabungan) ----------
        $r = $bi->rencana([$C], ['biaya' => [$s[5] => ['keringanan' => 'Orang tua menunggu gaji'], $s[6] => ['jenis' => ['tabungan'], 'bulan' => $bulan]]]);
        $this->cek('keringanan beralasan = satu catatan: s5 lolos tanpa biaya, s6 mencatat Tabungan', $r['ok'] && $r['rencana'][$s[5]]['keringanan'] === 'Orang tua menunggu gaji' && $r['rencana'][$s[5]]['baru'] === [] && count($r['rencana'][$s[6]]['baru']) === 1);
        $bi->catat($r['rencana'], $opr);
        $this->cek('keringanan tersimpan beserta pencatatnya', $this->jumlah('pkl_keringanan', ['siswa_id' => $s[5], 'alasan' => 'Orang tua menunggu gaji', 'dicatat_oleh' => 'Operator Uji']) === 1);

        // ---------- koreksi ----------
        $pb = $this->db->table('pkl_pembayaran')->where('siswa_id', $s[1])->orderBy('id')->get()->getRowArray();
        $x = $bi->hapusPembayaran((int) $pb['id'], $A, 'x', $opr);
        $this->cek('koreksi: alasan < 5 huruf → 422, tidak terhapus', ! $x['ok'] && $x['http'] === 422 && $this->jumlah('pkl_pembayaran', ['id' => $pb['id']]) === 1);
        $x = $bi->hapusPembayaran((int) $pb['id'], $B, 'Salah centang uang belum masuk', $opr);
        $this->cek('koreksi lewat ajuan LAIN → 403 (siswa bukan anggota ajuan itu), tidak terhapus', ! $x['ok'] && $x['http'] === 403 && $this->jumlah('pkl_pembayaran', ['id' => $pb['id']]) === 1);
        $x = $bi->hapusPembayaran((int) $pb['id'], $A, 'Salah centang uang belum masuk', $opr);
        $this->cek('koreksi sah: catatan terhapus, riwayat "koreksi_bayar" tercatat, ID ngawur → 404', $x['ok'] && $this->jumlah('pkl_pembayaran', ['id' => $pb['id']]) === 0 && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $A, 'aksi' => 'koreksi_bayar']) === 1 && $bi->hapusPembayaran(999999999, $A, 'alasan cukup panjang', $opr)['http'] === 404);
        $x = $bi->cabutBeasiswa($s[4], $B, 'abc', $opr);
        $y = $bi->cabutBeasiswa($s[4], $A, 'Salah memilih beasiswa', $opr);
        $z = $bi->cabutBeasiswa($s[4], $B, 'Salah memilih beasiswa', $opr);
        $this->cek('cabut beasiswa: alasan pendek 422, ajuan salah 404, sah → terhapus', ! $x['ok'] && $x['http'] === 422 && ! $y['ok'] && $z['ok'] && $this->jumlah('pkl_beasiswa', ['siswa_id' => $s[4]]) === 0);
        $this->cek('setelah beasiswa dicabut, SPP s4 wajib dibayar lagi (centang SPP → 1 item baru)', $bi->rencana([$B], ['semua' => ['jenis' => ['spp'], 'bulan' => $bulan]])['ringkas']['item_baru'] === 1);
        $kembali = $bi->cabutBeasiswa($s[4], $B, 'sudah dicabut', $opr);
        $this->cek('cabut beasiswa yang sudah tidak ada → 404', ! $kembali['ok'] && $kembali['http'] === 404);

        // ---------- pengaturan nominal ----------
        $x = $bi->simpanJenis(['spp' => ['nama' => 'Biaya SPP', 'nominal' => 'abc']], $adm);
        $this->cek('nominal "abc" ditolak', ! $x['ok'] && isset($x['galat']['spp.nominal']));
        $semuaMati = [];
        foreach ($bi->jenis(true) as $j) {
            $semuaMati[$j['kode']] = ['nama' => $j['nama'], 'nominal' => (string) $j['nominal'], 'aktif' => 0];
        }
        $x = $bi->simpanJenis($semuaMati, $adm);
        $this->cek('menonaktifkan SEMUA jenis ditolak', ! $x['ok'] && isset($x['galat']['umum']));
        $ubah = [];
        foreach ($bi->jenis(true) as $j) {
            $ubah[$j['kode']] = ['nama' => $j['nama'], 'nominal' => $j['kode'] === 'spp' ? '160.000' : (string) $j['nominal'], 'aktif' => 1];
        }
        $x = $bi->simpanJenis($ubah, $adm);
        $this->cek('nominal SPP 160.000 tersimpan; catatan LAMA tetap 150.000, catatan baru 160.000', $x['ok'] && $bi->jenisPeta()['spp']['nominal'] === 160000
            && $this->db->query("SELECT COUNT(*) n FROM pkl_pembayaran p JOIN pkl_biaya b ON b.id = p.biaya_id WHERE b.kode = 'spp' AND p.nominal = 150000 AND p.siswa_id = " . $s[1])->getRowArray()['n'] == 3
            && $bi->rencana([$B], ['semua' => ['jenis' => ['spp'], 'bulan' => $bulan]])['rencana'][$s[4]]['baru'][0]['nominal'] === 160000);
        $this->pulihkanBiaya();
        $this->cek('nominal SPP dipulihkan 150.000', $bi->jenisPeta()['spp']['nominal'] === 150000);
        $nonaktif = $bi->jenis(true);
        $this->db->table('pkl_biaya')->where('kode', 'osis')->update(['aktif' => 0]);
        $this->cek('jenis nonaktif tak muncul di jenis() dan centangnya ditolak ("tidak dikenal")', count($bi->jenis()) === 3 && ! $bi->rencana([$A], ['semua' => ['jenis' => ['osis']]])['ok']);
        $this->pulihkanBiaya();

        // ---------- unduh surat end-to-end ----------
        $this->bagian('Unduh surat end-to-end (hak → gerbang → surat → catat)');
        $nSuratD = $this->jumlah('pkl_surat', ['pengajuan_id' => $D]);
        $u = PklUnduh::proses([$D], date('Y-m-d'), [], $opr);
        $this->cek('Operator unduh D tanpa centang → DITOLAK (422, kode biaya); surat TIDAK terbit, tidak ada pembayaran', ! $u['ok'] && $u['http'] === 422 && $u['kode'] === 'biaya' && $this->jumlah('pkl_surat', ['pengajuan_id' => $D]) === $nSuratD && $this->jumlah('pkl_pembayaran', ['siswa_id' => $s[7]]) === 0, $u['pesan'] ?? '');
        $u = PklUnduh::proses([$D], date('Y-m-d'), ['semua' => ['jenis' => ['pkl']]], $hub);
        $this->cek('Hubin unduh D (bawaan) → 403 "dilarang"; surat TIDAK terbit, tidak ada pembayaran', ! $u['ok'] && $u['http'] === 403 && $u['kode'] === 'dilarang' && $this->jumlah('pkl_surat', ['pengajuan_id' => $D]) === 0 && $this->jumlah('pkl_pembayaran', ['siswa_id' => $s[7]]) === 0, $u['pesan'] ?? '');

        $urutSebelum = (int) $this->db->query('SELECT COALESCE(MAX(urut),0) m FROM pkl_surat WHERE tahun = ' . $thn)->getRowArray()['m'];
        $u = PklUnduh::proses([$D], date('Y-m-d'), ['semua' => ['jenis' => ['pkl', 'spp']]], $opr);
        $this->cek('Operator unduh D dengan PKL+SPP → berhasil, berkas .docx (zip), 2 siswa × 2 item dicatat', $u['ok'] && str_starts_with($u['biner'], 'PK') && str_ends_with($u['nama'], '.docx') && $u['ids'] === [$D] && $u['catat']['item'] === 4 && $u['catat']['total'] === 2 * (300000 + 150000), json_encode($u['catat'] ?? $u));
        $surat = $this->db->table('pkl_surat')->where('pengajuan_id', $D)->get()->getRowArray() ?? [];
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) date('n') - 1];
        $this->cek('NOMOR SURAT 3 angka: ' . ($surat['nomor'] ?? '?'), ($surat['nomor'] ?? '') === str_pad((string) ($urutSebelum + 1), 3, '0', STR_PAD_LEFT) . '/SMK-BN/PKL/' . $romawi . '/' . $thn && (int) ($surat['cetak_ke'] ?? 0) === 1);
        $this->cek('riwayat D: bayar + surat + cetak', $this->jumlah('pkl_riwayat', ['pengajuan_id' => $D, 'aksi' => 'bayar']) === 1 && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $D, 'aksi' => 'surat']) === 1 && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $D, 'aksi' => 'cetak']) === 1);

        $xml = $this->isiDocx($u['biner'] ?? '');
        $this->cek('isi Word: nomor 3 angka tercetak', $surat !== [] && str_contains($xml, htmlspecialchars((string) $surat['nomor'], ENT_XML1)));
        $this->cek('isi Word: Kepala Sekolah "Napis Kuturupi, S.T." (titik penutup ada, tak ada "S.T" tanpa titik)', str_contains($xml, 'Napis Kuturupi, S.T.') && ! preg_match('/Napis Kuturupi, S\.T(?!\.)/', $xml));
        $this->cek('isi Word: Waka Hubin "Puguh Wira Sakti, S.Pd." (titik penutup ditambahkan)', str_contains($xml, 'Puguh Wira Sakti, S.Pd.') && ! preg_match('/Puguh Wira Sakti, S\.Pd(?!\.)/', $xml));
        $this->cek('isi Word: kaki surat menyebut pencetak "Operator Uji (Operator Sekolah)"', str_contains($xml, 'Dicetak oleh Operator Uji (Operator Sekolah)'));

        $u2 = PklUnduh::proses([$D], date('Y-m-d'), [], $opr);
        $this->cek('cetak ulang D tanpa centang → lolos, cetak_ke = 2, nomor sama, pembayaran tidak bertambah', $u2['ok'] && $u2['catat']['item'] === 0 && (int) $this->db->table('pkl_surat')->where('pengajuan_id', $D)->get()->getRowArray()['cetak_ke'] === 2
            && $this->db->table('pkl_surat')->where('pengajuan_id', $D)->get()->getRowArray()['nomor'] === $surat['nomor'] && $this->jumlah('pkl_pembayaran', ['siswa_id' => $s[7]]) === 2);

        $nSurat = $this->jumlah('pkl_surat', ['pengajuan_id' => $E]);
        $u3 = PklUnduh::proses([$B, $D, $E], date('Y-m-d'), [], $adm);
        $this->cek('massal B+D+E tanpa centang: GAGAL karena E (satu siswa) belum punya catatan — tidak ada surat yang terbit sebagian', ! $u3['ok'] && $u3['kode'] === 'biaya' && $this->jumlah('pkl_surat', ['pengajuan_id' => $E]) === $nSurat && str_contains($u3['pesan'], '1 siswa') && str_contains($u3['pesan'], 'ZZUJI BY 9'), $u3['pesan'] ?? '');
        $u3 = PklUnduh::proses([$B, $D, $E], date('Y-m-d'), ['semua' => ['jenis' => ['osis'], 'bulan' => $bulan]], $adm);
        $this->cek('massal B+D+E dengan OSIS untuk semua oleh Admin → berhasil (Admin selalu boleh), 3 surat', $u3['ok'] && $u3['jumlah'] === 3 && count($u3['ids']) === 3 && str_contains($u3['nama'], '3 surat'), $u3['pesan'] ?? '');
        $this->cek('OSIS tercatat hanya untuk yang belum: s4 (sudah punya) tidak digandakan, total 1 baris per siswa', $this->db->query("SELECT COUNT(*) n FROM pkl_pembayaran p JOIN pkl_biaya b ON b.id = p.biaya_id WHERE b.kode = 'osis' AND p.siswa_id = " . $s[4])->getRowArray()['n'] == 1);

        // ---------- hak diubah Admin → langsung berlaku di unduh ----------
        PklHak::simpan(['hubin' => ['acc', 'surat', 'ubah', 'ttd'], 'operator' => ['laporan', 'ubah', 'pengaturan']], $adm);
        $uh = PklUnduh::proses([$D], date('Y-m-d'), [], $hub);
        $uo = PklUnduh::proses([$D], date('Y-m-d'), [], $opr);
        $this->cek('Admin memindahkan hak unduh ke Hubin: Hubin boleh, Operator DITOLAK 403', $uh['ok'] && ! $uo['ok'] && $uo['http'] === 403);
        PklHak::simpan(PklHak::BAWAAN, $adm);
        $uh = PklUnduh::proses([$D], date('Y-m-d'), [], $hub);
        $this->cek('dikembalikan ke bawaan: Hubin lagi-lagi DITOLAK', ! $uh['ok'] && $uh['http'] === 403);

        // ---------- pembuka keputusan ----------
        $this->cek('catatan ACC: Operator yang diberi hak ACC ditulis jujur "diberi wewenang ACC oleh Admin sekolah"', str_contains(PklSurat::catatanAcc(['acc_peran' => 'operator', 'acc_nama' => 'Ani', 'acc_at' => '2026-10-07 09:41:00', 'acc_kode' => 'PKL-00001-ABC123'], [], []), 'diberi wewenang ACC oleh Admin sekolah'));

        $this->ids = ['A' => $A, 'B' => $B, 'C' => $C, 'D' => $D, 'E' => $E, 'F' => $F, 's' => $s];
    }

    /** @var array<string, mixed> */
    private array $ids = [];

    private function isiDocx(string $biner): string
    {
        if ($biner === '') {
            return '';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ujb');
        file_put_contents($tmp, $biner);
        $zip = new \ZipArchive();
        $xml = '';
        if ($zip->open($tmp) === true) {
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
        }
        @unlink($tmp);

        return $xml;
    }

    // =================================================================
    // 4. WhatsApp manual
    // =================================================================

    private function ujiWa(): void
    {
        $this->bagian('Kabar WhatsApp manual');
        if ($this->ids === []) {
            $this->cek('bahan uji tersedia', false);

            return;
        }
        ['A' => $A, 'C' => $C, 'D' => $D, 's' => $s] = $this->ids;
        $opr = ['oleh' => 'Operator Uji', 'admin_id' => 9, 'peran' => 'operator', 'ip' => self::IP];
        $wa  = new PklWa();
        $set = ['school_name' => 'SMK Uji Bina'];
        $p   = (new \App\Models\PklPengaturanModel())->ambil();

        $this->cek('ajuan A belum bersurat (belum diunduh) → tidak masuk daftar WhatsApp', $wa->daftar([$A], $p, $set) === []);
        $UA = PklUnduh::proses([$A], date('Y-m-d'), [], $opr);
        $UC = PklUnduh::proses([$C], date('Y-m-d'), [], $opr);
        $this->cek('A dan C diunduh Operator (sudah ada catatan biaya sebelumnya)', $UA['ok'] && $UC['ok'], ($UA['pesan'] ?? '') . ($UC['pesan'] ?? ''));

        $d = $wa->daftar([$A, $C], $p, $set);
        $byS = [];
        foreach ($d as $r) {
            $byS[$r['siswa_id']] = $r;
        }
        $this->cek('daftar: 5 siswa (A 3 + C 2), pengaju lebih dulu, nomor surat ikut', count($d) === 5 && $d[0]['siswa_id'] === $s[1] && $byS[$s[1]]['nomor_surat'] !== '' && $byS[$s[1]]['peran'] === 'pengaju');
        $this->cek('nomor HP valid → tautan https://wa.me/62812…?text=…', str_starts_with((string) $byS[$s[1]]['url'], 'https://wa.me/6281200000001?text=') && $byS[$s[1]]['wa'] === '6281200000001');
        $this->cek('HP tidak valid ("12345") → tanpa tautan, alasan jelas', $byS[$s[6]]['url'] === null && $byS[$s[6]]['wa'] === null && $byS[$s[6]]['alasan_tidak'] === 'Nomor HP tidak valid');
        parse_str((string) parse_url((string) $byS[$s[2]]['url'], PHP_URL_QUERY), $q);
        $this->cek('pesan: nama berhuruf judul, perusahaan, nomor surat, sekolah; TANPA rincian uang', str_contains($q['text'] ?? '', 'Zzuji By 2') && str_contains($q['text'], 'PT ZZUJIBY Alfa') && str_contains($q['text'], $byS[$s[2]]['nomor_surat']) && str_contains($q['text'], 'SMK Uji Bina') && ! preg_match('/Rp|biaya|bayar/i', $q['text']), $q['text'] ?? '');
        $this->cek('belum dikabari: dikabari_at kosong', $byS[$s[1]]['dikabari_at'] === null);

        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['wa_pesan' => 'Hai {nama}, ambil surat {nomor_surat} ya.']);
        $d2 = $wa->daftar([$A], (new \App\Models\PklPengaturanModel())->ambil(), $set);
        $this->cek('templat kustom di Pengaturan dipakai', str_starts_with($d2[0]['pesan'], 'Hai Zzuji By 1, ambil surat ') && str_ends_with($d2[0]['pesan'], ' ya.'));
        $this->db->table('pkl_pengaturan')->where('id', 1)->update(['wa_pesan' => null]);

        $this->cek('tandai(A, s1): berhasil; dikabari_at & oleh tercatat; riwayat "kabari"; Audit Log', $wa->tandai($A, $s[1], $opr)
            && $this->db->table('pkl_anggota')->where(['pengajuan_id' => $A, 'siswa_id' => $s[1]])->where('dikabari_at IS NOT NULL', null, false)->where('dikabari_oleh', 'Operator Uji')->countAllResults() === 1
            && $this->jumlah('pkl_riwayat', ['pengajuan_id' => $A, 'aksi' => 'kabari']) === 1);
        $this->cek('daftar sesudahnya: s1 sudah dikabari, s2 belum', $wa->daftar([$A], $p, $set)[0]['dikabari_at'] !== null && $wa->daftar([$A], $p, $set)[1]['dikabari_at'] === null);
        $this->cek('tandai siswa yang BUKAN anggota ajuan itu / ajuan ngawur → false', ! $wa->tandai($A, $s[7], $opr) && ! $wa->tandai(999999999, $s[1], $opr));
    }

    // =================================================================
    // 5. Laporan Pembayaran
    // =================================================================

    private function ujiLaporan(): void
    {
        $this->bagian('Laporan Pembayaran (status, kekurangan, saringan, Excel)');
        if ($this->ids === []) {
            $this->cek('bahan uji tersedia', false);

            return;
        }
        ['s' => $s] = $this->ids;
        $bulan = date('Y-m');
        $bi    = new PklBiaya();
        $peta  = $bi->jenisPeta();
        // s8 dilunasi: SPP + Tabungan + OSIS bulan ini (PKL sudah dicatat saat unduh D).
        foreach (['spp', 'tabungan', 'osis'] as $kode) {
            $this->db->table('pkl_pembayaran')->ignore(true)->insert([
                'siswa_id' => $s[8], 'biaya_id' => $peta[$kode]['id'], 'periode' => $bulan, 'nominal' => $peta[$kode]['nominal'],
                'pengajuan_id' => $this->ids['D'], 'dicatat_oleh' => 'Operator Uji', 'dicatat_peran' => 'operator', 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $svc = new PklLaporanBiaya();
        $h   = $svc->data(['q' => 'ZZUJI BY']);
        $per = [];
        foreach ($h['baris'] as $b) {
            $per[$b['nis']] = $b;
        }
        $this->cek('laporan memuat 10 siswa uji (semua anggota ajuan disetujui)', count($h['baris']) === 10, (string) count($h['baris']));
        $this->cek('s8: PKL + SPP + Tabungan + OSIS bulan surat → LUNAS, kekurangan 0', ($per[self::NIS . '8']['status'] ?? '') === 'lunas' && ($per[self::NIS . '8']['kekurangan'] ?? -1) === 0 && ($per[self::NIS . '8']['total_bayar'] ?? 0) === 300000 + 150000 + 50000 + 10000, json_encode($per[self::NIS . '8'] ?? null));
        $this->cek('s7: PKL + SPP + OSIS → SEBAGIAN, kekurangan Rp 50.000 (Tabungan)', ($per[self::NIS . '7']['status'] ?? '') === 'sebagian' && ($per[self::NIS . '7']['kekurangan'] ?? -1) === 50000, json_encode($per[self::NIS . '7'] ?? null));
        $this->cek('s1: catatan PKL dikoreksi (dihapus), tersisa SPP 3 bulan → SEBAGIAN, kekurangan Rp 360.000 (PKL + Tabungan + OSIS)', ($per[self::NIS . '1']['status'] ?? '') === 'sebagian' && ($per[self::NIS . '1']['kekurangan'] ?? -1) === 360000 && ($per[self::NIS . '1']['total_bayar'] ?? -1) === 450000, json_encode($per[self::NIS . '1'] ?? null));
        $this->cek('s10 (disetujui, tak ada catatan apa pun) → BELUM, kekurangan penuh Rp 510.000', ($per[self::NIS . '10']['status'] ?? '') === 'belum' && ($per[self::NIS . '10']['kekurangan'] ?? -1) === 510000 && ($per[self::NIS . '10']['total_bayar'] ?? -1) === 0, json_encode($per[self::NIS . '10'] ?? null));
        $this->cek('s4 (OSIS saja, beasiswa sudah dicabut) → SEBAGIAN; s5 keringanan tampil di catatan', ($per[self::NIS . '4']['status'] ?? '') === 'sebagian' && ($per[self::NIS . '5']['keringanan'] ?? '') === 'Orang tua menunggu gaji');
        $this->cek('ringkasan konsisten: lunas+sebagian+belum = siswa (10)', $h['ringkas']['lunas'] + $h['ringkas']['sebagian'] + $h['ringkas']['belum'] === $h['ringkas']['siswa'] && $h['ringkas']['siswa'] === 10);

        // beasiswa untuk saringan & aturan SPP
        $this->db->table('pkl_beasiswa')->insert(['siswa_id' => $s[9], 'sumber' => 'yayasan', 'keterangan' => null, 'berakhir_at' => null, 'dicatat_oleh' => 'Operator Uji', 'created_at' => date('Y-m-d H:i:s')]);
        $h2 = $svc->data(['q' => 'ZZUJI BY 9']);
        $this->cek('beasiswa yayasan (s9 sudah punya OSIS): SPP tidak ditagih, kekurangan 350.000 = PKL 300.000 + Tabungan 50.000; label sumber tampil', count($h2['baris']) === 1 && $h2['baris'][0]['kekurangan'] === 350000 && $h2['baris'][0]['beasiswa'] === 'Kebijakan Yayasan', json_encode($h2['baris'][0] ?? null));
        $this->cek('saringan "hanya beasiswa" → tepat s9', array_column($svc->data(['q' => 'ZZUJI BY', 'beasiswa' => true])['baris'], 'nis') === [self::NIS . '9']);
        $this->cek('saringan status lunas + q → tepat s8', array_column($svc->data(['q' => 'ZZUJI BY', 'status' => 'lunas'])['baris'], 'nis') === [self::NIS . '8']);
        $this->cek('saringan q tidak peka huruf besar-kecil, cari NIS & perusahaan', count($svc->data(['q' => 'zzujiby3'])['baris']) === 1 && count($svc->data(['q' => 'pt zzujiby delta'])['baris']) === 2);
        $this->cek('saringan tanggal: besok dst → kosong; hari ini s/d hari ini → 9 siswa (s10 tak punya catatan); format ngawur diabaikan', $svc->data(['q' => 'ZZUJI BY', 'dari' => date('Y-m-d', strtotime('+1 day'))])['baris'] === [] && count($svc->data(['q' => 'ZZUJI BY', 'dari' => date('Y-m-d'), 'sampai' => date('Y-m-d')])['baris']) === 9 && count($svc->data(['q' => 'ZZUJI BY', 'dari' => 'bukan-tanggal'])['baris']) === 10);
        $kelasId = (int) $per[self::NIS . '1']['kelas_id'];
        $this->cek('saringan kelas: semua siswa uji berada di satu kelas; kelas lain → kosong', count($svc->data(['q' => 'ZZUJI BY', 'kelas_id' => $kelasId])['baris']) === 10 && $svc->data(['q' => 'ZZUJI BY', 'kelas_id' => 999999999])['baris'] === []);
        $kodeJur = $per[self::NIS . '1']['jurusan_kode'];
        $this->cek('saringan jurusan mengikuti jurusan yang sudah disatukan (TKJ/TJKT/TKJT → TKJ)', $kodeJur === '' || count($svc->data(['q' => 'ZZUJI BY', 'jurusan' => $kodeJur])['baris']) === 10);

        // Excel
        $biner = $svc->excel($h, ['teks' => 'uji'], ['school_name' => 'SMK UJI', 'academic_year' => '2026/2027']);
        $tmp = tempnam(sys_get_temp_dir(), 'ujx');
        file_put_contents($tmp, $biner);
        $this->cek('Excel: berkas .xlsx sah (zip), 3 lembar: Rincian, Rekap Kelas, Rekap Jurusan', str_starts_with($biner, 'PK'));
        try {
            $x = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
            $this->cek('Excel: nama lembar benar', $x->getSheetNames() === ['Rincian', 'Rekap Kelas', 'Rekap Jurusan'], json_encode($x->getSheetNames()));
            $ws = $x->getSheetByName('Rincian');
            $kolomNis = null;
            $baris = null;
            foreach ($ws->toArray(null, true, false, false) as $i => $row) {
                if (($row[2] ?? null) === self::NIS . '8') {
                    $baris = $row;
                }
            }
            $this->cek('Excel: NIS tersimpan sebagai TEKS, nama & total benar untuk s8 (Rp 510.000, status Lunas)', $baris !== null && $baris[1] === 'ZZUJI BY 8' && in_array(510000, array_map('intval', $baris), true) && in_array('Lunas', $baris, true), json_encode($baris));
            $jumlah = null;
            foreach ($ws->toArray(null, true, true, false) as $row) {
                if (($row[1] ?? null) === 'JUMLAH') {
                    $jumlah = $row;
                }
            }
            $this->cek('Excel: baris JUMLAH berisi rumus SUM yang benar', $jumlah !== null && $ws->getCell('I' . ($ws->getHighestRow()))->isFormula());
            $rek = $x->getSheetByName('Rekap Kelas')->toArray(null, true, true, false);
            $this->cek('Excel: Rekap Kelas berisi jumlah siswa uji (10)', in_array(10, array_map(static fn ($r) => (int) ($r[1] ?? 0), $rek), true));
            $x->disconnectWorksheets();
        } catch (\Throwable $e) {
            $this->cek('Excel dapat dibaca kembali', false, $e->getMessage());
        }
        @unlink($tmp);
        $kosong = $svc->excel($svc->data(['q' => 'tidak-ada-siswa-begini']), ['teks' => ''], []);
        $this->cek('Excel: laporan kosong tetap menghasilkan berkas sah', str_starts_with($kosong, 'PK'));
    }

    // =================================================================
    // Pemulihan & pembersihan
    // =================================================================

    private function simpanPengaturanAsli(): void
    {
        $this->pengaturanAsli ??= (function () {
            $r = (new \App\Models\PklPengaturanModel())->ambil();
            unset($r['id']);

            return $r;
        })();
    }

    private function simpanBiayaAsli(): void
    {
        $this->biayaAsli ??= $this->db->table('pkl_biaya')->get()->getResultArray();
    }

    private function pulihkanBiaya(): void
    {
        if ($this->biayaAsli === null) {
            return;
        }
        foreach ($this->biayaAsli as $b) {
            $this->db->table('pkl_biaya')->where('id', $b['id'])->update(['nama' => $b['nama'], 'nominal' => $b['nominal'], 'aktif' => $b['aktif']]);
        }
    }

    private function bersihkan(): void
    {
        $ids = array_map('intval', array_column(
            $this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(),
            'id'
        ));
        if ($ids !== []) {
            $aj = array_map('intval', array_column(
                $this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(),
                'pengajuan_id'
            ));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete(); // CASCADE: anggota, riwayat, surat
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete(); // CASCADE: pembayaran, beasiswa, keringanan
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujiby', 'after')->delete();
        $this->db->table('audit_log')->like('deskripsi', 'ZZUJI BY')->delete();

        $this->pulihkanBiaya();
        $this->biayaAsli = null;
        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        PklHak::lupakan();
    }
}
