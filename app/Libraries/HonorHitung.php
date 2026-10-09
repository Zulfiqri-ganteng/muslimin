<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Hitung otomatis isian Honor Ujian dari data sekolah (Fase 3). Semua hasil hanya SARAN AWAL:
 * Admin tetap bisa mengubah tiap angka di grid, dan sel yang sudah diubah tidak ditimpa kecuali diminta.
 *
 *   Koreksi         = Σ siswa aktif tiap kelas yang diampu guru (satu lembar jawaban per siswa per mapel diampu)
 *   Rapot           = Σ siswa aktif kelas yang diwalikan guru (kelas.wali_kelas_id)
 *   Pembuatan Soal  = jumlah penugasan pembuat soal guru pada jadwal periode ini (ujian_pembuat_soal)
 *   Pengawas        = TIDAK dihitung otomatis (keputusan: jumlah sesi diketik manual, mis. bila guru tidak masuk);
 *                     jadwal pengawas hanya ditampilkan sebagai PETUNJUK.
 *
 * Data guru ganda (induk_id) digabung ke satu orang. Penanda per sel: honor_nilai.otomatis_nilai = angka hasil hitung
 * terakhir; nilai ≠ otomatis_nilai berarti "diubah" (tampilan). PERLINDUNGAN memakai penanda eksplisit honor_nilai.manual
 * (1 = diketik atau hasil impor Excel, termasuk angka 0 yang sengaja diisi): sel manual tidak ditimpa tanpa "timpa".
 */
