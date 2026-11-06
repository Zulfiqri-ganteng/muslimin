<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Isi halaman wali kelas "Kelas Saya" (2026-11-07, W2) — SATU sumber untuk web (Admin\KelasSaya) dan API (Api\Wali). Hanya membaca.
 * Siapa yang boleh melihat kelas mana dijaga pemanggil lewat Libraries\WaliKelas::bolehKelas.
 *
 * Isi satu kelas:
 *   - siswa aktif: NIS/NISN, HP siswa, orang tua + HP + tautan WhatsApp (pesan pembuka dari wali kelas), status biodata,
 *     status PKL (rumus SAMA dengan menu PKL → Status Siswa: PklPengajuanModel::statusSiswa) dan jumlah terlambat tahun ajaran ini;
 *   - ringkasan: jumlah siswa, L/P, biodata terisi, ringkasan PKL (bila tingkat kelas ikut PKL), keterlambatan;
 *   - keterlambatan (Guru Piket) terbaru & surat sekolah siswa kelas ini (izin ASTS/TKA, surat PKL, …) terbaru.
 */
final class KelasSayaData
{
    /** Bagian halaman (tab web = kunci data API). */
    public const BAGIAN = ['siswa' => 'Siswa', 'pkl' => 'PKL', 'telat' => 'Keterlambatan', 'surat' => 'Surat'];

    public const LABEL_PKL = [
        'belum' => 'Belum mengajukan', 'ditolak' => 'Ditolak (perlu ajukan ulang)', 'menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perlu perbaikan',
        'belum_mulai' => 'Disetujui, belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL', 'disetujui' => 'Disetujui',
    ];

    /** Warna lencana web per fase PKL (kit admin). */
    public const WARNA_PKL = [
        'belum' => 'lencana-abu', 'ditolak' => 'lencana-merah', 'menunggu' => 'lencana-kuning', 'perbaikan' => 'lencana-kuning',
        'belum_mulai' => 'lencana-hijau', 'sedang' => 'lencana-hijau', 'selesai' => 'lencana-biru', 'disetujui' => 'lencana-hijau',
    ];

