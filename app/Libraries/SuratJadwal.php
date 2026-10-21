<?php

namespace App\Libraries;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Jadwal kegiatan yang DIBUKA untuk permintaan surat izin dari siswa (Layanan Siswa, permintaan klien 2026-10-21):
 *   - Ujian (jenis izin_asts): ASTS / ASAS / ASAT + semester + tahun pelajaran + tanggal — bisa diambil langsung dari periode di
 *     menu Ujian (ASTS 1, ASAS, ASTS 2, ASAT);
 *   - TKA : gelombang/sesi + tanggal — KHUSUS kelas XII (SuratAcara::TINGKAT).
 * Siswa yang sedang PKL memilih jadwal yang dibuka → surat izin untuk perusahaannya (tahap T3). Jadwal tampil bagi siswa selama
 * `buka` = 1 dan kegiatannya belum selesai (tgl_selesai >= hari ini). Dikelola pemegang hak Buat jenis surat itu (bawaan Operator)
 * di halaman admin/surat/jadwal (Admin\SuratJadwal). Rancangan: docs/DESAIN-SURAT-SEKOLAH.md bagian 10.
 */
final class SuratJadwal
{
    /** Jenis surat yang memakai jadwal. */
    public const JENIS = [SuratJenis::ASTS, SuratJenis::TKA];

    private static ?bool $tabelAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function tabelAda(): bool
    {
        if (self::$tabelAda === null) {
            try {
                self::$tabelAda = db_connect()->tableExists('surat_jadwal') && db_connect()->tableExists('surat_permintaan');
            } catch (\Throwable $e) {
                self::$tabelAda = false;
            }
        }

        return self::$tabelAda;
    }

    /** Nama jadwal: "ASTS Ganjil 2026/2027" / "ASAT 2026/2027" / "TKA Gelombang 1". */
    public static function label(array $j): string
    {
        return SuratAcara::namaPendek((string) $j['jenis'], $j);
    }

    /**
     * Keadaan jadwal bagi siswa: 'buka' (tampil di Layanan Siswa), 'tutup' (ditutup staf), 'selesai' (kegiatan sudah lewat).
     */
    public static function keadaan(array $j): string
    {
        if ((string) $j['tgl_selesai'] < date('Y-m-d')) {
            return 'selesai';
        }

        return (int) $j['buka'] === 1 ? 'buka' : 'tutup';
    }

    /** keadaan => [teks, kelas lencana] */
    public const TAMPIL = [
        'buka'    => ['Dibuka untuk siswa', 'lencana-hijau'],
        'tutup'   => ['Ditutup', 'lencana-abu'],
        'selesai' => ['Kegiatan sudah lewat', 'lencana-abu'],
    ];

    /**
     * Isian surat acara (SuratAcara) dari jadwal ini: siap dipakai membuat surat per perusahaan.
     *
     * @return array<string, mixed>
     */
    public static function isiSurat(array $j): array
    {
        $isi = ['tgl_mulai' => (string) $j['tgl_mulai'], 'tgl_selesai' => (string) $j['tgl_selesai'], 'tempat' => (string) $j['tempat'], 'mode' => 'perusahaan'];

        return $j['jenis'] === SuratJenis::ASTS
            ? ['kegiatan' => SuratAcara::kodeUjian($j['kegiatan'] ?? ''), 'semester' => (string) $j['semester'], 'tahun_pelajaran' => (string) $j['tahun_pelajaran']] + $isi
            : ['kegiatan' => 'TKA', 'sesi' => (string) $j['sesi']] + $isi;
    }

    // =================================================================
    // Baca
    // =================================================================

