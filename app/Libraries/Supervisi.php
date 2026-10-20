<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\SupervisiButirModel;
use App\Models\SupervisiInstrumenModel;
use App\Models\SupervisiJadwalModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Supervisi akademik guru (menu KURIKULUM → Supervisi Guru; rancangan docs/DESAIN-SUPERVISI.md). SATU sumber logika untuk halaman
 * admin (Admin\SupervisiInstrumen, Admin\SupervisiJadwal) dan API Android (S4).
 *
 *   - INSTRUMEN = lembar penilaian (bawaan 3: Perencanaan, Pelaksanaan, Penilaian), tiap instrumen punya BUTIR berkelompok aspek.
 *     Instrumen yang sudah dipakai jadwal/hasil tidak bisa dihapus (nonaktifkan saja). Butir boleh dihapus: hasil penilaian
 *     menyimpan SALINAN aspek & uraian butirnya (S2), jadi hasil lama tidak berubah.
 *   - JADWAL = satu kunjungan supervisi: guru, mapel & kelas yang diamati (opsional), supervisor, tanggal (+ jam), instrumen yang
 *     dipakai. Nama guru/NIP/mapel/kelas/supervisor DISALIN. Tahun ajaran & semester dihitung dari tanggal (Jul–Des = ganjil tahun
 *     itu/berikutnya, Jan–Jun = genap tahun sebelumnya/itu). Satu guru boleh beberapa kunjungan per semester, tetapi tidak dua
 *     kunjungan (yang tidak batal) pada tanggal yang sama.
 *   - Jadwal yang sudah ada penilaiannya tidak bisa dihapus (batalkan saja), gurunya tidak bisa diganti, dan instrumen yang sudah
 *     dinilai tidak bisa dicabut dari jadwalnya.
 * Hasil gagal membawa 'kode' 404|409|422 (dipakai API: tolakHasil). Pencatat = ['id' => ?int admins.id, 'nama' => string].
 */
final class Supervisi
{
    public const SEMESTER = ['ganjil' => 'Ganjil', 'genap' => 'Genap'];
    public const STATUS   = ['dijadwalkan' => 'Dijadwalkan', 'selesai' => 'Selesai', 'batal' => 'Batal'];
    public const JENIS    = ['perencanaan' => 'Perencanaan', 'pelaksanaan' => 'Pelaksanaan', 'penilaian' => 'Penilaian', 'lainnya' => 'Lainnya'];

    public const MAKS_KODE       = 40;
    public const MAKS_NAMA       = 150;
    public const MAKS_KETERANGAN = 255;
    public const MAKS_ASPEK      = 150;
    public const MAKS_URAIAN     = 500;
    public const MAKS_CATATAN    = 255;
    /** Buat massal: paling banyak kunjungan per hari (bawaan & batas atas). */
    public const MASSAL_PER_HARI = 3;
    public const MASSAL_MAKS_PER_HARI = 10;
    public const MASSAL_MAKS_HARI     = 184;

    /**
     * Instrumen bawaan (dipasang migrasi bila tabel instrumen kosong; tombol "Pasang instrumen bawaan" memasang yang belum ada).
     * Butir: [aspek, uraian].
     */
    public const INSTRUMEN_BAWAAN = [
        [
            'kode' => 'PERENCANAAN', 'jenis' => 'perencanaan', 'nama' => 'Perencanaan Pembelajaran (Modul Ajar)',
            'keterangan' => 'Telaah modul ajar/RPP sebelum pembelajaran.',
            'butir' => [
                ['Identitas & Tujuan', 'Identitas modul ajar (satuan pendidikan, fase/kelas, mata pelajaran, alokasi waktu) lengkap.'],
                ['Identitas & Tujuan', 'Capaian pembelajaran dan tujuan pembelajaran dirumuskan jelas dan terukur.'],
                ['Identitas & Tujuan', 'Asesmen awal/diagnostik direncanakan untuk mengetahui kesiapan belajar siswa.'],
                ['Langkah Pembelajaran', 'Model/metode pembelajaran sesuai tujuan dan memberi ruang pembelajaran berdiferensiasi.'],
                ['Langkah Pembelajaran', 'Kegiatan pendahuluan memuat apersepsi, motivasi, dan penyampaian tujuan.'],
                ['Langkah Pembelajaran', 'Kegiatan inti berpusat pada siswa dan tersusun runtut.'],
                ['Langkah Pembelajaran', 'Kegiatan penutup memuat kesimpulan, refleksi, dan tindak lanjut.'],
                ['Media & Asesmen', 'Media, alat, dan sumber belajar sesuai tujuan dan bervariasi.'],
                ['Media & Asesmen', 'Rencana asesmen formatif dan sumatif dilengkapi rubrik/pedoman penskoran.'],
                ['Media & Asesmen', 'Lampiran lengkap (LKPD, bahan bacaan, glosarium, daftar pustaka).'],
                ['Media & Asesmen', 'Refleksi guru dan siswa direncanakan.'],
            ],
        ],
        [
            'kode' => 'PELAKSANAAN', 'jenis' => 'pelaksanaan', 'nama' => 'Pelaksanaan Pembelajaran (Observasi Kelas)',
            'keterangan' => 'Pengamatan langsung saat guru mengajar di kelas.',
            'butir' => [
                ['Kegiatan Pendahuluan', 'Melakukan apersepsi dan memberi motivasi belajar.'],
                ['Kegiatan Pendahuluan', 'Menyampaikan tujuan pembelajaran dan kegiatan yang akan dilakukan.'],
                ['Kegiatan Inti', 'Menguasai materi pelajaran dan menyampaikannya dengan jelas.'],
                ['Kegiatan Inti', 'Menerapkan pembelajaran yang berpusat pada siswa.'],
                ['Kegiatan Inti', 'Melayani perbedaan kebutuhan belajar siswa (diferensiasi).'],
                ['Kegiatan Inti', 'Memanfaatkan media pembelajaran/TIK secara efektif.'],
                ['Kegiatan Inti', 'Membangun interaksi dan mengajukan pertanyaan pemantik.'],
                ['Kegiatan Inti', 'Memberikan umpan balik yang membangun.'],
                ['Kegiatan Inti', 'Mengelola kelas dan waktu dengan baik.'],
                ['Kegiatan Inti', 'Menguatkan nilai-nilai Profil Pelajar Pancasila.'],
                ['Kegiatan Penutup', 'Bersama siswa menyimpulkan dan merefleksikan pembelajaran.'],
                ['Kegiatan Penutup', 'Melakukan asesmen di akhir pembelajaran dan menyampaikan tindak lanjut.'],
            ],
        ],
        [
            'kode' => 'PENILAIAN', 'jenis' => 'penilaian', 'nama' => 'Penilaian/Asesmen Pembelajaran',
            'keterangan' => 'Telaah instrumen, pengolahan, dan tindak lanjut hasil penilaian.',
            'butir' => [
                ['Perencanaan Asesmen', 'Teknik penilaian sesuai dengan tujuan pembelajaran.'],
                ['Perencanaan Asesmen', 'Menggunakan ragam teknik dan instrumen penilaian.'],
                ['Perencanaan Asesmen', 'Memiliki rubrik/pedoman penskoran yang jelas.'],
                ['Pengolahan & Tindak Lanjut', 'Mengolah dan mencatat hasil penilaian secara tertib.'],
                ['Pengolahan & Tindak Lanjut', 'Memberikan umpan balik hasil penilaian kepada siswa.'],
                ['Pengolahan & Tindak Lanjut', 'Melaksanakan tindak lanjut hasil penilaian (remedial/pengayaan).'],
            ],
        ],
    ];