    /** Banyak baris keterlambatan & surat terbaru yang ditampilkan. */
    public const BATAS_DAFTAR = 100;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Semua isi satu kelas. null bila kelas tidak ada.
     *
     * @return array<string, mixed>|null
     */
    public function kelas(int $kelasId, string $namaWaliAkun = ''): ?array
    {
        $info = $this->db->table('kelas k')->select('k.id, k.nama_kelas, k.tingkat, g.nama AS wali, j.nama AS jurusan')
            ->join('guru g', 'g.id = k.wali_kelas_id', 'left')->join('jurusan j', 'j.id = k.jurusan_id', 'left')
            ->where('k.id', $kelasId)->where('k.deleted_at', null)->get()->getRowArray();
        if ($info === null) {
            return null;
        }
        $setting = (new SettingModel())->get();
        $ta      = trim((string) ($setting['academic_year'] ?? ''));
        $siswa   = $this->db->table('siswa')
            ->select('id, nis, nisn, nama, jenis_kelamin, no_hp, nama_ayah, nama_ibu, nama_wali, ortu_telepon, no_hp_wali, biodata_at')
            ->where('kelas_id', $kelasId)->where('status', 'aktif')->where('deleted_at', null)->orderBy('nama', 'ASC')->get()->getResultArray();
        $ids = array_map('intval', array_column($siswa, 'id'));

        // PKL: rumus Status Siswa (hanya bila tingkat kelas termasuk yang boleh PKL di Pengaturan PKL).
        $p        = (new PklPengaturanModel())->ambil();
        $ikutPkl  = in_array((string) $info['tingkat'], PklPengaturanModel::tingkatBoleh($p), true);
        $pkl      = [];
        $ringkPkl = null;
        if ($ikutPkl) {
            $model = new PklPengajuanModel();
            foreach ($model->statusSiswa([(string) $info['tingkat']], $kelasId, '', '', 1000, 1)[0] as $r) {
                $pkl[(int) $r['id']] = $r;
            }
            $ringkPkl = $model->ringkasanSiswa([(string) $info['tingkat']], $kelasId);
        }

        // Keterlambatan per siswa (tahun ajaran ini; catatan lama tanpa tahun ajaran ikut dihitung).
        $telat = [];
        if ($ids !== [] && $this->db->tableExists('keterlambatan_siswa')) {
            $b = $this->db->table('keterlambatan_siswa')->select('siswa_id, COUNT(*) n, MAX(tanggal) terakhir')->whereIn('siswa_id', $ids)->groupBy('siswa_id');
            if ($ta !== '') {
                $b->groupStart()->where('tahun_ajaran', $ta)->orWhere('tahun_ajaran', null)->groupEnd();
            }
            foreach ($b->get()->getResultArray() as $r) {
                $telat[(int) $r['siswa_id']] = ['jumlah' => (int) $r['n'], 'terakhir' => (string) $r['terakhir']];
            }
        }

        $sekolah = (string) ($setting['school_name'] ?? 'sekolah');
        $wali    = $namaWaliAkun !== '' ? $namaWaliAkun : (string) ($info['wali'] ?? '');
        $daftar  = [];
        foreach ($siswa as $s) {
            $sid    = (int) $s['id'];
            $ortu   = trim((string) ($s['nama_ayah'] ?: ($s['nama_ibu'] ?: ($s['nama_wali'] ?? ''))));
            $hpOrtu = trim((string) ($s['ortu_telepon'] ?: ($s['no_hp_wali'] ?? '')));
            $wa     = $hpOrtu !== '' ? PklWa::nomorWa($hpOrtu) : null;
            $pesan  = 'Assalamualaikum Bapak/Ibu orang tua/wali dari ' . $s['nama'] . ' (' . $info['nama_kelas'] . '). Saya ' . ($wali !== '' ? $wali . ', ' : '') . 'wali kelas ' . $info['nama_kelas'] . ' ' . $sekolah . '. ';
            $r      = $pkl[$sid] ?? null;
            $daftar[] = [
                'id' => $sid, 'nama' => (string) $s['nama'], 'jk' => (string) ($s['jenis_kelamin'] ?? ''), 'nis' => $s['nis'] ?: null, 'nisn' => $s['nisn'] ?: null,
                'hp' => $s['no_hp'] ?: null, 'ortu_nama' => $ortu !== '' ? $ortu : null, 'ortu_hp' => $hpOrtu !== '' ? $hpOrtu : null,
                'wa_ortu' => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
                'biodata' => ! empty($s['biodata_at']),
                'pkl' => $r === null ? null : [
                    'fase' => (string) $r['fase'], 'label' => self::LABEL_PKL[$r['fase']] ?? (string) $r['fase'],
                    'perusahaan' => $r['perusahaan_nama'] ?: null, 'kota' => $r['perusahaan_kota'] ?: null,
                    'mulai' => $r['tanggal_mulai'] ?: null, 'selesai' => $r['selesai_efektif'] ?: ($r['tanggal_selesai'] ?: null),
                    'pernah_ditolak' => (int) $r['jml_tolak'] > 0,
                ],
                'telat' => $telat[$sid] ?? ['jumlah' => 0, 'terakhir' => null],
            ];
        }

        $keterlambatan = $this->keterlambatan($kelasId, $ids);
        $surat         = $this->surat($ids);
        $jmlL          = count(array_filter($siswa, static fn (array $s): bool => $s['jenis_kelamin'] === 'L'));

        return [
            'kelas'   => ['id' => (int) $info['id'], 'nama' => (string) $info['nama_kelas'], 'tingkat' => (string) $info['tingkat'], 'wali' => $info['wali'] ?: null, 'jurusan' => $info['jurusan'] ?: null],
            'tahun_ajaran' => $ta !== '' ? $ta : null,
            'ringkasan' => [
                'siswa' => count($siswa), 'laki' => $jmlL, 'perempuan' => count($siswa) - $jmlL,
                'biodata_terisi' => count(array_filter($siswa, static fn (array $s): bool => ! empty($s['biodata_at']))),
                // Nomor HP orang tua hanya ada bila siswa sudah mengisi Biodata (data hosting 7 Nov: 146 dari 1.730 siswa).
                'tanpa_hp_ortu'  => count(array_filter($daftar, static fn (array $s): bool => $s['ortu_hp'] === null)),
                'pkl' => $ringkPkl === null ? null : [
                    'ikut' => true, 'belum_mengajukan' => (int) $ringkPkl['belum_isi'], 'menunggu' => (int) $ringkPkl['menunggu'] + (int) $ringkPkl['perbaikan'],
                    'sudah_pkl' => (int) $ringkPkl['sudah_pkl'], 'sedang' => (int) $ringkPkl['sedang'], 'selesai' => (int) $ringkPkl['selesai'], 'persen_pkl' => (int) $ringkPkl['persen_pkl'],
                ],
                'telat_siswa' => count(array_filter($telat, static fn (array $t): bool => $t['jumlah'] > 0)),
                'telat_kejadian' => array_sum(array_column($telat, 'jumlah')),
                'surat' => count($surat),
            ],
            'tautan_biodata' => LayananPublik::tautanBiodata(), // ajak siswa yang HP orang tuanya kosong mengisi Biodata
            'siswa'         => $daftar,
            'keterlambatan' => $keterlambatan,
            'surat'         => $surat,
        ];
    }

