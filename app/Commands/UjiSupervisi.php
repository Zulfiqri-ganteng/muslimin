<?php

namespace App\Commands;

use App\Libraries\HakAkses;
use App\Libraries\HonorCetak;
use App\Libraries\MenuAdmin;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiCetak as Cetak;
use App\Libraries\SupervisiPenilaian as Penilaian;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Uji Supervisi Guru (docs/DESAIN-SUPERVISI.md): tabel & instrumen bawaan, periode, instrumen & butir, jadwal (aturan bentrok,
 * supervisor, instrumen, jadwal yang sudah dinilai), buat massal, hak, dan halaman Admin\SupervisiInstrumen/SupervisiJadwal.
 * Memakai guru nyata di DB lokal; semua data uji (instrumen ZZUJI*, jadwal yang dibuat uji, Audit Log-nya) dihapus di akhir.
 *
 *   php spark dev:uji-supervisi
 *   php spark dev:uji-supervisi --simpan C:/folder   (+ simpan contoh lembar-hasil.pdf, program.xlsx, rekap.xlsx untuk dibuka manual)
 */
class UjiSupervisi extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-supervisi';
    protected $description = 'Uji Supervisi Guru: instrumen, jadwal, buat massal, halaman.';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    /** @var array<string, list<int>> tabel => id yang dibuat uji */
    private array $dibuat = ['supervisi_instrumen' => [], 'supervisi_butir' => [], 'supervisi_jadwal' => []];
    private array $pencatat = ['id' => null, 'nama' => 'ZZUJI Supervisi'];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . mb_substr($detail, 0, 400) : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        $this->db = db_connect();
        if (! $this->db->tableExists('supervisi_jadwal')) {
            CLI::write('Tabel supervisi belum ada. Jalankan "php spark migrate" dulu.', 'red');

            return EXIT_ERROR;
        }
        try {
            $this->ujiDasar();
            $this->ujiInstrumen();
            $this->ujiJadwal();
            $this->ujiMassal();
            $this->ujiHalaman();
            $this->ujiHitung();
            $this->ujiPenilaian();
            $this->ujiRekapCetak();
            $this->ujiApi();
        } catch (\Throwable $e) {
            $this->cek('tanpa galat fatal', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            $this->bersihkan();
        }

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function bersihkan(): void
    {
        $jadwal = array_merge($this->dibuat['supervisi_jadwal'], array_map('intval', array_column($this->db->table('supervisi_jadwal')->select('id')
            ->like('dicatat_oleh', 'ZZUJI', 'after')->get()->getResultArray(), 'id')));
        $ins = array_merge($this->dibuat['supervisi_instrumen'], array_map('intval', array_column($this->db->table('supervisi_instrumen')->select('id')
            ->like('kode', 'ZZUJI', 'after')->get()->getResultArray(), 'id')));
        if ($jadwal !== []) {
            $this->db->table('supervisi_jadwal')->whereIn('id', $jadwal)->delete(); // nilai ikut terhapus (CASCADE)
            $this->db->table('audit_log')->where('tabel', 'supervisi_jadwal')->whereIn('record_id', $jadwal)->delete();
        }
        if ($ins !== []) {
            $butir = array_map('intval', array_column($this->db->table('supervisi_butir')->select('id')->whereIn('instrumen_id', $ins)->get()->getResultArray(), 'id'));
            $this->db->table('supervisi_instrumen')->whereIn('id', $ins)->delete();
            $this->db->table('audit_log')->where('tabel', 'supervisi_instrumen')->whereIn('record_id', $ins)->delete();
            $this->db->table('audit_log')->where('tabel', 'supervisi_butir')->whereIn('record_id', array_merge($butir, $this->dibuat['supervisi_butir']) ?: [0])->delete();
        }
        $this->db->table('audit_log')->where('tabel', 'supervisi_instrumen')->where('record_id', 0)->like('deskripsi', 'Pasang ', 'after')->where('created_at >=', date('Y-m-d H:i:s', time() - 3600))->delete();
        session()->remove(['admin', '_ci_old_input', 'error', 'success']);
    }

    /** @return array{ok: bool, id?: int} */
    private function ingat(string $tabel, array $r): array
    {
        if (! empty($r['ok']) && ! empty($r['id'])) {
            $this->dibuat[$tabel][] = (int) $r['id'];
        }
        foreach ($r['ids'] ?? [] as $id) {
            $this->dibuat[$tabel][] = (int) $id;
        }

        return $r;
    }

    // =================================================================

    private function ujiDasar(): void
    {
        $this->bagian('Tabel, instrumen bawaan, periode, hak');
        $ada = array_filter(['supervisi_instrumen', 'supervisi_butir', 'supervisi_jadwal', 'supervisi_nilai', 'supervisi_nilai_butir'], fn (string $t): bool => $this->db->tableExists($t));
        $this->cek('5 tabel supervisi ada', count($ada) === 5);
        $bawaan = [];
        foreach (Supervisi::INSTRUMEN_BAWAAN as $b) {
            $row = $this->db->table('supervisi_instrumen')->where('kode', $b['kode'])->get()->getRowArray();
            $bawaan[] = $row !== null && $this->db->table('supervisi_butir')->where('instrumen_id', $row['id'])->countAllResults() >= count($b['butir']);
        }
        $this->cek('3 instrumen bawaan + butirnya terpasang (Perencanaan 11, Pelaksanaan 12, Penilaian 6)', ! in_array(false, $bawaan, true));

        $p = static fn (string $t): string => implode(' ', Supervisi::periode($t));
        $this->cek('periode dari tanggal: Jul–Des = ganjil, Jan–Jun = genap tahun ajaran yang sama', $p('2026-10-20') === '2026/2027 ganjil' && $p('2026-07-01') === '2026/2027 ganjil'
            && $p('2027-03-01') === '2026/2027 genap' && $p('2026-06-30') === '2025/2026 genap' && $p('2026-12-31') === '2026/2027 ganjil');
        $s = Supervisi::saringan(['ta' => '2025/2027', 'semester' => 'x', 'status' => 'batal']);
        $this->cek('saringan: tahun ajaran/semester tak sah → periode sekarang; status sah dipakai', $s['ta'] === Supervisi::periodeSekarang()['tahun_ajaran'] && $s['semester'] === Supervisi::periodeSekarang()['semester'] && $s['status'] === 'batal');

        $this->cek('hak: Admin boleh kelola; Operator & Guru Piket tidak (bawaan: hanya Admin, peran lain lewat Hak Akses)', HakAkses::boleh('admin', 'admin/supervisi/jadwal', 'POST')
            && HakAkses::boleh('admin', 'admin/supervisi/instrumen/3/butir') && ! HakAkses::boleh('operator', 'admin/supervisi/jadwal') && ! HakAkses::boleh('guru_piket', 'admin/supervisi/instrumen'));
    }

    private function ujiInstrumen(): void
    {
        $this->bagian('Instrumen & butir');
        $sup = new Supervisi();
        $r   = $this->ingat('supervisi_instrumen', $sup->simpanInstrumen(['kode' => 'zzuji ins', 'nama' => 'ZZUJI Instrumen', 'jenis' => 'lainnya', 'aktif' => '1']));
        $ins = $r['ok'] ? $sup->cariInstrumen((int) $r['id']) : null;
        $this->cek('tambah instrumen → kode dirapikan HURUF BESAR (ZZUJI-INS), urut paling bawah', $ins !== null && $ins['kode'] === 'ZZUJI-INS' && (int) $ins['aktif'] === 1
            && (int) $ins['urut'] > 3, json_encode($r));
        $this->cek('kode kembar → 409; jenis tak sah / nama kosong → 422', ($sup->simpanInstrumen(['kode' => 'ZZUJI-INS', 'nama' => 'Lain', 'jenis' => 'lainnya'])['kode'] ?? 0) === 409
            && ($sup->simpanInstrumen(['kode' => 'ZZUJI-X', 'nama' => 'Lain', 'jenis' => 'aneh'])['kode'] ?? 0) === 422
            && ($sup->simpanInstrumen(['kode' => 'ZZUJI-X', 'nama' => '', 'jenis' => 'lainnya'])['kode'] ?? 0) === 422);
        if ($ins === null) {
            return;
        }
        $id = (int) $ins['id'];
        $b  = [];
        foreach ([['Aspek A', 'Butir pertama uji'], ['Aspek A', 'Butir kedua uji'], ['Aspek B', 'Butir ketiga uji']] as [$a, $u]) {
            $x = $this->ingat('supervisi_butir', $sup->simpanButir($id, ['aspek' => $a, 'uraian' => $u, 'aktif' => '1']));
            $b[] = (int) ($x['id'] ?? 0);
        }
        $urut = static fn (): array => array_column($sup->daftarButir($id), 'uraian');
        $this->cek('tambah 3 butir → urut sesuai tambah; aspek tersaran', $urut() === ['Butir pertama uji', 'Butir kedua uji', 'Butir ketiga uji'] && $sup->daftarAspek($id) === ['Aspek A', 'Aspek B']);
        $sup->geserButir($b[2], -1);
        $atas = $sup->geserButir($b[0], -1);
        $this->cek('geser butir ketiga naik → di posisi 2; butir pertama naik → tetap ("paling atas")', $urut() === ['Butir pertama uji', 'Butir ketiga uji', 'Butir kedua uji'] && str_contains($atas['pesan'], 'paling atas'));
        $this->cek('uraian terlalu pendek → 422; ubah butir (nonaktif) → tersimpan', ($sup->simpanButir($id, ['aspek' => 'A', 'uraian' => 'abc'])['kode'] ?? 0) === 422
            && $sup->simpanButir(0, ['aspek' => 'Aspek B', 'uraian' => 'Butir ketiga diubah', 'aktif' => '0'], $b[2])['ok'] && count($sup->daftarButir($id, true)) === 2);
        $this->cek('hapus butir → hilang', $sup->hapusButir($b[1])['ok'] && count($sup->daftarButir($id)) === 2);
        $daftar = array_column($sup->daftarInstrumen(), null, 'id');
        $this->cek('daftar instrumen: jumlah butir aktif/semua & belum dipakai', ($daftar[$id]['butir_aktif'] ?? -1) === 1 && $daftar[$id]['butir_semua'] === 2 && $daftar[$id]['dipakai'] === false);

        // Pasang bawaan: semua ada → 0; PENILAIAN dinonaktifkan sementara → dipasang lagi dengan kode berakhiran tanggal.
        $this->cek('pasang instrumen bawaan saat semua ada → tidak menambah', $sup->pasangBawaan()['jumlah'] === 0);
        $pen = $this->db->table('supervisi_instrumen')->where('kode', 'PENILAIAN')->get()->getRowArray();
        if ($pen !== null) {
            $this->db->table('supervisi_instrumen')->where('id', $pen['id'])->update(['aktif' => 0]);
            $n   = $sup->pasangBawaan()['jumlah'];
            $baru = $this->db->table('supervisi_instrumen')->where('kode', 'PENILAIAN-' . date('Ymd'))->get()->getRowArray();
            $this->db->table('supervisi_instrumen')->where('id', $pen['id'])->update(['aktif' => (int) $pen['aktif']]);
            if ($baru !== null) {
                $this->dibuat['supervisi_instrumen'][] = (int) $baru['id'];
            }
            $this->cek('… bawaan nonaktif → dipasang lagi (kode PENILAIAN-' . date('Ymd') . ', 6 butir)', $n === 1 && $baru !== null
                && $this->db->table('supervisi_butir')->where('instrumen_id', $baru['id'] ?? 0)->countAllResults() === 6);
        }
    }

    private function ujiJadwal(): void
    {
        $this->bagian('Jadwal supervisi');
        $sup  = new Supervisi();
        $kini = Supervisi::periodeSekarang();
        $belum = $sup->ringkasan($kini['tahun_ajaran'], $kini['semester'])['guru_belum'];
        $spv  = $sup->pilihanSupervisor();
        $this->cek('saran supervisor: guru berjabatan struktural di atas (tanpa operator), Kepala Sekolah dulu', $spv['saran'] === [] || (! str_contains(mb_strtolower(implode(' ', array_column($spv['saran'], 'jabatan'))), 'operator')
            && str_starts_with($spv['saran'][0]['jabatan'], 'Kepala Sekolah')), json_encode(array_slice($spv['saran'], 0, 3)));
        $supId = (int) ($spv['saran'][0]['id'] ?? $spv['lain'][0]['id'] ?? 0);
        $calon = array_values(array_filter($belum, static fn (array $g): bool => $g['id'] !== $supId));
        if (count($calon) < 2 || $supId === 0) {
            $this->cek('data uji jadwal tersedia (2 guru belum dijadwalkan + supervisor)', false);

            return;
        }
        [$g1, $g2] = $calon;
        $saran = $sup->saranPengampu($g1['id']);
        $this->cek('saran mapel & kelas dari Penugasan: JP terbanyak dulu', $saran === [] || $saran[0]['jp'] >= end($saran)['jp'], json_encode(array_slice($saran, 0, 3)));

        $ins     = array_map('intval', array_column($sup->daftarInstrumen(true), 'id'));
        $tanggal = date('Y-m-d', strtotime('+1 day'));
        $dasar   = ['guru_id' => $g1['id'], 'supervisor_id' => $supId, 'tanggal' => $tanggal, 'jam' => '8:00', 'instrumen' => array_map('strval', array_slice($ins, 0, 2)),
            'mapel_id' => $saran[0]['mapel_id'] ?? '', 'kelas_id' => $saran[0]['kelas_id'] ?? ''];
        $r  = $this->ingat('supervisi_jadwal', $sup->simpanJadwal($dasar, $this->pencatat));
        $j  = $r['ok'] ? $sup->cariJadwal((int) $r['id']) : null;
        $pr = Supervisi::periode($tanggal);
        $this->cek('tambah jadwal → nama guru/supervisor disalin, jam 08:00, periode dari tanggal, status dijadwalkan', $j !== null && $j['nama_guru'] === $g1['nama'] && $j['jam'] === '08:00:00'
            && $j['tahun_ajaran'] === $pr['tahun_ajaran'] && $j['semester'] === $pr['semester'] && $j['status'] === 'dijadwalkan' && $j['instrumen_id'] === array_slice($ins, 0, 2)
            && ($saran === [] || $j['nama_mapel'] === $saran[0]['mapel']), json_encode($r));
        if ($j === null) {
            return;
        }
        $id = (int) $j['id'];
        $this->cek('guru sama, tanggal sama → 409', ($sup->simpanJadwal($dasar, $this->pencatat)['kode'] ?? 0) === 409);
        $this->cek('supervisor = guru itu sendiri → 422; tanpa instrumen → 422; jam ngawur → 422', ($sup->simpanJadwal(['supervisor_id' => $g1['id']] + $dasar, $this->pencatat)['kode'] ?? 0) === 422
            && ($sup->simpanJadwal(['instrumen' => []] + $dasar, $this->pencatat)['kode'] ?? 0) === 422 && ($sup->simpanJadwal(['jam' => '25:00'] + $dasar, $this->pencatat)['kode'] ?? 0) === 422);
        $nonaktif = array_values(array_diff(array_map('intval', array_column($sup->daftarInstrumen(), 'id')), $ins))[0] ?? 0;
        if ($nonaktif > 0) {
            $this->cek('instrumen nonaktif tidak bisa dipilih untuk jadwal baru → 422', ($sup->simpanJadwal(['guru_id' => $g2['id'], 'instrumen' => [$nonaktif]] + $dasar, $this->pencatat)['kode'] ?? 0) === 422);
        }
        $this->cek('instrumen yang dipakai jadwal tidak bisa dihapus → 409', ($sup->hapusInstrumen($ins[0])['kode'] ?? 0) === 409);

        $r = $sup->simpanJadwal(['batal' => '1'] + $dasar, $this->pencatat, $id);
        $this->cek('ubah → Batalkan: status batal; guru itu kini bisa dijadwalkan lagi di tanggal yang sama', $r['ok'] && $sup->cariJadwal($id)['status'] === 'batal'
            && $this->ingat('supervisi_jadwal', $sup->simpanJadwal($dasar, $this->pencatat))['ok']);
        $this->cek('ringkasan periode: 1 dijadwalkan + 1 batal, guru itu tidak lagi "belum dijadwalkan"', ($ring = $sup->ringkasan($pr['tahun_ajaran'], $pr['semester']))['batal'] >= 1
            && ! in_array($g1['id'], array_column($ring['guru_belum'], 'id'), true) && in_array($g1['id'], $ring['guru_sudah'], true));

        // Jadwal yang sudah ada penilaiannya (S2) — disimulasikan dengan satu baris supervisi_nilai.
        $this->db->table('supervisi_nilai')->insert(['jadwal_id' => $id, 'instrumen_id' => $ins[0], 'nama_instrumen' => 'uji', 'skor_total' => 0, 'skor_maks' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        $this->cek('sudah dinilai: hapus → 409, ganti guru → 409, cabut instrumen yang dinilai → 409', ($sup->hapusJadwal($id)['kode'] ?? 0) === 409
            && ($sup->simpanJadwal(['guru_id' => $g2['id'], 'batal' => '1'] + $dasar, $this->pencatat, $id)['kode'] ?? 0) === 409
            && ($sup->simpanJadwal(['instrumen' => [(string) $ins[1]], 'batal' => '1'] + $dasar, $this->pencatat, $id)['kode'] ?? 0) === 409);
        $this->db->table('supervisi_nilai')->where('jadwal_id', $id)->delete();
        $this->cek('belum dinilai lagi → hapus jadwal berhasil', $sup->hapusJadwal($id)['ok'] && $sup->cariJadwal($id) === null);
    }

    private function ujiMassal(): void
    {
        $this->bagian('Buat massal');
        $sup = new Supervisi();
        // Senin depan; bila pekan itu menyeberang semester, pakai Senin pertama semester berikutnya.
        $mulai = date('Y-m-d', strtotime('monday next week'));
        if (Supervisi::periode($mulai) !== Supervisi::periode(date('Y-m-d', strtotime($mulai . ' +11 days')))) {
            $mulai = date('Y-m-d', strtotime('first monday of ' . (date('n', strtotime($mulai)) >= 7 ? 'january ' . (date('Y', strtotime($mulai)) + 1) : 'july ' . date('Y', strtotime($mulai)))));
        }
        $jumat  = date('Y-m-d', strtotime($mulai . ' +4 days'));
        $pr     = Supervisi::periode($mulai);
        $belum  = $sup->ringkasan($pr['tahun_ajaran'], $pr['semester'])['guru_belum'];
        $spv    = $sup->pilihanSupervisor();
        $supId  = (int) ($spv['saran'][0]['id'] ?? $spv['lain'][0]['id'] ?? 0);
        $guru   = array_slice(array_values(array_filter($belum, static fn (array $g): bool => $g['id'] !== $supId)), 0, 6);
        $ins    = array_map('strval', array_column($sup->daftarInstrumen(true), 'id'));
        if (count($guru) < 6) {
            $this->cek('data uji massal tersedia (6 guru belum dijadwalkan)', false);

            return;
        }
        $data = ['guru_ids' => array_map('strval', array_column($guru, 'id')), 'supervisor_id' => (string) $supId, 'mulai' => $mulai, 'selesai' => $jumat, 'per_hari' => '1', 'instrumen' => $ins];
        $this->cek('6 guru, Senin–Jumat, maks 1/hari → 422 (butuh 6 hari sekolah)', ($sup->buatMassal($data, $this->pencatat)['kode'] ?? 0) === 422);
        $this->cek('rentang menyeberang semester → 422', ($sup->buatMassal(['mulai' => $pr['semester'] === 'ganjil' ? substr($pr['tahun_ajaran'], 0, 4) . '-12-20' : substr($pr['tahun_ajaran'], 5, 4) . '-06-25',
            'selesai' => $pr['semester'] === 'ganjil' ? substr($pr['tahun_ajaran'], 5, 4) . '-01-10' : substr($pr['tahun_ajaran'], 5, 4) . '-07-10'] + $data, $this->pencatat)['kode'] ?? 0) === 422);

        // Satu guru dijadwalkan dulu → dilewati; supervisor ikut dicentang → tidak dijadwalkan.
        $this->ingat('supervisi_jadwal', $sup->simpanJadwal(['guru_id' => $guru[0]['id'], 'supervisor_id' => $supId, 'tanggal' => $mulai, 'instrumen' => $ins], $this->pencatat));
        $data['guru_ids'][] = (string) $supId;
        $data['per_hari']   = '2';
        $r = $this->ingat('supervisi_jadwal', $sup->buatMassal($data, $this->pencatat));
        $rows = $r['ok'] ? $this->db->table('supervisi_jadwal')->whereIn('id', $r['ids'])->get()->getResultArray() : [];
        $perTanggal = array_count_values(array_column($rows, 'tanggal'));
        $hariKerja  = Supervisi::hariKerja($mulai, $jumat);
        $this->cek('maks 2/hari: 5 guru dijadwalkan, 1 dilewati (sudah punya), supervisor tidak ikut', $r['ok'] && $r['jumlah'] === 5 && count($r['dilewati']) === 1
            && str_contains($r['pesan'], 'Supervisornya sendiri') && ! in_array($supId, array_map('intval', array_column($rows, 'guru_id')), true), json_encode($r));
        $this->cek('… dibagi rata: tiap tanggal = hari sekolah, paling banyak 2, menyebar ke ≥ 3 hari', $perTanggal !== [] && max($perTanggal) <= 2 && count($perTanggal) >= 3
            && array_diff(array_keys($perTanggal), $hariKerja) === [], json_encode($perTanggal));
        $saran = $sup->saranPengampu((int) $guru[1]['id']);
        $baris = array_values(array_filter($rows, static fn (array $x): bool => (int) $x['guru_id'] === (int) $guru[1]['id']))[0] ?? [];
        $this->cek('… mapel & kelas terisi dari Penugasan (JP terbanyak)', $saran === [] ? ($baris['mapel_id'] ?? null) === null : (int) ($baris['mapel_id'] ?? 0) === $saran[0]['mapel_id'] && (int) $baris['kelas_id'] === $saran[0]['kelas_id']);
        $this->cek('semua guru sudah terjadwal → 409', ($sup->buatMassal($data, $this->pencatat)['kode'] ?? 0) === 409);
    }

    private function ujiHalaman(): void
    {
        $this->bagian('Halaman web');
        $sup = new Supervisi();
        $ins = $sup->daftarInstrumen(true);

        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiInstrumen::class, 'admin/supervisi/instrumen', 'GET', 'index');
        $this->cek('Instrumen: tabel + tombol Butir + Tambah + Pasang bawaan', str_contains($html, esc($ins[0]['nama'])) && str_contains($html, '/butir') && str_contains($html, 'Tambah instrumen')
            && str_contains($html, 'Pasang instrumen bawaan'));
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiInstrumen::class, 'admin/supervisi/instrumen/' . $ins[0]['id'] . '/butir', 'GET', 'butir', [], [], (int) $ins[0]['id']);
        $this->cek('Butir: aspek berkelompok + tombol geser', str_contains($html, '/geser') && str_contains($html, 'scope="colgroup"') && str_contains($html, 'sb-aspek-daftar'));

        $r = $this->panggil(\App\Controllers\Admin\SupervisiInstrumen::class, 'admin/supervisi/instrumen', 'POST', 'store', ['kode' => 'ZZUJI-WEB', 'nama' => 'ZZUJI Web', 'jenis' => 'lainnya', 'aktif' => '1']);
        $baru = $this->db->table('supervisi_instrumen')->where('kode', 'ZZUJI-WEB')->get()->getRowArray();
        $this->cek('POST tambah instrumen → dialihkan + pesan sukses', $r instanceof RedirectResponse && $baru !== null && str_contains((string) session('success'), 'ditambahkan'));
        if ($baru !== null) {
            $this->dibuat['supervisi_instrumen'][] = (int) $baru['id'];
            $this->panggil(\App\Controllers\Admin\SupervisiInstrumen::class, 'admin/supervisi/instrumen/delete/' . $baru['id'], 'POST', 'delete', [], [], (int) $baru['id']);
            $this->cek('POST hapus instrumen → hilang', $sup->cariInstrumen((int) $baru['id']) === null && str_contains((string) session('success'), 'dihapus'));
        }

        $guru = $sup->pilihanGuru();
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'GET', 'index');
        $this->cek('Jadwal: komponen supervisiJadwal + supervisi.js + saringan + dialog massal', str_contains($html, 'x-data="supervisiJadwal"') && str_contains($html, 'supervisi.js')
            && str_contains($html, 'name="semester"') && str_contains($html, 'Buat Jadwal Supervisi Massal') && str_contains($html, Supervisi::labelPeriode(...array_values(Supervisi::periodeSekarang()))));
        $gid = (int) ($guru[0]['id'] ?? 0);
        $j   = json_decode((string) $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal/saran', 'GET', 'saran', [], ['guru_id' => $gid])->getBody(), true);
        $this->cek('GET saran (JSON) → ok + daftar', ($j['ok'] ?? false) && is_array($j['saran'] ?? null));

        $spv   = $sup->pilihanSupervisor();
        $supId = (int) ($spv['saran'][0]['id'] ?? $spv['lain'][0]['id'] ?? 0);
        $kini  = Supervisi::periodeSekarang();
        $calon = array_values(array_filter($sup->ringkasan($kini['tahun_ajaran'], $kini['semester'])['guru_belum'], static fn (array $g): bool => $g['id'] !== $supId))[0] ?? null;
        if ($calon === null) {
            $this->cek('data uji halaman jadwal tersedia', false);

            return;
        }
        $tgl = date('Y-m-d', strtotime('+2 day'));
        $r   = $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'POST', 'store', ['guru_id' => $calon['id'], 'supervisor_id' => $supId,
            'tanggal' => $tgl, 'instrumen' => [(string) $ins[0]['id']], 'kembali' => 'status=batal']);
        $row = $this->db->table('supervisi_jadwal')->where(['guru_id' => $calon['id'], 'tanggal' => $tgl])->where('status !=', 'batal')->get()->getRowArray();
        if ($row !== null) {
            $this->dibuat['supervisi_jadwal'][] = (int) $row['id'];
        }
        $p = Supervisi::periode($tgl);
        $this->cek('POST tambah jadwal → kembali ke saringan + periode jadwal itu, pesan sukses', $r instanceof RedirectResponse && $row !== null && str_contains((string) session('success'), 'ditambahkan')
            && str_contains($r->getHeaderLine('Location'), 'status=batal') && str_contains($r->getHeaderLine('Location'), 'semester=' . $p['semester']), $r instanceof RedirectResponse ? $r->getHeaderLine('Location') : '');
        $r = $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'POST', 'store', ['guru_id' => $calon['id'], 'supervisor_id' => $supId, 'tanggal' => $tgl, 'instrumen' => [(string) $ins[0]['id']]]);
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'GET', 'index', [], [], ...[]);
        preg_match('/data-isian-lama="([^"]*)"/', $html, $m);
        $this->cek('simpan ganda → pesan galat + dialog dibuka lagi dengan isian lama', str_contains((string) session('error'), 'sudah punya jadwal') && str_contains($m[1] ?? '', (string) $calon['id']),
            (string) session('error') . ' | ' . ($m[1] ?? '-'));
        if ($row !== null) {
            session()->remove(['_ci_old_input']);
            $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'GET', 'index', [], ['ta' => $p['tahun_ajaran'], 'semester' => $p['semester']]);
            $this->cek('daftar memuat jadwal baru (nama guru + tombol Ubah)', str_contains($html, esc($calon['nama'])) && str_contains($html, 'data-ubah'));
            $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal/delete/' . $row['id'], 'POST', 'delete', [], [], (int) $row['id']);
            $this->cek('POST hapus jadwal → hilang', $sup->cariJadwal((int) $row['id']) === null && str_contains((string) session('success'), 'dihapus'));
        }
    }

    // =================================================================
    // S2 — Penilaian
    // =================================================================

    private function ujiHitung(): void
    {
        $this->bagian('Hitung nilai & predikat');
        $p = static fn (?float $n): ?string => Penilaian::predikat($n);
        $this->cek('batas predikat: 60,99 Kurang · 61 Cukup · 75,99 Cukup · 76 Baik · 90,99 Baik · 91 Amat Baik · 100 Amat Baik · kosong → null',
            $p(60.99) === 'Kurang' && $p(61.0) === 'Cukup' && $p(75.99) === 'Cukup' && $p(76.0) === 'Baik' && $p(90.99) === 'Baik' && $p(91.0) === 'Amat Baik'
            && $p(100.0) === 'Amat Baik' && $p(0.0) === 'Kurang' && $p(null) === null);
        $h = Penilaian::hitung([4, 3, 0, null]);
        $this->cek('hitung [4,3,TD,kosong] → skor 7 dari 8, nilai 87,50, 1 TD, 1 kosong', $h['total'] === 7 && $h['maks'] === 8 && $h['nilai'] === 87.5 && $h['td'] === 1 && $h['kosong'] === 1 && $h['predikat'] === 'Baik', json_encode($h));
        $this->cek('hitung [3,3,2] → 66,67 (2 desimal); semua TD → nilai kosong', Penilaian::hitung([3, 3, 2])['nilai'] === 66.67 && Penilaian::hitung([0, 0])['nilai'] === null);
        $this->cek('rata-rata [80, 70, kosong] → 75; skor isian: "" → kosong, TD/0 → 0, "4" → 4, "5"/"x" → tidak sah', Penilaian::rataRata([80.0, 70.0, null]) === 75.0
            && Penilaian::skorSah('') === null && Penilaian::skorSah('TD') === 0 && Penilaian::skorSah('0') === 0 && Penilaian::skorSah('4') === 4
            && Penilaian::skorSah('5') === false && Penilaian::skorSah('x') === false);
    }

    private function ujiPenilaian(): void
    {
        $this->bagian('Lembar penilaian: draf, selesai, salinan butir');
        $sup  = new Supervisi();
        $pen  = new Penilaian();
        $kini = Supervisi::periodeSekarang();
        $spv  = $sup->pilihanSupervisor();
        $supId = (int) ($spv['saran'][0]['id'] ?? $spv['lain'][0]['id'] ?? 0);
        $guru  = array_values(array_filter($sup->ringkasan($kini['tahun_ajaran'], $kini['semester'])['guru_belum'], static fn (array $g): bool => $g['id'] !== $supId))[0] ?? null;
        $ins   = array_column($sup->daftarInstrumen(true), null, 'kode');
        if ($guru === null || ! isset($ins['PERENCANAAN'], $ins['PENILAIAN'])) {
            $this->cek('data uji penilaian tersedia', false);

            return;
        }
        $i1 = (int) $ins['PERENCANAAN']['id'];
        $i2 = (int) $ins['PENILAIAN']['id'];
        $r  = $this->ingat('supervisi_jadwal', $sup->simpanJadwal(['guru_id' => $guru['id'], 'supervisor_id' => $supId, 'tanggal' => date('Y-m-d'),
            'instrumen' => [(string) $i2, (string) $i1]], $this->pencatat));
        if (! $r['ok']) {
            $this->cek('jadwal uji penilaian dibuat', false, $r['pesan']);

            return;
        }
        $id = (int) $r['id'];
        $l  = $pen->lembar($id);
        $this->cek('lembar baru: 2 instrumen urut instrumen (Perencanaan dulu), butir aktif dengan kunci "b…", belum tersimpan', $l !== null && array_column($l['instrumen'], 'id') === [$i1, $i2]
            && count($l['instrumen'][0]['butir']) === count($sup->daftarButir($i1, true)) && str_starts_with($l['instrumen'][0]['butir'][0]['kunci'], 'b') && ! $l['instrumen'][0]['tersimpan']
            && $l['ringkas']['nilai'] === null && ! $l['ringkas']['lengkap']);
        $k1 = array_column($l['instrumen'][0]['butir'], 'kunci');
        $k2 = array_column($l['instrumen'][1]['butir'], 'kunci');

        // Draf: hanya 3 butir instrumen pertama + kekuatan.
        $draf = [$i1 => ['skor' => [$k1[0] => '4', $k1[1] => '3', $k1[2] => 'TD'], 'catatan' => [$k1[0] => '  Modul   lengkap '], 'kekuatan' => "Baris satu\r\nBaris dua"]];
        $r = $pen->simpan($id, $draf, false, $this->pencatat);
        $n = $this->db->table('supervisi_nilai')->where('jadwal_id', $id)->get()->getResultArray();
        $this->cek('simpan draf → status tetap dijadwalkan; hanya instrumen yang disentuh dibuatkan hasil (semua butir disalin)', $r['ok'] && $sup->cariJadwal($id)['status'] === 'dijadwalkan'
            && count($n) === 1 && (int) $n[0]['instrumen_id'] === $i1 && $this->db->table('supervisi_nilai_butir')->where('nilai_id', $n[0]['id'])->countAllResults() === count($k1)
            && (float) $n[0]['nilai'] === 87.5 && $n[0]['kekuatan'] === "Baris satu\nBaris dua", json_encode($r));
        $l = $pen->lembar($id);
        $b0 = $l['instrumen'][0]['butir'][0];
        $this->cek('lembar sesudah draf: kunci "n…", skor & catatan (dirapikan) tersimpan, TD = 0, belum diisi = kosong', str_starts_with($b0['kunci'], 'n') && $b0['skor'] === 4
            && $b0['catatan'] === 'Modul lengkap' && $l['instrumen'][0]['butir'][2]['skor'] === 0 && $l['instrumen'][0]['butir'][3]['skor'] === null && $l['instrumen'][1]['tersimpan'] === false);
        $k1 = array_column($l['instrumen'][0]['butir'], 'kunci');

        $this->cek('selesai padahal belum lengkap → 422 "belum diisi"', ($x = $pen->simpan($id, $draf, true, $this->pencatat))['kode'] === 422 && str_contains($x['pesan'], 'belum diisi'), $x['pesan']);
        $this->cek('skor 7 → 422; "tidak perlu" + tindak lanjut lain → 422', ($pen->simpan($id, [$i1 => ['skor' => [$k1[0] => '7']]], false, $this->pencatat)['kode'] ?? 0) === 422
            && ($pen->simpan($id, [$i1 => ['tindak_lanjut' => ['tidak_perlu', 'individual']]], false, $this->pencatat)['kode'] ?? 0) === 422);

        $lengkap = [
            $i1 => ['skor' => array_fill_keys($k1, '4'), 'tindak_lanjut' => ['model'], 'rekomendasi' => 'Pertahankan'],
            $i2 => ['skor' => array_fill_keys($k2, 'TD'), 'tindak_lanjut' => ['pelatihan', 'individual']],
        ];
        $lengkap[$i1]['skor'][$k1[1]] = 'TD';
        $this->cek('instrumen yang semua butirnya TD → 422 "minimal satu"', str_contains($pen->simpan($id, $lengkap, true, $this->pencatat)['pesan'], 'minimal satu'));
        $lengkap[$i2]['skor'] = array_fill_keys($k2, '3');
        $r = $pen->simpan($id, $lengkap, true, $this->pencatat);
        $j = $sup->cariJadwal($id);
        $this->cek('Simpan & selesai: Perencanaan 100 (TD tak dihitung), Penilaian 75 → nilai akhir 87,50 Baik, status selesai', $r['ok'] && $j['status'] === 'selesai'
            && (float) $j['nilai_akhir'] === 87.5 && $j['predikat'] === 'Baik' && $r['predikat'] === 'Baik', json_encode($r));
        $l = $pen->lembar($id);
        $this->cek('hasil tersimpan: tindak lanjut (urut katalog), lengkap; lembar dikirim utuh → catatan yang tak dikirim jadi kosong', $l['instrumen'][1]['tindak_lanjut'] === ['individual', 'pelatihan'] && $l['ringkas']['lengkap']
            && $l['instrumen'][0]['butir'][0]['catatan'] === '' && $l['ringkas']['nilai'] === 87.5);
        $this->cek('kunci butir lama "b…" (lembar dibuka sebelum tersimpan) tetap diterima', str_starts_with($l['instrumen'][1]['butir'][0]['kunci'], 'n') && str_starts_with($k2[0], 'b')
            && $l['instrumen'][1]['ringkas']['nilai'] === 75.0);
        $this->cek('sudah selesai: simpan draf → 409; hapus jadwal → 409', ($pen->simpan($id, $lengkap, false, $this->pencatat)['kode'] ?? 0) === 409 && ($sup->hapusJadwal($id)['kode'] ?? 0) === 409);

        // Salinan: uraian butir di instrumen diubah → lembar yang sudah ada tetap memakai uraian lama.
        $butir = $sup->daftarButir($i1)[0];
        $this->db->table('supervisi_butir')->where('id', $butir['id'])->update(['uraian' => 'ZZUJI uraian baru']);
        $uraian = $pen->lembar($id)['instrumen'][0]['butir'][0]['uraian'];
        $this->db->table('supervisi_butir')->where('id', $butir['id'])->update(['uraian' => $butir['uraian']]);
        $this->cek('butir instrumen diubah sesudah dinilai → lembar tetap memakai salinan lama', $uraian === $butir['uraian']);

        // Halaman.
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian', 'GET', 'index');
        $this->cek('halaman Penilaian: daftar + nilai 87,50 + "Lihat hasil"', str_contains($html, esc($guru['nama'])) && str_contains($html, '87,50') && str_contains($html, 'Lihat hasil'));
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian/' . $id, 'GET', 'lembar', [], [], $id);
        $this->cek('lembar (selesai): komponen supervisiLembar + radio skor tercentang + tombol "Simpan perubahan" (tanpa draf)', str_contains($html, 'x-data="supervisiLembar"')
            && str_contains($html, 'name="nilai[' . $i1 . '][skor][' . $k1[0] . ']" value="4" class="peer sr-only" checked') && str_contains($html, 'Simpan perubahan') && ! str_contains($html, 'value="draf"'));

        $this->db->table('supervisi_jadwal')->where('id', $id)->update(['status' => 'dijadwalkan', 'nilai_akhir' => null, 'predikat' => null]);
        $r = $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian/' . $id, 'POST', 'simpan', ['aksi' => 'selesai', 'kembali' => 'status=selesai',
            'nilai' => [$i1 => ['skor' => [$k1[0] => '2']]]], [], $id);
        $loc = $r instanceof RedirectResponse ? $r->getHeaderLine('Location') : '';
        $this->cek('POST selesai belum lengkap → kembali ke lembar #ins-{instrumen} + pesan galat', str_contains($loc, 'penilaian/' . $id) && str_contains($loc, '#ins-') && (string) session('error') !== '', $loc);
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian/' . $id, 'GET', 'lembar', [], [], $id);
        $this->cek('… isian lama tampil lagi (skor 2 tercentang)', str_contains($html, 'name="nilai[' . $i1 . '][skor][' . $k1[0] . ']" value="2" class="peer sr-only" checked'));
        session()->remove(['_ci_old_input']);
        $r = $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian/' . $id, 'POST', 'simpan', ['aksi' => 'selesai', 'kembali' => 'status=selesai', 'nilai' => $lengkap], [], $id);
        $loc = $r instanceof RedirectResponse ? $r->getHeaderLine('Location') : '';
        $this->cek('POST selesai lengkap → ke daftar penilaian (saringan semula) + pesan sukses', str_ends_with($loc, 'admin/supervisi/penilaian?status=selesai') && str_contains((string) session('success'), 'selesai'), $loc);

        $ubah = ['guru_id' => $guru['id'], 'supervisor_id' => $supId, 'tanggal' => date('Y-m-d'), 'instrumen' => [(string) $i1, (string) $i2], 'catatan' => 'diubah'];
        $this->cek('jadwal selesai diubah (catatan) → tetap selesai dengan nilai akhirnya', $sup->simpanJadwal($ubah, $this->pencatat, $id)['ok']
            && ($j = $sup->cariJadwal($id))['status'] === 'selesai' && (float) $j['nilai_akhir'] === 87.5);
        $i3 = (int) ($ins['PELAKSANAAN']['id'] ?? 0);
        if ($i3 > 0) {
            $this->cek('… ditambah instrumen yang belum dinilai → kembali dijadwalkan, nilai akhir dikosongkan', $sup->simpanJadwal(['instrumen' => [(string) $i1, (string) $i2, (string) $i3]] + $ubah, $this->pencatat, $id)['ok']
                && ($j = $sup->cariJadwal($id))['status'] === 'dijadwalkan' && $j['nilai_akhir'] === null && ! $pen->lembar($id)['ringkas']['lengkap']);
        }
        $this->cek('dibatalkan → nilai akhir kosong & tidak bisa dinilai (409)', $sup->simpanJadwal(['batal' => '1'] + $ubah, $this->pencatat, $id)['ok']
            && $sup->cariJadwal($id)['nilai_akhir'] === null && ($pen->simpan($id, $lengkap, true, $this->pencatat)['kode'] ?? 0) === 409);
    }

    // =================================================================
    // S3 — Rekap & berkas cetak
    // =================================================================

    /**
     * Siapkan kunjungan uji: guru A dua kunjungan selesai (87,50 & 50,00 → rata-rata 68,75 Cukup), guru B satu kunjungan terjadwal.
     *
     * @return array{a: array, b: array, j1: int, j2: int, j3: int, i1: int, i2: int, i3: int, sup: int}|null
     */
    private function siapkanRekap(): ?array
    {
        $sup   = new Supervisi();
        $pen   = new Penilaian();
        $kini  = Supervisi::periodeSekarang();
        $spv   = $sup->pilihanSupervisor();
        $supId = (int) ($spv['saran'][0]['id'] ?? $spv['lain'][0]['id'] ?? 0);
        $calon = array_values(array_filter($sup->ringkasan($kini['tahun_ajaran'], $kini['semester'])['guru_belum'], static fn (array $g): bool => $g['id'] !== $supId));
        $ins   = array_column($sup->daftarInstrumen(true), null, 'kode');
        if (count($calon) < 4 || ! isset($ins['PERENCANAAN'], $ins['PENILAIAN'], $ins['PELAKSANAAN'])) {
            return null;
        }
        [$i1, $i2, $i3] = [(int) $ins['PERENCANAAN']['id'], (int) $ins['PENILAIAN']['id'], (int) $ins['PELAKSANAAN']['id']];
        $hari  = date('Y-m-d');
        $lain  = Supervisi::periode(date('Y-m-d', strtotime('+1 day'))) === $kini ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d', strtotime('-1 day'));
        $buat  = fn (int $guru, string $tgl, array $insIds): int => (int) ($this->ingat('supervisi_jadwal', $sup->simpanJadwal(['guru_id' => $guru, 'supervisor_id' => $supId,
            'tanggal' => $tgl, 'jam' => '08:00', 'instrumen' => array_map('strval', $insIds), 'catatan' => 'ZZUJI rekap'], $this->pencatat))['id'] ?? 0);
        $isi   = static function (array $lembar, array $skorPerIns, array $tl) : array {
            $out = [];
            foreach ($lembar['instrumen'] as $x) {
                $out[$x['id']] = ['skor' => array_fill_keys(array_column($x['butir'], 'kunci'), (string) $skorPerIns[$x['id']]), 'tindak_lanjut' => $tl[$x['id']] ?? [],
                    'kekuatan' => 'ZZUJI kekuatan', 'rekomendasi' => 'ZZUJI rekomendasi'];
            }

            return $out;
        };
        $j1 = $buat($calon[0]['id'], $hari, [$i1, $i2]);
        $j2 = $buat($calon[0]['id'], $lain, [$i1]);
        $j3 = $buat($calon[1]['id'], $hari, [$i3]);
        if ($j1 === 0 || $j2 === 0 || $j3 === 0) {
            return null;
        }
        $r1 = $pen->simpan($j1, $isi($pen->lembar($j1), [$i1 => 4, $i2 => 3], [$i1 => ['model'], $i2 => ['individual']]), true, $this->pencatat);
        $r2 = $pen->simpan($j2, $isi($pen->lembar($j2), [$i1 => 2], [$i1 => ['tidak_perlu']]), true, $this->pencatat);
        if (! $r1['ok'] || ! $r2['ok']) {
            $this->cek('kunjungan uji rekap dinilai', false, $r1['pesan'] . ' | ' . $r2['pesan']);

            return null;
        }

        return ['a' => $calon[0], 'b' => $calon[1], 'c' => $calon[2], 'd' => $calon[3], 'j1' => $j1, 'j2' => $j2, 'j3' => $j3, 'i1' => $i1, 'i2' => $i2, 'i3' => $i3, 'sup' => $supId] + $kini;
    }

    private function ujiRekapCetak(): void
    {
        $this->bagian('S3 — Rekap, Excel Program, PDF Lembar Hasil, Excel Rekap');
        $d = $this->siapkanRekap();
        if ($d === null) {
            $this->cek('data uji rekap tersedia (≥ 4 guru belum terjadwal, 3 instrumen bawaan)', false);

            return;
        }
        $this->rekapUji = $d;
        $pen   = new Penilaian();
        [$ta, $sem] = [$d['tahun_ajaran'], $d['semester']];
        $rekap = $pen->rekap($ta, $sem);
        $baris = array_values(array_filter($rekap['baris'], static fn (array $b): bool => $b['guru_id'] === $d['a']['id']))[0] ?? null;
        $kunci = array_column($rekap['instrumen'], 'kunci', 'id');
        $this->cek('rekap: guru dengan 2 kunjungan → nilai per instrumen dirata (Perencanaan 75, Penilaian 75), rata-rata 68,75 Cukup', $baris !== null
            && count($baris['kunjungan']) === 2 && $baris['nilai'][$kunci[$d['i1']]] === 75.0 && $baris['nilai'][$kunci[$d['i2']]] === 75.0
            && $baris['rata_rata'] === 68.75 && $baris['predikat'] === 'Cukup', json_encode($baris));
        $this->cek('rekap: tindak lanjut digabung urut katalog, "tidak perlu" gugur bila ada pembinaan', ($baris['tindak_lanjut'] ?? null) === ['individual', 'model'], json_encode($baris['tindak_lanjut'] ?? null));
        $idKolom = array_column($rekap['instrumen'], 'id');
        $this->cek('rekap: kolom instrumen urut daftar instrumen (Perencanaan sebelum Penilaian)', in_array($d['i1'], $idKolom, true)
            && array_search($d['i1'], $idKolom, true) < array_search($d['i2'], $idKolom, true));
        $belum = array_column($rekap['belum'], null, 'id');
        $this->cek('rekap: guru B terjadwal (tanggal + id jadwal), guru C belum dijadwalkan, guru A tidak di daftar belum', ($belum[$d['b']['id']]['status'] ?? '') === 'terjadwal'
            && ($belum[$d['b']['id']]['jadwal_id'] ?? 0) === $d['j3'] && ($belum[$d['c']['id']]['status'] ?? '') === 'belum_dijadwalkan' && ! isset($belum[$d['a']['id']]));
        $this->cek('rekap: ringkasan menghitung Cukup ≥ 1, kunjungan selesai ≥ 2; saringan predikat menyaring baris saja', $rekap['ringkas']['per_predikat']['Cukup'] >= 1
            && $rekap['ringkas']['kunjungan_selesai'] >= 2 && in_array($d['a']['id'], array_column($pen->rekap($ta, $sem, 'Cukup')['baris'], 'guru_id'), true)
            && ! in_array($d['a']['id'], array_column(($x = $pen->rekap($ta, $sem, 'Amat Baik'))['baris'], 'guru_id'), true) && $x['ringkas'] === $rekap['ringkas']
            && $pen->rekap($ta, $sem, 'ngawur')['predikat'] === '');

        // Excel Program.
        $rows = (new Supervisi())->daftarJadwal(['ta' => $ta, 'semester' => $sem, 'guru_id' => $d['a']['id']]);
        $ws   = $this->bacaXlsx(HonorCetak::xlsx(Cetak::program($rows, $ta, $sem)))?->getSheet(0);
        $this->cek('Excel Program: judul, kepala NO…KET di baris 5, nama guru & instrumen, A4 mendatar, Times New Roman', $ws !== null
            && $ws->getCell('A1')->getValue() === 'PROGRAM SUPERVISI AKADEMIK' && $ws->getCell('A3')->getValue() === 'SEMESTER ' . mb_strtoupper(Supervisi::SEMESTER[$sem]) . ' TAHUN PELAJARAN ' . str_replace('/', '-', $ta)
            && $ws->getCell('B5')->getValue() === 'HARI/TANGGAL' && $ws->getCell('I5')->getValue() === 'KET' && str_starts_with((string) $ws->getCell('D6')->getValue(), $d['a']['nama'])
            && str_contains((string) $ws->getCell('H6')->getValue(), 'Perencanaan Pembelajaran') && str_starts_with((string) $ws->getCell('I6')->getValue(), 'Selesai')
            && $ws->getPageSetup()->getOrientation() === 'landscape' && $ws->getStyle('D6')->getFont()->getName() === 'Times New Roman'
            && str_contains($this->teksLembar($ws), 'Kepala Sekolah'), $ws !== null ? (string) $ws->getCell('D6')->getValue() . ' | ' . $ws->getCell('I6')->getValue() : 'tak terbaca');

        // PDF lembar hasil.
        $l1   = $pen->lembar($d['j1']);
        $html = Cetak::lembarHtml([$l1, $pen->lembar($d['j3'])]);
        $this->cek('HTML lembar: judul, centang skor, TD/catatan, temuan, TTD 3 pihak; kunjungan belum dinilai bertanda BELUM DINILAI', str_contains($html, 'LEMBAR HASIL SUPERVISI AKADEMIK')
            && substr_count($html, '&#10003;') === count($l1['instrumen'][0]['butir']) + count($l1['instrumen'][1]['butir']) && str_contains($html, 'ZZUJI kekuatan')
            && str_contains($html, 'Guru yang disupervisi') && str_contains($html, 'Supervisor') && str_contains($html, 'BELUM DINILAI') && str_contains($html, '87,50')
            && substr_count($html, 'class="halaman') === 2);
        $pdf = Cetak::pdf(Cetak::lembarHtml([$l1]));
        $this->cek('PDF lembar hasil berawalan %PDF', str_starts_with($pdf, '%PDF'), substr($pdf, 0, 20));
        if (is_string($folder = CLI::getOption('simpan')) && is_dir($folder)) {
            // Contoh berkas untuk dibuka manual (data uji dihapus sesudahnya, berkasnya tetap).
            $folder = rtrim($folder, '/\\');
            file_put_contents($folder . '/lembar-hasil.pdf', Cetak::pdf($html));
            file_put_contents($folder . '/lembar-hasil.html', '<!doctype html><meta charset="utf-8"><body style="width:727px;margin:26px 34px">' . $html);
            file_put_contents($folder . '/program.xlsx', HonorCetak::xlsx(Cetak::program($rows, $ta, $sem)));
            file_put_contents($folder . '/rekap.xlsx', HonorCetak::xlsx(Cetak::rekap($rekap)));
            CLI::write('  contoh berkas disimpan di ' . $folder, 'cyan');
        }

        // Excel Rekap.
        $ss = $this->bacaXlsx(HonorCetak::xlsx(Cetak::rekap($rekap)));
        $ws = $ss?->getSheetByName('Rekap');
        $baru = null;
        if ($ws !== null) {
            for ($b = 6; $b <= $ws->getHighestRow(); $b++) {
                if ($ws->getCell('B' . $b)->getValue() === $d['a']['nama']) {
                    $baru = $b;
                }
            }
        }
        $kolRata = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($rekap['instrumen']));
        $this->cek('Excel Rekap: lembar Rekap (kepala RATA-RATA, baris guru A rata-rata 68,75 Cukup) + lembar Ringkasan (predikat & belum disupervisi)', $ws !== null && $baru !== null
            && $ws->getCell($kolRata . '5')->getValue() === 'RATA-RATA' && (float) $ws->getCell($kolRata . $baru)->getValue() === 68.75
            && $ws->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(7 + count($rekap['instrumen'])) . $baru)->getValue() === 'Cukup'
            && ($rs = $ss->getSheetByName('Ringkasan')) !== null && str_contains($this->teksLembar($rs), 'GURU YANG BELUM DISUPERVISI') && str_contains($this->teksLembar($rs), $d['b']['nama'])
            && str_contains($this->teksLembar($rs), 'Cukup (61–75,99)'), $ws !== null ? 'baris ' . $baru : 'tak terbaca');

        // Halaman & unduhan web.
        $qs   = ['ta' => $ta, 'semester' => $sem];
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiRekap::class, 'admin/supervisi/rekap', 'GET', 'index', [], $qs);
        $this->cek('halaman Rekap: guru A + 68,75 + tautan PDF kunjungan + bagian Belum disupervisi + tombol unduh', str_contains($html, esc($d['a']['nama'])) && str_contains($html, '68,75')
            && str_contains($html, 'penilaian/' . $d['j1'] . '/pdf') && str_contains($html, 'id="belum-disupervisi"') && str_contains($html, 'rekap/excel') && str_contains($html, 'supervisi/lembar?'));
        $res = $this->panggil(\App\Controllers\Admin\SupervisiRekap::class, 'admin/supervisi/rekap/excel', 'GET', 'excel', [], $qs);
        $this->cek('GET rekap/excel → .xlsx', str_contains($res->getHeaderLine('Content-Type'), 'spreadsheetml') && str_starts_with((string) $res->getBody(), 'PK'));
        $res = $this->panggil(\App\Controllers\Admin\SupervisiCetak::class, 'admin/supervisi/program', 'GET', 'program', [], $qs);
        $this->cek('GET program → .xlsx (nama berkas Program Supervisi …)', str_starts_with((string) $res->getBody(), 'PK') && str_contains($res->getHeaderLine('Content-Disposition'), 'Program Supervisi'));
        $res = $this->panggil(\App\Controllers\Admin\SupervisiCetak::class, 'admin/supervisi/penilaian/' . $d['j1'] . '/pdf', 'GET', 'lembar', [], [], $d['j1']);
        $this->cek('GET penilaian/{id}/pdf → PDF inline', $res->getHeaderLine('Content-Type') === 'application/pdf' && str_starts_with((string) $res->getBody(), '%PDF'));
        $res = $this->panggil(\App\Controllers\Admin\SupervisiCetak::class, 'admin/supervisi/lembar', 'GET', 'gabungan', [], $qs + ['guru_id' => $d['a']['id']]);
        $this->cek('GET lembar (gabungan guru A) → PDF', str_starts_with((string) $res->getBody(), '%PDF'));
        $res = $this->panggil(\App\Controllers\Admin\SupervisiCetak::class, 'admin/supervisi/lembar', 'GET', 'gabungan', [], $qs + ['guru_id' => $d['b']['id'], 'dari' => 'rekap']);
        $this->cek('GET lembar tanpa kunjungan selesai → kembali ke Rekap + pesan galat', $res instanceof RedirectResponse && str_contains($res->getHeaderLine('Location'), 'supervisi/rekap')
            && str_contains((string) session('error'), 'Belum ada kunjungan'));
        $html = (string) $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian', 'GET', 'index', [], $qs);
        $this->cek('Penilaian: tombol PDF per kunjungan + Lembar Hasil gabungan + Rekap; Jadwal: Unduh Program; Lembar: Cetak Lembar Hasil', str_contains($html, 'penilaian/' . $d['j3'] . '/pdf')
            && str_contains($html, 'supervisi/lembar?') && str_contains($html, 'supervisi/rekap?')
            && str_contains((string) $this->panggil(\App\Controllers\Admin\SupervisiJadwal::class, 'admin/supervisi/jadwal', 'GET', 'index', [], $qs), 'supervisi/program?')
            && str_contains((string) $this->panggil(\App\Controllers\Admin\SupervisiPenilaian::class, 'admin/supervisi/penilaian/' . $d['j1'], 'GET', 'lembar', [], [], $d['j1']), 'Cetak Lembar Hasil (PDF)'));

        $item = array_values(array_filter(array_merge(...array_column(MenuAdmin::sidebar(), 'items')), static fn (array $it): bool => $it[0] === 'admin/supervisi/rekap'))[0] ?? null;
        $this->cek('menu: Rekap Supervisi tidak lagi Segera; entri supervisi punya api "supervisi"', $item !== null && empty($item[4])
            && (MenuAdmin::katalog()['supervisi']['api'] ?? null) === ['supervisi'], json_encode($item));
    }

    /** @var array<string, mixed> data uji rekap (dipakai ujiApi) */
    private array $rekapUji = [];

    private function bacaXlsx(string $isi): ?\PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $f = WRITEPATH . 'uji-supervisi-' . getmypid() . '.xlsx';
        file_put_contents($f, $isi);
        try {
            return \PhpOffice\PhpSpreadsheet\IOFactory::load($f);
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($f);
        }
    }

    private function teksLembar(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): string
    {
        $t = '';
        foreach ($ws->toArray(null, false, false, false) as $baris) {
            $t .= implode(' | ', array_map('strval', $baris)) . "\n";
        }

        return $t;
    }

    // =================================================================
    // S4 — API Android
    // =================================================================

    private function ujiApi(): void
    {
        $this->bagian('S4 — API Android (api/v1/supervisi…)');
        $d = $this->rekapUji;
        if ($d === []) {
            $this->cek('data uji API tersedia', false);

            return;
        }
        [$k, $j] = $this->api('admin', 'meta');
        $insMeta = array_column($j['data']['instrumen'] ?? [], null, 'id');
        $this->cek('meta (Admin): periode, instrumen aktif + butir, skor 1–4 + TD, predikat, tindak lanjut, hak semua true', $k === 200
            && ($j['data']['periode']['tahun_ajaran'] ?? '') === $d['tahun_ajaran'] && count($insMeta[$d['i1']]['butir'] ?? []) === $insMeta[$d['i1']]['jumlah_butir']
            && count($j['data']['skor']) === 5 && count($j['data']['predikat']) === 4 && count($j['data']['tindak_lanjut']) === 5
            && ! in_array(false, $j['data']['hak'], true) && isset($j['data']['supervisor']['saran'], $j['data']['guru'][0]['nip']), json_encode(array_keys($j['data'] ?? [])));
        [$k1] = $this->api('operator', 'meta');
        [$k2] = $this->api('guru_piket', 'daftar');
        $this->cek('peran tanpa hak Supervisi (Operator, Guru Piket) → 403', $k1 === 403 && $k2 === 403);
        $this->cek('gerbang: awalan api "supervisi" milik menu Supervisi Guru', HakAkses::bolehApiAlamat('admin', 'supervisi/jadwal') && ! HakAkses::bolehApiAlamat('guru_piket', 'supervisi/jadwal'));

        [$k, $j] = $this->api('admin', 'daftar', ['ta' => $d['tahun_ajaran'], 'semester' => $d['semester'], 'guru_id' => $d['a']['id'], 'status' => 'selesai']);
        $b = array_column($j['data']['baris'] ?? [], null, 'id');
        $this->cek('GET jadwal (saring guru A + selesai): 2 baris, kemajuan/nilai/instrumen{dinilai}/bisa_hapus false, ringkas', $k === 200 && count($b) === 2
            && ($b[$d['j1']]['kemajuan'] ?? '') === 'selesai' && $b[$d['j1']]['nilai_akhir'] === 87.5 && $b[$d['j1']]['instrumen'][0]['dinilai'] === true
            && $b[$d['j1']]['bisa_hapus'] === false && $b[$d['j1']]['jam'] === '08:00' && isset($j['data']['ringkas']['guru_belum']), json_encode([count($b), $b[$d['j1']]['kemajuan'] ?? null, $b[$d['j1']]['nilai_akhir'] ?? null, $b[$d['j1']]['instrumen'] ?? null]));
        [$k, $j] = $this->api('admin', 'saran', ['guru_id' => $d['a']['id']]);
        $this->cek('GET jadwal/saran → 200 daftar', $k === 200 && is_array($j['data']));

        // Tambah, ganda, galat isian.
        $tgl = $this->tanggalSemester(3);
        [$k, $j] = $this->api('admin', 'tambah', [], ['guru_id' => $d['c']['id'], 'supervisor_id' => $d['sup'], 'tanggal' => $tgl, 'jam' => '9:30',
            'instrumen' => [$d['i3'], $d['i1']], 'catatan' => 'ZZUJI api']);
        $id = (int) ($j['data']['id'] ?? 0);
        if ($id > 0) {
            $this->dibuat['supervisi_jadwal'][] = $id;
        }
        $lembar = $j['data']['lembar']['instrumen'] ?? [];
        $this->cek('POST jadwal → 201 detail: jam 09:30, instrumen urut daftar (Perencanaan dulu), lembar butir berkunci "b…", kemajuan belum', $k === 201 && $id > 0
            && $j['data']['jam'] === '09:30' && array_column($lembar, 'id') === [$d['i1'], $d['i3']] && str_starts_with($lembar[0]['butir'][0]['kunci'] ?? '', 'b')
            && $j['data']['kemajuan'] === 'belum' && $j['data']['bisa_hapus'] === true, json_encode($j['message'] ?? ''));
        [$k] = $this->api('admin', 'tambah', [], ['guru_id' => $d['c']['id'], 'supervisor_id' => $d['sup'], 'tanggal' => $tgl, 'instrumen' => [$d['i1']]]);
        [$k2, $j2] = $this->api('admin', 'tambah', [], ['supervisor_id' => $d['sup'], 'tanggal' => $tgl, 'instrumen' => [$d['i1']]]);
        $this->cek('POST jadwal ganda (guru & tanggal sama) → 409; tanpa guru → 422', $k === 409 && $k2 === 422 && str_contains($j2['message'] ?? '', 'guru'));
        if ($id === 0) {
            return;
        }

        // Penilaian lewat aplikasi.
        $kunci1 = array_column($lembar[0]['butir'], 'kunci');
        $kunci3 = array_column($lembar[1]['butir'], 'kunci');
        [$k, $j] = $this->api('admin', 'nilai', [], ['selesai' => false, 'nilai' => [$d['i1'] => ['skor' => [$kunci1[0] => 4, $kunci1[1] => 0], 'catatan' => [$kunci1[0] => 'via HP']]]], $id);
        $l = $j['data']['lembar']['instrumen'] ?? [];
        $this->cek('POST nilai draf → 200 kemajuan draf, skor 4 & 0 (TD) tersimpan, kunci kini "n…", instrumen kedua belum tersimpan', $k === 200 && $j['data']['kemajuan'] === 'draf'
            && ($l[0]['butir'][0]['skor'] ?? null) === 4 && ($l[0]['butir'][1]['skor'] ?? null) === 0 && str_starts_with($l[0]['butir'][0]['kunci'], 'n')
            && $l[0]['butir'][0]['catatan'] === 'via HP' && $l[1]['tersimpan'] === false, json_encode($j['message'] ?? ''));
        $kunci1 = array_column($l[0]['butir'], 'kunci');
        [$k, $j] = $this->api('admin', 'nilai', [], ['selesai' => true, 'nilai' => [$d['i1'] => ['skor' => array_fill_keys($kunci1, 3)]]], $id);
        $this->cek('POST nilai selesai belum lengkap → 422 + data.instrumen_id (tab yang bermasalah)', $k === 422 && ($j['data']['instrumen_id'] ?? 0) === $d['i3'], json_encode($j));
        [$k, $j] = $this->api('admin', 'nilai', [], ['selesai' => true, 'nilai' => [
            $d['i1'] => ['skor' => array_fill_keys($kunci1, 3), 'tindak_lanjut' => ['kelompok'], 'kekuatan' => 'Rapi'],
            $d['i3'] => ['skor' => array_fill_keys($kunci3, 4)],                   // kunci "b…" lama tetap diterima
        ]], $id);
        $this->cek('POST nilai selesai lengkap → 200 status selesai, nilai akhir (75 + 100) / 2 = 87,50 Baik', $k === 200 && $j['data']['status'] === 'selesai'
            && $j['data']['nilai_akhir'] === 87.5 && $j['data']['predikat'] === 'Baik' && $j['data']['lembar']['ringkas']['lengkap'] === true, json_encode($j['message'] ?? ''));
        [$k, $j] = $this->api('admin', 'nilai', [], ['selesai' => false, 'nilai' => []], $id);
        $this->cek('POST nilai draf pada yang sudah selesai → 409', $k === 409);
        [$k, $j] = $this->api('admin', 'ubah', [], ['catatan' => 'ZZUJI api diubah'], $id);
        $this->cek('POST ubah hanya catatan → 200, kunci lain tetap (guru, jam, instrumen), tetap selesai', $k === 200 && $j['data']['catatan'] === 'ZZUJI api diubah'
            && $j['data']['guru_id'] === $d['c']['id'] && $j['data']['jam'] === '09:30' && $j['data']['status'] === 'selesai' && count($j['data']['instrumen']) === 2, json_encode($j['message'] ?? ''));
        [$k] = $this->api('admin', 'hapus', [], [], $id);
        [$k2] = $this->api('admin', 'detail', [], [], 99999999);
        $this->cek('DELETE jadwal yang sudah dinilai → 409; detail id tak ada → 404', $k === 409 && $k2 === 404);

        // Massal, batal, hapus.
        [$m1, $m2] = [min($this->tanggalSemester(5), $this->tanggalSemester(9)), max($this->tanggalSemester(5), $this->tanggalSemester(9))];
        [$k, $j] = $this->api('admin', 'massal', [], ['guru_ids' => [$d['b']['id'], $d['d']['id']], 'supervisor_id' => $d['sup'], 'mulai' => $m1,
            'selesai' => $m2, 'per_hari' => 2, 'instrumen' => [$d['i3']]]);
        $mid = (int) ($j['data']['ids'][0] ?? 0);
        foreach ($j['data']['ids'] ?? [] as $x) {
            $this->dibuat['supervisi_jadwal'][] = (int) $x;
        }
        $this->cek('POST jadwal/massal → 201: 1 dibuat (guru D), guru B (sudah terjadwal) dilewati', $k === 201 && ($j['data']['jumlah'] ?? 0) === 1 && $mid > 0
            && ($j['data']['dilewati'] ?? []) === [$d['b']['nama']], $k . ' ' . ($j['message'] ?? ''));
        [$k, $j] = $this->api('admin', 'massal', [], ['guru_ids' => [$d['b']['id']], 'supervisor_id' => $d['sup'], 'mulai' => $m1, 'selesai' => $m2, 'instrumen' => [$d['i3']]]);
        $this->cek('POST jadwal/massal untuk guru yang semuanya sudah terjadwal → 409', $k === 409, (string) $k);
        if ($mid > 0) {
            [$k] = $this->api('admin', 'hapus', [], [], $mid);
            $this->cek('DELETE jadwal hasil massal → 200', $k === 200);
        }
        [$k, $j] = $this->api('admin', 'ubah', [], ['batal' => true], $d['j3']);
        $this->cek('POST ubah {batal: true} → status batal, kemajuan batal', $k === 200 && $j['data']['status'] === 'batal' && $j['data']['kemajuan'] === 'batal');
        [$k] = $this->api('admin', 'pdf', [], [], $d['j3']);
        $this->cek('GET jadwal/{id}/pdf kunjungan batal → 409', $k === 409);
        [$k, $j] = $this->api('admin', 'hapus', [], [], $d['j3']);
        $this->cek('DELETE jadwal belum dinilai → 200', $k === 200 && $this->db->table('supervisi_jadwal')->where('id', $d['j3'])->countAllResults() === 0);

        // Rekap & unduhan.
        $qs = ['ta' => $d['tahun_ajaran'], 'semester' => $d['semester']];
        [$k, $j] = $this->api('admin', 'rekap', $qs);
        $ra = array_values(array_filter($j['data']['baris'] ?? [], static fn (array $r): bool => $r['guru_id'] === $d['a']['id']))[0] ?? null;
        $this->cek('GET rekap → 200: guru A 68,75 Cukup + tindak_lanjut_label, kolom instrumen berkunci, ringkas, belum', $k === 200 && $ra !== null && $ra['rata_rata'] === 68.75
            && $ra['tindak_lanjut_label'] === ['Pembinaan individual', 'Contoh praktik baik (model)'] && isset($j['data']['instrumen'][0]['kunci'], $j['data']['ringkas']['per_predikat'], $j['data']['belum']));
        $unduh = [
            'program'    => [$qs, [], 'PK'],
            'rekapExcel' => [$qs, [], 'PK'],
            'pdf'        => [[], [$d['j1']], '%PDF'],
            'gabungan'   => [$qs + ['guru_id' => $d['a']['id']], [], '%PDF'],
        ];
        $hasil = [];
        foreach ($unduh as $m => [$get, $arg, $awal]) {
            $res = $this->api('admin', $m, $get, [], false, ...$arg);
            $hasil[$m] = str_starts_with($this->isiUnduhan($res), $awal);
        }
        $this->cek('unduhan API: program & rekap/excel (.xlsx), jadwal/{id}/pdf & lembar gabungan (PDF)', ! in_array(false, $hasil, true), json_encode($hasil));
        [$k] = $this->api('admin', 'gabungan', $qs + ['guru_id' => $d['b']['id']]);
        $this->cek('GET lembar tanpa kunjungan selesai → 404', $k === 404);
    }

    /** Tanggal hari sekolah ke-N dari sekarang yang masih dalam semester berjalan (mundur bila menyeberang semester). */
    private function tanggalSemester(int $n): string
    {
        $kini = Supervisi::periodeSekarang();
        $t    = date('Y-m-d', strtotime('+' . $n . ' day'));

        return Supervisi::periode($t) === $kini ? $t : date('Y-m-d', strtotime('-' . $n . ' day'));
    }

    /**
     * Panggil Api\Supervisi sebagai peran tertentu (token sudah lolos ApiAuthFilter). $arg[0] === false → respons mentah.
     *
     * @return array{0: int, 1: array}|\CodeIgniter\HTTP\ResponseInterface
     */
    private function api(string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $json = true;
        if ($arg !== [] && $arg[0] === false) {
            $json = false;
            array_shift($arg);
        }
        \App\Libraries\ApiAuth::set(['id' => null, 'full_name' => 'ZZUJI Aplikasi ' . $peran, 'role' => $peran]);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/supervisi'), null, new \CodeIgniter\HTTP\UserAgent()))
            ->withMethod($post === [] ? 'GET' : 'POST');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Api\Supervisi();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}(...$arg);

        return $json ? [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []] : $r;
    }

    private function isiUnduhan(mixed $res): string
    {
        if (! $res instanceof \CodeIgniter\HTTP\DownloadResponse) {
            return '';
        }
        ob_start();
        $res->sendBody();

        return (string) ob_get_clean();
    }

    private function panggil(string $kelas, string $alamat, string $http, string $metode, array $post = [], array $get = [], mixed ...$arg): mixed
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), $alamat), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($http);
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => null, 'full_name' => 'ZZUJI Admin', 'username' => 'zzujisupervisi', 'role' => 'admin']);
        if ($http === 'POST') {
            session()->remove(['_ci_old_input', 'error', 'success']);
        }
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }
}
