<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UkkAlur;
use App\Models\AuditModel;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\NilaiUkkModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PesertaUkkModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Penilaian UKK (API, fase UKK A2): matriks peserta × penguji per jadwal. Cermin App\Controllers\Admin\PenilaianUkk;
 * aturan simpan (anti tamper, nilai berbobot, rata-rata semua penguji, lulus vs KKM) bersama web di
 * App\Libraries\UkkAlur::simpanNilai. Rute /api/v1/admin/penilaian-ukk:
 *   GET                daftar jadwal + progres (jumlah peserta, sudah dinilai)
 *   GET  jadwal/{id}   matriks: jadwal, paket (bobot + KKM), penguji[], peserta[] (+ nilai[] per penguji)
 *   POST jadwal/{id}   simpan satu sel {peserta_ukk_id, tipe_penguji, guru_id | penguji_eksternal_id,
 *                      persiapan_skor, proses_skor, hasil_skor, sikap_skor, waktu_skor, tanggal_nilai, keterangan}
 */
class PenilaianUkk extends BaseApiController
{
    protected AuditModel $audit;

    public function __construct()
    {
        $this->audit = new AuditModel();
    }

    /** GET daftar jadwal sebagai pintu masuk penilaian (tanpa paginasi, sama dengan web). */
    public function index(): ResponseInterface
    {
        $rows = (new JadwalUkkModel())->withRelations()
            ->orderBy('jadwal_ukk.tanggal_mulai', 'DESC')->orderBy('jadwal_ukk.id', 'DESC')->findAll();

        $progres = [];
        foreach ((new PesertaUkkModel())->select('jadwal_ukk_id, COUNT(*) AS jumlah, SUM(nilai_akhir IS NOT NULL) AS dinilai', false)
            ->where('jadwal_ukk_id IS NOT NULL')->groupBy('jadwal_ukk_id')->findAll() as $r) {
            $progres[(int) $r['jadwal_ukk_id']] = [(int) $r['jumlah'], (int) $r['dinilai']];
        }

        return $this->ok(array_map(static fn ($j) => self::jadwalData($j) + [
            'jumlah_peserta' => $progres[(int) $j['id']][0] ?? 0,
            'sudah_dinilai'  => $progres[(int) $j['id']][1] ?? 0,
        ], $rows));
    }

    /** GET matriks nilai satu jadwal. */
    public function jadwal($id = null): ResponseInterface
    {
        $id     = (int) $id;
        $jadwal = (new JadwalUkkModel())->withRelations()->where('jadwal_ukk.id', $id)->first();
        if (! $jadwal) {
            return $this->missing('Jadwal tidak ditemukan.');
        }
        $paket   = (new PaketSoalUkkModel())->find($jadwal['paket_soal_id']);
        $peserta = (new PesertaUkkModel())->withRelations()
            ->where('peserta_ukk.jadwal_ukk_id', $id)->orderBy('siswa.nama', 'ASC')->findAll();

        $nilaiPer = [];
        $ids      = array_column($peserta, 'id');
        if ($ids !== []) {
            foreach (self::nilaiQuery()->whereIn('nilai_ukk.peserta_ukk_id', $ids)->findAll() as $n) {
                $nilaiPer[(int) $n['peserta_ukk_id']][] = self::nilaiData($n);
            }
        }

        return $this->ok([
            'jadwal'  => self::jadwalData($jadwal),
            'paket'   => $paket ? [
                'id'    => (int) $paket['id'],
                'kode'  => $paket['kode'],
                'nama'  => $paket['nama'],
                'bobot' => [
                    'persiapan' => (float) $paket['bobot_persiapan'], 'proses' => (float) $paket['bobot_proses'],
                    'hasil'     => (float) $paket['bobot_hasil'], 'sikap' => (float) $paket['bobot_sikap'],
                    'waktu'     => (float) $paket['bobot_waktu'],
                ],
                'kkm'   => (float) $paket['kkm'],
            ] : null,
            'penguji' => array_map([JadwalUkk::class, 'pengujiData'], (new JadwalUkkPengujiModel())->forJadwal($id)),
            'peserta' => array_map(static fn ($p) => PesertaUkk::data($p) + ['nilai' => $nilaiPer[(int) $p['id']] ?? []], $peserta),
        ]);
    }

