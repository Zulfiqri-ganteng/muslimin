<?php

namespace App\Commands;

use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
use App\Libraries\SiswaStatus;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji status & hapus siswa (SiswaStatus, permintaan user 2026-10-29): nonaktifkan/aktifkan, hapus permanen hanya tanpa data terkait,
 * data terhapus lama (pulihkan), pesan bentrok NIS/NISN, halaman web, API Android. Memakai siswa uji sementara (NIS ZZUJISS-…) —
 * dibersihkan di akhir.
 *
 *   php spark dev:uji-siswa-status
 *   UJI_FIXTURE=C:\flutter-muslimin\test\fixtures\siswa php spark dev:uji-siswa-status
 */
class UjiSiswaStatus extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-siswa-status';
    protected $description = 'Uji nonaktifkan/aktifkan, hapus permanen berpenjaga, terhapus lama, bentrok NIS, web & API siswa.';

    private int $lulus = 0;
    private int $gagal = 0;
    private array $fx = [];
    /** @var array<string, int> */
    private array $id = [];
    private int $kelas = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . mb_substr($detail, 0, 400) : ''), 'red');
        }
    }

    public function run(array $params)
    {
        $db        = db_connect();
        $auditAwal = (int) ($db->table('audit_log')->selectMax('id', 'm')->get()->getRow()->m ?? 0);
        $this->kelas = (int) ($db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRow()->id ?? 0);
        try {
            $now = date('Y-m-d H:i:s');
            foreach (['A' => null, 'B' => null, 'C' => $now] as $k => $hapus) {
                $db->table('siswa')->insert(['nis' => 'ZZUJISS-' . $k, 'nisn' => 'ZZ90000' . ord($k), 'nama' => 'ZZUJI Siswa ' . $k, 'kelas_id' => $this->kelas,
                    'status' => 'aktif', 'tahun_masuk' => 2026, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $hapus]);
                $this->id[$k] = (int) $db->insertID();
            }
            $db->table('keterlambatan_siswa')->insert(['tanggal' => date('Y-m-d'), 'siswa_id' => $this->id['B'], 'nama_siswa' => 'ZZUJI Siswa B',
                'kelas_id' => $this->kelas, 'created_at' => $now]);

            $this->ujiPustaka();
            $this->ujiWeb();
            $this->ujiApi();
            $this->ujiHak();
            $dir = (string) getenv('UJI_FIXTURE');
            if ($dir !== '' && is_dir($dir)) {
                foreach ($this->fx as $nama => $isi) {
                    file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
                $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
            }
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            ApiAuth::clear();
            session()->remove(['isLoggedIn', 'admin', 'success', 'error', 'errors']);
            $db->table('keterlambatan_siswa')->like('nama_siswa', 'ZZUJI Siswa', 'after')->delete();
            $db->table('siswa')->like('nis', 'ZZUJISS-', 'after')->delete();
            $db->table('audit_log')->where('id >', $auditAwal)->where('tabel', 'siswa')->delete();
            master_data_changed('siswa');
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function baris(string $k): ?array
    {
        return db_connect()->table('siswa')->where('id', $this->id[$k])->get()->getRowArray();
    }

    private function ujiPustaka(): void
    {
        CLI::write('== Pustaka SiswaStatus ==', 'yellow');
        $t = SiswaStatus::tabelTerkait();
        $this->cek('tabel terkait dibaca otomatis dari kolom siswa_id (PKL, surat, biodata, terlambat, UKK, …)', count(array_intersect($t, ['pkl_anggota', 'pkl_pembayaran',
            'surat_sekolah_siswa', 'biodata_isian', 'keterlambatan_siswa', 'peserta_ukk'])) === 6 && ! in_array('siswa', $t, true), implode(',', $t));
        $k = SiswaStatus::keterkaitan([$this->id['A'], $this->id['B']]);
        $this->cek('keterkaitan: A kosong, B = Catatan terlambat (1)', $k[$this->id['A']] === [] && $k[$this->id['B']] === ['Catatan terlambat' => 1], json_encode($k));

        $r = SiswaStatus::hapusPermanen([$this->id['A'], $this->id['B']], 'ZZUJI');
        $this->cek('hapus permanen: A terhapus BENAR-BENAR (tanpa jejak), B ditolak dengan alasan', count($r['dihapus']) === 1 && $this->baris('A') === null
            && ($r['ditolak'][0]['terkait'] ?? '') === 'Catatan terlambat (1)' && $this->baris('B') !== null, json_encode($r));
        $this->cek('pesan hapus menyebut yang ditolak + saran Nonaktifkan', str_contains(SiswaStatus::pesanHapus($r), 'TIDAK dihapus') && str_contains(SiswaStatus::pesanHapus($r), 'Nonaktifkan'));
        $db = db_connect();
        $db->table('siswa')->insert(['nis' => 'ZZUJISS-A', 'nama' => 'ZZUJI Siswa A2', 'kelas_id' => $this->kelas, 'status' => 'aktif', 'created_at' => date('Y-m-d H:i:s')]);
        $this->id['A'] = (int) $db->insertID();
        $this->cek('NIS siswa yang dihapus permanen langsung bisa dipakai lagi', $this->id['A'] > 0);

        $this->cek('nonaktifkan dengan status tidak sah → ditolak', ! SiswaStatus::nonaktifkan($this->id['B'], 'aktif', '', 'ZZUJI')['ok']);
        $r = SiswaStatus::nonaktifkan($this->id['B'], 'pindah', 'Pindah ke SMK uji', 'ZZUJI');
        $b = $this->baris('B');
        $this->cek('nonaktifkan → status pindah, keterangan berisi alasan + tanggal, data tetap ada', $r['ok'] && $b['status'] === 'pindah'
            && str_contains((string) $b['keterangan'], 'Pindah sekolah ' . date('d-m-Y') . ': Pindah ke SMK uji') && $b['deleted_at'] === null, json_encode($b));
        $r = SiswaStatus::aktifkan($this->id['B'], 'ZZUJI');
        $this->cek('aktifkan kembali → aktif', $r['ok'] && $this->baris('B')['status'] === 'aktif');
        $this->cek('label status', SiswaStatus::label('aktif') === 'Aktif' && SiswaStatus::label('pindah') === 'Pindah sekolah');

        $this->cek('bentrok NIS milik data TERHAPUS → pesan menyebut nama + cara memulihkan', str_contains((string) SiswaStatus::pesanBentrok('nis', 'ZZUJISS-C'), 'TERHAPUS: ZZUJI Siswa C')
            && str_contains((string) SiswaStatus::pesanBentrok('nis', 'ZZUJISS-C'), 'Terhapus (lama)'));
        $this->cek('bentrok NIS milik siswa aktif → nama + kelas; diri sendiri → tidak bentrok', str_contains((string) SiswaStatus::pesanBentrok('nis', 'ZZUJISS-B'), 'sudah dipakai siswa lain: ZZUJI Siswa B (')
            && SiswaStatus::pesanBentrok('nis', 'ZZUJISS-B', $this->id['B']) === null && SiswaStatus::pesanBentrok('nis', '') === null);
    }

    private function ujiWeb(): void
    {
        CLI::write('== Halaman web ==', 'yellow');
        $html = (string) $this->web('index', ['status' => 'terhapus', 'q' => 'ZZUJI Siswa']);
        $this->cek('saringan "Terhapus (lama)": tampil data terhapus + Pulihkan + Hapus permanen + penjelasan', str_contains($html, 'ZZUJI Siswa C') && str_contains($html, 'Pulihkan')
            && str_contains($html, 'Hapus permanen') && ! str_contains($html, 'ZZUJI Siswa B'));
        $html = (string) $this->web('index', ['q' => 'ZZUJI Siswa']);
        $this->cek('daftar biasa: tombol Nonaktifkan (aktif), tanpa tombol Hapus per baris, tanpa "Hapus semua"', str_contains($html, '/nonaktifkan') && ! str_contains($html, 'data-bulk="all"')
            && ! str_contains($html, 'siswa/delete/' . $this->id['B']) && ! str_contains($html, 'ZZUJI Siswa C'));
        $this->web('store', [], ['nis' => 'ZZUJISS-C', 'nama' => 'ZZUJI Siswa D', 'status' => 'aktif'], 'POST');
        $this->cek('tambah siswa dengan NIS milik data terhapus → galat jelas', str_contains((string) (session('errors')['nis'] ?? ''), 'TERHAPUS'), json_encode(session('errors')));

        $this->web('bulkDelete', [], ['mode' => 'all'], 'POST');
        $this->cek('hapus massal mode "semua" → ditolak', str_contains((string) session('error'), 'tidak tersedia'));
        $this->web('delete', [], [], 'POST', (string) $this->id['B']);
        $this->cek('hapus siswa ber-data → ditolak + alasan', str_contains((string) session('error'), 'Catatan terlambat (1)') && $this->baris('B') !== null);
        $this->web('bulkDelete', [], ['ids' => [$this->id['A'], $this->id['B']]], 'POST');
        $this->cek('hapus massal terpilih → yang tanpa data terhapus, yang ber-data dilewati', str_contains((string) session('success'), '1 siswa dihapus permanen') && $this->baris('A') === null && $this->baris('B') !== null);

        $html = (string) $this->web('nonaktifkanForm', [], [], 'GET', (string) $this->id['B']);
        $this->cek('halaman Nonaktifkan: pilihan Lulus / Pindah sekolah / Keluar + alasan', str_contains($html, 'Lulus') && str_contains($html, 'Pindah sekolah') && str_contains($html, 'name="catatan"'));
        $this->web('nonaktifkan', [], ['status' => 'keluar', 'catatan' => ''], 'POST', (string) $this->id['B']);
        $this->cek('POST nonaktifkan → status keluar + pesan', $this->baris('B')['status'] === 'keluar' && str_contains((string) session('success'), 'tidak aktif'));
        $html = (string) $this->web('index', ['status' => 'nonaktif', 'q' => 'ZZUJI Siswa']);
        $this->cek('saringan "Tidak aktif (semua)": siswa keluar tampil + tombol Aktifkan', str_contains($html, 'ZZUJI Siswa B') && str_contains($html, '/aktifkan'));
        $html = (string) $this->web('detail', [], [], 'GET', (string) $this->id['B']);
        $this->cek('Detail: kartu Status siswa + Aktifkan kembali + alasan tak bisa hapus permanen', str_contains($html, 'Status siswa') && str_contains($html, 'Aktifkan kembali')
            && str_contains($html, 'Tidak bisa dihapus permanen') && str_contains($html, 'Catatan terlambat'));
        $this->web('aktifkan', [], [], 'POST', (string) $this->id['B']);
        $this->cek('POST aktifkan → aktif', $this->baris('B')['status'] === 'aktif');
        $this->web('pulihkan', [], [], 'POST', (string) $this->id['C']);
        $this->cek('POST pulihkan data terhapus → muncul lagi', $this->baris('C')['deleted_at'] === null && str_contains((string) session('success'), 'dipulihkan'));
        db_connect()->table('siswa')->where('id', $this->id['C'])->update(['deleted_at' => date('Y-m-d H:i:s')]); // kembalikan untuk uji API
        $this->cek('Audit Log mencatat nonaktif/aktif/hapus/pulihkan', db_connect()->table('audit_log')->where('tabel', 'siswa')->like('deskripsi', 'ZZUJI Siswa')->countAllResults() >= 4);
    }

    private function ujiApi(): void
    {
        CLI::write('== API Android ==', 'yellow');
        [$k, $j] = $this->api('destroy', null, (string) $this->id['B'], 'DELETE');
        $this->cek('DELETE siswa ber-data → 409 + data.terkait', $k === 409 && ($j['data']['terkait'][0]['label'] ?? '') === 'Catatan terlambat', json_encode($j));
        $this->fx['siswa_hapus_409'] = $j;
        [$k, $j] = $this->api('show', null, (string) $this->id['B']);
        $this->cek('GET detail: terkait[] + bisa_hapus_permanen + status_label/aktif/terhapus', $k === 200 && ($j['data']['bisa_hapus_permanen'] ?? null) === false
            && ($j['data']['status_label'] ?? '') === 'Aktif' && ($j['data']['aktif'] ?? null) === true && ($j['data']['terhapus'] ?? null) === false);
        $this->fx['siswa_detail_terkait'] = $this->pangkas($j);
        [$k, $j] = $this->api('status', ['status' => 'lulus', 'catatan' => 'Lulus 2026'], (string) $this->id['B'], 'POST');
        $this->cek('POST {id}/status lulus → baris segar status_label Lulus, aktif=false', $k === 200 && ($j['data']['status'] ?? '') === 'lulus' && ($j['data']['aktif'] ?? null) === false);
        $this->fx['siswa_status_nonaktif'] = $this->pangkas($j);
        [$k] = $this->api('status', ['status' => 'hilang'], (string) $this->id['B'], 'POST');
        $this->cek('POST status tidak sah → 422', $k === 422);
        [$k, $j] = $this->api('index', null, '', 'GET', ['status' => 'nonaktif', 'q' => 'ZZUJI Siswa']);
        $this->cek('GET daftar ?status=nonaktif → siswa lulus tampil', $k === 200 && in_array('ZZUJI Siswa B', array_column($j['data'] ?? [], 'nama'), true), json_encode(array_column($j['data'] ?? [], 'nama')));
        [$k, $j] = $this->api('index', null, '', 'GET', ['status' => 'terhapus', 'q' => 'ZZUJI Siswa']);
        $this->cek('GET daftar ?status=terhapus → hanya data terhapus lama (terhapus=true)', $k === 200 && array_column($j['data'] ?? [], 'nama') === ['ZZUJI Siswa C']
            && ($j['data'][0]['terhapus'] ?? null) === true);
        [$k, $j] = $this->api('pulihkan', null, (string) $this->id['C'], 'POST');
        $this->cek('POST {id}/pulihkan → 200', $k === 200 && ($j['data']['terhapus'] ?? null) === false);
        [$k] = $this->api('bulkDestroy', ['mode' => 'all'], '', 'POST');
        $this->cek('POST bulk-delete mode all → 422', $k === 422);
        [$k, $j] = $this->api('bulkDestroy', ['ids' => [$this->id['B'], $this->id['C']]], '', 'POST');
        $this->cek('POST bulk-delete ids → dihapus 1 (C), ditolak 1 (B ber-data), kunci lama "deleted" tetap ada', $k === 200 && ($j['data']['dihapus'] ?? 0) === 1
            && ($j['data']['deleted'] ?? 0) === 1 && count($j['data']['ditolak'] ?? []) === 1 && $this->baris('C') === null);
        $this->fx['siswa_bulk_hapus'] = $j;
    }

    private function ujiHak(): void
    {
        CLI::write('== Hak ==', 'yellow');
        $this->cek('alamat baru ikut hak Master Siswa (Admin boleh)', HakAkses::boleh('admin', 'admin/master/siswa/1/nonaktifkan') && HakAkses::boleh('admin', 'admin/master/siswa/1/aktifkan', 'POST')
            && HakAkses::boleh('admin', 'admin/master/siswa/1/pulihkan', 'POST'));
        $this->cek('peran tanpa Master Siswa tetap ditolak', HakAkses::boleh('guru', 'admin/master/siswa/1/aktifkan', 'POST') === HakAkses::boleh('guru', 'admin/master/siswa', 'POST'));
    }

    // =================================================================

    private function pangkas(array $j): array
    {
        foreach (['bagian', 'jejak'] as $k) {
            if (isset($j['data'][$k]) && is_array($j['data'][$k])) {
                $j['data'][$k] = array_slice($j['data'][$k], 0, 1);
            }
        }

        return $j;
    }

    private function web(string $metode, array $get = [], array $post = [], string $http = 'GET', string ...$arg): mixed
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/master/siswa'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        \Config\Services::injectMock('request', $request);
        session()->set(['isLoggedIn' => true, 'admin' => ['id' => 1, 'full_name' => 'ZZUJI Admin', 'username' => 'zzuji', 'role' => 'admin']]);
        session()->remove(['success', 'error', 'errors', '_ci_old_input']);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Admin\Master\Siswa();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function api(string $metode, ?array $json, string $id = '', string $http = 'GET', array $get = []): array
    {
        ApiAuth::set(['id' => 1, 'full_name' => 'ZZUJI Admin', 'username' => 'zzuji', 'role' => 'admin']);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/admin/master/siswa'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        if ($json !== null) {
            $request->setHeader('Content-Type', 'application/json');
            $request->setBody(json_encode($json));
        }
        $request->setGlobal('get', $get);
        \Config\Services::injectMock('request', $request);
        $c = new \App\Controllers\Api\Admin\Siswa();
        $c->initController($request, service('response'), service('logger'));
        $r = $id !== '' ? $c->{$metode}($id) : $c->{$metode}();
        ApiAuth::clear();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }
}
