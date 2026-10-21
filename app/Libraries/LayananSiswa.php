<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * LAYANAN SISWA — satu link bagi siswa untuk meminta surat sekolah sendiri (permintaan klien 2026-10-21; rancangan:
 * docs/DESAIN-SURAT-SEKOLAH.md bagian 10). Tahap T2: masuk + kelayakan + status permintaan; formulir surat menyusul (T3/T4).
 *
 *   - Masuk: pilih kelas → pilih nama → NISN ATAU tanggal lahir (HP siswa di data sekolah hampir kosong; NISN & tanggal lahir
 *     hampir lengkap). Percobaan salah dibatasi LoginThrottle. Sesi berlaku 30 menit sejak aktivitas terakhir; tombol Keluar.
 *   - Kelayakan tiap surat dihitung dari data sistem (ajuan PKL disetujui, tingkat kelas, jadwal yang dibuka staf), lengkap
 *     dengan kalimat alasan bila belum bisa — supaya siswa tidak menebak-nebak.
 *   - Status permintaan dibaca dari status suratnya (surat_permintaan → surat_sekolah).
 */
final class LayananSiswa
{
    public const SESI = 'layanan_siswa';
    public const TTL  = 1800;

    /**
     * Layanan surat untuk siswa: kunci => [judul, keterangan, ikon, jenis surat]. Urutan = urutan tampil.
     */
    public const SURAT = [
        'izin_ujian' => ['Surat Izin Ujian', 'Surat untuk perusahaan tempat PKL: izin mengikuti ASTS, ASAS, atau ASAT di sekolah.', 'calendar-month', SuratJenis::ASTS],
        'izin_tka'   => ['Surat Izin TKA', 'Khusus kelas XII: surat untuk perusahaan tempat PKL agar kamu bisa mengikuti TKA.', 'clipboard-list', SuratJenis::TKA],
        'pernyataan' => ['Surat Pernyataan Orang Tua', 'Formulir persetujuan orang tua untuk PKL. Data orang tuamu tercetak, tinggal ditandatangani.', 'users', SuratJenis::PERNYATAAN],
        'penarikan'  => ['Surat Penarikan PKL', 'Surat dari sekolah untuk menarik kamu dari tempat PKL (misalnya PKL dihentikan).', 'arrow-right-to-bracket', SuratJenis::PENARIKAN],
    ];

    /** Layanan surat yang formulirnya SUDAH dibangun (T3: izin_ujian, izin_tka · T4: pernyataan, penarikan). Sisanya "Segera". */
    public const SIAP = ['izin_ujian', 'izin_tka', 'pernyataan', 'penarikan'];

    /** Tujuan setelah masuk yang sah (kunci SURAT + 'status'). */
    public static function tujuanSah(?string $t): string
    {
        $t = (string) $t;

        return isset(self::SURAT[$t]) || $t === 'status' ? $t : '';
    }

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function tabelAda(): bool
    {
        return SuratJadwal::tabelAda();
    }

    // =================================================================
    // Cari nama
    // =================================================================

    /**
     * Tingkat kelas yang dilayani: tingkat yang boleh PKL (Pengaturan PKL) + XII (TKA). Kosong → XI & XII.
     *
     * @return list<string>
     */
    public static function tingkat(?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $t   = PklPengaturanModel::tingkatBoleh($p);

        return array_values(array_unique(array_merge($t === [] ? ['XI'] : $t, ['XII'])));
    }

