<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat permohonan PKL: data surat, penomoran, "perlu cetak ulang", dan perakitan berkas Word.
 *
 * Aturan (docs/DESAIN-PKL.md):
 *   - nomor ditetapkan SEKALI saat surat pertama diterbitkan; cetak ulang memakai nomor yang sama;
 *   - urutan per TAHUN (tahun tanggal surat); lantai "nomor berikutnya" di Pengaturan;
 *   - penomoran dikunci lewat baris pkl_pengaturan (FOR UPDATE) + UNIQUE(tahun, urut) di database,
 *     jadi dua staf yang menerbitkan bersamaan tak pernah mendapat nomor kembar;
 *   - sidik data (perusahaan, tanggal, siswa) disimpan tiap cetak → bila berubah = "perlu cetak ulang".
 */
final class PklSurat
{
    public const BERKAS_TEMPLATE = 'template_surat.docx';

    /** Penanda skalar yang bisa dipakai di template Word (${nama}). */
    public const SKALAR = [
        'nomor', 'tanggal', 'kota_surat', 'penerima', 'perusahaan', 'perusahaan_alamat', 'perusahaan_kota', 'alamat_lengkap',
        'perusahaan_telepon', 'kontak_nama', 'kontak_jabatan', 'mulai', 'selesai', 'lama', 'tahun_ajaran', 'jumlah_siswa',
        'siswa_daftar', 'sekolah', 'sekolah_alamat', 'sekolah_telepon', 'sekolah_email', 'waka_nama', 'waka_nip', 'waka_jabatan',
    ];

    /** Penanda per baris tabel siswa. */
    public const BARIS = ['no', 'siswa_nama', 'siswa_nis', 'siswa_nisn', 'siswa_kelas', 'siswa_jurusan'];

    /** Lebih dari ini → dipecah jadi ZIP beberapa berkas Word. */
    private const MAKS_SATU_BERKAS = 60;
    private const ISI_PER_BERKAS   = 50;

