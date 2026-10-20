<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Penulisan ajuan PKL yang melibatkan banyak tabel (ajuan + anggota + riwayat)
 * — SEMUANYA dalam satu transaksi: ajuan tak pernah tersimpan setengah jadi.
 *
 * Inilah satu-satunya tempat yang menyentuh pkl_anggota.siswa_aktif, kolom
 * penjaga "satu siswa satu ajuan aktif" (UNIQUE di database):
 *   siswa_aktif = siswa_id  selama status ajuan menunggu/perbaikan/disetujui
 *   siswa_aktif = NULL      bila ditolak
 * Tabrakan (siswa sudah aktif di ajuan lain — termasuk dua HP yang mengirim di
 * detik yang sama) ditolak DATABASE, lalu dilaporkan sebagai kode 'bentrok'.
 *
 * Semua metode mengembalikan array:
 *   ['ok' => true,  'id' => int, ...]
 *   ['ok' => false, 'kode' => 'bentrok'|'status'|'tidak_ada'|'pengaju_beda'|'galat', ...]
 *
 * $anggota = daftar baris anggota, PENGAJU PERTAMA:
 *   [['siswa_id' => int, 'kelas_id' => ?int, 'peran' => 'pengaju'|'teman', 'hp' => ?string (teman)], ...]
 *   HP pengaju diambil dari $data['hp'].
 * $data    = keluaran PklForm::proses() (kunci perusahaan_*, kontak_*, hp; tanggal_* hanya riwayat lama).
 * $konteks = ['oleh' => 'Siswa: Nama', 'admin_id' => ?int, 'peran' => ?string, 'ip' => ?string,
 *             'sumber' => 'siswa'|'staf'|'impor', 'tahun_ajaran' => ?string, 'aksi' => ?string].
 *
 * CATATAN ACC: tiap kali status menjadi `disetujui`, kolom acc_* (nama, peran, jam, IP, kode verifikasi)
 * disalin SEKETIKA dari $konteks — tak ikut berubah bila akunnya kelak diganti namanya — dan dikosongkan
 * lagi saat persetujuan dicabut. Dicetak di kaki surat. Siapa yang BERHAK meng-ACC dijaga di lapisan
 * controller/API (HakAkses::bolehAcc), bukan di sini.
 */
final class PklAjuan
{
    /**
     * Panjang maksimum alasan/catatan yang disimpan (pkl_pengajuan.catatan_staf & pkl_riwayat.catatan, 1000 huruf sejak migrasi
     * 2026-10-25-000001). Server yang belum migrasi memotong sendiri di 255 (lihat maksCatatan()).
     */
    public const MAKS_CATATAN = 1000;

    private static ?bool $kolomTolakSumber = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Kolom pkl_pengajuan.tolak_sumber ada? (migrasi 2026-10-25-000001) */
    public static function kolomTolakSumber(?BaseConnection $db = null): bool
    {
        if (self::$kolomTolakSumber === null) {
            try {
                self::$kolomTolakSumber = ($db ?? db_connect())->fieldExists('tolak_sumber', 'pkl_pengajuan');
            } catch (\Throwable $e) {
                self::$kolomTolakSumber = false;
            }
        }

        return self::$kolomTolakSumber;
    }

    /** Batas huruf alasan yang benar-benar muat di database ini (1000 setelah migrasi, 255 sebelumnya). */
    public static function maksCatatan(): int
    {
        return PklKeluar::tabelAda() ? self::MAKS_CATATAN : 255;
    }

