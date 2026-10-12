<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\UkkAlur;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Jadwal UKK + penugasan penguji (API, fase UKK A2). Cermin App\Controllers\Admin\JadwalUkk.
 * Rute /api/v1/admin/jadwal-ukk:
 *   GET ?q=&paket_soal_id=&tahun_ajaran_id=&per=&page= · POST · POST {id} · DELETE {id} · POST bulk-delete
 *   GET {id}/penguji · POST {id}/penguji {tipe, guru_id | penguji_eksternal_id, peran} · DELETE {id}/penguji/{pid}
 */
class JadwalUkk extends BaseCrud
{
    protected string $module     = 'jadwal_ukk';
    protected string $auditTable = 'jadwal_ukk';
    protected string $entity     = 'jadwal UKK';

    protected function makeModel(): Model
    {
        return new JadwalUkkModel();
    }

    /** + jumlah penguji & peserta per jadwal (subquery, tanpa N+1). */
    protected function baseBuilder()
    {
        return $this->model->withRelations()
            ->select('(SELECT COUNT(*) FROM jadwal_ukk_penguji jp WHERE jp.jadwal_ukk_id = jadwal_ukk.id) AS jumlah_penguji', false)
            ->select('(SELECT COUNT(*) FROM peserta_ukk ps WHERE ps.jadwal_ukk_id = jadwal_ukk.id AND ps.deleted_at IS NULL) AS jumlah_peserta', false);
    }

    protected function applyFilters($builder)
    {
        $q       = trim((string) $this->request->getGet('q'));
        $paketId = (int) $this->request->getGet('paket_soal_id');
        $tahunId = (int) $this->request->getGet('tahun_ajaran_id');
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('paket_soal_ukk.nama', $q)->orLike('paket_soal_ukk.kode', $q)->orLike('tempat_uji.nama', $q)->groupEnd();
        }
        if ($paketId > 0) {
            $builder = $builder->where('jadwal_ukk.paket_soal_id', $paketId);
        }
        if ($tahunId > 0) {
            $builder = $builder->where('jadwal_ukk.tahun_ajaran_id', $tahunId);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('jadwal_ukk.tanggal_mulai', 'DESC')->orderBy('jadwal_ukk.id', 'DESC');
    }

    /** Sama dengan web: tanggal mulai kosong = hari ini. */
    protected function collect(array $in): array
    {
        $teks = static fn (string $k): string => trim((string) ($in[$k] ?? ''));

        return [
            'paket_soal_id'   => (int) ($in['paket_soal_id'] ?? 0) ?: null,
            'tempat_uji_id'   => (int) ($in['tempat_uji_id'] ?? 0) ?: null,
            'tahun_ajaran_id' => (int) ($in['tahun_ajaran_id'] ?? 0) ?: null,
            'tanggal_mulai'   => $teks('tanggal_mulai') ?: date('Y-m-d'),
            'tanggal_selesai' => $teks('tanggal_selesai') ?: null,
            'sesi'            => $teks('sesi') ?: null,
            'keterangan'      => $teks('keterangan') ?: null,
        ];
    }

