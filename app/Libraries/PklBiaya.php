<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Biaya yang dicatat saat surat PKL diunduh — SATU sumber aturan untuk web (Admin\PklBiaya, Admin\PklBerkas)
 * dan API Android (Api\Pkl).
 *
 * Bukan modul keuangan: ini catatan "siswa X sudah menyerahkan biaya Y untuk periode Z", dibuat oleh yang berhak
 * mengunduh surat (PklHak 'surat'), lalu dibaca Laporan Pembayaran. Jenis biaya (pkl_biaya): Biaya PKL (sekali per
 * kegiatan PKL / tahun ajaran), SPP, Tabungan Wajib, Iuran OSIS (bulanan). Nominal diatur Admin; tiap catatan menyimpan
 * nominal pada saat dicatat.
 *
 * ALUR (urutan penting, supaya tak ada catatan salah):
 *   1. rencana()  — baca kiriman, validasi, hitung apa yang BARU dicatat dan siapa yang belum memenuhi gerbang;
 *   2. (pemanggil) bangun surat; bila GAGAL tak ada yang dicatat;
 *   3. catat()    — satu transaksi: pembayaran baru, beasiswa baru, keringanan, riwayat ajuan.
 *
 * GERBANG: tiap siswa dalam unduhan wajib punya MINIMAL SATU catatan — biaya baru, biaya yang sudah tercatat untuk
 * ajuan itu, beasiswa 3 tahun (membebaskan SPP), atau keringanan/ditunda beralasan. Biaya yang sudah tercatat tidak
 * diminta ulang (UNIQUE siswa+biaya+periode), jadi cetak ulang tidak menggandakan pembayaran.
 *
 * Kiriman ($in): ['biaya' => [siswa_id => ['jenis' => [kode…], 'bulan' => 'YYYY-MM', 'jumlah_bulan' => 1..12,
 *                'beasiswa' => 'sktm|yayasan|lainnya', 'beasiswa_ket' => '…', 'keringanan' => 'alasan']],
 *                'semua' => ['jenis' => [kode…], 'bulan' => 'YYYY-MM', 'jumlah_bulan' => 1..12]]
 * 'semua' berlaku untuk SEMUA siswa dalam unduhan (unduh massal); 'biaya' per siswa (unduh satu surat).
 */
