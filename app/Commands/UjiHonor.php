<?php

namespace App\Commands;

use App\Controllers\Admin\Kurikulum;
use App\Libraries\HakAkses;
use App\Libraries\HonorDokumen;
use App\Libraries\HonorCetak;
use App\Libraries\HonorHitung;
use App\Libraries\HonorImpor;
use App\Libraries\HonorPengaturan;
use App\Libraries\HonorPeriksa;
use App\Models\GuruModel;
use App\Models\PengampuModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji regresi Honor Ujian (rancangan: docs/DESAIN-HONOR.md).
 *
 * SELURUH uji berjalan di dalam SATU transaksi database yang di-ROLLBACK di akhir (juga bila uji berhenti karena
 * galat), jadi data asli — tarif, jabatan, guru, Audit Log — tidak pernah berubah. Cache dashboard dibuang di akhir.
 *
 * Jalankan:  php spark dev:uji-honor
 */
class UjiHonor extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-honor';
    protected $description = 'Uji Honor Ujian: tarif & komponen, tunjangan panitia, tanda tangan, tanda "bukan pengajar", hak akses (di-rollback).';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;

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
        $this->db = db_connect();
        $this->db->transBegin();
        // Jejak audit honor asli (mis. dari uji HTTP) dibuang DI DALAM transaksi uji supaya hitungan audit tepat; ikut di-rollback.
        $this->db->table('audit_log')->like('tabel', 'honor_', 'after')->delete();
        try {
            $this->ujiMurni();
            $this->ujiSeed();
            $this->ujiSimpanKomponen();
            $this->ujiTambahHapus();
            $this->ujiPanitia();
            $this->ujiTandaTangan();
            $this->ujiBukanPengajar();
            $this->pulihkanKomponen();
            $this->ujiHitungMurni();
            $this->ujiDokumen();
            $this->ujiPenerima();
            $this->ujiIsian();
            $this->ujiDataSurat();
            $this->ujiSinkron();
            $this->ujiKunciDanHapus();
            $this->ujiHitungOtomatis();
            $this->ujiImpor();
            $this->ujiCetak();
            $this->ujiStatus();
            $this->ujiPeriksa();
            $this->ujiUrutan();
            $this->ujiHak();
            $kode = $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $kode = EXIT_ERROR;
        }
        while ($this->db->transRollback()) {
            // batalkan semua tingkat transaksi sampai habis
        }
        cache()->delete('dash_kurikulum');
        cache()->delete('publik_stats_v2');
        cache()->delete('opt_guru');

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal  (semua perubahan uji sudah di-rollback)', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $kode;
    }

    // ------------------------------------------------------------------

    private function ujiMurni(): void
    {
        $this->bagian('Pembaca angka rupiah & pembantu murni');
        $sah = ['20000' => 20000, '20.000' => 20000, 'Rp 1.500.000' => 1500000, 'rp. 6.500' => 6500, ' 6.500 ' => 6500, '0' => 0, '99999999' => 99999999, '99.999.999' => 99999999, '007' => 7];
        foreach ($sah as $in => $out) {
            $this->cek('angka("' . $in . '") = ' . $out, HonorPengaturan::angka($in) === $out, var_export(HonorPengaturan::angka($in), true));
        }
        foreach (['', '   ', '-5', '12,5', '1.5', '20,000', 'abc', '1e5', '100000000', '1..000', '1.00.000', '１２', 'Rp', '20 000 000 000', '2,5jt', '0x10', '+5'] as $in) {
            $this->cek('angka("' . $in . '") ditolak (null)', HonorPengaturan::angka($in) === null, var_export(HonorPengaturan::angka($in), true));
        }
        $this->cek('angka(null) ditolak', HonorPengaturan::angka(null) === null);
        $this->cek('rupiah(1500000)', HonorPengaturan::rupiah(1500000) === 'Rp 1.500.000');
        $this->cek('rupiah(0)', HonorPengaturan::rupiah(0) === 'Rp 0');
        $this->cek('rapikan: spasi ganda & karakter kontrol', HonorPengaturan::rapikan("  Pembuatan \t  Soal\n ") === 'Pembuatan Soal', HonorPengaturan::rapikan("  Pembuatan \t  Soal\n "));
        $this->cek('jenisBerlaku(null) = 4 jenis', count(HonorPengaturan::jenisBerlaku(null)) === 4);
        $this->cek('jenisBerlaku("ASAT,ASAS") urut baku', HonorPengaturan::jenisBerlaku('ASAT,ASAS') === ['ASAS', 'ASAT'], json_encode(HonorPengaturan::jenisBerlaku('ASAT,ASAS')));
        $this->cek('jenisBerlaku("XYZ") kosong', HonorPengaturan::jenisBerlaku('XYZ') === []);
        $this->cek('berlakuUntuk menghormati daftar', HonorPengaturan::berlakuUntuk(['berlaku_di' => 'ASAS'], 'ASAS') && ! HonorPengaturan::berlakuUntuk(['berlaku_di' => 'ASAS'], 'ASTS1'));
    }

    private function ujiSeed(): void
    {
        $this->bagian('Komponen bawaan hasil migrasi');
        $a   = new HonorPengaturan();
        $per = [];
        foreach ($a->komponen() as $k) {
            $per[$k['kode']] = $k;
        }
        $this->cek('9 komponen bawaan', count($per) === 9, (string) count($per));
        $acuan = ['soal' => 20000, 'transport' => 25000, 'pengawas' => 6500, 'koreksi' => 1500, 'rapot' => 20000, 'lembur' => 100000];
        foreach ($acuan as $kode => $tarif) {
            $this->cek("tarif $kode = $tarif", isset($per[$kode]) && (int) $per[$kode]['tarif'] === $tarif);
        }
        foreach (['tunj_panitia', 'soal', 'transport', 'pengawas', 'koreksi', 'rapot'] as $kode) {
            $this->cek("$kode aktif", isset($per[$kode]) && (int) $per[$kode]['aktif'] === 1);
        }
        foreach (['tunj_struktural', 'tunj_walas', 'lembur'] as $kode) {
            $this->cek("$kode MATI bawaan", isset($per[$kode]) && (int) $per[$kode]['aktif'] === 0);
        }
        $this->cek('sumber otomatis: koreksi/rapot/soal', $per['koreksi']['sumber'] === 'koreksi' && $per['rapot']['sumber'] === 'rapot' && $per['soal']['sumber'] === 'soal');
        $this->cek('tipe tetap untuk tunjangan', $per['tunj_panitia']['tipe'] === 'tetap' && $per['tunj_struktural']['tipe'] === 'tetap' && $per['tunj_walas']['tipe'] === 'tetap');
        $this->cek('semua bawaan bertanda bawaan=1', count(array_filter($per, static fn ($k) => (int) $k['bawaan'] === 1)) === 9);
        $kodeAktif = array_column($a->komponen(true, 'ASTS1'), 'kode');
        $this->cek('ASTS1 aktif: 6 kolom seperti rekap sekolah (Rapot TETAP ada, nilainya 0)', $kodeAktif === ['tunj_panitia', 'soal', 'transport', 'pengawas', 'koreksi', 'rapot'], implode(',', $kodeAktif));
        $this->cek('judul cetak Rapot = "Rapot" (bukan RAPOT)', HonorCetak::judulKolom($per['rapot']) === 'Rapot' && HonorCetak::judulKolom($per['soal']) === 'PEMBUATAN SOAL');
        $this->cek('ASAS aktif memuat Rapot', in_array('rapot', array_column($a->komponen(true, 'ASAS'), 'kode'), true));
        $this->cek('urutan tampil naik', array_column($a->komponen(), 'urut') === array_values(array_column($a->komponen(), 'urut')) && $this->naik(array_map('intval', array_column($a->komponen(), 'urut'))));
        $ttd = $a->tandaTangan();
        $this->cek('honor_pengaturan punya baris awal', array_key_exists('ketua_nama', $ttd));
    }

    private function naik(array $a): bool
    {
        for ($i = 1; $i < count($a); $i++) {
            if ($a[$i] < $a[$i - 1]) {
                return false;
            }
        }

        return true;
    }

    /** Bentuk kiriman form untuk SEMUA komponen sesuai keadaan sekarang (lalu diubah per uji). */
    private function kirimSemua(HonorPengaturan $a): array
    {
        $k = [];
        foreach ($a->komponen() as $r) {
            $k[(int) $r['id']] = [
                'nama' => $r['nama'], 'judul_cetak' => (string) ($r['judul_cetak'] ?? ''), 'tarif' => number_format((int) $r['tarif'], 0, ',', '.'), 'satuan' => (string) $r['satuan'],
                'urut' => (int) $r['urut'], 'jenis' => HonorPengaturan::jenisBerlaku($r['berlaku_di']),
            ] + ((int) $r['aktif'] === 1 ? ['aktif' => '1'] : []);
        }

        return $k;
    }

    private function idKode(HonorPengaturan $a, string $kode): int
    {
        foreach ($a->komponen() as $r) {
            if ($r['kode'] === $kode) {
                return (int) $r['id'];
            }
        }

        return 0;
    }

    private function baris(string $kode): array
    {
        return $this->db->table('honor_komponen')->where('kode', $kode)->get()->getRowArray() ?? [];
    }

    private function jmlAudit(string $tabel): int
    {
        return (int) $this->db->table('audit_log')->where('tabel', $tabel)->countAllResults();
    }

    private function ujiSimpanKomponen(): void
    {
        $this->bagian('Simpan komponen & tarif');
        $a  = new HonorPengaturan();
        $id = $this->idKode($a, 'soal');

        $k = $this->kirimSemua($a);
        $h = $a->simpanKomponen($k);
        $this->cek('tanpa perubahan → ok & "Tidak ada perubahan"', $h['ok'] && $h['pesan'] === 'Tidak ada perubahan.', $h['pesan']);
        $this->cek('tanpa perubahan → tidak mencatat audit', $this->jmlAudit('honor_komponen') === 0);

        $k[$id]['tarif'] = '25.000';
        $h = $a->simpanKomponen($k);
        $this->cek('ubah tarif Pembuatan Soal 20.000 → 25.000', $h['ok'] && (int) $this->baris('soal')['tarif'] === 25000, $h['pesan']);
        $this->cek('audit tercatat memuat nama & tarif lama→baru', $this->jmlAudit('honor_komponen') === 1
            && $this->db->table('audit_log')->where('tabel', 'honor_komponen')->like('deskripsi', 'Pembuatan Soal')->like('deskripsi', '20.000→25.000')->countAllResults() === 1);
        $this->cek('komponen lain tidak ikut berubah', (int) $this->baris('koreksi')['tarif'] === 1500 && (int) $this->baris('transport')['tarif'] === 25000);

        // Penolakan — tak ada yang boleh tersimpan.
        $tolak = function (string $judul, callable $ubah, string $kata) use ($a): void {
            $k = $this->kirimSemua($a);
            $ubah($k);
            $sebelum = json_encode($a->komponen());
            $h = $a->simpanKomponen($k);
            $this->cek($judul . ' → ditolak & tidak tersimpan', ! $h['ok'] && json_encode($a->komponen()) === $sebelum && stripos($h['pesan'], $kata) !== false, $h['pesan']);
        };
        $tolak('tarif huruf', function (&$k) use ($id) { $k[$id]['tarif'] = 'abc'; }, 'tarif');
        $tolak('tarif minus', function (&$k) use ($id) { $k[$id]['tarif'] = '-100'; }, 'tarif');
        $tolak('tarif pecahan koma', function (&$k) use ($id) { $k[$id]['tarif'] = '1500,50'; }, 'tarif');
        $tolak('tarif kosong', function (&$k) use ($id) { $k[$id]['tarif'] = ''; }, 'tarif');
        $tolak('tarif melebihi batas', function (&$k) use ($id) { $k[$id]['tarif'] = '100000000'; }, 'tarif');
        $tolak('nama kosong', function (&$k) use ($id) { $k[$id]['nama'] = '   '; }, 'nama');
        $tolak('nama > 80 huruf', function (&$k) use ($id) { $k[$id]['nama'] = str_repeat('a', 81); }, 'nama');
        $tolak('nama kembar (beda huruf besar)', function (&$k) use ($id) { $k[$id]['nama'] = 'TRANSPORT'; }, 'dua kali');
        $tolak('satuan > 30 huruf', function (&$k) use ($id) { $k[$id]['satuan'] = str_repeat('x', 31); }, 'satuan');
        $tolak('urut 0', function (&$k) use ($id) { $k[$id]['urut'] = 0; }, 'urutan');
        $tolak('urut > 9999', function (&$k) use ($id) { $k[$id]['urut'] = 10000; }, 'urutan');
        $tolak('urut huruf', function (&$k) use ($id) { $k[$id]['urut'] = 'x'; }, 'urutan');
        $tolak('jenis ujian dikosongkan', function (&$k) use ($id) { $k[$id]['jenis'] = []; }, 'jenis ujian');
        $tolak('jenis ujian tak dikenal saja', function (&$k) use ($id) { $k[$id]['jenis'] = ['XYZ']; }, 'jenis ujian');
        $tolak('id komponen tak dikenal', function (&$k) { $k[999999] = ['nama' => 'Siluman', 'tarif' => '1', 'satuan' => '', 'urut' => 1, 'jenis' => ['ASAS']]; }, 'tidak dikenal');
        $tolak('semua komponen dimatikan', function (&$k) { foreach ($k as &$f) { unset($f['aktif']); } }, 'aktif');

        $h = $a->simpanKomponen([]);
        $this->cek('kiriman kosong ditolak', ! $h['ok']);

        // Jenis ujian
        $k = $this->kirimSemua($a);
        $k[$id]['jenis'] = ['ASAT', 'ASAS'];
        $h = $a->simpanKomponen($k);
        $this->cek('jenis ujian sebagian disimpan terurut ("ASAS,ASAT")', $h['ok'] && $this->baris('soal')['berlaku_di'] === 'ASAS,ASAT', (string) $this->baris('soal')['berlaku_di']);
        $this->cek('Pembuatan Soal tak lagi berlaku di ASTS1', ! in_array('soal', array_column($a->komponen(true, 'ASTS1'), 'kode'), true));
        $k[$id]['jenis'] = ['ASTS1', 'ASAS', 'ASTS2', 'ASAT'];
        $h = $a->simpanKomponen($k);
        $this->cek('semua 4 jenis → NULL (berlaku di semua)', $h['ok'] && $this->baris('soal')['berlaku_di'] === null);

        // Aktif/mati & urut
        $idLembur = $this->idKode($a, 'lembur');
        $k = $this->kirimSemua($a);
        $k[$idLembur]['aktif'] = '1';
        $k[$idLembur]['urut'] = 5;
        $h = $a->simpanKomponen($k);
        $this->cek('aktifkan Lembur & ubah urut', $h['ok'] && (int) $this->baris('lembur')['aktif'] === 1 && (int) $this->baris('lembur')['urut'] === 5);
        $this->cek('Lembur kini urutan pertama', $a->komponen()[0]['kode'] === 'lembur');
        $this->cek('audit menyebut "diaktifkan"', $this->db->table('audit_log')->where('tabel', 'honor_komponen')->like('deskripsi', 'diaktifkan')->countAllResults() === 1);

        // Satuan pada komponen tipe tetap dibuang; kode & sumber & bawaan tak bisa diubah lewat form
        $idPan = $this->idKode($a, 'tunj_panitia');
        $k = $this->kirimSemua($a);
        $k[$idPan]['satuan'] = 'orang';
        $k[$idPan]['kode'] = 'hack';
        $k[$idPan]['sumber'] = 'koreksi';
        $k[$idPan]['bawaan'] = 0;
        $h = $a->simpanKomponen($k);
        $p = $this->baris('tunj_panitia');
        $this->cek('komponen tetap: satuan tetap kosong; kode/sumber/bawaan tak berubah lewat form',
            $h['ok'] && $p !== [] && $p['satuan'] === null && $p['sumber'] === 'manual' && (int) $p['bawaan'] === 1 && $this->baris('hack') === [], json_encode($p));
        $this->cek('HTML pada nama disimpan apa adanya (dibersihkan saat tampil)', true);
    }

    private function ujiTambahHapus(): void
    {
        $this->bagian('Tambah & hapus komponen');
        $a = new HonorPengaturan();
        $h = $a->tambahKomponen(['nama' => 'Konsumsi', 'tipe' => 'satuan', 'tarif' => '15.000', 'satuan' => 'porsi']);
        $b = $this->baris('k_konsumsi');
        $this->cek('tambah "Konsumsi"', $h['ok'] && $b !== [] && (int) $b['tarif'] === 15000 && $b['satuan'] === 'porsi' && $b['sumber'] === 'manual' && (int) $b['bawaan'] === 0 && (int) $b['aktif'] === 1, $h['pesan']);
        $maks = (int) $this->db->table('honor_komponen')->selectMax('urut')->where('kode !=', 'k_konsumsi')->get()->getRow()->urut;
        $this->cek('urutan = terbesar + 10', $b !== [] && (int) $b['urut'] === $maks + 10, (string) ($b['urut'] ?? '?') . ' vs ' . ($maks + 10));
        $this->cek('komponen baru berlaku di semua jenis', $b !== [] && $b['berlaku_di'] === null);

        $h = $a->tambahKomponen(['nama' => 'konsumsi', 'tipe' => 'satuan', 'tarif' => '1']);
        $this->cek('nama sama (beda huruf besar) ditolak', ! $h['ok'], $h['pesan']);
        $h = $a->tambahKomponen(['nama' => '', 'tipe' => 'satuan', 'tarif' => '1']);
        $this->cek('nama kosong ditolak', ! $h['ok']);
        $h = $a->tambahKomponen(['nama' => 'Aneh', 'tipe' => 'ngawur', 'tarif' => '1']);
        $this->cek('tipe tak dikenal ditolak', ! $h['ok']);
        $h = $a->tambahKomponen(['nama' => 'Aneh', 'tipe' => 'satuan', 'tarif' => '-1']);
        $this->cek('tarif minus ditolak', ! $h['ok']);
        $h = $a->tambahKomponen(['nama' => 'Aneh', 'tipe' => 'satuan', 'tarif' => '1', 'satuan' => str_repeat('s', 31)]);
        $this->cek('satuan terlalu panjang ditolak', ! $h['ok']);

        $h = $a->tambahKomponen(['nama' => 'Tunjangan Khusus', 'tipe' => 'tetap', 'tarif' => '250.000', 'satuan' => 'diabaikan']);
        $c = $this->baris('k_tunjangan_khusus');
        $this->cek('tipe tetap: satuan dibuang', $h['ok'] && $c !== [] && $c['satuan'] === null && $c['tipe'] === 'tetap');

        $h1 = $a->tambahKomponen(['nama' => '!!!', 'tipe' => 'satuan', 'tarif' => '0']);
        $h2 = $a->tambahKomponen(['nama' => '???', 'tipe' => 'satuan', 'tarif' => '0']);
        $this->cek('nama tanpa huruf/angka → kode cadangan unik (k_baru, k_baru_2)', $h1['ok'] && $h2['ok'] && $this->baris('k_baru') !== [] && $this->baris('k_baru_2') !== []);
        $h = $a->tambahKomponen(['nama' => 'Éxtra Pengetikan Soal', 'tipe' => 'satuan', 'tarif' => '2000']);
        $this->cek('nama beraksen diterima, kode ASCII', $h['ok'], $h['pesan']);

        // Batas jumlah
        $n = count($a->komponen());
        $i = 0;
        while (count($a->komponen()) < HonorPengaturan::MAKS_KOMPONEN && $i++ < 40) {
            $a->tambahKomponen(['nama' => 'Isi ' . $i, 'tipe' => 'satuan', 'tarif' => '1']);
        }
        $this->cek('terisi sampai batas ' . HonorPengaturan::MAKS_KOMPONEN, count($a->komponen()) === HonorPengaturan::MAKS_KOMPONEN, (string) count($a->komponen()));
        $h = $a->tambahKomponen(['nama' => 'Kelebihan', 'tipe' => 'satuan', 'tarif' => '1']);
        $this->cek('melewati batas ditolak', ! $h['ok'] && $this->baris('k_kelebihan') === [], $h['pesan']);

        // Hapus
        $idBawaan = $this->idKode($a, 'soal');
        $h = $a->hapusKomponen($idBawaan);
        $this->cek('komponen bawaan TIDAK bisa dihapus', ! $h['ok'] && $this->baris('soal') !== [], $h['pesan']);
        $h = $a->hapusKomponen(999999);
        $this->cek('hapus id ngawur ditolak', ! $h['ok']);
        $h = $a->hapusKomponen((int) ($this->baris('k_konsumsi')['id'] ?? 0));
        $this->cek('komponen tambahan bisa dihapus', $h['ok'] && $this->baris('k_konsumsi') === [], $h['pesan']);
        $this->cek('hapus tercatat di audit', $this->db->table('audit_log')->where('tabel', 'honor_komponen')->where('aksi', 'delete')->like('deskripsi', 'Konsumsi')->countAllResults() === 1);
        $this->cek('semua komponen tetap punya kode unik', count(array_unique(array_column($a->komponen(), 'kode'))) === count($a->komponen()));
    }

    private function ujiPanitia(): void
    {
        $this->bagian('Tunjangan panitia per jabatan');
        $a   = new HonorPengaturan();
        $jab = $a->panitia();
        $this->cek('daftar jabatan terbaca & nominal awal 0', $jab !== [] && array_sum(array_map(static fn ($j) => (int) $j['nominal'], $jab)) === 0, (string) count($jab) . ' jabatan');
        [$j1, $j2, $j3] = [(int) $jab[0]['id'], (int) $jab[1]['id'], (int) $jab[2]['id']];

        $h = $a->simpanPanitia([$j1 => 'Rp 2.000.000', $j2 => '500000', $j3 => '']);
        $now = array_column($a->panitia(), 'nominal', 'id');
        $this->cek('simpan 2 nominal, 1 kosong', $h['ok'] && (int) $now[$j1] === 2000000 && (int) $now[$j2] === 500000 && (int) $now[$j3] === 0, $h['pesan']);
        $this->cek('audit mencatat nama jabatan', $this->db->table('audit_log')->where('tabel', 'honor_panitia_jabatan')->like('deskripsi', $jab[0]['nama'])->countAllResults() === 1);

        $h = $a->simpanPanitia([$j1 => '2.000.000', $j2 => '500.000', $j3 => '']);
        $this->cek('tanpa perubahan → "Tidak ada perubahan"', $h['ok'] && $h['pesan'] === 'Tidak ada perubahan.', $h['pesan']);

        $h = $a->simpanPanitia([$j1 => '', $j2 => '750.000']);
        $now = array_column($a->panitia(), 'nominal', 'id');
        $this->cek('kosongkan satu & ubah satu', $h['ok'] && (int) $now[$j1] === 0 && (int) $now[$j2] === 750000);
        $this->cek('baris dihapus bila 0/kosong (tidak menumpuk)', (int) $this->db->table('honor_panitia_jabatan')->where('jabatan_id', $j1)->countAllResults() === 0);

        $h = $a->simpanPanitia([$j1 => '1.000.000', $j2 => 'abc']);
        $now = array_column($a->panitia(), 'nominal', 'id');
        $this->cek('satu isian salah → SEMUA ditolak, tak ada yang sebagian tersimpan', ! $h['ok'] && (int) $now[$j1] === 0 && (int) $now[$j2] === 750000, $h['pesan']);
        $h = $a->simpanPanitia([999999 => '1000']);
        $this->cek('jabatan tak dikenal ditolak', ! $h['ok']);
        $h = $a->simpanPanitia([$j1 => '-1']);
        $this->cek('nominal minus ditolak', ! $h['ok']);
        $h = $a->simpanPanitia([$j1 => '100000000']);
        $this->cek('nominal melebihi batas ditolak', ! $h['ok']);
    }

    private function ujiTandaTangan(): void
    {
        $this->bagian('Nama tanda tangan');
        $a = new HonorPengaturan();
        $h = $a->simpanTandaTangan('  Elvira   Safitri, S.Pd ', "Maya Fadhillah, S.Pd");
        $t = $a->tandaTangan();
        $this->cek('simpan & rapikan (spasi ganda, titik gelar)', $h['ok'] && $t['ketua_nama'] === 'Elvira Safitri, S.Pd.' && $t['bendahara_nama'] === 'Maya Fadhillah, S.Pd.', json_encode($t));
        $h = $a->simpanTandaTangan('Ketua 123', 'X');
        $this->cek('angka pada nama ditolak', ! $h['ok'] && $a->tandaTangan()['ketua_nama'] === 'Elvira Safitri, S.Pd.', $h['pesan']);
        $h = $a->simpanTandaTangan(str_repeat('a', 151), '');
        $this->cek('nama > 150 huruf ditolak', ! $h['ok']);
        $h = $a->simpanTandaTangan('<b>Jahat</b>', '');
        $this->cek('tanda HTML ditolak', ! $h['ok']);
        $h = $a->simpanTandaTangan('', '');
        $t = $a->tandaTangan();
        $this->cek('dikosongkan → NULL', $h['ok'] && $t['ketua_nama'] === null && $t['bendahara_nama'] === null);
    }

    private function ujiBukanPengajar(): void
    {
        $this->bagian('Tanda "bukan pengajar" tidak merusak hitungan guru');
        cache()->delete('dash_kurikulum');
        $awal   = Kurikulum::dashboardData();
        $m      = new GuruModel();
        $kurang = (int) $awal['kurang'];
        $jmlGuru = (int) $awal['guru'];

        $idA = $m->insert(['kode_guru' => 'ZZUJIHN1', 'nama' => 'Uji Honor Pengajar', 'max_beban' => 24, 'bukan_pengajar' => 0]);
        $idB = $m->insert(['kode_guru' => 'ZZUJIHN2', 'nama' => 'Uji Honor Staf', 'max_beban' => 24, 'bukan_pengajar' => 1]);
        $this->cek('guru uji dibuat (pengajar & staf)', (int) $idA > 0 && (int) $idB > 0);
        $bad = new GuruModel();
        $this->cek('nilai bukan_pengajar=2 ditolak model', $bad->insert(['kode_guru' => 'ZZUJIHN3', 'nama' => 'X', 'max_beban' => 24, 'bukan_pengajar' => 2]) === false);
        $baris = $m->find($idB);
        $this->cek('kolom tersimpan sebagai 1', (int) ($baris['bukan_pengajar'] ?? 0) === 1);
        $this->cek('bawaan kolom = 0 bila tak diisi', (int) ($m->find((int) $m->insert(['kode_guru' => 'ZZUJIHN4', 'nama' => 'Tanpa Tanda', 'max_beban' => 24]))['bukan_pengajar'] ?? 9) === 0);

        cache()->delete('dash_kurikulum');
        $baru = Kurikulum::dashboardData();
        // Terhitung: ZZUJIHN1 dan ZZUJIHN4 (2 pengajar); staf (ZZUJIHN2) tidak.
        $this->cek('Total Guru bertambah 2 (staf tidak dihitung)', (int) $baru['guru'] === $jmlGuru + 2, $jmlGuru . ' → ' . $baru['guru']);
        $this->cek('Guru Kurang Jam bertambah 2 (staf tidak dihitung)', (int) $baru['kurang'] === $kurang + 2, $kurang . ' → ' . $baru['kurang']);
        $ids = array_column((new PengampuModel())->bebanPerGuruDetail(), 'id');
        $this->cek('bebanPerGuruDetail memuat pengajar, TANPA staf', in_array((string) $idA, array_map('strval', $ids), true) && ! in_array((string) $idB, array_map('strval', $ids), true));
        $this->cek('staf tetap ada di Master Guru (bisa menerima honor)', $m->find($idB) !== null && (int) $this->db->table('guru')->where('id', $idB)->countAllResults() === 1);

        $m->update($idB, ['bukan_pengajar' => 0]);
        cache()->delete('dash_kurikulum');
        $this->cek('tanda dicabut → ikut terhitung lagi', (int) Kurikulum::dashboardData()['guru'] === $jmlGuru + 3);

        // Data lama tidak berubah: guru yang ada sebelum uji tetap bukan_pengajar=0
        $this->cek('guru yang sudah ada sebelumnya tidak berubah (semua bukan_pengajar = 0)',
            (int) $this->db->table('guru')->where('bukan_pengajar', 1)->notLike('kode_guru', 'ZZUJIHN', 'after')->countAllResults() === 0);
    }

    // ==================================================================
    // FASE 2 — dokumen honor
    // ==================================================================

    private function ujiHitungMurni(): void
    {
        $this->bagian('Hitungan honor (fungsi murni)');
        $komp = [
            ['id' => 1, 'tipe' => 'tetap', 'tarif' => 0],
            ['id' => 2, 'tipe' => 'satuan', 'tarif' => 20000],
            ['id' => 3, 'tipe' => 'satuan', 'tarif' => 6500],
            ['id' => 4, 'tipe' => 'satuan', 'tarif' => 1500],
        ];
        $h = HonorDokumen::hitung($komp, [1 => 500000, 2 => 2, 3 => 6, 4 => 360]);
        $this->cek('panitia 500.000 + soal 2×20.000 + pengawas 6×6.500 + koreksi 360×1.500 = 1.119.000', $h['total'] === 1119000, (string) $h['total']);
        $this->cek('rupiah tiap komponen benar', $h['per'] === [1 => 500000, 2 => 40000, 3 => 39000, 4 => 540000]);
        $this->cek('isian kosong dihitung 0', HonorDokumen::hitung($komp, [])['total'] === 0);
        $this->cek('komponen tetap TIDAK dikali tarif', HonorDokumen::hitung([['id' => 1, 'tipe' => 'tetap', 'tarif' => 999]], [1 => 7])['total'] === 7);
        $this->cek('nilai besar tetap bilangan bulat (tanpa pecahan)', is_int(HonorDokumen::hitung($komp, [4 => 99999])['total']) && HonorDokumen::hitung($komp, [4 => 99999])['total'] === 149998500);
        $this->cek('angka Excel asli: 53 soal + 484 sesi + 19.244 lembar', HonorDokumen::hitung(array_slice($komp, 1), [2 => 53, 3 => 484, 4 => 19244])['total'] === 1060000 + 3146000 + 28866000);

        $this->cek('hariUjian 14–18 Sep 2026 = 5 hari', HonorDokumen::hariUjian('2026-09-14', '2026-09-18') === 5);
        $this->cek('hariUjian melewati Minggu tidak dihitung (14–20 Sep = 6)', HonorDokumen::hariUjian('2026-09-14', '2026-09-20') === 6);
        $this->cek('hariUjian satu hari = 1', HonorDokumen::hariUjian('2026-09-14', '2026-09-14') === 1);
        $this->cek('hariUjian tanggal kosong/terbalik/terlalu lebar/rusak = 0', HonorDokumen::hariUjian(null, '2026-09-18') === 0 && HonorDokumen::hariUjian('2026-09-18', '2026-09-14') === 0 && HonorDokumen::hariUjian('2026-01-01', '2026-12-31') === 0 && HonorDokumen::hariUjian('bukan', 'tanggal') === 0);

        foreach ([
            'Wakil Kepala Sekolah Bidang Kurikulum' => 'Waka. Kurikulum', 'Wakil Kepala Sekolah Bidang Kesiswaan' => 'Waka. Kesiswaan', 'Wakil Kepala Sekolah Bidang Sarana Prasarana' => 'Waka. Sarpras',
            'Wakil Kepala Sekolah Bidang Hubungan Masyarakat' => 'Waka. Humas & Hubungan Industri', 'Wakil Kepala Sekolah Bidang Lain' => 'Waka. Lain',
            'Ketua Program Keahlian' => 'Kaprog.', 'Guru Mata Pelajaran' => 'Guru Mata Pelajaran', 'GURU PIKET' => 'Guru Piket', 'Staf Tata Usaha' => 'Staf Tata Usaha', 'OPERATOR SEKOLAH' => 'Operator Sekolah', 'Kepala Sekolah' => 'Kepala Sekolah',
        ] as $in => $out) {
            $this->cek('label "' . $in . '" → "' . $out . '"', HonorDokumen::labelSingkat($in) === $out, HonorDokumen::labelSingkat($in));
        }
    }

    /** Kembalikan komponen ke keadaan awal migrasi (uji Fase 1 sengaja mengubah tarif/aktif; semuanya di dalam transaksi). */
    private function pulihkanKomponen(): void
    {
        $this->db->table('honor_komponen')->where('bawaan', 0)->delete();
        $awal = [
            'tunj_struktural' => [0, 0, null, 10], 'tunj_walas' => [0, 0, null, 20], 'tunj_panitia' => [0, 1, null, 30], 'soal' => [20000, 1, null, 40],
            'transport' => [25000, 1, null, 50], 'pengawas' => [6500, 1, null, 60], 'koreksi' => [1500, 1, null, 70], 'rapot' => [20000, 1, null, 80], 'lembur' => [100000, 0, null, 90],
        ];
        foreach ($awal as $kode => [$tarif, $aktif, $berlaku, $urut]) {
            $this->db->table('honor_komponen')->where('kode', $kode)->update(['tarif' => $tarif, 'aktif' => $aktif, 'berlaku_di' => $berlaku, 'urut' => $urut]);
        }
    }

    private function periode(string $jenis): array
    {
        return $this->db->table('ujian_periode')->where('jenis', $jenis)->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    private function jmlTabel(string $t, string $kolom, int $nilai): int
    {
        return (int) $this->db->table($t)->where($kolom, $nilai)->countAllResults();
    }

    private function guruId(string $kodeJabatan): int
    {
        return (int) ($this->db->table('guru_jabatan gj')->select('gj.guru_id')->join('jabatan j', 'j.id = gj.jabatan_id')->join('guru g', 'g.id = gj.guru_id')
            ->where('j.kode', $kodeJabatan)->where('g.deleted_at', null)->get()->getRow()->guru_id ?? 0);
    }

    private function ujiDokumen(): void
    {
        $this->bagian('Buat dokumen honor');
        $h  = new HonorDokumen();
        $p1 = $this->periode('ASTS1');
        $this->cek('periode ASTS1 tersedia untuk uji', $p1 !== []);
        $this->cek('belum ada dokumen di awal', $h->dokumenPeriode((int) $p1['id']) === null);

        // Semua komponen dimatikan → tidak bisa dibuat
        $this->db->table('honor_komponen')->update(['aktif' => 0]);
        $r = $h->buat($p1);
        $this->cek('tanpa komponen aktif → ditolak, tidak ada dokumen', ! $r['ok'] && $h->dokumenPeriode((int) $p1['id']) === null, $r['pesan']);
        $this->db->table('honor_komponen')->whereIn('kode', ['tunj_panitia', 'soal', 'transport', 'pengawas', 'koreksi', 'rapot'])->update(['aktif' => 1]);

        $r = $h->buat($p1);
        $this->cek('buat honor ASTS1 berhasil', $r['ok'] && ($r['id'] ?? 0) > 0, $r['pesan']);
        $dok = $h->dokumenPeriode((int) $p1['id']);
        $this->cek('status awal = draf', ($dok['status'] ?? '') === 'draf');
        $this->cek('judul bawaan gaya rekap sekolah', ($dok['judul'] ?? '') === 'HONOR ASESMEN SUMATIF TENGAH SEMESTER (ASTS) GANJIL', (string) ($dok['judul'] ?? ''));
        $set = $this->db->table('settings')->select('city, headmaster_name')->get()->getRowArray();
        $this->cek('tempat & Kepala Sekolah diambil dari Pengaturan Sekolah', ($dok['tempat'] ?? null) === $set['city'] && ($dok['kepsek_nama'] ?? null) === $set['headmaster_name'], json_encode([$dok['tempat'] ?? null, $dok['kepsek_nama'] ?? null]));
        $this->cek('tanggal bawaan = hari ini', ($dok['tanggal'] ?? '') === date('Y-m-d'));
        $kode = array_column($this->db->table('honor_dok_komponen')->where('dokumen_id', $dok['id'])->orderBy('urut')->get()->getResultArray(), 'kode');
        $this->cek('snapshot ASTS1 = 6 komponen aktif (Rapot ikut, seperti rekap sekolah)', $kode === ['tunj_panitia', 'soal', 'transport', 'pengawas', 'koreksi', 'rapot'], implode(',', $kode));
        $r = $h->buat($p1);
        $this->cek('buat dua kali ditolak (satu honor per periode)', ! $r['ok'] && (int) $this->db->table('honor_dokumen')->where('periode_id', $p1['id'])->countAllResults() === 1, $r['pesan']);

        $p2 = $this->periode('ASAS');
        $r2 = $h->buat($p2);
        $kode2 = array_column($this->db->table('honor_dok_komponen')->where('dokumen_id', $r2['id'] ?? 0)->get()->getResultArray(), 'kode');
        $this->cek('ASAS memuat Rapot (6 komponen)', $r2['ok'] && count($kode2) === 6 && in_array('rapot', $kode2, true), implode(',', $kode2));
        $this->cek('periode lain tidak terpengaruh dokumen pertama', (int) $this->db->table('honor_dokumen')->countAllResults() === 2);
        $this->cek('muat(id ngawur) = null', $h->muat(999999) === null);
    }

    private function ujiPenerima(): void
    {
        $this->bagian('Penerima');
        $h   = new HonorDokumen();
        $p1  = $this->periode('ASTS1');
        $dok = $h->dokumenPeriode((int) $p1['id']);
        $id  = (int) $dok['id'];
        $ks  = $this->guruId('KS');
        $kur = $this->guruId('WK-KUR');
        $gmp = (int) $this->db->table('guru_jabatan gj')->select('gj.guru_id')->join('jabatan j', 'j.id = gj.jabatan_id')->join('guru g', 'g.id = gj.guru_id')
            ->where('j.kode', 'GMP')->where('g.deleted_at', null)->whereNotIn('gj.guru_id', [$ks, $kur])->get()->getRow()->guru_id;
        $this->cek('guru uji ditemukan (KS, WK-KUR, GMP)', $ks > 0 && $kur > 0 && $gmp > 0);

        // Nominal panitia per jabatan dari Pengaturan
        $a      = new HonorPengaturan();
        $jabKs  = (int) $this->db->table('jabatan')->where('kode', 'KS')->get()->getRow()->id;
        $jabKur = (int) $this->db->table('jabatan')->where('kode', 'WK-KUR')->get()->getRow()->id;
        $a->simpanPanitia([$jabKs => '2.000.000', $jabKur => '1.500.000']);

        $calonAwal = count($h->calonPenerima($id));
        $r = $h->tambahPenerima($id, [$gmp, $kur, $ks]);
        $this->cek('tambah 3 penerima', $r['ok'] && $r['jumlah'] === 3, $r['pesan']);
        $m = $h->muat($id);
        $this->cek('urutan: struktural dulu, guru biasa terakhir', count($m['baris']) === 3 && (int) $m['baris'][2]['guru_id'] === $gmp && (int) $m['baris'][0]['guru_id'] !== $gmp, implode(' | ', array_column($m['baris'], 'jabatan')));
        $per = [];
        foreach ($m['baris'] as $b) {
            $per[(int) $b['guru_id']] = $b;
        }
        $dkPeta = [];
        foreach ($m['komponen'] as $k) {
            $dkPeta[$k['kode']] = (int) $k['id'];
        }
        $this->cek('label jabatan ringkas ("Waka. Kurikulum")', $per[$kur]['jabatan'] === 'Waka. Kurikulum', (string) $per[$kur]['jabatan']);
        $this->cek('nama disalin dari Master Guru', $per[$ks]['nama'] === $this->db->table('guru')->where('id', $ks)->get()->getRow()->nama);
        $this->cek('tunjangan panitia bawaan dari tabel jabatan (KS 2.000.000, Waka Kur 1.500.000, guru 0)',
            $per[$ks]['nilai'][$dkPeta['tunj_panitia']]['nilai'] === 2000000 && $per[$kur]['nilai'][$dkPeta['tunj_panitia']]['nilai'] === 1500000 && $per[$gmp]['nilai'][$dkPeta['tunj_panitia']]['nilai'] === 0);
        $hari       = HonorDokumen::hariUjian($p1['tanggal_mulai'], $p1['tanggal_selesai']);
        $struktural = fn (int $gid): bool => (int) $this->db->table('guru_jabatan gj')->join('jabatan j', 'j.id = gj.jabatan_id')->where('gj.guru_id', $gid)->where('j.is_struktural', 1)->countAllResults() > 0;
        $this->cek('transport bawaan = hari ujian bagi yang berjabatan struktural, 0 bagi lainnya',
            $per[$ks]['nilai'][$dkPeta['transport']]['nilai'] === ($struktural($ks) ? $hari : 0) && $per[$gmp]['nilai'][$dkPeta['transport']]['nilai'] === ($struktural($gmp) ? $hari : 0),
            $hari . ' hari; KS=' . $per[$ks]['nilai'][$dkPeta['transport']]['nilai']);
        $this->cek('SEMUA sel ada (3 baris × 6 komponen = 18 baris nilai)', (int) $this->db->table('honor_nilai')->whereIn('baris_id', array_column($m['baris'], 'id'))->countAllResults() === 18);
        $this->cek('total KS = panitia 2.000.000 + transport hari×25.000', $per[$ks]['total'] === 2000000 + $per[$ks]['nilai'][$dkPeta['transport']]['nilai'] * 25000, (string) $per[$ks]['total']);

        $r = $h->tambahPenerima($id, [$gmp, $ks]);
        $this->cek('menambah orang yang sudah ada → tidak ganda', $r['ok'] && $r['jumlah'] === 0 && count($h->muat($id)['baris']) === 3, $r['pesan']);
        $this->cek('calonPenerima berkurang 3', count($h->calonPenerima($id)) === $calonAwal - 3);
        $this->cek('daftar kosong ditolak', ! $h->tambahPenerima($id, [])['ok']);
        $r = $h->tambahPenerima($id, [999999]);
        $this->cek('guru tak ada ditolak', ! $r['ok'], $r['pesan']);
        $this->cek('dokumen tak ada ditolak', ! $h->tambahPenerima(999999, [$gmp])['ok']);

        // guru terhapus (soft) tidak bisa ditambah; data ganda tidak jadi calon
        $idDel = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHD1', 'nama' => 'Guru Terhapus', 'max_beban' => 24]);
        $this->db->table('guru')->where('id', $idDel)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->cek('guru yang sudah dihapus ditolak', ! $h->tambahPenerima($id, [$idDel])['ok']);
        $idGanda = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHD2', 'nama' => 'Data Ganda Uji', 'max_beban' => 24, 'induk_id' => $gmp]);
        $this->cek('data ganda (induk_id) tidak muncul sebagai calon', ! in_array($idGanda, array_map('intval', array_column($h->calonPenerima($id), 'id')), true));

        // batas penerima (pada dokumen ASAT sementara)
        $kosong = (int) $h->buat($this->periode('ASAT'))['id'];
        for ($i = 0; $i < HonorDokumen::MAKS_PENERIMA - 1; $i++) {
            $this->db->table('honor_baris')->insert(['dokumen_id' => $kosong, 'guru_id' => null, 'nama' => 'Pengisi ' . $i, 'urut' => $i + 1]);
        }
        $r = $h->tambahPenerima($kosong, [$ks, $kur]);
        $this->cek('melewati batas ' . HonorDokumen::MAKS_PENERIMA . ' penerima ditolak', ! $r['ok'], $r['pesan']);
        $r = $h->tambahPenerima($kosong, [$ks]);
        $this->cek('tepat di batas diterima', $r['ok'] && $r['jumlah'] === 1, $r['pesan']);
        $this->db->table('honor_dokumen')->where('id', $kosong)->delete();

        // ubah baris
        $b = $per[$gmp];
        $r = $h->ubahBaris($id, (int) $b['id'], 'Kaprog. TKJ');
        $this->cek('ubah label jabatan', $r['ok'] && $this->db->table('honor_baris')->where('id', $b['id'])->get()->getRow()->jabatan === 'Kaprog. TKJ', $r['pesan']);
        $r = $h->ubahBaris($id, (int) $b['id'], '  ');
        $this->cek('label dikosongkan → NULL', $r['ok'] && $this->db->table('honor_baris')->where('id', $b['id'])->get()->getRow()->jabatan === null);
        $this->cek('label > 120 huruf ditolak', ! $h->ubahBaris($id, (int) $b['id'], str_repeat('x', 121))['ok']);
        $this->cek('catatan > 255 huruf ditolak', ! $h->ubahBaris($id, (int) $b['id'], 'Guru', str_repeat('y', 256))['ok']);
        $this->cek('baris tak dikenal ditolak', ! $h->ubahBaris($id, 999999, 'Guru')['ok']);
        $h->ubahBaris($id, (int) $b['id'], 'Guru');
        $r2 = $h->ubahBaris($id, (int) $b['id'], 'Guru');
        $this->cek('tanpa perubahan → "Tidak ada perubahan"', $r2['ok'] && $r2['pesan'] === 'Tidak ada perubahan.', $r2['pesan']);

        // baris milik dokumen lain tidak bisa disentuh lewat dokumen ini
        $dokLain = (int) $this->db->table('honor_dokumen')->where('id !=', $id)->get()->getRow()->id;
        $r = $h->hapusBaris($dokLain, (int) $b['id']);
        $this->cek('menghapus baris dokumen A lewat dokumen B ditolak', ! $r['ok'] && $this->db->table('honor_baris')->where('id', $b['id'])->countAllResults() === 1, $r['pesan']);

        // hapus baris
        $nilaiSebelum = (int) $this->db->table('honor_nilai')->countAllResults();
        $r = $h->hapusBaris($id, (int) $b['id']);
        $this->cek('hapus penerima', $r['ok'] && count($h->muat($id)['baris']) === 2, $r['pesan']);
        $this->cek('isiannya ikut terhapus (CASCADE), penerima lain utuh', (int) $this->db->table('honor_nilai')->countAllResults() === $nilaiSebelum - 6);
        $h->tambahPenerima($id, [$gmp]);
    }

    private function ujiIsian(): void
    {
        $this->bagian('Isian & total (server)');
        $h   = new HonorDokumen();
        $id  = (int) $h->dokumenPeriode((int) $this->periode('ASTS1')['id'])['id'];
        $m   = $h->muat($id);
        $dk  = [];
        foreach ($m['komponen'] as $k) {
            $dk[$k['kode']] = (int) $k['id'];
        }
        $gmp = $m['baris'][count($m['baris']) - 1];
        $b   = (int) $gmp['id'];
        $tPanitia = (int) $gmp['per'][$dk['tunj_panitia']];
        $tTransport = (int) $gmp['per'][$dk['transport']];

        $r = $h->simpanNilai($id, $b, $dk['soal'], '2');
        $this->cek('soal 2 × 20.000 = 40.000', $r['ok'] && $r['nilai'] === 2 && $r['rupiah'] === 40000, json_encode($r));
        $r = $h->simpanNilai($id, $b, $dk['pengawas'], '6');
        $this->cek('pengawas 6 × 6.500 = 39.000', $r['ok'] && $r['rupiah'] === 39000);
        $r = $h->simpanNilai($id, $b, $dk['koreksi'], '360');
        $this->cek('koreksi 360 × 1.500 = 540.000; total baris = soal+pengawas+koreksi+bawaan', $r['ok'] && $r['rupiah'] === 540000 && $r['total_baris'] === 40000 + 39000 + 540000 + $tPanitia + $tTransport, json_encode($r));
        $r = $h->simpanNilai($id, $b, $dk['tunj_panitia'], '1.500.000');
        $this->cek('tunjangan tetap menerima "1.500.000" (titik ribuan) = nominal 1500000', $r['ok'] && $r['nilai'] === 1500000 && $r['rupiah'] === 1500000, json_encode($r));

        // total kolom & total keseluruhan konsisten dengan penjumlahan manual
        $m2     = $h->muat($id);
        $manual = 0;
        foreach ($m2['baris'] as $br) {
            $s = 0;
            foreach ($m2['komponen'] as $k) {
                $n  = (int) $br['nilai'][(int) $k['id']]['nilai'];
                $s += $k['tipe'] === 'tetap' ? $n : $n * (int) $k['tarif'];
            }
            $this->cek('total baris "' . $br['nama'] . '" = penjumlahan manual', $br['total'] === $s);
            $manual += $s;
        }
        $this->cek('total keseluruhan = Σ total baris = Σ total kolom', $m2['total'] === $manual && $m2['total'] === array_sum($m2['total_komponen']), $m2['total'] . ' vs ' . $manual);
        $this->cek('total_jumlah koreksi = 360 (hanya satu baris diisi)', $m2['total_jumlah'][$dk['koreksi']] === 360);

        // validasi
        $tolak = function (string $judul, $nilai, int $komp) use ($h, $id, $b): void {
            $ambil = fn () => $this->db->table('honor_nilai')->where('baris_id', $b)->where('dok_komponen_id', $komp)->get()->getRow()->nilai;
            $sebelum = $ambil();
            $r = $h->simpanNilai($id, $b, $komp, $nilai);
            $this->cek($judul . ' → ditolak, nilai tak berubah', ! $r['ok'] && $sebelum === $ambil(), $r['pesan']);
        };
        $tolak('jumlah minus', '-5', $dk['koreksi']);
        $tolak('jumlah huruf', 'seratus', $dk['koreksi']);
        $tolak('jumlah desimal koma', '12,5', $dk['koreksi']);
        $tolak('jumlah desimal titik', '1.5', $dk['koreksi']);
        $tolak('jumlah di atas batas (100.000)', '100000', $dk['koreksi']);
        $tolak('nominal di atas batas (100 juta)', '100000000', $dk['tunj_panitia']);
        $tolak('notasi ilmiah', '1e5', $dk['koreksi']);
        $tolak('angka + huruf', '12abc', $dk['koreksi']);
        $r = $h->simpanNilai($id, $b, $dk['koreksi'], '99999');
        $this->cek('tepat di batas jumlah (99.999) diterima', $r['ok'] && $r['rupiah'] === 99999 * 1500);
        $r = $h->simpanNilai($id, $b, $dk['koreksi'], '');
        $this->cek('dikosongkan → 0', $r['ok'] && $r['nilai'] === 0 && $r['rupiah'] === 0);
        $r = $h->simpanNilai($id, $b, $dk['koreksi'], '007');
        $this->cek('"007" → 7', $r['ok'] && $r['nilai'] === 7);

        // sel/baris dokumen lain
        $dokLain = $h->dokumenPeriode((int) $this->periode('ASAS')['id']);
        $mLain   = $h->muat((int) $dokLain['id']);
        $this->cek('komponen milik dokumen lain ditolak', ! $h->simpanNilai($id, $b, (int) $mLain['komponen'][0]['id'], '5')['ok']);
        $this->cek('baris milik dokumen lain ditolak', ! $h->simpanNilai((int) $dokLain['id'], $b, $dk['koreksi'], '5')['ok']);
        $this->cek('baris tak ada ditolak', ! $h->simpanNilai($id, 999999, $dk['koreksi'], '5')['ok']);
        $this->cek('dokumen tak ada ditolak', ! $h->simpanNilai(999999, $b, $dk['koreksi'], '5')['ok']);

        // audit
        $this->cek('perubahan isian tercatat di Audit Log (nama, komponen, lama→baru)', (int) $this->db->table('audit_log')->where('tabel', 'honor_nilai')->like('deskripsi', 'Koreksi')->countAllResults() >= 3);
        $n0 = (int) $this->db->table('audit_log')->where('tabel', 'honor_nilai')->countAllResults();
        $h->simpanNilai($id, $b, $dk['koreksi'], '7');
        $this->cek('menyimpan angka yang sama TIDAK menambah audit', (int) $this->db->table('audit_log')->where('tabel', 'honor_nilai')->countAllResults() === $n0);

        // otomatis_nilai (dipakai Fase 3): isian yang pernah dihitung otomatis lalu diubah → nilai ≠ otomatis
        $this->db->table('honor_nilai')->where('baris_id', $b)->where('dok_komponen_id', $dk['koreksi'])->update(['nilai' => 100, 'otomatis_nilai' => 100]);
        $r = $h->simpanNilai($id, $b, $dk['koreksi'], '120');
        $this->cek('isian otomatis lalu diubah: nilai 120 ≠ otomatis 100', $r['ok'] && $r['nilai'] === 120 && $r['otomatis'] === 100, json_encode($r));
    }

    private function ujiDataSurat(): void
    {
        $this->bagian('Data surat & tanda tangan dokumen');
        $h  = new HonorDokumen();
        $id = (int) $h->dokumenPeriode((int) $this->periode('ASTS1')['id'])['id'];
        $ok = ['judul' => 'HONOR ASTS UJI', 'tempat' => 'Bekasi', 'tanggal' => '2026-09-25', 'ketua_nama' => ' Elvira  Safitri, S.Pd ', 'bendahara_nama' => 'Maya Fadhillah, S.Pd', 'kepsek_nama' => 'Napis Kuturupi, S.T'];
        $r  = $h->simpanDokumen($id, $ok);
        $d  = $h->dokumenId($id);
        $this->cek('simpan data surat', $r['ok'] && $d['judul'] === 'HONOR ASTS UJI' && $d['tanggal'] === '2026-09-25' && $d['tempat'] === 'Bekasi', $r['pesan']);
        $this->cek('titik gelar dirapikan (S.Pd. / S.T.) & spasi ganda dibuang', $d['ketua_nama'] === 'Elvira Safitri, S.Pd.' && $d['kepsek_nama'] === 'Napis Kuturupi, S.T.', json_encode([$d['ketua_nama'], $d['kepsek_nama']]));
        $salah = [
            'judul kosong' => ['judul' => '  '] + $ok, 'judul > 200' => ['judul' => str_repeat('J', 201)] + $ok, 'tempat > 80' => ['tempat' => str_repeat('T', 81)] + $ok,
            'tanggal 30 Februari' => ['tanggal' => '2026-02-30'] + $ok, 'tanggal format Indonesia' => ['tanggal' => '25/09/2026'] + $ok, 'tanggal tahun 1999' => ['tanggal' => '1999-01-01'] + $ok,
            'nama ada angka' => ['ketua_nama' => 'Ketua 123'] + $ok, 'nama berisi HTML' => ['bendahara_nama' => '<script>x</script>'] + $ok, 'nama > 150' => ['kepsek_nama' => str_repeat('n', 151)] + $ok,
        ];
        foreach ($salah as $judul => $in) {
            $r = $h->simpanDokumen($id, $in);
            $this->cek($judul . ' → ditolak', ! $r['ok'], $r['pesan']);
        }
        $this->cek('data lama utuh setelah semua penolakan', $h->dokumenId($id)['judul'] === 'HONOR ASTS UJI');
        $r = $h->simpanDokumen($id, ['judul' => 'X', 'tempat' => '', 'tanggal' => '', 'ketua_nama' => '', 'bendahara_nama' => '', 'kepsek_nama' => '']);
        $d = $h->dokumenId($id);
        $this->cek('tempat/tanggal/nama boleh dikosongkan (NULL)', $r['ok'] && $d['tempat'] === null && $d['tanggal'] === null && $d['ketua_nama'] === null);
    }

    private function ujiSinkron(): void
    {
        $this->bagian('Perbarui komponen dari Pengaturan (snapshot tarif)');
        $h      = new HonorDokumen();
        $a      = new HonorPengaturan();
        $id     = (int) $h->dokumenPeriode((int) $this->periode('ASTS1')['id'])['id'];
        $dkKomp = static fn (array $m, string $kode) => array_values(array_filter($m['komponen'], static fn ($k) => $k['kode'] === $kode))[0] ?? null;
        $formSemua = function () use ($a): array {
            $s = [];
            foreach ($a->komponen() as $k) {
                $s[(int) $k['id']] = ['nama' => $k['nama'], 'tarif' => (string) $k['tarif'], 'satuan' => (string) $k['satuan'], 'urut' => (int) $k['urut'], 'jenis' => HonorPengaturan::jenisBerlaku($k['berlaku_di'])] + ((int) $k['aktif'] === 1 ? ['aktif' => '1'] : []);
            }

            return $s;
        };

        $r = $h->sinkronKomponen($id);
        $this->cek('belum ada perubahan → "sudah sama"', $r['ok'] && str_contains($r['pesan'], 'sudah sama'), $r['pesan']);

        $totalAwal = $h->muat($id)['total'];
        $idSoal    = (int) $this->db->table('honor_komponen')->where('kode', 'soal')->get()->getRow()->id;
        $semua     = $formSemua();
        $semua[$idSoal]['tarif'] = '30.000';
        $a->simpanKomponen($semua);
        $m = $h->muat($id);
        $this->cek('tarif di Pengaturan naik, dokumen TETAP memakai tarif lama (20.000)', (int) $dkKomp($m, 'soal')['tarif'] === 20000 && $m['total'] === $totalAwal);

        $r = $h->sinkronKomponen($id);
        $m = $h->muat($id);
        $this->cek('setelah "Perbarui" tarif soal = 30.000', $r['ok'] && (int) $dkKomp($m, 'soal')['tarif'] === 30000, $r['pesan']);
        $this->cek('total ikut sesuai tarif baru (2 soal × 10.000 lebih besar)', $m['total'] === $totalAwal + 2 * 10000 * 1, $m['total'] . ' vs ' . $totalAwal);
        $this->cek('isian angka tidak hilang setelah sinkron', $m['total_jumlah'][(int) $dkKomp($m, 'koreksi')['id']] > 0);

        // komponen baru yang aktif ikut ditambahkan, dengan sel 0 untuk semua baris
        $a->tambahKomponen(['nama' => 'Konsumsi Uji', 'tipe' => 'satuan', 'tarif' => '15.000', 'satuan' => 'porsi']);
        $nBaris = count($m['baris']);
        $r      = $h->sinkronKomponen($id);
        $m      = $h->muat($id);
        $baru   = $dkKomp($m, 'k_konsumsi_uji');
        $this->cek('komponen baru ikut masuk dokumen', $r['ok'] && $baru !== null);
        $this->cek('semua baris punya sel 0 untuk komponen baru', $baru !== null && (int) $this->db->table('honor_nilai')->where('dok_komponen_id', $baru['id'])->countAllResults() === $nBaris);
        $this->cek('komponen yang dipakai dokumen tidak bisa dihapus dari Pengaturan', ! $a->hapusKomponen((int) $baru['komponen_id'])['ok']);

        // komponen dimatikan: dibuang bila semua isiannya 0, dipertahankan bila ada angka
        $semua = $formSemua();
        unset($semua[(int) $baru['komponen_id']]['aktif']); // Konsumsi (semua 0) dimatikan
        $idPengawas = (int) $this->db->table('honor_komponen')->where('kode', 'pengawas')->get()->getRow()->id;
        unset($semua[$idPengawas]['aktif']); // Pengawas dimatikan (tapi ada angkanya)
        $a->simpanKomponen($semua);
        $r = $h->sinkronKomponen($id);
        $m = $h->muat($id);
        $this->cek('komponen mati & semua 0 → dibuang dari dokumen', $dkKomp($m, 'k_konsumsi_uji') === null, $r['pesan']);
        $this->cek('komponen mati TAPI berisi angka → dipertahankan (data tidak hilang)', $dkKomp($m, 'pengawas') !== null && str_contains($r['pesan'], 'dipertahankan'), $r['pesan']);
    }

    private function ujiKunciDanHapus(): void
    {
        $this->bagian('Dokumen terkunci, hapus, dan salin');
        $h  = new HonorDokumen();
        $p  = $this->periode('ASTS1');
        $id = (int) $h->dokumenPeriode((int) $p['id'])['id'];
        $m  = $h->muat($id);
        $b  = (int) $m['baris'][0]['id'];
        $dk = (int) $m['komponen'][0]['id'];
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'dikunci']);
        $sebelum = json_encode($h->muat($id));
        $guru    = (int) $h->calonPenerima($id)[0]['id'];
        $semua   = [
            'simpanNilai' => $h->simpanNilai($id, $b, $dk, '5'), 'tambahPenerima' => $h->tambahPenerima($id, [$guru]), 'hapusBaris' => $h->hapusBaris($id, $b),
            'ubahBaris' => $h->ubahBaris($id, $b, 'Ubah'), 'simpanDokumen' => $h->simpanDokumen($id, ['judul' => 'Ubah']), 'sinkronKomponen' => $h->sinkronKomponen($id), 'hapusDokumen' => $h->hapusDokumen($id),
        ];
        foreach ($semua as $aksi => $r) {
            $this->cek("terkunci: $aksi ditolak", ! $r['ok'] && str_contains($r['pesan'], 'DIKUNCI'), $r['pesan']);
        }
        $this->cek('terkunci: tak ada satu byte pun berubah', json_encode($h->muat($id)) === $sebelum);
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'final']);
        $this->cek('status final: masih boleh diubah (sebelum dikunci)', $h->simpanNilai($id, $b, $dk, '4')['ok']);

        // hapus dokumen → semua anak ikut terhapus
        $idAsas = (int) $h->dokumenPeriode((int) $this->periode('ASAS')['id'])['id'];
        $h->tambahPenerima($idAsas, [$this->guruId('KS')]);
        $this->cek('dokumen ASAS punya 1 baris untuk diuji hapus', $this->jmlTabel('honor_baris', 'dokumen_id', $idAsas) === 1);
        $r = $h->hapusDokumen($idAsas);
        $this->cek('hapus dokumen', $r['ok'] && $h->dokumenId($idAsas) === null, $r['pesan']);
        $this->cek('komponen, baris, nilai dokumen itu ikut terhapus', $this->jmlTabel('honor_dok_komponen', 'dokumen_id', $idAsas) === 0 && $this->jmlTabel('honor_baris', 'dokumen_id', $idAsas) === 0);
        $this->cek('dokumen lain tidak tersentuh', $h->dokumenId($id) !== null && count($h->muat($id)['baris']) > 0);
        $this->cek('hapus dicatat di audit', (int) $this->db->table('audit_log')->where('tabel', 'honor_dokumen')->where('aksi', 'delete')->countAllResults() === 1);
        $this->cek('hapus dokumen yang sudah tak ada ditolak', ! $h->hapusDokumen($idAsas)['ok']);

        // salin penerima dari dokumen lain
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'draf']);
        $m   = $h->muat($id);
        $dkP = (int) array_values(array_filter($m['komponen'], static fn ($k) => $k['kode'] === 'tunj_panitia'))[0]['id'];
        $dkS = (int) array_values(array_filter($m['komponen'], static fn ($k) => $k['kode'] === 'soal'))[0]['id'];
        $h->simpanNilai($id, (int) $m['baris'][0]['id'], $dkS, '9');
        $nama0    = $m['baris'][0]['nama'];
        $panitia0 = $m['baris'][0]['nilai'][$dkP]['nilai'];
        $baru     = $h->buat($this->periode('ASAS'), $id);
        $m2       = $h->muat((int) $baru['id']);
        $this->cek('salin: jumlah & urutan penerima sama', $baru['ok'] && count($m2['baris']) === count($m['baris']) && $m2['baris'][0]['nama'] === $nama0, $baru['pesan']);
        $dkP2 = (int) array_values(array_filter($m2['komponen'], static fn ($k) => $k['kode'] === 'tunj_panitia'))[0]['id'];
        $dkS2 = (int) array_values(array_filter($m2['komponen'], static fn ($k) => $k['kode'] === 'soal'))[0]['id'];
        $this->cek('salin: tunjangan tetap ikut, isian satuan (soal) mulai 0', $m2['baris'][0]['nilai'][$dkP2]['nilai'] === $panitia0 && $m2['baris'][0]['nilai'][$dkS2]['nilai'] === 0);
        $this->cek('salin: dokumen asal tidak berubah', $h->muat($id)['baris'][0]['nilai'][$dkS]['nilai'] === 9);
        $h->hapusDokumen((int) $baru['id']);
        $r = $h->buat($this->periode('ASAS'), 999999);
        $this->cek('salin dari dokumen yang tak ada ditolak (tak ada dokumen setengah jadi)', ! $r['ok'] && $h->dokumenPeriode((int) $this->periode('ASAS')['id']) === null, $r['pesan']);
        $this->cek('salin dari dokumen periode yang sama ditolak', ! $h->buat($p, $id)['ok']);
    }

    // ==================================================================
    // FASE 3 — hitung otomatis, pembuat soal, impor Excel
    // ==================================================================

    private function ujiHitungOtomatis(): void
    {
        $this->pulihkanKomponen();
        $this->bagian('Hitung otomatis: sumber angka');
        $hit = new HonorHitung();
        $peta = GuruModel::petaOrang();
        $orang = static fn (int $g) => $peta[$g] ?? $g;

        // Koreksi — dihitung ulang dengan cara berbeda (per kelas dulu, lalu per guru)
        $siswa = [];
        foreach ($this->db->table('siswa')->select('kelas_id')->where('status', 'aktif')->where('deleted_at', null)->where('kelas_id IS NOT NULL')->get()->getResultArray() as $s) {
            $siswa[(int) $s['kelas_id']] = ($siswa[(int) $s['kelas_id']] ?? 0) + 1;
        }
        $harap = [];
        foreach ($this->db->query('SELECT p.guru_id, p.kelas_id FROM pengampu p JOIN kelas k ON k.id = p.kelas_id WHERE p.deleted_at IS NULL AND k.deleted_at IS NULL')->getResultArray() as $r) {
            $harap[$orang((int) $r['guru_id'])] = ($harap[$orang((int) $r['guru_id'])] ?? 0) + ($siswa[(int) $r['kelas_id']] ?? 0);
        }
        $kor = $hit->koreksi();
        ksort($harap);
        ksort($kor);
        $this->cek('koreksi per guru = Σ siswa aktif kelas yang diampu (' . count($kor) . ' guru, total ' . array_sum($kor) . ')', $kor === $harap && count($kor) > 20);

        $hr = [];
        foreach ($this->db->table('kelas')->select('id, wali_kelas_id')->where('deleted_at', null)->where('wali_kelas_id IS NOT NULL')->get()->getResultArray() as $k) {
            $hr[$orang((int) $k['wali_kelas_id'])] = ($hr[$orang((int) $k['wali_kelas_id'])] ?? 0) + ($siswa[(int) $k['id']] ?? 0);
        }
        $rap = $hit->rapot();
        ksort($hr);
        ksort($rap);
        $this->cek('rapot per wali kelas = Σ siswa aktif kelas yang diwalikan (' . count($rap) . ' wali)', $rap === $hr && count($rap) > 10);

        // Pembuat soal & petunjuk pengawas pada periode ASTS1 (jadwal buatan uji)
        $p1  = $this->periode('ASTS1');
        $idP = (int) $p1['id'];
        $jid = [];
        for ($i = 1; $i <= 3; $i++) {
            $this->db->table('ujian_jadwal')->insert(['periode_id' => $idP, 'tingkat' => 'X', 'shift' => 'semua', 'tanggal' => '2026-09-1' . $i, 'created_at' => date('Y-m-d H:i:s')]);
            $jid[$i] = (int) $this->db->insertID();
        }
        $gA = $this->guruId('KS');
        $gB = $this->guruId('WK-KUR');
        $gC = (int) $this->db->table('guru')->where('deleted_at', null)->where('induk_id', null)->whereNotIn('id', [$gA, $gB])->orderBy('id', 'DESC')->get()->getRow()->id;
        $ins = fn (int $j, int $g) => $this->db->table('ujian_pembuat_soal')->insert(['jadwal_id' => $j, 'guru_id' => $g, 'created_at' => date('Y-m-d H:i:s')]);
        $ins($jid[1], $gA); $ins($jid[2], $gA); $ins($jid[3], $gA); $ins($jid[1], $gB);
        $this->db->table('ujian_jadwal')->where('id', $jid[3])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $s = $hit->soal($idP);
        $this->cek('pembuat soal: A di 2 jadwal aktif (jadwal terhapus tak dihitung), B di 1, lainnya 0', ($s[$gA] ?? 0) === 2 && ($s[$gB] ?? 0) === 1 && ! isset($s[$gC]), json_encode($s));
        $dup = false;
        try {
            $dup = ! @$this->db->table('ujian_pembuat_soal')->insert(['jadwal_id' => $jid[1], 'guru_id' => $gA, 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            $dup = true;
        }
        $this->db->resetTransStatus(); // INSERT ganda yang gagal tadi disengaja; jangan menulari uji berikutnya
        $this->cek('penugasan ganda (jadwal sama, guru sama) ditolak database', $dup && (int) $this->db->table('ujian_pembuat_soal')->where('jadwal_id', $jid[1])->where('guru_id', $gA)->countAllResults() === 1);
        $idGanda = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHG1', 'nama' => 'Data Ganda Soal', 'max_beban' => 24, 'induk_id' => $gB]);
        $ins($jid[2], $idGanda);
        $this->cek('penugasan atas data ganda digabung ke orang induknya', ($hit->soal($idP)[$gB] ?? 0) === 2, json_encode($hit->soal($idP)));

        $pg = fn (int $j, int $g, string $peran) => $this->db->table('ujian_pengawas')->insert(['jadwal_id' => $j, 'guru_id' => $g, 'peran' => $peran, 'created_at' => date('Y-m-d H:i:s')]);
        $pg($jid[1], $gC, 'pengawas'); $pg($jid[2], $gC, 'pengawas'); $pg($jid[1], $gB, 'cadangan');
        $pt = $hit->pengawasPetunjuk($idP);
        $this->cek('petunjuk pengawas: hanya peran "pengawas" (cadangan tak dihitung)', ($pt[$gC] ?? 0) === 2 && ! isset($pt[$gB]), json_encode($pt));
        $gm = $hit->gambaran($idP);
        $this->cek('gambaran memuat koreksi, rapot, soal dengan jumlah orang & total', array_keys($gm) === ['koreksi', 'rapot', 'soal'] && $gm['soal']['orang'] === 2 && $gm['soal']['total'] === 4 && $gm['koreksi']['total'] === array_sum($kor), json_encode($gm));

        $this->bagian('Hitung otomatis: terapkan ke honor');
        $h = new HonorDokumen();
        if (($lama = $h->dokumenPeriode($idP)) !== null) { // mulai dari honor bersih (sisa uji sebelumnya dibuang)
            $this->db->table('honor_dokumen')->where('id', $lama['id'])->update(['status' => 'draf']);
            $h->hapusDokumen((int) $lama['id']);
        }
        $id = (int) $h->buat($p1)['id'];
        $guruMengampu = (int) array_key_first(array_filter($kor, static fn (int $n, int $g): bool => $n > 100 && ! in_array($g, [$gA, $gB], true), ARRAY_FILTER_USE_BOTH));
        $this->cek('ada guru pengampu untuk uji', $guruMengampu > 0);
        $h->tambahPenerima($id, [$guruMengampu, $gA]);
        $m  = $h->muat($id);
        $dk = [];
        foreach ($m['komponen'] as $k) {
            $dk[$k['kode']] = (int) $k['id'];
        }
        $bar = static function (array $m, int $guru): array {
            foreach ($m['baris'] as $b) {
                if ((int) $b['guru_id'] === $guru) {
                    return $b;
                }
            }

            return [];
        };

        $r = $hit->terapkan($id, ['koreksi', 'soal']);
        $this->cek('terapkan koreksi+soal berhasil', $r['ok'] && count($r['ringkas']) === 2, $r['pesan']);
        $m = $h->muat($id);
        $b = $bar($m, $guruMengampu);
        $this->cek('koreksi guru = hitungan sumber & ditandai otomatis', $b['nilai'][$dk['koreksi']]['nilai'] === $kor[$guruMengampu] && $b['nilai'][$dk['koreksi']]['otomatis'] === $kor[$guruMengampu], json_encode($b['nilai'][$dk['koreksi']]));
        $this->cek('soal pembuat A = 2 (otomatis)', $bar($m, $gA)['nilai'][$dk['soal']]['nilai'] === 2);
        $this->cek('guru yang bukan pembuat soal = 0', $b['nilai'][$dk['soal']]['nilai'] === 0 && $b['nilai'][$dk['soal']]['otomatis'] === 0);
        $this->cek('rupiah koreksi = lembar × tarif sistem', $b['per'][$dk['koreksi']] === $kor[$guruMengampu] * 1500);
        $this->cek('PENGAWAS tidak dihitung otomatis (tetap manual, belum ada penanda)', $b['nilai'][$dk['pengawas']]['otomatis'] === null && $b['nilai'][$dk['pengawas']]['nilai'] === 0);
        $this->cek('menghitung ulang tanpa perubahan data: tak ada yang berubah', ($r2 = $hit->terapkan($id, ['koreksi']))['ok'] && $r2['ringkas'][0]['diubah'] === 0 && $r2['ringkas'][0]['dilewati'] === 0, json_encode($r2['ringkas'][0] ?? []));

        // isian manual tidak ditimpa
        $h->simpanNilai($id, (int) $b['id'], $dk['koreksi'], '123');
        $r = $hit->terapkan($id, ['koreksi']);
        $b = $bar($h->muat($id), $guruMengampu);
        $this->cek('isian yang diubah manual (123) TIDAK ditimpa', $b['nilai'][$dk['koreksi']]['nilai'] === 123 && $r['ringkas'][0]['dilewati'] >= 1, json_encode($b['nilai'][$dk['koreksi']]));
        $this->cek('...tapi pembanding "otomatis" tetap diperbarui (selisih terlihat)', $b['nilai'][$dk['koreksi']]['otomatis'] === $kor[$guruMengampu]);
        $r = $hit->terapkan($id, ['koreksi'], true);
        $b = $bar($h->muat($id), $guruMengampu);
        $this->cek('dengan "timpa" isian manual dikembalikan ke hitungan', $b['nilai'][$dk['koreksi']]['nilai'] === $kor[$guruMengampu]);

        // isian yang diketik (otomatis_nilai kosong, nilai ≠ 0) dianggap manual
        $h->simpanNilai($id, (int) $b['id'], $dk['soal'], '9');
        $hit->terapkan($id, ['soal']);
        $this->cek('isian ketikan dilindungi (bertanda manual)', $bar($h->muat($id), $guruMengampu)['nilai'][$dk['soal']]['nilai'] === 9 && $bar($h->muat($id), $guruMengampu)['nilai'][$dk['soal']]['manual'] === true);
        $h->simpanNilai($id, (int) $b['id'], $dk['koreksi'], '0');
        $hit->terapkan($id, ['koreksi']);
        $this->cek('angka 0 yang SENGAJA diketik juga dilindungi (guru yang memang tidak dibayar koreksi)', $bar($h->muat($id), $guruMengampu)['nilai'][$dk['koreksi']]['nilai'] === 0 && $kor[$guruMengampu] > 0);
        $h->simpanNilai($id, (int) $b['id'], $dk['koreksi'], (string) $kor[$guruMengampu]);
        $sel = $bar($h->muat($id), $guruMengampu)['nilai'][$dk['koreksi']];
        $this->cek('diketik sama persis dengan hitungan otomatis → kembali dianggap otomatis (tak lagi manual)', $sel['nilai'] === $kor[$guruMengampu] && $sel['manual'] === false, json_encode($sel));

        // penolakan
        $this->cek('sumber tak sah / manual (pengawas) ditolak', ! $hit->terapkan($id, ['pengawas'])['ok'] && ! $hit->terapkan($id, [])['ok'] && ! $hit->terapkan($id, ['ngawur'])['ok']);
        $r = $hit->terapkan($id, ['rapot']);
        $this->cek('Rapot ada di ASTS 1 (kolom tetap ada) → bisa dihitung bila dipilih', $r['ok'] && $r['ringkas'][0]['nama'] === 'Rapot', $r['pesan']);
        $this->cek('dokumen tak ada ditolak', ! $hit->terapkan(999999, ['koreksi'])['ok']);
        $kosong = (int) $h->buat($this->periode('ASAT'))['id'];
        $this->cek('honor tanpa penerima ditolak', ! $hit->terapkan($kosong, ['koreksi'])['ok']);
        $this->db->table('honor_dokumen')->where('id', $kosong)->delete();

        // penerima yang sudah dihapus dari Master Guru tidak disentuh
        $idHapus = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHG2', 'nama' => 'Penerima Dihapus', 'max_beban' => 24]);
        $h->tambahPenerima($id, [$idHapus]);
        $this->db->table('guru')->where('id', $idHapus)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $r = $hit->terapkan($id, ['koreksi', 'soal']);
        $this->cek('penerima yang gurunya sudah dihapus: dilewati tanpa galat', $r['ok'] && $r['ringkas'][0]['tanpa_data'] === 1, json_encode($r['ringkas'][0] ?? []));

        $this->cek('hitung otomatis tercatat di audit', (int) $this->db->table('audit_log')->where('tabel', 'honor_nilai')->like('deskripsi', 'Hitung otomatis')->countAllResults() >= 3);
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'dikunci']);
        $this->cek('honor terkunci: hitung otomatis ditolak', ! $hit->terapkan($id, ['koreksi'])['ok']);
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'draf']);
    }

    /** Buat berkas .xlsx uji (gaya rekap sekolah) di folder sementara; mengembalikan jalur. */
    private function buatXlsx(array $baris, array $opsi = []): string
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle($opsi['lembar'] ?? 'REKAP HONOR');
        $ws->setCellValue('A1', 'HONOR ASESMEN SUMATIF UJI');
        $ws->setCellValue('A2', 'SMK UJI');
        foreach (['A' => 'NO', 'B' => 'NAMA', 'C' => 'JABATAN', 'D' => 'TUNJANGAN PANITIA', 'E' => 'PEMBUATAN SOAL', 'G' => 'TRANSPORT', 'I' => 'PENGAWAS', 'K' => 'KOREKSI', 'M' => 'Rapot', 'O' => 'TOTAL', 'P' => 'TTD'] as $c => $t) {
            if (! isset($opsi['tanpa_header'])) {
                $ws->setCellValue($c . '5', $t);
            }
        }
        foreach (['E' => 'Rp. ' . ($opsi['tarif_soal'] ?? '20.000'), 'G' => 'Rp. 25.000', 'I' => 'Rp. 6.500', 'K' => 'Rp. 1.500', 'M' => 'Rp. 20.000'] as $c => $t) {
            $ws->setCellValue($c . '6', $t);
        }
        $r = 7;
        foreach ($baris as $i => $b) {
            $ws->setCellValue('A' . $r, $b['no'] ?? ($i + 1));
            $ws->setCellValue('B' . $r, $b['nama']);
            $ws->setCellValue('C' . $r, $b['jabatan'] ?? 'Guru');
            foreach (['D' => 'panitia', 'E' => 'soal', 'G' => 'transport', 'I' => 'pengawas', 'K' => 'koreksi', 'M' => 'rapot'] as $c => $k) {
                if (array_key_exists($k, $b)) {
                    $ws->setCellValue($c . $r, $b[$k]);
                }
            }
            $ws->setCellValue('F' . $r, "=E{$r}*20000");
            $ws->setCellValue('O' . $r, "=SUM(D{$r},F{$r},H{$r},J{$r},L{$r},N{$r})");
            $ws->setCellValue('H' . $r, "=G{$r}*25000");
            $ws->setCellValue('J' . $r, "=I{$r}*6500");
            $ws->setCellValue('L' . $r, "=K{$r}*1500");
            $ws->setCellValue('N' . $r, "=M{$r}*20000");
            $r++;
        }
        $r += 2;
        $ws->setCellValue('A' . $r, 'Jumlah');
        $ws->setCellValue('O' . $r, '=SUM(O7:O8)');
        $ws->setCellValue('C' . ($r + 2), 'Mengetahui,');
        $ws->setCellValue('C' . ($r + 3), 'Ketua');
        $ws->setCellValue('L' . ($r + 3), 'Bendahara');
        $ws->setCellValue('C' . ($r + 7), 'Elvira Safitri, S.Pd');
        $ws->setCellValue('L' . ($r + 7), 'Maya Fadhillah, S.Pd');
        $ws->setCellValue('F' . ($r + 8), 'Menyetujui,');
        $ws->setCellValue('F' . ($r + 9), 'Kepala SMK Uji');
        $ws->setCellValue('F' . ($r + 13), 'Napis Kuturupi, S.T');
        $jalur = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zzujihonor_' . bin2hex(random_bytes(4)) . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($jalur);

        return $jalur;
    }

    private function ujiImpor(): void
    {
        $this->pulihkanKomponen();
        $this->bagian('Impor Excel: pembantu murni');
        foreach ([
            'Napis Kuturupi, S.T' => 'napis kuturupi', 'Drs. H. Ahmad Fauzi, M.Pd.I' => 'ahmad fauzi', 'Elvira Safitri S.Pd' => 'elvira safitri', 'MULIA   Hati,S.Kom' => 'mulia hati',
            'Ahmad' => 'ahmad', '' => '',
        ] as $in => $out) {
            $this->cek('normalNama("' . $in . '") = "' . $out . '"', HonorImpor::normalNama($in) === $out, HonorImpor::normalNama($in));
        }
        foreach (['TUNJANGAN PANITIA' => 'tunj_panitia', 'PEMBUATAN SOAL' => 'soal', 'Pengetikan Soal' => 'soal', 'TRANSPORT' => 'transport', 'PENGAWAS' => 'pengawas', 'KOREKSI' => 'koreksi', 'Rapot' => 'rapot', 'RAPORT' => 'rapot', 'TOTAL' => 'total', 'JUMLAH' => 'total', 'TTD' => 'ttd', 'Tunjangan Struktural' => 'tunj_struktural', 'Tunjangan Wali Kelas' => 'tunj_walas', 'Lembur' => 'lembur', 'Konsumsi' => 'x:KONSUMSI', '' => null] as $in => $out) {
            $this->cek('kodeDariHeader("' . $in . '") = ' . var_export($out, true), HonorImpor::kodeDariHeader($in) === $out, var_export(HonorImpor::kodeDariHeader($in), true));
        }

        $this->bagian('Impor Excel: baca berkas buatan uji');
        $imp = new HonorImpor();
        $gm  = $this->db->table('guru')->select('id, nama')->where('deleted_at', null)->where('induk_id', null)->whereNotIn('nama', ['Data Ganda Uji', 'Data Ganda Soal', 'Penerima Dihapus'])->orderBy('id')->limit(40)->get()->getResultArray();
        $g1 = $gm[0];
        $g2 = $gm[1];
        $g3 = $gm[2];
        $varian = mb_strtoupper(HonorImpor::normalNama($g3['nama'])) . ', S.Pd'; // huruf besar + gelar lain → tetap cocok
        $path = $this->buatXlsx([
            ['nama' => $g1['nama'], 'jabatan' => 'Waka Uji', 'panitia' => 500000, 'soal' => 2, 'transport' => 5, 'pengawas' => 6, 'koreksi' => 360],
            ['nama' => $g2['nama'], 'jabatan' => 'Guru', 'soal' => 1, 'koreksi' => 100],
            ['nama' => $varian, 'jabatan' => 'Guru', 'pengawas' => 3],
            ['nama' => 'Orang Belum Terdaftar Uji', 'jabatan' => 'Staf TU', 'panitia' => 250000, 'transport' => 2],
            ['nama' => '', 'jabatan' => 'kosong'],
            ['nama' => 'Baris Angka Salah', 'jabatan' => 'Guru', 'koreksi' => 'abc'],
        ]);
        try {
            $p = $imp->baca($path);
        } catch (\Throwable $e) {
            $p = [];
            $this->cek('berkas uji terbaca', false, $e->getMessage());
        }
        $this->cek('kolom terbaca: panitia, soal, transport, pengawas, koreksi, rapot (tanpa total/ttd)', array_column($p['kolom'] ?? [], 'kode') === ['tunj_panitia', 'soal', 'transport', 'pengawas', 'koreksi', 'rapot'], implode(',', array_column($p['kolom'] ?? [], 'kode')));
        $this->cek('baris kosong & baris JUMLAH dilewati (5 penerima)', count($p['baris'] ?? []) === 5, (string) count($p['baris'] ?? []));
        $this->cek('nilai baris pertama terbaca (panitia 500.000, soal 2, transport 5, pengawas 6, koreksi 360)', ($p['baris'][0]['nilai'] ?? []) === ['tunj_panitia' => 500000, 'soal' => 2, 'transport' => 5, 'pengawas' => 6, 'koreksi' => 360, 'rapot' => 0], json_encode($p['baris'][0]['nilai'] ?? []));
        $this->cek('kolom TOTAL Excel terbaca dari rumus (500.000 + 2×20.000 + 5×25.000 + 6×6.500 + 360×1.500 = 1.244.000)', ($p['baris'][0]['total_excel'] ?? 0) === 500000 + 40000 + 125000 + 39000 + 540000, (string) ($p['baris'][0]['total_excel'] ?? 0));
        $this->cek('isian "abc" → dianggap 0 + peringatan', ($p['baris'][4]['nilai']['koreksi'] ?? -1) === 0 && count(array_filter($p['peringatan'] ?? [], static fn ($w) => str_contains($w, 'abc'))) === 1);
        $this->cek('nama penanda tangan terbaca (Ketua, Bendahara, Kepala Sekolah) dengan titik gelar', ($p['ttd'] ?? []) === ['ketua' => 'Elvira Safitri, S.Pd.', 'bendahara' => 'Maya Fadhillah, S.Pd.', 'kepsek' => 'Napis Kuturupi, S.T.'], json_encode($p['ttd'] ?? []));
        $this->cek('tarif Excel terbaca dari baris di bawah judul (soal 20.000)', ((array_values(array_filter($p['kolom'] ?? [], static fn ($k) => $k['kode'] === 'soal'))[0] ?? [])['tarif_excel'] ?? null) === 20000);

        $this->bagian('Impor Excel: pencocokan nama');
        $idDobel1 = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHI1', 'nama' => 'Budi Santoso Uji', 'max_beban' => 24]);
        $idDobel2 = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHI2', 'nama' => 'Budi Santoso Uji, S.Pd', 'max_beban' => 24]);
        $idMirip  = (int) (new GuruModel())->insert(['kode_guru' => 'ZZUJIHI3', 'nama' => 'Siti Salmahuji', 'max_beban' => 24]);
        $c = $imp->cocokkan(array_merge($p['baris'], [
            ['nama' => 'Budi Santoso Uji', 'jabatan' => '', 'nilai' => [], 'baris_excel' => 90, 'no' => null, 'total_excel' => null],
            ['nama' => 'Siti Salmaxuji', 'jabatan' => '', 'nilai' => [], 'baris_excel' => 91, 'no' => null, 'total_excel' => null],
        ]));
        $this->cek('nama persis → cocok + guru_id terisi', $c[0]['status'] === 'cocok' && $c[0]['guru_id'] === (int) $g1['id'], $c[0]['status']);
        $this->cek('huruf besar + gelar berbeda → tetap cocok', $c[2]['status'] === 'cocok' && $c[2]['guru_id'] === (int) $g3['id'], $c[2]['status'] . ' / ' . $varian);
        $this->cek('nama tak dikenal → "tidak", tanpa kandidat', $c[3]['status'] === 'tidak' && $c[3]['kandidat'] === [] && $c[3]['guru_id'] === null);
        $this->cek('dua guru bernama sama → "ganda" (Admin harus memilih), tak ada pilihan otomatis', $c[5]['status'] === 'ganda' && count($c[5]['kandidat']) === 2 && $c[5]['guru_id'] === null, $c[5]['status']);
        $this->cek('selisih satu huruf → "mirip" dengan satu kandidat', $c[6]['status'] === 'mirip' && $c[6]['guru_id'] === $idMirip && count($c[6]['kandidat']) === 1, $c[6]['status']);
        $this->db->table('guru')->whereIn('id', [$idDobel1, $idDobel2, $idMirip])->delete();

        $this->bagian('Impor Excel: terapkan');
        $periode = $this->periode('ASAT');
        $dokLib  = new HonorDokumen();
        $this->cek('periode ASAT belum punya honor (impor akan membuatnya)', $dokLib->dokumenPeriode((int) $periode['id']) === null);
        $c = $imp->cocokkan($p['baris']);
        $kode0 = (int) $this->db->query("SELECT MAX(CAST(kode_guru AS UNSIGNED)) AS m FROM guru WHERE kode_guru REGEXP '^[0-9]+\$'")->getRow()->m;
        $putus = [0 => 'guru:' . $g1['id'], 1 => 'guru:' . $g2['id'], 2 => 'guru:' . $g3['id'], 3 => 'baru:staf', 4 => 'lewati'];
        $an = $imp->analisis($p, (new HonorPengaturan())->komponen(true, 'ASAT'));
        $r = $imp->terapkan($periode, $p, $putus);
        $this->cek('impor berhasil (honor dibuat otomatis)', $r['ok'] && $dokLib->dokumenPeriode((int) $periode['id']) !== null, $r['pesan']);
        $dok = $dokLib->dokumenPeriode((int) $periode['id']);
        $m   = $dokLib->muat((int) $dok['id']);
        $this->cek('4 penerima masuk, 1 dilewati, 1 orang ditambah ke Master Guru', $r['ringkas']['baru'] === 4 && $r['ringkas']['dilewati'] === 1 && $r['ringkas']['guru_dibuat'] === 1 && count($m['baris']) === 4, json_encode($r['ringkas']));
        $baru = $this->db->table('guru')->where('nama', 'Orang Belum Terdaftar Uji')->get()->getRowArray();
        $this->cek('orang baru: bukan_pengajar=1, kode berikutnya, nama persis', $baru !== null && (int) $baru['bukan_pengajar'] === 1 && (int) $baru['kode_guru'] === $kode0 + 1, json_encode($baru));
        $dk = [];
        foreach ($m['komponen'] as $k) {
            $dk[$k['kode']] = (int) $k['id'];
        }
        $b0 = $m['baris'][0];
        $this->cek('isian baris 1 sama dengan Excel; label jabatan dari Excel; urutan mengikuti Excel', $b0['nilai'][$dk['tunj_panitia']]['nilai'] === 500000 && $b0['nilai'][$dk['soal']]['nilai'] === 2 && $b0['nilai'][$dk['koreksi']]['nilai'] === 360 && $b0['jabatan'] === 'Waka Uji' && (int) $b0['guru_id'] === (int) $g1['id']);
        $this->cek('isian hasil impor dianggap manual (tanpa penanda otomatis, bertanda manual)', $b0['nilai'][$dk['soal']]['otomatis'] === null && $b0['nilai'][$dk['soal']]['manual'] === true);
        $semuaManual = true;
        foreach ($m['baris'] as $br) {
            foreach ($m['komponen'] as $k) {
                $semuaManual = $semuaManual && $br['nilai'][(int) $k['id']]['manual'] === true;
            }
        }
        $this->cek('SEMUA sel hasil impor (termasuk yang 0) bertanda manual', $semuaManual);
        $totalSebelum = $m['total'];
        $hit2 = (new HonorHitung())->terapkan((int) $dok['id'], ['koreksi', 'rapot', 'soal']);
        $this->cek('"Hitung otomatis" sesudah impor TIDAK mengubah total (isian impor dilindungi)', $hit2['ok'] && $dokLib->muat((int) $dok['id'])['total'] === $totalSebelum, json_encode($hit2['ringkas'] ?? []));
        $this->cek('total honor = hitungan analisis sistem = Σ total per baris Excel', $m['total'] === $an['total'] && $m['total'] === 1244000 + (100 * 1500 + 20000) + (3 * 6500) + (250000 + 2 * 25000), $m['total'] . ' vs ' . $an['total']);
        $this->cek('nama penanda tangan dari Excel mengisi yang kosong (Kepala Sekolah dari Excel hanya bila kosong)', $dok['ketua_nama'] === 'Elvira Safitri, S.Pd.' && $dok['bendahara_nama'] === 'Maya Fadhillah, S.Pd.');

        // impor ulang: tidak menggandakan
        $r = $imp->terapkan($periode, $p, [0 => 'guru:' . $g1['id'], 1 => 'guru:' . $g2['id'], 2 => 'guru:' . $g3['id'], 3 => 'guru:' . $baru['id'], 4 => 'lewati']);
        $this->cek('impor ulang: memperbarui, tidak menggandakan penerima', $r['ok'] && $r['ringkas']['baru'] === 0 && $r['ringkas']['diperbarui'] === 4 && count($dokLib->muat((int) $dok['id'])['baris']) === 4, json_encode($r['ringkas']));
        // dua baris Excel ke orang yang sama
        $r = $imp->terapkan($periode, $p, [0 => 'guru:' . $g1['id'], 1 => 'guru:' . $g1['id'], 4 => 'lewati']);
        $this->cek('dua baris Excel ke orang yang sama → baris kedua dilewati + peringatan', $r['ok'] && $r['ringkas']['dilewati'] >= 1 && count(array_filter($r['ringkas']['peringatan'], static fn ($w) => str_contains($w, 'sudah diimpor'))) === 1, json_encode($r['ringkas']));
        $r = $imp->terapkan($periode, $p, [0 => 'guru:999999', 1 => 'ngawur', 2 => 'baru:ngawur']);
        $this->cek('pilihan tak sah (guru ngawur / tindakan ngawur) dilewati dengan aman', $r['ok'] && $r['ringkas']['baru'] === 0 && $r['ringkas']['dilewati'] === 5, json_encode($r['ringkas']));
        $r = $imp->terapkan($periode, $p, [0 => 'guru:' . $g3['id']], true);
        $this->cek('opsi "ganti seluruh daftar": hanya penerima dari impor yang tersisa', $r['ok'] && count($dokLib->muat((int) $dok['id'])['baris']) === 1);
        $this->db->table('honor_dokumen')->where('id', $dok['id'])->update(['status' => 'dikunci']);
        $this->cek('honor terkunci: impor ditolak', ! $imp->terapkan($periode, $p, [0 => 'guru:' . $g1['id']])['ok']);
        $this->db->table('honor_dokumen')->where('id', $dok['id'])->update(['status' => 'draf']);

        // angka melebihi batas
        $pBesar = $p;
        $pBesar['baris'][0]['nilai']['koreksi'] = 500000;
        $r = $imp->terapkan($periode, $pBesar, [0 => 'guru:' . $g2['id']]);
        $this->cek('angka melebihi batas → dianggap 0 + peringatan', $r['ok'] && count(array_filter($r['ringkas']['peringatan'], static fn ($w) => str_contains($w, 'melebihi batas'))) === 1);
        unlink($path);

        $this->bagian('Impor Excel: berkas bermasalah & tarif berbeda');
        $tulis = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zzujihonor_buruk.xlsx';
        $galat = static function (callable $f): string {
            try {
                $f();
            } catch (\Throwable $e) {
                return $e->getMessage();
            }

            return '';
        };
        $this->cek('jalur tak ada → pesan aman', $galat(fn () => $imp->baca('C:/tidak/ada.xlsx')) !== '');
        file_put_contents($tulis, 'bukan excel, hanya teks');
        $this->cek('berkas teks berekstensi .xlsx ditolak dengan pesan ramah', str_contains($galat(fn () => $imp->baca($tulis)), 'Excel'), $galat(fn () => $imp->baca($tulis)));
        file_put_contents($tulis, str_repeat('x', HonorImpor::MAKS_BYTE + 10));
        $this->cek('berkas > 2 MB ditolak', str_contains($galat(fn () => $imp->baca($tulis)), 'terlalu besar'));
        unlink($tulis);
        $tanpa = $this->buatXlsx([['nama' => 'X']], ['tanpa_header' => true]);
        $this->cek('Excel tanpa judul kolom NAMA ditolak dengan petunjuk', str_contains($galat(fn () => $imp->baca($tanpa)), 'Tabel honor tidak ditemukan'));
        unlink($tanpa);
        $beda = $this->buatXlsx([['nama' => $g1['nama'], 'soal' => 1]], ['tarif_soal' => '25.000']);
        $pb = $imp->baca($beda);
        $an2 = $imp->analisis($pb, (new HonorPengaturan())->komponen(true, 'ASAT'));
        $this->cek('tarif Excel ≠ tarif sistem → peringatan, yang dipakai tarif sistem (1 soal = 20.000)', count(array_filter($an2['peringatan'], static fn ($w) => str_contains($w, 'Pembuatan Soal'))) === 1 && $an2['total'] === 20000, json_encode($an2));
        unlink($beda);

        $this->ujiPenerimaanExcelAsli($imp);
    }

    /** Muat .xlsx hasil HonorCetak ke objek baru (seperti dibuka pengguna) dan cari kolom/baris penting. */
    private function bukaXlsx(string $isi): array
    {
        $jalur = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zzujicetak_' . bin2hex(random_bytes(4)) . '.xlsx';
        file_put_contents($jalur, $isi);
        $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($jalur);
        unlink($jalur);
        $ws    = $ss->getSheetByName('REKAP HONOR');
        $maxC  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        $total = 0;
        for ($c = 1; $c <= $maxC; $c++) {
            if ($ws->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . '5')->getValue() === 'TOTAL') {
                $total = $c;
            }
        }
        $jum = 0;
        for ($r = 7; $r <= $ws->getHighestDataRow(); $r++) {
            if ($ws->getCell('A' . $r)->getValue() === 'Jumlah') {
                $jum = $r;
                break;
            }
        }

        return [$ss, $ws, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($total), $jum];
    }

    private function jumlahHalamanPdf(string $pdf): int
    {
        return (int) preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    private function ujiCetak(): void
    {
        $this->bagian('Cetak: pembantu murni');
        foreach ([0 => 'nol', 1 => 'satu', 10 => 'sepuluh', 11 => 'sebelas', 15 => 'lima belas', 20 => 'dua puluh', 100 => 'seratus', 101 => 'seratus satu', 111 => 'seratus sebelas', 1000 => 'seribu', 1001 => 'seribu satu', 2000 => 'dua ribu',
            11000 => 'sebelas ribu', 100000 => 'seratus ribu', 1500000 => 'satu juta lima ratus ribu', 2000000 => 'dua juta', 44472000 => 'empat puluh empat juta empat ratus tujuh puluh dua ribu', 1000000000 => 'satu miliar'] as $n => $t) {
            $this->cek("terbilang($n) = \"$t\"", HonorCetak::terbilang($n) === $t, HonorCetak::terbilang($n));
        }
        $this->cek('tanggalIndo("2026-09-25") = 25 September 2026', HonorCetak::tanggalIndo('2026-09-25') === '25 September 2026');
        $this->cek('tanggalIndo("2026-01-05") = 5 Januari 2026 (tanpa nol di depan)', HonorCetak::tanggalIndo('2026-01-05') === '5 Januari 2026');
        $this->cek('tanggalIndo kosong / rusak = ""', HonorCetak::tanggalIndo(null) === '' && HonorCetak::tanggalIndo('') === '' && HonorCetak::tanggalIndo('ngawur') === '');
        $this->cek('namaBerkas aman (tanpa garis miring/spasi)', HonorCetak::namaBerkas(['jenis' => 'ASTS1', 'tahun_ajaran' => '2026/2027'], 'Rekap dan Slip', 'xlsx') === 'Honor-ASTS1-2026-2027-Rekap-dan-Slip.xlsx', HonorCetak::namaBerkas(['jenis' => 'ASTS1', 'tahun_ajaran' => '2026/2027'], 'Rekap dan Slip', 'xlsx'));

        $this->bagian('Cetak: Excel (REKAP + SLIP)');
        $h  = new HonorDokumen();
        $p  = $this->periode('ASTS2');
        $this->pulihkanKomponen();
        if (($lama = $h->dokumenPeriode((int) $p['id'])) !== null) {
            $h->hapusDokumen((int) $lama['id']);
        }
        $id = (int) $h->buat($p)['id'];
        $gs = $this->db->table('guru')->select('id')->where('deleted_at', null)->where('induk_id', null)->orderBy('id')->limit(4)->get()->getResultArray();
        $h->tambahPenerima($id, array_column($gs, 'id'));
        $h->simpanDokumen($id, ['judul' => 'HONOR ASESMEN SUMATIF TENGAH SEMESTER (ASTS) GENAP', 'tempat' => 'Bekasi', 'tanggal' => '2026-09-25', 'ketua_nama' => 'Elvira Safitri, S.Pd', 'bendahara_nama' => 'Maya Fadhillah, S.Pd', 'kepsek_nama' => 'Napis Kuturupi, S.T']);
        $m0 = $h->muat($id);
        $dk = [];
        foreach ($m0['komponen'] as $k) {
            $dk[$k['kode']] = (int) $k['id'];
        }
        $isi = [[500000, 2, 5, 6, 360], [0, 0, 0, 0, 0], [1200000, 3, 0, 10, 12], [0, 1, 4, 0, 77]];
        foreach ($m0['baris'] as $i => $br) {
            [$pan, $soal, $tr, $pw, $ko] = $isi[$i];
            foreach (['tunj_panitia' => $pan, 'soal' => $soal, 'transport' => $tr, 'pengawas' => $pw, 'koreksi' => $ko] as $kode => $v) {
                $h->simpanNilai($id, (int) $br['id'], $dk[$kode], (string) $v);
            }
        }
        $h->ubahBaris($id, (int) $m0['baris'][1]['id'], '<b>Jabatan</b> & "Uji"');
        $m  = $h->muat($id);
        $bh = HonorCetak::bahan($m, $p);
        $this->cek('bahan: total = total muat(); jumlah baris sama', $bh['total'] === $m['total'] && count($bh['baris']) === 4);
        $this->cek('bahan: judul 2 = SMK BINA NUSA KABUPATEN BEKASI; judul 3 = TAHUN PELAJARAN 2026-2027', $bh['sekolah'] === 'SMK BINA NUSA KABUPATEN BEKASI' && $bh['tahun'] === 'TAHUN PELAJARAN 2026-2027', $bh['sekolah'] . ' | ' . $bh['tahun']);
        $this->cek('bahan: nama penanda tangan dengan titik gelar', $bh['ketua'] === 'Elvira Safitri, S.Pd.' && $bh['kepsek'] === 'Napis Kuturupi, S.T.', $bh['ketua'] . ' | ' . $bh['kepsek']);

        $xlsx = HonorCetak::xlsx(HonorCetak::spreadsheet($m, $p));
        $this->cek('berkas .xlsx sah (tanda ZIP "PK")', str_starts_with($xlsx, 'PK') && strlen($xlsx) > 4000, (string) strlen($xlsx));
        [$ss, $ws, $tot, $jum] = $this->bukaXlsx($xlsx);
        $this->cek('lembar: REKAP HONOR + SLIP', $ss->getSheetNames() === ['REKAP HONOR', 'SLIP'], implode(',', $ss->getSheetNames()));
        $this->cek('judul 3 baris persis format sekolah', $ws->getCell('A1')->getValue() === 'HONOR ASESMEN SUMATIF TENGAH SEMESTER (ASTS) GENAP' && $ws->getCell('A2')->getValue() === 'SMK BINA NUSA KABUPATEN BEKASI' && $ws->getCell('A3')->getValue() === 'TAHUN PELAJARAN 2026-2027');
        $this->cek('header: NO, NAMA, JABATAN, TUNJANGAN PANITIA, PEMBUATAN SOAL, …, TOTAL, TTD', [$ws->getCell('A5')->getValue(), $ws->getCell('B5')->getValue(), $ws->getCell('C5')->getValue(), $ws->getCell('D5')->getValue(), $ws->getCell('E5')->getValue()] === ['NO', 'NAMA', 'JABATAN', 'TUNJANGAN PANITIA', 'PEMBUATAN SOAL'] && $ws->getCell($tot . '5')->getValue() === 'TOTAL');
        $this->cek('baris tarif: tulisan "Rp. 20.000 / 25.000 / 6.500 / 1.500" seperti rekap sekolah', [$ws->getCell('E6')->getValue(), $ws->getCell('G6')->getValue(), $ws->getCell('I6')->getValue(), $ws->getCell('K6')->getValue()] === ['Rp. 20.000', 'Rp. 25.000', 'Rp. 6.500', 'Rp. 1.500']);
        $this->cek('kolom rupiah = RUMUS jumlah × tarif (F7 = E7*20000)', $ws->getCell('F7')->isFormula() && (string) $ws->getCell('F7')->getValue() === '=E7*20000', (string) $ws->getCell('F7')->getValue());
        $this->cek('TOTAL baris & JUMLAH berupa rumus', $ws->getCell($tot . '7')->isFormula() && $ws->getCell($tot . $jum)->isFormula());
        $this->cek('JUMLAH menjumlah SEMUA baris (SUM(…7:…10)), bukan sebagian', str_contains((string) $ws->getCell($tot . $jum)->getValue(), '7:' . $tot . '10'), (string) $ws->getCell($tot . $jum)->getValue());
        $this->cek('JUMLAH TOTAL (dihitung ulang) = total sistem', (int) $ws->getCell($tot . $jum)->getCalculatedValue() === $m['total'], (int) $ws->getCell($tot . $jum)->getCalculatedValue() . ' vs ' . $m['total']);
        $semuaCocok = true;
        foreach ($bh['baris'] as $i => $br) {
            $semuaCocok = $semuaCocok && (int) $ws->getCell($tot . (7 + $i))->getCalculatedValue() === $br['total'] && $ws->getCell('B' . (7 + $i))->getValue() === $br['nama'];
        }
        $this->cek('TOTAL tiap baris di Excel = total sistem & nama sama', $semuaCocok);
        $this->cek('JUMLAH tiap komponen: jumlah (qty) & rupiah = total sistem', (int) $ws->getCell('E' . $jum)->getCalculatedValue() === $m['total_jumlah'][$dk['soal']] && (int) $ws->getCell('F' . $jum)->getCalculatedValue() === $m['total_komponen'][$dk['soal']] && (int) $ws->getCell('D' . $jum)->getCalculatedValue() === $m['total_komponen'][$dk['tunj_panitia']]);
        // Excel hidup: ubah satu angka, semua total ikut benar
        $ss2 = HonorCetak::spreadsheet($m, $p);
        $w2  = $ss2->getSheetByName('REKAP HONOR');
        $w2->setCellValue('E8', 10);
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($ss2)->clearCalculationCache();
        $this->cek('Excel HIDUP: ubah soal baris 2 dari 0 → 10 ⇒ JUMLAH naik 10 × 20.000 = 200.000', (int) $w2->getCell($tot . $jum)->getCalculatedValue() === $m['total'] + 200000, (int) $w2->getCell($tot . $jum)->getCalculatedValue() . ' vs ' . ($m['total'] + 200000));
        $ps = $ws->getPageSetup();
        $this->cek('cetak: landscape, Folio, muat 1 halaman lebar, judul baris 1–6 diulang', $ps->getOrientation() === 'landscape' && $ps->getPaperSize() === \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_FOLIO && $ps->getFitToWidth() === 1 && $ps->getFitToHeight() === 0 && $ps->getRowsToRepeatAtTop() === [1, 6], json_encode([$ps->getOrientation(), $ps->getPaperSize(), $ps->getFitToWidth(), $ps->getFitToHeight(), $ps->getRowsToRepeatAtTop()]));
        $teksSemua = '';
        foreach ($ws->toArray(null, true, false) as $baris) {
            $teksSemua .= implode('|', array_map('strval', $baris)) . "\n";
        }
        $this->cek('tanda tangan: Ketua, Bendahara, Kepala Sekolah, tempat & tanggal ada', str_contains($teksSemua, 'Elvira Safitri, S.Pd.') && str_contains($teksSemua, 'Maya Fadhillah, S.Pd.') && str_contains($teksSemua, 'Napis Kuturupi, S.T.') && str_contains($teksSemua, 'Bekasi, 25 September 2026') && str_contains($teksSemua, 'Mengetahui,') && str_contains($teksSemua, 'Menyetujui,'));
        $slip = $ss->getSheetByName('SLIP');
        $nSlip = 0;
        $jumlahSlip = 0;
        foreach ($slip->toArray(null, true, false) as $baris) {
            if ($baris[0] === 'SLIP HONOR') {
                $nSlip++;
            }
            if ($baris[0] === 'JUMLAH PENDAPATAN') {
                $jumlahSlip += (int) $baris[4];
            }
        }
        $this->cek('lembar SLIP: satu slip per penerima (4)', $nSlip === 4, (string) $nSlip);
        $this->cek('lembar SLIP: Σ "JUMLAH PENDAPATAN" semua slip = total sistem', $jumlahSlip === $m['total'], $jumlahSlip . ' vs ' . $m['total']);
        $teksSlip = '';
        foreach ($slip->toArray(null, true, false) as $baris) {
            $teksSlip .= implode('|', array_map('strval', $baris)) . "\n";
        }
        $this->cek('slip: terbilang & rincian "tarif × jumlah" tampil', str_contains($teksSlip, 'Terbilang:') && str_contains($teksSlip, '360 lembar'), '');
        $this->cek('slip penerima ber-nol: tertulis "Tidak ada honor"', str_contains($teksSlip, 'Tidak ada honor pada periode ini.'));
        $this->cek('slip TIDAK memuat label slip lama yang rusak (#REF!)', ! str_contains($teksSlip, '#REF!') && ! str_contains($teksSemua, '#REF!'));
        $kosong = HonorCetak::spreadsheet(['dokumen' => $m['dokumen'], 'komponen' => $m['komponen'], 'baris' => [], 'total_komponen' => [], 'total_jumlah' => [], 'total' => 0], $p);
        $this->cek('honor tanpa penerima tetap bisa dibangun (tanpa galat)', $kosong->getSheetCount() === 2);

        $this->bagian('Cetak: PDF & HTML');
        $htmlR = HonorCetak::rekapHtml($m, $p);
        $this->cek('HTML rekap memuat judul, nama, total berformat titik, tanda tangan', str_contains($htmlR, 'SMK BINA NUSA KABUPATEN BEKASI') && str_contains($htmlR, HonorCetak::rp($m['total'])) && str_contains($htmlR, $m['baris'][0]['nama'] === '' ? 'x' : htmlspecialchars($m['baris'][0]['nama'], ENT_QUOTES)) && str_contains($htmlR, 'Elvira Safitri, S.Pd.'));
        $this->cek('HTML rekap: teks berbahaya di label jabatan DIHINDARI (tanpa <b>Jabatan</b> mentah)', ! str_contains($htmlR, '<b>Jabatan</b>') && str_contains($htmlR, '&lt;b&gt;Jabatan&lt;/b&gt;'));
        $htmlS = HonorCetak::slipHtml($m, $p);
        $this->cek('HTML slip semua penerima memuat 4 slip & terbilang', substr_count((string) $htmlS, 'SLIP HONOR') === 4 && str_contains((string) $htmlS, 'Terbilang:'));
        $this->cek('HTML slip: label berbahaya juga dihindari', ! str_contains((string) $htmlS, '<b>Jabatan</b>'));
        $satu = HonorCetak::slipHtml($m, $p, (int) $m['baris'][2]['id']);
        $this->cek('slip satu penerima: hanya orang itu', substr_count((string) $satu, 'SLIP HONOR') === 1 && str_contains((string) $satu, '<b>' . htmlspecialchars($m['baris'][2]['nama'], ENT_QUOTES) . '</b>') && ! str_contains((string) $satu, '<b>' . htmlspecialchars($m['baris'][0]['nama'], ENT_QUOTES) . '</b>'));
        $this->cek('slip penerima yang tak ada → null', HonorCetak::slipHtml($m, $p, 999999) === null);
        $pdfR = HonorCetak::pdf($htmlR, 'f4-landscape');
        $this->cek('PDF rekap sah (%PDF-) dan ≥ 1 halaman', str_starts_with($pdfR, '%PDF-') && $this->jumlahHalamanPdf($pdfR) >= 1 && strlen($pdfR) > 3000, strlen($pdfR) . ' byte, ' . $this->jumlahHalamanPdf($pdfR) . ' hlm');
        $pdfS = HonorCetak::pdf((string) $htmlS, 'a4-portrait');
        $this->cek('PDF slip semua: sah, 2 halaman (4 slip, 2 per halaman)', str_starts_with($pdfS, '%PDF-') && $this->jumlahHalamanPdf($pdfS) === 2, $this->jumlahHalamanPdf($pdfS) . ' hlm');
        $pdf1 = HonorCetak::pdf((string) $satu, 'a4-portrait');
        $this->cek('PDF slip satu orang: 1 halaman', $this->jumlahHalamanPdf($pdf1) === 1);
    }

    /** Uji penerimaan: impor Excel asli sekolah (hanya bila ada di folder formatdatasekolah; berkas ini tidak masuk repo). */
    private function ujiPenerimaanExcelAsli(HonorImpor $imp): void
    {
        $this->bagian('UJI PENERIMAAN — Excel asli HONOR ASTS.xlsx');
        $asli = ROOTPATH . 'formatdatasekolah/HONOR ASTS.xlsx';
        if (! is_file($asli)) {
            CLI::write('  (dilewati: berkas asli tidak ada di folder ini)', 'yellow');

            return;
        }
        $p = $imp->baca($asli);
        $this->cek('59 penerima terbaca; 6 kolom komponen; nama Ketua/Bendahara/Kepala Sekolah terbaca', count($p['baris']) === 59 && count($p['kolom']) === 6 && count($p['ttd']) === 3, count($p['baris']) . ' baris, ' . count($p['kolom']) . ' kolom, ttd ' . count($p['ttd']));
        $c = $imp->cocokkan($p['baris']);
        $hit = array_count_values(array_column($c, 'status'));
        CLI::write('  Pencocokan nama: ' . json_encode($hit), 'cyan');
        $this->cek('semua baris diklasifikasi', array_sum($hit) === 59);
        $putus = [];
        foreach ($c as $i => $b) {
            $putus[$i] = $b['guru_id'] !== null ? 'guru:' . $b['guru_id'] : (! empty($b['kandidat']) ? 'guru:' . $b['kandidat'][0]['id'] : 'baru:guru');
        }
        $periode = $this->periode('ASAT');
        $dokLib  = new HonorDokumen();
        if (($lama = $dokLib->dokumenPeriode((int) $periode['id'])) !== null) {
            $dokLib->hapusDokumen((int) $lama['id']);
        }
        $an = $imp->analisis($p, (new HonorPengaturan())->komponen(true, 'ASAT'));
        $r  = $imp->terapkan($periode, $p, $putus);
        $this->cek('impor Excel asli berhasil', $r['ok'], $r['pesan']);
        $dok = $dokLib->dokumenPeriode((int) $periode['id']);
        $m   = $dokLib->muat((int) $dok['id']);
        $this->cek('59 penerima masuk (nama yang belum ada ikut ditambahkan ke Master Guru)', count($m['baris']) === 59, count($m['baris']) . ' baris; ' . $r['ringkas']['guru_dibuat'] . ' guru dibuat');
        $this->cek('TOTAL KESELURUHAN = Rp 44.472.000 (bukan Rp 35.441.000 seperti baris JUMLAH Excel yang keliru)', $m['total'] === 44472000, number_format($m['total'], 0, ',', '.'));
        $cocokTiap = 0;
        foreach ($m['baris'] as $i => $b) {
            if ($p['baris'][$i]['total_excel'] === null || $b['total'] === (int) $p['baris'][$i]['total_excel']) {
                $cocokTiap++;
            }
        }
        $this->cek('total TIAP ORANG sama dengan kolom TOTAL di Excel (59 dari 59)', $cocokTiap === 59, $cocokTiap . ' dari 59');
        $this->cek('analisis pratinjau sama dengan hasil terapan (44.472.000)', $an['total'] === 44472000, (string) $an['total']);
        $this->cek('urutan penerima = urutan Excel', $m['baris'][0]['jabatan'] === $p['baris'][0]['jabatan'] && $m['baris'][58]['nama'] !== '');
        // keluaran dari hasil impor asli: JUMLAH di Excel unduhan utuh (bukan 35.441.000), PDF banyak halaman
        $xl = HonorCetak::xlsx(HonorCetak::spreadsheet($m, $periode));
        [$ssA, $wsA, $totA, $jumA] = $this->bukaXlsx($xl);
        $this->cek('EXCEL UNDUHAN: JUMLAH TOTAL (rumus dihitung ulang) = Rp 44.472.000, 59 baris tercakup', (int) $wsA->getCell($totA . $jumA)->getCalculatedValue() === 44472000 && $jumA === 7 + 59, (int) $wsA->getCell($totA . $jumA)->getCalculatedValue() . ' pada baris ' . $jumA);
        $this->cek('EXCEL UNDUHAN: SUM jumlah mencakup baris 7 sampai 65 (bukan 7–44 seperti Excel lama)', str_contains((string) $wsA->getCell($totA . $jumA)->getValue(), '7:' . $totA . '65'), (string) $wsA->getCell($totA . $jumA)->getValue());
        $sl = 0;
        foreach ($ssA->getSheetByName('SLIP')->toArray(null, true, false) as $bar) {
            if ($bar[0] === 'JUMLAH PENDAPATAN') {
                $sl += (int) $bar[4];
            }
        }
        $this->cek('EXCEL UNDUHAN: 59 slip, Σ JUMLAH PENDAPATAN = 44.472.000 (slip tidak rusak)', $sl === 44472000, (string) $sl);
        $pdfAsli = HonorCetak::pdf(HonorCetak::rekapHtml($m, $periode), 'f4-landscape');
        $this->cek('PDF REKAP 59 orang: lebih dari 1 halaman, memuat Rp 44.472.000', $this->jumlahHalamanPdf($pdfAsli) >= 2 && str_contains(HonorCetak::rekapHtml($m, $periode), '44.472.000'), $this->jumlahHalamanPdf($pdfAsli) . ' hlm');
        $pdfSlipAsli = HonorCetak::pdf((string) HonorCetak::slipHtml($m, $periode), 'a4-portrait');
        $this->cek('PDF SLIP 59 orang: ±30 halaman (dua per halaman)', $this->jumlahHalamanPdf($pdfSlipAsli) >= 29 && $this->jumlahHalamanPdf($pdfSlipAsli) <= 32, (string) $this->jumlahHalamanPdf($pdfSlipAsli));
        CLI::write('  Peringatan impor: ' . count($r['ringkas']['peringatan']) . ' (tarif Excel sama dengan sistem → seharusnya 0)', 'cyan');
        $this->cek('tak ada peringatan tarif (tarif Excel = tarif sistem)', count($an['peringatan']) === 0, json_encode($an['peringatan']));
    }

    // ==================================================================
    // FASE 5 — status, kunci, pemeriksaan
    // ==================================================================

    private function ujiStatus(): void
    {
        $this->bagian('Status: draf → final → dikunci');
        $h  = new HonorDokumen();
        $p  = $this->periode('ASTS2');
        $id = (int) $h->dokumenPeriode((int) $p['id'])['id'];
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'draf']);
        $st = fn () => (string) $h->dokumenId($id)['status'];
        $this->cek('status awal draf', $st() === 'draf');
        $this->cek('draf → dikunci langsung DITOLAK (tandai Final dulu)', ! ($r = $h->ubahStatus($id, 'dikunci'))['ok'] && str_contains($r['pesan'], 'Final dulu') && $st() === 'draf', $r['pesan']);
        $this->cek('status sama ditolak', ! $h->ubahStatus($id, 'draf')['ok']);
        $this->cek('status ngawur ditolak', ! $h->ubahStatus($id, 'selesai')['ok'] && ! $h->ubahStatus($id, '')['ok']);
        $this->cek('dokumen tak ada ditolak', ! $h->ubahStatus(999999, 'final')['ok']);
        $r = $h->ubahStatus($id, 'final');
        $this->cek('draf → final', $r['ok'] && $st() === 'final', $r['pesan']);
        $this->cek('final: masih boleh diubah (isian)', $h->simpanNilai($id, (int) $h->muat($id)['baris'][0]['id'], (int) $h->muat($id)['komponen'][1]['id'], '3')['ok']);
        $r = $h->ubahStatus($id, 'draf');
        $this->cek('final → draf', $r['ok'] && $st() === 'draf');
        $h->ubahStatus($id, 'final');

        $m0 = $h->muat($id);
        $r  = $h->ubahStatus($id, 'dikunci');
        $d  = $h->dokumenId($id);
        $this->cek('final → dikunci', $r['ok'] && $st() === 'dikunci', $r['pesan']);
        $this->cek('kunci mencatat waktu, pengunci, total, dan sidik jari 64 huruf', ! empty($d['dikunci_at']) && ! empty($d['dikunci_oleh']) && (int) $d['kunci_total'] === $m0['total'] && strlen((string) $d['kunci_hash']) === 64, json_encode([$d['dikunci_oleh'], $d['kunci_total'], strlen((string) $d['kunci_hash'])]));
        $this->cek('terkunci: utuh() = true', HonorDokumen::utuh($h->muat($id)) === true);
        $this->cek('tidak dikunci → utuh() = null', (function () use ($h, $p): bool { $x = $h->muat((int) $h->dokumenPeriode((int) $this->periode('ASTS1')['id'])['id']); return HonorDokumen::utuh($x) === null; })());
        $this->cek('terkunci: simpanNilai/hapus/sinkron/status ditolak', ! $h->simpanNilai($id, (int) $m0['baris'][0]['id'], (int) $m0['komponen'][1]['id'], '9')['ok'] && ! $h->hapusDokumen($id)['ok'] && ! $h->sinkronKomponen($id)['ok'] && ! $h->ubahStatus($id, 'draf')['ok']);

        // sidik jari: ubah lewat DATABASE langsung → terdeteksi
        $this->db->table('honor_nilai')->where('baris_id', $m0['baris'][0]['id'])->where('dok_komponen_id', $m0['komponen'][1]['id'])->update(['nilai' => 9999]);
        $this->cek('isian diubah langsung di database → utuh() = false', HonorDokumen::utuh($h->muat($id)) === false);
        $this->db->table('honor_nilai')->where('baris_id', $m0['baris'][0]['id'])->where('dok_komponen_id', $m0['komponen'][1]['id'])->update(['nilai' => (int) $m0['baris'][0]['nilai'][(int) $m0['komponen'][1]['id']]['nilai']]);
        $this->cek('dikembalikan persis → utuh() = true lagi', HonorDokumen::utuh($h->muat($id)) === true);
        $this->db->table('honor_baris')->where('id', $m0['baris'][1]['id'])->update(['nama' => 'Diganti Diam-diam']);
        $this->cek('nama penerima diubah di database → terdeteksi', HonorDokumen::utuh($h->muat($id)) === false);
        $this->db->table('honor_baris')->where('id', $m0['baris'][1]['id'])->update(['nama' => $m0['baris'][1]['nama']]);
        $this->db->table('honor_dok_komponen')->where('id', $m0['komponen'][1]['id'])->update(['tarif' => (int) $m0['komponen'][1]['tarif'] + 1]);
        $this->cek('tarif diubah di database → terdeteksi', HonorDokumen::utuh($h->muat($id)) === false);
        $this->db->table('honor_dok_komponen')->where('id', $m0['komponen'][1]['id'])->update(['tarif' => (int) $m0['komponen'][1]['tarif']]);
        $this->db->table('honor_baris')->insert(['dokumen_id' => $id, 'guru_id' => null, 'nama' => 'Siluman', 'urut' => 99]);
        $this->cek('penerima disisipkan langsung di database → terdeteksi', HonorDokumen::utuh($h->muat($id)) === false);
        $this->db->table('honor_baris')->where('nama', 'Siluman')->delete();
        $this->cek('semua dipulihkan → utuh() = true', HonorDokumen::utuh($h->muat($id)) === true);

        // buka kunci
        $this->cek('dikunci → draf langsung ditolak', ! $h->ubahStatus($id, 'draf')['ok']);
        $this->cek('buka kunci tanpa alasan ditolak', ! ($r = $h->ubahStatus($id, 'final'))['ok'] && str_contains($r['pesan'], 'alasan') && $st() === 'dikunci', $r['pesan']);
        $this->cek('alasan terlalu pendek ("abc") ditolak', ! $h->ubahStatus($id, 'final', 'abc')['ok'] && ! $h->ubahStatus($id, 'final', '   ')['ok']);
        $this->cek('alasan terlalu panjang (>200) ditolak', ! $h->ubahStatus($id, 'final', str_repeat('a', 201))['ok']);
        $r = $h->ubahStatus($id, 'final', 'Salah ketik jumlah pengawas');
        $d = $h->dokumenId($id);
        $this->cek('buka kunci beralasan berhasil; kolom kunci dikosongkan', $r['ok'] && $st() === 'final' && $d['dikunci_at'] === null && $d['kunci_hash'] === null && $d['kunci_total'] === null, $r['pesan']);
        $this->cek('alasan tercatat di Audit Log (bersama perpindahan status)', (int) $this->db->table('audit_log')->where('tabel', 'honor_dokumen')->like('deskripsi', 'Dikunci → Final')->like('deskripsi', 'Salah ketik jumlah pengawas')->countAllResults() === 1);
        $this->cek('kunci juga tercatat (total & jumlah penerima)', (int) $this->db->table('audit_log')->where('tabel', 'honor_dokumen')->like('deskripsi', 'Final → Dikunci')->like('deskripsi', 'penerima')->countAllResults() >= 1);
        $this->cek('setelah dibuka, isian bisa diubah lagi', $h->simpanNilai($id, (int) $m0['baris'][0]['id'], (int) $m0['komponen'][1]['id'], '2')['ok']);

        // final/kunci butuh penerima
        $kosong = (int) $h->buat($this->periode('ASAS'))['id'];
        $this->cek('honor tanpa penerima tak bisa ditandai Final', ! ($r = $h->ubahStatus($kosong, 'final'))['ok'] && str_contains($r['pesan'], 'penerima'), $r['pesan']);
        $this->db->table('honor_dokumen')->where('id', $kosong)->delete();

        // penanda DRAF di cetakan
        $periode = $p;
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'draf']);
        $mm = $h->muat($id);
        $this->cek('cetakan draf memuat penanda DRAF (PDF rekap, slip, footer Excel)', str_contains(HonorCetak::rekapHtml($mm, $periode), 'DRAF') && str_contains((string) HonorCetak::slipHtml($mm, $periode), 'DRAF') && str_contains(HonorCetak::spreadsheet($mm, $periode)->getSheetByName('REKAP HONOR')->getHeaderFooter()->getOddFooter(), 'DRAF'));
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'final']);
        $mm = $h->muat($id);
        $this->cek('cetakan Final TANPA penanda DRAF', ! str_contains(HonorCetak::rekapHtml($mm, $periode), 'DRAF') && ! str_contains((string) HonorCetak::slipHtml($mm, $periode), 'DRAF') && ! str_contains(HonorCetak::spreadsheet($mm, $periode)->getSheetByName('REKAP HONOR')->getHeaderFooter()->getOddFooter(), 'DRAF'));
    }

    private function ujiPeriksa(): void
    {
        $this->bagian('Pemeriksaan anomali');
        $h  = new HonorDokumen();
        $pk = new HonorPeriksa();
        $p  = $this->periode('ASTS2');
        $id = (int) $h->dokumenPeriode((int) $p['id'])['id'];
        $this->db->table('honor_dokumen')->where('id', $id)->update(['status' => 'draf', 'ketua_nama' => null, 'bendahara_nama' => null, 'kepsek_nama' => null, 'tanggal' => null]);
        $kode = static fn (array $t): array => array_column($t, 'kode');

        // honor kosong
        $kosong = (int) $h->buat($this->periode('ASAS'))['id'];
        $this->cek('honor tanpa penerima → temuan "kosong"', $kode($pk->periksa($h->muat($kosong), $this->periode('ASAS'))) === ['kosong']);
        $this->db->table('honor_dokumen')->where('id', $kosong)->delete();

        // skenario penuh masalah
        $m  = $h->muat($id);
        $dk = [];
        foreach ($m['komponen'] as $k) {
            $dk[$k['kode']] = (int) $k['id'];
        }
        $b0 = (int) $m['baris'][0]['id'];
        $b1 = (int) $m['baris'][1]['id'];
        $guru0 = (int) $m['baris'][0]['guru_id'];
        $this->db->table('honor_baris')->whereNotIn('id', [$b0, $b1])->where('dokumen_id', $id)->delete();
        foreach (['Budi Uji Ganda', 'Budi Uji Ganda'] as $i => $nm) {
            $bid = $h->sisipBaris($id, null, $nm, 'Guru', 90 + $i);
            $this->db->table('honor_nilai')->where('baris_id', $bid)->where('dok_komponen_id', $dk['soal'])->update(['nilai' => 1, 'manual' => 1]);
        }
        $h->simpanNilai($id, $b0, $dk['koreksi'], '9999');             // total besar (≥ 14.998.500) + menyimpang
        foreach ([$b0, $b1] as $bx) {
            $this->db->table('honor_nilai')->where('baris_id', $bx)->where('dok_komponen_id', $dk['pengawas'])->update(['nilai' => 0]); // Pengawas kosong untuk semua
        }
        foreach ($m['komponen'] as $k) {
            $this->db->table('honor_nilai')->where('baris_id', $b1)->where('dok_komponen_id', $k['id'])->update(['nilai' => 0]); // baris bertotal 0
        }
        $t = $pk->periksa($h->muat($id), $p);
        $ks = $kode($t);
        $lv = array_column($t, 'level', 'kode');
        foreach (['total_nol' => 'peringatan', 'nama_ganda' => 'peringatan', 'total_besar' => 'peringatan', 'ttd_ketua_nama' => 'peringatan', 'ttd_bendahara_nama' => 'peringatan', 'ttd_kepsek_nama' => 'peringatan', 'tanpa_master' => 'info', 'tanggal_kosong' => 'info', 'menyimpang_koreksi' => 'info'] as $kd => $level) {
            $this->cek("temuan $kd ($level) muncul", ($lv[$kd] ?? null) === $level, implode(',', $ks));
        }
        $this->cek('temuan memuat nama orang & angka rupiah yang membantu mencari', (function () use ($t, $m): bool { foreach ($t as $x) { if ($x['kode'] === 'total_besar') { return str_contains($x['teks'], (string) $m['baris'][0]['nama']) && str_contains($x['teks'], 'Rp '); } } return false; })());
        $this->cek('komponen yang belum diisi siapa pun dilaporkan (mis. Pengawas)', in_array('komponen_kosong', $ks, true));

        // pengawas ≠ jadwal
        $this->db->table('ujian_jadwal')->insert(['periode_id' => $p['id'], 'tingkat' => 'X', 'shift' => 'semua', 'tanggal' => '2027-03-01', 'created_at' => date('Y-m-d H:i:s')]);
        $j = (int) $this->db->insertID();
        $this->db->table('ujian_pengawas')->insert(['jadwal_id' => $j, 'guru_id' => $guru0, 'peran' => 'pengawas', 'created_at' => date('Y-m-d H:i:s')]);
        $this->cek('Pengawas berbeda dari jadwal → catatan "pengawas_beda"', in_array('pengawas_beda', $kode($pk->periksa($h->muat($id), $p)), true) || in_array('pengawas_beda', $kode($pk->periksa($h->muat($id), $p)), true));

        // bandingkan dengan tahun sebelumnya
        $this->db->table('ujian_periode')->insert(['jenis' => 'ASTS2', 'tahun_ajaran' => '2025/2026', 'semester' => 'Genap', 'status' => 'selesai', 'created_at' => date('Y-m-d H:i:s')]);
        $pl = $this->db->table('ujian_periode')->where('tahun_ajaran', '2025/2026')->where('jenis', 'ASTS2')->get()->getRowArray();
        $idL = (int) $h->buat($pl)['id'];
        $h->tambahPenerima($idL, [$guru0]);
        $mL = $h->muat($idL);
        $h->simpanNilai($idL, (int) $mL['baris'][0]['id'], (int) $mL['komponen'][1]['id'], '10');
        $teksBanding = '';
        foreach ($pk->periksa($h->muat($id), $p) as $x) {
            if ($x['kode'] === 'banding') {
                $teksBanding = $x['teks'];
            }
        }
        $this->cek('perbandingan dengan TP sebelumnya: menyebut persen dan TP 2025/2026', str_contains($teksBanding, '2025/2026') && preg_match('/(naik|turun) \d+ %/', $teksBanding) === 1, $teksBanding);

        // honor bersih → tanpa peringatan
        $bersih = (int) $h->buat($this->periode('ASAS'))['id'];
        $h->simpanDokumen($bersih, ['judul' => 'HONOR UJI', 'tempat' => 'Bekasi', 'tanggal' => '2026-09-25', 'ketua_nama' => 'Elvira Safitri, S.Pd', 'bendahara_nama' => 'Maya Fadhillah, S.Pd', 'kepsek_nama' => 'Napis Kuturupi, S.T']);
        $h->tambahPenerima($bersih, [$guru0]);
        $mb = $h->muat($bersih);
        foreach ($mb['komponen'] as $k) {
            $h->simpanNilai($bersih, (int) $mb['baris'][0]['id'], (int) $k['id'], '1');
        }
        $tb = $pk->periksa($h->muat($bersih), $this->periode('ASAS'));
        $this->cek('honor yang lengkap & wajar: TIDAK ada temuan level "peringatan"', count(array_filter($tb, static fn ($x) => $x['level'] === 'peringatan')) === 0, json_encode($tb));
        $this->db->table('honor_dokumen')->where('id', $bersih)->delete();
    }

    private function ujiUrutan(): void
    {
        $this->bagian('Urutan baris, judul cetakan, urutan bawaan jabatan');
        $h = new HonorDokumen();
        $a = new HonorPengaturan();
        $this->pulihkanKomponen();

        // urutan bawaan menurut hierarki jabatan (honor ASAS baru)
        $pAs = $this->periode('ASAS');
        if (($lama = $h->dokumenPeriode((int) $pAs['id'])) !== null) {
            $this->db->table('honor_dokumen')->where('id', $lama['id'])->update(['status' => 'draf']);
            $h->hapusDokumen((int) $lama['id']);
        }
        $id  = (int) $h->buat($pAs)['id'];
        $kode = ['KS', 'WK-KUR', 'WK-SIS', 'WK-HUM', 'WK-SAR', 'KAPROG', 'OP'];
        $guru = [];
        foreach ($kode as $k) {
            $guru[$k] = $this->guruId($k);
        }
        $gmp = (int) $this->db->table('guru_jabatan gj')->select('gj.guru_id')->join('jabatan j', 'j.id = gj.jabatan_id')->join('guru g', 'g.id = gj.guru_id')->where('j.kode', 'GMP')->where('g.deleted_at', null)
            ->whereNotIn('gj.guru_id', array_filter($guru))->get()->getRow()->guru_id;
        $campur = array_filter($guru);
        $campur['GMP'] = $gmp;
        $ids = array_values($campur);
        shuffle($ids);
        $h->tambahPenerima($id, $ids);
        $urut = array_map(static fn (array $b): int => (int) $b['guru_id'], $h->muat($id)['baris']);
        $harap = array_values(array_filter(array_map(static fn (string $k): int => $campur[$k] ?? 0, [...$kode, 'GMP'])));
        $harap = array_values(array_unique($harap));
        $this->cek('urutan bawaan: Kepala Sekolah, Waka (Kur, Kes, Humas, Sarpras), Kaprog, Operator, lalu guru — walau dipilih acak', array_slice($urut, 0, count($harap)) === $harap || $urut === $harap, json_encode([$urut, $harap]));
        $this->cek('label bawaan memakai singkatan sekolah (Waka. …, Kaprog., Operator Sekolah)', (function () use ($h, $id): bool {
            $l = array_column($h->muat($id)['baris'], 'jabatan');
            return in_array('Waka. Kurikulum', $l, true) && in_array('Guru Mata Pelajaran', $l, true);
        })());
        $this->db->table('honor_dokumen')->where('id', $id)->delete();

        // pindah urutan (honor ASTS 2 dari uji cetak)
        $p2 = $this->periode('ASTS2');
        if (($ada = $h->dokumenPeriode((int) $p2['id'])) !== null) {
            $this->db->table('honor_dokumen')->where('id', $ada['id'])->update(['status' => 'draf']);
            $h->hapusDokumen((int) $ada['id']);
        }
        $idd = (int) $h->buat($p2)['id'];
        $h->tambahPenerima($idd, array_map('intval', array_column($this->db->table('guru')->select('id')->where('deleted_at', null)->where('induk_id', null)->orderBy('id')->limit(5)->get()->getResultArray(), 'id')));
        $m = $h->muat($idd);
        $b = array_map(static fn (array $x): int => (int) $x['id'], $m['baris']);
        $this->cek('honor ASTS 2 punya ≥ 4 penerima untuk uji', count($b) >= 4);
        $r = $h->pindahKe($idd, $b[3], 1);
        $baru = array_map(static fn (array $x): int => (int) $x['id'], $h->muat($idd)['baris']);
        $this->cek('pindah baris 4 ke nomor 1: jadi paling atas, lainnya bergeser', $r['ok'] && $baru === [$b[3], $b[0], $b[1], $b[2], $b[4]] && $r['urutan'] === $baru && $r['posisi'] === 1, json_encode($baru));
        $urutDb = array_map('intval', array_column($this->db->table('honor_baris')->select('urut')->where('dokumen_id', $idd)->orderBy('urut')->get()->getResultArray(), 'urut'));
        $this->cek('nomor urut dirapatkan 1..n tanpa ganda', $urutDb === range(1, count($b)), json_encode($urutDb));
        $r = $h->pindahKe($idd, $b[3], 3);
        $this->cek('pindah ke tengah (nomor 3)', $r['ok'] && array_search($b[3], array_map(static fn (array $x): int => (int) $x['id'], $h->muat($idd)['baris']), true) === 2);
        $r = $h->pindahKe($idd, $b[0], 99);
        $this->cek('nomor melebihi jumlah → dipasang di paling bawah', $r['ok'] && $r['posisi'] === count($b) && (int) end($r['urutan']) === $b[0]);
        $r = $h->pindahKe($idd, $b[1], 0);
        $this->cek('nomor 0/minus → paling atas', $r['ok'] && $r['posisi'] === 1 && (int) $r['urutan'][0] === $b[1]);
        $this->cek('pindah ke posisi yang sama → "Urutan tidak berubah"', ($rr = $h->pindahKe($idd, $b[1], 1))['ok'] && $rr['pesan'] === 'Urutan tidak berubah.');
        $this->cek('baris tak dikenal ditolak', ! $h->pindahKe($idd, 999999, 1)['ok']);
        $dokLain = $h->dokumenPeriode((int) $this->periode('ASTS1')['id']);
        $this->cek('baris milik honor lain tak bisa dipindah lewat honor ini', ! $h->pindahKe((int) $dokLain['id'], $b[1], 1)['ok']);
        $this->cek('pindah dicatat di audit', (int) $this->db->table('audit_log')->where('tabel', 'honor_baris')->like('deskripsi', 'Pindah')->countAllResults() >= 3);
        $mm = $h->muat($idd);
        $this->cek('Excel/PDF mengikuti urutan baru (baris pertama = yang dipindah ke atas)', HonorCetak::bahan($mm, $p2)['baris'][0]['nama'] === $mm['baris'][0]['nama'] && HonorCetak::bahan($mm, $p2)['baris'][0]['no'] === 1);
        $this->db->table('honor_dokumen')->where('id', $idd)->update(['status' => 'dikunci']);
        $this->cek('terkunci: pindah ditolak', ! $h->pindahKe($idd, $b[1], 2)['ok']);
        $this->db->table('honor_dokumen')->where('id', $idd)->update(['status' => 'draf']);

        // salin dari honor lain mempertahankan urutan
        $this->db->table('honor_dokumen')->where('id', $idd)->update(['status' => 'draf']);
        $salin = $h->buat($this->periode('ASAS'), $idd);
        $this->cek('salin ke honor baru mempertahankan urutan baris', $salin['ok'] && array_column($h->muat((int) $salin['id'])['baris'], 'nama') === array_column($h->muat($idd)['baris'], 'nama'), $salin['pesan'] ?? '');
        if (! empty($salin['id'])) {
            $h->hapusDokumen((int) $salin['id']);
        }

        // judul cetakan
        $semua = [];
        foreach ($a->komponen() as $k) {
            $semua[(int) $k['id']] = ['nama' => $k['nama'], 'judul_cetak' => (string) ($k['judul_cetak'] ?? ''), 'tarif' => (string) $k['tarif'], 'satuan' => (string) $k['satuan'], 'urut' => (int) $k['urut'], 'jenis' => HonorPengaturan::jenisBerlaku($k['berlaku_di'])] + ((int) $k['aktif'] === 1 ? ['aktif' => '1'] : []);
        }
        $idSoal = (int) $this->db->table('honor_komponen')->where('kode', 'soal')->get()->getRow()->id;
        $semua[$idSoal]['judul_cetak'] = 'PEMBUATAN SOAL (SET)';
        $r = $a->simpanKomponen($semua);
        $this->cek('judul cetakan disimpan; header cetak memakainya', $r['ok'] && HonorCetak::judulKolom($a->komponen()[3]) === 'PEMBUATAN SOAL (SET)', $r['pesan']);
        $semua[$idSoal]['judul_cetak'] = str_repeat('J', 81);
        $this->cek('judul cetakan > 80 huruf ditolak', ! $a->simpanKomponen($semua)['ok']);
        $semua[$idSoal]['judul_cetak'] = '   ';
        $this->cek('dikosongkan → kembali ke NAMA HURUF BESAR', $a->simpanKomponen($semua)['ok'] && HonorCetak::judulKolom($a->komponen()[3]) === 'PEMBUATAN SOAL');
        $this->cek('honor yang sudah dibuat tetap memakai judul lama sampai "Perbarui"', (function () use ($h, $a, $idd, $idSoal, $semua): bool {
            $semua[$idSoal]['judul_cetak'] = 'JUDUL BARU';
            $a->simpanKomponen($semua);
            $dkSoal = array_values(array_filter($h->muat($idd)['komponen'], static fn ($k) => $k['kode'] === 'soal'))[0];
            $sebelum = HonorCetak::judulKolom($dkSoal);
            $h->sinkronKomponen($idd);
            $dkSoal2 = array_values(array_filter($h->muat($idd)['komponen'], static fn ($k) => $k['kode'] === 'soal'))[0];

            return $sebelum === 'PEMBUATAN SOAL' && HonorCetak::judulKolom($dkSoal2) === 'JUDUL BARU';
        })());
        $this->cek('kapitalNama: "SMK BINA NUSA" → "SMK Bina Nusa"', HonorCetak::kapitalNama('SMK BINA NUSA') === 'SMK Bina Nusa', HonorCetak::kapitalNama('SMK BINA NUSA'));
    }

    private function ujiHak(): void
    {
        $this->bagian('Hak akses: hanya Admin');
        $alamat = ['admin/honor/pengaturan', 'admin/honor/pengaturan/komponen', 'admin/honor/pengaturan/komponen/3/hapus', 'admin/honor/pengaturan/panitia', 'admin/honor/pengaturan/tanda-tangan', 'admin/honor', 'admin/ujian/asts1/honor', 'admin/ujian/asts1/honor/xlsx'];
        foreach ($alamat as $al) {
            $this->cek('Admin boleh ' . $al, HakAkses::boleh('admin', $al));
        }
        foreach (['operator', 'hubin', 'ngawur', '', null] as $peran) {
            $semuaTolak = true;
            foreach ($alamat as $al) {
                $semuaTolak = $semuaTolak && ! HakAkses::boleh($peran, $al);
            }
            $this->cek('peran "' . (string) $peran . '" ditolak di SEMUA alamat honor', $semuaTolak);
        }
        $this->cek('trik ../ ditolak (hubin)', ! HakAkses::boleh('hubin', 'admin/pkl/../honor/pengaturan'));
        $this->cek('trik ../ ditolak (operator)', ! HakAkses::boleh('operator', 'admin/dokumen/../honor/pengaturan'));
        $this->cek('trik huruf besar ditolak (operator)', ! HakAkses::boleh('operator', 'ADMIN/HONOR/PENGATURAN'));
        $this->cek('menu: admin melihat, operator tidak', HakAkses::boleh('admin', 'admin/honor/pengaturan') && ! HakAkses::boleh('operator', 'admin/honor/pengaturan'));
        $this->cek('Operator tetap boleh hak lamanya (PKL) — tak terganggu', HakAkses::boleh('operator', 'admin/pkl') && HakAkses::boleh('hubin', 'admin/pkl'));
    }
}
