<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Periode ujian — satu baris per jenis ujian per tahun pelajaran.
 *
 * Empat jenis (ASTS1, ASAS, ASTS2, ASAT) inilah yang tampil sebagai empat
 * menu di sidebar. Barisnya dibuat otomatis saat menu pertama kali dibuka
 * (lihat ambilAtauBuat), jadi user tidak perlu setup apa pun.
 */
class UjianPeriodeModel extends Model
{
    protected $table         = 'ujian_periode';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'jenis', 'tahun_ajaran', 'semester', 'nama',
        'tanggal_mulai', 'tanggal_selesai', 'susulan_mulai', 'susulan_selesai',
        'status', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const JENIS  = ['ASTS1', 'ASAS', 'ASTS2', 'ASAT'];
    public const STATUS = ['draft', 'berjalan', 'selesai'];

    /** Label pendek untuk menu & judul halaman. */
    public const JENIS_LABEL = [
        'ASTS1' => 'ASTS 1',
        'ASAS'  => 'ASAS',
        'ASTS2' => 'ASTS 2',
        'ASAT'  => 'ASAT',
    ];

    /** Kepanjangan resmi, dipakai di kop cetakan & berita acara. */
    public const JENIS_PANJANG = [
        'ASTS1' => 'Asesmen Sumatif Tengah Semester 1',
        'ASAS'  => 'Asesmen Sumatif Akhir Semester',
        'ASTS2' => 'Asesmen Sumatif Tengah Semester 2',
        'ASAT'  => 'Asesmen Sumatif Akhir Tahun',
    ];

