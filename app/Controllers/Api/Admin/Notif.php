<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Fcm;
use App\Libraries\NotifJadwal;
use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JurusanModel;
use App\Models\NotifAturanModel;
use App\Models\NotifLogModel;
use App\Models\NotifPengaturanModel;
use App\Models\NotifPerangkatModel;

/**
 * Notifikasi HP jadwal guru masuk kelas (Firebase). Semua data milik admin
 * yang login saja. Rancangan: docs/DESAIN-ABSENSI-KELAS-NOTIF.md (B7).
 *
 *   GET    /api/v1/admin/notif?device_id=          → ringkasan + status HP ini
 *   GET    /api/v1/admin/notif/opsi                → pilihan hari, guru, jurusan, menit
 *   POST   /api/v1/admin/notif/pengaturan          → {aktif, jeda_sampai, diam_saat_ujian}
 *   GET    /api/v1/admin/notif/aturan              → daftar aturan
 *   POST   /api/v1/admin/notif/aturan              → buat aturan
 *   POST   /api/v1/admin/notif/aturan/{id}         → ubah aturan
 *   DELETE /api/v1/admin/notif/aturan/{id}         → hapus aturan
 *   POST   /api/v1/admin/notif/perangkat           → daftar token HP {device_id, token, nama_perangkat}
 *   POST   /api/v1/admin/notif/perangkat/terima    → {device_id, terima}
 *   POST   /api/v1/admin/notif/perangkat/hapus     → {device_id} (saat Keluar)
 *   GET    /api/v1/admin/notif/pratinjau?tanggal=  → notif yang akan terkirim hari itu
 *   POST   /api/v1/admin/notif/uji                 → {device_id} kirim notif uji ke HP ini
 *   GET    /api/v1/admin/notif/riwayat?page=       → riwayat notif
 */
class Notif extends BaseApiController
{
    // ===================== RINGKASAN & PILIHAN =====================
    public function index()
    {
        $adminId  = (int) $this->adminId();
        $deviceId = trim((string) $this->request->getGet('device_id'));
        $aturan   = (new NotifAturanModel())->milik($adminId);

        $perangkat = array_map(static fn ($p) => [
            'device_id'      => $p['device_id'],
            'nama_perangkat' => $p['nama_perangkat'],
            'terima'         => (int) $p['terima'] === 1,
            'last_seen_at'   => $p['last_seen_at'],
            'ini'            => $deviceId !== '' && $p['device_id'] === $deviceId,
        ], (new NotifPerangkatModel())->where('admin_id', $adminId)->orderBy('last_seen_at', 'DESC')->findAll());

        $ini = null;
        foreach ($perangkat as $p) {
            $ini = $p['ini'] ? $p : $ini;
        }

        return $this->ok([
            'fcm_siap'            => Fcm::siap(),
            'pengaturan'          => (new NotifPengaturanModel())->ambil($adminId),
            'jumlah_aturan'       => count($aturan),
            'jumlah_aturan_aktif' => count(array_filter($aturan, static fn ($a) => $a['aktif'])),
            'perangkat'           => $perangkat,
            'perangkat_ini'       => $ini,
            'sekarang'            => date('Y-m-d H:i:s'),
        ]);
    }

    public function opsi()
    {
        return $this->ok([
            'hari'    => array_map(static fn ($h) => [
                'id'    => (int) $h['id'],
                'nama'  => ucwords(strtolower((string) $h['nama'])),
                'aktif' => (int) $h['aktif'] === 1,
            ], (new HariModel())->orderBy('urutan', 'ASC')->findAll()),
            'guru'    => $this->guruOpsi(),
            'jurusan' => $this->jurusanOpsi(),
            'menit'   => NotifAturanModel::MENIT,
            // Ada key ini = server mendukung aturan per shift (aplikasi menampilkan pilihannya).
            'shift'   => array_map(
                static fn ($k, $v) => ['kode' => $k, 'label' => $v],
                array_keys(NotifAturanModel::SHIFT),
                NotifAturanModel::SHIFT
            ),
        ]);
    }

