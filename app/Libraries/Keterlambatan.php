<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalPiketModel;
use App\Models\KeterlambatanModel;
use App\Models\SettingModel;

/**
 * Buku piket keterlambatan siswa (menu KURIKULUM → Guru Piket). SATU sumber logika untuk halaman admin (Admin\Keterlambatan)
 * dan API Android (tahap berikutnya): cari siswa, catat, ubah, hapus, daftar per tanggal, guru piket hari itu.
 *
 * Aturan:
 *   - satu siswa paling banyak SATU catatan per tanggal; tanggal tidak boleh di masa depan;
 *   - siswa yang dicatat = siswa aktif (status 'aktif', tidak terhapus); nama, kelas, shift kelas, dan tahun ajaran disalin;
 *   - siswa yang BELUM TERDAFTAR di Data Siswa boleh dicatat MANUAL (manual = 1): nama diketik (disimpan HURUF BESAR seperti Data
 *     Siswa), kelas dipilih dari daftar kelas, nomor HP orang tua opsional (hp_ortu, untuk tombol WA). Ditolak bila nama itu ternyata
 *     siswa aktif terdaftar di kelas yang sama (harus dipilih dari pencarian). Satu nama + kelas paling banyak satu catatan per tanggal.
 *     Nama, kelas, & nomornya BOLEH diubah (salah ketik). Setelah anak itu masuk Data Siswa → tautkan(): catatannya menjadi catatan
 *     siswa terdaftar (rekap menyatu, WA memakai Data Siswa);
 *   - guru piket WAJIB dipilih (akun Guru Piket dipakai bersama) — saran = jadwal piket hari itu (Jadwal Piket), boleh guru lain;
 *   - sanksi & keterangan = teks bebas guru piket; jam datang boleh kosong;
 *   - siswa terdaftar & tanggal tidak bisa diubah (salah pilih → hapus lalu catat ulang).
 * Pencatat ($pencatat) = ['id' => ?int admins.id, 'nama' => string] — web dari sesi, API dari token.
 */
final class Keterlambatan
{
    public const SHIFT = ['pagi', 'siang'];
    public const HARI  = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu', 'Minggu'];

    public const MAKS_SANKSI     = 150;
    public const MAKS_KETERANGAN = 255;
    public const MIN_NAMA        = 3;
    public const MAKS_NAMA       = 150;
    /** Saran "pernah dicatat manual" paling banyak sekian di hasil pencarian. */
    public const MAKS_SARAN_MANUAL = 5;

    private KeterlambatanModel $model;
    private string $saluran;

    /** @param string $saluran 'web' | 'aplikasi' (API Android: Audit Log diberi "(via aplikasi)"). Hasil gagal membawa 'kode' 404|409|422. */
    public function __construct(string $saluran = 'web')
    {
        $this->model   = new KeterlambatanModel();
        $this->saluran = $saluran;
    }

    private function audit(string $aksi, int $id, string $teks): void
    {
        (new AuditModel())->record($aksi, 'keterlambatan_siswa', $id, mb_substr($teks . ($this->saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));
    }

    // =================================================================
    // Tanggal & guru piket
    // =================================================================

    /** Tanggal Y-m-d yang sah dan tidak di masa depan; null bila tidak. */
    public static function tanggalSah(?string $t): ?string
    {
        $t = trim((string) $t);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $t <= date('Y-m-d') ? $t : null;
    }

    /** "2026-10-19" → "Senin, 19 Oktober 2026". */
    public static function hariTanggal(string $tanggal): string
    {
        return self::HARI[(int) date('N', strtotime($tanggal))] . ', ' . IsianBantu::tanggalIndo($tanggal);
    }

    /** Jam "7:05" / "07:05" / "07:05:00" → "07:05:00"; '' → null; tidak sah → false. */
    public static function jamSah(?string $jam): string|false|null
    {
        $jam = trim((string) $jam);
        if ($jam === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2})[:.](\d{2})(?::(\d{2}))?$/', $jam, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
            return false;
        }

