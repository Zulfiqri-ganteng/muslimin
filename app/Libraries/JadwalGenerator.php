<?php

namespace App\Libraries;

use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\PengampuModel;

/**
 * Penyusun jadwal otomatis untuk SATU kelas.
 *
 * Strategi: greedy "most-constrained-first" —
 *  1) Hitung slot kosong kelas (hari aktif × jam shift, tanpa istirahat).
 *  2) Untuk tiap penugasan, hitung sisa JP yang belum terpasang.
 *  3) Urutkan penugasan yang paling SULIT ditempatkan dulu (paling sedikit slot layak).
 *  4) Tempatkan tiap JP pada slot layak (lolos R1 guru, R2 kelas, R3 ketersediaan),
 *     memprioritaskan penyebaran antar-hari (hindari menumpuk mapel sama di 1 hari).
 *  5) Semua dalam transaksi; laporkan yang gagal (kurang slot/terlalu padat).
 */
class JadwalGenerator
{
    /**
     * @return array{ok:bool,message:string,placed:int,failed:array}
     */
    public function generate(int $kelasId, bool $reset = false): array
    {
        $kelas = (new KelasModel())->find($kelasId);
        if (! $kelas) {
            return ['ok' => false, 'message' => 'Kelas tidak ditemukan.', 'placed' => 0, 'failed' => []];
        }

        $haris = array_map('intval', array_column((new HariModel())->aktifUrut(), 'id'));
        $jams  = array_map('intval', array_column(
            (new JamPelajaranModel())->where('shift', $kelas['shift'])->where('is_istirahat', 0)
                ->orderBy('jam_ke', 'ASC')->findAll(),
            'id'
        ));
        if (empty($haris) || empty($jams)) {
            return ['ok' => false, 'message' => 'Hari aktif atau jam pelajaran (shift ' . $kelas['shift'] . ') belum diatur.', 'placed' => 0, 'failed' => []];
        }

        $db     = db_connect();
        $jadwal = new JadwalModel();
        $taId   = $this->activeTaId($db);

        $db->transBegin();

        if ($reset) {
            $jadwal->where('kelas_id', $kelasId)->delete();
        }

        // --- state awal ---
        $classOcc = [];               // "h-j" => true (sel kelas terisi)
        $perDay   = [];               // [pengampuId][hari] => jumlah
        $placedBy = [];               // [pengampuId] => jumlah terpasang
        foreach ($jadwal->where('kelas_id', $kelasId)->findAll() as $r) {
            $classOcc[$r['hari_id'] . '-' . $r['jam_id']]   = true;
            $perDay[(int) $r['pengampu_id']][(int) $r['hari_id']] = ($perDay[(int) $r['pengampu_id']][(int) $r['hari_id']] ?? 0) + 1;
            $placedBy[(int) $r['pengampu_id']] = ($placedBy[(int) $r['pengampu_id']] ?? 0) + 1;
        }

        // guru sibuk (lintas kelas) & ketersediaan tidak — preload sebagai set
        $guruBusy = [];
        foreach ($db->table('jadwal')->select('guru_id, hari_id, jam_id')->get()->getResultArray() as $r) {
            $guruBusy[$r['guru_id'] . '-' . $r['hari_id'] . '-' . $r['jam_id']] = true;
        }
        $ketNo = [];
        foreach ($db->table('ketersediaan_guru')->select('guru_id, hari_id, jam_id')->where('status', 'tidak')->get()->getResultArray() as $r) {
            $ketNo[$r['guru_id'] . '-' . $r['hari_id'] . '-' . $r['jam_id']] = true;
        }

        // --- daftar penugasan + sisa JP ---
        $plist = (new PengampuModel())->forKelas($kelasId);
        $tasks = [];
        foreach ($plist as $p) {
            $pid   = (int) $p['id'];
            $sisa  = (int) $p['jp'] - ($placedBy[$pid] ?? 0);
            if ($sisa <= 0) {
                continue;
            }
            $guruId = (int) $p['guru_id'];
            // hitung slot layak saat ini (untuk urutan most-constrained-first)
            $feasible = 0;
            foreach ($haris as $h) {
                foreach ($jams as $j) {
                    if ($this->slotOk($classOcc, $guruBusy, $ketNo, $guruId, $h, $j)) {
                        $feasible++;
                    }
                }
            }
            $tasks[] = [
                'pengampu_id' => $pid, 'guru_id' => $guruId, 'sisa' => $sisa,
                'feasible' => $feasible, 'kode_mapel' => $p['kode_mapel'],
                'nama_mapel' => $p['nama_mapel'], 'guru_nama' => $p['guru_nama'],
            ];
        }

        // urut: paling sulit dulu (feasible kecil), lalu sisa terbesar
        usort($tasks, static function ($a, $b) {
            return [$a['feasible'], -$a['sisa']] <=> [$b['feasible'], -$b['sisa']];
        });

        // --- penempatan ---
        $placed = 0;
        $failed = [];
        foreach ($tasks as $t) {
            $pid = $t['pengampu_id']; $guruId = $t['guru_id'];
            $need = $t['sisa'];
            for ($n = 0; $n < $need; $n++) {
                $slot = $this->pickSlot($classOcc, $guruBusy, $ketNo, $perDay, $pid, $guruId, $haris, $jams);
                if ($slot === null) {
                    break; // tak ada slot layak lagi untuk penugasan ini
                }
                [$h, $j] = $slot;
                $jadwal->insert([
                    'tahun_ajaran_id' => $taId, 'kelas_id' => $kelasId,
                    'hari_id' => $h, 'jam_id' => $j,
                    'pengampu_id' => $pid, 'guru_id' => $guruId,
                    'created_by' => session('admin')['id'] ?? null,
                ]);
                $classOcc[$h . '-' . $j]               = true;
                $guruBusy[$guruId . '-' . $h . '-' . $j] = true;
                $perDay[$pid][$h]                       = ($perDay[$pid][$h] ?? 0) + 1;
                $placed++;
            }
        }

        // hitung yang gagal: bandingkan target vs terpasang akhir
        foreach ($tasks as $t) {
            $pid = $t['pengampu_id'];
            $terpasang = array_sum($perDay[$pid] ?? []);
            $target    = ($placedBy[$pid] ?? 0) + $t['sisa'];
            $kurang    = $target - $terpasang;
            if ($kurang > 0) {
                $failed[] = [
                    'kode_mapel' => $t['kode_mapel'], 'nama_mapel' => $t['nama_mapel'],
                    'guru_nama' => $t['guru_nama'], 'kurang' => $kurang,
                ];
            }
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return ['ok' => false, 'message' => 'Terjadi kesalahan database, dibatalkan.', 'placed' => 0, 'failed' => []];
        }
        $db->transCommit();

        cache()->delete('rekap_beban');
        cache()->delete('dash_kurikulum');

        $msg = "Berhasil menempatkan {$placed} JP.";
        if ($failed) {
            $msg .= ' Sebagian gagal (slot tidak cukup / terlalu padat).';
        }
        return ['ok' => true, 'message' => $msg, 'placed' => $placed, 'failed' => $failed];
    }

