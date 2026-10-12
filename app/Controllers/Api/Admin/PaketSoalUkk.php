<?php

namespace App\Controllers\Api\Admin;

use App\Models\PaketSoalUkkModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Paket Soal UKK (API, fase UKK A1). Cermin App\Controllers\Admin\Master\PaketSoalUkk:
 * bobot 5 komponen penilaian (default 10/30/40/10/10) WAJIB berjumlah 100, KKM, dan dua berkas PDF resmi
 * (kisi-kisi & jobsheet) di public/uploads/ukk — alamatnya publik (kisi_kisi_url / jobsheet_url), dibuka tanpa token.
 *
 * Rute: /api/v1/admin/master/paket-soal-ukk (index ?q=&jurusan_id=&tahun_ajaran_id=&per=&page= · store · update ·
 * destroy · bulk-delete) + POST/DELETE …/{id}/berkas/{kisi-kisi|jobsheet} (unggah PDF multipart "berkas" / hapus).
 */
class PaketSoalUkk extends BaseCrud
{
    protected string $module     = 'paket_soal_ukk';
    protected string $auditTable = 'paket_soal_ukk';
    protected string $entity     = 'paket soal';

    /** slug alamat → kolom tabel */
    private const BERKAS = ['kisi-kisi' => 'kisi_kisi_file', 'jobsheet' => 'jobsheet_file'];

    public function __construct()
    {
        parent::__construct();
        helper('ukkdoc');
    }

