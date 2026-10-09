<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\HonorDokumen;
use App\Libraries\HonorImpor;
use App\Libraries\HonorKoreksi;
use App\Libraries\HonorKoreksiImpor;
use App\Libraries\Skbm;
use App\Libraries\SkbmCetak;
use App\Libraries\SkbmImpor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji SKBM: logika per tahun ajaran (tambah/ubah/hapus, sel & JP, total, salin, perbandingan dengan Penugasan), impor berkas
 * SKBM asli sekolah (butuh formatdatasekolah/Jadwal_SMK_Bina_Nusa….xlsx + KOREKSI NILAI.xlsx), dan sambungannya ke ceklis
 * Koreksi honor. SELURUH uji berjalan dalam SATU transaksi yang di-ROLLBACK. Nama uji berawalan "ZZUJI SK".
 * Rancangan: docs/DESAIN-SKBM.md.
 *
 * Jalankan:  php spark dev:uji-skbm
 */
class UjiSkbm extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-skbm';
    protected $description = 'Uji SKBM: logika, impor berkas asli, perbandingan dengan Penugasan, dan sambungan ke Koreksi honor (di-rollback).';

    private int $lulus = 0;
    private int $gagal = 0;
    private int $seq   = 0;
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
        $this->db->table('audit_log')->like('tabel', 'skbm_', 'after')->delete();
        try {
            $this->ujiMurni();
            $this->ujiHak();
            $this->ujiLogika();
            $this->ujiNomorSk();
            $this->ujiBanding();
            $this->ujiEkspor();
            $this->ujiBerkasAsli();
            $kode = $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $kode = EXIT_ERROR;
        }
        while ($this->db->transRollback()) {
            // batalkan semua tingkat transaksi sampai habis
        }
        cache()->delete('opt_guru');
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal  (semua perubahan uji sudah di-rollback)', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $kode;
    }

    private function guru(string $nama): int
    {
        $this->seq++;
        $this->db->table('guru')->insert(['kode_guru' => 'ZZSK' . $this->seq . substr(str_replace('.', '', (string) microtime(true)), -6), 'nama' => $nama, 'bukan_pengajar' => 0, 'max_beban' => 24]);

        return (int) $this->db->insertID();
    }

    // ------------------------------------------------------------------

    private function ujiMurni(): void
    {
        $this->bagian('Pembantu murni: tahun ajaran, pemilihan lembar');
        foreach (['2026/2027', '2099/2100', '2000/2001'] as $t) {
            $this->cek("tahun \"$t\" sah", Skbm::tahunValid($t));
        }
        foreach (['2026/2028', '2026-2027', '26/27', '2026/2026', '', '2101/2102', '1999/2000', 'abc'] as $t) {
            $this->cek("tahun \"$t\" ditolak", ! Skbm::tahunValid($t));
        }
        $this->cek('tahunBawaan() berbentuk tahun ajaran sah', Skbm::tahunValid((new Skbm())->tahunBawaan()), (new Skbm())->tahunBawaan());
        $this->cek('pilihLembar: yang bernama SKBM', SkbmImpor::pilihLembar(['Petunjuk', 'Rekap Beban', 'SKBM 2026-2027', 'WALAS']) === 'SKBM 2026-2027');
        $this->cek('pilihLembar: satu lembar saja dipakai', SkbmImpor::pilihLembar(['Lembar1']) === 'Lembar1');
        $this->cek('pilihLembar: banyak lembar tanpa SKBM → null (minta pengguna memperbaiki)', SkbmImpor::pilihLembar(['A', 'B']) === null);
    }

    private function ujiHak(): void
    {
        $this->bagian('Hak akses: SKBM khusus Admin (data gaji → hanya Admin)');
        foreach (['admin/skbm', 'admin/skbm/sel', 'admin/skbm/bandingkan', 'admin/skbm/impor/unggah', 'admin/skbm/mapel/5/hapus', 'admin/ujian/asts1/honor/koreksi/isi-skbm'] as $a) {
            $this->cek("Admin boleh $a", HakAkses::boleh('admin', $a));
        }
        foreach (['operator', 'hubin'] as $peran) {
            foreach (['admin/skbm', 'admin/skbm/sel', 'admin/skbm/bandingkan', 'admin/skbm/impor/unggah', 'admin/skbm/mapel/5/hapus', 'admin/ujian/asts1/honor/koreksi/isi-skbm'] as $a) {
                $this->cek("$peran ditolak $a", ! HakAkses::boleh($peran, $a));
            }
        }
        $this->cek('trik ../ ditolak (operator)', ! HakAkses::boleh('operator', 'admin/dokumen/../skbm'));
        $this->cek('trik ../ ditolak (hubin)', ! HakAkses::boleh('hubin', 'admin/pkl/../skbm'));
    }

    private function ujiLogika(): void
    {
        $this->bagian('Logika SKBM per tahun ajaran (tambah, sel & JP, total, salin, kosongkan, CASCADE)');
        $s = new Skbm();
        $T = '2098/2099';
        $kelas = (new HonorKoreksi())->daftarKelas();
        [$kA, $kB] = [$kelas[0]['id'], $kelas[1]['id']];
        $gA = $this->guru('ZZUJI SK Alfa');
        $gB = $this->guru('ZZUJI SK Bravo');
        $this->cek('SKBM tahun uji awalnya kosong', ! $s->ada($T) && $s->muat($T)['guru'] === []);

        $r = $s->tambahMapel($T, $gA, 'ZZUJI Mapel Satu');
        $r2 = $s->tambahMapel($T, $gA, 'ZZUJI Mapel Dua');
        $r3 = $s->tambahMapel($T, $gB, '-');
        $this->cek('tambah 2 mapel untuk guru A dan "-" untuk guru B', $r['ok'] && $r2['ok'] && $r3['ok']);
        foreach ([['2098-2099', $gA, 'X'], [$T, $gA, '   '], [$T, 99999999, 'X'], [$T, $gA, str_repeat('x', 151)]] as [$th, $gid, $nm]) {
            $this->cek('tambahMapel ditolak untuk masukan salah (' . substr((string) $th, 0, 9) . '/' . $gid . '/' . strlen($nm) . ')', ! $s->tambahMapel($th, $gid, $nm)['ok']);
        }

        $a = $s->setSel($T, $r['id'], $kA, true);
        $this->cek('nyalakan sel tanpa JP → aktif, JP kosong', $a['ok'] && $a['jp'] === null && $a['total'] === 0);
        $a = $s->setSel($T, $r['id'], $kA, true, '4');
        $this->cek('isi JP 4 → total JP 4', $a['ok'] && $a['jp'] === 4 && $a['total'] === 4);
        $s->setSel($T, $r['id'], $kB, true, '6');
        $s->setSel($T, $r2['id'], $kA, true, '2');
        $m = $s->muat($T);
        $gm = $m['guru'][0];
        $this->cek('total: mapel1 = 10 JP, guru A = 12 JP, 3 sel, kode 1A/1B', $gm['mapel'][0]['total_jp'] === 10 && $gm['total_jp'] === 12 && $m['jumlah_sel'] === 3 && $gm['mapel'][0]['kode'] === '1A' && $gm['mapel'][1]['kode'] === '1B', json_encode([$gm['mapel'][0]['total_jp'], $gm['total_jp'], $m['jumlah_sel']]));
        $this->cek('guru B (mapel "-") ikut tampil tanpa sel; urutan = urutan masuk (A lalu B)', count($m['guru']) === 2 && $m['guru'][1]['nama'] === 'ZZUJI SK Bravo' && $m['guru'][1]['jml'] === 0);
        $colA = array_values(array_filter($m['kelas'], static fn ($k) => $k['id'] === $kA))[0];
        $this->cek('hitungan per kelas: kelas A dipakai 2 baris, 6 JP', $colA['jml'] === 2 && $colA['jp'] === 6, json_encode([$colA['jml'], $colA['jp']]));
        foreach (['0', 'abc', '41', '-1', '1.5'] as $buruk) {
            $this->cek('JP "' . $buruk . '" ditolak & tidak mengubah', ! $s->setSel($T, $r['id'], $kA, true, $buruk)['ok'] && $s->muat($T)['guru'][0]['mapel'][0]['sel'][$kA] === 4);
        }
        $this->cek('JP "" mengosongkan', $s->setSel($T, $r['id'], $kA, true, '')['jp'] === null);
        $s->setSel($T, $r['id'], $kA, true, '4');
        $this->cek('matikan sel menghapusnya', $s->setSel($T, $r2['id'], $kA, false)['ok'] && ! isset($s->muat($T)['guru'][0]['mapel'][1]['sel'][$kA]));
        $this->cek('sel pada baris tahun lain ditolak', ! $s->setSel('2097/2098', $r['id'], $kA, true)['ok']);
        $this->cek('ubahMapel', $s->ubahMapel($r2['id'], 'ZZUJI Mapel Tiga')['ok'] && $s->muat($T)['guru'][0]['mapel'][1]['nama'] === 'ZZUJI Mapel Tiga');

        $c = $s->salinTahun($T, '2097/2098');
        $this->cek('salin ke tahun kosong: 3 baris mapel, 2 sel', $c['ok'] && $s->muat('2097/2098')['jumlah_mapel'] === 3 && $s->muat('2097/2098')['jumlah_sel'] === 2, $c['pesan']);
        $this->cek('salin ke tahun berisi ditolak; dengan "ganti" boleh', ! $s->salinTahun($T, '2097/2098')['ok'] && $s->salinTahun($T, '2097/2098', true)['ok'] && $s->muat('2097/2098')['jumlah_mapel'] === 3);
        $this->cek('salin dari tahun kosong / tahun sama ditolak', ! $s->salinTahun('2096/2097', '2095/2096')['ok'] && ! $s->salinTahun($T, $T)['ok']);
        $this->cek('hapusMapel & hapusGuru', $s->hapusMapel($r2['id'])['ok'] && $s->hapusGuru($T, $gB)['ok'] && $s->muat($T)['jumlah_mapel'] === 1);
        $this->cek('kosongkan satu tahun tidak menyentuh tahun lain', $s->kosongkan($T)['ok'] && ! $s->ada($T) && $s->ada('2097/2098'));
        $this->db->table('guru')->where('id', $gA)->delete();
        $this->cek('hapus guru permanen → baris SKBM-nya ikut hilang (CASCADE)', (int) $this->db->table('skbm_mapel')->where('guru_id', $gA)->countAllResults() === 0);
        $this->cek('perubahan tercatat di Audit Log', (int) $this->db->table('audit_log')->like('tabel', 'skbm_', 'after')->countAllResults() >= 8);
    }

    private function ujiNomorSk(): void
    {
        $this->bagian('Nomor SK per tahun ajaran; nomor guru & kode dari SK sekolah');
        $s = new Skbm();
        $T = '2089/2090';
        $this->cek('nomor SK kosong di tahun baru', $s->nomorSk($T) === '');
        $this->cek('simpan "123/SMK-BN/SKBM/VII/2089" → tersimpan; spasi ganda dirapikan', $s->simpanNomorSk($T, "  123/SMK-BN/SKBM/VII/2089 ")['ok'] && $s->nomorSk($T) === '123/SMK-BN/SKBM/VII/2089');
        $this->cek('simpan nomor sama → "Tidak ada perubahan"', $s->simpanNomorSk($T, '123/SMK-BN/SKBM/VII/2089')['pesan'] === 'Tidak ada perubahan.');
        $this->cek('nomor memuat tanda berbahaya (<script>) ditolak, nomor lama utuh', ! $s->simpanNomorSk($T, '<script>x</script>')['ok'] && $s->nomorSk($T) === '123/SMK-BN/SKBM/VII/2089');
        $this->cek('nomor > 120 huruf ditolak', ! $s->simpanNomorSk($T, str_repeat('1', 121))['ok']);
        $this->cek('tahun tak sah ditolak', ! $s->simpanNomorSk('2089-2090', '1')['ok']);
        $this->cek('nomor kosong menghapus', $s->simpanNomorSk($T, '')['ok'] && $s->nomorSk($T) === '');
        $this->cek('perubahan nomor tercatat di Audit Log', (int) $this->db->table('audit_log')->where('tabel', 'skbm_sk')->countAllResults() >= 2);

        // Nomor guru & kode mengikuti SK sekolah yang tersimpan (nomor boleh melompat); baris buatan manual melanjutkan nomor terakhir.
        $gA = $this->guru('ZZUJI SK Nomor A');
        $gB = $this->guru('ZZUJI SK Nomor B');
        $gC = $this->guru('ZZUJI SK Nomor C');
        $now = date('Y-m-d H:i:s');
        foreach ([[$gA, 'ZZUJI X', '7A', 1], [$gA, 'ZZUJI Y', '7B', 2], [$gB, 'ZZUJI Z', '12', 3]] as [$g, $nm, $kd, $ur]) {
            $this->db->table('skbm_mapel')->insert(['tahun_ajaran' => $T, 'guru_id' => $g, 'mapel_nama' => $nm, 'mapel_id' => null, 'kode' => $kd, 'urut' => $ur, 'created_at' => $now, 'updated_at' => $now]);
        }
        $s->tambahMapel($T, $gC, 'ZZUJI Manual');
        $m = $s->muat($T);
        $nomor = array_map(static fn ($g) => $g['no'], $m['guru']);
        $kode  = array_map(static fn ($g) => array_map(static fn ($x) => $x['kode'], $g['mapel']), $m['guru']);
        $this->cek('nomor guru dari kode SK: 7 dan 12 (melompat); guru buatan manual melanjutkan → 13', $nomor === [7, 12, 13], json_encode($nomor));
        $this->cek('kode baris tersimpan tampil apa adanya (7A, 7B, 12); baris manual turunan nomor (13)', $kode === [['7A', '7B'], ['12'], ['13']], json_encode($kode));
    }

    private function ujiBanding(): void
    {
        $this->bagian('Perbandingan SKBM dengan Penugasan (pengampu)');
        $s = new Skbm();
        $T = '2094/2095';
        $kelas = (new HonorKoreksi())->daftarKelas();
        [$kA, $kB, $kC, $kD] = [$kelas[0]['id'], $kelas[1]['id'], $kelas[2]['id'], $kelas[3]['id']];
        $gA = $this->guru('ZZUJI SK Charlie');
        $gB = $this->guru('ZZUJI SK Delta');
        $gC = $this->guru('ZZUJI SK Echo');
        $mapel = $this->db->table('mata_pelajaran')->select('id')->where('deleted_at', null)->orderBy('id', 'DESC')->limit(2)->get()->getResultArray();
        $ins = function (int $guru, int $kelasId, int $mapelId, int $jp): void {
            $this->db->table('pengampu')->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->delete();
            $this->db->table('pengampu')->insert(['guru_id' => $guru, 'kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'jp' => $jp, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        };
        $ins($gA, $kA, (int) $mapel[0]['id'], 4);   // sama dengan SKBM
        $ins($gA, $kB, (int) $mapel[0]['id'], 2);   // hanya di Pengampu
        $ins($gB, $kA, (int) $mapel[1]['id'], 4);   // JP beda (SKBM 6)
        $ins($gC, $kD, (int) $mapel[1]['id'], 2);   // guru tanpa SKBM
        $r1 = $s->tambahMapel($T, $gA, 'ZZUJI Mapel A');
        $s->setSel($T, $r1['id'], $kA, true, '4');
        $s->setSel($T, $r1['id'], $kC, true, '3');   // hanya di SKBM
        $r2 = $s->tambahMapel($T, $gB, 'ZZUJI Mapel B');
        $s->setSel($T, $r2['id'], $kA, true, '6');
        $b = $s->bandingkanDenganPengampu($T);
        $this->cek('perbandingan: 1 sama (Charlie kelas A, 4 JP)', $b['sama'] === 1, json_encode($b['sama']));
        $this->cek('hanya di SKBM: Charlie di kelas C', count($b['hanya_skbm']) === 1 && $b['hanya_skbm'][0]['guru'] === 'ZZUJI SK Charlie' && $b['hanya_skbm'][0]['jp_skbm'] === 3, json_encode($b['hanya_skbm']));
        $this->cek('hanya di Penugasan: Charlie di kelas B', count($b['hanya_pengampu']) === 1 && $b['hanya_pengampu'][0]['jp_pengampu'] === 2, json_encode($b['hanya_pengampu']));
        $this->cek('JP beda: Delta SKBM 6 vs Penugasan 4', count($b['jp_beda']) === 1 && $b['jp_beda'][0]['jp_skbm'] === 6 && $b['jp_beda'][0]['jp_pengampu'] === 4, json_encode($b['jp_beda']));
        $this->cek('guru ber-Penugasan yang tidak ada di SKBM dilaporkan terpisah (Echo)', in_array('ZZUJI SK Echo', $b['guru_tanpa_skbm'], true) && $b['guru_skbm'] === 2);
        $this->cek('SKBM kosong → ada=false', ! $s->bandingkanDenganPengampu('2093/2094')['ada']);
    }

    private function ujiEkspor(): void
    {
        $this->bagian('Excel SKBM: unduh → impor kembali (pulang-pergi) dan template kosong');
        $s     = new Skbm();
        $T     = '2093/2094';
        $kelas = (new HonorKoreksi())->daftarKelas();
        [$kA, $kB, $kC] = [$kelas[0]['id'], $kelas[1]['id'], $kelas[5]['id']];
        $gA = $this->guru('ZZUJI SK Foxtrot');
        $gB = $this->guru('ZZUJI SK Golf');
        $gC = $this->guru('ZZUJI SK Hotel');
        $m1 = $s->tambahMapel($T, $gA, 'ZZUJI Mapel Ekspor 1')['id'];
        $m2 = $s->tambahMapel($T, $gA, 'ZZUJI Mapel Ekspor 2')['id'];
        $m3 = $s->tambahMapel($T, $gB, 'ZZUJI Mapel Ekspor 3')['id'];
        $s->tambahMapel($T, $gC, '-');
        $s->setSel($T, $m1, $kA, true, '4');
        $s->setSel($T, $m1, $kB, true, '6');
        $s->setSel($T, $m1, $kC, true);          // nyala tanpa JP → tanda "?" di Excel (tidak terbaca saat impor)
        $s->setSel($T, $m2, $kA, true, '2');
        $s->setSel($T, $m3, $kB, true, '3');
        $m = $s->muat($T);

        $this->cek('nomor SK disimpan', $s->simpanNomorSk($T, '123/ABC/SKBM/2093')['ok'] && $s->nomorSk($T) === '123/ABC/SKBM/2093');
        $ss = SkbmCetak::spreadsheet($m, SkbmCetak::info($T));
        $ws = $ss->getActiveSheet();
        $this->cek('judul Excel: "DAFTAR LAMPIRAN 3", "TAHUN PELAJARAN 2093/2094", "Nomor : <nomor SK>"; header ungu di baris 6–8', $ws->getCell('A1')->getValue() === 'DAFTAR LAMPIRAN 3' && $ws->getCell('A3')->getValue() === 'TAHUN PELAJARAN ' . $T
            && $ws->getCell('A4')->getValue() === 'Nomor : 123/ABC/SKBM/2093' && $ws->getCell('B6')->getValue() === 'NAMA GURU' && $ws->getStyle('B6')->getFill()->getStartColor()->getRGB() === 'CC66FF');
        $this->cek('lembar bernama "SKBM 2093-2094"; nama berkas SKBM-2093-2094.xlsx / template SKBM-TEMPLATE-2093-2094.xlsx', $ws->getTitle() === 'SKBM 2093-2094' && SkbmCetak::namaBerkas($T) === 'SKBM-2093-2094.xlsx' && SkbmCetak::namaBerkas($T, true) === 'SKBM-TEMPLATE-2093-2094.xlsx');
        $baris1 = 9; // baris data pertama (judul 4 baris, header di baris 6–8 — sama dengan lembar SKBM sekolah)
        $kolC   = 5 + array_search($kC, array_column($kelas, 'id'), true);
        $this->cek('sel tanpa JP bertanda "?"; total per baris & per kelas = rumus hidup', $ws->getCell([$kolC, $baris1])->getValue() === '?' && str_starts_with((string) $ws->getCell([5 + count($kelas), $baris1])->getValue(), '=SUM(E9:'));
        $tmp = tempnam(sys_get_temp_dir(), 'skbm');
        $xlsx = $tmp . '.xlsx';
        file_put_contents($xlsx, SkbmCetak::xlsx($ss));
        $imp = new SkbmImpor();
        $p   = $imp->cocokkan($imp->baca($xlsx));
        @unlink($xlsx);
        @unlink($tmp);
        $this->cek('dibaca kembali: lembar "SKBM 2093-2094", tahun 2093/2094, satu blok, semua ' . count($kelas) . ' kolom kelas dikenal', $p['lembar'] === 'SKBM 2093-2094' && $p['tahun'] === $T && count($p['blok']) === 1 && $p['blok'][0]['dikenal'] === count($kelas));
        $this->cek('tiga guru terbaca, jumlah baris mapel 2 / 1 / 1; ketiganya cocok dengan Master Guru', count($p['guru']) === 3 && array_map(static fn ($g) => count($g['baris']), $p['guru']) === [2, 1, 1] && array_column($p['guru'], 'status') === ['cocok', 'cocok', 'cocok']);
        $kolKe = array_column($p['blok'][0]['kelas'], 'kelas_id', 'c');
        $jpBaris = [];
        foreach ($p['guru'][0]['baris'][0]['sel'][0] as $c => $jp) {
            $jpBaris[$kolKe[$c]] = $jp;
        }
        $this->cek('JP baris pertama terbaca persis (kelas A=4, kelas B=6); sel "?" tidak ikut', $jpBaris === [$kA => 4, $kB => 6], json_encode($jpBaris));
        $keputusan = [];
        foreach ($p['guru'] as $i => $g) {
            $keputusan[$i] = 'guru:' . $g['guru_id'];
        }
        $r = $imp->terapkan('2092/2093', $p, $keputusan, true);
        $n = $s->muat('2092/2093');
        $this->cek('diimpor ke tahun lain: 3 guru, 4 baris mapel, sel & JP sama (kecuali sel "?")', $r['ok'] && $n['jumlah_guru'] === 3 && $n['jumlah_mapel'] === 4 && $n['jumlah_sel'] === $m['jumlah_sel'] - 1 && $n['total_jp'] === $m['total_jp'], $r['pesan']);
        $namaMapel = array_map(static fn ($g) => array_map(static fn ($x) => $x['nama'], $g['mapel']), $n['guru']);
        $this->cek('nama mapel & urutan guru terjaga ("-" tetap "-")', $namaMapel === [['ZZUJI Mapel Ekspor 1', 'ZZUJI Mapel Ekspor 2'], ['ZZUJI Mapel Ekspor 3'], ['-']], json_encode($namaMapel));

        // Template: tahun belum diisi → hanya header (kolom kelas lengkap), tanpa baris guru → pembaca impor mengenali strukturnya.
        $kosong = $s->muat('2091/2092');
        $tpl    = SkbmCetak::spreadsheet($kosong, SkbmCetak::info('2091/2092'));
        $this->cek('template: nomor SK belum diisi → "Nomor : ....."; ' . 30 . ' baris kosong siap diisi dengan rumus jumlah', str_starts_with((string) $tpl->getActiveSheet()->getCell('A4')->getValue(), 'Nomor : ....') && str_starts_with((string) $tpl->getActiveSheet()->getCell([5 + count($kelas), 9])->getValue(), '=SUM(E9:') && (string) $tpl->getActiveSheet()->getCell('A40')->getValue() === 'TOTAL JP / KELAS');
        $tmp2   = tempnam(sys_get_temp_dir(), 'skbm') . '.xlsx';
        file_put_contents($tmp2, SkbmCetak::xlsx($tpl));
        $pesan = '';
        try {
            $imp->baca($tmp2);
        } catch (\RuntimeException $e) {
            $pesan = $e->getMessage();
        }
        @unlink($tmp2);
        $this->cek('template kosong: header & kolom kelas terbaca, lalu ditolak karena belum ada baris guru (pesan jelas)', str_contains($pesan, 'Tidak ada baris guru'), $pesan);
    }

    // ------------------------------------------------------------------

    private function ujiBerkasAsli(): void
    {
        $this->bagian('Berkas SKBM asli sekolah: baca, cocokkan, terapkan, dan banding dengan KOREKSI NILAI.xlsx');
        $berkas = glob(ROOTPATH . 'formatdatasekolah/Jadwal_SMK_Bina_Nusa*SKBM*.xlsx')[0] ?? null;
        $koreksi = ROOTPATH . 'formatdatasekolah/KOREKSI NILAI.xlsx';
        if ($berkas === null || ! is_file($koreksi)) {
            CLI::write('  (berkas asli tidak ada di formatdatasekolah/ — bagian ini dilewati)', 'yellow');

            return;
        }
        $imp = new SkbmImpor();
        $t0  = microtime(true);
        $p   = $imp->baca($berkas);
        CLI::write(sprintf('  (info) baca berkas %.1f MB: %.1f detik, memori puncak proses %.0f MB (batas memory_limit = %s)', filesize($berkas) / 1048576, microtime(true) - $t0, memory_get_peak_usage(true) / 1048576, ini_get('memory_limit')), 'light_gray');
        $sel = [0 => 0, 1 => 0];
        foreach ($p['guru'] as $g) {
            foreach ($g['baris'] as $br) {
                foreach ($br['sel'] as $b => $x) {
                    $sel[$b] += count($x);
                }
            }
        }
        $this->cek('lembar "' . $p['lembar'] . '" dipilih otomatis; tahun terbaca ' . ($p['tahun'] ?? '?'), $p['lembar'] === 'SKBM 2026-2027' && $p['tahun'] === '2026/2027');
        $this->cek('dua blok kolom kelas ditemukan (33 dan 42 kolom)', count($p['blok']) === 2 && count($p['blok'][0]['kelas']) === 33 && count($p['blok'][1]['kelas']) === 42, json_encode(array_map(static fn ($b) => count($b['kelas']), $p['blok'])));
        $this->cek('guru terbaca ≥ 50; sel berangka: blok 1 = ' . $sel[0] . ', blok 2 = ' . $sel[1], count($p['guru']) >= 50 && $sel[1] === 491 && $sel[0] === 384);
        $p = $imp->cocokkan($p);
        $this->cek('blok SKBM yang benar (kolom kedua, 42 kelas dikenal) dipilih otomatis', $p['blok_dipilih'] === 1 && $p['blok'][1]['dikenal'] === 42, json_encode([$p['blok_dipilih'], $p['blok'][0]['dikenal'], $p['blok'][1]['dikenal']]));
        $st = array_count_values(array_column($p['guru'], 'status'));
        CLI::write('  (info) pencocokan guru ke Master Guru: ' . json_encode($st), 'light_gray');
        $putus = [];
        foreach ($p['guru'] as $i => $g) {
            $putus[$i] = $g['guru_id'] !== null ? 'guru:' . $g['guru_id'] : 'lewati';
        }
        $r = $imp->terapkan('2026/2027', $p, $putus, true);
        $this->cek('terapkan SKBM 2026/2027', $r['ok'], $r['pesan']);
        $s = new Skbm();
        $m = $s->muat('2026/2027');
        $this->cek('SKBM tersimpan: ' . $m['jumlah_guru'] . ' guru, ' . $m['jumlah_mapel'] . ' baris mapel, ' . $m['jumlah_sel'] . ' sel, ' . $m['total_jp'] . ' JP', $m['jumlah_guru'] > 40 && $m['jumlah_sel'] > 400);
        $r2 = $imp->terapkan('2026/2027', $p, $putus, false);
        $this->cek('terapkan lagi tanpa "ganti": semua dilewati, tidak ada baris ganda', $r2['ok'] && $r2['ringkas']['guru'] === 0 && $s->muat('2026/2027')['jumlah_mapel'] === $m['jumlah_mapel'], $r2['pesan']);
        $this->cek('terapkan dengan blok 1 (bukan SKBM) juga bisa dipilih Admin', $imp->cocokkan($p, 0)['blok_dipilih'] === 0);

        // banding struktur dengan KOREKSI NILAI.xlsx: (guru, mapel) → himpunan kelas
        $ki = new HonorKoreksiImpor();
        $k  = $ki->baca($koreksi);
        $kel = [];
        foreach ($k['kelas'] as $kk) {
            $kel[$kk['c']] = $kk['kunci'];
        }
        $dariKoreksi = [];
        foreach ($k['guru'] as $g) {
            foreach ($g['baris'] as $br) {
                $dariKoreksi[HonorImpor::normalNama($g['nama']) . '|' . mb_strtolower($br['mapel'])] = array_values(array_unique(array_map(static fn ($c) => $kel[$c], array_keys($br['sel']))));
            }
        }
        $kunciKelas = [];
        foreach ($m['kelas'] as $kl) {
            $u = HonorKoreksi::uraiKelas($kl['nama']);
            $kunciKelas[$kl['id']] = HonorKoreksi::kunciKelas($kl['tingkat'] !== '' ? $kl['tingkat'] : $u['tingkat'], $u['label']);
        }
        $sama = $beda = 0;
        $contoh = [];
        foreach ($m['guru'] as $g) {
            foreach ($g['mapel'] as $x) {
                $kunci = HonorImpor::normalNama($g['nama']) . '|' . mb_strtolower($x['nama']);
                if (! isset($dariKoreksi[$kunci])) {
                    continue;
                }
                $a = array_map(static fn ($id) => $kunciKelas[$id], array_keys($x['sel']));
                $b = $dariKoreksi[$kunci];
                sort($a);
                sort($b);
                if ($a === $b) {
                    $sama++;
                } else {
                    $beda++;
                    if (count($contoh) < 3) {
                        $contoh[] = $g['nama'] . '/' . $x['nama'];
                    }
                }
            }
        }
        $this->cek("SKBM vs KOREKSI NILAI: baris (guru, mapel) yang kelasnya SAMA = $sama dari " . ($sama + $beda) . ' yang bisa dibandingkan', $sama >= 90 && $sama / max(1, $sama + $beda) >= 0.95, 'beda: ' . implode(' ; ', $contoh));

        // sambungan ke Koreksi honor
        $jenis = 'ASTS1';
        $per = $this->db->table('ujian_periode')->where('jenis', $jenis)->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
        $this->db->table('honor_dokumen')->where('periode_id', $per['id'])->delete();
        $h = new HonorDokumen();
        $dokId = (int) $h->buat($per)['id'];
        $ids = array_values(array_unique(array_map(static fn ($g) => (int) $g['guru_id'], $m['guru'])));
        $h->tambahPenerima($dokId, $ids);
        $kor = new HonorKoreksi();
        $f = $kor->isiDariSkbm($dokId);
        $mk = $kor->muat($dokId);
        $this->cek('Koreksi honor: isi dari SKBM ' . $m['tahun'] . ' → ' . $mk['jumlah_guru'] . ' guru, ' . $mk['jumlah_mapel'] . ' baris mapel', $f['ok'] && $mk['jumlah_guru'] === $m['jumlah_guru'] && $mk['jumlah_mapel'] === $m['jumlah_mapel'], $f['pesan']);
        $selSkbm = $m['jumlah_sel'];
        $selKor = array_sum(array_map(static fn ($g) => array_sum(array_map(static fn ($x) => count($x['sel']), $g['mapel'])), $mk['guru']));
        $this->cek("jumlah sel kelas ceklis = SKBM ($selKor = $selSkbm)", $selKor === $selSkbm);
        $this->cek('diisi lagi tanpa "ganti": dilewati, tidak ada baris ganda', $kor->isiDariSkbm($dokId)['ok'] && $kor->muat($dokId)['jumlah_mapel'] === $mk['jumlah_mapel']);
        $this->cek('dengan "ganti": dibuat ulang, jumlah tetap', $kor->isiDariSkbm($dokId, true)['ok'] && $kor->muat($dokId)['jumlah_mapel'] === $mk['jumlah_mapel']);
        $this->cek('Koreksi honor tahun lain tanpa SKBM → pesan jelas', (function () use ($per): bool {
            $this->db->table('ujian_periode')->where('id', $per['id'])->update(['tahun_ajaran' => '2091/2092']);
            $r = (new HonorKoreksi())->isiDariSkbm((int) (new HonorDokumen())->dokumenPeriode((int) $per['id'])['id']);

            return ! $r['ok'] && str_contains($r['pesan'], '2091/2092') && str_contains($r['pesan'], 'belum diisi');
        })());
        $h->ubahStatus($dokId, 'final');
        $h->ubahStatus($dokId, 'dikunci');
        $this->cek('honor DIKUNCI → isi dari SKBM ditolak', ! $kor->isiDariSkbm($dokId, true)['ok']);
    }
}
