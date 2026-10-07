<?php

namespace App\Libraries;

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
 * Kolom (judul baris pertama, urutan bebas): NIS, Nama, Kelas, Perusahaan, Alamat, Kota, Telepon,
 * Kontak, Jabatan, Mulai, Selesai (dua terakhir opsional). Siswa dicocokkan lewat NIS; bila NIS
 * kosong, lewat Nama + Kelas.
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
     *
     * @throws \RuntimeException bila berkas tak bisa dibaca / judul kolom tak lengkap
     */
    public function baca(string $path): array
    {
        try {
            $data = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas tidak bisa dibaca. Pakai berkas Excel (.xlsx) atau CSV sesuai contoh.');
        }
        if (count($data) < 2) {
            throw new \RuntimeException('Berkas kosong — isi data mulai baris ke-2 (baris 1 = judul kolom).');
        }
        if (count($data) - 1 > self::MAKS_BARIS) {
            throw new \RuntimeException('Terlalu banyak baris (maksimal ' . self::MAKS_BARIS . ' per berkas). Pecah berkasnya.');
        }

        // Petakan judul → nomor kolom.
        $peta = [];
        foreach ($data[0] as $i => $judul) {
            $n = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $judul)));
            foreach (self::KOLOM as $kunci => $alias) {
                if (in_array($n, $alias, true) && ! isset($peta[$kunci])) {
                    $peta[$kunci] = $i;
                }
            }
        }
        foreach (['perusahaan'] as $wajib) {
            if (! isset($peta[$wajib])) {
                throw new \RuntimeException('Judul kolom "' . ucfirst($wajib) . '" tidak ditemukan di baris pertama. Unduh contoh berkas lalu ikuti judulnya.');
            }
        }
        if (! isset($peta['nis']) && ! (isset($peta['nama']) && isset($peta['kelas']))) {
            throw new \RuntimeException('Perlu kolom NIS, atau kolom Nama + Kelas, untuk mengenali siswa.');
        }

        [$perNis, $perNamaKelas] = $this->petaSiswa();
        $aktif = [];
        foreach ($this->db->table('pkl_anggota')->select('siswa_aktif, pengajuan_id')->where('siswa_aktif IS NOT NULL', null, false)->get()->getResultArray() as $r) {
            $aktif[(int) $r['siswa_aktif']] = (int) $r['pengajuan_id'];
        }

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
                $galat[] = ['baris' => $no, 'pesan' => $siswa['nama'] . ' sudah punya ajuan PKL aktif (PKL-' . str_pad((string) $aktif[$sid], 5, '0', STR_PAD_LEFT) . ') — dilewati.'];
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
     * @return array{dibuat: int, siswa: int, gagal: list<string>}
     */
    public function simpan(array $pratinjau, array $konteks): array
    {
        $ajuan = new PklAjuan($this->db);
        $out   = ['dibuat' => 0, 'siswa' => 0, 'gagal' => []];

        foreach ($pratinjau['kelompok'] as $k) {
            $data = [
                'perusahaan_nama' => $k['perusahaan'], 'perusahaan_norm' => $k['norm'],
                'perusahaan_alamat' => $k['alamat'] !== '' ? $k['alamat'] : null, 'perusahaan_kota' => $k['kota'] !== '' ? $k['kota'] : null,
                'perusahaan_telepon' => $k['telepon'] !== '' ? $k['telepon'] : null, 'kontak_nama' => $k['kontak_nama'] !== '' ? $k['kontak_nama'] : null,
                'kontak_jabatan' => $k['kontak_jabatan'] !== '' ? $k['kontak_jabatan'] : null,
                'tanggal_mulai' => $k['mulai'], 'tanggal_selesai' => $k['selesai'], 'hp' => null,
            ];
            $anggota = [];
            foreach ($k['siswa'] as $i => $s) {
                $anggota[] = ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'] ?: null, 'peran' => $i === 0 ? 'pengaju' : 'teman'];
            }

            $r = $ajuan->kirimBaru($data, $anggota, $konteks + ['sumber' => 'impor', 'aksi' => 'impor', 'status_awal' => 'disetujui']);
            if ($r['ok']) {
                $out['dibuat']++;
                $out['siswa'] += count($anggota);
            } else {
                $out['gagal'][] = $k['perusahaan'] . ': ' . (($r['kode'] ?? '') === 'bentrok'
                    ? 'ada siswa yang baru saja punya ajuan aktif lain — dilewati.'
                    : 'gagal disimpan.');
            }
        }

        return $out;
    }

    // =================================================================
    // Pembantu
    // =================================================================

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