    /**
     * Kelas (yang punya siswa aktif) pada tingkat yang dilayani, per tingkat, urut alami.
     *
     * @return array<string, list<array{id: int, nama: string}>>
     */
    public function kelas(?array $p = null): array
    {
        $rows = $this->db->table('kelas k')
            ->select('k.id, k.nama_kelas, k.tingkat')
            ->join('siswa s', 's.kelas_id = k.id')
            ->where('k.deleted_at', null)->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->whereIn('k.tingkat', self::tingkat($p))
            ->groupBy('k.id')
            ->get()->getResultArray();
        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($rows, static fn ($a, $b) => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9)) ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['tingkat']][] = ['id' => (int) $r['id'], 'nama' => (string) $r['nama_kelas']];
        }

        return $out;
    }

    /**
     * Nama siswa aktif satu kelas (hanya id + nama — daftar ini bisa dilihat siapa saja). Kelas di luar tingkat yang dilayani → [].
     *
     * @return list<array{id: int, nama: string}>
     */
    public function siswaKelas(int $kelasId, ?array $p = null): array
    {
        $k = $this->db->table('kelas')->select('tingkat')->where('id', $kelasId)->where('deleted_at', null)->get()->getRowArray();
        if ($k === null || ! in_array((string) $k['tingkat'], self::tingkat($p), true)) {
            return [];
        }

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nama' => (string) $r['nama']], $this->db->table('siswa')
            ->select('id, nama')->where('kelas_id', $kelasId)->where('status', 'aktif')->where('deleted_at', null)
            ->orderBy('nama', 'ASC')->get()->getResultArray());
    }

    // =================================================================
    // Masuk / keluar
    // =================================================================

    /**
     * Siswa membuktikan dirinya dengan NISN ATAU tanggal lahir (cukup salah satu cocok).
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function masuk(int $siswaId, string $nisnIn, string $tglIn, string $ip, ?array $p = null): array
    {
        $s = $siswaId > 0 ? $this->db->table('siswa s')->select('s.id, s.nama, s.nisn, s.tanggal_lahir, k.tingkat')
            ->join('kelas k', 'k.id = s.kelas_id')->where('s.id', $siswaId)->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->get()->getRowArray() : null;
        if ($s === null || ! in_array((string) $s['tingkat'], self::tingkat($p), true)) {
            return ['ok' => false, 'http' => 404, 'pesan' => 'Data siswa tidak ditemukan. Pilih ulang kelas dan namamu.'];
        }
        $nisn = preg_replace('/\D/', '', $nisnIn) ?? '';
        $thn  = (int) date('Y');
        $tgl  = trim($tglIn) !== '' ? IsianBantu::tanggal(trim($tglIn), $thn - 40, $thn) : null;
        if ($nisn === '' && trim($tglIn) === '') {
            return ['ok' => false, 'http' => 422, 'pesan' => 'Isi NISN atau tanggal lahirmu (cukup salah satu).'];
        }
        $nisnData = preg_replace('/\D/', '', (string) ($s['nisn'] ?? '')) ?? '';
        $tglData  = (string) ($s['tanggal_lahir'] ?? '');
        if ($nisnData === '' && $tglData === '') {
            return ['ok' => false, 'http' => 409, 'pesan' => 'NISN dan tanggal lahirmu belum tercatat di data sekolah. Hubungi operator sekolah.'];
        }

        $throttle = new LoginThrottle();
        $kunci    = 'layanan:' . $siswaId;
        $sisa     = $throttle->retryAfter($kunci, $ip);
        if ($sisa > 0) {
            return ['ok' => false, 'http' => 429, 'pesan' => 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($sisa / 60) . ' menit.'];
        }
        $cocok = ($nisn !== '' && $nisnData !== '' && $nisn === $nisnData) || ($tgl !== null && $tglData !== '' && $tgl === $tglData);
        if (! $cocok) {
            $throttle->hit($kunci, $ip, 'layanan');

            return ['ok' => false, 'http' => 422, 'pesan' => 'NISN atau tanggal lahir tidak cocok dengan data sekolah. Periksa lagi (tanggal lahir sesuai rapor/akta).'];
        }
        $throttle->clear($kunci, $ip);
        if (! is_cli()) {
            session()->regenerate(); // id sesi baru setelah masuk (cegah fiksasi sesi)
        }
        session()->set(self::SESI, ['id' => (int) $s['id'], 't' => time()]);

        return ['ok' => true, 'http' => 200, 'pesan' => 'Halo, ' . $s['nama'] . '!'];
    }

    /** Id siswa yang sedang masuk (sesi belum kedaluwarsa; waktu aktivitas diperbarui), atau null. */
    public static function siswaMasuk(): ?int
    {
        $sesi = session(self::SESI);
        if (! is_array($sesi) || (int) ($sesi['id'] ?? 0) <= 0) {
            return null;
        }
        if (time() - (int) ($sesi['t'] ?? 0) > self::TTL) {
            session()->remove(self::SESI);

            return null;
        }
        session()->set(self::SESI, ['id' => (int) $sesi['id'], 't' => time()]);

        return (int) $sesi['id'];
    }

    public static function keluar(): void
    {
        session()->remove(self::SESI);
    }

    // =================================================================
    // Profil, kelayakan, permintaan
    // =================================================================

    /** Siswa aktif + kelas + tingkat, atau null. */
    public function profil(int $id): ?array
    {
        $s = $this->db->table('siswa s')->select('s.id, s.nama, s.nisn, s.kelas_id, k.nama_kelas, k.tingkat')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->where('s.id', $id)->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->get()->getRowArray();
        if ($s === null) {
            return null;
        }
        $s['id']    = (int) $s['id'];
        $s['ajuan'] = (new PklPengajuanModel())->aktifMilik($id);

        return $s;
    }

    /**
     * Bisa / tidaknya tiap layanan surat untuk siswa ini, dengan alasan yang bisa dibaca siswa.
     *
     * @param array<string, mixed> $s profil()
     *
     * @return array<string, array{boleh: bool, siap: bool, alasan: string, jadwal: list<array<string, mixed>>}>
     */
    public function kelayakan(array $s, ?array $p = null): array
    {
        $p       ??= (new PklPengaturanModel())->ambil();
        $tingkat   = (string) ($s['tingkat'] ?? '');
        $disetujui = ($s['ajuan']['status'] ?? '') === 'disetujui';
        $jadwal    = new SuratJadwal($this->db);
        $tanpaPkl  = $s['ajuan'] === null
            ? 'Khusus siswa yang sedang PKL: ajuan PKL-mu harus sudah disetujui sekolah. Kamu belum punya ajuan PKL.'
            : 'Khusus siswa yang sedang PKL: ajuan PKL-mu (' . PklPengajuanModel::kode((int) $s['ajuan']['id']) . ') belum disetujui sekolah.';
        $out = [];
        foreach (self::SURAT as $kunci => [, , , $jenis]) {
            $jd     = in_array($jenis, SuratJadwal::JENIS, true) ? $jadwal->terbuka($jenis) : [];
            $alasan = match ($kunci) {
                'izin_ujian' => ! $disetujui ? $tanpaPkl : ($jd === [] ? 'Belum ada jadwal ujian yang dibuka sekolah. Tunggu pengumuman sekolah.' : ''),
                'izin_tka'   => $tingkat !== 'XII' ? 'Khusus siswa kelas XII.' : (! $disetujui ? $tanpaPkl : ($jd === [] ? 'Belum ada jadwal TKA yang dibuka sekolah. Tunggu pengumuman sekolah.' : '')),
                'pernyataan' => ! in_array($tingkat, PklPengaturanModel::tingkatBoleh($p), true) ? 'Khusus kelas yang mengikuti PKL.' : '',
                'penarikan'  => ! $disetujui ? 'Khusus siswa yang sedang PKL (ajuan PKL sudah disetujui sekolah).' : '',
                default      => '',
            };
            $out[$kunci] = ['boleh' => $alasan === '', 'siap' => in_array($kunci, self::SIAP, true), 'alasan' => $alasan, 'jadwal' => $jd];
        }

        return $out;
    }

    // =================================================================
    // T3 — Surat Izin Ujian / Izin TKA (satu surat per perusahaan per jadwal)
    // =================================================================

    /**
     * Kelompok perusahaan siswa ini untuk surat acara jenis $jenis (semua siswa PKL di perusahaan yang sama; TKA: hanya kelas XII),
     * atau null bila siswa tidak tercatat di sana.
     *
     * @return array<string, mixed>|null butir SuratAcara::perusahaan()
     */
    public function grupPerusahaan(int $siswaId, string $jenis, ?string $mulai = null, ?string $selesai = null): ?array
    {
        foreach ((new SuratAcara($this->db))->perusahaan($mulai, $selesai, $jenis) as $g) {
            if (in_array($siswaId, array_column($g['siswa'], 'siswa_id'), true)) {
                return $g;
            }
        }

        return null;
    }

    /** Permintaan aktif siswa untuk jadwal ini (suratnya belum dibatalkan): ['surat_id', 'kode'] atau null. */
    public function sudahDiminta(int $siswaId, int $jadwalId): ?array
    {
        if (! self::tabelAda()) {
            return null;
        }
        $r = $this->db->table('surat_permintaan sp')->select('sp.surat_id')
            ->join('surat_sekolah ss', 'ss.id = sp.surat_id')
            ->where('sp.siswa_id', $siswaId)->where('sp.jadwal_id', $jadwalId)->where('ss.status !=', 'dibatalkan')
            ->orderBy('sp.id', 'DESC')->get(1)->getRowArray();

        return $r === null ? null : ['surat_id' => (int) $r['surat_id'], 'kode' => \App\Models\SuratSekolahModel::kode((int) $r['surat_id'])];
    }

    /**
     * Data formulir Izin Ujian / Izin TKA: jadwal yang dibuka (+ sudah diminta? + di luar periode PKL?), perusahaan, teman satu surat.
     *
     * @param array<string, mixed> $s profil()
     *
     * @return array{jadwal: list<array<string, mixed>>, perusahaan: array<string, mixed>|null, teman: list<string>}
     */
    public function formIzin(array $s, string $kunci, array $kelayakan): array
    {
        $jenis = self::SURAT[$kunci][3];
        $g     = $this->grupPerusahaan((int) $s['id'], $jenis);
        $out   = [];
        foreach ($kelayakan[$kunci]['jadwal'] as $j) {
            $luar  = $g !== null && $g['mulai'] !== null && $g['selesai'] !== null && ($g['selesai'] < $j['tgl_mulai'] || $g['mulai'] > $j['tgl_selesai']);
            $out[] = $j + ['sudah' => $this->sudahDiminta((int) $s['id'], (int) $j['id']), 'luar_periode' => $luar];
        }

        return [
            'jadwal'     => $out,
            'perusahaan' => $g === null ? null : ['nama' => $g['nama'], 'alamat' => trim($g['alamat'] . ($g['kota'] !== '' && ! str_contains(mb_strtolower($g['alamat']), mb_strtolower($g['kota'])) ? ', ' . $g['kota'] : ''))],
            'teman'      => $g === null ? [] : array_values(array_column(array_filter($g['siswa'], static fn (array $x): bool => $x['siswa_id'] !== (int) $s['id']), 'nama')),
        ];
    }

    /**
     * Siswa meminta Surat Izin Ujian / Izin TKA untuk jadwal $jadwalId. Satu surat per perusahaan per jadwal: bila surat untuk
     * perusahaannya sudah ada (diminta teman atau dibuat operator), permintaan ditautkan ke surat itu — namanya ditambahkan bila
     * suratnya masih bisa diubah. Surat baru masuk antrean ACC (sumber 'siswa').
     *
     * kode: baru | ikut | sudah (sudah pernah minta) | tolak (tidak memenuhi syarat) | gagal
     *
     * @return array{ok: bool, kode: string, pesan: string, surat_id?: int}
     */
    public function ajukanIzin(int $siswaId, string $kunci, int $jadwalId, string $ip, ?array $p = null): array
    {
        if (! isset(self::SURAT[$kunci]) || ! in_array(self::SURAT[$kunci][3], SuratJadwal::JENIS, true)) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Jenis surat tidak dikenal.'];
        }
        $p   ??= (new PklPengaturanModel())->ambil();
        $jenis = self::SURAT[$kunci][3];
        $s     = $this->profil($siswaId);
        if ($s === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data siswa tidak ditemukan. Masuk lagi.'];
        }
        $k = $this->kelayakan($s, $p)[$kunci];
        if (! $k['boleh']) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => $k['alasan']];
        }
        $j = null;
        foreach ($k['jadwal'] as $x) {
            $j = (int) $x['id'] === $jadwalId ? $x : $j;
        }
        if ($j === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Pilih salah satu jadwal yang tersedia.'];
        }
        if (($lama = $this->sudahDiminta($siswaId, $jadwalId)) !== null) {
            return ['ok' => true, 'kode' => 'sudah', 'surat_id' => $lama['surat_id'], 'pesan' => 'Kamu sudah meminta surat ini sebelumnya. Statusnya ada di bawah.'];
        }
        $g = $this->grupPerusahaan($siswaId, $jenis, (string) $j['tgl_mulai'], (string) $j['tgl_selesai']);
        if ($g === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data PKL-mu belum lengkap untuk surat ini. Hubungi operator sekolah.'];
        }

        $isi     = SuratJadwal::isiSurat($j);
        $sidik   = SuratAcara::kunci($jenis, $isi, $g['kunci']);
        $konteks = ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip, 'saluran' => 'layanan'];
        $svc     = new SuratSekolah($this->db);
        $ada     = $svc->adaKunci($sidik);
        $kode    = 'ikut';
        if ($ada !== null) {
            $suratId = $ada['id'];
            $m       = $svc->muat($suratId);
            if ($m !== null && ! in_array($siswaId, array_map('intval', array_column($m['siswa'], 'siswa_id')), true)) {
                if (SuratSekolah::alasanTakBisaUbah($m['surat']) !== null) {
                    return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Surat untuk ' . $g['nama'] . ' (' . \App\Models\SuratSekolahModel::kode($suratId) . ') sudah disetujui sebelum namamu tercatat. Hubungi operator sekolah agar namamu ditambahkan.'];
                }
                $rows   = array_map(static fn (array $x): array => ['siswa_id' => (int) $x['siswa_id'], 'kelas_id' => $x['kelas_id'] ?? null, 'hp' => $x['hp'] ?? null], $m['siswa']);
                $saya   = null;
                foreach ($g['siswa'] as $x) {
                    $saya = $x['siswa_id'] === $siswaId ? $x : $saya;
                }
                $rows[] = ['siswa_id' => $siswaId, 'kelas_id' => $saya['kelas_id'] ?? null, 'hp' => $saya['hp'] ?? null];
                $u      = $svc->ubah($suratId, [
                    'judul' => SuratAcara::judul($jenis, $m['surat']['isi_arr'], (string) $m['surat']['perusahaan_nama'], count($rows)),
                    'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'isi' => $m['surat']['isi_arr'], 'siswa' => $rows,
                ], $konteks);
                if (! $u['ok']) {
                    return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Permintaan gagal disimpan. Coba lagi sebentar.'];
                }
            }
            $svc->catat($suratId, 'minta', $konteks, 'Diminta juga oleh ' . $s['nama'] . ' (' . ($s['nama_kelas'] ?? '-') . ')');
        } else {
            $kode = 'baru';
            $r    = $svc->buat($jenis, [
                'judul' => SuratAcara::judul($jenis, $isi, $g['nama'], count($g['siswa'])), 'tanggal_surat' => date('Y-m-d'),
                'isi' => $isi + ['pkey' => $g['kunci'], 'kunci' => $sidik, 'jadwal_id' => (int) $j['id']],
                'perusahaan_nama' => $g['nama'], 'pengajuan_id' => count($g['ajuan_ids']) === 1 ? $g['ajuan_ids'][0] : null,
                'siswa' => array_map(static fn (array $x): array => ['siswa_id' => $x['siswa_id'], 'kelas_id' => $x['kelas_id'], 'hp' => $x['hp']], $g['siswa']),
                'sumber' => 'siswa',
            ], $konteks, $p);
            if (! $r['ok']) {
                return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Permintaan gagal disimpan. Coba lagi sebentar.'];
            }
            $suratId = (int) $r['id'];
        }
        $this->simpanPermintaan($jenis, $s, $suratId, (int) $j['id'], null, $ip);

        return ['ok' => true, 'kode' => $kode, 'surat_id' => $suratId, 'pesan' => $kode === 'baru'
            ? 'Permintaan terkirim. ' . SuratJenis::label($jenis) . ' untuk ' . $g['nama'] . ' (' . SuratJadwal::label($j) . ') sedang menunggu ACC operator. Pantau statusnya di bawah.'
            : 'Permintaan terkirim. Surat untuk ' . $g['nama'] . ' (' . SuratJadwal::label($j) . ') sudah diajukan sebelumnya, dan namamu ' . (count($g['siswa']) > 1 ? 'ikut tercantum di surat itu' : 'tercantum') . '. Pantau statusnya di bawah.'];
    }

    // =================================================================
    // T4 — Pernyataan Orang Tua & Penarikan PKL (surat untuk siswa itu sendiri)
    // =================================================================

    /** Batas panjang isian data orang tua. */
    public const MAKS_ORTU = ['nama' => 100, 'pekerjaan' => 60, 'alamat' => 300, 'hp' => 20];

    /** Alasan penarikan yang bisa dipilih SISWA: indeks SuratPkl::ALASAN_SARAN => teks tombol (lainnya = ketik sendiri). */
    public const ALASAN_PENARIKAN = [
        '0' => 'Ada keperluan administrasi sekolah yang harus diselesaikan',
        '1' => 'Pindah ke tempat PKL lain',
        '3' => 'Permintaan orang tua/wali',
        '4' => 'Sakit, tidak bisa melanjutkan PKL',
    ];

    /**
     * Permintaan terakhir siswa untuk jenis $jenis yang suratnya belum dibatalkan, atau null.
     *
     * @return array{id: int, surat_id: int, kode: string, status: string, cetak_ke: int, isian: array<string, mixed>}|null
     */
    public function permintaanAktif(int $siswaId, string $jenis): ?array
    {
        if (! self::tabelAda()) {
            return null;
        }
        $r = $this->db->table('surat_permintaan sp')->select('sp.id, sp.isian, ss.id AS surat_id, ss.status, ss.cetak_ke')
            ->join('surat_sekolah ss', 'ss.id = sp.surat_id')
            ->where('sp.siswa_id', $siswaId)->where('sp.jenis', $jenis)->where('ss.status !=', 'dibatalkan')
            ->orderBy('sp.id', 'DESC')->get(1)->getRowArray();
        if ($r === null) {
            return null;
        }
        $isian = json_decode((string) ($r['isian'] ?? ''), true);

        return ['id' => (int) $r['id'], 'surat_id' => (int) $r['surat_id'], 'kode' => \App\Models\SuratSekolahModel::kode((int) $r['surat_id']),
            'status' => (string) $r['status'], 'cetak_ke' => (int) $r['cetak_ke'], 'isian' => is_array($isian) ? $isian : []];
    }

    /**
     * Isian awal formulir Pernyataan Orang Tua: data orang tua dari permintaan terakhir, bila belum ada dari data sekolah
     * (SuratPernyataan::ortu — sama dengan yang dicetak operator); HP siswa dari ajuan PKL / data sekolah.
     *
     * @return array{ortu: array{nama: string, pekerjaan: string, alamat: string, hp: string}, hp_anak: string, aktif: array|null}
     */
    public function formPernyataan(array $s): array
    {
        $id    = (int) $s['id'];
        $aktif = $this->permintaanAktif($id, SuratJenis::PERNYATAAN);
        $row   = $this->db->table('siswa')->select('nama_orang_tua, nama_ayah, nama_ibu, nama_wali, pekerjaan_ayah, pekerjaan_ibu, pekerjaan_wali, ortu_alamat, ortu_rt, ortu_rw, ortu_kelurahan, ortu_kecamatan, ortu_kota, ortu_telepon, no_hp_wali, alamat, rt, rw, kelurahan, kecamatan, kota, no_hp')
            ->where('id', $id)->get()->getRowArray() ?? [];
        $ortu  = is_array($aktif['isian']['ortu'] ?? null) ? $aktif['isian']['ortu'] + ['nama' => '', 'pekerjaan' => '', 'alamat' => '', 'hp' => ''] : SuratPernyataan::ortu($row);
        $hp    = (string) ($aktif['isian']['hp_anak'] ?? '');
        if ($hp === '') {
            $hp = (string) ($this->db->table('pkl_anggota')->select('hp')->where('siswa_aktif', $id)->get()->getRowArray()['hp'] ?? '') ?: (string) ($row['no_hp'] ?? '');
        }

        return ['ortu' => $ortu, 'hp_anak' => $hp, 'aktif' => $aktif];
    }

    /**
     * Siswa meminta Surat Pernyataan Orang Tua dengan data orang tua yang ia isi (dicetak menimpa data sekolah). Jenis ini tanpa
     * nomor & tanpa ACC: surat langsung siap dicetak operator. Permintaan yang belum dicetak boleh diperbarui datanya.
     *
     * kode: baru | ubah | ikut | tolak | gagal
     *
     * @param array<string, mixed> $in nama, pekerjaan, alamat, hp (orang tua), hp_anak
     *
     * @return array{ok: bool, kode: string, pesan: string, galat?: array<string, string>, surat_id?: int}
     */
    public function ajukanPernyataan(int $siswaId, array $in, string $ip, ?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $s   = $this->profil($siswaId);
        if ($s === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data siswa tidak ditemukan. Masuk lagi.'];
        }
        $k = $this->kelayakan($s, $p)['pernyataan'];
        if (! $k['boleh']) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => $k['alasan']];
        }
        $d     = [];
        $galat = [];
        foreach (self::MAKS_ORTU as $kol => $maks) {
            $d[$kol] = IsianBantu::rapikan((string) ($in[$kol] ?? ''));
            if (mb_strlen($d[$kol]) > $maks) {
                $galat[$kol] = 'Terlalu panjang (maksimal ' . $maks . ' huruf).';
            }
        }
        if ($d['nama'] === '') {
            $galat['nama'] = 'Isi nama orang tua/wali.';
        }
        if ($d['alamat'] === '') {
            $galat['alamat'] = 'Isi alamat orang tua/wali.';
        }
        if ($d['hp'] !== '') {
            $d['hp'] = IsianBantu::telepon($d['hp']);
            if (! IsianBantu::teleponSah($d['hp'])) {
                $galat['hp'] = 'Nomor HP orang tua tidak valid (contoh: 081234567890).';
            }
        }
        $hpAnak = IsianBantu::telepon((string) ($in['hp_anak'] ?? ''));
        if ($hpAnak !== '' && ! IsianBantu::hpSah($hpAnak)) {
            $galat['hp_anak'] = 'Nomor HP-mu tidak valid (contoh: 081234567890).';
        }
        if ($galat !== []) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Periksa lagi isianmu.', 'galat' => $galat];
        }

        $konteks = ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip, 'saluran' => 'layanan'];
        $isian   = ['ortu' => $d, 'hp_anak' => $hpAnak];
        $svc     = new SuratSekolah($this->db);
        $aktif   = $this->permintaanAktif($siswaId, SuratJenis::PERNYATAAN);
        $suratId = $aktif['surat_id'] ?? null;
        $kode    = 'ubah';
        if ($suratId === null) {
            $kunci = sha1('pernyataan|siswa|' . $siswaId . '|' . date('Y') . '|1'); // sama dengan surat "siswa pilihan" buatan operator
            $ada   = $svc->adaKunci($kunci);
            if ($ada !== null) {
                $suratId = $ada['id'];
                $kode    = 'ikut';
            } else {
                $r = $svc->buat(SuratJenis::PERNYATAAN, [
                    'judul' => (string) $s['nama'], 'tanggal_surat' => date('Y-m-d'),
                    'isi' => ['cakupan' => 'siswa', 'isi_ortu' => true, 'kunci' => $kunci, 'ortu_isian' => [(string) $siswaId => $d]],
                    'siswa' => [['siswa_id' => $siswaId, 'kelas_id' => ((int) ($s['kelas_id'] ?? 0)) ?: null, 'hp' => $hpAnak !== '' ? $hpAnak : null]],
                    'sumber' => 'siswa',
                ], $konteks, $p);
                if (! $r['ok']) {
                    return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Permintaan gagal disimpan. Coba lagi sebentar.'];
                }
                $suratId = (int) $r['id'];
                $kode    = 'baru';
            }
        }
        if ($kode !== 'baru') {
            // Surat yang sudah ada: data orang tua diperbarui selama belum dicetak (setelah dicetak → minta operator).
            $m = $svc->muat($suratId);
            if ($m === null) {
                return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Surat tidak ditemukan. Coba lagi.'];
            }
            if ((int) $m['surat']['cetak_ke'] > 0) {
                return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Surat Pernyataan Orang Tua-mu (' . \App\Models\SuratSekolahModel::kode($suratId) . ') sudah dicetak sekolah. Bila datanya salah, minta operator sekolah membetulkan dan mencetak ulang.'];
            }
            $isiSurat = $m['surat']['isi_arr'];
            $isiSurat['ortu_isian'] = is_array($isiSurat['ortu_isian'] ?? null) ? $isiSurat['ortu_isian'] : [];
            $isiSurat['ortu_isian'][(string) $siswaId] = $d;
            $this->db->table('surat_sekolah')->where('id', $suratId)->update(['isi' => json_encode($isiSurat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => date('Y-m-d H:i:s')]);
            if ($hpAnak !== '') {
                $this->db->table('surat_sekolah_siswa')->where('surat_id', $suratId)->where('siswa_id', $siswaId)->update(['hp' => $hpAnak]);
            }
            $svc->catat($suratId, $kode === 'ikut' ? 'minta' : 'ubah', $konteks, 'Data orang tua diisi/diperbarui oleh ' . $s['nama'] . ' (Layanan Siswa)');
        }
        if ($aktif !== null) {
            $this->db->table('surat_permintaan')->where('id', $aktif['id'])->update(['isian' => json_encode($isian, JSON_UNESCAPED_UNICODE)]);
        } else {
            $this->simpanPermintaan(SuratJenis::PERNYATAAN, $s, $suratId, null, $isian, $ip);
        }

        return ['ok' => true, 'kode' => $kode, 'surat_id' => $suratId, 'pesan' => $kode === 'ubah'
            ? 'Data orang tua di Surat Pernyataan-mu sudah diperbarui.'
            : 'Permintaan terkirim. Surat Pernyataan Orang Tua siap dicetak sekolah. Bila statusnya "Siap diambil", ambil di Tata Usaha lalu minta orang tua menandatanganinya di atas materai Rp10.000.'];
    }

    /** @return array{aktif: array|null, perusahaan: string} */
    public function formPenarikan(array $s): array
    {
        return ['aktif' => $this->permintaanAktif((int) $s['id'], SuratJenis::PENARIKAN), 'perusahaan' => (string) ($s['ajuan']['perusahaan_nama'] ?? '')];
    }

    /**
     * Siswa meminta Surat Penarikan PKL untuk DIRINYA dari tempat PKL ajuannya (alasan + tanggal mulai berlaku) → menunggu ACC
     * operator. Status PKL siswa tidak berubah (keputusan 2026-10-09). Satu permintaan aktif per siswa.
     *
     * @param array<string, mixed> $in alasan_pilih (kunci ALASAN_PENARIKAN | 'lain'), alasan_lain, tgl_efektif
     *
     * @return array{ok: bool, kode: string, pesan: string, galat?: array<string, string>, surat_id?: int}
     */
    public function ajukanPenarikan(int $siswaId, array $in, string $ip, ?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $s   = $this->profil($siswaId);
        if ($s === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data siswa tidak ditemukan. Masuk lagi.'];
        }
        $k = $this->kelayakan($s, $p)['penarikan'];
        if (! $k['boleh']) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => $k['alasan']];
        }
        if (($aktif = $this->permintaanAktif($siswaId, SuratJenis::PENARIKAN)) !== null) {
            return ['ok' => true, 'kode' => 'sudah', 'surat_id' => $aktif['surat_id'], 'pesan' => 'Kamu sudah meminta Surat Penarikan PKL (' . $aktif['kode'] . '). Statusnya ada di bawah.'];
        }
        $pilih = (string) ($in['alasan_pilih'] ?? '');
        $galat = [];
        if ($pilih !== 'lain' && ! isset(self::ALASAN_PENARIKAN[$pilih])) {
            $galat['alasan_pilih'] = 'Pilih alasan penarikan.';
        } elseif ($pilih === 'lain' && trim((string) ($in['alasan_lain'] ?? '')) === '') {
            $galat['alasan_lain'] = 'Tulis alasanmu.';
        }
        $h = SuratPkl::periksa(SuratJenis::PENARIKAN, [
            'tanggal_surat' => date('Y-m-d'), 'sapaan' => '', 'tgl_efektif' => (string) ($in['tgl_efektif'] ?? ''),
            'alasan_pilih' => $pilih !== 'lain' ? $pilih : '', 'alasan_lain' => $pilih === 'lain' ? (string) ($in['alasan_lain'] ?? '') : '',
        ]);
        if (! $h['ok']) {
            foreach ($h['galat'] as $g) {
                $galat[str_contains($g, 'terhitung sejak') ? 'tgl_efektif' : (str_contains($g, 'Alasan') ? 'alasan_lain' : 'umum')] ??= $g;
            }
        }
        if ($galat !== []) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Periksa lagi isianmu.', 'galat' => $galat];
        }

        $ajuanId = (int) $s['ajuan']['id'];
        $konteks = ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip, 'saluran' => 'layanan'];
        $r       = (new SuratPkl($this->db))->buat(SuratJenis::PENARIKAN, $h, [$ajuanId], [$siswaId], $konteks, $p, ['sumber' => 'siswa']);
        $suratId = (int) ($r['dibuat'][0] ?? 0);
        if ($suratId === 0) {
            $ada = (new SuratSekolah($this->db))->adaKunci(SuratPkl::kunci(SuratJenis::PENARIKAN, $ajuanId, $h['isi'], [$siswaId]));
            if ($ada === null) {
                return ['ok' => false, 'kode' => 'gagal', 'pesan' => 'Permintaan gagal disimpan. ' . implode(' ', $r['gagal'])];
            }
            $suratId = $ada['id']; // surat yang sama sudah dibuat operator → permintaan ditautkan
        }
        $this->simpanPermintaan(SuratJenis::PENARIKAN, $s, $suratId, null, ['alasan' => $h['isi']['alasan'], 'tgl_efektif' => $h['isi']['tgl_efektif']], $ip);

        return ['ok' => true, 'kode' => 'baru', 'surat_id' => $suratId,
            'pesan' => 'Permintaan terkirim. Surat Penarikan PKL dari ' . ($s['ajuan']['perusahaan_nama'] ?? 'tempat PKL-mu') . ' menunggu ACC operator. Pantau statusnya di bawah.'];
    }

    /** @param array<string, mixed>|null $isian */
    private function simpanPermintaan(string $jenis, array $s, int $suratId, ?int $jadwalId, ?array $isian, string $ip): void
    {
        $this->db->table('surat_permintaan')->insert([
            'jenis' => $jenis, 'siswa_id' => (int) $s['id'], 'kelas_id' => ((int) ($s['kelas_id'] ?? 0)) ?: null, 'surat_id' => $suratId,
            'jadwal_id' => $jadwalId, 'pengajuan_id' => ((int) ($s['ajuan']['id'] ?? 0)) ?: null,
            'isian' => $isian !== null ? json_encode($isian, JSON_UNESCAPED_UNICODE) : null, 'ip_address' => $ip, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Permintaan surat siswa ini (terbaru dulu) + status yang dibaca dari suratnya.
     *
     * @return list<array<string, mixed>>
     */
    public function permintaan(int $siswaId): array
    {
        if (! self::tabelAda()) {
            return [];
        }
        $rows = $this->db->table('surat_permintaan sp')
            ->select('sp.id, sp.jenis, sp.created_at, sp.surat_id, ss.status, ss.perlu_acc, ss.cetak_ke, ss.nomor, ss.judul, ss.perusahaan_nama, ss.catatan_staf, j.semester, j.tahun_pelajaran, j.sesi, j.kegiatan, j.jenis AS jadwal_jenis')
            ->join('surat_sekolah ss', 'ss.id = sp.surat_id', 'left')
            ->join('surat_jadwal j', 'j.id = sp.jadwal_id', 'left')
            ->where('sp.siswa_id', $siswaId)->orderBy('sp.id', 'DESC')->get()->getResultArray();

        return array_map(static function (array $r): array {
            $judul = SuratJenis::label((string) $r['jenis']);
            if ($r['jadwal_jenis'] !== null) {
                $judul .= ' — ' . SuratJadwal::label(['jenis' => $r['jadwal_jenis']] + $r);
            }

            return [
                'id' => (int) $r['id'], 'jenis' => (string) $r['jenis'], 'judul' => $judul, 'dibuat' => (string) $r['created_at'],
                'perusahaan' => (string) ($r['perusahaan_nama'] ?? ''), 'nomor' => (string) ($r['nomor'] ?? ''),
            ] + self::status($r['surat_id'] === null ? null : $r, (string) $r['jenis']);
        }, $rows);
    }

    /**
     * Status permintaan bagi siswa, dari baris surat (null = suratnya sudah tidak ada).
     *
     * @return array{status: string, status_teks: string, status_kelas: string, status_ket: string}
     */
    public static function status(?array $surat, string $jenis = ''): array
    {
        $st = $surat === null ? 'batal' : match ((string) $surat['status']) {
            'menunggu'     => 'menunggu',
            'dikembalikan' => 'diperiksa',
            'disetujui'    => (int) ($surat['cetak_ke'] ?? 0) > 0 ? 'ambil' : 'disetujui',
            default        => 'batal',
        };
        $tanpaAcc = $surat !== null && array_key_exists('perlu_acc', $surat) && (int) $surat['perlu_acc'] === 0;
        $ttdOrtu  = $jenis === SuratJenis::PERNYATAAN ? ' Minta orang tua menandatanganinya di atas materai Rp10.000.' : '';
        [$teks, $kelas, $ket] = match ($st) {
            'menunggu'  => ['Menunggu ACC', 'label-kuning', 'Permintaanmu sudah masuk dan menunggu disetujui operator sekolah.'],
            'diperiksa' => ['Sedang diperiksa sekolah', 'label-kuning', 'Sekolah sedang memeriksa ulang suratmu. Kamu tidak perlu mengajukan lagi.'],
            'disetujui' => $tanpaAcc
                ? ['Siap dicetak sekolah', 'label-merek', 'Surat sudah siap dan tinggal dicetak sekolah.']
                : ['Disetujui, sedang disiapkan', 'label-merek', 'Surat sudah disetujui dan sedang dicetak sekolah.'],
            'ambil'     => ['Siap diambil', 'label-buka', 'Surat sudah dicetak. Ambil di Tata Usaha sekolah.' . $ttdOrtu],
            default     => ['Dibatalkan', 'label-merah', 'Surat dibatalkan sekolah' . (trim((string) ($surat['catatan_staf'] ?? '')) !== '' ? ': ' . trim((string) $surat['catatan_staf']) : '') . '. Kamu boleh mengajukan lagi bila masih perlu.'],
        };

        return ['status' => $st, 'status_teks' => $teks, 'status_kelas' => $kelas, 'status_ket' => $ket];
    }
}
