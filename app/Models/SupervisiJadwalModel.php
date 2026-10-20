<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jadwal supervisi = satu kunjungan: guru, mapel/kelas, supervisor, tanggal, instrumen (JSON daftar id). Nama guru/NIP/mapel/kelas/
 * supervisor disalin. nilai_akhir & predikat diisi saat penilaian selesai (S2). Logika: Libraries\Supervisi.
 */
class SupervisiJadwalModel extends Model
{
    protected $table         = 'supervisi_jadwal';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tahun_ajaran', 'semester', 'tanggal', 'jam', 'guru_id', 'nama_guru', 'nip_guru', 'mapel_id', 'nama_mapel', 'kelas_id', 'nama_kelas',
        'supervisor_id', 'nama_supervisor', 'jabatan_supervisor', 'instrumen', 'status', 'catatan', 'nilai_akhir', 'predikat',
        'dicatat_oleh_id', 'dicatat_oleh',
    ];
}
