<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Kabar WhatsApp MANUAL ke siswa bahwa surat sekolahnya SIAP DIAMBIL — saat ini untuk Surat Balasan PKL (surat yang diambil
 * siswa di Tata Usaha lalu diserahkan ke perusahaan). Pola sama dengan kabar Surat Izin PKL (Libraries\PklWa): sistem hanya
 * menyiapkan nomor, pesan, dan tautan wa.me — staf yang menekan tombol dan mengirim dari WhatsApp-nya sendiri (tanpa gateway,
 * tanpa biaya, tanpa risiko nomor sekolah diblokir).
 *
 * Kabar baru tersedia setelah surat DIUNDUH (cetak_ke > 0): sistem tahu surat sudah diunduh, bukan sudah dicetak di kertas,
 * jadi redaksi "siap diambil" aman (keputusan user 2026-10-13). Nomor = HP siswa di surat (dari ajuan PKL), cadangan no_hp di
 * Master Siswa. Templat diatur di Pengaturan PKL (pkl_pengaturan.wa_pesan_balasan; kosong = PESAN_BAWAAN), penanda sama
 * dengan PklWa. Yang boleh mengabari = pemegang hak Unduh jenis surat itu. Jejak: dikabari_at/oleh per siswa, riwayat surat
 * (aksi "kabari"), Audit Log.
 */
final class SuratWa
{
    /** Jenis surat yang punya kabar "siap diambil". */
    public const JENIS = [SuratJenis::BALASAN];

    public const PESAN_BAWAAN = 'Halo {nama}, Surat Balasan PKL untuk {perusahaan} (No. {nomor_surat}) sudah selesai dan siap diambil di Tata Usaha {sekolah}. Setelah diambil, serahkan ke perusahaan tempat PKL. Terima kasih.';

    /** Batas baris sekali tampil (kabar massal per jenis). */
    public const MAKS_BARIS = 300;

    private static ?bool $kolomAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function berlaku(string $jenis): bool
    {
        return in_array($jenis, self::JENIS, true);
    }

    /** Migrasi 2026-10-18-000003_SuratWa sudah jalan? */
    public static function kolomAda(): bool
    {
        if (self::$kolomAda === null) {
            try {
                $db             = db_connect();
                self::$kolomAda = $db->tableExists('surat_sekolah_siswa') && $db->fieldExists('dikabari_at', 'surat_sekolah_siswa');
            } catch (\Throwable $e) {
                self::$kolomAda = false;
            }
        }

        return self::$kolomAda;
    }

    /** Templat yang berlaku (isian Pengaturan PKL, atau bawaan). */
    public static function templat(array $p): string
    {
        $t = trim((string) ($p['wa_pesan_balasan'] ?? ''));

        return $t !== '' ? $t : self::PESAN_BAWAAN;
    }

    /**
     * Siswa yang bisa dikabari pada surat-surat ini (hanya jenis yang berlaku, status siap unduh, SUDAH diunduh).
     *
     * @param list<int> $suratIds
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(array $suratIds, array $p, array $setting): array
    {
        $suratIds = array_values(array_unique(array_filter(array_map('intval', $suratIds))));

        return $suratIds === [] ? [] : $this->ambil(fn ($b) => $b->whereIn('ss.surat_id', $suratIds), $p, $setting);
    }

    /**
     * Kabar massal: siswa yang BELUM dikabari pada semua surat jenis $jenis yang sudah diunduh (terlama dulu, maks MAKS_BARIS).
     *
     * @return list<array<string, mixed>>
     */
    public function belumDikabari(string $jenis, array $p, array $setting): array
    {
        if (! self::berlaku($jenis)) {
            return [];
        }

        return $this->ambil(fn ($b) => $b->where('ws.jenis', $jenis)->where('ss.dikabari_at', null), $p, $setting);
    }

    /** Jumlah siswa pada satu surat & yang sudah dikabari. @return array{total: int, dikabari: int} */
    public function hitung(int $suratId): array
    {
        if (! self::kolomAda()) {
            return ['total' => 0, 'dikabari' => 0];
        }
        $r = $this->db->table('surat_sekolah_siswa')->select('COUNT(*) AS total, SUM(dikabari_at IS NOT NULL) AS dikabari', false)
            ->where('surat_id', $suratId)->get()->getRowArray();

        return ['total' => (int) ($r['total'] ?? 0), 'dikabari' => (int) ($r['dikabari'] ?? 0)];
    }