    private BaseConnection $db;
    private PklPengajuanModel $model;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db    = $db ?? db_connect();
        $this->model = new PklPengajuanModel();
    }

    public static function dirBerkas(): string
    {
        $dir = WRITEPATH . 'pkl/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Path template Word unggahan sekolah bila ada & berkasnya masih ada, selain itu null (= surat bawaan). */
    public static function pathTemplate(array $pengaturan): ?string
    {
        $nama = (string) ($pengaturan['template_surat'] ?? '');
        $path = self::dirBerkas() . $nama;

        return ($nama !== '' && is_file($path)) ? $path : null;
    }

    // =================================================================
    // Data & sidik
    // =================================================================

    /** @return array{ajuan: array<string,mixed>, anggota: list<array<string,mixed>>}|null */
    public function muat(int $id): ?array
    {
        $ajuan = $this->model->detail($id);

        return $ajuan === null ? null : ['ajuan' => $ajuan, 'anggota' => $this->model->anggotaDetail($id)];
    }

    /** Sidik data yang tampil di surat — berubah bila perusahaan/tanggal/siswa berubah. */
    public static function sidik(array $ajuan, array $anggota): string
    {
        $dasar = [
            (string) $ajuan['perusahaan_nama'], (string) ($ajuan['perusahaan_alamat'] ?? ''), (string) ($ajuan['perusahaan_kota'] ?? ''),
            (string) ($ajuan['perusahaan_telepon'] ?? ''), (string) ($ajuan['kontak_nama'] ?? ''), (string) ($ajuan['kontak_jabatan'] ?? ''),
            (string) ($ajuan['tanggal_mulai'] ?? ''), (string) ($ajuan['tanggal_selesai'] ?? ''),
        ];
        $siswa = array_map(static fn (array $a) => [
            (int) $a['siswa_id'], (string) $a['nama'], (string) ($a['nisn'] ?? ''), (string) ($a['nama_kelas'] ?? ''), (string) ($a['jurusan_nama'] ?? ''),
        ], $anggota);
        usort($siswa, static fn ($x, $y) => $x[0] <=> $y[0]);

        return sha1(json_encode([$dasar, $siswa], JSON_UNESCAPED_UNICODE));
    }

    public function surat(int $id): ?array
    {
        return $this->db->table('pkl_surat')->where('pengajuan_id', $id)->get()->getRowArray();
    }

    /**
     * Status surat banyak ajuan sekaligus (untuk daftar): id => ['nomor', 'perlu_ulang'].
     *
     * @param list<int> $ids
     *
     * @return array<int, array{nomor: string, perlu_ulang: bool}>
     */
    public function statusBanyak(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $surat = [];
        foreach ($this->db->table('pkl_surat')->whereIn('pengajuan_id', $ids)->get()->getResultArray() as $r) {
            $surat[(int) $r['pengajuan_id']] = $r;
        }
        if ($surat === []) {
            return [];
        }

        $out     = [];
        $ajuan   = [];
        foreach ($this->db->table('pkl_pengajuan')->whereIn('id', array_keys($surat))->get()->getResultArray() as $r) {
            $ajuan[(int) $r['id']] = $r;
        }
        $anggota = [];
        foreach ($this->db->table('pkl_anggota a')
            ->select('a.pengajuan_id, a.siswa_id, s.nama, s.nisn, k.nama_kelas, j.nama AS jurusan_nama')
            ->join('siswa s', 's.id = a.siswa_id')->join('kelas k', 'k.id = a.kelas_id', 'left')->join('jurusan j', 'j.id = k.jurusan_id', 'left')
            ->whereIn('a.pengajuan_id', array_keys($surat))->get()->getResultArray() as $r) {
            $anggota[(int) $r['pengajuan_id']][] = $r;
        }
        foreach ($surat as $id => $s) {
            $out[$id] = [
                'nomor'       => (string) $s['nomor'],
                'perlu_ulang' => isset($ajuan[$id]) && self::sidik($ajuan[$id], $anggota[$id] ?? []) !== (string) $s['sidik'],
            ];
        }

        return $out;
    }

    // =================================================================
    // Penomoran
    // =================================================================

    /**
     * Pastikan ajuan punya surat bernomor; bila belum, tetapkan nomor berikutnya.
     *
     * @return array{ok: bool, kode?: string, surat?: array<string,mixed>, baru?: bool}
     */
    public function terbitkan(int $id, string $tanggal, array $konteks): array
    {
        $tgl = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal || (int) $tgl->format('Y') < 2000) {
            return ['ok' => false, 'kode' => 'tanggal'];
        }

        $this->db->transBegin();
        try {
            // Mutex penomoran: semua penerbit antre di baris pengaturan.
            $this->db->query('SELECT id FROM pkl_pengaturan WHERE id = 1 FOR UPDATE');

            $ada = $this->surat($id);
            if ($ada !== null) {
                $this->db->transCommit();

                return ['ok' => true, 'surat' => $ada, 'baru' => false];
            }

            $muat = $this->muat($id);
            if ($muat === null || $muat['ajuan']['status'] !== 'disetujui') {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'status'];
            }

            $p      = (new PklPengaturanModel())->ambil();
            $tahun  = (int) $tgl->format('Y');
            $maks   = (int) ($this->db->query('SELECT COALESCE(MAX(urut), 0) m FROM pkl_surat WHERE tahun = ?', [$tahun])->getRowArray()['m'] ?? 0);
            $lantai = ((int) ($p['nomor_awal_tahun'] ?? 0) === $tahun) ? max(1, (int) $p['nomor_awal']) : 1;
            $urut   = max($maks + 1, $lantai);
            $pola   = (string) ($p['format_nomor'] ?? '') !== '' ? (string) $p['format_nomor'] : PklNomorSurat::BAWAAN;
            $now    = date('Y-m-d H:i:s');

            $baris = [
                'pengajuan_id' => $id, 'tahun' => $tahun, 'urut' => $urut, 'nomor' => PklNomorSurat::format($pola, $urut, $tgl),
                'tanggal_surat' => $tanggal, 'cetak_ke' => 0, 'sidik' => self::sidik($muat['ajuan'], $muat['anggota']),
                'dibuat_oleh' => $konteks['admin_id'] ?? null, 'created_at' => $now, 'updated_at' => $now,
            ];
            if (! $this->db->table('pkl_surat')->insert($baris)) {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'galat'];
            }
            (new PklAjuan($this->db))->catat($id, 'surat', $konteks, 'Surat diterbitkan, nomor ' . $baris['nomor']);
            $this->db->transCommit();

            return ['ok' => true, 'surat' => $baris + ['id' => (int) $this->db->insertID()], 'baru' => true];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[PKL] terbitkan surat gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'galat'];
        }
    }

    // =================================================================
    // Berkas
    // =================================================================

    /** Penanda surat untuk satu ajuan. @return array{v: array<string,string>, siswa: list<array<string,string>>} */
    public function isi(array $ajuan, array $anggota, array $surat, array $setting, array $p): array
    {
        $hari = ($ajuan['tanggal_mulai'] && $ajuan['tanggal_selesai']) ? IsianBantu::hariInklusif($ajuan['tanggal_mulai'], $ajuan['tanggal_selesai']) : 0;
        $bln  = $hari > 0 ? max(1, (int) round($hari / 30)) : 0;

        $siswa = [];
        $daftar = [];
        foreach (array_values($anggota) as $i => $a) {
            $siswa[] = [
                'no' => (string) ($i + 1), 'nama' => (string) $a['nama'], 'nis' => (string) ($a['nis'] ?? ''), 'nisn' => ((string) ($a['nisn'] ?? '')) !== '' ? (string) $a['nisn'] : '-',
                'kelas' => (string) ($a['nama_kelas'] ?? ''), 'jurusan' => ((string) ($a['jurusan_nama'] ?? '')) !== '' ? (string) $a['jurusan_nama'] : '-',
            ];
            $daftar[] = ($i + 1) . '. ' . $a['nama'] . ' (' . ($a['nama_kelas'] ?? '-') . ')';
        }

        $kontak  = trim((string) ($ajuan['kontak_nama'] ?? ''));
        $jabatan = trim((string) ($ajuan['kontak_jabatan'] ?? ''));
        $alamat  = trim((string) ($ajuan['perusahaan_alamat'] ?? ''));
        $kota    = trim((string) ($ajuan['perusahaan_kota'] ?? ''));

        return [
            'v' => [
                'nomor' => (string) $surat['nomor'], 'tanggal' => IsianBantu::tanggalIndo($surat['tanggal_surat']),
                'kota_surat' => (string) ($setting['city'] ?? ''),
                'penerima' => $kontak !== '' ? $kontak . ($jabatan !== '' ? ' (' . $jabatan . ')' : '') : 'Pimpinan',
                'perusahaan' => (string) $ajuan['perusahaan_nama'], 'perusahaan_alamat' => $alamat, 'perusahaan_kota' => $kota,
                'alamat_lengkap' => trim($alamat . ($kota !== '' && ! str_contains(mb_strtolower($alamat), mb_strtolower($kota)) ? ', ' . $kota : '')),
                'perusahaan_telepon' => (string) ($ajuan['perusahaan_telepon'] ?? ''), 'kontak_nama' => $kontak, 'kontak_jabatan' => $jabatan,
                'mulai' => IsianBantu::tanggalIndo($ajuan['tanggal_mulai']), 'selesai' => IsianBantu::tanggalIndo($ajuan['tanggal_selesai']),
                'lama' => $hari > 0 ? $hari . ' hari' . ($bln >= 1 ? ' atau ± ' . $bln . ' bulan' : '') : '',
                'tahun_ajaran' => (string) ($setting['academic_year'] ?? ''), 'jumlah_siswa' => (string) count($siswa), 'siswa_daftar' => implode("\n", $daftar),
                'sekolah' => (string) ($setting['school_name'] ?? ''), 'sekolah_alamat' => (string) ($setting['address'] ?? ''),
                'sekolah_telepon' => (string) ($setting['phone'] ?? ''), 'sekolah_email' => (string) ($setting['email'] ?? ''),
                'waka_nama' => (string) ($p['waka_hubin_nama'] ?? ''), 'waka_nip' => (string) ($p['waka_hubin_nip'] ?? ''),
                'waka_jabatan' => (string) ($p['waka_hubin_jabatan'] ?? 'Wakil Kepala Sekolah Bidang Hubungan Industri'),
            ],
            'siswa' => $siswa,
        ];
    }

    private function logo(array $setting): ?array
    {
        return PklDocx::infoLogo(! empty($setting['logo']) ? FCPATH . 'uploads/' . $setting['logo'] : null);
    }

    /**
     * Terbitkan (bila belum) dan rakit surat untuk banyak ajuan. Urutan $ids = urutan nomor
     * bagi yang BARU diberi nomor. Kembalian: berkas siap unduh, atau galat.
     *
     * @param list<int> $ids
     *
     * @return array{ok: bool, pesan?: string, biner?: string, nama?: string, jumlah?: int, dilewati?: int}
     */
    public function bangun(array $ids, array $konteks, ?string $tanggal = null): array
    {
        $setting = (new SettingModel())->get();
        $p       = (new PklPengaturanModel())->ambil();
        $logo    = $this->logo($setting);
        $path    = self::pathTemplate($p);

        $daftar = [];
        $sidik  = [];
        $nomor  = [];
        $lewat  = 0;
        foreach ($ids as $id) {
            $t = $this->terbitkan((int) $id, $tanggal ?? date('Y-m-d'), $konteks);
            $m = $t['ok'] ? $this->muat((int) $id) : null;
            if (! $t['ok'] || $m === null) {
                $lewat++;
                continue;
            }
            $daftar[]      = $this->isi($m['ajuan'], $m['anggota'], $t['surat'], $setting, $p);
            $sidik[$id]    = self::sidik($m['ajuan'], $m['anggota']);
            $nomor[$id]    = (string) $t['surat']['nomor'];
        }
        if ($daftar === []) {
            return ['ok' => false, 'pesan' => 'Tidak ada ajuan yang bisa dibuatkan surat (hanya ajuan berstatus Disetujui).'];
        }

        $rakit = static fn (array $bagian): string => $path !== null
            ? PklDocx::dariTemplate($path, $bagian)
            : PklDocx::dokumen(array_map(static fn (array $d) => PklDocx::suratBawaan($d['v'], $d['siswa'], $logo), $bagian), $logo);

        try {
            if (count($daftar) <= self::MAKS_SATU_BERKAS) {
                $biner = $rakit($daftar);
                $nama  = count($daftar) === 1
                    ? 'Surat PKL ' . self::amanNama($daftar[0]['v']['nomor']) . ' - ' . self::amanNama($daftar[0]['v']['perusahaan']) . '.docx'
                    : 'Surat PKL (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.docx';
            } else {
                $biner = $this->zipkan(array_map($rakit, array_chunk($daftar, self::ISI_PER_BERKAS)));
                $nama  = 'Surat PKL (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.zip';
            }
        } catch (\Throwable $e) {
            log_message('error', '[PKL] rakit surat gagal: ' . $e->getMessage());

            return ['ok' => false, 'pesan' => 'Surat gagal dibuat: ' . $e->getMessage() . ($path !== null ? ' Periksa template Word di Pengaturan PKL (atau hapus template untuk memakai surat bawaan).' : '')];
        }

        $now = date('Y-m-d H:i:s');
        foreach ($sidik as $id => $s) {
            $this->db->query('UPDATE pkl_surat SET sidik = ?, cetak_ke = cetak_ke + 1, terakhir_cetak_at = ?, updated_at = ? WHERE pengajuan_id = ?', [$s, $now, $now, (int) $id]);
            (new PklAjuan($this->db))->catat((int) $id, 'cetak', $konteks, 'Surat ' . $nomor[$id] . ' diunduh');
        }

        return ['ok' => true, 'biner' => $biner, 'nama' => $nama, 'jumlah' => count($daftar), 'dilewati' => $lewat];
    }

    /** Contoh template: surat bawaan dengan penanda ${...} apa adanya, untuk diedit di Word lalu diunggah. */
    public function contohTemplate(): string
    {
        $setting = (new SettingModel())->get();
        $v       = [];
        foreach (self::SKALAR as $k) {
            $v[$k] = '${' . $k . '}';
        }
        $baris = ['no' => '${no}', 'nama' => '${siswa_nama}', 'nis' => '${siswa_nis}', 'nisn' => '${siswa_nisn}', 'kelas' => '${siswa_kelas}', 'jurusan' => '${siswa_jurusan}'];
        $logo  = $this->logo($setting);

        return PklDocx::dokumen([PklDocx::suratBawaan($v, [$baris], $logo)], $logo);
    }

    /** Nama berkas aman untuk unduhan. */
    public static function amanNama(string $s): string
    {
        $s = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $s) ?? '');

        return mb_substr($s !== '' ? $s : 'surat', 0, 80);
    }

    /** @param list<string> $berkas isi docx */
    private function zipkan(array $berkas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pklz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($berkas as $i => $isi) {
            $zip->addFromString('Surat PKL bagian ' . ($i + 1) . '.docx', $isi);
        }
        $zip->close();
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }
}