    // ===================== PENGATURAN =====================
    public function pengaturan()
    {
        $in   = $this->body();
        $jeda = trim((string) ($in['jeda_sampai'] ?? ''));
        if ($jeda !== '') {
            $ts = strtotime($jeda);
            if ($ts === false) {
                return $this->invalid(['jeda_sampai' => 'Tanggal jeda tidak valid.']);
            }
            $jeda = date('Y-m-d', $ts);
        }
        $adminId = (int) $this->adminId();
        $p       = (new NotifPengaturanModel())->simpan(
            $adminId,
            $this->bool($in['aktif'] ?? true),
            $jeda !== '' ? $jeda : null,
            $this->bool($in['diam_saat_ujian'] ?? true)
        );
        (new AuditModel())->record('update', 'notif_pengaturan', $adminId, 'Ubah pengaturan notifikasi jadwal (via mobile)');

        return $this->ok($p, 'Pengaturan notifikasi disimpan.');
    }

    // ===================== ATURAN =====================
    public function aturan()
    {
        return $this->ok(array_map([$this, 'labelAturan'], (new NotifAturanModel())->milik((int) $this->adminId())));
    }

    public function aturanStore()
    {
        $adminId = (int) $this->adminId();
        $model   = new NotifAturanModel();
        if ($model->where('admin_id', $adminId)->countAllResults() >= NotifAturanModel::MAKS) {
            return $this->failure('Maksimal ' . NotifAturanModel::MAKS . ' aturan notifikasi.', 422);
        }
        $data = $this->validasiAturan($this->body());
        if (isset($data['_galat'])) {
            return $this->invalid($data['_galat']);
        }
        $id = $model->insert(['admin_id' => $adminId] + $data, true);
        (new AuditModel())->record('create', 'notif_aturan', (int) $id, 'Tambah aturan notifikasi "' . $data['nama'] . '" (via mobile)');

        return $this->created($this->labelAturan(NotifAturanModel::rapikan($model->find($id))), 'Aturan notifikasi ditambahkan.');
    }

    public function aturanUpdate($id = 0)
    {
        $model = new NotifAturanModel();
        $row   = $model->where('admin_id', (int) $this->adminId())->find((int) $id);
        if (! $row) {
            return $this->missing('Aturan tidak ditemukan.');
        }
        // Aplikasi lama tak mengirim `shift` → pertahankan nilai tersimpan.
        $data = $this->validasiAturan($this->body(), (string) ($row['shift'] ?? 'semua'));
        if (isset($data['_galat'])) {
            return $this->invalid($data['_galat']);
        }
        $model->update((int) $id, $data);
        (new AuditModel())->record('update', 'notif_aturan', (int) $id, 'Ubah aturan notifikasi "' . $data['nama'] . '" (via mobile)');

        return $this->ok($this->labelAturan(NotifAturanModel::rapikan($model->find((int) $id))), 'Aturan notifikasi disimpan.');
    }

    public function aturanDestroy($id = 0)
    {
        $model = new NotifAturanModel();
        $row   = $model->where('admin_id', (int) $this->adminId())->find((int) $id);
        if (! $row) {
            return $this->missing('Aturan tidak ditemukan.');
        }
        $model->delete((int) $id);
        (new AuditModel())->record('delete', 'notif_aturan', (int) $id, 'Hapus aturan notifikasi "' . $row['nama'] . '" (via mobile)');

        return $this->ok(['id' => (int) $id], 'Aturan notifikasi dihapus.');
    }

