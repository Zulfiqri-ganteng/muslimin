<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\HakPeran;
use App\Libraries\WaliKelas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji WALI KELAS (2026-11-07): W1 migrasi (admins.guru_id, peran Wali Kelas), akses (umum + dijaga WaliKelas), menu, buat akun
 * massal (akun baru + menghubungkan akun lama bernama sama), Kelola Akun; W2 isi Kelas Saya (siswa + WA ortu, PKL = Status Siswa,
 * keterlambatan, surat, tab); W3 API wali/kelas[/{id}] + menu Android (auth/menu). `--fixture <folder>` menyimpan respons API. SEMUA data uji di dalam satu transaksi
 * yang DIBATALKAN di akhir (buat akun massal ikut membuat akun wali lain di DB lokal — ikut dibatalkan).
 *
 *   php spark dev:uji-wali-kelas
 */
class UjiWaliKelas extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-wali-kelas';
    protected $description = 'Uji fitur wali kelas (W1: akun, akses, menu, Kelas Saya).';

    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' && ! $ok ? '  → ' . mb_substr($detail, 0, 300) : ''), $ok ? 'green' : 'red');
    }

    public function run(array $params)
    {
        $db = db_connect();
        CLI::write('== Fondasi ==', 'yellow');
        $this->cek('migrasi: admins.guru_id ada & peran "Wali Kelas" dikenal, beranda admin/kelas-saya, boleh aplikasi', WaliKelas::siap()
            && HakAkses::dikenal('wali_kelas') && HakAkses::beranda('wali_kelas') === 'admin/kelas-saya' && HakAkses::bolehApi('wali_kelas'));
        $this->cek('akses: Kelas Saya terbuka semua peran (umum); peran Wali Kelas tidak bisa membuka PKL/Master/Surat', HakAkses::boleh('wali_kelas', 'admin/kelas-saya')
            && HakAkses::boleh('hubin', 'admin/kelas-saya') && ! HakAkses::boleh('wali_kelas', 'admin/pkl') && ! HakAkses::boleh('wali_kelas', 'admin/master/siswa')
            && ! HakAkses::boleh('wali_kelas', 'admin/surat') && HakAkses::bolehApiAlamat('wali_kelas', 'wali/kelas'));
        $this->cek('namaPolos & usernameDari: "Puguh Wira Sakti, S.Pd" → "puguh wira sakti" / username dua kata bertitik',
            WaliKelas::namaPolos('Puguh  Wira Sakti, S.Pd') === 'puguh wira sakti' && preg_match('/^zzujiwali\.satu\d*$/', (new WaliKelas())->usernameDari('ZZUJIWALI Satu, S.Kom')) === 1);

        $db->transBegin();
        try {
            $this->uji($db);
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            $db->transRollback();
            WaliKelas::lupakan();
            HakPeran::lupakan();
            session()->remove(['admin', 'wali_massal']);
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function uji($db): void
    {
        $now = date('Y-m-d H:i:s');
        $guru = static function (string $nama) use ($db, $now): int {
            $db->table('guru')->insert(['nama' => $nama, 'kode_guru' => 'ZZW' . substr(md5($nama), 0, 6), 'no_wa' => '081299990001', 'created_at' => $now, 'updated_at' => $now]);

            return (int) $db->insertID();
        };
        $kelas = static function (string $nama, int $wali) use ($db, $now): int {
            $db->table('kelas')->insert(['nama_kelas' => $nama, 'tingkat' => 'XI', 'wali_kelas_id' => $wali, 'created_at' => $now, 'updated_at' => $now]);

            return (int) $db->insertID();
        };
        $g1 = $guru('ZZUJIWALI Satu, S.Pd');
        $g2 = $guru('ZZUJIWALI Dua, S.Kom');
        $k1 = $kelas('ZZUJI XI A', $g1);
        $k2 = $kelas('ZZUJI XI B', $g2);
        $k3 = $kelas('ZZUJI XI C', $g2);
        $db->table('siswa')->insert(['nis' => 'ZZUJIWK1', 'nama' => 'ZZUJI Siswa Wali', 'jenis_kelamin' => 'P', 'kelas_id' => $k2, 'status' => 'aktif',
            'nama_ayah' => 'ZZUJI Ayah', 'ortu_telepon' => '0813-7777-8888', 'created_at' => $now, 'updated_at' => $now]);
        $sw = (int) $db->insertID();
        // Akun staf lama bernama sama dengan guru 2 (mis. Waka Hubin yang juga wali kelas).
        $db->table('admins')->insert(['full_name' => 'ZZUJIWALI Dua', 'email' => 'zzujiwali.dua@contoh.id', 'username' => 'zzujiwalidua', 'password' => 'x', 'role' => 'hubin', 'aktif' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $lama = (int) $db->insertID();
        $db->table('admins')->insert(['full_name' => 'ZZUJI Tanpa Guru', 'email' => 'zzuji.tanpa@contoh.id', 'username' => 'zzujitanpa', 'password' => 'x', 'role' => 'hubin', 'aktif' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $tanpa = (int) $db->insertID();

        CLI::write('== Buat akun wali kelas sekaligus ==', 'yellow');
        $lib = new WaliKelas($db);
        $h   = $lib->buatAkunMassal(['oleh' => 'ZZUJI Admin']);
        $d1  = array_values(array_filter($h['dibuat'], static fn (array $d): bool => $d['nama'] === 'ZZUJIWALI Satu, S.Pd'))[0] ?? null;
        $baru = $d1 !== null ? $db->table('admins')->where('id', $d1['id'])->get()->getRowArray() : null;
        $this->cek('guru 1 → akun BARU: peran Wali Kelas, username zzujiwali.satu, email @walikelas.local, sandi sementara cocok & wajib ganti, HP = WA guru', $h['ok'] && $baru !== null
            && $baru['role'] === 'wali_kelas' && str_starts_with($baru['username'], 'zzujiwali.satu') && str_ends_with($baru['email'], '@' . WaliKelas::DOMAIN_EMAIL)
            && (int) $baru['guru_id'] === $g1 && (int) $baru['wajib_ganti_sandi'] === 1 && password_verify($d1['sandi'], $baru['password']) && $baru['phone'] === '081299990001'
            && $d1['kelas'] === ['ZZUJI XI A'], json_encode([$h['pesan'], $d1, $baru]));
        $t2 = array_values(array_filter($h['ditautkan'], static fn (array $d): bool => $d['nama'] === 'ZZUJIWALI Dua'))[0] ?? null;
        $akunLama = $db->table('admins')->where('id', $lama)->get()->getRowArray();
        $this->cek('guru 2 → akun lama bernama sama DIHUBUNGKAN (peran Waka Hubin tetap, tidak dibuat akun baru), wali 2 kelas', $t2 !== null && (int) $akunLama['guru_id'] === $g2
            && $akunLama['role'] === 'hubin' && $t2['kelas'] === ['ZZUJI XI B', 'ZZUJI XI C'] && $db->table('admins')->where('guru_id', $g2)->countAllResults() === 1);
        $this->cek('jejak Audit Log tercatat', $db->table('audit_log')->like('deskripsi', 'ZZUJIWALI')->countAllResults() >= 2);
        $h2 = $lib->buatAkunMassal(['oleh' => 'ZZUJI Admin']);
        $this->cek('diulang → "Semua wali kelas sudah punya akun." (tidak ada akun ganda)', $h2['ok'] && $h2['dibuat'] === [] && $h2['ditautkan'] === [] && str_contains($h2['pesan'], 'sudah punya akun'), $h2['pesan']);
        $this->cek('dipakaiAkun: guru 2 sudah dipakai akun ZZUJIWALI Dua (formulir akun menolak menautkan ke akun lain)', $lib->dipakaiAkun($g2, $tanpa) === 'ZZUJIWALI Dua' && $lib->dipakaiAkun($g2, $lama) === null);

        CLI::write('== Akses & menu ==', 'yellow');
        WaliKelas::lupakan();
        $this->cek('kelasAkun: wali baru 1 kelas; akun Waka Hubin-wali 2 kelas urut nama; akun tanpa guru kosong', array_column($lib->kelasAkun((int) $baru['id']), 'id') === [$k1]
            && array_column($lib->kelasAkun($lama), 'id') === [$k2, $k3] && $lib->kelasAkun($tanpa) === []);
        $this->cek('bolehKelas: hanya kelas yang diwalikan; Admin semua kelas', $lib->bolehKelas(['id' => $lama, 'role' => 'hubin'], $k2) && ! $lib->bolehKelas(['id' => $lama, 'role' => 'hubin'], $k1)
            && $lib->bolehKelas(['id' => 999999, 'role' => 'admin'], $k1));
        $this->cek('menu "Kelas Saya": tampil bagi wali & Admin, tersembunyi bagi akun bukan wali; item lain tidak terpengaruh',
            WaliKelas::itemTampil('admin/kelas-saya', ['id' => $lama, 'role' => 'hubin']) && WaliKelas::itemTampil('admin/kelas-saya', ['id' => 1, 'role' => 'admin'])
            && ! WaliKelas::itemTampil('admin/kelas-saya', ['id' => $tanpa, 'role' => 'hubin']) && WaliKelas::itemTampil('admin/pkl', ['id' => $tanpa, 'role' => 'hubin']));

        CLI::write('== Halaman ==', 'yellow');
        $html = $this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => (int) $baru['id'], 'role' => 'wali_kelas', 'full_name' => 'x'], []);
        $this->cek('Kelas Saya (wali baru): nama kelasnya tampil', str_contains($html, 'ZZUJI XI A') && str_contains($html, 'ZZUJIWALI Satu'));
        $html = $this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => $lama, 'role' => 'hubin', 'full_name' => 'x'], ['kelas' => (string) $k3]);
        $this->cek('Kelas Saya (Waka Hubin-wali): pilihan 2 kelas, ?kelas= kelas C tampil', str_contains($html, 'name="kelas"') && str_contains($html, 'ZZUJI XI C') && str_contains($html, 'Pilih kelas'));
        $html = $this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => $lama, 'role' => 'hubin', 'full_name' => 'x'], ['kelas' => (string) $k2]);
        $this->cek('… kelas B berisi siswanya (ZZUJI Siswa Wali)', str_contains($html, 'ZZUJI Siswa Wali'));
        $html = $this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => $lama, 'role' => 'hubin', 'full_name' => 'x'], ['kelas' => (string) $k1]);
        $this->cek('… ?kelas= kelas orang lain → TIDAK tampil (kembali ke kelasnya sendiri)', ! str_contains($html, 'ZZUJI XI A</h2>') && str_contains($html, 'ZZUJI XI B'));
        $html = $this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => $tanpa, 'role' => 'hubin', 'full_name' => 'x'], []);
        $this->cek('akun bukan wali → "Anda belum tercatat sebagai wali kelas"', str_contains($html, 'belum tercatat sebagai wali kelas'));
        $html = $this->halaman(\App\Controllers\Admin\Akun::class, ['id' => 1, 'role' => 'admin', 'full_name' => 'ZZUJI Admin'], []);
        $this->cek('Kelola Akun: kartu "Akun wali kelas" + isian "Hubungkan ke data guru"', str_contains($html, 'Akun wali kelas') && str_contains($html, 'name="guru_id"')
            && str_contains($html, 'ZZUJIWALI Satu, S.Pd — wali ZZUJI XI A'));

        // ===== W2: isi Kelas Saya =====
        CLI::write('== W2 isi Kelas Saya ==', 'yellow');
        $ta = (string) ((new \App\Models\SettingModel())->get()['academic_year'] ?? '');
        $db->table('keterlambatan_siswa')->insert(['tanggal' => date('Y-m-d'), 'jam' => '07:15:00', 'siswa_id' => $sw, 'nama_siswa' => 'ZZUJI Siswa Wali', 'kelas_id' => $k2,
            'nama_kelas' => 'ZZUJI XI B', 'tahun_ajaran' => $ta !== '' ? $ta : null, 'sanksi' => 'Membersihkan halaman', 'guru_piket' => 'ZZUJI Piket', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('pkl_pengajuan')->insert(['status' => 'menunggu', 'sumber' => 'siswa', 'perusahaan_nama' => 'ZZUJI PT Wali', 'perusahaan_norm' => 'zzuji pt wali',
            'diajukan_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $aj = (int) $db->insertID();
        $db->table('pkl_anggota')->insert(['pengajuan_id' => $aj, 'siswa_id' => $sw, 'peran' => 'pengaju', 'kelas_id' => $k2, 'siswa_aktif' => $sw, 'created_at' => $now]);
        $d  = (new \App\Libraries\KelasSayaData($db))->kelas($k2, 'ZZUJIWALI Dua');
        $s0 = $d['siswa'][0] ?? [];
        $this->cek('data kelas: siswa + ortu + tautan WA ortu (wa.me/62…, salam dari wali kelas)', ($s0['nama'] ?? '') === 'ZZUJI Siswa Wali' && ($s0['ortu_nama'] ?? '') === 'ZZUJI Ayah'
            && str_starts_with((string) ($s0['wa_ortu'] ?? ''), 'https://wa.me/6281377778888?text=') && str_contains(rawurldecode((string) $s0['wa_ortu']), 'Saya ZZUJIWALI Dua, wali kelas ZZUJI XI B'), json_encode($s0));
        $this->cek('PKL siswa = Status Siswa ("menunggu" + nama perusahaan) & ringkasan PKL kelas', ($s0['pkl']['fase'] ?? '') === 'menunggu' && ($s0['pkl']['perusahaan'] ?? '') === 'ZZUJI PT Wali'
            && ($d['ringkasan']['pkl']['menunggu'] ?? -1) === 1, json_encode([$s0['pkl'] ?? null, $d['ringkasan']]));
        $this->cek('keterlambatan: jumlah per siswa 1, ringkasan 1 siswa/1 kejadian, daftar terbaru berisi sanksi & jam', ($s0['telat']['jumlah'] ?? 0) === 1 && $d['ringkasan']['telat_siswa'] === 1
            && $d['ringkasan']['telat_kejadian'] === 1 && ($d['keterlambatan'][0]['sanksi'] ?? '') === 'Membersihkan halaman' && ($d['keterlambatan'][0]['jam'] ?? '') === '07:15', json_encode($d['keterlambatan']));
        $this->cek('surat: daftar (kosong untuk siswa baru) & kunci lengkap', $d['surat'] === [] && array_key_exists('surat', $d['ringkasan']) && $d['kelas']['nama'] === 'ZZUJI XI B');
        $tabs = [];
        foreach (['siswa' => 'WA ortu', 'pkl' => 'ZZUJI PT Wali', 'telat' => 'Membersihkan halaman', 'surat' => 'Belum ada surat untuk siswa kelas ini'] as $t => $isi) {
            $tabs[$t] = str_contains($this->halaman(\App\Controllers\Admin\KelasSaya::class, ['id' => $lama, 'role' => 'hubin', 'full_name' => 'ZZUJIWALI Dua'], ['kelas' => (string) $k2, 'tab' => $t]), $isi);
        }
        $this->cek('halaman: tab Siswa (WA ortu), PKL, Keterlambatan, Surat tampil sesuai isi', ! in_array(false, $tabs, true), json_encode($tabs));

        // ===== W3: API & menu Android =====
        CLI::write('== W3 API ==', 'yellow');
        [$k, $j] = $this->api('kelas', ['id' => $lama, 'role' => 'hubin', 'full_name' => 'ZZUJIWALI Dua'], null, 'wali_kelas_daftar');
        $this->cek('GET wali/kelas (Waka Hubin-wali): 2 kelas + 4 bagian tab', $k === 200 && array_column($j['data']['kelas'] ?? [], 'id') === [$k2, $k3] && count($j['data']['bagian'] ?? []) === 4, json_encode($j));
        [$k, $j] = $this->api('kelas', ['id' => $tanpa, 'role' => 'hubin', 'full_name' => 'x'], null, 'wali_kelas_daftar_kosong');
        $this->cek('GET wali/kelas (bukan wali): 200 kelas kosong + pesan', $k === 200 && ($j['data']['kelas'] ?? null) === [] && str_contains((string) ($j['message'] ?? ''), 'belum tercatat'));
        [$k, $j] = $this->api('detail', ['id' => $lama, 'role' => 'hubin', 'full_name' => 'ZZUJIWALI Dua'], $k2, 'wali_kelas_detail');
        $this->cek('GET wali/kelas/{id} kelasnya: 200 isi sama dengan web (siswa, pkl, telat, wa_ortu, keterlambatan, surat)', $k === 200
            && ($j['data']['siswa'][0]['pkl']['fase'] ?? '') === 'menunggu' && isset($j['data']['ringkasan'], $j['data']['keterlambatan'], $j['data']['surat']) && ! empty($j['data']['siswa'][0]['wa_ortu']), json_encode($j));
        [$k, $j] = $this->api('detail', ['id' => $lama, 'role' => 'hubin', 'full_name' => 'ZZUJIWALI Dua'], $k1, 'wali_kelas_403');
        $this->cek('GET wali/kelas/{id} kelas orang lain → 403', $k === 403, json_encode($j));
        [$k] = $this->api('detail', ['id' => 1, 'role' => 'admin', 'full_name' => 'ZZUJI Admin'], $k1, '');
        $this->cek('Admin → kelas mana pun 200', $k === 200);
        $this->cek('penyaring API: awalan wali boleh untuk peran Wali Kelas & Waka Hubin', HakAkses::bolehApiAlamat('wali_kelas', 'wali/kelas/5') && HakAkses::bolehApiAlamat('hubin', 'wali/kelas'));
        $menu      = $this->menuApi(['id' => (int) $baru['id'], 'role' => 'wali_kelas', 'full_name' => 'x']);
        $menuTanpa = $this->menuApi(['id' => $tanpa, 'role' => 'hubin', 'full_name' => 'x']);
        $this->cek('auth/menu: wali dapat item "kelas_saya"; akun bukan wali tidak', in_array('kelas_saya', $menu, true) && ! in_array('kelas_saya', $menuTanpa, true), json_encode([$menu, $menuTanpa]));
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $metode, array $admin, ?int $id, string $fixture): array
    {
        \App\Libraries\ApiAuth::set($admin);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/wali/kelas'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $c = new \App\Controllers\Api\Wali();
        $c->initController($request, service('response'), service('logger'));
        $r = $id !== null ? $c->{$metode}($id) : $c->{$metode}();
        \App\Libraries\ApiAuth::clear();
        $isi = json_decode((string) $r->getBody(), true) ?: [];
        $dir = (string) (CLI::getOption('fixture') ?? '');
        if ($fixture !== '' && $dir !== '') {
            @mkdir($dir, 0775, true);
            file_put_contents(rtrim($dir, '/\\') . '/' . $fixture . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return [$r->getStatusCode(), $isi];
    }

    /** @return list<string> kunci item menu auth/menu */
    private function menuApi(array $admin): array
    {
        \App\Libraries\ApiAuth::set($admin);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/auth/menu'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $c = new \App\Controllers\Api\Auth();
        $c->initController($request, service('response'), service('logger'));
        $j = json_decode((string) $c->menu()->getBody(), true) ?: [];
        \App\Libraries\ApiAuth::clear();
        $out = [];
        array_walk_recursive($j, static function ($v, $k) use (&$out): void {
            if ($k === 'kunci' && is_string($v)) {
                $out[] = $v;
            }
        });

        return $out;
    }

    private function halaman(string $kelas, array $admin, array $get): string
    {
        \Config\Services::resetSingle('renderer');
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/x'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        session()->set('admin', $admin);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return html_entity_decode((string) $c->index(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
