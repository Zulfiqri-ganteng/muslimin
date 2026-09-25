<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\BiodataForm;
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
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q       = trim((string) $this->request->getGet('q'));
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
        }
        if (in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if (in_array($status, SiswaModel::STATUS, true)) {
            $builder = $builder->where('siswa.status', $status);
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

        return $this->collectBiodata($in, $teks) + [
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

        return $this->transformInti($r) + $biodata;
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

    /** Ringkasan agregat untuk dashboard admin: GET .../siswa/statistik */
    public function statistik(): ResponseInterface
    {
        return $this->ok((new SiswaModel())->statistik());
    }
}
