<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Perakitan berkas Word surat sekolah (satu atau banyak surat sejenis) dari template per jenis (Libraries/Surat/*.docx),
 * memakai mesin yang sama dengan Surat Izin PKL (PklDocx::dariTemplate). Penanda ${…} UMUM disediakan di sini
 * (nomor, tanggal, perusahaan, siswa, Kepala Sekolah, blok Waka Hubin + tanda tangan digital, kaki "disetujui secara
 * elektronik"); penanda khusus tiap jenis ditambahkan oleh langkah jenis itu lewat self::khusus().
 *
 * Alur bangun(): surat harus berstatus 'disetujui' → nomor diterbitkan bila belum (SuratSekolah::terbitkan, urutan
 * bersama Surat Izin PKL) → berkas dirakit → baru setelah berkas jadi, jumlah unduhan + sidik + riwayat dicatat.
 * Gambar tanda tangan Waka Hubin HANYA dipasang bila yang meng-ACC memang akun Waka Hubin (ACC Admin → ruang dibiarkan
 * kosong dan kaki surat menjelaskan siapa yang menyetujui), sama dengan aturan Surat Izin PKL.
 */
final class SuratBerkas
{
    /** Batas surat per satu permintaan unduh. */
    public const MAKS_UNDUH = 300;

    /** Lebih dari ini → dipecah jadi ZIP beberapa berkas Word. */
    public const MAKS_SATU_BERKAS = 60;
    public const ISI_PER_BERKAS   = 50;

    private BaseConnection $db;
    private SuratSekolah $svc;

    public function __construct(?SuratSekolah $svc = null, ?BaseConnection $db = null)
    {
        $this->db  = $db ?? db_connect();
        $this->svc = $svc ?? new SuratSekolah($this->db);
    }

    /**
     * Terbitkan nomor (bila belum) dan rakit surat untuk banyak surat SEJENIS.
     *
     * $pathTemplate: ganti berkas template (dipakai uji / unggahan template kelak); bawaan = Libraries/Surat/{jenis}.docx.
     *
     * @param list<int>            $ids
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array{ok: true, biner: string, nama: string, jumlah: int, dilewati: int, ids: list<int>, zip: bool}|array{ok: false, kode: string, pesan: string}
     */
    public function bangun(array $ids, array $konteks, ?string $pathTemplate = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i) => $i > 0)));
        if ($ids === []) {
            return $this->gagal('kosong', 'Pilih dulu surat yang mau diunduh.');
        }
        if (count($ids) > self::MAKS_UNDUH) {
            return $this->gagal('terlalu_banyak', 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_UNDUH . ' surat). Pilih sebagian.');
        }

        // Urutan = urutan persetujuan (yang lebih dulu disetujui mendapat nomor lebih kecil).
        $baris = $this->db->table('surat_sekolah')->whereIn('id', $ids)->orderBy('COALESCE(acc_at, created_at)', 'ASC', false)->orderBy('id', 'ASC')->get()->getResultArray();
        if ($baris === []) {
            return $this->gagal('tidak_ada', 'Surat tidak ditemukan (mungkin sudah dihapus).');
        }
        $jenisUnik = array_values(array_unique(array_column($baris, 'jenis')));
        if (count($jenisUnik) > 1) {
            return $this->gagal('campur', 'Unduhan massal harus satu jenis surat. Saring dulu menurut jenisnya.');
        }
        $jenis = (string) $jenisUnik[0];

        // Template dicek SEBELUM nomor diterbitkan (nomor tak terpakai bila template tak ada). Tiap surat memakai template
        // varian-nya (mis. ASTS/TKA per perusahaan = dengan halaman Lampiran daftar siswa).
        $path = [];
        foreach ($baris as $r) {
            $v = SuratJenis::varian($jenis, SuratSekolah::dekodeIsi((string) ($r['isi'] ?? '')));
            if (! isset($path[$v])) {
                $path[$v] = $pathTemplate ?? SuratJenis::pathTemplate($jenis, $v);
                if (! is_file($path[$v])) {
                    return $this->gagal('template', 'Template Word untuk ' . SuratJenis::label($jenis) . ($v !== '' ? ' (varian ' . $v . ')' : '') . ' belum tersedia.');
                }
            }
        }

        $setting = (new SettingModel())->get();
        $p       = (new PklPengaturanModel())->ambil();
        $ttd     = PklSurat::infoTtd($p);
        $ekstra  = ['oleh' => (string) ($konteks['oleh'] ?? ''), 'peran' => (string) ($konteks['peran'] ?? ''), 'ttd' => $ttd, 'sekarang' => date('Y-m-d H:i:s')];

        $daftar   = [];
        $perVarian = [];
        $sidik    = [];
        $nomor    = [];
        $surat1   = null;
        $lewat    = 0;
        foreach ($baris as $r) {
            $id = (int) $r['id'];
            if ($r['status'] !== 'disetujui') {
                $lewat++;
                continue;
            }
            $t = $this->svc->terbitkan($id, $konteks);
            $m = $t['ok'] ? $this->svc->muat($id) : null;
            if ($m === null) {
                $lewat++;
                continue;
            }
            $ajuan = ! empty($m['surat']['pengajuan_id'])
                ? $this->db->table('pkl_pengajuan')->where('id', (int) $m['surat']['pengajuan_id'])->get()->getRowArray()
                : null;

            $item       = self::isi($m['surat'], $m['siswa'], $ajuan, $setting, $p, $ekstra);
            $daftar[]   = $item;
            $perVarian[SuratJenis::varian($jenis, $m['surat']['isi_arr'])][] = $item;
            $sidik[$id] = SuratSekolah::sidik($m['surat'], $m['siswa'], $p);
            $nomor[$id] = (string) ($m['surat']['nomor'] ?? '');
            $surat1   ??= $m['surat'];
        }
        if ($daftar === []) {
            return $this->gagal('kosong', 'Tidak ada surat yang bisa diunduh (hanya surat berstatus "Siap unduh").');
        }

        // Gambar tanda tangan Waka Hubin ikut dibawa bila ada surat yang memakainya.
        $media = [];
        if ($ttd !== null) {
            foreach ($daftar as $d) {
                if (str_contains((string) ($d['v']['ttd_hubin'] ?? ''), PklSurat::REL_TTD)) {
                    $media[] = ['id' => PklSurat::REL_TTD, 'ext' => $ttd['ext'], 'biner' => (string) file_get_contents($ttd['path'])];
                    break;
                }
            }
        }

        $label = SuratJenis::label($jenis);
        $zip   = false;
        try {
            if (count($perVarian) === 1 && count($daftar) <= self::MAKS_SATU_BERKAS) {
                $biner = PklDocx::dariTemplate($path[(string) array_key_first($perVarian)], $daftar, $media);
                $nama  = count($daftar) === 1
                    ? self::namaBerkas($surat1) . '.docx'
                    : $label . ' (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.docx';
            } else {
                // Lebih dari 60 surat, atau campuran varian (umum + per perusahaan): ZIP berisi satu/lebih berkas Word per varian.
                $berkas = [];
                foreach ($perVarian as $v => $isi) {
                    $akhiran = count($perVarian) > 1 ? ' ' . ($v === '' ? 'umum' : 'per ' . $v) : '';
                    $bagian  = array_chunk($isi, self::ISI_PER_BERKAS);
                    foreach ($bagian as $i => $potong) {
                        $berkas[PklSurat::amanNama($label . $akhiran) . (count($bagian) > 1 ? ' bagian ' . ($i + 1) : '') . '.docx'] = PklDocx::dariTemplate($path[(string) $v], $potong, $media);
                    }
                }
                $biner = $this->zipkan($berkas);
                $nama  = $label . ' (' . count($daftar) . ' surat) ' . date('Y-m-d') . '.zip';
                $zip   = true;
            }
        } catch (\Throwable $e) {
            log_message('error', '[Surat] rakit berkas gagal: ' . $e->getMessage());

            return $this->gagal('rakit', 'Berkas gagal dibuat: ' . $e->getMessage());
        }

        $now = date('Y-m-d H:i:s');
        foreach ($sidik as $id => $s) {
            $this->db->query('UPDATE surat_sekolah SET sidik = ?, cetak_ke = cetak_ke + 1, terakhir_cetak_at = ?, updated_at = ? WHERE id = ?', [$s, $now, $now, (int) $id]);
            $this->svc->catat((int) $id, 'cetak', $konteks, 'Berkas diunduh' . ($nomor[$id] !== '' ? ' (nomor ' . $nomor[$id] . ')' : ''));
        }
        $pertama = (int) array_key_first($sidik);
        (new AuditModel())->record('export', 'surat_sekolah', $pertama, SuratJenis::label($jenis) . ' diunduh: ' . count($daftar) . ' surat (' . mb_substr($nama, 0, 120) . ')');

        return ['ok' => true, 'biner' => $biner, 'nama' => $nama, 'jumlah' => count($daftar), 'dilewati' => $lewat, 'ids' => array_map('intval', array_keys($sidik)), 'zip' => $zip];
    }

    // =================================================================
    // Penanda surat
    // =================================================================

    /**
     * Penanda untuk satu surat.
     *
     * $ekstra: ['oleh' => nama pencetak, 'peran' => peran pencetak, 'ttd' => PklSurat::infoTtd() atau null, 'sekarang' => 'Y-m-d H:i:s'].
     *
     * @param array<string, mixed>       $surat   baris surat_sekolah
     * @param list<array<string, mixed>> $siswa   hasil SuratSekolah::siswa()
     * @param array<string, mixed>|null  $ajuan   baris pkl_pengajuan bila surat tertaut ke ajuan, selain itu null
     * @param array<string, mixed>       $setting pengaturan sekolah (SettingModel::get)
     * @param array<string, mixed>       $p       baris pkl_pengaturan
     * @param array<string, mixed>       $ekstra
     *
     * @return array{v: array<string, string>, siswa: list<array<string, string>>, meta: array<string, mixed>}
     */
    public static function isi(array $surat, array $siswa, ?array $ajuan, array $setting, array $p, array $ekstra = []): array
    {
        $baris  = [];
        $daftar = [];
        foreach (array_values($siswa) as $i => $a) {
            $hp = trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? '');
            $baris[] = [
                'no' => (string) ($i + 1), 'nama' => (string) $a['nama'], 'nis' => (string) ($a['nis'] ?? ''), 'nisn' => ((string) ($a['nisn'] ?? '')) !== '' ? (string) $a['nisn'] : '-',
                'kelas' => (string) ($a['nama_kelas'] ?? ''), 'jurusan' => ((string) ($a['jurusan_nama'] ?? '')) !== '' ? (string) $a['jurusan_nama'] : '-',
                'hp' => $hp !== '' ? $hp : '-',
            ];
            $daftar[] = ($i + 1) . '. ' . $a['nama'] . ' (' . ($a['nama_kelas'] ?? '-') . ')';
        }

        $alamat = trim((string) ($ajuan['perusahaan_alamat'] ?? ''));
        $kota   = trim((string) ($ajuan['perusahaan_kota'] ?? ''));
        $kontak = trim((string) ($ajuan['kontak_nama'] ?? ''));
        $jab    = trim((string) ($ajuan['kontak_jabatan'] ?? ''));

        $hubinNama    = IsianBantu::rapikanGelar((string) ($p['waka_hubin_nama'] ?? ''));
        $hubinJabatan = trim((string) ($p['waka_hubin_jabatan'] ?? '')) !== '' ? trim((string) $p['waka_hubin_jabatan']) : 'Wakil Kepala Sekolah Bidang Hubungan Industri';
        [$jab1, $jab2] = PklSurat::bagiDuaBaris($hubinJabatan, 30);

        // Tanda tangan digital Waka Hubin HANYA bila yang meng-ACC memang akun Waka Hubin.
        $ttd    = $ekstra['ttd'] ?? null;
        $ttdRaw = ($ttd !== null && (int) $surat['perlu_acc'] === 1 && ($surat['acc_peran'] ?? '') === 'hubin')
            ? PklDocx::gambarTtd(PklSurat::REL_TTD, (int) $ttd['cx'], (int) $ttd['cy'])
            : '';

        $v = [
            'nomor'         => (string) ($surat['nomor'] ?? ''),
            'tanggal'       => IsianBantu::tanggalIndo((string) $surat['tanggal_surat']),
            'tanggal_surat' => PklSurat::tanggalSurat((string) $surat['tanggal_surat']),
            'kota_surat'    => (string) ($setting['city'] ?? ''),
            'tahun_ajaran'  => (string) ($setting['academic_year'] ?? ''),
            'perusahaan'    => (string) ($surat['perusahaan_nama'] ?? '') !== '' ? (string) $surat['perusahaan_nama'] : (string) ($ajuan['perusahaan_nama'] ?? ''),
            'perusahaan_alamat' => $alamat, 'perusahaan_kota' => $kota,
            'alamat_lengkap' => trim($alamat . ($kota !== '' && ! str_contains(mb_strtolower($alamat), mb_strtolower($kota)) ? ', ' . $kota : '')),
            'perusahaan_telepon' => (string) ($ajuan['perusahaan_telepon'] ?? ''), 'kontak_nama' => $kontak, 'kontak_jabatan' => $jab,
            'penerima'      => $kontak !== '' ? $kontak . ($jab !== '' ? ' (' . $jab . ')' : '') : 'Pimpinan',
            'jumlah_siswa'  => (string) count($baris), 'siswa_daftar' => implode("\n", $daftar),
            'sekolah' => (string) ($setting['school_name'] ?? ''), 'sekolah_alamat' => (string) ($setting['address'] ?? ''),
            'sekolah_telepon' => (string) ($setting['phone'] ?? ''), 'sekolah_email' => (string) ($setting['email'] ?? ''),
            'kepsek_nama'   => IsianBantu::rapikanGelar((string) ($p['kepsek_nama'] ?? '')),
            'kontak_sekolah_nama' => IsianBantu::rapikanGelar((string) ($p['kontak_surat_nama'] ?? '')), 'kontak_sekolah_hp' => (string) ($p['kontak_surat_hp'] ?? ''),
            'hubin_nama'    => $hubinNama !== '' ? $hubinNama : '........................................',
            'hubin_jabatan' => $hubinJabatan, 'hubin_jabatan_1' => $jab1, 'hubin_jabatan_2' => $jab2,
            'ttd_hubin'     => $ttdRaw,
            'acc_footer'    => self::kakiSurat($surat, $p, $ekstra),
            'acc_oleh'      => (string) ($surat['acc_nama'] ?? ''), 'acc_peran' => (string) ($surat['acc_peran'] ?? ''),
            'acc_waktu'     => ! empty($surat['acc_at']) ? PklSurat::waktuIndo((string) $surat['acc_at']) : '', 'acc_kode' => (string) ($surat['acc_kode'] ?? ''),
        ];
        $v = array_merge($v, self::khusus((string) $surat['jenis'], $surat, $siswa, $ajuan, $setting, $p));

        return [
            'v'     => $v,
            'siswa' => $baris,
            'meta'  => [
                'urut'       => (int) ($surat['urut'] ?? 0),
                'nomor'      => (string) ($surat['nomor'] ?? ''),
                'jumlah'     => count($baris),
                'perusahaan' => $v['perusahaan'],
            ],
        ];
    }

    /**
     * Penanda KHUSUS jenis (kegiatan, tanggal, sesi, periode, alasan, sapaan …). Diisi di langkah tiap jenis;
     * sampai saat itu hanya penanda umum yang tersedia.
     *
     * @param array<string, mixed>       $surat
     * @param list<array<string, mixed>> $siswa
     * @param array<string, mixed>|null  $ajuan
     * @param array<string, mixed>       $setting
     * @param array<string, mixed>       $p
     *
     * @return array<string, string>
     */
    private static function khusus(string $jenis, array $surat, array $siswa, ?array $ajuan, array $setting, array $p): array
    {
        return match ($jenis) {
            SuratJenis::ASTS, SuratJenis::TKA => SuratAcara::token($jenis, $surat, SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''))),
            default => [],
        };
    }

    /**
     * Kaki surat. Surat wajib-ACC: catatan ACC lengkap (siapa, kapan, kode verifikasi) + siapa yang mencetak, apa adanya
     * seperti Surat Izin PKL (ACC Admin ditulis "mewakili Waka Hubin"). Surat tanpa ACC: hanya keterangan cetak.
     *
     * @param array<string, mixed> $surat
     * @param array<string, mixed> $p
     * @param array<string, mixed> $ekstra
     */
    public static function kakiSurat(array $surat, array $p, array $ekstra = []): string
    {
        if ((int) ($surat['perlu_acc'] ?? 1) === 1) {
            return PklSurat::catatanAcc($surat, $p, $ekstra);
        }
        if (empty($ekstra['oleh'])) {
            return '';
        }

        return 'Dicetak melalui Sistem Informasi Akademik Sekolah (BINUS) oleh ' . $ekstra['oleh']
            . (! empty($ekstra['peran']) ? ' (' . HakAkses::label((string) $ekstra['peran']) . ')' : '')
            . ' pada ' . PklSurat::waktuIndo((string) ($ekstra['sekarang'] ?? date('Y-m-d H:i:s'))) . '.';
    }

    /** Nama berkas satu surat: "{urut} {Jenis} - {judul}" (urut dilewati bila tanpa nomor), aman untuk sistem berkas. */
    public static function namaBerkas(array $surat): string
    {
        $urut = (int) ($surat['urut'] ?? 0);
        $nama = ($urut > 0 ? $urut . ' ' : '') . SuratJenis::label((string) $surat['jenis']) . ' - ' . IsianBantu::rapikan((string) $surat['judul']);

        return PklSurat::amanNama($nama);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** @param array<string, string> $berkas nama berkas → isi docx */
    private function zipkan(array $berkas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'srtz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($berkas as $nama => $isi) {
            $zip->addFromString((string) $nama, $isi);
        }
        $zip->close();
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }

    /** @return array{ok: false, kode: string, pesan: string} */
    private function gagal(string $kode, string $pesan): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan];
    }
}
