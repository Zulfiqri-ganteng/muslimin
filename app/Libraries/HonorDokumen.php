<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Dokumen Honor Ujian — satu dokumen per periode ujian: daftar penerima + isian per komponen + total.
 * SATU-SATUNYA tempat aturan honor ditulis; layar, Excel, dan PDF (Fase 4) memanggil fungsi yang sama,
 * jadi angka tak mungkin berbeda antar keluaran.
 *
 * ISIAN (honor_nilai.nilai):
 *   komponen `satuan` → JUMLAH (lembar, set, hari, sesi …); rupiahnya = jumlah × tarif (tarif disalin ke dokumen).
 *   komponen `tetap`  → NOMINAL rupiah langsung.
 * TOTAL tidak disimpan; dihitung ulang setiap dibaca (hitung()). Semua uang bilangan bulat rupiah.
 *
 * Tarif & daftar komponen dokumen adalah SNAPSHOT (honor_dok_komponen) — mengubah Pengaturan Honor tidak mengubah
 * dokumen yang sudah dibuat (kecuali Admin menekan "Perbarui dari Pengaturan" pada dokumen yang belum dikunci).
 * Dokumen berstatus `dikunci` tidak bisa diubah apa pun (Fase 5 menyediakan tombol kunci).
 *
 * Semua perubahan dicatat di Audit Log. Rancangan: docs/DESAIN-HONOR.md.
 */
final class HonorDokumen
{
    public const MAKS_JUMLAH   = 99999;
    public const MAKS_NOMINAL  = 99999999;
    public const MAKS_PENERIMA = 200;

    /** Judul bawaan dokumen (persis gaya rekap sekolah); bisa diubah Admin per dokumen. */
    public const JUDUL = [
        'ASTS1' => 'HONOR ASESMEN SUMATIF TENGAH SEMESTER (ASTS) GANJIL',
        'ASAS'  => 'HONOR ASESMEN SUMATIF AKHIR SEMESTER (ASAS) GANJIL',
        'ASTS2' => 'HONOR ASESMEN SUMATIF TENGAH SEMESTER (ASTS) GENAP',
        'ASAT'  => 'HONOR ASESMEN SUMATIF AKHIR TAHUN (ASAT) GENAP',
    ];

    /** Urutan jabatan saat memilih label bawaan penerima (angka kecil = lebih utama). */
    private const RANK_KATEGORI = ['struktural' => 1, 'kurikulum' => 1, 'kesiswaan' => 1, 'pembina' => 2, 'lainnya' => 3, 'wali_kelas' => 4, 'mapel' => 5];

    /** Urutan baku jabatan di rekap sekolah (kecil = di atas). Jabatan lain: lihat urutanBawaan(). */
    private const URUTAN_KODE = ['KS' => 10, 'WK-KUR' => 30, 'WK-SIS' => 40, 'WK-HUM' => 50, 'WK-SAR' => 60, 'KAPROG' => 70, 'OP' => 130, 'GMP' => 210, 'WALI' => 220, 'GP' => 300, 'TU' => 400];

    /**
     * Urutan bawaan menurut NAMA jabatan, untuk jabatan yang kodenya tak dikenal (mis. dibuat sendiri oleh sekolah).
     * Dicek berurutan; yang cocok pertama dipakai. Angkanya mengikuti urutan di rekap Excel sekolah (Kepala Sekolah,
     * Kepala Tata Usaha, para Waka, Kaprog, lalu Koordinator BK, Pembina OSIS, Kepala Lab, Kepala Perpustakaan, Operator).
     * Admin bisa menimpa angka ini per jabatan di Pengaturan Honor.
     */
    private const URUTAN_NAMA = [
        '/^kepala\s+sekolah$/u'                        => 10,
        '/kepala\s+tata\s+usaha|^ktu$|^kepala\s+tu$/u' => 20,
        '/^wakil\s+kepala\s+sekolah/u'                 => 65,
        '/koordinator\s+(?:bk|bimbingan)/u'            => 90,
        '/pembina\s+osis/u'                            => 100,
        '/kepala\s+lab/u'                              => 110,
        '/kepala\s+perpus/u'                           => 120,
    ];

