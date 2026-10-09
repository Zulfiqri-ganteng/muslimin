<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use CodeIgniter\Database\BaseConnection;

/**
 * SKBM — Surat Keputusan Pembagian Tugas Mengajar (Lampiran 3) per TAHUN AJARAN: siapa mengajar mapel apa di kelas mana
 * (dan berapa JP per minggu). Salinan SK resmi yang dipegang sistem; menjadi SUMBER ceklis Koreksi honor
 * (HonorKoreksi::isiDariSkbm) dan bisa dibandingkan dengan data Penugasan/pengampu (bandingkanDenganPengampu).
 *
 * Data: skbm_mapel (guru × mapel, urut = urutan di SK) + skbm_sel (kelas yang diajar, JP; NULL = belum diisi).
 * Sengaja terpisah dari tabel pengampu (dipakai Jadwal KBM, Rekap Beban, Cetak, API). Semua perubahan dicatat di Audit Log.
 * Rancangan: docs/DESAIN-SKBM.md.
 */
final class Skbm
{
    public const MAKS_BARIS    = 900;
    public const MAKS_PER_GURU = 40;
    public const MAKS_JP       = 40;
    public const MAKS_NAMA     = 150;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Tahun ajaran
    // =================================================================

    /** "2026/2027" → sah bila tahun kedua = tahun pertama + 1. */
    public static function tahunValid(string $t): bool
    {
        return preg_match('/^(\d{4})\/(\d{4})$/', $t, $m) === 1 && (int) $m[2] === (int) $m[1] + 1 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100;
    }

    /** Tahun ajaran bawaan: yang terbaru di periode ujian; bila belum ada, dihitung dari tanggal (Juli–Juni). */
    public function tahunBawaan(): string
    {
        $r = $this->db->tableExists('ujian_periode') ? $this->db->table('ujian_periode')->select('tahun_ajaran')->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray() : null;
        if ($r !== null && self::tahunValid((string) $r['tahun_ajaran'])) {
            return (string) $r['tahun_ajaran'];
        }
        $y = (int) date('Y');
        $a = (int) date('n') >= 7 ? $y : $y - 1;

        return $a . '/' . ($a + 1);
    }

    /** Tahun ajaran yang punya SKBM atau periode ujian, terbaru dulu. @return list<string> */
    public function tahunTersedia(): array
    {
        $t = [];
        foreach ($this->db->table('skbm_mapel')->select('tahun_ajaran')->distinct()->get()->getResultArray() as $r) {
            $t[$r['tahun_ajaran']] = true;
        }
        if ($this->db->tableExists('ujian_periode')) {
            foreach ($this->db->table('ujian_periode')->select('tahun_ajaran')->distinct()->where('deleted_at', null)->get()->getResultArray() as $r) {
                if (self::tahunValid((string) $r['tahun_ajaran'])) {
                    $t[$r['tahun_ajaran']] = true;
                }
            }
        }
        $t[$this->tahunBawaan()] = true;
        $k = array_keys($t);
        rsort($k);

        return $k;
    }

    /**
     * Pilihan tahun ajaran untuk layar (web & aplikasi): yang punya SKBM atau periode ujian, tahun berikutnya (supaya SKBM
     * tahun depan bisa mulai diisi), dan $sertakan bila dikirim — terbaru dulu, beserta jumlah isinya.
     *
     * @return list<array{tahun:string, guru:int, mapel:int}>
     */
    public function daftarTahun(?string $sertakan = null): array
    {
        $opsi  = $this->tahunTersedia();
        $depan = (int) substr($opsi[0], 0, 4) + 1;
        foreach ([$depan . '/' . ($depan + 1), $sertakan] as $t) {
            if ($t !== null && self::tahunValid($t) && ! in_array($t, $opsi, true)) {
                $opsi[] = $t;
            }
        }
        rsort($opsi);
        $berisi = [];
        foreach ($this->db->query('SELECT tahun_ajaran, COUNT(DISTINCT guru_id) AS guru, COUNT(*) AS mapel FROM skbm_mapel GROUP BY tahun_ajaran')->getResultArray() as $r) {
            $berisi[$r['tahun_ajaran']] = ['guru' => (int) $r['guru'], 'mapel' => (int) $r['mapel']];
        }

        return array_map(static fn (string $t): array => ['tahun' => $t] + ($berisi[$t] ?? ['guru' => 0, 'mapel' => 0]), $opsi);
    }

