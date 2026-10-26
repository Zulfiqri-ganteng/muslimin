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
 *   - Syarat (R1): ajuan PKL disetujui, siswa anggota aktif ajuan itu, tidak punya Surat Penarikan yang belum dibatalkan.
 *   - Maksimal SATU KALI per siswa per ajuan (R5, keputusan user): ditolak bila sudah ada surat perpanjangan siswa itu yang belum
 *     dibatalkan. Dibatalkan = boleh minta lagi.
 *   - Tanggal selesai semula: dari ajuan (Surat Balasan / laporan siswa) bila tercatat; kosong → diketik siswa/staf (ditandai).
 *   - Satu surat per ajuan + tanggal selesai baru (R6): penyusul bertanggal sama ikut surat itu selama belum bernomor/disetujui.
 *   - Tanggal baru BERLAKU hanya bila suratnya disetujui (R8) — dihitung lewat JOIN (selesaiEfektif), tanpa menyalin ke tabel lain,
 *     jadi ACC / batal ACC / batal surat otomatis benar. pkl_pengajuan.tanggal_selesai TIDAK ditimpa (R9).
 *
 * Isi surat dibekukan di surat_sekolah.isi (sapaan, selesai_lama, sumber_lama, selesai_baru, alasan_perpanjangan, kunci) + tabel
 * pkl_perpanjangan (satu baris per surat, tanggal bertipe DATE untuk dicari). Siswa surat = surat_sekolah_siswa.
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

    /** Kunci pencegah ganda (R6): ajuan + tanggal selesai baru; $n > 1 = surat lanjutan bila surat pertama sudah bernomor/disetujui. */
    public static function kunci(int $ajuanId, string $selesaiBaru, int $n = 1): string
    {
        $dasar = sha1('perpanjangan|' . $ajuanId . '|' . $selesaiBaru);

        return $n > 1 ? sha1($dasar . '|' . $n) : $dasar;
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
     * Perpanjangan yang BELUM dibatalkan untuk siswa-siswa ini (opsional: hanya ajuan $ajuanId), satu per siswa per ajuan.
     *
     * @param list<int> $siswaIds
     *
     * @return array<int, array{surat_id: int, kode: string, status: string, nomor: ?string, ajuan_id: int, selesai_lama: string, sumber_lama: string, selesai_baru: string, berlaku: bool}>
     *         siswa_id => perpanjangan (bila siswa punya di beberapa ajuan, yang terbaru)
     */
    public function aktif(array $siswaIds, ?int $ajuanId = null): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x) => $x > 0)));
        if ($siswaIds === [] || ! self::tabelAda()) {
            return [];
        }
        $b = $this->db->table('pkl_perpanjangan pp')
            ->select('ss.siswa_id, s.id AS surat_id, s.status, s.nomor, pp.pengajuan_id, pp.selesai_lama, pp.sumber_lama, pp.selesai_baru')
            ->join('surat_sekolah s', 's.id = pp.surat_id')
            ->join('surat_sekolah_siswa ss', 'ss.surat_id = s.id')
            ->where('s.jenis', SuratJenis::PERPANJANGAN)->where('s.status !=', 'dibatalkan')
            ->whereIn('ss.siswa_id', $siswaIds);
        if ($ajuanId !== null) {
            $b->where('pp.pengajuan_id', $ajuanId);
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
     * Siswa dengan Surat Penarikan yang belum dibatalkan di ajuan ini (R1).
     *
     * @param list<int> $siswaIds
     *
     * @return array<int, string> siswa_id => kode surat penarikan
     */
    public function ditarik(int $ajuanId, array $siswaIds): array
    {
        if ($siswaIds === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('surat_sekolah s')->select('ss.siswa_id, s.id')
            ->join('surat_sekolah_siswa ss', 'ss.surat_id = s.id')
            ->where('s.jenis', SuratJenis::PENARIKAN)->where('s.status !=', 'dibatalkan')->where('s.pengajuan_id', $ajuanId)
            ->whereIn('ss.siswa_id', $siswaIds)->get()->getResultArray() as $r) {
            $out[(int) $r['siswa_id']] = SuratSekolahModel::kode((int) $r['id']);
        }

        return $out;
    }

    /**
     * Satu ajuan disetujui untuk formulir: data ajuan (SuratPkl::ajuan) + tanggal selesai semula & asalnya + status tiap siswa
     * (bisa dipilih? sudah diperpanjang / ditarik). null bila ajuan tidak disetujui / tanpa siswa aktif.
     *
     * @return array<string, mixed>|null
     */
    public function ajuanForm(int $ajuanId): ?array
    {
        $a = (new SuratPkl($this->db))->ajuan([$ajuanId])[0] ?? null;
        if ($a === null) {
            return null;
        }
        $ids    = array_column($a['siswa'], 'siswa_id');
        $pj     = $this->aktif($ids, $ajuanId);
        $tarik  = $this->ditarik($ajuanId, $ids);
        $bisa   = 0;
        foreach ($a['siswa'] as &$s) {
            $x            = $pj[$s['siswa_id']] ?? null;
            $s['pj']      = $x !== null ? ['kode' => $x['kode'], 'surat_id' => $x['surat_id'], 'status' => $x['status'], 'selesai_baru' => $x['selesai_baru'], 'berlaku' => $x['berlaku']] : null;
            $s['ditarik'] = $tarik[$s['siswa_id']] ?? null;
            $s['bisa']    = $x === null && $s['ditarik'] === null;
            $bisa        += $s['bisa'] ? 1 : 0;
        }
        unset($s);
        $a['selesai_lama'] = $a['selesai'];
        $a['sumber_lama']  = $a['selesai'] !== null ? 'sekolah' : null;
        $a['bisa']         = $bisa;

        return $a;
    }

    /**
     * Cari ajuan disetujui (perusahaan, kode PKL-xxxxx, atau nama siswa aktif) untuk kotak "cari lalu pilih". Maks $maks ajuan,
     * terbaru dulu. Kata kunci < 2 huruf → [].
     *
     * @return list<array<string, mixed>> butir ajuanForm()
     */
    public function cari(string $q, int $maks = 10): array
    {
        $q = IsianBantu::rapikan($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $b = $this->db->table('pkl_pengajuan p')->select('p.id')->distinct()
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.status', 'disetujui')->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->groupStart()->like('p.perusahaan_nama', $q)->orLike('m.nama', $q)->orLike('s.nama', $q);
        if (preg_match('~^(pkl-?)?0*(\d{1,9})$~i', $q, $m) === 1) {
            $b->orWhere('p.id', (int) $m[2]);
        }
        $ids = array_map('intval', array_column($b->groupEnd()->orderBy('p.id', 'DESC')->get($maks)->getResultArray(), 'id'));

        return array_values(array_filter(array_map(fn (int $id) => $this->ajuanForm($id), $ids)));
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
                . ' JOIN surat_sekolah_siswa xs ON xs.surat_id = x.id WHERE pp.pengajuan_id = a.pengajuan_id AND xs.siswa_id = a.siswa_id)'
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
     * Buat surat perpanjangan (atau ikut surat yang sama, R6) untuk siswa pilihan satu ajuan. Semua pemeriksaan & penulisan dalam
     * SATU transaksi dengan kunci baris anggota (dua klik / dua teman bersamaan tidak menghasilkan perpanjangan ganda).
     *
     * @param array<string, mixed> $in      ajuan_id, siswa[] (id), tanggal_surat, sapaan, selesai_lama (bila belum tercatat), selesai_baru,
     *                                      alasan_pilih, alasan_lain
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
        $a      = $aid > 0 ? (new SuratPkl($this->db))->ajuan([$aid])[0] ?? null : null;
        if ($a === null) {
            return $this->tolak(['ajuan_id' => 'Ajuan PKL tidak ditemukan, belum disetujui, atau siswanya tidak aktif lagi.']);
        }
        $siswa = array_values(array_filter($a['siswa'], static fn (array $s) => in_array($s['siswa_id'], $pilih, true)));
        if ($pilih === []) {
            $galat['siswa'] = 'Pilih minimal satu siswa yang PKL-nya diperpanjang.';
        } elseif (count($siswa) !== count($pilih)) {
            $galat['siswa'] = 'Ada siswa pilihan yang bukan anggota aktif ajuan ' . $a['kode'] . '. Muat ulang halaman lalu pilih lagi.';
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
        $svc  = new SuratSekolah($this->db);

        $this->db->transBegin();
        try {
            // Kunci baris anggota (R5 dan R6 diperiksa sesudah ini, jadi permintaan bersamaan menunggu giliran).
            $this->db->query('SELECT id FROM pkl_anggota WHERE pengajuan_id = ? AND siswa_id IN ? FOR UPDATE', [$aid, $ids]);

            $ada = $this->aktif($ids, $aid);
            if ($ada !== []) {
                $this->db->transRollback();
                $teks = [];
                foreach ($ada as $sid => $x) {
                    $teks[] = ($nama[$sid] ?? ('#' . $sid)) . ' (' . $x['kode'] . ')';
                }

                return $this->tolak(['siswa' => 'Perpanjangan PKL hanya boleh satu kali. Sudah punya surat perpanjangan: ' . implode(', ', $teks) . '.'], 'sudah');
            }
            if (($tarik = $this->ditarik($aid, $ids)) !== []) {
                $this->db->transRollback();
                $teks = [];
                foreach ($tarik as $sid => $kode) {
                    $teks[] = ($nama[$sid] ?? ('#' . $sid)) . ' (' . $kode . ')';
                }

                return $this->tolak(['siswa' => 'Siswa sedang ditarik dari tempat PKL ini (Surat Penarikan): ' . implode(', ', $teks) . '.']);
            }

            // R6: surat ajuan + tanggal baru yang sama dan masih bisa diubah → ikut; sudah bernomor/disetujui → surat lanjutan.
            $ikut = null;
            for ($n = 1; $n <= 20; $n++) {
                $kunci = self::kunci($aid, $isi['selesai_baru'], $n);
                $x     = $svc->adaKunci($kunci);
                if ($x === null) {
                    break;
                }
                $baris = $this->db->query('SELECT id, status, nomor FROM surat_sekolah WHERE id = ? FOR UPDATE', [$x['id']])->getRowArray();
                if ($baris !== null && SuratSekolah::alasanTakBisaUbah($baris) === null) {
                    $ikut = (int) $baris['id'];
                    break;
                }
            }
            $isi['kunci'] = $kunci;

            if ($ikut !== null) {
                $suratId = $ikut;
                $lamaSiswa = $svc->siswa($suratId);
                foreach ($siswa as $s) {
                    if (! $svc->aturSiswa($suratId, null, ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], self::judul($a['perusahaan'], array_merge(array_column($lamaSiswa, 'nama'), array_values($nama))))) {
                        throw new \RuntimeException('siswa tidak bisa ditambahkan ke surat #' . $suratId);
                    }
                }
                $svc->catat($suratId, 'ubah', $konteks, mb_substr('Ditambahkan: ' . implode(', ', $nama) . ' (perpanjangan sampai ' . IsianBantu::tanggalIndo($isi['selesai_baru']) . ')', 0, 255));
                $kodeHasil = 'ikut';
            } else {
                $r = $svc->buat(SuratJenis::PERPANJANGAN, [
                    'judul' => self::judul($a['perusahaan'], array_values($nama)), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                    'perusahaan_nama' => $a['perusahaan'], 'pengajuan_id' => $aid, 'sumber' => $asal,
                    'siswa' => array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $siswa),
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
            (new PklAjuan($this->db))->catat($aid, 'surat_sekolah', $konteks, mb_substr('Surat Perpanjangan ' . SuratSekolahModel::kode($suratId) . ($kodeHasil === 'ikut' ? ' (ditambah)' : ' dibuat')
                . ': ' . implode(', ', $nama) . ' diperpanjang sampai ' . IsianBantu::tanggalIndo($isi['selesai_baru']), 0, 255));
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
     * (hanya bila bukan dari data sekolah), tanggal baru, alasan, dan siswa (dari ajuan yang sama; siswa baru tetap diperiksa R1/R5).
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
        $a     = $aid > 0 ? (new SuratPkl($this->db))->ajuan([$aid])[0] ?? null : null;
        if ($a === null) {
            return ['ok' => false, 'pesan' => 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi surat tidak bisa diubah. Batalkan surat ini bila perlu.'];
        }
        $pilih = array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($in['siswa'] ?? []), 'is_scalar')), static fn (int $x) => $x > 0)));
        $siswa = array_values(array_filter($a['siswa'], static fn (array $x) => in_array($x['siswa_id'], $pilih, true)));
        if ($pilih === []) {
            $galat['siswa'] = 'Pilih minimal satu siswa yang PKL-nya diperpanjang.';
        } elseif (count($siswa) !== count($pilih)) {
            $galat['siswa'] = 'Ada siswa pilihan yang bukan anggota aktif ajuan ini. Muat ulang halaman lalu pilih lagi.';
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
        if ($galat !== []) {
            return ['ok' => false, 'pesan' => 'Periksa lagi isiannya.', 'galat' => $galat];
        }
        $isi = ['sapaan' => $h['isi']['sapaan'], 'selesai_lama' => $lama, 'sumber_lama' => $sumber, 'selesai_baru' => $h['isi']['selesai_baru'],
            'alasan_perpanjangan' => $h['isi']['alasan_perpanjangan'], 'alamat' => (string) ($lamaI['alamat'] ?? $a['alamat'])];
        $ids     = array_column($siswa, 'siswa_id');
        $svc     = new SuratSekolah($this->db);
        $isi['kunci'] = (string) ($lamaI['kunci'] ?? '');
        if ($isi['selesai_baru'] !== (string) ($lamaI['selesai_baru'] ?? '')) {
            for ($n = 1; $n <= 20; $n++) { // tanggal baru berubah → kunci baru yang belum dipakai surat lain
                $isi['kunci'] = self::kunci($aid, $isi['selesai_baru'], $n);
                if ($svc->adaKunci($isi['kunci'], $id) === null) {
                    break;
                }
            }
        }

        $this->db->transBegin();
        try {
            $this->db->query('SELECT id FROM pkl_anggota WHERE pengajuan_id = ? AND siswa_id IN ? FOR UPDATE', [$aid, $ids]);
            $lain = array_filter($this->aktif($ids, $aid), static fn (array $x) => $x['surat_id'] !== $id);
            if ($lain !== []) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => 'Perpanjangan PKL hanya boleh satu kali — sudah punya surat lain: ' . implode(', ', array_map(static fn (array $x) => $x['kode'], $lain)) . '.', 'galat' => ['siswa' => 'Siswa sudah punya surat perpanjangan lain.']];
            }
            if (($tarik = $this->ditarik($aid, $ids)) !== []) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => 'Ada siswa yang sedang ditarik dari tempat PKL ini (' . implode(', ', $tarik) . ').', 'galat' => ['siswa' => 'Siswa sedang ditarik.']];
            }
            $r = $svc->ubah($id, [
                'judul' => self::judul((string) $surat['perusahaan_nama'], array_column($siswa, 'nama')), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                'siswa' => array_map(static fn (array $x) => ['siswa_id' => $x['siswa_id'], 'kelas_id' => $x['kelas_id'], 'hp' => $x['hp']], $siswa),
            ], $konteks);
            if (! $r['ok']) {
                $this->db->transRollback();

                return ['ok' => false, 'pesan' => (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.')];
            }
            $this->db->table('pkl_perpanjangan')->where('surat_id', $id)->update([
                'selesai_lama' => $lama, 'sumber_lama' => $sumber, 'selesai_baru' => $isi['selesai_baru'], 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new PklAjuan($this->db))->catat($aid, 'surat_sekolah', $konteks, mb_substr('Surat Perpanjangan ' . SuratSekolahModel::kode($id) . ' diubah: '
                . implode(', ', array_column($siswa, 'nama')) . ' diperpanjang sampai ' . IsianBantu::tanggalIndo($isi['selesai_baru']), 0, 255));
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
     * @param array<string, string> $galat
     *
     * @return array{ok: false, kode: string, pesan: string, galat: array<string, string>}
     */
    private function tolak(array $galat, string $kode = 'tolak'): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => (string) reset($galat), 'galat' => $galat];
    }
}