    // ===================== PERANGKAT (HP) =====================
    public function perangkat()
    {
        $in       = $this->body();
        $deviceId = trim((string) ($in['device_id'] ?? ''));
        $token    = trim((string) ($in['token'] ?? ''));
        $galat    = [];
        if ($deviceId === '' || strlen($deviceId) > 64) {
            $galat['device_id'] = 'ID perangkat tidak valid.';
        }
        if ($token === '' || strlen($token) > 4096) {
            $galat['token'] = 'Token notifikasi tidak valid.';
        }
        if ($galat !== []) {
            return $this->invalid($galat);
        }
        $p = (new NotifPerangkatModel())->daftar((int) $this->adminId(), $deviceId, $token, trim((string) ($in['nama_perangkat'] ?? '')));
        if ($p === []) {
            return $this->failure('Gagal mendaftarkan HP ini. Coba lagi.', 500);
        }

        return $this->ok([
            'device_id' => $p['device_id'],
            'terima'    => (int) $p['terima'] === 1,
        ], 'HP ini terdaftar untuk notifikasi.');
    }

    public function perangkatTerima()
    {
        $in    = $this->body();
        $model = new NotifPerangkatModel();
        $p     = $model->milik((int) $this->adminId(), trim((string) ($in['device_id'] ?? '')));
        if (! $p) {
            return $this->missing('HP ini belum terdaftar untuk notifikasi.');
        }
        $terima = $this->bool($in['terima'] ?? true);
        $model->update($p['id'], ['terima' => $terima ? 1 : 0, 'last_seen_at' => date('Y-m-d H:i:s')]);

        return $this->ok(['terima' => $terima], $terima ? 'Notifikasi di HP ini dinyalakan.' : 'Notifikasi di HP ini dimatikan.');
    }

    public function perangkatHapus()
    {
        $deviceId = trim((string) ($this->body()['device_id'] ?? ''));
        if ($deviceId !== '') {
            (new NotifPerangkatModel())->where('admin_id', (int) $this->adminId())->where('device_id', $deviceId)->delete();
        }

        return $this->ok(null, 'HP ini tidak lagi menerima notifikasi.');
    }

    // ===================== PRATINJAU, UJI, RIWAYAT =====================
    public function pratinjau()
    {
        $raw     = trim((string) $this->request->getGet('tanggal'));
        $ts      = $raw !== '' ? strtotime($raw) : false;
        $tanggal = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        $adminId = (int) $this->adminId();

        NotifJadwal::lupakan();
        $r     = NotifJadwal::rencana($adminId, $tanggal);
        $absen = (new AbsensiGuruModel())->forDate($tanggal);
        $belum = (new AbsensiBelumModel())->forDate($tanggal);
        $log   = new NotifLogModel();
        $now   = time();

        $grup = [];
        foreach ($r['grup'] as $g) {
            $p      = NotifJadwal::susunPesan($g['item'], $absen, $belum);
            $grup[] = [
                'slot'   => $g['slot'],
                'judul'  => $p['judul'],
                'isi'    => $p['isi'],
                'jumlah' => count($g['item']),
                'kosong' => $p['kosong'],
                'status' => $log->sudahAda('jadwal:' . $adminId . ':' . $tanggal . ':' . $g['slot'])
                    ? 'terkirim'
                    : ($g['kirim_ts'] <= $now - NotifJadwal::TOLERANSI_MENIT * 60 ? 'lewat' : 'akan'),
            ];
        }

        return $this->ok([
            'tanggal'  => $tanggal,
            'diam'     => $r['diam'],
            'fcm_siap' => Fcm::siap(),
            'grup'     => $grup,
        ]);
    }