    /**
     * Tandai siswa sudah dikabari (dipanggil setelah staf menekan tombol WhatsApp).
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, waktu?: string, oleh?: string, pesan?: string}
     */
    public function tandai(int $suratId, int $siswaId, array $konteks): array
    {
        if (! self::kolomAda()) {
            return ['ok' => false, 'pesan' => 'Fitur kabar WhatsApp belum aktif di server ini (migrasi belum dijalankan).'];
        }
        $r = $this->db->table('surat_sekolah_siswa ss')->select('ss.id, s.nama, ws.jenis')
            ->join('surat_sekolah ws', 'ws.id = ss.surat_id')->join('siswa s', 's.id = ss.siswa_id')
            ->where('ss.surat_id', $suratId)->where('ss.siswa_id', $siswaId)
            ->whereIn('ws.jenis', self::JENIS)->where('ws.status', 'disetujui')->where('ws.cetak_ke >', 0)
            ->get()->getRowArray();
        if ($r === null) {
            return ['ok' => false, 'pesan' => 'Siswa tidak ada pada surat ini, atau surat belum diunduh.'];
        }
        $waktu = date('Y-m-d H:i:s');
        $oleh  = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        $this->db->table('surat_sekolah_siswa')->where('id', (int) $r['id'])->update(['dikabari_at' => $waktu, 'dikabari_oleh' => $oleh]);
        (new SuratSekolah($this->db))->catat($suratId, 'kabari', $konteks, 'Dikabari lewat WhatsApp: ' . $r['nama']);
        (new AuditModel())->record('update', 'surat_sekolah_siswa', (int) $r['id'], mb_substr('Siswa dikabari lewat WhatsApp: ' . $r['nama'] . ' (' . SuratJenis::singkat((string) $r['jenis']) . ' ' . SuratSekolahModel::kode($suratId) . ')' . (($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

        return ['ok' => true, 'waktu' => $waktu, 'oleh' => $oleh];
    }

    // =================================================================

    /**
     * @param callable(\CodeIgniter\Database\BaseBuilder): mixed $saring
     *
     * @return list<array<string, mixed>>
     */
    private function ambil(callable $saring, array $p, array $setting): array
    {
        if (! self::kolomAda()) {
            return [];
        }
        $b = $this->db->table('surat_sekolah_siswa ss')
            ->select('ss.surat_id, ss.siswa_id, ss.hp, ss.dikabari_at, ss.dikabari_oleh, s.nama, s.no_hp AS hp_master, k.nama_kelas')
            ->select('ws.jenis, ws.nomor, ws.tanggal_surat, ws.perusahaan_nama')
            ->join('surat_sekolah ws', 'ws.id = ss.surat_id')
            ->join('siswa s', 's.id = ss.siswa_id')
            ->join('kelas k', 'k.id = ss.kelas_id', 'left')
            ->whereIn('ws.jenis', self::JENIS)->where('ws.status', 'disetujui')->where('ws.cetak_ke >', 0);
        $saring($b);
        $rows    = $b->orderBy('ws.terakhir_cetak_at', 'ASC')->orderBy('ws.id', 'ASC')->orderBy('ss.urut', 'ASC')->orderBy('ss.id', 'ASC')->limit(self::MAKS_BARIS)->get()->getResultArray();
        $templat = self::templat($p);
        $out     = [];
        foreach ($rows as $r) {
            $hp    = trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : trim((string) ($r['hp_master'] ?? ''));
            $wa    = $hp !== '' ? PklWa::nomorWa($hp) : null;
            $pesan = PklWa::isi($templat, [
                'nama' => IsianBantu::judul((string) $r['nama']), 'kelas' => (string) ($r['nama_kelas'] ?? ''), 'perusahaan' => (string) $r['perusahaan_nama'],
                'nomor_surat' => (string) ($r['nomor'] ?? ''), 'tanggal_surat' => IsianBantu::tanggalIndo((string) $r['tanggal_surat']),
                'sekolah' => (string) ($setting['school_name'] ?? ''),
            ]);
            $out[] = [
                'surat_id' => (int) $r['surat_id'], 'kode' => SuratSekolahModel::kode((int) $r['surat_id']), 'jenis' => (string) $r['jenis'],
                'nomor_surat' => (string) ($r['nomor'] ?? ''), 'perusahaan' => (string) $r['perusahaan_nama'],
                'siswa_id' => (int) $r['siswa_id'], 'nama' => (string) $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''),
                'hp' => $hp, 'wa' => $wa, 'url' => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
                'alasan_tidak' => $wa === null ? ($hp === '' ? 'Nomor HP kosong' : 'Nomor HP tidak valid') : null,
                'pesan' => $pesan, 'dikabari_at' => $r['dikabari_at'] ?? null, 'dikabari_oleh' => $r['dikabari_oleh'] ?? null,
            ];
        }

        return $out;
    }
}