    protected function makeModel(): Model
    {
        return new PaketSoalUkkModel();
    }

    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q       = trim((string) $this->request->getGet('q'));
        $jurusan = (int) $this->request->getGet('jurusan_id');
        $tahun   = (int) $this->request->getGet('tahun_ajaran_id');
        if ($q !== '') {
            $builder = $builder->groupStart()->like('paket_soal_ukk.nama', $q)->orLike('paket_soal_ukk.kode', $q)->groupEnd();
        }
        if ($jurusan > 0) {
            $builder = $builder->where('paket_soal_ukk.jurusan_id', $jurusan);
        }
        if ($tahun > 0) {
            $builder = $builder->where('paket_soal_ukk.tahun_ajaran_id', $tahun);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('paket_soal_ukk.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $angka = static fn (string $k, float $bawaan): float => isset($in[$k]) && trim((string) $in[$k]) !== '' ? (float) $in[$k] : $bawaan;

        return [
            'kode'            => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'            => trim((string) ($in['nama'] ?? '')),
            'jurusan_id'      => (int) ($in['jurusan_id'] ?? 0) ?: null,
            'tahun_ajaran_id' => (int) ($in['tahun_ajaran_id'] ?? 0) ?: null,
            'deskripsi'       => trim((string) ($in['deskripsi'] ?? '')) ?: null,
            'bobot_persiapan' => $angka('bobot_persiapan', 10),
            'bobot_proses'    => $angka('bobot_proses', 30),
            'bobot_hasil'     => $angka('bobot_hasil', 40),
            'bobot_sikap'     => $angka('bobot_sikap', 10),
            'bobot_waktu'     => $angka('bobot_waktu', 10),
            'kkm'             => $angka('kkm', 70),
            'keterangan'      => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        $bobot = [
            'persiapan' => (float) $r['bobot_persiapan'], 'proses' => (float) $r['bobot_proses'], 'hasil' => (float) $r['bobot_hasil'],
            'sikap' => (float) $r['bobot_sikap'], 'waktu' => (float) $r['bobot_waktu'],
        ];

        return [
            'id'                 => (int) $r['id'],
            'kode'               => $r['kode'],
            'nama'               => $r['nama'],
            'jurusan_id'         => ((int) ($r['jurusan_id'] ?? 0)) ?: null,
            'jurusan_nama'       => $r['jurusan_nama'] ?? null,
            'jurusan_kode'       => $r['jurusan_kode'] ?? null,
            'tahun_ajaran_id'    => ((int) ($r['tahun_ajaran_id'] ?? 0)) ?: null,
            'tahun_ajaran_label' => ! empty($r['tahun_ajaran_tahun']) ? $r['tahun_ajaran_tahun'] . ' ' . ($r['tahun_ajaran_semester'] ?? '') : null,
            'deskripsi'          => $r['deskripsi'] ?? null,
            'bobot'              => $bobot,
            'total_bobot'        => round(array_sum($bobot), 2),
            'kkm'                => (float) $r['kkm'],
            'kisi_kisi_url'      => ukkdoc_url($r['kisi_kisi_file'] ?? null),
            'jobsheet_url'       => ukkdoc_url($r['jobsheet_file'] ?? null),
            'keterangan'         => $r['keterangan'] ?? null,
        ];
    }

    /** Sama dengan web: total bobot 5 komponen wajib 100% (+ jurusan/tahun ajaran harus ada). */
    protected function periksa(array $payload): array
    {
        $err   = $this->rujukanHilang($payload, ['jurusan_id' => ['jurusan', 'Jurusan'], 'tahun_ajaran_id' => ['tahun_ajaran', 'Tahun ajaran']]);
        $total = $this->model->totalBobot($payload);
        if (abs($total - 100) > 0.01) {
            $err['bobot'] = 'Total bobot 5 komponen harus 100% (saat ini ' . round($total, 2) . '%).';
        }

        return $err;
    }

    /** POST …/{id}/berkas/{kisi-kisi|jobsheet} — unggah/ganti PDF (multipart, kolom "berkas", maks 10 MB). */
    public function unggahBerkas($id = null, string $jenis = ''): ResponseInterface
    {
        $id    = (int) $id;
        $field = self::BERKAS[$jenis] ?? null;
        $row   = $this->model->find($id);
        if ($row === null || $field === null) {
            return $this->missing($row === null ? 'Paket soal tidak ditemukan.' : 'Jenis berkas harus kisi-kisi atau jobsheet.');
        }
        $file = $this->request->getFile('berkas');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->invalid(['berkas' => 'Pilih berkas PDF yang akan diunggah (kolom "berkas").']);
        }
        $error = null; // ukkdoc_save menolak unggahan rusak/terlalu besar/bukan PDF dengan alasannya
        $baru  = ukkdoc_save($file, $error);
        if ($baru === null) {
            return $this->invalid(['berkas' => (string) $error]);
        }
        ukkdoc_delete($row[$field] ?? null);
        $this->model->update($id, [$field => $baru]);
        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, 'Unggah berkas ' . $jenis . ' paket soal ' . $row['nama'] . ' (via mobile)');

        return $this->ok($this->transform($this->freshRow($id)), 'Berkas ' . $jenis . ' diunggah.');
    }

    /** DELETE …/{id}/berkas/{kisi-kisi|jobsheet} — hapus PDF (paket soal tetap ada). */
    public function hapusBerkas($id = null, string $jenis = ''): ResponseInterface
    {
        $id    = (int) $id;
        $field = self::BERKAS[$jenis] ?? null;
        $row   = $this->model->find($id);
        if ($row === null || $field === null) {
            return $this->missing($row === null ? 'Paket soal tidak ditemukan.' : 'Jenis berkas harus kisi-kisi atau jobsheet.');
        }
        ukkdoc_delete($row[$field] ?? null);
        $this->model->update($id, [$field => null]);
        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, 'Hapus berkas ' . $jenis . ' paket soal ' . $row['nama'] . ' (via mobile)');

        return $this->ok($this->transform($this->freshRow($id)), 'Berkas ' . $jenis . ' dihapus.');
    }

    /**
     * Sama dengan web: paket soal soft delete — jadwal & peserta lama SENGAJA tidak dibersihkan (riwayat UKK tetap);
     * hanya berkas fisik yang dihapus agar tak menumpuk.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        foreach ($this->model->whereIn('id', $ids)->withDeleted()->findAll() as $r) {
            ukkdoc_delete($r['kisi_kisi_file'] ?? null);
            ukkdoc_delete($r['jobsheet_file'] ?? null);
        }
    }

    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('paket_soal_ukk.id', $id)->first() ?? [];
    }
}
