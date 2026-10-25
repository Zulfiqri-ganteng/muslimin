<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\HakPeran;
use App\Libraries\MenuAdmin;
use App\Libraries\PklHak;
use App\Libraries\SuratJenis;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Peran;

/**
 * Uji hak akses yang diatur Admin (rancangan: docs/DESAIN-HAK-AKSES.md):
 *   1. katalog menu utuh (awalan unik, item menu samping tercakup entrinya, semua rute admin terpetakan),
 *   2. KESETARAAN: dengan hak bawaan, mesin baru (HakPeran) memberi jawaban SAMA dengan aturan lama (Config\Peran + PklHak
 *      lama, disalin apa adanya di bawah) untuk SEMUA rute admin × 5 peran × GET/POST,
 *   3. gerbang API sama dengan aturan lama,
 *   4. hak rinci PKL & Surat: peta alamat, perluasan format lama, simpan format baru/lama, peringatan,
 *   5. Lihat vs Kelola, entri 'ikut', peran baru (tambah/ubah/hapus), beranda otomatis,
 *   6. halaman nyata: Sistem → Hak Akses (simpan menu / PKL & Surat / bawaan), menu bergaji (Honor, SKBM) yang diberikan Admin
 *      ke peran lain, mode "Lihat saja" (pita + penolakan simpan), hapus foto lab menurut menu pemiliknya.
 *
 * Tabel `peran` & kolom hak lama dipulihkan ke isi semula di akhir (juga bila uji berhenti di tengah).
 * Jalankan:  php spark dev:uji-hak
 */
