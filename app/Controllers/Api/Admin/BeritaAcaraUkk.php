<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UkkAlur;
use App\Models\AuditModel;
use App\Models\BeritaAcaraUkkModel;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\PesertaUkkModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Berita Acara UKK (API, fase UKK A2) — kelola DATA saja; cetak PDF tetap di web (keputusan 2026-08-27), alamatnya
 * disertakan sebagai 'pdf_web' (dibuka di peramban, perlu login web). Cermin App\Controllers\Admin\BeritaAcaraUkk.
 * Rute /api/v1/admin/berita-acara-ukk:
 *   GET ?q=&jadwal_ukk_id=&per=&page= · GET {id} (+ peserta & penguji jadwalnya = isi PDF)
 *   POST {jadwal_ukk_id, tanggal?, catatan?, keterangan?} (nomor otomatis "BA-UKK-{tahun}-001")
 *   POST {id} {tanggal?, catatan?, keterangan?} (ganti semua isian, sama dengan form web) · DELETE {id}
 */
class BeritaAcaraUkk extends BaseApiController
{
    protected BeritaAcaraUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new BeritaAcaraUkkModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q        = trim((string) $this->request->getGet('q'));
        $jadwalId = (int) $this->request->getGet('jadwal_ukk_id');

        $b = $this->model->withRelations();
        if ($q !== '') {
            $b = $b->groupStart()->like('berita_acara_ukk.nomor_ba', $q)->orLike('paket_soal_ukk.nama', $q)->groupEnd();
        }
        if ($jadwalId > 0) {
            $b = $b->where('berita_acara_ukk.jadwal_ukk_id', $jadwalId);
        }
        $rows  = $b->orderBy('berita_acara_ukk.tanggal', 'DESC')->orderBy('berita_acara_ukk.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        return $this->collection(array_map([self::class, 'data'], $rows), ['page' => $page, 'perPage' => $per, 'total' => $total]);
    }

    /** GET detail + daftar peserta & penguji jadwalnya (isi yang tercetak di PDF). */
    public function show($id = null): ResponseInterface
    {
        $ba = $this->model->withRelations()->where('berita_acara_ukk.id', (int) $id)->first();
        if (! $ba) {
            return $this->missing('Berita acara tidak ditemukan.');
        }
        $jadwalId = (int) $ba['jadwal_ukk_id'];

        return $this->ok(self::data($ba) + [
            'peserta' => array_map([PesertaUkk::class, 'data'], (new PesertaUkkModel())->withRelations()
                ->where('peserta_ukk.jadwal_ukk_id', $jadwalId)->orderBy('siswa.nama', 'ASC')->findAll()),
            'penguji' => array_map([JadwalUkk::class, 'pengujiData'], (new JadwalUkkPengujiModel())->forJadwal($jadwalId)),
        ]);
    }

    public function store(): ResponseInterface
    {
        $in       = $this->body();
        $jadwalId = (int) ($in['jadwal_ukk_id'] ?? 0);
        [$isi, $err] = $this->isian($in);
        if (! $jadwalId || ! (new JadwalUkkModel())->find($jadwalId)) {
            $err = ['jadwal_ukk_id' => 'Jadwal UKK wajib dipilih.'] + $err;
        }
        if ($err !== []) {
            return $this->invalid($err);
        }

        $data = ['jadwal_ukk_id' => $jadwalId, 'nomor_ba' => $this->model->nomorBerikutnya()] + $isi;
        if (! $this->model->insert($data)) {
            return $this->invalid($this->model->errors());
        }
        $id = (int) $this->model->getInsertID();
        $this->audit->record('create', 'berita_acara_ukk', $id, 'Buat berita acara UKK ' . $data['nomor_ba'] . ' (via mobile)');

        return $this->created(self::data($this->segar($id)), 'Berita acara ' . $data['nomor_ba'] . ' dibuat.');
    }

    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Berita acara tidak ditemukan.');
        }
        [$isi, $err] = $this->isian($this->body());
        if ($err !== []) {
            return $this->invalid($err);
        }
        if (! $this->model->update($id, ['id' => $id] + $isi)) {
            return $this->invalid($this->model->errors());
        }
        $this->audit->record('update', 'berita_acara_ukk', $id, 'Ubah berita acara UKK (via mobile)');

        return $this->ok(self::data($this->segar($id)), 'Berita acara diperbarui.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Berita acara tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'berita_acara_ukk', $id, 'Hapus berita acara UKK (via mobile)');

        return $this->ok(null, 'Berita acara dihapus.');
    }

    public static function data(array $r): array
    {
        return [
            'id'             => (int) $r['id'],
            'nomor_ba'       => $r['nomor_ba'],
            'jadwal_ukk_id'  => (int) $r['jadwal_ukk_id'],
            'jadwal_tanggal' => $r['jadwal_tanggal'] ?? null,
            'paket_nama'     => $r['paket_nama'] ?? null,
            'tempat_nama'    => $r['tempat_nama'] ?? null,
            'tanggal'        => $r['tanggal'],
            'catatan'        => $r['catatan'] ?? null,
            'keterangan'     => $r['keterangan'] ?? null,
            'pdf_web'        => site_url('admin/berita-acara-ukk/pdf/' . (int) $r['id']),
        ];
    }

    /** Isian tanggal/catatan/keterangan (tanggal kosong = hari ini, sama dengan web) + galatnya. */
    private function isian(array $in): array
    {
        $tanggal    = trim((string) ($in['tanggal'] ?? '')) ?: date('Y-m-d');
        $keterangan = trim((string) ($in['keterangan'] ?? '')) ?: null;
        $err        = [];
        if (! UkkAlur::tanggalSah($tanggal)) {
            $err['tanggal'] = 'Format tanggal harus TTTT-BB-HH.';
        }
        if ($keterangan !== null && mb_strlen($keterangan) > 255) {
            $err['keterangan'] = 'Keterangan maksimal 255 karakter.';
        }

        return [['tanggal' => $tanggal, 'catatan' => trim((string) ($in['catatan'] ?? '')) ?: null, 'keterangan' => $keterangan], $err];
    }

    private function segar(int $id): array
    {
        return $this->model->withRelations()->where('berita_acara_ukk.id', $id)->first() ?? [];
    }
}
