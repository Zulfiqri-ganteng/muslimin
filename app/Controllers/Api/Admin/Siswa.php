<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\BiodataForm;
use App\Libraries\SiswaDetail;
use App\Libraries\SiswaStatus;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Siswa (API). Cermin App\Controllers\Admin\Master\Siswa.
 * Rute: /api/v1/admin/master/siswa   (?q= &kelas_id= &tingkat= &status= &biodata=lengkap|belum)
 *
 * Tingkat & jurusan TIDAK disimpan di tabel siswa — keduanya ikut kelas
 * lewat join, jadi memindah kelas tak pernah meninggalkan data tak sinkron.
 *
 * Kolom biodata buku induk (SiswaModel::KOLOM_BIODATA) hanya ditulis bila
 * DIKIRIM klien — aplikasi versi lama yang belum mengenalnya tetap aman:
 * menyimpan siswa dari sana tidak menghapus biodata yang sudah diisi.
 */
class Siswa extends BaseCrud
{
    protected string $module     = 'siswa';
    protected string $auditTable = 'siswa';
    protected string $entity     = 'siswa';

    protected function makeModel(): Model
    {
        return new SiswaModel();
    }

    protected function baseBuilder()
    {
        // ?status=terhapus = HANYA data yang dulu dihapus dengan cara lama (hapus lunak) — untuk Pulihkan / Hapus permanen.
        $terhapus = strtolower(trim((string) $this->request->getGet('status'))) === 'terhapus';

        return ($terhapus ? $this->model->onlyDeleted() : $this->model)->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q       = trim((string) $this->request->getGet('q'));
        $tanpaKelas = strtolower(trim((string) $this->request->getGet('kelas_id'))) === 'tanpa'; // siswa yang belum ditempatkan di kelas
        $kelasId = (int) $this->request->getGet('kelas_id');
        $tingkat = strtoupper(trim((string) $this->request->getGet('tingkat')));
        $status  = strtolower(trim((string) $this->request->getGet('status')));

        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('siswa.nama', $q)->orLike('siswa.nis', $q)->orLike('siswa.nisn', $q)
                ->groupEnd();
        }
        if ($kelasId > 0) {
            $builder = $builder->where('siswa.kelas_id', $kelasId);
        } elseif ($tanpaKelas) {
            $builder = $builder->where('siswa.kelas_id', null);
        }
        if (in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if (in_array($status, SiswaModel::STATUS, true)) {
            $builder = $builder->where('siswa.status', $status);
        } elseif ($status === 'nonaktif') { // semua yang tidak aktif: lulus + pindah + keluar
            $builder = $builder->whereIn('siswa.status', array_keys(SiswaStatus::NONAKTIF));
        }
        // Sama dengan saringan "Biodata lengkap / belum" di web (dari biodata_at).
        $biodata = strtolower(trim((string) $this->request->getGet('biodata')));
        if ($biodata === 'lengkap') {
            $builder = $builder->where('siswa.biodata_at IS NOT NULL');
        } elseif ($biodata === 'belum') {
            $builder = $builder->where('siswa.biodata_at', null);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('siswa.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $teks = static fn (string $k) => trim((string) ($in[$k] ?? ''));

        $jk     = strtoupper($teks('jenis_kelamin'));
        $status = strtolower($teks('status'));

        return $this->collectBiodata($in, $teks) + $this->collectSekolah($in, $teks) + [
            'nis' => $teks('nis'),
            // NISN unik tapi boleh kosong → string kosong WAJIB jadi NULL,
            // kalau tidak siswa kedua tanpa NISN akan bentrok unique key.
            'nisn'          => $teks('nisn') ?: null,
            'nama'          => $teks('nama'),
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
            'tempat_lahir'  => $teks('tempat_lahir') ?: null,
            'tanggal_lahir' => $this->parseTanggal($teks('tanggal_lahir')),
            'agama'         => $teks('agama') ?: null,
            'alamat'        => $teks('alamat') ?: null,
            'no_hp'         => $teks('no_hp') ?: null,
            'nama_wali'     => $teks('nama_wali') ?: null,
            'no_hp_wali'    => $teks('no_hp_wali') ?: null,
            'kelas_id'      => (int) ($in['kelas_id'] ?? 0) ?: null,
            'tahun_masuk'   => (int) ($in['tahun_masuk'] ?? 0) ?: null,
            'status'        => in_array($status, SiswaModel::STATUS, true) ? $status : 'aktif',
            'keterangan'    => $teks('keterangan') ?: null,
        ];
    }

    /**
     * Kolom biodata yang ADA di kiriman saja (lihat catatan kelas).
     * Kosong/null yang dikirim eksplisit = dikosongkan.
     */
    private function collectBiodata(array $in, callable $teks): array
    {
        $data = [];
        foreach (SiswaModel::KOLOM_BIODATA as $k) {
            if (! array_key_exists($k, $in)) {
                continue;
            }
            $v        = $teks($k);
            $data[$k] = match ($k) {
                'anak_ke'          => ctype_digit($v) && (int) $v >= 1 && (int) $v <= 99 ? (int) $v : null,
                'diterima_tanggal' => $this->parseTanggal($v),
                default            => $v !== '' ? $v : null,
            };
        }

        return $data;
    }

    /**
     * Kolom Format 8355 (SiswaModel::KOLOM_SEKOLAH) — sama seperti biodata: hanya yang DIKIRIM klien yang
     * ditulis, sehingga aplikasi versi lama tidak menghapusnya saat menyimpan siswa.
     */
    private function collectSekolah(array $in, callable $teks): array
    {
        $data = [];
        foreach (SiswaModel::KOLOM_SEKOLAH as $k) {
            if (! array_key_exists($k, $in)) {
                continue;
            }
            $v        = $teks($k);
            $data[$k] = $k === 'sttb_tahun'
                ? (ctype_digit($v) && (int) $v >= 1990 && (int) $v <= 2100 ? (int) $v : null)
                : ($v !== '' ? $v : null);
        }

        return $data;
    }

    /** Terima Y-m-d maupun dd/mm/yyyy agar klien tidak mudah gagal simpan. */
    private function parseTanggal(string $nilai): ?string
    {
        if ($nilai === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $nilai);
            if ($dt !== false) {
                $err = \DateTimeImmutable::getLastErrors();
                if (! $err || (($err['warning_count'] ?? 0) === 0 && ($err['error_count'] ?? 0) === 0)) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        return null;
    }

    protected function transform(array $r): array
    {
        $biodata = [];
        foreach (SiswaModel::KOLOM_BIODATA as $k) {
            $biodata[$k] = $r[$k] ?? null;
        }
        $biodata['anak_ke']    = isset($r['anak_ke']) ? (int) $r['anak_ke'] : null;
        $biodata['biodata_at'] = $r['biodata_at'] ?? null;
        // Lengkap = 27 kolom wajib form isian terisi (ukuran yang sama dengan laporan Excel).
        $kurang                     = BiodataForm::kolomKosong($r);
        $biodata['biodata_lengkap'] = $kurang === [];
        $biodata['biodata_kurang']  = $kurang;

        // Format 8355 — kunci BARU (tambahan; klien lama mengabaikannya).
        $sekolah = [];
        foreach (SiswaModel::KOLOM_SEKOLAH as $k) {
            $sekolah[$k] = $r[$k] ?? null;
        }
        $sekolah['sttb_tahun'] = isset($r['sttb_tahun']) ? (int) $r['sttb_tahun'] : null;

        // Status jelas (2026-10-29): label tampil + aktif/terhapus (kunci tambahan; klien lama mengabaikannya).
        $statusInfo = ['status_label' => SiswaStatus::label((string) ($r['status'] ?? 'aktif')), 'aktif' => ($r['status'] ?? 'aktif') === 'aktif',
            'terhapus' => ! empty($r['deleted_at'])];

        return $this->transformInti($r) + $biodata + $sekolah + $statusInfo;
    }

    /** Kolom inti (bentuk lama — tidak diubah agar klien lama tetap cocok). */
    private function transformInti(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'nis'           => $r['nis'],
            'nisn'          => $r['nisn'] ?? null,
            'nama'          => $r['nama'],
            'jenis_kelamin' => $r['jenis_kelamin'] ?? null,
            'tempat_lahir'  => $r['tempat_lahir'] ?? null,
            'tanggal_lahir' => $r['tanggal_lahir'] ?? null,
            'agama'         => $r['agama'] ?? null,
            'alamat'        => $r['alamat'] ?? null,
            'no_hp'         => $r['no_hp'] ?? null,
            'nama_wali'     => $r['nama_wali'] ?? null,
            'no_hp_wali'    => $r['no_hp_wali'] ?? null,
            'kelas_id'      => ((int) ($r['kelas_id'] ?? 0)) ?: null,
            // Ikut kelas (hanya ada bila daftar diambil lewat withRelations)
            'nama_kelas'    => $r['nama_kelas'] ?? null,
            'tingkat'       => $r['tingkat'] ?? null,
            'jurusan_kode'  => $r['jurusan_kode'] ?? null,
            'tahun_masuk'   => ((int) ($r['tahun_masuk'] ?? 0)) ?: null,
            'status'        => $r['status'] ?? 'aktif',
            'keterangan'    => $r['keterangan'] ?? null,
        ];
    }

    /**
     * Baris segar sesudah tulis diambil lewat relasi supaya klien langsung
     * menerima nama kelas/tingkat/jurusan tanpa perlu memuat ulang daftar.
     */
    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('siswa.id', $id)->first() ?? [];
    }

    /**
     * GET .../siswa/{id} — DETAIL LENGKAP satu siswa (sama dengan halaman web "Detail Siswa").
     * Kunci-kunci datar lama + `jurusan_nama`, lalu: `bagian` (judul → baris {kunci,label,nilai} yang SUDAH diformat
     * tampil), `biodata` (disahkan_pada, kurang[], isian), `pkl` (ajuan aktif atau null), `jejak`.
     */
    public function show($id = null): ResponseInterface
    {
        $d = SiswaDetail::muat((int) $id);
        if ($d === null) {
            return $this->missing('Siswa tidak ditemukan (mungkin sudah dihapus).');
        }
        $bagian = array_map(static fn (array $b): array => [
            'judul' => $b[0],
            'baris' => array_map(static fn (array $x): array => ['kunci' => $x[0], 'label' => $x[1], 'nilai' => $x[2]], $b[1]),
        ], $d['bagian']);

        $terkait = SiswaStatus::keterkaitan([(int) $id])[(int) $id] ?? [];

        return $this->ok($this->transform($d['siswa']) + [
            'jurusan_nama' => $d['siswa']['jurusan_nama'] ?? null,
            'bagian' => $bagian, 'biodata' => $d['biodata'], 'pkl' => $d['pkl'], 'jejak' => $d['jejak'],
            // SiswaStatus (2026-10-29): data terkait & boleh tidaknya hapus permanen.
            'terkait' => array_map(static fn (string $l, int $n): array => ['label' => $l, 'jumlah' => $n], array_keys($terkait), $terkait),
            'bisa_hapus_permanen' => $terkait === [],
        ], 'Detail siswa.');
    }

    // ===================== STATUS & HAPUS (SiswaStatus, 2026-10-29) =====================

    /** DELETE .../siswa/{id} — HAPUS PERMANEN; 409 + data.terkait bila siswa sudah punya data terkait (nonaktifkan saja). */
    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        $r  = SiswaStatus::hapusPermanen([$id], $this->oleh());
        if ($r['dihapus'] !== []) {
            return $this->ok(null, SiswaStatus::pesanHapus($r));
        }
        if ($r['ditolak'] === []) {
            return $this->missing('Siswa tidak ditemukan.');
        }
        $terkait = SiswaStatus::keterkaitan([$id])[$id] ?? [];

        return $this->failure(SiswaStatus::pesanHapus($r), 409, [
            'terkait' => array_map(static fn (string $l, int $n): array => ['label' => $l, 'jumlah' => $n], array_keys($terkait), $terkait),
        ]);
    }