    public function ada(string $tahun): bool
    {
        return (int) $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->countAllResults() > 0;
    }

    /** Nomor SK tahun ajaran itu ("" bila belum diisi atau migrasi belum dijalankan). */
    public function nomorSk(string $tahun): string
    {
        if (! $this->db->tableExists('skbm_sk')) {
            return '';
        }
        $r = $this->db->table('skbm_sk')->select('nomor')->where('tahun_ajaran', $tahun)->get()->getRowArray();

        return $r === null ? '' : (string) $r['nomor'];
    }

    /** Simpan nomor SK (mis. "123/SMK-BN/SKBM/VII/2026"); kosong = hapus. Dicetak di baris "Nomor :" pada Excel SKBM. */
    public function simpanNomorSk(string $tahun, string $nomor): array
    {
        if (! self::tahunValid($tahun)) {
            return $this->gagal('Tahun ajaran tidak sah (contoh: 2026/2027).');
        }
        if (! $this->db->tableExists('skbm_sk')) {
            return $this->gagal('Fitur nomor SK belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).');
        }
        $nomor = trim((string) preg_replace('/\s+/u', ' ', $nomor));
        if (mb_strlen($nomor) > 120 || preg_match('/^[\p{L}\p{N} \/.\-_,:()&]*$/u', $nomor) !== 1) {
            return $this->gagal('Nomor SK maksimal 120 huruf dan hanya boleh berisi huruf, angka, spasi, dan tanda / . - _ , : ( ).');
        }
        $lama = $this->nomorSk($tahun);
        if ($lama === $nomor) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.', 'nomor' => $nomor];
        }
        if ($nomor === '') {
            $this->db->table('skbm_sk')->where('tahun_ajaran', $tahun)->delete();
        } else {
            $now = date('Y-m-d H:i:s');
            $ada = (int) $this->db->table('skbm_sk')->where('tahun_ajaran', $tahun)->countAllResults() > 0;
            if ($ada) {
                $this->db->table('skbm_sk')->where('tahun_ajaran', $tahun)->update(['nomor' => $nomor, 'updated_at' => $now]);
            } else {
                $this->db->table('skbm_sk')->insert(['tahun_ajaran' => $tahun, 'nomor' => $nomor, 'updated_at' => $now]);
            }
        }
        $this->audit('update', 'skbm_sk', null, 'SKBM ' . $tahun . ': nomor SK "' . $lama . '" → "' . $nomor . '"');

