<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Impor riwayat PKL lama dari Excel (data yang sudah ada sebelum sistem ini dipakai), supaya siswa
 * yang sudah PKL tidak bisa mengajukan lagi dan laporan "sudah/belum PKL" lengkap.
 *
 * Satu baris = satu siswa. Baris dengan perusahaan + tanggal mulai + tanggal selesai yang sama
 * (tanggal boleh kosong) digabung menjadi SATU ajuan (siswa pertama = pengaju). Hasilnya ajuan
 * berstatus Disetujui bersumber "impor" — BUKAN persetujuan Waka Hubin lewat sistem, dan ditandai
 * begitu di catatan ACC. Dua langkah: baca() → pratinjau (tak ada yang disimpan) → simpan().
 *
 * Dua bentuk berkas dikenali otomatis:
 * 1. Format contoh (judul di baris pertama, urutan bebas): NIS, Nama, Kelas, Perusahaan, Alamat, Kota,
 *    Telepon, Kontak, Jabatan, Mulai, Selesai (dua terakhir opsional). Siswa dicocokkan lewat NIS; bila
 *    NIS kosong, lewat Nama + Kelas.
 * 2. Format sekolah "DATA SISWA PRAKERIN" (sama dengan unduhan PklPrakerin): satu lembar per kelas
 *    (nama lembar = nama kelas), judul di atas, kolom NO, NAMA, Mitra DU/DI, Mulai, Selesai, Lamanya PKL.
 *    Siswa dicocokkan lewat nama di kelas lembarnya; nama yang hanya mirip (beda ejaan sedikit) jadi
 *    "saran" yang harus dicentang dulu sebelum disimpan. Baris tanpa Mitra (atau Mitra "PKL"/"-") dilewati,
 *    siswa yang sudah punya ajuan aktif tidak diubah. Satu ajuan = satu kelas + mitra + tanggal yang sama,
 *    dipecah per maksimal siswa per ajuan (Pengaturan PKL).
 */
final class PklImpor
{
    public const MAKS_BARIS = 2000;

    /** kunci internal => nama judul yang dikenali (sudah dinormalkan: huruf kecil, tanpa tanda baca) */
    private const KOLOM = [
        'nis'        => ['nis'],
        'nama'       => ['nama', 'namasiswa'],
        'kelas'      => ['kelas'],
        'perusahaan' => ['perusahaan', 'namaperusahaan', 'tempatpkl', 'dudi'],
        'alamat'     => ['alamat', 'alamatperusahaan'],
        'kota'       => ['kota', 'kotakabupaten', 'kabupaten'],
        'telepon'    => ['telepon', 'telp', 'notelp', 'nohp'],
        'kontak'     => ['kontak', 'pimpinan', 'namapimpinan', 'pembimbing'],
        'jabatan'    => ['jabatan'],
        'mulai'      => ['mulai', 'tanggalmulai', 'tglmulai'],
        'selesai'    => ['selesai', 'tanggalselesai', 'tglselesai'],
    ];

    /** Judul kolom format sekolah "DATA SISWA PRAKERIN" (judul Mulai/Selesai biasanya di baris bawah judul utama). */
    private const KOLOM_SEKOLAH = [
        'nama'    => ['nama', 'namasiswa', 'namalengkap', 'namapesertadidik'],
        'mitra'   => ['mitradidu', 'mitradudi', 'mitra', 'mitrapkl', 'mitraprakerin', 'mitraindustri', 'tempatprakerin', 'tempatpkl', 'perusahaan', 'namaperusahaan', 'dudi'],
        'mulai'   => ['mulai', 'tanggalmulai', 'tglmulai'],
        'selesai' => ['selesai', 'tanggalselesai', 'tglselesai'],
        'lama'    => ['lamanyapkl', 'lamapkl', 'lamanya', 'lama', 'lamabulan', 'lamanyaprakerin'],
    ];

    /** Isi kolom Mitra yang berarti "belum jelas di mana" (sudah dinormalkan). */
    private const MITRA_BELUM_JELAS = ['pkl', 'prakerin', 'belum', 'belum ada', 'tidak ada', 'kosong', 'tbd'];

