<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat permohonan PKL: data surat, penomoran, "perlu cetak ulang", dan perakitan berkas Word.
 *
 * Aturan (docs/DESAIN-PKL.md):
 *   - nomor ditetapkan SEKALI saat surat pertama diterbitkan; cetak ulang memakai nomor yang sama;
 *   - urutan per TAHUN (tahun tanggal surat); lantai "nomor berikutnya" di Pengaturan;
 *   - penomoran dikunci lewat baris pkl_pengaturan (FOR UPDATE) + UNIQUE(tahun, urut) di database,
 *     jadi dua staf yang menerbitkan bersamaan tak pernah mendapat nomor kembar;
 *   - sidik data (perusahaan, tanggal, siswa) disimpan tiap cetak → bila berubah = "perlu cetak ulang".
 */
final class PklSurat
{
    public const BERKAS_TEMPLATE = 'template_surat.docx';

    /** Template bawaan = surat resmi sekolah (relatif APPPATH). Dibuat dari contoh surat sekolah; lihat docs/DESAIN-PKL.md. */
    public const TEMPLATE_BAWAAN = 'Libraries/Surat/surat_pkl_binus.docx';

    /** Id relasi gambar tanda tangan Waka Hubin di dalam berkas Word. */
    private const REL_TTD = 'rIdTtdHubin';

    /** Penanda skalar yang bisa dipakai di template Word (${nama}). */
    public const SKALAR = [
        'nomor', 'tanggal', 'kota_surat', 'penerima', 'perusahaan', 'perusahaan_alamat', 'perusahaan_kota', 'alamat_lengkap',
        'perusahaan_telepon', 'kontak_nama', 'kontak_jabatan', 'mulai', 'selesai', 'lama', 'tahun_ajaran', 'jumlah_siswa',
        'siswa_daftar', 'sekolah', 'sekolah_alamat', 'sekolah_telepon', 'sekolah_email', 'waka_nama', 'waka_nip', 'waka_jabatan',
        // Surat resmi sekolah:
        'tanggal_surat', 'kepsek_nama', 'kontak_sekolah_nama', 'kontak_sekolah_hp',
        'hubin_nama', 'hubin_jabatan', 'hubin_jabatan_1', 'hubin_jabatan_2', 'ttd_hubin',
        'acc_footer', 'acc_oleh', 'acc_peran', 'acc_waktu', 'acc_kode',
    ];

    /** Penanda per baris tabel siswa. */
    public const BARIS = ['no', 'siswa_nama', 'siswa_nis', 'siswa_nisn', 'siswa_kelas', 'siswa_jurusan', 'siswa_hp'];

    /** Lebih dari ini → dipecah jadi ZIP beberapa berkas Word. */
    private const MAKS_SATU_BERKAS = 60;
    private const ISI_PER_BERKAS   = 50;