    /** POST .../siswa/bulk-delete {ids:[…]} — hapus permanen yang tanpa data terkait; mode "semua" tidak tersedia. */
    public function bulkDestroy(): ResponseInterface
    {
        $in = $this->body();
        if (($in['mode'] ?? '') === 'all') {
            return $this->failure('Hapus semua siswa tidak tersedia. Kirim ids siswa yang akan dihapus.', 422);
        }
        $ids = array_values(array_filter(array_map('intval', (array) ($in['ids'] ?? []))));
        if ($ids === []) {
            return $this->invalid(['ids' => 'Pilih minimal satu siswa.']);
        }
        $r = SiswaStatus::hapusPermanen($ids, $this->oleh());

        return $this->ok(['deleted' => count($r['dihapus']), 'dihapus' => count($r['dihapus']), 'ditolak' => $r['ditolak']], SiswaStatus::pesanHapus($r));
    }

    /** NIS/NISN bentrok → pesan jelas (termasuk pemilik yang sudah terhapus dengan cara lama). */
    protected function periksa(array $payload): array
    {
        $galat = [];
        foreach (['nis', 'nisn'] as $k) {
            if (array_key_exists($k, $payload) && ($p = SiswaStatus::pesanBentrok($k, $payload[$k], (int) ($payload['id'] ?? 0))) !== null) {
                $galat[$k] = $p;
            }
        }

        return $galat;
    }

