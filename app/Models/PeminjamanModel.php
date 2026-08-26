<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Peminjaman aset. Pengembalian TIDAK menambah baris baru — cukup memperbarui
 * baris ini (isi tanggal_kembali_aktual, kondisi_kembali, status).
 * Peminjam boleh teks bebas (peminjam_ref opsional, tanpa foreign key).
 */
class PeminjamanModel extends Model
{
    protected $table         = 'peminjaman';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'aset_id', 'peminjam_nama', 'peminjam_tipe', 'peminjam_ref', 'tujuan',
        'tanggal_pinjam', 'tanggal_kembali_rencana', 'tanggal_kembali_aktual',
        'kondisi_pinjam', 'kondisi_kembali', 'status', 'petugas_id', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const TIPE   = ['guru', 'siswa', 'umum'];
    public const STATUS = ['dipinjam', 'dikembalikan', 'terlambat', 'hilang'];

    protected $validationRules = [
        'id'             => 'permit_empty|is_natural',
        'aset_id'        => 'required|is_natural',
        'peminjam_nama'  => 'required|max_length[150]',
        'peminjam_tipe'  => 'permit_empty|in_list[guru,siswa,umum]',
        'tanggal_pinjam' => 'required|valid_date[Y-m-d]',
        'status'         => 'permit_empty|in_list[dipinjam,dikembalikan,terlambat,hilang]',
    ];
    protected $validationMessages = [
        'aset_id'       => ['required' => 'Aset yang dipinjam wajib dipilih.'],
        'peminjam_nama' => ['required' => 'Nama peminjam wajib diisi.'],
    ];

    /** Daftar peminjaman + info aset & petugas. */
    public function withRelations()
    {
        return $this->select('peminjaman.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS petugas_nama')
            ->join('aset', 'aset.id = peminjaman.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = peminjaman.petugas_id', 'left');
    }
}
