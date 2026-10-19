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
 *   - guru piket WAJIB dipilih (akun Guru Piket dipakai bersama) — saran = jadwal piket hari itu (Jadwal Piket), boleh guru lain;
 *   - sanksi & keterangan = teks bebas guru piket; jam datang boleh kosong;
 *   - siswa & tanggal tidak bisa diubah (salah pilih → hapus lalu catat ulang).
 * Pencatat ($pencatat) = ['id' => ?int admins.id, 'nama' => string] — web dari sesi, API dari token.
 */
final class Keterlambatan
{
    public const SHIFT = ['pagi', 'siang'];
    public const HARI  = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu', 'Minggu'];

    public const MAKS_SANKSI     = 150;
    public const MAKS_KETERANGAN = 255;

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
     *
     * @return list<array{id: int, nama: string, nis: string, kelas: string, shift: ?string, tercatat: ?string}>
     */
    public function cariSiswa(string $q, string $tanggal, int $batas = 15): array
    {
        $q = IsianBantu::rapikan($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $db   = db_connect();
        $rows = $db->table('siswa s')
            ->select('s.id, s.nama, s.nis, k.nama_kelas, k.shift')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->groupStart()
                ->like('s.nama', $q)
                ->orLike('s.nis', $q, 'after')
                ->orLike('s.nisn', $q, 'after')
            ->groupEnd()
            ->orderBy('s.nama', 'ASC')
            ->limit(max(1, min($batas, 50)))
            ->get()->getResultArray();
        if ($rows === []) {
            return [];
        }
        $sudah = [];
        foreach ($this->model->select('siswa_id, jam')->where('tanggal', $tanggal)->whereIn('siswa_id', array_column($rows, 'id'))->findAll() as $r) {
            $sudah[(int) $r['siswa_id']] = $r['jam'] !== null ? 'jam ' . substr((string) $r['jam'], 0, 5) : 'sudah dicatat';
        }

        return array_map(static fn (array $r): array => [
            'id'       => (int) $r['id'],
            'nama'     => (string) $r['nama'],
            'nis'      => (string) ($r['nis'] ?? ''),
            'kelas'    => (string) ($r['nama_kelas'] ?? ''),
            'shift'    => $r['shift'] ?? null,
            'tercatat' => $sudah[(int) $r['id']] ?? null,
        ], $rows);
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
     * @return array{siswa: list<array{nama: string, nis: string, kelas: string, jumlah: int, tanggal: list<string>, terakhir: string}>,
     *               kelas: list<array{kelas: string, jumlah: int, siswa: int}>, total: int, jumlah_siswa: int, jumlah_hari: int}
     */
    public function rekap(string $mulai, string $selesai, int $kelasId = 0): array
    {
        $rows  = $this->rentang($mulai, $selesai, $kelasId);
        $siswa = [];
        $kelas = [];
        $hari  = [];
        foreach ($rows as $r) {
            $kunci = $r['siswa_id'] !== null ? 'i' . $r['siswa_id'] : 'n' . mb_strtolower((string) $r['nama_siswa']);
            $nk    = (string) ($r['nama_kelas'] ?? '') ?: 'Tanpa kelas';
            $siswa[$kunci] ??= ['nama' => (string) $r['nama_siswa'], 'nis' => (string) ($r['nis'] ?? ''), 'kelas' => $nk, 'jumlah' => 0, 'tanggal' => [], 'terakhir' => ''];
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

    /** @return list<array{id: int, nama: string}> kelas aktif untuk saringan rekap */
    public function pilihanKelas(): array
    {
        return array_map(static fn (array $k): array => ['id' => (int) $k['id'], 'nama' => (string) $k['nama_kelas']],
            db_connect()->table('kelas')->select('id, nama_kelas')->where('deleted_at', null)->orderBy('tingkat', 'ASC')->orderBy('nama_kelas', 'ASC')->get()->getResultArray());
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

    /**
     * @param array{siswa_id?: mixed, tanggal?: mixed, jam?: mixed, guru_id?: mixed, sanksi?: mixed, keterangan?: mixed} $data
     * @param array{id?: ?int, nama?: string} $pencatat
     *
     * @return array{ok: bool, pesan: string, id?: int}
     */
    public function catat(array $data, array $pencatat): array
    {
        $tanggal = self::tanggalSah((string) ($data['tanggal'] ?? ''));
        if ($tanggal === null) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Tanggal tidak sah atau masih di masa depan.'];
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

    /**
     * Ubah jam, guru piket, sanksi, keterangan (siswa & tanggal tetap).
     *
     * @param array{jam?: mixed, guru_id?: mixed, sanksi?: mixed, keterangan?: mixed} $data
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
        if (! $this->model->update($id, $isian)) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Perubahan gagal disimpan. Coba lagi.'];
        }
        $this->audit('update', $id, 'Ubah catatan terlambat: ' . $row['nama_siswa'] . ', ' . $row['tanggal']);

        return ['ok' => true, 'pesan' => 'Catatan ' . $row['nama_siswa'] . ' diperbarui.'];
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