    private BaseConnection $db;
    private PklPengajuanModel $model;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db    = $db ?? db_connect();
        $this->model = new PklPengajuanModel();
    }

    public static function dirBerkas(): string
    {
        $dir = WRITEPATH . 'pkl/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Path template Word unggahan sekolah bila ada & berkasnya masih ada, selain itu null (= surat bawaan). */
    public static function pathTemplate(array $pengaturan): ?string
    {
        $nama = (string) ($pengaturan['template_surat'] ?? '');
        $path = self::dirBerkas() . $nama;

        return ($nama !== '' && is_file($path)) ? $path : null;
    }

    /** Template yang dipakai: unggahan sekolah, kalau tak ada → template bawaan (surat resmi sekolah), selain itu null. */
    public static function pathAktif(array $pengaturan): ?string
    {
        $unggah = self::pathTemplate($pengaturan);
        if ($unggah !== null) {
            return $unggah;
        }
        $bawaan = APPPATH . self::TEMPLATE_BAWAAN;

        return is_file($bawaan) ? $bawaan : null;
    }

    /**
     * Gambar tanda tangan Waka Hubin yang diunggah, atau null: ['path','ext','cx','cy'] — ukuran EMU,
     * dimuat dalam kotak 4,2 × 1,8 cm dengan perbandingan asli (ruang tanda tangan di template = 4 baris ≈ 1,95 cm).
     *
     * @return array{path: string, ext: string, cx: int, cy: int}|null
     */
    public static function infoTtd(array $pengaturan): ?array
    {
        $nama = (string) ($pengaturan['ttd_hubin'] ?? '');
        $path = self::dirBerkas() . $nama;
        if ($nama === '' || ! is_file($path)) {
            return null;
        }
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        $skala = min(1512000 / $info[0], 648000 / $info[1]);

        return [
            'path' => $path,
            'ext'  => $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg',
            'cx'   => max(1, (int) round($info[0] * $skala)),
            'cy'   => max(1, (int) round($info[1] * $skala)),
        ];
    }

    /** Batas ukuran gambar tanda tangan Waka Hubin (byte). */
    public const MAKS_TTD = 1048576;

    /**
     * Simpan gambar tanda tangan Waka Hubin (PNG/JPG, ≤ 1 MB, 100–4000 px). Jenis ditentukan dari ISI berkas,
     * bukan nama/ekstensi kiriman. Dipakai web & API.
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function simpanTtd(string $pathSementara, int $ukuran): array
    {
        if ($ukuran > self::MAKS_TTD) {
            return ['ok' => false, 'pesan' => 'Gambar terlalu besar (maksimal 1 MB). Kecilkan ukurannya dulu.'];
        }
        $info = @getimagesize($pathSementara);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return ['ok' => false, 'pesan' => 'Berkas bukan gambar PNG/JPG yang sah.'];
        }
        if ($info[0] < 100 || $info[1] < 40 || $info[0] > 4000 || $info[1] > 4000) {
            return ['ok' => false, 'pesan' => 'Ukuran gambar harus antara 100 dan 4000 piksel (lebar & tinggi).'];
        }

        $dir  = self::dirBerkas();
        $nama = 'ttd_hubin.' . ($info[2] === IMAGETYPE_PNG ? 'png' : 'jpg');
        foreach (['ttd_hubin.png', 'ttd_hubin.jpg'] as $lama) {
            if (is_file($dir . $lama)) {
                @unlink($dir . $lama);
            }
        }
        if (! @copy($pathSementara, $dir . $nama)) {
            return ['ok' => false, 'pesan' => 'Gambar gagal disimpan di server (folder writable/pkl tidak bisa ditulis).'];
        }
        (new PklPengaturanModel())->update(1, ['ttd_hubin' => $nama]);

        return ['ok' => true, 'pesan' => 'Tanda tangan disimpan. Surat yang di-ACC akun Waka Hubin akan memakainya.'];
    }

    public static function hapusTtd(): void
    {
        $p = (new PklPengaturanModel())->ambil();
        foreach (['ttd_hubin.png', 'ttd_hubin.jpg', (string) ($p['ttd_hubin'] ?? '')] as $nama) {
            if ($nama !== '' && basename($nama) === $nama && is_file(self::dirBerkas() . $nama)) {
                @unlink(self::dirBerkas() . $nama);
            }
        }
        (new PklPengaturanModel())->update(1, ['ttd_hubin' => null]);
    }

    /**
     * Pilih ajuan untuk unduhan surat massal. $mode: terpilih ($ids) | belum (belum dicetak / perlu cetak ulang) | semua.
     * Urutan hasil = urutan persetujuan (yang lebih dulu disetujui mendapat nomor lebih kecil).
     *
     * @param list<int> $ids
     *
     * @return array{ok: bool, ids: list<int>, pesan: string, kosong?: bool}
     */
    public function pilihUntukUnduh(string $mode, array $ids, int $maks = 300): array
    {
        $db = db_connect();
        if ($mode === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if ($ids === []) {
                return ['ok' => false, 'ids' => [], 'pesan' => 'Pilih dulu ajuan yang suratnya mau diunduh.'];
            }
        } else {
            $semua = array_map('intval', array_column($db->table('pkl_pengajuan')->select('id')->where('status', 'disetujui')->get()->getResultArray(), 'id'));
            if ($mode === 'semua') {
                $ids = $semua;
                if ($ids === []) {
                    return ['ok' => false, 'ids' => [], 'pesan' => 'Belum ada ajuan yang disetujui.'];
                }
            } else {
                $status = $this->statusBanyak($semua);
                $ids    = array_values(array_filter($semua, static fn (int $i) => ! isset($status[$i]) || $status[$i]['perlu_ulang']));
                if ($ids === []) {
                    return ['ok' => true, 'ids' => [], 'kosong' => true, 'pesan' => 'Semua surat sudah dicetak dan datanya tidak berubah. Tidak ada yang perlu diunduh. Mau mencetak ulang semuanya? Pakai "Unduh SEMUA surat".'];
                }
            }
        }
        if (count($ids) > $maks) {
            return ['ok' => false, 'ids' => [], 'pesan' => 'Terlalu banyak sekaligus (maksimal ' . $maks . ' surat). Pilih sebagian.'];
        }

        $urut = array_map('intval', array_column(
            $db->table('pkl_pengajuan')->select('id')->whereIn('id', $ids)->where('status', 'disetujui')
                ->orderBy('diputuskan_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(),
            'id'
        ));

        return ['ok' => $urut !== [], 'ids' => $urut, 'pesan' => $urut === [] ? 'Tak ada ajuan berstatus Disetujui di pilihan itu.' : ''];
    }

    // =================================================================
    // Data & sidik
    // =================================================================

    /** @return array{ajuan: array<string,mixed>, anggota: list<array<string,mixed>>}|null */
    public function muat(int $id): ?array
    {
        $ajuan = $this->model->detail($id);

        return $ajuan === null ? null : ['ajuan' => $ajuan, 'anggota' => $this->model->anggotaDetail($id)];
    }

    /**
     * Sidik data yang tampil di surat — berubah bila perusahaan/siswa (termasuk HP), penanda tangan
     * (Waka Hubin, Kepala Sekolah, kontak NB, gambar tanda tangan), atau catatan ACC berubah.
     * $p = pengaturan PKL (dibaca bila kosong).
     */
    public static function sidik(array $ajuan, array $anggota, ?array $p = null): string
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $ttd = self::infoTtd($p);
        $dasar = [
            (string) ($p['waka_hubin_nama'] ?? ''), (string) ($p['waka_hubin_nip'] ?? ''), (string) ($p['waka_hubin_jabatan'] ?? ''),
            (string) ($p['kepsek_nama'] ?? ''), (string) ($p['kontak_surat_nama'] ?? ''), (string) ($p['kontak_surat_hp'] ?? ''),
            $ttd !== null ? basename($ttd['path']) . '@' . (int) @filemtime($ttd['path']) : '-',
            (string) ($ajuan['acc_at'] ?? ''), (string) ($ajuan['acc_peran'] ?? ''), (string) ($ajuan['acc_nama'] ?? ''),
            (string) $ajuan['perusahaan_nama'], (string) ($ajuan['perusahaan_alamat'] ?? ''), (string) ($ajuan['perusahaan_kota'] ?? ''),
            (string) ($ajuan['perusahaan_telepon'] ?? ''), (string) ($ajuan['kontak_nama'] ?? ''), (string) ($ajuan['kontak_jabatan'] ?? ''),
        ];
        $siswa = array_map(static fn (array $a) => [
            (int) $a['siswa_id'], (string) $a['nama'], (string) ($a['nisn'] ?? ''), (string) ($a['nama_kelas'] ?? ''), (string) ($a['jurusan_nama'] ?? ''),
            trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? ''),
        ], $anggota);
        usort($siswa, static fn ($x, $y) => $x[0] <=> $y[0]);

        return sha1(json_encode([$dasar, $siswa], JSON_UNESCAPED_UNICODE));
    }
    public function surat(int $id): ?array
    {
        return $this->db->table('pkl_surat')->where('pengajuan_id', $id)->get()->getRowArray();
    }

    /**
     * Status surat banyak ajuan sekaligus (untuk daftar): id => ['nomor', 'perlu_ulang'].
     *
     * @param list<int> $ids
     *
     * @return array<int, array{nomor: string, perlu_ulang: bool}>
     */
    public function statusBanyak(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $surat = [];
        foreach ($this->db->table('pkl_surat')->whereIn('pengajuan_id', $ids)->get()->getResultArray() as $r) {
            $surat[(int) $r['pengajuan_id']] = $r;
        }
        if ($surat === []) {
            return [];
        }

        $out     = [];
        $p       = (new PklPengaturanModel())->ambil();
        $ajuan   = [];
        foreach ($this->db->table('pkl_pengajuan')->whereIn('id', array_keys($surat))->get()->getResultArray() as $r) {
            $ajuan[(int) $r['id']] = $r;
        }
        $anggota = [];
        foreach ($this->db->table('pkl_anggota a')
            ->select('a.pengajuan_id, a.siswa_id, a.hp, s.no_hp AS hp_master, s.nama, s.nisn, k.nama_kelas, j.nama AS jurusan_nama')
            ->join('siswa s', 's.id = a.siswa_id')->join('kelas k', 'k.id = a.kelas_id', 'left')->join('jurusan j', 'j.id = k.jurusan_id', 'left')
            ->whereIn('a.pengajuan_id', array_keys($surat))->get()->getResultArray() as $r) {
            $anggota[(int) $r['pengajuan_id']][] = $r;
        }
        foreach ($surat as $id => $s) {
            $out[$id] = [
                'nomor'       => (string) $s['nomor'],
                'perlu_ulang' => isset($ajuan[$id]) && self::sidik($ajuan[$id], $anggota[$id] ?? [], $p) !== (string) $s['sidik'],
            ];
        }

        return $out;
    }

    // =================================================================
    // Penomoran
    // =================================================================

    /**
     * Pastikan ajuan punya surat bernomor; bila belum, tetapkan nomor berikutnya.
     *
     * @return array{ok: bool, kode?: string, surat?: array<string,mixed>, baru?: bool}
     */
    public function terbitkan(int $id, string $tanggal, array $konteks): array
    {
        $tgl = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal || (int) $tgl->format('Y') < 2000) {
            return ['ok' => false, 'kode' => 'tanggal'];
        }

        $this->db->transBegin();
        try {
            // Mutex penomoran: semua penerbit antre di baris pengaturan.
            $this->db->query('SELECT id FROM pkl_pengaturan WHERE id = 1 FOR UPDATE');

            $ada = $this->surat($id);
            if ($ada !== null) {
                $this->db->transCommit();

                return ['ok' => true, 'surat' => $ada, 'baru' => false];
            }

            $muat = $this->muat($id);
            if ($muat === null || $muat['ajuan']['status'] !== 'disetujui') {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'status'];
            }

            $p      = (new PklPengaturanModel())->ambil();
            $tahun  = (int) $tgl->format('Y');
            $maks   = (int) ($this->db->query('SELECT COALESCE(MAX(urut), 0) m FROM pkl_surat WHERE tahun = ?', [$tahun])->getRowArray()['m'] ?? 0);
            $lantai = ((int) ($p['nomor_awal_tahun'] ?? 0) === $tahun) ? max(1, (int) $p['nomor_awal']) : 1;
            $urut   = max($maks + 1, $lantai);
            $pola   = (string) ($p['format_nomor'] ?? '') !== '' ? (string) $p['format_nomor'] : PklNomorSurat::BAWAAN;
            $now    = date('Y-m-d H:i:s');

            $baris = [
                'pengajuan_id' => $id, 'tahun' => $tahun, 'urut' => $urut, 'nomor' => PklNomorSurat::format($pola, $urut, $tgl),
                'tanggal_surat' => $tanggal, 'cetak_ke' => 0, 'sidik' => self::sidik($muat['ajuan'], $muat['anggota'], $p),
                'dibuat_oleh' => $konteks['admin_id'] ?? null, 'created_at' => $now, 'updated_at' => $now,
            ];
            if (! $this->db->table('pkl_surat')->insert($baris)) {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'galat'];
            }
            (new PklAjuan($this->db))->catat($id, 'surat', $konteks, 'Surat diterbitkan, nomor ' . $baris['nomor']);
            $this->db->transCommit();

            return ['ok' => true, 'surat' => $baris + ['id' => (int) $this->db->insertID()], 'baru' => true];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[PKL] terbitkan surat gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'galat'];
        }
    }

    // =================================================================
    // Berkas
    // =================================================================

    /**
     * Penanda surat untuk satu ajuan.
     *
     * $ekstra: ['oleh' => 'Nama pencetak', 'peran' => 'operator', 'ttd' => infoTtd(), 'sekarang' => 'Y-m-d H:i:s'].
     *
     * @return array{v: array<string,string>, siswa: list<array<string,string>>, meta: array<string,mixed>}
     */
    public function isi(array $ajuan, array $anggota, array $surat, array $setting, array $p, array $ekstra = []): array
    {
        $hari = ($ajuan['tanggal_mulai'] && $ajuan['tanggal_selesai']) ? IsianBantu::hariInklusif($ajuan['tanggal_mulai'], $ajuan['tanggal_selesai']) : 0;
        $bln  = $hari > 0 ? max(1, (int) round($hari / 30)) : 0;

        $siswa  = [];
        $daftar = [];
        foreach (array_values($anggota) as $i => $a) {
            $hp = trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? '');
            $siswa[] = [
                'no' => (string) ($i + 1), 'nama' => (string) $a['nama'], 'nis' => (string) ($a['nis'] ?? ''), 'nisn' => ((string) ($a['nisn'] ?? '')) !== '' ? (string) $a['nisn'] : '-',
                'kelas' => (string) ($a['nama_kelas'] ?? ''), 'jurusan' => ((string) ($a['jurusan_nama'] ?? '')) !== '' ? (string) $a['jurusan_nama'] : '-',
                'hp' => $hp !== '' ? $hp : '-',
            ];
            $daftar[] = ($i + 1) . '. ' . $a['nama'] . ' (' . ($a['nama_kelas'] ?? '-') . ')';
        }

        $kontak  = trim((string) ($ajuan['kontak_nama'] ?? ''));
        $jabatan = trim((string) ($ajuan['kontak_jabatan'] ?? ''));
        $alamat  = trim((string) ($ajuan['perusahaan_alamat'] ?? ''));
        $kota    = trim((string) ($ajuan['perusahaan_kota'] ?? ''));

        $wakaNama    = trim((string) ($p['waka_hubin_nama'] ?? ''));
        $wakaJabatan = trim((string) ($p['waka_hubin_jabatan'] ?? '')) !== '' ? trim((string) $p['waka_hubin_jabatan']) : 'Wakil Kepala Sekolah Bidang Hubungan Industri';
        [$jab1, $jab2] = self::bagiDuaBaris($wakaJabatan, 30);

        // Gambar tanda tangan Waka Hubin HANYA bila yang meng-ACC memang akun Waka Hubin. ACC oleh Admin
        // (cadangan) / data impor → ruang dibiarkan kosong; kaki surat menjelaskan siapa yang menyetujui.
        $ttd     = $ekstra['ttd'] ?? null;
        $ttdRaw  = ($ttd !== null && ($ajuan['acc_peran'] ?? '') === 'hubin')
            ? PklDocx::gambarTtd(self::REL_TTD, (int) $ttd['cx'], (int) $ttd['cy'])
            : '';
        $accWaktu = ! empty($ajuan['acc_at']) ? self::waktuIndo((string) $ajuan['acc_at']) : '';

        return [
            'v' => [
                'nomor' => (string) $surat['nomor'], 'tanggal' => IsianBantu::tanggalIndo($surat['tanggal_surat']),
                'tanggal_surat' => self::tanggalSurat((string) $surat['tanggal_surat']),
                'kota_surat' => (string) ($setting['city'] ?? ''),
                'penerima' => $kontak !== '' ? $kontak . ($jabatan !== '' ? ' (' . $jabatan . ')' : '') : 'Pimpinan',
                'perusahaan' => (string) $ajuan['perusahaan_nama'], 'perusahaan_alamat' => $alamat, 'perusahaan_kota' => $kota,
                'alamat_lengkap' => trim($alamat . ($kota !== '' && ! str_contains(mb_strtolower($alamat), mb_strtolower($kota)) ? ', ' . $kota : '')),
                'perusahaan_telepon' => (string) ($ajuan['perusahaan_telepon'] ?? ''), 'kontak_nama' => $kontak, 'kontak_jabatan' => $jabatan,
                'mulai' => IsianBantu::tanggalIndo($ajuan['tanggal_mulai']), 'selesai' => IsianBantu::tanggalIndo($ajuan['tanggal_selesai']),
                'lama' => $hari > 0 ? $hari . ' hari' . ($bln >= 1 ? ' atau ± ' . $bln . ' bulan' : '') : '',
                'tahun_ajaran' => (string) ($setting['academic_year'] ?? ''), 'jumlah_siswa' => (string) count($siswa), 'siswa_daftar' => implode("\n", $daftar),
                'sekolah' => (string) ($setting['school_name'] ?? ''), 'sekolah_alamat' => (string) ($setting['address'] ?? ''),
                'sekolah_telepon' => (string) ($setting['phone'] ?? ''), 'sekolah_email' => (string) ($setting['email'] ?? ''),
                'waka_nama' => $wakaNama, 'waka_nip' => (string) ($p['waka_hubin_nip'] ?? ''), 'waka_jabatan' => $wakaJabatan,
                'kepsek_nama' => (string) ($p['kepsek_nama'] ?? ''),
                'kontak_sekolah_nama' => (string) ($p['kontak_surat_nama'] ?? ''), 'kontak_sekolah_hp' => (string) ($p['kontak_surat_hp'] ?? ''),
                'hubin_nama' => $wakaNama !== '' ? $wakaNama : '........................................',
                'hubin_jabatan' => $wakaJabatan, 'hubin_jabatan_1' => $jab1, 'hubin_jabatan_2' => $jab2,
                'ttd_hubin' => $ttdRaw,
                'acc_footer' => self::catatanAcc($ajuan, $p, $ekstra),
                'acc_oleh' => (string) ($ajuan['acc_nama'] ?? ''), 'acc_peran' => (string) ($ajuan['acc_peran'] ?? ''),
                'acc_waktu' => $accWaktu, 'acc_kode' => (string) ($ajuan['acc_kode'] ?? ''),
            ],
            'siswa' => $siswa,
            'meta'  => [
                'urut'         => (int) ($surat['urut'] ?? 0),
                'nama_pengaju' => (string) ($anggota[0]['nama'] ?? ''),
                'kelas'        => (string) ($anggota[0]['nama_kelas'] ?? ''),
                'perusahaan'   => (string) $ajuan['perusahaan_nama'],
                'thn'          => (int) ($surat['tahun'] ?? date('Y')),
                'jumlah'       => count($siswa),
            ],
        ];
    }

    /** "2026-09-03" → "03 September 2026" (hari 2 angka, seperti surat sekolah). */
    public static function tanggalSurat(string $ymd): string
    {
        $t = strtotime($ymd);

        return $t === false ? '' : date('d', $t) . ' ' . IsianBantu::BULAN[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
    }

    /** "2026-10-07 09:41:00" → "Rabu, 07 Oktober 2026 pukul 09.41 WIB". */
    public static function waktuIndo(string $dt): string
    {
        $t = strtotime($dt);
        if ($t === false) {
            return '';
        }
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) date('w', $t)];

        return $hari . ', ' . date('d', $t) . ' ' . IsianBantu::BULAN[(int) date('n', $t) - 1] . ' ' . date('Y', $t) . ' pukul ' . date('H.i', $t) . ' WIB';
    }

    /** Bagi jabatan panjang jadi dua baris (kolom kiri tanda tangan sempit). @return array{0: string, 1: string} */
    public static function bagiDuaBaris(string $teks, int $maks): array
    {
        $teks = trim((string) preg_replace('/\s+/u', ' ', $teks));
        if (mb_strlen($teks) <= $maks) {
            return [$teks, ''];
        }
        $kata = explode(' ', $teks);
        $a    = '';
        foreach ($kata as $i => $k) {
            $coba = $a === '' ? $k : $a . ' ' . $k;
            if (mb_strlen($coba) > $maks && $a !== '') {
                return [$a, implode(' ', array_slice($kata, $i))];
            }
            $a = $coba;
        }

        return [$a, ''];
    }

    /**
     * Catatan ACC di kaki surat: siapa yang menyetujui, kapan, kode verifikasi, dan siapa yang mencetak.
     * Sengaja jujur: ACC oleh Admin ditulis "mewakili Waka Hubin", data impor ditulis bukan persetujuan sistem.
     */
    public static function catatanAcc(array $ajuan, array $p, array $ekstra = []): string
    {
        $nama  = trim((string) ($ajuan['acc_nama'] ?? ''));
        $peran = (string) ($ajuan['acc_peran'] ?? '');
        $waktu = ! empty($ajuan['acc_at']) ? self::waktuIndo((string) $ajuan['acc_at']) : '';
        $kode  = (string) ($ajuan['acc_kode'] ?? '');
        $jabatanHubin = trim((string) ($p['waka_hubin_jabatan'] ?? '')) !== '' ? trim((string) $p['waka_hubin_jabatan']) : 'Wakil Kepala Sekolah Bidang Hubungan Industri';

        if ($peran === 'impor') {
            $baris1 = 'Data PKL ini adalah riwayat yang diimpor ke Sistem Informasi Akademik Sekolah (BINUS) oleh ' . ($nama !== '' ? $nama : 'staf') . ($waktu !== '' ? ' pada ' . $waktu : '') . ' — bukan persetujuan elektronik Waka Hubin.';
            $baris2 = $kode !== '' ? 'Kode verifikasi: ' . $kode : '';
        } else {
            $siapa = match ($peran) {
                'hubin'    => $nama . ' (' . $jabatanHubin . ')',
                'admin'    => $nama . ' (Admin Sistem, mewakili Waka Hubin karena Waka Hubin berhalangan)',
                'operator' => $nama . ' (Operator Sekolah — tanpa wewenang ACC; mohon dikonfirmasi Waka Hubin)',
                default    => $nama !== '' ? $nama : 'tidak tercatat',
            };
            // Tiap keterangan satu baris pendek (tidak terpotong di tengah kode verifikasi).
            $baris1 = 'Surat ini disetujui secara elektronik melalui Sistem Informasi Akademik Sekolah (BINUS).';
            $baris2 = 'Disetujui oleh: ' . $siapa;
            $rinci  = array_filter([
                $waktu !== '' ? 'Waktu persetujuan: ' . $waktu : '',
                $kode !== '' ? 'Kode verifikasi: ' . $kode : '',
            ], static fn (string $b) => $b !== '');
            if ($rinci !== []) {
                $baris2 .= "\n" . implode(' · ', $rinci);
            }
        }

        $baris3 = '';
        if (! empty($ekstra['oleh'])) {
            $baris3 = 'Dicetak oleh ' . $ekstra['oleh'] . (! empty($ekstra['peran']) ? ' (' . HakAkses::label((string) $ekstra['peran']) . ')' : '')
                . ' pada ' . self::waktuIndo((string) ($ekstra['sekarang'] ?? date('Y-m-d H:i:s'))) . '.';
        }

        return implode("\n", array_filter([$baris1, $baris2, $baris3], static fn (string $b) => $b !== ''));
    }
    private function logo(array $setting): ?array
    {
        return PklDocx::infoLogo(! empty($setting['logo']) ? FCPATH . 'uploads/' . $setting['logo'] : null);
    }

    /**
     * Terbitkan (bila belum) dan rakit surat untuk banyak ajuan. Urutan $ids = urutan nomor
     * bagi yang BARU diberi nomor. Kembalian: berkas siap unduh, atau galat.
     *
     * @param list<int> $ids
     *
     * @return array{ok: bool, pesan?: string, biner?: string, nama?: string, jumlah?: int, dilewati?: int}
     */
    public function bangun(array $ids, array $konteks, ?string $tanggal = null): array
    {
        $setting = (new SettingModel())->get();
        $p       = (new PklPengaturanModel())->ambil();
        $logo    = $this->logo($setting);
        $path    = self::pathAktif($p);
        $ttd     = self::infoTtd($p);
        $ekstra  = ['oleh' => (string) ($konteks['oleh'] ?? ''), 'peran' => (string) ($konteks['peran'] ?? ''), 'ttd' => $ttd, 'sekarang' => date('Y-m-d H:i:s')];

        $daftar = [];
        $sidik  = [];
        $nomor  = [];
        $lewat  = 0;
        foreach ($ids as $id) {
            $t = $this->terbitkan((int) $id, $tanggal ?? date('Y-m-d'), $konteks);
            $m = $t['ok'] ? $this->muat((int) $id) : null;
            if (! $t['ok'] || $m === null) {
                $lewat++;
                continue;
            }
            $daftar[]      = $this->isi($m['ajuan'], $m['anggota'], $t['surat'], $setting, $p, $ekstra);
            $sidik[$id]    = self::sidik($m['ajuan'], $m['anggota'], $p);
            $nomor[$id]    = (string) $t['surat']['nomor'];
        }
        if ($daftar === []) {
            return ['ok' => false, 'pesan' => 'Tidak ada ajuan yang bisa dibuatkan surat (hanya ajuan berstatus Disetujui).'];
        }

        // Gambar tanda tangan Waka Hubin ikut dibawa bila ada surat yang memakainya.
        $media = [];
        if ($ttd !== null && $path !== null) {
            foreach ($daftar as $d) {
                if (str_contains((string) ($d['v']['ttd_hubin'] ?? ''), self::REL_TTD)) {
                    $media[] = ['id' => self::REL_TTD, 'ext' => $ttd['ext'], 'biner' => (string) file_get_contents($ttd['path'])];
                    break;
                }
            }
        }

        $rakit = static fn (array $bagian): string => $path !== null
            ? PklDocx::dariTemplate($path, $bagian, $media)
            : PklDocx::dokumen(array_map(static fn (array $d) => PklDocx::suratBawaan($d['v'], $d['siswa'], $logo), $bagian), $logo);

        try {
            if (count($daftar) <= self::MAKS_SATU_BERKAS) {
                $biner = $rakit($daftar);
                $nama  = count($daftar) === 1
                    ? PklNamaBerkas::format((string) ($p['format_nama_berkas'] ?? ''), $daftar[0]['meta']) . '.docx'
                    : 'Surat PKL (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.docx';
            } else {
                $biner = $this->zipkan(array_map($rakit, array_chunk($daftar, self::ISI_PER_BERKAS)));
                $nama  = 'Surat PKL (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.zip';
            }
        } catch (\Throwable $e) {
            log_message('error', '[PKL] rakit surat gagal: ' . $e->getMessage());

            return ['ok' => false, 'pesan' => 'Surat gagal dibuat: ' . $e->getMessage() . (self::pathTemplate($p) !== null ? ' Periksa template Word di Pengaturan PKL (atau hapus template untuk memakai surat bawaan sekolah).' : '')];
        }

        $now = date('Y-m-d H:i:s');
        foreach ($sidik as $id => $s) {
            $this->db->query('UPDATE pkl_surat SET sidik = ?, cetak_ke = cetak_ke + 1, terakhir_cetak_at = ?, updated_at = ? WHERE pengajuan_id = ?', [$s, $now, $now, (int) $id]);
            (new PklAjuan($this->db))->catat((int) $id, 'cetak', $konteks, 'Surat ' . $nomor[$id] . ' diunduh');
        }

        return ['ok' => true, 'biner' => $biner, 'nama' => $nama, 'jumlah' => count($daftar), 'dilewati' => $lewat];
    }
    /**
     * Template untuk diedit/diunggah ulang: berkas surat bawaan sekolah lengkap dengan penanda ${...}.
     * Bila berkas bawaan hilang, dibuatkan dari surat sederhana.
     */
    public function contohTemplate(): string
    {
        $bawaan = APPPATH . self::TEMPLATE_BAWAAN;
        if (is_file($bawaan)) {
            return (string) file_get_contents($bawaan);
        }

        $setting = (new SettingModel())->get();
        $v       = [];
        foreach (self::SKALAR as $k) {
            $v[$k] = '${' . $k . '}';
        }
        $baris = ['no' => '${no}', 'nama' => '${siswa_nama}', 'nis' => '${siswa_nis}', 'nisn' => '${siswa_nisn}', 'kelas' => '${siswa_kelas}', 'jurusan' => '${siswa_jurusan}', 'hp' => '${siswa_hp}'];
        $logo  = $this->logo($setting);

        return PklDocx::dokumen([PklDocx::suratBawaan($v, [$baris], $logo)], $logo);
    }
    /** Nama berkas aman untuk unduhan. */
    public static function amanNama(string $s): string
    {
        $s = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $s) ?? '');

        return mb_substr($s !== '' ? $s : 'surat', 0, 80);
    }

    /** @param list<string> $berkas isi docx */
    private function zipkan(array $berkas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pklz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($berkas as $i => $isi) {
            $zip->addFromString('Surat PKL bagian ' . ($i + 1) . '.docx', $isi);
        }
        $zip->close();
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }
}