    /**
     * GABUNGKAN ajuan $asalId ke $tujuanId (perusahaan sama; dicek pemanggil PklKeluar::gabungkan). Dalam SATU transaksi:
     * seluruh anggota asal dicatat di pkl_anggota_keluar (jenis 'pindah' → riwayat, bukan penolakan), dipindah ke ajuan tujuan
     * sebagai teman (HP & kelas ikut), lalu ajuan asal ditutup: status 'ditolak' + tolak_sumber 'gabung' + catatan "Digabung ke …".
     * Riwayat kedua ajuan mencatatnya. Surat Izin tujuan otomatis "perlu cetak ulang" (nomor tetap).
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, kode?: string, nama?: list<string>}
     */
    public function gabungkan(int $asalId, int $tujuanId, string $alasan, array $konteks): array
    {
        return $this->transaksi(function () use ($asalId, $tujuanId, $alasan, $konteks): array {
            $now   = date('Y-m-d H:i:s');
            $baris = [];
            foreach ($this->db->query('SELECT id, status FROM pkl_pengajuan WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [$asalId, $tujuanId])->getResultArray() as $r) {
                $baris[(int) $r['id']] = (string) $r['status'];
            }
            if (! isset($baris[$asalId], $baris[$tujuanId])) {
                return ['ok' => false, 'kode' => 'tidak_ada'];
            }
            if (! PklPengajuanModel::aktif($baris[$asalId]) || ! PklPengajuanModel::aktif($baris[$tujuanId])) {
                return ['ok' => false, 'kode' => 'status'];
            }
            $ada = $this->db->query(
                'SELECT a.id, a.siswa_id, a.peran, a.kelas_id, a.hp, s.nama FROM pkl_anggota a JOIN siswa s ON s.id = a.siswa_id'
                . ' WHERE a.pengajuan_id = ? ORDER BY (a.peran = \'pengaju\') DESC, a.id ASC',
                [$asalId]
            )->getResultArray();
            if ($ada === []) {
                return ['ok' => false, 'kode' => 'kosong'];
            }
            $kodeTujuan = PklPengajuanModel::kode($tujuanId);
            $kodeAsal   = PklPengajuanModel::kode($asalId);
            $catatanK   = mb_substr('Digabung ke ' . $kodeTujuan . ' (perusahaan sama)' . ($alasan !== '' ? ': ' . $alasan : ''), 0, self::MAKS_CATATAN);
            $aktifTujuan = PklPengajuanModel::aktif($baris[$tujuanId]);
            foreach ($ada as $a) {
                $k = $this->sisip('pkl_anggota_keluar', [
                    'pengajuan_id' => $asalId, 'siswa_id' => (int) $a['siswa_id'], 'kelas_id' => $a['kelas_id'] !== null ? (int) $a['kelas_id'] : null,
                    'peran' => (string) $a['peran'], 'hp' => $a['hp'], 'jenis' => 'pindah', 'alasan' => $catatanK, 'status_ajuan' => $baris[$asalId],
                    'sumber' => 'staf', 'oleh' => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150), 'admin_id' => $konteks['admin_id'] ?? null,
                    'peran_staf' => ($konteks['peran'] ?? '') !== '' ? mb_substr((string) $konteks['peran'], 0, 20) : null,
                    'ip_address' => $konteks['ip'] ?? null, 'created_at' => $now,
                ]);
                if (! $k['ok'] || ! $this->jalankan('DELETE FROM pkl_anggota WHERE id = ?', [(int) $a['id']])['ok']) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
                $gagal = $this->sisipAnggota($tujuanId, ['siswa_id' => (int) $a['siswa_id'], 'kelas_id' => $a['kelas_id'] !== null ? (int) $a['kelas_id'] : null,
                    'peran' => 'teman', 'hp' => $a['hp']], [], $now, $aktifTujuan);
                if ($gagal !== null) {
                    return $gagal;
                }
            }
            $nama = array_column($ada, 'nama');
            $ok   = $this->jalankan(
                'UPDATE pkl_pengajuan SET status = \'ditolak\', catatan_staf = ?, diputuskan_at = ?, diputuskan_oleh = ?, updated_at = ?,'
                . ' acc_admin_id = NULL, acc_nama = NULL, acc_peran = NULL, acc_at = NULL, acc_ip = NULL, acc_kode = NULL'
                . (self::kolomTolakSumber($this->db) ? ', tolak_sumber = \'gabung\'' : '') . ' WHERE id = ?',
                [mb_substr('Digabung ke ' . $kodeTujuan . ($alasan !== '' ? ': ' . $alasan : ''), 0, self::maksCatatan()), $now, $konteks['admin_id'] ?? null, $now, $asalId]
            )['ok'] && $this->jalankan('UPDATE pkl_pengajuan SET updated_at = ? WHERE id = ?', [$now, $tujuanId])['ok'];

            return $ok && $this->catat($asalId, 'gabung', $konteks, 'Digabung ke ' . $kodeTujuan . ': ' . implode(', ', $nama) . ($alasan !== '' ? ' — ' . $alasan : ''))
                && $this->catat($tujuanId, 'gabung_masuk', $konteks, 'Menerima siswa dari ' . $kodeAsal . ': ' . implode(', ', $nama))
                ? ['ok' => true, 'nama' => $nama]
                : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * Keluarkan satu/lebih siswa dari ajuan AKTIF (menunggu/perbaikan/disetujui) — revisi klien 2026-10-20: siswa ditolak perusahaan,
     * administrasi belum lunas, pindah kelompok, mengundurkan diri. Dalam SATU transaksi:
     *   1. salinan barisnya (peran, kelas saat mengajukan, HP) + jenis + alasan + siapa/kapan disimpan di pkl_anggota_keluar (riwayat);
     *   2. baris pkl_anggota dihapus → kunci siswa_aktif lepas, siswa langsung bebas mengajukan baru;
     *   3. bila PENGAJU ikut dikeluarkan, anggota tersisa yang paling awal ditambahkan menjadi pengaju (HP-nya sudah ada di barisnya);
     *   4. riwayat ajuan mencatat nama-nama + alasan.
     * Minimal satu siswa harus tersisa (kode 'semua') — bila semua ditolak, ajuan ditolak lewat ubahStatus(..., ['sumber' => 'perusahaan']).
     * Siapa yang BERHAK dijaga pemanggil (PklKeluar / PklLapor), bukan di sini.
     *
     * @param list<int>            $siswaIds
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip', 'sumber' => 'staf'|'lapor']
     *
     * @return array{ok: bool, kode?: string, keluar?: list<array{id: int, siswa_id: int, nama: string}>, pengaju_baru?: ?string, status?: string}
     */
    public function keluarkan(int $id, array $siswaIds, string $jenis, string $alasan, array $konteks): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x): bool => $x > 0)));
        if ($siswaIds === []) {
            return ['ok' => false, 'kode' => 'kosong'];
        }

        return $this->transaksi(function () use ($id, $siswaIds, $jenis, $alasan, $konteks): array {
            $now = date('Y-m-d H:i:s');
            $row = $this->db->query('SELECT id, status FROM pkl_pengajuan WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($row === null) {
                return ['ok' => false, 'kode' => 'tidak_ada'];
            }
            if (! PklPengajuanModel::aktif((string) $row['status'])) {
                return ['ok' => false, 'kode' => 'status', 'status' => (string) $row['status']];
            }
            $ada = $this->db->query(
                'SELECT a.id, a.siswa_id, a.peran, a.kelas_id, a.hp, s.nama FROM pkl_anggota a JOIN siswa s ON s.id = a.siswa_id'
                . ' WHERE a.pengajuan_id = ? ORDER BY (a.peran = \'pengaju\') DESC, a.id ASC',
                [$id]
            )->getResultArray();
            $peta = [];
            foreach ($ada as $a) {
                $peta[(int) $a['siswa_id']] = $a;
            }
            foreach ($siswaIds as $sid) {
                if (! isset($peta[$sid])) {
                    return ['ok' => false, 'kode' => 'bukan_anggota'];
                }
            }
            if (count($siswaIds) >= count($ada)) {
                return ['ok' => false, 'kode' => 'semua'];
            }

            $keluar        = [];
            $pengajuKeluar = false;
            foreach ($siswaIds as $sid) {
                $a = $peta[$sid];
                $r = $this->sisip('pkl_anggota_keluar', [
                    'pengajuan_id' => $id, 'siswa_id' => $sid, 'kelas_id' => $a['kelas_id'] !== null ? (int) $a['kelas_id'] : null,
                    'peran' => (string) $a['peran'], 'hp' => $a['hp'], 'jenis' => $jenis, 'alasan' => mb_substr($alasan, 0, self::MAKS_CATATAN),
                    'status_ajuan' => (string) $row['status'], 'sumber' => ($konteks['sumber'] ?? 'staf') === 'lapor' ? 'lapor' : 'staf',
                    'oleh' => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150), 'admin_id' => $konteks['admin_id'] ?? null,
                    'peran_staf' => ($konteks['peran'] ?? '') !== '' ? mb_substr((string) $konteks['peran'], 0, 20) : null,
                    'ip_address' => $konteks['ip'] ?? null, 'created_at' => $now,
                ]);
                if (! $r['ok']) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
                if (! $this->jalankan('DELETE FROM pkl_anggota WHERE id = ?', [(int) $a['id']])['ok']) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
                $pengajuKeluar = $pengajuKeluar || $a['peran'] === 'pengaju';
                $keluar[]      = ['id' => $r['id'], 'siswa_id' => $sid, 'nama' => (string) $a['nama']];
            }

            $pengajuBaru = null;
            if ($pengajuKeluar) {
                foreach ($ada as $a) {
                    if (! in_array((int) $a['siswa_id'], $siswaIds, true)) {
                        if (! $this->jalankan("UPDATE pkl_anggota SET peran = 'pengaju' WHERE id = ?", [(int) $a['id']])['ok']) {
                            return ['ok' => false, 'kode' => 'galat'];
                        }
                        $pengajuBaru = (string) $a['nama'];
                        break;
                    }
                }
            }
            if (! $this->jalankan('UPDATE pkl_pengajuan SET updated_at = ? WHERE id = ?', [$now, $id])['ok']) {
                return ['ok' => false, 'kode' => 'galat'];
            }

            $teks = 'Dikeluarkan (' . PklKeluar::labelJenis($jenis) . '): ' . implode(', ', array_column($keluar, 'nama'))
                . ($pengajuBaru !== null ? '; pengaju kini ' . $pengajuBaru : '') . ' — ' . $alasan;

            return $this->catat($id, $konteks['aksi'] ?? 'keluarkan', $konteks, $teks)
                ? ['ok' => true, 'keluar' => $keluar, 'pengaju_baru' => $pengajuBaru, 'status' => (string) $row['status']]
                : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * Kode verifikasi ACC, mis. PKL-00522-8F3A9C: nomor bukti + 6 huruf-angka tanda tangan HMAC dari
     * (ajuan, jam ACC, akun penyetuju). Tidak bisa ditebak tanpa kunci aplikasi; staf bisa mencocokkannya
     * dengan yang tertera di halaman detail.
     */
    public static function kodeVerifikasi(int $id, string $accAt, ?int $adminId): string
    {
        $kunci = (string) (env('encryption.key') ?: 'pkl-bina-nusa');

        return PklPengajuanModel::kode($id) . '-' . strtoupper(substr(hash_hmac('sha256', $id . '|' . $accAt . '|' . (int) $adminId, $kunci), 0, 6));
    }

    /**
     * Kolom acc_* untuk ajuan yang BARU disetujui.
     *
     * @return array<string, mixed>
     */
    private function kolomAcc(int $id, array $konteks, string $now): array
    {
        $adminId = isset($konteks['admin_id']) ? ((int) $konteks['admin_id'] ?: null) : null;
        $peran   = ($konteks['sumber'] ?? '') === 'impor' ? 'impor' : (string) ($konteks['peran'] ?? '');

        return [
            'acc_admin_id' => $adminId,
            'acc_nama'     => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150),
            'acc_peran'    => $peran !== '' ? mb_substr($peran, 0, 20) : null,
            'acc_at'       => $now,
            'acc_ip'       => $konteks['ip'] ?? null,
            'acc_kode'     => self::kodeVerifikasi($id, $now, $adminId),
        ];
    }

    /** @return array<string, null> semua kolom acc_* dikosongkan */
    private static function kosongAcc(): array
    {
        return ['acc_admin_id' => null, 'acc_nama' => null, 'acc_peran' => null, 'acc_at' => null, 'acc_ip' => null, 'acc_kode' => null];
    }

    /**
     * Ajuan baru dari siswa (atau staf atas nama siswa). Status awal `menunggu`;
     * $konteks['status_awal'] = 'disetujui' dipakai staf untuk riwayat PKL yang sudah
     * pasti (perusahaan langsung didaftarkan/ditautkan ke master).
     */
    public function kirimBaru(array $data, array $anggota, array $konteks): array
    {
        return $this->transaksi(function () use ($data, $anggota, $konteks): array {
            $now        = date('Y-m-d H:i:s');
            $statusAwal = ($konteks['status_awal'] ?? 'menunggu') === 'disetujui' ? 'disetujui' : 'menunggu';

            $perusahaanId = null;
            if ($statusAwal === 'disetujui') {
                $perusahaanId = $this->tautkanPerusahaan($data, isset($konteks['perusahaan_id']) ? (int) $konteks['perusahaan_id'] : null);
                if ($perusahaanId === null) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
            }

            $r = $this->sisip('pkl_pengajuan', $this->barisAjuan($data) + [
                'status'          => $statusAwal,
                'sumber'          => $konteks['sumber'] ?? 'siswa',
                'tahun_ajaran'    => $konteks['tahun_ajaran'] ?? null,
                'perusahaan_id'   => $perusahaanId,
                'kirim_ke'        => 1,
                'ip_address'      => $konteks['ip'] ?? null,
                'diputuskan_at'   => $statusAwal === 'disetujui' ? $now : null,
                'diputuskan_oleh' => $statusAwal === 'disetujui' ? ($konteks['admin_id'] ?? null) : null,
                'diajukan_at'     => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            if (! $r['ok']) {
                return ['ok' => false, 'kode' => 'galat'];
            }
            $id = $r['id'];

            if ($statusAwal === 'disetujui') {
                $up = $this->db->table('pkl_pengajuan')->where('id', $id)->update($this->kolomAcc($id, $konteks, $now));
                if (! $up) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
            }

            foreach ($anggota as $m) {
                $gagal = $this->sisipAnggota($id, $m, $data, $now);
                if ($gagal !== null) {
                    return $gagal;
                }
            }

            return $this->catat($id, $konteks['aksi'] ?? 'kirim', $konteks, null) ? ['ok' => true, 'id' => $id] : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * Pengaju mengirim ulang ajuan yang dikembalikan staf (status `perbaikan`):
     * isi ajuan diganti, daftar teman disesuaikan, status kembali `menunggu`,
     * catatan staf dikosongkan (jejaknya tetap di pkl_riwayat).
     */
    public function kirimUlang(int $id, array $data, array $anggota, array $konteks): array
    {
        return $this->transaksi(function () use ($id, $data, $anggota, $konteks): array {
            $now = date('Y-m-d H:i:s');

            // Kunci barisnya: dua pengiriman ulang bersamaan jadi antre, bukan bertabrakan.
            $row = $this->db->query('SELECT id, status FROM pkl_pengajuan WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($row === null) {
                return ['ok' => false, 'kode' => 'tidak_ada'];
            }
            if ($row['status'] !== 'perbaikan') {
                return ['ok' => false, 'kode' => 'status', 'status' => $row['status']];
            }

            $ada = $this->db->query('SELECT id, siswa_id, peran FROM pkl_anggota WHERE pengajuan_id = ?', [$id])->getResultArray();
            $pengajuLama = null;
            foreach ($ada as $a) {
                if ($a['peran'] === 'pengaju') {
                    $pengajuLama = (int) $a['siswa_id'];
                }
            }
            if ($pengajuLama === null || $pengajuLama !== (int) ($anggota[0]['siswa_id'] ?? 0)) {
                return ['ok' => false, 'kode' => 'pengaju_beda'];
            }

            $r = $this->jalankan(
                'UPDATE pkl_pengajuan SET status = ?, kirim_ke = kirim_ke + 1, catatan_staf = NULL,'
                . ' diputuskan_at = NULL, diputuskan_oleh = NULL, diajukan_at = ?, ip_address = ?, updated_at = ?,'
                . ' acc_admin_id = NULL, acc_nama = NULL, acc_peran = NULL, acc_at = NULL, acc_ip = NULL, acc_kode = NULL,'
                . ' perusahaan_nama = ?, perusahaan_norm = ?, perusahaan_alamat = ?, perusahaan_kota = ?,'
                . ' perusahaan_telepon = ?, kontak_nama = ?, kontak_jabatan = ?'
                . ' WHERE id = ?',
                [
                    'menunggu', $now, $konteks['ip'] ?? null, $now,
                    $data['perusahaan_nama'], $data['perusahaan_norm'], $data['perusahaan_alamat'], $data['perusahaan_kota'],
                    $data['perusahaan_telepon'], $data['kontak_nama'], $data['kontak_jabatan'], $id,
                ]
            );
            if (! $r['ok']) {
                return ['ok' => false, 'kode' => 'galat'];
            }

            // Anggota: hapus yang dikeluarkan, perbarui yang tetap, tambah yang baru.
            $baruIds = array_map(static fn (array $m) => (int) $m['siswa_id'], $anggota);
            $petaAda = [];
            foreach ($ada as $a) {
                $petaAda[(int) $a['siswa_id']] = (int) $a['id'];
                if (! in_array((int) $a['siswa_id'], $baruIds, true)) {
                    $hapus = $this->jalankan('DELETE FROM pkl_anggota WHERE id = ?', [(int) $a['id']]);
                    if (! $hapus['ok']) {
                        return ['ok' => false, 'kode' => 'galat'];
                    }
                }
            }
            foreach ($anggota as $m) {
                $sid = (int) $m['siswa_id'];
                if (isset($petaAda[$sid])) {
                    $pengaju = ($m['peran'] ?? 'teman') === 'pengaju';
                    $up      = $this->jalankan(
                        'UPDATE pkl_anggota SET peran = ?, kelas_id = ?, hp = ? WHERE id = ?',
                        [
                            $m['peran'] ?? 'teman', $m['kelas_id'] ?? null,
                            $pengaju ? ($data['hp'] ?? null) : ($m['hp'] ?? null), $petaAda[$sid],
                        ]
                    );
                    if (! $up['ok']) {
                        return ['ok' => false, 'kode' => 'galat'];
                    }
                    continue;
                }
                $gagal = $this->sisipAnggota($id, $m, $data, $now);
                if ($gagal !== null) {
                    return $gagal;
                }
            }

            return $this->catat($id, $konteks['aksi'] ?? 'kirim_ulang', $konteks, null) ? ['ok' => true, 'id' => $id] : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * Ubah status ajuan + sinkronkan kunci siswa_aktif seluruh anggotanya.
     * Dipakai tombol staf (ACC / kembalikan / tolak / batalkan persetujuan).
     *
     * Bila status baru AKTIF tetapi ada anggota yang sudah aktif di ajuan lain
     * (mis. ajuan ditolak lalu siswanya mengajukan lagi, kini ajuan lama hendak
     * dihidupkan), perubahan DITOLAK dengan kode 'bentrok' + daftar `siswa_ids`.
     *
     * Saat status baru `disetujui`, perusahaan ajuan didaftarkan ke master (pkl_perusahaan)
     * atau ditautkan ke yang sudah ada: $opsi['perusahaan_id'] = id master pilihan staf,
     * kosong = otomatis (nama pembanding sama → pakai yang ada, selain itu buat baru).
     *
     * @return array{ok: bool, kode?: string, status_lama?: string, siswa_ids?: list<int>}
     */
    public function ubahStatus(int $id, string $status, array $konteks, ?string $catatan = null, array $opsi = []): array
    {
        if (! in_array($status, PklPengajuanModel::STATUS, true)) {
            return ['ok' => false, 'kode' => 'galat'];
        }

        return $this->transaksi(function () use ($id, $status, $konteks, $catatan, $opsi): array {
            $now = date('Y-m-d H:i:s');
            $row = $this->db->query('SELECT * FROM pkl_pengajuan WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($row === null) {
                return ['ok' => false, 'kode' => 'tidak_ada'];
            }

            $perusahaanId = $row['perusahaan_id'] !== null ? (int) $row['perusahaan_id'] : null;
            if ($status === 'disetujui') {
                $perusahaanId = $this->tautkanPerusahaan($row, isset($opsi['perusahaan_id']) ? (int) $opsi['perusahaan_id'] : null);
                if ($perusahaanId === null) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
            }

            if (PklPengajuanModel::aktif($status)) {
                $bentrok = $this->db->query(
                    'SELECT a.siswa_id FROM pkl_anggota a'
                    . ' JOIN pkl_anggota b ON b.siswa_aktif = a.siswa_id AND b.pengajuan_id <> a.pengajuan_id'
                    . ' WHERE a.pengajuan_id = ?',
                    [$id]
                )->getResultArray();
                if ($bentrok !== []) {
                    return ['ok' => false, 'kode' => 'bentrok', 'siswa_ids' => array_map('intval', array_column($bentrok, 'siswa_id'))];
                }
            }

            $putus = $status !== 'menunggu';
            $r     = $this->jalankan(
                'UPDATE pkl_pengajuan SET status = ?, catatan_staf = ?, perusahaan_id = ?, diputuskan_at = ?, diputuskan_oleh = ?, updated_at = ? WHERE id = ?',
                [
                    $status, $catatan !== null ? mb_substr($catatan, 0, self::maksCatatan()) : null, $perusahaanId,
                    $putus ? $now : null, $putus ? ($konteks['admin_id'] ?? null) : null, $now, $id,
                ]
            );
            if (! $r['ok']) {
                return ['ok' => false, 'kode' => 'galat'];
            }
            // Sumber penolakan: 'perusahaan' hanya untuk "Ditolak perusahaan"; perubahan status lain mengosongkannya lagi.
            if (self::kolomTolakSumber($this->db)) {
                $sumber = $status === 'ditolak' && ($opsi['sumber'] ?? '') === 'perusahaan' ? 'perusahaan' : null;
                if (! $this->db->table('pkl_pengajuan')->where('id', $id)->update(['tolak_sumber' => $sumber])) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
            }

            // Catatan ACC: terisi bila disetujui, dikosongkan bila status lain (persetujuan dicabut/ditolak).
            $acc = $status === 'disetujui' ? $this->kolomAcc($id, $konteks, $now) : self::kosongAcc();
            if (! $this->db->table('pkl_pengajuan')->where('id', $id)->update($acc)) {
                return ['ok' => false, 'kode' => 'galat'];
            }

            $sync = $this->jalankan(
                PklPengajuanModel::aktif($status)
                    ? 'UPDATE pkl_anggota SET siswa_aktif = siswa_id WHERE pengajuan_id = ?'
                    : 'UPDATE pkl_anggota SET siswa_aktif = NULL WHERE pengajuan_id = ?',
                [$id]
            );
            if (! $sync['ok']) {
                return ['ok' => false, 'kode' => $sync['dup'] ? 'bentrok' : 'galat'];
            }

            $aksi = $konteks['aksi'] ?? match ($status) {
                'disetujui' => 'acc',
                'perbaikan' => 'kembalikan',
                'ditolak'   => 'tolak',
                default     => 'tunda',
            };

            return $this->catat($id, $aksi, $konteks, $catatan)
                ? ['ok' => true, 'id' => $id, 'status_lama' => (string) $row['status']]
                : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * "Ubah langsung" oleh staf: isi ajuan & daftar anggota diganti TANPA mengubah status.
     * Pengaju harus tetap orang yang sama. Anggota baru terkunci (siswa_aktif) hanya bila
     * ajuannya aktif; pada ajuan yang ditolak mereka tak terkunci. Bila ajuan sudah
     * `disetujui`, tautan ke master perusahaan ikut disesuaikan dengan nama barunya.
     * $konteks['catatan'] (mis. "tanggal di luar batas, dikonfirmasi") masuk riwayat.
     */
    public function ubahIsi(int $id, array $data, array $anggota, array $konteks): array
    {
        return $this->transaksi(function () use ($id, $data, $anggota, $konteks): array {
            $now = date('Y-m-d H:i:s');
            $row = $this->db->query('SELECT * FROM pkl_pengajuan WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($row === null) {
                return ['ok' => false, 'kode' => 'tidak_ada'];
            }
            $aktif = PklPengajuanModel::aktif((string) $row['status']);

            $ada = $this->db->query('SELECT id, siswa_id, peran FROM pkl_anggota WHERE pengajuan_id = ?', [$id])->getResultArray();
            $pengajuLama = null;
            foreach ($ada as $a) {
                if ($a['peran'] === 'pengaju') {
                    $pengajuLama = (int) $a['siswa_id'];
                }
            }
            if ($pengajuLama === null || $pengajuLama !== (int) ($anggota[0]['siswa_id'] ?? 0)) {
                return ['ok' => false, 'kode' => 'pengaju_beda'];
            }

            $perusahaanId = $row['perusahaan_id'] !== null ? (int) $row['perusahaan_id'] : null;
            if ($row['status'] === 'disetujui') {
                $perusahaanId = $this->tautkanPerusahaan($data, null);
                if ($perusahaanId === null) {
                    return ['ok' => false, 'kode' => 'galat'];
                }
            }

            $r = $this->jalankan(
                'UPDATE pkl_pengajuan SET updated_at = ?, perusahaan_id = ?,'
                . ' perusahaan_nama = ?, perusahaan_norm = ?, perusahaan_alamat = ?, perusahaan_kota = ?,'
                . ' perusahaan_telepon = ?, kontak_nama = ?, kontak_jabatan = ?'
                . ' WHERE id = ?',
                [
                    $now, $perusahaanId,
                    $data['perusahaan_nama'], $data['perusahaan_norm'], $data['perusahaan_alamat'], $data['perusahaan_kota'],
                    $data['perusahaan_telepon'], $data['kontak_nama'], $data['kontak_jabatan'], $id,
                ]
            );
            if (! $r['ok']) {
                return ['ok' => false, 'kode' => 'galat'];
            }

            $baruIds = array_map(static fn (array $m) => (int) $m['siswa_id'], $anggota);
            $petaAda = [];
            foreach ($ada as $a) {
                $petaAda[(int) $a['siswa_id']] = (int) $a['id'];
                if (! in_array((int) $a['siswa_id'], $baruIds, true)) {
                    if (! $this->jalankan('DELETE FROM pkl_anggota WHERE id = ?', [(int) $a['id']])['ok']) {
                        return ['ok' => false, 'kode' => 'galat'];
                    }
                }
            }
            foreach ($anggota as $m) {
                $sid = (int) $m['siswa_id'];
                if (isset($petaAda[$sid])) {
                    // HP diperbarui bila diisi (kosong = biarkan yang lama).
                    $hpBaru = ($m['peran'] ?? 'teman') === 'pengaju' ? ($data['hp'] ?? null) : ($m['hp'] ?? null);
                    if ($hpBaru !== null && $hpBaru !== '') {
                        $up = $this->jalankan('UPDATE pkl_anggota SET hp = ? WHERE id = ?', [$hpBaru, $petaAda[$sid]]);
                        if (! $up['ok']) {
                            return ['ok' => false, 'kode' => 'galat'];
                        }
                    }
                    continue;
                }
                $gagal = $this->sisipAnggota($id, $m, $data, $now, $aktif);
                if ($gagal !== null) {
                    return $gagal;
                }
            }

            return $this->catat($id, $konteks['aksi'] ?? 'ubah', $konteks, $konteks['catatan'] ?? null)
                ? ['ok' => true, 'id' => $id]
                : ['ok' => false, 'kode' => 'galat'];
        });
    }

    /**
     * Cari/buat perusahaan di master untuk data ajuan ($d memuat perusahaan_* & kontak_*).
     * $pilihId = pilihan staf (harus ada); kosong = nama pembanding sama → pakai yang ada,
     * selain itu daftarkan baru. Kolom master yang masih kosong dilengkapi dari ajuan.
     *
     * @return int|null id master, atau null bila gagal
     */
    private function tautkanPerusahaan(array $d, ?int $pilihId): ?int
    {
        $now = date('Y-m-d H:i:s');
        $id  = null;

        if ($pilihId !== null && $pilihId > 0) {
            $ada = $this->db->query('SELECT id FROM pkl_perusahaan WHERE id = ?', [$pilihId])->getRowArray();
            $id  = $ada !== null ? (int) $ada['id'] : null;
        } else {
            $ada = $this->db->query('SELECT id FROM pkl_perusahaan WHERE nama_norm = ? ORDER BY id LIMIT 1', [$d['perusahaan_norm']])->getRowArray();
            $id  = $ada !== null ? (int) $ada['id'] : null;
        }

        if ($id !== null) {
            $up = $this->jalankan(
                "UPDATE pkl_perusahaan SET alamat = COALESCE(NULLIF(alamat, ''), ?), kota = COALESCE(NULLIF(kota, ''), ?),"
                . " telepon = COALESCE(NULLIF(telepon, ''), ?), kontak_nama = COALESCE(NULLIF(kontak_nama, ''), ?),"
                . " kontak_jabatan = COALESCE(NULLIF(kontak_jabatan, ''), ?), updated_at = ? WHERE id = ?",
                [$d['perusahaan_alamat'], $d['perusahaan_kota'], $d['perusahaan_telepon'], $d['kontak_nama'], $d['kontak_jabatan'], $now, $id]
            );

            return $up['ok'] ? $id : null;
        }

        if ($pilihId !== null && $pilihId > 0) {
            return null; // staf menunjuk master yang tak ada
        }

        $r = $this->sisip('pkl_perusahaan', [
            'nama'           => $d['perusahaan_nama'],
            'nama_norm'      => $d['perusahaan_norm'],
            'alamat'         => $d['perusahaan_alamat'],
            'kota'           => $d['perusahaan_kota'],
            'telepon'        => $d['perusahaan_telepon'],
            'kontak_nama'    => $d['kontak_nama'],
            'kontak_jabatan' => $d['kontak_jabatan'],
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return $r['ok'] ? $r['id'] : null;
    }

    /**
     * Baris anggota yang kuncinya TIDAK sesuai status ajuannya — harus kosong.
     * Dipakai uji otomatis dan pemeriksaan data di Tahap 5.
     *
     * @return list<array<string, mixed>>
     */
    public function periksaKonsistensi(): array
    {
        return $this->db->query(
            "SELECT a.id, a.pengajuan_id, a.siswa_id, a.siswa_aktif, p.status"
            . " FROM pkl_anggota a JOIN pkl_pengajuan p ON p.id = a.pengajuan_id"
            . " WHERE (p.status IN ('menunggu','perbaikan','disetujui') AND (a.siswa_aktif IS NULL OR a.siswa_aktif <> a.siswa_id))"
            . "    OR (p.status = 'ditolak' AND a.siswa_aktif IS NOT NULL)"
        )->getResultArray();
    }

    /** Catat satu kejadian di pkl_riwayat. */
    public function catat(int $pengajuanId, string $aksi, array $konteks, ?string $catatan): bool
    {
        $r = $this->sisip('pkl_riwayat', [
            'pengajuan_id' => $pengajuanId,
            'aksi'         => $aksi,
            'oleh'         => mb_substr((string) ($konteks['oleh'] ?? 'Sistem'), 0, 150),
            'admin_id'     => $konteks['admin_id'] ?? null,
            'peran'        => $konteks['peran'] ?? null,
            'catatan'      => $catatan !== null ? mb_substr($catatan, 0, self::maksCatatan()) : null,
            'ip_address'   => $konteks['ip'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $r['ok'];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Kolom tabel pkl_pengajuan yang berasal dari isian form. */
    private function barisAjuan(array $data): array
    {
        return [
            'perusahaan_nama'    => $data['perusahaan_nama'],
            'perusahaan_norm'    => $data['perusahaan_norm'],
            'perusahaan_alamat'  => $data['perusahaan_alamat'],
            'perusahaan_kota'    => $data['perusahaan_kota'],
            'perusahaan_telepon' => $data['perusahaan_telepon'],
            'kontak_nama'        => $data['kontak_nama'],
            'kontak_jabatan'     => $data['kontak_jabatan'],
            // Tanggal PKL tidak ditanyakan lagi; hanya terisi untuk riwayat lama (impor).
            'tanggal_mulai'      => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai'    => $data['tanggal_selesai'] ?? null,
        ];
    }

    /**
     * Tambah satu anggota (aktif). HP pengaju dari $data['hp'], HP teman dari $m['hp'].
     *
     * @return array<string, mixed>|null null bila berhasil, selain itu hasil gagal siap kembalikan
     */
    private function sisipAnggota(int $pengajuanId, array $m, array $data, string $now, bool $aktif = true): ?array
    {
        $sid     = (int) $m['siswa_id'];
        $pengaju = ($m['peran'] ?? 'teman') === 'pengaju';

        $r = $this->sisip('pkl_anggota', [
            'pengajuan_id'  => $pengajuanId,
            'siswa_id'      => $sid,
            'peran'         => $m['peran'] ?? 'teman',
            'kelas_id'      => $m['kelas_id'] ?? null,
            'hp'            => $pengaju ? ($data['hp'] ?? null) : ($m['hp'] ?? null),
            'siswa_aktif'   => $aktif ? $sid : null,
            'created_at'    => $now,
        ]);
        if ($r['ok']) {
            return null;
        }

        // Tabrakan UNIQUE siswa_aktif = siswa ini sudah aktif di ajuan lain.
        return $r['dup'] && $r['kunci'] === 'siswa_aktif'
            ? ['ok' => false, 'kode' => 'bentrok', 'siswa_ids' => [$sid]]
            : ['ok' => false, 'kode' => 'galat'];
    }

    /** Jalankan $kerja dalam transaksi; apa pun selain ok=true membatalkan SEMUANYA. */
    private function transaksi(callable $kerja): array
    {
        $this->db->transBegin();
        try {
            $hasil = $kerja();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[PKL] transaksi gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'galat'];
        }

        if (($hasil['ok'] ?? false) !== true) {
            $this->db->transRollback();

            return $hasil;
        }
        $this->db->transCommit();

        return $hasil;
    }

    /** INSERT lewat builder; tahan galat di mode DBDebug hidup maupun mati. */
    private function sisip(string $tabel, array $baris): array
    {
        try {
            if ($this->db->table($tabel)->insert($baris)) {
                return ['ok' => true, 'id' => (int) $this->db->insertID(), 'dup' => false, 'kunci' => ''];
            }

            return $this->galatDb($this->db->error());
        } catch (\Throwable $e) {
            return $this->galatDb(['code' => $e->getCode(), 'message' => $e->getMessage()]);
        }
    }

    /** SQL mentah berparameter; tahan galat di mode DBDebug hidup maupun mati. */
    private function jalankan(string $sql, array $bind): array
    {
        try {
            if ($this->db->query($sql, $bind) !== false) {
                return ['ok' => true, 'dup' => false, 'kunci' => ''];
            }

            return $this->galatDb($this->db->error());
        } catch (\Throwable $e) {
            return $this->galatDb(['code' => $e->getCode(), 'message' => $e->getMessage()]);
        }
    }

    /**
     * @param array{code?: int|string, message?: string} $err
     *
     * @return array{ok: false, dup: bool, kunci: string}
     */
    private function galatDb(array $err): array
    {
        $dup   = (int) ($err['code'] ?? 0) === 1062;
        $kunci = '';
        // MariaDB: "... for key 'siswa_aktif'"; MySQL 8: "... for key 'pkl_anggota.siswa_aktif'".
        if ($dup && preg_match("/for key '([^']+)'/", (string) ($err['message'] ?? ''), $m)) {
            $kunci = substr(strrchr('.' . $m[1], '.'), 1);
        }
        if (! $dup) {
            log_message('error', '[PKL] galat database: ' . ($err['message'] ?? '?'));
        }

        return ['ok' => false, 'dup' => $dup, 'kunci' => $kunci];
    }
}
