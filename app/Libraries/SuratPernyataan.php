<?php

namespace App\Libraries;

use App\Models\KelasModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat Pernyataan dan Persetujuan Orang Tua/Wali PKL — formulir yang dicetak SATU HALAMAN PER SISWA lalu diisi dan
 * ditandatangani orang tua di atas materai (rancangan: docs/DESAIN-SURAT-SEKOLAH.md, L4). Tanpa nomor dan tanpa ACC:
 * surat langsung "siap unduh". Template = contoh sekolah persis (Libraries/Surat/pernyataan_ortu.docx).
 *
 * Cakupan pembuatan: per KELAS (satu surat per kelas, semua siswa aktifnya), per AJUAN PKL (satu surat per ajuan, semua
 * anggotanya), atau SISWA PILIHAN (satu surat). Data siswa selalu terisi; data orang tua diisi dari data sekolah bila
 * dipilih — yang kosong tetap titik-titik seperti contoh, supaya diisi tangan.
 *
 * Isian JSON surat_sekolah.isi: cakupan (kelas|ajuan|siswa), kelas (nama kelas, cakupan kelas), isi_ortu (bool), kunci.
 */
final class SuratPernyataan
{
    public const CAKUPAN      = ['kelas', 'ajuan', 'siswa'];
    public const MAKS_PILIHAN = 60;  // kelas / ajuan sekaligus
    public const MAKS_HALAMAN = 600; // halaman (= siswa) dalam satu unduhan

    /** Isian "pekerjaan" yang bukan pekerjaan (orang tua sudah tiada, tanda kosong) → dibiarkan titik-titik. */
    private const BUKAN_PEKERJAAN = ['-', '--', '0', 'sudah meninggal', 'meninggal', 'meninggal dunia', 'almarhum', 'almarhumah', 'alm', 'almh', 'tidak ada', 'tidak diketahui'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Kotak data kosong di contoh sekolah: 22 karakter "…". */
    public static function titik(): string
    {
        return str_repeat('…', 22);
    }

    /** Isi kurung tanda tangan yang kosong di contoh sekolah: 48 titik. */
    public static function titikTtd(): string
    {
        return str_repeat('.', 48);
    }

    // =================================================================
    // Data orang tua (murni)
    // =================================================================

    /**
     * Data orang tua/wali yang dicetak untuk seorang siswa (baris siswa + kolom data orang tua). Nama: "Nama Orang Tua" di
     * data sekolah, bila kosong ayah → ibu → wali. Pekerjaan hanya bila jelas milik orang yang namanya tercetak. Alamat:
     * alamat orang tua (lengkap dengan RT/RW, kelurahan, kecamatan, kota), bila kosong alamat siswa. HP: telepon orang tua,
     * bila kosong HP wali. Yang tak diketahui = '' (dicetak titik-titik).
     *
     * @param array<string, mixed> $a
     *
     * @return array{nama: string, pekerjaan: string, alamat: string, hp: string}
     */
    public static function ortu(array $a): array
    {
        $v = static fn (string $k): string => IsianBantu::rapikan((string) ($a[$k] ?? ''));

        $nama = '';
        foreach (['nama_orang_tua', 'nama_ayah', 'nama_ibu', 'nama_wali'] as $k) {
            if ($v($k) !== '') {
                $nama = $v($k);
                break;
            }
        }

        $pekerjaan = '';
        $n         = self::norm($nama);
        foreach (['ayah', 'ibu', 'wali'] as $s) {
            if ($n !== '' && self::norm($v('nama_' . $s)) === $n) {
                $pekerjaan = $v('pekerjaan_' . $s);
                break;
            }
        }
        if (in_array(mb_strtolower(trim($pekerjaan, ' .')), self::BUKAN_PEKERJAAN, true)) {
            $pekerjaan = '';
        }

        $alamat = self::alamatLengkap($v('ortu_alamat'), $v('ortu_rt'), $v('ortu_rw'), $v('ortu_kelurahan'), $v('ortu_kecamatan'), $v('ortu_kota'));
        if ($alamat === '') {
            $alamat = self::alamatLengkap($v('alamat'), $v('rt'), $v('rw'), $v('kelurahan'), $v('kecamatan'), $v('kota'));
        }

        return ['nama' => $nama, 'pekerjaan' => $pekerjaan, 'alamat' => $alamat, 'hp' => $v('ortu_telepon') !== '' ? $v('ortu_telepon') : $v('no_hp_wali')];
    }

    /**
     * Alamat satu baris: "Jl. Mawar 5, RT 001/RW 002, Bahagia, Kec. Babelan, Bekasi". Bagian yang sudah tertulis di
     * alamat dasar tidak diulang (data sekolah sering menulis RT/kelurahan di kolom alamat).
     */
    public static function alamatLengkap(string $dasar, string $rt, string $rw, string $kel, string $kec, string $kota): string
    {
        $hasil  = IsianBantu::rapikan($dasar);
        $tambah = static function (string $teks, string $awalan = '') use (&$hasil): void {
            $teks = IsianBantu::rapikan($teks);
            if ($teks === '' || ($hasil !== '' && str_contains(self::norm($hasil), self::norm($teks)))) {
                return;
            }
            $hasil .= ($hasil !== '' ? ', ' : '') . $awalan . $teks;
        };

        $angka = static fn (string $s): string => ctype_digit($s) ? ((int) $s > 0 ? sprintf('%03d', (int) $s) : '') : IsianBantu::rapikan($s);
        [$rt, $rw] = [$angka(trim($rt)), $angka(trim($rw))];
        if (($rt !== '' || $rw !== '') && ! preg_match('~(^|[^a-z])rt([^a-z]|$)~i', $hasil)) {
            $tambah(trim(($rt !== '' ? 'RT ' . $rt : '') . ($rt !== '' && $rw !== '' ? '/' : '') . ($rw !== '' ? 'RW ' . $rw : '')));
        }
        $tambah($kel);
        $kec = IsianBantu::rapikan($kec);
        if ($kec !== '' && ! str_contains(self::norm($hasil), self::norm($kec))) {
            $tambah($kec, preg_match('~^kec(amatan)?[.\s]~i', $kec) ? '' : 'Kec. ');
        }
        $tambah($kota);

        return $hasil;
    }

    /** Huruf kecil, hanya huruf/angka, spasi tunggal — untuk membandingkan nama/alamat. */
    private static function norm(string $s): string
    {
        return trim((string) preg_replace('~\s+~u', ' ', (string) preg_replace('~[^\p{L}\p{N}]+~u', ' ', mb_strtolower($s))));
    }

    // =================================================================
    // Halaman surat (penanda template, satu per siswa)
    // =================================================================

    /**
     * Butir PklDocx::dariTemplate untuk satu surat Pernyataan: satu butir (= satu halaman) per siswa.
     *
     * @param array<string, mixed>       $surat baris surat_sekolah
     * @param list<array<string, mixed>> $siswa hasil SuratSekolah::siswa() (memuat kolom data orang tua)
     *
     * @return list<array{v: array<string, string>, siswa: list<array<string, string>>, meta: array<string, mixed>}>
     */
    public static function halaman(array $surat, array $siswa): array
    {
        $isi     = SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''));
        $isiOrtu = ! empty($isi['isi_ortu']);
        $kosong  = self::titik();
        $atau    = static fn (string $s): string => trim($s) !== '' ? trim($s) : $kosong;