class UjiHak extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-hak';
    protected $description = 'Uji hak akses diatur Admin: kesetaraan aturan lama vs baru, katalog menu, Lihat/Kelola, peran baru, hak PKL & Surat.';

    private const LABEL_UJI = 'ZZUJI Peran Hak';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    /** @var list<array<string, mixed>>|null */
    private ?array $peranAsli = null;
    private ?array $pengaturanAsli = null;

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
        if (! $this->db->tableExists('peran')) {
            CLI::write('Tabel peran belum ada. Jalankan "php spark migrate" dulu.', 'red');

            return EXIT_ERROR;
        }
        $this->simpanAsli();
        try {
            $this->setelBawaan();
            $this->ujiKatalog();
            $this->ujiKesetaraan();
            $this->ujiApi();
            $this->ujiPklHak();
            $this->ujiLevelDanPeranBaru();
            $this->ujiHalaman();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            $this->pulihkan();
        }

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // 1. Katalog
    // =================================================================

    private function ujiKatalog(): void
    {
        $this->bagian('Katalog menu');
        $kat = MenuAdmin::katalog();

        $awalan = [];
        $ganda  = [];
        foreach ($kat as $k => $e) {
            foreach ($e['awalan'] as $a) {
                if (isset($awalan[$a])) {
                    $ganda[] = $a . ' (' . $awalan[$a] . ' & ' . $k . ')';
                }
                $awalan[$a] = $k;
            }
        }
        $this->cek('awalan alamat unik antar-entri (' . count($awalan) . ' awalan, ' . count($kat) . ' entri)', $ganda === [], implode(' | ', $ganda));

        $salahItem = [];
        foreach (MenuAdmin::sidebar() as $g) {
            foreach ($g['items'] as $it) {
                $e = MenuAdmin::cariAlamat(HakPeran::rapikan((string) $it[0]));
                $milik = $e !== null && in_array((string) $it[0], array_map(static fn (array $x): string => (string) $x[0], $e['item']), true);
                if (! $milik) {
                    $salahItem[] = $it[0] . ' → ' . ($e['kunci'] ?? 'tidak ada');
                }
            }
        }
        $this->cek('tiap item menu samping tercakup entri pemiliknya', $salahItem === [], implode(' | ', $salahItem));

        $ikutSalah = [];
        foreach ($kat as $k => $e) {
            foreach ($e['ikut'] as $i) {
                if (! isset($kat[$i]) || $kat[$i]['ikut'] !== []) {
                    $ikutSalah[] = $k . '→' . $i;
                }
            }
        }
        $this->cek('entri "ikut" hanya merujuk entri biasa yang ada', $ikutSalah === [], implode(' | ', $ikutSalah));

        $grupSidebar = array_map(static fn (array $g): ?string => $g['title'], MenuAdmin::sidebar());
        $this->cek('urutan grup menu samping tetap (aturan urutan sidebar)', $grupSidebar === [null, 'GURU', 'SUPERVISI GURU', 'JADWAL & ABSENSI', 'GURU PIKET', 'UJIAN', 'UJI KOMPETENSI (UKK)', 'BANTUAN SISWA', 'KESISWAAN', 'PKL / PRAKERIN', 'LABORATORIUM', 'SURAT SEKOLAH', 'ARSIP & INFO', 'SISTEM'], json_encode($grupSidebar));
        $bagianSidebar = array_map(static fn (array $g): ?string => $g['bagian'], MenuAdmin::sidebar());
        $this->cek('bagian menu samping: Dashboard, 7 grup KURIKULUM, 5 grup LAYANAN SEKOLAH, garis, Sistem', $bagianSidebar === array_merge([null], array_fill(0, 7, 'KURIKULUM'), array_fill(0, 5, 'LAYANAN SEKOLAH'), ['']), json_encode($bagianSidebar));
        $segera = array_keys(array_filter($kat, static fn (array $e): bool => $e['segera']));
        $this->cek('menu "Segera" tersembunyi dari tabel Hak Akses & semua itemnya bertanda Segera', $segera !== [] && array_filter($segera, static fn (string $k): bool => $kat[$k]['tampil']) === []
            && array_filter(array_merge(...array_column(MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => in_array(MenuAdmin::cariAlamat(HakPeran::rapikan((string) $it[0]))['kunci'] ?? '', $segera, true) && empty($it[4])) === [], implode(', ', $segera));

        $tak = [];
        foreach ($this->ruteAdmin() as [$verb, $pola, $contoh]) {
            if (in_array($contoh, ['admin', 'admin/login', 'admin/logout'], true)) {
                continue; // pintu masuk & autentikasi (di luar / ditangani khusus penjaga rute)
            }
            if (MenuAdmin::cariAlamat(HakPeran::rapikan($contoh)) === null) {
                $tak[] = $verb . ' ' . $pola;
            }
        }
        $this->cek('semua rute admin tercakup entri katalog', $tak === [], count($tak) . ' tak tercakup: ' . implode(' | ', array_slice($tak, 0, 12)));

        $matriks = MenuAdmin::untukMatriks();
        $isi     = array_merge(...array_values($matriks));
        $this->cek('tabel Hak Akses tanpa entri admin/umum/ikut', array_filter($isi, static fn (array $e): bool => ! $e['tampil'] || ! in_array($e['jenis'], [MenuAdmin::MENU, MenuAdmin::MODUL], true)) === []
            && ! in_array('akun', array_column($isi, 'kunci'), true) && ! in_array('ujian_akar', array_column($isi, 'kunci'), true));
    }

    // =================================================================
    // 2. Kesetaraan aturan lama vs baru (hak bawaan)
    // =================================================================

    private function ujiKesetaraan(): void
    {
        $this->bagian('Kesetaraan: aturan lama vs mesin baru (hak bawaan)');
        $peran   = ['admin', 'operator', 'hubin', 'guru', ''];
        $contoh  = [];
        foreach ($this->ruteAdmin() as [, , $c]) {
            $contoh[$c] = true;
        }
        foreach (['admin/surat-lain', 'admin/pkl-lain', 'admin/surat/../master/guru', 'ADMIN/Surat/Asts', 'admin//surat//12//acc', '/admin/surat/', 'admin/profile',
            'admin/profile/password', 'admin/master/siswa?x=1', 'admin/pkl/12/surat/ulang', 'admin/surat/12/ubah', 'admin/master/siswa-x', 'admin/dokumen/9/unduh'] as $c) {
            $contoh[$c] = true;
        }
        // Rute BARU yang sengaja berbeda dari aturan lama (diuji terpisah).
        $baru = static fn (string $a): bool => (bool) preg_match('~^admin/(hak-akses|pkl/ttd-kepsek|surat/balasan/laporan|pkl/alasan|pkl/riwayat|pkl/\d+/(ditolak-perusahaan|keluarkan|kabar-keluar)|surat/\d+/pindah-siswa)(/|$)~', strtolower(trim($a, '/'))); // + revisi PKL 2026-10-20 (dev:uji-pkl-keluar); + Izin TKA pindah gelombang 2026-10-26 = pemegang ACC (dev:uji-surat)

        $beda = [];
        $n    = 0;
        foreach (array_keys($contoh) as $a) {
            if ($baru($a)) {
                continue;
            }
            foreach ($peran as $p) {
                $lama = $this->lamaBoleh($p, $a);
                foreach (['GET', 'POST'] as $m) {
                    $n++;
                    if (HakPeran::boleh($p, $a, $m) !== $lama) {
                        $beda[] = ($p !== '' ? $p : '(kosong)') . ' ' . $m . ' ' . $a . ' lama=' . json_encode($lama);
                    }
                }
            }
        }
        $this->cek('jawaban sama untuk ' . count($contoh) . ' alamat × 5 peran × GET/POST (' . $n . ' pertanyaan)', $beda === [], count($beda) . ' beda: ' . implode(' | ', array_slice($beda, 0, 8)));

        $menuBeda = [];
        foreach (['operator', 'hubin'] as $p) {
            foreach (MenuAdmin::sidebar() as $g) {
                foreach ($g['items'] as $it) {
                    if (! $baru((string) $it[0]) && HakAkses::boleh($p, (string) $it[0]) !== $this->lamaBoleh($p, (string) $it[0])) {
                        $menuBeda[] = $p . ' ' . $it[0];
                    }
                }
            }
        }
        $this->cek('menu samping Operator & Hubin sama dengan sebelumnya', $menuBeda === [], implode(' | ', $menuBeda));

        foreach (['admin', 'operator', 'hubin', 'guru', ''] as $p) {
            $lamaB = $this->lamaPeran($p)['beranda'] ?? 'admin/profile';
            $this->cek('beranda ' . ($p !== '' ? $p : '(kosong)') . ' = ' . $lamaB, HakPeran::beranda($p) === $lamaB, HakPeran::beranda($p));
        }
        $this->cek('label & dikenal sama (Admin, Operator Sekolah, Waka Hubin; peran asing ditandai)', HakAkses::label('operator') === 'Operator Sekolah' && HakAkses::label('hubin') === 'Waka Hubin'
            && HakAkses::label('guru') === 'Guru (tak dikenal)' && HakAkses::label('') === 'Tanpa peran' && ! HakAkses::dikenal('guru') && HakAkses::dikenal('admin'));
        $this->cek('rute baru: Hak Akses khusus Admin; ttd-kepsek butuh hak ttd_kepsek; kotak laporan Balasan = pembuat ATAU penyetuju Balasan',
            HakPeran::boleh('admin', 'admin/hak-akses', 'POST') && ! HakPeran::boleh('operator', 'admin/hak-akses') && ! HakPeran::boleh('hubin', 'admin/hak-akses')
            && ! HakPeran::boleh('operator', 'admin/pkl/ttd-kepsek', 'POST') && ! HakPeran::boleh('hubin', 'admin/pkl/ttd-kepsek', 'POST') && HakPeran::boleh('admin', 'admin/pkl/ttd-kepsek', 'POST')
            && HakPeran::boleh('operator', 'admin/surat/balasan/laporan') && HakPeran::boleh('hubin', 'admin/surat/balasan/laporan'));
    }

    // =================================================================
    // 3. API
    // =================================================================

    private function ujiApi(): void
    {
        $this->bagian('Gerbang API (aplikasi Android)');
        $sama = true;
        $info = [];
        foreach (['admin', 'operator', 'hubin', 'guru', ''] as $p) {
            $lama = $this->lamaAksesApi($p);
            if (HakPeran::aksesApi($p) !== $lama || HakPeran::bolehApi($p) !== ! empty($this->lamaPeran($p)['api'])) {
                $sama   = false;
                $info[] = $p . ': ' . json_encode(HakPeran::aksesApi($p)) . ' vs lama ' . json_encode($lama);
            }
        }
        $this->cek('aksesApi & bolehApi sama dengan aturan lama (admin ["*"], operator/hubin ["pkl","surat"])', $sama && HakPeran::aksesApi('operator') === ['pkl', 'surat'], implode(' | ', $info));

        $beda = [];
        foreach (['pkl', 'pkl/ajuan/12', 'surat', 'surat/12/acc', 'admin/profile', 'admin/profile/password', 'admin/master/siswa', 'pkl/../admin/akun', 'surat-lain', 'pkl-x', 'ujian/asts1', 'admin/skbm', 'auth/me'] as $a) {
            foreach (['admin', 'operator', 'hubin', 'guru', ''] as $p) {
                if (HakPeran::bolehApiAlamat($p, $a) !== $this->lamaBolehApiAlamat($p, $a)) {
                    $beda[] = $p . ' ' . $a;
                }
            }
        }
        $this->cek('bolehApiAlamat sama untuk 13 alamat × 5 peran', $beda === [], implode(' | ', $beda));
    }

    // =================================================================
    // 4. Hak rinci PKL & Surat
    // =================================================================

    private function ujiPklHak(): void
    {
        $this->bagian('Hak rinci PKL & Surat');
        $peta = [
            'admin/surat' => null, 'admin/surat/12' => null, 'admin/surat/excel' => null, 'admin/surat?status=menunggu' => null,
            'admin/surat/asts' => 'surat_buat:izin_asts', 'admin/surat/tka/9/ubah' => 'surat_buat:izin_tka', 'admin/surat/pernyataan-ortu' => 'surat_buat:pernyataan_ortu',
            'admin/surat/balasan' => 'surat_buat:balasan_pkl', 'admin/surat/penarikan' => 'surat_buat:penarikan_pkl',
            'admin/surat/balasan/laporan' => 'surat_buat:balasan_pkl|surat_acc:balasan_pkl', 'admin/surat/balasan/laporan/3/setujui' => 'surat_buat:balasan_pkl|surat_acc:balasan_pkl',
            'admin/surat/12/unduh' => 'surat_unduh:*', 'admin/surat/unduh-massal' => 'surat_unduh:*',
            'admin/surat/12/acc' => 'surat_acc:*', 'admin/surat/12/kembalikan' => 'surat_acc:*', 'admin/surat/12/batal-acc' => 'surat_acc:*', 'admin/surat/acc-massal' => 'surat_acc:*',
            'admin/surat/12/ajukan-ulang' => 'surat_buat:*', 'admin/surat/12/batal' => 'surat_buat:*', 'admin/surat/12/ubah' => 'surat_buat:*',
            'admin/pkl' => null, 'admin/pkl/12' => null, 'admin/pkl/12/surat' => 'surat', 'admin/pkl/hak-akses' => 'khusus_admin', 'admin/pkl/hapus/5' => 'hapus',
            'admin/pkl/12/acc' => 'acc', 'admin/pkl/acc-massal' => 'acc', 'admin/pkl/12/kembalikan' => 'ubah', 'admin/pkl/laporan/excel' => 'laporan',
            'admin/surat-lain' => null, 'admin/master/siswa' => null,
        ];
        $salah = [];
        foreach ($peta as $a => $harap) {
            if (PklHak::hakUntukAlamat($a) !== $harap) {
                $salah[] = $a . ' → ' . json_encode(PklHak::hakUntukAlamat($a));
            }
        }
        $this->cek('alamat → hak yang dituntut (' . count($peta) . ' alamat)', $salah === [], implode(' | ', $salah));
        $this->cek('Tanda Tangan: membuka (GET) cukup ttd ATAU ttd_kepsek; mengunggah (POST) sesuai bagiannya', PklHak::hakUntukAlamat('admin/pkl/ttd') === 'ttd|ttd_kepsek'
            && PklHak::hakUntukAlamat('admin/pkl/ttd', 'POST') === 'ttd' && PklHak::hakUntukAlamat('admin/pkl/ttd-kepsek', 'POST') === 'ttd_kepsek' && PklHak::hakUntukAlamat('admin/pkl/ttd/gambar') === 'ttd|ttd_kepsek');

        $bw = PklHak::bawaanLengkap();
        $this->cek('bawaan: Hubin = acc, ubah, alasan, ttd + ACC 4 jenis bernomor', $bw['hubin'] === ['acc', 'ubah', 'alasan', 'ttd', 'surat_acc:izin_asts', 'surat_acc:izin_tka', 'surat_acc:balasan_pkl', 'surat_acc:penarikan_pkl'], json_encode($bw['hubin']));
        $this->cek('bawaan: Operator = surat, laporan, ubah, pengaturan + buat & unduh 5 jenis (tanpa ACC)', count($bw['operator']) === 14 && in_array('surat_buat:pernyataan_ortu', $bw['operator'], true)
            && ! in_array('surat_acc:balasan_pkl', $bw['operator'], true) && ! in_array('acc', $bw['operator'], true), json_encode($bw['operator']));
        $this->cek('perluasLama: "acc" lama = ACC ajuan + ACC jenis bernomor; kunci asing dibuang; Pernyataan tak punya ACC', PklHak::perluasLama(['acc', 'ngawur']) === ['acc', 'surat_acc:izin_asts', 'surat_acc:izin_tka', 'surat_acc:balasan_pkl', 'surat_acc:penarikan_pkl']
            && ! in_array('surat_acc:pernyataan_ortu', PklHak::semuaKunci(), true));

        $this->cek('boleh(): pola :* , gabungan |, kunci lama surat_sekolah, kunci asing, peran asing', PklHak::boleh('hubin', 'surat_acc:*') && ! PklHak::boleh('hubin', 'surat_buat:*')
            && PklHak::boleh('hubin', 'surat_buat:balasan_pkl|surat_acc:balasan_pkl') && PklHak::boleh('operator', 'surat_sekolah') && ! PklHak::boleh('hubin', 'surat_sekolah')
            && ! PklHak::boleh('admin', 'ngawur') && PklHak::boleh('admin', 'surat_acc:*') && ! PklHak::boleh('guru', 'acc') && ! PklHak::boleh('', 'acc') && ! PklHak::boleh('operator', 'surat_ngawur:*'));
        $pu = PklHak::petaUntuk('operator');
        $this->cek('petaUntuk: semua kunci PKL + kunci lama surat_sekolah (aplikasi lama)', array_keys($pu) === ['acc', 'surat', 'surat_sekolah', 'laporan', 'ubah', 'alasan', 'ttd', 'ttd_kepsek', 'pengaturan', 'hapus', 'hapus_disetujui'] && $pu['surat_sekolah'] && ! $pu['acc'], json_encode($pu));
        $ps = PklHak::petaSurat('hubin');
        $this->cek('petaSurat: Pernyataan tanpa ACC (null); Hubin ACC Balasan, tidak membuat', $ps['pernyataan_ortu']['acc'] === null && $ps['balasan_pkl']['acc'] === true && $ps['balasan_pkl']['buat'] === false && count($ps) === 5);

        $adm = ['oleh' => 'ZZUJI Admin', 'admin_id' => null, 'peran' => 'admin', 'ip' => '10.9.6.6'];
        // Format baru: Balasan di-ACC Operator saja.
        $kirim = PklHak::bawaanLengkap();
        $kirim['operator'][] = 'surat_acc:balasan_pkl';
        $kirim['hubin']      = array_values(array_diff($kirim['hubin'], ['surat_acc:balasan_pkl']));
        $r = PklHak::simpan($kirim, $adm, true);
        $this->cek('simpan format baru: Balasan di-ACC Operator saja (Hubin dicabut) → langsung berlaku', $r['ok'] && PklHak::bolehSurat('operator', 'acc', 'balasan_pkl') && ! PklHak::bolehSurat('hubin', 'acc', 'balasan_pkl')
            && PklHak::bolehSurat('hubin', 'acc', 'izin_asts'), $r['pesan']);
        $this->cek('peringatan: Operator memegang ACC sekaligus unduh (pemisahan tugas) — tidak memblokir', $this->ada($r['peringatan'], 'Operator Sekolah memegang ACC sekaligus unduh surat'), json_encode($r['peringatan']));

        // Format lama (aplikasi lama): saklar acc/surat_sekolah TIDAK berubah → hak per jenis dipertahankan.
        $r2 = PklHak::simpan(['hubin' => ['acc', 'ubah', 'ttd'], 'operator' => ['surat', 'surat_sekolah', 'laporan', 'ubah', 'pengaturan']], $adm);
        $this->cek('simpan format lama tanpa mengubah saklar: hak per jenis (Balasan → Operator) tetap', $r2['ok'] && PklHak::bolehSurat('operator', 'acc', 'balasan_pkl') && ! PklHak::bolehSurat('hubin', 'acc', 'balasan_pkl'));
        $r3 = PklHak::simpan(['hubin' => ['ubah', 'ttd'], 'operator' => ['surat', 'surat_sekolah', 'laporan', 'ubah', 'pengaturan']], $adm);
        $this->cek('format lama: saklar "acc" Hubin dimatikan → ACC ajuan & SEMUA ACC surat Hubin hilang', $r3['ok'] && ! PklHak::boleh('hubin', 'acc') && PklHak::jenisBoleh('hubin', 'acc') === []);
        $this->cek('peringatan: tak ada peran selain Admin yang ACC ajuan PKL / Surat Izin ASTS', $this->ada($r3['peringatan'], 'Tidak ada peran selain Admin yang bisa ACC ajuan PKL') && $this->ada($r3['peringatan'], \App\Libraries\SuratJenis::label(\App\Libraries\SuratJenis::ASTS) . ': hanya Admin yang bisa acc'), json_encode($r3['peringatan']));
        $r4 = PklHak::simpan(['hubin' => ['acc', 'ubah', 'ttd'], 'operator' => ['surat', 'laporan', 'ubah', 'pengaturan']], $adm);
        $this->cek('format lama: saklar "acc" Hubin dinyalakan lagi → ACC semua jenis kembali; "surat_sekolah" Operator dimatikan → buat/unduh semua jenis hilang',
            $r4['ok'] && PklHak::jenisBoleh('hubin', 'acc') === ['izin_asts', 'izin_tka', 'balasan_pkl', 'penarikan_pkl'] && PklHak::jenisBoleh('operator', 'buat') === [] && PklHak::jenisBoleh('operator', 'unduh') === []
            && PklHak::bolehSurat('operator', 'acc', 'balasan_pkl'));
        $this->cek('Audit Log mencatat perubahan hak', $this->db->table('audit_log')->like('deskripsi', 'Hak PKL & Surat diubah oleh ZZUJI Admin')->countAllResults() >= 3);
        $this->setelBawaan();
    }

    // =================================================================
    // 5. Lihat vs Kelola, ikut, peran baru, beranda
    // =================================================================

    private function ujiLevelDanPeranBaru(): void
    {
        $this->bagian('Lihat vs Kelola, entri ikut, peran baru');
        $adm = ['oleh' => 'ZZUJI Admin', 'admin_id' => null, 'peran' => 'admin', 'ip' => '10.9.6.6'];
        $r   = HakPeran::tambahPeran(['label' => self::LABEL_UJI, 'ringkas' => 'uji', 'api' => false], $adm);
        $kode = (string) ($r['kode'] ?? '');
        $this->cek('tambah peran baru → kode dari nama, tanpa hak, bukan peran sistem', $r['ok'] && $kode === 'zzuji_peran_hak' && HakPeran::dikenal($kode) && empty(HakPeran::peran($kode)['sistem'])
            && HakPeran::peran($kode)['menu'] === [] && HakPeran::hakDaftar($kode) === [], json_encode($r));
        $this->cek('nama peran ganda ditolak (tanpa beda huruf besar)', ! HakPeran::tambahPeran(['label' => strtolower(self::LABEL_UJI)], $adm)['ok'] && ! HakPeran::tambahPeran(['label' => 'ab'], $adm)['ok']);
        $this->cek('peran baru tanpa menu: tidak boleh apa pun selain Profil; beranda = Profil', ! HakPeran::boleh($kode, 'admin/master/siswa') && HakPeran::boleh($kode, 'admin/profile') && HakPeran::beranda($kode) === 'admin/profile');

        HakPeran::simpanMenu([$kode => [
            'master_siswa' => 'lihat', 'jadwal' => 'kelola', 'pkl' => 'boleh', 'ujian_asas' => 'lihat',
            'master_kelas' => 'boleh', 'surat' => 'kelola', 'akun' => 'kelola', 'ujian_akar' => 'kelola', 'ngawur' => 'kelola',
        ]]);
        $menu = HakPeran::peran($kode)['menu'];
        $urutMenu = $menu; ksort($urutMenu);
        $this->cek('simpanMenu membuang level yang tak sah, entri admin/ikut, dan kunci asing', $urutMenu === ['jadwal' => 'kelola', 'master_siswa' => 'lihat', 'pkl' => 'boleh', 'ujian_asas' => 'lihat'], json_encode($menu));
        $this->cek('Lihat: GET boleh, POST ditolak; Kelola: keduanya boleh', HakPeran::boleh($kode, 'admin/master/siswa') && HakPeran::boleh($kode, 'admin/master/siswa/12/ubah')
            && ! HakPeran::boleh($kode, 'admin/master/siswa', 'POST') && ! HakPeran::boleh($kode, 'admin/master/siswa/12', 'post')
            && HakPeran::boleh($kode, 'admin/jadwal', 'POST') && HakPeran::boleh($kode, 'admin/cetak/jadwal-kelas/3/pdf') && HakPeran::boleh($kode, 'admin/kurikulum/dashboard'));
        $this->cek('entri ikut: akar admin/ujian terbuka (punya ASAS lihat); tab Honor tetap tertutup; foto lab tertutup', HakPeran::boleh($kode, 'admin/ujian') && ! HakPeran::boleh($kode, 'admin/ujian/asas/periode', 'POST')
            && HakPeran::boleh($kode, 'admin/ujian/asas/laporan/pdf') && ! HakPeran::boleh($kode, 'admin/ujian/asas/honor') && ! HakPeran::boleh($kode, 'admin/ujian/asts1') && ! HakPeran::boleh($kode, 'admin/lab-gambar/hapus/5', 'POST'));
        $this->cek('levelAlamat: lihat/kelola/boleh/null', HakPeran::levelAlamat($kode, 'admin/master/siswa/9') === 'lihat' && HakPeran::levelAlamat($kode, 'admin/jadwal') === 'kelola'
            && HakPeran::levelAlamat($kode, 'admin/pkl/daftar/menunggu') === 'boleh' && HakPeran::levelAlamat($kode, 'admin/akun') === null);
        $this->cek('modul tanpa hak rinci: Beranda PKL terbuka, ACC/surat/pengaturan tertutup; modul Surat tak dipegang → tertutup walau punya hak Balasan', HakPeran::boleh($kode, 'admin/pkl')
            && ! HakPeran::boleh($kode, 'admin/pkl/12/acc', 'POST') && ! HakPeran::boleh($kode, 'admin/pkl/pengaturan') && ! HakPeran::boleh($kode, 'admin/surat'));
        HakPeran::simpanHak([$kode => ['surat_buat:balasan_pkl', 'acc', 'ngawur']]);
        HakPeran::simpanMenu([$kode => ['master_siswa' => 'lihat', 'jadwal' => 'kelola', 'ujian_asas' => 'lihat', 'pkl' => 'boleh', 'surat' => 'boleh']]);
        $this->cek('hak rinci: Balasan bisa dibuat, jenis lain tidak; ACC ajuan PKL boleh; kunci asing dibuang', HakPeran::boleh($kode, 'admin/surat/balasan') && ! HakPeran::boleh($kode, 'admin/surat/asts')
            && HakPeran::boleh($kode, 'admin/pkl/12/acc', 'POST') && HakPeran::hakDaftar($kode) === ['acc', 'surat_buat:balasan_pkl'] && HakPeran::boleh($kode, 'admin/surat/balasan/laporan'));
        $this->cek('Kelola Akun & Hak Akses tetap khusus Admin', ! HakPeran::boleh($kode, 'admin/akun') && ! HakPeran::boleh($kode, 'admin/hak-akses'));
        $this->cek('beranda otomatis = menu pertama yang boleh (Jadwal KBM — bagian Kurikulum di atas)', HakPeran::beranda($kode) === 'admin/jadwal', HakPeran::beranda($kode));

        $u = HakPeran::ubahPeran($kode, ['label' => self::LABEL_UJI . ' 2', 'beranda' => 'admin/jadwal', 'api' => true, 'ringkas' => 'uji 2'], $adm);
        $this->cek('ubah peran: nama, beranda, aplikasi → API hanya modul yang dipegang (pkl, surat)', $u['ok'] && HakPeran::label($kode) === self::LABEL_UJI . ' 2' && HakPeran::beranda($kode) === 'admin/jadwal'
            && HakPeran::aksesApi($kode) === ['pkl', 'surat'] && HakPeran::bolehApiAlamat($kode, 'pkl/ajuan') && ! HakPeran::bolehApiAlamat($kode, 'admin/master/siswa'), json_encode(HakPeran::aksesApi($kode)));
        $this->cek('beranda yang tak boleh dibuka diganti otomatis', HakPeran::ubahPeran($kode, ['label' => self::LABEL_UJI . ' 2', 'beranda' => 'admin/akun', 'api' => true], $adm)['ok'] && HakPeran::beranda($kode) === 'admin/jadwal');
        $this->cek('Admin & peran sistem tidak bisa diubah/dihapus', ! HakPeran::ubahPeran('admin', ['label' => 'Admin X'], $adm)['ok'] && ! HakPeran::hapusPeran('operator', $adm)['ok'] && ! HakPeran::hapusPeran('admin', $adm)['ok']);
        $this->cek('kodeDari: "Waka Kesiswaan" → waka_kesiswaan; diawali angka → peran_…; ≤ 20 huruf', HakPeran::kodeDari('Waka Kesiswaan') === 'waka_kesiswaan' && HakPeran::kodeDari('123 Abc') === 'peran_123_abc'
            && strlen(HakPeran::kodeDari('Wakil Kepala Sekolah Bidang Kurikulum Dan Lainnya')) <= 20 && preg_match(HakPeran::POLA_KODE, HakPeran::kodeDari('Tata Usaha')) === 1);

        $h = HakPeran::hapusPeran($kode, $adm);
        $this->cek('hapus peran buatan yang tak dipakai akun', $h['ok'] && ! HakPeran::dikenal($kode), $h['pesan']);
    }

    // =================================================================
    // 6. Halaman nyata (controller web)
    // =================================================================

    /**
     * Panggil metode controller sebagai $peran di $alamat dengan metode HTTP $http (sesi & request ditiru). Id akun sesi memakai
     * akun sungguhan (1 admin, 2 hubin, 3 operator) karena Audit Log punya kunci asing ke tabel admins.
     */
    private function panggil(string $kelas, string $peran, string $alamat, string $http, string $metode, array $post = [], mixed ...$arg): mixed
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), $alamat), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => 'ZZUJI ' . $peran, 'username' => 'zzujihak', 'role' => $peran]);
        session()->remove(['_ci_old_input', 'error', 'success', 'errors', 'peringatan_hak']);
        $request->setGlobal('get', []);
        $request->setGlobal('post', $post);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** Kiriman menu SEMUA peran yang diatur (seperti formulir aslinya), dengan perubahan $ubah[peran][entri] = level|''. */
    private function kirimanMenu(array $ubah): array
    {
        $out = [];
        foreach (HakPeran::peranAtur() as $kode) {
            $out[$kode] = array_merge((array) (HakPeran::peran($kode)['menu'] ?? []), $ubah[$kode] ?? []);
        }

        return $out;
    }

    private function ujiHalaman(): void
    {
        $this->bagian('Halaman nyata: Hak Akses, menu bergaji, Lihat saja, foto lab');
        $this->setelBawaan();
        $fotoId = 0;
        try {
            // --- Sistem → Hak Akses ---
            $html = (string) $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses', 'GET', 'index');
            $this->cek('halaman Hak Akses (Admin): tabel menu per peran tampil', str_contains($html, 'name="menu[operator][skbm]"') && str_contains($html, 'name="menu[hubin][pkl]"'));
            $r = $this->panggil(\App\Controllers\Admin\AturHak::class, 'operator', 'admin/hak-akses', 'GET', 'index');
            $this->cek('halaman Hak Akses (Operator) → ditolak, dialihkan', $r instanceof RedirectResponse && str_contains((string) session('error'), 'Admin'));
            $r = $this->panggil(\App\Controllers\Admin\AturHak::class, 'operator', 'admin/hak-akses/menu', 'POST', 'simpanMenu', ['menu' => $this->kirimanMenu(['operator' => ['akun' => 'kelola', 'skbm' => 'kelola']])]);
            $this->cek('Operator mencoba menyimpan Hak Akses → ditolak, tidak ada yang berubah', $r instanceof RedirectResponse && ! isset(HakPeran::peran('operator')['menu']['skbm']));

            // Admin memberi Operator: SKBM "Lihat", Honor ujian "Lihat", Pengaturan Honor "Lihat", Peminjaman "Kelola"; mencoba Kelola Akun (dibuang).
            $r = $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses/menu', 'POST', 'simpanMenu', ['menu' => $this->kirimanMenu(['operator' => [
                'skbm' => 'lihat', 'honor_ujian' => 'lihat', 'honor_pengaturan' => 'lihat', 'peminjaman' => 'kelola', 'akun' => 'kelola', 'hak_akses' => 'kelola',
            ]])]);
            $m = (array) (HakPeran::peran('operator')['menu'] ?? []);
            $this->cek('Admin menyimpan menu → berlaku langsung; Kelola Akun & Hak Akses tidak bisa diberikan', $r instanceof RedirectResponse && ($m['skbm'] ?? '') === 'lihat' && ($m['honor_ujian'] ?? '') === 'lihat'
                && ($m['peminjaman'] ?? '') === 'kelola' && ! isset($m['akun']) && ! isset($m['hak_akses']) && ($m['pkl'] ?? '') === 'boleh' && str_contains((string) session('success'), 'disimpan'), json_encode($m));
            $this->cek('… tercatat di Audit Log', $this->db->table('audit_log')->like('deskripsi', 'Menu per peran diubah oleh ZZUJI admin')->countAllResults() > 0);

            // --- SKBM (Lihat saja) ---
            $html = (string) $this->panggil(\App\Controllers\Admin\Skbm::class, 'operator', 'admin/skbm', 'GET', 'index');
            $this->cek('SKBM (Operator, Lihat): halaman terbuka + pita "Mode lihat saja" + penanda formulir dinonaktifkan', str_contains($html, 'Mode lihat saja') && str_contains($html, 'data-lihat-saja="1"'));
            $r = $this->panggil(\App\Controllers\Admin\Skbm::class, 'operator', 'admin/skbm/sel', 'POST', 'sel', ['mapel' => '1', 'kelas' => '1', 'aktif' => '1']);
            $j = $r instanceof ResponseInterface ? (json_decode((string) $r->getBody(), true) ?: []) : [];
            $this->cek('SKBM (Lihat): aksi ubah ditolak 403 "hanya bisa melihat"', $r instanceof ResponseInterface && $r->getStatusCode() === 403 && str_contains((string) ($j['pesan'] ?? ''), 'hanya bisa melihat'), (string) ($j['pesan'] ?? ''));
            $r = $this->panggil(\App\Controllers\Admin\Skbm::class, 'hubin', 'admin/skbm', 'GET', 'index');
            $this->cek('SKBM (Waka Hubin, tanpa hak) → dialihkan "tidak punya hak"', $r instanceof RedirectResponse && str_contains((string) session('error'), 'tidak punya hak membuka SKBM'));
            $html = (string) $this->panggil(\App\Controllers\Admin\Skbm::class, 'admin', 'admin/skbm', 'GET', 'index');
            $this->cek('SKBM (Admin): tanpa pita "Lihat saja"', $html !== '' && ! str_contains($html, 'data-lihat-saja'));

            // --- Pengaturan Honor (Lihat) ---
            $html = (string) $this->panggil(\App\Controllers\Admin\HonorPengaturan::class, 'operator', 'admin/honor/pengaturan', 'GET', 'index');
            $r    = $this->panggil(\App\Controllers\Admin\HonorPengaturan::class, 'operator', 'admin/honor/pengaturan/komponen', 'POST', 'simpanKomponen', []);
            $this->cek('Pengaturan Honor (Lihat): halaman terbuka, menyimpan ditolak "hanya bisa melihat"', str_contains($html, 'Mode lihat saja') && $r instanceof RedirectResponse && str_contains((string) session('error'), 'hanya bisa melihat Pengaturan Honor'), (string) session('error'));

            // --- Honor ujian: Lihat → ubah ditolak; Kelola → lolos pagar hak; peran tanpa hak → ditolak ---
            $r = $this->panggil(\App\Controllers\Admin\UjianHonor::class, 'operator', 'admin/ujian/asts1/honor/buat', 'POST', 'buat', [], 'asts1');
            $this->cek('Honor ujian (Lihat): membuat honor ditolak "hanya bisa melihat"', $r instanceof RedirectResponse && str_contains((string) session('error'), 'hanya bisa melihat Honor'), (string) session('error'));
            $r = $this->panggil(\App\Controllers\Admin\UjianHonor::class, 'hubin', 'admin/ujian/asts1/honor/impor', 'GET', 'impor', [], 'asts1');
            $this->cek('Honor ujian (Waka Hubin, tanpa hak) → ditolak', $r instanceof RedirectResponse && str_contains((string) session('error'), 'tidak punya hak membuka Honor'));
            $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses/menu', 'POST', 'simpanMenu', ['menu' => $this->kirimanMenu(['operator' => ['honor_ujian' => 'kelola']])]);
            $r = $this->panggil(\App\Controllers\Admin\UjianHonor::class, 'operator', 'admin/ujian/asts1/honor/buat', 'POST', 'buat', [], 'asts1');
            $this->cek('Honor ujian (Kelola): lolos pagar hak (berhenti di pemeriksaan periode, data tak berubah)', $r instanceof RedirectResponse && ! str_contains((string) session('error'), 'Hak Akses'), (string) session('error'));
            $this->cek('… tab Honor muncul untuk Operator (HakAkses), Waka Hubin tidak', HakAkses::boleh('operator', 'admin/ujian/asts1/honor') && ! HakAkses::boleh('hubin', 'admin/ujian/asts1/honor'));

            // --- Foto lab: hapus menurut menu pemilik foto ---
            $this->db->table('lab_gambar')->insert(['entitas' => 'aset', 'entitas_id' => 999999, 'file' => 'zzuji_hak_tidak_ada.webp', 'urutan' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $fotoId = (int) $this->db->insertID();
            $r = $this->panggil(\App\Controllers\Admin\LabGambar::class, 'operator', 'admin/lab-gambar/hapus/' . $fotoId, 'POST', 'delete', [], $fotoId);
            $this->cek('hapus foto Aset oleh peran yang hanya Kelola Peminjaman → ditolak, foto tetap ada', $r instanceof RedirectResponse && str_contains((string) session('error'), 'foto Aset')
                && $this->db->table('lab_gambar')->where('id', $fotoId)->countAllResults() === 1, (string) session('error'));
            $r = $this->panggil(\App\Controllers\Admin\LabGambar::class, 'admin', 'admin/lab-gambar/hapus/' . $fotoId, 'POST', 'delete', [], $fotoId);
            $this->cek('hapus foto oleh Admin → terhapus', $r instanceof RedirectResponse && $this->db->table('lab_gambar')->where('id', $fotoId)->countAllResults() === 0);

            // --- PKL & Surat lewat halaman: "ACC Surat Balasan = Operator saja", lalu kembalikan ke bawaan ---
            $hak = [];
            foreach (HakPeran::peranAtur() as $kode) {
                $hak[$kode] = HakPeran::hakDaftar($kode);
            }
            $hak['operator'][] = 'surat_acc:balasan_pkl';
            $hak['hubin']      = array_values(array_diff($hak['hubin'], ['surat_acc:balasan_pkl']));
            $acc = array_values(array_filter(SuratJenis::kode(), static fn (string $j): bool => SuratJenis::perluAcc($j)));
            $r   = $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses/pkl', 'POST', 'simpanPkl', ['hak' => $hak, 'acc' => $acc]);
            PklHak::lupakan();
            $this->cek('halaman Hak Akses (PKL & Surat): ACC Surat Balasan dipindah ke Operator saja; jenis lain tetap Waka Hubin',
                $r instanceof RedirectResponse && PklHak::bolehSurat('operator', 'acc', SuratJenis::BALASAN) && ! PklHak::bolehSurat('hubin', 'acc', SuratJenis::BALASAN)
                && PklHak::bolehSurat('hubin', 'acc', SuratJenis::ASTS) && ! PklHak::bolehSurat('operator', 'acc', SuratJenis::ASTS) && PklHak::boleh('hubin', 'acc'), (string) session('error'));
            $this->cek('… aturan wajib ACC tidak berubah', array_values(array_filter(SuratJenis::kode(), static fn (string $j): bool => SuratJenis::perluAcc($j))) === $acc);
            $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses/bawaan', 'POST', 'bawaan', ['bagian' => 'pkl']);
            $this->panggil(\App\Controllers\Admin\AturHak::class, 'admin', 'admin/hak-akses/bawaan', 'POST', 'bawaan', ['bagian' => 'menu']);
            PklHak::lupakan();
            $this->cek('"Kembalikan ke bawaan" (PKL & menu): Waka Hubin ACC lagi, Operator kehilangan SKBM/Honor', PklHak::bolehSurat('hubin', 'acc', SuratJenis::BALASAN) && ! PklHak::bolehSurat('operator', 'acc', SuratJenis::BALASAN)
                && ! HakAkses::boleh('operator', 'admin/skbm') && ! HakAkses::boleh('operator', 'admin/ujian/asts1/honor'));
        } finally {
            if ($fotoId > 0) {
                $this->db->table('lab_gambar')->where('id', $fotoId)->delete();
            }
            session()->remove(['admin', 'error', 'success', 'errors', 'peringatan_hak']);
            $this->setelBawaan();
        }
    }

    // =================================================================
    // Aturan LAMA (disalin dari HakAkses & PklHak sebelum 2026-10-17) — pembanding kesetaraan
    // =================================================================

    private function lamaPeran(string $p): ?array
    {
        return $p !== '' ? (config(Peran::class)->peran[$p] ?? null) : null;
    }

    private function lamaBoleh(string $peran, string $alamat): bool
    {
        $p = $this->lamaPeran($peran);
        if ($p === null) {
            return false;
        }
        $alamat = HakPeran::rapikan($alamat);
        if (str_contains($alamat, '..')) {
            return false;
        }
        foreach (config(Peran::class)->umum as $awalan) {
            if (HakPeran::cocokAwalan($alamat, $awalan)) {
                return true;
            }
        }
        foreach ($p['kecuali'] ?? [] as $awalan) {
            if (HakPeran::cocokAwalan($alamat, $awalan)) {
                return false;
            }
        }
        foreach ($p['akses'] as $awalan) {
            if ($awalan === '*' || HakPeran::cocokAwalan($alamat, $awalan)) {
                $hak = $this->lamaHakAlamat($alamat);
                if ($hak === null) {
                    return true;
                }
                if ($hak === 'khusus_admin') {
                    return $peran === 'admin';
                }

                return $this->lamaBolehPkl($peran, $hak);
            }
        }

        return false;
    }

    private function lamaHakAlamat(string $alamat): ?string
    {
        $a = HakPeran::rapikan($alamat);
        if ($a === 'admin/surat' || str_starts_with($a, 'admin/surat/')) {
            $sisa = ltrim(substr($a, strlen('admin/surat')), '/');

            return match (true) {
                $sisa === '' || $sisa === 'excel' || (bool) preg_match('~^\d+$~', $sisa) => null,
                (bool) preg_match('~^(acc-massal|\d+/(acc|batal-acc|kembalikan))$~', $sisa) => 'acc',
                default => 'surat_sekolah',
            };
        }
        if ($a !== 'admin/pkl' && ! str_starts_with($a, 'admin/pkl/')) {
            return null;
        }
        $sisa = ltrim(substr($a, strlen('admin/pkl')), '/');
        if ($sisa === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('~^hak-akses(/|$)~', $sisa)                 => 'khusus_admin',
            (bool) preg_match('~^(pengaturan|impor)(/|$)~', $sisa)        => 'pengaturan',
            (bool) preg_match('~^hapus(/|$)~', $sisa)                     => 'hapus',
            (bool) preg_match('~^ttd(/|$)~', $sisa)                       => 'ttd',
            (bool) preg_match('~^laporan(/|$)~', $sisa)                   => 'laporan',
            (bool) preg_match('~^(surat|surat-massal|wa)(/|$)~', $sisa)   => 'surat',
            (bool) preg_match('~^acc-massal$~', $sisa)                    => 'acc',
            (bool) preg_match('~^(baru|siswa-kelas)$~', $sisa)            => 'ubah',
            (bool) preg_match('~^\d+/(acc|tolak|batal-acc)$~', $sisa)     => 'acc',
            (bool) preg_match('~^\d+/(ubah|kembalikan)$~', $sisa)         => 'ubah',
            (bool) preg_match('~^\d+/(surat|pembayaran|wa)(/|$)~', $sisa) => 'surat',
            default                                                       => null,
        };
    }

    private function lamaBolehPkl(string $peran, string $hak): bool
    {
        if (! in_array($hak, ['acc', 'surat', 'surat_sekolah', 'laporan', 'ubah', 'ttd', 'pengaturan', 'hapus'], true)) {
            return false;
        }
        if ($peran === 'admin') {
            return true;
        }
        if (! in_array($peran, ['hubin', 'operator'], true)) {
            return false;
        }
        $pakai = ['hubin' => ['acc', 'ubah', 'ttd'], 'operator' => ['surat', 'surat_sekolah', 'laporan', 'ubah', 'pengaturan']];
        $mentah = (string) ($this->pengaturanAsli['hak_peran'] ?? '');
        $dek    = $mentah !== '' ? json_decode($mentah, true) : null;
        if (is_array($dek)) {
            $pakai = [];
            foreach (['hubin', 'operator'] as $p) {
                $pakai[$p] = is_array($dek[$p] ?? null) ? array_map('strval', $dek[$p]) : [];
            }
        }

        return in_array($hak, $pakai[$peran] ?? [], true);
    }

    /** @return list<string> */
    private function lamaAksesApi(string $peran): array
    {
        $p = $this->lamaPeran($peran);
        if ($p === null || empty($p['api'])) {
            return [];
        }
        if (in_array('*', (array) ($p['akses'] ?? []), true)) {
            return ['*'];
        }

        return array_values(array_map('strval', (array) ($p['api_akses'] ?? [])));
    }

    private function lamaBolehApiAlamat(string $peran, string $alamat): bool
    {
        $p = $this->lamaPeran($peran);
        if ($p === null || empty($p['api'])) {
            return false;
        }
        if (in_array('*', (array) ($p['akses'] ?? []), true)) {
            return true;
        }
        if (str_contains($alamat, '..')) {
            return false;
        }
        foreach (array_merge(config(Peran::class)->umum, (array) ($p['api_akses'] ?? [])) as $awalan) {
            if (HakPeran::cocokAwalan($alamat, (string) $awalan)) {
                return true;
            }
        }

        return false;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /**
     * Semua rute admin: [metode, pola, contoh alamat]. Pola regex diisi contoh: angka → 12, segmen → asts1.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function ruteAdmin(): array
    {
        $koleksi = service('routes');
        $koleksi->loadRoutes();
        $out = [];
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $verb) {
            foreach (array_keys($koleksi->getRoutes($verb)) as $pola) {
                $pola = (string) $pola;
                if ($pola !== 'admin' && ! str_starts_with($pola, 'admin/')) {
                    continue;
                }
                $contoh = str_replace(['([0-9]+)', '(:num)'], '12', $pola);
                $contoh = (string) preg_replace('/\([^()]*\)/', 'asts1', $contoh);
                $out[]  = [$verb, $pola, $contoh];
            }
        }

        return $out;
    }

    /** @param list<string> $daftar */
    private function ada(array $daftar, string $potongan): bool
    {
        foreach ($daftar as $d) {
            if (str_contains($d, $potongan)) {
                return true;
            }
        }

        return false;
    }

    /** Isi tabel peran (Admin, Operator, Hubin) dengan bawaan; peran buatan dibiarkan. */
    private function setelBawaan(): void
    {
        HakPeran::lupakan();
        $now = date('Y-m-d H:i:s');
        foreach (HakPeran::bawaan() as $kode => $p) {
            $data = ['menu' => json_encode($p['menu'], JSON_FORCE_OBJECT), 'hak' => json_encode(array_values($p['hak'])), 'label' => $p['label'], 'beranda' => $p['beranda'], 'api' => $p['api'] ? 1 : 0, 'updated_at' => $now];
            if ($this->db->table('peran')->where('kode', $kode)->countAllResults() > 0) {
                $this->db->table('peran')->where('kode', $kode)->update($data);
            } else {
                $this->db->table('peran')->insert($data + ['kode' => $kode, 'ringkas' => $p['ringkas'], 'sistem' => 1, 'urut' => $p['urut'], 'created_at' => $now]);
            }
        }
        HakPeran::lupakan();
    }

    private function simpanAsli(): void
    {
        $this->peranAsli      = $this->db->table('peran')->get()->getResultArray();
        $this->pengaturanAsli = $this->db->table('pkl_pengaturan')->select('hak_peran, surat_perlu_acc')->where('id', 1)->get()->getRowArray() ?? [];
        // Sisa uji yang berhenti di tengah.
        $this->db->table('peran')->like('label', 'ZZUJI', 'after')->delete();
        $this->peranAsli = array_values(array_filter($this->peranAsli, static fn (array $r): bool => ! str_starts_with((string) $r['label'], 'ZZUJI')));
    }

    private function pulihkan(): void
    {
        if ($this->peranAsli !== null) {
            $this->db->table('peran')->truncate();
            foreach ($this->peranAsli as $r) {
                $this->db->table('peran')->insert($r);
            }
        }
        if ($this->pengaturanAsli !== null && $this->pengaturanAsli !== []) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
        }
        $this->db->table('audit_log')->like('deskripsi', 'ZZUJI')->delete();
        HakPeran::lupakan();
    }
}