        return ['ok' => true, 'pesan' => $nomor === '' ? 'Nomor SK dihapus.' : 'Nomor SK disimpan.', 'nomor' => $nomor];
    }

    // =================================================================
    // Baca
    // =================================================================

    /**
     * SKBM lengkap satu tahun ajaran: kolom kelas, guru → baris mapel → sel (JP), dan semua total.
     *
     * @return array{tahun:string, kelas:list<array<string,mixed>>, grup:list<array<string,mixed>>, guru:list<array<string,mixed>>,
     *               total_jp:int, jumlah_guru:int, jumlah_mapel:int, jumlah_sel:int, tanpa_jp:int}
     */
    public function muat(string $tahun): array
    {
        $kelas = (new HonorKoreksi($this->db))->daftarKelas();
        $idx   = [];
        foreach ($kelas as $i => &$k) {
            $k['jml'] = 0;
            $k['jp']  = 0;
            $idx[$k['id']] = $i;
        }
        unset($k);
        $grup = [];
        foreach ($kelas as $i => $k) {
            $n = count($grup);
            if ($n > 0 && $grup[$n - 1]['judul'] === $k['grup']) {
                $grup[$n - 1]['sampai'] = $i;
            } else {
                $grup[] = ['judul' => $k['grup'], 'dari' => $i, 'sampai' => $i];
            }
        }

        $rows = $this->db->table('skbm_mapel m')->select('m.id, m.guru_id, m.mapel_nama, m.mapel_id, m.kode, m.urut, g.nama AS guru_nama')
            ->join('guru g', 'g.id = m.guru_id AND g.deleted_at IS NULL')
            ->where('m.tahun_ajaran', $tahun)->orderBy('m.urut', 'ASC')->orderBy('m.id', 'ASC')->get()->getResultArray();
        $selPer = [];
        if ($rows !== []) {
            foreach ($this->db->table('skbm_sel')->whereIn('skbm_mapel_id', array_map('intval', array_column($rows, 'id')))->get()->getResultArray() as $s) {
                $selPer[(int) $s['skbm_mapel_id']][(int) $s['kelas_id']] = $s['jp'] === null ? null : (int) $s['jp'];
            }
        }

        $perGuru = [];
        foreach ($rows as $r) {
            $perGuru[(int) $r['guru_id']][] = $r;
        }
        $guru = [];
        $totalJp = $nMapel = $nSel = $nTanpaJp = 0;
        $no = 0;
        foreach ($perGuru as $gid => $list) {
            // Nomor urut = angka di depan kode guru yang tersimpan dari SK sekolah (mis. "49A" → 49; nomor di SK bisa melompat),
            // bila tidak ada (baris dibuat manual) → lanjut dari nomor sebelumnya.
            $dariSk = null;
            foreach ($list as $r) {
                if (preg_match('/^\s*(\d{1,4})/', (string) ($r['kode'] ?? ''), $mm) === 1) {
                    $dariSk = (int) $mm[1];
                    break;
                }
            }
            $no = $dariSk ?? $no + 1;
            $jml    = count($list);
            $mapel  = [];
            $tg = $kg = 0;
            foreach ($list as $j => $r) {
                $sel = [];
                $tm  = 0;
                foreach ($selPer[(int) $r['id']] ?? [] as $kid => $jp) {
                    if (! isset($idx[$kid])) {
                        continue; // kelasnya sudah dihapus
                    }
                    $sel[$kid] = $jp;
                    $kelas[$idx[$kid]]['jml']++;
                    $kelas[$idx[$kid]]['jp'] += (int) $jp;
                    $tm += (int) $jp;
                    $nSel++;
                    $nTanpaJp += $jp === null ? 1 : 0;
                }
                $mapel[] = ['id' => (int) $r['id'], 'nama' => (string) $r['mapel_nama'], 'mapel_id' => $r['mapel_id'] === null ? null : (int) $r['mapel_id'],
                    'kode' => trim((string) ($r['kode'] ?? '')) !== '' ? trim((string) $r['kode']) : HonorKoreksi::kodeGuru($no, $j, $jml), 'sel' => $sel, 'total_jp' => $tm, 'jml' => count($sel)];
                $tg += $tm;
                $kg += count($sel);
                $nMapel++;
            }
            $guru[] = ['guru_id' => $gid, 'no' => $no, 'nama' => (string) $list[0]['guru_nama'], 'mapel' => $mapel, 'total_jp' => $tg, 'jml' => $kg];
            $totalJp += $tg;
        }

        return ['tahun' => $tahun, 'kelas' => $kelas, 'grup' => $grup, 'guru' => $guru, 'total_jp' => $totalJp, 'jumlah_guru' => count($guru), 'jumlah_mapel' => $nMapel, 'jumlah_sel' => $nSel, 'tanpa_jp' => $nTanpaJp];
    }

    /**
     * Ringkasan angka untuk layar setelah perubahan (server = sumber angka resmi).
     *
     * @return array<string,mixed>
     */
    public function ringkas(string $tahun): array
    {
        $m = $this->muat($tahun);
        $perMapel = $perGuru = $perGuruJml = [];
        foreach ($m['guru'] as $g) {
            $perGuru[$g['guru_id']]    = $g['total_jp'];
            $perGuruJml[$g['guru_id']] = $g['jml'];
            foreach ($g['mapel'] as $x) {
                $perMapel[$x['id']] = $x['total_jp'];
            }
        }
        $perKelas = [];
        foreach ($m['kelas'] as $k) {
            $perKelas[$k['id']] = ['jml' => $k['jml'], 'jp' => $k['jp']];
        }

        return ['total' => $m['total_jp'], 'per_mapel' => $perMapel, 'per_baris' => $perGuru, 'per_guru_jml' => $perGuruJml, 'per_kelas' => $perKelas,
            'jumlah_guru' => $m['jumlah_guru'], 'jumlah_mapel' => $m['jumlah_mapel'], 'jumlah_sel' => $m['jumlah_sel'], 'tanpa_jp' => $m['tanpa_jp']];
    }

    /**
     * Bahan ceklis Koreksi: per ORANG (id guru induk) → baris mapel (satu per nama mapel, kelas digabung).
     *
     * @return array<int, list<array{nama:string, mapel_id:?int, kelas:list<int>}>>
     */
    public function barisUntukHonor(string $tahun): array
    {
        $peta = GuruModel::petaOrang();
        $out  = [];
        $m    = $this->muat($tahun);
        foreach ($m['guru'] as $g) {
            $o = $peta[$g['guru_id']] ?? $g['guru_id'];
            foreach ($g['mapel'] as $x) {
                $kunci = mb_strtolower($x['nama']);
                $idxAda = null;
                foreach ($out[$o] ?? [] as $i => $b) {
                    if (mb_strtolower($b['nama']) === $kunci) {
                        $idxAda = $i;
                        break;
                    }
                }
                if ($idxAda === null) {
                    $out[$o][] = ['nama' => $x['nama'], 'mapel_id' => $x['mapel_id'], 'kelas' => array_keys($x['sel'])];
                } else {
                    $out[$o][$idxAda]['kelas'] = array_values(array_unique(array_merge($out[$o][$idxAda]['kelas'], array_keys($x['sel']))));
                }
            }
        }

        return $out;
    }

    // =================================================================
    // Tulis — mengembalikan ['ok' => bool, 'pesan' => string, …]
    // =================================================================

    public function tambahMapel(string $tahun, int $guruId, string $nama): array
    {
        if (! self::tahunValid($tahun)) {
            return $this->gagal('Tahun ajaran tidak sah (contoh: 2026/2027).');
        }
        $nama = HonorPengaturan::rapikan($nama);
        if ($nama === '' || mb_strlen($nama) > self::MAKS_NAMA) {
            return $this->gagal('Nama mapel wajib diisi (maksimal ' . self::MAKS_NAMA . ' huruf). Tulis "-" bila guru ini tidak mengampu mapel.');
        }
        $g = $this->db->table('guru')->select('id, nama')->where('id', $guruId)->where('deleted_at', null)->where('induk_id', null)->get()->getRowArray();
        if ($g === null) {
            return $this->gagal('Guru tidak ditemukan di Master Guru.');
        }
        if ((int) $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->where('guru_id', $guruId)->countAllResults() >= self::MAKS_PER_GURU) {
            return $this->gagal('Satu guru maksimal ' . self::MAKS_PER_GURU . ' baris mapel.');
        }
        if ((int) $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->countAllResults() >= self::MAKS_BARIS) {
            return $this->gagal('SKBM tahun ini sudah mencapai batas ' . self::MAKS_BARIS . ' baris.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('skbm_mapel')->insert([
            'tahun_ajaran' => $tahun, 'guru_id' => $guruId, 'mapel_nama' => $nama, 'mapel_id' => $this->idMapel($nama), 'kode' => null,
            'urut' => $this->urutBerikut($tahun), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        $this->audit('create', 'skbm_mapel', $id, 'SKBM ' . $tahun . ': tambah "' . $nama . '" untuk ' . $g['nama']);

        return ['ok' => true, 'pesan' => '"' . $nama . '" ditambahkan untuk ' . $g['nama'] . '.', 'id' => $id];
    }

    public function ubahMapel(int $id, string $nama): array
    {
        $nama = HonorPengaturan::rapikan($nama);
        if ($nama === '' || mb_strlen($nama) > self::MAKS_NAMA) {
            return $this->gagal('Nama mapel wajib diisi (maksimal ' . self::MAKS_NAMA . ' huruf).');
        }
        $m = $this->db->table('skbm_mapel')->where('id', $id)->get()->getRowArray();
        if ($m === null) {
            return $this->gagal('Baris mapel tidak ditemukan.');
        }
        if ($m['mapel_nama'] === $nama) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.', 'nama' => $nama];
        }
        $this->db->table('skbm_mapel')->where('id', $id)->update(['mapel_nama' => $nama, 'mapel_id' => $this->idMapel($nama), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->audit('update', 'skbm_mapel', $id, 'SKBM ' . $m['tahun_ajaran'] . ': mapel "' . $m['mapel_nama'] . '" → "' . $nama . '"');

        return ['ok' => true, 'pesan' => 'Tersimpan.', 'nama' => $nama];
    }

    public function hapusMapel(int $id): array
    {
        $m = $this->db->table('skbm_mapel')->where('id', $id)->get()->getRowArray();
        if ($m === null) {
            return $this->gagal('Baris mapel tidak ditemukan.');
        }
        $this->db->table('skbm_mapel')->where('id', $id)->delete();
        $this->audit('delete', 'skbm_mapel', $id, 'SKBM ' . $m['tahun_ajaran'] . ': hapus mapel "' . $m['mapel_nama'] . '"');

        return ['ok' => true, 'pesan' => 'Baris mapel "' . $m['mapel_nama'] . '" dihapus.', 'tahun' => $m['tahun_ajaran']];
    }

    public function hapusGuru(string $tahun, int $guruId): array
    {
        $g = $this->db->table('guru')->select('nama')->where('id', $guruId)->get()->getRowArray();
        $n = (int) $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->where('guru_id', $guruId)->countAllResults();
        if ($n === 0) {
            return $this->gagal('Guru ini tidak ada di SKBM ' . $tahun . '.');
        }
        $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->where('guru_id', $guruId)->delete();
        $this->audit('delete', 'skbm_mapel', $guruId, 'SKBM ' . $tahun . ': keluarkan ' . ($g['nama'] ?? '#' . $guruId) . " ($n baris mapel)");

        return ['ok' => true, 'pesan' => ($g['nama'] ?? 'Guru') . ' dikeluarkan dari SKBM ' . $tahun . '.'];
    }

    /**
     * Nyalakan / matikan satu kelas pada satu baris mapel. $jp: null = tidak diubah (baru → kosong), "" = kosongkan JP,
     * angka 1–MAKS_JP = isi JP.
     */
    public function setSel(string $tahun, int $mapelRowId, int $kelasId, bool $aktif, mixed $jp = null): array
    {
        $m = $this->db->table('skbm_mapel')->where('id', $mapelRowId)->where('tahun_ajaran', $tahun)->get()->getRowArray();
        $kelasAda = (int) $this->db->table('kelas')->where('id', $kelasId)->where('deleted_at', null)->countAllResults() === 1;
        if ($m === null || ! $kelasAda) {
            return $this->gagal('Baris mapel atau kelas tidak ditemukan. Muat ulang halaman.');
        }
        $ada = $this->db->table('skbm_sel')->where('skbm_mapel_id', $mapelRowId)->where('kelas_id', $kelasId)->get()->getRowArray();
        if (! $aktif) {
            if ($ada !== null) {
                $this->db->table('skbm_sel')->where('id', $ada['id'])->delete();
            }

            return ['ok' => true, 'pesan' => 'Tersimpan.'] + $this->ringkas($tahun);
        }
        $nilai = $ada['jp'] ?? null;
        if ($jp !== null) {
            $t = trim((string) $jp);
            if ($t === '') {
                $nilai = null;
            } elseif (! ctype_digit($t) || (int) $t < 1 || (int) $t > self::MAKS_JP) {
                return $this->gagal('JP harus bilangan bulat 1–' . self::MAKS_JP . ' (kosongkan bila belum diketahui).');
            } else {
                $nilai = (int) $t;
            }
        }
        if ($ada === null) {
            $this->db->table('skbm_sel')->insert(['skbm_mapel_id' => $mapelRowId, 'kelas_id' => $kelasId, 'jp' => $nilai, 'created_at' => date('Y-m-d H:i:s')]);
        } elseif ($jp !== null) {
            $this->db->table('skbm_sel')->where('id', $ada['id'])->update(['jp' => $nilai]);
        }

        return ['ok' => true, 'pesan' => 'Tersimpan.', 'jp' => $nilai === null ? null : (int) $nilai] + $this->ringkas($tahun);
    }

    public function kosongkan(string $tahun): array
    {
        $n = (int) $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->countAllResults();
        $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->delete();
        $this->audit('delete', 'skbm_mapel', null, 'SKBM ' . $tahun . ": dikosongkan ($n baris mapel)");

        return ['ok' => true, 'pesan' => $n > 0 ? "SKBM $tahun dikosongkan ($n baris mapel dibuang)." : "SKBM $tahun sudah kosong."];
    }

    /** Salin seluruh SKBM satu tahun ajaran ke tahun lain (tahun tujuan harus kosong, kecuali $ganti). */
    public function salinTahun(string $dari, string $ke, bool $ganti = false): array
    {
        if (! self::tahunValid($dari) || ! self::tahunValid($ke) || $dari === $ke) {
            return $this->gagal('Tahun sumber dan tujuan harus sah dan berbeda.');
        }
        if (! $this->ada($dari)) {
            return $this->gagal("SKBM $dari belum diisi — tidak ada yang bisa disalin.");
        }
        if (! $ganti && $this->ada($ke)) {
            return $this->gagal("SKBM $ke sudah berisi. Pilih \"ganti\" bila mau menimpa.");
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        if ($ganti) {
            $this->db->table('skbm_mapel')->where('tahun_ajaran', $ke)->delete();
        }
        $nBaris = $nSel = 0;
        foreach ($this->db->table('skbm_mapel')->where('tahun_ajaran', $dari)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            $this->db->table('skbm_mapel')->insert(['tahun_ajaran' => $ke, 'guru_id' => $r['guru_id'], 'mapel_nama' => $r['mapel_nama'], 'mapel_id' => $r['mapel_id'], 'kode' => $r['kode'], 'urut' => $r['urut'], 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
            foreach ($this->db->table('skbm_sel')->where('skbm_mapel_id', $r['id'])->get()->getResultArray() as $s) {
                $this->db->table('skbm_sel')->insert(['skbm_mapel_id' => $id, 'kelas_id' => $s['kelas_id'], 'jp' => $s['jp'], 'created_at' => $now]);
                $nSel++;
            }
            $nBaris++;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyalin. Tidak ada yang berubah.');
        }
        $this->audit('create', 'skbm_mapel', null, "SKBM: salin $dari → $ke ($nBaris baris mapel, $nSel kelas)");

        return ['ok' => true, 'pesan' => "SKBM $dari disalin ke $ke: $nBaris baris mapel, $nSel kelas."];
    }

    // =================================================================
    // Perbandingan dengan Penugasan (pengampu)
    // =================================================================

    /**
     * Bandingkan pasangan guru–kelas di SKBM dengan data Penugasan (pengampu), hanya untuk guru yang ada di SKBM.
     *
     * @return array{sama:int, hanya_skbm:list<array<string,mixed>>, hanya_pengampu:list<array<string,mixed>>, jp_beda:list<array<string,mixed>>,
     *               guru_skbm:int, guru_tanpa_skbm:list<string>, ada:bool}
     */
    public function bandingkanDenganPengampu(string $tahun): array
    {
        $peta = GuruModel::petaOrang();
        $nama = [];
        foreach ($this->db->table('guru')->select('id, nama')->get()->getResultArray() as $g) {
            $nama[(int) $g['id']] = (string) $g['nama'];
        }
        $kelasNama = [];
        foreach ($this->db->table('kelas')->select('id, nama_kelas')->where('deleted_at', null)->get()->getResultArray() as $k) {
            $kelasNama[(int) $k['id']] = (string) $k['nama_kelas'];
        }

        $skbm = [];
        $guruSkbm = [];
        foreach ($this->db->table('skbm_mapel m')->select('m.guru_id, m.mapel_nama, s.kelas_id, s.jp')->join('skbm_sel s', 's.skbm_mapel_id = m.id', 'left')
            ->where('m.tahun_ajaran', $tahun)->get()->getResultArray() as $r) {
            $o = $peta[(int) $r['guru_id']] ?? (int) $r['guru_id'];
            $guruSkbm[$o] = true;
            if ($r['kelas_id'] === null || ! isset($kelasNama[(int) $r['kelas_id']])) {
                continue;
            }
            $kunci = $o . '|' . (int) $r['kelas_id'];
            $skbm[$kunci]['o'] = $o;
            $skbm[$kunci]['k'] = (int) $r['kelas_id'];
            $skbm[$kunci]['mapel'][] = (string) $r['mapel_nama'];
            if ($r['jp'] !== null) {
                $skbm[$kunci]['jp'] = ($skbm[$kunci]['jp'] ?? 0) + (int) $r['jp'];
            }
        }
        $peng = [];
        $guruPengTanpa = [];
        foreach ($this->db->table('pengampu p')->select('p.guru_id, p.kelas_id, p.jp, mp.nama_mapel')->join('mata_pelajaran mp', 'mp.id = p.mapel_id', 'left')
            ->where('p.deleted_at', null)->get()->getResultArray() as $r) {
            $o = $peta[(int) $r['guru_id']] ?? (int) $r['guru_id'];
            if (! isset($kelasNama[(int) $r['kelas_id']])) {
                continue;
            }
            if (! isset($guruSkbm[$o])) {
                $guruPengTanpa[$o] = true;
                continue;
            }
            $kunci = $o . '|' . (int) $r['kelas_id'];
            $peng[$kunci]['o'] = $o;
            $peng[$kunci]['k'] = (int) $r['kelas_id'];
            $peng[$kunci]['mapel'][] = (string) ($r['nama_mapel'] ?? '?');
            $peng[$kunci]['jp'] = ($peng[$kunci]['jp'] ?? 0) + (int) $r['jp'];
        }

        $baris = static fn (array $x, array $nama, array $kelasNama, ?int $jpS, ?int $jpP): array => [
            'guru' => $nama[$x['o']] ?? ('#' . $x['o']), 'kelas' => $kelasNama[$x['k']] ?? '?', 'mapel' => implode(', ', array_unique($x['mapel'])), 'jp_skbm' => $jpS, 'jp_pengampu' => $jpP,
        ];
        $sama = 0;
        $hanyaS = $hanyaP = $jpBeda = [];
        foreach ($skbm as $kunci => $x) {
            if (! isset($peng[$kunci])) {
                $hanyaS[] = $baris($x, $nama, $kelasNama, $x['jp'] ?? null, null);
                continue;
            }
            $jpS = $x['jp'] ?? null;
            $jpP = $peng[$kunci]['jp'] ?? null;
            if ($jpS !== null && $jpP !== null && $jpP > 0 && $jpS !== $jpP) {
                $jpBeda[] = $baris($x, $nama, $kelasNama, $jpS, $jpP) + ['mapel_pengampu' => implode(', ', array_unique($peng[$kunci]['mapel']))];
            } else {
                $sama++;
            }
        }
        foreach ($peng as $kunci => $x) {
            if (! isset($skbm[$kunci])) {
                $hanyaP[] = $baris($x, $nama, $kelasNama, null, $x['jp'] ?? null);
            }
        }
        $urut = static function (array &$l): void {
            usort($l, static fn (array $a, array $b): int => [$a['guru'], $a['kelas']] <=> [$b['guru'], $b['kelas']]);
        };
        $urut($hanyaS);
        $urut($hanyaP);
        $urut($jpBeda);
        $tanpa = array_map(static fn (int $o): string => $nama[$o] ?? ('#' . $o), array_keys($guruPengTanpa));
        sort($tanpa);

        return ['ada' => $guruSkbm !== [], 'sama' => $sama, 'hanya_skbm' => $hanyaS, 'hanya_pengampu' => $hanyaP, 'jp_beda' => $jpBeda, 'guru_skbm' => count($guruSkbm), 'guru_tanpa_skbm' => $tanpa];
    }

    // =================================================================
    // Internal
    // =================================================================

    private function urutBerikut(string $tahun): int
    {
        $r = $this->db->table('skbm_mapel')->selectMax('urut')->where('tahun_ajaran', $tahun)->get()->getRow();

        return min(2000000000, (int) ($r->urut ?? 0) + 1);
    }

    private function idMapel(string $nama): ?int
    {
        $r = $this->db->table('mata_pelajaran')->select('id')->where('nama_mapel', $nama)->where('deleted_at', null)->get()->getRowArray();

        return $r === null ? null : (int) $r['id'];
    }

    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'pesan' => $pesan];
    }

    private function audit(string $aksi, string $tabel, ?int $id, string $deskripsi): void
    {
        (new AuditModel())->record($aksi, $tabel, $id, $deskripsi);
    }
}
