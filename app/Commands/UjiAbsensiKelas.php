<?php

namespace App\Commands;

use App\Libraries\AbsensiHarian;
use App\Libraries\AbsensiWa;
use App\Models\AbsensiHariModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JadwalPiketModel;
use App\Models\KelasModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Uji regresi absensi PER KELAS: urutan natural kelas, data kelas di
 * AbsensiHarian::muat(), dan pesan WhatsApp per kelas (AbsensiWa).
 *
 * Butuh jadwal nyata → jalankan pada salinan DB asli:
 *     php spark dev:uji-absensi-kelas --db=muslimin_asli
 *
 * Data uji ditulis pada tanggal jauh (Senin 7 Januari 2030) dan dihapus lagi
 * di akhir. Perintah menolak jalan bila tanggal itu sudah berisi absensi.
 */
class UjiAbsensiKelas extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-absensi-kelas';
    protected $description = 'Uji absensi per kelas + pesan WA per kelas.';
    protected $usage       = 'dev:uji-absensi-kelas [--db=nama_database]';
    protected $options     = ['--db' => 'Nama database uji (default: dari .env).'];

    private const TANGGAL = '2030-01-07';

    private int $lulus = 0;
    private int $gagal = 0;

    /** @var list<int> id baris jadwal_piket yang DIBUAT uji ini */
    private array $piketUji = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? ' — ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul, 'yellow');
    }

    public function run(array $params)
    {
        $db = $params['db'] ?? CLI::getOption('db');
        if (is_string($db) && $db !== '') {
            config('Database')->default['database'] = $db;
        }
        CLI::write('Database: ' . db_connect()->getDatabase(), 'light_gray');

        try {
            return $this->doRun();
        } catch (Throwable $e) {
            CLI::error('ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function doRun(): int
    {
        $this->ujiUrutNatural();

        if ((new AbsensiHariModel())->isRecorded(self::TANGGAL)) {
            CLI::error('Tanggal ' . self::TANGGAL . ' sudah berisi absensi — uji dibatalkan agar data tidak terhapus.');

            return EXIT_ERROR;
        }
        $hari = (new HariModel())->byWeekday((int) date('N', strtotime(self::TANGGAL)));
        if (! $hari || (int) $hari['aktif'] !== 1) {
            CLI::error('Hari Senin tidak aktif di database ini.');

            return EXIT_ERROR;
        }
        $sesi = array_values(array_filter(
            (new JadwalModel())->sessionsForHari((int) $hari['id']),
            static fn ($s) => $s['jam_shift'] === 'pagi'
        ));
        if (count($sesi) < 20) {
            CLI::error('Jadwal Senin pagi terlalu sedikit (' . count($sesi) . ') — pakai --db=muslimin_asli.');

            return EXIT_ERROR;
        }

        $this->ujiMuat();
        $this->ujiPesan((int) $hari['id'], $sesi);
        $this->ujiRenderWeb();
        $this->bersihkan();

        CLI::newLine();
        CLI::write('RINGKASAN: ' . $this->lulus . ' lulus, ' . $this->gagal . ' gagal', $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // -----------------------------------------------------------------
    private function ujiUrutNatural(): void
    {
        $this->bagian('Urutan natural nama kelas');

        $urut = KelasModel::urutNatural([
            1 => 'X TKJ 10', 2 => 'X TKJ 2', 3 => 'XI AKL', 4 => 'X AKL',
            5 => 'XII TKJ 1', 6 => 'X TKJ 1', 7 => 'x mplb 1',
        ]);
        asort($urut);
        $hasil = implode(',', array_keys($urut));
        $this->cek('X AKL, X MPLB 1, X TKJ 1, X TKJ 2, X TKJ 10, XI AKL, XII TKJ 1', $hasil === '4,7,6,2,1,3,5', $hasil);
    }

    private function ujiMuat(): void
    {
        $this->bagian('AbsensiHarian::muat() — data kelas');

        $d     = AbsensiHarian::muat(self::TANGGAL);
        $kelas = $d['kelas'];
        $this->cek('daftar kelas terisi', count($kelas) > 0, (string) count($kelas));

        $nama  = array_column($kelas, 'nama');
        $alami = $nama;
        usort($alami, [KelasModel::class, 'bandingNatural']);
        $this->cek('daftar kelas urut natural', $nama === $alami, implode(' | ', array_slice($nama, 0, 12)));
        $this->cek('peringkat urut 1..n berurutan', array_column($kelas, 'urut') === range(1, count($kelas)));

        $unik = count(array_unique(array_column($kelas, 'kelas_id')));
        $this->cek('tiap kelas muncul sekali', $unik === count($kelas));

        $adaUrut = true;
        foreach ($d['grup'] as $g) {
            foreach ($g['sesi'] as $s) {
                $adaUrut = $adaUrut && isset($s['kelas_urut'], $s['tingkat']) && array_key_exists('jurusan_kode', $s);
            }
        }
        $this->cek('tiap sesi membawa kelas_urut, tingkat, jurusan_kode', $adaUrut);
    }

    /**
     * Skenario: A telat di sesi pertamanya (A juga piket), B izin semua sesi,
     * C belum hadir, D piket yang tidak mengajar, E staf hadir, F staf izin.
     */
    private function ujiPesan(int $hariId, array $sesi): void
    {
        $this->bagian('Pesan WhatsApp per kelas (KBM pagi)');

        $perGuru = [];
        foreach ($sesi as $s) {
            $perGuru[(int) $s['guru_id']][] = $s;
        }
        $peta   = GuruModel::petaOrang();
        $keluar = GuruModel::tidakIkutAbsensi();
        $calon  = array_values(array_filter(
            array_keys($perGuru),
            static fn ($id) => ! isset($peta[$id]) && ! isset($keluar[$id])
        ));
        // A = guru dengan ≥2 sesi berurutan di kelas yang sama (uji penggabungan jam).
        $a = null;
        foreach ($calon as $id) {
            $s = $perGuru[$id];
            usort($s, static fn ($x, $y) => (int) $x['jam_ke'] <=> (int) $y['jam_ke']);
            if (count($s) >= 2 && $s[0]['kelas_id'] === $s[1]['kelas_id'] && (int) $s[1]['jam_ke'] === (int) $s[0]['jam_ke'] + 1) {
                $a = $id;
                break;
            }
        }
        $sisa = array_values(array_diff($calon, [$a]));
        [$b, $c] = [$sisa[0] ?? null, $sisa[1] ?? null];
        $mengajar = array_keys($perGuru);
        $bebas    = array_values(array_filter(
            array_map('intval', array_column((new GuruModel())->select('id')->where('induk_id', null)->where('ikut_absensi', 1)->findAll(), 'id')),
            static fn ($id) => ! in_array($id, $mengajar, true)
        ));
        [$dd, $e, $f] = [$bebas[0] ?? null, $bebas[1] ?? null, $bebas[2] ?? null];
        if (in_array(null, [$a, $b, $c, $dd, $e, $f], true)) {
            $this->cek('data skenario cukup', false, 'guru kurang untuk skenario');

            return;
        }

        // Sesi A pertama = telat 07:20; semua sesi B = izin "uji".
        $sesiA = $perGuru[$a];
        usort($sesiA, static fn ($x, $y) => (int) $x['jam_ke'] <=> (int) $y['jam_ke']);
        $rows = [];
        $row  = static fn ($s, $st, $jm = '', $ket = '') => [
            'kelas_id' => $s['kelas_id'], 'jam_id' => $s['jam_id'], 'guru_id' => $s['guru_id'],
            'hari_id' => $s['hari_id'], 'mapel_id' => $s['mapel_id'], 'jadwal_id' => $s['jadwal_id'],
            'status' => $st, 'jam_masuk' => $jm, 'keterangan' => $ket,
        ];
        $rows[] = $row($sesiA[0], 'telat', '07:20');
        foreach ($perGuru[$b] as $s) {
            $rows[] = $row($s, 'izin', '', 'uji');
        }

        // Piket pagi Senin: A (mengajar) + D (tidak mengajar). Baris yang SUDAH ada
        // (data asli, UNIQUE hari+shift+guru) tidak disisipkan ulang & tidak
        // dihapus saat bersih-bersih — hanya id yang dibuat uji ini yang dibuang.
        $piket = new JadwalPiketModel();
        foreach ([$a, $dd] as $gid) {
            $baris = ['hari_id' => $hariId, 'shift' => 'pagi', 'guru_id' => $gid];
            if ($piket->where($baris)->countAllResults() === 0) {
                $this->piketUji[] = (int) $piket->insert($baris, true);
            }
        }

        AbsensiHarian::simpan(self::TANGGAL, [
            'rows'  => $rows,
            'kerja' => [
                ['guru_id' => $e, 'status' => 'hadir', 'shift' => 'penuh', 'jam_masuk' => '', 'keterangan' => ''],
                ['guru_id' => $f, 'status' => 'izin', 'shift' => 'pagi', 'jam_masuk' => '', 'keterangan' => 'uji'],
            ],
            'shift' => 'pagi',
            'belum' => [$c],
        ], null);

        $d = AbsensiWa::data(self::TANGGAL, 'pagi');

        // Setiap sesi pagi (guru ikut absensi) tercakup tepat sekali oleh baris kelas.
        $jumlahJam = 0;
        foreach (['hadir', 'belum', 'tidak_hadir'] as $daftar) {
            foreach ($d[$daftar] as $o) {
                if ($o['kelas'] !== null) {
                    [$awal, $akhir] = array_pad(explode('-', (string) $o['jam']), 2, null);
                    $jumlahJam += (int) ($akhir ?? $awal) - (int) $awal + 1;
                }
            }
        }
        $sesiIkut = count(array_filter($sesi, static fn ($s) => ! isset($keluar[$peta[(int) $s['guru_id']] ?? (int) $s['guru_id']])));
        $this->cek('semua sesi pagi tercakup tepat sekali', $jumlahJam === $sesiIkut, $jumlahJam . ' vs ' . $sesiIkut);

        // Urutan baris kelas: kelas natural → jam.
        $urutKelas = [];
        foreach (AbsensiHarian::muat(self::TANGGAL)['kelas'] as $k) {
            $urutKelas[$k['nama']] = $k['urut'];
        }
        $kunci = array_map(static fn ($o) => [$urutKelas[$o['kelas']], (int) $o['jam']], $d['hadir']);
        $terurut = $kunci;
        sort($terurut);
        $this->cek('daftar hadir urut kelas natural → jam', $kunci === $terurut);

        $milik = static fn (array $daftar, int $gid) => array_values(array_filter($daftar, static fn ($o) => $o['guru_id'] === $gid));

        // A: sesi pertama telat, digabung dengan sesi berikutnya di kelas yang sama.
        $pertama = null;
        foreach ($milik($d['hadir'], $a) as $o) {
            if ($o['kelas'] === $sesiA[0]['nama_kelas'] && (int) $o['jam'] === (int) $sesiA[0]['jam_ke']) {
                $pertama = $o;
            }
        }
        $this->cek('A: baris pertama bergabung "jam ke x-y"', $pertama && str_contains((string) $pertama['jam'], '-'), json_encode($pertama));
        $this->cek('A: baris pertama berstatus telat 07:20', $pertama && $pertama['status'] === 'telat' && $pertama['jam_masuk'] === '07:20');
        $this->cek('A (mengajar + piket): tetap di daftar piket', $milik($d['piket'], $a) !== []);

        // B: semua sesinya di "tidak hadir" berlabel izin, tidak ada di hadir.
        $barisB = $milik($d['tidak_hadir'], $b);
        $this->cek('B: ada di tidak hadir per kelas', $barisB !== [] && $barisB[0]['kelas'] !== null);
        $this->cek('B: berstatus izin + keterangan', $barisB !== [] && $barisB[0]['status'] === 'izin' && $barisB[0]['keterangan'] === 'uji');
        $this->cek('B: tidak ada di daftar hadir', $milik($d['hadir'], $b) === []);

        // C: belum hadir per kelas.
        $this->cek('C: ada di belum hadir per kelas', ($milik($d['belum'], $c)[0]['kelas'] ?? null) !== null);
        $this->cek('C: tidak ada di daftar hadir', $milik($d['hadir'], $c) === []);

        $barisD = $milik($d['piket'], $dd);
        $this->cek('D (piket tak mengajar): di daftar piket tanpa kelas', $barisD !== [] && $barisD[0]['kelas'] === null);
        $this->cek('E (staf hadir): di daftar staf', $milik($d['staf'], $e) !== []);
        $fBaris = $milik($d['tidak_hadir'], $f);
        $this->cek('F (staf izin): di ekor tidak hadir tanpa kelas', $fBaris !== [] && $fBaris[0]['kelas'] === null
            && end($d['tidak_hadir'])['kelas'] === null);

        // Teks final.
        $pesan = AbsensiWa::pesan(self::TANGGAL, 'pagi');
        $this->cek('pesan memuat baris "kelas … jam ke …"', (bool) preg_match('/^\d+\. .+ kelas .+ jam ke \d+(-\d+)?/m', $pesan));
        $this->cek('pesan memuat "(terlambat, masuk 07:20)"', str_contains($pesan, '(terlambat, masuk 07:20)'));
        $this->cek('pesan memuat "- izin (uji)"', str_contains($pesan, ' - izin (uji)'));
        $this->cek('baris kelas hadir tanpa akhiran " - hadir"', ! preg_match('/ kelas .+ jam ke [\d-]+ - hadir/', $pesan));

        CLI::newLine();
        CLI::write('--- Contoh potongan pesan ---', 'light_gray');
        CLI::write(implode("\n", array_slice(explode("\n", $pesan), 0, 22)));
        CLI::write('…');
    }

    /**
     * Render halaman web input absensi lewat controller asli (kedua tampilan).
     * Notice/warning dianggap gagal. Tiap sesi harus muncul TEPAT SATU baris
     * `.absen-row` (dibaca isiJson() saat simpan) di tampilan mana pun.
     */
    private function ujiRenderWeb(): void
    {
        $this->bagian('Halaman web input absensi (render controller)');

        $d       = AbsensiHarian::muat(self::TANGGAL);
        $harapan = [];
        foreach ($d['grup'] as $g) {
            foreach ($g['sesi'] as $s) {
                $harapan[] = $s['kelas_id'] . '-' . $s['jam_id'];
            }
        }
        sort($harapan);

        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            foreach (['kelas', 'guru'] as $tampilan) {
                $html = $this->renderHalaman($tampilan);

                preg_match_all('/data-kelas="(\d+)" data-jam="(\d+)"/', $html, $m, PREG_SET_ORDER);
                $baris = array_map(static fn ($x) => $x[1] . '-' . $x[2], $m);
                sort($baris);
                $this->cek("tampilan {$tampilan}: tiap sesi tepat satu baris", $baris === $harapan, count($baris) . ' vs ' . count($harapan));
                $this->cek("tampilan {$tampilan}: tombol aktif sesuai", (bool) preg_match(
                    '/gantiTampilan\(\'' . $tampilan . '\'\)"\s+class="[^"]*bg-brand-700/',
                    $html
                ));

                if ($tampilan === 'kelas') {
                    // esc(…, 'attr') menyandikan spasi & "|" → urai dulu (seperti dataset di browser).
                    preg_match_all('/data-cari="([^"]*)"/', $html, $c);
                    $urutHtml = array_map(
                        static fn ($v) => explode('|', html_entity_decode($v, ENT_QUOTES | ENT_HTML5))[0],
                        $c[1]
                    );
                    $this->cek('tampilan kelas: jumlah kartu = jumlah kelas', count($urutHtml) === count($d['kelas']));
                    $this->cek('tampilan kelas: kartu urut natural', $urutHtml === array_map('strtolower', array_column($d['kelas'], 'nama')));
                    $this->cek('tampilan kelas: tombol belum hadir per baris', str_contains($html, 'Tandai belum hadir'));
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    private function renderHalaman(string $tampilan): string
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(
            config('App'),
            new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/absensi'),
            null,
            new \CodeIgniter\HTTP\UserAgent()
        );
        $request->setGlobal('get', ['tanggal' => self::TANGGAL, 'shift' => 'pagi', 'tampilan' => $tampilan]);
        \Config\Services::injectMock('request', $request);

        $c = new \App\Controllers\Admin\Absensi();
        $c->initController($request, service('response'), service('logger'));

        return (string) $c->index();
    }

    private function bersihkan(): void
    {
        try {
            AbsensiHarian::batalkan(self::TANGGAL);
            $ids = array_filter($this->piketUji);
            if ($ids !== []) {
                (new JadwalPiketModel())->whereIn('id', $ids)->delete();
            }
            $this->piketUji = [];
        } catch (Throwable $e) {
            CLI::error('Gagal membersihkan data uji: ' . $e->getMessage());
        }
    }
}
