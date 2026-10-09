<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\UjianJadwalModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Penugasan pembuat soal per jadwal ujian (dasar angka "Pembuatan Soal" di Honor Ujian).
 * Satu penugasan = satu guru membuat satu set soal untuk satu jadwal. Dipakai web (Admin\UjianHonor) dan API
 * Android (Api\Admin\Honor) supaya aturan dan pesannya sama.
 */
final class HonorPembuatSoal
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Jadwal periode ini beserta pembuat soalnya.
     *
     * @return array{jadwal:list<array<string,mixed>>, tugas:array<int,list<array<string,mixed>>>}
     */
    public function daftar(int $periodeId): array
    {
        $jadwal = (new UjianJadwalModel())->untukPeriode($periodeId)->findAll();
        $tugas  = [];
        if ($jadwal !== []) {
            foreach ($this->db->table('ujian_pembuat_soal s')->select('s.id, s.jadwal_id, s.guru_id, g.nama')->join('guru g', 'g.id = s.guru_id AND g.deleted_at IS NULL')
                ->whereIn('s.jadwal_id', array_map('intval', array_column($jadwal, 'id')))->orderBy('g.nama')->get()->getResultArray() as $t) {
                $tugas[(int) $t['jadwal_id']][] = $t;
            }
        }

        return ['jadwal' => $jadwal, 'tugas' => $tugas];
    }

    /** @param array<string,mixed> $periode baris ujian_periode */
    public function tambah(array $periode, int $jadwalId, int $guruId): array
    {
        $jadwal = $jadwalId > 0 ? (new UjianJadwalModel())->find($jadwalId) : null;
        $guru   = $guruId > 0 ? $this->db->table('guru')->select('id, nama')->where('id', $guruId)->where('deleted_at', null)->get()->getRowArray() : null;
        if ($jadwal === null || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return ['ok' => false, 'pesan' => 'Jadwal ujian tidak ditemukan.'];
        }
        if ($guru === null) {
            return ['ok' => false, 'pesan' => 'Pilih guru dulu.'];
        }
        if ($this->db->table('ujian_pembuat_soal')->where('jadwal_id', $jadwalId)->where('guru_id', $guruId)->countAllResults() > 0) {
            return ['ok' => false, 'pesan' => $guru['nama'] . ' sudah menjadi pembuat soal untuk jadwal ini.'];
        }
        $this->db->table('ujian_pembuat_soal')->insert(['jadwal_id' => $jadwalId, 'guru_id' => $guruId, 'created_at' => date('Y-m-d H:i:s')]);
        $id = (int) $this->db->insertID();
        (new AuditModel())->record('create', 'ujian_pembuat_soal', $id, 'Pembuat soal ' . $guru['nama'] . ' — jadwal ujian #' . $jadwalId);

        return ['ok' => true, 'pesan' => $guru['nama'] . ' ditugaskan sebagai pembuat soal.', 'id' => $id];
    }

    /** @param array<string,mixed> $periode baris ujian_periode */
    public function cabut(array $periode, int $id): array
    {
        $row = $this->db->table('ujian_pembuat_soal s')->select('s.id, g.nama')->join('ujian_jadwal j', 'j.id = s.jadwal_id')->join('guru g', 'g.id = s.guru_id', 'left')
            ->where('s.id', $id)->where('j.periode_id', (int) $periode['id'])->get()->getRowArray();
        if ($row === null) {
            return ['ok' => false, 'pesan' => 'Penugasan tidak ditemukan.'];
        }
        $this->db->table('ujian_pembuat_soal')->where('id', $id)->delete();
        (new AuditModel())->record('delete', 'ujian_pembuat_soal', $id, 'Cabut pembuat soal ' . ($row['nama'] ?? '?'));

        return ['ok' => true, 'pesan' => 'Penugasan dicabut.'];
    }
}
