<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Surat Sekolah — satu baris per surat (tabel surat_sekolah). Logika (buat, nomor, ACC) ada di Libraries\SuratSekolah;
 * model ini hanya untuk pembacaan/pembaruan sederhana dan pembantu tampilan.
 */
class SuratSekolahModel extends Model
{
    protected $table            = 'surat_sekolah';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'jenis', 'status', 'perlu_acc', 'judul', 'tanggal_surat', 'tahun', 'urut', 'nomor',
        'pengajuan_id', 'perusahaan_nama', 'isi', 'catatan_staf', 'diajukan_at',
        'acc_admin_id', 'acc_nama', 'acc_peran', 'acc_at', 'acc_ip', 'acc_kode',
        'cetak_ke', 'terakhir_cetak_at', 'sidik', 'dibuat_oleh', 'dibuat_nama',
    ];
    protected $useTimestamps = true;

    /** Urutan status di tab Daftar Surat. */
    public const STATUS = ['menunggu', 'dikembalikan', 'disetujui', 'dibatalkan'];

    /** status => [label, kelas lencana]. 'disetujui' = siap diunduh (setelah ACC, atau jenis tanpa ACC). */
    public const TAMPIL_STATUS = [
        'menunggu'     => ['Menunggu ACC', 'bg-amber-100 text-amber-800'],
        'dikembalikan' => ['Dikembalikan', 'bg-orange-100 text-orange-800'],
        'disetujui'    => ['Siap unduh', 'bg-green-100 text-green-800'],
        'dibatalkan'   => ['Dibatalkan', 'bg-slate-200 text-slate-600'],
    ];

    /** Kode tampilan, mis. SRT-00012 (bukan nomor surat resmi). */
    public static function kode(int $id): string
    {
        return 'SRT-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