final class PklBiaya
{
    public const MAKS_BULAN   = 12;
    public const SUMBER_BEASISWA = ['sktm' => 'SKTM', 'yayasan' => 'Kebijakan Yayasan', 'lainnya' => 'Lainnya'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Pembantu murni
    // =================================================================

    public static function rupiah(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    public static function bulanIni(): string
    {
        return date('Y-m');
    }

    /** "2026-10" yang sah (bulan 01–12, tahun antara tahun lalu s/d tahun depan), selain itu null. */
    public static function bulanSah(string $b): ?string
    {
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', trim($b), $m)) {
            return null;
        }
        $tahun = (int) $m[1];

        return ($tahun >= (int) date('Y') - 1 && $tahun <= (int) date('Y') + 1) ? trim($b) : null;
    }

    /** Geser bulan: ("2026-11", 2) → "2027-01". */
    public static function geserBulan(string $b, int $n): string
    {
        [$y, $m] = array_map('intval', explode('-', $b));
        $idx = $y * 12 + ($m - 1) + $n;

        return sprintf('%04d-%02d', intdiv($idx, 12), ($idx % 12) + 1);
    }

    /** "2026-10" → "Okt 2026". */
    public static function labelBulan(string $b): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $b, $m)) {
            return $b;
        }
        $nama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return ($nama[(int) $m[2] - 1] ?? $m[2]) . ' ' . $m[1];
    }

    /** Label periode sesuai siklus: bulanan "Okt 2026"; kegiatan apa adanya ("2026/2027"). */
    public static function labelPeriode(string $siklus, string $periode): string
    {
        return $siklus === 'bulanan' ? self::labelBulan($periode) : $periode;
    }

    /** Periode biaya kegiatan = tahun ajaran ajuan (cadangan: tahun ajaran sekolah, lalu tahun berjalan). */
    public static function periodeKegiatan(array $ajuan): string
    {
        $ta = trim((string) ($ajuan['tahun_ajaran'] ?? ''));
        if ($ta === '') {
            $ta = trim((string) ((new \App\Models\SettingModel())->get()['academic_year'] ?? ''));
        }

        return mb_substr($ta !== '' ? $ta : date('Y'), 0, 20);
    }

    /** Akhir Juni (tahun masuk + 3), atau null bila tahun masuk tak diketahui / sudah lewat. */
    public static function akhirBeasiswa(?int $tahunMasuk): ?string
    {
        if ($tahunMasuk === null || $tahunMasuk < 2000 || $tahunMasuk > 2100) {
            return null;
        }
        $akhir = ($tahunMasuk + 3) . '-06-30';

        return $akhir >= date('Y-m-d') ? $akhir : null;
    }

    // =================================================================
    // Jenis biaya
    // =================================================================

    /**
     * @return list<array<string, mixed>> jenis biaya urut tampil; aktif saja kecuali $semua
     */
    public function jenis(bool $semua = false): array
    {
        $b = $this->db->table('pkl_biaya')->orderBy('urut', 'ASC')->orderBy('id', 'ASC');
        if (! $semua) {
            $b->where('aktif', 1);
        }

        return array_map(static function (array $r): array {
            $r['id'] = (int) $r['id'];
            $r['nominal'] = (int) $r['nominal'];
            $r['aktif'] = (int) $r['aktif'];
            $r['urut']  = (int) $r['urut'];

            return $r;
        }, $b->get()->getResultArray());
    }

    /** @return array<string, array<string, mixed>> kode => jenis aktif */
    public function jenisPeta(): array
    {
        $out = [];
        foreach ($this->jenis() as $j) {
            $out[$j['kode']] = $j;
        }

        return $out;
    }

    /**
     * Simpan nominal / nama / aktif dari Pengaturan PKL. $kirim[kode] = ['nama', 'nominal', 'aktif'].
     * Kode & siklus tak bisa diubah (rumus laporan bergantung padanya). Sekurangnya satu jenis tetap aktif.
     *
     * @return array{ok: bool, pesan: string, galat: array<string, string>}
     */
    public function simpanJenis(array $kirim, array $konteks): array
    {
        $galat = [];
        $baru  = [];
        foreach ($this->jenis(true) as $j) {
            $k = $kirim[$j['kode']] ?? [];
            if (! is_array($k)) {
                $k = [];
            }
            $nama = IsianBantu::rapikan((string) ($k['nama'] ?? $j['nama']));
            if ($nama === '' || mb_strlen($nama) > 80) {
                $galat[$j['kode'] . '.nama'] = 'Nama biaya wajib diisi (maksimal 80 huruf).';
            }
            $mentah = preg_replace('/[^0-9]/', '', (string) ($k['nominal'] ?? ''));
            if ($mentah === '' || strlen($mentah) > 9) {
                $galat[$j['kode'] . '.nominal'] = 'Isi nominal dalam rupiah (angka saja, tanpa titik).';
            }
            $baru[$j['kode']] = ['nama' => $nama, 'nominal' => (int) $mentah, 'aktif' => ! empty($k['aktif']) ? 1 : 0, 'id' => $j['id'], 'lama' => $j];
        }
        if ($baru !== [] && array_sum(array_column($baru, 'aktif')) === 0) {
            $galat['umum'] = 'Sekurangnya satu jenis biaya harus aktif.';
        }
        if ($galat !== []) {
            return ['ok' => false, 'pesan' => 'Biaya belum disimpan. Periksa isian bertanda merah.', 'galat' => $galat];
        }

        $ubah = [];
        $now  = date('Y-m-d H:i:s');
        foreach ($baru as $kode => $b) {
            $l = $b['lama'];
            if ($b['nama'] !== $l['nama'] || $b['nominal'] !== $l['nominal'] || $b['aktif'] !== $l['aktif']) {
                $this->db->table('pkl_biaya')->where('id', $b['id'])->update(['nama' => $b['nama'], 'nominal' => $b['nominal'], 'aktif' => $b['aktif'], 'updated_at' => $now]);
                $ubah[] = $b['nama'] . ' ' . self::rupiah($b['nominal']) . ($b['aktif'] ? '' : ' (nonaktif)');
            }
        }
        if ($ubah !== []) {
            (new AuditModel())->record('update', 'pkl_biaya', null, mb_substr('Biaya PKL diubah oleh ' . ($konteks['oleh'] ?? '?') . ': ' . implode('; ', $ubah), 0, 255));
        }

        return ['ok' => true, 'pesan' => $ubah === [] ? 'Tidak ada perubahan biaya.' : 'Biaya disimpan. Catatan lama tidak berubah; nominal baru dipakai untuk catatan berikutnya.', 'galat' => []];
    }

    // =================================================================
    // Keadaan siswa pada unduhan ini
    // =================================================================

    /** @return array<int, array<string, mixed>> siswa_id => baris beasiswa YANG MASIH BERLAKU */
    public function beasiswaAktif(array $siswaIds): array
    {
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds))));
        if ($siswaIds === []) {
            return [];
        }
        $out = [];
        $hari = date('Y-m-d');
        foreach ($this->db->table('pkl_beasiswa')->whereIn('siswa_id', $siswaIds)->get()->getResultArray() as $r) {
            if ($r['berakhir_at'] === null || $r['berakhir_at'] >= $hari) {
                $out[(int) $r['siswa_id']] = $r;
            }
        }

        return $out;
    }

    /**
     * Keadaan semua siswa pada ajuan-ajuan yang mau diunduh: siapa, kelas, apa yang SUDAH tercatat, beasiswa.
     * Dipakai kotak dialog (JSON) dan rencana().
     *
     * @param list<int> $ajuanIds hanya yang berstatus disetujui yang dimuat
     *
     * @return array{surat: list<array<string,mixed>>, siswa: array<int, array<string,mixed>>, jenis: list<array<string,mixed>>, bulan_default: string}
     */
    public function siap(array $ajuanIds): array
    {
        $ajuanIds = array_values(array_unique(array_filter(array_map('intval', $ajuanIds))));
        $out = ['surat' => [], 'siswa' => [], 'jenis' => $this->jenis(), 'bulan_default' => self::bulanIni()];
        if ($ajuanIds === []) {
            return $out;
        }

        $model = new PklPengajuanModel();
        $ajuanRows = [];
        foreach ($this->db->table('pkl_pengajuan')->whereIn('id', $ajuanIds)->where('status', 'disetujui')->get()->getResultArray() as $r) {
            $ajuanRows[(int) $r['id']] = $r;
        }
        $nomor = [];
        foreach ($this->db->table('pkl_surat')->select('pengajuan_id, nomor')->whereIn('pengajuan_id', array_keys($ajuanRows) ?: [0])->get()->getResultArray() as $r) {
            $nomor[(int) $r['pengajuan_id']] = (string) $r['nomor'];
        }

        $semuaSiswa = [];
        foreach ($ajuanIds as $id) {
            if (! isset($ajuanRows[$id])) {
                continue;
            }
            $a = $ajuanRows[$id];
            $periodeKegiatan = self::periodeKegiatan($a);
            $out['surat'][] = ['id' => $id, 'kode' => PklPengajuanModel::kode($id), 'perusahaan' => (string) $a['perusahaan_nama'], 'nomor' => $nomor[$id] ?? null, 'periode_kegiatan' => $periodeKegiatan];
            foreach ($model->anggotaDetail($id) as $s) {
                $sid = (int) $s['siswa_id'];
                $semuaSiswa[] = $sid;
                $out['siswa'][$sid] = [
                    'siswa_id' => $sid, 'nama' => (string) $s['nama'], 'peran' => (string) $s['peran'], 'kelas' => (string) ($s['nama_kelas'] ?? ''),
                    'jurusan' => $s['jurusan_nama'] !== null ? SiswaResmi8355::jurusanDb(null, (string) $s['jurusan_nama']) : '',
                    'hp' => trim((string) ($s['hp'] ?? '')) !== '' ? (string) $s['hp'] : (string) ($s['hp_master'] ?? ''),
                    'ajuan_id' => $id, 'periode_kegiatan' => $periodeKegiatan,
                    'tercatat' => [], 'milik_ajuan' => 0, 'kegiatan_ada' => false, 'ada_keringanan' => false, 'beasiswa' => null, 'tahun_masuk' => null,
                ];
            }
        }
        if ($semuaSiswa === []) {
            return $out;
        }

        $kode = [];
        foreach ($this->jenis(true) as $j) {
            $kode[$j['id']] = $j;
        }
        foreach ($this->db->table('pkl_pembayaran')->whereIn('siswa_id', array_unique($semuaSiswa))->get()->getResultArray() as $r) {
            $sid = (int) $r['siswa_id'];
            $j = $kode[(int) $r['biaya_id']] ?? null;
            if ($j === null || ! isset($out['siswa'][$sid])) {
                continue;
            }
            $out['siswa'][$sid]['tercatat'][$j['kode']][] = (string) $r['periode'];
            if ((int) $r['pengajuan_id'] === $out['siswa'][$sid]['ajuan_id']) {
                $out['siswa'][$sid]['milik_ajuan']++;
            }
            if ($j['siklus'] === 'kegiatan' && (string) $r['periode'] === $out['siswa'][$sid]['periode_kegiatan']) {
                $out['siswa'][$sid]['kegiatan_ada'] = true;
            }
        }
        // Keringanan/penundaan yang pernah dicatat untuk ajuan ini tetap dihitung sebagai catatan (cetak ulang tak perlu mengulang alasan).
        foreach ($this->db->table('pkl_keringanan')->select('siswa_id, pengajuan_id')->whereIn('siswa_id', array_unique($semuaSiswa))->get()->getResultArray() as $r) {
            $sid = (int) $r['siswa_id'];
            if (isset($out['siswa'][$sid]) && (int) $r['pengajuan_id'] === $out['siswa'][$sid]['ajuan_id']) {
                $out['siswa'][$sid]['ada_keringanan'] = true;
            }
        }
        foreach ($this->beasiswaAktif(array_keys($out['siswa'])) as $sid => $b) {
            $out['siswa'][$sid]['beasiswa'] = ['sumber' => (string) $b['sumber'], 'sumber_label' => self::SUMBER_BEASISWA[$b['sumber']] ?? (string) $b['sumber'], 'keterangan' => $b['keterangan'], 'berakhir_at' => $b['berakhir_at']];
        }
        foreach ($this->db->table('siswa')->select('id, tahun_masuk')->whereIn('id', array_keys($out['siswa']))->get()->getResultArray() as $r) {
            $out['siswa'][(int) $r['id']]['tahun_masuk'] = $r['tahun_masuk'] !== null ? (int) $r['tahun_masuk'] : null;
        }

        return $out;
    }

    // =================================================================
    // 1. Rencana (validasi) — belum menulis apa pun
    // =================================================================

    /**
     * @param list<int>            $ajuanIds
     * @param array<string, mixed> $in       kiriman (lihat kepala berkas)
     *
     * @return array{ok: bool, galat: array<string, string>, rencana: array<int, array<string, mixed>>, ringkas: array<string, mixed>}
     */
    public function rencana(array $ajuanIds, array $in): array
    {
        $data  = $this->siap($ajuanIds);
        $peta  = $this->jenisPeta();
        $semua = is_array($in['semua'] ?? null) ? $in['semua'] : [];
        $per   = is_array($in['biaya'] ?? null) ? $in['biaya'] : [];

        $galat   = [];
        $rencana = [];
        $belum   = [];

        // Kode jenis tak dikenal / nonaktif (sekali, umum).
        $kodeKiriman = array_merge($this->daftarKode($semua['jenis'] ?? []), ...array_map(fn ($x) => $this->daftarKode(is_array($x) ? ($x['jenis'] ?? []) : []), array_values($per)));
        foreach (array_unique($kodeKiriman) as $kode) {
            if (! isset($peta[$kode])) {
                $galat['umum'] = 'Jenis biaya "' . mb_substr(IsianBantu::rapikan($kode), 0, 30) . '" tidak dikenal atau sedang dinonaktifkan. Muat ulang halaman.';
            }
        }

        foreach ($data['siswa'] as $sid => $s) {
            $spec = is_array($per[$sid] ?? null) ? $per[$sid] : [];

            $kode  = array_values(array_unique(array_merge($this->daftarKode($semua['jenis'] ?? []), $this->daftarKode($spec['jenis'] ?? []))));
            $bulan = $this->ambilBulan($spec['bulan'] ?? ($semua['bulan'] ?? ''));
            if ($bulan === false) {
                $galat[(string) $sid] = 'Bulan tidak valid untuk ' . $s['nama'] . '.';
                continue;
            }
            $jumlah = $this->ambilJumlah($spec['jumlah_bulan'] ?? ($semua['jumlah_bulan'] ?? 1));
            if ($jumlah === null) {
                $galat[(string) $sid] = 'Jumlah bulan untuk ' . $s['nama'] . ' harus 1–' . self::MAKS_BULAN . '.';
                continue;
            }

            // Beasiswa: yang berlaku sekarang, atau yang baru dicentang pada unduhan ini (hanya kiriman per siswa).
            $beasiswaBaru = null;
            $sumber = (string) ($spec['beasiswa'] ?? '');
            if ($sumber !== '' && $s['beasiswa'] === null) {
                if (! isset(self::SUMBER_BEASISWA[$sumber])) {
                    $galat[(string) $sid] = 'Sumber beasiswa ' . $s['nama'] . ' tidak dikenal.';
                    continue;
                }
                $beasiswaBaru = [
                    'sumber' => $sumber, 'keterangan' => mb_substr(IsianBantu::rapikan((string) ($spec['beasiswa_ket'] ?? '')), 0, 150) ?: null,
                    'berakhir_at' => self::akhirBeasiswa($s['tahun_masuk']),
                ];
            }
            $bebasSpp = $s['beasiswa'] !== null || $beasiswaBaru !== null;

            $keringanan = null;
            $alasan = IsianBantu::rapikan((string) ($spec['keringanan'] ?? ''));
            if ($alasan !== '') {
                if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
                    $galat[(string) $sid] = 'Alasan keringanan ' . $s['nama'] . ' wajib 5–255 huruf.';
                    continue;
                }
                $keringanan = $alasan;
            }

            $baru  = [];
            $sudah = [];
            $lewat = [];
            foreach ($kode as $k) {
                if (! isset($peta[$k])) {
                    continue;
                }
                $j = $peta[$k];
                if ($k === 'spp' && $bebasSpp) {
                    $lewat[] = 'SPP dibebaskan (beasiswa)';
                    continue;
                }
                $periodes = $j['siklus'] === 'bulanan'
                    ? array_map(fn (int $i) => self::geserBulan($bulan, $i), range(0, $jumlah - 1))
                    : [$s['periode_kegiatan']];
                foreach ($periodes as $p) {
                    if (in_array($p, $s['tercatat'][$k] ?? [], true)) {
                        $sudah[$k][] = $p;
                    } else {
                        $baru[] = ['biaya_id' => $j['id'], 'kode' => $k, 'nama' => $j['nama'], 'siklus' => $j['siklus'], 'periode' => $p, 'nominal' => $j['nominal']];
                    }
                }
            }

            $memenuhi = $baru !== [] || $sudah !== [] || $s['milik_ajuan'] > 0 || $s['kegiatan_ada'] || $s['ada_keringanan'] || $bebasSpp || $keringanan !== null;
            if (! $memenuhi) {
                $belum[] = $s['nama'] . ($s['kelas'] !== '' ? ' (' . $s['kelas'] . ')' : '');
            }

            $rencana[$sid] = [
                'siswa_id' => $sid, 'ajuan_id' => $s['ajuan_id'], 'nama' => $s['nama'], 'kelas' => $s['kelas'],
                'baru' => $baru, 'sudah' => $sudah, 'lewat' => $lewat, 'beasiswa_baru' => $beasiswaBaru, 'keringanan' => $keringanan,
            ];
        }

        if ($belum !== []) {
            $contoh = array_slice($belum, 0, 6);
            $galat['umum'] = ($galat['umum'] ?? '') . ($galat['umum'] ?? '' ? ' ' : '')
                . 'Wajib mencatat biaya: ' . count($belum) . ' siswa belum punya catatan (' . implode(', ', $contoh) . (count($belum) > 6 ? ', …' : '')
                . '). Centang minimal satu biaya, atau Beasiswa / Keringanan beralasan.';
        }
        if ($data['siswa'] === []) {
            $galat['umum'] = 'Tidak ada siswa pada ajuan yang berstatus Disetujui.';
        }

        $total = 0;
        $item  = 0;
        foreach ($rencana as $r) {
            foreach ($r['baru'] as $b) {
                $total += (int) $b['nominal'];
                $item++;
            }
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'rencana' => $rencana, 'ringkas' => ['siswa' => count($rencana), 'item_baru' => $item, 'total_baru' => $total]];
    }

    /** @return list<string> kode jenis (huruf kecil, alfanumerik) dari kiriman apa pun */
    private function daftarKode($mentah): array
    {
        $out = [];
        foreach ((array) $mentah as $k) {
            $k = strtolower(trim((string) $k));
            if ($k !== '') {
                $out[] = preg_replace('/[^a-z0-9_]/', '', $k) ?? '';
            }
        }

        return array_values(array_filter(array_unique($out), static fn (string $x) => $x !== ''));
    }

    /** "" → bulan ini; sah → dirinya; tidak sah → false. */
    private function ambilBulan($mentah)
    {
        $b = trim((string) $mentah);
        if ($b === '') {
            return self::bulanIni();
        }

        return self::bulanSah($b) ?? false;
    }

    private function ambilJumlah($mentah): ?int
    {
        $t = trim((string) $mentah);
        if ($t === '') {
            return 1;
        }

        return (ctype_digit($t) && (int) $t >= 1 && (int) $t <= self::MAKS_BULAN) ? (int) $t : null;
    }

    // =================================================================
    // 2. Catat — setelah berkas surat berhasil dibuat
    // =================================================================

    /**
     * @param array<int, array<string, mixed>> $rencana hasil rencana()['rencana']
     * @param array<string, mixed>             $konteks ['oleh', 'admin_id', 'peran', 'ip']
     *
     * @return array{ok: bool, pesan?: string, item: int, total: int}
     */
    public function catat(array $rencana, array $konteks): array
    {
        $now = date('Y-m-d H:i:s');
        $oleh = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        $item = 0;
        $total = 0;
        $perAjuan = [];

        $this->db->transBegin();
        try {
            foreach ($rencana as $r) {
                $ringkas = [];
                foreach ($r['baru'] as $b) {
                    $this->db->table('pkl_pembayaran')->ignore(true)->insert([
                        'siswa_id' => (int) $r['siswa_id'], 'biaya_id' => (int) $b['biaya_id'], 'periode' => $b['periode'], 'nominal' => (int) $b['nominal'],
                        'pengajuan_id' => (int) $r['ajuan_id'], 'dicatat_oleh_id' => $konteks['admin_id'] ?? null, 'dicatat_oleh' => $oleh,
                        'dicatat_peran' => $konteks['peran'] ?? null, 'created_at' => $now,
                    ]);
                    if ($this->db->affectedRows() > 0) { // 0 = sudah dicatat pengguna lain barusan (UNIQUE) → jangan dihitung dua kali
                        $item++;
                        $total += (int) $b['nominal'];
                        $ringkas[] = $b['nama'] . ($b['siklus'] === 'bulanan' ? ' ' . self::labelBulan($b['periode']) : '');
                    }
                }
                if ($r['beasiswa_baru'] !== null) {
                    $this->db->table('pkl_beasiswa')->ignore(true)->insert([
                        'siswa_id' => (int) $r['siswa_id'], 'sumber' => $r['beasiswa_baru']['sumber'], 'keterangan' => $r['beasiswa_baru']['keterangan'],
                        'berakhir_at' => $r['beasiswa_baru']['berakhir_at'], 'dicatat_oleh_id' => $konteks['admin_id'] ?? null, 'dicatat_oleh' => $oleh, 'created_at' => $now,
                    ]);
                    if ($this->db->affectedRows() > 0) {
                        $ringkas[] = 'Beasiswa ' . (self::SUMBER_BEASISWA[$r['beasiswa_baru']['sumber']] ?? '');
                    }
                }
                if ($r['keringanan'] !== null) {
                    $this->db->table('pkl_keringanan')->insert([
                        'siswa_id' => (int) $r['siswa_id'], 'pengajuan_id' => (int) $r['ajuan_id'], 'alasan' => $r['keringanan'],
                        'dicatat_oleh_id' => $konteks['admin_id'] ?? null, 'dicatat_oleh' => $oleh, 'created_at' => $now,
                    ]);
                    $ringkas[] = 'Keringanan';
                }
                if ($ringkas !== []) {
                    $perAjuan[(int) $r['ajuan_id']][] = $r['nama'] . ' (' . implode(', ', $ringkas) . ')';
                }
            }

            $svc = new PklAjuan($this->db);
            foreach ($perAjuan as $ajuanId => $baris) {
                $svc->catat($ajuanId, 'bayar', $konteks, 'Biaya dicatat: ' . implode('; ', $baris));
            }
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('transaksi pembayaran gagal');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[PKL] catat pembayaran gagal: ' . $e->getMessage());

            return ['ok' => false, 'pesan' => 'Catatan biaya gagal disimpan. Coba unduh lagi.', 'item' => 0, 'total' => 0];
        }

        if ($item > 0 || $perAjuan !== []) {
            (new AuditModel())->record('create', 'pkl_pembayaran', null, mb_substr('Biaya PKL dicatat saat unduh surat: ' . $item . ' item, ' . self::rupiah($total) . ', ' . count($perAjuan) . ' ajuan oleh ' . $oleh, 0, 255));
        }

        return ['ok' => true, 'item' => $item, 'total' => $total];
    }

    // =================================================================
    // Tampilan & koreksi per ajuan
    // =================================================================

    /**
     * Catatan biaya semua siswa pada satu ajuan, untuk kartu "Pembayaran" di halaman detail.
     *
     * @return list<array<string, mixed>> per siswa: nama, kelas, pembayaran[], beasiswa, keringanan[]
     */
    public function untukAjuan(int $ajuanId): array
    {
        $anggota = (new PklPengajuanModel())->anggotaDetail($ajuanId);
        $ids = array_map(static fn (array $a) => (int) $a['siswa_id'], $anggota);
        if ($ids === []) {
            return [];
        }
        $jenis = [];
        foreach ($this->jenis(true) as $j) {
            $jenis[$j['id']] = $j;
        }
        $bayar = [];
        foreach ($this->db->table('pkl_pembayaran')->whereIn('siswa_id', $ids)->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            $j = $jenis[(int) $r['biaya_id']] ?? ['nama' => '?', 'siklus' => 'kegiatan', 'kode' => '?'];
            $bayar[(int) $r['siswa_id']][] = [
                'id' => (int) $r['id'], 'kode' => $j['kode'], 'nama' => $j['nama'], 'periode' => (string) $r['periode'],
                'periode_label' => self::labelPeriode($j['siklus'], (string) $r['periode']), 'nominal' => (int) $r['nominal'],
                'oleh' => (string) $r['dicatat_oleh'], 'waktu' => (string) $r['created_at'], 'ajuan_id' => $r['pengajuan_id'] !== null ? (int) $r['pengajuan_id'] : null,
            ];
        }
        $beasiswa = [];
        foreach ($this->db->table('pkl_beasiswa')->whereIn('siswa_id', $ids)->get()->getResultArray() as $r) {
            $beasiswa[(int) $r['siswa_id']] = [
                'sumber' => (string) $r['sumber'], 'sumber_label' => self::SUMBER_BEASISWA[$r['sumber']] ?? (string) $r['sumber'], 'keterangan' => $r['keterangan'],
                'berakhir_at' => $r['berakhir_at'], 'berlaku' => $r['berakhir_at'] === null || $r['berakhir_at'] >= date('Y-m-d'), 'oleh' => (string) $r['dicatat_oleh'],
            ];
        }
        $ringan = [];
        foreach ($this->db->table('pkl_keringanan')->whereIn('siswa_id', $ids)->orderBy('id', 'DESC')->get()->getResultArray() as $r) {
            $ringan[(int) $r['siswa_id']][] = ['alasan' => (string) $r['alasan'], 'oleh' => (string) $r['dicatat_oleh'], 'waktu' => (string) $r['created_at']];
        }

        $out = [];
        foreach ($anggota as $a) {
            $sid = (int) $a['siswa_id'];
            $rows = $bayar[$sid] ?? [];
            $out[] = [
                'siswa_id' => $sid, 'nama' => (string) $a['nama'], 'peran' => (string) $a['peran'], 'kelas' => (string) ($a['nama_kelas'] ?? ''),
                'pembayaran' => $rows, 'total' => array_sum(array_column($rows, 'nominal')),
                'beasiswa' => $beasiswa[$sid] ?? null, 'keringanan' => $ringan[$sid] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Hapus satu catatan pembayaran yang salah centang. Alasan wajib; tercatat di riwayat ajuan & Audit Log.
     *
     * @return array{ok: bool, pesan: string, http: int}
     */
    public function hapusPembayaran(int $id, int $ajuanId, string $alasan, array $konteks): array
    {
        $alasan = IsianBantu::rapikan($alasan);
        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 200) {
            return ['ok' => false, 'pesan' => 'Alasan koreksi wajib diisi (5–200 huruf).', 'http' => 422];
        }
        $r = $this->db->table('pkl_pembayaran p')->select('p.*, b.nama AS biaya_nama, b.siklus, s.nama AS siswa_nama')
            ->join('pkl_biaya b', 'b.id = p.biaya_id')->join('siswa s', 's.id = p.siswa_id')->where('p.id', $id)->get()->getRowArray();
        if ($r === null) {
            return ['ok' => false, 'pesan' => 'Catatan pembayaran tidak ditemukan (mungkin sudah dihapus).', 'http' => 404];
        }
        // Hanya catatan milik siswa pada ajuan ini yang boleh dikoreksi lewat halaman ajuan ini.
        $ada = $this->db->table('pkl_anggota')->where('pengajuan_id', $ajuanId)->where('siswa_id', (int) $r['siswa_id'])->countAllResults();
        if ($ada === 0) {
            return ['ok' => false, 'pesan' => 'Catatan ini bukan milik siswa pada ajuan ini.', 'http' => 403];
        }

        $this->db->table('pkl_pembayaran')->where('id', $id)->delete();
        $label = $r['biaya_nama'] . ($r['siklus'] === 'bulanan' ? ' ' . self::labelBulan((string) $r['periode']) : ' ' . $r['periode']) . ' ' . self::rupiah((int) $r['nominal']);
        (new PklAjuan($this->db))->catat($ajuanId, 'koreksi_bayar', $konteks, mb_substr('Catatan dihapus: ' . $r['siswa_nama'] . ' — ' . $label . ' — ' . $alasan, 0, 255));
        (new AuditModel())->record('delete', 'pkl_pembayaran', $id, mb_substr('Catatan biaya dihapus: ' . $r['siswa_nama'] . ' — ' . $label . ' — alasan: ' . $alasan, 0, 255));

        return ['ok' => true, 'pesan' => 'Catatan "' . $label . '" milik ' . $r['siswa_nama'] . ' dihapus.', 'http' => 200];
    }

    /** @return array{ok: bool, pesan: string, http: int} */
    public function cabutBeasiswa(int $siswaId, int $ajuanId, string $alasan, array $konteks): array
    {
        $alasan = IsianBantu::rapikan($alasan);
        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 200) {
            return ['ok' => false, 'pesan' => 'Alasan koreksi wajib diisi (5–200 huruf).', 'http' => 422];
        }
        $ada = $this->db->table('pkl_anggota')->where('pengajuan_id', $ajuanId)->where('siswa_id', $siswaId)->countAllResults();
        $b   = $this->db->table('pkl_beasiswa b')->select('b.*, s.nama')->join('siswa s', 's.id = b.siswa_id')->where('b.siswa_id', $siswaId)->get()->getRowArray();
        if ($ada === 0 || $b === null) {
            return ['ok' => false, 'pesan' => 'Beasiswa siswa ini tidak ditemukan pada ajuan ini.', 'http' => 404];
        }
        $this->db->table('pkl_beasiswa')->where('siswa_id', $siswaId)->delete();
        (new PklAjuan($this->db))->catat($ajuanId, 'koreksi_bayar', $konteks, mb_substr('Beasiswa dicabut: ' . $b['nama'] . ' — ' . $alasan, 0, 255));
        (new AuditModel())->record('delete', 'pkl_beasiswa', (int) $b['id'], mb_substr('Beasiswa dicabut: ' . $b['nama'] . ' — alasan: ' . $alasan, 0, 255));

        return ['ok' => true, 'pesan' => 'Beasiswa ' . $b['nama'] . ' dicabut.', 'http' => 200];
    }
}
