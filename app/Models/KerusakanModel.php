<?php

namespace App\Models;

use CodeIgniter\Model;

/** Laporan kerusakan aset (berlaku untuk semua kategori, bukan komputer saja). */
class KerusakanModel extends Model
{
    protected $table         = 'kerusakan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'aset_id', 'tanggal_lapor', 'pelapor', 'deskripsi', 'tingkat', 'status', 'teknisi_id', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const TINGKAT = ['ringan', 'sedang', 'berat'];
    public const STATUS  = ['dilaporkan', 'diproses', 'selesai', 'tak_teratasi'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'aset_id'       => 'required|is_natural',
        'tanggal_lapor' => 'required|valid_date[Y-m-d]',
        'deskripsi'     => 'required|max_length[255]',
        'tingkat'       => 'permit_empty|in_list[ringan,sedang,berat]',
        'status'        => 'permit_empty|in_list[dilaporkan,diproses,selesai,tak_teratasi]',
    ];
    protected $validationMessages = [
        'aset_id'   => ['required' => 'Aset yang rusak wajib dipilih.'],
        'deskripsi' => ['required' => 'Deskripsi kerusakan wajib diisi.'],
    ];

    /** Daftar kerusakan + info aset & teknisi penangan. */
    public function withRelations()
    {
        return $this->select('kerusakan.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS teknisi_nama')
            ->join('aset', 'aset.id = kerusakan.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = kerusakan.teknisi_id', 'left');
    }

    /** Opsi kerusakan yang MASIH TERBUKA [id => "nomor — deskripsi"] untuk dikaitkan ke perbaikan. */
    public function optionsTerbuka(): array
    {
        $rows = $this->select('kerusakan.id, kerusakan.deskripsi, aset.nomor_aset')
            ->join('aset', 'aset.id = kerusakan.aset_id', 'left')
            ->whereIn('kerusakan.status', ['dilaporkan', 'diproses'])
            ->orderBy('kerusakan.tanggal_lapor', 'DESC')->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = ($r['nomor_aset'] ?? '-') . ' — ' . mb_substr((string) $r['deskripsi'], 0, 45);
        }

        return $out;
    }

    /** Jumlah kerusakan yang masih terbuka untuk sebuah aset (opsional kecualikan satu id). */
    public function terbukaCount(int $asetId, ?int $except = null): int
    {
        $b = $this->where('aset_id', $asetId)->whereIn('status', ['dilaporkan', 'diproses']);
        if ($except !== null) {
            $b = $b->where('id !=', $except);
        }

        return $b->countAllResults();
    }
}
