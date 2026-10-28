<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat Permohonan Perpanjangan PKL — surat bernomor ke SATU tempat PKL untuk siswa yang PKL-nya diperpanjang (permintaan user
 * 2026-10-26; rancangan + aturan R1–R12: docs/DESAIN-PERPANJANGAN-PKL.md). Satu-satunya tempat aturan perpanjangan, dipakai web
 * staf, Layanan Siswa, dan API.
 *
 *   - Syarat (R1): ajuan PKL disetujui, siswa anggota aktif ajuan disetujui di PERUSAHAAN yang sama (2026-10-28: teman boleh dari
 *     ajuan lain, bagian 13), tidak punya Surat Penarikan yang belum dibatalkan.
 *   - Maksimal SATU KALI per siswa per ajuan (R5, keputusan user): ditolak bila sudah ada surat perpanjangan siswa itu yang belum
 *     dibatalkan. Dibatalkan = boleh minta lagi.
 *   - Tanggal selesai semula: dari ajuan yang dipilih (Surat Balasan / laporan siswa) bila tercatat; kosong → diketik siswa/staf
 *     (ditandai). Satu surat = SATU tanggal semula: siswa yang ajuannya mencatat tanggal selesai lain tidak bisa ikut (R13).
 *   - Satu surat per perusahaan + tanggal semula + tanggal baru (R6): penyusul bertanggal sama ikut surat itu selama belum
 *     bernomor/disetujui (dicari langsung di pkl_perpanjangan, jadi surat buatan aturan lama ikut terbaca).
 *   - Tanggal baru BERLAKU hanya bila suratnya disetujui (R8) — dihitung lewat JOIN (selesaiEfektif), tanpa menyalin ke tabel lain,
 *     jadi ACC / batal ACC / batal surat otomatis benar. pkl_pengajuan.tanggal_selesai TIDAK ditimpa (R9).
 *
 * Isi surat dibekukan di surat_sekolah.isi (sapaan, selesai_lama, sumber_lama, selesai_baru, alasan_perpanjangan) + tabel
 * pkl_perpanjangan (satu baris per surat; pengajuan_id = ajuan yang dipilih saat membuat; tanggal bertipe DATE untuk dicari).
 * Siswa surat = surat_sekolah_siswa, dengan pengajuan_id = ajuan asal tiap siswa (migrasi 2026-10-28; kosong = ajuan surat).
 */
final class SuratPerpanjangan
{
    /** Alasan saran: kalimat sesudah "… karena {alasan}." => label pendek tombol. */
    public const ALASAN = [
        ['adanya permintaan dari pihak perusahaan/instansi tempat PKL', 'Permintaan perusahaan'],
        ['peserta didik belum menyelesaikan target kegiatan PKL yang ditetapkan', 'Target PKL belum selesai'],
        ['adanya penyesuaian jadwal kegiatan di tempat PKL', 'Jadwal di tempat PKL berubah'],
        ['permintaan peserta didik dan orang tua/wali untuk menambah pengalaman kerja', 'Permintaan siswa & orang tua'],
    ];

    public const MAKS_HARI   = 183; // lama perpanjangan paling panjang (±6 bulan)
    public const MAKS_ALASAN = 200;
    public const SUMBER      = ['sekolah', 'siswa', 'staf'];

    /** Bawaan setelan form siswa bila kolom belum dimigrasi. */
    public const BAWAAN_SEBELUM = 7;
    public const BAWAAN_SESUDAH = 30;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Setelan & teks (murni)
    // =================================================================

    /**
     * @param array<string, mixed> $p baris pkl_pengaturan
     *
     * @return array{buka: bool, sebelum: int, sesudah: int}
     */
    public static function setelan(array $p): array
    {
        return [
            'buka'    => (int) ($p['perpanjang_buka'] ?? 1) === 1,
            'sebelum' => max(0, (int) ($p['perpanjang_hari_sebelum'] ?? self::BAWAAN_SEBELUM)),
            'sesudah' => max(0, (int) ($p['perpanjang_hari_sesudah'] ?? self::BAWAAN_SESUDAH)),
        ];
    }

    private static function tgl(string $ymd): ?\DateTimeImmutable
    {
        $t = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $t !== false && $t->format('Y-m-d') === $ymd ? $t : null;
    }

    /** Selisih hari b − a (bisa negatif); tanggal tak sah → null. */
    public static function selisih(string $a, string $b): ?int
    {
        $x = self::tgl($a);
        $y = self::tgl($b);

        return $x === null || $y === null ? null : (int) $x->diff($y)->format('%r%a');
    }

    public static function tambahHari(string $ymd, int $n): string
    {
        $t = self::tgl($ymd);

        return $t === null ? '' : $t->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
    }

    /** Perpanjangan mulai sehari sesudah tanggal selesai semula. */
    public static function mulai(string $selesaiLama): string
    {
        return self::tambahHari($selesaiLama, 1);
    }

    /**
     * Lama perpanjangan untuk kalimat surat: "1 (satu) bulan" bila selisihnya genap bulan (toleransi 3 hari, rumus hari ÷ 30,4375
     * seperti Surat Balasan), selain itu "14 (empat belas) hari". Tanggal tak sah / tidak maju → "".
     */
    public static function lamaTeks(string $selesaiLama, string $selesaiBaru): string
    {
        $hari = self::selisih($selesaiLama, $selesaiBaru);
        if ($hari === null || $hari <= 0) {
            return '';
        }
        $bulan = (int) round($hari / 30.4375);
        if ($bulan >= 1 && abs($hari - $bulan * 30.4375) <= 3) {
            return $bulan . ' (' . HonorCetak::terbilang($bulan) . ') bulan';
        }

        return $hari . ' (' . HonorCetak::terbilang($hari) . ') hari';
    }

    /**
     * Jendela form SISWA (R2): buka H-{sebelum} sebelum tanggal selesai semula, tutup H+{sesudah} sesudahnya (0 = tanpa batas).
     * Staf tidak dibatasi jendela.
     *
     * @param array<string, mixed> $p
     *
     * @return array{status: string, buka_mulai: ?string, tutup: ?string}  status: buka | dini | lewat
     */
    public static function jendela(string $selesaiLama, array $p, ?string $hariIni = null): array
    {
        $s       = self::setelan($p);
        $hariIni ??= date('Y-m-d');
        $mulai   = $s['sebelum'] > 0 ? self::tambahHari($selesaiLama, -$s['sebelum']) : null;
        $tutup   = $s['sesudah'] > 0 ? self::tambahHari($selesaiLama, $s['sesudah']) : null;
        $status  = 'buka';
        if ($mulai !== null && $hariIni < $mulai) {
            $status = 'dini';
        } elseif ($tutup !== null && $hariIni > $tutup) {
            $status = 'lewat';
        }

        return ['status' => $status, 'buka_mulai' => $mulai, 'tutup' => $tutup];
    }