    private SupervisiInstrumenModel $instrumen;
    private SupervisiButirModel $butir;
    private SupervisiJadwalModel $jadwal;
    private string $saluran;

    /** @param string $saluran 'web' | 'aplikasi' (API Android: Audit Log diberi "(via aplikasi)"). */
    public function __construct(string $saluran = 'web')
    {
        $this->instrumen = new SupervisiInstrumenModel();
        $this->butir     = new SupervisiButirModel();
        $this->jadwal    = new SupervisiJadwalModel();
        $this->saluran   = $saluran;
    }

    private function audit(string $aksi, string $tabel, int $id, string $teks): void
    {
        (new AuditModel())->record($aksi, $tabel, $id, mb_substr($teks . ($this->saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));
    }

    /** @return array{ok: false, kode: int, pesan: string} */
    private static function gagal(int $kode, string $pesan): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan];
    }

    // =================================================================
    // Periode (tahun ajaran & semester)
    // =================================================================

    /**
     * Tahun ajaran & semester sebuah tanggal: Juli–Desember 2026 → 2026/2027 ganjil; Januari–Juni 2027 → 2026/2027 genap.
     *
     * @return array{tahun_ajaran: string, semester: string}
     */
    public static function periode(string $tanggal): array
    {
        $t = strtotime($tanggal) ?: time();
        $y = (int) date('Y', $t);

        return (int) date('n', $t) >= 7
            ? ['tahun_ajaran' => $y . '/' . ($y + 1), 'semester' => 'ganjil']
            : ['tahun_ajaran' => ($y - 1) . '/' . $y, 'semester' => 'genap'];
    }

    /** @return array{tahun_ajaran: string, semester: string} */
    public static function periodeSekarang(): array
    {
        return self::periode(date('Y-m-d'));
    }

    /** "2026/2027" (format tahun ajaran) atau null. */
    public static function tahunAjaranSah(?string $ta): ?string
    {
        $ta = trim((string) $ta);

        return preg_match('/^(\d{4})\/(\d{4})$/', $ta, $m) && (int) $m[2] === (int) $m[1] + 1 ? $ta : null;
    }

    /** Tanggal Y-m-d yang sah (boleh masa depan — jadwal); null bila tidak. */
    public static function tanggalSah(?string $t): ?string
    {
        $t = trim((string) $t);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $t;
    }

    /** "Semester Ganjil 2026/2027". */
    public static function labelPeriode(string $ta, string $semester): string
    {
        return 'Semester ' . (self::SEMESTER[$semester] ?? ucfirst($semester)) . ' ' . $ta;
    }

    /**
     * Tahun ajaran yang bisa dipilih di saringan: tahun ajaran sekarang + yang ada di jadwal (terbaru dulu).
     *
     * @return list<string>
     */
    public function pilihanTahunAjaran(): array
    {
        $ada = array_column($this->jadwal->select('tahun_ajaran')->distinct()->findAll(), 'tahun_ajaran');
        $ada[] = self::periodeSekarang()['tahun_ajaran'];
        $ada   = array_values(array_unique(array_filter(array_map('strval', $ada))));
        rsort($ada);

        return $ada;
    }

    // =================================================================
    // Instrumen
    // =================================================================

    /**
     * Semua instrumen (urut) + jumlah butir (aktif/semua) + 'dipakai' (jadwal atau hasil memakainya → tidak bisa dihapus).
     *
     * @return list<array<string, mixed>>
     */
    public function daftarInstrumen(bool $hanyaAktif = false): array
    {
        $q = $this->instrumen->orderBy('urut', 'ASC')->orderBy('id', 'ASC');
        if ($hanyaAktif) {
            $q->where('aktif', 1);
        }
        $rows = $q->findAll();
        if ($rows === []) {
            return [];
        }
        $jumlah = [];
        foreach ($this->butir->select('instrumen_id, aktif, COUNT(*) n')->groupBy(['instrumen_id', 'aktif'])->findAll() as $b) {
            $jumlah[(int) $b['instrumen_id']][(int) $b['aktif']] = (int) $b['n'];
        }
        $dipakai = $this->idInstrumenDipakai();

        return array_map(static fn (array $r): array => $r + [
            'butir_aktif' => $jumlah[(int) $r['id']][1] ?? 0,
            'butir_semua' => array_sum($jumlah[(int) $r['id']] ?? []),
            'dipakai'     => isset($dipakai[(int) $r['id']]),
        ], $rows);
    }

    public function cariInstrumen(int $id): ?array
    {
        return $id > 0 ? $this->instrumen->find($id) : null;
    }

    /** @return array<int, true> id instrumen yang dipakai jadwal mana pun atau hasil penilaian. */
    private function idInstrumenDipakai(): array
    {
        $out = [];
        foreach ($this->jadwal->select('instrumen')->findAll() as $j) {
            foreach (self::idDariJson($j['instrumen']) as $id) {
                $out[$id] = true;
            }
        }
        $db = db_connect();
        if ($db->tableExists('supervisi_nilai')) {
            foreach ($db->table('supervisi_nilai')->select('instrumen_id')->distinct()->where('instrumen_id IS NOT NULL')->get()->getResultArray() as $n) {
                $out[(int) $n['instrumen_id']] = true;
            }
        }

        return $out;
    }

    /** @return list<int> */
    public static function idDariJson(mixed $json): array
    {
        $a = is_array($json) ? $json : json_decode((string) $json, true);

        return is_array($a) ? array_values(array_unique(array_filter(array_map('intval', $a), static fn (int $i): bool => $i > 0))) : [];
    }

    /**
     * Tambah ($id = 0) atau ubah instrumen. Isian: kode, nama, jenis, keterangan, urut, aktif.
     *
     * @return array{ok: bool, pesan: string, kode?: int, id?: int}
     */
    public function simpanInstrumen(array $data, int $id = 0): array
    {
        $lama = null;
        if ($id > 0 && ($lama = $this->cariInstrumen($id)) === null) {
            return self::gagal(404, 'Instrumen tidak ditemukan.');
        }
        $kode  = mb_strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '-', trim((string) ($data['kode'] ?? ''))) ?? '');
        $kode  = trim($kode, '-');
        $nama  = IsianBantu::rapikan((string) ($data['nama'] ?? ''));
        $jenis = (string) ($data['jenis'] ?? '');
        $ket   = IsianBantu::rapikan((string) ($data['keterangan'] ?? ''));
        if ($kode === '' || mb_strlen($kode) > self::MAKS_KODE) {
            return self::gagal(422, 'Kode instrumen wajib diisi (huruf/angka, maks ' . self::MAKS_KODE . ').');
        }
        if (mb_strlen($nama) < 3 || mb_strlen($nama) > self::MAKS_NAMA) {
            return self::gagal(422, 'Nama instrumen wajib diisi (3–' . self::MAKS_NAMA . ' huruf).');
        }
        if (! isset(self::JENIS[$jenis])) {
            return self::gagal(422, 'Pilih jenis instrumen.');
        }
        if (mb_strlen($ket) > self::MAKS_KETERANGAN) {
            return self::gagal(422, 'Keterangan terlalu panjang (maks ' . self::MAKS_KETERANGAN . ' huruf).');
        }
        $kembar = $this->instrumen->where('kode', $kode)->where('id !=', $id)->first();
        if ($kembar !== null) {
            return self::gagal(409, 'Kode ' . $kode . ' sudah dipakai instrumen "' . $kembar['nama'] . '".');
        }
        $baris = [
            'kode'       => $kode,
            'nama'       => $nama,
            'jenis'      => $jenis,
            'keterangan' => $ket !== '' ? $ket : null,
            'aktif'      => self::ya($data['aktif'] ?? null) ? 1 : 0,
            'urut'       => isset($data['urut']) && $data['urut'] !== '' ? max(0, (int) $data['urut'])
                : ($lama['urut'] ?? ((int) ($this->instrumen->builder()->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0) + 1)),
        ];
        if ($id > 0) {
            $this->instrumen->update($id, $baris);
            $this->audit('update', 'supervisi_instrumen', $id, 'Ubah instrumen supervisi: ' . $nama);

            return ['ok' => true, 'pesan' => 'Instrumen "' . $nama . '" diperbarui.', 'id' => $id];
        }
        $this->instrumen->insert($baris);
        $id = (int) $this->instrumen->getInsertID();
        $this->audit('create', 'supervisi_instrumen', $id, 'Tambah instrumen supervisi: ' . $nama);

        return ['ok' => true, 'pesan' => 'Instrumen "' . $nama . '" ditambahkan. Isi butir penilaiannya.', 'id' => $id];
    }

    /** @return array{ok: bool, pesan: string, kode?: int} */
    public function hapusInstrumen(int $id): array
    {
        $row = $this->cariInstrumen($id);
        if ($row === null) {
            return self::gagal(404, 'Instrumen tidak ditemukan.');
        }
        if (isset($this->idInstrumenDipakai()[$id])) {
            return self::gagal(409, 'Instrumen "' . $row['nama'] . '" sudah dipakai jadwal/hasil supervisi — tidak bisa dihapus. Nonaktifkan saja (Ubah → matikan Aktif).');
        }
        $this->instrumen->delete($id); // butir ikut terhapus (FK CASCADE)
        $this->audit('delete', 'supervisi_instrumen', $id, 'Hapus instrumen supervisi: ' . $row['nama']);

        return ['ok' => true, 'pesan' => 'Instrumen "' . $row['nama'] . '" dihapus.'];
    }

    /**
     * Pasang instrumen bawaan yang BELUM ADA (dicocokkan dengan kode; yang ada tidak disentuh). Kode bawaan yang sudah dipakai
     * instrumen lain → instrumen bawaan dipasang dengan kode berakhiran tanggal.
     *
     * @return array{ok: bool, pesan: string, jumlah: int}
     */
    public function pasangBawaan(): array
    {
        $n = self::isiBawaan(db_connect(), false);
        if ($n === 0) {
            return ['ok' => true, 'pesan' => 'Ketiga instrumen bawaan sudah ada — tidak ada yang ditambahkan.', 'jumlah' => 0];
        }
        $this->audit('create', 'supervisi_instrumen', 0, 'Pasang ' . $n . ' instrumen supervisi bawaan');

        return ['ok' => true, 'pesan' => $n . ' instrumen bawaan ditambahkan.', 'jumlah' => $n];
    }

    /**
     * Tulis instrumen bawaan + butirnya. $semua = true (migrasi, tabel kosong): pasang ketiganya; false: hanya yang kodenya belum ada
     * di antara instrumen AKTIF (kode terpakai instrumen nonaktif → kode baru berakhiran tanggal). Mengembalikan jumlah instrumen baru.
     */
    public static function isiBawaan(BaseConnection $db, bool $semua = true): int
    {
        $now  = date('Y-m-d H:i:s');
        $urut = (int) ($db->table('supervisi_instrumen')->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0);
        $n    = 0;
        foreach (self::INSTRUMEN_BAWAAN as $ins) {
            $kode = $ins['kode'];
            if (! $semua) {
                $ada = $db->table('supervisi_instrumen')->where('kode', $kode)->get()->getRowArray();
                if ($ada !== null && (int) $ada['aktif'] === 1) {
                    continue;
                }
                if ($ada !== null) {
                    $kode .= '-' . date('Ymd');
                    if ($db->table('supervisi_instrumen')->where('kode', $kode)->countAllResults() > 0) {
                        continue;
                    }
                }
            }
            $db->table('supervisi_instrumen')->insert([
                'kode' => $kode, 'nama' => $ins['nama'], 'jenis' => $ins['jenis'], 'keterangan' => $ins['keterangan'],
                'aktif' => 1, 'urut' => ++$urut, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id    = (int) $db->insertID();
            $baris = [];
            foreach ($ins['butir'] as $i => [$aspek, $uraian]) {
                $baris[] = ['instrumen_id' => $id, 'aspek' => $aspek, 'uraian' => $uraian, 'urut' => $i + 1, 'aktif' => 1, 'created_at' => $now, 'updated_at' => $now];
            }
            $db->table('supervisi_butir')->insertBatch($baris);
            $n++;
        }

        return $n;
    }

    // =================================================================
    // Butir instrumen
    // =================================================================

    /** @return list<array<string, mixed>> butir satu instrumen, urut. */
    public function daftarButir(int $instrumenId, bool $hanyaAktif = false): array
    {
        $q = $this->butir->where('instrumen_id', $instrumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC');
        if ($hanyaAktif) {
            $q->where('aktif', 1);
        }

        return $q->findAll();
    }

    /** @return list<string> nama aspek yang sudah dipakai instrumen ini (saran isian). */
    public function daftarAspek(int $instrumenId): array
    {
        return array_values(array_unique(array_column($this->daftarButir($instrumenId), 'aspek')));
    }

    /**
     * Tambah ($id = 0) atau ubah butir. Isian: aspek, uraian, aktif. Butir baru ditaruh paling bawah.
     *
     * @return array{ok: bool, pesan: string, kode?: int, id?: int, instrumen_id?: int}
     */
    public function simpanButir(int $instrumenId, array $data, int $id = 0): array
    {
        if ($id > 0) {
            $lama = $this->butir->find($id);
            if ($lama === null) {
                return self::gagal(404, 'Butir tidak ditemukan.');
            }
            $instrumenId = (int) $lama['instrumen_id'];
        }
        $ins = $this->cariInstrumen($instrumenId);
        if ($ins === null) {
            return self::gagal(404, 'Instrumen tidak ditemukan.');
        }
        $aspek  = IsianBantu::rapikan((string) ($data['aspek'] ?? ''));
        $uraian = IsianBantu::rapikan((string) ($data['uraian'] ?? ''));
        if ($aspek === '' || mb_strlen($aspek) > self::MAKS_ASPEK) {
            return self::gagal(422, 'Aspek wajib diisi (maks ' . self::MAKS_ASPEK . ' huruf), mis. "Kegiatan Inti".');
        }
        if (mb_strlen($uraian) < 5 || mb_strlen($uraian) > self::MAKS_URAIAN) {
            return self::gagal(422, 'Uraian butir wajib diisi (5–' . self::MAKS_URAIAN . ' huruf).');
        }
        $baris = ['aspek' => $aspek, 'uraian' => $uraian, 'aktif' => self::ya($data['aktif'] ?? null) ? 1 : 0];
        if ($id > 0) {
            $this->butir->update($id, $baris);
            $this->audit('update', 'supervisi_butir', $id, 'Ubah butir instrumen ' . $ins['kode'] . ': ' . $uraian);

            return ['ok' => true, 'pesan' => 'Butir diperbarui.', 'id' => $id, 'instrumen_id' => $instrumenId];
        }
        $baris += ['instrumen_id' => $instrumenId, 'urut' => (int) ($this->butir->builder()->where('instrumen_id', $instrumenId)->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0) + 1];
        $this->butir->insert($baris);
        $id = (int) $this->butir->getInsertID();
        $this->audit('create', 'supervisi_butir', $id, 'Tambah butir instrumen ' . $ins['kode'] . ': ' . $uraian);

        return ['ok' => true, 'pesan' => 'Butir ditambahkan.', 'id' => $id, 'instrumen_id' => $instrumenId];
    }

    /** @return array{ok: bool, pesan: string, kode?: int, instrumen_id?: int} */
    public function hapusButir(int $id): array
    {
        $row = $id > 0 ? $this->butir->find($id) : null;
        if ($row === null) {
            return self::gagal(404, 'Butir tidak ditemukan.');
        }
        $this->butir->delete($id);
        $this->audit('delete', 'supervisi_butir', $id, 'Hapus butir instrumen #' . $row['instrumen_id'] . ': ' . $row['uraian']);

        return ['ok' => true, 'pesan' => 'Butir dihapus. Hasil penilaian lama tetap menyimpan butir ini.', 'instrumen_id' => (int) $row['instrumen_id']];
    }

    /**
     * Geser butir satu langkah ($arah -1 = naik, 1 = turun) dalam instrumennya. Urutan dirapikan 1..n sekalian.
     *
     * @return array{ok: bool, pesan: string, kode?: int, instrumen_id?: int}
     */
    public function geserButir(int $id, int $arah): array
    {
        $row = $id > 0 ? $this->butir->find($id) : null;
        if ($row === null) {
            return self::gagal(404, 'Butir tidak ditemukan.');
        }
        $ids = array_map('intval', array_column($this->daftarButir((int) $row['instrumen_id']), 'id'));
        $i   = array_search($id, $ids, true);
        $j   = $i + ($arah < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return ['ok' => true, 'pesan' => 'Butir sudah di ' . ($arah < 0 ? 'paling atas' : 'paling bawah') . '.', 'instrumen_id' => (int) $row['instrumen_id']];
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $k => $bid) {
            $this->butir->builder()->where('id', $bid)->update(['urut' => $k + 1]);
        }

        return ['ok' => true, 'pesan' => 'Urutan butir diperbarui.', 'instrumen_id' => (int) $row['instrumen_id']];
    }

    // =================================================================
    // Guru, supervisor, mapel, kelas
    // =================================================================

    /**
     * Guru yang bisa disupervisi: tanpa data guru ganda & tanpa yang ditandai "bukan pengajar". 'nip' untuk salinan.
     *
     * @return list<array{id: int, nama: string, nip: string}>
     */
    public function pilihanGuru(): array
    {
        $peta = GuruModel::petaOrang();
        $out  = [];
        foreach ((new GuruModel())->select('id, nama, nip, bukan_pengajar')->orderBy('nama', 'ASC')->findAll() as $g) {
            if (! isset($peta[(int) $g['id']]) && (int) ($g['bukan_pengajar'] ?? 0) !== 1) {
                $out[] = ['id' => (int) $g['id'], 'nama' => (string) $g['nama'], 'nip' => trim((string) ($g['nip'] ?? ''))];
            }
        }

        return $out;
    }

    /**
     * Pilihan supervisor: 'saran' = guru berjabatan struktural (Kepala Sekolah, Wakil Kepala Sekolah — Kurikulum didahulukan —,
     * Ketua Program Keahlian; tanpa operator) dengan nama jabatannya; 'lain' = guru lainnya.
     *
     * @return array{saran: list<array{id: int, nama: string, jabatan: string}>, lain: list<array{id: int, nama: string}>}
     */
    public function pilihanSupervisor(): array
    {
        $jabatan = $this->jabatanStruktural();
        $saran   = [];
        $lain    = [];
        $peta    = GuruModel::petaOrang();
        foreach ((new GuruModel())->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $id = (int) $g['id'];
            if (isset($peta[$id])) {
                continue;
            }
            if (isset($jabatan[$id])) {
                $saran[] = ['id' => $id, 'nama' => (string) $g['nama'], 'jabatan' => $jabatan[$id]['nama'], 'peringkat' => $jabatan[$id]['peringkat']];
            } else {
                $lain[] = ['id' => $id, 'nama' => (string) $g['nama']];
            }
        }
        usort($saran, static fn (array $a, array $b): int => $a['peringkat'] <=> $b['peringkat'] ?: strcasecmp($a['nama'], $b['nama']));

        return ['saran' => array_map(static fn (array $s): array => ['id' => $s['id'], 'nama' => $s['nama'], 'jabatan' => $s['jabatan']], $saran), 'lain' => $lain];
    }

    /**
     * Jabatan struktural teratas tiap guru yang masih berlaku: guru_id => [nama, peringkat] (peringkat kecil = didahulukan).
     *
     * @return array<int, array{nama: string, peringkat: int}>
     */
    private function jabatanStruktural(): array
    {
        $db = db_connect();
        if (! $db->tableExists('guru_jabatan') || ! $db->tableExists('jabatan')) {
            return [];
        }
        $rows = $db->table('guru_jabatan gj')->select('gj.guru_id, j.nama, j.level, j.kategori')
            ->join('jabatan j', 'j.id = gj.jabatan_id')
            ->where('j.deleted_at', null)->where('j.is_struktural', 1)->notLike('j.nama', 'operator', 'both', null, true)
            ->groupStart()->where('gj.tmt_selesai', null)->orWhere('gj.tmt_selesai >=', date('Y-m-d'))->groupEnd()
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            // Kepala sekolah (level 1) → Wakasek Kurikulum → wakasek lain → Kaprog: level × 2, kurikulum dikurangi 1.
            $p = (int) $r['level'] * 2 - ($r['kategori'] === 'kurikulum' ? 1 : 0);
            $g = (int) $r['guru_id'];
            if (! isset($out[$g]) || $p < $out[$g]['peringkat']) {
                $out[$g] = ['nama' => (string) $r['nama'], 'peringkat' => $p];
            }
        }

        return $out;
    }

    /**
     * Mapel & kelas yang diampu guru (Penugasan) — saran isian jadwal, JP terbanyak dulu. Termasuk penugasan data guru gandanya.
     *
     * @return list<array{mapel_id: int, mapel: string, kelas_id: int, kelas: string, jp: int}>
     */
    public function saranPengampu(int $guruId): array
    {
        if ($guruId <= 0) {
            return [];
        }
        $ids = [$guruId];
        foreach (GuruModel::petaOrang() as $anak => $induk) {
            if ($induk === $guruId) {
                $ids[] = (int) $anak;
            }
        }
        $rows = db_connect()->table('pengampu p')
            ->select('p.mapel_id, m.nama_mapel, p.kelas_id, k.nama_kelas, SUM(p.jp) jp')
            ->join('mata_pelajaran m', 'm.id = p.mapel_id')->join('kelas k', 'k.id = p.kelas_id')
            ->whereIn('p.guru_id', $ids)->where('p.deleted_at', null)->where('m.deleted_at', null)->where('k.deleted_at', null)
            ->groupBy(['p.mapel_id', 'p.kelas_id', 'm.nama_mapel', 'k.nama_kelas'])->get()->getResultArray();
        usort($rows, static fn (array $a, array $b): int => (int) $b['jp'] <=> (int) $a['jp'] ?: strnatcasecmp($a['nama_mapel'] . $a['nama_kelas'], $b['nama_mapel'] . $b['nama_kelas']));

        return array_map(static fn (array $r): array => ['mapel_id' => (int) $r['mapel_id'], 'mapel' => (string) $r['nama_mapel'],
            'kelas_id' => (int) $r['kelas_id'], 'kelas' => (string) $r['nama_kelas'], 'jp' => (int) $r['jp']], $rows);
    }

    /** @return list<array{id: int, nama: string}> */
    public function pilihanMapel(): array
    {
        $rows = db_connect()->table('mata_pelajaran')->select('id, nama_mapel')->where('deleted_at', null)->orderBy('nama_mapel', 'ASC')->get()->getResultArray();

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama' => (string) $r['nama_mapel']], $rows);
    }

    /** @return list<array{id: int, nama: string}> urut tingkat lalu nama alami */
    public function pilihanKelas(): array
    {
        $rows = db_connect()->table('kelas')->select('id, nama_kelas, tingkat')->where('deleted_at', null)->get()->getResultArray();
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['tingkat'], (string) $b['tingkat']) ?: strnatcasecmp((string) $a['nama_kelas'], (string) $b['nama_kelas']));

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama' => (string) $r['nama_kelas']], $rows);
    }

    // =================================================================
    // Jadwal (kunjungan supervisi)
    // =================================================================

    /**
     * Saringan halaman/API: ta (tahun ajaran), semester, status, guru_id. Kosong = periode sekarang, semua status, semua guru.
     *
     * @return array{ta: string, semester: string, status: string, guru_id: int}
     */
    public static function saringan(array $get): array
    {
        $kini = self::periodeSekarang();

        return [
            'ta'       => self::tahunAjaranSah((string) ($get['ta'] ?? '')) ?? $kini['tahun_ajaran'],
            'semester' => isset(self::SEMESTER[(string) ($get['semester'] ?? '')]) ? (string) $get['semester'] : $kini['semester'],
            'status'   => isset(self::STATUS[(string) ($get['status'] ?? '')]) ? (string) $get['status'] : '',
            'guru_id'  => max(0, (int) ($get['guru_id'] ?? 0)),
        ];
    }

    /**
     * Jadwal satu periode menurut saringan, urut tanggal & jam. Tiap baris + 'instrumen_id' (list<int>) & 'instrumen_nama'
     * (list<string>) & 'ada_nilai' (sudah ada penilaian → tidak bisa dihapus).
     *
     * @param array{ta: string, semester: string, status?: string, guru_id?: int} $s
     *
     * @return list<array<string, mixed>>
     */
    public function daftarJadwal(array $s): array
    {
        $q = $this->jadwal->where('tahun_ajaran', $s['ta'])->where('semester', $s['semester']);
        if (($s['status'] ?? '') !== '') {
            $q->where('status', $s['status']);
        }
        if (($s['guru_id'] ?? 0) > 0) {
            $q->where('guru_id', (int) $s['guru_id']);
        }
        $rows = $q->orderBy('tanggal', 'ASC')->orderBy('jam IS NULL', 'ASC', false)->orderBy('jam', 'ASC')->orderBy('nama_guru', 'ASC')->findAll();

        return $this->lengkapi($rows);
    }

    public function cariJadwal(int $id): ?array
    {
        $row = $id > 0 ? $this->jadwal->find($id) : null;

        return $row === null ? null : $this->lengkapi([$row])[0];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function lengkapi(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $namaIns = array_column($this->instrumen->select('id, nama')->findAll(), 'nama', 'id');
        $adaNilai = [];
        $db = db_connect();
        if ($db->tableExists('supervisi_nilai')) {
            foreach ($db->table('supervisi_nilai')->select('jadwal_id, instrumen_id')->whereIn('jadwal_id', array_column($rows, 'id'))->get()->getResultArray() as $n) {
                $adaNilai[(int) $n['jadwal_id']][] = (int) $n['instrumen_id'];
            }
        }
        foreach ($rows as &$r) {
            $ids                 = self::idDariJson($r['instrumen']);
            $r['instrumen_id']   = $ids;
            $r['instrumen_nama'] = array_values(array_map(static fn (int $i): string => (string) ($namaIns[$i] ?? 'Instrumen #' . $i), $ids));
            $r['dinilai']        = $adaNilai[(int) $r['id']] ?? [];
            $r['ada_nilai']      = isset($adaNilai[(int) $r['id']]);
        }

        return $rows;
    }

    /**
     * Ringkasan periode: jumlah per status + guru yang BELUM punya jadwal (selain batal) di periode itu.
     *
     * @return array{dijadwalkan: int, selesai: int, batal: int, total: int, guru_belum: list<array{id: int, nama: string}>, guru_sudah: list<int>}
     */
    public function ringkasan(string $ta, string $semester): array
    {
        $out = ['dijadwalkan' => 0, 'selesai' => 0, 'batal' => 0, 'total' => 0];
        foreach ($this->jadwal->select('status, COUNT(*) n')->where('tahun_ajaran', $ta)->where('semester', $semester)->groupBy('status')->findAll() as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
            $out['total'] += (int) $r['n'];
        }
        $sudah = $this->idGuruTerjadwal($ta, $semester);
        $out['guru_sudah'] = array_keys($sudah);
        $out['guru_belum'] = array_values(array_map(static fn (array $g): array => ['id' => $g['id'], 'nama' => $g['nama']],
            array_filter($this->pilihanGuru(), static fn (array $g): bool => ! isset($sudah[$g['id']]))));

        return $out;
    }

    /** @return array<int, true> guru yang punya jadwal (tidak batal) pada periode itu. */
    private function idGuruTerjadwal(string $ta, string $semester): array
    {
        $ids = array_column($this->jadwal->select('guru_id')->distinct()->where('tahun_ajaran', $ta)->where('semester', $semester)
            ->where('status !=', 'batal')->where('guru_id IS NOT NULL')->findAll(), 'guru_id');

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Tambah ($id = 0) atau ubah jadwal. Isian: guru_id*, supervisor_id*, tanggal*, jam, mapel_id, kelas_id, instrumen[]* (id),
     * catatan, batal (ubah saja: ya = dibatalkan; tidak = dijadwalkan/selesai seperti semula).
     *
     * @return array{ok: bool, pesan: string, kode?: int, id?: int}
     */
    public function simpanJadwal(array $data, array $pencatat, int $id = 0): array
    {
        $lama = null;
        if ($id > 0 && ($lama = $this->cariJadwal($id)) === null) {
            return self::gagal(404, 'Jadwal supervisi tidak ditemukan.');
        }
        $isian = $this->isianJadwal($data, $lama);
        if (isset($isian['galat'])) {
            return self::gagal($isian['kode'] ?? 422, $isian['galat']);
        }
        $bentrok = $this->jadwal->where('guru_id', $isian['guru_id'])->where('tanggal', $isian['tanggal'])->where('status !=', 'batal')
            ->where('id !=', $id)->first();
        if ($bentrok !== null && $isian['status'] !== 'batal') {
            return self::gagal(409, $isian['nama_guru'] . ' sudah punya jadwal supervisi pada ' . Keterlambatan::hariTanggal($isian['tanggal']) . '. Pilih tanggal lain atau ubah jadwal yang ada.');
        }
        if ($id > 0) {
            $this->jadwal->update($id, $isian);
            $this->audit('update', 'supervisi_jadwal', $id, 'Ubah jadwal supervisi: ' . $isian['nama_guru'] . ', ' . $isian['tanggal'] . ($isian['status'] === 'batal' ? ' (batal)' : ''));

            return ['ok' => true, 'pesan' => 'Jadwal supervisi ' . $isian['nama_guru'] . ' diperbarui.', 'id' => $id];
        }
        $isian += [
            'dicatat_oleh_id' => isset($pencatat['id']) ? (int) $pencatat['id'] : null,
            'dicatat_oleh'    => mb_substr((string) ($pencatat['nama'] ?? ''), 0, 150) ?: null,
        ];
        $this->jadwal->insert($isian);
        $id = (int) $this->jadwal->getInsertID();
        $this->audit('create', 'supervisi_jadwal', $id, 'Jadwal supervisi: ' . $isian['nama_guru'] . ', ' . $isian['tanggal'] . ' oleh ' . $isian['nama_supervisor']);

        return ['ok' => true, 'pesan' => 'Jadwal supervisi ' . $isian['nama_guru'] . ' (' . Keterlambatan::hariTanggal($isian['tanggal']) . ') ditambahkan.', 'id' => $id];
    }

    /**
     * Periksa & susun baris jadwal (tambah/ubah). ['galat' => …, 'kode' => …] bila tidak sah.
     *
     * @return array<string, mixed>
     */
    private function isianJadwal(array $data, ?array $lama): array
    {
        $tanggal = self::tanggalSah((string) ($data['tanggal'] ?? ''));
        if ($tanggal === null) {
            return ['galat' => 'Isi tanggal supervisi dengan benar.'];
        }
        $jam = Keterlambatan::jamSah(isset($data['jam']) ? (string) $data['jam'] : null);
        if ($jam === false) {
            return ['galat' => 'Jam tidak sah. Tulis seperti 08:00.'];
        }
        $guruM  = new GuruModel();
        $guruId = (int) ($data['guru_id'] ?? 0);
        $guru   = $guruId > 0 ? $guruM->select('id, nama, nip')->find($guruId) : null;
        if ($guru === null) {
            return ['galat' => 'Pilih guru yang disupervisi.'];
        }
        if ($lama !== null && $lama['ada_nilai'] && (int) $lama['guru_id'] !== $guruId) {
            return ['galat' => 'Guru tidak bisa diganti karena kunjungan ini sudah ada penilaiannya.', 'kode' => 409];
        }
        $supId = (int) ($data['supervisor_id'] ?? 0);
        $sup   = $supId > 0 ? $guruM->select('id, nama')->find($supId) : null;
        if ($sup === null) {
            return ['galat' => 'Pilih supervisor (penilai).'];
        }
        if ($supId === $guruId) {
            return ['galat' => 'Supervisor tidak boleh guru yang disupervisi sendiri.'];
        }
        $db    = db_connect();
        $mapel = null;
        if ((int) ($data['mapel_id'] ?? 0) > 0) {
            $mapel = $db->table('mata_pelajaran')->select('id, nama_mapel')->where('id', (int) $data['mapel_id'])->where('deleted_at', null)->get()->getRowArray();
            if ($mapel === null) {
                return ['galat' => 'Mata pelajaran tidak ditemukan. Muat ulang halaman.'];
            }
        }
        $kelas = null;
        if ((int) ($data['kelas_id'] ?? 0) > 0) {
            $kelas = $db->table('kelas')->select('id, nama_kelas')->where('id', (int) $data['kelas_id'])->where('deleted_at', null)->get()->getRowArray();
            if ($kelas === null) {
                return ['galat' => 'Kelas tidak ditemukan. Muat ulang halaman.'];
            }
        }
        $ins = $this->instrumenSah($data['instrumen'] ?? [], $lama);
        if (isset($ins['galat'])) {
            return $ins;
        }
        $catatan = IsianBantu::rapikan((string) ($data['catatan'] ?? ''));
        if (mb_strlen($catatan) > self::MAKS_CATATAN) {
            return ['galat' => 'Catatan terlalu panjang (maks ' . self::MAKS_CATATAN . ' huruf).'];
        }
        // Selesai tetap selesai (nilai akhir dipertahankan) KECUALI dibatalkan atau ditambah instrumen yang belum dinilai → kembali
        // "dijadwalkan" dan nilai akhirnya dikosongkan (diisi lagi saat lembar disimpan "selesai").
        $tetapSelesai = ($lama['status'] ?? '') === 'selesai' && array_diff($ins['ids'], $lama['dinilai'] ?? []) === [];
        $status = self::ya($data['batal'] ?? null) ? 'batal' : ($tetapSelesai ? 'selesai' : 'dijadwalkan');
        $nilaiAkhir = $status === 'selesai' ? [] : ['nilai_akhir' => null, 'predikat' => null];

        return self::periode($tanggal) + $nilaiAkhir + [
            'tanggal'            => $tanggal,
            'jam'                => $jam,
            'guru_id'            => (int) $guru['id'],
            'nama_guru'          => mb_substr((string) $guru['nama'], 0, 150),
            'nip_guru'           => trim((string) ($guru['nip'] ?? '')) !== '' ? mb_substr(trim((string) $guru['nip']), 0, 60) : null,
            'mapel_id'           => $mapel !== null ? (int) $mapel['id'] : null,
            'nama_mapel'         => $mapel !== null ? mb_substr((string) $mapel['nama_mapel'], 0, 150) : null,
            'kelas_id'           => $kelas !== null ? (int) $kelas['id'] : null,
            'nama_kelas'         => $kelas !== null ? mb_substr((string) $kelas['nama_kelas'], 0, 50) : null,
            'supervisor_id'      => (int) $sup['id'],
            'nama_supervisor'    => mb_substr((string) $sup['nama'], 0, 150),
            'jabatan_supervisor' => ($this->jabatanStruktural()[(int) $sup['id']]['nama'] ?? null),
            'instrumen'          => json_encode($ins['ids']),
            'status'             => $status,
            'catatan'            => $catatan !== '' ? $catatan : null,
        ];
    }

    /**
     * Daftar instrumen jadwal: minimal satu; instrumen baru harus aktif (yang sudah ada di jadwal lama boleh tetap walau kini
     * nonaktif); instrumen yang sudah dinilai tidak boleh dicabut.
     *
     * @return array{ids?: list<int>, galat?: string, kode?: int}
     */
    private function instrumenSah(mixed $masuk, ?array $lama): array
    {
        $ids = self::idDariJson(is_array($masuk) ? $masuk : (is_string($masuk) && str_starts_with(trim($masuk), '[') ? $masuk : explode(',', (string) $masuk)));
        if ($ids === []) {
            return ['galat' => 'Centang minimal satu instrumen yang dipakai.'];
        }
        $boleh = array_merge(array_map('intval', array_column($this->instrumen->select('id')->where('aktif', 1)->findAll(), 'id')), $lama['instrumen_id'] ?? []);
        foreach ($ids as $i) {
            if (! in_array($i, $boleh, true)) {
                return ['galat' => 'Instrumen yang dipilih tidak aktif atau tidak ada. Muat ulang halaman.'];
            }
        }
        $dicabut = array_diff($lama['dinilai'] ?? [], $ids);
        if ($dicabut !== []) {
            return ['galat' => 'Instrumen yang sudah dinilai tidak bisa dicabut dari jadwal ini.', 'kode' => 409];
        }
        // Urutan mengikuti urutan instrumen (bukan urutan centang).
        $urut = array_map('intval', array_column($this->instrumen->select('id')->whereIn('id', $ids)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->findAll(), 'id'));

        return ['ids' => $urut];
    }

    /** @return array{ok: bool, pesan: string, kode?: int} */
    public function hapusJadwal(int $id): array
    {
        $row = $this->cariJadwal($id);
        if ($row === null) {
            return self::gagal(404, 'Jadwal supervisi tidak ditemukan.');
        }
        if ($row['status'] === 'selesai' || $row['ada_nilai']) {
            return self::gagal(409, 'Jadwal ' . $row['nama_guru'] . ' sudah ada penilaiannya — tidak bisa dihapus. Bila tidak jadi, Ubah → Batalkan.');
        }
        $this->jadwal->delete($id);
        $this->audit('delete', 'supervisi_jadwal', $id, 'Hapus jadwal supervisi: ' . $row['nama_guru'] . ', ' . $row['tanggal']);

        return ['ok' => true, 'pesan' => 'Jadwal supervisi ' . $row['nama_guru'] . ' dihapus.'];
    }

    /**
     * Hari sekolah di antara dua tanggal (hari yang AKTIF di master Hari; master kosong → Senin–Jumat).
     *
     * @return list<string> Y-m-d
     */
    public static function hariKerja(string $mulai, string $selesai): array
    {
        $aktif = [];
        $hm    = new HariModel();
        foreach (range(1, 7) as $n) {
            $h = $hm->byWeekday($n);
            if ($h !== null && (int) ($h['aktif'] ?? 1) === 1) {
                $aktif[$n] = true;
            }
        }
        if ($aktif === []) {
            $aktif = array_fill_keys(range(1, 5), true);
        }
        $out = [];
        for ($t = strtotime($mulai), $akhir = strtotime($selesai); $t <= $akhir; $t = strtotime('+1 day', $t)) {
            if (isset($aktif[(int) date('N', $t)])) {
                $out[] = date('Y-m-d', $t);
            }
        }

        return $out;
    }

    /**
     * Buat banyak jadwal sekaligus: guru_ids[] dibagi rata ke hari sekolah antara mulai–selesai (paling banyak per_hari kunjungan
     * per hari; urut nama guru). Mapel & kelas = penugasan guru dengan JP terbanyak (bisa diubah satu per satu). Guru yang sudah
     * punya jadwal (tidak batal) di semester itu dilewati. Rentang harus dalam satu semester.
     * Isian: guru_ids[], supervisor_id, mulai, selesai, per_hari, jam, instrumen[].
     *
     * @return array{ok: bool, pesan: string, kode?: int, jumlah?: int, ids?: list<int>, dilewati?: list<string>}
     */
    public function buatMassal(array $data, array $pencatat): array
    {
        $mulai   = self::tanggalSah((string) ($data['mulai'] ?? ''));
        $selesai = self::tanggalSah((string) ($data['selesai'] ?? ''));
        if ($mulai === null || $selesai === null || $selesai < $mulai) {
            return self::gagal(422, 'Isi rentang tanggal dengan benar (tanggal selesai tidak boleh sebelum tanggal mulai).');
        }
        if (self::periode($mulai) !== self::periode($selesai)) {
            return self::gagal(422, 'Rentang tanggal harus berada dalam satu semester (Juli–Desember atau Januari–Juni).');
        }
        if ((strtotime($selesai) - strtotime($mulai)) / 86400 > self::MASSAL_MAKS_HARI) {
            return self::gagal(422, 'Rentang tanggal terlalu panjang (maks ' . self::MASSAL_MAKS_HARI . ' hari).');
        }
        $perHari = (int) ($data['per_hari'] ?? self::MASSAL_PER_HARI);
        if ($perHari < 1 || $perHari > self::MASSAL_MAKS_PER_HARI) {
            return self::gagal(422, 'Kunjungan per hari 1–' . self::MASSAL_MAKS_PER_HARI . '.');
        }
        $guruIds = self::idDariJson(is_array($data['guru_ids'] ?? null) ? $data['guru_ids'] : explode(',', (string) ($data['guru_ids'] ?? '')));
        if ($guruIds === []) {
            return self::gagal(422, 'Centang minimal satu guru.');
        }
        $periode = self::periode($mulai);
        $sudah   = $this->idGuruTerjadwal($periode['tahun_ajaran'], $periode['semester']);
        $supId   = (int) ($data['supervisor_id'] ?? 0);
        $nama    = array_column((new GuruModel())->select('id, nama')->whereIn('id', $guruIds)->orderBy('nama', 'ASC')->findAll(), 'nama', 'id');
        $dilewati = []; // nama guru yang sudah punya jadwal semester ini
        $antre    = [];
        $supIkut  = false; // supervisor ikut tercentang → dilewati (tidak menyupervisi diri sendiri)
        foreach ($nama as $gid => $n) {
            if ((int) $gid === $supId) {
                $supIkut = true;
            } elseif (isset($sudah[(int) $gid])) {
                $dilewati[] = (string) $n;
            } else {
                $antre[] = (int) $gid;
            }
        }
        if ($antre === []) {
            return self::gagal(409, 'Semua guru yang dicentang sudah punya jadwal supervisi ' . self::labelPeriode($periode['tahun_ajaran'], $periode['semester'])
                . ($supIkut ? ' (atau guru itu supervisornya sendiri)' : '') . '.');
        }
        $hari = self::hariKerja($mulai, $selesai);
        if ($hari === [] || count($hari) * $perHari < count($antre)) {
            $butuh = (int) ceil(count($antre) / $perHari);

            return self::gagal(422, count($antre) . ' guru × maks ' . $perHari . ' per hari butuh ' . $butuh . ' hari sekolah, sedangkan rentang itu hanya '
                . count($hari) . ' hari sekolah. Perpanjang rentang atau naikkan jumlah per hari.');
        }

        $ids    = [];
        $galat  = [];
        $jumlah = count($antre);
        foreach ($antre as $i => $gid) {
            $saran = $this->saranPengampu($gid)[0] ?? null;
            $r     = $this->simpanJadwal([
                'guru_id'       => $gid,
                'supervisor_id' => $data['supervisor_id'] ?? 0,
                'tanggal'       => $hari[intdiv($i * count($hari), $jumlah)], // dibagi rata ke semua hari dalam rentang
                'jam'           => $data['jam'] ?? '',
                'mapel_id'      => $saran['mapel_id'] ?? 0,
                'kelas_id'      => $saran['kelas_id'] ?? 0,
                'instrumen'     => $data['instrumen'] ?? [],
            ], $pencatat);
            if (! $r['ok']) {
                // Galat isian bersama (supervisor/instrumen) berlaku untuk semua → hentikan di guru pertama.
                if ($ids === [] && in_array($r['kode'] ?? 0, [404, 422], true)) {
                    return $r;
                }
                $galat[] = $nama[$gid] . ': ' . $r['pesan'];

                continue;
            }
            $ids[] = (int) $r['id'];
        }
        $pesan = count($ids) . ' jadwal supervisi dibuat (' . IsianBantu::tanggalIndo($mulai) . ' – ' . IsianBantu::tanggalIndo($selesai) . ').'
            . ($dilewati !== [] ? ' ' . count($dilewati) . ' guru dilewati karena sudah punya jadwal semester ini.' : '')
            . ($supIkut ? ' Supervisornya sendiri tidak dijadwalkan.' : '')
            . ($galat !== [] ? ' Gagal: ' . implode('; ', $galat) : '');

        return ['ok' => $ids !== [], 'pesan' => $pesan, 'jumlah' => count($ids), 'ids' => $ids, 'dilewati' => $dilewati] + ($ids === [] ? ['kode' => 409] : []);
    }

    /** Isian ya/tidak dari formulir/aplikasi: true, 1, "1", "ya", "true", "on". */
    public static function ya(mixed $v): bool
    {
        return $v === true || in_array(mb_strtolower(trim((string) $v)), ['1', 'ya', 'true', 'on'], true);
    }
}