    /** Nama mirip mulai skor ini disarankan; mulai SKOR_CENTANG langsung dicentang di pratinjau. */
    private const SKOR_SARAN   = 80;
    private const SKOR_CENTANG = 90;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Berkas Excel contoh untuk diunduh. */
    public static function contoh(): string
    {
        $x  = new Spreadsheet();
        $ws = $x->getActiveSheet()->setTitle('Riwayat PKL');
        $ws->fromArray([
            ['NIS', 'Nama', 'Kelas', 'Perusahaan', 'Alamat', 'Kota', 'Telepon', 'Kontak', 'Jabatan', 'Mulai', 'Selesai'],
            ['252610001', 'Contoh Siswa Satu', 'XII TKJ 1', 'PT Contoh Teknologi', 'Jl. Contoh No. 1, Cikarang', 'Bekasi', '02188776655', 'Bapak Andi', 'HRD', '2026-01-05', '2026-04-05'],
            ['252610002', 'Contoh Siswa Dua', 'XII TKJ 1', 'PT Contoh Teknologi', 'Jl. Contoh No. 1, Cikarang', 'Bekasi', '02188776655', 'Bapak Andi', 'HRD', '2026-01-05', '2026-04-05'],
        ], null, 'A1');
        $ws->getStyle('A1:K1')->getFont()->setBold(true);
        foreach (range('A', 'K') as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
        $ws->getStyle('A2:A3')->getNumberFormat()->setFormatCode('@');
        $tmp = tempnam(sys_get_temp_dir(), 'pklx');
        (new Xlsx($x))->save($tmp);
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }

    /**
     * Baca berkas → pratinjau. Tidak menyimpan apa pun.
     *
     * @return array{kelompok: list<array<string,mixed>>, galat: list<array{baris:int, pesan:string}>, ringkas: array<string,int>}
     *         Format sekolah menambah: format, tahun_ajaran, lembar, saran, sudah, lewati (lihat bacaSekolah()).
     *
     * @throws \RuntimeException bila berkas tak bisa dibaca / judul kolom tak lengkap
     */
    public function baca(string $path): array
    {
        try {
            $buku = IOFactory::load($path);
            $data = $buku->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas tidak bisa dibaca. Pakai berkas Excel (.xlsx) atau CSV sesuai contoh.');
        }
        if (! isset(self::petaJudul($data[0] ?? [], self::KOLOM)['perusahaan'])) {
            $sekolah = $this->bacaSekolah($buku);
            if ($sekolah !== null) {
                return $sekolah;
            }
        }
        if (count($data) < 2) {
            throw new \RuntimeException('Berkas kosong — isi data mulai baris ke-2 (baris 1 = judul kolom).');
        }
        if (count($data) - 1 > self::MAKS_BARIS) {
            throw new \RuntimeException('Terlalu banyak baris (maksimal ' . self::MAKS_BARIS . ' per berkas). Pecah berkasnya.');
        }

        // Petakan judul → nomor kolom.
        $peta = self::petaJudul($data[0], self::KOLOM);
        foreach (['perusahaan'] as $wajib) {
            if (! isset($peta[$wajib])) {
                throw new \RuntimeException('Judul kolom "' . ucfirst($wajib) . '" tidak ditemukan di baris pertama. Unduh contoh berkas lalu ikuti judulnya — atau pakai format sekolah "DATA SISWA PRAKERIN" (kolom NAMA & Mitra DU/DI, satu lembar per kelas).');
            }
        }
        if (! isset($peta['nis']) && ! (isset($peta['nama']) && isset($peta['kelas']))) {
            throw new \RuntimeException('Perlu kolom NIS, atau kolom Nama + Kelas, untuk mengenali siswa.');
        }

        [$perNis, $perNamaKelas] = $this->petaSiswa();
        $aktif = $this->ajuanAktif();

        $kelompok = [];
        $galat    = [];
        $sudah    = [];
        foreach (array_slice($data, 1) as $i => $b) {
            $no  = $i + 2; // nomor baris di Excel
            $ambil = static fn (string $k): string => isset($peta[$k]) ? IsianBantu::rapikan((string) ($b[$peta[$k]] ?? '')) : '';
            if (implode('', array_map(static fn ($c) => trim((string) $c), $b)) === '') {
                continue; // baris kosong
            }

            $perusahaan = $ambil('perusahaan');
            $mentahMulai   = isset($peta['mulai']) ? trim((string) ($b[$peta['mulai']] ?? '')) : '';
            $mentahSelesai = isset($peta['selesai']) ? trim((string) ($b[$peta['selesai']] ?? '')) : '';
            $mulai      = isset($peta['mulai']) ? self::tanggal($b[$peta['mulai']] ?? null) : null;
            $selesai    = isset($peta['selesai']) ? self::tanggal($b[$peta['selesai']] ?? null) : null;
            if (mb_strlen($perusahaan) < 3) {
                $galat[] = ['baris' => $no, 'pesan' => 'Nama perusahaan kosong/terlalu pendek.'];
                continue;
            }
            // Tanggal opsional; bila diisi harus sah (salah ketik tidak boleh lolos diam-diam).
            if (($mentahMulai !== '' && $mulai === null) || ($mentahSelesai !== '' && $selesai === null)
                || ($mulai !== null && $selesai !== null && $selesai <= $mulai)) {
                $galat[] = ['baris' => $no, 'pesan' => 'Tanggal mulai/selesai tidak valid (pakai format 2026-01-05 atau 05/01/2026, selesai harus setelah mulai) — atau kosongkan keduanya.'];
                continue;
            }

            $nis  = $ambil('nis');
            $siswa = null;
            if ($nis !== '') {
                $siswa = $perNis[mb_strtolower($nis)] ?? null;
            } elseif ($ambil('nama') !== '' && $ambil('kelas') !== '') {
                $siswa = $perNamaKelas[mb_strtolower($ambil('nama')) . '|' . mb_strtolower($ambil('kelas'))] ?? null;
            }
            if ($siswa === null) {
                $galat[] = ['baris' => $no, 'pesan' => 'Siswa tidak ditemukan (' . ($nis !== '' ? 'NIS ' . $nis : $ambil('nama') . ' / ' . $ambil('kelas')) . '). Periksa NIS atau ejaan nama & kelas — harus sama dengan Master Siswa.'];
                continue;
            }
            $sid = (int) $siswa['id'];
            if (isset($sudah[$sid])) {
                $galat[] = ['baris' => $no, 'pesan' => $siswa['nama'] . ' muncul dua kali di berkas (juga di baris ' . $sudah[$sid] . ').'];
                continue;
            }
            if (isset($aktif[$sid])) {
                $galat[] = ['baris' => $no, 'pesan' => $siswa['nama'] . ' sudah punya ajuan PKL aktif (PKL-' . str_pad((string) $aktif[$sid]['id'], 5, '0', STR_PAD_LEFT) . ') — dilewati.'];
                continue;
            }
            $sudah[$sid] = $no;

            $norm  = PklForm::normPerusahaan($perusahaan);
            $kunci = $norm . '|' . $mulai . '|' . $selesai;
            $kelompok[$kunci] ??= [
                'perusahaan' => $perusahaan, 'norm' => $norm, 'alamat' => $ambil('alamat'), 'kota' => IsianBantu::judul($ambil('kota')),
                'telepon' => IsianBantu::telepon($ambil('telepon')), 'kontak_nama' => IsianBantu::judul($ambil('kontak')), 'kontak_jabatan' => $ambil('jabatan'),
                'mulai' => $mulai, 'selesai' => $selesai, 'siswa' => [],
            ];
            // Kolom opsional yang kosong di baris pertama dilengkapi dari baris berikutnya.
            foreach (['alamat' => 'alamat', 'telepon' => 'telepon', 'kontak_jabatan' => 'jabatan'] as $dest => $src) {
                if ($kelompok[$kunci][$dest] === '' && $ambil($src) !== '') {
                    $kelompok[$kunci][$dest] = $dest === 'telepon' ? IsianBantu::telepon($ambil($src)) : $ambil($src);
                }
            }
            $kelompok[$kunci]['siswa'][] = ['siswa_id' => $sid, 'kelas_id' => (int) $siswa['kelas_id'], 'nama' => $siswa['nama'], 'kelas' => $siswa['nama_kelas'], 'baris' => $no];
        }

        $kelompok = array_values($kelompok);

        return [
            'kelompok' => $kelompok,
            'galat'    => $galat,
            'ringkas'  => [
                'baris'    => count($data) - 1,
                'kelompok' => count($kelompok),
                'siswa'    => array_sum(array_map(static fn ($k) => count($k['siswa']), $kelompok)),
                'galat'    => count($galat),
            ],
        ];
    }

    /**
     * Simpan hasil pratinjau sebagai ajuan Disetujui. Tiap kelompok = satu transaksi sendiri,
     * jadi satu kelompok yang gagal tidak membatalkan yang lain.
     *
     * $pakai = kunci saran nama mirip yang dicentang (format sekolah). Saran yang tak dicentang tidak disimpan;
     * null = tak ada saran yang dipakai.
     *
     * @return array{dibuat: int, siswa: int, gagal: list<string>}
     */
    public function simpan(array $pratinjau, array $konteks, ?array $pakai = null): array
    {
        $ajuan = new PklAjuan($this->db);
        $out   = ['dibuat' => 0, 'siswa' => 0, 'gagal' => []];
        $cek   = array_flip(array_map('strval', $pakai ?? []));

        foreach ($pratinjau['kelompok'] as $k) {
            $siswa = array_values(array_filter($k['siswa'], static fn (array $s): bool => empty($s['saran']) || isset($cek[(string) ($s['kunci'] ?? '')])));
            if ($siswa === []) {
                continue; // isinya hanya saran yang tidak dicentang
            }
            $data = [
                'perusahaan_nama' => $k['perusahaan'], 'perusahaan_norm' => $k['norm'],
                'perusahaan_alamat' => $k['alamat'] !== '' ? $k['alamat'] : null, 'perusahaan_kota' => $k['kota'] !== '' ? $k['kota'] : null,
                'perusahaan_telepon' => $k['telepon'] !== '' ? $k['telepon'] : null, 'kontak_nama' => $k['kontak_nama'] !== '' ? $k['kontak_nama'] : null,
                'kontak_jabatan' => $k['kontak_jabatan'] !== '' ? $k['kontak_jabatan'] : null,
                'tanggal_mulai' => $k['mulai'], 'tanggal_selesai' => $k['selesai'], 'hp' => null,
            ];
            $anggota = [];
            foreach ($siswa as $i => $s) {
                $anggota[] = ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'] ?: null, 'peran' => $i === 0 ? 'pengaju' : 'teman'];
            }

            $r = $ajuan->kirimBaru($data, $anggota, $konteks + [
                'sumber' => 'impor', 'aksi' => 'impor', 'status_awal' => 'disetujui', 'tahun_ajaran' => $pratinjau['tahun_ajaran'] ?? null,
            ]);
            if ($r['ok']) {
                $out['dibuat']++;
                $out['siswa'] += count($anggota);
            } else {
                $out['gagal'][] = $k['perusahaan'] . (isset($k['kelas']) ? ' (' . $k['kelas'] . ')' : '') . ': ' . (($r['kode'] ?? '') === 'bentrok'
                    ? 'ada siswa yang baru saja punya ajuan aktif lain — dilewati.'
                    : 'gagal disimpan.');
            }
        }

        return $out;
    }

    // =================================================================
    // Format sekolah "DATA SISWA PRAKERIN"
    // =================================================================

    /**
     * Pratinjau berkas format sekolah, atau null bila tak satu lembar pun berjudul NAMA + Mitra.
     *
     * Tambahan dibanding format contoh: 'format' => 'sekolah', 'tahun_ajaran', 'lembar' (ringkasan per lembar),
     * 'saran' (nama mirip, perlu dicentang), 'sudah' (siswa yang sudah punya ajuan aktif — tidak diubah),
     * 'lewati' (Mitra belum jelas, mis. "PKL"). Galat memuat 'lembar' + 'baris'.
     *
     * @throws \RuntimeException
     */
    private function bacaSekolah(Spreadsheet $buku): ?array
    {
        $lembar = [];
        $tahun  = null;
        try {
            foreach ($buku->getWorksheetIterator() as $ws) {
                $data = $ws->toArray(null, true, false, false);
                $kop  = self::kopSekolah($data);
                if ($kop === null) {
                    continue;
                }
                foreach (array_slice($data, 0, $kop['judul']) as $b) {
                    if ($tahun === null && preg_match('/TAHUN\s*(?:PELAJARAN|AJARAN)\s*(\d{4})\s*[\/\-]\s*(\d{4})/i', implode(' ', array_map(static fn ($c) => (string) $c, $b)), $m)) {
                        $tahun = $m[1] . '/' . $m[2];
                    }
                }
                $lembar[] = ['judul' => IsianBantu::rapikan($ws->getTitle()), 'kop' => $kop, 'data' => $data];
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas tidak bisa dibaca. Pakai berkas Excel (.xlsx) atau CSV sesuai contoh.');
        }
        if ($lembar === []) {
            return null;
        }

        // Baris bernama per lembar (baris kosong & baris tanpa nama diabaikan).
        $baris = [];
        foreach ($lembar as $li => $l) {
            $k = $l['kop']['kolom'];
            foreach ($l['data'] as $i => $b) {
                if ($i < $l['kop']['data']) {
                    continue;
                }
                $nama = IsianBantu::rapikan((string) ($b[$k['nama']] ?? ''));
                if ($nama === '' || mb_strlen(self::normNama($nama)) < 2) {
                    continue;
                }
                $baris[] = [
                    'lembar' => $li, 'no' => $i + 1, 'nama' => $nama,
                    'mitra'  => isset($k['mitra']) ? IsianBantu::rapikan((string) ($b[$k['mitra']] ?? '')) : '',
                    'mulai'  => isset($k['mulai']) ? ($b[$k['mulai']] ?? null) : null,
                    'selesai' => isset($k['selesai']) ? ($b[$k['selesai']] ?? null) : null,
                    'lama'   => isset($k['lama']) ? ($b[$k['lama']] ?? null) : null,
                ];
            }
        }
        if ($baris === []) {
            throw new \RuntimeException('Berkas format "DATA SISWA PRAKERIN" terbaca, tapi kolom NAMA kosong di semua lembar.');
        }
        if (count($baris) > self::MAKS_BARIS) {
            throw new \RuntimeException('Terlalu banyak baris (maksimal ' . self::MAKS_BARIS . ' siswa per berkas). Pecah berkasnya.');
        }

        // Kelas sistem untuk tiap lembar (nama lembar = nama kelas).
        $kelas = $this->db->table('kelas')->select('id, nama_kelas, tingkat')->where('deleted_at', null)->get()->getResultArray();
        foreach ($lembar as $li => $l) {
            $lembar[$li]['kelas'] = self::cariKelas($l['judul'], $kelas);
        }

        // Siswa aktif per kelas & per nama (nama dinormalkan).
        $perKelas = [];
        $perNama  = [];
        foreach ($this->db->table('siswa s')->select('s.id, s.nama, s.kelas_id, k.nama_kelas, k.tingkat')
            ->join('kelas k', 'k.id = s.kelas_id AND k.deleted_at IS NULL', 'left')->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->get()->getResultArray() as $r) {
            $n = self::normNama((string) $r['nama']);
            $perKelas[(int) $r['kelas_id']][$n][] = $r;
            $perNama[$n][] = $r;
        }
        $aktif = $this->ajuanAktif();

        // Langkah 1: cocok persis (semua baris, ada mitra atau tidak, supaya siswanya tak ikut jadi calon "mirip").
        $dipakai = []; // siswa_id => indeks baris pertama yang memakainya
        foreach ($baris as $bi => $b) {
            $kls = $lembar[$b['lembar']]['kelas'];
            $n   = self::normNama($b['nama']);
            $cal = $kls !== null ? ($perKelas[(int) $kls['id']][$n] ?? []) : [];
            if ($cal === []) {
                // Pindah kelas: cari di kelas lain setingkat (lembar tak dikenali: seluruh sekolah).
                $cal = array_values(array_filter($perNama[$n] ?? [], static fn (array $s): bool => $kls === null || $s['tingkat'] === $kls['tingkat']));
            }
            if (count($cal) === 1) {
                $baris[$bi]['siswa'] = $cal[0];
                $dipakai[(int) $cal[0]['id']] ??= $bi;
            } elseif (count($cal) > 1) {
                $baris[$bi]['kembar'] = array_column($cal, 'nama_kelas');
            }
        }

        $kelompok = [];
        $galat    = [];
        $saran    = [];
        $sudah    = [];
        $lewati   = [];
        $siap     = [];
        $hitung = array_fill_keys(array_keys($lembar), ['nama' => 0, 'mitra' => 0, 'siap' => 0, 'saran' => 0, 'sudah' => 0, 'galat' => 0, 'lewati' => 0]);
        foreach ($baris as $bi => $b) {
            $li  = $b['lembar'];
            $lbl = $lembar[$li]['judul'];
            $kls = $lembar[$li]['kelas'];
            $hitung[$li]['nama']++;
            if ($b['mitra'] === '') {
                continue; // belum ada mitra: tidak diimpor, cukup dihitung
            }
            $hitung[$li]['mitra']++;
            $tolak = static function (string $pesan) use (&$galat, &$hitung, $li, $lbl, $b): void {
                $galat[] = ['baris' => $b['no'], 'lembar' => $lbl, 'pesan' => $pesan];
                $hitung[$li]['galat']++;
            };

            $norm = PklForm::normPerusahaan($b['mitra']);
            if (mb_strlen($norm) < 3 || in_array($norm, self::MITRA_BELUM_JELAS, true)) {
                $lewati[] = ['baris' => $b['no'], 'lembar' => $lbl, 'nama' => $b['nama'], 'mitra' => $b['mitra']];
                $hitung[$li]['lewati']++;
                continue;
            }

            [$mulai, $selesai, $salahTgl] = self::tanggalSekolah($b['mulai'], $b['selesai'], $b['lama']);
            if ($salahTgl) {
                $tolak('Tanggal mulai "' . $b['nama'] . '" tidak valid — isi tanggal (mis. 28/07/2026) atau kosongkan.');
                continue;
            }

            $s     = $b['siswa'] ?? null;
            $isSar = null;
            if ($s === null) {
                // Langkah 2: nama mirip di kelas lembar ini, dari siswa yang belum dipakai baris lain.
                [$calon, $skor, $kedua] = $kls !== null ? self::calonMirip($b['nama'], $perKelas[(int) $kls['id']] ?? [], $dipakai) : [null, 0, 0];
                if ($calon !== null && $skor >= self::SKOR_SARAN && $kedua <= $skor - 5) {
                    $s     = $calon;
                    $isSar = $skor;
                    $dipakai[(int) $calon['id']] = $bi;
                } elseif (isset($b['kembar'])) {
                    $tolak('Ada ' . count($b['kembar']) . ' siswa bernama "' . $b['nama'] . '" (' . implode(', ', array_unique($b['kembar'])) . ') — tidak bisa dipastikan yang mana.');
                    continue;
                } else {
                    $tolak('"' . $b['nama'] . '" tidak ditemukan ' . ($kls !== null ? 'di kelas ' . $kls['nama_kelas'] : '(nama lembar "' . $lbl . '" tidak cocok dengan kelas mana pun)')
                        . ($calon !== null && $skor >= 60 ? ' — mungkin maksudnya "' . $calon['nama'] . '"?' : '') . ' Samakan ejaannya dengan Data Siswa, lalu unggah lagi.');
                    continue;
                }
            }

            $sid = (int) $s['id'];
            if (isset($siap[$sid])) {
                $tolak($s['nama'] . ' muncul dua kali (juga di ' . $siap[$sid] . ').');
                continue;
            }
            if (isset($aktif[$sid])) {
                $a = $aktif[$sid];
                $sudah[] = [
                    'baris' => $b['no'], 'lembar' => $lbl, 'nama' => $s['nama'], 'mitra' => $b['mitra'], 'sistem' => (string) $a['perusahaan_nama'],
                    'kode' => PklPengajuanModel::kode((int) $a['id']), 'status' => (string) $a['status'],
                    'beda' => PklForm::normPerusahaan((string) $a['perusahaan_nama']) !== $norm,
                ];
                $hitung[$li]['sudah']++;
                continue;
            }
            $siap[$sid] = $lbl . ' baris ' . $b['no'];

            $isi = ['siswa_id' => $sid, 'kelas_id' => (int) $s['kelas_id'], 'nama' => (string) $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? ''), 'baris' => $b['no'], 'lembar' => $lbl];
            if ($isSar !== null) {
                $kunci  = 'l' . $li . 'b' . $b['no'];
                $isi   += ['saran' => true, 'kunci' => $kunci];
                $saran[] = ['kunci' => $kunci, 'lembar' => $lbl, 'baris' => $b['no'], 'excel' => $b['nama'], 'sistem' => (string) $s['nama'],
                    'kelas' => (string) ($s['nama_kelas'] ?? ''), 'skor' => $isSar, 'centang' => $isSar >= self::SKOR_CENTANG, 'mitra' => $b['mitra']];
                $hitung[$li]['saran']++;
            } else {
                $hitung[$li]['siap']++;
            }
            if ($kls !== null && (int) $s['kelas_id'] !== (int) $kls['id']) {
                $isi['catatan'] = 'di sistem kelas ' . ($s['nama_kelas'] ?? '?');
            }

            $kunciK = $li . '|' . $norm . '|' . $mulai . '|' . $selesai;
            $kelompok[$kunciK] ??= [
                'perusahaan' => $b['mitra'], 'norm' => $norm, 'alamat' => '', 'kota' => '', 'telepon' => '', 'kontak_nama' => '', 'kontak_jabatan' => '',
                'mulai' => $mulai, 'selesai' => $selesai, 'kelas' => $kls['nama_kelas'] ?? $lbl, 'siswa' => [],
            ];
            $kelompok[$kunciK]['siswa'][] = $isi;
        }

        // Satu ajuan paling banyak N siswa (aturan sekolah, Pengaturan PKL) — kelompok besar dipecah.
        $maks  = PklPengaturanModel::maksSiswa((new PklPengaturanModel())->ambil());
        $pecah = [];
        foreach ($kelompok as $k) {
            foreach (array_chunk($k['siswa'], $maks) as $potong) {
                $pecah[] = ['siswa' => $potong] + $k;
            }
        }

        $ringkasLembar = [];
        foreach ($lembar as $li => $l) {
            $ringkasLembar[] = ['judul' => $l['judul'], 'kelas' => $l['kelas']['nama_kelas'] ?? null] + $hitung[$li];
        }
        $jmlSaran = count($saran);

        return [
            'format'       => 'sekolah',
            'tahun_ajaran' => $tahun ?? (((new SettingModel())->get()['academic_year'] ?? '') ?: null),
            'maks_siswa'   => $maks,
            'lembar'       => $ringkasLembar,
            'kelompok'     => $pecah,
            'galat'        => $galat,
            'saran'        => $saran,
            'sudah'        => $sudah,
            'lewati'       => $lewati,
            'ringkas'      => [
                'baris'       => count($baris),
                'mitra'       => array_sum(array_column($hitung, 'mitra')),
                'kelompok'    => count($pecah),
                'siswa'       => array_sum(array_column($hitung, 'siap')),
                'saran'       => $jmlSaran,
                'sudah'       => count($sudah),
                'lewati'      => count($lewati),
                'galat'       => count($galat),
                'tanpa_mitra' => count($baris) - array_sum(array_column($hitung, 'mitra')),
            ],
        ];
    }

    /**
     * Cari baris judul format sekolah di 15 baris pertama: baris yang memuat NAMA + Mitra; judul Mulai/Selesai/Lamanya
     * boleh di baris itu atau baris bawahnya (sel gabungan "Praktek Kerja Lapangan").
     *
     * @return array{judul: int, data: int, kolom: array<string, int>}|null indeks 0-based
     */
    private static function kopSekolah(array $data): ?array
    {
        foreach (array_slice($data, 0, 15, true) as $r => $b) {
            $peta = self::petaJudul($b, self::KOLOM_SEKOLAH);
            if (! isset($peta['nama'], $peta['mitra'])) {
                continue;
            }
            $mulaiData = $r + 1;
            $bawah     = self::petaJudul($data[$r + 1] ?? [], self::KOLOM_SEKOLAH);
            unset($bawah['nama'], $bawah['mitra']);
            if ($bawah !== []) {
                $peta     += $bawah;
                $mulaiData = $r + 2;
            }

            return ['judul' => $r, 'data' => $mulaiData, 'kolom' => $peta];
        }

        return null;
    }

    /**
     * Lembar → kelas sistem: nama sama persis, atau tingkat + nomor sama dengan kode jurusan yang satu awalan yang lain
     * ("XII AK" → "XII AKL", "XII MPLB 1" → "XII MP 1") asal hanya satu kelas yang cocok.
     *
     * @param list<array{id: int|string, nama_kelas: string, tingkat: string}> $kelas
     */
    private static function cariKelas(string $judul, array $kelas): ?array
    {
        $rapi = static fn (string $s): string => trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($s)) ?? '') ?? '');
        $urai = static function (string $s): ?array {
            if (! preg_match('/(?:^|\s)(XII|XI|X|12|11|10)\s+([A-Z]+)(?:\s+(\d+))?(?:\s|$)/', $s, $m)) {
                return null;
            }

            return [['10' => 'X', '11' => 'XI', '12' => 'XII'][$m[1]] ?? $m[1], $m[2], ltrim($m[3] ?? '', '0')];
        };
        $j = $rapi($judul);
        foreach ($kelas as $k) {
            if ($rapi((string) $k['nama_kelas']) === $j) {
                return $k;
            }
        }
        $u = $urai($j);
        if ($u === null) {
            return null;
        }
        $cocok = array_values(array_filter($kelas, static function (array $k) use ($u, $urai, $rapi): bool {
            $v = $urai($rapi((string) $k['nama_kelas']));

            return $v !== null && $v[0] === $u[0] && $v[2] === $u[2] && (str_starts_with($v[1], $u[1]) || str_starts_with($u[1], $v[1]));
        }));

        return count($cocok) === 1 ? $cocok[0] : null;
    }

    /**
     * Siswa paling mirip di satu kelas (yang belum dipakai baris lain).
     *
     * @param array<string, list<array>> $calon nama dinormalkan => siswa
     *
     * @return array{0: ?array, 1: int, 2: int} [siswa terbaik, skornya, skor terbaik kedua]
     */
    private static function calonMirip(string $nama, array $calon, array $dipakai): array
    {
        $n      = self::normNama($nama);
        $best   = null;
        $skor   = 0;
        $kedua  = 0;
        foreach ($calon as $kn => $daftar) {
            foreach ($daftar as $s) {
                if (isset($dipakai[(int) $s['id']])) {
                    continue;
                }
                $v = self::skorMirip($n, (string) $kn);
                if ($v > $skor) {
                    $kedua = $skor;
                    $skor  = $v;
                    $best  = $s;
                } elseif ($v > $kedua) {
                    $kedua = $v;
                }
            }
        }

        return [$best, $skor, $kedua];
    }

    /** 0–100: kemiripan huruf; nama yang kata-katanya awalan nama lain ("Budi Santoso" ↔ "Budi Santoso Putra") minimal 90. */
    private static function skorMirip(string $a, string $b): int
    {
        similar_text($a, $b, $persen);
        $skor = (int) round($persen);
        $wa   = explode(' ', $a);
        $wb   = explode(' ', $b);
        [$pendek, $panjang] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];
        if (count($pendek) >= 2 && array_slice($panjang, 0, count($pendek)) === $pendek) {
            $skor = max($skor, 90);
        }

        return $skor;
    }

    /** Nama untuk dicocokkan: huruf kecil, tanda baca jadi spasi ("Nur'aini" = "Nur Aini"). */
    private static function normNama(string $s): string
    {
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    /**
     * Tanggal format sekolah. Selesai di berkas sekolah biasanya rumus =EDATE(Mulai, Lamanya): tanpa Mulai, hasil rumusnya
     * (angka kecil) diabaikan; Selesai kosong/tak masuk akal tetapi Lamanya diisi → dihitung dari Mulai + Lamanya bulan.
     *
     * @return array{0: ?string, 1: ?string, 2: bool} [mulai, selesai, mulai tak valid]
     */
    private static function tanggalSekolah($mulai, $selesai, $lama): array
    {
        if ($mulai === null || trim((string) $mulai) === '') {
            return [null, null, false];
        }
        $m = self::tanggal($mulai);
        if ($m === null) {
            return [null, null, true];
        }
        $s = self::tanggal($selesai);
        if ($s === null || $s <= $m) {
            $bulan = is_numeric($lama) ? (int) $lama : 0;
            $s     = $bulan >= 1 && $bulan <= 24 ? self::tambahBulan($m, $bulan) : null;
        }

        return [$m, $s, false];
    }

    /** Seperti EDATE Excel: 31 Jan + 1 bulan = 28/29 Feb. */
    private static function tambahBulan(string $ymd, int $bulan): string
    {
        $t    = new \DateTimeImmutable($ymd);
        $awal = $t->modify('first day of this month')->modify('+' . $bulan . ' month');

        return $awal->setDate((int) $awal->format('Y'), (int) $awal->format('n'), min((int) $t->format('j'), (int) $awal->format('t')))->format('Y-m-d');
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Baris judul → [kunci => indeks kolom] menurut daftar alias. */
    private static function petaJudul(array $baris, array $kolom): array
    {
        $peta = [];
        foreach ($baris as $i => $judul) {
            $n = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $judul)));
            foreach ($kolom as $kunci => $alias) {
                if (in_array($n, $alias, true) && ! isset($peta[$kunci])) {
                    $peta[$kunci] = $i;
                }
            }
        }

        return $peta;
    }

    /** Ajuan aktif per siswa: siswa_id => [id, status, perusahaan_nama]. */
    private function ajuanAktif(): array
    {
        $aktif = [];
        foreach ($this->db->table('pkl_anggota a')->select('a.siswa_aktif, p.id, p.status, p.perusahaan_nama')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')->where('a.siswa_aktif IS NOT NULL', null, false)->get()->getResultArray() as $r) {
            $aktif[(int) $r['siswa_aktif']] = $r;
        }

        return $aktif;
    }

    /** Siswa aktif: peta menurut NIS dan menurut "nama|kelas" (huruf kecil). @return array{0: array, 1: array} */
    private function petaSiswa(): array
    {
        $perNis = [];
        $perNK  = [];
        foreach ($this->db->table('siswa s')->select('s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->get()->getResultArray() as $r) {
            $perNis[mb_strtolower((string) $r['nis'])] = $r;
            $perNK[mb_strtolower(IsianBantu::rapikan((string) $r['nama'])) . '|' . mb_strtolower((string) $r['nama_kelas'])] = $r;
        }

        return [$perNis, $perNK];
    }

    /** Sel Excel (serial angka / teks) → Y-m-d atau null. */
    private static function tanggal($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return TanggalExcel::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        $s = trim((string) $v);
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y'] as $fmt) {
            $t = \DateTimeImmutable::createFromFormat('!' . $fmt, $s);
            if ($t !== false && $t->format($fmt) === $s) {
                $y = (int) $t->format('Y');

                return ($y >= 2015 && $y <= (int) date('Y') + 3) ? $t->format('Y-m-d') : null;
            }
        }

        return null;
    }
}