    /**
     * Semua jadwal (terbaru dulu) + keadaan + jumlah permintaan siswa.
     *
     * @param list<string>|null $jenis saring jenis (null = semua)
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(?array $jenis = null): array
    {
        if (! self::tabelAda()) {
            return [];
        }
        $b = $this->db->table('surat_jadwal j')
            ->select('j.*, (SELECT COUNT(*) FROM surat_permintaan sp WHERE sp.jadwal_id = j.id) AS jml_permintaan', false)
            ->orderBy('j.tgl_mulai', 'DESC')->orderBy('j.id', 'DESC');
        if ($jenis !== null) {
            $b->whereIn('j.jenis', $jenis === [] ? [''] : $jenis);
        }

        return array_map(static fn (array $j): array => self::lengkapi($j), $b->get()->getResultArray());
    }

    /**
     * Jadwal yang tampil bagi siswa: dibuka dan kegiatannya belum selesai, terdekat dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function terbuka(string $jenis): array
    {
        if (! self::tabelAda()) {
            return [];
        }

        return array_map(static fn (array $j): array => self::lengkapi($j + ['jml_permintaan' => 0]), $this->db->table('surat_jadwal')
            ->where('jenis', $jenis)->where('buka', 1)->where('tgl_selesai >=', date('Y-m-d'))
            ->orderBy('tgl_mulai', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray());
    }

    public function muat(int $id): ?array
    {
        if (! self::tabelAda() || $id <= 0) {
            return null;
        }
        $j = $this->db->table('surat_jadwal j')
            ->select('j.*, (SELECT COUNT(*) FROM surat_permintaan sp WHERE sp.jadwal_id = j.id) AS jml_permintaan', false)
            ->where('j.id', $id)->get()->getRowArray();

        return $j === null ? null : self::lengkapi($j);
    }

    /** @param array<string, mixed> $j */
    private static function lengkapi(array $j): array
    {
        $j['id']             = (int) $j['id'];
        $j['buka']           = (int) $j['buka'];
        $j['jml_permintaan'] = (int) ($j['jml_permintaan'] ?? 0);
        $j['label']          = self::label($j);
        $j['hari_tanggal']   = SuratAcara::hariTanggal((string) $j['tgl_mulai'], (string) $j['tgl_selesai']);
        $j['keadaan']        = self::keadaan($j);

        return $j;
    }

    // =================================================================
    // Tulis
    // =================================================================

