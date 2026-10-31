<?php

namespace App\Commands;

use App\Libraries\LayananSiswa;
use App\Libraries\LayananToken;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\SuratJadwal;
use App\Libraries\SuratJenis;
use App\Models\PklPengaturanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji API publik formulir surat Layanan Siswa (GET/POST publik/layanan/surat/{kunci}, docs/API-LAYANAN-SISWA.md): izin ujian, izin
 * TKA gelombang per siswa, pernyataan orang tua, penarikan. Hasilnya dibandingkan dengan fungsi yang dipakai halaman web
 * (LayananSiswa::form*), jadi aplikasi dan web tidak bisa berbeda diam-diam.
 *
 * Data uji bertanda "ZZUJI LS" (nama), NIS "ZZUJILS…", IP "10.9.7.9"; dibuat & dihapus sendiri. Pengaturan PKL dipulihkan di akhir.
 * UJI_FIXTURE=<folder> menulis respons nyata sebagai fixture Flutter.
 *
 * Jalankan:  php spark dev:uji-layanan-surat
 */
class UjiLayananSurat extends BaseCommand
{
    use UjiPeranAman;

    protected $group       = 'dev';
    protected $name        = 'dev:uji-layanan-surat';
    protected $description = 'Uji API formulir surat Layanan Siswa: izin ujian, izin TKA, pernyataan orang tua, penarikan.';

    private const NIS  = 'ZZUJILS';
    private const IP   = '10.9.7.9';
    private const OLEH = 'ZZUJI LS';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    private int $kelasId = 0;