    /**
     * Keterlambatan terbaru siswa kelas ini (kelas saat dicatat ATAU siswanya sekarang di kelas ini).
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function keterlambatan(int $kelasId, array $ids): array
    {
        if (! $this->db->tableExists('keterlambatan_siswa')) {
            return [];
        }
        $b = $this->db->table('keterlambatan_siswa')->select('id, tanggal, jam, siswa_id, nama_siswa, sanksi, keterangan, guru_piket, dikabari_at')
            ->groupStart()->where('kelas_id', $kelasId);
        if ($ids !== []) {
            $b->orWhereIn('siswa_id', $ids);
        }

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'tanggal' => (string) $r['tanggal'], 'jam' => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : null,
            'siswa_id' => $r['siswa_id'] !== null ? (int) $r['siswa_id'] : null, 'nama' => (string) $r['nama_siswa'], 'sanksi' => $r['sanksi'] ?: null,
            'keterangan' => $r['keterangan'] ?: null, 'guru_piket' => $r['guru_piket'] ?: null, 'ortu_dikabari' => $r['dikabari_at'] !== null,
        ], $b->groupEnd()->orderBy('tanggal', 'DESC')->orderBy('jam', 'DESC')->limit(self::BATAS_DAFTAR)->get()->getResultArray());
    }

    /**
     * Surat sekolah terbaru yang memuat siswa kelas ini (kecuali yang dibatalkan).
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function surat(array $ids): array
    {
        if ($ids === [] || ! $this->db->tableExists('surat_sekolah_siswa')) {
            return [];
        }
        $rows = $this->db->table('surat_sekolah_siswa ss')
            ->select('s.id, s.jenis, s.status, s.nomor, s.tanggal_surat, s.created_at, GROUP_CONCAT(si.nama ORDER BY si.nama SEPARATOR ", ") AS nama_siswa', false)
            ->join('surat_sekolah s', 's.id = ss.surat_id')->join('siswa si', 'si.id = ss.siswa_id')
            ->whereIn('ss.siswa_id', $ids)->where('s.status !=', 'dibatalkan')
            ->groupBy('s.id')->orderBy('s.created_at', 'DESC')->limit(self::BATAS_DAFTAR)->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'kode' => SuratSekolahModel::kode((int) $r['id']), 'jenis' => (string) $r['jenis'], 'jenis_label' => SuratJenis::label((string) $r['jenis']),
            'status' => (string) $r['status'], 'status_label' => SuratSekolahModel::TAMPIL_STATUS[$r['status']][0] ?? (string) $r['status'],
            'nomor' => $r['nomor'] ?: null, 'tanggal' => (string) ($r['tanggal_surat'] ?: substr((string) $r['created_at'], 0, 10)), 'siswa' => (string) $r['nama_siswa'],
        ], $rows);
    }
}
