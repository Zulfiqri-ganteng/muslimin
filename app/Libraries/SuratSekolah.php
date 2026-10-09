<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Register surat sekolah ("buku agenda surat keluar"): membuat surat, memuat & mendaftar, menerbitkan nomor,
 * sidik data, riwayat. Alur ACC dan perakitan berkas Word ada di langkah berikutnya. Rancangan: docs/DESAIN-SURAT-SEKOLAH.md.
 *
 * Aturan:
 *   - jenis wajib ACC → status awal 'menunggu'; jenis tanpa ACC → langsung 'disetujui' (= siap unduh, tanpa catatan ACC);
 *   - nomor ditetapkan SEKALI, saat pertama diunduh, memakai SATU urutan dengan Surat Izin PKL (Libraries\SuratNomor) di bawah
 *     kunci baris pkl_pengaturan; cetak ulang memakai nomor yang sama; jenis tanpa nomor (Pernyataan Orang Tua) tak pernah bernomor;
 *   - sidik data disimpan tiap cetak → bila data berubah sesudahnya = "perlu cetak ulang".
 *
 * $konteks = ['oleh' => nama pelaku, 'admin_id' => ?int, 'peran' => string, 'ip' => ?string] (sama dengan PklAjuan).
 */
final class SuratSekolah
{
    public const PER_HALAMAN = 25;
    public const MAKS_ISI    = 60000; // byte JSON isian khusus jenis
    public const MAKS_SISWA  = 400;   // siswa dalam satu surat (Pernyataan per kelas bisa puluhan)

    /** aksi riwayat → kalimat tampil */
    public const AKSI = [
        'buat' => 'Surat dibuat', 'ubah' => 'Data surat diubah', 'ajukan_ulang' => 'Diajukan ulang untuk ACC', 'acc' => 'Disetujui (ACC)',
        'kembalikan' => 'Dikembalikan untuk diperbaiki', 'batal_acc' => 'Persetujuan dibatalkan', 'batal' => 'Surat dibatalkan',
        'nomor' => 'Nomor surat diterbitkan', 'cetak' => 'Berkas diunduh',
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Membuat
    // =================================================================

    /**
     * Buat satu surat (belum bernomor).
     *
     * $data: judul, tanggal_surat (Y-m-d), perusahaan_nama?, pengajuan_id?, isi (array → JSON),
     *        siswa (list<['siswa_id' => int, 'kelas_id' => ?int, 'hp' => ?string]>, urutan = urutan di surat).
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>      $konteks
     * @param array<string, mixed>|null $p       baris pkl_pengaturan (dibaca bila null)
     *
     * @return array{ok: bool, id?: int, status?: string, kode?: string, pesan?: string}
     */
    public function buat(string $jenis, array $data, array $konteks, ?array $p = null): array
    {
        if (! SuratJenis::ada($jenis)) {
            return $this->gagal('jenis', 'Jenis surat tidak dikenal.');
        }
        $judul = IsianBantu::rapikan((string) ($data['judul'] ?? ''));
        if ($judul === '' || mb_strlen($judul) > 190) {
            return $this->gagal('judul', 'Judul surat wajib diisi (maksimal 190 huruf).');
        }
        $tanggal = (string) ($data['tanggal_surat'] ?? '');
        if (! self::tanggalSah($tanggal)) {
            return $this->gagal('tanggal', 'Tanggal surat tidak valid.');
        }
        $isi  = $data['isi'] ?? [];
        $json = is_array($isi) ? json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : false;
        if ($json === false || strlen($json) > self::MAKS_ISI) {
            return $this->gagal('isi', 'Isi surat tidak valid atau terlalu besar.');
        }
        $siswa = $this->rapikanSiswa((array) ($data['siswa'] ?? []));
        if (count($siswa) > self::MAKS_SISWA) {
            return $this->gagal('siswa', 'Terlalu banyak siswa dalam satu surat (maksimal ' . self::MAKS_SISWA . ').');
        }
        $siswa      = $this->lengkapiKelas($siswa);
        $perusahaan = IsianBantu::rapikan((string) ($data['perusahaan_nama'] ?? ''));
        $pengajuan  = (int) ($data['pengajuan_id'] ?? 0) ?: null;

        $p      ??= (new PklPengaturanModel())->ambil();
        $perluAcc = SuratJenis::perluAcc($jenis, $p);
        $status   = $perluAcc ? 'menunggu' : 'disetujui';
        $now      = date('Y-m-d H:i:s');
        $oleh     = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);

        $this->db->transBegin();
        try {
            $ok = $this->db->table('surat_sekolah')->insert([
                'jenis' => $jenis, 'status' => $status, 'perlu_acc' => $perluAcc ? 1 : 0, 'judul' => $judul, 'tanggal_surat' => $tanggal,
                'pengajuan_id' => $pengajuan, 'perusahaan_nama' => $perusahaan !== '' ? mb_substr($perusahaan, 0, 150) : null,
                'isi' => $json, 'diajukan_at' => $perluAcc ? $now : null,
                'dibuat_oleh' => ($konteks['admin_id'] ?? null) ?: null, 'dibuat_nama' => $oleh,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $this->db->insertID();
            if (! $ok || $id < 1) {
                throw new \RuntimeException('surat tidak tersimpan');
            }
            foreach ($siswa as $i => $s) {
                $ok = $this->db->table('surat_sekolah_siswa')->insert([
                    'surat_id' => $id, 'siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp'],
                    'urut' => $i + 1, 'created_at' => $now,
                ]);
                if (! $ok) {
                    throw new \RuntimeException('siswa #' . $s['siswa_id'] . ' tidak tersimpan');
                }
            }
            $this->catat($id, 'buat', $konteks, $perluAcc ? 'Menunggu ACC' : 'Jenis ini tanpa ACC: langsung siap unduh');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Surat] buat gagal: ' . $e->getMessage());

            return $this->gagal('galat', 'Surat gagal disimpan. Periksa data siswanya, lalu coba lagi.');
        }

        (new AuditModel())->record('create', 'surat_sekolah', $id, SuratJenis::label($jenis) . ' dibuat: ' . $judul);

        return ['ok' => true, 'id' => $id, 'status' => $status, 'kode' => SuratSekolahModel::kode($id)];
    }