    /** @var array<string, array<string, mixed>> */
    private array $fx = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' ? '  → ' . $detail : ''), $ok ? 'green' : 'red');
    }

    public function run(array $params)
    {
        try {
            $this->db = db_connect();
            $this->bersihkan();
            $this->simpanPeranAsli();
            $this->pengaturanAsli = (new PklPengaturanModel())->ambil();
            unset($this->pengaturanAsli['id']);
            $this->setelPeranBawaan();
            $this->db->table('pkl_pengaturan')->where('id', 1)->update(['tingkat' => 'XI,XII', 'hak_peran' => null, 'surat_perlu_acc' => null, 'selesai_paling_akhir' => null, 'durasi_maks_hari' => 0]);
            $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->where('tingkat', 'XII')->orderBy('id')->get()->getRowArray();
            if ($k === null) {
                $this->cek('ada kelas XII sebagai bahan uji', false);
            } else {
                $this->kelasId = (int) $k['id'];
                $this->ujiGerbang();
                $this->ujiIzin();
                $this->ujiTka();
                $this->ujiPernyataan();
                $this->ujiPenarikan();
                $this->tulisFixture();
            }
        } catch (\Throwable $e) {
            $this->cek('FATAL ' . get_class($e), false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function ujiGerbang(): void
    {
        CLI::write('== Gerbang ==', 'yellow');
        $s = $this->buatSiswa(1);
        $t = $this->token($s);
        [$k] = $this->api('surat', [], '', 'izin_ujian');
        $this->cek('tanpa token → 401', $k === 401, (string) $k);
        [$k, $j] = $this->api('surat', [], $t, 'admin');
        $this->cek('kunci tak dikenal → 404', $k === 404, (string) $k);
        [$k] = $this->api('surat', [], $t, 'perpanjangan');
        $this->cek('perpanjangan tetap di alamatnya sendiri → 404 di sini', $k === 404, (string) $k);

        foreach (['izin_ujian', 'penarikan'] as $kunci) {
            [$k, $j] = $this->api('surat', [], $t, $kunci);
            $this->cek('siswa tanpa PKL, GET ' . $kunci . ' → 409 tidak_boleh, kalimat sama dengan web ("… belum bisa diajukan: …")', $k === 409 && ($j['data']['kode'] ?? '') === 'tidak_boleh'
                && str_contains((string) $j['message'], LayananSiswa::SURAT[$kunci][0] . ' belum bisa diajukan: Khusus siswa'), (string) ($j['message'] ?? ''));
            [$k, $j] = $this->api('ajukanSurat', ['alasan_pilih' => '0'], $t, $kunci);
            $this->cek('siswa tanpa PKL, POST ' . $kunci . ' → 409 juga', $k === 409 && ($j['data']['kode'] ?? '') === 'tidak_boleh', (string) $k);
        }
        $this->fx['layanan_surat_409_tidak_boleh'] = $j;

        [$k, $j] = $this->api('saya', [], $t);
        $api = array_column($j['data']['surat'] ?? [], 'api', 'kunci');
        $this->cek('saya → kelima surat api=true; token_baru ikut', $k === 200 && array_values(array_unique($api)) === [true] && count($api) === 5 && LayananToken::baca((string) ($j['data']['token_baru'] ?? '')) === $s, json_encode($api));
    }

    private function ujiIzin(): void
    {
        CLI::write('== Surat Izin Ujian ==', 'yellow');
        $s1  = $this->buatSiswa(11);
        $s2  = $this->buatSiswa(12);
        $aj  = $this->buatAjuan($s1, 'PT ' . self::OLEH . ' Ujian', [$s2]);
        $hr  = static fn (int $n): string => date('Y-m-d', strtotime('+' . $n . ' days'));
        $jd  = new SuratJadwal($this->db);
        $r   = $jd->simpan(SuratJenis::ASTS, ['kegiatan' => 'ASTS', 'semester' => 'Ganjil', 'tahun_pelajaran' => '2031/2032', 'tgl_mulai' => $hr(60), 'tgl_selesai' => $hr(62), 'tempat' => 'Aula ' . self::OLEH, 'buka' => '1'], null, $this->ctx());
        $jid = (int) ($r['id'] ?? 0);
        $this->cek('bahan: ajuan disetujui + jadwal dibuka', $aj > 0 && $jid > 0, json_encode($r));
        $t = $this->token($s1);

        [$k, $j] = $this->api('surat', [], $t, 'izin_ujian');
        $baris   = array_values(array_filter($j['data']['jadwal'] ?? [], static fn (array $x): bool => $x['id'] === $jid))[0] ?? [];
        $this->cek('GET → judul web, jadwal {label, hari_tanggal, tempat, sudah=null}, perusahaan, teman nama', $k === 200 && ($j['data']['judul'] ?? '') === 'Surat Izin Ujian'
            && ($baris['tempat'] ?? '') === 'Aula ' . self::OLEH && array_key_exists('sudah', $baris) && $baris['sudah'] === null && ! isset($baris['jml_permintaan'])
            && str_contains((string) ($j['data']['perusahaan']['nama'] ?? ''), self::OLEH) && ($j['data']['teman'] ?? []) === [self::OLEH . ' 12'], json_encode($baris));
        $this->cek('GET → isi sama dengan fungsi halaman web (LayananSiswa::formIzin)', $this->samaIzin($s1, $j['data'] ?? []));
        $this->cek('GET → token_baru sah', LayananToken::baca((string) ($j['data']['token_baru'] ?? '')) === $s1);
        $this->fx['layanan_surat_izin_form'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => '0'], $t, 'izin_ujian');
        $this->cek('POST tanpa jadwal → 409 "Pilih salah satu jadwal yang tersedia."', $k === 409 && $j['message'] === 'Pilih salah satu jadwal yang tersedia.', (string) ($j['message'] ?? ''));
        $this->fx['layanan_surat_izin_409'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jid], $t, 'izin_ujian');
        $this->cek('POST → 201 kode baru, surat memuat 2 siswa, permintaan tercatat', $k === 201 && ($j['data']['kode'] ?? '') === 'baru' && $this->jumlah('surat_sekolah_siswa', ['surat_id' => (int) ($j['data']['surat_id'] ?? 0)]) === 2
            && $this->jumlah('surat_permintaan', ['siswa_id' => $s1, 'jadwal_id' => $jid]) === 1 && str_starts_with((string) ($j['data']['kode_surat'] ?? ''), 'SR'), json_encode($j['data'] ?? null));
        $this->fx['layanan_surat_izin_kirim'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jid], $t, 'izin_ujian');
        $this->cek('POST kedua → 200 kode sudah', $k === 200 && ($j['data']['kode'] ?? '') === 'sudah', (string) $k);

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jid], $this->token($s2), 'izin_ujian');
        $this->cek('teman meminta jadwal sama → 201 kode ikut (tetap satu surat)', $k === 201 && ($j['data']['kode'] ?? '') === 'ikut', json_encode($j['data'] ?? null));

        [$k, $j] = $this->api('surat', [], $t, 'izin_ujian');
        $baris   = array_values(array_filter($j['data']['jadwal'] ?? [], static fn (array $x): bool => $x['id'] === $jid))[0] ?? [];
        $this->cek('GET sesudah kirim → jadwal "sudah" berisi kode surat', str_starts_with((string) ($baris['sudah']['kode'] ?? ''), 'SR'), json_encode($baris['sudah'] ?? null));
        $this->fx['layanan_surat_izin_sudah'] = $j;
    }

    private function samaIzin(int $s, array $d): bool
    {
        $lib = new LayananSiswa();
        $p   = (new PklPengaturanModel())->ambil();
        $pf  = $lib->profil($s);
        $f   = $lib->formIzin($pf, 'izin_ujian', $lib->kelayakan($pf, $p));

        return array_column($f['jadwal'], 'id') === array_column($d['jadwal'] ?? [], 'id') && $f['perusahaan'] === ($d['perusahaan'] ?? null) && $f['teman'] === ($d['teman'] ?? null);
    }

    private function ujiTka(): void
    {
        CLI::write('== Surat Izin TKA ==', 'yellow');
        $s1  = $this->buatSiswa(21);
        $s2  = $this->buatSiswa(22);
        $s3  = $this->buatSiswa(23);
        $this->buatAjuan($s1, 'PT ' . self::OLEH . ' Tka', [$s2, $s3]);
        $hr  = static fn (int $n): string => date('Y-m-d', strtotime('+' . $n . ' days'));
        $jd  = new SuratJadwal($this->db);
        $jA  = (int) ($jd->simpan(SuratJenis::TKA, ['sesi' => 'Gelombang ' . self::NIS . ' A', 'tgl_mulai' => $hr(70), 'tgl_selesai' => $hr(71), 'tempat' => '', 'buka' => '1'], null, $this->ctx())['id'] ?? 0);
        $jB  = (int) ($jd->simpan(SuratJenis::TKA, ['sesi' => 'Gelombang ' . self::NIS . ' B', 'tgl_mulai' => $hr(73), 'tgl_selesai' => $hr(74), 'tempat' => '', 'buka' => '1'], null, $this->ctx())['id'] ?? 0);
        $t   = $this->token($s1);

        [$k, $j] = $this->api('surat', [], $t, 'izin_tka');
        $this->cek('GET → gelombang, saya kosong, 2 teman belum tercantum, tidak terkunci', $k === 200 && ($j['data']['saya']['penempatan'] ?? null) === [] && ($j['data']['saya']['terkunci'] ?? null) === false
            && count($j['data']['teman'] ?? []) === 2 && ($j['data']['teman'][0]['terkunci'] ?? null) === false && in_array($jA, array_column($j['data']['jadwal'] ?? [], 'id'), true), (string) $k);
        $this->fx['layanan_surat_tka_form'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => '0'], $t, 'izin_tka');
        $this->cek('POST tanpa gelombang → 409 "Pilih gelombang TKA-mu."', $k === 409 && $j['message'] === 'Pilih gelombang TKA-mu.', (string) ($j['message'] ?? ''));

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jA, 'teman' => [$s2 => (string) $jA, $s3 => (string) $jB]], $t, 'izin_tka');
        $this->cek('POST diri A + teman A & B → 201, kalimat merinci per gelombang', $k === 201 && ($j['data']['kode'] ?? '') === 'baru' && str_contains((string) $j['message'], 'Satu surat untuk tiap gelombang'), (string) ($j['message'] ?? ''));
        $this->fx['layanan_surat_tka_kirim'] = $j;

        [$k, $j] = $this->api('surat', [], $t, 'izin_tka');
        $saya    = $j['data']['saya'] ?? [];
        $teman   = array_column($j['data']['teman'] ?? [], null, 'id');
        $this->cek('GET sesudah kirim → penempatan diri "dipilih sendiri", teman "diisikan oleh", isi surat tidak dikirim', ($saya['jadwal_id'] ?? 0) === $jA && str_contains((string) ($saya['penempatan'][0]['teks'] ?? ''), '· dipilih sendiri')
            && str_contains((string) ($teman[$s3]['penempatan'][0]['teks'] ?? ''), 'diisikan oleh ' . self::OLEH . ' 21') && ($teman[$s3]['jadwal_id'] ?? 0) === $jB && ! isset($saya['penempatan'][0]['isi']), json_encode($saya));
        $this->fx['layanan_surat_tka_sesudah'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jB], $this->token($s2), 'izin_tka');
        $this->cek('teman pindah sendiri ke B → 201/200', in_array($k, [200, 201], true), (string) $k . ' ' . ($j['message'] ?? ''));
        [$k, $j] = $this->api('surat', [], $t, 'izin_tka');
        $teman   = array_column($j['data']['teman'] ?? [], null, 'id');
        $this->cek('teman yang sudah memilih sendiri → terkunci + sendiri', ($teman[$s2]['terkunci'] ?? false) === true && ($teman[$s2]['sendiri'] ?? false) === true, json_encode($teman[$s2] ?? null));
        [$k, $j] = $this->api('ajukanSurat', ['jadwal_id' => (string) $jA, 'teman' => [$s2 => (string) $jA]], $t, 'izin_tka');
        $this->cek('mengisikan teman yang terkunci → tidak diubah, disebut di pesan', str_contains((string) $j['message'], 'sudah memilih sendiri'), (string) ($j['message'] ?? ''));

        // Aplikasi mengirim JSON: angka (bukan teks) dan kunci teman berupa teks.
        [$k, $j] = $this->api('ajukanSurat', [], $t, 'izin_tka', json: ['jadwal_id' => $jA, 'teman' => [(string) $s3 => $jA]]);
        [, $g]   = $this->api('surat', [], $t, 'izin_tka');
        $teman   = array_column($g['data']['teman'] ?? [], null, 'id');
        $this->cek('kiriman JSON seperti aplikasi → teman yang diisikan pindah gelombang', in_array($k, [200, 201], true) && ($teman[$s3]['jadwal_id'] ?? 0) === $jA, (string) $k . ' ' . ($j['message'] ?? ''));
    }

    private function ujiPernyataan(): void
    {
        CLI::write('== Surat Pernyataan Orang Tua ==', 'yellow');
        $s = $this->buatSiswa(31);
        $this->db->table('siswa')->where('id', $s)->update(['nama_ayah' => 'AYAH ' . self::OLEH, 'pekerjaan_ayah' => 'Karyawan', 'ortu_alamat' => 'Jl. Uji No. 2', 'ortu_telepon' => '081200000031']);
        $t = $this->token($s);

        [$k, $j] = $this->api('surat', [], $t, 'pernyataan');
        $this->cek('GET → data orang tua dari data sekolah, aktif null, batas kolom', $k === 200 && str_contains((string) ($j['data']['ortu']['nama'] ?? ''), self::OLEH) && array_key_exists('aktif', $j['data']) && $j['data']['aktif'] === null && ($j['data']['dicetak'] ?? null) === false
            && ($j['data']['maks']['alamat'] ?? 0) === 300, json_encode($j['data']['ortu'] ?? null));
        $this->fx['layanan_surat_pernyataan_form'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['nama' => '', 'alamat' => '', 'hp' => '12', 'hp_anak' => '9'], $t, 'pernyataan');
        $g = $j['data']['errors'] ?? [];
        $this->cek('POST salah → 422 per kolom: nama, alamat, hp, hp_anak', $k === 422 && isset($g['nama'], $g['alamat'], $g['hp'], $g['hp_anak']) && $j['message'] === 'Periksa lagi isianmu.', json_encode($g));
        $this->fx['layanan_surat_pernyataan_422'] = $j;

        $isi = ['nama' => 'Ibu ' . self::OLEH, 'pekerjaan' => 'Guru', 'alamat' => 'Jl. Uji No. 3', 'hp' => '081200000032', 'hp_anak' => '081300000031'];
        [$k, $j] = $this->api('ajukanSurat', $isi, $t, 'pernyataan');
        $this->cek('POST → 201 kode baru, kalimat materai', $k === 201 && ($j['data']['kode'] ?? '') === 'baru' && str_contains((string) $j['message'], 'materai Rp10.000'), json_encode($j['data'] ?? null));
        $this->fx['layanan_surat_pernyataan_kirim'] = $j;
        $suratId = (int) ($j['data']['surat_id'] ?? 0);

        [$k, $j] = $this->api('surat', [], $t, 'pernyataan');
        $this->cek('GET sesudah kirim → isian terakhir terisi, aktif berkode, dicetak=false', ($j['data']['ortu']['nama'] ?? '') === 'Ibu ' . self::OLEH && ($j['data']['hp_anak'] ?? '') === '081300000031' && isset($j['data']['aktif']['kode']) && ! isset($j['data']['aktif']['isian']), json_encode($j['data']['aktif'] ?? null));
        $this->fx['layanan_surat_pernyataan_aktif'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['nama' => 'Bapak ' . self::OLEH] + $isi, $t, 'pernyataan');
        $this->cek('POST perbaikan → 200 kode ubah, "sudah diperbarui"', $k === 200 && ($j['data']['kode'] ?? '') === 'ubah' && str_contains((string) $j['message'], 'sudah diperbarui'), (string) ($j['message'] ?? ''));
        $this->fx['layanan_surat_pernyataan_ubah'] = $j;

        $this->db->table('surat_sekolah')->where('id', $suratId)->update(['cetak_ke' => 1]);
        [$k, $j] = $this->api('surat', [], $t, 'pernyataan');
        $this->cek('sudah dicetak → GET dicetak=true', ($j['data']['dicetak'] ?? false) === true, (string) $k);
        $this->fx['layanan_surat_pernyataan_dicetak'] = $j;
        [$k, $j] = $this->api('ajukanSurat', $isi, $t, 'pernyataan');
        $this->cek('sudah dicetak → POST 409 "minta operator"', $k === 409 && str_contains((string) $j['message'], 'sudah dicetak sekolah'), (string) ($j['message'] ?? ''));
    }

    private function ujiPenarikan(): void
    {
        CLI::write('== Surat Penarikan PKL ==', 'yellow');
        $s = $this->buatSiswa(41);
        $this->buatAjuan($s, 'PT ' . self::OLEH . ' Tarik', []);
        $t = $this->token($s);

        [$k, $j] = $this->api('surat', [], $t, 'penarikan');
        $this->cek('GET → perusahaan, aktif null, 4 alasan dari web', $k === 200 && str_contains((string) ($j['data']['perusahaan'] ?? ''), self::OLEH) && array_key_exists('aktif', $j['data']) && $j['data']['aktif'] === null
            && array_column($j['data']['alasan'] ?? [], 'label', 'kode') === LayananSiswa::ALASAN_PENARIKAN, json_encode($j['data']['alasan'] ?? null));
        $this->fx['layanan_surat_penarikan_form'] = $j;

        [$k, $j] = $this->api('ajukanSurat', ['alasan_pilih' => 'lain', 'alasan_lain' => '', 'tgl_efektif' => date('Y-m-d')], $t, 'penarikan');
        $this->cek('alasan lain kosong → 422 alasan_lain', $k === 422 && isset($j['data']['errors']['alasan_lain']), json_encode($j['data']['errors'] ?? null));
        $this->fx['layanan_surat_penarikan_422'] = $j;
        [$k, $j] = $this->api('ajukanSurat', ['alasan_pilih' => '', 'tgl_efektif' => ''], $t, 'penarikan');
        $this->cek('alasan & tanggal kosong → 422 alasan_pilih + tgl_efektif', $k === 422 && isset($j['data']['errors']['alasan_pilih'], $j['data']['errors']['tgl_efektif']), json_encode($j['data']['errors'] ?? null));

        [$k, $j] = $this->api('ajukanSurat', ['alasan_pilih' => '4', 'tgl_efektif' => date('Y-m-d')], $t, 'penarikan');
        $this->cek('POST → 201 kode baru, menunggu ACC', $k === 201 && ($j['data']['kode'] ?? '') === 'baru' && str_contains((string) $j['message'], 'menunggu ACC'), (string) ($j['message'] ?? ''));
        $this->fx['layanan_surat_penarikan_kirim'] = $j;

        [$k, $j] = $this->api('surat', [], $t, 'penarikan');
        $this->cek('GET sesudah kirim → aktif berkode', isset($j['data']['aktif']['kode']) && ! isset($j['data']['aktif']['isian']), json_encode($j['data']['aktif'] ?? null));
        $this->fx['layanan_surat_penarikan_aktif'] = $j;
        [$k, $j] = $this->api('ajukanSurat', ['alasan_pilih' => '0', 'tgl_efektif' => date('Y-m-d')], $t, 'penarikan');
        $this->cek('POST kedua → 200 kode sudah', $k === 200 && ($j['data']['kode'] ?? '') === 'sudah', (string) $k);

        [$k, $j] = $this->api('saya', [], $t);
        $this->cek('saya → "Permintaan suratku" memuat Surat Penarikan "Menunggu ACC"', in_array('Menunggu ACC', array_column(array_filter($j['data']['permintaan'] ?? [], static fn (array $x): bool => $x['jenis'] === SuratJenis::PENARIKAN), 'status_teks'), true), (string) $k);
        $this->fx['layanan_saya_lengkap'] = $j;
    }

    private function tulisFixture(): void
    {
        $dir = (string) getenv('UJI_FIXTURE');
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }
        foreach ($this->fx as $nama => $isi) {
            // Token uji tidak disimpan di berkas fixture.
            if (isset($isi['data']['token_baru'])) {
                $isi['data']['token_baru'] = 'TOKEN_BARU_CONTOH';
            }
            file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
    }

    // ----------------------------------------------------------------- pembantu

    private function token(int $siswaId): string
    {
        return (string) (LayananToken::buat($siswaId)['token'] ?? '');
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $metode, array $post, string $token, string $kunci = '', ?array $json = null): array
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/publik/layanan'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($post !== [] || $json !== null ? 'POST' : 'GET');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', []);
        $request->setGlobal('post', $post);
        if ($json !== null) {
            $request->setBody((string) json_encode($json));
            $request->setHeader('Content-Type', 'application/json');
        }
        if ($token !== '') {
            $request->setHeader(LayananToken::HEADER, $token);
        }
        $c = new \App\Controllers\Api\Layanan();
        $c->initController($request, service('response'), service('logger'));
        $r = $kunci === '' ? $c->{$metode}() : $c->{$metode}($kunci);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    private function buatSiswa(int $n): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('siswa')->insert([
            'nis' => self::NIS . $n, 'nama' => self::OLEH . ' ' . $n, 'jenis_kelamin' => 'P', 'kelas_id' => $this->kelasId, 'no_hp' => '08130000000' . $n,
            'status' => 'aktif', 'tanggal_lahir' => '2009-05-17', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<int> $teman */
    private function buatAjuan(int $siswaId, string $perusahaan, array $teman): int
    {
        $data = [
            'perusahaan_nama' => $perusahaan, 'perusahaan_norm' => PklForm::normPerusahaan($perusahaan),
            'perusahaan_alamat' => 'Jl. Uji Layanan No. 1', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null, 'hp' => '081300009999', 'tanggal_lahir' => null, 'teman' => [],
        ];
        $anggota = [['siswa_id' => $siswaId, 'kelas_id' => $this->kelasId, 'peran' => 'pengaju', 'hp' => '081300009999']];
        foreach ($teman as $t) {
            $anggota[] = ['siswa_id' => $t, 'kelas_id' => $this->kelasId, 'peran' => 'teman', 'hp' => null];
        }
        $r = (new PklAjuan())->kirimBaru($data, $anggota,
            ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP, 'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui', 'tahun_ajaran' => '2031/2032']);

        return (int) ($r['id'] ?? 0);
    }

    /** @return array<string, mixed> */
    private function ctx(): array
    {
        return ['oleh' => self::OLEH . ' operator', 'admin_id' => null, 'peran' => 'operator', 'ip' => self::IP];
    }

    private function jumlah(string $tabel, array $where): int
    {
        return (int) $this->db->table($tabel)->where($where)->countAllResults();
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete(); // CASCADE: siswa, riwayat
        $this->db->table('surat_sekolah')->like('dibuat_nama', 'Siswa: ' . self::OLEH, 'after')->delete();
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_map('intval', array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete();
            }
            $this->db->table('surat_permintaan')->whereIn('siswa_id', $ids)->delete();
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama', self::OLEH)->delete();
        $this->db->table('surat_jadwal')->groupStart()->like('sesi', self::NIS)->orLike('tempat', self::OLEH)->groupEnd()->delete();
        $this->db->table('audit_log')->like('deskripsi', self::OLEH)->delete();
        $this->db->table('audit_log')->like('deskripsi', self::NIS)->delete();
        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        $this->pulihkanPeran();
        PklHak::lupakan();
    }
}