    /**
     * Alasan: pilihan saran (alasan_pilih = indeks ALASAN) atau ketikan sendiri (alasan_lain), dirapikan supaya pas di kalimat
     * "… karena {alasan}." (pola SuratPkl::alasan).
     *
     * @param array<string, mixed> $in
     */
    public static function alasan(array $in): string
    {
        $pilih = (string) ($in['alasan_pilih'] ?? '');
        if ($pilih !== '' && ctype_digit($pilih) && isset(self::ALASAN[(int) $pilih])) {
            return self::ALASAN[(int) $pilih][0];
        }

        return SuratPkl::alasan(['alasan_lain' => (string) ($in['alasan_lain'] ?? '')]);
    }

    /** Indeks saran untuk teks alasan tersimpan ('lain' bila ketikan sendiri). */
    public static function indeksAlasan(string $alasan): string
    {
        foreach (self::ALASAN as $i => [$kalimat]) {
            if ($kalimat === $alasan) {
                return (string) $i;
            }
        }

        return 'lain';
    }

    /**
     * SQL ajuan asal siswa di surat perpanjangan: kolom per siswa (migrasi 2026-10-28) bila ada, selain itu ajuan suratnya.
     * $ss = alias surat_sekolah_siswa, $pp = alias pkl_perpanjangan.
     */
    public static function sqlAjuanSiswa(string $ss = 'ss', string $pp = 'pp'): string
    {
        return SuratSekolah::kolomAjuanSiswa() ? 'COALESCE(' . $ss . '.pengajuan_id, ' . $pp . '.pengajuan_id)' : $pp . '.pengajuan_id';
    }

    /**
     * Tanggal selesai yang tercatat di ajuan siswa ini bila BERBEDA dari tanggal semula surat $lama (R13: satu surat = satu tanggal
     * semula), selain itu null. Ajuan yang belum mencatat tanggal selesai ikut tanggal surat.
     *
     * @param array<string, mixed> $siswa butir grup() (selesai_ajuan)
     */
    public static function bedaSelesai(array $siswa, ?string $lama): ?string
    {
        $t = (string) ($siswa['selesai_ajuan'] ?? '');

        return $t !== '' && (string) $lama !== '' && $t !== $lama ? $t : null;
    }

    /** @param list<string> $nama */
    public static function judul(string $perusahaan, array $nama): string
    {
        return mb_substr($perusahaan . ' (' . count($nama) . ' siswa)', 0, 190);
    }

    /**
     * Periksa isian surat (tanpa data ajuan). selesai_lama hanya diperiksa bila diisi; aturan terhadap ajuan di buat()/ubahSurat().
     *
     * @param array<string, mixed> $in tanggal_surat, sapaan, selesai_lama?, selesai_baru, alasan_pilih, alasan_lain
     *
     * @return array{ok: bool, galat: array<string, string>, tanggal_surat: string, isi: array<string, mixed>}
     */
    public static function periksa(array $in): array
    {
        $galat = [];
        $thn   = (int) date('Y');
        $surat = IsianBantu::tanggal(trim((string) ($in['tanggal_surat'] ?? '')), $thn - 1, $thn + 2);
        if ($surat === null) {
            $galat['tanggal_surat'] = 'Tanggal surat belum diisi atau tidak sah.';
        }
        $sapaan = SuratPkl::sapaan((string) ($in['sapaan'] ?? ''));
        if (mb_strlen($sapaan) > 60) {
            $galat['sapaan'] = 'Sapaan terlalu panjang (maksimal 60 huruf).';
        }
        $lamaIn = trim((string) ($in['selesai_lama'] ?? ''));
        $lama   = $lamaIn !== '' ? IsianBantu::tanggal($lamaIn, $thn - 1, $thn + 1) : null;
        if ($lamaIn !== '' && $lama === null) {
            $galat['selesai_lama'] = 'Tanggal selesai PKL semula tidak sah.';
        }
        $baru = IsianBantu::tanggal(trim((string) ($in['selesai_baru'] ?? '')), $thn - 1, $thn + 2);
        if ($baru === null) {
            $galat['selesai_baru'] = 'Isi tanggal PKL diperpanjang sampai.';
        }
        $alasan = self::alasan($in);
        if ($alasan === '') {
            $galat['alasan'] = 'Pilih atau tulis alasan perpanjangan.';
        } elseif (mb_strlen($alasan) > self::MAKS_ALASAN) {
            $galat['alasan'] = 'Alasan terlalu panjang (maksimal ' . self::MAKS_ALASAN . ' huruf).';
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'tanggal_surat' => (string) $surat,
            'isi' => ['sapaan' => $sapaan, 'selesai_lama' => (string) $lama, 'selesai_baru' => (string) $baru, 'alasan_perpanjangan' => $alasan]];
    }