final class HonorHitung
{
    /** Sumber komponen yang bisa dihitung otomatis → nama tampil. */
    public const SUMBER = ['koreksi' => 'Koreksi', 'rapot' => 'Rapot', 'soal' => 'Pembuatan Soal'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Sumber angka (orang_id => jumlah)
    // =================================================================

    /** Jumlah siswa aktif per kelas (kelas_id => n). */
    private function siswaPerKelas(): array
    {
        $out = [];
        foreach ($this->db->table('siswa')->select('kelas_id, COUNT(*) AS n')->where('status', 'aktif')->where('deleted_at', null)
            ->where('kelas_id IS NOT NULL')->groupBy('kelas_id')->get()->getResultArray() as $r) {
            $out[(int) $r['kelas_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** guru_id → id orang (data ganda dipetakan ke induknya). */
    private function orang(int $guruId, array $peta): int
    {
        return $peta[$guruId] ?? $guruId;
    }

    /** Koreksi: lembar jawaban = Σ siswa kelas yang diampu (tiap penugasan pengampu = satu mapel di satu kelas). */
    public function koreksi(): array
    {
        $siswa = $this->siswaPerKelas();
        $peta  = GuruModel::petaOrang();
        $out   = [];
        $rows  = $this->db->table('pengampu p')->select('p.guru_id, p.kelas_id')
            ->join('kelas k', 'k.id = p.kelas_id AND k.deleted_at IS NULL')
            ->where('p.deleted_at', null)->get()->getResultArray();
        foreach ($rows as $r) {
            $o = $this->orang((int) $r['guru_id'], $peta);
            $out[$o] = ($out[$o] ?? 0) + ($siswa[(int) $r['kelas_id']] ?? 0);
        }

        return $out;
    }

    /** Rapot: Σ siswa kelas yang diwalikan. */
    public function rapot(): array
    {
        $siswa = $this->siswaPerKelas();
        $peta  = GuruModel::petaOrang();
        $out   = [];
        foreach ($this->db->table('kelas')->select('id, wali_kelas_id')->where('deleted_at', null)->where('wali_kelas_id IS NOT NULL')->get()->getResultArray() as $k) {
            $o = $this->orang((int) $k['wali_kelas_id'], $peta);
            $out[$o] = ($out[$o] ?? 0) + ($siswa[(int) $k['id']] ?? 0);
        }

        return $out;
    }

    /** Pembuatan soal: jumlah penugasan pembuat soal pada jadwal periode ini. */
    public function soal(int $periodeId): array
    {
        $peta = GuruModel::petaOrang();
        $out  = [];
        foreach ($this->db->table('ujian_pembuat_soal s')->select('s.guru_id, COUNT(*) AS n')
            ->join('ujian_jadwal j', 'j.id = s.jadwal_id AND j.deleted_at IS NULL')
            ->where('j.periode_id', $periodeId)->groupBy('s.guru_id')->get()->getResultArray() as $r) {
            $o = $this->orang((int) $r['guru_id'], $peta);
            $out[$o] = ($out[$o] ?? 0) + (int) $r['n'];
        }

        return $out;
    }

    /** Petunjuk pengawas: jumlah sesi berstatus "pengawas" (bukan cadangan) pada jadwal periode ini. HANYA PETUNJUK. */
    public function pengawasPetunjuk(int $periodeId): array
    {
        $peta = GuruModel::petaOrang();
        $out  = [];
        foreach ($this->db->table('ujian_pengawas p')->select('p.guru_id, COUNT(*) AS n')
            ->join('ujian_jadwal j', 'j.id = p.jadwal_id AND j.deleted_at IS NULL')
            ->where('j.periode_id', $periodeId)->where('p.peran', 'pengawas')->where('p.guru_id IS NOT NULL')->groupBy('p.guru_id')->get()->getResultArray() as $r) {
            $o = $this->orang((int) $r['guru_id'], $peta);
            $out[$o] = ($out[$o] ?? 0) + (int) $r['n'];
        }

        return $out;
    }

    /** Angka untuk satu sumber. */
    public function sumber(string $sumber, int $periodeId): array
    {
        return match ($sumber) {
            'koreksi' => $this->koreksi(),
            'rapot'   => $this->rapot(),
            'soal'    => $this->soal($periodeId),
            default   => [],
        };
    }

    /**
     * Gambaran sumber untuk dialog "Hitung otomatis": berapa orang & berapa total tiap sumber.
     *
     * @return array<string, array{orang:int, total:int}>
     */
    public function gambaran(int $periodeId): array
    {
        $out = [];
        foreach (array_keys(self::SUMBER) as $s) {
            $d = array_filter($this->sumber($s, $periodeId), static fn (int $n): bool => $n > 0);
            $out[$s] = ['orang' => count($d), 'total' => array_sum($d)];
        }

        return $out;
    }

    // =================================================================
    // Terapkan ke dokumen honor
    // =================================================================

    /**
     * Isi otomatis komponen bersumber $sumber (subset dari SUMBER) pada seluruh penerima dokumen.
     * Sel yang sudah diubah manual dilewati (hanya penanda "otomatis" diperbarui supaya selisihnya terlihat),
     * kecuali $timpa = true.
     *
     * @param list<string> $sumber
     *
     * @return array{ok:bool, pesan:string, ringkas?:list<array<string,mixed>>}
     */
    public function terapkan(int $dokumenId, array $sumber, bool $timpa = false): array
    {
        $dok = $this->db->table('honor_dokumen')->where('id', $dokumenId)->get()->getRowArray();
        if ($dok === null) {
            return ['ok' => false, 'pesan' => 'Honor tidak ditemukan.'];
        }
        if ($dok['status'] === 'dikunci') {
            return ['ok' => false, 'pesan' => 'Honor ini sudah DIKUNCI dan tidak bisa diubah.'];
        }
        $sumber = array_values(array_intersect(array_keys(self::SUMBER), array_map('strval', $sumber)));
        if ($sumber === []) {
            return ['ok' => false, 'pesan' => 'Pilih minimal satu komponen yang mau dihitung otomatis.'];
        }
        $komponen = $this->db->table('honor_dok_komponen')->where('dokumen_id', $dokumenId)->whereIn('sumber', $sumber)->orderBy('urut')->get()->getResultArray();
        if ($komponen === []) {
            return ['ok' => false, 'pesan' => 'Honor ini tidak memuat komponen yang bisa dihitung otomatis (' . implode(', ', array_map(static fn (string $s): string => self::SUMBER[$s], $sumber)) . ').'];
        }

        $baris = $this->db->table('honor_baris b')->select('b.id, b.guru_id, b.nama, g.id AS guru_ada')
            ->join('guru g', 'g.id = b.guru_id AND g.deleted_at IS NULL', 'left')
            ->where('b.dokumen_id', $dokumenId)->get()->getResultArray();
        if ($baris === []) {
            return ['ok' => false, 'pesan' => 'Belum ada penerima. Tambahkan penerima dulu.'];
        }
        $sel = [];
        foreach ($this->db->table('honor_nilai')->whereIn('baris_id', array_map('intval', array_column($baris, 'id')))->get()->getResultArray() as $r) {
            $sel[(int) $r['baris_id']][(int) $r['dok_komponen_id']] = $r;
        }
        $peta   = GuruModel::petaOrang();
        $data   = [];
        foreach ($sumber as $s) {
            $data[$s] = $this->sumber($s, (int) $dok['periode_id']);
        }

        $ringkas = [];
        $now     = date('Y-m-d H:i:s');
        $this->db->transStart();
        foreach ($komponen as $k) {
            $kid = (int) $k['id'];
            $c   = ['nama' => $k['nama'], 'diisi' => 0, 'diubah' => 0, 'sama' => 0, 'dilewati' => 0, 'tanpa_data' => 0, 'jumlah' => 0, 'terpotong' => 0];
            foreach ($baris as $b) {
                if ($b['guru_ada'] === null) { // orangnya sudah dihapus dari Master Guru: jangan disentuh
                    $c['tanpa_data']++;
                    continue;
                }
                $h = (int) ($data[$k['sumber']][$this->orang((int) $b['guru_id'], $peta)] ?? 0);
                if ($h > HonorDokumen::MAKS_JUMLAH) {
                    $h = HonorDokumen::MAKS_JUMLAH;
                    $c['terpotong']++;
                }
                $lama  = $sel[(int) $b['id']][$kid] ?? null;
                $nilai = (int) ($lama['nilai'] ?? 0);
                $manual = $lama !== null && (int) ($lama['manual'] ?? 0) === 1; // diketik / hasil impor
                if ($manual && ! $timpa) {
                    $this->simpanSel((int) $b['id'], $kid, $nilai, $h, $lama, $now, true); // nilai tetap; hanya pembanding "otomatis" diperbarui
                    $c['dilewati']++;
                    continue;
                }
                $this->simpanSel((int) $b['id'], $kid, $h, $h, $lama, $now, false);
                $c[$h !== $nilai ? 'diubah' : 'sama']++;
                if ($h > 0) {
                    $c['diisi']++;
                    $c['jumlah'] += $h;
                }
            }
            $ringkas[] = $c;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['ok' => false, 'pesan' => 'Gagal menghitung otomatis. Coba lagi.'];
        }

        $bagian = [];
        foreach ($ringkas as $c) {
            $t = $c['nama'] . ': ' . $c['diisi'] . ' orang terisi (total ' . number_format($c['jumlah'], 0, ',', '.') . ')';
            if ($c['dilewati'] > 0) {
                $t .= ', ' . $c['dilewati'] . ' isian yang sudah kamu ubah dibiarkan';
            }
            if ($c['terpotong'] > 0) {
                $t .= ', ' . $c['terpotong'] . ' angka dipotong ke batas ' . number_format(HonorDokumen::MAKS_JUMLAH, 0, ',', '.');
            }
            $bagian[] = $t;
        }
        (new AuditModel())->record('update', 'honor_nilai', $dokumenId, 'Hitung otomatis honor #' . $dokumenId . ($timpa ? ' (timpa isian manual)' : '') . ' — ' . implode('; ', $bagian));

        return ['ok' => true, 'pesan' => 'Hitung otomatis selesai. ' . implode('. ', $bagian) . '.', 'ringkas' => $ringkas];
    }

    private function simpanSel(int $barisId, int $dkId, int $nilai, int $otomatis, ?array $lama, string $now, bool $manual): void
    {
        if ($lama === null) {
            $this->db->table('honor_nilai')->insert(['baris_id' => $barisId, 'dok_komponen_id' => $dkId, 'nilai' => $nilai, 'otomatis_nilai' => $otomatis, 'manual' => $manual ? 1 : 0, 'updated_at' => $now]);

            return;
        }
        if ((int) $lama['nilai'] === $nilai && $lama['otomatis_nilai'] !== null && (int) $lama['otomatis_nilai'] === $otomatis && (int) ($lama['manual'] ?? 0) === ($manual ? 1 : 0)) {
            return;
        }
        $this->db->table('honor_nilai')->where('id', $lama['id'])->update(['nilai' => $nilai, 'otomatis_nilai' => $otomatis, 'manual' => $manual ? 1 : 0, 'updated_at' => $now]);
    }
}