    // =================================================================
    // Mengubah
    // =================================================================

    /**
     * Ubah data sebuah surat yang BELUM disetujui dan BELUM bernomor (status menunggu / dikembalikan). Status tidak berubah:
     * surat yang dikembalikan tetap "dikembalikan" sampai diajukan ulang. Surat yang sudah disetujui harus dibatalkan
     * persetujuannya dulu; yang sudah bernomor hanya bisa dibatalkan (nomor tak boleh bergeser).
     *
     * $data seperti buat() tetapi tanpa jenis: judul, tanggal_surat, isi, perusahaan_nama (opsional), siswa (opsional;
     * bila ada, daftar siswa DIGANTI seluruhnya).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, kode?: string, pesan?: string}
     */
    public function ubah(int $id, array $data, array $konteks): array
    {
        $judul = IsianBantu::rapikan((string) ($data['judul'] ?? ''));
        if ($judul === '' || mb_strlen($judul) > 190) {
            return $this->gagal('judul', 'Judul surat wajib diisi (maksimal 190 huruf).');
        }
        $tanggal = (string) ($data['tanggal_surat'] ?? '');
        if (! self::tanggalSah($tanggal)) {
            return $this->gagal('tanggal', 'Tanggal surat tidak valid.');
        }
        $json = is_array($data['isi'] ?? null) ? json_encode($data['isi'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : false;
        if ($json === false || strlen($json) > self::MAKS_ISI) {
            return $this->gagal('isi', 'Isi surat tidak valid atau terlalu besar.');
        }
        $siswa = null;
        if (array_key_exists('siswa', $data)) {
            $siswa = $this->lengkapiKelas($this->rapikanSiswa((array) $data['siswa']));
            if (count($siswa) > self::MAKS_SISWA) {
                return $this->gagal('siswa', 'Terlalu banyak siswa dalam satu surat (maksimal ' . self::MAKS_SISWA . ').');
            }
        }

        $this->db->transBegin();
        try {
            $s = $this->db->query('SELECT status, nomor, jenis FROM surat_sekolah WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if ($s === null) {
                $this->db->transRollback();

                return $this->gagal('tidak_ada', 'Surat tidak ditemukan (mungkin sudah dihapus).');
            }
            if ((string) ($s['nomor'] ?? '') !== '') {
                $this->db->transRollback();

                return $this->gagal('terbit', 'Surat ini sudah bernomor, jadi datanya tidak bisa diubah. Bila salah, batalkan surat lalu buat yang baru.');
            }
            if (! in_array($s['status'], ['menunggu', 'dikembalikan'], true)) {
                $this->db->transRollback();

                return $this->gagal('status', 'Surat berstatus "' . (SuratSekolahModel::TAMPIL_STATUS[$s['status']][0] ?? $s['status']) . '" tidak bisa diubah. Surat yang sudah disetujui harus dibatalkan persetujuannya dulu.');
            }

            $set = ['judul' => $judul, 'tanggal_surat' => $tanggal, 'isi' => $json, 'updated_at' => date('Y-m-d H:i:s')];
            if (array_key_exists('perusahaan_nama', $data)) {
                $nama                = IsianBantu::rapikan((string) $data['perusahaan_nama']);
                $set['perusahaan_nama'] = $nama !== '' ? mb_substr($nama, 0, 150) : null;
            }
            if (! $this->db->table('surat_sekolah')->where('id', $id)->update($set)) {
                throw new \RuntimeException('perubahan tidak tersimpan');
            }
            if ($siswa !== null) {
                $this->db->table('surat_sekolah_siswa')->where('surat_id', $id)->delete();
                foreach ($siswa as $i => $x) {
                    if (! $this->db->table('surat_sekolah_siswa')->insert(['surat_id' => $id, 'siswa_id' => $x['siswa_id'], 'kelas_id' => $x['kelas_id'], 'hp' => $x['hp'], 'urut' => $i + 1, 'created_at' => date('Y-m-d H:i:s')])) {
                        throw new \RuntimeException('siswa #' . $x['siswa_id'] . ' tidak tersimpan');
                    }
                }
            }
            $this->catat($id, 'ubah', $konteks, 'Data surat diubah');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Surat] ubah gagal: ' . $e->getMessage());

            return $this->gagal('galat', 'Perubahan gagal disimpan. Coba lagi sebentar.');
        }

        (new AuditModel())->record('update', 'surat_sekolah', $id, SuratSekolahModel::kode($id) . ' diubah: ' . $judul . ' — oleh ' . mb_substr((string) ($konteks['oleh'] ?? 'tak dikenal'), 0, 60));

        return ['ok' => true];
    }

    // =================================================================
    // Membaca
    // =================================================================

    /**
     * Surat lengkap: baris, isian JSON yang sudah didekode ('isi_arr'), siswa, riwayat.
     *
     * @return array{surat: array<string, mixed>, siswa: list<array<string, mixed>>, riwayat: list<array<string, mixed>>}|null
     */
    public function muat(int $id): ?array
    {
        $s = $this->db->table('surat_sekolah')->where('id', $id)->get()->getRowArray();
        if ($s === null) {
            return null;
        }
        $s['isi_arr'] = self::dekodeIsi((string) ($s['isi'] ?? ''));

        return ['surat' => $s, 'siswa' => $this->siswa($id), 'riwayat' => $this->riwayat($id)];
    }

    /** @return array<string, mixed> */
    public static function dekodeIsi(string $json): array
    {
        $d = $json !== '' ? json_decode($json, true) : null;

        return is_array($d) ? $d : [];
    }

    /**
     * Siswa pada surat, berurutan seperti di surat. Nama/NIS/jurusan dibaca LANGSUNG dari data induk (bila berubah,
     * sidik berubah → "perlu cetak ulang"); kelas = kelas saat surat dibuat.
     *
     * @return list<array<string, mixed>>
     */
    public function siswa(int $suratId): array
    {
        return $this->db->table('surat_sekolah_siswa ss')
            ->select('ss.id, ss.siswa_id, ss.kelas_id, ss.hp, ss.urut, s.nama, s.nis, s.nisn, s.no_hp AS hp_master, k.nama_kelas, j.nama AS jurusan_nama')
            ->join('siswa s', 's.id = ss.siswa_id')
            ->join('kelas k', 'k.id = ss.kelas_id', 'left')
            ->join('jurusan j', 'j.id = k.jurusan_id', 'left')
            ->where('ss.surat_id', $suratId)
            ->orderBy('ss.urut', 'ASC')->orderBy('ss.id', 'ASC')
            ->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> terbaru di atas */
    public function riwayat(int $suratId): array
    {
        return $this->db->table('surat_sekolah_riwayat')->where('surat_id', $suratId)
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Daftar surat untuk halaman Daftar Surat.
     *
     * $f: status, jenis, tahun (tahun tanggal surat), q (nomor / judul / perusahaan). Nilai asing diabaikan.
     * Urutan: tab Menunggu → yang paling lama menunggu di atas; selainnya yang terbaru di atas.
     *
     * @param array<string, mixed> $f
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, jml_hal: int}
     */
    public function daftar(array $f, int $halaman = 1, int $per = self::PER_HALAMAN): array
    {
        $per   = max(1, $per);
        $total = (int) $this->terapkan($this->db->table('surat_sekolah s'), $f)->countAllResults();
        $jml   = max(1, (int) ceil($total / $per));
        $hal   = min(max(1, $halaman), $jml);

        $b = $this->terapkan($this->db->table('surat_sekolah s'), $f)
            ->select('s.id, s.jenis, s.status, s.perlu_acc, s.judul, s.tanggal_surat, s.nomor, s.perusahaan_nama, s.cetak_ke, s.catatan_staf')
            ->select('s.acc_nama, s.acc_peran, s.acc_at, s.acc_kode, s.dibuat_nama, s.created_at, s.updated_at, s.isi, s.sidik')
            ->select('(SELECT COUNT(*) FROM surat_sekolah_siswa x WHERE x.surat_id = s.id) AS jml_siswa', false);
        if ((string) ($f['status'] ?? '') === 'menunggu') {
            $b->orderBy('s.created_at', 'ASC')->orderBy('s.id', 'ASC');
        } else {
            $b->orderBy('s.created_at', 'DESC')->orderBy('s.id', 'DESC');
        }

        return [
            'rows'    => $b->limit($per, ($hal - 1) * $per)->get()->getResultArray(),
            'total'   => $total,
            'page'    => $hal,
            'jml_hal' => $jml,
        ];
    }

    /** @return array<string, int> semua + jumlah per status (untuk lencana tab) */
    public function hitungStatus(): array
    {
        $out = ['semua' => 0] + array_fill_keys(SuratSekolahModel::STATUS, 0);
        foreach ($this->db->query('SELECT status, COUNT(*) n FROM surat_sekolah GROUP BY status')->getResultArray() as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
            $out['semua'] += (int) $r['n'];
        }

        return $out;
    }

    /** @return list<int> tahun-tahun tanggal surat yang ada, terbaru dulu */
    public function tahunTersedia(): array
    {
        return array_map('intval', array_column(
            $this->db->query('SELECT DISTINCT YEAR(tanggal_surat) y FROM surat_sekolah ORDER BY y DESC')->getResultArray(),
            'y'
        ));
    }

    // =================================================================
    // Nomor
    // =================================================================

    /**
     * Pastikan surat bernomor punya nomor; bila belum, tetapkan nomor berikutnya (urutan bersama dengan Surat Izin PKL).
     * Hanya surat berstatus 'disetujui' yang bisa diberi nomor. Jenis tanpa nomor: tidak melakukan apa pun (ok).
     *
     * @return array{ok: bool, kode?: string, surat?: array<string, mixed>, baru?: bool}
     *         kode galat: tidak_ada | status | galat
     */
    public function terbitkan(int $id, array $konteks): array
    {
        $this->db->transBegin();
        try {
            // Mutex penomoran: semua penerbit nomor (Surat Izin PKL & surat sekolah) antre di baris pengaturan.
            $this->db->query('SELECT id FROM pkl_pengaturan WHERE id = 1 FOR UPDATE');

            $s = $this->db->table('surat_sekolah')->where('id', $id)->get()->getRowArray();
            if ($s === null) {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'tidak_ada'];
            }
            if (! SuratJenis::bernomor((string) $s['jenis']) || (string) ($s['nomor'] ?? '') !== '') {
                $this->db->transCommit();

                return ['ok' => true, 'surat' => $s, 'baru' => false];
            }
            if ($s['status'] !== 'disetujui') {
                $this->db->transRollback();

                return ['ok' => false, 'kode' => 'status'];
            }

            $p     = (new PklPengaturanModel())->ambil();
            $tgl   = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $s['tanggal_surat']);
            if ($tgl === false) {
                throw new \RuntimeException('tanggal surat rusak');
            }
            $tahun = (int) $tgl->format('Y');
            $urut  = SuratNomor::urutBerikutnya($this->db, $tahun, $p);
            $nomor = PklNomorSurat::format(SuratNomor::pola($p), $urut, $tgl);

            $ok = $this->db->table('surat_sekolah')->where('id', $id)->update(['tahun' => $tahun, 'urut' => $urut, 'nomor' => $nomor, 'updated_at' => date('Y-m-d H:i:s')]);
            if (! $ok) {
                throw new \RuntimeException('nomor tidak tersimpan');
            }
            $this->catat($id, 'nomor', $konteks, 'Nomor ' . $nomor);
            $this->db->transCommit();

            return ['ok' => true, 'surat' => array_merge($s, ['tahun' => $tahun, 'urut' => $urut, 'nomor' => $nomor]), 'baru' => true];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Surat] terbitkan nomor gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'galat'];
        }
    }

    // =================================================================
    // Sidik & riwayat
    // =================================================================

    /**
     * Sidik data yang tampil di surat — berubah bila isi, tujuan, daftar/urutan siswa (termasuk HP & kelas), penanda tangan
     * (Kepala Sekolah, Waka Hubin, kontak NB, gambar tanda tangan), atau catatan ACC berubah.
     *
     * @param array<string, mixed>       $surat baris surat_sekolah
     * @param list<array<string, mixed>> $siswa hasil siswa()
     * @param array<string, mixed>       $p     baris pkl_pengaturan
     */
    public static function sidik(array $surat, array $siswa, array $p): string
    {
        $ttd   = PklSurat::infoTtd($p);
        $dasar = [
            (string) $surat['jenis'], (string) $surat['tanggal_surat'], (string) ($surat['perusahaan_nama'] ?? ''), (string) ($surat['isi'] ?? ''),
            (string) ($p['kepsek_nama'] ?? ''), (string) ($p['waka_hubin_nama'] ?? ''), (string) ($p['waka_hubin_nip'] ?? ''), (string) ($p['waka_hubin_jabatan'] ?? ''),
            (string) ($p['kontak_surat_nama'] ?? ''), (string) ($p['kontak_surat_hp'] ?? ''),
            $ttd !== null ? basename($ttd['path']) . '@' . (int) @filemtime($ttd['path']) : '-',
            (string) ($surat['acc_at'] ?? ''), (string) ($surat['acc_peran'] ?? ''), (string) ($surat['acc_nama'] ?? ''),
        ];
        $daftar = array_map(static fn (array $a) => [
            (int) $a['siswa_id'], (string) $a['nama'], (string) ($a['nis'] ?? ''), (string) ($a['nisn'] ?? ''), (string) ($a['nama_kelas'] ?? ''), (string) ($a['jurusan_nama'] ?? ''),
            trim((string) ($a['hp'] ?? '')) !== '' ? (string) $a['hp'] : (string) ($a['hp_master'] ?? ''),
        ], $siswa);

        return sha1(json_encode([$dasar, $daftar], JSON_UNESCAPED_UNICODE));
    }

    /**
     * "Perlu cetak ulang": surat pernah diunduh, dan data yang tampil di surat berubah sejak itu (nomor tetap sama).
     *
     * @param array<string, mixed>       $surat
     * @param list<array<string, mixed>> $siswa
     * @param array<string, mixed>       $p
     */
    public static function perluCetakUlang(array $surat, array $siswa, array $p): bool
    {
        return (int) ($surat['cetak_ke'] ?? 0) > 0 && (string) ($surat['sidik'] ?? '') !== '' && self::sidik($surat, $siswa, $p) !== (string) $surat['sidik'];
    }

    /**
     * Status "perlu cetak ulang" banyak surat sekaligus (untuk daftar): id => true. Hanya surat yang pernah diunduh dan
     * berstatus 'disetujui' dicek; siswa dimuat dengan satu kueri.
     *
     * @param list<array<string, mixed>> $rows baris daftar() (wajib memuat jenis, tanggal_surat, perusahaan_nama, isi, acc_*, cetak_ke, sidik)
     * @param array<string, mixed>       $p
     *
     * @return array<int, bool>
     */
    public function perluUlangBanyak(array $rows, array $p): array
    {
        $cek = array_values(array_filter($rows, static fn (array $r) => (int) $r['cetak_ke'] > 0 && $r['status'] === 'disetujui'));
        if ($cek === []) {
            return [];
        }
        $ids   = array_map(static fn (array $r) => (int) $r['id'], $cek);
        $siswa = [];
        foreach ($this->db->table('surat_sekolah_siswa ss')
            ->select('ss.surat_id, ss.siswa_id, ss.hp, ss.urut, s.nama, s.nis, s.nisn, s.no_hp AS hp_master, k.nama_kelas, j.nama AS jurusan_nama')
            ->join('siswa s', 's.id = ss.siswa_id')->join('kelas k', 'k.id = ss.kelas_id', 'left')->join('jurusan j', 'j.id = k.jurusan_id', 'left')
            ->whereIn('ss.surat_id', $ids)->orderBy('ss.urut', 'ASC')->orderBy('ss.id', 'ASC')->get()->getResultArray() as $r) {
            $siswa[(int) $r['surat_id']][] = $r;
        }
        $out = [];
        foreach ($cek as $r) {
            $out[(int) $r['id']] = self::perluCetakUlang($r, $siswa[(int) $r['id']] ?? [], $p);
        }

        return $out;
    }

    /**
     * Pilih surat untuk unduhan: mode terpilih ($ids) | belum (belum pernah diunduh / perlu cetak ulang) | semua — dua mode
     * terakhir untuk SATU jenis ($jenis) dan hanya yang berstatus 'disetujui'. Urutan = urutan persetujuan.
     *
     * @param list<int>            $ids
     * @param array<string, mixed> $p
     *
     * @return array{ok: bool, ids: list<int>, pesan: string, kosong?: bool}
     */
    public function pilihUntukUnduh(string $mode, array $ids, string $jenis, int $maks, array $p): array
    {
        if ($mode === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if ($ids === []) {
                return ['ok' => false, 'ids' => [], 'pesan' => 'Pilih dulu surat yang mau diunduh.'];
            }
        } else {
            if (! SuratJenis::ada($jenis)) {
                return ['ok' => false, 'ids' => [], 'pesan' => 'Pilih dulu jenis suratnya (saring Daftar Surat menurut jenis), lalu unduh.'];
            }
            $semua = $this->db->table('surat_sekolah s')
                ->select('s.id, s.jenis, s.status, s.tanggal_surat, s.perusahaan_nama, s.isi, s.acc_nama, s.acc_peran, s.acc_at, s.cetak_ke, s.sidik')
                ->where('s.status', 'disetujui')->where('s.jenis', $jenis)
                ->orderBy('COALESCE(s.acc_at, s.created_at)', 'ASC', false)->orderBy('s.id', 'ASC')
                ->limit($maks + 1)->get()->getResultArray();
            if ($semua === []) {
                return ['ok' => false, 'ids' => [], 'pesan' => 'Belum ada ' . SuratJenis::label($jenis) . ' yang siap diunduh.'];
            }
            if ($mode === 'semua') {
                $ids = array_map(static fn (array $r) => (int) $r['id'], $semua);
            } else {
                $ulang = $this->perluUlangBanyak($semua, $p);
                $ids   = array_values(array_map(
                    static fn (array $r) => (int) $r['id'],
                    array_filter($semua, static fn (array $r) => (int) $r['cetak_ke'] === 0 || ! empty($ulang[(int) $r['id']]))
                ));
                if ($ids === []) {
                    return ['ok' => true, 'ids' => [], 'kosong' => true, 'pesan' => 'Semua ' . SuratJenis::label($jenis) . ' sudah diunduh dan datanya tidak berubah. Tidak ada yang perlu diunduh. Mau mencetak ulang semuanya? Pakai "Unduh SEMUA".'];
                }
            }
        }
        if (count($ids) > $maks) {
            return ['ok' => false, 'ids' => [], 'pesan' => 'Terlalu banyak sekaligus (maksimal ' . $maks . ' surat). Pilih sebagian.'];
        }

        return ['ok' => true, 'ids' => $ids, 'pesan' => ''];
    }

    /** Catat satu kejadian di surat_sekolah_riwayat. */
    public function catat(int $suratId, string $aksi, array $konteks, ?string $catatan = null): bool
    {
        return (bool) $this->db->table('surat_sekolah_riwayat')->insert([
            'surat_id'   => $suratId,
            'aksi'       => mb_substr($aksi, 0, 30),
            'oleh'       => mb_substr((string) ($konteks['oleh'] ?? 'Sistem'), 0, 150),
            'admin_id'   => ($konteks['admin_id'] ?? null) ?: null,
            'peran'      => isset($konteks['peran']) && $konteks['peran'] !== '' ? mb_substr((string) $konteks['peran'], 0, 20) : null,
            'catatan'    => $catatan !== null ? mb_substr($catatan, 0, 255) : null,
            'ip_address' => $konteks['ip'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Y-m-d yang sah dan tahunnya masuk akal. */
    public static function tanggalSah(string $s): bool
    {
        $t = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);

        return $t !== false && $t->format('Y-m-d') === $s && (int) $t->format('Y') >= 2000 && (int) $t->format('Y') <= 2100;
    }

    /**
     * @param array<string, mixed> $f
     * @param \CodeIgniter\Database\BaseBuilder $b
     *
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function terapkan($b, array $f)
    {
        $status = (string) ($f['status'] ?? '');
        if (in_array($status, SuratSekolahModel::STATUS, true)) {
            $b->where('s.status', $status);
        }
        $jenis = (string) ($f['jenis'] ?? '');
        if (SuratJenis::ada($jenis)) {
            $b->where('s.jenis', $jenis);
        }
        $tahun = (int) ($f['tahun'] ?? 0);
        if ($tahun >= 2000 && $tahun <= 2100) {
            $b->where('s.tanggal_surat >=', $tahun . '-01-01')->where('s.tanggal_surat <=', $tahun . '-12-31');
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()->like('s.nomor', $q)->orLike('s.judul', $q)->orLike('s.perusahaan_nama', $q)->groupEnd();
        }

        return $b;
    }

    /**
     * @param list<mixed> $in
     *
     * @return list<array{siswa_id: int, kelas_id: ?int, hp: ?string}>
     */
    private function rapikanSiswa(array $in): array
    {
        $out  = [];
        $lihat = [];
        foreach ($in as $s) {
            $sid = (int) (is_array($s) ? ($s['siswa_id'] ?? 0) : $s);
            if ($sid < 1 || isset($lihat[$sid])) {
                continue;
            }
            $lihat[$sid] = true;
            $hp          = is_array($s) ? trim((string) ($s['hp'] ?? '')) : '';
            $out[]       = [
                'siswa_id' => $sid,
                'kelas_id' => is_array($s) ? (((int) ($s['kelas_id'] ?? 0)) ?: null) : null,
                'hp'       => $hp !== '' ? mb_substr($hp, 0, 25) : null,
            ];
        }

        return $out;
    }

    /**
     * Siswa yang kelasnya tidak disebut diisi kelas siswa SAAT INI (kelas berubah tiap kenaikan, jadi disalin ke surat):
     * kolom kelas di surat tidak boleh tercetak kosong hanya karena pemanggil lupa menyebutkannya.
     *
     * @param list<array{siswa_id: int, kelas_id: ?int, hp: ?string}> $siswa
     *
     * @return list<array{siswa_id: int, kelas_id: ?int, hp: ?string}>
     */
    private function lengkapiKelas(array $siswa): array
    {
        $tanpa = [];
        foreach ($siswa as $s) {
            if ($s['kelas_id'] === null) {
                $tanpa[] = $s['siswa_id'];
            }
        }
        if ($tanpa === []) {
            return $siswa;
        }
        $sekarang = [];
        foreach ($this->db->table('siswa')->select('id, kelas_id')->whereIn('id', $tanpa)->get()->getResultArray() as $r) {
            $sekarang[(int) $r['id']] = ((int) $r['kelas_id']) ?: null;
        }
        foreach ($siswa as $i => $s) {
            if ($s['kelas_id'] === null) {
                $siswa[$i]['kelas_id'] = $sekarang[$s['siswa_id']] ?? null;
            }
        }

        return $siswa;
    }

    /** @return array{ok: false, kode: string, pesan: string} */
    private function gagal(string $kode, string $pesan): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan];
    }
}