    /**
     * Aturan tanggal terhadap ajuan (R3, R4). galat = memblokir; peringatan = kuning (tidak memblokir).
     *
     * @param array<string, mixed> $p
     *
     * @return array{galat: array<string, string>, peringatan: list<string>}
     */
    public static function aturanTanggal(?string $mulaiAjuan, string $lama, string $sumber, string $baru, array $p): array
    {
        $galat = [];
        $warn  = [];
        if ($lama === '') {
            $galat['selesai_lama'] = 'Tanggal selesai PKL semula belum tercatat — isi tanggal selesai PKL yang sekarang.';
        } elseif ($mulaiAjuan !== null && $mulaiAjuan !== '' && $lama < $mulaiAjuan) {
            $galat['selesai_lama'] = 'Tanggal selesai semula lebih awal dari tanggal mulai PKL (' . IsianBantu::tanggalIndo($mulaiAjuan) . ').';
        }
        if ($lama !== '' && $baru !== '') {
            $hari = self::selisih($lama, $baru);
            if ($hari === null || $hari <= 0) {
                $galat['selesai_baru'] = 'Tanggal perpanjangan harus sesudah tanggal selesai semula (' . IsianBantu::tanggalIndo($lama) . ').';
            } elseif ($hari > self::MAKS_HARI) {
                $galat['selesai_baru'] = 'Perpanjangan ' . $hari . ' hari terlalu lama (paling lama ' . self::MAKS_HARI . ' hari) — periksa bulan/tahunnya.';
            }
        }
        if ($galat === []) {
            if ($sumber === 'siswa') {
                $warn[] = 'Tanggal selesai semula (' . IsianBantu::tanggalIndo($lama) . ') diketik siswa karena belum ada Surat Balasan — cocokkan dengan perusahaan sebelum ACC.';
            }
            $akhir = (string) ($p['selesai_paling_akhir'] ?? '');
            if ($akhir !== '' && $baru > $akhir) {
                $warn[] = 'Tanggal perpanjangan (' . IsianBantu::tanggalIndo($baru) . ') melewati batas selesai PKL sekolah ' . IsianBantu::tanggalIndo($akhir) . '.';
            }
            $maks = (int) ($p['durasi_maks_hari'] ?? 0);
            if ($maks > 0 && $mulaiAjuan !== null && $mulaiAjuan !== '' && ($total = IsianBantu::hariInklusif($mulaiAjuan, $baru)) > $maks) {
                $warn[] = 'Total PKL menjadi ' . $total . ' hari, lebih dari maksimal ' . $maks . ' hari di Pengaturan PKL.';
            }
        }

        return ['galat' => $galat, 'peringatan' => $warn];
    }

    /**
     * Penanda khusus template perpanjangan_pkl.docx (menimpa penanda umum yang sama namanya).
     *
     * @param array<string, mixed> $surat
     * @param array<string, mixed> $isi
     *
     * @return array<string, string>
     */
    public static function token(array $surat, array $isi): array
    {
        $lama = (string) ($isi['selesai_lama'] ?? '');
        $baru = (string) ($isi['selesai_baru'] ?? '');
        $v    = [
            'sapaan'               => trim((string) ($isi['sapaan'] ?? '')) !== '' ? (string) $isi['sapaan'] : SuratPkl::SAPAAN_BAWAAN,
            'tanggal_surat'        => IsianBantu::tanggalIndo((string) ($surat['tanggal_surat'] ?? '')),
            'selesai_lama'         => IsianBantu::tanggalIndo($lama),
            'mulai_perpanjangan'   => IsianBantu::tanggalIndo(self::mulai($lama)),
            'selesai_baru'         => IsianBantu::tanggalIndo($baru),
            'lama_perpanjangan'    => self::lamaTeks($lama, $baru),
            'periode_perpanjangan' => SuratPkl::periodeTeks(self::mulai($lama), $baru),
            'alasan'               => (string) ($isi['alasan_perpanjangan'] ?? ''),
        ];
        if (array_key_exists('alamat', $isi)) {
            $v['alamat_lengkap'] = (string) $isi['alamat']; // alamat dibekukan saat surat dibuat (seperti Surat Balasan)
        }

        return $v;
    }

    // =================================================================
    // Membaca
    // =================================================================

