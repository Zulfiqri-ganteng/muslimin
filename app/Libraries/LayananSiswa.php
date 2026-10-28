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
        'perpanjangan' => ['Surat Perpanjangan PKL', 'Surat dari sekolah untuk perusahaan bila PKL-mu diperpanjang. Hanya boleh satu kali.', 'calendar-edit', SuratJenis::PERPANJANGAN],
        'penarikan'  => ['Surat Penarikan PKL', 'Surat dari sekolah untuk menarik kamu dari tempat PKL (misalnya PKL dihentikan).', 'arrow-right-to-bracket', SuratJenis::PENARIKAN],
    ];

    /** Layanan surat yang formulirnya SUDAH dibangun (T3: izin_ujian, izin_tka · T4: pernyataan, penarikan · 2026-10-26: perpanjangan). Sisanya "Segera". */
    public const SIAP = ['izin_ujian', 'izin_tka', 'pernyataan', 'perpanjangan', 'penarikan'];

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
     * Tingkat yang ditawarkan di langkah masuk untuk tujuan $tujuan: Surat Izin TKA hanya kelas XII (klien 2026-10-22),
     * tujuan lain = semua tingkat yang dilayani.
     *
     * @return list<string>
     */
    public static function tingkatTujuan(string $tujuan, ?array $p = null): array
    {
        return $tujuan === 'izin_tka' ? ['XII'] : self::tingkat($p);
    }

    /**
     * Kelas (yang punya siswa aktif) pada tingkat yang dilayani — atau hanya tingkat untuk $tujuan — per tingkat, urut alami.
     *
     * @return array<string, list<array{id: int, nama: string}>>
     */
    public function kelas(?array $p = null, string $tujuan = ''): array
    {
        $rows = $this->db->table('kelas k')
            ->select('k.id, k.nama_kelas, k.tingkat')
            ->join('siswa s', 's.kelas_id = k.id')
            ->where('k.deleted_at', null)->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->whereIn('k.tingkat', self::tingkatTujuan($tujuan, $p))
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
        $r = $this->cocokkan($siswaId, $nisnIn, $tglIn, $ip, $p);
        if (! $r['ok']) {
            return $r;
        }
        if (! is_cli()) {
            session()->regenerate(); // id sesi baru setelah masuk (cegah fiksasi sesi)
        }
        session()->set(self::SESI, ['id' => $siswaId, 't' => time()]);

        return $r;
    }

    /**
     * Pemeriksaan bukti diri TANPA menyimpan sesi (dipakai masuk() web dan API publik Layanan Siswa yang memakai token —
     * Api\Layanan, LayananToken): siswa aktif di tingkat yang dilayani, NISN ATAU tanggal lahir cocok, dibatasi LoginThrottle.
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function cocokkan(int $siswaId, string $nisnIn, string $tglIn, string $ip, ?array $p = null): array
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
                // Perpanjangan: jendela H-7 / H+30 & "sudah pernah" ditampilkan di formulirnya sendiri (siswa tetap bisa melihat status).
                'perpanjangan' => ! SuratPerpanjangan::tabelAda() ? 'Belum tersedia.'
                    : (! $disetujui ? 'Khusus siswa yang sedang PKL (ajuan PKL sudah disetujui sekolah).'
                    : (! SuratPerpanjangan::setelan($p)['buka'] ? 'Permintaan surat perpanjangan sedang ditutup sekolah. Hubungi operator sekolah.' : '')),
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
     * Siswa meminta Surat Izin Ujian untuk jadwal $jadwalId. Satu surat per perusahaan per jadwal: bila surat untuk perusahaannya
     * sudah ada (diminta teman atau dibuat operator), permintaan ditautkan ke surat itu — namanya ditambahkan bila suratnya masih
     * bisa diubah. Surat baru masuk antrean ACC (sumber 'siswa'). Surat Izin TKA memakai aturan gelombang per siswa → ajukanTka().
     *
     * kode: baru | ikut | sudah (sudah pernah minta) | tolak (tidak memenuhi syarat) | gagal
     *
     * @return array{ok: bool, kode: string, pesan: string, surat_id?: int}
     */
    public function ajukanIzin(int $siswaId, string $kunci, int $jadwalId, string $ip, ?array $p = null): array
    {
        if ($kunci === 'izin_tka') {
            return $this->ajukanTka($siswaId, ['jadwal_id' => $jadwalId], $ip, $p);
        }
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
    // Surat Izin TKA — gelombang PER SISWA (2026-10-25, kasus PT Inti Pantja: teman satu tempat PKL beda gelombang TKA)
    // =================================================================

    /**
     * Penempatan gelombang TKA tiap siswa = surat Izin TKA (belum dibatalkan) yang memuat namanya. Aturan: SATU siswa = SATU
     * gelombang; lebih dari satu = data lama sebelum aturan ini (dibereskan operator).
     *   sendiri  = siswa itu sendiri yang memilih gelombang ini (bukan diisikan teman / dicantumkan otomatis / dibuat staf)
     *   diisikan = nama teman yang mengisikan gelombang ini ('' bila tidak)
     *
     * @param list<int> $ids
     *
     * @return array<int, list<array{surat_id: int, kode: string, status: string, isi: array<string, mixed>, label: string, hari_tanggal: string, status_teks: string, bisa_ubah: bool, sendiri: bool, diisikan: string}>>
     */
    public function penempatanTka(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $rows = $this->db->table('surat_sekolah_siswa x')
            ->select('x.siswa_id, ss.id, ss.status, ss.nomor, ss.perlu_acc, ss.cetak_ke, ss.isi')
            ->join('surat_sekolah ss', 'ss.id = x.surat_id')
            ->where('ss.jenis', SuratJenis::TKA)->where('ss.status !=', 'dibatalkan')->whereIn('x.siswa_id', $ids)
            ->orderBy('ss.id', 'ASC')->get()->getResultArray();
        $minta = []; // "siswa:surat" => isian permintaan terbaru
        if ($rows !== [] && self::tabelAda()) {
            foreach ($this->db->table('surat_permintaan')->select('siswa_id, surat_id, isian')->where('jenis', SuratJenis::TKA)->whereIn('siswa_id', $ids)
                ->whereIn('surat_id', array_values(array_unique(array_map('intval', array_column($rows, 'id')))))->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
                $minta[(int) $r['siswa_id'] . ':' . (int) $r['surat_id']] = SuratSekolah::dekodeIsi((string) ($r['isian'] ?? ''));
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $isi = SuratSekolah::dekodeIsi((string) ($r['isi'] ?? ''));
            $mi  = $minta[(int) $r['siswa_id'] . ':' . (int) $r['id']] ?? null;
            $out[(int) $r['siswa_id']][] = [
                'surat_id' => (int) $r['id'], 'kode' => \App\Models\SuratSekolahModel::kode((int) $r['id']), 'status' => (string) $r['status'], 'isi' => $isi,
                'label' => SuratAcara::namaPendek(SuratJenis::TKA, $isi), 'hari_tanggal' => SuratAcara::hariTanggal((string) ($isi['tgl_mulai'] ?? ''), (string) ($isi['tgl_selesai'] ?? '')),
                'status_teks' => self::status($r, SuratJenis::TKA)['status_teks'], 'bisa_ubah' => SuratSekolah::alasanTakBisaUbah($r) === null,
                'sendiri' => $mi !== null && empty($mi['diisikan_oleh']), 'diisikan' => $mi !== null ? (string) ($mi['diisikan_nama'] ?? '') : '',
            ];
        }

        return $out;
    }

    /** Surat (isian) ini untuk gelombang $j? Unsurnya sama dengan sidik SuratAcara::kunci: tanggal mulai, tanggal selesai, sesi. */
    public static function cocokJadwal(array $isi, array $j): bool
    {
        return (string) ($isi['tgl_mulai'] ?? '') === (string) $j['tgl_mulai'] && (string) ($isi['tgl_selesai'] ?? '') === (string) $j['tgl_selesai']
            && mb_strtolower(trim((string) ($isi['sesi'] ?? ''))) === mb_strtolower(trim((string) ($j['sesi'] ?? '')));
    }

    /**
     * Surat Izin TKA per perusahaan (belum dibatalkan, termasuk buatan staf) untuk perusahaan $pkey pada gelombang $j, terlama dulu.
     * Dipakai juga alat staf SuratTkaPindah.
     *
     * @return list<array{id: int, status: string, bisa_ubah: bool}>
     */
    public function suratGelombang(string $pkey, array $j): array
    {
        $out  = [];
        $rows = $this->db->table('surat_sekolah')->select('id, status, nomor, isi')->where('jenis', SuratJenis::TKA)->where('status !=', 'dibatalkan')
            ->like('isi', '"pkey":' . json_encode($pkey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $r) {
            $isi = SuratSekolah::dekodeIsi((string) $r['isi']);
            if (($isi['pkey'] ?? '') === $pkey && ($isi['mode'] ?? '') === 'perusahaan' && self::cocokJadwal($isi, $j)) {
                $out[] = ['id' => (int) $r['id'], 'status' => (string) $r['status'], 'bisa_ubah' => SuratSekolah::alasanTakBisaUbah($r) === null];
            }
        }

        return $out;
    }

    /**
     * Data formulir Surat Izin TKA: gelombang yang dibuka (+ di luar periode PKL?), perusahaan, penempatan siswa ini sekarang, dan
     * teman satu tempat PKL (kelas XII) beserta gelombangnya. Teman TERKUNCI bila sudah memilih sendiri atau suratnya sudah
     * disetujui; siswa sendiri terkunci (tidak bisa pindah gelombang) bila suratnya sudah disetujui.
     *
     * @param array<string, mixed> $s         profil()
     * @param array<string, mixed> $kelayakan kelayakan()
     *
     * @return array{jadwal: list<array<string, mixed>>, perusahaan: array{nama: string, alamat: string}|null, saya: array<string, mixed>, teman: list<array<string, mixed>>}
     */
    public function formTka(array $s, array $kelayakan): array
    {
        $sid    = (int) $s['id'];
        $g      = $this->grupPerusahaan($sid, SuratJenis::TKA);
        $jadwal = array_map(static fn (array $j): array => $j + ['luar_periode' => $g !== null && $g['mulai'] !== null && $g['selesai'] !== null
            && ($g['selesai'] < $j['tgl_mulai'] || $g['mulai'] > $j['tgl_selesai'])], $kelayakan['izin_tka']['jadwal'] ?? []);
        $tempat  = $this->penempatanTka($g === null ? [$sid] : array_column($g['siswa'], 'siswa_id'));
        $ringkas = static function (array $ada) use ($jadwal): array {
            $jid = 0; // gelombang (yang dibuka) tempat namanya tercantum sekarang
            foreach ($ada as $t) {
                foreach ($jadwal as $j) {
                    $jid = self::cocokJadwal($t['isi'], $j) ? (int) $j['id'] : $jid;
                }
            }

            return ['penempatan' => $ada, 'jadwal_id' => $jid, 'disetujui' => in_array(false, array_column($ada, 'bisa_ubah'), true),
                'sendiri' => in_array(true, array_column($ada, 'sendiri'), true)];
        };
        $teman = [];
        foreach ($g['siswa'] ?? [] as $x) {
            if ($x['siswa_id'] !== $sid) {
                $r       = $ringkas($tempat[$x['siswa_id']] ?? []);
                $teman[] = ['id' => $x['siswa_id'], 'nama' => $x['nama'], 'kelas' => $x['kelas'], 'terkunci' => $r['sendiri'] || $r['disetujui']] + $r;
            }
        }
        $saya = $ringkas($tempat[$sid] ?? []);

        return [
            'jadwal'     => $jadwal,
            'perusahaan' => $g === null ? null : ['nama' => $g['nama'], 'alamat' => trim($g['alamat'] . ($g['kota'] !== '' && ! str_contains(mb_strtolower($g['alamat']), mb_strtolower($g['kota'])) ? ', ' . $g['kota'] : ''))],
            'saya'       => $saya + ['terkunci' => $saya['disetujui']],
            'teman'      => $teman,
        ];
    }

    /**
     * Siswa meminta Surat Izin TKA dengan gelombang PER SISWA:
     *   - SATU siswa = SATU gelombang; surat per perusahaan per gelombang hanya memuat siswa gelombang itu.
     *   - $in['jadwal_id'] = gelombang siswa ini (wajib); $in['teman'][siswa_id] = gelombang teman satu tempat PKL ('' = tidak diubah,
     *     biar teman memilih sendiri). Teman yang memilih SENDIRI, atau yang suratnya sudah disetujui, tidak diubah (disebut di pesan).
     *   - Pindah gelombang: nama dikeluarkan dari surat lama yang masih bisa diubah (surat yang jadi kosong dibatalkan otomatis);
     *     surat lama siswa ini sudah disetujui → ditolak, minta operator.
     *   - Surat perusahaan untuk gelombang itu masih bisa diubah → nama ditambahkan; tidak ada / sudah disetujui → surat BARU (menunggu
     *     ACC) untuk nama yang menyusul.
     * Semua perubahan dalam SATU transaksi: satu langkah gagal → tidak ada yang berubah.
     *
     * kode: baru | ikut | sudah | tolak | gagal
     *
     * @param array<string, mixed> $in
     *
     * @return array{ok: bool, kode: string, pesan: string, surat_id?: int}
     */
    public function ajukanTka(int $siswaId, array $in, string $ip, ?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $s   = $this->profil($siswaId);
        if ($s === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data siswa tidak ditemukan. Masuk lagi.'];
        }
        $k = $this->kelayakan($s, $p)['izin_tka'];
        if (! $k['boleh']) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => $k['alasan']];
        }
        $jadwal = [];
        foreach ($k['jadwal'] as $j) {
            $jadwal[(int) $j['id']] = $j;
        }
        $jSaya = $jadwal[is_scalar($in['jadwal_id'] ?? null) ? (int) $in['jadwal_id'] : 0] ?? null;
        if ($jSaya === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Pilih gelombang TKA-mu.'];
        }
        $g = $this->grupPerusahaan($siswaId, SuratJenis::TKA);
        if ($g === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data PKL-mu belum lengkap untuk surat ini. Hubungi operator sekolah.'];
        }
        $anggota = [];
        foreach ($g['siswa'] as $x) {
            $anggota[$x['siswa_id']] = $x;
        }

        // Rencana: siswa => gelombang. Teman yang tidak diisi tidak diubah.
        $rencana = [$siswaId => (int) $jSaya['id']];
        $pilih   = is_array($in['teman'] ?? null) ? $in['teman'] : [];
        foreach ($anggota as $id => $x) {
            $v = is_scalar($pilih[$id] ?? null) ? trim((string) $pilih[$id]) : '';
            if ($id === $siswaId || $v === '') {
                continue;
            }
            if (! isset($jadwal[(int) $v])) {
                return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Pilihan gelombang untuk ' . $x['nama'] . ' tidak dikenal. Muat ulang halaman.'];
            }
            $rencana[$id] = (int) $v;
        }

        $tempat = $this->penempatanTka(array_keys($rencana));
        $keluar = []; // surat lama => siswa yang dikeluarkan
        $masuk  = []; // gelombang => siswa yang ditambahkan ke surat gelombang itu
        $proses = []; // siswa => [jadwal_id, ke (surat yang sudah memuatnya), dari (surat yang ditinggalkan)]
        $lewati = []; // teman yang tidak diubah + alasannya
        foreach ($rencana as $id => $jid) {
            $ada  = $tempat[$id] ?? [];
            $sama = array_values(array_filter($ada, static fn (array $t): bool => self::cocokJadwal($t['isi'], $jadwal[$jid])));
            $lain = array_values(array_filter($ada, static fn (array $t): bool => ! self::cocokJadwal($t['isi'], $jadwal[$jid])));
            if ($id === $siswaId) {
                foreach ($lain as $t) {
                    if (! $t['bisa_ubah']) {
                        return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Namamu sudah tercantum di surat ' . $t['label'] . ' (' . $t['kode'] . ', ' . $t['status_teks']
                            . '). Surat itu sudah disetujui sekolah, jadi gelombangmu tidak bisa diganti dari sini. Kalau gelombangmu salah, minta operator sekolah memindahkanmu.'];
                    }
                }
            } else {
                if ($sama !== [] && $lain === []) {
                    continue; // sudah di gelombang itu
                }
                $sendiri = array_values(array_filter($ada, static fn (array $t): bool => $t['sendiri']));
                $kunci   = array_values(array_filter($lain, static fn (array $t): bool => ! $t['bisa_ubah']));
                if ($sendiri !== [] || $kunci !== []) {
                    $lewati[] = $anggota[$id]['nama'] . ($sendiri !== [] ? ' sudah memilih sendiri (' . $sendiri[0]['label'] . ')' : ' sudah tercantum di surat ' . $kunci[0]['label'] . ' yang sudah disetujui');

                    continue;
                }
            }
            foreach ($lain as $t) {
                $keluar[$t['surat_id']][] = $id;
            }
            if ($sama === []) {
                $masuk[$jid][] = $id;
            }
            $proses[$id] = ['jadwal_id' => $jid, 'ke' => $sama[0]['surat_id'] ?? null, 'dari' => array_column($lain, 'surat_id')];
        }
        $catatLewati = $lewati === [] ? '' : ' Tidak diubah: ' . implode('; ', $lewati) . ' — kalau salah, minta operator sekolah.';
        $sayaSama    = array_values(array_filter($tempat[$siswaId] ?? [], static fn (array $t): bool => self::cocokJadwal($t['isi'], $jSaya)));
        if ($masuk === [] && $keluar === [] && in_array(true, array_column($sayaSama, 'sendiri'), true)) {
            return ['ok' => true, 'kode' => 'sudah', 'surat_id' => $sayaSama[0]['surat_id'],
                'pesan' => 'Kamu sudah meminta surat ' . $sayaSama[0]['label'] . ' (' . $sayaSama[0]['kode'] . ') sebelumnya.' . $catatLewati . ' Statusnya ada di bawah.'];
        }

        $konteks = ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip, 'saluran' => 'layanan'];
        $svc     = new SuratSekolah($this->db);
        $pengisi = $s['nama'] . ' (' . ($s['nama_kelas'] ?? '-') . ')';
        $namaDari = static fn (array $ids): string => implode(', ', array_map(static fn (int $id): string => $anggota[$id]['nama'], $ids));
        $baris   = static fn (array $rows): array => array_map(static fn (array $x): array => ['siswa_id' => (int) $x['siswa_id'], 'kelas_id' => $x['kelas_id'] ?? null, 'hp' => $x['hp'] ?? null], $rows);
        $suratJadwal = []; // gelombang => surat tujuan
        $baru        = false;
        $this->db->transBegin();
        try {
            // 1) Keluarkan dari surat gelombang lain; surat yang jadi kosong dibatalkan.
            foreach ($keluar as $suratId => $ids) {
                $m = $svc->muat($suratId);
                if ($m === null || SuratSekolah::alasanTakBisaUbah($m['surat']) !== null) {
                    throw new \DomainException('Surat ' . \App\Models\SuratSekolahModel::kode($suratId) . ' baru saja diputuskan sekolah. Muat ulang halaman lalu coba lagi.');
                }
                $svc->catat($suratId, 'pindah', $konteks, 'Pindah gelombang (diisi ' . $pengisi . '): '
                    . implode(', ', array_map(static fn (int $id): string => $anggota[$id]['nama'] . ' → ' . SuratJadwal::label($jadwal[$rencana[$id]]), $ids)));
                $sisa = array_values(array_filter($m['siswa'], static fn (array $x): bool => ! in_array((int) $x['siswa_id'], $ids, true)));
                if ($sisa === []) {
                    if (! $svc->batalOtomatis($suratId, 'Semua siswa di surat ini pindah ke gelombang TKA lain.', $konteks)) {
                        throw new \DomainException('Surat ' . \App\Models\SuratSekolahModel::kode($suratId) . ' baru saja diputuskan sekolah. Muat ulang halaman lalu coba lagi.');
                    }
                } else {
                    $u = $svc->ubah($suratId, ['judul' => SuratAcara::judul(SuratJenis::TKA, $m['surat']['isi_arr'], (string) $m['surat']['perusahaan_nama'], count($sisa)),
                        'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'isi' => $m['surat']['isi_arr'], 'siswa' => $baris($sisa)], $konteks);
                    if (! $u['ok']) {
                        throw new \RuntimeException('keluarkan dari ' . $suratId . ': ' . ($u['pesan'] ?? ''));
                    }
                }
            }

            // 2) Masukkan ke surat gelombang tujuan: surat yang masih bisa diubah → tambah nama; tidak ada / sudah disetujui → surat baru.
            foreach ($masuk as $jid => $ids) {
                $teman  = array_values(array_filter($ids, static fn (int $id): bool => $id !== $siswaId));
                $target = null;
                foreach ($this->suratGelombang($g['kunci'], $jadwal[$jid]) as $d) {
                    $target ??= $d['bisa_ubah'] ? $d['id'] : null;
                }
                if ($target !== null) {
                    $m = $svc->muat($target);
                    if ($m === null || SuratSekolah::alasanTakBisaUbah($m['surat']) !== null) {
                        throw new \DomainException('Surat ' . \App\Models\SuratSekolahModel::kode($target) . ' baru saja diputuskan sekolah. Muat ulang halaman lalu coba lagi.');
                    }
                    $rows  = $baris($m['siswa']);
                    $punya = array_column($rows, 'siswa_id');
                    foreach ($ids as $id) {
                        if (! in_array($id, $punya, true)) {
                            $rows[] = ['siswa_id' => $id, 'kelas_id' => $anggota[$id]['kelas_id'], 'hp' => $anggota[$id]['hp']];
                        }
                    }
                    $u = $svc->ubah($target, ['judul' => SuratAcara::judul(SuratJenis::TKA, $m['surat']['isi_arr'], (string) $m['surat']['perusahaan_nama'], count($rows)),
                        'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'isi' => $m['surat']['isi_arr'], 'siswa' => $rows], $konteks);
                    if (! $u['ok']) {
                        throw new \RuntimeException('tambah ke ' . $target . ': ' . ($u['pesan'] ?? ''));
                    }
                    $svc->catat($target, 'minta', $konteks, in_array($siswaId, $ids, true)
                        ? 'Diminta juga oleh ' . $pengisi . ($teman !== [] ? '; ikut diisikan: ' . $namaDari($teman) : '')
                        : 'Diisikan oleh ' . $pengisi . ' untuk temannya: ' . $namaDari($teman));
                    $suratJadwal[$jid] = $target;
                } else {
                    $isi   = SuratJadwal::isiSurat($jadwal[$jid]);
                    $dasar = SuratAcara::kunci(SuratJenis::TKA, $isi, $g['kunci']);
                    $kunci = $dasar;
                    for ($n = 1; $svc->adaKunci($kunci) !== null; $n++) { // surat pertama sudah disetujui → surat tambahan untuk nama yang menyusul
                        if ($n > 50) {
                            throw new \DomainException('Surat untuk gelombang ini sudah terlalu banyak. Hubungi operator sekolah.');
                        }
                        $kunci = sha1($dasar . '|' . $n);
                    }
                    $ajuan = array_values(array_unique(array_map(static fn (int $id): int => (int) $anggota[$id]['ajuan_id'], $ids)));
                    $r     = $svc->buat(SuratJenis::TKA, [
                        'judul' => SuratAcara::judul(SuratJenis::TKA, $isi, $g['nama'], count($ids)), 'tanggal_surat' => date('Y-m-d'),
                        'isi' => $isi + ['pkey' => $g['kunci'], 'kunci' => $kunci, 'jadwal_id' => $jid],
                        'perusahaan_nama' => $g['nama'], 'pengajuan_id' => count($ajuan) === 1 && $ajuan[0] > 0 ? $ajuan[0] : null,
                        'siswa' => array_map(static fn (int $id): array => ['siswa_id' => $id, 'kelas_id' => $anggota[$id]['kelas_id'], 'hp' => $anggota[$id]['hp']], $ids),
                        'sumber' => 'siswa',
                    ], $konteks, $p);
                    if (! $r['ok']) {
                        throw new \RuntimeException('surat baru: ' . ($r['pesan'] ?? ''));
                    }
                    $suratJadwal[$jid] = (int) $r['id'];
                    $baru              = true;
                    if ($teman !== []) {
                        $svc->catat((int) $r['id'], 'minta', $konteks, 'Diisikan oleh ' . $pengisi . ' untuk temannya: ' . $namaDari($teman));
                    }
                }
            }

            // 3) Catatan permintaan: satu baris per siswa untuk gelombang TKA-nya; baris lama ikut pindah ke surat tujuan.
            foreach ($proses as $id => $x) {
                $ke    = $x['ke'] ?? $suratJadwal[$x['jadwal_id']];
                $isian = $id === $siswaId ? null : json_encode(['diisikan_oleh' => $siswaId, 'diisikan_nama' => (string) $s['nama']], JSON_UNESCAPED_UNICODE);
                if ($x['dari'] !== []) {
                    $this->db->table('surat_permintaan')->where('jenis', SuratJenis::TKA)->where('siswa_id', $id)->whereIn('surat_id', $x['dari'])
                        ->update(['surat_id' => $ke, 'jadwal_id' => $x['jadwal_id']]);
                }
                $ada = array_map('intval', array_column($this->db->table('surat_permintaan')->select('id')->where('jenis', SuratJenis::TKA)
                    ->where('siswa_id', $id)->where('surat_id', $ke)->orderBy('id', 'DESC')->get()->getResultArray(), 'id'));
                if ($ada === []) {
                    $this->db->table('surat_permintaan')->insert([
                        'jenis' => SuratJenis::TKA, 'siswa_id' => $id, 'kelas_id' => $id === $siswaId ? (((int) ($s['kelas_id'] ?? 0)) ?: null) : $anggota[$id]['kelas_id'],
                        'surat_id' => $ke, 'jadwal_id' => $x['jadwal_id'], 'pengajuan_id' => ((int) $anggota[$id]['ajuan_id']) ?: null,
                        'isian' => $isian, 'ip_address' => $ip, 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    if (count($ada) > 1) { // baris lama yang ikut pindah → satu baris saja
                        $this->db->table('surat_permintaan')->whereIn('id', array_slice($ada, 1))->delete();
                    }
                    $this->db->table('surat_permintaan')->where('id', $ada[0])->update(['jadwal_id' => $x['jadwal_id'], 'isian' => $isian]);
                }
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Layanan] ajukanTka siswa #' . $siswaId . ' gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'gagal', 'pesan' => $e instanceof \DomainException ? $e->getMessage() : 'Permintaan gagal disimpan. Tidak ada yang berubah — coba lagi sebentar.'];
        }

        $perGelombang = [];
        foreach ($proses as $id => $x) {
            $perGelombang[SuratJadwal::label($jadwal[$x['jadwal_id']])][] = $id === $siswaId ? 'kamu' : $anggota[$id]['nama'];
        }
        $ringkas = implode('; ', array_map(static fn (string $lbl, array $nama): string => $lbl . ': ' . implode(', ', $nama), array_keys($perGelombang), $perGelombang));

        return ['ok' => true, 'kode' => $baru ? 'baru' : 'ikut', 'surat_id' => $proses[$siswaId]['ke'] ?? $suratJadwal[$rencana[$siswaId]] ?? 0,
            'pesan' => 'Permintaan terkirim untuk ' . $g['nama'] . ' — ' . $ringkas . '. Satu surat untuk tiap gelombang, menunggu ACC operator.' . $catatLewati . ' Pantau statusnya di bawah.'];
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

    // =================================================================
    // Surat Perpanjangan PKL (2026-10-26; docs/DESAIN-PERPANJANGAN-PKL.md bagian 7) — aturan di SuratPerpanjangan
    // =================================================================

    /**
     * Data formulir Surat Perpanjangan untuk siswa ini: ajuan + tanggal semula, jendela (buka/dini/lewat), perpanjangan yang sudah
     * ada (dirinya), teman yang PKL di PERUSAHAAN yang sama — satu ajuan maupun ajuan lain (2026-10-28; bisa dicentang / terkunci +
     * alasannya), dan pilihan cepat tanggal (+2 minggu, +1/2/3 bulan). Teman satu ajuan dulu, lalu yang lain urut nama.
     *
     * @return array<string, mixed>
     */
    public function formPerpanjangan(array $s, ?array $p = null): array
    {
        $p   ??= (new PklPengaturanModel())->ambil();
        $lib   = new SuratPerpanjangan($this->db);
        $aid   = (int) ($s['ajuan']['id'] ?? 0);
        $a     = $aid > 0 ? $lib->ajuanForm($aid) : null;
        $lama  = $a['selesai_lama'] ?? null;
        $saya  = null;
        $teman = [];
        foreach ($a['siswa'] ?? [] as $x) {
            if ($x['siswa_id'] === (int) $s['id']) {
                $saya = $x;
                continue;
            }
            $ket = match (true) {
                $x['pj'] !== null      => 'Sudah punya surat perpanjangan',
                $x['ditarik'] !== null => 'Sedang ditarik',
                $x['beda'] !== null    => 'PKL-nya selesai ' . IsianBantu::tanggalIndo($x['beda']) . ' (beda tanggal), minta surat sendiri',
                // Tanggal semula diketik pengaju: beri tahu tanggal yang tercatat di ajuan teman (harus sama, R13).
                $lama === null && $x['selesai_ajuan'] !== null => 'Tercatat selesai ' . IsianBantu::tanggalIndo($x['selesai_ajuan']),
                default                => '',
            };
            $teman[] = ['id' => $x['siswa_id'], 'nama' => $x['nama'], 'kelas' => $x['kelas'], 'bisa' => $x['bisa'], 'ket' => $ket,
                'satu_ajuan' => $x['ajuan_ini'], 'ajuan_kode' => $x['ajuan_kode']];
        }
        usort($teman, static fn (array $x, array $y): int => [! $x['satu_ajuan'], $x['nama']] <=> [! $y['satu_ajuan'], $y['nama']]);
        $cepat  = [];
        if ($lama !== null) {
            $tgl     = \DateTimeImmutable::createFromFormat('!Y-m-d', $lama);
            $cepat[] = ['label' => '+2 minggu', 'tanggal' => SuratPerpanjangan::tambahHari($lama, 14)];
            foreach ([1, 2, 3] as $n) { // tanggal sama n bulan kemudian; 31 → akhir bulan (sama dengan formulir staf)
                $awal  = $tgl->modify('first day of +' . $n . ' month');
                $hari  = min((int) $tgl->format('j'), (int) $awal->format('t'));
                $cepat[] = ['label' => '+' . $n . ' bulan', 'tanggal' => $awal->setDate((int) $awal->format('Y'), (int) $awal->format('n'), $hari)->format('Y-m-d')];
            }
        }

        return [
            'ajuan' => $a, 'perusahaan' => (string) ($a['perusahaan'] ?? ($s['ajuan']['perusahaan_nama'] ?? '')),
            'lama' => $lama, 'jendela' => $lama !== null ? SuratPerpanjangan::jendela($lama, $p) : null, 'setelan' => SuratPerpanjangan::setelan($p),
            'saya' => $saya, 'sudah' => $saya['pj'] ?? null, 'ditarik' => $saya['ditarik'] ?? null, 'teman' => $teman, 'cepat' => $cepat,
        ];
    }

    /**
     * Siswa meminta Surat Perpanjangan PKL untuk DIRINYA (+ teman satu perusahaan yang dicentang) → menunggu ACC operator. Jendela H-x / H+y
     * berlaku (R2); maksimal satu kali (R5). Teman yang dicentang mendapat baris permintaan "diisikan oleh" supaya statusnya tampil
     * di halamannya sendiri.
     *
     * @param array<string, mixed> $in pilih_tanggal (Y-m-d | 'lain'), selesai_baru, selesai_lama (bila belum tercatat), alasan_pilih,
     *                                 alasan_lain, teman[] (id)
     *
     * @return array{ok: bool, kode: string, pesan: string, galat?: array<string, string>, surat_id?: int}
     */
    public function ajukanPerpanjangan(int $siswaId, array $in, string $ip, ?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $s   = $this->profil($siswaId);
        if ($s === null) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => 'Data siswa tidak ditemukan. Masuk lagi.'];
        }
        $k = $this->kelayakan($s, $p)['perpanjangan'];
        if (! $k['boleh']) {
            return ['ok' => false, 'kode' => 'tolak', 'pesan' => $k['alasan']];
        }
        $pilih = (string) ($in['pilih_tanggal'] ?? '');
        $baru  = $pilih !== '' && $pilih !== 'lain' ? $pilih : (string) ($in['selesai_baru'] ?? '');
        $teman = array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($in['teman'] ?? []), 'is_scalar')), static fn (int $x) => $x > 0 && $x !== $siswaId)));
        $r     = (new SuratPerpanjangan($this->db))->buat([
            'ajuan_id' => (int) $s['ajuan']['id'], 'siswa' => array_merge([$siswaId], $teman), 'tanggal_surat' => date('Y-m-d'), 'sapaan' => '',
            'selesai_lama' => (string) ($in['selesai_lama'] ?? ''), 'selesai_baru' => $baru,
            'alasan_pilih' => (string) ($in['alasan_pilih'] ?? ''), 'alasan_lain' => (string) ($in['alasan_lain'] ?? ''),
        ], ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip, 'saluran' => 'layanan'], $p, ['sumber' => 'siswa', 'jendela' => true]);
        if (! $r['ok']) {
            $galat = $r['galat'] ?? [];
            if (isset($galat['selesai_baru']) && $pilih !== 'lain' && $pilih !== '') {
                $galat['pilih_tanggal'] = $galat['selesai_baru'];
            }

            return ['ok' => false, 'kode' => $r['kode'], 'pesan' => $r['pesan'], 'galat' => $galat];
        }
        $suratId = (int) $r['surat_id'];
        $isi     = ['selesai_baru' => $baru];
        $this->simpanPermintaan(SuratJenis::PERPANJANGAN, $s, $suratId, null, $isi, $ip);
        foreach ($teman as $t) {
            $ts = $this->profil($t);
            if ($ts !== null) {
                $this->simpanPermintaan(SuratJenis::PERPANJANGAN, $ts, $suratId, null, $isi + ['diisikan_oleh' => $siswaId, 'diisikan_nama' => $s['nama']], $ip);
            }
        }
        (new SuratSekolah($this->db))->catat($suratId, 'minta', ['oleh' => 'Siswa: ' . $s['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip],
            mb_substr('Diminta lewat Layanan Siswa' . ($teman !== [] ? ' (+' . count($teman) . ' teman)' : ''), 0, 255));

        return ['ok' => true, 'kode' => $r['kode'], 'surat_id' => $suratId,
            'pesan' => 'Permintaan terkirim. Surat Perpanjangan PKL untuk ' . ($s['ajuan']['perusahaan_nama'] ?? 'tempat PKL-mu') . ' menunggu ACC operator. Pantau statusnya di bawah.'
                . ($teman !== [] ? ' Temanmu yang kamu centang ikut tercantum di surat yang sama.' : '')];
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
     * Permintaan surat siswa ini (terbaru dulu) + status yang dibaca dari suratnya. Ikut tampil: surat Izin Ujian / Izin TKA yang
     * memuat namanya TANPA permintaan darinya (dicantumkan teman, atau dibuat sekolah). `catatan` = keterangan tambahan untuk siswa
     * (gelombang TKA diisikan teman / namanya dicantumkan).
     *
     * @return list<array<string, mixed>>
     */
    public function permintaan(int $siswaId): array
    {
        if (! self::tabelAda()) {
            return [];
        }
        $rows = $this->db->table('surat_permintaan sp')
            ->select('sp.id, sp.jenis, sp.created_at, sp.surat_id, sp.isian, ss.status, ss.perlu_acc, ss.cetak_ke, ss.nomor, ss.judul, ss.perusahaan_nama, ss.catatan_staf, j.semester, j.tahun_pelajaran, j.sesi, j.kegiatan, j.jenis AS jadwal_jenis')
            ->join('surat_sekolah ss', 'ss.id = sp.surat_id', 'left')
            ->join('surat_jadwal j', 'j.id = sp.jadwal_id', 'left')
            ->where('sp.siswa_id', $siswaId)->orderBy('sp.id', 'DESC')->get()->getResultArray();

        $out = array_map(static function (array $r): array {
            $judul = SuratJenis::label((string) $r['jenis']);
            if ($r['jadwal_jenis'] !== null) {
                $judul .= ' — ' . SuratJadwal::label(['jenis' => $r['jadwal_jenis']] + $r);
            }
            $pengisi = (string) (SuratSekolah::dekodeIsi((string) ($r['isian'] ?? ''))['diisikan_nama'] ?? '');

            return [
                'id' => (int) $r['id'], 'jenis' => (string) $r['jenis'], 'judul' => $judul, 'dibuat' => (string) $r['created_at'],
                'perusahaan' => (string) ($r['perusahaan_nama'] ?? ''), 'nomor' => (string) ($r['nomor'] ?? ''),
                'catatan' => $pengisi === '' ? ''
                    : ($r['jenis'] === SuratJenis::TKA ? 'Gelombang ini diisikan oleh temanmu, ' . $pengisi . '. Kalau salah, pilih gelombangmu sendiri di formulir Surat Izin TKA.'
                    : 'Namamu dicantumkan oleh temanmu, ' . $pengisi . '. Kalau PKL-mu tidak diperpanjang, beri tahu operator sekolah.'),
            ] + self::status($r['surat_id'] === null ? null : $r, (string) $r['jenis']);
        }, $rows);

        // Surat Izin Ujian / TKA yang memuat namanya tanpa permintaan darinya.
        $punya = array_values(array_filter(array_map('intval', array_column($rows, 'surat_id'))));
        $b     = $this->db->table('surat_sekolah_siswa x')
            ->select('ss.id AS surat_id, ss.jenis, ss.status, ss.perlu_acc, ss.cetak_ke, ss.nomor, ss.perusahaan_nama, ss.catatan_staf, ss.isi, ss.created_at' . (SuratSekolah::kolomSumber() ? ', ss.sumber' : ''))
            ->join('surat_sekolah ss', 'ss.id = x.surat_id')
            ->where('x.siswa_id', $siswaId)->whereIn('ss.jenis', [SuratJenis::ASTS, SuratJenis::TKA])->where('ss.status !=', 'dibatalkan');
        if ($punya !== []) {
            $b->whereNotIn('ss.id', $punya);
        }
        foreach ($b->get()->getResultArray() as $r) {
            $out[] = [
                'id' => 0, 'jenis' => (string) $r['jenis'], 'dibuat' => (string) $r['created_at'],
                'judul' => SuratJenis::label((string) $r['jenis']) . ' — ' . SuratAcara::namaPendek((string) $r['jenis'], SuratSekolah::dekodeIsi((string) ($r['isi'] ?? ''))),
                'perusahaan' => (string) ($r['perusahaan_nama'] ?? ''), 'nomor' => (string) ($r['nomor'] ?? ''),
                'catatan' => 'Namamu dicantumkan di surat ini oleh ' . (($r['sumber'] ?? '') === 'siswa' ? 'teman PKL-mu' : 'sekolah') . '.',
            ] + self::status($r, (string) $r['jenis']);
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['dibuat'], $a['dibuat']) ?: $b['id'] <=> $a['id']);

        return $out;
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