    /** POST .../siswa/{id}/status {status: aktif|lulus|pindah|keluar, catatan?} — nonaktifkan / aktifkan kembali. */
    public function status($id = null): ResponseInterface
    {
        $in     = $this->body();
        $status = strtolower(trim((string) ($in['status'] ?? '')));
        if ($status !== 'aktif' && ! isset(SiswaStatus::NONAKTIF[$status])) {
            return $this->invalid(['status' => 'Pilih aktif, lulus, pindah, atau keluar.']);
        }
        $r = $status === 'aktif' ? SiswaStatus::aktifkan((int) $id, $this->oleh())
            : SiswaStatus::nonaktifkan((int) $id, $status, (string) ($in['catatan'] ?? ''), $this->oleh());
        if (! $r['ok']) {
            return $this->missing($r['pesan']);
        }

        return $this->ok($this->transform($this->freshRow((int) $id)), $r['pesan']);
    }

    /** POST .../siswa/{id}/pulihkan — data terhapus lama dikembalikan (status semula). */
    public function pulihkan($id = null): ResponseInterface
    {
        $r = SiswaStatus::pulihkan((int) $id, $this->oleh());

        return $r['ok'] ? $this->ok($this->transform($this->freshRow((int) $id)), $r['pesan']) : $this->missing($r['pesan']);
    }

    private function oleh(): string
    {
        $a = $this->admin() ?? [];

        return (string) (($a['full_name'] ?? '') ?: ($a['username'] ?? 'Admin')) . ' (via mobile)';
    }

    /** Ringkasan agregat untuk dashboard admin: GET .../siswa/statistik */
    public function statistik(): ResponseInterface
    {
        return $this->ok((new SiswaModel())->statistik());
    }
}
