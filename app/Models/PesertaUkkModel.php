<?php

namespace App\Models;

use CodeIgniter\Model;

class PesertaUkkModel extends Model
{
    protected $table         = 'peserta_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'siswa_id', 'paket_soal_id', 'jadwal_ukk_id', 'tahun_ajaran_id',
        'no_peserta', 'status', 'nilai_akhir', 'predikat', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const STATUS = ['terdaftar', 'hadir', 'tidak_hadir', 'lulus', 'tidak_lulus'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'siswa_id'      => 'required|is_natural',
        'paket_soal_id' => 'required|is_natural',
        'jadwal_ukk_id' => 'permit_empty|is_natural',
        'status'        => 'permit_empty|in_list[terdaftar,hadir,tidak_hadir,lulus,tidak_lulus]',
    ];
    protected $validationMessages = [
        'siswa_id'      => ['required' => 'Siswa wajib dipilih.'],
        'paket_soal_id' => ['required' => 'Paket soal wajib dipilih.'],
    ];

    /** Cek siswa sudah terdaftar pada paket soal ini (untuk validasi sebelum insert, ikut DB unique(siswa_id,paket_soal_id)). */
    public function sudahTerdaftar(int $siswaId, int $paketSoalId, ?int $exceptId = null): bool
    {
        $b = $this->where(['siswa_id' => $siswaId, 'paket_soal_id' => $paketSoalId]);
        if ($exceptId !== null) {
            $b = $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    /** Daftar peserta + nama siswa/kelas/paket soal/jadwal. */
    public function withRelations()
    {
        return $this->select(
            'peserta_ukk.*, siswa.nis, siswa.nama AS siswa_nama, kelas.nama_kelas,'
            . ' paket_soal_ukk.kode AS paket_kode, paket_soal_ukk.nama AS paket_nama, paket_soal_ukk.kkm,'
            . ' jadwal_ukk.tanggal_mulai AS jadwal_tanggal'
        )
            ->join('siswa', 'siswa.id = peserta_ukk.siswa_id', 'left')
            ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
            ->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id', 'left')
            ->join('jadwal_ukk', 'jadwal_ukk.id = peserta_ukk.jadwal_ukk_id', 'left');
    }

    /** Nomor peserta berikut untuk sebuah paket soal, format "UKK-{KODE}-001". */
    public function nomorBerikutnya(string $kodePaket): string
    {
        $prefix = 'UKK-' . mb_strtoupper($kodePaket) . '-';
        $last   = $this->withDeleted()->select('no_peserta')
            ->like('no_peserta', $prefix, 'after')
            ->orderBy('no_peserta', 'DESC')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last['no_peserta'], $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /** Opsi peserta satu jadwal [id => "no - nama"] untuk form penilaian. */
    public function optionsUntukJadwal(int $jadwalId): array
    {
        $out = [];
        foreach ($this->withRelations()->where('peserta_ukk.jadwal_ukk_id', $jadwalId)
            ->orderBy('siswa.nama', 'ASC')->findAll() as $r) {
            $out[$r['id']] = ($r['no_peserta'] ?? '-') . ' - ' . $r['siswa_nama'];
        }

        return $out;
    }

    /** Opsi peserta LULUS yang BELUM punya sertifikat [id => "no - nama - paket"], untuk terbitkan sertifikat. */
    public function optionsLulusBelumSertifikat(): array
    {
        $sudahPunya = array_column(
            (new SertifikatUkkModel())->select('peserta_ukk_id')->findAll(),
            'peserta_ukk_id'
        );

        $b = $this->withRelations()->where('peserta_ukk.status', 'lulus');
        if ($sudahPunya !== []) {
            $b = $b->whereNotIn('peserta_ukk.id', $sudahPunya);
        }

        $out = [];
        foreach ($b->orderBy('siswa.nama', 'ASC')->findAll() as $r) {
            $out[$r['id']] = ($r['no_peserta'] ?? '-') . ' - ' . $r['siswa_nama'] . ' (' . ($r['paket_kode'] ?? '-') . ')';
        }

        return $out;
    }
}
