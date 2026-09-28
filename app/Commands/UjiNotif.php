<?php

namespace App\Commands;

use App\Libraries\Fcm;
use App\Libraries\NotifJadwal;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\NotifAturanModel;
use App\Models\NotifLogModel;
use App\Models\NotifPengaturanModel;
use App\Models\NotifPerangkatModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Uji regresi notifikasi jadwal guru: blok masuk kelas, pencocokan aturan,
 * alasan diam (jeda/libur/ujian), susunan pesan, anti dobel, toleransi cron,
 * pembuangan token mati, dan tanda tangan JWT Firebase.
 *
 * Pengirim Firebase DIGANTI pengirim palsu → tidak butuh akun Firebase.
 *     php spark dev:uji-notif --db muslimin_asli
 *
 * Data uji (aturan, perangkat, pengaturan, log, periode ujian) dibuat untuk
 * admin pertama pada Senin 7 Januari 2030 dan dihapus lagi di akhir.
 */
class UjiNotif extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-notif';
    protected $description = 'Uji mesin notifikasi jadwal guru (tanpa Firebase sungguhan).';
    protected $usage       = 'dev:uji-notif [--db nama_database]';
    protected $options     = ['--db' => 'Nama database uji (default: dari .env).'];

    private const TANGGAL = '2030-01-07'; // Senin
    private const DEVICE  = 'uji-notif-device';
    private const TOKEN   = 'uji-notif-token-0123456789';

    private int $lulus = 0;
    private int $gagal = 0;
    private int $adminId = 0;
    private ?array $pengaturanLama = null;
    private ?int $periodeUji = null;

    /** @var list<int> id aturan yang DIBUAT uji ini (hanya ini yang dihapus) */
    private array $aturanUji = [];

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

        // Salinan DB uji (--db) belum tentu sudah dimigrasi: `spark migrate` selalu
        // memakai DB dari .env, jadi migrasikan di sini lewat koneksi yang sama.
        if (! db_connect()->tableExists('notif_log')) {
            CLI::write('Tabel notif belum ada di DB ini — menjalankan migrasi…', 'light_gray');
            \Config\Services::migrations()->latest();
        }

        try {
            $kode = $this->doRun();
        } catch (Throwable $e) {
            CLI::error('ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $kode = EXIT_ERROR;
        }
        $this->bersihkan();

        return $kode;
    }

    private function doRun(): int
    {
        $this->adminId = (int) (db_connect()->table('admins')->select('id')->orderBy('id')->get(1)->getRow('id') ?? 0);
        $hari          = (new HariModel())->byWeekday(1);
        if ($this->adminId === 0 || ! $hari) {
            CLI::error('Butuh minimal 1 admin & hari Senin.');

            return EXIT_ERROR;
        }
        if ((new NotifLogModel())->where('tanggal', self::TANGGAL)->countAllResults() > 0) {
            CLI::error('notif_log sudah berisi tanggal ' . self::TANGGAL . ' — uji dibatalkan.');

            return EXIT_ERROR;
        }
        // Aturan / HP asli akan mengacaukan hasil uji & TIDAK BOLEH disentuh:
        // uji "token mati" membuang HP yang dikirimi, jadi HP klien bisa terhapus.
        if ((new NotifAturanModel())->where('admin_id', $this->adminId)->countAllResults() > 0
            || (new NotifPerangkatModel())->where('device_id !=', self::DEVICE)->countAllResults() > 0) {
            CLI::error('DB ini sudah punya aturan / HP notifikasi asli — jalankan uji di salinan DB (--db muslimin_asli).');

            return EXIT_ERROR;
        }
        $this->pengaturanLama = (new NotifPengaturanModel())->find($this->adminId);
        (new NotifPengaturanModel())->simpan($this->adminId, true, null, true);

        $this->ujiJwt();
        $hariId = (int) $hari['id'];
        $blok   = $this->ujiBlok($hariId);
        if ($blok === []) {
            CLI::error('Tidak ada jadwal Senin — pakai --db muslimin_asli.');

            return EXIT_ERROR;
        }
        $this->ujiAturan($hariId, $blok);
        $this->ujiDiam();
        $this->ujiPesan($blok);
        $this->ujiKirim();

        CLI::newLine();
        CLI::write('RINGKASAN: ' . $this->lulus . ' lulus, ' . $this->gagal . ' gagal', $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // -----------------------------------------------------------------
    private function ujiJwt(): void
    {
        $this->bagian('JWT Firebase (RS256)');

        // Windows/XAMPP: openssl_pkey_new butuh path openssl.cnf eksplisit.
        $opsi = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $kunci = @openssl_pkey_new($opsi);
        foreach (['C:/xampp/php/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if ($kunci === false && is_file($cnf)) {
                $opsi['config'] = $cnf;
                $kunci          = @openssl_pkey_new($opsi);
            }
        }
        if ($kunci === false) {
            CLI::write('  [LEWAT] openssl_pkey_new tidak tersedia di PHP ini (openssl.cnf).', 'light_gray');

            return;
        }
        openssl_pkey_export($kunci, $pem, null, isset($opsi['config']) ? ['config' => $opsi['config']] : []);
        $pub  = openssl_pkey_get_details($kunci)['key'];
        $kred = ['client_email' => 'uji@contoh.iam.gserviceaccount.com', 'private_key' => $pem];
        $jwt  = Fcm::jwt($kred, 1_900_000_000);

        $bagian = explode('.', $jwt);
        $this->cek('JWT terdiri dari 3 bagian', count($bagian) === 3);
        $urai  = static fn ($s) => json_decode((string) base64_decode(strtr($s, '-_', '+/')), true);
        $klaim = $urai($bagian[1] ?? '');
        $this->cek('klaim iss/scope/aud/exp benar', ($klaim['iss'] ?? '') === $kred['client_email']
            && ($klaim['scope'] ?? '') === 'https://www.googleapis.com/auth/firebase.messaging'
            && ($klaim['aud'] ?? '') === 'https://oauth2.googleapis.com/token'
            && ($klaim['exp'] ?? 0) - ($klaim['iat'] ?? 0) === 3600);
        $pad = static fn ($s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
        $this->cek('tanda tangan valid (openssl_verify)', openssl_verify(
            $bagian[0] . '.' . $bagian[1],
            $pad($bagian[2] ?? ''),
            $pub,
            OPENSSL_ALGO_SHA256
        ) === 1);
        $this->cek('Fcm::siap() false tanpa FCM_KEY_PATH yang valid', ! Fcm::siap() || Fcm::pathKunci() !== '');
    }

    /** @return list<array> */
    private function ujiBlok(int $hariId): array
    {
        $this->bagian('Blok masuk kelas (Senin)');

        NotifJadwal::lupakan();
        $blok = NotifJadwal::blokHari($hariId);
        $sesi = (new JadwalModel())->sessionsForHari($hariId);

        $jumlahJam = array_sum(array_map(static fn ($b) => $b['akhir'] - $b['awal'] + 1, $blok));
        $this->cek('setiap sesi masuk tepat satu blok', $jumlahJam === count($sesi), $jumlahJam . ' vs ' . count($sesi));
        $this->cek('jumlah jam_ids = panjang blok', array_reduce($blok, static fn ($ok, $b) => $ok && count($b['jam_ids']) === $b['akhir'] - $b['awal'] + 1, true));
        $this->cek('ada blok gabungan (jam ke x-y)', array_filter($blok, static fn ($b) => $b['akhir'] > $b['awal']) !== []);
        $this->cek('nama tanpa gelar (tak ada koma)', array_filter($blok, static fn ($b) => str_contains($b['nama'], ',')) === []);

        // Tak boleh ada dua blok bersambung yang seharusnya satu.
        $sambung = false;
        foreach ($blok as $a) {
            foreach ($blok as $b) {
                $sambung = $sambung || ($a !== $b && $a['oid'] === $b['oid'] && $a['kelas_id'] === $b['kelas_id']
                    && $a['shift'] === $b['shift'] && $a['akhir'] + 1 === $b['awal']);
            }
        }
        $this->cek('tidak ada blok bersambung yang terpisah', ! $sambung);

        return $blok;
    }

    private function ujiAturan(int $hariId, array $blok): void
    {
        $this->bagian('Pencocokan aturan');

        $r = NotifJadwal::rencana($this->adminId, self::TANGGAL);
        $this->cek('tanpa aturan → diam "Belum ada aturan"', str_contains((string) $r['diam'], 'Belum ada aturan'));

        // Guru A = pemilik blok pertama; jurusan J = jurusan blok itu.
        $a     = $blok[0]['oid'];
        $j     = $blok[0]['jurusan_id'];
        $model = new NotifAturanModel();
        $this->aturanUji[] = (int) $model->insert(['admin_id' => $this->adminId, 'nama' => 'uji A', 'hari' => json_encode([$hariId]),
            'guru' => json_encode([$a]), 'jurusan' => null, 'menit_sebelum' => 5, 'aktif' => 1], true);

        NotifJadwal::lupakan();
        $item = $this->itemRencana();
        $this->cek('aturan guru A: hanya blok milik A', $item !== [] && array_filter($item, static fn ($i) => $i['oid'] !== $a) === []);
        $this->cek('aturan guru A: semua blok A tercakup', count($item) === count(array_filter($blok, static fn ($b) => $b['oid'] === $a)));
        $this->cek('menit 5 → kirim = jam masuk − 5', array_filter($item, static fn ($i) => $i['menit'] !== 5) === []);

        // Aturan kedua: semua guru jurusan J, 10 menit → blok A di jurusan J memakai 10.
        $this->aturanUji[] = (int) $model->insert(['admin_id' => $this->adminId, 'nama' => 'uji J', 'hari' => json_encode([$hariId]),
            'guru' => json_encode([]), 'jurusan' => json_encode([$j]), 'menit_sebelum' => 10, 'aktif' => 1], true);
        $item = $this->itemRencana();
        $this->cek('aturan jurusan: semua blok jurusan J ikut', count(array_filter($item, static fn ($i) => $i['jurusan_id'] === $j))
            === count(array_filter($blok, static fn ($b) => $b['jurusan_id'] === $j)));
        $this->cek('cocok 2 aturan → menit terbesar (10)', array_filter($item, static fn ($i) => $i['oid'] === $a && $i['jurusan_id'] === $j && $i['menit'] !== 10) === []);

        $r   = NotifJadwal::rencana($this->adminId, self::TANGGAL);
        $ok  = true;
        $urut = true;
        foreach ($r['grup'] as $g) {
            foreach ($g['item'] as $i) {
                $ok = $ok && date('H:i', strtotime(self::TANGGAL . ' ' . $i['mulai']) - $i['menit'] * 60) === $g['slot'];
            }
            $kunci = array_map(static fn ($i) => [$i['urut'], $i['awal']], $g['item']);
            $s     = $kunci;
            sort($s);
            $urut = $urut && $kunci === $s;
        }
        $this->cek('slot kelompok = jam masuk − menit', $ok);
        $this->cek('isi kelompok urut kelas natural', $urut);
        $this->cek('kelompok urut waktu', array_column($r['grup'], 'slot') === array_values(array_unique(array_column($r['grup'], 'slot'))));

        // Aturan non-aktif diabaikan; hari lain tak cocok.
        $model->whereIn('id', $this->aturanUji)->set('aktif', 0)->update();
        $this->cek('semua aturan nonaktif → diam', NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'] !== null);
        $model->whereIn('id', $this->aturanUji)->set('aktif', 1)->update();
        $this->cek('aturan Senin tak berlaku di Selasa', $this->itemRencana('2030-01-08') === []);
    }

    private function itemRencana(string $tanggal = self::TANGGAL): array
    {
        $item = [];
        foreach (NotifJadwal::rencana($this->adminId, $tanggal)['grup'] as $g) {
            array_push($item, ...$g['item']);
        }

        return $item;
    }

    private function ujiDiam(): void
    {
        $this->bagian('Alasan diam');

        $p = new NotifPengaturanModel();
        $p->simpan($this->adminId, false, null, true);
        $this->cek('notif dimatikan → diam', str_contains((string) NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'], 'dimatikan'));
        $p->simpan($this->adminId, true, self::TANGGAL, true);
        $this->cek('jeda sampai hari itu → diam', str_contains((string) NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'], 'Dijeda'));
        $p->simpan($this->adminId, true, '2030-01-06', true);
        $this->cek('jeda sudah lewat → kirim', NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'] === null);
        $p->simpan($this->adminId, true, null, true);
        $this->cek('Minggu (bukan hari KBM) → diam', str_contains((string) NotifJadwal::rencana($this->adminId, '2030-01-06')['diam'], 'libur'));

        // Periode ujian uji (tahun ajaran fiktif agar tak bentrok UNIQUE jenis+tahun).
        $db = db_connect();
        $db->table('ujian_periode')->insert([
            'jenis' => 'ASAT', 'tahun_ajaran' => '2099/2100', 'semester' => 'Genap',
            'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => '2030-01-09', 'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->periodeUji = (int) $db->insertID();
        $this->cek('hari ujian → diam', str_contains((string) NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'], 'ujian'));
        $p->simpan($this->adminId, true, null, false);
        $this->cek('diam saat ujian dimatikan → kirim', NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'] === null);
        $db->table('ujian_periode')->where('id', $this->periodeUji)->delete();
        $this->periodeUji = null;
        $p->simpan($this->adminId, true, null, true);
        $this->cek('periode dihapus → kirim lagi', NotifJadwal::rencana($this->adminId, self::TANGGAL)['diam'] === null);
    }

    private function ujiPesan(array $blok): void
    {
        $this->bagian('Susunan pesan');

        $b    = $blok[0];
        $satu = NotifJadwal::susunPesan([$b]);
        $this->cek('1 guru: judul "Nama masuk Kelas"', $satu['judul'] === $b['nama'] . ' masuk ' . $b['kelas'], $satu['judul']);
        $this->cek('1 guru: isi "Jam ke … · HH:MM · KBM …"', str_starts_with($satu['isi'], 'Jam ke ' . $b['awal']) && str_contains($satu['isi'], 'KBM ' . $b['shift']), $satu['isi']);

        $izin = NotifJadwal::susunPesan([$b], [$b['kelas_id'] . '-' . $b['jam_ids'][0] => ['status' => 'izin']]);
        $this->cek('guru izin → judul ⚠ & isi IZIN', str_starts_with($izin['judul'], '⚠ ') && str_contains($izin['isi'], 'IZIN') && $izin['kosong'] === 1);
        $belum = NotifJadwal::susunPesan([$b], [], [$b['shift'] => [$b['oid']]]);
        $this->cek('guru belum hadir → ⚠ BELUM HADIR', str_contains($belum['isi'], 'BELUM HADIR'));

        $dua  = array_slice(array_values(array_filter($blok, static fn ($x) => $x['mulai'] === $b['mulai'] && $x['shift'] === $b['shift'])), 0, 3);
        if (count($dua) >= 2) {
            $multi = NotifJadwal::susunPesan($dua);
            $this->cek('banyak guru: 1 notif, judul "Jadwal masuk … · n guru"', str_contains($multi['judul'], 'Jadwal masuk ' . $b['mulai']) && str_ends_with($multi['judul'], count($dua) . ' guru'), $multi['judul']);
            $this->cek('banyak guru: satu baris per guru', count(explode("\n", $multi['isi'])) === count($dua));
        }
        $this->cek('tanpa nama mapel di isi', ! str_contains($satu['isi'] . $satu['judul'], 'mapel'));
    }

    private function ujiKirim(): void
    {
        $this->bagian('Kirim (pengirim palsu), anti dobel, toleransi, token mati');

        (new NotifPerangkatModel())->daftar($this->adminId, self::DEVICE, self::TOKEN, 'HP Uji');
        $terkirim = [];
        NotifJadwal::$pengirim = static function (string $t, string $j, string $i, array $d) use (&$terkirim) {
            $terkirim[] = ['token' => $t, 'judul' => $j, 'data' => $d];

            return ['ok' => true, 'kode' => null, 'pesan' => null, 'token_mati' => false];
        };

        $r  = NotifJadwal::rencana($this->adminId, self::TANGGAL);
        $g0 = $r['grup'][0];
        $h  = NotifJadwal::jalankan($g0['kirim_ts']);
        $this->cek('tepat di waktu kirim → terkirim 1', $h['kelompok'] >= 1 && $h['berhasil'] >= 1 && ($terkirim[0]['token'] ?? '') === self::TOKEN, json_encode($h));
        $this->cek('data notif membawa tanggal & shift', ($terkirim[0]['data']['tanggal'] ?? '') === self::TANGGAL && isset($terkirim[0]['data']['shift']));
        $log = (new NotifLogModel())->where('kunci', 'jadwal:' . $this->adminId . ':' . self::TANGGAL . ':' . $g0['slot'])->first();
        $this->cek('riwayat tercatat berhasil=1', $log && (int) $log['berhasil'] === 1);

        $n = count($terkirim);
        NotifJadwal::jalankan($g0['kirim_ts'] + 60);
        NotifJadwal::jalankan($g0['kirim_ts'] + 9 * 60);
        $baru = array_filter(array_slice($terkirim, $n), static fn ($x) => $x['data']['slot'] === $g0['slot']);
        $this->cek('putaran berikutnya tak mengirim ulang slot yang sama', $baru === []);

        // Slot yang terlewat > toleransi tidak dikirim.
        $akhir = end($r['grup']);
        $n     = count($terkirim);
        if ($akhir['slot'] !== $g0['slot']) {
            NotifJadwal::jalankan($akhir['kirim_ts'] + (NotifJadwal::TOLERANSI_MENIT + 1) * 60);
            $baru = array_filter(array_slice($terkirim, $n), static fn ($x) => $x['data']['slot'] === $akhir['slot']);
            $this->cek('terlambat > toleransi → tidak dikirim', $baru === []);
        }

        $h = NotifJadwal::jalankan(strtotime(self::TANGGAL . ' 03:00'));
        $this->cek('jam tanpa jadwal → tidak ada kiriman', $h['kelompok'] === 0);

        // Semua HP gagal sesaat (bukan token mati) → menit berikutnya dicoba lagi.
        if ($akhir['slot'] !== $g0['slot']) {
            $kunci  = 'jadwal:' . $this->adminId . ':' . self::TANGGAL . ':' . $akhir['slot'];
            $sukses = NotifJadwal::$pengirim;
            NotifJadwal::$pengirim = static fn () => ['ok' => false, 'kode' => 'UNAVAILABLE', 'pesan' => 'uji putus', 'token_mati' => false];
            NotifJadwal::jalankan($akhir['kirim_ts']);
            $this->cek('semua gagal → kunci dilepas (bisa dicoba ulang)', ! (new NotifLogModel())->sudahAda($kunci));
            NotifJadwal::$pengirim = $sukses;
            $n = count($terkirim);
            NotifJadwal::jalankan($akhir['kirim_ts'] + 60);
            $ulang = array_filter(array_slice($terkirim, $n), static fn ($x) => $x['data']['slot'] === $akhir['slot']);
            $this->cek('menit berikutnya dikirim ulang & berhasil', count($ulang) === 1 && (new NotifLogModel())->sudahAda($kunci));
            $this->cek('percobaan gagal tetap ada di riwayat', (new NotifLogModel())->like('kunci', 'gagal:' . $kunci, 'after')->countAllResults() === 1);
        }

        // Klaim kunci dua kali.
        $log  = new NotifLogModel();
        $row  = ['admin_id' => $this->adminId, 'jenis' => 'uji', 'kunci' => 'uji:dobel:' . self::TANGGAL, 'tanggal' => self::TANGGAL, 'judul' => 'x', 'isi' => 'x'];
        $this->cek('klaim pertama berhasil, kedua ditolak', $log->klaim($row) !== null && $log->klaim($row) === null);

        // Token ditolak Firebase → perangkat dibuang.
        NotifJadwal::$pengirim = static fn () => ['ok' => false, 'kode' => 'UNREGISTERED', 'pesan' => 'uji', 'token_mati' => true];
        $g1 = $r['grup'][1] ?? null;
        if ($g1 !== null) {
            NotifJadwal::jalankan($g1['kirim_ts']);
            $this->cek('token UNREGISTERED → perangkat dihapus', (new NotifPerangkatModel())->milik($this->adminId, self::DEVICE) === null);
        }
        NotifJadwal::$pengirim = null;

        // Pendaftaran ulang: HP sama pindah akun lain tidak menyisakan baris lama.
        $p = new NotifPerangkatModel();
        $p->daftar($this->adminId, self::DEVICE, self::TOKEN, 'HP Uji');
        $p->daftar($this->adminId, self::DEVICE, self::TOKEN . '-baru', 'HP Uji');
        $this->cek('token HP diperbarui, tetap satu baris', $p->where('device_id', self::DEVICE)->countAllResults() === 1
            && ($p->milik($this->adminId, self::DEVICE)['token'] ?? '') === self::TOKEN . '-baru');
    }

    private function bersihkan(): void
    {
        try {
            NotifJadwal::$pengirim = null;
            $db = db_connect();
            $db->table('notif_log')->where('admin_id', $this->adminId)->where('tanggal >=', '2030-01-01')->where('tanggal <=', '2030-01-31')->delete();
            if ($this->aturanUji !== []) {
                $db->table('notif_aturan')->whereIn('id', $this->aturanUji)->delete();
            }
            $db->table('notif_perangkat')->where('device_id', self::DEVICE)->delete();
            if ($this->periodeUji !== null) {
                $db->table('ujian_periode')->where('id', $this->periodeUji)->delete();
            }
            if ($this->adminId > 0) {
                $db->table('notif_pengaturan')->where('admin_id', $this->adminId)->delete();
                if ($this->pengaturanLama !== null) {
                    $db->table('notif_pengaturan')->insert($this->pengaturanLama);
                }
            }
        } catch (Throwable $e) {
            CLI::error('Gagal membersihkan data uji: ' . $e->getMessage());
        }
    }
}