    /** ASTS1 & ASAS di semester Ganjil; ASTS2 & ASAT di semester Genap. */
    public const JENIS_SEMESTER = [
        'ASTS1' => 'Ganjil',
        'ASAS'  => 'Ganjil',
        'ASTS2' => 'Genap',
        'ASAT'  => 'Genap',
    ];

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural',
        'jenis'           => 'required|in_list[ASTS1,ASAS,ASTS2,ASAT]',
        'tahun_ajaran'    => 'required|max_length[20]',
        'semester'        => 'required|in_list[Ganjil,Genap]',
        'nama'            => 'permit_empty|max_length[100]',
        'tanggal_mulai'   => 'permit_empty|valid_date[Y-m-d]',
        'tanggal_selesai' => 'permit_empty|valid_date[Y-m-d]',
        'susulan_mulai'   => 'permit_empty|valid_date[Y-m-d]',
        'susulan_selesai' => 'permit_empty|valid_date[Y-m-d]',
        'status'          => 'permit_empty|in_list[draft,berjalan,selesai]',
    ];
    protected $validationMessages = [
        'jenis'        => ['required' => 'Jenis ujian wajib dipilih.'],
        'tahun_ajaran' => ['required' => 'Tahun pelajaran wajib diisi.'],
    ];

    /**
     * Terjemahkan potongan URL (mis. "asts1") jadi kode jenis ("ASTS1").
     *
     * @return string|null null bila slug tidak dikenal — pemanggil yang
     *                     memutuskan mau redirect atau menampilkan error.
     */
    public static function dariSlug(string $slug): ?string
    {
        $jenis = strtoupper(trim($slug));

        return in_array($jenis, self::JENIS, true) ? $jenis : null;
    }

    /** Kebalikan dariSlug(): "ASTS1" → "asts1", untuk dirakit jadi URL. */
    public static function keSlug(string $jenis): string
    {
        return strtolower(trim($jenis));
    }

    /**
     * Tahun pelajaran yang sedang berjalan.
     *
     * Sengaja dibaca dari `settings.academic_year`, BUKAN dari tabel
     * `tahun_ajaran` — tabel itu kosong di produksi sementara settings selalu
     * terisi. Lihat docs/DESAIN-UJIAN.md.
     */
    public function tahunBerjalan(): string
    {
        $setting = (new SettingModel())->get();

        return trim((string) ($setting['academic_year'] ?? '')) ?: date('Y') . '/' . (date('Y') + 1);
    }

    /**
     * Ambil periode satu jenis untuk satu tahun pelajaran; buat bila belum ada.
     *
     * Dipanggil saat halaman menu ujian dibuka sehingga keempat menu selalu
     * punya periode yang siap dipakai tanpa langkah setup manual.
     */
    public function ambilAtauBuat(string $jenis, ?string $tahun = null): array
    {
        $jenis = strtoupper(trim($jenis));
        if (! in_array($jenis, self::JENIS, true)) {
            throw new \InvalidArgumentException('Jenis ujian tidak dikenal: ' . $jenis);
        }
        $tahun ??= $this->tahunBerjalan();

        // withDeleted(): UNIQUE(jenis, tahun_ajaran) tetap berlaku pada baris
        // yang sudah di-soft-delete, jadi periode lama dipulihkan, bukan
        // diinsert ulang (kalau dipaksa insert akan bentrok unique index).
        //
        // findAll(1), BUKAN first(): pada model ber-soft-delete, first() yang
        // dipasangkan dengan withDeleted() membuat CI4 menyisipkan
        // "GROUP BY <tabel>.id" ke query SELECT * — sah di MariaDB lokal tapi
        // DITOLAK server ber-ONLY_FULL_GROUP_BY (default MySQL 5.7+).
        $row = $this->withDeleted()
            ->where(['jenis' => $jenis, 'tahun_ajaran' => $tahun])
            ->findAll(1)[0] ?? null;

        if ($row && $row['deleted_at'] === null) {
            return $row;
        }

        $data = [
            'jenis'        => $jenis,
            'tahun_ajaran' => $tahun,
            'semester'     => self::JENIS_SEMESTER[$jenis],
        ];

        if ($row) {
            $this->protect(false);
            $this->update($row['id'], $data + ['deleted_at' => null]);
            $this->protect(true);

            return $this->find($row['id']);
        }

        $id = $this->insert($data + ['status' => 'draft'], true);

        return $this->find($id);
    }

    /**
     * Periode satu jenis untuk tahun pelajaran yang diminta.
     *
     * Aturan yang sama dipakai web maupun API, jadi ditaruh di model supaya
     * tidak ditulis ulang di tiap controller:
     *  - tahun kosong / sama dengan tahun berjalan → dibuat otomatis,
     *  - tahun pelajaran LAIN → hanya dibaca, null bila memang belum ada
     *    (riwayat tidak boleh terisi baris kosong tak sengaja).
     */
    public function untukTahun(string $jenis, ?string $tahun = null): ?array
    {
        $berjalan = $this->tahunBerjalan();
        $tahun    = trim((string) $tahun);

        if ($tahun === '' || $tahun === $berjalan) {
            return $this->ambilAtauBuat($jenis, $berjalan);
        }

        return $this->where(['jenis' => $jenis, 'tahun_ajaran' => $tahun])->first();
    }

    /** Label tampil satu baris periode, mis. "ASTS 1 — TP 2026/2027". */
    public function label(array $row): string
    {
        if (! empty($row['nama'])) {
            return $row['nama'];
        }

        return (self::JENIS_LABEL[$row['jenis']] ?? $row['jenis']) . ' — TP ' . $row['tahun_ajaran'];
    }

    /** Riwayat periode satu jenis (tahun pelajaran terbaru lebih dulu). */
    public function riwayat(string $jenis): array
    {
        return $this->where('jenis', strtoupper($jenis))
            ->orderBy('tahun_ajaran', 'DESC')
            ->findAll();
    }

    /** Opsi dropdown [id => "ASTS 1 — TP 2026/2027"] untuk filter laporan. */
    public function options(): array
    {
        $out = [];
        foreach ($this->orderBy('tahun_ajaran', 'DESC')->orderBy('jenis', 'ASC')->findAll() as $r) {
            $out[$r['id']] = $this->label($r);
        }

        return $out;
    }
}