    public function uji()
    {
        if (! Fcm::siap()) {
            return $this->failure('Firebase belum dipasang di server — notifikasi belum bisa dikirim.', 503);
        }
        $adminId = (int) $this->adminId();
        $p       = (new NotifPerangkatModel())->milik($adminId, trim((string) ($this->body()['device_id'] ?? '')));
        if (! $p) {
            return $this->missing('HP ini belum terdaftar untuk notifikasi. Nyalakan dulu "Terima notifikasi di HP ini".');
        }

        $judul = 'Tes notifikasi berhasil';
        $isi   = 'Notifikasi jadwal guru akan muncul seperti ini. ' . date('H:i');
        $data  = ['jenis' => 'uji', 'tanggal' => date('Y-m-d')];
        $logId = (new NotifLogModel())->klaim([
            'admin_id' => $adminId, 'jenis' => 'uji', 'kunci' => 'uji:' . $adminId . ':' . bin2hex(random_bytes(8)),
            'tanggal'  => date('Y-m-d'), 'slot' => date('H:i:s'), 'judul' => $judul, 'isi' => $isi,
            'data'     => $data, 'jml_perangkat' => 1,
        ]);
        $k = NotifJadwal::kirimKePerangkat([$p], $judul, $isi, $data);
        if ($logId !== null) {
            (new NotifLogModel())->update($logId, [
                'berhasil' => $k['berhasil'], 'gagal' => $k['gagal'],
                'galat'    => $k['galat'] !== [] ? implode("\n", $k['galat']) : null,
            ]);
        }

        return $k['berhasil'] > 0
            ? $this->ok(null, 'Notifikasi uji dikirim — cek HP ini.')
            : $this->failure('Gagal mengirim: ' . ($k['galat'][0] ?? 'tidak diketahui'), 502);
    }

    public function riwayat()
    {
        $per   = 30;
        $page  = max(1, (int) $this->request->getGet('page'));
        $model = new NotifLogModel();
        $total = $model->where('admin_id', (int) $this->adminId())->countAllResults(false); // false: filter dipakai lagi
        $rows  = $model->orderBy('id', 'DESC')->findAll($per, ($page - 1) * $per);

        return $this->collection(array_map(static fn ($r) => [
            'id'         => (int) $r['id'],
            'jenis'      => $r['jenis'],
            'tanggal'    => $r['tanggal'],
            'slot'       => $r['slot'] !== null ? substr((string) $r['slot'], 0, 5) : null,
            'judul'      => $r['judul'],
            'isi'        => $r['isi'],
            'perangkat'  => (int) $r['jml_perangkat'],
            'berhasil'   => (int) $r['berhasil'],
            'gagal'      => (int) $r['gagal'],
            'galat'      => $r['galat'],
            'created_at' => $r['created_at'],
        ], $rows), ['page' => $page, 'perPage' => $per, 'total' => $total]);
    }

    // ===================== HELPER =====================

    /** Guru data utama (bukan data ganda), urut nama. */
    private function guruOpsi(): array
    {
        return array_map(static fn ($g) => [
            'id'        => (int) $g['id'],
            'nama'      => $g['nama'],
            'kode_guru' => $g['kode_guru'] ?? null,
        ], (new GuruModel())->select('id, nama, kode_guru')->where('induk_id', null)->orderBy('nama', 'ASC')->findAll());
    }

    /** Jurusan yang dipakai minimal satu kelas aktif. */
    private function jurusanOpsi(): array
    {
        return array_map(static fn ($j) => [
            'id'   => (int) $j['id'],
            'kode' => $j['kode'],
            'nama' => $j['nama'],
        ], (new JurusanModel())
            ->select('jurusan.id, jurusan.kode, jurusan.nama')
            ->join('kelas', 'kelas.jurusan_id = jurusan.id')
            ->where('kelas.deleted_at', null)
            ->distinct()
            ->orderBy('jurusan.kode', 'ASC')
            ->findAll());
    }