    /**
     * Periksa isian formulir jadwal. ASTS: `periode` = indeks periode di menu Ujian (SuratAcara::periodeUjian) → semester, tahun,
     * dan tanggal diambil dari sana; kosong = diisi manual. TKA: sesi wajib.
     *
     * @param array<string, mixed> $in
     *
     * @return array{ok: bool, galat: array<string, string>, data: array<string, mixed>}
     */
    public function periksa(string $jenis, array $in): array
    {
        $galat = [];
        if (! in_array($jenis, self::JENIS, true)) {
            return ['ok' => false, 'galat' => ['jenis' => 'Jenis jadwal tidak dikenal.'], 'data' => []];
        }
        if ($jenis === SuratJenis::ASTS && trim((string) ($in['periode'] ?? '')) !== '') {
            $p = (new SuratAcara($this->db))->periodeUjian()[(int) $in['periode']] ?? null;
            if ($p === null) {
                $galat['periode'] = 'Periode ujian tidak ditemukan lagi. Muat ulang halaman.';
            } elseif ($p['mulai'] === null || $p['selesai'] === null) {
                $galat['periode'] = 'Tanggal periode ini belum diisi di menu Ujian. Isi dulu di menu Ujian, atau isi tanggal manual di bawah.';
            } else { // periode Ujian menimpa isian manual
                $in = ['kegiatan' => $p['kegiatan'], 'semester' => $p['semester'], 'tahun_pelajaran' => $p['tahun'], 'tgl_mulai' => $p['mulai'], 'tgl_selesai' => $p['selesai']] + $in;
            }
        }

        $thn   = (int) date('Y');
        $mulai = IsianBantu::tanggal(trim((string) ($in['tgl_mulai'] ?? '')), $thn - 1, $thn + 2);
        $akhir = IsianBantu::tanggal(trim((string) ($in['tgl_selesai'] ?? '')), $thn - 1, $thn + 2);
        if ($mulai === null) {
            $galat['tgl_mulai'] ??= 'Isi tanggal mulai kegiatan.';
        }
        if ($akhir === null) {
            $galat['tgl_selesai'] ??= 'Isi tanggal selesai kegiatan.';
        }
        if ($mulai !== null && $akhir !== null) {
            if ($akhir < $mulai) {
                $galat['tgl_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            } elseif (IsianBantu::hariInklusif($mulai, $akhir) > SuratAcara::MAKS_HARI) {
                $galat['tgl_selesai'] = 'Rentang kegiatan terlalu panjang (maksimal ' . SuratAcara::MAKS_HARI . ' hari). Periksa lagi tanggalnya.';
            }
        }
        $tempat = IsianBantu::rapikan((string) ($in['tempat'] ?? ''));
        if ($tempat === '') {
            $tempat = SuratAcara::TEMPAT_BAWAAN;
        }
        if (mb_strlen($tempat) > 100) {
            $galat['tempat'] = 'Tempat terlalu panjang (maksimal 100 huruf).';
        }

        $data = ['jenis' => $jenis, 'kegiatan' => null, 'tgl_mulai' => (string) $mulai, 'tgl_selesai' => (string) $akhir, 'tempat' => $tempat,
            'semester' => null, 'tahun_pelajaran' => null, 'sesi' => null, 'buka' => ! empty($in['buka']) ? 1 : 0];
        if ($jenis === SuratJenis::ASTS) {
            $mentah   = strtoupper(trim((string) ($in['kegiatan'] ?? '')));
            $kegiatan = SuratAcara::kodeUjian($mentah);
            if ($mentah !== '' && $mentah !== $kegiatan) {
                $galat['kegiatan'] = 'Pilih ujiannya: ASTS, ASAS, atau ASAT.';
            }
            $data['kegiatan'] = $kegiatan;
            $semester = $kegiatan === 'ASAT' ? 'Genap' : (string) ($in['semester'] ?? ''); // ASAT selalu semester Genap
            $tp       = IsianBantu::rapikan((string) ($in['tahun_pelajaran'] ?? ''));
            if (! in_array($semester, ['Ganjil', 'Genap'], true)) {
                $galat['semester'] ??= 'Pilih semester (Ganjil atau Genap).';
            }
            if (preg_match('~^(\d{4})/(\d{4})$~', $tp, $m) !== 1 || (int) $m[2] !== (int) $m[1] + 1) {
                $galat['tahun_pelajaran'] ??= 'Tahun pelajaran harus berbentuk 2026/2027.';
            }
            $data['semester']        = $semester;
            $data['tahun_pelajaran'] = $tp;
        } else {
            $sesi = IsianBantu::rapikan((string) ($in['sesi'] ?? ''));
            if ($sesi === '' || mb_strlen($sesi) > 40) {
                $galat['sesi'] = 'Isi gelombang/sesi (maksimal 40 huruf), mis. Gelombang 1.';
            }
            $data['sesi'] = $sesi;
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'data' => $data];
    }

    /**
     * Simpan jadwal baru ($id null) atau ubah. Jadwal yang sudah dipakai permintaan siswa tidak boleh diubah isinya (surat siswa
     * sudah memakai tanggalnya) — cukup ditutup, lalu buat jadwal baru.
     *
     * @param array<string, mixed> $in
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array{ok: bool, pesan: string, galat?: array<string, string>, id?: int}
     */
    public function simpan(string $jenis, array $in, ?int $id, array $konteks): array
    {
        if (! self::tabelAda()) {
            return ['ok' => false, 'pesan' => 'Fitur jadwal belum aktif di server ini: jalankan "php spark migrate".'];
        }
        $lama = null;
        if ($id !== null) {
            $lama = $this->muat($id);
            if ($lama === null || $lama['jenis'] !== $jenis) {
                return ['ok' => false, 'pesan' => 'Jadwal tidak ditemukan.'];
            }
            if ($lama['jml_permintaan'] > 0) {
                return ['ok' => false, 'pesan' => 'Jadwal ' . $lama['label'] . ' sudah dipakai ' . $lama['jml_permintaan'] . ' permintaan siswa, jadi isinya tidak bisa diubah. Tutup jadwal ini lalu buat jadwal baru.'];
            }
        }
        $h = $this->periksa($jenis, $in);
        if (! $h['ok']) {
            return ['ok' => false, 'pesan' => 'Periksa lagi isian jadwal.', 'galat' => $h['galat']];
        }
        $d = $h['data'];
        $kembar = $this->db->table('surat_jadwal')->where('jenis', $jenis)->where('tgl_mulai', $d['tgl_mulai'])->where('tgl_selesai', $d['tgl_selesai']);
        if ($jenis === SuratJenis::ASTS) {
            $kembar->where('kegiatan', $d['kegiatan'])->where('semester', $d['semester'])->where('tahun_pelajaran', $d['tahun_pelajaran']);
        } else {
            $kembar->where('sesi', $d['sesi']);
        }
        if ($id !== null) {
            $kembar->where('id !=', $id);
        }
        if ($kembar->countAllResults() > 0) {
            return ['ok' => false, 'pesan' => 'Jadwal yang sama (' . self::label($d) . ', ' . SuratAcara::hariTanggal($d['tgl_mulai'], $d['tgl_selesai']) . ') sudah ada.'];
        }

        $now = date('Y-m-d H:i:s');
        if ($id === null) {
            $ok = $this->db->table('surat_jadwal')->insert($d + [
                'dibuat_oleh' => ($konteks['admin_id'] ?? null) ?: null, 'dibuat_nama' => mb_substr((string) ($konteks['oleh'] ?? ''), 0, 150) ?: null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = $ok ? (int) $this->db->insertID() : 0;
        } else {
            $ok = $this->db->table('surat_jadwal')->where('id', $id)->update($d + ['updated_at' => $now]);
        }
        if (! $ok || $id <= 0) {
            return ['ok' => false, 'pesan' => 'Jadwal gagal disimpan. Coba lagi.'];
        }
        $teks = self::label($d) . ' (' . SuratAcara::hariTanggal($d['tgl_mulai'], $d['tgl_selesai']) . ', ' . ($d['buka'] ? 'dibuka untuk siswa' : 'belum dibuka') . ')';
        $this->audit($lama === null ? 'create' : 'update', $id, ($lama === null ? 'Jadwal permintaan siswa dibuat: ' : 'Jadwal permintaan siswa diubah: ') . $teks . $this->saluran($konteks));

        return ['ok' => true, 'id' => $id, 'pesan' => 'Jadwal ' . self::label($d) . ' disimpan' . ($d['buka'] ? ' dan dibuka untuk siswa.' : '. Jadwal belum dibuka untuk siswa.')];
    }

    /**
     * Buka / tutup jadwal untuk siswa.
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, pesan: string}
     */
    public function aturBuka(int $id, bool $buka, array $konteks): array
    {
        $j = $this->muat($id);
        if ($j === null) {
            return ['ok' => false, 'pesan' => 'Jadwal tidak ditemukan.'];
        }
        if ($buka && $j['keadaan'] === 'selesai') {
            return ['ok' => false, 'pesan' => 'Kegiatan ' . $j['label'] . ' sudah lewat, jadi tidak bisa dibuka lagi.'];
        }
        $this->db->table('surat_jadwal')->where('id', $id)->update(['buka' => $buka ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->audit('update', $id, 'Jadwal permintaan siswa ' . $j['label'] . ($buka ? ' DIBUKA' : ' DITUTUP') . ' untuk siswa' . $this->saluran($konteks));

        return ['ok' => true, 'pesan' => $j['label'] . ($buka ? ' dibuka: siswa yang sedang PKL kini bisa mengajukan surat izinnya.' : ' ditutup: siswa tidak bisa lagi mengajukan surat untuk jadwal ini.')];
    }

    /**
     * Hapus jadwal yang BELUM dipakai permintaan siswa (yang sudah dipakai cukup ditutup).
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, pesan: string}
     */
    public function hapus(int $id, array $konteks): array
    {
        $j = $this->muat($id);
        if ($j === null) {
            return ['ok' => false, 'pesan' => 'Jadwal tidak ditemukan.'];
        }
        if ($j['jml_permintaan'] > 0) {
            return ['ok' => false, 'pesan' => 'Jadwal ' . $j['label'] . ' sudah dipakai ' . $j['jml_permintaan'] . ' permintaan siswa, jadi tidak bisa dihapus. Tutup saja jadwalnya.'];
        }
        $this->db->table('surat_jadwal')->where('id', $id)->delete();
        $this->audit('delete', $id, 'Jadwal permintaan siswa dihapus: ' . $j['label'] . ' (' . $j['hari_tanggal'] . ')' . $this->saluran($konteks));

        return ['ok' => true, 'pesan' => 'Jadwal ' . $j['label'] . ' dihapus.'];
    }

    private function saluran(array $konteks): string
    {
        return ($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : '';
    }

    private function audit(string $aksi, int $id, string $teks): void
    {
        (new AuditModel())->record($aksi, 'surat_jadwal', $id, mb_substr($teks, 0, 255));
    }
}