    /** POST simpan satu sel nilai; balasan = sel itu + agregat peserta terbaru (nilai akhir, status, predikat). */
    public function simpan($id = null): ResponseInterface
    {
        $hasil = UkkAlur::simpanNilai((int) $id, $this->body());
        if (! $hasil['ok']) {
            return $this->tolakHasil($hasil);
        }
        $this->audit->record($hasil['baru'] ? 'create' : 'update', 'nilai_ukk', $hasil['nilai_id'], 'Simpan nilai UKK peserta #' . $hasil['peserta_id'] . ' (' . $hasil['tipe'] . ') (via mobile)');

        $data = [
            'nilai'   => self::nilaiData(self::nilaiQuery()->where('nilai_ukk.id', $hasil['nilai_id'])->first()),
            'peserta' => PesertaUkk::data((new PesertaUkkModel())->withRelations()->where('peserta_ukk.id', $hasil['peserta_id'])->first()),
        ];

        return $hasil['baru'] ? $this->created($data, 'Nilai tersimpan.') : $this->ok($data, 'Nilai tersimpan.');
    }

    /**
     * Bentuk satu sel nilai untuk klien. 'kunci' = "{tipe}-{id penguji}", sama dengan 'kunci' penguji di
     * JadwalUkk::pengujiData — klien mencocokkan sel ke kolom penguji lewat kunci ini.
     */
    public static function nilaiData(array $n): array
    {
        $angka    = static fn ($v) => $v === null ? null : (float) $v;
        $internal = $n['tipe_penguji'] === 'internal';
        $orang    = (int) ($internal ? $n['guru_id'] : $n['penguji_eksternal_id']);

        $out = [
            'id'                   => (int) $n['id'],
            'kunci'                => $n['tipe_penguji'] . '-' . $orang,
            'tipe_penguji'         => $n['tipe_penguji'],
            'guru_id'              => $internal ? $orang : null,
            'penguji_eksternal_id' => $internal ? null : $orang,
            'penguji_nama'         => $internal ? ($n['guru_nama'] ?? null) : ($n['eksternal_nama'] ?? null),
        ];
        foreach (UkkAlur::SKOR as $k) {
            $out[$k] = $angka($n[$k] ?? null);
        }

        return $out + [
            'nilai_akhir'   => $angka($n['nilai_akhir'] ?? null),
            'tanggal_nilai' => $n['tanggal_nilai'] ?? null,
            'keterangan'    => $n['keterangan'] ?? null,
        ];
    }

    /** Nilai + nama penguji (guru / penguji eksternal). */
    private static function nilaiQuery(): NilaiUkkModel
    {
        return (new NilaiUkkModel())
            ->select('nilai_ukk.*, guru.nama AS guru_nama, penguji_eksternal.nama AS eksternal_nama')
            ->join('guru', 'guru.id = nilai_ukk.guru_id', 'left')
            ->join('penguji_eksternal', 'penguji_eksternal.id = nilai_ukk.penguji_eksternal_id', 'left')
            ->orderBy('nilai_ukk.tipe_penguji', 'ASC');
    }

    private static function jadwalData(array $j): array
    {
        return [
            'id'              => (int) $j['id'],
            'paket_soal_id'   => (int) $j['paket_soal_id'],
            'paket_kode'      => $j['paket_kode'] ?? null,
            'paket_nama'      => $j['paket_nama'] ?? null,
            'tempat_nama'     => $j['tempat_nama'] ?? null,
            'tanggal_mulai'   => $j['tanggal_mulai'],
            'tanggal_selesai' => $j['tanggal_selesai'] ?? null,
            'sesi'            => $j['sesi'] ?? null,
        ];
    }
}