    /**
     * Validasi & bentuk data aturan dari body. Id yang tidak dikenal dibuang.
     * `shift` tidak dikirim (aplikasi lama) → pakai $shiftLama (bawaan 'semua').
     *
     * @return array<string,mixed> data siap simpan, atau ['_galat' => [...]]
     */
    private function validasiAturan(array $in, string $shiftLama = 'semua'): array
    {
        $ids = static fn ($v) => array_values(array_unique(array_filter(array_map('intval', is_array($v) ? $v : []), static fn ($x) => $x > 0)));

        $hariValid    = array_map('intval', array_column((new HariModel())->findAll(), 'id'));
        $guruValid    = array_column($this->guruOpsi(), 'id');
        $jurusanValid = array_column($this->jurusanOpsi(), 'id');

        $guruMinta    = $ids($in['guru'] ?? []);
        $jurusanMinta = $ids($in['jurusan'] ?? []);
        $hari         = array_values(array_intersect($ids($in['hari'] ?? []), $hariValid));
        $guru         = array_values(array_intersect($guruMinta, $guruValid));
        $jurusan      = array_values(array_intersect($jurusanMinta, $jurusanValid));
        $menit        = (int) ($in['menit_sebelum'] ?? 5);
        $nama         = trim((string) ($in['nama'] ?? ''));
        $shift        = array_key_exists('shift', $in) ? strtolower(trim((string) $in['shift'])) : $shiftLama;

        $galat = [];
        if ($hari === []) {
            $galat['hari'] = 'Pilih minimal satu hari.';
        }
        if (! isset(NotifAturanModel::SHIFT[$shift])) {
            $galat['shift'] = 'Pilihan shift tidak valid.';
        }
        // Daftar kosong berarti "semua" — jangan sampai pilihan yang semuanya
        // tidak dikenal (mis. guru sudah dihapus) diam-diam jadi SEMUA guru.
        if ($guruMinta !== [] && $guru === []) {
            $galat['guru'] = 'Guru yang dipilih tidak ditemukan (mungkin sudah dihapus). Pilih ulang guru.';
        }
        if ($jurusanMinta !== [] && $jurusan === []) {
            $galat['jurusan'] = 'Jurusan yang dipilih tidak ditemukan. Pilih ulang jurusan.';
        }
        if (! in_array($menit, NotifAturanModel::MENIT, true)) {
            $galat['menit_sebelum'] = 'Pilihan waktu notifikasi tidak valid.';
        }
        if (mb_strlen($nama) > 100) {
            $galat['nama'] = 'Nama aturan maksimal 100 karakter.';
        }
        if ($galat !== []) {
            return ['_galat' => $galat];
        }

        return [
            'nama'          => $nama !== '' ? $nama : 'Aturan notifikasi',
            'hari'          => json_encode($hari),
            'shift'         => $shift,
            'guru'          => json_encode($guru),
            'jurusan'       => json_encode($jurusan),
            'menit_sebelum' => $menit,
            'aktif'         => $this->bool($in['aktif'] ?? true) ? 1 : 0,
        ];
    }

    /** Aturan rapi + label siap tampil di aplikasi. */
    private function labelAturan(array $a): array
    {
        static $hari = null, $jurusan = null;
        $hari    ??= array_column(array_map(static fn ($h) => ['id' => (int) $h['id'], 'nama' => ucwords(strtolower((string) $h['nama']))], (new HariModel())->orderBy('urutan', 'ASC')->findAll()), 'nama', 'id');
        $jurusan ??= array_column($this->jurusanOpsi(), 'kode', 'id');

        $namaHari = array_values(array_filter(array_map(static fn ($id) => $hari[$id] ?? null, array_keys($hari)), static fn ($n) => $n !== null));
        $pilihHari = array_values(array_filter(array_map(static fn ($id, $n) => in_array($id, $a['hari'], true) ? $n : null, array_keys($hari), $hari)));

        return $a + [
            'hari_label'    => count($pilihHari) === count($namaHari) ? 'Setiap hari KBM' : implode(', ', $pilihHari),
            'shift_label'   => 'KBM ' . strtolower(NotifAturanModel::SHIFT[$a['shift']]),
            'guru_label'    => $a['guru'] === [] ? 'Semua guru' : count($a['guru']) . ' guru',
            'jurusan_label' => $a['jurusan'] === [] ? 'Semua jurusan' : implode(', ', array_map(static fn ($id) => $jurusan[$id] ?? ('#' . $id), $a['jurusan'])),
            'menit_label'   => $a['menit_sebelum'] === 0 ? 'Tepat saat jam masuk' : $a['menit_sebelum'] . ' menit sebelum',
        ];
    }

    private function bool($v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on', 'ya'], true);
    }
}