    protected function periksa(array $payload): array
    {
        $err = $this->rujukanHilang($payload, [
            'paket_soal_id'   => ['paket_soal_ukk', 'Paket soal'],
            'tempat_uji_id'   => ['tempat_uji', 'Tempat uji'],
            'tahun_ajaran_id' => ['tahun_ajaran', 'Tahun ajaran'],
        ]);
        foreach (['tanggal_mulai', 'tanggal_selesai'] as $k) {
            if ($payload[$k] !== null && ! UkkAlur::tanggalSah($payload[$k])) {
                $err[$k] = 'Format tanggal harus TTTT-BB-HH.';
            }
        }
        if (! isset($err['tanggal_mulai']) && ! isset($err['tanggal_selesai']) && $payload['tanggal_selesai'] !== null
            && $payload['tanggal_selesai'] < $payload['tanggal_mulai']) {
            $err['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        return $err;
    }

    protected function transform(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'paket_soal_id'   => (int) $r['paket_soal_id'],
            'paket_kode'      => $r['paket_kode'] ?? null,
            'paket_nama'      => $r['paket_nama'] ?? null,
            'tempat_uji_id'   => ((int) ($r['tempat_uji_id'] ?? 0)) ?: null,
            'tempat_nama'     => $r['tempat_nama'] ?? null,
            'tahun_ajaran_id' => ((int) ($r['tahun_ajaran_id'] ?? 0)) ?: null,
            'tanggal_mulai'   => $r['tanggal_mulai'],
            'tanggal_selesai' => $r['tanggal_selesai'] ?? null,
            'sesi'            => $r['sesi'] ?? null,
            'keterangan'      => $r['keterangan'] ?? null,
            'jumlah_penguji'  => (int) ($r['jumlah_penguji'] ?? 0),
            'jumlah_peserta'  => (int) ($r['jumlah_peserta'] ?? 0),
        ];
    }

    /**
     * Sama dengan web: penugasan penguji dihapus total (tak ada riwayat berdiri sendiri); peserta yang sudah
     * terdaftar TETAP ada, hanya lepas rujukan jadwalnya.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal_ukk_penguji')->whereIn('jadwal_ukk_id', $ids)->delete();
        $db->table('peserta_ukk')->whereIn('jadwal_ukk_id', $ids)->update(['jadwal_ukk_id' => null]);
    }

    protected function freshRow(int $id): array
    {
        return $this->baseBuilder()->where('jadwal_ukk.id', $id)->first() ?? [];
    }

    // ===================== Penugasan penguji =====================

    /** GET {id}/penguji — jadwal + daftar penguji tertugas. */
    public function penguji($id = null): ResponseInterface
    {
        $jadwal = $this->freshRow((int) $id);
        if ($jadwal === []) {
            return $this->missing('Jadwal tidak ditemukan.');
        }

        return $this->ok([
            'jadwal'  => $this->transform($jadwal),
            'penguji' => array_map([self::class, 'pengujiData'], (new JadwalUkkPengujiModel())->forJadwal((int) $id)),
        ]);
    }

    /** POST {id}/penguji — tugaskan guru (internal) atau penguji eksternal; ganda ditolak 409. */
    public function pengujiStore($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Jadwal tidak ditemukan.');
        }
        $in    = $this->body();
        $tipe  = strtolower(trim((string) ($in['tipe'] ?? '')));
        $peran = strtolower(trim((string) ($in['peran'] ?? ''))) ?: 'anggota';
        if (! in_array($tipe, ['internal', 'eksternal'], true)) {
            return $this->invalid(['tipe' => 'Tipe penguji harus internal atau eksternal.']);
        }
        if (! in_array($peran, ['ketua', 'anggota'], true)) {
            return $this->invalid(['peran' => 'Peran harus ketua atau anggota.']);
        }
        $guruId = $tipe === 'internal' ? ((int) ($in['guru_id'] ?? 0) ?: null) : null;
        $eksId  = $tipe === 'eksternal' ? ((int) ($in['penguji_eksternal_id'] ?? 0) ?: null) : null;
        if ($tipe === 'internal' && ! $guruId) {
            return $this->invalid(['guru_id' => 'Pilih guru untuk penguji internal.']);
        }
        if ($tipe === 'eksternal' && ! $eksId) {
            return $this->invalid(['penguji_eksternal_id' => 'Pilih penguji eksternal.']);
        }
        $hilang = $this->rujukanHilang(
            ['guru_id' => $guruId, 'penguji_eksternal_id' => $eksId],
            ['guru_id' => ['guru', 'Guru'], 'penguji_eksternal_id' => ['penguji_eksternal', 'Penguji eksternal']]
        );
        if ($hilang !== []) {
            return $this->invalid($hilang);
        }

        $model = new JadwalUkkPengujiModel();
        $dup   = $model->where('jadwal_ukk_id', $id)->where('tipe', $tipe);
        $dup   = $tipe === 'internal' ? $dup->where('guru_id', $guruId) : $dup->where('penguji_eksternal_id', $eksId);
        if ($dup->countAllResults() > 0) {
            return $this->failure('Penguji ini sudah ditugaskan pada jadwal ini.', 409);
        }

        $data = ['jadwal_ukk_id' => $id, 'tipe' => $tipe, 'guru_id' => $guruId, 'penguji_eksternal_id' => $eksId, 'peran' => $peran];
        if (! $model->insert($data)) {
            return $this->invalid($model->errors());
        }
        $pid = (int) $model->getInsertID();
        $this->audit->record('create', 'jadwal_ukk_penguji', $pid, 'Tugaskan penguji ' . $tipe . ' pada jadwal UKK #' . $id . ' (via mobile)');

        $baris = array_values(array_filter((new JadwalUkkPengujiModel())->forJadwal($id), static fn ($p) => (int) $p['id'] === $pid));

        return $this->created(self::pengujiData($baris[0]), 'Penguji ditugaskan.');
    }

    /** DELETE {id}/penguji/{pid} — lepas penugasan (harus milik jadwal ini). */
    public function pengujiHapus($id = null, $pengujiId = null): ResponseInterface
    {
        $id        = (int) $id;
        $pengujiId = (int) $pengujiId;
        $model     = new JadwalUkkPengujiModel();
        if (! $model->where('jadwal_ukk_id', $id)->where('id', $pengujiId)->first()) {
            return $this->missing('Penugasan penguji tidak ditemukan pada jadwal ini.');
        }
        $model->delete($pengujiId);
        $this->audit->record('delete', 'jadwal_ukk_penguji', $pengujiId, 'Lepas penugasan penguji dari jadwal UKK #' . $id . ' (via mobile)');

        return $this->ok(null, 'Penugasan penguji dihapus.');
    }

    /**
     * Bentuk satu penugasan (JadwalUkkPengujiModel::forJadwal) untuk klien. 'kunci' = "{tipe}-{id penguji}",
     * dipakai mencocokkan kolom penguji dengan sel nilai di matriks penilaian.
     */
    public static function pengujiData(array $p): array
    {
        $internal = $p['tipe'] === 'internal';
        $orang    = (int) ($internal ? $p['guru_id'] : $p['penguji_eksternal_id']);

        return [
            'id'                   => (int) $p['id'],
            'kunci'                => $p['tipe'] . '-' . $orang,
            'tipe'                 => $p['tipe'],
            'peran'                => $p['peran'],
            'guru_id'              => $internal ? $orang : null,
            'penguji_eksternal_id' => $internal ? null : $orang,
            'nama'                 => $internal ? ($p['guru_nama'] ?? null) : ($p['eksternal_nama'] ?? null),
            'kode_guru'            => $internal ? ($p['kode_guru'] ?? null) : null,
            'instansi'             => $internal ? null : ($p['eksternal_instansi'] ?? null),
        ];
    }
}