        return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
    }

    /** Tahun ajaran sekolah sekarang (Pengaturan Sekolah), mis. "2026/2027". */
    public static function tahunAjaran(): string
    {
        return mb_substr(trim((string) ((new SettingModel())->get()['academic_year'] ?? '')), 0, 20);
    }

    /**
     * Guru piket pada hari tanggal itu menurut Jadwal Piket: ['pagi' => [['id', 'nama'], …], 'siang' => […]].
     *
     * @return array{pagi: list<array{id: int, nama: string}>, siang: list<array{id: int, nama: string}>}
     */
    public function guruPiketHari(string $tanggal): array
    {
        $out  = ['pagi' => [], 'siang' => []];
        $hari = (new HariModel())->byWeekday((int) date('N', strtotime($tanggal)));
        if ($hari === null) {
            return $out;
        }
        $ids  = (new JadwalPiketModel())->forHari((int) $hari['id']);
        $nama = $this->namaGuru(array_merge($ids['pagi'], $ids['siang']));
        foreach (self::SHIFT as $sh) {
            foreach ($ids[$sh] as $gid) {
                if (isset($nama[$gid])) {
                    $out[$sh][] = ['id' => $gid, 'nama' => $nama[$gid]];
                }
            }
            usort($out[$sh], static fn (array $a, array $b): int => strcasecmp($a['nama'], $b['nama']));
        }

        return $out;
    }

    /**
     * Semua guru yang bisa dipilih sebagai guru piket (sama dengan pilihan di Jadwal Piket: tanpa data guru ganda & guru yang
     * tidak ikut absensi).
     *
     * @return list<array{id: int, nama: string}>
     */
    public function pilihanGuru(): array
    {
        $peta   = GuruModel::petaOrang();
        $keluar = GuruModel::tidakIkutAbsensi();
        $out    = [];
        foreach ((new GuruModel())->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $id = (int) $g['id'];
            if (! isset($peta[$id]) && ! isset($keluar[$id])) {
                $out[] = ['id' => $id, 'nama' => (string) $g['nama']];
            }
        }

        return $out;
    }

    /** @param list<int> $ids @return array<int, string> */
    private function namaGuru(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ((new GuruModel())->select('id, nama')->whereIn('id', array_values(array_unique($ids)))->findAll() as $g) {
            $out[(int) $g['id']] = (string) $g['nama'];
        }

        return $out;
    }

    // =================================================================
    // Cari siswa & daftar
    // =================================================================

    /**
     * Siswa aktif yang namanya memuat $q atau NIS/NISN-nya diawali $q (minimal 2 huruf), urut nama. 'tercatat' = jam/teks bila
     * siswa itu sudah dicatat terlambat pada $tanggal.
     * $denganManual: + di AKHIR daftar, paling banyak MAKS_SARAN_MANUAL siswa BELUM TERDAFTAR yang pernah dicatat manual dengan nama
     * memuat $q (id = null, manual = true, 'jumlah' = berapa kali dicatat, 'hp_ortu' = nomor terakhir yang diisi) — memilihnya mengisi
     * formulir manual dengan ejaan yang sama (rekap tetap menyatu). Tanpa $denganManual hasilnya hanya siswa terdaftar (aplikasi lama).
     *
     * @return list<array{id: ?int, manual: bool, nama: string, nis: string, kelas: string, kelas_id: ?int, shift: ?string, tercatat: ?string, jumlah?: int, hp_ortu?: ?string}>
     */
    public function cariSiswa(string $q, string $tanggal, int $batas = 15, bool $denganManual = false): array
    {
        $q = IsianBantu::rapikan($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $rows = $this->siswaAktif()
            ->groupStart()
                ->like('s.nama', $q)
                ->orLike('s.nis', $q, 'after')
                ->orLike('s.nisn', $q, 'after')
            ->groupEnd()
            ->orderBy('s.nama', 'ASC')
            ->limit(max(1, min($batas, 50)))
            ->get()->getResultArray();
        $out = $this->bentukSiswa($rows, $tanggal);

        return $denganManual ? array_merge($out, $this->saranManual($tanggal, $q)) : $out;
    }

    /** Siswa aktif per kelas paling banyak sekian (kelas terbesar sekarang ±60). */
    public const MAKS_SISWA_KELAS = 200;

    /**
     * SEMUA siswa aktif satu kelas, urut nama — tombol "Pilih dari kelas" di formulir catat (guru piket menekan kelas lalu nama,
     * tanpa mengetik). Baris berbentuk sama dengan cariSiswa(). $denganManual: + di AKHIR, siswa BELUM TERDAFTAR yang pernah dicatat
     * manual di kelas itu pada tahun ajaran sekarang. null = kelas tidak ada / sudah dihapus.
     *
     * @return array{kelas: array{id: int, nama: string}, hasil: list<array<string, mixed>>}|null
     */
    public function siswaKelas(int $kelasId, string $tanggal, bool $denganManual = false): ?array
    {
        $kelas = $kelasId > 0 ? db_connect()->table('kelas')->select('id, nama_kelas')->where('id', $kelasId)->where('deleted_at', null)->get()->getRowArray() : null;
        if ($kelas === null) {
            return null;
        }
        $rows = $this->siswaAktif()->where('s.kelas_id', $kelasId)->orderBy('s.nama', 'ASC')->limit(self::MAKS_SISWA_KELAS)->get()->getResultArray();
        $out  = $this->bentukSiswa($rows, $tanggal);

        return [
            'kelas' => ['id' => (int) $kelas['id'], 'nama' => (string) $kelas['nama_kelas']],
            'hasil' => $denganManual ? array_merge($out, $this->saranManual($tanggal, '', $kelasId, self::MAKS_SISWA_KELAS)) : $out,
        ];
    }

    /** Pembangun kueri siswa aktif (tidak terhapus) + kelasnya — dasar cariSiswa() & siswaKelas(). */
    private function siswaAktif(): \CodeIgniter\Database\BaseBuilder
    {
        return db_connect()->table('siswa s')
            ->select('s.id, s.nama, s.nis, s.kelas_id, k.nama_kelas, k.shift')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif');
    }

    /**
     * Baris siswa untuk pencarian/daftar kelas; 'tercatat' = jam/teks bila siswa itu sudah dicatat terlambat pada $tanggal.
     *
     * @return list<array{id: int, manual: bool, nama: string, nis: string, kelas: string, kelas_id: ?int, shift: ?string, tercatat: ?string}>
     */
    private function bentukSiswa(array $rows, string $tanggal): array
    {
        $sudah = [];
        if ($rows !== []) {
            foreach ($this->model->select('siswa_id, jam')->where('tanggal', $tanggal)->whereIn('siswa_id', array_column($rows, 'id'))->findAll() as $r) {
                $sudah[(int) $r['siswa_id']] = self::teksTercatat($r['jam']);
            }
        }

        return array_map(static fn (array $r): array => [
            'id'       => (int) $r['id'],
            'manual'   => false,
            'nama'     => (string) $r['nama'],
            'nis'      => (string) ($r['nis'] ?? ''),
            'kelas'    => (string) ($r['nama_kelas'] ?? ''),
            'kelas_id' => $r['kelas_id'] !== null ? (int) $r['kelas_id'] : null,
            'shift'    => $r['shift'] ?? null,
            'tercatat' => $sudah[(int) $r['id']] ?? null,
        ], $rows);
    }

    /**
     * Siswa belum terdaftar yang pernah dicatat manual, dikelompokkan per nama + kelas: nama memuat $q (pencarian), atau semua di kelas
     * $kelasId pada tahun ajaran sekarang (daftar kelas).
     *
     * @return list<array<string, mixed>>
     */
    private function saranManual(string $tanggal, string $q = '', int $kelasId = 0, int $batas = self::MAKS_SARAN_MANUAL): array
    {
        $db    = db_connect();
        $kueri = $db->table('keterlambatan_siswa t')
            ->select("t.nama_siswa, t.kelas_id, MAX(t.id) id_akhir, COUNT(*) n, SUBSTRING_INDEX(GROUP_CONCAT(t.hp_ortu ORDER BY t.id DESC SEPARATOR ','), ',', 1) hp", false)
            ->where('t.manual', 1)->where('t.siswa_id', null);
        if ($q !== '') {
            $kueri->like('t.nama_siswa', $q);
        }
        if ($kelasId > 0) {
            // Anggota kelas berganti tiap tahun ajaran: catatan manual tahun lalu di kelas ini bukan anak kelas ini lagi.
            $kueri->where('t.kelas_id', $kelasId);
            if (($ta = self::tahunAjaran()) !== '') {
                $kueri->groupStart()->where('t.tahun_ajaran', $ta)->orWhere('t.tahun_ajaran', null)->groupEnd();
            }
        }
        $grup = $kueri->groupBy(['t.nama_siswa', 't.kelas_id'])
            ->orderBy('t.nama_siswa', 'ASC')
            ->limit($batas)
            ->get()->getResultArray();
        if ($grup === []) {
            return [];
        }
        // Nama & shift kelas SEKARANG (kelas bisa berganti nama); cadangan salinan pada catatan terakhir.
        $akhir = [];
        foreach ($db->table('keterlambatan_siswa t')->select('t.id, t.nama_kelas, t.shift, k.nama_kelas kelas_kini, k.shift shift_kini')
            ->join('kelas k', 'k.id = t.kelas_id AND k.deleted_at IS NULL', 'left')
            ->whereIn('t.id', array_column($grup, 'id_akhir'))->get()->getResultArray() as $r) {
            $akhir[(int) $r['id']] = $r;
        }
        $hariItu = [];
        foreach ($this->model->select('nama_siswa, kelas_id, jam')->where('tanggal', $tanggal)->where('manual', 1)->where('siswa_id', null)
            ->whereIn('nama_siswa', array_column($grup, 'nama_siswa'))->findAll() as $r) {
            $hariItu[self::kunciManual((string) $r['nama_siswa'], $r['kelas_id'])] = self::teksTercatat($r['jam']);
        }

        return array_map(static function (array $g) use ($akhir, $hariItu): array {
            $a = $akhir[(int) $g['id_akhir']] ?? [];

            return [
                'id'       => null,
                'manual'   => true,
                'nama'     => (string) $g['nama_siswa'],
                'nis'      => '',
                'kelas'    => (string) (($a['kelas_kini'] ?? null) ?: ($a['nama_kelas'] ?? '')),
                'kelas_id' => $g['kelas_id'] !== null ? (int) $g['kelas_id'] : null,
                'shift'    => ($a['shift_kini'] ?? null) ?: ($a['shift'] ?? null),
                'tercatat' => $hariItu[self::kunciManual((string) $g['nama_siswa'], $g['kelas_id'])] ?? null,
                'jumlah'   => (int) $g['n'],
                'hp_ortu'  => ($g['hp'] ?? '') !== '' ? (string) $g['hp'] : null,
            ];
        }, $grup);
    }

    private static function teksTercatat(mixed $jam): string
    {
        return $jam !== null ? 'jam ' . substr((string) $jam, 0, 5) : 'sudah dicatat';
    }

    /** Kunci satu siswa manual: nama (tanpa beda huruf besar/kecil) + kelas. */
    private static function kunciManual(string $nama, mixed $kelasId): string
    {
        return mb_strtoupper(IsianBantu::rapikan($nama)) . '|' . (int) $kelasId;
    }

    /**
     * Catatan satu tanggal, urut jam datang (kosong di akhir) lalu urutan dicatat. + 'nis', 'ortu_telepon', 'no_hp_wali' dari data siswa
     * (bila masih ada; untuk tombol WA orang tua).
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(string $tanggal): array
    {
        return db_connect()->table('keterlambatan_siswa t')
            ->select('t.*, s.nis, s.ortu_telepon, s.no_hp_wali')
            ->join('siswa s', 's.id = t.siswa_id', 'left')
            ->where('t.tanggal', $tanggal)
            ->orderBy('t.jam IS NULL', 'ASC', false)
            ->orderBy('t.jam', 'ASC')
            ->orderBy('t.id', 'ASC')
            ->get()->getResultArray();
    }

    public function cari(int $id): ?array
    {
        return $this->model->find($id) ?: null;
    }

    /** Satu catatan + 'nis', 'ortu_telepon', 'no_hp_wali' (seperti baris daftar()). */
    public function cariLengkap(int $id): ?array
    {
        return db_connect()->table('keterlambatan_siswa t')->select('t.*, s.nis, s.ortu_telepon, s.no_hp_wali')
            ->join('siswa s', 's.id = t.siswa_id', 'left')->where('t.id', $id)->get()->getRowArray() ?: null;
    }

    // =================================================================
    // Rentang tanggal, Excel buku piket, rekap
    // =================================================================

    public const MAKS_HARI_RENTANG = 366;

    /**
     * Rentang tanggal dari isian (kosong = awal bulan ini s.d. hari ini). Tidak sah → rentang bawaan + pesan.
     *
     * @return array{mulai: string, selesai: string, pesan: ?string}
     */
    public static function rentangSah(?string $mulai, ?string $selesai): array
    {
        $bawaan = ['mulai' => date('Y-m-01'), 'selesai' => date('Y-m-d'), 'pesan' => null];
        if (trim((string) $mulai) === '' && trim((string) $selesai) === '') {
            return $bawaan;
        }
        $m = self::tanggalSah($mulai);
        $s = self::tanggalSah($selesai);
        if ($m === null || $s === null) {
            return ['pesan' => 'Tanggal tidak sah atau masih di masa depan — ditampilkan bulan ini.'] + $bawaan;
        }
        if ($m > $s) {
            [$m, $s] = [$s, $m];
        }
        if (IsianBantu::hariInklusif($m, $s) > self::MAKS_HARI_RENTANG) {
            return ['mulai' => date('Y-m-d', strtotime($s . ' -' . (self::MAKS_HARI_RENTANG - 1) . ' days')), 'selesai' => $s,
                'pesan' => 'Rentang paling panjang ' . self::MAKS_HARI_RENTANG . ' hari — tanggal mulai disesuaikan.'];
        }

        return ['mulai' => $m, 'selesai' => $s, 'pesan' => null];
    }

    /**
     * Catatan dalam rentang (opsional satu kelas — kelas SAAT dicatat), urut tanggal lalu jam datang.
     *
     * @return list<array<string, mixed>>
     */
    public function rentang(string $mulai, string $selesai, int $kelasId = 0): array
    {
        $q = db_connect()->table('keterlambatan_siswa t')
            ->select('t.*, s.nis')
            ->join('siswa s', 's.id = t.siswa_id', 'left')
            ->where('t.tanggal >=', $mulai)->where('t.tanggal <=', $selesai);
        if ($kelasId > 0) {
            $q->where('t.kelas_id', $kelasId);
        }

        return $q->orderBy('t.tanggal', 'ASC')->orderBy('t.jam IS NULL', 'ASC', false)->orderBy('t.jam', 'ASC')->orderBy('t.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Isi kepala "GURU PIKET" satu lembar: guru piket yang MENCATAT hari itu (urut pertama mencatat); bila belum ada catatan,
     * jadwal piket hari itu (pagi lalu siang). Kosong = tidak ada keduanya (lembar memakai garis isian).
     */
    public function guruLembar(string $tanggal, array $rows): string
    {
        $nama = array_values(array_unique(array_filter(array_map(static fn (array $r): string => trim((string) ($r['guru_piket'] ?? '')), $rows))));
        if ($nama === []) {
            $pk   = $this->guruPiketHari($tanggal);
            $nama = array_values(array_unique(array_column(array_merge($pk['pagi'], $pk['siang']), 'nama')));
        }

        return implode(', ', $nama);
    }

    /**
     * Bahan Excel buku piket: satu hari (selalu ada lembar, walau kosong) atau rentang (hanya hari yang ada catatannya).
     *
     * @return list<array{tanggal: string, rows: list<array<string, mixed>>, guru: string}>
     */
    public function bahanBuku(string $mulai, string $selesai, int $kelasId = 0): array
    {
        $perHari = [];
        foreach ($this->rentang($mulai, $selesai, $kelasId) as $r) {
            $perHari[(string) $r['tanggal']][] = $r;
        }
        if ($perHari === [] && $mulai === $selesai) {
            $perHari[$mulai] = [];
        }
        $out = [];
        foreach ($perHari as $tgl => $rows) {
            $out[] = ['tanggal' => (string) $tgl, 'rows' => $rows, 'guru' => $this->guruLembar((string) $tgl, $rows)];
        }

        return $out;
    }

    /**
     * Rekap rentang: per siswa (jumlah terbanyak dulu) & per kelas (kelas SAAT dicatat).
     *
     * 'manual' = siswa belum terdaftar (dicatat manual; dikelompokkan per nama + kelas).
     *
     * @return array{siswa: list<array{nama: string, nis: string, kelas: string, manual: bool, jumlah: int, tanggal: list<string>, terakhir: string}>,
     *               kelas: list<array{kelas: string, jumlah: int, siswa: int}>, total: int, jumlah_siswa: int, jumlah_hari: int}
     */
    public function rekap(string $mulai, string $selesai, int $kelasId = 0): array
    {
        $rows  = $this->rentang($mulai, $selesai, $kelasId);
        $siswa = [];
        $kelas = [];
        $hari  = [];
        foreach ($rows as $r) {
            $manual = self::manual($r);
            $kunci  = $r['siswa_id'] !== null ? 'i' . $r['siswa_id'] : ($manual ? 'm' . self::kunciManual((string) $r['nama_siswa'], $r['kelas_id']) : 'n' . mb_strtolower((string) $r['nama_siswa']));
            $nk     = (string) ($r['nama_kelas'] ?? '') ?: 'Tanpa kelas';
            $siswa[$kunci] ??= ['nama' => (string) $r['nama_siswa'], 'nis' => (string) ($r['nis'] ?? ''), 'kelas' => $nk, 'manual' => $manual, 'jumlah' => 0, 'tanggal' => [], 'terakhir' => ''];
            $siswa[$kunci]['jumlah']++;
            $siswa[$kunci]['tanggal'][] = (string) $r['tanggal'];
            $siswa[$kunci]['terakhir']  = (string) $r['tanggal'];
            $siswa[$kunci]['kelas']     = $nk; // kelas saat catatan terakhir
            $kelas[$nk] ??= ['kelas' => $nk, 'jumlah' => 0, 'anggota' => []];
            $kelas[$nk]['jumlah']++;
            $kelas[$nk]['anggota'][$kunci] = true;
            $hari[(string) $r['tanggal']] = true;
        }
        $siswa = array_values($siswa);
        usort($siswa, static fn (array $a, array $b): int => [$b['jumlah'], $a['nama']] <=> [$a['jumlah'], $b['nama']]);
        $kelas = array_map(static fn (array $k): array => ['kelas' => $k['kelas'], 'jumlah' => $k['jumlah'], 'siswa' => count($k['anggota'])], array_values($kelas));
        usort($kelas, static fn (array $a, array $b): int => [$b['jumlah'], $a['kelas']] <=> [$a['jumlah'], $b['kelas']]);

        return ['siswa' => $siswa, 'kelas' => $kelas, 'total' => count($rows), 'jumlah_siswa' => count($siswa), 'jumlah_hari' => count($hari)];
    }

    /**
     * Kelas aktif urut tingkat lalu nama secara alami ("X TKJ 2" sebelum "X TKJ 10") + tingkat & jumlah siswa aktif — saringan rekap,
     * pilihan kelas siswa manual, dan tombol "Pilih dari kelas" di formulir catat.
     *
     * @return list<array{id: int, nama: string, tingkat: string, jumlah_siswa: int}>
     */
    public function pilihanKelas(): array
    {
        $db     = db_connect();
        $jumlah = array_column($db->table('siswa')->select('kelas_id, COUNT(*) n')->where('deleted_at', null)->where('status', 'aktif')
            ->where('kelas_id IS NOT NULL')->groupBy('kelas_id')->get()->getResultArray(), 'n', 'kelas_id');
        $kelas  = $db->table('kelas')->select('id, nama_kelas, tingkat')->where('deleted_at', null)->get()->getResultArray();
        usort($kelas, static fn (array $a, array $b): int => strcmp((string) $a['tingkat'], (string) $b['tingkat']) ?: strnatcasecmp((string) $a['nama_kelas'], (string) $b['nama_kelas']));

        return array_map(static fn (array $k): array => ['id' => (int) $k['id'], 'nama' => (string) $k['nama_kelas'], 'tingkat' => (string) ($k['tingkat'] ?? ''),
            'jumlah_siswa' => (int) ($jumlah[$k['id']] ?? 0)], $kelas);
    }

    /**
     * Sanksi yang paling sering ditulis (90 hari terakhir) — saran isian, guru piket tetap bebas menulis sendiri.
     *
     * @return list<string>
     */
    public function saranSanksi(int $batas = 12): array
    {
        return array_column($this->model->select('sanksi, COUNT(*) n')
            ->where('sanksi IS NOT NULL')->where('sanksi !=', '')
            ->where('tanggal >=', date('Y-m-d', strtotime('-90 days')))
            ->groupBy('sanksi')->orderBy('n', 'DESC')->orderBy('sanksi', 'ASC')
            ->findAll($batas), 'sanksi');
    }

    // =================================================================
    // Catat, ubah, hapus
    // =================================================================

    /** Catatan siswa belum terdaftar (dicatat manual)? */
    public static function manual(array $row): bool
    {
        return (int) ($row['manual'] ?? 0) === 1 && ($row['siswa_id'] ?? null) === null;
    }

    /** Isian "manual" dari formulir/aplikasi: true, 1, "1", "ya", "true", "on". */
    public static function mintaManual(mixed $v): bool
    {
        return $v === true || in_array(mb_strtolower(trim((string) $v)), ['1', 'ya', 'true', 'on'], true);
    }

    /** Nama siswa manual: spasi dirapikan, HURUF BESAR (seperti Data Siswa). */
    public static function namaManual(string $nama): string
    {
        return mb_strtoupper(IsianBantu::rapikan($nama));
    }

    /**
     * @param array{siswa_id?: mixed, tanggal?: mixed, jam?: mixed, guru_id?: mixed, sanksi?: mixed, keterangan?: mixed,
     *              manual?: mixed, nama_siswa?: mixed, kelas_id?: mixed, hp_ortu?: mixed} $data
     *        manual = ya → siswa belum terdaftar: nama_siswa* + kelas_id* + hp_ortu? (siswa_id diabaikan)
     * @param array{id?: ?int, nama?: string} $pencatat
     *
     * @return array{ok: bool, pesan: string, id?: int, kode?: int, errors?: array<string, string>, siswa_terdaftar?: array<string, mixed>}
     */
    public function catat(array $data, array $pencatat): array
    {
        $tanggal = self::tanggalSah((string) ($data['tanggal'] ?? ''));
        if ($tanggal === null) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Tanggal tidak sah atau masih di masa depan.'];
        }
        if (self::mintaManual($data['manual'] ?? null)) {
            return $this->catatManual($data, $tanggal, $pencatat);
        }
        $siswaId = (int) ($data['siswa_id'] ?? 0);
        $siswa   = $siswaId > 0 ? db_connect()->table('siswa s')
            ->select('s.id, s.nama, s.kelas_id, s.status, k.nama_kelas, k.shift')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.id', $siswaId)->where('s.deleted_at', null)
            ->get()->getRowArray() : null;
        if ($siswa === null) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Pilih siswa dari daftar hasil pencarian.'];
        }
        if ((string) $siswa['status'] !== 'aktif') {
            return ['ok' => false, 'kode' => 422, 'pesan' => $siswa['nama'] . ' bukan siswa aktif.'];
        }
        $isian = $this->isian($data);
        if (isset($isian['galat'])) {
            return ['ok' => false, 'kode' => 422, 'pesan' => $isian['galat']];
        }
        $ada = $this->model->where('siswa_id', $siswaId)->where('tanggal', $tanggal)->first();
        if ($ada !== null) {
            return ['ok' => false, 'kode' => 409, 'pesan' => $siswa['nama'] . ' sudah tercatat terlambat pada ' . self::hariTanggal($tanggal)
                . ($ada['jam'] !== null ? ' (jam ' . substr((string) $ada['jam'], 0, 5) . ')' : '') . '. Ubah catatan yang ada bila perlu.'];
        }

        $baris = $isian + [
            'tanggal'         => $tanggal,
            'siswa_id'        => $siswaId,
            'nama_siswa'      => (string) $siswa['nama'],
            'kelas_id'        => $siswa['kelas_id'] !== null ? (int) $siswa['kelas_id'] : null,
            'nama_kelas'      => $siswa['nama_kelas'] ?? null,
            'shift'           => in_array($siswa['shift'] ?? null, self::SHIFT, true) ? $siswa['shift'] : null,
            'tahun_ajaran'    => self::tahunAjaran() ?: null,
            'dicatat_oleh_id' => isset($pencatat['id']) ? (int) $pencatat['id'] : null,
            'dicatat_oleh'    => mb_substr((string) ($pencatat['nama'] ?? ''), 0, 150) ?: null,
        ];
        try {
            $ok = $this->model->insert($baris);
        } catch (\Throwable $e) {
            // Dua pencatat menyimpan siswa yang sama bersamaan → kunci unik siswa + tanggal menolak yang kedua.
            log_message('error', '[Keterlambatan] gagal mencatat: ' . $e->getMessage());
            $ok = false;
        }
        if (! $ok) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Catatan gagal disimpan (mungkin sudah dicatat orang lain barusan). Muat ulang halaman lalu periksa daftarnya.'];
        }
        $id = (int) $this->model->getInsertID();
        $this->audit('create', $id, 'Catat terlambat: ' . $siswa['nama'] . ' (' . ($siswa['nama_kelas'] ?? '-') . '), ' . $tanggal);

        return ['ok' => true, 'pesan' => $siswa['nama'] . ' (' . ($siswa['nama_kelas'] ?? 'tanpa kelas') . ') dicatat terlambat.', 'id' => $id];
    }

    /** Catat siswa BELUM TERDAFTAR (nama & kelas diketik). @see catat() */
    private function catatManual(array $data, string $tanggal, array $pencatat): array
    {
        $m = $this->periksaManual((string) ($data['nama_siswa'] ?? ''), (int) ($data['kelas_id'] ?? 0), (string) ($data['hp_ortu'] ?? ''));
        if (isset($m['galat'])) {
            return $this->tolakManual($m);
        }
        $isian = $this->isian($data);
        if (isset($isian['galat'])) {
            return ['ok' => false, 'kode' => 422, 'pesan' => $isian['galat']];
        }
        $ada = $this->kembarManual($m['nama'], (int) $m['kelas']['id'], $tanggal);
        if ($ada !== null) {
            return ['ok' => false, 'kode' => 409, 'pesan' => $m['nama'] . ' (' . $m['kelas']['nama_kelas'] . ') sudah tercatat terlambat pada ' . self::hariTanggal($tanggal)
                . ($ada['jam'] !== null ? ' (jam ' . substr((string) $ada['jam'], 0, 5) . ')' : '') . '. Ubah catatan yang ada bila perlu.'];
        }
        $baris = $isian + [
            'tanggal'         => $tanggal,
            'siswa_id'        => null,
            'manual'          => 1,
            'nama_siswa'      => $m['nama'],
            'kelas_id'        => (int) $m['kelas']['id'],
            'nama_kelas'      => (string) $m['kelas']['nama_kelas'],
            'shift'           => in_array($m['kelas']['shift'] ?? null, self::SHIFT, true) ? $m['kelas']['shift'] : null,
            'tahun_ajaran'    => self::tahunAjaran() ?: null,
            'hp_ortu'         => $m['hp'],
            'dicatat_oleh_id' => isset($pencatat['id']) ? (int) $pencatat['id'] : null,
            'dicatat_oleh'    => mb_substr((string) ($pencatat['nama'] ?? ''), 0, 150) ?: null,
        ];
        if (! $this->model->insert($baris)) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Catatan gagal disimpan. Muat ulang halaman lalu periksa daftarnya.'];
        }
        $id = (int) $this->model->getInsertID();
        $this->audit('create', $id, 'Catat terlambat (siswa belum terdaftar): ' . $m['nama'] . ' (' . $m['kelas']['nama_kelas'] . '), ' . $tanggal);

        return ['ok' => true, 'pesan' => $m['nama'] . ' (' . $m['kelas']['nama_kelas'] . ') dicatat terlambat — ditandai "belum terdaftar" di Data Siswa.', 'id' => $id];
    }

    /**
     * Periksa isian siswa manual: nama (huruf, 3–150), kelas (ada di daftar kelas), nomor HP orang tua (kosong / HP 08… sah), dan
     * nama itu BUKAN siswa aktif terdaftar di kelas yang sama ($cekTerdaftar; collation kolom tidak membedakan huruf besar/kecil).
     *
     * @return array{nama: string, kelas: array<string, mixed>, hp: ?string}|array{galat: string, kolom: string, siswa_terdaftar?: array<string, mixed>}
     */
    private function periksaManual(string $nama, int $kelasId, string $hp, bool $cekTerdaftar = true): array
    {
        $nama = self::namaManual($nama);
        if (mb_strlen($nama) < self::MIN_NAMA) {
            return ['galat' => 'Tulis nama lengkap siswa (minimal ' . self::MIN_NAMA . ' huruf).', 'kolom' => 'nama_siswa'];
        }
        if (mb_strlen($nama) > self::MAKS_NAMA) {
            return ['galat' => 'Nama siswa terlalu panjang (maks ' . self::MAKS_NAMA . ' huruf).', 'kolom' => 'nama_siswa'];
        }
        if (! IsianBantu::namaOrangSah($nama)) {
            return ['galat' => 'Nama siswa hanya boleh huruf (boleh titik, koma, petik, dan tanda hubung).', 'kolom' => 'nama_siswa'];
        }
        $kelas = $kelasId > 0 ? db_connect()->table('kelas')->select('id, nama_kelas, shift')->where('id', $kelasId)->where('deleted_at', null)->get()->getRowArray() : null;
        if ($kelas === null) {
            return ['galat' => 'Pilih kelas siswa.', 'kolom' => 'kelas_id'];
        }
        $hp = trim($hp);
        $n  = null;
        if ($hp !== '') {
            $n = IsianBantu::telepon($hp);
            if (! IsianBantu::teleponMurni($hp) || ! IsianBantu::hpSah($n)) {
                return ['galat' => 'Nomor HP orang tua tidak valid. Tulis nomor WhatsApp yang diawali 08, mis. 0812 3456 7890 (atau kosongkan).', 'kolom' => 'hp_ortu'];
            }
        }
        $terdaftar = $cekTerdaftar ? db_connect()->table('siswa')->select('id, nama, nis')
            ->where('deleted_at', null)->where('status', 'aktif')->where('kelas_id', (int) $kelas['id'])
            ->where('nama', $nama)->get()->getRowArray() : null;
        if ($terdaftar !== null) {
            return [
                'galat'           => $terdaftar['nama'] . ' sudah terdaftar di Data Siswa (' . $kelas['nama_kelas'] . ($terdaftar['nis'] ? ', NIS ' . $terdaftar['nis'] : '') . ') — cari lalu pilih dari hasil pencarian.',
                'kolom'           => 'nama_siswa',
                'siswa_terdaftar' => ['id' => (int) $terdaftar['id'], 'nama' => (string) $terdaftar['nama'], 'nis' => (string) ($terdaftar['nis'] ?? ''), 'kelas' => (string) $kelas['nama_kelas'], 'kelas_id' => (int) $kelas['id']],
            ];
        }

        return ['nama' => $nama, 'kelas' => $kelas, 'hp' => $n];
    }

    /** @param array{galat: string, kolom: string, siswa_terdaftar?: array<string, mixed>} $m */
    private function tolakManual(array $m): array
    {
        return ['ok' => false, 'kode' => 422, 'pesan' => $m['galat'], 'errors' => [$m['kolom'] => $m['galat']]]
            + (isset($m['siswa_terdaftar']) ? ['siswa_terdaftar' => $m['siswa_terdaftar']] : []);
    }

    /** Catatan manual lain dengan nama + kelas sama pada tanggal itu (selain $kecuali). */
    private function kembarManual(string $nama, int $kelasId, string $tanggal, int $kecuali = 0): ?array
    {
        $q = $this->model->where('tanggal', $tanggal)->where('manual', 1)->where('siswa_id', null)->where('kelas_id', $kelasId)->where('nama_siswa', $nama);
        if ($kecuali > 0) {
            $q->where('id !=', $kecuali);
        }

        return $q->first();
    }

    /**
     * Ubah jam, guru piket, sanksi, keterangan (siswa terdaftar & tanggal tetap). Catatan siswa BELUM TERDAFTAR: nama_siswa, kelas_id,
     * hp_ortu juga boleh diubah — hanya kunci yang dikirim (null = tidak diubah; hp_ortu '' = dikosongkan).
     *
     * @param array{jam?: mixed, guru_id?: mixed, sanksi?: mixed, keterangan?: mixed, nama_siswa?: mixed, kelas_id?: mixed, hp_ortu?: mixed} $data
     *
     * @return array{ok: bool, pesan: string}
     */
    public function ubah(int $id, array $data): array
    {
        $row = $this->cari($id);
        if ($row === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Catatan keterlambatan tidak ditemukan.'];
        }
        $isian = $this->isian($data);
        if (isset($isian['galat'])) {
            return ['ok' => false, 'kode' => 422, 'pesan' => $isian['galat']];
        }
        $teks = '';
        if (self::manual($row)) {
            $nama  = (string) ($data['nama_siswa'] ?? $row['nama_siswa']);
            $kelas = (int) ($data['kelas_id'] ?? $row['kelas_id'] ?? 0);
            // Nama & kelas tetap → cek "sudah terdaftar" dilewati: catatan lama tetap bisa diubah jam/sanksinya walau anaknya kini
            // sudah masuk Data Siswa (yang benar untuk itu: Tautkan).
            $m = $this->periksaManual($nama, $kelas, (string) ($data['hp_ortu'] ?? $row['hp_ortu'] ?? ''),
                self::namaManual($nama) !== (string) $row['nama_siswa'] || $kelas !== (int) $row['kelas_id']);
            if (isset($m['galat'])) {
                return $this->tolakManual(isset($m['siswa_terdaftar'])
                    ? ['galat' => $m['siswa_terdaftar']['nama'] . ' sudah terdaftar di Data Siswa (' . $m['siswa_terdaftar']['kelas'] . ') — pakai tombol Tautkan agar catatan ini menyatu dengan data siswanya.'] + $m
                    : $m);
            }
            if ($this->kembarManual($m['nama'], (int) $m['kelas']['id'], (string) $row['tanggal'], $id) !== null) {
                return ['ok' => false, 'kode' => 409, 'pesan' => $m['nama'] . ' (' . $m['kelas']['nama_kelas'] . ') sudah punya catatan lain pada ' . self::hariTanggal((string) $row['tanggal']) . '. Hapus salah satunya.'];
            }
            $isian += [
                'nama_siswa' => $m['nama'],
                'kelas_id'   => (int) $m['kelas']['id'],
                'nama_kelas' => (string) $m['kelas']['nama_kelas'],
                'shift'      => in_array($m['kelas']['shift'] ?? null, self::SHIFT, true) ? $m['kelas']['shift'] : null,
                'hp_ortu'    => $m['hp'],
            ];
            if ($m['nama'] !== $row['nama_siswa'] || (int) $m['kelas']['id'] !== (int) $row['kelas_id']) {
                $teks = ' → ' . $m['nama'] . ' (' . $m['kelas']['nama_kelas'] . ')';
            }
        }
        if (! $this->model->update($id, $isian)) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Perubahan gagal disimpan. Coba lagi.'];
        }
        $this->audit('update', $id, 'Ubah catatan terlambat: ' . $row['nama_siswa'] . $teks . ', ' . $row['tanggal']);

        return ['ok' => true, 'pesan' => 'Catatan ' . ($isian['nama_siswa'] ?? $row['nama_siswa']) . ' diperbarui.'];
    }

    /**
     * Tautkan catatan siswa BELUM TERDAFTAR ke siswa di Data Siswa (setelah anak itu dimasukkan): catatan menjadi catatan siswa
     * terdaftar — nama dari Data Siswa; kelas dari Data Siswa bila catatan itu tahun ajaran sekarang (catatan tahun lalu tetap kelas
     * lamanya). $semua = juga catatan manual lain dengan nama + kelas yang sama. Tanggal yang siswa itu sudah punya catatan dilewati.
     * Nomor HP orang tua dari catatan disimpan ke Data Siswa bila di sana masih kosong (aturan sama dengan "Isi no. ortu").
     *
     * @return array{ok: bool, pesan: string, kode?: int, jumlah?: int, ids?: list<int>, dilewati?: list<string>}
     */
    public function tautkan(int $id, int $siswaId, bool $semua = true): array
    {
        $row = $this->cari($id);
        if ($row === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Catatan keterlambatan tidak ditemukan.'];
        }
        if (! self::manual($row)) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Catatan ' . $row['nama_siswa'] . ' sudah memakai Data Siswa — tidak perlu ditautkan.'];
        }
        $siswa = $siswaId > 0 ? db_connect()->table('siswa s')->select('s.id, s.nama, s.kelas_id, k.nama_kelas, k.shift')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->where('s.id', $siswaId)->where('s.deleted_at', null)->get()->getRowArray() : null;
        if ($siswa === null) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Pilih siswa dari hasil pencarian Data Siswa.', 'errors' => ['siswa_id' => 'Pilih siswa dari hasil pencarian Data Siswa.']];
        }

        $q = $this->model->where('manual', 1)->where('siswa_id', null);
        if ($semua) {
            $q->where('nama_siswa', $row['nama_siswa'])->where('kelas_id', $row['kelas_id'] === null ? null : (int) $row['kelas_id']);
        } else {
            $q->where('id', $id);
        }
        $anggota = $q->orderBy('tanggal', 'ASC')->findAll();
        $sudah   = array_column($this->model->select('tanggal')->where('siswa_id', $siswaId)
            ->whereIn('tanggal', array_column($anggota, 'tanggal') ?: ['0000-00-00'])->findAll(), 'tanggal');
        $ta      = self::tahunAjaran();
        $ids     = [];
        $lewat   = [];
        $hp      = null;
        foreach ($anggota as $r) {
            if (in_array($r['tanggal'], $sudah, true)) {
                $lewat[] = (string) $r['tanggal'];

                continue;
            }
            $ubah = ['siswa_id' => $siswaId, 'manual' => 0, 'nama_siswa' => (string) $siswa['nama']];
            if ($siswa['kelas_id'] !== null && (($r['tahun_ajaran'] ?? '') === '' || $r['tahun_ajaran'] === $ta)) {
                $ubah += ['kelas_id' => (int) $siswa['kelas_id'], 'nama_kelas' => $siswa['nama_kelas'],
                    'shift' => in_array($siswa['shift'] ?? null, self::SHIFT, true) ? $siswa['shift'] : null];
            }
            try {
                $ok = $this->model->update((int) $r['id'], $ubah);
            } catch (\Throwable $e) {
                log_message('error', '[Keterlambatan] gagal menautkan: ' . $e->getMessage());
                $ok = false;
            }
            if (! $ok) {
                $lewat[] = (string) $r['tanggal'];

                continue;
            }
            $ids[] = (int) $r['id'];
            $hp    = ($r['hp_ortu'] ?? '') !== '' ? (string) $r['hp_ortu'] : $hp; // anggota urut tanggal → nomor terakhir yang diisi
            $this->audit('update', (int) $r['id'], 'Tautkan catatan terlambat (belum terdaftar) ' . $r['nama_siswa'] . ' → Data Siswa ' . $siswa['nama'] . ', ' . $r['tanggal']);
        }
        $tglLewat = implode(', ', array_map(static fn (string $t): string => date('d-m-Y', strtotime($t)), $lewat));
        if ($ids === []) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Tidak ada catatan yang ditautkan: ' . $siswa['nama'] . ' sudah punya catatan terlambat pada tanggal yang sama ('
                . $tglLewat . '). Hapus salah satu catatan dulu.'];
        }
        $pesan = count($ids) . ' catatan ditautkan ke ' . $siswa['nama'] . ' (' . ($siswa['nama_kelas'] ?? 'tanpa kelas') . ') di Data Siswa.'
            . ($lewat !== [] ? ' ' . count($lewat) . ' dilewati karena siswa itu sudah punya catatan pada ' . $tglLewat . '.' : '');
        if ($hp !== null && (new KeterlambatanWa())->isiHp($ids[0], $hp, $this->saluran)['ok']) {
            $pesan .= ' Nomor HP orang tua ' . $hp . ' disimpan ke Data Siswa.';
        }

        return ['ok' => true, 'pesan' => $pesan, 'jumlah' => count($ids), 'ids' => $ids, 'dilewati' => $lewat];
    }

    /** @return array{ok: bool, pesan: string, tanggal?: string} */
    public function hapus(int $id): array
    {
        $row = $this->cari($id);
        if ($row === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Catatan keterlambatan tidak ditemukan.'];
        }
        $this->model->delete($id);
        $this->audit('delete', $id, 'Hapus catatan terlambat: ' . $row['nama_siswa'] . ' (' . ($row['nama_kelas'] ?? '-') . '), ' . $row['tanggal']);

        return ['ok' => true, 'pesan' => 'Catatan ' . $row['nama_siswa'] . ' dihapus.', 'tanggal' => (string) $row['tanggal']];
    }

    /**
     * Isian bersama catat & ubah: jam, guru piket, sanksi, keterangan. ['galat' => …] bila tidak sah.
     *
     * @return array<string, mixed>
     */
    private function isian(array $data): array
    {
        $jam = self::jamSah(isset($data['jam']) ? (string) $data['jam'] : null);
        if ($jam === false) {
            return ['galat' => 'Jam datang tidak sah. Tulis seperti 07:15.'];
        }
        $guruId = (int) ($data['guru_id'] ?? 0);
        $guru   = $guruId > 0 ? (new GuruModel())->select('id, nama')->find($guruId) : null;
        if ($guru === null) {
            return ['galat' => 'Pilih guru piket yang mencatat.'];
        }
        $sanksi     = IsianBantu::rapikan((string) ($data['sanksi'] ?? ''));
        $keterangan = IsianBantu::rapikan((string) ($data['keterangan'] ?? ''));
        if (mb_strlen($sanksi) > self::MAKS_SANKSI) {
            return ['galat' => 'Sanksi terlalu panjang (maks ' . self::MAKS_SANKSI . ' huruf).'];
        }
        if (mb_strlen($keterangan) > self::MAKS_KETERANGAN) {
            return ['galat' => 'Keterangan terlalu panjang (maks ' . self::MAKS_KETERANGAN . ' huruf).'];
        }

        return [
            'jam'        => $jam,
            'guru_id'    => (int) $guru['id'],
            'guru_piket' => mb_substr((string) $guru['nama'], 0, 150),
            'sanksi'     => $sanksi !== '' ? $sanksi : null,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
        ];
    }
}