    /**
     * Perpanjangan yang BELUM dibatalkan untuk siswa-siswa ini (opsional: hanya bila ajuan asal siswanya = $ajuan / salah satu
     * $ajuan), satu per siswa per ajuan. ajuan_id = ajuan asal SISWA itu (bukan ajuan yang dipilih saat surat dibuat).
     *
     * @param list<int>           $siswaIds
     * @param int|list<int>|null  $ajuan
     *
     * @return array<int, array{surat_id: int, kode: string, status: string, nomor: ?string, ajuan_id: int, selesai_lama: string, sumber_lama: string, selesai_baru: string, berlaku: bool}>
     *         siswa_id => perpanjangan (bila siswa punya di beberapa ajuan, yang terbaru)
     */
    public function aktif(array $siswaIds, int|array|null $ajuan = null): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x) => $x > 0)));
        if ($siswaIds === [] || ! self::tabelAda()) {
            return [];
        }
        $kol = self::sqlAjuanSiswa('ss', 'pp');
        $b   = $this->db->table('pkl_perpanjangan pp')
            ->select('ss.siswa_id, s.id AS surat_id, s.status, s.nomor, ' . $kol . ' AS pengajuan_id, pp.selesai_lama, pp.sumber_lama, pp.selesai_baru', false)
            ->join('surat_sekolah s', 's.id = pp.surat_id')
            ->join('surat_sekolah_siswa ss', 'ss.surat_id = s.id')
            ->where('s.jenis', SuratJenis::PERPANJANGAN)->where('s.status !=', 'dibatalkan')
            ->whereIn('ss.siswa_id', $siswaIds);
        if ($ajuan !== null) {
            $b->where($kol . ' IN (' . implode(', ', array_map('intval', (array) $ajuan ?: [0])) . ')', null, false);
        }
        $out = [];
        foreach ($b->orderBy('s.id', 'ASC')->get()->getResultArray() as $r) {
            $out[(int) $r['siswa_id']] = [
                'surat_id' => (int) $r['surat_id'], 'kode' => SuratSekolahModel::kode((int) $r['surat_id']), 'status' => (string) $r['status'],
                'nomor' => $r['nomor'] ?? null, 'ajuan_id' => (int) $r['pengajuan_id'], 'selesai_lama' => (string) $r['selesai_lama'],
                'sumber_lama' => (string) $r['sumber_lama'], 'selesai_baru' => (string) $r['selesai_baru'], 'berlaku' => $r['status'] === 'disetujui',
            ];
        }

        return $out;
    }

    /**
     * Tanggal selesai PKL EFEKTIF (R8) siswa-siswa ini pada ajuan disetujui tempat mereka aktif: tanggal baru bila surat
     * perpanjangannya sudah disetujui, selain itu tanggal di ajuan (bisa null). Satu sumber untuk detail ajuan, Status Siswa,
     * Layanan Siswa, API, dan (nanti) sertifikat.
     *
     * @param list<int> $siswaIds
     *
     * @return array<int, array{ajuan_id: int, selesai_ajuan: ?string, selesai: ?string, diperpanjang: bool, menunggu: bool, perpanjangan: ?array}>
     */
    public function selesaiEfektif(array $siswaIds): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x) => $x > 0)));
        if ($siswaIds === []) {
            return [];
        }
        $rows = $this->db->table('pkl_anggota a')->select('a.siswa_id, a.pengajuan_id, p.tanggal_selesai')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->where('p.status', 'disetujui')->whereIn('a.siswa_id', $siswaIds)
            ->orderBy('a.pengajuan_id', 'ASC')->get()->getResultArray();
        $pj  = $this->aktif($siswaIds);
        $out = [];
        foreach ($rows as $r) {
            $sid  = (int) $r['siswa_id'];
            $aid  = (int) $r['pengajuan_id'];
            $x    = isset($pj[$sid]) && $pj[$sid]['ajuan_id'] === $aid ? $pj[$sid] : null;
            $awal = ($r['tanggal_selesai'] ?? '') !== '' ? (string) $r['tanggal_selesai'] : null;
            $out[$sid] = [
                'ajuan_id' => $aid, 'selesai_ajuan' => $awal,
                'selesai' => $x !== null && $x['berlaku'] ? $x['selesai_baru'] : $awal,
                'diperpanjang' => $x !== null && $x['berlaku'], 'menunggu' => $x !== null && ! $x['berlaku'], 'perpanjangan' => $x,
            ];
        }

        return $out;
    }

    /**
     * Siswa dengan Surat Penarikan yang belum dibatalkan di ajuan ini / salah satu ajuan ini (R1).
     *
     * @param int|list<int> $ajuan
     * @param list<int>     $siswaIds
     *
     * @return array<int, string> siswa_id => kode surat penarikan
     */
    public function ditarik(int|array $ajuan, array $siswaIds): array
    {
        if ($siswaIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('surat_sekolah s')->select('ss.siswa_id, s.id')
            ->join('surat_sekolah_siswa ss', 'ss.surat_id = s.id')
            ->where('s.jenis', SuratJenis::PENARIKAN)->where('s.status !=', 'dibatalkan')->whereIn('s.pengajuan_id', array_map('intval', (array) $ajuan ?: [0]))
            ->whereIn('ss.siswa_id', $siswaIds)->get()->getResultArray() as $r) {
            $out[(int) $r['siswa_id']] = SuratSekolahModel::kode((int) $r['id']);
        }

        return $out;
    }

    /**
     * Ajuan $ajuanId + ajuan DISETUJUI lain di perusahaan yang sama ($ajuanId lebih dulu, lalu urut id). Kunci perusahaan sama dengan
     * Surat Izin Ujian/TKA (SuratAcara::perusahaan): master perusahaan, atau nama ternormalisasi bila belum tertaut. Server yang belum
     * menjalankan migrasi 2026-10-28 → hanya [$ajuanId] (aturan lama: teman satu ajuan).
     *
     * @return list<int>
     */
    public function ajuanSeperusahaan(int $ajuanId): array
    {
        $r = $this->db->table('pkl_pengajuan')->select('perusahaan_id, perusahaan_norm')->where('id', $ajuanId)->get()->getRowArray();
        if ($r === null || ! SuratSekolah::kolomAjuanSiswa() || (empty($r['perusahaan_id']) && (string) ($r['perusahaan_norm'] ?? '') === '')) {
            return [$ajuanId];
        }
        $b = $this->db->table('pkl_pengajuan')->select('id')->where('status', 'disetujui')->where('id !=', $ajuanId);
        if (! empty($r['perusahaan_id'])) {
            $b->where('perusahaan_id', (int) $r['perusahaan_id']);
        } else {
            $b->where('perusahaan_id', null)->where('perusahaan_norm', (string) $r['perusahaan_norm']);
        }

        return array_merge([$ajuanId], array_map('intval', array_column($b->orderBy('id', 'ASC')->get()->getResultArray(), 'id')));
    }

    /**
     * Ajuan disetujui $ajuanId (SuratPkl::ajuan) dengan siswa = SEMUA siswa aktif yang PKL di perusahaan yang sama: siswa ajuan ini
     * dulu, lalu ajuan lain (urut id). Tiap siswa + ajuan_id, ajuan_kode, selesai_ajuan (tanggal selesai tercatat di ajuannya),
     * ajuan_ini. ajuan_ids = ajuan yang ikut (ajuan ini pertama). null bila ajuan tidak disetujui / tanpa siswa aktif.
     *
     * @return array<string, mixed>|null
     */
    private function grup(int $ajuanId): ?array
    {
        $semua = array_column((new SuratPkl($this->db))->ajuan($this->ajuanSeperusahaan($ajuanId)), null, 'id');
        $a     = $semua[$ajuanId] ?? null;
        if ($a === null) {
            return null;
        }
        unset($semua[$ajuanId]);
        ksort($semua);
        $siswa = [];
        foreach (array_merge([$a], array_values($semua)) as $x) {
            foreach ($x['siswa'] as $s) {
                $siswa[$s['siswa_id']] ??= $s + ['ajuan_id' => $x['id'], 'ajuan_kode' => $x['kode'], 'selesai_ajuan' => $x['selesai'], 'ajuan_ini' => $x['id'] === $ajuanId];
            }
        }
        $a['siswa']     = array_values($siswa);
        $a['ajuan_ids'] = array_merge([$ajuanId], array_map('intval', array_keys($semua)));

        return $a;
    }

    /**
     * Satu ajuan disetujui untuk formulir: data ajuan (SuratPkl::ajuan) + tanggal selesai semula & asalnya + SEMUA siswa yang PKL di
     * perusahaan yang sama (grup(); 2026-10-28) dengan status tiap siswa (bisa dipilih? sudah diperpanjang / ditarik / tanggal
     * selesai tercatatnya beda). jml_ajuan = banyak ajuan yang ikut. null bila ajuan tidak disetujui / tanpa siswa aktif.
     *
     * @return array<string, mixed>|null
     */
    public function ajuanForm(int $ajuanId): ?array
    {
        $a = $this->grup($ajuanId);
        if ($a === null) {
            return null;
        }
        $ids    = array_column($a['siswa'], 'siswa_id');
        $pj     = $this->aktif($ids, $a['ajuan_ids']);
        $tarik  = $this->ditarik($a['ajuan_ids'], $ids);
        $bisa   = 0;
        foreach ($a['siswa'] as &$s) {
            $x            = $pj[$s['siswa_id']] ?? null;
            $s['pj']      = $x !== null ? ['kode' => $x['kode'], 'surat_id' => $x['surat_id'], 'status' => $x['status'], 'selesai_baru' => $x['selesai_baru'], 'berlaku' => $x['berlaku']] : null;
            $s['ditarik'] = $tarik[$s['siswa_id']] ?? null;
            $s['beda']    = self::bedaSelesai($s, $a['selesai']);
            $s['bisa']    = $x === null && $s['ditarik'] === null && $s['beda'] === null;
            $bisa        += $s['bisa'] ? 1 : 0;
        }
        unset($s);
        $a['selesai_lama'] = $a['selesai'];
        $a['sumber_lama']  = $a['selesai'] !== null ? 'sekolah' : null;
        $a['bisa']         = $bisa;
        $a['jml_ajuan']    = count($a['ajuan_ids']);

        return $a;
    }

    /**
     * Cari ajuan disetujui (perusahaan, kode PKL-xxxxx, atau nama siswa aktif) untuk kotak "cari lalu pilih". SATU hasil per
     * perusahaan (formulirnya sudah memuat semua siswa di sana; ajuan = yang cocok paling baru). Maks $maks, terbaru dulu. Kata
     * kunci < 2 huruf → [].
     *
     * @return list<array<string, mixed>> butir ajuanForm()
     */
    public function cari(string $q, int $maks = 10): array
    {
        $q = IsianBantu::rapikan($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $b = $this->db->table('pkl_pengajuan p')->select('p.id, p.perusahaan_id, p.perusahaan_norm')->distinct()
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.status', 'disetujui')->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->groupStart()->like('p.perusahaan_nama', $q)->orLike('m.nama', $q)->orLike('s.nama', $q);
        if (preg_match('~^(pkl-?)?0*(\d{1,9})$~i', $q, $m) === 1) {
            $b->orWhere('p.id', (int) $m[2]);
        }
        $lintas = SuratSekolah::kolomAjuanSiswa();
        $lihat  = [];
        $out    = [];
        foreach ($b->groupEnd()->orderBy('p.id', 'DESC')->get($maks * 5)->getResultArray() as $r) {
            $k = ! $lintas ? 'a:' . $r['id'] : (! empty($r['perusahaan_id']) ? 'm:' . (int) $r['perusahaan_id'] : 'n:' . $r['perusahaan_norm']);
            if (isset($lihat[$k])) {
                continue;
            }
            $lihat[$k] = true;
            if (($f = $this->ajuanForm((int) $r['id'])) !== null) {
                $out[] = $f;
            }
            if (count($out) >= $maks) {
                break;
            }
        }

        return $out;
    }

    /**
     * Siswa yang PKL-nya berakhir dalam $hari hari ke depan (tanggal di ajuan) dan BELUM punya perpanjangan / penarikan — bahan
     * kartu Beranda PKL & daftar cepat di formulir. Hanya ajuan yang tanggal selesainya tercatat.
     *
     * @return list<array{siswa_id: int, nama: string, kelas: string, ajuan_id: int, kode: string, perusahaan: string, selesai: string, sisa: int}>
     */
    public function berakhir(int $hari = 7, ?string $hariIni = null): array
    {
        $hariIni ??= date('Y-m-d');
        $sampai   = self::tambahHari($hariIni, $hari);
        $pj       = self::tabelAda()
            ? 'NOT EXISTS (SELECT 1 FROM pkl_perpanjangan pp JOIN surat_sekolah x ON x.id = pp.surat_id AND x.status <> \'dibatalkan\''
                . ' JOIN surat_sekolah_siswa xs ON xs.surat_id = x.id WHERE ' . self::sqlAjuanSiswa('xs', 'pp') . ' = a.pengajuan_id AND xs.siswa_id = a.siswa_id)'
            : '1 = 1';
        $tarik = 'NOT EXISTS (SELECT 1 FROM surat_sekolah t JOIN surat_sekolah_siswa ts ON ts.surat_id = t.id WHERE t.jenis = \'' . SuratJenis::PENARIKAN
            . '\' AND t.status <> \'dibatalkan\' AND t.pengajuan_id = a.pengajuan_id AND ts.siswa_id = a.siswa_id)';
        $rows = $this->db->table('pkl_anggota a')
            ->select('a.siswa_id, a.pengajuan_id, p.tanggal_selesai, p.perusahaan_nama, m.nama AS master_nama, s.nama, k.nama_kelas')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.status', 'disetujui')->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->where('p.tanggal_selesai >=', $hariIni)->where('p.tanggal_selesai <=', $sampai)
            ->where($pj, null, false)->where($tarik, null, false)
            ->orderBy('p.tanggal_selesai', 'ASC')->orderBy('p.id', 'ASC')->orderBy('a.peran', 'ASC')->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'siswa_id' => (int) $r['siswa_id'], 'nama' => (string) $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''),
            'ajuan_id' => (int) $r['pengajuan_id'], 'kode' => PklPengajuanModel::kode((int) $r['pengajuan_id']),
            'perusahaan' => (string) ($r['master_nama'] ?? '') !== '' ? (string) $r['master_nama'] : (string) $r['perusahaan_nama'],
            'selesai' => (string) $r['tanggal_selesai'], 'sisa' => (int) self::selisih($hariIni, (string) $r['tanggal_selesai']),
        ], $rows);
    }

    // =================================================================
    // Membuat & mengubah
    // =================================================================

    /**
     * Buat surat perpanjangan (atau ikut surat yang sama, R6) untuk siswa pilihan yang PKL di perusahaan ajuan $in['ajuan_id']
     * (siswa ajuan itu dan/atau ajuan disetujui lain di perusahaan yang sama, 2026-10-28). Semua pemeriksaan & penulisan dalam SATU
     * transaksi dengan kunci baris anggota seluruh perusahaan (dua klik / dua teman bersamaan tidak menghasilkan perpanjangan ganda).
     *
     * @param array<string, mixed> $in      ajuan_id (ajuan yang dipilih: sumber tanggal semula, alamat, mulai PKL), siswa[] (id),
     *                                      tanggal_surat, sapaan, selesai_lama (bila belum tercatat), selesai_baru, alasan_pilih, alasan_lain
     * @param array<string, mixed> $konteks pelaku (SuratSekolah)
     * @param array{sumber?: string, jendela?: bool, hariIni?: string} $opsi sumber = staf|siswa (asal surat & asal tanggal semula yang
     *                                      diketik); jendela = terapkan jendela form siswa (R2)
     *
     * @return array{ok: bool, kode: string, pesan: string, galat?: array<string, string>, surat_id?: int, peringatan?: list<string>}
     *         kode: baru | ikut | tolak | gagal
     */
    public function buat(array $in, array $konteks, ?array $p = null, array $opsi = []): array
    {
        $p    ??= (new PklPengaturanModel())->ambil();
        $asal   = ($opsi['sumber'] ?? 'staf') === 'siswa' ? 'siswa' : 'staf';
        $h      = self::periksa($in);
        $galat  = $h['galat'];
        $aid    = (int) ($in['ajuan_id'] ?? 0);
        $pilih  = array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($in['siswa'] ?? []), 'is_scalar')), static fn (int $x) => $x > 0)));
        $a      = $aid > 0 ? $this->grup($aid) : null;
        if ($a === null) {
            return $this->tolak(['ajuan_id' => 'Ajuan PKL tidak ditemukan, belum disetujui, atau siswanya tidak aktif lagi.']);
        }
        $siswa = array_values(array_filter($a['siswa'], static fn (array $s) => in_array($s['siswa_id'], $pilih, true)));
        if ($pilih === []) {
            $galat['siswa'] = 'Pilih minimal satu siswa yang PKL-nya diperpanjang.';
        } elseif (count($siswa) !== count($pilih)) {
            $galat['siswa'] = 'Ada siswa pilihan yang tidak sedang PKL di ' . $a['perusahaan'] . ' (ajuan disetujui). Muat ulang halaman lalu pilih lagi.';
        }

        // Tanggal selesai semula: data sekolah menang; diketik hanya bila belum tercatat (R3).
        $lama   = $a['selesai'] ?? '';
        $sumber = 'sekolah';
        if ($lama === '' || $lama === null) {
            $lama   = (string) $h['isi']['selesai_lama'];
            $sumber = $asal;
        }
        if (! isset($galat['selesai_lama']) && ! isset($galat['selesai_baru'])) {
            $at    = self::aturanTanggal($a['mulai'] ?? null, (string) $lama, $sumber, (string) $h['isi']['selesai_baru'], $p);
            $galat += $at['galat'];
        }
        if (! isset($galat['siswa']) && ($beda = self::tolakBeda($siswa, (string) $lama)) !== '') {
            $galat['siswa'] = $beda;
        }
        if (($opsi['jendela'] ?? false) && $lama !== '' && ! isset($galat['selesai_lama'])) {
            $j = self::jendela((string) $lama, $p, $opsi['hariIni'] ?? null);
            if ($j['status'] === 'dini') {
                return $this->tolak(['jendela' => 'Surat perpanjangan bisa diminta mulai ' . IsianBantu::tanggalIndo((string) $j['buka_mulai']) . ' (' . self::setelan($p)['sebelum'] . ' hari sebelum PKL selesai).']);
            }
            if ($j['status'] === 'lewat') {
                return $this->tolak(['jendela' => 'Batas meminta surat perpanjangan sudah lewat (' . IsianBantu::tanggalIndo((string) $j['tutup']) . '). Hubungi operator sekolah.']);
            }
        }
        if ($galat !== []) {
            return $this->tolak($galat);
        }

        $isi  = ['sapaan' => $h['isi']['sapaan'], 'selesai_lama' => (string) $lama, 'sumber_lama' => $sumber, 'selesai_baru' => $h['isi']['selesai_baru'],
            'alasan_perpanjangan' => $h['isi']['alasan_perpanjangan'], 'alamat' => (string) $a['alamat']];
        $ids  = array_column($siswa, 'siswa_id');
        $nama = array_column($siswa, 'nama', 'siswa_id');
        $grup = $a['ajuan_ids'];
        $svc  = new SuratSekolah($this->db);

        $this->db->transBegin();
        try {
            // Kunci baris anggota seluruh perusahaan (R5 dan R6 diperiksa sesudah ini, jadi permintaan bersamaan menunggu giliran).
            $this->db->query('SELECT id FROM pkl_anggota WHERE pengajuan_id IN ? FOR UPDATE', [$grup]);

            $ada = $this->aktif($ids, $grup);
            if ($ada !== []) {
                $this->db->transRollback();
                $teks = [];
                foreach ($ada as $sid => $x) {
                    $teks[] = ($nama[$sid] ?? ('#' . $sid)) . ' (' . $x['kode'] . ')';
                }

                return $this->tolak(['siswa' => 'Perpanjangan PKL hanya boleh satu kali. Sudah punya surat perpanjangan: ' . implode(', ', $teks) . '.'], 'sudah');
            }
            if (($tarik = $this->ditarik($grup, $ids)) !== []) {
                $this->db->transRollback();
                $teks = [];
                foreach ($tarik as $sid => $kode) {
                    $teks[] = ($nama[$sid] ?? ('#' . $sid)) . ' (' . $kode . ')';
                }

                return $this->tolak(['siswa' => 'Siswa sedang ditarik dari tempat PKL ini (Surat Penarikan): ' . implode(', ', $teks) . '.']);
            }

            // R6: surat perusahaan ini dengan tanggal semula & baru yang sama dan masih bisa diubah → ikut; selain itu surat baru.
            $ikut = $this->suratTerbuka($grup, (string) $lama, $isi['selesai_baru']);

            if ($ikut !== null) {
                $suratId = $ikut;
                $lamaSiswa = $svc->siswa($suratId);
                foreach ($siswa as $s) {
                    if (! $svc->aturSiswa($suratId, null, self::barisSurat($s), self::judul($a['perusahaan'], array_merge(array_column($lamaSiswa, 'nama'), array_values($nama))))) {
                        throw new \RuntimeException('siswa tidak bisa ditambahkan ke surat #' . $suratId);
                    }
                }
                $svc->catat($suratId, 'ubah', $konteks, mb_substr('Ditambahkan: ' . implode(', ', $nama) . ' (perpanjangan sampai ' . IsianBantu::tanggalIndo($isi['selesai_baru']) . ')', 0, 255));
                $kodeHasil = 'ikut';
            } else {
                $r = $svc->buat(SuratJenis::PERPANJANGAN, [
                    'judul' => self::judul($a['perusahaan'], array_values($nama)), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                    'perusahaan_nama' => $a['perusahaan'], 'pengajuan_id' => $aid, 'sumber' => $asal,
                    'siswa' => array_map(self::barisSurat(...), $siswa),
                ], $konteks, $p);
                if (! $r['ok']) {
                    throw new \RuntimeException((string) ($r['pesan'] ?? 'surat gagal disimpan'));
                }
                $suratId = (int) $r['id'];
                $now     = date('Y-m-d H:i:s');
                $this->db->table('pkl_perpanjangan')->insert([
                    'surat_id' => $suratId, 'pengajuan_id' => $aid, 'selesai_lama' => $isi['selesai_lama'], 'sumber_lama' => $sumber,
                    'selesai_baru' => $isi['selesai_baru'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                $kodeHasil = 'baru';
            }
            $this->catatAjuan($siswa, $konteks, 'Surat Perpanjangan ' . SuratSekolahModel::kode($suratId) . ($kodeHasil === 'ikut' ? ' (ditambah)' : ' dibuat'), $isi['selesai_baru']);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Perpanjangan] buat gagal (ajuan #' . $aid . '): ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Surat perpanjangan gagal disimpan. Coba lagi sebentar.'];
        }

        return [
            'ok' => true, 'kode' => $kodeHasil, 'surat_id' => $suratId, 'peringatan' => $at['peringatan'] ?? [],
            'pesan' => $kodeHasil === 'ikut'
                ? implode(', ', $nama) . ' ditambahkan ke Surat Perpanjangan ' . SuratSekolahModel::kode($suratId) . ' (tanggal perpanjangan sama).'
                : 'Surat Perpanjangan ' . SuratSekolahModel::kode($suratId) . ' dibuat untuk ' . implode(', ', $nama) . '.',
        ];
    }

    /**
     * Ubah data surat perpanjangan yang belum bernomor (menunggu/dikembalikan): tanggal surat, sapaan, tanggal selesai semula
     * (hanya bila bukan dari data sekolah), tanggal baru, alasan, dan siswa (yang PKL di perusahaan yang sama; siswa baru tetap
     * diperiksa R1/R5/R13).
     *
     * @param array<string, mixed> $m  hasil SuratSekolah::muat()
     * @param array<string, mixed> $in seperti buat()
     *
     * @return array{ok: bool, pesan: string, galat?: array<string, string>, peringatan?: list<string>}
     */
    public function ubahSurat(array $m, array $in, array $konteks, ?array $p = null): array
    {
        $p   ??= (new PklPengaturanModel())->ambil();
        $surat = $m['surat'];
        if (($s = SuratSekolah::alasanTakBisaUbah($surat)) !== null) {
            return ['ok' => false, 'pesan' => $s];
        }
        $id    = (int) $surat['id'];
        $aid   = (int) ($surat['pengajuan_id'] ?? 0);
        $lamaI = $surat['isi_arr'];
        $h     = self::periksa($in);
        $galat = $h['galat'];
        $a     = $aid > 0 ? $this->grup($aid) : null;
        if ($a === null) {
            return ['ok' => false, 'pesan' => 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi surat tidak bisa diubah. Batalkan surat ini bila perlu.'];
        }
        $pilih = array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($in['siswa'] ?? []), 'is_scalar')), static fn (int $x) => $x > 0)));
        $siswa = array_values(array_filter($a['siswa'], static fn (array $x) => in_array($x['siswa_id'], $pilih, true)));
        if ($pilih === []) {
            $galat['siswa'] = 'Pilih minimal satu siswa yang PKL-nya diperpanjang.';
        } elseif (count($siswa) !== count($pilih)) {
            $galat['siswa'] = 'Ada siswa pilihan yang tidak sedang PKL di ' . $a['perusahaan'] . ' (ajuan disetujui). Muat ulang halaman lalu pilih lagi.';
        }
        $sumber = (string) ($lamaI['sumber_lama'] ?? 'sekolah');
        $lama   = (string) ($lamaI['selesai_lama'] ?? '');
        if ($a['selesai'] !== null) {
            [$lama, $sumber] = [(string) $a['selesai'], 'sekolah']; // Surat Balasan terbit sesudahnya → data sekolah menang
        } elseif ($sumber !== 'sekolah' && $h['isi']['selesai_lama'] !== '') {
            [$lama, $sumber] = [(string) $h['isi']['selesai_lama'], 'staf'];
        }
        if (! isset($galat['selesai_lama']) && ! isset($galat['selesai_baru'])) {
            $at    = self::aturanTanggal($a['mulai'] ?? null, $lama, $sumber, (string) $h['isi']['selesai_baru'], $p);
            $galat += $at['galat'];
        }
        if (! isset($galat['siswa']) && ($beda = self::tolakBeda($siswa, $lama)) !== '') {
            $galat['siswa'] = $beda;
        }
        if ($galat !== []) {
            return ['ok' => false, 'pesan' => 'Periksa lagi isiannya.', 'galat' => $galat];
        }
        $isi = ['sapaan' => $h['isi']['sapaan'], 'selesai_lama' => $lama, 'sumber_lama' => $sumber, 'selesai_baru' => $h['isi']['selesai_baru'],
            'alasan_perpanjangan' => $h['isi']['alasan_perpanjangan'], 'alamat' => (string) ($lamaI['alamat'] ?? $a['alamat'])];
        $ids  = array_column($siswa, 'siswa_id');
        $grup = $a['ajuan_ids'];
        $svc  = new SuratSekolah($this->db);

        $this->db->transBegin();
        try {
            $this->db->query('SELECT id FROM pkl_anggota WHERE pengajuan_id IN ? FOR UPDATE', [$grup]);
            $lain = array_filter($this->aktif($ids, $grup), static fn (array $x) => $x['surat_id'] !== $id);
            if ($lain !== []) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => 'Perpanjangan PKL hanya boleh satu kali — sudah punya surat lain: ' . implode(', ', array_map(static fn (array $x) => $x['kode'], $lain)) . '.', 'galat' => ['siswa' => 'Siswa sudah punya surat perpanjangan lain.']];
            }
            if (($tarik = $this->ditarik($grup, $ids)) !== []) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => 'Ada siswa yang sedang ditarik dari tempat PKL ini (' . implode(', ', $tarik) . ').', 'galat' => ['siswa' => 'Siswa sedang ditarik.']];
            }
            $r = $svc->ubah($id, [
                'judul' => self::judul((string) $surat['perusahaan_nama'], array_column($siswa, 'nama')), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                'siswa' => array_map(self::barisSurat(...), $siswa),
            ], $konteks);
            if (! $r['ok']) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.')];
            }
            $this->db->table('pkl_perpanjangan')->where('surat_id', $id)->update([
                'selesai_lama' => $lama, 'sumber_lama' => $sumber, 'selesai_baru' => $isi['selesai_baru'], 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->catatAjuan($siswa, $konteks, 'Surat Perpanjangan ' . SuratSekolahModel::kode($id) . ' diubah', $isi['selesai_baru']);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Perpanjangan] ubah #' . $id . ' gagal: ' . $e->getMessage());

            return ['ok' => false, 'pesan' => 'Perubahan gagal disimpan. Coba lagi sebentar.'];
        }

        return ['ok' => true, 'pesan' => 'Data surat diperbarui.', 'peringatan' => $at['peringatan'] ?? []];
    }

    /**
     * Ringkasan untuk halaman detail surat: tanggal semula (+ asal), mulai, tanggal baru, lama, berlaku?
     *
     * @param array<string, mixed> $surat baris surat_sekolah (+ isi_arr)
     *
     * @return array<string, mixed>
     */
    public static function ringkas(array $surat): array
    {
        $isi  = $surat['isi_arr'] ?? SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''));
        $lama = (string) ($isi['selesai_lama'] ?? '');
        $baru = (string) ($isi['selesai_baru'] ?? '');

        return [
            'selesai_lama' => $lama, 'sumber_lama' => (string) ($isi['sumber_lama'] ?? 'sekolah'), 'mulai' => self::mulai($lama), 'selesai_baru' => $baru,
            'lama_teks' => self::lamaTeks($lama, $baru), 'berlaku' => ($surat['status'] ?? '') === 'disetujui', 'alasan' => (string) ($isi['alasan_perpanjangan'] ?? ''),
        ];
    }

    /** Peringatan surat untuk SuratPeriksa (kuning; surat dilewati ACC massal). @return list<string> */
    public static function peringatanSurat(array $surat, ?array $ajuan, array $p): array
    {
        $r = self::ringkas($surat);

        return self::aturanTanggal(($ajuan['tanggal_mulai'] ?? '') !== '' ? (string) $ajuan['tanggal_mulai'] : null, $r['selesai_lama'], $r['sumber_lama'], $r['selesai_baru'], $p)['peringatan'];
    }

    private static ?bool $tabel = null;

    /** Tabel pkl_perpanjangan sudah dimigrasi (server yang belum migrasi tetap jalan; fitur ini saja yang belum ada). */
    public static function tabelAda(): bool
    {
        if (self::$tabel === null) {
            try {
                self::$tabel = db_connect()->tableExists('pkl_perpanjangan');
            } catch (\Throwable $e) {
                self::$tabel = false;
            }
        }

        return self::$tabel;
    }

    /**
     * Kalimat galat R13 bila ada siswa pilihan yang tanggal selesai tercatatnya beda dari tanggal semula surat ('' = semua cocok).
     *
     * @param list<array<string, mixed>> $siswa butir grup()
     */
    private static function tolakBeda(array $siswa, string $lama): string
    {
        $beda = [];
        foreach ($siswa as $s) {
            if (($t = self::bedaSelesai($s, $lama)) !== null) {
                $beda[] = $s['nama'] . ' (selesai ' . IsianBantu::tanggalIndo($t) . ')';
            }
        }

        return $beda === [] ? '' : 'Satu surat hanya untuk satu tanggal selesai PKL (' . IsianBantu::tanggalIndo($lama) . '). Tanggal selesai PKL berbeda: '
            . implode(', ', $beda) . ' — buat surat terpisah untuk siswa itu.';
    }

    /**
     * Baris siswa untuk SuratSekolah (+ ajuan asalnya).
     *
     * @param array<string, mixed> $s butir grup()
     *
     * @return array{siswa_id: int, kelas_id: ?int, hp: ?string, pengajuan_id: int}
     */
    private static function barisSurat(array $s): array
    {
        return ['siswa_id' => (int) $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp'], 'pengajuan_id' => (int) $s['ajuan_id']];
    }

    /**
     * Surat perpanjangan perusahaan ini (ajuan $ajuanIds) dengan tanggal semula & baru yang sama yang masih bisa ditambah siswa
     * (belum bernomor, menunggu/dikembalikan = SuratSekolah::alasanTakBisaUbah null), dikunci FOR UPDATE; null bila tidak ada (R6).
     *
     * @param list<int> $ajuanIds
     */
    private function suratTerbuka(array $ajuanIds, string $lama, string $baru): ?int
    {
        $r = $this->db->query('SELECT s.id FROM pkl_perpanjangan pp JOIN surat_sekolah s ON s.id = pp.surat_id'
            . ' WHERE pp.pengajuan_id IN ? AND pp.selesai_lama = ? AND pp.selesai_baru = ? AND s.jenis = ?'
            . " AND s.status IN ('menunggu', 'dikembalikan') AND (s.nomor IS NULL OR s.nomor = '') ORDER BY s.id ASC LIMIT 1 FOR UPDATE",
            [$ajuanIds, $lama, $baru, SuratJenis::PERPANJANGAN])->getRowArray();

        return $r === null ? null : (int) $r['id'];
    }

    /**
     * Riwayat tiap ajuan asal siswa surat ("Surat Perpanjangan X dibuat: A, B diperpanjang sampai …"), supaya detail ajuan teman
     * dari ajuan lain juga mencatatnya.
     *
     * @param list<array<string, mixed>> $siswa butir grup()
     * @param array<string, mixed>       $konteks
     */
    private function catatAjuan(array $siswa, array $konteks, string $awal, string $baru): void
    {
        $per = [];
        foreach ($siswa as $s) {
            $per[(int) $s['ajuan_id']][] = (string) $s['nama'];
        }
        $svc = new PklAjuan($this->db);
        foreach ($per as $aid => $nama) {
            $lain = count($siswa) - count($nama);
            $svc->catat($aid, 'surat_sekolah', $konteks, mb_substr($awal . ': ' . implode(', ', $nama) . ' diperpanjang sampai ' . IsianBantu::tanggalIndo($baru)
                . ($lain > 0 ? ' (satu surat dengan ' . $lain . ' siswa dari ajuan lain)' : ''), 0, 255));
        }
    }

    /**
     * @param array<string, string> $galat
     *
     * @return array{ok: false, kode: string, pesan: string, galat: array<string, string>}
     */
    private function tolak(array $galat, string $kode = 'tolak'): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => (string) reset($galat), 'galat' => $galat];
    }
}
