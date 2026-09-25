<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruModel extends Model
{
    protected $table          = 'guru';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['nip', 'kode_guru', 'nama', 'no_wa', 'ikut_absensi', 'induk_id', 'jenis_kelamin', 'status_guru', 'max_beban', 'keterangan'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'kode_guru'     => 'required|max_length[20]|is_unique[guru.kode_guru,id,{id}]',
        'nama'          => 'required|max_length[150]',
        'nip'           => 'permit_empty|max_length[60]',
        'jenis_kelamin' => 'permit_empty|in_list[L,P]',
        'status_guru'   => 'permit_empty|in_list[PNS,PPPK,GTY,GTT]',
        'max_beban'     => 'permit_empty|is_natural',
        'no_wa'         => 'permit_empty|regex_match[/^62\d{7,13}$/]',
        'ikut_absensi'  => 'permit_empty|in_list[0,1]',
        'induk_id'      => 'permit_empty|is_natural_no_zero',
    ];
    protected $beforeUpdate = ['cegahIndukDiriSendiri'];

    /** Guru tidak boleh menjadi data ganda dari dirinya sendiri (web & API). */
    protected function cegahIndukDiriSendiri(array $data): array
    {
        $induk = (int) ($data['data']['induk_id'] ?? 0);
        if ($induk > 0 && in_array($induk, array_map('intval', (array) ($data['id'] ?? [])), true)) {
            $data['data']['induk_id'] = null;
        }

        return $data;
    }

    protected $validationMessages = [
        'kode_guru' => ['is_unique' => 'Kode guru sudah dipakai.', 'required' => 'Kode guru wajib diisi.'],
        'nama'      => ['required' => 'Nama guru wajib diisi.'],
        'no_wa'     => ['regex_match' => 'Nomor WhatsApp tidak valid (contoh: 081234567890).'],
    ];

    /**
     * Peta guru_id → id ORANG (data utama). Data guru ganda (induk_id terisi)
     * dipetakan ke induknya agar absensi, pesan WA & rekap menghitungnya satu
     * orang. Guru tanpa induk tidak ada di peta (pakai id-nya sendiri).
     *
     * @return array<int,int>
     */
    public static function petaOrang(): array
    {
        $peta = [];
        $rows = (new self())->withDeleted()->select('id, induk_id')->where('induk_id IS NOT NULL')->findAll();
        foreach ($rows as $r) {
            $induk = (int) $r['induk_id'];
            if ($induk > 0 && $induk !== (int) $r['id']) {
                $peta[(int) $r['id']] = $induk;
            }
        }

        return $peta;
    }

    /**
     * Semua id guru milik satu ORANG: data utama + data gandanya.
     *
     * @return list<int>
     */
    public static function idsOrang(int $orangId): array
    {
        $ids = [$orangId];
        foreach (self::petaOrang() as $gid => $induk) {
            if ($induk === $orangId) {
                $ids[] = $gid;
            }
        }

        return $ids;
    }

    /**
     * ID guru yang TIDAK ikut absensi & laporan (mis. ketua yayasan).
     *
     * @return array<int,true>
     */
    public static function tidakIkutAbsensi(): array
    {
        $ids = array_column((new self())->withDeleted()->select('id')->where('ikut_absensi', 0)->findAll(), 'id');

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Seragamkan nomor WhatsApp ke format 62… (tanpa +, spasi, atau tanda
     * hubung) agar bisa dipakai untuk tag "@62…" di grup WA.
     * "0812-3456-7890" / "+62 812…" / "812…" → "62812…". Kosong → null.
     */
    public static function normalNoWa(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0')) {
            $d = '62' . substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62' . $d;
        }

        return $d;
    }

    /** Opsi guru untuk dropdown (id => "kode - nama"), dengan cache. */
    public function options(): array
    {
        return cache()->remember('opt_guru', 21600, function () {
            $rows = $this->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['kode_guru'] . ' - ' . $r['nama'];
            }
            return $out;
        });
    }
}
