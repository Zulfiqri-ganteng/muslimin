<?php

namespace App\Models;

use CodeIgniter\Model;

class NilaiUkkModel extends Model
{
    protected $table         = 'nilai_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'peserta_ukk_id', 'tipe_penguji', 'guru_id', 'penguji_eksternal_id',
        'persiapan_skor', 'proses_skor', 'hasil_skor', 'sikap_skor', 'waktu_skor',
        'nilai_akhir', 'tanggal_nilai', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'             => 'permit_empty|is_natural',
        'peserta_ukk_id' => 'required|is_natural',
        'tipe_penguji'   => 'required|in_list[internal,eksternal]',
        'guru_id'        => 'permit_empty|is_natural',
        'penguji_eksternal_id' => 'permit_empty|is_natural',
        'persiapan_skor' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'proses_skor'    => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'hasil_skor'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'sikap_skor'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'waktu_skor'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];
    protected $validationMessages = [
        'peserta_ukk_id' => ['required' => 'Peserta wajib dipilih.'],
        'tipe_penguji'   => ['required' => 'Tipe penguji wajib dipilih.'],
    ];

    /** Hitung nilai akhir berbobot dari 5 komponen sesuai bobot paket soal. */
    public function hitungNilaiAkhir(array $skor, array $paket): float
    {
        $bobot = [
            'persiapan_skor' => (float) ($paket['bobot_persiapan'] ?? 10),
            'proses_skor'    => (float) ($paket['bobot_proses'] ?? 30),
            'hasil_skor'     => (float) ($paket['bobot_hasil'] ?? 40),
            'sikap_skor'     => (float) ($paket['bobot_sikap'] ?? 10),
            'waktu_skor'     => (float) ($paket['bobot_waktu'] ?? 10),
        ];

        $total = 0.0;
        foreach ($bobot as $field => $persen) {
            $total += (float) ($skor[$field] ?? 0) * $persen / 100;
        }

        return round($total, 2);
    }

    /** Semua nilai milik satu peserta + nama penguji. */
    public function untukPeserta(int $pesertaId): array
    {
        return $this->select(
            'nilai_ukk.*, guru.nama AS guru_nama, penguji_eksternal.nama AS eksternal_nama'
        )
            ->join('guru', 'guru.id = nilai_ukk.guru_id', 'left')
            ->join('penguji_eksternal', 'penguji_eksternal.id = nilai_ukk.penguji_eksternal_id', 'left')
            ->where('peserta_ukk_id', $pesertaId)
            ->orderBy('tipe_penguji', 'ASC')
            ->findAll();
    }

    /** Rata-rata nilai_akhir seluruh penguji untuk satu peserta (null bila belum ada nilai). */
    public function rataRataUntukPeserta(int $pesertaId): ?float
    {
        $row = $this->selectAvg('nilai_akhir', 'rata')
            ->where('peserta_ukk_id', $pesertaId)
            ->where('nilai_akhir IS NOT NULL')
            ->first();

        return $row && $row['rata'] !== null ? round((float) $row['rata'], 2) : null;
    }
}