        $out = [];
        foreach (array_values($siswa) as $a) {
            $o    = $isiOrtu ? self::ortu($a) : ['nama' => '', 'pekerjaan' => '', 'alamat' => '', 'hp' => ''];
            $hp   = trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? '');
            $nama = IsianBantu::rapikan((string) ($a['nama'] ?? ''));

            $out[] = [
                'v' => [
                    'anak_nama'      => $atau($nama),
                    'anak_kelas'     => $atau((string) ($a['nama_kelas'] ?? '')),
                    'anak_jurusan'   => $atau((string) ($a['jurusan_nama'] ?? '')),
                    'anak_hp'        => $atau($hp),
                    'ortu_nama'      => $atau($o['nama']),
                    'ortu_pekerjaan' => $atau($o['pekerjaan']),
                    'ortu_alamat'    => $atau($o['alamat']),
                    'ortu_hp'        => $atau($o['hp']),
                    'ttd_anak'       => $nama !== '' ? $nama : self::titikTtd(),
                    'ttd_ortu'       => $o['nama'] !== '' ? $o['nama'] : self::titikTtd(),
                    'tahun'          => substr((string) ($surat['tanggal_surat'] ?? date('Y-m-d')), 0, 4),
                ],
                'siswa' => [],
                'meta'  => ['urut' => 0, 'nomor' => '', 'jumlah' => 1, 'perusahaan' => (string) ($surat['perusahaan_nama'] ?? ''), 'siswa_id' => (int) ($a['siswa_id'] ?? 0)],
            ];
        }

        return $out;
    }

    // =================================================================
    // Pilihan formulir
    // =================================================================

    /**
     * Kelas yang boleh PKL (Pengaturan PKL → tingkat; kosong = semua) yang punya siswa aktif, urut tingkat lalu nama.
     *
     * @param array<string, mixed> $p
     *
     * @return list<array{id: int, nama: string, tingkat: string, jml: int}>
     */
    public function kelas(array $p): array
    {
        $boleh = PklPengaturanModel::tingkatBoleh($p);
        $b     = $this->db->table('kelas k')
            ->select('k.id, k.nama_kelas, k.tingkat, COUNT(s.id) AS jml')
            ->join('siswa s', 's.kelas_id = k.id')
            ->where('k.deleted_at', null)->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->groupBy('k.id, k.nama_kelas, k.tingkat');
        if ($boleh !== []) {
            $b->whereIn('k.tingkat', $boleh);
        }
        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        $rows = $b->get()->getResultArray();
        usort($rows, static fn (array $x, array $y) => (($urut[$x['tingkat']] ?? 9) <=> ($urut[$y['tingkat']] ?? 9)) ?: KelasModel::bandingNatural((string) $x['nama_kelas'], (string) $y['nama_kelas']));

        return array_map(static fn (array $r) => ['id' => (int) $r['id'], 'nama' => (string) $r['nama_kelas'], 'tingkat' => (string) $r['tingkat'], 'jml' => (int) $r['jml']], $rows);
    }

    /**
     * Siswa aktif di kelas-kelas tersebut (untuk pemilih "siswa pilihan"), urut nama.
     *
     * @param list<int> $kelasIds
     *
     * @return list<array{id: int, nama: string, nis: string, kelas_id: int}>
     */
    public function siswaKelas(array $kelasIds): array
    {
        if ($kelasIds === []) {
            return [];
        }

        return array_map(static fn (array $r) => ['id' => (int) $r['id'], 'nama' => (string) $r['nama'], 'nis' => (string) ($r['nis'] ?? ''), 'kelas_id' => (int) $r['kelas_id']],
            $this->db->table('siswa')->select('id, nama, nis, kelas_id')->whereIn('kelas_id', $kelasIds)
                ->where('deleted_at', null)->where('status', 'aktif')->orderBy('nama', 'ASC')->get()->getResultArray());
    }

    /**
     * Ajuan PKL yang masih berjalan (menunggu / perbaikan / disetujui), terbaru dulu, dengan anggota yang masih aktif
     * (pengaju dulu). $ids = hanya ajuan itu.
     *
     * @param list<int>|null $ids
     *
     * @return list<array{id: int, kode: string, status: string, perusahaan: string, siswa: list<array{siswa_id: int, kelas_id: ?int, hp: ?string, nama: string, kelas: string}>}>
     */
    public function ajuan(?array $ids = null): array
    {
        $b = $this->db->table('pkl_pengajuan p')
            ->select('p.id, p.status, p.perusahaan_nama, a.siswa_id, a.kelas_id, a.hp, s.nama, k.nama_kelas')
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->whereIn('p.status', ['menunggu', 'perbaikan', 'disetujui']);
        if ($ids !== null) {
            $b->whereIn('p.id', $ids !== [] ? $ids : [0]);
        }
        $rows = $b->orderBy('p.id', 'DESC')->orderBy('a.peran', 'ASC')->orderBy('a.id', 'ASC')->get()->getResultArray(); // ENUM: pengaju sebelum teman

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[$id] ??= ['id' => $id, 'kode' => PklPengajuanModel::kode($id), 'status' => (string) $r['status'], 'perusahaan' => (string) $r['perusahaan_nama'], 'siswa' => []];
            $out[$id]['siswa'][] = [
                'siswa_id' => (int) $r['siswa_id'], 'kelas_id' => ((int) $r['kelas_id']) ?: null,
                'hp' => trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : null, 'nama' => (string) $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''),
            ];
        }

        return array_values($out);
    }

    // =================================================================
    // Masukan & membuat surat
    // =================================================================

    /**
     * Periksa masukan formulir.
     *
     * @param array<string, mixed> $in
     *
     * @return array{ok: bool, galat: list<string>, tanggal_surat: string, cakupan: string, isi_ortu: bool, pilihan: list<int>}
     */
    public static function periksa(array $in): array
    {
        $galat = [];
        $thn   = (int) date('Y');
        $tgl   = IsianBantu::tanggal(trim((string) ($in['tanggal_surat'] ?? '')), $thn - 1, $thn + 2);
        if ($tgl === null) {
            $galat[] = 'Tanggal surat belum diisi atau tidak sah.';
        }
        $cakupan = (string) ($in['cakupan'] ?? '');
        if (! in_array($cakupan, self::CAKUPAN, true)) {
            $galat[] = 'Pilih cakupan surat: per kelas, per ajuan PKL, atau siswa pilihan.';
            $cakupan = 'kelas';
        }
        $pilihan = array_values(array_unique(array_filter(array_map('intval', (array) ($in[$cakupan] ?? [])), static fn (int $x) => $x > 0)));
        $maks    = $cakupan === 'siswa' ? SuratSekolah::MAKS_SISWA : self::MAKS_PILIHAN;
        if ($pilihan === []) {
            $galat[] = ['kelas' => 'Pilih minimal satu kelas.', 'ajuan' => 'Pilih minimal satu ajuan PKL.', 'siswa' => 'Pilih minimal satu siswa.'][$cakupan];
        } elseif (count($pilihan) > $maks) {
            $galat[] = 'Terlalu banyak sekaligus (maksimal ' . $maks . ').';
        }

        return [
            'ok' => $galat === [], 'galat' => $galat, 'tanggal_surat' => (string) $tgl, 'cakupan' => $cakupan,
            'isi_ortu' => (string) ($in['isi_ortu'] ?? '1') !== '0', 'pilihan' => $pilihan,
        ];
    }

    /**
     * Buat surat dari masukan yang SUDAH lolos periksa(): per kelas / per ajuan = satu surat per pilihan; siswa pilihan =
     * satu surat. Pilihan yang sama persis (kunci sama, belum dibatalkan) dilewati dan dilaporkan — unduh ulang surat lama.
     *
     * @param array{tanggal_surat: string, cakupan: string, isi_ortu: bool, pilihan: list<int>} $h
     * @param array<string, mixed>                                                               $konteks
     *
     * @return array{ok: bool, dibuat: list<int>, sama: list<string>, gagal: list<string>, pesan: string}
     */
    public function buat(array $h, array $konteks, ?array $p = null): array
    {
        $svc   = new SuratSekolah($this->db);
        $tahun = substr($h['tanggal_surat'], 0, 4);
        $ortu  = $h['isi_ortu'] ? 1 : 0;
        $rencana = []; // [judul, isi, siswa, perusahaan, pengajuan_id, nama tampil]
        $gagal   = [];

        if ($h['cakupan'] === 'kelas') {
            $kelas = [];
            foreach ($this->db->table('kelas')->select('id, nama_kelas')->whereIn('id', $h['pilihan'])->where('deleted_at', null)->get()->getResultArray() as $k) {
                $kelas[(int) $k['id']] = (string) $k['nama_kelas'];
            }
            $perKelas = [];
            foreach ($this->siswaKelas(array_keys($kelas)) as $s) {
                $perKelas[$s['kelas_id']][] = $s;
            }
            $hp = $this->hpPkl(array_merge([], ...array_map(static fn (array $l) => array_column($l, 'id'), array_values($perKelas))));
            foreach ($h['pilihan'] as $kid) {
                if (! isset($kelas[$kid]) || empty($perKelas[$kid])) {
                    $gagal[] = (isset($kelas[$kid]) ? 'Kelas ' . $kelas[$kid] : 'Kelas pilihan') . ' — tidak ada siswa aktif.';
                    continue;
                }
                $daftar    = $perKelas[$kid];
                $rencana[] = [
                    'Kelas ' . $kelas[$kid] . ' (' . count($daftar) . ' siswa)',
                    ['cakupan' => 'kelas', 'kelas' => $kelas[$kid], 'isi_ortu' => (bool) $ortu, 'kunci' => sha1('pernyataan|kelas|' . $kid . '|' . $tahun . '|' . $ortu)],
                    array_map(static fn (array $s) => ['siswa_id' => $s['id'], 'kelas_id' => $kid, 'hp' => $hp[$s['id']] ?? null], $daftar),
                    '', null, 'Kelas ' . $kelas[$kid],
                ];
            }
        } elseif ($h['cakupan'] === 'ajuan') {
            $peta = [];
            foreach ($this->ajuan($h['pilihan']) as $a) {
                $peta[$a['id']] = $a;
            }
            foreach ($h['pilihan'] as $aid) {
                $a = $peta[$aid] ?? null;
                if ($a === null) {
                    $gagal[] = 'Ajuan ' . PklPengajuanModel::kode($aid) . ' tidak ditemukan, sudah ditolak, atau anggotanya tidak aktif lagi.';
                    continue;
                }
                $rencana[] = [
                    $a['perusahaan'] . ' (' . count($a['siswa']) . ' siswa)',
                    ['cakupan' => 'ajuan', 'isi_ortu' => (bool) $ortu, 'kunci' => sha1('pernyataan|ajuan|' . $aid . '|' . $ortu)],
                    array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $a['siswa']),
                    $a['perusahaan'], $aid, $a['perusahaan'] . ' (' . $a['kode'] . ')',
                ];
            }
        } else {
            $siswa = $this->db->table('siswa s')->select('s.id, s.nama, s.kelas_id, k.nama_kelas')
                ->join('kelas k', 'k.id = s.kelas_id', 'left')
                ->whereIn('s.id', $h['pilihan'])->where('s.deleted_at', null)->where('s.status', 'aktif')->get()->getResultArray();
            usort($siswa, static fn (array $x, array $y) => KelasModel::bandingNatural((string) ($x['nama_kelas'] ?? ''), (string) ($y['nama_kelas'] ?? '')) ?: strcasecmp((string) $x['nama'], (string) $y['nama']));
            if (count($siswa) < count($h['pilihan'])) {
                $gagal[] = (count($h['pilihan']) - count($siswa)) . ' siswa pilihan tidak ditemukan atau tidak aktif lagi (dilewati).';
            }
            if ($siswa !== []) {
                $ids = array_map(static fn (array $s) => (int) $s['id'], $siswa);
                $hp  = $this->hpPkl($ids);
                sort($ids);
                $judul     = (string) $siswa[0]['nama'] . (count($siswa) > 1 ? ' dan ' . (count($siswa) - 1) . ' siswa lain' : '');
                $rencana[] = [
                    $judul,
                    ['cakupan' => 'siswa', 'isi_ortu' => (bool) $ortu, 'kunci' => sha1('pernyataan|siswa|' . implode(',', $ids) . '|' . $tahun . '|' . $ortu)],
                    array_map(static fn (array $s) => ['siswa_id' => (int) $s['id'], 'kelas_id' => ((int) $s['kelas_id']) ?: null, 'hp' => $hp[(int) $s['id']] ?? null], $siswa),
                    '', null, $judul,
                ];
            }
        }

        $dibuat = [];
        $sama   = [];
        foreach ($rencana as [$judul, $isi, $daftar, $perusahaan, $ajuanId, $tampil]) {
            if (($ada = $svc->adaKunci($isi['kunci'])) !== null) {
                $sama[] = $tampil . ' — sudah ada (' . SuratSekolahModel::kode($ada['id']) . '); buka surat itu untuk mengunduh ulang.';
                continue;
            }
            $r = $svc->buat(SuratJenis::PERNYATAAN, [
                'judul' => mb_substr($judul, 0, 190), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi, 'siswa' => $daftar,
                'perusahaan_nama' => $perusahaan, 'pengajuan_id' => $ajuanId,
            ], $konteks, $p);
            if ($r['ok']) {
                $dibuat[] = (int) $r['id'];
            } else {
                $gagal[] = $tampil . ' — ' . (string) ($r['pesan'] ?? 'gagal disimpan.');
            }
        }

        $pesan = [];
        if ($dibuat !== []) {
            $pesan[] = count($dibuat) . ' surat dibuat.';
        }
        if ($sama !== []) {
            $pesan[] = count($sama) . ' dilewati karena sudah ada.';
        }
        if ($gagal !== []) {
            $pesan[] = count($gagal) . ' tidak bisa dibuat.';
        }

        return ['ok' => $dibuat !== [], 'dibuat' => $dibuat, 'sama' => $sama, 'gagal' => $gagal, 'pesan' => implode(' ', $pesan)];
    }

    /**
     * HP yang diisi siswa di ajuan PKL-nya yang masih aktif (lebih baru/lengkap daripada HP di Master Siswa).
     *
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    private function hpPkl(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('pkl_anggota')->select('siswa_aktif, hp')->whereIn('siswa_aktif', $ids)->get()->getResultArray() as $r) {
            if (trim((string) ($r['hp'] ?? '')) !== '') {
                $out[(int) $r['siswa_aktif']] = (string) $r['hp'];
            }
        }

        return $out;
    }
}