    /** Urutan bawaan sebuah jabatan (tanpa pengaturan Admin): kode baku → nama jabatan → 500 + level. */
    public static function urutanBawaan(array $j): int
    {
        $kode = strtoupper(trim((string) ($j['kode'] ?? '')));
        if (isset(self::URUTAN_KODE[$kode])) {
            return self::URUTAN_KODE[$kode];
        }
        $nama = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) ($j['nama'] ?? ''))));
        foreach (self::URUTAN_NAMA as $pola => $u) {
            if (preg_match($pola, $nama) === 1) {
                return $u;
            }
        }

        return 500 + (int) ($j['level'] ?? 0);
    }

    /** Urutan efektif: yang diatur Admin (urut_honor) bila ada, selain itu urutan bawaan. */
    private static function urutanJabatan(array $j): int
    {
        $diatur = $j['urut_honor'] ?? null;

        return $diatur !== null && $diatur !== '' ? (int) $diatur : self::urutanBawaan($j);
    }

    /**
     * Peringkat orang yang BELUM punya jabatan apa pun: disamakan dengan Guru Mata Pelajaran (atau Staf Tata Usaha
     * bila ditandai bukan pengajar), supaya tidak melompat ke atas daftar.
     *
     * @return array{label:string, rank:int, level:int, panitia:int, struktural:bool}
     */
    private static function tanpaJabatan(array $g): array
    {
        $staf = (int) ($g['bukan_pengajar'] ?? 0) === 1;

        return [
            'label' => $staf ? 'Staf Tata Usaha' : 'Guru Mata Pelajaran', 'rank' => self::URUTAN_KODE[$staf ? 'TU' : 'GMP'],
            'level' => $staf ? 6 : 5, 'panitia' => 0, 'struktural' => false,
        ];
    }

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Pembantu murni
    // =================================================================

    /**
     * Rupiah tiap komponen & total satu baris.
     *
     * @param list<array<string,mixed>> $komponen baris honor_dok_komponen (id, tipe, tarif)
     * @param array<int,int>            $nilai    dok_komponen_id => isian
     *
     * @return array{per:array<int,int>, total:int}
     */
    public static function hitung(array $komponen, array $nilai): array
    {
        $per   = [];
        $total = 0;
        foreach ($komponen as $k) {
            $id = (int) $k['id'];
            $n  = (int) ($nilai[$id] ?? 0);
            $rp = $k['tipe'] === 'tetap' ? $n : $n * (int) $k['tarif'];
            $per[$id] = $rp;
            $total   += $rp;
        }

        return ['per' => $per, 'total' => $total];
    }

    /** Jumlah hari ujian = hari dari tanggal mulai s/d selesai periode, tanpa hari Minggu; 0 bila belum diisi. */
    public static function hariUjian(?string $mulai, ?string $selesai): int
    {
        if (! $mulai || ! $selesai) {
            return 0;
        }
        try {
            $a = new \DateTimeImmutable($mulai);
            $b = new \DateTimeImmutable($selesai);
        } catch (\Throwable) {
            return 0;
        }
        if ($b < $a || $a->diff($b)->days > 60) {
            return 0;
        }
        $n = 0;
        for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) {
            if ($d->format('w') !== '0') {
                $n++;
            }
        }

        return $n;
    }

    /** Nama jabatan panjang → label ringkas gaya rekap sekolah ("Waka Kurikulum", "Kaprog"). */
    public static function labelSingkat(string $nama): string
    {
        $nama = trim($nama);
        // Singkatan seperti di rekap Excel sekolah ("Waka. Kurikulum", "Kaprog.", "Guru Mata Pelajaran" dibiarkan utuh).
        $peta = [
            '/^Wakil Kepala Sekolah Bidang\s+Kurikulum$/iu'                => 'Waka. Kurikulum',
            '/^Wakil Kepala Sekolah Bidang\s+Kesiswaan$/iu'                => 'Waka. Kesiswaan',
            '/^Wakil Kepala Sekolah Bidang\s+Sarana(?: dan)? Prasarana$/iu' => 'Waka. Sarpras',
            '/^Wakil Kepala Sekolah Bidang\s+Hubungan Masyarakat$/iu'      => 'Waka. Humas & Hubungan Industri',
            '/^Wakil Kepala Sekolah Bidang\s+/iu'                          => 'Waka. ',
            '/^Ketua Program Keahlian\b/iu'                                => 'Kaprog.',
            '/^GURU PIKET$/iu'                                             => 'Guru Piket',
            '/^OPERATOR SEKOLAH$/iu'                                       => 'Operator Sekolah',
        ];
        foreach ($peta as $pola => $ganti) {
            $nama = (string) preg_replace($pola, $ganti, $nama);
        }

        return mb_substr(trim($nama), 0, 120);
    }

    // =================================================================
    // Baca
    // =================================================================

    public function dokumenPeriode(int $periodeId): ?array
    {
        return $this->db->table('honor_dokumen')->where('periode_id', $periodeId)->get()->getRowArray();
    }

    public function dokumenId(int $id): ?array
    {
        return $this->db->table('honor_dokumen')->where('id', $id)->get()->getRowArray();
    }

    /**
     * Dokumen lengkap beserta total — bahan layar, Excel, dan PDF.
     *
     * @return array{dokumen:array<string,mixed>, komponen:list<array<string,mixed>>, baris:list<array<string,mixed>>,
     *               total_komponen:array<int,int>, total_jumlah:array<int,int>, total:int}|null
     */
    public function muat(int $dokumenId): ?array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return null;
        }
        $komponen = $this->db->table('honor_dok_komponen')->where('dokumen_id', $dokumenId)
            ->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $baris = $this->db->table('honor_baris')->where('dokumen_id', $dokumenId)
            ->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        $nilaiPer = [];
        if ($baris !== []) {
            $rows = $this->db->table('honor_nilai')->whereIn('baris_id', array_map('intval', array_column($baris, 'id')))->get()->getResultArray();
            foreach ($rows as $r) {
                $nilaiPer[(int) $r['baris_id']][(int) $r['dok_komponen_id']] = [
                    'nilai'     => (int) $r['nilai'],
                    'otomatis'  => $r['otomatis_nilai'] === null ? null : (int) $r['otomatis_nilai'],
                    'manual'    => (int) ($r['manual'] ?? 0) === 1,
                ];
            }
        }

        $totalKomp   = array_fill_keys(array_map('intval', array_column($komponen, 'id')), 0);
        $totalJumlah = $totalKomp;
        $total       = 0;
        foreach ($baris as &$b) {
            $b['id'] = (int) $b['id'];
            $isi     = $nilaiPer[$b['id']] ?? [];
            $angka   = [];
            foreach ($komponen as $k) {
                $angka[(int) $k['id']] = (int) ($isi[(int) $k['id']]['nilai'] ?? 0);
            }
            $h          = self::hitung($komponen, $angka);
            $b['nilai'] = $isi;
            $b['per']   = $h['per'];
            $b['total'] = $h['total'];
            foreach ($h['per'] as $kid => $rp) {
                $totalKomp[$kid]   += $rp;
                $totalJumlah[$kid] += $angka[$kid];
            }
            $total += $h['total'];
        }
        unset($b);

        return ['dokumen' => $dok, 'komponen' => $komponen, 'baris' => $baris, 'total_komponen' => $totalKomp, 'total_jumlah' => $totalJumlah, 'total' => $total];
    }

    /**
     * Guru yang belum ada di dokumen (calon penerima), lengkap dengan label jabatan bawaan.
     *
     * @return list<array<string,mixed>>
     */
    public function calonPenerima(int $dokumenId): array
    {
        $sudah = array_map('intval', array_column(
            $this->db->table('honor_baris')->select('guru_id')->where('dokumen_id', $dokumenId)->where('guru_id IS NOT NULL')->get()->getResultArray(),
            'guru_id'
        ));
        $b = $this->db->table('guru')->select('id, kode_guru, nama, bukan_pengajar')->where('deleted_at', null)->where('induk_id', null);
        if ($sudah !== []) {
            $b->whereNotIn('id', $sudah);
        }
        $rows = $b->orderBy('nama', 'ASC')->get()->getResultArray();
        $info = $this->infoJabatan(array_map('intval', array_column($rows, 'id')));
        foreach ($rows as &$r) {
            $r['jabatan'] = $info[(int) $r['id']]['label'] ?? ((int) $r['bukan_pengajar'] === 1 ? 'Staf Tata Usaha' : 'Guru Mata Pelajaran');
        }
        unset($r);

        return $rows;
    }

    /** Ringkasan semua dokumen (untuk pilihan "salin penerima dari …"). */
    public function dokumenLain(int $kecualiPeriodeId): array
    {
        return $this->db->table('honor_dokumen d')
            ->select('d.id, p.jenis, p.tahun_ajaran, (SELECT COUNT(*) FROM honor_baris b WHERE b.dokumen_id = d.id) AS jml')
            ->join('ujian_periode p', 'p.id = d.periode_id')
            ->where('d.periode_id !=', $kecualiPeriodeId)
            ->orderBy('p.tahun_ajaran', 'DESC')->orderBy('p.jenis', 'ASC')
            ->get()->getResultArray();
    }

    // =================================================================
    // Tulis — mengembalikan ['ok' => bool, 'pesan' => string, …]
    // =================================================================

    /**
     * Buat dokumen honor untuk satu periode ujian.
     *
     * @param array<string,mixed> $periode baris ujian_periode
     * @param int $salinDari id dokumen sumber (0 = mulai kosong): menyalin daftar penerima, label, dan isian TETAP
     */
    public function buat(array $periode, int $salinDari = 0): array
    {
        $periodeId = (int) $periode['id'];
        $jenis     = (string) $periode['jenis'];
        if ($this->dokumenPeriode($periodeId) !== null) {
            return $this->gagal('Honor untuk periode ini sudah ada.');
        }
        $komponen = (new HonorPengaturan($this->db))->komponen(true, $jenis);
        if ($komponen === []) {
            return $this->gagal('Belum ada komponen honor yang aktif untuk ujian ini. Aktifkan dulu di Pengaturan Honor.');
        }
        $sumber = null;
        if ($salinDari > 0) {
            $sumber = $this->dokumenId($salinDari);
            if ($sumber === null || (int) $sumber['periode_id'] === $periodeId) {
                return $this->gagal('Honor sumber salinan tidak ditemukan.');
            }
        }

        $tt     = (new HonorPengaturan($this->db))->tandaTangan();
        $set    = $this->db->table('settings')->select('city, headmaster_name')->get()->getRowArray() ?? [];
        $now    = date('Y-m-d H:i:s');
        $oleh   = $this->pelaku();
        $label  = $this->labelPeriode($periode);

        $this->db->transStart();
        $this->db->table('honor_dokumen')->insert([
            'periode_id'     => $periodeId,
            'status'         => 'draf',
            'judul'          => self::JUDUL[$jenis] ?? 'HONOR UJIAN',
            'tempat'         => $this->potong((string) ($set['city'] ?? ''), 80) ?: null,
            'tanggal'        => date('Y-m-d'),
            'ketua_nama'     => $tt['ketua_nama'],
            'bendahara_nama' => $tt['bendahara_nama'],
            'kepsek_nama'    => $this->potong((string) ($set['headmaster_name'] ?? ''), 150) ?: null,
            'dibuat_oleh'    => $oleh,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $dokId = (int) $this->db->insertID();

        $dkPeta = []; // komponen_id => dok_komponen_id
        foreach ($komponen as $k) {
            $this->db->table('honor_dok_komponen')->insert($this->salinKomponen($dokId, $k));
            $dkPeta[(int) $k['id']] = (int) $this->db->insertID();
        }

        $disalin = 0;
        if ($sumber !== null) {
            $disalin = $this->salinPenerima((int) $sumber['id'], $dokId, $dkPeta);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal membuat honor. Coba lagi.');
        }

        $this->audit('create', 'honor_dokumen', $dokId, 'Buat honor ' . $label . ' (' . count($komponen) . ' komponen' . ($disalin > 0 ? ', ' . $disalin . ' penerima disalin' : '') . ')');

        return ['ok' => true, 'pesan' => 'Honor ' . $label . ' dibuat.' . ($disalin > 0 ? ' ' . $disalin . ' penerima disalin dari honor sebelumnya.' : ''), 'id' => $dokId];
    }

    /**
     * Ubah judul, tempat, tanggal, dan nama penanda tangan.
     *
     * @param array<string,mixed> $in
     */
    public function simpanDokumen(int $dokumenId, array $in): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $judul = HonorPengaturan::rapikan((string) ($in['judul'] ?? ''));
        if ($judul === '' || mb_strlen($judul) > 200) {
            return $this->gagal('Judul wajib diisi (maksimal 200 huruf).');
        }
        $tempat = HonorPengaturan::rapikan((string) ($in['tempat'] ?? ''));
        if (mb_strlen($tempat) > 80) {
            return $this->gagal('Tempat terlalu panjang (maksimal 80 huruf).');
        }
        $tanggal = trim((string) ($in['tanggal'] ?? ''));
        if ($tanggal !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
            if (! $d || $d->format('Y-m-d') !== $tanggal || $d->format('Y') < 2000 || $d->format('Y') > 2100) {
                return $this->gagal('Tanggal tidak sah.');
            }
        }
        $nama = [];
        foreach (['ketua_nama' => 'Ketua panitia', 'bendahara_nama' => 'Bendahara', 'kepsek_nama' => 'Kepala Sekolah'] as $kunci => $label) {
            $v = IsianBantu::rapikanGelar(HonorPengaturan::rapikan((string) ($in[$kunci] ?? '')));
            if ($v !== '' && (mb_strlen($v) > 150 || ! IsianBantu::namaOrangSah($v))) {
                return $this->gagal($label . ': nama hanya boleh huruf, titik, koma, tanda petik, dan strip (maksimal 150 huruf).');
            }
            $nama[$kunci] = $v !== '' ? $v : null;
        }

        $this->db->table('honor_dokumen')->where('id', $dokumenId)->update([
            'judul' => $judul, 'tempat' => $tempat !== '' ? $tempat : null, 'tanggal' => $tanggal !== '' ? $tanggal : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ] + $nama);
        $this->audit('update', 'honor_dokumen', $dokumenId, 'Ubah data surat honor ' . $this->labelDokumen($dokumenId));

        return ['ok' => true, 'pesan' => 'Data honor disimpan.'];
    }

    /**
     * Tambah penerima dari Master Guru. Yang sudah ada dilewati.
     *
     * @param list<int|string> $guruIds
     */
    public function tambahPenerima(int $dokumenId, array $guruIds): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $guruIds = array_values(array_unique(array_filter(array_map('intval', $guruIds), static fn (int $i): bool => $i > 0)));
        if ($guruIds === []) {
            return $this->gagal('Pilih minimal satu orang.');
        }
        $guru = $this->db->table('guru')->select('id, nama, bukan_pengajar')->whereIn('id', $guruIds)->where('deleted_at', null)->get()->getResultArray();
        if (count($guru) !== count($guruIds)) {
            return $this->gagal('Ada orang yang tidak ditemukan di Master Guru. Muat ulang halaman lalu coba lagi.');
        }
        $ada = array_map('intval', array_column(
            $this->db->table('honor_baris')->select('guru_id')->where('dokumen_id', $dokumenId)->get()->getResultArray(),
            'guru_id'
        ));
        $baru = array_values(array_filter($guru, static fn (array $g): bool => ! in_array((int) $g['id'], $ada, true)));
        if ($baru === []) {
            return ['ok' => true, 'pesan' => 'Semua orang yang dipilih sudah ada di daftar.', 'jumlah' => 0];
        }
        $jml = (int) $this->db->table('honor_baris')->where('dokumen_id', $dokumenId)->countAllResults();
        if ($jml + count($baru) > self::MAKS_PENERIMA) {
            return $this->gagal('Penerima melebihi batas ' . self::MAKS_PENERIMA . ' orang.');
        }

        $info     = $this->infoJabatan(array_map('intval', array_column($baru, 'id')));
        usort($baru, static function (array $a, array $b) use ($info): int {
            $ia = $info[(int) $a['id']] ?? self::tanpaJabatan($a);
            $ib = $info[(int) $b['id']] ?? self::tanpaJabatan($b);
            if ($ia['rank'] !== $ib['rank']) {
                return $ia['rank'] <=> $ib['rank'];
            }

            return $ia['level'] !== $ib['level'] ? $ia['level'] <=> $ib['level'] : strcasecmp((string) $a['nama'], (string) $b['nama']);
        });

        $komponen = $this->db->table('honor_dok_komponen')->where('dokumen_id', $dokumenId)->get()->getResultArray();
        $periode  = $this->db->table('ujian_periode')->where('id', $dok['periode_id'])->get()->getRowArray() ?? [];
        $hari     = self::hariUjian($periode['tanggal_mulai'] ?? null, $periode['tanggal_selesai'] ?? null);
        $urut     = (int) ($this->db->table('honor_baris')->selectMax('urut')->where('dokumen_id', $dokumenId)->get()->getRow()->urut ?? 0);
        $now      = date('Y-m-d H:i:s');

        $this->db->transStart();
        foreach ($baru as $g) {
            $gi = $info[(int) $g['id']] ?? self::tanpaJabatan($g);
            $this->db->table('honor_baris')->insert([
                'dokumen_id' => $dokumenId, 'guru_id' => (int) $g['id'], 'nama' => mb_substr((string) $g['nama'], 0, 150),
                'jabatan' => $gi['label'], 'urut' => ++$urut, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $barisId = (int) $this->db->insertID();
            $this->isiAwal($barisId, $komponen, (int) $gi['panitia'], (bool) $gi['struktural'] ? $hari : 0);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menambah penerima. Coba lagi.');
        }
        $this->audit('create', 'honor_baris', $dokumenId, 'Tambah ' . count($baru) . ' penerima ke honor ' . $this->labelDokumen($dokumenId));

        return ['ok' => true, 'pesan' => count($baru) . ' penerima ditambahkan.', 'jumlah' => count($baru)];
    }

    public function hapusBaris(int $dokumenId, int $barisId): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $b = $this->db->table('honor_baris')->where('id', $barisId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($b === null) {
            return $this->gagal('Penerima tidak ditemukan.');
        }
        $this->db->table('honor_baris')->where('id', $barisId)->delete(); // nilai ikut terhapus (CASCADE)
        $this->audit('delete', 'honor_baris', $barisId, 'Hapus penerima "' . $b['nama'] . '" dari honor ' . $this->labelDokumen($dokumenId));

        return ['ok' => true, 'pesan' => '"' . $b['nama'] . '" dihapus dari daftar.'];
    }

    /**
     * Pindahkan seorang penerima ke nomor urut $posisi (1 = paling atas); penerima lain bergeser dan semua nomor
     * dirapatkan 1..n. Urutan ini dipakai layar, Excel, dan PDF, serta ikut tersalin ke honor berikutnya.
     *
     * @return array{ok:bool, pesan:string, urutan?:list<int>, posisi?:int}
     */
    public function pindahKe(int $dokumenId, int $barisId, int $posisi): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $rows = $this->db->table('honor_baris')->select('id, nama, urut')->where('dokumen_id', $dokumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $ids  = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $idx  = array_search($barisId, $ids, true);
        if ($idx === false) {
            return $this->gagal('Penerima tidak ditemukan.');
        }
        $posisi = max(1, min(count($ids), $posisi));
        $nama   = (string) $rows[$idx]['nama'];
        array_splice($ids, (int) $idx, 1);
        array_splice($ids, $posisi - 1, 0, [$barisId]);

        $awal = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        if ($ids === $awal && (int) $rows[0]['urut'] === 1) {
            return ['ok' => true, 'pesan' => 'Urutan tidak berubah.', 'urutan' => $ids, 'posisi' => $posisi];
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        foreach ($ids as $i => $id) {
            $this->db->table('honor_baris')->where('id', $id)->update(['urut' => $i + 1, 'updated_at' => $now]);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal memindahkan. Coba lagi.');
        }
        if ($ids !== $awal) {
            $this->audit('update', 'honor_baris', $barisId, 'Pindah "' . $nama . '" ke nomor ' . $posisi . ' di honor ' . $this->labelDokumen($dokumenId));
        }

        return ['ok' => true, 'pesan' => '"' . $nama . '" dipindah ke nomor ' . $posisi . '.', 'urutan' => $ids, 'posisi' => $posisi];
    }

    // =================================================================
    // Atur ulang urutan: menurut aturan jabatan, atau mengikuti Excel sekolah.
    // Hanya nomor urut (dan label jabatan bila diminta) yang berubah — angka isian TIDAK pernah disentuh.
    // Alur aman: rencana*() hanya menghitung (pratinjau), terapkanUrutan() baru menulis.
    // =================================================================

    /** Baris dokumen + data Master Guru untuk perencanaan (urutan sekarang = urut, id). */
    private function barisPerencanaan(int $dokumenId): array
    {
        return $this->db->table('honor_baris b')
            ->select('b.id, b.guru_id, b.nama, b.jabatan, b.urut, g.id AS guru_ada, g.nama AS guru_nama, g.bukan_pengajar')
            ->join('guru g', 'g.id = b.guru_id AND g.deleted_at IS NULL', 'left')
            ->where('b.dokumen_id', $dokumenId)
            ->orderBy('b.urut', 'ASC')->orderBy('b.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * @param list<array<string,mixed>> $rows     baris dokumen menurut urutan sekarang
     * @param list<int>                 $idsBaru  id baris menurut urutan baru (harus memuat semua baris)
     * @param array<int,string>         $labelBaru id baris → label jabatan baru (hanya baris yang labelnya mau diganti)
     *
     * @return array{baris:list<array<string,mixed>>, pindah:int, label:int}
     */
    private function susunRencana(array $rows, array $idsBaru, array $labelBaru): array
    {
        $lama = [];
        foreach ($rows as $i => $r) {
            $lama[(int) $r['id']] = ['pos' => $i + 1, 'r' => $r];
        }
        $baris = [];
        $pindah = $label = 0;
        foreach ($idsBaru as $i => $id) {
            $r       = $lama[$id]['r'];
            $jabLama = trim((string) ($r['jabatan'] ?? ''));
            $jabBaru = array_key_exists($id, $labelBaru) ? trim((string) $labelBaru[$id]) : $jabLama;
            $geser   = $lama[$id]['pos'] !== $i + 1;
            $beda    = $jabBaru !== $jabLama;
            $pindah += $geser ? 1 : 0;
            $label  += $beda ? 1 : 0;
            $baris[] = [
                'id' => $id, 'nama' => (string) $r['nama'], 'posisi_lama' => $lama[$id]['pos'], 'posisi_baru' => $i + 1, 'urut_lama' => (int) $r['urut'],
                'jabatan_lama' => $jabLama, 'jabatan_baru' => $jabBaru, 'pindah' => $geser, 'label_beda' => $beda,
            ];
        }

        return ['baris' => $baris, 'pindah' => $pindah, 'label' => $label];
    }

    /**
     * Rencana urutan menurut ATURAN jabatan — hasilnya sama dengan honor yang baru dibuat: jabatan (urutan di Pengaturan
     * Honor / bawaan), lalu level, lalu abjad. Label jabatan diambil dari Master Guru. Penerima yang tautan Master
     * Gurunya sudah tak ada tetap di bawah dengan urutan lama dan labelnya tak diubah. Urutan manual lama DIGANTI.
     *
     * @return array{baris:list<array<string,mixed>>, pindah:int, label:int}
     */
    public function rencanaUrutanAturan(int $dokumenId): array
    {
        $rows  = $this->barisPerencanaan($dokumenId);
        $ids   = [];
        foreach ($rows as $r) {
            if ($r['guru_ada'] !== null) {
                $ids[] = (int) $r['guru_ada'];
            }
        }
        $info  = $this->infoJabatan($ids);
        $item  = [];
        $label = [];
        foreach ($rows as $i => $r) {
            if ($r['guru_ada'] === null) {
                $item[] = ['id' => (int) $r['id'], 'rank' => 99999, 'level' => 0, 'nama' => '', 'i' => $i];
                continue;
            }
            $gi = $info[(int) $r['guru_ada']] ?? self::tanpaJabatan($r);
            $label[(int) $r['id']] = (string) $gi['label'];
            $item[] = ['id' => (int) $r['id'], 'rank' => (int) $gi['rank'], 'level' => (int) $gi['level'], 'nama' => (string) $r['guru_nama'], 'i' => $i];
        }
        usort($item, static function (array $a, array $b): int {
            return [$a['rank'], $a['level']] <=> [$b['rank'], $b['level']] ?: (strcasecmp($a['nama'], $b['nama']) ?: $a['i'] <=> $b['i']);
        });

        return $this->susunRencana($rows, array_column($item, 'id'), $label);
    }

    /**
     * Rencana urutan mengikuti EXCEL sekolah. Yang cocok dipindah ke nomor sesuai Excel (label jabatan = tulisan di
     * Excel), yang tidak ada di Excel ditaruh di bawah dengan urutan lama.
     *
     * @param list<array<string,mixed>> $excel baris Excel dalam urutannya (kunci 'nama', 'jabatan')
     *
     * @return array{baris:list<array<string,mixed>>, pindah:int, label:int, tak_ada_di_dokumen:list<string>, tak_ada_di_excel:list<string>, ganda:list<string>}
     */
    public function rencanaUrutanExcel(int $dokumenId, array $excel): array
    {
        $rows  = $this->barisPerencanaan($dokumenId);
        $m     = HonorImpor::cocokkanUrutan($excel, $rows);
        $label = [];
        foreach ($m['cocok'] as $iExcel => $id) {
            $j = trim((string) ($excel[$iExcel]['jabatan'] ?? ''));
            if ($j !== '') {
                $label[$id] = mb_substr($j, 0, 120);
            }
        }

        return $this->susunRencana($rows, $m['urut'], $label) + ['tak_ada_di_dokumen' => $m['tak_ada_di_dokumen'], 'tak_ada_di_excel' => $m['tak_ada_di_excel'], 'ganda' => $m['ganda']];
    }

    /**
     * Terapkan rencana (hasil rencanaUrutan*, dihitung ulang server saat menerapkan). Semua nomor urut ditulis ulang
     * rapat 1..n dalam satu transaksi; label jabatan hanya bila $labelJuga.
     */
    public function terapkanUrutan(int $dokumenId, array $rencana, bool $labelJuga, string $sumber): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $ada = array_map('intval', array_column($this->db->table('honor_baris')->select('id')->where('dokumen_id', $dokumenId)->get()->getResultArray(), 'id'));
        $ids = array_map(static fn (array $b): int => (int) $b['id'], $rencana['baris']);
        sort($ada);
        $cek = $ids;
        sort($cek);
        if ($ada !== $cek) {
            return $this->gagal('Daftar penerima berubah sejak pratinjau. Buka pratinjau lagi.');
        }
        $pindah = (int) $rencana['pindah'];
        $label  = $labelJuga ? (int) $rencana['label'] : 0;
        if ($pindah === 0 && $label === 0) {
            return ['ok' => true, 'pesan' => 'Tidak ada yang perlu diubah — urutan' . ($labelJuga ? ' dan jabatan' : '') . ' sudah sesuai.', 'pindah' => 0, 'label' => 0];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        foreach ($rencana['baris'] as $b) {
            $ubah = [];
            if ((int) $b['urut_lama'] !== (int) $b['posisi_baru']) {
                $ubah['urut'] = (int) $b['posisi_baru'];
            }
            if ($labelJuga && $b['label_beda']) {
                $ubah['jabatan'] = $b['jabatan_baru'] !== '' ? mb_substr((string) $b['jabatan_baru'], 0, 120) : null;
            }
            if ($ubah !== []) {
                $this->db->table('honor_baris')->where('id', (int) $b['id'])->where('dokumen_id', $dokumenId)->update($ubah + ['updated_at' => $now]);
            }
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal mengatur ulang urutan. Tidak ada yang berubah.');
        }
        $this->audit('update', 'honor_dokumen', $dokumenId, 'Atur ulang urutan honor ' . $this->labelDokumen($dokumenId) . ' (' . $sumber . '): ' . $pindah . ' orang berpindah nomor'
            . ($labelJuga ? ', ' . $label . ' label jabatan diganti' : ''));

        return ['ok' => true, 'pesan' => 'Urutan diperbarui: ' . $pindah . ' orang berpindah nomor' . ($labelJuga ? ', ' . $label . ' label jabatan diganti' : '') . '. Angka isian tidak diubah.', 'pindah' => $pindah, 'label' => $label];
    }

    /** Ubah label jabatan dan/atau catatan satu penerima. */
    public function ubahBaris(int $dokumenId, int $barisId, ?string $jabatan, ?string $catatan = null): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $b = $this->db->table('honor_baris')->where('id', $barisId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($b === null) {
            return $this->gagal('Penerima tidak ditemukan.');
        }
        $ubah = [];
        if ($jabatan !== null) {
            $jabatan = HonorPengaturan::rapikan($jabatan);
            if (mb_strlen($jabatan) > 120) {
                return $this->gagal('Jabatan terlalu panjang (maksimal 120 huruf).');
            }
            $ubah['jabatan'] = $jabatan !== '' ? $jabatan : null;
        }
        if ($catatan !== null) {
            $catatan = HonorPengaturan::rapikan($catatan);
            if (mb_strlen($catatan) > 255) {
                return $this->gagal('Catatan terlalu panjang (maksimal 255 huruf).');
            }
            $ubah['catatan'] = $catatan !== '' ? $catatan : null;
        }
        if ($ubah === [] || array_intersect_assoc($ubah, $b) === $ubah) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.', 'jabatan' => $b['jabatan']];
        }
        $this->db->table('honor_baris')->where('id', $barisId)->update($ubah + ['updated_at' => date('Y-m-d H:i:s')]);
        $this->audit('update', 'honor_baris', $barisId, 'Ubah ' . (isset($ubah['jabatan']) ? 'jabatan' : 'catatan') . ' "' . $b['nama'] . '" di honor ' . $this->labelDokumen($dokumenId));

        return ['ok' => true, 'pesan' => 'Tersimpan.', 'jabatan' => $ubah['jabatan'] ?? $b['jabatan']];
    }

    /**
     * Simpan SATU isian (sel). Mengembalikan angka resmi dari server (rupiah sel, total baris, total kolom, total semua)
     * supaya layar menampilkan hasil hitungan server, bukan perkiraan sendiri.
     */
    public function simpanNilai(int $dokumenId, int $barisId, int $dokKomponenId, mixed $isi): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $baris = $this->db->table('honor_baris')->where('id', $barisId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        $komp  = $this->db->table('honor_dok_komponen')->where('id', $dokKomponenId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($baris === null || $komp === null) {
            return $this->gagal('Isian tidak ditemukan. Muat ulang halaman lalu coba lagi.');
        }

        $teks = trim((string) $isi);
        $n    = $teks === '' ? 0 : HonorPengaturan::angka($teks);
        $maks = $komp['tipe'] === 'tetap' ? self::MAKS_NOMINAL : self::MAKS_JUMLAH;
        if ($n === null || $n > $maks) {
            return $this->gagal($komp['tipe'] === 'tetap'
                ? 'Nominal tidak sah. Isi angka bulat rupiah 0 sampai ' . number_format($maks, 0, ',', '.') . ', tanpa koma.'
                : 'Jumlah tidak sah. Isi bilangan bulat 0 sampai ' . number_format($maks, 0, ',', '.') . ', tanpa koma.');
        }

        $lama = $this->db->table('honor_nilai')->where('baris_id', $barisId)->where('dok_komponen_id', $dokKomponenId)->get()->getRowArray();
        $nilaiLama = (int) ($lama['nilai'] ?? 0);
        if ($lama === null) {
            $this->db->table('honor_nilai')->insert(['baris_id' => $barisId, 'dok_komponen_id' => $dokKomponenId, 'nilai' => $n, 'otomatis_nilai' => null, 'manual' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        } elseif ($nilaiLama !== $n) {
            // Diketik sama persis dengan hitungan otomatis → kembali dianggap otomatis; selain itu = diisi manual.
            $manual = $lama['otomatis_nilai'] !== null && (int) $lama['otomatis_nilai'] === $n ? 0 : 1;
            $this->db->table('honor_nilai')->where('id', $lama['id'])->update(['nilai' => $n, 'manual' => $manual, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        if ($nilaiLama !== $n) {
            $this->audit('update', 'honor_nilai', $barisId, 'Honor ' . $this->labelDokumen($dokumenId) . ': ' . $baris['nama'] . ' — ' . $komp['nama'] . ' ' . number_format($nilaiLama, 0, ',', '.') . '→' . number_format($n, 0, ',', '.'));
        }

        return ['ok' => true, 'pesan' => 'Tersimpan.'] + $this->angkaResmi($dokumenId, $barisId, $dokKomponenId);
    }

    /**
     * Perbarui komponen dokumen dari Pengaturan Honor: tarif/nama/urutan terbaru, komponen aktif baru ditambahkan,
     * komponen yang kini tidak berlaku dibuang HANYA bila semua isiannya 0 (tak ada data yang hilang).
     */
    public function sinkronKomponen(int $dokumenId): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $periode = $this->db->table('ujian_periode')->where('id', $dok['periode_id'])->get()->getRowArray() ?? [];
        $jenis   = (string) ($periode['jenis'] ?? '');
        $aturan  = [];
        foreach ((new HonorPengaturan($this->db))->komponen() as $k) {
            $aturan[(int) $k['id']] = $k;
        }
        $berlaku = [];
        foreach ((new HonorPengaturan($this->db))->komponen(true, $jenis) as $k) {
            $berlaku[(int) $k['id']] = $k;
        }
        $dkAda = [];
        foreach ($this->db->table('honor_dok_komponen')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $r) {
            $dkAda[(int) $r['komponen_id']] = $r;
        }

        $diubah = $baru = $dibuang = $dipertahankan = 0;
        $this->db->transStart();
        foreach ($dkAda as $kid => $dk) {
            $a = $aturan[$kid] ?? null;
            if ($a === null || ! isset($berlaku[$kid])) { // dimatikan / tak berlaku / terhapus dari pengaturan
                $adaIsi = (int) $this->db->table('honor_nilai')->where('dok_komponen_id', $dk['id'])->where('nilai >', 0)->countAllResults() > 0;
                if ($adaIsi) {
                    $dipertahankan++;
                } else {
                    $this->db->table('honor_dok_komponen')->where('id', $dk['id'])->delete();
                    $dibuang++;
                }
                continue;
            }
            $baruIsi = ['kode' => $a['kode'], 'nama' => $a['nama'], 'judul_cetak' => $a['judul_cetak'] ?? null, 'tipe' => $a['tipe'], 'tarif' => (int) $a['tarif'], 'satuan' => $a['satuan'], 'sumber' => $a['sumber'], 'urut' => (int) $a['urut']];
            $lama = ['kode' => $dk['kode'], 'nama' => $dk['nama'], 'judul_cetak' => $dk['judul_cetak'] ?? null, 'tipe' => $dk['tipe'], 'tarif' => (int) $dk['tarif'], 'satuan' => $dk['satuan'], 'sumber' => $dk['sumber'], 'urut' => (int) $dk['urut']];
            if ($baruIsi !== $lama) {
                $this->db->table('honor_dok_komponen')->where('id', $dk['id'])->update($baruIsi);
                $diubah++;
            }
        }
        foreach ($berlaku as $kid => $k) {
            if (isset($dkAda[$kid])) {
                continue;
            }
            $this->db->table('honor_dok_komponen')->insert($this->salinKomponen($dokumenId, $k));
            $dkId = (int) $this->db->insertID();
            foreach ($this->db->table('honor_baris')->select('id')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $b) {
                $this->db->table('honor_nilai')->insert(['baris_id' => (int) $b['id'], 'dok_komponen_id' => $dkId, 'nilai' => 0, 'otomatis_nilai' => null, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $baru++;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal memperbarui komponen. Coba lagi.');
        }
        if ($diubah + $baru + $dibuang === 0) {
            return ['ok' => true, 'pesan' => 'Komponen sudah sama dengan Pengaturan Honor.' . ($dipertahankan > 0 ? ' (' . $dipertahankan . ' komponen sudah dimatikan tetapi dipertahankan karena berisi angka.)' : '')];
        }
        $this->audit('update', 'honor_dokumen', $dokumenId, 'Perbarui komponen honor ' . $this->labelDokumen($dokumenId) . ": $diubah diubah, $baru ditambah, $dibuang dibuang");

        return ['ok' => true, 'pesan' => "Komponen diperbarui: $diubah diubah, $baru ditambah, $dibuang dibuang."
            . ($dipertahankan > 0 ? " $dipertahankan komponen yang sudah dimatikan tetap dipertahankan karena berisi angka." : '')];
    }

    public function hapusDokumen(int $dokumenId): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (($tolak = $this->tolakKunci($dok)) !== null) {
            return $tolak;
        }
        $label = $this->labelDokumen($dokumenId);
        $jml   = (int) $this->db->table('honor_baris')->where('dokumen_id', $dokumenId)->countAllResults();
        $this->db->table('honor_dokumen')->where('id', $dokumenId)->delete(); // komponen, baris, nilai ikut terhapus (CASCADE)
        $this->audit('delete', 'honor_dokumen', $dokumenId, 'Hapus honor ' . $label . " ($jml penerima)");

        return ['ok' => true, 'pesan' => 'Honor ' . $label . ' dihapus.'];
    }

    // =================================================================
    // Status: draf → final → dikunci
    // =================================================================

    public const STATUS = ['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'];

    /**
     * Sidik jari isi honor (HMAC-SHA256 atas komponen, tarif, penerima, dan semua isian). Bila isi berubah sedikit pun
     * — termasuk lewat database langsung — sidik jari berubah. Tidak memuat status/waktu.
     */
    public static function sidikJari(array $m): string
    {
        $k = [];
        foreach ($m['komponen'] as $kom) {
            $k[] = [(string) $kom['kode'], (string) $kom['tipe'], (int) $kom['tarif']];
        }
        $b = [];
        foreach ($m['baris'] as $br) {
            $n = [];
            foreach ($m['komponen'] as $kom) {
                $n[(string) $kom['kode']] = (int) ($br['nilai'][(int) $kom['id']]['nilai'] ?? 0);
            }
            $b[] = [$br['guru_id'] === null ? null : (int) $br['guru_id'], (string) $br['nama'], (string) ($br['jabatan'] ?? ''), $n];
        }
        $kanonik = json_encode(['dok' => (int) $m['dokumen']['id'], 'periode' => (int) $m['dokumen']['periode_id'], 'k' => $k, 'b' => $b], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $kunci   = (string) (config(\Config\Encryption::class)->key ?? '');

        return $kunci !== '' ? hash_hmac('sha256', (string) $kanonik, $kunci) : hash('sha256', (string) $kanonik);
    }

    /**
     * Apakah isi honor yang DIKUNCI masih sama dengan saat dikunci? null = tidak dikunci / tak ada sidik jari.
     */
    public static function utuh(array $m): ?bool
    {
        $d = $m['dokumen'];
        if ($d['status'] !== 'dikunci' || empty($d['kunci_hash'])) {
            return null;
        }

        return hash_equals((string) $d['kunci_hash'], self::sidikJari($m)) && (int) $d['kunci_total'] === (int) $m['total'];
    }

    /**
     * Ubah status. Aturan: draf→final, final→draf, final→dikunci, dikunci→final (WAJIB alasan ≥ 5 huruf).
     * Final/kunci butuh minimal satu penerima. Mengunci menyimpan sidik jari & total; membuka kunci mengosongkannya.
     */
    public function ubahStatus(int $dokumenId, string $ke, ?string $alasan = null): array
    {
        $dok = $this->dokumenId($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if (! isset(self::STATUS[$ke])) {
            return $this->gagal('Status tidak dikenal.');
        }
        $dari = (string) $dok['status'];
        if ($dari === $ke) {
            return $this->gagal('Honor sudah berstatus ' . self::STATUS[$ke] . '.');
        }
        $boleh = ['draf' => ['final'], 'final' => ['draf', 'dikunci'], 'dikunci' => ['final']];
        if (! in_array($ke, $boleh[$dari], true)) {
            return $this->gagal($dari === 'draf' ? 'Tandai Final dulu sebelum mengunci.' : ($dari === 'dikunci' ? 'Honor terkunci hanya bisa dibuka kuncinya (kembali ke Final).' : 'Perpindahan status tidak diizinkan.'));
        }
        $m = $this->muat($dokumenId);
        if (in_array($ke, ['final', 'dikunci'], true) && $m['baris'] === []) {
            return $this->gagal('Belum ada penerima. Tambahkan penerima dulu.');
        }
        $alasan = HonorPengaturan::rapikan((string) $alasan);
        if ($dari === 'dikunci' && (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 200)) {
            return $this->gagal('Membuka kunci wajib disertai alasan (5–200 huruf). Alasan dicatat di Audit Log.');
        }

        $ubah = ['status' => $ke, 'updated_at' => date('Y-m-d H:i:s')];
        if ($ke === 'dikunci') {
            $ubah += ['dikunci_at' => date('Y-m-d H:i:s'), 'dikunci_oleh' => $this->pelaku(), 'kunci_hash' => self::sidikJari($m), 'kunci_total' => (int) $m['total']];
        } elseif ($dari === 'dikunci') {
            $ubah += ['dikunci_at' => null, 'dikunci_oleh' => null, 'kunci_hash' => null, 'kunci_total' => null];
        }
        $this->db->table('honor_dokumen')->where('id', $dokumenId)->update($ubah);
        $this->audit('update', 'honor_dokumen', $dokumenId, 'Status honor ' . $this->labelDokumen($dokumenId) . ': ' . self::STATUS[$dari] . ' → ' . self::STATUS[$ke]
            . ($ke === 'dikunci' ? ' (total Rp ' . number_format((int) $m['total'], 0, ',', '.') . ', ' . count($m['baris']) . ' penerima)' : '')
            . ($dari === 'dikunci' ? ' — alasan buka kunci: ' . $alasan : ''));

        return ['ok' => true, 'pesan' => match ($ke) {
            'final'   => $dari === 'dikunci' ? 'Kunci dibuka. Honor kembali berstatus Final dan bisa diubah.' : 'Honor ditandai Final.',
            'dikunci' => 'Honor DIKUNCI. Isinya tidak bisa diubah lagi.',
            default   => 'Honor dikembalikan ke Draf.',
        }];
    }

    /**
     * Sisipkan satu penerima beserta SEMUA selnya bernilai 0 (dipakai impor Excel). Pemanggil bertanggung jawab atas
     * transaksi, pemeriksaan status dokumen, dan audit. @return int id baris
     */
    public function sisipBaris(int $dokumenId, ?int $guruId, string $nama, ?string $jabatan, int $urut): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('honor_baris')->insert([
            'dokumen_id' => $dokumenId, 'guru_id' => $guruId, 'nama' => mb_substr($nama, 0, 150), 'jabatan' => $jabatan !== null && $jabatan !== '' ? mb_substr($jabatan, 0, 120) : null,
            'urut' => max(1, min(65000, $urut)), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $barisId = (int) $this->db->insertID();
        foreach ($this->db->table('honor_dok_komponen')->select('id')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $k) {
            $this->db->table('honor_nilai')->insert(['baris_id' => $barisId, 'dok_komponen_id' => (int) $k['id'], 'nilai' => 0, 'otomatis_nilai' => null, 'updated_at' => $now]);
        }

        return $barisId;
    }

    /** Set isian langsung tanpa audit per sel (impor): dianggap manual (otomatis_nilai dikosongkan). */
    public function setNilaiLangsung(int $barisId, int $dokKomponenId, int $nilai): void
    {
        $now  = date('Y-m-d H:i:s');
        $ada  = $this->db->table('honor_nilai')->where('baris_id', $barisId)->where('dok_komponen_id', $dokKomponenId)->get()->getRowArray();
        if ($ada === null) {
            $this->db->table('honor_nilai')->insert(['baris_id' => $barisId, 'dok_komponen_id' => $dokKomponenId, 'nilai' => $nilai, 'otomatis_nilai' => null, 'manual' => 1, 'updated_at' => $now]);
        } else {
            $this->db->table('honor_nilai')->where('id', $ada['id'])->update(['nilai' => $nilai, 'otomatis_nilai' => null, 'manual' => 1, 'updated_at' => $now]);
        }
    }

    // =================================================================
    // Internal
    // =================================================================

    /** Angka resmi setelah sebuah sel berubah (dipakai layar). */
    private function angkaResmi(int $dokumenId, int $barisId, int $dokKomponenId): array
    {
        $m = $this->muat($dokumenId);
        $b = null;
        foreach ($m['baris'] as $baris) {
            if ($baris['id'] === $barisId) {
                $b = $baris;
                break;
            }
        }

        return [
            'nilai'          => (int) ($b['nilai'][$dokKomponenId]['nilai'] ?? 0),
            'otomatis'       => $b['nilai'][$dokKomponenId]['otomatis'] ?? null,
            'rupiah'         => (int) ($b['per'][$dokKomponenId] ?? 0),
            'total_baris'    => (int) ($b['total'] ?? 0),
            'total_komponen' => (int) ($m['total_komponen'][$dokKomponenId] ?? 0),
            'total_jumlah'   => (int) ($m['total_jumlah'][$dokKomponenId] ?? 0),
            'total'          => (int) $m['total'],
        ];
    }

    private function salinKomponen(int $dokId, array $k): array
    {
        return [
            'dokumen_id' => $dokId, 'komponen_id' => (int) $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'judul_cetak' => $k['judul_cetak'] ?? null, 'tipe' => $k['tipe'],
            'tarif' => (int) $k['tarif'], 'satuan' => $k['satuan'], 'sumber' => $k['sumber'], 'urut' => (int) $k['urut'],
        ];
    }

    /** Isi awal semua sel satu baris baru: tunjangan panitia dari tabel jabatan, transport = hari ujian (struktural), sisanya 0. */
    private function isiAwal(int $barisId, array $komponen, int $nominalPanitia, int $hariTransport): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($komponen as $k) {
            $n = 0;
            if ($k['kode'] === 'tunj_panitia') {
                $n = $nominalPanitia;
            } elseif ($k['kode'] === 'transport') {
                $n = $hariTransport;
            }
            $this->db->table('honor_nilai')->insert(['baris_id' => $barisId, 'dok_komponen_id' => (int) $k['id'], 'nilai' => $n, 'otomatis_nilai' => null, 'updated_at' => $now]);
        }
    }

    /** Salin penerima + isian tetap dari dokumen lain. @return int jumlah penerima disalin */
    private function salinPenerima(int $dariDokId, int $keDokId, array $dkPeta): int
    {
        $sumberDk = [];
        foreach ($this->db->table('honor_dok_komponen')->where('dokumen_id', $dariDokId)->get()->getResultArray() as $r) {
            $sumberDk[(int) $r['id']] = $r;
        }
        $baruKomp = $this->db->table('honor_dok_komponen')->where('dokumen_id', $keDokId)->get()->getResultArray();
        $now = date('Y-m-d H:i:s');
        $n   = 0;
        foreach ($this->db->table('honor_baris')->where('dokumen_id', $dariDokId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $b) {
            $this->db->table('honor_baris')->insert([
                'dokumen_id' => $keDokId, 'guru_id' => $b['guru_id'], 'nama' => $b['nama'], 'jabatan' => $b['jabatan'], 'catatan' => null,
                'urut' => (int) $b['urut'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $barisBaru = (int) $this->db->insertID();
            // isian TETAP (tunjangan) ikut disalin; isian satuan dimulai 0
            $nilaiSumber = [];
            foreach ($this->db->table('honor_nilai')->where('baris_id', $b['id'])->get()->getResultArray() as $v) {
                $dk = $sumberDk[(int) $v['dok_komponen_id']] ?? null;
                if ($dk !== null) {
                    $nilaiSumber[(int) $dk['komponen_id']] = (int) $v['nilai'];
                }
            }
            foreach ($baruKomp as $k) {
                $n0 = ($k['tipe'] === 'tetap') ? (int) ($nilaiSumber[(int) $k['komponen_id']] ?? 0) : 0;
                $this->db->table('honor_nilai')->insert(['baris_id' => $barisBaru, 'dok_komponen_id' => (int) $k['id'], 'nilai' => $n0, 'otomatis_nilai' => null, 'updated_at' => $now]);
            }
            $n++;
        }

        return $n;
    }

    /**
     * Label jabatan bawaan & nominal panitia per guru.
     *
     * @param list<int> $guruIds
     *
     * @return array<int, array{label:string, rank:int, level:int, panitia:int, struktural:bool}>
     */
    private function infoJabatan(array $guruIds): array
    {
        if ($guruIds === []) {
            return [];
        }
        // Tabel urutan (migrasi HonorUrutanJabatan) belum tentu sudah ada saat kode baru lebih dulu dipasang: tanpa tabelnya
        // dipakai urutan bawaan, halaman Honor tetap jalan.
        $adaUrutan = $this->db->tableExists('honor_jabatan_urutan');
        $q = $this->db->table('guru_jabatan gj')
            ->select('gj.guru_id, gj.is_utama, j.id AS jid, j.kode, j.nama, j.kategori, j.level, j.is_struktural, COALESCE(hp.nominal, 0) AS panitia, ' . ($adaUrutan ? 'hu.urutan AS urut_honor' : 'NULL AS urut_honor'))
            ->join('jabatan j', 'j.id = gj.jabatan_id')
            ->join('honor_panitia_jabatan hp', 'hp.jabatan_id = j.id', 'left');
        if ($adaUrutan) {
            $q->join('honor_jabatan_urutan hu', 'hu.jabatan_id = j.id', 'left');
        }
        $rows = $q->whereIn('gj.guru_id', $guruIds)->get()->getResultArray();
        $per = [];
        foreach ($rows as $r) {
            $per[(int) $r['guru_id']][] = $r;
        }
        $hasil = [];
        foreach ($per as $gid => $daftar) {
            usort($daftar, static function (array $a, array $b): int {
                $ra = self::urutanJabatan($a);
                $rb = self::urutanJabatan($b);
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                return (int) $b['is_utama'] <=> (int) $a['is_utama'];
            });
            $pilih = $daftar[0];
            $hasil[$gid] = [
                'label'      => self::labelSingkat((string) $pilih['nama']),
                'rank'       => self::urutanJabatan($pilih),
                'level'      => (int) $pilih['level'],
                'panitia'    => (int) max(array_map(static fn (array $r): int => (int) $r['panitia'], $daftar)),
                'struktural' => (bool) array_filter($daftar, static fn (array $r): bool => (int) $r['is_struktural'] === 1),
            ];
        }

        return $hasil;
    }

    private function tolakKunci(array $dok): ?array
    {
        return $dok['status'] === 'dikunci' ? $this->gagal('Honor ini sudah DIKUNCI dan tidak bisa diubah.') : null;
    }

    private function labelPeriode(array $periode): string
    {
        return (new UjianPeriodeModel())->label($periode);
    }

    /** Label periode sebuah dokumen (mis. "ASTS 1 — TP 2026/2027") untuk pesan dan audit. */
    public function labelPublik(int $dokumenId): string
    {
        return $this->labelDokumen($dokumenId);
    }

    private function labelDokumen(int $dokumenId): string
    {
        $p = $this->db->table('honor_dokumen d')->select('p.*')->join('ujian_periode p', 'p.id = d.periode_id')->where('d.id', $dokumenId)->get()->getRowArray();

        return $p !== null ? $this->labelPeriode($p) : ('#' . $dokumenId);
    }

    private function pelaku(): string
    {
        return mb_substr((string) (session('admin')['full_name'] ?? 'Admin'), 0, 150);
    }

    private function potong(string $s, int $maks): string
    {
        return mb_substr(HonorPengaturan::rapikan($s), 0, $maks);
    }

    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'pesan' => $pesan];
    }

    private function audit(string $aksi, string $tabel, ?int $id, string $deskripsi): void
    {
        (new AuditModel())->record($aksi, $tabel, $id, $deskripsi);
    }
}
