<?php

namespace App\Commands;

use App\Libraries\LayananToken;
use App\Libraries\LoginThrottle;
use App\Libraries\PklAjuan;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Config\Services;

/**
 * Uji FORM PENGAJUAN PKL publik — halaman web `pkl` (Controllers\Pkl: siswa, perusahaan, buka, kirim) dan API aplikasi Android
 * `publik/pkl/*` (Api\PklAjukan, layanan LY5). Keduanya memakai logika yang SAMA, jadi skenario & hasilnya sama:
 *   daftar nama + status, kirim baru (pengaju + teman), kirim ulang ditolak (pesan terkunci), galat per kolom, dikembalikan
 *   staf → buka dengan HP (salah/benar) → kirim perbaikan; tanpa izin buka → ditolak.
 * Jalur API: izin buka = token `X-Izin-Token` (bukan sesi). `--fixture <folder>` menyimpan respons API untuk tes aplikasi.
 *
 * Data uji "ZZUJI PP …" (nama), NIS "ZZUJIPP…", IP "10.9.7.9", perusahaan "ZZUJIPP …" — dibuat & dihapus sendiri; Pengaturan PKL
 * dipulihkan ke isi semula di akhir.
 *
 * Jalankan:  php spark dev:uji-pkl-publik
 */
class UjiPklPublik extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-pkl-publik';
    protected $description = 'Uji form pengajuan PKL publik: halaman web & API aplikasi (LY5).';

    private const NIS  = 'ZZUJIPP';
    private const IP   = '10.9.7.9';
    private const OLEH = 'ZZUJI PP';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $asli = null;
    private int $kelasId = 0;

    /** @var array<int, int> nomor => id siswa uji */
    private array $s = [];

    public function run(array $params)
    {
        try {
            return $this->jalan();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function jalan(): int
    {
        $this->db = db_connect();
        $this->bersihkan();
        $k = $this->db->table('kelas')->select('id, tingkat')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if ($k === null) {
            $this->cek('ada kelas sebagai bahan uji', false, 'tabel kelas kosong');

            return EXIT_ERROR;
        }
        $this->kelasId = (int) $k['id'];
        $this->asli    = (new PklPengaturanModel())->ambil();
        $this->db->table('pkl_pengaturan')->where('id', 1)->update([
            'form_buka' => 1, 'form_tutup' => null, 'tingkat' => (string) $k['tingkat'], 'maks_anggota' => 3, 'batas_keputusan_hari' => 5,
        ]);
        $now = date('Y-m-d H:i:s');
        for ($n = 1; $n <= 6; $n++) {
            $this->db->table('siswa')->insert([
                'nis' => self::NIS . $n, 'nama' => self::OLEH . ' ' . $n, 'jenis_kelamin' => 'L', 'kelas_id' => $this->kelasId,
                'no_hp' => $this->hp($n), 'status' => 'aktif', 'tanggal_lahir' => '2009-03-03', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->s[$n] = (int) $this->db->insertID();
        }

        $this->ujiWeb();
        if (class_exists(\App\Controllers\Api\PklAjukan::class)) {
            $this->ujiApi();
        } else {
            CLI::write('(Api\PklAjukan belum ada — bagian API dilewati)', 'yellow');
        }

        $this->bersihkan();
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . $detail : ''), $ok ? 'green' : 'red');
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    private function hp(int $n): string
    {
        return '0812333300' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /** Isian perusahaan yang sah. @return array<string, string> */
    private function isian(int $pengaju, string $perusahaan): array
    {
        return [
            'siswa_id' => (string) $pengaju, 'perusahaan_nama' => $perusahaan, 'perusahaan_alamat' => 'Jl. Raya Uji Pengajuan No. 5, Cikarang',
            'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => '02188776655', 'kontak_nama' => 'Bapak Andi Wijaya', 'kontak_jabatan' => 'Manajer HRD',
            'hp' => $this->hp(array_search($pengaju, $this->s, true) ?: 0), 'pernyataan' => '1',
        ];
    }

    private function permintaan(string $uri, array $get, array $post, ?string $json = null): IncomingRequest
    {
        $request = new IncomingRequest(config('App'), new SiteURI(config('App'), $uri), $json, new UserAgent());
        Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('server', ['REMOTE_ADDR' => self::IP]);

        return $request;
    }

    /** Controller web publik (sesi ikut, seperti peramban). @return array{0: int, 1: array<string, mixed>} */
    private function web(string $metode, array $get = [], array $post = []): array
    {
        $request = $this->permintaan('pkl', $get, $post);
        $c       = new \App\Controllers\Pkl();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r = $c->{$metode}();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    /** API aplikasi (isi JSON, tanpa sesi). @return array{0: int, 1: array<string, mixed>} */
    private function api(string $metode, array $get = [], array $json = [], string $token = '', string $fixture = ''): array
    {
        session()->remove(['pkl_buka', 'pkl_selesai']); // API tidak boleh menumpang sesi web
        $request = $this->permintaan('api/v1/publik/pkl', $get, [], $json === [] ? null : json_encode($json));
        if ($json !== []) {
            $request->setHeader('Content-Type', 'application/json');
        }
        if ($token !== '') {
            $request->setHeader('X-Izin-Token', $token);
        }
        $c = new \App\Controllers\Api\PklAjukan();
        $c->initController($request, service('response'), service('logger'));
        /** @var ResponseInterface $r */
        $r   = $c->{$metode}();
        $isi = json_decode((string) $r->getBody(), true) ?: [];
        $dir = (string) (CLI::getOption('fixture') ?? '');
        if ($fixture !== '' && $dir !== '') {
            $simpan = $isi;
            if ($fixture === 'pkl_siswa') { // daftar nama sekelas: hanya siswa uji — nama siswa asli tidak boleh masuk fixture
                $simpan['data'] = array_values(array_filter($isi['data'] ?? [], static fn (array $s): bool => str_starts_with((string) $s['nama'], self::OLEH)));
            }
            @mkdir($dir, 0775, true);
            file_put_contents(rtrim($dir, '/\\') . '/' . $fixture . '.json', json_encode($simpan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return [$r->getStatusCode(), $isi];
    }

    /** @param array<int, array<string, mixed>> $daftar */
    private function status(array $daftar, int $siswaId): string
    {
        foreach ($daftar as $s) {
            if ((int) $s['id'] === $siswaId) {
                return (string) $s['status'] . ($s['peran'] !== null ? '/' . $s['peran'] : '');
            }
        }

        return '-';
    }

    private function kembalikan(int $siswaId): int
    {
        $id = (int) ((new PklPengajuanModel())->aktifMilik($siswaId)['id'] ?? 0);
        (new PklAjuan())->ubahStatus($id, 'perbaikan', ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP], 'Alamat perusahaan kurang lengkap');

        return $id;
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_map('intval', array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete(); // CASCADE: anggota, riwayat
            }
            $throttle = new LoginThrottle();
            foreach ($ids as $id) {
                $throttle->clear('pkl:' . $id, self::IP);
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujipp', 'after')->delete();
        $this->db->table('audit_log')->like('deskripsi', self::OLEH)->delete();
        session()->remove(['pkl_buka', 'pkl_selesai']);
        if ($this->asli !== null) {
            $p = $this->asli;
            unset($p['id']);
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($p);
            $this->asli = null;
        }
    }

    // =================================================================
    // Halaman web (patokan perilaku)
    // =================================================================

    private function ujiWeb(): void
    {
        $this->bagian('Halaman web pkl: siswa, kirim, buka, kirim perbaikan');
        [$k, $j] = $this->web('siswa', ['kelas_id' => $this->kelasId]);
        $this->cek('siswa: 200, siswa uji berstatus belum', $k === 200 && ($j['ok'] ?? false) && $this->status($j['data'] ?? [], $this->s[1]) === 'belum');
        [$k, $j] = $this->web('siswa');
        $this->cek('siswa tanpa kelas → 422', $k === 422);

        [$k, $j] = $this->web('kirim', [], $this->isian($this->s[5], '') + []);
        $this->cek('kirim isian salah → 422 errors.perusahaan_nama', $k === 422 && isset($j['errors']['perusahaan_nama']), json_encode($j));

        $isi     = $this->isian($this->s[1], 'ZZUJIPP Perusahaan Web') + ['teman' => [(string) $this->s[2]], 'teman_hp' => [$this->s[2] => $this->hp(2)]];
        [$k, $j] = $this->web('kirim', [], $isi);
        $info    = (array) session('pkl_selesai');
        $this->cek('kirim baru (pengaju + 1 teman) → ok + ke halaman selesai, ringkasan 2 siswa', $k === 200 && ($j['ok'] ?? false) && str_ends_with((string) ($j['redirect'] ?? ''), 'pkl/selesai')
            && (int) ($info['jumlah'] ?? 0) === 2 && ($info['revisi'] ?? true) === false, json_encode($j));
        [$k, $j] = $this->web('kirim', [], $isi);
        $this->cek('kirim lagi → 409 pesan terkunci (sudah mengajukan)', $k === 409 && str_contains((string) ($j['message'] ?? ''), 'Kamu sudah mengajukan PKL'), (string) ($j['message'] ?? ''));
        [, $j] = $this->web('siswa', ['kelas_id' => $this->kelasId]);
        $this->cek('siswa: pengaju menunggu/pengaju, teman menunggu/teman', $this->status($j['data'] ?? [], $this->s[1]) === 'menunggu/pengaju' && $this->status($j['data'] ?? [], $this->s[2]) === 'menunggu/teman');

        $idAj = $this->kembalikan($this->s[1]);
        session()->remove('pkl_buka');
        [$k, $j] = $this->web('kirim', [], $isi + ['ajuan_id' => (string) $idAj]);
        $this->cek('kirim perbaikan tanpa buka → 403', $k === 403 && str_contains((string) ($j['message'] ?? ''), 'Buka ajuanmu dulu'), (string) ($j['message'] ?? ''));
        [$k, $j] = $this->web('buka', [], ['siswa_id' => (string) $this->s[2], 'hp' => $this->hp(2)]);
        $this->cek('buka oleh teman → 409', $k === 409);
        [$k, $j] = $this->web('buka', [], ['siswa_id' => (string) $this->s[1], 'hp' => '081299998888']);
        $this->cek('buka HP salah → 422', $k === 422 && str_contains((string) ($j['message'] ?? ''), 'tidak cocok'));
        [$k, $j] = $this->web('buka', [], ['siswa_id' => (string) $this->s[1], 'hp' => $this->hp(1)]);
        $this->cek('buka HP benar → 200 data ajuan + catatan sekolah + teman', $k === 200 && (int) ($j['data']['ajuan_id'] ?? 0) === $idAj && ($j['catatan'] ?? '') === 'Alamat perusahaan kurang lengkap'
            && count($j['data']['teman'] ?? []) === 1, json_encode($j));
        [$k, $j] = $this->web('kirim', [], $isi + ['ajuan_id' => (string) $idAj, 'perusahaan_alamat' => 'Jl. Raya Uji Pengajuan No. 5 Blok C, Cikarang Barat']);
        $info    = (array) session('pkl_selesai');
        $this->cek('kirim perbaikan sesudah buka → ok (revisi), ajuan kembali menunggu', $k === 200 && ($j['ok'] ?? false) && ($info['revisi'] ?? false) === true
            && ($this->db->table('pkl_pengajuan')->select('status')->where('id', $idAj)->get()->getRowArray()['status'] ?? '') === 'menunggu', json_encode($j));
    }

    // =================================================================
    // API aplikasi (LY5)
    // =================================================================

    private function ujiApi(): void
    {
        $this->bagian('API publik aplikasi: publik/pkl (LY5)');
        [$k, $j] = $this->api('index', [], [], '', 'pkl_form');
        $ada     = false;
        foreach ($j['data']['kelas'] ?? [] as $g) {
            $ada = $ada || in_array($this->kelasId, array_map('intval', array_column($g['kelas'] ?? [], 'id')), true);
        }
        $this->cek('GET publik/pkl: terbuka, kelas per tingkat, maks siswa 3, batas 5 hari', $k === 200 && ($j['data']['terbuka'] ?? false) === true && $ada
            && (int) ($j['data']['maks_siswa'] ?? 0) === 3 && (int) ($j['data']['batas_hari'] ?? 0) === 5 && is_bool($j['data']['lapor_terbuka'] ?? null), json_encode($j['data'] ?? $j));
        [$k, $j] = $this->api('siswa', ['kelas_id' => $this->kelasId], [], '', 'pkl_siswa');
        $this->cek('GET siswa: amplop API, status sama dengan web', $k === 200 && ($j['status'] ?? '') === 'success' && $this->status($j['data'] ?? [], $this->s[1]) === 'menunggu/pengaju'
            && $this->status($j['data'] ?? [], $this->s[3]) === 'belum', json_encode($j['data'] ?? $j));
        [$k, $j] = $this->api('perusahaan', ['q' => 'zz']);
        $this->cek('GET perusahaan?q= → 200 daftar', $k === 200 && is_array($j['data'] ?? null));

        [$k, $j] = $this->api('kirim', [], ['siswa_id' => $this->s[6], 'perusahaan_nama' => 'X', 'perusahaan_alamat' => 'Jl', 'perusahaan_kota' => '', 'hp' => '0812', 'pernyataan' => ''], '', 'pkl_kirim_galat');
        $this->cek('POST kirim isian salah → 422 data.errors per kolom (kalimat web)', $k === 422 && isset($j['data']['errors']['perusahaan_nama'], $j['data']['errors']['hp'], $j['data']['errors']['pernyataan']), json_encode($j));
        $isi     = $this->isian($this->s[3], 'ZZUJIPP Perusahaan Aplikasi') + ['teman' => [$this->s[4]], 'teman_hp' => [(string) $this->s[4] => $this->hp(4)]];
        $isi['siswa_id'] = $this->s[3];
        [$k, $j] = $this->api('kirim', [], $isi, '', 'pkl_kirim');
        $this->cek('POST kirim baru → 200 ringkasan (nomor bukti, 2 siswa, batas)', $k === 200 && str_starts_with((string) ($j['data']['no'] ?? ''), 'PKL-') && (int) ($j['data']['jumlah'] ?? 0) === 2
            && ($j['data']['revisi'] ?? true) === false && ($j['data']['batas'] ?? '') !== '', json_encode($j));
        [$k, $j] = $this->api('kirim', [], $isi, '', 'pkl_kirim_terkunci');
        $this->cek('POST kirim lagi → 409 pesan terkunci (kalimat web)', $k === 409 && str_contains((string) ($j['message'] ?? ''), 'Kamu sudah mengajukan PKL'));

        $idAj = $this->kembalikan($this->s[3]);
        [$k, $j] = $this->api('kirim', [], $isi + ['ajuan_id' => $idAj], '', 'pkl_kirim_belum_buka');
        $this->cek('POST kirim perbaikan tanpa token → 403', $k === 403 && str_contains((string) ($j['message'] ?? ''), 'Buka ajuanmu dulu'));
        [$k, $j] = $this->api('buka', [], ['siswa_id' => $this->s[3], 'hp' => '081299998888'], '', 'pkl_buka_salah');
        $this->cek('POST buka HP salah → 422', $k === 422);
        [$k, $j] = $this->api('buka', [], ['siswa_id' => $this->s[3], 'hp' => $this->hp(3)], '', 'pkl_buka');
        $token   = (string) ($j['data']['token'] ?? '');
        $this->cek('POST buka HP benar → 200 data ajuan + catatan + token', $k === 200 && $token !== '' && (int) ($j['data']['ajuan_id'] ?? 0) === $idAj
            && ($j['data']['catatan'] ?? '') === 'Alamat perusahaan kurang lengkap' && count($j['data']['teman'] ?? []) === 1, json_encode($j));
        $this->cek('token izin buka ≠ token Lapor / Layanan Siswa', LayananToken::bacaIzin('pkl-lapor', $token) === null && LayananToken::baca($token) === null);
        [$k, $j] = $this->api('kirim', [], $isi + ['ajuan_id' => $idAj + 1], $token);
        $this->cek('POST kirim perbaikan dengan token ajuan lain → 409/403 (tidak tersimpan)', in_array($k, [403, 409], true));
        [$k, $j] = $this->api('kirim', [], $isi + ['ajuan_id' => $idAj, 'perusahaan_alamat' => 'Jl. Raya Uji Pengajuan No. 5 Blok D, Cikarang Barat'], $token, 'pkl_kirim_perbaikan');
        $this->cek('POST kirim perbaikan dengan token → 200 revisi, ajuan kembali menunggu', $k === 200 && ($j['data']['revisi'] ?? false) === true
            && ($this->db->table('pkl_pengajuan')->select('status')->where('id', $idAj)->get()->getRowArray()['status'] ?? '') === 'menunggu', json_encode($j));
        $this->cek('API tidak menulis sesi web (izin buka & halaman selesai)', session('pkl_buka') === null && session('pkl_selesai') === null);
    }
}
