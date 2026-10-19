<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Buku piket keterlambatan siswa: satu baris = satu siswa terlambat pada satu tanggal (unik siswa + tanggal).
 * Nama siswa, kelas, shift, tahun ajaran, dan guru piket disalin saat dicatat. manual = 1: siswa BELUM TERDAFTAR (nama & kelas diketik
 * guru piket, siswa_id kosong, nomor HP orang tua di hp_ortu). Logika catat/ubah/hapus/tautkan: Libraries\Keterlambatan.
 */
class KeterlambatanModel extends Model
{
    protected $table         = 'keterlambatan_siswa';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tanggal', 'jam', 'siswa_id', 'nama_siswa', 'kelas_id', 'nama_kelas', 'shift', 'tahun_ajaran', 'sanksi', 'keterangan',
        'guru_id', 'guru_piket', 'dikabari_at', 'dikabari_oleh', 'dicatat_oleh_id', 'dicatat_oleh', 'manual', 'hp_ortu',
    ];
}