    /** Slot layak? (R2 kelas kosong, R1 guru bebas, R3 guru tersedia) */
    private function slotOk(array $classOcc, array $guruBusy, array $ketNo, int $guruId, int $h, int $j): bool
    {
        if (isset($classOcc[$h . '-' . $j])) {
            return false;
        }
        if (isset($guruBusy[$guruId . '-' . $h . '-' . $j])) {
            return false;
        }
        if (isset($ketNo[$guruId . '-' . $h . '-' . $j])) {
            return false;
        }
        return true;
    }

    /** Pilih slot terbaik: sebar antar hari (perDay terkecil), lalu hari & jam paling awal. */
    private function pickSlot(array $classOcc, array $guruBusy, array $ketNo, array $perDay, int $pid, int $guruId, array $haris, array $jams): ?array
    {
        $best = null; $bestLoad = PHP_INT_MAX;
        foreach ($haris as $h) {
            $load = $perDay[$pid][$h] ?? 0;
            if ($load >= $bestLoad) {
                continue; // hari ini tak mungkin lebih baik
            }
            foreach ($jams as $j) {
                if ($this->slotOk($classOcc, $guruBusy, $ketNo, $guruId, $h, $j)) {
                    $best = [$h, $j]; $bestLoad = $load;
                    break; // jam paling awal pada hari terbaik ini
                }
            }
        }
        return $best;
    }

    private function activeTaId($db): ?int
    {
        $ta = $db->table('tahun_ajaran')->where('is_aktif', 1)->get()->getRowArray();
        return $ta ? (int) $ta['id'] : null;
    }
}
