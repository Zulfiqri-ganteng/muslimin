<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Folder dokumen — bertingkat lewat parent_id (self-FK), ala Google Drive.
 */
class DokumenFolderModel extends Model
{
    protected $table         = 'dokumen_folder';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama', 'parent_id', 'deskripsi', 'visibilitas', 'warna', 'created_by'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural',
        'nama'        => 'required|max_length[150]',
        'parent_id'   => 'permit_empty|is_natural',
        'deskripsi'   => 'permit_empty|max_length[255]',
        'visibilitas' => 'permit_empty|in_list[privat,link,publik]',
    ];
    protected $validationMessages = [
        'nama' => ['required' => 'Nama folder wajib diisi.'],
    ];

    /** Batas kedalaman folder — penjaga agar pohon tidak jadi tak terhingga. */
    public const MAKS_KEDALAMAN = 10;

    /**
     * Isi satu folder (daftar subfolder langsung). $parentId null = akar.
     */
    public function anak(?int $parentId): array
    {
        $b = $parentId === null
            ? $this->where('parent_id IS NULL', null, false)
            : $this->where('parent_id', $parentId);

        return $b->orderBy('nama', 'ASC')->findAll();
    }

    /**
     * Jejak dari akar sampai folder ini, untuk breadcrumb.
     * Urutan: [akar, ..., folder ini]. Aman terhadap rantai rusak/melingkar.
     *
     * @return list<array<string, mixed>>
     */
    public function jejak(?int $id): array
    {
        $out    = [];
        $lihat  = [];
        $cursor = $id;

        while ($cursor !== null && $cursor > 0 && count($out) < self::MAKS_KEDALAMAN + 5) {
            if (isset($lihat[$cursor])) {
                break;  // rantai melingkar — berhenti daripada berputar selamanya
            }
            $lihat[$cursor] = true;

            $row = $this->find($cursor);
            if ($row === null) {
                break;
            }
            array_unshift($out, $row);
            $cursor = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        return $out;
    }

    /**
     * Semua id keturunan sebuah folder (termasuk dirinya sendiri) — dipakai
     * saat menghapus folder beserta isinya, dan saat menghitung ukuran.
     *
     * @return list<int>
     */
    public function keturunan(int $id, bool $denganTerhapus = false): array
    {
        $hasil    = [$id];
        $antrian  = [$id];
        $dijelajah = [$id => true];

        while ($antrian !== []) {
            $b = $this->select('id')->whereIn('parent_id', $antrian);
            if ($denganTerhapus) {
                $b->withDeleted();
            }
            $batch   = $b->findAll();
            $antrian = [];

            foreach ($batch as $r) {
                $anakId = (int) $r['id'];
                if (isset($dijelajah[$anakId])) {
                    continue;
                }
                $dijelajah[$anakId] = true;
                $hasil[]            = $anakId;
                $antrian[]          = $anakId;
            }
        }

        return $hasil;
    }

    /**
     * Apakah $calonParent berada di dalam $folderId? Dipakai untuk menolak
     * pemindahan folder ke dalam dirinya sendiri (yang akan memutus pohon).
     */
    public function akanMelingkar(int $folderId, ?int $calonParent): bool
    {
        if ($calonParent === null) {
            return false;
        }
        if ($folderId === $calonParent) {
            return true;
        }

        return in_array($calonParent, $this->keturunan($folderId), true);
    }

    /** Kedalaman folder (akar = 1). */
    public function kedalaman(?int $id): int
    {
        return $id === null ? 0 : count($this->jejak($id));
    }

    /** Opsi dropdown [id => "Induk / Anak"] untuk pemindahan folder. */
    public function optionsBerjenjang(?int $kecualikan = null): array
    {
        $semua = $this->orderBy('nama', 'ASC')->findAll();
        $anak  = [];

        foreach ($semua as $r) {
            $anak[$r['parent_id'] === null ? 0 : (int) $r['parent_id']][] = $r;
        }

        $terlarang = $kecualikan !== null ? array_flip($this->keturunan($kecualikan)) : [];
        $out       = [];

        $telusur = static function (int $indukId, string $awalan, int $level) use (&$telusur, &$out, $anak, $terlarang) {
            if ($level > self::MAKS_KEDALAMAN) {
                return;
            }
            foreach ($anak[$indukId] ?? [] as $r) {
                $id = (int) $r['id'];
                if (isset($terlarang[$id])) {
                    continue;   // dirinya sendiri & keturunannya tak boleh jadi induk
                }
                $label    = $awalan === '' ? $r['nama'] : $awalan . ' / ' . $r['nama'];
                $out[$id] = $label;
                $telusur($id, $label, $level + 1);
            }
        };
        $telusur(0, '', 1);

        return $out;
    }
}
